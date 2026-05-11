<?php
/**
 * Configurações do Sitemap do WordPress
 * Garante que não há output antes do XML
 */

/**
 * Verifica se estamos gerando um sitemap XML
 * @return bool
 */
function bazar_is_sitemap_request() {
    static $is_sitemap = null;
    
    if ($is_sitemap !== null) {
        return $is_sitemap;
    }
    
    // Verificar REQUEST_URI
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    
    // Verificar se é uma requisição de sitemap
    $is_sitemap = (
        strpos($request_uri, 'wp-sitemap') !== false ||
        strpos($request_uri, '/sitemap') !== false ||
        isset($_GET['sitemap']) ||
        (preg_match('/\/wp-sitemap.*\.xml/', $request_uri))
    );
    
    return $is_sitemap;
}

/**
 * Previne output durante geração de sitemap XML
 * Limpa qualquer output buffer antes do sitemap ser gerado
 * Executa muito cedo no carregamento do WordPress
 */
add_action('init', 'bazar_prevent_output_on_sitemap', 1);
function bazar_prevent_output_on_sitemap() {
    if (bazar_is_sitemap_request()) {
        // Limpar qualquer output buffer existente
        while (ob_get_level()) {
            ob_end_clean();
        }
        // Prevenir qualquer output adicional de hooks comuns
        remove_all_actions('wp_head');
        remove_all_actions('wp_footer');
        remove_all_actions('wp_print_styles');
        remove_all_actions('wp_print_scripts');
    }
}

add_filter( 'wp_sitemaps_add_provider', 'custom_site_map', 10, 2);
function custom_site_map ( $provider, $name ) {
	return ( $name == 'users' ) 
		? false 
		: $provider;
}

add_filter( 'wp_sitemaps_post_types', 'remove_post_type_from_wp_sitemap' );
function remove_post_type_from_wp_sitemap( $post_types ) {
	unset( $post_types['teste'] );
	unset( $post_types['app'] );
	return $post_types;
}

add_filter( 'wp_sitemaps_taxonomies', 'remove_tax_on_sitemap');
function remove_tax_on_sitemap( $taxonomies ){
    unset( $taxonomies['post_tag'] );
    unset( $taxonomies['negociacao'] );
		unset( $taxonomies['material'] );
		unset( $taxonomies['cor'] );
		unset( $taxonomies['alfabeto'] );
		unset( $taxonomies['medidas'] );
		unset( $taxonomies['especificacoes'] );
		unset( $taxonomies['genero'] );
		unset( $taxonomies['conservacao'] );
		unset( $taxonomies['idade'] );
		// unset( $taxonomies['componente'] );
    return $taxonomies;
};

add_filter( 'wp_sitemaps_posts_query_args', 'remove_posts_from_sitemap', 10, 2 );
function remove_posts_from_sitemap( $args, $post_type ) {
    // Excluir páginas específicas
    if ( 'page' === $post_type ) {
			$args['post__not_in'] = array( 
				203, // Anuncair
				708, // Editar Anuncio
				199, // Meus Anuncios
				196, // Minha Conta
				192, // Confirmar Email
				708, // Editar anúncio
				606, // Reenviar Senha
				1289, // ?
				1792 // ?
			);
		}
    
    // Excluir posts vendidos do tipo 'post'
    if ( 'post' === $post_type ) {
        // Verificar se termo 'vendido' existe (usando helper com cache)
        $vendido_term_id = bazar_get_vendido_term_id();
        if ( $vendido_term_id > 0 ) {
            // Adicionar tax_query para excluir posts vendidos
            if ( !isset($args['tax_query']) ) {
                $args['tax_query'] = array();
            }
            
            $args['tax_query'][] = array(
                'taxonomy' => 'status',
                'field'    => 'slug',
                'terms'    => 'vendido',
                'operator' => 'NOT IN',
            );
            
            // Se já existir tax_query, definir relação
            if ( count($args['tax_query']) > 1 ) {
                $args['tax_query']['relation'] = 'AND';
            }
        }
    }

	return $args;
}

