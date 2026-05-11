<!-- Hero Section - CTA Principal com Imagem -->
<?php
$promocao_ativa = isset($args) && is_array($args) && isset($args['promocao_ativa'])
  ? $args['promocao_ativa']
  : false;
$random_number = rand(1, 4);
$random_bg = "background-image: url('" . get_template_directory_uri() . "/assets/imgs/content/banner-{$random_number}.webp')";
?>
<div class="row align-middle align-center lg">
  <div class="col s-11">

    <section class="hero-section" aria-label="Hero Principal" style="<?php echo $random_bg; ?>">

      <?php
      if ($promocao_ativa):
        get_template_part('template-parts/landing/promo-banner');
      endif;
      ?>

      <div class="hero-content">

        <h1 class="hero-title bold">
          Querendo vender sua Bicicleta?
        </h1>

        <p class="hero-description">
          No <strong>XXXXXX</strong> você anuncia quantas vezes quiser, é <strong>seguro e rápido</strong>,
          e você fica com <strong></strong>100% do valor da venda</strong>, sem taxas nem comissões!
        </p>

        <?php get_template_part('template-parts/landing/btn-cta'); ?>

      </div>
      <div class="hero-overlay"></div>
    </section>

  </div>
</div>