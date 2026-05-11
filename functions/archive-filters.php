<?php
/**
 * Funções para gerenciar filtros dinâmicos em archives
 * 
 * Este arquivo contém funções helper para:
 * - Determinar categorias ativas baseado no contexto
 * - Buscar termos baseados na query atual (filtros dinâmicos)
 * - Combinar taxonomias baseadas em categorias
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retorna taxonomias baseadas nas categorias ativas
 * 
 * @param array $categories Array de slugs de categorias ativas
 * @return array Array associativo de taxonomias ['taxonomy' => 'Label']
 */
if (!function_exists('bazar_get_taxonomies_by_categories')) {
    function bazar_get_taxonomies_by_categories( $categories ) {
        // Taxonomias comuns a todas as categorias
        $taxonomies_common = array(
            'modalidade' => 'Modalidade',
            'marca-modelo' => 'Marca/Modelo',
            'conservacao' => 'Conservacao',
            'genero' => 'Genero',
            'idade' => 'Idade',
            'material' => 'Material',
            'cor' => 'Cor',
            'negociacao' => 'Negociacao'
        );
        
        // Taxonomias específicas por categoria
        $taxonomies_bicicletas = array(
            'componente' => 'Componente'
        );
        
        $taxonomies_pecas = array(
            'componente' => 'Componente',
            'medida' => 'Medida'
        );
        
        $taxonomies_acessorios = array(
            'acessorio' => 'Acessório',
            'medida' => 'Medida'
        );
        
        // Iniciar com taxonomias comuns
        $result = $taxonomies_common;
        
        // Adicionar taxonomias específicas baseadas nas categorias ativas
        foreach ($categories as $category) {
            switch ($category) {
                case 'bicicleta':
                    $result = array_merge($result, $taxonomies_bicicletas);
                    break;
                case 'peca':
                    $result = array_merge($result, $taxonomies_pecas);
                    break;
                case 'acessorio':
                    $result = array_merge($result, $taxonomies_acessorios);
                    break;
            }
        }        
        // Remover duplicatas mantendo a primeira ocorrência
        return $result;
    }
}

/**
 * Converte $query->posts (objetos ou IDs) numa lista de IDs int.
 *
 * @param array $posts
 * @return int[]
 */
if (!function_exists('bazar_wp_query_posts_list_to_ids')) {
    function bazar_wp_query_posts_list_to_ids($posts)
    {
        $posts = array_values(array_filter((array) $posts));
        if (empty($posts)) {
            return array();
        }
        if (is_numeric($posts[0])) {
            return array_values(array_unique(array_map('intval', $posts)));
        }
        return array_values(array_unique(array_map('intval', wp_list_pluck($posts, 'ID'))));
    }
}

/**
 * IDs de todos os posts que cumprem a mesma query da listagem (facets na sidebar).
 *
 * Antes usávamos só $query->posts = página atual (ex. 20), e a sidebar omitia categorias/taxonomias
 * presentes noutras páginas — ex.: "Peça" desaparecia com filtros de componente.
 *
 * @param WP_Query $query
 * @return int[]
 */
if (!function_exists('bazar_get_post_ids_for_archive_facets')) {
    function bazar_get_post_ids_for_archive_facets($query)
    {
        if (!$query || !($query instanceof WP_Query)) {
            return array();
        }

        // Cache in-request: a mesma query é consultada várias vezes (uma por taxonomia
        // na sidebar). Sem cache, cada chamada pode disparar uma WP_Query extra.
        static $cache = array();
        $cache_key = spl_object_hash($query);
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $found = (int) $query->found_posts;
        $current = bazar_wp_query_posts_list_to_ids($query->posts);

        if ($found === 0) {
            return $cache[$cache_key] = array();
        }

        $ppp = (int) $query->get('posts_per_page');
        if ($ppp === -1 || count($current) >= $found) {
            return $cache[$cache_key] = $current;
        }

        $max = (int) apply_filters('bazar_facet_requery_max_posts', 8000, $query);
        $qv = $query->query_vars;
        $qv['posts_per_page'] = ($max > 0 && $found > $max) ? $max : -1;
        $qv['paged'] = 1;
        $qv['fields'] = 'ids';
        $qv['no_found_rows'] = true;
        $qv['update_post_meta_cache'] = false;
        $qv['update_post_term_cache'] = false;

        $q2 = new WP_Query($qv);
        $ids = bazar_wp_query_posts_list_to_ids($q2->posts);
        wp_reset_postdata();

        return $cache[$cache_key] = $ids;
    }
}

/**
 * Retorna os term_ids de uma taxonomia que existem entre os posts da query fornecida.
 *
 * Helper de baixo nível usado por filtros dinâmicos. Executa 1 SQL (termos ↔ posts)
 * excluindo posts com status 'vendido'. O resultado é cacheado em memória por
 * (taxonomia + query) para que múltiplas leituras no mesmo request não repitam a SQL.
 *
 * @param string $taxonomy
 * @param WP_Query|null $query
 * @return int[] Lista de term_ids (pode ser vazia)
 */
