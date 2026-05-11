<?php
/**
 * Meta Fields para Posts (Anúncios)
 * 
 * Exibe meta fields relacionados a localização, proximidade e outros dados
 * no painel de edição do WordPress
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adicionar meta box para exibir meta fields do post
 */
add_action('add_meta_boxes', 'bazar_add_post_meta_fields_box');
function bazar_add_post_meta_fields_box() {
    // Adicionar apenas para posts (anúncios)
    add_meta_box(
        'bazar_post_meta_fields',
        'Dados do Anúncio (Meta Fields)',
        'bazar_display_post_meta_fields',
        'post',
        'normal',
        'high'
    );
}

/**
 * Exibir meta fields do post
 */
function bazar_display_post_meta_fields($post) {
    // Nonce para segurança
    wp_nonce_field('bazar_post_meta_fields_nonce', 'bazar_post_meta_fields_nonce');
    
    // Obter todos os meta fields relacionados
    $meta_fields = array(
        // Localização
        'cep' => 'CEP',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        
        // Dados de Proximidade
        'proximidade_cep_base' => 'CEP Base (Proximidade)',
        'proximidade_regiao_postal' => 'Região Postal',
        'proximidade_sub_regiao' => 'Sub Região',
        'proximidade_setor' => 'Setor',
        'proximidade_subsetor' => 'Subsetor',
        
        // Rating
        'simple_rating' => 'Rating',
        
        // Outros
        'post_views_count' => 'Visualizações',
    );
    
    ?>
    <div class="bazar-meta-fields-display">
        <style>
            .bazar-meta-fields-display {
                padding: 10px 0;
            }
            .bazar-meta-fields-display table {
                width: 100%;
                border-collapse: collapse;
            }
            .bazar-meta-fields-display table th,
            .bazar-meta-fields-display table td {
                padding: 8px 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            .bazar-meta-fields-display table th {
                background-color: #f5f5f5;
                font-weight: 600;
                width: 30%;
            }
            .bazar-meta-fields-display table td {
                background-color: #fff;
            }
            .bazar-meta-fields-display .meta-empty {
                color: #999;
                font-style: italic;
            }
            .bazar-meta-fields-section {
                margin-bottom: 20px;
            }
            .bazar-meta-fields-section h3 {
                margin: 0 0 10px 0;
                padding: 10px;
                background-color: #f0f0f0;
                border-left: 4px solid #2271b1;
            }
        </style>
        
        <!-- Seção: Localização -->
        <div class="bazar-meta-fields-section">
            <h3>📍 Localização</h3>
            <table>
                <tr>
                    <th>CEP</th>
                    <td><?php echo esc_html(get_post_meta($post->ID, 'cep', true) ?: '<span class="meta-empty">Não informado</span>'); ?></td>
                </tr>
                <tr>
                    <th>Latitude</th>
                    <td><?php echo esc_html(get_post_meta($post->ID, 'latitude', true) ?: '<span class="meta-empty">Não informado</span>'); ?></td>
                </tr>
                <tr>
                    <th>Longitude</th>
                    <td><?php echo esc_html(get_post_meta($post->ID, 'longitude', true) ?: '<span class="meta-empty">Não informado</span>'); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Seção: Dados de Proximidade -->
        <div class="bazar-meta-fields-section">
            <h3>🗺️ Dados de Proximidade</h3>
            <table>
                <tr>
                    <th>CEP Base</th>
                    <td><?php echo esc_html(get_post_meta($post->ID, 'proximidade_cep_base', true) ?: '<span class="meta-empty">Não calculado</span>'); ?></td>
                </tr>
                <tr>
                    <th>Região Postal</th>
                    <td><?php echo esc_html(get_post_meta($post->ID, 'proximidade_regiao_postal', true) ?: '<span class="meta-empty">Não calculado</span>'); ?></td>
                </tr>
                <tr>
                    <th>Sub Região</th>
                    <td><?php echo esc_html(get_post_meta($post->ID, 'proximidade_sub_regiao', true) ?: '<span class="meta-empty">Não calculado</span>'); ?></td>
                </tr>
                <tr>
                    <th>Setor</th>
                    <td><?php echo esc_html(get_post_meta($post->ID, 'proximidade_setor', true) ?: '<span class="meta-empty">Não calculado</span>'); ?></td>
                </tr>
                <tr>
                    <th>Subsetor</th>
                    <td><?php echo esc_html(get_post_meta($post->ID, 'proximidade_subsetor', true) ?: '<span class="meta-empty">Não calculado</span>'); ?></td>
                </tr>
            </table>
            <p><small><em>Estes dados são calculados automaticamente quando o CEP é salvo. Usados para ordenação por proximidade na busca.</em></small></p>
        </div>
        
        <!-- Seção: Impulsionamento/Destaque -->
        <?php
        $destaque_ativo = get_post_meta($post->ID, 'destaque_ativo', true);
        $is_destaque = has_term('destaque', 'status', $post->ID);
        $destaque_aguarda_cpf = defined('BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO')
          && get_post_meta($post->ID, BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO, true) === '1';
        if ($destaque_ativo || $is_destaque || get_post_meta($post->ID, 'destaque_payment_id', true)) :
        ?>
        <div class="bazar-meta-fields-section">
            <h3>⭐ Impulsionamento / Destaque</h3>
            <table>
                <tr>
                    <th>Status</th>
                    <td>
                        <?php if ($is_destaque || ($destaque_ativo === '1' && !$destaque_aguarda_cpf)): ?>
                            <span style="color: #46b450; font-weight: bold;">✅ Em destaque</span>
                        <?php elseif ($destaque_aguarda_cpf && get_post_meta($post->ID, 'destaque_payment_status', true) === 'paid'): ?>
                            <span style="color: #dba617; font-weight: bold;">⏳ Pagamento ok — aguardando validação do CPF do autor</span>
                        <?php else: ?>
                            <span class="meta-empty">Não está em destaque</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Tipo de Impulsionamento</th>
                    <td><?php 
                        $tipo = get_post_meta($post->ID, 'destaque_tipo', true);
                        if ($tipo === 'newsletter' || $tipo === 'desconto') {
                            echo '<span style="color: #2271b1;">📧 Newsletter (com desconto)</span>';
                        } elseif ($tipo === 'simples' || $tipo === 'normal') {
                            echo '<span>💳 Simples (sem desconto)</span>';
                        } else {
                            echo '<span class="meta-empty">Não informado</span>';
                        }
                    ?></td>
                </tr>
                <tr>
                    <th>ID do Pagamento (Stripe)</th>
                    <td><?php 
                        $payment_id = get_post_meta($post->ID, 'destaque_payment_id', true);
                        if ($payment_id) {
                            echo '<code style="font-size: 11px;">' . esc_html($payment_id) . '</code>';
                        } else {
                            echo '<span class="meta-empty">Não informado</span>';
                        }
                    ?></td>
                </tr>
                <tr>
                    <th>Status do Pagamento</th>
                    <td><?php 
                        $payment_status = get_post_meta($post->ID, 'destaque_payment_status', true);
                        if ($payment_status === 'paid') {
                            echo '<span style="color: #46b450; font-weight: bold;">✅ Pago</span>';
                        } elseif ($payment_status) {
                            echo esc_html(ucfirst($payment_status));
                        } else {
                            echo '<span class="meta-empty">Não informado</span>';
                        }
                    ?></td>
                </tr>
                <tr>
                    <th>Data de Ativação</th>
                    <td><?php 
                        $data_ativacao = get_post_meta($post->ID, 'destaque_data_ativacao', true);
                        if ($data_ativacao) {
                            $data_formatada = date_i18n('d/m/Y H:i:s', $data_ativacao);
                            echo esc_html($data_formatada);
                            echo ' <small style="color: #666;">(' . human_time_diff($data_ativacao, current_time('timestamp')) . ' atrás)</small>';
                        } else {
                            echo '<span class="meta-empty">Não informado</span>';
                        }
                    ?></td>
                </tr>
                <?php
                $data_remocao = get_post_meta($post->ID, 'destaque_data_remocao', true);
                $motivo_remocao = get_post_meta($post->ID, 'destaque_motivo_remocao', true);
                if ($data_remocao):
                ?>
                <tr>
                    <th>Data de Remoção</th>
                    <td>
                        <?php 
                            $data_formatada = date_i18n('d/m/Y H:i:s', $data_remocao);
                            echo esc_html($data_formatada);
                        ?>
                        <?php if ($motivo_remocao): ?>
                            <br><small style="color: #d63638;"><strong>Motivo:</strong> <?php echo esc_html($motivo_remocao); ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Desconto de Newsletter</th>
                    <td><?php 
                        $newsletter_discount = get_post_meta($post->ID, 'destaque_newsletter_discount', true);
                        if ($newsletter_discount === '1') {
                            $desconto_pct = 10;
                            if (function_exists('bazar_destaque_get_promo_config')) {
                                $desconto_pct = bazar_destaque_get_promo_config()['desconto_percent'];
                            }
                            echo '<span style="color: #2271b1;">✅ Sim (' . (int) $desconto_pct . '% OFF)</span>';
                        } else {
                            echo '<span>❌ Não</span>';
                        }
                    ?></td>
                </tr>
                <?php
                $preco_pago = get_post_meta($post->ID, 'destaque_preco_pago', true);
                if ($preco_pago):
                ?>
                <tr>
                    <th>Preço Pago</th>
                    <td><strong>R$ <?php echo esc_html(number_format(floatval($preco_pago), 2, ',', '.')); ?></strong></td>
                </tr>
                <?php endif; ?>
            </table>
            <p><small><em>Estes dados são gerenciados automaticamente pelo sistema de pagamento Stripe.</em></small></p>
        </div>
        <?php endif; ?>
        
        <!-- Seção: Outros Dados -->
        <div class="bazar-meta-fields-section">
            <h3>📊 Outros Dados</h3>
            <table>
                <tr>
                    <th>Rating</th>
                    <td><?php 
                        $rating = get_post_meta($post->ID, 'simple_rating', true);
                        echo $rating ? esc_html($rating) . '/5' : '<span class="meta-empty">Não avaliado</span>';
                    ?></td>
                </tr>
                <tr>
                    <th>Visualizações</th>
                    <td><?php 
                        $views = get_post_meta($post->ID, 'post_views_count', true);
                        echo $views ? number_format($views, 0, ',', '.') : '0';
                    ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Informações adicionais -->
        <div class="bazar-meta-fields-section">
            <p><strong>ℹ️ Informação:</strong> Estes campos são gerenciados automaticamente pelo sistema. Para alterar a localização, edite o anúncio através do formulário de edição.</p>
        </div>
    </div>
    <?php
}

/**
 * Opcional: Adicionar botão para recalcular dados de proximidade
 */
add_action('post_submitbox_misc_actions', 'bazar_add_recalculate_proximity_button');
function bazar_add_recalculate_proximity_button($post) {
    // Apenas para posts publicados
    if ($post->post_status !== 'publish') {
        return;
    }
    
    // Verificar se tem CEP
    $cep = get_post_meta($post->ID, 'cep', true);
    if (empty($cep)) {
        return;
    }
    
    ?>
    <div class="misc-pub-section">
        <span id="recalculate-proximity-wrapper">
            <a href="#" id="recalculate-proximity" class="button button-small" style="margin-top: 5px;">
                🔄 Recalcular Dados de Proximidade
            </a>
            <span class="spinner" style="float: none; margin: 0 5px;"></span>
        </span>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#recalculate-proximity').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $spinner = $button.next('.spinner');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bazar_recalculate_proximity',
                    post_id: <?php echo $post->ID; ?>,
                    nonce: '<?php echo wp_create_nonce('bazar_recalculate_proximity'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Dados de proximidade recalculados com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao recalcular: ' + (response.data || 'Erro desconhecido'));
                    }
                },
                error: function() {
                    alert('Erro ao recalcular dados de proximidade.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * AJAX: Recalcular dados de proximidade
 */
add_action('wp_ajax_bazar_recalculate_proximity', 'bazar_ajax_recalculate_proximity');
function bazar_ajax_recalculate_proximity() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bazar_recalculate_proximity')) {
        wp_send_json_error('Erro de segurança');
        return;
    }
    
    // Verificar permissão
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Sem permissão');
        return;
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    if ($post_id <= 0) {
        wp_send_json_error('ID do post inválido');
        return;
    }
    
    // Obter CEP
    $cep = get_post_meta($post_id, 'cep', true);
    if (empty($cep)) {
        wp_send_json_error('Post não possui CEP');
        return;
    }
    
    // Recalcular dados de proximidade
    global $geo_api;
    if (!$geo_api) {
        $geo_api = BazarBikes_GeoAPI::getInstance();
    }
    
    $result = $geo_api->salvar_dados_proximidade_anuncio($post_id, $cep);
    
    if ($result) {
        wp_send_json_success('Dados recalculados com sucesso');
    } else {
        wp_send_json_error('Erro ao recalcular dados');
    }
}
