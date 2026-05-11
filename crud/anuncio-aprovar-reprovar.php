<?php
// Ações AJAX para aprovar/reprovar anúncios
add_action('wp_ajax_bazar_anuncio_aprovar_reprovar', 'bazar_anuncio_aprovar_reprovar');
add_action('wp_ajax_nopriv_bazar_anuncio_aprovar_reprovar', 'bazar_anuncio_aprovar_reprovar');

// Função que inicializa a classe de aprovação/reprovação
function bazar_anuncio_aprovar_reprovar()
{
    $object = new __Bazar_Anuncio_Aprovar_Reprovar();
    wp_die();
}

// Classe principal para aprovação/reprovação de anúncios
class __Bazar_Anuncio_Aprovar_Reprovar extends __Bazar_Error_Handler
{

    public $label = 'anuncio_aprovar_reprovar';
    public $action;
    public $nonce;
    public $post_id;
    public $data_output;
    private $operation_type; // 'aprovar' ou 'reprovar'
    private $motivos_indeferimento;

    public function __construct()
    {

        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_clean();
        }

        // Iniciar sessão se necessário
        if (session_status() === PHP_SESSION_NONE)
            session_start();

        try {
            $this->inicializar_resposta_padrao();
            $this->processar_operacao();

        } catch (Exception $e) {
            $this->definir_erro_excecao($e);
        }

