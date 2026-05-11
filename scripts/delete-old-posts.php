<?php
/**
 * Script para desativar postagens antigas
 * 
 * Desativa postagens publicadas há mais de 1 ano, marcando-as como vendidas
 * 
 * IMPORTANTE: Execute este script apenas através do painel do WordPress
 * ou via WP-CLI. Para executar via painel, adicione no functions.php:
 * require_once get_template_directory() . '/scripts/delete-old-posts.php';
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Remove imagens anexadas de uma postagem, exceto a imagem destacada
 * @param int $post_id ID da postagem
 * @return int Número de imagens removidas
 */
function bazar_remove_post_images_except_featured($post_id)
{
  $featured_id = get_post_thumbnail_id($post_id);
  $attachments = get_attached_media('image', $post_id);
  $removed_count = 0;

  foreach ($attachments as $attachment) {
    if ($attachment->ID != $featured_id) {
      $deleted = wp_delete_attachment($attachment->ID, true);
      if ($deleted) {
        $removed_count++;
      }
    }
  }

  return $removed_count;
}

/**
 * Função principal para desativar postagens antigas
 * Regra de negócio:
 * - Postagens com mais de 1 ano (mas menos de 2): pode marcar como vendido OU mover para lixeira
 * - Postagens com mais de 2 anos: pode marcar como vendido OU mover para lixeira
 * 
 * @param bool $dry_run Se true, apenas lista as postagens sem desativar
 * @param bool $force Forçar execução mesmo se já foi executado
 * @param array $post_types Tipos de post a processar (padrão: todos)
 * @param string $action_1_year Ação para postagens com mais de 1 ano: 'vendido' ou 'trash' (padrão: 'vendido')
 * @param string $action_2_years Ação para postagens com mais de 2 anos: 'vendido' ou 'trash' (padrão: 'trash')
 * @return array Array com estatísticas da operação
 */
