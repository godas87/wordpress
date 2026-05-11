<!-- Buy Section - Como Comprar -->

<div class="row align-center lg buy-section">
  <div class="col s-11 m-12 xl-11">

    <div class="row align-center lg ">
      <div class="col s-11 m-10 l-9 text-center pb-4">

        <?php
        $titulos_principal = array(
          "Como Comprar",
          "Como Encontrar o que Procura",
          "Guia de Compra",
          "Dicas para Comprar"
        );

        $subtitulos_principal = array(
          "Encontre exatamente o que você procura com nossos filtros avançados e busque por categoria",
          "Use nossos filtros inteligentes para localizar produtos ideais na sua região",
          "Descubra produtos perfeitos utilizando nossos recursos de busca avançada",
          "Navegue facilmente e encontre o que precisa com nossos filtros personalizados"
        );

        $titulos_filtros = array(
          "Filtros Avançados",
          "Busca Inteligente",
          "Filtros Personalizados",
          "Pesquisa Avançada"
        );

        $titulos_localizacao = array(
          "Busca por Localização",
          "Encontre na sua Região",
          "Produtos Próximos",
          "Busca por Cidade"
        );

        $titulos_verificados = array(
          "Anúncios Verificados",
          "Anúncios Confiáveis",
          "Produtos Verificados",
          "Anúncios Seguros"
        );

        // Usar seleção determinística baseada na página (melhor para SEO)
        $titulo_principal = bazar_get_deterministic_variation($titulos_principal, 'buy-titulo-principal');
        $subtitulo_principal = bazar_get_deterministic_variation($subtitulos_principal, 'buy-subtitulo-principal');
        $titulo_filtros = bazar_get_deterministic_variation($titulos_filtros, 'buy-titulo-filtros');
        $titulo_localizacao = bazar_get_deterministic_variation($titulos_localizacao, 'buy-titulo-localizacao');
        $titulo_verificados = bazar_get_deterministic_variation($titulos_verificados, 'buy-titulo-verificados');
        ?>

        <h2 class="section-title bold"><?php echo $titulo_principal; ?></h2>
        <p class="section-subtitle"><?php echo $subtitulo_principal; ?></p>

      </div>

      <?php
      $textos_filtros = array(
        "Use nossos filtros para encontrar exatamente o que procura: por categoria, modalidade, marca, modelo, conservação, material, cor, gênero, idade e muito mais.",
        "Refine sua busca com filtros detalhados: escolha categoria, modalidade, marca, modelo, estado de conservação, material do quadro, cor, gênero e faixa etária.",
        "Navegue com precisão usando nossos filtros avançados: filtre por tipo de produto, modalidade, marca e modelo, conservação, material, cor e outras características.",
        "Encontre o produto ideal utilizando nossos filtros: selecione categoria, modalidade, marca, modelo, estado, material, cor, gênero e muito mais."
      );

      $textos_localizacao = array(
        "Filtre por cidade e estado para encontrar produtos próximos a você. Facilita a negociação e reduz custos de entrega.",
        "Busque produtos na sua região filtrando por cidade e estado. Negocie pessoalmente e economize com frete.",
        "Encontre anúncios perto de você usando o filtro de localização. Combine encontros presenciais e evite gastos com envio.",
        "Localize vendedores na sua área através dos filtros de cidade e estado. Facilite a negociação e reduza custos de transporte."
      );

      $textos_verificados = array(
        "Todos os anúncios passam por moderação antes da publicação, garantindo qualidade e confiabilidade.",
        "Cada anúncio é revisado por nossa equipe antes de ser publicado, assegurando conteúdo de qualidade e confiança.",
        "Nossa moderação verifica todos os anúncios antes da publicação, garantindo informações precisas e confiáveis.",
        "Anúncios são moderados antes de serem exibidos, assegurando que apenas conteúdo de qualidade seja publicado."
      );

      // Usar seleção determinística baseada na página (melhor para SEO)
      $texto_filtros = bazar_get_deterministic_variation($textos_filtros, 'buy-filtros');
      $texto_localizacao = bazar_get_deterministic_variation($textos_localizacao, 'buy-localizacao');
      $texto_verificados = bazar_get_deterministic_variation($textos_verificados, 'buy-verificados');
      ?>

      <div class="col s-11 m-4 pb-2">

        <div class="buy-feature-card">
          <div class="buy-feature-icon">
            <i class="fas fa-filter"></i>
          </div>
          <h3 class="bold"><?php echo $titulo_filtros; ?></h3>
          <p><?php echo $texto_filtros; ?></p>
        </div>

        <a href="<?php bloginfo('url') ?>/bicicleta-modalidade/mountain-bike-mtb/"
          title="Encontre Bicicletas Mountain Bike" class="feature-card">
          <i class="fas fa-angle-right"></i>
          Bicicletas Mountain Bike
        </a>

      </div>
      <div class="col s-11 m-4 pb-2">

        <div class="buy-feature-card">
          <div class="buy-feature-icon">
            <i class="fas fa-map-marker-alt"></i>
          </div>
          <h3 class="bold"><?php echo $titulo_localizacao; ?></h3>
          <p><?php echo $texto_localizacao; ?></p>
        </div>

        <a href="<?php bloginfo('url') ?>/bike/bicicleta/" title="Encontre Bicicletas de Estrada" class="feature-card">
          <i class="fas fa-angle-right"></i>
          Encontre Bicicletas na sua cidade
        </a>

      </div>
      <div class="col s-11 m-4 pb-2">

        <div class="buy-feature-card">
          <div class="buy-feature-icon">
            <i class="fas fa-search"></i>
          </div>
          <h3 class="bold"><?php echo $titulo_verificados; ?></h3>
          <p><?php echo $texto_verificados; ?></p>
        </div>

        <a href="<?php bloginfo('url') ?>/bicicleta-modalidade/speed/" title="Encontre Bicicletas de Estrada"
          class="feature-card">
          <i class="fas fa-angle-right"></i>
          Bicicletas de Estrada
        </a>

      </div>
    </div>

  </div>
</div>