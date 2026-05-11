<?php 
$post = isset( $args['post'] ) && !empty( $args['post'] ) 
  ? $args['post'] 
  : get_post( get_the_ID() );

$product_id = $post->ID;
  
$url_code = get_the_permalink( $product_id );

$qrcode_image = 'https://quickchart.io/qr
    ?text='.esc_url( $url_code ).'
    &ecLevel=L
    &margin=1
    &size=200
    &centerImageUrl=https%3A%2F%2FXXXXXX%2Fsrc%2Fimgs%2Fbazar-bikes.png';
?>
<div id="screenshot-<?php echo $product_id; ?>" class="screenshot">
    <div class="tag bold">Vende-se</div>
    <?php get_template_part('template-parts/loop/product-card', null, array(
        'post' => $post
    )); ?>
    <div class="row align-center align-middle screenshot-ass">
        <div class="col shrink">
            <figure>
                <img 
                    src="<?php bloginfo('url')?>/src/imgs/bazar-bikes.png"
                    width="36"
                    height="36"
                    title="Bazar Bikes"
                    alt="Bazar Bikes" />
            </figure>
        </div>
        
        <?php if( $qrcode_image ) : ?>
        <div class="col shrink">            
            <figure>                
                <img 
                    src="<?php echo esc_url( $qrcode_image ); ?>" 
                    width="36" 
                    height="36" 
                    title="QR Code"
                    alt="QR Code" />
            </figure>
        </div>
        <?php endif; ?>

        <div class="s-auto col text-right">
            <div class="site bold">www.<span class="red">XXXXXX</span>.com.br</div>
        </div>            
    </div>
</div>