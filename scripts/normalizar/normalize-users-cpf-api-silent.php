<?php
/**
 * Normalizacao SILENCIOSA de usuarios via API de CPF.
 *
 * Regras (conforme combinadas):
 * - Nao enviar emails/nenhum tipo de notificacao neste script.
 * - Validar CPF via API (sem usar __BazarValidations, para evitar emails).
 * - Preencher last_name com o sobrenome da API (fonte de verdade):
 *   - se user last_name estiver vazio: preenche
 *   - se nao bater: substitui
 * - Nome (first_name): nao altera.
 *   - se first_name nao bater com o first_name da API (apos normalizacao), marcar meta
 *     user_meta: bazar_nome_inconsistente = 1
 * - data_nascimento da API (YYYY-MM-DD):
 *   - converter para dd/mm/aaaa
 *   - se indicar menor de idade (< 18): nao sobrescrever (deixa como esta)
 * - CPF invalido + nao tem nenhum post_type='post' (mesmo pending/draft):
 *   - bloquear usuario: user_meta bazar_user_blocked = true
 *   - role=none nao e usada (nao existe no WP), apenas bloquear e suficiente
 * - API: limite de 100 requests/dia.
 * - Retomar de onde parou no ultimo dia:
 *   - cursor em option: bazar_norm_cpf_cursor_user_id
 *   - contador diario em option: bazar_norm_cpf_counter_date, bazar_norm_cpf_counter_count
 * - Performance (1314 usuarios):
 *   - cache por CPF via transient para nao reconsultar
 *   - processamento em cursor para retomar
 *
 * Executacao recomendada (CLI):
 *   php normalize-users-cpf-api-silent.php --batch=50
 *   php normalize-users-cpf-api-silent.php --reset
 *
 * @package XXXXXX
 */

// Permitir execucao via CLI e via painel admin (normalizador silencioso).
$__bazar_norm_is_cli = php_sapi_name() === 'cli';

// Bootstrap do WordPress (somente se nao estiver carregado).
if (!defined('ABSPATH')) {
  $theme_dir = dirname(__DIR__, 2); // .../themes/bazar
  $wp_root = dirname($theme_dir, 3); // .../ (raiz do WP)
  $wp_load = $wp_root . '/wp-load.php';
  if (!file_exists($wp_load)) {
    echo "wp-load.php nao encontrado em: {$wp_load}\n";
    exit(1);
  }
  require_once $wp_load;
}

// Silenciar emails (inclusive caso algum hook externo tente enviar).
add_filter('wp_mail', function () {
  return false;
});

// -----------------------
// Parse de argumentos
// -----------------------
$batch = 50;
$reset = false;
$mode = 'all';

if ($__bazar_norm_is_cli) {
  $argv_ = $_SERVER['argv'] ?? [];
  foreach ($argv_ as $arg) {
    if (preg_match('/--batch=(\d+)/', (string) $arg, $m)) {
      $batch = max(1, (int) $m[1]);
    }
    if (preg_match('/--mode=([a-z_]+)/', (string) $arg, $m)) {
      $m0 = (string) ($m[1] ?? '');
      if (in_array($m0, ['all', 'with_published', 'without_any', 'manual'], true)) {
        $mode = $m0;
      }
    }
    if ($arg === '--reset') {
      $reset = true;
    }
  }
} else {
  // Receber parâmetros via globals setados pelo painel admin.
  if (isset($GLOBALS['BAZAR_NORM_CPF_API_SILENT_BATCH'])) {
    $batch = max(1, (int) $GLOBALS['BAZAR_NORM_CPF_API_SILENT_BATCH']);
  }
  if (!empty($GLOBALS['BAZAR_NORM_CPF_API_SILENT_RESET'])) {
    $reset = true;
  }
  if (!empty($GLOBALS['BAZAR_NORM_CPF_API_SILENT_MODE'])) {
    $m0 = (string) $GLOBALS['BAZAR_NORM_CPF_API_SILENT_MODE'];
    if (in_array($m0, ['all', 'with_published', 'without_any', 'manual'], true)) {
      $mode = $m0;
    }
  }
}

