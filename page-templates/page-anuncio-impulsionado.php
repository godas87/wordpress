<?php
/**
 * Template Name: Anúncio Impulsionado
 *
 * Página de finalização e validação após pagamento.
 * Exibe mensagens de sucesso, erro, cancelamento ou suporte.
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

get_header();

// CTA pós-pagamento: seguir Instagram
$instagram_url = '';
if (function_exists('bazar_destaque_get_promo_config')) {
  $promo_config = bazar_destaque_get_promo_config();
  if (!empty($promo_config['link'])) {
    $instagram_url = (string) $promo_config['link'];
  }
}
if ($instagram_url === '') {
  $instagram_url = 'https://XXXXXX/';
}

// Parâmetros da URL
$payment_status = isset($_GET['payment']) ? sanitize_text_field($_GET['payment']) : '';
$session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
$anuncio_id = isset($_GET['anuncio']) ? intval($_GET['anuncio']) : 0;
$cancelado = !empty($_GET['cancelado']);

// Estado
$processed = false;
$error_message = '';
$anuncio = null;
$is_destaque = false;
$user_id = get_current_user_id();
$show_support_message = false;

/**
 * Carrega dados do anúncio e define $anuncio e $is_destaque.
 * Usa helpers com cache quando disponíveis.
 */
$load_anuncio_data = function ($post_id) use (&$anuncio, &$is_destaque) {
  $anuncio = null;
  $is_destaque = false;
  if ($post_id <= 0) {
    return;
  }
  if (function_exists('bazar_stripe_get_product_data_cached')) {
    $product_data = bazar_stripe_get_product_data_cached($post_id);
  } elseif (function_exists('bazar_get_product_card_data')) {
    $product_data = bazar_get_product_card_data($post_id);
  } else {
    $product_data = null;
  }
  if ($product_data && !empty($product_data['status_data'])) {
    $is_destaque = !empty($product_data['status_data']['is_destaque']);
    $anuncio = isset($product_data['post']) ? $product_data['post'] : null;
    return;
  }
  $post = function_exists('bazar_stripe_get_post_cached')
    ? bazar_stripe_get_post_cached($post_id)
    : get_post($post_id);
  if ($post && $post->post_type === 'post') {
    $anuncio = $post;
    $status = function_exists('bazar_get_anuncio_status')
      ? bazar_get_anuncio_status($post_id)
      : array('is_destaque' => false);
    $is_destaque = !empty($status['is_destaque']);
  }
};

// Resolver session_id e anuncio_id (URL ou user_meta)
$effective_session_id = $session_id;
$effective_anuncio_id = $anuncio_id;
if (empty($effective_session_id) && $user_id && function_exists('bazar_stripe_get_checkout_data_from_user_meta')) {
  $checkout_data = bazar_stripe_get_checkout_data_from_user_meta($user_id);
  if (!empty($checkout_data['session_id'])) {
    $effective_session_id = $checkout_data['session_id'];
    if (!empty($checkout_data['post_id'])) {
      $effective_anuncio_id = intval($checkout_data['post_id']);
    }
  }
}

// Processar pagamento quando há session e é cenário de sucesso ou recuperação
if (!empty($effective_session_id) && function_exists('bazar_stripe_process_payment_success')) {
  $should_process = ($payment_status === 'success')
    || (empty($payment_status) && empty($session_id) && empty($anuncio_id));
  if ($should_process) {
    $processed = bazar_stripe_process_payment_success($effective_session_id, $effective_anuncio_id);
    if ($processed && $effective_anuncio_id <= 0 && $user_id && function_exists('bazar_stripe_get_checkout_data_from_user_meta')) {
      $checkout_data = bazar_stripe_get_checkout_data_from_user_meta($user_id);
      if (!empty($checkout_data['post_id'])) {
        $effective_anuncio_id = intval($checkout_data['post_id']);
      }
    }
  }
}

if (!$processed && $payment_status === 'success') {
  $error_message = __('Não foi possível confirmar o pagamento nesta página. Se o valor foi cobrado, guarde o comprovante e fale com o suporte.', 'bazar');
}
if (!$processed && $payment_status === 'success' && empty($session_id)) {
  $show_support_message = true;
}
if (!$processed && empty($payment_status) && empty($session_id) && empty($anuncio_id)) {
  $show_support_message = true;
}

// Definir anuncio_id final e carregar dados do anúncio uma vez
if ($effective_anuncio_id > 0) {
  $anuncio_id = $effective_anuncio_id;
}
if ($anuncio_id > 0) {
  $load_anuncio_data($anuncio_id);
}

$destaque_aguarda_verificacao = false;
if ($anuncio_id > 0 && defined('BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO')) {
  $destaque_aguarda_verificacao = get_post_meta($anuncio_id, BAZAR_META_DESTAQUE_AGUARDA_VERIFICACAO, true) === '1';
}

