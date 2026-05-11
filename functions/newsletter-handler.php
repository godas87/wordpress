<?php
/**
 * Handler para processar formulário de newsletter
 * Prevenir acesso direto
 */
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Processa o formulário de newsletter
 */
add_action('admin_post_newsletter_subscribe', 'bazar_newsletter_subscribe');
add_action('admin_post_nopriv_newsletter_subscribe', 'bazar_newsletter_subscribe');

function bazar_newsletter_subscribe()
{
  // Verificar nonce
  if (!isset($_POST['newsletter_nonce']) || !wp_verify_nonce($_POST['newsletter_nonce'], 'newsletter_subscribe')) {
    wp_redirect(home_url('/?newsletter=error'));
    exit;
  }

  // Obter e validar email
  $email = isset($_POST['newsletter_email']) ? sanitize_email($_POST['newsletter_email']) : '';

  if (empty($email) || !is_email($email)) {
    wp_redirect(home_url('/?newsletter=invalid'));
    exit;
  }

  // Verificar se email já existe
  global $wpdb;
  $existing_id = $wpdb->get_var($wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'newsletter' 
        AND post_title = %s 
        LIMIT 1",
    $email
  ));

  if ($existing_id) {
    wp_redirect(home_url('/?newsletter=exists#newsletter'));
    exit;
  }

  // Criar novo post (assinante)
  $post_data = array(
    'post_title' => $email,
    'post_type' => 'newsletter',
    'post_status' => 'publish',
    'post_author' => 1,
  );

  $post_id = wp_insert_post($post_data);

  if (is_wp_error($post_id) || $post_id === 0) {
    wp_redirect(home_url('/?newsletter=error#newsletter'));
    exit;
  }

  // Salvar data de cadastro como meta
  update_post_meta($post_id, 'newsletter_date', current_time('mysql'));
  update_post_meta($post_id, 'newsletter_ip', $_SERVER['REMOTE_ADDR'] ?? '');

  // Redirecionar com sucesso
  wp_redirect(home_url('/?newsletter=success#newsletter'));
  exit;
}
?>