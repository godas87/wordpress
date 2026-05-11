<?php
/**
 * Fachada pública sobre __Bazar_Product_Data_Repository (hub por contexto).
 *
 * @package XXXXXX
 */
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Dados do produto para um contexto específico (SEO, schema, relacionados, etc.).
 *
 * @param int|null $post_id
 * @param string $context Uma das constantes __Bazar_Product_Data_Repository::CONTEXT_*
 * @param \WP_Post|null $post
 * @return array|null
 */
function bazar_get_product_data_for_context($post_id = null, $context = null, $post = null)
{
  if ($context === null) {
    $context = __Bazar_Product_Data_Repository::CONTEXT_FULL;
  }

  if (!$post_id && empty($post)) {
    $post_id = get_the_ID();
  }

  if (!$post_id || $post_id === 0 || !is_numeric($post_id)) {
    if (
      !is_archive()
      && !is_tax()
      && !is_category()
      && !is_home()
      && !is_search()
      && !is_front_page()
    ) {
      error_log("[bazar_get_product_data_for_context] Invalid post_id: " . var_export($post_id, true) . " | Context: " . (is_singular() ? 'singular' : 'not singular'));
    }
    return null;
  }

  return __Bazar_Product_Data_Repository::get((int) $post_id, $context, $post);
}

/**
 * Pacote completo (single, e-mails, aprovação, etc.).
 *
 * @param int|null $post_id
 * @param \WP_Post|null $post
 * @return array|null
 */
function bazar_get_product_data($post_id = null, $post = null)
{
  return bazar_get_product_data_for_context(
    $post_id,
    __Bazar_Product_Data_Repository::CONTEXT_FULL,
    $post
  );
}

/**
 * Dados mínimos para cards em listagens.
 *
 * @param int|null $post_id
 * @return array|null
 */
function bazar_get_product_card_data($post_id = null)
{
  $post_id = $post_id ?: get_the_ID();
  if (!$post_id || !is_numeric($post_id)) {
    return null;
  }

  return __Bazar_Product_Data_Repository::get((int) $post_id, __Bazar_Product_Data_Repository::CONTEXT_CARD, null);
}
