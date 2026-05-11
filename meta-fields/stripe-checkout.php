<?php
/**
 * Configurações simplificadas do Stripe
 * Chaves da API e preços para o impulsionamento (Checkout Session).
 *
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adiciona página de configurações do Stripe
 */
add_action('admin_menu', 'bazar_stripe_add_admin_menu');
function bazar_stripe_add_admin_menu() {
    add_options_page(
        'Configurações Stripe',
        'Stripe Checkout',
        'manage_options',
        'bazar-stripe-config',
        'bazar_stripe_config_page'
    );
}

/**
 * Página de configurações
 */
function bazar_stripe_config_page() {
    // Salvar configurações
    if (isset($_POST['bazar_stripe_save']) && check_admin_referer('bazar_stripe_config')) {
        update_option('bazar_stripe_secret_key_test', sanitize_text_field($_POST['stripe_secret_key_test']));
        update_option('bazar_stripe_secret_key_live', sanitize_text_field($_POST['stripe_secret_key_live']));
        update_option('bazar_stripe_modo_producao', isset($_POST['stripe_modo_producao']) ? '1' : '0');

        // Salvar preços e configurações de promoção
        $preco_normal = floatval($_POST['preco_normal'] ?? 50.00);
        $preco_desconto_newsletter = floatval($_POST['preco_desconto_newsletter'] ?? 25.00);
        $desconto_newsletter_percent = max(0, min(100, (int) ($_POST['desconto_newsletter_percent'] ?? 10)));
        $promocao_ativa = isset($_POST['promocao_newsletter_ativa']) ? '1' : '0';
        // Campos globais da Promo
        $promo_titulo = isset($_POST['promo_titulo']) ? sanitize_text_field($_POST['promo_titulo']) : '';
        $promo_subtitulo = isset($_POST['promo_subtitulo']) ? sanitize_text_field($_POST['promo_subtitulo']) : '';
        $promo_modal_btn_label = isset($_POST['promo_modal_btn_label']) ? sanitize_text_field($_POST['promo_modal_btn_label']) : '';
        $promo_descricao = isset($_POST['promo_descricao']) ? wp_kses_post($_POST['promo_descricao']) : '';
        $promo_link = isset($_POST['promo_link']) ? esc_url_raw($_POST['promo_link']) : '';
        $promo_terms_url = isset($_POST['promo_terms_url']) ? esc_url_raw($_POST['promo_terms_url']) : '';
        // Checkbox: aplicar desconto direto no checkout
        $promo_aplica_desconto_checkout = isset($_POST['promo_aplica_desconto_checkout']) ? '1' : '0';
        // Checkbox: exibir promo nos emails de publicação
        $promo_mostrar_email = isset($_POST['promo_mostrar_email']) ? '1' : '0';
        
        // Validações
        if ($preco_normal <= 0) {
            echo '<div class="notice notice-error"><p>Preço normal deve ser maior que zero.</p></div>';
        } elseif ($promocao_ativa === '1' && $preco_desconto_newsletter >= $preco_normal) {
            echo '<div class="notice notice-error"><p>Preço com desconto de newsletter deve ser menor que o preço normal quando a promoção estiver ativa.</p></div>';
        } else {
            update_option('bazar_destaque_preco_normal', $preco_normal);
            update_option('bazar_destaque_preco_desconto_newsletter', $preco_desconto_newsletter);
            update_option('bazar_destaque_desconto_newsletter_percent', $desconto_newsletter_percent);
            update_option('bazar_promocao_newsletter_ativa', $promocao_ativa);
            // Salvar campos da Promo
            update_option('bazar_promo_titulo', $promo_titulo);
            update_option('bazar_promo_subtitulo', $promo_subtitulo);
            update_option('bazar_promo_modal_btn_label', $promo_modal_btn_label);
            update_option('bazar_promo_descricao', $promo_descricao);
            update_option('bazar_promo_link', $promo_link);
            update_option('bazar_promo_terms_url', $promo_terms_url);
            update_option('bazar_promo_aplica_desconto_checkout', $promo_aplica_desconto_checkout);
            update_option('bazar_promo_mostrar_email', $promo_mostrar_email);
            echo '<div class="notice notice-success"><p>Configurações salvas com sucesso!</p></div>';
        }
    }
    
    // Obter valores atuais
    $preco_normal = get_option('bazar_destaque_preco_normal', 50.00);
    $preco_desconto_newsletter = get_option('bazar_destaque_preco_desconto_newsletter', 25.00);
    $desconto_newsletter_percent = (int) get_option('bazar_destaque_desconto_newsletter_percent', 10);
    $promocao_ativa = get_option('bazar_promocao_newsletter_ativa', '0');
    // Campos atuais da Promo
    $promo_titulo = get_option('bazar_promo_titulo', '');
    $promo_subtitulo = get_option('bazar_promo_subtitulo', '');
    $promo_modal_btn_label = get_option('bazar_promo_modal_btn_label', '');
    $promo_descricao = get_option('bazar_promo_descricao', '');
    $promo_link = get_option('bazar_promo_link', '');
    $promo_terms_url = get_option('bazar_promo_terms_url', '');
    $promo_aplica_desconto_checkout = get_option('bazar_promo_aplica_desconto_checkout', '1');
    $promo_mostrar_email = get_option('bazar_promo_mostrar_email', '1');
    ?>
    <div class="wrap">
        <h1>Configurações Strip</h1>
        <p>Configure as chaves da API do Stripe e os preços do impulsionamento (checkout via API).</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('bazar_stripe_config'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="stripe_secret_key_test">Stripe Secret Key (Teste)</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            id="stripe_secret_key_test" 
                            name="stripe_secret_key_test" 
                            value="<?php echo esc_attr(get_option('bazar_stripe_secret_key_test', '')); ?>" 
                            class="regular-text"
                            placeholder="sk_test_xxxxx"
                        />
                        <p class="description">
                            Chave secreta de teste do Stripe (começa com sk_test_). Necessária para verificar pagamentos.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="stripe_secret_key_live">Stripe Secret Key (Produção)</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            id="stripe_secret_key_live" 
                            name="stripe_secret_key_live" 
                            value="<?php echo esc_attr(get_option('bazar_stripe_secret_key_live', '')); ?>" 
                            class="regular-text"
                            placeholder="sk_live_xxxxx"
                        />
                        <p class="description">
                            Chave secreta de produção do Stripe (começa com sk_live_). Use apenas quando estiver em produção.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Modo de pagamento</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                id="stripe_modo_producao"
                                name="stripe_modo_producao"
                                value="1"
                                <?php checked(get_option('bazar_stripe_modo_producao', '0'), '1'); ?>
                            />
                            <strong>Usar modo produção (pagamentos reais)</strong>
                        </label>
                        <p class="description" style="margin-top:6px;">
                            Marque esta opção em <strong>produção</strong> para que o Stripe use a chave <code>sk_live_</code> e cobranças reais sejam processadas.
                            Se estiver desmarcado, será usada a chave de teste (<code>sk_test_</code>) e cartões de teste (ex.: 4242...) serão aceitos sem cobrança.
                        </p>
                    </td>
                </tr>
            </table>
            
            <h2>Configurações de Preços</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="preco_normal">Preço Normal do Impulsionamento</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="preco_normal" 
                            name="preco_normal" 
                            value="<?php echo esc_attr($preco_normal); ?>" 
                            class="small-text"
                            step="0.01"
                            min="0.01"
                            required
                        />
                        <span> R$</span>
                        <p class="description">
                            Preço padrão para impulsionar um anúncio (ex: 50.00)
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="preco_desconto_newsletter">Preço com Desconto Newsletter</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="preco_desconto_newsletter" 
                            name="preco_desconto_newsletter" 
                            value="<?php echo esc_attr($preco_desconto_newsletter); ?>" 
                            class="small-text"
                            step="0.01"
                            min="0.01"
                            required
                        />
                        <span> R$</span>
                        <p class="description">
                            Preço com desconto de newsletter para usuários que assinam (ex: 25.00)
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="desconto_newsletter_percent">Porcentagem de desconto (%)</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="desconto_newsletter_percent" 
                            name="desconto_newsletter_percent" 
                            value="<?php echo esc_attr($desconto_newsletter_percent); ?>" 
                            class="small-text"
                            min="0"
                            max="100"
                        />
                        <span> %</span>
                        <p class="description">
                            Porcentagem exibida na promoção (ex.: 10 = 10% de desconto). Usado em botões, banners e emails.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="promocao_newsletter_ativa">Ativar Promoção Newsletter</label>
                    </th>
                    <td>
                        <label>
                            <input 
                                type="checkbox" 
                                id="promocao_newsletter_ativa" 
                                name="promocao_newsletter_ativa" 
                                value="1"
                                <?php checked($promocao_ativa, '1'); ?>
                            />
                            Ativar promoção de <?php echo (int) $desconto_newsletter_percent; ?>% de desconto para assinantes da newsletter
                        </label>
                        <p class="description">
                            Quando ativada, usuários que ainda não usaram o desconto verão a opção de impulsionar com desconto ao assinar a newsletter.
                        </p>
                    </td>
                </tr>
            </table>

            <h2>Promo</h2>
            <p>Defina aqui os textos globais da promoção (usados em banners, CTAs, modais, etc.) e se o desconto deve ser aplicado automaticamente no checkout.</p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="promo_titulo">Título da Promo</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="promo_titulo"
                            name="promo_titulo"
                            value="<?php echo esc_attr($promo_titulo); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            Exemplo: "PROMOÇÃO INSTAGRAM 50% OFF".
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="promo_subtitulo">Subtítulo</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="promo_subtitulo"
                            name="promo_subtitulo"
                            value="<?php echo esc_attr($promo_subtitulo); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            Exemplo: "Curta @XXXXXX e peça o cupom no Direct".
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="promo_modal_btn_label">Texto do botão (promoção) no modal</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="promo_modal_btn_label"
                            name="promo_modal_btn_label"
                            value="<?php echo esc_attr($promo_modal_btn_label); ?>"
                            class="regular-text"
                            placeholder="<?php echo esc_attr(__('Ex.: Impulsionar com 50% OFF', 'bazar')); ?>"
                        />
                        <p class="description">
                            Rótulo do botão principal de checkout com desconto no modal <strong>Impulsionar anúncio</strong>.
                            Se vazio, o tema usa o subtítulo acima e, em último caso, um texto padrão.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="promo_descricao">Descrição</label>
                    </th>
                    <td>
                        <textarea
                            id="promo_descricao"
                            name="promo_descricao"
                            rows="4"
                            class="large-text"
                        ><?php echo esc_textarea($promo_descricao); ?></textarea>
                        <p class="description">
                            Texto mais detalhado da promoção (pode ser usado em páginas e modais).
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="promo_link">Link da Promo (opcional)</label>
                    </th>
                    <td>
                        <input
                            type="url"
                            id="promo_link"
                            name="promo_link"
                            value="<?php echo esc_attr($promo_link); ?>"
                            class="regular-text"
                            placeholder="https://XXXXXX/"
                        />
                        <p class="description">
                            URL para onde o CTA da promoção deve apontar (perfil do Instagram, landing da promo, etc.).
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="promo_terms_url">URL dos Termos da Promo (opcional)</label>
                    </th>
                    <td>
                        <input
                            type="url"
                            id="promo_terms_url"
                            name="promo_terms_url"
                            value="<?php echo esc_attr($promo_terms_url); ?>"
                            class="regular-text"
                            placeholder="<?php echo esc_attr(home_url('/termos-promocao-instagram/')); ?>"
                        />
                        <p class="description">
                            Endereço da página com os termos legais da promoção. Se vazio, será usado o padrão configurado no tema.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="promo_aplica_desconto_checkout">Aplicar desconto direto no checkout</label>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                id="promo_aplica_desconto_checkout"
                                name="promo_aplica_desconto_checkout"
                                value="1"
                                <?php checked($promo_aplica_desconto_checkout, '1'); ?>
                            />
                            Ativar uso do preço com desconto diretamente no checkout.
                        </label>
                        <p class="description">
                            Quando desmarcado, apenas os textos da promoção serão exibidos no site. O desconto real deve ser aplicado via cupom na página do Stripe ou outro fluxo externo.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="promo_mostrar_email">Exibir promoção nos e-mails de publicação</label>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                id="promo_mostrar_email"
                                name="promo_mostrar_email"
                                value="1"
                                <?php checked($promo_mostrar_email, '1'); ?>
                            />
                            Incluir a promoção vigente no e-mail enviado quando o anúncio é publicado.
                        </label>
                        <p class="description">
                            Quando desmarcado, os e-mails de publicação não exibem nenhum destaque de promoção.
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="bazar_stripe_save" class="button button-primary" value="Salvar Configurações" />
            </p>
        </form>
        
        <hr>
        
        <h2>Cupons / Códigos promocionais</h2>
        <p>O checkout permite que o cliente digite um código promocional na <strong>página do Stripe</strong>. Para funcionar:</p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><strong>Caracteres permitidos pelo Stripe:</strong> apenas letras (A–Z, a–z) e números (0–9). Não use <code>#</code>, <code>-</code>, <code>_</code> ou outros caracteres especiais — o campo do Stripe pode removê-los e o código não bate.</li>
            <li>Crie primeiro um <strong>Coupon</strong> (desconto) no Stripe e depois um <strong>Promotion Code</strong> (o texto que o cliente digita) vinculado a esse coupon.</li>
            <li>Use o mesmo <strong>modo</strong> (Teste ou Produção): o código deve estar no mesmo ambiente (Test/Live) do checkout.</li>
        </ul>
        <p>Exemplo: use <code>TESTEPROD1</code> em vez de <code>TESTEPROD#1</code>.</p>

        <hr>
        
        <h2>Instruções</h2>
        <ol>
            <li>Acesse o painel do Stripe: <a href="https://dashboard.stripe.com" target="_blank">https://dashboard.stripe.com</a></li>
            <li>Os preços utilizados no checkout são os configurados acima (Impulsionamento Simples e com desconto Newsletter).</li>
            <li>O checkout é criado via API (Checkout Session). As URLs de retorno são definidas no código:
                <ul>
                    <li><strong>Sucesso:</strong> <code><?php echo esc_url(home_url('/anuncio-impulsionado/?payment=success&session_id={CHECKOUT_SESSION_ID}')); ?></code></li>
                    <li><strong>Cancelamento:</strong> <code><?php echo esc_url(home_url('/anuncio-impulsionado/?payment=canceled')); ?></code></li>
                </ul>
            </li>
        </ol>
        
        <div class="notice notice-info" style="margin: 20px 0;">
            <p><strong>Como funciona:</strong> O sistema verifica pagamentos automaticamente quando o usuário retorna da página de pagamento do Stripe, usando a API do Stripe. Não é necessário configurar webhook!</p>
        </div>
        
        <p>
            <strong>Documentação completa:</strong> 
            <a href="<?php echo get_template_directory_uri(); ?>/docs/STRIPE.md" target="_blank">Ver guia completo</a>
        </p>
    </div>
    <?php
}

/**
 * Adiciona nonce para o checkout
 */
add_action('wp_enqueue_scripts', 'bazar_stripe_add_nonce');
function bazar_stripe_add_nonce() {
    if (is_user_logged_in()) {
        wp_localize_script('app', 'ajax_object', array_merge(
            (array) wp_localize_script('app', 'ajax_object', array()),
            array(
                'nonce_stripe_checkout' => wp_create_nonce('bazar_stripe_checkout')
            )
        ));
    }
}

