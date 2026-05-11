<?php
/**
 * Migração: unificar termos redundantes filhos de Freio (taxonomia componente).
 *
 * Regra padrão (canônicos):
 * - hidraulico  → disco-hidraulico
 * - mecanico    → disco-mecanico
 *
 * Ajuste o mapa via filtro `bazar_merge_freio_redundant_terms_map` se os slugs forem outros.
 *
 * Uso: Ferramentas > Unificar termos Freio (redundantes)
 * Ou: require_once get_template_directory() . '/scripts/normalizar/merge-freio-redundant-terms.php';
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Slug do termo pai Freio (nível 0 em componente).
 *
 * @return string
 */
function bazar_merge_freio_parent_slug()
{
  return apply_filters('bazar_merge_freio_parent_slug', 'freio');
}

/**
 * Mapa slug antigo (redundante) => slug canônico (prevalece).
 *
 * @return array<string, string>
 */
function bazar_merge_freio_redundant_terms_map()
{
  return apply_filters(
    'bazar_merge_freio_redundant_terms_map',
    array(
      'hidraulico' => 'disco-hidraulico',
      'mecanico'   => 'disco-mecanico',
    )
  );
}

/**
 * Copia term_meta do termo de origem para o destino apenas quando a chave não existe no destino.
 *
 * @param int $from_term_id
 * @param int $to_term_id
 */
function bazar_merge_freio_copy_term_meta_if_missing($from_term_id, $to_term_id)
{
  global $wpdb;

  $from_term_id = (int) $from_term_id;
  $to_term_id = (int) $to_term_id;
  if ($from_term_id <= 0 || $to_term_id <= 0 || $from_term_id === $to_term_id) {
    return;
  }

  $rows = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT meta_key, meta_value FROM {$wpdb->termmeta} WHERE term_id = %d",
      $from_term_id
    )
  );

  if (empty($rows)) {
    return;
  }

  foreach ($rows as $row) {
    $key = $row->meta_key;
    if ($key === '' || $key === 'term_order') {
      continue;
    }
    if (metadata_exists('term', $to_term_id, $key)) {
      continue;
    }
    add_term_meta($to_term_id, $key, maybe_unserialize($row->meta_value), true);
  }
}

/**
 * Verifica se o termo é filho direto do pai Freio.
 *
 * @param WP_Term $term
 * @param int     $freio_parent_id
 * @return bool
 */
function bazar_merge_freio_term_is_child_of_freio($term, $freio_parent_id)
{
  return $term && !is_wp_error($term) && (int) $term->parent === (int) $freio_parent_id;
}

/**
 * Migra posts do termo antigo para o canônico e remove o termo antigo se ficar vazio.
 *
 * @param bool  $dry_run
 * @param array $args { bool delete_empty: apagar termo redundante após migração (default true), bool copy_meta: copiar term_meta (default true) }
 * @return array{ success: bool, message: string, stats: array }
 */
