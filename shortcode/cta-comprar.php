<?php
add_shortcode('cta_comprar', 'cta_comprar_shortcode');
function cta_comprar_shortcode( $atts ){    

    $txt_title = array(
        'Quer comprar uma bicicleta usada?',
        'Bicicletas novas e usadas disponíveis!',
        'Procurando bicicletas usadas de qualidade?',
        'Buscando uma bicicleta usada ideal?',
        'Bicicletas usadas no Bazar Bikes, confira agora!'
    );
    
    $txt_desc = array(
        'Descubra ótimas opções de bicicletas usadas para você.',
        'Encontre sua bicicleta favorita no Bazar Bikes!',
        'Confira nosso classificado de bicicletas novas e usadas.',
        'Veja nossas bicicletas novas e usadas no classificado.',
        'Explore nosso classificado de bicicletas usadas e novas.'
    );

    global $seo;
    
    // Obter dados SEO usando método público
    $seo_data = (isset($seo) && method_exists($seo, 'SEO_params')) ? $seo->SEO_params() : array();
    $seo_key = isset($seo_data['key']) ? $seo_data['key'] : '';
    $seo_url = isset($seo_data['url']) ? $seo_data['url'] : '';
    
    $has_title = !empty($seo_key);
    $title = ( $has_title ) 
        ? 'Procurando ' . $seo_key . '?' 
        : $txt_title[ rand(0, 4) ];            
    
    $link = ( !empty($seo_url) ) ? $seo_url : get_bloginfo('url') . '/bicicletas/';

    return '
    <div class="cta-box mb-3"> 
        <div class="text1 bold">'.$title.'</div>
        <div class="text2">'.$txt_desc[ rand(0, 4) ].'</div>
        <a
            href="'.esc_url( $link ).'" 
            class="button"
            title="'.$title.'"
        >Encontre sua bike aqui</a>
    </div>';
    exit;
};
?>