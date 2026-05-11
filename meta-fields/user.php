<?php
// Adicionar campos protegidos como campos de contato (mesmo comportamento do nome de usuário)
// Isso para o Dashboard do WordPress
$camposProtegidos = array(
  'cpf' => 'CPF',
  'cnpj' => 'CNPJ',
  'data_nascimento' => 'Data de Nascimento',
  'ativar_email' => 'Status de Ativação',
  'codigo_ativacao' => 'Código de Ativação',
  'codigo_ativacao_check_datetime' => 'Data de envio de confirmação de e-mail',
  'resend_pass_datetime' => 'Data de Reenvio de Senha',
);

// Usar a estrutura nativa do WordPress para organizar campos
add_filter('user_contactmethods', 'bazar_add_protected_contact_methods', 10, 1);
function bazar_add_protected_contact_methods($contactmethods)
{
  global $camposProtegidos;
  foreach ($camposProtegidos as $campo => $label) {
    $contactmethods[$campo] = $label;
  }
  return $contactmethods;
}

//USER CUSTOM FIELDS 
add_filter('user_contactmethods', 'my_new_contactmethods', 10, 1);
function my_new_contactmethods($contactmethods)
{
  $contactmethods['fone'] = 'Telefone';
  $contactmethods['whatsapp_ativo'] = 'WhatsApp ativo (sim/não)';
  return $contactmethods;
}

// Adicionar campos de Endereço como campos de contato
add_filter('user_contactmethods', 'bazar_add_address_contact_methods', 10, 1);
function bazar_add_address_contact_methods($contactmethods)
{
  $contactmethods['cep'] = 'CEP';
  $contactmethods['bairro'] = 'Bairro';
  $contactmethods['cidade'] = 'Cidade';
  $contactmethods['estado'] = 'Estado';
  $contactmethods['estado_sigla'] = 'Estado (Sigla)';
  $contactmethods['ddd'] = 'DDD';
  $contactmethods['latitude'] = 'Latitude';
  $contactmethods['longitude'] = 'Longitude';
  return $contactmethods;
}

add_filter('user_contactmethods', 'bazar_add_config_contact_methods', 10, 1);
function bazar_add_config_contact_methods($contactmethods)
{
  $contactmethods['favoritos'] = 'Anúncios Favoritos';
  $contactmethods['bazar_desconto_newsletter'] = 'Desconto Newsletter';

  return $contactmethods;
}


add_filter('user_contactmethods', 'bazar_add_blocked_contact_methods', 10, 1);
function bazar_add_blocked_contact_methods($contactmethods)
{
  $contactmethods['bazar_user_blocked'] = 'Bloqueado';
  $contactmethods['bazar_user_blocked_date'] = 'Data de Bloqueio';
  $contactmethods['bazar_user_blocked_by'] = 'Bloqueado por';
  return $contactmethods;
}

// Tornar os campos protegidos somente leitura (como o nome de usuário)
add_action('show_user_profile', 'bazar_make_protected_fields_readonly');
add_action('edit_user_profile', 'bazar_make_protected_fields_readonly');
function bazar_make_protected_fields_readonly($user)
{
  global $camposProtegidos;

  foreach ($camposProtegidos as $campo => $label) {
    $itensProtegidos[] = '#' . $campo;
  }
  ;
  $itensProtegidos[] = '#email';
  ?>
  <script type="text/javascript">
    let itensProtegidos = <?php echo json_encode($itensProtegidos); ?>;
    jQuery(document).ready(function ($) {
      // Tornar campos protegidos somente leitura (mesmo comportamento do nome de usuário)
      $(itensProtegidos.join(',')).each(function () {
        $(this).prop('readonly', true);
        $(this).prop('disabled', false); // Não desabilitar para manter o estilo padrão
        $(this).css({
          'background-color': '#f1f1f1',
          'color': '#666'
        });
      });
    });
  </script>
  <?php
}

// Prevenir atualização dos campos protegidos (proteção no servidor)
add_action('personal_options_update', 'bazar_prevent_protected_fields_update');
add_action('edit_user_profile_update', 'bazar_prevent_protected_fields_update');
function bazar_prevent_protected_fields_update($user_id)
{
  // Remover os campos protegidos do $_POST para evitar atualização
  global $camposProtegidos;
  foreach ($camposProtegidos as $campo => $label) {
    unset($_POST[$campo]);
  }
}


add_action('profile_update', 'custom_update_profile_modified');
function custom_update_profile_modified($user_id)
{
  update_user_meta($user_id, 'profile_updated', current_time('mysql'));
}

//add_filter('manage_users_columns', 'pippin_add_user_id_column');
function pippin_add_user_id_column($columns)
{
  $columns['user_id'] = 'User ID';
  return $columns;
}

//add_action('manage_users_custom_column',  'pippin_show_user_id_column_content', 10, 3);
function pippin_show_user_id_column_content($value, $column_name, $user_id)
{
  $user = get_userdata($user_id);
  if ('user_id' == $column_name)
    return $user_id;
  return $value;
}
?>