<?php
/**
 * Script para normalizar anúncios indeferidos para post_status = 'draft'
 * 
 * REGRA: Anúncios com motivos_para_indeferimento SEMPRE devem ter post_status = 'draft'
 * 
 * Este script migra anúncios antigos que possam estar em 'pending' ou outros status
 * mas que têm motivos de indeferimento, garantindo que todos usem 'draft'
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normaliza um anúncio indeferido para garantir que está em 'draft'
 * 
 * @param int $post_id ID do post
 * @return bool True se normalizado, false se não precisa normalização
 */
function bazar_normalize_indeferido_to_draft($post_id) {
    $post = get_post($post_id);
    
    if (!$post || $post->post_type !== 'post') {
        return false;
    }
    
    $motivos_indeferimento = get_field('motivos_para_indeferimento', $post_id);
    
    // Se há motivos de indeferimento, o status DEVE ser 'draft'
    if (!empty($motivos_indeferimento)) {
        $current_status = get_post_status($post_id);
        
        if ($current_status !== 'draft') {
            // Migrar para 'draft'
            $update_result = wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft'
            ));
            
            if (!is_wp_error($update_result) && $update_result) {
                error_log('[Bazar Normalize Indeferidos] Anúncio ID ' . $post_id . ' migrado de "' . $current_status . '" para "draft"');
                return true;
            } else {
                error_log('[Bazar Normalize Indeferidos] Erro ao migrar anúncio ID ' . $post_id . ': ' . (is_wp_error($update_result) ? $update_result->get_error_message() : 'wp_update_post retornou false'));
                return false;
            }
        }
    }
    
    return false; // Não precisa normalização
}

/**
 * Normaliza todos os anúncios indeferidos do banco de dados
 * 
 * @param bool $force Se true, força atualização mesmo se já foi executado
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_all_indeferidos_to_draft($force = false) {
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_indeferidos_normalized_to_draft')) {
        return array(
            'success' => true,
            'message' => 'A normalização de indeferidos já foi executada anteriormente. Marque "Forçar execução" para executar novamente.',
            'stats' => get_option('bazar_indeferidos_normalized_stats', array())
        );
    }
    
    $stats = array(
        'posts_found' => 0,
        'posts_migrated' => 0,
        'posts_already_draft' => 0,
        'errors' => 0
    );
    
    // Buscar todos os posts que têm motivos_para_indeferimento
    $args = array(
        'post_type' => 'post',
        'post_status' => 'any', // Buscar em todos os status
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'motivos_para_indeferimento',
                'compare' => 'EXISTS'
            )
        ),
        'fields' => 'ids'
    );
    
    $posts = get_posts($args);
    
    if (empty($posts)) {
        return array(
            'success' => true,
            'message' => 'Nenhum anúncio com motivos de indeferimento encontrado.',
            'stats' => $stats
        );
    }
    
    $stats['posts_found'] = count($posts);
    
    foreach ($posts as $post_id) {
        $motivos = get_field('motivos_para_indeferimento', $post_id);
        
        // Só processar se realmente tiver motivos
        if (empty($motivos)) {
            continue;
        }
        
        $current_status = get_post_status($post_id);
        
        if ($current_status === 'draft') {
            $stats['posts_already_draft']++;
            continue;
        }
        
        // Migrar para 'draft'
        $update_result = wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'draft'
        ));
        
        if (!is_wp_error($update_result) && $update_result) {
            $stats['posts_migrated']++;
            error_log('[Bazar Normalize Indeferidos] Anúncio ID ' . $post_id . ' migrado de "' . $current_status . '" para "draft"');
        } else {
            $stats['errors']++;
            error_log('[Bazar Normalize Indeferidos] Erro ao migrar anúncio ID ' . $post_id . ': ' . (is_wp_error($update_result) ? $update_result->get_error_message() : 'wp_update_post retornou false'));
        }
    }
    
    // Marcar como executado
    update_option('bazar_indeferidos_normalized_to_draft', true);
    update_option('bazar_indeferidos_normalized_date', current_time('mysql'));
    update_option('bazar_indeferidos_normalized_stats', $stats);
    
    return array(
        'success' => true,
        'message' => 'Normalização de indeferidos concluída com sucesso!',
        'stats' => $stats
    );
}

// Adicionar página no menu admin para executar o script
add_action('admin_menu', 'bazar_add_normalize_indeferidos_menu');
function bazar_add_normalize_indeferidos_menu() {
    add_management_page(
        'Normalizar Indeferidos para Draft',
        'Normalizar Indeferidos',
        'manage_options',
        'bazar-normalize-indeferidos',
        'bazar_normalize_indeferidos_page'
    );
}

// Página de administração para normalizar
function bazar_normalize_indeferidos_page() {
    $already_normalized = get_option('bazar_indeferidos_normalized_to_draft');
    $normalized_date = get_option('bazar_indeferidos_normalized_date');
    $last_stats = get_option('bazar_indeferidos_normalized_stats');
    
    // Executar normalização
    if (isset($_POST['bazar_execute_normalize_indeferidos']) && check_admin_referer('bazar_normalize_indeferidos_action')) {
        $force = isset($_POST['force']) && $_POST['force'] == '1';
        
        $result = bazar_normalize_all_indeferidos_to_draft($force);
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Estatísticas:</strong><br>';
            echo 'Anúncios encontrados com motivos de indeferimento: ' . $stats['posts_found'] . '<br>';
            echo 'Anúncios migrados para draft: ' . $stats['posts_migrated'] . '<br>';
            echo 'Anúncios já em draft: ' . $stats['posts_already_draft'] . '<br>';
            if ($stats['errors'] > 0) {
                echo 'Erros: ' . $stats['errors'] . '<br>';
            }
            echo '</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Normalizar Indeferidos para Draft</h1>
        
        <div class="card">
            <h2>O que este script faz?</h2>
            <p><strong>REGRA:</strong> Anúncios indeferidos/reprovados SEMPRE devem ter <code>post_status = 'draft'</code>.</p>
            <p>Este script garante que todos os anúncios com motivos de indeferimento estejam com status 'draft'.</p>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca todos os posts que têm o campo ACF <code>motivos_para_indeferimento</code> preenchido</li>
                <li>Verifica o status atual de cada anúncio</li>
                <li>Se o status não for 'draft', migra automaticamente para 'draft'</li>
                <li>Registra estatísticas da migração</li>
            </ol>
            
            <?php if ($already_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização já foi executada anteriormente.</p>
                    <?php if ($normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($last_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $last_stats['posts_migrated']; ?> anúncios migrados de <?php echo $last_stats['posts_found']; ?> encontrados.
                        </p>
                    <?php endif; ?>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A normalização ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('bazar_normalize_indeferidos_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force" value="1" <?php checked($already_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado anteriormente)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize_indeferidos" class="button button-primary button-large">
                        Executar Normalização
                    </button>
                </p>
            </form>
            
            <hr>
            <h3>Avisos Importantes</h3>
            <ul>
                <li><strong>Backup:</strong> Recomenda-se fazer backup do banco de dados antes de executar qualquer script.</li>
                <li><strong>Regra:</strong> Esta normalização garante que a regra seja aplicada: anúncios indeferidos sempre em 'draft'.</li>
                <li><strong>Automático:</strong> A função <code>bazar_get_anuncio_status()</code> também corrige automaticamente quando detecta inconsistência.</li>
            </ul>
        </div>
    </div>
    <?php
}
?>

