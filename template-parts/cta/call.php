<?php
$valid_router = is_page('anunciar-bicicletas-e-pecas-gratis') || is_page('quem-somos') || is_page('bazar-do-ciclista');
$link = ($valid_router)
  ? get_bloginfo('url') . '/anunciar/'
  : get_bloginfo('url') . '/anunciar-bicicletas-e-pecas-gratis/';
$rand = rand(1, 3);
// Config da promoção (título/subtítulo/percentual)
$promo_config = function_exists('bazar_destaque_get_promo_config') ? bazar_destaque_get_promo_config() : null;
?>
<div class="row align-center align-middle lg pb-6">

  <div class="col <?php echo ($valid_router) ? 's-11' : 's-11 l-12'; ?>">
    <div class="visual-section">
      <div class="visual-image template-<?php echo $rand; ?>"></div>
      <div class="visual-content">
        <h2 class="visual-title bold">Venda para quem realmente quer comprar</h2>
        <p class="visual-text">
          O Bazar Bikes foi feito para ciclistas, por ciclistas.
          Usuários qualificados visitam nosso site todos os dias procurando bicicletas, peças e acessórios.
        </p>

        <?php if (!empty($promo_config) && !empty($promo_config['promocao_ativa'])): ?>
          <div class="cta-footer-promo">
            <span class="cta-footer-promo__badge"><?php echo (int) $promo_config['desconto_percent']; ?>%</span>
            <?php
            // Usar subtítulo configurado ou uma frase genérica
            $cta_text = !empty($promo_config['subtitulo'])
              ? $promo_config['subtitulo']
              : 'Impulsione seu anúncio e aproveite a promoção.';
            ?>
            <p class="cta-footer-promo__text"><?php echo esc_html($cta_text); ?></p>
          </div>
        <?php endif; ?>

        <a href="<?php echo esc_url($link); ?>" class="button" title="Criar anúncio">Começar agora</a>
      </div>
    </div>
  </div>
</div>