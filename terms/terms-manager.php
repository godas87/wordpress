<?php
/**
 * Classe para gerenciar termos de taxonomia de forma genérica
 * Desacoplada de objetos específicos (posts, users, etc.)
 */
class __Bazar_Terms_Manager {

    private static $debug_log = array();
    private static $error_messages = array();

    /**
     * Verifica se um termo existe, se não, cria e retorna o ID do termo
     * @param string $name Nome do termo
     * @param string $taxonomy Taxonomia
     * @param int|null $parent_id ID do termo pai
     * @param string|null $slug Slug customizado
     * @return int|false ID do termo ou false em caso de erro
     */
    public static function ensure_term(
        $name, 
        $taxonomy, 
        $parent_id = null, 
        $slug = null
    ) {

        $name = sanitize_text_field($name);
        $taxonomy = sanitize_text_field($taxonomy);
        $parent_id = intval(sanitize_text_field($parent_id));
        $slug = strtolower(sanitize_text_field($slug));
        
        // Se o nome for numérico, pode ser:
        // 1) um ID de termo válido; ou
        // 2) um nome legítimo (ex.: modelos "4500", "29", etc).
        // Só convertemos para nome quando o ID realmente existe.
        if( is_numeric( $name ) && strlen( $name ) <= 10 ) {            
            // Tentar buscar o termo pelo ID para obter o nome real
            $term_by_id = get_term( intval($name), $taxonomy );
            if( $term_by_id && !is_wp_error($term_by_id) ) {
                $name = $term_by_id->name;                
            } else {
                // Não é erro: manter valor numérico como nome textual do termo.
                $name = (string) $name;
            }
        }
        
        // IMPORTANTE: term_exists() busca por ID se receber string numérica
        // Para termos hierárquicos, verificar se existe com o parent correto
        // Buscar por nome primeiro
        $existing_term = get_term_by( 'name', $name, $taxonomy );
        
        if( $existing_term && !is_wp_error($existing_term) ){
            // Se foi passado um parent_id, verificar se o termo existente tem o mesmo parent
            if( $parent_id > 0 ){
                $existing_parent = isset($existing_term->parent) ? intval($existing_term->parent) : 0;
                // Se o parent não corresponde, não usar o termo existente (será criado novo ou atualizado)
                if( $existing_parent != $parent_id ){
                    // Termo existe mas com parent diferente - continuar para criar novo ou atualizar
                } else {
                    // Termo existe com parent correto - retornar
                    return $existing_term->term_id;
                }
            } else {
                // Sem parent especificado - retornar termo existente se também não tiver parent
                $existing_parent = isset($existing_term->parent) ? intval($existing_term->parent) : 0;
                if( $existing_parent == 0 ){
                    return $existing_term->term_id;
                }
            }
        }

        // Preparar argumentos para criação
        $args = [];
        if( $parent_id ){
            $args['parent'] = $parent_id;
        }
        if( $slug ){
            $args['slug'] = $slug;
        }
        
        // Criar termo - garantir que estamos usando o nome, não um ID
        $result = wp_insert_term( $name, $taxonomy, $args );
        if( is_wp_error($result) ){
            // Termo já existe (slug duplicado ou mesmo nome/parent): usar o term_id existente
            $code = $result->get_error_code();
            if( $code === 'term_exists' ){
                $existing = $result->get_error_data( $code );
                $term_id = is_array($existing) && isset($existing['term_id'])
                    ? (int) $existing['term_id']
                    : (is_numeric($existing) ? (int) $existing : 0);
                if( $term_id > 0 ){
                    return $term_id;
                }
            }
            $error_msg = 'Erro ao inserir termo: ' . $result->get_error_message();
            self::log_debug('ensure_term', $error_msg);
            self::add_error($error_msg);
            return false;
        }

        $term_id = is_array($result) ? $result['term_id'] : $result;
        
        return $term_id;
    }

