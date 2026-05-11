<?php
// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

// Modal de ajuda para tipos de trocadores
if (function_exists('bazar_render_trocadores_grid')):
  ?>
  <div id="modal-trocadores-help" class="modal componente-help">
    <div class="modal-content">
      <div class="modal-head">
        <h3 class="title"><?php _e('Tipos de Trocadores', 'bazar'); ?></h3>
        <button type="button" class="close modal-close" aria-label="<?php _e('Fechar', 'bazar'); ?>">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-bx">
        <?php echo bazar_render_trocadores_grid(true); ?>
      </div>
    </div>
    <!-- Overlay escuro de fundo -->
    <div class="modal-overlay"></div>
  </div>
<?php endif; ?>