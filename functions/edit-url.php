<?php
/**
 * Retorna a URL de edição com base no post-type
 * 
 * @param int $post_id ID do post
 * @return string URL de edição
 */

 // Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}
function bazar_get_edit_url($post_id){
    if (!$post_id) return '';
    return home_url('/editar-anuncio/?post_id=' . $post_id);
}
?>