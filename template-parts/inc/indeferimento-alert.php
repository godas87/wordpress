<div class="col s-12 pb-1">
      <div style="border: 2px solid #c9201a; background: #fff3f3; padding: 1rem;">
          <small class="bold d-block" style="color: #666;">
              <a href="<?php echo bazar_get_edit_url($args['post_id']); ?>" class="black">
                  <i class="fas fa-edit"></i>
                  Editar Anúncio
              </a>
          </small>
          <p class="mb-0">
              <strong>Seu anúcio foi indeferido, faça os ajustes necessários e salve o anúncio para reenviar para aprovação:</strong><br>        
              <?php echo wp_kses_post( nl2br( esc_html( $args['motivos_indeferimento'] ) ) ); ?>
          </p>
          <?php 
          $em_reavaliacao = get_field('reavaliacao', $args['post_id']);
          if( $em_reavaliacao ) : 
          ?>
          <hr style="border-color: #999; margin: 0.5rem 0;" />
          <small class="d-block" style="color: #28a745;">
              <i class="fa fa-check-circle"></i> 
              <strong>Está indeferido, porém os novos ajustes foram enviados para revisão.</strong>
          </small>
          <?php endif; ?>
      </div>
  </div>