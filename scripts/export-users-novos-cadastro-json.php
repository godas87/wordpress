<?php
/**
 * Exporta usuários "novos" (user_registered >= cutoff) em JSON.
 *
 * Ordenação: mais recente -> mais antigo.
 *
 * Inclui:
 * - user data (ID, user_registered, login, email)
 * - usermeta relevantes (cpf, data_nascimento, nome, contato/localização, flags de controle, UTM)
 * - contagem de posts do tipo `post` e de `ads` (publish e total)
 *
 * Uso (CLI):
 * php.exe scripts/export-users-novos-cadastro-json.php --cutoff=2026-02-19
 *
 * Uso (Browser):
 * acessa via URL passando ?cutoff=YYYY-MM-DD
 *
 * @package XXXXXX
 */

// Bootstrap do WordPress
if (!defined('ABSPATH')) {
  // Sobe diretórios até encontrar wp-load.php
  $current = __DIR__;
  $found = false;
  for ($i = 0; $i < 8; $i++) {
    $candidate = $current . '/wp-load.php';
    if (file_exists($candidate)) {
      require_once $candidate;
      $found = true;
      break;
    }
    $parent = dirname($current);
    if ($parent === $current) {
      break;
    }
    $current = $parent;
  }

  if (!$found) {
    exit("wp-load.php nao encontrado.\n");
  }
}

function bazar_export_is_profile_complete($meta)
{
  $first_name = trim((string) ($meta['first_name'] ?? ''));
  $last_name = trim((string) ($meta['last_name'] ?? ''));
  $cpf = trim((string) ($meta['cpf'] ?? ''));
  $dob = trim((string) ($meta['data_nascimento'] ?? ''));
  $fone = trim((string) ($meta['fone'] ?? ''));
  $cep = trim((string) ($meta['cep'] ?? ''));
  $bairro = trim((string) ($meta['bairro'] ?? ''));
  $cidade = trim((string) ($meta['cidade'] ?? ''));
  $estado_sigla = trim((string) ($meta['estado_sigla'] ?? ''));

  if (empty($estado_sigla)) {
    $estado_sigla = trim((string) ($meta['estado-sigla'] ?? ''));
  }

  return !(
    $first_name === '' ||
    $last_name === '' ||
    $cpf === '' ||
    $dob === '' ||
    $fone === '' ||
    $cep === '' ||
    $bairro === '' ||
    $cidade === '' ||
    $estado_sigla === ''
  );
}

function bazar_export_is_bool_meta($value)
{
  return ($value === 'true' || $value === true || $value === '1' || $value === 1);
}

