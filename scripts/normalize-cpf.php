<?php
/**
 * Painel Admin - Normalizacao silenciosa via API de CPF
 *
 * Objetivo:
 * - Executar o script sem enviar emails/notificacoes
 * - Permitir retomada via cursor
 * - Respeitar limite diario de 100 requests
 *
 * @package XXXXXX
 */
if (!defined('ABSPATH')) {
  exit;
}

add_action('admin_menu', function () {
  add_management_page(
    'Normalizar CPFs (Silencioso)',
    'Normalizar CPFs',
    'manage_options',
    'bazar-normalize-cpf-api-silent',
    'bazar_normalize_cpf_api_silent_page'
  );
});

function bazar_apply_usuario_update_policy_once($cutoff_ymd = '2026-02-19')
{
  global $wpdb;

  if (!$wpdb) {
    return array(
      'success' => false,
      'message' => 'wpdb indisponivel.'
    );
  }

  $cutoff_sql = (string) $cutoff_ymd . ' 00:00:00';
  $option_key = 'bazar_usuario_update_policy_applied_' . str_replace('-', '', (string) $cutoff_ymd);

  if (get_option($option_key)) {
    return array(
      'success' => false,
      'message' => 'Politica de usuario_update ja foi aplicada anteriormente.',
      'already_applied' => true,
    );
  }

  set_time_limit(0);

  // Antigos: user_registered < cutoff (liberar 1 edicao via usuario_update='false').
  // Novos: user_registered >= cutoff (bloquear via usuario_update='true').
  $old_ids = $wpdb->get_col(
    $wpdb->prepare(
      "SELECT ID FROM {$wpdb->users} WHERE user_registered < %s",
      $cutoff_sql
    )
  );
  $new_ids = $wpdb->get_col(
    $wpdb->prepare(
      "SELECT ID FROM {$wpdb->users} WHERE user_registered >= %s",
      $cutoff_sql
    )
  );

  $old_ids = is_array($old_ids) ? array_map('intval', $old_ids) : array();
  $new_ids = is_array($new_ids) ? array_map('intval', $new_ids) : array();

  $old_count = count($old_ids);
  $new_count = count($new_ids);

  foreach ($new_ids as $uid) {
    if ($uid > 0) {
      update_user_meta($uid, 'usuario_update', 'true');
    }
  }

  foreach ($old_ids as $uid) {
    if ($uid > 0) {
      update_user_meta($uid, 'usuario_update', 'false');
    }
  }

  update_option($option_key, true);
  update_option($option_key . '_date', current_time('mysql'));
  update_option($option_key . '_stats', array(
    'cutoff' => $cutoff_ymd,
    'old_count' => $old_count,
    'new_count' => $new_count,
  ));

  return array(
    'success' => true,
    'message' => 'Politica aplicada com sucesso.',
    'old_count' => $old_count,
    'new_count' => $new_count,
    'option_key' => $option_key,
  );
}

