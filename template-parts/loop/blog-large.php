<?php
$post = isset( $args['post'] ) && !empty( $args['post'] ) 
  ? $args['post'] 
  : get_post( get_the_ID() );
$blog_id = isset( $post->ID ) && !empty( $post->ID ) 
  ? $post->ID 
  : get_the_ID();
$permalink = get_the_permalink( $blog_id );
$title = isset( $post->title ) && !empty( $post->title ) 
  ? $post->title 
  : get_the_title( $blog_id );
?>
<article class="row blog loop-large">
    <div class="col s-12 m-4 l-3">
        <figure>
            <?php $img = wp_get_attachment_image_src( get_post_thumbnail_id( $blog_id ), "m" ); ?>
            <a href="<?php echo $permalink; ?>" title="<?php echo $title; ?>">
                <img
                    alt="<?php echo $title; ?>"
                    src="<?php echo $img[0]; ?>"
                    width="<?php echo $img[1]; ?>" 
                    height="<?php echo $img[2]; ?>" 
                    title="<?php echo $title; ?>"
                />
            </a>
        </figure>
    </div>
    <div class="col s-12 m-8 l-9">
        <a href="<?php echo $permalink; ?>" title="<?php echo $title; ?>">
            <h3><?php echo $title; ?></h3>
        </a>        
        <?php the_excerpt(); ?>
        <!-- <a href="<?php echo $permalink; ?>" title="<?php echo $title; ?>">
            <small>Saiba mais</small>
        </a>         -->
    </div>
    <div class="col s-12">
        <hr />
    </div>
</article>