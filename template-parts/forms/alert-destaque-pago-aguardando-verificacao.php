<?php
/**
 * Single: autor com pagamento de impulsionamento confirmado, destaque ainda não liberado (CPF não verificado).
 */
if (!defined('ABSPATH')) {
  exit;
}

$current_user_id = get_current_user_id();
if (!is_user_logged_in() || !$current_user_id) {
  return;
}

$post_id = isset($post_id) ? (int) $post_id : (int) get_the_ID();
if ($post_id <= 0) {
  return;
}

$post_author_id = (int) get_post_field('post_author', $post_id);
if ($post_author_id !== (int) $current_user_id) {
  return;
}

if (!defined('BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO')) {
  return;
}

$aguarda = get_post_meta($post_id, BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO, true) === '1';
$pago = get_post_meta($post_id, 'destaque_payment_status', true) === 'paid';

if (!$aguarda || !$pago) {
  return;
}

$minha_conta_url = home_url('/minha-conta/');
?>
<div class="alert alert-warning clear" role="status" style="margin-bottom: .5rem;">
  <i class="fas fa-rocket pr-1" aria-hidden="true"></i>
  <span>
    <?php echo wp_kses_post(
      sprintf(
        /* translators: %s: URL Minha Conta */
        __(
          'Seu anúncio foi <strong>impulsionado</strong> e o <strong>pagamento foi confirmado</strong>. Para <strong>liberar o destaque</strong> nas buscas, basta <a href="%s">confirmar seus dados cadastrais</a> (validação do CPF) em Minha conta.',
          'bazar'
        ),
        esc_url($minha_conta_url)
      )
    ); ?>
  </span>
</div>
