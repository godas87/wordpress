<?php
/* Template Name: Cadastro */
if( 
    isset( $_GET['sucess'] ) 
    && $_GET['sucess'] === 'true'
    && isset( $_GET['user_id'] )
    && $_GET['user_id'] != ''
) :    
    $insert_new_user = ( get_user_by( 'ID', sanitize_text_field( $_GET['user_id'] ) ) ) ? true : false;
endif;
// Caso não seja um novo usuário ou usuário já logado, 
// redireciona para a página de minha conta
if( 
    ( isset( $insert_new_user )  && $insert_new_user === false )
    || ( !isset( $insert_new_user ) && is_user_logged_in() )
) : 
    wp_redirect( esc_url(get_bloginfo('url').'\/minha-conta\/') ); 
    exit;
endif;
if ( have_posts() ) : while ( have_posts() ) : the_post();

$msg_success = 'Cadastro realizado com sucesso. Enviamos um e-mail de confirmação.<br/> Caso não tenha recebido, clique <a href="'.get_bloginfo('url').'/confirmar-email" title="Confirmar e-mail">'.__('aqui', 'bazar').'</a>';

// Dados iniciais do formulário (perfil completo no cadastro)
$user_data = array(
    'first_name' => isset($_POST['first_name']) ? sanitize_text_field( $_POST['first_name'] ) : '',
    'last_name' => isset($_POST['last_name']) ? sanitize_text_field( $_POST['last_name'] ) : '',
    'email' => isset($_POST['user_email']) ? sanitize_email( $_POST['user_email'] ) : '',
    'telefone' => isset($_POST['telefone']) ? sanitize_text_field( $_POST['telefone'] ) : '',
    'whatsapp_ativo' => isset($_POST['whatsapp_ativo']) ? $_POST['whatsapp_ativo'] : false,
    'cpf' => isset($_POST['cpf']) ? sanitize_text_field( $_POST['cpf'] ) : '',
    'data_nascimento' => isset($_POST['data_nascimento']) ? sanitize_text_field( $_POST['data_nascimento'] ) : '',
    'cep' => isset($_POST['cep']) ? sanitize_text_field( $_POST['cep'] ) : '',
    'bairro' => isset($_POST['bairro']) ? sanitize_text_field( $_POST['bairro'] ) : '',
    'cidade' => isset($_POST['cidade']) ? sanitize_text_field( $_POST['cidade'] ) : '',
    'estado' => isset($_POST['estado']) ? sanitize_text_field( $_POST['estado'] ) : '',
    'estado_sigla' => isset($_POST['estado_sigla']) ? sanitize_text_field( $_POST['estado_sigla'] ) : '',
    'senha' => isset($_POST['senha']) ? sanitize_text_field( $_POST['senha'] ) : '',
    'confirmar_senha' => isset($_POST['confirmar_senha']) ? sanitize_text_field( $_POST['confirmar_senha'] ) : '',
    'termos' => isset($_POST['termos']) ? $_POST['termos'] : true
);

$cep_url = esc_url( 'https://buscacepinter.correios.com.br/app/localidade_logradouro/index.php' );

get_header();
?>
<h1 class="d-none">
  <?php bloginfo('name');?> - <?php the_title(); ?>
</h1>

