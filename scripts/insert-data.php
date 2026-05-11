<?php
/**
 * Script para inserir dados das taxonomias a partir do JSON
 * 
 * IMPORTANTE: Execute este script apenas UMA VEZ através do painel do WordPress
 * ou via WP-CLI. Para executar via painel, adicione temporariamente no functions.php:
 * require_once get_template_directory() . '/scripts/insert-data.php';
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Incluir funções necessárias para upload de imagens
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

/**
 * Converte caminho relativo em URL absoluta baseada no domínio atual
 * 
 * @param string $path Caminho relativo (ex: "/src/imgs/componentes/bike.png")
 * @return string URL absoluta
 */
function bazar_normalize_asset_url($path) {
    if (empty($path)) {
        return '';
    }
    
    // Se já é uma URL absoluta (http:// ou https://), retornar como está
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }
    
    // Se começa com "/", é caminho relativo à raiz do site
    if (strpos($path, '/') === 0) {
        // Remover barra inicial
        $relative_path = ltrim($path, '/');
        
        // Verificar se o arquivo existe no diretório do tema
        $theme_path = get_template_directory() . '/' . $relative_path;
        if (file_exists($theme_path)) {
            // Retornar URL do tema
            return get_template_directory_uri() . '/' . $relative_path;
        }
        
        // Se não existe no tema, tentar na raiz do WordPress
        $wp_path = ABSPATH . $relative_path;
        if (file_exists($wp_path)) {
            // Retornar URL da raiz do site
            return site_url('/' . $relative_path);
        }
        
        // Se não encontrou, assumir que está no tema mesmo assim
        return get_template_directory_uri() . '/' . $relative_path;
    }
    
    // Se não começa com "/", assumir que é relativo ao tema
    $theme_path = get_template_directory() . '/' . $path;
    if (file_exists($theme_path)) {
        return get_template_directory_uri() . '/' . $path;
    }
    
    // Fallback: retornar como URL do tema
    return get_template_directory_uri() . '/' . $path;
}

/**
 * Faz upload de imagem a partir de URL ou caminho local e retorna o attachment ID
 * 
 * @param string $image_url URL da imagem ou caminho local
 * @param string $term_name Nome do termo (para nomear o arquivo)
 * @return int|false Attachment ID ou false em caso de erro
 */
