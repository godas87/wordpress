<?php global $IS_ADMIN; ?>
<div class="edit-menu">
  <?php
  // Obter status globalizado (considera indeferimento)
  global $product_data;
  $status_data = (isset($product_data['status_data']) && !empty($product_data['status_data']))
    ? $product_data['status_data']
    : bazar_get_anuncio_status(get_the_ID());

  $post_status_global = $status_data['status'];
  $isPublished = ($status_data['original_status'] == 'publish');
  $isVendido = ($status_data['is_vendido']);

  // Verificar se pode destacar usando a função que já faz todas as validações necessárias
  $pode_destacar = false;
  if (function_exists('bazar_can_boost_anuncio')) {
    $result = bazar_can_boost_anuncio($product_data['id']);
    $pode_destacar = $result['can'] ?? false;
  }
  ?>

  <?php if ($isPublished && !$isVendido): ?>
    <div class="bx large">
      <?php get_template_part('template-parts/btn/screenshot'); ?>
    </div>
  <?php else: ?>
    <div class="bx large">
      <small class="bold <?php echo bazar_get_anuncio_status_class($post_status_global); ?> label">
        <?php echo bazar_get_anuncio_status_icon($post_status_global); ?>
        <?php echo bazar_get_anuncio_status_label($post_status_global); ?>
      </small>
    </div>
  <?php endif; ?>

  <?php if (!$isVendido): ?>
    <div class="bx">
      <a href="<?php echo bazar_get_edit_url($product_data['id']); ?>" title="<?php _e('Editar anúncio', 'bazar'); ?>"
        class="black">
        <i class="fas fa-edit"></i>
        <?php _e('Editar', 'bazar'); ?>
      </a>
    </div>
  <?php endif; ?>
  <div class="bx">
    <a href="<?php bloginfo('url') ?>/meus-anuncios/" title="<?php _e('Meus anuncios', 'bazar'); ?>" class="black">
      <i class="fas fa-th"></i><?php _e('Meus anuncios', 'bazar'); ?>
    </a>
  </div>
  <?php
  // Botão para marcar como vendido (apenas se publicado e ainda não estiver vendido)
  if ($isPublished && !$isVendido):
    ?>
    <div class="bx">
      <button type="button" id="bazar-marcar-vendido" class="clear-button black"
        data-post-id="<?php echo esc_attr($product_data['id']); ?>"
        data-silent-vendido="<?php echo $IS_ADMIN ? '1' : '0'; ?>"
        title="<?php _e('Marcar como vendido', 'bazar'); ?>">
        <i class="fas fa-check-circle black"></i><?php _e('Foi vendido', 'bazar'); ?>
      </button>
    </div>
  <?php endif; ?>

</div>