$minha_conta_url = home_url('/minha-conta/');
$anuncio_post_status = ($anuncio_id > 0 && function_exists('get_post_status')) ? get_post_status($anuncio_id) : '';
$anuncio_url_ver = '';
if ($anuncio_id > 0) {
  if ($anuncio_post_status === 'publish') {
    $anuncio_url_ver = get_permalink($anuncio_id);
  } else {
    $anuncio_url_ver = add_query_arg(
      array(
        'p' => $anuncio_id,
        'preview' => 'true',
      ),
      home_url('/')
    );
  }
}

if (have_posts()):
  while (have_posts()):
    the_post();
    ?>
    <h1 class="d-none"><?php bloginfo('name'); ?> - <?php the_title(); ?></h1>
    <div class="row align-center page">
      <div class="s-11 l-9 col">
        <div class="box-content">
          <div class="msg-box pt-2 pb-1">

            <?php if ($processed && $is_destaque): ?>
              <i class="fas fa-check-circle green" style="font-size:3rem;"></i>
              <h3 class="pt-1 pb-2"><?php esc_html_e('Pagamento confirmado', 'bazar'); ?></h3>
              <?php if ($anuncio) : ?>
                <p>
                  <?php esc_html_e('Seu anúncio', 'bazar'); ?>
                  <strong>&ldquo;<?php echo esc_html($anuncio->post_title); ?>&rdquo;</strong>
                  <?php esc_html_e('está impulsionado: o pagamento foi confirmado e o destaque já está ativo.', 'bazar'); ?>
                </p>
                <?php if ($anuncio_post_status === 'publish') : ?>
                  <p class="mb-1"><?php esc_html_e('Ele aparecerá priorizado nas buscas e listagens enquanto estiver ativo e não estiver vendido.', 'bazar'); ?></p>
                <?php elseif (in_array($anuncio_post_status, array('pending', 'draft', 'future'), true)) : ?>
                  <p class="mb-1"><?php esc_html_e('Quando a equipe publicar o anúncio, ele passará a aparecer em destaque nas buscas conforme as regras do site.', 'bazar'); ?></p>
                <?php else : ?>
                  <p class="mb-1"><?php esc_html_e('Assim que o anúncio estiver publicado, o destaque valerá nas buscas e listagens.', 'bazar'); ?></p>
                <?php endif; ?>
              <?php else : ?>
                <p><?php esc_html_e('Seu pagamento foi processado com sucesso e o destaque foi registrado.', 'bazar'); ?></p>
              <?php endif; ?>
              <?php if ($anuncio_url_ver !== '') : ?>
                <a href="<?php echo esc_url($anuncio_url_ver); ?>" class="button mb-1" title="<?php esc_attr_e('Ver meu anúncio', 'bazar'); ?>">
                  <i class="fas fa-eye white"></i> <?php esc_html_e('Ver meu anúncio', 'bazar'); ?>
                </a>
              <?php endif; ?>
              <a href="<?php echo esc_url($instagram_url); ?>" target="_blank" rel="noopener noreferrer"
                class="alt button mb-1" title="<?php esc_attr_e('Seguir no Instagram', 'bazar'); ?>">
                <i class="fab fa-instagram"></i> <?php esc_html_e('Seguir no Instagram', 'bazar'); ?>
              </a>
              <hr />
              <?php get_template_part('template-parts/btn/google-meu-negocios'); ?>

            <?php elseif ($processed && $destaque_aguarda_verificacao): ?>
              <i class="fas fa-check-circle green" style="font-size:3rem;"></i>
              <h3 class="pt-1 pb-2"><?php esc_html_e('Pagamento confirmado', 'bazar'); ?></h3>
              <?php if ($anuncio) : ?>
                <p>
                  <?php
                  echo wp_kses_post(
                    sprintf(
                      /* translators: 1: título do anúncio, 2: URL Minha Conta */
                      __(
                        'O pagamento do impulsionamento do anúncio %1$s foi confirmado. Para <strong>liberar o destaque nas buscas</strong>, valide seu CPF e complete os dados obrigatórios em <a href="%2$s">Minha conta</a>. O anúncio segue o fluxo normal de aprovação da equipe.',
                        'bazar'
                      ),
                      '<strong>' . esc_html($anuncio->post_title) . '</strong>',
                      esc_url($minha_conta_url)
                    )
                  );
                  ?>
                </p>
              <?php else : ?>
                <p>
                  <?php
                  echo wp_kses_post(
                    sprintf(
                      /* translators: %s: URL Minha Conta */
                      __(
                        'Pagamento recebido. Para <strong>ativar o destaque nas buscas</strong>, valide seu CPF e complete os dados em <a href="%s">Minha conta</a>. Depois disso, o sistema aplicará o impulsionamento aos anúncios elegíveis já publicados.',
                        'bazar'
                      ),
                      esc_url($minha_conta_url)
                    )
                  );
                  ?>
                </p>
              <?php endif; ?>
              <a href="<?php echo esc_url($minha_conta_url); ?>" class="button mb-1" title="<?php esc_attr_e('Minha conta', 'bazar'); ?>">
                <i class="fas fa-user white"></i> <?php esc_html_e('Ir para Minha conta', 'bazar'); ?>
              </a>
              <?php if ($anuncio_url_ver !== '') : ?>
                <a href="<?php echo esc_url($anuncio_url_ver); ?>" class="alt button mb-1" title="<?php esc_attr_e('Ver meu anúncio', 'bazar'); ?>">
                  <i class="fas fa-eye"></i> <?php esc_html_e('Ver meu anúncio', 'bazar'); ?>
                </a>
              <?php endif; ?>
              <a href="<?php echo esc_url($instagram_url); ?>" target="_blank" rel="noopener noreferrer"
                class="alt button mb-1" title="<?php esc_attr_e('Seguir no Instagram', 'bazar'); ?>">
                <i class="fab fa-instagram"></i> <?php esc_html_e('Seguir no Instagram', 'bazar'); ?>
              </a>
              <a href="<?php echo esc_url(home_url('/meus-anuncios/')); ?>" class="alt button mb-1" title="Meus anúncios">
                <i class="fas fa-th"></i> Meus anúncios
              </a>
              <hr />
              <?php get_template_part('template-parts/btn/google-meu-negocios'); ?>

            <?php elseif (!empty($error_message) || ($payment_status === 'success' && !$processed)): ?>
              <i class="fas fa-exclamation-circle red" style="font-size:3rem;"></i>
              <h3 class="pt-1"><?php esc_html_e('Não foi possível concluir aqui', 'bazar'); ?></h3>
              <p>
                <?php
                echo esc_html(
                  $error_message
                    ? $error_message
                    : __('Não foi possível confirmar o pagamento. Tente novamente ou entre em contato com o suporte.', 'bazar')
                );
                ?>
              </p>
              <a href="<?php echo esc_url(get_bloginfo('url') . '/meus-anuncios/'); ?>" class="alt button mb-1"
                title="Voltar para Meus Anúncios">
                <i class="fas fa-arrow-left"></i> Voltar para Meus Anúncios
              </a>

            <?php elseif ($cancelado || $payment_status === 'canceled'): ?>
              <i class="fas fa-exclamation-triangle red" style="font-size:3rem;"></i>
              <h3 class="pt-1"><?php esc_html_e('Pagamento cancelado', 'bazar'); ?></h3>
              <p><?php esc_html_e('O checkout foi fechado antes da confirmação. Nenhum valor foi cobrado. Você pode tentar de novo quando quiser.', 'bazar'); ?></p>
              <?php if ($anuncio_id > 0): ?>
                <?php
                // Anúncios pending sem aprovação ADM: validacao-single.php redireciona a single para /bicicletas/
                // se não houver preview=true. Mesmo padrão da página de sucesso pós-cadastro.
                $url_tentar_novamente = add_query_arg(
                  array(
                    'p' => $anuncio_id,
                    'preview' => 'true',
                    'impulsionar' => '1',
                  ),
                  home_url('/')
                );
                ?>
                <a href="<?php echo esc_url($url_tentar_novamente); ?>" class="button mb-1" title="Tentar Novamente">
                  <i class="fas fa-redo"></i> Tentar Novamente
                </a>
              <?php endif; ?>
              <a href="<?php echo esc_url(get_bloginfo('url') . '/meus-anuncios/'); ?>" class="alt button mb-1"
                title="Voltar para Meus Anúncios">
                <i class="fas fa-arrow-left"></i> Voltar para Meus Anúncios
              </a>

            <?php elseif ($show_support_message): ?>
              <i class="fas fa-info-circle red" style="font-size:3rem;"></i>
              <h3 class="pt-1"><?php esc_html_e('Precisa de ajuda com o pagamento?', 'bazar'); ?></h3>
              <p>
                <?php esc_html_e('Se o pagamento foi concluído no cartão mas o destaque ainda não aparece, pode ser que falte validar o CPF em Minha conta ou que o anúncio ainda esteja em moderação. Se após isso continuar diferente do esperado, fale com o suporte com o comprovante em mãos.', 'bazar'); ?>
              </p>
              <a href="#" class="button mb-1 open-contato" title="Entrar em contato">
                <i class="fas fa-envelope white"></i> Entrar em Contato
              </a>
              <a href="<?php echo esc_url(get_bloginfo('url') . '/meus-anuncios/'); ?>" class="alt button mb-1"
                title="Voltar para Meus Anúncios">
                <i class="fas fa-arrow-left"></i> Voltar para Meus Anúncios
              </a>

            <?php else: ?>
              <i class="fas fa-info-circle red" style="font-size:3rem;"></i>
              <h3 class="pt-1"><?php esc_html_e('Status do pagamento', 'bazar'); ?></h3>
              <p><?php esc_html_e('Abra esta página ao voltar do checkout do cartão para ver se o pagamento foi confirmado. Se chegou aqui sem finalizar uma compra, use Meus anúncios para gerenciar seus anúncios.', 'bazar'); ?></p>
              <a href="<?php echo esc_url(get_bloginfo('url') . '/meus-anuncios/'); ?>" class="alt button mb-1"
                title="Voltar para Meus Anúncios">
                <i class="fas fa-arrow-left"></i> Voltar para Meus Anúncios
              </a>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
    <?php
  endwhile;
endif;
get_footer();