<div class="promo-banner">
    <div class="container">
        <div class="promo-content">
            <span class="promo-badge">🎉 PROMOÇÃO</span>
            <h2 class="promo-title">
                <?php
                $desconto_percent = 10;
                $promo_titulo = '';
                $promo_subtitulo = '';
                if (function_exists('bazar_destaque_get_promo_config')) {
                  $promo_config = bazar_destaque_get_promo_config();
                  $desconto_percent = $promo_config['desconto_percent'];
                  $promo_titulo = isset($promo_config['titulo']) ? (string) $promo_config['titulo'] : '';
                  $promo_subtitulo = isset($promo_config['subtitulo']) ? (string) $promo_config['subtitulo'] : '';
                }

                // Título padrão se não houver título configurado
                if (trim($promo_titulo) === '') {
                  $promo_titulo = sprintf(
                    '<strong>%d%% de DESCONTO</strong> para impulsionar seu anúncio.',
                    (int) $desconto_percent
                  );
                } else {
                  // Permitir que o título venha pronto do painel (sem forçar strong)
                  $promo_titulo = wp_kses_post($promo_titulo);
                }

                // Subtítulo padrão se não houver subtítulo configurado
                if (trim($promo_subtitulo) === '') {
                  $promo_subtitulo = 'Oferta por tempo limitado. Aproveite agora!';
                }
                ?>
                <?php echo $promo_titulo; ?><br />
                <small class="promo-subtitle"><?php echo esc_html($promo_subtitulo); ?></small>
            </h2>
        </div>
    </div>
</div>