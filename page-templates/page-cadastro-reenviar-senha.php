<?php
/* Template Name: Reenviar Senha*/
if (is_user_logged_in()):
  wp_redirect(esc_url(get_bloginfo('url') . '\/minha-conta\/'));
  exit;
endif;

if (have_posts()):
  while (have_posts()):
    the_post();
    get_header();
    ?>

    <h1 class="d-none">
      <?php bloginfo('name'); ?> - <?php the_title(); ?>
    </h1>

    <?php small_content(); ?>

    <?php
    // $cta_data = array(            
    //     'title' => 'E-mail confirmado!',
    //     'description' => 'E-mail confirmado com sucesso. Agora você pode criar seu primeiro anúncio.',
    // );
    // get_template_part('template-parts/cta/form-send-success');
    //get_template_part('template-parts/cta/msg-box'); 
    ?>

    <div class="form-box">

      <h2>
        <?php the_title(); ?>
      </h2>

      <?php the_content(); ?>

      <?php
      if (isset($_GET['erro']) && $_GET['erro'] === 'token_invalido') {
        $msg_token = __('O link de redefinição de senha expirou ou é inválido. Solicite novamente o e-mail abaixo para receber um novo link e trocar sua senha.', 'bazar');
        echo '<div class="alert alert-info" role="alert">' . esc_html($msg_token) . '</div>';
      }
      ?>

      <form method="post" id="form-reenvio-senha" class="send-form" name="reenvio-senha" action="<?php the_permalink(); ?>">

        <div id="alert"></div>

        <div class="row">
          <div class="s-12 col">

            <label for="user_email">
              <?php _e('E-mail cadastrado', 'bazar'); ?>
            </label>
            <input name="user_email" type="email" required placeholder="<?php _e('E-mail', 'bazar'); ?>:"
              value="<?php if (isset($_POST['user_email'])): echo $_POST['user_email']; elseif (isset($_GET['user_email'])):
                echo $_GET['user_email']; endif; ?>" />

          </div>
          <div class="s-12 col">
            <input type="submit" class="large" value="Entrar" />
          </div>
          <div class="s-12 col text-center" style="padding-top: .75rem;">
            <small><?php _e('Verifique também no lixo eletrônico, ou caixa de SPAN.', 'bazar'); ?></small>
          </div>
        </div>

        <?php get_template_part('template-parts/forms/input-redirect'); ?>
        <?php $nonce = wp_create_nonce('nonce_cadastro_reenviar_senha'); ?>
        <input type="hidden" name="nonce_cadastro_reenviar_senha" value="<?php echo $nonce; ?>" />
        <input name="action" type="hidden" value="bazar_cadastro_reenviar_senha" />

      </form>

    </div><!-- /form-box -->

    <?php close_content(); ?>

    <script type="text/javascript">
      var __BAZAR_Page = 'cadastro-reenviar-senha';
    </script>

    <?php get_footer(); endwhile; endif; ?>