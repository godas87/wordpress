<?php
/**
 * Bloco AdSense do footer. Exibido em todas as telas em produção (posts e páginas).
 * Não usar !is_page() aqui — senão o anúncio não aparece em páginas (ex.: home estática).
 *
 * O wrapper .x1x2xe já replica internamente o que .row + .col faziam
 * (max-width 70rem, centralizado, contexto flex). Sem essas propriedades
 * o loader do AdSense descarta o slot por largura "infinita" do <body>.
 * No mobile a padding lateral é zerada para o ad ocupar 100% da viewport.
 */
if (bazar_is_production()):
  bazar_adsense_script_once();
  ?>
  <div class="x1x2xe">
    <div class="row">
      <div class="s-12 col">
        <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-9613173072002426"
          data-ad-slot="<?php echo esc_attr(bazar_adsense_footer()); ?>" data-ad-format="auto"
          data-full-width-responsive="true"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
      </div>

    </div>
  </div>
<?php endif; ?>