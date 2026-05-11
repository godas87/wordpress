<?php
if( !is_user_logged_in() ) :
    $redirect_page = get_the_ID();
    $location = ( $redirect_page == '' )
        ? get_bloginfo('url').'\/entrar\/'
        : get_bloginfo('url').'\/entrar\/?redirect='.$redirect_page;
    wp_redirect( $location );
    exit;
endif;
?>