// -----------------------
// Config
// -----------------------
$API_LIMIT_PER_DAY = 100;
// Cache por CPF para reduzir consultas (dias).
$CPF_CACHE_DAYS = 30;
// Prefixo das options/transients.
$OPT_CURSOR_BASE = 'bazar_norm_cpf_cursor_user_id';
$OPT_COUNTER_DATE = 'bazar_norm_cpf_counter_date';
$OPT_COUNTER_COUNT = 'bazar_norm_cpf_counter_count';
$TRANSIENT_PREFIX = 'bazar_norm_cpf_api_';
$OPT_CURSOR = $mode === 'all' ? $OPT_CURSOR_BASE : ($OPT_CURSOR_BASE . '_' . $mode);

// -----------------------
// Log persistente (para auditoria)
// -----------------------
// Preferencia: gravar na mesma pasta do script, para facilitar conferencia.
$LOG_TODAY = function_exists('current_time') ? current_time('Y-m-d') : date('Y-m-d');
$LOG_DIR = rtrim(__DIR__, "/\\");
$LOG_FILE = $LOG_DIR . '/bazar-norm-cpf-' . $LOG_TODAY . '_' . $mode . '.jsonl';

function bazar_norm_log_jsonl($event)
{
  global $LOG_FILE;
  if (!is_string($LOG_FILE) || trim($LOG_FILE) === '') {
    return;
  }
  // JSONL facilita append e leitura posterior.
  $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if (!is_string($line)) {
    return;
  }
  @file_put_contents($LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
}

// Garante que o arquivo exista mesmo se nao houver candidatos/alteracoes.
if (!file_exists($LOG_FILE)) {
  @file_put_contents(
    $LOG_FILE,
    json_encode(
      [
        'ts' => function_exists('current_time') ? current_time('mysql') : date('c'),
        'event' => 'init',
        'mode' => $mode,
        'batch' => $batch ?? null,
        'reset' => $reset ?? null,
      ],
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . "\n",
    FILE_APPEND | LOCK_EX
  );
}

// -----------------------
// Helpers de normalizacao
// -----------------------
function bazar_norm_remover_preposicoes($string)
{
  $preposicoes = ['de', 'da', 'do', 'dos', 'das', 'e'];
  return str_replace($preposicoes, '', $string);
}

function bazar_norm_remover_acentos($string)
{
  $acentos = [
    'á' => 'a',
    'à' => 'a',
    'ã' => 'a',
    'â' => 'a',
    'ä' => 'a',
    'é' => 'e',
    'è' => 'e',
    'ê' => 'e',
    'ë' => 'e',
    'í' => 'i',
    'ì' => 'i',
    'î' => 'i',
    'ï' => 'i',
    'ó' => 'o',
    'ò' => 'o',
    'õ' => 'o',
    'ô' => 'o',
    'ö' => 'o',
    'ú' => 'u',
    'ù' => 'u',
    'û' => 'u',
    'ü' => 'u',
    'ç' => 'c',
    'Á' => 'A',
    'À' => 'A',
    'Ã' => 'A',
    'Â' => 'A',
    'Ä' => 'A',
    'É' => 'E',
    'È' => 'E',
    'Ê' => 'E',
    'Ë' => 'E',
    'Í' => 'I',
    'Ì' => 'I',
    'Î' => 'I',
    'Ï' => 'I',
    'Ó' => 'O',
    'Ò' => 'O',
    'Õ' => 'O',
    'Ô' => 'O',
    'Ö' => 'O',
    'Ú' => 'U',
    'Ù' => 'U',
    'Û' => 'U',
    'Ü' => 'U',
    'Ç' => 'C',
  ];
  return strtr($string, $acentos);
}

function bazar_norm_normalizar_nome($nome)
{
  $nome = strtolower((string) $nome);
  $nome = bazar_norm_remover_acentos($nome);
  $nome = bazar_norm_remover_preposicoes($nome);
  $nome = preg_replace('/\s+/', ' ', trim($nome));
  // Remove pontuacao/simbolos e mantem apenas letras e espacos.
  $nome = preg_replace('/[^a-z\s]/', '', $nome);
  return $nome;
}

function bazar_norm_parse_api_full_name($full_name)
{
  $full_name = trim((string) $full_name);
  if ($full_name === '') {
    return ['first' => '', 'last' => ''];
  }
  // Separar por espacos e ignorar entradas vazias.
  $parts = preg_split('/\s+/', $full_name) ?: [];
  $parts = array_values(array_filter($parts, static fn($p) => trim((string) $p) !== ''));
  if (empty($parts)) {
    return ['first' => '', 'last' => ''];
  }
  $first = (string) ($parts[0] ?? '');
  $last = '';
  if (count($parts) > 1) {
    $last = trim(implode(' ', array_slice($parts, 1)));
  }
  return ['first' => $first, 'last' => $last];
}

function bazar_norm_convert_yyyy_mm_dd_to_dd_mm_yyyy($yyyy_mm_dd)
{
  $yyyy_mm_dd = trim((string) $yyyy_mm_dd);
  if ($yyyy_mm_dd === '') {
    return '';
  }
  $dt = DateTime::createFromFormat('Y-m-d', $yyyy_mm_dd);
  if ($dt === false) {
    return '';
  }
  return $dt->format('d/m/Y');
}

function bazar_norm_try_convert_api_dob_to_dd_mm_yyyy($api_dob)
{
  $api_dob = trim((string) $api_dob);
  if ($api_dob === '') {
    return '';
  }

  // Formato esperado original: YYYY-MM-DD
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $api_dob)) {
    return bazar_norm_convert_yyyy_mm_dd_to_dd_mm_yyyy($api_dob);
  }

  // Já pode vir como dd/mm/yyyy (ou variações com - e .)
  if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $api_dob)) {
    return $api_dob;
  }
  if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $api_dob)) {
    return str_replace('-', '/', $api_dob);
  }
  if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $api_dob)) {
    return str_replace('.', '/', $api_dob);
  }

  // Tentativas finais com DateTime
  $dt = DateTime::createFromFormat('d/m/Y', $api_dob);
  if ($dt !== false) {
    return $dt->format('d/m/Y');
  }
  $dt = DateTime::createFromFormat('d-m-Y', $api_dob);
  if ($dt !== false) {
    return $dt->format('d/m/Y');
  }

  return '';
}

