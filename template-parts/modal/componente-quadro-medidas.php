<?php
// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

// Modal de ajuda para tamanhos de quadro
if (function_exists('bazar_render_quadro_table')):
  ?>
  <div id="modal-quadro-help" class="modal componente-help">
    <div class="modal-content">
      <div class="modal-head">
        <h3 class="title"><?php _e('Tabela de Recomendação de Tamanhos de Quadro', 'bazar'); ?></h3>
        <button type="button" class="close modal-close" aria-label="<?php _e('Fechar', 'bazar'); ?>">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-bx">
        <?php echo bazar_render_quadro_table(true); ?>
      </div>
    </div>
    <!-- Overlay escuro de fundo -->
    <div class="modal-overlay"></div>
  </div>
<?php endif; ?>