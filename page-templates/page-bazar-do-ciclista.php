<?php
/**
 * Template Name: Bazar do Ciclista
 * 
 * Landing page otimizada para capturar tráfego do concorrente "bazardociclista.com.br"
 * Foco em SEO e conversão, destacando as vantagens do XXXXXX
 * 
 * @package XXXXXX
 */
get_header();
global $seo;
global $schema;

if (have_posts()):
  while (have_posts()):
    the_post();

    $promocao_ativa = function_exists('bazar_promocao_newsletter_ativa')
      ? bazar_promocao_newsletter_ativa()
      : false;
    ?>

    <section class="landing pt-2">

      <!-- Hero Section Específica -->
      <div class="row align-middle align-center lg">
        <div class="col s-11">
          <section class="hero-section hero-section-bazar-ciclista" aria-label="Hero Principal">
            <div class="hero-content lg">
              <h1 class="hero-title bold">
                Bazar do Ciclista: Classificado de Bicicletas Usadas e Novas
              </h1>
              <p class="hero-description">
                Encontre ou anuncie bicicletas, peças e acessórios no <strong>XXXXXX</strong>.
                <strong>100% grátis</strong>, sem comissões e com aprovação rápida. <br>
                A alternativa certa para compradores e vendedores de bicicletas.
              </p>
              <?php get_template_part('template-parts/landing/btn-cta'); ?>
            </div>
            <div class="hero-overlay"></div>
          </section>
        </div>
      </div>

      <!-- Seção de Diferenciação -->
      <div class="row align-center lg pt-6 pb-6">
        <div class="col s-11 m-10 l-8">
          <div class="text-center mb-4">
            <h2 class="section-title bold">Por que escolher o XXXXXX?</h2>
            <p class="section-subtitle">
              Somos um classificado especializado em ciclismo, com diversos anúncios ativos e uma comunidade crescente de
              ciclistas.
            </p>
          </div>
        </div>
      </div>

      <!-- Benefits Section -->
      <div class="pb-6">
        <?php get_template_part('template-parts/landing/benefits'); ?>
      </div>

      <!-- Como Funciona -->
      <div class="pb-10">
        <?php get_template_part('template-parts/landing/how-works'); ?>
      </div>

      <!-- Buy Section - Como Comprar -->
      <div class="pb-8">
        <?php get_template_part('template-parts/landing/buy-section'); ?>
      </div>

      <!-- Highlights Section -->
      <div class="pb-8">
        <?php get_template_part('template-parts/landing/buy-section-2'); ?>
        <?php get_template_part('template-parts/landing/cta'); ?>
      </div>

      <!-- Boost Section (se houver promoção) -->
      <?php if ($promocao_ativa): ?>
        <div class="pb-6">
          <?php get_template_part(
            'template-parts/landing/boost',
            null,
            array('promocao_ativa' => $promocao_ativa)
          ); ?>
        </div>
      <?php endif; ?>

      <!-- Slide de Destaques -->
      <div class="relative pb-6">
        <?php get_template_part('template-parts/landing/slide-destaques'); ?>
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