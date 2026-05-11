<?php
/**
 * Função helper para buscar marcas com cache
 * 
 * Busca marcas da taxonomia 'marca-modelo' que têm imagem,
 * embaralha e retorna até 6 marcas para exibição.
 * Usa wp_cache para otimizar performance.
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Busca marcas com imagem para exibição
 * 
 * @param int $limit Número máximo de marcas a retornar (padrão: 6)
 * @return array Array de objetos WP_Term com imagem
 */
if (!function_exists('bazar_get_brands_with_images')) {
    function bazar_get_brands_with_images($limit = 6)
    {

        $cache_expire = 1800; // 30 minutos

        // Chave e grupo do cache
        $cache_key = 'brands_with_images_' . $limit;
        $cache_group = 'bazar_brands';

        // Tentar obter do cache
        $cached = wp_cache_get($cache_key, $cache_group);
        if ($cached !== false && is_array($cached) && !empty($cached)) {
            // Retornar diretamente os objetos do cache (sem queries adicionais)
            return $cached;
        }

        // Se não há cache, buscar do banco
        // Buscar mais termos para garantir que teremos pelo menos $limit com imagem
        $terms = get_terms('marca-modelo', array(
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'desc',
            'number' => 15, // Buscar mais para garantir que teremos termos com imagem
            'hierarchical' => false,
            'parent' => 0 // Apenas termos pais (marcas principais)
        ));

        if (empty($terms) || is_wp_error($terms)) {
            return array();
        }

        // Filtrar apenas termos que têm imagem
        $terms_with_images = array();
        foreach ($terms as $term) {
            $image = get_field('imagem', $term);

            if ($image && !empty($image)) {
                // ACF pode retornar ID (int) ou array com 'ID', 'url', 'width', 'height'
                $attachment_id = is_array($image) ? (int) ($image['ID'] ?? $image['id'] ?? 0) : (int) $image;
                if ($attachment_id <= 0) {
                    continue;
                }

                $image_url = '';
                $image_width = 200;
                $image_height = 200;

                // Se ACF retornou array com width/height, usar (são do tamanho original)
                if (is_array($image) && !empty($image['width']) && !empty($image['height'])) {
                    $image_width = (int) $image['width'];
                    $image_height = (int) $image['height'];
                }

                // URL e dimensões: usar tamanho registrado 'thumbnail' (sempre existe) para evitar 1x1
                $image_data = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                if (!empty($image_data) && !empty($image_data[0])) {
                    $image_url = $image_data[0];
                    if (!empty($image_data[1]) && !empty($image_data[2]) && $image_data[1] > 1 && $image_data[2] > 1) {
                        $image_width = (int) $image_data[1];
                        $image_height = (int) $image_data[2];
                    }
                }

                if (empty($image_url)) {
                    $image_url = wp_get_attachment_url($attachment_id);
                    if ($image_url && ($image_width === 200 || $image_height === 200)) {
                        $meta = wp_get_attachment_metadata($attachment_id);
                        if (!empty($meta['width']) && !empty($meta['height'])) {
                            $image_width = (int) $meta['width'];
                            $image_height = (int) $meta['height'];
                        }
                    }
                }

                if (!empty($image_url)) {
                    $term->imagem = $image_url;
                    $term->imagem_width = $image_width;
                    $term->imagem_height = $image_height;
                    $term->term_permalink = get_term_link($term);
                    $terms_with_images[] = $term;
                }
            }

            // Se já temos termos suficientes, parar
            if (count($terms_with_images) >= ($limit * 2)) {
                break;
            }
        }

        // Se não encontrou termos com imagem suficientes, retornar o que tem
        if (empty($terms_with_images)) {
            return array();
        }

        // Embaralhar os termos que têm imagem
        shuffle($terms_with_images);

        // Limitar ao número solicitado
        $selected_terms = array_slice($terms_with_images, 0, $limit);

        // Salvar os objetos completos no cache (incluindo a imagem) para evitar queries futuras
        wp_cache_set($cache_key, $selected_terms, $cache_group, $cache_expire);

        return $selected_terms;
    }
}
