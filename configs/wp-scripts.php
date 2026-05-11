<?php
add_action('wp_enqueue_scripts', 'custom_enqueue_scripts', 100);
function custom_enqueue_scripts()
{
  // REGISTER MAIN JS — filemtime em ?ver= força novo URL após cada deploy (browser/CDN/LSCache do ficheiro)
  $bazar_app_path = get_template_directory() . '/assets/js/app.min.js';
  $bazar_app_ver = file_exists($bazar_app_path) ? (string) filemtime($bazar_app_path) : '1';
  wp_register_script('app', get_template_directory_uri() . '/assets/js/app.min.js', array(), $bazar_app_ver, false);
  // IDs dos componentes (centralizados - sincronizados com PHP)
  $component_title_ids = bazar_get_component_title_ids();
  // Porcentagem de desconto newsletter (para labels no modal impulsionar)
  $bazar_desconto_percent = 10;
  if (function_exists('bazar_destaque_get_promo_config')) {
    $bazar_desconto_percent = bazar_destaque_get_promo_config()['desconto_percent'];
  }

  // REGISTER AJAX OBJECT
  if (is_user_logged_in()):
    //canvas images - GERAR SCREENSHOT DE ANÚNCIOS
    wp_register_script('html2canvas', get_template_directory_uri() . '/assets/js/lib/html2canvas.min.js', array(), false, true);

    global $current_user;
    wp_localize_script('app', 'ajax_object', array(
      'ajaxurl' => admin_url('admin-ajax.php'),
      'template_url' => get_template_directory_uri(),
      'userEstado' => $current_user->estado,
      'userCidade' => $current_user->cidade,
      'userNome' => $current_user->display_name,
      'userEmail' => $current_user->user_email,
      'userCEP' => $current_user->cep,
      'bazar_geo_nonce' => wp_create_nonce('bazar_geo_nonce'),
      'bazar_location_nonce' => wp_create_nonce('bazar_location_nonce'),
      'bazar_count_click_nonce' => wp_create_nonce('bazar_count_click'),
      'nonce_block_user' => wp_create_nonce('nonce_block_user'),
      'nonce_cancelar_conta' => wp_create_nonce('nonce_cancelar_conta'),
      'nonce_reativar_conta' => wp_create_nonce('nonce_reativar_conta'),
      'nonce_stripe_create_checkout' => wp_create_nonce('nonce_stripe_create_checkout'),
      'nonce_stripe_checkout' => wp_create_nonce('bazar_stripe_checkout'),
      'home_url' => home_url('/'),
      'component_title_ids' => $component_title_ids, // IDs dos componentes sincronizados com PHP
      'bazar_desconto_percent' => $bazar_desconto_percent,
    ));
  else:
    wp_localize_script('app', 'ajax_object', array(
      'ajaxurl' => admin_url('admin-ajax.php'),
      'template_url' => get_template_directory_uri(),
      'userCEP' => '',
      'bazar_geo_nonce' => wp_create_nonce('bazar_geo_nonce'),
      'bazar_location_nonce' => wp_create_nonce('bazar_location_nonce'),
      'bazar_count_click_nonce' => wp_create_nonce('bazar_count_click'),
      'nonce_block_user' => wp_create_nonce('nonce_block_user'),
      'nonce_cancelar_conta' => wp_create_nonce('nonce_cancelar_conta'),
      'nonce_reativar_conta' => wp_create_nonce('nonce_reativar_conta'),
      'nonce_stripe_create_checkout' => wp_create_nonce('nonce_stripe_create_checkout'),
      'nonce_stripe_checkout' => wp_create_nonce('bazar_stripe_checkout'),
      'home_url' => home_url('/'),
      'component_title_ids' => $component_title_ids, // IDs dos componentes sincronizados com PHP
      'bazar_desconto_percent' => $bazar_desconto_percent,
    ));
  endif;
  // ENQUEUE GLOBAL CSS (filemtime evita cache eterno com versão fixa)
  $bazar_css_path = get_template_directory() . '/assets/css/css.css';
  $bazar_css_ver = file_exists($bazar_css_path) ? (string) filemtime($bazar_css_path) : '1.0.0';
  wp_enqueue_style('bazar-bikes', get_template_directory_uri() . '/assets/css/css.css', array(), $bazar_css_ver, 'all');
  // DEQUEUE  
  wp_dequeue_style('global-styles');
  wp_dequeue_style('wp-block-library');
  wp_dequeue_style('wp-block-library-theme');
  wp_dequeue_style('classic-theme-styles');
  wp_dequeue_style('wp-block-cover-inline-css');
  wp_dequeue_style('core-block-supports');
  wp_dequeue_style('contact-form-7');
  // DEREGISTER
  wp_deregister_style('wp-pagenavi');
  wp_deregister_style('core-block-supports');
  // DEQUEUE SCRIPTS
  wp_dequeue_script('contact-form-7');
  // PLUGINS on AMP PAGES
  if (bazar_is_amp_page()):
    wp_deregister_script('affegg-price-alert');
    wp_dequeue_script('affegg-price-alert-js');
    wp_dequeue_script('affegg-price-alert');
    wp_dequeue_style('litespeed-cache-dummy-css');
    wp_deregister_style('litespeed-cache-dummy-css');
  endif;

}

add_action('wp_footer', function () {
  wp_dequeue_style('core-block-supports');
}, 5);

//GENERANL CSS
add_action('get_footer', 'init_css');
function init_css()
{

  wp_dequeue_style('core-block-supports');
  // GLOBAL JS
  wp_enqueue_script('app');
  // Stripe Checkout Simple (sempre carregar para modais)
  wp_enqueue_script('destaque-checkout-simple');
  // Contact Form 7 Plugin
  if (is_page('publicidade')):
    wp_enqueue_script('contact-form-7');
    wp_enqueue_style('contact-form-7');
  endif;
  // ScreenShot Product Generate (download do card — single, exibir anúncio, Minha Conta, Meus anúncios)
  if (
    is_user_logged_in()
    && (
      is_singular('post')
      || is_page(array('minha-conta', 'meus-anuncios'))
    )
  ):
    wp_enqueue_script('html2canvas');
  endif;
}
;