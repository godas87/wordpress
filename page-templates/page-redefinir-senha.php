<?php
/* Template Name: Redefinir Senha */
// Não usar validacao.php aqui: esta página permite acesso por token (link do e-mail) ou usuário logado.
// A validacao global redirecionaria para entrar antes de checarmos o token.

$redefinir_token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
$redefinir_user_id = null;

if (is_user_logged_in()) {
  // Usuário logado (veio da Minha Conta): pode alterar senha normalmente
} elseif ($redefinir_token !== '') {
  // Usuário não logado mas tem token (veio do e-mail / formulário "reenviar senha"): validar token
  $redefinir_user_id = bazar_redefinir_senha_token_user_id($redefinir_token);
  if (!$redefinir_user_id) {
    $url_reenviar = get_permalink(get_page_by_path('reenviar-senha'));
    if (!$url_reenviar) {
      $url_reenviar = home_url('/reenviar-senha/');
    }
    wp_redirect(esc_url(add_query_arg('erro', 'token_invalido', $url_reenviar)));
    exit;
  }
} else {
  // Nem loado nem com token: redirecionar para entrar
  $redirect = get_permalink(get_page_by_path('entrar'));
  if (!$redirect) {
    $redirect = home_url('/entrar/');
  }
  wp_redirect(esc_url($redirect));
  exit;
}

$minha_conta = get_permalink(get_page_by_path('minha-conta'));
if (!$minha_conta) {
  $minha_conta = home_url('/minha-conta/');
}

add_action('wp_head', function () {
  echo '<meta name="robots" content="noindex, nofollow">' . "\n";
}, 1);

if (have_posts()):
  while (have_posts()):
    the_post();
    get_header();
    ?>

    <h1 class="d-none"><?php bloginfo('name'); ?> - <?php the_title(); ?></h1>

    <?php small_content(); ?>

    <div class="form-box">
      <h2><?php the_title(); ?></h2>
      <?php the_content(); ?>

      <p class="silver">
        <?php _e('Mínimo 8 caracteres: letras, números e 1 caractere especial.', 'bazar'); ?>
      </p>

      <div id="alert"></div>

      <form method="post" id="form-alterar-senha" class="send-form-bazar" name="alterar-senha"
        action="<?php the_permalink(); ?>">
        <div class="row">
          <div class="s-12 col">

            <label for="new_senha1"><?php _e('Nova senha', 'bazar'); ?></label>

            <input name="new_senha1" type="password" autocomplete="new-password"
              placeholder="<?php _e('Nova Senha', 'bazar'); ?>:" value="" required />
            <span id="showPpass" class="fa-solid fa-eye bt-show-password" title="<?php _e('Ver senha', 'bazar'); ?>"></span>

          </div>
          <div class="s-12 col">

            <label for="new_senha2"><?php _e('Confirmar nova senha', 'bazar'); ?></label>
            <input name="new_senha2" type="password" autocomplete="new-password"
              placeholder="<?php _e('Confirmar nova senha', 'bazar'); ?>:" value="" required />
            <span id="showPpassConfirm" class="fa-solid fa-eye bt-show-password"
              title="<?php _e('Ver senha', 'bazar'); ?>"></span>

          </div>
          <div class="s-12 col">

            <?php
            $url_args = array(
              'sucess' => true,
              'msg' => 'senha',
            );
            ?>
            <input type="hidden" class="redirect" name="redirect"
              value="<?php echo esc_url_raw(add_query_arg($url_args, $minha_conta)); ?>" />

            <?php if ($redefinir_token !== ''): ?>
              <input type="hidden" name="redefinir_senha_token" value="<?php echo esc_attr($redefinir_token); ?>" />
            <?php endif; ?>

            <?php $nonce = wp_create_nonce('nonce_alterar_senha'); ?>
            <input type="hidden" name="nonce_alterar_senha" value="<?php echo esc_attr($nonce); ?>" />
            <input type="hidden" name="action" value="bazar_alterar_senha" />

          </div>
          <div class="s-12 col">
            <input type="submit" class="bt-enviar bt-check full" value="<?php _e('Alterar senha', 'bazar'); ?>" />
          </div>

          <?php if (is_user_logged_in()): ?>
            <div class="s-12 col pt-1">
              <a href="<?php echo esc_url($minha_conta); ?>" class="lost-password regular"><i
                  class="fa fa-arrow-left pr-1"></i><?php _e('Voltar para Minha Conta', 'bazar'); ?></a>
            </div>
          <?php endif; ?>

        </div>
      </form>
    </div>

    <?php close_content(); ?>

    <script type="text/javascript">
      var __BAZAR_Page = 'alterar-senha';
    </script>

    <?php
    get_footer();
  endwhile;
endif;
