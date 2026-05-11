<?php
/**
 * Input Location - Dados de localização para o fluxo do formulário
 *
 * Por padrão usa o usuário logado. Em edição de anúncio, defina antes do include:
 * `$bazar_input_location_user_id = (int) get_post_field( 'post_author', $post_id );`
 * para enviar cidade/estado/CEP do autor do anúncio (evita ADM sobrescrever com o próprio perfil).
 *
 * Campos hidden: cidade, estado, estado_sigla, cep, user_nome, user_email
 */

$user_nome = '';
$user_email = '';
$user_cidade = '';
$user_estado = '';
$user_estado_sigla = '';
$user_cep = '';
if ( is_user_logged_in() ) {
    $user_id = (int) get_current_user_id();
    if ( isset( $bazar_input_location_user_id ) && is_numeric( $bazar_input_location_user_id ) ) {
        $maybe = (int) $bazar_input_location_user_id;
        if ( $maybe > 0 && get_userdata( $maybe ) ) {
            $user_id = $maybe;
        }
    }
    $profile_user = get_userdata( $user_id );
    if ( $profile_user ) {
        $user_cidade = get_user_meta( $user_id, 'cidade', true );
        $user_estado = get_user_meta( $user_id, 'estado', true );
        $user_estado_sigla = get_user_meta( $user_id, 'estado_sigla', true );
        $user_cep = get_user_meta( $user_id, 'cep', true );
        $user_nome = $profile_user->display_name;
        $user_email = $profile_user->user_email;
    }
}

// Adicionar campos hidden apenas se houver dados
if( !empty( $user_nome ) ) : ?>
    <input type="hidden" name="user_nome" value="<?php echo esc_attr( $user_nome ); ?>" />
<?php endif; ?>

<?php if( !empty( $user_email ) ) : ?>
    <input type="hidden" name="user_email" value="<?php echo esc_attr( $user_email ); ?>" />
<?php endif; ?>

<?php if( !empty( $user_cidade ) ) : ?>
    <input type="hidden" name="user_cidade" value="<?php echo esc_attr( $user_cidade ); ?>" />
<?php endif; ?>

<?php if( !empty( $user_estado ) ) : ?>
    <input type="hidden" name="user_estado" value="<?php echo esc_attr( $user_estado ); ?>" />
<?php endif; ?>

<?php 
// Gerar estado_sigla automaticamente se houver estado
if( !empty( $user_estado ) ) :
    // Se já tem estado_sigla no user meta, usar ele
    if( !empty( $user_estado_sigla ) ) {
        $estado_sigla = $user_estado_sigla;
    } else {
        // Caso contrário, gerar automaticamente a partir do nome do estado
        $geo_api = BazarBikes_GeoAPI::getInstance();
        $estado_sigla = $geo_api->obter_sigla_estado($user_estado);
        // Se não conseguiu converter, verificar se o próprio estado já é uma sigla (2 caracteres)
        if( empty($estado_sigla) || $estado_sigla === $user_estado ) {
            $estado_sigla = ( strlen($user_estado) === 2 ) ? strtoupper($user_estado) : $user_estado;
        }
    }
?>
    <input type="hidden" name="user_estado_sigla" value="<?php echo esc_attr( $estado_sigla ); ?>" />
<?php endif; ?>

<?php if( !empty( $user_cep ) ) : ?>
    <input type="hidden" name="user_cep" value="<?php echo esc_attr( $user_cep ); ?>" />
<?php endif; ?>