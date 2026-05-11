<?php
/**
 * Service de publicação de anúncios.
 *
 * Só publica (pending ou draft → publish) quando:
 * - Anúncio aprovado pelo ADM (meta bazar_anuncio_aprovado_adm),
 * - Dados mínimos no perfil (bazar_perfil_completo: nome, telefone, endereço completo).
 * Inclui `draft` para reavaliação após indeferimento (fluxo em `anuncio-aprovar-reprovar.php`).
 *
 * E-mail confirmado não bloqueia mais a publicação; o usuário segue com alerta e bloqueio no próximo login
 * até confirmar (ver login.php e header).
 *
 * Disparado em: aprovação pelo ADM, atualização de perfil (Minha Conta), confirmação de e-mail.
 *
 * Destaque (Stripe / `anuncio-destaque-service.php`): a publicação automática (pending → publish) é independente
 * do pagamento de impulsionamento; após o perfil/publicação, `bazar_destaque_service_try_apply_pending_for_user()`
 * libera o termo quando o pagamento já estava pago e faltava só verificação de CPF. Textos sobre e-mail não
 * confirmado nos e-mails transacionais reutilizam `bazar_publication_service_html_paragraph_email_opcional()` quando possível.
 */

if (!defined('ABSPATH')) {
  exit;
}

/** Meta do post: aprovado pelo administrador (1 = sim) */
define('BAZAR_META_APROVADO_ADM', 'bazar_anuncio_aprovado_adm');

/**
 * Tenta publicar todos os anúncios do usuário que estão aprovados e cumprem perfil completo.
 *
 * @param int $user_id ID do autor
 * @return int[] IDs dos posts que foram publicados
 */
function bazar_publication_service_try_publish_for_user($user_id)
{
  $published = array();

  if (!$user_id || !function_exists('bazar_perfil_completo')) {
    return $published;
  }

  if (!bazar_perfil_completo($user_id)) {
    return $published;
  }

  $posts = get_posts(array(
    'author' => $user_id,
    'post_type' => 'post',
    'post_status' => array('pending', 'draft'),
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_query' => array(
      array(
        'key' => BAZAR_META_APROVADO_ADM,
        'value' => '1',
        'compare' => '=',
      ),
    ),
  ));

  foreach ($posts as $post_id) {
    $result = wp_update_post(array(
      'ID' => $post_id,
      'post_status' => 'publish',
    ));
    if ($result && !is_wp_error($result)) {
      $published[] = (int) $post_id;
    }
  }

  return $published;
}

/**
 * Pendências de perfil que impedem a publicação automática após aprovação do ADM.
 * E-mail confirmado não entra aqui (não bloqueia publicação).
 *
 * @param int $user_id ID do autor
 * @return array{ need_endereco: bool, need_dados_pessoais: bool, need_email_confirmacao: bool }
 */
function bazar_publication_service_get_profile_pendencias_publicacao($user_id)
{
  $author_id = (int) $user_id;
  $out = array(
    'need_endereco' => false,
    'need_dados_pessoais' => false,
    'need_email_confirmacao' => false,
  );
  if ($author_id < 1) {
    return $out;
  }
  if (function_exists('bazar_perfil_endereco_completo')) {
    $out['need_endereco'] = !bazar_perfil_endereco_completo($author_id);
  }
  if (function_exists('bazar_perfil_dados_pessoais_completos')) {
    $out['need_dados_pessoais'] = !bazar_perfil_dados_pessoais_completos($author_id);
  }
  if (function_exists('bazar_usuario_email_confirmado_meta')) {
    $out['need_email_confirmacao'] = !bazar_usuario_email_confirmado_meta($author_id);
  }
  return $out;
}

/**
 * Lista HTML (&lt;ul&gt;…&lt;/ul&gt;) com o que falta para publicação (endereço / nome e telefone).
 *
 * @param int                  $author_id
 * @param array<string, bool>|null $pendencias Opcional; evita recalcular se já tiver
 * @return string HTML vazio se nada pendente para publicação
 */
