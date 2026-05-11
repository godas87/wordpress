<?php 
/* Template Name: Links Úteis */
if ( have_posts() ) : while ( have_posts() ) : the_post();
get_header(); 
?>

<h1 class="d-none">
	<?php bloginfo('name');?> - <?php the_title(); ?>
</h1>

<div class="row align-center page page-links">
    <div class="s-11 l-12 col">

        <div class="box-content mb-2">

            <h2 class="title">
                <?php the_title(); ?>
            </h2>
            
            <?php the_content(); ?>

        </div>

        <div class="box-content">
            <?php 
            $terms = get_terms( 'marca-modelo', array(
                'hide_empty' => false,
                'orderby' => 'term_order',
            ));
            if( $terms ) : 
            ?>  
            <h3 class="title">
                As melhores marcas você encontra aqui
            </h3>

            <div class="row align-center collapse brands pb-3 pt-1">
                <?php
                $key = 0;
                foreach( $terms as $term ) :

                    //var_dump($term->parent);                
                    if( $term->parent > 0) continue;  
                    //if( $key > 5 ) break;          

                    $image = get_field('imagem', $term);		        
                    if( $image ): 
                        $image_url = wp_get_attachment_image_src( $image, array('200','200'), true );
                        //$url = get_term_link( $term );
                        $url = get_field('url', $term);
                ?>
                <div class="col s-6 m-3 l-2">
                    <div class="item text-center">
                        <figure>
                            <a 
                                href="<?php echo esc_url($url); ?>" 
                                title="Bicicletas e peças <?php echo $term->name; ?>"
                            >
                                <img 
                                    src="<?php echo $image_url[0]; ?>" 
                                    width="<?php echo $image_url[1]; ?>" 
                                    height="<?php echo $image_url[2]; ?>" 
                                    alt="<?php echo $term->name; ?>"/>
                            </a>
                        </figure>
                        <span>
                            <a 
                                href="<?php echo esc_url($url); ?>" 
                                title="Bicicletas e peças <?php echo $term->name; ?>"
                                class="regular black"
                            >
                                <?php echo $term->name; ?>
                            </a>
                        </span>
                    </div>
                </div>
                <?php 
                    $key ++;
                    endif;
                endforeach;
                ?>
            </div><!-- row -->
            <?php endif; ?>

        </div><!-- /box-content -->

<?php close_content(); ?>
 
<?php get_footer(); endwhile; endif; ?>