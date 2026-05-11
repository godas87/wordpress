<?php 
/**
 * Retorna a URL de redirecionamento após atualização de anúncios
 * 
 * @param int $post_id ID do post
 * @return string URL de redirecionamento
 */
if (!defined('ABSPATH')) {
  exit;
}
function bazar_get_updated_url( $post_id ) {
  $post_type = get_post_type( $post_id );
  return get_bloginfo('url') . '/anuncio-atualizado/?post_id=' . $post_id;
}

function bazar_get_updated_cadastro_url( $post_id ) {
  return get_bloginfo('url') . '/anuncio-cadastro-atualizado/?post_id=' . $post_id;
}

/**
 * URL pós-cadastro do anúncio: mesma página de sucesso (anúncio atualizado), onde ficam
 * o recado de aprovação e a escolha Grátis / Turbinar. Mantido o nome da função para compat.
 *
 * @param int $post_id ID do anúncio
 * @return string
 */
function bazar_get_anuncio_plano_url( $post_id ) {
  return bazar_get_updated_url( (int) $post_id );
}

/**
 * Verifica se o perfil tem CEP, bairro, cidade, estado e sigla preenchidos.
 *
 * @param int $user_id
 * @return bool
 */
function bazar_user_has_min_address_meta( $user_id ) {
  $user_id = (int) $user_id;
  if ( $user_id < 1 ) {
    return false;
  }
  $cep          = trim( (string) get_user_meta( $user_id, 'cep', true ) );
  $bairro       = trim( (string) get_user_meta( $user_id, 'bairro', true ) );
  $cidade       = trim( (string) get_user_meta( $user_id, 'cidade', true ) );
  $estado       = trim( (string) get_user_meta( $user_id, 'estado', true ) );
  $estado_sigla = trim( (string) get_user_meta( $user_id, 'estado_sigla', true ) );
  if ( $estado_sigla === '' ) {
    $estado_sigla = trim( (string) get_user_meta( $user_id, 'estado-sigla', true ) );
  }
  return ( $cep !== '' && $bairro !== '' && $cidade !== '' && $estado !== '' && $estado_sigla !== '' );
}

/**
 * Aceita redirect_to que aponta para anúncio atualizado (sucesso + plano) ou cadeia
 * cadastro-endereco → anuncio-atualizado.
 *
 * @param string $url
 * @param int    $depth
 * @return bool
 */
function bazar_redirect_targets_anuncio_updated_flow( $url, $depth = 0 ) {
  if ( $depth > 6 || $url === '' ) {
    return false;
  }
  $u = function_exists( 'bazar_sanitize_redirect_url' ) ? bazar_sanitize_redirect_url( $url ) : esc_url_raw( $url );
  if ( $u === '' ) {
    return false;
  }
  if ( ( strpos( $u, '/anuncio-atualizado/' ) !== false || strpos( $u, '/anuncio-cadastro-atualizado/' ) !== false )
    && strpos( $u, 'post_id=' ) !== false ) {
    return true;
  }
  $parts = wp_parse_url( $u );
  if ( empty( $parts['query'] ) ) {
    return false;
  }
  parse_str( $parts['query'], $q );
  if ( empty( $q['redirect_to'] ) ) {
    return false;
  }
  return bazar_redirect_targets_anuncio_updated_flow( $q['redirect_to'], $depth + 1 );
}

/**
 * URL para voltar após confirmar e-mail (fluxo pós-inserção de anúncio).
 *
 * @param int    $user_id
 * @param string $url
 */
function bazar_set_email_confirm_return_url( $user_id, $url ) {
  $user_id = (int) $user_id;
  $url     = function_exists( 'bazar_sanitize_redirect_url' ) ? bazar_sanitize_redirect_url( $url ) : '';
  if ( $user_id < 1 || $url === '' ) {
    return;
  }
  update_user_meta( $user_id, 'bazar_email_confirm_return_url', $url );
}

/**
 * Lê e remove a URL de retorno pós-confirmação de e-mail.
 *
 * @param int $user_id
 * @return string URL sanitizada ou vazio
 */
function bazar_consume_email_confirm_return_url( $user_id ) {
  $user_id = (int) $user_id;
  if ( $user_id < 1 ) {
    return '';
  }
  $raw = get_user_meta( $user_id, 'bazar_email_confirm_return_url', true );
  delete_user_meta( $user_id, 'bazar_email_confirm_return_url' );
  return function_exists( 'bazar_sanitize_redirect_url' ) ? bazar_sanitize_redirect_url( (string) $raw ) : '';
}

/**
 * Próximo passo após e-mail confirmado: redirect_to na URL (fluxo encadeado) ou meta gravada no cadastro do anúncio.
 *
 * @param int $user_id
 * @return string
 */
function bazar_resolve_post_confirm_email_redirect( $user_id ) {
  $user_id = (int) $user_id;
  if ( $user_id < 1 ) {
    return '';
  }
  if ( ! empty( $_GET['redirect_to'] ) ) {
    $r = function_exists( 'bazar_sanitize_redirect_url' )
      ? bazar_sanitize_redirect_url( wp_unslash( (string) $_GET['redirect_to'] ) )
      : '';
    if ( $r !== ''
      && function_exists( 'bazar_redirect_targets_anuncio_updated_flow' )
      && bazar_redirect_targets_anuncio_updated_flow( $r ) ) {
      delete_user_meta( $user_id, 'bazar_email_confirm_return_url' );
      return $r;
    }
  }
  return function_exists( 'bazar_consume_email_confirm_return_url' )
    ? bazar_consume_email_confirm_return_url( $user_id )
    : '';
}
?>