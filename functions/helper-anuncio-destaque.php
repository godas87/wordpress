<?php
/**
 * Funções helper para gerenciamento de anúncios em destaque
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Verifica se a promoção de newsletter está ativa
 * 
 * @return bool True se a promoção está ativa
 */
if (!function_exists('bazar_promocao_newsletter_ativa')) {
    function bazar_promocao_newsletter_ativa()
    {
        return get_option('bazar_promocao_newsletter_ativa', '0') === '1';
    }
}

/**
 * AJAX: Valida se o usuário pode usar o desconto de newsletter
 * 
 * @package XXXXXX
 */
add_action('wp_ajax_bazar_validate_newsletter_discount', 'bazar_validate_newsletter_discount');
function bazar_validate_newsletter_discount()
{
    // Limpar output anterior
    if (ob_get_level()) {
        ob_clean();
    }

    // Verificar se usuário está logado
    $user_id = get_current_user_id();
    if (empty($user_id)) {
        wp_send_json_success(array(
            'can_use_discount' => false,
            'message' => 'Usuário não está logado'
        ));
        return;
    }

    // Validar se pode usar desconto
    $can_use = false;
    if (function_exists('bazar_user_can_use_newsletter_discount')) {
        $can_use = bazar_user_can_use_newsletter_discount($user_id);
    }

    wp_send_json_success(array(
        'can_use_discount' => $can_use,
        'message' => $can_use ? 'Pode usar desconto' : 'Não pode usar desconto'
    ));
}

/**
 * Obtém o preço do destaque (normal ou com desconto)
 * Usa cache estático da config para evitar múltiplos get_option na mesma requisição.
 *
 * @param bool $com_desconto Se true, retorna preço com desconto de newsletter
 * @return float Preço em reais
 */
if (!function_exists('bazar_destaque_get_preco')) {
    function bazar_destaque_get_preco($com_desconto = false)
    {
        $config = bazar_destaque_get_promo_config();
        return $com_desconto ? $config['preco_desconto_newsletter'] : $config['preco_normal'];
    }
}

/**
 * Retorna a configuração da promoção (preços, textos e controle de desconto) com cache estático.
 * Use esta função em templates e JS (via wp_localize_script) para exibir valor, % de desconto e textos globais.
 *
 * @return array {
 *   preco_normal,
 *   preco_desconto_newsletter,
 *   desconto_percent,
 *   promocao_ativa,
 *   titulo,
 *   subtitulo,
 *   modal_promo_btn_label,
 *   descricao,
 *   link,
 *   aplica_desconto_checkout,
 *   terms_url
 * }
 */
if (!function_exists('bazar_destaque_get_promo_config')) {
    function bazar_destaque_get_promo_config()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = array(
            'preco_normal' => floatval(get_option('bazar_destaque_preco_normal', 50.00)),
            'preco_desconto_newsletter' => floatval(get_option('bazar_destaque_preco_desconto_newsletter', 45.00)),
            'desconto_percent' => max(0, min(100, (int) get_option('bazar_destaque_desconto_newsletter_percent', 10))),
            // Promo é considerada "ativa" quando há pelo menos um título definido
            // (independente de aplicar ou não desconto direto no checkout).
            'promocao_ativa' => trim((string) get_option('bazar_promo_titulo', '')) !== '',
            'titulo' => get_option('bazar_promo_titulo', ''),
            'subtitulo' => get_option('bazar_promo_subtitulo', ''),
            'modal_promo_btn_label' => get_option('bazar_promo_modal_btn_label', ''),
            'descricao' => get_option('bazar_promo_descricao', ''),
            'link' => get_option('bazar_promo_link', ''),
            // Controle separado para aplicar ou não o preço com desconto no checkout
            'aplica_desconto_checkout' => get_option('bazar_promo_aplica_desconto_checkout', '1') === '1',
            // URL dos termos da promoção (fallback para termos de Instagram se não definido)
            'terms_url' => get_option('bazar_promo_terms_url', home_url('/termos-promocao-instagram/')),
        );
        return $cache;
    }
}



/**
 * Verifica se um usuário já usou o desconto de newsletter
 * 
 * Esta função APENAS verifica se o desconto foi usado.
 * A inserção na newsletter é feita em bazar_stripe_ativar_destaque() quando o pagamento é processado.
 * 
 * @param int $user_id ID do usuário
 * @return bool True se já usou o desconto
 */
if (!function_exists('bazar_have_desconto_newsletter')) {
    function bazar_have_desconto_newsletter($user_id)
    {

        if (empty($user_id)) {
            return false;
        }

        // Verificar se o meta field indica que o desconto foi usado
        $meta_value = get_user_meta($user_id, 'bazar_desconto_newsletter', true);

        // Retornar true se o meta field existe e tem valor válido
        return (
            $meta_value === '1'
            || $meta_value === 1
            || $meta_value === true
        );
    }
}

