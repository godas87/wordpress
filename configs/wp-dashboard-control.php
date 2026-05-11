<?php
/**
 * ÚNICA FONTE DE VERDADE para controle de acesso ao dashboard do WordPress
 * Intercepta todos os redirecionamentos relacionados a sessão expirada,
 * acesso não autorizado e tentativas de acesso ao wp-login.php
 * Redireciona sempre para /entrar/ ao invés de mostrar telas do WordPress
 */

/**
 * Verifica se a requisição é AJAX
 * @return bool
 */
// REMOVER BARRA DE TAREFAS NO HEADER
add_filter('show_admin_bar', 'my_function_admin_bar');
function my_function_admin_bar()
{
  return false;
}

// LOGIN REDIRECT
add_filter('login_redirect', 'admin_default_page', 10, 3);
function admin_default_page($redirect_to, $request, $user)
{

  if (isset($user->roles) && is_array($user->roles)) {
    //check for admins
    if (in_array('administrator', $user->roles)) {
      // redirect them to the default place
      return $redirect_to;
    } else {
      return get_bloginfo('url') . '\/minha-conta\/';
    }
  } else {
    return $redirect_to;
  }
}


add_action('wp_dashboard_setup', 'remove_dashboard_widgets');
function remove_dashboard_widgets()
{
  global $wp_meta_boxes;
  unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_welcome_panel']);
  unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_browser_nag']);
  unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_php_nag']);
}

if (!current_user_can('manage_options')) {
  add_action('wp_dashboard_setup', 'remove_dashboard_widgets');
}


function bazar_is_ajax_request()
{
  return (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
  ) || (
    !empty($_POST['action']) &&
    (
      strpos($_POST['action'], 'bazarbikes_') === 0
      || strpos($_POST['action'], 'ajax_') === 0
    )
  ) || (
    defined('DOING_AJAX') && DOING_AJAX
  );
}

/**
 * Normaliza host para comparação (www vs sem www = mesmo site)
 * @param string $host
 * @return string
 */
function bazar_normalize_redirect_host($host)
{
  $host = strtolower(trim($host));
  if (strpos($host, 'www.') === 0) {
    return substr($host, 4);
  }
  return $host;
}

/**
 * Valida se uma URL pertence ao mesmo domínio do site
 * Previne vulnerabilidade de Open Redirect
 * Considera www e sem www como o mesmo domínio (evita bloqueio em produção)
 * @param string $url URL a validar
 * @return bool True se URL é válida e do mesmo domínio
 */
function bazar_validate_redirect_url($url)
{

  if (empty($url)) {
    return false;
  }

  // Parse da URL
  $parsed_url = wp_parse_url($url);

  // Se não conseguir fazer parse, rejeitar
  if (!$parsed_url) {
    return false;
  }

  // Se for URL relativa (sem host), aceitar
  if (!isset($parsed_url['host'])) {
    return true;
  }

  // Obter domínio do site atual (normalizado: www = sem www)
  $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
  $site_host = bazar_normalize_redirect_host($site_host);
  $url_host = bazar_normalize_redirect_host($parsed_url['host']);

  // Comparar hosts normalizados
  if ($url_host === $site_host) {
    return true;
  }

  // Rejeitar URLs de outros domínios
  return false;
}

/**
 * Sanitiza e valida URL de redirecionamento
 * @param string $url URL a sanitizar e validar
 * @return string URL sanitizada se válida, string vazia se inválida
 */
function bazar_sanitize_redirect_url($url)
{

  if (empty($url)) {
    return '';
  }

  // Sanitizar URL
  $sanitized = esc_url_raw($url);

  // Validar se pertence ao mesmo domínio
  if (!bazar_validate_redirect_url($sanitized)) {
    return '';
  }

  return $sanitized;
}

/**
 * Gera URL de login com redirect_to preservado
 * @param string $redirect_to URL para redirecionar após login
 * @return string URL completa para /entrar/
 */
function bazar_get_login_url($redirect_to = '')
{

  $login_url = home_url('/entrar/');

  // Validar e sanitizar redirect_to antes de usar
  $validated_redirect = bazar_sanitize_redirect_url($redirect_to);

  $result_url = ($validated_redirect)
    ? add_query_arg('redirect_to', urlencode($validated_redirect), $login_url)
    : $login_url;

  return $result_url;
}

