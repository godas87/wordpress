<?php
/**
 * Classe para reativação de conta de usuário cancelada
 * 
 * @package XXXXXX
 */

add_action('wp_ajax_bazar_reativar_conta', 'bazar_reativar_conta');
add_action('wp_ajax_nopriv_bazar_reativar_conta', 'bazar_reativar_conta');
function bazar_reativar_conta() {
    $object = new __Bazar_User_Reactivate();
    wp_die();
}

class __Bazar_User_Reactivate extends __Bazar_Error_Handler {

    public $label = 'reactivate_user';
    public $action = 'bazar_reativar_conta';
    public $nonce = 'nonce_reativar_conta';
    public $user_id;
    public $user_email;
    public $data_output;

    public function __construct() {
        
        // Limpar qualquer output anterior
        if( ob_get_level() ){ ob_clean(); }
        
        try {
            $this->inicializar_resposta_padrao();
            $this->processar_reativacao();
            
        } catch (Exception $e) {
            $this->definir_erro_excecao($e);
        }
        
        // Garantir que apenas JSON seja retornado
        header('Content-Type: application/json');
        echo json_encode($this->data_output);
        exit;
    }

    /**
     * Processa a reativação da conta
     */
    private function processar_reativacao() {
        
        // Verificar segurança e método POST
        if( !$this->verificar_seguranca() ){ 
            return false; 
        }

        // Obter email do POST
        $this->user_email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        
        if( empty($this->user_email) ) {
            $this->definir_erro_campo_obrigatorio('user_email');
            return false;
        }

        // Verificar se usuário existe
        $user = get_user_by('email', $this->user_email);
        if( !$user ) {
            $this->definir_erro_servidor(
                'Usuário não encontrado.',
                'processar_reativacao',
                'Email não existe'
            );
            return false;
        }

        $this->user_id = $user->ID;

        // Verificar se está cancelado
        $is_cancelled = get_user_meta($this->user_id, 'bazar_user_cancelled', true);
        $is_cancelled = ($is_cancelled === 'true' || $is_cancelled === true || $is_cancelled === '1' || $is_cancelled === 1);
        
        if( !$is_cancelled ) {
            $this->definir_erro_servidor(
                'Esta conta não está cancelada.',
                'processar_reativacao',
                'Conta não está cancelada'
            );
            return false;
        }

        // Processar reativação
        if( !$this->reativar_conta() ) {
            return false;
        }

        // Enviar email de notificação (não é obrigatório)
        $this->enviar_email_notificacao_reativacao();

        // Definir resposta de sucesso
        $this->data_output = array(
            'success' => true,
            'title' => 'Conta reativada',
            'msg' => 'Sua conta foi reativada com sucesso! Você já pode fazer login normalmente.',
            'redirect' => get_bloginfo('url') . '/entrar/'
        );

        return true;
    }

    /**
     * Reativa a conta do usuário
     * Remove cancelamento e reativa diretamente (e-mail já foi validado ao receber o link)
     * Posts permanecem na lixeira (não são reativados)
     */
    private function reativar_conta() {
        
        // Remover marca de cancelamento
        delete_user_meta($this->user_id, 'bazar_user_cancelled');
        delete_user_meta($this->user_id, 'bazar_user_cancelled_date');
        delete_user_meta($this->user_id, 'bazar_user_cancelled_ip');

        // Se o usuário conseguiu receber o e-mail de reativação, o e-mail é válido
        // Não é necessário resetar confirmação de e-mail - reativar diretamente
        // Garantir que o e-mail está marcado como confirmado
        $email_status = get_user_meta($this->user_id, 'ativar_email', true);
        if( $email_status !== 'true' ) {
            // Se não estava confirmado, confirmar agora (usuário recebeu o e-mail)
            update_user_meta($this->user_id, 'ativar_email', 'true');
        }
        // Service de publicação: tentar publicar anúncios aprovados pelo ADM agora que e-mail está ativado
        if ( function_exists( 'bazar_publication_service_try_publish_for_user' ) ) {
            bazar_publication_service_try_publish_for_user( $this->user_id );
        }
        if ( function_exists( 'bazar_destaque_service_try_apply_pending_for_user' ) ) {
            bazar_destaque_service_try_apply_pending_for_user( $this->user_id );
        }

        // Log para auditoria
        // error_log("[Bazar User Reactivate] Usuário {$this->user_id} reativou conta - e-mail confirmado automaticamente");

        return true;
    }

    /**
     * Envia email de notificação de reativação
     * Informa que a conta foi reativada com sucesso
     * 
     * @return bool True se enviado com sucesso
     */
    private function enviar_email_notificacao_reativacao() {
        
        $user = get_user_by('ID', $this->user_id);
        if( !$user ) {
            return false;
        }

        $user_name = trim($user->first_name . ' ' . $user->last_name);
        if( empty($user_name) ) {
            $user_name = $user->display_name;
        }

        // URL de login
        $url_login = get_bloginfo('url') . '/entrar/';

        // Corpo do email
        $email_body = 'Olá ' . $user_name . ',<br>
        <p>Sua <b>conta foi reativada com sucesso</b> no Bazar Bikes!</p>
        <p>Você já pode fazer login normalmente e utilizar todos os recursos da plataforma.</p>';

        // Preparar dados do email
        $mail_data = array(
            'name' => $user_name,
            'to' => $this->user_email,
            'subject' => 'Conta reativada - Bazar Bikes',
            'msg_header' => 'Conta Reativada',
            'email_body' => $email_body,
            'buttons' => array(
                0 => array(
                    'label' => 'Fazer Login',
                    'url' => $url_login,
                    'text' => 'Acessar minha conta',
                ),
            ),
            'fail_on_error' => 'alert', // Não é erro fatal
        );

        // Enviar email
        if (class_exists('__Bazar_Send_Mail')) {
            $send_mail = new __Bazar_Send_Mail();
            return $send_mail->send_mail_msg($mail_data);
        }

        return false;
    }

}
?>