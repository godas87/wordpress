<?php
/**
 * Script para normalizar categorias para taxonomia 'modalidade'
 * 
 * Migra os filhos da categoria "Bicicleta" (BMX, Cargueira, Chopper, etc.)
 * para a taxonomia "modalidade"
 * 
 * IMPORTANTE: Execute este script apenas UMA VEZ através do painel do WordPress
 * ou via WP-CLI. Para executar via painel, adicione temporariamente no functions.php:
 * require_once get_template_directory() . '/scripts/normalize-category-to-modalidade.php';
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normaliza o campo 'descricao' ao migrar de category para modalidade
 * Transfere conteúdo do ACF Field 'descricao' para 'descricao_seo' e limpa ambiguidades
 * 
 * @param int $category_term_id ID do termo de category
 * @param int $modalidade_term_id ID do termo de modalidade
 * @return bool true se normalizado com sucesso
 */
function bazar_normalize_description_on_migration($category_term_id, $modalidade_term_id) {
    // 1. Buscar conteúdo do ACF Field 'descricao' do termo de category
    $category_descricao_acf = '';
    if (function_exists('get_field')) {
        $category_descricao_acf = get_field('descricao', 'category_' . $category_term_id);
        // Se for array (ACF image field), pegar apenas o valor
        if (is_array($category_descricao_acf)) {
            $category_descricao_acf = '';
        }
    }
    
    // Se não encontrou no ACF, verificar no term meta
    if (empty($category_descricao_acf)) {
        $category_descricao_meta = get_term_meta($category_term_id, 'descricao', true);
        if (!empty($category_descricao_meta)) {
            $category_descricao_acf = $category_descricao_meta;
        }
    }
    
    // 2. Se encontrou conteúdo no termo de category, transferir para ACF Field 'descricao_seo' do termo de modalidade
    if (!empty($category_descricao_acf)) {
        // Verificar se já existe conteúdo em "descricao_seo" (não sobrescrever se já tiver)
        $existing_descricao_seo = '';
        if (function_exists('get_field')) {
            $existing_descricao_seo = get_field('descricao_seo', 'modalidade_' . $modalidade_term_id);
            if (is_array($existing_descricao_seo)) {
                $existing_descricao_seo = '';
            }
        }
        // Se não encontrou no ACF, verificar no term meta
        if (empty($existing_descricao_seo)) {
            $existing_descricao_seo = get_term_meta($modalidade_term_id, 'descricao_seo', true);
        }
        
        // Só transferir se "descricao_seo" estiver vazio (preservar conteúdo já existente)
        if (empty($existing_descricao_seo)) {
            // Transferir conteúdo do termo de category para o ACF Field 'descricao_seo' do termo de modalidade
            if (function_exists('update_field')) {
                update_field('descricao_seo', sanitize_textarea_field($category_descricao_acf), 'modalidade_' . $modalidade_term_id);
            }
            // Também salvar como term meta para garantir
            update_term_meta($modalidade_term_id, 'descricao_seo', sanitize_textarea_field($category_descricao_acf));
            error_log('[Bazar Normalize Category] Conteúdo do ACF Field "descricao" do termo de category (ID: ' . $category_term_id . ') transferido para "descricao_seo" do termo de modalidade (ID: ' . $modalidade_term_id . ')');
        }
    }
    
    // 3. Limpar o ACF Field 'descricao' do termo de modalidade (remover ambiguidade)
    if (function_exists('update_field')) {
        update_field('descricao', '', 'modalidade_' . $modalidade_term_id);
    }
    
    // 4. Limpar o term meta 'descricao' do termo de modalidade também (remover ambiguidade)
    delete_term_meta($modalidade_term_id, 'descricao');
    
    // 5. Copiar a description padrão do WordPress do termo de category para o termo de modalidade
    $category_term = get_term($category_term_id, 'category');
    if ($category_term && !is_wp_error($category_term) && !empty($category_term->description)) {
        $term_update = wp_update_term($modalidade_term_id, 'modalidade', array(
            'description' => sanitize_textarea_field($category_term->description)
        ));
        
        if (is_wp_error($term_update)) {
            error_log('[Bazar Normalize Category] Erro ao atualizar description do termo de modalidade: ' . $term_update->get_error_message());
            return false;
        }
    }
    
    return true;
}

