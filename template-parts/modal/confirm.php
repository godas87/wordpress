<div 
    id="modal-confirm" 
    class="modal-alert-message" 
    style="display: none;"
>    
    <!-- Caixa do modal -->
    <div class="modal-alert-message-box">
        <div class="modal-alert-message-content">
            <!-- Mensagem -->
            <div class="modal-alert-message-texto">
                <h3 id="modal-confirm-title">Confirmar Ação</h3>
                <p id="modal-confirm-message">Tem certeza que deseja realizar esta ação?</p>
            </div>            
            <!-- Botões de Confirmação (modo: confirm) -->
            <div class="modal-alert-message-buttons" id="modal-confirm-buttons-confirm">
                <button 
                    type="button" 
                    class="button clear regular medium" 
                    id="bt-cancelar-confirm"
                >
                    Cancelar
                </button>
                <button 
                    type="button" 
                    class="button green regular medium" 
                    id="bt-confirmar-confirm"
                >
                    Confirmar
                </button>
            </div>
            <!-- Botão de Resultado (modo: feedback) -->
            <div class="modal-alert-message-buttons" id="modal-confirm-buttons-feedback" style="display: none;">
                <button 
                    type="button" 
                    class="button green regular medium" 
                    id="bt-fechar-confirm"
                >
                    OK
                </button>
            </div>
        </div>
    </div>
    <!-- Overlay de fundo -->
    <div class="modal-alert-message-overlay"></div>
</div>

