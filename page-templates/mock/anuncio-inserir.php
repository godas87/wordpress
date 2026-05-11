<?php
if (!bazar_is_production()):
  $_POST = array(
    'componente' => array(
      '13309' => '12900', //aro
      '13320' => '12906', //quadro
      '13376' => '13018', //suspensão
      '13119' => '13120', //freio    
      '13357' => '12917', //cambio dianteiro
      '13361' => '12912', //cambio traseiro
      '13383' => '13211', //passador
      '13394' => '12936' //pneu
    ),
    'modalidade' => 'mountain-bike-mtb',
    'marcas_modelos' => 'Sense',
    'marcas_modelos_child' => 'Rock Evo',
    'marcas_modelos_child_add' => 'false',
    'category' => '1', // ID da categoria bicicleta  
    'conservacao' => 'usado', // slug
    'material' => 'aluminio', // slug
    'peso' => '12,5', // formato: número com vírgula (máx 3 casas decimais)
    'valor' => '4.000,00', // formato monetário brasileiro
    'ano' => '2023',
    'cor' => 'preto', // slug
    'genero' => 'masculino', // slug
    'idade' => 'adulto', // slug 
    'nota_fiscal' => 'true', // 'true' ou 'false'
    'exibir_contato' => 'true', // 'true' ou 'false'
    'negociacao' => array('a-vista'), // array de slugs    
    'txt-descricao' => 'Bicicleta em excelente estado, bem conservada e revisada. Ideal para trilhas e passeios.',
    'post_type' => '1', // 1=bicicletas, 2=componentes, 3=acessorios  
    'termos' => 'true',
    'nonce_anuncio_inserir' => wp_create_nonce('nonce_anuncio_inserir'),
    'action' => 'bazar_anuncio_inserir',
    'redirect' => '',
  );
  //var_dump($_POST);
endif;
?>