<?php
add_action('wp_ajax_bazar_login_user', 'bazar_login_user');
add_action('wp_ajax_nopriv_bazar_login_user', 'bazar_login_user');
function bazar_login_user()
{
  $object = new __Bazar_Login_User();
  wp_die();
}

class __Bazar_Login_User extends __Bazar_Error_Handler
{

  public $label = 'login_user';
  public $action;
  public $nonce;
  public $post_id;
  public $update_data = false;
  public $data_output;
  private $validations;
  private $campos_obrigatorios;

  // Configurações de proteção contra força bruta
  private $max_attempts_per_email = 5; // Máximo de tentativas por email
  private $lockout_period = 900; // 15 minutos em segundos

  public function __construct()
  {

    // Limpar qualquer output anterior
    if (ob_get_level()) {
      ob_clean();
    }

    try {
      $this->validations = new __BazarValidations();
      $this->inicializar_resposta_padrao();
      $this->processar_login();

    } catch (Exception $e) {
      $this->definir_erro_excecao($e);
    }

    // Garantir que apenas JSON seja retornado
    header('Content-Type: application/json');
    echo json_encode($this->data_output);
    exit;
  }

  /**
   * Processa o login do usuário
   */
  private function processar_login()
  {

    // Verificar segurança e método POST
    if (!$this->verificar_seguranca()) {
      return false;
    }

    // Validar dados
    if (!$this->validar_dados()) {
      return false;
    }

    // Preparar dados do usuário
    $user_data = $this->preparar_dados_usuario();
    if (!$user_data) {
      $this->definir_erro_post_vazio();
      return false;
    }

    // Verificar se usuário existe
    if (!$this->verificar_usuario_existe($user_data['user_login'])) {
      return false;
    }

    // Verificar proteção contra força bruta (apenas se usuário existe)
    if (!$this->verificar_protecao_forca_bruta($user_data['user_login'])) {
      return false;
    }

    // Realizar login
    $login_success = $this->realizar_login($user_data);

    if (!$login_success) {
      // Registrar tentativa falha
      $this->registrar_tentativa_falha($user_data['user_login']);
      return false;
    }

    // Se login foi bem-sucedido, limpar tentativas falhas
    $this->limpar_tentativas_falhas($user_data['user_login']);

    // Definir resposta de sucesso
    $this->definir_resposta_sucesso($user_data['user_login']);

    return true;
  }

  /**
   * Define campos obrigatórios para login
   */
  private function setCamposObrigatorios()
  {
    $this->campos_obrigatorios = [
      'user_email',
      'user_pass'
    ];
  }

  /**
   * Valida dados do formulário
   */
  private function validar_dados()
  {

    if (isset($_POST) && empty($_POST)) {
      $this->definir_erro_post_vazio();
      return false;
    }

    // setar campos obrigatórios
    $this->setCamposObrigatorios();

    // Percorrer $_POST e validar cada campo
    foreach ($_POST as $field_name => $valor) {

      // Limpar espaços em branco
      $valor_limpo = trim($valor);

      // Verificar se o campo é obrigatório e está vazio
      if (
        in_array($field_name, $this->campos_obrigatorios)
        && empty($valor_limpo)
      ) {
        $this->definir_erro_campo_obrigatorio($field_name);
        return false;
      }

      // Validações específicas 
      if ($field_name === 'user_email') {
        if (!$this->validations->__BAZAR_validaEmail($valor_limpo)) {
          $this->definir_erro_campos_invalidos(
            'Por favor, digite um <b>e-mail válido</b>.',
            'user_email'
          );
          return false;
        }
      }
    }

    return true;
  }

  /**
   * Prepara dados do usuário a partir do POST
   */
  private function preparar_dados_usuario()
  {
    return array(
      'user_login' => sanitize_email($_POST['user_email']),
      'user_password' => wp_strip_all_tags($_POST['user_pass']),
      'remember' => true,
    );
  }

  /**
   * Verifica se o usuário existe no sistema
   */
  private function verificar_usuario_existe($user_login)
  {

    if (!username_exists($user_login) && !email_exists($user_login)) {
      $this->definir_erro_email_nao_cadastrado();
      return false;
    }

    // Obter objeto do usuário
    $user = $this->obter_usuario($user_login);
    if (!$user) {
      return true; // Se não encontrou, já foi tratado em verificar_usuario_existe
    }

    // Verificar se usuário está bloqueado
    if (!$this->verificar_usuario_bloqueado($user)) {
      return false;
    }

    // Verificar se usuário está cancelado
    if (!$this->verificar_usuario_cancelado($user)) {
      return false;
    }

    // Exigir confirmação de e-mail antes do login
    if (!$this->verificar_usuario_email_confirmado($user)) {
      return false;
    }

    return true;
  }

