<?php
/**
 * Helpers utilitários gerais
 * 
 * Este arquivo contém funções helper utilitárias usadas em vários lugares:
 * - Obter IP do usuário
 * - Limpar transients
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obtém o IP real do usuário
 * 
 * Verifica múltiplos headers HTTP para obter o IP real do cliente,
 * considerando proxies, load balancers e CDNs (Cloudflare, etc.)
 * 
 * @return string IP do usuário ou '0.0.0.0' como fallback
 */
if (!function_exists('bazar_get_user_ip')) {
    function bazar_get_user_ip()
    {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',      // Cloudflare
            'HTTP_X_REAL_IP',             // Nginx proxy
            'HTTP_CLIENT_IP',              // Proxy
            'HTTP_X_FORWARDED_FOR',       // Proxy/Load balancer
            'HTTP_X_FORWARDED',           // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',   // Cluster
            'HTTP_FORWARDED_FOR',         // Proxy
            'HTTP_FORWARDED',             // Proxy
            'REMOTE_ADDR'                 // IP direto
        );

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true && !empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];

                // Se for X_FORWARDED_FOR ou similar, pegar o primeiro IP (IP real do cliente)
                if (in_array($key, array('HTTP_X_FORWARDED_FOR', 'HTTP_FORWARDED_FOR'))) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Validar se é um IP válido (excluir IPs privados e reservados)
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }

        // Fallback para REMOTE_ADDR
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}

/**
 * Limpa TODOS os transients do WordPress
 * 
 * Remove tanto transients expirados quanto não expirados.
 * Útil para limpeza completa do banco de dados.
 * 
 * @param string|null $prefix Prefixo para filtrar transients (ex: 'bazar_'). Se null, limpa todos.
 * @param int $limit Limite de transients a processar por execução (padrão: 1000, 0 = sem limite)
 * @return array Estatísticas da limpeza ['deleted' => int, 'processed' => int, 'errors' => int]
 */
if (!function_exists('bazar_clear_all_transients')) {
    function bazar_clear_all_transients($prefix = null, $limit = 1000)
    {
        global $wpdb;

        $stats = array(
            'deleted' => 0,
            'processed' => 0,
            'errors' => 0
        );

        // Construir query base
        $where_clauses = array(
            "(option_name LIKE '_transient_%' OR option_name LIKE '_transient_timeout_%')"
        );

        $query_params = array();

        // Se prefix foi especificado, adicionar filtro
        if ($prefix !== null && !empty($prefix)) {
            $where_clauses[] = "(option_name LIKE %s OR option_name LIKE %s)";
            $query_params[] = $wpdb->esc_like('_transient_' . $prefix) . '%';
            $query_params[] = $wpdb->esc_like('_transient_timeout_' . $prefix) . '%';
        }

        // Construir query completa
        $where_sql = implode(' AND ', $where_clauses);

        // Preparar query com parâmetros
        if (!empty($query_params)) {
            // Adicionar LIMIT se necessário
            if ($limit > 0) {
                $query_params[] = $limit;
                $query = $wpdb->prepare(
                    "SELECT option_name 
                     FROM {$wpdb->options} 
                     WHERE {$where_sql}
                     LIMIT %d",
                    $query_params
                );
            } else {
                $query = $wpdb->prepare(
                    "SELECT option_name 
                     FROM {$wpdb->options} 
                     WHERE {$where_sql}",
                    $query_params
                );
            }
        } else {
            // Sem prefix, query direta
            if ($limit > 0) {
                $query = $wpdb->prepare(
                    "SELECT option_name 
                     FROM {$wpdb->options} 
                     WHERE {$where_sql}
                     LIMIT %d",
                    $limit
                );
            } else {
                $query = "SELECT option_name 
                          FROM {$wpdb->options} 
                          WHERE {$where_sql}";
            }
        }

        $transients = $wpdb->get_results($query, ARRAY_A);

        if (empty($transients)) {
            return $stats;
        }

        $stats['processed'] = count($transients);

        foreach ($transients as $transient) {
            $option_name = $transient['option_name'];

            // Extrair nome do transient (remover prefixos)
            $transient_name = str_replace(array('_transient_timeout_', '_transient_'), '', $option_name);

            // Deletar tanto o timeout quanto o valor do transient
            $deleted_timeout = $wpdb->delete(
                $wpdb->options,
                array('option_name' => '_transient_timeout_' . $transient_name),
                array('%s')
            );

            $deleted_value = $wpdb->delete(
                $wpdb->options,
                array('option_name' => '_transient_' . $transient_name),
                array('%s')
            );

            if ($deleted_timeout !== false && $deleted_value !== false) {
                $stats['deleted']++;
            } else {
                $stats['errors']++;
            }
        }

        return $stats;
    }
}

/**
 * Limpa apenas transients com prefixo 'bazar_'
 * Wrapper para bazar_clear_all_transients() com prefixo padrão
 * 
 * @param int $limit Limite de transients a processar (padrão: 1000, 0 = sem limite)
 * @return array Estatísticas da limpeza
 */
if (!function_exists('bazar_clear_bazar_transients')) {
    function bazar_clear_bazar_transients($limit = 1000)
    {
        return bazar_clear_all_transients('bazar_', $limit);
    }
}

