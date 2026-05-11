<?php
/**
 * Gerenciador Genérico de Transients
 * Singleton para gerenciar todos os transients do sistema
 * 
 * @package XXXXXX
 */
class __Bazar_Transients {
    
    // ============================================
    // CONFIGURAÇÕES DE REGRA DE NEGÓCIO
    // ============================================
    // Ajuste estes valores para modificar o comportamento do sistema
    
    /**
     * Prefixo usado em todos os transients
     * @var string
     */
    private $prefix = 'bazar_';
    
    /**
     * LIMITES DE ENVIO POR IP
     */
    
    /**
     * Limite máximo de emails que um IP pode enviar por período
     * @var int
     */
    private static $ip_email_limit = 20;
    
    /**
     * Período de tempo (em segundos) para o limite de emails por IP
     * Padrão: 1 hora (3600 segundos)
     * @var int
     */
    private static $ip_email_period = 3600; // 1 hora
    
    /**
     * BLOQUEIO DE IP
     */
    
    /**
     * Período de bloqueio de IP quando excede limite (em segundos)
     * Padrão: 24 horas (86400 segundos)
     * @var int
     */
    private static $ip_block_period = 86400; // 24 horas
    
    /**
     * LIMITES DE ENVIO POR EMAIL+POST_ID
     */
    
    /**
     * Limite máximo de emails que um email pode enviar para o mesmo post_id por período
     * @var int
     */
    private static $email_post_limit = 1;
    
    /**
     * Período de tempo (em segundos) para o limite de emails por email+post_id
     * Padrão: 30 minutos (1800 segundos)
     * @var int
     */
    private static $email_post_period = 1800; // 30 minutos
    
    /**
     * CACHE DE LOCALIZAÇÃO
     */
    
    /**
     * Período de expiração do cache de localização (em segundos)
     * Padrão: 24 horas (86400 segundos)
     * @var int
     */
    private static $location_cache_period = 86400; // 24 horas
    
    // ============================================
    // SINGLETON
    // ============================================
    
    private static $instance = null;
    
    /**
     * Singleton pattern
     * @return __Bazar_Transients
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor privado (Singleton)
     */
    private function __construct() {
        // Inicialização se necessário no futuro
    }
    
    /**
     * Gera chave completa com prefixo
     * @param string $key Chave base
     * @param string $group Grupo (opcional)
     * @return string Chave completa
     */
    private function build_key( $key, $group = '') {
        $group_prefix = !empty($group) ? $group . '_' : '';
        return $this->prefix . $group_prefix . $key;
    }
    
    /**
     * GET - Obtém transient
     * @param string $key Chave do transient
     * @param string $group Grupo (opcional)
     * @return mixed Valor do transient ou false se não existir
     */
    private function get( $key, $group = '' ) {
        $full_key = $this->build_key($key, $group);
        return get_transient($full_key);
    }
    
    /**
     * SET - Define transient
     * O tempo de expiração é determinado automaticamente baseado no grupo
     * @param string $key Chave do transient
     * @param mixed $value Valor a ser armazenado
     * @param string $group Grupo (opcional) - usado para determinar expire automaticamente
     * @return bool True em caso de sucesso, false em caso de falha
     */
    private function set( $key, $value, $group = '' ) {
        // Determinar expire automaticamente baseado no grupo
        switch ($group) {
            case 'ip_limits':
                $expire = self::$ip_email_period;
                break;
            case 'email_limits':
                $expire = self::$email_post_period;
                break;
            case 'ip_blocks':
                $expire = self::$ip_block_period;
                break;
            case 'location':
                $expire = self::$location_cache_period;
                break;
            default:
                $expire = 3600; // Padrão genérico: 1 hora
                break;
        }
        
        $full_key = $this->build_key($key, $group);
        return set_transient($full_key, $value, $expire);
    }
    
    /**
     * DELETE - Remove transient
     * @param string $key Chave do transient
     * @param string $group Grupo (opcional)
     * @return bool True em caso de sucesso, false em caso de falha
     */
    private function delete( $key, $group = '' ) {
        $full_key = $this->build_key($key, $group);
        return delete_transient($full_key);
    }
    
    /**
     * EXISTS - Verifica se transient existe
     * @param string $key Chave do transient
     * @param string $group Grupo (opcional)
     * @return bool True se existe, false se não existe
     */
    private function exists($key, $group = '') {
        return ($this->get($key, $group) !== false);
    }
    
    // ============================================
    // MÉTODOS PÚBLICOS GENÉRICOS
    // ============================================
    