/**
 * Verifica se um usuário pode usar o desconto de newsletter
 * Verifica se: promoção está ativa, usuário ainda não usou o desconto
 * 
 * @param int $user_id ID do usuário
 * @return bool True se PODE usar desconto (promoção ativa E ainda não usou)
 */
if (!function_exists('bazar_user_can_use_newsletter_discount')) {
    function bazar_user_can_use_newsletter_discount($user_id)
    {
        // Se promoção não está ativa, não pode usar desconto
        if (!bazar_promocao_newsletter_ativa()) {
            return false;
        }

        // Se já usou o desconto, não pode usar novamente
        return !bazar_have_desconto_newsletter($user_id);
    }
}

/**
 * Marca que um usuário usou o desconto de newsletter
 * 
 * @param int $user_id ID do usuário
 * @return bool True se foi salvo com sucesso
 */
if (!function_exists('bazar_set_desconto_newsletter_on_user_data')) {
    function bazar_set_desconto_newsletter_on_user_data($user_id)
    {
        if (empty($user_id)) {
            return false;
        }

        return update_user_meta($user_id, 'bazar_desconto_newsletter', '1');
    }
}

/**
 * Verifica se um email é assinante da newsletter
 * 
 * @param string $email Email do usuário
 * @return bool True se é assinante
 */
if (!function_exists('check_user_in_newsletter')) {
    function check_user_in_newsletter($email)
    {

        if (empty($email) || !is_email($email)) {
            return false;
        }

        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'newsletter' 
            AND post_title = %s 
            AND post_status = 'publish'
            LIMIT 1",
            $email
        ));

        return !empty($exists);
    }
}

/**
 * Inclui o e-mail do usuário na base da newsletter (post_type `newsletter`), se ainda não existir.
 * Não grava `bazar_desconto_newsletter` — esse meta é só do fluxo “desconto por assinar newsletter” (outro preço).
 *
 * @param int $user_id ID do usuário WordPress.
 * @return bool True se já era assinante ou se o post da newsletter foi criado com sucesso.
 */
if (!function_exists('bazar_newsletter_add_user_if_missing')) {
    function bazar_newsletter_add_user_if_missing($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id < 1) {
            return false;
        }
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email) || !is_email($user->user_email)) {
            return false;
        }
        $email = $user->user_email;
        if (function_exists('check_user_in_newsletter') && check_user_in_newsletter($email)) {
            return true;
        }
        $post_id = wp_insert_post(array(
            'post_title' => $email,
            'post_type' => 'newsletter',
            'post_status' => 'publish',
            'post_author' => 1,
        ), true);
        return !is_wp_error($post_id) && $post_id > 0;
    }
}

/**
 * Verifica se um usuário pode impulsionar um anúncio
 * 
 * @param int $post_id ID do anúncio
 * @param int $user_id ID do usuário (opcional, usa usuário atual se não informado)
 * @return array Array com 'can' (bool) e 'message' (string) - mensagem vazia se pode
 */
if (!function_exists('bazar_can_boost_anuncio')) {
    function bazar_can_boost_anuncio($post_id, $user_id = null)
    {

        // Inicializar resposta
        $result = array(
            'can' => false,
            'message' => ''
        );

        // Verificar se post_id foi informado
        if (empty($post_id)) {
            $result['message'] = 'ID do anúncio não informado';
            return $result;
        }

        // Obter usuário atual se não foi informado
        if (empty($user_id)) {
            $user_id = get_current_user_id();
        }

        // Verificar se usuário está logado
        if (empty($user_id)) {
            $result['message'] = 'Usuário não está logado';
            return $result;
        }

        // Verificar se anúncio existe
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            $result['message'] = 'Anúncio não encontrado';
            return $result;
        }

        // Verificar se usuário é o autor do anúncio
        if (intval($post->post_author) !== intval($user_id)) {
            $result['message'] = 'Você não é o autor deste anúncio';
            return $result;
        }

        // Verificar se anúncio está publicado
        if ($post->post_status !== 'publish') {
            $result['message'] = 'Anúncio precisa estar publicado para ser impulsionado';
            return $result;
        }

        // Verificar se anúncio está vendido
        if (has_term('vendido', 'status', $post_id)) {
            $result['message'] = 'Anúncios vendidos não podem ser impulsionados';
            return $result;
        }

        // Verificar se anúncio já está em destaque
        if (has_term('destaque', 'status', $post_id)) {
            $result['message'] = 'Este anúncio já está em destaque';
            return $result;
        }

        // Pagamento já registrado (destaque liberado após verificação do CPF em Minha conta)
        if (get_post_meta($post_id, 'destaque_payment_status', true) === 'paid') {
            $result['message'] = 'Já existe pagamento de Destaque para este anúncio. Conclua a verificação do CPF em Minha conta para ativar o destaque.';
            return $result;
        }

        // Tudo OK!
        $result['can'] = true;
        return $result;
    }
}

