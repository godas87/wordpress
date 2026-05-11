<script>
  !function (f, b, e, v, n, t, s) {
    if (f.fbq) return; n = f.fbq = function () {
      n.callMethod ?
        n.callMethod.apply(n, arguments) : n.queue.push(arguments)
    };
    if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0';
    n.queue = []; t = b.createElement(e); t.async = !0;
    t.src = v; s = b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t, s)
  }(window, document, 'script',
    'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', 'XXXXXX');
  fbq('track', 'PageView');
</script><noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=XXXXXX&ev=PageView&noscript=1" /></noscript>
<?php
$registration_post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

if (!function_exists('bazar_pixel_should_track_complete_registration')) {
  /**
   * Gatilho resiliente para CompleteRegistration.
   *
   * Mantém o fluxo canônico e adiciona fallback por post_id em rotas de anúncio,
   * evitando depender de um único slug de sucesso.
   *
   * @param int $post_id
   * @param string $request_uri
   * @return bool
   */
  function bazar_pixel_should_track_complete_registration($post_id, $request_uri = '')
  {
    $post_id = (int) $post_id;
    // Sempre exige ID do anúncio: UTMs vêm do post (bazar_ad_utm_*) e do evento faz sentido só após envio real
    if ($post_id <= 0) {
      return false;
    }

    if (is_page(array('anuncio-atualizado'))) {
      return true;
    }

    if (function_exists('bazar_get_updated_url')) {
      $expected_url = bazar_get_updated_url($post_id);
      $expected_path = wp_parse_url($expected_url, PHP_URL_PATH);
      $current_path = wp_parse_url((string) $request_uri, PHP_URL_PATH);
      if (!empty($expected_path) && !empty($current_path) && untrailingslashit($expected_path) === untrailingslashit($current_path)) {
        return true;
      }
    }

    // Fallback: post_id de anúncio na URL + rota de anúncio (exceto telas de edição/inserção).
    $is_ad_post = get_post_type($post_id) === 'post';
    $is_ad_route = strpos((string) $request_uri, 'anuncio') !== false;
    $is_excluded_page = is_page(array('anuncio-inserir', 'anuncio-editar'));
    if ($is_ad_post && $is_ad_route && !$is_excluded_page) {
      return true;
    }

    return false;
  }
}

$track_complete_registration = bazar_pixel_should_track_complete_registration($registration_post_id, $request_uri);

?>
<?php if ($track_complete_registration): ?>
  <?php $registration_utm = bazar_pixel_get_utm_context($registration_post_id, false); ?>
  <?php $registration_event_id = 'cr_' . md5((string) $registration_post_id . '|' . (string) get_current_user_id()); ?>
  <script>
    (function () {
      var eventId = '<?php echo esc_js($registration_event_id); ?>';
      try {
        var dedupeKey = 'bazar_fb_cr_' + eventId;
        if (window.sessionStorage && sessionStorage.getItem(dedupeKey) === '1') {
          return;
        }
        fbq('track', 'CompleteRegistration', {
          content_name: 'Cadastro de Anúncio',
          status: 'sucesso',
          utm_source: '<?php echo esc_js($registration_utm['utm_source']); ?>',
          utm_medium: '<?php echo esc_js($registration_utm['utm_medium']); ?>',
          utm_campaign: '<?php echo esc_js($registration_utm['utm_campaign']); ?>',
          utm_content: '<?php echo esc_js($registration_utm['utm_content']); ?>'
        }, {
          eventID: eventId
        });
        if (window.sessionStorage) {
          sessionStorage.setItem(dedupeKey, '1');
        }
      } catch (e) {
        fbq('track', 'CompleteRegistration', {
          content_name: 'Cadastro de Anúncio',
          status: 'sucesso',
          utm_source: '<?php echo esc_js($registration_utm['utm_source']); ?>',
          utm_medium: '<?php echo esc_js($registration_utm['utm_medium']); ?>',
          utm_campaign: '<?php echo esc_js($registration_utm['utm_campaign']); ?>',
          utm_content: '<?php echo esc_js($registration_utm['utm_content']); ?>'
        }, {
          eventID: eventId
        });
      }
    })();
  </script>
<?php endif; ?>

<?php
// Purchase: sucesso do impulsionamento — dados em app/functions/pixel-meta-events.php.
$bazar_boost_purchase_pixel = bazar_pixel_build_boost_purchase_payload();

