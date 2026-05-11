<?php
/**
 * Função para buscar FAQs de um termo com cache
 * 
 * @param int $term_id ID do termo
 * @param string $taxonomy Nome da taxonomia
 * @return array Array de FAQs no formato ['pergunta' => string, 'resposta' => string]
 */
if (!function_exists('bazar_get_faqs_for_term')) {
  function bazar_get_faqs_for_term($term_id, $taxonomy)
  {
    // Cache key único por termo
    $cache_key = 'bazar_faqs_' . $taxonomy . '_' . $term_id;
    $cache_group = 'bazar_faqs';

    // Tentar buscar do cache primeiro
    $cached_faqs = wp_cache_get($cache_key, $cache_group);
    if ($cached_faqs !== false) {
      return $cached_faqs;
    }

    $faq = array();

    // Buscar FAQs específicos da taxonomia/termo
    $args = array(
      'post_type' => 'faq',
      'posts_per_page' => -1,
      'post_status' => 'publish',
      'tax_query' => array(
        array(
          'taxonomy' => $taxonomy,
          'field' => 'term_id',
          'terms' => $term_id,
        ),
      ),
      'meta_query' => array(
        array(
          'key' => 'faq_ativo',
          'value' => true,
          'compare' => '='
        )
      ),
      'meta_key' => 'faq_ordem',
      'orderby' => 'meta_value_num',
      'order' => 'ASC'
    );

    $faq_query = new WP_Query($args);
    if ($faq_query->have_posts()) {
      foreach ($faq_query->posts as $faq_post) {
        $pergunta = get_post_meta($faq_post->ID, 'faq_pergunta', true);
        $resposta = get_post_meta($faq_post->ID, 'faq_resposta', true);

        if (!empty($pergunta) && !empty($resposta)) {
          $faq[] = array(
            'pergunta' => $pergunta,
            'resposta' => $resposta
          );
        }
      }
    }
    wp_reset_postdata();

    // Salvar no cache por 1 hora (3600 segundos)
    wp_cache_set($cache_key, $faq, $cache_group, 3600);

    return $faq;
  }
}