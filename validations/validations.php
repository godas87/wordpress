<?php
class __BazarValidations
{

  // https://XXXXXX/
  private $api_cpf_key = null;

  /**
   * Delay mínimo entre consultas à API de CPF (em segundos)
   * Evita erro "Limite de consultas repetidas excedido"
   * @var int
   */
  private $api_cpf_min_delay = 2; // 2 segundos entre consultas

  /**
   * Tempo de expiração do cache de resultados de CPF (em segundos)
   * @var int
   */
  private $cache_expire_cpf = 86400; // 24 horas

  /** Prefixo das transients de CPF (persiste entre requisições, inclusive em localhost) */
  private $transient_prefix_cpf = 'bazar_cpf_';

  /** Transient para controle de delay entre consultas */
  private $transient_delay_key = 'bazar_cpf_last_request';

  public function __construct()
  {
    // Inicialização da classe
    $this->get_api_cpf_key();
  }

  private function get_api_cpf_key()
  {
    // Carregar a chave apenas uma vez e armazenar na propriedade da instância
    if ($this->api_cpf_key === null) {
      $this->api_cpf_key = (string) get_option('bazar_api_cpf_key', '');
    }
    return $this->api_cpf_key;
  }

  /**
   * Valida telefone brasileiro
   * @param string $telefone
   * @return bool
   */
  public function __BAZAR_validaFone($telefone)
  {

    if (empty($telefone))
      return false;

    // Remove todos os caracteres não numéricos
    $telefone = preg_replace('/\D/', '', $telefone);

    // Verifica se tem a quantidade correta de números
    if (strlen($telefone) < 10 || strlen($telefone) > 11)
      return false;

    // Se tiver 11 caracteres, verificar se começa com 9 o celular
    if (strlen($telefone) == 11 && substr($telefone, 2, 1) != '9')
      return false;

    // Verifica se todos os dígitos são iguais
    $foneSemDDD = substr($telefone, 2);
    if ($this->__Bazar_digitosIguais($foneSemDDD))
      return false;

    // DDDs válidos
    $codigosDDD = [
      11,
      12,
      13,
      14,
      15,
      16,
      17,
      18,
      19,
      21,
      22,
      24,
      27,
      28,
      31,
      32,
      33,
      34,
      35,
      37,
      38,
      41,
      42,
      43,
      44,
      45,
      46,
      47,
      48,
      49,
      51,
      53,
      54,
      55,
      61,
      62,
      64,
      63,
      65,
      66,
      67,
      68,
      69,
      71,
      73,
      74,
      75,
      77,
      79,
      81,
      82,
      83,
      84,
      85,
      86,
      87,
      88,
      89,
      91,
      92,
      93,
      94,
      95,
      96,
      97,
      98,
      99
    ];

    // Verifica se o DDD é válido
    $ddd = (int) substr($telefone, 0, 2);
    if (!in_array($ddd, $codigosDDD))
      return false;

    return true;
  }

  /**
   * Valida se há muitos dígitos consecutivos iguais
   * @param string $stringValue - String para validar
   * @param int $maxRepeat - Máximo de dígitos consecutivos permitidos (padrão: 6)
   * @return bool - true se tem muitos dígitos repetidos (ERRO), false se está OK
   */
  public function __Bazar_maxCharConsecutiveRepeat($stringValue, $maxRepeat = 6)
  {

    if (empty($stringValue) || !is_string($stringValue))
      return false;

    // Validação de números repetidos - máximo 6 dígitos consecutivos iguais
    $digitosRepetidos = 1;
    $maxRepetidos = 1;

    for ($i = 0; $i < strlen($stringValue) - 1; $i++) {
      if ($stringValue[$i] === $stringValue[$i + 1]) {
        $digitosRepetidos++;
        if ($digitosRepetidos > $maxRepetidos) {
          $maxRepetidos = $digitosRepetidos;
        }
      } else {
        $digitosRepetidos = 1;
      }
    }

    // Retorna true se tem mais dígitos repetidos que o permitido (indica erro)
    return ($maxRepetidos > $maxRepeat);
  }

