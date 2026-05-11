<?php
$post = (isset($args['post']) && !empty($args['post']))
  ? $args['post']
  : get_post(get_the_ID());

// Reutilizar status_data se já foi calculado, senão calcular
if (
  isset($args['status_data'])
  && !empty($args['status_data'])
  && is_array($args['status_data'])
) {
  $status_data = $args['status_data'];
} else {
  $status_data = bazar_get_anuncio_status(
    $post->ID,
    $post->post_status
  );
}
$is_vendido = $status_data['is_vendido'];
$is_destaque = $status_data['is_destaque'];
// $is_destaque = false;
?>
<article class="product card adm">

  <figure>
    <a href="<?php echo $post->guid; ?>" title="<?php echo $post->post_title; ?>" target="_blank">
      <img width="320" height="220" alt="<?php echo $post->post_title; ?>"
        title="<?php echo $post->post_title; ?>, Bazar Bikes"
        src="<?php echo get_the_post_thumbnail_url($post->ID, 'm'); ?>" />
    </a>
    <?php
    if ($is_vendido):
      get_template_part('template-parts/inc/vendido-badge', null, array(
        'small' => true
      ));
    elseif ($is_destaque):
      get_template_part('template-parts/inc/destaque-badge', null, array(
        'small' => true
      ));
    endif;
    ?>
  </figure>

  <div class="box-border">

    <?php
    $active = ($status_data['original_status'] === 'publish' && !$is_vendido && $is_destaque);
    get_template_part('template-parts/btn/impulsionar', null, array(
      'post_id' => $post->ID,
      'is_destaque' => $is_destaque,
      'is_vendido' => $is_vendido
    ));
    ?>

    <?php if ($status_data['original_status'] === 'publish'): ?>
      <small>
        Código: <b><?php echo $post->ID; ?></b>
      </small>
    <?php endif; ?>

    <h3 class="product-name">
      <a href="<?php echo $post->guid; ?>" title="<?php echo $post->post_title; ?>" class="black regular"
        target="_blank">
        <?php echo $post->post_title; ?>
      </a>
    </h3>

    <hr />

    <a href="<?php echo $post->guid; ?>" title="<?php echo $post->post_title; ?>" class="black price" target="_blank">
      <small>R$</small>
      <span class="silver-dark">
        <?php echo number_format(get_field('valor'), 2, ',', '.'); ?>
      </span>
    </a>

    <?php
    if ($status_data['original_status'] === 'publish' && !$is_vendido):
      get_template_part('template-parts/btn/screenshot');
    endif;
    ?>

  </div><!-- /box-border -->
</article>