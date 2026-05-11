<div class="row align-center lg buy-section">
  <div class="col s-11">

    <?php
    $titulos_principal = array(
      "Crie seu Anúncio",
      "Anuncie Grátis",
      "Publique seu Produto",
      "Comece a Vender"
    );

    $titulos_gratis = array(
      "100% Grátis",
      "Totalmente Gratuito",
      "Sem Custos",
      "Grátis para Anunciar"
    );

    $titulos_aprovacao = array(
      "Aprovação Rápida",
      "Publicação Rápida",
      "Aprovação Ágil",
      "Publicação Imediata"
    );

    $titulos_publico = array(
      "Público Certo",
      "Público Qualificado",
      "Compradores Certos",
      "Audiência Segmentada"
    );

    $textos_gratis = array(
      "Anuncie quantas vezes quiser sem pagar nada. Sem taxas, sem comissões, sem pegadinhas. Você só paga se quiser impulsionar seu anúncio.",
      "Publique seus produtos sem custos. Zero taxas, zero comissões. A única cobrança é opcional, caso queira dar destaque ao seu anúncio.",
      "Anuncie gratuitamente quantas vezes precisar. Sem custos ocultos ou comissões. Você só investe se optar por impulsionar seu produto.",
      "Crie e publique anúncios sem pagar nada. Totalmente gratuito. Você só paga se desejar dar mais visibilidade ao seu produto."
    );

    $textos_aprovacao = array(
      "Seu anúncio é revisado rapidamente e publicado em poucas horas. Comece a receber contatos no mesmo dia.",
      "Aprovação ágil e publicação em tempo hábil. Receba contatos já nas primeiras horas após publicação.",
      "Processo de aprovação rápido e descomplicado. Seu anúncio estará no ar em poucos minutos.",
      "Aprovação em tempo hábil e publicação imediata. Comece a vender no mesmo dia que publicar."
    );

    $textos_publico = array(
      "Venda para ciclistas interessados em bicicletas e peças. Público segmentado que realmente quer comprar.",
      "Conecte-se com ciclistas que buscam bicicletas e componentes. Público qualificado e interessado em comprar.",
      "Alcance compradores qualificados que procuram bicicletas e acessórios. Público focado e engajado.",
      "Venda para compradores interessados e qualificados. Ciclistas que realmente querem comprar."
    );

    // Usar seleção determinística baseada na página (melhor para SEO)
    $titulo_principal = bazar_get_deterministic_variation($titulos_principal, 'buy-section-2-titulo-principal');
    $titulo_gratis = bazar_get_deterministic_variation($titulos_gratis, 'buy-section-2-titulo-gratis');
    $titulo_aprovacao = bazar_get_deterministic_variation($titulos_aprovacao, 'buy-section-2-titulo-aprovacao');
    $titulo_publico = bazar_get_deterministic_variation($titulos_publico, 'buy-section-2-titulo-publico');
    $texto_gratis = bazar_get_deterministic_variation($textos_gratis, 'buy-section-2-texto-gratis');
    $texto_aprovacao = bazar_get_deterministic_variation($textos_aprovacao, 'buy-section-2-texto-aprovacao');
    $texto_publico = bazar_get_deterministic_variation($textos_publico, 'buy-section-2-texto-publico');
    ?>

    <h2 class="section-title bold text-center mb-4"><?php echo $titulo_principal; ?></h2>

    <div class="row align-center lg">
      <div class="col s-12 m-6 l-4 pb-2">

        <div class="buy-feature-card">
          <div class="buy-feature-icon">
            <i class="fas fa-gift"></i>
          </div>
          <h3 class="bold"><?php echo $titulo_gratis; ?></h3>
          <p><?php echo $texto_gratis; ?></p>
        </div>
      </div>
      <div class="col s-12 m-6 l-4 pb-2">

        <div class="buy-feature-card">
          <div class="buy-feature-icon">
            <i class="fas fa-bolt"></i>
          </div>
          <h3 class="bold"><?php echo $titulo_aprovacao; ?></h3>
          <p><?php echo $texto_aprovacao; ?></p>
        </div>

      </div>
      <div class="col s-12 m-6 l-4 pb-2">

        <div class="buy-feature-card">
          <div class="buy-feature-icon">
            <i class="fas fa-users"></i>
          </div>
          <h3 class="bold"><?php echo $titulo_publico; ?></h3>
          <p><?php echo $texto_publico; ?></p>
        </div>

      </div>
    </div>
  </div>
</div>