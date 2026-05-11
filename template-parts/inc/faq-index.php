<?php
global $schema;
global $current_term;

// Buscar FAQs do CPT 'faq' associados ao termo atual (focados em dúvidas de COMPRADORES)
$faq = array();

if ($current_term && !is_wp_error($current_term)) {
  // Primeiro, buscar FAQs do termo atual
  $faq = bazar_get_faqs_for_term($current_term->term_id, $current_term->taxonomy);

  // Se não encontrou FAQs e o termo tem um pai, buscar FAQs do termo pai
  if (empty($faq) && $current_term->parent > 0) {
    $parent_term = get_term($current_term->parent, $current_term->taxonomy);
    if ($parent_term && !is_wp_error($parent_term)) {
      $faq = bazar_get_faqs_for_term($parent_term->term_id, $current_term->taxonomy);
    }
  }
}
?>
<div class="row align-center">
  <div class="col s-12 m-10 l-12 text-center">

    <h2 class="faq-section-title bold text-center"><?php _e('Perguntas relacionadas', 'bazar'); ?></h2>

    <p class="faq-section-subtitle text-center"><?php _e('Separamos algumas dúvidas comuns', 'bazar'); ?></p>

    <?php
    if (
      !empty($faq)
      && is_array($faq)
      && count($faq) > 0
    ):
      ?>
      <ul id="faq-toogle" class="faq grid-2">
        <?php
        $items = [];
        foreach ($faq as $item):
          $items[] = $item;
          ?>
          <li class="faq-item">
            <h3 class="bold toogleLink">
              <?php echo esc_html($item['pergunta']); ?>
              <i class="fas fa-chevron-down"></i>
            </h3>
            <div class="question box">
              <p><?php echo wp_kses_post($item['resposta']); ?></p>
            </div>
          </li><!-- /faq-item -->
          <?php
        endforeach;

        // Gerar schema FAQPage se o objeto schema estiver disponível
        if (
          isset($schema)
          && is_object($schema)
          && method_exists($schema, 'schema_FAQPage')
        ) {
          $schema->schema_FAQPage($items);
        }
        ?>
      </ul>
      <?php
    else:
      // Fallback: FAQ genérico para vendedores
      get_template_part('template-parts/inc/faq-comprar', null, array('grid' => '2'));
    endif;
    ?>
  </div>
</div>