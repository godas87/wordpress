<?php
/**
 * Meta Fields para Taxonomias (Termos)
 * 
 * Exibe e permite editar meta fields relacionados a ordenação e outros dados
 * no painel de edição de termos do WordPress
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adicionar meta box para exibir e editar meta fields do termo
 */
add_action('admin_init', 'bazar_add_taxonomy_meta_fields_box');
function bazar_add_taxonomy_meta_fields_box() {
    // Obter todas as taxonomias públicas
    $taxonomies = get_taxonomies(array('public' => true), 'names');
    
    // Adicionar meta box para cada taxonomia
    foreach ($taxonomies as $taxonomy) {
        add_action($taxonomy . '_edit_form_fields', 'bazar_display_taxonomy_meta_fields', 10, 2);
        add_action($taxonomy . '_add_form_fields', 'bazar_display_taxonomy_meta_fields_add', 10, 1);
    }
}

/**
 * Exibir meta fields na página de edição de termo
 */
function bazar_display_taxonomy_meta_fields($term, $taxonomy) {
    // Nonce para segurança
    wp_nonce_field('bazar_taxonomy_meta_fields_nonce', 'bazar_taxonomy_meta_fields_nonce');
    
    // Determinar qual campo de ordenação usar
    $ordem_key = ($taxonomy === 'componente') ? 'ordem_componente' : 'ordem';
    
    // Obter valor atual da ordem
    $ordem_atual = '';
    if (function_exists('get_field')) {
        $ordem_atual = get_field($ordem_key, $term);
    }
    if (empty($ordem_atual) || !is_numeric($ordem_atual)) {
        $ordem_atual = get_term_meta($term->term_id, $ordem_key, true);
    }
    if (empty($ordem_atual) || !is_numeric($ordem_atual)) {
        $ordem_atual = '999'; // Valor padrão
    }
    
    // Obter outros meta fields comuns
    $icone = get_term_meta($term->term_id, 'icone', true);
    $descricao = get_term_meta($term->term_id, 'descricao', true);
    $default_bicicletas = '';
    if ($taxonomy === 'componente') {
        if (function_exists('get_field')) {
            $default_bicicletas = get_field('default_bicicletas', $term);
        }
        if (empty($default_bicicletas)) {
            $default_bicicletas = get_term_meta($term->term_id, 'default_bicicletas', true);
        }
    }
    
    ?>
    <tr class="form-field bazar-taxonomy-meta-fields-row">
        <th scope="row" colspan="2">
            <h3 style="margin: 15px 0 10px 0; padding: 10px; background-color: #f0f0f0; border-left: 4px solid #2271b1;">
                📋 Meta Fields do Termo
            </h3>
        </th>
    </tr>
    
    <tr class="form-field">
        <th scope="row">
            <label for="bazar_ordem">
                <?php 
                echo ($taxonomy === 'componente') ? __('Ordem do Componente', 'bazar-bikes') : __('Ordem', 'bazar-bikes'); 
                ?>
            </label>
        </th>
        <td>
            <input 
                type="number" 
                id="bazar_ordem" 
                name="bazar_ordem" 
                value="<?php echo esc_attr($ordem_atual); ?>" 
                min="0" 
                max="9999"
                step="1"
                style="width: 120px; padding: 5px; font-size: 14px;"
            />
            <p class="description">
                <?php _e('Valor numérico para ordenação. Menor valor aparece primeiro. Padrão: 999 (último).', 'bazar-bikes'); ?>
                <?php if ($taxonomy === 'componente'): ?>
                    <br><strong>Campo usado:</strong> <code>ordem_componente</code>
                <?php else: ?>
                    <br><strong>Campo usado:</strong> <code>ordem</code>
                <?php endif; ?>
            </p>
        </td>
    </tr>
    
    <?php if ($taxonomy === 'componente'): ?>
    <tr class="form-field">
        <th scope="row">
            <label for="bazar_default_bicicletas"><?php _e('Componente Obrigatório', 'bazar-bikes'); ?></label>
        </th>
        <td>
            <input 
                type="checkbox" 
                id="bazar_default_bicicletas" 
                name="bazar_default_bicicletas" 
                value="1"
                <?php checked($default_bicicletas, true, true); ?>
            />
            <label for="bazar_default_bicicletas"><?php _e('Marcar como componente obrigatório (default_bicicletas)', 'bazar-bikes'); ?></label>
        </td>
    </tr>
    <?php endif; ?>
    
    <tr class="form-field">
        <th scope="row">
            <label><?php _e('Outros Meta Fields', 'bazar-bikes'); ?></label>
        </th>
        <td>
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Campo</th>
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($icone)): ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Ícone</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">
                            <?php echo esc_html($icone); ?>
                            <?php if (filter_var($icone, FILTER_VALIDATE_URL)): ?>
                                <br><img src="<?php echo esc_url($icone); ?>" alt="Ícone" style="max-width: 50px; max-height: 50px; margin-top: 5px;">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($descricao)): ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Descrição</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo esc_html($descricao); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Term ID</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo esc_html($term->term_id); ?></td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Slug</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo esc_html($term->slug); ?></td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Taxonomia</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo esc_html($taxonomy); ?></td>
                    </tr>
                </tbody>
            </table>
            <p class="description" style="margin-top: 10px;">
                <em>ℹ️ Estes campos são apenas informativos. Para editar outros campos, use os campos ACF ou edite diretamente no banco de dados.</em>
            </p>
        </td>
    </tr>
    <?php
}