/**
 * Função principal para normalizar categorias para modalidade
 * @param bool $force Forçar execução mesmo se já foi executado
 * @param bool $remove_category_terms Remover termos de categoria após migração
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_category_to_modalidade($force = false, $remove_category_terms = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_category_to_modalidade_normalized')) {
        return array(
            'success' => true,
            'message' => 'A normalização de categorias para modalidade já foi executada anteriormente. Marque a opção "Forçar execução" para executar novamente.',
            'stats' => get_option('bazar_category_to_modalidade_stats', array())
        );
    }
    
    $stats = array(
        'categories_found' => 0,
        'modalidade_terms_created' => 0,
        'modalidade_terms_existing' => 0,
        'posts_updated' => 0,
        'posts_processed' => 0,
        'errors' => 0,
        'details' => array()
    );
    
    // Buscar o termo "Bicicleta" na taxonomia "category"
    $bicicleta_term = get_term_by('name', 'Bicicleta', 'category');
    
    if (!$bicicleta_term || is_wp_error($bicicleta_term)) {
        error_log('[Bazar Normalize Category] Erro: Termo "Bicicleta" não encontrado na taxonomia "category"');
        return array(
            'success' => false,
            'message' => 'Erro: Termo "Bicicleta" não encontrado na taxonomia "category"',
            'stats' => $stats
        );
    }
    
    // Buscar todos os filhos de "Bicicleta"
    $child_terms = get_terms(array(
        'taxonomy' => 'category',
        'parent' => $bicicleta_term->term_id,
        'hide_empty' => false
    ));
    
    if (is_wp_error($child_terms) || empty($child_terms)) {
        error_log('[Bazar Normalize Category] Aviso: Nenhum filho encontrado para "Bicicleta"');
        return array(
            'success' => true,
            'message' => 'Nenhum filho encontrado para a categoria "Bicicleta". Nada a migrar.',
            'stats' => $stats
        );
    }
    
    $stats['categories_found'] = count($child_terms);
    error_log('[Bazar Normalize Category] Encontrados ' . count($child_terms) . ' filhos de "Bicicleta"');
    
    // Processar cada categoria filha
    foreach ($child_terms as $category_term) {
        $category_name = $category_term->name;
        $category_id = $category_term->term_id;
        
        error_log('[Bazar Normalize Category] Processando categoria: "' . $category_name . '" (ID: ' . $category_id . ')');
        
        // Buscar ou criar termo na taxonomia "modalidade"
        $modalidade_term = get_term_by('name', $category_name, 'modalidade');
        
        if (!$modalidade_term || is_wp_error($modalidade_term)) {
            // Criar novo termo na taxonomia "modalidade"
            $insert_result = wp_insert_term(
                $category_name,
                'modalidade',
                array(
                    'slug' => sanitize_title($category_name)
                )
            );
            
            if (is_wp_error($insert_result)) {
                $stats['errors']++;
                error_log('[Bazar Normalize Category] Erro ao criar termo "' . $category_name . '" na taxonomia "modalidade": ' . $insert_result->get_error_message());
                $stats['details'][] = array(
                    'category_name' => $category_name,
                    'category_id' => $category_id,
                    'error' => 'Erro ao criar termo: ' . $insert_result->get_error_message()
                );
                continue;
            }
            
            $modalidade_term_id = $insert_result['term_id'];
            $stats['modalidade_terms_created']++;
            error_log('[Bazar Normalize Category] Termo criado na taxonomia "modalidade": "' . $category_name . '" (ID: ' . $modalidade_term_id . ')');
        } else {
            $modalidade_term_id = $modalidade_term->term_id;
            $stats['modalidade_terms_existing']++;
            error_log('[Bazar Normalize Category] Termo já existe na taxonomia "modalidade": "' . $category_name . '" (ID: ' . $modalidade_term_id . ')');
        }
        
        // Normalizar campo 'descricao' ao migrar de category para modalidade
        // Transfere conteúdo do ACF Field 'descricao' para 'descricao_seo' e limpa ambiguidades
        $normalize_result = bazar_normalize_description_on_migration($category_id, $modalidade_term_id);
        if (!$normalize_result) {
            error_log('[Bazar Normalize Category] Aviso: Erro ao normalizar campo "descricao" para o termo de modalidade (ID: ' . $modalidade_term_id . ')');
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
            error_log('[Bazar Normalize Category] Nenhum post encontrado com a categoria "' . $category_name . '"');
            continue;
        }
        
        error_log('[Bazar Normalize Category] Encontrados ' . count($posts) . ' posts com a categoria "' . $category_name . '"');
        
        $posts_updated_for_category = 0;
        $post_details = array();
        
        // Processar cada post
        foreach ($posts as $post) {
            $post_id = $post->ID;
            $stats['posts_processed']++;
            
            // Verificar se o post já tem o termo de modalidade
            $existing_modalidade = wp_get_object_terms($post_id, 'modalidade', array('fields' => 'ids'));
            
            if (in_array($modalidade_term_id, $existing_modalidade)) {
                // Post já tem o termo de modalidade
                continue;
            }
            
            // Adicionar termo de modalidade ao post
            $set_result = wp_set_object_terms($post_id, array($modalidade_term_id), 'modalidade', true);
            
            if (is_wp_error($set_result)) {
                $stats['errors']++;
                error_log('[Bazar Normalize Category] Erro ao associar termo de modalidade ao post ID ' . $post_id . ': ' . $set_result->get_error_message());
                $post_details[] = array(
                    'post_id' => $post_id,
                    'error' => 'Erro ao associar modalidade: ' . $set_result->get_error_message()
                );
                continue;
            }
            
            $posts_updated_for_category++;
            $stats['posts_updated']++;
            
            // Se solicitado, remover a categoria filha do post
            if ($remove_category_terms) {
                // Obter todos os termos de categoria atuais do post
                $current_category_terms = wp_get_object_terms($post_id, 'category', array('fields' => 'ids'));
                
                if (!is_wp_error($current_category_terms) && !empty($current_category_terms)) {
                    // Remover o termo específico do array
                    $updated_category_terms = array_diff($current_category_terms, array($category_id));
                    
                    // Atualizar os termos de categoria do post
                    $remove_result = wp_set_object_terms($post_id, $updated_category_terms, 'category', false);
                    
                    if (is_wp_error($remove_result)) {
                        error_log('[Bazar Normalize Category] Erro ao remover categoria do post ID ' . $post_id . ': ' . $remove_result->get_error_message());
                    } else {
                        error_log('[Bazar Normalize Category] Categoria removida do post ID ' . $post_id);
                    }
                }
            }
        }
        
        // Adicionar detalhes para esta categoria
        $stats['details'][] = array(
            'category_name' => $category_name,
            'category_id' => $category_id,
            'modalidade_term_id' => $modalidade_term_id,
            'posts_updated' => $posts_updated_for_category,
            'posts_found' => count($posts),
            'post_details' => $post_details
        );
    }
    
    // Criar mapeamento de redirecionamento (slug_category => slug_modalidade)
    // Salvar em option com autoload=true para cache em memória (otimizado para LiteSpeed)
    $redirect_map = array();
    foreach ($child_terms as $category_term) {
        $category_slug = $category_term->slug;
        $modalidade_term = get_term_by('name', $category_term->name, 'modalidade');
        if ($modalidade_term && !is_wp_error($modalidade_term)) {
            $redirect_map[$category_slug] = $modalidade_term->slug;
        }
    }
    
    // Salvar mapeamento com autoload para cache em memória
    update_option('bazar_category_to_modalidade_redirect_map', $redirect_map, true);
    
    // Marcar como executado
    update_option('bazar_category_to_modalidade_normalized', true);
    update_option('bazar_category_to_modalidade_normalized_date', current_time('mysql'));
    update_option('bazar_category_to_modalidade_stats', $stats);
    if ($remove_category_terms) {
        update_option('bazar_category_terms_removed', true);
    }
    
    return array(
        'success' => true,
        'message' => 'Normalização de categorias para modalidade concluída com sucesso!',
        'stats' => $stats
    );
}

/**
 * Função para remover apenas as categorias filhas de "Bicicleta" dos posts
 * Útil quando a migração já foi feita mas as categorias ainda estão associadas
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da remoção
 */
