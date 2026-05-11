<?php
/**
 * Componente de recomendação de tamanho de quadro
 * Exibido apenas se o anúncio tiver o componente "Quadro" cadastrado
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}
$recommendation = bazar_get_quadro_recommendation();
if ($recommendation && is_array($recommendation)):
    ?>
    <div class="box-content mb-1">
        <div class="row align-center align-middle">
            <div class="s-12 m-4">
                <figure>
                    <img src="<?php echo get_template_directory_uri(); ?>/assets/imgs/frame.svg"
                        alt="<?php _e('Recomendação de Tamanho', 'bazar'); ?>">
                </figure>
            </div>
            <div class="s-10 m-8">

                <strong>Tamanho do quadro:</strong>
                <?php
                //echo esc_html($quadro_size); 
                echo esc_html($recommendation['tamanho_descritivo']);
                ?>
                <br>

                <?php if (!empty($recommendation['altura_pessoa'])): ?>
                    <strong>Ideal ciclistas com altura entre:</strong>
                    <?php echo esc_html($recommendation['altura_pessoa']); ?>
                    <br>
                <?php endif; ?>

                <?php if (!empty($recommendation['equivalencia'])): ?>
                    <strong>Equivalência:</strong>
                    <?php echo esc_html($recommendation['equivalencia']); ?>
                    <br>
                <?php endif; ?>

            </div>
        </div>
    </div>
<?php endif; ?>