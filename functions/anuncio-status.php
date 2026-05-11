<?php
/**
 * Função para obter o status globalizado do anúncio
 * Considera se há motivos de indeferimento para retornar status 'indeferido'
 * 
 * @param int $post_id ID do post
 * @return array Array com 'status' (string) e 'motivos_indeferimento' (string|null)
 */

 // Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}
function bazar_get_anuncio_status( $post_id, $post_status = null ) {
    
    $post_id = ( isset($post_id) && !empty($post_id) && is_numeric($post_id) ) 
        ? $post_id 
        : get_the_ID();

    $post_status = ( isset($post_status) && !empty($post_status) ) 
        ? $post_status 
        : get_post_status( $post_id );

    $is_vendido = ( $post_id ) 
        ? has_term('vendido', 'status', $post_id)
        : false;        

    $is_destaque = ( $post_id ) 
        ? has_term('destaque', 'status', $post_id)
        : false;        
    
    $motivos_indeferimento = '';

    if( $post_status === 'publish' && $is_vendido ) {
        return array(
            'status' => 'vendido',
            'motivos_indeferimento' => $motivos_indeferimento,
            'original_status' => $post_status,
            'is_vendido' => $is_vendido,
            'is_destaque' => $is_destaque
        );
    }
    
    if( $post_status === 'publish' && $is_destaque ) {
        return array(
            'status' => 'destaque',
            'motivos_indeferimento' => $motivos_indeferimento,
            'original_status' => $post_status,
            'is_vendido' => $is_vendido,
            'is_destaque' => $is_destaque
        );
    }
    $motivos_indeferimento = get_field('motivos_para_indeferimento', $post_id);
    
    // REGRA: Anúncios indeferidos/reprovados SEMPRE devem ter post_status = 'draft'
    // Se há motivos de indeferimento, o status DEVE ser 'draft'
    if( !empty($motivos_indeferimento) ) {
        // Se tem motivos mas não está em 'draft', corrigir automaticamente
        if( $post_status !== 'draft' ) {
            // Migrar para 'draft' automaticamente
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft'
            ));
            $post_status = 'draft';
        }
        
        // Retornar como 'indeferido'
        return array(
            'status' => 'indeferido',
            'motivos_indeferimento' => $motivos_indeferimento,
            'original_status' => 'draft', // Sempre 'draft' para indeferidos
            'is_vendido' => false,
            'is_destaque' => $is_destaque
        );
    }

    // REGRA: Pending + aprovado pelo ADM = aguardando perfil completo para publicar (e-mail não bloqueia)
    $meta_aprovado_adm = defined('BAZAR_META_APROVADO_ADM') ? BAZAR_META_APROVADO_ADM : 'bazar_anuncio_aprovado_adm';
    if ( $post_status === 'pending' && get_post_meta( $post_id, $meta_aprovado_adm, true ) === '1' ) {
        return array(
            'status' => 'aprovado_aguardando_dados',
            'motivos_indeferimento' => $motivos_indeferimento,
            'original_status' => 'pending',
            'is_vendido' => false,
            'is_destaque' => $is_destaque
        );
    }
    
    return array(
        'status' => $post_status,
        'motivos_indeferimento' => $motivos_indeferimento,
        'original_status' => $post_status,
        'is_vendido' => $is_vendido,
        'is_destaque' => $is_destaque
    );
}

/**
 * Função para obter o label do status do anúncio
 * 
 * @param string|array $status Status do anúncio (string) ou array com 'status' (array)
 * @return string Label do status
 */
function bazar_get_anuncio_status_label( $status = null ) {

    $status = bazar_get_anuncio_status_value( $status );
    
    $labels = array(
        'publish' => 'Aprovado',
        'pending' => 'Aguardando aprovação',
        'aprovado_aguardando_dados' => 'Confirme seus dados',
        'indeferido' => 'Indeferido',
        'vendido' => 'Vendido',
        'draft' => 'Rascunho',
        'trash' => 'Lixeira'
    );
    
    return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
}

/**
 * Função para obter a classe CSS do status do anúncio
 * 
 * @param string|array $status Status do anúncio (string) ou array com 'status' (array)
 * @return string Classe CSS
 */
function bazar_get_anuncio_status_class( $status = null ) {
    
    $status = bazar_get_anuncio_status_value( $status );
    
    $classes = array(
        'publish' => 'green',
        'pending' => 'red',
        'aprovado_aguardando_dados' => 'orange',
        'indeferido' => 'red',
        'vendido' => 'silver',
        'draft' => 'silver',
        'trash' => 'silver',
        'destaque' => 'green'
    );
    
    return isset($classes[$status]) ? $classes[$status] : 'silver';
}

/**
 * Função para obter o ícone do status do anúncio
 * 
 * @param string|array $status Status do anúncio (string) ou array com 'status' (array)
 * @return string HTML do ícone
 */
function bazar_get_anuncio_status_icon( $status = null ) {
    
    $status = bazar_get_anuncio_status_value( $status );

    // var_dump($status);
    
    $icons = array(
        'publish' => '<i class="fa fa-check-circle green"></i>',
        'pending' => '<i class="fa fa-clock red"></i>',
        'aprovado_aguardando_dados' => '<i class="fa fa-check-circle orange"></i>',
        'indeferido' => '<i class="fa fa-times-circle red"></i>',
        'vendido' => '<i class="fa fa-check-circle silver"></i>',
        'draft' => '<i class="fa fa-edit silver"></i>',
        'trash' => '<i class="fa fa-trash silver"></i>',
        'destaque' => '<i class="fas fa-star green"></i>'
    );
    
    return isset($icons[$status]) ? $icons[$status] : '<i class="fa fa-question silver"></i>';
}

function bazar_get_anuncio_status_value( $status ) {
    // Se for array, extrair o status
    if (is_array($status)) {
        $status = isset($status['status']) ? $status['status'] : 'publish';
    }    
    // Garantir que seja string
    $status = ( isset($status) && !empty($status) && is_string($status) ) 
        ? $status 
        : 'publish';
    return $status;
}