<?php 
global $post_data;
global $has_params;
if( $has_params && !empty($has_params) && $post_data && !empty($post_data) ) : 
?>
<div class="tags-list">
    <?php
    $total_filtros = 0;
    foreach( $post_data as $key => $value ) :                
        if( $value != '' && $key != 'order' && $key != 'category_fields' && $key != 'title' ) :
            
            // Tratamento especial para valor_faixa (não é taxonomia)
            if ($key === 'valor_faixa') {
                // Converte o valor para array se for string
                $faixas = is_array($value) 
                    ? $value 
                    : explode(',', $value);
                $faixas = array_map('trim', $faixas);
                $faixas = array_filter($faixas);
                
                foreach ($faixas as $faixa_string) {
                    // Formato: "min-max" (ex: "1000-2000")
                    if (strpos($faixa_string, '-') !== false) {
                        $parts = explode('-', $faixa_string);
                        if (count($parts) == 2) {
                            $min = floatval($parts[0]);
                            $max = floatval($parts[1]);
                            
                            // Formatar label
                            $label = '';
                            if ($min == 0) {
                                $label = 'Até R$' . number_format($max, 0, ',', '.');
                            } else {
                                $label = 'R$' . number_format($min, 0, ',', '.') . ' - R$' . number_format($max, 0, ',', '.');
                            }
                            
                            $total_filtros++;
                            echo '<span class="tag removable" data-tax="valor_faixa" data-value="' . esc_attr($faixa_string) . '">';
                            echo 'Valor ' . esc_html($label);
                            echo '<span class="remove-tag">×</span>';
                            echo '</span>';
                        }
                    }
                }
                continue; // Pular para próximo item
            }
            
            // Determinar nome da taxonomia
            // category não tem sufixo _filter, componente agora usa componente_filter
            $tax_name = '';
            if ($key === 'category') {
                $tax_name = $key;
            } elseif (strpos($key, '_filter') !== false) {
                // Remove o sufixo "_filter" da chave
                $tax_name = str_replace('_filter', '', $key);
            } else {
                // Pular campos que não são taxonomias
                continue;
            }
            
            $tax_obj_ = get_taxonomy($tax_name);
            
            if($tax_obj_):
                // Converte o valor para array se for string
                $values = is_array($value) 
                  ? $value 
                  : explode(',', $value);
                $values = array_map('trim', $values);
                $values = array_filter($values); // Remove valores vazios
                
                foreach( $values as $single_value ) :
                    $display_name = '';
                    $tag_value = $single_value;
                    
                    // Tratamento especial para componentes (formato hierárquico: parent-child)
                    if($tax_name === 'componente') {
                        // Formato esperado: "parent-child" (ex: "aro-29", "quadro-19", "cambio-traseiro-10v")
                        // Aceitar também ":" para compatibilidade temporária
                        $separator = (strpos($single_value, '-') !== false) ? '-' : ':';
                        if (strpos($single_value, $separator) !== false) {
                            // Dividir por separador, mas tentar encontrar o parent correto
                            // Pode haver múltiplos hífens (ex: "cambio-traseiro-10v")
                            $parts = explode($separator, $single_value);
                            
                            // Tentar encontrar o parent começando do início
                            // Ex: "cambio-traseiro-10v" -> tentar "cambio", depois "cambio-traseiro"
                            $parent_term = null;
                            $child_slug = '';
                            
                            for ($i = 1; $i < count($parts); $i++) {
                                $parent_slug = implode($separator, array_slice($parts, 0, $i));
                                $child_slug = implode($separator, array_slice($parts, $i));
                                
                                $parent_term = get_term_by('slug', $parent_slug, 'componente');
                                if ($parent_term && !is_wp_error($parent_term) && $parent_term->parent == 0) {
                                    // Encontrou parent válido, verificar se child existe
                                    $child_terms = get_terms(array(
                                        'taxonomy' => 'componente',
                                        'parent' => $parent_term->term_id,
                                        'hide_empty' => false,
                                        'number' => 0
                                    ));
                                    
                                    if (!is_wp_error($child_terms) && !empty($child_terms)) {
                                        foreach ($child_terms as $term) {
                                            if ($term->slug === $child_slug) {
                                                $display_name = esc_html($parent_term->name . ' ' . $term->name);
                                                $tag_value = $single_value; // Manter formato hierárquico
                                                break 2; // Sair dos dois loops
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Se não encontrou no formato hierárquico, tentar buscar termo simples (fallback)
                        if (empty($display_name)) {
                            $term = get_term_by('slug', $single_value, 'componente');
                            if ($term && !is_wp_error($term)) {
                                if ($term->parent !== 0) {
                                    $parent_term = get_term($term->parent, 'componente');
                                    if ($parent_term && !is_wp_error($parent_term)) {
                                        $display_name = esc_html($parent_term->name . ' ' . $term->name);
                                    } else {
                                        $display_name = esc_html($term->name);
                                    }
                                } else {
                                    $display_name = esc_html($term->name);
                                }
                            }
                        }
                    } else {
                        // Para outras taxonomias, buscar termo normalmente
                        $term = get_term_by('slug', $single_value, $tax_name);
                        if ($term && !is_wp_error($term)) {
                            if ($tax_name === 'marca-modelo') {
                                $display_name = 'Marca ' . esc_html($term->name);
                            } elseif ($tax_name === 'category') {
                                $display_name = 'Categoria ' . esc_html($term->name);
                            } else {
                                $display_name = esc_html($tax_obj_->labels->name . ' ' . $term->name);
                            }
                        }
                    }
                    
                    // Exibir tag apenas se encontrou o termo
                    if (!empty($display_name)) {
                        $total_filtros++;
                        echo '<span class="tag removable" data-tax="' . esc_attr($tax_name) . '" data-value="' . esc_attr($tag_value) . '">';
                        echo $display_name;
                        echo '<span class="remove-tag">×</span>';
                        echo '</span>';
                    }
                endforeach;
            endif;        
        endif;
    endforeach;
    if( $total_filtros >= 2 ) :
    ?>    
    <span class="tag clear-search">
    <i class="fas fa-filter" style="opacity: .2;"></i>
        <span class="remove-tag">
          <i class="fas fa-undo"></i>
        </span>
    </span>
    <?php endif; ?>
</div>
<?php endif; ?>