function bazar_upload_image_from_url($image_url, $term_name = '') {
    if (empty($image_url)) {
        return false;
    }
    
    // Se é um caminho local do sistema de arquivos, converter para URL primeiro
    $local_path = null;
    $template_uri = get_template_directory_uri();
    $template_dir = get_template_directory();
    
    // Verificar se é caminho do tema
    if (strpos($image_url, $template_uri) === 0) {
        // Converter URL do tema para caminho local
        $relative_path = str_replace($template_uri, '', $image_url);
        $local_path = $template_dir . $relative_path;
    } elseif (strpos($image_url, site_url()) === 0) {
        // Converter URL do site para caminho local
        $relative_path = str_replace(site_url(), '', $image_url);
        $local_path = ABSPATH . ltrim($relative_path, '/');
    } elseif (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        // Se não é URL válida, pode ser caminho local
        if (file_exists($image_url)) {
            $local_path = $image_url;
        } else {
            error_log('[Bazar Insert Data] Caminho de imagem inválido: ' . $image_url);
            return false;
        }
    }
    
    // Se encontrou caminho local, fazer upload direto do arquivo
    if ($local_path && file_exists($local_path)) {
        $filename = basename($local_path);
        $file_array = array(
            'name' => sanitize_file_name($term_name . '-' . $filename),
            'tmp_name' => $local_path
        );
        
        // Copiar arquivo para local temporário (media_handle_sideload precisa de tmp_name)
        $tmp = get_temp_dir() . sanitize_file_name($term_name . '-' . $filename);
        if (copy($local_path, $tmp)) {
            $file_array['tmp_name'] = $tmp;
            $attachment_id = media_handle_sideload($file_array, 0);
            
            // Limpar arquivo temporário
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
            
            if (is_wp_error($attachment_id)) {
                error_log('[Bazar Insert Data] Erro ao fazer upload da imagem local: ' . $image_url . ' - ' . $attachment_id->get_error_message());
                return false;
            }
            
            return $attachment_id;
        } else {
            error_log('[Bazar Insert Data] Erro ao copiar arquivo local: ' . $local_path);
            return false;
        }
    }
    
    // Se não é arquivo local, validar URL
    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        error_log('[Bazar Insert Data] URL de imagem inválida: ' . $image_url);
        return false;
    }
    
    // Verificar se a imagem já foi importada (evitar duplicatas)
    // Buscar por attachment com mesmo nome de arquivo
    $filename = basename(parse_url($image_url, PHP_URL_PATH));
    $existing_attachment = get_posts(array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'meta_query' => array(
            array(
                'key' => '_wp_attached_file',
                'value' => $filename,
                'compare' => 'LIKE'
            )
        )
    ));
    
    if (!empty($existing_attachment)) {
        // Imagem já existe, retornar ID existente
        return $existing_attachment[0]->ID;
    }
    
    // Fazer download temporário da imagem
    $tmp = download_url($image_url);
    
    if (is_wp_error($tmp)) {
        error_log('[Bazar Insert Data] Erro ao baixar imagem: ' . $image_url . ' - ' . $tmp->get_error_message());
        return false;
    }
    
    // Preparar nome do arquivo
    $file_array = array(
        'name' => sanitize_file_name($term_name . '-' . $filename),
        'tmp_name' => $tmp
    );
    
    // Fazer upload para a biblioteca de mídia
    $attachment_id = media_handle_sideload($file_array, 0);
    
    // Limpar arquivo temporário se ainda existir
    if (file_exists($tmp)) {
        @unlink($tmp);
    }
    
    if (is_wp_error($attachment_id)) {
        error_log('[Bazar Insert Data] Erro ao fazer upload da imagem: ' . $image_url . ' - ' . $attachment_id->get_error_message());
        return false;
    }
    
    return $attachment_id;
}

/**
 * Salva as descrições do termo: padrão WordPress (curta) e SEO (longa).
 * - descricao: texto curto → campo padrão do WordPress (description). Prevalecer.
 * - descricao_seo: texto longo para SEO → ACF + term_meta.
 * Os dois conteúdos são independentes; não duplicar o mesmo texto nos dois campos.
 *
 * @param int    $term_id        ID do termo
 * @param string $taxonomy_slug  Slug da taxonomia
 * @param string $descricao      Descrição curta → WordPress description
 * @param string $descricao_seo  Descrição longa para SEO → ACF descricao_seo
 * @return bool true se salvo com sucesso
 */
function bazar_save_term_descriptions($term_id, $taxonomy_slug, $descricao = '', $descricao_seo = '') {
    $ok = true;

    // 1. Descrição padrão do WordPress (prevalecer)
    if ($descricao !== '') {
        $descricao = sanitize_textarea_field($descricao);
        $term_update = wp_update_term($term_id, $taxonomy_slug, array(
            'description' => $descricao
        ));
        if (is_wp_error($term_update)) {
            error_log('[Bazar Insert Data] Erro ao atualizar description do termo: ' . $term_update->get_error_message());
            $ok = false;
        }
    }

    // 2. descricao_seo (ACF + term_meta)
    if ($descricao_seo !== '') {
        $descricao_seo = sanitize_textarea_field($descricao_seo);
        if (function_exists('update_field')) {
            update_field('descricao_seo', $descricao_seo, $taxonomy_slug . '_' . $term_id);
        }
        update_term_meta($term_id, 'descricao_seo', $descricao_seo);
    }

    return $ok;
}

/**
 * Função para inserir dados das taxonomias a partir do JSON
 */
