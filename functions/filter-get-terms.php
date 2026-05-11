<?php
/**
 * Filtro global para get_terms() que exclui termos com apenas posts na lixeira
 * 
 * Este filtro intercepta todas as chamadas a get_terms() e remove termos que:
 * - Só têm posts na lixeira (post_status = 'trash')
 * - Só têm posts marcados como 'vendido'
 * 
 * Aplica-se apenas quando hide_empty = true e não está no admin
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filtro global para get_terms() - exclui termos com apenas posts na lixeira/vendidos
 * 
 * @param array|WP_Error $terms Array de termos ou WP_Error
 * @param array|string $taxonomies Taxonomia(s) sendo consultada(s)
 * @param array $args Argumentos passados para get_terms()
 * @return array|WP_Error Array filtrado de termos ou WP_Error
 */
if (!function_exists('bazar_filter_terms_exclude_trash')) {
    add_filter('get_terms', 'bazar_filter_terms_exclude_trash', 10, 3);
    
    function bazar_filter_terms_exclude_trash($terms, $taxonomies, $args) {
        // Se não há termos ou é erro, retornar como está
        if (empty($terms) || is_wp_error($terms)) {
            return $terms;
        }
        
        // Aplicar apenas se hide_empty está ativo (ou não especificado, padrão é true)
        $hide_empty = isset($args['hide_empty']) ? $args['hide_empty'] : true;
        if (!$hide_empty) {
            return $terms; // Se hide_empty = false, não filtrar (já está sendo filtrado manualmente)
        }
        
        // Não aplicar no admin (pode causar problemas na interface)
        if (is_admin()) {
            return $terms;
        }
        
        // Verificar se é uma taxonomia de posts
        $taxonomy_array = is_array($taxonomies) ? $taxonomies : array($taxonomies);
        $is_post_taxonomy = false;
        
        foreach ($taxonomy_array as $tax) {
            $tax_obj = get_taxonomy($tax);
            if ($tax_obj && in_array('post', $tax_obj->object_type)) {
                $is_post_taxonomy = true;
                break;
            }
        }
        
        // Aplicar apenas para taxonomias de posts
        if (!$is_post_taxonomy) {
            return $terms;
        }
        
        // Buscar termos que têm posts publicados (excluindo lixeira e vendidos)
        global $wpdb;
        
        // Buscar ID do termo 'vendido' (usando helper com cache)
        $vendido_term_id = bazar_get_vendido_term_id();
        
        // Extrair term IDs dos termos retornados
        $term_ids = array();
        foreach ($terms as $term) {
            if (is_object($term) && isset($term->term_id)) {
                $term_ids[] = intval($term->term_id);
            } elseif (is_numeric($term)) {
                $term_ids[] = intval($term);
            }
        }
        
        if (empty($term_ids)) {
            return $terms;
        }
        
        // Preparar placeholders para term IDs
        $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
        
        // Preparar placeholders para taxonomias
        $tax_placeholders = implode(',', array_fill(0, count($taxonomy_array), '%s'));
        
        // Query SQL para encontrar termos que têm posts publicados
        if ($vendido_term_id > 0) {
            $query = $wpdb->prepare("
                SELECT DISTINCT tt.term_id
                FROM {$wpdb->term_taxonomy} tt
                INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                WHERE tt.taxonomy IN ($tax_placeholders)
                AND tt.term_id IN ($placeholders)
                AND p.post_status = 'publish'
                AND p.post_type = 'post'
                AND p.ID NOT IN (
                    SELECT DISTINCT tr2.object_id
                    FROM {$wpdb->term_relationships} tr2
                    INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                    WHERE tt2.taxonomy = 'status'
                    AND tt2.term_id = %d
                )
            ", array_merge($taxonomy_array, $term_ids, array($vendido_term_id)));
        } else {
            $query = $wpdb->prepare("
                SELECT DISTINCT tt.term_id
                FROM {$wpdb->term_taxonomy} tt
                INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                WHERE tt.taxonomy IN ($tax_placeholders)
                AND tt.term_id IN ($placeholders)
                AND p.post_status = 'publish'
                AND p.post_type = 'post'
            ", array_merge($taxonomy_array, $term_ids));
        }
        
        $valid_term_ids = $wpdb->get_col($query);
        $valid_term_ids = array_map('intval', $valid_term_ids);
        
        // Filtrar termos: manter apenas os que têm posts publicados
        $filtered_terms = array();
        foreach ($terms as $term) {
            $term_id = is_object($term) && isset($term->term_id) 
                ? intval($term->term_id) 
                : (is_numeric($term) ? intval($term) : 0);
            
            if ($term_id > 0 && in_array($term_id, $valid_term_ids)) {
                $filtered_terms[] = $term;
            }
        }
        
        return $filtered_terms;
    }
}

/**
 * Filtro global para get_terms() - ordena automaticamente pelo campo 'ordem' ou 'ordem_componente'
 * 
 * @param array|WP_Error $terms Array de termos ou WP_Error
 * @param array|string $taxonomies Taxonomia(s) sendo consultada(s)
 * @param array $args Argumentos passados para get_terms()
 * @return array|WP_Error Array ordenado de termos ou WP_Error
 */
if (!function_exists('bazar_filter_terms_order_by_meta')) {
    // add_filter('get_terms', 'bazar_filter_terms_order_by_meta', 5, 3);
    
    function bazar_filter_terms_order_by_meta($terms, $taxonomies, $args) {
        // Se não há termos ou é erro, retornar como está
        if (empty($terms) || is_wp_error($terms)) {
            return $terms;
        }
        
        // Se orderby foi especificado explicitamente, não aplicar ordenação automática
        if (isset($args['orderby']) && $args['orderby'] !== 'name' && $args['orderby'] !== 'term_order') {
            return $terms;
        }
        
        // Verificar taxonomia
        $taxonomy_array = is_array($taxonomies) ? $taxonomies : array($taxonomies);
        if (empty($taxonomy_array)) {
            return $terms;
        }
        
        // Determinar qual campo de ordenação usar
        $meta_key = 'ordem'; // Padrão
        if (in_array('componente', $taxonomy_array)) {
            $meta_key = 'ordem_componente';
        }
        
        // Adicionar valores de ordenação aos termos
        $terms_with_order = array();
        foreach ($terms as $term) {
            if (!is_object($term) || !isset($term->term_id)) {
                continue;
            }
            
            $term_id = intval($term->term_id);
            $ordem = 999; // Valor padrão (último)
            
            // Tentar buscar via ACF primeiro (se disponível)
            if (function_exists('get_field')) {
                $ordem_acf = get_field($meta_key, $term);
                if (!empty($ordem_acf) && is_numeric($ordem_acf)) {
                    $ordem = intval($ordem_acf);
                }
            }
            
            // Se não encontrou via ACF, tentar via term_meta (fallback)
            if ($ordem === 999) {
                $ordem_meta = get_term_meta($term_id, $meta_key, true);
                if (!empty($ordem_meta) && is_numeric($ordem_meta)) {
                    $ordem = intval($ordem_meta);
                }
            }
            
            // Adicionar propriedade temporária para ordenação
            $term->bazar_ordem = $ordem;
            $terms_with_order[] = $term;
        }
        
        // Ordenar por ordem (menor primeiro), depois por nome
        usort($terms_with_order, function($a, $b) {
            $ordem_a = isset($a->bazar_ordem) ? intval($a->bazar_ordem) : 999;
            $ordem_b = isset($b->bazar_ordem) ? intval($b->bazar_ordem) : 999;
            
            if ($ordem_a !== $ordem_b) {
                return $ordem_a - $ordem_b;
            }
            
            // Se ordem é igual, ordenar por nome
            $name_a = isset($a->name) ? strtolower($a->name) : '';
            $name_b = isset($b->name) ? strtolower($b->name) : '';
            return strcmp($name_a, $name_b);
        });
        
        // Remover propriedade temporária
        foreach ($terms_with_order as $term) {
            if (isset($term->bazar_ordem)) {
                unset($term->bazar_ordem);
            }
        }
        
        return $terms_with_order;
    }
}
?>