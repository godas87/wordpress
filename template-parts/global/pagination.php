<?php 
global $wp_query;
$pagination_query = ( isset( $args['custom_query'] ) && !empty( $args['custom_query'] ) ) 
    ? $args['custom_query'] 
    : $wp_query;
// var_dump($pagination_query);
?>
<div class="row pagination pt-2 pb-2">
    <div class="s-12 col text-center reset">
        <?php wp_pagenavi( array( 'query' => $pagination_query ) ); ?>
    </div>
</div>