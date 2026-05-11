<?php
/**
 * Custom Post Type: Newsletter
 * Para armazenar emails de assinantes do newsletter
 */
add_action('init', 'cpt_newsletter');
function cpt_newsletter() {
    $labels = array(
        'name' => 'Newsletter',
        'singular_name' => 'Assinante',
        'all_items' => 'Todos os Assinantes',
        'add_new_item' => 'Adicionar Assinante',
        'edit_item' => 'Editar Assinante',
        'new_item' => 'Novo Assinante',
        'view_item' => 'Visualizar Assinante',
        'search_items' => 'Buscar Assinantes',
        'not_found' => 'Nenhum assinante encontrado',
        'not_found_in_trash' => 'Nenhum assinante encontrado na lixeira'
    );
    
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'public' => false, // Não exibir publicamente
        'show_ui' => true, // Mostrar no admin
        'show_in_menu' => true,
        'menu_position' => 25,
        'menu_icon' => 'dashicons-email-alt',
        'show_in_nav_menus' => false,
        'show_in_rest' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => false,
        'can_export' => true,
        'rewrite' => false, // Não precisa de URL pública
        'capability_type' => 'post',
        'supports' => array('title'), // Apenas título (email será o título)
        'publicly_queryable' => false,
    );
    
    register_post_type('newsletter', $args);
}

/**
 * Adicionar colunas customizadas na listagem do admin
 */
add_filter('manage_newsletter_posts_columns', 'newsletter_custom_columns');
function newsletter_custom_columns($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = 'Email';
    $new_columns['date'] = 'Data de Cadastro';
    return $new_columns;
}

/**
 * Tornar o campo título obrigatório e validar email
 */
add_action('save_post_newsletter', 'newsletter_validate_email', 10, 2);
function newsletter_validate_email($post_id, $post) {
    // Pular autosaves e revisões
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (wp_is_post_revision($post_id)) {
        return;
    }
    
    // Validar email
    $email = sanitize_email($post->post_title);
    if (!is_email($email)) {
        wp_die('Erro: Por favor, insira um email válido.');
    }
    
    // Verificar se email já existe
    global $wpdb;
    $existing_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'newsletter' 
        AND post_title = %s 
        AND ID != %d 
        LIMIT 1",
        $email,
        $post_id
    ));
    
    if ($existing_id) {
        wp_die('Erro: Este email já está cadastrado no newsletter.');
    }
}
?>

