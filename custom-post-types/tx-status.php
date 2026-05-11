<?php
/**
 * Taxonomia para marcar produtos como vendidos e destaque
 * 
 * @package XXXXXX
 */
add_action( 'init', 'tax_status', 0 );
function tax_status() {
	$labels = array(
		'name' => __( 'Status do Produto', 'taxonomy general name' ),
		'singular_name' => __( 'Status do Produto', 'taxonomy singular name' ),
		'search_items' =>  __( 'Buscar' ),
		'all_items' => __( 'Todos' ),
		'parent_item' => __( 'Pai' ),
		'parent_item_colon' => __( 'Pai:' ),
		'edit_item' => __( 'Editar' ), 
		'update_item' => __( 'Atualizar' ),
		'add_new_item' => __( 'Adicionar' ),
		'new_item_name' => __( 'Novo' ),
		'menu_name' => __( 'Status do Produto' ),
	);
	$args = array(
		'labels'              => $labels,
		'hierarchical'        => true,
		'public'              => false, // Oculto do frontend
		'show_ui'             => true,
		'show_admin_column'   => true,
		'show_in_nav_menus'   => false,
		'show_tagcloud'       => false,
		'publicly_queryable'  => false, // Não aparece em URLs
		'query_var'					  => true,
		'rewrite'             => false, // Sem rewrite de URL
	);
	register_taxonomy( 'status', array( 'post' ), $args );
	
	// // Criar termo 'sim' se não existir
	if (!term_exists('vendido', 'status')) {
		wp_insert_term('Vendido', 'status', array(
			'description' => 'Produto vendido/desativado',
			'slug' => 'vendido'
		));
		wp_insert_term('Destaque', 'status', array(
			'description' => 'Produto em destaque',
			'slug' => 'destaque'
		));
	}
}
?>