<?php
add_shortcode('cta_vender', 'cta_vender_shortcode');
function cta_vender_shortcode( $atts ){    

    $txt_title = array(
        'Quer vender sua bicicleta usada?',
        'Anuncie sua bicicleta usada aqui!',
        'Está pensando em vender sua bicicleta usada?',
        'Coloque sua bicicleta usada à venda agora!',
        'Venda sua bicicleta usada no Bazar Bikes!'
    );
    
    $txt_desc = array(
        'Anuncie sua bicicleta grátis em nosso classificado online.',
        'Venda sua bicicleta usada sem custos no nosso classificado.',
        'Cadastre sua bicicleta usada gratuitamente e encontre compradores.',
        'Anuncie sua bicicleta usada facilmente e sem pagar nada.',
        'Publique seu anúncio de bicicleta usada no Bazar Bikes de graça.'
    );   
    
    $link = get_bloginfo('url').'\/anunciar-bicicletas-e-pecas-gratis\/';

    $title = $txt_title[ rand(0, 4) ];
    return '
    <div class="cta-box mb-3"> 
        <div class="text1 bold">'.$title.'</div>
        <div class="text2">'.$txt_desc[ rand(0, 4) ].'</div>
        <a
            href="'.esc_url( $link ).'" 
            class="button"
            title="'.$title.'"
        >Anuncie sua bike aqui</a>
    </div>';
    exit;
};
?>