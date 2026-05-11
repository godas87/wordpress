<?php
/**
 * Hook para enviar email quando anúncio muda de 'pending' para 'draft'
 * Dispara automaticamente quando um anúncio é reprovado
 * 
 * @package XXXXXX
 */

add_action('pending_to_draft', 'draft_send_mail', 10, 1);
function draft_send_mail($post)
{

  $post_id = $post->ID;
  $post_type = get_post_type($post_id);

  // Apenas processar posts do tipo 'post'
  if (
    !isset($post_type) ||
    $post_type !== 'post'
  ) {
    return;
  }

  $author_id = $post->post_author;
  $user_name = get_the_author_meta('user_firstname', $author_id);
  $user_email = get_the_author_meta('user_email', $author_id);

  if (empty($user_email)) {
    return;
  }

  $motivos_para_indeferimento = get_field('motivos_para_indeferimento', $post_id);

  // Determinar se é uma reprovação (tem motivos de indeferimento) ou outra situação
  $is_reprovacao = !empty($motivos_para_indeferimento);

  // Preparar conteúdo do email baseado no contexto
  if ($is_reprovacao) {
    // É uma reprovação - email mais específico
    $email_body = 'Prezado(a) ' . $user_name . ',<br>
			<p>Sua solicitação para divulgação no site Bazar Bikes foi <b>indeferida</b>.</p>
			<p>Seu anúncio está pausado até que faça os ajustes necessários para publicação.</p>';

    $email_body .= '<p>
			<b>Motivos do indeferimento:</b><br>
			' . nl2br(esc_html($motivos_para_indeferimento)) . '
		</p>';

    $email_body .= '<p><hr>Acesse seu anúncio e faça os devido ajustes clicando no link abaixo:';

    $subject = "Indeferimento";
    $msg_header = "Solicitação Indeferida";
    $button_url = add_query_arg('preview', 'true', get_permalink($post_id));
  } else {
    // Outra situação (não é reprovação específica)
    $email_body = 'Prezado(a) ' . $user_name . ',<br>
			<p>Sua solicitação para divulgação no site Bazar Bikes está pendente. Seu anúncio está pausado até que faça ajustes para publicação.</p>';

    $email_body .= '<p><hr>Acesse seu anúncio e faça os devido ajustes clicando no link abaixo:';

    $subject = "Aguardando ajustes para publicação de anúncio";
    $msg_header = "Solicitação aguardando ajustes para publicação.";
    $button_url = get_permalink($post_id);
  }

  // Enviar email
  $mail_data = array(
    'name' => $user_name,
    'to' => $user_email,
    'subject' => $subject,
    'msg_header' => $msg_header,
    'email_body' => $email_body,
    'buttons' => array(
      0 => array(
        "label" => "Revisar anúncio",
        "url" => $button_url,
        "text" => "Revisar anúncio",
      ),
    ),
    'fail_on_error' => 'alert', // Não é erro fatal
  );

  $send_mail_adm = new __Bazar_Send_Mail();
  $send_mail_adm->send_mail_msg($mail_data);
}
?>