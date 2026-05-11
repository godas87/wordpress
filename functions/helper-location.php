<?php
/**
 * Helpers para gerenciamento de localização
 * 
 * Este arquivo contém funções helper para:
 * - Obter localização atual do usuário (considerando contexto da página)
 * - Aplicar ordenação por proximidade em WP_Query
 * - Detectar localização de páginas de taxonomia cidade
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obtém localização atual do usuário considerando contexto da página
 * 
 * Prioridade:
 * 1. Página de taxonomia cidade (is_tax('cidade')) - localização temporária
 * 2. Usuário logado (user_meta)
 * 3. localStorage (via JavaScript, não disponível no PHP)
 * 
 * @return array|null Dados de localização no formato padrão ou null
 */
if (!function_exists('bazar_get_current_location')) {
    function bazar_get_current_location()
    {
        try {
            global $geo_api;

            if (!$geo_api) {
                $geo_api = BazarBikes_GeoAPI::getInstance();
            }

            $location = $geo_api->get_smart_location();

            // Verificar se tem dados válidos (cidade ou estado)
            // A estrutura retornada é: ['localizacao' => [...], 'proximidade' => [...], 'meta' => [...]]
            if ($location && is_array($location) && isset($location['localizacao'])) {
                $has_valid = (
                    !empty($location['localizacao']['cidade'])
                    || !empty($location['localizacao']['estado'])
                    || !empty($location['localizacao']['estado_sigla'])
                );

                if ($has_valid) {
                    return $location;
                }
            }

            return null;
        } catch (Exception $e) {
            // Em caso de erro, retornar null silenciosamente para não quebrar páginas públicas
            error_log('bazar_get_current_location() error: ' . $e->getMessage());
            return null;
        }
    }
}

/**
 * GEO implícito na listagem de anúncios (index.php / archive.php → index_query).
 *
 * Quando ativo, o Archive Query Builder aplica cidade/estado de perfil ou cache Geo API
 * na tax_query. Quando inativo (padrão), listagens sem termo cidade na URL ficam nacionais,
 * exceto filtros explícitos na URL ou redirect para arquivo de cidade (ver documentação).
 *
 * Ativar em wp-config.php:
 *   define('BAZAR_GEO_LISTAGEM_ATIVO', true);
 * Ou:
 *   add_filter('bazar_archive_geo_implicit_enabled', '__return_true');
 *
 * Precedência (documentado em config/docs/GEO-LISTAGEM-E-REGRAS.md): filtros/taxonomia na URL
 * e escolha explícita de localidade prevalecem; este flag só libera o uso de localização implícita.
 *
 * @return bool
 */
if (!function_exists('bazar_archive_geo_implicit_listing_enabled')) {
    function bazar_archive_geo_implicit_listing_enabled()
    {
        if (defined('BAZAR_GEO_LISTAGEM_ATIVO')) {
            return (bool) BAZAR_GEO_LISTAGEM_ATIVO;
        }

        return (bool) apply_filters('bazar_archive_geo_implicit_enabled', false);
    }
}

/**
 * Redirect HTTP 302 da listagem nacional (página de posts) para o arquivo canónico da taxonomia `cidade`.
 *
 * Desligado por defeito. Ativar:
 *   define('BAZAR_CIDADE_CANONICAL_REDIRECT', true);
 * ou `add_filter('bazar_cidade_canonical_redirect_enabled', '__return_true');`
 *
 * Opt-out (cookie HttpOnly): parâmetro único GET `?bazar_listagem_nacional=1` grava o cookie e
 * remove o parâmetro da URL (ver `wp-cidade-canonical-redirect.php`).
 *
 * @return bool
 */
if (!function_exists('bazar_cidade_canonical_redirect_enabled')) {
    function bazar_cidade_canonical_redirect_enabled()
    {
        if (defined('BAZAR_CIDADE_CANONICAL_REDIRECT')) {
            return (bool) BAZAR_CIDADE_CANONICAL_REDIRECT;
        }

        return (bool) apply_filters('bazar_cidade_canonical_redirect_enabled', false);
    }
}

