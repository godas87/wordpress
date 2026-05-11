<?php
global $post_data, $index_query, $base_query, $current_term, $categories_query;
global $geo_api;
if (!$geo_api) {
    $geo_api = BazarBikes_GeoAPI::getInstance();
}

// Preparar localização usando função helper
global $current_location;
if (!$current_location || empty($current_location['localizacao'])) {
    $current_location = bazar_get_current_location();
}

$location_data = bazar_prepare_location_for_filter(
    $current_term, 
    $current_location, 
    $geo_api
);
$current_estado = $location_data['current_estado'];
$current_cidade = $location_data['current_cidade'];
$estados_com_anuncios = $location_data['estados_com_anuncios'];
$cidades_disponiveis = $location_data['cidades_disponiveis'];

// Form URL
$form_url = ($current_term && !is_wp_error($current_term) && (is_tax() || is_category()))
  ? esc_url(get_term_link($current_term))
  : esc_url(home_url('/bicicletas/'));

// Preparar categorias usando função helper
$is_index_page = ( !is_tax() && !is_category() );
$categs = bazar_get_categories_by_filter(
    $base_query, 
    $categories_query, 
    $index_query, 
    $is_index_page
);
$categories_data = bazar_prepare_categories_for_display(
    $categs, 
    $post_data, 
    $current_term
);

$categs = $categories_data['categories'];
$has_categories = $categories_data['has_categories'];
$has_single_category = $categories_data['has_single_category'];
$queried_category = $categories_data['queried_category'];

// Obter slugs para taxonomies
global $active_categories;
if (!$active_categories) {
  if ($has_categories) {
    $active_categories = array_map(function($c) { return $c->slug; }, $categs);
  } else {
    $active_categories = bazar_get_main_categories_slugs();
  }
}

