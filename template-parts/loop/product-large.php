<?php 
$card_data = ( isset( $args['post'] ) && !empty( $args['post'] ) )
    ? bazar_get_product_card_data($args['post']->ID ) 
    : null;
// Extrair dados (já processados)
$permalink = $card_data['permalink'];
$title = $card_data['title'];
$status_data = $card_data['status_data'];
$location = $card_data['formatted']['location'];
$valor = $card_data['formatted']['valor'];
$featured_img = $card_data['images']['featured'];
$featured_img_m = $card_data['images']['featured_medium'];
$codigo = $card_data['id'];
$data = date('d/m/Y', strtotime($card_data['data']));
?>
<a 
    href="<?php echo esc_url($permalink); ?>" 
    title="<?php echo $title; ?>"
    target="_blank"
    rel="me noopenner noreferrer"
    class="row product regular"
>
    <figure class="s-12 m-5">
        <?php
        $size_w = ( wp_is_mobile() ) ? '375' : '540'; 
        $size_h = ( wp_is_mobile() ) ? '246' : '390';
        ?>
        <img 
            srcset="<?php echo esc_url($featured_img_m).' 400w,'.esc_url($featured_img).' 1000w'; ?>"
            alt="<?php echo esc_attr($title); ?>" 
            title="<?php echo esc_attr($title); ?>, Bazar Bikes" 
            src="<?php echo esc_url($featured_img); ?>"
            width="<?php echo $size_w ; ?>" height="<?php echo $size_h ; ?>" 
        />
        <?php 
        if( $status_data['is_vendido'] == true ) :
            get_template_part(
                'template-parts/inc/vendido-badge', null, array(
                    'small' => true
                )
            );
        elseif( $status_data['is_destaque'] == true ) :
            get_template_part(  
                'template-parts/inc/destaque-badge', null, array(
                    'small' => true
                )
            );
        endif;
        ?>
    </figure><!-- /col -->
    <div class="col s-12 m-7 box-border">        
            
        <?php 
        get_template_part('template-parts/loop/product-attr', null, array(
            'card_data' => $card_data
        )); 
        ?>
        
        <h2 class="product-name">
            <?php echo esc_html($title); ?>
        </h2>

        <?php if( $location ) : ?>
        <div class="location">            
            <span><?php echo esc_html($location); ?></span>
        </div>
        <?php endif; ?>

        <b class="price">
            <small>R$</small>
            <?php echo esc_html($valor); ?>
        </b>

    </div>
</a>