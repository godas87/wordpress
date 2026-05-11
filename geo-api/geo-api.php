<?php
/**
 * XXXXXX GeoAPI - Sistema de Localização Baseado em CEP
 * 
 * Componente principal para busca de CEP com coordenadas e cache inteligente
 * Arquiteturalmente preparado para migração entre BrasilAPI V2 e GeocodeR
 * 
 * CACHE: Utiliza um único cache de localização (chave fixa: 'current_location')
 * que funciona para usuários logados e guests, garantindo consistência.
 * O campo 'source' (api_fonte) identifica a origem dos dados.
 * 
 * @package XXXXXX
 * @version 1.0.0
 */

class BazarBikes_GeoAPI
{

    private static $instance = null;
    private $api_provider = 'unknown';
    private $default_provider = 'viacep' | 'brasilapi_v2';
    private $data_api_output = array();
    private $result_output = array();

    private $cache_key = 'current_location';
    private $cache_group = 'user_location';

    /**
     * Singleton pattern
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Inicializar array de dados da API
        $this->init_api_output();
        // Inicializar array de dados de saída
        $this->init_result_output();
    }

    private function init_api_output()
    {
        // Inicializar array de dados de saída
        $this->data_api_output = array(
            'cep' => '',
            'logradouro' => '',
            'complemento' => '',
            'unidade' => '',
            'bairro' => '',
            'cidade' => '',
            'estado_sigla' => '',
            'estado' => '',
            'cidade_term_id' => '',
            'estado_term_id' => '',
            'regiao' => '',
            'ibge' => '',
            'gia' => '',
            'ddd' => '',
            'siafi' => '',
            'api_fonte' => $this->api_provider
        );
    }

    private function init_result_output()
    {
        // Inicializar array de dados de saída
        $this->result_output = array(
            'localizacao' => array(
                'cep' => "",
                'cidade' => "",
                'estado' => "",
                'estado_sigla' => "",
                'cidade_term_id' => "",
                'estado_term_id' => "",
                'bairro' => "",
                'logradouro' => "",
                'latitude' => "",
                'longitude' => ""
            ),
            'proximidade' => array(
                'cep_base' => "",
                'regiao_postal' => "",
                'sub_regiao' => "",
                'setor' => "",
                'subsetor' => ""
            ),
            'meta' => array(
                'find_result' => false,
                'fonte_dados' => $this->api_provider,
                'timestamp' => current_time('c'),
                'tem_coordenadas' => "",
            )
        );

        // Retornar o array inicializado
        return $this->result_output;
    }

    /**
     * Preencher data_api_output de forma centralizada
     * Reduz repetição de código e mantém consistência
     * 
     * @param array $data Array com dados de localização
     * @param string $api_fonte Fonte dos dados (ex: 'modal_escolhida', 'user_profile', 'viaCep')
     * @return array $this->data_api_output
     */
    private function fill_data_api_output($data = array(), $api_fonte = 'unknown')
    {

        // Limpa valores anteriores
        $this->init_api_output();

        $normalize_data = array(
            // Mapeamento de campos básicos
            'cep' => $data['cep'] ?? '',
            'cidade' => $data['cidade'] ?? '',
            'estado' => $data['estado'] ?? '',
            'estado_sigla' => $data['estado_sigla'] ?? '',
            'cidade_term_id' => isset($data['cidade_term_id']) ? intval($data['cidade_term_id']) : 0,
            'estado_term_id' => isset($data['estado_term_id']) ? intval($data['estado_term_id']) : 0,
            'bairro' => $data['bairro'] ?? '',
            'logradouro' => $data['logradouro'] ?? '',
            // Campos opcionais (dependem da fonte)
            'latitude' => $data['latitude'] ?? '',
            'longitude' => $data['longitude'] ?? '',
            'ddd' => $data['ddd'] ?? '',
            'regiao' => $data['regiao'] ?? '',
            'ibge' => $data['ibge'] ?? '',
            'gia' => $data['gia'] ?? '',
            'siafi' => $data['siafi'] ?? '',
            // Dados de proximidade (se disponíveis)
            'proximidade_cep_base' => $data['proximidade_cep_base'] ?? '',
            'proximidade_regiao_postal' => $data['proximidade_regiao_postal'] ?? '',
            'proximidade_sub_regiao' => $data['proximidade_sub_regiao'] ?? '',
            'proximidade_setor' => $data['proximidade_setor'] ?? '',
            'proximidade_subsetor' => $data['proximidade_subsetor'] ?? '',
            'api_fonte' => $api_fonte,
        );

        return $this->data_api_output = $normalize_data;
    }

    /**
     * Persistir localização no cache único (mesmo formato que fill_data_api_output).
     * Usado pela sidebar (ajax_save_location_selection) e extensível.
     *
     * @param array $location_data Campos aceites por fill_data_api_output; opcional 'source'.
     * @return bool
     */
    public function save_location($location_data = array())
    {
        if (empty($location_data) || !is_array($location_data)) {
            return false;
        }

        $source = isset($location_data['source']) ? (string) $location_data['source'] : 'sidebar_selection';
        $payload = $location_data;
        unset($payload['source']);

        $normalized = $this->fill_data_api_output($payload, $source);
        if (empty($normalized) || !is_array($normalized)) {
            return false;
        }

        $this->set_location_cache($normalized);
        return true;
    }

