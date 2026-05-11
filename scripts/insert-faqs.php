<?php
/**
 * Script para inserir FAQs do JSON no CPT 'faq'
 * 
 * IMPORTANTE: Execute este script através do painel do WordPress
 * ou via WP-CLI. Para executar via painel, adicione temporariamente no functions.php:
 * require_once get_template_directory() . '/scripts/insert-faqs.php';
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Função para inserir FAQs do JSON no CPT 'faq'
 * 
 * @param bool $force Se true, atualiza FAQs existentes
 * @return array Resultado da inserção
 */
function bazar_insert_faqs_from_json($force = false) {
    // Verificar nome do arquivo - pode ser faqs.json ou taxonomias-faqs.json
    $json_file = get_template_directory() . '/scripts/taxonomias-faqs.json';
    if (!file_exists($json_file)) {
        $json_file = get_template_directory() . '/scripts/faqs.json';
    }
    
    if (!file_exists($json_file)) {
        error_log('[Bazar Insert FAQs] Arquivo JSON não encontrado. Procurou em: ' . $json_file);
        return array(
            'success' => false,
            'message' => 'Arquivo JSON não encontrado. Verifique se existe taxonomias-faqs.json ou faqs.json em scripts/',
            'stats' => array()
        );
    }
    
    $json_content = file_get_contents($json_file);
    $taxonomies_data = json_decode($json_content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Bazar Insert FAQs] Erro ao decodificar JSON: ' . json_last_error_msg());
        return array(
            'success' => false,
            'message' => 'Erro ao decodificar JSON: ' . json_last_error_msg(),
            'stats' => array()
        );
    }
    
    $stats = array(
        'faqs_created' => 0,
        'faqs_updated' => 0,
        'faqs_skipped' => 0,
        'errors' => 0,
        'terms_not_found' => 0
    );
    
    // Processar cada taxonomia do JSON (category, modalidade, componente, acessorio, marca-modelo, cidade)
    foreach ($taxonomies_data as $taxonomy_slug => $terms_data) {
        if (!is_array($terms_data)) {
            continue;
        }
        
        // Processar cada termo da taxonomia
        // Para "cidade": cada item é uma cidade (termo filho do estado); o nome deve existir na taxonomia cidade
        foreach ($terms_data as $term_data) {
            if (!isset($term_data['nome'])) {
                continue;
            }
            
            $term_name = trim($term_data['nome']);
            if (empty($term_name)) {
                continue;
            }
            
            // Buscar termo existente (para cidade: termo filho com nome da cidade, ex. "Brasília", "Belo Horizonte")
            $term = get_term_by('name', $term_name, $taxonomy_slug);
            if (!$term || is_wp_error($term)) {
                // Termo não existe ainda; pular (para cidade: criar termos a partir de taxonomias-cidade.json antes)
                $stats['terms_not_found']++;
                continue;
            }
            
            // Processar FAQs do termo
            if (isset($term_data['faq']) && is_array($term_data['faq']) && !empty($term_data['faq'])) {
                foreach ($term_data['faq'] as $index => $faq_item) {
                    // Validar FAQ
                    if (empty($faq_item['pergunta']) || empty($faq_item['resposta'])) {
                        $stats['faqs_skipped']++;
                        continue; // FAQ vazio, pular
                    }
                    
                    // Verificar se FAQ já existe
                    $existing_faq_id = bazar_find_existing_faq(
                        $faq_item['pergunta'],
                        $taxonomy_slug,
                        $term->term_id
                    );
                    
                    if ($existing_faq_id && !$force) {
                        // FAQ já existe e não é para forçar atualização
                        $stats['faqs_skipped']++;
                        continue;
                    } elseif ($existing_faq_id && $force) {
                        // Atualizar FAQ existente
                        $result = bazar_update_faq_post($existing_faq_id, $faq_item, $taxonomy_slug, $term->term_id, $index);
                        if ($result) {
                            $stats['faqs_updated']++;
                        } else {
                            $stats['errors']++;
                        }
                    } else {
                        // Criar novo FAQ
                        $faq_post_id = bazar_create_faq_post($faq_item, $taxonomy_slug, $term->term_id, $index);
                        
                        if ($faq_post_id && !is_wp_error($faq_post_id)) {
                            $stats['faqs_created']++;
                        } else {
                            $stats['errors']++;
                            if (is_wp_error($faq_post_id)) {
                                error_log('[Bazar Insert FAQs] Erro ao criar FAQ: ' . $faq_post_id->get_error_message());
                            }
                        }
                    }
                }
            }
        }
    }
    
    return array(
        'success' => true,
        'message' => 'FAQs inseridos com sucesso!',
        'stats' => $stats
    );
}

