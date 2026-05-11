<?php
add_action('wp_ajax_bazar_attachment_delete', 'bazar_attachment_delete');
add_action('wp_ajax_nopriv_bazar_attachment_delete', 'bazar_attachment_delete');
function bazar_attachment_delete() {
    $object = new __Bazar_Attachment_Delete();
    wp_die();
}
class __Bazar_Attachment_Delete{
    
    public $action;
    public $file_id;
    
	public function __construct( $action = null, $file_id = null ) {

        $this->action = ( isset( $_POST['action'] ) ) ? 
            wp_strip_all_tags( $_POST['action'] ) : 
            wp_strip_all_tags( $action );

        if ( !empty( $this->action ) && $this->action == 'bazar_attachment_delete') :

            $this->file_id = ( isset( $_POST["file_id"] ) ) ? 
                wp_strip_all_tags( $_POST["file_id"] ) : 
                wp_strip_all_tags( $file_id );
            
            $this->_bazar_delete_file();
        
        endif;

        return;
	}

    public function _bazar_delete_file( $file_id = null ){

        $delete_id = ( $file_id == null ) ? 
            $this->file_id : 
            $file_id;

        wp_delete_attachment( $delete_id, true );

    }
	
}
?>