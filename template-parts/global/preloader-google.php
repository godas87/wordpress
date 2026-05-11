<?php
// Somente em produção
if (!bazar_is_production()): ?>
  <link rel="preconnect" href="https://www.google.com">
  <link rel="preconnect" href="https://www.gstatic.com" crossorigin>
  <link rel="dns-prefetch" href="//www.google-analytics.com" />
  <script src="https://www.google.com/recaptcha/api.js?render=XXXXXX"></script>
  <?php if (!is_singular('web-stories')):   //type="font/woff2" ?>
    <link rel="preload" href="<?php bloginfo('url') ?>/src/fonts/Roboto-Bold.woff2" as="font" crossorigin>
    <link rel="preload" href="<?php bloginfo('url') ?>/src/fonts/Roboto-Regular.woff2" as="font" crossorigin>
    <link rel="preload" href="<?php bloginfo('url') ?>/src/fonts/Roboto-Thin.woff2" as="font" crossorigin>
    <link rel="preload" href="<?php bloginfo('url') ?>/src/fonts/robotocondensed-bold.woff2" as="font" crossorigin>
    <link rel="preload" href="<?php bloginfo('url') ?>/src/fonts/robotocondensed-light.woff2" as="font" crossorigin>
  <?php endif; ?>
<?php endif; ?>