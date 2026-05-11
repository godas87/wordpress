<?php
/**
 * Classe para validação anti-spam e anti-phishing de mensagens
 * Usada especificamente para validar mensagens de usuário para usuário
 */
class __Bazar_Message_Anti_Spam {
    
    /**
     * Palavras-chave de phishing que devem bloquear a mensagem
     * @var array
     */
    private $phishing_keywords = array(
        // Urgência e pressão
        'urgent',
        'as soon as possible',
        'immediate action',
        'act now',
        'verify your',
        'verify bank',
        'verify account',
        'verification link',
        'verification process',
        'complete verification',
        
        // Ameaças e suspensão
        'suspended',
        'temporary suspension',
        'limited access',
        'account will be',
        'failure to complete',
        'may result in',
        
        // Informações bancárias
        'bank details',
        'banking information',
        'banking details',
        'payouts',
        'payment information',
        'financial information',
        
        // Links suspeitos
        'click here',
        'follow this link',
        'access now',
        'verify now',
        'click the link',
        
        // Impersonação do sistema
        'from XXXXXX',
        'XXXXXX time',
        'XXXXXX team',
        'XXXXXX support',
        'XXXXXX account',
        'bazar bikes team',
        'bazar bikes support',
        'bazar bikes team email',
        'bazar bikes support email',
        'bazar bikes account email',
        'bazar bikes team email',
        'bazar bikes support email',
        'bazar bikes account email',
        'bazar bikes team email',
        'bazar bikes support email',
        'bazar bikes account email',
    );
    
    
    /**
     * Tamanho máximo da mensagem em caracteres
     * @var int
     */
    private $max_message_length = 1000;
    
    /**
     * Tamanho mínimo da mensagem em caracteres
     * @var int
     */
    private $min_message_length = 10;
    
    /**
     * Valida mensagem contra spam e phishing
     * @param string $message Mensagem a ser validada
     * @return array Retorna array com 'valid' => bool e 'error' => string|null
     */
    public function validate_message( $message ) {
        
        if( empty($message) || !is_string($message) ) {
            return array(
                'valid' => false,
                'error' => 'Mensagem vazia ou inválida.'
            );
        }
        
        // Normalizar mensagem para validação (minúsculas, sem espaços extras)
        $normalized = $this->normalize_message( $message );
        
        // 1. Validar tamanho
        $length_check = $this->validate_length( $message );
        if( !$length_check['valid'] ) {
            return $length_check;
        }
        
        // 2. Detectar palavras-chave de phishing
        $phishing_check = $this->detect_phishing_keywords( $normalized );
        if( !$phishing_check['valid'] ) {
            return $phishing_check;
        }
        
        // 3. Validar e detectar links suspeitos
        $links_check = $this->validate_links( $message );
        if( !$links_check['valid'] ) {
            return $links_check;
        }
        
        // 4. Detectar tentativa de impersonação
        $impersonation_check = $this->detect_impersonation( $normalized );
        if( !$impersonation_check['valid'] ) {
            return $impersonation_check;
        }
        
        // 5. Detectar padrões de spam
        $spam_check = $this->detect_spam_patterns( $message );
        if( !$spam_check['valid'] ) {
            return $spam_check;
        }
        
        // Todas as validações passaram
        return array(
            'valid' => true,
            'error' => null
        );
    }
    
    /**
     * Normaliza mensagem para validação (remove acentos, converte para minúsculas)
     * @param string $message
     * @return string
     */
    private function normalize_message( $message ) {
        // Converter para minúsculas
        $normalized = mb_strtolower( $message, 'UTF-8' );
        
        // Remover acentos para melhor detecção
        $normalized = $this->remove_accents( $normalized );
        
        // Remover espaços extras
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return trim( $normalized );
    }
    
    /**
     * Remove acentos de uma string
     * @param string $string
     * @return string
     */
    private function remove_accents( $string ) {
        $accents = array(
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n'
        );
        return strtr( $string, $accents );
    }
    
    /**
     * Valida tamanho da mensagem
     * @param string $message
     * @return array
     */
    private function validate_length( $message ) {
        $length = mb_strlen( $message, 'UTF-8' );
        
        if( $length < $this->min_message_length ) {
            return array(
                'valid' => false,
                'error' => 'A mensagem é muito curta. Por favor, escreva uma mensagem mais completa.'
            );
        }
        
        if( $length > $this->max_message_length ) {
            return array(
                'valid' => false,
                'error' => 'A mensagem é muito longa. Por favor, reduza para no máximo ' . $this->max_message_length . ' caracteres.'
            );
        }
        
        return array( 'valid' => true );
    }
    
