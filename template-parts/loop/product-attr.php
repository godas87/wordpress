<?php 
// Reutilizar dados pré-processados quando disponíveis (otimização)
$card_data = isset( $args['card_data'] ) && !empty( $args['card_data'] ) 
  ? $args['card_data'] 
  : null;
$product_id = ( isset( $card_data['id'] ) && !empty( $card_data['id'] ) ) 
    ? $card_data['id'] 
    : get_the_ID();

// Extrair dados se já vierem pré-processados
$conservacao = ( $card_data && !empty( $card_data['taxonomies']['conservacao'] ) ) 
    ? $card_data['taxonomies']['conservacao'][0] 
    : null;
    
$material = ( $card_data && !empty($card_data['taxonomies']['material']) ) 
    ? $card_data['taxonomies']['material'][0] 
    : null;

$peso = ( $card_data && !empty($card_data['fields']['peso']) ) 
    ? $card_data['fields']['peso'] 
    : get_field('peso', $product_id);

$ano = ( $card_data && !empty($card_data['fields']['ano']) )
    ? $card_data['fields']['ano'] 
    : get_field('ano', $product_id);

// Fallback: buscar taxonomias se não vierem pré-processadas
if( !$conservacao && has_term('', 'conservacao', $product_id) ){
    $taxs = get_the_terms($product_id, 'conservacao');
    $conservacao = $taxs && !is_wp_error($taxs) ? $taxs[0] : null;
}
if( !$material ){
    $taxs = get_the_terms($product_id, 'material');
    $material = $taxs && !is_wp_error($taxs) ? $taxs[0] : null;
}
?>
<div class="product-attr regular">
    <?php if( $conservacao ) : ?>
    <span><?php echo esc_html($conservacao->name); ?></span>
    <?php endif; ?>
    
    <?php if( $material ) : ?>
    <span><?php echo esc_html($material->name); ?></span>
    <?php endif; ?>

    <?php if( $peso ) : ?>
    <span><?php echo esc_html($peso); ?> Kg </span>
    <?php endif; ?>
    
    <?php if( $ano ) : ?>
    <span><?php echo esc_html($ano); ?></span>
    <?php endif; ?>
</div><!-- /product-attr -->