<?php
/**
 * Limpa transients expirados do WordPress
 * Executa limpeza apenas de transients com prefixo 'bazar_'
 * 
 * @param int $limit Limite de transients a processar por execução (padrão: 1000)
 * @return array Estatísticas da limpeza
 */

 // Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}
function bazar_cleanup_expired_transients($limit = 1000) {
    global $wpdb;
    
    $stats = array(
        'deleted' => 0,
        'processed' => 0,
        'errors' => 0
    );
    
    // Buscar transients expirados com prefixo 'bazar_'
    // WordPress armazena transients como: _transient_* e _transient_timeout_*
    $expired_transients = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name 
             FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE %s
             AND option_value < %d
             LIMIT %d",
            $wpdb->esc_like('_transient_timeout_') . '%',
            $wpdb->esc_like('_transient_timeout_bazar_') . '%',
            time(),
            $limit
        ),
        ARRAY_A
    );
    
    if (empty($expired_transients)) {
        return $stats;
    }
    
    foreach ($expired_transients as $transient) {
        $stats['processed']++;
        
        // Extrair nome do transient (remover prefixo _transient_timeout_)
        $transient_name = str_replace('_transient_timeout_', '', $transient['option_name']);
        
        // Deletar tanto o timeout quanto o valor do transient
        $deleted_timeout = $wpdb->delete(
            $wpdb->options,
            array('option_name' => '_transient_timeout_' . $transient_name),
            array('%s')
        );
        
        $deleted_value = $wpdb->delete(
            $wpdb->options,
            array('option_name' => '_transient_' . $transient_name),
            array('%s')
        );
        
        if ($deleted_timeout !== false && $deleted_value !== false) {
            $stats['deleted']++;
        } else {
            $stats['errors']++;
        }
    }
    
    return $stats;
}

/**
 * Agenda limpeza automática de transients expirados
 * Executa diariamente às 3h da manhã
 */
add_action('init', 'bazar_schedule_transient_cleanup');
function bazar_schedule_transient_cleanup() {
    if (!wp_next_scheduled('bazar_cleanup_expired_transients')) {
        // Agendar para 3h da manhã (horário de baixo tráfego)
        wp_schedule_event(strtotime('tomorrow 3:00'), 'daily', 'bazar_cleanup_expired_transients');
    }
}

/**
 * Executa limpeza quando o cron é acionado
 */
add_action('bazar_cleanup_expired_transients', 'bazar_run_transient_cleanup');
function bazar_run_transient_cleanup() {
    $stats = bazar_cleanup_expired_transients(1000);
    
    // Log para debug (opcional)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Bazar Transients Cleanup] Deleted: ' . $stats['deleted'] . ' | Processed: ' . $stats['processed'] . ' | Errors: ' . $stats['errors']);
    }
}

/**
 * Limpar agendamento ao desativar (se necessário)
 */
register_deactivation_hook(__FILE__, 'bazar_unschedule_transient_cleanup');
function bazar_unschedule_transient_cleanup() {
    $timestamp = wp_next_scheduled('bazar_cleanup_expired_transients');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'bazar_cleanup_expired_transients');
    }
}

/**
 * Função para executar limpeza manual (útil para admin)
 * Pode ser chamada via WP-CLI ou painel admin
 */
function bazar_manual_cleanup_transients() {
    return bazar_cleanup_expired_transients(5000); // Limpar mais em execução manual
}

