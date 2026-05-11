<?php
global $author_whatsapp;
if ($author_whatsapp != '' && (get_field('exibir_contato') == 'true' || get_field('exibir_contato') === true)):
  $url_whats = (wp_is_mobile()) ? 'api' : 'web';
  $whatsapp_str = '55' . str_replace(array('.', '-', '(', ')', ' ', '  '), '', $author_whatsapp);
  ?>
  <a class="button green regular full btn-whatsapp"
    href="https://<?php echo $url_whats; ?>.whatsapp.com/send?phone=<?php echo $whatsapp_str; ?>&text=<?php echo "Olá, vi o seu anúncio '" . strtoupper(get_the_title()) . "' no site Bazar Bikes e tenho interesse. Por favor entre em contato."; ?>"
    target="_blank" title="Enviar mensagem por Whatsapp" data-post-id="<?php echo get_the_ID(); ?>"
    data-nonce="<?php echo wp_create_nonce('bazar_count_click'); ?>" data-meta-key="_count_contact_whatsapp">
    <span class="fab fa-whatsapp white"></span>
    <?php _e('Enviar um Whatsapp', 'bazar'); ?>
  </a>
<?php endif; ?>