// ============================================
// 0a. HOST CANÓNICO (www vs apex) EM /wp-admin E wp-login.php
// ============================================
// Com HOME em https://www.... e acesso a https://XXXXXX/wp-admin, o cookie de sessão
// (escopo do host) pode não ser enviado — is_user_logged_in() fica falso e o utilizador cai em /entrar/
// sem necessidade. Redirecionar primeiro para o mesmo path no host de home_url() unifica o domínio.
add_action('init', 'bazar_canonical_host_redirect_for_auth_routes', 0);
function bazar_canonical_host_redirect_for_auth_routes()
{
  if (defined('DOING_CRON') && DOING_CRON) {
    return;
  }
  if (defined('WP_CLI') && WP_CLI) {
    return;
  }
  if (defined('DOING_AJAX') && DOING_AJAX) {
    return;
  }
  if (bazar_is_ajax_request()) {
    return;
  }

  $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
  if ($uri === '') {
    return;
  }

  $is_auth_route = (strpos($uri, '/wp-admin') !== false) || (strpos($uri, 'wp-login.php') !== false);
  if (!$is_auth_route) {
    return;
  }

  $home_parsed = wp_parse_url(home_url('/'));
  if (empty($home_parsed['host'])) {
    return;
  }
  $canonical_host = strtolower($home_parsed['host']);
  $current_host = isset($_SERVER['HTTP_HOST'])
    ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])))
    : '';

  if ($current_host === '' || $canonical_host === $current_host) {
    return;
  }

  // Só redirecionar entre variantes do mesmo site (www / sem www), nunca entre domínios diferentes
  if (bazar_normalize_redirect_host($canonical_host) !== bazar_normalize_redirect_host($current_host)) {
    return;
  }

  if (headers_sent()) {
    return;
  }

  $scheme = (!empty($home_parsed['scheme'])) ? $home_parsed['scheme'] : (is_ssl() ? 'https' : 'http');
  $destination = $scheme . '://' . $canonical_host . $uri;
  wp_safe_redirect($destination, 301);
  exit;
}

// ============================================
// 0. EVITA CACHE NO ADMIN (produção: CDN/proxy não pode cachear 302 nem páginas)
// ============================================
// auth_redirect() roda em admin.php ANTES de admin_init; o 302 pode ser cacheado em produção.
// Enviar no-cache em init para qualquer request a /wp-admin (assim o 302 já sai com esses headers).
add_action('init', 'bazar_admin_no_cache_headers', 1);
function bazar_admin_no_cache_headers()
{
  $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
  if (strpos($uri, '/wp-admin') === false) {
    return;
  }
  if (headers_sent()) {
    return;
  }
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
  header('Expires: 0');
}

// ============================================
// 1. INTERCEPTA ACESSO AO WP-LOGIN.PHP
// ============================================
add_action('init', 'bazar_intercept_wp_login', 1);
function bazar_intercept_wp_login()
{
  global $pagenow;

  if (bazar_is_ajax_request()) {
    return;
  }

  if ($pagenow !== 'wp-login.php') {
    return;
  }

  // Permitir apenas ações específicas (logout, reset, etc) e envio do formulário
  $allowed_actions = array('logout', 'rp', 'resetpass', 'register');
  $action = isset($_GET['action']) ? $_GET['action'] : '';
  if (in_array($action, $allowed_actions) || isset($_POST['wp-submit'])) {
    return;
  }

  // Todo o resto: redirecionar para /entrar/ (único ponto de login)
  $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
  wp_redirect(bazar_get_login_url($redirect_to));
  exit;
}

// ============================================
// 2. INTERCEPTA TENTATIVAS DE ACESSO AO ADMIN
// ============================================
// Bypass em emergência: defina em wp-config.php: define('BAZAR_ALLOW_WP_ADMIN_DIRECT', true);
// Prioridade 20: garante que o usuário e capacidades já foram carregados (evita bloqueio em produção)
add_action('admin_init', 'bazar_intercept_admin_access', 20);
function bazar_intercept_admin_access()
{
  if (defined('BAZAR_ALLOW_WP_ADMIN_DIRECT') && BAZAR_ALLOW_WP_ADMIN_DIRECT) {
    return;
  }
  // Se for AJAX, não redirecionar
  if (bazar_is_ajax_request()) {
    return;
  }

  // Se não está logado e está tentando acessar admin
  if (!is_user_logged_in()) {
    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
    wp_redirect(bazar_get_login_url($redirect_to));
    exit;
  }

  // Permitir acesso: por capacidade OU por role (fallback para produção onde capacidades podem falhar)
  $user = wp_get_current_user();
  $roles = (array) $user->roles;
  $by_capability = current_user_can('manage_options') || current_user_can('edit_others_posts');
  $by_role = in_array('administrator', $roles, true) || in_array('editor', $roles, true);
  $can_access_admin = $by_capability || $by_role;

  if (!$can_access_admin) {
    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
    wp_redirect(bazar_get_login_url($redirect_to));
    exit;
  }
}

// ============================================
// 2b. /entrar/ COM redirect_to — utilizador JÁ logado
// ============================================
// Se o cookie só ficou válido após o redirect canónico (ou o utilizador já tinha sessão),
// envia logo para redirect_to em vez de mostrar o formulário "à toa".
// wp-admin: só quem pode aceder ao admin (mesma regra que bazar_intercept_admin_access), senão loop autor ↔ entrar.
add_action('template_redirect', 'bazar_entrar_redirect_if_logged_in_with_redirect_to', 3);
function bazar_entrar_redirect_if_logged_in_with_redirect_to()
{
  if (!is_user_logged_in()) {
    return;
  }
  if (!is_page('entrar')) {
    return;
  }
  if (empty($_GET['redirect_to'])) {
    return;
  }

  $redirect_to = bazar_sanitize_redirect_url(wp_unslash((string) $_GET['redirect_to']));
  if ($redirect_to === '') {
    return;
  }

  if (strpos($redirect_to, '/wp-admin') !== false) {
    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    $by_capability = current_user_can('manage_options') || current_user_can('edit_others_posts');
    $by_role = in_array('administrator', $roles, true) || in_array('editor', $roles, true);
    if (!($by_capability || $by_role)) {
      return;
    }
  }

  wp_safe_redirect($redirect_to);
  exit;
}

