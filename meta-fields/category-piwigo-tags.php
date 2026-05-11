<?php
//busca listagem de categorias no BD Postgress
add_filter('acf/load_field/name=pwigo_tag', 'acf_load_field_choices_piwigo_tags');
function acf_load_field_choices_piwigo_tags( $field ) {

    if( !is_admin() && !wp_doing_ajax() ) return $field;
    
    $json = 'https://XXXXXX/?tag_exclude_id=77&tags_list';
    $token = 'XXXXXX';
    $exists = ( false === ( @file_get_contents($json) ) ) ? false : true;
    if( $exists ) :                    
        $verifySSLpeer=array(
            'http' => array(
                'header' => "Authorization: Bearer $token",
                'method' => 'GET',  // Método da requisição (pode ser POST, PUT, etc.)
            ),
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            )
        );					
        $getCategorias = json_decode( 
            file_get_contents( 
                $json, 
                false, 
                stream_context_create( $verifySSLpeer ) 
            ), true 
        );
    endif;
    //var_dump( $getCategorias );
    if( isset($getCategorias) && is_array($getCategorias) && !empty($getCategorias) ) :
        if( is_array($getCategorias) ) {
            foreach( $getCategorias as $n => $choice ) {
                $field['choices'][$choice] = $choice;
            }        
        }
    endif;    
    return $field;
}
?>