/**
 * Nome do cookie que impede redirect para arquivo de cidade (listagem nacional explícita).
 *
 * @return string
 */
if (!function_exists('bazar_listagem_nacional_opt_out_cookie_name')) {
    function bazar_listagem_nacional_opt_out_cookie_name()
    {
        return apply_filters('bazar_listagem_nacional_opt_out_cookie_name', 'bazar_skip_cidade_canonical');
    }
}

/**
 * @return bool
 */
if (!function_exists('bazar_has_listagem_nacional_opt_out_cookie')) {
    function bazar_has_listagem_nacional_opt_out_cookie()
    {
        $name = bazar_listagem_nacional_opt_out_cookie_name();
        return !empty($_COOKIE[$name]) && (string) $_COOKIE[$name] === '1';
    }
}

/**
 * Em URLs /bicicleta-cidade/{estado}/{cidade}/ garante o termo da cidade (filho),
 * não o do estado (pai). Quando o objeto consultado fica no estado, a tax_query usa
 * include_children e lista todo o estado — este helper corrige lendo o slug da URL.
 *
 * @param WP_Term|null $queried Se null, usa get_queried_object().
 * @return WP_Term|WP_Post|WP_Post_Type|object|null
 */
if (!function_exists('bazar_parse_request_path_for_cidade_resolve')) {
    /**
     * Path da requisição sem query string, sem prefixo do diretório do WordPress (se houver).
     * Evita falhar o regex de cidade quando REQUEST_URI inclui subpasta ou formato inesperado.
     *
     * @return string Path normalizado (sem barra inicial/final), ex.: bicicleta-cidade/mg/belo-horizonte
     */
    function bazar_parse_request_path_for_cidade_resolve()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ($uri === '') {
            return '';
        }
        $path = wp_parse_url($uri, PHP_URL_PATH);
        if (!is_string($path)) {
            $path = '';
        }
        $path = rawurldecode($path);
        $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
        if (is_string($home_path) && $home_path !== '' && $home_path !== '/') {
            $home_path = untrailingslashit($home_path);
            if ($home_path !== '' && strpos($path, $home_path) === 0) {
                $path = (string) substr($path, strlen($home_path));
            }
        }
        return trim($path, '/');
    }
}

if (!function_exists('bazar_resolve_cidade_archive_queried_term')) {
    function bazar_resolve_cidade_archive_queried_term($queried = null)
    {
        if ($queried === null) {
            $queried = get_queried_object();
        }
        if (!$queried || is_wp_error($queried)) {
            return $queried;
        }
        if (!is_tax('cidade') || !isset($queried->taxonomy) || $queried->taxonomy !== 'cidade') {
            return $queried;
        }

        $path = function_exists('bazar_parse_request_path_for_cidade_resolve')
            ? bazar_parse_request_path_for_cidade_resolve()
            : '';
        if ($path === '') {
            return $queried;
        }

        // Qualquer prefixo antes de bicicleta-cidade (ex.: WP em subpasta /bazar/bicicleta-cidade/mg/belo-horizonte/).
        // O padrão antigo (^|/)bicicleta-cidade falhava porque o carácter antes de "bicicleta" era a última letra de "bazar", não "/".
        if (!preg_match('#bicicleta-cidade/([^/]+)/([^/]+)(?:/page/\d+)?$#i', $path, $m)) {
            return $queried;
        }

        $estado_slug = sanitize_title($m[1]);
        $cidade_slug = sanitize_title($m[2]);
        if ($estado_slug === '' || $cidade_slug === '' || $cidade_slug === 'page') {
            return $queried;
        }

        $city_term = get_term_by('slug', $cidade_slug, 'cidade');
        if (!$city_term || is_wp_error($city_term)) {
            return $queried;
        }

        if ((int) $city_term->parent <= 0) {
            return $queried;
        }

        $parent = get_term($city_term->parent, 'cidade');
        if (!$parent || is_wp_error($parent) || (int) $parent->parent !== 0) {
            return $queried;
        }

        if ($parent->slug !== $estado_slug) {
            return $queried;
        }

        return $city_term;
    }
}

