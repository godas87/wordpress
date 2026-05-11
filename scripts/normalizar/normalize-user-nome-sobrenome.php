<?php
/**
 * Normalização: Nome e Sobrenome (1 palavra cada)
 *
 * Regra de negócio:
 * - first_name deve ser apenas o primeiro nome (primeira palavra)
 * - last_name deve conter apenas 1 sobrenome (1 palavra)
 * - se o sobrenome contiver o nome (ex.: "Pedro Pedro"), remover duplicação
 * - se last_name ficar vazio e o first_name original tiver mais de uma palavra,
 *   usar a 2ª palavra como last_name (ex.: "Pedro Godoy" -> first=Pedro, last=Godoy)
 *
 * Acesso: Ferramentas → Normalizar Nome/Sobrenome
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

function bazar_norm_name_tokens($s)
{
  $s = trim((string) $s);
  $s = preg_replace('/\s+/', ' ', $s);
  if ($s === '' || $s === null) {
    return array();
  }
  $parts = preg_split('/\s+/', $s);
  $parts = is_array($parts) ? array_values(array_filter($parts, function ($p) {
    return trim((string) $p) !== '';
  })) : array();
  return $parts;
}

function bazar_norm_key($s)
{
  $s = (string) $s;
  $s = function_exists('remove_accents') ? remove_accents($s) : $s;
  $s = mb_strtolower(trim($s), 'UTF-8');
  return $s;
}

function bazar_norm_title_case($s)
{
  $s = trim((string) $s);
  if ($s === '') {
    return '';
  }
  // UTF-8 safe title-case
  if (function_exists('mb_convert_case')) {
    $s = mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
  }
  // Manter espaços normalizados
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

function bazar_norm_is_preposicao_nome($token)
{
  $t = bazar_norm_key($token);
  // Preposições comuns em nomes no PT-BR (para remover no sobrenome)
  return in_array($t, array('de', 'da', 'do', 'dos', 'das', 'e'), true);
}

/**
 * Normaliza 1 usuário e retorna detalhes.
 *
 * @param int $user_id
 * @param bool $dry_run
 * @return array
 */
function bazar_normalize_user_nome_sobrenome($user_id, $dry_run = true)
{
  $user_id = (int) $user_id;
  $user = $user_id > 0 ? get_userdata($user_id) : false;
  if (!$user) {
    return array('updated' => false, 'user_id' => $user_id, 'error' => 'user_not_found');
  }

  $first_raw = trim((string) ($user->first_name ?? ''));
  $last_raw = trim((string) ($user->last_name ?? ''));

  $first_tokens = bazar_norm_name_tokens($first_raw);
  $last_tokens = bazar_norm_name_tokens($last_raw);

  // Determinar first_name final
  $first_pick = $first_tokens[0] ?? '';
  if ($first_pick === '' && !empty($last_tokens)) {
    $first_pick = $last_tokens[0] ?? '';
  }

  $first_final = bazar_norm_title_case($first_pick);

  // Determinar last_name final: priorizar last_name existente, removendo tokens iguais ao first
  $first_key = bazar_norm_key($first_final);
  $last_clean_tokens = array();
  foreach ($last_tokens as $t) {
    $tk = bazar_norm_key($t);
    if ($tk === '' || $tk === $first_key || bazar_norm_is_preposicao_nome($t)) {
      continue;
    }
    $last_clean_tokens[] = $t;
  }

  // Fallback: usar 2ª palavra do first_name original
  if (empty($last_clean_tokens) && count($first_tokens) >= 2) {
    $candidate = $first_tokens[1];
    if (!bazar_norm_is_preposicao_nome($candidate) && bazar_norm_key($candidate) !== $first_key) {
      $last_clean_tokens[] = $candidate;
    }
  }

  // Regra: apenas 1 sobrenome (1 palavra)
  $last_pick = $last_clean_tokens[0] ?? '';
  $last_final = bazar_norm_title_case($last_pick);

  // Evitar sobrenome igual ao nome
  if ($last_final !== '' && bazar_norm_key($last_final) === $first_key) {
    $last_final = '';
  }

  $updated = false;
  $changes = array();

  if ($first_final !== $first_raw) {
    $changes['first_name'] = array('from' => $first_raw, 'to' => $first_final);
  }
  if ($last_final !== $last_raw) {
    $changes['last_name'] = array('from' => $last_raw, 'to' => $last_final);
  }

  if (!empty($changes) && !$dry_run) {
    $r = wp_update_user(array(
      'ID' => $user_id,
      'first_name' => $first_final,
      'last_name' => $last_final,
    ));
    if (!is_wp_error($r)) {
      $updated = true;
    } else {
      return array(
        'updated' => false,
        'user_id' => $user_id,
        'error' => 'wp_update_user_failed',
        'wp_error' => $r->get_error_message(),
        'changes' => $changes,
      );
    }
  } elseif (!empty($changes)) {
    $updated = true; // “updated” no sentido de que mudaria algo
  }

  return array(
    'updated' => $updated,
    'user_id' => $user_id,
    'dry_run' => (bool) $dry_run,
    'changes' => $changes,
  );
}

