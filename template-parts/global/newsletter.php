<?php
/**
 * Formulário de Newsletter
 * Layout moderno e simples para o footer
 */

// Verificar mensagens de retorno
$newsletter_message = '';
$newsletter_class = '';

if (isset($_GET['newsletter'])) {
  switch ($_GET['newsletter']) {
    case 'success':
      $newsletter_message = 'Email cadastrado com sucesso!';
      $newsletter_class = 'newsletter-success';
      break;
    case 'exists':
      $newsletter_message = 'Este email já está cadastrado.';
      $newsletter_class = 'newsletter-warning';
      break;
    case 'invalid':
      $newsletter_message = 'Por favor, insira um email válido.';
      $newsletter_class = 'newsletter-error';
      break;
    case 'error':
      $newsletter_message = 'Ocorreu um erro. Tente novamente.';
      $newsletter_class = 'newsletter-error';
      break;
  }
}
?>

<section id="newsletter" class="newsletter-section text-white">
  <div class="row align-center">
    <div class="s-11 m-10 l-8 col">

      <?php if (!empty($newsletter_message)): ?>
        <div class="newsletter-message <?php echo esc_attr($newsletter_class); ?> text-center">
          <?php echo esc_html($newsletter_message); ?>
        </div>
      <?php endif; ?>

      <h3 class="newsletter-title">
        <i class="fas fa-envelope"></i>
        Newsletter Bazar Bikes
      </h3>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="newsletter-form"
        id="newsletter-form">
        <div class="newsletter-input-group">
          <input type="email" name="newsletter_email" id="newsletter_email" placeholder="Cadastre seu email" required
            class="newsletter-input" value="" />
          <button type="submit" class="button newsletter-button" title="Cadastrar no newsletter">
            <!-- <span class="newsletter-button-text">Cadastrar</span> -->
            <i class="fas fa-paper-plane newsletter-button-icon"></i>
          </button>
        </div>
        <?php wp_nonce_field('newsletter_subscribe', 'newsletter_nonce'); ?>
        <input type="hidden" name="action" value="newsletter_subscribe">
      </form>

      <small class="newsletter-privacy">
        <i class="fas fa-lock"></i>
        Seus dados estão seguros. Não enviamos spam.
      </small>

    </div>
  </div>
</section>