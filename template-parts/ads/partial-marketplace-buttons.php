<?php
/**
 * Botões por marketplace (Amazon, ML, Shopee).
 * Usa cache em query_var 'bazar_ad_data' quando definido (evita novas queries).
 * $data = array
 *   'id' => int,
 *   'nonce' => string,
 *   'marketplaces' => array,
 * );
 * $marketplaces = array(
 *   'link' => string,
 *   'label' => string,
 *   'meta_key' => string,
 *   'desconto' => string,
 *   'icon' => string,
 *   'icon_alt' => string,
 * );
 *
 * @package XXXXXX
 */
$data = get_query_var('bazar_ad_data');
if (
  !empty($data['marketplaces'])
  && !empty($data['nonce'])
):
  echo '<div class="ads-marketplace">';
  $marketplaces = array_values((array) $data['marketplaces']);
  $marketplaces_count = count($marketplaces);

  $id = (int) $data['id'];
  $nonce = $data['nonce'];

  if ($marketplaces_count === 1):
    $m = $marketplaces[0];
    $desconto_html = (isset($m['desconto']) && trim((string) $m['desconto']) !== '')
      ? ' <span class="tag">' . esc_html(trim($m['desconto'])) . '</span>'
      : '';
    ?>
    <a href="<?php echo esc_url($m['link']); ?>"
      class="ads-marketplace-buttons ads-marketplace-buttons--single bt handleClick ads-marketplace-btn" target="_blank"
      rel="noopener" data-nonce="<?php echo esc_attr($nonce); ?>" data-post-id="<?php echo $id; ?>"
      data-meta-key="<?php echo esc_attr($m['meta_key']); ?>" title="<?php echo esc_attr($m['icon_alt']); ?>">
      <small class="bold mb-0 pr-1">Compre agora</small>
      <?php if (!empty($m['icon'])): ?>
        <img src="<?php echo esc_url($m['icon']); ?>" alt="<?php echo esc_attr($m['icon_alt']); ?>" width="30" height="30"
          fill="black" title="<?php echo esc_attr($m['icon_alt']); ?>">
      <?php endif; ?>
      <span class="label"><?php echo esc_html($m['label']); ?></span>
      <?php echo $desconto_html; ?>
    </a>
    <?php
  else:
    echo '<div class="ads-marketplace-buttons">';
    echo '<small class="bold mb-0 pr-1">Compre agora</small>';

    foreach ($marketplaces as $m):
      $desconto_html = (isset($m['desconto']) && trim((string) $m['desconto']) !== '')
        ? ' <span class="tag">' . esc_html(trim($m['desconto'])) . '</span>'
        : '';
      ?>
      <a href="<?php echo esc_url($m['link']); ?>" class="bt handleClick ads-marketplace-btn" target="_blank" rel="noopener"
        data-nonce="<?php echo esc_attr($nonce); ?>" data-post-id="<?php echo $id; ?>"
        data-meta-key="<?php echo esc_attr($m['meta_key']); ?>" title="<?php echo esc_attr($m['icon_alt']); ?>">
        <?php if (!empty($m['icon'])): ?>
          <img src="<?php echo esc_url($m['icon']); ?>" alt="<?php echo esc_attr($m['icon_alt']); ?>" width="30" height="30"
            fill="black" title="<?php echo esc_attr($m['icon_alt']); ?>">
        <?php endif; ?>
        <span class="label"><?php echo esc_html($m['label']); ?></span>
        <?php echo $desconto_html; ?>
      </a>
      <?php
    endforeach;
    echo '</div>';
  endif;
  echo '</div>';
  return;
endif;