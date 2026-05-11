<?php
add_shortcode('ads_amazon', 'ads_amazon_shortcode');
function ads_amazon_shortcode()
{

  ob_start(); // Inicia o buffer de saída
  get_template_part('template-parts/ads/afiliados/responsive', null, array('css_class' => 'content'));
  return ob_get_clean(); // Retorna o conteúdo do buffer

}
?>