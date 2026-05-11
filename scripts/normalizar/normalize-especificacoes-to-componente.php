<?php
/**
 * Script para normalizar taxonomia 'especificacoes' para taxonomia 'componente'
 * 
 * Migra termos da taxonomia antiga "especificacoes" (Aro, Freio, Quadro, etc.)
 * para a taxonomia "componente"
 * 
 * IMPORTANTE: Execute este script apenas UMA VEZ através do painel do WordPress
 * ou via WP-CLI. Para executar via painel, adicione temporariamente no functions.php:
 * require_once get_template_directory() . '/scripts/normalize-especificacoes-to-componente.php';
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mapeamento de especificacoes para componente
 * Apenas termos com count > 0 e apenas PAIS
 */
function bazar_get_especificacoes_to_componente_map() {
    return array(
        'Aro' => 'Aro',
        'Freio' => 'Freio',
        'Quadro' => 'Quadro',
        'Trocador' => 'Trocador',
        //'Valvula' => 'Valvula',
        'Links' => 'Corrente',
        'Relação' => 'Cassete',
        // 'Velocidade' => IGNORADO (conforme solicitado)
    );
}

/**
 * Função principal para normalizar especificacoes para componente
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_especificacoes_to_componente($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_especificacoes_to_componente_normalized')) {
        return array(
            'success' => true,
            'message' => 'A normalização de especificações para componente já foi executada anteriormente. Marque a opção "Forçar execução" para executar novamente.',
            'stats' => get_option('bazar_especificacoes_to_componente_stats', array())
        );
    }
    
    $stats = array(
        'especificacoes_found' => 0,
        'especificacoes_processed' => 0,
        'componente_terms_created' => 0,
        'componente_terms_existing' => 0,
        'posts_updated' => 0,
        'posts_processed' => 0,
        'errors' => 0,
        'skipped' => 0,
        'details' => array()
    );
    
    // Obter mapeamento
    $mapping = bazar_get_especificacoes_to_componente_map();
    
    // Buscar todos os termos PAIS da taxonomia "especificacoes" com count > 0
    $especificacoes_terms = get_terms(array(
        'taxonomy' => 'especificacoes',
        'parent' => 0, // Apenas termos pais
        'hide_empty' => false
    ));
    
    if (is_wp_error($especificacoes_terms) || empty($especificacoes_terms)) {
        error_log('[Bazar Normalize Especificacoes] Aviso: Nenhum termo encontrado na taxonomia "especificacoes"');
        return array(
            'success' => true,
            'message' => 'Nenhum termo encontrado na taxonomia "especificacoes". Nada a migrar.',
            'stats' => $stats
        );
    }
    
    // Filtrar apenas termos com count > 0 e que estão no mapeamento
    $terms_to_process = array();
    foreach ($especificacoes_terms as $term) {
        // Verificar se tem count > 0 (usando get_term_meta ou contando posts)
        $term_count = $term->count;
        
        // Verificar se está no mapeamento
        if (isset($mapping[$term->name]) && $term_count > 0) {
            $terms_to_process[] = $term;
        }
    }
    
    if (empty($terms_to_process)) {
        error_log('[Bazar Normalize Especificacoes] Aviso: Nenhum termo com count > 0 encontrado no mapeamento');
        return array(
            'success' => true,
            'message' => 'Nenhum termo com count > 0 encontrado no mapeamento. Nada a migrar.',
            'stats' => $stats
        );
    }
    
    $stats['especificacoes_found'] = count($terms_to_process);
    error_log('[Bazar Normalize Especificacoes] Encontrados ' . count($terms_to_process) . ' termos para processar');
    
    // Processar cada termo de especificacoes (PAIS e FILHOS)
    foreach ($terms_to_process as $especificacao_term) {
        $especificacao_name = $especificacao_term->name;
        $especificacao_id = $especificacao_term->term_id;
        $especificacao_count = $especificacao_term->count;
        
        // Verificar se está no mapeamento
        if (!isset($mapping[$especificacao_name])) {
            $stats['skipped']++;
            error_log('[Bazar Normalize Especificacoes] Termo "' . $especificacao_name . '" não está no mapeamento. Pulando.');
            continue;
        }
        
        $componente_name = $mapping[$especificacao_name];
        
        error_log('[Bazar Normalize Especificacoes] Processando PAIS: "' . $especificacao_name . '" (ID: ' . $especificacao_id . ', Count: ' . $especificacao_count . ') → "' . $componente_name . '"');
        $stats['especificacoes_processed']++;
        
        // Buscar ou criar termo PAIS na taxonomia "componente"
        $componente_term = get_term_by('name', $componente_name, 'componente');
        
        if (!$componente_term || is_wp_error($componente_term)) {
            // Criar novo termo na taxonomia "componente"
            $insert_result = wp_insert_term(
                $componente_name,
                'componente',
                array(
                    'slug' => sanitize_title($componente_name)
                )
            );
            
            if (is_wp_error($insert_result)) {
                $stats['errors']++;
                error_log('[Bazar Normalize Especificacoes] Erro ao criar termo "' . $componente_name . '" na taxonomia "componente": ' . $insert_result->get_error_message());
                $stats['details'][] = array(
                    'especificacao_name' => $especificacao_name,
                    'especificacao_id' => $especificacao_id,
                    'componente_name' => $componente_name,
                    'error' => 'Erro ao criar termo: ' . $insert_result->get_error_message()
                );
                continue;
            }
            
            $componente_term_id = $insert_result['term_id'];
            $stats['componente_terms_created']++;
            error_log('[Bazar Normalize Especificacoes] Termo criado na taxonomia "componente": "' . $componente_name . '" (ID: ' . $componente_term_id . ')');
        } else {
            $componente_term_id = $componente_term->term_id;
            $stats['componente_terms_existing']++;
            error_log('[Bazar Normalize Especificacoes] Termo já existe na taxonomia "componente": "' . $componente_name . '" (ID: ' . $componente_term_id . ')');
        }
        
        // Buscar FILHOS da especificação para migrar também
        $especificacao_children = get_terms(array(
            'taxonomy' => 'especificacoes',
            'parent' => $especificacao_id,
            'hide_empty' => false
        ));
        
        // Criar mapeamento de filhos: especificacao_child_id => componente_child_id
        $children_mapping = array();
        
        if (!is_wp_error($especificacao_children) && !empty($especificacao_children)) {
            error_log('[Bazar Normalize Especificacoes] Encontrados ' . count($especificacao_children) . ' filhos de "' . $especificacao_name . '"');
            
            foreach ($especificacao_children as $especificacao_child) {
                $child_name = $especificacao_child->name;
                $child_id = $especificacao_child->term_id;
                $child_count = $especificacao_child->count;
                
                // Buscar ou criar filho correspondente em componente
                $componente_child = get_term_by('name', $child_name, 'componente');
                
                if (!$componente_child || is_wp_error($componente_child)) {
                    // Criar filho em componente
                    $insert_child_result = wp_insert_term(
                        $child_name,
                        'componente',
                        array(
                            'slug' => sanitize_title($child_name),
                            'parent' => $componente_term_id
                        )
                    );
                    
                    if (is_wp_error($insert_child_result)) {
                        error_log('[Bazar Normalize Especificacoes] Erro ao criar filho "' . $child_name . '" em componente: ' . $insert_child_result->get_error_message());
                        continue;
                    }
                    
                    $componente_child_id = $insert_child_result['term_id'];
                    $stats['componente_terms_created']++;
                    error_log('[Bazar Normalize Especificacoes] Filho criado: "' . $child_name . '" (ID: ' . $componente_child_id . ') como filho de "' . $componente_name . '"');
                } else {
                    $componente_child_id = $componente_child->term_id;
                    
                    // Se o filho já existe mas não tem o pai correto, atualizar
                    if ($componente_child->parent != $componente_term_id) {
                        wp_update_term($componente_child_id, 'componente', array('parent' => $componente_term_id));
                        error_log('[Bazar Normalize Especificacoes] Filho "' . $child_name . '" atualizado para ter pai "' . $componente_name . '"');
                    }
                    
                    $stats['componente_terms_existing']++;
                    error_log('[Bazar Normalize Especificacoes] Filho já existe: "' . $child_name . '" (ID: ' . $componente_child_id . ')');
                }
                
                $children_mapping[$child_id] = $componente_child_id;
            }
        }
        
        // Buscar todos os posts que têm essa especificação (PAIS ou FILHOS)
        $all_term_ids = array($especificacao_id);
        if (!empty($children_mapping)) {
            $all_term_ids = array_merge($all_term_ids, array_keys($children_mapping));
        }
        
        $posts = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'tax_query' => array(
                array(
                    'taxonomy' => 'especificacoes',
                    'field' => 'term_id',
                    'terms' => $all_term_ids,
                    'operator' => 'IN'
                )
            )
        ));
        
        if (empty($posts)) {
            error_log('[Bazar Normalize Especificacoes] Nenhum post encontrado com a especificação "' . $especificacao_name . '"');
            continue;
        }
        
        error_log('[Bazar Normalize Especificacoes] Encontrados ' . count($posts) . ' posts com a especificação "' . $especificacao_name . '"');
        
        $posts_updated_for_term = 0;
        $post_details = array();
        
        // Processar cada post
        foreach ($posts as $post) {
            $post_id = $post->ID;
            $stats['posts_processed']++;
            
            // Obter termos de especificacoes do post
            $post_especificacoes = wp_get_object_terms($post_id, 'especificacoes', array('fields' => 'ids'));
            
            if (is_wp_error($post_especificacoes)) {
                continue;
            }
            
            // Determinar quais componentes associar (PAIS e/ou FILHOS)
            $componentes_to_add = array();
            
            // Verificar se o post tem o termo PAIS
            if (in_array($especificacao_id, $post_especificacoes)) {
                $componentes_to_add[] = $componente_term_id;
            }
            
            // Verificar se o post tem algum FILHO e adicionar o componente filho correspondente
            foreach ($children_mapping as $espec_child_id => $comp_child_id) {
                if (in_array($espec_child_id, $post_especificacoes)) {
                    $componentes_to_add[] = $comp_child_id;
                }
            }
            
            if (empty($componentes_to_add)) {
                continue;
            }
            
            // Obter componentes atuais do post
            $existing_componente = wp_get_object_terms($post_id, 'componente', array('fields' => 'ids'));
            if (is_wp_error($existing_componente)) {
                $existing_componente = array();
            }
            
            // Adicionar apenas componentes que ainda não estão associados
            $componentes_to_add = array_diff($componentes_to_add, $existing_componente);
            
            if (empty($componentes_to_add)) {
                continue;
            }
            
            // Adicionar termos de componente ao post
            $set_result = wp_set_object_terms($post_id, $componentes_to_add, 'componente', true);
            
            if (is_wp_error($set_result)) {
                $stats['errors']++;
                error_log('[Bazar Normalize Especificacoes] Erro ao associar termo de componente ao post ID ' . $post_id . ': ' . $set_result->get_error_message());
                $post_details[] = array(
                    'post_id' => $post_id,
                    'error' => 'Erro ao associar componente: ' . $set_result->get_error_message()
                );
                continue;
            }
            
            $posts_updated_for_term++;
            $stats['posts_updated']++;
        }
        
        // Adicionar detalhes para esta especificação
        $stats['details'][] = array(
            'especificacao_name' => $especificacao_name,
            'especificacao_id' => $especificacao_id,
            'componente_name' => $componente_name,
            'componente_term_id' => $componente_term_id,
            'children_migrated' => count($children_mapping),
            'posts_updated' => $posts_updated_for_term,
            'posts_found' => count($posts),
            'post_details' => $post_details
        );
    }
    
    // Criar mapeamento de redirecionamento (slug_especificacoes => slug_componente)
    $redirect_map = array();
    foreach ($terms_to_process as $especificacao_term) {
        $especificacao_name = $especificacao_term->name;
        if (isset($mapping[$especificacao_name])) {
            $componente_name = $mapping[$especificacao_name];
            $componente_term = get_term_by('name', $componente_name, 'componente');
            if ($componente_term && !is_wp_error($componente_term)) {
                $redirect_map[$especificacao_term->slug] = $componente_term->slug;
            }
        }
    }
    
    // Salvar mapeamento com autoload para cache em memória
    update_option('bazar_especificacoes_to_componente_redirect_map', $redirect_map, true);
    
    // Marcar como executado
    update_option('bazar_especificacoes_to_componente_normalized', true);
    update_option('bazar_especificacoes_to_componente_normalized_date', current_time('mysql'));
    update_option('bazar_especificacoes_to_componente_stats', $stats);
    
    return array(
        'success' => true,
        'message' => 'Normalização de especificações para componente concluída com sucesso!',
        'stats' => $stats
    );
}

/**
 * Mapeamento de category->peca para componente
 * Apenas termos PAIS (não filhos)
 */
