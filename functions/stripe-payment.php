<?php
/**
 * Sistema de pagamento Stripe - Fonte única de verdade
 *
 * Gerencia checkout via Payment Links e verificação de pagamento.
 * Após gravar metas de pagamento no anúncio, a visibilidade do termo `destaque` e a meta
 * `bazar_destaque_aguarda_verificacao` ficam a cargo de `bazar_destaque_service_sync_visibility_after_paid()`
 * (alinhado ao fluxo de publicação e ao serviço de destaque).
 *
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

/** Meta do post: promoção de desconto já utilizada (1x por anúncio) */
if (!defined('BAZAR_META_DESTAQUE_PROMO_USED')) {
  define('BAZAR_META_DESTAQUE_PROMO_USED', 'bazar_destaque_promo_used');
}

/**
 * Retorna instancia unica do servico de UTM.
 *
 * @return __BazarUtmService|null
 */
function bazar_get_utm_service_instance()
{
  static $utm_service = null;
  if ($utm_service instanceof __BazarUtmService) {
    return $utm_service;
  }
  if (!class_exists('__BazarUtmService')) {
    return null;
  }
  $utm_service = new __BazarUtmService();
  return $utm_service;
}

// ============================================
// CHECKOUT - AJAX Handler
// ============================================

add_action('wp_ajax_bazar_checkout_stripe', 'bazar_checkout_stripe');
add_action('wp_ajax_nopriv_bazar_checkout_stripe', 'bazar_checkout_stripe');
function bazar_checkout_stripe()
{

  // Limpar output anterior
  if (ob_get_level()) {
    ob_clean();
  }

  // Verificar nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bazar_stripe_checkout')) {
    wp_send_json_error(array('message' => 'Erro de segurança. Recarregue a página.'));
    return;
  }

  // Verificar se usuário está logado
  $user_id = get_current_user_id();
  if (empty($user_id)) {
    wp_send_json_error(array('message' => 'Você precisa estar logado para destacar um anúncio.'));
    return;
  }

  // Obter dados
  $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
  $tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : 'simples';
  if (!in_array($tipo, array('simples', 'newsletter', 'promo'), true)) {
    $tipo = 'simples';
  }

  if (empty($post_id)) {
    wp_send_json_error(array('message' => 'ID do anúncio não informado.'));
    return;
  }

  // Validar anúncio usando cache
  $post = bazar_stripe_get_post_cached($post_id);
  if (!$post || $post->post_type !== 'post') {
    wp_send_json_error(array('message' => 'Anúncio não encontrado.'));
    return;
  }

  // Verificar se usuário é o autor
  if (intval($post->post_author) !== intval($user_id)) {
    wp_send_json_error(array('message' => 'Você não é o autor deste anúncio.'));
    return;
  }

  // Verificar se já está em destaque
  if (has_term('destaque', 'status', $post_id)) {
    wp_send_json_error(array('message' => 'Este anúncio já está em destaque.'));
    return;
  }

  // Já existe pagamento registrado (destaque ativo ou aguardando verificação do CPF)
  if (get_post_meta($post_id, 'destaque_payment_status', true) === 'paid') {
    wp_send_json_error(array(
      'message' => 'Este anúncio já possui pagamento de destaque. Valide seu CPF em Minha conta para liberar o destaque, se ainda não fez.',
    ));
    return;
  }

  // Promoção: 1x por anúncio (mesmo que o pagamento anterior tenha sido cancelado no Stripe, mantemos simples por agora)
  if ($tipo === 'promo' && get_post_meta($post_id, BAZAR_META_DESTAQUE_PROMO_USED, true) === '1') {
    wp_send_json_error(array(
      'message' => 'A promoção com desconto já foi usada para este anúncio.',
    ));
    return;
  }

  // Obter dados do usuário usando cache
  $user = bazar_stripe_get_user_cached($user_id);
  $user_email = $user ? $user->user_email : '';

  // Captura UTM no momento do checkout (atribuicao da transacao/boost).
  bazar_save_boost_utm_from_cookie($post_id);

  // Tentar criar Checkout Session via API (permite metadata)
  // IMPORTANTE: Esta função SUBSTITUI os Payment Links quando a chave secreta está configurada
  // Se a chave não estiver configurada, usa Payment Links como fallback
  $checkout_url = bazar_stripe_create_checkout_session(
    $post_id,
    $user_id,
    $user_email,
    $tipo
  );

  // Se falhou, usar Payment Link como fallback
  if (empty($checkout_url)) {
    wp_send_json_error(array(
      'message' => 'Pagamento não configurado. Entre em contato com o administrador.'
    ));
    return;
  }

  // Salvar dados temporários
  bazar_stripe_save_checkout_data(
    $post_id,
    $user_id,
    $tipo
  );

  // Retornar URL
  wp_send_json_success(array(
    'checkout_url' => $checkout_url
  ));
}

