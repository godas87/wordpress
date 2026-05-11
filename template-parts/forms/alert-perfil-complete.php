<?php
$current_user_id = get_current_user_id();

$post_author_id = 0;
if (is_singular()) {
  $post_author_id = (int) get_post_field('post_author', get_the_ID());
}

if (!is_user_logged_in() || $post_author_id === 0 || (int) $current_user_id !== (int) $post_author_id) {
  return;
}

$perfil_completo = (
  is_user_logged_in()
  && function_exists('bazar_perfil_completo')
  && bazar_perfil_completo($current_user_id)
);
$dados_pessoais_ok = (
  is_user_logged_in()
  && function_exists('bazar_perfil_dados_pessoais_completos')
  && bazar_perfil_dados_pessoais_completos($current_user_id)
);
$user_alert = is_user_logged_in() ? get_userdata($current_user_id) : false;
$falta_sobrenome = (
  $user_alert
  && trim((string) ($user_alert->last_name ?? '')) === ''
);
$falta_telefone = (
  trim((string) get_user_meta($current_user_id, 'fone', true)) === ''
);
$endereco_ok = (
  is_user_logged_in()
  && function_exists('bazar_perfil_endereco_completo')
  && bazar_perfil_endereco_completo($current_user_id)
);
$email_ativado = (
  is_user_logged_in()
  && function_exists('bazar_email_ativado')
  && bazar_email_ativado($current_user_id)
);

if ($perfil_completo && $email_ativado) {
  return;
}

$minha_conta_url = home_url('/minha-conta/');
$confirmar_email_url = home_url('/confirmar-email/');
?>
<?php if (!$perfil_completo): ?>
  <?php if (!$dados_pessoais_ok): ?>
    <?php if ($falta_sobrenome): ?>
      <div class="alert alert-info clear" style="margin-bottom: .5rem;">
        <i class="fa fa-user"></i>
        <span>
          <?php
          echo wp_kses(
            sprintf(
              /* translators: %s: URL Minha Conta */
              __('Informe seu <strong>sobrenome</strong> em <a href="%s" title="Minha Conta">Minha Conta</a> — é obrigatório para publicar o anúncio.', 'bazar'),
              esc_url($minha_conta_url)
            ),
            array(
              'strong' => array(),
              'a' => array(
                'href' => array(),
                'title' => array(),
              ),
            )
          );
          ?>
        </span>
      </div>
    <?php endif; ?>
    <?php if ($falta_telefone): ?>
      <div class="alert alert-info clear" style="margin-bottom: .5rem;">
        <i class="fa fa-phone"></i>
        <span>
          <?php
          echo wp_kses(
            sprintf(
              /* translators: %s: URL Minha Conta */
              __('Informe seu <strong>telefone com DDD</strong> em <a href="%s" title="Minha Conta">Minha Conta</a> — é obrigatório para publicar o anúncio.', 'bazar'),
              esc_url($minha_conta_url)
            ),
            array(
              'strong' => array(),
              'a' => array(
                'href' => array(),
                'title' => array(),
              ),
            )
          );
          ?>
        </span>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  <?php if (!$endereco_ok): ?>
    <div class="alert alert-info clear" style="margin-bottom: .5rem;">
      <i class="fa fa-map-marker-alt"></i>
      <span>
        <?php
        echo wp_kses(
          sprintf(
            /* translators: %s: URL Minha Conta */
            __('Preencha corretamente seu <strong>endereço</strong> (CEP, bairro, cidade e estado) em <a href="%s" title="Minha Conta">Minha Conta</a>.', 'bazar'),
            esc_url($minha_conta_url)
          ),
          array(
            'strong' => array(),
            'a' => array(
              'href' => array(),
              'title' => array(),
            ),
          )
        );
        ?>
      </span>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php /*
if (!$email_ativado): 
?>
<div class="alert alert-warning clear" style="margin-bottom: .5rem;">
<i class="fa fa-envelope"></i>
<span>
<?php
echo wp_kses(
sprintf(
/* translators: %s: URL confirmar e-mail * /
__('Confirme seu e-mail para poder entrar novamente após sair da conta. <a href="%s" title="Confirmar e-mail">Abrir confirmação de e-mail</a>.', 'bazar'),
esc_url($confirmar_email_url)
),
array(
'a' => array(
'href'  => array(),
'title' => array(),
),
)
);
?>
</span>
</div>
<?php endif; */ ?>