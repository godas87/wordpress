<?php
add_action('wp_ajax_bazar_block_user', 'bazar_block_user');
function bazar_block_user() {
    $object = new __Bazar_User_Block();
    wp_die();
}

class __Bazar_User_Block extends __Bazar_Error_Handler {

    public $label = 'block_user';
    public $action = 'bazar_block_user';
    public $nonce = 'nonce_block_user';
    public $user_id;
    public $data_output;

    public function __construct() {
        
        // Limpar qualquer output anterior
        if( ob_get_level() ){ ob_clean(); }
        
        try {
            $this->inicializar_resposta_padrao();
            $this->processar_bloqueio();
            
        } catch (Exception $e) {
            $this->definir_erro_excecao($e);
        }
        
        // Garantir que apenas JSON seja retornado
        header('Content-Type: application/json');
        echo json_encode($this->data_output);
        exit;
    }

    /**
     * Processa o bloqueio/desbloqueio do usuário
     */
    private function processar_bloqueio() {
        
        // Verificar segurança e método POST
        if( !$this->verificar_seguranca() ){ 
            return false; 
        }
        
        // Verificar se usuário é admin
        if( !current_user_can('manage_options') ) {
            $this->definir_erro_servidor(
                'Acesso negado. Apenas administradores podem bloquear usuários.',
                'processar_bloqueio',
                'Usuário não tem permissão de administrador'
            );
            return false;
        }

        // Obter user_id
        $this->user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
        if( empty($this->user_id) ) {
            $this->definir_erro_servidor(
                'ID do usuário não informado.',
                'processar_bloqueio',
                'user_id vazio'
            );
            return false;
        }

        // Verificar se usuário existe
        $user = get_user_by('ID', $this->user_id);
        if( !$user ) {
            $this->definir_erro_servidor(
                'Usuário não encontrado.',
                'processar_bloqueio',
                'Usuário não existe'
            );
            return false;
        }

        // Verificar se é tentativa de bloquear a si mesmo
        if( $this->user_id == get_current_user_id() ) {
            $this->definir_erro_servidor(
                'Você não pode bloquear a si mesmo.',
                'processar_bloqueio',
                'Tentativa de bloquear próprio usuário'
            );
            return false;
        }

        // Verificar se usuário já está bloqueado
        $is_blocked = get_user_meta($this->user_id, 'bazar_user_blocked', true);
        $is_blocked = ($is_blocked === 'true' || $is_blocked === true || $is_blocked === '1' || $is_blocked === 1);

        // Alternar status de bloqueio
        if( $is_blocked ) {
            // Desbloquear
            delete_user_meta($this->user_id, 'bazar_user_blocked');
            delete_user_meta($this->user_id, 'bazar_user_blocked_date');
            delete_user_meta($this->user_id, 'bazar_user_blocked_by');
            
            $this->data_output = array(
                'success' => true,
                'message' => 'Usuário desbloqueado com sucesso.',
                'blocked' => false
            );
        } else {
            // Bloquear
            update_user_meta($this->user_id, 'bazar_user_blocked', true);
            update_user_meta($this->user_id, 'bazar_user_blocked_date', current_time('mysql'));
            update_user_meta($this->user_id, 'bazar_user_blocked_by', get_current_user_id());
            
            $this->data_output = array(
                'success' => true,
                'message' => 'Usuário bloqueado com sucesso.',
                'blocked' => true
            );
        }

        return true;
    }

}

// ============================================
// INCLUIR MODAL NO ADMIN
// ============================================
add_action('admin_footer', 'bazar_user_block_admin_modal');
function bazar_user_block_admin_modal() {
    $screen = get_current_screen();
    if ($screen && ($screen->id === 'users' || $screen->id === 'users-network')) {
        // Adicionar CSS inline para o modal no admin
        echo '<style>
            .modal-alert-message {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                z-index: 999999 !important;
                display: none;
            }
            .modal-alert-message .modal-alert-message-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(3px);
                -webkit-backdrop-filter: blur(3px);
            }
            .modal-alert-message .modal-alert-message-box {
                position: relative;
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
            }
            .modal-alert-message .modal-alert-message-content {
                background: white;
                border-radius: 8px;
                padding: 2rem;
                max-width: 500px;
                width: 100%;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                z-index: 1;
                position: relative;
            }
            .modal-alert-message .modal-alert-message-texto {
                margin-bottom: 1.5rem;
            }
            .modal-alert-message .modal-alert-message-texto h3 {
                margin-top: 0;
                margin-bottom: 1rem;
                font-size: 1.25rem;
            }
            .modal-alert-message .modal-alert-message-texto p {
                margin-bottom: 0;
            }
            .modal-alert-message .modal-alert-message-buttons {
                display: flex;
                gap: 1rem;
                justify-content: flex-end;
            }
            .modal-alert-message .modal-alert-message-buttons button {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                min-width: 100px;
            }
            .modal-alert-message .modal-alert-message-buttons .button.green {
                background-color: #28a745;
                color: white;
            }
            .modal-alert-message .modal-alert-message-buttons .button.red {
                background-color: #dc3545;
                color: white;
            }
            .modal-alert-message .modal-alert-message-buttons .button.clear {
                background-color: #f8f9fa;
                color: #333;
                border: 1px solid #dee2e6;
            }
            .modal-alert-message .modal-alert-message-buttons .button:hover {
                opacity: 0.9;
            }
        </style>';
        
        get_template_part('template-parts/modal/confirm');
    }
}

