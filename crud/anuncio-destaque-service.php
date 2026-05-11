<?php
/**
 * Serviço de destaque (impulsionar): termo `destaque` só quando o usuário é elegível (CPF + verificado).
 *
 * Relação com publicação (`anuncio-publication-service.php`):
 * - O impulsionamento pode ser pago com o anúncio ainda em `pending`; o termo pode ser aplicado antes do ar,
 *   mas o e-mail “destaque ativo” só é enviado quando o post está `publish` (igual ao fluxo pós-verificação de CPF).
 * - Quando o ADM aprova / o perfil completa, `bazar_publication_service_try_publish_for_user()` publica;
 *   em seguida costuma chamar-se `bazar_destaque_service_try_apply_pending_for_user()` para liberar destaque
 *   pago que estava só com meta `bazar_destaque_aguarda_verificacao`.
 *
 * O Stripe (`stripe-payment.php`) regista sempre as metas de pagamento e delega a visibilidade do termo a
 * `bazar_destaque_service_sync_visibility_after_paid()` para evitar duplicar regras.
 */

if (!defined('ABSPATH')) {
  exit;
}

/** Meta: pagamento ok, mas destaque ainda não liberado (falta verificação de CPF) */
define('BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO', 'bazar_destaque_aguarda_verificacao');

/**
 * Usuário pode receber destaque efetivo (mesma lógica do selo na single + CPF com 11 dígitos).
 *
 * @param int $user_id
 * @return bool
 */
function bazar_destaque_service_usuario_elegivel($user_id)
{
  if (!$user_id || !function_exists('bazar_perfil_verificado')) {
    return false;
  }
  if (!bazar_perfil_verificado($user_id)) {
    return false;
  }
  $cpf_digits = preg_replace('/\D/', '', (string) get_user_meta($user_id, 'cpf', true));
  return strlen($cpf_digits) === 11;
}

/**
 * Associa o termo destaque, limpa “aguarda verificação” e notifica por e-mail se o anúncio já está publicado.
 * Não grava metas de pagamento. Presume que o caller já validou elegibilidade e regras de negócio do post.
 *
 * @param int $post_id
 * @return bool True se o termo foi aplicado (term ID válido)
 */
function bazar_destaque_service_apply_destaque_term_and_notify($post_id)
{
  $post_id = (int) $post_id;
  $destaque_term_id = function_exists('bazar_get_destaque_term_id') ? (int) bazar_get_destaque_term_id() : 0;
  if ($post_id <= 0 || $destaque_term_id <= 0) {
    return false;
  }

  wp_set_object_terms($post_id, array($destaque_term_id), 'status', true);
  update_post_meta($post_id, 'destaque_ativo', '1');
  delete_post_meta($post_id, BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO);
  clean_post_cache($post_id);

  if (
    get_post_status($post_id) === 'publish'
    && function_exists('bazar_send_mail_destaque_ativo_anuncio_publicado')
  ) {
    bazar_send_mail_destaque_ativo_anuncio_publicado($post_id);
  }

  return true;
}

/**
 * Ajusta termo destaque e metas após pagamento confirmado (Stripe ou outro).
 * Não altera `destaque_payment_*` — o caller grava essas metas antes.
 *
 * @param int $post_id
 * @param int $user_id
 */
function bazar_destaque_service_sync_visibility_after_paid($post_id, $user_id)
{
  $post_id = (int) $post_id;
  $user_id = (int) $user_id;
  if ($post_id <= 0 || $user_id <= 0) {
    return;
  }

  $destaque_term_id = function_exists('bazar_get_destaque_term_id') ? (int) bazar_get_destaque_term_id() : 0;
  $elegivel = bazar_destaque_service_usuario_elegivel($user_id);

  if ($elegivel && $destaque_term_id > 0) {
    bazar_destaque_service_apply_destaque_term_and_notify($post_id);
    return;
  }

  if ($destaque_term_id > 0 && has_term('destaque', 'status', $post_id)) {
    $terms = wp_get_object_terms($post_id, 'status', array('fields' => 'ids'));
    if (!is_wp_error($terms) && !empty($terms)) {
      $terms = array_diff($terms, array($destaque_term_id));
      wp_set_object_terms($post_id, $terms, 'status', false);
    }
  }
  update_post_meta($post_id, 'destaque_ativo', '0');
  update_post_meta($post_id, BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO, '1');
  clean_post_cache($post_id);
}

/**
 * Aplica o termo de destaque e metas quando o pagamento já foi registrado e o usuário é elegível.
 *
 * @param int $post_id
 * @param int $user_id
 * @return bool True se o destaque foi aplicado agora
 */
function bazar_destaque_service_aplicar_destaque_no_post($post_id, $user_id)
{
  $post_id = (int) $post_id;
  $user_id = (int) $user_id;
  if ($post_id <= 0 || $user_id <= 0) {
    return false;
  }
  $post = get_post($post_id);
  if (!$post || $post->post_type !== 'post' || (int) $post->post_author !== $user_id) {
    return false;
  }
  if ($post->post_status !== 'publish') {
    return false;
  }
  if (has_term('vendido', 'status', $post_id)) {
    return false;
  }
  if (!bazar_destaque_service_usuario_elegivel($user_id)) {
    return false;
  }
  $destaque_term_id = function_exists('bazar_get_destaque_term_id') ? (int) bazar_get_destaque_term_id() : 0;
  if ($destaque_term_id <= 0) {
    return false;
  }

  if (has_term('destaque', 'status', $post_id)) {
    update_post_meta($post_id, 'destaque_ativo', '1');
    delete_post_meta($post_id, BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO);
    clean_post_cache($post_id);
    return true;
  }

  return bazar_destaque_service_apply_destaque_term_and_notify($post_id);
}

/**
 * Para todos os anúncios do usuário com pagamento pago e aguardando verificação, aplica destaque.
 *
 * @param int $user_id
 * @return int[] IDs dos posts que passaram a ter destaque efetivo
 */
function bazar_destaque_service_try_apply_pending_for_user($user_id)
{
  $applied = array();
  if (!$user_id || !bazar_destaque_service_usuario_elegivel($user_id)) {
    return $applied;
  }

  $posts = get_posts(array(
    'author' => $user_id,
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_query' => array(
      'relation' => 'AND',
      array(
        'key' => 'destaque_payment_status',
        'value' => 'paid',
        'compare' => '=',
      ),
      array(
        'key' => BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO,
        'value' => '1',
        'compare' => '=',
      ),
    ),
    'suppress_filters' => false,
  ));

  foreach ($posts as $pid) {
    if (bazar_destaque_service_aplicar_destaque_no_post((int) $pid, $user_id)) {
      if (has_term('destaque', 'status', (int) $pid)) {
        $applied[] = (int) $pid;
      }
    }
  }

  return $applied;
}
