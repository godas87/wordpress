<?php
/**
 * Pinterest - Scripts centralizados
 *
 * Inclui:
 * - Pinterest Tag (pintrk): rastreamento de conversões e remarketing
 * - pinit.js: necessário para o botão "Salvar" (Pin It) nos compartilhamentos
 *
 * Configuração: use o filtro 'bazar_pinterest_config' ou as constantes
 * da classe BazarBikes_Pinterest_Integration (quando wp-pinterest.php ativo).
 *
 * @package XXXXXX
 */
if (!defined('ABSPATH')) {
  exit;
}

// Configuração: prioridade para classe, depois filtro, depois padrões
$pinterest_tag_id = '2614402249284';
$pinterest_tag_enabled = true;

if (class_exists('BazarBikes_Pinterest_Integration')) {
  $tag_id = BazarBikes_Pinterest_Integration::PINTEREST_TAG_ID;
  $tag_enabled = BazarBikes_Pinterest_Integration::PINTEREST_TAG_ENABLED;
  if (!empty($tag_id)) {
    $pinterest_tag_id = $tag_id;
  }
  $pinterest_tag_enabled = $tag_enabled;
}

$config = apply_filters('bazar_pinterest_config', [
  'tag_id' => $pinterest_tag_id,
  'tag_enabled' => $pinterest_tag_enabled,
  'load_save_button' => true,
]);

$pinterest_tag_id = $config['tag_id'];
$pinterest_tag_enabled = $config['tag_enabled'];
$load_save_button = $config['load_save_button'];

// Não exibir em páginas privadas
$excluded_pages = ['minha-conta', 'editar-anuncio', 'meus-anuncios'];
if (is_page($excluded_pages)) {
  return;
}

?>

<?php if ($pinterest_tag_enabled && !empty($pinterest_tag_id)) : ?>
<!-- Pinterest Tag (rastreamento) -->
<script>
  !function (e) {
    if (!window.pintrk) {
      window.pintrk = function () {
        window.pintrk.queue.push(Array.prototype.slice.call(arguments))
      }; var
        n = window.pintrk; n.queue = [], n.version = "3.0"; var
          t = document.createElement("script"); t.async = !0, t.src = e; var
            r = document.getElementsByTagName("script")[0];
      r.parentNode.insertBefore(t, r)
    }
  }("https://s.pinimg.com/ct/core.js");
  <?php if (is_user_logged_in()) :
    $user = wp_get_current_user();
    $hashed_email = !empty($user->user_email) ? wp_hash($user->user_email) : '';
  ?>
  pintrk('load', '<?php echo esc_js($pinterest_tag_id); ?>', { em: '<?php echo esc_js($hashed_email); ?>' });
  <?php else : ?>
  pintrk('load', '<?php echo esc_js($pinterest_tag_id); ?>');
  <?php endif; ?>
  pintrk('page');
</script>
<noscript>
  <img height="1" width="1" style="display:none;" alt=""
    src="https://ct.pinterest.com/v3/?event=init&tid=<?php echo esc_attr($pinterest_tag_id); ?>&noscript=1" />
</noscript>
<!-- end Pinterest Tag -->
<?php endif; ?>

<?php if ($load_save_button) : ?>
<!-- Pinterest Save Button (pinit.js) -->
<script async defer src="https://assets.pinterest.com/js/pinit.js"></script>
<!-- end Pinterest Save Button -->
<?php endif; ?>