function bazar_export_users_novos_cadastro_payload($cutoff)
{
  $cutoff_sql = (string) $cutoff . ' 00:00:00';
  $generated_at = function_exists('current_time') ? current_time('mysql') : date('c');

  global $wpdb;
  if (!$wpdb) {
    return [
      'error' => 'wpdb indisponivel',
      'cutoff' => $cutoff,
    ];
  }

  // Pegar IDs e datas ordenadas
  $users_rows = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT ID, user_registered, user_login, user_email, display_name
       FROM {$wpdb->users}
       WHERE user_registered >= %s
       ORDER BY user_registered DESC",
      $cutoff_sql
    )
  );

  $user_ids = [];
  if (is_array($users_rows)) {
    foreach ($users_rows as $row) {
      $user_ids[] = (int) $row->ID;
    }
  }
  $user_ids = array_values(array_filter($user_ids));

  // Contagens em lote (posts e ads) para reduzir N queries
  $posts_counts = [];
  $ads_counts = [];

  if (!empty($user_ids)) {
    $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
    $in_args = $user_ids;

    $sql_posts = $wpdb->prepare(
      "SELECT post_author,
              SUM(CASE WHEN post_status = 'publish' THEN 1 ELSE 0 END) AS publish_count,
              COUNT(*) AS total_count
       FROM {$wpdb->posts}
       WHERE post_author IN ($placeholders)
         AND post_type = 'post'
       GROUP BY post_author",
      ...$in_args
    );

    $sql_ads = $wpdb->prepare(
      "SELECT post_author,
              SUM(CASE WHEN post_status = 'publish' THEN 1 ELSE 0 END) AS publish_count,
              COUNT(*) AS total_count
       FROM {$wpdb->posts}
       WHERE post_author IN ($placeholders)
         AND post_type = 'ads'
       GROUP BY post_author",
      ...$in_args
    );

    $rows_posts = $wpdb->get_results($sql_posts);
    if (is_array($rows_posts)) {
      foreach ($rows_posts as $r) {
        $posts_counts[(int) $r->post_author] = [
          'publish' => (int) $r->publish_count,
          'total' => (int) $r->total_count,
        ];
      }
    }

    $rows_ads = $wpdb->get_results($sql_ads);
    if (is_array($rows_ads)) {
      foreach ($rows_ads as $r) {
        $ads_counts[(int) $r->post_author] = [
          'publish' => (int) $r->publish_count,
          'total' => (int) $r->total_count,
        ];
      }
    }
  }

  $users_out = [];
  if (!is_array($users_rows)) {
    $users_rows = [];
  }

  foreach ($users_rows as $row) {
    $uid = (int) $row->ID;

    // Metas
    $meta = [
      'cpf' => get_user_meta($uid, 'cpf', true),
      'data_nascimento' => get_user_meta($uid, 'data_nascimento', true),
      'fone' => get_user_meta($uid, 'fone', true),
      'cep' => get_user_meta($uid, 'cep', true),
      'bairro' => get_user_meta($uid, 'bairro', true),
      'cidade' => get_user_meta($uid, 'cidade', true),
      'estado_sigla' => get_user_meta($uid, 'estado_sigla', true),
      'estado-sigla' => get_user_meta($uid, 'estado-sigla', true),
      'whatsapp_ativo' => get_user_meta($uid, 'whatsapp_ativo', true),
      'usuario_update' => get_user_meta($uid, 'usuario_update', true),
      'bazar_perfil_verificado' => get_user_meta($uid, 'bazar_perfil_verificado', true),
      'ativar_email' => get_user_meta($uid, 'ativar_email', true),
      'profile_updated' => get_user_meta($uid, 'profile_updated', true),
      'bazar_user_blocked' => get_user_meta($uid, 'bazar_user_blocked', true),
      'bazar_user_cancelled' => get_user_meta($uid, 'bazar_user_cancelled', true),
      'first_name' => get_user_meta($uid, 'first_name', true),
      'last_name' => get_user_meta($uid, 'last_name', true),
      'bazar_user_utm_source' => get_user_meta($uid, 'bazar_user_utm_source', true),
      'bazar_user_utm_medium' => get_user_meta($uid, 'bazar_user_utm_medium', true),
      'bazar_user_utm_campaign' => get_user_meta($uid, 'bazar_user_utm_campaign', true),
      'bazar_user_utm_content' => get_user_meta($uid, 'bazar_user_utm_content', true),
      'bazar_user_utm_captured_at' => get_user_meta($uid, 'bazar_user_utm_captured_at', true),
    ];

    $is_complete = bazar_export_is_profile_complete($meta);
    $is_verified = bazar_export_is_bool_meta($meta['bazar_perfil_verificado']);

    $users_out[] = [
      'ID' => $uid,
      'user_registered' => (string) $row->user_registered,
      'user_login' => (string) ($row->user_login ?? ''),
      'user_email' => (string) ($row->user_email ?? ''),
      'display_name' => (string) ($row->display_name ?? ''),
      'first_name' => (string) ($meta['first_name'] ?? ''),
      'last_name' => (string) ($meta['last_name'] ?? ''),

      'cpf' => (string) ($meta['cpf'] ?? ''),
      'data_nascimento' => (string) ($meta['data_nascimento'] ?? ''),
      'fone' => (string) ($meta['fone'] ?? ''),
      'cep' => (string) ($meta['cep'] ?? ''),
      'bairro' => (string) ($meta['bairro'] ?? ''),
      'cidade' => (string) ($meta['cidade'] ?? ''),
      'estado_sigla' => (string) ($meta['estado_sigla'] ?? ($meta['estado-sigla'] ?? '')),
      'whatsapp_ativo' => bazar_export_is_bool_meta($meta['whatsapp_ativo']),

      'usuario_update' => (string) ($meta['usuario_update'] ?? ''),
      'bazar_perfil_verificado' => $is_verified,
      'perfil_completo' => $is_complete,
      'ativar_email' => bazar_export_is_bool_meta($meta['ativar_email']),

      'bazar_user_blocked' => bazar_export_is_bool_meta($meta['bazar_user_blocked']),
      'bazar_user_cancelled' => bazar_export_is_bool_meta($meta['bazar_user_cancelled']),
      'utm' => [
        'source' => (string) ($meta['bazar_user_utm_source'] ?? ''),
        'medium' => (string) ($meta['bazar_user_utm_medium'] ?? ''),
        'campaign' => (string) ($meta['bazar_user_utm_campaign'] ?? ''),
        'content' => (string) ($meta['bazar_user_utm_content'] ?? ''),
        'captured_at' => (string) ($meta['bazar_user_utm_captured_at'] ?? ''),
      ],

      'posts_post' => $posts_counts[$uid] ?? ['publish' => 0, 'total' => 0],
      'posts_ads' => $ads_counts[$uid] ?? ['publish' => 0, 'total' => 0],
    ];
  }

  return [
    'cutoff' => $cutoff,
    'generated_at' => $generated_at,
    'users_count' => count($users_out),
    'users' => $users_out,
  ];
}