  /**
   * Valida senha (8 caracteres ou mais, letras, números e 1 caractere especial)
   * @param string $senha
   * @return bool
   */
  public function __BAZAR_validaSenha($senha)
  {
    if (empty($senha))
      return false;

    // Pelo menos 8 caracteres, pelo menos uma letra, um número e um caractere especial
    $pattern = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&#._-]).{8,}$/';
    return preg_match($pattern, $senha) === 1;
  }

  /**
   * Valida email
   * @param string $email
   * @return bool
   */
  public function __BAZAR_validaEmail($email)
  {
    if (empty($email))
      return false;

    $pattern = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
    return preg_match($pattern, $email) === 1;
  }

  /**
   * Valida valor (maior que R$ 10,00)
   * @param string $valor
   * @return bool
   */
  public function __BAZAR_validaValor($valor)
  {
    if (empty($valor))
      return false;

    $valorNumerico = (int) preg_replace('/[\D]+/', '', $valor);
    $valorDecimal = $valorNumerico * 0.01;

    return $valorDecimal > 10;
  }


  /**
   * Valida CPF
   * @param string $cpf
   * @return bool
   */
  public function __BAZAR_validaCPF($cpf)
  {
    if (empty($cpf))
      return false;

    $cpf = preg_replace('/[^\d]+/', '', $cpf);

    if (strlen($cpf) < 11)
      return false;

    // Verifica se todos os dígitos são iguais
    if ($this->__Bazar_digitosIguais($cpf))
      return false;

    // Validação do primeiro dígito verificador
    $numeros = substr($cpf, 0, 9);
    $digitos = substr($cpf, 9);
    $soma = 0;

    for ($i = 10; $i > 1; $i--) {
      $soma += $numeros[10 - $i] * $i;
    }

    $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    if ($resultado != $digitos[0])
      return false;

    // Validação do segundo dígito verificador
    $numeros = substr($cpf, 0, 10);
    $soma = 0;

    for ($i = 11; $i > 1; $i--) {
      $soma += $numeros[11 - $i] * $i;
    }

    $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    if ($resultado != $digitos[1])
      return false;

    return true;
  }

  /**
   * Valida CNPJ
   * @param string $cnpj
   * @return bool
   */
  public function __BAZAR_validCNPJ($cnpj)
  {
    if (empty($cnpj))
      return false;

    $cnpj = preg_replace('/[^\d]+/', '', $cnpj);

    if (strlen($cnpj) != 14)
      return false;

    // Elimina CNPJs inválidos conhecidos
    $cnpjsInvalidos = [
      "00000000000000",
      "11111111111111",
      "22222222222222",
      "33333333333333",
      "44444444444444",
      "55555555555555",
      "66666666666666",
      "77777777777777",
      "88888888888888",
      "99999999999999"
    ];

    if (in_array($cnpj, $cnpjsInvalidos))
      return false;

    // Validação do primeiro dígito verificador
    $tamanho = strlen($cnpj) - 2;
    $numeros = substr($cnpj, 0, $tamanho);
    $digitos = substr($cnpj, $tamanho);
    $soma = 0;
    $pos = $tamanho - 7;

    for ($i = $tamanho; $i >= 1; $i--) {
      $soma += $numeros[$tamanho - $i] * $pos--;
      if ($pos < 2)
        $pos = 9;
    }

    $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    if ($resultado != $digitos[0])
      return false;

    // Validação do segundo dígito verificador
    $tamanho = $tamanho + 1;
    $numeros = substr($cnpj, 0, $tamanho);
    $soma = 0;
    $pos = $tamanho - 7;

    for ($i = $tamanho; $i >= 1; $i--) {
      $soma += $numeros[$tamanho - $i] * $pos--;
      if ($pos < 2)
        $pos = 9;
    }

    $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    if ($resultado != $digitos[1])
      return false;

    return true;
  }

  /**
   * Valida CEP
   * @param string $cep
   * @return bool
   */
  public function __BAZAR_validaCEP($cep)
  {
    if (empty($cep))
      return false;

    $cepLimpo = preg_replace('/[^\d]+/', '', $cep);

    if (strlen($cepLimpo) < 8)
      return false;

    if ($this->__Bazar_digitosIguais($cepLimpo))
      return false;

    return true;
  }


