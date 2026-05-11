<?php
/**
 * Opções de links do site (ex.: grupo WhatsApp)
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
  exit;
}

add_action('admin_menu', 'bazar_links_site_add_admin_menu');
function bazar_links_site_add_admin_menu()
{
  add_options_page(
    'Bazar Settings',
    'Bazar Settings',
    'manage_options',
    'bazar-links-site',
    'bazar_links_site_config_page'
  );
}

function bazar_links_site_config_page()
{
  if (isset($_POST['bazar_links_site_save']) && check_admin_referer('bazar_links_site_config')) {
    update_option('bazar_whatsapp_group_url', esc_url_raw($_POST['whatsapp_group_url'] ?? ''));
    update_option('bazar_api_cpf_key', sanitize_text_field($_POST['bazar_api_cpf_key'] ?? ''));
    echo '<div class="notice notice-success"><p>Configurações salvas com sucesso!</p></div>';
  }

  $whatsapp_group_url = get_option('bazar_whatsapp_group_url', 'XXXXXX');
  $bazar_api_cpf_key = get_option('bazar_api_cpf_key', '');
  ?>
  <div class="wrap">
    <h1>Bazar Settings</h1>
    <p>Configure links utilizados em CTAs e páginas do tema.</p>

    <form method="post" action="">
      <?php wp_nonce_field('bazar_links_site_config'); ?>

      <h2 class="title">WhatsApp</h2>
      <table class="form-table">
        <tr>
          <th scope="row">
            <label for="whatsapp_group_url">Link do grupo de WhatsApp</label>
          </th>
          <td>
            <input type="url" id="whatsapp_group_url" name="whatsapp_group_url"
              value="<?php echo esc_attr($whatsapp_group_url); ?>" class="large-text code"
              placeholder="https://chat.whatsapp.com/..." />
            <p class="description">URL de convite do grupo (ex.: https://chat.whatsapp.com/...). Usado no CTA "Entrar no
              grupo do WhatsApp".</p>
          </td>
        </tr>
      </table>

      <h2 class="title">API CPF</h2>
      <a href="https://XXXXXX/" target="_blank" rel="noopener">XXXXXX</a>
      <table class="form-table">
        <tr>
          <th scope="row">
            <label for="bazar_api_cpf_key">Access Token:</label>
          </th>
          <td>
            <input type="text" id="bazar_api_cpf_key" name="bazar_api_cpf_key"
              value="<?php echo esc_attr($bazar_api_cpf_key); ?>" class="large-text code"
              placeholder="Chave da API de CPF (opcional)" />
            <p class="description">Chave da API usada para validação de CPF em backend. Adicione aqui para centralizar a
              configuração.</p>
          </td>
        </tr>
      </table>


      <p class="submit">
        <input type="submit" name="bazar_links_site_save" class="button button-primary" value="Salvar" />
      </p>
    </form>
  </div>
  <?php
}