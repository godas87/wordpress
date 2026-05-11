<?php
/**
 * Versão otimizada: Busca faixas de valor reutilizando query existente
 * 
 * Extrai valores dos posts da query fornecida e calcula faixas dinâmicas
 * baseadas nos valores mínimos e máximos encontrados.
 * Se não houver query válida, retorna array vazio.
 * 
 * @param WP_Query|null $query Query com posts (opcional, usa global $index_query se disponível)
 * @return array Array de faixas de valor com min, max e label (vazio se não houver query)
 */
if (!function_exists('bazar_get_faixas_valor_optimized')) {
    function bazar_get_faixas_valor_optimized($query = null) {
        
        global $index_query;
        
        // Usar global se não fornecido
        if ($query === null) {
            $query = $index_query;
        }
        
        // Se não há query válida, retornar vazio
        if (!$query || !$query instanceof WP_Query || !$query->have_posts()) {
            return array();
        }
        
        // Extrair valores dos posts da query
        $valores = array();
        foreach ($query->posts as $post) {
            $valor = get_field('valor', $post->ID);
            if ($valor) {
                $valor_float = floatval($valor);
                if ($valor_float > 0) {
                    $valores[] = $valor_float;
                }
            }
        }
        
        // Se não há valores, retornar vazio
        if (empty($valores)) {
            return array();
        }
        
        // Calcular min e max dos valores encontrados
        $min_valor = min($valores);
        $max_valor = max($valores);
        
        // Gerar faixas dinâmicas baseadas nos valores reais
        return bazar_generate_faixas_valor_dynamic($min_valor, $max_valor);
    }
}

/**
 * Gera faixas de valor dinâmicas baseadas nos valores mínimos e máximos encontrados
 * 
 * Cria faixas proporcionais aos valores reais, usando intervalos "redondos"
 * 
 * @param float $min_valor Valor mínimo encontrado
 * @param float $max_valor Valor máximo encontrado
 * @return array Array de faixas de valor com min, max e label
 */
if (!function_exists('bazar_generate_faixas_valor_dynamic')) {
    function bazar_generate_faixas_valor_dynamic($min_valor, $max_valor) {
        $faixas_valor = array();
        
        // Arredondar valores para facilitar
        $min_valor = floor($min_valor);
        $max_valor = ceil($max_valor);
        
        // Se min e max são muito próximos ou iguais, criar apenas uma faixa
        if ($max_valor - $min_valor <= 100) {
            $faixas_valor[0] = array(
                'min' => 0,
                'max' => $max_valor,
                'label' => 'Até R$' . number_format($max_valor, 0, ',', '.')
            );
            return $faixas_valor;
        }
        
        // Calcular range e número ideal de faixas (4-6 faixas)
        $range = $max_valor - $min_valor;
        $num_faixas = 5;
        
        // Calcular tamanho de cada faixa
        $tamanho_faixa = $range / $num_faixas;
        
        // Arredondar tamanho da faixa para um valor "redondo"
        $tamanho_faixa_arredondado = bazar_round_to_nice_number($tamanho_faixa);
        
        // Garantir que o tamanho não seja muito pequeno
        if ($tamanho_faixa_arredondado < 100) {
            $tamanho_faixa_arredondado = 100;
        }
        
        // Ajustar min para começar em valor "redondo" (múltiplo do tamanho da faixa)
        $min_arredondado = floor($min_valor / $tamanho_faixa_arredondado) * $tamanho_faixa_arredondado;
        if ($min_arredondado < 0) {
            $min_arredondado = 0;
        }
        
        // Gerar faixas
        $current_min = $min_arredondado;
        $key = 0;
        $max_faixas = 6;
        
        while ($current_min < $max_valor && $key < $max_faixas) {
            $current_max = $current_min + $tamanho_faixa_arredondado;
            
            // Se for a última faixa ou próxima do máximo, ajustar para cobrir o max_valor
            if ($key == $max_faixas - 1 || $current_max >= $max_valor) {
                $current_max = $max_valor;
            }
            
            // Criar label
            $label = '';
            if ($current_min == 0) {
                $label = 'Até R$' . number_format($current_max, 0, ',', '.');
            } elseif ($current_max >= $max_valor) {
                $label = 'Mais de R$' . number_format($current_min, 0, ',', '.');
            } else {
                $label = 'R$' . number_format($current_min, 0, ',', '.') . ' - R$' . number_format($current_max, 0, ',', '.');
            }
            
            $faixas_valor[$key] = array(
                'min' => $current_min,
                'max' => $current_max,
                'label' => $label
            );
            
            $current_min = $current_max;
            $key++;
            
            // Se já cobrimos o máximo, parar
            if ($current_min >= $max_valor) {
                break;
            }
        }
        
        return $faixas_valor;
    }
}

/**
 * Arredonda número para um valor "redondo" (múltiplo de 100, 500, 1000, etc)
 * 
 * @param float $number Número para arredondar
 * @return int Número arredondado
 */
if (!function_exists('bazar_round_to_nice_number')) {
    function bazar_round_to_nice_number($number) {
        if ($number <= 0) {
            return 100;
        }
        
        // Calcular ordem de grandeza
        $magnitude = floor(log10($number));
        $base = pow(10, $magnitude);
        
        // Arredondar para múltiplos de 1, 2, 5, 10, 20, 50, 100, etc
        $normalized = $number / $base;
        
        if ($normalized <= 1) {
            return $base;
        } elseif ($normalized <= 2) {
            return 2 * $base;
        } elseif ($normalized <= 5) {
            return 5 * $base;
        } else {
            return 10 * $base;
        }
    }
}

function pluralize_resultados($count) {
    return $count == 1 ? 'resultado' : 'resultados';
} 