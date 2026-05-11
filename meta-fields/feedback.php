<?php
/**
 * Meta Fields para Feedback
 * 
 * Exibe todos os dados do feedback no painel de edição do WordPress
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Adicionar meta box para exibir dados do feedback
 */
add_action('add_meta_boxes', 'bazar_add_feedback_meta_fields_box');
function bazar_add_feedback_meta_fields_box()
{
  // Adicionar apenas para feedback
  add_meta_box(
    'bazar_feedback_meta_fields',
    'Dados do Feedback',
    'bazar_display_feedback_meta_fields',
    'feedback',
    'normal',
    'high'
  );
}

/** Lista de meta keys do feedback (feedback-vendido.php) para exibição */
function bazar_feedback_meta_keys()
{
  return array(
    '_feedback_post_id' => 'ID do anúncio',
    '_feedback_author_id' => 'ID do autor',
    '_feedback_nota' => 'Nota (1-5)',
    '_feedback_recomendaria' => 'Recomendaria',
    '_feedback_o_que_gostou' => 'O que mais gostou',
    '_feedback_o_que_melhorar' => 'O que pode melhorar',
    '_feedback_comentarios' => 'Comentários adicionais',
    '_feedback_date' => 'Data do feedback',
    '_feedback_ip' => 'IP do usuário',
    '_feedback_token' => 'Token',
    '_feedback_enviado' => 'Feedback enviado',
    '_feedback_enviado_date' => 'Data do feedback enviado',
  );
}

/**
 * Exibir dados do feedback
 */
