<?php
/**
 * Slide de Anúncios em Destaque
 * 
 * Exibe apenas anúncios que estão em destaque (termo 'destaque' na taxonomia 'status')
 * Hierarquia de filtros: Cidade > Category > Modalidade > Todos
 * Focado em maximizar ROI dos anúncios impulsionados
 * 
 * @package XXXXXX
 */
global $schema;
global $exclude_posts_id;

// Obter termo destaque
$destaque_term_id = 0;
if (function_exists('bazar_get_destaque_term_id')) {
    $destaque_term_id = bazar_get_destaque_term_id();
}

// Se não encontrou o termo, não exibir nada
if ($destaque_term_id <= 0) {
    return;
}

// Inicializar array de exclusão
if (!is_array($exclude_posts_id)) {
    $exclude_posts_id = array();
}

// Obter localização do usuário (cidade)
$cidade_ids = array();
$current_location = null;
if (function_exists('bazar_get_current_location')) {
    $current_location = bazar_get_current_location();
    if ($current_location && !empty($current_location['localizacao']['cidade_term_id'])) {
        $cidade_ids[] = (int)$current_location['localizacao']['cidade_term_id'];
    }
}

// Obter category do contexto (single.php ou archive.php)
$category_ids = array();
if (is_singular('post')) {
    // Se estiver em single.php, pegar category do post atual
    $post_categories = get_the_terms(get_the_ID(), 'category');
    if ($post_categories && !is_wp_error($post_categories)) {
        foreach ($post_categories as $cat) {
            $category_ids[] = $cat->term_id;
        }
    }
} elseif (is_category() || (is_tax() && get_queried_object()->taxonomy === 'category')) {
    // Se estiver em archive de category
    $current_term = get_queried_object();
    if ($current_term && !is_wp_error($current_term)) {
        $category_ids[] = $current_term->term_id;
    }
}

// Obter modalidade do contexto
$modalidade_ids = array();
if (is_singular('post')) {
    // Se estiver em single.php, pegar modalidade do post atual
    $post_modalidades = get_the_terms(get_the_ID(), 'modalidade');
    if ($post_modalidades && !is_wp_error($post_modalidades)) {
        foreach ($post_modalidades as $modalidade) {
            $modalidade_ids[] = $modalidade->term_id;
        }
    }
} elseif (is_tax('modalidade')) {
    // Se estiver em archive de modalidade
    $current_term = get_queried_object();
    if ($current_term && !is_wp_error($current_term)) {
        $modalidade_ids[] = $current_term->term_id;
    }
}

// Base query args (comum a todas as queries)
$base_query_args = array(
    'post_type' => 'post',
    'post_status' => 'publish',
    'tax_query' => array(
        'relation' => 'AND',
        array(
            'taxonomy' => 'status',
            'field' => 'slug',
            'terms' => 'vendido',
            'operator' => 'NOT IN',
        ),
        array(
            'taxonomy' => 'status',
            'field' => 'term_id',
            'terms' => $destaque_term_id,
        ),
    ),
    'orderby' => 'rand',
);

// Adicionar exclusão de IDs se houver produtos já exibidos
if (!empty($exclude_posts_id) && is_array($exclude_posts_id)) {
    $base_query_args['post__not_in'] = $exclude_posts_id;
}

// Array para armazenar posts encontrados
$destaques_posts = array();
$posts_needed = 12;

// Prioridade 1: Destaques da cidade do usuário
if (!empty($cidade_ids) && count($destaques_posts) < $posts_needed) {
    $query_args = $base_query_args;
    $query_args['posts_per_page'] = $posts_needed - count($destaques_posts);
    $query_args['tax_query'][] = array(
        'taxonomy' => 'cidade',
        'field' => 'term_id',
        'terms' => $cidade_ids,
    );
    
    $query = new WP_Query($query_args);
    if ($query->have_posts()) {
        $existing_ids = wp_list_pluck($destaques_posts, 'ID');
        if (!is_array($existing_ids)) {
            $existing_ids = array();
        }
        foreach ($query->posts as $post) {
            if (!in_array($post->ID, $existing_ids)) {
                $destaques_posts[] = $post;
                $existing_ids[] = $post->ID; // Atualizar lista para próxima iteração
            }
        }
    }
}

// Prioridade 2: Destaques da mesma category
if (!empty($category_ids) && count($destaques_posts) < $posts_needed) {
    $query_args = $base_query_args;
    $query_args['posts_per_page'] = $posts_needed - count($destaques_posts);
    // Adicionar exclusão dos posts já encontrados
    $existing_ids = wp_list_pluck($destaques_posts, 'ID');
    if (!is_array($existing_ids)) {
        $existing_ids = array();
    }
    if (!empty($existing_ids)) {
        $base_exclude = !empty($base_query_args['post__not_in']) && is_array($base_query_args['post__not_in']) 
            ? $base_query_args['post__not_in'] 
            : array();
        $query_args['post__not_in'] = array_merge($existing_ids, $base_exclude);
    }
    $query_args['tax_query'][] = array(
        'taxonomy' => 'category',
        'field' => 'term_id',
        'terms' => $category_ids,
    );
    
    $query = new WP_Query($query_args);
    if ($query->have_posts()) {
        $existing_ids = wp_list_pluck($destaques_posts, 'ID');
        if (!is_array($existing_ids)) {
            $existing_ids = array();
        }
        foreach ($query->posts as $post) {
            if (!in_array($post->ID, $existing_ids)) {
                $destaques_posts[] = $post;
                $existing_ids[] = $post->ID; // Atualizar lista para próxima iteração
            }
        }
    }
}