    /**
     * Detecta palavras-chave de phishing na mensagem
     * @param string $normalized_message Mensagem normalizada
     * @return array
     */
    private function detect_phishing_keywords( $normalized_message ) {
        
        foreach( $this->phishing_keywords as $keyword ) {
            // Buscar palavra-chave na mensagem (case-insensitive já está normalizado)
            if( strpos( $normalized_message, $keyword ) !== false ) {
                return array(
                    'valid' => false,
                    'error' => 'Sua mensagem contém conteúdo suspeito e não pode ser enviada. Por favor, revise sua mensagem.'
                );
            }
        }
        
        return array( 'valid' => true );
    }
    
    /**
     * Valida links na mensagem
     * Bloqueia todos os links no corpo da mensagem
     * @param string $message
     * @return array
     */
    private function validate_links( $message ) {
        
        // Detectar URLs na mensagem (http, https, www, e formatos comuns)
        $url_patterns = array(
            '/(https?:\/\/[^\s]+)/i',           // http:// ou https://
            '/(www\.[^\s]+)/i',                 // www.exemplo.com
            '/([a-z0-9-]+\.(com|net|org|br|io|co|me|info|biz|tv|cc)[^\s]*)/i', // domínios comuns sem protocolo
        );
        
        foreach( $url_patterns as $pattern ) {
            if( preg_match( $pattern, $message, $matches ) ) {
                return array(
                    'valid' => false,
                    'error' => 'Links não são permitidos no corpo da mensagem. Por favor, remova todos os links antes de enviar.'
                );
            }
        }
        
        return array( 'valid' => true );
    }
    
    
    /**
     * Detecta tentativa de impersonação do sistema
     * @param string $normalized_message Mensagem normalizada
     * @return array
     */
    private function detect_impersonation( $normalized_message ) {
        
        // Padrões simples de impersonação (busca direta)
        $simple_patterns = array(
            'from XXXXXX',
            'XXXXXX team',
            'XXXXXX support',
            'XXXXXX account',
            'bazar bikes team',
            'bazar bikes support',
            'the XXXXXX',
        );
        
        // Padrões complexos de impersonação (usar regex)
        $regex_patterns = array(
            '/this is.*notice.*XXXXXX/i',
            '/important notice.*XXXXXX/i',
            '/XXXXXX.*team/i',
            '/XXXXXX.*support/i',
        );
        
        // Verificar padrões simples
        foreach( $simple_patterns as $pattern ) {
            if( strpos( $normalized_message, $pattern ) !== false ) {
                return array(
                    'valid' => false,
                    'error' => 'Sua mensagem tenta se passar pelo sistema e não pode ser enviada. Por favor, revise sua mensagem.'
                );
            }
        }
        
        // Verificar padrões regex
        foreach( $regex_patterns as $pattern ) {
            if( preg_match( $pattern, $normalized_message ) ) {
                return array(
                    'valid' => false,
                    'error' => 'Sua mensagem tenta se passar pelo sistema e não pode ser enviada. Por favor, revise sua mensagem.'
                );
            }
        }
        
        return array( 'valid' => true );
    }
    
    /**
     * Detecta padrões comuns de spam
     * @param string $message
     * @return array
     */
    private function detect_spam_patterns( $message ) {
        
        // Verificar repetição excessiva de palavras (spam)
        $words = preg_split( '/\s+/', mb_strtolower( $message, 'UTF-8' ) );
        $word_counts = array_count_values( $words );
        
        foreach( $word_counts as $word => $count ) {
            // Se uma palavra aparece mais de 10 vezes e a mensagem tem menos de 200 caracteres, pode ser spam
            if( $count > 10 && mb_strlen( $message, 'UTF-8' ) < 200 ) {
                return array(
                    'valid' => false,
                    'error' => 'Sua mensagem contém repetição excessiva e não pode ser enviada. Por favor, revise sua mensagem.'
                );
            }
        }
        
        return array( 'valid' => true );
    }
    
    /**
     * Sanitiza mensagem removendo conteúdo suspeito
     * @param string $message
     * @return string Mensagem sanitizada
     */
    public function sanitize_message( $message ) {
        
        if( empty($message) || !is_string($message) ) {
            return '';
        }
        
        // Remover tags HTML (já deve estar feito, mas garantir)
        $sanitized = wp_strip_all_tags( $message );
        
        // Limitar tamanho
        if( mb_strlen( $sanitized, 'UTF-8' ) > $this->max_message_length ) {
            $sanitized = mb_substr( $sanitized, 0, $this->max_message_length, 'UTF-8' );
        }
        
        // Escape de caracteres HTML
        $sanitized = esc_html( $sanitized );
        
        // Remover quebras de linha excessivas
        $sanitized = preg_replace( '/\n{3,}/', "\n\n", $sanitized );
        
        return trim( $sanitized );
    }
}