    /**
     * Associa termos a um objeto (post, user, etc.)
     * @param int $object_id ID do objeto
     * @param string $object_type Tipo do objeto (post, user, etc.)
     * @param array $term_ids Array com IDs dos termos
     * @param string $taxonomy Nome da taxonomia
     * @return bool Sucesso da operação
     */
    public static function associate_terms(
        $post_id, 
        $object_type, 
        $term_ids, 
        $taxonomy
    ) {
        
        if( empty($post_id) || empty($term_ids) || empty($taxonomy) ){
            $error_msg = 'object_id obrigatório: ' . $post_id;
            self::log_debug('associate_terms', $error_msg);
            self::add_error($error_msg);
            return false;
        }

        switch( $object_type ){
            case 'post':            
                return wp_set_object_terms( 
                    $post_id, 
                    $term_ids, 
                    $taxonomy, 
                    true
                );
                
            default:
                $error_msg = 'Tipo de objeto não suportado: ' . $object_type;
                self::log_debug('associate_terms', $error_msg);
                self::add_error($error_msg);
                return false;
        }
    }

    /**
     * Remove termos de localização de todos os posts de um usuário
     * @param int $user_id ID do usuário
     * @return bool Sucesso da operação
     */
    public static function desassociate_terms( $post_id = null, $taxonomy = [] ) {

        if( empty($post_id) || empty($taxonomy) ){ 
            $error_msg = 'post_id obrigatório: ' . $post_id . ' | taxonomy obrigatório: ' . $taxonomy;
            self::log_debug('desassociate_terms', $error_msg);
            self::add_error($error_msg);
            return false; 
        }

        wp_delete_object_term_relationships( $post_id, $taxonomy );
        return true;
    }


    public static function findParentTerms( $terms ) {
        
        if( empty($terms) ){ return []; }

        return array_values( 
            array_filter( $terms, function( $term ) {
                return isset( $term->parent ) && $term->parent == 0;
            })
        );
    }

    public static function findChildTermByParentId( $parent_id, $terms ) {
        if( empty($parent_id) || empty($terms) ){ return []; }
        return array_values( 
            array_filter( $terms, function( $term ) use ( $parent_id ) {
                return isset( $term->parent ) && $term->parent == $parent_id;
            })
        );
    }


    /**
     * Método genérico para inserir termos pai e filho e associá-los a um post
     * Alias/conveniência que usa ensure_term() e associate_terms()
     * Substitui a funcionalidade da classe obsoleta __Bazar_Insert_Terms
     * 
     * Aceita tanto IDs numéricos quanto nomes de termos, convertendo automaticamente IDs para nomes
     * 
     * @param int $post_id ID do post
     * @param string|int $parent Nome ou ID do termo pai
     * @param string|int $child Nome ou ID do termo filho
     * @param string $tax Taxonomia (hierárquica - pai e filho na mesma taxonomia)
     * @param string|null $parent_slug Slug do termo pai (opcional)
     * @param string|null $child_slug Slug do termo filho (opcional)
     * @param string $post_type Tipo do post (padrão: 'post')
     * @return bool|array Retorna array com ['parent_id' => int, 'child_id' => int] ou false em caso de erro
     */
    public static function insert_parent_child_terms(
        $post_id,
        $parent,
        $child,
        $tax,
        $parent_slug = null,
        $child_slug = null,
        $post_type = 'post'
    ) {
        
        if( 
            empty($post_id) 
            || empty($parent) 
            || empty($child) 
            || empty($tax)
        ) {
            $error_msg = 'Parâmetros obrigatórios não fornecidos para insert_parent_child_terms';
            self::log_debug('insert_parent_child_terms', $error_msg);
            self::add_error($error_msg);
            return false;
        }

        // Normalizar: se passar 'estado' como taxonomia, usar 'cidade' (única taxonomia existente)
        $taxonomy = ( $tax === 'estado') ? 'cidade' : $tax;        

        // Converter IDs para nomes antes de criar/verificar termos
        $parent_name = self::convert_term_id_to_name( $parent, $taxonomy );
        if( !$parent_name ){
            $error_msg = 'Erro ao converter ID para nome do termo pai: ' . $parent;
            self::log_debug('insert_parent_child_terms', $error_msg);
            self::add_error($error_msg);
            return false;
        }

        $child_name = self::convert_term_id_to_name( $child, $taxonomy );
        if( !$child_name ){
            $error_msg = 'Erro ao converter ID para nome do termo filho: ' . $child;
            self::log_debug('insert_parent_child_terms', $error_msg);
            self::add_error($error_msg);
            return false;
        }

        // Criar/verificar termos usando ensure_term() com os nomes
        $parent_id = self::ensure_term(
            $parent_name, 
            $taxonomy,
            null, 
            $parent_slug
        );
        if( !$parent_id ){
            $error_msg = 'Erro ao criar/verificar termo pai: ' . $parent_name;
            self::log_debug('insert_parent_child_terms', $error_msg);
            self::add_error($error_msg);
            return false;
        }

        $child_id = self::ensure_term(
            $child_name, 
            $taxonomy,
            $parent_id, 
            $child_slug
        );
        if( !$child_id ){
            $error_msg = 'Erro ao criar/verificar termo filho: ' . $child_name;
            self::log_debug('insert_parent_child_terms', $error_msg);
            self::add_error($error_msg);
            return false;
        }

        // Associar ambos os termos ao post usando associate_terms()
        $associate_terms = self::associate_terms(
            $post_id, 
            $post_type, 
            [$parent_id, $child_id], 
            $taxonomy
        );
        if( !$associate_terms ){
            $error_msg = 'Erro ao associar termos ao post: ' . $post_id . ' - ' . $parent_id . ' - ' . $child_id;
            self::log_debug('insert_parent_child_terms', $error_msg);
            self::add_error($error_msg);
            return false;
        }

        return [
            'parent_id' => $parent_id,
            'child_id' => $child_id
        ];
    }

