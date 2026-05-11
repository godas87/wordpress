<?php
/**
 * Helpers para perfil de usuário: completo, verificado (CPF/API) e selos de confiança.
 *
 * - Perfil completo: obrigatório para publicar anúncios (nome, telefone, endereço completo).
 * - Perfil verificado (bazar_perfil_verificado): CPF validado pela API; trava campos sensíveis.
 * - Selos: CPF | Endereço | Contato (e-mail hoje; telefone verificado no futuro). Check completo = os 3.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Nome, sobrenome e telefone obrigatórios para publicação (cadastro inicial pode deixar sobrenome vazio).
 *
 * @param int $user_id ID do usuário
 * @return bool
 */
function bazar_perfil_dados_pessoais_completos($user_id)
{
  if (!$user_id) {
    return false;
  }

  $user = get_userdata($user_id);
  if (!$user) {
    return false;
  }

  $first_name = trim((string) ($user->first_name ?? ''));
  $last_name = trim((string) ($user->last_name ?? ''));
  $telefone = trim((string) get_user_meta($user_id, 'fone', true));

  return (
    $first_name !== ''
    && $last_name !== ''
    && $telefone !== ''
  );
}

/**
 * Endereço mínimo para publicação (mesmos campos usados no CRUD / localização).
 *
 * @param int $user_id ID do usuário
 * @return bool
 */
function bazar_perfil_endereco_completo($user_id)
{
  if (!$user_id) {
    return false;
  }

  $cep = trim((string) get_user_meta($user_id, 'cep', true));
  $bairro = trim((string) get_user_meta($user_id, 'bairro', true));
  $cidade = trim((string) get_user_meta($user_id, 'cidade', true));
  $estado = trim((string) get_user_meta($user_id, 'estado', true));
  $estado_sigla = trim((string) get_user_meta($user_id, 'estado_sigla', true));
  if ($estado_sigla === '') {
    $estado_sigla = trim((string) get_user_meta($user_id, 'estado-sigla', true));
  }

  return (
    $cep !== ''
    && $bairro !== ''
    && $cidade !== ''
    && $estado !== ''
    && $estado_sigla !== ''
  );
}

/**
 * Verifica se o usuário tem dados mínimos para publicação de anúncios (alinhado ao service de publicação).
 * Exige: nome, sobrenome, telefone, endereço completo (CEP, bairro, cidade, estado, sigla). CPF e data de nascimento são opcionais.
 *
 * @param int $user_id ID do usuário
 * @return bool
 */
function bazar_perfil_completo($user_id)
{
  return bazar_perfil_dados_pessoais_completos($user_id)
    && bazar_perfil_endereco_completo($user_id);
}

/**
 * CPF e data de nascimento opcionais: retorna true se algum dos dois está vazio (selo/aviso na Minha Conta).
 *
 * @param int $user_id
 * @return bool
 */
function bazar_perfil_dados_opcionais_incompletos($user_id)
{
  if (!$user_id) {
    return false;
  }
  $cpf_raw = (string) get_user_meta($user_id, 'cpf', true);
  $cpf_digits = preg_replace('/\D/', '', (string) $cpf_raw);
  $dob = trim((string) get_user_meta($user_id, 'data_nascimento', true));

  // Para considerar "preenchido", CPF precisa ter 11 dígitos e DOB precisa parecer dd/mm/aaaa.
  if ($cpf_digits === '' || strlen($cpf_digits) !== 11) {
    return true;
  }
  $dob_ok = false;
  // Aceitar também formatos comuns que podem aparecer em produção/legado.
  if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dob)) {
    $dob_ok = true; // dd/mm/aaaa
  } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob)) {
    $dob_ok = true; // dd-mm-aaaa
  } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    $dob_ok = true; // yyyy-mm-dd
  }

  if ($dob === '' || !$dob_ok) {
    return true;
  }

  return false;
}

/**
 * Verifica se o perfil foi verificado pela API externa (ex.: CPF).
 * Usado para exibir selo "Escudo Verde" na Minha Conta.
 * Cache estático por request.
 *
 * @param int $user_id ID do usuário
 * @return bool
 */
