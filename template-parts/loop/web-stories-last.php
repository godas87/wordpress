<amp-story-page id="page-last" class="clean" auto-advance-after="10s">    
    <amp-story-grid-layer template="fill">    
      
      <div class="home text-center">

        <?php get_template_part('template-parts/loop/web-stories-logo'); ?>

        <amp-img 
          src="<?php bloginfo('url'); ?>/src/imgs/web-stories/template-2.png"
          width="554" 
          height="338"
          layout="responsive"
          class="mb-2"> 
        </amp-img>
        
        <h1 class="pb-1 text-center" animate-in="fly-in-right">
          <span class="bg-black">Quer vender sua bicicleta?</span><br/>
          <span class="head2">Anuncie grátis, sem pagar comissões.</span>
        </h1>

        <h3 class="pb-1 text-black text-center" animate-in="fly-in-left">
          Anucie em nosso site quantas vezes quiser, é seguro e é grátis, não cobramos comissões! </br></br>
          <b>www.XXXXXX</b>
        </h3>

        <a 
          href="<?php bloginfo('url'); ?>/anunciar-bicicletas-e-pecas-gratis/" 
          class="amp-button" 
          title="<?php _e('Anuncie grátis', 'bazar'); ?>"
          animate-in="fly-in-bottom"
          target="_blank"
        >
          <?php _e('Anuncie grátis', 'bazar'); ?>
        </a>
      </div>
      
    </amp-story-grid-layer>
  </amp-story-page>