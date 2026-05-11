<div class="row">
    <div class="s-12 l-5 col text-left">

        <div class="share">
            <small><?php _e('Compartilhe', 'bazar'); ?>: </small>
            <?php get_template_part('template-parts/inc/share'); ?>
        </div>

    </div>
    <div class="s-12 l-7 col">

        <?php get_template_part('template-parts/inc/product-rating'); ?>

    </div>
    <div class="s-12 col">

        <hr>

    </div>
    <div class="s-9 col">

        Código: <b><?php echo get_the_ID(); ?></b> | <?php the_time('d/m/Y'); ?>
        <time datetime="<?php the_time('Y'); ?>"></time>

    </div>
    <div class="s-3 text-right col pl-0">

        <?php
        $views = get_post_meta(get_the_ID(), 'post_views_count', true);
        if ($views != ''):
            ?>
            <small>
                <?php echo $views; ?>
                <span class="fas fa-eye inline-block" style="padding-left: .25rem;"></span>
            </small>
        <?php endif; ?>

    </div>
</div>