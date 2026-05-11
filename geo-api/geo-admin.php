<?php
/**
 * XXXXXX Admin Geo - Painel de Administração para Geolocalização
 * 
 * Interface de administração para gerenciar migração entre APIs
 * e visualizar estatísticas do sistema de CEP
 */

class BazarBikes_Admin_Geo {
    
    private static $instance = null;
    
    /**
     * Singleton pattern
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Adicionar menu de administração
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'XXXXXX Geolocalização',
            'Geolocalização',
            'manage_options',
            'bazarbikes-geo',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enfileirar scripts de administração
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'tools_page_bazarbikes-geo') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'bazarbikes_admin_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bazarbikes_admin_nonce')
        ));
    }
    
    /**
     * Página de administração
     */
    public function admin_page() {
        $geo_api = BazarBikes_GeoAPI::getInstance();
        $stats = $geo_api->obter_estatisticas_cache();
        $provider_atual = get_option('bazarbikes_geo_provider', 'brasilapi_v2');
        $geocodebr_url = get_option('bazarbikes_geocodebr_url', '');
        
        ?>
        <div class="wrap">
            <h1>XXXXXX Geolocalização</h1>
            
            <div class="bazarbikes-admin-container">
                
                <!-- Status Atual -->
                <div class="postbox">
                    <h2 class="hndle">Status Atual</h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Provedor Atual</th>
                                <td>
                                    <strong><?php echo esc_html($provider_atual); ?></strong>
                                    <?php if ($provider_atual === 'brasilapi_v2'): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                        <span class="description">BrasilAPI V2 - Coordenadas sempre disponíveis</span>
                                    <?php elseif ($provider_atual === 'geocodebr'): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                        <span class="description">GeocodeR - VPS própria</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">CEPs em Cache</th>
                                <td>
                                    <strong><?php echo number_format($stats['total_ceps']); ?></strong> CEPs
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">CEPs com Coordenadas</th>
                                <td>
                                    <strong><?php echo number_format($stats['ceps_com_coordenadas']); ?></strong> CEPs
                                    <span class="description">(<?php echo $stats['total_ceps'] > 0 ? round(($stats['ceps_com_coordenadas'] / $stats['total_ceps']) * 100, 1) : 0; ?>%)</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Migração para GeocodeR -->
                <div class="postbox">
                    <h2 class="hndle">Migração para GeocodeR</h2>
                    <div class="inside">
                        <p>Migre para o GeocodeR (VPS própria) para ter controle total sobre os dados de geolocalização.</p>
                        
                        <form id="migracao-geocodebr-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="geocodebr_url">URL do GeocodeR</label>
                                    </th>
                                    <td>
                                        <input type="url" 
                                               id="geocodebr_url" 
                                               name="geocodebr_url" 
                                               value="<?php echo esc_attr($geocodebr_url); ?>"
                                               placeholder="https://sua-vps.com/api"
                                               class="regular-text" />
                                        <p class="description">
                                            URL base da sua instância do GeocodeR.<br>
                                            Exemplo: <code>https://geocode.suaempresa.com</code>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary" id="btn-migrar-geocodebr">
                                    Migrar para GeocodeR
                                </button>
                                <span id="migracao-loading" style="display: none;">
                                    <span class="spinner is-active"></span> Migrando...
                                </span>
                            </p>
                        </form>
                        
                        <div id="migracao-result" style="display: none;"></div>
                    </div>
                </div>
                
                <!-- Voltar para BrasilAPI V2 -->
                <?php if ($provider_atual === 'geocodebr'): ?>
                <div class="postbox">
                    <h2 class="hndle">Voltar para BrasilAPI V2</h2>
                    <div class="inside">
                        <p>Volte para o BrasilAPI V2 se precisar usar o serviço gratuito novamente.</p>
                        
                        <p class="submit">
                            <button type="button" class="button button-secondary" id="btn-voltar-brasilapi">
                                Voltar para BrasilAPI V2
                            </button>
                            <span id="volta-loading" style="display: none;">
                                <span class="spinner is-active"></span> Alterando...
                            </span>
                        </p>
                        
                        <div id="volta-result" style="display: none;"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Teste de CEP -->
                <div class="postbox">
                    <h2 class="hndle">Teste de CEP</h2>
                    <div class="inside">
                        <p>Teste a busca de CEP com o provedor atual.</p>
                        
                        <form id="teste-cep-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="teste_cep">CEP para Teste</label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="teste_cep" 
                                               name="teste_cep" 
                                               placeholder="01001-000"
                                               maxlength="9"
                                               class="regular-text" />
                                        <p class="description">Digite um CEP para testar a API atual</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-secondary" id="btn-testar-cep">
                                    Testar CEP
                                </button>
                                <span id="teste-loading" style="display: none;">
                                    <span class="spinner is-active"></span> Testando...
                                </span>
                            </p>
                        </form>
                        
                        <div id="teste-result" style="display: none;"></div>
                    </div>
                </div>
                
                <!-- Informações Técnicas -->
                <div class="postbox">
                    <h2 class="hndle">Informações Técnicas</h2>
                    <div class="inside">
                        <h3>APIs Suportadas</h3>
                        <ul>
                            <li><strong>BrasilAPI V2:</strong> API gratuita com coordenadas precisas</li>
                            <li><strong>GeocodeR:</strong> VPS própria baseada em <a href="https://github.com/ipeaGIT/geocodebr" target="_blank">geocodebr</a></li>
                            <li><strong>ViaCEP:</strong> Fallback automático (sem coordenadas)</li>
                        </ul>
                        
                        <h3>Cache</h3>
                        <ul>
                            <li>CEPs são armazenados em cache por 30 dias</li>
                            <li>Cache é compartilhado entre todas as APIs</li>
                            <li>Migração preserva dados existentes</li>
                        </ul>
                        
                        <h3>Cálculo de Proximidade</h3>
                        <ul>
                            <li><strong>Coordenadas:</strong> Fórmula de Haversine (precisão máxima)</li>
                            <li><strong>CEP:</strong> Estrutura hierárquica (fallback)</li>
                        </ul>
                    </div>
                </div>
                
            </div>
        </div>
        
        <style>
        .bazarbikes-admin-container .postbox {
            margin-bottom: 20px;
        }
        
        .bazarbikes-admin-container .postbox h2 {
            margin: 0;
            padding: 12px 20px;
            border-bottom: 1px solid #ccd0d4;
        }
        
        .bazarbikes-admin-container .inside {
            padding: 20px;
        }
        
        .bazarbikes-admin-container .form-table th {
            width: 200px;
        }
        
        #migracao-result, #volta-result, #teste-result {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
        }
        