function bazar_merge_freio_redundant_terms($dry_run = true, $args = array())
{
  $delete_empty = !isset($args['delete_empty']) || (bool) $args['delete_empty'];
  $copy_meta = !isset($args['copy_meta']) || (bool) $args['copy_meta'];

  $stats = array(
    'freio_parent_id'   => 0,
    'pairs'             => array(),
    'posts_scanned'     => 0,
    'posts_migrated'    => 0,
    'terms_deleted'     => 0,
    'skipped_no_parent' => 0,
    'skipped_no_old'    => 0,
    'skipped_no_new'    => 0,
    'skipped_same'      => 0,
    'skipped_bad_parent'=> 0,
    'errors'            => 0,
    'details'           => array(),
  );

  $parent_slug = bazar_merge_freio_parent_slug();
  $freio_parent = get_term_by('slug', $parent_slug, 'componente');

  if (!$freio_parent || is_wp_error($freio_parent) || (int) $freio_parent->parent !== 0) {
    return array(
      'success' => false,
      'message' => 'Termo pai "Freio" não encontrado (slug: ' . $parent_slug . ').',
      'stats'   => $stats,
    );
  }

  $freio_parent_id = (int) $freio_parent->term_id;
  $stats['freio_parent_id'] = $freio_parent_id;

  $map = bazar_merge_freio_redundant_terms_map();

  foreach ($map as $old_slug => $new_slug) {
    $old_slug = sanitize_title((string) $old_slug);
    $new_slug = sanitize_title((string) $new_slug);

    $pair_stat = array(
      'old_slug'       => $old_slug,
      'new_slug'       => $new_slug,
      'old_id'         => 0,
      'new_id'         => 0,
      'posts_found'    => 0,
      'posts_migrated' => 0,
      'deleted'        => false,
      'note'           => '',
    );

    if ($old_slug === $new_slug) {
      $stats['skipped_same']++;
      $pair_stat['note'] = 'Slugs iguais; ignorado.';
      $stats['details'][] = $pair_stat;
      continue;
    }

    $old_term = get_term_by('slug', $old_slug, 'componente');
    $new_term = get_term_by('slug', $new_slug, 'componente');

    if (!$old_term || is_wp_error($old_term)) {
      $stats['skipped_no_old']++;
      $pair_stat['note'] = 'Termo antigo não existe.';
      $stats['details'][] = $pair_stat;
      continue;
    }

    if (!bazar_merge_freio_term_is_child_of_freio($old_term, $freio_parent_id)) {
      $stats['skipped_bad_parent']++;
      $pair_stat['old_id'] = (int) $old_term->term_id;
      $pair_stat['note'] = 'Termo antigo não é filho direto de Freio.';
      $stats['details'][] = $pair_stat;
      continue;
    }

    if (!$new_term || is_wp_error($new_term)) {
      $stats['skipped_no_new']++;
      $pair_stat['old_id'] = (int) $old_term->term_id;
      $pair_stat['note'] = 'Termo canônico não existe — crie "' . $new_slug . '" em Componente > Freio antes de migrar.';
      $stats['details'][] = $pair_stat;
      continue;
    }

    if (!bazar_merge_freio_term_is_child_of_freio($new_term, $freio_parent_id)) {
      $stats['skipped_bad_parent']++;
      $pair_stat['old_id'] = (int) $old_term->term_id;
      $pair_stat['new_id'] = (int) $new_term->term_id;
      $pair_stat['note'] = 'Termo canônico não é filho direto de Freio.';
      $stats['details'][] = $pair_stat;
      continue;
    }

    $old_id = (int) $old_term->term_id;
    $new_id = (int) $new_term->term_id;
    $pair_stat['old_id'] = $old_id;
    $pair_stat['new_id'] = $new_id;

    if ($old_id === $new_id) {
      $stats['skipped_same']++;
      $pair_stat['note'] = 'Mesmo term_id.';
      $stats['details'][] = $pair_stat;
      continue;
    }

    $posts = get_posts(array(
      'post_type'              => 'any',
      'posts_per_page'         => -1,
      'post_status'            => 'any',
      'fields'                 => 'ids',
      'orderby'                => 'ID',
      'order'                  => 'ASC',
      'tax_query'              => array(
        array(
          'taxonomy' => 'componente',
          'field'    => 'term_id',
          'terms'    => array($old_id),
        ),
      ),
      'no_found_rows'          => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => true,
    ));

    $pair_stat['posts_found'] = is_array($posts) ? count($posts) : 0;
    $stats['posts_scanned'] += $pair_stat['posts_found'];

    if ($dry_run) {
      $stats['details'][] = $pair_stat;
      continue;
    }

    if ($copy_meta) {
      bazar_merge_freio_copy_term_meta_if_missing($old_id, $new_id);
    }

    $migrated = 0;
    if (!empty($posts)) {
      foreach ($posts as $post_id) {
        $post_id = (int) $post_id;
        $current = wp_get_object_terms($post_id, 'componente', array('fields' => 'ids'));

        if (is_wp_error($current)) {
          $stats['errors']++;
          continue;
        }

        if (in_array($new_id, $current, true)) {
          $updated = array_values(array_diff($current, array($old_id)));
        } else {
          $updated = array_values(array_unique(array_merge(array_diff($current, array($old_id)), array($new_id))));
        }

        $set = wp_set_object_terms($post_id, $updated, 'componente', false);
        if (is_wp_error($set)) {
          $stats['errors']++;
          continue;
        }

        $migrated++;
        $stats['posts_migrated']++;
      }
    }

    $pair_stat['posts_migrated'] = $migrated;

    $remaining = get_objects_in_term($old_id, 'componente');
    if (is_wp_error($remaining)) {
      $stats['errors']++;
      $pair_stat['note'] = 'Erro ao verificar objetos restantes no termo antigo.';
      $stats['details'][] = $pair_stat;
      continue;
    }

    $remaining = is_array($remaining) ? array_filter($remaining) : array();

    if ($delete_empty && empty($remaining)) {
      $del = wp_delete_term($old_id, 'componente');
      if (is_wp_error($del)) {
        $stats['errors']++;
        $pair_stat['note'] = 'Falha ao apagar termo: ' . $del->get_error_message();
      } else {
        $pair_stat['deleted'] = true;
        $stats['terms_deleted']++;
      }
    } elseif (!empty($remaining)) {
      $pair_stat['note'] = 'Termo antigo mantido: ainda há ' . count($remaining) . ' objeto(s) associado(s).';
    }

    $stats['details'][] = $pair_stat;
  }

  if (!$dry_run && function_exists('bazar_clear_componentes_cache')) {
    bazar_clear_componentes_cache();
  }

  $msg = $dry_run
    ? sprintf(
      'Simulação: %d par(es) analisado(s). Posts que usam termos redundantes: %d. Execute sem simulação para aplicar.',
      count($map),
      (int) $stats['posts_scanned']
    )
    : sprintf(
      'Migração concluída. Posts migrados: %d. Termos redundantes removidos: %d. Erros: %d.',
      (int) $stats['posts_migrated'],
      (int) $stats['terms_deleted'],
      (int) $stats['errors']
    );

  return array(
    'success' => $stats['errors'] === 0 || $stats['posts_migrated'] > 0 || $dry_run,
    'message' => $msg,
    'stats'   => $stats,
  );
}