function bazar_insert_taxonomies_from_json($force = false) {
    $json_file = get_template_directory() . '/scripts/taxonomias.json';
    
    if (!file_exists($json_file)) {
        error_log('[Bazar Insert Data] Arquivo JSON não encontrado: ' . $json_file);
        return array(
            'success' => false,
            'message' => 'Arquivo JSON não encontrado: ' . $json_file
        );
    }
    
    $json_content = file_get_contents($json_file);
    $taxonomies_data = json_decode($json_content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Bazar Insert Data] Erro ao decodificar JSON: ' . json_last_error_msg());
        return array(
            'success' => false,
            'message' => 'Erro ao decodificar JSON: ' . json_last_error_msg()
        );
    }
    
    $stats = array(
        'taxonomies_processed' => 0,
        'terms_inserted' => 0,
        'terms_updated' => 0,
        'children_inserted' => 0,
        'images_uploaded' => 0,
        'errors' => 0
    );
    
    // Processar cada taxonomia do JSON
    foreach ($taxonomies_data as $taxonomy_slug => $terms_data) {
        if (!is_array($terms_data)) {
            continue;
        }
        
        $stats['taxonomies_processed']++;
        
        // Processar cada termo da taxonomia
        foreach ($terms_data as $term_data) {
            if (!isset($term_data['nome'])) {
                continue;
            }
            
            $term_name = trim($term_data['nome']);
            if (empty($term_name)) {
                continue;
            }
            
            // Verificar se o termo já existe
            $existing_term = get_term_by('name', $term_name, $taxonomy_slug);
            
            if ($existing_term) {
                // Termo já existe
                $term_id = $existing_term->term_id;
                
                // Se force=true e existe slug no JSON, atualizar o slug do termo
                if ($force && isset($term_data['slug']) && !empty(trim($term_data['slug']))) {
                    $new_slug = sanitize_title($term_data['slug']);
                    // Só atualizar se o slug for diferente
                    if ($existing_term->slug !== $new_slug) {
                        $update_result = wp_update_term($term_id, $taxonomy_slug, array(
                            'slug' => $new_slug
                        ));
                        if (is_wp_error($update_result)) {
                            error_log('[Bazar Insert Data] Erro ao atualizar slug do termo "' . $term_name . '": ' . $update_result->get_error_message());
                        }
                    }
                }
                
                if ($force) {
                    // Forçar atualização: atualizar metadados mesmo se o termo já existe
                    $stats['terms_updated']++;
                    $skip_metadata = false;
                } else {
                    // Não forçar: apenas contar como atualizado
                    $stats['terms_updated']++;
                    // Mas SEMPRE atualizar ícones, mesmo sem force
                    $skip_metadata = false;
                }
            } else {
                // Inserir novo termo
                // Preparar argumentos para wp_insert_term
                $term_args = array();
                
                // Se existe campo "slug" no JSON e está preenchido, usar ele
                if (isset($term_data['slug']) && !empty(trim($term_data['slug']))) {
                    $term_args['slug'] = sanitize_title($term_data['slug']);
                }
                // Caso contrário, deixar WordPress criar automaticamente
                
                // Inserir termo
                if (!empty($term_args)) {
                    $insert_result = wp_insert_term(
                        $term_name,
                        $taxonomy_slug,
                        $term_args
                    );
                } else {
                    $insert_result = wp_insert_term(
                        $term_name,
                        $taxonomy_slug
                    );
                }
                
                if (is_wp_error($insert_result)) {
                    error_log('[Bazar Insert Data] Erro ao inserir termo "' . $term_name . '" na taxonomia "' . $taxonomy_slug . '": ' . $insert_result->get_error_message());
                    $stats['errors']++;
                    continue;
                }
                
                $term_id = $insert_result['term_id'];
                $stats['terms_inserted']++;
                $skip_metadata = false;
            }
            
            // Ícone: SEMPRE atualizar se estiver presente no JSON, mesmo se o termo já existe
            if (isset($term_data['icone']) && !empty($term_data['icone'])) {
                // Normalizar caminho relativo para URL absoluta
                $icone_url = bazar_normalize_asset_url($term_data['icone']);
                update_term_meta($term_id, 'icone', esc_url_raw($icone_url));
                // Se ACF estiver disponível, também salvar via update_field
                if (function_exists('update_field')) {
                    update_field('icone', esc_url_raw($icone_url), $taxonomy_slug . '_' . $term_id);
                }
            }
            
            // Salvar outros metadados do termo (sempre se for novo, ou se force=true)
            if (!isset($skip_metadata) || !$skip_metadata || $force) {
                if (isset($term_data['default_bicicletas'])) {
                    update_term_meta($term_id, 'default_bicicletas', (bool) $term_data['default_bicicletas']);
                    // Se ACF estiver disponível, também salvar via update_field
                    if (function_exists('update_field')) {
                        update_field('default_bicicletas', (bool) $term_data['default_bicicletas'], 'componente_' . $term_id);
                    }
                }
                
                // Descrição padrão WordPress (até 90 chars) e descricao_seo (até 1500 chars) — dados independentes
                $descricao     = isset($term_data['descricao']) ? trim((string) $term_data['descricao']) : '';
                $descricao_seo = isset($term_data['descricao_seo']) ? trim((string) $term_data['descricao_seo']) : '';
                if ($descricao !== '' || $descricao_seo !== '') {
                    bazar_save_term_descriptions($term_id, $taxonomy_slug, $descricao, $descricao_seo);
                }
                
                // Salvar imagem (fazer upload se for URL externa)
                if (isset($term_data['imagem']) && !empty($term_data['imagem'])) {
                    // Normalizar caminho relativo para URL absoluta
                    $imagem_url = bazar_normalize_asset_url($term_data['imagem']);
                    $imagem_url = esc_url_raw($imagem_url);
                    
                    // Verificar se é URL local (mesmo domínio) ou externa
                    $site_host = parse_url(site_url(), PHP_URL_HOST);
                    $image_host = parse_url($imagem_url, PHP_URL_HOST);
                    $is_local = ($site_host === $image_host || empty($image_host));
                    
                    // Também verificar se é caminho do tema ou site
                    $template_uri = get_template_directory_uri();
                    $is_template = (strpos($imagem_url, $template_uri) === 0);
                    $is_site = (strpos($imagem_url, site_url()) === 0);
                    
                    if ($is_local || $is_template || $is_site) {
                        // URL local - salvar como está
                        update_term_meta($term_id, 'imagem', $imagem_url);
                        if (function_exists('update_field')) {
                            update_field('imagem', $imagem_url, $taxonomy_slug . '_' . $term_id);
                        }
                    } else {
                        // URL externa - fazer upload
                        $attachment_id = bazar_upload_image_from_url($imagem_url, $term_name);
                        if ($attachment_id) {
                            // Salvar attachment ID ao invés da URL
                            update_term_meta($term_id, 'imagem', $attachment_id);
                            if (function_exists('update_field')) {
                                update_field('imagem', $attachment_id, $taxonomy_slug . '_' . $term_id);
                            }
                            $stats['images_uploaded'] = isset($stats['images_uploaded']) ? $stats['images_uploaded'] + 1 : 1;
                        } else {
                            // Se upload falhou, salvar URL como fallback
                            update_term_meta($term_id, 'imagem', $imagem_url);
                            if (function_exists('update_field')) {
                                update_field('imagem', $imagem_url, $taxonomy_slug . '_' . $term_id);
                            }
                        }
                    }
                }
                
                // Salvar descricao_tecnica
                if (isset($term_data['descricao_tecnica']) && !empty($term_data['descricao_tecnica'])) {
                    update_term_meta($term_id, 'descricao_tecnica', sanitize_textarea_field($term_data['descricao_tecnica']));
                    // Se ACF estiver disponível, também salvar via update_field
                    if (function_exists('update_field')) {
                        update_field('descricao_tecnica', sanitize_textarea_field($term_data['descricao_tecnica']), $taxonomy_slug . '_' . $term_id);
                    }
                }
                
                // FAQ agora é processado separadamente através do script insert-faqs.php
                
                // Salvar ordem_componente (campo específico para componentes)
                if (isset($term_data['ordem_componente'])) {
                    $ordem = intval($term_data['ordem_componente']);
                    // Salvar como term meta
                    update_term_meta($term_id, 'ordem_componente', $ordem);
                    // Se ACF estiver disponível, também salvar via update_field
                    if (function_exists('update_field')) {
                        update_field('ordem_componente', $ordem, 'componente_' . $term_id);
                    }
                }
                
                // Salvar ordem (campo genérico para todas as taxonomias)
                if (isset($term_data['ordem'])) {
                    $ordem = intval($term_data['ordem']);
                    // Salvar como term meta
                    update_term_meta($term_id, 'ordem', $ordem);
                    // Se ACF estiver disponível, também salvar via update_field
                    if (function_exists('update_field')) {
                        update_field('ordem', $ordem, $taxonomy_slug . '_' . $term_id);
                    }
                }
            }
            
            // Processar filhos (termos filhos) - sempre processar, mesmo se o termo pai já existe
            if (isset($term_data['filhos']) && is_array($term_data['filhos']) && !empty($term_data['filhos'])) {
                foreach ($term_data['filhos'] as $filho_data) {
                    if (!isset($filho_data['nome'])) {
                        continue;
                    }
                    
                    $filho_name = trim($filho_data['nome']);
                    if (empty($filho_name)) {
                        continue;
                    }
                    
                    // JSON é a única fonte de verdade - permitir termos "duplicados" com mesmo nome mas pais diferentes
                    // Verificar se já existe um termo com este nome que seja filho do pai atual
                    $filho_id = null;
                    $children_of_parent = get_term_children($term_id, $taxonomy_slug);
                    
                    if (!empty($children_of_parent) && !is_wp_error($children_of_parent)) {
                        foreach ($children_of_parent as $child_id) {
                            $child_term = get_term($child_id, $taxonomy_slug);
                            if ($child_term && !is_wp_error($child_term) && strcasecmp(trim($child_term->name), $filho_name) === 0) {
                                $filho_id = $child_term->term_id;
                                break;
                            }
                        }
                    }
                    
                    if (!$filho_id) {
                        // Não existe como filho do pai atual - criar novo termo (mesmo que já exista com outro pai)
                        // Preparar argumentos para wp_insert_term
                        $filho_args = array(
                            'parent' => $term_id
                        );
                        
                        // Se existe campo "slug" no JSON e está preenchido, usar ele
                        if (isset($filho_data['slug']) && !empty(trim($filho_data['slug']))) {
                            $filho_args['slug'] = sanitize_title($filho_data['slug']);
                        }
                        // Caso contrário, deixar WordPress criar automaticamente (não passar slug)
                        
                        // Tentar inserir termo
                        if (!empty($filho_args['slug'])) {
                            // Se tem slug definido, usar ele
                            $insert_filho_result = wp_insert_term(
                                $filho_name,
                                $taxonomy_slug,
                                $filho_args
                            );
                        } else {
                            // Se não tem slug, deixar WordPress criar automaticamente
                            $insert_filho_result = wp_insert_term(
                                $filho_name,
                                $taxonomy_slug,
                                array('parent' => $term_id)
                            );
                        }
                        
                        if (is_wp_error($insert_filho_result)) {
                            // Se der erro (slug duplicado mesmo com parent), tentar com timestamp apenas se não foi slug do JSON
                            if (isset($filho_data['slug']) && !empty(trim($filho_data['slug']))) {
                                // Se foi slug do JSON e deu erro, tentar com timestamp
                                $unique_slug = sanitize_title($filho_data['slug']) . '-' . time();
                            } else {
                                // Se não tinha slug e deu erro, tentar com timestamp no nome sanitizado
                                $unique_slug = sanitize_title($filho_name) . '-' . time();
                            }
                            
                            $insert_filho_result = wp_insert_term(
                                $filho_name,
                                $taxonomy_slug,
                                array(
                                    'parent' => $term_id,
                                    'slug' => $unique_slug
                                )
                            );
                            
                            if (is_wp_error($insert_filho_result)) {
                                error_log('[Bazar Insert Data] Erro ao inserir filho "' . $filho_name . '" na taxonomia "' . $taxonomy_slug . '": ' . $insert_filho_result->get_error_message());
                                $stats['errors']++;
                                continue;
                            }
                        }
                        
                        $filho_id = $insert_filho_result['term_id'];
                        $stats['children_inserted']++;
                        $skip_filho_metadata = false;
                    } else {
                        // Já existe como filho do pai atual - apenas atualizar metadados se necessário
                        $skip_filho_metadata = !$force;
                    }
                    
                    // Salvar metadados do filho (sempre se for novo, ou se force=true)
                    // Garantir que temos um filho_id válido antes de salvar metadados
                    if ($filho_id && (!isset($skip_filho_metadata) || !$skip_filho_metadata || $force)) {
                        if (isset($filho_data['icone']) && !empty($filho_data['icone'])) {
                            // Normalizar caminho relativo para URL absoluta
                            $icone_url = bazar_normalize_asset_url($filho_data['icone']);
                            update_term_meta($filho_id, 'icone', esc_url_raw($icone_url));
                        }
                        
                        // Descrição padrão WordPress e descricao_seo também para filhos
                        $descricao_filho     = isset($filho_data['descricao']) ? trim((string) $filho_data['descricao']) : '';
                        $descricao_seo_filho = isset($filho_data['descricao_seo']) ? trim((string) $filho_data['descricao_seo']) : '';
                        if ($descricao_filho !== '' || $descricao_seo_filho !== '') {
                            bazar_save_term_descriptions($filho_id, $taxonomy_slug, $descricao_filho, $descricao_seo_filho);
                        }
                        
                        // Salvar imagem também para filhos (fazer upload se for URL externa)
                        if (isset($filho_data['imagem']) && !empty($filho_data['imagem'])) {
                            // Normalizar caminho relativo para URL absoluta
                            $imagem_url = bazar_normalize_asset_url($filho_data['imagem']);
                            $imagem_url = esc_url_raw($imagem_url);
                            
                            // Verificar se é URL local (mesmo domínio) ou externa
                            $site_host = parse_url(site_url(), PHP_URL_HOST);
                            $image_host = parse_url($imagem_url, PHP_URL_HOST);
                            $is_local = ($site_host === $image_host || empty($image_host));
                            
                            // Também verificar se é caminho do tema ou site
                            $template_uri = get_template_directory_uri();
                            $is_template = (strpos($imagem_url, $template_uri) === 0);
                            $is_site = (strpos($imagem_url, site_url()) === 0);
                            
                            if ($is_local || $is_template || $is_site) {
                                // URL local - salvar como está
                                update_term_meta($filho_id, 'imagem', $imagem_url);
                                if (function_exists('update_field')) {
                                    update_field('imagem', $imagem_url, $taxonomy_slug . '_' . $filho_id);
                                }
                            } else {
                                // URL externa - fazer upload
                                $attachment_id = bazar_upload_image_from_url($imagem_url, $filho_name);
                                if ($attachment_id) {
                                    // Salvar attachment ID ao invés da URL
                                    update_term_meta($filho_id, 'imagem', $attachment_id);
                                    if (function_exists('update_field')) {
                                        update_field('imagem', $attachment_id, $taxonomy_slug . '_' . $filho_id);
                                    }
                                    $stats['images_uploaded'] = isset($stats['images_uploaded']) ? $stats['images_uploaded'] + 1 : 1;
                                } else {
                                    // Se upload falhou, salvar URL como fallback
                                    update_term_meta($filho_id, 'imagem', $imagem_url);
                                    if (function_exists('update_field')) {
                                        update_field('imagem', $imagem_url, $taxonomy_slug . '_' . $filho_id);
                                    }
                                }
                            }
                        }
                        
                        // Salvar descricao_tecnica também para filhos (se especificado)
                        if (isset($filho_data['descricao_tecnica']) && !empty($filho_data['descricao_tecnica'])) {
                            update_term_meta($filho_id, 'descricao_tecnica', sanitize_textarea_field($filho_data['descricao_tecnica']));
                            // Se ACF estiver disponível, também salvar via update_field
                            if (function_exists('update_field')) {
                                update_field('descricao_tecnica', sanitize_textarea_field($filho_data['descricao_tecnica']), $taxonomy_slug . '_' . $filho_id);
                            }
                        }
                        
                        // Salvar ordem_componente também para filhos (se especificado)
                        if (isset($filho_data['ordem_componente'])) {
                            $ordem = intval($filho_data['ordem_componente']);
                            update_term_meta($filho_id, 'ordem_componente', $ordem);
                            // Se ACF estiver disponível, também salvar via update_field
                            if (function_exists('update_field')) {
                                update_field('ordem_componente', $ordem, 'componente_' . $filho_id);
                            }
                        }
                        
                        // Salvar ordem também para filhos (se especificado)
                        if (isset($filho_data['ordem'])) {
                            $ordem = intval($filho_data['ordem']);
                            update_term_meta($filho_id, 'ordem', $ordem);
                            // Se ACF estiver disponível, também salvar via update_field
                            if (function_exists('update_field')) {
                                update_field('ordem', $ordem, $taxonomy_slug . '_' . $filho_id);
                            }
                        }
                    } elseif (!$filho_id) {
                        error_log('[Bazar Insert Data] AVISO: Não foi possível obter filho_id para "' . $filho_name . '" na taxonomia "' . $taxonomy_slug . '"');
                    }
                }
            }
        }
    }
    
    return array(
        'success' => true,
        'message' => 'Taxonomias inseridas com sucesso!',
        'stats' => $stats
    );
}

