<?php
/**
 * Modal de reativação de conta cancelada
 * Segue o mesmo padrão do modal de impulsionar
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}
$nonce = wp_create_nonce('nonce_reativar_conta');
?>
<div id="modal-reativar-conta" class="modal">

    <div class="modal-content">
        <button type="button" class="close modal-close modal-close-btn">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="modal-bx">
            <h2 class="bold" style="text-align: center; margin-bottom: 1.5rem;">
                <i class="fa fa-info-circle" style="color: #17a2b8;"></i>
                Conta Cancelada
            </h2>
            
            <p style="text-align: center; margin-bottom: 1.5rem;">
                Seu cadastro está cancelado, deseja ativar?
            </p>
            
            <div id="alert" style="margin-bottom: 1rem;"></div>
            
            <form id="form-reativar-conta" method="post">
                <input type="hidden" name="action" value="bazar_reativar_conta">
                <input type="hidden" name="user_email" id="reactivate-user-email" value="">
                <input type="hidden" name="nonce_reativar_conta" value="<?php echo esc_attr($nonce); ?>">
                
                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="button full primary" style="margin-right: 1rem;">
                        <i class="fa fa-check"></i>
                        Reativar minha conta
                    </button>
                    <button type="button" class="button secondary modal-close full">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal-overlay"></div>
</div>