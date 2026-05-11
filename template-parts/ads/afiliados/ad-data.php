<?php
/**
 * Carrega um ad aleatório e preenche query_var 'bazar_ad_data' para uso nos layouts.
 * Deve ser incluído antes de qualquer template de layout (responsive, content, sidebar, amp).
 * Evita queries repetidas: os dados ficam em cache em query_var.
 *
 * @package XXXXXX
 */

$page_id = (is_singular() || is_page()) ? get_the_ID() : null;
$tax_ad = new RelatedTaxQuery($page_id, 'ads', 1);
$query = $tax_ad->getQuery();

if (!$query || !is_object($query) || empty($query->posts)) {
  return;
}

$post = $query->posts[0];
setup_postdata($post);

$id = $post->ID;
$link_amazon = get_post_meta($id, 'link_amazon', true);
$link_ml = get_post_meta($id, 'link_ml', true);
$link_shopee = get_post_meta($id, 'link_shopee', true);
$link_centauro = get_post_meta($id, 'link_centauro', true);
$link_decathlon = get_post_meta($id, 'link_decathlon', true);

$url_primary = '';
$meta_primary = '_count_click_amazon';
$label_primary = 'Compre agora na Amazon';

if (!empty($link_amazon)) {
  $url_primary = esc_url($link_amazon);
  $meta_primary = '_count_click_amazon';
  $label_primary = 'Compre agora na Amazon';
}
if ($url_primary === '' && !empty($link_ml)) {
  $url_primary = esc_url($link_ml);
  $meta_primary = '_count_click_ml';
  $label_primary = 'Compre no Mercado Livre';
}
if ($url_primary === '' && !empty($link_shopee)) {
  $url_primary = esc_url($link_shopee);
  $meta_primary = '_count_click_shopee';
  $label_primary = 'Compre na Shopee';
}
if ($url_primary === '' && !empty($link_centauro)) {
  $url_primary = esc_url($link_centauro);
  $meta_primary = '_count_click_centauro';
  $label_primary = 'Compre na Centauro';
}
if ($url_primary === '' && !empty($link_decathlon)) {
  $url_primary = esc_url($link_decathlon);
  $meta_primary = '_count_click_decathlon';
  $label_primary = 'Compre na Decathlon';
}

if ($url_primary === '') {
  wp_reset_postdata();
  return;
}

$descricao = get_post_meta($id, 'descricao', true);
$marca = get_post_meta($id, 'marca', true);
$target = '_blank'; // Ads sempre abrem em nova aba
$nonce = wp_create_nonce('bazar_count_click');
$thumb_id = get_post_thumbnail_id();
$url_imagem = get_post_meta($id, 'url_imagem', true);
$imgs = $thumb_id ? wp_get_attachment_image_src($thumb_id, 'sidebar') : $url_imagem;
$img = $thumb_id ? $imgs[0] : (is_array($imgs) ? ($imgs[0] ?? '') : (string) $imgs);
$w = $thumb_id && !empty($imgs[1]) ? $imgs[1] : '100%';
$h = $thumb_id && !empty($imgs[2]) ? $imgs[2] : 'auto';

$base_url = get_bloginfo('url');
$marketplaces = array();
if (!empty($link_amazon)) {
  $marketplaces[] = array(
    'link' => esc_url($link_amazon),
    'label' => 'Amazon',
    'meta_key' => '_count_click_amazon',
    'desconto' => get_post_meta($id, 'desconto_amazon', true),
    'icon' => $base_url . '/src/imgs/amazon-icon.svg',
    'icon_alt' => 'Amazon Brasil',
  );
}
if (!empty($link_ml)) {
  $marketplaces[] = array(
    'link' => esc_url($link_ml),
    'label' => 'Mercado Livre',
    'meta_key' => '_count_click_ml',
    'desconto' => get_post_meta($id, 'desconto_ml', true),
    'icon' => $base_url . '/src/imgs/mercado-livre-icon.svg',
    'icon_alt' => 'Mercado Livre',
  );
}
if (!empty($link_shopee)) {
  $marketplaces[] = array(
    'link' => esc_url($link_shopee),
    'label' => 'Shopee',
    'meta_key' => '_count_click_shopee',
    'desconto' => get_post_meta($id, 'desconto_shopee', true),
    'icon' => $base_url . '/src/imgs/shopee-icon.svg',
    'icon_alt' => 'Shopee',
  );
}
if (!empty($link_centauro)) {
  $marketplaces[] = array(
    'link' => esc_url($link_centauro),
    'label' => 'Centauro',
    'meta_key' => '_count_click_centauro',
    'desconto' => get_post_meta($id, 'desconto_centauro', true),
    'icon' => $base_url . '/src/imgs/centauro-icon.svg',
    'icon_alt' => 'Centauro',
  );
}
if (!empty($link_decathlon)) {
  $marketplaces[] = array(
    'link' => esc_url($link_decathlon),
    'label' => 'Decathlon',
    'meta_key' => '_count_click_decathlon',
    'desconto' => get_post_meta($id, 'desconto_decathlon', true),
    'icon' => $base_url . '/src/imgs/decathlon-icon.svg',
    'icon_alt' => 'Decathlon',
  );
}

$outros = array();
if ($meta_primary !== '_count_click_amazon' && !empty($link_amazon)) {
  $outros[] = array('url' => esc_url($link_amazon), 'label' => 'Amazon', 'meta_key' => '_count_click_amazon');
}
if ($meta_primary !== '_count_click_ml' && !empty($link_ml)) {
  $outros[] = array('url' => esc_url($link_ml), 'label' => 'Mercado Livre', 'meta_key' => '_count_click_ml');
}
if ($meta_primary !== '_count_click_shopee' && !empty($link_shopee)) {
  $outros[] = array('url' => esc_url($link_shopee), 'label' => 'Shopee', 'meta_key' => '_count_click_shopee');
}
if ($meta_primary !== '_count_click_centauro' && !empty($link_centauro)) {
  $outros[] = array('url' => esc_url($link_centauro), 'label' => 'Centauro', 'meta_key' => '_count_click_centauro');
}
if ($meta_primary !== '_count_click_decathlon' && !empty($link_decathlon)) {
  $outros[] = array('url' => esc_url($link_decathlon), 'label' => 'Decathlon', 'meta_key' => '_count_click_decathlon');
}

$img_dimensoes = array('width' => '100%', 'height' => 'auto');
if ($img && function_exists('getimagesize')) {
  $size = @getimagesize($img);
  if ($size !== false) {
    $img_dimensoes = array('width' => $size[0], 'height' => $size[1]);
  }
}

$data = array(
  'id' => $id,
  'title' => $post->post_title,
  'descricao' => $descricao,
  'marca' => $marca,
  'img' => $img,
  'w' => $w,
  'h' => $h,
  'img_dimensoes' => $img_dimensoes,
  'url_primary' => $url_primary,
  'meta_primary' => $meta_primary,
  'label_primary' => $label_primary,
  'nonce' => $nonce,
  'target' => $target,
  'marketplaces' => $marketplaces,
  'outros' => $outros,
);

set_query_var('bazar_ad_data', $data);
wp_reset_postdata();
?>