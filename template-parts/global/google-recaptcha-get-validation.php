<?php
if( 'GET' == $_SERVER['REQUEST_METHOD'] && isset( $_GET['g-recaptcha-response'] ) ) :
	$check_robot = new __Bazar_Google_reCapatcha();
	if(  !$check_robot->isValidCaptcha() ) :
	 	http_response_code(403);
	 	wp_die(__("<p><strong>Error:</strong> Código de verificação do reCAPTCHA inválido. Acesso proibido.</p><p><a href='javascript:history.back()'>« Voltar</a></p>"));
	endif;
endif;
?>