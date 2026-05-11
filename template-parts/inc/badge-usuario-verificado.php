<?php
/**
 * Selo "Usuário verificado" (estilo rede social: círculo + check).
 *
 * @param array $args extract(): use $show_verified_badge (nome único — evita colisão EXTR_SKIP com outros partials).
 */
if (!defined('ABSPATH')) {
  exit;
}
$is_verified = isset($args['is_verified'])
  ? ($args['is_verified'] == 'true' || $args['is_verified'] == true || $args['is_verified'] == '1' || $args['is_verified'] == 1)
  : false;

$active_class = $is_verified ? 'active' : '';

$gid = function_exists('wp_unique_id') ? wp_unique_id('bazar-uvb-') : 'bazar-uvb-' . wp_rand(1000, 9999);
$label = ($is_verified) ? __('Usuário verificado', 'bazar') : __('Usuário não verificado', 'bazar');
?>
<span class="user-verified-badge <?php echo $active_class; ?>" role="img" aria-label="<?php echo esc_attr($label); ?>"
  title="<?php echo esc_attr($label); ?>">
  <svg class="user-verified-badge__svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"
    style="display:block;">
    <defs>
      <linearGradient id="<?php echo esc_attr($gid); ?>" x1="22%" y1="0%" x2="78%" y2="100%">
        <stop offset="0%" stop-color="#3897f0" />
        <stop offset="55%" stop-color="#2d88e6" />
        <stop offset="100%" stop-color="#1c5bbf" />
      </linearGradient>
    </defs>
    <circle cx="12" cy="12" r="11" fill="url(#<?php echo esc_attr($gid); ?>)" />
    <path fill="#fff" d="M10.2 14.4 7.1 11.3l1.05-1.05 2.05 2.05 5.65-5.65 1.05 1.08-6.7 6.67z" />
  </svg>
  <span class="show-for-sr"><?php echo esc_html($label); ?></span>
</span>