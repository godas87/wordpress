<?php
/*
Template Name: Feed Meta CSV
Template Post Type: app
*/
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="feed_meta.csv"');

if (have_posts()):
  while (have_posts()):
    the_post();
    $params = array(
      'posts_per_page' => -1,
      'post_type' => 'post',
      'post_status' => 'publish',
      'orderby' => 'date',
      'order' => 'DESC'
    );

    // Remover itens marcados como vendidos (taxonomia 'status', slug 'vendido')
    $vendido_term_id = function_exists('bazar_get_vendido_term_id') ? (int) bazar_get_vendido_term_id() : 0;
    if ($vendido_term_id > 0) {
      $params['tax_query'] = [
        [
          'taxonomy' => 'status',
          'field' => 'term_id',
          'terms' => [$vendido_term_id],
          'operator' => 'NOT IN',
        ],
      ];
    }

    $utm = '';
    $the_query = new WP_Query($params);

    if ($the_query->have_posts()) {
      $output = fopen('php://output', 'w');

      // Cabeçalhos Meta Catalog (mesma ordem do growman/catalog_products.csv).
      $headers = array(
        'id',
        'title',
        'description',
        'availability',
        'condition',
        'price',
        'link',
        'image_link',
        'brand',
        'google_product_category',
        'fb_product_category',
        'quantity_to_sell_on_facebook',
        'sale_price',
        'sale_price_effective_date',
        'item_group_id',
        'gender',
        'color',
        'size',
        'age_group',
        'material',
        'pattern',
        'shipping',
        'shipping_weight',
        'video[0].url',
        'video[0].tag[0]',
        'gtin',
        'product_tags[0]',
        'product_tags[1]',
        'style[0]',
      );

      fputcsv($output, $headers);

      $posts = $the_query->posts;
      foreach ($posts as $post) {
        $post_id = (int) $post->ID;

        $url = ($utm === '')
          ? get_permalink($post_id)
          : get_permalink($post_id) . '?' . $utm;

        // Conservação (ACF) -> Meta condition (new/used)
        $conservacao = function_exists('get_field') ? get_field('conservacao', $post_id) : null;
        $conservacao_nome = '';
        if (is_array($conservacao) && !empty($conservacao)) {
          $conservacao_nome = (string) ($conservacao[0] ?? '');
        } else {
          $conservacao_nome = is_string($conservacao) ? $conservacao : '';
        }
        $condition = (strcasecmp($conservacao_nome, 'Nova') !== 0) ? 'used' : 'new';

        // Preço -> number + moeda (Meta espera "10.00 BRL")
        $valor_raw = function_exists('get_field') ? get_field('valor', $post_id) : '';
        $valor_str = is_scalar($valor_raw) ? (string) $valor_raw : '';
        $valor_str = preg_replace('/[^0-9,.\-]/', '', $valor_str);
        // Se tiver vírgula mas não tiver ponto, assumimos vírgula decimal.
        if (strpos($valor_str, ',') !== false && strpos($valor_str, '.') === false) {
          $valor_str = str_replace(',', '.', $valor_str);
        }
        $valor_num = (float) $valor_str;
        $price = $valor_num > 0
          ? (number_format($valor_num, 2, '.', '') . ' BRL')
          : '';

        // Imagem principal
        $featured_img_url = get_the_post_thumbnail_url($post_id, 'l');
        $img = $featured_img_url ? esc_url($featured_img_url) : '';

        // Brand: usa o primeiro termo de "marca-modelo"
        $brands = get_the_terms($post_id, 'marca-modelo');
        $brand = '';
        if ($brands && !is_wp_error($brands)) {
          $first_brand = $brands[0] ?? null;
          $brand = ($first_brand && !empty($first_brand->name)) ? (string) $first_brand->name : '';
        }

        // Cidade: usamos como product_tags[0] (sem inventar endereços que não existem)
        $cidades = get_the_terms($post_id, 'cidade');
        $cidade_0 = '';
        if ($cidades && !is_wp_error($cidades)) {
          $cidade_0 = (string) (($cidades[0] ?? null)->name ?? '');
        }

        $google_product_category = 'Sporting Goods > Outdoor Recreation > Cycling > Bicycles';

        // Disponibilidade: seu feed não tem estoque, então assumimos sempre "in stock".
        $availability = 'in stock';

        $data = array(
          // 1) id
          'item_' . $post_id,
          // 2) title
          (string) $post->post_title,
          // 3) description
          strip_tags((string) $post->post_content),
          // 4) availability
          $availability,
          // 5) condition
          $condition,
          // 6) price
          $price,
          // 7) link
          esc_url($url),
          // 8) image_link
          $img,
          // 9) brand
          trim($brand),
          // 10) google_product_category
          $google_product_category,
          // 11) fb_product_category
          $google_product_category,
          // 12) quantity_to_sell_on_facebook
          '1',
          // 13) sale_price
          '',
          // 14) sale_price_effective_date
          '',
          // 15) item_group_id
          '',
          // 16) gender
          '',
          // 17) color
          '',
          // 18) size
          '',
          // 19) age_group
          '',
          // 20) material
          '',
          // 21) pattern
          '',
          // 22) shipping
          '',
          // 23) shipping_weight
          '',
          // 24) video[0].url
          '',
          // 25) video[0].tag[0]
          '',
          // 26) gtin
          '',
          // 27) product_tags[0]
          trim($cidade_0),
          // 28) product_tags[1]
          '',
          // 29) style[0]
          '',
        );

        // Limpeza final (evita quebra de CSV e HTML em colunas)
        foreach ($data as &$value) {
          $value = is_string($value) ? $value : (string) $value;
          $value = str_replace(array("\r\n", "\r", "\n", "\t"), ' ', $value);
          $value = strip_tags($value);
        }
        unset($value);

        fputcsv($output, $data);
      }

      fclose($output);
    }

    wp_reset_postdata();
  endwhile;
endif;
?>