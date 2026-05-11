<?php
/**
 * Exportador específico para a taxonomia 'marca-modelo'
 *
 * Formato de saída:
 * [
 *   { "categoria": "Nome do termo pai", "tipo": ["Filho 1", "Filho 2"] },
 *   ...
 * ]
 *
 * Este arquivo deve ser incluído a partir do tema (ex.: require_once get_template_directory() . '/scripts/export-marca-modelo-json.php')
 * para registrar as rotas/admin page. Também pode ser acessado via admin.php?page=bazar-export-marca-modelo
 */

// Prevenir acesso direto quando não carregado dentro do WP
if (!defined('ABSPATH')) {
    return;
}

/**
 * Retorna array com a estrutura solicitada para 'marca-modelo'
 * @return array
 */
function bazar_get_marca_modelo_json()
{
    $taxonomy = 'marca-modelo';
    $result = array();

    $tax_obj = get_taxonomy($taxonomy);
    if (!$tax_obj || !$tax_obj->hierarchical) {
        return $result;
    }

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
                $tipos[] = $child->name;
            }
        }
        $result[] = array(
            'categoria' => $parent->name,
            'tipo' => $tipos
        );
    }

    return $result;
}

/**
 * Gera JSON a partir da estrutura
 */
function bazar_export_marca_modelo_json($pretty = true)
{
    $data = bazar_get_marca_modelo_json();
    if ($pretty) {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Handler para exibir JSON quando invocado via admin (GET param)
 */
function bazar_export_marca_modelo_json_handler()
{
    if (isset($_GET['bazar_export_marca_modelo_json']) && current_user_can('manage_options')) {
        $pretty = isset($_GET['pretty']) && $_GET['pretty'] == '1';
        header('Content-Type: application/json; charset=utf-8');
        echo bazar_export_marca_modelo_json($pretty);
        exit;
    }
}
add_action('admin_init', 'bazar_export_marca_modelo_json_handler');

/**
 * Adiciona página em Ferramentas (Tools) para visualizar/baixar o JSON
 */
function bazar_add_export_marca_modelo_menu()
{
    add_management_page(
        'Exportar Marca-Modelo',
        'Exportar Marca-Modelo',
        'manage_options',
        'bazar-export-marca-modelo',
        'bazar_export_marca_modelo_page'
    );
}
add_action('admin_menu', 'bazar_add_export_marca_modelo_menu');

function bazar_export_marca_modelo_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Acesso negado', 'bazar'));
    }

    $json_data = bazar_export_marca_modelo_json(true);
    $url_pretty = admin_url('admin.php?page=bazar-export-marca-modelo&bazar_export_marca_modelo_json=1&pretty=1');
    $url_compact = admin_url('admin.php?page=bazar-export-marca-modelo&bazar_export_marca_modelo_json=1');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Exportar taxonomy: marca-modelo', 'bazar'); ?></h1>
        <p>
            <a class="button button-primary" href="<?php echo esc_url($url_pretty); ?>" target="_blank"><?php echo esc_html__('Abrir JSON (formatado)', 'bazar'); ?></a>
            <a class="button" href="<?php echo esc_url($url_compact); ?>" target="_blank"><?php echo esc_html__('Download JSON (compacto)', 'bazar'); ?></a>
        </p>
        <h2><?php echo esc_html__('Preview / Copiar JSON abaixo:', 'bazar'); ?></h2>
        <textarea readonly style="width:100%;height:400px;font-family: monospace;"><?php echo esc_textarea($json_data); ?></textarea>
    </div>
    <?php
}

