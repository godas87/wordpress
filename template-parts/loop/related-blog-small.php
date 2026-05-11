<?php
global $seo;
global $schema;
global $exclude_posts_id;
$tax = new RelatedTaxQuery( 
    get_the_ID(), 
    'blog', 
    4, 
    $exclude_posts_id 
);
$query = $tax->getQuery();
if( $query ) :
?>
<div class="nav_context mb-1">
    <pre>Leia também</pre>
    <ul>
        <?php 
        $items = [];
        if ($query && is_object($query) && isset($query->posts) && !empty($query->posts)) :
        foreach ( $query->posts as $post ) :
            $items[] = $post;
            setup_postdata($post);
        ?>
        <li>
            <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                <?php the_title(); ?>
            </a>
        </li> 
        <?php 
        $exclude_posts_id[] = $post->ID;
        endforeach;
        endif;
        wp_reset_postdata();
        if (isset($schema) && is_object($schema) && method_exists($schema, 'schema_CollectionNewsArticle')) {
            $schema->schema_CollectionNewsArticle( $items );
        }
        ?>
    </ul>
</div>
<?php endif; ?>