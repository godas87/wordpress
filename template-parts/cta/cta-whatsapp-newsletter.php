<?php
/**
 * Template part: dois CTAs reutilizáveis (WhatsApp + Newsletter)
 * WhatsApp: link externo (opção bazar_whatsapp_group_url)
 * Newsletter: âncora para #news (formulário no footer)
 *
 * @package XXXXXX
 */
if (!defined('ABSPATH')) {
  exit;
}
$whatsapp_url = (isset($args['whatsapp_url']) && !empty($args['whatsapp_url']))
  ? $args['whatsapp_url']
  : get_option('bazar_whatsapp_group_url', '');

// Imagens 1–3: WhatsApp usa uma; Newsletter usa outra (evita repetir)
$cta_whatsapp_num = !empty($whatsapp_url) ? rand(1, 3) : 0;
$remaining = array_values(array_diff(array(1, 2, 3), array($cta_whatsapp_num)));
$cta_news_num = $remaining[array_rand($remaining)];
?>
<div class="row align-center">
  <?php if (!empty($whatsapp_url)): ?>
    <div class="s-12 m-6 col pb-2">
      <div class="cta-whatsapp-link">

        <figure>
          <a href="<?php echo esc_url($whatsapp_url); ?>" title="<?php esc_attr_e('Grupo de WhatsApp', 'bazar'); ?>"
            target="_blank" rel="noopener noreferrer">
            <img
              src="<?php bloginfo('template_url'); ?>/assets/imgs/content/cta-whatsapp-<?php echo (int) $cta_whatsapp_num; ?>.webp"
              alt="WhatsApp" width="1024" height="460" title="WhatsApp">
          </a>
        </figure>

        <a href="<?php echo esc_url($whatsapp_url); ?>" title="<?php esc_attr_e('Grupo de WhatsApp', 'bazar'); ?>"
          target="_blank" class="box" rel="noopener noreferrer">
          <span class="bx">
            <span class="text">
              <i class="fab fa-whatsapp"></i>
              <?php _e('Bazar Ofertas', 'bazar'); ?>
              <br>
              <small>
                <?php _e('Grupo de Ofertas. Silencioso. Sem spam.', 'bazar'); ?>
              </small>
            </span>
          </span>
          <i class="fas fa-chevron-right alt"></i>
        </a>

      </div>
    </div><!-- /col -->
  <?php endif; ?>

  <div class="s-12 m-6 col">

    <div class="cta-news-link">

      <figure>
        <a href="#newsletter" title="<?php esc_attr_e('Inscrever na newsletter', 'bazar'); ?>" target="_blank"
          rel="noopener noreferrer">
          <img
            src="<?php bloginfo('template_url'); ?>/assets/imgs/content/cta-news-<?php echo (int) $cta_news_num; ?>.webp"
            alt="Newsletter" width="1024" height="460" title="Newsletter">
        </a>
      </figure>

      <a href="#newsletter" title="<?php esc_attr_e('Inscrever na newsletter', 'bazar'); ?>" class="box"
        rel="noopener noreferrer">

        <span class="bx">

          <span class="text">
            <i class="fas fa-envelope"></i>
            <?php _e('Assine a newsletter', 'bazar'); ?>
            <br>
            <small>
              <?php _e('Noticias, dicas e promoções.', 'bazar'); ?>
            </small>
          </span>

        </span>

        <i class="fas fa-chevron-right alt"></i>

      </a>
    </div>

  </div><!-- /col -->
</div><!-- /row -->