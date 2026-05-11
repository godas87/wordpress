<?php 
global $cta_active;
$status = ( null == $cta_active && !$cta_active ) ? 'hide' : '';

global $cta_title; 
$show_title = ( null != $cta_title && $cta_title ) ? $cta_title : '';

global $cta_size; 
$show_size = ( null != $cta_size && $cta_size ) ? $cta_size : '';
?>
<div class="msg-box <?php echo $status.' '.$show_size; ?>">
    <figure>
        <img 
            class="sucess" 
            alt="<?php bloginfo('name');?>" 
            src="<?php bloginfo('template_url');?>/assets/imgs/content/template-<?php echo rand(1,3);?>.webp" 
            width="554px" 
            height="338px" />
    </figure>
    <h3 class="bold_">
        <?php echo $cta_title; ?>
    </h3>
    <p></p>                
    <a 
        href="<?php bloginfo('url');?>/anunciar/" 
        class="button mb-1" 
        title="Criar anúncio"
    >
        Cria anúncio grátis
    </a>
    <a 
        href="<?php bloginfo('url');?>/bicicletas/" 
        class="button alt mb-1" 
        title="Ver anúncios"
    >
        Buscar Bicicletas
    </a>
</div><!-- /msg-box -->