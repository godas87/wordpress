<?php 
$alfabeto = get_terms('alfabeto');
if($alfabeto) :
?>
<div class="row pt-2">
    <?php foreach( $alfabeto as $letra ) : ?>                
    <div class="col s-12 m-4 pb-3">
        <div class="glossario">

            <?php
            echo '<h3 class="red">'.$letra->name.'</h3>';

            $args = array (
                'post_type' => 'glossario-ciclismo',
                'posts_per_page' => 5,
                'orderby' => 'rand',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'alfabeto',
                        'field' => 'slug',
                        'terms' => $letra->slug,
                    ),
                ),
            );
            $query = new WP_Query( $args );
            if ( $query->have_posts() ) : 
                echo '<ul>';
                while ( $query->have_posts() ) : $query->the_post();
                    echo '<li><a data-wpel-link="internal" rel="noopener noreferrer" href="'.get_the_permalink().'" title="'.get_the_title().'">'.get_the_title().'</a></li>';
                endwhile; wp_reset_postdata();
                echo '</ul>';
            endif;            
            ?>
            <a 
                class="button" 
                href="<?php echo get_term_link($letra);?>" 
                title="Ver todos"
            >
                Ver todos
            </a>
        </div>                    
    </div><!-- /col -->
    <?php endforeach; ?>
</div><!-- /row -->
<?php endif; ?>