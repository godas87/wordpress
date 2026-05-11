<?php
global $cta_data;
$cta_data = ( isset( $cta_data ) && !empty( $cta_data ) ) 
  ? $cta_data 
  : array(
    'size' => '',
    'title' => 'Formulário enviado com sucesso!',
    'description' => 'Solicitação realizada com sucesso.',
  );
$site_url = get_bloginfo('url');
?>
<div class="msg-box<?php echo ( isset( $cta_data['size'] ) && $cta_data['size'] != '' ) ? ' '.$cta_data['size'] : ''; ?>">
    <figure>
        <img 
            class="sucess" 
            alt="<?php bloginfo('name');?>" 
            src="<?php bloginfo('template_url');?>/assets/imgs/content/template-<?php echo rand(1,3);?>.webp" 
            width="250" 
            height="153" />
    </figure>

    <h3 class="bold_">
        <?php echo ( $cta_data['title'] ) ? $cta_data['title'] : _e('Formulário enviado com sucesso!', 'bazar'); ?>
    </h3>

    <?php if( $cta_data['description'] && !empty( $cta_data['description'] ) ) : ?>
        <p><?php echo $cta_data['description']; ?></p>
    <?php endif; ?>
    
    <a 
        href="<?php echo $site_url;?>/anunciar/" 
        class="button mb-1" 
        title="Criar anúncio"
    >
        <?php _e('Criar anúncio grátis', 'bazar'); ?>
    </a>
    
    <a 
        href="<?php echo $site_url;?>/bicicletas/" 
        class="button alt mb-1" 
        title="Ver anúncios"
    >
        <?php _e('Buscar Bicicletas', 'bazar'); ?>
    </a>

    <?php get_template_part('template-parts/btn/whatsapp-group'); ?>

</div><!-- /msg-box -->