  /**
   * Obtém objeto do usuário por email ou login
   * 
   * @param string $user_login Email ou login do usuário
   * @return WP_User|false Objeto do usuário ou false se não encontrado
   */
  private function obter_usuario($user_login)
  {
    $user = get_user_by('email', $user_login);
    if (!$user) {
      $user = get_user_by('login', $user_login);
    }
    return $user;
  }

  /**
   * Verifica se o usuário está bloqueado
   * 
   * @param WP_User $user Objeto do usuário
   * @return bool true se não está bloqueado, false se está bloqueado
   */
  private function verificar_usuario_bloqueado($user)
  {
    if (!$user) {
      return true;
    }

    $is_blocked = get_user_meta($user->ID, 'bazar_user_blocked', true);
    $is_blocked = ($is_blocked === 'true' || $is_blocked === true || $is_blocked === '1' || $is_blocked === 1);

    if ($is_blocked) {
      $this->definir_erro_campos_invalidos(
        'Usuário bloqueado. Entre em contato com o suporte.',
        'user_email'
      );
      return false;
    }

    return true;
  }

  /**
   * Verifica se o usuário está cancelado
   * Retorna resposta especial com opção de reativação se estiver cancelado
   * 
   * @param WP_User $user Objeto do usuário
   * @return bool true se não está cancelado, false se está cancelado
   */
  private function verificar_usuario_cancelado($user)
  {
    if (!$user) {
      return true;
    }

    $is_cancelled = get_user_meta($user->ID, 'bazar_user_cancelled', true);
    $is_cancelled = ($is_cancelled === 'true' || $is_cancelled === true || $is_cancelled === '1' || $is_cancelled === 1);

    if ($is_cancelled) {
      // Retornar resposta especial com opção de reativação
      $this->data_output = array(
        'success' => false,
        'cancelled' => true,
        'title' => 'Conta cancelada',
        'msg' => 'Seu cadastro está cancelado, deseja ativar?',
        'user_email' => $user->user_email,
        'show_reactivate' => true
      );
      return false;
    }

    return true;
  }

  /**
   * Verifica se o usuário confirmou o e-mail.
   * Se não confirmou, bloqueia login e direciona para fluxo de confirmação.
   *
   * @param WP_User $user
   * @return bool
   */
  private function verificar_usuario_email_confirmado($user)
  {
    if (!$user || !function_exists('bazar_usuario_email_confirmado_meta')) {
      return true;
    }

    // Admin/equipe não entram nesse bloqueio.
    if (user_can($user, 'manage_options')) {
      return true;
    }

    if (bazar_usuario_email_confirmado_meta((int) $user->ID)) {
      return true;
    }

    $target = $this->get_redirect_target_from_request();
    if (empty($target)) {
      // Sem destino explícito, priorizar o fluxo de endereço após confirmação de e-mail.
      $target = home_url('/cadastro-endereco/');
    }
    $confirmar_url = add_query_arg('redirect_to', $target, home_url('/confirmar-email/'));

    $this->data_output = array(
      'submit' => false,
      'title' => 'Confirme seu e-mail',
      'msg' => '<span>É preciso confirmar seu e-mail para entrar. <a href="' . esc_url($confirmar_url) . '">Reenviar código.</a></span>.',
      'redirect' => false,
      'confirm_url' => $confirmar_url,
      'email_pending' => true,
    );
    return false;
  }

  /**
   * Realiza o login do usuário
   */
  private function realizar_login($user_data)
  {

    // Obter objeto do usuário
    $user = $this->obter_usuario($user_data['user_login']);
    if (!$user) {
      // Se não encontrou usuário, wp_signon vai retornar erro
      // Não precisa tratar aqui
    } else {
      // Verificar se usuário está bloqueado
      if (!$this->verificar_usuario_bloqueado($user)) {
        return false;
      }

      // Verificar se usuário está cancelado
      if (!$this->verificar_usuario_cancelado($user)) {
        return false;
      }
    }

    // Em HTTPS (produção) o cookie deve ser Secure para o navegador enviá-lo em requisições ao wp-admin
    $secure_cookie = is_ssl();
    $user_signon = wp_signon($user_data, $secure_cookie);

    if (is_wp_error($user_signon)) {
      $this->definir_erro_campos_invalidos(
        'E-mail ou senha incorretos.',
        'user_email'
      );
      return false;
    }
    return true;
  }


