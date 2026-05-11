<?php
/**
 * Área administrativa: aprovar / indeferir anúncio (single).
 * Status exibido alinhado a bazar_get_anuncio_status() e regras de publicação.
 */
if (!current_user_can('manage_options')) {
  return;
}

$post_id = (int) get_the_ID();
$post_status = get_post_status($post_id);

if (!in_array($post_status, ['pending', 'draft'], true)) {
  return;
}

$status_data = function_exists('bazar_get_anuncio_status')
  ? bazar_get_anuncio_status($post_id)
  : array('status' => $post_status);
$global_status = isset($status_data['status']) ? (string) $status_data['status'] : $post_status;

$modifier = 'pending';
$status_title = '';
$status_description = '';
$show_approve = true;

switch ($global_status) {
  case 'aprovado_aguardando_dados':
    $modifier = 'approved-waiting';
    $status_title = __('Publicação Pendente', 'bazar');
    $status_description = __('O vendedor precisa completar o cadastro em Minha Conta ou confirmar o e-mail; o anúncio será publicado automaticamente quando os requisitos forem atendidos.', 'bazar');
    $show_approve = false;
    break;

  case 'pending':
    $modifier = 'pending';
    $status_title = __('Aguardando Aprovação do ADM', 'bazar');
    $status_description = __('Revise o conteúdo para aprovar ou indeferir.', 'bazar');
    break;

  case 'indeferido':
    $modifier = 'indeferido';
    $status_title = __('Indeferido', 'bazar');
    $status_description = __('O vendedor foi notificado. Você pode aprovar novamente após correções.', 'bazar');
    break;

  case 'draft':
    $modifier = 'draft';
    $status_title = __('Em reavaliação (rascunho)', 'bazar');
    $status_description = __('Anúncio em rascunho para nova análise. Aprove ou indeferir.', 'bazar');
    break;

  default:
    $status_title = function_exists('bazar_get_anuncio_status_label')
      ? bazar_get_anuncio_status_label($global_status)
      : ucfirst($global_status);
    $status_description = __('Ações administrativas disponíveis para este anúncio.', 'bazar');
}

$motivos_existentes = isset($_POST['motivos']) ? $_POST['motivos'] : '';
$status_icon = function_exists('bazar_get_anuncio_status_icon')
  ? bazar_get_anuncio_status_icon($global_status)
  : '<i class="fa fa-shield-alt"></i>';
?>

<div class="box-content mb-1 admin-approval-section admin-approval-section--<?php echo esc_attr($modifier); ?>">
  <div class="admin-approval-section__head">
    <h3 class="h4 admin-approval-section__title">
      <i class="fas fa-shield-alt" aria-hidden="true"></i>
      <?php esc_html_e('Área administrativa', 'bazar'); ?>
    </h3>
    <div class="admin-approval-section__status-block">
      <div class="admin-approval-section__status-label">
        <?php echo wp_kses_post($status_icon); ?>
        <strong><?php echo esc_html($status_title); ?></strong>
      </div>
      <?php if ($status_description !== ''): ?>
        <p class="admin-approval-section__hint"><?php echo esc_html($status_description); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="row align-middle admin-approval-section__actions">
    <?php if ($show_approve): ?>
      <div class="col s-12">
        <button type="button" id="btn-aprovar-anuncio" class="button success admin-approval-section__btn"
          data-post-id="<?php echo esc_attr($post_id); ?>">
          <i class="fas fa-check-circle" aria-hidden="true"></i>
          <?php esc_html_e('Aprovar anúncio', 'bazar'); ?>
        </button>
      </div>
    <?php else: ?>
      <div class="col s-12">
        <div class="admin-approval-section__approved-pill" role="status">
          <i class="fas fa-check-circle" aria-hidden="true"></i>
          <?php esc_html_e('Aprovado pelo Administrador', 'bazar'); ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="col s-12">
      <button type="button" id="btn-indeferir-anuncio" class="button danger admin-approval-section__btn"
        data-post-id="<?php echo esc_attr($post_id); ?>">
        <i class="fas fa-times-circle" aria-hidden="true"></i>
        <?php esc_html_e('Indeferir anúncio', 'bazar'); ?>
      </button>
    </div>
  </div>

  <div id="reprovar-form" class="d-none admin-approval-section__reprovar">
    <form id="form-reprovar-anuncio">
      <label for="motivos-indeferimento" class="bold">
        <?php esc_html_e('Motivos do indeferimento', 'bazar'); ?> <span class="red">*</span>
      </label>
      <textarea id="motivos-indeferimento" name="motivos" rows="5" required class="admin-approval-section__textarea"
        placeholder="<?php esc_attr_e('Descreva os motivos do indeferimento…', 'bazar'); ?>"><?php echo esc_textarea($motivos_existentes ? $motivos_existentes : ''); ?></textarea>

      <div class="row align-middle mt-1">
        <div class="col s-12 m-6">
          <button type="submit" id="btn-salvar-indeferimento" class="button success admin-approval-section__btn">
            <i class="fas fa-paper-plane" aria-hidden="true"></i>
            <?php esc_html_e('Salvar e enviar e-mail', 'bazar'); ?>
          </button>
        </div>
        <div class="col s-12 m-6">
          <button type="button" id="btn-cancelar-indeferimento" class="button black admin-approval-section__btn">
            <i class="fas fa-times" aria-hidden="true"></i>
            <?php esc_html_e('Cancelar', 'bazar'); ?>
          </button>
        </div>
      </div>

      <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
      <input type="hidden" name="operation" value="reprovar">
      <?php $nonce = wp_create_nonce('nonce_anuncio_aprovar_reprovar'); ?>
      <input type="hidden" name="nonce_anuncio_aprovar_reprovar" value="<?php echo esc_attr($nonce); ?>">
      <input type="hidden" name="action" value="bazar_anuncio_aprovar_reprovar">
    </form>
  </div>

  <div id="approval-feedback" class="d-none admin-approval-section__feedback" role="status" aria-live="polite"></div>
</div>