        // Garantir que apenas JSON seja retornado
        header('Content-Type: application/json');
        echo json_encode($this->data_output);
        exit;
    }

    /**
     * Processa a operação de aprovação ou reprovação
     */
    private function processar_operacao()
    {

        // Verificar segurança e método POST
        if (!$this->verificar_seguranca()) {
            return false;
        }

        // Verificar se usuário é admin
        if (!current_user_can('manage_options')) {
            $this->definir_erro_servidor(
                'Acesso negado. Apenas administradores podem aprovar ou reprovar anúncios.',
                'processar_operacao',
                'Usuário não tem permissão de administrador'
            );
            return false;
        }

        // Obter post_id
        $this->post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
        if (empty($this->post_id)) {
            $this->definir_erro_servidor(
                'ID do anúncio não informado.',
                'processar_operacao',
                'post_id vazio'
            );
            return false;
        }

        // Verificar se post existe
        $post = (!empty($this->post_id))
            ? bazar_get_product_data($this->post_id)
            : null;

        if (!$post || empty($post['post']) || $post['post']->post_type !== 'post') {
            $this->definir_erro_servidor(
                'Anúncio não encontrado.',
                'processar_operacao',
                'Post não existe ou não é do tipo correto'
            );
            return false;
        }

        // Obter tipo de operação
        $this->operation_type = isset($_POST['operation']) ? wp_strip_all_tags($_POST['operation']) : null;
        if (!in_array($this->operation_type, ['aprovar', 'reprovar'])) {
            $this->definir_erro_servidor(
                'Tipo de operação inválido.',
                'processar_operacao',
                'Operation deve ser "aprovar" ou "reprovar"'
            );
            return false;
        }

        // Verificar se post está em pending ou draft (para aprovação)
        // Aceitar 'pending' (novos) e 'draft' (reavaliação de reprovados)
        if ($this->operation_type === 'aprovar') {
            $current_status = get_post_status($this->post_id);
            if (!in_array($current_status, ['pending', 'draft'])) {
                $this->definir_erro_servidor(
                    'Apenas anúncios pendentes ou em rascunho podem ser aprovados.',
                    'processar_operacao',
                    'Status do post não é pending ou draft: ' . $current_status
                );
                return false;
            }
        }

        // Processar operação
        if ($this->operation_type === 'aprovar') {
            $this->aprovar_anuncio();
        } else {
            // Obter motivos do indeferimento
            $this->motivos_indeferimento = isset($_POST['motivos']) ? wp_kses_post($_POST['motivos']) : '';
            if (empty($this->motivos_indeferimento)) {
                $this->definir_erro_servidor(
                    'É necessário informar os motivos do indeferimento.',
                    'processar_operacao',
                    'Campo motivos vazio'
                );
                return false;
            }
            $this->reprovar_anuncio();
        }
    }

    /**
     * Aprova o anúncio: marca como aprovado pelo ADM e publica quando o perfil estiver completo (e-mail não obrigatório).
     * Ordem: publicar anúncios elegíveis → liberar destaque pago pendente de CPF (`anuncio-destaque-service.php`), igual a Minha Conta.
     */
    private function aprovar_anuncio()
    {

        // Limpar motivos de indeferimento antes de aprovar
        update_field('motivos_para_indeferimento', '', $this->post_id);

        // Marcar como aprovado pelo administrador (meta do service de publicação)
        update_post_meta($this->post_id, BAZAR_META_APROVADO_ADM, '1');

        $author_id = (int) get_post_field('post_author', $this->post_id);
        $published = function_exists('bazar_publication_service_try_publish_for_user')
            ? bazar_publication_service_try_publish_for_user($author_id)
            : array();

        if (function_exists('bazar_destaque_service_try_apply_pending_for_user')) {
            bazar_destaque_service_try_apply_pending_for_user($author_id);
        }

        $foi_publicado = in_array($this->post_id, $published, true);

        if ($foi_publicado) {
            $this->data_output = array(
                'submit' => true,
                'title' => 'Anúncio aprovado!',
                'msg' => 'O anúncio foi aprovado e publicado com sucesso. O usuário receberá um e-mail de confirmação.',
                'redirect' => get_permalink($this->post_id),
            );
        } else {
            $pendencias = function_exists('bazar_publication_service_get_profile_pendencias_publicacao')
                ? bazar_publication_service_get_profile_pendencias_publicacao($author_id)
                : null;
            $falta_cadastro_publicacao = $pendencias
                && (!empty($pendencias['need_endereco']) || !empty($pendencias['need_dados_pessoais']));

            if ($falta_cadastro_publicacao && function_exists('bazar_publication_service_send_approved_pending_profile_email')) {
                bazar_publication_service_send_approved_pending_profile_email($this->post_id);
            }

            $this->data_output = array(
                'submit' => true,
                'title' => 'Anúncio aprovado',
                'msg' => $falta_cadastro_publicacao
                    ? 'O anúncio foi aprovado. O usuário receberá um e-mail para completar o que faltar no perfil (nome, telefone ou endereço); após isso o anúncio será publicado automaticamente. A confirmação de e-mail não é necessária para publicar.'
                    : 'O anúncio foi aprovado, mas não foi publicado neste momento (perfil já completo ou falha ao atualizar o status). Peça ao usuário para salvar novamente em Minha Conta ou verifique o anúncio no painel.',
                'redirect' => get_permalink($this->post_id),
            );
        }

        return true;
    }

    /**
     * Reprova o anúncio (salva motivos, envia email, muda status para draft)
     */
    private function reprovar_anuncio()
    {

        // Salvar motivos do indeferimento no ACF
        $update_field = update_field('motivos_para_indeferimento', $this->motivos_indeferimento, $this->post_id);

        if (!$update_field) {
            $this->log_debug('reprovar_anuncio', 'Aviso: update_field retornou false, mas pode ser que o campo não exista ainda');
            // Não é erro fatal, continuar
        }

        // Mudar status para 'draft' (anúncio reprovado não deve aparecer nas buscas)
        // O hook 'pending_to_draft' será disparado automaticamente e enviará o email
        $update_result = wp_update_post(array(
            'ID' => $this->post_id,
            'post_status' => 'draft'
        ));

        if (defined('BAZAR_META_APROVADO_ADM')) {
            delete_post_meta($this->post_id, BAZAR_META_APROVADO_ADM);
        }

        if (is_wp_error($update_result) || !$update_result) {
            $this->log_debug('reprovar_anuncio', 'Aviso: Erro ao mudar status para draft: ' . (is_wp_error($update_result) ? $update_result->get_error_message() : 'wp_update_post retornou false'));
            // Não é erro fatal, continuar
        }

        // O email será enviado automaticamente pelo hook 'pending_to_draft'
        // Não é necessário enviar manualmente aqui

        // Sempre definir resposta de sucesso
        $this->data_output = array(
            'submit' => true,
            'title' => 'Anúncio indeferido!',
            'msg' => 'O anúncio foi indeferido e os motivos foram salvos com sucesso. O usuário receberá um email de notificação.',
            'redirect' => get_permalink($this->post_id)
        );

        return true;
    }

}
?>