<?php
/**
 * Alerta na single: anúncio aprovado pelo ADM, mas ainda pending (falta perfil completo — e-mail não bloqueia).
 */
if (!defined('ABSPATH')) {
  exit;
}

$current_user_id = get_current_user_id();
if (!is_user_logged_in() || !$current_user_id) {
  return;
}

// Argumentos vêm de get_template_part( ..., null, array( 'post_id' => ..., 'post_status_global' => ... ) ).
$post_id = isset($post_id) ? (int) $post_id : (int) get_the_ID();
if ($post_id <= 0) {
  return;
}

$post_author_id = (int) get_post_field('post_author', $post_id);
if ($post_author_id !== (int) $current_user_id) {
  return;
}

$status = isset($post_status_global) ? (string) $post_status_global : '';
if ($status === '' && function_exists('bazar_get_anuncio_status')) {
  $sd = bazar_get_anuncio_status($post_id);
  $status = isset($sd['status']) ? (string) $sd['status'] : '';
}

if ($status !== 'aprovado_aguardando_dados') {
  return;
}

$minha_conta_url = home_url('/minha-conta/');
?>
<div class="alert alert-warning clear" role="status" style="margin-bottom: .5rem;">
  <i class="fa fa-check-circle pr-1"></i>
  <span>
    <?php echo wp_kses_post(
      sprintf(
        /* translators: %s: URL Minha Conta */
        __('Seu anúncio foi <strong>aprovado para exibição</strong>, no entanto você precisa <a href="%s">completar seu cadastro</a> para que ela seja publicado.', 'bazar'),
        esc_url($minha_conta_url)
      )
    ); ?>
  </span>
</div>