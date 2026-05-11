<form method="get" name="s" action="<?php bloginfo('url'); ?>/bicicletas/">
    <div class="row align-middle form-head box-input">
        <div class="col">
            <input 
                type="text" 
                name='s' 
                value="<?php if ( isset( $_GET['s'] ) && !empty( $_GET['s'] ) ) echo $_GET['s'] ?>" 
                placeholder="Digite aqui o que procura"
            />
        </div>
        <div class="col shrink reset">
            <input 
                type="image" 
                src="<?php bloginfo('template_url'); ?>/assets/imgs/submit.png" 
                alt="Buscar" 
                width="25" 
                height="24"
            >
        </div>
    </div>
</form>