<?php 
/**
 * Formata a exibição da taxonomia 'cidade' no padrão: "Cidade / Estado"
 * 
 * @param int|null $post_id ID do post. Se não fornecido, usa get_the_ID()
 * @return string String formatada no padrão "Cidade / Estado" ou string vazia se não encontrar
 */
if (!defined('ABSPATH')) {
    exit;
}

/*
* Formata a exibição da taxonomia 'cidade' no padrão: "Cidade / Estado"
* 
* @param int|null $post_id ID do post. Se não fornecido, usa get_the_ID()
* @param array|null $cidade_terms Array de termos da taxonomia 'cidade'. Se não fornecido, usa get_the_terms($post_id, 'cidade')
* @return string String formatada no padrão "Cidade / Estado" ou string vazia se não encontrar
*/
function format_city_state( $post_id = null, $cidade_terms = null ) {
    
    // Se não foi passado post_id, tenta usar o ID do post atual  
    $post_id = ( $post_id !== null || !empty($post_id) )
        ? $post_id
        : get_the_ID();
        
    // Buscar termos da taxonomia 'cidade' para o post caso não tenha sido passado no parâmetro
    if( $cidade_terms === null || empty($cidade_terms) ) {
        $cidade_terms = get_the_terms($post_id, 'cidade');
    }
    
    // Se foi passado um objeto WP_Term único, converter para array
    if( is_object($cidade_terms) && isset($cidade_terms->term_id) && !is_array($cidade_terms) ) {
        // É um objeto WP_Term único, converter para array
        $term_obj = $cidade_terms;
        $cidade_terms = array($term_obj);
        
        // Se tiver parent, buscar o termo pai também (cidade tem estado como parent)
        if( isset($term_obj->parent) && $term_obj->parent != 0 && $term_obj->parent !== '0' ) {
            $parent_term = get_term($term_obj->parent);
            if( $parent_term && !is_wp_error($parent_term) ) {
                // Adicionar o parent no início do array (estado vem antes)
                array_unshift($cidade_terms, $parent_term);
            }
        }
    }
    
    // Garantir que é um array
    if( !is_array($cidade_terms) ) {
        return '';
    }
    
    // Verificar se há termos e se não é erro
    if( empty($cidade_terms) || is_wp_error($cidade_terms) ) return '';
    
    // Identificar estado (pai) e cidade (filho)
    $estado_term = null;
    $cidade_term = null;
    
    foreach( $cidade_terms as $term ){
        // Verificar se $term é um objeto válido
        if( !is_object($term) || !isset($term->parent) ) {
            continue;
        }
        
        if( $term->parent === 0 || $term->parent === '0' ){
            // Termo pai = Estado
            $estado_term = $term;
        } else {
            // Termo filho = Cidade
            $cidade_term = $term;
        }
    }
    
    // Se encontrou estado e cidade, formatar
    if( $estado_term && $cidade_term ){
        // Formato: "Cidade / Estado" (nome completo do estado)
        return esc_html($cidade_term->name) . ' / ' . strtoupper(esc_html($estado_term->slug));
    }
    
    // Fallback: se não encontrou ambos, tentar usar os dois primeiros termos do array
    if( count($cidade_terms) >= 2 ){
        // Assumir que o primeiro é estado e o segundo é cidade (ou vice-versa)
        $term1 = $cidade_terms[0];
        $term2 = $cidade_terms[1];
        
        // Verificar qual é pai e qual é filho
        if( $term1->parent == 0 && $term2->parent != 0 ){
            // term1 é estado, term2 é cidade
            return esc_html($term2->name) . ' / ' . strtoupper(esc_html($term1->slug));
        } 
        elseif( $term2->parent == 0 && $term1->parent != 0 ){
            // term2 é estado, term1 é cidade
            return esc_html($term1->name) . ' / ' . strtoupper(esc_html($term2->slug));
        } 
        else {
            // Se não conseguiu identificar, usar ordem do array
            return esc_html($term1->name) . ' / ' .strtoupper(esc_html($term2->slug));
        }
    }
    
    // Se só tem um termo, retornar apenas ele
    if( count($cidade_terms) == 1 ){
        return esc_html($cidade_terms[0]->name);
    }
    
    return '';
}
?>