/**
 * Função para executar a inserção de taxonomias
 * Apenas controla a inserção de termos nas taxonomias a partir do JSON
 */
function bazar_run_insert_taxonomies($force = false) {
    // Verificar se já foi executado (evitar executar múltiplas vezes, a menos que force=true)
    if (!$force && get_option('bazar_taxonomies_inserted')) {
        // Retornar array válido com informações de que já foi executado
        $stats = get_option('bazar_taxonomies_inserted_stats', array());
        return array(
            'success' => true,
            'message' => 'As taxonomias já foram inseridas anteriormente. Marque "Forçar atualização" para executar novamente.',
            'stats' => $stats
        );
    }
    
    // Inserir taxonomias do JSON
    $result_taxonomies = bazar_insert_taxonomies_from_json($force);
    
    // Verificar se o resultado é válido
    if (!is_array($result_taxonomies) || !isset($result_taxonomies['success'])) {
        return array(
            'success' => false,
            'message' => 'Erro: A função de inserção não retornou um resultado válido.',
            'stats' => array()
        );
    }
    
    if ($result_taxonomies['success']) {
        if (isset($result_taxonomies['stats'])) {
            error_log('[Bazar Insert Taxonomies] Taxonomias inseridas: ' . 
                $result_taxonomies['stats']['terms_inserted'] . ' termos inseridos, ' . 
                $result_taxonomies['stats']['children_inserted'] . ' filhos inseridos.');
        }
    } else {
        error_log('[Bazar Insert Taxonomies] Erro ao inserir taxonomias: ' . $result_taxonomies['message']);
    }
    
    // Marcar como executado
    update_option('bazar_taxonomies_inserted', true);
    update_option('bazar_taxonomies_inserted_date', current_time('mysql'));
    update_option('bazar_taxonomies_inserted_stats', $result_taxonomies['stats'] ?? array());
    
    error_log('[Bazar Insert Taxonomies] Inserção de taxonomias concluída.');
    
    return $result_taxonomies;
}


