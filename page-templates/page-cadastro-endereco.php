<?php
/* Template Name: Cadastro Endereço*/
get_template_part('template-parts/global/validacao-endereco');

if (have_posts()):
  while (have_posts()):
    the_post();

    $cep_url = esc_url('https://buscacepinter.correios.com.br/app/localidade_logradouro/index.php');
    $user_id = get_current_user_id();

    $user = get_user_by('ID', $user_id);
    if ($user):
      $user_data = array(
        // Dados básicos do usuário (da tabela users)
        'cep' => isset($_POST['cep']) ? $_POST['cep'] : get_user_meta($user_id, 'cep', true),
        'bairro' => isset($_POST['bairro']) ? $_POST['bairro'] : get_user_meta($user_id, 'bairro', true),
        'cidade' => isset($_POST['cidade']) ? $_POST['cidade'] : get_user_meta($user_id, 'cidade', true),
        'estado' => isset($_POST['estado']) ? $_POST['estado'] : get_user_meta($user_id, 'estado', true),
        'estado_sigla' => (isset($_POST['estado_sigla'])) ? $_POST['estado_sigla'] : get_user_meta($user_id, 'estado_sigla', true),
      );
      $user_data = apply_filters('bazar_user_data', $user_data);

      $perfil_verificado = function_exists('bazar_perfil_verificado') && bazar_perfil_verificado($user_id);
      $perfil_completo = function_exists('bazar_perfil_completo') && bazar_perfil_completo($user_id);

      // Controle “1 vez”: permite edição de CPF/NOME/DATA apenas quando usuario_update está liberado.
      $usuario_update_raw = get_user_meta($user_id, 'usuario_update', true);


    endif;
    get_header();
    ?>

    <h1 class="d-none">
      <?php bloginfo('name'); ?> - <?php the_title(); ?>
    </h1>

    <div class="row align-center pt-3 pb-2">
      <div class="s-11 m-7 l-5 col">
        <div class="box-content">

          <div class="form-box">

            <h2><?php the_title(); ?></h2>

            <hr />

            <p>Confirme seu endereço.</p>
            <?php // the_content(); ?>

            <div id="alert"></div>

            <form method="post" id="form-cadastro-editar" class="send-form-bazar" name="edit-user"
              action="<?php the_permalink(); ?>">

              <input type="text" name="fake_autofill_address" class="not_required" autocomplete="address-line1"
                style="display: none;" />

              <div class="row">
                <div class="s-12 col">
                  <!-- Mensagem para busca de CEP -->
                  <div id="endereco-msg"></div>

                </div>
                <div class="s-12 col">

                  <a href="<?php echo esc_url($cep_url); ?>" target="_blank"
                    title="<?php _e('Econtre seu CEP', 'bazar'); ?>" class="link-cep">
                    <i class="fa fa-info-circle"></i>
                    <?php _e('Não sei meu CEP ', 'bazar'); ?>
                  </a>
                  <label for="cep">
                    <?php _e('CEP', 'bazar'); ?>
                  </label>
                  <input name="cep" class="mask_cep" type="text" data-cep-context="formulario"
                    placeholder="<?php _e('Digite seu CEP', 'bazar'); ?>" value="<?php echo $user_data['cep'] ?? ''; ?>" />

                </div>
                <div class="s-12 col">

                  <label for="bairro"><?php _e('Bairro', 'bazar'); ?></label>
                  <input name="bairro" type="text" class="format-text" placeholder="Bairro ou Região"
                    value="<?php echo $user_data['bairro'] ?? ''; ?>" />

                </div>
                <div class="s-12 col">

                  <label for="cidade"><?php _e('Cidade', 'bazar'); ?></label>
                  <input name="cidade" class="format-text" readonly type="text" placeholder="Cidade"
                    value="<?php echo $user_data['cidade'] ?? ''; ?>" />

                </div>
                <div class="s-12 col">

                  <label for="estado"><?php _e('Estado', 'bazar'); ?></label>
                  <input name="estado" autocomplete="address-level1" class="format-text" readonly type="text"
                    placeholder="Estado" value="<?php echo $user_data['estado'] ?? ''; ?>" />

                  <input type="hidden" name="estado_sigla" autocomplete="off"
                    value="<?php echo $user_data['estado_sigla'] ?? ''; ?>" />
                </div>

                <div class="s-12 col">
                  <hr />
                </div>

                <div class="s-12 col text-right">
                  <input type="submit" class="bt-enviar bt-check" value="<?php _e('Salvar Endereço', 'bazar'); ?>" />
                </div><!-- /col -->

                <?php $nonce = wp_create_nonce('nonce_edit_endereco'); ?>
                <input type="hidden" name="nonce_edit_endereco" value="<?php echo $nonce; ?>" />
                <?php get_template_part('template-parts/forms/input-redirect'); ?>
                <input name="user_id" type="hidden" value="<?php echo $user_id; ?>" />
                <input name="action" type="hidden" value="bazar_edit_endereco" />

              </div><!-- /row -->
            </form>

          </div><!-- /form-box -->
        </div><!-- /box-content -->
      </div><!-- /col -->
    </div><!-- /row -->

    <script type="text/javascript">
      var __BAZAR_Page = 'cadastro-editar';
    </script>

    <?php get_footer(); endwhile; endif; ?>