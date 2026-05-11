<?php
/**
 * Reengajamento de novos cadastros sem conversão em anúncio.
 *
 * Regras:
 * - Janela de data: user_registered >= 2026-02-19 00:00:00 (GMT do WP), sem data final — inclui todo cadastro desde essa data “até agora”
 * - Segmento A: criou anúncio (post_type=post), mas ainda falta concluir dados (perfil incompleto e/ou e-mail não confirmado)
 * - Segmento B: cadastrou e não criou anúncio (inclui quem ainda não confirmou o e-mail — cópia reforça confirmação quando aplicável)
 * - Envio (fila/cron): enquanto houver pendentes, 1 lead por minuto; quando a fila não tiver mais pendentes o evento de cron é removido (não fica agendado à toa)
 * - E-mail em HTML simples (text/html): quebras como <br> para clientes que ignoram texto puro
 * - WhatsApp: sem API; link wa.me com texto do modelo (abertura manual pelo time)
 * - Admin: envio dos modelos A/B para um e-mail e geração de links wa.me (mesmos textos da produção)
 * - Saudação sem nome: “Olá Teste…” só quando o compose é chamado pelo formulário de teste do admin; na fila/cron, sem nome fica em branco
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

define('BAZAR_REENGAGE_CUTOFF_YMD', '2026-02-19');
define('BAZAR_REENGAGE_OPTION_QUEUE', 'bazar_reengage_queue');
define('BAZAR_REENGAGE_OPTION_STATS', 'bazar_reengage_stats');
define('BAZAR_REENGAGE_CRON_HOOK', 'bazar_reengage_send_tick');
define('BAZAR_REENGAGE_META_SENT_AT', 'bazar_reengage_email_sent_at');
define('BAZAR_REENGAGE_SUPPORT_WA_DISPLAY', 'XXXXXX');
define('BAZAR_REENGAGE_MAIL_MINHA_CONTA_TOKEN', '[[BAZAR_REENGAGE_MINHA_CONTA]]');
define('BAZAR_REENGAGE_MAIL_CONFIRMAR_EMAIL_TOKEN', '[[BAZAR_REENGAGE_CONFIRMAR_EMAIL]]');

function bazar_reengage_bool_meta($v)
{
  return ($v === 'true' || $v === true || $v === '1' || $v === 1);
}

function bazar_reengage_headers_html()
{
  return array(
    'Content-Type: text/html; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: Bazar Bikes <XXXXXX>',
  );
}

/**
 * Trecho de texto do e-mail: escapa, linkifica URLs soltas, quebras em <br>.
 */
function bazar_reengage_mail_body_html_chunk($chunk)
{
  $safe = esc_html((string) $chunk, ENT_QUOTES, 'UTF-8');
  if (function_exists('make_clickable')) {
    $safe = make_clickable($safe);
  }
  return nl2br($safe, false);
}

/**
 * Corpo HTML mínimo; no lead quente, troca token por link "Minha Conta" (href limpo).
 */
function bazar_reengage_mail_body_html($body)
{
  $body = str_replace(array("\r\n", "\r"), "\n", (string) $body);
  $token_mc = BAZAR_REENGAGE_MAIL_MINHA_CONTA_TOKEN;
  $token_ce = BAZAR_REENGAGE_MAIL_CONFIRMAR_EMAIL_TOKEN;
  // Um modelo usa só um token por e-mail; tratar antes de misturar HTML com nl2br.
  if (strpos($body, $token_mc) !== false) {
    $parts = explode($token_mc, $body, 2);
    $link = '<a href="' . esc_url(home_url('/minha-conta/')) . '">Minha Conta</a>';
    return bazar_reengage_mail_body_html_chunk($parts[0]) . $link . bazar_reengage_mail_body_html_chunk($parts[1] ?? '');
  }
  if (strpos($body, $token_ce) !== false) {
    $parts = explode($token_ce, $body, 2);
    $link = '<a href="' . esc_url(home_url('/confirmar-email/')) . '">Confirmar e-mail</a>';
    return bazar_reengage_mail_body_html_chunk($parts[0]) . $link . bazar_reengage_mail_body_html_chunk($parts[1] ?? '');
  }
  return bazar_reengage_mail_body_html_chunk($body);
}

/**
 * Primeira linha do e-mail: "Oi Nome, Tudo Bem?" ou, sem nome, "Olá Teste…" só se $admin_test_form for true.
 */
function bazar_reengage_email_opening($first_name, $admin_test_form = false)
{
  $first_name = trim((string) $first_name);
  if ($first_name === '') {
    return $admin_test_form ? 'Olá, Teste!' : '';
  }
  return "Oi {$first_name}, Tudo Bem?";
}

/**
 * Trecho antes de “Aqui é o Pedro…” no WhatsApp: com nome “Oi Nome, tudo bem?”; sem nome, “Olá Teste…” só se $admin_test_form.
 *
 * @param string $name Nome já resolvido (ex.: bazar_reengage_user_greeting_name).
 */
