<?php
/**
 * Importador de artigos para CPT "blog" a partir de JSON.
 *
 * Suporta:
 * - Manifesto: ficheiro com { "itens": [ { "markdown", "capa", "titulo", "meta_description", "slug", "outlines", … } ] }
 *   (gerado por scripts/blog/gerar-manifesto-blog.py). Caminhos relativos à pasta do tema. O campo "outlines" é só para planeamento e é ignorado na importação.
 * - Formato plano legado: array de objetos com titulo, conteudo, descricao_seo, tipo_artigo, capa,
 *   taxonomias e opcionalmente links_internos_sugeridos.
 * - Formato MANUS: array de envelopes { "tag", "blog": [ { titulo, descricao, taxonomias, … } ] }.
 *   O corpo pode estar omitido no JSON e lido de um ficheiro .md homónimo (mesmo basename que o JSON)
 *   na mesma pasta. Capa opcional por ficheiro homónimo .webp|.png|.jpg|.jpeg|.gif.
 *
 * Uso rápido (WP-CLI eval-file):
 * wp eval-file wp-content/themes/bazar/scripts/blog/import-artigos-json.php
 *
 * Uso em runtime (ex.: arquivo temporário/admin):
 * require_once get_template_directory() . '/scripts/blog/import-artigos-json.php';
 * $resultado = bazar_importar_artigos_blog_json(
 *     get_template_directory() . '/scripts/blog/artigo_pilar_mtb.json',
 *     array('dias_inicio' => 70)
 * );
 * print_r($resultado);
 *
 * Limites: no início da importação em lote chama-se set_time_limit(0) e aumenta memória (admin).
 * Se o erro persistir no WAMP, suba max_execution_time em php.ini ou desative o limite só para testes.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Importação em lote + geração de metadados de imagem pode exceder o max_execution_time do PHP (ex.: 120s no WAMP).
 */
if (!function_exists('bazar_import_blog_aumentar_limites_runtime')) {
    function bazar_import_blog_aumentar_limites_runtime()
    {
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        } else {
            @ini_set('memory_limit', '512M');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
    }
}

