<div 
    id="modal-vendido-success" 
    class="modal-alert-message" 
    style="display: none;"
>    
    <!-- Caixa do modal -->
    <div class="modal-alert-message-box">
        <div class="modal-alert-message-content">
            <!-- Mensagem -->
            <div class="modal-alert-message-texto">
                <h3 id="modal-vendido-success-title text-center" style="color: #28a745;">Sucesso!</h3>
                <p id="modal-vendido-success-message text-center">Anúncio marcado como vendido com sucesso!</p>
                <!-- Botão Google Meu Negócio -->
                <div class="text-center mt-2">
                    <?php get_template_part('template-parts/btn/google-meu-negocios'); ?>
                </div>
            </div>            
            <!-- Botão de Fechar -->
            <div class="modal-alert-message-buttons">
                <button 
                    type="button" 
                    class="button green regular medium" 
                    id="bt-fechar-vendido-success"
                >
                    OK
                </button>
            </div>
        </div>
    </div>
    <div class="modal-alert-message-overlay"></div>
</div>

