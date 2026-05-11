<?php if (bazar_is_production()): ?>
  <?php bazar_adsense_script_once(); ?>
  <div class="x1x2xe x1x2xe-top">
    <div class="row">
      <div class="s-12 col">
        <?php if (!wp_is_mobile()): ?>
          <ins class="adsbygoogle" style="display:inline-block;width:728px;height:90px"
            data-ad-client="ca-pub-9613173072002426" data-ad-slot="<?php echo esc_attr(bazar_adsense_top()); ?>"></ins>
        <?php else: ?>
          <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-9613173072002426"
            data-ad-slot="<?php echo esc_attr(bazar_adsense_sidebar()); ?>" data-ad-format="auto"
            data-full-width-responsive="true"></ins>
        <?php endif; ?>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
      </div><!-- /bxad -->
    </div>
  </div>
<?php endif; ?>