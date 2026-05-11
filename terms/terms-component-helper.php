<?php
/**
 * Classe para funcionalidades específicas de componentes
 * FONTE DE VERDADE para busca e manipulação de componentes
 * Faz apenas 1 query e reutiliza o array em memória
 * 
 * @package XXXXXX
 */
class __Bazar_Component_Helper {

    /**
     * Cache interno do array de componentes (evita múltiplas queries)
     * @var array|null
     */
    private static $components_cache = null;

    private static $cache_size = 604800; // 7 dias

    /**
     * Busca TODOS os componentes (pais e filhos) com cache
     * Faz apenas 1 query e armazena em cache interno e WordPress
     * 
     * @return array Array de objetos WP_Term ordenados hierarquicamente
     */
    public static function get_all_components() {
        // Se já temos em cache interno, retornar
        if (self::$components_cache !== null) {
            return self::$components_cache;
        }

        // Tentar buscar do cache do WordPress
        $cache_key = 'bazar_all_components';
        $cache_group = 'bazar_components';
        $cached = wp_cache_get($cache_key, $cache_group);
        
        if ($cached !== false && is_array($cached)) {
            // Armazenar também no cache interno
            self::$components_cache = $cached;
            return $cached;
        }

        // Se não estiver em cache, fazer a query (APENAS 1 VEZ)
        $args = array(
            'taxonomy' => 'componente',
            'hierarchical' => 1,
            'hide_empty' => false,
        );
        
        $components = get_terms($args);
        
        if (empty($components) || is_wp_error($components)) {
            $result = array();
        } else {
            // Ordenar componentes hierarquicamente
            $result = self::ordenarComponentesHierarquicamente($components);
        }

        // Armazenar em ambos os caches
        // Cache de componentes: 7 dias (604800 segundos) - componentes raramente mudam
        self::$components_cache = $result;
        wp_cache_set($cache_key, $result, $cache_group, self::$cache_size);

        return $result;
    }

    /**
     * Busca apenas componentes PAIS (parent = 0)
     * Reutiliza o array de get_all_components() sem fazer nova query
     * 
     * @return array Array de objetos WP_Term (apenas pais) ordenados
     */
    public static function get_parent_components() {
        // Buscar todos os componentes (usa cache)
        $all_components = self::get_all_components();
        
        if (empty($all_components)) {
            return array();
        }

        // Filtrar apenas componentes pais (reutiliza array em memória)
        $parents = array_filter($all_components, function($term) {
            return isset($term->parent) && intval($term->parent) === 0;
        });

        // Reindexar array
        return array_values($parents);
    }

    /**
     * Busca apenas componentes OBRIGATÓRIOS (default_bicicletas = true)
     * Reutiliza o array de get_all_components() sem fazer nova query
     * 
     * @return array Array de objetos WP_Term (apenas obrigatórios) ordenados
     */
    public static function get_default_components() {
        // Buscar todos os componentes pais (usa cache)
        $parents = self::get_parent_components();
        
        if (empty($parents)) {
            return array();
        }

        // Filtrar apenas componentes obrigatórios (reutiliza array em memória)
        $obrigatorios = array();
        foreach ($parents as $term) {
            if (self::isDefaultBicicletas($term)) {
                $obrigatorios[] = $term;
            }
        }

        return $obrigatorios;
    }

    /**
     * Limpa o cache interno e do WordPress
     * Deve ser chamado quando componentes são editados/criados/deletados
     * 
     * @return bool True se os caches foram limpos
     */
    public static function clear_cache() {
        // Limpar cache interno
        self::$components_cache = null;
        
        // Limpar cache do WordPress
        $cache_group = 'bazar_components';
        $cache_keys = array(
            'bazar_all_components',
            'bazar_componentes_parents',
            'bazar_componentes_obrigatorios'
        );
        
        $cleared = true;
        foreach ($cache_keys as $cache_key) {
            $result = wp_cache_delete($cache_key, $cache_group);
            if ($result === false) {
                $cleared = false;
            }
        }
        
        return $cleared;
    }

