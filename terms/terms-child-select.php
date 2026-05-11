<?php
add_action('wp_ajax_bazar_list_subitem', 'bazar_list_subitem');
add_action('wp_ajax_nopriv_bazar_list_subitem', 'bazar_list_subitem');
function bazar_list_subitem() {
    $object = new __Bazar_List_Subitem();
    wp_die();
}

class __Bazar_List_Subitem{

	public function __construct() {	

		if( empty( $_POST["cat_id"] ) && empty( $_POST["tax"] ) ) :
			echo '<option value="">Erro ao buscar os dados</option>';
		endif;

		$allTerms = ( isset( $_POST['all'] ) && $_POST['all'] == 'true' ) ? true : false;
		$cat_id = intval( $_POST['cat_id'] );
		$taxonomy = sanitize_text_field($_POST["tax"]);

		echo '<option value="">Selecione</option>';		
						
			$arg = array(
				'taxonomy' => $taxonomy,
				'hierarchical' => 1,
				'hide_empty' => !$allTerms,
				'parent' => $cat_id
			);
			$cats = get_terms( $arg );
			if( $cats && !is_wp_error( $cats ) ) :
				// Ordena os termos usando a classe __Bazar_Terms_Manager
				$cats = __Bazar_Terms_Manager::ordenar($cats, $taxonomy );
				foreach( $cats as $cat ) :
					echo '<option value="'.$cat->term_id.'">'.$cat->name.'</option>';
				endforeach;
			else :
					echo '<option value="">Nenhum termo encontrado</option>';
			endif;
	}
};
?>