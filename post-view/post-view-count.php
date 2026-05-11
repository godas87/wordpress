<?php
/**
 * Classe para contabilizar visualizações de posts
 * Garante que cada post seja contado apenas uma vez por sessão
 * Usa wp_cache como fallback quando sessão não está disponível
 * 
 * @package XXXXXX
 */
class setPostViewsCount {
	
	/**
	 * Construtor
	 * 
	 * @param int $postID ID do post a ser contabilizado
	 */
	public function __construct( $postID ) {
		
		// Validar postID
		$postID = intval( $postID );
		if ( !$postID ) {
			return;
		}
		
		// Verificar se sessão já está ativa (iniciada no início do single.php)
		// Se não estiver ativa, usar wp_cache como fallback
		if ( session_status() === PHP_SESSION_ACTIVE && isset( $_SESSION ) ) {
			// Usar sessão (método preferido)
			$this->process_with_session( $postID );
		} else {
			// Usar wp_cache como fallback (quando sessão não funciona)
			$this->process_with_cache( $postID );
		}
	}
	
	/**
	 * Processa usando sessão PHP
	 */
	private function process_with_session( $postID ) {
		// Inicializar array de posts visualizados se não existir
		if ( !isset( $_SESSION['postViews'] ) || !is_array( $_SESSION['postViews'] ) ) {
			$_SESSION['postViews'] = array();
		}		
		// Usar chave específica no array para evitar duplicatas
		$session_key = 'post_' . $postID;
		// Verificar se este post já foi visualizado nesta sessão
		if ( !isset( $_SESSION['postViews'][$session_key] ) ) {
			// Marcar como visualizado ANTES de incrementar
			$_SESSION['postViews'][$session_key] = true;
			// Incrementar contador
			$this->increment_count( $postID );
		}
	}
	
	/**
	 * Processa usando wp_cache (fallback quando sessão não funciona)
	 */
	private function process_with_cache( $postID ) {
		// Obter IP do visitante para criar chave única
		$ip = $this->get_user_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$visitor_id = md5( $ip . '_' . $user_agent );
		
		// Criar chave única para este post + visitante
		$view_key = 'post_view_' . $postID . '_' . $visitor_id;
		$cache_group = 'bazar_post_views';
		
		// Verificar se já foi visualizado (cache expira em 24h)
		$already_viewed = wp_cache_get( $view_key, $cache_group );
		
		if ( $already_viewed === false ) {
			// Marcar como visualizado (24 horas = 86400 segundos)
			wp_cache_set( $view_key, true, $cache_group, 86400 );
			
			// Incrementar contador
			$this->increment_count( $postID );
		}
	}
	
	/**
	 * Obtém o IP do usuário para usar como identificador único
	 * 
	 * @return string IP do usuário
	 */
	private function get_user_ip(){
			// Usar função global se disponível, senão fallback
			if (function_exists('bazar_get_user_ip')) {
					return bazar_get_user_ip();
			}			
			// Fallback caso função não esteja disponível
			return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	}
	
	/**
	 * Incrementa o contador do post
	 */
	private function increment_count( $postID ) {
		$count_key = 'post_views_count';
		$count = get_post_meta( $postID, $count_key, true );
		
		if ( $count == '' || $count == false || $count === false ) {
			// Primeira visualização - criar meta com valor 1
			delete_post_meta( $postID, $count_key );
			add_post_meta( $postID, $count_key, '1' );
		} else {
			// Incrementar contador existente
			$count = intval( $count ) + 1;
			update_post_meta( $postID, $count_key, $count );
		}
	}
	
}
?>