    /**
     * GET - Obtém transient genérico
     * @param string $key Chave do transient
     * @param string $group Grupo (opcional)
     * @return mixed Valor do transient ou false se não existir
     */
    public function get_transient($key, $group = '') {
        return $this->get($key, $group);
    }
    
    /**
     * SET - Define transient genérico com tempo de expiração customizado
     * @param string $key Chave do transient
     * @param mixed $value Valor a ser armazenado
     * @param int $expire Tempo de expiração em segundos
     * @param string $group Grupo (opcional)
     * @return bool True em caso de sucesso, false em caso de falha
     */
    public function set_transient($key, $value, $expire, $group = '') {
        $full_key = $this->build_key($key, $group);
        return set_transient($full_key, $value, $expire);
    }
    
    /**
     * DELETE - Remove transient genérico
     * @param string $key Chave do transient
     * @param string $group Grupo (opcional)
     * @return bool True em caso de sucesso, false em caso de falha
     */
    public function delete_transient($key, $group = '') {
        return $this->delete($key, $group);
    }
    
    // ============================================
    // MÉTODOS ESPECÍFICOS PARA RATE LIMITING
    // ============================================
    
    /**
     * Verifica rate limit (genérico)
     * @param string $identifier Identificador único (ex: email_post_id)
     * @param int $limit Limite máximo de ações
     * @param int $period Período em segundos
     * @param string $group Grupo do transient (padrão: 'rate_limit')
     * @return bool True se pode executar, false se limite atingido
     */
    private function check_rate_limit($identifier, $limit, $period, $group = 'rate_limit') {
        $key = md5($identifier);
        $data = $this->get($key, $group);
        
        if ($data === false) {
            return true; // Pode executar
        }
        
        return ($data['count'] < $limit);
    }
    
    /**
     * Incrementa contador de rate limit
     * @param string $identifier Identificador único
     * @param int $limit Limite máximo de ações
     * @param int $period Período em segundos
     * @param string $group Grupo do transient (padrão: 'rate_limit')
     * @return array Dados atualizados do contador
     */
    private function increment_rate_limit($identifier, $limit, $period, $group = 'rate_limit') {
        $key = md5($identifier);
        $data = $this->get($key, $group);
        
        if ($data === false) {
            // Primeiro incremento
            $data = array(
                'count' => 1,
                'first_action' => time(),
                'last_action' => time()
            );
        } else {
            // Incrementar contador
            $data['count']++;
            $data['last_action'] = time();
        }
        
        // set() determina o expire automaticamente baseado no grupo
        $this->set($key, $data, $group);
        return $data;
    }
    