function bazar_publication_service_html_ul_pendencias_publicacao($author_id, $pendencias = null)
{
  $author_id = (int) $author_id;
  if ($pendencias === null) {
    $pendencias = bazar_publication_service_get_profile_pendencias_publicacao($author_id);
  }
  if (empty($pendencias['need_endereco']) && empty($pendencias['need_dados_pessoais'])) {
    return '';
  }
  $minha_conta_url = home_url('/minha-conta/');
  $html = '<ul style="margin: 0 0 12px 0; padding-left: 20px; line-height: 1.6;">';
  if (!empty($pendencias['need_endereco'])) {
    $html .= '<li><strong>Endereço:</strong> preencha CEP, bairro, cidade e estado em <a href="' . esc_url($minha_conta_url) . '">Minha Conta</a>.</li>';
  }
  if (!empty($pendencias['need_dados_pessoais'])) {
    $html .= '<li><strong>Nome e contato:</strong> informe nome, sobrenome e telefone com DDD em <a href="' . esc_url($minha_conta_url) . '">Minha Conta</a>.</li>';
  }
  $html .= '</ul>';
  return $html;
}

/**
 * Parágrafo sobre confirmação de e-mail (não bloqueia publicação).
 *
 * @param int $author_id
 * @return string HTML vazio se e-mail já confirmado
 */
function bazar_publication_service_html_paragraph_email_opcional($author_id)
{
  $p = bazar_publication_service_get_profile_pendencias_publicacao($author_id);
  if (empty($p['need_email_confirmacao'])) {
    return '';
  }
  $confirmar_email_url = home_url('/confirmar-email/');
  return '<p style="margin: 12px 0 0 0;"><strong>E-mail:</strong> confirme seu endereço quando puder — <a href="' . esc_url($confirmar_email_url) . '">abrir confirmação</a>. Isso não impede a publicação do anúncio, mas é necessário para entrar novamente no site após sair da conta.</p>';
}

/**
 * Botões padrão Minha Conta + confirmar e-mail (se aplicável).
 *
 * @param int $author_id
 * @return array<int, array{label: string, url: string, text: string}>
 */
function bazar_publication_service_mail_buttons_perfil($author_id)
{
  $p = bazar_publication_service_get_profile_pendencias_publicacao($author_id);
  $minha_conta_url = home_url('/minha-conta/');
  $buttons = array(
    array(
      'label' => 'Minha Conta',
      'url' => $minha_conta_url,
      'text' => 'Ir para Minha Conta',
    ),
  );
  if (!empty($p['need_email_confirmacao'])) {
    $buttons[] = array(
      'label' => 'Confirmar e-mail',
      'url' => home_url('/confirmar-email/'),
      'text' => 'Confirmar e-mail',
    );
  }
  return $buttons;
}

/** Contexto: e-mail logo após envio do anúncio (aguardando aprovação). */
define('BAZAR_PUBLICATION_MAIL_CTX_SUBMITTED', 'submitted_pending_review');

/** Contexto: anúncio já aprovado pelo ADM mas perfil incompleto impede publicar. */
define('BAZAR_PUBLICATION_MAIL_CTX_APPROVED_PROFILE', 'approved_pending_profile');

/**
 * Bloco HTML único sobre cadastro / publicação automática (lista de pendências, e-mail opcional, fechos).
 * Usado no e-mail de envio de anúncio e no e-mail “aprovado mas falta cadastro”.
 *
 * @param int    $author_id
 * @param string $context   BAZAR_PUBLICATION_MAIL_CTX_SUBMITTED | BAZAR_PUBLICATION_MAIL_CTX_APPROVED_PROFILE
 * @return string HTML (vazio para APPROVED_PROFILE se não houver pendência de endereço/dados pessoais)
 */
