<?php
// Incluir função de validação se não estiver disponível
if( !function_exists( 'bazar_sanitize_redirect_url' ) ) {
    require_once( get_template_directory() . '/app/functions/dashboard-control.php' );
}
$redirect = '';
$redirect_to = '';
// Suportar redirect (ID de post) - formato antigo
if( isset( $_GET['redirect'] ) ) {
    $redirect = intval($_GET['redirect']);
} elseif( isset( $_POST['redirect'] ) ) {
    $redirect = intval( $_POST['redirect'] );
}
// Suportar redirect_to (URL completa) - formato novo
// IMPORTANTE: Validar segurança para prevenir Open Redirect
if( isset( $_GET['redirect_to'] ) ) {
    $redirect_to = bazar_sanitize_redirect_url( $_GET['redirect_to'] );
} elseif( isset( $_POST['redirect_to'] ) ) {
    $redirect_to = bazar_sanitize_redirect_url( $_POST['redirect_to'] );
}
// Priorizar redirect_to se ambos existirem e for válido
if( $redirect_to != '' ) : 
?>
<input type="hidden" class="redirect" name="redirect" value="<?php echo esc_attr( $redirect_to ); ?>" />
<?php elseif( $redirect != '' ) : 
?>
<input type="hidden" class="redirect" name="redirect" value="<?php bloginfo('url'); ?>/?p=<?php echo $redirect; ?>" />
<?php endif; ?>