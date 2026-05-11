<?php
/* 
Template Name: Feed Google 
Template Post Type: app
*/
header('Content-Type: text/xml');
if (have_posts()):
  while (have_posts()):
    the_post();
    $item = '';
    $params = array(
      'posts_per_page' => 300,
      'post_type' => 'post',
      'post_status' => 'publish',
      'orderby' => 'date',
      'order' => 'DESC'
    );
    // Garantir exclusão de itens vendidos (taxonomia status, termo vendido)
    $vendido_term_id = 0;
    if (function_exists('bazar_get_vendido_term_id')) {
      $vendido_term_id = (int) bazar_get_vendido_term_id();
    }
    if ($vendido_term_id <= 0) {
      $vendido_term = get_term_by('slug', 'vendido', 'status');
      $vendido_term_id = ($vendido_term && !is_wp_error($vendido_term)) ? (int) $vendido_term->term_id : 0;
    }
    if ($vendido_term_id > 0) {
      $params['tax_query'] = array(
        array(
          'taxonomy' => 'status',
          'field' => 'term_id',
          'terms' => array($vendido_term_id),
          'operator' => 'NOT IN'
        )
      );
    }
    //$utm = '?utm_source=facebook&utm_medium=organic&utm_campaign=facebook_feed';
    $utm = '';
    $the_query = new WP_Query($params);
    if ($the_query->have_posts()) {
      $item .= '
	<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
	<channel>
		<title>' . get_bloginfo('name') . '</title>
		<link>' . get_bloginfo('url') . '</link>
		<description>' . get_bloginfo('description') . '</description>';
      $posts = $the_query->posts;
      foreach ($posts as $post):
        $url = ($utm == '')
          ? get_permalink($post->ID)
          : get_permalink($post->ID) . '?' . $utm;
        $conservacao = get_field('conservacao', $post->ID);
        $conservacao_primeiro = '';
        if (is_array($conservacao) && isset($conservacao[0])) {
          $conservacao_primeiro = is_object($conservacao[0]) && isset($conservacao[0]->name)
            ? (string) $conservacao[0]->name
            : (string) $conservacao[0];
        } elseif (is_string($conservacao) && $conservacao !== '') {
          $conservacao_primeiro = $conservacao;
        }
        $condition = ($conservacao_primeiro === 'Nova') ? 'new' : 'used';
        $valor_raw = get_field('valor', $post->ID);
        $valor_num = is_numeric($valor_raw) ? (float) $valor_raw : 0;
        $item .= '			
			<item>
				<g:id>' . $post->ID . '</g:id>
				<g:title>' . $post->post_title . '</g:title>
				<g:description>' . strip_tags($post->post_content) . '</g:description>
				<g:link>' . $url . '</g:link>
				<g:availability>in_stock</g:availability>
				<g:availability_date>' . date('Y-12-31\\T23:59:59O') . '</g:availability_date>
				<g:condition>' . $condition . '</g:condition>
				<g:price>' . number_format($valor_num, 2, '.', '') . ' BRL</g:price>				
				<g:canonical_link>' . $url . '</g:canonical_link>
				<g:gender>' . get_field('genero') . '</g:gender>
				<g:google_product_category>Sporting Goods > Outdoor Recreation > Cycling > Bicycles</g:google_product_category>';
        $colors = get_the_terms($post->ID, 'cor');
        if ($colors):
          $item .= '<g:color>';
          foreach ($colors as $key => $color):
            if (empty($color))
              continue;
            $separator = ($key !== count($colors) - 1) ? '/' : '';
            $item .= $color->name . $separator;
          endforeach;
          $item .= '</g:color>';
        endif;
        //brand
        $brands = get_the_terms($post->ID, 'marca-modelo');
        if ($brands):
          $item .= '<g:brand>';
          foreach ($brands as $key => $brand):
            //var_dump( count( $brands ) );			
            if (empty($brand))
              continue;
            $separator = ($key !== (count($brands) - 1)) ? ' ' : '';
            $item .= $brand->name . $separator;
          endforeach;
          $item .= '</g:brand>';
        endif;
        //categorias
        $categs = get_the_terms($post->ID, 'category');
        if ($categs):
          $item .= '<g:product_type>';
          foreach ($categs as $key => $categ):
            $separator = ($key !== count($categs) - 1) ? ' > ' : '';
            $item .= $categ->name . $separator;
          endforeach;
          $item .= '</g:product_type>';
        endif;
        //imagens				
        $featured_img_url = get_the_post_thumbnail_url(get_the_ID(), 'l');
        $item .= '<g:image_link>' . esc_url($featured_img_url) . '</g:image_link>';
        $imgs = get_attached_media('image', $post->ID);
        if ($imgs):
          foreach ($imgs as $img):
            $src = wp_get_attachment_image_src($img->ID, 'l');
            if (is_array($src) && !empty($src[0])) {
              $item .= '<g:additional_image_link>' . esc_url($src[0]) . '</g:additional_image_link>';
            }
          endforeach;
        endif;
        $item .= '</item>';
      endforeach;
      $item .= '</channel></rss>';
      echo $item;
    }
    wp_reset_postdata();
  endwhile;
endif;
?>