/**
 * Buscar FAQ existente baseado na pergunta, taxonomia e termo
 * 
 * @param string $pergunta Pergunta do FAQ
 * @param string $taxonomy_slug Slug da taxonomia
 * @param int $term_id ID do termo
 * @return int|false ID do post FAQ ou false se não encontrado
 */
function bazar_find_existing_faq($pergunta, $taxonomy_slug, $term_id) {
    $args = array(
        'post_type' => 'faq',
        'posts_per_page' => 1,
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => 'faq_pergunta',
                'value' => $pergunta,
                'compare' => '='
            )
        ),
        'tax_query' => array(
            array(
                'taxonomy' => $taxonomy_slug,
                'field' => 'term_id',
                'terms' => $term_id
            )
        )
    );
    
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        return $query->posts[0]->ID;
    }
    
    return false;
}

/**
 * Criar post FAQ no CPT
 * 
 * @param array $faq_item Array com 'pergunta' e 'resposta'
 * @param string $taxonomy_slug Slug da taxonomia
 * @param int $term_id ID do termo
 * @param int $ordem Ordem do FAQ (índice no array)
 * @return int|WP_Error ID do post criado ou WP_Error em caso de erro
 */
function bazar_create_faq_post($faq_item, $taxonomy_slug, $term_id, $ordem = 0) {
    // Criar título único baseado na pergunta (primeiras 10 palavras)
    $title = wp_trim_words($faq_item['pergunta'], 10, '...');
    
    $post_data = array(
        'post_title' => sanitize_text_field($title),
        'post_content' => '',
        'post_status' => 'publish',
        'post_type' => 'faq',
        'post_author' => 1
    );
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        return $post_id;
    }
    
    // Associar à taxonomia
    $tax_result = wp_set_object_terms($post_id, array($term_id), $taxonomy_slug);
    if (is_wp_error($tax_result)) {
        error_log('[Bazar Insert FAQs] Erro ao associar taxonomia: ' . $tax_result->get_error_message());
    }
    
    // Salvar meta fields
    update_post_meta($post_id, 'faq_pergunta', sanitize_text_field($faq_item['pergunta']));
    update_post_meta($post_id, 'faq_resposta', wp_kses_post($faq_item['resposta']));
    update_post_meta($post_id, 'faq_ordem', intval($ordem));
    update_post_meta($post_id, 'faq_ativo', true);
    update_post_meta($post_id, 'faq_taxonomy_source', $taxonomy_slug);
    update_post_meta($post_id, 'faq_term_source', $term_id);
    
    // Limpar cache após criação
    bazar_clear_faqs_cache($post_id, $taxonomy_slug, $term_id);
    
    return $post_id;
}

/**
 * Limpar cache de FAQs quando um FAQ é atualizado/criado/deletado
 * 
 * @param int $post_id ID do post FAQ
 * @param string $taxonomy Nome da taxonomia (opcional)
 * @param int $term_id ID do termo (opcional)
 */
function bazar_clear_faqs_cache($post_id = null, $taxonomy = null, $term_id = null) {
    $cache_group = 'bazar_faqs';
    
    // Se temos informações específicas, limpar apenas esse cache
    if ($taxonomy && $term_id) {
        $cache_key = 'bazar_faqs_' . $taxonomy . '_' . $term_id;
        wp_cache_delete($cache_key, $cache_group);
        
        // Se o termo tem pai, limpar cache do pai também
        $term = get_term($term_id, $taxonomy);
        if ($term && !is_wp_error($term) && $term->parent > 0) {
            $parent_cache_key = 'bazar_faqs_' . $taxonomy . '_' . $term->parent;
            wp_cache_delete($parent_cache_key, $cache_group);
        }
    } else {
        // Limpar todo o grupo de cache de FAQs (mais agressivo)
        // Nota: wp_cache_flush_group não existe nativamente, então limpamos manualmente
        // Em produção, você pode usar um plugin de cache ou implementar um sistema de tags
    }
}

/**
 * Atualizar post FAQ existente
 * 
 * @param int $post_id ID do post FAQ
 * @param array $faq_item Array com 'pergunta' e 'resposta'
 * @param string $taxonomy_slug Slug da taxonomia
 * @param int $term_id ID do termo
 * @param int $ordem Ordem do FAQ
 * @return bool true se atualizado com sucesso
 */