/**
 * Seleciona um índice determinístico de um array baseado no contexto da página
 * 
 * Esta função gera um índice consistente baseado no ID da página ou slug,
 * garantindo que cada página sempre mostre a mesma variação, mas diferentes entre páginas.
 * Isso é melhor para SEO porque:
 * - Cada página tem conteúdo único e consistente
 * - Não é visto como manipulação (é determinístico)
 * - Melhor para cache e performance
 * 
 * @param array $options Array de opções para escolher
 * @param string|null $context Contexto adicional (ex: 'filtros', 'benefits'). Se null, usa ID da página
 * @return mixed Item selecionado do array
 */
if (!function_exists('bazar_get_deterministic_variation')) {
    function bazar_get_deterministic_variation($options, $context = null)
    {
        if (empty($options) || !is_array($options)) {
            return null;
        }

        // Gerar seed determinístico baseado no contexto
        $seed = '';

        // Priorizar ID da página (mais confiável)
        if (is_singular() || is_page()) {
            global $post;
            if ($post && isset($post->ID)) {
                $seed = 'page_' . $post->ID;
            }
        }

        // Se não tem ID, usar slug ou URI
        if (empty($seed)) {
            if (is_page()) {
                global $post;
                if ($post && isset($post->post_name)) {
                    $seed = 'slug_' . $post->post_name;
                }
            } else {
                // Usar URI atual como fallback
                $seed = 'uri_' . $_SERVER['REQUEST_URI'];
            }
        }

        // Adicionar contexto se fornecido
        if ($context !== null) {
            $seed .= '_' . $context;
        }

        // Gerar hash determinístico
        $hash = crc32($seed);

        // Converter para índice válido do array
        $index = abs($hash) % count($options);

        return $options[$index];
    }
}

/**
 * URL absoluta da página atual para canonical e hreflang no head.
 *
 * Resolve o canonical por contexto de página:
 * - Singular (post/page/CPT): {@see wp_get_canonical_url()} (trata paginação de comentários, etc.).
 * - Taxonomia/categoria/tag: {@see get_term_link()} + paginação (/page/N/).
 * - Post type archive: {@see get_post_type_archive_link()} + paginação.
 * - Autor: {@see get_author_posts_url()} + paginação.
 * - Front page / home (blog): home ou página de posts + paginação.
 * - Fallback: home_url(path da requisição), descartando query string de filtros.
 *
 * IMPORTANTE: não chamar wp_get_canonical_url() fora de contexto singular — ela cai no
 * $GLOBALS['post'] (que pode estar populado com o primeiro post do loop principal em archives)
 * e devolve o permalink de um produto aleatório como canonical, quebrando o SEO das taxonomias.
 *
 * Não usar $_SERVER['REQUEST_SCHEME'] sozinho — em produção (proxy/CDN) vem vazio e gera href="://..."
 *
 * @return string
 */
if (!function_exists('bazar_get_current_canonical_url_for_header')) {
    function bazar_get_current_canonical_url_for_header()
    {
        $paged = 0;
        if (function_exists('get_query_var')) {
            $paged = max((int) get_query_var('paged'), (int) get_query_var('page'));
        }

        $append_paged = static function ($url) use ($paged) {
            if (!is_string($url) || $url === '' || $paged < 2) {
                return $url;
            }
            return trailingslashit(untrailingslashit($url)) . 'page/' . $paged . '/';
        };

        if (function_exists('is_singular') && is_singular() && function_exists('wp_get_canonical_url')) {
            $canonical = wp_get_canonical_url();
            if (is_string($canonical) && $canonical !== '') {
                return $canonical;
            }
        }

        if (
            function_exists('is_category') && function_exists('is_tag') && function_exists('is_tax')
            && (is_category() || is_tag() || is_tax())
        ) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && isset($term->term_id)) {
                $link = get_term_link($term);
                if (is_string($link) && $link !== '') {
                    return $append_paged($link);
                }
            }
        }

        if (function_exists('is_post_type_archive') && is_post_type_archive()) {
            $link = get_post_type_archive_link(get_post_type());
            if (is_string($link) && $link !== '') {
                return $append_paged($link);
            }
        }

        if (function_exists('is_author') && is_author()) {
            $author_id = (int) get_queried_object_id();
            if ($author_id > 0) {
                $link = get_author_posts_url($author_id);
                if (is_string($link) && $link !== '') {
                    return $append_paged($link);
                }
            }
        }

        if (function_exists('is_front_page') && is_front_page()) {
            return home_url('/');
        }

        if (function_exists('is_home') && is_home()) {
            $posts_page_id = (int) get_option('page_for_posts');
            $link = $posts_page_id ? get_permalink($posts_page_id) : home_url('/');
            if (is_string($link) && $link !== '') {
                return $append_paged($link);
            }
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        if (function_exists('home_url')) {
            return home_url($path);
        }

        $scheme = 'http';
        if (function_exists('is_ssl') && is_ssl()) {
            $scheme = 'https';
        } elseif (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https') {
            $scheme = 'https';
        }

        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        if ($host === '') {
            return '';
        }

        return esc_url_raw($scheme . '://' . $host . $path);
    }
}
?>