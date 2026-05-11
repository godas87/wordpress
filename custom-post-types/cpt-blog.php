<?php
add_action( 'init', 'cpt_blog' );
function cpt_blog() {	
	$labels = array(
		'name' => 'Blog',
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
        'menu_position' => 20,
		'menu_icon' => 'dashicons-rss',
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'exclude_from_search' => false,
        'has_archive' => true,
        'query_var' => true,
        'can_export' => true,
        'rewrite' => true,
        'capability_type' => 'post',
		'supports' => array( 'title', 'editor', 'excerpt', 'revisions', 'thumbnail', 'author', 'custom-fields', 'comments'),        
        'taxonomies' => array('category', 'modalidade', 'componente')
    );
    register_post_type( 'blog', $args );
}
?>