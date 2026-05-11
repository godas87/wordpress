<?php
add_action( 'init', 'tax_conservacao', 0 );
function tax_conservacao() {
	$labels = array(
		'name' => _x( 'Conservação', 'taxonomy general name' ),
		'singular_name' => _x( 'Conservação', 'taxonomy singular name' ),
		'search_items' =>  __( 'Buscar' ),
		'all_items' => __( 'Todos' ),
		'parent_item' => __( 'Pai' ),
		'parent_item_colon' => __( 'Pai:' ),
		'edit_item' => __( 'Editar' ), 
		'update_item' => __( 'Editar' ),
		'add_new_item' => __( 'Adicionar' ),
		'new_item_name' => __( 'Novo' ),
		'menu_name' => __( 'Conservação' ),
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => true,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => false,
		'publicly_queryable'		 => true,
		'query_var'					 => true,
	);
	register_taxonomy( 'conservacao', array( 'post' ), $args );
}
?>