    /**
     * Converte ID de termo para nome, ou retorna o nome se já for um nome
     * 
     * @param string|int $value ID ou nome do termo
     * @param string $taxonomy Nome da taxonomia
     * @return string|false Nome do termo ou false em caso de erro
     */
    private static function convert_term_id_to_name( $value, $taxonomy ) {
        
        if( empty($value) || empty($taxonomy) ) {
            self::log_debug('convert_term_id_to_name', 'valor vazio ou taxonomia vazia');
            return false;
        }

        // Se for numérico, pode ser ID OU nome textual (ex.: "4500").
        // Buscar por ID; se não existir, preservar o valor como nome.
        if( is_numeric( $value ) ) {
            $term = get_term( intval($value), $taxonomy );
            if( $term && !is_wp_error($term) ) {
                return $term->name;
            }
            return sanitize_text_field( (string) $value );
        }

        // Se não for numérico, retornar o valor sanitizado (já é um nome)
        $sanitized = sanitize_text_field( $value );
        return $sanitized;
    }


    // ============================================================================
    // CONTROLE DE LOCALIZAÇÃO - TAXONOMIAS DE CIDADE E ESTADO
    // ============================================================================

    /**
     * Garante que os termos de localização existam
     * @param string $estado Nome do estado
     * @param string $cidade Nome da cidade  
     * @param string|null $estado_sigla Sigla do estado
     * @return array|false Array com ['estado_id' => int, 'cidade_id' => int] ou false
     */
    public static function ensure_location_terms( $estado, $cidade, $estado_sigla = null ) {
        
        $estado = is_string($estado) ? trim($estado) : '';
        $cidade = is_string($cidade) ? trim($cidade) : '';
        if( empty($estado) || empty($cidade) ){ return false; }

        // 1. Verificar/Criar Estado
        $estado_id = self::ensure_term(
            $estado,
            'cidade',
            null, 
            $estado_sigla
        );
        if( !$estado_id ){
            $error_msg = 'Erro ao criar/verificar estado: ' . $estado;
            self::log_debug('ensure_location_terms', $error_msg);
            self::add_error($error_msg);
            return false;
        }

        // 2. Verificar/Criar Cidade
        $cidade_id = self::ensure_term(
            $cidade,
            'cidade',
            $estado_id
        );
        if( !$cidade_id ){
            $error_msg = 'Erro ao criar/verificar cidade: ' . $cidade;
            self::log_debug('ensure_location_terms', $error_msg);
            self::add_error($error_msg);
            return false;
        }

        return [
            'estado_id' => $estado_id,
            'cidade_id' => $cidade_id
        ];
    }    

