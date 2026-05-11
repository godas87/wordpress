<?php
add_action('wp_set_comment_status', 'notificar_admin_comentario_aprovado');
function notificar_admin_comentario_aprovado( $comment_id, $comment_status ) {
    	
	$comment = get_comment( $comment_id );
    if ( $comment->comment_approved == 1 ) :
                    
		$post = get_post($comment->comment_post_ID);
        $post_title = $post->post_title;
		
		$author = get_userdata( $post->post_author );
		$author_email = $author->user_email;
        $author_name = $author->first_name;

        $email_body = '	
		<p>
			Prezado(a) '.$author_name.', <br>
			Um novo comentário foi enviado em sua postagem "'. $post_title .'", e aguarda seu retorno.
		</p>';

        $email_body .= '<p style="text-align:center;">
			<strong style="font-size:14px;">Você pode visualizá-lo aqui:</strong>
			<br><br>
			<a href="'.get_the_permalink($post->ID).'/#comment-'.$comment_id.'" style="display:inline-block; padding:.5rem 1rem; background-color:#c9201a; color:#FFF; text-transform: uppercase; border-radius:7px; text-decoration:none;" title="Responder comentário">
				Responder comentário
			</a>
		</p>';

        //SEND MAIL USER
		$mail_data = array(
			'name' => $author_name,
			'to' => $author_email,
			'subject' => "Moderação de comentários",
			'msg_header' => "Um novo comentário em seu anúncio.",
			'email_body' => trim($email_body),
		);
		$send_mail_adm = new __Bazar_Send_Mail();
		$send_mail_adm->send_mail_msg( $mail_data );

    endif;
}


// Envia um email para o autor do artigo.
add_action('comment_post', 'notificar_autor_artigo_comentario', 10, 3);
function notificar_autor_artigo_comentario($comment_ID, $comment_approved, $commentdata) {
	
	// Notifica resposat ao autor do comentário
	if ( $comment_approved == 1 ) :

		$comment = get_comment( $commentdata['comment_parent'] );

		$post = get_post( $comment->comment_post_ID );
        $post_title = $post->post_title;
        $comment_author_email = $comment->comment_author_email;
        $comment_author = $comment->comment_author;

        $email_body = '	
		<p>
			Prezado(a) '.$comment_author.', <br>
			Seu comentário em "' . $post_title . '" foi respondido. Obrigado por sua contribuição!
		</p>';

        $email_body .= '<p style="text-align:center;">
			<strong style="font-size:14px;">Para visualização acesse o link abaixo:</strong>
			<br><br>
			<a href="'.get_the_permalink($post->ID).'/#comment-'.$comment_ID.'" style="display:inline-block; padding:.5rem 1rem; background-color:#c9201a; color:#FFF; text-transform: uppercase; border-radius:7px; text-decoration:none;" title="Ver comentário">
				Ver meu comentário
			</a>
		</p>';

        //SEND MAIL USER
		$mail_data = array(
			'name' => $comment_author,
			'to' => $comment_author_email,
			'subject' => "Moderação de comentários",
			'msg_header' => "Seu comentário foi respondido.",
			'email_body' => trim($email_body),
		);
		$send_mail_adm = new __Bazar_Send_Mail();
		$send_mail_adm->send_mail_msg( $mail_data );

	endif;

	// Verifica se o comentário está pendente de aprovação
	if ( $comment_approved == 0 ) : 
        
		$comment = get_comment($comment_ID);
                    
		$post = get_post($comment->comment_post_ID);
        $post_title = $post->post_title;
		
		$author = get_userdata( $post->post_author );
		$author_email = $author->user_email;
        $author_name = $author->first_name;

		$comment_link = get_bloginfo('url').'/wp-admin/edit-comments.php';

        $email_body = '	
		<p>
			Prezado(a), <br>
			Um novo comentário foi enviado para aprovação.
		</p>';

        $email_body .= '<p style="text-align:center;">
			<strong style="font-size:14px;">Você pode visualizá-lo aqui:</strong>
			<br><br>
			<a href="'.$comment_link.'" style="display:inline-block; padding:.5rem 1rem; background-color:#c9201a; color:#FFF; text-transform: uppercase; border-radius:7px; text-decoration:none;" title="Moderar comentário">
				Moderar comentário
			</a>
		</p>';

        //SEND MAIL USER
		$mail_data = array(
			'name' => 'Bazar Bikes',
			'to' => 'XXXXXX',
			'subject' => "Moderação de comentários",
			'msg_header' => "Um novo comentário espera moderação.",
			'email_body' => trim($email_body),
		);
		$send_mail_adm = new __Bazar_Send_Mail();
		$send_mail_adm->send_mail_msg( $mail_data );

    endif;

}
?>