function bazar_normalize_cpf_api_silent_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  // Normalização manual por CPF (1 usuário).
  $manual_run = !empty($_POST['bazar_norm_cpf_manual_run']);
  $manual_cpf = '';
  $output = '';

  if ($manual_run) {
    $manual_cpf = (string) ($_POST['manual_cpf'] ?? '');
    $manual_cpf = preg_replace('/[^\d]/', '', $manual_cpf);
    $manual_cpf = str_pad($manual_cpf, 11, '0', STR_PAD_LEFT);
  }

  // Política 1 vez: bloquear novos e liberar antigos (cutoff 19/02/2026).
  $policy_cutoff = '2026-02-19';
  $policy_option_key = 'bazar_usuario_update_policy_applied_' . str_replace('-', '', (string) $policy_cutoff);
  $policy_done = (bool) get_option($policy_option_key, false);
  $policy_notice_html = '';

  $policy_run = !empty($_POST['bazar_apply_usuario_update_policy_1x']);
  if ($policy_run && isset($_POST['bazar_usuario_update_policy_nonce'])) {
    if (!wp_verify_nonce((string) $_POST['bazar_usuario_update_policy_nonce'], 'bazar_usuario_update_policy_action')) {
      $policy_notice_html = '<div class="notice notice-error"><p>Nonce invalido (politica).</p></div>';
    } else {
      if ($policy_done) {
        $policy_notice_html = '<div class="notice notice-info"><p>Politica ja executada anteriormente.</p></div>';
      } else {
        $result = bazar_apply_usuario_update_policy_once($policy_cutoff);
        if (!is_array($result) || empty($result['success'])) {
          $msg = is_array($result) && !empty($result['message']) ? $result['message'] : 'Erro ao aplicar politica.';
          $policy_notice_html = '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>';
        } else {
          $old_count = isset($result['old_count']) ? (int) $result['old_count'] : 0;
          $new_count = isset($result['new_count']) ? (int) $result['new_count'] : 0;
          $policy_notice_html = '<div class="notice notice-success"><p>Politica aplicada. Antigos: ' . $old_count . ' / Novos: ' . $new_count . '</p></div>';
        }
      }
    }
  }

  // Execução manual por CPF.
  if ($manual_run && isset($_POST['bazar_norm_cpf_nonce_manual']) && !empty($manual_cpf)) {
    if (!wp_verify_nonce((string) $_POST['bazar_norm_cpf_nonce_manual'], 'bazar_norm_cpf_api_silent_action')) {
      echo '<div class="notice notice-error"><p>Nonce invalido (manual).</p></div>';
    } else {
      // Forcar execucao do script normalizador silencioso.
      define('BAZAR_NORM_CPF_API_SILENT_RUN_NOW', true);
      $GLOBALS['BAZAR_NORM_CPF_API_SILENT_BATCH'] = 1;
      $GLOBALS['BAZAR_NORM_CPF_API_SILENT_RESET'] = 0;
      $GLOBALS['BAZAR_NORM_CPF_API_SILENT_MODE'] = 'manual';
      $GLOBALS['BAZAR_NORM_CPF_API_SILENT_MANUAL_CPF'] = $manual_cpf;

      $script_path = get_template_directory() . '/scripts/normalizar/normalize-users-cpf-api-silent.php';
      if (!file_exists($script_path)) {
        echo '<div class="notice notice-error"><p>Script de normalizacao nao encontrado.</p></div>';
      } else {
        ob_start();
        require $script_path;
        $output = (string) ob_get_clean();
      }
    }
  }

  // Render UI
  echo '<div class="wrap">';
  echo '<h1>Normalizar CPFs (Silencioso)</h1>';

  if (!empty($policy_notice_html)) {
    echo $policy_notice_html;
  }

  if ($output !== '') {
    echo '<div class="notice notice-success"><p>Execucao concluida. Veja o log abaixo.</p></div>';
    echo '<pre style="background:#111;color:#0f0;padding:12px;overflow:auto;max-height:420px;">' . esc_html($output) . '</pre>';
  }

  echo '<div class="card" style="background:#fff;padding:16px;">';
  echo '<p><strong>Observacao:</strong> normalizacao em massa foi removida (inviável). Use apenas normalizacao manual (1 CPF) e a politica 1 vez abaixo.</p>';

  echo '</div>'; // card
  // Form manual: normaliza 1 usuário pelo CPF informado.
  echo '<div class="card" style="background:#fff;padding:16px; margin-top: 16px;">';
  echo '<h2 style="margin-top:0;">Normalização manual (1 usuário)</h2>';
  echo '<p>Digite o CPF e clique em normalizar. Isso faz chamadas silenciosas para o(s) usuário(s) encontrado(s).</p>';
  echo '<form method="post">';
  wp_nonce_field('bazar_norm_cpf_api_silent_action', 'bazar_norm_cpf_nonce_manual');
  echo '<input type="hidden" name="bazar_norm_cpf_manual_run" value="1" />';
  echo '<p>';
  echo '<label>CPF do usuário:</label> ';
  echo '<input name="manual_cpf" type="text" placeholder="000.000.000-00" style="width:180px;" />';
  echo '</p>';
  echo '<p>';
  echo '<button type="submit" class="button button-secondary">Normalizar manual</button>';
  echo '</p>';
  echo '</form>';
  echo '</div>'; // manual card

  // Politica 1 vez: bloquear novos / liberar antigos
  $policy_done_date = (string) get_option($policy_option_key . '_date', '');
  echo '<div class="card" style="background:#fff;padding:16px; margin-top: 16px;">';
  echo '<h2 style="margin-top:0;">Politica de usuario_update (1 vez)</h2>';
  echo '<p>Aplicar regra para <strong>bloquear novos</strong> e <strong>liberar antigos</strong> (1 edição) usando cutoff em <strong>19/02/2026</strong>.</p>';
  if ($policy_done) {
    echo '<p><strong>Status:</strong> executado';
    if ($policy_done_date !== '') {
      echo ' em ' . esc_html($policy_done_date);
    }
    echo '.</p>';
    echo '<p><em>Este botão fica indisponível após a primeira execução.</em></p>';
  } else {
    echo '<form method="post">';
    wp_nonce_field('bazar_usuario_update_policy_action', 'bazar_usuario_update_policy_nonce');
    echo '<input type="hidden" name="bazar_apply_usuario_update_policy_1x" value="1" />';
    echo '<p><button type="submit" class="button button-primary">Aplicar politica (1 vez)</button></p>';
    echo '</form>';
  }
  echo '</div>'; // policy card

  echo '</div>'; // wrap
}