function bazar_normalize_all_users_nome_sobrenome($dry_run = true, $limit = 0)
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
    'total' => count($ids),
    'updated' => 0,
    'dry_run' => (bool) $dry_run,
    'details' => array(),
  );

  foreach ($ids as $uid) {
    $r = bazar_normalize_user_nome_sobrenome((int) $uid, (bool) $dry_run);
    if (!empty($r['updated']) && !empty($r['changes'])) {
      $stats['updated']++;
      // Guardar poucos detalhes pra não estourar option
      if (count($stats['details']) < 200) {
        $stats['details'][] = $r;
      }
    }
  }

  return $stats;
}

add_action('admin_menu', 'bazar_add_normalize_nome_sobrenome_menu');
function bazar_add_normalize_nome_sobrenome_menu()
{
  add_management_page(
    'Normalizar Nome / Sobrenome',
    'Normalizar Nome/Sobrenome',
    'manage_options',
    'bazar-normalize-nome-sobrenome',
    'bazar_normalize_nome_sobrenome_page'
  );
}

function bazar_normalize_nome_sobrenome_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  $already_normalized = (bool) get_option('bazar_nome_sobrenome_normalized', false);
  $last_stats = get_option('bazar_nome_sobrenome_normalized_stats', array());

  $ran = false;
  $ran_dry = true;
  $limit = 0;

  if (isset($_POST['bazar_execute_normalize_nome_sobrenome']) && check_admin_referer('bazar_normalize_nome_sobrenome_action')) {
    set_time_limit(0);
    $force = isset($_POST['force']) && $_POST['force'] === '1';
    $dry = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
    $limit = isset($_POST['limit']) ? max(0, (int) $_POST['limit']) : 0;

    if ($force) {
      delete_option('bazar_nome_sobrenome_normalized');
    }

    $stats = bazar_normalize_all_users_nome_sobrenome($dry, $limit);
    update_option('bazar_nome_sobrenome_normalized', true);
    update_option('bazar_nome_sobrenome_normalized_stats', $stats);

    $last_stats = $stats;
    $already_normalized = true;
    $ran = true;
    $ran_dry = $dry;
  }

  echo '<div class="wrap">';
  echo '<h1>Normalizar Nome / Sobrenome</h1>';
  echo '<div class="card" style="background:#fff;padding:16px;">';
  echo '<h2 style="margin-top:0;">O que esta normalização faz?</h2>';
  echo '<ul style="list-style:disc;padding-left:18px;">';
  echo '<li><strong>Nome</strong>: fica apenas com a <strong>primeira palavra</strong> do first_name.</li>';
  echo '<li><strong>Sobrenome</strong>: fica com apenas <strong>1 palavra</strong> no last_name.</li>';
  echo '<li>Remove duplicações onde o <strong>nome aparece no sobrenome</strong> (ex.: \"Pedro Pedro\").</li>';
  echo '<li>Se não houver sobrenome, tenta usar a <strong>2ª palavra</strong> do nome original (ex.: \"Pedro Godoy\").</li>';
  echo '</ul>';
  echo '<p><strong>Recomendação:</strong> faça backup antes de executar.</p>';
  echo '</div>';

  if ($ran) {
    $mode_label = $ran_dry ? 'SIMULAÇÃO (dry-run)' : 'EXECUÇÃO';
    $total = isset($last_stats['total']) ? (int) $last_stats['total'] : 0;
    $updated = isset($last_stats['updated']) ? (int) $last_stats['updated'] : 0;
    echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html($mode_label) . ' concluída.</strong> ' . esc_html($total) . ' usuários processados, ' . esc_html($updated) . ' com ajustes.</p></div>';
  } elseif ($already_normalized && !empty($last_stats)) {
    $total = isset($last_stats['total']) ? (int) $last_stats['total'] : 0;
    $updated = isset($last_stats['updated']) ? (int) $last_stats['updated'] : 0;
    $dry = !empty($last_stats['dry_run']);
    echo '<div class="notice notice-info"><p><strong>Status:</strong> já executado anteriormente (' . ($dry ? 'dry-run' : 'execução') . '). Último resultado: ' . esc_html($total) . ' processados, ' . esc_html($updated) . ' com ajustes.</p></div>';
  }

  echo '<div class="card" style="background:#fff;padding:16px; margin-top:16px;">';
  echo '<h2 style="margin-top:0;">Executar normalização</h2>';
  echo '<form method="post" action="">';
  wp_nonce_field('bazar_normalize_nome_sobrenome_action');
  echo '<p><label><input type="checkbox" name="dry_run" value="1" checked> Rodar em <strong>simulação</strong> (não altera o banco)</label></p>';
  echo '<p><label><input type="checkbox" name="force" value="1" ' . ($already_normalized ? 'checked' : '') . '> Forçar execução (mesmo se já foi rodado)</label></p>';
  echo '<p><label>Limite (0 = todos): <input type="number" name="limit" value="0" min="0" style="width:120px;"></label></p>';
  echo '<p><button type="submit" name="bazar_execute_normalize_nome_sobrenome" class="button button-primary button-large">Executar</button></p>';
  echo '</form>';
  echo '</div>';

  if (!empty($last_stats) && !empty($last_stats['details']) && is_array($last_stats['details'])) {
    echo '<div class="card" style="background:#fff;padding:16px; margin-top:16px;">';
    echo '<h2 style="margin-top:0;">Amostra de alterações (até 200)</h2>';
    echo '<pre style="background:#111;color:#0f0;padding:12px;overflow:auto;max-height:420px;">' . esc_html(wp_json_encode($last_stats['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
    echo '</div>';
  }

  echo '</div>'; // wrap
}

