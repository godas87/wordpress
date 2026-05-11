<?php
// Carregar dados do produto (reutilizar se já vierem via $args)
$product_data = isset($args['product_data']) && !empty($args['product_data'])
  ? $args['product_data']
  : bazar_get_product_data(get_the_ID());

$product_id = $product_data['id'];

// Reutilizar status_data se já vier pré-processado
$status_data = isset($product_data['status_data']) && !empty($product_data['status_data'])
  ? $product_data['status_data']
  : bazar_get_anuncio_status($product_id);

// Usar dados já carregados (evita queries desnecessárias)
$title = $product_data['title'];
$permalink = $product_data['permalink'];
$blogname = $product_data['site_name'];

// Processar imagens: preferir dados do $post_data, fallback apenas se necessário
$imgs = !empty($product_data['images']['gallery'])
  ? $product_data['images']['gallery']
  : get_attached_media('', $product_id);

$schema_imgs = [];
?>
<section class="slide relative pb-1">

  <?php
  if ($status_data['is_vendido'] == true):
    get_template_part('template-parts/inc/vendido-badge');

  elseif ($status_data['is_destaque'] == true):
    get_template_part('template-parts/inc/destaque-badge', null, array(
      'small' => false
    ));

  endif;
  ?>
  <div id="new-slider" class="splide splide_imgs imgs relative" role="group" aria-label="Galeria de Imagens">

    <div class="splide__track">
      <ul class="splide__list">
        <?php foreach ($imgs as $index => $img):
          // Detectar se $img é string (URL) ou objeto (WP_Post)
          $is_string = is_string($img);
          $is_video = false;
          $img_url = '';
          $img_lg_url = '';
          $img_thumb = null;

          if ($is_string) {
            // Se for string (URL do $post_data), usar diretamente
            $img_url = $img;
            $img_lg_url = $img;
            $img_thumb = $img;
          } else {
            // Se for objeto WP_Post, processar uma única vez
            $is_video = isset($img->post_mime_type) && $img->post_mime_type == 'video/mp4';

            if ($is_video) {
              $img_url = isset($img->guid) ? $img->guid : '';
            } else {
              // Processar tamanhos de imagem uma única vez
              $img_lg = wp_get_attachment_image_src($img->ID, 'l');
              $img_thumb = wp_get_attachment_image_src($img->ID, 'thumbnail');
              $img_lg_url = $img_lg ? $img_lg[0] : '';
            }
          }
          ?>
          <li class="splide__slide">
            <?php if ($is_video && !$is_string): ?>
              <div class="responsive-embed">
                <video width="320" height="240" controls="controls" autoplay="autoplay">
                  <source src="<?php echo esc_url($img_url); ?>" type="video/mp4" />
                </video>
              </div>
            <?php else: ?>
              <div class="swipebox" rel="gallery-master" href="<?php echo esc_url($img_lg_url); ?>"
                title="<?php echo esc_attr($title); ?>">
                <img src="<?php echo esc_url($img_lg_url); ?>" alt="Anúncio <?php echo esc_attr($blogname); ?>"
                  title="<?php echo esc_attr($title); ?> | <?php echo esc_attr($blogname); ?>" />
              </div>
            <?php endif; ?>
          </li>
          <?php
          if (isset($img_thumb) && $img_thumb) {
            $thumb_url = is_array($img_thumb) ? $img_thumb[0] : $img_thumb;
            $schema_imgs[$index][0] = $thumb_url;
          } elseif ($is_string && $img_url) {
            $schema_imgs[$index][0] = $img_url;
          } elseif (!$is_string && isset($img->guid)) {
            $schema_imgs[$index][0] = $img->guid;
          }
        endforeach; ?>
      </ul>
    </div>
  </div>
</section>
<?php
global $schema;
if (isset($schema) && is_object($schema) && method_exists($schema, 'schema_Gallery')) {
  $schema->schema_Gallery($schema_imgs);
}
?>