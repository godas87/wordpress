<?php
/**
 * AJAX: mover anúncio para a lixeira (autor ou quem tem delete_post).
 */

if (!defined('ABSPATH')) {
  exit;
}

add_action('wp_ajax_bazar_anuncio_delete', 'bazar_anuncio_delete');

function bazar_anuncio_delete()
{
  if (!is_user_logged_in()) {
    wp_send_json_error(array('message' => 'Sessão expirada. Entre novamente na sua conta.'), 403);
  }

  $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
  if ($post_id < 1) {
    wp_send_json_error(array('message' => 'Identificação do anúncio inválida.'), 400);
  }

  $post = get_post($post_id);
  if (!$post || $post->post_type !== 'post') {
    wp_send_json_error(array('message' => 'Anúncio não encontrado.'), 404);
  }

  $uid = get_current_user_id();
  if ((int) $post->post_author !== $uid && !current_user_can('delete_post', $post_id)) {
    wp_send_json_error(array('message' => 'Você não tem permissão para excluir este anúncio.'), 403);
  }

  $deleted = __Bazar_Anuncio_Delete::process_delete($post_id);
  if (!$deleted) {
    wp_send_json_error(array('message' => 'Não foi possível mover o anúncio para a lixeira. Tente novamente.'), 500);
  }

  wp_send_json_success(array('message' => 'Anúncio excluído com sucesso.'));
}

class __Bazar_Anuncio_Delete
{

  /**
   * Remove anexos (exceto destacada), destaque se houver, e envia o post para a lixeira.
   *
   * @param int $post_id
   * @return bool
   */
  public static function process_delete($post_id)
  {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
      return false;
    }

    if (function_exists('bazar_remove_post_images_except_featured')) {
      bazar_remove_post_images_except_featured($post_id);
    } else {
      $imgs = get_posts(array(
        'post_type' => 'attachment',
        'posts_per_page' => -1,
        'post_parent' => $post_id,
      ));
      if ($imgs) {
        $featured_id = get_post_thumbnail_id($post_id);
        foreach ($imgs as $img) {
          if ((int) $img->ID !== (int) $featured_id) {
            $img_delete = new __Bazar_Attachment_Delete();
            $img_delete->_bazar_delete_file($img->ID);
          }
        }
      }
      wp_reset_postdata();
    }

    if (function_exists('bazar_remover_destaque') && has_term('destaque', 'status', $post_id)) {
      bazar_remover_destaque($post_id, 'excluido');
    }

    $result = wp_trash_post($post_id);
    if (is_wp_error($result)) {
      return false;
    }
    return (bool) $result;
  }
}
