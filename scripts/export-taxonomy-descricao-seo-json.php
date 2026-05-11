<?php
/**
 * Exportador de taxonomia em JSON incluindo o campo 'descricao_seo'
 *
 * Formato de saída (taxonomia hierárquica):
 * [
 *   {
 *     "categoria": "Nome do termo pai",
 *     "descricao_seo": "Descrição SEO do pai",
 *     "tipo": [
 *       { "name": "Filho 1", "descricao_seo": "..." },
 *       { "name": "Filho 2", "descricao_seo": "..." }
 *     ]
 *   },
 *   ...
 * ]
 *
 * Formato para taxonomia não hierárquica: lista plana de termos com term_id, name, slug, description, descricao_seo.
 *
 * Uso: Ferramentas > Exportar Taxonomia (descricao_seo) ou admin.php?page=bazar-export-taxonomy-descricao-seo
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
    return;
}

/**
 * Obtém o valor de descricao_seo para um termo (ACF ou term_meta).
 *
 * @param int    $term_id  ID do termo
 * @param string $taxonomy Slug da taxonomia
 * @return string
 */
function bazar_get_term_descricao_seo($term_id, $taxonomy)
{
    $descricao_seo = '';
    if (function_exists('get_field')) {
        $descricao_seo = get_field('descricao_seo', $taxonomy . '_' . $term_id);
        if (is_array($descricao_seo)) {
            $descricao_seo = '';
        }
    }
    if ((string) $descricao_seo === '') {
        $descricao_seo = get_term_meta($term_id, 'descricao_seo', true);
    }
    return is_string($descricao_seo) ? $descricao_seo : '';
}

/**
 * Retorna array com a estrutura da taxonomia incluindo descricao_seo.
 *
 * @param string $taxonomy Slug da taxonomia
 * @return array
 */
function bazar_get_taxonomy_descricao_seo_json($taxonomy)
{
    $result = array();

    $tax_obj = get_taxonomy($taxonomy);
    if (!$tax_obj) {
        return $result;
    }

    if ($tax_obj->hierarchical) {
        $parents = get_terms(array(
            'taxonomy' => $taxonomy,
            'parent' => 0,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        if (is_wp_error($parents) || empty($parents)) {
            return $result;
        }

        foreach ($parents as $parent) {
            $children = get_terms(array(
                'taxonomy' => $taxonomy,
                'parent' => $parent->term_id,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC'
            ));
            $tipos = array();
            if (!is_wp_error($children) && !empty($children)) {
                foreach ($children as $child) {
                    $tipos[] = array(
                        'name' => $child->name,
                        'slug' => $child->slug,
                        'term_id' => $child->term_id,
                        'descricao_seo' => bazar_get_term_descricao_seo($child->term_id, $taxonomy)
                    );
                }
            }
            $result[] = array(
                'categoria' => $parent->name,
                'slug' => $parent->slug,
                'term_id' => $parent->term_id,
                'descricao_seo' => bazar_get_term_descricao_seo($parent->term_id, $taxonomy),
                'tipo' => $tipos
            );
        }
    } else {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        if (is_wp_error($terms) || empty($terms)) {
            return $result;
        }
        foreach ($terms as $term) {
            $result[] = array(
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'descricao_seo' => bazar_get_term_descricao_seo($term->term_id, $taxonomy)
            );
        }
    }

    return $result;
}

/**
 * Gera JSON a partir da estrutura
 *
 * @param string $taxonomy Slug da taxonomia
 * @param bool   $pretty   Se true, formata com indentação
 * @return string
 */
function bazar_export_taxonomy_descricao_seo_json($taxonomy, $pretty = true)
{
    $data = bazar_get_taxonomy_descricao_seo_json($taxonomy);
    $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($pretty) {
        $options |= JSON_PRETTY_PRINT;
    }
    return json_encode($data, $options);
}

/**
 * Handler para exibir JSON quando invocado via admin (GET param)
 */
function bazar_export_taxonomy_descricao_seo_json_handler()
{
    if (isset($_GET['bazar_export_taxonomy_descricao_seo']) && current_user_can('manage_options')) {
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : 'modalidade';
        if (!taxonomy_exists($taxonomy)) {
            status_header(400);
            echo json_encode(array('error' => 'Taxonomia inválida'));
            exit;
        }
        $pretty = isset($_GET['pretty']) && $_GET['pretty'] == '1';
        header('Content-Type: application/json; charset=utf-8');
        echo bazar_export_taxonomy_descricao_seo_json($taxonomy, $pretty);
        exit;
    }
}
add_action('admin_init', 'bazar_export_taxonomy_descricao_seo_json_handler');

/**
 * Adiciona página em Ferramentas para visualizar/baixar o JSON
 */
function bazar_add_export_taxonomy_descricao_seo_menu()
{
    add_management_page(
        'Exportar Taxonomia (descricao_seo)',
        'Exportar Taxonomia (descricao_seo)',
        'manage_options',
        'bazar-export-taxonomy-descricao-seo',
        'bazar_export_taxonomy_descricao_seo_page'
    );
}
add_action('admin_menu', 'bazar_add_export_taxonomy_descricao_seo_menu');

function bazar_export_taxonomy_descricao_seo_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Acesso negado', 'bazar'));
    }

    $taxonomies = get_taxonomies(array('public' => true), 'objects');
    $current_taxonomy = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : 'modalidade';
    if (!taxonomy_exists($current_taxonomy)) {
        $current_taxonomy = 'modalidade';
    }

    $json_data = bazar_export_taxonomy_descricao_seo_json($current_taxonomy, true);
    $base_url = admin_url('admin.php?page=bazar-export-taxonomy-descricao-seo&bazar_export_taxonomy_descricao_seo=1&taxonomy=' . rawurlencode($current_taxonomy));
    $url_pretty = $base_url . '&pretty=1';
    $url_compact = $base_url;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Exportar taxonomia com campo descricao_seo', 'bazar'); ?></h1>

        <form method="get" action="" style="margin: 1em 0;">
            <input type="hidden" name="page" value="bazar-export-taxonomy-descricao-seo" />
            <label for="taxonomy"><?php echo esc_html__('Taxonomia:', 'bazar'); ?></label>
            <select name="taxonomy" id="taxonomy" onchange="this.form.submit()">
                <?php foreach ($taxonomies as $tax): ?>
                    <option value="<?php echo esc_attr($tax->name); ?>" <?php selected($current_taxonomy, $tax->name); ?>>
                        <?php echo esc_html($tax->label . ' (' . $tax->name . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button"><?php echo esc_html__('Atualizar', 'bazar'); ?></button>
        </form>

        <p>
            <strong><?php echo esc_html__('Taxonomia atual:', 'bazar'); ?></strong> <code><?php echo esc_html($current_taxonomy); ?></code>
        </p>
        <p>
            <a class="button button-primary" href="<?php echo esc_url($url_pretty); ?>" target="_blank"><?php echo esc_html__('Abrir JSON (formatado)', 'bazar'); ?></a>
            <a class="button" href="<?php echo esc_url($url_compact); ?>" target="_blank"><?php echo esc_html__('Download JSON (compacto)', 'bazar'); ?></a>
        </p>
        <h2><?php echo esc_html__('Preview / Copiar JSON abaixo:', 'bazar'); ?></h2>
        <textarea readonly style="width:100%;height:400px;font-family: monospace;"><?php echo esc_textarea($json_data); ?></textarea>
    </div>
    <?php
}
