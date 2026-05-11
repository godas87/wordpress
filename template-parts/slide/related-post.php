<?php
global $seo;
global $schema;
global $exclude_posts_id;
// Obter dados SEO usando método público
// $seo_data = (isset($seo) && method_exists($seo, 'SEO_params')) ? $seo->SEO_params() : array();
// $seo_key = isset($seo_data['key']) ? $seo_data['key'] : '';

$tax = new RelatedTaxQuery(
    get_the_ID(),
    'post',
    12, // Converter string para int
    $exclude_posts_id
);
$new_query = $tax->getQuery(); // Usar getQuery() em vez de acessar $query diretamente
// Verifica se $new_query é um objeto válido e tem posts
if ($new_query && is_object($new_query) && isset($new_query->posts) && !empty($new_query->posts)):
    ?>
    <h2 id="view_itens" class="size-1">
        <?php
        /* 
        echo ( $seo && !empty($seo_key) && (is_category() || is_tax()) )
        ? __('Bicicleta Usadas e Novas em ' . $seo_key, 'bazar')
        : __('No Bazar Bikes você encontra Bicicleta Usada e Nova', 'bazar');
        */
        _e('Você também pode gostar', 'bazar');
        ?>
    </h2>
    <div id="related-slider" class="splide splide_related" role="related" aria-label="Bicicletas Usadas e Novas">
        <div class="splide__track">
            <ul class="splide__list">
                <?php
                $items = [];
                foreach ($new_query->posts as $key => $post):
                    $items[] = $post;
                    ?>
                    <li class="splide__slide">
                        <?php
                        get_template_part('template-parts/loop/product-card', null, array(
                            'post' => $post
                        )); ?>
                    </li>
                <?php
                endforeach;
                wp_reset_postdata();
                // Schema CollectionPage
                if ($schema && method_exists($schema, 'schema_CollectionProducts')) {
                    $schema->schema_CollectionProducts($items);
                }
                ?>
            </ul><!-- /splide__list -->
        </div><!-- /splide__track -->
    </div><!-- /related-slider -->
<?php endif; ?>