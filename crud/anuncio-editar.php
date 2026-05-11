<?php
// Ações AJAX para edição de anúncios de bicicletas
add_action('wp_ajax_bazar_anuncio_editar', 'bazar_anuncio_editar');
add_action('wp_ajax_nopriv_bazar_anuncio_editar', 'bazar_anuncio_editar');

// Função que inicializa a classe de edição de anúncios
function bazar_anuncio_editar(){
    $object = new __Bazar_Anuncio_Editar();
    wp_die();
}

// Classe principal para edição de anúncios de bicicletas
class __Bazar_Anuncio_Editar extends __Bazar_Anuncio_Crude{

    private $had_indeferimento;
    public function __construct(){
        $this->label = 'anuncio_editar';
        parent::__construct();
    }

    protected function process_form(){
        
        // DEBUG: Log de debug para rastrear o fluxo
        // $this->log_debug('process_form', 'Iniciando process_form()');
        // $this->log_debug('process_form', 'POST keys: ' . implode(', ', array_keys($_POST ?? [])));
        // $this->log_debug('process_form', 'FILES keys: ' . implode(', ', array_keys($_FILES ?? [])));
        
        // Obter post_id do POST
        $this->post_id = (isset($_POST['post_id']))
			? wp_strip_all_tags($_POST['post_id'])
			: null;

        // DEBUG: $this->log_debug('process_form', 'post_id: ' . ($this->post_id ?? 'não definido'));

        // Verificar se havia motivos de indeferimento antes da edição
        $this->had_indeferimento = false;
        if( !empty($this->post_id) ) {
            $motivos_indeferimento = get_field('motivos_para_indeferimento', $this->post_id);
            $this->had_indeferimento = !empty($motivos_indeferimento);
        }

        // Validar mudança de categoria (se tentar mudar de bicicleta para outra categoria)
        if( !$this->validate_category_change() ) {
            // DEBUG: $this->log_debug('process_form', 'Falhou em validate_category_change()');
            return false;
        }

        // Validar formulário        
        if( !$this->validation_form() ) {
            // DEBUG: $this->log_debug('process_form', 'Falhou em validation_form()');
            return false;
        }

        // Título gerado no JavaScript ( evita queries no backend )
        // Fallback: gerar título no PHP se não foi enviado pelo JS
        $title = isset( $_POST['title'] ) && !empty( $_POST['title'] ) 
            ? wp_strip_all_tags( $_POST['title'] )
            : $this->generate_title();
        
        // DEBUG: $this->log_debug('process_form', 'Título gerado: ' . ($title ?? 'não gerado'));

        // Editar post
        $edit_post_id = $this->edit_post_operation( $this->post_id, $title );
        if( !$edit_post_id ) { 
            // DEBUG: $this->log_debug('process_form', 'Falhou em edit_post_operation()');
            return false; 
        }
        // DEBUG: $this->log_debug('process_form', 'edit_post_operation() passou - post_id: ' . $edit_post_id);

        // DEFAULT ACTIONS - Validar retornos
        if( !$this->add_acf_fields($edit_post_id) ) {
            // DEBUG: $this->log_debug('process_form', 'Falhou em add_acf_fields()');
            return false;
        }
        // DEBUG: $this->log_debug('process_form', 'add_acf_fields() passou');

        if( !$this->add_taxonomy_terms($edit_post_id) ) {
            // DEBUG: $this->log_debug('process_form', 'Falhou em add_taxonomy_terms()');
            return false;
        }
        // DEBUG: $this->log_debug('process_form', 'add_taxonomy_terms() passou');

        if( !$this->add_meta_fields($edit_post_id) ) {
            // DEBUG: $this->log_debug('process_form', 'Falhou em add_meta_fields()');
            return false;
        }
        // DEBUG: $this->log_debug('process_form', 'add_meta_fields() passou');

        // Salvar dados de proximidade baseados no CEP (não bloqueia se falhar)
        $this->add_proximidade_data($edit_post_id);

        if( !$this->upload_files($edit_post_id) ) {
            // DEBUG: $this->log_debug('process_form', 'Falhou em upload_files()');
            return false;
        }
        // DEBUG: $this->log_debug('process_form', 'upload_files() passou');

        if( !$this->process_emails($edit_post_id) ) {
            // DEBUG: $this->log_debug('process_form', 'Falhou em process_emails()');
            return false;
        }
        // DEBUG: $this->log_debug('process_form', 'process_emails() passou');
        
        if( !$this->add_taxonomy_custom($edit_post_id) ) {
            // DEBUG: $this->log_debug('process_form', 'Falhou em add_taxonomy_custom()');
            return false;
        }
        // DEBUG: $this->log_debug('process_form', 'add_taxonomy_custom() passou');
        
        $this->update_attachments_order();
        // DEBUG: $this->log_debug('process_form', 'update_attachments_order() executado');
        
        if( isset($_POST['post_thumbnail']) ){
            $this->set_thumbnail( 
                $this->post_id, 
                wp_strip_all_tags($_POST['post_thumbnail']) 
            );
            // DEBUG: $this->log_debug('process_form', 'set_thumbnail() executado');
        };

        $this->set_qrcode( $edit_post_id );
        // DEBUG: $this->log_debug('process_form', 'set_qrcode() executado');

        // Se havia indeferimento antes da edição, marcar como em reavaliação
        if( $this->had_indeferimento ){
            update_field('reavaliacao', true, $edit_post_id);
            // DEBUG: $this->log_debug('process_form', 'reavaliacao atualizado');
        };
        
        if( !$this->process_successful_operation($edit_post_id) ) {
            // DEBUG: $this->log_debug('process_form', 'Falhou em process_successful_operation()');
            return false;
        }
        // DEBUG: $this->log_debug('process_form', 'process_successful_operation() passou');

        // DEBUG: $this->log_debug('process_form', 'process_form() concluído com sucesso');
        return true;
    }

