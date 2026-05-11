<?php
add_action('init', 'tax_modalidade', 0);
function tax_modalidade()
{
  $labels = array(
    'name' => _x('Modalidades', 'taxonomy general name'),
    'singular_name' => _x('Modalidade', 'taxonomy singular name'),
    'search_items' => __('Buscar'),
    'all_items' => __('Todos'),
    'parent_item' => __('Pai'),
    'parent_item_colon' => __('Pai:'),
    'edit_item' => __('Editar'),
    'update_item' => __('Editar'),
    'add_new_item' => __('Adicionar'),
    'new_item_name' => __('Novo'),
    'menu_name' => __('Modalidade'),
  );
  $args = array(
    'labels' => $labels,
    'hierarchical' => true,
    'public' => true,
    'show_ui' => true,
    'show_admin_column' => true,
    'show_in_nav_menus' => true,
    'show_tagcloud' => false,
    'publicly_queryable' => true,
    'query_var' => true,
    'rewrite' => array('slug' => 'bicicleta-modalidade', 'hierarchical' => true),
  );
  register_taxonomy('modalidade', array('post'), $args);
}

// URL /bicicleta-modalidade/ (sem termo): exibir archive em vez de 404
add_action('init', 'bazar_modalidade_archive_rewrite', 1);
function bazar_modalidade_archive_rewrite() {
  add_rewrite_rule('^bicicleta-modalidade/?$', 'index.php?bazar_modalidade_archive=1', 'top');
}

add_filter('query_vars', 'bazar_modalidade_archive_query_var');
function bazar_modalidade_archive_query_var($vars) {
  $vars[] = 'bazar_modalidade_archive';
  return $vars;
}
?>