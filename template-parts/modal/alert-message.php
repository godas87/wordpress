<div 
    id="modal-alert-message" 
    class="modal-alert-message" 
    style="display: none;"
>    
    <!-- Caixa do modal -->
    <div class="modal-alert-message-box">
        <div class="modal-alert-message-content">
            <!-- Mensagem de aviso -->
            <div class="modal-alert-message-texto">
                <h3>Aviso Importante</h3>
                <p>O site Bazar Bikes não detém posse do produto anunciado. A responsabilidade pela venda é exclusiva do anunciante.</p>
                <?php get_template_part('template-parts/inc/product-dicas'); ?>
            </div>            
            <!-- Botões -->
            <div class="modal-alert-message-buttons">
                <button 
                    type="button" 
                    class="button clear regular medium" id="bt-cancelar-alert-message"
                >
                    Cancelar
                </button>
                <button 
                    type="button" 
                    class="button green regular medium" 
                    id="bt-confirmar-alert-message"
                >
                    Enviar Mensagem para o Vendedor
                </button>
            </div>
        </div>
    </div>
    <!-- Overlay de fundo -->
    <div class="modal-alert-message-overlay"></div>
</div>