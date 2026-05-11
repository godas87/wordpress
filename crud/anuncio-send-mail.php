<?php
add_action('wp_ajax_bazar_anuncio_send_mail', 'bazar_anuncio_send_mail');
add_action('wp_ajax_nopriv_bazar_anuncio_send_mail', 'bazar_anuncio_send_mail');
function bazar_anuncio_send_mail() {
    $object = new __Bazar_Anuncio_Send_Mail();
    wp_die();
}

class __Bazar_Anuncio_Send_Mail extends __Bazar_Error_Handler {

    public $label = 'anuncio_send_mail';
    public $action;
    public $nonce;
    public $post_id;
    public $data_output;    
    private $campos_obrigatorios;
    private $user_name;
    private $user_email;
    private $send_name;
    private $send_email;
    private $send_msg;
    private $test_mail_recipient = 'XXXXXX';
    // Injeção de dependências
    private $validations;
    private $transients;
    private $anti_spam;

    public function __construct() {
        
        // Limpar qualquer output anterior
        if( ob_get_level() ){ ob_clean(); }
        
        // Iniciar sessão se necessário
        if ( session_status() === PHP_SESSION_NONE ) session_start();
        
        try {
            if( class_exists('__BazarValidations') ) {
                $this->validations = new __BazarValidations();
            } else {
                $this->log_debug( 'anuncio_send_mail_error', 'Classe __BazarValidations não encontrada' );
            }

            if( class_exists('__Bazar_Transients') ) {
                $this->transients = __Bazar_Transients::get_instance();
            } else {
                $this->log_debug( 'anuncio_send_mail_error', 'Classe __Bazar_Transients não encontrada' );
            }
                        
            if( class_exists('__Bazar_Message_Anti_Spam') ) {
                $this->anti_spam = new __Bazar_Message_Anti_Spam();
            } else {
                $this->log_debug( 'anuncio_send_mail_error', 'Classe __Bazar_Message_Anti_Spam não encontrada' );
            }
            
            $this->inicializar_resposta_padrao();

            $this->processar_envio_email();
            
        } catch (Exception $e) {
            $this->log_debug( 'anuncio_send_mail_exception', 'Exception capturada: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() );
            $this->definir_erro_excecao($e);
        } catch (Error $e) {
            $this->log_debug( 'anuncio_send_mail_error', 'Error fatal capturado: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() );
            $this->definir_erro_excecao($e);
        }
        
        // Verificar se data_output está definido antes de retornar JSON
        if( !isset($this->data_output) || !is_array($this->data_output) ) {
            $this->log_debug( 'anuncio_send_mail_error', 'data_output não está definido ou não é array antes de retornar JSON' );
            $this->inicializar_resposta_padrao();
            $this->definir_erro_servidor( 'Erro ao processar solicitação. Tente novamente.' );
        }
        
        // Garantir que apenas JSON seja retornado
        header('Content-Type: application/json');
        echo json_encode($this->data_output);
        exit;
    }

    /**
     * Processa o envio de email do anúncio
     */
    private function processar_envio_email() {
        
        // Verificar segurança e método POST
        if( !$this->verificar_seguranca() ){ return false; }
        
        // Validar dados primeiro (antes de preparar)
        if( !$this->validar_dados() ){ return false; }

        // Preparar dados do formulário (necessário para verificar_limite_envio)
        if( !$this->preparar_dados() ){ return false; }
        
        // Verificar limite de envio de emails (após preparar dados)
        if( !$this->verificar_limite_envio() ){ return false; }

        // Enviar email para o dono do anúncio
        if( !$this->enviar_email_dono_anuncio() ){  return false; }

        // Enviar email de confirmação para quem enviou
        $this->enviar_email_confirmacao();

        // Salvar flag na sessão
        $_SESSION['send_mail'] = true;

        // Contabilizar clique de envio de mensagem
        if( $this->post_id ) {
            try {
                new __Bazar_Count_Click( 
                    intval($this->post_id), 
                    '_count_contact_email' 
                );
            } catch (Exception $e) {
                $this->log_debug( 'anuncio_send_mail_error', 'Erro ao contabilizar clique: ' . $e->getMessage() );
            } catch (Error $e) {
                $this->log_debug( 'anuncio_send_mail_error', 'Erro fatal ao contabilizar clique: ' . $e->getMessage() );
            }
        }

        // Definir resposta de sucesso
        $this->definir_resposta_sucesso();
        
        return true;
    }

    /**
     * Define campos obrigatórios
     */
    private function setCamposObrigatorios(){
        $this->campos_obrigatorios = [
            'a_name',
            'a_mail',
            'nome',
            'email',
            'mensagem',
            'post_id'
        ];
    }

    /**
     * Valida dados do formulário
     */
    private function validar_dados() {

        // Setar campos obrigatórios
        $this->setCamposObrigatorios();
        
        // Percorrer $_POST e validar cada campo obrigatório
        foreach( $this->campos_obrigatorios as $field_name ){
            
            $valor = $_POST[$field_name] ?? '';
            $valor_limpo = trim($valor);
            
            // Verificar se o campo obrigatório está vazio
            if( $this->is_field_empty( $valor_limpo ) ){
                $this->definir_erro_campo_obrigatorio( $field_name );
                return false;
            }
            
            // Validações específicas
            if( $field_name === 'a_mail' || $field_name === 'email' ){
                if( !$this->validations->__BAZAR_validaEmail($valor_limpo) ){
                    $this->definir_erro_servidor(
                        'Por favor, digite um <b>e-mail válido</b>.',
                        'validar_dados'                        
                    );
                    return false;
                }
            }
            
            // Validação anti-spam para mensagem (apenas para campo mensagem)
            if( $field_name === 'mensagem' && $this->anti_spam ){
                $spam_validation = $this->anti_spam->validate_message( $valor_limpo );
                if( !$spam_validation['valid'] ){
                    $this->definir_erro_campos_invalidos(
                        $spam_validation['error'] ?? 'Sua mensagem contém conteúdo suspeito e não pode ser enviada.',
                        $field_name
                    );
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Prepara dados do formulário
     */
    private function preparar_dados() {
        
        $this->post_id = wp_strip_all_tags( $_POST['post_id'] ?? '' );
        
        // Dados do dono do anúncio
        $this->user_name = wp_strip_all_tags( $_POST['a_name'] ?? '' );
        $this->user_email = wp_strip_all_tags( $_POST['a_mail'] ?? '' );
        
        // Dados de quem está enviando a mensagem
        $this->send_name = wp_strip_all_tags( $_POST['nome'] ?? '' );
        $this->send_email = wp_strip_all_tags( $_POST['email'] ?? '' );
        
        // Sanitizar mensagem usando validação anti-spam (já validada em validar_dados)
        $mensagem_raw = $_POST['mensagem'] ?? '';
        if( $this->anti_spam ){
            $this->send_msg = $this->anti_spam->sanitize_message( $mensagem_raw );
        } else {
            // Fallback se classe anti-spam não estiver disponível
            $this->send_msg = wp_strip_all_tags( $mensagem_raw );
        }

        // Verificar se todos os dados obrigatórios foram preparados
        if( 
            empty($this->post_id) ||
            empty($this->user_name) ||
            empty($this->user_email) ||
            empty($this->send_name) ||
            empty($this->send_email) ||
            empty($this->send_msg)
        ){
            $this->definir_erro_campos_invalidos('Os campos enviados no formulário são inválidos.');
            return false;
        }

        return true;
    }

    /**
     * Obtém o destinatário do email baseado no ambiente
     * is_production() está na classe __Bazar_Error_Handler
     */
    private function get_email_recipient( $original_email ) {        	
        return ( $this->is_production() )
            ? $original_email 
            : $this->test_mail_recipient;
    }

    /**
     * Envia email para o dono do anúncio
     */
    private function enviar_email_dono_anuncio() {
        
        $email_body_user = '
        Olá '.$this->user_name.', um de nossos usuários acessou seu anúncio e está interessado na sua oferta.
        <br><br>
        <strong style="font-size:14px;">Mensagem do comprador interessado:</strong>
        <br>
        <b>Nome:</b> '.$this->send_name.'<br>
        <b>Email:</b> '.$this->send_email.'<br>
        <b>Mensagem:</b> <br>'.$this->send_msg;

        $box_footer = '<strong style="color:#666; font-size:14px;">Produto anunciado:</strong><br>
            '.get_the_title( $this->post_id).'<br>
            <a href="'.get_the_permalink($this->post_id).'" target="_blank">Ver Anúncio</a>';

        // em localhost, redirecionar email para email de teste
        $email_destinatario = $this->get_email_recipient( $this->user_email );

        $mail_data = array(
            'name' => $this->user_name,
            'from' => $this->send_email,
            'to' => $email_destinatario,            
            'subject' => "Intenção de compra",
            'msg_header' => "Seu anúncio tem interessados que aguardam seu retorno.",
            'email_body' => $email_body_user,
            'box_footer' => $box_footer,
            'buttons' => array(
                0 => array(
                    "label" => "Responder e-mail.",
                    "url" => 'mailto:'.$this->send_email,					
                    "text" => "Responder e-mail",
                ),
            ),
            'fail_on_error' => true,
        );
        
        try {
            $send_mail_user = new __Bazar_Send_Mail();
            $send_result = $send_mail_user->send_mail_msg( $mail_data );            
            // Caso não seja possível enviar o email para o dono do anúncio, quebrar o fluxo
            return $this->processar_retorno_email(
                $send_result,
                $send_mail_user,
                'enviar_email_dono_anuncio'
            );
        } 
        catch( Exception $e ){
            $this->definir_erro_servidor(
                'Erro ao enviar email. Tente novamente.',
                'anuncio_send_mail_error',
                $e->getMessage()
            );
            return false;
        } 
        catch( Error $e ){
            $this->definir_erro_servidor(
                'Erro ao enviar email. Tente novamente.',
                'anuncio_send_mail_error',
                $e
            );
            return false;
        }
    }

    /**
     * Envia email de confirmação para quem enviou a mensagem
     */
    private function enviar_email_confirmacao() {
        
        $email_body = '
            Olá '.$this->send_name.', sua mensagem foi enviada com sucesso. Agora é aguardar o retorno do vendedor.
            <br><br>
            <strong style="font-size:14px;">Mensagem enviada:</strong>
            <br>
            <b>Nome:</b> '.$this->send_name.'<br>
            <b>Email:</b> '.$this->send_email.'<br>
            <b>Mensagem:</b> <br>'.$this->send_msg;

        $box_footer = '<p>
            <strong style="color:#666; font-size:14px;">Anúncio que você está interessado:</strong><br>
            '.get_the_title( $this->post_id).'<br>
            <a href="'.get_the_permalink($this->post_id).'" target="_blank">Ver Anúncio</a>
        </p>';

        // Em localhost, redirecionar email de confirmação para email de teste
        $email_destinatario_confirmacao = $this->get_email_recipient( $this->send_email );
        
        $mail_arr = array(
            'name' => $this->send_name,
            'to' => $email_destinatario_confirmacao,
            'subject' => "Intenção de compra",
            'msg_header' => "Mensagem enviada com sucesso!",
            'email_body' => trim($email_body),
            'box_footer' => trim($box_footer),
            'fail_on_error' => 'alert',
        );
        
        try {
            $send_mail = new __Bazar_Send_Mail();
            $send_result = $send_mail->send_mail_msg( $mail_arr );
            return $this->processar_retorno_email( 
                $send_result, 
                $send_mail, 
                'enviar_email_confirmacao' 
            );                        
        } 
        catch( Exception $e ){
            $this->definir_erro_servidor(
                'Erro ao enviar email. Tente novamente.',
                'anuncio_send_mail_error',
                $e
            );
            return false;
        } 
        catch( Error $e ){
            $this->definir_erro_servidor(
                'Erro ao enviar email. Tente novamente.',
                'anuncio_send_mail_error',
                $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Verifica limite de envio de emails usando transients
     * Valida tanto email+post_id quanto IP do usuário
     * @return bool True se pode enviar, false se limite atingido
     */
    private function verificar_limite_envio() {
        
        if( !$this->transients ){
            $this->definir_erro_servidor( 
                'Erro ao verificar limite de envio. Tente novamente.',
                'verificar_limite_envio',
                'Tentativa de verificar limite mas $this->transients é null'
            );
            return false;
        }
                
        $ip = $this->transients->get_user_ip();
        
        // ============================================
        // VALIDAÇÃO 1: Verificar se IP está bloqueado
        // ============================================
        if( $this->transients->is_ip_blocked($ip) ){
            $remaining = $this->transients->get_ip_block_remaining_time($ip);
            $this->log_email_block(
                'IP_BLOCKED', 
                $ip,
                null,
                $this->post_id, 
                $remaining
            );
            $this->definir_erro_ip_bloqueado();
            return false;
        }
        
        // ============================================
        // VALIDAÇÃO 2: Verificar limite por IP
        // ============================================
        // Usa valores padrão da classe __Bazar_Transients
        // (configuráveis nas propriedades estáticas da classe)
        $ip_limit_info = $this->transients->check_ip_limit($ip);

        if( !$ip_limit_info ){
            // IP excedeu limite, bloquear e obter tempo restante
            $this->transients->block_ip( $ip );
            $remaining = $this->transients->get_ip_block_remaining_time( $ip );
            $ip_info = $this->transients->get_ip_limit_info( $ip );
            $this->log_email_block(
                'IP_LIMIT_EXCEEDED',
                $ip, 
                null, 
                $this->post_id, 
                $remaining, 
                $ip_info
            );
            $this->definir_erro_ip_bloqueado();
            return false;
        }
        
        // ============================================
        // VALIDAÇÃO 3: Verificar limite por email+post_id (sem IP)
        // ============================================
        // Usa valores padrão da classe __Bazar_Transients
        // (configuráveis nas propriedades estáticas da classe)
        // 
        // ESTRATÉGIA DE SEGURANÇA EQUILIBRADA:
        // - Validação 1 e 2 já protegem contra spam por IP
        // - Esta validação previne que o mesmo email envie múltiplas mensagens para o mesmo anúncio
        // - Permite que diferentes pessoas (emails diferentes) do mesmo IP enviem mensagens
        // - Permite que a mesma pessoa use emails diferentes para o mesmo anúncio
        // - Usar apenas email+post_id (sem IP) para permitir mais flexibilidade
        $email_limit_info = $this->transients->check_email_limit(
            $this->send_email, 
            $this->post_id,
            null // Não passar IP - usar apenas email+post_id
        );

        if( !$email_limit_info ) {
            $remaining = $this->transients->get_email_remaining_time(
                $this->send_email, 
                $this->post_id,
                null // Não passar IP
            );
            $email_info = $this->transients->get_email_limit_info(
                $this->send_email, 
                $this->post_id,
                null // Não passar IP
            );
            $this->log_email_block(
                'EMAIL_LIMIT_EXCEEDED',
                $ip, 
                $this->send_email, 
                $this->post_id, 
                $remaining, 
                $email_info
            );
            $this->definir_erro_limite_envio();
            return false;
        }
        
        // ============================================
        // TUDO OK: Incrementar contadores
        // ============================================
        // Usa valores padrão da classe __Bazar_Transients
        $this->transients->increment_ip_limit($ip);
        $this->transients->increment_email_limit(
            $this->send_email, 
            $this->post_id,
            null // Não passar IP - usar apenas email+post_id
        );
        
        return true;
    }

    /**
     * Define erro de limite de envio atingido (email+post_id)    
     */
    private function definir_erro_limite_envio() {
        $this->data_output = array(
            'submit' => false,
            'title' => 'Limite de envio atingido!',
            'msg' => 'Você já enviou mensagem para este anúncio. Tente mais tarde.',
        );
    }

    /**
     * Define erro de IP bloqueado
     */
    private function definir_erro_ip_bloqueado() {               
        $this->data_output = array(
            'submit' => false,
            'title' => 'Acesso temporariamente bloqueado!',
            'msg' => 'Muitas mensagens foram enviadas a partir deste endereço. Por segurança, o envio foi bloqueado temporariamente.',
        );
    }

    /**
     * Registra log de bloqueio de email/IP
     * @param string $tipo Tipo de bloqueio: IP_BLOCKED, IP_LIMIT_EXCEEDED, EMAIL_LIMIT_EXCEEDED
     * @param string $ip Endereço IP
     * @param string|null $email Email do remetente (opcional)
     * @param int|string $post_id ID do anúncio
     * @param int $remaining_seconds Tempo restante em segundos
     * @param array|null $limit_info Informações adicionais do limite (opcional)
     */
    private function log_email_block($tipo, $ip, $email = null, $post_id = null, $remaining_seconds = 0, $limit_info = null) {
        $timestamp = date('Y-m-d H:i:s');
        
        // Calcular tempo restante formatado
        $hours = floor($remaining_seconds / 3600);
        $minutes = floor(($remaining_seconds % 3600) / 60);
        $time_remaining = '';
        if ($hours > 0) {
            $time_remaining = $hours . 'h ' . $minutes . 'm';
        } else if ($minutes > 0) {
            $time_remaining = $minutes . 'm';
        } else {
            $time_remaining = $remaining_seconds . 's';
        }
        
        // Montar mensagem de log
        $log_parts = array(
            "[Bazar Email Block]",
            "[{$timestamp}]",
            "[{$tipo}]",
            "IP: {$ip}",
        );
        
        if ($email) {
            $log_parts[] = "Email: {$email}";
        }
        
        if ($post_id) {
            $log_parts[] = "Post ID: {$post_id}";
        }
        
        $log_parts[] = "Tempo restante: {$time_remaining}";
        
        if ($limit_info && is_array($limit_info)) {
            $count = isset($limit_info['count']) ? $limit_info['count'] : 'N/A';
            $log_parts[] = "Tentativas: {$count}";
        }
        
        $log_message = implode(' | ', $log_parts);
        
        // Registrar no log do WordPress
        error_log($log_message);
    }

    /**
     * Define resposta de sucesso
     */
    private function definir_resposta_sucesso() {
        $this->data_output = array(
            'submit' => true,
            'title' => 'Sucesso!',
            'msg' => 'Mensagem enviada com sucesso!',
            'resetForm' => true,
        );

        $this->set_email_alert();
        
        // Adicionar redirect se especificado
        if( isset( $_POST['redirect'] ) ){
            $this->data_output['redirect'] = esc_url( $_POST['redirect'] );
        }
    }
}
?>