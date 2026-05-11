<?php
/**
 * Hub central de dados de produto (anúncio).
 * Separação por contexto — alinhado à ideia de “camadas” do CRUD: blocos reutilizáveis, montagem explícita.
 *
 * @package XXXXXX
 */
if (!defined('ABSPATH')) {
    exit;
}

final class __Bazar_Product_Data_Repository
{
    public const CONTEXT_FULL = 'full';

    public const CONTEXT_CARD = 'card';

    /** Meta tags (Open Graph / descrição) — taxonomias e campos mínimos */
    public const CONTEXT_SEO = 'seo';

    /** Item em coleção (ItemList) — taxonomias/ACF como no full, galeria reduzida (sem varrer anexos) */
    public const CONTEXT_SCHEMA_COLLECTION = 'schema_collection';

    /** RelatedTaxQuery — cidade, categoria, marca-modelo, modalidade, valor */
    public const CONTEXT_RELATED = 'related';

    /** Taxonomias carregadas no pacote completo (espelha o helper legado). */
    private const TAXONOMIES_FULL = array(
        'category',
        'cidade',
        'marca-modelo',
        'modalidade',
        'componente',
        'conservacao',
        'material',
        'cor',
        'genero',
        'idade',
        'negociacao',
        'medidas',
        'acessorio',
        'especificacoes',
        'status',
    );

    private const TAXONOMIES_CARD = array(
        'conservacao',
        'material',
        'cidade',
        'status',
    );

    private const TAXONOMIES_SEO = array(
        'status',
        'cor',
        'cidade',
    );

    private const TAXONOMIES_RELATED = array(
        'cidade',
        'category',
        'marca-modelo',
        'modalidade',
    );

    private const ACF_FULL = array(
        'valor',
        'peso',
        'ano',
        'nota_fiscal',
        'exibir_contato',
        'componentes',
    );

    private const ACF_CARD = array('valor', 'peso', 'ano');

    private const ACF_SEO = array('valor', 'peso');

    private const ACF_RELATED = array('valor');

    /** @var array<string, array> */
    private static $cache = array();

    /**
     * @param int|string|null $post_id
     * @param string $context Uma das constantes CONTEXT_*
     * @param \WP_Post|null $post
     * @return array|null Mesmo formato legado por contexto (chaves esperadas pelos consumidores).
     */
    public static function get($post_id, $context, $post = null)
    {
        if (!$post_id || $post_id === 0 || !is_numeric($post_id)) {
            return null;
        }
        $post_id = (int) $post_id;
        $key = $post_id . '|' . $context;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $post = self::resolve_post($post_id, $post);
        if (!$post || !isset($post->ID)) {
            return null;
        }

        switch ($context) {
            case self::CONTEXT_FULL:
                $data = self::assemble_full($post);
                break;
            case self::CONTEXT_CARD:
                $data = self::assemble_card($post);
                break;
            case self::CONTEXT_SEO:
                $data = self::assemble_seo($post);
                break;
            case self::CONTEXT_SCHEMA_COLLECTION:
                $data = self::assemble_schema_collection($post);
                break;
            case self::CONTEXT_RELATED:
                $data = self::assemble_related($post);
                break;
            default:
                $data = self::assemble_full($post);
        }

        if ($data !== null) {
            self::$cache[$key] = $data;
        }

        return $data;
    }