function bazar_get_category_peca_to_componente_map() {
    return array(
        'Kit/Grupo' => null, // Ignorar (sem correspondência direta)
        'Cubo' => null, // Ignorar (pode ser Cubo Dianteiro ou Cubo Traseiro)
        'Freios' => 'Freio',
        'Pedal' => 'Pedal',
        'Câmbio Traseiro' => 'Câmbio Traseiro',
        'Coroa' => 'Coroa',
        'Passador Trigger' => 'Trocador',
        'Corrente' => 'Corrente',
        'Guidão' => 'Guidão',
        'Canote' => 'Canote',
        'Banco/Selim' => 'Selim',
        'Quadro' => 'Quadro',
        'Freio Dianteiro' => 'Freio',
        'Disco de Freio' => 'Freio',
        'Roda' => 'Roda',
        'Velocidade' => 'Câmbio Traseiro',
    );
}

/**
 * Função para normalizar category->peca para componente
 * Migra apenas termos PAIS (não filhos)
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_category_peca_to_componente($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_category_peca_to_componente_normalized')) {
        return array(
            'success' => true,
            'message' => 'A normalização de category->peca para componente já foi executada anteriormente. Marque a opção "Forçar execução" para executar novamente.',
            'stats' => get_option('bazar_category_peca_to_componente_stats', array())
        );
    }
    
    $stats = array(
        'categories_found' => 0,
        'categories_processed' => 0,
        'componente_terms_created' => 0,
        'componente_terms_existing' => 0,
        'posts_updated' => 0,
        'posts_processed' => 0,
        'errors' => 0,
        'skipped' => 0,
        'details' => array()
    );
    
    // Obter mapeamento
    $mapping = bazar_get_category_peca_to_componente_map();
    
    // Buscar o termo "Peça" na taxonomia "category"
    $peca_term = get_term_by('name', 'Peça', 'category');
    
    if (!$peca_term || is_wp_error($peca_term)) {
        error_log('[Bazar Normalize Category Peca] Erro: Termo "Peça" não encontrado na taxonomia "category"');
        return array(
            'success' => false,
            'message' => 'Erro: Termo "Peça" não encontrado na taxonomia "category"',
            'stats' => $stats
        );
    }
    
    // Buscar todos os FILHOS de "Peça" (apenas PAIS, não netos)
    $peca_children = get_terms(array(
        'taxonomy' => 'category',
        'parent' => $peca_term->term_id,
        'hide_empty' => false
    ));
    
    if (is_wp_error($peca_children) || empty($peca_children)) {
        error_log('[Bazar Normalize Category Peca] Aviso: Nenhum filho encontrado para "Peça"');
        return array(
            'success' => true,
            'message' => 'Nenhum filho encontrado para a categoria "Peça". Nada a migrar.',
            'stats' => $stats
        );
    }
    
    // Filtrar apenas termos que estão no mapeamento e não são null
    $terms_to_process = array();
    foreach ($peca_children as $term) {
        if (isset($mapping[$term->name]) && $mapping[$term->name] !== null) {
            $terms_to_process[] = $term;
        }
    }
    
    if (empty($terms_to_process)) {
        error_log('[Bazar Normalize Category Peca] Aviso: Nenhum termo encontrado no mapeamento');
        return array(
            'success' => true,
            'message' => 'Nenhum termo encontrado no mapeamento. Nada a migrar.',
            'stats' => $stats
        );
    }
    
    $stats['categories_found'] = count($terms_to_process);
    error_log('[Bazar Normalize Category Peca] Encontrados ' . count($terms_to_process) . ' termos para processar');
    
    // Processar cada categoria filha (apenas PAIS, não filhos)
    foreach ($terms_to_process as $category_term) {
        $category_name = $category_term->name;
        $category_id = $category_term->term_id;
        $category_count = $category_term->count;
        
        if (!isset($mapping[$category_name]) || $mapping[$category_name] === null) {
            $stats['skipped']++;
            error_log('[Bazar Normalize Category Peca] Termo "' . $category_name . '" não está no mapeamento ou deve ser ignorado. Pulando.');
            continue;
        }
        
        $componente_name = $mapping[$category_name];
        
        error_log('[Bazar Normalize Category Peca] Processando: "' . $category_name . '" (ID: ' . $category_id . ', Count: ' . $category_count . ') → "' . $componente_name . '"');
        $stats['categories_processed']++;
        
        // Buscar ou criar termo na taxonomia "componente"
        $componente_term = get_term_by('name', $componente_name, 'componente');
        
        if (!$componente_term || is_wp_error($componente_term)) {
            // Criar novo termo na taxonomia "componente"
            $insert_result = wp_insert_term(
                $componente_name,
                'componente',
                array(
                    'slug' => sanitize_title($componente_name)
                )
            );
            
            if (is_wp_error($insert_result)) {
                $stats['errors']++;
                error_log('[Bazar Normalize Category Peca] Erro ao criar termo "' . $componente_name . '" na taxonomia "componente": ' . $insert_result->get_error_message());
                $stats['details'][] = array(
                    'category_name' => $category_name,
                    'category_id' => $category_id,
                    'componente_name' => $componente_name,
                    'error' => 'Erro ao criar termo: ' . $insert_result->get_error_message()
                );
                continue;
            }
            
            $componente_term_id = $insert_result['term_id'];
            $stats['componente_terms_created']++;
            error_log('[Bazar Normalize Category Peca] Termo criado na taxonomia "componente": "' . $componente_name . '" (ID: ' . $componente_term_id . ')');
        } else {
            $componente_term_id = $componente_term->term_id;
            $stats['componente_terms_existing']++;
            error_log('[Bazar Normalize Category Peca] Termo já existe na taxonomia "componente": "' . $componente_name . '" (ID: ' . $componente_term_id . ')');
        }
        
        // Buscar todos os posts que têm essa categoria
        $posts = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'tax_query' => array(
                array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $category_id
                )
            )
        ));
        
        if (empty($posts)) {
            error_log('[Bazar Normalize Category Peca] Nenhum post encontrado com a categoria "' . $category_name . '"');
            continue;
        }
        
        error_log('[Bazar Normalize Category Peca] Encontrados ' . count($posts) . ' posts com a categoria "' . $category_name . '"');
        
        $posts_updated_for_term = 0;
        $post_details = array();
        
        // Processar cada post
        foreach ($posts as $post) {
            $post_id = $post->ID;
            $stats['posts_processed']++;
            
            // Verificar se o post já tem o termo de componente
            $existing_componente = wp_get_object_terms($post_id, 'componente', array('fields' => 'ids'));
            
            if (in_array($componente_term_id, $existing_componente)) {
                // Post já tem o termo de componente
                continue;
            }
            
            // Adicionar termo de componente ao post
            $set_result = wp_set_object_terms($post_id, array($componente_term_id), 'componente', true);
            
            if (is_wp_error($set_result)) {
                $stats['errors']++;
                error_log('[Bazar Normalize Category Peca] Erro ao associar termo de componente ao post ID ' . $post_id . ': ' . $set_result->get_error_message());
                $post_details[] = array(
                    'post_id' => $post_id,
                    'error' => 'Erro ao associar componente: ' . $set_result->get_error_message()
                );
                continue;
            }
            
            $posts_updated_for_term++;
            $stats['posts_updated']++;
        }
        
        // Adicionar detalhes para esta categoria
        $stats['details'][] = array(
            'category_name' => $category_name,
            'category_id' => $category_id,
            'componente_name' => $componente_name,
            'componente_term_id' => $componente_term_id,
            'posts_updated' => $posts_updated_for_term,
            'posts_found' => count($posts),
            'post_details' => $post_details
        );
    }
    
    // Marcar como executado
    update_option('bazar_category_peca_to_componente_normalized', true);
    update_option('bazar_category_peca_to_componente_normalized_date', current_time('mysql'));
    update_option('bazar_category_peca_to_componente_stats', $stats);
    
    return array(
        'success' => true,
        'message' => 'Normalização de category->peca para componente concluída com sucesso!',
        'stats' => $stats
    );
}

/**
 * Função para remover termos de especificacoes dos posts
 * Útil quando a migração já foi feita mas as especificações ainda estão associadas
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da remoção
 */