function bazar_update_faq_post($post_id, $faq_item, $taxonomy_slug, $term_id, $ordem = 0) {
    // Atualizar título se necessário
    $title = wp_trim_words($faq_item['pergunta'], 10, '...');
    wp_update_post(array(
        'ID' => $post_id,
        'post_title' => sanitize_text_field($title)
    ));
    
    // Atualizar meta fields
    update_post_meta($post_id, 'faq_pergunta', sanitize_text_field($faq_item['pergunta']));
    update_post_meta($post_id, 'faq_resposta', wp_kses_post($faq_item['resposta']));
    update_post_meta($post_id, 'faq_ordem', intval($ordem));
    update_post_meta($post_id, 'faq_taxonomy_source', $taxonomy_slug);
    update_post_meta($post_id, 'faq_term_source', $term_id);
    
    // Garantir que está associado à taxonomia correta
    $current_terms = wp_get_object_terms($post_id, $taxonomy_slug, array('fields' => 'ids'));
    if (!in_array($term_id, $current_terms)) {
        wp_set_object_terms($post_id, array($term_id), $taxonomy_slug, true);
    }
    
    // Limpar cache após atualização
    bazar_clear_faqs_cache($post_id, $taxonomy_slug, $term_id);
    
    return true;
}

/**
 * Função para executar a inserção de FAQs
 * 
 * @param bool $force Se true, atualiza FAQs existentes
 * @return array Resultado da inserção
 */
function bazar_run_insert_faqs($force = false) {
    // Verificar se já foi executado (evitar executar múltiplas vezes, a menos que force=true)
    if (!$force && get_option('bazar_faqs_inserted')) {
        $stats = get_option('bazar_faqs_inserted_stats', array());
        return array(
            'success' => true,
            'message' => 'Os FAQs já foram inseridos anteriormente. Marque "Forçar atualização" para executar novamente.',
            'stats' => $stats
        );
    }
    
    // Inserir FAQs do JSON
    $result_faqs = bazar_insert_faqs_from_json($force);
    
    // Verificar se o resultado é válido
    if (!is_array($result_faqs) || !isset($result_faqs['success'])) {
        return array(
            'success' => false,
            'message' => 'Erro: A função de inserção não retornou um resultado válido.',
            'stats' => array()
        );
    }
    
    if ($result_faqs['success']) {
        if (isset($result_faqs['stats'])) {
            error_log('[Bazar Insert FAQs] FAQs inseridos: ' . 
                $result_faqs['stats']['faqs_created'] . ' criados, ' . 
                $result_faqs['stats']['faqs_updated'] . ' atualizados, ' . 
                $result_faqs['stats']['faqs_skipped'] . ' ignorados.');
        }
    } else {
        error_log('[Bazar Insert FAQs] Erro ao inserir FAQs: ' . $result_faqs['message']);
    }
    
    // Marcar como executado
    update_option('bazar_faqs_inserted', true);
    update_option('bazar_faqs_inserted_date', current_time('mysql'));
    update_option('bazar_faqs_inserted_stats', $result_faqs['stats'] ?? array());
    
    error_log('[Bazar Insert FAQs] Inserção de FAQs concluída.');
    
    return $result_faqs;
}

// Adicionar página no menu admin para executar o script
function bazar_add_insert_faqs_menu() {
    add_management_page(
        'Inserir FAQs',
        'Inserir FAQs',
        'manage_options',
        'bazar-insert-faqs',
        'bazar_insert_faqs_page'
    );
}
add_action('admin_menu', 'bazar_add_insert_faqs_menu');

