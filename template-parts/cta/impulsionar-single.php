<?php
global $is_post_published;
global $is_vendido;
global $post_id;
global $author_id;
// Botão de Impulsionar para o autor do anúncio
if (
  is_user_logged_in()
  && (
    $author_id == get_current_user_id()
    || current_user_can('manage_options')
  )
  && $is_post_published
  && !$is_vendido
):
  // Verificar se pode destacar
  $pode_impulsionar = false;
  if (function_exists('bazar_can_boost_anuncio')) {
    $result_boost = bazar_can_boost_anuncio($post_id);
    $pode_impulsionar = $result_boost['can'] ?? false;
  }

  if ($pode_impulsionar):
    ?>
    <div id="impulsionar-fixed-bar" class="impulsionar-fixed-bar">
      <div class="boost-single">
        <figure>
          <img src="<?php echo get_template_directory_uri(); ?>/assets/imgs/content/cta-footer.png"
            alt="Impulsionar Anúncio">
        </figure>
        <button type="button" class="bt-modal btn-impulsionar-single" data-modal="impulsionar"
          data-anuncio-id="<?php echo esc_attr($post_id); ?>">
          <i class="fas fa-rocket"></i>
          <span>Impulsione seu Anúncio</span>
        </button>
      </div>
    </div>
    <?php
  endif;
endif;
?>