function bazar_remove_especificacoes_from_posts($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_especificacoes_removed')) {
        return array(
            'success' => true,
            'message' => 'A remoção de especificações já foi executada anteriormente. Marque a opção "Forçar execução" para executar novamente.',
            'stats' => get_option('bazar_especificacoes_removed_stats', array())
        );
    }
    
    $stats = array(
        'especificacoes_found' => 0,
        'posts_processed' => 0,
        'posts_updated' => 0,
        'especificacoes_removed' => 0,
        'errors' => 0,
        'details' => array()
    );
    
    // Obter mapeamento para saber quais termos remover
    $mapping = bazar_get_especificacoes_to_componente_map();
    
    // Buscar todos os termos PAIS da taxonomia "especificacoes" que estão no mapeamento
    $especificacoes_terms = get_terms(array(
        'taxonomy' => 'especificacoes',
        'parent' => 0, // Apenas termos pais
        'hide_empty' => false
    ));
    
    if (is_wp_error($especificacoes_terms) || empty($especificacoes_terms)) {
        error_log('[Bazar Remove Especificacoes] Aviso: Nenhum termo encontrado na taxonomia "especificacoes"');
        return array(
            'success' => true,
            'message' => 'Nenhum termo encontrado na taxonomia "especificacoes".',
            'stats' => $stats
        );
    }
    
    // Filtrar apenas termos que estão no mapeamento
    $terms_to_remove = array();
    foreach ($especificacoes_terms as $term) {
        if (isset($mapping[$term->name])) {
            $terms_to_remove[] = $term;
        }
    }
    
    if (empty($terms_to_remove)) {
        error_log('[Bazar Remove Especificacoes] Aviso: Nenhum termo encontrado no mapeamento');
        return array(
            'success' => true,
            'message' => 'Nenhum termo encontrado no mapeamento.',
            'stats' => $stats
        );
    }
    
    $stats['especificacoes_found'] = count($terms_to_remove);
    $term_ids_to_remove = wp_list_pluck($terms_to_remove, 'term_id');
    error_log('[Bazar Remove Especificacoes] Encontrados ' . count($terms_to_remove) . ' termos para remover');
    
    // Buscar todos os posts que têm pelo menos uma dessas especificações
    $posts = get_posts(array(
        'post_type' => 'any',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'tax_query' => array(
            array(
                'taxonomy' => 'especificacoes',
                'field' => 'term_id',
                'terms' => $term_ids_to_remove,
                'operator' => 'IN'
            )
        )
    ));
    
    if (empty($posts)) {
        error_log('[Bazar Remove Especificacoes] Nenhum post encontrado com as especificações');
        return array(
            'success' => true,
            'message' => 'Nenhum post encontrado com as especificações.',
            'stats' => $stats
        );
    }
    
    error_log('[Bazar Remove Especificacoes] Encontrados ' . count($posts) . ' posts com especificações');
    
    // Processar cada post
    foreach ($posts as $post) {
        $post_id = $post->ID;
        $stats['posts_processed']++;
        
        // Obter todos os termos de especificacoes atuais do post
        $current_especificacoes_terms = wp_get_object_terms($post_id, 'especificacoes', array('fields' => 'ids'));
        
        if (is_wp_error($current_especificacoes_terms)) {
            $stats['errors']++;
            error_log('[Bazar Remove Especificacoes] Erro ao obter especificações do post ID ' . $post_id . ': ' . $current_especificacoes_terms->get_error_message());
            continue;
        }
        
        if (empty($current_especificacoes_terms)) {
            continue;
        }
        
        // Remover todos os termos do mapeamento do array
        $updated_especificacoes_terms = array_diff($current_especificacoes_terms, $term_ids_to_remove);
        
        // Se houve mudança, atualizar os termos de especificacoes do post
        if (count($updated_especificacoes_terms) < count($current_especificacoes_terms)) {
            $removed_count = count($current_especificacoes_terms) - count($updated_especificacoes_terms);
            $stats['especificacoes_removed'] += $removed_count;
            
            // Reindexar o array para evitar problemas
            $updated_especificacoes_terms = array_values($updated_especificacoes_terms);
            
            // Atualizar os termos de especificacoes do post
            $remove_result = wp_set_object_terms($post_id, $updated_especificacoes_terms, 'especificacoes', false);
            
            if (is_wp_error($remove_result)) {
                $stats['errors']++;
                error_log('[Bazar Remove Especificacoes] Erro ao remover especificações do post ID ' . $post_id . ': ' . $remove_result->get_error_message());
                $stats['details'][] = array(
                    'post_id' => $post_id,
                    'error' => 'Erro ao remover especificações: ' . $remove_result->get_error_message()
                );
            } else {
                $stats['posts_updated']++;
                error_log('[Bazar Remove Especificacoes] Especificações removidas do post ID ' . $post_id . ' (' . $removed_count . ' especificação(ões) removida(s))');
            }
        }
    }
    
    // Marcar como executado
    update_option('bazar_especificacoes_removed', true);
    update_option('bazar_especificacoes_removed_date', current_time('mysql'));
    update_option('bazar_especificacoes_removed_stats', $stats);
    
    return array(
        'success' => true,
        'message' => 'Remoção de especificações dos posts concluída com sucesso!',
        'stats' => $stats
    );
}