$taxonomies = bazar_get_taxonomies_by_categories($active_categories);
?>
<div id="sidebar-search" class="form-busca modal-nav">
    <div class="bx">
        <div class="form-head modal-nav-head show-for-s-only">
            <span class="fa fa-sliders-h red"></span>
            <small><b>FILTRAR RESULTADOS</b></small>
            <a id="bt-close-filter" href="#" class="close bold bt-close-filter">Fechar</a>
        </div>
        <div class="form-body modal-nav-body">

            <b class="label-title">Localidade</b>
            <div class="box-highlight">
                <select 
                    id="estado_localizacao" 
                    name="estado" 
                    data-categ="cidade" 
                    data-current="<?php echo $current_estado; ?>" 
                    data-child="cidade_localizacao"
                >
                    <option value=""><?php _e('Estado', 'bazar'); ?></option>
                    <?php 
                    if (!empty($estados_com_anuncios)): 
                        foreach ($estados_com_anuncios as $estado): 
                    ?>
                        <option 
                            value="<?php echo esc_attr($estado->term_id); ?>"<?php echo ($estado->term_id === $current_estado) ? ' selected' : ''; ?>>
                            <?php echo esc_html($estado->name); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
                <select 
                    id="cidade_localizacao" 
                    name="cidade" 
                    data-categ="cidade" 
                    data-current="<?php echo $current_cidade; ?>"
                >
                    <option value=""><?php _e('Cidades', 'bazar'); ?></option>
                    <?php 
                    if (!empty($cidades_disponiveis)): 
                        foreach ($cidades_disponiveis as $cidade): 
                    ?>
                        <option 
                            value="<?php echo esc_attr($cidade->term_id); ?>" <?php echo ($cidade->term_id == $current_cidade) ? 'selected' : ''; ?>>
                            <?php echo esc_html($cidade->name); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
                <a 
                    href="#" 
                    class="btn-location <?php echo ($current_estado || $current_cidade) ? '' : 'desactivated'; ?>" 
                    data-estado-id="<?php echo esc_attr($current_estado); ?>" 
                    data-cidade-id="<?php echo esc_attr($current_cidade); ?>" 
                    id="btn-location-redirect"
                    <?php echo ($current_estado || $current_cidade) ? '' : 'disabled'; ?>
                >
                    <i class="fas fa-map-marker-alt"></i>
                    <?php _e('Selecionar', 'bazar'); ?>
                </a>
            </div>

            <form 
                id="form_busca" 
                class="form" 
                method="get" 
                name="busca" 
                autocomplete="off" 
                action="<?php echo esc_url($form_url); ?>"
            >
                
                <?php // Category
                if ($has_categories): ?>
                <b class="label-title">Categoria</b>
                <div id="box-categories" class="box-highlight">
                    <?php 
                    foreach($categs as $categ): 
                        
                        $category_link = get_category_link( $categ->term_id );                        
                        $category_link = ( $category_link && !is_wp_error($category_link) )
                            ? $category_link 
                            : '';
                        
                        // Verificar se está ativa
                        $is_active = (
                            ($current_term && $categ->term_id == $current_term->term_id) ||
                            ($queried_category && $categ->term_id == $queried_category->term_id) ||
                            bazar_is_term_selected(
                                $categ->slug, 
                                'category', 
                                $post_data, 
                                $current_term
                            ) ||
                            $has_single_category
                        );
                        
                        $active_class = $is_active ? 'active' : '';
                        $disabled_class = $has_single_category ? 'disabled' : '';
                        
                        if ($is_index_page && $category_link): ?>
                            <a 
                                href="<?php echo esc_url($category_link); ?>" 
                                title="<?php echo esc_attr($categ->name); ?>" 
                                class="box-highlight-item regular category-item <?php echo $active_class; ?> <?php echo $disabled_class; ?>"
                            >
                                <span class="box-highlight-icon">
                                    <?php echo __Bazar_Terms_Manager::get_term_icon($categ); ?>
                                </span>
                                <span class="box-highlight-name">
                                    <?php echo esc_html($categ->name); ?>
                                </span>
                                <span class="box-highlight-check">
                                    <i class="fas fa-check"></i>
                                </span>
                            </a>
                        <?php else: ?>
                            <button 
                                type="button" 
                                title="<?php echo esc_attr($categ->name); ?>" 
                                class="box-highlight-item regular category-item <?php echo $active_class; ?> <?php echo $disabled_class; ?>" 
                                data-category-slug="<?php echo esc_attr($categ->slug); ?>" 
                                data-category-link="<?php echo esc_url($category_link); ?>" 
                                data-is-category-page="<?php echo is_category() ? '1' : '0'; ?>" 
                                <?php echo $has_single_category ? 'disabled' : ''; ?>
                            >
                                <input 
                                    type="checkbox" 
                                    name="category[]" 
                                    value="<?php echo esc_attr($categ->slug); ?>" 
                                    <?php echo $is_active ? 'checked' : ''; ?> 
                                    <?php echo $has_single_category ? 'disabled' : ''; ?> 
                                    style="display:none;" 
                                />
                                <span class="box-highlight-icon"><?php echo __Bazar_Terms_Manager::get_term_icon($categ); ?></span>
                                <span class="box-highlight-name"><?php echo esc_html($categ->name); ?></span>
                                <span class="box-highlight-check"><i class="fas fa-check"></i></span>
                            </button>
                        <?php endif;
                    endforeach; ?>
                </div>
                <?php endif; ?>

                <?php
                // Componentes (apenas os pais com default_bicicletas = true / "obrigatórios").
                // Estratégia:
                //  - Pais: bazar_get_componentes_default() -> cached em memória (0 queries)
                //  - Filhos: bazar_get_all_components() -> cached em memória (0 queries)
                //  - Disponibilidade no contexto: bazar_get_available_term_ids() -> 1 SQL
                // Antes: ~N+1 SQLs (uma por pai) + requeries. Agora: 1 SQL total.
                if (isset($taxonomies['componente'])):
                    $parents_obrigatorios = bazar_get_componentes_default();

                    if (!empty($parents_obrigatorios)):
                        $query_for_terms = ($base_query instanceof WP_Query) ? $base_query : $index_query;

                        // term_ids de 'componente' presentes nos resultados atuais
                        $available_ids = bazar_get_available_term_ids('componente', $query_for_terms);
                        $available_lookup = array_flip(array_map('intval', $available_ids));

                        // Agrupa TODOS os filhos por parent_id (cache em memória)
                        $all_components = bazar_get_all_components();
                        $children_by_parent = array();
                        foreach ($all_components as $term) {
                            if (empty($term->parent)) continue;
                            $children_by_parent[(int) $term->parent][] = $term;
                        }

                        $is_componente_tax_page = (
                            $current_term
                            && !is_wp_error($current_term)
                            && $current_term->taxonomy === 'componente'
                        );

                        foreach ($parents_obrigatorios as $componente):
                            $parent_id = (int) $componente->term_id;
                            $all_childs = isset($children_by_parent[$parent_id])
                                ? $children_by_parent[$parent_id]
                                : array();

                            if (empty($all_childs)) continue;

                            // Caso especial: na página de taxonomia 'componente', o bloco 'aro'
                            // é renderizado como links de navegação (todos os filhos, sem filtrar por query).
                            $render_as_links = ($is_componente_tax_page && $componente->slug === 'aro');

                            if ($render_as_links):
                                echo '<b class="label-title">' . esc_html($componente->name) . '</b>';
                                echo '<div class="checkbox-group">';
                                foreach ($all_childs as $child):
                                    $term_link = get_term_link($child, 'componente');
                                    if (!$term_link || is_wp_error($term_link)) continue;
                                    $is_current = ($current_term && $current_term->term_id === $child->term_id);
                                    ?>
                                    <a
                                        href="<?php echo esc_url($term_link); ?>"
                                        class="checkbox-label taxonomy-link regular <?php echo $is_current ? 'active' : ''; ?>"
                                        title="<?php echo esc_attr($child->name); ?>"
                                    >
                                        <span class="checkbox-custom"></span>
                                        <?php echo esc_html($child->name); ?>
                                    </a>
                                <?php endforeach;
                                echo '</div>';
                                continue;
                            endif;

                            // Checkboxes: filtra filhos disponíveis no contexto atual
                            $componentes_childs = array();
                            foreach ($all_childs as $child) {
                                if (isset($available_lookup[(int) $child->term_id])) {
                                    $componentes_childs[] = $child;
                                }
                            }
                            if (empty($componentes_childs)) continue;

                            // Ordena por count (mais populares primeiro) e promove selecionados.
                            usort($componentes_childs, function ($a, $b) {
                                return ((int) $b->count) - ((int) $a->count);
                            });

                            $selected = array();
                            $unselected = array();
                            foreach ($componentes_childs as $child) {
                                $is_checked = bazar_is_term_selected(
                                    $child->slug,
                                    'componente_filter',
                                    $post_data,
                                    null,
                                    $componente->slug
                                );
                                if ($is_checked) {
                                    $selected[] = $child;
                                } else {
                                    $unselected[] = $child;
                                }
                            }
                            $componentes_childs = array_merge($selected, $unselected);

                            echo '<b class="label-title">' . esc_html($componente->name) . '</b>';
                            echo '<div class="checkbox-group">';
                            foreach ($componentes_childs as $child):
                                $is_checked = bazar_is_term_selected(
                                    $child->slug,
                                    'componente_filter',
                                    $post_data,
                                    null,
                                    $componente->slug
                                );
                                $componente_value = esc_attr($componente->slug . '-' . $child->slug);
                                ?>
                                <label class="checkbox-label">
                                    <input
                                        type="checkbox"
                                        name="componente_filter[]"
                                        value="<?php echo $componente_value; ?>"
                                        <?php echo $is_checked ? 'checked' : ''; ?>
                                    >
                                    <span class="checkbox-custom"></span>
                                    <?php echo esc_html($child->name); ?>
                                </label>
                            <?php endforeach;
                            echo '</div>';
                        endforeach;
                    endif;
                endif;
                ?>

                <?php
                // Outras taxonomias (exceto 'componente', tratado acima).
                if (!empty($taxonomies)):
                    foreach ($taxonomies as $tax => $title):

                        if ($tax === 'componente') continue;

                        $is_taxonomy_page = (
                            $current_term
                            && !is_wp_error($current_term)
                            && $current_term->taxonomy === $tax
                        );

                        // Em páginas de taxonomia de marca-modelo/modalidade mostramos
                        // todos os termos da taxonomia (navegação), ignorando a base_query.
                        $force_all = $is_taxonomy_page && (
                            $tax === 'marca-modelo' || $tax === 'modalidade'
                        );

                        $query_for_terms = ($base_query instanceof WP_Query) ? $base_query : $index_query;

                        $args_terms = bazar_get_terms_from_query(
                            $tax,
                            $force_all ? null : $query_for_terms,
                            array('parent' => 0),
                            null,
                            $force_all
                        );
                        
                        if ($args_terms && !is_wp_error($args_terms) && !empty($args_terms)):
                            // Separar selecionados e não selecionados
                            // Termos já vêm ordenados por count de bazar_get_terms_from_query()
                            $selected = array();
                            $unselected = array();
                            foreach ($args_terms as $term) {
                                $is_checked = bazar_is_term_selected(
                                    $term->slug, 
                                    $tax . '_filter', 
                                    $post_data, 
                                    $current_term
                                );
                                if ( $is_checked ) {
                                    $selected[] = $term;
                                } else {
                                    $unselected[] = $term;
                                }
                            }
                            
                            // Juntar: selecionados primeiro (mantendo ordem por count dentro de cada grupo)
                            $args_terms = !empty($selected)
                                ? array_merge($selected, $unselected) 
                                : $unselected;
                            
                            echo '<b class="label-title">' . esc_html($title) . '</b>';
                            echo '<div class="checkbox-group">';
                            
                            if ($is_taxonomy_page && ($tax === 'marca-modelo' || $tax === 'modalidade')):
                                // Links para marca-modelo e modalidade em suas próprias páginas (sem parâmetros)
                                foreach ($args_terms as $term):
                                    if ($tax == 'marca-modelo' && $term->parent !== 0) continue;
                                    
                                    $term_link = get_term_link($term, $tax);
                                    if (!$term_link || is_wp_error($term_link)) continue;
                                    
                                    $is_current = ($current_term && $current_term->term_id === $term->term_id) 
                                        || ($tax === 'marca-modelo' && $current_term && $current_term->parent === $term->term_id);
                                    // Links não devem preservar outros filtros (redirect limpo)
                                    ?>
                                    <a 
                                        href="<?php echo esc_url($term_link); ?>" 
                                        class="checkbox-label taxonomy-link regular <?php echo $is_current ? 'active' : ''; ?>" 
                                        title="<?php echo esc_attr($term->name); ?>"
                                    >
                                        <span class="checkbox-custom"></span>
                                        <?php echo esc_html($term->name); ?>
                                    </a>
                                <?php endforeach;
                            else:
                                // Checkboxes para outras taxonomias
                                foreach ($args_terms as $term):
                                    if ( $tax == 'marca-modelo' && $term->parent !== 0 ) continue;
                                    $is_checked = bazar_is_term_selected(
                                        $term->slug, 
                                        $tax . '_filter', 
                                        $post_data, 
                                        $current_term
                                    );
                                    ?>
                                    <label class="checkbox-label">
                                        <input 
                                            type="checkbox" 
                                            name="<?php echo esc_attr($tax); ?>_filter[]" 
                                            value="<?php echo esc_attr($term->slug); ?>" 
                                            <?php echo $is_checked ? 'checked' : ''; ?>
                                        >
                                            <span class="checkbox-custom"></span>
                                            <?php echo esc_html($term->name); ?>
                                    </label>
                                <?php endforeach;
                            endif;
                            echo '</div>';
                        endif;
                    endforeach;
                endif;
                ?>

                <?php
                // Faixas de valor
                $faixas_valor = bazar_get_faixas_valor_optimized($index_query);
                if (!empty($faixas_valor)): ?>
                    <b class="label-title">Valor</b>
                    <div class="checkbox-group">
                        <?php foreach ($faixas_valor as $faixa):
                            $faixa_min = intval($faixa['min']);
                            $faixa_max = intval($faixa['max']);
                            $faixa_value = $faixa_min . '-' . $faixa_max;
                            
                            $checked = false;
                            if (isset($post_data['valor_faixa'])) {
                                $valor_faixa = bazar_normalize_filter_value($post_data['valor_faixa']);
                                foreach ($valor_faixa as $valor_url) {
                                    if (strpos($valor_url, '-') !== false) {
                                        $parts = explode('-', $valor_url);
                                        if (count($parts) == 2 && intval($parts[0]) == $faixa_min && intval($parts[1]) == $faixa_max) {
                                            $checked = true;
                                            break;
                                        }
                                    }
                                    if ($valor_url === $faixa_value) {
                                        $checked = true;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="valor_faixa[]" value="<?php echo esc_attr($faixa_value); ?>" data-min="<?php echo $faixa['min']; ?>" data-max="<?php echo $faixa['max']; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                <span class="checkbox-custom"></span>
                                <?php echo $faixa['label']; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <a 
                    id="clear-search" 
                    href="#" 
                    class="clear-search black <?php echo empty($_SERVER["QUERY_STRING"]) ? 'disabled' : ''; ?>" 
                    title="Limpar busca"
                    <?php echo empty($_SERVER["QUERY_STRING"]) ? 'disabled' : ''; ?>
                >
                    <i class="fas fa-undo"></i><small>Limpar Formulário</small>
                </a>

            </form>
        </div>
    </div>
    <div class="bg-overlay"></div>
</div>