    /**
     * Calcula tempo restante até poder executar novamente
     * @param string $identifier Identificador único
     * @param int $period Período em segundos
     * @param string $group Grupo do transient (padrão: 'rate_limit')
     * @return int Tempo restante em segundos, 0 se pode executar
     */
    private function get_remaining_time($identifier, $period, $group = 'rate_limit') {
        $key = md5($identifier);
        $data = $this->get($key, $group);
        
        if ($data === false) {
            return 0; // Pode executar
        }
        
        $elapsed = time() - $data['first_action'];
        $remaining = $period - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Obtém informações completas do rate limit
     * @param string $identifier Identificador único
     * @param string $group Grupo do transient (padrão: 'rate_limit')
     * @return array|false Dados do rate limit ou false se não existir
     */
    private function get_rate_limit_info( $identifier, $group = 'rate_limit' ) {
        $key = md5($identifier);
        return $this->get($key, $group);
    }
    
    /**
     * Reseta rate limit para um identificador
     * @param string $identifier Identificador único
     * @param string $group Grupo do transient (padrão: 'rate_limit')
     * @return bool True em caso de sucesso
     */
    private function reset_rate_limit($identifier, $group = 'rate_limit') {
        $key = md5($identifier);
        return $this->delete($key, $group);
    }
    
    // ============================================
    // MÉTODOS ESPECÍFICOS PARA EMAIL LIMITS
    // ============================================
    
    /**
     * Verifica limite de envio de email
     * @param string $email Email do remetente
     * @param int|string $post_id ID do anúncio
     * @param string|null $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @param int|null $limit Limite de envios (null = usa self::$email_post_limit)
     * @param int|null $period Período em segundos (null = usa self::$email_post_period)
     * @return bool True se pode enviar, false se limite atingido
     */
    public function check_email_limit($email, $post_id, $ip = null, $limit = null, $period = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        $limit = $limit ?? self::$email_post_limit;
        $period = $period ?? self::$email_post_period;
        
        // Se IP foi fornecido, usar ip+email+post_id, senão apenas email+post_id
        $identifier = ($ip !== null) 
            ? $ip . '_' . strtolower(trim($email)) . '_' . $post_id
            : strtolower(trim($email)) . '_' . $post_id;
            
        return $this->check_rate_limit($identifier, $limit, $period, 'email_limits');
    }
    
    /**
     * Incrementa contador de envio de email
     * @param string $email Email do remetente
     * @param int|string $post_id ID do anúncio
     * @param string|null $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @param int|null $limit Limite de envios (null = usa self::$email_post_limit)
     * @param int|null $period Período em segundos (null = usa self::$email_post_period)
     * @return array Dados atualizados do contador
     */
    public function increment_email_limit($email, $post_id, $ip = null, $limit = null, $period = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        $limit = $limit ?? self::$email_post_limit;
        $period = $period ?? self::$email_post_period;
        
        // Se IP foi fornecido, usar ip+email+post_id, senão apenas email+post_id
        $identifier = ($ip !== null) 
            ? $ip . '_' . strtolower(trim($email)) . '_' . $post_id
            : strtolower(trim($email)) . '_' . $post_id;
            
        return $this->increment_rate_limit($identifier, $limit, $period, 'email_limits');
    }
    
    /**
     * Calcula tempo restante para envio de email
     * @param string $email Email do remetente
     * @param int|string $post_id ID do anúncio
     * @param string|null $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @param int|null $period Período em segundos (null = usa self::$email_post_period)
     * @return int Tempo restante em segundos, 0 se pode enviar
     */
    public function get_email_remaining_time($email, $post_id, $ip = null, $period = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        $period = $period ?? self::$email_post_period;
        
        // Se IP foi fornecido, usar ip+email+post_id, senão apenas email+post_id
        $identifier = ($ip !== null) 
            ? $ip . '_' . strtolower(trim($email)) . '_' . $post_id
            : strtolower(trim($email)) . '_' . $post_id;
            
        return $this->get_remaining_time($identifier, $period, 'email_limits');
    }
    
    /**
     * Obtém informações do limite de email
     * @param string $email Email do remetente
     * @param int|string $post_id ID do anúncio
     * @param string|null $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @return array|false Dados do limite ou false se não existir
     */
    public function get_email_limit_info( $email, $post_id, $ip = null ) {
        // Se IP foi fornecido, usar ip+email+post_id, senão apenas email+post_id
        $identifier = ($ip !== null) 
            ? $ip . '_' . strtolower(trim($email)) . '_' . $post_id
            : strtolower(trim($email)) . '_' . $post_id;
            
        return $this->get_rate_limit_info(
            $identifier, 
            'email_limits'
        );
    }
    
    /**
     * Reseta limite de email para um email/post_id específico
     * @param string $email Email do remetente
     * @param int|string $post_id ID do anúncio
     * @param string|null $ip Endereço IP (opcional, se null usa apenas email+post_id)
     * @return bool True em caso de sucesso
     */
    public function reset_email_limit($email, $post_id, $ip = null) {
        // Se IP foi fornecido, usar ip+email+post_id, senão apenas email+post_id
        $identifier = ($ip !== null) 
            ? $ip . '_' . strtolower(trim($email)) . '_' . $post_id
            : strtolower(trim($email)) . '_' . $post_id;
            
        return $this->reset_rate_limit($identifier, 'email_limits');
    }
    
    // ============================================
    // MÉTODOS ESPECÍFICOS PARA LOCATION CACHE
    // ============================================
    
    /**
     * Salva localização no cache
     * @param int|string $user_id ID do usuário
     * @param array $location_data Dados de localização
     * @return bool True em caso de sucesso
     */
    public function save_location($user_id, $location_data) {
        $key = 'user_' . $user_id;
        // set() usa self::$location_cache_period automaticamente para grupo 'location'
        return $this->set($key, $location_data, 'location');
    }
    
    /**
     * Carrega localização do cache
     * @param int|string $user_id ID do usuário
     * @return array|false Dados de localização ou false se não existir
     */
    public function get_location($user_id) {
        $key = 'user_' . $user_id;
        return $this->get($key, 'location');
    }
    
    /**
     * Remove localização do cache
     * @param int|string $user_id ID do usuário
     * @return bool True em caso de sucesso
     */
    public function delete_location($user_id) {
        $key = 'user_' . $user_id;
        return $this->delete($key, 'location');
    }
    
    // ============================================
    // MÉTODOS ESPECÍFICOS PARA IP LIMITS
    // ============================================
    
    /**
     * Obtém IP real do usuário (considerando proxies)
     * Usa função global bazar_get_user_ip() se disponível
     * 
     * @return string IP do usuário
     */
    public static function get_user_ip(){
        // Usar função global se disponível, senão fallback
        if (function_exists('bazar_get_user_ip')) {
            return bazar_get_user_ip();
        }			
        // Fallback caso função não esteja disponível
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Verifica se IP está bloqueado
     * @param string $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @return bool True se está bloqueado, false se não está
     */
    public function is_ip_blocked($ip = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        
        $key = 'blocked_' . md5($ip);
        $blocked = $this->get($key, 'ip_blocks');
        
        return ( $blocked !== false );
    }
    
    /**
     * Bloqueia IP temporariamente
     * Usa self::$ip_block_period automaticamente
     * @param string $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @return bool True em caso de sucesso
     */
    public function block_ip($ip = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        
        $period = self::$ip_block_period;
        
        $key = 'blocked_' . md5($ip);
        $data = array(
            'ip' => $ip,
            'blocked_at' => time(),
            'blocked_until' => time() + $period
        );
        
        // set() usa self::$ip_block_period automaticamente para grupo 'ip_blocks'
        return $this->set($key, $data, 'ip_blocks');
    }
    
    /**
     * Desbloqueia IP
     * @param string $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @return bool True em caso de sucesso
     */
    public function unblock_ip($ip = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        
        $key = 'blocked_' . md5($ip);
        return $this->delete($key, 'ip_blocks');
    }
    
    /**
     * Verifica limite de envios por IP
     * @param string $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @param int|null $limit Limite máximo de envios (null = usa self::$ip_email_limit)
     * @param int|null $period Período em segundos (null = usa self::$ip_email_period)
     * @return bool True se pode enviar, false se limite atingido
     */
    public function check_ip_limit($ip = null, $limit = null, $period = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        
        $limit = $limit ?? self::$ip_email_limit;
        $period = $period ?? self::$ip_email_period;
        $identifier = 'ip_' . $ip;
        
        return $this->check_rate_limit($identifier, $limit, $period, 'ip_limits');
    }
    
    /**
     * Incrementa contador de envios por IP
     * @param string $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @param int|null $limit Limite máximo de envios (null = usa self::$ip_email_limit)
     * @param int|null $period Período em segundos (null = usa self::$ip_email_period)
     * @return array Dados atualizados do contador
     */
    public function increment_ip_limit($ip = null, $limit = null, $period = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        
        $limit = $limit ?? self::$ip_email_limit;
        $period = $period ?? self::$ip_email_period;
        $identifier = 'ip_' . $ip;
        
        $data = $this->increment_rate_limit($identifier, $limit, $period, 'ip_limits');
        
        // Se excedeu o limite, bloquear IP
        if ($data['count'] >= $limit) {
            $this->block_ip($ip);
        }
        
        return $data;
    }
    
    /**
     * Calcula tempo restante para IP poder enviar novamente
     * @param string $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @param int|null $period Período em segundos (null = usa self::$ip_email_period)
     * @return int Tempo restante em segundos, 0 se pode enviar
     */
    public function get_ip_remaining_time($ip = null, $period = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        
        $period = $period ?? self::$ip_email_period;
        $identifier = 'ip_' . $ip;
        
        return $this->get_remaining_time($identifier, $period, 'ip_limits');
    }
    
    /**
     * Obtém informações do limite de IP
     * @param string $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @return array|false Dados do limite ou false se não existir
     */
    public function get_ip_limit_info($ip = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        
        $identifier = 'ip_' . $ip;
        return $this->get_rate_limit_info($identifier, 'ip_limits');
    }
    
    /**
     * Reseta limite de IP
     * @param string $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @return bool True em caso de sucesso
     */
    public function reset_ip_limit($ip = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        
        $identifier = 'ip_' . $ip;
        return $this->reset_rate_limit($identifier, 'ip_limits');
    }
    
    /**
     * Verifica tempo restante de bloqueio de IP
     * @param string $ip Endereço IP (opcional, se não informado obtém automaticamente)
     * @return int Tempo restante em segundos, 0 se não está bloqueado
     */
    public function get_ip_block_remaining_time($ip = null) {
        if ($ip === null) {
            $ip = self::get_user_ip();
        }
        
        $key = 'blocked_' . md5($ip);
        $blocked = $this->get($key, 'ip_blocks');
        
        if ($blocked === false) {
            return 0; // Não está bloqueado
        }
        
        $remaining = $blocked['blocked_until'] - time();
        return max(0, $remaining);
    }
    
    /**
     * Previne clonagem (Singleton)
     */
    private function __clone() {}
    
    /**
     * Previne unserialize (Singleton)
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>

