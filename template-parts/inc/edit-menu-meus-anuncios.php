<?php
// Obter status globalizado (considera indeferimento e vendido)
// Reutilizar status_data se já foi calculado, senão calcular
$pos_id = (isset($args['pos_id']) && !empty($args['pos_id']))
  ? $args['pos_id']
  : get_the_ID();

// Reutilizar status_data se já foi calculado, senão calcular
if (
  isset($args['status_data'])
  && !empty($args['status_data'])
  && is_array($args['status_data'])
) {
  $status_data = $args['status_data'];
} else {
  // Fallback: calcular se não foi passado (verifica vendido internamente)
  $status_data = bazar_get_anuncio_status(
    $pos_id,
    null
  );
}

$post_status_global = $status_data['status'];
$isPublished = ($status_data['original_status'] == 'publish');
$isVendido = ($status_data['is_vendido']);
$is_admin_user = current_user_can('manage_options');
$isDestaque = ($status_data['is_destaque'] ?? false);
$post_status = $status_data['original_status'];
?>
<div class="edit-menu" style="padding-bottom: .75rem;">

  <?php if ($isPublished && !$isVendido): ?>
    <!-- Anúncio aprovado e não vendido -->
    <div class="bx">
      <a href="<?php echo bazar_get_edit_url($pos_id); ?>" title="<?php _e('Editar anúncio', 'bazar'); ?>">
        <i class="fas fa-edit black"></i><?php _e('Editar', 'bazar'); ?>
      </a>
    </div>
    <div class="bx">
      <a href="#" class="exclude_post" data-id="<?php echo $pos_id; ?>"
        title="<?php _e('Mover para lixeira', 'bazar'); ?>">
        <i class="fas fa-times black"></i><?php _e('Excluir', 'bazar'); ?>
      </a>
    </div>
    <div class="bx">
      <button type="button" id="bazar-marcar-vendido" class="btn-vendido" data-post-id="<?php echo esc_attr($pos_id); ?>"
        data-silent-vendido="<?php echo $is_admin_user ? '1' : '0'; ?>"
        title="<?php _e('Marcar como vendido', 'bazar'); ?>">
        <i class="fas fa-check-circle"></i><?php _e('Foi Vendido', 'bazar'); ?>
      </button>
    </div>

  <?php elseif ($isPublished && $isVendido): ?>
    <!-- Anúncio vendido (apenas visualização) -->
    <div class="bx">
      <small class="bold <?php echo bazar_get_anuncio_status_class($post_status_global); ?> label">
        <?php echo bazar_get_anuncio_status_icon($post_status_global); ?>
        <?php echo bazar_get_anuncio_status_label($post_status_global); ?>
      </small>
    </div>

  <?php elseif ($post_status == 'pending' || ($post_status == 'draft' && $post_status_global == 'indeferido')): ?>
    <!-- Anúncio aguardando aprovação ou reprovado (em reavaliação) -->
    <div class="bx">
      <a href="<?php echo bazar_get_edit_url($pos_id); ?>" title="<?php _e('Editar anúncio', 'bazar'); ?>">
        <i class="fas fa-edit black"></i><?php _e('Editar', 'bazar'); ?>
      </a>
    </div>

    <div class="bx">
      <a href="<?php the_permalink($pos_id); ?>&preview=true" title="<?php _e('Preview', 'bazar'); ?>" target="_blank">
        <i class="fas fa-eye black"></i>
        <?php _e('Preview', 'bazar'); ?>
      </a>
    </div>

    <div class="bx">
      <small class="bold <?php echo bazar_get_anuncio_status_class($post_status_global); ?>">
        <?php echo bazar_get_anuncio_status_icon($post_status_global); ?>
        <?php echo bazar_get_anuncio_status_label($post_status_global); ?>
      </small>
    </div>

  <?php else: ?>
    <!-- Outros status (draft sem motivos, etc) -->
    <div class="bx">
      <a href="<?php echo bazar_get_edit_url($pos_id); ?>" title="<?php _e('Editar anúncio', 'bazar'); ?>">
        <i class="fas fa-edit black"></i><?php _e('Editar', 'bazar'); ?>
      </a>
    </div>

    <div class="bx">
      <a href="#" class="exclude_post" data-id="<?php echo $pos_id; ?>"
        title="<?php _e('Mover para lixeira', 'bazar'); ?>">
        <i class="fas fa-times black"></i><?php _e('Excluir', 'bazar'); ?>
      </a>
    </div>
  <?php endif; ?>

</div><!-- /edit-menu -->