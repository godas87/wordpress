<?php
/**
 * Script para normalizar campos ACF para taxonomias
 * 
 * Converte meta-fields ACF antigos para taxonomias:
 * - ACF 'conservacao' -> TAXONOMIA 'conservacao'
 * - ACF 'genero' -> TAXONOMIA 'genero'
 * - ACF 'grupo_de_idade' -> TAXONOMIA 'idade'
 * 
 * IMPORTANTE: Execute este script apenas UMA VEZ através do painel do WordPress
 * ou via WP-CLI.
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Função para normalizar valores booleanos dos campos ACF
 * Converte 'sim'/'nao' para 'true'/'false'
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_acf_boolean_fields($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_acf_boolean_fields_normalized')) {
        return array(
            'success' => true,
            'message' => 'Normalização de campos booleanos ACF já foi executada anteriormente.',
            'stats' => array()
        );
    }

    $stats = array(
        'posts_processed' => 0,
        'posts_updated' => 0,
        'nota_fiscal_updated' => 0,
        'exibir_contato_updated' => 0,
        'errors' => 0,
        'details' => array()
    );

    // Campos booleanos que precisam ser normalizados
    $boolean_fields = array('nota_fiscal', 'exibir_contato');

    // Buscar todos os posts que têm pelo menos um dos campos booleanos
    // ACF pode armazenar campos de duas formas:
    // 1. Com o nome do campo diretamente (meta_key = 'nota_fiscal')
    // 2. Com field keys (meta_key = 'field_xxxxx')
    global $wpdb;
    
    // Buscar posts que têm os campos pelos nomes
    $post_ids_by_name = array();
    if (!empty($boolean_fields) && isset($wpdb) && is_object($wpdb)) {
        // Usar $wpdb->prepare() com placeholders
        $placeholders = implode(',', array_fill(0, count($boolean_fields), '%s'));
        $query = $wpdb->prepare(
            "SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key IN ($placeholders)",
            $boolean_fields[0],
            isset($boolean_fields[1]) ? $boolean_fields[1] : $boolean_fields[0]
        );
        
        $post_ids_by_name = $wpdb->get_col($query);
        
        if (!is_array($post_ids_by_name)) {
            $post_ids_by_name = array();
        }
    }
    
    // Se ACF estiver ativo, também buscar pelos field keys conhecidos
    // Field keys para nota_fiscal e exibir_contato (baseado no código encontrado)
    $acf_field_keys = array(
        'field_60342ebe1bca4', // nota_fiscal
        'field_60342f329a1b1'  // exibir_contato
    );
    
    $post_ids_by_key = array();
    if (function_exists('get_field') && !empty($acf_field_keys) && isset($wpdb) && is_object($wpdb)) {
        // Usar $wpdb->prepare() com placeholders
        $key_placeholders = implode(',', array_fill(0, count($acf_field_keys), '%s'));
        $query = $wpdb->prepare(
            "SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key IN ($key_placeholders)",
            $acf_field_keys[0],
            $acf_field_keys[1]
        );
        
        $post_ids_by_key = $wpdb->get_col($query);
        
        if (!is_array($post_ids_by_key)) {
            $post_ids_by_key = array();
        }
    }
    
    // Combinar e remover duplicatas
    $post_ids = array_unique(array_merge($post_ids_by_name, $post_ids_by_key));

    if (empty($post_ids)) {
        return array(
            'success' => true,
            'message' => 'Nenhum post encontrado com campos booleanos ACF para normalizar.',
            'stats' => $stats
        );
    }

    $stats['posts_processed'] = count($post_ids);

    // Processar cada post
    foreach ($post_ids as $post_id) {
        $post_updated = false;
        $post_details = array(
            'post_id' => $post_id,
            'fields_updated' => array(),
            'errors' => array()
        );

        // Processar cada campo booleano
        foreach ($boolean_fields as $field_name) {
            // Buscar valor atual do campo ACF
            if (function_exists('get_field')) {
                $current_value = get_field($field_name, $post_id);
            } else {
                $current_value = get_post_meta($post_id, $field_name, true);
            }
            
            // Se já for boolean true ou false, pular
            if (is_bool($current_value)) {
                continue;
            }
            
            // Se for array, pegar o primeiro elemento
            if (is_array($current_value) && !empty($current_value)) {
                $value = $current_value[0];
                
                // Se array[0] == '' || '0' || 'false' → false
                if ($value === '' || $value === '0' || $value === 'false' || $value === 0 || $value === false) {
                    $new_value = false;
                }
                // Se array[0] != '' && (array[0] == '1' || 'true') → true
                elseif ($value !== '' && ($value === '1' || $value === 'true' || $value === 1 || $value === true)) {
                    $new_value = true;
                }
                // Caso padrão: false
                else {
                    $new_value = false;
                }
            }
            // Se não for array
            else {
                $value = $current_value;
                
                // Se value == '' || '0' || 'false' → false
                if ($value === '' || $value === '0' || $value === 'false' || $value === null || $value === 0 || $value === false) {
                    $new_value = false;
                }
                // Se value != '' && (value == '1' || 'true') → true
                elseif ($value !== '' && ($value === '1' || $value === 'true' || $value === 1 || $value === true)) {
                    $new_value = true;
                }
                // Caso padrão: false
                else {
                    $new_value = false;
                }
            }
            
            // Garantir que seja sempre boolean
            $new_value = (bool) $new_value;
            
            // LIMPAR TODOS OS VALORES ANTIGOS
            global $wpdb;
            
            // Deletar todas as entradas do campo pelo nome (pode haver múltiplas)
            $wpdb->delete(
                $wpdb->postmeta,
                array(
                    'post_id' => $post_id,
                    'meta_key' => $field_name
                ),
                array('%d', '%s')
            );
            
            // Deletar todos os field keys (antigos e novos) para limpar completamente
            $all_field_keys = array(
                'field_60342ebe1bca4', // nota_fiscal novo
                'field_60342f329a1b1', // exibir_contato novo
                'field_67d5b268367e2', // nota_fiscal antigo
                'field_67d5b2683a3c1'  // exibir_contato antigo
            );
            foreach ($all_field_keys as $field_key) {
                $wpdb->delete(
                    $wpdb->postmeta,
                    array(
                        'post_id' => $post_id,
                        'meta_key' => $field_key
                    ),
                    array('%d', '%s')
                );
            }
            
            // SALVAR NOVO VALOR COMO BOOLEAN
            // Salvar apenas pelo nome do campo (não salvar field keys)
            add_post_meta($post_id, $field_name, $new_value, true);
            
            // Atualizar via ACF se disponível (apenas pelo nome do campo)
            if (function_exists('update_field')) {
                update_field($field_name, $new_value, $post_id);
            }
            
            $new_value_display = $new_value ? 'true' : 'false';
            $post_details['fields_updated'][] = $field_name . ': "' . var_export($current_value, true) . '" → ' . $new_value_display;
            
            // Incrementar contador específico
            if ($field_name === 'nota_fiscal') {
                $stats['nota_fiscal_updated']++;
            } elseif ($field_name === 'exibir_contato') {
                $stats['exibir_contato_updated']++;
            }
            
            $post_updated = true;
        }

        if ($post_updated) {
            $stats['posts_updated']++;
            $stats['details'][] = $post_details;
        }
    }

    // Marcar como executado
    update_option('bazar_acf_boolean_fields_normalized', true);
    update_option('bazar_acf_boolean_fields_normalized_date', current_time('mysql'));
    update_option('bazar_acf_boolean_fields_normalized_stats', $stats);

    return array(
        'success' => true,
        'message' => 'Normalização de campos booleanos ACF concluída com sucesso!',
        'stats' => $stats
    );
}

/**
 * Função para normalizar o campo ACF 'peso'
 * Remove zeros à direita da vírgula, mas mantém zeros significativos
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_acf_peso_field($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_acf_peso_field_normalized')) {
        return array(
            'success' => true,
            'message' => 'Normalização do campo peso ACF já foi executada anteriormente.',
            'stats' => array()
        );
    }

    $stats = array(
        'posts_processed' => 0,
        'posts_updated' => 0,
        'peso_updated' => 0,
        'errors' => 0,
        'details' => array()
    );

    // Buscar todos os posts que têm o campo 'peso'
    global $wpdb;
    
    $post_ids = array();
    if (isset($wpdb) && is_object($wpdb)) {
        $query = $wpdb->prepare(
            "SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s",
            'peso'
        );
        
        $post_ids = $wpdb->get_col($query);
        
        if (!is_array($post_ids)) {
            $post_ids = array();
        }
    }

    if (empty($post_ids)) {
        return array(
            'success' => true,
            'message' => 'Nenhum post encontrado com campo peso ACF para normalizar.',
            'stats' => $stats
        );
    }

    $stats['posts_processed'] = count($post_ids);

    // Processar cada post
    foreach ($post_ids as $post_id) {
        $post_updated = false;
        $post_details = array(
            'post_id' => $post_id,
            'peso_old' => '',
            'peso_new' => '',
            'errors' => array()
        );

        // Buscar valor atual do campo ACF
        if (function_exists('get_field')) {
            $current_value = get_field('peso', $post_id);
        } else {
            $current_value = get_post_meta($post_id, 'peso', true);
        }
        
        // Se o campo não existe ou está vazio, pular
        if (empty($current_value) && $current_value !== '0' && $current_value !== 0 && $current_value !== '0,0' && $current_value !== '0,00') {
            continue;
        }
        
        // Converter para string para processar
        $peso_string = (string) $current_value;
        $post_details['peso_old'] = $peso_string;
        
        // Normalizar: remover zeros à direita da vírgula, mas manter zeros significativos
        // Exemplos:
        // 12,500 -> 12,5
        // 12,000 -> 12
        // 12,120 -> 12,120 (mantém)
        // 0,500 -> 0,500 (mantém porque começa com zero)
        
        // Converter ponto para vírgula se necessário (padronizar formato)
        $peso_string = str_replace('.', ',', $peso_string);
        
        // Verificar se tem vírgula
        if (strpos($peso_string, ',') !== false) {
            // Separar parte inteira e decimal
            $parts = explode(',', $peso_string);
            $inteira = $parts[0];
            $decimal = isset($parts[1]) ? $parts[1] : '';
            
            // Se começa com zero (0,xxx), manter todos os zeros significativos
            if ($inteira === '0' || $inteira === '') {
                // Manter como está (0,500 -> 0,500)
                $peso_normalizado = $peso_string;
            } else {
                // Remover zeros à direita da parte decimal
                $decimal_limpa = rtrim($decimal, '0');
                
                // Se sobrou algo na parte decimal, manter a vírgula
                if (!empty($decimal_limpa)) {
                    $peso_normalizado = $inteira . ',' . $decimal_limpa;
                } else {
                    // Se não sobrou nada, remover a vírgula (12,000 -> 12)
                    $peso_normalizado = $inteira;
                }
            }
        } else {
            // Se não tem vírgula, manter como está
            $peso_normalizado = $peso_string;
        }
        
        $post_details['peso_new'] = $peso_normalizado;
        
        // Se o valor mudou, atualizar
        if ($peso_string !== $peso_normalizado) {
            // Atualizar via ACF se disponível
            if (function_exists('update_field')) {
                update_field('peso', $peso_normalizado, $post_id);
            }
            
            // Atualizar via post_meta também
            update_post_meta($post_id, 'peso', $peso_normalizado);
            
            $stats['peso_updated']++;
            $post_updated = true;
        }
        
        if ($post_updated) {
            $stats['posts_updated']++;
            $stats['details'][] = $post_details;
        }
    }

    // Marcar como executado
    update_option('bazar_acf_peso_field_normalized', true);
    update_option('bazar_acf_peso_field_normalized_date', current_time('mysql'));
    update_option('bazar_acf_peso_field_normalized_stats', $stats);

    return array(
        'success' => true,
        'message' => 'Normalização do campo peso ACF concluída com sucesso!',
        'stats' => $stats
    );
}

/**
 * Função para normalizar o campo meta 'simple_rating'
 * Converte formato antigo (array com valores separados por ";") para valor único (média arredondada)
 * Exemplo: "5;5;5;5" → 5, "4;5;4" → 4, "3;4;5" → 4
 * 
 * @param bool $force Forçar execução mesmo se já foi executado
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_simple_rating_field($force = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_simple_rating_field_normalized')) {
        return array(
            'success' => true,
            'message' => 'Normalização do campo simple_rating já foi executada anteriormente.',
            'stats' => array()
        );
    }

    $stats = array(
        'posts_processed' => 0,
        'posts_updated' => 0,
        'rating_updated' => 0,
        'errors' => 0,
        'details' => array()
    );

    // Buscar todos os posts que têm o campo 'simple_rating'
    global $wpdb;
    
    $post_ids = array();
    if (isset($wpdb) && is_object($wpdb)) {
        $query = $wpdb->prepare(
            "SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s",
            'simple_rating'
        );
        
        $post_ids = $wpdb->get_col($query);
        
        if (!is_array($post_ids)) {
            $post_ids = array();
        }
    }

    if (empty($post_ids)) {
        return array(
            'success' => true,
            'message' => 'Nenhum post encontrado com campo simple_rating para normalizar.',
            'stats' => $stats
        );
    }

    $stats['posts_processed'] = count($post_ids);

    // Processar cada post
    foreach ($post_ids as $post_id) {
        $post_updated = false;
        $post_details = array(
            'post_id' => $post_id,
            'rating_old' => '',
            'rating_new' => '',
            'errors' => array()
        );

        // Buscar valor atual do campo
        $current_value = get_post_meta($post_id, 'simple_rating', true);
        
        // Se o campo não existe ou está vazio, pular
        if (empty($current_value) && $current_value !== '0' && $current_value !== 0 && $current_value !== '') {
            continue;
        }
        
        // Converter para string para exibição
        if (is_array($current_value)) {
            $post_details['rating_old'] = implode(';', $current_value);
        } else {
            $post_details['rating_old'] = (string) $current_value;
        }
        
        // Verificar se já está no formato novo (valor único inteiro entre 1-5)
        // Se for string sem ";" e for número válido, verificar se já está normalizado
        if (is_string($current_value) && strpos($current_value, ';') === false) {
            $int_value = intval($current_value);
            if ($int_value >= 1 && $int_value <= 5 && (string) $int_value === trim($current_value)) {
                // Já está normalizado (ex: "5" ou 5), pular
                continue;
            }
        }
        
        // Se for número direto (não string) e estiver entre 1-5, já está normalizado
        if (is_numeric($current_value) && !is_string($current_value)) {
            $int_value = intval($current_value);
            if ($int_value >= 1 && $int_value <= 5) {
                // Já está normalizado, pular
                continue;
            }
        }
        
        // Formato antigo: string com valores separados por ";"
        // Exemplo: "5;5;5;5" ou "4;5;4"
        $values = array();
        
        if (is_string($current_value) && strpos($current_value, ';') !== false) {
            // Separar por ponto e vírgula
            $values = explode(';', $current_value);
        } elseif (is_array($current_value)) {
            // Se já for array, usar diretamente
            $values = $current_value;
        } else {
            // Tentar converter para array
            $values = array($current_value);
        }
        
        // Filtrar valores vazios e converter para inteiros
        $values = array_filter($values, function($v) {
            return !empty($v) && is_numeric($v);
        });
        
        $values = array_map('intval', $values);
        
        // Filtrar valores válidos (1-5)
        $values = array_filter($values, function($v) {
            return $v >= 1 && $v <= 5;
        });
        
        // Se não houver valores válidos, usar valor padrão
        if (empty($values)) {
            $new_rating = 5; // Valor padrão
        } else {
            // Calcular média e arredondar para inteiro
            $average = array_sum($values) / count($values);
            $new_rating = round($average);
            
            // Garantir que está entre 1-5
            if ($new_rating < 1) {
                $new_rating = 1;
            } elseif ($new_rating > 5) {
                $new_rating = 5;
            }
        }
        
        $post_details['rating_new'] = (string) $new_rating;
        
        // Atualizar o campo com o novo valor único
        update_post_meta($post_id, 'simple_rating', $new_rating);
        
        $stats['rating_updated']++;
        $post_updated = true;
        
        if ($post_updated) {
            $stats['posts_updated']++;
            $stats['details'][] = $post_details;
        }
    }

    // Marcar como executado
    update_option('bazar_simple_rating_field_normalized', true);
    update_option('bazar_simple_rating_field_normalized_date', current_time('mysql'));
    update_option('bazar_simple_rating_field_normalized_stats', $stats);

    return array(
        'success' => true,
        'message' => 'Normalização do campo simple_rating concluída com sucesso!',
        'stats' => $stats
    );
}

/**
 * Normaliza o gênero do campo 'conservacao'
 * Nova || Novo => Novo(a)
 * Usada || Usado => Usado(a)
 * Seminova || Seminovo => Seminovo(a)
 * 
 * @param string $value Valor original
 * @return string Valor normalizado
 */