function bazar_export_users_novos_cadastro_output_json($payload, $pretty = true)
{
  $json = json_encode(
    $payload,
    $pretty ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
  );
  if (!is_string($json)) {
    exit("Falha ao serializar JSON.\n");
  }
  return $json;
}

function bazar_export_users_novos_cadastro_write_file($payload, $cutoff)
{
  $out_dir = get_template_directory() . '/scripts/data';
  if (!is_dir($out_dir)) {
    @mkdir($out_dir, 0755, true);
  }

  $out_file = $out_dir . '/usuarios-novos-cadastro-' . $cutoff . '.json';
  $json = bazar_export_users_novos_cadastro_output_json($payload, true);
  file_put_contents($out_file, $json);
  return $out_file;
}

// ------------------------------------------------------------
// Painel admin (como export-taxonomies-json.php)
// ------------------------------------------------------------
function bazar_add_export_users_novos_cadastro_menu()
{
  add_management_page(
    'Exportar Usuários Novos JSON',
    'Exportar Usuários Novos',
    'manage_options',
    'bazar-export-users-novos-cadastro',
    'bazar_export_users_novos_cadastro_page'
  );
}
add_action('admin_menu', 'bazar_add_export_users_novos_cadastro_menu');

function bazar_export_users_novos_cadastro_json_handler()
{
  if (!isset($_GET['bazar_export_users_novos_cadastro_json'])) {
    return;
  }
  if (!current_user_can('manage_options')) {
    wp_die('Sem permissao.');
  }

  $cutoff = !empty($_GET['cutoff']) ? (string) $_GET['cutoff'] : '2026-02-19';
  if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $cutoff)) {
    $cutoff = '2026-02-19';
  }

  $pretty = isset($_GET['pretty']) && $_GET['pretty'] == '0' ? false : true;

  $payload = bazar_export_users_novos_cadastro_payload($cutoff);
  if (isset($payload['error'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo bazar_export_users_novos_cadastro_output_json($payload, true);
    exit;
  }

  if (isset($_GET['save']) && $_GET['save'] === '1') {
    bazar_export_users_novos_cadastro_write_file($payload, $cutoff);
  }

  header('Content-Type: application/json; charset=utf-8');
  echo bazar_export_users_novos_cadastro_output_json($payload, $pretty);
  exit;
}
add_action('admin_init', 'bazar_export_users_novos_cadastro_json_handler');

function bazar_export_users_novos_cadastro_page()
{
  $selected_cutoff = !empty($_GET['cutoff']) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', (string) $_GET['cutoff'])
    ? (string) $_GET['cutoff']
    : '2026-02-19';

  $payload = bazar_export_users_novos_cadastro_payload($selected_cutoff);
  $json_data = isset($payload['error'])
    ? bazar_export_users_novos_cadastro_output_json($payload, true)
    : bazar_export_users_novos_cadastro_output_json($payload, true);

  ?>
  <div class="wrap">
    <h1>Exportar Usuários Novos em JSON</h1>

    <div class="card" style="background:#fff;padding:16px;margin-bottom:16px;">
      <h2>Filtros</h2>
      <form method="get" action="">
        <input type="hidden" name="page" value="bazar-export-users-novos-cadastro" />
        <table class="form-table">
          <tr>
            <th scope="row"><label for="cutoff">Cutoff (YYYY-MM-DD)</label></th>
            <td>
              <input id="cutoff" name="cutoff" type="text" value="<?php echo esc_attr($selected_cutoff); ?>" style="min-width:220px;" />
            </td>
          </tr>
        </table>
        <p class="submit">
          <button type="submit" class="button button-primary">Atualizar Visualização</button>
        </p>
      </form>
      <p>
        <strong>JSON:</strong>
        <a href="<?php echo admin_url('admin.php?page=bazar-export-users-novos-cadastro&bazar_export_users_novos_cadastro_json=1&cutoff=' . urlencode($selected_cutoff) . '&pretty=1'); ?>" class="button" target="_blank">Abrir JSON</a>
        <a href="<?php echo admin_url('admin.php?page=bazar-export-users-novos-cadastro&bazar_export_users_novos_cadastro_json=1&cutoff=' . urlencode($selected_cutoff) . '&pretty=1&save=1'); ?>" class="button" target="_blank">Salvar + Abrir</a>
      </p>
    </div>

    <div class="card" style="background:#fff;padding:16px;">
      <h2>Resultado</h2>
      <?php
      if (isset($payload['error'])) {
        echo '<p style="color:red;">' . esc_html($payload['error']) . '</p>';
      } else {
        echo '<p><strong>Usuários exportados:</strong> ' . (int) ($payload['users_count'] ?? 0) . '</p>';
      }
      ?>
      <pre id="json-output" style="background:#f5f5f5;padding:15px;border:1px solid #ddd;border-radius:4px;overflow-x:auto;max-height:600px;overflow-y:auto;font-family:Courier New, monospace;font-size:12px;line-height:1.4;"><?php echo esc_html($json_data); ?></pre>
    </div>
  </div>
  <?php
}

// ------------------------------------------------------------
// Execucao CLI/Browser (sem interferir no admin)
// ------------------------------------------------------------
$cutoff = '2026-02-19';
if (php_sapi_name() === 'cli') {
  $argv_ = $_SERVER['argv'] ?? [];
  foreach ($argv_ as $arg) {
    if (preg_match('/--cutoff=([0-9]{4}-[0-9]{2}-[0-9]{2})/', (string) $arg, $m)) {
      $cutoff = (string) $m[1];
    }
  }

  $payload = bazar_export_users_novos_cadastro_payload($cutoff);
  $out_file = bazar_export_users_novos_cadastro_write_file($payload, $cutoff);

  echo "OK. Arquivo: {$out_file}\n";
  echo "Usuarios exportados: " . (int) ($payload['users_count'] ?? 0) . "\n";
  return;
}

if (!is_admin() && !empty($_GET['cutoff'])) {
  $cutoff_candidate = (string) $_GET['cutoff'];
  if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $cutoff_candidate)) {
    $cutoff = $cutoff_candidate;
  }

  $payload = bazar_export_users_novos_cadastro_payload($cutoff);
  $out_file = bazar_export_users_novos_cadastro_write_file($payload, $cutoff);
  echo "OK. Arquivo: {$out_file}\n";
}

