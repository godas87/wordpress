<?php
/**
 * AJAX para buscar dados básicos do anúncio (thumbnail, título, preço)
 * Usado no modal de impulsionar
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_bazar_get_product_data_impulsionar', 'bazar_get_product_data_impulsionar');
add_action('wp_ajax_nopriv_bazar_get_product_data_impulsionar', 'bazar_get_product_data_impulsionar');

function bazar_get_product_data_impulsionar() {
    // Verificar nonce (opcional, mas recomendado)
    // check_ajax_referer('bazar_get_anuncio_data', 'nonce');
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    if (!$post_id) {
        wp_send_json_error(array('message' => 'ID do anúncio não fornecido'));
        return;
    }
    
    // Verificar se usuário está logado
    $user_id = get_current_user_id();
    if (empty($user_id)) {
        wp_send_json_error(array('message' => 'Você precisa estar logado para impulsionar um anúncio.'));
        return;
    }
    
    // Verificar se o post existe
    $product_data = bazar_get_product_card_data( $post_id );
    if (!$product_data || !is_array($product_data) || !isset($product_data['id'])) {
        wp_send_json_error(array('message' => 'Anúncio não encontrado'));
        return;
    }
    
    // Verificar se usuário é o autor do anúncio
    // Usar author['id'] que já está disponível no product_data
    $author_id = isset($product_data['author']['id']) ? intval($product_data['author']['id']) : 0;
    if ($author_id === 0 || $author_id !== intval($user_id)) {
        wp_send_json_error(array('message' => 'Você não é o autor deste anúncio.'));
        return;
    }
    
    if (function_exists('bazar_can_boost_anuncio')) {
        $result_boost = bazar_can_boost_anuncio($post_id, $user_id);
        $can_pending_checkout = (
            !$result_boost['can']
            && get_post_status($post_id) === 'pending'
            && function_exists('bazar_can_offer_boost_checkout_on_success')
            && bazar_can_offer_boost_checkout_on_success($post_id, $user_id)
        );
        if (!$result_boost['can'] && !$can_pending_checkout) {
            wp_send_json_error(array('message' => $result_boost['message'] ? $result_boost['message'] : 'Não é possível impulsionar este anúncio.'));
            return;
        }
    }
    
    // Buscar dados
    $thumb = $product_data['images']['featured_medium'];
    $title = $product_data['title'];
    $preco = $product_data['formatted']['valor'];
    
    // Verificar se anúncio está em destaque
    $is_destaque = $product_data['status_data']['is_destaque'];
    
    // Verificar se usuário já usou o desconto de 50%
    // IMPORTANTE: usar $user_id (usuário logado atual), não sobrescrever
    $have_desconto_newsletter = false;
    if ( $user_id > 0 && function_exists('bazar_have_desconto_newsletter')) {
        $have_desconto_newsletter = bazar_have_desconto_newsletter($user_id);
    }
    
    wp_send_json_success(array(
        'thumb' => $thumb,
        'title' => $title,
        'preco' => $preco,
        'is_destaque' => $is_destaque,
        'have_desconto_newsletter' => $have_desconto_newsletter,
    ));
}