function bazar_normalize_conservacao_gender($value) {
    // Mapeamento de normalização (case-insensitive)
    $normalization_map = array(
        'nova' => 'Novo',
        'novo' => 'Novo',
        'novoa' => 'Novo',
        'novo-a' => 'Novo',
        'usada' => 'Usado',
        'usado' => 'Usado',
        'usadoa' => 'Usado',
        'usado-a' => 'Usado',
        'seminova' => 'Seminovo',
        'seminovo' => 'Seminovo'
    );
    
    // Converter para lowercase para comparação
    $value_lower = mb_strtolower(trim($value), 'UTF-8');
    
    // Verificar se existe no mapeamento
    if (isset($normalization_map[$value_lower])) {
        return $normalization_map[$value_lower];
    }
    
    // Se não encontrou, retornar valor original (pode ser que já esteja normalizado ou seja outro valor)
    return $value;
}

/**
 * Função principal para normalizar campos ACF para taxonomias
 * @param bool $force Forçar execução mesmo se já foi executado
 * @param bool $delete_acf_fields Se true, remove os campos ACF após migração
 * @return array Array com estatísticas da normalização
 */
function bazar_normalize_acf_fields_to_taxonomies($force = false, $delete_acf_fields = false) {
    
    // Verificar se já foi executado (a menos que seja forçado)
    if (!$force && get_option('bazar_acf_fields_normalized')) {
        return array(
            'success' => true,
            'message' => 'Normalização de campos ACF já foi executada anteriormente.',
            'stats' => array()
        );
    }

    $stats = array(
        'posts_processed' => 0,
        'posts_updated' => 0,
        'conservacao_migrated' => 0,
        'genero_migrated' => 0,
        'idade_migrated' => 0,
        'terms_created' => 0,
        'errors' => 0,
        'details' => array()
    );

    // Mapeamento de campos ACF para taxonomias
    $field_mapping = array(
        'conservacao' => 'conservacao',
        'genero' => 'genero',
        'grupo_de_idade' => 'idade'
    );

    // Buscar todos os posts que têm pelo menos um dos campos ACF
    global $wpdb;
    
    $meta_keys = array_keys($field_mapping);
    
    // Construir query usando $wpdb->prepare()
    $post_ids = array();
    if (!empty($meta_keys) && isset($wpdb) && is_object($wpdb)) {
        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $query = $wpdb->prepare(
            "SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key IN ($placeholders)",
            $meta_keys[0],
            isset($meta_keys[1]) ? $meta_keys[1] : $meta_keys[0],
            isset($meta_keys[2]) ? $meta_keys[2] : $meta_keys[0]
        );
        
        $post_ids = $wpdb->get_col($query);
        
        if (!is_array($post_ids)) {
            $post_ids = array();
        }
    }

    if (empty($post_ids)) {
        return array(
            'success' => true,
            'message' => 'Nenhum post encontrado com campos ACF para normalizar.',
            'stats' => $stats
        );
    }

    $stats['posts_processed'] = count($post_ids);

    // Processar cada post
    foreach ($post_ids as $post_id) {
        $post_updated = false;
        $post_details = array(
            'post_id' => $post_id,
            'fields_migrated' => array(),
            'errors' => array()
        );

        // Processar cada campo ACF
        foreach ($field_mapping as $acf_field => $taxonomy) {
            // Buscar valor do campo ACF
            $acf_value = get_post_meta($post_id, $acf_field, true);
            
            // Se o campo ACF não existe ou está vazio, pular
            if (empty($acf_value)) {
                continue;
            }

            // Normalizar o valor (pode ser string ou array)
            $values = is_array($acf_value) ? $acf_value : array($acf_value);
            
            $term_ids = array();
            
            foreach ($values as $value) {
                // Limpar o valor
                $value = trim($value);
                
                if (empty($value)) {
                    continue;
                }

                // Normalizar gênero para campo 'conservacao'
                if ($acf_field === 'conservacao') {
                    $value = bazar_normalize_conservacao_gender($value);
                }

                // Buscar termo existente na taxonomia pelo nome
                $term = get_term_by('name', $value, $taxonomy);
                
                if (!$term || is_wp_error($term)) {
                    // Termo não existe, criar novo
                    $insert_result = wp_insert_term(
                        $value,
                        $taxonomy
                    );
                    
                    if (is_wp_error($insert_result)) {
                        $post_details['errors'][] = "Erro ao criar termo '$value' na taxonomia '$taxonomy': " . $insert_result->get_error_message();
                        $stats['errors']++;
                        error_log('[Bazar Normalize ACF] Erro ao criar termo "' . $value . '" na taxonomia "' . $taxonomy . '" para post ID ' . $post_id . ': ' . $insert_result->get_error_message());
                        continue;
                    }
                    
                    $term_id = $insert_result['term_id'];
                    $stats['terms_created']++;
                    error_log('[Bazar Normalize ACF] Termo criado: "' . $value . '" (ID: ' . $term_id . ') na taxonomia "' . $taxonomy . '"');
                } else {
                    $term_id = $term->term_id;
                }
                
                $term_ids[] = $term_id;
            }

            // Se encontrou termos, associar ao post
            if (!empty($term_ids)) {
                // Remover duplicatas
                $term_ids = array_unique($term_ids);
                
                // Associar termos ao post
                $set_result = wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
                
                if (is_wp_error($set_result)) {
                    $post_details['errors'][] = "Erro ao associar termos da taxonomia '$taxonomy' ao post: " . $set_result->get_error_message();
                    $stats['errors']++;
                    error_log('[Bazar Normalize ACF] Erro ao associar termos da taxonomia "' . $taxonomy . '" ao post ID ' . $post_id . ': ' . $set_result->get_error_message());
                } else {
                    // Marcar campo como migrado
                    $post_details['fields_migrated'][] = $acf_field . ' -> ' . $taxonomy;
                    
                    // Incrementar contador específico
                    switch ($acf_field) {
                        case 'conservacao':
                            $stats['conservacao_migrated']++;
                            break;
                        case 'genero':
                            $stats['genero_migrated']++;
                            break;
                        case 'grupo_de_idade':
                            $stats['idade_migrated']++;
                            break;
                    }
                    
                    // Remover campo ACF se solicitado
                    if ($delete_acf_fields) {
                        delete_post_meta($post_id, $acf_field);
                        error_log('[Bazar Normalize ACF] Campo ACF "' . $acf_field . '" removido do post ID ' . $post_id);
                    }
                    
                    $post_updated = true;
                }
            }
        }

        if ($post_updated) {
            $stats['posts_updated']++;
            $stats['details'][] = $post_details;
        }
    }

    // Marcar como executado
    update_option('bazar_acf_fields_normalized', true);
    update_option('bazar_acf_fields_normalized_date', current_time('mysql'));
    update_option('bazar_acf_fields_normalized_stats', $stats);
    if ($delete_acf_fields) {
        update_option('bazar_acf_fields_deleted', true);
    }

    return array(
        'success' => true,
        'message' => 'Normalização de campos ACF concluída com sucesso!',
        'stats' => $stats
    );
}