  /**
   * Verifica proteção contra força bruta
   * Usa user_meta para rastrear tentativas (mais simples e eficiente)
   * 
   * @param string $user_login Email ou login do usuário
   * @return bool true se pode continuar, false se está bloqueado
   */
  private function verificar_protecao_forca_bruta($user_login)
  {

    $user = $this->obter_usuario($user_login);
    if (!$user) {
      return true; // Se usuário não existe, não bloquear (já será tratado em verificar_usuario_existe)
    }

    // Obter tentativas falhas do usuário
    $attempts_data = get_user_meta($user->ID, 'bazar_login_failed_attempts', true);

    if (empty($attempts_data)) {
      return true; // Sem tentativas anteriores
    }

    // Verificar se está bloqueado
    $last_attempt = isset($attempts_data['last_attempt']) ? (int) $attempts_data['last_attempt'] : 0;
    $attempts_count = isset($attempts_data['count']) ? (int) $attempts_data['count'] : 0;

    // Se excedeu limite, verificar se ainda está no período de bloqueio
    if ($attempts_count >= $this->max_attempts_per_email) {
      $time_since_last = time() - $last_attempt;

      if ($time_since_last < $this->lockout_period) {
        // Ainda está bloqueado
        $remaining_time = $this->lockout_period - $time_since_last;
        $minutes = ceil($remaining_time / 60);

        $this->definir_erro_seguranca(
          'Muitas tentativas de login falharam. Tente novamente mais tarde ou entre em contato com o suporte.'
        );
        return false;
      } else {
        // Período de bloqueio expirou, resetar contador
        delete_user_meta($user->ID, 'bazar_login_failed_attempts');
      }
    }

    return true;
  }

  /**
   * Registra tentativa de login falha
   * Usa user_meta (mais simples que transients)
   * 
   * @param string $user_login Email ou login do usuário
   */
  private function registrar_tentativa_falha($user_login)
  {

    $user = $this->obter_usuario($user_login);
    if (!$user) {
      return; // Se usuário não existe, não registrar
    }

    $attempts_data = get_user_meta($user->ID, 'bazar_login_failed_attempts', true);

    if (empty($attempts_data)) {
      $attempts_data = array(
        'count' => 1,
        'first_attempt' => time(),
        'last_attempt' => time()
      );
    } else {
      $attempts_data['count'] = (int) $attempts_data['count'] + 1;
      $attempts_data['last_attempt'] = time();

      // Se é a primeira tentativa após período de bloqueio, resetar
      if (!isset($attempts_data['first_attempt'])) {
        $attempts_data['first_attempt'] = time();
      }
    }

    update_user_meta($user->ID, 'bazar_login_failed_attempts', $attempts_data);
  }

  /**
   * Limpa tentativas falhas após login bem-sucedido
   * 
   * @param string $user_login Email ou login do usuário
   */
  private function limpar_tentativas_falhas($user_login)
  {

    $user = $this->obter_usuario($user_login);
    if (!$user) {
      return;
    }

    delete_user_meta($user->ID, 'bazar_login_failed_attempts');
  }

  /**
   * Define resposta de sucesso
   */
  private function definir_resposta_sucesso($user_login = '')
  {
    $user = $this->obter_usuario($user_login);
    $redirect_url = $this->resolve_redirect_after_login($user);

    $this->data_output = array(
      'submit' => true,
      'title' => 'Bem-vindo!',
      'msg' => 'O que deseja fazer:',
      'redirect' => $redirect_url,
    );
  }

  /**
   * Obtém o redirect alvo enviado pelo formulário (sanitizado).
   *
   * @return string
   */
  private function get_redirect_target_from_request()
  {
    $redirect_url = '';
    if (isset($_POST['redirect']) && !empty($_POST['redirect'])) {
      $redirect_url = function_exists('bazar_sanitize_redirect_url')
        ? bazar_sanitize_redirect_url($_POST['redirect'])
        : esc_url_raw($_POST['redirect']);
    }
    return $redirect_url;
  }

  /**
   * Resolve redirect pós-login:
   * - Se perfil sem endereço mínimo: força /cadastro-endereco/?redirect_to=destino
   * - Caso contrário: destino normal.
   *
   * @param WP_User|false $user
   * @return string
   */
  private function resolve_redirect_after_login($user = false)
  {
    $target = $this->get_redirect_target_from_request();
    if (empty($target)) {
      $target = get_bloginfo('url') . '/entrar/';
    }

    if (!$user) {
      return $target;
    }

    // Admin/equipe não entram no gate de endereço.
    if (user_can($user, 'manage_options')) {
      return $target;
    }

    if (function_exists('bazar_user_has_min_address_meta') && !bazar_user_has_min_address_meta((int) $user->ID)) {
      return add_query_arg(
        array(
          'redirect_to' => $target,
          'from_login' => '1',
        ),
        home_url('/cadastro-endereco/')
      );
    }

    return $target;
  }

}
?>