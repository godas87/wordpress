<div class="row align-center lg benefits-section">
  <div class="col s-11 l-12 xl-11">

    <?php
    $titulos_gratis = array(
      "100% Grátis",
      "Totalmente Gratuito",
      "Sem Custos",
      "Grátis para Anunciar"
    );

    $titulos_publico = array(
      "Público Segmentado",
      "Público Qualificado",
      "Compradores Certos",
      "Audiência Segmentada"
    );

    $titulos_rapido = array(
      "Rápido e Fácil",
      "Simples e Rápido",
      "Fácil e Rápido",
      "Processo Ágil"
    );

    $textos_gratis = array(
      "Sem taxas, sem comissões, sem pegadinhas. Você só paga se quiser impulsionar seu anúncio.",
      "Totalmente gratuito para anunciar. Sem custos ocultos ou comissões. Pague apenas se desejar dar destaque ao seu anúncio.",
      "Anuncie sem custos. Zero taxas, zero comissões. A única cobrança é opcional, caso queira impulsionar seu produto.",
      "100% gratuito para criar e publicar anúncios. Você só investe se optar por dar mais visibilidade ao seu produto."
    );

    $textos_publico = array(
      "Venda para ciclistas interessados em bicicletas e peças. Público qualificado que realmente quer comprar.",
      "Conecte-se com ciclistas que buscam bicicletas e componentes. Público segmentado e interessado em comprar.",
      "Alcance compradores qualificados que procuram bicicletas e acessórios. Público focado e engajado.",
      "Venda para um público específico de ciclistas em busca de produtos. Compradores interessados e qualificados."
    );

    $textos_rapido = array(
      "Crie seu anúncio em minutos. Aprovação rápida e publicação imediata. Comece a vender hoje mesmo!",
      "Anuncie rapidamente: cadastro simples, aprovação ágil e publicação instantânea. Venda já no mesmo dia!",
      "Processo rápido e descomplicado. Crie, publique e comece a receber contatos em poucos minutos.",
      "Anuncie de forma simples e rápida. Aprovação em tempo hábil e publicação imediata. Venda hoje mesmo!"
    );

    // Usar seleção determinística baseada na página (melhor para SEO)
    $titulo_gratis = bazar_get_deterministic_variation($titulos_gratis, 'benefits-titulo-gratis');
    $titulo_publico = bazar_get_deterministic_variation($titulos_publico, 'benefits-titulo-publico');
    $titulo_rapido = bazar_get_deterministic_variation($titulos_rapido, 'benefits-titulo-rapido');
    $texto_gratis = bazar_get_deterministic_variation($textos_gratis, 'benefits-gratis');
    $texto_publico = bazar_get_deterministic_variation($textos_publico, 'benefits-publico');
    $texto_rapido = bazar_get_deterministic_variation($textos_rapido, 'benefits-rapido');
    ?>

    <div class="row align-center lg">
      <div class="col s-12 m-6 l-4">
        <div class="benefit-card">
          <figure>
            <img
              src="<?php echo get_template_directory_uri(); ?>/assets/imgs/content/benefit-a-<?php echo rand(1, 3); ?>.webp"
              alt="100% Grátis">
            <h3 class="bold"><?php echo $titulo_gratis; ?></h3>
          </figure>
          <div class="benefit-content">
            <p><?php echo $texto_gratis; ?></p>
          </div>
        </div>
      </div>
      <div class="col s-12 m-6 l-4">
        <div class="benefit-card">
          <figure>
            <img
              src="<?php echo get_template_directory_uri(); ?>/assets/imgs/content/benefit-b-<?php echo rand(1, 3); ?>.webp"
              alt="100% Grátis">
            <h3 class="bold"><?php echo $titulo_publico; ?></h3>
          </figure>
          <div class="benefit-content">
            <p><?php echo $texto_publico; ?></p>
          </div>
        </div>
      </div>
      <div class="col s-12 l-4">
        <div class="benefit-card">
          <figure class="benefit-image">
            <img
              src="<?php echo get_template_directory_uri(); ?>/assets/imgs/content/benefit-c-<?php echo rand(1, 3); ?>.webp"
              alt="100% Grátis">
            <h3 class="bold"><?php echo $titulo_rapido; ?></h3>
          </figure>
          <div class="benefit-content">
            <p><?php echo $texto_rapido; ?></p>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>