/**
 * Função para deletar termos de especificacoes após migração
 * ATENÇÃO: Esta função deleta permanentemente os termos da taxonomia 'especificacoes'
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da deleção
 */
function bazar_delete_especificacoes_terms($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_especificacoes_terms_deleted')) {
        return array(
            'success' => true,
            'message' => 'A deleção de termos de especificações já foi executada anteriormente. Marque a opção "Forçar execução" para executar novamente.',
            'stats' => get_option('bazar_especificacoes_terms_deleted_stats', array())
        );
    }
    
    $stats = array(
        'terms_found' => 0,
        'terms_deleted' => 0,
        'errors' => 0,
        'details' => array()
    );
    
    // Obter mapeamento para saber quais termos deletar
    $mapping = bazar_get_especificacoes_to_componente_map();
    
    // Buscar todos os termos PAIS da taxonomia "especificacoes" que estão no mapeamento
    $especificacoes_terms = get_terms(array(
        'taxonomy' => 'especificacoes',
        'parent' => 0, // Apenas termos pais
        'hide_empty' => false
    ));
    
    if (is_wp_error($especificacoes_terms) || empty($especificacoes_terms)) {
        error_log('[Bazar Delete Especificacoes] Aviso: Nenhum termo encontrado na taxonomia "especificacoes"');
        return array(
            'success' => true,
            'message' => 'Nenhum termo encontrado na taxonomia "especificacoes".',
            'stats' => $stats
        );
    }
    
    // Filtrar apenas termos que estão no mapeamento
    $terms_to_delete = array();
    foreach ($especificacoes_terms as $term) {
        if (isset($mapping[$term->name])) {
            $terms_to_delete[] = $term;
        }
    }
    
    if (empty($terms_to_delete)) {
        error_log('[Bazar Delete Especificacoes] Aviso: Nenhum termo encontrado no mapeamento');
        return array(
            'success' => true,
            'message' => 'Nenhum termo encontrado no mapeamento.',
            'stats' => $stats
        );
    }
    
    $stats['terms_found'] = count($terms_to_delete);
    error_log('[Bazar Delete Especificacoes] Encontrados ' . count($terms_to_delete) . ' termos para deletar');
    
    // Deletar cada termo
    foreach ($terms_to_delete as $term) {
        $term_id = $term->term_id;
        $term_name = $term->name;
        
        // Verificar se o termo ainda tem posts associados (não deveria, mas vamos garantir)
        $term_posts = get_objects_in_term($term_id, 'especificacoes');
        
        if (is_wp_error($term_posts)) {
            error_log('[Bazar Delete Especificacoes] Erro ao verificar posts do termo "' . $term_name . '" (ID: ' . $term_id . '): ' . $term_posts->get_error_message());
            // Continuar mesmo assim, tentar deletar
        } elseif (!empty($term_posts) && is_array($term_posts)) {
            error_log('[Bazar Delete Especificacoes] Aviso: Termo "' . $term_name . '" (ID: ' . $term_id . ') ainda tem ' . count($term_posts) . ' post(s) associado(s). Pulando deleção.');
            $stats['details'][] = array(
                'term_id' => $term_id,
                'term_name' => $term_name,
                'error' => 'Termo ainda tem posts associados: ' . count($term_posts) . ' post(s)'
            );
            continue;
        }
        
        // Deletar o termo
        $delete_result = wp_delete_term($term_id, 'especificacoes');
        
        if (is_wp_error($delete_result)) {
            $stats['errors']++;
            error_log('[Bazar Delete Especificacoes] Erro ao deletar termo "' . $term_name . '" (ID: ' . $term_id . '): ' . $delete_result->get_error_message());
            $stats['details'][] = array(
                'term_id' => $term_id,
                'term_name' => $term_name,
                'error' => 'Erro ao deletar termo: ' . $delete_result->get_error_message()
            );
        } else {
            $stats['terms_deleted']++;
            error_log('[Bazar Delete Especificacoes] Termo deletado: "' . $term_name . '" (ID: ' . $term_id . ')');
        }
    }
    
    // Atualizar mapeamento de redirecionamento removendo termos deletados
    $redirect_map = get_option('bazar_especificacoes_to_componente_redirect_map', array());
    if (!empty($redirect_map)) {
        foreach ($terms_to_delete as $term) {
            $especificacao_slug = $term->slug;
            if (isset($redirect_map[$especificacao_slug])) {
                unset($redirect_map[$especificacao_slug]);
            }
        }
        update_option('bazar_especificacoes_to_componente_redirect_map', $redirect_map, true);
    }
    
    // Marcar como executado
    update_option('bazar_especificacoes_terms_deleted', true);
    update_option('bazar_especificacoes_terms_deleted_date', current_time('mysql'));
    update_option('bazar_especificacoes_terms_deleted_stats', $stats);
    
    return array(
        'success' => true,
        'message' => 'Deleção de termos de especificações concluída com sucesso!',
        'stats' => $stats
    );
}

