<?php
$total_results = array_key_exists('total_results', $args) ? $args['total_results'] : 0;
$posts_per_page = array_key_exists('posts_per_page', $args) ? $args['posts_per_page'] : 0;
$start = array_key_exists('start', $args) ? $args['start'] : 0;
$end = array_key_exists('end', $args) ? $args['end'] : 0;
$paged = array_key_exists('paged', $args) ? $args['paged'] : 1;
?>
<p class="mb-0">
    <?php       
    if ( $total_results < $posts_per_page ) :
        echo '<b>'.$total_results.'</b>';
    else :
    ?>
        <?php echo $start; ?> a  <?php echo ( $paged == 1 ) ? $posts_per_page : $end; ?>
        de <b><?php echo $total_results; ?></b>
    <?php endif;?>
    <?php echo pluralize_resultados($total_results); ?> | Página <?php echo $paged; ?>
</p>