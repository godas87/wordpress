<?php
/**
 * Helper para exibição de tipos de pneus
 * Fonte centralizada de dados e funções
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Busca dados de pneus do banco de dados (taxonomia componente)
 * Com cache para otimizar performance
 * 
 * @return array Array associativo com nome => dados do pneu
 */
if (!function_exists('bazar_get_pneus_mapping')) {
  function bazar_get_pneus_mapping()
  {

    $cache_key = 'bazar_pneus_mapping';
    $cache_group = 'bazar_terms';
    $cache_expire = 3600; // 1 hora

    // Tentar obter do cache
    $cached = wp_cache_get($cache_key, $cache_group);

    if ($cached !== false && is_array($cached)) {
      return $cached;
    }

    // Buscar termo pai "Pneu"
    $pneu_parent = get_term_by('name', 'Pneu', 'componente');

    if (!$pneu_parent || is_wp_error($pneu_parent)) {
      $empty_result = array();
      wp_cache_set($cache_key, $empty_result, $cache_group, $cache_expire);
      return $empty_result;
    }

    // Buscar todos os filhos de "Pneu"
    $pneus_children = get_terms(array(
      'taxonomy' => 'componente',
      'parent' => $pneu_parent->term_id,
      'hide_empty' => false
    ));

    if (is_wp_error($pneus_children) || empty($pneus_children)) {
      $empty_result = array();
      wp_cache_set($cache_key, $empty_result, $cache_group, $cache_expire);
      return $empty_result;
    }

    $mapping = array();

    foreach ($pneus_children as $pneu_term) {

      // Buscar descricao_tecnica do term meta ou ACF
      $descricao_tecnica = null;
      if (function_exists('get_field')) {
        $descricao_tecnica = get_field('descricao_tecnica', 'componente_' . $pneu_term->term_id);
      }
      if (empty($descricao_tecnica)) {
        $descricao_tecnica = get_term_meta($pneu_term->term_id, 'descricao_tecnica', true);
      }

      // Fallback para descricao padrão se descricao_tecnica não existir
      if (empty($descricao_tecnica)) {
        $descricao_tecnica = get_term_meta($pneu_term->term_id, 'descricao', true);
      }

      $mapping[$pneu_term->name] = array(
        'nome' => $pneu_term->name,
        'descricao' => !empty($descricao_tecnica) ? $descricao_tecnica : ''
      );
    }

    // Salvar no cache
    wp_cache_set($cache_key, $mapping, $cache_group, $cache_expire);

    return $mapping;
  }
}

/**
 * Renderiza tabela HTML de tipos de pneus
 * 
 * @param bool $show_header Se deve exibir cabeçalho (padrão: true)
 * @return string HTML da tabela
 */
if (!function_exists('bazar_render_pneus_table')) {
  function bazar_render_pneus_table($show_header = true)
  {
    $mapping = bazar_get_pneus_mapping();
    ob_start();
    ?>
    <div class="row align-middle">
      <?php if ($show_header): ?>
        <div class="s-12 col">
          <p class="description">
            <?php _e('Conheça os diferentes tipos de pneus disponíveis:', 'bazar'); ?>
          </p>
        </div>
      <?php endif; ?>
      <?php foreach ($mapping as $pneu):
        $nome = esc_html($pneu['nome']);
        $descricao = esc_html($pneu['descricao']);
        ?>
        <div class="s-12 m-6 l-4 col pb-2">
          <div class="item-box">
            <div class="item-head">
              <h4>
                <?php _e('Pneu ' . esc_html($nome), 'bazar'); ?>
              </h4>
            </div>
            <div class="item">
              <?php echo ($descricao && !empty($descricao)) ? $descricao : __('Sem descrição disponível', 'bazar'); ?>
            </div><!-- /item -->
          </div><!-- /item-box -->
        </div><!-- /col -->
      <?php endforeach; ?>
    </div><!-- /row -->
    <?php
    return ob_get_clean();
  }
}

/**
 * Limpa o cache de pneus quando um termo é editado, criado ou deletado
 */
if (!function_exists('bazar_clear_pneus_cache')) {
  function bazar_clear_pneus_cache()
  {
    $cache_key = 'bazar_pneus_mapping';
    $cache_group = 'bazar_terms';
    wp_cache_delete($cache_key, $cache_group);
  }
}

// Limpar cache quando termos da taxonomia 'componente' forem editados
add_action('edited_componente', 'bazar_clear_pneus_cache');
add_action('created_componente', 'bazar_clear_pneus_cache');
add_action('delete_componente', 'bazar_clear_pneus_cache');

// Limpar cache quando term meta for atualizado
add_action('updated_term_meta', function ($meta_id, $term_id, $meta_key, $meta_value) {
  if (isset($meta_key) && $meta_key === 'descricao_tecnica') {
    $term = get_term($term_id);
    if ($term && $term->taxonomy === 'componente') {
      $pneu_parent = get_term_by('name', 'Pneu', 'componente');
      if ($pneu_parent && $term->parent == $pneu_parent->term_id) {
        bazar_clear_pneus_cache();
      }
    }
  }
}, 10, 4);
?>