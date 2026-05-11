<?php
/**
 * Classe unificada para gerar queries de posts relacionados
 * Substitui TaxQueryGenerator e RelatedTaxQuery
 * Usa helper centralizado para evitar queries redundantes
 * 
 * @package XXXXXX
 */
class RelatedTaxQuery
{

    // Propriedades públicas
    public $post_id;
    public $post_type;
    public $post_count;
    public $query;
    // Propriedades privadas
    private $product_data; // Dados do produto obtidos via helper
    private $exclude_ids = array();
    private $debug = array();
    private $shuffle = false; // Opção para embaralhar resultados
    private $is_ads = false; // Flag para tipo ads

    /**
     * Para anúncios (post): embaralha cada grupo de query separadamente e concatena na ordem de prioridade
     * (cidade+modalidade → estado+modalidade → só modalidade), sem misturar camadas no shuffle final.
     */
    private $tiered_shuffle_posts = false;

    /** Contador para seed distinta por camada no embaralhamento em tiers */
    private $tier_shuffle_counter = 0;

    /** IDs de ads já retornados nesta requisição (evita repetir o mesmo ad na página) */
    private static $ads_already_shown = array();

    /** Limites de faixa de preço (BRL) para ordenação de relacionados — grupo 1: [0, 2000), etc. */
    private const PRICE_BAND_UPPER_1 = 2000;

    private const PRICE_BAND_UPPER_2 = 10000;

    private const PRICE_BAND_UPPER_3 = 30000;

    /**
     * Construtor
     * 
     * @param int $post_id ID do post
     * @param string|array $post_type Tipo(s) de post
     * @param int $post_count Quantidade de posts a retornar
     * @param array|null $exclude_ids IDs de posts a excluir
     */
    public function __construct(
        $post_id,
        $post_type,
        $post_count = 12,
        $exclude_ids = null,
    ) {
        $this->post_type = is_array($post_type) ? $post_type : array($post_type);
        $this->is_ads = in_array('ads', $this->post_type);

        // Só exige post_id para tipos que usam relacionamento (não para ads)
        if (is_null($post_id) && !$this->is_ads) {
            $this->query = false;
            return;
        }

        $this->post_id = $post_id;
        $this->post_count = (int) $post_count;

        // Para ads, não usar helper (ads não tem taxonomias complexas)
        // Para blog, não usar helper de produtos (blog tem estrutura diferente)
        if (!$this->is_ads && in_array('post', $this->post_type)) {
            // Obter dados do produto via helper (evita queries redundantes)
            // Apenas para posts (produtos), não para blog
            $this->product_data = bazar_get_product_data_for_context(
                $post_id,
                __Bazar_Product_Data_Repository::CONTEXT_RELATED
            );
        }

        // Cache desabilitado para produtos relacionados
        // Motivo: produtos relacionados devem variar para dar mais oportunidades de visualização
        // Cache fazia com que sempre aparecessem os mesmos produtos

        // Processar IDs a excluir
        if ($exclude_ids) {
            $this->exclude_ids = is_array($exclude_ids) ? $exclude_ids : array($exclude_ids);
        }

        // Debug
        $this->debug['post_id'] = $post_id;
        $this->debug['post_type'] = $this->post_type;
        $this->debug['post_count'] = $this->post_count;
        $this->debug['exclude_ids'] = $this->exclude_ids;
        $this->debug['is_ads'] = $this->is_ads;

        // Gerar query
        $this->generate();
    }

    /**
     * Gera a query de posts relacionados
     * 
     * @return WP_Query|false
     */
    public function generate()
    {
        if (is_null($this->post_id) && !$this->is_ads) {
            $this->query = false;
            return false;
        }

        // Para ads, usar lógica simplificada (não depende de post_id)
        if ($this->is_ads) {
            return $this->generateAdsQuery();
        }

        // Cache desabilitado - produtos relacionados devem variar
        $this->debug['cache_hit'] = false;

        // Executar queries em ordem de prioridade
        $this->executeQueries();

        // Se não encontrou nenhum post, criar query vazia para manter compatibilidade
        if (is_null($this->query) || !is_object($this->query)) {
            $args = $this->buildBaseQueryArgs();
            $args['posts_per_page'] = 0; // Query vazia
            $this->query = new WP_Query($args);
        }

        // Aplicar shuffle nos resultados finais mesclados
        // Manter apenas randomização simples - RELEVÂNCIA é mais importante que destaque
        // Destaques terão seu próprio slide separado, então aqui priorizamos relevância
        // Usar seed baseado no post_id para ter randomização variada mas determinística
        // Cada produto terá uma ordem diferente de relacionados
        if ($this->query && is_object($this->query) && !empty($this->query->posts)) {
            if (in_array('post', $this->post_type, true)) {
                if ($this->should_apply_price_band_ordering()) {
                    $this->query->posts = $this->order_related_posts_by_price_bands($this->query->posts);
                } else {
                    $this->query->posts = $this->shuffleRelatedPosts($this->query->posts, false);
                }
            } else {
                $this->query->posts = $this->shuffleRelatedPosts($this->query->posts, false);
            }
            $this->query->post_count = count($this->query->posts);
        }

        // Cache desabilitado - produtos relacionados devem variar para melhor distribuição de visualizações

        return $this->query;
    }

