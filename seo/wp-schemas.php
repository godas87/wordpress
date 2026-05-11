<?php
class __Bazar_Schema
{

  public $post_id;
  public $schema;
  public $schemaCustom;
  public $title;
  public $description;
  public $url;
  public $img;
  public $author_name;
  public $key;
  public $product;
  public $breadcrumb;
  private $logo_url = 'https://XXXXXX/src/imgs/logo-bazar-bikes.png';
  // Array to track unique products on a single page render to avoid duplicate schemas
  private $unique_products = [];


  /*
   * Construtor
   * @param array $params
   * @example $params = array(
   *     'id' => get_the_ID(),
   *     'title' => get_the_title(),
   *     'description' => get_the_excerpt(),
   *     'url' => get_the_permalink(),
   *     'img' => get_the_post_thumbnail_url(),
   *     'author_name' => get_the_author_meta('display_name'),
   *     'key' => get_the_terms(get_the_ID(), 'post_tag'),
   *     'tags' => get_the_terms(get_the_ID(), 'tags'),    
   * );
   * @return json
   * @example <script type="application/ld+json">' . $this->schema_init() . '</script>';
   */
  public function __construct($params)
  {

    $this->post_id = isset($params['id']) && !empty($params['id'])
      ? $params['id']
      : null;

    $this->title = isset($params['title']) && !empty($params['title'])
      ? $params['title']
      : null;

    $this->description = isset($params['description']) && !empty($params['description'])
      ? $params['description']
      : null;

    $this->url = isset($params['url']) && !empty($params['url'])
      ? $params['url']
      : null;

    $this->img = isset($params['img']) && !empty($params['img'])
      ? $params['img']
      : null;

    $this->author_name = isset($params['author_name']) && !empty($params['author_name'])
      ? $params['author_name']
      : null;

    $this->key = isset($params['key']) && !empty($params['key'])
      ? $params['key']
      : null;


    // Validando os parâmetros
    if (is_null($this->title) || $this->title === '')
      return;
    if (is_null($this->description) || $this->description === '')
      return;
    if (is_null($this->url) || $this->url === '')
      return;
    if (is_null($this->author_name) || $this->author_name === '')
      return;

    echo '<script type="application/ld+json">' . $this->schema_init() . '</script>';
  }

