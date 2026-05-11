<?php 
$card_data = ( isset( $args['post'] ) && !empty( $args['post'] ) )
    ? bazar_get_product_card_data( $args['post']->ID ) 
    : null;
// Extrair dados (já processados)
$permalink = $card_data['permalink'];
$title = $card_data['title'];
$status_data = $card_data['status_data'];
$location = $card_data['formatted']['location'];
$valor = $card_data['formatted']['valor'];
$featured_img = $card_data['images']['featured_medium'];
?>
<a 
    href="<?php echo esc_url($permalink); ?>" 
    title="<?php echo esc_attr($title); ?>" 
    target="_blank"
    rel="me noopenner noreferrer"
    class="product card regular"
>
    <figure>
        <img 
            width="320" 
            height="220" 
            alt="<?php echo esc_attr($title); ?>" 
            title="<?php echo esc_attr($title); ?>, Bazar Bikes" 
            src="<?php echo esc_url($featured_img); ?>"
        />
        <?php         
        if( $status_data['is_vendido'] == true ) :
            get_template_part( 'template-parts/inc/vendido-badge', null,  array(
                'small' => true
            ));

        elseif( $status_data['is_destaque'] == true ) :
            get_template_part( 'template-parts/inc/destaque-badge', null, array(
                'small' => true
            ));
        
        endif;
        ?>
    </figure>                
    <div class="space">
        <?php 
        get_template_part('template-parts/loop/product-attr', null, array(
            'card_data' => $card_data
        )); 
        ?>
        <h3 class="product-name">
            <?php echo esc_html($title); ?>
        </h3>
        <?php if( $location ) : ?>
        <div class="location">
            <i class="fas fa-location-dot icon"></i>
            <span><?php echo esc_html($location); ?></span>
        </div>
        <?php endif; ?>
    </div>
    <b class="price">
        <small>R$</small>
        <?php echo esc_html($valor); ?>
    </b>
</a>