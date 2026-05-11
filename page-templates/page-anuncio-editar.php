<?php
/* Template Name: Editar Bicicletas*/
get_template_part('template-parts/global/validacao');

$post_id = (isset($_GET['post_id'])) ? wp_strip_all_tags($_GET['post_id']) : null;
$check_user = current_user_can('edit_post', $post_id);
if (!$post_id || !$check_user):
  wp_redirect(esc_url(get_bloginfo('url') . '\/meus-anuncios\/'));
  exit;
endif;

if (have_posts()):
  while (have_posts()):
    the_post();

    // Instancia a classe de upload para obter os valores de configuração
    $attachment_upload = new __Bazar_Attachment_Upload();
    if ($attachment_upload && !is_wp_error($attachment_upload)):
      $min_images = $attachment_upload->get_min_images_count();
      $max_images = $attachment_upload->get_max_images_count();
      $min_width = $attachment_upload->get_min_image_width();
      $max_width = $attachment_upload->get_max_image_width();
      $min_height = $attachment_upload->get_min_image_height();
      $max_height = $attachment_upload->get_max_image_height();
      $max_size_label = $attachment_upload->get_max_size_label();
      $allowed_formats = $attachment_upload->get_allowed_image_types_string();
      $allowed_formats_accept = $attachment_upload->get_allowed_image_types_for_accept();
    endif;

    // Obter status globalizado (considera indeferimento)
    // Sempre deve retornar isVendido = false - anúncios vendidos não podem ser editados
    $status_data = (isset($product_data['status_data']) && !empty($product_data['status_data']))
      ? $product_data['status_data']
      : bazar_get_anuncio_status($post_id);

    $post_status = $status_data['status'];
    $motivos_indeferimento = $status_data['motivos_indeferimento'];

    // Busca TODOS os componentes (pais e filhos) uma única vez para reutilizar
    // Usa função centralizada com cache
    $componentes_array = bazar_get_all_components();
    $parent_terms = (!is_wp_error($componentes_array) && !empty($componentes_array))
      ? bazar_get_componentes_parents()
      : array();

    // Obter categoria do post
    $post_categories = get_the_terms($post_id, 'category');
    $current_category = null;
    if ($post_categories && !is_wp_error($post_categories) && !empty($post_categories)) {
      // Pegar a primeira categoria (pai se houver)
      foreach ($post_categories as $cat) {
        if ($cat->parent == 0) {
          $current_category = $cat;
          break;
        }
      }
      // Se não encontrou pai, pegar a primeira
      if (!$current_category) {
        $current_category = $post_categories[0];
      }
    }
    // var_dump( $current_category );

    $creation_date = get_the_date('d/m/Y H:i', $post_id);
    $last_modified_date = get_the_modified_date('d/m/Y H:i', $post_id);

    $marcas_modelos = get_the_terms($post_id, 'marca-modelo');
    $cor = get_the_terms($post_id, 'cor');
    $material = get_the_terms($post_id, 'material');
    $negociacao = get_the_terms($post_id, 'negociacao');
    $conservacao = get_the_terms($post_id, 'conservacao');
    $genero = get_the_terms($post_id, 'genero');
    $idade = get_the_terms($post_id, 'idade');

    $post_components = get_the_terms($post_id, 'componente');
    $data_components = array();
    if ($post_components && !is_wp_error($post_components) && !empty($post_components)) {
      foreach ($post_components as $component) {
        $data_components['componente'][] = array(
          'componente_id' => $component->term_id,
          'parent_id' => $component->parent,
          'name' => $component->name,
          'slug' => $component->slug,
        );
      }
      if (isset($post_components['quadro'])) {
        $data_components['quadro'] = array(
          'componente_id' => $post_components['quadro']->term_id,
          'parent_id' => $post_components['quadro']->parent,
          'name' => $post_components['quadro']->name,
          'slug' => $post_components['quadro']->slug,
        );
      }
    }

    // Processar medidas: separar pais e filhos e agrupar
    $post_medidas = get_the_terms($post_id, 'medidas');
    $medidas_processed = array();
    if ($post_medidas && !is_wp_error($post_medidas) && !empty($post_medidas)) {
      $medidas_pais = array();
      $medidas_filhos = array();

      foreach ($post_medidas as $medida) {
        if ($medida->parent == 0) {
          $medidas_pais[] = $medida;
        } else {
          $medidas_filhos[] = $medida;
        }
      }

      // Agrupar filhos por pai
      foreach ($medidas_pais as $pai) {
        $filhos_do_pai = array();
        foreach ($medidas_filhos as $filho) {
          if ($filho->parent == $pai->term_id) {
            $filhos_do_pai[] = $filho;
          }
        }
        $medidas_processed[] = array(
          'pai' => $pai,
          'filhos' => $filhos_do_pai
        );
      }
    }

    // Processar acessórios: identificar pai e filho
    $post_acessorios = get_the_terms($post_id, 'acessorio');
    $acessorio_pai_id = null;
    $acessorio_filho_id = null;
    if ($post_acessorios && !is_wp_error($post_acessorios) && !empty($post_acessorios)) {
      foreach ($post_acessorios as $acessorio) {
        if ($acessorio->parent == 0) {
          $acessorio_pai_id = $acessorio->term_id;
        } else {
          $acessorio_filho_id = $acessorio->term_id;
        }
      }
    }

    // Processar componentes para peças: identificar pai e filho
    $componente_peca_pai_id = null;
    $componente_peca_filho_id = null;
    if ($post_components && !is_wp_error($post_components) && !empty($post_components)) {
      foreach ($post_components as $component) {
        if ($component->parent == 0) {
          $componente_peca_pai_id = $component->term_id;
        } else {
          $componente_peca_filho_id = $component->term_id;
        }
      }
    }

    $content_post = get_post($post_id);
    $content = $content_post->post_content;

    $attachments = get_posts(array(
      'post_type' => 'attachment',
      'posts_per_page' => -1,
      'post_parent' => $post_id,
      'order' => 'ASC',
      'orderby' => 'menu_order',
    ));
    $files = array();
    foreach ($attachments as $attachment):
      $files[] = array(
        'id' => $attachment->ID,
        'url' => wp_get_attachment_image_src($attachment->ID, 'medium')[0],
        'order' => $attachment->menu_order
      );
    endforeach;

    //DATA ARRAY - Valida valores de $_POST quando disponíveis, senão usa valores do post
    $marca_post = isset($_POST['marcas_modelos']) ? wp_strip_all_tags($_POST['marcas_modelos']) : '';
    $modelo_post = isset($_POST['marcas_modelos_child']) ? wp_strip_all_tags($_POST['marcas_modelos_child']) : '';

    $data = array(
      'permalink' => esc_url(get_the_permalink($post_id)),
      'marcas_modelos' => array(
        // Se há POST, usa valores validados do POST, senão usa valores do post
        !empty($marca_post) ? sanitize_text_field($marca_post) :
        (($marcas_modelos && !is_wp_error($marcas_modelos) && isset($marcas_modelos[0])) ?
          (($marcas_modelos[0]->parent == 0) ? sanitize_text_field($marcas_modelos[0]->name) : (isset($marcas_modelos[1]) ? sanitize_text_field($marcas_modelos[1]->name) : '')) : ''),
        !empty($modelo_post) ? sanitize_text_field($modelo_post) :
        (($marcas_modelos && !is_wp_error($marcas_modelos) && isset($marcas_modelos[1])) ?
          (($marcas_modelos[0]->parent == 0) ? sanitize_text_field($marcas_modelos[1]->name) : (isset($marcas_modelos[0]) ? sanitize_text_field($marcas_modelos[0]->name) : '')) : '')
      ),
      'negociacao' => isset($_POST['negociacao']) && is_array($_POST['negociacao'])
        ? array_map('sanitize_text_field', $_POST['negociacao'])
        : (($negociacao) ? wp_list_pluck($negociacao, 'slug') : array()),
      'cor' => isset($_POST['cor']) ? sanitize_text_field($_POST['cor']) :
        (($cor && !is_wp_error($cor) && isset($cor[0])) ? sanitize_text_field($cor[0]->name) : ''),
      'material' => isset($_POST['material']) ? sanitize_text_field($_POST['material']) :
        (($material && !is_wp_error($material) && isset($material[0])) ? sanitize_text_field($material[0]->name) : ''),
      'conservacao' => isset($_POST['conservacao']) ? sanitize_text_field($_POST['conservacao']) :
        (($conservacao && !is_wp_error($conservacao) && isset($conservacao[0])) ? sanitize_text_field($conservacao[0]->name) : ''),
      'genero' => isset($_POST['genero']) ? sanitize_text_field($_POST['genero']) :
        (($genero && !is_wp_error($genero) && isset($genero[0])) ? sanitize_text_field($genero[0]->name) : ''),
      'idade' => isset($_POST['idade']) ? sanitize_text_field($_POST['idade']) :
        (($idade && !is_wp_error($idade) && isset($idade[0])) ? sanitize_text_field($idade[0]->name) : ''),
      'valor' => isset($_POST['valor']) ? sanitize_text_field($_POST['valor']) : get_field('valor', $post_id),
      'peso' => isset($_POST['peso']) ? sanitize_text_field($_POST['peso']) : get_field('peso', $post_id),
      'ano' => isset($_POST['ano']) ? absint($_POST['ano']) : get_field('ano', $post_id),
      'nota_fiscal' => isset($_POST['nota_fiscal']) ? sanitize_text_field($_POST['nota_fiscal']) : get_field('nota_fiscal', $post_id),
      'exibir_contato' => isset($_POST['exibir_contato']) ? sanitize_text_field($_POST['exibir_contato']) : get_field('exibir_contato', $post_id),
      'descricao' => isset($_POST['txt-descricao']) ? wp_kses_post($_POST['txt-descricao']) : wp_kses_post($content),
      'post_thumbnail' => isset($_POST['post_thumbnail']) ? absint($_POST['post_thumbnail']) : get_post_thumbnail_id($post_id),
      'files' => $files,
      'componente' => $post_components,
      'medidas' => $post_medidas,
      'acessorio' => $post_acessorios,
      'medidas_processed' => $medidas_processed,
      'acessorio_pai_id' => $acessorio_pai_id,
      'acessorio_filho_id' => $acessorio_filho_id,
      'componente_peca_pai_id' => $componente_peca_pai_id,
      'componente_peca_filho_id' => $componente_peca_filho_id,
    );

    $msg_medidas = __('<b>Medidas são opcionais</b>, mas lembre-se, quanto melhor descrever seu anúncio, maiores as chances de venda.', 'bazar');

    get_header();
    ?>

    <h1 class="d-none">
      <?php bloginfo('name'); ?> - <?php the_title(); ?>
    </h1>

    <?php large_content(); ?>

    <div class="row align-center align-middle sticky form-header">
      <div class="s-6 m-8 col">
        <h2 class="mb-0">
          <?php the_title(); ?>
        </h2>
      </div>
      <div class="s-6 m-4 col text-right">
        <button type="button" title="<?php _e('Meus anúncios	', 'bazar'); ?>" class="button clear"
          onclick="window.location.href='<?php echo get_bloginfo('url') . '/meus-anuncios/'; ?>'">
          <i class="fa fa-th"></i>
        </button>        
        <?php
        $preview_url = get_the_permalink($post_id);
        if ($post_status !== 'publish') {
          // Se já tem query string, adiciona com &, senão com ?
          $preview_url .= (strpos($preview_url, '?') === false ? '?' : '&') . 'preview=true';
        }
        ?>
        <button type="button" title="<?php _e('Previsualizar', 'bazar'); ?>" class="button clear"
          onclick="window.open('<?php echo esc_html($preview_url); ?>', '_blank');">
          <i class="fa fa-eye"></i>
        </button>
        <button type="submit" title="<?php _e('Salvar Alterações', 'bazar'); ?>" form="form-anuncio-editar" class="button send-form">
          <i class="fa fa-save"></i>
        </button>
      </div>
    </div><!-- sticky -->

    <div class="row align-center align-middle">

      <div class="s-12 m-4 col mb-1">
        <label class="silver h6" style="margin-bottom: .25rem;">Status:</label>
        <?php echo bazar_get_anuncio_status_icon($post_status); ?>
        <small>
          <?php echo bazar_get_anuncio_status_label($post_status); ?>
        </small>
      </div>

      <div class="s-6 m-4 col mb-1">
        <label class="silver h6" style="margin-bottom: .25rem;">Criação:</label>
        <i class="fa fa-calendar-check"></i>
        <small>
          <?php echo esc_html($creation_date); ?>
        </small>
      </div>

      <div class="s-6 m-4 col mb-1">
        <?php if ($last_modified_date && $last_modified_date !== $creation_date): ?>
          <label class="silver h6" style="margin-bottom: .25rem;">Alteração:</label>
          <i class="fa fa-calendar-plus"></i>
          <small>
            <?php echo esc_html($last_modified_date); ?>
          </small>
        <?php endif; ?>
      </div>
    </div>

    <?php get_template_part('template-parts/cta/msg-box'); ?>

    <div class="form-box anuncio-forms">

      <?php
      // Exibir mensagem de indeferimento se houver
      if (!empty($motivos_indeferimento)):
        $em_reavaliacao = get_field('reavaliacao', $post_id);
        ?>
        <div class="alert alert-info">
          <p class="mb-0">

            <strong>Motivos do indeferimento:</strong><br>
            <?php echo wp_kses_post(nl2br(esc_html($motivos_indeferimento))); ?>

            <?php if ($em_reavaliacao): ?>
              <small class="d-block" style="color: #28a745;">
                <i class="fa fa-check-circle"></i>
                <strong>Está indeferido, porém os novos ajustes foram enviados para revisão.</strong>
              </small>
            <?php else: ?>
              <small class="d-block">
                <i class="fa fa-info-circle"></i>
                Por favor, revise os motivos acima, faça as correções necessárias e salve o anúncio para reenviar para
                aprovação.
              </small>
            <?php endif; ?>

          </p>
        </div>
      <?php endif; ?>

      <div class="content">
        <?php the_content(); ?>
      </div>

      <form method="post" id="form-anuncio-editar" class="send-form" name="edit_post" action="<?php the_permalink(); ?>"
        enctype="multipart/form-data">

        <div id="alert">
          <?php
          if (isset($_SESSION['sucess']) && $_SESSION['sucess'] == true) {
            echo '<div class="msg_box msg-sucess bold clear">Cadastro atualizado com sucesso.</div>';
            $_SESSION['sucess'] = '';
          }
          ;
          ?>
        </div>

        <?php
        // var_dump($current_category->slug);
        // Sistema de Tabs para categorias
        $categs = get_terms(array(
          'taxonomy' => 'category',
          'hide_empty' => false,
          'parent' => 0,
          'hierarchical' => 1
        ));
        if ($categs && !is_wp_error($categs)):
          $categs = __Bazar_Terms_Manager::ordenar($categs, 'category');
          ?>
          <div id="tab-edit" class="tabs-menu">
            <?php
            foreach ($categs as $key => $categ):
              $is_active = ($current_category && $current_category->term_id == $categ->term_id);
              $disabled_attr = (!$is_active) ? 'disabled' : '';
              $disabled_class = (!$is_active) ? 'tab-disabled' : '';
              $disabled_data = (!$is_active) ? 'data-disabled="true"' : '';
              $title_tooltip = (!$is_active) ? 'title="Não é possível alterar a categoria de um anúncio de bicicleta. Para cadastrar uma peça ou acessório, crie um novo anúncio."' : '';
              ?>
              <button type="button"
                class="tab-btn <?php echo ($is_active || (!$current_category && $key == 0)) ? 'active' : ''; ?> <?php echo $disabled_class; ?>"
                data-tab="edit-<?php echo $categ->slug; ?>" data-categoria="<?php echo $categ->slug; ?>"
                data-category-id="<?php echo $categ->term_id; ?>" <?php echo $disabled_attr; ?>         <?php echo $disabled_data; ?>
                <?php echo $title_tooltip; ?>>
                <div class="bx">
                  <span class="tab-icon">
                    <?php echo __Bazar_Terms_Manager::get_term_icon($categ); ?>
                  </span>
                  <h3><?php echo $categ->name; ?></h3>
                  <?php if (!$is_active): ?>
                    <small class="tab-locked-msg" style="display: block; font-size: 0.7em; color: #999; margin-top: 4px;">
                      <i class="fa fa-lock"></i> Bloqueado
                    </small>
                  <?php endif; ?>
                </div>
              </button>
            <?php endforeach; ?>

            <input type="hidden" name="category"
              value="<?php echo $current_category ? $current_category->slug : ($categs[0] ? $categs[0]->slug : ''); ?>" />

            <input type="hidden" name="category_id"
              value="<?php echo $current_category ? $current_category->term_id : ($categs[0] ? $categs[0]->term_id : ''); ?>" />

            <input type="hidden" name="category_fields"
              value="<?php echo 'edit-' . ($current_category ? $current_category->slug : ($categs[0] ? $categs[0]->slug : '')); ?>" />

            <input type="hidden" name="title" value="" />

          </div><!-- /tabs-menu -->

          <?php
          // Tab Content para Peças
          if ($parent_terms && !is_wp_error($parent_terms)):
            $show_peca = ($current_category && $current_category->slug == 'peca');
            ?>
            <div id="edit-peca" class="tab-content" style="display: <?php echo $show_peca ? 'block' : 'none'; ?>;">
              <?php if ($show_peca): ?>
                <div class="row">
                  <div class="s-12 col relative pb-1">

                    <small><?php echo $msg_medidas; ?></small>

                  </div>
                  <div class="s-12 m-6 col">

                    <label class="blue">Componente:</label>
                    <select id="componente_pecas" name="componente_pecas" data-categ="componente" data-label="Componente"
                      data-child="componente_pecas_child" data-allItens="true" class="list-sub-item">
                      <option value="">Selecione</option>
                      <?php
                      $current_componente_peca = (isset($_POST['componente_pecas']))
                        ? intval($_POST['componente_pecas'])
                        : $data['componente_peca_pai_id'];

                      foreach ($parent_terms as $key => $componente): ?>
                        <option value="<?php echo $componente->term_id; ?>" <?php selected($componente->term_id, $current_componente_peca, true); ?>>
                          <?php echo $componente->name; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>

                  </div>
                  <div class="s-12 m-6 col">

                    <label class="blue">Especificação:</label>
                    <select id="componente_pecas_child" name="componente_pecas_child" data-label="Especificação" data-current="<?php
                    $current_child = (isset($_POST['componente_pecas_child']))
                      ? $_POST['componente_pecas_child']
                      : $data['componente_peca_filho_id'];
                    echo $current_child ? esc_attr($current_child) : '';
                    ?>">
                      <option value="">Selecione</option>
                    </select>

                  </div>

                  <?php
                  $args_medidas = array(
                    'taxonomy' => 'medidas',
                    'hide_empty' => false,
                    'parent' => 0,
                    'hierarchical' => 1
                  );
                  $medidas = get_terms($args_medidas);
                  if ($medidas && !is_wp_error($medidas)):
                    $medidas = __Bazar_Terms_Manager::ordenar($medidas, 'medidas');
                    ?>
                    <div class="s-12 m-6 col">
                      <label>Medida:</label>
                      <select name="medidas[0]" id="medidas_0" class="list-sub-item not_required" data-categ="medidas"
                        data-label="Medida" data-child="medidas_child_0" data-allitens="true">
                        <option value="">Selecione</option>
                        <?php
                        $current_medida_pai_0 = null;
                        $current_medida_filho_0 = null;
                        if (!empty($data['medidas_processed']) && isset($data['medidas_processed'][0])) {
                          $current_medida_pai_0 = $data['medidas_processed'][0]['pai']->term_id;
                          if (!empty($data['medidas_processed'][0]['filhos']) && isset($data['medidas_processed'][0]['filhos'][0])) {
                            $current_medida_filho_0 = $data['medidas_processed'][0]['filhos'][0]->term_id;
                          }
                        }
                        $selected_medida_0 = isset($_POST['medidas'][0]) ? intval($_POST['medidas'][0]) : $current_medida_pai_0;
                        foreach ($medidas as $medida): ?>
                          <option value="<?php echo $medida->term_id; ?>" <?php selected($medida->term_id, $selected_medida_0, true); ?>>
                            <?php echo $medida->name; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="s-12 m-6 col">
                      <label>Especificação:</label>
                      <select id="medidas_child_0" name="medidas_child[0]" class="not_required" data-label="Especificação"
                        data-current="<?php
                        $selected_medida_child_0 = isset($_POST['medidas_child'][0]) ? $_POST['medidas_child'][0] : $current_medida_filho_0;
                        echo $selected_medida_child_0 ? esc_attr($selected_medida_child_0) : '';
                        ?>">
                        <option value="">Selecione</option>
                      </select>
                    </div>

                    <div class="col s-12 medidas-dynamic-container">
                      <?php
                      // Renderizar medidas adicionais (a partir do índice 1)
                      if (!empty($data['medidas_processed']) && count($data['medidas_processed']) > 1):
                        for ($i = 1; $i < count($data['medidas_processed']); $i++):
                          $medida_item = $data['medidas_processed'][$i];
                          $medida_pai = $medida_item['pai'];
                          $medida_filho = !empty($medida_item['filhos']) && isset($medida_item['filhos'][0]) ? $medida_item['filhos'][0] : null;
                          ?>
                          <div class="row medidas-item-row">
                            <div class="s-12 m-6 col">
                              <label>Medida:</label>
                              <select name="medidas[<?php echo $i; ?>]" id="medidas_<?php echo $i; ?>"
                                class="list-sub-item not_required" data-categ="medidas" data-label="Medida"
                                data-child="medidas_child_<?php echo $i; ?>" data-allitens="true">
                                <option value="">Selecione</option>
                                <?php foreach ($medidas as $medida): ?>
                                  <option value="<?php echo $medida->term_id; ?>" <?php selected($medida->term_id, $medida_pai->term_id, true); ?>>
                                    <?php echo $medida->name; ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                            <div class="s-12 m-6 col">
                              <label>Especificação:</label>
                              <select id="medidas_child_<?php echo $i; ?>" name="medidas_child[<?php echo $i; ?>]" class="not_required"
                                data-label="Especificação"
                                data-current="<?php echo $medida_filho ? esc_attr($medida_filho->term_id) : ''; ?>">
                                <option value="">Selecione</option>
                              </select>
                            </div>
                            <button type="button" class="button btn-remover-medida" title="Remover medida">
                              <i class="fa fa-times"></i>
                            </button>
                          </div>
                          <?php
                        endfor;
                      endif;
                      ?>
                    </div>

                    <div class="col s-12 pb-1">
                      <button type="button" id="btn-nova-medida" class="button small dark"
                        title="<?php _e('Adicionar nova medida', 'bazar'); ?>">
                        <i class="fa fa-plus"></i>
                        <?php _e('Adicionar Nova Medida', 'bazar'); ?>
                      </button>
                    </div>
                    <?php
                    // end medidas
                  endif; ?>

                </div><!-- /row -->
              <?php endif; ?>
            </div><!-- /tab-content -->
          <?php endif; ?>


          <?php
          // Tab Content para Acessórios
          $args_acessorios = array(
            'taxonomy' => 'acessorio',
            'hide_empty' => false,
            'parent' => 0,
            'hierarchical' => 1
          );
          $acessorios_ = get_terms($args_acessorios);
          if ($acessorios_ && !is_wp_error($acessorios_)):
            $parent_acessorios = __Bazar_Terms_Manager::ordenarAlfabeticamente($acessorios_);
            $show_acessorio = ($current_category && $current_category->slug == 'acessorio');
            ?>
            <div id="edit-acessorio" class="tab-content" style="display: <?php echo $show_acessorio ? 'block' : 'none'; ?>;">
              <?php if ($show_acessorio): ?>
                <div class="row">
                  <div class="s-12 col relative pb-1">
                    <small><?php echo $msg_medidas; ?></small>
                  </div>
                  <div class="s-12 m-6 col">
                    <label class="blue">Acessório:</label>
                    <select id="acessorio" name="acessorio" data-categ="acessorio" data-label="Acessório"
                      data-child="acessorio_child" data-allItens="true" class="list-sub-item">
                      <option value="">Selecione</option>
                      <?php
                      $current_acessorio = isset($_POST['acessorio']) ? intval($_POST['acessorio']) : $data['acessorio_pai_id'];
                      foreach ($parent_acessorios as $acessorio):
                        ?>
                        <option value="<?php echo $acessorio->term_id; ?>" <?php selected($acessorio->term_id, $current_acessorio, true); ?>>
                          <?php echo $acessorio->name; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="s-12 m-6 col">
                    <label class="blue">Especificação:</label>
                    <select id="acessorio_child" name="acessorio_child" data-label="Especificação" data-current="<?php
                    $current_acessorio_child = isset($_POST['acessorio_child']) ? $_POST['acessorio_child'] : $data['acessorio_filho_id'];
                    echo $current_acessorio_child ? esc_attr($current_acessorio_child) : '';
                    ?>">
                      <option value="">Selecione</option>
                    </select>
                  </div>

                  <?php
                  $args_medidas = array(
                    'taxonomy' => 'medidas',
                    'hide_empty' => false,
                    'parent' => 0,
                    'hierarchical' => 1
                  );
                  $medidas = get_terms($args_medidas);
                  if ($medidas && !is_wp_error($medidas)):
                    $medidas = __Bazar_Terms_Manager::ordenar($medidas, 'medidas');
                    ?>
                    <div class="s-12 m-6 col medidas-item" data-index="0">
                      <label>Medida:</label>
                      <select name="medidas_acessorio[0]" id="medidas_0_acessorio" class="list-sub-item not_required"
                        data-categ="medidas" data-label="Medida" data-child="medidas_child_0_acessorio" data-allitens="true">
                        <option value="">Selecione</option>
                        <?php
                        $current_medida_acessorio_pai_0 = null;
                        $current_medida_acessorio_filho_0 = null;
                        if (!empty($data['medidas_processed']) && isset($data['medidas_processed'][0])) {
                          $current_medida_acessorio_pai_0 = $data['medidas_processed'][0]['pai']->term_id;
                          if (!empty($data['medidas_processed'][0]['filhos']) && isset($data['medidas_processed'][0]['filhos'][0])) {
                            $current_medida_acessorio_filho_0 = $data['medidas_processed'][0]['filhos'][0]->term_id;
                          }
                        }
                        $selected_medida_acessorio_0 = isset($_POST['medidas_acessorio'][0]) ? intval($_POST['medidas_acessorio'][0]) : $current_medida_acessorio_pai_0;
                        foreach ($medidas as $medida): ?>
                          <option value="<?php echo $medida->term_id; ?>" <?php selected($medida->term_id, $selected_medida_acessorio_0, true); ?>>
                            <?php echo $medida->name; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="s-12 m-6 col">
                      <label>Especificação:</label>
                      <select id="medidas_child_0_acessorio" name="medidas_acessorio_child[0]" class="not_required"
                        data-label="Especificação" data-current="<?php
                        $selected_medida_acessorio_child_0 = isset($_POST['medidas_acessorio_child'][0]) ? $_POST['medidas_acessorio_child'][0] : $current_medida_acessorio_filho_0;
                        echo $selected_medida_acessorio_child_0 ? esc_attr($selected_medida_acessorio_child_0) : '';
                        ?>">
                        <option value="">Selecione</option>
                      </select>
                    </div>

                    <div class="col s-12 medidas-dynamic-container">
                      <?php
                      // Renderizar medidas adicionais para acessórios (a partir do índice 1)
                      if (!empty($data['medidas_processed']) && count($data['medidas_processed']) > 1):
                        for ($i = 1; $i < count($data['medidas_processed']); $i++):
                          $medida_item = $data['medidas_processed'][$i];
                          $medida_pai = $medida_item['pai'];
                          $medida_filho = !empty($medida_item['filhos']) && isset($medida_item['filhos'][0]) ? $medida_item['filhos'][0] : null;
                          ?>
                          <div class="row medidas-item-row">
                            <div class="s-12 m-6 col">
                              <label>Medida:</label>
                              <select name="medidas_acessorio[<?php echo $i; ?>]" id="medidas_<?php echo $i; ?>_acessorio"
                                class="list-sub-item not_required" data-categ="medidas" data-label="Medida"
                                data-child="medidas_child_<?php echo $i; ?>_acessorio" data-allitens="true">
                                <option value="">Selecione</option>
                                <?php foreach ($medidas as $medida): ?>
                                  <option value="<?php echo $medida->term_id; ?>" <?php selected($medida->term_id, $medida_pai->term_id, true); ?>>
                                    <?php echo $medida->name; ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                            <div class="s-12 m-6 col">
                              <label>Especificação:</label>
                              <select id="medidas_child_<?php echo $i; ?>_acessorio" name="medidas_acessorio_child[<?php echo $i; ?>]"
                                class="not_required" data-label="Especificação"
                                data-current="<?php echo $medida_filho ? esc_attr($medida_filho->term_id) : ''; ?>">
                                <option value="">Selecione</option>
                              </select>
                            </div>
                            <button type="button" class="button btn-remover-medida" title="Remover medida">
                              <i class="fa fa-times"></i>
                            </button>
                          </div>
                          <?php
                        endfor;
                      endif;
                      ?>
                    </div>

                    <div class="col s-12 pb-1">
                      <button type="button" id="btn-nova-medida-acessorio" class="button small dark"
                        title="<?php _e('Adicionar nova medida', 'bazar'); ?>">
                        <i class="fa fa-plus"></i>
                        <?php _e('Adicionar Nova Medida', 'bazar'); ?>
                      </button>
                    </div>
                    <?php
                    // end medidas
                  endif; ?>

                </div><!-- /row -->
              <?php endif; ?>
            </div><!-- /tab-content -->
          <?php endif; ?>


          <?php
          if ($parent_terms && !is_wp_error($parent_terms)):
            $show_bicicleta = ($current_category && $current_category->slug == 'bicicleta');
            ?>
            <div id="edit-bicicleta" class="tab-content" style="display: <?php echo $show_bicicleta ? 'block' : 'none'; ?>;">
              <?php if ($show_bicicleta): ?>
                <div class="row form_componentes pb-1">
                  <div class="s-12 col relative pb-1">

                    <small class="mb-0">
                      - Os componentes <b class="blue">obrigatórios</b>.
                      <br>
                      - <b>Marca e Modelo</b> são opcionais, mas lembre-se, quanto melhor descrever seu anúncio, maiores as
                      chances de venda.
                    </small>

                  </div>

                  <?php
                  foreach ($parent_terms as $key => $item):

                    $is_default = __Bazar_Component_Helper::isDefaultBicicletas($item);
                    $icon = __Bazar_Terms_Manager::get_term_icon($item);
                    $child_terms = __Bazar_Terms_Manager::findChildTermByParentId(
                      $item->term_id,
                      $componentes_array
                    );

                    // Obtém os termos do componente atual para edição
                    $componente_term_ids = __Bazar_Component_Helper::get_componente_terms(
                      $post_id,
                      $item->term_id,
                      'componente'
                    );

                    // Obtém marca/modelo do componente para edição
                    $componenteMarcaModelo = __Bazar_Component_Helper::get_marca_modelo(
                      $post_id,
                      $item->term_id
                    );
                    $currentComponenteMarca = (
                      isset($componenteMarcaModelo['marca'])
                      && !empty($componenteMarcaModelo['marca'])
                    )
                      ? $componenteMarcaModelo['marca']
                      : null;

                    $currentComponenteModelo = (
                      isset($componenteMarcaModelo['modelo'])
                      && !empty($componenteMarcaModelo['modelo'])
                    )
                      ? $componenteMarcaModelo['modelo']
                      : null;
                    $optional_has_value = !$is_default && (
                      !empty($componente_term_ids) || $currentComponenteMarca || $currentComponenteModelo
                    );
                    ?>
                    <div
                      class="col s-12 m-4 l-3 componente<?php echo (!$is_default) ? ' componente-optional' : ''; ?><?php echo (!empty($optional_has_value)) ? ' is-expanded' : ''; ?>"
                      data-key="<?php echo $item->term_id; ?>" data-component-name="<?php echo esc_attr($item->name); ?>">

                      <div class="componente-wrap">

                        <div class="componente-header">

                          <?php echo $icon; ?>

                          <label for="componente_<?php echo $item->term_id; ?>" class="h4 <?php echo ($is_default) ? 'blue' : '' ?>">
                            <?php echo $item->name; ?>

                            <?php
                            if ($item->slug == 'quadro'):
                              get_template_part('template-parts/btn/help-quadros');
                            endif;
                            // Botão de ajuda para Trocadores
                            if ($item->slug == 'trocador' || $item->slug == 'passador/trocador'):
                              get_template_part('template-parts/btn/help-trocadores');
                            endif;
                            // Botão de ajuda para Pneus
                            if ($item->slug == 'pneu'):
                              get_template_part('template-parts/btn/help-pneus');
                            endif;
                            ?>
                          </label>

                          <?php if (!$is_default && !empty($child_terms)): ?>
                            <button type="button" class="componente-toggle"
                              aria-expanded="<?php echo $optional_has_value ? 'true' : 'false'; ?>"
                              aria-label="<?php echo esc_attr($optional_has_value ? sprintf(__('Ocultar %s', 'bazar'), $item->name) : sprintf(__('Adicionar %s', 'bazar'), $item->name)); ?>">
                              <i class="fa fa-plus" aria-hidden="true"></i>
                            </button>
                          <?php endif; ?>

                        </div><!-- /componente-header -->

                        <?php if (!empty($child_terms)): ?>
                          <div class="componente-fields">
                            <select data-label="<?php echo $item->name; ?>" id="componente_<?php echo $item->term_id; ?>"
                              name="componente[<?php echo $item->term_id; ?>]"
                              class="<?php echo (!$is_default) ? 'not_required' : ''; ?>">
                              <option value="">Especificação</option>
                              <?php foreach ($child_terms as $child): ?>
                                <option value="<?php echo esc_attr($child->term_id); ?>" <?php selected(in_array($child->term_id, $componente_term_ids), true); ?>>
                                  <?php echo esc_html($child->name); ?>
                                </option>
                              <?php endforeach; ?>
                            </select>

                            <select id="marca_<?php echo esc_attr($item->term_id); ?>"
                              name="c_marca[<?php echo esc_attr($item->term_id); ?>]"
                              data-child="modelo_<?php echo esc_attr($item->term_id); ?>" data-label="Marca" class="not_required"
                              data-current="<?php echo esc_attr($currentComponenteMarca); ?>">
                              <option value="">Marca</option>
                            </select>

                            <select id="modelo_<?php echo esc_attr($item->term_id); ?>"
                              name="c_modelo[<?php echo esc_attr($item->term_id); ?>]" data-label="Modelo" class="not_required"
                              data-current="<?php echo esc_attr($currentComponenteModelo); ?>">
                              <option value="">Modelo</option>
                            </select>
                          </div>
                        <?php endif; ?>

                      </div><!-- /componente-wrap -->
                    </div><!-- /col -->
                  <?php endforeach; ?>

                </div><!-- /form_componentes -->
              <?php endif; ?>
            </div><!-- /tab-content -->
          <?php endif; ?>

        <?php endif; // Fecha if( $categs && !is_wp_error( $categs ) ) ?>

        <div class="row">

          <?php
          $args_modalidade = array(
            'taxonomy' => 'modalidade',
            'hierarchical' => 1,
            'hide_empty' => false
          );
          $modalidade_array = get_terms($args_modalidade);
          if ($modalidade_array && !is_wp_error($modalidade_array)):
            $modalidade_array = __Bazar_Terms_Manager::ordenarUpCountItem($modalidade_array);
            ?>
            <div class="s-12 m-4 col">
              <label>Modalidade:</label>
              <select id="modalidade" name="modalidade">
                <option value="">Selecione</option>
                <?php foreach ($modalidade_array as $modalidade): ?>
                  <option value="<?php echo $modalidade->slug; ?>" <?php
                     $modalidade_terms = get_the_terms($post_id, 'modalidade');
                     if ($modalidade_terms && !is_wp_error($modalidade_terms)) {
                       selected($modalidade->slug, $modalidade_terms[0]->slug);
                     }
                     ?>>
                    <?php echo $modalidade->name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="s-12 m-4 col">

            <label for="marcas_modelos">Marca:</label>
            <select id="marcas_modelos" name="marcas_modelos" data-child="marcas_modelos_child"
              data-current="<?php echo esc_attr($data['marcas_modelos'][0]); ?>" data-label="Marca">
              <option value="">Selecione</option>
            </select>

          </div><!-- /marca -->
          <div class="s-12 m-4 col">

            <label for="marcas_modelos_child">Modelo:</label>
            <select id="marcas_modelos_child" name="marcas_modelos_child"
              data-current="<?php echo esc_attr($data['marcas_modelos'][1]); ?>" data-label="Modelo" class="input-sufix">
              <option value="">Selecione</option>
            </select>
            <input type="hidden" id="marcas_modelos_child_add" name="marcas_modelos_child_add" value="false">
            <a id="new_input" href="#" class="input-box-sufix uper" title="Adicionar modelo"> + </a>

          </div><!-- /modelo -->

          <div class="s-12 col">
            <hr />
          </div>

          <?php
          $args_conservacao = array(
            'taxonomy' => 'conservacao',
            'hierarchical' => 1,
            'hide_empty' => false,
          );
          $conservacao_array = get_terms($args_conservacao);
          if ($conservacao_array && !is_wp_error($conservacao_array)):
            ?>
            <div class="s-12 m-4 col">
              <label>Conservação:</label>
              <select id="conservacao" name="conservacao">
                <option value="">Selecione</option>
                <?php foreach ($conservacao_array as $conservacao): ?>
                  <option value="<?php echo $conservacao->slug; ?>" <?php
                     $conservacao_terms = get_the_terms($post_id, 'conservacao');
                     if ($conservacao_terms && !is_wp_error($conservacao_terms)) {
                       selected($conservacao->slug, $conservacao_terms[0]->slug);
                     }
                     ?>>
                    <?php echo $conservacao->name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <?php
          $args_material = array(
            'taxonomy' => 'material',
            'hierarchical' => 1,
            'hide_empty' => 0,
          );
          $material_array = get_terms($args_material);
          if ($material_array && !is_wp_error($material_array)):
            $material_array = __Bazar_Terms_Manager::ordenarUpCountItem($material_array);
            ?>
            <div class="s-12 m-4 col">
              <label>Material:</label>
              <select id="material" name="material">
                <option value="">Selecione</option>
                <?php foreach ($material_array as $material): ?>
                  <option value="<?php echo $material->slug; ?>" <?php
                     $material_terms = get_the_terms($post_id, 'material');
                     if ($material_terms && !is_wp_error($material_terms)) {
                       selected($material->slug, $material_terms[0]->slug);
                     }
                     ?>>
                    <?php echo $material->name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="s-12 m-4 col">
            <label>Peso:</label>
            <input id="peso_mask" maxlength="6" name="peso" type="text" class="input-sufix" placeholder="Peso"
              value="<?php echo esc_attr(get_field('peso', $post_id)); ?>" />
            <span class="kg input-box-sufix">Kg</span>
          </div><!-- peso -->

          <div class="s-12 m-4 col">
            <label>Valor:</label>
            <input class="mask_money input-prefix" name="valor" type="text" placeholder="Valor"
              value="<?php echo esc_attr(number_format(get_field('valor', $post_id), 2, ',', '.')); ?>" />
            <span class="currency input-box-prefix">R$</span>
          </div><!-- valor -->

          <div class="s-12 m-4 col">
            <label>
              <span class="fas fa-calendar-alt"></span>
              Ano:
            </label>
            <select name="ano" id="ano">
              <option value="">Selecione</option>
              <?php for ($n = date("Y"); $n >= 1970; $n--): ?>
                <option value="<?php echo $n; ?>" <?php selected($n, get_field('ano', $post_id)); ?>><?php echo $n; ?>
                </option>
              <?php endfor; ?>
            </select>
          </div><!-- ano -->

          <?php
          $args_cores = array(
            'taxonomy' => 'cor',
            'hierarchical' => 1,
            'hide_empty' => false,
          );
          $cores_array = get_terms($args_cores);
          if ($cores_array && !is_wp_error($cores_array)):
            $cores_array = __Bazar_Terms_Manager::ordenarAlfabeticamente($cores_array);
            ?>
            <div class="s-12 m-4 col">
              <label>Cor predominante:</label>
              <select name="cor" id="cor">
                <option value="">Selecione</option>
                <?php foreach ($cores_array as $cor): ?>
                  <option value="<?php echo $cor->slug; ?>" <?php
                     $cor_terms = get_the_terms($post_id, 'cor');
                     if ($cor_terms && !is_wp_error($cor_terms)) {
                       selected($cor->slug, $cor_terms[0]->slug);
                     }
                     ?>>
                    <?php echo $cor->name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>


          <div class="s-12 col">
            <hr />
          </div>


          <?php
          $args_genero = array(
            'taxonomy' => 'genero',
            'hierarchical' => 1,
            'hide_empty' => false,
          );
          $genero_array = get_terms($args_genero);
          if ($genero_array && !is_wp_error($genero_array)):
            ?>
            <div class="s-12 m-6 col">
              <label>Gênero:</label>
              <select name="genero" id="genero">
                <option value="">Selecione</option>
                <?php foreach ($genero_array as $genero_): ?>
                  <option value="<?php echo $genero_->slug; ?>" <?php
                     $genero_terms = get_the_terms($post_id, 'genero');
                     if ($genero_terms && !is_wp_error($genero_terms)) {
                       selected($genero_->slug, $genero_terms[0]->slug);
                     }
                     ?>>
                    <?php echo $genero_->name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <?php
          $args_idade = array(
            'taxonomy' => 'idade',
            'hierarchical' => 1,
            'hide_empty' => false,
          );
          $idade_array = get_terms($args_idade);
          if ($idade_array && !is_wp_error($idade_array)):
            ?>
            <div class="s-12 m-6 col">
              <label>Grupo de Idade:</label>
              <select name="idade" id="idade">
                <option value="">Selecione</option>
                <?php
                $taxs = get_terms($args_idade);
                foreach ($taxs as $tax):
                  ?>
                  <option value="<?php echo $tax->slug; ?>" <?php
                     $idade_terms = get_the_terms($post_id, 'idade');
                     if ($idade_terms && !is_wp_error($idade_terms)) {
                       selected($tax->slug, $idade_terms[0]->slug);
                     }
                     ?>>
                    <?php echo $tax->name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>


          <div class="s-12 m-6 col">
            <label>Possui Nota Fiscal:</label>
            <select name="nota_fiscal" id="nota_fiscal">
              <option value="">Selecione</option>
              <option value="true" <?php selected(get_field('nota_fiscal', $post_id), true); ?>>Sim</option>
              <option value="false" <?php selected(get_field('nota_fiscal', $post_id), false); ?>>Não</option>
            </select>
          </div><!-- /nf -->


          <div class="s-12 m-6 col">
            <label>Exibir meu Telefone na busca:</label>
            <select name="exibir_contato" id="exibir_contato">
              <option value="">Selecione</option>
              <option value="true" <?php selected(get_field('exibir_contato', $post_id), true); ?>>Sim</option>
              <option value="false" <?php selected(get_field('exibir_contato', $post_id), false); ?>>Não</option>
            </select>
          </div>


          <div class="s-12 col">
            <hr />
          </div>

          <?php
          $args_negociacao = array(
            'taxonomy' => 'negociacao',
            'hierarchical' => 0,
            'parent' => 0,
            'hide_empty' => 0,
          );
          $negociacao_array = get_terms($args_negociacao);
          if ($negociacao_array && !is_wp_error($negociacao_array)):
            ?>
            <div class="s-12 m-3 col pb-3">
              <h4 class="mb-2">
                Negociação
              </h4>
              <?php foreach ($negociacao_array as $x => $categ5): ?>
                <div>
                  <label for="condicao<?php echo $x; ?>" class="bold xl">
                    <input type="checkbox" value="<?php echo $categ5->slug; ?>" id="condicao<?php echo $x; ?>"
                      name="negociacao[]" <?php
                      $negociacao_terms = get_the_terms($post_id, 'negociacao');
                      if ($negociacao_terms && !is_wp_error($negociacao_terms)) {
                        $negociacao_slugs = wp_list_pluck($negociacao_terms, 'slug');
                        checked(in_array($categ5->slug, $negociacao_slugs), true);
                      }
                      ?> />
                    <?php echo $categ5->name; ?>
                  </label>
                </div>
              <?php endforeach; ?>

            </div><!-- /negociacao -->
          <?php endif; ?>


          <div class="s-12 m-9 col">

            <h4 class="mb-2">
              <span>Descreva com detalhes seu anúncio.
                <div class="show-for-s-only"></br></div>
                <small>Restam (<span id="qts" class="silver text-right">600</span>) caracteres</small>
              </span>
            </h4>

            <textarea id="txt-descricao" name="txt-descricao" data-label="Descrição" rows="8"
              maxlength="600"><?php echo esc_textarea($data['descricao']); ?></textarea>

          </div><!-- /desc -->

          <div class="s-12 col">
            <hr />
          </div>

          <div class="s-12 col">

            <h4 class="mb-1">
              <span class="fas fa-camera"></span>
              <span>Imagens</span> <small>(<span id="count_imgs"><?php echo count($data['files']); ?></span> de 10
                imagens)</small>
            </h4>
            <small class="mb-1 d-block">Segure e arraste as imagens para alterar a ordem com que serão exibidas em seu
              anúncio.</small>

            <ul id="gallery" class="edit-galeria drag-drop-theme">
              <?php foreach ($data['files'] as $file): ?>
                <li id="<?php echo $file['id']; ?>" style="background-image:url(<?php echo esc_url($file['url']); ?>)">
                  <span class="fas fa-times red remove remove_image" data-id="<?php echo $file['id']; ?>"></span>
                  <label>
                    <input id="post_thumbnail_<?php echo $file['id']; ?>" type="radio" name="post_thumbnail" <?php checked($file['id'], $data['post_thumbnail']); ?> value="<?php echo $file['id']; ?>">
                    Capa
                  </label>
                  <input type="hidden" name="gallery-order[]" value="<?php echo $file['id']; ?>">
                </li>
              <?php endforeach; ?>
            </ul>

            <h5>
              <span class="fas fa-upload"></span>
              <span>Enviar Novas Imagens</span>
            </h5>

            <div class="box-input">
              <input type="file" name="input-file[]" id="input-file" multiple="multiple"
                accept="<?php echo $allowed_formats_accept; ?>" class="not_required" />
            </div>
            <div id="show_imgs"></div>

            <small>* Mínimo <?php echo $min_images; ?> imagens.</small><br>
            <small>* Máximo de <?php echo $max_images; ?> imagens.</small><br>
            <small>* Cada imagem deve ter no máximo <?php echo $max_size_label; ?></small><br>
            <small>* Formato de arquivo: <?php echo $allowed_formats; ?></small><br>
            <small>* Largura entre <?php echo $min_width; ?>px e <?php echo $max_width; ?>px | Altura entre
              <?php echo $min_height; ?>px e <?php echo $max_height; ?>px.</small>

          </div><!-- /imgs -->

          <div class="s-12 col">
            <hr />
          </div>

          <div class="s-12 m-7 l-6 col">

            <div class="row align-middle termos">
              <div class="col shrink">
                <input type="checkbox" checked name="termos" id="termos" value="true">
              </div>
              <div class="col reset">
                <?php get_template_part('template-parts/forms/termos'); ?>
              </div>
            </div>

          </div><!-- /termos -->

          <div class="s-12 m-5 l-6 col text-right">
            <input type="submit" class="bt-enviar" disabled value="Salvar Alterações" />
          </div><!-- /col -->

          <?php $nonce = wp_create_nonce('nonce_anuncio_editar'); ?>
          <input type="hidden" name="nonce_anuncio_editar" value="<?php echo $nonce; ?>" />

          <input type="hidden" name="action" value="bazar_anuncio_editar" />

          <?php get_template_part('template-parts/forms/input-redirect'); ?>

          <?php
          // Localização do anúncio = perfil do autor, não do usuário que edita (ex.: administrador).
          $bazar_input_location_user_id = (int) get_post_field( 'post_author', $post_id );
          get_template_part( 'template-parts/forms/input-location' );
          ?>

          <input type="hidden" name="post_id" value="<?php echo wp_strip_all_tags($_GET['post_id']); ?>" />
          <input type="hidden" name="post_type" value="1" />
          <input type="hidden" name="redirect"
            value="<?php echo get_the_permalink() . '?post_id=' . wp_strip_all_tags($_GET['post_id']); ?>" />

        </div><!-- /row -->

      </form>

    </div><!-- /form-box -->


    <div class="alert alert-info mt-2">
      <small><?php _e('Ao editar um anúncio aprovado, ele será <b>despublicado</b> para nova aprovação. Em caso de dúvidas, entre em contato <a href="' . get_bloginfo('url') . '/contato/" class="open-contato black regular" title="Contato"><u>clicando aqui</u></a>.', 'bazar'); ?></small>
    </div>

    <?php close_content(); ?>
    <script type="text/javascript">
      var __BAZAR_Page = 'anuncio-editar';   
    </script>
    <?php get_template_part('template-parts/modal/componente-quadro-medidas'); ?>
    <?php get_template_part('template-parts/modal/componente-trocadores'); ?>
    <?php get_template_part('template-parts/modal/componente-pneu'); ?>
    <?php get_footer(); endwhile; endif; ?>