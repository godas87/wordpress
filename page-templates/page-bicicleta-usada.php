<?php
/**
 * Template Name: Bicicleta Usada
 * 
 * Template para exibir bicicletas usadas
 * Filtra por taxonomia 'conservacao' com slug 'usado'
 * 
 * @package XXXXXX
 */
get_header();

// Criar schema.org
global $schema;
// Criar SEO meta fields
global $seo;
$seo_data = $seo->SEO_params();
// Obter termos já cacheados do SEO (evita queries duplicadas)
$preloaded_terms = $seo->get_cached_terms();

// Adicionar filtro de conservacao 'usado' aos parâmetros GET
$_GET['conservacao_filter'] = 'usado';

// Construir queries usando função helper compartilhada
// Para page template, não incluir taxonomia da URL (padrão: false)
$queries = bazar_build_archive_queries(array(
  'preloaded_terms' => $preloaded_terms, // Reutilizar termos já carregados
));

// Extrair variáveis
global $base_query, $index_query, $current_term, $post_data, $has_params, $categories_query;
$base_query = $queries['base_query'];
$index_query = $queries['index_query'];
$current_term = $queries['current_term'];
$post_data = $queries['post_data'];
$has_params = isset($queries['has_params']) ? $queries['has_params'] : false;
$categories_query = isset($queries['categories_query']) ? $queries['categories_query'] : null;

// Valores para exibição
$total_results = $index_query->found_posts;
$max_num_pages = $index_query->max_num_pages;

$posts_per_page = (isset($index_query->query_vars['posts_per_page']))
  ? $index_query->query_vars['posts_per_page']
  : 20;

$paged = (isset($index_query->query_vars['paged']) && $index_query->query_vars['paged'] > 0)
  ? $index_query->query_vars['paged']
  : 1;

$start = ($paged == 1)
  ? $paged
  : ($paged * $posts_per_page - $posts_per_page);

$end = ($start + $posts_per_page >= $total_results)
  ? $total_results
  : $start + $posts_per_page;
?>
<section class="archive-header">
  <?php get_template_part('template-parts/ads/adsense/top'); ?>
</section>

<div class="row align-center page archive">
  <div class="s-12 m-3 xl-2 col">
    <?php get_template_part('template-parts/forms/busca-archive'); ?>
  </div>

  <div class="s-11 m-8 xl-9 m-off-1 col p-relative">
    <h1><?php echo $seo_data['title']; ?></h1>

    <?php get_template_part('template-parts/inc/tag-list'); ?>

    <div class="row align-middle header-results">
      <div class="col s-12 m-6">
        <?php get_template_part('template-parts/inc/header-count-results', null, array(
          'total_results' => $total_results,
          'posts_per_page' => $posts_per_page,
          'start' => $start,
          'end' => $end,
          'paged' => $paged
        )); ?>
      </div>
      <div class="col s-12 m-6">
        <?php
        if ($total_results):
          get_template_part('template-parts/inc/order-by');
        endif;
        ?>
      </div>
    </div>

    <?php
    if (
      $index_query->have_posts()
      && !is_wp_error($index_query)
      && !is_null($index_query)
      && !empty($index_query->posts)
    ):
      $items = [];
      ?>
      <div id="content" class="col">
        <?php
        $x = 0;
        while ($index_query->have_posts()):
          $index_query->the_post();
          $index_post = $index_query->post;
          $items[] = $index_post;
          $x++;
          if ($x == 2 || $x > 8 && $x % 6 == 0) {
            get_template_part('template-parts/ads/afiliados/responsive', null, array(
              'css_class' => 'content'
            ));
          }
          get_template_part('template-parts/loop/product-large', null, array(
            'post' => $index_post
          ));
        endwhile;
        $schema->schema_CollectionProducts($items);
        ?>
      </div><!-- /content -->

      <?php
    else:
      get_template_part('template-parts/loop/no-posts');
    endif;
    ?>

    <?php get_template_part('template-parts/cta/cta-archive'); ?>

    <?php
    get_template_part('template-parts/global/pagination', null, array(
      'custom_query' => $index_query
    ));
    ?>
  </div><!-- /col -->
</div><!-- /row -->

<?php get_template_part('template-parts/archive-footer'); ?>

<script type="text/javascript">
  var __BAZAR_Page = 'index';
</script>
<?php get_footer(); ?>