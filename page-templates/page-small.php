<?php 
/* Template Name: Small page*/
if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

<?php get_header(); ?>

<h1 class="d-none">
	<?php bloginfo('name');?> - <?php the_title(); ?>
</h1>

<?php medium_content(); ?>
		
    <h2 class="title large">
        <?php the_title(); ?>
    </h2>        
        
    <?php the_content(); ?>
                
<?php close_content(); ?>

<?php get_footer(); ?>

<?php endwhile; endif; ?>