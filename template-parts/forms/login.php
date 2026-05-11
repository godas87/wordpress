<form 
    method="post" 
    id="form-login" 
    class="send-form" 
    name="form_login" 
    action="<?php the_permalink(); ?>"
>

    <div id="alert"></div>

    <div class="row">
        <div class="s-12 col">

            <label for="user_email">
                <?php _e('E-mail', 'bazar'); ?>
            </label>
            <input 
                name="user_email" 
                type="email" 
                required
                placeholder="<?php _e('E-mail', 'bazar'); ?>:" 
                value="<?php if( isset ( $_POST['user_email'] ) ) : echo $_POST['user_email']; elseif( isset ( $_GET['user_email'] ) ) : echo $_GET['user_email']; endif; ?>" 
            />

        </div>
        <div class="s-12 col">
            <label for="user_pass">
                <?php _e('Senha', 'bazar'); ?>
            </label>
            <input 
                type="password" 
                name="user_pass" 
                value="<?php if( isset ( $_POST['user_pass'] ) ) : echo $_POST['user_pass']; elseif( isset ( $_GET['user_pass'] ) ) : echo $_GET['user_pass']; endif; ?>" 
                placeholder="<?php _e('Senha', 'bazar'); ?>:" 
            />
            <span 
                id="showPpass" 
                class="fa-solid fa-eye bt-show-password" 
                title="<?php _e('Ver senha', 'bazar'); ?>"
            ></span>
        </div>
        <div class="s-12 col">
            <input type="submit" class="large" value="Entrar"/>
        </div>
    </div>
            
    <?php get_template_part('template-parts/forms/input-redirect'); ?>
    <?php $nonce = wp_create_nonce( 'nonce_login_user' ); ?>
    <input type="hidden" name="nonce_login_user" value="<?php echo $nonce; ?>" />
    <input name="action" type="hidden" value="bazar_login_user" />
                                            
</form>