<?php
/**
 * Helper para obter campo 'ordem' de um termo
 * Reutilizável em outros lugares
 */
if (!function_exists('exibir_taxonomias_no_footer')) {
    function exibir_taxonomias_no_footer() {
        
        // Usar cache do WordPress (wp_cache_get/wp_cache_set)
        // v3: marca-modelo passa a exibir marcas (pais) com count agregado > 5
        $cache_key = 'footer_taxonomies_menu_v3';
        $cache_group = 'bazar_footer_menu';
        // Limite mínimo de itens publicados (agregado pai+filhos) para uma marca aparecer
        $marca_min_count = 5;
        
        $cached_output = wp_cache_get($cache_key, $cache_group);
        
        if ($cached_output !== false) {
            echo $cached_output;
            return;
        }
        
        // Iniciar buffer de saída para cache
        ob_start();
        
        // Definir as taxonomias
        $taxonomias = array( 
            // 'category',
            'modalidade', 
            'marca-modelo',
            'cidade'
        );
        
        foreach ( $taxonomias as $taxonomia ) :
            // Usar get_terms() com filtro global (remove automaticamente lixeira/vendidos)
            // O filtro global bazar_filter_terms_exclude_trash() já remove termos
            // que só têm posts na lixeira ou vendidos quando hide_empty = true
            // Ordenar por count primeiro (WordPress já faz isso nativamente)
            $categs = get_terms( array(
                'taxonomy' => $taxonomia,
                'hide_empty' => true, // Filtro global aplica automaticamente
                'orderby' => 'count', // Ordenar por quantidade
                'order' => 'DESC' // Maior para menor
            ) );
            
            if( empty($categs) || is_wp_error($categs) ) {
                continue; // Pular se não houver termos
            }
            
            // Verificar se existem termos para essa taxonomia
            if( ! empty( $categs ) && ! is_wp_error( $categs ) ) :

                echo '
                <div class="s-11 l-12 col">                
                    <ul>
                        <li><h4>';

                            if( $taxonomia === 'category' ) :
                                _e( 'Bicicletas Usadas e Novas', 'bazar' );
                            endif;

                            if( $taxonomia === 'modalidade' ) :
                                _e( 'Todas as Modalidades', 'bazar' );
                            endif;

                            if( $taxonomia === 'marca-modelo' ) :
                                _e( 'Marcas e Modelos', 'bazar' );
                            endif;

                            if( $taxonomia === 'cidade' ) :
                                _e( 'Encontre a bicicleta certa na sua Cidade', 'bazar' );
                            endif;

                echo '</h4></li>';

                $key = 0;
                $has_items = false;

                // Para modalidade, verificar se há termos filhos primeiro
                $has_children = false;
                if( $taxonomia === 'modalidade' ) {
                    foreach ( $categs as $categ ) {
                        if( $categ->parent !== 0 ) {
                            $has_children = true;
                            break;
                        }
                    }
                }

                // Para marca-modelo, exibir apenas os pais (marcas) com count agregado > $marca_min_count.
                // Itens são quase sempre cadastrados nos filhos (modelos), então o count "nativo"
                // do pai costuma ser 0. Agregamos: pai.count + soma(filhos.count).
                $marca_aggregate = array(); // [parent_term_id => total_count agregado]
                if( $taxonomia === 'marca-modelo' ) {
                    foreach ( $categs as $c ) {
                        $pid = ( (int) $c->parent === 0 ) ? (int) $c->term_id : (int) $c->parent;
                        if( !isset($marca_aggregate[$pid]) ) {
                            $marca_aggregate[$pid] = 0;
                        }
                        $marca_aggregate[$pid] += (int) $c->count;
                    }

                    // Reduzir $categs apenas aos pais e reordenar pelo count agregado (DESC).
                    $parents_only = array();
                    foreach ( $categs as $c ) {
                        if( (int) $c->parent === 0 ) {
                            $parents_only[] = $c;
                        }
                    }
                    usort($parents_only, function($a, $b) use ($marca_aggregate) {
                        $ca = isset($marca_aggregate[(int)$a->term_id]) ? $marca_aggregate[(int)$a->term_id] : 0;
                        $cb = isset($marca_aggregate[(int)$b->term_id]) ? $marca_aggregate[(int)$b->term_id] : 0;
                        if( $ca === $cb ) {
                            return strcasecmp($a->name, $b->name);
                        }
                        return ($cb > $ca) ? 1 : -1;
                    });
                    $categs = $parents_only;
                }

                foreach ( $categs as $categ ) :

                    if( $key > 20 ) break;

                    // Filtros por taxonomia: o que entra no menu
                    if( $taxonomia === 'marca-modelo' ) {
                        // Apenas pais (já garantido acima) com count agregado mínimo
                        $aggregate = isset($marca_aggregate[(int) $categ->term_id])
                            ? (int) $marca_aggregate[(int) $categ->term_id]
                            : 0;
                        if( $aggregate <= $marca_min_count ) continue;
                    } elseif( $taxonomia === 'modalidade' ) {
                        // Se há filhos, exibir apenas filhos; senão, exibir pais
                        if( $has_children && $categ->parent === 0 ) continue;
                        if( !$has_children && $categ->parent !== 0 ) continue;
                    } else {
                        // Para outras taxonomias, exibir apenas filhos
                        if( $categ->parent === 0 ) continue;
                    }

                    $link = get_term_link( $categ );
                    if( is_wp_error( $link ) ) continue;

                    // Formatar label baseado na taxonomia
                    if( $taxonomia === 'marca-modelo' ) {
                        // Marca (pai): apenas o nome da marca
                        $label = esc_html($categ->name);
                    } elseif( $taxonomia === 'modalidade' ) {
                        // Modalidade: exibir apenas o nome do termo (se for pai) ou "Pai Filho" (se for filho)
                        if( $categ->parent === 0 ) {
                            $label = esc_html($categ->name);
                        } else {
                            $parent = get_term( $categ->parent, $taxonomia );
                            if( $parent && !is_wp_error($parent) ) {
                                $label = $parent->name . ' ' . $categ->name;
                            } else {
                                $label = esc_html($categ->name);
                            }
                        }
                    } elseif( $taxonomia === 'cidade' ) {
                        // Cidade: formatar como "Cidade / Estado"
                        $parent = get_term( $categ->parent, $taxonomia );
                        if( $parent && !is_wp_error($parent) ) {
                            $label = esc_html($categ->name) . ' / ' . strtoupper(esc_html($parent->slug));
                        } else {
                            $label = esc_html($categ->name);
                        }
                    } else {
                        // Outras taxonomias: "Pai Filho"
                        $parent = get_term( $categ->parent, $taxonomia );
                        if( $parent && !is_wp_error($parent) ) {
                            $label = $parent->name . ' ' . $categ->name;
                        } else {
                            $label = esc_html($categ->name);
                        }
                    }

                    $output = '<li>
                        <a 
                            href="' . esc_url( $link ) . '" 
                            title="' . esc_html( $categ->name ) . '"
                            class="regular"
                        >'. esc_html( $label ) .'</a>
                    </li>';
                    $key ++;
                    $has_items = true;
                    echo $output;

                endforeach;
                
                // Fechar apenas se houver itens
                if( $has_items ) {
                    echo '
                    </ul>
                </div>';
                } else {
                    // Se não houver itens, fechar o HTML aberto
                    echo '
                    </ul>
                </div>';
                }
            endif;
        endforeach;
        
        // Capturar output e salvar no cache do WordPress (usando wp_cache)
        $output = ob_get_clean();
        // Cache por 24 horas (86400 segundos) - menu de taxonomias muda raramente
        // Cache é limpo automaticamente via hooks quando taxonomias são editadas
        wp_cache_set($cache_key, $output, $cache_group, 86400);
        // Exibir output
        echo $output;
    }
}
/**
 * Limpar cache quando taxonomias são atualizadas
 */
add_action('edited_term', 'bazar_clear_footer_taxonomies_cache');
add_action('created_term', 'bazar_clear_footer_taxonomies_cache');
add_action('delete_term', 'bazar_clear_footer_taxonomies_cache');
add_action('wp_trash_post', 'bazar_clear_footer_cache_on_post_status_change');
add_action('untrash_post', 'bazar_clear_footer_cache_on_post_status_change');
add_action('save_post', 'bazar_clear_footer_cache_on_post_status_change');

/**
 * Limpar cache quando taxonomias são atualizadas
 */
function bazar_clear_footer_taxonomies_cache() {
    $cache_group = 'bazar_footer_menu';
    // Limpar cache antigo e novo (manter histórico para evitar dados defasados após deploy)
    wp_cache_delete('footer_taxonomies_menu', $cache_group);
    wp_cache_delete('footer_taxonomies_menu_v2', $cache_group);
    wp_cache_delete('footer_taxonomies_menu_v3', $cache_group);
}

/**
 * Limpar cache quando status de posts são alterados (trash, untrash, save)
 */
function bazar_clear_footer_cache_on_post_status_change() {
    // Usar a mesma função de limpeza de cache
    bazar_clear_footer_taxonomies_cache();
}
?>