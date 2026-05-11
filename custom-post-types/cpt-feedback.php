<?php
/**
 * Custom Post Type: Feedback
 * Para armazenar feedbacks de vendas dos usuários
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'cpt_feedback');
function cpt_feedback() {
    $labels = array(
        'name' => 'Feedbacks',
        'singular_name' => 'Feedback',
        'all_items' => 'Todos os Feedbacks',
        'add_new_item' => 'Adicionar Feedback',
        'edit_item' => 'Editar Feedback',
        'new_item' => 'Novo Feedback',
        'view_item' => 'Visualizar Feedback',
        'search_items' => 'Buscar Feedbacks',
        'not_found' => 'Nenhum feedback encontrado',
        'not_found_in_trash' => 'Nenhum feedback encontrado na lixeira'
    );
    
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'public' => false, // Não exibir publicamente
        'show_ui' => true, // Mostrar no admin
        'show_in_menu' => true,
        'menu_position' => 26,
        'menu_icon' => 'dashicons-star-filled',
        'show_in_nav_menus' => false,
        'show_in_rest' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => false,
        'can_export' => true,
        'rewrite' => false, // Não precisa de URL pública
        'capability_type' => 'post',
        'supports' => array('title'), // Apenas título
        'publicly_queryable' => false,
    );
    
    register_post_type('feedback', $args);
}

/**
 * Adicionar colunas customizadas na listagem do admin
 */
add_filter('manage_feedback_posts_columns', 'feedback_custom_columns');
function feedback_custom_columns($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = 'Anúncio';
    $new_columns['autor'] = 'Autor';
    $new_columns['nota'] = 'Nota';
    $new_columns['data'] = 'Data';
    return $new_columns;
}

/**
 * Preencher colunas customizadas
 */
add_action('manage_feedback_posts_custom_column', 'feedback_custom_column_content', 10, 2);
function feedback_custom_column_content($column, $post_id) {
    switch ($column) {
        case 'autor':
            $author_id = get_post_meta($post_id, '_feedback_author_id', true);
            if ($author_id) {
                $user = get_user_by('ID', $author_id);
                if ($user) {
                    echo esc_html($user->display_name);
                } else {
                    echo '—';
                }
            } else {
                echo '—';
            }
            break;
            
        case 'nota':
            $nota = get_post_meta($post_id, '_feedback_nota', true);
            if ($nota) {
                echo esc_html($nota) . '/5';
            } else {
                echo '—';
            }
            break;
            
        case 'data':
            $date = get_post_meta($post_id, '_feedback_date', true);
            if ($date) {
                echo esc_html(date_i18n('d/m/Y H:i', strtotime($date)));
            } else {
                echo get_the_date('d/m/Y H:i', $post_id);
            }
            break;
    }
}

/**
 * Tornar colunas ordenáveis
 */
add_filter('manage_edit-feedback_sortable_columns', 'feedback_sortable_columns');
function feedback_sortable_columns($columns) {
    $columns['nota'] = 'nota';
    $columns['data'] = 'data';
    return $columns;
}

/**
 * Ordenação customizada
 */
add_action('pre_get_posts', 'feedback_custom_orderby');
function feedback_custom_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if ($query->get('post_type') !== 'feedback') {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    if ($orderby === 'nota') {
        $query->set('meta_key', '_feedback_nota');
        $query->set('orderby', 'meta_value_num');
    } elseif ($orderby === 'data') {
        $query->set('meta_key', '_feedback_date');
        $query->set('orderby', 'meta_value');
    }
}
?>