function bazar_reengage_whatsapp_opening($name, $admin_test_form = false)
{
  $name = trim((string) $name);
  if ($name === '') {
    return $admin_test_form ? 'Olá, Teste!' : '';
  }
  return "Oi {$name}, tudo bem?";
}

/**
 * Junta saudação opcional com a frase do Pedro (sem espaço inicial quando a saudação é vazia).
 */
function bazar_reengage_whatsapp_opening_plus_pedro($name, $admin_test_form = false)
{
  $open = bazar_reengage_whatsapp_opening($name, $admin_test_form);
  $pedro = 'Aqui é o Pedro, responsável pelo Bazar Bikes.';
  if ($open === '') {
    return $pedro;
  }
  return "{$open} {$pedro}";
}

/**
 * Nome para saudação (e-mail e WhatsApp): primeiro nome; senão nome completo; senão display_name; senão parte do e-mail.
 *
 * @param WP_User|null $user
 * @return string
 */
function bazar_reengage_user_greeting_name($user)
{
  if (!$user instanceof WP_User) {
    return '';
  }
  $fn = trim((string) $user->first_name);
  if ($fn !== '') {
    return sanitize_text_field($fn);
  }
  $ln = trim((string) $user->last_name);
  $full = trim($fn . ' ' . $ln);
  if ($full !== '') {
    return sanitize_text_field($full);
  }
  $dn = trim((string) $user->display_name);
  if ($dn !== '') {
    return sanitize_text_field($dn);
  }
  $nick = trim((string) get_user_meta($user->ID, 'nickname', true));
  if ($nick !== '') {
    return sanitize_text_field($nick);
  }
  $login = trim((string) $user->user_login);
  if ($login !== '' && strpos($login, '@') === false) {
    return sanitize_text_field($login);
  }
  $email = (string) $user->user_email;
  if ($email !== '' && strpos($email, '@') !== false) {
    $local = trim(explode('@', $email, 2)[0]);
    $local = str_replace(array('.', '_', '+'), ' ', $local);
    $local = preg_replace('/\s+/', ' ', $local);
    if ($local !== '') {
      return sanitize_text_field($local);
    }
  }
  return '';
}

/**
 * Normaliza telefone BR para dígitos com DDI 55 (wa.me). Retorna string vazia se inválido.
 */
function bazar_reengage_phone_digits_br($fone_raw)
{
  $d = preg_replace('/\D+/', '', (string) $fone_raw);
  if ($d === '') {
    return '';
  }
  if (strlen($d) >= 12 && substr($d, 0, 2) === '55') {
    return $d;
  }
  $len = strlen($d);
  if ($len === 10 || $len === 11) {
    return '55' . $d;
  }
  return '';
}

/**
 * URL wa.me para iniciar conversa COM o número do usuário (você abre o link e envia a mensagem).
 * Quebras: normaliza para \r\n antes de codificar — WhatsApp costuma respeitar %0D%0A no parâmetro text.
 */
function bazar_reengage_wa_me_url_for_lead($phone_digits_55, $message)
{
  $phone_digits_55 = preg_replace('/\D+/', '', (string) $phone_digits_55);
  if ($phone_digits_55 === '') {
    return '';
  }
  $message = str_replace(array("\r\n", "\r"), "\n", (string) $message);
  $message = str_replace("\n", "\r\n", $message);
  return 'https://wa.me/' . $phone_digits_55 . '?text=' . rawurlencode($message);
}

/**
 * Escapa href de wa.me sem usar esc_url(): o esc_url() do WP remove A–F em %0A/%0D e quebra as linhas na mensagem.
 */
function bazar_reengage_esc_href_wa_me($url)
{
  if (!is_string($url) || strpos($url, 'https://wa.me/') !== 0) {
    return '';
  }
  return esc_attr($url);
}

/**
 * Textos alinhados a scripts/EMAILS.txt (Lead Quente = segmento A, Lead Frio = B).
 *
 * @param bool $admin_test_form true só no formulário de teste do admin (nome vazio → “Olá Teste…”).
 */