/**
 * Pode exibir/iniciar checkout de impulsionamento na tela de sucesso pós-cadastro
 * (anúncio ainda pending). Modal impulsionar e AJAX bazar_get_product_data_impulsionar aceitam pending quando esta função retorna true.
 *
 * @param int      $post_id
 * @param int|null $user_id
 * @return bool
 */
if (!function_exists('bazar_can_offer_boost_checkout_on_success')) {
    function bazar_can_offer_boost_checkout_on_success($post_id, $user_id = null)
    {
        if (empty($post_id)) {
            return false;
        }
        if (empty($user_id)) {
            $user_id = get_current_user_id();
        }
        if (empty($user_id)) {
            return false;
        }
        $post = get_post((int) $post_id);
        if (!$post || $post->post_type !== 'post') {
            return false;
        }
        if ((int) $post->post_author !== (int) $user_id) {
            return false;
        }
        if (has_term('vendido', 'status', $post_id)) {
            return false;
        }
        if (has_term('destaque', 'status', $post_id)) {
            return false;
        }
        if (get_post_meta($post_id, 'destaque_payment_status', true) === 'paid') {
            return false;
        }
        return true;
    }
}

/**
 * Obtém o preço do impulsionamento para um anúncio específico
 * 
 * @param int $post_id ID do anúncio
 * @param int $user_id ID do usuário (opcional, usa usuário atual se não informado)
 * @return float Preço em reais
 */
if (!function_exists('bazar_get_boost_price')) {
    function bazar_get_boost_price($post_id, $user_id = null)
    {
        if (empty($user_id)) {
            $user_id = get_current_user_id();
        }

        $can_use_discount = bazar_user_can_use_newsletter_discount($user_id);
        return bazar_destaque_get_preco($can_use_discount);
    }
}

/**
 * Inicializa opções padrão do sistema de destaque
 * Executada automaticamente na primeira vez que o sistema é usado
 */
if (!function_exists('bazar_destaque_init_options')) {
    function bazar_destaque_init_options()
    {
        // Preço normal
        if (get_option('bazar_destaque_preco_normal') === false) {
            add_option('bazar_destaque_preco_normal', 50.00);
        }

        // Preço com desconto de newsletter
        if (get_option('bazar_destaque_preco_desconto_newsletter') === false) {
            add_option('bazar_destaque_preco_desconto_newsletter', 25.00);
        }

        // Porcentagem de desconto exibida na promoção (default 10%)
        if (get_option('bazar_destaque_desconto_newsletter_percent') === false) {
            add_option('bazar_destaque_desconto_newsletter_percent', 10);
        }

        // Promoção newsletter (desativada por padrão)
        if (get_option('bazar_promocao_newsletter_ativa') === false) {
            add_option('bazar_promocao_newsletter_ativa', '0');
        }
    }

    // Executar na inicialização (apenas se não existir)
    add_action('init', 'bazar_destaque_init_options', 1);
}

/**
 * Aplica ordenação por destaque na query
 * Posts com destaque aparecem primeiro
 * 
 * @param array $args Args da query (modificado por referência)
 * @return array Args modificados
 */
if (!function_exists('bazar_apply_destaque_ordering')) {
    function bazar_apply_destaque_ordering(&$args)
    {
        // Verificar se já existe uma ordenação customizada
        // Se houver orderby definido, não aplicar ordenação por destaque
        // (a ordenação por destaque será aplicada via posts_clauses)

        // Usar variável estática para garantir que o filtro seja aplicado apenas uma vez
        static $destaque_filter_added = false;
        if ($destaque_filter_added) {
            return $args; // Já foi adicionado, retornar args sem modificação
        }

        // Criar função única para o filtro
        $destaque_filter = function ($clauses, $wp_query) {
            global $wpdb;

            // JOIN com term_relationships e term_taxonomy para verificar se o post tem o termo 'destaque'
            $clauses['join'] .= " LEFT JOIN (
                SELECT DISTINCT tr.object_id
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                INNER JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
                WHERE tt.taxonomy = 'status' AND t.slug = 'destaque'
            ) AS destaque_terms ON ({$wpdb->posts}.ID = destaque_terms.object_id)";

            // Adicionar campo de ordenação: 1 para posts com destaque, 2 para os demais
            $clauses['fields'] .= ", CASE 
                WHEN destaque_terms.object_id IS NOT NULL THEN 1
                ELSE 2
            END AS destaque_order";

            // Adicionar ordenação por destaque (antes da ordenação padrão)
            if (empty($clauses['orderby'])) {
                $clauses['orderby'] = "destaque_order ASC";
            } else {
                // Verificar se já existe orderby e adicionar destaque_order no início
                $clauses['orderby'] = "destaque_order ASC, " . $clauses['orderby'];
            }

            return $clauses;
        };

        // Adicionar filtro (será aplicado na próxima WP_Query)
        $destaque_filter_added = true;
        add_filter('posts_clauses', $destaque_filter, 10, 2);

        return $args;
    }
}
?>