// ============================================
// ENFILEIRAR SCRIPTS E ESTILOS NO ADMIN
// ============================================
add_action('admin_enqueue_scripts', 'bazar_user_block_admin_scripts');
function bazar_user_block_admin_scripts($hook) {
    // Apenas na página de usuários
    if ($hook !== 'users.php') {
        return;
    }
    
    
    // Enfileirar jQuery
    wp_enqueue_script('jquery');
    
    // Enfileirar script comum reutilizável
    $script_version = file_exists(get_template_directory() . '/assets/js/inc/userBlockCommon.js') 
        ? filemtime(get_template_directory() . '/assets/js/inc/userBlockCommon.js') 
        : '1.0.0';
    
    wp_enqueue_script(
        'bazar-user-block-common',
        get_template_directory_uri() . '/assets/js/inc/userBlockCommon.js',
        array('jquery'),
        $script_version,
        true
    );
    
    // Localizar script com variáveis necessárias
    wp_localize_script('bazar-user-block-common', 'ajax_object', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce_block_user' => wp_create_nonce('nonce_block_user')
    ));
    
    // Garantir que ajaxurl esteja disponível globalmente
    wp_add_inline_script('bazar-user-block-common', '
        if (typeof ajaxurl === "undefined") {
            var ajaxurl = "' . admin_url('admin-ajax.php') . '";
        }
    ', 'before');
    
    // Inicializar - usar múltiplas estratégias para garantir que funcione
    wp_add_inline_script('bazar-user-block-common', '
        (function() {
            function init() {
                if (typeof jQuery === "undefined") {
                    setTimeout(init, 100);
                    return;
                }
                
                jQuery(document).ready(function($) {
                    // Verificar se BAZAR está disponível
                    if (typeof window.BAZAR === "undefined") {
                        return;
                    }
                    
                    if (typeof window.BAZAR.initUserBlock !== "function") {
                        return;
                    }
                    
                    // Verificar se há botões na página
                    var $buttons = $(".bazar-block-user-btn");
                    if ($buttons.length === 0) {
                        // Tentar novamente após um delay (pode ser renderizado dinamicamente)
                        setTimeout(function() {
                            $buttons = $(".bazar-block-user-btn");
                            if ($buttons.length > 0) {
                                window.BAZAR.initUserBlock(".bazar-block-user-btn");
                            }
                        }, 500);
                        return;
                    }
                    
                    // Inicializar
                    window.BAZAR.initUserBlock(".bazar-block-user-btn");
                });
            }
            
            // Tentar inicializar imediatamente e após DOM ready
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", init);
            } else {
                init();
            }
        })();
    ');
}

// ============================================
// ADICIONAR COLUNA NA LISTA DE USUÁRIOS
// ============================================
add_filter('manage_users_columns', 'bazar_add_user_block_column', 10, 1);
function bazar_add_user_block_column($columns) {
    $columns['bazar_blocked'] = 'Bloqueado';
    return $columns;
}

add_filter('manage_users_custom_column', 'bazar_user_block_column_content', 10, 3);
function bazar_user_block_column_content($value, $column_name, $user_id) {
    if ($column_name === 'bazar_blocked') {
        // Verificar se é admin antes de mostrar botão
        if (!current_user_can('manage_options')) {
            return '—';
        }
        
        $is_blocked = get_user_meta($user_id, 'bazar_user_blocked', true);
        $is_blocked = ($is_blocked === 'true' || $is_blocked === true || $is_blocked === '1' || $is_blocked === 1);
        
        $blocked_text = $is_blocked ? 'Sim' : 'Não';
        $blocked_class = $is_blocked ? 'bazar-user-blocked' : 'bazar-user-active';
        $button_text = $is_blocked ? 'Desbloquear' : 'Bloquear';
        $button_class = $is_blocked ? 'button button-secondary' : 'button button-primary';
        
        $nonce = wp_create_nonce('nonce_block_user');
        
        return sprintf(
            '<span class="%s" style="margin-right: 10px;">%s</span><a href="#" class="bazar-block-user-btn %s" data-user-id="%d" data-blocked="%s" data-nonce="%s" style="text-decoration: none; cursor: pointer;">%s</a>',
            esc_attr($blocked_class),
            esc_html($blocked_text),
            esc_attr($button_class),
            $user_id,
            $is_blocked ? '1' : '0',
            esc_attr($nonce),
            esc_html($button_text)
        );
    }
    return $value;
}
?>
