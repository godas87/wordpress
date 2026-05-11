<?php
// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

// Modal de ajuda para tipos de pneus
if (function_exists('bazar_render_pneus_table')):
  ?>
  <div id="modal-pneus-help" class="modal componente-help">
    <div class="modal-content">
      <div class="modal-head">
        <h3 class="title"><?php _e('Tipos de Pneus', 'bazar'); ?></h3>
        <button type="button" class="close modal-close" aria-label="<?php _e('Fechar', 'bazar'); ?>">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-bx">
        <?php echo bazar_render_pneus_table(true); ?>
      </div>
    </div>
    <!-- Overlay escuro de fundo -->
    <div class="modal-overlay"></div>
  </div>
<?php endif; ?>