    /**
     * AJAX: Buscar CEP
     * 
     * @return array $dados: $this->result_output | return
     */
    public function ajax_buscar_cep()
    {

        // error_log('BazarBikes_GeoAPI()->ajax_buscar_cep()->iniciado: ' . print_r($_POST, true));

        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bazar_geo_nonce')) {
            error_log('BazarBikes_GeoAPI()->ajax_buscar_cep()->Nonce inválido');
            wp_send_json_error('Erro de segurança');
            return;
        }
        // Verificar se o CEP é válido
        $cep = sanitize_text_field($_POST['cep'] ?? '');
        if (empty($cep)) {
            error_log('BazarBikes_GeoAPI()->ajax_buscar_cep()->CEP vazio');
            wp_send_json_error('CEP é obrigatório');
            return;
        }
        // Valida e Busca CEP
        $dados = $this->buscar_cep($cep);
        // Se CEP não foi encontrado, retornar erro
        if ($dados === false) {
            wp_send_json_error('CEP não encontrado');
            return;
        }
        // Retornar dados encontrados
        wp_send_json_success($dados);
    }

    /**
     * AJAX: Salvar seleção de localização da sidebar
     * Salva estado/cidade selecionados no cache único
     */
    public function ajax_save_location_selection()
    {

        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bazar_geo_nonce')) {
            wp_send_json_error('Erro de segurança');
            return;
        }

        // Obter dados
        $estado_term_id = isset($_POST['estado_term_id']) ? intval($_POST['estado_term_id']) : 0;
        $cidade_term_id = isset($_POST['cidade_term_id']) ? intval($_POST['cidade_term_id']) : 0;

        // Validar que pelo menos estado foi selecionado
        if ($estado_term_id <= 0) {
            wp_send_json_error('Estado é obrigatório');
            return;
        }

        // Buscar termo do estado
        $estado_term = get_term($estado_term_id, 'cidade');
        if (!$estado_term || is_wp_error($estado_term) || $estado_term->parent != 0) {
            wp_send_json_error('Estado inválido');
            return;
        }

        $location_data = array(
            'estado' => $estado_term->name,
            'estado_sigla' => strtoupper($estado_term->slug),
            'estado_term_id' => $estado_term_id,
            'cidade_term_id' => 0,
            'source' => 'sidebar_selection'
        );

        // Se cidade foi selecionada, buscar termo da cidade
        if ($cidade_term_id > 0) {
            $cidade_term = get_term($cidade_term_id, 'cidade');
            if ($cidade_term && !is_wp_error($cidade_term) && $cidade_term->parent == $estado_term_id) {
                $location_data['cidade'] = $cidade_term->name;
                $location_data['cidade_term_id'] = $cidade_term_id;
            }
        }

        // Salvar no cache único
        $saved = $this->save_location($location_data);

        if ($saved) {
            // Buscar slugs para retornar no response (útil para JavaScript construir URL)
            $estado_slug = strtolower($estado_term->slug);
            $cidade_slug = '';
            if ($cidade_term_id > 0) {
                $cidade_term = get_term($cidade_term_id, 'cidade');
                if ($cidade_term && !is_wp_error($cidade_term)) {
                    $cidade_slug = $cidade_term->slug;
                }
            }

            // Retornar dados salvos para uso no JavaScript
            wp_send_json_success(array(
                'message' => 'Localização salva com sucesso',
                'location' => $location_data,
                'slugs' => array(
                    'estado' => $estado_slug,
                    'cidade' => $cidade_slug
                )
            ));
        } else {
            wp_send_json_error('Erro ao salvar localização');
        }
    }

    /**
     * Buscar dados por CEP
     * 
     * @param string $cep CEP no formato XXXXX-XXX ou XXXXXXXX     
     * @return array $this->result_output_data | false
     */
    public function buscar_cep($cep): array|false
    {

        if (!$cep) {
            error_log('BazarBikes_GeoAPI()->buscar_cep()->CEP vazaio.');
            return false;
        }

        // Limpar e validar CEP
        $cep_limpo = $this->limpar_cep($cep);
        if (!$this->validar_cep($cep_limpo)) {
            error_log('BazarBikes_GeoAPI()->buscar_cep()->CEP inválido.');
            return false;
        }

        // Consultar API baseada no provedor configurado
        // $dados: $this->data_api_output = array()
        $dados = $this->consultar_api($cep_limpo);
        if ($dados != false) {
            return $this->result_output_data($dados);
        }

        // CEP não encontrado - retornar false
        error_log('BazarBikes_GeoAPI()->buscar_cep()->CEP não encontrado');
        return false;
    }

    /**
     * Consultar API baseada no provedor configurado
     * 
     * @param string $cep CEP no formato XXXXX-XXX ou XXXXXXXX
     * @return array $this->data_api_output | false
     */
    private function consultar_api($cep): array|false
    {

        // fallback para viacep
        $this->api_provider = 'viacep';
        $data = $this->consultar_viacep($cep);
        if (!$data) {
            // fallback para brasilapi_v2
            $this->api_provider = 'brasilapi_v2';
            $data = $this->consultar_brasilapi_v2($cep);
        }

        return $data;
    }

    /**
     * Consultar ViaCEP (fallback)
     * 
     * @param string $cep CEP no formato XXXXX-XXX ou XXXXXXXX
     * @return array $this->data_api_output | false
     */
    private function consultar_viacep($cep): array|false
    {

        if (!$cep)
            return false;

        $url = "https://viacep.com.br/ws/{$cep}/json/";

        $api_data = $this->process_consulta_api($url);
        if ($api_data) {
            return $this->response_viacep($api_data);
        }
        ;

        error_log('BazarBikes_GeoAPI()->consultar_viacep()->$api_data false');
        return false;
    }

    private function response_viacep($api_data = null): array|false
    {

        if (!$api_data)
            return false;

        // Verificar se a estrutura de dados está correta
        // https://viacep.com.br/
        // {
        //     "cep": "01001-000",
        //     "logradouro": "Praça da Sé",
        //     "complemento": "lado ímpar",
        //     "unidade": "",
        //     "bairro": "Sé",
        //     "localidade": "São Paulo",
        //     "uf": "SP",
        //     "estado": "São Paulo",
        //     "regiao": "Sudeste",
        //     "ibge": "3550308",
        //     "gia": "1004",
        //     "ddd": "11",
        //     "siafi": "7107"
        //   }

        // Normalizar dados da API ViaCEP para formato padrão
        $normalized_data = array(
            'cep' => $api_data['cep'] ?? '',
            'cidade' => $api_data['localidade'] ?? '',
            'estado' => $api_data['estado'] ?? '',
            'estado_sigla' => $api_data['uf'] ?? '',
            'regiao' => $api_data['regiao'] ?? '',
            'bairro' => $api_data['bairro'] ?? '',
            'logradouro' => $api_data['logradouro'] ?? '',
            'ibge' => $api_data['ibge'] ?? '',
            'gia' => $api_data['gia'] ?? '',
            'ddd' => $api_data['ddd'] ?? '',
            'siafi' => $api_data['siafi'] ?? '',
        );

        return $this->fill_data_api_output($normalized_data, 'viaCep');
    }

    /**
     * Consultar BrasilAPI V2 (com coordenadas)
     */
    private function consultar_brasilapi_v2($cep): array|false
    {
        if (!$cep)
            return false;

        $url = "https://brasilapi.com.br/api/cep/v2/{$cep}";

        $api_data = $this->process_consulta_api($url);
        if ($api_data) {
            return $this->response_brasilapi_v2($api_data);
        }

        error_log('BazarBikes_GeoAPI()->consultar_brasilapi_v2()->$api_data false');
        return false;
    }

    private function response_brasilapi_v2($api_data = null): array|false
    {

        if (!$api_data)
            return false;

        // Verificar se a estrutura de dados está correta
        // https://brasilapi.com.br/docs#tag/CEP-V2
        if (
            !isset($api_data['cep'])
            || !isset($api_data['state'])
            || !isset($api_data['city'])
            || !isset($api_data['neighborhood'])
            || !isset($api_data['street'])
        ) {
            error_log('BazarBikes_GeoAPI()->response_brasilapi_v2()->campos obrigatórios não encontrados');
            return false;
        }

        // Normalizar dados da API BrasilAPI V2 para formato padrão
        $normalized_data = array(
            'cep' => $api_data['cep'] ?? '',
            'cidade' => $api_data['city'] ?? '',
            'estado' => $this->get_state_name_by_slug($api_data['state'] ?? ''),
            'estado_sigla' => $api_data['state'] ?? '',
            'bairro' => $api_data['neighborhood'] ?? '',
            'logradouro' => $api_data['street'] ?? '',
            'latitude' => $api_data['location']['coordinates']['latitude'] ?? '',
            'longitude' => $api_data['location']['coordinates']['longitude'] ?? '',
        );

        return $this->fill_data_api_output($normalized_data, 'brasilapi_v2');
    }

    private function process_consulta_api($url)
    {

        if (!$url) {
            error_log('BazarBikes_GeoAPI()->process_consulta_api()->URL vazia');
            return false;
        }

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'XXXXXX/1.0'
        ));

        if (is_wp_error($response)) {
            error_log('BazarBikes_GeoAPI()->process_consulta_api()->$response->is_wp_error()');
            $response->get_error_message();
            return false;
        }

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body, true);

        if (!$data || isset($data['errors'])) {
            error_log('BazarBikes_GeoAPI()->process_consulta_api()->$data || isset($data["errors"])->json_decode error');
            return false;
        }

        return $data;
    }

    /**
     * Formatar dados de retorno
     * 
     * @param array $dados: $this->data_api_output = array()
     * @return array $this->result_output = array()|false
     */
    private function result_output_data($dados = array()): array
    {

        // Limpar resultado anterior
        $this->init_result_output();

        if (!empty($dados)) {

            $cep = $dados['cep'] ?? '';
            $cep_limpo = $this->limpar_cep($cep);
            if (!empty($cep_limpo)) {
                // Usar dados de proximidade do user_meta se disponíveis, senão calcular
                $proximidade_cep_base = $dados['proximidade_cep_base'] ?? '';
                $proximidade_regiao_postal = $dados['proximidade_regiao_postal'] ?? '';
                $proximidade_sub_regiao = $dados['proximidade_sub_regiao'] ?? '';
                $proximidade_setor = $dados['proximidade_setor'] ?? '';
                $proximidade_subsetor = $dados['proximidade_subsetor'] ?? '';
                // Se não tem dados de proximidade salvos mas tem CEP válido, calcular
                if (empty($proximidade_cep_base)) {
                    $proximidade_data = $this->process_proximity_data_by_cep($cep_limpo);
                    $proximidade_cep_base = $proximidade_data['proximidade_cep_base'] ?? '';
                    $proximidade_regiao_postal = $proximidade_data['regiao_postal'] ?? '';
                    $proximidade_sub_regiao = $proximidade_data['sub_regiao'] ?? '';
                    $proximidade_setor = $proximidade_data['setor'] ?? '';
                    $proximidade_subsetor = $proximidade_data['subsetor'] ?? '';
                }
                $this->result_output['proximidade'] = array(
                    'cep_base' => $proximidade_cep_base,
                    'regiao_postal' => $proximidade_regiao_postal,
                    'sub_regiao' => $proximidade_sub_regiao,
                    'setor' => $proximidade_setor,
                    'subsetor' => $proximidade_subsetor
                );
            }

            $this->result_output = array(
                'localizacao' => array(
                    'cep' => $cep,
                    'cidade' => $dados['cidade'] ?? '',
                    'estado' => $dados['estado'] ?? '',
                    'estado_sigla' => $dados['estado_sigla'] ?? '',
                    'cidade_term_id' => $dados['cidade_term_id'] ?? '',
                    'estado_term_id' => $dados['estado_term_id'] ?? '',
                    'bairro' => $dados['bairro'] ?? '',
                    'logradouro' => $dados['logradouro'] ?? '',
                    'latitude' => $dados['latitude'] ?? '',
                    'longitude' => $dados['longitude'] ?? ''
                ),
                'meta' => array(
                    'find_result' => true,
                    'fonte_dados' => $dados['api_fonte'] ?? 'unknown',
                    'is_logged_in' => is_user_logged_in() ?? false,
                    'user_id' => get_current_user_id() ?? '',
                    'timestamp' => current_time('c'),
                    'tem_coordenadas' => !is_null($dados['latitude'] ?? null),
                )
            );
        }
        // Retornar estrutura vazia caso não tenha dados
        return $this->result_output;
    }


    /**
     * Limpar CEP
     */
    private function limpar_cep($cep)
    {
        if (!$cep)
            return false;
        return preg_replace('/[^0-9]/', '', $cep);
    }

    /**
     * Validar CEP
     */
    private function validar_cep($cep)
    {
        if (!$cep)
            return false;
        return preg_match('/^[0-9]{8}$/', $cep);
    }



    // ==============================
    // OBTER LOCALIZAÇÃO INTELLIGENTE
    // ==============================

    /**
     * Obter localização inteligente - verifica múltiplas fontes
     * PADRÃO UNIFICADO: Sempre retorna o mesmo formato     
     * 
     * REGRA DE NEGÓCIO: Modal é a fonte de verdade (exceto página de taxonomia cidade)
     * 
     * Prioridade:
     * 1. Localização escolhida no modal (wp_cache para logados e guests)
     * 2. Dados do perfil do usuário logado (fallback apenas se não houver escolha no modal)
     * 3. Estrutura vazia
     * 
     * @return array $this->result_output_data
     */
    public function get_smart_location(): array
    {
        // 1. PRIORIDADE: Página de taxonomia cidade
        // Se está em página de taxonomia cidade, detectar e salvar no cache
        if (is_tax('cidade')) {
            $location_data = $this->get_taxonomy_location_data();
            // Salvar no cache para preservar ao navegar para outras páginas
            if (
                $location_data
                && is_array($location_data)
                && !empty($location_data)
            ) {
                $this->set_location_cache($location_data);
            }
            return $this->result_data($location_data);
        }

        // 2. Se NÃO está em página de taxonomia cidade, buscar do cache primeiro
        // Isso preserva a localização detectada de páginas de taxonomia cidade
        $location_data = $this->get_location_data();

        // Se encontrou no cache, retornar
        if (
            $location_data
            && is_array($location_data)
            && !empty($location_data)
        ) {
            return $this->result_data($location_data);
        }

        // 3. Se não encontrou no cache e é logado, buscar do user_meta
        if (is_user_logged_in()) {
            $location_data = $this->get_user_profile_data();
            // Se encontrou dados do perfil, salvar no cache
            if (
                $location_data
                && is_array($location_data)
                && !empty($location_data)
            ) {
                $this->set_location_cache($location_data);
            }
        }

        return $this->result_data($location_data);
    }


    private function result_data($location_data = array())
    {

        if (
            $location_data
            && is_array($location_data)
            && (!empty($location_data['cidade']) || !empty($location_data['estado']))
        ) {
            return $this->result_output_data($location_data);
        }

        // Retornar estrutura vazia mas válida para evitar erros
        return $this->init_result_output();
    }

    /*
     * Obter dados de localização da página de taxonomia cidade
     * 
     * @return array $this->fill_data_api_output
     */
    private function get_taxonomy_location_data()
    {

        global $current_term;

        $term = $current_term ?: get_queried_object();
        if (function_exists('bazar_resolve_cidade_archive_queried_term')) {
            $term = bazar_resolve_cidade_archive_queried_term($term);
        }
        if ($term && !is_wp_error($term)) {

            // Se não tem PAI, não é CIDADE e sim ESTADO
            $cidade_name = ((int) $term->parent > 0)
                ? $term->name
                : '';

            $cidade_term_id = ((int) $term->parent > 0)
                ? $term->term_id
                : 0;

            $estado_term = ((int) $term->parent > 0)
                ? get_term($term->parent)
                : $term;

            $estado_sigla = strtoupper($estado_term->slug);
            $estado_term_id = $estado_term->term_id;

            if ($estado_term) {
                $normalized_data = array(
                    'cidade' => $cidade_name,
                    'estado' => $estado_term->name,
                    'estado_sigla' => $estado_sigla,
                    'cidade_term_id' => $cidade_term_id,
                    'estado_term_id' => $estado_term_id,
                );
                return $this->fill_data_api_output($normalized_data, 'taxonomy_page');
            }
        }
        return array();
    }

    /**
     * Obter dados de localização do cache único
     * Usa uma única chave de cache, independente de ser logado ou guest
     * O campo 'source' identifica a origem dos dados
     * 
     * @return array Dados de localização ou array vazio se não encontrado
     */
    private function get_location_data()
    {

        $location_data = $this->get_location_cache();
        if (!empty($location_data)) {
            return $location_data;
        }

        // Se NÃO encontrou no cache e é logado: buscar de user_meta e popular cache
        if (is_user_logged_in()) {
            $location_data = $this->get_user_profile_data();
        }

        // Se não encontrou dados do perfil, retornar array vazio
        return (
            $location_data
            && is_array($location_data)
            && !empty($location_data)
        )
            ? $location_data
            : array();

    }

    /**
     * Buscar dados do perfil do usuário e popular cache único
     * Usado quando usuário logado não tem cache mas tem dados no perfil
     * 
     * @return array|null Dados de localização ou null se não encontrado
     */
    private function get_user_profile_data()
    {

        $user_id = get_current_user_id();

        if (empty($user_id)) {
            return null;
        }

        // Buscar dados do perfil (user_meta)
        $cep = get_user_meta($user_id, 'cep', true);
        $cidade = get_user_meta($user_id, 'cidade', true);
        $estado = get_user_meta($user_id, 'estado', true);
        $estado_sigla = get_user_meta($user_id, 'estado-sigla', true);
        if (empty($estado_sigla)) {
            $estado_sigla = get_user_meta($user_id, 'estado_sigla', true);
        }
        $logradouro = get_user_meta($user_id, 'logradouro', true);
        $bairro = get_user_meta($user_id, 'bairro', true);
        $regiao = get_user_meta($user_id, 'regiao', true);
        $latitude = get_user_meta($user_id, 'latitude', true);
        $longitude = get_user_meta($user_id, 'longitude', true);
        $ddd = get_user_meta($user_id, 'ddd', true);

        // Se não tem dados no perfil, retornar null
        if (empty($cidade) && empty($estado)) {
            return null;
        }

        // Buscar dados de proximidade do user_meta (se disponíveis)
        $proximidade_cep_base = get_user_meta($user_id, 'proximidade_cep_base', true);
        $proximidade_regiao_postal = get_user_meta($user_id, 'proximidade_regiao_postal', true);
        $proximidade_sub_regiao = get_user_meta($user_id, 'proximidade_sub_regiao', true);
        $proximidade_setor = get_user_meta($user_id, 'proximidade_setor', true);
        $proximidade_subsetor = get_user_meta($user_id, 'proximidade_subsetor', true);

        // Se não tem dados de proximidade salvos mas tem CEP, calcular
        if (!empty($cep) && empty($proximidade_cep_base)) {
            $proximity_data = $this->process_proximity_data_by_cep($cep);
            $proximidade_cep_base = $proximity_data['proximidade_cep_base'] ?? '';
            $proximidade_regiao_postal = $proximity_data['proximidade_regiao_postal'] ?? '';
            $proximidade_sub_regiao = $proximity_data['proximidade_sub_regiao'] ?? '';
            $proximidade_setor = $proximity_data['proximidade_setor'] ?? '';
            $proximidade_subsetor = $proximity_data['proximidade_subsetor'] ?? '';
        }

        // Montar dados de localização
        $location_data = array(
            'cep' => $cep,
            'cidade' => $cidade,
            'estado' => $estado,
            'estado_sigla' => $estado_sigla,
            'regiao' => $regiao,
            'bairro' => $bairro,
            'logradouro' => $logradouro,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'ddd' => $ddd,
            'proximidade_cep_base' => $proximidade_cep_base,
            'proximidade_regiao_postal' => $proximidade_regiao_postal,
            'proximidade_sub_regiao' => $proximidade_sub_regiao,
            'proximidade_setor' => $proximidade_setor,
            'proximidade_subsetor' => $proximidade_subsetor,
        );

        // Retornar dados formatados
        return $this->fill_data_api_output($location_data, 'user_profile');
    }

    private function set_location_cache($location_data = array())
    {

        if (empty($location_data) || !is_array($location_data))
            return null;

        $this->clear_location_cache();

        // Salvar no cache do WordPress
        wp_cache_set(
            $this->cache_key,
            $location_data,
            $this->cache_group,
            86400  // 24h
        );

    

    }
    private function get_location_cache()
    {
        // Buscar do cache do WordPress primeiro
        $cached_location = wp_cache_get(
            $this->cache_key,
            $this->cache_group
        );
        
        // Se encontrou no cache, retornar
        if ($cached_location && is_array($cached_location) && !empty($cached_location)) {
            return $cached_location;
        }
        
        return null;
    }

    /**
     * Obter IP do usuário para usar como identificador de cache para guests
     * Usa função global bazar_get_user_ip()
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

    public function clear_location_cache()
    {
        // Limpar cache único de localização (chave fixa)        
        wp_cache_delete($this->cache_key, $this->cache_group);
        return true;
    }

    /**
     * Estatísticas de cache para o painel de administração.
     * O cache atual é por sessão (wp_cache), não há tabela persistente de CEPs;
     * retorna totais zerados. Mantido para compatibilidade com a página de Geolocalização.
     *
     * @return array{total_ceps: int, ceps_com_coordenadas: int}
     */
    public function obter_estatisticas_cache()
    {
        return array(
            'total_ceps' => 0,
            'ceps_com_coordenadas' => 0,
        );
    }

    /**
     * Obter nome do estado pela sigla
     * 
     * @param string $sigla Sigla do estado
     * @return string Nome do estado
     */
    public function get_state_name_by_slug($sigla)
    {
        $estados = array(
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espírito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins'
        );

        return $estados[$sigla] ?? $sigla;
    }

    /**
     * Obter sigla do estado pelo nome
     * 
     * @param string $nome Nome do estado
     * @return string Sigla do estado
     */
    public function obter_sigla_estado($nome)
    {
        if (empty($nome))
            return $nome;

        $estados = array(
            'Acre' => 'AC',
            'Alagoas' => 'AL',
            'Amapá' => 'AP',
            'Amazonas' => 'AM',
            'Bahia' => 'BA',
            'Ceará' => 'CE',
            'Distrito Federal' => 'DF',
            'Espírito Santo' => 'ES',
            'Goiás' => 'GO',
            'Maranhão' => 'MA',
            'Mato Grosso' => 'MT',
            'Mato Grosso do Sul' => 'MS',
            'Minas Gerais' => 'MG',
            'Pará' => 'PA',
            'Paraíba' => 'PB',
            'Paraná' => 'PR',
            'Pernambuco' => 'PE',
            'Piauí' => 'PI',
            'Rio de Janeiro' => 'RJ',
            'Rio Grande do Norte' => 'RN',
            'Rio Grande do Sul' => 'RS',
            'Rondônia' => 'RO',
            'Roraima' => 'RR',
            'Santa Catarina' => 'SC',
            'São Paulo' => 'SP',
            'Sergipe' => 'SE',
            'Tocantins' => 'TO'
        );

        return $estados[$nome] ?? $nome;
    }

    /**
     * Obter estados que possuem anúncios
     * 
     * @return array Lista de estados com anúncios
     */
    public function get_estados_com_anuncios()
    {
        $estados = get_terms(array(
            'taxonomy' => 'cidade',
            'parent' => 0,
            'hide_empty' => true,
            'orderby' => 'name'
        ));

        if (is_wp_error($estados)) {
            return array();
        }
        return $estados;
    }

    /**
     * Obter lista de cidades com anúncios de um estado
     * Exclui posts vendidos e na lixeira
     * 
     * Otimizado: aceita $parent_term_id diretamente para evitar query desnecessária
     * 
     * @param string|int $estado_sigla_ou_term_id Sigla do estado (ex: 'MG', 'SP') ou term_id do estado
     * @return array Array de objetos WP_Term com cidades que têm anúncios
     */
    public function get_cidades_com_anuncios($parent_term_id = null)
    {

        if (empty($parent_term_id) || is_array($parent_term_id)) {
            return array();
        }

        $cidades = get_terms(array(
            'taxonomy' => 'cidade',
            'parent' => intval($parent_term_id),
            'hide_empty' => true, // Filtro global aplica automaticamente
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        if (is_wp_error($cidades)) {
            return array();
        }

        return $cidades;
    }

    /**
     * Buscar localização próxima com anúncios baseado em CEP
     * Reutiliza lógica de proximidade de bazar_apply_proximity_ordering()
     * 
     * Fluxo:
     * 1. Verifica se a cidade tem anúncios
     * 2. Se não, verifica se o estado tem anúncios
     * 3. Se não, busca estados próximos (mesma regiao_postal)
     * 
     * @param string $cep CEP de referência
     * @param string|null $cidade Nome da cidade (opcional)
     * @param string|null $estado_sigla Sigla do estado (opcional)
     * @return array ['cidade_tem' => bool, 'estado_tem' => bool, 'estados_proximos' => array]
     */
    public function buscar_localizacao_proxima_com_anuncios($cep, $cidade = null, $estado_sigla = null)
    {

        if (empty($cep)) {
            return [
                'cidade_tem' => false,
                'estado_tem' => false,
                'estados_proximos' => []
            ];
        }

        // Calcular dados de proximidade (reutilizar lógica existente)
        $proximity_data = $this->get_proximity_data_by_cep($cep);
        $regiao_postal = $proximity_data['proximidade_regiao_postal'] ?? '';

        $result = [
            'cidade_tem' => false,
            'estado_tem' => false,
            'estados_proximos' => []
        ];

        // 1. Verificar cidade (se fornecida)
        if (!empty($cidade) && !empty($estado_sigla)) {
            $result['cidade_tem'] = $this->cidade_tem_anuncios($cidade, $estado_sigla);

            // Se cidade tem anúncios, estado também tem (lógica)
            if ($result['cidade_tem']) {
                $result['estado_tem'] = true;
                return $result;
            }
        }

        // 2. Verificar estado (se fornecido)
        if (!empty($estado_sigla)) {
            $result['estado_tem'] = $this->estado_tem_anuncios($estado_sigla);

            // Se estado tem anúncios, retornar (mesmo que cidade não tenha)
            if ($result['estado_tem']) {
                return $result;
            }
        }

        // 3. Buscar estados próximos (mesma regiao_postal)
        // Reutilizar lógica de proximidade: buscar estados que têm anúncios com mesma regiao_postal
        if (!empty($regiao_postal)) {
            $result['estados_proximos'] = $this->buscar_estados_por_proximidade($regiao_postal);
        }

        return $result;
    }

    /**
     * Buscar estados que têm anúncios com mesma regiao_postal (proximidade)
     * Reutiliza lógica de bazar_apply_proximity_ordering()
     * 
     * @param string $regiao_postal Primeiro dígito do CEP (regiao_postal)
     * @return array Lista de estados com anúncios próximos
     */
    private function buscar_estados_por_proximidade($regiao_postal)
    {

        if (empty($regiao_postal)) {
            return [];
        }

        global $wpdb;

        // Buscar estados que têm anúncios com mesma regiao_postal
        // Usar mesma lógica de bazar_apply_proximity_ordering() - JOIN com post_meta e taxonomia
        $sql = "
            SELECT DISTINCT t_estado.term_id, t_estado.name, t_estado.slug
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_regiao ON (
                p.ID = pm_regiao.post_id 
                AND pm_regiao.meta_key = 'proximidade_regiao_postal'
                AND pm_regiao.meta_value = %s
            )
            INNER JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
            INNER JOIN {$wpdb->term_taxonomy} tt_cidade ON (
                tr.term_taxonomy_id = tt_cidade.term_taxonomy_id 
                AND tt_cidade.taxonomy = 'cidade' 
                AND tt_cidade.parent > 0
            )
            INNER JOIN {$wpdb->term_taxonomy} tt_estado ON (tt_cidade.parent = tt_estado.term_id AND tt_estado.taxonomy = 'cidade')
            INNER JOIN {$wpdb->terms} t_estado ON (tt_estado.term_id = t_estado.term_id)
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            GROUP BY t_estado.term_id
            ORDER BY t_estado.name ASC
        ";

        // Buscar estados que têm anúncios com mesma regiao_postal
        $estados_meta = $wpdb->get_results($wpdb->prepare($sql, $regiao_postal));

        // Se encontrou estados por meta field, retornar
        if (!empty($estados_meta)) {
            $estados_combinados = array();
            foreach ($estados_meta as $estado) {
                $estados_combinados[] = array(
                    'id' => $estado->term_id,
                    'name' => $estado->name,
                    'slug' => $estado->slug
                );
            }
            return $estados_combinados;
        }

        // Fallback: buscar estados que têm anúncios (sem filtro de proximidade)
        // Retornar primeiros 5 estados com anúncios como alternativa
        $estados_com_anuncios = $this->get_estados_com_anuncios();

        $estados_combinados = array();
        foreach ($estados_com_anuncios as $estado) {
            $estados_combinados[] = array(
                'id' => $estado['id'],
                'name' => $estado['name'],
                'slug' => $estado['slug']
            );
            // Limitar a 5 estados
            if (count($estados_combinados) >= 5) {
                break;
            }
        }

        return $estados_combinados;
    }


    /**
     * Salvar dados de proximidade nos meta fields do usuário
     * 
     * @param int $user_id ID do usuário
     * @param string|null $cep CEP do anúncio (opcional, busca do post_meta se não fornecido)
     * @return bool true se salvou com sucesso, false caso contrário
     */
    public function salvar_dados_proximidade_usuario($user_id, $cep = null)
    {

        // Verificar se não é autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return false;

        if (empty($user_id) || empty($cep))
            return false;

        // Calcular dados de proximidade
        $proximity_data = $this->process_proximity_data_by_cep($cep);
        // Se não tem dados de proximidade, não há o que fazer
        if (empty($proximity_data))
            return true;

        // Salvar no user_meta
        update_user_meta($user_id, 'proximidade_cep_base', $proximity_data['proximidade_cep_base']);
        update_user_meta($user_id, 'proximidade_regiao_postal', $proximity_data['proximidade_regiao_postal']);
        update_user_meta($user_id, 'proximidade_sub_regiao', $proximity_data['proximidade_sub_regiao']);
        update_user_meta($user_id, 'proximidade_setor', $proximity_data['proximidade_setor']);
        update_user_meta($user_id, 'proximidade_subsetor', $proximity_data['proximidade_subsetor']);

        return true;
    }


    /**
     * Salvar dados de proximidade nos anúncios
     * 
     * @param int $post_id ID do post
     * @param string|null $cep CEP do anúncio (opcional, busca do post_meta se não fornecido)
     * @return bool true se salvou com sucesso, false caso contrário
     */
    public function salvar_dados_proximidade_anuncio($post_id, $cep = null)
    {
        // Verificar se não é autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return false;

        // Verificar se o usuário tem permissão (apenas para edição manual)
        // Durante criação/edição via formulário, já foi validado
        if (!current_user_can('edit_post', $post_id))
            return false;

        // Obter CEP (prioridade: parâmetro > post_meta)
        if (empty($cep)) {
            $cep = get_post_meta($post_id, 'cep', true);
        }

        // Sem CEP, não há o que fazer
        if (empty($cep))
            return false;

        // Calcular dados de proximidade
        $proximity_data = $this->process_proximity_data_by_cep($cep);
        // Se não tem dados de proximidade, não há o que fazer
        if (empty($proximity_data))
            return true;

        // Salvar no user_meta
        update_post_meta($post_id, 'proximidade_cep_base', $proximity_data['proximidade_cep_base']);
        update_post_meta($post_id, 'proximidade_regiao_postal', $proximity_data['proximidade_regiao_postal']);
        update_post_meta($post_id, 'proximidade_sub_regiao', $proximity_data['proximidade_sub_regiao']);
        update_post_meta($post_id, 'proximidade_setor', $proximity_data['proximidade_setor']);
        update_post_meta($post_id, 'proximidade_subsetor', $proximity_data['proximidade_subsetor']);

        return true;
    }


    private function process_proximity_data_by_cep($cep = null)
    {

        if (empty($cep))
            return array();

        $cep_limpo = $this->limpar_cep($cep);
        if (!$this->validar_cep($cep_limpo))
            return array();

        $proximidade_cep_base = $cep_limpo;
        $proximidade_regiao_postal = substr($cep_limpo, 0, 1);
        $proximidade_sub_regiao = substr($cep_limpo, 0, 2);
        $proximidade_setor = substr($cep_limpo, 0, 3);
        $proximidade_subsetor = substr($cep_limpo, 0, 4);

        return array(
            'proximidade_cep_base' => ($proximidade_cep_base) ? $proximidade_cep_base : '',
            'proximidade_regiao_postal' => ($proximidade_regiao_postal) ? $proximidade_regiao_postal : '',
            'proximidade_sub_regiao' => ($proximidade_sub_regiao) ? $proximidade_sub_regiao : '',
            'proximidade_setor' => ($proximidade_setor) ? $proximidade_setor : '',
            'proximidade_subsetor' => ($proximidade_subsetor) ? $proximidade_subsetor : '',
        );
    }

    public function get_proximity_data_by_cep($cep = null)
    {
        if (empty($cep))
            return array();
        return $this->process_proximity_data_by_cep($cep);
    }


    /**
     * Calcular proximidade entre dois CEPs
     */
    public function calcular_proximidade($cep1, $cep2)
    {

        $dados1 = $this->buscar_cep($cep1);
        $dados2 = $this->buscar_cep($cep2);

        if (!$dados1 || !$dados2) {
            return false;
        }

        // Usar coordenadas se disponíveis
        if ($dados1['localizacao']['latitude'] && $dados2['localizacao']['latitude']) {
            $distancia_km = $this->calcular_distancia_haversine(
                $dados1['localizacao']['latitude'],
                $dados1['localizacao']['longitude'],
                $dados2['localizacao']['latitude'],
                $dados2['localizacao']['longitude']
            );

            return array(
                'metodo' => 'coordenadas',
                'distancia_km' => $distancia_km,
                'proximidade' => $this->calcular_pontuacao_por_distancia($distancia_km)
            );
        }

        // Fallback: proximidade por estrutura CEP
        return array(
            'metodo' => 'cep',
            'proximidade' => $this->calcular_proximidade_por_cep($cep1, $cep2)
        );
    }

    /**
     * Calcular distância por Haversine
     */
    private function calcular_distancia_haversine($lat1, $lon1, $lat2, $lon2)
    {
        $R = 6371; // Raio da Terra em km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }

    /**
     * Calcular pontuação por distância
     */
    private function calcular_pontuacao_por_distancia($distancia_km)
    {
        if ($distancia_km <= 5)
            return 100;
        if ($distancia_km <= 15)
            return 90;
        if ($distancia_km <= 30)
            return 80;
        if ($distancia_km <= 50)
            return 70;
        if ($distancia_km <= 100)
            return 60;
        if ($distancia_km <= 200)
            return 50;
        if ($distancia_km <= 500)
            return 40;
        return 30;
    }

    /**
     * Calcular proximidade por estrutura CEP
     */
    private function calcular_proximidade_por_cep($cep1, $cep2)
    {
        $cep1 = $this->limpar_cep($cep1);
        $cep2 = $this->limpar_cep($cep2);

        // Mesmo bairro (4 dígitos)
        if (substr($cep1, 0, 4) === substr($cep2, 0, 4))
            return 100;

        // Mesma cidade (3 dígitos)
        if (substr($cep1, 0, 3) === substr($cep2, 0, 3))
            return 90;

        // Mesma região metropolitana (2 dígitos)
        if (substr($cep1, 0, 2) === substr($cep2, 0, 2))
            return 70;

        // Mesmo estado (1 dígito)
        if (substr($cep1, 0, 1) === substr($cep2, 0, 1))
            return 50;

        return 30; // Nacional
    }

    /**
     * Previne clonagem (Singleton)
     */
    private function __clone()
    {
    }

    /**
     * Previne unserialize (Singleton)
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Inicializar hooks AJAX
 * Padrão: bazar_* para manter consistência
 */
add_action('wp_ajax_bazar_buscar_cep', 'bazar_geo_api_ajax_buscar_cep');
add_action('wp_ajax_nopriv_bazar_buscar_cep', 'bazar_geo_api_ajax_buscar_cep');
add_action('wp_ajax_bazar_calcular_proximidade', 'bazar_geo_api_ajax_calcular_proximidade');
add_action('wp_ajax_nopriv_bazar_calcular_proximidade', 'bazar_geo_api_ajax_calcular_proximidade');
add_action('wp_ajax_bazar_get_term_link', 'bazar_geo_api_ajax_get_term_link');
add_action('wp_ajax_nopriv_bazar_get_term_link', 'bazar_geo_api_ajax_get_term_link');

function bazar_geo_api_ajax_buscar_cep()
{
    global $geo_api;
    if (!$geo_api) {
        $geo_api = BazarBikes_GeoAPI::getInstance();
    }
    $geo_api->ajax_buscar_cep();
}

function bazar_geo_api_ajax_calcular_proximidade()
{
    global $geo_api;
    if (!$geo_api) {
        $geo_api = BazarBikes_GeoAPI::getInstance();
    }
    $geo_api->ajax_calcular_proximidade();
}

function bazar_geo_api_ajax_get_term_link()
{

    $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : 'cidade';

    if ($term_id <= 0) {
        wp_send_json_error('Term ID inválido');
        return;
    }

    // Usar get_term_link() padrão do WordPress
    $url = get_term_link($term_id, $taxonomy);

    if (is_wp_error($url)) {
        wp_send_json_error('Erro ao obter URL do termo');
        return;
    }

    wp_send_json_success(array(
        'url' => $url
    ));
}