/**
 * Admin: Ferramentas
 */
function bazar_merge_freio_redundant_terms_admin_menu()
{
  add_management_page(
    'Unificar termos Freio (redundantes)',
    'Freio: termos redundantes',
    'manage_options',
    'bazar-merge-freio-redundant-terms',
    'bazar_merge_freio_redundant_terms_admin_page'
  );
}
add_action('admin_menu', 'bazar_merge_freio_redundant_terms_admin_menu');

/**
 * Página de administração
 */
function bazar_merge_freio_redundant_terms_admin_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  $map = bazar_merge_freio_redundant_terms_map();
  $parent_slug = bazar_merge_freio_parent_slug();

  if (isset($_POST['bazar_merge_freio_terms']) && check_admin_referer('bazar_merge_freio_terms')) {
    $dry = !isset($_POST['execute']) || (string) $_POST['execute'] !== '1';
    $result = bazar_merge_freio_redundant_terms(
      $dry,
      array(
        'delete_empty' => isset($_POST['delete_empty']) && (string) $_POST['delete_empty'] === '1',
        'copy_meta'    => isset($_POST['copy_meta']) && (string) $_POST['copy_meta'] === '1',
      )
    );

    $class = !empty($result['success']) ? 'notice-success' : 'notice-error';
    echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';

    if (!empty($result['stats']['details'])) {
      echo '<div class="card" style="max-width:960px;margin-top:16px;"><h2>Detalhe por par</h2>';
      echo '<table class="widefat striped"><thead><tr>';
      echo '<th>Antigo → Canônico</th><th>IDs</th><th>Posts</th><th>Nota</th>';
      echo '</tr></thead><tbody>';
      foreach ($result['stats']['details'] as $row) {
        echo '<tr>';
        echo '<td><code>' . esc_html($row['old_slug']) . '</code> → <code>' . esc_html($row['new_slug']) . '</code></td>';
        echo '<td>' . (int) $row['old_id'] . ' → ' . (int) $row['new_id'] . '</td>';
        echo '<td>' . (int) $row['posts_found'] . ' / ' . (int) $row['posts_migrated'] . '</td>';
        echo '<td>' . esc_html($row['note'] ?? '') . ($row['deleted'] ?? false ? ' <strong>Termo antigo excluído.</strong>' : '') . '</td>';
        echo '</tr>';
      }
      echo '</tbody></table></div>';
    }
  }
  ?>
  <div class="wrap">
    <h1>Unificar termos redundantes (Freio)</h1>

    <div class="card" style="max-width: 720px; margin: 20px 0;">
      <h2>O que faz</h2>
      <p>Para cada par abaixo, reatribui aos anúncios o termo <strong>canônico</strong> e remove o termo redundante dos posts. Se nada mais usar o termo antigo, ele pode ser <strong>excluído</strong> (opcional).</p>
      <p>Pai esperado na taxonomia <code>componente</code>: slug <code><?php echo esc_html($parent_slug); ?></code>.</p>
      <p><strong>Mapa:</strong></p>
      <table class="widefat striped">
        <thead><tr><th>Redundante (slug)</th><th>Canônico (slug)</th></tr></thead>
        <tbody>
        <?php foreach ($map as $from => $to) : ?>
          <tr>
            <td><code><?php echo esc_html($from); ?></code></td>
            <td><code><?php echo esc_html($to); ?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="description">Filtros: <code>bazar_merge_freio_redundant_terms_map</code>, <code>bazar_merge_freio_parent_slug</code>.</p>
    </div>

    <div class="card" style="max-width: 720px; margin: 20px 0;">
      <h2>Executar</h2>
      <form method="post">
        <?php wp_nonce_field('bazar_merge_freio_terms'); ?>
        <input type="hidden" name="bazar_merge_freio_terms" value="1" />
        <p>
          <label><input type="radio" name="execute" value="0" checked />
            <strong>Simular</strong> — apenas contar posts e validar termos</label>
        </p>
        <p>
          <label><input type="radio" name="execute" value="1" />
            <strong>Executar</strong> — migrar posts e apagar termos vazios</label>
        </p>
        <p>
          <label><input type="checkbox" name="delete_empty" value="1" checked />
            Excluir termo redundante do WordPress se não houver mais objetos associados</label>
        </p>
        <p>
          <label><input type="checkbox" name="copy_meta" value="1" checked />
            Copiar <code>term_meta</code> do termo antigo para o canônico (só chaves que o canônico ainda não tem)</label>
        </p>
        <p><button type="submit" class="button button-primary">Enviar</button></p>
      </form>
    </div>

    <div class="notice notice-warning" style="max-width:720px;">
      <p><strong>Backup:</strong> faça backup do banco antes de executar em produção.</p>
    </div>
  </div>
  <?php
}