    /**
     * Encontra marca e modelo de um componente em um array de dados
     * Retorna apenas os dados, sem formatação HTML
     * 
     * @param int|string|null $componente_id ID do componente
     * @param array|null $arrayDados Array com dados dos componentes
     * @return array|false Array com 'marca' e 'modelo' ou false se não encontrado
     */
    public static function find_marca_modelo($componente_id = null, $arrayDados = null) {
        if( is_null($componente_id) || is_null($arrayDados) || !is_array($arrayDados) ) {
            return false;
        }
        
        $componente_id_str = strval($componente_id);
        
        foreach( $arrayDados as $item ) {
            if( 
                isset($item['componente_id']) 
                && $item['componente_id'] === $componente_id_str
                && !empty($item['marca'])
            ) {
                return array(
                    'marca' => $item['marca'],
                    'modelo' => isset($item['modelo']) && !empty($item['modelo']) ? $item['modelo'] : ''
                );
            }
        }
        
        return false;
    }

    /**
     * Formata marca e modelo em HTML
     * 
     * @param string $marca Nome da marca
     * @param string $modelo Nome do modelo
     * @return string HTML formatado
     */
    public static function format_marca_modelo_html($marca, $modelo = '') {
        $texto = trim($marca . ' ' . $modelo);
        return '<span class="brand">' . esc_html($texto) . '</span>';
    }

    /**
     * Encontra e retorna HTML formatado de marca/modelo de componente
     * Wrapper que combina find_marca_modelo() e format_marca_modelo_html()
     * 
     * @param int|string|null $componente_id ID do componente
     * @param array|null $arrayDados Array com dados dos componentes
     * @return string HTML formatado ou string vazia se não encontrado
     */
    public static function find_marca_modelo_html($componente_id = null, $arrayDados = null) {
        $dados = self::find_marca_modelo($componente_id, $arrayDados);
        
        if( $dados === false ) {
            return '';
        }
        
        return self::format_marca_modelo_html($dados['marca'], $dados['modelo']);
    }

    /**
     * Obtém os termos de marca e modelo para um componente específico de um post
     * 
     * @param int $post_id ID da postagem
     * @param int $parent_id ID do componente pai
     * @return array Array com 'marca' e 'modelo' (strings vazias se não encontrado)
     */
    public static function get_marca_modelo($post_id, $parent_id) {
        $result = array(
            'marca' => '',
            'modelo' => ''
        );
        
        if( empty($post_id) || empty($parent_id) ) {
            return $result;
        }
        
        $id = strval($parent_id);
        
        // Obtém os componentes do post através do campo ACF
        if( !function_exists('get_field') ) {
            return $result;
        }
        
        $componentes = get_field('componentes', $post_id);
        if( !$componentes || !is_array($componentes) ) {
            return $result;
        }
        
        foreach( $componentes as $componente ) {
            if( isset($componente['parent_id']) && $componente['parent_id'] === $id ) {
                $result['marca'] = isset($componente['marca']) ? $componente['marca'] : '';
                $result['modelo'] = isset($componente['modelo']) ? $componente['modelo'] : '';
                break;
            }
        }
        
        return $result;
    }

    /**
     * Obtém os IDs dos termos de componente associados a um post, filtrados por parent_id
     * 
     * @param int $post_id ID da postagem
     * @param int $parent_id ID do componente pai
     * @param string $taxonomy Nome da taxonomia (padrão: 'componente')
     * @return array Array com IDs dos termos (array vazio se não encontrado)
     */
    public static function get_componente_terms($post_id, $parent_id, $taxonomy = 'componente') {
        $result = array();
        
        if( empty($post_id) || empty($parent_id) ) {
            return $result;
        }
        
        // Obtém todos os termos de componente associados ao post
        $all_terms = get_the_terms($post_id, $taxonomy);
        if( !$all_terms || is_wp_error($all_terms) ) {
            return $result;
        }
        
        $parent_id_int = intval($parent_id);
        
        // Filtra apenas os termos que são filhos do parent_id especificado
        foreach( $all_terms as $term ) {
            if( isset($term->parent) && intval($term->parent) === $parent_id_int ) {
                $result[] = intval($term->term_id);
            }
        }
        
        return $result;
    }

    /**
     * Verifica se um termo tem o campo default_bicicletas como true
     * Método específico para componentes de bicicletas
     * 
     * @param object $term Objeto WP_Term
     * @return bool True se default_bicicletas é true
     */
    public static function isDefaultBicicletas( $term ) {
        if (!function_exists('get_field')) {
            return false;
        }
        $default_field = get_field('default_bicicletas', $term);
        return ($default_field === true || $default_field === '1' || $default_field === 1 || $default_field === 'true');
    }

