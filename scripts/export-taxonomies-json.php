<?php
/**
 * Script para exportar taxonomias em formato JSON hierárquico
 * 
 * Exibe os dados das taxonomias organizados hierarquicamente (pai -> filhos)
 * Mostra apenas o campo 'nome' de cada termo
 * 
 * IMPORTANTE: Execute este script através do painel do WordPress
 * ou via URL. Para executar via painel, adicione no functions.php:
 * require_once get_template_directory() . '/scripts/export-taxonomies-json.php';
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retorna um mapa [term_id => quantidade_de_posts_vendidos] para uma taxonomia.
 *
 * Considera apenas posts publicados. 1 SQL por taxonomia (evita N queries).
 *
 * @param string $taxonomy
 * @return array<int,int>
 */
function bazar_get_vendidos_count_by_term($taxonomy) {
    global $wpdb;

    $vendido_term = get_term_by('slug', 'vendido', 'status');
    if (!$vendido_term || is_wp_error($vendido_term)) {
        return array();
    }

    $sql = $wpdb->prepare("
        SELECT tt.term_id, COUNT(DISTINCT tr.object_id) AS qtd
        FROM {$wpdb->term_taxonomy} tt
        INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
        WHERE tt.taxonomy = %s
        AND p.post_status = 'publish'
        AND tr.object_id IN (
            SELECT tr2.object_id
            FROM {$wpdb->term_relationships} tr2
            INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
            WHERE tt2.taxonomy = 'status'
            AND tt2.term_id = %d
        )
        GROUP BY tt.term_id
    ", $taxonomy, (int) $vendido_term->term_id);

    $rows = $wpdb->get_results($sql);
    $map = array();
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $map[(int) $row->term_id] = (int) $row->qtd;
        }
    }
    return $map;
}

/**
 * Retorna as categorias principais (parent=0 em 'category') como mapa [term_id => slug].
 * Usa bazar_get_main_categories_slugs() se disponível; fallback para [bicicleta, peca, acessorio].
 *
 * @return array<int,string>
 */
function bazar_export_get_main_category_terms() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $slugs = function_exists('bazar_get_main_categories_slugs')
        ? bazar_get_main_categories_slugs()
        : array('bicicleta', 'peca', 'acessorio');

    $cache = array();
    foreach ((array) $slugs as $slug) {
        $slug = (string) $slug;
        if ($slug === '') continue;
        $term = get_term_by('slug', $slug, 'category');
        if ($term && !is_wp_error($term)) {
            $cache[(int) $term->term_id] = $slug;
        }
    }
    return $cache;
}

/**
 * Breakdown por categoria principal (bicicleta/peca/acessorio) para uma taxonomia.
 *
 * Executa 2 SQLs por taxonomia:
 *  - uma para total de posts por (termo, categoria)
 *  - outra para vendidos por (termo, categoria)
 *
 * Retorna estrutura:
 *   [term_id => [ slug_categoria => ['count' => N, 'vendidos' => M] ]]
 *
 * Não gera breakdown para a taxonomia 'category' (seria redundante).
 *
 * @param string $taxonomy
 * @return array<int,array<string,array<string,int>>>
 */