    /**
     * Atualiza termos de localização para todos os posts de um usuário
     * @param int $user_id ID do usuário
     * @param string $estado Nome do estado
     * @param string $cidade Nome da cidade
     * @param string|null $estado_sigla Sigla do estado
     * @return bool Sucesso da operação
     */
    public static function update_user_posts_location(
        $user_id, 
        $estado, 
        $cidade, 
        $estado_sigla = null
    ) {
        
        // Garantir que os termos existam
        // Retorna array com ['estado_id' => int, 'cidade_id' => int] ou false
        $location_terms = self::ensure_location_terms(
            $estado, 
            $cidade, 
            $estado_sigla
        );
        if( !$location_terms ){ return false; }

        // Buscar todos os anúncios do usuário (post_type 'post' = anúncios neste tema)
        $args = array(
            'author' => $user_id,
            'post_type' => 'post',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'trash'),
        );
        $user_posts = get_posts( $args );

        // Usuário não tem posts, não é erro
        if( !$user_posts ){ return true;  }

        // Atualizar termos de cada post
        foreach( $user_posts as $post ){

            // Primeiro, desassociar todos os termos de localização antigos
            self::desassociate_terms( 
                $post->ID,
                ['cidade'] 
            );
                        
            // Associar novos valores para cidade (estado e cidade na mesma taxonomia)
            self::associate_terms(
                $post->ID,
                'post',
                [$location_terms['estado_id'], $location_terms['cidade_id']],
                'cidade'
            );
        }

