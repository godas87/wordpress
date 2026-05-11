<?php
/**
 * Helper functions para gerenciar categorias em filtros
 * 
 * Este arquivo contém funções helper para:
 * - Buscar categorias baseadas em queries
 * - Preparar e ordenar categorias para exibição
 * - Determinar categorias ativas
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Busca categorias baseadas no contexto e query
 * 
 * @param WP_Query|null $base_query Query base (sem filtros GET)
 * @param WP_Query|null $categories_query Query específica para categorias (sem filtro GET de category)
 * @param WP_Query|null $index_query Query final (com todos os filtros)
 * @param bool $is_index_page Se é página index
 * @return array Array de objetos WP_Term ou null
 */
if (!function_exists('bazar_get_categories_by_filter')) {
    function bazar_get_categories_by_filter(
      $base_query = null, 
      $categories_query = null, 
      $index_query = null, 
      $is_index_page = false
    ) {
        
        // Em index ou página de category: buscar todas as categorias principais
        if ($is_index_page || is_category()) {
            $categs = get_terms(array(
                'taxonomy' => 'category',
                'parent' => 0,
                'hide_empty' => true,
                'hierarchical' => 1,
                'orderby' => 'count',
                'order' => 'DESC'
            ));
            
            if (is_wp_error($categs) || empty($categs)) {
                return null;
            }
            
            return $categs;
        }
        
        // Em outras páginas: usar query específica ou base_query
        $query_for_categories = null;
        if ($categories_query instanceof WP_Query) {
            $query_for_categories = $categories_query;
        } elseif ($base_query instanceof WP_Query) {
            $query_for_categories = $base_query;
        } elseif ($index_query instanceof WP_Query) {
            $query_for_categories = $index_query;
        }
        
        if (!$query_for_categories) {
            return null;
        }
        
        $categs = bazar_get_terms_from_query(
            'category',
            $query_for_categories,
            array('parent' => 0, 'hierarchical' => 1),
            null,
            false
        );
        
        if (is_wp_error($categs) || empty($categs)) {
            return null;
        }
        
        return $categs;
    }
}

/**
 * Prepara categorias para exibição: separa selecionadas, ordena e retorna dados estruturados
 * 
 * @param array|null $categs Array de objetos WP_Term
 * @param array $post_data Dados de $_GET processados
 * @param WP_Term|null $current_term Termo atual da URL
 * @return array Array com: 'categories', 'has_categories', 'has_single_category', 'queried_category'
 */
if (!function_exists('bazar_prepare_categories_for_display')) {
    function bazar_prepare_categories_for_display(
      $categs, 
      $post_data = array(), 
      $current_term = null
    ) {
        
        $result = array(
            'categories' => array(),
            'has_categories' => false,
            'has_single_category' => false,
            'queried_category' => null
        );
        
        if (!$categs || is_wp_error($categs) || empty($categs)) {
            return $result;
        }
        
        // Separar selecionadas e não selecionadas
        // Nota: Se $categs já vier ordenado por count (via get_terms), manteremos a ordem ao separar
        $selected_categs = array();
        $unselected_categs = array();
        
        foreach ($categs as $categ) {
            $is_selected = false;
            
            // Verificar se é a categoria da URL
            if (is_category()) {
                $queried_obj = get_queried_object();
                if ($queried_obj && $queried_obj->term_id == $categ->term_id) {
                    $is_selected = true;
                }
            }
            
            // Verificar se está selecionada via GET
            if (!$is_selected && isset($post_data['category'])) {
                $values = bazar_normalize_filter_value($post_data['category']);
                $is_selected = in_array($categ->slug, $values);
            }
            
            if ($is_selected) {
                $selected_categs[] = $categ;
            } else {
                $unselected_categs[] = $categ;
            }
        }
        
        // Se os termos já vieram ordenados por count, não precisamos reordenar
        // Apenas juntar: selecionadas primeiro (mantendo ordem original dentro de cada grupo)
        $result['categories'] = !empty($selected_categs) 
            ? array_merge($selected_categs, $unselected_categs) 
            : $unselected_categs;
        
        $result['has_categories'] = true;
        $result['has_single_category'] = (count($result['categories']) === 1);
        
        if (is_category()) {
            $result['queried_category'] = get_queried_object();
        }
        
        return $result;
    }
}