  public function schema_init()
  {
    // Reset unique products registry at start of page-level schema generation
    $this->unique_products = [];


    if (is_front_page()):
      $this->schemaCustom[] = $this->schema_SearchResultsMain();
      $this->schemaCustom[] = $this->schema_WebPage();

    elseif (is_page()):
      $lista_breadcrumb = [
        [
          "name" => "Home",
          "url" => get_bloginfo('url')
        ],
        [
          "name" => $this->title,
          "url" => $this->url
        ]
      ];
      $this->breadcrumb = $lista_breadcrumb;
      $this->schemaCustom[] = $this->schema_WebPage();
      $this->schemaCustom[] = $this->schema_Breadcrumb($lista_breadcrumb);


    elseif (is_singular('post')):
      // Usar get_queried_object() para evitar query desnecessária (usa cache do WordPress)
      $post = get_queried_object();
      $this->schemaCustom = $this->schema_ProductIndividual($post);

    elseif (is_singular('web-stories')):

      $this->schemaCustom[] = $this->schema_NewsArticleItem();

      $lista_breadcrumb = [
        [
          "name" => "Home",
          "url" => get_bloginfo('url')
        ],
        [
          "name" => "Web Stories",
          "url" => get_bloginfo('url') . '\/web-stories\/'
        ],
        [
          "name" => $this->title,
          "url" => $this->url
        ]
      ];
      $this->breadcrumb = $lista_breadcrumb;
      $this->schemaCustom[] = $this->schema_Breadcrumb($lista_breadcrumb);


    elseif (is_singular('glossario-ciclismo')):

      $this->schemaCustom[] = $this->schema_DefinedTerm();

      $lista_breadcrumb = [
        [
          "name" => "Home",
          "url" => get_bloginfo('url')
        ],
        [
          "name" => "Glossário Ciclismo",
          "url" => trailingslashit(home_url('glossario-ciclismo'))
        ],
        [
          "name" => $this->title,
          "url" => $this->url
        ]
      ];
      $this->breadcrumb = $lista_breadcrumb;
      $this->schemaCustom[] = $this->schema_Breadcrumb($lista_breadcrumb);


    elseif (is_singular('blog')):

      $this->schemaCustom[] = $this->schema_NewsArticleItem();

      $lista_breadcrumb = [
        [
          "name" => "Home",
          "url" => get_bloginfo('url')
        ],
        [
          "name" => "Blog",
          "url" => get_bloginfo('url') . '\/blog\/'
        ],
        [
          "name" => $this->title,
          "url" => $this->url
        ]
      ];
      $this->breadcrumb = $lista_breadcrumb;
      $this->schemaCustom[] = $this->schema_Breadcrumb($lista_breadcrumb);


    elseif (is_category() || is_tax()):

      $this->schemaCustom[] = $this->schema_SearchResults();

      $term = get_queried_object();
      $parent_term = ($term->parent) ? get_term($term->parent) : null;
      $parent_term_link = ($parent_term && !is_wp_error($parent_term)) ? get_term_link($parent_term) : '';
      $parent_term_link = ($parent_term_link && !is_wp_error($parent_term_link)) ? $parent_term_link : '';

      $term_link = get_term_link($term);
      $term_link = ($term_link && !is_wp_error($term_link)) ? $term_link : '';

      $lista_breadcrumb = ($term->parent && $parent_term && !is_wp_error($parent_term) && isset($parent_term->name))
        ?
        [
          [
            "name" => "Home",
            "url" => get_bloginfo('url')
          ],
          [
            "name" => $parent_term->name,
            "url" => $parent_term_link
          ],
          [
            "name" => (isset($term->name)) ? $term->name : '',
            "url" => $term_link
          ]
        ]
        :
        [
          [
            "name" => "Home",
            "url" => get_bloginfo('url')
          ],
          [
            "name" => (isset($term->name)) ? $term->name : '',
            "url" => $term_link
          ]
        ];
      $this->breadcrumb = $lista_breadcrumb;
      $this->schemaCustom[] = $this->schema_Breadcrumb($lista_breadcrumb);


    elseif (is_post_type_archive('blog')):

      $this->schemaCustom[] = $this->schema_SearchResultsMain();

      $lista_breadcrumb = [
        [
          "name" => "Home",
          "url" => get_bloginfo('url')
        ],
        [
          "name" => "Blog",
          "url" => get_bloginfo('url') . '/blog'
        ]
      ];
      $this->breadcrumb = $lista_breadcrumb;
      $this->schemaCustom[] = $this->schema_Breadcrumb($lista_breadcrumb);

    elseif (is_post_type_archive('web-stories')):

      $this->schemaCustom[] = $this->schema_SearchResultsMain();

      $lista_breadcrumb = [
        [
          "name" => "Home",
          "url" => get_bloginfo('url')
        ],
        [
          "name" => 'Web Stories',
          "url" => get_bloginfo('url') . '/web-stories'
        ]
      ];
      $this->breadcrumb = $lista_breadcrumb;
      $this->schemaCustom[] = $this->schema_Breadcrumb($lista_breadcrumb);

    elseif (is_post_type_archive('glossario-ciclismo')):

      $this->schemaCustom[] = $this->schema_DefinedTermSet();
      $this->schemaCustom[] = $this->schema_SearchResultsMain();

      $lista_breadcrumb = [
        [
          "name" => "Home",
          "url" => get_bloginfo('url')
        ],
        [
          "name" => "Glossário Ciclismo",
          "url" => get_bloginfo('url') . '\/glossario-ciclismo\/'
        ]
      ];
      $this->breadcrumb = $lista_breadcrumb;
      $this->schemaCustom[] = $this->schema_Breadcrumb($lista_breadcrumb);
    endif;

    return json_encode($this->schema_Main(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }


  /* 
   * POSTS / PRODUCTS
   */
  private function get_product($product = null, $collection_context = false)
  {

    if ($product === null)
      return;

    // Lidar com objeto WP_Post ou ID (string/inteiro)
    if (is_object($product) && isset($product->ID)) {
      $product_id = $product->ID;
    } elseif (is_numeric($product)) {
      $product_id = intval($product);
    } else {
      error_log("[__Bazar_Schemas] get_product() recebeu tipo inválido: " . gettype($product));
      return null;
    }

    // Singular: bazar_get_product_data() = FULL — cache já aquecido por __Bazar_SEO_Meta no header.
    // Coleção: CONTEXT_SCHEMA_COLLECTION (galeria leve, sem varrer anexos por item).
    if ($collection_context) {
      $product_data = bazar_get_product_data_for_context(
        $product_id,
        __Bazar_Product_Data_Repository::CONTEXT_SCHEMA_COLLECTION
      );
    } else {
      $product_data = bazar_get_product_data($product_id);
    }

    // Validar se product_data foi retornado corretamente
    if (!$product_data || !is_array($product_data)) {
      error_log("[__Bazar_Schemas] get_product() falhou ao obter dados para product_id: {$product_id}");
      return null;
    }

    $title = $product_data['title'];
    $url = $product_data['permalink'];
    $img = $product_data['images']['featured'];
    $img_urls = $product_data['images']['gallery'];

    // Verificar se a postagem está desativada (vendida) usando taxonomia.
    // Regra robusta: prioriza slug "vendido" e usa fallback por name (case-insensitive).
    $is_vendido = false;
    if (isset($product_data['taxonomies']['status']) && !empty($product_data['taxonomies']['status'])) {
      foreach ($product_data['taxonomies']['status'] as $status) {
        $status_slug = isset($status->slug) ? strtolower(trim((string) $status->slug)) : '';
        $status_name = isset($status->name) ? strtolower(trim((string) $status->name)) : '';
        if ($status_slug === 'vendido' || $status_name === 'vendido') {
          $is_vendido = true;
          break;
        }
      }
    }

    $condition = ($product_data['taxonomies']['conservacao'][0]->name != 'Novo(a)' || $product_data['taxonomies']['conservacao'][0]->name != 'Novo')
      ? 'UsedCondition'
      : 'NewCondition';

    $availability = ($is_vendido)
      ? "OutOfStock"
      : "InStock";

    // Rating (usando dados do helper)
    $rating = $product_data['meta']['rating'];
    $average_count = 1;
    $average_rating = $rating;


    // Taxonomias (usando dados do helper)
    $brand = $product_data['taxonomies']['marca-modelo'];
    $marca = ($brand && isset($brand[0]) && isset($brand[0]->name)) ? $brand[0]->name : '';

    $modelo = ($brand && isset($brand[1]) && isset($brand[1]->name)) ? $brand[0]->name . ' ' . $brand[1]->name : '';

    $cat = $product_data['taxonomies']['category'];
    $category = ($cat && isset($cat[0], $cat[1]) && isset($cat[0]->name, $cat[1]->name)) ? $cat[0]->name . ' ' . $cat[1]->name : '';

    // Taxonomia 'especificacoes' está sendo inutilizada - mantido apenas para compatibilidade
    $modalidade_terms = $product_data['taxonomies']['modalidade'];
    $modalidade = ($modalidade_terms && isset($modalidade_terms[0]) && isset($modalidade_terms[0]->name)) ? $modalidade_terms[0]->name : '';

    // Taxonomias adicionais para breadcrumbs (usando dados do helper)
    $componentes_terms = $product_data['taxonomies']['componente'];
    $medidas_terms = $product_data['taxonomies']['medidas'];
    $acessorios_terms = $product_data['taxonomies']['acessorio'];
    $especificacoes_terms = $product_data['taxonomies']['especificacoes']; // Para posts antigos

    // Campos ACF (usando dados do helper)
    $valor = $product_data['formatted']['valor'];
    $valor_schema = $product_data['formatted']['valor_schema'];
    $color_terms = $product_data['taxonomies']['cor'];
    $color = ($color_terms && isset($color_terms[0]) && isset($color_terms[0]->name)) ? $color_terms[0]->name : '';
    $peso = $product_data['fields']['peso'];
    $material_terms = $product_data['taxonomies']['material'];
    $material = ($material_terms && isset($material_terms[0]) && isset($material_terms[0]->name)) ? $material_terms[0]->name : '';

    $desc = "Compre $title | Valor R$$valor | Cor: $color | Peso: {$peso}g | XXXXXX | {$product->post_content}";

    // Preparar URLs das taxonomias (evitar chamadas duplicadas de get_term_link)
    $category_url = '';
    if ($cat && isset($cat[1])) {
      $term_link = get_term_link($cat[1], 'category');
      $category_url = ($term_link && !is_wp_error($term_link)) ? $term_link : '';
    }

    $marca_url = '';
    if ($brand && isset($brand[1])) {
      $term_link = get_term_link($brand[1], 'marca-modelo');
      $marca_url = ($term_link && !is_wp_error($term_link)) ? $term_link : '';
    }

    $modelo_url = '';
    if ($brand && isset($brand[1])) {
      $term_link = get_term_link($brand[1], 'marca-modelo');
      $modelo_url = ($term_link && !is_wp_error($term_link)) ? $term_link : '';
    }

    $modalidade_url = '';
    if ($modalidade_terms && isset($modalidade_terms[0])) {
      $term_link = get_term_link($modalidade_terms[0], 'modalidade');
      $modalidade_url = ($term_link && !is_wp_error($term_link)) ? $term_link : '';
    }

    $product = array(
      'id' => $product_id,
      'title' => $title,
      'description' => $desc,
      'url' => $url,
      'img' => $img,
      'ano' => $product_data['fields']['ano'],
      'seller' => $product_data['author']['name'],
      'galery' => $img_urls,
      'peso' => $peso,
      'color' => $color,
      'material' => $material,
      'category' => $category,
      'category_url' => $category_url,
      'category_terms' => $cat ? $cat : array(), // Termos completos para breadcrumb
      'organization' => ($brand && isset($brand[0]) && isset($brand[0]->name)) ? $brand[0]->name : '',
      'marca' => $marca,
      'modelo' => $modelo,
      'modelo_url' => $modelo_url,
      'marca_url' => $marca_url,
      'brand_terms' => $brand ? $brand : array(), // Termos completos para breadcrumb
      // Taxonomia 'especificacoes' está sendo inutilizada - mantido apenas para compatibilidade
      'modalidade' => $modalidade,
      'modalidade_url' => $modalidade_url,
      'modalidade_terms' => $modalidade_terms ? $modalidade_terms : array(), // Termos completos para breadcrumb
      'componentes_terms' => $componentes_terms ? $componentes_terms : array(), // Termos completos para breadcrumb
      'medidas_terms' => $medidas_terms ? $medidas_terms : array(), // Termos completos para breadcrumb
      'acessorios_terms' => $acessorios_terms ? $acessorios_terms : array(), // Termos completos para breadcrumb
      'especificacoes_terms' => $especificacoes_terms ? $especificacoes_terms : array(), // Para posts antigos
      'location' => $product_data['formatted']['location'],
      'average_count' => intval($average_count),
      'average_rating' => floatval($average_rating),
      'valor' => $valor,
      'valor_schema' => $valor_schema,
      'condition' => $condition,
      'availability' => $availability,
    );
    return $product;
  }

  /**
   * Constrói breadcrumb dinamicamente baseado na lógica do CRUD
   * Replica a mesma ordem e estrutura usada na geração de títulos
   * Usa dados já obtidos em get_product() para evitar queries duplicadas
   * 
   * @param array $product Array de dados do produto retornado por get_product()
   * @return array Array de breadcrumbs formatado
   */
  private function build_breadcrumb_for_post($product)
  {
    $breadcrumb = array();

    // IDs dos componentes relevantes (centralizados em bazar_get_component_title_ids)
    $component_title_ids = bazar_get_component_title_ids();

    // 1. Identificar categoria (usa dados já obtidos)
    $categories = isset($product['category_terms']) ? $product['category_terms'] : array();
    if (empty($categories)) {
      return $breadcrumb;
    }

    // Pega a categoria filha (índice 1) se existir, senão a primeira
    $category_term = isset($categories[1]) ? $categories[1] : $categories[0];
    $category_slug = $category_term->slug;

    // Adiciona categoria ao breadcrumb (sempre adiciona, mesmo se URL não estiver disponível)
    $category_link = !empty($product['category_url'])
      ? $product['category_url']
      : (($term_link = get_term_link($category_term, 'category')) && !is_wp_error($term_link) ? $term_link : '');

    $breadcrumb[] = array(
      'name' => $category_term->name,
      'url' => $category_link
    );

    // 2. Montar breadcrumb baseado na categoria (usa dados já obtidos)
    switch ($category_slug) {
      case 'bicicleta':
        // Estrutura: Category → Modalidade → Marca-Modelo → [Componentes: Aro, Quadro, Velocidades] OU [Especificações para posts antigos]

        // Modalidade (usa dados já obtidos)
        $modalidade_terms = isset($product['modalidade_terms']) ? $product['modalidade_terms'] : array();
        if (!empty($modalidade_terms) && isset($modalidade_terms[0])) {
          $modalidade_link = !empty($product['modalidade_url'])
            ? $product['modalidade_url']
            : (($term_link = get_term_link($modalidade_terms[0], 'modalidade')) && !is_wp_error($term_link) ? $term_link : '');

          $breadcrumb[] = array(
            'name' => $modalidade_terms[0]->name,
            'url' => $modalidade_link
          );
        }

        // Marca-Modelo (usa dados já obtidos)
        $brand_terms = isset($product['brand_terms']) ? $product['brand_terms'] : array();
        if (!empty($brand_terms) && isset($brand_terms[1])) {
          $marca_link = !empty($product['marca_url'])
            ? $product['marca_url']
            : (($term_link = get_term_link($brand_terms[1], 'marca-modelo')) && !is_wp_error($term_link) ? $term_link : '');

          $breadcrumb[] = array(
            'name' => $brand_terms[1]->name,
            'url' => $marca_link
          );
        }

        // Componentes relevantes: Aro, Quadro (usa dados já obtidos)
        $componentes = isset($product['componentes_terms']) ? $product['componentes_terms'] : array();
        if (!empty($componentes)) {
          // Busca Aro
          foreach ($componentes as $comp) {
            if ($comp->parent == $component_title_ids['aro']) {
              $aro_link = get_term_link($comp, 'componente');
              if ($aro_link && !is_wp_error($aro_link)) {
                $breadcrumb[] = array(
                  'name' => 'Aro ' . $comp->name,
                  'url' => $aro_link
                );
              }
              break;
            }
          }

          // Busca Quadro
          foreach ($componentes as $comp) {
            if ($comp->parent == $component_title_ids['quadro']) {
              $quadro_link = get_term_link($comp, 'componente');
              if ($quadro_link && !is_wp_error($quadro_link)) {
                $breadcrumb[] = array(
                  'name' => 'Quadro ' . $comp->name,
                  'url' => $quadro_link
                );
              }
              break;
            }
          }

          // Velocidades (se ambos os câmbios existirem)
          $cambio_dianteiro_term = null;
          $cambio_traseiro_term = null;
          foreach ($componentes as $comp) {
            if ($comp->parent == $component_title_ids['cambio_dianteiro']) {
              $cambio_dianteiro_term = $comp;
            }
            if ($comp->parent == $component_title_ids['cambio_traseiro']) {
              $cambio_traseiro_term = $comp;
            }
          }
          if ($cambio_dianteiro_term && $cambio_traseiro_term) {
            $v1 = intval(str_replace('v', '', $cambio_dianteiro_term->name));
            $v2 = intval(str_replace('v', '', $cambio_traseiro_term->name));
            if ($v1 > 0 && $v2 > 0) {
              $velocidades_name = ($v1 * $v2) . ' V';
              // Link para a taxonomia de velocidades, se existir
              $velocidades_terms = get_the_terms($product['id'], 'velocidades');
              $velocidades_link = ($velocidades_terms && !is_wp_error($velocidades_terms) && isset($velocidades_terms[0]))
                ? get_term_link($velocidades_terms[0], 'velocidades')
                : '';
              if ($velocidades_link && !is_wp_error($velocidades_link)) {
                $breadcrumb[] = array(
                  'name' => $velocidades_name,
                  'url' => $velocidades_link
                );
              } else {
                $breadcrumb[] = array(
                  'name' => $velocidades_name,
                  'url' => ''
                );
              }
            }
          }
        } else {
          // Posts antigos: se não tem componentes, verifica se tem especificações
          $especificacoes = isset($product['especificacoes_terms']) ? $product['especificacoes_terms'] : array();
          if (!empty($especificacoes)) {
            // Ordena: primeiro pais (parent = 0), depois filhos
            $especificacoes_pais = array();
            $especificacoes_filhos = array();

            foreach ($especificacoes as $espec) {
              if ($espec->parent == 0) {
                $especificacoes_pais[] = $espec;
              } else {
                $especificacoes_filhos[] = $espec;
              }
            }

            // Adiciona especificações pais primeiro
            foreach ($especificacoes_pais as $espec_pai) {
              $espec_link = get_term_link($espec_pai, 'especificacoes');
              if ($espec_link && !is_wp_error($espec_link)) {
                $breadcrumb[] = array(
                  'name' => $espec_pai->name,
                  'url' => $espec_link
                );
              }
            }

            // Adiciona especificações filhos depois
            foreach ($especificacoes_filhos as $espec_filho) {
              $espec_link = get_term_link($espec_filho, 'especificacoes');
              if ($espec_link && !is_wp_error($espec_link)) {
                $breadcrumb[] = array(
                  'name' => $espec_filho->name,
                  'url' => $espec_link
                );
              }
            }
          }
        }
        break;

      case 'peca':
        // Estrutura: Category → Componente (parent) → Componente (child) → Marca-Modelo → Medidas

        // Componentes (usa dados já obtidos)
        $componentes = isset($product['componentes_terms']) ? $product['componentes_terms'] : array();
        if (!empty($componentes)) {
          // Encontra o componente pai (parent = 0)
          $componente_pai = null;
          $componente_filho = null;

          foreach ($componentes as $comp) {
            if ($comp->parent == 0) {
              $componente_pai = $comp;
            } else {
              // Pega o primeiro filho encontrado
              if (!$componente_filho) {
                $componente_filho = $comp;
              }
            }
          }

          // Adiciona componente pai
          if ($componente_pai) {
            $pai_link = get_term_link($componente_pai, 'componente');
            if ($pai_link && !is_wp_error($pai_link)) {
              $breadcrumb[] = array(
                'name' => $componente_pai->name,
                'url' => $pai_link
              );
            }
          }

          // Adiciona componente filho (especificação)
          if ($componente_filho) {
            $filho_link = get_term_link($componente_filho, 'componente');
            if ($filho_link && !is_wp_error($filho_link)) {
              $breadcrumb[] = array(
                'name' => $componente_filho->name,
                'url' => $filho_link
              );
            }
          }
        }

        // Marca-Modelo (usa dados já obtidos)
        $brand_terms = isset($product['brand_terms']) ? $product['brand_terms'] : array();
        if (!empty($brand_terms) && isset($brand_terms[1]) && !empty($product['marca_url'])) {
          $breadcrumb[] = array(
            'name' => $brand_terms[1]->name,
            'url' => $product['marca_url']
          );
        }

        // Medidas (usa dados já obtidos)
        $medidas = isset($product['medidas_terms']) ? $product['medidas_terms'] : array();
        if (!empty($medidas)) {
          // Ordena: primeiro pais, depois filhos
          $medidas_pais = array();
          $medidas_filhos = array();

          foreach ($medidas as $medida) {
            if ($medida->parent == 0) {
              $medidas_pais[] = $medida;
            } else {
              $medidas_filhos[] = $medida;
            }
          }

          // Adiciona medidas pais primeiro
          foreach ($medidas_pais as $medida_pai) {
            $medida_link = get_term_link($medida_pai, 'medidas');
            if ($medida_link && !is_wp_error($medida_link)) {
              $breadcrumb[] = array(
                'name' => $medida_pai->name,
                'url' => $medida_link
              );
            }
          }

          // Adiciona medidas filhos (especificações)
          foreach ($medidas_filhos as $medida_filho) {
            $medida_link = get_term_link($medida_filho, 'medidas');
            if ($medida_link && !is_wp_error($medida_link)) {
              $breadcrumb[] = array(
                'name' => $medida_filho->name,
                'url' => $medida_link
              );
            }
          }
        }
        break;

      case 'acessorio':
        // Estrutura: Category → Acessório (parent) → Acessório (child) → Marca-Modelo → Medidas

        // Acessórios (usa dados já obtidos)
        $acessorios = isset($product['acessorios_terms']) ? $product['acessorios_terms'] : array();
        if (!empty($acessorios)) {
          // Encontra o acessório pai e filho
          $acessorio_pai = null;
          $acessorio_filho = null;

          foreach ($acessorios as $acess) {
            if ($acess->parent == 0) {
              $acessorio_pai = $acess;
            } else {
              if (!$acessorio_filho) {
                $acessorio_filho = $acess;
              }
            }
          }

          // Adiciona acessório pai
          if ($acessorio_pai) {
            $pai_link = get_term_link($acessorio_pai, 'acessorio');
            if ($pai_link && !is_wp_error($pai_link)) {
              $breadcrumb[] = array(
                'name' => $acessorio_pai->name,
                'url' => $pai_link
              );
            }
          }

          // Adiciona acessório filho (especificação)
          if ($acessorio_filho) {
            $filho_link = get_term_link($acessorio_filho, 'acessorio');
            if ($filho_link && !is_wp_error($filho_link)) {
              $breadcrumb[] = array(
                'name' => $acessorio_filho->name,
                'url' => $filho_link
              );
            }
          }
        }

        // Marca-Modelo (usa dados já obtidos)
        $brand_terms = isset($product['brand_terms']) ? $product['brand_terms'] : array();
        if (!empty($brand_terms) && isset($brand_terms[1]) && !empty($product['marca_url'])) {
          $breadcrumb[] = array(
            'name' => $brand_terms[1]->name,
            'url' => $product['marca_url']
          );
        }

        // Medidas (usa dados já obtidos)
        $medidas = isset($product['medidas_terms']) ? $product['medidas_terms'] : array();
        if (!empty($medidas)) {
          // Ordena: primeiro pais, depois filhos
          $medidas_pais = array();
          $medidas_filhos = array();

          foreach ($medidas as $medida) {
            if ($medida->parent == 0) {
              $medidas_pais[] = $medida;
            } else {
              $medidas_filhos[] = $medida;
            }
          }

          // Adiciona medidas pais primeiro
          foreach ($medidas_pais as $medida_pai) {
            $medida_link = get_term_link($medida_pai, 'medidas');
            if ($medida_link && !is_wp_error($medida_link)) {
              $breadcrumb[] = array(
                'name' => $medida_pai->name,
                'url' => $medida_link
              );
            }
          }

          // Adiciona medidas filhos (especificações)
          foreach ($medidas_filhos as $medida_filho) {
            $medida_link = get_term_link($medida_filho, 'medidas');
            if ($medida_link && !is_wp_error($medida_link)) {
              $breadcrumb[] = array(
                'name' => $medida_filho->name,
                'url' => $medida_link
              );
            }
          }
        }
        break;

      default:
        // Fallback: apenas Category → Marca-Modelo (usa dados já obtidos)
        $brand_terms = isset($product['brand_terms']) ? $product['brand_terms'] : array();
        if (!empty($brand_terms) && isset($brand_terms[1]) && !empty($product['marca_url'])) {
          $breadcrumb[] = array(
            'name' => $brand_terms[1]->name,
            'url' => $product['marca_url']
          );
        }
        break;
    }

    return $breadcrumb;
  }

  public function schema_ProductIndividual($post)
  {

    if (is_null($post))
      return;

    $product = $this->get_product($post);

    // Validar se get_product() retornou dados válidos
    if (!$product || !is_array($product)) {
      error_log("[__Bazar_Schemas] schema_ProductIndividual() falhou: get_product() retornou null ou inválido");
      return null;
    }

    $productSchema = $this->schema_Product(
      $product,
      $type = 'IndividualProduct'
    );
    $productSchema["subjectOf"] = ["@id" => "https://XXXXXX/#website"];

    // Constrói breadcrumb dinamicamente baseado na lógica do CRUD (usa dados já obtidos)
    $lista_breadcrumb = $this->build_breadcrumb_for_post($product);

    // Fallback: se o breadcrumb estiver vazio, usa estrutura mínima
    if (empty($lista_breadcrumb)) {
      $lista_breadcrumb = array();
      if (!empty($product['category']) && !empty($product['category_url'])) {
        $lista_breadcrumb[] = array(
          "name" => $product['category'],
          'url' => $product['category_url']
        );
      }
      if (!empty($product['marca']) && !empty($product['marca_url'])) {
        $lista_breadcrumb[] = array(
          "name" => $product['marca'],
          'url' => $product['marca_url']
        );
      }
    }

    $this->breadcrumb = $lista_breadcrumb;

    return [
      $productSchema,
      $this->schema_Brand($product['marca'], $product['marca_url']),
      $this->schema_Breadcrumb($lista_breadcrumb)
    ];
  }

  public function schema_Product($product = null, $type = null)
  {

    if (is_null($product))
      return false;

    // Register unique product and skip generation if already emitted on this page
    if (!$this->schema_register_unique_product($product)) {
      return false;
    }

    $setType = (!is_null($type)) ? $type : "Product";

    $schema = [
      "@type" => $setType,
      "@id" => "#product_" . $product['id'],
      "additionalType" => "http://www.productontology.org/id/Bicycle",
      "productID" => $product['id'],
      "url" => $product['url'],
      "name" => $product['title'],
      "description" => $product['description'],
      "sku" => $product['id'],
      "weight" => $product['peso'] . 'KGM',
      "color" => $product['color'],
      "material" => $product['material'],
      "productionDate" => $product['ano'],
      "manufacturer" => [
        "@type" => "Organization",
        "name" => $product['marca']
      ],
      "brand" => [
        "@type" => "Brand",
        "name" => $product['modelo']
      ],
      "category" => $product['category'],
      "offers" => [
        "@type" => "Offer",
        "priceCurrency" => "BRL",
        "price" => $product['valor_schema'],
        "itemCondition" => "https://schema.org/" . $product['condition'],
        "availability" => "https://schema.org/" . $product['availability'],
        "priceValidUntil" => date('Y-m-d', strtotime('+1 year')),
        "seller" => [
          "@type" => "Person",
          "name" => $product['seller']
        ],
        "shippingDetails" => [
          "@type" => "OfferShippingDetails",
          "shippingLabel" => "Frete por conta do comprador",
          "shippingRate" => [
            "@type" => "MonetaryAmount",
            "currency" => "BRL",
            "value" => "0.00"
          ],
          "shippingOrigin" => [
            "@type" => "DefinedRegion",
            "name" => "BR"
          ],
          "shippingDestination" => [
            "@type" => "DefinedRegion",
            "addressCountry" => "BR"
          ],
          "deliveryTime" => [
            "@type" => "ShippingDeliveryTime",
            "handlingTime" => [
              "@type" => "QuantitativeValue",
              "minValue" => 1,
              "maxValue" => 30,
              "unitCode" => "DAY"
            ],
            "transitTime" => [
              "@type" => "QuantitativeValue",
              "minValue" => 1,
              "maxValue" => 30,
              "unitCode" => "DAY"
            ]
          ]
        ],
        "hasMerchantReturnPolicy" => [
          "@type" => "MerchantReturnPolicy",
          "applicableCountry" => "BR",
          "name" => "Política de Devolução XXXXXX",
          "url" => "https://XXXXXX/termos-de-uso/",
          "returnPolicyCategory" => "https://schema.org/MerchantReturnFiniteReturnWindow",
          "merchantReturnDays" => 60,
          "returnMethod" => "https://schema.org/ReturnByMail",
          "returnFees" => "https://schema.org/ReturnFeesCustomerResponsibility"
        ]
      ],
      "identifier" => [
        "@type" => "PropertyValue",
        "propertyID" => "ID",
        "value" => $product['id']
      ],
      "image" => $product['galery']
    ];
    // CONDITIONAL: aggregateRating only when there are rating counts
    $average_count = isset($product['average_count']) ? intval($product['average_count']) : 0;
    $average_rating = isset($product['average_rating']) ? (float) $product['average_rating'] : 0.0;
    if ($average_count > 0) {
      $schema['aggregateRating'] = [
        "@type" => "AggregateRating",
        "ratingValue" => $average_rating,
        "bestRating" => $average_rating,
        "ratingCount" => $average_count
      ];
    }

    return $schema;
  }

  /**
   * Register product in unique registry and return false if duplicate.
   * @param array $product
   * @return bool True if product is new and registered, False if duplicate
   */
  private function schema_register_unique_product($product)
  {
    if (!is_array($this->unique_products)) {
      $this->unique_products = [];
    }

    $unique_key = '';
    if (isset($product['id']) && !empty($product['id'])) {
      $unique_key = 'product_' . intval($product['id']);
    } elseif (isset($product['url']) && !empty($product['url'])) {
      $unique_key = 'url_' . md5($product['url']);
    }

    if ($unique_key === '') {
      // No reliable key — assume unique
      return true;
    }

    if (isset($this->unique_products[$unique_key])) {
      // Already registered on this page render
      // error_log("[__Bazar_Schemas] Produto duplicado detectado, chave: " . $unique_key);
      return false;
    }

    $this->unique_products[$unique_key] = true;
    return true;
  }

  public function schema_CollectionProducts($products)
  {

    if (is_null($products) || empty($products))
      return;

    // Ensure unique registry exists for collection rendering as well
    if (!is_array($this->unique_products)) {
      $this->unique_products = [];
    }

    $itemListElement = [];
    // Use associative map to deduplicate brands by @id
    $schema_brands_map = [];

    foreach ($products as $key => $post) {

      $product = $this->get_product($post, true);

      // Pular se get_product() retornou null ou inválido
      if (!$product || !is_array($product)) {
        error_log("[__Bazar_Schemas] schema_CollectionProducts() pulando produto inválido na posição: " . ($key + 1));
        continue;
      }

      $productSchema = $this->schema_Product($product);

      // Pular se schema_Product() retornou false ou inválido
      if (!$productSchema || $productSchema === false) {
        continue;
      }

      // Remove aggregateRating from product schema when rendering collection listing
      // to avoid duplicate aggregateRating snippets in list pages / merchant lists.
      if (isset($productSchema['aggregateRating'])) {
        unset($productSchema['aggregateRating']);
      }

      $itemListElement[] = [
        "@type" => "ListItem",
        "position" => $key + 1,
        "item" => $productSchema // Adicionando o schema do produto
      ];

      // Adicionar brand apenas se existir (deduplicado pelo @id)
      if (isset($product['marca']) && isset($product['marca_url'])) {
        $brand_schema = $this->schema_Brand($product['marca'], $product['marca_url']);
        if (!empty($brand_schema) && is_array($brand_schema) && isset($brand_schema['@id'])) {
          $schema_brands_map[$brand_schema['@id']] = $brand_schema;
        } else {
          // Fallback: use URL-based @id if schema_Brand returned non-standard
          $fallback_id = rtrim($product['marca_url'], '/') . '#brand';
          $schema_brands_map[$fallback_id] = $this->schema_Brand($product['marca'], $product['marca_url']);
        }
      }
    }

    $collection = [
      "@context" => "https://schema.org",
      "@graph" => [
        "@type" => "CollectionPage",
        "@id" => "#products",
        "mainEntity" => [
          "@type" => "ItemList",
          "itemListElement" => $itemListElement
        ],
        "potentialAction" => [
          "@type" => "ReadAction",
          "target" => $this->url
        ],
      ]
    ];
    //return $collectionPageSchema;        
    $schema[] = $collection;
    // Convert map to indexed array and only add if not empty
    $schema_brands = array_values($schema_brands_map);
    if (!empty($schema_brands)) {
      $schema[] = ["@context" => "https://schema.org", "@graph" => $schema_brands];
    }

    $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '<script type="application/ld+json">' . $json . '</script>';
  }


  /* 
   * BLOG
   */
  public function schema_NewsArticle($post)
  {
    // $autor = ( get_the_author_meta('display_name', $post->id) != '' ) ? get_the_author_meta('display_name', $post->id) : 'XXXXXX';        
    $img = get_the_post_thumbnail_url($post->ID, 'l');
    $img_thumb = get_the_post_thumbnail_url($post->ID, 'm');
    $schema = [
      "@type" => "NewsArticle",
      "@id" => "#news_" . $post->ID,
      "datePublished" => get_the_date('c', $post->ID),
      "dateModified" => get_the_modified_date('c', $post->ID),
      "headline" => get_the_title($post->ID),
      "description" => get_the_excerpt($post->ID),
      "url" => get_the_permalink($post->ID),
      "image" => $img,
      "thumbnail" => $img_thumb,
      "articleSection" => [
        "Bicicleta",
        "Mountain Bike (MTB)",
        "Bike"
      ],
      "author" => [
        "@type" => "Organization",
        "name" => "XXXXXX",
        "url" => "https://XXXXXX/quem-somos/"
      ],
      "inLanguage" => "pt-BR",
    ];
    return $schema;
  }

  public function schema_NewsArticleItem()
  {

    $schema = [
      "@type" => "NewsArticle",
      "@id" => "#news_" . $this->post_id,
      "url" => $this->url,
      "mainEntityOfPage" => $this->url,
      "headline" => $this->title,
      "description" => $this->description,
      "image" => $this->img,
      "datePublished" => get_the_date('c', $this->post_id),
      "dateModified" => get_the_modified_date('c', $this->post_id),
      "author" => [
        "@type" => "Organization",
        "name" => "XXXXXX",
        "url" => "https://XXXXXX/quem-somos/"
      ],
      "articleSection" => [
        "Bicicleta",
        "Mountain Bike (MTB)",
        "Bike"
      ],
      "articleBody" => get_the_content(),
      "isAccessibleForFree" => "http://schema.org/True",
      "isPartOf" => ["@id" => "https://XXXXXX/#website"],
      "publisher" => ["@id" => "https://XXXXXX/#organization"],
      "inLanguage" => "pt-BR"
    ];
    return $schema;
  }

  public function schema_CollectionNewsArticle($posts)
  {

    if (is_null($posts) || empty($posts))
      return '';

    $itemListElement = [];
    foreach ($posts as $key => $post) {
      $postSchema = $this->schema_NewsArticle($post); // Gerando schema individual do produto            
      $itemListElement[] = [
        "@type" => "ListItem",
        "position" => $key + 1,
        "item" => $postSchema // Adicionando o schema do produto
      ];
    }
    $schema = [
      "@context" => "https://schema.org",
      "@graph" => [
        "@type" => "CollectionPage",
        "@id" => "#news",
        "inLanguage" => "pt-BR",
        "potentialAction" => [
          "@type" => "ReadAction",
          "target" => $this->url
        ],
        "mainEntity" => [
          "@type" => "ItemList",
          "itemListElement" => $itemListElement
        ]
      ]
    ];
    // Não exibir schema durante geração de sitemap XML
    $json_schema = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '<script type="application/ld+json">' . $json_schema . '</script>';
  }

  /**
   * Gera schema FAQPage para perguntas frequentes
   * @param array $faq_items Array de itens FAQ com 'pergunta' e 'resposta'
   * @return void
   */
  public function schema_FAQPage($faq_items = null)
  {

    if (is_null($faq_items) || empty($faq_items) || !is_array($faq_items)) {
      return;
    }

    $mainEntity = [];

    foreach ($faq_items as $key => $item) {
      // Validar se o item tem pergunta e resposta
      if (!isset($item['pergunta']) || empty($item['pergunta'])) {
        continue;
      }

      if (!isset($item['resposta']) || empty($item['resposta'])) {
        continue;
      }

      // Limpar HTML da pergunta e resposta para o schema
      $pergunta = wp_strip_all_tags($item['pergunta']);
      $resposta = wp_strip_all_tags($item['resposta']);

      // Se a resposta estiver vazia após remover HTML, pular
      if (empty($resposta)) {
        continue;
      }

      $mainEntity[] = [
        "@type" => "Question",
        "name" => $pergunta,
        "acceptedAnswer" => [
          "@type" => "Answer",
          "text" => $resposta
        ]
      ];
    }

    // Se não houver perguntas válidas, retornar
    if (empty($mainEntity)) {
      return;
    }

    $schema = [
      "@context" => "https://schema.org",
      "@type" => "FAQPage",
      "@id" => $this->url . "#faq",
      "mainEntity" => $mainEntity,
      "inLanguage" => "pt-BR",
      "isPartOf" => ["@id" => "https://XXXXXX/#website"]
    ];

    $json_schema = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '<script type="application/ld+json">' . $json_schema . '</script>';
  }

  public function schema_WebPage()
  {
    $schema_WebPage = [
      "@type" => "WebPage",
      "@id" => "#webpage",
      "url" => $this->url,
      "name" => $this->title,
      "description" => $this->description,
      "primaryImageOfPage" => ["@id" => "#primaryimage"],
      "thumbnailUrl" => $this->img,
      "potentialAction" => [
        [
          "@type" => "ReadAction",
          "target" => [$this->url]
        ]
      ],
      "isPartOf" => ["@id" => "https://XXXXXX/#website"],
      "inLanguage" => "pt-BR"
    ];
    return $schema_WebPage;
  }

  public function schema_DefinedTermSet()
  {
    $schema = [
      "@type" => ["DefinedTermSet", "Cycling"],
      "@id" => "#glossario-ciclismo",
      "name" => $this->title,
      "description" => $this->description,
      "mainEntityOfPage" => ["@id" => "#definedTerm"],
      "subjectOf" => ["@id" => "https://XXXXXX/#website"]
    ];
    return $schema;
  }

  public function schema_DefinedTerm()
  {
    $schema = [
      [
        "@type" => ["DefinedTermSet"],
        "@id" => "#glossario-ciclismo",
        "name" => "Glossário de Ciclismo",
      ],
      [
        "@type" => "DefinedTerm",
        "@id" => "#definedTerm",
        "url" => $this->url,
        "name" => $this->title,
        "description" => $this->description,
        "image" => $this->img,
        "potentialAction" => [
          [
            "@type" => "ReadAction",
            "target" => [$this->url]
          ]
        ],
        "inDefinedTermSet" => "https://XXXXXX/#glossario-ciclismo",
        "subjectOf" => ["@id" => "https://XXXXXX/#website"]
      ]
    ];
    return $schema;

  }

  public function schema_SearchResultsMain()
  {
    $schema = [
      "@type" => "SearchResultsPage",
      "@id" => "#archive_search",
      "name" => $this->key
    ];
    return $schema;
  }

  public function schema_SearchResults()
  {
    if ($this->key == '')
      return '';
    $schema = [
      "@type" => "SearchResultsPage",
      "@id" => "#categoria_search",
      "name" => $this->key
    ];
    return $schema;
  }

  public function schema_Breadcrumb($breadcrumbList = null)
  {

    if (is_null($breadcrumbList))
      return;

    $listItem = [];
    foreach ($breadcrumbList as $key => $value):
      $listItem[] = [
        "@type" => "ListItem",
        "position" => $key + 1,
        "name" => $value['name'],
        "item" => $value['url']
      ];
    endforeach;

    $breadcrumbSchema = [
      "@type" => "BreadcrumbList",
      "itemListElement" => $listItem
    ];
    return $breadcrumbSchema;
  }

  public function schema_Gallery($imgs = null)
  {

    if (is_null($imgs))
      return '';

    $images = [];
    foreach ($imgs as $img) {
      $images[] = [
        "@type" => "ImageObject",
        "url" => $img[0],
        "thumbnailUrl" => $img[0],
        "inLanguage" => "pt-BR"
      ];
    }
    ;
    $schema_Gallery = [
      "@context" => "https://schema.org",
      "@type" => "ImageGallery",
      "@id" => "#gallery",
      "image" => $images,
      "inLanguage" => "pt-BR",
      "author" => [
        "@type" => "Person",
        "name" => $this->author_name
      ],
      "potentialAction" => [
        [
          "@type" => "ViewAction",
          "target" => [$this->url]
        ]
      ]
    ];
    $json_schema = json_encode($schema_Gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '<script type="application/ld+json">' . $json_schema . '</script>';
  }

  public function schema_Brand($name = null, $url = null, $logo = null)
  {

    if (is_null($name) || $name == '')
      return '';
    if (is_null($url) || $url == '')
      return '';

    $schema = [
      "@type" => "Brand",
      "@id" => $url . "#brand",
      "url" => $url,
      "name" => $name
    ];
    if (!is_null($logo)):
      $schema['logo'] = [
        "@type" => "ImageObject",
        "url" => $logo,
        "caption" => "Logo of " . $name
      ];
    endif;
    return $schema;
  }


  public function schema_Brands($schema_brands)
  {
    $json_schema = json_encode(["@context" => "https://schema.org", "@graph" => $schema_brands], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '<script type="application/ld+json">' . $json_schema . '</script>';
  }

  private function schema_Main()
  {
    //var_dump( $this->schemaCustom );
    $this->schema = [
      "@context" => "https://schema.org",
      "@graph" => [
        $this->schemaCustom,
        [
          "@type" => "ImageObject",
          "@id" => "#primaryimage",
          "url" => $this->img,
          "contentUrl" => $this->img,
          "inLanguage" => "pt-BR"
        ],
        [
          "@type" => "WebSite",
          "@id" => "https://XXXXXX/#website",
          "url" => "https://XXXXXX/",
          "name" => "XXXXXX",
          "alternateName" => "XXXXXX",
          "description" => get_bloginfo('description'),
          "publisher" => ["@id" => "https://XXXXXX/#organization"],
          "potentialAction" => [
            [
              "@type" => "SearchAction",
              "target" => "https://XXXXXX/?s={search_term_string}",
              "query-input" => "required name=search_term_string"
            ]
          ],
          "inLanguage" => "pt-BR"
        ],
        [
          "@type" => "Organization",
          "@id" => "https://XXXXXX/#organization",
          "name" => "XXXXXX",
          "url" => "https://XXXXXX/",
          "email" => "XXXXXX",
          "telephone" => "XXXXXX",
          "address" => [
            "@type" => "PostalAddress",
            "addressLocality" => "XXXXXX",
            "addressRegion" => "XXXXXX",
            "addressCountry" => "BR",
          ],
          "knowsAbout" => ["bicicletas", "bicicletas usadas", "peças de bicicleta", "classificado de bicicletas", "ciclismo", "vender bicicleta usada", "comprar bicicleta usada"],
          "sameAs" => [
            "https://XXXXXX/",
            "https://XXXXXX/",
            "https://XXXXXX",
            "https://XXXXXX",
            "https://www.semexe.com/",
            "https://bazardociclista.com.br/",
            "https://www.olx.com.br/bicicletas/",
            "https://www.mercadolivre.com.br/bicicletas/",
            "https://www.enjoei.com.br/bicicletas/",
            "https://bikemagazine.com.br/",
            "https://classificados.bikemagazine.com.br/"
          ],
          "logo" => [
            "@type" => "ImageObject",
            "inLanguage" => "pt-BR",
            "@id" => "#logo",
            "url" => $this->logo_url,
            "width" => 1200,
            "height" => 1200,
            "caption" => "XXXXXX"
          ],
          "image" => ["@id" => "#primaryimage"]
        ],
        [
          "@type" => "SiteNavigationElement",
          "name" => "Home",
          "url" => "https://XXXXXX/"
        ],
        [
          "@type" => "SiteNavigationElement",
          "name" => "Mountain Bike (MTB)",
          "url" => "https://XXXXXX/modalidade/mountain-bike-mtb/"
        ],
        [
          "@type" => "SiteNavigationElement",
          "name" => "Bicicletas",
          "url" => "https://XXXXXX/bicicletas/"
        ],
        [
          "@type" => "SiteNavigationElement",
          "name" => "Bicicleta Usada",
          "url" => "https://XXXXXX/bicicleta-usada/"
        ],
        [
          "@type" => "SiteNavigationElement",
          "name" => "Anunciar",
          "url" => "https://XXXXXX/anunciar/"
        ],
        [
          "@type" => "SiteNavigationElement",
          "name" => "Cadastro",
          "url" => "https://XXXXXX/cadastro/"
        ],
        [
          "@type" => "SiteNavigationElement",
          "name" => "Blog",
          "url" => "https://XXXXXX/blog/"
        ],
        [
          "@type" => "SiteNavigationElement",
          "name" => "Web Stories",
          "url" => "https://XXXXXX/web-stories/"
        ],
        [
          "@type" => "SiteNavigationElement",
          "name" => "Quem Somos",
          "url" => "https://XXXXXX/quem-somos/"
        ],
        [
          "@type" => "SiteNavigationElement",
          "name" => "Publicidade",
          "url" => "https://XXXXXX/publicidade/"
        ]
      ]
    ];
    return $this->schema;
  }
}
;
?>