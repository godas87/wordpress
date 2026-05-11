<?php
/**
 * Helper para exibição de tipos de trocadores
 * Fonte centralizada de dados e funções
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Busca dados de trocadores do banco de dados (taxonomia componente)
 * Com cache para otimizar performance
 * 
 * @return array Array associativo com nome => dados do trocador
 */
if (!function_exists('bazar_get_trocadores_mapping')) {
  function bazar_get_trocadores_mapping()
  {

    $cache_key = 'bazar_trocadores_mapping';
    $cache_group = 'bazar_terms';
    $cache_expire = 3600; // 1 hora

    // Tentar obter do cache
    $cached = wp_cache_get($cache_key, $cache_group);

    if ($cached !== false && is_array($cached)) {
      return $cached;
    }

    // Buscar termo pai "Trocador"
    $trocador_parent = get_term_by('name', 'Trocador', 'componente');

    if (!$trocador_parent || is_wp_error($trocador_parent)) {
      $empty_result = array();
      wp_cache_set($cache_key, $empty_result, $cache_group, $cache_expire);
      return $empty_result;
    }

    // Buscar todos os filhos de "Trocador"
    $trocadores_children = get_terms(array(
      'taxonomy' => 'componente',
      'parent' => $trocador_parent->term_id,
      'hide_empty' => false
    ));

    if (is_wp_error($trocadores_children) || empty($trocadores_children)) {
      $empty_result = array();
      wp_cache_set($cache_key, $empty_result, $cache_group, $cache_expire);
      return $empty_result;
    }

    $mapping = array();

    foreach ($trocadores_children as $trocador_term) {
      // Buscar imagem do term meta ou ACF
      $imagem = null;
      if (function_exists('get_field')) {
        $imagem = get_field('imagem', 'componente_' . $trocador_term->term_id);
      }
      if (empty($imagem)) {
        $imagem = get_term_meta($trocador_term->term_id, 'imagem', true);
      }

      // Tratar diferentes formatos de retorno do ACF
      if (!empty($imagem)) {
        // Se for array (objeto de attachment do ACF), extrair ID ou URL
        if (is_array($imagem)) {
          if (isset($imagem['ID'])) {
            $imagem = wp_get_attachment_url($imagem['ID']);
          } elseif (isset($imagem['url'])) {
            $imagem = $imagem['url'];
          } else {
            $imagem = null;
          }
        }
        // Se for um attachment ID (número), converter para URL
        elseif (is_numeric($imagem)) {
          $imagem = wp_get_attachment_url($imagem);
        }
        // Se já for uma URL, manter como está
      }

      // Buscar descricao_tecnica do term meta ou ACF
      $descricao_tecnica = null;
      if (function_exists('get_field')) {
        $descricao_tecnica = get_field('descricao_tecnica', 'componente_' . $trocador_term->term_id);
      }
      if (empty($descricao_tecnica)) {
        $descricao_tecnica = get_term_meta($trocador_term->term_id, 'descricao_tecnica', true);
      }

      // Fallback para descricao padrão se descricao_tecnica não existir
      if (empty($descricao_tecnica)) {
        $descricao_tecnica = get_term_meta($trocador_term->term_id, 'descricao', true);
      }

      $mapping[$trocador_term->name] = array(
        'nome' => $trocador_term->name,
        'imagem' => !empty($imagem) ? esc_url_raw($imagem) : null,
        'descricao' => !empty($descricao_tecnica) ? $descricao_tecnica : ''
      );
    }

    // Salvar no cache
    wp_cache_set($cache_key, $mapping, $cache_group, $cache_expire);

    return $mapping;
  }
}

/**
 * Renderiza grid HTML de tipos de trocadores
 * 
 * @param bool $show_header Se deve exibir cabeçalho (padrão: true)
 * @return string HTML do grid
 */
if (!function_exists('bazar_render_trocadores_grid')) {
  function bazar_render_trocadores_grid($show_header = true)
  {
    $mapping = bazar_get_trocadores_mapping();

    ob_start();
    ?>
    <div class="trocadores-grid-container pb-2">
      <?php if ($show_header): ?>
        <div class="trocadores-intro">
          <p><?php _e('Conheça os diferentes tipos de trocadores disponíveis:', 'bazar'); ?></p>
        </div>
      <?php endif; ?>
      <div class="trocadores-grid">
        <?php foreach ($mapping as $trocador):
          $nome = esc_html($trocador['nome']);
          $imagem = $trocador['imagem'];
          $descricao = esc_html($trocador['descricao']);
          $has_image = false;
          $image_url = '';

          if (!empty($imagem)) {
            // Se for uma URL válida, usar diretamente
            if (filter_var($imagem, FILTER_VALIDATE_URL)) {
              $image_url = $imagem;
              $has_image = true;
            }
            // Se for um caminho do tema, verificar existência
            elseif (strpos($imagem, get_template_directory_uri()) === 0) {
              $image_path = str_replace(get_template_directory_uri(), get_template_directory(), $imagem);
              if (file_exists($image_path)) {
                $image_url = $imagem;
                $has_image = true;
              }
            }
            // Se for uma URL de attachment do WordPress, usar diretamente
            else {
              $upload_dir = wp_upload_dir();
              if (strpos($imagem, site_url()) === 0 || (isset($upload_dir['baseurl']) && strpos($imagem, $upload_dir['baseurl']) === 0)) {
                $image_url = $imagem;
                $has_image = true;
              }
            }
          }
          ?>
          <div class="trocador-item">
            <div class="trocador-image-wrapper">
              <?php if ($has_image && !empty($image_url)): ?>
                <div class="trocador-image">
                  <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($nome); ?>" loading="lazy" width="110"
                    height="100">
                </div>
              <?php else: ?>
                <div class="trocador-image trocador-image-placeholder">
                  <span class="placeholder-icon">⚙️</span>
                </div>
              <?php endif; ?>
            </div>
            <div class="trocador-info">
              <h4 class="trocador-nome"><?php echo $nome; ?></h4>
              <?php if ($descricao): ?>
                <p class="trocador-descricao"><?php echo $descricao; ?></p>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
  }
}

/**
 * Limpa o cache de trocadores quando um termo é editado, criado ou deletado
 */
if (!function_exists('bazar_clear_trocadores_cache')) {
  function bazar_clear_trocadores_cache()
  {
    $cache_key = 'bazar_trocadores_mapping';
    $cache_group = 'bazar_terms';
    wp_cache_delete($cache_key, $cache_group);
  }
}

// Limpar cache quando termos da taxonomia 'componente' forem editados
add_action('edited_componente', 'bazar_clear_trocadores_cache');
add_action('created_componente', 'bazar_clear_trocadores_cache');
add_action('delete_componente', 'bazar_clear_trocadores_cache');

// Limpar cache quando term meta for atualizado
add_action('updated_term_meta', function ($meta_id, $term_id, $meta_key, $meta_value) {
  if ($meta_key === 'imagem' || $meta_key === 'descricao_tecnica') {
    $term = get_term($term_id);
    if ($term && $term->taxonomy === 'componente') {
      bazar_clear_trocadores_cache();
    }
  }
}, 10, 4);

