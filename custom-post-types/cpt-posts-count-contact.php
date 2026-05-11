<?php
/**
 * Adiciona colunas de contagem de contatos na lista de posts
 * 
 * Exibe contadores de email e WhatsApp para posts (anúncios)
 * 
 * @package XXXXXX
 * @version 1.0.0
 */

// 1. Adiciona colunas na lista de posts
add_filter('manage_posts_columns', 'bazar_add_count_contact_columns', 10, 1);
function bazar_add_count_contact_columns($columns) {
    // Adiciona as colunas após a coluna de título
    $columns['count_contact_email'] = 'Email';
    $columns['count_contact_whatsapp'] = 'WhatsApp';
    return $columns;
}

// 2. Exibe os valores nas colunas
add_action('manage_posts_custom_column', 'bazar_show_count_contact_columns', 10, 2);
function bazar_show_count_contact_columns($column_name, $post_id) {
    // Coluna de contagem de emails
    if( 'count_contact_email' === $column_name ) {
        $count = get_post_meta( $post_id, '_count_contact_email', true );
        echo ( empty($count) ) ? '0' : $count;
    }
    
    // Coluna de contagem de WhatsApp
    if( 'count_contact_whatsapp' === $column_name ) {
        $count = get_post_meta( $post_id, '_count_contact_whatsapp', true );
        echo ( empty($count) ) ? '0' : $count;
    }
}

// 3. Estilos CSS para as colunas
add_action( 'admin_head-edit.php', 'estilos_colunas_count_contact' );
function estilos_colunas_count_contact() {
    // Aplicar apenas na lista de posts
    $screen = get_current_screen();
    if( $screen && $screen->post_type === 'post' ) {
        echo '<style>
            .column-count_contact_email,
            .column-count_contact_whatsapp {
                width: 80px;
                text-align: center;
            }
        </style>';
    }
}

// 4. Torna as colunas ordenáveis
add_filter('manage_edit-post_sortable_columns', 'bazar_sortable_count_contact_columns');
function bazar_sortable_count_contact_columns($columns) {
    $columns['count_contact_email'] = 'count_contact_email';
    $columns['count_contact_whatsapp'] = 'count_contact_whatsapp';
    return $columns;
}
?>