function bazar_get_category_breakdown_by_term($taxonomy) {
    global $wpdb;

    if ($taxonomy === 'category') {
        return array();
    }

    $main_categories = bazar_export_get_main_category_terms();
    if (empty($main_categories)) {
        return array();
    }

    $cat_ids = array_keys($main_categories);
    $cat_placeholders = implode(',', array_fill(0, count($cat_ids), '%d'));

    // Totais (inclui vendidos) agrupados por (termo da taxonomia, categoria principal).
    $sql_total = $wpdb->prepare("
        SELECT ttm.term_id AS main_term_id,
               ttc.term_id AS category_term_id,
               COUNT(DISTINCT tr.object_id) AS qtd
        FROM {$wpdb->term_taxonomy} ttm
        INNER JOIN {$wpdb->term_relationships} tr ON ttm.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
        INNER JOIN {$wpdb->term_relationships} trc ON trc.object_id = p.ID
        INNER JOIN {$wpdb->term_taxonomy} ttc ON ttc.term_taxonomy_id = trc.term_taxonomy_id
        WHERE ttm.taxonomy = %s
          AND ttc.taxonomy = 'category'
          AND ttc.term_id IN ($cat_placeholders)
          AND p.post_status = 'publish'
        GROUP BY ttm.term_id, ttc.term_id
    ", array_merge(array($taxonomy), $cat_ids));

    $rows_total = (array) $wpdb->get_results($sql_total);

    // Vendidos (mesmo recorte + filtro adicional por termo 'vendido' da taxonomia 'status').
    $rows_vendidos = array();
    $vendido_term = get_term_by('slug', 'vendido', 'status');
    if ($vendido_term && !is_wp_error($vendido_term)) {
        $vendido_id = (int) $vendido_term->term_id;

        $sql_vendidos = $wpdb->prepare("
            SELECT ttm.term_id AS main_term_id,
                   ttc.term_id AS category_term_id,
                   COUNT(DISTINCT tr.object_id) AS qtd
            FROM {$wpdb->term_taxonomy} ttm
            INNER JOIN {$wpdb->term_relationships} tr ON ttm.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_relationships} trc ON trc.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} ttc ON ttc.term_taxonomy_id = trc.term_taxonomy_id
            INNER JOIN {$wpdb->term_relationships} trv ON trv.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} ttv ON ttv.term_taxonomy_id = trv.term_taxonomy_id
            WHERE ttm.taxonomy = %s
              AND ttc.taxonomy = 'category'
              AND ttc.term_id IN ($cat_placeholders)
              AND ttv.taxonomy = 'status'
              AND ttv.term_id = %d
              AND p.post_status = 'publish'
            GROUP BY ttm.term_id, ttc.term_id
        ", array_merge(array($taxonomy), $cat_ids, array($vendido_id)));

        $rows_vendidos = (array) $wpdb->get_results($sql_vendidos);
    }

    $result = array();
    foreach ($rows_total as $row) {
        $tid = (int) $row->main_term_id;
        $cid = (int) $row->category_term_id;
        if (!isset($main_categories[$cid])) continue;
        $slug = $main_categories[$cid];
        $result[$tid][$slug] = array(
            'count' => (int) $row->qtd,
            'vendidos' => 0,
        );
    }
    foreach ($rows_vendidos as $row) {
        $tid = (int) $row->main_term_id;
        $cid = (int) $row->category_term_id;
        if (!isset($main_categories[$cid])) continue;
        $slug = $main_categories[$cid];
        if (!isset($result[$tid][$slug])) {
            $result[$tid][$slug] = array('count' => 0, 'vendidos' => 0);
        }
        $result[$tid][$slug]['vendidos'] = (int) $row->qtd;
    }

    return $result;
}

/**
 * Monta a representação de um termo com counts totais, vendidos, disponíveis
 * e, quando aplicável, breakdown por categoria principal.
 *
 * @param object $term Objeto WP_Term
 * @param array<int,int> $vendidos_map Mapa term_id => qtd_vendidos (da taxonomia)
 * @param array<int,array<string,array<string,int>>> $category_breakdown term_id => [slug => [count, vendidos]]
 * @return array
 */