// ============================================
// MODO TESTE / PRODUÇÃO (não depender de WP_DEBUG)
// ============================================

/**
 * Define se o Stripe deve usar chave de TESTE ou de PRODUÇÃO.
 * Em produção você DEVE marcar "Usar modo produção" nas configurações do Stripe.
 * Antes a lógica usava WP_DEBUG, o que fazia produção usar chave de teste quando WP_DEBUG era true.
 *
 * @return bool True = usar sk_test_ (cartões de teste aceitos). False = usar sk_live_ (cobrança real).
 */
function bazar_stripe_is_test_mode() {
  $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
  if (strpos($host, 'localhost') !== false || $host === '127.0.0.1') {
    return true; // sempre teste em ambiente local
  }
  $modo_producao = get_option('bazar_stripe_modo_producao', '0');
  return ($modo_producao !== '1');
}

// ============================================
// FUNÇÕES AUXILIARES - CHECKOUT
// ============================================

/**
 * Cria Checkout Session via API do Stripe com metadata
 * Permite enviar post_id, user_id, etc. que aparecerão no painel do Stripe
 * 
 * @param int $post_id ID do anúncio
 * @param int $user_id ID do usuário
 * @param string $user_email Email do usuário
 * @param string $tipo Tipo de checkout ('simples' ou 'newsletter')
 * @return string|false URL do checkout ou false se falhar
 */