// Adicionar página no menu admin para executar o script
function bazar_add_normalize_especificacoes_menu() {
    add_management_page(
        'Normalizar Especificações para Componente',
        'Normalizar Especificações',
        'manage_options',
        'bazar-normalize-especificacoes',
        'bazar_normalize_especificacoes_page'
    );
}
add_action('admin_menu', 'bazar_add_normalize_especificacoes_menu');

// Página de administração para normalizar
function bazar_normalize_especificacoes_page() {
    $already_normalized = get_option('bazar_especificacoes_to_componente_normalized');
    $normalized_date = get_option('bazar_especificacoes_to_componente_normalized_date');
    $last_stats = get_option('bazar_especificacoes_to_componente_stats');
    $especificacoes_removed = get_option('bazar_especificacoes_removed');
    $terms_deleted = get_option('bazar_especificacoes_terms_deleted');
    
    // Executar normalização
    if (isset($_POST['bazar_execute_normalize_especificacoes']) && check_admin_referer('bazar_normalize_especificacoes_action')) {
        $force = isset($_POST['force']) && $_POST['force'] == '1';
        
        $result = bazar_normalize_especificacoes_to_componente($force);
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Estatísticas:</strong><br>';
            echo 'Especificações encontradas: ' . $stats['especificacoes_found'] . '<br>';
            echo 'Especificações processadas: ' . $stats['especificacoes_processed'] . '<br>';
            echo 'Termos de componente criados: ' . $stats['componente_terms_created'] . '<br>';
            echo 'Termos de componente existentes: ' . $stats['componente_terms_existing'] . '<br>';
            echo 'Posts processados: ' . $stats['posts_processed'] . '<br>';
            echo 'Posts atualizados: ' . $stats['posts_updated'] . '<br>';
            echo 'Termos ignorados: ' . $stats['skipped'] . '<br>';
            if ($stats['errors'] > 0) {
                echo 'Erros: ' . $stats['errors'] . '<br>';
            }
            echo '</p></div>';
            
            // Mostrar detalhes se houver
            if (!empty($stats['details']) && count($stats['details']) <= 50) {
                echo '<div class="card"><h3>Detalhes das Migrações</h3>';
                echo '<table class="widefat">';
                echo '<thead><tr><th>Especificação</th><th>Componente</th><th>Componente ID</th><th>Filhos Migrados</th><th>Posts Encontrados</th><th>Posts Atualizados</th><th>Erros</th></tr></thead>';
                echo '<tbody>';
                foreach ($stats['details'] as $detail) {
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($detail['especificacao_name']) . '</strong><br><small>Especificação ID: ' . $detail['especificacao_id'] . '</small></td>';
                    echo '<td>' . esc_html($detail['componente_name']) . '</td>';
                    echo '<td>' . $detail['componente_term_id'] . '</td>';
                    echo '<td>' . (isset($detail['children_migrated']) ? $detail['children_migrated'] : '0') . '</td>';
                    echo '<td>' . $detail['posts_found'] . '</td>';
                    echo '<td>' . $detail['posts_updated'] . '</td>';
                    echo '<td>' . (empty($detail['post_details']) ? '-' : '<span style="color: red;">' . count($detail['post_details']) . ' erro(s)</span>') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            } elseif (!empty($stats['details'])) {
                echo '<div class="notice notice-info"><p>Total de ' . count($stats['details']) . ' especificações processadas. Detalhes completos disponíveis no log do WordPress.</p></div>';
            }
        }
    }
    
    // Executar normalização de category->peca
    if (isset($_POST['bazar_execute_normalize_category_peca']) && check_admin_referer('bazar_normalize_category_peca_action')) {
        $force = isset($_POST['force_peca']) && $_POST['force_peca'] == '1';
        
        $result = bazar_normalize_category_peca_to_componente($force);
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Estatísticas:</strong><br>';
            echo 'Categorias encontradas: ' . $stats['categories_found'] . '<br>';
            echo 'Categorias processadas: ' . $stats['categories_processed'] . '<br>';
            echo 'Termos de componente criados: ' . $stats['componente_terms_created'] . '<br>';
            echo 'Termos de componente existentes: ' . $stats['componente_terms_existing'] . '<br>';
            echo 'Posts processados: ' . $stats['posts_processed'] . '<br>';
            echo 'Posts atualizados: ' . $stats['posts_updated'] . '<br>';
            echo 'Termos ignorados: ' . $stats['skipped'] . '<br>';
            if ($stats['errors'] > 0) {
                echo 'Erros: ' . $stats['errors'] . '<br>';
            }
            echo '</p></div>';
            
            // Mostrar detalhes se houver
            if (!empty($stats['details']) && count($stats['details']) <= 50) {
                echo '<div class="card"><h3>Detalhes das Migrações</h3>';
                echo '<table class="widefat">';
                echo '<thead><tr><th>Categoria</th><th>Componente</th><th>Componente ID</th><th>Posts Encontrados</th><th>Posts Atualizados</th><th>Erros</th></tr></thead>';
                echo '<tbody>';
                foreach ($stats['details'] as $detail) {
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($detail['category_name']) . '</strong><br><small>Category ID: ' . $detail['category_id'] . '</small></td>';
                    echo '<td>' . esc_html($detail['componente_name']) . '</td>';
                    echo '<td>' . $detail['componente_term_id'] . '</td>';
                    echo '<td>' . $detail['posts_found'] . '</td>';
                    echo '<td>' . $detail['posts_updated'] . '</td>';
                    echo '<td>' . (empty($detail['post_details']) ? '-' : '<span style="color: red;">' . count($detail['post_details']) . ' erro(s)</span>') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            } elseif (!empty($stats['details'])) {
                echo '<div class="notice notice-info"><p>Total de ' . count($stats['details']) . ' categorias processadas. Detalhes completos disponíveis no log do WordPress.</p></div>';
            }
        }
    }
    
    // Executar remoção de especificações
    if (isset($_POST['bazar_execute_remove_especificacoes']) && check_admin_referer('bazar_remove_especificacoes_action')) {
        $force = isset($_POST['force_remove']) && $_POST['force_remove'] == '1';
        
        $result = bazar_remove_especificacoes_from_posts($force);
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Estatísticas da Remoção:</strong><br>';
            echo 'Especificações encontradas: ' . $stats['especificacoes_found'] . '<br>';
            echo 'Posts processados: ' . $stats['posts_processed'] . '<br>';
            echo 'Posts atualizados: ' . $stats['posts_updated'] . '<br>';
            echo 'Especificações removidas: ' . $stats['especificacoes_removed'] . '<br>';
            if ($stats['errors'] > 0) {
                echo 'Erros: ' . $stats['errors'] . '<br>';
            }
            echo '</p></div>';
        }
    }
    
    // Executar deleção de termos
    if (isset($_POST['bazar_execute_delete_especificacoes']) && check_admin_referer('bazar_delete_especificacoes_action')) {
        $force = isset($_POST['force_delete']) && $_POST['force_delete'] == '1';
        
        $result = bazar_delete_especificacoes_terms($force);
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Estatísticas da Deleção:</strong><br>';
            echo 'Termos encontrados: ' . $stats['terms_found'] . '<br>';
            echo 'Termos deletados: ' . $stats['terms_deleted'] . '<br>';
            if ($stats['errors'] > 0) {
                echo 'Erros: ' . $stats['errors'] . '<br>';
            }
            echo '</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Normalizar Especificações para Componente</h1>
        
        <div class="card">
            <h2>Migrar Especificações para Taxonomia "Componente"</h2>
            <p>Este script migra termos da taxonomia antiga "especificacoes" para a taxonomia "componente":</p>
            <ul>
                <li><strong>Mapeamento:</strong> Aro → Aro, Freio → Freio, Quadro → Quadro, Trocador → Trocador, Links → Corrente, Relação → Cassete</li>
                <li><strong>Critérios:</strong> Termos PAIS com count > 0 (e seus FILHOS também são migrados)</li>
                <li><strong>Importante:</strong> Os FILHOS também são migrados (ex: "Aro 29" → componente "Aro" com filho "29")</li>
                <li><strong>Ignorados:</strong> Velocidade (conforme solicitado)</li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca termos PAIS da taxonomia "especificacoes" com count > 0</li>
                <li>Filtra apenas termos que estão no mapeamento</li>
                <li>Para cada termo PAIS:
                    <ul>
                        <li>Busca ou cria o termo PAIS correspondente na taxonomia "componente"</li>
                        <li>Busca todos os FILHOS da especificação</li>
                        <li>Para cada FILHO, busca ou cria o filho correspondente em componente (como filho do componente PAIS)</li>
                        <li>Busca todos os posts que têm essa especificação (PAIS ou FILHOS)</li>
                        <li>Associa o termo de componente correspondente (PAIS ou FILHO) a esses posts</li>
                    </ul>
                </li>
                <li>Cria mapeamento de redirecionamento (slug_especificacoes => slug_componente)</li>
            </ol>
            
            <?php if ($already_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização já foi executada anteriormente.</p>
                    <?php if ($normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($last_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $last_stats['posts_updated']; ?> posts atualizados de <?php echo $last_stats['posts_processed']; ?> processados.
                            <?php echo $last_stats['especificacoes_processed']; ?> especificações processadas.
                        </p>
                    <?php endif; ?>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A normalização ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('bazar_normalize_especificacoes_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force" value="1" <?php checked($already_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado anteriormente)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize_especificacoes" class="button button-primary button-large">
                        Executar Normalização
                    </button>
                </p>
            </form>
            
            <hr>
            <h3>Avisos Importantes</h3>
            <ul>
                <li><strong>Backup:</strong> Recomenda-se fazer backup do banco de dados antes de executar qualquer script.</li>
                <li><strong>Foco:</strong> Aros são muito importantes, especialmente Aro 29.</li>
                <li><strong>Termos:</strong> Se um termo não existir na taxonomia "componente", ele será criado automaticamente.</li>
                <li><strong>Duplicatas:</strong> O script evita criar termos duplicados e não adiciona termos de componente duplicados aos posts.</li>
            </ul>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Migrar Category->Peça para Taxonomia "Componente"</h2>
            <p>Este script migra os filhos da categoria "Peça" (apenas PAIS, não filhos) para a taxonomia "componente":</p>
            <ul>
                <li><strong>Mapeamento:</strong> Freios → Freio, Pedal → Pedal, Câmbio Traseiro → Câmbio Traseiro, Coroa → Coroa, Passador Trigger → Trocador/Passador, Corrente → Corrente, Guidão → Guidão, Canote → Canote, Banco/Selim → Selim, Quadro → Quadro, Freio Dianteiro → Freio, Disco de Freio → Freio, Roda → Roda</li>
                <li><strong>Critérios:</strong> Apenas termos PAIS (filhos de "Peça")</li>
                <li><strong>Ignorados:</strong> Kit/Grupo, Cubo (sem correspondência direta)</li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca o termo "Peça" na taxonomia "category"</li>
                <li>Busca todos os FILHOS de "Peça" (apenas PAIS, não netos)</li>
                <li>Para cada filho:
                    <ul>
                        <li>Verifica se está no mapeamento</li>
                        <li>Busca ou cria o termo correspondente na taxonomia "componente"</li>
                        <li>Busca todos os posts que têm essa categoria</li>
                        <li>Associa o termo de componente a esses posts</li>
                    </ul>
                </li>
            </ol>
            
            <?php
            $peca_normalized = get_option('bazar_category_peca_to_componente_normalized');
            $peca_normalized_date = get_option('bazar_category_peca_to_componente_normalized_date');
            $peca_last_stats = get_option('bazar_category_peca_to_componente_stats');
            ?>
            
            <?php if ($peca_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização já foi executada anteriormente.</p>
                    <?php if ($peca_normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($peca_normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($peca_last_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $peca_last_stats['posts_updated']; ?> posts atualizados de <?php echo $peca_last_stats['posts_processed']; ?> processados.
                            <?php echo $peca_last_stats['categories_processed']; ?> categorias processadas.
                        </p>
                    <?php endif; ?>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A normalização ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('bazar_normalize_category_peca_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force_peca" value="1" <?php checked($peca_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado anteriormente)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize_category_peca" class="button button-primary button-large">
                        Executar Normalização Category->Peça
                    </button>
                </p>
            </form>
            
            <hr>
            <h3>Avisos Importantes</h3>
            <ul>
                <li><strong>Backup:</strong> Recomenda-se fazer backup do banco de dados antes de executar qualquer script.</li>
                <li><strong>Apenas PAIS:</strong> Este script migra apenas os filhos diretos de "Peça", não os netos.</li>
                <li><strong>Termos:</strong> Se um termo não existir na taxonomia "componente", ele será criado automaticamente.</li>
                <li><strong>Duplicatas:</strong> O script evita criar termos duplicados e não adiciona termos de componente duplicados aos posts.</li>
            </ul>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Remover Especificações dos Posts</h2>
            <p>Esta função remove as especificações dos posts (mas não deleta os termos).</p>
            <p><strong>Use esta opção se:</strong></p>
            <ul>
                <li>A migração para componente já foi feita anteriormente</li>
                <li>As especificações ainda estão associadas aos posts</li>
                <li>Você quer limpar as associações antigas</li>
            </ul>
            
            <?php if ($especificacoes_removed): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A remoção de especificações já foi executada anteriormente.</p>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A remoção de especificações ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('bazar_remove_especificacoes_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force_remove" value="1" <?php checked($especificacoes_removed); ?>>
                        Forçar execução (executar mesmo se já foi executado anteriormente)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_remove_especificacoes" class="button button-secondary button-large">
                        Remover Especificações dos Posts
                    </button>
                </p>
            </form>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Deletar Termos de Especificações</h2>
            <p>Esta função <strong>deleta permanentemente</strong> os termos da taxonomia 'especificacoes'.</p>
            <p><strong>Use esta opção se:</strong></p>
            <ul>
                <li>A migração para componente já foi feita anteriormente</li>
                <li>As especificações já foram removidas dos posts</li>
                <li>Você quer limpar completamente os termos antigos</li>
            </ul>
            <p><strong>Atenção:</strong> Esta função irá <strong>deletar permanentemente</strong> os termos da taxonomia 'especificacoes'. Certifique-se de que a migração foi bem-sucedida antes de executar.</p>
            
            <?php if ($terms_deleted): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A deleção de termos já foi executada anteriormente.</p>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A deleção de termos ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('bazar_delete_especificacoes_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force_delete" value="1" <?php checked($terms_deleted); ?>>
                        Forçar execução (executar mesmo se já foi executado anteriormente)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_delete_especificacoes" class="button button-secondary button-large" style="background-color: #dc3232; color: white; border-color: #dc3232;">
                        Deletar Termos de Especificações
                    </button>
                </p>
            </form>
        </div>
    </div>
    <?php
}
?>

