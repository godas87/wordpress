<?php
/* Template Name: Meus Anuncios*/
get_template_part('template-parts/global/validacao');

$user_id = get_current_user_id();

$custom_args = array(
  'post_type' => array(
    'post'
  ),
  'post_status' => array(
    'publish',
    'pending'
  ),
  'posts_per_page' => -1,
  'author' => $user_id,
);
$custom_query = new WP_Query($custom_args);

if (have_posts()):
  while (have_posts()):
    the_post();
    get_header();
    ?>

    <h1 class="d-none">
      <?php bloginfo('name'); ?> - <?php the_title(); ?>
    </h1>

    <?php index_layout(); ?>

    <?php get_template_part('template-parts/forms/alert-perfil-complete'); ?>

    <h2 class="title bold_">
      <?php the_title(); ?>
    </h2>

    <a href="<?php bloginfo('url'); ?>/anunciar/" class="button small mb-1" title="Criar um novo anúncio">
      <i class="fa fa-plus-circle" style="padding-right: .5rem;"></i>
      Criar um Novo Anúncio
    </a>

    <?php if ($custom_query->have_posts()): ?>

      <?php get_template_part('template-parts/cta/msg-box'); ?>

      <p>
        Exibindo
        <b id="count_post"><?php echo $custom_query->post_count; ?></b>
        <?php echo _n('anúncio', 'anúncios', $custom_query->post_count, 'bazar') ?>.
      </p>

      <div class="row s-up-1 m-up-2 l-up-3 grid">

        <?php
        while ($custom_query->have_posts()):
          $custom_query->the_post();
          $post = $custom_query->post;
          // Calcular status globalizado UMA VEZ (considera vendido)
          // Verificar se está vendido antes de chamar a função
  
          $status_data = bazar_get_anuncio_status(
            $post->ID,
            $post->post_status
          );

          $post_status_class = '';
          if ($status_data['original_status'] !== 'publish') {
            $post_status_class = 'pending';
          } elseif ($status_data['is_vendido']) {
            $post_status_class = 'vendido';
          }
          ?>
          <div class="col mb-3 <?php echo $post_status_class; ?>">
            <?php
            get_template_part(
              'template-parts/inc/edit-menu-meus-anuncios',
              null,
              array(
                'pos_id' => $post->ID,
                'status_data' => $status_data
              )
            ); ?>
            <?php
            get_template_part('template-parts/loop/product-card-adm', null, array(
              'post' => $post,
              'status_data' => $status_data
            )); ?>
            <?php
            get_template_part('template-parts/loop/product-screenshot', null, array(
              'post' => $post
            )); ?>

          </div>

          <?php
        endwhile;
        wp_reset_postdata();
        ?>

      </div><!-- /grid -->

      <?php get_template_part('template-parts/global/pagination'); ?>

    <?php else: ?>
      <div class="row align-center">
        <div class="s-12 l-6 col pt-4 pb-4 text-center sucess">
          <h2 class="silver">
            <?php _e('Você ainda não tem anúncios publicados. :(', 'bazar'); ?>
          </h2>
        </div><!-- /col -->
      </div><!-- /row -->
    <?php endif; ?>

    <?php close_clear_content(); ?>

    <script type="text/javascript">
      var __BAZAR_Page = 'anuncio-exibir';
    </script>

    <?php
    // Incluir modais para confirmações
    get_template_part('template-parts/modal/confirm');
    get_template_part('template-parts/modal/vendido-success');
    get_template_part('template-parts/modal/impulsionar');
    ?>

    <?php get_footer(); endwhile; endif; ?>