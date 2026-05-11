<?php
/*
Template Name: API WebStories
Template Post Type: app
*/
$output = array();
$data_atual = new DateTime();
$exclude_tax_ids_bz = array(
    12170,
    12387, //Unisex
    12409, //10-36
    12420, //1.75
    12301,
    12303,
    12456,
    12335,
    12498,
    12404,
    12412,
    12389,
    12401,
    12460,
    12379,
    12381,
    12275,
    12237,
    12457,
    12308,
    12304,
    12343,
    12340,
    12442
);
$tax_exclude_bz = array(
    'post_tag',
    'nav_menu',
    'link_category',
    'post_format',
    'wp_theme',
    'wp_template_part_area',
    'wp_pattern_category',
    'alfabeto',
    'ads',        
    'ads-cta',
    'app',
    'blog',
    'cor',
    'glossario',
    'negociacao',
    'material',
    'palavra-chave',
    'teste',
    'web-stories',
    // 'category', // Descomente se quiser incluir categorias
    // 'marca-modelo', // Exemplo de outra taxonomia
    // 'cidade',
    // 'especificacoes'
);

create_tax();
function create_tax() {

    global $output;
    global $tax_exclude_bz;

    $taxs = get_taxonomies();
    for( $x = 0; $x <= 5; $x++ ) :
        foreach ( $taxs as $tax ) {        
            // Pula as taxonomias excluídas
            if ( in_array( $tax, $tax_exclude_bz ) ) continue;        
            // Armazena o resultado da função recursiva no array global
            $tax_items = generate_items_recursive( $tax );

            if ( $tax_items ) $output = array_merge($output, $tax_items);
        }
    endfor;

    // echo count( $output );
    // Gera o JSON a partir do $output acumulado
    echo json_encode( $output );
    
}

// Função para gerar itens de maneira recursiva
function generate_items_recursive( $taxonomy = 'category', $term_count = 5 ) {

    global $data_atual;
    global $exclude_tax_ids_bz;
    $output_local = array(); // Variável local para armazenar os dados temporários

    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
    ));

    // Excluir termos que não tenham pelo menos $term_count itens publicados (não lixeira, não vendidos)
    $terms = bazar_webstories_filter_terms_by_valid_count( $terms, $taxonomy, $term_count );

    foreach( $terms as $term ) {

        // Apenas Itens Pais para Marcas e Modelos        
        if( 
            $term->taxonomy === 'marca-modelo'
            && $term->parent !== 0
        ) continue;         

        // Verifica se a categoria tem pelo menos o número mínimo de postagens
        if( $term->count < $term_count ) continue;                        

        // Evita categorias ou taxonomias específicas
        if( in_array( $term->term_id, $exclude_tax_ids_bz ) ) continue;

        // Apenas itens filhos
        if( 
            $term->taxonomy !== 'marca-modelo'
            && $term->parent === 0
        ) continue;               
       
        // Tratamento de nomes com base no tipo de taxonomia
        $name = handle_term_name( $term, $taxonomy );

        $pwigo_tag = ( get_field('pwigo_tag', $term) ) 
            ? get_field('pwigo_tag', $term) 
            : 'ciclismo';

        $horas_aleatorias = mt_rand(8 * 3600, 20 * 3600); // 3600 é o número de segundos em uma hora
        $data_formatada = $data_atual->format('Y-m-d') . 'T' . gmdate('H:i:s', $horas_aleatorias);

        $output_local[] = array(
            'taxonomy' => $term->taxonomy,
            'term_id' => $term->term_id,
            'name' => $name,
            'date' => $data_formatada,
            'pwigo_tag' => $pwigo_tag,
        );

        $data_atual->modify('+1 day'); // Adiciona 1 dia para o próximo termo
    }
    return $output_local; // Retorna os itens gerados para o $output global
}

/**
 * Filtra termos mantendo apenas os que têm pelo menos $min_count posts
 * publicados (excluindo lixeira e itens marcados como vendido).
 *
 * @param array  $terms     Array de WP_Term.
 * @param string $taxonomy  Taxonomia.
 * @param int    $min_count Mínimo de posts válidos por termo.
 * @return array
 */
function bazar_webstories_filter_terms_by_valid_count( $terms, $taxonomy, $min_count ) {
    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return $terms;
    }
    $term_ids = array_map( function( $t ) { return (int) $t->term_id; }, $terms );
    $vendido_term_id = function_exists( 'bazar_get_vendido_term_id' ) ? (int) bazar_get_vendido_term_id() : 0;
    global $wpdb;
    $placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
    $args = array_merge( array( $taxonomy ), $term_ids );
    $sub = '';
    if ( $vendido_term_id > 0 ) {
        $sub = "AND p.ID NOT IN (
            SELECT tr2.object_id
            FROM {$wpdb->term_relationships} tr2
            INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
            WHERE tt2.taxonomy = 'status' AND tt2.term_id = %d
        )";
        $args[] = $vendido_term_id;
    }
    $sql = $wpdb->prepare(
        "SELECT tt.term_id, COUNT(DISTINCT p.ID) AS cnt
        FROM {$wpdb->term_taxonomy} tt
        INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
        WHERE tt.taxonomy = %s
        AND tt.term_id IN ($placeholders)
        AND p.post_status = 'publish'
        AND p.post_type = 'post'
        $sub
        GROUP BY tt.term_id
        HAVING cnt >= %d",
        array_merge( $args, array( $min_count ) )
    );
    $valid_ids = $wpdb->get_col( $sql );
    if ( empty( $valid_ids ) ) {
        return array();
    }
    $valid_ids = array_map( 'intval', array_filter( $valid_ids ) );
    return array_filter( $terms, function( $term ) use ( $valid_ids ) {
        return in_array( (int) $term->term_id, $valid_ids, true );
    } );
}

// Função para tratar os nomes com base na taxonomia
function handle_term_name( $term, $taxonomy) {

    $parent = ( $term->parent )
        ? get_term_by('term_id', $term->parent, $term->taxonomy)->name
        : '';

    $name = ( $parent != '' ) 
        ? $parent . ' ' . $term->name
        : $term->name;
        
    // Personalize o tratamento de nomes para diferentes taxonomias
    if( $taxonomy == 'category' ) {
        if (strpos($name, 'Peça') !== false) {
            $name = str_replace('Peça', 'Peça para Bicicleta', $name);
        }
        if( strpos($name, 'Pneu') !== false ) {
            $name = str_replace('Pneu', 'Pneu para Bicicleta', $name);
        }
        if( strpos($name, 'Roda') !== false ) {
            $name = str_replace('Roda', 'Roda para Bicicleta', $name);
        }        
    } 
    elseif( $taxonomy == 'cidade' ){
        
        $name = ( $parent != '' ) 
            ? $term->name . ' ' . $parent
            : $term->name;

        $name = 'Bicicletas usadas em ' . $name;
            
    } 
    elseif( $taxonomy == 'especificacoes' ) {
        $name = 'Bicicletas usadas ' . $name;
        
    }
    elseif( $taxonomy == 'marca-modelo' ) {
        $name = 'Bicicletas ' . $name . ' novas e usadas';
        
    }

    return $name;
}
?>