    /**
     * Obtém o valor do campo ordem_componente de um termo
     * Campo usado para ordenação personalizada entre componentes destacados
     * Tenta buscar via ACF (get_field) primeiro, depois via term_meta como fallback
     * 
     * @param object $term Objeto WP_Term
     * @return int Valor da ordem (padrão: 999 se não definido)
     */
    private static function get_ordem_componente($term) {
        
        $ordem = null;
        
        // Tentar buscar via ACF primeiro (se disponível)
        if (function_exists('get_field')) {
            $ordem = get_field('ordem_componente', $term);
        }
        
        // Se não encontrou via ACF, tentar via term_meta (fallback)
        if (empty($ordem) || !is_numeric($ordem)) {
            $term_id = isset($term->term_id) ? $term->term_id : 0;
            if ($term_id > 0) {
                $ordem_meta = get_term_meta($term_id, 'ordem_componente', true);
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
     * Ordena componentes hierarquicamente com lógica específica para componentes
     * Ordenação dos pais:
     * 1. default_bicicletas (true primeiro)
     * 2. ordem_componente (campo ACF personalizado, menor primeiro)
     * 3. term_order (se disponível)
     * 4. Alfabeticamente
     * 
     * Ordenação dos filhos: numericamente (números primeiro)
     * 
     * @param array $terms Array de objetos WP_Term de componentes
     * @return array Array ordenado
     */
    public static function ordenarComponentesHierarquicamente( $terms ) {
        if( empty($terms) || !is_array($terms) ) { 
            return $terms; 
        }

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
        
        // Ordena os pais: default_bicicletas → ordem_componente → term_order → alfabético
        usort($pais, function($a, $b) {
            
            $defaultA = self::isDefaultBicicletas($a);
            $defaultB = self::isDefaultBicicletas($b);
            
            // 1. Se um tem default_bicicletas e o outro não, o com default vem primeiro
            if( $defaultA && !$defaultB ) {
                return -1;
            }
            if( !$defaultA && $defaultB ) {
                return 1;
            }
            
            // 2. Se ambos têm ou não têm default_bicicletas, ordena por ordem_componente (campo ACF)
            // Isso permite ordenação personalizada entre os destacados E os não-destacados
            $ordemA = self::get_ordem_componente($a);
            $ordemB = self::get_ordem_componente($b);
            
            // Se ambos têm ordem_componente definida e são diferentes, ordenar por ordem
            if( $ordemA != 999 && $ordemB != 999 && $ordemA != $ordemB ) {
                return $ordemA - $ordemB; // Menor ordem primeiro
            }
            
            // Se apenas um tem ordem_componente definida, o com ordem vem primeiro (dentro do mesmo grupo default)
            if( $ordemA != 999 && $ordemB == 999 ) {
                return -1;
            }
            if( $ordemA == 999 && $ordemB != 999 ) {
                return 1;
            }
            
            // 3. Se ordem_componente é igual ou não aplicável, ordena por term_order
            if( isset($a->term_order) && isset($b->term_order) ){
                // Se term_order são diferentes, ordena por term_order
                if( $a->term_order != $b->term_order ){
                    return $a->term_order - $b->term_order;
                }
                // Se term_order são iguais, ordena alfabeticamente
                return __Bazar_Terms_Manager::ordenarAlfabetico($a, $b);
            }
            
            // 4. Se apenas um tem term_order, o com term_order vem primeiro
            if( isset($a->term_order) && !isset($b->term_order) ){
                return -1;
            }
            if( !isset($a->term_order) && isset($b->term_order) ){
                return 1;
            }
            
            // 5. Se nenhum tem term_order, ordena alfabeticamente
            return __Bazar_Terms_Manager::ordenarAlfabetico($a, $b);
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
        
        // Ordena os filhos dentro de cada grupo pai usando ordenação numérica
        foreach( $filhos_por_pai as $pai_id => $filhos_grupo ){
            usort($filhos_grupo, function($a, $b) {
                return __Bazar_Terms_Manager::ordenarNumericamenteCallback($a, $b);
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
}
/**
 * Limpa o cache de todos os componentes (compatibilidade)
 * Wrapper que acessa a classe __Bazar_Component_Helper
 * 
 * @deprecated Use bazar_clear_componentes_cache() ou __Bazar_Component_Helper::clear_cache() diretamente
 */
function bazar_clear_all_components_cache(){
    if (class_exists('__Bazar_Component_Helper')) {
        return __Bazar_Component_Helper::clear_cache();
    }
    return false;
}
?>