function bazar_stripe_create_checkout_session($post_id, $user_id, $user_email, $tipo)
{

  // Obter dados do anúncio usando cache
  $anuncio = bazar_stripe_get_post_cached($post_id);
  if (!$anuncio) {
    error_log('Anúncio não encontrado. ID: ' . $post_id);
    return false;
  }

  // Obter chave secreta do Stripe (modo definido nas configurações, não por WP_DEBUG)
  $is_test_mode = bazar_stripe_is_test_mode();
  $stripe_secret_key = $is_test_mode
    ? get_option('bazar_stripe_secret_key_test', '')
    : get_option('bazar_stripe_secret_key_live', '');

  if (empty($stripe_secret_key)) {
    return false; // Chave não configurada, usar Payment Link como fallback
  }

  // Obter preços
  $preco_normal = 47.90;
  $preco_newsletter = 23.90;

  if (function_exists('bazar_destaque_get_preco')) {
    $preco_normal = bazar_destaque_get_preco(false);
    $preco_newsletter = bazar_destaque_get_preco(true);
  }

  // Determinar preço baseado no tipo
  $preco_promo = round(((float) $preco_normal) * 0.5, 2);
  $amount = ($tipo === 'newsletter') ? $preco_newsletter : (($tipo === 'promo') ? $preco_promo : $preco_normal);
  $amount_cents = intval($amount * 100); // Stripe usa centavos

  $titulo_produto = ($tipo === 'newsletter')
    ? 'Impulsionar Anúncio | Assinatura Newsletter'
    : (($tipo === 'promo') ? 'Impulsionar Anúncio | Promoção' : 'Impulsionar Anúncio');

  $descricao_produto = ($tipo === 'newsletter')
    ? (function_exists('bazar_destaque_get_promo_config') ? (bazar_destaque_get_promo_config()['desconto_percent'] . '% OFF') : '10% OFF') . ' - Newsletter - Impulsionar Anúncio: ' . wp_strip_all_tags($anuncio->post_title)
    : (($tipo === 'promo') ? 'Promoção 50% OFF - Impulsionar Anúncio: ' . wp_strip_all_tags($anuncio->post_title) : 'Impulsionar Anúncio: ' . wp_strip_all_tags($anuncio->post_title));

  // Título curto para metadata Stripe (sempre definido; limite ~100 caracteres)
  $anuncio_titulo = wp_strip_all_tags($anuncio->post_title);
  if (strlen($anuncio_titulo) > 100) {
    $anuncio_titulo = substr($anuncio_titulo, 0, 97) . '...';
  }

  // Obter nome do usuário usando cache
  $user_name = '';
  $user = bazar_stripe_get_user_cached($user_id);
  if ($user) {
    $user_name = trim($user->first_name . ' ' . $user->last_name);
    if (empty($user_name)) {
      $user_name = $user->display_name;
    }
    // Limitar tamanho
    if (strlen($user_name) > 100) {
      $user_name = substr($user_name, 0, 97) . '...';
    }
  }

  // URLs de retorno
  $success_url = home_url('/anuncio-impulsionado/?payment=success&session_id={CHECKOUT_SESSION_ID}&anuncio=' . $post_id);
  $cancel_url = home_url('/anuncio-impulsionado/?payment=canceled&anuncio=' . $post_id);

  // Fazer requisição para API do Stripe
  // Stripe API usa form-urlencoded, não JSON
  $api_url = 'https://api.stripe.com/v1/checkout/sessions';

  // Preparar dados no formato que Stripe aceita (form-urlencoded)
  // IMPORTANTE: Metadados devem ser enviados como metadata[key]=value
  $body_params = array(
    'mode' => 'payment',
    'payment_method_types[]' => 'card',
    'line_items[0][price_data][currency]' => 'brl',
    'line_items[0][price_data][product_data][name]' => $titulo_produto,
    'line_items[0][price_data][product_data][description]' => $descricao_produto,
    'line_items[0][price_data][unit_amount]' => $amount_cents,
    'line_items[0][quantity]' => 1,
    'success_url' => $success_url,
    'cancel_url' => $cancel_url,
    'customer_email' => $user_email,
  );
  // Cupom manual só no fluxo promo. Checkout "simples" = valor cheio, sem campo de código no Stripe.
  if ($tipo === 'promo') {
    $body_params['allow_promotion_codes'] = 'true';
  }

  // Adicionar metadados (formato específico para Stripe)
  // Cada metadado deve ser adicionado como metadata[key]=value
  $body_params['metadata[post_id]'] = (string) $post_id;
  $body_params['metadata[user_id]'] = (string) $user_id;
  $body_params['metadata[tipo]'] = $tipo;
  $body_params['metadata[anuncio_titulo]'] = $anuncio_titulo;
  $body_params['metadata[user_email]'] = $user_email;

  // Adicionar nome do usuário se disponível
  if (!empty($user_name)) {
    $body_params['metadata[user_name]'] = $user_name;
  }

  // Construir body usando http_build_query (funciona corretamente com arrays)
  // O Stripe aceita o formato metadata[key]=value que http_build_query gera
  $body_string = http_build_query($body_params);

  $response = wp_remote_post($api_url, array(
    'headers' => array(
      'Authorization' => 'Bearer ' . $stripe_secret_key,
      'Content-Type' => 'application/x-www-form-urlencoded'
    ),
    'body' => $body_string,
    'timeout' => 15
  ));

  if (is_wp_error($response)) {
    error_log('Stripe API Error: ' . $response->get_error_message());
    return false;
  }

  $status_code = wp_remote_retrieve_response_code($response);
  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  if ($status_code !== 200 || !isset($data['url'])) {
    error_log('Stripe Checkout Session Error (Status: ' . $status_code . '): ' . $body);
    return false;
  }

  // Retornar URL do checkout
  return $data['url'];
}

