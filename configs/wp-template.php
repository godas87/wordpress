<?php
// Log de template apenas quando flag de debug estiver ativa
if (defined('BAZAR_DEBUG_TEMPLATES') && BAZAR_DEBUG_TEMPLATES) {
  add_filter('template_include', function ($template) {
    error_log('TEMPLATE_CHOSEN: ' . $template);
    return $template;
  });
}

add_filter('template_include', 'custom_template_include');
function custom_template_include($template)
{

  global $wp_query;
  $post_type = get_query_var('post_type');
  $post_types_array = array(
    'teste',
  );

  if (
    !is_admin()
    && $wp_query->is_singular($post_types_array)
  ) {
    $new_template = 'single-blog.php';
    $template = locate_template($new_template);
  }


  if (
    !is_admin()
    && $wp_query->is_singular('blog') && is_amp_endpoint()
  ) {
    $new_template = 'single-amp.php';
    $template = locate_template($new_template);
  }


  if (
    !is_admin()
    && $wp_query->is_singular()
    && $post_type == 'glossario-ciclismo'
  ) {
    $new_template = 'single-blog.php';
    $template = locate_template($new_template);
  }


  if (
    !is_admin()
    && $wp_query->is_archive()
    && $post_type == 'web-stories'
  ) {
    $new_template = 'archive-blog.php';
    $template = locate_template($new_template);
  }

  // /bicicleta-modalidade/ (sem termo): exibir archive com listagem
  if (
    ! is_admin()
    && (int) get_query_var( 'bazar_modalidade_archive' ) === 1
  ) {
    $template = locate_template( 'archive.php' );
  }

  //default
  return $template;
}
;