function bazar_display_feedback_meta_fields($post)
{
  $post_id = $post->ID;

  $feedback_post_id = get_post_meta($post_id, '_feedback_post_id', true);
  $author_id = get_post_meta($post_id, '_feedback_author_id', true);
  $nota = get_post_meta($post_id, '_feedback_nota', true);
  $recomendaria = get_post_meta($post_id, '_feedback_recomendaria', true);
  $o_que_gostou = get_post_meta($post_id, '_feedback_o_que_gostou', true);
  $o_que_melhorar = get_post_meta($post_id, '_feedback_o_que_melhorar', true);
  $comentarios = get_post_meta($post_id, '_feedback_comentarios', true);
  $feedback_date = get_post_meta($post_id, '_feedback_date', true);
  $feedback_ip = get_post_meta($post_id, '_feedback_ip', true);

  // Obter dados do anúncio
  $anuncio_post = $feedback_post_id ? get_post($feedback_post_id) : null;
  $anuncio_title = $anuncio_post ? get_the_title($feedback_post_id) : 'Anúncio não encontrado';
  $anuncio_url = $anuncio_post ? get_permalink($feedback_post_id) : '#';

  // Obter dados do autor
  $author_name = '—';
  $author_email = '—';
  if ($author_id) {
    $user = get_user_by('ID', $author_id);
    if ($user) {
      $author_name = $user->display_name;
      $author_email = $user->user_email;
    }
  }

  // Labels para recomendaria
  $recomendaria_labels = array(
    'sim' => 'Sim, definitivamente',
    'talvez' => 'Talvez',
    'nao' => 'Não'
  );
  $recomendaria_label = isset($recomendaria_labels[$recomendaria])
    ? $recomendaria_labels[$recomendaria]
    : ($recomendaria ?: '—');

  // Gerar estrelas para nota
  $estrelas_html = '';
  if ($nota) {
    for ($i = 1; $i <= 5; $i++) {
      if ($i <= $nota) {
        $estrelas_html .= '<span style="color: #ffc107; font-size: 18px;">★</span>';
      } else {
        $estrelas_html .= '<span style="color: #ddd; font-size: 18px;">☆</span>';
      }
    }
    $estrelas_html .= ' <strong>(' . $nota . '/5)</strong>';
  } else {
    $estrelas_html = '<span class="meta-empty">Não informado</span>';
  }

  ?>
  <div class="bazar-feedback-meta-fields-display">
    <style>
      .bazar-feedback-meta-fields-display {
        padding: 10px 0;
      }

      .bazar-feedback-meta-fields-display table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
      }

      .bazar-feedback-meta-fields-display table th,
      .bazar-feedback-meta-fields-display table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
        vertical-align: top;
      }

      .bazar-feedback-meta-fields-display table th {
        background-color: #f5f5f5;
        font-weight: 600;
        width: 25%;
      }

      .bazar-feedback-meta-fields-display table td {
        background-color: #fff;
      }

      .bazar-feedback-meta-fields-display .meta-empty {
        color: #999;
        font-style: italic;
      }

      .bazar-feedback-meta-fields-section {
        margin-bottom: 20px;
      }

      .bazar-feedback-meta-fields-section h3 {
        margin: 0 0 10px 0;
        padding: 10px;
        background-color: #f0f0f0;
        border-left: 4px solid #2271b1;
      }

      .bazar-feedback-meta-fields-display .text-content {
        white-space: pre-wrap;
        word-wrap: break-word;
        max-width: 100%;
      }

      .bazar-feedback-meta-fields-display a {
        color: #2271b1;
        text-decoration: none;
      }

      .bazar-feedback-meta-fields-display a:hover {
        text-decoration: underline;
      }
    </style>

    <!-- Seção: Informações Básicas -->
    <div class="bazar-feedback-meta-fields-section">
      <h3>📋 Informações Básicas</h3>
      <table>
        <tr>
          <th>Anúncio</th>
          <td>
            <?php if ($anuncio_post): ?>
              <a href="<?php echo esc_url($anuncio_url); ?>" target="_blank">
                <?php echo esc_html($anuncio_title); ?>
              </a>
              <br>
              <small>
                <a href="<?php echo esc_url(admin_url('post.php?post=' . $feedback_post_id . '&action=edit')); ?>">
                  Editar anúncio (ID: <?php echo esc_html($feedback_post_id); ?>)
                </a>
              </small>
            <?php else: ?>
              <span class="meta-empty">Anúncio não encontrado (ID: <?php echo esc_html($feedback_post_id); ?>)</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Autor do Feedback</th>
          <td>
            <strong><?php echo esc_html($author_name); ?></strong>
            <?php if ($author_email): ?>
              <br><small><?php echo esc_html($author_email); ?></small>
            <?php endif; ?>
            <?php if ($author_id): ?>
              <br><small>
                <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $author_id)); ?>">
                  Editar usuário (ID: <?php echo esc_html($author_id); ?>)
                </a>
              </small>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Data do Feedback</th>
          <td>
            <?php
            if ($feedback_date) {
              echo esc_html(date_i18n('d/m/Y H:i:s', strtotime($feedback_date)));
              echo ' <small style="color: #666;">(' . human_time_diff(strtotime($feedback_date), current_time('timestamp')) . ' atrás)</small>';
            } else {
              echo get_the_date('d/m/Y H:i:s', $post_id);
            }
            ?>
          </td>
        </tr>
        <tr>
          <th>IP do Usuário</th>
          <td><?php echo $feedback_ip ? esc_html($feedback_ip) : '<span class="meta-empty">Não registrado</span>'; ?></td>
        </tr>
      </table>
    </div>

    <!-- Seção: Avaliação -->
    <div class="bazar-feedback-meta-fields-section">
      <h3>⭐ Avaliação</h3>
      <table>
        <tr>
          <th>Nota</th>
          <td><?php echo $estrelas_html; ?></td>
        </tr>
        <tr>
          <th>Recomendaria o Bazar Bikes?</th>
          <td>
            <?php
            if ($recomendaria === 'sim') {
              echo '<span style="color: #46b450; font-weight: bold;">✅ ' . esc_html($recomendaria_label) . '</span>';
            } elseif ($recomendaria === 'nao') {
              echo '<span style="color: #dc3545; font-weight: bold;">❌ ' . esc_html($recomendaria_label) . '</span>';
            } else {
              echo esc_html($recomendaria_label);
            }
            ?>
          </td>
        </tr>
      </table>
    </div>

    <!-- Seção: Comentários -->
    <div class="bazar-feedback-meta-fields-section">
      <h3>💬 Comentários</h3>
      <table>
        <tr>
          <th>O que mais gostou</th>
          <td>
            <?php if ($o_que_gostou): ?>
              <div class="text-content"><?php echo nl2br(esc_html($o_que_gostou)); ?></div>
            <?php else: ?>
              <span class="meta-empty">Não informado</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>O que pode ser melhorado</th>
          <td>
            <?php if ($o_que_melhorar): ?>
              <div class="text-content"><?php echo nl2br(esc_html($o_que_melhorar)); ?></div>
            <?php else: ?>
              <span class="meta-empty">Não informado</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Comentários adicionais</th>
          <td>
            <?php if ($comentarios): ?>
              <div class="text-content"><?php echo nl2br(esc_html($comentarios)); ?></div>
            <?php else: ?>
              <span class="meta-empty">Não informado</span>
            <?php endif; ?>
          </td>
        </tr>
      </table>
    </div>

    <!-- Seção: Todos os meta fields (exibição completa) -->
    <div class="bazar-feedback-meta-fields-section">
      <h3>📦 Todos os meta fields</h3>
      <table>
        <?php
        $all_meta = get_post_meta($post_id);
        $labels = bazar_feedback_meta_keys();
        if (!empty($all_meta)) {
          ksort($all_meta);
          foreach ($all_meta as $meta_key => $meta_values) {
            $value = is_array($meta_values) && isset($meta_values[0]) ? $meta_values[0] : '';
            $label = isset($labels[$meta_key]) ? $labels[$meta_key] : '';
            ?>
            <tr>
              <th>
                <code><?php echo esc_html($meta_key); ?></code>
                <?php if ($label): ?>
                  <br><small style="font-weight: normal; color: #666;"><?php echo esc_html($label); ?></small>
                <?php endif; ?>
              </th>
              <td>
                <?php if ((string) $value !== ''): ?>
                  <div class="text-content"><?php echo nl2br(esc_html($value)); ?></div>
                <?php else: ?>
                  <span class="meta-empty">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php
          }
        } else {
          echo '<tr><td colspan="2"><span class="meta-empty">Nenhum meta field registrado.</span></td></tr>';
        }
        ?>
      </table>
    </div>

    <!-- Informações adicionais -->
    <div class="bazar-feedback-meta-fields-section">
      <p><strong>ℹ️ Informação:</strong> Estes dados foram coletados através do formulário de feedback enviado ao usuário
        após a venda do anúncio. Os campos são somente leitura.</p>
    </div>
  </div>
  <?php
}