if ($bazar_boost_purchase_pixel) :
  $bazar_boost_purchase_fbq_json = wp_json_encode(
    array(
      'eventId' => $bazar_boost_purchase_pixel['event_id'],
      'payload' => $bazar_boost_purchase_pixel['fbq_payload'],
    )
  );
  ?>
  <script>
    (function () {
      var cfg = <?php echo $bazar_boost_purchase_fbq_json; ?>;
      if (!cfg || !cfg.eventId || !cfg.payload || typeof fbq !== 'function') {
        return;
      }
      try {
        var dedupeKey = 'bazar_fb_purchase_' + cfg.eventId;
        if (window.sessionStorage && sessionStorage.getItem(dedupeKey) === '1') {
          return;
        }
        fbq('track', 'Purchase', cfg.payload, { eventID: cfg.eventId });
        if (window.sessionStorage) {
          sessionStorage.setItem(dedupeKey, '1');
        }
      } catch (e) {
        fbq('track', 'Purchase', cfg.payload, { eventID: cfg.eventId });
      }
    })();
  </script>
<?php endif; ?>

<?php
// Abandono do checkout de impulsionamento: retorno Stripe sem pagamento (cancel_url).
$is_boost_canceled_page = is_page(array('anuncio-impulsionado'))
  && isset($_GET['payment'])
  && sanitize_text_field((string) $_GET['payment']) === 'canceled';

if ($is_boost_canceled_page && function_exists('bazar_destaque_get_promo_config')):

  $canceled_anuncio_id = isset($_GET['anuncio']) ? (int) $_GET['anuncio'] : 0;
  $canceled_uid = (int) get_current_user_id();
  $canceled_ok = false;
  if ($canceled_anuncio_id > 0 && $canceled_uid > 0) {
    $canceled_post = get_post($canceled_anuncio_id);
    $canceled_ok = $canceled_post
      && $canceled_post->post_type === 'post'
      && (int) $canceled_post->post_author === $canceled_uid;
  }

  if ($canceled_ok):
    $promo_cancel = bazar_destaque_get_promo_config();
    $cancel_value = isset($promo_cancel['preco_normal']) ? (float) $promo_cancel['preco_normal'] : 47.90;
    $preco_desc_cancel = isset($promo_cancel['preco_desconto_newsletter'])
      ? (float) $promo_cancel['preco_desconto_newsletter']
      : $cancel_value;
    $desc_pct_cancel = isset($promo_cancel['desconto_percent']) ? (int) $promo_cancel['desconto_percent'] : 0;
    $aplica_chk_cancel = !empty($promo_cancel['aplica_desconto_checkout']);
    if ($aplica_chk_cancel && $desc_pct_cancel >= 50 && $preco_desc_cancel > 0) {
      $cancel_value = $preco_desc_cancel;
    }

    $cancel_utm = bazar_pixel_get_utm_context($canceled_anuncio_id, true);
    $cancel_event_id = 'boost_cancel_' . md5((string) $canceled_anuncio_id . '|' . (string) $canceled_uid);
    ?>
  <script>
    (function () {
      var eventId = '<?php echo esc_js($cancel_event_id); ?>';
      var payload = {
        value: <?php echo esc_js(number_format((float) $cancel_value, 2, '.', '')); ?>,
        currency: 'BRL',
        content_name: 'Impulsionamento — checkout cancelado',
        content_ids: ['<?php echo esc_js((string) $canceled_anuncio_id); ?>'],
        content_type: 'product',
        utm_source: '<?php echo esc_js($cancel_utm['utm_source']); ?>',
        utm_medium: '<?php echo esc_js($cancel_utm['utm_medium']); ?>',
        utm_campaign: '<?php echo esc_js($cancel_utm['utm_campaign']); ?>',
        utm_content: '<?php echo esc_js($cancel_utm['utm_content']); ?>'
      };
      try {
        var dedupeKey = 'bazar_fb_boost_cancel_' + eventId;
        if (window.sessionStorage && sessionStorage.getItem(dedupeKey) === '1') {
          return;
        }
        fbq('trackCustom', 'BoostCheckoutCanceled', payload, { eventID: eventId });
        if (window.sessionStorage) {
          sessionStorage.setItem(dedupeKey, '1');
        }
      } catch (e) {
        fbq('trackCustom', 'BoostCheckoutCanceled', payload, { eventID: eventId });
      }
    })();
  </script>
    <?php
  endif;
endif;
?>