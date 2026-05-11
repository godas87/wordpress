<?php
/* Template Name: Login */
$user_logged_in = is_user_logged_in();

if (have_posts()):
    while (have_posts()):
        the_post();
        get_header();
        ?>

        <h1 class="d-none">
            <?php bloginfo('name'); ?> - <?php the_title(); ?>
        </h1>

        <?php small_content(); ?>

        <?php
        if ($user_logged_in):
            $cta_data = array(
                'size' => 'small',
                'title' => 'Seja bem-vindo!',
                'description' => 'No Bazar Bikes você pode criar seu anúncio de forma rápida e fácil.',
            );
            get_template_part('template-parts/cta/form-send-success');
        else:
            ?>
            <div class="form-box">

                <?php
                if (isset($_GET['aiowps_login_msg_id']) && $_GET['aiowps_login_msg_id'] == 'session_expired'):
                    echo '<div class="alert alert-info">Sua sessão expirou. Por favor, faça login novamente.</div>';
                endif;
                ?>

                <h2>
                    <?php the_title(); ?>
                </h2>

                <?php the_content(); ?>

                <?php get_template_part('template-parts/forms/login'); ?>

                <p class="text-center pt-1">
                    <?php _e('Não tem uma conta?', 'bazar'); ?>
                    <a href="<?php bloginfo('url'); ?>/cadastro<?php if (isset($_GET['redirect'])):
                          echo '?redirect=' . intval($_GET['redirect']);
                      endif; ?>" class="black"
                        title="<?php _e('Fazer cadastro', 'bazar'); ?>">
                        <?php _e('Cadastre-se', 'bazar'); ?>
                    </a>
                </p>

                <a href="<?php bloginfo('url'); ?>/reenviar-senha" class="lost-password black"
                    title="<?php _e('Reenviar Senha', 'bazar'); ?>" rel="me">
                    <i class="fa-solid fa-envelope" style="margin-right: .25rem;"></i>
                    <?php _e('Reenviar Senha', 'bazar'); ?>
                </a>

            </div><!-- /form-box -->
        <?php endif; ?>

        <?php close_content(); ?>
        <script type="text/javascript">
            var __BAZAR_Page = 'login';
        </script>
        <?php get_footer(); endwhile; endif; ?>
<?php get_template_part('template-parts/modal/reativar-conta'); ?>