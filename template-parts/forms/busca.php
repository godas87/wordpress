<?php 
$post_data = $_GET; 
//var_dump( $post_data );
?>
<div class="form-box busca">

    <?php //if( !is_front_page() ) : ?>
    <div class="row form pb-1">
        <div class="s-12 m-5 l-4 col">
                    
            <form method="get" name="codigo" action="<?php bloginfo('url'); ?>/bicicletas/">            
                <div class="row align-middle form-head box-input">
                    <div class="col">
                        <input type="text" class="mask_number" name='p' placeholder="Buscar código" value="<?php if ( isset( $_GET['p'] ) && !empty( $_GET['p'] ) ) echo $_GET['p'] ?>"/>
                    </div>
                    <div class="col shrink reset">
                        <input type="submit" value="Buscar"/>                                
                    </div>
                </div>
            </form>

        </div><!-- /col --> 
        <div class="s-12 m-7 l-8 col s-order-2 m-order-1 hide-for-s-only">
                    
            <?php get_template_part('template-parts/forms/search-string'); ?>

        </div><!-- /col --> 
    </div><!-- /row -->   
    <?php //endif; ?>

    <form id="form_busca" method="get" name="busca" action="<?php bloginfo('url'); ?>/bicicletas/">

        <div class="row form">
            <div class="s-12 m-4 col">
                
                <div class="categ bold">
                    <span class="fas fa-bicycle red"></span>
                    <span>Categoria</span>
                </div>
                
                <div class="relative">
                    <select 
                        id="c" 
                        name="c" 
                        data-categ="category" 
                        data-label="Tipo" 
                        data-child="c_" 
                        class="list-sub-item"
                    >
                        <option value="">Tipo</option>
                        <?php 
                        $args = array(
                            'taxonomy' => 'category',
                            'hierarchical' => 1,
                            'parent' => 0,				
                            'hide_empty' => 1,
                            'orderby' => 'name',
                        );			
                        $categs = get_terms($args);
                        foreach($categs as $categ) :                            
                        ?>
                        <option value="<?php echo $categ->term_id; ?>" <?php if( isset( $post_data['c'] ) ) : selected( $post_data['c'], $categ->term_id ); endif; ?>><?php echo $categ->name; ?></option>
                        <?php endforeach; ?>				
                    </select>
                    <span class="close-field <?php if( isset($post_data['c']) && $post_data['c'] != '' ) : echo 'active'; endif; ?>" data-field="c">X</span>
                </div>

                <div class="relative">
                    <select id="c_" name="c_" data-label="Categoria">
                        <option value="">Categoria</option>
                        <?php 
                        if ( isset( $post_data['c_'] ) && !empty( $post_data['c_'] ) || isset( $post_data['c'] ) && !empty( $post_data['c'] ) ) :
                        $args = array(
                            'taxonomy' => 'category',
                            'hierarchical' => 1,
                            'child_of' => $post_data['c'],			
                            'hide_empty' => 1,
                            'orderby' => 'name',
                        );
                        $categs = get_terms($args);
                        foreach($categs as $categ) :
                        ?>
                        <option value="<?php echo $categ->term_id; ?>" <?php if( isset( $post_data['c_'] ) ) :  selected( $post_data['c_'], $categ->term_id ); endif; ?>><?php echo $categ->name; ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                    <span class="close-field <?php if( isset( $post_data['c_'] ) && $post_data['c_'] != '' ) echo 'active'; ?>" data-field="c_">X</span>
                </div>

            </div><!-- /col -->
            <div class="s-12 m-4 col">
                
                <div class="categ bold">
                    <span class="fas fa-tag red"></span>
                    <span>Marca e Modelo</span>
                </div>                
                
                <div class="relative">
                    <select id="ma" name="ma" data-categ="marca-modelo" data-label="Marca" data-child="m_" class="list-sub-item">
                        <option value="">Marca</option>
                        <?php
                        $args2 = array(
                            'taxonomy' => 'marca-modelo',
                            'hierarchical' => 1,
                            'parent' => 0,			
                            'hide_empty' => 1,
                            'orderby' => 'name',
                        );
                        $categs2 = get_terms($args2);
                        foreach($categs2 as $categ2) :
                        ?>
                        <option value="<?php echo $categ2->term_id; ?>" <?php if( isset( $post_data['ma'] ) ) :  selected( $post_data['ma'], $categ2->term_id ); endif; ?>><?php echo $categ2->name; ?></option>			
                        <?php endforeach; ?>				
                    </select>
                    <span class="close-field <?php if( isset( $post_data['ma'] ) && $post_data['ma'] != '' ) echo 'active'; ?>" data-field="ma">X</span>
                </div>

                <div class="relative">
                    <select id="m_" name="m_" data-label="Modelo">
                        <option value="">Modelo</option>
                        <?php 
                        if ( isset( $post_data['m_'] ) && !empty( $post_data['m_'] ) || isset( $post_data['ma'] ) && !empty( $post_data['ma'] ) ) :
                        $args2_ = array(
                            'taxonomy' => 'marca-modelo',
                            'hierarchical' => 1,
                            'child_of' => $post_data['ma'],			
                            'hide_empty' => 1,
                            'orderby' => 'name',
                        );
                        $categs2_ = get_terms($args2_);
                        foreach($categs2_ as $categ2_) :
                        ?>
                        <option value="<?php echo $categ2_->term_id; ?>" <?php if( isset( $post_data['m_'] ) ) : selected( $post_data['m_'], $categ2_->term_id ); endif; ?>><?php echo $categ2_->name; ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                    <span class="close-field <?php if( isset( $post_data['m_'] ) && $post_data['m_'] != '' ) echo 'active'; ?>" data-field="m_">X</span>
                </div>
                
            </div><!-- /col -->
            <div class="s-12 m-4 col">
                
                <div class="categ bold">
                    <span class="fas fa-cog red"></span>
                    <span>Especificações</span>
                </div>

                <div class="relative">
                    <select id="e" name="e" data-categ="especificacoes" data-label="Especificação" data-child="e_" class="list-sub-item">
                        <option value="">Especificação</option>
                        <?php 
                        $args = array(
                            'taxonomy' => 'especificacoes',
                            'hierarchical' => 0,
                            'parent' => 0,				
                            'hide_empty' => 1,
                            'orderby' => 'name',
                        );
                        $categs = get_terms($args);
                        foreach($categs as $categ) :
                        ?>
                        <option value="<?php echo $categ->term_id; ?>" <?php if( isset( $post_data['e'] ) ) : selected( $post_data['e'], $categ->term_id ); endif; ?>><?php echo $categ->name; ?></option>
                        <?php endforeach; ?>						
                    </select>
                    <span class="close-field <?php if( isset( $post_data['e'] ) && $post_data['e'] != '' ) echo 'active'; ?>" data-field="e">X</span>
                </div>

                <div class="relative">                
                    <select id="e_" name="e_" class="" data-label="Selecione">
                        <option value="">Selecione</option>
                        <?php 
                        if ( isset( $post_data['e_'] ) && !empty( $post_data['e_'] ) || isset( $post_data['e'] ) && !empty( $post_data['e'] ) ) :
                        $args3_ = array(
                            'taxonomy' => 'especificacoes',
                            'hierarchical' => 1,
                            'child_of' => $post_data['e'],			
                            'hide_empty' => 1,
                            'orderby' => 'name',
                        );
                        $categs3_ = get_terms($args3_);
                        foreach($categs3_ as $categ3_) :
                        ?>
                        <option value="<?php echo $categ3_->term_id; ?>" <?php if( isset( $post_data['e_'] ) ) : selected( $post_data['e_'], $categ3_->term_id ); endif; ?> ><?php echo $categ3_->name; ?></option>
                        <?php
                        endforeach;
                        endif; 
                        ?>
                    </select>
                    <span class="close-field <?php if( isset( $post_data['e_'] ) && $post_data['e_'] != '' ) echo 'active'; ?>" data-field="e_">X</span>
                </div>
                
            </div><!-- /col -->
            
            <?php //if( !is_front_page() ) : ?>
            <div class="s-12 col"><hr /></div>
            <div class="s-12 m-4 col">
                
                <div class="box-input">
                    <span class="fas fa-cube"></span>
                    <div class="item">
                        <select id="conservacao_busca" name="conservacao">
                            <option value="">Conservação</option>
                            <option <?php if ( isset( $post_data['conservacao'] ) && $post_data['conservacao'] == 'Usado' ) selected( $post_data['conservacao'], 'Usado' ); ?> value="Usado">Usado</option>
                            <option <?php if ( isset( $post_data['conservacao'] ) && $post_data['conservacao'] == 'Novo' ) selected( $post_data['conservacao'], 'Novo' ); ?> value="Novo">Novo</option>
                            <option <?php if ( isset( $post_data['conservacao'] ) && $post_data['conservacao'] == 'Seminovo' ) selected( $post_data['conservacao'], 'Seminovo' ); ?> value="Seminovo">Seminovo</option>
                        </select>
                        <span class="close-field <?php if( isset( $post_data['conservacao'] ) && $post_data['conservacao'] != '' ) echo 'active'; ?>" data-field="conservacao_busca">X</span>
                    </div>                                
                </div><!-- /box-input -->
                                                                                        
            </div><!-- /col -->
            <div class="s-12 m-4 col">

                <div class="box-input">
                    <span class="fas fa-calendar-alt"></span>                        
                    <div class="item">

                        <select name="ano" id="ano_busca">
                            <option value="">Ano</option>
                            <?php for ($n = date("Y"); $n >= 1970; $n--) : ?>
                            <option value="<?php echo $n; ?>" <?php if ( isset( $post_data['ano'] ) ) selected( $post_data['ano'], $n ); ?>><?php echo $n; ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="close-field <?php if( isset( $post_data['ano'] ) && $post_data['ano'] != '' ) echo 'active'; ?>" data-field="ano_busca">X</span>

                    </div><!-- /item -->
                </div><!-- /box-input -->
            
            </div><!-- /col -->
            <div class="s-12 m-4 col">
                
                <div class="box-input">
                    <span class="fas fa-cube"></span>
                    <div class="item">

                        <select id="material_busca" name="material">
                            <option value="">Material</option>
                            <?php
                            $args = array(
                                'taxonomy' => 'material',
                                'hierarchical' => 1,							
                                'hide_empty' => 1,
                                'orderby' => 'name',
                            );
                            $categs = get_terms($args);
                            foreach($categs as $categ) :
                            ?>
                            <option value="<?php echo $categ->term_id; ?>" <?php if ( isset( $post_data['material'] ) ) selected( $post_data['material'], $categ->term_id ); ?>><?php echo $categ->name; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="close-field <?php if( isset( $post_data['material'] ) && $post_data['material'] != '' ) echo 'active'; ?>" data-field="material_busca">X</span>
                        

                    </div><!-- /item -->
                </div><!-- /box-input -->
                                                                                        
            </div><!-- /col -->
            <div class="s-12 m-4 col">
                
                <div class="box-input">
                    <span class="fas fa-paint-brush"></span>                        
                    <div class="item">
                        
                        <select name="cor" id="cor_busca">
                            <option value="">Cor predominante</option>
                            <?php
                            $args = array(
                                'taxonomy' => 'cor',
                                'hierarchical' => 1,
                                'hide_empty' => true,
                                //'orderby' => 'name',
                            );
                            $categs = get_terms($args);
                            foreach($categs as $categ) :
                            ?>
                            <option value="<?php echo $categ->term_id; ?>" <?php if ( isset( $post_data['cor'] ) ) selected( $post_data['cor'], $categ->term_id ); ?>><?php echo $categ->name; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="close-field <?php if( isset( $post_data['cor'] ) && $post_data['cor'] != '' ) echo 'active'; ?>" data-field="cor_busca">X</span>                        
                        
                    </div><!-- /item -->
                </div><!-- /box-input -->
            
            </div><!-- /col -->
            <div class="s-12 m-4 col">
                
                <div class="row align-middle box-input">
                    <div class="col shrink reset">
                        <span class="fas fa-money-bill-alt"></span>
                    </div>                    
                    <div class="col reset relative">
                        <select name="valor_de" id="valor_de_busca">
                            <option value="">Valor de:</option>
                            <?php for($n = 0; $n <= 30000; $n+=500) : ?>
                            <option value="<?php echo $n; ?>" <?php if( isset ( $post_data['valor_de'] ) ) selected( $post_data['valor_de'], $n ); ?>><?php echo 'R$ '.number_format($n,2,',','.'); ?></option>
                            <?php endfor; ?>

                        </select>                        
                        <span class="close-field <?php if( isset( $post_data['valor_de'] ) && $post_data['valor_de'] != '' ) echo 'active'; ?>" data-field="valor_de_busca">X</span>                        
                    </div>
                </div>
                <!-- /box-input -->
                
            </div><!-- /col -->
            <div class="s-12 m-4 col">
                
                <div class="box-input">
                    <span class="fas fa-money-bill-alt"></span>                        
                    <div class="item">
                        
                        <select name="valor_ate" id="valor_ate_busca">
                            <option value="">Valor até:</option>
                            <?php for($n = 0; $n <= 30000; $n+=500) : ?>
                            <option value="<?php echo $n; ?>" <?php if( isset ( $post_data['valor_ate'] ) ) selected( $post_data['valor_ate'], $n ); ?>><?php echo 'R$ '.number_format($n,2,',','.'); ?></option>
                            <?php endfor; ?>
                            
                        </select>
                        <span class="close-field <?php if( isset( $post_data['valor_ate'] ) && $post_data['valor_ate'] != '' ) echo 'active'; ?>" data-field="valor_ate_busca">X</span>

                    </div>
                </div>
                <!-- /box-input -->
                
            </div><!-- /col -->
            <div class="s-12 col"><hr /></div>
            <?php //endif; ?>
                 
            <div class="s-12 m-4 col">
                    
                <div class="relative">
                    <select id="estado_" name="estado" data-categ="cidade" data-child="cidade_" class="list-sub-item">
                        <option value="">Estado</option>
                        <?php 
                        $args = array(
                            'taxonomy' => 'cidade',
                            'hierarchical' => 1,
                            'parent' => 0,				
                            'hide_empty' => 1,
                            'orderby' => 'name',
                        );			
                        $categs = get_terms($args);
                        foreach($categs as $categ) :                            
                        ?>
                        <option value="<?php echo $categ->term_id; ?>" <?php if( isset( $post_data['estado'] ) ) : selected( $post_data['estado'], $categ->term_id ); endif; ?>><?php echo $categ->name; ?></option>
                        <?php endforeach; ?>				
                    </select>
                    <span class="close-field <?php if( isset($post_data['estado']) && $post_data['estado'] != '' ) : echo 'active'; endif; ?>" data-field="estado_">X</span>
                </div>
            
            </div><!-- /col -->
            <div class="s-12 m-4 col">

                <div class="relative">
                    <select id="cidade_" name="cidade">
                        <option value="">Cidade</option>
                        <?php 
                        if ( isset( $post_data['cidade'] ) && !empty( $post_data['cidade'] ) || isset( $post_data['cidade'] ) && !empty( $post_data['cidade'] ) ) :
                        $args = array(
                            'taxonomy' => 'cidade',
                            'hierarchical' => 1,
                            'child_of' => $post_data['estado'],			
                            'hide_empty' => 1,
                            'orderby' => 'name',
                        );
                        $categs = get_terms($args);
                        foreach($categs as $categ) :
                        ?>
                        <option value="<?php echo $categ->term_id; ?>" <?php if( isset( $post_data['cidade'] ) ) :  selected( $post_data['cidade'], $categ->term_id ); endif; ?>><?php echo $categ->name; ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                    <span class="close-field <?php if( isset( $post_data['cidade'] ) && $post_data['cidade'] != '' ) echo 'active'; ?>" data-field="cidade_">X</span>
                </div>

            </div><!-- /col -->
            <div class="s-12 m-4 col">

                <div class="row align-middle">
                    <div class="shrink col reset-right">

                        <a id="clear-search" href="#" class="clear-search black <?php if( $_SERVER["QUERY_STRING"] == null ) echo 'disabled'; ?>" title="Limpar busca">
                            <i class="fas fa-undo"></i><small class="show-for-s-only">Limpar</small>
                        </a>

                    </div><!-- /col -->
                    <div class="col">

                        <input id="bt-busca-anuncio" type="submit" class="bt-enviar" value="Buscar anúncio" /> 
                        <input id="bt-busca-anuncio-mobile" type="submit" class="bt-enviar bt-mobile" value="Buscar anúncio" /> 
                        <input name="action" type="hidden" value="buscar_anuncio" /> 

                    </div><!-- /col -->
                </div><!-- /row -->

            </div><!-- /col -->
        </div><!-- /row -->

    </form>

</div><!-- /form-box -->