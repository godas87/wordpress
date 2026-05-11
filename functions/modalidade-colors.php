<?php
/**
 * Cores das modalidades (estilo UCI)
 * 
 * Sistema de cores para identificar visualmente cada modalidade
 * Modalidades não listadas usam a cor padrão "urbana"
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retorna array com cores das modalidades
 * 
 * @return array Array associativo ['slug_modalidade' => '#cor_hex']
 */
function bazar_get_modalidade_colors() {
    return array(
        // Modalidades principais com cores específicas
        'mountain-bike-mtb' => '#2d5016',      // Verde escuro
        'mountain-bike' => '#2d5016',          // Verde escuro (alternativo)
        'mtb' => '#2d5016',                    // Verde escuro (abreviação)
        
        'speed' => '#0066cc',                  // Azul
        'estrada' => '#0066cc',                // Azul (alternativo)
        'road' => '#0066cc',                   // Azul (inglês)
        
        'gravel' => '#ff6600',                 // Laranja
        'gravel-bike' => '#ff6600',            // Laranja (alternativo)
        
        'eletrica' => '#9933cc',               // Roxo
        'eletrica' => '#9933cc',               // Roxo (alternativo)
        'electric' => '#9933cc',               // Roxo (inglês)
        
        'bmx' => '#ffcc00',                     // Amarelo
        'bmx-racing' => '#ffcc00',              // Amarelo (alternativo)
        
        'downhill' => '#990000',               // Vermelho escuro
        'dh' => '#990000',                     // Vermelho escuro (abreviação)
        
        'triathlon' => '#0099cc',              // Azul claro
        'triatlo' => '#0099cc',                 // Azul claro (português)
        
        // Cor padrão para modalidades não listadas (urbana, custom, cargueira, etc.)
        'default' => '#333333',                 // Cinza escuro
        'urbana' => '#333333',                 // Cinza escuro
        'custom' => '#333333',                  // Cinza escuro
        'cargueira' => '#333333',              // Cinza escuro
        'dobravel' => '#333333',               // Cinza escuro
        'chopper' => '#333333',                // Cinza escuro
        'infantil' => '#333333',               // Cinza escuro
        'equilibrio' => '#333333',             // Cinza escuro
        'freerider' => '#333333',              // Cinza escuro
        'gavel' => '#333333',                  // Cinza escuro
        'vintage' => '#333333',                // Cinza escuro
    );
}

/**
 * Retorna a cor de uma modalidade específica
 * 
 * @param string $modalidade_slug Slug da modalidade
 * @return string Cor em hexadecimal (ex: #2d5016)
 */
function bazar_get_modalidade_color($modalidade_slug) {
    $colors = bazar_get_modalidade_colors();
    $slug = strtolower(trim($modalidade_slug));
    
    // Buscar cor específica
    if (isset($colors[$slug])) {
        return $colors[$slug];
    }
    
    // Se não encontrar, retornar cor padrão (urbana)
    return $colors['default'];
}

/**
 * Retorna array de cores para uso em SCSS/CSS
 * Pode ser usado para gerar variáveis CSS
 * 
 * @return array Array com cores formatadas para CSS
 */
function bazar_get_modalidade_colors_css() {
    $colors = bazar_get_modalidade_colors();
    $css_array = array();
    
    foreach ($colors as $slug => $color) {
        $css_array[$slug] = $color;
    }
    
    return $css_array;
}

