<div class="row align-center lg how-works-section">
  <div class="col s-11 m-12 xl-11 text-center">

        <?php 
        $titulos_principal = array(
            "Como Funciona",
            "Como Começar",
            "Passo a Passo",
            "Como Vender"
        );
        
        $subtitulos_principal = array(
            "Três passos simples para começar a vender",
            "Três etapas fáceis para anunciar seus produtos",
            "Siga três passos e comece a vender hoje",
            "Em três etapas você começa a vender"
        );
        
        $titulos_cadastro = array(
            "Cadastre-se",
            "Crie sua Conta",
            "Registre-se",
            "Faça seu Cadastro"
        );
        
        $titulos_anuncie = array(
            "Anuncie",
            "Crie seu Anúncio",
            "Publique",
            "Cadastre seu Produto"
        );
        
        $titulos_venda = array(
            "Venda",
            "Receba Contatos",
            "Feche Negócios",
            "Venda Rápido"
        );
        
        // Usar seleção determinística baseada na página (melhor para SEO)
        $titulo_principal = bazar_get_deterministic_variation($titulos_principal, 'how-works-titulo-principal');
        $subtitulo_principal = bazar_get_deterministic_variation($subtitulos_principal, 'how-works-subtitulo-principal');
        $titulo_cadastro = bazar_get_deterministic_variation($titulos_cadastro, 'how-works-titulo-cadastro');
        $titulo_anuncie = bazar_get_deterministic_variation($titulos_anuncie, 'how-works-titulo-anuncie');
        $titulo_venda = bazar_get_deterministic_variation($titulos_venda, 'how-works-titulo-venda');
        ?>
        
        <h2 class="section-title bold"><?php echo $titulo_principal; ?></h2>
        <p class="section-subtitle"><?php echo $subtitulo_principal; ?></p>

        <?php 
        $frases = array(
            array(
                "Crie seu cadastro e valide sua conta por e-mail.",
                "Registre-se e confirme sua conta através do e-mail.",
                "Faça seu cadastro e confirme sua conta por e-mail.",
                "Complete seu registro e valide a conta por meio do e-mail."
            ),
            array(
                "Crie seu anúncio. Escolha boas fotos e seja descritivo.",
                "Selecione imagens de qualidade e forneça informações detalhadas.",
                "Opte por fotos impactantes e inclua uma descrição completa.",
                "Cadastre e escolha imagens atrativas e descrição detalhada."
            ),
            array(
                "Seu anúncio aprovado, passa a ser exibido em nossa busca.",
                "Após a aprovação, seu anúncio será exibido em nossa busca.",
                "Uma vez aprovado, seu anúncio estará visível em nossa pesquisa.",
                "Após aprovação, seu anúncio é exibido nos resultados de busca."
            )
        );
        // Usar seleção determinística baseada na página (melhor para SEO)
        $fraseAleatoria_1 = bazar_get_deterministic_variation($frases[0], 'how-works-1');
        $fraseAleatoria_2 = bazar_get_deterministic_variation($frases[1], 'how-works-2');
        $fraseAleatoria_3 = bazar_get_deterministic_variation($frases[2], 'how-works-3');            
        ?>
        
        <div class="row collapse align-center">
            <div class="col s-12 m-4">
                <div class="step-card">
                    
                    <figure>
                        <img
                            src="<?php bloginfo('template_url');?>/assets/imgs/icon-1.svg"
                            alt="Cadastre-se"
                        />
                    </figure>
                    <h3 class="bold"><?php echo $titulo_cadastro; ?></h3>
                    <p><?php echo $fraseAleatoria_1;?></p>
                </div>
            </div>
            <div class="col s-12 m-4">
                <div class="step-card">
                    
                    <figure>
                        <img 
                            src="<?php bloginfo('template_url');?>/assets/imgs/icon-2.svg"
                            alt="Anuncie"
                        />
                    </figure>
                    <h3 class="bold"><?php echo $titulo_anuncie; ?></h3>
                    <p><?php echo $fraseAleatoria_2;?></p>
                </div>
            </div>
            <div class="col s-12 m-4">
                <div class="step-card">                    
                    <figure>
                        <img 
                            src="<?php bloginfo('template_url');?>/assets/imgs/icon-3.svg"
                            alt="Venda"
                        />
                    </figure>
                    <h3 class="bold"><?php echo $titulo_venda; ?></h3>
                    <p><?php echo $fraseAleatoria_3;?></p>
                </div>
            </div>
        </div>

  </div>
</div>