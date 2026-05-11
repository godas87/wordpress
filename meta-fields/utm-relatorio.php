<?php
/**
 * Relatorio de UTM (usuarios, anuncios e boosts).
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

add_action('admin_menu', 'bazar_utm_relatorio_admin_menu');
function bazar_utm_relatorio_admin_menu()
{
  add_management_page(
    'Relatorio UTM',
    'Relatorio UTM',
    'manage_options',
    'bazar-utm-relatorio',
    'bazar_utm_relatorio_render_page'
  );
}

/**
 * Helper de leitura de filtro de data.
 *
 * @param string $key
 * @return string
 */
function bazar_utm_get_date_filter($key)
{
  if (!isset($_GET[$key])) {
    return '';
  }
  $value = sanitize_text_field((string) $_GET[$key]);
  if ($value === '') {
    return '';
  }
  $date = date_create($value);
  return $date ? $date->format('Y-m-d') : '';
}

/**
 * Monta clausula SQL de periodo (datetime).
 *
 * @param string $field
 * @param string $date_from
 * @param string $date_to
 * @return string
 */
function bazar_utm_build_period_clause($field, $date_from, $date_to)
{
  global $wpdb;
  $clauses = array();

  if ($date_from !== '') {
    $clauses[] = $wpdb->prepare("{$field} >= %s", $date_from . ' 00:00:00');
  }
  if ($date_to !== '') {
    $clauses[] = $wpdb->prepare("{$field} <= %s", $date_to . ' 23:59:59');
  }

  if (empty($clauses)) {
    return '';
  }
  return ' AND ' . implode(' AND ', $clauses);
}

function bazar_utm_relatorio_render_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  global $wpdb;

  $date_from = bazar_utm_get_date_filter('date_from');
  $date_to = bazar_utm_get_date_filter('date_to');

  $user_period = bazar_utm_build_period_clause('u.user_registered', $date_from, $date_to);
  $post_period = bazar_utm_build_period_clause('p.post_date', $date_from, $date_to);

  $users_by_campaign = $wpdb->get_results(
    "
      SELECT um.meta_value AS campaign, COUNT(*) AS total_users
      FROM {$wpdb->users} u
      INNER JOIN {$wpdb->usermeta} um
        ON um.user_id = u.ID
       AND um.meta_key = 'bazar_user_utm_campaign'
      WHERE um.meta_value <> ''
      {$user_period}
      GROUP BY um.meta_value
      ORDER BY total_users DESC, campaign ASC
    "
  );

  $ads_by_campaign = $wpdb->get_results(
    "
      SELECT pm.meta_value AS campaign, COUNT(*) AS total_ads
      FROM {$wpdb->posts} p
      INNER JOIN {$wpdb->postmeta} pm
        ON pm.post_id = p.ID
       AND pm.meta_key = 'bazar_ad_utm_campaign'
      WHERE p.post_type = 'post'
        AND p.post_status NOT IN ('auto-draft','trash')
        AND pm.meta_value <> ''
      {$post_period}
      GROUP BY pm.meta_value
      ORDER BY total_ads DESC, campaign ASC
    "
  );

  $boosts_by_campaign = $wpdb->get_results(
    "
      SELECT bcampaign.meta_value AS campaign,
             COUNT(DISTINCT p.ID) AS total_boosts,
             SUM(CAST(COALESCE(amount.meta_value, '0') AS UNSIGNED)) AS total_cents
      FROM {$wpdb->posts} p
      INNER JOIN {$wpdb->postmeta} payment
        ON payment.post_id = p.ID
       AND payment.meta_key = 'destaque_payment_id'
       AND payment.meta_value <> ''
      INNER JOIN {$wpdb->postmeta} bcampaign
        ON bcampaign.post_id = p.ID
       AND bcampaign.meta_key = 'bazar_boost_utm_campaign'
       AND bcampaign.meta_value <> ''
      LEFT JOIN {$wpdb->postmeta} amount
        ON amount.post_id = p.ID
       AND amount.meta_key = 'destaque_amount_total_cents'
      WHERE p.post_type = 'post'
        AND p.post_status NOT IN ('auto-draft','trash')
      {$post_period}
      GROUP BY bcampaign.meta_value
      ORDER BY total_boosts DESC, campaign ASC
    "
  );

  ?>
  <div class="wrap">
    <h1>Relatorio UTM</h1>
    <p>Visao desacoplada entre aquisicao de usuario, criacao de anuncio e impulsionamento.</p>

    <form method="get" style="margin:16px 0;padding:12px;background:#fff;border:1px solid #ccd0d4;">
      <input type="hidden" name="page" value="bazar-utm-relatorio">
      <label for="date_from"><strong>De:</strong></label>
      <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>"
        style="margin-right:12px;">
      <label for="date_to"><strong>Ate:</strong></label>
      <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="margin-right:12px;">
      <button type="submit" class="button button-primary">Filtrar</button>
      <a class="button" href="<?php echo esc_url(admin_url('tools.php?page=bazar-utm-relatorio')); ?>">Limpar</a>
    </form>

    <h2>1) Usuarios por campanha (cadastro / first-touch)</h2>
    <?php bazar_utm_render_table($users_by_campaign, 'campaign', 'total_users', 'Usuarios'); ?>

    <h2>2) Anuncios por campanha (criacao do anuncio)</h2>
    <?php bazar_utm_render_table($ads_by_campaign, 'campaign', 'total_ads', 'Anuncios'); ?>

    <h2>3) Boosts pagos por campanha (impulsionamento)</h2>
    <?php bazar_utm_render_boost_table($boosts_by_campaign); ?>
  </div>
  <?php
}

/**
 * Tabela simples de campanha x total.
 *
 * @param array $rows
 * @param string $campaign_key
 * @param string $total_key
 * @param string $total_label
 * @return void
 */
function bazar_utm_render_table($rows, $campaign_key, $total_key, $total_label)
{
  if (empty($rows)) {
    echo '<p><em>Sem dados para o periodo informado.</em></p>';
    return;
  }
  echo '<table class="widefat striped" style="max-width:840px;"><thead><tr><th>Campanha</th><th>' . esc_html($total_label) . '</th></tr></thead><tbody>';
  foreach ($rows as $row) {
    $campaign = esc_html((string) ($row->$campaign_key ?? ''));
    $total = (int) ($row->$total_key ?? 0);
    echo '<tr><td>' . $campaign . '</td><td>' . number_format_i18n($total) . '</td></tr>';
  }
  echo '</tbody></table>';
}

/**
 * Tabela de boost com receita.
 *
 * @param array $rows
 * @return void
 */
function bazar_utm_render_boost_table($rows)
{
  if (empty($rows)) {
    echo '<p><em>Sem dados para o periodo informado.</em></p>';
    return;
  }
  echo '<table class="widefat striped" style="max-width:1000px;"><thead><tr><th>Campanha</th><th>Boosts pagos</th><th>Receita (BRL)</th></tr></thead><tbody>';
  foreach ($rows as $row) {
    $campaign = esc_html((string) ($row->campaign ?? ''));
    $total_boosts = (int) ($row->total_boosts ?? 0);
    $total_cents = (int) ($row->total_cents ?? 0);
    $revenue = $total_cents / 100;
    echo '<tr><td>' . $campaign . '</td><td>' . number_format_i18n($total_boosts) . '</td><td>R$ ' . esc_html(number_format($revenue, 2, ',', '.')) . '</td></tr>';
  }
  echo '</tbody></table>';
}
