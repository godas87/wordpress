<?php
/**
 * Script para corrigir URLs dos campos 'icone' e 'imagem' em term meta.
 *
 * Problema: foram gravadas URLs como
 *   https://XXXXXX/wp-content/themes/bazar/src/imgs/...
 * O correto é:
 *   https://XXXXXX/src/imgs/...
 *
 * Este script atualiza term_meta (e ACF quando existir) sem reinserir taxonomias.
 *
 * Como executar (apenas UMA VEZ, em produção ou local):
 *
 * 1) Via painel: no functions.php, adicione temporariamente e carregue uma página do admin:
 *    require_once get_template_directory() . '/scripts/fix-icone-urls.php';
 *    Depois remova a linha.
 *
 * 2) Via WP-CLI (na raiz do WordPress):
 *    wp eval-file wp-content/themes/bazar/scripts/fix-icone-urls.php
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
    exit;
}

function bazar_fix_icone_urls_run() {
    global $wpdb;

    $base_site = trailingslashit(site_url());
    $pattern_wrong = '%/wp-content/themes/bazar/src/%';
    $total_fixed = 0;

    foreach (array('icone', 'imagem') as $meta_key) {
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value LIKE %s",
            $meta_key,
            $pattern_wrong
        ));

        if (empty($results)) {
            continue;
        }

        $fixed = 0;
        foreach ($results as $row) {
            $old_url = $row->meta_value;
            // Valores numéricos (attachment ID) não devem ser alterados
            if (is_numeric($old_url)) {
                continue;
            }
            $new_url = preg_replace(
                '#https?://[^/]+/wp-content/themes/bazar/src/#i',
                $base_site . 'src/',
                $old_url
            );

            if ($new_url === $old_url) {
                continue;
            }

            $term_id = (int) $row->term_id;
            update_term_meta($term_id, $meta_key, esc_url_raw($new_url));

            $term = get_term($term_id);
            if ($term && !is_wp_error($term) && function_exists('update_field')) {
                update_field($meta_key, esc_url_raw($new_url), $term->taxonomy . '_' . $term_id);
            }

            $fixed++;
        }

        $total_fixed += $fixed;
        if ($fixed > 0) {
            echo "URLs de '{$meta_key}' corrigidas: {$fixed}.\n";
        }
    }

    update_option('bazar_fix_icone_urls_done', true);

    if ($total_fixed === 0) {
        echo "Nenhum termo com URL de ícone ou imagem incorreta encontrada.\n";
    } else {
        echo "Total corrigido: {$total_fixed}.\n";
    }
}

// Executar uma vez no admin ao carregar o script (via functions.php)
add_action('admin_init', function () {
    if (get_option('bazar_fix_icone_urls_done')) {
        return;
    }
    bazar_fix_icone_urls_run();
}, 99);

// WP-CLI: executar ao carregar o arquivo
if (defined('WP_CLI') && WP_CLI) {
    bazar_fix_icone_urls_run();
}