if (!function_exists('bazar_get_available_term_ids')) {
    function bazar_get_available_term_ids($taxonomy, $query = null)
    {
        global $index_query, $wpdb;

        if ($query === null) {
            $query = $index_query;
        }

        if (!$query || !($query instanceof WP_Query)) {
            return array();
        }

        $post_ids = bazar_get_post_ids_for_archive_facets($query);
        if (empty($post_ids)) {
            return array();
        }

        static $cache = array();
        $cache_key = $taxonomy . ':' . spl_object_hash($query);
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $vendido_term_id = bazar_get_vendido_term_id();
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));

        if ($vendido_term_id > 0) {
            $sql = $wpdb->prepare("
                SELECT DISTINCT tt.term_id
                FROM {$wpdb->term_taxonomy} tt
                INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                WHERE tt.taxonomy = %s
                AND tr.object_id IN ($placeholders)
                AND tr.object_id NOT IN (
                    SELECT DISTINCT tr2.object_id
                    FROM {$wpdb->term_relationships} tr2
                    INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                    WHERE tt2.taxonomy = 'status'
                    AND tt2.term_id = %d
                )
            ", array_merge(array($taxonomy), $post_ids, array($vendido_term_id)));
        } else {
            $sql = $wpdb->prepare("
                SELECT DISTINCT tt.term_id
                FROM {$wpdb->term_taxonomy} tt
                INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                WHERE tt.taxonomy = %s
                AND tr.object_id IN ($placeholders)
            ", array_merge(array($taxonomy), $post_ids));
        }

        $term_ids = array_map('intval', (array) $wpdb->get_col($sql));
        return $cache[$cache_key] = $term_ids;
    }
}

/**
 * Busca termos de uma taxonomia que existem nos resultados da query atual.
 *
 * Essencial para filtros dinâmicos: apenas termos com posts no contexto são exibidos.
 * Internamente reusa bazar_get_available_term_ids() (que já faz cache in-request).
 *
 * @param string $taxonomy Nome da taxonomia
 * @param WP_Query|null $query Query com resultados (se null, usa $index_query global)
 * @param array $args Args extras para get_terms() (ex.: 'parent' => 0)
 * @param int|null $limit Limite de termos
 * @param bool $force_all Se true, ignora a query e retorna todos os termos da taxonomia
 * @return array|WP_Error
 */
if (!function_exists('bazar_get_terms_from_query')) {
    function bazar_get_terms_from_query(
        $taxonomy,
        $query = null,
        $args = array(),
        $limit = null,
        $force_all = false
    ) {

        if ($force_all) {
            $default_args = array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'orderby'    => 'count',
                'order'      => 'DESC',
            );
            return get_terms(wp_parse_args($args, $default_args));
        }

        $term_ids = bazar_get_available_term_ids($taxonomy, $query);
        if (empty($term_ids)) {
            return array();
        }

        if ($limit !== null && $limit > 0) {
            $term_ids = array_slice($term_ids, 0, $limit);
        }

        $default_args = array(
            'taxonomy'   => $taxonomy,
            'include'    => $term_ids,
            'hide_empty' => false,
            'orderby'    => 'count',
            'order'      => 'DESC',
        );

        return get_terms(wp_parse_args($args, $default_args));
    }
}

/**
 * Verifica se um termo está selecionado nos filtros
 * 
 * @param string $term_slug Slug do termo
 * @param string $filter_key Chave do filtro (ex: 'category', 'componente_filter')
 * @param array $post_data Dados de $_GET processados
 * @param WP_Term|null $current_term Termo atual da URL
 * @param string|null $parent_slug Slug do parent (para componentes hierárquicos)
 * @return bool True se o termo está selecionado
 */
if (!function_exists('bazar_is_term_selected')) {
    function bazar_is_term_selected($term_slug, $filter_key, $post_data, $current_term = null, $parent_slug = null) {
        if ($current_term && $current_term->slug === $term_slug) {
            return true;
        }
        
        if (!isset($post_data[$filter_key])) {
            return false;
        }
        
        $values = bazar_normalize_filter_value($post_data[$filter_key]);
        
        // Para componente_filter, verificar formato hierárquico (parent-child)
        if ($filter_key === 'componente_filter' && $parent_slug) {
            $expected_value_dash = trim($parent_slug) . '-' . trim($term_slug);
            $expected_value_colon = trim($parent_slug) . ':' . trim($term_slug);
            foreach ($values as $value) {
                $trimmed_value = trim($value);
                if ($trimmed_value === $expected_value_dash || $trimmed_value === $expected_value_colon) {
                    return true;
                }
                // URL manual / bookmark: slug completo do filho (ex. aro-700c) sem repetir o pai no valor
                if ($trimmed_value === trim($term_slug)) {
                    return true;
                }
            }
            return false;
        }
        
        return in_array($term_slug, $values);
    }
}

/**
 * Normaliza valores de filtro
 * 
 * @param mixed $value Valor a normalizar
 * @return array Array de valores normalizados
 */
if (!function_exists('bazar_normalize_filter_value')) {
    function bazar_normalize_filter_value($value) {
        if (is_array($value)) {
            return array_map('trim', $value);
        }
        return array_map('trim', explode(',', $value));
    }
}
?>