  public function __Bazar_digitosIguais($stringValue = '')
  {

    if (empty($stringValue))
      return false;

    // Verifica se todos os dígitos são iguais
    $digitosIguais = true;
    for ($i = 0; $i < strlen($stringValue) - 1; $i++) {
      if ($stringValue[$i] != $stringValue[$i + 1]) {
        $digitosIguais = false;
        break;
      }
    }
    return $digitosIguais;
  }

  /**
   * Valida data de nascimento (formato DD/MM/YYYY e idade mínima de 18 anos)
   * @param string $dataNascimento
   * @return bool
   */
  public function __BAZAR_validaDataNascimento($dataNascimento)
  {

    if (empty($dataNascimento))
      return false;

    // Validar formato da data (DD/MM/YYYY)
    $pattern = '/^[0-9]{2}\/[0-9]{2}\/[0-9]{4}$/';
    if (!preg_match($pattern, $dataNascimento))
      return false;

    // Converter data para verificação
    $partes = explode('/', $dataNascimento);
    $dia = (int) $partes[0];
    $mes = (int) $partes[1];
    $ano = (int) $partes[2];

    // Verificar se a data é válida
    if (!checkdate($mes, $dia, $ano))
      return false;

    // Calcular idade
    $hoje = new DateTime();
    $dataNasc = new DateTime("$ano-$mes-$dia");
    $idade = $hoje->diff($dataNasc)->y;

    // Verificar se tem pelo menos 18 anos
    return $idade >= 18;
  }

  /**
   * Valida idade
   * @param int $idade
   * @return bool
   */
  public function __BAZAR_validaIdade($idade)
  {
    return is_numeric($idade) && $idade >= 18 && $idade <= 120;
  }


  public function __BAZAR_formatBrazilianNames($nome)
  {
    if (empty($nome))
      return '';

    $preposicoes = ['de', 'da', 'do', 'dos', 'das'];
    $abreviacoes = [
      'dr.' => 'Doutor',
      'dr' => 'Doutor',
      'dra.' => 'Doutora',
      'dra' => 'Doutora',
      'sr.' => 'Senhor',
      'sr' => 'Senhor',
      'sra.' => 'Senhora',
      'sra' => 'Senhora'
    ];

    $partesDoNome = explode(' ', trim($nome));
    $nomeFormatado = [];

    foreach ($partesDoNome as $parte) {
      $palavra = strtolower($parte);

      if (isset($abreviacoes[$palavra])) {
        $nomeFormatado[] = $abreviacoes[$palavra];
      } elseif (in_array($palavra, $preposicoes)) {
        $nomeFormatado[] = $palavra;
      } else {
        $nomeFormatado[] = ucfirst(strtolower($parte));
      }
    }

    return implode(' ', $nomeFormatado);
  }

