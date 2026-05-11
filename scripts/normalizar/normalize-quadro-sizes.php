<?php
/**
 * Script para normalizar tamanhos de quadro dentro da taxonomia 'componente'
 * 
 * Normaliza tamanhos antigos (ex: "13'(33cm)", "17 (43cm)") para o padrão simplificado (ex: "13", "17")
 * 
 * IMPORTANTE: Execute este script apenas UMA VEZ através do painel do WordPress
 * ou via WP-CLI. Para executar via painel, adicione temporariamente no functions.php:
 * require_once get_template_directory() . '/scripts/normalize-quadro-sizes.php';
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normaliza o nome de um tamanho de quadro antigo para o padrão novo
 * 
 * @param string $old_name Nome antigo (ex: "13'(33cm)", "17 (43cm)", "23+(57cm-63cm)")
 * @return string Nome normalizado (ex: "13", "17", "23+")
 */
function bazar_normalize_quadro_size($old_name) {
    // Remover espaços e caracteres especiais, extrair número inicial
    $cleaned = trim($old_name);
    
    // Caso especial: 23+
    if (preg_match('/^23\+/i', $cleaned)) {
        return '23+';
    }
    
    // Extrair número inicial (13, 14, 15, 17, 19, 20, 21, 22, 23)
    if (preg_match('/^(\d+)/', $cleaned, $matches)) {
        $number = intval($matches[1]);
        
        // Mapear para os tamanhos padrão
        $mapping = array(
            13 => '13',
            14 => '15',  // 14 mapeia para 15 (mais próximo)
            15 => '15',
            17 => '17',
            19 => '19',
            20 => '21',  // 20 mapeia para 21 (mais próximo)
            21 => '21',
            22 => '23',  // 22 mapeia para 23 (mais próximo)
            23 => '23'
        );
        
        if (isset($mapping[$number])) {
            return $mapping[$number];
        }
    }
    
    // Se não conseguir normalizar, retornar o nome original limpo
    return $cleaned;
}

