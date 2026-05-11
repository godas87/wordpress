<?php
/**
 * Helper para recomendações de tamanho de quadro
 * Fonte centralizada de dados e funções
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Array de mapeamento: tamanho do quadro → recomendação
 * 
 * @return array Array associativo com tamanho => dados de recomendação
 */
if (!function_exists('bazar_get_quadro_size_mapping')) {
  function bazar_get_quadro_size_mapping()
  {
    return array(
      '13' => array(
        'tamanho_descritivo' => 'PP (XS)',
        'equivalencia' => '46 - 48 cm',
        'altura_pessoa' => '1,50m - 1,60m'
      ),
      '15' => array(
        'tamanho_descritivo' => 'P (S)',
        'equivalencia' => '15" - 16" / 50 - 52 cm',
        'altura_pessoa' => '1,60m - 1,70m'
      ),
      '17' => array(
        'tamanho_descritivo' => 'M (M)',
        'equivalencia' => '17" - 18" / 54 - 56 cm',
        'altura_pessoa' => '1,70m - 1,80m'
      ),
      '19' => array(
        'tamanho_descritivo' => 'G (L)',
        'equivalencia' => '19" - 20" / 58 - 60 cm',
        'altura_pessoa' => '1,80m - 1,90m'
      ),
      '21' => array(
        'tamanho_descritivo' => 'GG (XL)',
        'equivalencia' => '21" - 22" / 62 - 64 cm',
        'altura_pessoa' => '1,90m - 2,00m'
      ),
      '23' => array(
        'tamanho_descritivo' => 'GG (XL)',
        'equivalencia' => '21" - 22" / 62 - 64 cm',
        'altura_pessoa' => '1,90m - 2,00m'
      ),
      '23+' => array(
        'tamanho_descritivo' => 'GG (XL)',
        'equivalencia' => '21" - 22" / 62 - 64 cm',
        'altura_pessoa' => '1,90m - 2,00m'
      ),
    );
  }
}

/**
 * Busca recomendação por tamanho do quadro
 * 
 * @param string $tamanho Tamanho do quadro (ex: "13", "15", "17", etc.)
 * @return array|null Array com dados de recomendação ou null se não encontrado
 */
if (!function_exists('bazar_get_quadro_recommendation')) {
  function bazar_get_quadro_recommendation()
  {

    // Se ainda não tiver product_data, tentar obter do contexto atual
    $product_data = bazar_get_product_data(get_the_ID());

    $quadro_parent_id = bazar_get_component_title_ids()['quadro'];
    $tamanho = null;
    // Buscar o componente quadro usando array_filter (mais eficiente e funcional)
    $quadro_components = array_filter(
      $product_data['taxonomies']['componente'],
      function ($componente) use ($quadro_parent_id) {
        return (
          isset($componente->parent)
          && intval($componente->parent) === intval($quadro_parent_id)
        );
      }
    );
    // Pegar o primeiro elemento encontrado
    if (!empty($quadro_components)) {
      $quadro_component = reset($quadro_components);
      $tamanho = trim($quadro_component->slug);
    }


    if (empty($tamanho)) {
      return null;
    }

    $mapping = bazar_get_quadro_size_mapping();

    // Tentar busca direta
    if (isset($mapping[$tamanho])) {
      return $mapping[$tamanho];
    }

    // Tentar normalizar (remover espaços, converter para string)
    $tamanho = strtolower($tamanho);
    foreach ($mapping as $key => $value) {
      if (strtolower($key) === $tamanho) {
        return $value;
      }
    }

    return null;
  }
}

/**
 * Renderiza tabela HTML de recomendações de tamanho
 * 
 * @param bool $show_header Se deve exibir cabeçalho (padrão: true)
 * @return string HTML da tabela
 */
if (!function_exists('bazar_render_quadro_table')) {
  function bazar_render_quadro_table($show_header = true)
  {
    $mapping = bazar_get_quadro_size_mapping();
    ob_start();
    ?>
    <div class="row align-middle">
      <?php if ($show_header): ?>
        <div class="s-12 col">
          <p class="description">
            <?php _e('Tabela de recomendação de tamanhos de quadro baseada na altura da pessoa:', 'bazar'); ?>
          </p>
        </div>
      <?php endif; ?>
      <?php
      // Agrupar por tamanho descritivo para evitar duplicatas
      $grouped = array();
      foreach ($mapping as $tamanho => $data) {
        $key = $data['tamanho_descritivo'] . '|' . $data['equivalencia'] . '|' . $data['altura_pessoa'];
        if (!isset($grouped[$key])) {
          $grouped[$key] = array(
            'tamanhos' => array(),
            'data' => $data
          );
        }
        $grouped[$key]['tamanhos'][] = $tamanho;
      }

      foreach ($grouped as $group):
        $tamanhos_str = implode(', ', $group['tamanhos']);
        $data = $group['data'];
        ?>
        <div class="s-12 m-6 l-4 col pb-2">
          <div class="item-box">
            <div class="item-head">
              <h4>
                <?php _e('Quadro ' . esc_html($tamanhos_str), 'bazar'); ?>
              </h4>
            </div>
            <div class="item">
              <span>
                <b><?php _e('Tamanho Descritivo', 'bazar'); ?>:</b> <?php echo esc_html($data['tamanho_descritivo']); ?>
              </span>
              <span>
                <b><?php _e('Equivalência', 'bazar'); ?>:</b> <?php echo esc_html($data['equivalencia']); ?>
              </span>
              <span>
                <b><?php _e('Altura da Pessoa', 'bazar'); ?>:</b> <?php echo esc_html($data['altura_pessoa']); ?>
              </span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
  }
}
?>