function bazar_reengage_compose_email($segment, $name, $has_email_pending = false, $has_profile_pending = false, $admin_test_form = false)
{
  $open = bazar_reengage_email_opening($name, $admin_test_form);
  $wa_display = BAZAR_REENGAGE_SUPPORT_WA_DISPLAY;
  $lead = ($open !== '' ? $open . "\n" : '') . 'Meu nome é Pedro e sou o responsável pelo Bazar Bikes.' . "\n\n";

  if ($segment === 'A') {
    $subject = 'Vamos finalizar seu anúncio?';
    $body = $lead;
    $body .= "Seu anúncio está quase no ar! Notei que falta apenas um detalhe no seu perfil (como confirmação de e-mail ou endereço) para liberar a publicação automática.\n\n";
    $body .= "Acesse Minha Conta no link abaixo para finalizar. Se precisar de ajuda com os dados, é só me chamar por aqui!\n\n";
    $body .= BAZAR_REENGAGE_MAIL_MINHA_CONTA_TOKEN . "\n\n";
    $body .= "Se preferir pode entrar em contato também através do Whatsapp {$wa_display}.\n\n";
    $body .= "--\n";
    $body .= "Equipe Bazar Bikes,\n";
    $body .= "Bons Negócios\n";
    $body .= "{$wa_display}\n";
    return array($subject, $body);
  }

  $subject = 'Anúncie grátis no Bazar Bikes';
  $body = $lead;
  if ($has_email_pending) {
    $body .= "Vi que você criou sua conta, mas ainda falta confirmar seu e-mail para ativar tudo certinho por aqui.\n\n";
    $body .= 'É rápido: use o link ' . BAZAR_REENGAGE_MAIL_CONFIRMAR_EMAIL_TOKEN . " (ou peça reenvio do e-mail na mesma página, se precisar).\n\n";
  }
  $body .= "No XXXXXX você fica com 100% do valor da venda (não cobramos taxas ou comissões).\n\n";
  $body .= "Ficou com alguma dúvida sobre como anunciar seu produto? Se quiser, me responda aqui dizendo se teve algum problema que eu te ajudo pessoalmente a colocar seu anúncio no ar.\n\n";
  $body .= "Se preferir pode entrar em contato também através do Whatsapp {$wa_display}.\n\n";
  $body .= "--\n";
  $body .= "Equipe Bazar Bikes,\n";
  $body .= "Bons Negócios\n";
  $body .= "{$wa_display}\n";
  return array($subject, $body);
}

/**
 * Mensagem para colar em wa.me (scripts/EMAILS.txt — blocos WHATSAPP).
 *
 * @param bool $admin_test_form true só no formulário de teste do admin (nome vazio → “Olá Teste…”).
 */
function bazar_reengage_compose_whatsapp_message($segment, $name, $has_email_pending = false, $admin_test_form = false)
{
  $head = bazar_reengage_whatsapp_opening_plus_pedro($name, $admin_test_form);

  $minha_conta_url = home_url('/minha-conta/');

  if ($segment === 'A') {
    return "{$head}\n\n"
      . "Notei que seu anúncio está quase pronto, mas travou em um detalhe do perfil (provavelmente confirmação de e-mail ou endereço).\n\n"
      . "Quer que eu te ajude a liberar por aqui? Se preferir, é só finalizar por este link:\n\n"
      . "{$minha_conta_url}\n\n"
      . 'Qualquer coisa é só me chamar!';
  }

  $extra = '';
  if ($segment === 'B' && $has_email_pending) {
    $extra = 'Se ainda não confirmou o e-mail de cadastro, entra em ' . home_url('/confirmar-email/') . " que resolve em um clique.\n\n";
  }

  return "{$head}\n\n"
    . $extra
    . "Vi que você criou sua conta no site! Só passando para lembrar que aqui você anuncia e fica com 100% do valor da venda (não cobramos nenhuma taxa ou comissão).\n\n"
    . "Ficou alguma dúvida sobre como postar sua bike ou teve algum problema no cadastro? Se quiser, me manda por aqui que eu te ajudo agora mesmo.\n\n"
    . 'Abraço!';
}

function bazar_reengage_get_user_ids_since_cutoff($cutoff_ymd = BAZAR_REENGAGE_CUTOFF_YMD)
{
  global $wpdb;
  if (!$wpdb) {
    return array();
  }
  $cutoff_sql = $cutoff_ymd . ' 00:00:00';
  $ids = $wpdb->get_col(
    $wpdb->prepare(
      "SELECT ID FROM {$wpdb->users} WHERE user_registered >= %s ORDER BY ID ASC",
      $cutoff_sql
    )
  );
  return is_array($ids) ? array_map('intval', $ids) : array();
}

function bazar_reengage_get_post_counts_map($user_ids)
{
  global $wpdb;
  $map = array();
  if (!$wpdb || empty($user_ids)) {
    return $map;
  }
  $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
  $sql = $wpdb->prepare(
    "SELECT post_author, COUNT(*) AS total_posts
     FROM {$wpdb->posts}
     WHERE post_author IN ($placeholders)
       AND post_type = 'post'
       AND post_status NOT IN ('trash','auto-draft')
     GROUP BY post_author",
    ...$user_ids
  );
  $rows = $wpdb->get_results($sql);
  if (is_array($rows)) {
    foreach ($rows as $r) {
      $map[(int) $r->post_author] = (int) $r->total_posts;
    }
  }
  return $map;
}

