<?php
/* Template Name: Anunciar*/
get_template_part('template-parts/global/validacao');

get_template_part('page-templates/mock/anuncio-inserir');

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

    $msg_medidas = __('<b>Medidas são opcionais</b>, mas lembre-se, quanto melhor descrever seu anúncio, maiores as chances de venda.', 'bazar');

    // Busca TODOS os componentes (pais e filhos) uma única vez para reutilizar
    // Usa função centralizada com cache
    $componentes_array = bazar_get_all_components();
    $parent_terms = (!is_wp_error($componentes_array) && !empty($componentes_array))
      ? bazar_get_componentes_parents()
      : array();

    get_header();
    ?>

    <h1 class="d-none">
      <?php bloginfo('name'); ?> - <?php the_title(); ?>
    </h1>

    <?php large_content(); ?>

    <div class="form-box anuncio-forms">

      <div class="row align-center align-middle sticky form-header">
        <div class="s-8 col">
          <h2 class="mb-0">
            <?php _e('Anuncie grátis, sem pagar comissões', 'bazar'); ?>
          </h2>
        </div>
        <div class="s-4 col text-right">
          <button type="button" title="<?php _e('Meus anúncios	', 'bazar'); ?>" class="button clear"
            onclick="window.location.href='<?php echo get_bloginfo('url') . '/meus-anuncios/'; ?>'">
            <i class="fa fa-th"></i>
          </button>
          <button type="submit" title="<?php _e('Inserir Anúncio', 'bazar'); ?>" form="form-anuncio-inserir"
            class="button send-form">
            <i class="fa fa-save"></i>
          </button>
        </div>
      </div><!-- sticky -->

      <div class="content">
        <?php // the_content(); ?>
      </div>

      <form method="post" id="form-anuncio-inserir" name="add_post" action="<?php the_permalink(); ?>"
        enctype="multipart/form-data">

        <div id="alert"></div>

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
                  <option value="<?php echo $modalidade->slug; ?>" <?php if (isset($_POST['modalidade']))
                       selected($modalidade->slug, $_POST['modalidade'], true); ?>>
                    <?php echo $modalidade->name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="s-12 m-4 col">

            <label for="marcas_modelos">Marca:</label>
            <select id="marcas_modelos" name="marcas_modelos" data-child="marcas_modelos_child" data-current="<?php if (isset($_POST['marcas_modelos']))
              echo $_POST['marcas_modelos']; ?>" data-label="Marca">
              <option value="">Selecione</option>
            </select>

          </div><!-- /marca -->
          <div class="s-12 m-4 col">

            <label for="marcas_modelos_child">Modelo:</label>
            <select id="marcas_modelos_child" name="marcas_modelos_child" data-current="<?php if (isset($_POST['marcas_modelos_child']))
              echo $_POST['marcas_modelos_child']; ?>" data-label="Modelo" class="input-sufix">
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
              <select id="conservacao" name="conservacao" data-label="Conservação">
                <option value="">Selecione</option>
                <?php foreach ($conservacao_array as $conservacao): ?>
                  <option value="<?php echo $conservacao->slug; ?>" <?php if (isset($_POST['conservacao']))
                       selected($conservacao->slug, $_POST['conservacao']); ?>>
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
            //'orderby' => 'name',
          );
          $material_array = get_terms($args_material);
          if ($material_array && !is_wp_error($material_array)):
            $material_array = __Bazar_Terms_Manager::ordenarUpCountItem($material_array);
            ?>
            <div class="s-12 m-4 col">
              <label>Material:</label>
              <select id="material" name="material" data-label="Material">
                <option value="">Selecione</option>
                <?php foreach ($material_array as $material): ?>
                  <option value="<?php echo $material->slug; ?>" <?php if (isset($_POST['material']))
                       selected($material->slug, $_POST['material']); ?>>
                    <?php echo $material->name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="s-12 m-4 col">
            <label>Peso:</label>
            <input id="peso_mask" maxlength="6" name="peso" type="text" class="input-sufix" placeholder="Peso"
              data-label="Peso" value="<?php if (isset($_POST['peso'])):
                echo $_POST['peso'];
              endif; ?>" />
            <span class="kg input-box-sufix">Kg</span>
          </div><!-- peso -->

          <div class="s-12 m-4 col">
            <label>Valor:</label>
            <input class="mask_money input-prefix" name="valor" type="text" placeholder="Valor" data-label="Valor" value="<?php if (isset($_POST['valor'])):
              echo $_POST['valor'];
            endif; ?>" />
            <span class="currency input-box-prefix">R$</span>
          </div><!-- valor -->

          <div class="s-12 m-4 col">
            <label>Ano:</label>
            <select name="ano" id="ano" data-label="Ano">
              <option value="">Selecione</option>
              <?php for ($n = date("Y"); $n >= 1970; $n--): ?>
                <option value="<?php echo $n; ?>" <?php if (isset($_POST['ano']))
                     selected($n, $_POST['ano'], $n); ?>>
                  <?php echo $n; ?>
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
              <select name="cor" id="cor" data-label="Cor">
                <option value="">Selecione</option>
                <?php foreach ($cores_array as $cor): ?>
                  <option value="<?php echo $cor->slug; ?>" <?php if (isset($_POST['cor']))
                       selected($cor->slug, $_POST['cor'], $n); ?>>
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
            //'orderby' => 'name',
          );
          $genero_array = get_terms($args_genero);
          if ($genero_array && !is_wp_error($genero_array)):
            ?>
            <div class="s-12 m-6 col">
              <label>Gênero:</label>
              <select name="genero" id="genero" data-label="Gênero">
                <option value="">Selecione</option>
                <?php foreach ($genero_array as $genero_): ?>
                  <option value="<?php echo $genero_->slug; ?>" <?php if (isset($_POST['genero']))
                       selected($genero_->slug, $_POST['genero']); ?>>
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
            //'orderby' => 'name',
          );
          $idade_array = get_terms($args_idade);
          if ($idade_array && !is_wp_error($idade_array)):
            ?>
            <div class="s-12 m-6 col">
              <label>Grupo de Idade:</label>
              <select name="idade" id="idade" data-label="Grupo de Idade">
                <option value="">Selecione</option>
                <?php
                $args = array(
                  'taxonomy' => 'idade',
                  'hierarchical' => 1,
                  'hide_empty' => false,
                  //'orderby' => 'name',
                );
                $taxs = get_terms($args);
                foreach ($taxs as $tax):
                  ?>
                  <option value="<?php echo $tax->slug; ?>" <?php if (isset($_POST['idade']))
                       selected($tax->slug, $_POST['idade']); ?>>
                    <?php echo $tax->name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>


          <div class="s-12 m-6 col">
            <label>Possui Nota Fiscal:</label>
            <select name="nota_fiscal" id="nota_fiscal" data-label="Nota Fiscal">
              <option value="">Selecione</option>
              <option value="true" <?php if (isset($_POST['nota_fiscal']))
                selected($_POST['nota_fiscal'], 'true'); ?>>Sim
              </option>
              <option value="false" <?php if (isset($_POST['nota_fiscal']))
                selected($_POST['nota_fiscal'], 'false'); ?>>Não
              </option>
            </select>
          </div><!-- /nf -->


          <div class="s-12 m-6 col">
            <label>Exibir meu Telefone na busca:</label>
            <select name="exibir_contato" id="exibir_contato" data-label="Exibir Telefone">
              <option value="">Selecione</option>
              <option value="true" <?php if (isset($_POST['exibir_contato']))
                selected($_POST['exibir_contato'], 'true'); ?>>Sim</option>
              <option value="false" <?php if (isset($_POST['exibir_contato']))
                selected($_POST['exibir_contato'], 'false'); ?>>Não</option>
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
              <h4 class="mb-2">Negociação</h4>
              <?php foreach ($negociacao_array as $x => $categ5): ?>
                <div>
                  <label for="condicao<?php echo $x; ?>" class="bold xl">
                    <input type="checkbox" value="<?php echo $categ5->slug; ?>" id="condicao<?php echo $x; ?>"
                      name="negociacao[]" data-label="Negociação" <?php // if( isset( $_POST['negociacao'] ) ) : checked( $categ5->slug, $_POST['negociacao'][$x] ); endif; ?>         <?php if (isset($_POST['negociacao']) && is_array($_POST['negociacao']) && in_array($categ5->slug, $_POST['negociacao']))
                                  echo 'checked'; ?> />
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
                <small>Restam (<span id="qts" class="text-right silver">600</span>) caracteres</small>
              </span>
            </h4>

            <textarea id="txt-descricao" name="txt-descricao" data-label="Descrição" rows="8" maxlength="600"
              minlength="10"><?php if (isset($_POST['txt-descricao']))
                echo $_POST['txt-descricao']; ?></textarea>

          </div><!-- /desc -->

          <div class="s-12 col">
            <hr />
          </div>

          <div class="s-12 col">

            <h4>
              <span class="fas fa-camera"></span>
              <span>Imagens | A última selecionada é definida como Capa</span>
            </h4>
            <div class="box-input">
              <input type="file" name="input-file[]" id="input-file" multiple="multiple"
                accept="<?php echo $allowed_formats_accept; ?>" />
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

          <div class="s-12 col">
            <h4>Descreva o Produto</h4>
          </div>
          <div class="s-12 col">

            <?php
            $categs = get_terms(array(
              'taxonomy' => 'category',
              'hide_empty' => false,
              'parent' => 0,
              'hierarchical' => 1
            ));
            if ($categs && !is_wp_error($categs)):
              $categs = __Bazar_Terms_Manager::ordenar($categs, 'category');
              ?>
              <div id="tab-add" class="tabs-menu">
                <?php foreach ($categs as $key => $categ): ?>
                  <button type="button" class="tab-btn <?php echo ($key == 0) ? 'active' : ''; ?>"
                    data-tab="add-<?php echo $categ->slug; ?>" data-categoria="<?php echo $categ->slug; ?>"
                    data-category-id="<?php echo $categ->term_id; ?>">
                    <div class="bx">
                      <span class="tab-icon">
                        <?php echo __Bazar_Terms_Manager::get_term_icon($categ); ?>
                      </span>
                      <h3><?php echo $categ->name; ?></h3>
                    </div>
                  </button>
                <?php endforeach; ?>

                <input type="hidden" name="category" value="<?php echo $categs[0]->slug; ?>" />

                <input type="hidden" name="category_id" value="<?php echo $categs[0]->term_id; ?>" />

                <input type="hidden" name="category_fields" value="<?php echo 'add-' . $categs[0]->slug; ?>" />

                <input type="hidden" name="title" value="" />

              </div><!-- /tabs-menu -->

              <?php if ($parent_terms && !is_wp_error($parent_terms)): ?>
                <div id="add-peca" class="tab-content" style="display: none;">
                  <div class="row">
                    <div class="s-12 col relative pb-1">

                      <small><?php echo $msg_medidas; ?></small>

                    </div>
                    <div class="s-12 m-6 col">

                      <label class="blue">Componente:</label>
                      <select id="componente_pecas" name="componente_pecas" data-categ="componente" data-label="Componente"
                        data-child="componente_pecas_child" data-allItens="true" class="list-sub-item">
                        <option value="">Selecione</option>
                        <?php foreach ($parent_terms as $key => $componente): ?>
                          <option value="<?php echo $componente->term_id; ?>" <?php if (isset($_POST['componente_pecas']))
                               selected($componente->term_id, $_POST['componente_pecas'], true); ?>>
                            <?php echo $componente->name; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>

                    </div>
                    <div class="s-12 m-6 col">

                      <label class="blue">Especificação:</label>
                      <select id="componente_pecas_child" name="componente_pecas_child" data-label="Especificação" data-current="<?php if (isset($_POST['componente_pecas_child']))
                        echo $_POST['componente_pecas_child']; ?>">
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
                          <?php foreach ($medidas as $medida): ?>
                            <option value="<?php echo $medida->term_id; ?>" <?php if (isset($_POST['medidas'][0]))
                                 selected($medida->term_id, $_POST['medidas'][0], true); ?>>
                              <?php echo $medida->name; ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="s-12 m-6 col">
                        <label>Especificação:</label>
                        <select id="medidas_child_0" name="medidas_child[0]" class="not_required" data-label="Especificação"
                          data-current="<?php if (isset($_POST['medidas_child'][0]))
                            echo $_POST['medidas_child'][0]; ?>">
                          <option value="">Selecione</option>
                        </select>
                      </div>

                      <div class="col s-12 medidas-dynamic-container"></div>

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
                </div><!-- /tab-content -->
                <?php
              // end peças
            endif; ?>

              <?php
              $args_acessorios = array(
                'taxonomy' => 'acessorio',
                'hide_empty' => false,
                'parent' => 0,
                'hierarchical' => 1
              );
              $acessorios_ = get_terms($args_acessorios);
              if ($acessorios_ && !is_wp_error($acessorios_)):
                $parent_acessorios = __Bazar_Terms_Manager::ordenarAlfabeticamente($acessorios_);
                ?>
                <div id="add-acessorio" class="tab-content" style="display: none;">
                  <div class="row">
                    <div class="s-12 col relative pb-1">
                      <small><?php echo $msg_medidas; ?></small>
                    </div>
                    <div class="s-12 m-6 col">
                      <label class="blue">Acessório:</label>
                      <select id="acessorio" name="acessorio" data-categ="acessorio" data-label="Acessório"
                        data-child="acessorio_child" data-allItens="true" class="list-sub-item">
                        <option value="">Selecione</option>
                        <?php foreach ($parent_acessorios as $acessorio): ?>
                          <option value="<?php echo $acessorio->term_id; ?>" <?php if (isset($_POST['acessorio']))
                               selected($acessorio->term_id, $_POST['acessorio'], true); ?>>
                            <?php echo $acessorio->name; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="s-12 m-6 col">
                      <label class="blue">Especificação:</label>
                      <select id="acessorio_child" name="acessorio_child" data-label="Especificação">
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
                          <?php foreach ($medidas as $medida): ?>
                            <option value="<?php echo $medida->term_id; ?>" <?php if (isset($_POST['medidas_acessorio'][0]))
                                 selected($medida->term_id, $_POST['medidas_acessorio'][0], true); ?>>
                              <?php echo $medida->name; ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="s-12 m-6 col">
                        <label>Especificação:</label>
                        <select id="medidas_child_0_acessorio" name="medidas_acessorio_child[0]" data-label="Especificação"
                          data-current="<?php if (isset($_POST['medidas_acessorio_child'][0]))
                            echo $_POST['medidas_acessorio_child'][0]; ?>" class="not_required">
                          <option value="">Selecione</option>
                        </select>
                      </div>

                      <div class="col s-12 medidas-dynamic-container"></div>

                      <div class="col s-12 pb-1">
                        <button type="button" id="btn-nova-medida-acessorio" class="button small dark"
                          title="<?php _e('Adicionar nova medida', 'bazar'); ?>">
                          <i class="fa fa-plus"></i> <?php _e('Adicionar Nova Medida', 'bazar'); ?>
                        </button>
                      </div>
                    <?php endif; ?>

                  </div><!-- /row -->
                </div><!-- /tab-content -->
                <?php
                // end acessórios
              endif; ?>

              <?php if ($parent_terms && !is_wp_error($parent_terms)): ?>
                <div id="add-bicicleta" class="tab-content">
                  <div class="row form_componentes pb-1">
                    <div class="s-12 col relative pb-1">
                      <small>
                        - Os componentes <b class="blue">em azul são obrigatórios</b> e os demais opcionais.
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
                      ?>
                      <div class="col s-12 m-4 l-3 componente<?php echo (!$is_default) ? ' componente-optional' : ''; ?>"
                        data-key="<?php echo $item->term_id; ?>" data-component-name="<?php echo esc_attr($item->name); ?>">

                        <div class="componente-wrap">

                          <div class="componente-header">

                            <?php echo $icon; ?>

                            <label for="componente_<?php echo $item->term_id; ?>"
                              class="h4 <?php echo ($is_default) ? 'blue' : '' ?>">

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
                              <button type="button" class="componente-toggle" aria-expanded="false"
                                aria-label="<?php echo esc_attr(sprintf(__('Adicionar %s', 'bazar'), $item->name)); ?>">
                                <i class="fa fa-plus" aria-hidden="true"></i>
                              </button>
                            <?php endif; ?>

                          </div>

                          <?php if (!empty($child_terms)): ?>
                            <div class="componente-fields">
                              <select data-label="<?php echo $item->name; ?>" id="componente_<?php echo $item->term_id; ?>"
                                name="componente[<?php echo $item->term_id; ?>]"
                                class="<?php echo (!$is_default) ? 'not_required' : ''; ?>">
                                <option value="">Especificação</option>
                                <?php foreach ($child_terms as $child): ?>
                                  <option value="<?php echo esc_attr($child->term_id); ?>" <?php if (isset($_POST['componente'][$item->term_id]) && $_POST['componente'][$item->term_id] == $child->term_id)
                                       selected($child->term_id, $_POST['componente'][$item->term_id]); ?>>
                                    <?php echo esc_html($child->name); ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>

                              <select id="marca_<?php echo esc_attr($item->term_id); ?>"
                                name="c_marca[<?php echo esc_attr($item->term_id); ?>]"
                                data-child="modelo_<?php echo esc_attr($item->term_id); ?>" data-label="Marca" class="not_required">
                                <option value="">Marca</option>
                              </select>

                              <select id="modelo_<?php echo esc_attr($item->term_id); ?>"
                                name="c_modelo[<?php echo esc_attr($item->term_id); ?>]" data-label="Modelo" class="not_required">
                                <option value="">Modelo</option>
                              </select>
                            </div>
                          <?php endif; ?>

                        </div>

                      </div><!-- /col -->
                    <?php endforeach; ?>

                  </div><!-- /form_componentes -->
                </div><!-- /tab-content -->
                <?php
              // end bicicleta
            endif; ?>

              <?php
              // if( $categoria && !is_wp_error( $categoria ) ) :
            endif; ?>

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
            <input type="submit" class="bt-enviar" value="<?php _e('Cadastrar anúncio', 'bazar'); ?>" />
          </div><!-- /col -->

          <?php $nonce = wp_create_nonce('nonce_anuncio_inserir'); ?>
          <input type="hidden" name="nonce_anuncio_inserir" value="<?php echo $nonce; ?>" />
          <input type="hidden" name="action" value="bazar_anuncio_inserir" />
          <?php get_template_part('template-parts/forms/input-redirect'); ?>
          <?php get_template_part('template-parts/forms/input-location'); ?>

        </div><!-- /row -->

      </form>

    </div><!-- /form-box -->

    <?php close_content(); ?>

    <script type="text/javascript">
      var __BAZAR_Page = 'anuncio-inserir';
    </script>
    <?php get_template_part('template-parts/modal/componente-quadro-medidas'); ?>
    <?php get_template_part('template-parts/modal/componente-trocadores'); ?>
    <?php get_template_part('template-parts/modal/componente-pneu'); ?>
    <?php get_footer(); endwhile; endif; ?>