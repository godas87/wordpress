<?php
/**
 * Helper centralizado para Stripe - Cache de queries
 * Centraliza get_post(), get_userdata() e get_user_meta() com cache
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// SISTEMA DE CACHE - Otimização de Queries
// ============================================

/**
 * Cache estático para posts - evita múltiplas queries get_post()
 * @param int $post_id ID do post
 * @return WP_Post|null Objeto post ou null se não encontrado
 */
function bazar_stripe_get_post_cached($post_id) {
    static $cache = array();
    
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }
    
    $post = get_post($post_id);
    if ($post) {
        $cache[$post_id] = $post;
    }
    
    return $post;
}

/**
 * Cache estático para usuários - evita múltiplas queries get_userdata()
 * @param int $user_id ID do usuário
 * @return WP_User|false Objeto usuário ou false se não encontrado
 */
function bazar_stripe_get_user_cached($user_id) {
    static $cache = array();
    
    if (isset($cache[$user_id])) {
        return $cache[$user_id];
    }
    
    $user = get_userdata($user_id);
    if ($user) {
        $cache[$user_id] = $user;
    }
    
    return $user;
}

/**
 * Cache estático para user_meta - evita múltiplas queries get_user_meta()
 * @param int $user_id ID do usuário
 * @param string $meta_key Chave do meta
 * @param bool $single Se deve retornar valor único
 * @return mixed Valor do meta
 */
function bazar_stripe_get_user_meta_cached($user_id, $meta_key, $single = true) {
    static $cache = array();
    $cache_key = $user_id . '_' . $meta_key . '_' . ($single ? '1' : '0');
    
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }
    
    $value = get_user_meta($user_id, $meta_key, $single);
    $cache[$cache_key] = $value;
    
    return $value;
}

/**
 * Obtém dados completos do produto usando helper otimizado
 * @param int $post_id ID do post
 * @return array|null Dados do produto ou null se não encontrado
 */
function bazar_stripe_get_product_data_cached($post_id) {
    static $cache = array();
    
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }
    
    // Usar helper otimizado que já tem cache interno
    if (function_exists('bazar_get_product_card_data')) {
        $data = bazar_get_product_card_data($post_id);
        if ($data) {
            $cache[$post_id] = $data;
            return $data;
        }
    }
    
    // Fallback: usar get_product_data completo
    if (function_exists('bazar_get_product_data')) {
        $data = bazar_get_product_data($post_id);
        if ($data) {
            $cache[$post_id] = $data;
            return $data;
        }
    }
    
    return null;
}
