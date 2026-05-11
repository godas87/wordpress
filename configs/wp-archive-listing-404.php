<?php
/**
 * Evita 404 da main query em listagens paginadas de anúncios (post) quando a página
 * pedida excede o que a main query calculou, mantendo 200 para o tema renderizar
 * archive.php / index.php com index_query (lista vazia ou coerente com o builder).
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Query vars de taxonomias públicas ligadas a `post` (exclui rewrite false).
 *
 * @return string[]
 */
function bazar_listing_post_taxonomy_query_var_keys()
{
    static $keys = null;
    if ($keys !== null) {
        return $keys;
    }
    $keys = array();
    foreach (get_taxonomies(array('public' => true), 'objects') as $tax) {
        if (empty($tax->object_type) || !in_array('post', (array) $tax->object_type, true)) {
            continue;
        }
        if (false === $tax->rewrite) {
            continue;
        }
        if (empty($tax->query_var)) {
            continue;
        }
        $keys[] = is_string($tax->query_var) ? $tax->query_var : $tax->name;
    }
    return $keys;
}

/**
 * Detecta 404 de paginação em contexto de arquivo de listagem de posts.
 *
 * @param WP_Query $q
 * @return string|false Tipo de contexto ou false.
 */
function bazar_detect_paged_listing_404_context($q)
{
    if (!($q instanceof WP_Query)) {
        return false;
    }
    if (is_admin() || !$q->is_main_query()) {
        return false;
    }
    if (!$q->is_404() || $q->is_feed()) {
        return false;
    }

    $paged = max((int) $q->get('paged'), (int) $q->get('page'));
    if ($paged < 2) {
        return false;
    }

    if (!(bool) apply_filters('bazar_fix_paged_listing_404_enabled', true, $q)) {
        return false;
    }

    $qv = $q->query_vars;
    $posts_page_id = (int) get_option('page_for_posts');
    if ($posts_page_id > 0) {
        if (!empty($qv['page_id']) && (int) $qv['page_id'] === $posts_page_id) {
            return 'posts_page';
        }
        $posts_slug = get_post_field('post_name', $posts_page_id);
        if ($posts_slug && !empty($qv['pagename'])) {
            $parts = explode('/', trim((string) $qv['pagename'], '/'));
            if ($parts && end($parts) === $posts_slug) {
                return 'posts_page';
            }
        }
    }

    if (!empty($qv['category_name'])) {
        return 'category';
    }
    if (!empty($qv['tag'])) {
        return 'tag';
    }
    if (!empty($qv['author_name'])) {
        return 'author';
    }

    foreach (bazar_listing_post_taxonomy_query_var_keys() as $key) {
        if (!empty($qv[$key])) {
            return 'tax';
        }
    }

    if (get_option('show_on_front') === 'posts') {
        if (
            $paged >= 2
            && empty($qv['name'])
            && empty($qv['pagename'])
            && empty($qv['error'])
        ) {
            return 'home_posts';
        }
    }

    return false;
}

/**
 * @param WP_Query $q
 * @param string   $kind Retorno de bazar_detect_paged_listing_404_context().
 */
function bazar_unstick_paged_listing_404_flags($q, $kind)
{
    if (!($q instanceof WP_Query) || !$kind) {
        return;
    }

    $q->is_404 = false;

    switch ($kind) {
        case 'posts_page':
        case 'home_posts':
            $q->is_home = true;
            $q->is_archive = false;
            $q->is_category = false;
            $q->is_tax = false;
            $q->is_tag = false;
            $q->is_author = false;
            break;
        case 'category':
            $q->is_archive = true;
            $q->is_category = true;
            $q->is_tax = false;
            $q->is_tag = false;
            $q->is_author = false;
            $q->is_home = false;
            break;
        case 'tag':
            $q->is_archive = true;
            $q->is_tag = true;
            $q->is_category = false;
            $q->is_tax = false;
            $q->is_author = false;
            $q->is_home = false;
            break;
        case 'tax':
            $q->is_archive = true;
            $q->is_tax = true;
            $q->is_category = false;
            $q->is_tag = false;
            $q->is_author = false;
            $q->is_home = false;
            break;
        case 'author':
            $q->is_archive = true;
            $q->is_author = true;
            $q->is_category = false;
            $q->is_tax = false;
            $q->is_tag = false;
            $q->is_home = false;
            break;
        default:
            $q->is_404 = true;
            break;
    }

    do_action('bazar_after_unstick_paged_listing_404', $q, $kind);
}

add_filter('pre_handle_404', function ($preempt, $wp_query) {
    if ($preempt) {
        return $preempt;
    }
    if (bazar_detect_paged_listing_404_context($wp_query)) {
        return true;
    }
    return $preempt;
}, 10, 2);

add_action('wp', function () {
    global $wp_query;
    if (!($wp_query instanceof WP_Query)) {
        return;
    }
    $kind = bazar_detect_paged_listing_404_context($wp_query);
    if ($kind) {
        bazar_unstick_paged_listing_404_flags($wp_query, $kind);
    }
}, 0);