function bazar_deactivate_old_posts($dry_run = false, $force = false, $post_types = array(), $action_1_year = 'vendido', $action_2_years = 'trash')
{

  // Verificar se já foi executado (a menos que seja forçado ou dry_run)
  // Dry run sempre pode ser executado para verificar novamente
  if (!$force && !$dry_run && get_option('bazar_old_posts_deactivated')) {
    return array(
      'success' => true,
      'message' => 'Desativação de postagens antigas já foi executada anteriormente. Use "Forçar execução" para executar novamente.',
      'stats' => array()
    );
  }

  // Validar ações
  if (!in_array($action_1_year, array('vendido', 'trash'))) {
    $action_1_year = 'vendido';
  }
  if (!in_array($action_2_years, array('vendido', 'trash'))) {
    $action_2_years = 'trash';
  }

  $stats = array(
    'posts_found' => 0,
    'posts_found_more_than_1_year' => 0,
    'posts_found_more_than_2_years' => 0,
    'posts_marked_as_sold' => 0,      // Marcadas como vendido
    'posts_moved_to_trash' => 0,      // Movidas para lixeira
    'drafts_moved_to_trash' => 0,     // Rascunhos (qualquer idade) - sempre para lixeira
    'posts_skipped' => 0,
    'images_removed' => 0,
    'errors' => 0,
    'action_1_year' => $action_1_year,
    'action_2_years' => $action_2_years,
    'details' => array()
  );

  // Calcular datas limite
  $one_year_ago = date('Y-m-d H:i:s', strtotime('-1 year'));
  $two_years_ago = date('Y-m-d H:i:s', strtotime('-2 years'));

  // Se não especificou post_types, buscar todos os tipos de post (exceto attachment, revision, nav_menu_item)
  if (empty($post_types)) {
    $post_types = get_post_types(array('public' => true), 'names');
    // Remover tipos que não devem ser processados
    $excluded_types = array('attachment', 'revision', 'nav_menu_item');
    $post_types = array_diff($post_types, $excluded_types);
  }

  // Buscar postagens com mais de 1 ano que ainda não foram marcadas como vendidas ou movidas para lixo
  // Excluir posts que já têm termo 'vendido' na taxonomia 'status' ou que estão no lixo
  $vendido_term = get_term_by('slug', 'vendido', 'status');
  $vendido_term_id = $vendido_term ? $vendido_term->term_id : 0;

  $args = array(
    'post_type' => $post_types,
    'post_status' => array('publish', 'draft', 'pending', 'private'), // Excluir 'trash'
    'posts_per_page' => -1,
    'date_query' => array(
      array(
        'before' => $one_year_ago,
        'inclusive' => true
      )
    ),
    'tax_query' => array(
      array(
        'taxonomy' => 'status',
        'field' => 'term_id',
        'terms' => $vendido_term_id,
        'operator' => 'NOT IN'
      )
    ),
    'fields' => 'ids'
  );

  $old_posts = get_posts($args);
  $stats['posts_found'] = count($old_posts);

  if (!empty($old_posts)) {
    error_log('[Bazar Deactivate Old Posts] Encontradas ' . count($old_posts) . ' postagens com mais de 1 ano');
  }

  // Buscar TODAS as postagens em rascunho (draft) para mover para lixeira
  $draft_args = array(
    'post_type' => $post_types,
    'post_status' => 'draft',
    'posts_per_page' => -1,
    'fields' => 'ids'
  );

  $draft_posts = get_posts($draft_args);

  if (!empty($draft_posts)) {
    error_log('[Bazar Deactivate Old Posts] Encontradas ' . count($draft_posts) . ' postagens em rascunho para mover para lixeira');
    // Ação silenciosa: não disparar e-mail (pending-to-trash.php verifica este flag)
    $GLOBALS['bazar_silent_trash'] = true;

    foreach ($draft_posts as $draft_id) {
      $draft_post = get_post($draft_id);

      if (!$draft_post) {
        $stats['posts_skipped']++;
        continue;
      }

      $draft_title = get_the_title($draft_id);
      $draft_type = get_post_type($draft_id);
      $draft_date = get_the_date('Y-m-d H:i:s', $draft_id);

      if ($dry_run) {
        $stats['drafts_moved_to_trash']++;
        $stats['details'][] = array(
          'post_id' => $draft_id,
          'post_title' => $draft_title,
          'post_type' => $draft_type,
          'post_status' => 'draft',
          'post_date' => $draft_date,
          'age_category' => 'Rascunho',
          'action' => 'Seria movida para o lixo'
        );
      } else {
        // Mover rascunho para lixeira
        $trash_result = wp_trash_post($draft_id);

        if (is_wp_error($trash_result)) {
          $stats['errors']++;
          error_log('[Bazar Deactivate Old Posts] Erro ao mover rascunho para lixo ID ' . $draft_id . ': ' . $draft_title . ' - ' . $trash_result->get_error_message());
          $stats['details'][] = array(
            'post_id' => $draft_id,
            'post_title' => $draft_title,
            'post_type' => $draft_type,
            'post_status' => 'draft',
            'post_date' => $draft_date,
            'age_category' => 'Rascunho',
            'error' => 'Erro ao mover para lixo'
          );
        } else {
          // Remover imagens exceto featured image
          $images_removed = bazar_remove_post_images_except_featured($draft_id);
          $stats['images_removed'] += $images_removed;

          $stats['drafts_moved_to_trash']++;
          error_log('[Bazar Deactivate Old Posts] Rascunho movido para lixo: ID ' . $draft_id . ' - ' . $draft_title . ' (' . $draft_type . ', ' . $draft_date . ') - ' . $images_removed . ' imagens removidas');
          $stats['details'][] = array(
            'post_id' => $draft_id,
            'post_title' => $draft_title,
            'post_type' => $draft_type,
            'post_status' => 'draft',
            'post_date' => $draft_date,
            'age_category' => 'Rascunho',
            'action' => 'Movida para o lixo',
            'images_removed' => $images_removed
          );
        }
      }
    }

    $GLOBALS['bazar_silent_trash'] = false;
  }

  // Se não há postagens antigas nem rascunhos, retornar
  if (empty($old_posts) && empty($draft_posts)) {
    return array(
      'success' => true,
      'message' => 'Nenhuma postagem encontrada para processar (postagens antigas ou rascunhos).',
      'stats' => $stats
    );
  }

  // Processar cada postagem antiga
  if (!empty($old_posts)) {
    foreach ($old_posts as $post_id) {
      $post = get_post($post_id);

      if (!$post) {
        $stats['posts_skipped']++;
        continue;
      }

      $post_date = get_the_date('Y-m-d H:i:s', $post_id);
      $post_timestamp = get_post_time('U', false, $post_id);
      $post_title = get_the_title($post_id);
      $post_type = get_post_type($post_id);
      $post_status = $post->post_status;

      // Determinar idade da postagem
      $two_years_ago_timestamp = strtotime('-2 years');
      $is_more_than_2_years = ($post_timestamp <= $two_years_ago_timestamp);

      if ($is_more_than_2_years) {
        $stats['posts_found_more_than_2_years']++;
      } else {
        $stats['posts_found_more_than_1_year']++;
      }

      // Determinar ação baseada na idade e configuração
      $selected_action = $is_more_than_2_years ? $action_2_years : $action_1_year;

      if ($dry_run) {
        // Apenas adicionar aos detalhes sem processar
        $action_text = $selected_action === 'trash' ? 'Seria movida para o lixo' : 'Seria marcada como vendida';
        $stats['details'][] = array(
          'post_id' => $post_id,
          'post_title' => $post_title,
          'post_type' => $post_type,
          'post_status' => $post_status,
          'post_date' => $post_date,
          'age_category' => $is_more_than_2_years ? 'Mais de 2 anos' : 'Mais de 1 ano',
          'action' => $action_text
        );
        if ($selected_action === 'trash') {
          $stats['posts_moved_to_trash']++;
        } else {
          $stats['posts_marked_as_sold']++;
        }
      } else {
        if ($selected_action === 'trash') {
          // Mover para o lixo e remover imagens
          $trash_result = wp_trash_post($post_id);

          if (is_wp_error($trash_result)) {
            $stats['errors']++;
            error_log('[Bazar Deactivate Old Posts] Erro ao mover post para lixo ID ' . $post_id . ': ' . $post_title . ' - ' . $trash_result->get_error_message());
            $stats['details'][] = array(
              'post_id' => $post_id,
              'post_title' => $post_title,
              'post_type' => $post_type,
              'post_status' => $post_status,
              'post_date' => $post_date,
              'age_category' => $is_more_than_2_years ? 'Mais de 2 anos' : 'Mais de 1 ano',
              'error' => 'Erro ao mover para lixo'
            );
          } else {
            // Remover imagens exceto featured image
            $images_removed = bazar_remove_post_images_except_featured($post_id);
            $stats['images_removed'] += $images_removed;

            $stats['posts_moved_to_trash']++;
            error_log('[Bazar Deactivate Old Posts] Post movido para lixo: ID ' . $post_id . ' - ' . $post_title . ' (' . $post_type . ', ' . $post_date . ') - ' . $images_removed . ' imagens removidas');
            $stats['details'][] = array(
              'post_id' => $post_id,
              'post_title' => $post_title,
              'post_type' => $post_type,
              'post_status' => $post_status,
              'post_date' => $post_date,
              'age_category' => $is_more_than_2_years ? 'Mais de 2 anos' : 'Mais de 1 ano',
              'action' => 'Movida para o lixo',
              'images_removed' => $images_removed
            );
          }
        } else {
          // Marcar como vendida usando taxonomia 'status'
          $deactivation_date = current_time('mysql');

          // Buscar termo 'vendido' da taxonomia 'status'
          $vendido_term = get_term_by('slug', 'vendido', 'status');
          if (!$vendido_term) {
            // Criar termo se não existir
            $term_result = wp_insert_term('Vendido', 'status', array(
              'description' => 'Produto vendido/desativado',
              'slug' => 'vendido'
            ));
            if (is_wp_error($term_result)) {
              $stats['errors']++;
              error_log('[Bazar Deactivate Old Posts] Erro ao criar termo vendido: ' . $term_result->get_error_message());
              $stats['details'][] = array(
                'post_id' => $post_id,
                'post_title' => $post_title,
                'post_type' => $post_type,
                'post_status' => $post_status,
                'post_date' => $post_date,
                'age_category' => $is_more_than_2_years ? 'Mais de 2 anos' : 'Mais de 1 ano',
                'error' => 'Erro ao criar termo vendido'
              );
              continue;
            }
            $vendido_term_id = $term_result['term_id'];
          } else {
            $vendido_term_id = $vendido_term->term_id;
          }

          // Adicionar termo 'vendido' à postagem (taxonomia 'status')
          $term_result = wp_set_object_terms($post_id, array($vendido_term_id), 'status', false);

          // Salvar data de desativação (mantido para histórico)
          update_post_meta($post_id, '_bazar_vendido_date', $deactivation_date);

          // NÃO remover imagens quando marca como vendido

          if (is_wp_error($term_result)) {
            $stats['errors']++;
            error_log('[Bazar Deactivate Old Posts] Erro ao marcar post como vendido ID ' . $post_id . ': ' . $post_title . ' - ' . $term_result->get_error_message());
            $stats['details'][] = array(
              'post_id' => $post_id,
              'post_title' => $post_title,
              'post_type' => $post_type,
              'post_status' => $post_status,
              'post_date' => $post_date,
              'age_category' => $is_more_than_2_years ? 'Mais de 2 anos' : 'Mais de 1 ano',
              'error' => 'Erro ao marcar como vendido'
            );
          } else {
            $stats['posts_marked_as_sold']++;
            error_log('[Bazar Deactivate Old Posts] Post marcado como vendido: ID ' . $post_id . ' - ' . $post_title . ' (' . $post_type . ', ' . $post_date . ')');
            $stats['details'][] = array(
              'post_id' => $post_id,
              'post_title' => $post_title,
              'post_type' => $post_type,
              'post_status' => $post_status,
              'post_date' => $post_date,
              'age_category' => $is_more_than_2_years ? 'Mais de 2 anos' : 'Mais de 1 ano',
              'action' => 'Marcada como vendida',
              'images_removed' => 0 // Não remove imagens
            );
          }
        }
      }
    }
  } // Fim do loop de postagens antigas (if !empty($old_posts))

  // Marcar como executado (apenas se não for dry run)
  if (!$dry_run) {
    update_option('bazar_old_posts_deactivated', true);
    update_option('bazar_old_posts_deactivated_date', current_time('mysql'));
    update_option('bazar_old_posts_deactivated_stats', $stats);
  }

  $total_processed = $stats['posts_marked_as_sold'] + $stats['posts_moved_to_trash'] + $stats['drafts_moved_to_trash'];
  $message = $dry_run
    ? 'Dry Run concluído! ' . $stats['posts_marked_as_sold'] . ' postagem(ns) seriam marcadas como vendidas, ' . $stats['posts_moved_to_trash'] . ' seriam movidas para o lixo e ' . $stats['drafts_moved_to_trash'] . ' rascunho(s) seriam movidos para o lixo.'
    : 'Processamento de postagens antigas concluído com sucesso! ' . $stats['posts_marked_as_sold'] . ' marcadas como vendidas, ' . $stats['posts_moved_to_trash'] . ' movidas para o lixo e ' . $stats['drafts_moved_to_trash'] . ' rascunho(s) movidos para o lixo.';

  return array(
    'success' => true,
    'message' => $message,
    'stats' => $stats
  );
}