    protected function validationComponents(){

        $this->debug_log['post_data'] = $_POST;

        foreach( $_POST['componente'] as $parent => $child) {        
            
            if( empty($child) ) continue;

            $parent = intval($parent);

            $this->debug_log['componente'][] = '$parent_id: ' .$parent;
            $this->debug_log['marca'][] = 'marca: ' .$_POST['c_marca'][$parent];
            $this->debug_log['modelo'][] = 'modelo: ' .$_POST['c_modelo'][$parent];
            
            //$parent_id = intval($parent);
            
            // $this->debug_log['$parent_id'] = '$parent_id: ' .$parent_id;
            // $this->debug_log['marca_'.$parent_id] = 'Marca: ' . $_POST['c_marca'][$parent_id];
            // $this->debug_log['modelo_'.$parent_id] = 'Modelo: '.$_POST['c_modelo'][$parent_id];
        }
        // Método de debug - comentado
        // $this->definir_erro_servidor('Debug Componente');
    }


    protected function add_taxonomy_custom($post_id = null)
    {
        if( !$this->default_validation($post_id) ) return false;

        if( isset($_POST['modalidade']) && !empty($_POST['modalidade']) ) {
            wp_set_object_terms($post_id, wp_strip_all_tags($_POST['modalidade']), 'modalidade');
        }

        if( isset($_POST['velocidades']) && !empty($_POST['velocidades']) ) {
            wp_set_object_terms($post_id, wp_strip_all_tags($_POST['velocidades']), 'velocidades');
        }

        if( !$this->add_components_($post_id) ) {
            return false;
        }

        return true;
    }

