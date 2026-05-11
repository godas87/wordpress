<?php
$minha_conta_url = home_url('/minha-conta/');

if (!is_user_logged_in()) {
  wp_safe_redirect($minha_conta_url);
  exit;
}

$user_id = get_current_user_id();
$redirect_candidate = '';

if (!empty($_GET['redirect_to'])) {
  $redirect_candidate = (string) $_GET['redirect_to'];
} elseif (!empty($_POST['redirect'])) {
  $redirect_candidate = (string) $_POST['redirect'];
}

if (function_exists('bazar_sanitize_redirect_url')) {
  $redirect_candidate = bazar_sanitize_redirect_url($redirect_candidate);
} else {
  $redirect_candidate = esc_url_raw($redirect_candidate);
}

// Esta tela so deve existir no fluxo de cadastro de anuncio (inclui cadeia via confirmar-email).
$is_anuncio_flow = (
  ! empty( $redirect_candidate )
  && function_exists( 'bazar_redirect_targets_anuncio_updated_flow' )
  && bazar_redirect_targets_anuncio_updated_flow( $redirect_candidate )
);
$is_login_gate_flow = (isset($_GET['from_login']) && (string) $_GET['from_login'] === '1');

if (!$is_anuncio_flow && !$is_login_gate_flow) {
  wp_safe_redirect($minha_conta_url);
  exit;
}

$cep = trim((string) get_user_meta($user_id, 'cep', true));
$bairro = trim((string) get_user_meta($user_id, 'bairro', true));
$cidade = trim((string) get_user_meta($user_id, 'cidade', true));
$estado = trim((string) get_user_meta($user_id, 'estado', true));
$estado_sigla = trim((string) get_user_meta($user_id, 'estado_sigla', true));
if ($estado_sigla === '') {
  $estado_sigla = trim((string) get_user_meta($user_id, 'estado-sigla', true));
}
$has_full_address = (
  $cep !== ''
  && $bairro !== ''
  && $cidade !== ''
  && $estado !== ''
  && $estado_sigla !== ''
);

if ($has_full_address) {
  wp_safe_redirect($minha_conta_url);
  exit;
}
?>