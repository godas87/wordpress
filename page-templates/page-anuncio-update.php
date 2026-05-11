<?php
/**
 * Template Name: Anuncio Update
 *
 * Sucesso pós-cadastro/edição: mensagem de aprovação + escolha Grátis (preview) ou Turbinar (checkout direto).
 */
get_template_part('template-parts/global/validacao');

$post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

if ($post_id < 1) {
  wp_safe_redirect(home_url('/meus-anuncios/'));
  exit;
}

$post = get_post($post_id);
if (
  !$post
  || $post->post_type !== 'post'
  || (int) $post->post_author !== (int) get_current_user_id()
) {
  wp_safe_redirect(home_url('/meus-anuncios/'));
  exit;
}

$url_preview = home_url('/?p=' . $post_id . '&preview=true');

$can_turbo = function_exists('bazar_can_offer_boost_checkout_on_success')
  && bazar_can_offer_boost_checkout_on_success($post_id);

$preco_normal = 0;
$promocao_ativa = false;
$promo_modal_btn_label = '';
$promo_subtitulo = '';
$terms_url = home_url('/termos-promocao-instagram/');
$promo_config = array();
$preco_promo = 0;
$desconto_percent = 0;
$promo_titulo = '';
$promo_descricao = '';
$aplica_desconto_checkout = false;

if (function_exists('bazar_destaque_get_promo_config')) {
  $promo_config = bazar_destaque_get_promo_config();
  $preco_normal = isset($promo_config['preco_normal']) ? (float) $promo_config['preco_normal'] : 0;
  $promocao_ativa = !empty($promo_config['promocao_ativa']);
  $promo_modal_btn_label = trim((string) ($promo_config['modal_promo_btn_label'] ?? ''));
  $promo_subtitulo = !empty($promo_config['subtitulo']) ? (string) $promo_config['subtitulo'] : '';
  $preco_promo = isset($promo_config['preco_desconto_newsletter']) ? (float) $promo_config['preco_desconto_newsletter'] : 0;
  $desconto_percent = isset($promo_config['desconto_percent']) ? (int) $promo_config['desconto_percent'] : 0;
  $promo_titulo = trim((string) ($promo_config['titulo'] ?? ''));
  $promo_descricao = trim((string) ($promo_config['descricao'] ?? ''));
  $aplica_desconto_checkout = !empty($promo_config['aplica_desconto_checkout']);
  if (!empty($promo_config['terms_url'])) {
    $terms_url = (string) $promo_config['terms_url'];
  }
} elseif (function_exists('bazar_destaque_get_preco')) {
  $preco_normal = (float) bazar_destaque_get_preco(false);
}

$promo_btn_text = $promo_modal_btn_label !== ''
  ? $promo_modal_btn_label
  : ($promo_subtitulo !== '' ? $promo_subtitulo : __('Impulsionar com desconto da promoção', 'bazar'));

$show_price_compare = $promocao_ativa && $aplica_desconto_checkout && $preco_normal > 0 && $preco_promo > 0 && $preco_promo < $preco_normal;

