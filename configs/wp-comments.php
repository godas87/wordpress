<?php
add_filter( 'comment_form_fields', 'examplename_move_comment_field_to_bottom');
function examplename_move_comment_field_to_bottom( $fields ) {    
    unset( $fields['url'] );
    unset( $fields['cookies'] );
    return $fields;
}

add_filter('comment_form_defaults', 'personalizar_formulario_comentarios');
function personalizar_formulario_comentarios($args) {
    $args['comment_field'] = '<p class="col s-12 comment-form-comment"><textarea id="comment" name="comment" cols="45" rows="8" placeholder="Escreva Seu comentário" aria-required="true" required></textarea></p>';    
    //$defaults['comment_notes_before'] = '';    
    $args['title_reply'] = 'Fale o que você Pensa';
    return $args;
}

add_filter('comment_form_default_fields', 'personalizar_formulario_comentarios_fields');
function personalizar_formulario_comentarios_fields($fields) {
    $fields['author'] = '<p class="col s-12 m-6 comment-form-author"><input id="author" name="author" type="text" placeholder="Nome" aria-required="true" required></p>';
    $fields['email'] = '<p class="col s-12 m-6 comment-form-email"><input id="email" name="email" type="email" placeholder="E-mail" ria-required="true" required></p>';
    return $fields;
}


// Remove comment date
add_filter( 'get_comment_date', 'wpb_remove_comment_date', 10, 3); 
function wpb_remove_comment_date($date, $d, $comment) { 
    return '';
}

// Remove comment time
add_filter( 'get_comment_time', 'wpb_remove_comment_time', 10, 3); 
function wpb_remove_comment_time( $format = '', $gmt = false, $translate = true, $comment_id = 0 ) { 
    return '';
}