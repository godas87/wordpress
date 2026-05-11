<?php
/**
 * Classe para marcar anúncio como vendido
 * 
 * @package XXXXXX
 */

add_action('wp_ajax_bazar_anuncio_marcar_vendido', 'bazar_anuncio_marcar_vendido');
add_action('wp_ajax_nopriv_bazar_anuncio_marcar_vendido', 'bazar_anuncio_marcar_vendido');
function bazar_anuncio_marcar_vendido() {
    // Limpar qualquer output anterior
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Definir header JSON
    header('Content-Type: application/json');
    
    $object = new __Bazar_Anuncio_Marcar_Vendido();
    
    // Retornar resposta JSON
    wp_send_json(array(
        'success' => $object->success,
        'message' => $object->message
    ));
    
    wp_die();
}

class __Bazar_Anuncio_Marcar_Vendido {

    public $action;
    public $post_id;
    public $success = false;
    public $message = '';

    public function __construct( $action = null, $post_id = null ) {

        $this->action = ( isset($_POST['action']) ) ? 
            wp_strip_all_tags($_POST['action']) : 
            null;

        if ( !empty( $this->action ) && $this->action == 'bazar_anuncio_marcar_vendido') :

            $this->post_id = ( isset($_POST['post_id']) ) ?
                intval(wp_strip_all_tags($_POST['post_id'])) : 
                null;

            if( $this->post_id ) :

                // Verificar se o usuário é o autor do post ou admin
                $post = bazar_get_product_data($this->post_id);
                if (!$post) {
                    $this->success = false;
                    $this->message = 'Anúncio não encontrado.';
                    return;
                }

                $author_id = $post['author']['id'];
                $current_user_id = get_current_user_id();

                if ($author_id != $current_user_id && !current_user_can('manage_options')) {
                    $this->success = false;
                    $this->message = 'Você não tem permissão para realizar esta ação.';
                    return;
                }

                // Buscar termo 'vendido' da taxonomia 'status' (usando helper com cache)
                $vendido_term_id = function_exists('bazar_get_vendido_term_id') 
                    ? bazar_get_vendido_term_id() 
                    : null;

                if (empty($vendido_term_id)) {
                    $this->success = false;
                    $this->message = 'Erro ao marcar anúncio como vendido. Tente novamente.';
                    return;
                }

                // Adicionar termo 'vendido' à postagem
                $term_result = wp_set_object_terms( 
                    $this->post_id, 
                    array($vendido_term_id), 
                    'status', 
                    false
                );

                if (is_wp_error($term_result)) {
                    $this->success = false;
                    $this->message = 'Erro ao marcar anúncio como vendido. Tente novamente.';
                    return;
                }

                // Remover destaque se existir (o hook também fará isso, mas garantimos aqui)
                if (function_exists('bazar_remover_destaque') && has_term('destaque', 'status', $this->post_id)) {
                    bazar_remover_destaque($this->post_id, 'vendido');
                }

                // Salvar data de desativação (mantido para histórico)
                update_post_meta($this->post_id, '_bazar_vendido_date', current_time('mysql'));

                // Remover imagens exceto featured image
                // if (function_exists('bazar_remove_post_images_except_featured')) {
                //     bazar_remove_post_images_except_featured($this->post_id);
                // }

                // Limpar cache relacionado ao post
                $this->limpar_cache_post($this->post_id);

                // E-mail de feedback só para o autor; admin marca em silêncio (sem e-mail / token)
                if (!current_user_can('manage_options')) {
                    $this->enviar_email_feedback(
                        $post['user']['name'],
                        $post['user']['email'],
                        $this->post_id
                    );
                }

                $this->success = true;
                $this->message = 'Anúncio marcado como vendido com sucesso!';

            else:
                $this->success = false;
                $this->message = 'ID do anúncio não fornecido.';
            endif;

        else:
            $this->success = false;
            $this->message = 'Ação inválida.';
        endif;

    }

    /**
     * Limpa cache relacionado ao post quando é marcado como vendido
     * Garante que o anúncio não apareça mais nos resultados de busca
     * 
     * @param int $post_id ID do post
     */
    private function limpar_cache_post($post_id) {
        
        // Limpar cache do WordPress para o post específico
        // Isso limpa cache de post, meta, termos, etc.
        clean_post_cache($post_id);
        
        if (class_exists('__Bazar_Product_Data_Repository')) {
            __Bazar_Product_Data_Repository::flush_post_cache($post_id);
        }
        
        // Limpar cache de termos relacionados (especialmente 'status' que mudou)
        clean_term_cache(null, 'status');
        clean_term_cache(null, 'category');
        
        // Limpar cache de queries relacionadas do WordPress
        // Isso força o WordPress a recarregar os dados do post nas próximas queries
        wp_cache_delete($post_id, 'posts');
        wp_cache_delete($post_id, 'post_meta');
        
        // Limpar transients relacionados (se houver)
        // Exemplo: transients de busca, listagens, etc.
        delete_transient('bazar_search_' . md5($post_id));
        delete_transient('bazar_listing_' . $post_id);
        
        // Limpar cache de object cache por grupo (se estiver usando Redis/Memcached)
        // Isso limpa todos os posts do cache, garantindo que queries sejam refeitas
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('posts');
            wp_cache_flush_group('post_meta');
        }
        
        // Limpar cache de queries WP_Query (WordPress mantém cache de queries)
        // Isso garante que próximas queries não usem resultados em cache
        wp_cache_flush();
        
        // Hook para plugins externos limparem seu próprio cache
        // Alguns plugins de busca/cache mantêm cache próprio
        do_action('bazar_clear_post_cache', $post_id);
        do_action('clean_post_cache', $post_id);
    }

    /**
     * Envia email de feedback quando anúncio é marcado como vendido
     * Usa dados já obtidos para evitar queries desnecessárias
     * 
     * @param string $user_name Nome do usuário
     * @param string $user_email Email do usuário
     * @param int $post_id ID do post
     */
    private function enviar_email_feedback( $user_name, $user_email, $post_id ) {
        
        if (empty($user_email) || empty($user_name) || empty($post_id) ) {
            return;
        }
        
        // Preparar corpo do email
        $email_body = 'Olá ' . $user_name . ',<br>
        <p>Parabéns! Vimos que você marcou seu anúncio como <b>vendido</b>.</p>
        <p>Gostaríamos muito de saber sua opinião sobre a experiência de vender no Bazar Bikes!</p>
        <p>Seu feedback é muito importante para melhorarmos nossos serviços.</p>';
        
        // Gerar token único para o feedback
        $feedback_token = wp_generate_password(32, false);
        update_post_meta($post_id, '_feedback_token', $feedback_token);
        
        // Criar URL da página de feedback
        $feedback_url = add_query_arg(array(
            'post_id' => $post_id,
            'token' => $feedback_token
        ), home_url('/feedback-vendido/'));
        
        // Preparar dados do email
        $mail_data = array(
            'name' => $user_name,
            'to' => $user_email,
            'subject' => 'Parabéns pela venda! Ajude-nos a melhorar',
            'msg_header' => 'Anúncio Vendido',
            'email_body' => $email_body,
            'buttons' => array(
                0 => array(
                    'label' => 'Dar Feedback',
                    'url' => $feedback_url,
                    'text' => 'Compartilhe sua experiência',
                ),
            ),
            'fail_on_error' => 'alert', // Não é erro fatal
        );
        
        // Enviar email
        if (class_exists('__Bazar_Send_Mail')) {
            $send_mail = new __Bazar_Send_Mail();
            $send_mail->send_mail_msg($mail_data);
        }
    }

};
?>