function bazar_perfil_verificado($user_id)
{
  if (!$user_id) {
    return false;
  }

  // static $cache = array();
  // if (array_key_exists($user_id, $cache)) {
  //   return $cache[$user_id];
  // }

  // Para ser considerado "verificado", CPF e data de nascimento precisam estar preenchidos.
  // Caso contrário, mesmo que o meta esteja setado, não exibimos o selo.
  if (bazar_perfil_dados_opcionais_incompletos($user_id)) {
    return false;
  }

  $v = get_user_meta($user_id, 'bazar_perfil_verificado', true);
  $v_check = ($v === 'true' || $v === true || $v === '1' || $v === 1);
  return $v_check;
  // $cache[$user_id] = $v_check;
  //return $cache[$user_id];

}

/**
 * Selo CPF: identidade validada (mesma regra de bazar_perfil_verificado).
 */
function bazar_selo_cpf_ok($user_id)
{
  return (int) $user_id > 0 && function_exists('bazar_perfil_verificado') && bazar_perfil_verificado((int) $user_id);
}

/**
 * Selo endereço: CEP, bairro, cidade, estado e sigla preenchidos.
 */
function bazar_selo_endereco_ok($user_id)
{
  return (int) $user_id > 0 && function_exists('bazar_perfil_endereco_completo') && bazar_perfil_endereco_completo((int) $user_id);
}

/**
 * Selo contato: e-mail confirmado no cadastro. No futuro pode exigir também telefone verificado.
 */
function bazar_selo_contato_ok($user_id)
{
  return (int) $user_id > 0 && function_exists('bazar_usuario_email_confirmado_meta') && bazar_usuario_email_confirmado_meta((int) $user_id);
}

/**
 * Os três selos ativos (check completo no perfil e no anúncio).
 */
function bazar_perfil_selos_completos($user_id)
{
  $user_id = (int) $user_id;
  if ($user_id < 1) {
    return false;
  }
  return bazar_selo_cpf_ok($user_id)
    && bazar_selo_endereco_ok($user_id)
    && bazar_selo_contato_ok($user_id);
}

/**
 * Checklist único para a Minha Conta: CPF, endereço, e-mail confirmado.
 * Usar no template em vez de repetir chamadas aos selos.
 *
 * @param int $user_id
 * @return array Complete, items (id, ok, label), title_complete, title_pending
 */
function bazar_get_perfil_selos_checklist($user_id)
{
  $user_id = (int) $user_id;

  $labels = array(
    'cpf' => __('Identidade', 'bazar'),
    'endereco' => __('Endereço', 'bazar'),
    'contato' => __('E-mail', 'bazar'),
  );

  if ($user_id < 1) {
    $items = array();
    foreach ($labels as $id => $label) {
      $items[] = array(
        'id' => $id,
        'ok' => false,
        'label' => $label,
      );
    }
    return array(
      'complete' => false,
      'items' => $items,
      'title_complete' => __('Perfil Verificado', 'bazar'),
      'title_pending' => __('Verificação de Perfil', 'bazar'),
    );
  }

  $items = array(
    array(
      'id' => 'cpf',
      'ok' => bazar_selo_cpf_ok($user_id),
      'label' => $labels['cpf'],
    ),
    array(
      'id' => 'endereco',
      'ok' => bazar_selo_endereco_ok($user_id),
      'label' => $labels['endereco'],
    ),
    array(
      'id' => 'contato',
      'ok' => bazar_selo_contato_ok($user_id),
      'label' => $labels['contato'],
    ),
  );

  $complete = $items[0]['ok'] && $items[1]['ok'] && $items[2]['ok'];

  return array(
    'complete' => $complete,
    'items' => $items,
    'title_complete' => __('Perfil Verificado', 'bazar'),
    'title_pending' => __('Verificação de Perfil', 'bazar'),
  );
}

/**
 * E-mail confirmado no cadastro (só meta). Use em cron, filas e regras sem sessão.
 *
 * @param int $user_id ID do usuário
 * @return bool
 */
function bazar_usuario_email_confirmado_meta($user_id)
{
  $user_id = (int) $user_id;
  if ($user_id < 1) {
    return false;
  }
  $v = get_user_meta($user_id, 'ativar_email', true);
  return ($v === 'true' || $v === true || $v === '1' || $v === 1);
}

/**
 * Verifica se o e-mail está ativado para o contexto do visitante (alertas no site).
 * Sem login: não aplica (retorna true). Com login: lê a meta do usuário informado.
 *
 * @param int $user_id ID do usuário (normalmente get_current_user_id())
 * @return bool
 */
function bazar_email_ativado($user_id)
{
  if (!is_user_logged_in()) {
    return true;
  }

  if (!$user_id) {
    return false;
  }
  return bazar_usuario_email_confirmado_meta((int) $user_id);
}