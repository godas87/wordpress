<?php
global $product_data;
$autor_url = get_author_posts_url($product_data['author']['id'] ?? '', $product_data['author']['nicename'] ?? '');
$author_name = $product_data['author']['name'];
$author_sobrenome = $product_data['author']['sobrenome'] ?? '';
$author_posts_count = $product_data['author']['posts_count'] ?? '';
$author_registered = $product_data['author']['registered'] ?? '';
$author_location = $product_data['formatted']['location'] && $product_data['formatted']['location'] != '' ?
    $product_data['formatted']['location'] : '<span class="silver">Endereço não verificado</span>';
$author_uid = (int) ($product_data['author']['id'] ?? 0);
$is_verified = !empty($product_data['author']['perfil_verificado']);
$selo_email_pendente = !empty($product_data['author']['mostrar_selo_email_pendente']);
$is_current_author = is_user_logged_in() && get_current_user_id() === $author_uid;
$nao_verificado_action = ($is_current_author && $author_uid > 0) ? home_url('/minha-conta/') : '';
?>
<div class="row">
    <div class="s-12 m-4 col pb-1">
        <small class="d-block">Anunciante:</small>
        <span class="user-verified-name-row">
            <a href="<?php echo esc_url($autor_url); ?>"
                title="<?php echo esc_attr(sprintf(__('Anúncios de %s', 'bazar'), $author_name)); ?>" rel="author"
                class="black">
                <?php echo esc_html(trim($author_name . ' ' . $author_sobrenome)); ?>
            </a>
            <?php
            get_template_part(
                'template-parts/inc/badge-usuario-verificado',
                null,
                array(
                    'is_verified' => $is_verified,
                )
            );
            ?>
            <small><?php echo '(' . (int) $author_posts_count . ')'; ?></small>
        </span>
    </div>
    <div class="s-12 m-4 col pb-1">
        <small class="d-block">Localidade:</small>
        <span>
            <?php echo '<b>' . $author_location . '</b>'; ?>
        </span>
    </div>
    <div class="s-12 m-4 col pb-1">
        <small class="d-block">Cadastro:</small>
        <b>
            <?php echo $author_registered; ?>
        </b>
    </div>
</div>
<?php get_template_part('template-parts/inc/product-denuncia'); ?>
<?php get_template_part('template-parts/btn/contact-info'); ?>