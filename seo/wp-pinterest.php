<?php
/**
 * Integração Pinterest para SEO e compartilhamento
 *
 * O XXXXXX já possui Open Graph e Schema.org Product (wp-seo-meta.php e wp-schemas.php),
 * que são os requisitos base para Pinterest Rich Pins. Este arquivo adiciona:
 *
 * - Meta tag de verificação do domínio (Pinterest Business)
 * - Meta tags otimizadas para Rich Pins
 * - Desativação de Rich Pins em páginas privadas/sensíveis
 * - Botão "Salvar" (Pin It) para compartilhamento
 * - Pinterest Tag opcional (conversões/analytics)
 *
 * @package XXXXXX
 * @see https://help.pinterest.com/en/business/article/rich-pins
 * @see https://developers.pinterest.com/docs/save-button/
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Classe de integração Pinterest
 */
class BazarBikes_Pinterest_Integration
{

  /**
   * ID de verificação do Pinterest (obter em: Pinterest Business > Configurações > Claimed accounts)
   * Deixe vazio até verificar o domínio em business.pinterest.com
   */
  const PINTEREST_VERIFICATION_ID = '';

  /**
   * Habilitar Pinterest Tag para rastreamento de conversões (opcional)
   */
  const PINTEREST_TAG_ENABLED = false;

  /**
   * ID do Pinterest Tag (obter em: Pinterest Ads Manager > Conversões)
   */
  const PINTEREST_TAG_ID = '';

  /**
   * Inicializa hooks
   * Nota: Pinterest Tag e pinit.js estão centralizados em template-parts/lib/pinterest.php
   * (incluído via footer-scripts). Esta classe fornece a configuração.
   */
  public static function init()
  {

  }

  /**
   * Verifica se deve exibir integração Pinterest na página atual
   */
  public static function should_show_pinterest()
  {
    if (is_admin() || is_feed()) {
      return false;
    }

    // Não exibir em páginas que não devem ser compartilhadas
    if (
      is_page([
        'feedback-vendido',
        'minha-conta',
        'meus-anuncios',
        'editar-anuncio',
        'confirmar-email',
      ])
    ) {
      return false;
    }

    if (is_404() || is_search()) {
      return false;
    }

    return true;
  }

  /**
   * Verifica se deve desativar Rich Pins na página (páginas privadas/sensíveis)
   */
  public static function should_disable_rich_pins()
  {
    return is_page('feedback-vendido')
      || is_page('editar-anuncio')
      || is_page('minha-conta')
      || is_page('meus-anuncios')
      || is_page('confirmar-email')
      || is_singular('app')
      || is_singular('teste');
  }

  /**
   * Gera URL para compartilhar no Pinterest (Pin It)
   *
   * @param string|null $url   URL da página (default: URL atual)
   * @param string|null $media URL da imagem
   * @param string|null $description Descrição do pin
   * @return string URL do Pinterest
   */
  public static function get_pin_it_url($url = null, $media = null, $description = null)
  {
    $url = $url ?: (is_singular() ? get_permalink() : home_url('/'));
    $media = $media ?: self::get_featured_image_url();
    $description = $description ?: (is_singular() ? get_the_title() : get_bloginfo('name') . ' - ' . get_bloginfo('description'));

    return add_query_arg([
      'url' => urlencode($url),
      'media' => urlencode($media),
      'description' => urlencode(wp_strip_all_tags($description)),
    ], 'https://pinterest.com/pin/create/button/');
  }

  /**
   * Obtém URL da imagem destacada no formato adequado para Pinterest
   * Pinterest recomenda imagens com proporção 2:3, mínimo 600px de largura
   */
  public static function get_featured_image_url()
  {
    if (is_singular() && has_post_thumbnail()) {
      return get_the_post_thumbnail_url(get_the_ID(), 'l');
    }
    return 'https://XXXXXX/src/imgs/bazar-bikes-groups.jpg';
  }

  /**
   * Renderiza botão "Salvar" (Pin It) do Pinterest
   *
   * @param array $args {
   *     @type string $url   URL para pinar
   *     @type string $media URL da imagem
   *     @type string $description Descrição
   *     @type string $class Classes CSS adicionais
   * }
   */
  public static function render_save_button($args = [])
  {
    if (!self::should_show_pinterest()) {
      return;
    }

    $defaults = [
      'url' => is_singular() ? get_permalink() : home_url('/'),
      'media' => self::get_featured_image_url(),
      'description' => is_singular() ? get_the_title() : get_bloginfo('name'),
      'class' => '',
    ];
    $args = wp_parse_args($args, $defaults);

    $pin_url = self::get_pin_it_url($args['url'], $args['media'], $args['description']);
    $class = esc_attr(trim('pinterest fab fa-pinterest-p ' . $args['class']));
    ?>
    <a href="<?php echo esc_url($pin_url); ?>" title="<?php esc_attr_e('Salvar no Pinterest', 'bazar'); ?>"
      rel="noopener noreferrer" target="_blank" class="<?php echo $class; ?>" data-pin-do="buttonPin" data-pin-tall="true"
      data-pin-save="true">
      <span class="d-none">Pinterest</span>
    </a>
    <?php
  }

}

// Inicializar
add_action('init', function () {
  BazarBikes_Pinterest_Integration::init();
});
