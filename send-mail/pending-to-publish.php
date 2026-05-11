<?php
// Hook para quando anúncio é aprovado (de pending ou draft para publish)
add_action('pending_to_publish', 'pending_to_publish_send_mail', 10, 1);
add_action('draft_to_publish', 'pending_to_publish_send_mail', 10, 1);
function pending_to_publish_send_mail($post)
{
	// Não enviar email quando for restauração em lote da lixeira → vendido
	if (!empty($GLOBALS['bazar_restore_trash_as_vendido'])) {
		return;
	}

	$post_id = $post->ID;

	$post_type = get_post_type($post_id);
	$author_id = $post->post_author;

	$user_name = get_the_author_meta('user_firstname', $author_id);
	$user_email = get_the_author_meta('user_email', $author_id);

	// Limpar motivos de indeferimento e reavaliação quando o anúncio for aprovado
	if ($post_type == 'post') {
		$motivos_indeferimento = get_field('motivos_para_indeferimento', $post_id);
		if (!empty($motivos_indeferimento)) {
			update_field('motivos_para_indeferimento', '', $post_id);
		}
		// Limpar flag de reavaliação
		update_field('reavaliacao', false, $post_id);
	}

	//SEND MAIL TO USER (quando anúncio vai para publish, seja pelo ADM ou pelo service ao completar perfil/confirmar email)
	if ((isset($post_type) && $post_type == 'post')) {

		$author_id = (int) $author_id;
		$anuncio_url = get_permalink($post_id);
		$email_body = 'Parabéns ' . esc_html($user_name) . ', seu anúncio foi <strong>publicado</strong> com sucesso no Bazar Bikes. Seja-bem vindo a nossa comunidade de ciclistas.';
		if (function_exists('bazar_publication_service_html_paragraph_email_opcional')) {
			$email_body .= bazar_publication_service_html_paragraph_email_opcional($author_id);
		} elseif (function_exists('bazar_usuario_email_confirmado_meta') && !bazar_usuario_email_confirmado_meta($author_id)) {
			$confirmar_email_url = home_url('/confirmar-email/');
			$email_body .= ' <p><strong>Importante:</strong> confirme seu e-mail em <a href="' . esc_url($confirmar_email_url) . '">confirmar e-mail</a> para poder entrar novamente no site depois de sair da conta.</p>';
		}

		// Pagamento de destaque registado, mas termo "destaque" ainda não ativo (ex.: aguarda verificação de CPF em Minha Conta)
		$destaque_pago_aguarda_ativacao = (
			get_post_meta($post_id, 'destaque_payment_status', true) === 'paid'
			&& !has_term('destaque', 'status', $post_id)
		);

		// Verificar se pode destacar (novo checkout; não aplica quando já há pagamento pendente de verificação)
		$pode_destacar = false;
		$preco_normal = 50.00;

		if (function_exists('bazar_can_boost_anuncio')) {
			$validacao = bazar_can_boost_anuncio($post_id, (int) $author_id);
			$pode_destacar = $validacao['can'] ?? false;
			if ($pode_destacar && function_exists('bazar_destaque_get_promo_config')) {
				$promo_cfg = bazar_destaque_get_promo_config();
				$preco_normal = $promo_cfg['preco_normal'];
			} elseif ($pode_destacar && function_exists('bazar_destaque_get_preco')) {
				$preco_normal = bazar_destaque_get_preco(false);
			}
		}

		// Ícone de sucesso ao lado do título da seção de impulsionamento (filtro para CDN ou ambiente local)
		$check_icon_url = apply_filters(
			'bazar_email_publicacao_check_icon_url',
			'https://XXXXXX/src/imgs/check.png'
		);

		// Dados do anúncio para o card (thumbnail + título + preço)
		$product_data = function_exists('bazar_get_product_data') ? bazar_get_product_data($post_id) : null;
		$anuncio_titulo = $product_data['title'] ?? get_the_title($post_id);
		$preco_anuncio = '';
		if ($product_data && !empty($product_data['formatted']['valor'])) {
			$preco_anuncio = 'R$ ' . $product_data['formatted']['valor'];
		}
		$thumb_url = get_the_post_thumbnail_url($post_id, 'medium');
		if (!$thumb_url) {
			$thumb_url = get_the_post_thumbnail_url($post_id, 'thumbnail');
		}

		$product_card = array();
		if ($thumb_url || $anuncio_titulo !== '' || $preco_anuncio !== '') {
			$product_card = array(
				'image_url' => $thumb_url ? $thumb_url : '',
				'title' => $anuncio_titulo,
				'price' => $preco_anuncio,
			);
		}

		$anuncio_ja_impulsionado = has_term('destaque', 'status', $post_id);

		$buttons = array(
			array(
				'label' => 'Ver meu anúncio',
				'url' => $anuncio_url ? $anuncio_url : (get_bloginfo('url') . '/?p=' . $post_id),
				'text' => 'Ver meu anúncio',
			),
		);

		$highlighted_sections = array();
		$minha_conta_url = home_url('/minha-conta/');
		$confirmar_email_url = home_url('/confirmar-email/');

		// Destaque já pago, termo ainda não ativo: mensagens separadas (e-mail vs. requisitos do impulsionamento)
		if ($destaque_pago_aguarda_ativacao) {
			$pendencias_pub = function_exists('bazar_publication_service_get_profile_pendencias_publicacao')
				? bazar_publication_service_get_profile_pendencias_publicacao($author_id)
				: null;
			$need_email_confirmacao = $pendencias_pub && !empty($pendencias_pub['need_email_confirmacao']);
			$elegivel_destaque = function_exists('bazar_destaque_service_usuario_elegivel')
				&& bazar_destaque_service_usuario_elegivel($author_id);

			if ($need_email_confirmacao) {
				$highlighted_sections[] = array(
					'title' => 'Confirme seu e-mail',
					'content' => 'Seu anúncio já está publicado e o pagamento do impulsionamento foi recebido. A confirmação do e-mail não bloqueia a ativação do destaque após a verificação do perfil (CPF), mas você precisará confirmá-lo para acessar a conta novamente depois de sair do site.',
					'content_allow_html' => false,
					'button_url' => $confirmar_email_url,
					'button_text' => 'Confirmar e-mail',
					'highlight_color' => '#6a1b9a',
					'bg_color' => '#faf5fc',
					'border_color' => '#e1bee7',
					'promo_notice' => '',
				);
			}

			if (!$elegivel_destaque) {
				$perfil_ok = function_exists('bazar_perfil_verificado') && bazar_perfil_verificado($author_id);
				$cpf_digits = preg_replace('/\D/', '', (string) get_user_meta($author_id, 'cpf', true));
				if (!$perfil_ok) {
					$destaque_cadastro_msg = 'Recebemos o pagamento do impulsionamento. Para o destaque entrar no ar, conclua a verificação de identidade em Minha Conta: envio e validação do CPF pelos dados solicitados na plataforma. Quando a verificação for aprovada, o sistema ativa o destaque sozinho — não é preciso pagar de novo.';
				} else {
					$destaque_cadastro_msg = 'Recebemos o pagamento do impulsionamento. Para liberar o destaque, ajuste o CPF em Minha Conta para ficar completo (11 dígitos). Depois de salvar, a ativação ocorre automaticamente.';
				}
				$highlighted_sections[] = array(
					'title' => 'Ative seu impulsionamento',
					'content' => $destaque_cadastro_msg,
					'content_allow_html' => false,
					'button_url' => $minha_conta_url,
					'button_text' => 'Ir para Minha Conta',
					'highlight_color' => '#1565C0',
					'bg_color' => '#f8f9fa',
					'border_color' => '#e8eaed',
					'promo_notice' => '',
				);
			} else {
				// Pagamento ok e perfil elegível, mas termo ainda não refletido (atraso/race)
				$highlighted_sections[] = array(
					'title' => 'Impulsionamento em liberação',
					'content' => 'O pagamento foi confirmado e seu perfil já permite o destaque. O sistema deve aplicar o impulsionamento em instantes. Se após alguns minutos o anúncio ainda não aparecer em destaque, atualize a página ou acesse Minha Conta.',
					'content_allow_html' => false,
					'button_url' => $minha_conta_url,
					'button_text' => 'Ir para Minha Conta',
					'highlight_color' => '#1565C0',
					'bg_color' => '#f8f9fa',
					'border_color' => '#e8eaed',
					'promo_notice' => '',
				);
			}
		}

		// Já publicado com destaque ativo: mesmo padrão visual do upsell (check + card cinza), sem CTA de pagamento
		if ($anuncio_ja_impulsionado && !$destaque_pago_aguarda_ativacao) {
			$ver_anuncio_url = $anuncio_url ? $anuncio_url : (get_bloginfo('url') . '/?p=' . $post_id);
			$highlighted_sections[] = array(
				'title' => 'Anúncio impulsionado',
				'title_image_url' => $check_icon_url,
				'content' => 'Seu anúncio está em modo TURBO, agora ele aparece como destaque nas buscas e listagens do XXXXXX!',
				'content_allow_html' => false,
				'product_card' => $product_card,
				'button_url' => $ver_anuncio_url,
				'button_text' => 'Ver meu anúncio',
				'button_compact' => true,
				'highlight_color' => '#2e7d32',
				'bg_color' => '#f4f6f4',
				'border_color' => '#dde3d6',
				'promo_notice' => '',
			);
			// Evita botão preto duplicado no rodapé (generate_buttons): o CTA verde já está no highlight
			$buttons = array();
		}

		// Seção: impulsionamento (layout enxuto: ícone + título, texto, card do anúncio, CTA)
		if ($pode_destacar && $preco_normal > 0) {
			$boost_url = add_query_arg('impulsionar', '1', get_permalink($post_id));
			$promo_button_label = 'Impulsionar com desconto';
			if (function_exists('bazar_destaque_get_promo_config')) {
				$promo_cfg_email = bazar_destaque_get_promo_config();
				if (!empty($promo_cfg_email['modal_promo_btn_label'])) {
					$promo_button_label = (string) $promo_cfg_email['modal_promo_btn_label'];
				}
			}

			$boost_intro = 'Apareça em destaque nos resultados de buscas e receba muito mais visualizações. Seu anúncio ficará no topo até ser vendido. Aproveite a <strong>promoção de lançamento com 50% de desconto</strong>!';

			$highlighted_sections[] = array(
				'title' => 'Impulsione seu anúncio',
				'title_image_url' => $check_icon_url,
				'content' => $boost_intro,
				'content_allow_html' => true,
				'product_card' => $product_card,
				'promo_notice' => '',
				'button_url' => $boost_url,
				'button_text' => $promo_button_label,
				'button_compact' => true,
				'highlight_color' => '#769F51',
				'bg_color' => '#f4f6f4',
				'border_color' => '#dde3d6',
			);
		}

		//SEND MAIL USER
		$mail_data = array(
			'name' => $user_name,
			'to' => $user_email,
			'subject' => 'Anúncio publicado',
			'msg_header' => 'Seu anúncio está no ar',
			'email_body' => $email_body,
			'buttons' => $buttons,
			'highlighted_sections' => $highlighted_sections,
			'highlighted_sections_before_buttons' => true,
		);
		$send_mail = new __Bazar_Send_Mail();
		$send_mail->send_mail_msg($mail_data);
	}

}
?>