function bazar_remove_category_children_from_posts($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_category_children_removed')) {
        return array(
            'success' => true,
            'message' => 'A remoção de categorias filhas já foi executada anteriormente. Marque a opção "Forçar execução" para executar novamente.',
            'stats' => get_option('bazar_category_children_removed_stats', array())
        );
    }
    
    $stats = array(
        'categories_found' => 0,
        'posts_processed' => 0,
        'posts_updated' => 0,
        'categories_removed' => 0,
        'terms_deleted' => 0,
        'errors' => 0,
        'details' => array()
    );
    
    // Buscar o termo "Bicicleta" na taxonomia "category"
    $bicicleta_term = get_term_by('name', 'Bicicleta', 'category');
    
    if (!$bicicleta_term || is_wp_error($bicicleta_term)) {
        error_log('[Bazar Remove Category] Erro: Termo "Bicicleta" não encontrado na taxonomia "category"');
        return array(
            'success' => false,
            'message' => 'Erro: Termo "Bicicleta" não encontrado na taxonomia "category"',
            'stats' => $stats
        );
    }
    
    // Buscar todos os filhos de "Bicicleta"
    $child_terms = get_terms(array(
        'taxonomy' => 'category',
        'parent' => $bicicleta_term->term_id,
        'hide_empty' => false
    ));
    
    if (is_wp_error($child_terms) || empty($child_terms)) {
        error_log('[Bazar Remove Category] Aviso: Nenhum filho encontrado para "Bicicleta"');
        return array(
            'success' => true,
            'message' => 'Nenhum filho encontrado para a categoria "Bicicleta". Nada a remover.',
            'stats' => $stats
        );
    }
    
    $stats['categories_found'] = count($child_terms);
    $child_term_ids = wp_list_pluck($child_terms, 'term_id');
    error_log('[Bazar Remove Category] Encontrados ' . count($child_terms) . ' filhos de "Bicicleta"');
    
    // Buscar todos os posts que têm pelo menos uma dessas categorias filhas
    $posts = get_posts(array(
        'post_type' => 'any',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'tax_query' => array(
            array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $child_term_ids,
                'operator' => 'IN'
            )
        )
    ));
    
    if (empty($posts)) {
        error_log('[Bazar Remove Category] Nenhum post encontrado com as categorias filhas');
        return array(
            'success' => true,
            'message' => 'Nenhum post encontrado com as categorias filhas de "Bicicleta".',
            'stats' => $stats
        );
    }
    
    error_log('[Bazar Remove Category] Encontrados ' . count($posts) . ' posts com categorias filhas');
    
    // Processar cada post
    foreach ($posts as $post) {
        $post_id = $post->ID;
        $stats['posts_processed']++;
        
        // Obter todos os termos de categoria atuais do post
        $current_category_terms = wp_get_object_terms($post_id, 'category', array('fields' => 'ids'));
        
        if (is_wp_error($current_category_terms)) {
            $stats['errors']++;
            error_log('[Bazar Remove Category] Erro ao obter categorias do post ID ' . $post_id . ': ' . $current_category_terms->get_error_message());
            continue;
        }
        
        if (empty($current_category_terms)) {
            continue;
        }
        
        // Remover todos os termos filhos de "Bicicleta" do array
        $updated_category_terms = array_diff($current_category_terms, $child_term_ids);
        
        // Se houve mudança, atualizar os termos de categoria do post
        if (count($updated_category_terms) < count($current_category_terms)) {
            $removed_count = count($current_category_terms) - count($updated_category_terms);
            $stats['categories_removed'] += $removed_count;
            
            // Reindexar o array para evitar problemas
            $updated_category_terms = array_values($updated_category_terms);
            
            // Atualizar os termos de categoria do post
            $remove_result = wp_set_object_terms($post_id, $updated_category_terms, 'category', false);
            
            if (is_wp_error($remove_result)) {
                $stats['errors']++;
                error_log('[Bazar Remove Category] Erro ao remover categorias do post ID ' . $post_id . ': ' . $remove_result->get_error_message());
                $stats['details'][] = array(
                    'post_id' => $post_id,
                    'error' => 'Erro ao remover categorias: ' . $remove_result->get_error_message()
                );
            } else {
                $stats['posts_updated']++;
                error_log('[Bazar Remove Category] Categorias removidas do post ID ' . $post_id . ' (' . $removed_count . ' categoria(s) removida(s))');
            }
        }
    }
    
    // Após desassociar todos os posts, deletar os termos filhos da taxonomia 'category'
    error_log('[Bazar Remove Category] Iniciando deleção dos termos filhos de "Bicicleta"');
    
    foreach ($child_terms as $child_term) {
        $term_id = $child_term->term_id;
        $term_name = $child_term->name;
        
        // Verificar se o termo ainda tem posts associados (não deveria, mas vamos garantir)
        $term_posts = get_objects_in_term($term_id, 'category');
        
        if (is_wp_error($term_posts)) {
            error_log('[Bazar Remove Category] Erro ao verificar posts do termo "' . $term_name . '" (ID: ' . $term_id . '): ' . $term_posts->get_error_message());
            // Continuar mesmo assim, tentar deletar
        } elseif (!empty($term_posts) && is_array($term_posts)) {
            error_log('[Bazar Remove Category] Aviso: Termo "' . $term_name . '" (ID: ' . $term_id . ') ainda tem ' . count($term_posts) . ' post(s) associado(s). Pulando deleção.');
            $stats['details'][] = array(
                'term_id' => $term_id,
                'term_name' => $term_name,
                'error' => 'Termo ainda tem posts associados: ' . count($term_posts) . ' post(s)'
            );
            continue;
        }
        
        // Deletar o termo
        $delete_result = wp_delete_term($term_id, 'category');
        
        if (is_wp_error($delete_result)) {
            $stats['errors']++;
            error_log('[Bazar Remove Category] Erro ao deletar termo "' . $term_name . '" (ID: ' . $term_id . '): ' . $delete_result->get_error_message());
            $stats['details'][] = array(
                'term_id' => $term_id,
                'term_name' => $term_name,
                'error' => 'Erro ao deletar termo: ' . $delete_result->get_error_message()
            );
        } else {
            $stats['terms_deleted']++;
            error_log('[Bazar Remove Category] Termo deletado: "' . $term_name . '" (ID: ' . $term_id . ')');
        }
    }
    
    // Atualizar mapeamento de redirecionamento removendo categorias deletadas
    $redirect_map = get_option('bazar_category_to_modalidade_redirect_map', array());
    if (!empty($redirect_map)) {
        foreach ($child_terms as $child_term) {
            $category_slug = $child_term->slug;
            if (isset($redirect_map[$category_slug])) {
                unset($redirect_map[$category_slug]);
            }
        }
        update_option('bazar_category_to_modalidade_redirect_map', $redirect_map, true);
    }
    
    // Marcar como executado
    update_option('bazar_category_children_removed', true);
    update_option('bazar_category_children_removed_date', current_time('mysql'));
    update_option('bazar_category_children_removed_stats', $stats);
    
    return array(
        'success' => true,
        'message' => 'Remoção de categorias filhas e deleção dos termos concluída com sucesso!',
        'stats' => $stats
    );
}

