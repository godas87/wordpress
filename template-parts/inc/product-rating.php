<?php
global $product_data;
$rating = ( isset($product_data['meta']['rating']) && !empty($product_data['meta']['rating']) )
    ? $product_data['meta']['rating'] 
    : get_post_meta( get_the_ID(), 'simple_rating', true );
// Verificar se é administrador
$is_admin = is_user_logged_in() && current_user_can('manage_options');
// Caso não tenha rating, usar valor padrão
if (!$rating || $rating == '') {
    $rating = 5; // Valor padrão
}
// Classe para desativar funcionalidade quando não for admin
$rating_class = 'rating' . (!$is_admin ? ' rating-disabled' : '');
?>
<div 
    id="rating" 
    class="<?php echo $rating_class; ?>" 
    data-postId="<?php echo $product_data['id']; ?>" 
    data-ratingValue="<?php echo $rating; ?>" 
    data-is-admin="<?php echo ($is_admin ? '1' : '0'); ?>"
>
    <small>Nossa Avaliação:</small>
    <?php 
    $output = '';
    for ($i = 1; $i <= 5; $i++) {
        $output .= '<span class="fas fa-star star ' . (($i <= $rating ) ? 'filled' : '') . '" data-rating="' . $i . '"></span>';
    };
    echo $output;
    ?>
</div>