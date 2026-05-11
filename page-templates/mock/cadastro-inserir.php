<?php
if (!bazar_is_production()):
  $_POST = array(
    'first_name' => 'Pedro',
    'last_name' => 'Godoy',
    'email' => 'XXXXXX',
    'cpf' => '08731194605', //324.828.080-81
    'data_nascimento' => '15/01/1986',
    'telefone' => '319888911565',
    'whatsapp_ativo' => '1',
    'cep' => '30140000', ///30140000
    'logradouro' => 'Avenida Brasil',
    'numero' => '321',
    'bairro' => 'Santa Efigênia',
    'complemento' => '',
    'cidade' => 'Belo Horizonte',
    'estado' => 'Minas Gerais',
    'estado_sigla' => 'mg',
    'senha' => 'Senha@123',
    'confirmar_senha' => 'Senha@123',
    'termos' => true,
    'redirect' => '',
    'nonce_cadastro_inserir' => wp_create_nonce('nonce_cadastro_inserir'),
    'action' => 'bazar_cadastro_inserir',
  );
endif;
?>