    // Adiciona componentes ao post
    private function add_components_($post_id){

        if( !$this->default_validation($post_id) ) return false;

        // Limpa todos os valores existentes
        delete_field('componentes', $post_id);

        $terms_to_add = [];

        // EXCEÇÃO: Processamento especial para o quadro
        if (isset($_POST['quadro']) && isset($_POST['quadro_id']) && !empty($_POST['quadro'])):

            $componente_id = intval(wp_strip_all_tags($_POST['quadro']));
            $componente_parent_id = intval(wp_strip_all_tags($_POST['quadro_id']));

            $terms_to_add[] = $componente_id;
            $terms_to_add[] = $componente_parent_id;

            $value_quadro = [
                'componente_id' => $componente_id,
                'parent_id' => $componente_parent_id,
                'marca' => wp_strip_all_tags($_POST['marcas_modelos'] ?? ''),
                'modelo' => wp_strip_all_tags($_POST['marcas_modelos_child'] ?? ''),
            ];
            $add_quadro = add_row('componentes', $value_quadro, $post_id);            
            if (!$add_quadro){
                // $this->log_debug('add_components_', 'ACF Fields add_row() failed para quadro!');
                $this->definir_erro_servidor('Não foi possível adicionar o quadro ao anúncio.');
                return false;
            }
        endif;

        // Processa os outros componentes
        if (isset($_POST['componente']) && is_array($_POST['componente'])) {
            foreach ($_POST['componente'] as $parent => $child ) {
                
                if (empty($child)) continue;

                $componente_id = wp_strip_all_tags($child);
                $componente_parent_id = wp_strip_all_tags($parent);

                $terms_to_add[] = intval($componente_id);
                $terms_to_add[] = intval($componente_parent_id);

                $value_componente = [
                    'componente_id' => $componente_id,
                    'parent_id' => $componente_parent_id,
                    'marca' => wp_strip_all_tags($_POST['c_marca'][$componente_parent_id] ?? ''),
                    'modelo' => wp_strip_all_tags($_POST['c_modelo'][$componente_parent_id] ?? ''),
                ];
                
                $add_componente = add_row('componentes', $value_componente, $post_id);
                if (!$add_componente) {
                    $this->log_debug('add_components_', 'Erro ao adicionar componente: ' . $componente_id);
                    $this->definir_erro_servidor('Não foi possível adicionar o componente ao anúncio.');
                    return false;
                }
                
            }
        }

        // Adiciona os termos à taxonomia
        if (!empty($terms_to_add)) {
            wp_set_object_terms($post_id, $terms_to_add, 'componente');
        }

        return true;
    }

    /**
     * Valida se a categoria pode ser alterada
     * Regra: Se o anúncio foi criado como 'bicicleta', não pode mudar para 'peca' ou 'acessorio'
     * @return bool true se pode alterar ou se não está tentando alterar
     */
    protected function validate_category_change() {
        
        // Se não tem post_id, não é edição válida
        if( empty($this->post_id) ) {
            return true; // Deixa outras validações tratarem
        }

        // Obter categoria original do post
        $original_categories = get_the_terms( $this->post_id, 'category' );
        if( !$original_categories || is_wp_error($original_categories) || empty($original_categories) ) {
            return true; // Se não tem categoria, deixa outras validações tratarem
        }

        // Buscar categoria pai (original)
        $original_category = null;
        foreach( $original_categories as $cat ) {
            if( $cat->parent == 0 ) {
                $original_category = $cat;
                break;
            }
        }
        // Se não encontrou pai, pegar a primeira
        if( !$original_category ) {
            $original_category = $original_categories[0];
        }

        $original_category_slug = $original_category->slug;

        // Obter categoria que está sendo enviada no POST
        $new_category = isset($_POST['category']) ? wp_strip_all_tags($_POST['category']) : null;

        // Se não tem nova categoria no POST, não está tentando alterar
        if( empty($new_category) ) {
            return true;
        }

        // REGRA: Se original é 'bicicleta' e nova categoria é diferente, bloquear
        if( $original_category_slug === 'bicicleta' && $new_category !== 'bicicleta' ) {
            $this->definir_erro_servidor(
                'Não é possível alterar a categoria de um anúncio de bicicleta. Para cadastrar uma peça ou acessório, crie um novo anúncio.',
                'validate_category_change',
                'Tentativa de alterar categoria de bicicleta para ' . $new_category
            );
            return false;
        }

        // Outras categorias podem ser editadas normalmente (dentro da mesma categoria)
        return true;
    }    

}
?>