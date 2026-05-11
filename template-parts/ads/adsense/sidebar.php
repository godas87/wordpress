<?php if (bazar_is_production()): ?>
  <?php bazar_adsense_script_once(); ?>
  <div class="sticky alt text-center">
    <div class="x1x2xe">
      <div class="row">
        <div class="s-12 col">
          <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-9613173072002426"
            data-ad-slot="<?php echo esc_attr(bazar_adsense_sidebar()); ?>" data-ad-format="auto"
            data-full-width-responsive="true"></ins>
          <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>