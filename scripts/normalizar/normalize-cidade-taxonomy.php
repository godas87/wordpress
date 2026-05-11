<?php
/**
 * Script para normalizar dados da taxonomia 'cidade'
 * 
 * Converte nomes de estados de sigla (ex: "SP") para nome completo (ex: "São Paulo")
 * 
 * IMPORTANTE: Execute este script apenas UMA VEZ através do painel do WordPress
 * ou via WP-CLI. Para executar via painel, adicione temporariamente no functions.php:
 * require_once get_template_directory() . '/scripts/normalize-cidade-taxonomy.php';
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verifica se uma string é uma sigla de estado válida
 * Usa BazarBikes_GeoAPI::get_state_name_by_slug() para validar
 * @param string $nome Nome do termo
 * @param BazarBikes_GeoAPI $geo_api Instância da classe (opcional)
 * @return bool|string Retorna a sigla se for válida, false caso contrário
 */
function bazar_is_estado_sigla($nome, $geo_api = null) {
    $nome_limpo = trim($nome);
    
    // Verificar se tem exatamente 2 caracteres e são letras maiúsculas
    if (strlen($nome_limpo) === 2 && ctype_upper($nome_limpo)) {
        // Obter instância da classe se não foi fornecida
        if (!$geo_api && class_exists('BazarBikes_GeoAPI')) {
            $geo_api = BazarBikes_GeoAPI::getInstance();
        }
        
        // Verificar se a sigla existe usando BazarBikes_GeoAPI
        if ($geo_api) {
            $nome_completo = $geo_api->get_state_name_by_slug($nome_limpo);
            // Se retornou um nome diferente da sigla, a sigla é válida
            if ($nome_completo !== $nome_limpo && !empty($nome_completo)) {
                return $nome_limpo;
            }
        }
    }
    
    return false;
}