  /**
   * Valida se CPF existe via API externa
   * 
   * @param string $cpf CPF para validar (formato: 12345678901 ou 123.456.789-01)
   * @return array Resultado da validação com a seguinte estrutura:
   * 
   * RETORNO POSSÍVEIS:
   * 
   * 1. API funcionou e CPF existe:
   *    [
   *        'success' => true, 
   *        'error' => null, 
   *        'should_continue' => true, 
   *        'api_data' => array
   *    ]
   * 
   * 2. API funcionou mas CPF não existe:
   *    [
   *        'success' => false, 
   *        'error' => 'cpf_nao_existe', 
   *        'should_continue' => false
   *    ]
   * 
   * 3. API falhou (timeout, conexão, etc.) - continua o fluxo normalmente:
   *    [
   *        'success' => true, 
   *        'error' => null, 
   *        'should_continue' => true
   *    ]
   *    (Envia email de notificação para XXXXXX)
   * 
   * COMO USAR:
   * 
   * $resultado = $validations->__BAZAR_validaCPF_Existe('12345678901');    
   * if( !$resultado['success'] ){
   *     if ($resultado['error'] === 'cpf_nao_existe') {
   *         // CPF não existe na base de dados
   *     }
   *     return false; // Parar o processo
   * }
   * // CPF existe, continuar o processo
   */
  public function __BAZAR_validaCPF_Existe($cpf)
  {

    // Normalizar CPF (remover tudo que não é dígito e garantir 11 dígitos)
    $cpf_limpo = preg_replace('/[^\d]/', '', $cpf);
    $cpf_limpo = str_pad($cpf_limpo, 11, '0', STR_PAD_LEFT);

    // ============================================
    // VERIFICAR CACHE ANTES DE CONSULTAR API
    // ============================================
    // Usamos transients (persistem no BD) para funcionar também em localhost sem Redis/Memcached.
    // Erros temporários: 2-5 min. Resultados válidos: 24h.
    $cache_key = $this->transient_prefix_cpf . $cpf_limpo;
    $cached_result = get_transient($cache_key);

    if ($cached_result !== false && is_array($cached_result)) {
      return $cached_result;
    }

    // ============================================
    // CONTROLE DE DELAY MÍNIMO ENTRE CONSULTAS
    // ============================================
    // Evita erro "Limite de consultas repetidas excedido"
    $this->aguardar_delay_minimo_api();

    // Fazer requisição para a API
    $api_url = 'https://XXXXXX/api/consulta?cpf=' . $cpf_limpo;

    // Configurações de retry para timeout e limite de requisições
    $max_tentativas = 3;
    $delay_entre_tentativas = 2; // segundos
    $tentativa = 0;
    $response_data = false;
    $http_code = 0;
    $error_message = '';

    while ($tentativa < $max_tentativas && $response_data === false) {
      $tentativa++;

      if ($tentativa > 1) {
        // Aguardar antes de tentar novamente
        sleep($delay_entre_tentativas);
      }

      // Usar wp_remote_get() em vez de curl (abordagem WordPress)
      $response = wp_remote_get($api_url, array(
        'timeout' => 15,
        'headers' => array(
          'X-API-KEY' => $this->api_cpf_key,
          'Content-Type' => 'application/json',
          'User-Agent' => 'XXXXXX/1.0'
        ),
        'sslverify' => false,
        'redirection' => 3
      ));

      // Verificar se houve erro na requisição
      if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        $http_code = 0;

        // Se é a última tentativa, registrar erro e bloquear (não permitir salvar sem validar nome/sobrenome)
        if ($tentativa >= $max_tentativas) {
          $this->enviar_email_api_falha($cpf, $error_message, $http_code);
          $result = [
            'success' => false,
            'error' => 'api_indisponivel',
            'should_continue' => false
          ];
          set_transient($cache_key, $result, 120);
          return $result;
        }
        // Continuar loop para próxima tentativa
        continue;
      }

      // Obter código HTTP e corpo da resposta
      $http_code = wp_remote_retrieve_response_code($response);
      $body = wp_remote_retrieve_body($response);

      // Se é erro 429 no HTTP code, tratar imediatamente (não tentar decodificar JSON)
      if ($http_code == 429) {
        // Tentar decodificar JSON para pegar mensagem de erro
        $response_data = !empty($body) ? json_decode($body, true) : null;
        $error_message = ($response_data && isset($response_data['message']))
          ? $response_data['message']
          : 'Limite de consultas repetidas excedido';

        // Se é a última tentativa ou já tentou 2 vezes, bloquear e cachear
        if ($tentativa >= $max_tentativas || $tentativa >= 2) {
          $result = [
            'success' => false,
            'error' => 'api_indisponivel',
            'should_continue' => false
          ];
          set_transient($cache_key, $result, 300);
          $this->enviar_email_api_falha($cpf, 'API Error: ' . $error_message, $http_code);
          return $result;
        }

        // Se ainda há tentativas, aguardar mais tempo antes da próxima
        sleep(5);
        continue;
      }

      // Se a resposta foi bem-sucedida (200) ou erro conhecido (404, 500), processar
      if (in_array($http_code, [200, 404, 500]) && !empty($body)) {
        $response_data = json_decode($body, true);

        // Se conseguiu decodificar JSON, sair do loop
        if ($response_data !== null) {
          break;
        }
      }
    }

