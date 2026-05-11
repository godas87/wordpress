<?php
/**
 * Dados para eventos Meta (Pixel / futura CAPI): UTMs e payload de Purchase do impulsionamento.
 *
 * @package XXXXXX
 */
if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('bazar_pixel_get_utm_context')) {
  /**
   * Retorna contexto UTM para eventos do Pixel.
   *
   * Prioridade:
   * - Purchase / BoostCheckoutCanceled: boost -> ad -> user
   * - CompleteRegistration: ad -> user
   *
   * @param int  $anuncio_id
   * @param bool $is_purchase
   * @return array
   */
  function bazar_pixel_get_utm_context($anuncio_id = 0, $is_purchase = false)
  {
    $anuncio_id = (int) $anuncio_id;
    $utm = array(
      'utm_source' => '',
      'utm_medium' => '',
      'utm_campaign' => '',
      'utm_content' => '',
    );

    $fill_from_prefix = function ($prefix) use (&$utm, $anuncio_id) {
      if ($anuncio_id <= 0) {
        return;
      }
      $utm['utm_source'] = (string) get_post_meta($anuncio_id, "{$prefix}_utm_source", true);
      $utm['utm_medium'] = (string) get_post_meta($anuncio_id, "{$prefix}_utm_medium", true);
      $utm['utm_campaign'] = (string) get_post_meta($anuncio_id, "{$prefix}_utm_campaign", true);
      $utm['utm_content'] = (string) get_post_meta($anuncio_id, "{$prefix}_utm_content", true);
    };

    if ($is_purchase) {
      $fill_from_prefix('bazar_boost');
    }

    if ($utm['utm_source'] === '' && $utm['utm_medium'] === '' && $utm['utm_campaign'] === '' && $utm['utm_content'] === '') {
      $fill_from_prefix('bazar_ad');
    }

    if ($utm['utm_source'] === '' && $utm['utm_medium'] === '' && $utm['utm_campaign'] === '' && $utm['utm_content'] === '') {
      $user_id = 0;
      if ($anuncio_id > 0) {
        $user_id = (int) get_post_field('post_author', $anuncio_id);
      }
      if ($user_id <= 0) {
        $user_id = (int) get_current_user_id();
      }
      if ($user_id > 0) {
        $utm['utm_source'] = (string) get_user_meta($user_id, 'bazar_user_utm_source', true);
        $utm['utm_medium'] = (string) get_user_meta($user_id, 'bazar_user_utm_medium', true);
        $utm['utm_campaign'] = (string) get_user_meta($user_id, 'bazar_user_utm_campaign', true);
        $utm['utm_content'] = (string) get_user_meta($user_id, 'bazar_user_utm_content', true);
      }
    }

    return $utm;
  }
}

if (!function_exists('bazar_pixel_build_boost_purchase_payload')) {
  /**
   * Página de sucesso do impulsionamento (?payment=success): monta o payload do Meta Purchase.
   * Uso: template do pixel, CAPI, relatórios — um único sítio com a regra de negócio.
   *
   * @return array|null {
   *   @type string $event_id    ID de dedupe Meta
   *   @type int    $anuncio_id
   *   @type string $session_id
   *   @type array  $fbq_payload Argumentos do fbq('track','Purchase', …)
   * }
   */
  function bazar_pixel_build_boost_purchase_payload()
  {
    if (
      !is_page(array('anuncio-impulsionado'))
      || !isset($_GET['payment'])
      || sanitize_text_field((string) $_GET['payment']) !== 'success'
    ) {
      return null;
    }

    if (!function_exists('bazar_destaque_get_promo_config')) {
      return null;
    }

    $promo_config = bazar_destaque_get_promo_config();

    $purchase_value = isset($promo_config['preco_normal'])
      ? (float) $promo_config['preco_normal']
      : 47.90;
    $purchase_name = 'Impulsionamento';
    $promo_source = 'none';
    $discount_value = 0.0;

    $anuncio_id = isset($_GET['anuncio']) ? (int) $_GET['anuncio'] : 0;
    $session_id = isset($_GET['session_id']) ? sanitize_text_field((string) $_GET['session_id']) : '';

    $coupon_used = false;
    if ($anuncio_id > 0) {
      $paid_session_id = (string) get_post_meta($anuncio_id, 'destaque_payment_id', true);
      if ($session_id !== '' && $paid_session_id === $session_id) {
        $total_cents = (int) get_post_meta($anuncio_id, 'destaque_amount_total_cents', true);
        $discount_cents = (int) get_post_meta($anuncio_id, 'destaque_amount_discount_cents', true);
        $tipo_checkout = (string) get_post_meta($anuncio_id, 'destaque_tipo', true);

        if ($total_cents > 0) {
          $purchase_value = $total_cents / 100;
        }
        if ($discount_cents > 0) {
          $coupon_used = true;
          $discount_value = $discount_cents / 100;
        }
        if ($tipo_checkout === 'newsletter') {
          $promo_source = 'newsletter';
        } elseif ($coupon_used) {
          $promo_source = 'coupon';
        }
      }
    }

    $preco_normal = isset($promo_config['preco_normal'])
      ? (float) $promo_config['preco_normal']
      : 47.90;
    $preco_desconto = isset($promo_config['preco_desconto_newsletter'])
      ? (float) $promo_config['preco_desconto_newsletter']
      : $preco_normal;
    $desconto_percent = isset($promo_config['desconto_percent'])
      ? (int) $promo_config['desconto_percent']
      : 0;
    $aplica_checkout = !empty($promo_config['aplica_desconto_checkout']);

    if ($purchase_value <= 0) {
      $purchase_value = ($aplica_checkout && $desconto_percent >= 50) ? $preco_desconto : $preco_normal;
    }
    if ($promo_source === 'none' && $aplica_checkout && $desconto_percent >= 50) {
      $promo_source = 'promo_checkout_50';
    }
    if ($promo_source !== 'none') {
      $purchase_name = 'Impulsionamento - Promoção';
    }

    $purchase_utm = bazar_pixel_get_utm_context($anuncio_id, true);
    $purchase_event_id = 'purchase_' . md5((string) $anuncio_id . '|' . (string) $session_id . '|' . (string) get_current_user_id());

    $fbq_payload = array(
      'value' => round((float) $purchase_value, 2),
      'currency' => 'BRL',
      'content_name' => $purchase_name,
      'promo_source' => $promo_source,
      'discount_value' => round((float) $discount_value, 2),
      'utm_source' => $purchase_utm['utm_source'],
      'utm_medium' => $purchase_utm['utm_medium'],
      'utm_campaign' => $purchase_utm['utm_campaign'],
      'utm_content' => $purchase_utm['utm_content'],
    );

    return array(
      'event_id' => $purchase_event_id,
      'anuncio_id' => $anuncio_id,
      'session_id' => $session_id,
      'fbq_payload' => $fbq_payload,
    );
  }
}