// Adicionar página no menu admin para executar o script
function bazar_add_normalize_category_menu() {
    add_management_page(
        'Normalizar Categorias para Modalidade',
        'Normalizar Categorias',
        'manage_options',
        'bazar-normalize-category',
        'bazar_normalize_category_page'
    );
}
add_action('admin_menu', 'bazar_add_normalize_category_menu');

// Página de administração para normalizar
function bazar_normalize_category_page() {
    $already_normalized = get_option('bazar_category_to_modalidade_normalized');
    $normalized_date = get_option('bazar_category_to_modalidade_normalized_date');
    $category_terms_removed = get_option('bazar_category_terms_removed');
    $last_stats = get_option('bazar_category_to_modalidade_stats');
    
    // Executar normalização
    if (isset($_POST['bazar_execute_normalize_category']) && check_admin_referer('bazar_normalize_category_action')) {
        $force = isset($_POST['force']) && $_POST['force'] == '1';
        $remove_category = isset($_POST['remove_category_terms']) && $_POST['remove_category_terms'] == '1';
        
        $result = bazar_normalize_category_to_modalidade($force, $remove_category);
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Estatísticas:</strong><br>';
            echo 'Categorias encontradas: ' . $stats['categories_found'] . '<br>';
            echo 'Termos de modalidade criados: ' . $stats['modalidade_terms_created'] . '<br>';
            echo 'Termos de modalidade existentes: ' . $stats['modalidade_terms_existing'] . '<br>';
            echo 'Posts processados: ' . $stats['posts_processed'] . '<br>';
            echo 'Posts atualizados: ' . $stats['posts_updated'] . '<br>';
            if ($stats['errors'] > 0) {
                echo 'Erros: ' . $stats['errors'] . '<br>';
            }
            echo '</p></div>';
            
            // Mostrar detalhes se houver
            if (!empty($stats['details']) && count($stats['details']) <= 50) {
                echo '<div class="card"><h3>Detalhes das Migrações</h3>';
                echo '<table class="widefat">';
                echo '<thead><tr><th>Categoria</th><th>Termo Modalidade ID</th><th>Posts Encontrados</th><th>Posts Atualizados</th><th>Erros</th></tr></thead>';
                echo '<tbody>';
                foreach ($stats['details'] as $detail) {
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($detail['category_name']) . '</strong><br><small>Category ID: ' . $detail['category_id'] . '</small></td>';
                    echo '<td>' . $detail['modalidade_term_id'] . '</td>';
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
    
    // Executar remoção de categorias filhas
    if (isset($_POST['bazar_execute_remove_categories']) && check_admin_referer('bazar_remove_category_action')) {
        $force = isset($_POST['force_remove']) && $_POST['force_remove'] == '1';
        
        $result = bazar_remove_category_children_from_posts($force);
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Estatísticas da Remoção:</strong><br>';
            echo 'Categorias encontradas: ' . $stats['categories_found'] . '<br>';
            echo 'Posts processados: ' . $stats['posts_processed'] . '<br>';
            echo 'Posts atualizados: ' . $stats['posts_updated'] . '<br>';
            echo 'Categorias removidas: ' . $stats['categories_removed'] . '<br>';
            echo 'Termos deletados: ' . $stats['terms_deleted'] . '<br>';
            if ($stats['errors'] > 0) {
                echo 'Erros: ' . $stats['errors'] . '<br>';
            }
            echo '</p></div>';
            
            // Mostrar detalhes se houver
            if (!empty($stats['details']) && count($stats['details']) <= 50) {
                echo '<div class="card"><h3>Detalhes das Remoções</h3>';
                echo '<table class="widefat">';
                echo '<thead><tr><th>Post ID</th><th>Erro</th></tr></thead>';
                echo '<tbody>';
                foreach ($stats['details'] as $detail) {
                    echo '<tr>';
                    $post_id = isset($detail['post_id']) ? $detail['post_id'] : 'N/A';
                    $error = isset($detail['error']) ? $detail['error'] : 'Erro desconhecido';
                    if ($post_id !== 'N/A' && $post_id) {
                        echo '<td><a href="' . get_edit_post_link($post_id) . '" target="_blank">' . esc_html($post_id) . '</a></td>';
                    } else {
                        echo '<td>' . esc_html($post_id) . '</td>';
                    }
                    echo '<td><span style="color: red;">' . esc_html($error) . '</span></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Normalizar Categorias para Modalidade</h1>
        
        <div class="card">
            <h2>Migrar Filhos de "Bicicleta" para Taxonomia "Modalidade"</h2>
            <p>Este script migra os filhos da categoria "Bicicleta" (BMX, Cargueira, Chopper, etc.) para a taxonomia "modalidade":</p>
            <ul>
                <li><strong>Categoria "Bicicleta" → Filhos</strong>: BMX, Cargueira, Chopper, Custom, Dobrável, Downhill, Elétrica, Equilibrio, Freerider, Gavel, Gravel, Infantil, Mountain Bike (MTB), Speed, Triathlon, Urbana, Vintage</li>
                <li><strong>Taxonomia "modalidade"</strong>: Cada filho será criado como um termo na taxonomia "modalidade"</li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca o termo "Bicicleta" na taxonomia "category"</li>
                <li>Busca todos os filhos de "Bicicleta"</li>
                <li>Para cada filho:
                    <ul>
                        <li>Cria ou busca o termo correspondente na taxonomia "modalidade"</li>
                        <li>Busca todos os posts que têm essa categoria filha</li>
                        <li>Associa o termo de modalidade a esses posts</li>
                        <li>Opcionalmente, remove a categoria filha dos posts (se marcado)</li>
                    </ul>
                </li>
            </ol>
            
            <?php if ($already_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização já foi executada anteriormente.</p>
                    <?php if ($normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($category_terms_removed): ?>
                        <p><strong>Categorias:</strong> Removidas dos posts após migração.</p>
                    <?php else: ?>
                        <p><strong>Categorias:</strong> Mantidas nos posts (não foram removidas).</p>
                    <?php endif; ?>
                    <?php if ($last_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $last_stats['posts_updated']; ?> posts atualizados de <?php echo $last_stats['posts_processed']; ?> processados.
                            <?php echo $last_stats['categories_found']; ?> categorias encontradas.
                            <?php echo $last_stats['modalidade_terms_created']; ?> termos de modalidade criados.
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
                <?php wp_nonce_field('bazar_normalize_category_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force" value="1" <?php checked($already_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado anteriormente)
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="remove_category_terms" value="1">
                        Remover categorias filhas dos posts após migração (recomendado apenas após verificar que a migração foi bem-sucedida)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize_category" class="button button-primary button-large">
                        Executar Normalização
                    </button>
                </p>
            </form>
            
            <hr>
            <h3>Avisos Importantes</h3>
            <ul>
                <li><strong>Backup:</strong> Recomenda-se fazer backup do banco de dados antes de executar qualquer script.</li>
                <li><strong>Categorias:</strong> Por padrão, as categorias filhas são mantidas nos posts. Marque a opção acima para removê-las após verificar que a migração foi bem-sucedida.</li>
                <li><strong>Termos:</strong> Se um termo não existir na taxonomia "modalidade", ele será criado automaticamente.</li>
                <li><strong>Duplicatas:</strong> O script evita criar termos duplicados e não adiciona termos de modalidade duplicados aos posts.</li>
            </ul>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Remover e Deletar Categorias Filhas</h2>
            <p>Esta função remove as categorias filhas de "Bicicleta" (BMX, Cargueira, Chopper, etc.) dos posts e <strong>deleta os termos</strong> da taxonomia 'category'.</p>
            <p><strong>Use esta opção se:</strong></p>
            <ul>
                <li>A migração para modalidade já foi feita anteriormente</li>
                <li>As categorias filhas ainda estão associadas aos posts</li>
                <li>Você quer limpar as associações antigas e deletar os termos</li>
            </ul>
            <p><strong>Atenção:</strong> Esta função irá <strong>deletar permanentemente</strong> os termos filhos de "Bicicleta" da taxonomia 'category'. Certifique-se de que a migração para modalidade foi bem-sucedida antes de executar.</p>
            
            <?php
            $children_removed = get_option('bazar_category_children_removed');
            $children_removed_date = get_option('bazar_category_children_removed_date');
            $children_removed_stats = get_option('bazar_category_children_removed_stats');
            ?>
            
            <?php if ($children_removed): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A remoção de categorias filhas já foi executada anteriormente.</p>
                    <?php if ($children_removed_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($children_removed_date); ?></p>
                    <?php endif; ?>
                    <?php if ($children_removed_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $children_removed_stats['posts_updated']; ?> posts atualizados de <?php echo $children_removed_stats['posts_processed']; ?> processados.
                            <?php echo $children_removed_stats['categories_removed']; ?> categorias removidas.
                            <?php echo isset($children_removed_stats['terms_deleted']) ? $children_removed_stats['terms_deleted'] : 0; ?> termos deletados.
                        </p>
                    <?php endif; ?>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A remoção de categorias filhas ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('bazar_remove_category_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force_remove" value="1" <?php checked($children_removed); ?>>
                        Forçar execução (executar mesmo se já foi executado anteriormente)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_remove_categories" class="button button-secondary button-large">
                        Remover Categorias Filhas dos Posts
                    </button>
                </p>
            </form>
            
            <hr>
            <h3>Avisos Importantes</h3>
            <ul>
                <li><strong>Backup:</strong> Recomenda-se fazer backup do banco de dados antes de executar qualquer script.</li>
                <li><strong>Atenção:</strong> Esta função remove permanentemente as associações das categorias filhas de "Bicicleta" dos posts.</li>
                <li><strong>Recomendação:</strong> Execute esta função apenas após confirmar que a migração para modalidade foi bem-sucedida.</li>
            </ul>
        </div>
    </div>
    <?php
}
?>

