<?php
/**
 * Classe para cancelamento de conta de usuário
 * 
 * @package XXXXXX
 */

add_action('wp_ajax_bazar_cancelar_conta', 'bazar_cancelar_conta');
function bazar_cancelar_conta() {
    $object = new __Bazar_User_Cancel();
    wp_die();
}

class __Bazar_User_Cancel extends __Bazar_Error_Handler {

    public $label = 'cancel_user';
    public $action = 'bazar_cancelar_conta';
    public $nonce = 'nonce_cancelar_conta';
    public $user_id;
    public $data_output;

    public function __construct() {
        
        // Limpar qualquer output anterior
        if( ob_get_level() ){ ob_clean(); }
        
        try {
            $this->inicializar_resposta_padrao();
            $this->processar_cancelamento();
            
        } catch (Exception $e) {
            $this->definir_erro_excecao($e);
        }
        
        // Garantir que apenas JSON seja retornado
        header('Content-Type: application/json');
        echo json_encode($this->data_output);
        exit;
    }

    /**
     * Processa o cancelamento da conta
     */
    private function processar_cancelamento() {
        
        // Verificar segurança e método POST
        if( !$this->verificar_seguranca() ){ 
            return false; 
        }

        // Obter user_id (usuário logado)
        $this->user_id = get_current_user_id();
        if( empty($this->user_id) ) {
            $this->definir_erro_servidor(
                'Você precisa estar logado para cancelar sua conta.',
                'processar_cancelamento',
                'Usuário não logado'
            );
            return false;
        }

        // Verificar se usuário existe
        $user = get_user_by('ID', $this->user_id);
        if( !$user ) {
            $this->definir_erro_servidor(
                'Usuário não encontrado.',
                'processar_cancelamento',
                'Usuário não existe'
            );
            return false;
        }

        // Verificar se já está cancelado
        $is_cancelled = get_user_meta($this->user_id, 'bazar_user_cancelled', true);
        $is_cancelled = ($is_cancelled === 'true' || $is_cancelled === true || $is_cancelled === '1' || $is_cancelled === 1);
        
        if( $is_cancelled ) {
            $this->definir_erro_servidor(
                'Sua conta já está cancelada.',
                'processar_cancelamento',
                'Conta já cancelada'
            );
            return false;
        }

        // Processar cancelamento
        if( !$this->cancelar_conta() ) {
            return false;
        }

        // Fazer logout
        wp_logout();

        // Definir resposta de sucesso
        $this->data_output = array(
            'success' => true,
            'title' => 'Conta cancelada',
            'msg' => 'Sua conta foi cancelada com sucesso. Todos os seus anúncios foram movidos para a lixeira.',
            'redirect' => home_url('/')
        );

        return true;
    }

    /**
     * Cancela a conta do usuário
     * Move todos os posts para lixeira e deleta imagens (exceto featured)
     */
    private function cancelar_conta() {
        
        // Marcar como cancelado
        update_user_meta($this->user_id, 'bazar_user_cancelled', true);
        update_user_meta($this->user_id, 'bazar_user_cancelled_date', current_time('mysql'));
        update_user_meta($this->user_id, 'bazar_user_cancelled_ip', bazar_get_user_ip() ?? '');

        // Mover todos os posts do usuário para lixeira
        $posts_movidos = $this->mover_posts_para_lixeira();
        
        // Log para auditoria
        // error_log("[Bazar User Cancel] Usuário {$this->user_id} cancelou conta. Posts movidos: {$posts_movidos}");

        return true;
    }

    /**
     * Move todos os posts do usuário para lixeira
     * Deleta imagens da galeria (exceto featured image)
     * 
     * @return int Número de posts processados
     */
    private function mover_posts_para_lixeira() {
        
        // Buscar todos os posts do usuário (exceto os que já estão na lixeira)
        $args = array(
            'author' => $this->user_id,
            'post_type' => 'post',
            'post_status' => array('publish', 'pending', 'draft', 'auto-draft'),
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $posts = get_posts($args);
        
        if( empty($posts) ) {
            return 0;
        }

        $count = 0;
        
        foreach( $posts as $post_id ) {
            
            // Remover destaque se existir (antes de mover para lixeira)
            if (function_exists('bazar_remover_destaque') && has_term('destaque', 'status', $post_id)) {
                bazar_remover_destaque($post_id, 'cancelado');
            }

            // Deletar imagens da galeria (exceto featured image)
            if (function_exists('bazar_remove_post_images_except_featured')) {
                bazar_remove_post_images_except_featured($post_id);
            } else {
                // Fallback: usar método manual
                $imgs = get_posts( array(
                    'post_type' => 'attachment',
                    'posts_per_page' => -1,
                    'post_parent' => $post_id,
                ));  
                if( $imgs ) :
                    $featured_id = get_post_thumbnail_id($post_id);
                    foreach( $imgs as $img ) :
                        // Não remover featured image
                        if( $img->ID != $featured_id ) {
                            wp_delete_attachment($img->ID, true);
                        }
                    endforeach;
                endif; 
                wp_reset_postdata();
            }

            // Mover para lixeira
            $result = wp_trash_post($post_id);
            
            if( $result ) {
                $count++;
            }
        }

        return $count;
    }
}
?>