/**
 * Calcula estatísticas de postagens por idade (apenas posts do tipo 'post')
 * @return array Array com estatísticas de postagens por idade
 */
function bazar_get_posts_age_stats_for_delete()
{
  $stats = array(
    'total_posts' => 0,
    'last_year' => 0,      // Último ano (menos de 1 ano)
    'more_than_1_year' => 0,  // Mais de 1 ano
    'more_than_2_years' => 0  // Mais de 2 anos
  );

  // Data atual
  $now = current_time('timestamp');

  // Calcular datas de corte
  $one_year_ago = strtotime('-1 year', $now);
  $two_years_ago = strtotime('-2 years', $now);

  // Buscar apenas posts do tipo 'post' (exceto revisões e rascunhos)
  $args = array(
    'post_type' => 'post',  // Apenas posts do tipo 'post'
    'post_status' => array('publish', 'private', 'draft', 'pending'),
    'posts_per_page' => -1,
    'fields' => 'ids'
  );

  $all_posts = get_posts($args);
  $stats['total_posts'] = count($all_posts);

  foreach ($all_posts as $post_id) {
    $post_date = get_post_time('U', false, $post_id);

    if ($post_date >= $one_year_ago) {
      // Do último ano (menos de 1 ano)
      $stats['last_year']++;
    } elseif ($post_date >= $two_years_ago) {
      // Mais de 1 ano, mas menos de 2 anos
      $stats['more_than_1_year']++;
    } else {
      // Mais de 2 anos
      $stats['more_than_2_years']++;
    }
  }

  return $stats;
}

