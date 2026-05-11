<?php
/**
 * Classe base para gerenciamento centralizado de mensagens de erro
 * Estende __Bazar_Verify e fornece métodos comuns de definição de erros
 */
class __Bazar_Error_Handler extends __Bazar_Verify
{


  /**
   * Verifica se está em ambiente de produção
   */
  protected function is_production()
  {
    return bazar_is_production();
  }

  /**
   * Verifica se um valor de campo está vazio
   * @param mixed $value - Valor do campo
   * @return bool true se campo está vazio
   */
  protected function is_field_empty($value)
  {
    // Se for null ou undefined, está vazio
    if ($value === null || $value === false) {
      return true;
    }

    // Para arrays, verificar se está vazio ou se todos os valores estão vazios
    if (is_array($value)) {
      // Se array está vazio, retornar true
      if (count($value) === 0) {
        return true;
      }
      // Se array está vazio, retornar true
      if (empty($value[0])) {
        return true;
      }
      return false;
    }

    // Para strings, verificar se está vazio após trim
    if (is_string($value)) {
      return empty(trim($value));
    }

    // Para números, 0 é considerado vazio para campos obrigatórios
    if (is_numeric($value)) {
      return ($value == 0);
    }

    // Outros tipos são considerados não vazios
    return false;
  }

  /**
   * Inicializa resposta padrão
   */
  protected function inicializar_resposta_padrao()
  {
    $this->data_output = array(
      'submit' => false,
      'title' => 'Erro no processamento!',
      'msg' => '<span>Erro interno do servidor. Tente novamente.</span>',
      'log' => array(
        'debug_log' => 'Inicializa resposta padrão',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A'
      ),
    );
  }

  /**
   * Verifica segurança e método POST
   */
  protected function verificar_seguranca()
  {
    // Verificar método POST primeiro
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->definir_erro_post_vazio();
      return false;
    }

    // Para multipart/form-data, verificar se há dados em $_POST ou $_FILES
    $has_post_data = isset($_POST) && !empty($_POST);
    $has_files_data = isset($_FILES) && !empty($_FILES);

    if (!$has_post_data && !$has_files_data) {
      $this->definir_erro_post_vazio();
      return false;
    }

    // Verificar segurança (nonce e action)
    $check_security = $this->_bazar_check_security();
    if (!$check_security) {
      $this->definir_erro_nonce();
      return false;
    }