function bazar_reengage_build_queue($force_regenerate = false)
{
  $ids = bazar_reengage_get_user_ids_since_cutoff();
  $post_counts = bazar_reengage_get_post_counts_map($ids);
  $queue = array();

  $skip = array(
    'sem_usuario' => 0,
    'email_invalido' => 0,
    'cancelado' => 0,
    'bloqueado' => 0,
    'ja_recebeu_email' => 0,
    'fora_segmento' => 0,
  );

  foreach ($ids as $uid) {
    $user = get_userdata($uid);
    if (!$user) {
      $skip['sem_usuario']++;
      continue;
    }

    $email = trim((string) ($user->user_email ?? ''));
    if ($email === '' || !is_email($email)) {
      $skip['email_invalido']++;
      continue;
    }

    if (bazar_reengage_bool_meta(get_user_meta($uid, 'bazar_user_cancelled', true))) {
      $skip['cancelado']++;
      continue;
    }
    if (bazar_reengage_bool_meta(get_user_meta($uid, 'bazar_user_blocked', true))) {
      $skip['bloqueado']++;
      continue;
    }

    if (!$force_regenerate && get_user_meta($uid, BAZAR_REENGAGE_META_SENT_AT, true)) {
      $skip['ja_recebeu_email']++;
      continue;
    }

    $posts_total = (int) ($post_counts[$uid] ?? 0);
    $perfil_ok = function_exists('bazar_perfil_completo') ? bazar_perfil_completo($uid) : true;
    $email_ok = function_exists('bazar_usuario_email_confirmado_meta')
      ? bazar_usuario_email_confirmado_meta($uid)
      : true;

    $segment = '';
    if ($posts_total > 0 && (!$perfil_ok || !$email_ok)) {
      $segment = 'A';
    } elseif ($posts_total === 0) {
      $segment = 'B';
    } else {
      $skip['fora_segmento']++;
      continue;
    }

    $fone = trim((string) get_user_meta($uid, 'fone', true));
    $wa_user_ok = bazar_reengage_bool_meta(get_user_meta($uid, 'whatsapp_ativo', true))
      && bazar_reengage_phone_digits_br($fone) !== '';

    $queue[] = array(
      'user_id' => (int) $uid,
      'email' => $email,
      'name' => bazar_reengage_user_greeting_name($user),
      'fone' => $fone,
      'wa_contact_ok' => $wa_user_ok,
      'segment' => $segment,
      'has_email_pending' => !$email_ok,
      'has_profile_pending' => !$perfil_ok,
      'status' => 'pending',
      'attempts' => 0,
      'last_error' => '',
      'sent_at' => '',
    );
  }

  $wa_count = count(array_filter($queue, function ($i) {
    return !empty($i['wa_contact_ok']);
  }));

  update_option(BAZAR_REENGAGE_OPTION_QUEUE, $queue, false);
  update_option(BAZAR_REENGAGE_OPTION_STATS, array(
    'generated_at' => current_time('mysql'),
    'cutoff_ymd' => BAZAR_REENGAGE_CUTOFF_YMD,
    'usuarios_desde_cutoff' => count($ids),
    'pulos_geracao' => $skip,
    'total' => count($queue),
    'pending' => count($queue),
    'sent' => 0,
    'failed' => 0,
    'segment_a' => count(array_filter($queue, function ($i) {
      return $i['segment'] === 'A';
    })),
    'segment_b' => count(array_filter($queue, function ($i) {
      return $i['segment'] === 'B';
    })),
    'com_email_pendente' => count(array_filter($queue, function ($i) {
      return !empty($i['has_email_pending']);
    })),
    'com_whatsapp' => $wa_count,
  ), false);

  return $queue;
}

/**
 * Quantidade de itens com status pending na fila.
 */
function bazar_reengage_count_pending()
{
  $queue = get_option(BAZAR_REENGAGE_OPTION_QUEUE, array());
  if (!is_array($queue)) {
    return 0;
  }
  $n = 0;
  foreach ($queue as $item) {
    if (($item['status'] ?? '') === 'pending') {
      $n++;
    }
  }
  return $n;
}

