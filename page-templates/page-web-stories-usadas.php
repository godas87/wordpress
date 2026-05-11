<?php
/* 
Template Name: Webstories Usadas 
Template Post Type: web-stories
*/
if (have_posts()):
  while (have_posts()):
    the_post();
    get_header();
    $page_image = (has_post_thumbnail(get_the_ID()))
      ? get_the_post_thumbnail_url(get_the_ID(), 'medium')
      : get_bloginfo('url') . '/src/imgs/web-stories/bg-bazar-bikes-red.jpg';
    $page_webstories = 0;
    ?>
    <amp-story standalone title="<?php the_title(); ?>" publisher="<?php bloginfo('name'); ?>"
      publisher-logo-src="<?php bloginfo('url'); ?>/src/imgs/bazar-bike.png"
      poster-portrait-src="<?php echo $page_image; ?>">
      <!-- Páginas de CAPA  -->
      <amp-story-page id="page-<?php echo $page_webstories++; ?>" class="clean" auto-advance-after="5s">
        <amp-story-grid-layer template="fill">

          <amp-img src="<?php echo $page_image; ?>" width="720" height="1280" layout="responsive">
          </amp-img>
        </amp-story-grid-layer>

        <amp-story-grid-layer template="fill">
          <amp-img src="<?php bloginfo('url'); ?>/src/imgs/web-stories/bg-red.png" width="720" height="1280"
            layout="responsive">
          </amp-img>
        </amp-story-grid-layer>

        <amp-story-grid-layer template="fill">
          <amp-img src="<?php bloginfo('url'); ?>/src/imgs/web-stories/bg-degrade.png" width="720" height="1280"
            layout="responsive">
          </amp-img>
        </amp-story-grid-layer>

        <amp-story-grid-layer template="vertical">

          <?php get_template_part('template-parts/loop/web-stories-logo'); ?>

          <div class="home">

            <h1 class="pb-1">
              <span class="head1">Procurando</span><br />
              <span class="head2"><?php the_title(); ?></span>
            </h1>

            <br>
            <small>Por Bazar Bikes em <?php echo the_time('d/m/Y'); ?></small>
            <br><br>
            <a href="<?php bloginfo('url'); ?>" class="amp-button"
              title="<?php _e('Classificado de Bicicletas', 'bazar'); ?>" animate-in="fly-in-left">
              <?php _e('www.XXXXXX', 'bazar'); ?>
            </a>
          </div>

        </amp-story-grid-layer>
      </amp-story-page>
      <!-- Páginas de CAPA  -->


      <!-- Páginas Content  -->
      <amp-story-page id="page-<?php echo $page_webstories++; ?>" class="clean" auto-advance-after="10s">

        <amp-story-grid-layer template="fill">

          <amp-img src="<?php echo $page_image; ?>" width="720" height="1280" layout="responsive">
          </amp-img>
        </amp-story-grid-layer>

        <amp-story-grid-layer template="fill">
          <amp-img src="<?php bloginfo('url'); ?>/src/imgs/web-stories/bg-degrade.png" width="720" height="1280"
            layout="responsive">
          </amp-img>
        </amp-story-grid-layer>

        <amp-story-grid-layer template="vertical">

          <?php get_template_part('template-parts/loop/web-stories-logo'); ?>

          <div class="home large" animate-in="fly-in-left">
            <?php the_content(); ?>
          </div>

        </amp-story-grid-layer>
      </amp-story-page>
      <!-- Páginas Content  -->

      <?php /*
 <amp-story-page id="page-<?php echo $page_webstories++; ?>" auto-advance-after="6s">
   <amp-story-grid-layer template="vertical">
     <?php get_template_part('template-parts/ads/sidebar-amazon-amp'); ?>
   </amp-story-grid-layer>
 </amp-story-page>
 */ ?>

      <?php
      // CONTEUDO
      $tax_id = (get_field('tax_id') == '')
        ? '12170'
        : get_field('tax_id');

      $tax_name = (get_field('tax_name') == '')
        ? 'category'
        : get_field('tax_name');

      $related_query[] = array(
        'taxonomy' => $tax_name,
        'field' => 'term_id',
        'terms' => $tax_id
      );

      $args = array(
        'post_type' => 'post',
        'posts_per_page' => 8,
        'orderby' => 'rand',
        'tax_query' => array(
          $related_query,
        )
      );
      $query = new WP_Query($args);
      if ($query->have_posts()):
        while ($query->have_posts()):
          $query->the_post();
          ?>
          <amp-story-page id="page-<?php echo $page_webstories; ?>" auto-advance-after="8s">

            <amp-story-grid-layer template="vertical" class="border">

              <?php get_template_part('template-parts/loop/web-stories-logo'); ?>

              <?php $img = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), "m"); ?>
              <amp-img src="<?php echo $img[0]; ?>" width="<?php echo $img[1]; ?>" height="<?php echo $img[2]; ?>"
                layout="fixed">
              </amp-img>

              <div class="content text-black">

                <p animate-in="fade-in">
                  <small>
                    <?php the_field('conservacao'); ?>
                    | <?php $cidade = get_the_terms(get_the_ID(), 'cidade');
                    echo $cidade[0]->name . ' / ' . $cidade[1]->name; ?>
                  </small>
                </p>

                <h1 class="text-1 pb-1" animate-in="fade-in">
                  <?php the_title(); ?>
                </h1>

                <div class="description" animate-in="fade-in">
                  <?php the_excerpt(); ?>
                </div>

                <hr class="mb-1" />

                <h2 class="pb-1" animate-in="fade-in">
                  <?php echo 'R$ ' . number_format(get_field('valor'), 2, ',', '.'); ?>
                </h2>

                <a href="<?php the_permalink(); ?>" class="amp-button" title="<?php _e('Ver anúncio', 'bazar'); ?>"
                  animate-in="fade-in">
                  <?php _e('VER ANÚNCIO', 'bazar'); ?>
                </a>
              </div><!-- /content -->

            </amp-story-grid-layer>

          </amp-story-page>
          <?php
          $page_webstories += 1;
        endwhile;
        wp_reset_postdata();
      endif;
      ?>

      <amp-story-page id="page-amz-ad" auto-advance-after="8s">
        <amp-story-grid-layer template="vertical">
          <?php get_template_part('template-parts/ads/afiliados/amp'); ?>
        </amp-story-grid-layer>
      </amp-story-page>

      <?php get_template_part('template-parts/loop/web-stories-last'); ?>

      <amp-analytics type="googleanalytics" id="analytics1">
        <script type="application/json">
        {
          "vars": {
            "account": "UA-65696156-1"
          },
          "triggers": {
            "trackPageview": {
              "on": "visible",
              "request": "pageview"
            }
          }
        }
        </script>
      </amp-analytics>

    </amp-story>
    </body>

    </html>
  <?php endwhile; endif; ?>