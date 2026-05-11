<?php
get_template_part('template-parts/ads/afiliados/ad-data');
$data = get_query_var('bazar_ad_data');
if (empty($data)) {
  return;
}
$d = $data['img_dimensoes'];
?>
<div class="bazar_box black">
  <span class="badge">Nossa principal escolha</span>
  <amp-img id="my-amp-img" src="<?php echo esc_url($data['img']); ?>" alt="<?php echo esc_attr($data['title']); ?>"
    width="<?php echo esc_attr($d['width']); ?>" height="<?php echo esc_attr($d['height']); ?>"
    layout="intrinsic"></amp-img>
  <div class="nome text-center">
    <?php echo esc_html($data['title']); ?>
    <span class="marca">| <?php echo esc_html($data['marca']); ?></span>
  </div>
  <?php if ($data['descricao'] !== ''): ?>
    <div class="desc text-center"><small><?php echo esc_html($data['descricao']); ?></small></div>
  <?php endif; ?>

  <a href="<?php echo esc_url($data['url_primary']); ?>" class="bt handleClick" target="_blank" rel="noopener"
    title="<?php echo esc_attr($data['title']); ?>" data-nonce="<?php echo esc_attr($data['nonce']); ?>"
    data-post-id="<?php echo (int) $data['id']; ?>" data-meta-key="<?php echo esc_attr($data['meta_primary']); ?>">
    <b><?php echo esc_html($data['label_primary']); ?></b>
    <?php if ($data['meta_primary'] === '_count_click_amazon'): ?>
      <amp-img src="https://XXXXXX/src/imgs/amazon.png" alt="Amazon Brasil" width="143" height="45"
        layout="fixed"></amp-img>
    <?php endif; ?>
  </a>

  <?php if (!empty($data['outros'])): ?>
    <div class="ads-amp-outros text-center">
      <?php foreach ($data['outros'] as $o):
        if (empty($o['url']))
          continue; ?>
        <a href="<?php echo esc_url($o['url']); ?>" class="handleClick" target="_blank" rel="noopener"
          data-nonce="<?php echo esc_attr($data['nonce']); ?>" data-post-id="<?php echo (int) $data['id']; ?>"
          data-meta-key="<?php echo esc_attr($o['meta_key']); ?>"><?php echo esc_html($o['label']); ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php get_template_part('template-parts/ads/msg'); ?>
</div>