function bazar_reengage_send_one_from_queue()
{
  $queue = get_option(BAZAR_REENGAGE_OPTION_QUEUE, array());
  if (empty($queue) || !is_array($queue)) {
    return array('processed' => false, 'reason' => 'empty_queue');
  }

  $idx = null;
  foreach ($queue as $k => $item) {
    if (($item['status'] ?? '') === 'pending') {
      $idx = $k;
      break;
    }
  }
  if ($idx === null) {
    return array('processed' => false, 'reason' => 'no_pending');
  }

  $item = $queue[$idx];
  $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;

  $uid_item = (int) ($item['user_id'] ?? 0);
  $user_send = $uid_item > 0 ? get_userdata($uid_item) : false;
  $greeting_name = $user_send
    ? bazar_reengage_user_greeting_name($user_send)
    : trim((string) ($item['name'] ?? ''));
  if ($greeting_name === '') {
    $greeting_name = trim((string) ($item['name'] ?? ''));
  }

  $uid_send = (int) $item['user_id'];
  $fone_live = trim((string) get_user_meta($uid_send, 'fone', true));
  $wa_live_ok = bazar_reengage_bool_meta(get_user_meta($uid_send, 'whatsapp_ativo', true))
    && bazar_reengage_phone_digits_br($fone_live) !== '';

  $wa_me_url = '';
  $ok = false;

  if ($wa_live_ok) {
    $digits = bazar_reengage_phone_digits_br($fone_live);
    $wa_msg = bazar_reengage_compose_whatsapp_message(
      (string) $item['segment'],
      $greeting_name,
      !empty($item['has_email_pending'])
    );
    $wa_me_url = bazar_reengage_wa_me_url_for_lead($digits, $wa_msg);
    $ok = true;
  } else {
    list($subject, $body) = bazar_reengage_compose_email(
      (string) $item['segment'],
      $greeting_name,
      !empty($item['has_email_pending']),
      !empty($item['has_profile_pending'])
    );
    $body_mail = bazar_reengage_mail_body_html($body);
    $ok = wp_mail((string) $item['email'], $subject, $body_mail, bazar_reengage_headers_html());
  }

  if ($ok) {
    $item['status'] = 'sent';
    $item['sent_at'] = current_time('mysql');
    update_user_meta((int) $item['user_id'], BAZAR_REENGAGE_META_SENT_AT, $item['sent_at']);
  } else {
    $item['status'] = 'failed';
    $item['last_error'] = 'wp_mail_failed';
  }

  $queue[$idx] = $item;
  update_option(BAZAR_REENGAGE_OPTION_QUEUE, $queue, false);

  $pending = 0;
  $sent = 0;
  $failed = 0;
  foreach ($queue as $q) {
    $st = $q['status'] ?? '';
    if ($st === 'pending') {
      $pending++;
    } elseif ($st === 'sent') {
      $sent++;
    } elseif ($st === 'failed') {
      $failed++;
    }
  }

  $stats = get_option(BAZAR_REENGAGE_OPTION_STATS, array());
  $stats['updated_at'] = current_time('mysql');
  $stats['total'] = count($queue);
  $stats['pending'] = $pending;
  $stats['sent'] = $sent;
  $stats['failed'] = $failed;
  update_option(BAZAR_REENGAGE_OPTION_STATS, $stats, false);

  return array(
    'processed' => true,
    'success' => (bool) $ok,
    'item' => $item,
    'wa_me_url' => $wa_me_url,
    'channel' => $wa_live_ok ? 'whatsapp' : 'email',
  );
}

add_filter('cron_schedules', function ($schedules) {
  if (!isset($schedules['every_minute'])) {
    $schedules['every_minute'] = array(
      'interval' => 60,
      'display' => 'A cada minuto',
    );
  }
  return $schedules;
});

add_action(BAZAR_REENGAGE_CRON_HOOK, function () {
  bazar_reengage_send_one_from_queue();
  if (bazar_reengage_count_pending() < 1) {
    bazar_reengage_stop_cron();
  }
});

/**
 * Liga o envio automático (1/min) só se existir pendente na fila.
 *
 * @return bool true se agendou; false se não havia pendente para processar
 */
function bazar_reengage_start_cron()
{
  wp_clear_scheduled_hook(BAZAR_REENGAGE_CRON_HOOK);
  if (bazar_reengage_count_pending() < 1) {
    return false;
  }
  wp_schedule_event(time() + 60, 'every_minute', BAZAR_REENGAGE_CRON_HOOK);
  return true;
}

function bazar_reengage_stop_cron()
{
  wp_clear_scheduled_hook(BAZAR_REENGAGE_CRON_HOOK);
}

add_action('switch_theme', function () {
  if (function_exists('bazar_reengage_stop_cron')) {
    bazar_reengage_stop_cron();
  }
});

/**
 * Monta wa.me para o lead (telefone + texto), lendo meta atual do usuário.
 */
function bazar_reengage_wa_url_for_queue_item($item)
{
  if (!is_array($item)) {
    return '';
  }
  $uid = (int) ($item['user_id'] ?? 0);
  if ($uid < 1) {
    return '';
  }
  $fone = trim((string) get_user_meta($uid, 'fone', true));
  if (!bazar_reengage_bool_meta(get_user_meta($uid, 'whatsapp_ativo', true))) {
    return '';
  }
  $digits = bazar_reengage_phone_digits_br($fone);
  if ($digits === '') {
    return '';
  }
  $u = get_userdata($uid);
  $greet = $u ? bazar_reengage_user_greeting_name($u) : trim((string) ($item['name'] ?? ''));
  if ($greet === '') {
    $greet = trim((string) ($item['name'] ?? ''));
  }
  $msg = bazar_reengage_compose_whatsapp_message(
    (string) ($item['segment'] ?? 'B'),
    $greet,
    !empty($item['has_email_pending'])
  );
  return bazar_reengage_wa_me_url_for_lead($digits, $msg);
}

