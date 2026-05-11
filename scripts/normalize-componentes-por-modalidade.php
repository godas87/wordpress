<?php
/**
 * Normalização: atribuir componentes padrão por modalidade a anúncios sem componente.
 *
 * Cenário: filtros como componente_filter=aro-700c só devolvem posts que têm esse termo;
 * anúncios antigos ou incompletos ficam de fora. Este script preenche valores típicos de mercado
 * quando o anúncio já tem modalidade mas zero termos em `componente`.
 *
 * Uso: Ferramentas > Normalizar componentes (modalidade)
 * Ou: require_once get_template_directory() . '/scripts/normalize-componentes-por-modalidade.php';
 *
 * Meta: `_bazar_componentes_inferidos` = 1 (e opcionalmente JSON em `_bazar_componentes_inferidos_detalhe`)
 * para auditoria / reversão futura.
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Slugs de pais da taxonomia componente (nível 0) — nomes gerados pelo WP a partir dos rótulos.
 */
function bazar_normalize_componente_parent_slugs()
{
  return array(
    'aro'                 => 'aro',
    'quadro'              => 'quadro',
    'cambio_dianteiro'    => 'cambio-dianteiro',
    'cambio_traseiro'     => 'cambio-traseiro',
    'freio'               => 'freio',
  );
}

/**
 * Padrões sugeridos por slug de modalidade (termo publicado no site).
 * Chaves internas: aro, quadro, cambio_dianteiro, cambio_traseiro, freio → slugs FILHOS sob cada pai.
 *
 * Notas:
 * - MTB na Bazar usa o slug `mountain-bike-mtb` (não `mtb`); aliases abaixo cobrem sinónimos.
 * - Quadro "54" em Speed/Triathlon: só aplica se existir filho com esse slug em Quadro na BD.
 * - BMX: câmbio traseiro "Fixa" (slug típico `fixa`) para monocorrente; confirme no WP.
 * - Freio: slugs típicos de filhos (ex. `disco-hidraulico`, `v-brake`, `ferradura`); ajuste se na BD forem outros.
 *
 * @return array<string, array<string, string>>
 */
function bazar_normalize_default_component_children_by_modalidade()
{
  $map = array(
    // Mountain Bike — mercado massificado aro 29, grupos 3x7 ainda comuns em entrada
    'mountain-bike-mtb' => array(
      'aro'                 => '29',
      'quadro'              => '17',
      'cambio_dianteiro'    => '3v',
      'cambio_traseiro'     => '7v',
      'freio'               => 'disco-hidraulico',
    ),
    // Speed / estrada — aro 700c, quadro cm típico (ajuste o slug se na BD for outro, ex. 54mm)
    'speed' => array(
      'aro'                 => '700c',
      'quadro'              => '54',
      'cambio_dianteiro'    => '3v',
      'cambio_traseiro'     => '7v',
      'freio'               => 'ferradura',
    ),
    // Gravel — aproximação conservadora (700c, quadro médio, 2x11 comum)
    'gravel' => array(
      'aro'                 => '700c',
      'quadro'              => '19',
      'cambio_dianteiro'    => '2v',
      'cambio_traseiro'     => '11v',
      'freio'               => 'disco-mecanico',
    ),
    // Urbana — aro 29 / quadro 17 (definição pedida)
    'urbana' => array(
      'aro'                 => '29',
      'quadro'              => '17',
      'cambio_dianteiro'    => '3v',
      'cambio_traseiro'     => '7v',
      'freio'               => 'v-brake',
    ),
    // Elétrica — aro 29 / quadro 17 (definição pedida)
    'eletrica' => array(
      'aro'                 => '29',
      'quadro'              => '17',
      'cambio_dianteiro'    => '3v',
      'cambio_traseiro'     => '7v',
      'freio'               => 'disco-hidraulico',
    ),
    // Infantil — aro 24 / quadro 15; câmbios 2x6 (definição pedida)
    'infantil' => array(
      'aro'                 => '24',
      'quadro'              => '15',
      'cambio_dianteiro'    => '2v',
      'cambio_traseiro'     => '6v',
      'freio'               => 'v-brake',
    ),
    // BMX — 20", quadro pequeno, monocorrente (1v + traseiro Fixa se existir na taxonomia)
    'bmx' => array(
      'aro'                 => '20',
      'quadro'              => '13',
      'cambio_dianteiro'    => '1v',
      'cambio_traseiro'     => 'fixa',
      'freio'               => 'v-brake',
    ),
    // Dobrável — 20" e grupos 3x7 comuns em entrada
    'dobravel' => array(
      'aro'                 => '20',
      'quadro'              => '17',
      'cambio_dianteiro'    => '3v',
      'cambio_traseiro'     => '7v',
      'freio'               => 'v-brake',
    ),
    // Triathlon / TT — próximo da Speed (700c, quadro M/L, 2x11)
    'triathlon' => array(
      'aro'                 => '700c',
      'quadro'              => '54',
      'cambio_dianteiro'    => '2v',
      'cambio_traseiro'     => '11v',
      'freio'               => 'disco-hidraulico',
    ),
  );

  return apply_filters('bazar_normalize_component_defaults_by_modalidade', $map);
}

