<?php
/* Template Name: Confirmar E-mail*/
if ( have_posts() ) : while ( have_posts() ) : the_post();

$ativado = false;
$refresh = false;
$cliente_id = '';

// Verificar se usuário está logado
if( is_user_logged_in() ) :
    $current_user = wp_get_current_user();
    $cliente_id = $current_user->ID;
endif;

// Verificar se há parâmetros de ativação na URL
if( 
    isset( $_GET['ativar'] ) 
    && !empty($_GET['ativar']) 
    && $_GET['ativar'] === 'true' 
    && isset( $_GET['cliente_id'] ) 
    && !empty($_GET['cliente_id']) 
    && isset( $_GET['codigo'] ) 
    && !empty($_GET['codigo'])
) :
    $cliente_id = absint( $_GET['cliente_id'] );
endif;

// Processar ativação se houver cliente_id
if( !empty($cliente_id) && $cliente_id > 0 ) :
    
    $status_user = get_user_meta( $cliente_id, 'ativar_email', true );
	$codigo_check = get_user_meta( $cliente_id, 'codigo_ativacao', true );
    $codigo_recebido = isset( $_GET['codigo'] ) ? wp_strip_all_tags( $_GET['codigo'] ) : '';
    
    // Se já está ativado
    if( $status_user == 'true' ) :
        $ativado = true;
        
    // Se tem código e está correto
	elseif( 
        !empty($codigo_recebido) &&
        !empty($codigo_check) &&
        $codigo_check === $codigo_recebido && 
        $status_user == 'false' 
    ) :		
		$user = get_user_by( 'id', $cliente_id ); 
		if( $user ) :			
			update_user_meta( $cliente_id, 'ativar_email', 'true');								
			wp_set_current_user( $cliente_id, $user->user_login );
			wp_set_auth_cookie( $cliente_id );
			$ativado = true;
			// Service de publicação: tentar publicar anúncios aprovados pelo ADM agora que e-mail está ativado
			if ( function_exists( 'bazar_publication_service_try_publish_for_user' ) ) {
				bazar_publication_service_try_publish_for_user( $cliente_id );
			}
			if ( function_exists( 'bazar_destaque_service_try_apply_pending_for_user' ) ) {
				bazar_destaque_service_try_apply_pending_for_user( $cliente_id );
			}
        endif;
            
    // Se tem código mas está incorreto
    elseif( 
        !empty($codigo_recebido) &&
        !empty($codigo_check) &&
        $codigo_check !== $codigo_recebido && 
        $status_user == 'false' 
    ) :
        $refresh = true;
    endif;

endif;

if ( $ativado && ! empty( $cliente_id ) && (int) $cliente_id > 0 ) {
	$bounce = function_exists( 'bazar_resolve_post_confirm_email_redirect' )
		? bazar_resolve_post_confirm_email_redirect( (int) $cliente_id )
		: '';
	if ( $bounce !== '' ) {
		wp_safe_redirect( $bounce );
		exit;
	}
}

get_header(); 
?>

<h1 class="d-none">
	<?php bloginfo('name');?> - <?php the_title(); ?>
</h1>

<?php small_content(); ?>

    <?php
    if( $ativado) :
        $cta_data = array(            
            'title' => 'Publicação liberada!',
            'description' => 'E-mail confirmado com sucesso. Agora você pode publicar seu primeiro anúncio.',
        );
        get_template_part('template-parts/cta/form-send-success');
    else :        
    ?>
    <div class="form-box">

        <h2>
            <?php the_title(); ?>
        </h2>     

        <?php the_content(); ?>

        <div id="alert">
            <?php if( $refresh ) echo '<div class="msg-erro bold">Seu código expirou, envie novamente.</div>';?>
        </div>
        
        <form 
            method="post" 
            id="form-cadastro-confirmar-email" 
            class="send-form" 
            name="confirmar_user" 
            action="<?php the_permalink(); ?>"
        >
            <div class="row">
                <div class="s-12 col">

                    <label for="user_email">
                        <?php _e('E-mail cadastrado', 'bazar'); ?>
                    </label>
                    <input 
                        name="user_email" 
                        type="email" 
                        required
                        placeholder="<?php _e('E-mail', 'bazar'); ?>:" 
                        value="<?php if( isset ( $_POST['user_email'] ) ) : echo $_POST['user_email']; elseif( isset ( $_GET['user_email'] ) ) : echo $_GET['user_email']; endif; ?>" 
                    />

                </div>
                <div class="s-12 col">
                    
                    <input 
                        type="submit" 
                        class="bt-enviar bt-check" 
                        value="<?php _e('Enviar', 'bazar'); ?>" 
                    />

                </div>
                <div class="s-12 col text-center" style="padding-top: .75rem;">
                    <small><?php _e('Verifique também no lixo eletrônico, ou caixa de SPAN.', 'bazar'); ?></small>
                </div>
            </div>            
            
            <?php get_template_part('template-parts/forms/input-redirect', 'input-redirect'); ?>
            <?php $nonce = wp_create_nonce( 'nonce_confirmar_mail' ); ?>
            <input type="hidden" name="nonce_confirmar_mail" value="<?php echo $nonce; ?>" />
            <input name="action" type="hidden" value="bazar_confirmar_mail" />

        </form>        
    
    </div><!-- /form-box -->

    <?php endif; ?>

<?php close_content(); ?>

<script type="text/javascript">
	var __BAZAR_Page = 'cadastro-confirmar-email';
</script>

<?php get_footer(); endwhile; endif;?>