// Página de administração para inserir FAQs
function bazar_insert_faqs_page() {
    // Executar inserção de FAQs do JSON
    if (isset($_POST['bazar_insert_faqs']) && check_admin_referer('bazar_insert_faqs_action')) {
        $force = isset($_POST['force_faqs']) && $_POST['force_faqs'] == '1';
        
        // Limpar flag se forçar
        if ($force) {
            delete_option('bazar_faqs_inserted');
        }
        
        $result = bazar_run_insert_faqs($force);
        
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
            echo 'FAQs criados: ' . $stats['faqs_created'] . '<br>';
            echo 'FAQs atualizados: ' . $stats['faqs_updated'] . '<br>';
            echo 'FAQs ignorados: ' . $stats['faqs_skipped'] . '<br>';
            if (!empty($stats['terms_not_found'])) {
                echo 'Termos não encontrados (sem FAQ inserido): ' . $stats['terms_not_found'] . ' <em>— verifique se os termos da taxonomia (ex.: cidade) já existem no WordPress</em><br>';
            }
            if ($stats['errors'] > 0) {
                echo 'Erros: ' . $stats['errors'] . '<br>';
            }
            echo '</p></div>';
        }
    }
    
    $faqs_inserted = get_option('bazar_faqs_inserted');
    $faqs_date = get_option('bazar_faqs_inserted_date');
    $faqs_stats = get_option('bazar_faqs_inserted_stats');
    ?>
    <div class="wrap">
        <h1>Inserir FAQs do JSON</h1>
        
        <?php if ($faqs_inserted): ?>
            <div class="notice notice-info">
                <p><strong>Status:</strong> Os FAQs já foram inseridos.</p>
                <?php if ($faqs_date): ?>
                    <p><strong>Data:</strong> <?php echo esc_html($faqs_date); ?></p>
                <?php endif; ?>
                <?php if ($faqs_stats): ?>
                    <p><strong>Resultados da última execução:</strong></p>
                    <ul>
                        <li>FAQs criados: <?php echo (int) $faqs_stats['faqs_created']; ?></li>
                        <li>FAQs atualizados: <?php echo (int) $faqs_stats['faqs_updated']; ?></li>
                        <li>FAQs ignorados: <?php echo (int) $faqs_stats['faqs_skipped']; ?></li>
                        <?php if (!empty($faqs_stats['terms_not_found'])): ?>
                        <li>Termos não encontrados: <?php echo (int) $faqs_stats['terms_not_found']; ?></li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
                <p>Use a opção "Forçar atualização" abaixo para atualizar FAQs existentes.</p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p><strong>Status:</strong> Os FAQs ainda não foram inseridos. Execute manualmente através do botão abaixo.</p>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>O que este script faz?</h2>
            <p>Este script insere todos os FAQs do arquivo <code>taxonomias-faqs.json</code> (ou <code>faqs.json</code>) no WordPress como posts do CPT 'faq'.</p>
            
            <h3>Taxonomias processadas:</h3>
            <ul>
                <li><strong>Taxonomias:</strong> category, modalidade, componente, acessorio, marca-modelo, cidade</li>
                <li><strong>Cidade:</strong> cada item em <code>cidade</code> no JSON é uma cidade (ex. Brasília, Belo Horizonte). Os termos com esses nomes devem já existir na taxonomia <code>cidade</code> (geralmente criados a partir de taxonomias-cidade.json).</li>
                <li><strong>Recursos:</strong> Cria posts no CPT 'faq', associa às taxonomias correspondentes, mantém ordem e metadados</li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Lê o arquivo <code>taxonomias-faqs.json</code> (ou <code>faqs.json</code>) do diretório de scripts</li>
                <li>Processa cada taxonomia e seus termos</li>
                <li>Para cada termo, processa os FAQs do array 'faq'</li>
                <li>Cria posts no CPT 'faq' ou atualiza existentes</li>
                <li>Associa os FAQs às taxonomias correspondentes</li>
            </ol>
            
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('bazar_insert_faqs_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force_faqs" value="1">
                        Forçar atualização (executar mesmo se já foi executado anteriormente e atualizar FAQs existentes)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_insert_faqs" class="button button-primary button-large">
                        Inserir FAQs do JSON
                    </button>
                </p>
            </form>
        </div>
    </div>
    <?php
}

// Hooks para limpar cache quando FAQs são atualizados manualmente no WordPress
add_action('save_post_faq', 'bazar_clear_faqs_cache_on_save', 10, 3);
add_action('delete_post', 'bazar_clear_faqs_cache_on_delete', 10, 1);
add_action('wp_trash_post', 'bazar_clear_faqs_cache_on_delete', 10, 1);
add_action('untrash_post', 'bazar_clear_faqs_cache_on_delete', 10, 1);

/**
 * Limpar cache quando um FAQ é salvo/atualizado
 * 
 * @param int $post_id ID do post
 * @param WP_Post $post Objeto do post
 * @param bool $update Se é atualização ou criação
 */
function bazar_clear_faqs_cache_on_save($post_id, $post, $update) {
    // Verificar se é um post do tipo 'faq'
    if ($post->post_type !== 'faq') {
        return;
    }
    
    // Buscar termos associados ao FAQ
    $taxonomies = array('category', 'modalidade', 'componente', 'acessorio', 'marca-modelo', 'cidade');
    
    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term_id) {
                bazar_clear_faqs_cache($post_id, $taxonomy, $term_id);
            }
        }
    }
}

/**
 * Limpar cache quando um FAQ é deletado
 * 
 * @param int $post_id ID do post
 */
function bazar_clear_faqs_cache_on_delete($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'faq') {
        return;
    }
    
    // Buscar termos associados antes de deletar
    $taxonomies = array('category', 'modalidade', 'componente', 'acessorio', 'marca-modelo', 'cidade');
    
    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term_id) {
                bazar_clear_faqs_cache($post_id, $taxonomy, $term_id);
            }
        }
    }
}