    // Se não conseguiu obter dados válidos após todas as tentativas, bloquear (não salvar sem validar)
    if ($response_data === false || $response_data === null) {
      $result = [
        'success' => false,
        'error' => 'api_indisponivel',
        'should_continue' => false
      ];
      set_transient($cache_key, $result, 120);
      $this->enviar_email_api_falha($cpf, $error_message ?: 'Falha ao obter resposta da API', $http_code);
      return $result;
    }

    // Se a API retornou erro (CPF não existe)
    if (!isset($response_data['code']) || $response_data['code'] !== 200) {
      // Verificar se é erro 404 (CPF não encontrado) ou outro erro
      if (isset($response_data['code']) && $response_data['code'] == 404) {
        $result = [
          'success' => false,
          'error' => 'cpf_nao_existe',
          'should_continue' => false
        ];
        set_transient($cache_key, $result, $this->cache_expire_cpf);
        return $result;
      }
      // Para outros erros (429, 500, etc.), continuar o fluxo
      $error_code = $response_data['code'] ?? $http_code;
      $error_message = $response_data['message'] ?? 'Unknown error';

      // Se é erro 429 (limite excedido), bloquear e cachear
      if ($error_code == 429) {
        $result = [
          'success' => false,
          'error' => 'api_indisponivel',
          'should_continue' => false
        ];
        set_transient($cache_key, $result, 300);
        $this->enviar_email_api_falha($cpf, 'API Error: ' . $error_message, $error_code);
        return $result;
      }

      // Para outros erros (500, etc.), bloquear e cachear por tempo menor
      $result = [
        'success' => false,
        'error' => 'api_indisponivel',
        'should_continue' => false
      ];
      set_transient($cache_key, $result, 120);
      $this->enviar_email_api_falha($cpf, 'API Error: ' . $error_message, $error_code);
      return $result;
    }

    // CPF existe na API
    // Atualizar timestamp da última consulta bem-sucedida
    $this->atualizar_timestamp_ultima_consulta();

    // Preparar resultado
    $result = [
      'success' => true,
      'error' => null,
      'should_continue' => true,
      'api_data' => $response_data['data'] ?? null
    ];

    // ============================================
    // SALVAR RESULTADO NO CACHE (transient persiste entre requisições)
    // ============================================
    set_transient($cache_key, $result, $this->cache_expire_cpf);

