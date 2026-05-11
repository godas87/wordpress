<?php
/**
 * Classe para gerenciar meta tags SEO do site
 * Refatorada para melhor organização, reutilização e manutenibilidade
 * 
 * @package XXXXXX
 */
class __Bazar_SEO_Meta
{

    // === CONSTANTES ===
    private const DEFAULT_TITLE = 'Bicicletas novas e Bicicletas usadas';
    private const DEFAULT_IMAGE = 'https://XXXXXX/src/imgs/bazar-bikes-groups.jpg';
    private const DEFAULT_DESCRIPTION = "Classificado de bicicletas novas e bicicletas usadas, peças e acessórios. Anuncie grátis, sem pagar comissões. Para comprar e vender bicicletas com segurança e praticidade.";
    private const SITE_NAME = 'Bazar Bikes';
    private const BRAND_SUFFIX = ' | Bazar Bikes';
    private const MIN_TITLE_LENGTH = 51;

    // === PROPRIEDADES ===
    private $title = '';
    private $description;
    private $site_description;
    private $url;
    private $img;
    private $img_thumb;
    private $meta_type;
    private $author_name;
    private $author_city;
    private $author_fone;
    private $key;
    private $tags = array();
    private $hide_robots = false;
    private $term;
    private $search_params_array = array(
        'category',
        'componente',
        'modalidade',
        'marca-modelo',
        'conservacao',
        'cor',
        'material',
        'genero',
        'idade',
        'negociacao',
        'valor_faixa',
        's',
        'codigo',
    );
    private $control_params_array = array(
        'order',
        'paged',
        'page',
        'post_id',
        'token',
        'preview'
    );
    private $tracking_params_array = array(
        'gclid',
        'fbclid',
        'msclkid'
    );

    private $taxonomies_to_index = array(
        'modalidade',
        'marca-modelo',
        'cidade',
        'category',
        'componente'
    );

    // Cache para product_data (evita queries duplicadas)
    private $product_data = null;
    private $current_post_id = null;

    // Cache para termos já buscados (evita queries duplicadas em bazar_build_archive_queries)
    // Estrutura: ['taxonomy_name' => ['slug' => WP_Term, ...]]
    private $cached_terms = array();

    public function __construct()
    {
        $this->site_description = get_bloginfo('description');
        $this->init();
    }

    /**
     * Método principal de inicialização
     * Detecta o tipo de página e delega para método específico
     */
    public function init()
    {
        $this->detect_page_type_and_init();
        $this->set_defaults();
    }

    /**
     * Router: detecta tipo de página e chama método correspondente
     * IMPORTANTE: Verificar taxonomias ANTES de archives genéricos
     * IMPORTANTE: Verificar parâmetros GET ANTES de singular para evitar detectar busca como produto
     */
    private function detect_page_type_and_init()
    {
        // Verificar páginas específicas primeiro (antes de verificar parâmetros de busca)
        if (is_page('feedback-vendido')) {
            $this->init_singular_page();
            return;
        }

        // /bicicleta-modalidade/ (índice de modalidades)
        if ((int) get_query_var('bazar_modalidade_archive') === 1) {
            $this->init_modalidade_archive();
            return;
        }

        // Verificar se há parâmetros GET de busca/filtro (prioridade sobre singular)
        if ($this->has_search_params()) {
            // Se há parâmetros de busca, tratar como archive/home com filtros
            if (is_category() || is_tax()) {
                $this->init_taxonomy();
            } elseif (is_author()) {
                // is_author() implica is_archive(); prioridade sobre init_home/init_archive
                $this->init_author();
            } elseif (is_home() || is_archive()) {
                $this->init_home();
            } else {
                // Fallback: tratar como archive genérico
                $this->init_archive();
            }
            return;
        }

        if (is_front_page()) {
            $this->init_front_page();
        } elseif (is_category() || is_tax()) {
            // Verificar taxonomias ANTES de archives genéricos
            $this->init_taxonomy();
        } elseif (is_author()) {
            // Arquivo de autor: is_archive() também é true; tratar antes de init_archive()
            $this->init_author();
        } elseif (is_post_type_archive()) {
            $this->init_post_type_archive();
        } elseif (is_archive()) {
            $this->init_archive();
        } elseif (is_singular('post')) {
            $this->init_singular_post();
        } elseif (is_singular('blog')) {
            $this->init_singular_blog();
        } elseif (is_singular('web-stories')) {
            $this->init_singular_web_stories();
        } elseif (is_single() || is_page()) {
            $this->init_singular_page();
        } elseif (is_home()) {
            $this->init_home();
        } elseif (is_404()) {
            $this->init_404();
        }
    }