if (!function_exists('bazar_importar_artigos_blog_json')) {
    /**
     * Conversor simples de Markdown para HTML.
     * Cobertura: títulos, parágrafos, listas, blockquote e formatação inline básica.
     *
     * @param string $markdown
     * @return string
     */
    function bazar_markdown_to_html($markdown)
    {
        $markdown = (string) $markdown;
        if (trim($markdown) === '') {
            return '';
        }

        $markdown = str_replace(array("\r\n", "\r"), "\n", $markdown);
        $lines = explode("\n", $markdown);

        $html = array();
        $in_ul = false;
        $in_ol = false;
        $in_blockquote = false;

        $close_lists = function () use (&$html, &$in_ul, &$in_ol) {
            if ($in_ul) {
                $html[] = '</ul>';
                $in_ul = false;
            }
            if ($in_ol) {
                $html[] = '</ol>';
                $in_ol = false;
            }
        };

        $apply_inline = function ($text) {
            $text = esc_html((string) $text);
            $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
            $text = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $text);
            $text = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $text);
            $text = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/', '<a href="$2">$1</a>', $text);
            return $text;
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($in_blockquote) {
                    $html[] = '</blockquote>';
                    $in_blockquote = false;
                }
                $close_lists();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
                if ($in_blockquote) {
                    $html[] = '</blockquote>';
                    $in_blockquote = false;
                }
                $close_lists();
                $level = strlen($m[1]);
                $html[] = sprintf('<h%d>%s</h%d>', $level, $apply_inline($m[2]), $level);
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $m)) {
                $close_lists();
                if (!$in_blockquote) {
                    $html[] = '<blockquote>';
                    $in_blockquote = true;
                }
                $html[] = '<p>' . $apply_inline($m[1]) . '</p>';
                continue;
            }

            if (preg_match('/^\-\s+(.+)$/', $trimmed, $m)) {
                if ($in_blockquote) {
                    $html[] = '</blockquote>';
                    $in_blockquote = false;
                }
                if ($in_ol) {
                    $html[] = '</ol>';
                    $in_ol = false;
                }
                if (!$in_ul) {
                    $html[] = '<ul>';
                    $in_ul = true;
                }
                $html[] = '<li>' . $apply_inline($m[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
                if ($in_blockquote) {
                    $html[] = '</blockquote>';
                    $in_blockquote = false;
                }
                if ($in_ul) {
                    $html[] = '</ul>';
                    $in_ul = false;
                }
                if (!$in_ol) {
                    $html[] = '<ol>';
                    $in_ol = true;
                }
                $html[] = '<li>' . $apply_inline($m[1]) . '</li>';
                continue;
            }

            if ($in_blockquote) {
                $html[] = '</blockquote>';
                $in_blockquote = false;
            }
            $close_lists();
            $html[] = '<p>' . $apply_inline($trimmed) . '</p>';
        }

        if ($in_blockquote) {
            $html[] = '</blockquote>';
        }
        $close_lists();

        return implode("\n", $html);
    }

    /**
     * Remove a primeira linha H1 em Markdown (# Título), pois o post já usa post_title.
     * Só atua sobre ATX com um único "#" (não remove "##" nem níveis inferiores).
     *
     * @param string $markdown
     * @return string
     */
    function bazar_blog_strip_primeiro_h1_markdown($markdown)
    {
        $markdown = (string) $markdown;
        if (trim($markdown) === '') {
            return $markdown;
        }

        $markdown = str_replace(array("\r\n", "\r"), "\n", $markdown);
        $lines = explode("\n", $markdown);
        $i = 0;
        $n = count($lines);

        while ($i < $n && trim($lines[$i]) === '') {
            $i++;
        }
        if ($i >= $n) {
            return $markdown;
        }

        $first = $lines[$i];
        if (preg_match('/^#\s+.+$/', $first)) {
            $i++;
            while ($i < $n && trim($lines[$i]) === '') {
                $i++;
            }
            return implode("\n", array_slice($lines, $i));
        }

        return $markdown;
    }

    /**
     * Data de publicação: mesmos dias que antes (índice 0 mais antigo, cada passo +1 dia).
     * Hora do dia: aleatória dentro do horário comercial local (9h–18h); vários no mesmo dia
     * mantêm espaçamento crescente até ao fim da janela.
     *
     * @param int   $index Índice na ordem de importação (0 = primeiro / mais antigo do lote).
     * @param array $args  Opções com dias_inicio.
     * @return array{0:string,1:string} [ post_date, post_date_gmt ] formato MySQL.
     */
    function bazar_blog_datas_publicacao_import($index, array $args)
    {
        static $segundos_no_dia_por_chave = array();

        $dias_inicio = max(0, (int) $args['dias_inicio']);
        $days_ago = max(0, $dias_inicio - $index);
        $chave = (string) $days_ago;

        $tz = wp_timezone();
        $meia_noite = (new \DateTimeImmutable('now', $tz))->setTime(0, 0, 0)->modify('-' . $days_ago . ' days');

        // Horário comercial (hora local): 09:00 … 17:59 (múltiplos no mesmo dia até 18:00).
        $seg_com_ini = 9 * 3600;
        $seg_com_fim = 18 * 3600 - 1;

        if (!isset($segundos_no_dia_por_chave[$chave])) {
            $segundos_no_dia_por_chave[$chave] = random_int($seg_com_ini, $seg_com_fim);
        } else {
            $segundos_no_dia_por_chave[$chave] = min(
                $segundos_no_dia_por_chave[$chave] + random_int(180, 3600),
                $seg_com_fim
            );
        }

        $desloco = $segundos_no_dia_por_chave[$chave];
        $dt = $meia_noite->modify('+' . $desloco . ' seconds');

        $post_date = $dt->format('Y-m-d H:i:s');
        $post_date_gmt = get_gmt_from_date($post_date);

        return array($post_date, $post_date_gmt);
    }

    /**
     * Garante includes necessários para upload de mídia no WP.
     * Seguro para chamadas repetidas.
     */
    function bazar_import_blog_media_includes()
    {
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
    }

    /**
     * Faz upload da capa (path local relativo/absoluto ou URL) e retorna attachment ID.
     *
     * @param string $capa_value Valor do campo "capa" no JSON.
     * @param int    $post_id    Post alvo.
     * @return int|WP_Error
     */
    function bazar_importar_capa_blog($capa_value, $post_id)
    {
        $capa_value = trim((string) $capa_value);
        if ($capa_value === '') {
            return new WP_Error('empty_cover', 'Campo capa vazio.');
        }

        bazar_import_blog_media_includes();

        // Se for URL, usa sideload direto.
        if (preg_match('#^https?://#i', $capa_value)) {
            $attachment_id = media_sideload_image($capa_value, $post_id, null, 'id');
            if (is_wp_error($attachment_id)) {
                return $attachment_id;
            }
            return (int) $attachment_id;
        }

        // Path local: tenta absoluto, depois relativo ao tema.
        $local_path = $capa_value;
        if (!preg_match('#^[A-Za-z]:[\\\\/]#', $local_path) && strpos($local_path, '/') !== 0) {
            $local_path = get_template_directory() . '/' . ltrim($local_path, '/\\');
        }
        $local_path = wp_normalize_path($local_path);

        if (!file_exists($local_path)) {
            return new WP_Error('cover_not_found', 'Arquivo de capa não encontrado: ' . $local_path);
        }

        $filename = basename($local_path);
        $bits = @file_get_contents($local_path);
        if ($bits === false) {
            return new WP_Error('cover_read_error', 'Não foi possível ler a capa: ' . $local_path);
        }

        $upload = wp_upload_bits($filename, null, $bits);
        if (!empty($upload['error'])) {
            return new WP_Error('cover_upload_error', 'Falha no upload da capa: ' . $upload['error']);
        }

        $filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        return (int) $attachment_id;
    }

    /**
     * Converte entrada MANUS ({ tag, blog: [...] }) no formato esperado pela importação
     * e enriquece itens com conteúdo/capa lado a lado ao JSON quando faltarem no objeto.
     *
     * Convenções:
     * - Um único artigo por ficheiro (caso típico MANUS): lê basename.md ao lado do JSON.
     * - Vários artigos no mesmo JSON: só o primeiro sem "conteudo" usa basename.md; os restantes precisam
     *   de "conteudo" no próprio JSON (ou partir em ficheiros JSON separados).
     * - descricao → descricao_seo quando descricao_seo não existir.
     * - tipo_artigo vazio + nome de ficheiro artigo_pilar_|cluster_|orfao_* → tipo inferido (Pilar, Cluster, Orfão).
     *
     * @param string $json_path Caminho absoluto do JSON importado (define pasta e basename dos sidecars).
     * @param array  $items     Resultado decodificado de json_decode(..., true) (lista de elementos).
     * @return array Lista plana normalizada para o loop principal.
     */
    function bazar_normalizar_itens_import_blog_json($json_path, array $items)
    {
        $json_path = wp_normalize_path($json_path);
        $dir = dirname($json_path);
        $base = pathinfo($json_path, PATHINFO_FILENAME);

        $is_manus = false;
        if ($items !== array() && isset($items[0]) && is_array($items[0]) && array_key_exists('blog', $items[0])) {
            $is_manus = true;
        }

        if ($is_manus) {
            $flat = array();
            foreach ($items as $wrapper) {
                if (empty($wrapper['blog']) || !is_array($wrapper['blog'])) {
                    continue;
                }
                foreach ($wrapper['blog'] as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $flat[] = $entry;
                }
            }
            $items = array_values($flat);
        }

        $n_posts = count($items);
        $sidecar_md = $dir . '/' . $base . '.md';
        $md_body = '';
        if (file_exists($sidecar_md)) {
            $read = file_get_contents($sidecar_md);
            if ($read !== false) {
                $md_body = $read;
            }
        }

        foreach ($items as $index => &$item) {
            if (!is_array($item)) {
                $item = array();
                continue;
            }

            if (empty($item['descricao_seo']) && !empty($item['descricao'])) {
                $item['descricao_seo'] = $item['descricao'];
            }

            if ($md_body !== '' && empty($item['conteudo'])) {
                if ($n_posts === 1 || $index === 0) {
                    $item['conteudo'] = $md_body;
                }
            }

            if (empty($item['capa'])) {
                foreach (array('webp', 'png', 'jpg', 'jpeg', 'gif') as $ext) {
                    $candidate = wp_normalize_path($dir . '/' . $base . '.' . $ext);
                    if (file_exists($candidate)) {
                        $item['capa'] = $candidate;
                        break;
                    }
                }
            }

            if (empty($item['tipo_artigo']) && preg_match('/^artigo_(pilar|cluster|orfao)_/i', $base, $m)) {
                $map = array(
                    'pilar' => 'Pilar',
                    'cluster' => 'Cluster',
                    'orfao' => 'Orfão',
                );
                $k = strtolower($m[1]);
                if (isset($map[$k])) {
                    $item['tipo_artigo'] = $map[$k];
                }
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Importa uma lista já normalizada de itens (titulo, conteudo, descricao_seo, tipo_artigo, capa, taxonomias).
     *
     * @param array  $items         Lista de artigos.
     * @param array  $args          Mesmas opções de bazar_importar_artigos_blog_json.
     *                               dias_inicio: quantos dias atrás está o primeiro artigo; cada seguinte = 1 dia mais recente.
     * @param string $origem_label  Caminho ou rótulo para logs (ex.: JSON ou manifesto).
     * @return array Estatísticas de importação.
     */
    function bazar_importar_itens_blog_array(array $items, array $args, $origem_label = '')
    {
        bazar_import_blog_aumentar_limites_runtime();

        $defaults = array(
            'dias_inicio' => 70,
            'publicar' => true,
            'pular_se_titulo_existir' => true,
        );
        $args = wp_parse_args($args, $defaults);

        $result = array(
            'arquivo' => $origem_label,
            'total_json' => count($items),
            'importados' => 0,
            'pulados' => 0,
            'erros' => 0,
            'detalhes' => array(),
        );

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $result['erros']++;
                $result['detalhes'][] = sprintf('Item %d inválido: estrutura incorreta.', $index + 1);
                continue;
            }

            $titulo = isset($item['titulo']) ? sanitize_text_field($item['titulo']) : '';
            $conteudo = isset($item['conteudo']) ? (string) $item['conteudo'] : '';
            $descricao_seo = isset($item['descricao_seo']) ? sanitize_textarea_field($item['descricao_seo']) : '';
            $tipo_artigo = isset($item['tipo_artigo']) ? sanitize_text_field($item['tipo_artigo']) : '';
            $capa = isset($item['capa']) ? (string) $item['capa'] : '';

            if ($titulo === '' || $conteudo === '') {
                $result['erros']++;
                $hint = '';
                if ($titulo !== '' && trim($conteudo) === '') {
                    if (!empty($item['_markdown_rel'])) {
                        $hint = ' Markdown: ' . $item['_markdown_rel'];
                    } elseif ($origem_label !== '') {
                        $hint = sprintf(
                            ' (origem: %s, item %d)',
                            $origem_label,
                            $index + 1
                        );
                    }
                }
                $result['detalhes'][] = sprintf('Item %d inválido: título/conteúdo ausentes.%s', $index + 1, $hint);
                continue;
            }

            if (!empty($args['pular_se_titulo_existir'])) {
                $existing_ids = get_posts(array(
                    'post_type' => 'blog',
                    'title' => $titulo,
                    'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
                    'fields' => 'ids',
                    'posts_per_page' => 1,
                    'suppress_filters' => true,
                ));
                if (!empty($existing_ids)) {
                    $existing_id = (int) $existing_ids[0];
                    $result['pulados']++;
                    $result['detalhes'][] = sprintf('Pulou "%s": título já existente (ID %d).', $titulo, $existing_id);
                    continue;
                }
            }

            list($post_date, $post_date_gmt) = bazar_blog_datas_publicacao_import($index, $args);
            $dias_atras = max(0, (int) $args['dias_inicio'] - $index);

            $postarr = array(
                'post_type' => 'blog',
                'post_status' => !empty($args['publicar']) ? 'publish' : 'draft',
                'post_title' => $titulo,
                'post_content' => wp_kses_post(bazar_markdown_to_html(bazar_blog_strip_primeiro_h1_markdown($conteudo))),
                'post_excerpt' => $descricao_seo,
                'post_date' => $post_date,
                'post_date_gmt' => $post_date_gmt,
            );

            $post_id = wp_insert_post($postarr, true);
            if (is_wp_error($post_id)) {
                $result['erros']++;
                $result['detalhes'][] = sprintf('Erro ao inserir "%s": %s', $titulo, $post_id->get_error_message());
                continue;
            }

            if (!empty($item['_slug_manifesto'])) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_name' => $item['_slug_manifesto'],
                ));
            }

            if (!empty($item['taxonomias']) && is_array($item['taxonomias'])) {
                $grouped = array();
                foreach ($item['taxonomias'] as $tax_item) {
                    $tax = isset($tax_item['tipo']) ? sanitize_key($tax_item['tipo']) : '';
                    if ($tax === '' && !empty($tax_item['taxonomy'])) {
                        $tax = sanitize_key($tax_item['taxonomy']);
                    }
                    if ($tax === '' || !taxonomy_exists($tax)) {
                        continue;
                    }

                    $term_id = 0;
                    if (!empty($tax_item['id']) && is_numeric($tax_item['id'])) {
                        $term = get_term((int) $tax_item['id'], $tax);
                        if ($term && !is_wp_error($term)) {
                            $term_id = (int) $term->term_id;
                        }
                    }

                    if ($term_id === 0 && !empty($tax_item['slug'])) {
                        $term = get_term_by('slug', sanitize_title($tax_item['slug']), $tax);
                        if ($term && !is_wp_error($term)) {
                            $term_id = (int) $term->term_id;
                        }
                    }

                    if ($term_id > 0) {
                        if (!isset($grouped[$tax])) {
                            $grouped[$tax] = array();
                        }
                        $grouped[$tax][] = $term_id;
                    }
                }

                foreach ($grouped as $tax => $term_ids) {
                    wp_set_object_terms($post_id, array_values(array_unique($term_ids)), $tax, false);
                }
            }

            if ($descricao_seo !== '') {
                update_post_meta($post_id, 'descricao_seo', $descricao_seo);
            }
            if ($tipo_artigo !== '') {
                update_post_meta($post_id, 'tipo_artigo', $tipo_artigo);
            }
            if (!empty($item['links_internos_sugeridos']) && is_array($item['links_internos_sugeridos'])) {
                update_post_meta($post_id, 'links_internos_sugeridos', wp_json_encode($item['links_internos_sugeridos'], JSON_UNESCAPED_UNICODE));
            }

            if ($capa !== '') {
                $attachment_id = bazar_importar_capa_blog($capa, $post_id);
                if (is_wp_error($attachment_id)) {
                    $result['detalhes'][] = sprintf(
                        'Aviso em "%s": falha ao importar capa (%s).',
                        $titulo,
                        $attachment_id->get_error_message()
                    );
                } else {
                    set_post_thumbnail($post_id, (int) $attachment_id);
                }
            }

            $result['importados']++;
            $texto_data = function_exists('mysql2date')
                ? mysql2date(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    $post_date
                )
                : $post_date;

            $result['detalhes'][] = sprintf(
                'Importado "%s" (ID %d) — %s (#%d na fila, %d dias atrás).',
                $titulo,
                $post_id,
                $texto_data,
                $index + 1,
                $dias_atras
            );
        }

        return $result;
    }

    /**
     * Importa artigos de JSON para o post type "blog".
     *
     * @param string $json_path Caminho absoluto para o arquivo JSON.
     * @param array  $args      Configuração opcional:
     *                          - dias_inicio (int): primeiro item ~N dias atrás; padrão 70. Os seguintes sobem um dia até “hoje”.
     *                          - publicar (bool): true=publish / false=draft
     *                          - pular_se_titulo_existir (bool): evita duplicar por título (padrão true)
     * @return array Estatísticas de importação.
     */
    function bazar_importar_artigos_blog_json($json_path, $args = array())
    {
        $result = array(
            'arquivo' => $json_path,
            'total_json' => 0,
            'importados' => 0,
            'pulados' => 0,
            'erros' => 0,
            'detalhes' => array(),
        );

        if (!file_exists($json_path)) {
            $result['erros']++;
            $result['detalhes'][] = 'Arquivo JSON não encontrado: ' . $json_path;
            return $result;
        }

        $raw = file_get_contents($json_path);
        if ($raw === false || trim($raw) === '') {
            $result['erros']++;
            $result['detalhes'][] = 'Arquivo JSON vazio ou ilegível.';
            return $result;
        }

        $items = json_decode($raw, true);
        if (!is_array($items)) {
            $result['erros']++;
            $result['detalhes'][] = 'JSON inválido.';
            return $result;
        }

        $items = bazar_normalizar_itens_import_blog_json($json_path, $items);

        $base_hint = pathinfo($json_path, PATHINFO_FILENAME);
        foreach ($items as $idx => &$one) {
            if (is_array($one) && $one !== array() && trim((string) ($one['conteudo'] ?? '')) === '') {
                $one['_markdown_rel'] = sprintf('%s.md', $base_hint);
            }
        }
        unset($one);

        return bazar_importar_itens_blog_array($items, $args, $json_path);
    }

    /**
     * Importa a partir de blog-manifesto.json (ou equivalente): itens com markdown e capa relativos ao tema.
     *
     * @param string $manifest_path Caminho absoluto ao manifesto.
     * @param array  $args            Mesmas opções de bazar_importar_artigos_blog_json.
     * @return array Estatísticas de importação.
     */
    function bazar_importar_blog_manifesto($manifest_path, $args = array())
    {
        $result = array(
            'arquivo' => $manifest_path,
            'total_json' => 0,
            'importados' => 0,
            'pulados' => 0,
            'erros' => 0,
            'detalhes' => array(),
        );

        if (!file_exists($manifest_path)) {
            $result['erros']++;
            $result['detalhes'][] = 'Manifesto não encontrado: ' . $manifest_path;
            return $result;
        }

        $raw = file_get_contents($manifest_path);
        if ($raw === false || trim($raw) === '') {
            $result['erros']++;
            $result['detalhes'][] = 'Manifesto vazio ou ilegível.';
            return $result;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['itens']) || !is_array($data['itens'])) {
            $result['erros']++;
            $result['detalhes'][] = 'Manifesto inválido: falta a chave "itens" (array).';
            return $result;
        }

        $theme_dir = get_template_directory();
        $items = array();

        foreach ($data['itens'] as $idx => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $md_rel = isset($entry['markdown']) ? trim((string) $entry['markdown']) : '';
            if ($md_rel === '') {
                $result['erros']++;
                $result['detalhes'][] = sprintf('Item %d: falta "markdown".', $idx + 1);
                continue;
            }

            $md_abs = wp_normalize_path($theme_dir . '/' . ltrim(str_replace('\\', '/', $md_rel), '/'));
            $conteudo = file_get_contents($md_abs);
            if ($conteudo === false || trim($conteudo) === '') {
                $result['erros']++;
                $result['detalhes'][] = sprintf('Item %d: Markdown ilegível ou vazio (%s).', $idx + 1, $md_rel);
                continue;
            }

            $titulo = isset($entry['titulo']) ? sanitize_text_field($entry['titulo']) : '';
            $meta = '';
            if (!empty($entry['meta_description'])) {
                $meta = sanitize_textarea_field($entry['meta_description']);
            } elseif (!empty($entry['descricao_seo'])) {
                $meta = sanitize_textarea_field($entry['descricao_seo']);
            }

            $tipo_artigo = isset($entry['tipo_artigo']) ? sanitize_text_field($entry['tipo_artigo']) : '';

            $capa = '';
            if (!empty($entry['capa'])) {
                $c = trim((string) $entry['capa']);
                if (preg_match('#^https?://#i', $c)) {
                    $capa = $c;
                } else {
                    $capa = wp_normalize_path($theme_dir . '/' . ltrim(str_replace('\\', '/', $c), '/'));
                }
            }

            $taxonomias = !empty($entry['taxonomias']) && is_array($entry['taxonomias'])
                ? $entry['taxonomias']
                : array();

            $items[] = array(
                'titulo' => $titulo,
                'conteudo' => $conteudo,
                'descricao_seo' => $meta,
                'tipo_artigo' => $tipo_artigo,
                'capa' => $capa,
                'taxonomias' => $taxonomias,
                '_markdown_rel' => $md_rel,
            );

            if (!empty($entry['slug'])) {
                $items[count($items) - 1]['_slug_manifesto'] = sanitize_title($entry['slug']);
            }
        }

        return bazar_importar_itens_blog_array($items, $args, $manifest_path);
    }
}