if (have_posts()) {
  while (have_posts()) {
    the_post();
    /**
     * Disparo único para analytics/pixels após sucesso do cadastro (centralização de eventos).
     *
     * @param int $post_id ID do anúncio recém-enviado.
     */
    do_action('bazar_anuncio_success_after_submit', $post_id);

    get_header();
    ?>

    <h1 class="d-none">
      <?php bloginfo('name'); ?> — <?php the_title(); ?>
    </h1>

    <div class="row align-center page">
      <div class="s-11 m-10 l-9 col bazar-plano-sucesso">

        <div class="box-content">

          <header class="bazar-plano-sucesso__masthead">
            <div class="bazar-plano-sucesso__masthead-main">
              <span class="bazar-plano-sucesso__masthead-icon" aria-hidden="true">
                <i class="fas fa-check"></i>
              </span>
              <div class="bazar-plano-sucesso__masthead-copy">
                <h2 class="bazar-plano-sucesso__masthead-title">
                  <?php esc_html_e('Anúncio enviado para aprovação', 'bazar'); ?>
                </h2>
                <p class="bazar-plano-sucesso__masthead-text">
                  <?php esc_html_e('Assim que o anúncio for aprovado, você receberá um e-mail de confirmação.', 'bazar'); ?>
                </p>
              </div>
            </div>
            <div class="bazar-plano-sucesso__masthead-next">
              <span class="bazar-plano-sucesso__masthead-label"><?php esc_html_e('Próximo passo', 'bazar'); ?></span>
              <p class="bazar-plano-sucesso__masthead-lead">
                <?php esc_html_e('Escolha um plano', 'bazar'); ?>
              </p>
            </div>
          </header>

          <div class="bazar-plano-sucesso__grid">

            <article class="bazar-plano-card bazar-plano-card--free" aria-labelledby="bazar-plano-free-title">

              <h4 id="bazar-plano-free-title" class="bazar-plano-card__name">
                <?php esc_html_e('Grátis', 'bazar'); ?>
              </h4>

              <div class="box">

                <p class="bazar-plano-card__tagline">
                  <?php esc_html_e('Perfeito se você não tem pressa. Sem custo e sem compromisso.', 'bazar'); ?>
                </p>

                <ul class="bazar-plano-card__list">
                  <li><?php esc_html_e('Anúncio entra na fila de moderação', 'bazar'); ?></li>
                  <li><?php esc_html_e('Visibilidade padrão após a publicação', 'bazar'); ?></li>
                  <li><?php esc_html_e('Pré-visualize como ficará na página do produto', 'bazar'); ?></li>
                </ul>

                <a href="<?php echo esc_url($url_preview); ?>" class="btn-default" target="_blank"
                  rel="noopener noreferrer">
                  <?php esc_html_e('Continuar com plano grátis', 'bazar'); ?>
                </a>

              </div>
            </article>

            <article class="bazar-plano-card bazar-plano-card--turbo" aria-labelledby="bazar-plano-turbo-title">
              <?php if ($can_turbo): ?>
                <span class="bazar-plano-card__badge"><?php esc_html_e('Mais visibilidade', 'bazar'); ?></span>
              <?php endif; ?>

              <h4 id="bazar-plano-turbo-title" class="bazar-plano-card__name">
                <?php esc_html_e('Impulsionar', 'bazar'); ?>
              </h4>

              <div class="box">

                <p class="bazar-plano-card__tagline">
                  <?php esc_html_e('Pagamento único. Seu anúncio ganha prioridade nas listagens e buscas até ser marcado como vendido.', 'bazar'); ?>
                </p>

                <ul class="bazar-plano-card__list">
                  <li><?php esc_html_e('Destaque nas buscas e listagens do site', 'bazar'); ?></li>
                  <li><?php esc_html_e('Mais contatos enquanto o anúncio estiver ativo', 'bazar'); ?></li>
                  <li><?php esc_html_e('Sem mensalidade — você paga só esta vez', 'bazar'); ?></li>
                </ul>

                <?php if ($can_turbo): ?>
                  <div class="bazar-plano-card__pricing">
                    <?php if ($promocao_ativa && ($promo_titulo !== '' || $promo_descricao !== '')): ?>
                      <?php if ($promo_titulo !== ''): ?>
                        <p class="bazar-plano-card__promo-headline"><?php echo esc_html($promo_titulo); ?></p>
                      <?php endif; ?>
                      <?php if ($promo_descricao !== ''): ?>
                        <p class="bazar-plano-card__promo-desc"><?php echo esc_html($promo_descricao); ?></p>
                      <?php endif; ?>
                    <?php else: ?>
                      <p class="bazar-plano-card__promo-headline"><?php esc_html_e('Garanta seu lugar no topo', 'bazar'); ?></p>
                      <p class="bazar-plano-card__promo-desc">
                        <?php esc_html_e('Quanto antes você impulsionar após publicar, mais tempo em destaque aproveita após a aprovação.', 'bazar'); ?>
                      </p>
                    <?php endif; ?>

                    <?php if ($show_price_compare): ?>
                      <div class="bazar-plano-card__price-row">
                        <?php if ($desconto_percent > 0): ?>
                          <span
                            class="bazar-plano-card__pill-off"><?php echo esc_html(sprintf(/* translators: %d: percent */ __('%d%% OFF', 'bazar'), $desconto_percent)); ?></span>
                        <?php endif; ?>
                        <span
                          class="bazar-plano-card__price-old">R$&nbsp;<?php echo esc_html(number_format($preco_normal, 2, ',', '.')); ?></span>
                        <span
                          class="bazar-plano-card__price-promo">R$&nbsp;<?php echo esc_html(number_format($preco_promo, 2, ',', '.')); ?></span>
                      </div>
                    <?php elseif ($preco_normal > 0): ?>
                      <div class="bazar-plano-card__price-row">
                        <span class="bazar-plano-card__price-label"><?php esc_html_e('A partir de', 'bazar'); ?></span>
                        <span
                          class="bazar-plano-card__price-single">R$&nbsp;<?php echo esc_html(number_format($preco_normal, 2, ',', '.')); ?></span>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div id="bazar-plano-checkout">

                    <?php if ($promocao_ativa): ?>
                      <button type="button" id="btn-plano-checkout-promo" class="btn-promo btn-checkout-promo"
                        data-checkout-tipo="promo" data-anuncio-id="<?php echo esc_attr((string) $post_id); ?>">
                        <i class="fab fa-instagram" aria-hidden="true"></i>
                        <?php echo esc_html($promo_btn_text); ?>
                      </button>

                      <button type="button" id="btn-plano-checkout-simples"
                        class="btn-default btn-checkout-stripe btn-checkout-simples" data-checkout-tipo="simples"
                        data-anuncio-id="<?php echo esc_attr((string) $post_id); ?>">
                        <i class="fas fa-rocket" aria-hidden="true"></i>
                        <?php
                        echo esc_html(
                          sprintf(
                            /* translators: %s: preço formatado (ex.: 50,00) */
                            __('Impulsionar sem desconto (R$ %s)', 'bazar'),
                            number_format($preco_normal, 2, ',', '.')
                          )
                        );
                        ?>
                      </button>

                    <?php else: ?>
                      <button type="button" id="btn-plano-checkout-simples"
                        class="btn-promo btn-checkout-stripe btn-checkout-simples" data-checkout-tipo="simples"
                        data-anuncio-id="<?php echo esc_attr((string) $post_id); ?>">
                        <i class="fas fa-rocket" aria-hidden="true"></i>
                        <?php
                        echo esc_html(
                          sprintf(
                            /* translators: %s: preço formatado */
                            __('Impulsionar por R$ %s', 'bazar'),
                            number_format($preco_normal, 2, ',', '.')
                          )
                        );
                        ?>
                      </button>
                    <?php endif; ?>

                    <p class="bazar-plano-sucesso__legal">
                      <?php
                      echo wp_kses(
                        sprintf(
                          /* translators: %s: URL dos termos */
                          __('Para mais informações, acesse os <a href="%s" target="_blank" rel="noopener noreferrer">Termos Legais</a>.', 'bazar'),
                          esc_url($terms_url)
                        ),
                        array(
                          'a' => array(
                            'href' => array(),
                            'target' => array(),
                            'rel' => array(),
                          ),
                        )
                      );
                      ?>
                    </p>

                  </div>

                <?php else: ?>

                  <p class="bazar-plano-sucesso__unavailable mb-0">
                    <?php esc_html_e('O impulsionamento não está disponível para este anúncio no momento (por exemplo, já pago, em destaque ou vendido).', 'bazar'); ?>
                  </p>

                <?php endif; ?>

              </div>
            </article>

          </div>

          <div class="text-center pt-4">
            <?php get_template_part('template-parts/btn/google-meu-negocios'); ?>
          </div>

        </div><!-- /box-content -->

      </div>
    </div>

    <script>
      window.__BAZAR_PostId = <?php echo (int) $post_id; ?>;
    </script>

    <?php
    get_footer();
  }
}