function bazar_build_term_payload($term, array $vendidos_map, array $category_breakdown = array()) {
    $total = (int) $term->count;
    $tid = (int) $term->term_id;
    $vendidos = isset($vendidos_map[$tid]) ? (int) $vendidos_map[$tid] : 0;
    // Proteção: nunca retornar negativo caso $term->count esteja fora de sincronia.
    $disponiveis = max(0, $total - $vendidos);

    $payload = array(
        'id' => $tid,
        'nome' => $term->name,
        'count' => $total,
        'vendidos' => $vendidos,
        'disponiveis' => $disponiveis,
    );

    // Breakdown por categoria principal (bicicleta/peca/acessorio).
    // Sempre presente com zeros para facilitar consumo no front (shape estável).
    $main_categories = bazar_export_get_main_category_terms();
    if (!empty($main_categories)) {
        $breakdown = isset($category_breakdown[$tid]) ? $category_breakdown[$tid] : array();
        $por_cat = array();
        foreach ($main_categories as $slug) {
            $count_cat = isset($breakdown[$slug]['count']) ? (int) $breakdown[$slug]['count'] : 0;
            $vend_cat  = isset($breakdown[$slug]['vendidos']) ? (int) $breakdown[$slug]['vendidos'] : 0;
            $por_cat[$slug] = array(
                'count' => $count_cat,
                'vendidos' => $vend_cat,
                'disponiveis' => max(0, $count_cat - $vend_cat),
            );
        }
        $payload['por_categoria'] = $por_cat;
    }

    return $payload;
}

/**
 * Busca todas as taxonomias hierárquicas e retorna em formato JSON
 * @param string|null $taxonomy Nome da taxonomia específica (opcional). Se null, busca todas as hierárquicas
 * @return array Array com dados hierárquicos das taxonomias
 */
function bazar_get_taxonomies_hierarchical_json($taxonomy = null) {
    $result = array();
    
    // Se não especificou taxonomia, buscar todas as hierárquicas
    if (empty($taxonomy)) {
        $taxonomies = get_taxonomies(array('hierarchical' => true), 'objects');
    } else {
        $tax_obj = get_taxonomy($taxonomy);
        if ($tax_obj && $tax_obj->hierarchical) {
            $taxonomies = array($taxonomy => $tax_obj);
        } else {
            return array(
                'error' => 'Taxonomia não encontrada ou não é hierárquica: ' . $taxonomy
            );
        }
    }
    
    foreach ($taxonomies as $tax_name => $tax_object) {
        // Buscar todos os termos pais (parent = 0)
        $parent_terms = get_terms(array(
            'taxonomy' => $tax_name,
            'parent' => 0,
            'hide_empty' => false,
        ));
        
        if (is_wp_error($parent_terms) || empty($parent_terms)) {
            $result[$tax_name] = array();
            continue;
        }

        // 1 SQL por taxonomia: todos os counts de "vendidos" de uma vez.
        $vendidos_map = bazar_get_vendidos_count_by_term($tax_name);
        // 2 SQLs por taxonomia: breakdown (count/vendidos) cruzado com cada categoria principal.
        $category_breakdown = bazar_get_category_breakdown_by_term($tax_name);

        $tax_data = array();

        foreach ($parent_terms as $parent_term) {
            // Buscar termos filhos
            $child_terms = get_terms(array(
                'taxonomy' => $tax_name,
                'parent' => $parent_term->term_id,
                'hide_empty' => false,
            ));

            $parent_data = bazar_build_term_payload($parent_term, $vendidos_map, $category_breakdown);

            // Adicionar filhos se existirem
            if (!is_wp_error($child_terms) && !empty($child_terms)) {
                $children = array();
                foreach ($child_terms as $child_term) {
                    $children[] = bazar_build_term_payload($child_term, $vendidos_map, $category_breakdown);
                }
                $parent_data['filhos'] = $children;
            }

            $tax_data[] = $parent_data;
        }
        
        $result[$tax_name] = $tax_data;
    }
    
    return $result;
}

/**
 * Retorna JSON formatado das taxonomias
 * @param string|null $taxonomy Nome da taxonomia específica (opcional)
 * @param bool $pretty Se true, retorna JSON formatado (indentado)
 * @return string JSON string
 */