function bazar_norm_is_minor_from_yyyy_mm_dd($yyyy_mm_dd)
{
  $yyyy_mm_dd = trim((string) $yyyy_mm_dd);
  if ($yyyy_mm_dd === '') {
    return false;
  }
  $dt_birth = DateTime::createFromFormat('Y-m-d', $yyyy_mm_dd);
  if ($dt_birth === false) {
    return false;
  }
  $now = new DateTime();
  $age = $now->diff($dt_birth)->y;
  return ($age < 18);
}

function bazar_norm_is_minor_from_api_dob($api_dob)
{
  $api_dob = trim((string) $api_dob);
  if ($api_dob === '') {
    return false;
  }

  // Se vier como YYYY-MM-DD
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $api_dob)) {
    return bazar_norm_is_minor_from_yyyy_mm_dd($api_dob);
  }

  // Se vier em dd/mm/yyyy (ou -/.)
  $converted = bazar_norm_try_convert_api_dob_to_dd_mm_yyyy($api_dob);
  if ($converted === '') {
    return false; // sem interpretar, não bloqueia/salta
  }

  $dt_birth = DateTime::createFromFormat('d/m/Y', $converted);
  if ($dt_birth === false) {
    return false;
  }

  $now = new DateTime();
  $age = $now->diff($dt_birth)->y;
  return ($age < 18);
}

function bazar_norm_user_has_posts_any_status($user_id)
{
  $posts = get_posts([
    'post_type' => 'post',
    'author' => (int) $user_id,
    'post_status' => 'any',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'no_found_rows' => true,
  ]);
  return !empty($posts);
}

function bazar_norm_get_author_ids_with_published_posts()
{
  global $wpdb;
  $post_type = 'post';
  $status = 'publish';
  $sql = $wpdb->prepare(
    "SELECT DISTINCT post_author
     FROM {$wpdb->posts}
     WHERE post_type = %s AND post_status = %s AND post_author IS NOT NULL AND post_author <> 0",
    $post_type,
    $status
  );

  $ids = $wpdb->get_col($sql);
  return is_array($ids) ? array_map('intval', $ids) : [];
}

