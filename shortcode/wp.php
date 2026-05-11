<?php
add_shortcode('view_itens', 'view_itens_code');
// [view_itens label='Freios Shimano']
function view_itens_code( $value ){
    $default = array(
        'label' => 'Bicicletas'
    );
    $str = shortcode_atts($default, $value);
    return '<div class="cta mb-3">        
        <span>
            Acesse as <strong>melhores ofertas! <br>'.$str['label'].'</strong> novos e usados.
        </span>
        <a class="button" href="#view_itens" rel="noreferrer" title="Veja itens a venda">
            Clique aqui
        </a>
    </div>';
};

add_shortcode('view_itens_alt', 'view_itens_code_alt');
function view_itens_code_alt( $value ){
    $default = array(
        'label' => 'Bicicletas'
    );
    $str = shortcode_atts($default, $value);
    return '<div class="cta mb-3">        
        <span>
            Acesse as <strong>melhores ofertas! <br>'.$str['label'].'</strong> novas e usadas.
        </span>
        <a class="button" href="#view_itens" rel="noreferrer" title="Veja itens a venda">
            Clique aqui
        </a>
    </div>';
};

// [create_ad]
add_shortcode('create_ad', 'create_ad_code');
function create_ad_code(){
    return '<div class="cta mb-3">        
        <span>
            Quer vender bicicleta ou peça?
            <br>
            Anuncie <b>sem pagar comissões.</b> 
        </span>
        <a class="button" href="'.get_bloginfo('url').'/anunciar-bicicletas-e-pecas-gratis/" rel="noreferrer" title="Anuncie grátis">
            Anuncie grátis
        </a>
    </div>';
};

// [create_ad_2]
add_shortcode('create_ad_2', 'create_ad_2_code');
function create_ad_2_code(){
    return '<div class="cta mb-3">        
        <span>
            Anuncie sua bicicleta,
            <br>
            <b>Não pague comissões.</b> 
        </span>
        <a class="button" href="'.get_bloginfo('url').'/anunciar-bicicletas-e-pecas-gratis/" rel="noreferrer" title="Anuncie grátis">
            Anuncie grátis
        </a>
    </div>';
};
?>