/**
 * Salva dados temporários do checkout em user_meta
 * 
 * @param int $post_id ID do anúncio
 * @param int $user_id ID do usuário
 * @param string $tipo Tipo de checkout
 */
function bazar_stripe_save_checkout_data($post_id, $user_id, $tipo)
{
  $data = array(
    'post_id' => $post_id,
    'user_id' => $user_id,
    'tipo' => $tipo,
    'timestamp' => current_time('timestamp'),
    'session_id' => null // Será preenchido quando Stripe retornar
  );

  // Salvar em user_meta (mais confiável que transients)
  // Permite buscar facilmente por user_id quando não há parâmetros na URL
  update_user_meta($user_id, 'bazar_checkout_pending', $data);
}

// ============================================
// VERIFICAÇÃO DE PAGAMENTO
// ============================================

/**
 * Verifica se um pagamento foi bem-sucedido via API do Stripe
 * 
 * @param string $session_id ID da sessão do checkout
 * @return array|false Dados do pagamento ou false se não encontrado
 */
function bazar_stripe_verify_payment($session_id)
{

  if (empty($session_id)) {
    return false;
  }

  // Obter chave secreta do Stripe (modo definido nas configurações)
  $is_test_mode = bazar_stripe_is_test_mode();
  $stripe_secret_key = $is_test_mode
    ? get_option('bazar_stripe_secret_key_test', '')
    : get_option('bazar_stripe_secret_key_live', '');

  if (empty($stripe_secret_key)) {
    return false;
  }

  // Fazer requisição para API do Stripe
  $api_url = 'https://api.stripe.com/v1/checkout/sessions/' . $session_id;

  $response = wp_remote_get($api_url, array(
    'headers' => array(
      'Authorization' => 'Bearer ' . $stripe_secret_key
    ),
    'timeout' => 10
  ));

  if (is_wp_error($response)) {
    error_log('Stripe API Error: ' . $response->get_error_message());
    return false;
  }

  $body = wp_remote_retrieve_body($response);
  $status_code = wp_remote_retrieve_response_code($response);

  if ($status_code !== 200) {
    return false;
  }

  $data = json_decode($body, true);

  if (!$data || !isset($data['payment_status'])) {
    return false;
  }

  // Verificar se pagamento foi bem-sucedido
  if ($data['payment_status'] === 'paid') {
    return $data;
  }

  return false;
}

/**
 * Processa pagamento bem-sucedido e ativa destaque
 * 
 * @param string $session_id ID da sessão do checkout
 * @param int $post_id ID do anúncio (opcional, tenta obter do transiente)
 * @return bool True se processado com sucesso
 */
