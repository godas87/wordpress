<?php
/**
 * Hooks para remoção automática do destaque
 * Remove destaque quando anúncio é vendido ou excluído
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Remove destaque quando anúncio é marcado como vendido
 * Hook disparado após adicionar termo 'vendido' na taxonomia 'status'
 */
if (!function_exists('bazar_remover_destaque_ao_vender')) {
    add_action('set_object_terms', 'bazar_remover_destaque_ao_vender', 10, 6);
    function bazar_remover_destaque_ao_vender($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        
        // Apenas processar taxonomia 'status' e posts
        if ($taxonomy !== 'status' || get_post_type($object_id) !== 'post') {
            return;
        }
        
        // Verificar se o termo 'vendido' está sendo adicionado
        $vendido_term = get_term_by('slug', 'vendido', 'status');
        if (!$vendido_term) {
            return;
        }
        
        $vendido_tt_id = $vendido_term->term_taxonomy_id;
        
        // Verificar se 'vendido' está nos novos termos
        if (!in_array($vendido_tt_id, $tt_ids)) {
            return;
        }
        
        // Remover destaque se existir
        if (has_term('destaque', 'status', $object_id)) {
            bazar_remover_destaque($object_id, 'vendido');
        }

        // Pagamento registrado mas destaque ainda não liberado (CPF): limpar pendência ao vender
        if (defined('BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO')) {
            delete_post_meta($object_id, BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO);
        }
    }
}

/**
 * Remove destaque quando anúncio é excluído (movido para lixeira)
 * Hook disparado quando post é movido para trash
 */
if (!function_exists('bazar_remover_destaque_ao_excluir')) {
    add_action('wp_trash_post', 'bazar_remover_destaque_ao_excluir', 10, 1);
    function bazar_remover_destaque_ao_excluir($post_id) {
        
        // Apenas processar posts
        if (get_post_type($post_id) !== 'post') {
            return;
        }
        
        // Verificar se anúncio está em destaque
        if (!has_term('destaque', 'status', $post_id)) {
            return;
        }
        
        // Remover destaque
        bazar_remover_destaque($post_id, 'excluido');
    }
}

/**
 * Remove destaque quando anúncio é deletado permanentemente
 * Hook disparado quando post é deletado permanentemente (não apenas movido para trash)
 */
if (!function_exists('bazar_remover_destaque_ao_deletar')) {
    add_action('before_delete_post', 'bazar_remover_destaque_ao_deletar', 10, 1);
    function bazar_remover_destaque_ao_deletar($post_id) {
        
        // Apenas processar posts
        if (get_post_type($post_id) !== 'post') {
            return;
        }
        
        // Verificar se anúncio está em destaque
        if (!has_term('destaque', 'status', $post_id)) {
            return;
        }
        
        // Remover destaque (antes de deletar, para garantir limpeza)
        bazar_remover_destaque($post_id, 'deletado');
    }
}

/**
 * Função centralizada para remover destaque
 * 
 * @param int $post_id ID do post
 * @param string $motivo Motivo da remoção ('vendido', 'excluido', 'deletado')
 */
if (!function_exists('bazar_remover_destaque')) {
    function bazar_remover_destaque($post_id, $motivo = '') {
        
        if (empty($post_id)) {
            return false;
        }
        
        // Verificar se realmente está em destaque
        if (!has_term('destaque', 'status', $post_id)) {
            return false;
        }
        
        // Obter termo destaque
        $destaque_term_id = bazar_get_destaque_term_id();
        if (empty($destaque_term)) {
            $term = get_term_by('slug', 'destaque', 'status');
            $destaque_term_id = $term && !is_wp_error($term) ? (int)$term->term_id : null;
        }
        if (empty($destaque_term_id)) {
            return false;
        }        
        
        // Remover termo 'destaque' da taxonomia 'status'
        $terms = wp_get_object_terms($post_id, 'status', array('fields' => 'ids'));
        if (!is_wp_error($terms) && !empty($terms)) {
            // Remover ID do termo destaque
            $terms = array_diff($terms, array($destaque_term_id));
            // Atualizar termos (sem destaque)
            wp_set_object_terms($post_id, $terms, 'status', false);
        }
        
        // Limpar meta fields relacionados ao destaque
        // Manter histórico, mas marcar como inativo
        update_post_meta($post_id, 'destaque_ativo', '0');
        update_post_meta($post_id, 'destaque_data_remocao', current_time('timestamp'));
        update_post_meta($post_id, 'destaque_motivo_remocao', $motivo);
        
        // Limpar cache
        wp_cache_delete('bazar_destaque_term_id', 'bazar_terms');
        clean_post_cache($post_id);
        
        // Log para debug (opcional)
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     error_log(sprintf(
        //         'Bazar Destaque: Removido destaque do anúncio #%d. Motivo: %s',
        //         $post_id,
        //         $motivo
        //     ));
        // }
        
        return true;
    }
}

/**
 * Hook adicional: Remover destaque quando termo 'vendido' é adicionado diretamente
 * Este hook cobre casos onde o termo é adicionado de outras formas
 */
if (!function_exists('bazar_remover_destaque_ao_adicionar_vendido')) {
    add_action('added_term_relationship', 'bazar_remover_destaque_ao_adicionar_vendido', 10, 3);
    function bazar_remover_destaque_ao_adicionar_vendido($object_id, $tt_id, $taxonomy) {
        
        // Apenas processar taxonomia 'status' e posts
        if ($taxonomy !== 'status' || get_post_type($object_id) !== 'post') {
            return;
        }
        
        // Verificar se o termo adicionado é 'vendido'
        $term = get_term_by('term_taxonomy_id', $tt_id, 'status');
        if (!$term || $term->slug !== 'vendido') {
            return;
        }
        
        // Verificar se anúncio está em destaque
        if (!has_term('destaque', 'status', $object_id)) {
            return;
        }
        
        // Remover destaque
        bazar_remover_destaque($object_id, 'vendido');
    }
}

