<?php global $post_id; ?>
<div class="edit-menu">

    <div class="bx">
        <a 
            href="<?php bloginfo('url'); ?>/meus-anuncios/" 
            class="black"
            title="<?php _e('Meus anúncios', 'bazar'); ?>"
        >
            <i class="fas fa-arrow-left red"></i>
            <?php _e('Voltar para meus anúncios', 'bazar');?>
        </a>
    </div>
    
    <div class="bx">
        <?php if( get_post_status( $post_id ) == 'publish' ) : ?>
        <a 
            href="<?php echo esc_html( get_the_permalink( $post_id ) ); ?>" 
            title="<?php _e('Visualizar anúncio', 'bazar');?>"
            class="black"
            target="_blank"            
        >
            <i class="fas fa-eye red"> </i>
            <?php _e('Visualizar anúncio', 'bazar'); ?>
        </a>
        <?php else : ?>
        <a 
            href="<?php echo esc_html( get_the_permalink( $post_id ) ); ?>/&preview=true" 
            title="<?php _e('Preview', 'bazar');?>"
            target="_blank"
        >
            <i class="fas fa-eye black"></i>
            <?php _e('Preview', 'bazar');?>
        </a>
        <?php endif; ?>
    </div>            
</div> 