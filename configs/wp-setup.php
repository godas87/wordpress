<?php
add_action( 'after_setup_theme', 'custom_theme_features' );
function custom_theme_features(){	
	// Ativa Imagens Destacadas
	add_theme_support( 'post-thumbnails' );
	add_image_size( 'm', '375', '246', true );
	add_image_size( 'l', '540', '390', true );
	add_image_size( 'sidebar', '320', '190', false );
	add_image_size( 'blog', '1080', '540', true );
	//add_image_size( 'medium', 375, 246, true );
	//add_image_size( 'large', 500, 360, true );

	/**
	 * Geração de miniaturas: por defeito o WP cria medium, medium_large, large, 1536x1536, 2048x2048, etc.
	 * Isto restringe aos tamanhos que o tema regista + thumbnail (lista/admin).
	 * Ajuste via filtro `bazar_image_sizes_whitelist` ou remova `thumbnail` se não precisar.
	 */
	add_filter( 'intermediate_image_sizes', 'bazar_intermediate_image_sizes_whitelist', 99 );

	remove_action('wp_head', 'rel_canonical');
	remove_action('embed_head', 'rel_canonical');
	add_filter('wpseo_canonical', '__return_false');

	// Adicionar Resumo ás páginas
	add_post_type_support('page','excerpt');	
	// Ativa Menu
	register_nav_menu( 'primary', __( 'Menu', 'menu' ) );	
	//ROBOTS META TAGS
	remove_action( 'wp_head', 'indexSearchPage', -1 );

	remove_action('wp_head', 'wp_resource_hints', 2);

	remove_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );
	// Remove Emojis
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	add_filter( 'emoji_svg_url', '__return_false' );
	add_filter( 'wp_headers', 'disable_pingback');
	add_filter( 'styles_inline_size_limit', '__return_zero' );	//Remove WordPress.org Dns-prefetch.
	//SEGURANÇA 
	//REMOVE LINKS DE: RSD / WLWMANIFEST / XMLRPC.PHP
	remove_action ('wp_head', 'rsd_link');
	remove_action( 'wp_head', 'wlwmanifest_link');
	remove_action( 'wp_head', 'wp_shortlink_wp_head');
	remove_action( 'wp_head', 'wp_generator');
	remove_action( 'wp_head', 'feed_links', 2 ); //removes feed links.
	remove_action( 'wp_head', 'feed_links_extra', 3 );  //removes comments feed
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'wp_head', 'rest_output_link_wp_head');
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links');
	remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head');
	
	// Remover função obsoleta the_block_template_skip_link (deprecated desde WP 6.4.0)
	remove_action( 'wp_body_open', 'the_block_template_skip_link', 1 );

	// Remove filtro obsoleto de imagens responsivas (deprecated desde WP 5.5) em the_content e acf_the_content
	add_action( 'init', 'bazar_remove_deprecated_content_image_filters', 11 );
	
	//Adiciona TAG <p> ao the_content()
	if ( ! has_filter("the_content", "wpautop"))
		add_filter ("the_content", "wpautop");	
	// Habilita suporte para HTML5
	add_theme_support( 'html5', array( 'comment-list', 'search-form', 'comment-form', ) );
}

/**
 * Remove wp_make_content_images_responsive (deprecated desde WP 5.5) de the_content e acf_the_content.
 * O ACF formata campos WYSIWYG com o filtro acf_the_content, que também recebe esse callback.
 * Substitui por wp_filter_content_tags no acf_the_content para manter imagens responsivas.
 */
function bazar_remove_deprecated_content_image_filters() {
	remove_filter( 'the_content', 'wp_make_content_images_responsive' );
	remove_filter( 'acf_the_content', 'wp_make_content_images_responsive' );
	if ( function_exists( 'wp_filter_content_tags' ) ) {
		add_filter( 'acf_the_content', 'wp_filter_content_tags' );
	}
}

/**
 * Só gera ficheiros intermediários para estes nomes (intersect com o que o WP pretende criar).
 * Evita medium / medium_large / large / 1536x1536 / 2048x2048 quando não estão na lista.
 *
 * @param string[] $sizes Nomes de tamanhos registados para esta subida.
 * @return string[]
 */
function bazar_intermediate_image_sizes_whitelist( $sizes ) {
	$sizes     = is_array( $sizes ) ? $sizes : array();
	$whitelist = apply_filters(
		'bazar_image_sizes_whitelist',
		array(
			'thumbnail',
			'm',
			'l',
			'sidebar',
			'blog',
		)
	);
	$whitelist = is_array( $whitelist ) ? $whitelist : array();

	return array_values( array_intersect( $whitelist, $sizes ) );
}