//Remove taxonomy parents on 'especificacoes' from the WP sitemap
// Para 'category': exibir apenas termos pais (excluir filhos)
// Para 'modalidade': exibir todos os termos (pais e filhos)
// Para outras taxonomias (exceto 'marca-modelo'): exibir apenas filhos (excluir pais)
// IMPORTANTE: Excluir termos que só têm posts na lixeira
add_filter( 'wp_sitemaps_taxonomies_query_args', 'remove_tax_from_sitemap', 10, 2);
function remove_tax_from_sitemap( $args ) {
	
	if ( !isset($args['taxonomy']) ) {
		return $args;
	}
	
	$taxonomy = $args['taxonomy'];
	
	// Primeiro: excluir termos que só têm posts na lixeira (não têm posts publicados)
	global $wpdb;
	$all_terms = get_terms( array(
		'taxonomy' => $taxonomy,
		'hide_empty' => false, // Buscar todos para filtrar manualmente
	) );
	
	if( !empty($all_terms) && !is_wp_error($all_terms) ) {
		$term_ids = array_map(function($term) {
			return $term->term_id;
		}, $all_terms);
		
		if( !empty($term_ids) ) {
			// Buscar ID do termo 'vendido' (usando helper com cache)
			$vendido_term_id = bazar_get_vendido_term_id();
			
			// Query para encontrar termos que têm posts publicados (excluindo vendidos)
			$placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
			
			if( $vendido_term_id > 0 ) {
				// Se existe termo 'vendido', excluir posts que têm esse termo
				$query = $wpdb->prepare("
					SELECT DISTINCT tt.term_id
					FROM {$wpdb->term_taxonomy} tt
					INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
					INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
					WHERE tt.taxonomy = %s
					AND tt.term_id IN ($placeholders)
					AND p.post_status = 'publish'
					AND p.post_type = 'post'
					AND p.ID NOT IN (
						SELECT DISTINCT tr2.object_id
						FROM {$wpdb->term_relationships} tr2
						INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
						WHERE tt2.taxonomy = 'status'
						AND tt2.term_id = %d
					)
				", array_merge(array($taxonomy), $term_ids, array($vendido_term_id)));
			} else {
				// Se não existe termo 'vendido', usar query simples
				$query = $wpdb->prepare("
					SELECT DISTINCT tt.term_id
					FROM {$wpdb->term_taxonomy} tt
					INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
					INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
					WHERE tt.taxonomy = %s
					AND tt.term_id IN ($placeholders)
					AND p.post_status = 'publish'
					AND p.post_type = 'post'
				", array_merge(array($taxonomy), $term_ids));
			}
			
			$terms_with_published = $wpdb->get_col($query);
			$terms_with_published = array_map('intval', $terms_with_published);
			
			// Excluir termos que não têm posts publicados
			foreach( $all_terms as $term ) {
				if( !in_array($term->term_id, $terms_with_published) ) {
					if (!isset($args['exclude'])) {
						$args['exclude'] = array();
					}
					if( !in_array($term->term_id, $args['exclude']) ) {
						$args['exclude'][] = $term->term_id;
					}
				}
			}
		}
	}
	
	// Segundo: aplicar lógica de hierarquia (pais/filhos)
	// Para 'category': exibir apenas termos pais (excluir filhos)
	if ( $taxonomy === 'category' ) {
		$terms = get_terms( array(
			'taxonomy' => $taxonomy,
			'hide_empty' => false, // Já filtramos acima
		) );
		
		if (!is_wp_error($terms) && !empty($terms)) {
			foreach( $terms as $term ){
				// Excluir termos filhos (parent != 0)
				if( $term->parent != 0 ){
					if (!isset($args['exclude'])) {
						$args['exclude'] = array();
					}
					if( !in_array($term->term_id, $args['exclude']) ) {
						$args['exclude'][] = $term->term_id;
					}
				}			
			}
		}
	}
	// Para 'modalidade' e 'marca-modelo': exibir todos os termos (pais e filhos) - não excluir por hierarquia
	// (mas já excluímos os sem posts publicados acima)
	elseif ( $taxonomy === 'modalidade' || $taxonomy === 'marca-modelo' || $taxonomy === 'cidade' ) {
		// Não fazer nada adicional, já filtramos termos sem posts publicados
	}
	// Para outras taxonomias: exibir apenas filhos (excluir pais)
	else {
		$terms = get_terms( array(
			'taxonomy' => $taxonomy,
			'hide_empty' => false, // Já filtramos acima
		) );
		
		if (!is_wp_error($terms) && !empty($terms)) {
			foreach( $terms as $term ){
				// Excluir termos pais (parent == 0)
				if( $term->parent == 0 ){
					if (!isset($args['exclude'])) {
						$args['exclude'] = array();
					}
					if( !in_array($term->term_id, $args['exclude']) ) {
						$args['exclude'][] = $term->term_id;
					}
				}			
			}
		}
	}
	
	return $args;
}