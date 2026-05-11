<?php
/**
 * Template Name: Quem Somos
 * 
 * Página institucional sobre o Bazar Bikes
 * Design moderno e informativo
 * 
 * @package XXXXXX
 */
get_header();
global $seo;
global $schema;

if (have_posts()):
  while (have_posts()):
    the_post(); ?>

    <section class="landing pt-2">

      <div class="row align-middle align-center lg">
        <div class="col s-11">

          <section class="hero-section hero-section-about" aria-label="Hero Principal">
            <div class="hero-content lg">

              <h1 class="hero-title bold">
                Bazar Bikes, classificado de bicicletas novas e usadas.
              </h1>

              <p class="hero-description">
                No <strong>XXXXXX</strong> você anuncia quantas vezes quiser, é <strong>seguro e
                  grátis</strong>,
                não cobramos comissões!
              </p>

              <?php get_template_part('template-parts/landing/btn-cta'); ?>

            </div>
            <div class="hero-overlay"></div>
          </section>

        </div>
      </div>

      <?php if (!empty(get_the_content())): ?>
        <div class="row align-center lg pt-6 pb-6">
          <div class="col s-11 m-10 l-8">

            <h2 class="section-title bold">
              <?php the_title(); ?>
            </h2>

            <div class="section-subtitle">
              <?php the_content(); ?>
            </div>

          </div>
        </div>
      <?php endif; ?>

      <!-- Benefits Section -->
      <div class="pb-6">
        <?php get_template_part('template-parts/landing/benefits'); ?>
      </div>

      <!-- How Works Section -->
      <div class="pb-10">
        <?php get_template_part('template-parts/landing/how-works'); ?>
      </div>

      <!-- Buy Section - Como Comprar -->
      <div class="pb-8">
        <?php get_template_part('template-parts/landing/buy-section'); ?>
      </div>

      <!-- FAQ Section -->
      <div class="pb-8">
        <?php get_template_part('template-parts/landing/faq'); ?>
      </div>

    </section>
  <?php endwhile; endif; ?>

<script type="text/javascript">
  var __BAZAR_Page = 'landing';
</script>
<?php get_footer(); ?>