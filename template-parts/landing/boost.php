<!-- Hero Section - CTA Principal com Imagem -->
<?php
$promocao_ativa = false;
// Obter preços, textos e porcentagem (cache estático)
$preco_normal = null;
$preco_desconto = null;
$desconto_percent = 10;
if (function_exists('bazar_destaque_get_promo_config')) {
  $promo_config = bazar_destaque_get_promo_config();
  $preco_normal = $promo_config['preco_normal'];
  $preco_desconto = $promo_config['preco_desconto_newsletter'];
  $desconto_percent = $promo_config['desconto_percent'];
  $promocao_ativa = !empty($promo_config['promocao_ativa']);
  $promo_titulo = isset($promo_config['titulo']) ? (string) $promo_config['titulo'] : '';
}
if (function_exists('bazar_destaque_get_preco') && $preco_normal === null) {
  $preco_normal = bazar_destaque_get_preco(false);
  $preco_desconto = bazar_destaque_get_preco(true);
}
?>
<div class="row align-middle align-center lg">
  <div class="col s-11">

    <section class="hero-section boost-section" aria-label="Hero Principal">

      <?php
      if ($promocao_ativa):
        get_template_part('template-parts/landing/promo-banner');
      endif;
      ?>
      <div class="hero-content">

        <h2 class="hero-title">
          Impulsione seu anúncio e venda mais rápido
        </h2>

        <div class="boost-pricing">
          <p class="pricing-main">
            <strong>Pagamento único:</strong>
            <span class="price-normal">R$ <?php echo number_format(
              $preco_normal,
              2,
              ',',
              '.'
            ); ?></span>
            <?php if ($promocao_ativa): ?>
              <span class="price-discount">
                <strong>Ou <?php //echo (int) $desconto_percent; ?></strong> <?php echo $promo_titulo; ?>
              </span>
            <?php endif; ?>
          </p>
        </div>

        <ul class="boost-benefits">
          <li>
            <i class="fas fa-trophy" aria-hidden="true"></i>
            <span>Apareça primeiro em todas as buscas</span>
          </li>
          <li>
            <i class="fas fa-eye" aria-hidden="true"></i>
            <span>Receba muito mais visualizações</span>
          </li>
          <li>
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            <span>Aumente suas chances de venda</span>
          </li>
        </ul>

        <p class="hero-description">
          <strong>Como funciona:</strong> Após criar e ter seu anúncio <strong>aprovado</strong>, você pode optar por
          <strong>impulsioná-lo</strong>.
          Ao impulsionar, seu anúncio aparece no topo das buscas até ser vendido.
        </p>

        <?php get_template_part('template-parts/landing/btn-cta'); ?>

      </div>
      <div class="hero-overlay"></div>
    </section>

  </div>
</div>