// Adicionar página no menu admin para executar o script
function bazar_add_deactivate_old_posts_menu()
{
  add_management_page(
    'Desativar Postagens Antigas',
    'Desativar Postagens Antigas',
    'manage_options',
    'bazar-deactivate-old-posts',
    'bazar_deactivate_old_posts_page'
  );
}
add_action('admin_menu', 'bazar_add_deactivate_old_posts_menu');

// Página de administração para desativar postagens antigas
function bazar_deactivate_old_posts_page()
{
  $already_deactivated = get_option('bazar_old_posts_deactivated');
  $deactivated_date = get_option('bazar_old_posts_deactivated_date');
  $last_stats = get_option('bazar_old_posts_deactivated_stats');

  // Calcular estatísticas de postagens por idade (apenas posts do tipo 'post')
  $posts_age_stats = bazar_get_posts_age_stats_for_delete();

  // Obter todos os tipos de post disponíveis
  $available_post_types = get_post_types(array('public' => true), 'objects');
  $excluded_types = array('attachment', 'revision', 'nav_menu_item');
  $available_post_types = array_filter($available_post_types, function ($type) use ($excluded_types) {
    return !in_array($type->name, $excluded_types);
  });
  ?>
  <div class="wrap">
    <h1>Desativar Postagens Antigas</h1>

    <!-- Resumo de Postagens por Idade -->
    <div class="card" style="margin-bottom: 20px;">
      <h2>Resumo de Postagens por Idade (apenas Posts)</h2>
      <table class="widefat">
        <tr>
          <td><strong>Total de postagens:</strong></td>
          <td><?php echo number_format($posts_age_stats['total_posts'], 0, ',', '.'); ?></td>
        </tr>
        <tr>
          <td><strong>Do último ano (menos de 1 ano):</strong></td>
          <td><strong
              style="color: #00a32a;"><?php echo number_format($posts_age_stats['last_year'], 0, ',', '.'); ?></strong>
          </td>
        </tr>
        <tr>
          <td><strong>Mais de 1 ano:</strong></td>
          <td><strong
              style="color: #dba617;"><?php echo number_format($posts_age_stats['more_than_1_year'], 0, ',', '.'); ?></strong>
          </td>
        </tr>
        <tr>
          <td><strong>Mais de 2 anos:</strong></td>
          <td><strong
              style="color: #d63638;"><?php echo number_format($posts_age_stats['more_than_2_years'], 0, ',', '.'); ?></strong>
          </td>
        </tr>
      </table>
    </div>

    <?php
    // Estatísticas de postagens marcadas como vendidas
    $sold_count = 0;
    $vendido_term = get_term_by('slug', 'vendido', 'status');
    if ($vendido_term) {
      $sold_query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'tax_query' => array(
          array(
            'taxonomy' => 'status',
            'field' => 'term_id',
            'terms' => $vendido_term->term_id
          )
        ),
        'fields' => 'ids'
      ));
      if ($sold_query->have_posts()) {
        $sold_count = $sold_query->post_count;
      }
      wp_reset_postdata();
    }

    // Contar posts no lixo
    $trash_count = wp_count_posts('post')->trash;
    ?>

    <div class="card" style="margin-bottom: 20px;">
      <h2>Estatísticas de Processamento</h2>
      <table class="widefat">
        <tr>
          <td><strong>Postagens marcadas como vendidas:</strong></td>
          <td><strong style="color: #dba617;"><?php echo number_format($sold_count, 0, ',', '.'); ?></strong></td>
        </tr>
        <tr>
          <td><strong>Postagens no lixo:</strong></td>
          <td><strong style="color: #d63638;"><?php echo number_format($trash_count, 0, ',', '.'); ?></strong></td>
        </tr>
      </table>
    </div>

    <?php
    // Executar desativação
    if (isset($_POST['bazar_execute_deactivate_old_posts']) && check_admin_referer('bazar_deactivate_old_posts_action')) {
      $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] == '1';
      $force = isset($_POST['force']) && $_POST['force'] == '1';

      // Post types selecionados
      $selected_post_types = array();
      if (isset($_POST['post_types']) && is_array($_POST['post_types'])) {
        $selected_post_types = array_map('sanitize_text_field', $_POST['post_types']);
      }

      // Ações para cada faixa de idade
      $action_1_year = isset($_POST['action_1_year']) && in_array($_POST['action_1_year'], array('vendido', 'trash'))
        ? sanitize_text_field($_POST['action_1_year'])
        : 'vendido';
      $action_2_years = isset($_POST['action_2_years']) && in_array($_POST['action_2_years'], array('vendido', 'trash'))
        ? sanitize_text_field($_POST['action_2_years'])
        : 'trash';

      $result = bazar_deactivate_old_posts($dry_run, $force, $selected_post_types, $action_1_year, $action_2_years);

      $class = $result['success'] ? 'notice-success' : 'notice-error';
      echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';

      if (isset($result['stats'])) {
        $stats = $result['stats'];
        echo '<div class="notice notice-info"><p>';
        echo '<strong>Estatísticas:</strong><br>';
        echo 'Postagens encontradas (mais de 1 ano): ' . $stats['posts_found'] . '<br>';
        echo '&nbsp;&nbsp;- Mais de 1 ano (menos de 2): ' . $stats['posts_found_more_than_1_year'] . '<br>';
        echo '&nbsp;&nbsp;- Mais de 2 anos: ' . $stats['posts_found_more_than_2_years'] . '<br>';
        if ($dry_run) {
          echo 'Postagens que seriam marcadas como vendidas: ' . $stats['posts_marked_as_sold'] . '<br>';
          echo 'Postagens que seriam movidas para o lixo: ' . $stats['posts_moved_to_trash'] . '<br>';
        } else {
          echo 'Postagens marcadas como vendidas: ' . $stats['posts_marked_as_sold'] . '<br>';
          echo 'Postagens movidas para o lixo: ' . $stats['posts_moved_to_trash'] . '<br>';
          if (isset($stats['images_removed']) && $stats['images_removed'] > 0) {
            echo 'Imagens removidas (apenas posts no lixo): ' . $stats['images_removed'] . '<br>';
          }
        }
        echo 'Postagens ignoradas: ' . $stats['posts_skipped'] . '<br>';
        if ($stats['errors'] > 0) {
          echo 'Erros: ' . $stats['errors'] . '<br>';
        }
        echo '</p></div>';

        // Mostrar detalhes se houver
        if (!empty($stats['details']) && count($stats['details']) <= 100) {
          echo '<div class="card"><h3>Detalhes das Postagens</h3>';
          echo '<table class="widefat">';
          echo '<thead><tr><th>ID</th><th>Título</th><th>Tipo</th><th>Status</th><th>Data</th><th>Idade</th><th>Imagens Removidas</th><th>Ação/Erro</th></tr></thead>';
          echo '<tbody>';
          foreach ($stats['details'] as $detail) {
            echo '<tr>';
            echo '<td>' . $detail['post_id'] . '</td>';
            echo '<td><strong>' . esc_html($detail['post_title']) . '</strong></td>';
            echo '<td>' . esc_html($detail['post_type']) . '</td>';
            echo '<td>' . esc_html($detail['post_status']) . '</td>';
            echo '<td>' . esc_html($detail['post_date']) . '</td>';
            echo '<td>' . (isset($detail['age_category']) ? esc_html($detail['age_category']) : '-') . '</td>';
            echo '<td>' . (isset($detail['images_removed']) ? $detail['images_removed'] : '-') . '</td>';
            if (isset($detail['error'])) {
              echo '<td><span style="color: red;">' . esc_html($detail['error']) . '</span></td>';
            } else {
              echo '<td>' . esc_html($detail['action']) . '</td>';
            }
            echo '</tr>';
          }
          echo '</tbody></table>';
          echo '</div>';
        } elseif (!empty($stats['details'])) {
          echo '<div class="notice notice-info"><p>Total de ' . count($stats['details']) . ' postagens processadas. Detalhes completos disponíveis no log do WordPress.</p></div>';
        }
      }
    }
    ?>

    <div class="card">
      <h2>Processar Postagens Antigas</h2>
      <p>Este script processa postagens antigas de acordo com a seguinte regra de negócio:</p>

      <h3>Regra de Negócio:</h3>
      <ul>
        <li><strong>Postagens em rascunho (draft):</strong> Todas as postagens em rascunho são movidas para o lixo (trash)
          e suas imagens são removidas (exceto a imagem destacada), independente da idade.</li>
        <li><strong>Postagens com mais de 1 ano (mas menos de 2):</strong> Você pode escolher entre:
          <ul>
            <li><strong>Marcar como vendido:</strong> Adiciona termo 'vendido' na taxonomia 'status'. Mantém postagem
              publicada e <strong>NÃO remove imagens</strong>.</li>
            <li><strong>Mover para lixeira:</strong> Move postagem para lixeira e remove todas as imagens anexadas (exceto
              featured image).</li>
          </ul>
        </li>
        <li><strong>Postagens com mais de 2 anos:</strong> Você pode escolher entre:
          <ul>
            <li><strong>Marcar como vendido:</strong> Adiciona termo 'vendido' na taxonomia 'status'. Mantém postagem
              publicada e <strong>NÃO remove imagens</strong>.</li>
            <li><strong>Mover para lixeira:</strong> Move postagem para lixeira e remove todas as imagens anexadas (exceto
              featured image).</li>
          </ul>
        </li>
      </ul>

      <h3>Como funciona:</h3>
      <ol>
        <li><strong>Rascunhos (draft):</strong> Todas as postagens em rascunho são movidas para o lixo e suas imagens
          removidas (exceto featured image), independente da idade.</li>
        <li>Busca todas as postagens publicadas há mais de 1 ano que ainda não foram processadas</li>
        <li>Aplica a ação escolhida para cada faixa de idade (marcar como vendido OU mover para lixeira)</li>
        <li>Registra todas as operações no log do WordPress</li>
      </ol>

      <?php if ($already_deactivated): ?>
        <div class="notice notice-info">
          <p><strong>Status:</strong> A desativação de postagens antigas já foi executada anteriormente.</p>
          <?php if ($deactivated_date): ?>
            <p><strong>Data:</strong> <?php echo esc_html($deactivated_date); ?></p>
          <?php endif; ?>
          <?php if ($last_stats): ?>
            <p><strong>Última execução:</strong>
              <?php echo $last_stats['posts_marked_as_sold']; ?> postagens marcadas como vendidas e
              <?php echo $last_stats['posts_moved_to_trash']; ?> movidas para o lixo.
              <?php if (isset($last_stats['images_removed']) && $last_stats['images_removed'] > 0): ?>
                <?php echo $last_stats['images_removed']; ?> imagens removidas (apenas dos posts no lixo).
              <?php endif; ?>
            </p>
          <?php endif; ?>
          <p>Marque a opção abaixo para forçar a execução novamente.</p>
        </div>
      <?php else: ?>
        <div class="notice notice-warning">
          <p><strong>Status:</strong> A desativação de postagens antigas ainda não foi executada.</p>
        </div>
      <?php endif; ?>

      <form method="post" action="">
        <?php wp_nonce_field('bazar_deactivate_old_posts_action'); ?>

        <table class="form-table">
          <tr>
            <th scope="row">
              <label>Tipos de Post</label>
            </th>
            <td>
              <fieldset>
                <legend class="screen-reader-text"><span>Tipos de Post</span></legend>
                <?php foreach ($available_post_types as $post_type): ?>
                  <label>
                    <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php if ($post_type->name == 'post')
                         echo 'checked'; ?>>
                    <?php echo esc_html($post_type->label); ?> (<?php echo esc_html($post_type->name); ?>)
                  </label><br>
                <?php endforeach; ?>
              </fieldset>
              <p class="description">Selecione os tipos de post a processar. Se nenhum for selecionado, todos serão
                processados.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label>Ação para postagens com mais de 1 ano</label>
            </th>
            <td>
              <fieldset>
                <legend class="screen-reader-text"><span>Ação para postagens com mais de 1 ano</span></legend>
                <label>
                  <input type="radio" name="action_1_year" value="vendido" checked>
                  <strong>Marcar como vendido</strong> (adiciona termo 'vendido' na taxonomia 'status', mantém publicada,
                  não remove imagens)
                </label><br>
                <label>
                  <input type="radio" name="action_1_year" value="trash">
                  <strong>Mover para lixeira</strong> (move para lixeira e remove imagens, exceto featured image)
                </label>
              </fieldset>
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label>Ação para postagens com mais de 2 anos</label>
            </th>
            <td>
              <fieldset>
                <legend class="screen-reader-text"><span>Ação para postagens com mais de 2 anos</span></legend>
                <label>
                  <input type="radio" name="action_2_years" value="vendido">
                  <strong>Marcar como vendido</strong> (adiciona termo 'vendido' na taxonomia 'status', mantém publicada,
                  não remove imagens)
                </label><br>
                <label>
                  <input type="radio" name="action_2_years" value="trash" checked>
                  <strong>Mover para lixeira</strong> (move para lixeira e remove imagens, exceto featured image)
                </label>
              </fieldset>
            </td>
          </tr>
          <tr>
            <th scope="row">Opções</th>
            <td>
              <label>
                <input type="checkbox" name="dry_run" value="1" checked>
                <strong>Dry Run</strong> (apenas listar postagens sem processar)
              </label><br>
              <label>
                <input type="checkbox" name="force" value="1" <?php checked($already_deactivated); ?>>
                Forçar execução (executar mesmo se já foi executado anteriormente)
              </label>
            </td>
          </tr>
        </table>

        <p>
          <button type="submit" name="bazar_execute_deactivate_old_posts" class="button button-primary button-large">
            Desativar Postagens
          </button>
        </p>
      </form>

      <hr>
      <h3>Avisos Importantes</h3>
      <ul>
        <li><strong>Backup:</strong> Recomenda-se fazer backup do banco de dados antes de executar qualquer script.</li>
        <li><strong>Dry Run:</strong> Sempre execute primeiro com "Dry Run" marcado para ver quais postagens seriam
          processadas.</li>
        <li><strong>Rascunhos (draft):</strong> TODAS as postagens em rascunho serão movidas para o lixo e suas imagens
          removidas (exceto featured image), independente da idade.</li>
        <li><strong>Mais de 1 ano (menos de 2):</strong> Você pode escolher entre marcar como vendido (mantém publicada,
          não remove imagens) OU mover para lixeira (remove imagens).</li>
        <li><strong>Mais de 2 anos:</strong> Você pode escolher entre marcar como vendido (mantém publicada, não remove
          imagens) OU mover para lixeira (remove imagens).</li>
        <li><strong>Marcar como vendido:</strong> Adiciona termo 'vendido' na taxonomia 'status', mantém postagem
          publicada para preservar SEO, <strong>NÃO remove imagens</strong>.</li>
        <li><strong>Mover para lixeira:</strong> Move postagem para lixeira (trash) e remove todas as imagens anexadas,
          exceto a imagem destacada (featured image).</li>
        <li><strong>Status:</strong> O script processa postagens com status: publish, draft, pending, private (não
          processa posts já no lixo).</li>
        <li><strong>Tipos de Post:</strong> Por padrão, todos os tipos de post públicos são processados (exceto
          attachment, revision, nav_menu_item).</li>
        <li><strong>Taxonomia:</strong> O script usa a taxonomia 'status' com termo 'vendido' para marcar postagens como
          vendidas.</li>
      </ul>
    </div>
  </div>
  <?php
}
?>