        #migracao-result.success, #volta-result.success, #teste-result.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        #migracao-result.error, #volta-result.error, #teste-result.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .teste-result-details {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Migração para GeocodeR
            $('#migracao-geocodebr-form').on('submit', function(e) {
                e.preventDefault();
                
                const url = $('#geocodebr_url').val();
                if (!url) {
                    alert('Por favor, digite a URL do GeocodeR');
                    return;
                }
                
                $('#migracao-loading').show();
                $('#migracao-result').hide();
                
                $.post(bazarbikes_admin_ajax.ajaxurl, {
                    action: 'bazarbikes_migrar_geocodebr',
                    geocodebr_url: url,
                    nonce: bazarbikes_admin_ajax.nonce
                }, function(response) {
                    $('#migracao-loading').hide();
                    
                    if (response.success) {
                        $('#migracao-result')
                            .removeClass('error')
                            .addClass('success')
                            .html('✅ ' + response.data.message)
                            .show();
                        
                        // Recarregar página após 2 segundos
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#migracao-result')
                            .removeClass('success')
                            .addClass('error')
                            .html('❌ ' + response.data)
                            .show();
                    }
                });
            });
            
            // Voltar para BrasilAPI V2
            $('#btn-voltar-brasilapi').on('click', function() {
                if (!confirm('Tem certeza que deseja voltar para o BrasilAPI V2?')) {
                    return;
                }
                
                $('#volta-loading').show();
                $('#volta-result').hide();
                
                $.post(bazarbikes_admin_ajax.ajaxurl, {
                    action: 'bazarbikes_voltar_brasilapi',
                    nonce: bazarbikes_admin_ajax.nonce
                }, function(response) {
                    $('#volta-loading').hide();
                    
                    if (response.success) {
                        $('#volta-result')
                            .removeClass('error')
                            .addClass('success')
                            .html('✅ ' + response.data.message)
                            .show();
                        
                        // Recarregar página após 2 segundos
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#volta-result')
                            .removeClass('success')
                            .addClass('error')
                            .html('❌ ' + response.data)
                            .show();
                    }
                });
            });
            