function bazar_publication_service_mail_body_profile_publish_block($author_id, $context)
{
  $author_id = (int) $author_id;
  $pendencias = bazar_publication_service_get_profile_pendencias_publicacao($author_id);
  $has_pub_block = !empty($pendencias['need_endereco']) || !empty($pendencias['need_dados_pessoais']);
  $minha_conta_url = home_url('/minha-conta/');
  $html = '';

  if ($context === BAZAR_PUBLICATION_MAIL_CTX_SUBMITTED) {
    if ($has_pub_block) {
      $html .= '<p>Para que ele possa ser <strong>publicado automaticamente assim que for aprovado</strong>, complete o que faltar em <a href="' . esc_url($minha_conta_url) . '">Minha Conta</a>:</p>';
      $html .= bazar_publication_service_html_ul_pendencias_publicacao($author_id, $pendencias);
      $html .= '<p>Quando a equipe aprovar e não houver pendências de cadastro, o anúncio entra no ar e você receberá outro e-mail.</p>';
    } else {
      $html .= '<p>Seu cadastro já permite que o anúncio seja <strong>publicado automaticamente</strong> assim que a equipe aprovar. Você receberá outro e-mail quando estiver no ar.</p>';
    }
    $html .= bazar_publication_service_html_paragraph_email_opcional($author_id);
    return $html;
  }

  if ($context === BAZAR_PUBLICATION_MAIL_CTX_APPROVED_PROFILE) {
    if (!$has_pub_block) {
      return '';
    }
    $html .= '<p>Seu anúncio no <strong>Bazar Bikes</strong> foi <strong>aprovado</strong> pela nossa equipe. Ele ainda não entra no ar porque falta concluir parte do seu cadastro:</p>';
    $html .= bazar_publication_service_html_ul_pendencias_publicacao($author_id, $pendencias);
    $html .= bazar_publication_service_html_paragraph_email_opcional($author_id);
    $pendentes_count = (int) $pendencias['need_endereco'] + (int) $pendencias['need_dados_pessoais'];
    if ($pendentes_count === 1) {
      $html .= '<p>Assim que você concluir esse passo, o anúncio será publicado automaticamente e você receberá outro e-mail.</p>';
    } else {
      $html .= '<p>Assim que você concluir esses passos, o anúncio será publicado automaticamente e você receberá outro e-mail.</p>';
    }
    return $html;
  }

  return '';
}

/**
 * Botões do e-mail de “anúncio enviado”: Meus anúncios + Minha Conta / confirmar e-mail quando aplicável.
 *
 * @param int $author_id
 * @return array<int, array{label: string, url: string, text: string}>
 */
function bazar_publication_service_mail_buttons_submission($author_id)
{
  $first = array(
    array(
      'label' => 'Meus anúncios',
      'url' => home_url('/meus-anuncios/'),
      'text' => 'Meus anúncios',
    ),
  );
  return array_merge($first, bazar_publication_service_mail_buttons_perfil($author_id));
}

/**
 * Envia e-mail quando o anúncio foi aprovado mas ainda não publicou por falta de dados no perfil
 * (endereço e/ou nome/telefone). E-mail não confirmado não impede publicação; recomenda-se confirmar
 * na mesma mensagem quando aplicável.
 *
 * @param int $post_id ID do anúncio
 */
function bazar_publication_service_send_approved_pending_profile_email($post_id)
{
  $post = get_post($post_id);
  if (!$post || $post->post_type !== 'post') {
    return;
  }
  $author_id = (int) $post->post_author;
  $user_name = get_the_author_meta('user_firstname', $author_id);
  $user_email = get_the_author_meta('user_email', $author_id);
  if (empty($user_email)) {
    return;
  }

  $pendencias = bazar_publication_service_get_profile_pendencias_publicacao($author_id);
  if (!$pendencias['need_endereco'] && !$pendencias['need_dados_pessoais']) {
    return;
  }

  $profile_block = bazar_publication_service_mail_body_profile_publish_block($author_id, BAZAR_PUBLICATION_MAIL_CTX_APPROVED_PROFILE);
  if ($profile_block === '') {
    return;
  }

  $email_body = '<p>Olá ' . esc_html($user_name) . ',</p>';
  $email_body .= $profile_block;

  $pendentes_count = (int) $pendencias['need_endereco'] + (int) $pendencias['need_dados_pessoais'];
  $subject = ($pendentes_count === 1)
    ? 'Anúncio aprovado — falta um passo no cadastro'
    : 'Anúncio aprovado — faltam dados no cadastro';

  $mail_data = array(
    'name' => $user_name,
    'to' => $user_email,
    'subject' => $subject,
    'msg_header' => 'Anúncio aprovado',
    'email_body' => $email_body,
    'buttons' => bazar_publication_service_mail_buttons_perfil($author_id),
    'fail_on_error' => false,
  );

  if (class_exists('__Bazar_Send_Mail')) {
    $send_mail = new __Bazar_Send_Mail();
    $send_mail->send_mail_msg($mail_data);
  }
}