    return true;
  }

  /**
   * Define erro de segurança
   */
  protected function definir_erro_nonce()
  {

    $msg = '<span>Esta página expirou, <a href="#" title="Atualizar a página" onclick="window.location.reload(true);">atualize.</a> para continuar.</span>';

    $this->data_output = array(
      'submit' => false,
      'title' => 'Erro de <b>segurança</b>!',
      'msg' => $msg,
      'log' => array(
        'debug_log' => 'Erro de segurança. Nonce expirado'
      ),
    );

    $this->log_debug('definir_erro_nonce', 'Erro de segurança. Nonce expirado');
  }


  protected function get_field_label($field_name, $field_label = null)
  {

    if (!$field_name && $field_name == '')
      return '';

    // Preparar nome do campo para formatação (remover índices de arrays)
    $field_name = preg_replace('/\[\d+\]/', '', $field_name);

    $label = '';
    // Se field_label foi fornecido, usar diretamente (prioridade máxima)
    if ($field_label) {
      $label = $field_label;
    } else {
      // Se não há label fornecido, tentar formatação brasileira
      $label = ucfirst(str_replace('_', ' ', $field_name));
      if (property_exists($this, 'validations') && $this->validations instanceof __BazarValidations) {
        $label = $this->validations->__BAZAR_formatBrazilianNames($label);
      }
    }

    return $label;
  }

  /**
   * Define erro de campo obrigatório
   * @param string $field_name Nome do campo
   * @param string|null $custom_msg Mensagem personalizada (opcional)
   */
  protected function definir_erro_campo_obrigatorio($field_name, $field_label = null, $custom_msg = null)
  {

    $field = $this->get_field_label($field_name, $field_label);

    $msg = $custom_msg ?: $field . ' é obrigatório.';

    $this->data_output = array(
      'submit' => false,
      'title' => 'Campo obrigatório!',
      'msg' => '<span>' . $msg . '</span>',
      'field_name' => $field_name,
      'log' => array(
        'debug_log' => 'Campo obrigatório faltando ou vazio: ' . $field . ' | Valor: ' . (isset($_POST[$field_name]) ? json_encode($_POST[$field_name]) : 'não existe')
      ),
    );

    $this->log_debug(
      'required_fields',
      'Campo obrigatório faltando ou vazio: ' . $field . ' | Valor: ' . (isset($_POST[$field_name]) ? json_encode($_POST[$field_name]) : 'não existe')
    );

  }

  /**
   * Define erro de campos inválidos
   * @param string|null $custom_msg Mensagem personalizada (opcional)
   */
  protected function definir_erro_campos_invalidos($custom_msg = null, $filed_name = null, $debug_log = null)
  {

    if (!$custom_msg):
      $label = $this->get_field_label($filed_name);
    endif;

    $msg = ($custom_msg)
      ? $custom_msg
      : $label . ' é inválido. Verifique os valores preenchidos.';

    $this->data_output = array(
      'submit' => false,
      'title' => 'Campos inválidos!',
      'msg' => '<span>' . $msg . '</span>',
      'field_name' => $filed_name,
      'log' => array(
        'debug_log' => $debug_log
      )
    );

    $this->log_debug(
      'required_fields',
      'Campos inválidos: ' . $filed_name . ' | ' . $debug_log
    );

  }



  /**
   * Define erro de email não cadastrado
   * @param string|null $field_name Nome do campo (padrão: 'user_email')
   * @param string|null $custom_msg Mensagem personalizada (opcional)
   */
  protected function definir_erro_email_nao_cadastrado($field_name = 'user_email', $custom_msg = null)
  {

    $msg = $custom_msg
      ? $custom_msg
      : '<span>Este <b>e-mail</b> não está cadastrado. <a href="' . get_bloginfo('url') . '/cadastro">Cadastre-se</a>.</span>';

    $this->data_output = array(
      'submit' => false,
      'title' => 'Erro de <b>segurança</b>!',
      'msg' => $msg,
      'field_name' => $field_name,
    );

    $this->log_debug(
      'definir_erro_email_nao_cadastrado',
      '<span>Erro de email não cadastrado. Email: ' . $field_name . '</span>',
      true
    );
  }

  /**
   * Define erro de email existente
   * @param string|null $field_name Nome do campo (padrão: 'user_email')
   */
  protected function definir_erro_email_existente($field_name = 'user_email')
  {

    $this->data_output = array(
      'submit' => false,
      'title' => 'Erro de <b>segurança</b>!',
      'msg' => '<span>Este <b>e-mail</b> já está sendo <b>utilizado</b> em nosso site! Entre <a href="' . get_bloginfo('url') . '/entrar/" title="Entrar">aqui</a>.</span>',
      'field_name' => $field_name,
      'log' => array(
        'debug_log' => 'Erro de email existente. Email: ' . $field_name
      )
    );

    $this->log_debug(
      'definir_erro_email_existente',
      'Erro de email existente. Email: ' . $field_name,
      true
    );
  }

  /**
   * Define erro de POST vazio
   */
  protected function definir_erro_post_vazio()
  {
    $this->data_output = array(
      'submit' => false,
      'title' => 'Dados não recebidos!',
      'msg' => '<span>Nenhum dado foi enviado.</span>',
      'log' => array(
        'post_empty' => true,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A'
      ),
    );

    $this->log_debug('definir_erro_post_vazio', 'POST vazio');
  }

  /**
   * Define erro de exceção
   * @param Exception|Error $exception
   */
  protected function definir_erro_excecao($exception, $key = 'definir_erro_excecao')
  {
    $this->data_output = array(
      'submit' => false,
      'title' => 'Erro no processamento!',
      'msg' => '<span>Erro interno do servidor. Tente novamente.</span>',
      'debug' => $exception->getMessage(),
    );

    $this->log_debug($key, 'Exceção capturada: ' . $exception->getMessage());
    $this->log_debug($key, 'Stack trace: ' . $exception->getTraceAsString(), true);
    $this->log_debug($key, 'Arquivo: ' . $exception->getFile() . ' Linha: ' . $exception->getLine());
  }

  /**
   * Define erro de servidor
   * @param string|null $custom_msg Mensagem personalizada (opcional)
   */
  protected function definir_erro_servidor($custom_msg = null, $key = 'error_server', $erro = null)
  {

    $msg = $custom_msg ?: 'Oops! Falha ao processar sua solicitação! Tente mais tarde.';

    $this->data_output = array(
      'submit' => false,
      'title' => 'Erro no servidor!',
      'msg' => '<span>' . $msg . '</span>',
      'log' => array(
        'debug_log' => 'Erro no servidor: ' . $msg
      )
    );

    $msg_erro = ($erro !== null) ? 'Erro no servidor: ' . $erro : $msg;

    $this->log_debug($key, $msg_erro);

  }


  /**
   * Define erro de servidor
   * @param string|null $custom_msg Mensagem personalizada (opcional)
   */
  protected function definir_erro_seguranca($custom_msg = null)
  {
    $msg = $custom_msg ?: 'Oops! Falha de segurança! Tente mais tarde.';

    $this->data_output = array(
      'submit' => false,
      'title' => 'Erro de segurança!',
      'msg' => '<span>' . $msg . '</span>',
      'log' => array(
        'debug_log' => 'Erro de segurança: ' . $msg
      )
    );

    $this->log_debug(
      'error_security',
      'Erro de segurança: ' . $msg
    );

  }



  /**
   * Define erro de CPF existente
   * @param string|null $field_name Nome do campo (padrão: 'cpf')
   */
  protected function definir_erro_cpf_existente($field_name = 'cpf')
  {
    $this->data_output = array(
      'submit' => false,
      'title' => 'Erro de <b>segurança</b>!',
      'msg' => '<span>Este <b>CPF</b> já está sendo <b>utilizado</b> em nosso site! Entre <a href="' . get_bloginfo('url') . '/entrar/" title="Entrar">aqui</a>.</span>',
      'field_name' => $field_name,
    );

    $this->log_debug(
      'error_cpf_existente',
      'Erro de CPF existente: ' . $field_name
    );

  }

  /**
   * Define erro de CPF não existe na API
   * @param string|null $field_name Nome do campo (padrão: 'cpf')
   */
  protected function definir_erro_cpf_nao_existe($field_name = 'cpf')
  {
    $this->data_output = array(
      'submit' => false,
      'title' => 'CPF não existe!',
      'msg' => '<span>CPF informado não encontrado.</span>',
      'field_name' => $field_name,
    );

    $this->log_debug(
      'error_cpf_nao_existe',
      'Erro de CPF não existe: ' . $field_name
    );

  }


  /**
   * Define erro de texto fake/genérico
   * @param string $field_name Nome do campo
   */
  protected function definir_erro_texto_fake($field_name)
  {

    $field_label = property_exists($this, 'validations') && $this->validations instanceof __BazarValidations
      ? $this->validations->__BAZAR_formatBrazilianNames($field_name)
      : ucfirst(strtolower($field_name));

    $this->data_output = array(
      'submit' => false,
      'title' => 'Texto inválido!',
      'msg' => 'O campo <b>' . $field_label . '</b> deve conter informações reais e não texto genérico ou de teste.',
      'field_name' => $field_name,
    );

    $this->log_debug(
      'error_text_fake',
      'Erro de texto fake: ' . $field_name
    );

  }

  /**
   * Define erro de envio de email
   * @param string|array $debug_log Log de debug
   * @param string|null $custom_msg Mensagem personalizada (opcional)
   */
  protected function definir_erro_envio_email($debug_log, $custom_msg = null)
  {

    if (is_array($debug_log)) {
      $debug_log = implode("\n", $debug_log);
    }

    $msg = $custom_msg ?: 'Houve um erro ao tentar enviar o email. Favor, tente mais tarde!';

    $this->data_output = array(
      'submit' => false,
      'title' => 'Erro ao enviar e-mail!',
      'msg' => $msg,
      'log' => $debug_log,
      'debug' => $debug_log,
    );

    $this->log_debug('error_email_send', 'Erro ao enviar email: ' . $msg);

  }

  /**
   * Processa retorno de send_mail_msg() e adiciona alerta ao data_output se necessário
   * Registra log de erro quando o envio falha
   * @param mixed $send_result Resultado de send_mail_msg() (pode ser bool ou array)
   * @param object|null $send_mail_object Instância de __Bazar_Send_Mail (opcional, para obter error_info)
   * @param string|null $method_name Nome do método que está enviando o email (para log)
   * @return bool Retorna true se sucesso ou alerta, false se erro fatal
   */
  protected function processar_retorno_email($send_result, $send_mail_object = null, $method_name = null)
  {

    // $this->log_debug( 'processar_retorno_email', 'send_result: ' . print_r($send_result, true) );
    // $this->log_debug( 'processar_retorno_email', 'send_mail_object: ' . print_r($send_mail_object, true) );
    // $this->log_debug( 'processar_retorno_email', 'method_name: ' . $method_name );

    // Se retornou array, verificar se é alerta
    if (is_array($send_result) && isset($send_result['alert'])) {
      // Adicionar alerta ao data_output se disponível
      if (property_exists($this, 'data_output')) {
        // Garantir que data_output seja array
        if (!is_array($this->data_output)) {
          $this->data_output = array();
        }
        $this->data_output['email_alert'] = array(
          'type' => isset($send_result['alert_type']) ? $send_result['alert_type'] : 'info',
          'title' => isset($send_result['alert_title']) ? $send_result['alert_title'] : 'Atenção',
          'msg' => isset($send_result['alert_msg']) ? $send_result['alert_msg'] : 'Não foi possível enviar o email.'
        );
      }

      // Registrar log de erro quando houver alerta
      if ($send_mail_object && method_exists($send_mail_object, 'get_error_messages')) {
        $error_messages = $send_mail_object->get_error_messages();
        $log_message = !empty($error_messages)
          ? 'Erro ao enviar email' . ($method_name ? ' (' . $method_name . ')' : '') . '. Motivo: ' . (is_array($error_messages) ? implode(', ', $error_messages) : $error_messages)
          : 'Erro ao enviar email' . ($method_name ? ' (' . $method_name . ')' : '') . '.';

        $this->log_debug($method_name ? $method_name : 'send_mail_error', $log_message);
      }

      // Retornar true (sucesso com alerta)
      return true;
    }

    // Se retornou false, é erro fatal - registrar log
    if ($send_result === false) {
      if (
        $send_mail_object
        && method_exists($send_mail_object, 'get_error_messages')
      ) {
        $error_messages = $send_mail_object->get_error_messages();
        $log_message = !empty($error_messages)
          ? 'Erro ao enviar email' . ($method_name ? ' (' . $method_name . ')' : '') . '. Motivo: ' . (is_array($error_messages) ? implode(', ', $error_messages) : $error_messages)
          : 'Erro ao enviar email' . ($method_name ? ' (' . $method_name . ')' : '') . '.';

        $this->definir_erro_envio_email(
          $log_message
        );
        // Retornar false (erro fatal)
        return false;
      }
    }

    // Se retornou true, é sucesso
    return true;
  }


  /**
   * Define mensagem de alerta de email
   * @param string|null $msg Mensagem de alerta de email
   */
  public function set_email_alert(): bool
  {
    // Se houver alerta de email, definir mensagem de sucesso        
    if (!empty($this->data_output['email_alert']['msg'])) {
      $this->data_output['msg'] = '<span>' . $this->data_output['email_alert']['msg'] . '</span>';
    } elseif (isset($this->data_output['email_alert'])) {
      $this->log_debug('set_email_alert', 'Existe o array(email_alert) porém Não há mensagem de alerta de email');
    }
    // Não deve falhar; será considerado um sucesso
    return true;
  }

  /**
   * Método centralizado de log
   * Salva tanto em $this->debug_log (se existir) quanto em error_log()
   * 
   * @param string $key Chave para identificar o log (ex: 'add_localizacao', 'validation_form')
   * @param mixed $message Mensagem a ser logada (pode ser string, array, objeto, etc.)
   * @param bool $append Se true, adiciona à mensagem existente ao invés de sobrescrever
   * @return void
   */
  protected function log_debug($key = 'error_server', $message = null, $append = false)
  {

    // Converter mensagem para string se necessário
    $message_str = ($message !== null) && is_scalar($message)
      ? (string) $message
      : print_r($message, true);

    // Preparar mensagem formatada para error_log
    $class_name = get_class($this);
    $formatted_message = sprintf(
      '[%s] [%s] %s',
      $class_name,
      $key,
      $message_str
    );

    // Salvar em error_log()
    error_log($formatted_message);

    // Salvar em $this->debug_log apenas se a propriedade existir
    if (property_exists($this, 'debug_log')) {
      if ($append && isset($this->debug_log[$key])) {
        // Se já existe e é append, concatenar ou criar array
        if (is_array($this->debug_log[$key])) {
          $this->debug_log[$key][] = $message_str;
        } else {
          $this->debug_log[$key] = $this->debug_log[$key] . ' | ' . $message_str;
        }
      } else {
        // Sobrescrever ou criar novo
        $this->debug_log[$key] = $message_str;
      }
    }
  }
}