<?php
global $schema;
// Buscar marcas usando função helper com cache
// 6 marcas, cache de 1 hora
$terms = bazar_get_brands_with_images(6);
if (!empty($terms)):
    ?>
    <h2 class="size-1">
        <?php _e('As melhores marcas você encontra no XXXXXX', 'bazar'); ?>
    </h2>
    <div class="row s-up-2 m-up-6 collapse brands">
        <?php
        $schema_brands = [];
        foreach ($terms as $term):

            $image = $term->imagem;
            $image_width = $term->imagem_width;
            $image_height = $term->imagem_height;
            // Verificar novamente se tem imagem (segurança)
            if (empty($image))
                continue;

            $url = (isset($term->term_permalink) && !empty($term->term_permalink))
                ? $term->term_permalink
                : get_term_link($term);

            if (is_wp_error($url))
                continue;

            ?>
            <div class="col">
                <div class="item text-center">
                    <figure>
                        <a href="<?php echo esc_url($url); ?>" title="Bicicletas e peças <?php echo esc_attr($term->name); ?>">
                            <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($term->name); ?>"
                                title="Bicicletas e peças <?php echo esc_attr($term->name); ?>"
                                width="<?php echo esc_attr($image_width); ?>" height="<?php echo esc_attr($image_height); ?>"
                                loading="lazy" />
                        </a>
                    </figure>
                    <h3 class="h5">
                        <?php echo esc_html($term->name); ?>
                    </h3>
                </div>
            </div>
            <?php
            if (isset($schema) && is_object($schema) && method_exists($schema, 'schema_Brand')) {
                $schema_brands[] = $schema->schema_Brand($term->name, $url, $image);
            }
        endforeach;
        ?>
        <?php
        if (isset($schema) && is_object($schema) && method_exists($schema, 'schema_Brands')) {
            $schema->schema_Brands($schema_brands);
        }
        ?>
    </div><!-- row -->
<?php endif; ?>