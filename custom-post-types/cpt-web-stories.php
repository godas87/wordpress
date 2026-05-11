<?php
add_action( 'init', 'cpt_web_stories' );
function cpt_web_stories() {	
	$labels = array(
		'name' => 'WebStories',
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
        'menu_position' => 10,
		'menu_icon' => 'dashicons-rss', 
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'exclude_from_search' => false,
        'has_archive' => true,
        'query_var' => true,
        'can_export' => true,
        'rewrite' => true,
        'capability_type' => 'post',
		'supports' => array( 'title', 'editor', 'revisions', 'thumbnail', 'author', 'custom-fields'),
        'taxonomies' => array('post_tag'),
    );
    register_post_type( 'web-stories', $args );
}
// 1. Função para adicionar a coluna "Visualizações" na listagem de web-stories
add_filter( 'manage_edit-web-stories_columns', 'adicionar_coluna_visualizacoes_web_stories' );
function adicionar_coluna_visualizacoes_web_stories( $columns ) {
    $columns['post_views_count'] = 'Views'; // Adiciona a coluna com o título "Visualizações"
    return $columns;
}
// 2. Função para exibir o número de visualizações na coluna
add_action( 'manage_web-stories_posts_custom_column', 'exibir_numero_visualizacoes_web_stories', 10, 2 );
function exibir_numero_visualizacoes_web_stories( $column, $post_id ) {
    if ( $column == 'post_views_count' ) {
        // Obtém o valor do meta field
        $post_views_count = get_post_meta( $post_id, 'post_views_count', true );         
        // Exibe o número de visualizações ou um traço caso não haja visualizações
        echo ( $post_views_count ) ? $post_views_count : '-';
    }
}
// 3. Estilos CSS opcionais para a coluna (melhorar a aparência)
add_action( 'admin_head-edit.php', 'estilos_coluna_visualizacoes_web_stories' );
function estilos_coluna_visualizacoes_web_stories() {
    echo '<style>
        .column-post_views_count {
            width: 70px; /* Largura fixa para a coluna */
            text-align: center; /* Alinha o texto ao centro */
        }
    </style>';
}
// 4. Função para tornar a coluna "Visualizações" ordenável
add_filter( 'manage_edit-web-stories_sortable_columns', 'tornar_coluna_visualizacoes_ordenavel' );
function tornar_coluna_visualizacoes_ordenavel( $sortable_columns ) {
    // Define o meta field como chave para ordenação
    $sortable_columns['post_views_count'] = 'post_views_count';
    return $sortable_columns;
}
// 5. Função para realizar a ordenação
// src app/wp/wp-query.php
?>