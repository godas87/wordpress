<?php
global $user;
if( $user ) :
           
    // if( !is_page('minha-revenda') && $user->roles[0] == 'revenda_user') :
    //     $location = get_bloginfo('url').'\/minha-revenda\/';        
    // endif;
    
    // if( !is_page('minha-parceria') && $user->roles[0] == 'paceiro_user') :
    //     $location = get_bloginfo('url').'\/minha-parceria\/'; 
    // endif;   
    $location = '';
    
    if( !is_page('minha-conta') && $user->roles[0] == 'author') :
        $location = get_bloginfo('url').'\/minha-conta\/';    
    endif;

    if( $user->roles[0] == 'administrator' ) :
        $location = get_bloginfo('url').'\/wp-admin\/'; 
    endif;

    if( $location != '' ) :    
        wp_redirect($location);
        exit;
    endif;

endif;
?>