if (!function_exists('bazar_add_import_blog_json_menu')) {
    function bazar_add_import_blog_json_menu()
    {
        add_management_page(
            'Importar artigos blog (JSON)',
            'Importar Blog JSON',
            'manage_options',
            'bazar-import-blog-json',
            'bazar_render_import_blog_json_page'
        );
    }
    add_action('admin_menu', 'bazar_add_import_blog_json_menu');
}

if (!function_exists('bazar_render_import_blog_json_page')) {
    function bazar_render_import_blog_json_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Acesso negado', 'bazar'));
        }

        $default_relative = 'scripts/blog/blog-manifesto.json';
        $json_relative = isset($_POST['bazar_json_relative'])
            ? sanitize_text_field(wp_unslash($_POST['bazar_json_relative']))
            : $default_relative;
        $usar_manifesto = !isset($_POST['bazar_usar_manifesto']) || $_POST['bazar_usar_manifesto'] === '1';
        $dias_inicio = isset($_POST['bazar_dias_inicio']) ? (int) $_POST['bazar_dias_inicio'] : 70;
        $publicar = !isset($_POST['bazar_publicar']) || $_POST['bazar_publicar'] === '1';
        $pular_titulo = !isset($_POST['bazar_pular_titulo']) || $_POST['bazar_pular_titulo'] === '1';

        $result = null;
        if (isset($_POST['bazar_importar_blog_json'])) {
            check_admin_referer('bazar_import_blog_json_action', 'bazar_import_blog_json_nonce');

            $json_absolute = get_template_directory() . '/' . ltrim($json_relative, '/\\');
            $import_args = array(
                'dias_inicio' => $dias_inicio,
                'publicar' => $publicar,
                'pular_se_titulo_existir' => $pular_titulo,
            );

            if ($usar_manifesto) {
                $result = bazar_importar_blog_manifesto($json_absolute, $import_args);
            } else {
                $result = bazar_importar_artigos_blog_json($json_absolute, $import_args);
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Importar artigos para CPT blog via JSON', 'bazar'); ?></h1>
            <p><?php echo esc_html__('Primeiro item recebe data de hoje - N dias; próximos itens seguem N-1, N-2... No manifesto, a ordem da fila editorial já vem intercalada por vertical.', 'bazar'); ?></p>

            <form method="post">
                <?php wp_nonce_field('bazar_import_blog_json_action', 'bazar_import_blog_json_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Modo</th>
                        <td>
                            <label>
                                <input type="radio" name="bazar_usar_manifesto" value="1" <?php checked($usar_manifesto); ?> />
                                Manifesto (<code>blog-manifesto.json</code> com caminhos para .md e capas)
                            </label>
                            <br />
                            <label>
                                <input type="radio" name="bazar_usar_manifesto" value="0" <?php checked(!$usar_manifesto); ?> />
                                JSON de artigo único (legado / MANUS por pasta)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bazar_json_relative">Ficheiro (relativo ao tema)</label></th>
                        <td>
                            <input name="bazar_json_relative" id="bazar_json_relative" class="regular-text" type="text" value="<?php echo esc_attr($json_relative); ?>" />
                            <p class="description">Manifesto: <code>scripts/blog/blog-manifesto.json</code> · Artigo: <code>scripts/blog/mtb/artigo_pilar_mtb.json</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bazar_dias_inicio">Dias atrás (primeiro item)</label></th>
                        <td>
                            <input name="bazar_dias_inicio" id="bazar_dias_inicio" type="number" min="0" value="<?php echo esc_attr((string) $dias_inicio); ?>" />
                            <p class="description">O <strong>primeiro</strong> artigo fica com esta data (dias atrás); cada um seguinte avança <strong>um dia</strong> na direção de hoje. A <strong>hora</strong> é <strong>aleatória no horário comercial</strong> (9h–18h, fuso do site). Vários no mesmo dia ficam espaçados até às 18h.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Status dos posts</th>
                        <td>
                            <label>
                                <input type="checkbox" name="bazar_publicar" value="1" <?php checked($publicar); ?> />
                                Publicar imediatamente (desmarque para rascunho)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Duplicidade por título</th>
                        <td>
                            <label>
                                <input type="checkbox" name="bazar_pular_titulo" value="1" <?php checked($pular_titulo); ?> />
                                Pular item quando já existir post com mesmo título
                            </label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="bazar_importar_blog_json" value="1" class="button button-primary">
                        Executar importação
                    </button>
                </p>
            </form>

            <?php if (is_array($result)) : ?>
                <hr />
                <h2>Resultado</h2>
                <p>
                    <strong>Total no JSON:</strong> <?php echo esc_html((string) $result['total_json']); ?> |
                    <strong>Importados:</strong> <?php echo esc_html((string) $result['importados']); ?> |
                    <strong>Pulados:</strong> <?php echo esc_html((string) $result['pulados']); ?> |
                    <strong>Erros:</strong> <?php echo esc_html((string) $result['erros']); ?>
                </p>

                <?php if (!empty($result['detalhes'])) : ?>
                    <textarea readonly style="width:100%;height:320px;font-family:monospace;"><?php echo esc_textarea(implode("\n", $result['detalhes'])); ?></textarea>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
