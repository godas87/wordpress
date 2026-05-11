<?php
if (
  is_user_logged_in()
  && !is_page('confirmar-email')
  && !is_page('page-confirmar-email')
):
  if (!bazar_email_ativado(get_current_user_id())):
    ?>
    <div class="email-validation">
      <div class="bx">
        <i class="fas fa-envelope"></i>
        <span>Você precisa <b>confirmar seu e-mail</b> <a title="Ativar conta"
            href="<?php bloginfo('url'); ?>/confirmar-email/">clicando aqui.</a>
        </span>
      </div>
    </div>
    <?php
  endif;
endif;
?>