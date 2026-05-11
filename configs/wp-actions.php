<?php
// REMOVE SPECULATIVE LOADING
remove_action('wp_head', 'wp_output_speculation_rules_script');
/**
 * Ponto único de redirects no ciclo `template_redirect`.
 *
 * Por que centralizar:
 * - evita espalhar regras de redirect em vários add_action;
 * - facilita manutenção e leitura da ordem de execução;
 * - reduz risco de conflito/loop entre redirects.
 *
 * Ordem aplicada (importante):
 * 1) normalização de espaço final no path;
 * 2) correção de URL órfã em taxonomias hierárquicas;
 * 3) redirect de página de anexo para o post pai.
 *
 * Observação: qualquer regra que redireciona chama `exit`, então
 * as próximas regras não são executadas na mesma requisição.
 */
add_action('template_redirect', 'bazar_handle_template_redirects', 0);
function bazar_handle_template_redirects()
{
  if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
    return;
  }

  if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'GET') {
    return;
  }

  // 1) Normalização de espaço final no path.
  if (bazar_redirect_trailing_whitespace_path_if_needed()) {
    return;
  }

  // 2) Taxonomias órfãs (filho sem pai no path) => URL canônica do termo.
  if (bazar_redirect_orphan_taxonomy_term_if_needed()) {
    return;
  }

  // 3) Attachment para post pai.
  bazar_redirect_attachment_page_if_needed();
}

/**
 * Normaliza URL removendo espaços em branco no fim do path.
 * Evita duplicidade de URL para SEO (ex.: /strada/%20).
 *
 * Exemplos:
 * - /bicicleta-marca-modelo/strada/%20 -> /bicicleta-marca-modelo/strada/
 * - /bicicleta-cidade/sp/sao-paulo/%20?utm_source=x -> .../sao-paulo/?utm_source=x
 *
 * Regras:
 * - preserva query string;
 * - usa redirect 301 (canônico).
 *
 * @return bool True quando redirecionou
 */
function bazar_redirect_trailing_whitespace_path_if_needed()
{
  $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
  if ($request_uri === '') {
    return false;
  }

  $parts = wp_parse_url($request_uri);
  if (!is_array($parts)) {
    return false;
  }

  $path_raw = isset($parts['path']) ? (string) $parts['path'] : '';
  $path_decoded = rawurldecode($path_raw);
  $trimmed_decoded = rtrim($path_decoded);
  if ($trimmed_decoded === $path_decoded) {
    return false;
  }

  $normalized_path = implode('/', array_map('rawurlencode', explode('/', $trimmed_decoded)));
  if ($normalized_path === '') {
    $normalized_path = '/';
  }

  $destination = home_url($normalized_path);
  if (!empty($parts['query'])) {
    $destination .= '?' . $parts['query'];
  }

  wp_safe_redirect($destination, 301);
  exit;
}

/**
 * Redireciona termos órfãos para a URL canônica da taxonomia (301).
 *
 * Definição de "órfã" neste contexto:
 * - termo atual pertence a taxonomia hierárquica;
 * - termo atual possui pai definido (parent > 0);
 * - URL acessada NÃO contém o slug do pai como primeiro segmento
 *   após o prefixo da taxonomia.
 *
 * Taxonomias tratadas:
 * - marca-modelo: /bicicleta-marca-modelo/{pai}/{filho}/
 * - cidade:       /bicicleta-cidade/{estado}/{cidade}/
 * - componente:   /bicicleta-pecas/{grupo}/{subgrupo-ou-medida}/
 *
 * Exemplos:
 * - /bicicleta-marca-modelo/strada/   -> /bicicleta-marca-modelo/caloi/strada/
 * - /bicicleta-cidade/sao-paulo/      -> /bicicleta-cidade/sp/sao-paulo/
 * - /bicicleta-pecas/29/              -> /bicicleta-pecas/aro/29/
 *
 * Estratégia:
 * - detecta contexto da taxonomia atual com is_tax();
 * - valida se termo é filho;
 * - valida ausência do pai no path;
 * - resolve destino com get_term_link($term) (fonte canônica do WP);
 * - preserva query string da requisição original.
 *
 * @return bool True quando redirecionou
 */