// ============================================
// 3. INTERCEPTA PREVIEW SEM LOGIN
// ============================================
add_action('template_redirect', 'bazar_intercept_preview_without_login', 1);
function bazar_intercept_preview_without_login()
{
  // Se for AJAX, não redirecionar
  if (bazar_is_ajax_request()) {
    return;
  }

  // Verifica se é um preview
  $is_preview = isset($_GET['preview']) && $_GET['preview'] == 'true';

  // Se for preview e usuário não está logado, redirecionar para login
  if ($is_preview && !is_user_logged_in()) {
    // Construir URL atual preservando parâmetros
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
      . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    // Redirecionar para login com redirect_to usando função segura
    $login_url = bazar_get_login_url($current_url);
    wp_redirect(esc_url_raw($login_url));
    exit;
  }
}

// ============================================
// 4. INTERCEPTA AUTH_REDIRECT() DO WORDPRESS
// ============================================
add_action('template_redirect', 'bazar_intercept_auth_redirect', 2);
function bazar_intercept_auth_redirect()
{
  // Se for AJAX, não redirecionar
  if (bazar_is_ajax_request()) {
    return;
  }

  // Intercepta quando WordPress tenta fazer auth_redirect
  // Isso acontece quando usuário não está logado e tenta acessar área protegida
  if (!is_user_logged_in() && is_admin()) {
    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
    wp_redirect(bazar_get_login_url($redirect_to));
    exit;
  }
}

// ============================================
// 5. SUBSTITUI URL DE LOGIN DO WORDPRESS — /entrar/ é o único ponto de login
// ============================================
add_filter('login_url', 'bazar_custom_login_url', 10, 2);
function bazar_custom_login_url($login_url, $redirect)
{
  if (strpos($login_url, 'wp-login.php') !== false) {
    return bazar_get_login_url($redirect);
  }
  return $login_url;
}

// ============================================
// 6. INTERCEPTA WP_DIE() QUANDO SESSÃO EXPIRA
// ============================================
add_filter('wp_die_handler', 'bazar_intercept_wp_die', 1);
function bazar_intercept_wp_die($handler)
{
  // Verificar se é requisição AJAX
  if (bazar_is_ajax_request()) {
    return $handler; // Deixa o handler padrão tratar AJAX
  }

  // NÃO interceptar em páginas públicas (taxonomias, arquivos, etc)
  // Apenas interceptar em áreas administrativas ou páginas protegidas
  if (is_admin() || is_page_template()) {
    // Verificar se usuário não está logado (sessão expirada)
    if (!is_user_logged_in()) {
      // Interceptar wp_die() e redirecionar para /entrar/
      return 'bazar_wp_die_redirect';
    }
  }

  return $handler;
}

function bazar_wp_die_redirect($message, $title = '', $args = array())
{
  // Se não está logado, redirecionar para /entrar/ ao invés de mostrar tela do WordPress
  // Mas apenas se for área administrativa ou página protegida
  if (!is_user_logged_in() && (is_admin() || is_page_template())) {
    // Usar home_url() para construir URL atual de forma segura
    $current_url = home_url($_SERVER['REQUEST_URI']);
    $redirect_to = esc_url_raw($current_url);
    wp_redirect(bazar_get_login_url($redirect_to));
    exit;
  }

  // Se estiver logado ou for página pública, usar handler padrão do WordPress
  _default_wp_die_handler($message, $title, $args);
}

// ============================================
// 7. MENSAGEM DE ERRO DE LOGIN
// ============================================
add_filter('login_errors', 'bazar_login_error_message');
function bazar_login_error_message()
{
  return 'Acesso negado!';
}

/**
 * Valida token de redefinição de senha e retorna o user_id se válido.
 * Token deve estar em user_meta redefinir_senha_token e redefinir_senha_token_expiry > time().
 *
 * @param string $token Token enviado por e-mail (formulário reenviar senha).
 * @return int User ID ou 0 se token inválido/expirado.
 */
function bazar_redefinir_senha_token_user_id($token)
{
  if (empty($token) || !is_string($token)) {
    return 0;
  }
  $users = get_users(array(
    'meta_key' => 'redefinir_senha_token',
    'meta_value' => $token,
    'number' => 1,
    'fields' => 'ID',
  ));
  if (empty($users)) {
    return 0;
  }
  $user_id = (int) $users[0];
  $expiry = get_user_meta($user_id, 'redefinir_senha_token_expiry', true);
  if ($expiry === '' || (int) $expiry < time()) {
    return 0;
  }
  return $user_id;
}