    /**
     * Gera query para tipo ads (anúncios).
     * Exclui ads já retornados nesta requisição para não repetir o mesmo banner na página.
     *
     * @return WP_Query|false
     */
    private function generateAdsQuery()
    {
        $exclude = array_merge(
            array_filter(array($this->post_id)),
            $this->exclude_ids,
            self::$ads_already_shown
        );
        $exclude = array_unique(array_filter($exclude));

        $args = array(
            'post_type' => 'ads',
            'posts_per_page' => $this->post_count,
            'post_status' => 'publish',
            'post__not_in' => $exclude,
            'orderby' => 'rand'
        );

        $this->query = new WP_Query($args);

        if ($this->query->have_posts()) {
            shuffle($this->query->posts);
            foreach ($this->query->posts as $p) {
                self::$ads_already_shown[] = $p->ID;
            }
            self::$ads_already_shown = array_unique(self::$ads_already_shown);
        }

        return $this->query;
    }

    /**
     * Executa queries em ordem de prioridade baseado no tipo de post
     * Delega para métodos específicos: executeQueriesForPost() ou executeQueriesForBlog()
     */
    private function executeQueries()
    {
        $this->query = null;

        // Determinar tipo de post
        $is_post = in_array('post', $this->post_type);
        $is_blog = in_array('blog', $this->post_type);

        if ($is_post) {
            $this->executeQueriesForPost();
        } elseif ($is_blog) {
            $this->executeQueriesForBlog();
        } else {
            // Para outros tipos: apenas cidade
            $this->queryByCidade();
        }
    }

    /**
     * Executa queries para posts (produtos/anúncios)
     * Ordem: cidade+modalidade -> estado (pai e cidades filhas)+modalidade -> apenas modalidade (aleatório)
     */
    private function executeQueriesForPost()
    {
        $this->tiered_shuffle_posts = true;
        $this->tier_shuffle_counter = 0;

        try {
            // Prioridade 1: Cidade (filho) + Modalidade
            $this->queryByCidadeAndModalidade();
            $this->debug['after_cidade_modalidade'] = $this->getPostCount();

            // Prioridade 2: Mesmo estado da cidade + Modalidade (completa até 12 sem repetir IDs)
            if ($this->needsMorePosts()) {
                $this->queryByEstadoAndModalidade();
                $this->debug['after_estado_modalidade'] = $this->getPostCount();
            }

            // Prioridade 3: Apenas mesma modalidade (orderby rand em buildBaseQueryArgs)
            if ($this->needsMorePosts()) {
                $this->queryByModalidade();
                $this->debug['after_modalidade'] = $this->getPostCount();
            }
        } finally {
            $this->tiered_shuffle_posts = false;
        }
    }

    /**
     * Executa queries para blog (conteúdo)
     * Ordem baseada nas taxonomias indexadas: category -> cidade -> marca-modelo -> modalidade -> componente
     * Focado em SEO e relacionamento por taxonomias indexadas
     */
    private function executeQueriesForBlog()
    {
        // Prioridade 1: Category (mesma categoria de conteúdo - mais genérico)
        $this->queryByCategoryForBlog();
        $this->debug['after_category'] = $this->getPostCount();

        // Prioridade 2: Cidade (mesma localização - SEO local)
        if ($this->needsMorePosts()) {
            $this->queryByCidadeForBlog();
            $this->debug['after_cidade'] = $this->getPostCount();
        }

        // Prioridade 3: Marca-Modelo (mesma marca - conteúdo específico)
        if ($this->needsMorePosts()) {
            $this->queryByMarcaModeloForBlog();
            $this->debug['after_marca_modelo'] = $this->getPostCount();
        }

        // Prioridade 4: Modalidade (mesma modalidade - conteúdo temático)
        if ($this->needsMorePosts()) {
            $this->queryByModalidadeForBlog();
            $this->debug['after_modalidade'] = $this->getPostCount();
        }

        // Prioridade 5: Componente (mesmo componente - conteúdo técnico)
        if ($this->needsMorePosts()) {
            $this->queryByComponenteForBlog();
            $this->debug['after_componente'] = $this->getPostCount();
        }

        // Prioridade 6: Fallback genérico (outros posts blog aleatórios)
        if ($this->needsMorePosts()) {
            $this->queryByFallbackForBlog();
            $this->debug['after_fallback'] = $this->getPostCount();
        }
    }

