<?php
// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Filtro de fallback: remove o link do LiteSpeed dummy CSS do HTML
 * caso seja injetado de forma que wp_dequeue não capture.
 */
add_filter('style_loader_tag', 'bazar_amp_filter_litespeed_dummy_css', 10, 4);
function bazar_amp_filter_litespeed_dummy_css($html, $handle, $href, $media)
{
  if (!bazar_is_amp_page()) {
    return $html;
  }
  if ($handle === 'litespeed-cache-dummy-css' || (is_string($href) && strpos($href, 'litespeed-dummy') !== false)) {
    return '';
  }
  return $html;
}

/**
 * Verifica se a página atual é AMP (blog ?amp=1 ou web-stories).
 */
function bazar_is_amp_page()
{
  return is_amp_endpoint() || is_singular('web-stories');
}

function is_amp_endpoint()
{
  return (isset($_GET['amp']) && $_GET['amp'] == 1);
}
function convert_iframe_to_amp($content)
{

  // Substitui <iframe> por <amp-iframe> corretamente
  $content = preg_replace_callback(
    '/<iframe\s([^>]*)\s?\/?>(.*?)<\/iframe>/is',
    function ($matches) {
      // Atributos da tag <iframe>
      $attributes = trim($matches[1]);
      // Conteúdo da tag <iframe> (se houver)
      $content = trim($matches[2]);
      // Adiciona os atributos necessários ao <amp-iframe>
      // Exemplo de atributos AMP:
      $amp_attributes = preg_replace('/\bloading\s*=\s*"[^"]*"/i', '', $attributes); // Remove atributo de loading
      $amp_attributes = trim($amp_attributes);
      // Retorna a tag <amp-iframe> com atributos e conteúdo
      return '<amp-iframe ' . $amp_attributes . ' layout="responsive" frameborder="0">' . $content . '</amp-iframe>';
    },
    $content
  );
  return $content;

}
function convert_images_to_amp($content)
{
  // Regex para encontrar todas as tags <img>
  $pattern = '/<img(.*?)>/i';

  // Função de callback para substituir <img> por <amp-img>
  $replacement = function ($matches) {
    // Extrai os atributos da tag <img>
    $img_tag = $matches[0];
    preg_match('/src="([^"]+)"/i', $img_tag, $src);
    preg_match('/width="([^"]+)"/i', $img_tag, $width);
    preg_match('/height="([^"]+)"/i', $img_tag, $height);
    preg_match('/alt="([^"]*)"/i', $img_tag, $alt);

    // Se não encontrar largura ou altura, atribui valores padrão
    $width = isset($width[1]) ? $width[1] : '600';
    $height = isset($height[1]) ? $height[1] : '400';
    $alt = isset($alt[1]) ? $alt[1] : '';

    // Cria a tag <amp-img>
    $amp_img = sprintf(
      '<amp-img src="%s" width="%s" height="%s" layout="responsive" alt="%s"></amp-img>',
      esc_url($src[1]),
      esc_attr($width),
      esc_attr($height),
      esc_attr($alt)
    );

    return $amp_img;
  };

  // Aplica a substituição ao conteúdo
  $content = preg_replace_callback($pattern, $replacement, $content);

  return $content;
}
?>