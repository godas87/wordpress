<?php
add_action('pre_get_posts', 'wpa_order_states');
function wpa_order_states($query)
{

    // Feed RSS (/feed): não exibir itens vendidos
    if (
        !is_admin()
        && $query->is_main_query()
        && $query->is_feed()
    ) {
        $post_type = $query->get('post_type');
        if (empty($post_type) || $post_type === 'post') {
            $vendido_term_id = function_exists('bazar_get_vendido_term_id') ? (int) bazar_get_vendido_term_id() : 0;
            if ($vendido_term_id > 0) {
                $existing = (array) $query->get('tax_query');
                $query->set('tax_query', array_merge($existing, array(
                    array(
                        'taxonomy' => 'status',
                        'field' => 'term_id',
                        'terms' => array($vendido_term_id),
                        'operator' => 'NOT IN',
                    ),
                )));
            }
        }
    }

    // /bicicleta-modalidade/ (sem termo): tratar como archive para não dar 404
    if (
        !is_admin()
        && $query->is_main_query()
        && (int) get_query_var('bazar_modalidade_archive') === 1
    ) {
        $query->set('post_type', 'post');
        $query->set('post_status', 'publish');
        $query->is_archive = true;
        $query->is_tax = false;
        $query->is_home = false;
        $query->is_404 = false;
        return $query;
    }

    // if( !is_admin() ) return $query;

    // WEBSTORIES
    if (
        is_admin()
        && $query->is_main_query()
        && $query->get('post_type') === 'web-stories'
    ):
        if ('post_views_count' === $query->get('orderby')) {
            $query->set('meta_key', 'post_views_count');
            $query->set('orderby', 'meta_value_num');
        }
        // $query->set( 'orderby', 'date meta_value_num' );
        // $query->set( 'order', 'desc' );
    endif;

    // ADS (Afiliados)
    if (
        is_admin()
        && $query->is_main_query()
        && $query->get('post_type') === 'ads'
    ) {
        $orderby = $query->get('orderby');
        $meta_keys = array(
            'count_click' => '_count_click_amazon',
            'count_click_ml' => '_count_click_ml',
            'count_click_shopee' => '_count_click_shopee',
            'count_click_centauro' => '_count_click_centauro',
            'count_click_decathlon' => '_count_click_decathlon',
        );
        if (isset($meta_keys[$orderby])) {
            $query->set('meta_key', $meta_keys[$orderby]);
            $query->set('orderby', 'meta_value_num');
        }
    }

    // POSTS - Contagem de contatos (Email e WhatsApp)
    if (
        is_admin()
        && $query->is_main_query()
        && $query->get('post_type') === 'post'
    ):
        // Ordena pela coluna de contagem de emails
        if ('count_contact_email' === $query->get('orderby')):
            $query->set('meta_key', '_count_contact_email');
            $query->set('orderby', 'meta_value_num');
        endif;

        // Ordena pela coluna de contagem de WhatsApp
        if ('count_contact_whatsapp' === $query->get('orderby')):
            $query->set('meta_key', '_count_contact_whatsapp');
            $query->set('orderby', 'meta_value_num');
        endif;
    endif;

    return $query;
}