/**
 * Função para normalizar meta fields 'estado' dos usuários
 * Converte siglas de estados para nomes completos nos meta fields dos usuários
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_user_estado_meta($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_user_estado_meta_normalized')) {
        return array(
            'success' => true,
            'message' => 'Normalização de meta fields de usuários já foi executada anteriormente.',
            'stats' => array()
        );
    }

    $stats = array(
        'total_usuarios' => 0,
        'usuarios_convertidos' => 0,
        'usuarios_ja_normalizados' => 0,
        'erros' => 0,
        'detalhes' => array()
    );

    // Verificar se a classe BazarBikes_GeoAPI está disponível
    if (!class_exists('BazarBikes_GeoAPI')) {
        return array(
            'success' => false,
            'message' => 'Classe BazarBikes_GeoAPI não encontrada. Verifique se o arquivo está carregado.',
            'stats' => $stats
        );
    }
    
    $geo_api = BazarBikes_GeoAPI::getInstance();
    
    // Buscar todos os usuários que têm meta field 'estado'
    global $wpdb;
    $users_with_estado = $wpdb->get_results(
        "SELECT DISTINCT user_id, meta_value as estado_value 
         FROM {$wpdb->usermeta} 
         WHERE meta_key = 'estado' 
         AND meta_value != '' 
         AND meta_value IS NOT NULL"
    );
    
    if (empty($users_with_estado)) {
        return array(
            'success' => true,
            'message' => 'Nenhum usuário encontrado com meta field estado.',
            'stats' => $stats
        );
    }
    
    $stats['total_usuarios'] = count($users_with_estado);
    
    // Processar cada usuário
    foreach ($users_with_estado as $user_data) {
        $user_id = intval($user_data->user_id);
        $estado_atual = trim($user_data->estado_value);
        
        // Verificar se o estado é uma sigla
        $sigla = bazar_is_estado_sigla($estado_atual, $geo_api);
        
        if ($sigla) {
            // Estado precisa ser convertido
            $nome_completo = $geo_api->get_state_name_by_slug($sigla);
            
            if ($nome_completo && $nome_completo !== $sigla) {
                // Atualizar meta field 'estado' com nome completo
                $update_estado = update_user_meta($user_id, 'estado', $nome_completo);
                
                // Garantir que 'estado_sigla' existe com a sigla
                $estado_sigla_atual = get_user_meta($user_id, 'estado_sigla', true);
                if (empty($estado_sigla_atual)) {
                    update_user_meta($user_id, 'estado_sigla', strtoupper($sigla));
                }
                
                if ($update_estado !== false) {
                    $stats['usuarios_convertidos']++;
                    $stats['detalhes'][] = array(
                        'user_id' => $user_id,
                        'estado_antigo' => $estado_atual,
                        'estado_novo' => $nome_completo,
                        'status' => 'sucesso'
                    );
                    error_log('[Bazar Normalize User Estado] Usuário ID ' . $user_id . ': "' . $estado_atual . '" → "' . $nome_completo . '"');
                } else {
                    $stats['erros']++;
                    $stats['detalhes'][] = array(
                        'user_id' => $user_id,
                        'estado_antigo' => $estado_atual,
                        'estado_novo' => $nome_completo,
                        'status' => 'erro',
                        'mensagem' => 'Erro ao atualizar meta field'
                    );
                }
            } else {
                $stats['erros']++;
                error_log('[Bazar Normalize User Estado] Erro ao converter sigla para usuário ID ' . $user_id . ': ' . $estado_atual);
            }
        } else {
            // Estado já está normalizado (nome completo)
            $stats['usuarios_ja_normalizados']++;
        }
    }

    // Marcar como executado
    update_option('bazar_user_estado_meta_normalized', true);
    update_option('bazar_user_estado_meta_normalized_date', current_time('mysql'));
    update_option('bazar_user_estado_meta_normalized_stats', $stats);

    return array(
        'success' => true,
        'message' => 'Normalização de meta fields de usuários concluída com sucesso!',
        'stats' => $stats
    );
}

/**
 * Função principal para normalizar taxonomia 'cidade'
 * Converte siglas de estados para nomes completos
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_cidade_taxonomy($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_cidade_taxonomy_normalized')) {
        return array(
            'success' => true,
            'message' => 'Normalização já foi executada anteriormente.',
            'stats' => array()
        );
    }

    $stats = array(
        'total_estados' => 0,
        'estados_convertidos' => 0,
        'estados_ja_normalizados' => 0,
        'erros' => 0,
        'detalhes' => array()
    );

    // Buscar todos os termos pais (estados) da taxonomia 'cidade'
    $estados = get_terms(array(
        'taxonomy' => 'cidade',
        'parent' => 0,
        'hide_empty' => false,
    ));

    if (is_wp_error($estados) || empty($estados)) {
        return array(
            'success' => false,
            'message' => 'Erro ao buscar estados ou nenhum estado encontrado.',
            'stats' => $stats
        );
    }

    $stats['total_estados'] = count($estados);
    
    // Verificar se a classe BazarBikes_GeoAPI está disponível
    if (!class_exists('BazarBikes_GeoAPI')) {
        return array(
            'success' => false,
            'message' => 'Classe BazarBikes_GeoAPI não encontrada. Verifique se o arquivo está carregado.',
            'stats' => $stats
        );
    }
    
    $geo_api = BazarBikes_GeoAPI::getInstance();

    // Processar cada estado
    foreach ($estados as $estado) {
        
        $estado_nome_atual = trim($estado->name);
        $estado_id = $estado->term_id;
        
        // Verificar se o nome é uma sigla usando BazarBikes_GeoAPI
        $sigla = bazar_is_estado_sigla($estado_nome_atual, $geo_api);
        
        if ($sigla) {
            // Estado precisa ser convertido
            // Usar método get_state_name_by_slug() da classe BazarBikes_GeoAPI
            $nome_completo = $geo_api->get_state_name_by_slug($sigla);
            
            // Atualizar o termo
            $update_result = wp_update_term(
                $estado_id,
                'cidade',
                array(
                    'name' => $nome_completo
                )
            );
            
            if (is_wp_error($update_result)) {
                $stats['erros']++;
                $stats['detalhes'][] = array(
                    'estado_id' => $estado_id,
                    'nome_antigo' => $estado_nome_atual,
                    'nome_novo' => $nome_completo,
                    'status' => 'erro',
                    'mensagem' => $update_result->get_error_message()
                );
                error_log('[Bazar Normalize Cidade] Erro ao atualizar estado ID ' . $estado_id . ': ' . $update_result->get_error_message());
            } else {
                $stats['estados_convertidos']++;
                $stats['detalhes'][] = array(
                    'estado_id' => $estado_id,
                    'nome_antigo' => $estado_nome_atual,
                    'nome_novo' => $nome_completo,
                    'status' => 'sucesso'
                );
                error_log('[Bazar Normalize Cidade] Estado convertido: "' . $estado_nome_atual . '" → "' . $nome_completo . '" (ID: ' . $estado_id . ')');
            }
        } else {
            // Estado já está normalizado (nome completo)
            $stats['estados_ja_normalizados']++;
        }
    }

    // Marcar como executado
    update_option('bazar_cidade_taxonomy_normalized', true);
    update_option('bazar_cidade_taxonomy_normalized_date', current_time('mysql'));
    update_option('bazar_cidade_taxonomy_normalized_stats', $stats);

    return array(
        'success' => true,
        'message' => 'Normalização concluída com sucesso!',
        'stats' => $stats
    );
}

// Executar quando solicitado via admin
function bazar_run_normalize_cidade() {
    if (isset($_GET['bazar_normalize_cidade']) && current_user_can('manage_options')) {
        $force = isset($_GET['force']) && $_GET['force'] == '1';
        $result = bazar_normalize_cidade_taxonomy($force);
        
        $message = $result['message'];
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            $message .= '<br><br><strong>Estatísticas:</strong><br>';
            $message .= 'Total de estados: ' . $stats['total_estados'] . '<br>';
            $message .= 'Estados convertidos: ' . $stats['estados_convertidos'] . '<br>';
            $message .= 'Estados já normalizados: ' . $stats['estados_ja_normalizados'] . '<br>';
            if ($stats['erros'] > 0) {
                $message .= 'Erros: ' . $stats['erros'] . '<br>';
            }
        }
        
        wp_die($message . '<br><br><a href="' . admin_url() . '">Voltar ao painel</a>');
    }
}
add_action('admin_init', 'bazar_run_normalize_cidade');

// Adicionar página no menu admin para executar o script
function bazar_add_normalize_cidade_menu() {
    add_management_page(
        'Normalizar Taxonomia Cidade',
        'Normalizar Cidade',
        'manage_options',
        'bazar-normalize-cidade',
        'bazar_normalize_cidade_page'
    );
}
add_action('admin_menu', 'bazar_add_normalize_cidade_menu');

// Página de administração para normalizar
function bazar_normalize_cidade_page() {
    // Executar normalização de taxonomia
    if (isset($_POST['bazar_execute_normalize']) && check_admin_referer('bazar_normalize_cidade_action')) {
        $force = isset($_POST['force']) && $_POST['force'] == '1';
        $normalize_users = isset($_POST['normalize_users']) && $_POST['normalize_users'] == '1';
        
        $result = bazar_normalize_cidade_taxonomy($force);
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . '"><p>' . esc_html($result['message']) . '</p></div>';
        
        if (isset($result['stats'])) {
            $stats = $result['stats'];
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Estatísticas (Taxonomia):</strong><br>';
            echo 'Total de estados: ' . $stats['total_estados'] . '<br>';
            echo 'Estados convertidos: ' . $stats['estados_convertidos'] . '<br>';
            echo 'Estados já normalizados: ' . $stats['estados_ja_normalizados'] . '<br>';
            if ($stats['erros'] > 0) {
                echo 'Erros: ' . $stats['erros'] . '<br>';
            }
            echo '</p></div>';
            
            // Mostrar detalhes se houver conversões
            if (!empty($stats['detalhes']) && $stats['estados_convertidos'] > 0) {
                echo '<div class="card"><h3>Detalhes das Conversões (Taxonomia)</h3><ul>';
                foreach ($stats['detalhes'] as $detalhe) {
                    if (isset($detalhe['status']) && $detalhe['status'] === 'sucesso') {
                        echo '<li>ID ' . $detalhe['estado_id'] . ': "' . esc_html($detalhe['nome_antigo']) . '" → "' . esc_html($detalhe['nome_novo']) . '"</li>';
                    }
                }
                echo '</ul></div>';
            }
        }
        
        // Executar normalização de usuários se solicitado
        if ($normalize_users) {
            $result_users = bazar_normalize_user_estado_meta($force);
            
            $class_users = $result_users['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $class_users . '"><p>' . esc_html($result_users['message']) . '</p></div>';
            
            if (isset($result_users['stats'])) {
                $stats_users = $result_users['stats'];
                echo '<div class="notice notice-info"><p>';
                echo '<strong>Estatísticas (Usuários):</strong><br>';
                echo 'Total de usuários: ' . $stats_users['total_usuarios'] . '<br>';
                echo 'Usuários convertidos: ' . $stats_users['usuarios_convertidos'] . '<br>';
                echo 'Usuários já normalizados: ' . $stats_users['usuarios_ja_normalizados'] . '<br>';
                if ($stats_users['erros'] > 0) {
                    echo 'Erros: ' . $stats_users['erros'] . '<br>';
                }
                echo '</p></div>';
                
                // Mostrar detalhes se houver conversões (limitado a 50 para não sobrecarregar)
                if (!empty($stats_users['detalhes']) && $stats_users['usuarios_convertidos'] > 0) {
                    $detalhes_limitados = array_slice($stats_users['detalhes'], 0, 50);
                    echo '<div class="card"><h3>Detalhes das Conversões (Usuários)</h3>';
                    if (count($stats_users['detalhes']) > 50) {
                        echo '<p><em>Mostrando primeiros 50 de ' . count($stats_users['detalhes']) . ' usuários convertidos.</em></p>';
                    }
                    echo '<ul>';
                    foreach ($detalhes_limitados as $detalhe) {
                        if (isset($detalhe['status']) && $detalhe['status'] === 'sucesso') {
                            echo '<li>Usuário ID ' . $detalhe['user_id'] . ': "' . esc_html($detalhe['estado_antigo']) . '" → "' . esc_html($detalhe['estado_novo']) . '"</li>';
                        }
                    }
                    echo '</ul></div>';
                }
            }
        }
    }
    
    $already_normalized = get_option('bazar_cidade_taxonomy_normalized');
    $normalized_date = get_option('bazar_cidade_taxonomy_normalized_date');
    $last_stats = get_option('bazar_cidade_taxonomy_normalized_stats');
    
    $users_normalized = get_option('bazar_user_estado_meta_normalized');
    $users_normalized_date = get_option('bazar_user_estado_meta_normalized_date');
    $last_stats_users = get_option('bazar_user_estado_meta_normalized_stats');
    ?>
    <div class="wrap">
        <h1>Normalizar Taxonomia 'Cidade' e Meta Fields de Usuários</h1>
        
        <div class="card" style="margin-bottom: 20px;">
            <h2>1. Normalizar Taxonomia 'Cidade'</h2>
            <p>Este script normaliza os nomes dos estados na taxonomia 'cidade':</p>
            <ul>
                <li><strong>Antes:</strong> Nome = sigla (ex: "SP", "RJ", "MG")</li>
                <li><strong>Depois:</strong> Nome = nome completo (ex: "São Paulo", "Rio de Janeiro", "Minas Gerais")</li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca todos os termos pais (estados) da taxonomia 'cidade'</li>
                <li>Identifica quais têm sigla como nome (2 letras maiúsculas)</li>
                <li>Converte sigla → nome completo usando BazarBikes_GeoAPI</li>
                <li>Atualiza o nome do termo</li>
            </ol>
            
            <?php if ($already_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização já foi executada anteriormente.</p>
                    <?php if ($normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($last_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $last_stats['estados_convertidos']; ?> estados convertidos
                            de <?php echo $last_stats['total_estados']; ?> total.
                        </p>
                    <?php endif; ?>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A normalização ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
        </div>
        
        <div class="card" style="margin-bottom: 20px;">
            <h2>2. Normalizar Meta Fields de Usuários</h2>
            <p>Este script normaliza os meta fields 'estado' dos usuários que têm sigla salva:</p>
            <ul>
                <li><strong>Problema:</strong> Usuários antigos têm sigla (ex: "ES") no meta field 'estado'</li>
                <li><strong>Solução:</strong> Converte sigla → nome completo (ex: "Espírito Santo")</li>
                <li><strong>Resultado:</strong> Novos anúncios usarão nome completo ao invés de sigla</li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca todos os usuários que têm meta field 'estado' com sigla</li>
                <li>Converte sigla → nome completo usando BazarBikes_GeoAPI</li>
                <li>Atualiza o meta field 'estado' com o nome completo</li>
                <li>Garante que o meta field 'estado_sigla' existe com a sigla</li>
            </ol>
            
            <?php if ($users_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização de usuários já foi executada anteriormente.</p>
                    <?php if ($users_normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($users_normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($last_stats_users): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $last_stats_users['usuarios_convertidos']; ?> usuários convertidos
                            de <?php echo $last_stats_users['total_usuarios']; ?> total.
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A normalização de usuários ainda não foi executada.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Executar Normalização</h2>
            <form method="post" action="">
                <?php wp_nonce_field('bazar_normalize_cidade_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force" value="1" <?php checked($already_normalized || $users_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado anteriormente)
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="normalize_users" value="1" <?php checked(!$users_normalized); ?>>
                        <strong>Normalizar meta fields de usuários</strong> (recomendado se houver usuários antigos)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize" class="button button-primary button-large">
                        Executar Normalização
                    </button>
                </p>
            </form>
            
            <hr>
            <h3>Alternativa: Via URL</h3>
            <p>Você também pode executar o script acessando:</p>
            <code><?php echo admin_url('admin.php?page=bazar-normalize-cidade&bazar_normalize_cidade=1'); ?></code>
            <?php if ($already_normalized): ?>
                <br><br>
                <code><?php echo admin_url('admin.php?page=bazar-normalize-cidade&bazar_normalize_cidade=1&force=1'); ?></code> (com força)
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
