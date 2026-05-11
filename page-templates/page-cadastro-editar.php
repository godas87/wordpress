<?php
/* Template Name: Minha Conta*/
get_template_part('template-parts/global/validacao');
//redirect based on USER ROLES
get_template_part('template-parts/global/validacao-perfil');
// Verificar se há mensagem de sucesso (edição de dados ou redefinição de senha)
$edit_success = false;
$success_message = '';
if (isset($_GET['sucess']) && ($_GET['sucess'] === 'true' || $_GET['sucess'] === '1' || $_GET['sucess'] === 1)) {
  $edit_success = true;
  if (isset($_GET['msg']) && $_GET['msg'] === 'senha') {
    $success_message = __('Senha redefinida com sucesso.', 'bazar');
  } else {
    $success_message = __('Dados atualizados com sucesso.', 'bazar');
  }
}
if (have_posts()):
  while (have_posts()):
    the_post();

    $cep_url = esc_url('https://buscacepinter.correios.com.br/app/localidade_logradouro/index.php');

    $url_alterar_senha = get_permalink(get_page_by_path('redefinir-senha'));
    if (!$url_alterar_senha) {
      $url_alterar_senha = home_url('/redefinir-senha/');
    }

    $user_id = get_current_user_id();
    $user = get_user_by('ID', $user_id);
    if ($user):
      $user_data = array(
        // Dados básicos do usuário (da tabela users)
        'first_name' => isset($_POST['first_name']) ? $_POST['first_name'] : $user->first_name,
        'last_name' => isset($_POST['last_name']) ? $_POST['last_name'] : $user->last_name,
        'user_email' => isset($_POST['user_email']) ? $_POST['user_email'] : $user->user_email,
        // Meta fields (da tabela usermeta)
        'cpf' => isset($_POST['cpf']) ? $_POST['cpf'] : get_user_meta($user_id, 'cpf', true),
        'data_nascimento' => isset($_POST['data_nascimento']) ? $_POST['data_nascimento'] : get_user_meta($user_id, 'data_nascimento', true),
        'telefone' => isset($_POST['telefone']) ? $_POST['telefone'] : get_user_meta($user_id, 'fone', true),
        'whatsapp_ativo' => isset($_POST['whatsapp_ativo']) ? (bool) $_POST['whatsapp_ativo'] : (get_user_meta($user_id, 'whatsapp_ativo', true) === 'true'),
        'cep' => isset($_POST['cep']) ? $_POST['cep'] : get_user_meta($user_id, 'cep', true),
        'bairro' => isset($_POST['bairro']) ? $_POST['bairro'] : get_user_meta($user_id, 'bairro', true),
        'cidade' => isset($_POST['cidade']) ? $_POST['cidade'] : get_user_meta($user_id, 'cidade', true),
        'estado' => isset($_POST['estado']) ? $_POST['estado'] : get_user_meta($user_id, 'estado', true),
        'estado_sigla' => (isset($_POST['estado_sigla'])) ? $_POST['estado_sigla'] : get_user_meta($user_id, 'estado_sigla', true),
        // Data de criação e última modificação do usuário
        'user_registered' => (isset($user->user_registered)) ? $user->user_registered : '',
        'last_modified' => get_user_meta($user_id, 'profile_updated', true),
      );
      $user_data = apply_filters('bazar_user_data', $user_data);
      // Normalização defensiva para evitar inconsistência de máscara/formatos legados.
      $user_data['cpf'] = preg_replace('/\D/', '', (string) ($user_data['cpf'] ?? ''));
      $dob_raw = trim((string) ($user_data['data_nascimento'] ?? ''));
      if ($dob_raw !== '') {
        // YYYY-MM-DD -> DD/MM/YYYY
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob_raw, $m)) {
          $user_data['data_nascimento'] = $m[3] . '/' . $m[2] . '/' . $m[1];
        }
        // DD-MM-YYYY -> DD/MM/YYYY
        else if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dob_raw, $m)) {
          $user_data['data_nascimento'] = $m[1] . '/' . $m[2] . '/' . $m[3];
        }
        // YYYYMMDD -> DD/MM/YYYY
        else if (preg_match('/^(\d{8})$/', $dob_raw, $m)) {
          $yyyy = substr($m[1], 0, 4);
          $mm = substr($m[1], 4, 2);
          $dd = substr($m[1], 6, 2);
          $user_data['data_nascimento'] = $dd . '/' . $mm . '/' . $yyyy;
        }
      }
      // Formata a data de criação do usuário
      $creation_date = !empty($user_data['user_registered'])
        ? (new DateTime($user_data['user_registered']))->format('d/m/Y H:i')
        : '';
      // Verificar se o usuário tem perfil completo
      $last_modified_date = !empty($user_data['last_modified'])
        ? (new DateTime($user_data['last_modified']))->format('d/m/Y H:i')
        : '';

      $perfil_verificado = function_exists('bazar_perfil_verificado') && bazar_perfil_verificado($user_id);
      $selos_checklist = function_exists('bazar_get_perfil_selos_checklist')
        ? bazar_get_perfil_selos_checklist($user_id)
        : array(
          'complete' => false,
          'items' => array(),
          'title_complete' => '',
          'title_pending' => '',
        );

      // Defesa contra meta indevida: se o CPF está vazio no formulário, não tratamos como verificado.
      if ($perfil_verificado) {
        $cpf_u = trim((string) ($user_data['cpf'] ?? ''));
        // Alguns casos podem deixar CPF "invisível" na UI por máscara, mas ainda existirem caracteres no meta.
        // Só consideramos verificado se houver 11 dígitos.
        $cpf_u_digits = preg_replace('/\D/', '', (string) $cpf_u);
        if ($cpf_u_digits === '' || strlen((string) $cpf_u_digits) !== 11) {
          $perfil_verificado = false;
        }
      }
      /** Borda amarela só no bloco de endereço quando CEP/cidade/estado/bairro incompletos */
      $cep_u = trim((string) ($user_data['cep'] ?? ''));
      $bairro_u = trim((string) ($user_data['bairro'] ?? ''));
      $cidade_u = trim((string) ($user_data['cidade'] ?? ''));
      $estado_u = trim((string) ($user_data['estado'] ?? ''));
      $sigla_u = trim((string) ($user_data['estado_sigla'] ?? ''));
      $endereco_completo = ($cep_u !== '' && $bairro_u !== '' && $cidade_u !== '' && $estado_u !== '' && $sigla_u !== '');
      $address_pending = !$endereco_completo ? 'form-input-pending' : '';

      // Regras do formulário (edição):
      // - Quando NÃO verificado, CEP e data de nascimento ficam opcionais.
      // - Quando verificado, apenas data de nascimento fica travada (readonly).
      $endereco_not_required_class = !$perfil_verificado ? 'not_required' : '';
      $dob_not_required_class = !$perfil_verificado ? 'not_required' : '';
      // CEP nunca deve ser travado.
      $cep_travado = false;
      // DOB fica travada apenas após verificação de CPF (API).
      $dob_travado = (bool) $perfil_verificado;

      // Após verificação do CPF (API), nome/CPF/DOB devem ficar travados.
      $campos_cpf_nome_dob_travados = (bool) $perfil_verificado;

    else:
      $perfil_verificado = false;
      $selos_checklist = function_exists('bazar_get_perfil_selos_checklist')
        ? bazar_get_perfil_selos_checklist(0)
        : array(
          'complete' => false,
          'items' => array(),
          'title_complete' => '',
          'title_pending' => '',
        );
      $address_pending = '';
      $endereco_not_required_class = '';
      $dob_not_required_class = '';
      $cep_travado = false;
      $dob_travado = false;
      $campos_cpf_nome_dob_travados = false;
    endif;
    get_header();
    ?>

    <h1 class="d-none">
      <?php bloginfo('name'); ?> - <?php the_title(); ?>
    </h1>

    <div class="row align-center pt-3 pb-2">
      <div class="s-11 l-9 col">
        <div class="box-content">

          <div class="form-box">

            <?php
            $selos_complete = !empty($selos_checklist['complete']);
            $selos_alert_class = $selos_complete ? 'alert-success' : 'alert-warning';
            $selos_mod_class = $selos_complete
              ? 'bazar-perfil-selos-alert--complete'
              : 'bazar-perfil-selos-alert--pending';
            ?>
            <div
              class="alert <?php echo esc_attr($selos_alert_class); ?> bazar-perfil-selos-alert <?php echo esc_attr($selos_mod_class); ?> clear"
              role="status">

              <i class="fa fa-shield-alt bazar-perfil-selos-alert__icon" aria-hidden="true"></i>

              <div class="bazar-perfil-selos-alert__head">

                <span class="bazar-perfil-selos-alert__title">
                  <?php echo esc_html($selos_complete ? $selos_checklist['title_complete'] : $selos_checklist['title_pending']); ?>
                </span>

                <?php if (!empty($selos_checklist['items']) && !$selos_complete): ?>
                  <ul class="bazar-perfil-selos-alert__list">
                    <?php foreach ($selos_checklist['items'] as $selo_item): ?>
                      <?php
                      $selo_ok = !empty($selo_item['ok']);
                      $item_mod = ($selo_ok)
                        ? 'bazar-perfil-selos-alert__item--ok'
                        : 'bazar-perfil-selos-alert__item--pending';
                      $icon_class = ($selo_ok)
                        ? 'fa-check-circle'
                        : 'fa-times-circle';
                      ?>
                      <li class="bazar-perfil-selos-alert__item <?php echo esc_attr($item_mod); ?>">
                        <i class="fa fa-fw <?php echo esc_attr($icon_class); ?> bazar-perfil-selos-alert__item-icon"
                          aria-hidden="true"></i>
                        <span class="bazar-perfil-selos-alert__item-label"><?php echo esc_html($selo_item['label']); ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            </div>

            <div class="row align-center">

              <div class="s-12 m-6 col">
                <h2><?php the_title(); ?></h2>
              </div>

              <div class="s-shrink col">
                <label class="silver h6" style="margin-bottom: .25rem;">Criação:</label>
                <i class="fa fa-calendar-plus silver"></i>
                <small><?php echo esc_html($creation_date); ?></small>
              </div>

              <?php
              if (
                !empty($last_modified_date)
                && $last_modified_date !== $creation_date
              ): ?>
                <div class="s-shrink col">
                  <label class="silver h6" style="margin-bottom: .25rem;">Alteração:</label>
                  <i class="fa fa-calendar-check silver"></i>
                  <small><?php echo esc_html($last_modified_date); ?></small>
                </div>
              <?php endif; ?>

            </div>
            <hr />

            <?php // the_content(); ?>

            <?php
            // Alerta de página: sucesso, aviso "completar" ou container para AJAX            
            if (
              $edit_success
              && $success_message !== ''
            ): ?>
              <div id="alert-cadastro-success" class="alert alert-success alert-url-temp clear" role="alert">
                <?php echo esc_html($success_message); ?>
              </div>
            <?php endif; ?>

            <div id="alert"></div>

            <form method="post" id="form-cadastro-editar" class="send-form-bazar" name="edit-user"
              action="<?php the_permalink(); ?>">

              <!-- Honeypot fields to absorb browser autofill -->
              <input type="text" name="fake_autofill_username" class="not_required" autocomplete="username"
                style="display: none;" />
              <input type="text" name="fake_autofill_address" class="not_required" autocomplete="address-line1"
                style="display: none;" />

              <div class="row">
                <div class="s-12 m-6 col">

                  <label for="first_name"><?php _e('Nome', 'bazar'); ?></label>
                  <input class="format-text <?php echo esc_attr($campos_cpf_nome_dob_travados ? 'campo-travado' : ''); ?>"
                    name="first_name" type="text" placeholder="<?php _e('Nome', 'bazar'); ?>:"
                    value="<?php echo esc_attr($user_data['first_name'] ?? ''); ?>" <?php echo $campos_cpf_nome_dob_travados ? ' readonly="readonly"' : ''; ?> />

                </div><!-- /col -->
                <div class="s-12 m-6 col">

                  <label for="last_name"><?php _e('Sobrenome', 'bazar'); ?></label>
                  <input class="format-text" name="last_name" type="text" placeholder="<?php _e('Sobrenome', 'bazar'); ?>:"
                    value="<?php echo esc_attr($user_data['last_name'] ?? ''); ?>" />

                </div><!-- /col -->
                <div class="s-12 m-4 col">

                  <label for="user_email"><?php _e('E-mail', 'bazar'); ?></label>
                  <input name="user_email" type="text" class="campo-travado"
                    placeholder="<?php _e('seuemail@email.com', 'bazar'); ?>"
                    value="<?php echo esc_attr($user_data['user_email'] ?? ''); ?>" readonly="readonly" />

                </div> <!-- /col -->

                <div class="s-12 m-6 col">

                  <label for="cpf"><?php _e('CPF', 'bazar'); ?></label>
                  <input name="cpf" type="text" placeholder="<?php _e('000.000.000-00', 'bazar'); ?>"
                    class="mask_cpf not_required <?php echo esc_attr($campos_cpf_nome_dob_travados ? 'campo-travado' : ''); ?>"
                    value="<?php echo esc_attr($user_data['cpf'] ?? ''); ?>" <?php echo $campos_cpf_nome_dob_travados ? ' readonly="readonly"' : ''; ?> />

                </div><!-- /col -->
                <div class="s-12 m-6 col">

                  <label for="data_nascimento"><?php _e('Data de Nascimento', 'bazar'); ?></label>
                  <input name="data_nascimento" type="text" placeholder="<?php _e('dd/mm/aaaa', 'bazar'); ?>"
                    class="mask_date <?php echo esc_attr(trim($dob_not_required_class . ' ' . ($dob_travado ? 'campo-travado' : ''))); ?>"
                    data-toggle="datepicker" value="<?php echo esc_attr($user_data['data_nascimento'] ?? ''); ?>" <?php echo $dob_travado ? ' readonly="readonly"' : ''; ?> />

                </div><!-- /col -->

                <div class="s-12 m-5 col">
                  <label for="telefone">
                    <?php _e('Telefone', 'bazar'); ?>
                  </label>
                  <input name="telefone" type="text" placeholder="<?php _e('(00)0.0000-0000', 'bazar'); ?>"
                    class="mask_phone" value="<?php echo $user_data['telefone'] ?? ''; ?>" />
                </div><!-- /col -->
                <div class="s-12 m-3 col">
                  <label class="whatsapp_ativo">
                    <input type="checkbox" name="whatsapp_ativo" value="1" <?php checked(!empty($user_data['whatsapp_ativo'])); ?> />
                    <i class="fab fa-whatsapp"></i>
                    <?php _e('Possui WhatsApp?', 'bazar'); ?>
                  </label>
                </div><!-- /col -->

                <div class="s-12 col pt-1">
                  <h3>
                    <?php _e('Endereço', 'bazar'); ?>
                  </h3>
                  <!-- Mensagem para busca de CEP -->
                  <div id="endereco-msg"></div>

                </div>
                <div class="s-12 m-6 col">

                  <a href="<?php echo esc_url($cep_url); ?>" target="_blank"
                    title="<?php _e('Econtre seu CEP', 'bazar'); ?>" class="link-cep">
                    <i class="fa fa-info-circle"></i>
                    <?php _e('Não sei meu CEP ', 'bazar'); ?>
                  </a>
                  <label for="cep">
                    <?php _e('CEP', 'bazar'); ?>
                  </label>
                  <input name="cep" class="mask_cep <?php echo esc_attr(trim($address_pending)); ?>" type="text"
                    data-cep-context="formulario" placeholder="<?php _e('Digite seu CEP', 'bazar'); ?>"
                    value="<?php echo $user_data['cep'] ?? ''; ?>" <?php echo $cep_travado ? ' readonly="readonly"' : ''; ?> />

                </div>
                <div class="s-12 m-6 col">

                  <label for="bairro"><?php _e('Bairro', 'bazar'); ?></label>
                  <input name="bairro" type="text"
                    class="format-text <?php echo esc_attr(trim($endereco_not_required_class . ' ' . $address_pending)); ?>"
                    placeholder="Bairro ou Região" value="<?php echo $user_data['bairro'] ?? ''; ?>" />

                </div>
                <div class="s-12 m-6 col">

                  <label for="cidade"><?php _e('Cidade', 'bazar'); ?></label>
                  <input name="cidade"
                    class="format-text <?php echo esc_attr(trim($endereco_not_required_class . ' ' . $address_pending)); ?>"
                    readonly type="text" placeholder="Cidade" value="<?php echo $user_data['cidade'] ?? ''; ?>" />

                </div>
                <div class="s-12 m-6 col">

                  <label for="estado"><?php _e('Estado', 'bazar'); ?></label>
                  <input name="estado" autocomplete="address-level1"
                    class="format-text <?php echo esc_attr(trim($endereco_not_required_class . ' ' . $address_pending)); ?>"
                    readonly type="text" placeholder="Estado" value="<?php echo $user_data['estado'] ?? ''; ?>" />

                  <input type="hidden" name="estado_sigla" autocomplete="off"
                    class="<?php echo esc_attr(trim($endereco_not_required_class . ' ' . $address_pending)); ?>"
                    value="<?php echo $user_data['estado_sigla'] ?? ''; ?>" />
                </div>

                <div class="s-12 col pt-1">
                  <a href="<?php echo esc_url($url_alterar_senha); ?>" title="<?php _e('Redefinir Senha', 'bazar'); ?>"
                    class="button clear small">
                    <i class="fa fa-lock pr-1"></i><?php _e('Redefinir Senha', 'bazar'); ?>
                  </a>

                </div>

                <div class="s-12 col">
                  <hr />
                </div>

                <div class="s-12 m-7 col">

                  <div class="row align-middle termos">
                    <div class="col shrink">
                      <input type="checkbox" readonly checked name="termos" id="termos" value="true">
                    </div>
                    <div class="col reset">
                      <?php get_template_part('template-parts/forms/termos'); ?>
                    </div>
                  </div>

                </div><!-- /col -->

                <div class="s-12 m-5 col text-right">
                  <input type="submit" class="bt-enviar bt-check" value="<?php _e('Salvar alterações', 'bazar'); ?>" />
                </div><!-- /col -->

                <?php $nonce = wp_create_nonce('nonce_edit_user'); ?>
                <input type="hidden" name="nonce_edit_user" value="<?php echo $nonce; ?>" />
                <?php get_template_part('template-parts/forms/input-redirect'); ?>
                <input name="user_id" type="hidden" value="<?php echo $user_id; ?>" />
                <input name="action" type="hidden" value="bazar_edit_user" />

              </div><!-- /row -->
            </form>

          </div><!-- /form-box -->
        </div><!-- /box-content -->
      </div><!-- /col -->
    </div><!-- /row -->

    <div class="row align-center pb-3">
      <div class="s-11 l-9 col">
        <div class="box-content">
          <p class="mb-0" style="font-size: .7rem; line-height: 1.1;">
            Ao cancelar sua conta, todos os seus anúncios serão movidos para a lixeira e as imagens serão removidas
            (exceto
            a imagem de capa). Esta ação não pode ser desfeita. Seus dados serão mantidos para fins de auditoria.
            <button type="button" class="button clean bt-modal" data-modal="cancelar-conta">Cancelar Minha
              Conta</button>
          </p>
        </div><!-- /box-content -->
      </div><!-- /col -->
    </div><!-- /row -->

    <script type="text/javascript">
      var __BAZAR_Page = 'cadastro-editar';
    </script>

    <?php get_template_part('template-parts/modal/confirm'); ?>
    <?php get_template_part('template-parts/modal/cancelar-conta'); ?>

    <?php get_footer(); endwhile; endif; ?>