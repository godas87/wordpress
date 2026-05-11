<?php
/**
 * Restaurar da lixeira e marcar como vendido
 *
 * Reduz 404: posts na lixeira viram 404. Este script restaura para "publicado"
 * e adiciona o termo "vendido" na taxonomia status. A URL volta a funcionar e
 * a página recebe NOINDEX (não indexa no Google), exibindo o badge "Vendido".
 *
 * Uso: Ferramentas > Restaurar lixeira como vendido
 * Ou inclua no functions.php: require_once get_template_directory() . '/scripts/restore-trash-as-vendido.php';
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Restaura posts da lixeira (post_type = post) e marca como vendido.
 *
 * @param bool $dry_run Se true, apenas lista e conta; não altera nada.
 * @return array { success, message, stats: { total, restored, errors, sample_ids } }
 */
function bazar_restore_trash_as_vendido($dry_run = true)
{
  $stats = array(
    'total' => 0,
    'restored' => 0,
    'errors' => 0,
    'sample_ids' => array(),
    'sample_titles' => array(),
  );

  $vendido_term = get_term_by('slug', 'vendido', 'status');
  if (!$vendido_term || is_wp_error($vendido_term)) {
    return array(
      'success' => false,
      'message' => 'Termo "vendido" da taxonomia "status" não encontrado. Verifique a taxonomia Status do Produto.',
      'stats' => $stats,
    );
  }

  $vendido_term_id = (int) $vendido_term->term_id;

  $query = new WP_Query(array(
    'post_type' => 'post',
    'post_status' => 'trash',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'orderby' => 'date',
    'order' => 'DESC',
  ));

  $post_ids = $query->posts;
  wp_reset_postdata();
  $stats['total'] = count($post_ids);

  if ($stats['total'] === 0) {
    return array(
      'success' => true,
      'message' => 'Nenhum anúncio na lixeira (post_type: post).',
      'stats' => $stats,
    );
  }

  $sample_limit = 10;

  // Evitar que hooks (email etc.) disparem ao restaurar em lote
  if (!$dry_run) {
    $GLOBALS['bazar_restore_trash_as_vendido'] = true;
  }

  foreach ($post_ids as $post_id) {
    if ($dry_run) {
      if (count($stats['sample_ids']) < $sample_limit) {
        $stats['sample_ids'][] = $post_id;
        $stats['sample_titles'][] = get_the_title($post_id);
      }
      $stats['restored']++;
      continue;
    }

    // Restaurar: post_status = publish
    $updated = wp_update_post(array(
      'ID' => $post_id,
      'post_status' => 'publish',
    ), true);

    if (is_wp_error($updated)) {
      $stats['errors']++;
      continue;
    }

    // Marcar como vendido (substitui outros termos de status)
    $term_result = wp_set_object_terms($post_id, array($vendido_term_id), 'status', false);
    if (is_wp_error($term_result)) {
      $stats['errors']++;
      continue;
    }

    $stats['restored']++;
  }

  if (!$dry_run) {
    unset($GLOBALS['bazar_restore_trash_as_vendido']);
  }

  if ($dry_run) {
    $message = sprintf(
      'Simulação: %s anúncio(s) na lixeira seriam restaurados e marcados como vendido (NOINDEX). Nenhuma alteração foi feita.',
      number_format_i18n($stats['total'])
    );
  } else {
    $message = sprintf(
      'Concluído: %s anúncio(s) restaurados e marcados como vendido. URLs voltam a abrir com página "Vendido" e NOINDEX.',
      number_format_i18n($stats['restored'])
    );
    if ($stats['errors'] > 0) {
      $message .= ' ' . sprintf('Erros: %s.', number_format_i18n($stats['errors']));
    }
  }

  return array(
    'success' => true,
    'message' => $message,
    'stats' => $stats,
  );
}

function bazar_add_restore_trash_as_vendido_menu()
{
  add_management_page(
    'Restaurar lixeira como vendido',
    'Lixeira → Vendido',
    'manage_options',
    'bazar-restore-trash-as-vendido',
    'bazar_restore_trash_as_vendido_page'
  );
}
add_action('admin_menu', 'bazar_add_restore_trash_as_vendido_menu');

function bazar_restore_trash_as_vendido_page()
{
  $trash_count = (int) wp_count_posts('post')->trash;
  $vendido_term = get_term_by('slug', 'vendido', 'status');
  ?>
  <div class="wrap">
    <h1>Restaurar da lixeira e marcar como vendido</h1>

    <div class="card" style="max-width: 640px; margin: 20px 0;">
      <h2>Por que usar?</h2>
      <p>Anúncios na <strong>lixeira</strong> geram <strong>404</strong> quando alguém acessa o link. Isso prejudica SEO e experiência.</p>
      <p>Ao restaurar e marcar como <strong>vendido</strong>:</p>
      <ul style="list-style: disc; margin-left: 20px;">
        <li>A URL volta a funcionar (página exibe "Vendido")</li>
        <li>A página recebe <strong>NOINDEX</strong> (não entra no Google)</li>
        <li>O anúncio não aparece em buscas nem listagens</li>
      </ul>
    </div>

    <div class="card" style="max-width: 640px; margin: 20px 0;">
      <h2>Status atual</h2>
      <table class="widefat">
        <tr>
          <td><strong>Anúncios na lixeira (post):</strong></td>
          <td><strong><?php echo number_format_i18n($trash_count); ?></strong></td>
        </tr>
        <tr>
          <td><strong>Termo "vendido" (status):</strong></td>
          <td><?php echo $vendido_term ? 'OK' : 'Não encontrado'; ?></td>
        </tr>
      </table>
    </div>

    <?php
    if (isset($_POST['bazar_restore_trash_as_vendido']) && current_user_can('manage_options') && check_admin_referer('bazar_restore_trash_as_vendido')) {
      $dry_run = !isset($_POST['execute']) || $_POST['execute'] !== '1';
      $result = bazar_restore_trash_as_vendido($dry_run);

      $class = $result['success'] ? 'notice-success' : 'notice-error';
      echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';

      if (!empty($result['stats']['sample_titles']) && $dry_run) {
        echo '<div class="notice notice-info"><p><strong>Exemplos que seriam processados:</strong></p><ul>';
        foreach (array_slice($result['stats']['sample_titles'], 0, 5) as $title) {
          echo '<li>' . esc_html($title) . '</li>';
        }
        if ($result['stats']['total'] > 5) {
          echo '<li>… e mais ' . ($result['stats']['total'] - 5) . '</li>';
        }
        echo '</ul></div>';
      }
    }
    ?>

    <div class="card" style="max-width: 640px; margin: 20px 0;">
      <h2>Executar</h2>
      <form method="post">
        <?php wp_nonce_field('bazar_restore_trash_as_vendido'); ?>
        <input type="hidden" name="bazar_restore_trash_as_vendido" value="1" />
        <p>
          <label>
            <input type="radio" name="execute" value="0" checked />
            <strong>Simular</strong> — só mostrar quantos seriam processados (não altera nada)
          </label>
        </p>
        <p>
          <label>
            <input type="radio" name="execute" value="1" />
            <strong>Executar</strong> — restaurar da lixeira e marcar todos como vendido
          </label>
        </p>
        <p>
          <button type="submit" class="button button-primary">Enviar</button>
        </p>
      </form>
    </div>
  </div>
  <?php
}