/**
 * Modalidade slug do post → slug canónico na chave de $map (aliases).
 *
 * @return array<string, string>
 */
function bazar_normalize_modalidade_slug_aliases()
{
  return apply_filters(
    'bazar_normalize_modalidade_slug_aliases',
    array(
      'mtb'               => 'mountain-bike-mtb',
      'mountain-bike'     => 'mountain-bike-mtb',
      'mountain_bike_mtb' => 'mountain-bike-mtb',
      // Plurais / grafias alternativas → slug canónico do termo na BD
      'urbanas'           => 'urbana',
      'eletricas'         => 'eletrica',
      'dobraveis'         => 'dobravel',
    )
  );
}

/**
 * Resolve term_id do filho de `componente` pelo slug do pai (nível 0) e slug do filho.
 *
 * @param string $parent_slug Ex.: aro, quadro, cambio-dianteiro
 * @param string $child_slug  Ex.: 29, 700c, 3v
 * @return int 0 se não encontrar
 */
function bazar_normalize_resolve_componente_child_term_id($parent_slug, $child_slug)
{
  $parent_slug = sanitize_title($parent_slug);
  $child_slug  = sanitize_title($child_slug);

  if ($parent_slug === '' || $child_slug === '') {
    return 0;
  }

  $parent = get_term_by('slug', $parent_slug, 'componente');
  if (!$parent || is_wp_error($parent)) {
    return 0;
  }
  if ((int) $parent->parent !== 0) {
    return 0;
  }

  $children = get_terms(array(
    'taxonomy'   => 'componente',
    'parent'     => (int) $parent->term_id,
    'hide_empty' => false,
    'number'     => 0,
  ));

  if (is_wp_error($children) || empty($children)) {
    return 0;
  }

  foreach ($children as $term) {
    if ($term->slug === $child_slug) {
      return (int) $term->term_id;
    }
  }

  return 0;
}

/**
 * @param string $modal_slug
 * @return string
 */
function bazar_normalize_canonical_modalidade_slug($modal_slug)
{
  $modal_slug = sanitize_title($modal_slug);
  $aliases = bazar_normalize_modalidade_slug_aliases();
  if (isset($aliases[$modal_slug])) {
    return $aliases[$modal_slug];
  }
  return $modal_slug;
}

/**
 * @param int $post_id
 * @return bool
 */
