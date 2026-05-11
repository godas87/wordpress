<?php
/**
 * Redirect 302 da página de listagem de posts (ex. /bicicletas/) para o arquivo canónico
 * da taxonomia cidade (ex. /bicicleta-cidade/mg/belo-horizonte/) quando há localização resolvida.
 *
 * Requer `BAZAR_CIDADE_CANONICAL_REDIRECT` ou filtro `bazar_cidade_canonical_redirect_enabled`.
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chaves GET que podem ser repassadas ao URL de destino (além de *_filter).
 *
 * @return string[]
 */
function bazar_cidade_canonical_redirect_allowed_query_keys()
{
    $keys = array('category', 'order', 'valor_faixa', 'page', 'paged');
    return apply_filters('bazar_cidade_canonical_redirect_allowed_query_keys', $keys);
}

/**
 * true = não redirecionar (parâmetros de listagem / tracking desconhecidos).
 *
 * @return bool
 */
function bazar_cidade_canonical_redirect_request_has_blocking_query()
{
    $allowed = array_flip(bazar_cidade_canonical_redirect_allowed_query_keys());
    $utm = '/^utm_/';

    foreach ($_GET as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $key = (string) $key;
        if ($key === 'bazar_listagem_nacional' || $key === 'bazar_no_cidade_redirect') {
            continue;
        }
        if (preg_match($utm, $key) || in_array($key, array('gclid', 'fbclid', 'msclkid'), true)) {
            continue;
        }
        if (preg_match('/_filter$/', $key)) {
            continue;
        }
        if (isset($allowed[$key])) {
            continue;
        }

        return true;
    }

    return false;
}

/**
 * Monta query string preservada para o term link.
 *
 * @return array<string, string|array>
 */
function bazar_cidade_canonical_redirect_collect_preserved_args()
{
    $out = array();
    $allowed = array_flip(bazar_cidade_canonical_redirect_allowed_query_keys());

    foreach ($_GET as $key => $value) {
        $key = (string) $key;
        if (preg_match('/_filter$/', $key) || isset($allowed[$key])) {
            if ($key === 'paged' || $key === 'page') {
                continue;
            }
            $out[$key] = $value;
        }
    }

    return $out;
}

/**
 * Apenas listagem “nacional” de posts (home de artigos, não a página inicial estática).
 *
 * @return bool
 */
function bazar_is_posts_listing_for_cidade_redirect()
{
    if (!is_home()) {
        return false;
    }
    if (is_front_page()) {
        return false;
    }

    return true;
}

/**
 * URL absoluta da requisição (path + query) para comparação com o destino do redirect.
 *
 * @return string
 */
function bazar_cidade_redirect_current_request_url()
{
    if (empty($_SERVER['HTTP_HOST'])) {
        return '';
    }
    $scheme = is_ssl() ? 'https' : 'http';
    $host = strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])));
    $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';

    return $scheme . '://' . $host . $uri;
}

/**
 * @param string $url
 * @return string
 */
function bazar_normalize_url_for_redirect_compare($url)
{
    $parts = wp_parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return '';
    }
    $path = isset($parts['path']) ? untrailingslashit($parts['path']) : '';
    $path = strtolower($path);
    $query = isset($parts['query']) ? $parts['query'] : '';
    parse_str($query, $q);
    unset($q['bazar_listagem_nacional'], $q['bazar_no_cidade_redirect']);
    if (!empty($q)) {
        ksort($q);
        $query = http_build_query($q);
    } else {
        $query = '';
    }

    return strtolower((string) $parts['host']) . '|' . $path . '?' . $query;
}

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return;
    }
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
        return;
    }
    if (!function_exists('bazar_cidade_canonical_redirect_enabled') || !bazar_cidade_canonical_redirect_enabled()) {
        return;
    }

    if (isset($_GET['bazar_listagem_nacional']) && (string) $_GET['bazar_listagem_nacional'] === '1') {
        $name = bazar_listagem_nacional_opt_out_cookie_name();
        $expire = time() + (int) apply_filters('bazar_listagem_nacional_opt_out_cookie_ttl', 15552000);
        $path = (string) apply_filters('bazar_listagem_nacional_opt_out_cookie_path', '/');
        setcookie($name, '1', $expire, $path, '', is_ssl(), true);
        wp_safe_redirect(remove_query_arg('bazar_listagem_nacional'), 302);
        exit;
    }

    if (function_exists('bazar_has_listagem_nacional_opt_out_cookie') && bazar_has_listagem_nacional_opt_out_cookie()) {
        return;
    }

    if (isset($_GET['bazar_no_cidade_redirect']) && (string) $_GET['bazar_no_cidade_redirect'] === '1') {
        return;
    }

    if (apply_filters('bazar_skip_cidade_canonical_redirect', false)) {
        return;
    }

    if (!bazar_is_posts_listing_for_cidade_redirect()) {
        return;
    }

    if (bazar_cidade_canonical_redirect_request_has_blocking_query()) {
        return;
    }

    if (!function_exists('bazar_get_current_location')) {
        return;
    }

    $location = bazar_get_current_location();
    if (
        !$location
        || empty($location['localizacao'])
    ) {
        return;
    }

    $loc = $location['localizacao'];
    $term_id = 0;
    if (!empty($loc['cidade_term_id'])) {
        $term_id = (int) $loc['cidade_term_id'];
    } elseif (!empty($loc['estado_term_id'])) {
        $term_id = (int) $loc['estado_term_id'];
    }

    if ($term_id < 1) {
        return;
    }

    $term = get_term($term_id, 'cidade');
    if (!$term || is_wp_error($term)) {
        return;
    }

    $link = get_term_link($term);
    if (is_wp_error($link)) {
        return;
    }

    $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
    if ($paged > 1) {
        $link = trailingslashit(untrailingslashit($link)) . 'page/' . $paged . '/';
    }

    $preserved = bazar_cidade_canonical_redirect_collect_preserved_args();
    if (!empty($preserved)) {
        $link = add_query_arg($preserved, $link);
    }

    $current = bazar_cidade_redirect_current_request_url();
    if (
        $current !== ''
        && bazar_normalize_url_for_redirect_compare($current) === bazar_normalize_url_for_redirect_compare($link)
    ) {
        return;
    }

    wp_safe_redirect($link, 302);
    exit;
}, 5);