<?php medium_content(); ?>    

    <?php 
    if( 
        isset( $insert_new_user ) 
        && $insert_new_user === true
    ) :        
        // Caso seja um novo usuário, exibe CTA de cadastro finalizado
        $cta_data = array(
            'title' => 'Seja bem vindo!',
            'description' => $msg_success,
        );
        get_template_part('template-parts/cta/form-send-success');    
    else :
    // Caso não seja um novo usuário, exibe o formulário de cadastro
    ?>    
    <div class="form-box">

        <h2>
            <?php the_title(); ?>
        </h2>

        <?php // the_content(); ?>

        <p class="alert alert-info">
            <small>
              <?php _e('Utilize seu <b>melhor e-mail</b>.<br>Para alterá-lo depois apenas com suporte.', 'bazar'); ?>
            </small>
        </p>

        <div id="alert"></div>

        <form 
            method="post" 
            id="form-cadastro-inserir"
            name="add_user" 
            action="<?php the_permalink(); ?>"
            enctype="multipart/form-data"
        >
            <!-- Honeypot fields to absorb browser autofill -->
            <input type="text" name="fake_autofill_username" class="not_required" autocomplete="username" style="display: none;" />
            <input type="text" name="fake_autofill_address" class="not_required" autocomplete="address-line1" style="display: none;" />
            <div class="row">
                <div class="s-12 m-6 col">
                    <label for="first_name"><?php _e('Nome', 'bazar'); ?></label>
                    <input 
                        class="format-text"
                        name="first_name" 
                        id="first_name"
                        type="text" 
                        placeholder="<?php esc_attr_e('Seu nome', 'bazar'); ?>" 
                        value="<?php echo esc_attr( $user_data['first_name'] ?? '' ); ?>" />
                </div><!-- /col -->
                <div class="s-12 m-6 col">
                    <label for="last_name"><?php _e('Sobrenome', 'bazar'); ?></label>
                    <input 
                        class="format-text"
                        name="last_name" 
                        id="last_name"
                        type="text" 
                        autocomplete="family-name"
                        placeholder="<?php esc_attr_e('Seu sobrenome', 'bazar'); ?>" 
                        value="<?php echo esc_attr( $user_data['last_name'] ?? '' ); ?>" />
                </div><!-- /col -->
                <div class="s-12 m-6 col">
                    <label for="user_email"><?php _e('E-mail', 'bazar'); ?></label>
                    <input 
                        name="user_email" 
                        id="user_email"
                        type="text" 
                        placeholder="<?php _e('seuemail@email.com', 'bazar'); ?>"
                        value="<?php echo esc_attr( $user_data['email'] ?? '' ); ?>" />
                </div><!-- /col -->

                <div class="s-12 m-6 col">
                  <label for="cpf"><?php _e('CPF', 'bazar'); ?></label>
                  <input name="cpf" id="cpf" type="text" class="mask_cpf"
                    placeholder="<?php esc_attr_e('000.000.000-00', 'bazar'); ?>"
                    value="<?php echo esc_attr( $user_data['cpf'] ?? '' ); ?>" />
                </div>
                <div class="s-12 m-6 col">
                    <label for="data_nascimento"><?php _e('Data de Nascimento', 'bazar'); ?> </label>
                  <input name="data_nascimento" id="data_nascimento" type="text"
                    class="mask_date" data-toggle="datepicker"
                    placeholder="<?php esc_attr_e('dd/mm/aaaa', 'bazar'); ?>"
                    value="<?php echo esc_attr( $user_data['data_nascimento'] ?? '' ); ?>" />
                </div>

                <div class="s-12 m-6 col">
                  <label for="telefone"><?php _e('Telefone', 'bazar'); ?></label>
                  <input name="telefone" id="telefone" type="text" inputmode="numeric"
                    placeholder="<?php esc_attr_e('(00) 00000-0000', 'bazar'); ?>"
                    class="mask_phone" value="<?php echo esc_attr($user_data['telefone'] ?? ''); ?>" />
                </div><!-- /col -->
                <div class="s-12 col">
                  <label class="d-block pb-1" for="whatsapp_ativo" style="font-size: 1rem;">
                    <input type="checkbox" name="whatsapp_ativo" value="1" <?php checked(!empty($user_data['whatsapp_ativo'])); ?> />
                    <i class="fab fa-whatsapp"></i>
                    <?php _e('Possui WhatsApp?', 'bazar'); ?>
                  </label>
                </div><!-- /col -->                

                <div class="s-12 col pt-1">
                  <h3><?php _e('Endereço', 'bazar'); ?></h3>
                  <div id="endereco-msg"></div>
                </div>
                <div class="s-12 m-6 col">
                  <a href="<?php echo esc_url( $cep_url ); ?>" target="_blank"
                    title="<?php esc_attr_e('Encontre seu CEP', 'bazar'); ?>" class="link-cep">
                    <i class="fa fa-info-circle"></i>
                    <?php _e('Não sei meu CEP ', 'bazar'); ?>
                  </a>
                  <label for="cep"><?php _e('CEP', 'bazar'); ?></label>
                  <input name="cep" id="cep" class="mask_cep" type="text" data-cep-context="formulario"
                    placeholder="<?php esc_attr_e('Digite seu CEP', 'bazar'); ?>"
                    value="<?php echo esc_attr( $user_data['cep'] ?? '' ); ?>" />
                </div>
                <div class="s-12 m-6 col">
                  <label for="bairro"><?php _e('Bairro', 'bazar'); ?></label>
                  <input name="bairro" id="bairro" type="text" class="format-text" placeholder="<?php esc_attr_e('Bairro ou Região', 'bazar'); ?>"
                    value="<?php echo esc_attr( $user_data['bairro'] ?? '' ); ?>" />
                </div>
                <div class="s-12 m-6 col">
                  <label for="cidade"><?php _e('Cidade', 'bazar'); ?></label>
                  <input name="cidade" id="cidade" class="format-text" readonly type="text" placeholder="<?php esc_attr_e('Cidade', 'bazar'); ?>"
                    value="<?php echo esc_attr( $user_data['cidade'] ?? '' ); ?>" />
                </div>
                <div class="s-12 m-6 col">
                  <label for="estado"><?php _e('Estado', 'bazar'); ?></label>
                  <input name="estado" id="estado" autocomplete="address-level1" class="format-text" readonly type="text" placeholder="<?php esc_attr_e('Estado', 'bazar'); ?>"
                    value="<?php echo esc_attr( $user_data['estado'] ?? '' ); ?>" />
                  <input type="hidden" name="estado_sigla" id="estado_sigla" autocomplete="off"
                    value="<?php echo esc_attr( $user_data['estado_sigla'] ?? '' ); ?>" />
                </div>

                <div class="s-12 col pt-1">
                  <h3><?php _e('Senha', 'bazar'); ?></h3>
                  <small class="regular d-block pb-1">
                        <?php _e('A senha deve ter no mínimo <b>8 caracteres, letras, números</b> e pelo menos  <b>1 caractere especial</b>.', 'bazar'); ?>
                    </small>
                </div>

                <div class="s-12 col"></div>
                <div class="s-12 m-6 col">
                    <label for="senha"><?php _e('Senha', 'bazar'); ?></label>                  
                  <input 
                    name="senha" 
                    id="senha"
                    type="password"
                    autocomplete="new-password" 
                    placeholder="<?php _e('Senha', 'bazar'); ?>:" 
                    value="<?php echo esc_attr( $user_data['senha'] ?? '' ); ?>" />
                  <span 
                    id="showPpass" 
                    class="fa-solid fa-eye bt-show-password" 
                    title="<?php _e('Ver senha', 'bazar'); ?>"
                  ></span>
                </div><!-- /col -->
                <div class="s-12 m-6 col">                        
                    <label for="confirmar_senha"><?php _e('Confirmar senha', 'bazar'); ?></label>
                    <input 
                        name="confirmar_senha" 
                        id="confirmar_senha"
                        type="password"
                        autocomplete="new-password" 
                        placeholder="<?php _e('Confirmar senha', 'bazar'); ?>:" 
                        value="<?php echo esc_attr( $user_data['confirmar_senha'] ?? '' ); ?>" />
                    <span 
                        id="showPpassConfirm" 
                        class="fa-solid fa-eye bt-show-password" 
                        title="<?php _e('Ver senha', 'bazar'); ?>"
                    ></span>
                </div><!-- /col -->            
                <div class="s-12 col">                    
                    <hr />
                </div>
                <div class="s-12 m-7 col">                    
                    <div class="row align-middle termos">
                        <div class="col shrink">
                            <input 
                                type="checkbox" 
                                name="termos" 
                                id="termos" 
                                value="true" 
                                checked="checked"
                                <?php checked( $user_data['termos'] ?? false, true ); ?> />
                        </div>
                        <div class="col reset">
                            <?php get_template_part('template-parts/forms/termos'); ?>
                        </div>
                    </div>
                </div>
                <div class="s-12 m-5 col text-right">
                    <input type="submit" class="bt-enviar bt-check btn-success" disabled value="<?php _e('Cadastrar', 'bazar'); ?>" />
                </div>

                <?php $nonce = wp_create_nonce( 'nonce_cadastro_inserir' ); ?>
                <input type="hidden" name="nonce_cadastro_inserir" value="<?php echo $nonce; ?>" />
                <?php get_template_part('tempalte-parts/forms/input-redirect'); ?>
                <input name="action" type="hidden" value="bazar_cadastro_inserir" />

            </div><!-- /row -->
        </form>

    </div><!-- /form-box -->

    <?php endif; ?>

<?php close_content(); ?> 

<script type="text/javascript">
  var __BAZAR_Page = 'cadastro-inserir';
</script>

<?php get_footer(); endwhile; endif;?>
<?php get_template_part('template-parts/modal/reativar-conta'); ?>