function bazar_normalize_post_is_eligible($post_id)
{
  $post_id = (int) $post_id;
  if ($post_id <= 0) {
    return false;
  }
  if (get_post_type($post_id) !== 'post') {
    return false;
  }
  if (get_post_status($post_id) !== 'publish') {
    return false;
  }
  if (function_exists('bazar_get_vendido_term_id')) {
    $vid = (int) bazar_get_vendido_term_id();
    if ($vid > 0 && has_term($vid, 'status', $post_id)) {
      return false;
    }
  } elseif (has_term('vendido', 'status', $post_id)) {
    return false;
  }

  return (bool) has_term('bicicleta', 'category', $post_id);
}

/**
 * Executa normalização.
 *
 * @param bool  $dry_run
 * @param array $args { int limit: máx. posts a alterar por execução (0 = sem limite) }
 * @return array{ success: bool, message: string, stats: array }
 */
function bazar_normalize_componentes_por_modalidade($dry_run = true, $args = array())
{
  $limit = isset($args['limit']) ? max(0, (int) $args['limit']) : 0;

  $stats = array(
    'scanned'                    => 0,
    'skipped_has_componente'     => 0,
    'skipped_no_modalidade'      => 0,
    'skipped_no_mapping'         => 0,
    'skipped_not_eligible'       => 0,
    'updated'                    => 0,
    'errors'                     => 0,
    'missing_terms'              => array(),
    'sample_titles'              => array(),
    'sample_ids'                 => array(),
  );

  $defaults_map = bazar_normalize_default_component_children_by_modalidade();
  $parents      = bazar_normalize_componente_parent_slugs();

  $tax_q = array(
    'relation' => 'AND',
    array(
      'taxonomy' => 'category',
      'field'    => 'slug',
      'terms'    => array('bicicleta'),
    ),
    array(
      'taxonomy' => 'componente',
      'operator' => 'NOT EXISTS',
    ),
    array(
      'taxonomy' => 'modalidade',
      'operator' => 'EXISTS',
    ),
  );

  $vendido_id = function_exists('bazar_get_vendido_term_id') ? (int) bazar_get_vendido_term_id() : 0;
  if ($vendido_id > 0) {
    $tax_q[] = array(
      'taxonomy' => 'status',
      'field'    => 'term_id',
      'terms'    => array($vendido_id),
      'operator' => 'NOT IN',
    );
  }

  $query_args = array(
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'posts_per_page'         => $limit > 0 ? $limit : -1,
    'fields'                 => 'ids',
    'orderby'                => 'ID',
    'order'                  => 'ASC',
    'tax_query'              => $tax_q,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => true,
  );

  $query_args = apply_filters('bazar_normalize_componentes_query_args', $query_args);

  $q = new WP_Query($query_args);

  if (!$q->have_posts()) {
    return array(
      'success' => true,
      'message' => 'Nenhum anúncio em falta: categoria Bicicleta, com modalidade, sem componente, não vendido (ou já tudo normalizado).',
      'stats'   => $stats,
    );
  }

  foreach ($q->posts as $post_id) {
    $post_id = (int) $post_id;
    $stats['scanned']++;

    if (!bazar_normalize_post_is_eligible($post_id)) {
      $stats['skipped_not_eligible']++;
      continue;
    }

    $comp_terms = wp_get_post_terms($post_id, 'componente', array('fields' => 'ids'));
    if (!is_wp_error($comp_terms) && !empty($comp_terms)) {
      $stats['skipped_has_componente']++;
      continue;
    }

    $modal_terms = wp_get_post_terms($post_id, 'modalidade', array(
      'fields'  => 'all',
      'orderby' => 'term_id',
      'order'   => 'ASC',
    ));
    if (is_wp_error($modal_terms) || empty($modal_terms)) {
      $stats['skipped_no_modalidade']++;
      continue;
    }

    $first = $modal_terms[0];
    $modal_slug = bazar_normalize_canonical_modalidade_slug($first->slug);

    if (!isset($defaults_map[$modal_slug])) {
      $stats['skipped_no_mapping']++;
      continue;
    }

    $plan = $defaults_map[$modal_slug];
    $term_ids_to_set = array();
    $detail = array('modalidade' => $modal_slug, 'componentes' => array());

    foreach ($plan as $key => $child_slug) {
      if (!isset($parents[$key])) {
        continue;
      }
      $parent_slug = $parents[$key];
      $tid = bazar_normalize_resolve_componente_child_term_id($parent_slug, $child_slug);
      if ($tid > 0) {
        $term_ids_to_set[] = $tid;
        $detail['componentes'][$key] = array('parent' => $parent_slug, 'child' => $child_slug, 'term_id' => $tid);
      } else {
        $stats['missing_terms'][] = sprintf('Post %d: %s / %s (pai=%s filho=%s)', $post_id, get_the_title($post_id), $modal_slug, $parent_slug, $child_slug);
      }
    }

    if (empty($term_ids_to_set)) {
      $stats['errors']++;
      continue;
    }

    $term_ids_to_set = array_values(array_unique(array_map('intval', $term_ids_to_set)));

    if (count($stats['sample_titles']) < 8) {
      $stats['sample_titles'][] = get_the_title($post_id);
      $stats['sample_ids'][] = $post_id;
    }

    if ($dry_run) {
      $stats['updated']++;
      continue;
    }

    $set = wp_set_object_terms($post_id, $term_ids_to_set, 'componente', false);
    if (is_wp_error($set)) {
      $stats['errors']++;
      continue;
    }

    update_post_meta($post_id, '_bazar_componentes_inferidos', '1');
    update_post_meta($post_id, '_bazar_componentes_inferidos_detalhe', wp_json_encode($detail));

    $stats['updated']++;
  }

  $msg = sprintf(
    'Analisados: %s. Atualizados: %s (dry-run: %s). Já com componente: %s. Sem modalidade: %s. Modalidade sem mapa: %s. Erros: %s.',
    number_format_i18n($stats['scanned']),
    number_format_i18n($stats['updated']),
    $dry_run ? 'sim' : 'não',
    number_format_i18n($stats['skipped_has_componente']),
    number_format_i18n($stats['skipped_no_modalidade']),
    number_format_i18n($stats['skipped_no_mapping']),
    number_format_i18n($stats['errors'])
  );

  if (!empty($stats['missing_terms'])) {
    $msg .= ' Avisos de termos em falta na taxonomia: ' . number_format_i18n(count($stats['missing_terms'])) . '.';
  }

  return array(
    'success' => true,
    'message' => $msg,
    'stats'   => $stats,
  );
}