/**
 * Aplica ordenação por proximidade na WP_Query
 * 
 * Esta função modifica os argumentos da query ANTES de executá-la,
 * adicionando ordenação por proximidade de CEP.
 * Funciona apenas se o usuário tiver CEP definido.
 * 
 * IMPORTANTE: Esta função deve ser chamada ANTES de executar WP_Query,
 * modificando diretamente os $args ao invés de usar filtros globais.
 * 
 * @param array $args Array de argumentos da WP_Query (será modificado)
 * @param string|array|null $user_location CEP do usuário (string) ou dados completos de localização (array) - opcional
 * @return array Array de argumentos modificado
 */
if (!function_exists('bazar_apply_proximity_ordering')) {
    function bazar_apply_proximity_ordering(&$args, $user_location = null)
    {

        global $geo_api;

        $location = null;
        $user_cep = null;

        // Se $user_location é um array (dados completos de localização), usar diretamente
        if (is_array($user_location) && !empty($user_location)) {
            $location = $user_location;
            $user_cep = $location['localizacao']['cep'] ?? null;
        }
        // Se $user_location é uma string (CEP), buscar localização completa
        elseif (is_string($user_location) && !empty($user_location)) {
            $user_cep = $user_location;
            $location = bazar_get_current_location();
        }
        // Se não foi passado nada, buscar localização atual
        else {
            $location = bazar_get_current_location();
            if ($location && !empty($location['localizacao']['cep'])) {
                $user_cep = $location['localizacao']['cep'];
            } else {
                return $args; // Sem CEP, retornar args sem modificação
            }
        }

        // Validar CEP
        if (empty($user_cep)) {
            return $args; // Sem CEP, retornar args sem modificação
        }

        // Usar dados de proximidade da localização (já calculados, evita chamada de API)
        if ($location && !empty($location['proximidade'])) {
            // Usar dados de proximidade já calculados (do user_meta ou calculados)
            $proximidade = $location['proximidade'];
            $user_regiao_postal = $proximidade['regiao_postal'] ?? '';
            $user_sub_regiao = $proximidade['sub_regiao'] ?? '';
            $user_setor = $proximidade['setor'] ?? '';
            $user_subsetor = $proximidade['subsetor'] ?? '';
        } else {
            $geo_api = BazarBikes_GeoAPI::getInstance();
            $proximidade = $geo_api->get_proximity_data_by_cep($user_cep);
            $user_regiao_postal = $proximidade['proximidade_regiao_postal'] ?? '';
            $user_sub_regiao = $proximidade['proximidade_sub_regiao'] ?? '';
            $user_setor = $proximidade['proximidade_setor'] ?? '';
            $user_subsetor = $proximidade['proximidade_subsetor'] ?? '';
        }

        // Obter cidade e estado do usuário para fallback (anúncios antigos sem meta fields)
        $user_cidade = $location['localizacao']['cidade'] ?? '';
        $user_estado_sigla = $location['localizacao']['estado_sigla'] ?? '';

        // Adicionar filtro específico para esta query usando posts_clauses
        // Usar variável estática para garantir que o filtro seja aplicado apenas uma vez
        static $proximity_filter_added = false;
        if ($proximity_filter_added) {
            return $args; // Já foi adicionado, retornar args sem modificação
        }

        // Criar função única para o filtro
        $proximity_filter = function ($clauses, $wp_query) use ($user_regiao_postal, $user_sub_regiao, $user_setor, $user_subsetor, $user_cidade, $user_estado_sigla) {

            global $wpdb;

            // Adicionar JOIN com post_meta para dados de proximidade (anúncios novos)
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_subsetor ON ({$wpdb->posts}.ID = pm_subsetor.post_id AND pm_subsetor.meta_key = 'proximidade_subsetor')";
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_setor ON ({$wpdb->posts}.ID = pm_setor.post_id AND pm_setor.meta_key = 'proximidade_setor')";
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_sub_regiao ON ({$wpdb->posts}.ID = pm_sub_regiao.post_id AND pm_sub_regiao.meta_key = 'proximidade_sub_regiao')";
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_regiao_postal ON ({$wpdb->posts}.ID = pm_regiao_postal.post_id AND pm_regiao_postal.meta_key = 'proximidade_regiao_postal')";

            // FALLBACK: JOIN com taxonomia 'cidade' para anúncios antigos sem meta fields
            // Buscar cidade do anúncio (termo com parent > 0) e estado (termo parent)
            // Usar subquery para garantir apenas uma cidade por post (pega a primeira)
            $clauses['join'] .= " LEFT JOIN (
                SELECT tr.object_id, 
                    t_cidade.name AS cidade_name, 
                    t_estado.slug AS estado_slug
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt_cidade ON (tr.term_taxonomy_id = tt_cidade.term_taxonomy_id AND tt_cidade.taxonomy = 'cidade' AND tt_cidade.parent > 0)
                INNER JOIN {$wpdb->terms} t_cidade ON (tt_cidade.term_id = t_cidade.term_id)
                LEFT JOIN {$wpdb->term_taxonomy} tt_estado ON (tt_cidade.parent = tt_estado.term_id AND tt_estado.taxonomy = 'cidade')
                LEFT JOIN {$wpdb->terms} t_estado ON (tt_estado.term_id = t_estado.term_id)
                GROUP BY tr.object_id
            ) AS cidade_terms ON ({$wpdb->posts}.ID = cidade_terms.object_id)";

            // Criar campo de ordenação por proximidade com fallback para taxonomia
            // Prioridade: 
            // 1. Mesmo subsetor (meta field) - anúncios novos
            // 2. Mesmo setor (meta field) - anúncios novos
            // 3. Mesma sub_regiao (meta field) - anúncios novos
            // 4. Mesma regiao_postal (meta field) - anúncios novos
            // 4. Mesma cidade (taxonomia) - anúncios antigos (fallback) - mesma prioridade que regiao_postal
            // 5. Outras regiões
            // Escapar valores para SQL (usar esc_sql do WordPress)
            $user_cidade_escaped = esc_sql($user_cidade);
            $user_estado_sigla_escaped = esc_sql(strtolower($user_estado_sigla));

            $clauses['fields'] .= ", CASE 
                WHEN pm_subsetor.meta_value = '{$user_subsetor}' THEN 1
                WHEN pm_setor.meta_value = '{$user_setor}' THEN 2
                WHEN pm_sub_regiao.meta_value = '{$user_sub_regiao}' THEN 3
                WHEN pm_regiao_postal.meta_value = '{$user_regiao_postal}' THEN 4
                WHEN cidade_terms.cidade_name = '{$user_cidade_escaped}' AND LOWER(cidade_terms.estado_slug) = '{$user_estado_sigla_escaped}' THEN 4
                ELSE 5
            END AS proximidade_order";

            // Adicionar ordenação por proximidade (antes da ordenação padrão)
            if (empty($clauses['orderby'])) {
                $clauses['orderby'] = "proximidade_order ASC";
            } else {
                $clauses['orderby'] = "proximidade_order ASC, " . $clauses['orderby'];
            }

            return $clauses;
        };

        // Adicionar filtro (será aplicado na próxima WP_Query)
        $proximity_filter_added = true;
        add_filter('posts_clauses', $proximity_filter, 10, 2);

        return $args;
    }
}