function bazar_stripe_process_payment_success($session_id, $post_id = 0)
{

  // Verificar pagamento via API
  $payment_data = bazar_stripe_verify_payment($session_id);

  if (!$payment_data) {
    return false;
  }

  // Obter user_id
  $user_id = get_current_user_id();

  // Buscar dados do user_meta usando cache (post_id e tipo)
  $checkout_data = null;
  if (!empty($user_id)) {
    $checkout_data = bazar_stripe_get_checkout_data_from_user_meta(
      $user_id,
      $post_id
    );
  }

  // Se não tiver post_id, tentar obter do user_meta
  if (
    empty($post_id)
    && $checkout_data
    && isset($checkout_data['post_id'])
  ) {
    $post_id = intval($checkout_data['post_id']);
  }

  if (empty($post_id) || empty($user_id)) {
    return false;
  }

  // Fallback de captura de UTM para cenarios em que o checkout nao registrou antes.
  bazar_save_boost_utm_from_cookie($post_id);

  // Atualizar user_meta com session_id (para rastreamento)
  if (
    $checkout_data
    && empty($checkout_data['session_id'])
  ) {
    $checkout_data['session_id'] = $session_id;
    update_user_meta($user_id, 'bazar_checkout_pending', $checkout_data);
  }

  // Verificar se já foi processado
  $existing_payment_id = get_post_meta($post_id, 'destaque_payment_id', true);
  if (
    !empty($existing_payment_id)
    && $existing_payment_id === $session_id
  ) {
    // Limpar user_meta após processamento bem-sucedido
    delete_user_meta($user_id, 'bazar_checkout_pending');
    return true; // Já foi processado
  }

  // Verificar se anúncio ainda existe e usuário é o autor usando cache
  $post = bazar_stripe_get_post_cached($post_id);
  if (
    !$post
    || intval($post->post_author) !== intval($user_id)
  ) {
    return false;
  }

  // Verificar se já está em destaque
  if (has_term('destaque', 'status', $post_id)) {
    return true; // Já está em destaque
  }

  // Determinar tipo de pagamento
  // IMPORTANTE: Não usar apenas o preço, pois é frágil (pode mudar, ter arredondamentos, etc.)
  // Usar sistema de prioridades para garantir identificação correta:

  $tipo = 'simples'; // padrão seguro

  // Prioridade 1: Tipo do user_meta (MAIS CONFIÁVEL)
  // Salvo durante checkout, contém o tipo exato escolhido pelo usuário
  if (
    $checkout_data
    && isset($checkout_data['tipo'])
    && in_array(
      $checkout_data['tipo'],
      array('simples', 'newsletter', 'promo'),
      true
    )
  ) {
    $tipo = $checkout_data['tipo'];
  }
  // Prioridade 2: Metadata do Stripe (se Payment Link tiver metadata configurado)
  // Payment Links podem ter metadata, mas não é obrigatório
  elseif (
    isset($payment_data['metadata']['tipo'])
    && in_array($payment_data['metadata']['tipo'], array('simples', 'newsletter', 'promo'), true)
  ) {
    $tipo = $payment_data['metadata']['tipo'];
  }

  // Obter dados do anúncio e usuário para metadata usando cache
  $anuncio_titulo = ($post)
    ? wp_strip_all_tags($post->post_title)
    : 'Anúncio #' . $post_id;

  if (strlen($anuncio_titulo) > 100) {
    $anuncio_titulo = substr($anuncio_titulo, 0, 97) . '...';
  }

  $user = bazar_stripe_get_user_cached($user_id);
  $user_email = $user ? $user->user_email : '';
  $user_name = '';
  if ($user) {
    $user_name = trim($user->first_name . ' ' . $user->last_name);
    if (empty($user_name)) {
      $user_name = $user->display_name;
    }
    if (strlen($user_name) > 100) {
      $user_name = substr($user_name, 0, 97) . '...';
    }
  }

  // Atualizar metadados no Payment Intent (para aparecer no painel do Stripe)
  // IMPORTANTE: Metadados da Checkout Session não aparecem no painel, precisam estar no Payment Intent
  if (
    isset($payment_data['payment_intent'])
    && !empty($payment_data['payment_intent'])
  ) {
    $payment_intent_id = $payment_data['payment_intent'];
    bazar_stripe_update_payment_intent_metadata(
      $payment_intent_id,
      $post_id,
      $user_id,
      $tipo,
      $anuncio_titulo,
      $user_email,
      $user_name
    );
  }

  // Ativar destaque
  $amount_total_cents = isset($payment_data['amount_total']) ? intval($payment_data['amount_total']) : 0;
  $amount_subtotal_cents = isset($payment_data['amount_subtotal']) ? intval($payment_data['amount_subtotal']) : 0;
  $amount_discount_cents = 0;
  if (isset($payment_data['total_details']) && is_array($payment_data['total_details'])) {
    $amount_discount_cents = isset($payment_data['total_details']['amount_discount'])
      ? intval($payment_data['total_details']['amount_discount'])
      : 0;
  } elseif ($amount_subtotal_cents > 0 && $amount_total_cents >= 0 && $amount_subtotal_cents > $amount_total_cents) {
    // Fallback quando total_details não vier no payload.
    $amount_discount_cents = max(0, $amount_subtotal_cents - $amount_total_cents);
  }

  bazar_stripe_ativar_destaque(
    $post_id,
    $user_id,
    $session_id,
    $tipo,
    $amount_total_cents,
    $amount_discount_cents
  );

  // Limpar user_meta após processamento bem-sucedido
  delete_user_meta($user_id, 'bazar_checkout_pending');

  return true;
}

