<?php
!defined('ABSPATH') && exit;

global $schema;
global $exclude_ids;
// Inicializar array de IDs excluídos se não existir (global para funcionar entre múltiplas chamadas)
if (!isset($exclude_ids) || !is_array($exclude_ids)) {
    $exclude_ids = array();
}
/**
 * Recebe argumentos via get_template_part
 * Quando chamado: get_template_part('template-parts/home/slide-taxonomy', null, array(...))
 * O WordPress pode extrair as variáveis automaticamente, mas vamos garantir com extract()
 */
$args = isset($args) ? $args : array();
if (!empty($args) && is_array($args)) {
    extract($args);
}
// Configuração da taxonomia atual com valores padrão
// Aceita tanto variáveis diretas ($taxonomy) quanto via array ($args['taxonomy'])
$current_taxonomy = array(
    'taxonomy' => isset($taxonomy) ? $taxonomy : (isset($args['taxonomy']) ? $args['taxonomy'] : 'modalidade'),
    'label' => isset($label) ? $label : (isset($args['label']) ? $args['label'] : 'Bicicletas de Montanha | MTB'),
    'slug' => isset($slug) ? $slug : (isset($args['slug']) ? $args['slug'] : 'mountain-bike-mtb'),
    'term_id' => isset($term_id) ? (int)$term_id : (isset($args['term_id']) ? (int)$args['term_id'] : 13298),
    'posts_per_page' => isset($posts_per_page) ? (int)$posts_per_page : (isset($args['posts_per_page']) ? (int)$args['posts_per_page'] : 16)
);

// Buscar termo para validar e obter informações
$term = null;
if (!empty($current_taxonomy['term_id'])) {
    $term = get_term($current_taxonomy['term_id'], $current_taxonomy['taxonomy']);
} elseif (!empty($current_taxonomy['slug'])) {
    $term = get_term_by('slug', $current_taxonomy['slug'], $current_taxonomy['taxonomy']);
}

// Se não encontrou o termo, tentar usar valores padrão ou pular
if (!$term || is_wp_error($term)) {
    // Tentar buscar por slug se term_id não funcionou
    if (!empty($current_taxonomy['slug'])) {
        $term = get_term_by('slug', $current_taxonomy['slug'], $current_taxonomy['taxonomy']);
    }
    
    // Se ainda não encontrou, pular este slider
    if (!$term || is_wp_error($term)) {
        return; // Retornar sem exibir nada
    }
}

// Atualizar valores com dados reais do termo
$current_taxonomy['term_id'] = $term->term_id;
// Só usar o nome do termo se o label não foi fornecido ou é o padrão
$default_label = 'Bicicletas de Montanha | MTB';
if (empty($current_taxonomy['label']) || $current_taxonomy['label'] === $default_label) {
    $current_taxonomy['label'] = $term->name;
}
if (empty($current_taxonomy['slug'])) {
    $current_taxonomy['slug'] = $term->slug;
}

// Buscar link do termo (usar objeto $term ou term_id + taxonomy)
$term_link = get_term_link($term);
if (is_wp_error($term_link) || empty($term_link)) {
    // Fallback: tentar com term_id e taxonomy
    $term_link = get_term_link($current_taxonomy['term_id'], $current_taxonomy['taxonomy']);
    if (is_wp_error($term_link) || empty($term_link)) {
        $term_link = '#';
    }
}

// Gerar ID único para o slider (necessário para o JavaScript funcionar)
$slider_id = 'taxonomy-slider-' . sanitize_html_class($current_taxonomy['taxonomy']) . '-' . sanitize_html_class($current_taxonomy['slug']) . '-' . $term->term_id;

// Mesmo corte da faixa 1 dos relacionados: excluir anúncios com valor < R$ 2.000 (meta ACF `valor`).
$slide_min_valor_brl = 2000;

// Query de posts (excluindo IDs já exibidos em outros slides)
$query_args = array(
    'post_type' => 'post',
    'posts_per_page' => $current_taxonomy['posts_per_page'],
    'tax_query' => array(
        'relation' => 'AND',
        array(
            'taxonomy' => 'status',
            'field' => 'slug',
            'terms' => 'vendido',
            'operator' => 'NOT IN',
        ),
        array(
            'taxonomy' => $current_taxonomy['taxonomy'],
            'field' => 'term_id',
            'terms' => $current_taxonomy['term_id'],
        ),
    ),
    'meta_query' => array(
        'relation' => 'AND',
        array(
            'key' => 'valor',
            'compare' => 'EXISTS',
        ),
        array(
            'key' => 'valor',
            'value' => $slide_min_valor_brl,
            'type' => 'NUMERIC',
            'compare' => '>=',
        ),
    ),
);

// Adicionar exclusão de IDs se houver produtos já exibidos
if (!empty($exclude_ids) && is_array($exclude_ids)) {
    $query_args['post__not_in'] = $exclude_ids;
}

// Aplicar ordenação por destaque (posts em destaque aparecem primeiro)
if (function_exists('bazar_apply_destaque_ordering')) {
    bazar_apply_destaque_ordering($query_args);
}

// Query WP
$tax_query_obj = new WP_Query($query_args);
if ($tax_query_obj->have_posts()) :    
    // Embaralhar posts de forma inteligente: separar destaques e não-destaques
    // Destaques aparecem primeiro, mas com ordem variada dentro de cada grupo
    $destaques = array();
    $nao_destaques = array();
    
    foreach ($tax_query_obj->posts as $post) {
        if (has_term('destaque', 'status', $post->ID)) {
            $destaques[] = $post;
        } else {
            $nao_destaques[] = $post;
        }
    }
    
    // Embaralhar cada grupo separadamente
    shuffle($destaques);
    shuffle($nao_destaques);
    
    // Juntar: destaques primeiro, depois não-destaques
    $tax_query_obj->posts = array_merge($destaques, $nao_destaques);
    // Array pra Schema CollectionProducts
    $items = array();
?>
<div class="row align-center taxonomy-slider-section">
    <div class="s-12 col">        
        
        <h2 class="size-1">
            <a href="<?php echo esc_url($term_link); ?>" class="slider-link">
                <?php _e('Ver todos', 'bazar'); ?>
            </a>
            <br/>    
            <?php echo esc_html($current_taxonomy['label']); ?>
        </h2>
        
        <div 
            id="<?php echo esc_attr($slider_id); ?>" 
            class="splide taxonomy-slider" 
            role="group" 
            aria-label="<?php echo esc_attr($current_taxonomy['label']); ?>"
        >
            <div class="splide__track">
                <ul class="splide__list">
                    <?php    
                    while ($tax_query_obj->have_posts()) : 
                        $tax_query_obj->the_post();
                        $post = $tax_query_obj->post;
                        
                        // Adicionar ID à lista de excluídos para não repetir em outros slides
                        if (!in_array($post->ID, $exclude_ids)) {
                            $exclude_ids[] = $post->ID;
                        }
                        
                        $items[] = $post;
                    ?>
                    <li class="splide__slide">
                        <div class="slide-item">
                            <?php                 
                            get_template_part('template-parts/loop/product-card', null, array(
                                'post' => $post
                            )); 
                            ?>
                        </div>
                    </li>    
                    <?php 
                    endwhile; 
                    wp_reset_postdata();
                    ?>
                </ul>
            </div>
        </div>
        
        <?php
        // Schema markup para SEO
        if (!empty($items) && isset($schema) && method_exists($schema, 'schema_CollectionProducts')) {
            $schema->schema_CollectionProducts($items);
        }
        ?>
    </div>
</div>
<?php 
endif; // Fim do if have_posts
?>