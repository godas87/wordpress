<?php
/**
 * Custom Post Type: FAQ
 * 
 * CPT para gerenciar FAQs que podem ser associados a taxonomias
 * 
 * @package XXXXXX
 */
add_action('init', 'cpt_faq');
function cpt_faq()
{
  $labels = array(
    'name' => 'FAQ',
    'singular_name' => 'FAQ',
    'all_items' => 'Todos os FAQs',
    'add_new_item' => 'Adicionar FAQ',
    'edit_item' => 'Editar FAQ',
    'new_item' => 'Novo FAQ',
    'view_item' => 'Visualizar FAQ',
    'search_items' => 'Buscar FAQ',
    'not_found' => 'Nenhum FAQ encontrado',
    'not_found_in_trash' => 'Nenhum FAQ encontrado na lixeira'
  );
  $args = array(
    'labels' => $labels,
    'hierarchical' => false,
    'public' => false, // Não precisa de URL pública
    'show_ui' => true,
    'show_in_menu' => true,
    'menu_position' => 30,
    'menu_icon' => 'dashicons-editor-help',
    'show_in_nav_menus' => false,
    'show_in_rest' => true, // Para usar Gutenberg se necessário
    'exclude_from_search' => true,
    'has_archive' => false,
    'query_var' => true,
    'can_export' => true,
    'rewrite' => false,
    'capability_type' => 'post',
    'supports' => array('title', 'editor', 'custom-fields'),
    // Associar a todas as taxonomias que podem ter FAQs
    'taxonomies' => array('category', 'modalidade', 'componente', 'acessorio', 'marca-modelo', 'cidade')
  );
  register_post_type('faq', $args);
}
?>