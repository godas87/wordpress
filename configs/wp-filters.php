<?php
add_filter('doing_it_wrong_trigger_error', '__return_false');
// REMOVE SEPARATE CORE BLOCK ASSETS
add_filter('should_load_separate_core_block_assets', '__return_false', 99);
// ADD THUMBNAIL TO POST
add_filter('post_thumbnail_html', 'wpdocs_post_image_html', 10, 3);
function wpdocs_post_image_html($html, $post_id, $post_image_id)
{
  $html = '<a href="' . get_permalink($post_id) . '" alt="' . esc_attr(get_the_title($post_id)) . '">' . $html . '</a>';
  return $html;
}
// REMOVE SPECULATIVE LOADING
add_filter('wp_speculative_loading_enabled', '__return_false');
add_filter('wp_speculation_rules_configuration', '__return_null');
// REMOVE TAG <P> ON CONTACT FORM 7
add_filter('wpcf7_autop_or_not', '__return_false');
// desabilita atualizações ACF Fields
// desabilita atualizações Speaker
add_filter('site_transient_update_plugins', 'filter_plugin_updates');
function filter_plugin_updates($value)
{
  unset($value->response['advanced-custom-fields/acf.php']);
  unset($value->response['speaker/speaker.php']);
  return $value;
}
// ADD TITLE TO MENU ITEM
add_filter('nav_menu_link_attributes', 'add_menu_link_title', 10, 3);
function add_menu_link_title($atts, $item, $args)
{
  if ($item->title) {
    $atts['title'] = $item->title;
  }
  return $atts;
}

// Remove filtro obsoleto de imagens responsivas (deprecated desde WP 5.5); usar wp_filter_content_tags
add_action('init', function () {
  remove_filter('the_content', 'wp_make_content_images_responsive');
  // add_filter('the_content', 'wp_image_add_srcset_and_sizes');
}, 11);

// CLEAR CLASSES IN NAV_MENU
add_filter('nav_menu_css_class', 'my_css_attributes_filter', 100, 1);
add_filter('nav_menu_item_id', 'my_css_attributes_filter', 100, 1);
//add_filter('page_css_class', 'my_css_attributes_filter', 100, 1);
function my_css_attributes_filter($var)
{
  return is_array($var) ? array_intersect(
    $var,
    array(
      'navScroll',
      'current-menu-item',
      'menu-item-has-children',
      'open-contato'
    )
  ) : '';
}
//REMOVE TITLE TO POST PROTECT 
add_filter('private_title_format', 'title_format');
add_filter('protected_title_format', 'title_format');
function title_format($content)
{
  return '%s';
}
//  EXCERPT LENGTH
add_filter('excerpt_length', 'custom_excerpt_length', 10);
function custom_excerpt_length($length)
{
  return 32;
}
// EXCERPT MORE LINK
add_filter('excerpt_more', 'new_excerpt_more');
function new_excerpt_more($more)
{
  if (is_admin()) {
    return $more;
  }

  if (is_singular('blog')) {
    return '';
  }

  return ' ... <a title="leia mais" href="' . get_the_permalink() . '"><i class="fas fa-angle-right"></i></a>';
}
// EXCERPT DEFAULT
add_filter('get_the_excerpt', 'display_default_excerpt');
function display_default_excerpt($excerpt)
{
  if (is_singular('blog') && !has_excerpt()) {
    return '';
  }
  return $excerpt;
  //return $excerpt.' <a class="block w-100 pt-1" title="leia mais" href="'.get_the_permalink().'">Saiba mais <i class="fal fa-angle-right"></i></a>';
}
// UPLOAD SVG IMAGE
add_filter('upload_mimes', 'cc_mime_types');
function cc_mime_types($mimes)
{
  // New allowed mime types.
  $mimes['jpg|jpeg'] = 'image/jpeg';
  $mimes['png'] = 'image/png';
  $mimes['svg'] = 'image/svg+xml';
  $mimes['svgz'] = 'image/svg+xml';
  $mimes['webp'] = 'image/webp';
  $mimes['heic'] = 'image/heic';
  $mimes['heif'] = 'image/heif';
  // Optional. Remove a mime type.
  unset($mimes['exe']);
  unset($mimes['json']);
  unset($mimes['xlx']);
  unset($mimes['xlxs']);
  unset($mimes['bat']);
  unset($mimes['mp4']);
  unset($mimes['md']);
  unset($mimes['env']);
  unset($mimes['pdf']);
  unset($mimes['sql']);
  unset($mimes['doc']);
  unset($mimes['docx']);
  unset($mimes['tmp']);
  unset($mimes['txt']);
  unset($mimes['csv']);
  unset($mimes['tsv']);
  unset($mimes['xml']);
  unset($mimes['json']);
  unset($mimes['yaml']);
  unset($mimes['yml']);
  unset($mimes['toml']);
  unset($mimes['ini']);
  unset($mimes['cfg']);
  unset($mimes['conf']);
  unset($mimes['config']);
  unset($mimes['htaccess']);
  unset($mimes['htpasswd']);
  unset($mimes['htgroup']);
  unset($mimes['htusers']);
  unset($mimes['htgroups']);
  unset($mimes['htusers']);
  unset($mimes['htgroups']);
  unset($mimes['zip']);
  unset($mimes['rar']);
  unset($mimes['7z']);
  unset($mimes['tar']);
  unset($mimes['gz']);
  unset($mimes['bz2']);
  unset($mimes['xz']);
  unset($mimes['wmv']);
  unset($mimes['avi']);
  unset($mimes['mov']);
  unset($mimes['mpg']);
  unset($mimes['mpeg']);
  unset($mimes['mp3']);
  unset($mimes['wav']);
  unset($mimes['ogg']);
  unset($mimes['flac']);
  unset($mimes['m4a']);
  unset($mimes['m4v']);
  unset($mimes['m4b']);
  unset($mimes['m4p']);
  unset($mimes['m4v']);
  unset($mimes['m4b']);
  return $mimes;
}

// CORRETOR ORTOGRÁFICO
add_filter('tiny_mce_before_init', 'fb_mce_external_languages');
function fb_mce_external_languages($initArray)
{
  $initArray['spellchecker_languages'] = '+Portuguese=pt, English=en';
  return $initArray;
}
// MUDA O HEADER PARA ENVIO DE EMAILS
add_filter('wp_mail_content_type', 'set_html_content_type');
function set_html_content_type()
{
  return 'text/html';
}
// MUDA O CHARSET PARA ENVIO DE EMAILS
add_filter('wp_mail_charset', 'change_mail_charset');
function change_mail_charset($charset)
{
  return 'UTF-8';
}
// FORÇA LOGIN EM XMLRPC.PHP
add_filter('v_forcelogin_whitelist', 'my_forcelogin_whitelist', 10, 1);
function my_forcelogin_whitelist($whitelist)
{
  $whitelist[] = site_url('/xmlrpc.php');
  return $whitelist;
}
// DESABILITA PINGBACK
function disable_pingback($headers)
{ // Disable x-pingback
  unset($headers['X-Pingback']);
  return $headers;
}
function disable_pingback_url($output, $show = '')
{ // Remove pingback URLs
  if ($show == 'pingback_url')
    $output = '';
  return $output;
}
function disable_xmlrpc_methods($methods)
{ // Disable XML-RPC methods
  unset($methods['pingback.ping']);
  return $methods;
}
// REMOVER ACENTOS NO UPLOAD DE IMAGENS
add_filter('sanitize_file_name', 'sa_sanitize_spanish_chars', 10);
function sa_sanitize_spanish_chars($filename)
{
  return remove_accents($filename);
}