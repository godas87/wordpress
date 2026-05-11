<?php
add_action( 'init', 'cpt_api' );
function cpt_api() {	
	$labels = array(
		'name' => 'API',
		'all_items' => 'Todos',
		'add_new_item' => 'Adicionar item',
		'edit_item' => 'Editar item',
		'new_item' => 'Novo item',
		'view_item' => 'Visualizar item',
		'search_items' => 'Buscar item',
		'not_found' => 'Nada encontrado',
		'not_found_in_trash' => 'Nada encontrado na lixeira'
    );
    $args = array( 
        'labels' => $labels,
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 1,
		'menu_icon' => 'dashicons-admin-site-alt3', 
        'show_in_nav_menus' => true,
        'exclude_from_search' => true,
        'has_archive' => true,
        'query_var' => true,
        'can_export' => true,
        'rewrite' => true,
        'capability_type' => 'post',
        'supports' => array( 'title', 'editor', 'revisions', 'author', 'page-attributes', 'thumbnail', 'custom-fields'),
    );
    register_post_type( 'app', $args );
}
?>