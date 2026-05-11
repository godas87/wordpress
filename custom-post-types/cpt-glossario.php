<?php
add_action( 'init', 'cpt_glossario' );
function cpt_glossario() {	
	$labels = array(
		'name' => 'Glossário Ciclismos',
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
		'show_in_rest' => true,
        'menu_position' => 20,
		'menu_icon' => 'dashicons-rss',
        'show_in_nav_menus' => true,
        'exclude_from_search' => false,
        'has_archive' => true,
        'query_var' => true,
        'can_export' => true,
        'rewrite' => true,
        'capability_type' => 'post',
		'supports' => array( 'title', 'editor', 'excerpt', 'revisions', 'thumbnail', 'author', 'custom-fields', 'comments'),
		'taxonomies' => array( 'alfabeto', 'post_tag' ),		
    );
    register_post_type( 'glossario-ciclismo', $args );
}


add_action( 'init', 'tax_glossario', 0 );
function tax_glossario() {	
	$labels = array(
		'name' => _x( 'Alfabeto', 'taxonomy general name' ),
		'singular_name' => _x( 'Alfabeto', 'taxonomy singular name' ),
		'search_items' =>  __( 'Buscar' ),
		'all_items' => __( 'Todo Alfabeto' ),
		'parent_item' => __( 'Pai' ),
		'parent_item_colon' => __( 'Pai:' ),
		'edit_item' => __( 'Editar' ), 
		'update_item' => __( 'Editar' ),
		'add_new_item' => __( 'Adicionar' ),
		'new_item_name' => __( 'Novo' ),
		'menu_name' => __( 'Alfabeto' ),
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
		'rewrite'           		 => array( 'slug' => 'glossario-ciclismo/alfabeto', 'hierarchical' => true ),
	);
	register_taxonomy( 'alfabeto', array( 'glossario-ciclismo', 'glossario' ), $args );
}


// Add this code to your theme's functions.php file or in a custom plugin
// Function to add the dropdown filter
add_action('restrict_manage_posts', 'add_taxonomy_filter_to_glossario_admin');
function add_taxonomy_filter_to_glossario_admin() {
    global $typenow;
    // Check if we are on the 'glossario' custom post type admin page
    if ($typenow === 'glossario-ciclismo') {
        $taxonomy = 'alfabeto'; // Replace 'alfabeto' with your actual taxonomy name
        $current_tax = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
        $tax_obj = get_taxonomy($taxonomy);  
        // Display the dropdown filter
        echo '<select name="' . $taxonomy . '" id="' . $taxonomy . '" class="postform">';
        echo '<option value="">' . $tax_obj->labels->all_items . '</option>';
        $terms = get_terms($taxonomy);
        foreach ($terms as $term) {
            echo '<option value="' . $term->slug . '"' . selected($current_tax, $term->slug, false) . '>' . $term->name . '</option>';
        }
        echo '</select>';
    }
}
?>