function bazar_redirect_orphan_taxonomy_term_if_needed()
{
  $taxonomy_configs = array(
    'marca-modelo' => '/bicicleta-marca-modelo/',
    'cidade' => '/bicicleta-cidade/',
    'componente' => '/bicicleta-pecas/',
  );

  $active_taxonomy = '';
  $active_base = '';
  foreach ($taxonomy_configs as $taxonomy => $base) {
    if (is_tax($taxonomy)) {
      $active_taxonomy = $taxonomy;
      $active_base = $base;
      break;
    }
  }

  if ($active_taxonomy === '') {
    return false;
  }

  $term = get_queried_object();
  if (!$term || is_wp_error($term) || !isset($term->parent) || (int) $term->parent < 1) {
    return false;
  }

  $parent_term = get_term((int) $term->parent, $active_taxonomy);
  if (!$parent_term || is_wp_error($parent_term) || empty($parent_term->slug)) {
    return false;
  }

  $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
  $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
  $path = strtolower(rawurldecode($path));
  $base = strtolower($active_base);
  $pos = strpos($path, $base);
  if ($pos === false) {
    return false;
  }

  $tail = substr($path, $pos + strlen($base));
  $tail = trim((string) $tail);
  $tail = trim($tail, '/');
  if ($tail === '') {
    return false;
  }

  $segments = array_values(array_filter(explode('/', $tail), 'strlen'));
  if (empty($segments)) {
    return false;
  }

  $expected_parent_slug = strtolower((string) $parent_term->slug);
  if ($segments[0] === $expected_parent_slug) {
    return false;
  }

  $canonical_url = get_term_link($term);
  if (!$canonical_url || is_wp_error($canonical_url)) {
    return false;
  }

  $query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);
  if ($query !== '') {
    $canonical_url .= (strpos($canonical_url, '?') === false ? '?' : '&') . $query;
  }

  wp_safe_redirect($canonical_url, 301);
  exit;
}

/**
 * Redireciona páginas de anexo para o conteúdo pai.
 *
 * Motivo:
 * - evita thin content/duplicidade de páginas de attachment;
 * - concentra autoridade no post principal.
 *
 * Comportamento:
 * - se o anexo tiver post pai: 301 para permalink do pai;
 * - se não tiver pai: 301 para home.
 */
function bazar_redirect_attachment_page_if_needed()
{
  if (!is_attachment()) {
    return;
  }

  global $post;
  if ($post && $post->post_parent) {
    wp_safe_redirect(get_permalink($post->post_parent), 301);
    exit;
  }

  wp_safe_redirect(home_url('/'), 301);
  exit;
}

//LIMIT LENGHT POST TITLE
//add_action('publish_post', 'post_title_max_lenght');
function post_title_max_lenght()
{
  global $post;
  $title = $post->post_title;
  if (strlen($title) >= 68)
    wp_die("O Titulo deve conter no máximo 85 caracteres");
}

// REMOVE COMMENTES DASHBOARD MENU
//add_action('admin_menu', 'cwp_desativa_comentarios_admin_menu');
function cwp_desativa_comentarios_admin_menu()
{
  remove_menu_page('edit-comments.php');
}

// HIDE TRACKBACKS
add_action('pre_ping', 'no_self_ping');
function no_self_ping(&$links)
{
  $home = get_option('home');
  foreach ($links as $l => $link) {
    if (0 === strpos($link, $home)) {
      unset($links[$l]);
    }
  }
}

// REMOVER SENHA FORTE
add_action('wp_print_scripts', 'DisableStrongPW', 100);
function DisableStrongPW()
{
  if (wp_script_is('wc-password-strength-meter', 'enqueued')) {
    wp_dequeue_script('wc-password-strength-meter');
  }
}

// MUDAR LOGO TELA DE LOGIN
add_action('admin_head', 'login_logo');
add_action('login_head', 'login_logo');
function login_logo()
{
  $w = '120px';
  $h = '120px';
  echo '<style> .login h1 a { background-image: url("' . get_bloginfo("url") . '/src/imgs/bazar-bikes.svg") !important; margin:0 auto !important; background-size:' . $w . ' ' . $h . '  !important; height:' . $h . ' !important; width:' . $w . ' !important; }</style>';
}

// MUDAR URL TELA DE LOGIN
add_filter('login_headerurl', 'login_logo_headerurl');
function login_logo_headerurl($url)
{
  $url = home_url('/');
  return $url;
}

// LOGOUT AUTOMÁTICO SEM CONFIRMAÇÃO
add_action('init', 'bazar_auto_logout');
function bazar_auto_logout()
{
  // Verificar se é a ação de logout e se o usuário está logado
  if (isset($_GET['bazar_logout']) && $_GET['bazar_logout'] == '1' && is_user_logged_in()) {
    // Verificar nonce para segurança
    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'bazar_logout')) {
      // Fazer logout
      wp_logout();
      // Redirecionar para home
      $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/');
      wp_safe_redirect($redirect_to);
      exit;
    }
  }
}

// Função helper para gerar URL de logout automático
function bazar_logout_url($redirect = '')
{
  if (empty($redirect)) {
    $redirect = home_url('/');
  }
  $logout_url = add_query_arg(array(
    'bazar_logout' => '1',
    'redirect_to' => urlencode($redirect),
    '_wpnonce' => wp_create_nonce('bazar_logout')
  ), home_url('/'));
  return $logout_url;
}