<?php
global $seo;
global $schema;
global $exclude_posts_id;

// Obter dados SEO usando método público
// $seo_data = (isset($seo) && method_exists($seo, 'SEO_params')) ? $seo->SEO_params() : array();

$tax = new RelatedTaxQuery( 
    get_the_ID(), 
    'blog', 
    12, // Converter string para int
    $exclude_posts_id    
);
$new_query = $tax->getQuery();
if( $new_query && is_object($new_query) && isset($new_query->posts) && !empty($new_query->posts) ) :
?>
<h2 id="view_itens" class="size-1">
    <?php 
    /*echo ( $seo && !empty($seo_key) && (is_category() || is_tax()) )
    ? __('Tudo sobre ' . $seo_key, 'bazar')
    : __('Tudo sobre Ciclismo', 'bazar'); */    
    _e('Leia também', 'bazar');
?>
</h2>
<div 
    id="related-blog" 
    class="splide splide_related" 
    role="related" 
    aria-label="Bicicletas Usadas e Novas"
>
    <div class="splide__track">
        <ul class="splide__list">
            <?php    
            $items = [];
            foreach ( $new_query->posts as $key => $post ) :
                $items[] = $post;
                setup_postdata($post); // Configurar o post global para o template part
            ?>
            <li class="splide__slide">
                <?php get_template_part('template-parts/loop/blog', null, array(
                    'post' => $post
                )); ?>
            </li>    
            <?php 
            endforeach; 
            wp_reset_postdata();
            // Schema CollectionPage
            if( $schema && method_exists($schema, 'schema_CollectionProducts') ) {
                $schema->schema_CollectionNewsArticle( $items );
            }
            ?>
        </ul><!-- /splide__list -->
    </div><!-- /splide__track -->
</div><!-- /related-slider -->
<?php endif; ?>