<?php
add_filter('the_content', 'add_tag_figure');
function add_tag_figure($content)
{

  // Aplica o filtro ao conteúdo das postagens AMP
  if (is_amp_endpoint()) {
    $content = convert_images_to_amp($content);
    $content = convert_iframe_to_amp($content);
    return $content;
  }

  // Verifica se está em uma postagem única e se é um post do tipo 'post'
  if (
    is_singular('blog')
    || is_singular('glossario-ciclismo')
    || is_singular('teste')
  ) {
    // Divida o conteúdo em parágrafos
    $paragrafos = explode('</p>', $content);
    if (count($paragrafos) > 2):
      $x = 0;
      $new_content = '';
      foreach ($paragrafos as $key => $paragrafo) {
        $new_content .= $paragrafo . '</p>';
        if ($x == 2 || $x > 8 && $x % 6 == 0) {
          $new_content .= do_shortcode('[ads_amazon]');
        }
        $x++;
      }
      $content = $new_content;
    endif;

    $content = preg_replace('/s*(<div .*>)?\s*(<img .* \/>)\s*(<\/div>)/iU', '\1<figure class="full">\2</figure>\3', $content);
    $content = preg_replace('/s*(<a .*>)?\s*(<img .* \/>)\s*(<\/a>)/iU', '<figure class="full">\1\2\3</figure>', $content);
    $content = preg_replace('/(<img .* \/>)/', '<figure class="full">\1</figure>', $content);
  }
  return $content;
}

function add_tag_iframe($content)
{
  $content = preg_replace('/(<iframe [^>]+>)(.*?)(<\/iframe>)/', '<div class="responsive-embed">${0}</div>', $content);
  return $content;
}