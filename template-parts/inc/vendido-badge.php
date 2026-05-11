<?php
/**
 * Badge de aviso para produtos vendidos
 * 
 * @package XXXXXX
 */
// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}
$class_small = ( isset($args['small']) && $args['small'] == true ) ? 'small' : '';
?>
<div class="vendido-badge <?php echo $class_small; ?>">
    <div class="bx_vendido">
        <i class="fa fa-check-circle"></i>
        <strong>Vendido</strong>
        <small>Este produto não está mais disponível</small>
    </div>
</div>