    return $result;
  }

  /**
   * Invalida o cache de um CPF específico
   * Útil quando é necessário forçar uma nova consulta à API
   * 
   * @param string $cpf CPF para invalidar (formato: 12345678901 ou 123.456.789-01)
   * @return bool True se o cache foi invalidado, false caso contrário
   */
  public function invalidar_cache_cpf($cpf)
  {
    // Normalizar CPF da mesma forma que na validação
    $cpf_limpo = preg_replace('/[^\d]/', '', $cpf);
    $cpf_limpo = str_pad($cpf_limpo, 11, '0', STR_PAD_LEFT);

    if (empty($cpf_limpo) || strlen($cpf_limpo) != 11) {
      return false;
    }

    $cache_key = $this->transient_prefix_cpf . $cpf_limpo;
    return delete_transient($cache_key);
  }

  /**
   * Aguarda delay mínimo entre consultas à API de CPF
   * Usa transient para persistir entre requisições (funciona em localhost).
   */
  private function aguardar_delay_minimo_api()
  {

    $last_request_time = get_transient($this->transient_delay_key);

    if ($last_request_time !== false && is_numeric($last_request_time)) {
      $elapsed = time() - (int) $last_request_time;

      if ($elapsed < $this->api_cpf_min_delay) {
        $wait_time = $this->api_cpf_min_delay - $elapsed;
        sleep($wait_time);
      }
    }
  }

  /**
   * Atualiza timestamp da última consulta à API de CPF
   */
  private function atualizar_timestamp_ultima_consulta()
  {
    set_transient($this->transient_delay_key, time(), 60);
  }

  /**
   * Valida se a data de nascimento confere com os dados da API
   * 
   * @param string $data_nascimento_usuario Data informada pelo usuário (formato: DD/MM/YYYY)
   * @param array $api_data Dados retornados pela API do CPF
   * @return array Resultado da validação:
   * 
   * RETORNO POSSÍVEIS:
   * 
   * 1. Data confere:
   *    [
   *        'success' => true, 
   *        'error' => null, 
   *        'should_continue' => true
   *    ]
   * 
   * 2. Data não confere:
   *    [
   *        'success' => false, 
   *        'error' => 'data_nascimento_inconsistente', 
   *        'should_continue' => false
   *    ]
   * 
   * COMO USAR:
   * 
   * $resultado = $validations->__BAZAR_validaDataNascimento_API('01/01/1990', $api_data);
   * 
   * if( !$resultado['success'] ){
   *     // Data de nascimento não confere
   *     return false;
   * }
   * // Data confere, continuar o processo
   */
  public function __BAZAR_validaDataNascimento_CPF_API($data_nascimento_usuario, $api_data)
  {

    $data_nascimento_usuario = sanitize_text_field($data_nascimento_usuario);

    // Verificar se a data de nascimento informada corresponde à da API
    if (isset($api_data['data_nascimento'])) {
      $data_api = $api_data['data_nascimento'];

      // Converter ambas as datas para timestamp para comparação mais robusta
      $timestamp_api = $this->converter_data_para_timestamp($data_api);
      $timestamp_usuario = $this->converter_data_para_timestamp($data_nascimento_usuario);

      if ($timestamp_api === false || $timestamp_usuario === false) {
        // Se não conseguiu converter uma das datas, usar comparação de string como fallback
        $data_api_formatada = $this->converter_data_para_timestamp($data_api);
        if ($data_api_formatada !== $data_nascimento_usuario) {
          return [
            'success' => false,
            'error' => 'data_nascimento_inconsistente',
            'should_continue' => false
          ];
        }
      } else {
        // Comparar timestamps (mais robusto)
        if ($timestamp_api !== $timestamp_usuario) {
          return [
            'success' => false,
            'error' => 'data_nascimento_inconsistente',
            'should_continue' => false
          ];
        }
      }
    }

    return [
      'success' => true,
      'error' => null,
      'should_continue' => true
    ];
  }


  /**
   * Converte data para timestamp (suporta múltiplos formatos)
   * Funciona tanto para dados do usuário quanto da API
   */
  private function converter_data_para_timestamp($data)
  {
    // Tentar diferentes formatos de data
    $formatos = [
      'd/m/Y',     // 02/08/1984
      'd-m-Y',     // 02-08-1984
      'Y-m-d',     // 1984-08-02
      'Y/m/d',     // 1984/08/02
      'm/d/Y',     // 08/02/1984 (formato americano)
      'm-d-Y',     // 08-02-1984
    ];

    foreach ($formatos as $formato) {
      $timestamp = DateTime::createFromFormat($formato, $data);
      if ($timestamp !== false) {
        return $timestamp->getTimestamp();
      }
    }

    // Se nenhum formato funcionou, tentar strtotime como último recurso
    $timestamp = strtotime($data);
    return $timestamp !== false ? $timestamp : false;
  }


  /**
   * Valida se o nome e sobrenome informados conferem com os dados da API
   * 
   * @param string $nome_usuario Nome informado pelo usuário
   * @param string $sobrenome_usuario Sobrenome informado pelo usuário
   * @param array $api_data Dados retornados pela API do CPF
   * @return array Resultado da validação:
   * 
   * RETORNO POSSÍVEIS:
   * 
   * 1. Nome confere:
   *    [
   *        'success' => true, 
   *        'error' => null, 
   *        'should_continue' => true
   *    ]
   * 
   * 2. Nome não confere:
   *    [
   *        'success' => false, 
   *        'error' => 'nome_inconsistente', 
   *        'should_continue' => false
   *    ]
   * 
   * COMO USAR:
   * 
   * $resultado = $validations->__BAZAR_validaNome_API('João', 'Silva', $api_data);
   * 
   * if( !$resultado['success'] ){
   *     // Nome não confere com o CPF
   *     return false;
   * }
   * // Nome confere, continuar o processo
   */
  public function __BAZAR_validaNome_CPF_API($nome_usuario, $sobrenome_usuario, $nome_completo_api, $cpf)
  {


    if (empty($nome_completo_api)) {
      // Se não há nome completo na API, enviar email de notificação
      // Não deve barrar o cadastro pois pode ser um erro temporário da API
      // Nota: A API usa header X-API-KEY para autenticação, não parâmetro na query string
      $cUrlTest = 'https://XXXXXX/api/consulta?cpf=' . $cpf;
      $this->enviar_email_falha_api_cpf_nome(
        $cpf,
        'API Error: Nome não encontrado no CPF informado. Verificar se o CPF está correto, e se a API de CPF está funcionando corretamente. <br/>Para testar (requer header X-API-KEY): ' . $cUrlTest
      );
      return [
        'success' => true,
        'error' => null,
        'should_continue' => true
      ];
    }

    // Se não há dados para validar, continuar o processo
    if (
      $nome_usuario === null || $nome_usuario === ''
      || $sobrenome_usuario === null || $sobrenome_usuario === ''
      || $nome_completo_api === null || $nome_completo_api === ''
    ) {
      return [
        'success' => true,
        'error' => null,
        'should_continue' => true
      ];
    }

    $nome_usuario = sanitize_text_field($nome_usuario);
    $sobrenome_usuario = sanitize_text_field($sobrenome_usuario);

    // Dividir o nome da API em partes
    $partes_nome_api = explode(' ', trim($nome_completo_api));
    $primeiro_nome_api = $partes_nome_api[0];

    // Normalizar nomes para comparação
    $nome_usuario_normalizado = $this->normalizar_nome($nome_usuario);
    $primeiro_nome_api_normalizado = $this->normalizar_nome($primeiro_nome_api);

    // Comparar o primeiro nome (deve ser exato)
    if ($nome_usuario_normalizado !== $primeiro_nome_api_normalizado) {
      return [
        'success' => false,
        'error' => 'nome_inconsistente',
        'should_continue' => false
      ];
    }

    // Validar sobrenome: não pode ser só preposição (ex.: "De", "Da") – normalizar_nome usa remover_preposicoes, fica vazio
    if (!empty($sobrenome_usuario)) {
      $sobrenome_usuario_normalizado = $this->normalizar_nome($sobrenome_usuario);

      if ($sobrenome_usuario_normalizado === '') {
        return [
          'success' => false,
          'error' => 'sobrenome_inconsistente',
          'should_continue' => false
        ];
      }

      $nome_completo_api_normalizado = $this->normalizar_nome($nome_completo_api);

      // Verificar se o sobrenome do usuário está contido no nome completo da API
      if (strpos($nome_completo_api_normalizado, $sobrenome_usuario_normalizado) === false) {
        return [
          'success' => false,
          'error' => 'sobrenome_inconsistente',
          'should_continue' => false
        ];
      }
    }

    return [
      'success' => true,
      'error' => null,
      'should_continue' => true
    ];
  }

  /**
   * Normaliza nome para comparação (remove acentos, converte para minúsculas, etc.)
   */
  private function normalizar_nome($nome)
  {
    // Converter para minúsculas
    $nome = strtolower($nome);
    // Remover acentos
    $nome = $this->remover_acentos($nome);
    // Remove preposições
    $nome = $this->remover_preposicoes($nome);
    // Remover espaços extras
    $nome = preg_replace('/\s+/', ' ', trim($nome));
    // Remover caracteres especiais (exceto espaços)
    $nome = preg_replace('/[^a-z\s]/', '', $nome);

    return $nome;
  }

  /**
   * Remove acentos de uma string
   */
  private function remover_acentos($string)
  {
    $acentos = [
      'á' => 'a',
      'à' => 'a',
      'ã' => 'a',
      'â' => 'a',
      'ä' => 'a',
      'é' => 'e',
      'è' => 'e',
      'ê' => 'e',
      'ë' => 'e',
      'í' => 'i',
      'ì' => 'i',
      'î' => 'i',
      'ï' => 'i',
      'ó' => 'o',
      'ò' => 'o',
      'õ' => 'o',
      'ô' => 'o',
      'ö' => 'o',
      'ú' => 'u',
      'ù' => 'u',
      'û' => 'u',
      'ü' => 'u',
      'ç' => 'c',
      'Á' => 'A',
      'À' => 'A',
      'Ã' => 'A',
      'Â' => 'A',
      'Ä' => 'A',
      'É' => 'E',
      'È' => 'E',
      'Ê' => 'E',
      'Ë' => 'E',
      'Í' => 'I',
      'Ì' => 'I',
      'Î' => 'I',
      'Ï' => 'I',
      'Ó' => 'O',
      'Ò' => 'O',
      'Õ' => 'O',
      'Ô' => 'O',
      'Ö' => 'O',
      'Ú' => 'U',
      'Ù' => 'U',
      'Û' => 'U',
      'Ü' => 'U',
      'Ç' => 'C'
    ];

    return strtr($string, $acentos);
  }

  private function remover_preposicoes($string)
  {
    $preposicoes = ['de', 'da', 'do', 'dos', 'das', 'e'];
    return str_replace($preposicoes, '', $string);
  }

  /**
   * Envia email quando a API de CPF falha
   */
  private function enviar_email_api_falha($cpf, $curl_error, $http_code)
  {

    $email_body = '
        <div style="text-align:left;">
            <h4>Falha na validação de CPF via API</h4>
            <p><strong>CPF:</strong> ' . $cpf . '</p>
            <p><strong>Erro cURL:</strong> ' . ($curl_error ?: 'Nenhum') . '</p>
            <p><strong>HTTP Code:</strong> ' . $http_code . '</p>
            <p><strong>Data/Hora:</strong> ' . date('d/m/Y H:i:s') . '</p>
            <p><strong>IP:</strong> ' . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . '</p>
            <p><strong>User Agent:</strong> ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . '</p>
            <br>
            <p><small>O cadastro foi processado normalmente, mas a validação via API não pôde ser realizada.</small></p>
        </div>';

    $mail_data = array(
      'name' => 'Sistema Bazar Bikes',
      'to' => get_option('admin_email'),
      'subject' => 'Falha na validação de CPF via API',
      'msg_header' => 'Notificação de falha na API de CPF',
      'email_body' => trim($email_body),
      'fail_on_error' => false,
    );

    // Verificar se a classe de email existe antes de usar
    if (class_exists('__Bazar_Send_Mail')) {
      $send_mail = new __Bazar_Send_Mail();
      $send_result = $send_mail->send_mail_msg($mail_data);
      // Não é necessário processar retorno aqui pois é apenas notificação interna
    }
  }


  private function enviar_email_falha_api_cpf_nome($cpf, $msg_error)
  {

    $email_body = '
        <div style="text-align:left;">
            <h4>Falha na validação de CPF via API</h4>
            <p><strong>CPF:</strong> ' . $cpf . '</p>
            <p><strong>Erro:</strong> ' . $msg_error . '</p>
            <p><strong>Data/Hora:</strong> ' . date('d/m/Y H:i:s') . '</p>
            <p><strong>IP:</strong> ' . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . '</p>
            <p><strong>User Agent:</strong> ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . '</p>
            <br>
            <p><small>O cadastro foi processado normalmente, mas a validação via API não pôde ser realizada.</small></p>
        </div>';

    $mail_data = array(
      'name' => 'Sistema Bazar Bikes',
      'to' => get_option('admin_email'),
      'subject' => 'Falha na validação de CPF via API',
      'msg_header' => 'Notificação de falha na API de CPF',
      'email_body' => trim($email_body),
      'fail_on_error' => false,
    );

    // Verificar se a classe de email existe antes de usar
    if (class_exists('__Bazar_Send_Mail')) {
      $send_mail = new __Bazar_Send_Mail();
      $send_result = $send_mail->send_mail_msg($mail_data);
      // Não é necessário processar retorno aqui pois é apenas notificação interna
    }
  }

}
?>