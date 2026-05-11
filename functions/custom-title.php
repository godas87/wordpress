<?php
/**
 * Retorna o Titulo com a palavra Bicicleta em vermelho
 * 
 * @param int $title get_the_title() do post
 * @return string Titulo alterado com a palavra Bicicleta em vermelho
 */
// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}
function highlight_bicicleta_title($title)
{
  $highlighted = str_replace(
    'Bicicleta',
    '<span class="red">Bicicleta</span>',
    $title
  );
  return $highlighted;
}
?>