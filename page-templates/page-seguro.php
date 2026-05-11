<?php
/* Template Name: Seguro para Bicicletas */
get_header();
if ( have_posts() ) : while ( have_posts() ) : the_post();
$term = get_queried_object();
?>

<section class="vender seguro pb-3">

    <div class="bg-white pt-5 pb-4">

      <div class="row align-middle pb-4">
        <figure class="col s-order-1 m-order-2">
            <img 
                src="https://XXXXXX.aride.com.br/wp-content/uploads/2025/08/banner-1024x634.png" 
                alt="Ciclista"
                title="<?php bloginfo('name'); ?> anuncie grátis" 
                width="800" 
                height="495"
            >
        </figure><!-- /col -->
        <div class="s-12 m-6 l-5 col s-order-2 m-order-1">

            <h1 class="title title-clear">
              Conheça os nossos benefícios                         
            </h1>
                            
            <p class="large">
            Associe-se agora ao nosso clube e conte com os melhores benefícios para você e o sua Bike.
            </p>
            
            <a 
                href="<?php bloginfo('url');?>/anunciar/" 
                title="Criar Anúncio" 
                class="button secondary-light mr-1"
            >
                <i class="fas fa-check reset"></i>
                <span class="regular">ASSOCIE-SE</span> AGORA
            </a>

            <a 
                href="<?php bloginfo('url');?>/anunciar/" 
                title="Criar Anúncio" 
                class="button secondary-light"
            >
                <i class="fas fa-check reset"></i>
                <span class="regular">ASSISTIR</span> VÍDEO
            </a>

        </div><!-- /col -->
      </div><!-- /box-content -->

        <div class="bg-secondary pb-1 pt-2 pl-3 pr-3 mb-3">
            <div class="row s-up-1 m-up-3 align-center">
                <div class="col text-center">
                    <h2 class="white">
                      <span class="fas fa-clock pr-1"></span>
                      Assitência 24h
                    </h2>
                </div>
                <div class="col text-center upper">
                    <h2 class="white">
                      <span class="fas fa-shield-alt pr-1"></span>
                      Seguro
                    </h2>
                </div>
                <div class="col text-center upper">
                    <h2 class="white">
                      <span class="fas fa-map-marker-alt pr-1"></span>
                      Monitoramento
                    </h2>
                </div>
            </div>
        </div>

        <div class="row align-center">
            <div class="col s-10 m-6 l-5 text-center">
                <h2 class="title">
                  Como funciona o seguro para bicicletas?
                </h2>
                <p class="large">
                  Serviços digitais <br>e sem burocracia.
                </p>
            </div>
            <div class="col s-10 m-11">

                <?php if ( !empty( get_the_content() ) ) : ?>
                <div class="description pb-4" itemprop="description">
                    <?php the_content(); ?>
                </div>
                <?php endif; ?>

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
                        "Elabore seu anúncio. Seleciona imagens de qualidade e forneça informações detalhadas.",
                        "Faça seu anúncio. Opte por fotos impactantes e inclua uma descrição completa.",
                        "Desenvolva seu anúncio. Escolha imagens atrativas e forneça uma descrição detalhada."
                    ),
                    array(
                        "Seu anúncio aprovado, passa a ser exibido em nossa busca.",
                        "Após a aprovação, seu anúncio será exibido em nossa busca.",
                        "Uma vez aprovado, seu anúncio estará visível em nossa pesquisa.",
                        "Com a aprovação, seu anúncio será apresentado em nossos resultados de busca."
                    )
                );
                $fraseAleatoria_1 = $frases[0][array_rand($frases[0])];
                $fraseAleatoria_2 = $frases[1][array_rand($frases[1])];
                $fraseAleatoria_3 = $frases[2][array_rand($frases[2])];            
                ?>
                <div class="row align-center grid pb-4">
                    <div class="col s-8 m-4 pb-4 item">
                        <figure>
                            <img
                                src="<?php bloginfo('template_url');?>/assets/imgs/icon-1.svg"
                                alt="Cadastre-se"
                            />
                        </figure>
                        <h3>Cadastre-se</h3>
                        <p><?php echo $fraseAleatoria_1;?></p>
                    </div>
                    <div class="col s-8 m-4 pb-4 item">
                        <figure>
                            <img 
                                src="<?php bloginfo('template_url');?>/assets/imgs/icon-2.svg"
                                alt="Anuncie"
                            />
                        </figure>
                        <h3>Anuncie</h3>
                        <p><?php echo $fraseAleatoria_2;?></p>
                    </div>
                    <div class="col s-8 m-4 pb-4 item">
                        <figure>
                            <img 
                                src="<?php bloginfo('template_url');?>/assets/imgs/icon-3.svg"
                                alt="Venda"
                            />
                        </figure>
                        <h3>Venda</h3>
                        <p><?php echo $fraseAleatoria_3;?></p>
                    </div>
                </div><!-- /row -->

            </div><!-- /col -->        
        </div><!-- /row -->

        
        <div class="row align-center collapse higlights mb-4">
            <div class="col s-12 m-6">

                <figure class="mb-0">
                    <img 
                        src="<?php bloginfo('template_url');?>/assets/imgs/content/onde-vender-bicicletas-<?php echo rand(1,3);?>.webp"
                        alt="Ciclismo" 
                        title="Vender bicicletas, anuncie grátis"
                    >
                </figure><!-- /col -->

            </div>
            <div class="col s-12 m-6 bg-secondary">
                <div class="bx">
                    
                    <div>
                        <span class="text text-1">3 Motivos</span>
                    </div>
                    <div>
                        <span class="text text-2">para anunciar no</span>
                    </div>
                    <span class="text text-2">Bazar Bikes</span>
                    
                    <?php 
                    $motivos = array(
                        array(                    
                            "Não pague comissões.",
                            "Zero Taxas, 100% grátis.",
                            "Não cobramos comissões."
                        ),
                        array(
                            "Venda para ciclistas, grátis.",
                            "Nosso site é 100% para ciclismo.",
                            "Anuncie diretamente para ciclistas."
                        ),
                        array(
                            "Anuncie com segurança, é rápido.",
                            "É rápido e seguro criar seu anúncio.",
                            "É fácil e rápido criar seu anúncio."
                        )
                    );
                    
                    $motivos_1 = $motivos[0][array_rand($motivos[0])];
                    $motivos_2 = $motivos[1][array_rand($motivos[1])];
                    $motivos_3 = $motivos[2][array_rand($motivos[2])];
                    ?>
                    <ul>
                        <li>
                            <span>1. <?php echo $motivos_1; ?></span>
                        </li>
                        <li>
                            <span>2. <?php echo $motivos_2; ?></span>
                        </li>
                        <li>
                            <span>3. <?php echo $motivos_3; ?></span>
                        </li>
                    </ul>
                    
                    <a 
                        href="<?php bloginfo('url')?>/anunciar/" 
                        class="button secondary-transparent"
                        title="Criar anúncio"
                    >
                        Criar meu anúncio
                    </a>

                </div><!-- bx -->
            </div>
        </div><!-- higlights -->
                    
                
        <div class="text-center pt-2 pb-1 mb-2">
            <h2 class="green">
                Perguntas Frequentes
            </h2>
            <hr />
        </div>

        <div class="row align-center faq">        
            <div class="col s-11 m-5 pb-3">
                <b>O que é o Bazar Bikes?</b>
                <p>Uma plataforma digital criada para ajudar os ciclistas a terem uma ferramenta completa e segura para anunciar seus porudtos, novos e usados. Somos um classificado grátis, especializado em bicicletas.</p>
            </div>
            
            <div class="col s-11 m-5 pb-3">
                <b>Por que anunciar na Bazar Bikes?</b>
                <p>Com uma diversidade de bicicletas e peças, o Bazar Bikes oferece a você a chance de promover seus produtos para um público altamente segmentado interessado no universo das bicicletas.</p>
            </div>
            
            <div class="col s-11 m-5 pb-3">
                <b>Quem pode anunciar no Bazar Bikes?</b>
                <p>Nosso foco são ciclistas. Qualquer pessoa que possua um CPF válido no território Brasileiro, e que deseje comprar ou vender uma bicicleta, XXXXXX é o portal ideal.</p>
            </div>

            <div class="col s-11 m-5 pb-3">
                <b>Preciso pagar para utilizar este serviço?</b>
                <p>O Bazar Bikes é 100% grátis para quem quer comprar e para quem quer vender. Não cobramos nenhum tipo de assinatura ou taxa para que os usuários acessem nossa ferramenta.</p>
            </div>

            <div class="col s-11 m-5 pb-3">
                <b>Quanto custa anunciar na Bazar Bikes?</b>
                <p>O Bazar Bikes é 100% grátis. Não cobramos taxas nem comissóes, você cria seu cadastro e anuncia seu produto, se ele estiver dentro das nossa <a href="<?php bloginfo('url'); ?>/termos-de-uso/" title="Política de Uso">Política de Uso</a> é publicado e passar a ser exibido em nossa busca, imeditamente e gratuitamente. </p>
            </div>

            <div class="col s-11 m-5 pb-3">
                <b>O Bazar Bikes é uma loja online?</b>
                <p>Somos um classificado de bicicletas grátis, todos os anúncios são de responsabilidade de seus criadores, não detemos posse, nem intermediamos negociações. Nosso objetivo é fornecer tecnologia para facilitar a vida dos ciclistas.</p>
            </div>

        </div><!-- /faq -->


        <div class="row align-center">
            <div class="s-10 l-11 col">

                <div class="row align-center cta-footer">
                    <div class="col s-11 m-shrink text-center pb-2">
                        <figure>
                            <img 
                                src="<?php bloginfo('url'); ?>/src/imgs/bazar-bikes.svg" 
                                alt="<?php bloginfo('name'); ?>" 
                                title="<?php bloginfo('name'); ?>" 
                                width="115px" 
                                height="115px" 
                            />
                        </figure>
                    </div>
                    <div class="col s-11 m-8 l-7 pb-2">
                        <p class="h1">Quer vender sua bicicleta?</p>
                        <a 
                            href="<?php bloginfo('url');?>/anunciar/" 
                            class="button secondary" 
                            title="Anuncie grátis"
                        >
                            Anuncie grátis
                        </a>
                    </div>
                </div><!-- /cta-footer -->

            </div>
        </div>

    </div>

</section>
<?php endwhile; endif; ?>
        
 <?php get_footer(); ?>