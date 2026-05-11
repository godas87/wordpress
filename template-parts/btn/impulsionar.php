<?php
// Obter dados do post
$post_id = (isset($args['post_id']) && !empty($args['post_id'])
  ? $args['post_id']
  : get_the_ID());
// Verificar se está em destaque
$is_destaque = (isset($args['is_destaque']) && $args['is_destaque'])
  ? $args['is_destaque']
  : false;
$is_vendido = (isset($args['is_vendido']) && $args['is_vendido'])
  ? $args['is_vendido']
  : false;

// Se estiver vendido, verificar token e feedback
$feedback_token = '';
$feedback_url = '';
$feedback_exists = false;
$feedback_admin_url = '';

if ($is_vendido) {
  // Verificar se já existe feedback
  $existing_feedback = get_posts(array(
    'post_type' => 'feedback',
    'meta_query' => array(
      array(
        'key' => '_feedback_post_id',
        'value' => $post_id,
        'compare' => '='
      )
    ),
    'posts_per_page' => 1,
    'post_status' => 'any'
  ));

  $feedback_exists = !empty($existing_feedback);

  if (!$feedback_exists) {
    // Verificar se existe token
    $feedback_token = get_post_meta($post_id, '_feedback_token', true);

    // Se não tem token, gerar um novo (para anúncios vendidos antes da implementação do sistema)
    if (empty($feedback_token)) {
      $feedback_token = wp_generate_password(32, false);
      update_post_meta($post_id, '_feedback_token', $feedback_token);
    }

    // Link para página de feedback com token
    $feedback_url = add_query_arg(array(
      'post_id' => $post_id,
      'token' => $feedback_token
    ), home_url('/feedback-vendido/'));
  }
}
?>
<?php if ($is_vendido): ?>
  <?php if ($feedback_exists): ?>
    <!-- Feedback já foi enviado - apenas exibir mensagem -->
    <button type="button" class="btn-impulsionar active" disabled title="<?php _e('Feedback enviado', 'bazar'); ?>">
      <i class="fa fa-check-circle"></i>
      <?php _e('Feedback enviado', 'bazar'); ?>
    </button>
  <?php elseif (!empty($feedback_url)): ?>
    <!-- Link para enviar feedback (ainda tem token) -->
    <a href="<?php echo esc_url($feedback_url); ?>" class="btn-impulsionar"
      title="<?php _e('Enviar feedback de venda', 'bazar'); ?>">
      <i class="fa fa-star"></i>
      <?php _e('Enviar Feedback', 'bazar'); ?>
    </a>
  <?php else: ?>
    <!-- Sem token e sem feedback (caso raro) -->
    <button type="button" class="btn-impulsionar vendido" disabled title="<?php _e('Anúncio vendido', 'bazar'); ?>">
      <?php _e('Anúncio vendido', 'bazar'); ?>
    </button>
  <?php endif; ?>
<?php
    // caso não esteja vendido
  else: ?>
  <button type="button" id="btn-impulsionar"
    class="btn-impulsionar bt-modal <?php echo ($is_destaque && !$is_vendido) ? 'active' : ''; ?>"
    data-anuncio-id="<?php echo esc_attr($post_id); ?>" <?php echo (!$is_destaque) ? 'data-modal="impulsionar"' : ''; ?>
    title="<?php _e('Destacar anúncio', 'bazar'); ?>">
    <?php if ($is_destaque): ?>
      <i class="fas fa-star reset"></i>
      <?php _e('Anúncio em Destaque', 'bazar'); ?>
    <?php else: ?>
      <i class="fas fa-rocket"></i>
      <?php _e('Impulsionar anúncio', 'bazar'); ?>
    <?php endif; ?>
  </button>
<?php endif; ?>