/**
 * Exibir meta fields na página de adicionar novo termo
 */
function bazar_display_taxonomy_meta_fields_add($taxonomy) {
    // Nonce para segurança
    wp_nonce_field('bazar_taxonomy_meta_fields_nonce', 'bazar_taxonomy_meta_fields_nonce');
    
    // Determinar qual campo de ordenação usar
    $ordem_key = ($taxonomy === 'componente') ? 'ordem_componente' : 'ordem';
    
    ?>
    <div class="form-field">
        <label for="bazar_ordem"><?php _e('Ordem', 'bazar-bikes'); ?></label>
        <input 
            type="number" 
            id="bazar_ordem" 
            name="bazar_ordem" 
            value="999" 
            min="0" 
            max="9999"
            style="width: 100px;"
        />
        <p class="description">
            <?php _e('Valor numérico para ordenação. Menor valor aparece primeiro. Padrão: 999 (último).', 'bazar-bikes'); ?>
        </p>
    </div>
    
    <?php if ($taxonomy === 'componente'): ?>
    <div class="form-field">
        <label for="bazar_default_bicicletas">
            <input 
                type="checkbox" 
                id="bazar_default_bicicletas" 
                name="bazar_default_bicicletas" 
                value="1"
            />
            <?php _e('Componente Obrigatório (default_bicicletas)', 'bazar-bikes'); ?>
        </label>
        <p class="description">
            <?php _e('Marcar como componente obrigatório para bicicletas.', 'bazar-bikes'); ?>
        </p>
    </div>
    <?php endif; ?>
    <?php
}

/**
 * Salvar meta fields quando termo é editado
 */
add_action('edited_term', 'bazar_save_taxonomy_meta_fields', 10, 3);
function bazar_save_taxonomy_meta_fields($term_id, $tt_id, $taxonomy) {
    // Verificar nonce
    if (!isset($_POST['bazar_taxonomy_meta_fields_nonce']) || 
        !wp_verify_nonce($_POST['bazar_taxonomy_meta_fields_nonce'], 'bazar_taxonomy_meta_fields_nonce')) {
        return;
    }
    
    // Verificar permissão
    if (!current_user_can('manage_categories')) {
        return;
    }
    
    // Determinar qual campo de ordenação usar
    $ordem_key = ($taxonomy === 'componente') ? 'ordem_componente' : 'ordem';
    
    // Salvar campo de ordenação
    if (isset($_POST['bazar_ordem'])) {
        $ordem = intval($_POST['bazar_ordem']);
        
        // Validar range
        if ($ordem < 0) {
            $ordem = 0;
        }
        if ($ordem > 9999) {
            $ordem = 9999;
        }
        
        // Salvar como term meta
        update_term_meta($term_id, $ordem_key, $ordem);
        
        // Se ACF estiver disponível, também salvar via update_field
        if (function_exists('update_field')) {
            $term = get_term($term_id, $taxonomy);
            if ($term && !is_wp_error($term)) {
                update_field($ordem_key, $ordem, $taxonomy . '_' . $term_id);
            }
        }
    }
    
    // Salvar default_bicicletas (apenas para componentes)
    if ($taxonomy === 'componente' && isset($_POST['bazar_default_bicicletas'])) {
        $default_bicicletas = ($_POST['bazar_default_bicicletas'] === '1') ? true : false;
        
        // Salvar como term meta
        update_term_meta($term_id, 'default_bicicletas', $default_bicicletas);
        
        // Se ACF estiver disponível, também salvar via update_field
        if (function_exists('update_field')) {
            $term = get_term($term_id, $taxonomy);
            if ($term && !is_wp_error($term)) {
                update_field('default_bicicletas', $default_bicicletas, $taxonomy . '_' . $term_id);
            }
        }
    } elseif ($taxonomy === 'componente') {
        // Se checkbox não foi marcado, remover
        delete_term_meta($term_id, 'default_bicicletas');
        if (function_exists('update_field')) {
            $term = get_term($term_id, $taxonomy);
            if ($term && !is_wp_error($term)) {
                update_field('default_bicicletas', false, $taxonomy . '_' . $term_id);
            }
        }
    }
    
    // Limpar cache relacionado
    if (class_exists('__Bazar_Component_Helper') && $taxonomy === 'componente') {
        __Bazar_Component_Helper::clear_cache();
    }
}

/**
 * Salvar meta fields quando termo é criado
 */
add_action('created_term', 'bazar_save_taxonomy_meta_fields', 10, 3);

