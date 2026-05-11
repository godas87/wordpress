<?php 
$componentes_ordenados = bazar_get_componentes_default();
if ( $componentes_ordenados && !empty($componentes_ordenados) ) :
?>
<h2 class="size-1">
    <?php _e('Busque por componentes', 'bazar'); ?>
</h2>
<div class="row s-up-2 m-up-4 l-up-8 collapse">
    <?php
    $schema_components = [];
    
    foreach ($componentes_ordenados as $term) :
                
        $image = get_field('imagem', $term);
        $url = get_term_link($term);
        
        if (is_wp_error($url)) {
            continue;
        }
        
        // Se não tiver imagem, usar ícone ou placeholder
        $image_url = null;
        $image_width = 200;
        $image_height = 200;
        
        if (!empty($image)) {
            $image_data = wp_get_attachment_image_src($image, array('200', '200'), true);
            if ($image_data) {
                $image_url = $image_data[0];
                $image_width = $image_data[1];
                $image_height = $image_data[2];
            }
        }
        
        // Se não tiver imagem, tentar buscar ícone
        $icon = null;
        if (empty($image_url) && class_exists('__Bazar_Terms_Manager')) {
            $icon = __Bazar_Terms_Manager::get_term_icon($term);
            // Se o ícone for apenas o padrão (cog), considerar como vazio
            if (!empty($icon) && strpos($icon, 'fa-cog') !== false && strlen($icon) < 50) {
                $icon = null;
            }
        }
    ?>
    <div class="col">
        <div class="item text-center componente-card">        
            <figure>
                <a 
                    href="<?php echo esc_url($url); ?>" 
                    title="<?php echo esc_attr($term->name); ?>"
                    class="componente-link"
                >
                    <?php if (!empty($image_url)) : ?>
                        <img 
                            src="<?php echo esc_url($image_url); ?>" 
                            width="<?php echo esc_attr($image_width); ?>" 
                            height="<?php echo esc_attr($image_height); ?>" 
                            alt="<?php echo esc_attr($term->name); ?>"
                            title="<?php echo esc_attr($term->name); ?>"
                            class="componente-image"
                        />
                    <?php elseif (!empty($icon)) : ?>
                        <div class="componente-icon">
                            <?php echo $icon; ?>
                        </div>
                    <?php else : ?>
                        <div class="componente-placeholder">
                            <i class="fas fa-cog"></i>
                        </div>
                    <?php endif; ?>
                </a>
            </figure>
            <h3 class="h5 componente-name">
                <?php echo esc_html($term->name); ?>
            </h3>
        </div>
    </div>
    <?php endforeach; ?>
</div><!-- row componentes-grid -->
<?php endif; ?>