/**
 * Admin: submenu em Ferramentas
 */
function bazar_normalize_componentes_admin_menu()
{
  add_management_page(
    'Normalizar componentes (modalidade)',
    'Componentes por modalidade',
    'manage_options',
    'bazar-normalize-componentes-modalidade',
    'bazar_normalize_componentes_admin_page'
  );
}
add_action('admin_menu', 'bazar_normalize_componentes_admin_menu');

function bazar_normalize_componentes_admin_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  $map = bazar_normalize_default_component_children_by_modalidade();
  ?>
  <div class="wrap">
    <h1>Normalizar componentes por modalidade</h1>

    <div class="card" style="max-width: 720px; margin: 20px 0;">
      <h2>Análise da proposta</h2>
      <p><strong>Problema:</strong> muitos anúncios têm modalidade mas nenhum termo em <code>componente</code>, por isso filtros como <code>componente_filter=aro-700c</code> mostram poucos resultados — não é bug da query.</p>
      <p><strong>Abordagem:</strong> para cada anúncio <strong>publicado</strong>, categoria <strong>Bicicleta</strong>, <strong>sem</strong> componente e <strong>não</strong> vendido, aplicar um conjunto de slugs típicos por modalidade (mercado médio).</p>
      <p><strong>Riscos:</strong> os filtros passam a parecer “precisos” mesmo quando o vendedor não informou dados — convém comunicar ou revisitar anúncios marcados com meta <code>_bazar_componentes_inferidos</code>.</p>
      <p><strong>Slugs de modalidade no WP:</strong> MTB = <code>mountain-bike-mtb</code> (aliases <code>mtb</code>, <code>urbanas</code>→<code>urbana</code>, <code>eletricas</code>→<code>eletrica</code>, …). Mapa inclui também <code>urbana</code>, <code>eletrica</code>, <code>infantil</code>, <code>bmx</code>, <code>dobravel</code>, <code>triathlon</code> — valores são médias de mercado; ajuste com o filtro <code>bazar_normalize_component_defaults_by_modalidade</code>.</p>
      <p><strong>Quadro 54 / termos em falta:</strong> só entram se o filho existir na taxonomia Componente (ex. BMX traseiro <code>fixa</code>).</p>
    </div>

    <div class="card" style="max-width: 720px; margin: 20px 0;">
      <h2>Mapa actual (filtro <code>bazar_normalize_component_defaults_by_modalidade</code>)</h2>
      <table class="widefat striped">
        <thead>
          <tr>
            <th>Modalidade (slug)</th>
            <th>Aro / Quadro / Câmbios / Freio</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($map as $slug => $row) : ?>
          <tr>
            <td><code><?php echo esc_html($slug); ?></code></td>
            <td><pre style="margin:0;font-size:12px;"><?php echo esc_html(print_r($row, true)); ?></pre></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php
    if (isset($_POST['bazar_normalize_componentes']) && check_admin_referer('bazar_normalize_componentes')) {
      $dry = !isset($_POST['execute']) || (string) $_POST['execute'] !== '1';
      $lim = isset($_POST['limit']) ? max(0, absint($_POST['limit'])) : 0;
      $result = bazar_normalize_componentes_por_modalidade($dry, array('limit' => $lim));

      $class = !empty($result['success']) ? 'notice-success' : 'notice-error';
      echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';

      if (!empty($result['stats']['missing_terms'])) {
        echo '<div class="notice notice-warning"><p><strong>Termos em falta (pai/filho não encontrados na taxonomia):</strong></p><ul style="max-height:240px;overflow:auto;">';
        foreach (array_slice($result['stats']['missing_terms'], 0, 40) as $line) {
          echo '<li>' . esc_html($line) . '</li>';
        }
        if (count($result['stats']['missing_terms']) > 40) {
          echo '<li>…</li>';
        }
        echo '</ul></div>';
      }

      if ($dry && !empty($result['stats']['sample_titles'])) {
        echo '<div class="notice notice-info"><p><strong>Exemplos que seriam atualizados (primeiros IDs):</strong></p><ul>';
        foreach ($result['stats']['sample_titles'] as $i => $title) {
          $id = isset($result['stats']['sample_ids'][$i]) ? (int) $result['stats']['sample_ids'][$i] : 0;
          echo '<li>#' . esc_html((string) $id) . ' — ' . esc_html($title) . '</li>';
        }
        echo '</ul></div>';
      }
    }
    ?>

    <div class="card" style="max-width: 720px; margin: 20px 0;">
      <h2>Executar</h2>
      <form method="post">
        <?php wp_nonce_field('bazar_normalize_componentes'); ?>
        <input type="hidden" name="bazar_normalize_componentes" value="1" />
        <p>
          <label>Máximo de anúncios a processar por execução (só entram anúncios <strong>Bicicleta</strong> com <strong>modalidade</strong>, <strong>sem</strong> componente e não vendidos). Use <code>0</code> para todos.<br />
            <input type="number" name="limit" value="200" min="0" step="1" style="width:120px;" />
          </label>
        </p>
        <p>
          <label><input type="radio" name="execute" value="0" checked />
            <strong>Simular</strong> — contar e listar exemplos, sem gravar termos</label>
        </p>
        <p>
          <label><input type="radio" name="execute" value="1" />
            <strong>Executar</strong> — aplicar termos e meta de auditoria</label>
        </p>
        <p><button type="submit" class="button button-primary">Enviar</button></p>
      </form>
    </div>
  </div>
  <?php
}