// Prioridade 3: Destaques da mesma modalidade
if (!empty($modalidade_ids) && count($destaques_posts) < $posts_needed) {
    $query_args = $base_query_args;
    $query_args['posts_per_page'] = $posts_needed - count($destaques_posts);
    // Adicionar exclusão dos posts já encontrados
    $existing_ids = wp_list_pluck($destaques_posts, 'ID');
    if (!is_array($existing_ids)) {
        $existing_ids = array();
    }
    if (!empty($existing_ids)) {
        $base_exclude = !empty($base_query_args['post__not_in']) && is_array($base_query_args['post__not_in']) 
            ? $base_query_args['post__not_in'] 
            : array();
        $query_args['post__not_in'] = array_merge($existing_ids, $base_exclude);
    }
    $query_args['tax_query'][] = array(
        'taxonomy' => 'modalidade',
        'field' => 'term_id',
        'terms' => $modalidade_ids,
    );
    
    $query = new WP_Query($query_args);
    if ($query->have_posts()) {
        $existing_ids = wp_list_pluck($destaques_posts, 'ID');
        if (!is_array($existing_ids)) {
            $existing_ids = array();
        }
        foreach ($query->posts as $post) {
            if (!in_array($post->ID, $existing_ids)) {
                $destaques_posts[] = $post;
                $existing_ids[] = $post->ID; // Atualizar lista para próxima iteração
            }
        }
    }
}

// Prioridade 4: Todos os destaques (fallback)
if (count($destaques_posts) < $posts_needed) {
    $query_args = $base_query_args;
    $query_args['posts_per_page'] = $posts_needed - count($destaques_posts);
    // Adicionar exclusão dos posts já encontrados
    $existing_ids = wp_list_pluck($destaques_posts, 'ID');
    if (!is_array($existing_ids)) {
        $existing_ids = array();
    }
    if (!empty($existing_ids)) {
        $base_exclude = !empty($base_query_args['post__not_in']) && is_array($base_query_args['post__not_in']) 
            ? $base_query_args['post__not_in'] 
            : array();
        $query_args['post__not_in'] = array_merge($existing_ids, $base_exclude);
    }
    
    $query = new WP_Query($query_args);
    if ($query->have_posts()) {
        $existing_ids = wp_list_pluck($destaques_posts, 'ID');
        if (!is_array($existing_ids)) {
            $existing_ids = array();
        }
        foreach ($query->posts as $post) {
            if (!in_array($post->ID, $existing_ids)) {
                $destaques_posts[] = $post;
                $existing_ids[] = $post->ID; // Atualizar lista para próxima iteração
            }
        }
    }
}

// Se não encontrou posts e estava usando exclusão, tentar sem exclusão
if (empty($destaques_posts) && !empty($exclude_posts_id)) {
    unset($base_query_args['post__not_in']);
    $base_query_args['posts_per_page'] = 12;
    $query = new WP_Query($base_query_args);
    if ($query->have_posts()) {
        $destaques_posts = $query->posts;
    }
}

// Verifica se tem posts
if (!empty($destaques_posts)) :
    // Limitar a 12 posts finais
    $destaques_posts = array_slice($destaques_posts, 0, 12);
    
    // Embaralhar para variar os destaques exibidos
    shuffle($destaques_posts);
    
    $items = array();
?>
<h2 class="size-1">
    <?php _e('Anúncios em Destaque', 'bazar'); ?>
</h2>
<div 
    id="destaques-slider" 
    class="splide splide_related" 
    role="group" 
    aria-label="<?php _e('Anúncios em Destaque', 'bazar'); ?>"
>
    <div class="splide__track">
        <ul class="splide__list">
            <?php    
            foreach ($destaques_posts as $key => $post) :
                $items[] = $post;
                
                // Adicionar ID à lista de excluídos para não repetir em outros slides
                // Garantir que $exclude_posts_id seja um array
                if (!is_array($exclude_posts_id)) {
                    $exclude_posts_id = array();
                }
                if (!in_array($post->ID, $exclude_posts_id)) {
                    $exclude_posts_id[] = $post->ID;
                }
            ?>
            <li class="splide__slide">
                <?php                 
                get_template_part('template-parts/loop/product-card', null, array(
                    'post' => $post
                )); ?>
            </li>    
            <?php 
            endforeach; 
            wp_reset_postdata();
            
            // Schema CollectionProducts
            if ($schema && method_exists($schema, 'schema_CollectionProducts')) {
                $schema->schema_CollectionProducts($items);
            }
            ?>
        </ul><!-- /splide__list -->
    </div><!-- /splide__track -->
</div><!-- /destaques-slider -->
<?php endif; ?>

