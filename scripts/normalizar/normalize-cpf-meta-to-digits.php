<?php
/**
 * Normaliza o usermeta "cpf" para conter apenas dígitos (11 números).
 *
 * Objetivo:
 * - Eliminar variações de máscara/formato no banco que quebram comparações (ex.: '123.456.789-00' vs '12345678900').
 * - Não altera "bazar_perfil_verificado" (só CPF no usermeta).
 *
 * Uso:
 * - Painel: Ferramentas -> Normalizar CPF (somente dígitos)
 * - CLI (opcional):
 *   php normalize-cpf-meta-to-digits.php --batch=200 --offset=0 --dry_run=1
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  // Execução via CLI sem bootstrap do WP.
  $theme_dir = dirname(__DIR__, 2); // .../themes/bazar
  $wp_root = dirname($theme_dir, 3); // .../
  $wp_load = $wp_root . '/wp-load.php';
  if (!file_exists($wp_load)) {
    echo "wp-load.php nao encontrado em: {$wp_load}\n";
    exit(1);
  }
  require_once $wp_load;
}

// Evita dupla inclusão (ex.: CLI direto -> wp-load -> functions.php -> requer este arquivo de novo).
if (!defined('BAZAR_NORMALIZE_CPF_DIGITS_HOOKED')) {
  define('BAZAR_NORMALIZE_CPF_DIGITS_HOOKED', '1');
  add_action('admin_menu', function () {
    add_management_page(
      'Normalizar CPF (digitos)',
      'Normalizar CPF',
      'manage_options',
      'bazar-normalize-cpf-digits',
      'bazar_normalize_cpf_digits_page'
    );
  });
}

if (!function_exists('bazar_normalize_cpf_digits_page')) {
function bazar_normalize_cpf_digits_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  $notice = '';
  $notice_class = 'notice-info';

  if (isset($_POST['bazar_cpf_digits_run']) && check_admin_referer('bazar_cpf_digits_action')) {
    $dry_run = empty($_POST['dry_run']) ? false : true;
    $limit = isset($_POST['limit']) ? max(1, (int) $_POST['limit']) : 200;
    $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;

    $stats = bazar_normalize_cpf_meta_to_digits($limit, $offset, $dry_run);

    $notice_class = (!empty($stats['updated_count'])) ? 'notice-success' : 'notice-info';
    $notice = sprintf(
      'Processados: %d | Atualizados: %d | Pulados: %d (dry_run=%s)',
      (int) ($stats['scanned_count'] ?? 0),
      (int) ($stats['updated_count'] ?? 0),
      (int) ($stats['skipped_count'] ?? 0),
      $dry_run ? 'true' : 'false'
    );
  }

  $last = get_option('bazar_cpf_digits_last_run_at', '');
  $last_txt = $last ? 'Última execução: ' . esc_html($last) : '';

  echo '<div class="wrap">';
  echo '<h1>Normalizar CPF (somente dígitos)</h1>';
  echo '<p>Converte o usermeta <code>cpf</code> para <strong>11 dígitos</strong>, removendo máscara e separadores.</p>';
  if ($notice !== '') {
    echo '<div class="notice ' . esc_attr($notice_class) . '"><p>' . esc_html($notice) . '</p></div>';
  }
  if ($last_txt) {
    echo '<p><em>' . $last_txt . '</em></p>';
  }

  echo '<form method="post">';
  wp_nonce_field('bazar_cpf_digits_action');
  echo '<input type="hidden" name="bazar_cpf_digits_run" value="1" />';

  echo '<table class="form-table" role="presentation">';
  echo '<tr>';
  echo '<th scope="row"><label for="dry_run">Dry-run (não grava)</label></th>';
  echo '<td><label><input type="checkbox" name="dry_run" value="1" checked> Não atualizar</label></td>';
  echo '</tr>';
  echo '<tr>';
  echo '<th scope="row"><label for="limit">Limite (por execução)</label></th>';
  echo '<td><input type="number" name="limit" value="200" min="1" style="width:120px"></td>';
  echo '</tr>';
  echo '<tr>';
  echo '<th scope="row"><label for="offset">Offset (cursor)</label></th>';
  echo '<td><input type="number" name="offset" value="0" min="0" style="width:120px"></td>';
  echo '</tr>';
  echo '</table>';

  echo '<p><button type="submit" class="button button-primary">Normalizar</button></p>';
  echo '</form>';

  echo '</div>';
}
}

if (!function_exists('bazar_normalize_cpf_meta_to_digits')) {
function bazar_normalize_cpf_meta_to_digits($limit, $offset, $dry_run)
{
  global $wpdb;
  if (!$wpdb) {
    return array(
      'scanned_count' => 0,
      'updated_count' => 0,
      'skipped_count' => 0,
      'dry_run' => (bool) $dry_run,
    );
  }

  $limit = max(1, (int) $limit);
  $offset = max(0, (int) $offset);

  // Buscar linhas do usermeta onde "cpf" existe e não está vazio.
  // (Não filtramos regex aqui porque pode ser mais pesado; normalizamos em PHP e decidimos.)
  $rows = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT user_id, meta_value
       FROM {$wpdb->usermeta}
       WHERE meta_key = 'cpf'
         AND meta_value IS NOT NULL
         AND TRIM(meta_value) <> ''
       ORDER BY user_id ASC
       LIMIT %d OFFSET %d",
      $limit,
      $offset
    )
  );

  $scanned = is_array($rows) ? count($rows) : 0;
  $updated = 0;
  $skipped = 0;

  if (!is_array($rows) || empty($rows)) {
    return array(
      'scanned_count' => 0,
      'updated_count' => 0,
      'skipped_count' => 0,
      'dry_run' => (bool) $dry_run,
    );
  }

  foreach ($rows as $r) {
    $uid = (int) ($r->user_id ?? 0);
    if ($uid <= 0) {
      $skipped++;
      continue;
    }
    $meta_value = (string) ($r->meta_value ?? '');
    $digits = preg_replace('/\D/', '', $meta_value);
    $digits = (string) $digits;

    if (strlen($digits) !== 11) {
      $skipped++;
      continue;
    }

    // Se já estiver normalizado, não grava.
    if ($meta_value === $digits) {
      $skipped++;
      continue;
    }

    if (!$dry_run) {
      update_user_meta($uid, 'cpf', $digits);
    }
    $updated++;
  }

  update_option('bazar_cpf_digits_last_run_at', current_time('mysql'), false);

  return array(
    'scanned_count' => $scanned,
    'updated_count' => $updated,
    'skipped_count' => $skipped,
    'dry_run' => (bool) $dry_run,
  );
}
}

// CLI (opcional). Apenas executa se estiver em modo CLI.
if (php_sapi_name() === 'cli' && isset($_SERVER['argv'])) {
  $batch = 200;
  $offset = 0;
  $dry_run = true;

  // Só executa se este arquivo for o "main script" do CLI.
  // (Se o tema já incluiu este arquivo, evita rodar duas vezes na mesma execução.)
  $argv0 = (string) ($_SERVER['argv'][0] ?? '');
  $is_main = false;
  try {
    $is_main = ($argv0 !== '') && (realpath($argv0) === realpath(__FILE__));
  } catch (Exception $e) {
    $is_main = ($argv0 !== '');
  }
  if (!$is_main) {
    return;
  }

  foreach ($_SERVER['argv'] as $arg) {
    if (preg_match('/--batch=(\d+)/', (string) $arg, $m)) {
      $batch = max(1, (int) $m[1]);
    } elseif (preg_match('/--offset=(\d+)/', (string) $arg, $m)) {
      $offset = max(0, (int) $m[1]);
    } elseif ($arg === '--dry_run=0' || $arg === '--dry-run=0') {
      $dry_run = false;
    } elseif ($arg === '--dry_run=1' || $arg === '--dry-run=1') {
      $dry_run = true;
    }
  }

  $stats = bazar_normalize_cpf_meta_to_digits($batch, $offset, $dry_run);
  echo "scanned={$stats['scanned_count']} updated={$stats['updated_count']} skipped={$stats['skipped_count']} dry_run=" . ($dry_run ? 'true' : 'false') . "\n";
}