// Adicionar página no menu admin para executar o script
function bazar_add_normalize_acf_menu() {
    add_management_page(
        'Normalizar Campos ACF',
        'Normalizar ACF',
        'manage_options',
        'bazar-normalize-acf',
        'bazar_normalize_acf_page'
    );
}
add_action('admin_menu', 'bazar_add_normalize_acf_menu');

// Página de administração para normalizar
function bazar_normalize_acf_page() {
    $already_normalized = get_option('bazar_acf_fields_normalized');
    $normalized_date = get_option('bazar_acf_fields_normalized_date');
    $acf_fields_deleted = get_option('bazar_acf_fields_deleted');
    $last_stats = get_option('bazar_acf_fields_normalized_stats');
    
    $boolean_normalized = get_option('bazar_acf_boolean_fields_normalized');
    $boolean_normalized_date = get_option('bazar_acf_boolean_fields_normalized_date');
    $boolean_last_stats = get_option('bazar_acf_boolean_fields_normalized_stats');
    
    $peso_normalized = get_option('bazar_acf_peso_field_normalized');
    $peso_normalized_date = get_option('bazar_acf_peso_field_normalized_date');
    $peso_last_stats = get_option('bazar_acf_peso_field_normalized_stats');
    
    $rating_normalized = get_option('bazar_simple_rating_field_normalized');
    $rating_normalized_date = get_option('bazar_simple_rating_field_normalized_date');
    $rating_last_stats = get_option('bazar_simple_rating_field_normalized_stats');
    ?>
    <div class="wrap">
        <h1>Normalizar Campos ACF</h1>
        
        <?php
        // Executar normalização do campo simple_rating
        if (isset($_POST['bazar_execute_normalize_rating']) && check_admin_referer('bazar_normalize_acf_rating_action')) {
            $force = isset($_POST['force_rating']) && $_POST['force_rating'] == '1';
            
            $result = bazar_normalize_simple_rating_field($force);
            
            $class = $result['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
            
            if (isset($result['stats'])) {
                $stats = $result['stats'];
                echo '<div class="notice notice-info"><p>';
                echo '<strong>Estatísticas:</strong><br>';
                echo 'Posts processados: ' . $stats['posts_processed'] . '<br>';
                echo 'Posts atualizados: ' . $stats['posts_updated'] . '<br>';
                echo 'Rating atualizado: ' . $stats['rating_updated'] . '<br>';
                if ($stats['errors'] > 0) {
                    echo 'Erros: ' . $stats['errors'] . '<br>';
                }
                echo '</p></div>';
                
                // Mostrar detalhes se houver
                if (!empty($stats['details']) && count($stats['details']) <= 50) {
                    echo '<div class="card"><h3>Detalhes das Atualizações</h3>';
                    echo '<table class="widefat">';
                    echo '<thead><tr><th>Post ID</th><th>Rating Antigo</th><th>Rating Novo</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($stats['details'] as $detail) {
                        echo '<tr>';
                        echo '<td><a href="' . get_edit_post_link($detail['post_id']) . '" target="_blank">' . $detail['post_id'] . '</a></td>';
                        echo '<td>' . esc_html($detail['rating_old']) . '</td>';
                        echo '<td><strong>' . esc_html($detail['rating_new']) . '</strong></td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                } elseif (!empty($stats['details'])) {
                    echo '<div class="notice notice-info"><p>Total de ' . count($stats['details']) . ' posts atualizados. Detalhes completos disponíveis no log do WordPress.</p></div>';
                }
            }
        }
        
        // Executar normalização do campo peso
        if (isset($_POST['bazar_execute_normalize_peso']) && check_admin_referer('bazar_normalize_acf_peso_action')) {
            $force = isset($_POST['force_peso']) && $_POST['force_peso'] == '1';
            
            $result = bazar_normalize_acf_peso_field($force);
            
            $class = $result['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
            
            if (isset($result['stats'])) {
                $stats = $result['stats'];
                echo '<div class="notice notice-info"><p>';
                echo '<strong>Estatísticas:</strong><br>';
                echo 'Posts processados: ' . $stats['posts_processed'] . '<br>';
                echo 'Posts atualizados: ' . $stats['posts_updated'] . '<br>';
                echo 'Peso atualizado: ' . $stats['peso_updated'] . '<br>';
                if ($stats['errors'] > 0) {
                    echo 'Erros: ' . $stats['errors'] . '<br>';
                }
                echo '</p></div>';
                
                // Mostrar detalhes se houver
                if (!empty($stats['details']) && count($stats['details']) <= 50) {
                    echo '<div class="card"><h3>Detalhes das Atualizações</h3>';
                    echo '<table class="widefat">';
                    echo '<thead><tr><th>Post ID</th><th>Peso Antigo</th><th>Peso Novo</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($stats['details'] as $detail) {
                        echo '<tr>';
                        echo '<td><a href="' . get_edit_post_link($detail['post_id']) . '" target="_blank">' . $detail['post_id'] . '</a></td>';
                        echo '<td>' . esc_html($detail['peso_old']) . '</td>';
                        echo '<td><strong>' . esc_html($detail['peso_new']) . '</strong></td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                } elseif (!empty($stats['details'])) {
                    echo '<div class="notice notice-info"><p>Total de ' . count($stats['details']) . ' posts atualizados. Detalhes completos disponíveis no log do WordPress.</p></div>';
                }
            }
        }
        
        // Executar normalização de campos booleanos
        if (isset($_POST['bazar_execute_normalize_boolean']) && check_admin_referer('bazar_normalize_acf_boolean_action')) {
            $force = isset($_POST['force_boolean']) && $_POST['force_boolean'] == '1';
            
            $result = bazar_normalize_acf_boolean_fields($force);
            
            $class = $result['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
            
            if (isset($result['stats'])) {
                $stats = $result['stats'];
                echo '<div class="notice notice-info"><p>';
                echo '<strong>Estatísticas:</strong><br>';
                echo 'Posts processados: ' . $stats['posts_processed'] . '<br>';
                echo 'Posts atualizados: ' . $stats['posts_updated'] . '<br>';
                echo 'Nota Fiscal atualizada: ' . $stats['nota_fiscal_updated'] . '<br>';
                echo 'Exibir Contato atualizado: ' . $stats['exibir_contato_updated'] . '<br>';
                if ($stats['errors'] > 0) {
                    echo 'Erros: ' . $stats['errors'] . '<br>';
                }
                echo '</p></div>';
                
                // Mostrar detalhes se houver
                if (!empty($stats['details']) && count($stats['details']) <= 50) {
                    echo '<div class="card"><h3>Detalhes das Atualizações</h3>';
                    echo '<table class="widefat">';
                    echo '<thead><tr><th>Post ID</th><th>Campos Atualizados</th><th>Erros</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($stats['details'] as $detail) {
                        echo '<tr>';
                        echo '<td><a href="' . get_edit_post_link($detail['post_id']) . '" target="_blank">' . $detail['post_id'] . '</a></td>';
                        echo '<td>' . (empty($detail['fields_updated']) ? '-' : implode('<br>', $detail['fields_updated'])) . '</td>';
                        echo '<td>' . (empty($detail['errors']) ? '-' : '<span style="color: red;">' . implode('<br>', $detail['errors']) . '</span>') . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                } elseif (!empty($stats['details'])) {
                    echo '<div class="notice notice-info"><p>Total de ' . count($stats['details']) . ' posts atualizados. Detalhes completos disponíveis no log do WordPress.</p></div>';
                }
            }
        }
        
        // Executar normalização de taxonomias
        if (isset($_POST['bazar_execute_normalize_acf']) && check_admin_referer('bazar_normalize_acf_action')) {
            $force = isset($_POST['force']) && $_POST['force'] == '1';
            $delete_acf = isset($_POST['delete_acf_fields']) && $_POST['delete_acf_fields'] == '1';
            
            $result = bazar_normalize_acf_fields_to_taxonomies($force, $delete_acf);
            
            $class = $result['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
            
            if (isset($result['stats'])) {
                $stats = $result['stats'];
                echo '<div class="notice notice-info"><p>';
                echo '<strong>Estatísticas:</strong><br>';
                echo 'Posts processados: ' . $stats['posts_processed'] . '<br>';
                echo 'Posts atualizados: ' . $stats['posts_updated'] . '<br>';
                echo 'Conservação migrada: ' . $stats['conservacao_migrated'] . '<br>';
                echo 'Gênero migrado: ' . $stats['genero_migrated'] . '<br>';
                echo 'Idade migrada: ' . $stats['idade_migrated'] . '<br>';
                echo 'Termos criados: ' . $stats['terms_created'] . '<br>';
                if ($stats['errors'] > 0) {
                    echo 'Erros: ' . $stats['errors'] . '<br>';
                }
                echo '</p></div>';
                
                // Mostrar detalhes se houver
                if (!empty($stats['details']) && count($stats['details']) <= 50) {
                    echo '<div class="card"><h3>Detalhes das Migrações</h3>';
                    echo '<table class="widefat">';
                    echo '<thead><tr><th>Post ID</th><th>Campos Migrados</th><th>Erros</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($stats['details'] as $detail) {
                        echo '<tr>';
                        echo '<td><a href="' . get_edit_post_link($detail['post_id']) . '" target="_blank">' . $detail['post_id'] . '</a></td>';
                        echo '<td>' . (empty($detail['fields_migrated']) ? '-' : implode('<br>', $detail['fields_migrated'])) . '</td>';
                        echo '<td>' . (empty($detail['errors']) ? '-' : '<span style="color: red;">' . implode('<br>', $detail['errors']) . '</span>') . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                } elseif (!empty($stats['details'])) {
                    echo '<div class="notice notice-info"><p>Total de ' . count($stats['details']) . ' posts atualizados. Detalhes completos disponíveis no log do WordPress.</p></div>';
                }
            }
        }
        ?>
        
        <div class="card">
            <h2>1. Normalizar Campo Simple Rating</h2>
            <p>Este script normaliza o campo meta 'simple_rating' convertendo formato antigo (array com valores separados por ";") para valor único (média arredondada):</p>
            <ul>
                <li><strong>"5;5;5;5"</strong> → <strong>5</strong> (média: 5.0)</li>
                <li><strong>"4;5;4"</strong> → <strong>4</strong> (média: 4.33 → arredondado)</li>
                <li><strong>"3;4;5"</strong> → <strong>4</strong> (média: 4.0)</li>
                <li><strong>"5"</strong> → <strong>5</strong> (já normalizado, mantém)</li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca todos os posts que têm o campo 'simple_rating'</li>
                <li>Verifica se está no formato antigo (string com ";")</li>
                <li>Calcula a média dos valores e arredonda para inteiro (1-5)</li>
                <li>Atualiza o campo com o valor único normalizado</li>
            </ol>
            
            <p><strong>Importante:</strong> O sistema agora armazena apenas uma única avaliação (definida pelo administrador) em vez de múltiplas avaliações de usuários.</p>
            
            <?php if ($rating_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização do campo simple_rating já foi executada anteriormente.</p>
                    <?php if ($rating_normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($rating_normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($rating_last_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $rating_last_stats['posts_updated']; ?> posts atualizados de <?php echo $rating_last_stats['posts_processed']; ?> processados.
                            <?php echo $rating_last_stats['rating_updated']; ?> campos rating atualizados.
                        </p>
                    <?php endif; ?>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A normalização do campo simple_rating ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('bazar_normalize_acf_rating_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force_rating" value="1" <?php checked($rating_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado anteriormente)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize_rating" class="button button-primary button-large">
                        Normalizar Campo Simple Rating
                    </button>
                </p>
            </form>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>2. Normalizar Campo Peso ACF</h2>
            <p>Este script normaliza o campo ACF 'peso' removendo zeros à direita da vírgula:</p>
            <ul>
                <li><strong>12,500</strong> → <strong>12,5</strong></li>
                <li><strong>12,000</strong> → <strong>12</strong></li>
                <li><strong>12,120</strong> → <strong>12,120</strong> (mantém zeros significativos)</li>
                <li><strong>0,500</strong> → <strong>0,500</strong> (mantém quando começa com zero)</li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca todos os posts que têm o campo 'peso' ACF</li>
                <li>Remove zeros à direita da vírgula, mas mantém zeros significativos</li>
                <li>Atualiza o campo ACF com o valor normalizado</li>
            </ol>
            
            <?php if ($peso_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização do campo peso já foi executada anteriormente.</p>
                    <?php if ($peso_normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($peso_normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($peso_last_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $peso_last_stats['posts_updated']; ?> posts atualizados de <?php echo $peso_last_stats['posts_processed']; ?> processados.
                            <?php echo $peso_last_stats['peso_updated']; ?> campos peso atualizados.
                        </p>
                    <?php endif; ?>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A normalização do campo peso ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('bazar_normalize_acf_peso_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force_peso" value="1" <?php checked($peso_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado anteriormente)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize_peso" class="button button-primary button-large">
                        Normalizar Campo Peso
                    </button>
                </p>
            </form>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>3. Normalizar Campos Booleanos ACF</h2>
            <p>Este script converte valores booleanos dos campos ACF de formato antigo para novo:</p>
            <ul>
                <li><strong>ACF 'nota_fiscal'</strong>: 'sim'/'nao' → true/false (boolean)</li>
                <li><strong>ACF 'exibir_contato'</strong>: 'sim'/'nao' → true/false (boolean)</li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca todos os posts que têm os campos booleanos ACF</li>
                <li>Converte valores antigos ('sim', 'nao', '1', '0', 'true', 'false') para booleanos (true/false)</li>
                <li>Atualiza os campos ACF com os novos valores booleanos</li>
            </ol>
            
            <?php if ($boolean_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização de campos booleanos já foi executada anteriormente.</p>
                    <?php if ($boolean_normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($boolean_normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($boolean_last_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $boolean_last_stats['posts_updated']; ?> posts atualizados de <?php echo $boolean_last_stats['posts_processed']; ?> processados.
                            <?php echo $boolean_last_stats['nota_fiscal_updated']; ?> nota_fiscal, 
                            <?php echo $boolean_last_stats['exibir_contato_updated']; ?> exibir_contato atualizados.
                        </p>
                    <?php endif; ?>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A normalização de campos booleanos ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('bazar_normalize_acf_boolean_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force_boolean" value="1" <?php checked($boolean_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado anteriormente)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize_boolean" class="button button-primary button-large">
                        Normalizar Campos Booleanos
                    </button>
                </p>
            </form>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>4. Normalizar Campos ACF para Taxonomias</h2>
            <p>Este script migra campos ACF (Advanced Custom Fields) antigos para taxonomias:</p>
            <ul>
                <li><strong>ACF 'conservacao'</strong> → <strong>Taxonomia 'conservacao'</strong></li>
                <li><strong>ACF 'genero'</strong> → <strong>Taxonomia 'genero'</strong></li>
                <li><strong>ACF 'grupo_de_idade'</strong> → <strong>Taxonomia 'idade'</strong></li>
            </ul>
            
            <h3>Normalização de Gênero (Campo Conservação):</h3>
            <p>O script normaliza automaticamente os valores de conservação para formato neutro:</p>
            <ul>
                <li><strong>Nova</strong> ou <strong>Novo</strong> → <strong>Novo(a)</strong></li>
                <li><strong>Usada</strong> ou <strong>Usado</strong> → <strong>Usado(a)</strong></li>
                <li><strong>Seminova</strong> ou <strong>Seminovo</strong> → <strong>Seminovo(a)</strong></li>
            </ul>
            
            <h3>Como funciona:</h3>
            <ol>
                <li>Busca todos os posts que têm pelo menos um dos campos ACF mencionados</li>
                <li>Para cada campo ACF, busca o valor armazenado</li>
                <li><strong>Para 'conservacao':</strong> Normaliza o gênero (Nova/Novo → Novo(a), etc.)</li>
                <li>Busca o termo correspondente na taxonomia (ou cria se não existir)</li>
                <li>Associa o termo ao post usando a taxonomia</li>
                <li>Opcionalmente, remove o campo ACF antigo (se marcado)</li>
            </ol>
            
            <?php if ($already_normalized): ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização já foi executada anteriormente.</p>
                    <?php if ($normalized_date): ?>
                        <p><strong>Data:</strong> <?php echo esc_html($normalized_date); ?></p>
                    <?php endif; ?>
                    <?php if ($acf_fields_deleted): ?>
                        <p><strong>Campos ACF:</strong> Removidos após migração.</p>
                    <?php else: ?>
                        <p><strong>Campos ACF:</strong> Mantidos no banco de dados (não foram removidos).</p>
                    <?php endif; ?>
                    <?php if ($last_stats): ?>
                        <p><strong>Última execução:</strong> 
                            <?php echo $last_stats['posts_updated']; ?> posts atualizados de <?php echo $last_stats['posts_processed']; ?> processados.
                            <?php echo $last_stats['conservacao_migrated']; ?> conservação, 
                            <?php echo $last_stats['genero_migrated']; ?> gênero, 
                            <?php echo $last_stats['idade_migrated']; ?> idade migrados.
                        </p>
                    <?php endif; ?>
                    <p>Marque a opção abaixo para forçar a execução novamente.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>Status:</strong> A normalização ainda não foi executada.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('bazar_normalize_acf_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force" value="1" <?php checked($already_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado anteriormente)
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="delete_acf_fields" value="1">
                        Remover campos ACF após migração (recomendado apenas após verificar que a migração foi bem-sucedida)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize_acf" class="button button-primary button-large">
                        Executar Normalização
                    </button>
                </p>
            </form>
            
            <hr>
            <h3>Avisos Importantes</h3>
            <ul>
                <li><strong>Backup:</strong> Recomenda-se fazer backup do banco de dados antes de executar qualquer script.</li>
                <li><strong>Campos Booleanos:</strong> Os valores são convertidos diretamente nos campos ACF existentes.</li>
                <li><strong>Campos ACF (Taxonomias):</strong> Por padrão, os campos ACF antigos são mantidos no banco de dados. Marque a opção acima para removê-los após verificar que a migração foi bem-sucedida.</li>
                <li><strong>Termos:</strong> Se um termo não existir na taxonomia, ele será criado automaticamente.</li>
                <li><strong>Duplicatas:</strong> O script evita criar termos duplicados e remove duplicatas nas associações.</li>
            </ul>
        </div>
    </div>
    <?php
}
?>

