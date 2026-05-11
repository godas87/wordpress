<?php
/**
 * Template Name: Anunciar Grátis
 * 
 * Landing page moderna para captura de tráfego pago
 * Design minimalista estilo Google com foco em conversão
 * 
 * @package XXXXXX
 */
get_header();
if (have_posts()):
  while (have_posts()):
    the_post();

    $promocao_ativa = function_exists('bazar_promocao_newsletter_ativa')
      ? bazar_promocao_newsletter_ativa()
      : false;
    ?>

    <section class="landing pt-2">

      <?php get_template_part(
        'template-parts/landing/hero',
        null,
        array('promocao_ativa' => $promocao_ativa)
      ); ?>

      <div class="row align-middle align-center lg">
        <div class="col s-10 m-9 l-8 pt-8 pb-8">

          <h2 class="section-title bold">
            XXXXXX é um classificado especializado em ciclismo. O lugar ideal para anúnciar sua Bicicleta.
          </h2>

          <a href="<?php bloginfo('url'); ?>/anunciar/" class="button" title="Anunciar produto">
            Anunciar produto
          </a>

        </div>
      </div>

      <div class="pb-8">
        <?php get_template_part('template-parts/landing/benefits'); ?>
      </div>

      <div class="pb-8">
        <?php get_template_part('template-parts/landing/how-works'); ?>
      </div>

      <!-- Highlights Section -->
      <div class="pb-8">
        <?php get_template_part('template-parts/landing/buy-section-2'); ?>
        <?php get_template_part('template-parts/landing/cta'); ?>
      </div>

      <div class="pb-6">
        <?php get_template_part(
          'template-parts/landing/boost',
          null,
          array('promocao_ativa' => $promocao_ativa)
        ); ?>
      </div>

      <div class="relative pb-6">
        <?php get_template_part('template-parts/landing/slide-destaques'); ?>
      </div>

      <div class="pb-7">
        <?php get_template_part('template-parts/landing/faq'); ?>
      </div>

    </section>
  <?php endwhile; endif; ?>
<script>
  var __BAZAR_Page = 'vender';
</script>
<?php get_footer(); ?>