function bazar_norm_get_author_ids_with_any_posts()
{
  global $wpdb;
  $post_type = 'post';
  $sql = $wpdb->prepare(
    "SELECT DISTINCT post_author
     FROM {$wpdb->posts}
     WHERE post_type = %s AND post_author IS NOT NULL AND post_author <> 0",
    $post_type
  );

  $ids = $wpdb->get_col($sql);
  return is_array($ids) ? array_map('intval', $ids) : [];
}

function bazar_norm_block_user_silent($user_id, $by_user_id = 0)
{
  update_user_meta($user_id, 'bazar_user_blocked', true);
  update_user_meta($user_id, 'bazar_user_blocked_date', current_time('mysql'));
  update_user_meta($user_id, 'bazar_user_blocked_by', (int) $by_user_id);
}

// -----------------------
// API CPF - chamada separada (sem __BazarValidations)
// -----------------------
function bazar_norm_fetch_cpf_from_api($cpf_limpo)
{
  $cpf_limpo = preg_replace('/[^\d]/', '', (string) $cpf_limpo);
  $cpf_limpo = str_pad($cpf_limpo, 11, '0', STR_PAD_LEFT);
  if (strlen($cpf_limpo) !== 11) {
    return [
      'ok' => false,
      'exists' => false,
      'error_type' => 'cpf_format_invalid',
      'api_data' => null,
    ];
  }

  $api_key = (string) get_option('bazar_api_cpf_key', '');
  if (trim($api_key) === '') {
    return [
      'ok' => false,
      'exists' => false,
      'error_type' => 'missing_api_key',
      'api_data' => null,
    ];
  }

  $api_url = 'https://XXXXXX/api/consulta?cpf=' . $cpf_limpo;

  $response = wp_remote_get($api_url, [
    'timeout' => 15,
    'headers' => [
      'X-API-KEY' => $api_key,
      'Content-Type' => 'application/json',
      'User-Agent' => 'XXXXXX/normalizacao-cpf/1.0',
    ],
    'sslverify' => false,
    'redirection' => 3,
  ]);

  if (is_wp_error($response)) {
    return [
      'ok' => false,
      'exists' => false,
      'error_type' => 'api_indisponivel',
      'api_data' => null,
    ];
  }

  $http_code = wp_remote_retrieve_response_code($response);
  $body = wp_remote_retrieve_body($response);
  $decoded = !empty($body) ? json_decode($body, true) : null;

  if (!is_array($decoded)) {
    return [
      'ok' => false,
      'exists' => false,
      'error_type' => 'api_resposta_invalida',
      'api_data' => null,
    ];
  }

  $code = $decoded['code'] ?? null;
  if ((string) $code === '200') {
    return [
      'ok' => true,
      'exists' => true,
      'error_type' => null,
      'api_data' => $decoded['data'] ?? null,
      'http_code' => $http_code,
    ];
  }

  if ((string) $code === '404') {
    return [
      'ok' => true,
      'exists' => false,
      'error_type' => 'cpf_nao_existe',
      'api_data' => null,
      'http_code' => $http_code,
    ];
  }

  // Para outros codigos, tratar como indisponivel (nao bloquear automaticamente).
  return [
    'ok' => false,
    'exists' => false,
    'error_type' => 'api_indisponivel',
    'api_data' => null,
    'http_code' => $http_code,
  ];
}

// -----------------------
// Estado/retomada + Execucao (guardado)
// -----------------------
$__bazar_norm_should_run =
  $__bazar_norm_is_cli
  || defined('BAZAR_NORM_CPF_API_SILENT_RUN_NOW');

