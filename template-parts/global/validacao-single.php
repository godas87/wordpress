<?php
if ( session_status() === PHP_SESSION_NONE ) session_start();
if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) :
    if( $_SESSION['send_mail'] ) unset($_POST);
    header( "Location: ".$_SERVER['PHP_SELF'] );
    exit;
endif;

// Verifica se é um preview
$is_preview = isset($_GET['preview']) && $_GET['preview'] == 'true';

$post_id = get_the_ID();

$page_redirect = ( 
    get_post_status( get_the_ID() ) == 'publish' 
    || get_post_status( get_the_ID() ) == 'draft'
) 
    ? false 
    : true;

// Autor logado pode ver a single quando o anúncio está pending mas já aprovado pelo ADM (aguardando perfil/e-mail para publicar).
if ( $page_redirect && is_user_logged_in() && $post_id ) {
    $st = get_post_status( $post_id );
    if ( $st === 'pending' ) {
        $meta_aprovado = defined( 'BAZAR_META_APROVADO_ADM' ) ? BAZAR_META_APROVADO_ADM : 'bazar_anuncio_aprovado_adm';
        $author_id = (int) get_post_field( 'post_author', $post_id );
        if ( get_post_meta( $post_id, $meta_aprovado, true ) === '1' && $author_id === (int) get_current_user_id() ) {
            $page_redirect = false;
        }
    }
}

if( 
    $page_redirect 
    && !$is_preview
){
    wp_redirect( esc_url( get_bloginfo('url').'/bicicletas/' ) );
    exit;
}

// Força o carregamento dos campos ACF durante o preview
if( $is_preview ){
    // Força o carregamento dos campos ACF para o post
    add_filter('acf/pre_load_post_id', function($post_id) {
        return get_the_ID();
    });
    
    // Força o carregamento dos campos ACF para os termos
    add_filter('acf/pre_load_term_id', function($term_id) {
        return $term_id;
    });
    
    // Força o carregamento dos campos ACF para posts não publicados
    add_filter('acf/pre_load_post', function($post) {
        if (get_post_status($post->ID) !== 'publish') {
            // Força o carregamento dos campos ACF para posts não publicados
            add_filter('acf/pre_load_post_id', function($post_id) use ($post) {
                return $post->ID;
            });
        }
        return $post;
    });
}
?>