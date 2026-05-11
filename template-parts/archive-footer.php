<div class="relative">
  <?php get_template_part('template-parts/ads/adsense/top'); ?>
</div>

<section id="description" class="archive-index pt-4 pb-4">
  <div class="row align-center">
    <div class="col s-11 l-12">

      <!-- <div class="relative mb-4">
        <?php // get_template_part('template-parts/slide/related-post'); ?>
      </div> -->

      <div class="relative pb-4">
        <?php get_template_part('template-parts/slide/destaques'); ?>
      </div>

      <div class="relative mb-4">
        <?php get_template_part('template-parts/ads/afiliados/responsive'); ?>
      </div>

      <div class="relative pb-6">
        <?php get_template_part('template-parts/slide/related-blog'); ?>
      </div>

      <div class="pb-4">
        <?php get_template_part('template-parts/cta/cta-whatsapp-newsletter'); ?>
      </div>

      <div class="pb-6">
        <?php get_template_part('template-parts/inc/faq-index'); ?>
      </div>

      <?php
      if (is_tax() || is_category()) {
        global $current_term;
        $description = get_field('descricao_seo', $current_term);
        $term_name = ($current_term->parent === 0 || $current_term->parent === null || $current_term->parent === '0')
          ? $current_term->name
          : get_term($current_term->parent)->name . ' ' . $current_term->name;
      }
      if (isset($description) && !empty($description)): ?>
        <div class="pb-3">
          <div class="text-center">
            <h2>Guia Técnico e Manual de Compra para <?php echo esc_html($term_name); ?></h2>
            <p>Para te ajudar a encontrar o melhor produto.</p>
          </div>
          <div class="guide-collapsible">
            <div id="desc" class="content blog truncated-text">
              <?php echo $description; ?>
            </div>
            <button id="btn-show-content" type="button" aria-expanded="false" aria-controls="desc" hidden>
              Exibir mais
            </button>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</section>

<?php get_template_part('template-parts/modal/orderby'); ?>