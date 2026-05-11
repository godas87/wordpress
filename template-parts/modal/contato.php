<?php
$url_whats = (wp_is_mobile()) ? 'api' : 'web';
$whatsapp = 'XXXXXX';
$whatsapp_msg = 'Olá, acessei o site XXXXXX e estou entrando em contato através do Whatsapp.';
?>
<div id="modal-contato" class="modal-side">
  <div class="modal-side-box">
    <div class="modal-side-head">

      <h2 class="title">
        <?php _e('Contato', 'bazar'); ?>
      </h2>
      <span class="fas fa-times close modal-side-close"></span>

    </div><!-- /modal-side-head -->
    <div class="modal-side-content">

      <p class="xl">
        Entre em contato com a equipe do site. Sua opinião é muito importante.
      </p>

      <ul>
        <li>
          <a href="#" title="E-mail Bazar Bikes" class="item" id="copy-to-clipboard"
            data-copy-email="XXXXXX">
            <span id="msg-success" class="success-email">
              E-mail copiado!
            </span>
            <i class="fas fa-envelope icon"></i>
            <p>
              <span>E-mail</span>
              <b>XXXXXX</b>

            </p>
            <i class="fas fa-copy icon-last"></i>
          </a>
        </li>
        <li>
          <a href="https://<?php echo $url_whats; ?>.whatsapp.com/send?phone=<?php echo $whatsapp; ?>&text=<?php echo $whatsapp_msg; ?>"
            target="_blank" title="Whatsapp Bazar Bikes" class="item">
            <i class="fab fa-whatsapp icon"></i>
            <p>
              <span>Whatsapp</span>
              <b>XXXXXX</b>
            </p>
            <i class="fas fa-chevron-right icon-last"></i>
          </a>
        </li>
        <li>
          <a href="<?php bloginfo('url'); ?>/publicidade" title="Parceria Bazar Bikes" class="item">
            <i class="fas fa-handshake icon"></i>
            <p>
              <span>Parcerias e</span>
              <b>Publicidade</b>
            </p>
            <i class="fas fa-chevron-right icon-last"></i>
          </a>
        </li>
      </ul>

      <div class="lgpd">
        <?php get_template_part('template-parts/global/msg-lgpd'); ?>
      </div>

    </div><!-- /modal-side-content -->
  </div><!-- /modal-content -->
  <div class="bg-modal-side"></div>
</div><!-- /modal -->