        return true;
    }


    // ============================================================================
    // MÉTODOS DE ORDENAÇÃO - CALLBACKS (para usort)
    // ============================================================================

    /**
     * Obtém o valor do campo 'ordem' de um termo (genérico para todas as taxonomias)
     * Tenta buscar via ACF (get_field) primeiro, depois via term_meta como fallback
     * 
     * @param object $term Objeto WP_Term
     * @param string $taxonomy Nome da taxonomia (para determinar qual campo usar)
     * @return int Valor da ordem (padrão: 999 se não definido)
     */
    private static function get_ordem($term, $taxonomy = null) {
        $ordem = null;
        
        // Determinar qual campo usar baseado na taxonomia
        $meta_key = 'ordem'; // Padrão
        if ($taxonomy === 'componente') {
            $meta_key = 'ordem_componente';
        }
        
        // Tentar buscar via ACF primeiro (se disponível)
        if (function_exists('get_field')) {
            $ordem = get_field($meta_key, $term);
        }
        
        // Se não encontrou via ACF, tentar via term_meta (fallback)
        if (empty($ordem) || !is_numeric($ordem)) {
            $term_id = isset($term->term_id) ? $term->term_id : 0;
            if ($term_id > 0) {
                $ordem_meta = get_term_meta($term_id, $meta_key, true);
                if (!empty($ordem_meta) && is_numeric($ordem_meta)) {
                    $ordem = $ordem_meta;
                }
            }
        }
        
        // Se não tem valor ou é vazio, retornar 999 (último)
        if (empty($ordem) || !is_numeric($ordem)) {
            return 999;
        }
        
        return intval($ordem);
    }

    /**
     * Callback para ordenação alfabética de termos
     * @param object $a Primeiro termo para comparação
     * @param object $b Segundo termo para comparação
     * @return int Retorna -1, 0 ou 1 para ordenação
     */
    public static function ordenarAlfabetico($a, $b) {
        if( !isset($a->name) || !isset($b->name) ) {
            return 0;
        }
        return strcmp(trim($a->name), trim($b->name));
    }

    /**
     * Callback para ordenação com números primeiro, depois alfabeticamente
     * Exemplo: "10mm", "20mm", "Aro 26", "Aro 27.5", "Shimano"
     * @param object $a Primeiro termo para comparação
     * @param object $b Segundo termo para comparação
     * @return int Retorna -1, 0 ou 1 para ordenação
     */
    public static function ordenarNumericamenteCallback($a, $b) {
        if( !isset($a->name) || !isset($b->name) ) {
            return 0;
        }

        $nameA = trim($a->name);
        $nameB = trim($b->name);
        
        // Verifica se começa com número
        $startsWithNumberA = preg_match('/^\d/', $nameA);
        $startsWithNumberB = preg_match('/^\d/', $nameB);
        
        // Se ambos começam com número, ordena numericamente
        if ($startsWithNumberA && $startsWithNumberB) {
            // Extrai o número do início
            preg_match('/^(\d+)/', $nameA, $matchesA);
            preg_match('/^(\d+)/', $nameB, $matchesB);
            
            $numA = isset($matchesA[1]) ? intval($matchesA[1]) : 0;
            $numB = isset($matchesB[1]) ? intval($matchesB[1]) : 0;
            
            if ($numA != $numB) {
                return $numA - $numB;
            }
            // Se os números são iguais, ordena alfabeticamente
            return strcmp($nameA, $nameB);
        }
        
        // Se apenas A começa com número, A vem primeiro
        if ($startsWithNumberA && !$startsWithNumberB) {
            return -1;
        }
        
        // Se apenas B começa com número, B vem primeiro
        if (!$startsWithNumberA && $startsWithNumberB) {
            return 1;
        }
        
        // Se nenhum começa com número, ordena alfabeticamente
        return strcmp($nameA, $nameB);
    }

    public static function ordenarUpCountItemCallback( $a, $b ) {
        
        // Se as quantidades não estão definidas, retorna 0 (ordem original)
        if( !isset($a->count) || !isset($b->count) ) {
            return 0;
        }
        // Se as quantidades são diferentes, ordena por quantidade (maior primeiro)
        if( $a->count != $b->count ) {
            return $b->count - $a->count;
        }
        // Se as quantidades são iguais, ordena alfabeticamente
        return self::ordenarAlfabetico($a, $b);
    }

    // ============================================================================
    // MÉTODOS DE ORDENAÇÃO - FUNÇÕES DE ARRAY (recebem e retornam array)
    // ============================================================================

    /**
     * Ordena um array de termos alfabeticamente pelo nome
     * Considera o campo 'ordem' primeiro, depois ordena alfabeticamente
     * @param array $terms Array de objetos WP_Term
     * @param string|null $taxonomy Nome da taxonomia (para determinar qual campo de ordem usar)
     * @return array Array ordenado
     */
    public static function ordenarAlfabeticamente( $terms, $taxonomy = null ) {
        if( empty($terms) || !is_array($terms) ) {
            return $terms;
        }
        
        // Ordenar considerando 'ordem' primeiro, depois alfabeticamente
        usort($terms, function($a, $b) use ($taxonomy) {
            // Verificar se ambos têm campo 'ordem' definido
            $ordemA = self::get_ordem($a, $taxonomy);
            $ordemB = self::get_ordem($b, $taxonomy);
            
            // Se ambos têm ordem definida e são diferentes, ordenar por ordem
            if ($ordemA != 999 && $ordemB != 999 && $ordemA != $ordemB) {
                return $ordemA - $ordemB;
            }
            
            // Se apenas um tem ordem definida, o com ordem vem primeiro
            if ($ordemA != 999 && $ordemB == 999) {
                return -1;
            }
            if ($ordemA == 999 && $ordemB != 999) {
                return 1;
            }
            
            // Se nenhum tem ordem ou ambos têm a mesma ordem, ordenar alfabeticamente
            return self::ordenarAlfabetico($a, $b);
        });
        
        return $terms;
    }    

    /**
     * Ordena um array de termos com números primeiro, depois alfabeticamente
     * Considera o campo 'ordem' primeiro, depois ordena numericamente
     * @param array $terms Array de objetos WP_Term
     * @param string|null $taxonomy Nome da taxonomia (para determinar qual campo de ordem usar)
     * @return array Array ordenado
     */
    public static function ordenarNumericamente( $terms, $taxonomy = null ) {
        if( empty($terms) || !is_array($terms) ) {
            return $terms;
        }
        
        // Ordenar considerando 'ordem' primeiro, depois numericamente
        usort($terms, function($a, $b) use ($taxonomy) {
            // Verificar se ambos têm campo 'ordem' definido
            $ordemA = self::get_ordem($a, $taxonomy);
            $ordemB = self::get_ordem($b, $taxonomy);
            
            // Se ambos têm ordem definida e são diferentes, ordenar por ordem
            if ($ordemA != 999 && $ordemB != 999 && $ordemA != $ordemB) {
                return $ordemA - $ordemB;
            }
            
            // Se apenas um tem ordem definida, o com ordem vem primeiro
            if ($ordemA != 999 && $ordemB == 999) {
                return -1;
            }
            if ($ordemA == 999 && $ordemB != 999) {
                return 1;
            }
            
            // Se nenhum tem ordem ou ambos têm a mesma ordem, ordenar numericamente
            return self::ordenarNumericamenteCallback($a, $b);
        });
        
        return $terms;
    }


    public static function ordenarUpCountItem( $terms ) {
        if( empty($terms) || !is_array($terms) ) {
            return $terms;
        }
        usort($terms, [__CLASS__, 'ordenarUpCountItemCallback']);
        return $terms;
    }

    /**
     * Ordena termos hierarquicamente de forma genérica
     * Pais: ordena por 'ordem' (se disponível), depois term_order, depois alfabeticamente
     * Filhos: ordena por 'ordem' (se disponível), depois numericamente
     * 
     * Para ordenação específica de componentes (com default_bicicletas), 
     * use __Bazar_Component_Helper::ordenarComponentesHierarquicamente()
     * 
     * @param array $terms Array de objetos WP_Term
     * @param string|null $taxonomy Nome da taxonomia (para determinar qual campo de ordem usar)
     * @return array Array ordenado
     */
    public static function ordenarHierarquicamente( $terms, $taxonomy = null ) {
        
        if( empty($terms) || !is_array($terms) ) { return $terms; }

        // Primeiro, separamos pais e filhos
        $pais = array();
        $filhos = array();
        
        foreach( $terms as $term ){
            if( $term->parent == 0 ){
                $pais[] = $term;
            } 
            elseif( isset($term->parent) && $term->parent != 0 ){
                $filhos[] = $term;
            }
        }
        
        // Ordena os pais: primeiro por 'ordem', depois term_order, depois alfabeticamente
        usort($pais, function($a, $b) use ($taxonomy) {
            // 1. Verificar campo 'ordem' primeiro
            $ordemA = self::get_ordem($a, $taxonomy);
            $ordemB = self::get_ordem($b, $taxonomy);
            
            // Se ambos têm ordem definida e são diferentes, ordenar por ordem
            if ($ordemA != 999 && $ordemB != 999 && $ordemA != $ordemB) {
                return $ordemA - $ordemB;
            }
            
            // Se apenas um tem ordem definida, o com ordem vem primeiro
            if ($ordemA != 999 && $ordemB == 999) {
                return -1;
            }
            if ($ordemA == 999 && $ordemB != 999) {
                return 1;
            }
            
            // 2. Se ordem é igual ou não aplicável, ordena por term_order
            if( isset($a->term_order) && isset($b->term_order) ){
                // Se term_order são diferentes, ordena por term_order
                if( $a->term_order != $b->term_order ){
                    return $a->term_order - $b->term_order;
                }
                // Se term_order são iguais, ordena alfabeticamente
                return self::ordenarAlfabetico($a, $b);
            }
            
            // Se apenas um tem term_order, o com term_order vem primeiro
            if( isset($a->term_order) && !isset($b->term_order) ){
                return -1;
            }
            if( !isset($a->term_order) && isset($b->term_order) ){
                return 1;
            }
            
            // Se nenhum tem term_order, ordena alfabeticamente
            return self::ordenarAlfabetico($a, $b);
        });
        
        // Cria array associativo para busca rápida de pais
        $pais_map = array_combine(
            array_map(function($pai) { return $pai->term_id; }, $pais),
            $pais
        );
        
        // Agrupa filhos por pai e ordena cada grupo
        $filhos_por_pai = array();
        foreach( $filhos as $filho ){
            $pai_id = isset($filho->parent) ? $filho->parent : 0;
            if( !isset($filhos_por_pai[$pai_id]) ){
                $filhos_por_pai[$pai_id] = array();
            }
            $filhos_por_pai[$pai_id][] = $filho;
        }
        
        // Ordena os filhos dentro de cada grupo pai: primeiro por 'ordem', depois numericamente
        foreach( $filhos_por_pai as $pai_id => $filhos_grupo ){
            usort($filhos_grupo, function($a, $b) use ($taxonomy) {
                // Verificar campo 'ordem' primeiro
                $ordemA = self::get_ordem($a, $taxonomy);
                $ordemB = self::get_ordem($b, $taxonomy);
                
                // Se ambos têm ordem definida e são diferentes, ordenar por ordem
                if ($ordemA != 999 && $ordemB != 999 && $ordemA != $ordemB) {
                    return $ordemA - $ordemB;
                }
                
                // Se apenas um tem ordem definida, o com ordem vem primeiro
                if ($ordemA != 999 && $ordemB == 999) {
                    return -1;
                }
                if ($ordemA == 999 && $ordemB != 999) {
                    return 1;
                }
                
                // Se nenhum tem ordem ou ambos têm a mesma ordem, ordenar numericamente
                return self::ordenarNumericamenteCallback($a, $b);
            });
            $filhos_por_pai[$pai_id] = $filhos_grupo;
        }
        
        // Monta o array final: para cada pai, adiciona o pai seguido de seus filhos
        $resultado = array();
        foreach( $pais as $pai ){
            $resultado[] = $pai;
            // Adiciona os filhos deste pai, se existirem
            if( isset($filhos_por_pai[$pai->term_id]) ){
                foreach( $filhos_por_pai[$pai->term_id] as $filho ){
                    $resultado[] = $filho;
                }
            }
        }
        
        // Adiciona filhos órfãos (filhos cujo pai não está na lista de pais)
        foreach( $filhos_por_pai as $pai_id => $filhos_grupo ){
            if( !isset($pais_map[$pai_id]) ){
                foreach( $filhos_grupo as $filho ){
                    $resultado[] = $filho;
                }
            }
        }
        
        return $resultado;
    }

    /**
     * Método principal de ordenação - escolhe automaticamente o melhor método
     * ou usa o método especificado
     * @param array $terms Array de objetos WP_Term
     * @param string|null $taxonomy Nome da taxonomia (para escolha automática)
     * @param string|null $metodo Método específico: 'alfabetico', 'numeros_primeiro', 'hierarquico'
     * @return array Array ordenado
     */
    public static function ordenar( $terms, $taxonomy = null, $metodo = null ) {
        
        if( empty($terms) || !is_array($terms) ) { return $terms; }

        // Se método foi especificado, usa ele (passando taxonomy para considerar campo 'ordem')
        if( $metodo ) {
            switch( $metodo ) {
                case 'alfabetico':
                    return self::ordenarAlfabeticamente($terms, $taxonomy);
                case 'numerico':
                    return self::ordenarNumericamente($terms, $taxonomy);
                case 'hierarquico':
                    return self::ordenarHierarquicamente($terms, $taxonomy);
                default:
                    return self::ordenarAlfabeticamente($terms, $taxonomy);
            }
        }

        // Escolha automática baseada na taxonomia
        // Taxonomias que geralmente têm números (especificações, acessórios)
        $taxonomias_com_numeros = ['especificacoes', 'acessorio'];
        
        // Taxonomias hierárquicas que devem manter hierarquia
        $taxonomias_hierarquicas = ['marca-modelo', 'cidade', 'modalidade'];
        
        // Tratamento especial para componentes (usa método específico)
        if( $taxonomy === 'componente' ) {
            // Verifica se há termos com parent para decidir se usa hierárquico
            $tem_hierarquia = false;
            foreach( $terms as $term ) {
                if( isset($term->parent) && $term->parent != 0 ) {
                    $tem_hierarquia = true;
                    break;
                }
            }
            if( $tem_hierarquia && class_exists('__Bazar_Component_Helper') ) {
                return __Bazar_Component_Helper::ordenarComponentesHierarquicamente($terms);
            }
            // Se não tem hierarquia, ordena numericamente
            return self::ordenarNumericamente($terms);
        }
        
        if( $taxonomy && in_array($taxonomy, $taxonomias_com_numeros) ) {
            return self::ordenarNumericamente($terms, $taxonomy);
        }
        
        if( $taxonomy && in_array($taxonomy, $taxonomias_hierarquicas) ) {
            // Verifica se há termos com parent para decidir se usa hierárquico
            $tem_hierarquia = false;
            foreach( $terms as $term ) {
                if( isset($term->parent) && $term->parent != 0 ) {
                    $tem_hierarquia = true;
                    break;
                }
            }
            if( $tem_hierarquia ) {
                return self::ordenarHierarquicamente($terms, $taxonomy);
            }
        }
        
        // Padrão: ordenação alfabética (considerando campo 'ordem')
        return self::ordenarAlfabeticamente($terms, $taxonomy);
    }

    // ============================================================================
    // MÉTODOS DE ÍCONES E IMAGENS (genéricos para qualquer taxonomia)
    // ============================================================================

    /**
     * Obtém o ícone de um termo (genérico para qualquer taxonomia)
     * Tenta obter do campo ACF 'icone', com fallback para term_meta em preview
     * 
     * @param WP_Term $term O termo do qual obter o ícone
     * @return string|null URL do ícone ou null se não encontrado
     */
    public static function get_term_icon( $term ) {
        
        if( !$term || !is_object($term) ) {
            return null;
        }

        $is_preview = isset($_GET['preview']) && $_GET['preview'] == 'true';
        
        // Tenta obter o ícone do campo ACF
        $icon = null;
        if( function_exists('get_field') ) {
            $icon = get_field('icone', $term);
        }
        
        // Se não encontrou e está em preview, tenta obter do meta
        if( !$icon && $is_preview ) {
            $icon = get_term_meta($term->term_id, 'icone', true);
        }
        
        // Se o ícone é um ID, converte para URL
        if( $icon && is_numeric($icon) ) {
            $icon = wp_get_attachment_url($icon);
        }

        // Tenta obter o ícone do termo pai
        // Função recursiva
        $parent_id = isset($term->parent) ? $term->parent : 0;
        if( $parent_id ) {
            $parent = get_term($parent_id);
            if( $parent && !is_wp_error($parent) ) {
                return __Bazar_Terms_Manager::get_term_icon($parent);
            }
        }

        return __Bazar_Terms_Manager::generate_term_image(
            $icon, 
            $term->name
        );
    }

    /**
     * Gera HTML de imagem para termo (genérico)
     * 
     * @param string|null $url URL da imagem
     * @param string|null $title Título/alt da imagem
     * @param string|null $default_path Caminho padrão se URL não fornecida
     * @return string HTML da tag img
     */
    public static function generate_term_image( $url = null, $title = null, $default_path = null) {

        $src = $url;
        
        if( !$src ) {
            if( $default_path ) {
                $src = $default_path;
            } else {
                $src = get_bloginfo('url') . '/src/imgs/componentes/default.png';
            }
        }

        $title = $title ? $title : 'Icone do Componente';
        
        return '<img src="' . esc_url($src) . '" title="' . esc_attr($title) . '" alt="' . esc_attr($title) . '" />';
    }


    /**
     * Método centralizado de log
     * Salva em self::$debug_log e error_log()
     */
    private static function log_debug( $key, $message, $append = false ) {
        $message_str = is_scalar($message) ? (string) $message : print_r($message, true);
        
        error_log('[' . __CLASS__ . '] [' . $key . '] ' . $message_str);
        
        if( $append && isset(self::$debug_log[$key]) ) {
            self::$debug_log[$key] .= ' | ' . $message_str;
        } else {
            self::$debug_log[$key] = $message_str;
        }
    }

    /**
     * Retorna o array de logs de debug
     */
    public static function get_debug_log() {
        return self::$debug_log;
    }

    /**
     * Retorna mensagens de erro
     */
    public static function get_error_message( $as_string = false ) {
        return $as_string ? implode("\n", self::$error_messages) : self::$error_messages;
    }

    /**
     * Limpa os logs e mensagens de erro
     */
    public static function clear_logs() {
        self::$debug_log = array();
        self::$error_messages = array();
    }

    /**
     * Adiciona uma mensagem de erro
     */
    private static function add_error( $message ) {
        self::$error_messages[] = $message;
        self::log_debug('error', $message, true);
    }
    
}
?>