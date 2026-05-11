<?php
/**
 * Helpers centralizados para manipulação de termos
 * 
 * Este arquivo contém funções helper para:
 * - Obter ID do termo 'vendido' com cache
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obtém o ID do termo 'vendido' na taxonomia 'status' com cache persistente
 * 
 * Esta função usa cache persistente do WordPress (wp_cache) para evitar
 * múltiplas queries ao banco de dados. O cache é infinito e persiste entre
 * requisições. O termo 'vendido' é usado em vários lugares do sistema e
 * dificilmente mudará, tornando o cache infinito ideal.
 * 
 * @return int Term ID ou 0 se não existir
 */
if (!function_exists('bazar_get_vendido_term_id')) {    

    function bazar_get_vendido_term_id() {

        $cache_size = 604800; // 7 dias        
        $cache_key = 'bazar_vendido_term_id';
        $cache_group = 'bazar_terms';
        
        // Tenta obter do cache
        $vendido_term_id = wp_cache_get($cache_key, $cache_group);
        
        // Se não estiver em cache, busca no banco e armazena
        if ($vendido_term_id === false || $vendido_term_id === null || $vendido_term_id === 0) {
            $vendido_term = get_term_by('slug', 'vendido', 'status');
            $vendido_term_id = $vendido_term && !is_wp_error($vendido_term) 
                ? (int)$vendido_term->term_id 
                : 0;
            
            // Armazena no cache por 7 dias (604800 segundos)
            // Cache é limpo automaticamente via hooks quando termos são editados
            wp_cache_set($cache_key, $vendido_term_id, $cache_group, $cache_size);
        }
        
        return $vendido_term_id;
    }
}


if (!function_exists('bazar_get_destaque_term_id')) {
    function bazar_get_destaque_term_id() {
        
        $cache_size = 604800; // 7 dias
        $cache_key = 'bazar_destaque_term_id';
        $cache_group = 'bazar_terms';
        
        // Tenta obter do cache
        $destaque_term_id = wp_cache_get($cache_key, $cache_group);
        
        // Se não estiver em cache, busca no banco e armazena
        if ($destaque_term_id === false || empty($destaque_term_id)) {
            $destaque_term = get_term_by('slug', 'destaque', 'status');
            $destaque_term_id = $destaque_term && !is_wp_error($destaque_term) 
                ? (int)$destaque_term->term_id 
                : 0;
            
            // Armazena no cache por 7 dias (604800 segundos)
            // Cache é limpo automaticamente via hooks quando termos são editados
            wp_cache_set($cache_key, $destaque_term_id, $cache_group, $cache_size);
        }
        
        return $destaque_term_id;
    }
}

// Função para limpar o cache dos termos
// Deve ser chamado quando termos são editados/criados/deletados
// @return bool True se os caches foram limpos com sucesso
// Hooks para limpar o cache dos termos
if (!function_exists('bazar_clear_terms_cache')) {
    
    add_action('edited_term', 'bazar_clear_terms_cache', 10, 3);
    add_action('created_term', 'bazar_clear_terms_cache', 10, 3);
    add_action('delete_term', 'bazar_clear_terms_cache', 10, 5);
    function bazar_clear_terms_cache() {

        $taxonomy = isset($_GET['taxonomy']) ? $_GET['taxonomy'] : null;

        if ($taxonomy == 'status') {
            wp_cache_delete('bazar_vendido_term_id', 'bazar_terms');
            wp_cache_delete('bazar_destaque_term_id', 'bazar_terms');
        }
    }
};
?>