/**
 * Salva UTM do boost no anuncio a partir do cookie atual.
 *
 * @param int $post_id
 * @return void
 */
function bazar_save_boost_utm_from_cookie($post_id)
{
  $post_id = (int) $post_id;
  if ($post_id <= 0) {
    return;
  }
  $utm_service = bazar_get_utm_service_instance();
  if (!$utm_service) {
    return;
  }
  $utm_service->save_boost_utm($post_id);
}

// ============================================
// FUNÇÕES AUXILIARES - METADATA
// ============================================

/**
 * Atualiza metadados no Payment Intent (para aparecer no painel do Stripe)
 * IMPORTANTE: Metadados devem estar no Payment Intent, não apenas na Checkout Session
 * 
 * @param string $payment_intent_id ID do Payment Intent
 * @param int $post_id ID do anúncio
 * @param int $user_id ID do usuário
 * @param string $tipo Tipo de checkout
 * @param string $anuncio_titulo Título do anúncio
 * @param string $user_email Email do usuário
 * @param string $user_name Nome do usuário
 * @return bool True se atualizado com sucesso
 */
function bazar_stripe_update_payment_intent_metadata($payment_intent_id, $post_id, $user_id, $tipo, $anuncio_titulo, $user_email, $user_name = '')
{

  // Obter chave secreta do Stripe (modo definido nas configurações)
  $is_test_mode = bazar_stripe_is_test_mode();
  $stripe_secret_key = $is_test_mode
    ? get_option('bazar_stripe_secret_key_test', '')
    : get_option('bazar_stripe_secret_key_live', '');

  if (empty($stripe_secret_key)) {
    return false;
  }

  // Preparar metadados
  $body_params = array(
    'metadata[post_id]' => (string) $post_id,
    'metadata[user_id]' => (string) $user_id,
    'metadata[tipo]' => $tipo,
    'metadata[anuncio_titulo]' => $anuncio_titulo,
    'metadata[user_email]' => $user_email
  );

  if (!empty($user_name)) {
    $body_params['metadata[user_name]'] = $user_name;
  }

  // Atualizar Payment Intent via API
  $api_url = 'https://api.stripe.com/v1/payment_intents/' . $payment_intent_id;

  $response = wp_remote_post($api_url, array(
    'headers' => array(
      'Authorization' => 'Bearer ' . $stripe_secret_key,
      'Content-Type' => 'application/x-www-form-urlencoded'
    ),
    'body' => http_build_query($body_params),
    'timeout' => 10,
    'method' => 'POST' // Stripe usa POST para atualizar
  ));

  if (is_wp_error($response)) {
    error_log('Stripe Payment Intent Metadata Error: ' . $response->get_error_message());
    return false;
  }

  $status_code = wp_remote_retrieve_response_code($response);

  if ($status_code !== 200) {
    $body = wp_remote_retrieve_body($response);
    error_log('Stripe Payment Intent Metadata Error (Status: ' . $status_code . '): ' . $body);
    return false;
  }

  return true;
}

