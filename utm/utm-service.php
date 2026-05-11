<?php
/**
 * Servico central de UTM (fonte de verdade).
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

class __BazarUtmService
{
  /**
   * @var string
   */
  private $cookie_name = 'bazar_utm';

  /**
   * Le e sanitiza UTM do cookie.
   *
   * @return array|null
   */
  public function get_utm_from_cookie()
  {
    if (!isset($_COOKIE[$this->cookie_name])) {
      return null;
    }

    $raw_cookie = wp_unslash((string) $_COOKIE[$this->cookie_name]);
    if ($raw_cookie === '') {
      return null;
    }

    $decoded = rawurldecode($raw_cookie);
    $data = json_decode($decoded, true);
    if (!is_array($data)) {
      return null;
    }

    $utm_keys_in_query = !empty($data['utm_keys_in_query']);

    $result = array(
      'utm_source' => $this->normalize_utm_value($data['utm_source'] ?? ''),
      'utm_medium' => $this->normalize_utm_value($data['utm_medium'] ?? ''),
      'utm_campaign' => $this->normalize_utm_value($data['utm_campaign'] ?? ''),
      'utm_content' => $this->normalize_utm_value($data['utm_content'] ?? ''),
      'captured_at' => sanitize_text_field((string) ($data['captured_at'] ?? '')),
      'landing_path' => esc_url_raw((string) ($data['landing_path'] ?? '')),
    );

    $has_any_utm_value = (
      $result['utm_source'] !== ''
      || $result['utm_medium'] !== ''
      || $result['utm_campaign'] !== ''
      || $result['utm_content'] !== ''
    );

    // Sem UTM na URL (cookie antigo só com fbclid etc.): não inventar atribuição.
    if (!$utm_keys_in_query && !$has_any_utm_value) {
      return null;
    }

    $this->apply_utm_defaults($result);

    return $result;
  }

  /**
   * Le e sanitiza UTM da query string atual.
   * Fallback para cenarios sem cookie (ex.: bloqueio de cookie no primeiro hit).
   *
   * @return array|null
   */
  public function get_utm_from_request()
  {
    if (!$this->has_utm_keys_in_request()) {
      return null;
    }

    $result = array(
      'utm_source' => $this->normalize_utm_value($_GET['utm_source'] ?? ''),
      'utm_medium' => $this->normalize_utm_value($_GET['utm_medium'] ?? ''),
      'utm_campaign' => $this->normalize_utm_value($_GET['utm_campaign'] ?? ''),
      'utm_content' => $this->normalize_utm_value($_GET['utm_content'] ?? ''),
      'captured_at' => current_time('mysql'),
      'landing_path' => esc_url_raw((string) (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '')),
    );

    $this->apply_utm_defaults($result);

    return $result;
  }

  /**
   * Indica se a query string traz algum parametro UTM (mesmo vazio).
   *
   * @return bool
   */
  private function has_utm_keys_in_request()
  {
    foreach (array('utm_source', 'utm_medium', 'utm_campaign', 'utm_content') as $key) {
      if (isset($_GET[$key])) {
        return true;
      }
    }
    return false;
  }

  /**
   * Preenche campos UTM vazios quando a URL ja trouxe parametros UTM.
   * utm_campaign vazio usa fallback "site".
   *
   * @param array $utm
   * @return void
   */
  private function apply_utm_defaults(array &$utm)
  {
    if (($utm['utm_campaign'] ?? '') === '') {
      $utm['utm_campaign'] = 'site';
    }
  }

  /**
   * Contexto UTM (cookie first, request fallback).
   *
   * @return array|null
   */
  private function get_utm_context()
  {
    $utm = $this->get_utm_from_cookie();
    if ($utm) {
      return $utm;
    }
    return $this->get_utm_from_request();
  }

  /**
   * Salva UTM de aquisicao no usuario (first-touch, sem sobrescrever).
   *
   * @param int $user_id
   * @return void
   */
  public function save_user_first_touch($user_id)
  {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
      return;
    }

    $existing_campaign = (string) get_user_meta($user_id, 'bazar_user_utm_campaign', true);
    if ($existing_campaign !== '') {
      return;
    }

    $utm = $this->get_utm_context();
    if (!$utm) {
      return;
    }

    update_user_meta($user_id, 'bazar_user_utm_source', $utm['utm_source']);
    update_user_meta($user_id, 'bazar_user_utm_medium', $utm['utm_medium']);
    update_user_meta($user_id, 'bazar_user_utm_campaign', $utm['utm_campaign']);
    update_user_meta($user_id, 'bazar_user_utm_content', $utm['utm_content']);
    update_user_meta($user_id, 'bazar_user_utm_captured_at', $this->resolve_captured_at($utm['captured_at'] ?? ''));
  }

  /**
   * Salva UTM de criacao do anuncio.
   *
   * @param int $post_id
   * @return void
   */
  public function save_ad_utm($post_id)
  {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
      return;
    }

    $utm = $this->get_utm_context();
    if (!$utm) {
      return;
    }

    update_post_meta($post_id, 'bazar_ad_utm_source', $utm['utm_source']);
    update_post_meta($post_id, 'bazar_ad_utm_medium', $utm['utm_medium']);
    update_post_meta($post_id, 'bazar_ad_utm_campaign', $utm['utm_campaign']);
    update_post_meta($post_id, 'bazar_ad_utm_content', $utm['utm_content']);
    update_post_meta($post_id, 'bazar_ad_utm_captured_at', $this->resolve_captured_at($utm['captured_at'] ?? ''));
  }

  /**
   * Salva UTM de boost/transacao.
   *
   * @param int $post_id
   * @return void
   */
  public function save_boost_utm($post_id)
  {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
      return;
    }

    $utm = $this->get_utm_context();
    if (!$utm) {
      return;
    }

    update_post_meta($post_id, 'bazar_boost_utm_source', $utm['utm_source']);
    update_post_meta($post_id, 'bazar_boost_utm_medium', $utm['utm_medium']);
    update_post_meta($post_id, 'bazar_boost_utm_campaign', $utm['utm_campaign']);
    update_post_meta($post_id, 'bazar_boost_utm_content', $utm['utm_content']);
    update_post_meta($post_id, 'bazar_boost_utm_captured_at', $this->resolve_captured_at($utm['captured_at'] ?? ''));
  }

  /**
   * Normaliza valor de UTM.
   *
   * @param mixed $value
   * @param int $max_length
   * @return string
   */
  private function normalize_utm_value($value, $max_length = 150)
  {
    $value = is_scalar($value) ? (string) $value : '';
    $value = sanitize_text_field($value);
    $value = strtolower(trim($value));
    if ($value === '') {
      return '';
    }
    if (strlen($value) > $max_length) {
      $value = substr($value, 0, $max_length);
    }
    return $value;
  }

  /**
   * Resolve captured_at padrao.
   *
   * @param string $captured_at
   * @return string
   */
  private function resolve_captured_at($captured_at)
  {
    $captured_at = sanitize_text_field((string) $captured_at);
    return $captured_at !== '' ? $captured_at : current_time('mysql');
  }
}

