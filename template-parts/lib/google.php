<script async src="https://www.googletagmanager.com/gtag/js?id=XXXXXX"></script>
<script async src="https://www.googletagmanager.com/gtag/js?id=XXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  gtag('js', new Date());
  gtag('config', 'XXXXXX');
  gtag('config', 'XXXXXX');
  <?php if (is_page(array('anunciar'))): ?>
    // Evento de Visualização de Página de Anúncio para o GA4
    gtag('event', 'page_view_anunciar', {
      'page_title': 'Página de Criar Anúncio'
    });
    // Conversão específica do Google Ads para esta página
    gtag('event', 'conversion', {
      'send_to': 'XXXXXX'
    });
  <?php endif; ?>
</script>