    /**
     * Limpa entradas do repositório para um post (ex.: após marcar vendido).
     *
     * @param int $post_id
     */
    public static function flush_post_cache($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }
        $prefix = $post_id . '|';
        foreach (array_keys(self::$cache) as $k) {
            if (strpos((string) $k, $prefix) === 0) {
                unset(self::$cache[$k]);
            }
        }
    }

    /**
     * @param int $post_id
     * @param \WP_Post|null $post
     * @return \WP_Post|null
     */
    private static function resolve_post($post_id, $post)
    {
        if ($post && is_object($post) && isset($post->ID)) {
            return $post;
        }
        $p = get_post($post_id);
        return ($p && isset($p->ID)) ? $p : null;
    }

    /**
     * @param mixed $terms
     * @return array
     */
    private static function normalize_terms($terms)
    {
        if (is_wp_error($terms) || !$terms) {
            return array();
        }
        return $terms;
    }

    /**
     * @param int $post_id
     * @param string[] $taxonomy_keys
     * @return array<string, array>
     */
    private static function load_taxonomies($post_id, array $taxonomy_keys)
    {
        $out = array();
        foreach ($taxonomy_keys as $tax) {
            $out[$tax] = self::normalize_terms(get_the_terms($post_id, $tax));
        }
        return $out;
    }

    /**
     * @param int $post_id
     * @param string[] $field_names
     * @return array<string, mixed>
     */
    private static function load_acf_fields($post_id, array $field_names)
    {
        $fields = array();
        foreach ($field_names as $name) {
            $fields[$name] = get_field($name, $post_id);
        }
        return $fields;
    }

    /**
     * @param int $post_id
     * @return array<string, mixed>
     */
    private static function build_author_full($post_id)
    {
        $author_id = (int) get_post_field('post_author', $post_id);
        $fone = get_the_author_meta('fone', $author_id);
        $whatsapp_ativo_meta = get_the_author_meta('whatsapp_ativo', $author_id);
        $whatsapp_ativo = ($whatsapp_ativo_meta === 'true' || $whatsapp_ativo_meta === true || $whatsapp_ativo_meta === '1' || $whatsapp_ativo_meta === 1);
        $whatsapp_value = $whatsapp_ativo ? $fone : '';
        $perfil_selos_completos = false;
        if ($author_id && function_exists('bazar_perfil_selos_completos')) {
            $perfil_selos_completos = bazar_perfil_selos_completos($author_id);
        }
        $email_confirmado = (
            $author_id
            && function_exists('bazar_usuario_email_confirmado_meta')
            && bazar_usuario_email_confirmado_meta($author_id)
        );
        $userdata = get_userdata($author_id);

        return array(
            'id' => $author_id,
            'name' => get_the_author_meta('first_name', $author_id),
            'sobrenome' => get_the_author_meta('last_name', $author_id),
            'nicename' => get_the_author_meta('user_nicename', $author_id),
            'email' => get_the_author_meta('email', $author_id),
            'fone' => $fone,
            'whatsapp' => $whatsapp_value,
            'whatsapp_ativo' => $whatsapp_ativo,
            'bairro' => get_the_author_meta('bairro', $author_id),
            'posts_count' => (int) count_user_posts($author_id, 'post'),
            'registered' => $userdata ? date('d/m/Y', strtotime($userdata->user_registered)) : '',
            /** Check azul: CPF + endereço + e-mail confirmados */
            'perfil_verificado' => $perfil_selos_completos,
            'email_confirmado' => (bool) $email_confirmado,
            /** Selo adicional quando o e-mail ainda não foi confirmado */
            'mostrar_selo_email_pendente' => $author_id > 0 && !$email_confirmado,
        );
    }

    /**
     * Autor mínimo para schema (vendedor no Offer).
     *
     * @param int $post_id
     * @return array{id: int, name: string}
     */
    private static function build_author_schema($post_id)
    {
        $author_id = (int) get_post_field('post_author', $post_id);
        return array(
            'id' => $author_id,
            'name' => get_the_author_meta('first_name', $author_id),
        );
    }

    /**
     * @param int $post_id
     * @param bool $collection_only Destaque + cópia única na lista `gallery` (evita get_attached_media em loops).
     * @return array{featured: string, featured_medium: string, gallery: string[]}
     */
    private static function build_images($post_id, $collection_only)
    {
        $featured = get_the_post_thumbnail_url($post_id, 'l');
        $featured_medium = get_the_post_thumbnail_url($post_id, 'm');
        $gallery = array();

        if ($collection_only) {
            if ($featured !== false && $featured !== '') {
                $gallery[] = $featured;
            }
            return array(
                'featured' => $featured ? $featured : '',
                'featured_medium' => $featured_medium ? $featured_medium : '',
                'gallery' => $gallery,
            );
        }

        $imgs = get_attached_media('', $post_id);
        foreach ($imgs as $img) {
            if (strpos($img->post_mime_type, 'image/') === 0) {
                $img_src = wp_get_attachment_image_src($img->ID, 'l');
                if ($img_src) {
                    $gallery[] = $img_src[0];
                }
            }
        }

        return array(
            'featured' => $featured ? $featured : '',
            'featured_medium' => $featured_medium ? $featured_medium : '',
            'gallery' => $gallery,
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, array> $taxonomies
     * @param int $post_id
     * @return array{valor: string, peso: string, location: string, valor_schema: string|null}
     */
    private static function build_formatted_block(array $fields, array $taxonomies, $post_id)
    {
        $cidade = isset($taxonomies['cidade']) ? $taxonomies['cidade'] : array();
        return array(
            'valor' => !empty($fields['valor']) ? number_format((float) $fields['valor'], 2, ',', '.') : '',
            'peso' => !empty($fields['peso']) ? $fields['peso'] . ' Kg' : 'Não informado',
            'location' => function_exists('format_city_state') ? format_city_state($post_id, $cidade) : '',
            'valor_schema' => (isset($fields['valor']) && is_numeric($fields['valor']))
                ? number_format((float) $fields['valor'], 2, '.', '')
                : null,
        );
    }

    /**
     * @param \WP_Post $post
     * @return array
     */
    private static function assemble_full($post)
    {
        $post_id = (int) $post->ID;
        $taxonomies = self::load_taxonomies($post_id, self::TAXONOMIES_FULL);
        $fields = self::load_acf_fields($post_id, self::ACF_FULL);

        $data = array(
            'id' => $post_id,
            'title' => $post->post_title,
            'permalink' => get_the_permalink($post_id),
            'site_name' => 'Bazar Bikes',
            'post' => $post,
            'taxonomies' => $taxonomies,
            'fields' => $fields,
            'author' => self::build_author_full($post_id),
            'meta' => array(
                'rating' => get_post_meta($post_id, 'simple_rating', true),
            ),
            'images' => self::build_images($post_id, false),
            'formatted' => self::build_formatted_block(
                $fields,
                $taxonomies,
                $post_id
            ),
            'status_data' => function_exists('bazar_get_anuncio_status')
                ? bazar_get_anuncio_status($post_id, $post->post_status)
                : array(),
        );

        return $data;
    }

    /**
     * @param \WP_Post $post
     * @return array
     */
    private static function assemble_card($post)
    {
        $post_id = (int) $post->ID;
        $taxonomies = self::load_taxonomies($post_id, self::TAXONOMIES_CARD);
        $fields = self::load_acf_fields($post_id, self::ACF_CARD);
        $author_id = (int) get_post_field('post_author', $post_id);
        $fone_card = get_the_author_meta('fone', $author_id);
        $wa_ativo = (get_the_author_meta('whatsapp_ativo', $author_id) === 'true' || get_the_author_meta('whatsapp_ativo', $author_id) === true);

        return array(
            'id' => $post_id,
            'title' => $post->post_title,
            'permalink' => get_the_permalink($post_id),
            'type' => $post->post_type,
            'post_status' => $post->post_status,
            'data' => $post->post_date,
            'taxonomies' => $taxonomies,
            'fields' => $fields,
            'images' => array(
                'featured' => get_the_post_thumbnail_url($post_id, 'l'),
                'featured_medium' => get_the_post_thumbnail_url($post_id, 'm'),
            ),
            'author' => array(
                'id' => $author_id,
                'name' => get_the_author_meta('first_name', $author_id),
                'sobrenome' => get_the_author_meta('last_name', $author_id),
                'nicename' => get_the_author_meta('user_nicename', $author_id),
                'email' => get_the_author_meta('email', $author_id),
                'fone' => $fone_card,
                'whatsapp' => $wa_ativo ? $fone_card : '',
                'whatsapp_ativo' => $wa_ativo,
                'bairro' => get_the_author_meta('bairro', $author_id),
            ),
            'formatted' => array(
                'location' => function_exists('format_city_state') ? format_city_state($post_id, $taxonomies['cidade']) : '',
                'valor' => !empty($fields['valor']) ? number_format((float) $fields['valor'], 2, ',', '.') : '',
            ),
            'status_data' => function_exists('bazar_get_anuncio_status')
                ? bazar_get_anuncio_status($post_id, $post->post_status)
                : array(),
        );
    }

    /**
     * @param \WP_Post $post
     * @return array
     */
    private static function assemble_seo($post)
    {
        $post_id = (int) $post->ID;
        $taxonomies = self::load_taxonomies($post_id, self::TAXONOMIES_SEO);
        $fields = self::load_acf_fields($post_id, self::ACF_SEO);
        $images = self::build_images($post_id, true);

        return array(
            'id' => $post_id,
            'title' => $post->post_title,
            'permalink' => get_the_permalink($post_id),
            'site_name' => 'Bazar Bikes',
            'post' => $post,
            'taxonomies' => $taxonomies,
            'fields' => $fields,
            'images' => array(
                'featured' => $images['featured'],
                'featured_medium' => $images['featured_medium'],
            ),
            'formatted' => array(
                'location' => function_exists('format_city_state') ? format_city_state($post_id, $taxonomies['cidade']) : '',
                'valor' => !empty($fields['valor']) ? number_format((float) $fields['valor'], 2, ',', '.') : '',
                'valor_schema' => (isset($fields['valor']) && is_numeric($fields['valor']))
                    ? number_format((float) $fields['valor'], 2, '.', '')
                    : null,
                'peso' => !empty($fields['peso']) ? $fields['peso'] . ' Kg' : 'Não informado',
            ),
        );
    }

    /**
     * Schema em listagens: evita get_attached_media por item; image JSON-LD usa destaque (mínimo).
     *
     * @param \WP_Post $post
     * @return array
     */
    private static function assemble_schema_collection($post)
    {
        $post_id = (int) $post->ID;
        $taxonomies = self::load_taxonomies($post_id, self::TAXONOMIES_FULL);
        $fields = self::load_acf_fields($post_id, self::ACF_FULL);

        return array(
            'id' => $post_id,
            'title' => $post->post_title,
            'permalink' => get_the_permalink($post_id),
            'site_name' => 'Bazar Bikes',
            'post' => $post,
            'taxonomies' => $taxonomies,
            'fields' => $fields,
            'author' => self::build_author_schema($post_id),
            'meta' => array(
                'rating' => get_post_meta($post_id, 'simple_rating', true),
            ),
            'images' => self::build_images($post_id, true),
            'formatted' => self::build_formatted_block($fields, $taxonomies, $post_id),
        );
    }

    /**
     * @param \WP_Post $post
     * @return array
     */
    private static function assemble_related($post)
    {
        $post_id = (int) $post->ID;
        $taxonomies = self::load_taxonomies($post_id, self::TAXONOMIES_RELATED);
        $fields = self::load_acf_fields($post_id, self::ACF_RELATED);

        return array(
            'id' => $post_id,
            'title' => $post->post_title,
            'permalink' => get_the_permalink($post_id),
            'taxonomies' => $taxonomies,
            'fields' => $fields,
        );
    }
}