/**
 * Função para normalizar tamanhos de quadro antigos para o padrão simplificado
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_quadro_sizes($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_quadro_sizes_normalized')) {
        return array(
            'success' => true,
            'message' => 'A normalização de tamanhos de quadro já foi executada anteriormente. Marque a opção "Forçar execução" para executar novamente.',
            'stats' => get_option('bazar_quadro_sizes_normalized_stats', array())
        );
    }
    
    $stats = array(
        'old_terms_found' => 0,
        'old_terms_processed' => 0,
        'new_terms_created' => 0,
        'new_terms_existing' => 0,
        'posts_migrated' => 0,
        'posts_processed' => 0,
        'terms_renamed' => 0,
        'terms_deleted' => 0,
        'errors' => 0,
        'skipped' => 0,
        'details' => array()
    );
    
    // Buscar o termo pai "Quadro" na taxonomia "componente" DINAMICAMENTE
    $quadro_term = get_term_by('name', 'Quadro', 'componente');
    
    if (!$quadro_term || is_wp_error($quadro_term)) {
        return array(
            'success' => false,
            'message' => 'Erro: Termo pai "Quadro" não encontrado na taxonomia "componente".',
            'stats' => $stats
        );
    }
    
    $quadro_parent_id = $quadro_term->term_id;
    error_log('[Bazar Normalize Quadro Sizes] Termo pai "Quadro" encontrado (ID: ' . $quadro_parent_id . ')');
    
    // Buscar todos os filhos de "Quadro"
    $quadro_children = get_terms(array(
        'taxonomy' => 'componente',
        'parent' => $quadro_parent_id,
        'hide_empty' => false
    ));
    
    if (is_wp_error($quadro_children) || empty($quadro_children)) {
        error_log('[Bazar Normalize Quadro Sizes] Aviso: Nenhum filho encontrado para "Quadro"');
        return array(
            'success' => true,
            'message' => 'Nenhum filho encontrado para o componente "Quadro". Nada a normalizar.',
            'stats' => $stats
        );
    }
    
    $stats['old_terms_found'] = count($quadro_children);
    error_log('[Bazar Normalize Quadro Sizes] Encontrados ' . count($quadro_children) . ' termos filhos de "Quadro"');
    
    // Tamanhos padrão esperados
    $standard_sizes = array('13', '15', '17', '19', '21', '23', '23+');
    
    // Criar mapeamento: tamanho normalizado => termo existente ou null
    $normalized_terms_map = array();
    foreach ($standard_sizes as $size) {
        $term = get_term_by('name', $size, 'componente');
        if ($term && !is_wp_error($term) && intval($term->parent) === intval($quadro_parent_id)) {
            $normalized_terms_map[$size] = $term;
        } else {
            $normalized_terms_map[$size] = null;
        }
    }
    
    // Processar cada termo filho
    foreach ($quadro_children as $old_term) {
        $old_name = $old_term->name;
        $old_id = $old_term->term_id;
        $old_slug = $old_term->slug;
        
        // Normalizar o nome
        $normalized_name = bazar_normalize_quadro_size($old_name);
        
        // Se o nome já está normalizado, pular
        if ($old_name === $normalized_name) {
            $stats['skipped']++;
            error_log('[Bazar Normalize Quadro Sizes] Termo "' . $old_name . '" já está normalizado. Pulando.');
            continue;
        }
        
        $stats['old_terms_processed']++;
        error_log('[Bazar Normalize Quadro Sizes] Normalizando: "' . $old_name . '" → "' . $normalized_name . '"');
        
        // Verificar se o termo normalizado já existe
        $new_term = isset($normalized_terms_map[$normalized_name]) ? $normalized_terms_map[$normalized_name] : null;
        
        if (!$new_term) {
            // Criar novo termo normalizado
            $insert_result = wp_insert_term(
                $normalized_name,
                'componente',
                array(
                    'slug' => sanitize_title($normalized_name),
                    'parent' => $quadro_parent_id
                )
            );
            
            if (is_wp_error($insert_result)) {
                $stats['errors']++;
                error_log('[Bazar Normalize Quadro Sizes] Erro ao criar termo "' . $normalized_name . '": ' . $insert_result->get_error_message());
                $stats['details'][] = array(
                    'old_name' => $old_name,
                    'old_id' => $old_id,
                    'normalized_name' => $normalized_name,
                    'error' => 'Erro ao criar termo: ' . $insert_result->get_error_message()
                );
                continue;
            }
            
            $new_term_id = $insert_result['term_id'];
            $new_term = get_term($new_term_id, 'componente');
            $normalized_terms_map[$normalized_name] = $new_term;
            $stats['new_terms_created']++;
            error_log('[Bazar Normalize Quadro Sizes] Termo criado: "' . $normalized_name . '" (ID: ' . $new_term_id . ')');
        } else {
            $new_term_id = $new_term->term_id;
            $stats['new_terms_existing']++;
            error_log('[Bazar Normalize Quadro Sizes] Termo já existe: "' . $normalized_name . '" (ID: ' . $new_term_id . ')');
        }
        
        // Buscar todos os posts que têm o termo antigo
        $posts = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'tax_query' => array(
                array(
                    'taxonomy' => 'componente',
                    'field' => 'term_id',
                    'terms' => $old_id
                )
            )
        ));
        
        $posts_migrated_for_term = 0;
        $post_details = array();
        
        if (!empty($posts)) {
            error_log('[Bazar Normalize Quadro Sizes] Encontrados ' . count($posts) . ' posts com o termo antigo "' . $old_name . '"');
            
            // Migrar posts do termo antigo para o termo novo
            foreach ($posts as $post) {
                $post_id = $post->ID;
                $stats['posts_processed']++;
                
                // Obter termos de componente atuais do post
                $current_componentes = wp_get_object_terms($post_id, 'componente', array('fields' => 'ids'));
                
                if (is_wp_error($current_componentes)) {
                    $stats['errors']++;
                    error_log('[Bazar Normalize Quadro Sizes] Erro ao obter componentes do post ID ' . $post_id);
                    continue;
                }
                
                // Se o post já tem o termo novo, remover apenas o antigo
                if (in_array($new_term_id, $current_componentes)) {
                    // Remover apenas o termo antigo
                    $updated_componentes = array_diff($current_componentes, array($old_id));
                    $updated_componentes = array_values($updated_componentes);
                    
                    if (count($updated_componentes) < count($current_componentes)) {
                        wp_set_object_terms($post_id, $updated_componentes, 'componente', false);
                        $posts_migrated_for_term++;
                        $stats['posts_migrated']++;
                    }
                } else {
                    // Adicionar o termo novo e remover o antigo
                    $updated_componentes = array_diff($current_componentes, array($old_id));
                    $updated_componentes[] = $new_term_id;
                    $updated_componentes = array_values(array_unique($updated_componentes));
                    
                    wp_set_object_terms($post_id, $updated_componentes, 'componente', false);
                    $posts_migrated_for_term++;
                    $stats['posts_migrated']++;
                }
            }
        } else {
            error_log('[Bazar Normalize Quadro Sizes] Nenhum post encontrado com o termo antigo "' . $old_name . '"');
        }
        
        // Se não há mais posts associados ao termo antigo, deletar o termo
        $remaining_posts = get_objects_in_term($old_id, 'componente');
        if (is_wp_error($remaining_posts)) {
            error_log('[Bazar Normalize Quadro Sizes] Erro ao verificar posts restantes do termo "' . $old_name . '"');
        } elseif (empty($remaining_posts) || (is_array($remaining_posts) && count($remaining_posts) === 0)) {
            // Deletar o termo antigo
            $delete_result = wp_delete_term($old_id, 'componente');
            if (is_wp_error($delete_result)) {
                error_log('[Bazar Normalize Quadro Sizes] Erro ao deletar termo antigo "' . $old_name . '": ' . $delete_result->get_error_message());
            } else {
                $stats['terms_deleted']++;
                error_log('[Bazar Normalize Quadro Sizes] Termo antigo deletado: "' . $old_name . '" (ID: ' . $old_id . ')');
            }
        } else {
            error_log('[Bazar Normalize Quadro Sizes] Termo antigo "' . $old_name . '" ainda tem ' . count($remaining_posts) . ' post(s) associado(s). Mantendo termo.');
        }
        
        // Adicionar detalhes
        $stats['details'][] = array(
            'old_name' => $old_name,
            'old_id' => $old_id,
            'normalized_name' => $normalized_name,
            'new_term_id' => $new_term_id,
            'posts_found' => count($posts),
            'posts_migrated' => $posts_migrated_for_term
        );
    }
    
    // Marcar como executado
    update_option('bazar_quadro_sizes_normalized', true);
    update_option('bazar_quadro_sizes_normalized_date', current_time('mysql'));
    update_option('bazar_quadro_sizes_normalized_stats', $stats);
    
    return array(
        'success' => true,
        'message' => 'Normalização de tamanhos de quadro concluída com sucesso!',
        'stats' => $stats
    );
}

// Adicionar ao menu admin
function bazar_add_normalize_quadro_sizes_menu() {
    add_management_page(
        'Normalizar Tamanhos de Quadro',
        'Normalizar Quadros',
        'manage_options',
        'bazar-normalize-quadro-sizes',
        'bazar_normalize_quadro_sizes_page'
    );
}
add_action('admin_menu', 'bazar_add_normalize_quadro_sizes_menu');

// Página de administração para normalizar quadros
function bazar_normalize_quadro_sizes_page() {
    $already_normalized = get_option('bazar_quadro_sizes_normalized');
    $normalized_date = get_option('bazar_quadro_sizes_normalized_date');
    $last_stats = get_option('bazar_quadro_sizes_normalized_stats');
    
    // Executar normalização
    if (isset($_POST['bazar_execute_normalize_quadro_sizes']) && check_admin_referer('bazar_normalize_quadro_sizes_action')) {
        $force = isset($_POST['force']) && $_POST['force'] == '1';
        
        $result = bazar_normalize_quadro_sizes($force);
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Estatísticas:</strong><br>';
            echo 'Termos antigos encontrados: ' . $stats['old_terms_found'] . '<br>';
            echo 'Termos antigos processados: ' . $stats['old_terms_processed'] . '<br>';
            echo 'Termos novos criados: ' . $stats['new_terms_created'] . '<br>';
            echo 'Termos novos existentes: ' . $stats['new_terms_existing'] . '<br>';
            echo 'Posts processados: ' . $stats['posts_processed'] . '<br>';
            echo 'Posts migrados: ' . $stats['posts_migrated'] . '<br>';
            echo 'Termos deletados: ' . $stats['terms_deleted'] . '<br>';
            echo 'Termos ignorados: ' . $stats['skipped'] . '<br>';
            if ($stats['errors'] > 0) {
                echo 'Erros: ' . $stats['errors'] . '<br>';
            }
            echo '</p></div>';
            
            // Mostrar detalhes se houver
            if (!empty($stats['details']) && count($stats['details']) <= 50) {
                echo '<div class="card"><h3>Detalhes das Normalizações</h3>';
                echo '<table class="widefat">';
                echo '<thead><tr><th>Nome Antigo</th><th>Nome Normalizado</th><th>Novo Termo ID</th><th>Posts Encontrados</th><th>Posts Migrados</th></tr></thead>';
                echo '<tbody>';
                foreach ($stats['details'] as $detail) {
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($detail['old_name']) . '</strong><br><small>ID: ' . $detail['old_id'] . '</small></td>';
                    echo '<td>' . esc_html($detail['normalized_name']) . '</td>';
                    echo '<td>' . $detail['new_term_id'] . '</td>';
                    echo '<td>' . $detail['posts_found'] . '</td>';
                    echo '<td>' . $detail['posts_migrated'] . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            } elseif (!empty($stats['details'])) {
                echo '<div class="notice notice-info"><p>Total de ' . count($stats['details']) . ' termos processados. Detalhes completos disponíveis no log do WordPress.</p></div>';
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Normalizar Tamanhos de Quadro</h1>
        
        <div class="card">
            <h2>Normalizar Tamanhos de Quadro para Padrão Simplificado</h2>
            <p>Este script normaliza os tamanhos de quadro antigos (ex: "13'(33cm)", "17 (43cm)") para o padrão simplificado (ex: "13", "17"):</p>
            <ul>
                <li><strong>Padrão Antigo:</strong> "13'(33cm)", "14 (35cm)", "17 (43cm)", "19 (48cm)", "20 (52cm)", "21 (54cm)", "22 (56cm)", "23+(57cm-63cm)"</li>
                <li><strong>Padrão Novo:</strong> "13", "15", "17", "19", "21", "23", "23+"</li>
                <li><strong>Mapeamento:</strong> 14→15, 20→21, 22→23 (mais próximo)</li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca o termo pai "Quadro" na taxonomia "componente" dinamicamente</li>
                <li>Busca todos os termos filhos de "Quadro"</li>
                <li>Para cada termo antigo:
                    <ul>
                        <li>Normaliza o nome extraindo apenas o número inicial</li>
                        <li>Mapeia para o tamanho padrão correspondente</li>
                        <li>Se o termo normalizado não existe, cria-o</li>
                        <li>Migra todos os posts do termo antigo para o termo novo</li>
                        <li>Se o termo antigo não tem mais posts, deleta-o</li>
                    </ul>
                </li>
            </ol>
            
            <?php if ($already_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização já foi executada anteriormente.</p>
                    <?php if ($normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($last_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $last_stats['posts_migrated']; ?> posts migrados de <?php echo $last_stats['posts_processed']; ?> processados.
                            <?php echo $last_stats['old_terms_processed']; ?> termos processados.
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
                <?php wp_nonce_field('bazar_normalize_quadro_sizes_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force" value="1" <?php checked($already_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado anteriormente)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize_quadro_sizes" class="button button-primary button-large">
                        Executar Normalização de Quadros
                    </button>
                </p>
            </form>
            
            <hr>
            <h3>Avisos Importantes</h3>
            <ul>
                <li><strong>Backup:</strong> Recomenda-se fazer backup do banco de dados antes de executar qualquer script.</li>
                <li><strong>Mapeamento:</strong> Tamanhos intermediários (14, 20, 22) são mapeados para o tamanho padrão mais próximo.</li>
                <li><strong>Termos:</strong> Termos antigos sem posts associados serão deletados automaticamente.</li>
                <li><strong>Duplicatas:</strong> O script evita criar termos duplicados e migra posts corretamente.</li>
            </ul>
        </div>
    </div>
    <?php
}
?>

