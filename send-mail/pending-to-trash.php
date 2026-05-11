<?php
/**
 * Envia email quando um anúncio é movido para a lixeira (excluído)
 * IMPORTANTE: Este hook é disparado quando o anúncio é REALMENTE excluído,
 * não quando é reprovado. Para reprovação, use a classe anuncio-aprovar-reprovar.php
 */
add_action('draft_to_trash', 'trash_send_mail', 10, 1);
add_action('pending_to_trash', 'trash_send_mail', 10, 1);
function trash_send_mail($post)
{
  // Ação silenciosa: quando o script de postagens antigas move rascunhos para a lixeira em lote, não enviar e-mail
  if (!empty($GLOBALS['bazar_silent_trash'])) {
    return;
  }

  $post_id = $post->ID;
  $titulo_anuncio = get_the_title($post_id);
  $post_type = get_post_type($post_id);

  $author_id = $post->post_author;
  $user_name = get_the_author_meta('user_firstname', $author_id);
  $user_email = get_the_author_meta('user_email', $author_id);

  $email_help = 'XXXXXX';

  if (
    is_admin() &&
    isset($post_type) &&
    ($post_type == 'post')
  ) {

    $email_body = 'Prezado(a) ' . $user_name . ',<br>
			<p>Informamos que seu anúncio <b>' . $titulo_anuncio . '</b> foi <b>desativado</b> no site Bazar Bikes.</p>
			<p>Se você acredita que isso foi um erro ou deseja mais informações, entre em contato conosco.</p>';

    $email_body .= '<hr><p>Para mais informações envie um email para o suporte <a href="mailto:' . $email_help . '">clicando aqui</a>.';

    //SEND MAIL USER
    //INIT CLASS		
    $mail_data = array(
      'name' => $user_name,
      'to' => $user_email,
      'subject' => "Anúncio desativado",
      'msg_header' => "Anúncio desativado",
      'email_body' => trim($email_body)
    );
    $send_mail_adm = new __Bazar_Send_Mail();
    $send_mail_adm->send_mail_msg($mail_data);
  }

}
?>