function bazar_reengage_admin_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  $notice = '';
  $notice_class = 'notice-info';
  $notice_wa_link = '';
  $notice_wa_test_links = null;

  if (isset($_POST['bazar_reengage_send_test']) && check_admin_referer('bazar_reengage_test_action')) {
    $test_email = sanitize_email($_POST['test_email'] ?? '');
    if (!is_email($test_email)) {
      $notice = 'Informe um e-mail válido.';
      $notice_class = 'notice-error';
    } else {
      list($s1, $b1) = bazar_reengage_compose_email('A', '', true, true, true);
      list($s2, $b2) = bazar_reengage_compose_email('B', '', false, false, true);
      $ok1 = wp_mail($test_email, $s1, bazar_reengage_mail_body_html($b1), bazar_reengage_headers_html());
      $ok2 = wp_mail($test_email, $s2, bazar_reengage_mail_body_html($b2), bazar_reengage_headers_html());
      $notice = ($ok1 && $ok2)
        ? 'Os dois e-mails (modelos A e B) foram enviados para a caixa informada.'
        : 'Falha ao enviar um ou mais e-mails.';
      $notice_class = ($ok1 && $ok2) ? 'notice-success' : 'notice-error';
    }
  }

  if (isset($_POST['bazar_reengage_test_wa']) && check_admin_referer('bazar_reengage_test_wa_action')) {
    $test_phone = sanitize_text_field(wp_unslash($_POST['test_whatsapp'] ?? ''));
    $digits = bazar_reengage_phone_digits_br($test_phone);
    if ($digits === '') {
      $notice = 'Informe um telefone válido (ex.: XXXXXX).';
      $notice_class = 'notice-error';
    } else {
      $msg_a = bazar_reengage_compose_whatsapp_message('A', '', false, true);
      $msg_b = bazar_reengage_compose_whatsapp_message('B', '', false, true);
      $msg_b_email = bazar_reengage_compose_whatsapp_message('B', '', true, true);
      $notice_wa_test_links = array(
        'a' => bazar_reengage_wa_me_url_for_lead($digits, $msg_a),
        'b' => bazar_reengage_wa_me_url_for_lead($digits, $msg_b),
        'b_email' => bazar_reengage_wa_me_url_for_lead($digits, $msg_b_email),
      );
      $notice = 'Links WhatsApp gerados. Abra no dispositivo com o app: o chat com o número informado abre com o texto pronto — basta enviar. (Sem API; mesmo fluxo da produção.)';
      $notice_class = 'notice-success';
    }
  }

  if (isset($_POST['bazar_reengage_generate_queue']) && check_admin_referer('bazar_reengage_generate_action')) {
    $force = !empty($_POST['force_regenerate']);
    $queue = bazar_reengage_build_queue($force);
    $notice = 'Fila gerada com ' . count($queue) . ' usuários.';
    $notice_class = 'notice-success';
  }

  if (isset($_POST['bazar_reengage_start']) && check_admin_referer('bazar_reengage_run_action')) {
    if (bazar_reengage_start_cron()) {
      $notice = 'Envio automático iniciado (1 lead por minuto; o cron é removido sozinho quando não houver mais pendentes).';
      $notice_class = 'notice-success';
    } else {
      $notice = 'Não há itens pendentes na fila. Gere a fila e tente de novo.';
      $notice_class = 'notice-warning';
    }
  }

  if (isset($_POST['bazar_reengage_stop']) && check_admin_referer('bazar_reengage_run_action')) {
    bazar_reengage_stop_cron();
    $notice = 'Envio automático pausado.';
    $notice_class = 'notice-info';
  }

  if (isset($_POST['bazar_reengage_send_one_now']) && check_admin_referer('bazar_reengage_run_action')) {
    $r = bazar_reengage_send_one_from_queue();
    if (bazar_reengage_count_pending() < 1) {
      bazar_reengage_stop_cron();
    }
    if (!empty($r['processed'])) {
      if (!empty($r['success'])) {
        $ch = (string) ($r['channel'] ?? 'email');
        if ($ch === 'whatsapp' && !empty($r['wa_me_url'])) {
          $notice = 'Este cadastro usa WhatsApp no lugar de e-mail: item marcado como enviado; abra o link abaixo para enviar a mensagem no app (wa.me, sem API).';
          $notice_wa_link = (string) $r['wa_me_url'];
        } else {
          $notice = '1 e-mail enviado agora.';
        }
        $notice_class = 'notice-success';
      } else {
        $notice = '1 item processado com falha no envio.';
        $notice_class = 'notice-error';
      }
    } else {
      $notice = 'Nada pendente para processar.';
      $notice_class = 'notice-info';
    }
  }

  $stats = get_option(BAZAR_REENGAGE_OPTION_STATS, array());
  $queue = get_option(BAZAR_REENGAGE_OPTION_QUEUE, array());
  $cron_running = (bool) wp_next_scheduled(BAZAR_REENGAGE_CRON_HOOK);

  echo '<div class="wrap">';
  echo '<h1>Reengajar novos cadastros (sem conversão)</h1>';
  echo '<p>Recorte de <strong>data de cadastro</strong>: <code>user_registered &gt;= ' . esc_html(BAZAR_REENGAGE_CUTOFF_YMD) . ' 00:00:00</code> (constante <code>BAZAR_REENGAGE_CUTOFF_YMD</code>) — <strong>sem data final</strong>; entram todos os usuários cadastrados desde essa data. A fila só inclui quem está no <strong>segmento A ou B</strong> (ver abaixo). Com <strong>Iniciar envio</strong>: <strong>1 lead por minuto</strong> enquanto houver pendentes; quando não houver mais nenhum pendente, o <strong>evento de cron some sozinho</strong> (para voltar a enviar, gere a fila e clique em Iniciar de novo). Quem tem <code>whatsapp_ativo</code> e telefone válido <strong>não recebe e-mail</strong> (canal WhatsApp: link <code>wa.me</code>); os demais recebem só o e-mail.</p>';

  if ($notice !== '') {
    echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible">';
    echo '<p>' . esc_html($notice) . '</p>';
    if ($notice_wa_link !== '') {
      echo '<p><a class="button button-primary" target="_blank" rel="noopener noreferrer" href="' . bazar_reengage_esc_href_wa_me($notice_wa_link) . '">Abrir WhatsApp deste lead</a></p>';
    }
    if (is_array($notice_wa_test_links)) {
      echo '<p style="margin-top:10px;"><strong>Modelo A (lead quente):</strong><br>';
      echo '<a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="' . bazar_reengage_esc_href_wa_me($notice_wa_test_links['a']) . '">Abrir wa.me — A</a></p>';
      echo '<p><strong>Modelo B (lead frio):</strong><br>';
      echo '<a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="' . bazar_reengage_esc_href_wa_me($notice_wa_test_links['b']) . '">Abrir wa.me — B</a></p>';
      echo '<p><strong>Modelo B com texto de e-mail pendente:</strong><br>';
      echo '<a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="' . bazar_reengage_esc_href_wa_me($notice_wa_test_links['b_email']) . '">Abrir wa.me — B (e-mail)</a></p>';
    }
    echo '</div>';
  }

  echo '<div class="card" style="background:#fff;padding:16px;">';
  echo '<h2 style="margin-top:0;">E-mail — modelos A e B</h2>';
  echo '<form method="post">';
  wp_nonce_field('bazar_reengage_test_action');
  echo '<p><label>E-mail de destino: <input type="email" name="test_email" style="min-width:320px;" placeholder="seuemail@dominio.com"></label></p>';
  echo '<p><button type="submit" name="bazar_reengage_send_test" class="button button-secondary">Enviar modelos A e B</button></p>';
  echo '</form>';
  echo '</div>';

  echo '<div class="card" style="background:#fff;padding:16px; margin-top:16px;">';
  echo '<h2 style="margin-top:0;">WhatsApp — links wa.me</h2>';
  echo '<p style="margin-top:0;color:#50575e;">Informe o número (o app abre uma conversa <em>com esse número</em> e pré-preenche a mensagem; não há API — você confirma o envio no WhatsApp).</p>';
  echo '<form method="post">';
  wp_nonce_field('bazar_reengage_test_wa_action');
  echo '<p><label>Telefone (celular com DDD): <input type="text" name="test_whatsapp" style="min-width:320px;" placeholder="XXXXXX" autocomplete="tel"></label></p>';
  echo '<p><button type="submit" name="bazar_reengage_test_wa" class="button button-secondary">Gerar links (A, B e B+e-mail)</button></p>';
  echo '</form>';
  echo '</div>';

  echo '<div class="card" style="background:#fff;padding:16px; margin-top:16px;">';
  echo '<h2 style="margin-top:0;">Fila de envio</h2>';
  echo '<form method="post" style="margin-bottom:12px;">';
  wp_nonce_field('bazar_reengage_generate_action');
  echo '<p><label><input type="checkbox" name="force_regenerate" value="1"> Regerar incluindo quem já recebeu antes</label></p>';
  echo '<p><button type="submit" name="bazar_reengage_generate_queue" class="button button-primary">Gerar fila</button></p>';
  echo '</form>';

  echo '<p><strong>Status do cron:</strong> ' . ($cron_running ? 'Rodando' : 'Parado') . '</p>';
  $u_cut = (int) ($stats['usuarios_desde_cutoff'] ?? 0);
  if ($u_cut > 0 || !empty($stats['pulos_geracao'])) {
    echo '<p><strong>Usuários no recorte de data</strong> (cadastro &gt;= ' . esc_html((string) ($stats['cutoff_ymd'] ?? BAZAR_REENGAGE_CUTOFF_YMD)) . '): <strong>' . $u_cut . '</strong>. ';
    echo 'Na <strong>fila</strong> (A/B após filtros): <strong>' . (int) ($stats['total'] ?? 0) . '</strong>.</p>';
    $pul = $stats['pulos_geracao'] ?? array();
    if (is_array($pul) && array_sum($pul) > 0) {
      echo '<p style="font-size:13px;color:#444;"><strong>Excluídos na geração:</strong> ';
      echo 'sem usuário: ' . (int) ($pul['sem_usuario'] ?? 0) . '; ';
      echo 'e-mail inválido: ' . (int) ($pul['email_invalido'] ?? 0) . '; ';
      echo 'conta cancelada: ' . (int) ($pul['cancelado'] ?? 0) . '; ';
      echo 'bloqueado: ' . (int) ($pul['bloqueado'] ?? 0) . '; ';
      echo 'já contatado (meta de envio): ' . (int) ($pul['ja_recebeu_email'] ?? 0) . '; ';
      echo '<span title="Tem anúncio e perfil completo com e-mail já confirmado — fora do recorte desta campanha.">fora do segmento A/B</span>: ' . (int) ($pul['fora_segmento'] ?? 0) . '.';
      echo '</p>';
    }
  }
  echo '<p><strong>Total na fila:</strong> ' . (int) ($stats['total'] ?? 0) . ' | <strong>Pendentes:</strong> ' . (int) ($stats['pending'] ?? 0) . ' | <strong>Enviados:</strong> ' . (int) ($stats['sent'] ?? 0) . ' | <strong>Falhas:</strong> ' . (int) ($stats['failed'] ?? 0) . '</p>';
  echo '<p><strong>Segmento A</strong> (começou anúncio, falta perfil e/ou e-mail confirmado): ' . (int) ($stats['segment_a'] ?? 0) . ' | <strong>Segmento B</strong> (sem anúncio): ' . (int) ($stats['segment_b'] ?? 0) . ' | <strong>Com e-mail ainda não confirmado</strong> (texto extra A/B): ' . (int) ($stats['com_email_pendente'] ?? 0) . ' | <strong>Com WhatsApp na fila:</strong> ' . (int) ($stats['com_whatsapp'] ?? 0) . '</p>';

  echo '<form method="post">';
  wp_nonce_field('bazar_reengage_run_action');
  echo '<p>';
  echo '<button type="submit" name="bazar_reengage_start" class="button button-primary" style="margin-right:8px;">Iniciar envio (1/min)</button>';
  echo '<button type="submit" name="bazar_reengage_stop" class="button" style="margin-right:8px;">Pausar</button>';
  echo '<button type="submit" name="bazar_reengage_send_one_now" class="button button-secondary">Enviar 1 agora</button>';
  echo '</p>';
  echo '</form>';
  echo '</div>';

  echo '<div class="card" style="background:#fff;padding:16px; margin-top:16px;">';
  echo '<h2 style="margin-top:0;">Pendentes — link WhatsApp (wa.me)</h2>';
  echo '<p>Leads <strong>pendentes</strong> com <code>whatsapp_ativo</code> e telefone normalizável (o cron <strong>não</strong> manda e-mail para eles). O link abre conversa <em>com o número do lead</em> e já traz o texto do modelo (quente/frio).</p>';
  if (!empty($queue) && is_array($queue)) {
    echo '<table class="widefat striped" style="margin-top:8px;"><thead><tr><th>E-mail</th><th>Seg.</th><th>WhatsApp</th></tr></thead><tbody>';
    $wa_table_rows = 0;
    foreach ($queue as $q) {
      if (($q['status'] ?? '') !== 'pending') {
        continue;
      }
      $wurl = bazar_reengage_wa_url_for_queue_item($q);
      if ($wurl === '') {
        continue;
      }
      $wa_table_rows++;
      if ($wa_table_rows > 50) {
        break;
      }
      echo '<tr><td>' . esc_html((string) ($q['email'] ?? '')) . '</td><td>' . esc_html((string) ($q['segment'] ?? '')) . '</td><td><a target="_blank" rel="noopener noreferrer" href="' . bazar_reengage_esc_href_wa_me($wurl) . '">Abrir wa.me</a></td></tr>';
    }
    echo '</tbody></table>';
    if ($wa_table_rows === 0) {
      echo '<p><em>Nenhum pendente elegível para wa.me nesta fila.</em></p>';
    }
  } else {
    echo '<p><em>Gere a fila primeiro.</em></p>';
  }
  echo '</div>';

  if (!empty($queue) && is_array($queue)) {
    $sample = array_slice($queue, 0, 20);
    foreach ($sample as $k => $row) {
      if (!is_array($sample[$k])) {
        continue;
      }
      $sample[$k]['wa_me_url'] = bazar_reengage_wa_url_for_queue_item($row);
    }
    echo '<div class="card" style="background:#fff;padding:16px; margin-top:16px;">';
    echo '<h2 style="margin-top:0;">Amostra da fila (20 primeiros)</h2>';
    echo '<pre style="background:#111;color:#0f0;padding:12px;overflow:auto;max-height:420px;">' . esc_html(wp_json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
    echo '</div>';
  }

  echo '</div>';
}

add_action('admin_menu', function () {
  add_management_page(
    'Reengajar cadastros sem anúncio',
    'Reengajar Cadastros',
    'manage_options',
    'bazar-reengage-cadastros',
    'bazar_reengage_admin_page'
  );
});

