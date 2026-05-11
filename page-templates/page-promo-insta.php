<?php
/**
 * Template Name: Promo Instagram
 *
 * Landing para tráfego pago do anúncio Instagram: venda de bike, anúncio grátis,
 * zero comissão, público MTB/Speed. Otimizada para conversão: CTA acima da dobra,
 * copy enxuto, prova social e fluxo claro da promo cupom.
 *
 * @package XXXXXX
 */
get_header();
global $seo;
global $schema;

if (have_posts()):
  while (have_posts()):
    the_post();
    ?>

    <div class="app-content mb-4">

      <section class="section-bg">
        <div class="row">

          <div class="ellipse blue"></div>
          <div class="ellipse blue-2"></div>
          <div class="ellipse pink"></div>

          <div class="s-12 col text-center pt-3 pb-6">
            <div class="btn-group">
              <a href="#promo" rel="noreferrer" title="Promoção Instagram">
                Promoção Instagram
              </a>
            </div>
          </div><!-- /col -->

          <div class="s-10 s-off-1 m-8 col">

            <h1 class="color-1 size-1 bold">DE CICLISTA<BR>PARA CICLISTA</h1>

            <p class="txt_box">No XXXXXX, sua máquina aparece para quem realmente entende o valor dela.</p>

            <ul>
              <li>
                <i class="fa fa-check-circle"></i> Anúncio 100% Grátis
              </li>
              <li>
                <i class="fa fa-check-circle"></i> ZERO taxa de comissão sobre a venda
              </li>
              <li>
                <i class="fa fa-check-circle"></i> Público especializado em MTB, Speed e Performance
              </li>
            </ul>

            <div class="pt-2">
              <a href="#promo" class="btn btn-link">Anuncie Grátis</a>
            </div>

          </div>
        </div>
      </section>

      <section id="promo" class="row align-center pt-6 pb-6">
        <div class="s-11 m-8 col text-center pb-3 pb-6">

          <div class="stars text-center pb-1">
            <i class="fab fa-instagram color-1" style="font-size: 6rem;"></i>
          </div>

          <h2 class="text-center pb-2 size-1">
            PROMOÇÃO <span class="color-1">INSTAGRAM</span>
          </h2>


          <h2 class="text-center pb-2 size-2">
            <span class="color-2">50% OFF</span> PARA IMPULSIONAR SEU ANÚNCIO
          </h2>

          <p class="txt_box">
            Cansado de taxas de comissão que levam uma fatia do seu lucro? Aqui o modelo é direto: de ciclista para
            ciclista.
          </p>
        </div>
        <div class="s-11 m-10 col text-center pb-6">

          <div class="row align-center features pb-3">
            <div class="s-11 m-4 col pb-2">
              <div class="bx">
                <i class="fa fa-thumbs-up"></i>
                <br>
                <span>Curta a página <br><a href="https://XXXXXX/" target="_blank" rel="noreferrer"
                    title="Curta a página @XXXXXX">@XXXXXX</a></span>
              </div>
            </div>
            <div class="s-11 m-4 col pb-2">
              <div class="bx">
                <i class="fa fa-plus-circle"></i>
                <br>
                <span>Crie seu anúncio <br>
                  <a href="<?php bloginfo('url'); ?>/anunciar/" target="_blank" rel="noreferrer"
                    title="Crie seu anúncio grátis">grátis</a></span>
              </div>
            </div>

            <div class="s-11 m-4 col pb-2">
              <div class="bx">
                <i class="fa fa-paper-plane"></i>
                <br>
                <span>Mande <br><a href="https://XXXXXX/" target="_blank" rel="noreferrer"
                    title="Curta a página @XXXXXX">QUERO CUPOM</a> no Direct</span>
              </div>
            </div>
          </div>

          <h2 class="text-center pb-2 size-2">
            Você receberá um cupom de desconto para Impulsionar seu anúncio, com 50% de desconto.
          </h2>

          <small>
            <a href="<?php bloginfo('url'); ?>/termos-promocao-instagram/" target="_blank" rel="noreferrer"
              title="Texto legal" class="white regular">Para termos condições e
              regras, acesse
              aqui.</a>
          </small>

        </div><!-- /col -->
        <div class="s-11 m-10 col">
          <!-- CTA Final com Imagem -->
          <div class="cta-final-section">
            <div class="cta-footer">
              <div class="row align-center align-middle">
                <div class="col s-11 m-shrink text-center">
                  <figure>
                    <img src="<?php bloginfo('url'); ?>/src/imgs/bazar-bikes.svg" alt="<?php bloginfo('name'); ?>"
                      title="<?php bloginfo('name'); ?>" width="115px" height="115px" />
                  </figure>
                </div>
                <div class="col s-11 m-8 l-7">
                  <p class="h1">Pronto para vender sua bicicleta?</p>
                  <p class="cta-subtitle">Junte-se a milhares de ciclistas que já vendem no Bazar Bikes</p>
                  <a href="<?php bloginfo('url'); ?>/anunciar/" class="button" title="Anuncie grátis">Anuncie grátis
                    agora</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

    </div>

    <?php
  endwhile;
endif;
?>
<script type="text/javascript">var __BAZAR_Page = 'landing';</script>
<?php get_footer(); ?>