    /**
     * Verifica se há parâmetros GET de busca/filtro na URL
     * @return bool True se há parâmetros de busca
     */
    private function has_search_params()
    {

        if (empty($_GET)) {
            return false;
        }

        // Lista de parâmetros que indicam busca/filtro (formato antigo, mantido para compatibilidade)
        $params_to_check = $this->search_params_array;
        foreach ($_GET as $key => $value) {
            // Ignorar campos de controle vazios
            if (empty($value) && !in_array($key, $this->control_params_array)) {
                continue;
            }
            // Se for um parâmetro de busca conhecido
            if (in_array($key, $params_to_check)) {
                return true;
            }
            // Se terminar com _filter (padrão novo: {taxonomy}_filter)
            if (strpos($key, '_filter') !== false) {
                // Ignorar campos de controle
                if (!in_array($key, $this->control_params_array)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Verifica se a query string contém parâmetros não relacionados a tracking.
     * Parâmetros de tracking (utm_*, gclid, fbclid...) são ignorados para robots.
     *
     * @return bool True quando há parâmetros relevantes para noindex
     */
    private function has_non_tracking_query_params()
    {
        if (empty($_GET)) {
            return false;
        }

        foreach ($_GET as $key => $value) {
            $normalized_key = sanitize_key((string) $key);

            if ($this->is_tracking_param($normalized_key)) {
                continue;
            }

            if (is_array($value)) {
                $has_non_empty_value = false;
                foreach ($value as $item) {
                    if (!empty($item)) {
                        $has_non_empty_value = true;
                        break;
                    }
                }

                if ($has_non_empty_value) {
                    return true;
                }
                continue;
            }

            if (!empty($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determina se o parâmetro é apenas de tracking/campanha.
     *
     * @param string $key Nome do parâmetro da query string
     * @return bool
     */
    private function is_tracking_param($key)
    {
        return strpos($key, 'utm_') === 0
            || in_array($key, $this->tracking_params_array, true);
    }

    // === MÉTODOS POR TIPO DE PÁGINA ===

    /**
     * Inicializa meta tags para página inicial
     */
    private function init_front_page()
    {
        $this->title = self::SITE_NAME . ' | Classificado de bicicletas, anuncie grátis.';
        $this->description = $this->site_description;
        $this->key = "Classificado de Bicicletas"; // Corrigido typo: "Bicilcetas" → "Bicicletas"
    }

    /**
     * Inicializa meta tags para /bicicleta-modalidade/ (todas as modalidades)
     */
    private function init_modalidade_archive()
    {
        $this->title = 'Bicicletas todas Modalidades | Novas e Usadas';
        $this->description = 'Encontre a bicicleta ideal no XXXXXX. Explore nossa seleção por modalidade: MTB, speed, gravel, urbana e mais. Bikes usadas e seminovas com os melhores preços.';
        $this->key = 'XXXXXX, bicicletas por modalidade, MTB, speed, gravel, bicicletas usadas, bikes seminovas';
    }

    /**
     * Inicializa meta tags para arquivos
     */
    private function init_archive()
    {
        $post_type = get_post_type();
        if ($post_type) {
            $post_type_obj = get_post_type_object($post_type);
            if ($post_type_obj) {
                $this->title = $post_type_obj->labels->name . ' | Novas e Usadas';
                $this->description = 'Encontre a bicicleta ideal no XXXXXX. Explore nossa seleção de bikes usadas e seminovas com os melhores preços. Filtre por preço, modalidade, conservação e muito mais.';
                $this->key = "XXXXXX, " . $post_type_obj->labels->name . ", bicicletas usadas, bikes seminovas, compra de bicicleta";
            } else {
                $this->title = 'Bicicletas | Novas e Usadas';
                $this->description = 'Encontre a bicicleta ideal no XXXXXX. Explore nossa seleção de bikes usadas e seminovas com os melhores preços.';
                $this->key = "XXXXXX, bicicletas usadas, bikes seminovas, compra de bicicleta";
            }
        }
    }

    /**
     * Inicializa meta tags para posts (produtos).
     * Usa bazar_get_product_data() (CONTEXT_FULL): no header o SEO roda antes do Schema e do template single;
     * uma única carga preenche o cache do repositório para JSON-LD e para o corpo da página.
     */
    private function init_singular_post()
    {
        $product_data = $this->get_product_data();
        if (!$product_data) {
            return;
        }

        // Verificar se produto foi vendido
        $is_vendido = $this->is_product_sold($product_data);
        if ($is_vendido) {
            $this->hide_robots = true;
        }

        // Montar descrição usando dados do helper
        $this->description = $this->build_product_description($product_data);

        // Imagens usando dados do helper
        $this->set_product_images($product_data);

        // Tags relacionadas
        $this->set_product_tags();

        $this->meta_type = 'product';
    }

    /**
     * Inicializa meta tags para páginas específicas
     */
    private function init_singular_page()
    {
        $post_id = get_the_ID();

        // Páginas específicas
        if (is_page('anunciar')) {
            $this->title = 'Anuncie grátis, sem pagar comissões';
            $this->description = 'Anuncie grátis, sem pagar comissões. ' . $this->site_description;
            $this->key = $this->title;
            $this->set_featured_image($post_id);
        } elseif (is_page('bicicleta-usada')) {
            $this->title = get_the_title() . ' | Classificado de Bicicletas';
            $this->key = "Bicicleta Usada";
            $this->set_featured_image($post_id);
        } elseif (is_page('bazar-do-ciclista')) {
            // SEO otimizado para capturar tráfego do concorrente
            $this->title = 'Bazar do Ciclista - Classificado de Bicicletas Grátis | XXXXXX';
            $this->description = 'Bazar do Ciclista: encontre e anuncie bicicletas, peças e acessórios grátis. O melhor classificado de bicicletas do Brasil. 100% gratuito, sem comissões.';
            $this->key = 'Bazar do Ciclista, Classificado de Bicicletas, Bicicletas Usadas, Anunciar Bicicleta Grátis';
            $this->set_featured_image($post_id);
        } elseif (is_page('feedback-vendido')) {
            // Página de feedback (privada, não indexar)
            $this->title = 'Feedback de Venda | ' . self::SITE_NAME;
            $this->description = 'Compartilhe sua experiência de venda no ' . self::SITE_NAME . '. Sua opinião é muito importante para melhorarmos nossos serviços.';
            $this->key = 'Feedback, Avaliação, Bazar Bikes';
            $this->hide_robots = true; // Não indexar página privada com token
            $this->set_featured_image($post_id);
        } else {
            // Páginas genéricas
            $this->set_excerpt_as_description();
            $this->set_featured_image($post_id);
        }
    }

    /**
     * Inicializa meta tags para posts do blog
     */
    private function init_singular_blog()
    {
        $this->set_excerpt_as_description();
        $this->set_featured_image(get_the_ID());
    }

    /**
     * Inicializa meta tags para web stories.
     * Web stories com mais de 2 meses: noindex para não indexar na busca principal.
     */
    private function init_singular_web_stories()
    {
        $this->title = get_the_title();
        $this->img = get_the_post_thumbnail_url(get_the_ID(), 'l');
        $this->key = self::DEFAULT_TITLE;

        $post_id = get_the_ID();
        $post_date = get_post_time('U', true, $post_id);
        $cutoff = strtotime('-2 months');
        if ($post_date && $post_date < $cutoff) {
            $this->hide_robots = true;
        }
    }

    /**
     * Inicializa meta tags para página home/blog
     */
    private function init_home()
    {
        $this->key = "Bicicletas usadas e bicicletas novas";

        // Verificar se há parâmetros de busca
        $has_search = $this->has_search_params();

        if ($has_search) {
            // Se há parâmetros de busca, construir título baseado nos filtros
            $this->build_search_title_from_get_params();
        } else {
            // Sem filtros: título padrão
            $this->title = self::DEFAULT_TITLE;
            $this->description = self::DEFAULT_DESCRIPTION;
        }
    }

    /**
     * Inicializa meta tags para taxonomias
     * Aplica lógica consistente para todas as taxonomias
     * Agora considera filtros GET para construir títulos dinâmicos
     */
    private function init_taxonomy()
    {

        $term = get_queried_object();
        if (!$term || is_wp_error($term)) {
            return;
        }

        $this->term = $term;

        // Verificar páginas duplicadas
        $current_url = esc_url($_SERVER['REQUEST_URI']);

        // Por padrão, marcar termos pais como NOINDEX (para evitar duplicação)
        // Mas permitir indexação para taxonomias importantes
        $this->hide_robots = (
            $term->parent == 0
            && !in_array($term->taxonomy, $this->taxonomies_to_index)
        );

        $prefix = $this->build_taxonomy_prefix($term);
        $this->meta_type = 'product';
        $taxonomy = $term->taxonomy;

        // Verificar se há filtros GET (exceto para taxonomias que ignoram filtros)
        $taxonomies_ignore_filters = array('alfabeto');
        $active_filters = array();
        $has_filters = false;

        if (!in_array($taxonomy, $taxonomies_ignore_filters)) {
            $active_filters = $this->get_active_filters_for_title();
            $has_filters = !empty($active_filters);
        }

        // Se houver filtros GET, construir título dinâmico
        if ($has_filters) {
            $this->build_taxonomy_title_with_filters($term, $prefix, $active_filters);
            return;
        }

        // Obter descrição de SEO do term meta (prioridade) ou usar description padrão
        $seo_description = get_term_meta($term->term_id, 'descricao', true);
        if (empty($seo_description)) {
            $seo_description = $term->description;
        }

        // Taxonomias específicas com títulos customizados (sem filtros)
        // Usar switch para evitar múltiplas validações is_tax()
        switch ($taxonomy) {
            case 'marca-modelo':
                $this->title = 'Bicicletas ' . $prefix . ' | Bicicletas Usadas e Novas';
                // Usar descrição de SEO se disponível, senão usar padrão
                $this->description = !empty($seo_description)
                    ? $seo_description
                    : 'Bicicletas ' . $prefix . ' você encontra no ' . self::SITE_NAME . '. ' . self::DEFAULT_DESCRIPTION;
                $this->key = 'Bicicletas ' . $prefix;
                $this->hide_robots = false;
                break;

            case 'cidade':
                $cidade = format_city_state(null, $term);
                $this->title = 'Bicicletas em ' . $cidade . ' | Bicicletas Usadas e Novas';
                $this->description = !empty($seo_description)
                    ? $seo_description
                    : 'Bikes usadas e novas em ' . $cidade . ' você encontra aqui. ' . self::DEFAULT_DESCRIPTION;
                $this->key = 'Bicicletas em ' . $cidade;
                $this->hide_robots = false;
                break;

            case 'modalidade':
                $this->title = 'Bicicletas ' . $prefix . ' | Bicicletas Usadas e Novas';
                $this->description = !empty($seo_description)
                    ? $seo_description
                    : 'Bicicletas ' . $prefix . ' você encontra no ' . self::SITE_NAME . '. ' . self::DEFAULT_DESCRIPTION;
                $this->key = 'Bicicletas ' . $prefix;
                $this->hide_robots = false;
                break;

            case 'alfabeto':
                $this->title = 'Letra ' . $prefix . ' | Glossário de Ciclismo';
                $this->description = !empty($seo_description)
                    ? $seo_description
                    : 'Glossário de ciclismo - Letra ' . $prefix . '. ' . self::DEFAULT_DESCRIPTION;
                $this->key = self::DEFAULT_TITLE;
                break;

            case 'category':
                $this->title = $prefix . ' | Usadas e Novas | ' . self::SITE_NAME;
                $this->description = !empty($seo_description)
                    ? $seo_description
                    : $prefix . ' usadas e novas você encontra no ' . self::SITE_NAME . '. ' . self::DEFAULT_DESCRIPTION;
                $this->key = $prefix . ', usadas, novas';
                $this->hide_robots = false;
                break;

            case 'componente':
                $this->title = $prefix . ' | Bicicletas e Peças | ' . self::SITE_NAME;
                $this->description = !empty($seo_description)
                    ? $seo_description
                    : $prefix . ' para bicicletas você encontra no ' . self::SITE_NAME . '. ' . self::DEFAULT_DESCRIPTION;
                $this->key = $prefix . ', componentes, peças';
                $this->hide_robots = false;
                break;

            case 'acessorio':
                $this->title = $prefix . ' | Acessórios para Bicicleta | ' . self::SITE_NAME;
                $this->description = !empty($seo_description)
                    ? $seo_description
                    : $prefix . ' para bicicletas você encontra no ' . self::SITE_NAME . '. ' . self::DEFAULT_DESCRIPTION;
                $this->key = $prefix . ', acessórios';
                $this->hide_robots = false;
                break;

            default:
                // Taxonomias genéricas: formato padrão
                $taxonomy_obj = get_taxonomy($taxonomy);
                $taxonomy_name = ($taxonomy_obj && isset($taxonomy_obj->labels->singular_name))
                    ? $taxonomy_obj->labels->singular_name
                    : $taxonomy;

                $prefix = "Bicicletas " . $taxonomy_name . ' ' . $prefix;
                $this->title = $prefix . ' | Bicicletas Usadas e Novas';
                $this->description = !empty($seo_description)
                    ? $seo_description
                    : ($term->description != ''
                        ? $prefix . ' | ' . $term->description
                        : $prefix . ' a venda, classificado ' . self::SITE_NAME . '. ' . self::DEFAULT_DESCRIPTION);
                $this->key = 'Bike ' . $prefix;
                break;
        }
    }

    /**
     * Inicializa meta tags para arquivos de post types
     */
    private function init_post_type_archive()
    {
        if (is_post_type_archive('blog')) {
            $this->title = 'Blog ' . self::SITE_NAME . ' | Artigos sobre ciclismo e bicicletas.';
            $this->description = 'Os melhores artigos sobre bike e ciclismo você encontra no Blog do ' . self::SITE_NAME . '. ' . $this->site_description;
            $this->meta_type = 'blog';
            $this->key = 'blog';
        } elseif (is_post_type_archive('glossario-ciclismo')) {
            $this->title = 'Glossário de Ciclismo | Tudo sobre ciclismo e bicicletas.';
            $this->description = self::SITE_NAME . ', Tudo sobre bike e ciclismo. ' . $this->site_description;
            $this->key = 'ciclismo';
        } elseif (is_post_type_archive('web-stories')) {
            $this->title = 'WebStories | Bicicletas ';
            $this->description = 'WebStories ' . self::SITE_NAME . '. ' . $this->site_description;
            $this->key = 'webstories';
        }
    }

    /**
     * Inicializa meta tags para páginas de autor
     */
    private function init_author()
    {
        $author_name = '';
        $author_obj = get_queried_object();
        if ($author_obj instanceof WP_User) {
            $author_name = get_the_author_meta('display_name', $author_obj->ID);
            if ($author_name === '') {
                $author_name = $author_obj->display_name;
            }
        }
        if ($author_name === '') {
            $author_name = get_the_author();
        }
        if ($author_name === '') {
            $author_name = 'Anunciante';
        }
        $this->title = 'Anuncios de ' . $author_name . self::BRAND_SUFFIX;
        $this->description = 'Todas os anúncios de ' . $author_name . '. ' . self::DEFAULT_DESCRIPTION;
        $this->hide_robots = true;
    }

    /**
     * Inicializa meta tags para páginas 404
     */
    private function init_404()
    {
        $this->title = 'Página não encontrada';
        $this->description = 'A página que você procura não está mais aqui, ela foi movida ou removida. ' . $this->site_description;
    }

    // === HELPERS REUTILIZÁVEIS ===

    /**
     * Obtém dados do produto (singular anúncio): mesmo pacote FULL que Schema e single.php reutilizam.
     *
     * @param int|null $post_id ID do post (opcional)
     * @return array|null Dados do produto ou null se não encontrado
     */
    private function get_product_data($post_id = null)
    {

        if ($this->product_data === null) {
            $post_id = $post_id ?: get_the_ID();
            if (!$post_id) {
                return null;
            }

            $this->current_post_id = $post_id;
            $this->product_data = bazar_get_product_data($post_id);

            if (empty($this->product_data)) {
                error_log("[__Bazar_SEO_Meta] Failed to get product data for post ID: {$post_id}");
                return null;
            }
        }
        return $this->product_data;
    }

    /**
     * Verifica se produto foi vendido usando dados do helper
     * @param array $product_data Dados do produto
     * @return bool True se vendido
     */
    private function is_product_sold($product_data)
    {
        if (isset($product_data['taxonomies']['status']) && !empty($product_data['taxonomies']['status'])) {
            foreach ($product_data['taxonomies']['status'] as $status) {
                if ($status->slug === 'vendido' || $status->name === 'vendido') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Monta descrição do produto usando dados do helper
     * @param array $product_data Dados do produto
     * @return string Descrição formatada
     */
    private function build_product_description($product_data)
    {
        $title = $product_data['title'];
        $valor = isset($product_data['fields']['valor']) ? $product_data['fields']['valor'] : '';
        $peso = isset($product_data['fields']['peso']) ? $product_data['fields']['peso'] : '';

        $cor_name = '';
        if (!empty($product_data['taxonomies']['cor']) && isset($product_data['taxonomies']['cor'][0])) {
            $cor_name = $product_data['taxonomies']['cor'][0]->name;
        }

        return 'Compre ' . $title . ' | Valor R$' . $valor . ' | Cor: ' . $cor_name . ' | Peso: ' . $peso . 'g | ' . self::SITE_NAME . ' | ' . $this->site_description;
    }

    /**
     * Define imagens do produto usando dados do helper
     * @param array $product_data Dados do produto
     */
    private function set_product_images($product_data)
    {
        $post_id = get_the_ID();

        // Imagem destacada do helper
        if (!empty($product_data['images']['featured'])) {
            $this->img = $product_data['images']['featured'];
        } elseif (has_post_thumbnail($post_id)) {
            $this->img = get_the_post_thumbnail_url($post_id, 'l');
        }

        // Thumbnail
        if (!empty($product_data['images']['featured_medium'])) {
            $this->img_thumb = $product_data['images']['featured_medium'];
        } elseif (has_post_thumbnail($post_id)) {
            $this->img_thumb = get_the_post_thumbnail_url($post_id, 'm');
        }
    }

    /**
     * Define tags do produto
     */
    private function set_product_tags()
    {
        $post_id = get_the_ID();
        $tags = get_the_terms($post_id, 'post_tag');

        if ($tags && !is_wp_error($tags)) {
            $tag_names = array();
            foreach ($tags as $tag) {
                $tag_names[] = $tag->name;
            }
            $this->key = implode(', ', $tag_names);
            $this->tags = $tags;
        }
    }

    /**
     * Define imagem destacada para um post
     * @param int $post_id ID do post
     * @param string $size_large Tamanho grande (padrão: 'l')
     * @param string $size_medium Tamanho médio (padrão: 'm')
     */
    private function set_featured_image($post_id, $size_large = 'l', $size_medium = 'm')
    {
        if (has_post_thumbnail($post_id)) {
            $this->img = get_the_post_thumbnail_url($post_id, $size_large);
            $this->img_thumb = get_the_post_thumbnail_url($post_id, $size_medium);
        }
    }

    /**
     * Define descrição usando excerpt
     */
    private function set_excerpt_as_description()
    {
        if (has_excerpt()) {
            $this->description = get_the_excerpt();
        }
    }

    /**
     * Constrói título de busca baseado em parâmetros GET
     */
    private function build_search_title_from_get_params()
    {

        if (isset($_GET['s']) && $_GET['s'] != '') {
            $this->title = 'Resultados para: ' . sanitize_text_field($_GET['s']);
            return;
        }

        if (isset($_GET['codigo']) && $_GET['codigo'] != '') {
            $this->title = 'Código: ' . sanitize_text_field($_GET['codigo']);
            return;
        }

        // Verificar se há filtro de categoria
        $category_name = '';
        if (isset($_GET['category']) && !empty($_GET['category'])) {
            $category_slug = is_array($_GET['category']) ? $_GET['category'][0] : $_GET['category'];
            $category_term = get_term_by('slug', sanitize_text_field($category_slug), 'category');
            if ($category_term && !is_wp_error($category_term)) {
                $category_name = $category_term->name;
            }
        }

        // Se há categoria específica, usar formato: "Peças Novas e Usadas | Bazar Bikes"
        if (!empty($category_name)) {
            $this->title = $category_name . ' Novas e Usadas';
            $this->description = $category_name . ' novas e usadas você encontra no ' . self::SITE_NAME . '. ' . self::DEFAULT_DESCRIPTION;
            $this->key = $category_name . ' usadas, ' . $category_name . ' novas';
            return;
        }

        // Construir título baseado em outros parâmetros
        $search_string = '';

        foreach ($_GET as $key => $value) {
            if ($value == '') {
                continue;
            }

            // Ignorar campos de controle
            if (in_array($key, array('category_fields', 'title', 'order', 's', 'codigo', 'paged', 'page'))) {
                continue;
            }

            // Processar filtros no formato {taxonomy}_filter
            if (strpos($key, '_filter') !== false) {
                $taxonomy_name = str_replace('_filter', '', $key);

                // Validar se é uma taxonomia válida
                if (!taxonomy_exists($taxonomy_name)) {
                    continue;
                }

                // Processar valores (podem ser separados por vírgula)
                $values = is_array($value) ? $value : explode(',', $value);
                $values = array_map('trim', $values);
                $values = array_filter($values);

                // Usar apenas o primeiro termo para título
                if (!empty($values)) {
                    $first_slug = sanitize_text_field($values[0]);
                    $term = get_term_by('slug', $first_slug, $taxonomy_name);
                    if ($term && !is_wp_error($term)) {
                        $search_string .= $term->name . ' . ';
                    }
                }
                continue;
            }

            // Processar outros parâmetros conhecidos
            if ($key === 'conservacao') {
                $search_string .= substr($value, 0, -1) . 'a . ';
            } elseif ($key === 'ano') {
                $search_string .= $value . ' . ';
            } elseif ($key === 'ano_ate') {
                $search_string .= 'até ' . $value . ' . ';
            } elseif ($key === 'valor_de') {
                $search_string .= 'R$' . $value . ' . ';
            } elseif ($key === 'valor_ate') {
                $search_string .= 'até R$' . $value . ' . ';
            }
        }

        if ($search_string != '') {
            $this->title = substr($search_string, 0, -3);
        } else {
            // Fallback: título padrão se não houver outros parâmetros
            $this->title = self::DEFAULT_TITLE;
        }
        $this->key = $search_string;
    }

    /**
     * Obtém localidade (cidade/estado) do cache de localização
     * Usa BazarBikes_GeoAPI para obter localização do cache inteligente
     * @return string Localidade formatada (ex: "Belo Horizonte / MG") ou string vazia
     */
    private function get_location_from_filters()
    {
        $localidade = '';

        // Obter instância da API de localização
        global $geo_api;
        if (!$geo_api) {
            $geo_api = BazarBikes_GeoAPI::getInstance();
        }

        // Obter localização do cache inteligente
        $location_data = $geo_api->get_smart_location();

        // Verificar se tem dados válidos de localização
        if (
            $location_data
            && is_array($location_data)
            && isset($location_data['localizacao'])
            && (!empty($location_data['localizacao']['cidade']) || !empty($location_data['localizacao']['estado']))
        ) {
            $loc = $location_data['localizacao'];

            // Se tem cidade e estado, formatar usando format_city_state
            if (!empty($loc['cidade_term_id']) && !empty($loc['estado_term_id'])) {
                $cidade_term = get_term($loc['cidade_term_id'], 'cidade');
                if ($cidade_term && !is_wp_error($cidade_term)) {
                    $localidade = format_city_state(null, $cidade_term);
                }
            }
            // Se só tem estado
            elseif (!empty($loc['estado_term_id'])) {
                $estado_term = get_term($loc['estado_term_id'], 'cidade');
                if ($estado_term && !is_wp_error($estado_term)) {
                    $localidade = $estado_term->name;
                }
            }
            // Fallback: usar nomes diretos se não tiver term_id
            elseif (!empty($loc['cidade']) && !empty($loc['estado_sigla'])) {
                $localidade = $loc['cidade'] . ' / ' . $loc['estado_sigla'];
            } elseif (!empty($loc['estado'])) {
                $localidade = $loc['estado'];
            }
        }

        return $localidade;
    }

    /**
     * Obtém localidade APENAS de filtros GET explícitos (não do perfil do usuário)
     * Usado para títulos de taxonomia 'category' - só inclui localização se vier de filtro GET
     * Isso aumenta a autoridade das páginas de taxonomia cidade para SEO
     * 
     * @return string Localidade formatada (ex: "Belo Horizonte / MG") ou string vazia
     */
    private function get_location_from_get_filters_only()
    {
        $localidade = '';

        // Verificar se há filtro GET de cidade
        if (isset($_GET['cidade_filter']) && !empty($_GET['cidade_filter'])) {
            $cidade_slug = is_array($_GET['cidade_filter']) ? $_GET['cidade_filter'][0] : $_GET['cidade_filter'];
            $cidade_slug = sanitize_text_field($cidade_slug);

            // Buscar termo da cidade
            $cidade_term = get_term_by('slug', $cidade_slug, 'cidade');
            if ($cidade_term && !is_wp_error($cidade_term)) {
                $localidade = format_city_state(null, $cidade_term);
            }
        }
        // Verificar formato antigo (sem _filter)
        elseif (isset($_GET['cidade']) && !empty($_GET['cidade'])) {
            $cidade_slug = is_array($_GET['cidade']) ? $_GET['cidade'][0] : $_GET['cidade'];
            $cidade_slug = sanitize_text_field($cidade_slug);

            $cidade_term = get_term_by('slug', $cidade_slug, 'cidade');
            if ($cidade_term && !is_wp_error($cidade_term)) {
                $localidade = format_city_state(null, $cidade_term);
            }
        }

        return $localidade;
    }

    /**
     * Extrai filtros GET relevantes para construção de títulos
     * Suporta padrão: {taxonomy}_filter={slug1,slug2} e formatos especiais (category, componente)
     * @return array Array associativo com filtros formatados ['category' => 'Peças', 'componente' => '6v']
     */
    private function get_active_filters_for_title()
    {
        $filters = array();

        if (empty($_GET)) {
            return $filters;
        }

        // Taxonomias com formato especial (sem _filter)
        $special_taxonomies = array('category');

        foreach ($_GET as $key => $value) {
            if (empty($value) || in_array($key, $this->control_params_array)) {
                continue;
            }

            $taxonomy_name = null;
            $is_filter_format = false;

            // Verificar se é formato especial ou _filter
            if (in_array($key, $special_taxonomies)) {
                $taxonomy_name = $key;
            } elseif (strpos($key, '_filter') !== false) {
                $taxonomy_name = str_replace('_filter', '', $key);
                $is_filter_format = true;
            } else {
                continue;
            }

            // Validar se é uma taxonomia válida
            if (!taxonomy_exists($taxonomy_name)) {
                continue;
            }

            // Processar valores (podem ser separados por vírgula ou array)
            $values = is_array($value) ? $value : explode(',', $value);
            $values = array_map('trim', $values);
            $values = array_filter($values);

            if (empty($values)) {
                continue;
            }

            // Para componentes, armazenar todos os valores; para outros, apenas o primeiro
            if ($taxonomy_name === 'componente') {
                // Processar todos os componentes
                $component_names = array();
                foreach ($values as $slug) {
                    $slug_clean = sanitize_text_field($slug);
                    // Verificar cache primeiro
                    if (isset($this->cached_terms[$taxonomy_name][$slug_clean])) {
                        $component_term = $this->cached_terms[$taxonomy_name][$slug_clean];
                    } else {
                        $component_term = get_term_by('slug', $slug_clean, $taxonomy_name);
                        // Cachear termo
                        if ($component_term && !is_wp_error($component_term)) {
                            if (!isset($this->cached_terms[$taxonomy_name])) {
                                $this->cached_terms[$taxonomy_name] = array();
                            }
                            $this->cached_terms[$taxonomy_name][$slug_clean] = $component_term;
                        }
                    }
                    if ($component_term && !is_wp_error($component_term)) {
                        $component_names[] = $component_term->name;
                    }
                }
                if (!empty($component_names)) {
                    $filters[$taxonomy_name] = $component_names; // Array para múltiplos componentes
                }
            } else {
                // Para outros filtros, usar apenas o primeiro valor
                $first_slug = sanitize_text_field($values[0]);
                // Verificar cache primeiro
                if (isset($this->cached_terms[$taxonomy_name][$first_slug])) {
                    $term = $this->cached_terms[$taxonomy_name][$first_slug];
                } else {
                    $term = get_term_by('slug', $first_slug, $taxonomy_name);
                    // Cachear termo
                    if ($term && !is_wp_error($term)) {
                        if (!isset($this->cached_terms[$taxonomy_name])) {
                            $this->cached_terms[$taxonomy_name] = array();
                        }
                        $this->cached_terms[$taxonomy_name][$first_slug] = $term;
                    }
                }

                if ($term && !is_wp_error($term)) {
                    $filters[$taxonomy_name] = $term->name;
                }
            }
        }

        return $filters;
    }

    /**
     * Constrói título e descrição de taxonomia considerando filtros GET
     * @param WP_Term $term Termo da taxonomia atual
     * @param string $prefix Prefixo do termo (ex: "Mountain Bike", "Caloi", "Bicicleta")
     * @param array $active_filters Array com filtros ativos ['category' => 'Peças', 'componente' => '6v']
     */
    private function build_taxonomy_title_with_filters($term, $prefix, $active_filters)
    {
        $category_name = isset($active_filters['category']) ? $active_filters['category'] : '';
        $componente_data = isset($active_filters['componente']) ? $active_filters['componente'] : null;
        $taxonomy = $term->taxonomy;

        // Processar componentes (pode ser array ou string)
        $componente_names = array();
        if (!empty($componente_data)) {
            $componente_names = is_array($componente_data) ? $componente_data : array($componente_data);
        }

        // Outros filtros (marca-modelo, modalidade, etc)
        $other_filters = array();
        foreach ($active_filters as $key => $value) {
            if (!in_array($key, array('category', 'componente'))) {
                $other_filters[] = $value;
            }
        }

        // Obter descrição de SEO do term meta (prioridade) ou usar description padrão
        $seo_description = get_term_meta($term->term_id, 'descricao', true);
        if (empty($seo_description)) {
            $seo_description = $term->description;
        }

        // Caso especial: taxonomia 'cidade'
        if ($taxonomy === 'cidade') {
            $cidade = format_city_state(null, $term);
            if (!empty($category_name)) {
                $this->title = $category_name . ' em ' . $cidade . ' | Novas e Usadas';
                // Usar descrição de SEO se disponível, senão usar padrão
                $this->description = !empty($seo_description)
                    ? $seo_description
                    : $category_name . ' em ' . $cidade . ' você encontra no ' . self::SITE_NAME . '. Classificado de ' . strtolower($category_name) . ' novas e usadas. ' . self::DEFAULT_DESCRIPTION;
                $this->key = $category_name . ', em ' . $cidade;
            } else {
                $this->title = 'Bicicletas em ' . $cidade . ' | Bicicletas Usadas e Novas';
                $this->description = !empty($seo_description)
                    ? $seo_description
                    : 'Bikes usadas e novas em ' . $cidade . ' você encontra aqui. ' . self::DEFAULT_DESCRIPTION;
                $this->key = 'Bicicletas em ' . $cidade;
            }
            $this->hide_robots = false;
            return;
        }

        // Caso especial: taxonomia 'category' (página de categoria)
        if ($taxonomy === 'category') {
            // Se há componentes, usar formato: "Bicicleta com 6v | Novas e Usadas"
            if (!empty($componente_names)) {
                $componente_text = implode(', ', $componente_names);
                $this->title = $prefix . ' com ' . $componente_text . ' | Novas e Usadas';
                // Quando há filtros, não usar descrição de SEO (priorizar contexto dos filtros)
                $this->description = $prefix . ' com ' . $componente_text . ' você encontra no ' . self::SITE_NAME . '. ' . self::DEFAULT_DESCRIPTION;
                $this->key = $prefix . ', ' . implode(', ', $componente_names) . ', usadas, novas';
            } else {
                // Sem componente, usar formato padrão de category
                $localidade = $this->get_location_from_filters();
                if (!empty($localidade)) {
                    $this->title = $prefix . ' | Usadas e Novas em ' . $localidade . ' | ' . self::SITE_NAME;
                    // Quando há filtros de localização, não usar descrição de SEO (priorizar contexto)
                    $this->description = $prefix . ' usadas e novas em ' . $localidade . ' você encontra no ' . self::SITE_NAME . '. ' . self::DEFAULT_DESCRIPTION;
                    $this->key = $prefix . ', usadas, novas, em ' . $localidade;
                } else {
                    $this->title = $prefix . ' | Usadas e Novas | ' . self::SITE_NAME;
                    // Sem filtros, usar descrição de SEO se disponível
                    $this->description = !empty($seo_description)
                        ? $seo_description
                        : $prefix . ' usadas e novas você encontra no ' . self::SITE_NAME . '. ' . self::DEFAULT_DESCRIPTION;
                    $this->key = $prefix . ', usadas, novas';
                }
            }
            $this->hide_robots = false;
            return;
        }

        // Casos gerais: outras taxonomias com filtros
        // Título: {Category} {Taxonomy Term} | Novas e Usadas | Bazar Bikes
        $category_prefix = !empty($category_name) ? $category_name : 'Bicicletas';
        // Construir título completo (já inclui SITE_NAME, então não precisa adicionar sufixo depois)
        $this->title = $category_prefix . ' ' . $prefix . ' | Novas e Usadas | ' . self::SITE_NAME;

        // Descrição: {Category} {Taxonomy Term} { | Componente1 | Componente2 | } Novas e usadas você encontra no Bazar Bikes...
        $description_text = $category_prefix . ' ' . $prefix;

        // Adicionar componentes na descrição (se houver) no formato: { | Componente1 | Componente2 | }
        if (!empty($componente_names)) {
            $description_text .= ' { | ' . implode(' | ', $componente_names) . ' | }';
        }

        $description_text .= ' Novas e usadas você encontra no ' . self::SITE_NAME . '. Vejas os anúncios de bicicletas usadas e bicicletas novas e encontre a bike perfeita para seu pedal. Quer vender sua bicicleta? Anuncie grátis.';
        $this->description = $description_text;

        // Construir keywords
        $key_parts = array($category_prefix, $prefix);
        if (!empty($componente_names)) {
            $key_parts = array_merge($key_parts, $componente_names);
        }
        if (!empty($other_filters)) {
            $key_parts = array_merge($key_parts, $other_filters);
        }
        $key_parts[] = 'usadas';
        $key_parts[] = 'novas';
        $this->key = implode(', ', $key_parts);
    }

    /**
     * Constrói prefixo de taxonomia incluindo o nome da taxonomia
     * @param WP_Term|null $term Termo da taxonomia
     * @return string Prefixo formatado com nome da taxonomia
     */
    private function build_taxonomy_prefix($term = null)
    {

        if ($term === null || !isset($term) || empty($term)) {
            $term = $this->term;
        }

        if (!$term || !isset($term->taxonomy)) {
            return '';
        }

        // Construir resultado com nome do termo
        $result = $term->name;

        // Se tiver parent, adicionar antes do nome do termo
        if ($term->parent !== 0) {
            $parent_term = get_term($term->parent);
            if ($parent_term && !is_wp_error($parent_term)) {
                $result = $parent_term->name . ' ' . $term->name;
            }
        }

        return $result;
    }

    // === GETTERS/SETTERS ===

    /**
     * Obtém a URL correta baseada no contexto da página
     * @return string URL da página atual
     */
    private function get_current_url()
    {

        if (is_front_page()) {
            return home_url('/');
        }

        if (is_home()) {
            $posts_page_id = get_option('page_for_posts');
            return $posts_page_id ? get_permalink($posts_page_id) : home_url('/');
        }

        if (is_archive()) {
            if (is_category() || is_tag() || is_tax()) {
                $term = get_queried_object();
                $term_link = get_term_link($term);
                return ($term_link && !is_wp_error($term_link)) ? $term_link : home_url('/');
            }
            if (is_post_type_archive()) {
                return get_post_type_archive_link(get_post_type());
            }
            if (is_author()) {
                $author_id = get_queried_object_id();
                return $author_id ? get_author_posts_url($author_id) : home_url('/');
            }
            return home_url('/');
        }

        if (is_singular()) {
            $permalink = get_permalink();
            // Se get_permalink() retornar false, usar fallback
            if ($permalink === false) {
                $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                return home_url($request_uri);
            }
            return $permalink;
        }

        if (is_404()) {
            return home_url('/404');
        }

        // Fallback: URL atual da requisição
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        return home_url($request_uri);
    }

    /**
     * Obtém o ID correto baseado no contexto da página
     * @return int|null ID da página/post atual ou null para páginas não-singulares
     */
    private function get_current_id()
    {
        if (is_singular()) {
            return get_the_ID();
        }

        if (is_home()) {
            $posts_page_id = get_option('page_for_posts');
            return $posts_page_id ? $posts_page_id : null;
        }

        return null;
    }

    /**
     * Define a cidade baseada no contexto da página
     * - Se for produto (singular post): usa cidade do anunciante
     * - Se for página institucional: usa 'Brasil'
     * @return string Cidade formatada
     */
    private function get_author_city()
    {
        // Se for produto (singular post), buscar cidade do anunciante
        if (is_singular('post')) {
            $product_data = $this->get_product_data();
            if ($product_data && isset($product_data['formatted']['location'])) {
                return $product_data['formatted']['location'];
            }
        }
        if (is_tax('cidade')) {
            return format_city_state(null, $this->term);
        }
        return '';
    }

    /**
     * Define valores padrão para propriedades não definidas
     */
    public function set_defaults()
    {

        $this->title = (is_null($this->title) || $this->title == '')
            ? get_the_title()
            : $this->title;

        // Adicionar sufixo da marca se título for muito curto
        // Mas verificar se já não termina com o sufixo para evitar duplicação
        if (strlen($this->title) < self::MIN_TITLE_LENGTH) {
            // Verificar se o título já termina com o sufixo da marca
            $suffix = self::BRAND_SUFFIX;
            $title_ends_with_suffix = substr($this->title, -strlen($suffix)) === $suffix;

            // Só adicionar se não terminar com o sufixo
            if (!$title_ends_with_suffix) {
                $this->title = $this->title . $suffix;
            }
        }

        $this->description = (is_null($this->description) || $this->description == '')
            ? get_the_title() . ' | ' . $this->site_description
            : $this->description;

        $this->url = (is_null($this->url) || $this->url == '')
            ? $this->get_current_url()
            : $this->url;

        $this->img = (is_null($this->img) || $this->img == '')
            ? self::DEFAULT_IMAGE
            : $this->img;

        if (is_author()) {
            $qo = get_queried_object();
            if ($qo instanceof WP_User) {
                $dn = get_the_author_meta('display_name', $qo->ID);
                $this->author_name = ($dn !== '' && $dn !== false) ? $dn : $qo->display_name;
                $fone = get_the_author_meta('fone', $qo->ID);
                $this->author_fone = ($fone !== '' && $fone !== false) ? $fone : 'XXXXXX';
            } else {
                $this->author_name = self::SITE_NAME;
                $this->author_fone = 'XXXXXX';
            }
        } else {
            $this->author_name = (get_the_author_meta('display_name') != '')
                ? get_the_author_meta('display_name')
                : self::SITE_NAME;

            $this->author_fone = (get_the_author_meta('fone') != '')
                ? get_the_author_meta('fone')
                : 'XXXXXX';
        }

        // Definir cidade baseada no contexto (produto = cidade do anunciante, institucional = Brasil)
        $this->author_city = $this->get_author_city();

        $this->meta_type = (is_null($this->meta_type) || $this->meta_type == '')
            ? 'website'
            : $this->meta_type;

        $this->key = (is_null($this->key) || $this->key == '')
            ? ''
            : $this->key;
    }

    // === MÉTODOS PÚBLICOS ===

    /**
     * Retorna meta tag robots baseado em condições
     * @return string Meta tag robots
     */
    private function get_robots_meta()
    {
        $should_noindex_orphan_taxonomy_child = $this->has_orphan_child_taxonomy_url();
        $should_noindex_follow = (
            $should_noindex_orphan_taxonomy_child
            || $this->has_non_tracking_query_params()
            || is_paged()
        );

        $should_noindex_nofollow = (
            is_page(array('editar-anuncio', 'minha-conta', 'meus-anuncios', 'confirmar-email', 'social-feed'))
            || is_admin()
            || is_singular('app')
            || is_singular('teste')
            || is_tax('post_tag')
            || is_tax('negociacao')
            || is_tax('material')
            || is_tax('cor')
            || is_tax('alfabeto')
            || is_404()
            || is_author()
            || $this->hide_robots
        );

        if ($should_noindex_nofollow) {
            return '<meta name="robots" content="noindex, nofollow">';
        }

        if ($should_noindex_follow) {
            return '<meta name="robots" content="noindex, follow">';
        }

        return '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />';
    }

    /**
     * Detecta URLs órfãs de taxonomias hierárquicas críticas de SEO.
     * Órfã = termo filho com pai definido, mas URL sem o slug do pai na primeira posição.
     *
     * @return bool
     */
    private function has_orphan_child_taxonomy_url()
    {
        return $this->is_orphan_child_taxonomy_url('marca-modelo', '/bicicleta-marca-modelo/')
            || $this->is_orphan_child_taxonomy_url('cidade', '/bicicleta-cidade/')
            || $this->is_orphan_child_taxonomy_url('componente', '/bicicleta-pecas/');
    }

    /**
     * Verifica se a taxonomia atual está em URL órfã (filho sem pai no path).
     *
     * @param string $taxonomy Taxonomia alvo.
     * @param string $base_prefix Prefixo de URL da taxonomia.
     * @return bool
     */
    private function is_orphan_child_taxonomy_url($taxonomy, $base_prefix)
    {
        if (!is_tax($taxonomy)) {
            return false;
        }

        $term = get_queried_object();
        if (!$term || is_wp_error($term) || !isset($term->parent) || (int) $term->parent < 1) {
            return false;
        }

        $parent_term = get_term((int) $term->parent, $taxonomy);
        if (!$parent_term || is_wp_error($parent_term) || empty($parent_term->slug)) {
            return false;
        }

        if (empty($_SERVER['REQUEST_URI'])) {
            return false;
        }

        $request_raw = (string) wp_unslash($_SERVER['REQUEST_URI']);
        $path = (string) wp_parse_url($request_raw, PHP_URL_PATH);
        $path = strtolower(rawurldecode($path));

        $base = strtolower($base_prefix);
        $pos = strpos($path, $base);
        if ($pos === false) {
            return false;
        }

        $tail = substr($path, $pos + strlen($base));
        $tail = trim((string) $tail);
        $tail = trim($tail, '/');
        if ($tail === '') {
            return false;
        }

        $segments = array_values(array_filter(explode('/', $tail), 'strlen'));
        if (empty($segments)) {
            return false;
        }

        $expected_parent_slug = strtolower((string) $parent_term->slug);
        return $segments[0] !== $expected_parent_slug;
    }

    /**
     * Constrói todas as meta tags HTML
     * @return string HTML com todas as meta tags
     */
    public function META_fields()
    {
        $head_meta_tags = '<title>' . esc_html($this->title) . '</title>
        ' . $this->get_robots_meta() . '
        <meta name="description" content="' . esc_attr($this->description) . '">
        <meta name="url" content="' . esc_url($this->url) . '">
        <meta property="og:locale" content="pt_BR" />
        <meta property="og:type" content="' . esc_attr($this->meta_type) . '" />
        <meta property="og:title" content="' . esc_attr($this->title) . '" />
        <meta property="og:description" content="' . esc_attr($this->description) . '" />
        <meta property="og:url" content="' . esc_url($this->url) . '" />
        <meta property="og:site_name" content="' . esc_attr(self::SITE_NAME) . '" />
        <meta property="article:publisher" content="' . esc_url($this->url) . '" />
        <meta property="og:image" content="' . esc_url($this->img) . '" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:site" content="@XXXXXX" />
        <meta name="twitter:title" content="' . esc_attr($this->title) . '" />
        <meta name="twitter:image" content="' . esc_url($this->img) . '" />
        <meta name="twitter:description" content="' . esc_attr($this->description) . '">
        <meta name="theme-color" content="#FFF"/>
        <meta name="country" content="Brasil">
        <meta name="language" content="pt-br">
        <meta name="city" content="' . esc_attr($this->author_city) . '">
        <meta name="audience" content="all">
        <meta name="author" content="' . esc_attr($this->author_name) . '" />
        <meta name="publisher" content="' . esc_attr(self::SITE_NAME) . '" />';

        return $head_meta_tags;
    }

    /**
     * Retorna parâmetros SEO em formato array
     * @return array Parâmetros SEO
     */
    public function SEO_params()
    {
        return array(
            'id' => $this->get_current_id(),
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'img' => $this->img,
            'author_name' => $this->author_name,
            'key' => $this->key,
            'tags' => $this->tags
        );
    }

    /**
     * Retorna termos já cacheados durante processamento SEO
     * Útil para reutilizar em bazar_build_archive_queries() e evitar queries duplicadas
     * 
     * @return array Estrutura: ['taxonomy_name' => ['slug' => WP_Term, ...]]
     */
    public function get_cached_terms()
    {
        return $this->cached_terms;
    }

    /**
     * Retorna termos cacheados formatados para uso em tax_query
     * Converte estrutura de cache para formato de slugs por taxonomia
     * 
     * @return array Estrutura: ['taxonomy_name' => ['slug1', 'slug2', ...]]
     */
    public function get_cached_terms_slugs()
    {
        $result = array();
        foreach ($this->cached_terms as $taxonomy => $terms) {
            $result[$taxonomy] = array_keys($terms);
        }
        return $result;
    }

    /**
     * Exibe meta fields (chamado quando necessário)
     */
    public function display_meta_fields()
    {
        echo $this->META_fields();
    }
}
?>