if ($__bazar_norm_should_run):
  if ($reset) {
    // Reset completo das duas rotas para evitar “pular” usuários.
    delete_option($OPT_CURSOR_BASE);
    delete_option($OPT_CURSOR_BASE . '_with_published');
    delete_option($OPT_CURSOR_BASE . '_without_any');
    delete_option($OPT_COUNTER_DATE);
    delete_option($OPT_COUNTER_COUNT);
    echo "Reset realizado.\n";
  }

  $cursor = (int) get_option($OPT_CURSOR, 0);
  $manual_cpf_clean = '';
  $manual_user_ids = [];

  // Modo manual: normaliza apenas usuários correspondentes ao CPF informado.
  if (!empty($GLOBALS['BAZAR_NORM_CPF_API_SILENT_MANUAL_CPF'])) {
    $manual_cpf_raw = (string) $GLOBALS['BAZAR_NORM_CPF_API_SILENT_MANUAL_CPF'];
    $manual_cpf_clean = preg_replace('/[^\d]/', '', $manual_cpf_raw);
    $manual_cpf_clean = str_pad($manual_cpf_clean, 11, '0', STR_PAD_LEFT);

    if (!empty($manual_cpf_clean) && strlen($manual_cpf_clean) === 11) {
      // Importante: usermeta.cpf pode estar com pontuação/formatação.
      // Então não fazemos match exato no meta_value; filtramos pelo CPF “limpo”.
      $all_with_cpf = get_users([
        'fields' => 'ID',
        'orderby' => 'ID',
        'order' => 'ASC',
        'number' => -1,
        'meta_query' => [
          [
            'key' => 'cpf',
            'value' => '',
            'compare' => '!=',
          ],
        ],
      ]);

      $manual_user_ids = [];
      if (is_array($all_with_cpf)) {
        foreach ($all_with_cpf as $candidate_uid) {
          $candidate_uid = (int) $candidate_uid;
          $cpf_raw = get_user_meta($candidate_uid, 'cpf', true);
          $cpf_clean = preg_replace('/[^\d]/', '', (string) $cpf_raw);
          $cpf_clean = str_pad($cpf_clean, 11, '0', STR_PAD_LEFT);
          if ($cpf_clean === $manual_cpf_clean) {
            $manual_user_ids[] = $candidate_uid;
          }
        }
      }
    }
  }

  $today = current_time('Y-m-d');
  $counter_date = (string) get_option($OPT_COUNTER_DATE, '');
  $counter_count = (int) get_option($OPT_COUNTER_COUNT, 0);

  if ($counter_date !== $today) {
    $counter_date = $today;
    $counter_count = 0;
    update_option($OPT_COUNTER_DATE, $counter_date);
    update_option($OPT_COUNTER_COUNT, $counter_count);
  }

  echo "Cursor atual: {$cursor}\n";
  echo "Contador API hoje ({$today}): {$counter_count}/{$API_LIMIT_PER_DAY}\n";
  echo "Modo de normalizacao: {$mode}\n";

  // Buscar candidatos (cpf preenchido) - ou só o CPF manual.
  if ($mode === 'manual' && !empty($manual_user_ids)) {
    // Não respeita cursor no modo manual (evita pular por retenção do dia).
    $cursor = 0;
    $candidate_ids = array_map(static fn($x) => (int) $x, $manual_user_ids);
    echo "Modo manual: CPF={$manual_cpf_clean} | users=" . count($candidate_ids) . "\n";
    bazar_norm_log_jsonl([
      'ts' => current_time('mysql'),
      'event' => 'manual_candidates',
      'mode' => $mode,
      'manual_cpf' => $manual_cpf_clean,
      'users' => count($candidate_ids),
    ]);
  } else {
    // Buscar candidatos (cpf preenchido)
    $candidate_args = [
      'fields' => 'ID',
      'orderby' => 'ID',
      'order' => 'ASC',
      'number' => -1,
      'meta_query' => [
        [
          'key' => 'cpf',
          'value' => '',
          'compare' => '!=',
        ],
      ],
    ];

    $candidate_ids = get_users($candidate_args);
    if (empty($candidate_ids) || !is_array($candidate_ids)) {
      bazar_norm_log_jsonl([
        'ts' => current_time('mysql'),
        'event' => 'candidates_empty',
        'mode' => $mode,
        'manual_cpf' => $manual_cpf_clean,
        'manual_users_found' => count($manual_user_ids ?? []),
      ]);
      echo "Nenhum usuario com CPF encontrado.\n";
      exit(0);
    }
  }

  // Log base após seleção de candidatos (para diagnóstico).
  bazar_norm_log_jsonl([
    'ts' => current_time('mysql'),
    'event' => 'candidates_selected',
    'mode' => $mode,
    'manual_cpf' => $manual_cpf_clean,
    'manual_users_found' => count($manual_user_ids ?? []),
    'candidate_count' => is_array($candidate_ids) ? count($candidate_ids) : 0,
    'manual_users_sample' => isset($manual_user_ids) && is_array($manual_user_ids) ? array_slice(array_map('intval', $manual_user_ids), 0, 10) : [],
    'candidate_ids_sample' => is_array($candidate_ids) ? array_slice(array_map('intval', $candidate_ids), 0, 10) : [],
  ]);

  // Aplicar filtro de rota (para reduzir chamadas na API).
  if ($mode === 'manual') {
    // No manual, não filtramos por publish/sem posts.
    echo "Modo manual: pulando filtro de rota.\n";
  } elseif ($mode === 'with_published') {
    $authors_published = bazar_norm_get_author_ids_with_published_posts();
    $set = array_fill_keys($authors_published, true);
    $filtered = [];
    foreach ($candidate_ids as $uid) {
      $uid_i = (int) $uid;
      if (isset($set[$uid_i])) {
        $filtered[] = $uid_i;
      }
    }
    $candidate_ids = $filtered;
    echo "Candidatos apos filtro (com publish): " . count($candidate_ids) . "\n";
  } elseif ($mode === 'without_any') {
    $authors_any = bazar_norm_get_author_ids_with_any_posts();
    $set = array_fill_keys($authors_any, true);
    $filtered = [];
    foreach ($candidate_ids as $uid) {
      $uid_i = (int) $uid;
      if (!isset($set[$uid_i])) {
        $filtered[] = $uid_i;
      }
    }
    $candidate_ids = $filtered;
    echo "Candidatos apos filtro (sem post_type='post'): " . count($candidate_ids) . "\n";
  } else {
    echo "Candidatos (modo all): " . count($candidate_ids) . "\n";
  }

  if (empty($candidate_ids) || !is_array($candidate_ids)) {
    echo "Nenhum candidato para este modo apos filtro.\n";
    exit(0);
  }

  $processed_users = 0;

  // Dedup local de CPF (para rodar em lote sem consultar repetido no mesmo run).
  $cpf_local_cache = [];

  foreach ($candidate_ids as $uid) {
    $uid = (int) $uid;
    if ($uid <= $cursor) {
      continue;
    }

    if ($processed_users >= $batch) {
      echo "Limite de batch atingido (users={$batch}).\n";
      break;
    }

    $cpf_raw = get_user_meta($uid, 'cpf', true);
    $cpf_clean = preg_replace('/[^\d]/', '', (string) $cpf_raw);
    $cpf_clean = str_pad($cpf_clean, 11, '0', STR_PAD_LEFT);

    // Atualizar cursor mesmo se nao houver cpf util.
    update_option($OPT_CURSOR, $uid);
    $processed_users++;

    $log_event_base = [
      'ts' => current_time('mysql'),
      'mode' => $mode,
      'user_id' => $uid,
      'cpf' => $cpf_clean,
    ];

    if (empty($cpf_clean) || strlen($cpf_clean) !== 11) {
      echo "[{$uid}] CPF invalido no formato (meta). Pular.\n";
      bazar_norm_log_jsonl($log_event_base + [
        'result' => 'cpf_invalido_format',
      ]);
      continue;
    }

    // Verificar cache local/dedupe
    if (isset($cpf_local_cache[$cpf_clean])) {
      $api_result = $cpf_local_cache[$cpf_clean];
    } else {
      // Cache persistente via transient.
      $tkey = $TRANSIENT_PREFIX . $cpf_clean;
      $api_result = get_transient($tkey);
      if (!is_array($api_result)) {
        // Antes: travava ao atingir 100/dia.
        // Agora: remove a trava para permitir continuar mesmo com risco de exceder cota.
        if ($counter_count >= $API_LIMIT_PER_DAY) {
          echo "Limite diario de API atingido, continuando mesmo assim em user_id={$uid}.\n";
        }

        $api_result = bazar_norm_fetch_cpf_from_api($cpf_clean);

        // Incrementar contador SOMENTE em chamadas reais.
        $counter_count++;
        update_option($OPT_COUNTER_COUNT, $counter_count);

        set_transient($tkey, $api_result, $CPF_CACHE_DAYS * DAY_IN_SECONDS);
      }

      $cpf_local_cache[$cpf_clean] = $api_result;
    }

    // Aplicar regras com base no resultado.
    $user = get_userdata($uid);
    if (!$user) {
      echo "[{$uid}] Usuario nao encontrado. Pular.\n";
      bazar_norm_log_jsonl($log_event_base + [
        'result' => 'usuario_nao_encontrado',
      ]);
      continue;
    }

    $api_data = isset($api_result['api_data']) ? $api_result['api_data'] : null;

    // CPF invalido ou nao existe na API.
    if (array_key_exists('exists', $api_result) && $api_result['exists'] === false) {
      // Ajuste para rotas: aqui ja filtramos por “tem publish” ou “sem nenhum post”.
      if ($mode === 'without_any') {
        $has_posts = false;
      } elseif ($mode === 'with_published') {
        $has_posts = true;
      } else {
        $has_posts = bazar_norm_user_has_posts_any_status($uid);
      }
      if (!$has_posts) {
        bazar_norm_block_user_silent($uid, 0);
        echo "[{$uid}] CPF nao existe + sem posts: bloqueado.\n";
        bazar_norm_log_jsonl($log_event_base + [
          'result' => 'cpf_nao_existe_bloqueado',
        ]);
      } else {
        echo "[{$uid}] CPF nao existe, mas possui posts: nao bloqueado.\n";
        bazar_norm_log_jsonl($log_event_base + [
          'result' => 'cpf_nao_existe_com_posts',
        ]);
      }
      update_user_meta($uid, 'bazar_norm_cpf_result_last', 'cpf_nao_existe');
      continue;
    }

    // Caso API falhe (indisponivel/outros), nao mexer.
    if (empty($api_result['ok']) || empty($api_result['exists'])) {
      echo "[{$uid}] API indisponivel/erro. Pular alteracoes.\n";
      update_user_meta($uid, 'bazar_norm_cpf_result_last', 'api_indisponivel');
      bazar_norm_log_jsonl($log_event_base + [
        'result' => 'api_erro',
        'api_error_type' => isset($api_result['error_type']) ? (string) $api_result['error_type'] : null,
        'api_http_code' => isset($api_result['http_code']) ? (int) $api_result['http_code'] : null,
      ]);
      continue;
    }

    if (!is_array($api_data)) {
      echo "[{$uid}] API ok mas api_data ausente. Pular alteracoes.\n";
      update_user_meta($uid, 'bazar_norm_cpf_result_last', 'api_data_missing');
      bazar_norm_log_jsonl($log_event_base + [
        'result' => 'api_data_ausente',
      ]);
      continue;
    }

    $api_full_name = $api_data['nome'] ?? '';
    $api_dob = trim((string) ($api_data['data_nascimento'] ?? ''));

    $name_parts = bazar_norm_parse_api_full_name($api_full_name);
    $api_first = $name_parts['first'];
    $api_last = $name_parts['last'];

    $user_first = (string) ($user->first_name ?? '');
    $user_last = (string) ($user->last_name ?? '');

    $api_first_norm = bazar_norm_normalizar_nome($api_first);
    $api_last_norm = bazar_norm_normalizar_nome($api_last);
    $user_first_norm = bazar_norm_normalizar_nome($user_first);
    $user_last_norm = bazar_norm_normalizar_nome($user_last);

    // first_name: apenas marcar inconsistência (sem bloquear).
    if ($api_first_norm !== '' && $user_first_norm !== '' && $api_first_norm !== $user_first_norm) {
      update_user_meta($uid, 'bazar_nome_inconsistente', 1);
      echo "[{$uid}] Nome inconsistente marcado (first_name difere).\n";
      $__bazar_norm_name_inconsistent_set = true;
    }

    $__bazar_norm_last_name_updated = false;
    $__bazar_norm_dob_updated = false;
    $__bazar_norm_dob_minor_skipped = false;
    $__bazar_norm_name_inconsistent_set = $__bazar_norm_name_inconsistent_set ?? false;

    // last_name: fonte de verdade.
    $should_update_last = false;
    if ($api_last_norm !== '') {
      if (trim($user_last) === '' || $user_last_norm !== $api_last_norm) {
        $should_update_last = true;
      }
    }

    if ($should_update_last) {
      $updated = wp_update_user([
        'ID' => $uid,
        'last_name' => $api_last,
      ]);
      if (is_wp_error($updated)) {
        echo "[{$uid}] Falha ao atualizar last_name: " . $updated->get_error_message() . "\n";
      } else {
        echo "[{$uid}] last_name normalizado.\n";
        $__bazar_norm_last_name_updated = true;
      }
    }

    // data_nascimento: preencher ou corrigir (se nao for menor).
    $existing_dob = trim((string) get_user_meta($uid, 'data_nascimento', true));
    if (!empty($api_dob)) {
      $minor = bazar_norm_is_minor_from_api_dob($api_dob);
      $converted = bazar_norm_try_convert_api_dob_to_dd_mm_yyyy($api_dob);

      // Log/eco explícitos para diagnóstico de conversão/skip.
      bazar_norm_log_jsonl($log_event_base + [
        'event' => 'dob_diagnose',
        'api_dob_raw' => $api_dob,
        'existing_dob' => $existing_dob,
        'minor' => $minor,
        'converted' => $converted,
      ]);

      if (!$minor) {
        if ($converted !== '' && ($existing_dob === '' || $existing_dob !== $converted)) {
          update_user_meta($uid, 'data_nascimento', $converted);
          $after = trim((string) get_user_meta($uid, 'data_nascimento', true));
          bazar_norm_log_jsonl($log_event_base + [
            'event' => 'dob_updated',
            'before' => $existing_dob,
            'after' => $after,
          ]);
          echo "[{$uid}] data_nascimento preenchido/corrigido.\n";
          $__bazar_norm_dob_updated = true;
        } elseif ($converted === '') {
          echo "[{$uid}] data_nascimento nao convertido (formato inesperado). api_dob={$api_dob}\n";
          bazar_norm_log_jsonl($log_event_base + [
            'event' => 'dob_skip',
            'reason' => 'converted_empty',
            'api_dob_raw' => $api_dob,
          ]);
        } else {
          echo "[{$uid}] data_nascimento ja está igual (nenhuma atualização).\n";
          bazar_norm_log_jsonl($log_event_base + [
            'event' => 'dob_skip',
            'reason' => 'already_equal',
          ]);
        }
      } else {
        echo "[{$uid}] data_nascimento menor de idade: nao sobrescrevendo.\n";
        $__bazar_norm_dob_minor_skipped = true;
        bazar_norm_log_jsonl($log_event_base + [
          'event' => 'dob_skip',
          'reason' => 'minor',
        ]);
      }
    }

    update_user_meta($uid, 'bazar_norm_cpf_result_last', 'cpf_ok');
    update_user_meta($uid, 'bazar_norm_cpf_normalizado_at', current_time('mysql'));
    update_user_meta($uid, 'bazar_norm_cpf_last_cpf', $cpf_clean);

    // Abrir campos de edição para “usuários antigos” travados:
    // - se o perfil está verificado (bazar_perfil_verificado=true)
    // - e usuario_update ainda não foi desbloqueado
    // então liberamos UMA edição futura e depois o backend travará novamente.
    $__bazar_norm_usuario_update_unlocked = false;
    if (function_exists('bazar_perfil_verificado') && bazar_perfil_verificado($uid)) {
      $usuario_update_raw = get_user_meta($uid, 'usuario_update', true);
      $usuario_update_empty = ($usuario_update_raw === '' || $usuario_update_raw === null);
      $usuario_update_locked = ($usuario_update_raw === 'true' || $usuario_update_raw === true || $usuario_update_raw === '1' || $usuario_update_raw === 1);
      if ($usuario_update_empty || $usuario_update_locked) {
        update_user_meta($uid, 'usuario_update', 'false');
        $__bazar_norm_usuario_update_unlocked = true;
      }
    }

    bazar_norm_log_jsonl($log_event_base + [
      'result' => 'cpf_ok_processado',
      'changed' => [
        'last_name_updated' => $__bazar_norm_last_name_updated,
        'name_inconsistent_set' => $__bazar_norm_name_inconsistent_set,
        'dob_updated' => $__bazar_norm_dob_updated,
        'dob_minor_skipped' => $__bazar_norm_dob_minor_skipped,
        'usuario_update_unlocked' => $__bazar_norm_usuario_update_unlocked,
      ],
    ]);
  }

  echo "Finalizado. Users processados neste run: {$processed_users}\n";
  echo "Cursor salvo em: " . (int) get_option($OPT_CURSOR, 0) . "\n";
  echo "Log JSONL em: {$LOG_FILE}\n";
endif;

