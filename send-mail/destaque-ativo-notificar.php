<?php
/**
 * E-mail ao autor: impulsionamento (termo destaque) ativo e anúncio já publicado.
 * Disparado em `bazar_destaque_service_apply_destaque_term_and_notify()` (pós-pagamento elegível ou pós-CPF).
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Envia e-mail avisando que o destaque está valendo (anúncio publicado).
 *
 * @param int $post_id ID do anúncio.
 * @return bool True se enviou ou se não era necessário; false só em falha relevante.
 */
function bazar_send_mail_destaque_ativo_anuncio_publicado($post_id)
{
  $post_id = (int) $post_id;
  if ($post_id < 1) {
    return false;
  }

  if (get_post_type($post_id) !== 'post' || get_post_status($post_id) !== 'publish') {
    return false;
  }

  if (!has_term('destaque', 'status', $post_id)) {
    return false;
  }

  $lock_key = 'bazar_destaque_ativo_mail_' . $post_id;
  if (get_transient($lock_key)) {
    return true;
  }

  $author_id = (int) get_post_field('post_author', $post_id);
  if ($author_id < 1) {
    return false;
  }

  $user_name = get_the_author_meta('user_firstname', $author_id);
  if ($user_name === '' || $user_name === null) {
    $user = get_userdata($author_id);
    $user_name = $user ? $user->display_name : '';
  }

  $user_email = get_the_author_meta('user_email', $author_id);
  if ($user_email === '' || !is_email($user_email)) {
    return false;
  }

  $anuncio_url = get_permalink($post_id);
  if (!$anuncio_url) {
    $anuncio_url = home_url('/?p=' . $post_id);
  }

  $check_icon_url = apply_filters(
    'bazar_email_publicacao_check_icon_url',
    'https://XXXXXX/src/imgs/check.png'
  );

  $product_data = function_exists('bazar_get_product_data') ? bazar_get_product_data($post_id) : null;
  $anuncio_titulo = ($product_data && !empty($product_data['title'])) ? $product_data['title'] : get_the_title($post_id);
  $preco_anuncio = '';
  if ($product_data && !empty($product_data['formatted']['valor'])) {
    $preco_anuncio = 'R$ ' . $product_data['formatted']['valor'];
  }

  $thumb_url = get_the_post_thumbnail_url($post_id, 'medium');
  if (!$thumb_url) {
    $thumb_url = get_the_post_thumbnail_url($post_id, 'thumbnail');
  }

  $product_card = array();
  if ($thumb_url || $anuncio_titulo !== '' || $preco_anuncio !== '') {
    $product_card = array(
      'image_url' => $thumb_url ? $thumb_url : '',
      'title' => $anuncio_titulo,
      'price' => $preco_anuncio,
    );
  }

  $email_body = 'Parabéns ' . esc_html($user_name) . ', o <strong>impulsionamento</strong> do seu anúncio já está valendo: ele aparece em destaque nas buscas e listagens enquanto estiver ativo. Seja-bem vindo a nossa comunidade de ciclistas.';

  if (function_exists('bazar_publication_service_html_paragraph_email_opcional')) {
    $email_body .= bazar_publication_service_html_paragraph_email_opcional($author_id);
  }

  $highlighted_sections = array(
    array(
      'title' => 'Anúncio impulsionado',
      'title_image_url' => $check_icon_url,
      'content' => 'Seu anúncio está em modo TURBO, com destaque nas buscas e listagens do XXXXXX!',
      'content_allow_html' => false,
      'product_card' => $product_card,
      'button_url' => $anuncio_url,
      'button_text' => 'Ver meu anúncio',
      'button_compact' => true,
      'highlight_color' => '#2e7d32',
      'bg_color' => '#f4f6f4',
      'border_color' => '#dde3d6',
      'promo_notice' => '',
    ),
  );

  // Só o CTA verde no highlight (evita botão preto duplicado em generate_buttons)
  $buttons = array();

  if (!class_exists('__Bazar_Send_Mail')) {
    return false;
  }

  $mail_data = array(
    'name' => $user_name,
    'to' => $user_email,
    'subject' => 'Seu anúncio está em destaque',
    'msg_header' => 'Impulsionamento ativo',
    'email_body' => $email_body,
    'buttons' => $buttons,
    'highlighted_sections' => $highlighted_sections,
    'highlighted_sections_before_buttons' => true,
    'fail_on_error' => false,
  );

  $send_mail = new __Bazar_Send_Mail();
  $ok = (bool) $send_mail->send_mail_msg($mail_data);

  if ($ok) {
    set_transient($lock_key, 1, 15 * MINUTE_IN_SECONDS);
  }

  return $ok;
}
