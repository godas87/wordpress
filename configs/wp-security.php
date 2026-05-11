<?php
// ============================================
// XML-RPC (desativado: reduz ataques e carga; use REST/app se precisar de API)
// ============================================
add_filter('xmlrpc_enabled', '__return_false');

// ============================================
// BLOQUEIO DE USUÁRIOS SPAM
// ============================================
/**
 * Valida se usuário está bloqueado antes de autenticar
 */
add_filter('wp_authenticate_user', 'bazar_check_user_blocked', 10, 2);
function bazar_check_user_blocked($user, $password) {
    if (is_wp_error($user)) {
        return $user;
    }
    
    // Verificar bloqueio
    $is_blocked = get_user_meta($user->ID, 'bazar_user_blocked', true);
    $is_blocked = ($is_blocked === 'true' || $is_blocked === true || $is_blocked === '1' || $is_blocked === 1);
    
    if ($is_blocked) {
        return new WP_Error(
            'user_blocked',
            'Usuário bloqueado. Entre em contato com o suporte.'
        );
    }

    // Verificar cancelamento
    $is_cancelled = get_user_meta($user->ID, 'bazar_user_cancelled', true);
    $is_cancelled = ($is_cancelled === 'true' || $is_cancelled === true || $is_cancelled === '1' || $is_cancelled === 1);
    
    if ($is_cancelled) {
        return new WP_Error(
            'user_cancelled',
            'Seu cadastro está cancelado, deseja ativar?'
        );
    }
    
    return $user;
}

function tg_enable_strict_transport_security_hsts_header_wordpress() {
    header( 'Strict-Transport-Security: max-age=31536000' );
}
add_action( 'send_headers', 'tg_enable_strict_transport_security_hsts_header_wordpress' );
/** 
 * Enables the HTTP Strict Transport Security (HSTS) header in WordPress. 
 */