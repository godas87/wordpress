<?php
/**
 * Renormalização de selo "Usuário verificado"
 * Regra de negócio:
 * - Se usuário tem CPF + data_nascimento preenchidos em formato válido => bazar_perfil_verificado = true
 * - Caso contrário => bazar_perfil_verificado = false
 * - Metas legados de carimbo (_at/_source) são removidos
 *
 * Acesso: Ferramentas → Corrigir selo verificado (legado)
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  // Bootstrap WP para execução via CLI/stream
  $theme_dir = dirname(__DIR__, 2); // .../themes/bazar
  $wp_root = dirname($theme_dir, 3); // .../
  $wp_load = $wp_root . '/wp-load.php';
  if (!file_exists($wp_load)) {
    exit;
  }
  require_once $wp_load;
}

// Permitir execução via CLI também.
$__bazar_norm_is_cli = (php_sapi_name() === 'cli');

function bazar_norm_is_cpf_dob_valid($uid)
{
  $cpf_raw = (string) get_user_meta($uid, 'cpf', true);
  $cpf_digits = preg_replace('/\D/', '', $cpf_raw);
  $dob = trim((string) get_user_meta($uid, 'data_nascimento', true));

  if ($cpf_digits === '' || strlen($cpf_digits) !== 11) {
    return false;
  }
  if ($dob === '') {
    return false;
  }
  $dob_ok = false;
  if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dob)) {
    $dob_ok = true; // dd/mm/aaaa
  } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob)) {
    $dob_ok = true; // dd-mm-aaaa
  } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    $dob_ok = true; // yyyy-mm-dd
  }

  if (!$dob_ok) {
    return false;
  }
  return true;
}

function bazar_norm_is_true_meta($v)
{
  return ($v === 'true' || $v === true || $v === '1' || $v === 1);
}

function bazar_norm_sync_verified_meta($dry_run = true, $limit = 0)
{
  $args = array(
    'fields' => 'ID',
    'orderby' => 'ID',
    'order' => 'ASC',
    'number' => $limit > 0 ? (int) $limit : 0,
  );

  $users = get_users($args);
  $ids = is_array($users) ? $users : array();

  $stats = array(
    'total_processed' => count($ids),
    'updated' => 0,
    'set_true' => 0,
    'set_false' => 0,
    'dry_run' => (bool) $dry_run,
    'details' => array(),
  );

  foreach ($ids as $uid) {
    $uid = (int) $uid;
    $should_be_true = bazar_norm_is_cpf_dob_valid($uid);
    $current = get_user_meta($uid, 'bazar_perfil_verificado', true);
    $current_bool = bazar_norm_is_true_meta($current);

    $new_value = $should_be_true ? 'true' : 'false';
    $changed = ($current_bool !== $should_be_true);

    if (count($stats['details']) < 200) {
      $stats['details'][] = array(
        'user_id' => $uid,
        'cpf' => (string) get_user_meta($uid, 'cpf', true),
        'data_nascimento' => (string) get_user_meta($uid, 'data_nascimento', true),
        'from' => $current_bool ? 'true' : 'false',
        'to' => $new_value,
        'changed' => $changed,
      );
    }

    if ($changed) {
      $stats['updated']++;
      if ($should_be_true) {
        $stats['set_true']++;
      } else {
        $stats['set_false']++;
      }
    }

    if (!$dry_run) {
      update_user_meta($uid, 'bazar_perfil_verificado', $new_value);
      // Limpeza de legado/carimbo extra: não é usado na regra de negócio atual.
      delete_user_meta($uid, 'bazar_perfil_verificado_at');
      delete_user_meta($uid, 'bazar_perfil_verificado_source');
    }
  }

  return $stats;
}

add_action('admin_menu', function () {
  add_management_page(
    'Renormalizar Usuários Verificados',
    'Renormalizar Verificados',
    'manage_options',
    'bazar-normalize-verified-legacy',
    'bazar_normalize_verified_legacy_page'
  );
});

function bazar_normalize_verified_legacy_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  $ran = false;
  $last_stats = get_option('bazar_verified_sync_stats', array());

  if (isset($_POST['bazar_renormalizar_verified']) && check_admin_referer('bazar_renormalizar_verified_action')) {
    set_time_limit(0);
    $dry = false; // botão único executa de fato (1 vez)
    $limit = isset($_POST['limit']) ? max(0, (int) $_POST['limit']) : 0;
    $stats = bazar_norm_sync_verified_meta($dry, $limit);
    update_option('bazar_verified_sync_stats', $stats);
    $last_stats = $stats;
    $ran = true;
  }

  echo '<div class="wrap">';
  echo '<h1>Renormalizar selo “Usuário verificado”</h1>';

  echo '<div class="card" style="background:#fff;padding:16px;">';
  echo '<p><strong>Regra aplicada para todos os usuários:</strong></p>';
  echo '<ul style="list-style:disc;padding-left:18px;">';
  echo '<li>Se <code>cpf</code> e <code>data_nascimento</code> estiverem preenchidos/validos, define <code>bazar_perfil_verificado=true</code>.</li>';
  echo '<li>Caso contrário, define <code>bazar_perfil_verificado=false</code>.</li>';
  echo '<li>Remove metas extras <code>bazar_perfil_verificado_at</code> e <code>bazar_perfil_verificado_source</code>.</li>';
  echo '</ul>';
  echo '<p>Botão para execução única (sem simulação).</p>';
  echo '</div>';

  if ($ran && !empty($last_stats)) {
    $mode_label = !empty($last_stats['dry_run']) ? 'SIMULAÇÃO (dry-run)' : 'EXECUÇÃO';
    echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html($mode_label) . ' concluída.</strong> Processados: ' . (int) ($last_stats['total_processed'] ?? 0) . ' | Atualizados: ' . (int) ($last_stats['updated'] ?? 0) . ' | true: ' . (int) ($last_stats['set_true'] ?? 0) . ' | false: ' . (int) ($last_stats['set_false'] ?? 0) . '</p></div>';
  }

  echo '<div class="card" style="background:#fff;padding:16px; margin-top:16px;">';
  echo '<h2 style="margin-top:0;">Renormalizar</h2>';
  echo '<form method="post" action="">';
  wp_nonce_field('bazar_renormalizar_verified_action');
  echo '<p><label>Limite (0 = todos): <input type="number" name="limit" value="0" min="0" style="width:120px;"></label></p>';
  echo '<p><button type="submit" name="bazar_renormalizar_verified" class="button button-primary button-large" value="1">Renormalizar agora</button></p>';
  echo '</form>';
  echo '</div>';

  if (!empty($last_stats) && !empty($last_stats['details']) && is_array($last_stats['details'])) {
    echo '<div class="card" style="background:#fff;padding:16px; margin-top:16px;">';
    echo '<h2 style="margin-top:0;">Amostra (até 200)</h2>';
    echo '<pre style="background:#111;color:#0f0;padding:12px;overflow:auto;max-height:420px;">' . esc_html(wp_json_encode($last_stats['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
    echo '</div>';
  }

  echo '</div>';
}

// -----------------------
// Execução CLI
// -----------------------
if ($__bazar_norm_is_cli) {
  $argv_ = $_SERVER['argv'] ?? [];
  $action = 'sync';
  $dry_run = true;
  $limit = 0;

  foreach ($argv_ as $arg) {
    $arg = (string) $arg;
    if (preg_match('/--action=([a-z_]+)/', $arg, $m)) {
      $a = (string) ($m[1] ?? '');
      if (in_array($a, ['sync'], true)) {
        $action = $a;
      }
    }
    if (preg_match('/--limit=(\d+)/', $arg, $m)) {
      $limit = max(0, (int) $m[1]);
    }
    if ($arg === '--no-dry-run') {
      $dry_run = false;
    }
    if ($arg === '--dry-run') {
      $dry_run = true;
    }
  }

  $result = array('action' => $action, 'dry_run' => $dry_run, 'limit' => $limit);
  $result['stats'] = bazar_norm_sync_verified_meta($dry_run, $limit);

  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

