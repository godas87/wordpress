<?php
/**
 * Parâmetros GET da listagem (archive / index): chaves ignoradas, sanitização e validação de taxonomias.
 * Alinhado à refatoração descrita em config/docs/ANALISE-ARCHIVE-QUERY-BUILDER.md
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('bazar_archive_get_ignored_get_keys')) {
  /**
   * Chaves de controlo / layout que nunca devem afetar a WP_Query da listagem
   * (redirecionamentos de cidade/estado, campos de formulário, etc.).
   *
   * @return string[]
   */
  function bazar_archive_get_ignored_get_keys()
  {
    $keys = array(
      'category_id',
      'category_fields',
      'title',
      'estado',
      'cidade',
      'estado_sigla',
    );

    return apply_filters('bazar_archive_ignored_get_keys', $keys);
  }
}

if (!function_exists('bazar_archive_taxonomy_applies_to_post')) {
  /**
   * Taxonomia registada e associada ao post type `post`.
   *
   * @param string $taxonomy
   * @return bool
   */
  function bazar_archive_taxonomy_applies_to_post($taxonomy)
  {
    $taxonomy = sanitize_key($taxonomy);
    if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
      return false;
    }

    $obj = get_taxonomy($taxonomy);
    if (!$obj) {
      return false;
    }

    return in_array('post', (array) $obj->object_type, true);
  }
}

if (!function_exists('bazar_archive_sanitize_get_param')) {
  /**
   * Sanitiza um valor de parâmetro GET antes de ir para tax_query / meta_query.
   *
   * @param string $key   Chave em $_GET (ex.: componente_filter, order).
   * @param mixed  $value Valor bruto.
   * @return mixed|string|int
   */
  function bazar_archive_sanitize_get_param($key, $value)
  {
    $key = (string) $key;

    if ($key === 'page' || $key === 'paged') {
      return max(0, absint($value));
    }

    if (is_array($value)) {
      $out = array();
      foreach ($value as $k => $v) {
        $out[$k] = sanitize_text_field(wp_unslash(strval($v)));
      }
      return $out;
    }

    $raw = wp_unslash(strval($value));

    if ($key === 'order') {
      return sanitize_key($raw);
    }

    return sanitize_text_field($raw);
  }
}
