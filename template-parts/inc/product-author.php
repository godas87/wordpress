<?php
if (!defined('ABSPATH')) {
  exit;
}
global $product_data;
global $is_post_published;
global $alert_message;
global $IS_ADMIN;
// names
$is_verified = !empty($product_data['author']['perfil_verificado']);
$author_name = isset($product_data['author']['name']) ? $product_data['author']['name'] : '';
$author_sobrenome = isset($product_data['author']['sobrenome']) ? $product_data['author']['sobrenome'] : '';
// contato
$exibir_contato = $product_data['fields']['exibir_contato'];
$exibir_ok = (
  $exibir_contato == 'true' 
  || $exibir_contato === true 
  || $exibir_contato === '1' 
  || $exibir_contato === 1
);
  $tem_fone = !empty($product_data['author']['fone']);
  $tem_whatsapp = (
    $tem_fone 
    && (
      $product_data['author']['whatsapp_ativo'] 
      && $product_data['author']['whatsapp_ativo'] === 'true' 
      || $product_data['author']['whatsapp_ativo'] === true 
      || $product_data['author']['whatsapp_ativo'] === '1' 
      || $product_data['author']['whatsapp_ativo'] === 1
    )
  );
?>
<div class="box-content mb-1 contact">
  <div class="row align-middle auth">
    <div class="col shrink">
      <i class="fas fa-user-alt silver"></i>
    </div>
    <div class="col pl-0">
      <span class="bold user-verified-name-row">
        <?php        
        echo trim($author_name . ' ' . $author_sobrenome) ?: '—';
        get_template_part(
          'template-parts/inc/badge-usuario-verificado',
          null,
          array(
            'is_verified' => $is_verified,
          )
        );
        ?>
      </span>
      <small class="d-block">
        <?php
        // Location (usando dados do helper)
        if ($product_data['formatted']['location']) {
          echo $product_data['formatted']['location'];
        }
        echo !empty($product_data['author']['bairro']) ? ' | ' . esc_html($product_data['author']['bairro']) : '';
        ?>
      </small>
    </div>
  </div>

  <?php if ($exibir_ok && $tem_fone && $is_post_published): ?>
    <small class="show_fone" onclick="showPhone()"><?php _e('Ver telefone', 'bazar'); ?></small>
  <?php endif; ?>

  <?php if (!$exibir_ok): ?>
    <div class="fones bold">
      <small><?php _e('ESTE USUÁRIO OPTOU NÃO EXIBIR TELEFONES', 'bazar'); ?></small>
    </div>
  <?php elseif ($tem_fone): ?>
    <div id="show_phone" class="fones bold d-none">
      <b class="mask_phone_"><?php echo esc_html($product_data['author']['fone']); ?></b>
    </div>
  <?php endif; ?>

  <?php
  if ($is_post_published && $tem_whatsapp && $exibir_ok) {
    get_template_part('template-parts/btn/whatsapp');
  } elseif (!$is_post_published) {
    echo $alert_message;
  }
  ?>

  <?php
  // Botão para bloquear usuário (apenas para administradores)
  if ($IS_ADMIN && isset($product_data['author']['id'])):
    $author_id = $product_data['author']['id'];
    $is_blocked = get_user_meta($author_id, 'bazar_user_blocked', true);
    $is_blocked = ($is_blocked === 'true' || $is_blocked === true || $is_blocked === '1' || $is_blocked === 1);
    ?>
    <button type="button" id="bazar-block-user" class="button full <?php echo $is_blocked ? 'blocked' : ''; ?>"
      data-user-id="<?php echo $author_id; ?>"
      title="<?php echo $is_blocked ? __('Desbloquear usuário', 'bazar') : __('Bloquear usuário', 'bazar'); ?>">
      <i class="fas fa-ban"></i>
      <span
        class="block-text"><?php echo $is_blocked ? __('Desbloquear usuário', 'bazar') : __('Bloquear usuário', 'bazar'); ?></span>
    </button>
  <?php endif; ?>
</div>