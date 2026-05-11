<?php
/**
 * Helper functions para gerenciar localização em filtros
 * 
 * Este arquivo contém funções helper para:
 * - Preparar dados de localização para selects
 * - Determinar localização atual baseada no contexto
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prepara dados de localização para exibição nos selects
 * 
 * @param WP_Term|null $current_term Termo atual da URL
 * @param array $current_location Localização do usuário
 * @param object $geo_api Instância da API de geolocalização
 * @return array Array com: 'current_estado', 'current_cidade', 'estados_com_anuncios', 'cidades_disponiveis'
 */
if (!function_exists('bazar_prepare_location_for_filter')) {
    function bazar_prepare_location_for_filter($current_term = null, $current_location = null, $geo_api = null) {
        
        $result = array(
            'current_estado' => '',
            'current_cidade' => '',
            'estados_com_anuncios' => array(),
            'cidades_disponiveis' => array()
        );
        
        // Obter API se não fornecida
        if (!$geo_api) {
            global $geo_api;
            if (!$geo_api) {
                $geo_api = BazarBikes_GeoAPI::getInstance();
            }
        }
        
        // Obter localização do usuário se não fornecida
        if (!$current_location || empty($current_location['localizacao'])) {
            global $current_location;
            if (!$current_location || empty($current_location['localizacao'])) {
                $current_location = bazar_get_current_location();
            }
        }
        
        // Se estamos em uma página de taxonomia de cidade, não usar localização do usuário
        // Permitir que o usuário altere a cidade
        $is_cidade_taxonomy = ($current_term && !is_wp_error($current_term) && $current_term->taxonomy === 'cidade');
        
        if ($is_cidade_taxonomy) {
            // Em página de cidade, não preencher com localização do usuário
            // Permitir seleção livre
            $result['current_estado'] = '';
            $result['current_cidade'] = '';
            
            // Preencher com a cidade da URL para mostrar como selecionada
            if ($current_term->parent == 0) {
                // É um estado
                $result['current_estado'] = $current_term->term_id;
            } else {
                // É uma cidade
                $estado_term = get_term($current_term->parent, 'cidade');
                if ($estado_term && !is_wp_error($estado_term)) {
                    $result['current_estado'] = $estado_term->term_id;
                }
                $result['current_cidade'] = $current_term->term_id;
            }
        } else {
            // Usar localização do usuário
            $result['current_estado'] = ($current_location) 
                ? $current_location['localizacao']['estado_term_id'] 
                : '';
            $result['current_cidade'] = ($current_location) 
                ? $current_location['localizacao']['cidade_term_id'] 
                : '';
        }
        
        // Buscar estados e cidades disponíveis
        $result['estados_com_anuncios'] = $geo_api->get_estados_com_anuncios();
        $result['cidades_disponiveis'] = ($result['current_estado'] && $result['current_estado'] !== '') 
            ? $geo_api->get_cidades_com_anuncios($result['current_estado']) 
            : array();
        
        return $result;
    }
}

