<?php
class __Bazar_Verify{

    public $label;
    public $action;
    public $nonce;   
	public $data_output;

	public function _bazar_check_security() {

        // Salvar valores originais antes de sobrescrever com valores do POST
        $original_action = $this->action; // Valor original da classe (ex: 'bazar_cancelar_conta')
        $original_nonce_name = $this->nonce; // Nome original do nonce (ex: 'nonce_cancelar_conta')

        // Buscar action do POST (sobrescreve $this->action)
        $action_from_post = ( isset($_POST['action']) ) ? 
            wp_strip_all_tags($_POST['action']) : 
            null;
        $this->action = $action_from_post;

        // Determinar nome do campo nonce no POST
        // Se $original_nonce_name está definido, usar diretamente (prioridade)
        // Caso contrário, construir a partir de $this->label (fallback)
        $nonce_field_name = null;
        $nonce_name_for_verification = null; // Nome do nonce para wp_verify_nonce()
        
        if( !empty($original_nonce_name) ) {
            // Se $original_nonce_name está definido, usar esse nome de campo
            $nonce_field_name = $original_nonce_name;
            $nonce_name_for_verification = $original_nonce_name; // Salvar nome antes de sobrescrever
        } else {
            // Fallback: construir a partir de $this->label
            $nonce_field_name = 'nonce_'.$this->label;
            $nonce_name_for_verification = 'nonce_'.$this->label;
        }

        // Buscar valor do nonce no POST (sobrescreve $this->nonce com o valor)
        $nonce_value = ( isset($_POST[$nonce_field_name]) ) ? 
            wp_strip_all_tags($_POST[$nonce_field_name]) : 
            null;
        
        $this->nonce = $nonce_value; 

        // Verificar action
        // Se $original_action está definido na classe, usar para comparação (prioridade)
        // Caso contrário, usar 'bazar_'.$this->label como padrão
        $expected_action = !empty($original_action) ? $original_action : 'bazar_'.$this->label;
        
        if( empty( $this->action ) || $this->action != $expected_action ) :
            $this->definir_erro_seguranca('Valor da action inválido: ' . ($this->action ?? 'não definido') . ' | Esperado: ' . $expected_action);
            return false;
        endif;

        // Verificar nonce
        if( empty( $this->nonce ) ) :
            if( method_exists($this, 'definir_erro_seguranca') ) {
                $this->definir_erro_seguranca('Nonce não encontrado no POST. Campo esperado: ' . $nonce_field_name);
            }
            return false;
        endif;

        // Verificar validade do nonce usando o nome correto
        $nonce_verified = wp_verify_nonce( $this->nonce, $nonce_name_for_verification );
        if( !$nonce_verified ) :
            if( method_exists($this, 'definir_erro_seguranca') ) {
                $this->definir_erro_seguranca('Nonce inválido ou expirado. Atualize a página e tente novamente.');
            }
            return false;
        endif;

        return true;
    }
	
};	
?>