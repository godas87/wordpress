<?php
get_template_part('template-parts/ads/afiliados/ad-data');
$data = get_query_var('bazar_ad_data');
if (empty($data)) {
  return;
}
$css_class = isset($args['css_class']) ? $args['css_class'] : '';
?>
<div class="bazar_amz_box <?php echo esc_attr($css_class); ?>">
  <span class="badge">Nossa principal escolha</span>
  <div class="row align-center align-middle">
    <div class="s-12 m-6 l-7 col text-center m-text-left s-order-2 m-order-1">

      <div class="nome h3 bold">
        <?php echo esc_html($data['title']); ?> | <span class="silver"><?php echo esc_html($data['marca']); ?></span>
      </div>

      <?php if ($data['descricao'] !== ''): ?>
        <p class="descricao regular"><?php echo esc_html($data['descricao']); ?></p>
      <?php endif; ?>

      <?php get_template_part('template-parts/ads/partial-marketplace-buttons'); ?>

    </div>
    <div class="s-12 m-6 l-5 col text-center s-order-1 m-order-2">

      <figure>
        <img src="<?php echo esc_url($data['img']); ?>" alt="<?php echo esc_attr($data['title']); ?>"
          title="<?php echo esc_attr($data['title']); ?>" width="<?php echo esc_attr($data['w']); ?>"
          height="<?php echo esc_attr($data['h']); ?>" />
      </figure>

    </div>
    <div class="s-12 col s-order-3">
      <?php get_template_part('template-parts/ads/msg'); ?>
    </div>
  </div>
</div>