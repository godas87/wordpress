<?php 
global $schema;
$brads = (isset($schema) && is_object($schema) && isset($schema->breadcrumb)) ? $schema->breadcrumb : null;
if( $brads ) : ?>                
<ul class="cat_list">
    <?php 
    $total_items = count( $brads );
    foreach( $brads as $index => $brad ) : 
        $is_last_item = ( $index === $total_items - 1 );
    ?>
    <li>
        <a 
            href="<?php echo esc_url($brad['url']); ?>"
            title="Anúncios em <?php echo esc_attr($brad['name']); ?>"
            class="regular"
        ><?php echo $brad['name']; ?></a>
        <?php echo (!$is_last_item) ? ' > ' :  ''; ?>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif;  ?>