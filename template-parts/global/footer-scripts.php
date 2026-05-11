<?php
if (bazar_is_production()):
  if (!is_page(array('minha-conta', 'editar-anuncio', 'meus-anuncios'))):
    get_template_part('template-parts/lib/pixel');
    get_template_part('template-parts/lib/google');
    // get_template_part('template-parts/lib/pinterest');
  endif;
endif;
?>