<?php
$user_id = get_current_user_id();
$is_user_logged_in = is_user_logged_in();
$promocao_ativa = false;

// Obter ID do anúncio atual (se estiver na single)
$current_post_id = is_singular() ? get_the_ID() : 0;

// URL dos termos da promoção vigente (definida no painel Promo)
$terms_url = '';

// Verificar se deve abrir o modal automaticamente
$open_modal = false;
$is_guest_cta = false; // Flag para sinalizar que é CTA para não logados
if (isset($_GET['impulsionar']) && $_GET['impulsionar'] === '1' && $current_post_id > 0) {
  // Verificar se usuário está logado
  if ($is_user_logged_in) {
    // Verificar se usuário é o autor do anúncio
    $post = get_post($current_post_id);
    if ($post && intval($post->post_author) === intval($user_id)) {
      if (function_exists('bazar_can_boost_anuncio')) {
        $result_boost = bazar_can_boost_anuncio($current_post_id, $user_id);
        if ($result_boost['can']) {
          $open_modal = true;
        } elseif (
          get_post_status($current_post_id) === 'pending'
          && function_exists('bazar_can_offer_boost_checkout_on_success')
          && bazar_can_offer_boost_checkout_on_success($current_post_id, $user_id)
        ) {
          // Mesma regra da página de sucesso: checkout com anúncio ainda não publicado
          $open_modal = true;
        }
      }
    }
  } else {
    // Para não logados: abrir modal quando vierem do link do email (?impulsionar=1)
    // Após login, o sistema validará se é autor e pode impulsionar
    $open_modal = true;
    $is_guest_cta = true;
  }
}

$modal_class = $open_modal ? 'modal modal-impulsionar open' : 'modal modal-impulsionar';
// Adicionar data-attribute para sinalizar guest CTA ao JS
$modal_data_attr = $is_guest_cta ? ' data-impulsionar-guest-cta="1"' : '';

// Obter preços e dados da promoção vigente
$preco_normal = 0;
$promo_link = '';
$promo_subtitulo = '';
$promo_modal_btn_label = '';
if (function_exists('bazar_destaque_get_promo_config')) {
  $promo_config = bazar_destaque_get_promo_config();
  $preco_normal = $promo_config['preco_normal'];
  $promocao_ativa = !empty($promo_config['promocao_ativa']);
  $promo_link = !empty($promo_config['link']) ? $promo_config['link'] : '';
  $promo_subtitulo = !empty($promo_config['subtitulo'])
    ? $promo_config['subtitulo']
    : '';
  $promo_modal_btn_label = trim((string) ($promo_config['modal_promo_btn_label'] ?? ''));
  $terms_url = !empty($promo_config['terms_url'])
    ? $promo_config['terms_url']
    : home_url('/termos-promocao-instagram/');
} elseif (function_exists('bazar_destaque_get_preco')) {
  $preco_normal = bazar_destaque_get_preco(false);
}
if (empty($terms_url)) {
  $terms_url = home_url('/termos-promocao-instagram/');
}
$rand = rand(1, 3);
?>
<div id="modal-impulsionar" class="<?php echo esc_attr($modal_class); ?>" <?php echo $modal_data_attr; ?>>
  <div class="modal-content">
    <button type="button" class="close modal-close modal-close-btn">
      <i class="fas fa-times"></i>
    </button>

    <div class="impulsionar-loading-overlay" aria-hidden="true">
      <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
      <span><?php _e('Carregando...', 'bazar'); ?></span>
    </div>

    <div class="impulsionar-body">
      <div class="impulsionar-image">
        <img
          src="<?php echo esc_url(get_template_directory_uri() . '/assets/imgs/content/cta-impulsionar-' . $rand . '.jpg'); ?>"
          alt="Impulsionar Anúncio" title="Impulsionar Anúncio">
      </div>

      <div class="modal-bx">

        <h2 class="impulsionar-title bold">Impulsionar Anúncio</h2>

        <div class="boost-pricing">
          <p class="pricing-main">
            <strong><?php esc_html_e('Por apenas:', 'bazar'); ?></strong>
            <span class="price-normal">R$ <?php echo esc_html(number_format($preco_normal, 2, ',', '.')); ?></span>
          </p>
        </div>

        <ul class="impulsionar-benefits">
          <li><i class="fas fa-check"></i> Pagamento <b>único.</b> Até vender!</li>
          <li><i class="fas fa-check"></i> Apareça no topo dos resultados de busca</li>
          <li><i class="fas fa-check"></i> Aumente em até 5x o número de visualizações</li>
          <li><i class="fas fa-check"></i> Destaque permanente enquanto o anúncio estiver ativo</li>
        </ul>

        <!-- Informações do anúncio serão carregadas dinamicamente via AJAX -->
        <div class="impulsionar-anuncio-info" style="display: none;"></div>

        <div class="impulsionar-buttons" style="display: none;">
          <?php if ($promocao_ativa): ?>
            <button type="button" id="btn-checkout-promo-instagram" class="button primary btn-checkout-promo"
              data-checkout-tipo="promo" data-anuncio-id="<?php echo esc_attr($current_post_id); ?>" <?php if (!$is_user_logged_in): ?> data-requires-login="1" <?php
                     if ($current_post_id > 0) {
                       $redirect_url = add_query_arg('impulsionar', '1', get_permalink($current_post_id));
                       echo 'data-login-redirect="' . esc_attr($redirect_url) . '"';
                     }
                     ?>   <?php endif; ?>>
              <i class="fab fa-instagram"></i>
              <?php
              $texto_botao_promo = $promo_modal_btn_label !== ''
                ? $promo_modal_btn_label
                : ($promo_subtitulo !== ''
                  ? $promo_subtitulo
                  : __('Impulsionar com desconto (promoção)', 'bazar'));
              echo esc_html($texto_botao_promo);
              ?>
            </button>
          <?php endif; ?>

          <button type="button" id="btn-checkout-stripe"
            class="button secondary btn-checkout-stripe btn-checkout-simples" data-checkout-tipo="simples"
            data-anuncio-id="<?php echo esc_attr($current_post_id); ?>" <?php if (!$is_user_logged_in): ?>
              data-requires-login="1" <?php
              if ($current_post_id > 0) {
                $redirect_url = add_query_arg('impulsionar', '1', get_permalink($current_post_id));
                echo 'data-login-redirect="' . esc_attr($redirect_url) . '"';
              }
              ?> <?php endif; ?>>
            <i class="fas fa-rocket"></i>
            <?php
            echo esc_html(
              sprintf(
                /* translators: %s: preço formatado (ex.: 47,90) */
                __('Impulsionar sem desconto', 'bazar'),
                number_format($preco_normal, 2, ',', '.')
              )
            );
            ?>
          </button>

        </div>
        <small class="legal-terms">
          Para mais informações, acesse os <a href="<?php echo esc_url($terms_url); ?>" target="_blank"
            title="<?php esc_attr_e('Termos da promoção', 'bazar'); ?>" class="black">Termos Legais</a>
        </small>
      </div>
    </div><!-- /.impulsionar-body -->

  </div>
  <!-- Overlay escuro de fundo -->
  <div class="modal-overlay"></div>
</div>