            // Teste de CEP
            $('#teste-cep-form').on('submit', function(e) {
                e.preventDefault();
                
                console.log('XXXXXX Admin: Iniciando teste de CEP');
                
                const cep = $('#teste_cep').val();
                if (!cep) {
                    alert('Por favor, digite um CEP');
                    return;
                }
                
                console.log('XXXXXX Admin: CEP para teste:', cep);
                console.log('XXXXXX Admin: AJAX URL:', bazarbikes_admin_ajax.ajaxurl);
                console.log('XXXXXX Admin: Nonce:', bazarbikes_admin_ajax.nonce);
                
                $('#teste-loading').show();
                $('#teste-result').hide();
                
                $.post(bazarbikes_admin_ajax.ajaxurl, {
                    action: 'bazarbikes_testar_cep',
                    cep: cep,
                    nonce: bazarbikes_admin_ajax.nonce
                }, function(response) {
                    console.log('XXXXXX Admin: Resposta recebida:', response);
                    
                    $('#teste-loading').hide();
                    
                    if (response.success) {
                        const data = response.data;
                        let html = 'CEP encontrado!<br><br>';
                        html += '<strong>Dados:</strong><br>';
                        html += 'CEP: ' + data.localizacao.cep + '<br>';
                        html += 'Cidade: ' + data.localizacao.cidade + '<br>';
                        html += 'Estado: ' + data.localizacao.estado + '<br>';
                        html += 'Bairro: ' + data.localizacao.bairro + '<br>';
                        
                        if (data.localizacao.latitude && data.localizacao.longitude) {
                            html += 'Coordenadas: ' + data.localizacao.latitude + ', ' + data.localizacao.longitude + '<br>';
                        }
                        
                        html += 'Fonte: ' + data.meta.fonte_dados + '<br>';
                        html += 'Cache: ' + (data.meta.cache_hit ? 'Sim' : 'Não') + '<br>';
                        
                        $('#teste-result')
                            .removeClass('error')
                            .addClass('success')
                            .html(html)
                            .show();
                    } else {
                        console.log('XXXXXX Admin: Erro na resposta:', response.data);
                        $('#teste-result')
                            .removeClass('success')
                            .addClass('error')
                            .html('❌ ' + response.data)
                            .show();
                    }
                }).fail(function(xhr, status, error) {
                    console.error('XXXXXX Admin: Erro na requisição AJAX:', error);
                    console.error('XXXXXX Admin: Status:', status);
                    console.error('XXXXXX Admin: XHR:', xhr);
                    
                    $('#teste-loading').hide();
                    $('#teste-result')
                        .removeClass('success')
                        .addClass('error')
                        .html('❌ Erro na requisição: ' + error)
                        .show();
                });
            });
            
            // Formatar CEP automaticamente
            $('#teste_cep').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length > 5) {
                    value = value.substring(0, 5) + '-' + value.substring(5, 8);
                }
                $(this).val(value);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Previne clonagem (Singleton)
     */
    private function __clone() {}
    
    /**
     * Previne unserialize (Singleton)
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Inicializar a classe
$bazarbikes_admin_geo = BazarBikes_Admin_Geo::getInstance();

/**
 * AJAX: Voltar para BrasilAPI V2
 */
add_action('wp_ajax_bazarbikes_voltar_brasilapi', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sem permissão');
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bazarbikes_admin_nonce')) {
        wp_send_json_error('Erro de segurança');
        return;
    }
    
    update_option('bazarbikes_geo_provider', 'brasilapi_v2');
    
    wp_send_json_success(array(
        'message' => 'Voltou para BrasilAPI V2 com sucesso'
    ));
});

/**
 * AJAX: Testar CEP
 */
add_action('wp_ajax_bazarbikes_testar_cep', function() {
    try {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sem permissão');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bazarbikes_admin_nonce')) {
            wp_send_json_error('Erro de segurança');
            return;
        }
        
        $cep = sanitize_text_field($_POST['cep'] ?? '');
        if (empty($cep)) {
            wp_send_json_error('CEP é obrigatório');
            return;
        }
        
        // Verificar se a classe existe
        if (!class_exists('BazarBikes_GeoAPI')) {
            wp_send_json_error('Classe não encontrada');
            return;
        }
        
        $geo_api = BazarBikes_GeoAPI::getInstance();
        $dados = $geo_api->buscar_cep($cep);
        
        if ($dados) {
            wp_send_json_success($dados);
        } else {
            wp_send_json_error('CEP não encontrado');
        }
        
    } catch (Exception $e) {
        wp_send_json_error('Erro interno: ' . $e->getMessage());
    } catch (Error $e) {
        wp_send_json_error('Erro fatal: ' . $e->getMessage());
    }
});