    /**
     * Query por cidade + modalidade (prioridade máxima)
     * Ex: bicicletas elétricas na mesma cidade
     */
    private function queryByCidadeAndModalidade()
    {
        $cidade_ids = $this->getCidadeIds();
        $modalidade_ids = $this->getModalidadeIds();

        // Só executar se tiver ambos
        if (empty($cidade_ids) || empty($modalidade_ids)) {
            $this->debug['cidade_modalidade_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        // Adicionar filtros combinados
        $args['tax_query'][] = array(
            'taxonomy' => 'cidade',
            'field' => 'term_id',
            'terms' => $cidade_ids
        );
        $args['tax_query'][] = array(
            'taxonomy' => 'modalidade',
            'field' => 'term_id',
            'terms' => $modalidade_ids
        );
        // Definir relação AND para combinar filtros
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        // Aplicar ordenação por proximidade
        $this->applyProximityOrdering($args);

        $query = new WP_Query($args);
        $this->debug['cidade_modalidade_query'] = $query->request;
        $this->debug['cidade_modalidade_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Escopo geográfico por estado na taxonomia `cidade`: termo pai (estado) + todas as cidades filhas.
     * Usado para completar relacionados com mesma modalidade no restante do estado.
     *
     * @return array Lista de term_id
     */
    private function getEstadoCidadeScopeIds()
    {
        $terms = array();

        if (!empty($this->product_data)) {
            $terms = isset($this->product_data['taxonomies']['cidade'])
                ? $this->product_data['taxonomies']['cidade']
                : array();
        } else {
            $raw = get_the_terms($this->post_id, 'cidade');
            if ($raw && !is_wp_error($raw)) {
                $terms = $raw;
            }
        }

        if (empty($terms)) {
            return array();
        }

        $scope = array();

        foreach ($terms as $term) {
            if ((int) $term->parent === 0) {
                // Anúncio marcado com o estado (pai): inclui o estado e todas as cidades filhas.
                $scope[] = (int) $term->term_id;
                $children = get_term_children($term->term_id, 'cidade');
                if (!is_wp_error($children) && !empty($children)) {
                    foreach ($children as $cid) {
                        $scope[] = (int) $cid;
                    }
                }
            } else {
                // Cidade filha: estado = parent; incluir pai + todas as cidades desse estado.
                $parent_id = (int) $term->parent;
                $scope[] = $parent_id;
                $children = get_term_children($parent_id, 'cidade');
                if (!is_wp_error($children) && !empty($children)) {
                    foreach ($children as $cid) {
                        $scope[] = (int) $cid;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($scope)));
    }

    /**
     * Query por estado (escopo cidade: pai + filhos) + modalidade
     */
    private function queryByEstadoAndModalidade()
    {
        $cidade_scope_ids = $this->getEstadoCidadeScopeIds();
        $modalidade_ids = $this->getModalidadeIds();

        if (empty($cidade_scope_ids) || empty($modalidade_ids)) {
            $this->debug['estado_modalidade_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        $args['tax_query'][] = array(
            'taxonomy' => 'cidade',
            'field' => 'term_id',
            'terms' => $cidade_scope_ids,
        );
        $args['tax_query'][] = array(
            'taxonomy' => 'modalidade',
            'field' => 'term_id',
            'terms' => $modalidade_ids,
        );
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        $this->applyProximityOrdering($args);

        $query = new WP_Query($args);
        $this->debug['estado_modalidade_query'] = $query->request;
        $this->debug['estado_modalidade_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Query por cidade + category (prioridade alta)
     * Ex: bicicletas infantis na mesma cidade
     */
    private function queryByCidadeAndCategory()
    {
        $cidade_ids = $this->getCidadeIds();
        $category_ids = $this->getCategoryIds();

        // Só executar se tiver ambos
        if (empty($cidade_ids) || empty($category_ids)) {
            $this->debug['cidade_category_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        // Adicionar filtros combinados
        $args['tax_query'][] = array(
            'taxonomy' => 'cidade',
            'field' => 'term_id',
            'terms' => $cidade_ids
        );
        $args['tax_query'][] = array(
            'taxonomy' => 'category',
            'field' => 'term_id',
            'terms' => $category_ids
        );
        // Definir relação AND para combinar filtros
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        // Aplicar ordenação por proximidade
        $this->applyProximityOrdering($args);

        $query = new WP_Query($args);
        $this->debug['cidade_category_query'] = $query->request;
        $this->debug['cidade_category_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Query por cidade
     * Prioridade 3: Apenas localização
     */
    private function queryByCidade()
    {
        $cidade_ids = $this->getCidadeIds();
        if (empty($cidade_ids)) {
            $this->debug['cidade_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        // Adicionar filtro de cidade ao tax_query existente
        $args['tax_query'][] = array(
            'taxonomy' => 'cidade',
            'field' => 'term_id',
            'terms' => $cidade_ids
        );
        // Definir relação AND quando há múltiplos filtros
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        // Aplicar ordenação por proximidade (compatível com anúncios novos e antigos)
        $this->applyProximityOrdering($args);

        $query = new WP_Query($args);
        $this->debug['cidade_query'] = $query->request;
        $this->debug['cidade_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Query por marca-modelo
     * Prioridade 4: Após cidade, category e modalidade
     */
    private function queryByMarcaModelo()
    {
        $marca_modelo_ids = $this->getMarcaModeloIds();
        if (empty($marca_modelo_ids)) {
            $this->debug['marca_modelo_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        // Adicionar filtro de marca-modelo ao tax_query existente
        $args['tax_query'][] = array(
            'taxonomy' => 'marca-modelo',
            'field' => 'term_id',
            'terms' => $marca_modelo_ids
        );
        // Definir relação AND quando há múltiplos filtros
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        // Aplicar ordenação por proximidade (compatível com anúncios novos e antigos)
        $this->applyProximityOrdering($args);

        $query = new WP_Query($args);
        $this->debug['marca_modelo_query'] = $query->request;
        $this->debug['marca_modelo_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Query por category
     * Prioridade 2: Após cidade
     */
    private function queryByCategory()
    {
        $category_ids = $this->getCategoryIds();
        if (empty($category_ids)) {
            $this->debug['category_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        // Adicionar filtro de category ao tax_query existente
        $args['tax_query'][] = array(
            'taxonomy' => 'category',
            'field' => 'term_id',
            'terms' => $category_ids
        );
        // Definir relação AND quando há múltiplos filtros
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        // Aplicar ordenação por proximidade (compatível com anúncios novos e antigos)
        $this->applyProximityOrdering($args);

        $query = new WP_Query($args);
        $this->debug['category_query'] = $query->request;
        $this->debug['category_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Query por modalidade
     * Prioridade 3: Após cidade e category
     */
    private function queryByModalidade()
    {
        $modalidade_ids = $this->getModalidadeIds();
        if (empty($modalidade_ids)) {
            $this->debug['modalidade_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        // Adicionar filtro de modalidade ao tax_query existente
        $args['tax_query'][] = array(
            'taxonomy' => 'modalidade',
            'field' => 'term_id',
            'terms' => $modalidade_ids
        );
        // Definir relação AND quando há múltiplos filtros
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        // Aplicar ordenação por proximidade (compatível com anúncios novos e antigos)
        $this->applyProximityOrdering($args);

        $query = new WP_Query($args);
        $this->debug['modalidade_query'] = $query->request;
        $this->debug['modalidade_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Constrói argumentos base para WP_Query
     * 
     * @return array
     */
    private function buildBaseQueryArgs()
    {
        $exclude_ids = array_merge(array($this->post_id), $this->exclude_ids);

        $args = array(
            'post_type' => $this->post_type,
            'posts_per_page' => $this->getRemainingPosts(),
            'post_status' => 'publish',
            'post__not_in' => array_unique(array_filter($exclude_ids)),
            // Usar randomização para variar os produtos relacionados
            // Cada produto terá uma lista diferente de relacionados
            'orderby' => 'rand'
        );

        // Excluir posts vendidos (taxonomia 'status' com termo 'vendido')
        if (in_array('post', $this->post_type)):
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'status',
                    'field' => 'slug',
                    'terms' => 'vendido',
                    'operator' => 'NOT IN'
                )
            );
        endif;

        // NÃO aplicar ordenação por destaque aqui
        // Produtos relacionados devem priorizar RELEVÂNCIA (categoria/modalidade/cidade)
        // Destaques terão seu próprio slide separado

        $this->debug['query_args'] = $args;
        return $args;
    }

    /**
     * Obtém IDs de cidade do produto (usando helper)
     * 
     * @return array
     */
    private function getCidadeIds()
    {
        // Se não tem product_data (ads ou erro), buscar diretamente
        if (empty($this->product_data)) {
            $terms = get_the_terms($this->post_id, 'cidade');
            if (!$terms || is_wp_error($terms)) {
                return array();
            }

            $cidade_ids = array();
            foreach ($terms as $term) {
                // Apenas termos filhos (parent != 0)
                if ($term->parent != 0) {
                    $cidade_ids[] = $term->term_id;
                }
            }
            return $cidade_ids;
        }

        $cidade_terms = isset($this->product_data['taxonomies']['cidade'])
            ? $this->product_data['taxonomies']['cidade']
            : array();

        if (empty($cidade_terms)) {
            return array();
        }

        $cidade_ids = array();
        foreach ($cidade_terms as $term) {
            // Apenas termos filhos (parent != 0)
            if ($term->parent != 0) {
                $cidade_ids[] = $term->term_id;
            }
        }

        return $cidade_ids;
    }

    /**
     * Obtém IDs de category do produto (usando helper)
     * Inclui termos filhos (ex: "Infantil" filho de "Bicicleta")
     * 
     * @return array
     */
    private function getCategoryIds()
    {
        // Se não tem product_data (ads ou erro), buscar diretamente
        if (empty($this->product_data)) {
            $terms = get_the_terms($this->post_id, 'category');
            if (!$terms || is_wp_error($terms)) {
                return array();
            }

            $category_ids = array();
            foreach ($terms as $term) {
                // Incluir todos os termos (pais e filhos) para category
                // Isso permite encontrar outras bicicletas infantis, por exemplo
                $category_ids[] = $term->term_id;
            }
            return $category_ids;
        }

        $category_terms = isset($this->product_data['taxonomies']['category'])
            ? $this->product_data['taxonomies']['category']
            : array();

        if (empty($category_terms)) {
            return array();
        }

        $category_ids = array();
        foreach ($category_terms as $term) {
            // Incluir todos os termos (pais e filhos) para category
            // Isso permite encontrar outras bicicletas infantis, por exemplo
            $category_ids[] = $term->term_id;
        }

        return $category_ids;
    }

    /**
     * Obtém IDs de marca-modelo do produto (usando helper)
     * 
     * @return array
     */
    private function getMarcaModeloIds()
    {
        // Se não tem product_data (ads ou erro), buscar diretamente
        if (empty($this->product_data)) {
            $terms = get_the_terms($this->post_id, 'marca-modelo');
            if (!$terms || is_wp_error($terms)) {
                return array();
            }

            $marca_modelo_ids = array();
            foreach ($terms as $term) {
                // Incluir todos os termos (pais e filhos) para marca-modelo
                $marca_modelo_ids[] = $term->term_id;
            }
            return $marca_modelo_ids;
        }

        $marca_modelo_terms = isset($this->product_data['taxonomies']['marca-modelo'])
            ? $this->product_data['taxonomies']['marca-modelo']
            : array();

        if (empty($marca_modelo_terms)) {
            return array();
        }

        $marca_modelo_ids = array();
        foreach ($marca_modelo_terms as $term) {
            // Incluir todos os termos (pais e filhos) para marca-modelo
            $marca_modelo_ids[] = $term->term_id;
        }

        return $marca_modelo_ids;
    }

    /**
     * Obtém IDs de modalidade do produto (usando helper)
     * 
     * @return array
     */
    private function getModalidadeIds()
    {
        if (empty($this->product_data)) {
            $terms = get_the_terms($this->post_id, 'modalidade');
            if (!$terms || is_wp_error($terms)) {
                return array();
            }
            return array_map('intval', wp_list_pluck($terms, 'term_id'));
        }

        $modalidade_terms = isset($this->product_data['taxonomies']['modalidade'])
            ? $this->product_data['taxonomies']['modalidade']
            : array();

        if (empty($modalidade_terms)) {
            return array();
        }

        $modalidade_ids = array();
        foreach ($modalidade_terms as $term) {
            $modalidade_ids[] = (int) $term->term_id;
        }

        return $modalidade_ids;
    }

    /**
     * Ordena relacionados de produto: mesmo grupo de preço do anúncio atual primeiro (por proximidade de valor),
     * depois demais grupos por "vizinhança" na escada 1–4 (Opção 1), sem misturar blocos.
     *
     * @param \WP_Post[] $posts
     * @return \WP_Post[]
     */
    private function order_related_posts_by_price_bands(array $posts)
    {
        $posts = array_values(array_filter($posts));
        if ($posts === array()) {
            return $posts;
        }

        $anchor_val = $this->resolve_reference_valor_for_price_band();
        $anchor_group = $this->get_price_group_for_valor($anchor_val);
        if ($anchor_group === null) {
            return $posts;
        }

        $related_ids = array_values(
            array_filter(array_map('intval', wp_list_pluck($posts, 'ID')))
        );
        if ($related_ids !== array()) {
            update_postmeta_cache($related_ids);
        }

        $valor_por_post_id = $this->map_valor_por_post_id($related_ids);

        $partition = $this->partition_related_rows_by_price_band(
            $posts,
            $valor_por_post_id,
            $anchor_group
        );

        return $this->flatten_partitioned_rows_by_price_band(
            $partition,
            $anchor_group,
            $anchor_val
        );
    }

    /**
     * Valor de referência do anúncio atual (ACF já carregado em product_data quando existir).
     *
     * @return float|null
     */
    private function resolve_reference_valor_for_price_band()
    {
        $post_id = (int) $this->post_id;
        if (!empty($this->product_data) && isset($this->product_data['fields']['valor'])) {
            $fv = $this->product_data['fields']['valor'];
            if ($fv !== '' && $fv !== null && $fv !== false && is_numeric($fv)) {
                return (float) $fv;
            }
        }
        if ($post_id > 0) {
            return $this->read_post_valor_meta($post_id);
        }
        return null;
    }

    /**
     * @param int[] $post_ids
     * @return array<int, float|null>
     */
    private function map_valor_por_post_id(array $post_ids)
    {
        $map = array();
        foreach ($post_ids as $id) {
            $map[(int) $id] = $this->read_post_valor_meta((int) $id);
        }
        return $map;
    }

    /**
     * @param \WP_Post[] $posts
     * @param array<int, float|null> $valor_por_post_id
     * @param int $anchor_group
     * @return array{same: array, by_group: array<int, array>, none: array}
     */
    private function partition_related_rows_by_price_band(array $posts, array $valor_por_post_id, $anchor_group)
    {
        $same = array();
        $by_group = array(1 => array(), 2 => array(), 3 => array(), 4 => array());
        $none = array();

        foreach ($posts as $post) {
            if (!is_object($post) || !isset($post->ID)) {
                continue;
            }
            $pid = (int) $post->ID;
            $v = isset($valor_por_post_id[$pid]) ? $valor_por_post_id[$pid] : $this->read_post_valor_meta($pid);
            $g = $this->get_price_group_for_valor($v);
            $row = array('post' => $post, 'val' => $v);
            if ($g === $anchor_group) {
                $same[] = $row;
            } elseif ($g === null) {
                $none[] = $row;
            } else {
                $by_group[$g][] = $row;
            }
        }

        return array(
            'same' => $same,
            'by_group' => $by_group,
            'none' => $none,
        );
    }

    /**
     * @param array{same: array, by_group: array<int, array>, none: array} $partition
     * @param int $anchor_group
     * @param float|null $anchor_val
     * @return \WP_Post[]
     */
    private function flatten_partitioned_rows_by_price_band(array $partition, $anchor_group, $anchor_val)
    {
        $ordered = $this->sort_price_band_rows_and_pluck_posts($partition['same'], $anchor_val);
        foreach ($this->get_other_price_groups_by_neighborhood($anchor_group) as $g) {
            $ordered = array_merge(
                $ordered,
                $this->sort_price_band_rows_and_pluck_posts($partition['by_group'][$g], $anchor_val)
            );
        }
        return array_merge(
            $ordered,
            $this->sort_price_band_rows_and_pluck_posts($partition['none'], $anchor_val)
        );
    }

    /**
     * @param array<int, array{post: \WP_Post, val: float|null}> $rows
     * @param float|null $anchor_val
     * @return \WP_Post[]
     */
    private function sort_price_band_rows_and_pluck_posts(array $rows, $anchor_val)
    {
        if ($rows === array()) {
            return array();
        }
        usort(
            $rows,
            function ($a, $b) use ($anchor_val) {
                $dx = $this->price_distance_to_anchor($a['val'], $anchor_val);
                $dy = $this->price_distance_to_anchor($b['val'], $anchor_val);
                if ($dx !== $dy) {
                    return $dx <=> $dy;
                }
                return $a['post']->ID <=> $b['post']->ID;
            }
        );
        return array_column($rows, 'post');
    }

    /**
     * @param int $post_id
     * @return float|null
     */
    private function read_post_valor_meta($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return null;
        }
        $raw = get_post_meta($post_id, 'valor', true);
        if ($raw === '' || $raw === null || !is_numeric($raw)) {
            return null;
        }
        return (float) $raw;
    }

    /**
     * @param float|null $valor
     * @return int|null Grupo 1–4 ou null se inválido
     */
    private function get_price_group_for_valor($valor)
    {
        if ($valor === null || !is_numeric($valor)) {
            return null;
        }
        $v = (float) $valor;
        if ($v < 0) {
            return null;
        }
        if ($v < self::PRICE_BAND_UPPER_1) {
            return 1;
        }
        if ($v < self::PRICE_BAND_UPPER_2) {
            return 2;
        }
        if ($v < self::PRICE_BAND_UPPER_3) {
            return 3;
        }
        return 4;
    }

    /**
     * @param int $anchor_group 1–4
     * @return int[]
     */
    private function get_other_price_groups_by_neighborhood($anchor_group)
    {
        $all = array(1, 2, 3, 4);
        $others = array_values(
            array_filter(
                $all,
                function ($g) use ($anchor_group) {
                    return (int) $g !== (int) $anchor_group;
                }
            )
        );
        usort(
            $others,
            function ($a, $b) use ($anchor_group) {
                $da = abs((int) $a - (int) $anchor_group);
                $db = abs((int) $b - (int) $anchor_group);
                if ($da !== $db) {
                    return $da <=> $db;
                }
                return $a <=> $b;
            }
        );
        return $others;
    }

    /**
     * @param float|null $val
     * @param float|null $anchor_val
     * @return float|int
     */
    private function price_distance_to_anchor($val, $anchor_val)
    {
        if ($val === null || $anchor_val === null || !is_numeric($val) || !is_numeric($anchor_val)) {
            return PHP_INT_MAX;
        }
        return abs((float) $val - (float) $anchor_val);
    }

    /**
     * @return bool
     */
    private function should_apply_price_band_ordering()
    {
        if (!in_array('post', $this->post_type, true)) {
            return false;
        }
        return $this->get_price_group_for_valor($this->resolve_reference_valor_for_price_band()) !== null;
    }

    /**
     * Embaralha posts de relacionados.
     * Quando $use_tier_salt é true (fluxo produto em camadas), cada chamada usa seed diferente
     * para variar a ordem dentro da camada sem misturar camadas entre si.
     *
     * @param \WP_Post[] $posts
     * @param bool       $use_tier_salt
     * @return \WP_Post[]
     */
    private function shuffleRelatedPosts(array $posts, $use_tier_salt = false)
    {
        $posts = array_values(array_filter($posts));
        if (count($posts) < 2) {
            return $posts;
        }

        if ($use_tier_salt) {
            $this->tier_shuffle_counter++;
            $seed = (int) $this->post_id + (int) (time() / 86400) + ($this->tier_shuffle_counter * 100003);
        } else {
            $seed = (int) $this->post_id + (int) (time() / 86400);
        }

        mt_srand($seed);
        shuffle($posts);
        mt_srand();

        return $posts;
    }

    /**
     * Executa uma query e mescla resultados
     * 
     * @param WP_Query $query
     */
    private function executeQuery($query)
    {
        if ($query->have_posts()) {
            if ($this->tiered_shuffle_posts && !$this->should_apply_price_band_ordering()) {
                $query->posts = $this->shuffleRelatedPosts($query->posts, true);
                $query->post_count = count($query->posts);
            }
            if (is_null($this->query)) {
                $this->query = $query;
            } else {
                $this->mergeQueries($query);
            }
            $this->debug['query_success'] = true;
        } else {
            $this->debug['query_empty'] = true;
        }
    }

    /**
     * Mescla resultados de uma nova query com a query existente
     * 
     * @param WP_Query $new_query
     */
    private function mergeQueries($new_query)
    {
        $existing_ids = $this->getExistingPostIds();
        $new_posts = array_filter($new_query->posts, function ($post) use ($existing_ids) {
            return !in_array($post->ID, $existing_ids);
        });
        $new_posts = array_values($new_posts);

        if ($this->tiered_shuffle_posts && !empty($new_posts) && !$this->should_apply_price_band_ordering()) {
            $new_posts = $this->shuffleRelatedPosts($new_posts, true);
        }

        if (!empty($new_posts)) {
            $this->query->posts = array_merge($this->query->posts, $new_posts);
            $this->query->post_count = count($this->query->posts);
            $this->debug['merged_posts'] = count($new_posts);
        }
    }

    /**
     * Obtém IDs dos posts já incluídos na query
     * 
     * @return array
     */
    private function getExistingPostIds()
    {
        return is_null($this->query) ? array() : wp_list_pluck($this->query->posts, 'ID');
    }

    /**
     * Calcula quantos posts ainda são necessários
     * 
     * @return int
     */
    private function getRemainingPosts()
    {
        $current_count = $this->getPostCount();
        return max(0, $this->post_count - $current_count);
    }

    /**
     * Verifica se ainda são necessários mais posts
     * 
     * @return bool
     */
    private function needsMorePosts()
    {
        return $this->getPostCount() < $this->post_count;
    }

    /**
     * Obtém contagem atual de posts
     * 
     * @return int
     */
    private function getPostCount()
    {
        return is_null($this->query) ? 0 : count($this->query->posts);
    }

    /**
     * Retorna a query gerada
     * 
     * @return WP_Query|false
     */
    public function getQuery()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // error_log('RelatedTaxQuery Debug: ' . print_r($this->debug, true));
        }

        if (!$this->query || !is_object($this->query)) {
            return false;
        }

        return $this->query;
    }

    /**
     * Define se deve embaralhar os resultados
     * 
     * @param bool $shuffle
     * @return void
     */
    public function setShuffle($shuffle = true)
    {
        $this->shuffle = (bool) $shuffle;
    }

    /**
     * Aplica ordenação por proximidade nas queries de posts relacionados
     * Compatível com anúncios novos (meta fields) e antigos (taxonomia cidade)
     * 
     * @param array $args Argumentos da WP_Query (será modificado por referência)
     * @return void
     */
    private function applyProximityOrdering(&$args)
    {
        // Desativado (opção A): mesmo critério do archive — sem ordenação por CEP/JOINs; ordem vem da query base.
        return;
    }

    /**
     * Query por category para blog
     */
    private function queryByCategoryForBlog()
    {
        $category_ids = $this->getCategoryIdsForBlog();
        if (empty($category_ids)) {
            $this->debug['category_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        $args['tax_query'][] = array(
            'taxonomy' => 'category',
            'field' => 'term_id',
            'terms' => $category_ids
        );
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        $query = new WP_Query($args);
        $this->debug['category_query'] = $query->request;
        $this->debug['category_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Query por cidade para blog
     */
    private function queryByCidadeForBlog()
    {
        $cidade_ids = $this->getCidadeIdsForBlog();
        if (empty($cidade_ids)) {
            $this->debug['cidade_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        $args['tax_query'][] = array(
            'taxonomy' => 'cidade',
            'field' => 'term_id',
            'terms' => $cidade_ids
        );
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        $query = new WP_Query($args);
        $this->debug['cidade_query'] = $query->request;
        $this->debug['cidade_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Query por marca-modelo para blog
     */
    private function queryByMarcaModeloForBlog()
    {
        $marca_modelo_ids = $this->getMarcaModeloIdsForBlog();
        if (empty($marca_modelo_ids)) {
            $this->debug['marca_modelo_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        $args['tax_query'][] = array(
            'taxonomy' => 'marca-modelo',
            'field' => 'term_id',
            'terms' => $marca_modelo_ids
        );
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        $query = new WP_Query($args);
        $this->debug['marca_modelo_query'] = $query->request;
        $this->debug['marca_modelo_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Query por modalidade para blog
     */
    private function queryByModalidadeForBlog()
    {
        $modalidade_ids = $this->getModalidadeIdsForBlog();
        if (empty($modalidade_ids)) {
            $this->debug['modalidade_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        $args['tax_query'][] = array(
            'taxonomy' => 'modalidade',
            'field' => 'term_id',
            'terms' => $modalidade_ids
        );
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        $query = new WP_Query($args);
        $this->debug['modalidade_query'] = $query->request;
        $this->debug['modalidade_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Query por componente para blog
     */
    private function queryByComponenteForBlog()
    {
        $componente_ids = $this->getComponenteIdsForBlog();
        if (empty($componente_ids)) {
            $this->debug['componente_empty'] = true;
            return;
        }

        $args = $this->buildBaseQueryArgs();
        $args['tax_query'][] = array(
            'taxonomy' => 'componente',
            'field' => 'term_id',
            'terms' => $componente_ids
        );
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        $query = new WP_Query($args);
        $this->debug['componente_query'] = $query->request;
        $this->debug['componente_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Query fallback para blog (outros posts blog aleatórios)
     */
    private function queryByFallbackForBlog()
    {
        $args = $this->buildBaseQueryArgs();
        // Sem filtros de taxonomia, apenas posts do tipo blog

        $query = new WP_Query($args);
        $this->debug['fallback_query'] = $query->request;
        $this->debug['fallback_found'] = $query->found_posts;

        $this->executeQuery($query);
    }

    /**
     * Obtém IDs de category para blog (busca direta, sem helper)
     */
    private function getCategoryIdsForBlog()
    {
        $terms = get_the_terms($this->post_id, 'category');
        if (!$terms || is_wp_error($terms)) {
            return array();
        }

        $category_ids = array();
        foreach ($terms as $term) {
            $category_ids[] = $term->term_id;
        }

        return $category_ids;
    }

    /**
     * Obtém IDs de cidade para blog (busca direta, sem helper)
     */
    private function getCidadeIdsForBlog()
    {
        $terms = get_the_terms($this->post_id, 'cidade');
        if (!$terms || is_wp_error($terms)) {
            return array();
        }

        $cidade_ids = array();
        foreach ($terms as $term) {
            // Apenas termos filhos (parent != 0) para cidade
            if ($term->parent != 0) {
                $cidade_ids[] = $term->term_id;
            }
        }

        return $cidade_ids;
    }

    /**
     * Obtém IDs de marca-modelo para blog (busca direta, sem helper)
     */
    private function getMarcaModeloIdsForBlog()
    {
        $terms = get_the_terms($this->post_id, 'marca-modelo');
        if (!$terms || is_wp_error($terms)) {
            return array();
        }

        $marca_modelo_ids = array();
        foreach ($terms as $term) {
            $marca_modelo_ids[] = $term->term_id;
        }

        return $marca_modelo_ids;
    }

    /**
     * Obtém IDs de modalidade para blog (busca direta, sem helper)
     */
    private function getModalidadeIdsForBlog()
    {
        $terms = get_the_terms($this->post_id, 'modalidade');
        if (!$terms || is_wp_error($terms)) {
            return array();
        }

        $modalidade_ids = array();
        foreach ($terms as $term) {
            $modalidade_ids[] = $term->term_id;
        }

        return $modalidade_ids;
    }

    /**
     * Obtém IDs de componente para blog (busca direta, sem helper)
     */
    private function getComponenteIdsForBlog()
    {
        $terms = get_the_terms($this->post_id, 'componente');
        if (!$terms || is_wp_error($terms)) {
            return array();
        }

        $componente_ids = array();
        foreach ($terms as $term) {
            $componente_ids[] = $term->term_id;
        }

        return $componente_ids;
    }

    /**
     * Retorna informações de debug
     * 
     * @return array
     */
    public function getDebug()
    {
        return $this->debug;
    }
}
?>