<?php
/**
 * Formulário de cancelamento de conta (oculto)
 * O modal de confirmação usa o modal-confirm padrão
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$nonce = wp_create_nonce('nonce_cancelar_conta');
?>

<!-- Formulário (oculto) - usado pelo JavaScript -->
<form id="form-cancelar-conta" method="post" style="display: none;">
    <input type="hidden" name="action" value="bazar_cancelar_conta">
    <input type="hidden" name="nonce_cancelar_conta" value="<?php echo esc_attr($nonce); ?>">
</form>

