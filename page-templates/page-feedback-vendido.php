<?php
/**
 * Template Name: Feedback de Venda
 * 
 * Página para coletar feedback de usuários que venderam anúncios
 * 
 * @package XXXXXX
 */
// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}
// Validar post_id
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
$sucesso = isset($_GET['sucesso']) && $_GET['sucesso'] === '1';

// Acesso pós-envio: sucesso=1 e post_id (token já foi removido após envio)
if (
  $sucesso
  && !empty($post_id)
) {
  $is_submitted = get_posts(array(
    'post_type' => 'feedback',
    'meta_query' => array(array('key' => '_feedback_post_id', 'value' => $post_id, 'compare' => '=')),
    'posts_per_page' => 1,
    'post_status' => 'any'
  ));
  if (empty($is_submitted) || is_wp_error($is_submitted)) {
    wp_redirect(home_url('/'));
    exit;
  }

  $feedback_submitted = (!empty($is_submitted)) ? true : false;
  $whatsapp_url = get_option('bazar_whatsapp_group_url', '') ?? '';

} else {
  // Acesso com token
  if (empty($post_id) || empty($token)) {
    wp_redirect(home_url('/'));
    exit;
  }
  $expected_token = get_post_meta($post_id, '_feedback_token', true);
  if (empty($expected_token) || $expected_token !== $token) {
    wp_redirect(home_url('/'));
    exit;
  }
  $post = bazar_get_product_data($post_id);
  if (!$post || !$post['status_data']['is_vendido']) {
    wp_redirect(home_url('/'));
    exit;
  }
  $existing_feedback = get_posts(array(
    'post_type' => 'feedback',
    'meta_query' => array(array('key' => '_feedback_post_id', 'value' => $post_id, 'compare' => '=')),
    'posts_per_page' => 1,
    'post_status' => 'any'
  ));
  $feedback_submitted = !empty($existing_feedback);
  $user_name = $post['author']['name'];
  $post_title = $post['title'];
  $whatsapp_url = get_option('bazar_whatsapp_group_url', '') ?? '';
}

get_header();
?>

<h1 class="d-none">
  <?php bloginfo('name'); ?> - Feedback de Venda
</h1>

<div class="row align-center page">
  <div class="s-11 m-9 l-10 col">

    <div class="box-content mb-2">
      <div class="row align-center feedback-form form-box">
        <?php
        // Mensagem de sucesso se já enviou feedback
        if ($feedback_submitted):
          ?>
          <div class="col s-12 l-10 text-center pt-1 pb-1">
            <h2 class="h3 mb-1">
              <i class="fa fa-check-circle green d-block pb-1"></i>
              Obrigado pelo seu feedback!
            </h2>
            <p class="mb-0">Sua mensagem foi registrado com sucesso.<br>Agradecemos muito sua contribuição!</p>
          </div>
        <?php else: ?>
          <div class="col s-12 l-11">
            <!-- Formulário de feedback -->
            <h2><?php _e('Compartilhe sua experiência', 'bazar'); ?></h2>

            <div class="alert alert-info">
              <p class="mb-0">
                Olá <strong><?php echo esc_html($user_name); ?></strong>!<br>
                Parabéns pela venda do seu anúncio <strong>"<?php echo esc_html($post_title); ?>"</strong>.
              </p>
            </div>

            <p>Sua opinião é muito importante para melhorarmos nossos serviços. Compartilhe sua experiência conosco:</p>

            <div id="alert"></div>

            <form id="form-feedback-vendido" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">

              <div class="form-group mb-2">
                <label class="d-block mb-1 black">
                  <strong>Como você avalia sua experiência de venda no Bazar Bikes? *</strong>
                </label>
                <div class="rating-stars" id="rating-stars">
                  <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="nota" id="nota-<?php echo $i; ?>" value="<?php echo $i; ?>"
                      class="d-none required">
                    <label for="nota-<?php echo $i; ?>" class="star-label" data-rating="<?php echo $i; ?>">
                      <i class="fa fa-star"></i>
                    </label>
                  <?php endfor; ?>
                </div>
                <small class="d-block mt-1 text-muted">
                  Selecione de 1 a 5 estrelas
                </small>
              </div>

              <!-- Recomendaria? -->
              <div class="form-group mb-2">
                <label class="d-block mb-1 black">
                  <strong>Você recomendaria o Bazar Bikes para outros vendedores? *</strong>
                </label>
                <div class="radio-group">
                  <label class="radio-label">
                    <input type="radio" name="recomendaria" value="sim" class="required">
                    <span>Sim, definitivamente</span>
                  </label>
                  <label class="radio-label">
                    <input type="radio" name="recomendaria" value="talvez" class="required">
                    <span>Talvez</span>
                  </label>
                  <label class="radio-label">
                    <input type="radio" name="recomendaria" value="nao" class="required">
                    <span>Não</span>
                  </label>
                </div>
              </div>

              <!-- O que mais gostou -->
              <div class="form-group mb-2">
                <label for="o_que_gostou" class="d-block mb-1 black">
                  <strong>O que você mais gostou? (opcional)</strong>
                </label>
                <textarea id="o_que_gostou" name="o_que_gostou" rows="3" maxlength="500"
                  placeholder="Compartilhe o que mais te agradou na experiência de vender no Bazar Bikes..."
                  class="not_required"></textarea>
                <small class="d-block text-muted">
                  Máximo 500 caracteres
                </small>
              </div>

              <!-- O que pode melhorar -->
              <div class="form-group mb-2">
                <label for="o_que_melhorar" class="d-block mb-1 black">
                  <strong>O que pode ser melhorado? (opcional)</strong>
                </label>
                <textarea id="o_que_melhorar" name="o_que_melhorar" rows="3" maxlength="500"
                  placeholder="Sugestões de melhorias são sempre bem-vindas..." class="not_required"></textarea>
                <small class="d-block text-muted">
                  Máximo 500 caracteres
                </small>
              </div>

              <!-- Comentários gerais -->
              <div class="form-group mb-2">
                <label for="comentarios" class="d-block mb-1 black">
                  <strong>Comentários adicionais (opcional)</strong>
                </label>
                <textarea id="comentarios" name="comentarios" rows="4" maxlength="1000"
                  placeholder="Alguma observação adicional que gostaria de compartilhar?" class="not_required"></textarea>
                <small class="d-block text-muted">
                  Máximo 1000 caracteres
                </small>
              </div>

              <!-- Campos hidden -->
              <input type="hidden" name="action" value="bazar_feedback_vendido">
              <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
              <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
              <?php wp_nonce_field('bazar_feedback_vendido', 'feedback_nonce'); ?>
              <!-- Botão submit -->
              <div class="form-group mt-2">
                <button type="submit" class="button button-primary button-large">
                  <i class="fa fa-paper-plane pr-1"></i>
                  Enviar Feedback
                </button>
              </div>

            </form>
          </div><!-- /col -->
        <?php endif; ?>
      </div><!-- /row -->
    </div><!-- /box-content -->

    <?php
    if ($feedback_submitted):
      get_template_part('template-parts/cta/cta-whatsapp-newsletter', null, array('whatsapp_url' => $whatsapp_url));
    endif;
    ?>

  </div><!-- /s-11 m-9 l-10 col -->
</div><!-- /row align-center page -->
<script type="text/javascript">
  var __BAZAR_Page = 'feedback-form';
</script>
<?php get_footer(); ?>