// Adicionar página no menu admin para executar o script
function bazar_add_insert_data_menu() {
    add_management_page(
        'Inserir Taxonomias',
        'Inserir Taxonomias',
        'manage_options',
        'bazar-insert-taxonomies',
        'bazar_insert_taxonomies_page'
    );
}
add_action('admin_menu', 'bazar_add_insert_data_menu');

// Página de administração para inserir taxonomias
function bazar_insert_taxonomies_page() {
    // Executar inserção de taxonomias do JSON
    if (isset($_POST['bazar_insert_taxonomies']) && check_admin_referer('bazar_insert_taxonomies_action')) {
        $force = isset($_POST['force_taxonomies']) && $_POST['force_taxonomies'] == '1';
        
        // Limpar flag se forçar
        if ($force) {
            delete_option('bazar_taxonomies_inserted');
        }
        
        $result = bazar_run_insert_taxonomies($force);
        
        // Verificar se $result é válido antes de acessar
        if (!is_array($result) || !isset($result['success'])) {
            $result = array(
                'success' => false,
                'message' => 'Erro: A função não retornou um resultado válido.',
                'stats' => array()
            );
        }
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Estatísticas:</strong><br>';
            echo 'Taxonomias processadas: ' . $stats['taxonomies_processed'] . '<br>';
            echo 'Termos inseridos: ' . $stats['terms_inserted'] . '<br>';
            echo 'Termos atualizados: ' . $stats['terms_updated'] . '<br>';
            echo 'Filhos inseridos: ' . $stats['children_inserted'] . '<br>';
            if (isset($stats['images_uploaded']) && $stats['images_uploaded'] > 0) {
                echo 'Imagens importadas: ' . $stats['images_uploaded'] . '<br>';
            }
            if ($stats['errors'] > 0) {
                echo 'Erros: ' . $stats['errors'] . '<br>';
            }
            echo '</p></div>';
        }
    }
    
    $taxonomies_inserted = get_option('bazar_taxonomies_inserted');
    $taxonomies_date = get_option('bazar_taxonomies_inserted_date');
    $taxonomies_stats = get_option('bazar_taxonomies_inserted_stats');
    ?>
    <div class="wrap">
        <h1>Inserir Taxonomias do JSON</h1>
        
        <?php if ($taxonomies_inserted): ?>
            <div class="notice notice-info">
                <p><strong>Status:</strong> As taxonomias já foram inseridas.</p>
                <?php if ($taxonomies_date): ?>
                    <p><strong>Data:</strong> <?php echo esc_html($taxonomies_date); ?></p>
                <?php endif; ?>
                <?php if ($taxonomies_stats): ?>
                    <p><strong>Resultados da última execução:</strong></p>
                    <ul>
                        <li>Taxonomias processadas: <?php echo $taxonomies_stats['taxonomies_processed']; ?></li>
                    <li>Termos inseridos: <?php echo $taxonomies_stats['terms_inserted']; ?></li>
                    <li>Termos atualizados: <?php echo $taxonomies_stats['terms_updated']; ?></li>
                    <li>Filhos inseridos: <?php echo $taxonomies_stats['children_inserted']; ?></li>
                    <?php if (isset($taxonomies_stats['images_uploaded']) && $taxonomies_stats['images_uploaded'] > 0): ?>
                    <li>Imagens importadas: <?php echo $taxonomies_stats['images_uploaded']; ?></li>
                    <?php endif; ?>
                    </ul>
                <?php endif; ?>
                <p>Use a opção "Forçar atualização" abaixo para atualizar termos existentes e metadados.</p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p><strong>Status:</strong> As taxonomias ainda não foram inseridas. Execute manualmente através do botão abaixo.</p>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>O que este script faz?</h2>
            <p>Este script insere todas as taxonomias do arquivo <code>taxonomias.json</code> no WordPress.</p>
            
            <h3>Taxonomias inseridas:</h3>
            <ul>
                <li><strong>Taxonomias:</strong> category, cor, negociacao, material, conservacao, genero, idade, modalidade, componente, especificacoes, acessorio, medidas</li>
                <li><strong>Recursos:</strong> Termos hierárquicos (pais e filhos), metadados (ícones, descrições, imagens, descrições técnicas), atualização automática de termos existentes</li>
                <li><strong>Upload de Imagens:</strong> URLs externas de imagens são automaticamente importadas para a biblioteca de mídia do WordPress</li>
                <li><strong>FAQs:</strong> Use o menu separado "Inserir FAQs" para processar FAQs do arquivo <code>taxonomias-faqs.json</code></li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Lê o arquivo <code>taxonomias.json</code> do diretório de scripts</li>
                <li>Processa cada taxonomia e seus termos</li>
                <li>Insere termos novos ou atualiza termos existentes</li>
                <li>Processa termos filhos (hierarquia)</li>
                <li>Salva metadados (ícones, descrições, imagens, descrições técnicas)</li>
                <li><strong>Upload de Imagens:</strong> URLs externas são baixadas e importadas para a biblioteca de mídia (evita duplicatas)</li>
                <li><strong>Nota:</strong> FAQs são processados separadamente através do menu "Inserir FAQs"</li>
            </ol>
            
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('bazar_insert_taxonomies_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force_taxonomies" value="1">
                        Forçar atualização (executar mesmo se já foi executado anteriormente e atualizar termos existentes)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_insert_taxonomies" class="button button-primary button-large">
                        Inserir Taxonomias do JSON
                    </button>
                </p>
            </form>
        </div>
    </div>
    <?php
}