// ============================================
// FUNÇÕES AUXILIARES - PROCESSAMENTO
// ============================================


/**
 * Obtém dados completos do checkout do user_meta
 * 
 * @param int $user_id ID do usuário
 * @param int $post_id ID do anúncio (opcional, para validação)
 * @return array|null Dados do checkout ou null se não encontrado
 * @return array array(
 *     'post_id' => $post_id,
 *     'user_id' => $user_id,
 *     'tipo' => $tipo,
 *     'timestamp' => current_time('timestamp'),
 *     'session_id' => null|string
 * )         
 */
function bazar_stripe_get_checkout_data_from_user_meta($user_id, $post_id = 0)
{
  // Buscar do user_meta usando cache
  $checkout_data = bazar_stripe_get_user_meta_cached($user_id, 'bazar_checkout_pending', true);

  if (empty($checkout_data) || !is_array($checkout_data)) {
    return null;
  }

  // Se foi passado post_id, validar se corresponde
  if ($post_id > 0 && isset($checkout_data['post_id']) && intval($checkout_data['post_id']) !== intval($post_id)) {
    return null; // Não corresponde ao post_id esperado
  }

  // Verificar se não expirou (1 hora)
  $expire_time = HOUR_IN_SECONDS;
  if (isset($checkout_data['timestamp']) && (current_time('timestamp') - intval($checkout_data['timestamp'])) > $expire_time) {
    // Expirado, limpar
    delete_user_meta($user_id, 'bazar_checkout_pending');
    return null;
  }

  return $checkout_data;
}

/**
 * Ativa destaque do anúncio
 *
 * @param int $post_id ID do anúncio
 * @param int $user_id ID do usuário
 * @param string $session_id ID da sessão do Stripe
 * @param string $tipo Tipo de destaque ('simples', 'newsletter' ou 'promo')
 * @param int $amount_total_cents Valor final pago (centavos)
 * @param int $amount_discount_cents Valor de desconto aplicado (centavos)
 */
function bazar_stripe_ativar_destaque($post_id, $user_id, $session_id, $tipo, $amount_total_cents = 0, $amount_discount_cents = 0)
{

  // Metas de pagamento (sempre registradas; pagamento não é bloqueado)
  update_post_meta($post_id, 'destaque_payment_id', $session_id);
  update_post_meta($post_id, 'destaque_payment_status', 'paid');
  update_post_meta($post_id, 'destaque_tipo', $tipo);
  update_post_meta($post_id, 'destaque_data_ativacao', current_time('timestamp'));
  update_post_meta($post_id, 'destaque_amount_total_cents', intval($amount_total_cents));
  update_post_meta($post_id, 'destaque_amount_discount_cents', intval($amount_discount_cents));
  update_post_meta($post_id, 'destaque_coupon_used', intval($amount_discount_cents) > 0 ? '1' : '0');

  if (function_exists('bazar_destaque_service_sync_visibility_after_paid')) {
    bazar_destaque_service_sync_visibility_after_paid($post_id, $user_id);
  }

  // Promoção (ex.: 50% Instagram): 1x por anúncio + inclusão na newsletter (termos da campanha).
  if ($tipo === 'promo') {
    update_post_meta($post_id, BAZAR_META_DESTAQUE_PROMO_USED, '1');
    if (function_exists('bazar_newsletter_add_user_if_missing')) {
      bazar_newsletter_add_user_if_missing($user_id);
    }
  }

  // Fluxo “preço newsletter”: inscreve na base e marca desconto newsletter como usado (meta separada da promo).
  if ($tipo === 'newsletter') {
    if (function_exists('bazar_newsletter_add_user_if_missing')) {
      bazar_newsletter_add_user_if_missing($user_id);
    }
    if (function_exists('bazar_set_desconto_newsletter_on_user_data')) {
      bazar_set_desconto_newsletter_on_user_data($user_id);
    }
  }
}