function bazar_export_taxonomies_json($taxonomy = null, $pretty = true) {
    $data = bazar_get_taxonomies_hierarchical_json($taxonomy);
    
    if ($pretty) {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Executar quando solicitado via admin (retornar JSON puro)
function bazar_export_taxonomies_json_handler() {
    if (isset($_GET['bazar_export_taxonomies_json']) && current_user_can('manage_options')) {
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : null;
        $pretty = isset($_GET['pretty']) && $_GET['pretty'] == '1';
        
        header('Content-Type: application/json; charset=utf-8');
        echo bazar_export_taxonomies_json($taxonomy, $pretty);
        exit;
    }
}
add_action('admin_init', 'bazar_export_taxonomies_json_handler');

// Adicionar página no menu admin para visualizar JSON
function bazar_add_export_taxonomies_menu() {
    add_management_page(
        'Exportar Taxonomias JSON',
        'Exportar Taxonomias',
        'manage_options',
        'bazar-export-taxonomies',
        'bazar_export_taxonomies_page'
    );
}
add_action('admin_menu', 'bazar_add_export_taxonomies_menu');

// Página de administração para exportar taxonomias
function bazar_export_taxonomies_page() {
    $selected_taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : '';
    $pretty = isset($_GET['pretty']) && $_GET['pretty'] == '1';
    
    // Buscar todas as taxonomias hierárquicas disponíveis
    $all_taxonomies = get_taxonomies(array('hierarchical' => true), 'objects');
    
    // Gerar JSON
    $json_data = bazar_export_taxonomies_json($selected_taxonomy ?: null, true);
    $data_array = bazar_get_taxonomies_hierarchical_json($selected_taxonomy ?: null);
    
    ?>
    <div class="wrap">
        <h1>Exportar Taxonomias em JSON</h1>
        
        <div class="card">
            <h2>Filtros</h2>
            <form method="get" action="">
                <input type="hidden" name="page" value="bazar-export-taxonomies">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="taxonomy">Taxonomia</label>
                        </th>
                        <td>
                            <select name="taxonomy" id="taxonomy" style="min-width: 300px;">
                                <option value="">Todas as taxonomias hierárquicas</option>
                                <?php foreach ($all_taxonomies as $tax_name => $tax_object): ?>
                                    <option value="<?php echo esc_attr($tax_name); ?>" <?php selected($selected_taxonomy, $tax_name); ?>>
                                        <?php echo esc_html($tax_object->label . ' (' . $tax_name . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Atualizar Visualização</button>
                </p>
            </form>
        </div>
        
        <div class="card">
            <h2>Estatísticas</h2>
            <?php
            if (!empty($data_array) && !isset($data_array['error'])):
                foreach ($data_array as $tax_name => $tax_data):
                    $tax_obj = get_taxonomy($tax_name);
                    $total_pais = count($tax_data);
                    $total_filhos = 0;
                    foreach ($tax_data as $parent) {
                        if (isset($parent['filhos'])) {
                            $total_filhos += count($parent['filhos']);
                        }
                    }
            ?>
                <p>
                    <strong><?php echo esc_html($tax_obj->label); ?> (<?php echo esc_html($tax_name); ?>):</strong>
                    <?php echo $total_pais; ?> termos pais, <?php echo $total_filhos; ?> termos filhos
                </p>
            <?php
                endforeach;
            else:
                echo '<p>Nenhuma taxonomia hierárquica encontrada.</p>';
            endif;
            ?>
        </div>
        
        <div class="card">
            <h2>JSON Exportado</h2>
            <p>
                <button type="button" class="button" onclick="copyToClipboard()">Copiar JSON</button>
                <a href="<?php echo admin_url('admin.php?page=bazar-export-taxonomies&bazar_export_taxonomies_json=1&taxonomy=' . urlencode($selected_taxonomy) . '&pretty=1'); ?>" class="button" target="_blank">Abrir JSON em nova aba</a>
                <a href="<?php echo admin_url('admin.php?page=bazar-export-taxonomies&bazar_export_taxonomies_json=1&taxonomy=' . urlencode($selected_taxonomy)); ?>" class="button" download="taxonomies.json">Download JSON</a>
            </p>
            <pre id="json-output" style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; max-height: 600px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4;"><?php echo esc_html($json_data); ?></pre>
        </div>
        
        <div class="card">
            <h2>Estrutura de Dados</h2>
            <p>O JSON exportado segue a seguinte estrutura:</p>
            <pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;">
{
  "nome_da_taxonomia": [
    {
      "id": 123,
      "nome": "Nome do Termo Pai",
      "count": 307,
      "vendidos": 163,
      "disponiveis": 144,
      "por_categoria": {
        "bicicleta": { "count": 220, "vendidos": 100, "disponiveis": 120 },
        "peca":      { "count": 87,  "vendidos": 63,  "disponiveis": 24 },
        "acessorio": { "count": 0,   "vendidos": 0,   "disponiveis": 0 }
      },
      "filhos": [
        {
          "id": 124,
          "nome": "Nome do Termo Filho 1",
          "count": 20,
          "vendidos": 5,
          "disponiveis": 15,
          "por_categoria": { "bicicleta": {"count":20,"vendidos":5,"disponiveis":15}, "peca": {"count":0,"vendidos":0,"disponiveis":0}, "acessorio": {"count":0,"vendidos":0,"disponiveis":0} }
        }
      ]
    }
  ]
}
            </pre>
            <ul style="margin-left: 20px;">
                <li><strong>count</strong>: total de posts publicados associados ao termo (bruto, soma todas as categorias e também posts sem categoria).</li>
                <li><strong>vendidos</strong>: posts publicados com status <code>vendido</code>.</li>
                <li><strong>disponiveis</strong>: <code>count - vendidos</code>.</li>
                <li><strong>por_categoria</strong>: breakdown cruzando o termo com cada categoria principal (<code>bicicleta</code>, <code>peca</code>, <code>acessorio</code>). Posts que não pertençam a nenhuma delas ficam fora deste detalhamento, por isso a soma por_categoria pode ser menor que o total. Ausente na própria taxonomia <code>category</code>.</li>
            </ul>
            <p><strong>Nota:</strong> Termos sem filhos não terão a propriedade "filhos".</p>
        </div>
        
        <div class="card">
            <h2>URLs de Acesso Direto</h2>
            <p><strong>JSON Formatado (Pretty Print):</strong></p>
            <code style="display: block; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; margin: 5px 0; word-break: break-all;">
                <?php echo admin_url('admin.php?page=bazar-export-taxonomies&bazar_export_taxonomies_json=1&taxonomy=' . urlencode($selected_taxonomy) . '&pretty=1'); ?>
            </code>
            
            <p><strong>JSON Compacto:</strong></p>
            <code style="display: block; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; margin: 5px 0; word-break: break-all;">
                <?php echo admin_url('admin.php?page=bazar-export-taxonomies&bazar_export_taxonomies_json=1&taxonomy=' . urlencode($selected_taxonomy)); ?>
            </code>
            
            <?php if (!empty($selected_taxonomy)): ?>
                <p><strong>Taxonomia específica:</strong> <?php echo esc_html($selected_taxonomy); ?></p>
            <?php else: ?>
                <p><strong>Todas as taxonomias hierárquicas</strong></p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function copyToClipboard() {
            const jsonOutput = document.getElementById('json-output');
            const text = jsonOutput.textContent;
            
            // Criar elemento temporário para copiar
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                document.execCommand('copy');
                alert('JSON copiado para a área de transferência!');
            } catch (err) {
                alert('Erro ao copiar. Tente selecionar manualmente e copiar (Ctrl+C).');
            }
            
            document.body.removeChild(textarea);
        }
    </script>
    
    <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin: 20px 0;
        }
        .card h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
    <?php
}

