<?php
// Classe principal para inserção de anúncios de bicicletas
abstract class __Bazar_Anuncio_Crude extends __Bazar_Error_Handler
{
  // Propriedades comuns
  protected $post_type;
  public $label;
  public $action;
  public $nonce;
  protected $post_id;
  protected $admin_email = 'XXXXXX';
  protected $operation_success = false;
  protected $attachment_upload;
  protected $debug_log = array();
  protected $start_time;
  public $custom_tags = array();
  // IDs dos componentes relevantes (centralizados em bazar_get_component_title_ids)
  protected $component_title_ids;
  protected $title_separator = ' ';
  protected $category;
  protected $category_id;
  protected $validations;
  protected $has_files = false;
  protected $files_count = 0;
  protected $is_edit = false;
  protected $is_silent_admin_edit = false;
  /** Se deve enviar e-mail ao admin com assunto "Reavaliação" (edição pelo autor de anúncio já no ar/aprovado). */
  protected $send_admin_reavaliacao_email = false;
  private $anti_spam;

  // Construtor da classe
  public function __construct()
  {

    // Limpar qualquer output anterior
    if (ob_get_level()) {
      ob_clean();
    }

    try {
      // Inicialização
      if (session_status() === PHP_SESSION_NONE)
        session_start();
      // Injeção de dependência
      $this->attachment_upload = new __Bazar_Attachment_Upload();
      $this->validations = new __BazarValidations();
      $this->anti_spam = new __Bazar_Message_Anti_Spam();
      // Inicializar IDs dos componentes (centralizados)
      $this->component_title_ids = bazar_get_component_title_ids();
      // Mensagem defatul
      $this->inicializar_resposta_padrao();
      // Define o tipo de post || 4 = post
      $this->define_post_type(1);
      // Processar formulário
      $this->process_form();

      // DEBUG: Se process_form retornou false mas não definiu um erro específico, adicionar log
      // if( $process_result === false && 
      //     isset($this->data_output['log']['debug_log']) && 
      //     $this->data_output['log']['debug_log'] === 'Inicializa resposta padrão' ) {
      // 	$this->log_debug('constructor', 'process_form() retornou false mas não definiu erro específico');
      // }

    } catch (Exception $e) {
      $this->definir_erro_excecao($e);
    } catch (Error $e) {
      $this->definir_erro_excecao($e);
    }

    // Adicionar debug_log se houver
    if (!empty($this->debug_log) && count($this->debug_log) > 0) {
      $this->data_output['debug_log'] = $this->debug_log;
    }

    // Output padronizado
    header('Content-Type: application/json');
    echo json_encode($this->data_output);
    exit;
  }


  // Métodos abstratos que devem ser implementados pelas classes filhas
  abstract protected function process_form();

  protected function validation_form()
  {

    // DEBUG: $this->log_debug('validation_form', 'Iniciando validation_form()');

    // Verificar se é edição
    $this->is_edit = $this->is_edit_form();
    // DEBUG: $this->log_debug('validation_form', 'is_edit: ' . ($this->is_edit ? 'true' : 'false'));

    // Verificar segurança e método POST
    if (!$this->verificar_seguranca()) {
      // DEBUG: $this->log_debug('validation_form', 'Falhou em verificar_seguranca()');
      return false;
    }
    // DEBUG: $this->log_debug('validation_form', 'verificar_seguranca() passou');

    // Verificar classes obrigatórias
    if (!$this->validation_required_classes()) {
      // DEBUG: $this->log_debug('validation_form', 'Falhou em validation_required_classes()');
      return false;
    }
    // DEBUG: $this->log_debug('validation_form', 'validation_required_classes() passou');

    // Validar campos obrigatórios baseados na categoria
    if (!$this->validate_required_fields()) {
      // DEBUG: $this->log_debug('validation_form', 'Falhou em validate_required_fields()');
      return false;
    }
    // DEBUG: $this->log_debug('validation_form', 'validate_required_fields() passou');

    // Validação de imagens
    if (!$this->images_gallery_validation()) {
      // DEBUG: $this->log_debug('validation_form', 'Falhou em images_gallery_validation()');
      return false;
    }

    // DEBUG: $this->log_debug('validation_form', 'images_gallery_validation() passou');		
    // DEBUG: $this->log_debug('validation_form', 'validation_form() concluído com sucesso');
    return true;
  }

  private function is_edit_form()
  {
    return (
      isset($_POST['action'])
      && !empty($_POST['action'])
      && $_POST['action'] === 'bazar_anuncio_editar'
    );
  }

  /**
   * Valida todos os campos obrigatórios baseados na categoria
   * @return bool true se todos os campos obrigatórios estão preenchidos
   */
  protected function validate_required_fields()
  {

    if (!$this->validate_post_category()) {
      return false;
    }

    // Obter campos obrigatórios baseados na categoria
    $required_fields = $this->get_required_fields_by_category($this->category);

    // Validar cada campo obrigatório
    if ($required_fields):
      foreach ($required_fields as $field):
        // Validar campo específico
        if (!$this->validate_single_field($field)) {
          return false;
        }
      endforeach;
    endif;

    return true;
  }

  /**
   * Valida um único campo obrigatório
   * @param string $field - Nome do campo a validar
   * @return bool true se campo está preenchido
   */
  protected function validate_single_field($field)
  {

    // Caso especial: campos de componente (formato componente[ID])
    if ($this->is_component_field($field)) {
      return $this->validate_component_field($field);
    }

    // Validação padrão para outros campos
    return $this->validate_standard_field($field);
  }

  /**
   * Verifica se um campo é do tipo componente[ID]
   * @param string $field - Nome do campo
   * @return bool true se é campo de componente
   */
  protected function is_component_field($field)
  {
    return preg_match('/^componente\[(\d+)\]$/', $field) === 1;
  }

  /**
   * Valida campo de componente obrigatório
   * @param string $field - Nome do campo no formato componente[ID]
   * @return bool true se componente está preenchido
   */
  protected function validate_component_field($field)
  {

    // Extrair ID do componente
    preg_match('/^componente\[(\d+)\]$/', $field, $matches);
    $component_id = intval($matches[1]);

    // Obter array de componentes do POST
    $componente_post = (isset($_POST['componente']) && is_array($_POST['componente']))
      ? $_POST['componente']
      : array();

    // Verificar se componente está preenchido
    if (
      !isset($componente_post[$component_id])
      || empty(trim($componente_post[$component_id]))
    ) {
      // Buscar nome do componente para mensagem de erro
      $componente_name = $this->get_component_name($component_id);
      $this->definir_erro_campo_obrigatorio(
        $field,
        $componente_name
      );
      return false;
    }

    return true;
  }

  /**
   * Obtém o nome de um componente pelo ID
   * @param int $component_id - ID do componente
   * @return string Nome do componente ou 'Componente' como fallback
   */
  protected function get_component_name($component_id)
  {

    $componente_term = get_term($component_id);
    return ($componente_term && !is_wp_error($componente_term))
      ? $componente_term->name
      : 'Componente';
  }

  /**
   * Valida campo padrão (não componente)
   * @param string $field - Nome do campo
   * @return bool true se campo está preenchido
   */
  protected function validate_standard_field($field)
  {

    // Verificar se campo existe no POST
    if (!isset($_POST[$field])) {
      $this->definir_erro_campo_obrigatorio($field);
      return false;
    }

    // Verificar se campo está vazio (is_field_empty já trata arrays, strings, números, etc)
    if ($this->is_field_empty($_POST[$field])) {
      $this->definir_erro_campo_obrigatorio($field);
      return false;
    }


    // Validação anti-spam para mensagem (apenas para campo mensagem)
    if ($field === 'txt-descricao') {
      $spam_validation = $this->anti_spam->validate_message($_POST[$field]);
      if (!$spam_validation['valid']) {
        $this->definir_erro_campos_invalidos(
          $spam_validation['error'] ?? 'Sua "Descrição" contém conteúdo suspeito e não pode ser enviada.',
          $field
        );
        return false;
      }
    }

    return true;
  }


  protected function validation_required_classes()
  {

    // Verificar classes e funções necessárias
    if (!class_exists('__Bazar_Attachment_Upload')) {
      $this->definir_erro_servidor(
        'Recursos faltando. Não foi possível processar sua solicitação, entre em contato com o suporte.',
        'validation_required_classes',
        '__Bazar_Attachment_Upload faltando'
      );
      return false;
    }

    if (!class_exists('__Bazar_Terms_Manager')) {
      $this->definir_erro_servidor(
        'Recursos faltando. Não foi possível processar sua solicitação, entre em contato com o suporte.',
        'validation_required_classes',
        '__Bazar_Terms_Manager faltando'
      );
      return false;
    }

    if (!class_exists('__Bazar_Send_Mail')) {
      $this->definir_erro_servidor(
        'Recursos faltando. Não foi possível processar sua solicitação, entre em contato com o suporte.',
        'validation_required_classes',
        '__Bazar_Send_Mail faltando'
      );
      return false;
    }

    if (!function_exists('add_row')) {
      $this->definir_erro_servidor(
        'Recursos faltando. Não foi possível processar sua solicitação, entre em contato com o suporte.',
        'validation_required_classes',
        'add_row > ACF Fields faltando'
      );
      return false;
    }

    // if (!class_exists('__Bazar_QR_Code'))
    return true;
  }

  /**
   * Retorna array de campos obrigatórios baseados na categoria
   * @param string|null $category - Categoria ativa (ex: 'bicicleta', 'peca', 'acessorio')
   * @return array Array com nomes dos campos obrigatórios
   */
  protected function get_required_fields_by_category($category = null)
  {

    if (!$category) {
      return array();
    }

    // Campos comuns a todas as categorias
    // Localização (user_cidade, user_estado) não é mais obrigatória: vem do perfil do usuário (Minha Conta)
    $common_fields = array(
      'category',
      'modalidade',
      'marcas_modelos',
      'marcas_modelos_child',
      'conservacao',
      'material',
      'peso',
      'valor',
      'ano',
      'cor',
      'genero',
      'idade',
      'nota_fiscal',
      'exibir_contato',
      'negociacao',
      'termos',
      'user_nome',
      'user_email',
      // 'user_cidade', 'user_estado' removidos: localidade vem do cadastro do usuário
      // 'estado_sigla',
      // 'cep',
    );

    // Campos específicos por categoria
    $category_specific_fields = array();

    if ($category === 'bicicleta') {
      $category_specific_fields = $this->required_bicicleta_components();
    } elseif ($category === 'peca') {
      // Peça não tem campos específicos obrigatórios além dos comuns
      $category_specific_fields = array(
        'componente_pecas',
        'componente_pecas_child',
      );
    } elseif ($category === 'acessorio') {
      // Acessório não tem campos específicos obrigatórios além dos comuns
      $category_specific_fields = array(
        'acessorio',
        'acessorio_child',
      );
    }
    // Combinar campos comuns e específicos
    return array_merge($common_fields, $category_specific_fields);
  }


  /**
   * Retorna array de campos obrigatórios de componentes para bicicleta
   * Busca componentes com default_bicicletas = true e retorna no formato componente[ID]
   * Reutiliza método global bazar_get_componentes_default() que já tem cache interno
   * @return array Array com campos obrigatórios no formato ['componente[13210]', 'componente[12790]', ...]
   */
  protected function required_bicicleta_components()
  {

    $required_fields = array();

    // Reutilizar método global que já busca componentes obrigatórios com cache
    // bazar_get_componentes_default() usa __Bazar_Component_Helper::get_default_components()
    if (function_exists('bazar_get_componentes_default')) {
      $componentes = bazar_get_componentes_default();
      if (empty($componentes) || !is_array($componentes)) {
        return $required_fields;
      }
    }

    // Converter IDs para formato componente[ID] para validação
    foreach ($componentes as $componente) {
      if (isset($componente->term_id)) {
        $required_fields[] = 'componente[' . intval($componente->term_id) . ']';
      }
    }

    return $required_fields;
  }



  /**
   * Verifica se há novas imagens sendo enviadas e se é edição
   * Globaliza $this->has_new_files e $this->is_edit para uso em outros métodos
   */
  protected function check_has_files()
  {

    // Verificar se há novas imagens sendo enviadas
    $this->has_files = false;

    if (
      isset($_FILES['input-file'])
      && !empty($_FILES['input-file'])
    ) {
      $input_file = $_FILES['input-file'];

      $has_files = (
        isset($input_file['name'][0])
        && !empty($input_file['name'][0] ?? [])
      ) ? true : false;

      $count_files = ($has_files)
        ? count($input_file['name'] ?? [])
        : 0;

      $this->has_files = ($count_files > 0);
      $this->files_count = $count_files;

    }
  }

  protected function images_gallery_validation()
  {

    // set $this->has_files 
    // set $this->files_count
    $this->check_has_files();

    // Em edição, se não houver novas imagens sendo enviadas, não validar quantidade mínima		
    if (
      $this->is_edit
      && !$this->has_files
    ) {
      return true;
    }

    // Verifica o número total de imagens (existentes + novas)
    $total_images = 0;
    $existing_images = 0;

    // Conta imagens existentes - apenas para o caso de edição
    if (
      isset($_POST['gallery-order'])
      && is_array($_POST['gallery-order'])
    ):
      // $existing_images = count(array_filter()); // remove itens vazaios e conta
      $existing_images = count($_POST['gallery-order'] ?? []);
      $total_images += $existing_images;
    endif;

    // Conta novas imagens sendo enviadas (usa propriedade globalizada)
    if ($this->has_files) {
      $total_images += $this->files_count;
    }

    // Validação do número total de imagens
    $min_images = $this->attachment_upload->get_min_images_count();
    $max_images = $this->attachment_upload->get_max_images_count();

    if ($total_images < $min_images):
      $this->definir_erro_campos_invalidos(
        'É preciso ter pelo menos ' . $min_images . ' fotos para o produto.',
        'input-file',
        'Total de imagens mínimo não atingido: ' . $total_images
      );
      return false;
    endif;

    if ($total_images > $max_images) {
      $this->definir_erro_campos_invalidos(
        'O número máximo de imagens permitido é ' . $max_images . '.',
        'input-file',
        'Total de imagens excedido: ' . $total_images
      );
      return false;
    }


    // Validar tipo, tamanho e dimensões apenas se houver imagens
    if ($this->has_files && $this->files_count > 0) {

      $check_images = $this->attachment_upload;

      $files = $_FILES['input-file'];
      foreach ($files['name'] as $key => $file) {

        $file_data = $check_images->prepare_file_data(
          $files,
          $key
        );

        $check_images->init_upload_process($file_data);

        if (!$check_images->check_image_type) {
          $this->definir_erro_campos_invalidos(
            'Tipo de arquivo não suportado. Use apenas: ' . $check_images->get_allowed_image_types_string() . '.',
            'input-file',
            'Tipo de arquivo inválido: ' . $file_data['type']
          );
          return false;
        }

        if (!$check_images->check_max_size) {
          $this->definir_erro_campos_invalidos(
            'Fotos muito grandes, o tamanho máximo para cada imagem é de ' . $check_images->get_max_size_label(),
            'input-file',
            'Arquivo muito grande: ' . $file_data['size']
          );
          return false;
        }

        if (!$check_images->check_image_dimensions) {
          $min_width = $check_images->get_min_image_width();
          $max_width = $check_images->get_max_image_width();
          $min_height = $check_images->get_min_image_height();
          $max_height = $check_images->get_max_image_height();
          $this->definir_erro_campos_invalidos(
            'Dimensões da imagem inválidas. Mínimo ' . $min_width . 'px e máximo ' . $max_width . 'px de largura. Mínimo de ' . $min_height . 'px e máximo de ' . $max_height . 'px para altura.',
            'input-file',
            'Dimensões inválidas: W: ' . $file_data['width'] . ' H: ' . $file_data['height']
          );
          return false;
        }

      }
    }
    return true;
  }

  protected function define_post_type($type = 1)
  {

    if (!$type) {
      return false;
    }

    switch ($type) {
      case 1:
        $this->post_type = 'post';
        break;
      case 2:
        $this->post_type = 'eventos';
        break;
      case 3:
        $this->post_type = 'revendas';
        break;
      default:
        $this->definir_erro_servidor(
          'Falha de validação! Tipo de Anúncio inválido.',
          'define_post_type',
          'Tipo de post não encontrado'
        );
        return false;
    }
    return true;
  }



  /*
   * CRUD OPERATIONS
   *
   */
  protected function generate_title()
  {

    $category = isset($_POST['category']) ? wp_strip_all_tags($_POST['category']) : 'bicicleta';
    $category_term = get_term_by('slug', $category, 'category');
    $category_name = ($category_term && !is_wp_error($category_term)) ? $category_term->name : '';

    // Gerar título baseado na categoria
    switch ($category) {
      case 'peca':
        return $this->generate_peca_title($category_name);
      case 'acessorio':
        return $this->generate_acessorio_title($category_name);
      case 'bicicleta':
      default:
        return $this->generate_bicicleta_title($category_name);
    }
  }

  /**
   * Gera título para categoria 'bicicleta'
   * @param {string} $category_name - Nome da categoria
   * @returns {string} Título gerado
   */
  protected function generate_bicicleta_title($category_name)
  {

    $aro_ID = $this->component_title_ids['aro'];
    $quadro_ID = $this->component_title_ids['quadro'];
    $cambio_dianteiro_ID = $this->component_title_ids['cambio_dianteiro'];
    $cambio_traseiro_ID = $this->component_title_ids['cambio_traseiro'];

    $title = $category_name;

    // MODALIDADE
    if (isset($_POST['modalidade']) && !empty($_POST['modalidade'])) {
      $modalidade_term = get_term_by('slug', wp_strip_all_tags($_POST['modalidade']), 'modalidade');
      if ($modalidade_term && !is_wp_error($modalidade_term)) {
        $title .= ' | ' . $modalidade_term->name;
      }
    }

    // MARCA E MODELO	
    if (isset($_POST['marcas_modelos']) && isset($_POST['marcas_modelos_child'])) {
      $title .= ' ' . $_POST['marcas_modelos'] . ' ' . $_POST['marcas_modelos_child'];
    }

    // ARO
    if (isset($_POST['componente[' . $aro_ID . ']'])) {
      $aro_term = get_term(intval($_POST['componente[' . $aro_ID . ']']), 'componente');
      if ($aro_term && !is_wp_error($aro_term)) {
        $title .= ' Aro ' . $aro_term->name;
      }
    }

    // QUADRO
    if (isset($_POST['componente[' . $quadro_ID . ']'])) {
      $quadro_term = get_term(intval($_POST['componente[' . $quadro_ID . ']']), 'componente');
      if ($quadro_term && !is_wp_error($quadro_term)) {
        $title .= ' Quadro ' . $quadro_term->name;
      }
    }

    // VELOCIDADES
    if (isset($_POST['componente[' . $cambio_dianteiro_ID . ']']) && isset($_POST['componente[' . $cambio_traseiro_ID . ']'])) {
      $cambio_dianteiro = get_term(intval($_POST['componente[' . $cambio_dianteiro_ID . ']']), 'componente');
      $cambio_traseiro = get_term(intval($_POST['componente[' . $cambio_traseiro_ID . ']']), 'componente');

      if ($cambio_dianteiro && !is_wp_error($cambio_dianteiro) && $cambio_traseiro && !is_wp_error($cambio_traseiro)) {
        $v1 = intval(str_replace('v', '', $cambio_dianteiro->name));
        $v2 = intval(str_replace('v', '', $cambio_traseiro->name));
        if ($v1 > 0 && $v2 > 0) {
          $velocidades = ($v1 * $v2) . ' V';
          $title .= ' ' . $velocidades;
        }
      }
    }

    return wp_strip_all_tags($title);
  }

  /**
   * Gera título para categoria 'peca'
   * Formato: Componente + Especificação + Medidas (se existirem)
   * @param {string} $category_name - Nome da categoria
   * @returns {string} Título gerado
   */
  protected function generate_peca_title($category_name)
  {

    $title = $category_name;

    // COMPONENTE E ESPECIFICAÇÃO
    if (isset($_POST['componente_pecas']) && !empty($_POST['componente_pecas'])) {
      $componente_term = get_term(intval($_POST['componente_pecas']), 'componente');
      if ($componente_term && !is_wp_error($componente_term)) {
        $title .= $this->title_separator . $componente_term->name;

        // Especificação (child)
        if (isset($_POST['componente_pecas_child']) && !empty($_POST['componente_pecas_child'])) {
          $especificacao_term = get_term(intval($_POST['componente_pecas_child']), 'componente');
          if ($especificacao_term && !is_wp_error($especificacao_term)) {
            $title .= ' ' . $especificacao_term->name;
          }
        }
      }
    }

    // MARCA E MODELO
    $marca = isset($_POST['marcas_modelos']) ? trim($_POST['marcas_modelos']) : '';
    $modelo = isset($_POST['marcas_modelos_child']) ? trim($_POST['marcas_modelos_child']) : '';
    if (!empty($marca) && !empty($modelo)) {
      $title .= $this->title_separator . wp_strip_all_tags($marca . ' ' . $modelo);
    } elseif (!empty($marca)) {
      $title .= $this->title_separator . wp_strip_all_tags($marca);
    }

    // MEDIDAS (se existirem)
    $medidas = $this->get_medidas_from_post();
    if (!empty($medidas)) {
      $title .= $this->title_separator . implode(' ', $medidas);
    }

    return $title;
  }

  /**
   * Gera título para categoria 'acessorio'
   * Formato: Acessório + Especificação + Marca + Modelo + Medidas (se existirem)
   * @param {string} $category_name - Nome da categoria
   * @returns {string} Título gerado
   */
  protected function generate_acessorio_title($category_name)
  {

    $title = $category_name;

    // ACESSÓRIO E ESPECIFICAÇÃO
    if (isset($_POST['acessorio']) && !empty($_POST['acessorio'])) {
      $acessorio_term = get_term(intval($_POST['acessorio']), 'acessorio');
      if ($acessorio_term && !is_wp_error($acessorio_term)) {
        $title .= $this->title_separator . $acessorio_term->name;

        // Especificação (child)
        if (isset($_POST['acessorio_child']) && !empty($_POST['acessorio_child'])) {
          $especificacao_term = get_term(intval($_POST['acessorio_child']), 'acessorio');
          if ($especificacao_term && !is_wp_error($especificacao_term)) {
            $title .= $this->title_separator . $especificacao_term->name;
          }
        }
      }
    }

    // MARCA E MODELO
    $marca = isset($_POST['marcas_modelos']) ? trim($_POST['marcas_modelos']) : '';
    $modelo = isset($_POST['marcas_modelos_child']) ? trim($_POST['marcas_modelos_child']) : '';
    if (!empty($marca) && !empty($modelo)) {
      $title .= $this->title_separator . wp_strip_all_tags($marca . ' ' . $modelo);
    } elseif (!empty($marca)) {
      $title .= $this->title_separator . wp_strip_all_tags($marca);
    }

    // MEDIDAS (se existirem)
    $medidas = $this->get_medidas_from_post();
    if (!empty($medidas)) {
      $title .= $this->title_separator . implode(' ', $medidas);
    }

    return wp_strip_all_tags($title);
  }

  /**
   * Obtém todas as medidas preenchidas do POST
   * @returns {Array} Array com nomes das medidas
   */
  protected function get_medidas_from_post()
  {
    $medidas = array();

    // Determinar qual campo usar baseado na categoria
    $medidas_field = 'medidas';
    $medidas_child_field = 'medidas_child';

    if ($this->category === 'acessorio') {
      $medidas_field = 'medidas_acessorio';
      $medidas_child_field = 'medidas_acessorio_child';
    }

    if (!isset($_POST[$medidas_field]) || !is_array($_POST[$medidas_field])) {
      return $medidas;
    }

    foreach ($_POST[$medidas_field] as $index => $medida_id) {
      if (empty($medida_id)) {
        continue;
      }

      $medida_term = get_term(intval($medida_id), 'medidas');
      if ($medida_term && !is_wp_error($medida_term)) {
        $medida_name = $medida_term->name;

        // Buscar especificação correspondente
        if (isset($_POST[$medidas_child_field][$index]) && !empty($_POST[$medidas_child_field][$index])) {
          $child_term = get_term(intval($_POST[$medidas_child_field][$index]), 'medidas');
          if ($child_term && !is_wp_error($child_term)) {
            $medida_name .= ' ' . $child_term->name;
          }
        }

        $medidas[] = $medida_name;
      }
    }

    return $medidas;
  }

  protected function validate_post_category()
  {

    // Obter categoria do formulário
    $this->category = (isset($_POST['category']) && !empty($_POST['category']))
      ? sanitize_text_field($_POST['category'])
      : null;

    if (empty($this->category)) {
      $this->definir_erro_servidor(
        'Categoria ou ID da categoria não informados.',
        'validate_post_category'
      );
      return false;
    }

    $this->category_id = isset($_POST['category_id'])
      ? intval($_POST['category_id'])
      : null;

    if (empty($this->category_id)) {
      $this->definir_erro_servidor(
        'ID da categoria não informado.',
        'validate_post_category'
      );
      return false;
    }

    return true;
  }

  protected function insert_post_operation($title = null)
  {

    if (empty($title) || empty($_POST['txt-descricao'])) {
      $this->definir_erro_servidor(
        'Erro ao gerar o Título. Verifique os campos obrigatórios.',
        'insert_post_operation'
      );
      return false;
    }

    $post_data_insert = array(
      'post_title' => wp_strip_all_tags($title),
      'post_content' => wp_kses_post($_POST['txt-descricao']),
      'post_type' => wp_strip_all_tags($this->post_type),
      'post_category' => array($this->category_id),
      'post_status' => 'pending',
    );

    $new_post_id = wp_insert_post($post_data_insert);

    if (!$new_post_id || is_wp_error($new_post_id)) {
      $this->definir_erro_servidor(
        'Falha ao tentar cadastrar seu Anúncio!',
        'wp_insert_post',
        $new_post_id->get_error_message()
      );
      return false;
    }

    $this->operation_success = true;

    return $new_post_id;
  }

  protected function edit_post_operation($post_id = null, $title = null)
  {

    if (empty($title) || empty($post_id)) {
      $this->definir_erro_servidor(
        'Parâmetros vazios. Verifique os campos obrigatórios.',
        'edit_post_operation'
      );
      return false;
    }

    // Verificar se o anúncio está reprovado (draft com motivos de indeferimento)
    // Se estiver, voltar para 'pending' para reavaliação
    $current_status = get_post_status($post_id);
    $motivos_indeferimento = get_field('motivos_para_indeferimento', $post_id);
    $meta_aprov = defined('BAZAR_META_APROVADO_ADM') ? BAZAR_META_APROVADO_ADM : 'bazar_anuncio_aprovado_adm';
    $is_already_approved = ((string) get_post_meta((int) $post_id, $meta_aprov, true) === '1');
    $is_admin_edit = current_user_can('manage_options');
    $this->is_silent_admin_edit = ($this->is_edit && $is_admin_edit && $is_already_approved);
    $new_status = 'pending';

    $post_author_id = (int) get_post_field('post_author', $post_id);
    $editor_id = (int) get_current_user_id();
    $is_author_editing_own = ($post_author_id > 0 && $post_author_id === $editor_id);
    $was_approved_or_live = ($current_status === 'publish')
      || ($is_already_approved && $current_status !== 'draft');
    $this->send_admin_reavaliacao_email = $this->is_edit
      && $is_author_editing_own
      && $was_approved_or_live;

    // Edição pós-aprovação feita por ADM: manter status e não voltar para fila.
    if ($this->is_silent_admin_edit) {
      $new_status = $current_status;
    }

    // Se estava em 'draft' com motivos de indeferimento, voltar para 'pending' para reavaliação
    if (!$this->is_silent_admin_edit && $current_status === 'draft' && !empty($motivos_indeferimento)) {
      $new_status = 'pending';
      // Limpar motivos de indeferimento ao reenviar para reavaliação
      update_field('motivos_para_indeferimento', '', $post_id);
    }

    $post_data_edit = array(
      'ID' => $post_id,
      'post_title' => wp_strip_all_tags($title),
      'post_content' => wp_kses_post($_POST['txt-descricao']),
      'post_type' => wp_strip_all_tags($this->post_type),
      'post_status' => $new_status
    );

    $edit_post_id = wp_update_post($post_data_edit);
    if (!$edit_post_id || is_wp_error($edit_post_id)) {
      $this->definir_erro_servidor(
        'Falha ao tentar editar seu Anúncio!',
        'wp_update_post',
        $edit_post_id->get_error_message()
      );
      return false;
    }

    // Volta para pending = precisa nova aprovação do ADM. Sem remover a meta, o single tratava como
    // "aprovado aguardando dados" e escondia o botão Aprovar (bug ao editar anúncio já publicado).
    if ($new_status === 'pending' && !$this->is_silent_admin_edit) {
      delete_post_meta((int) $post_id, $meta_aprov);
    }

    $this->operation_success = true;

    return $edit_post_id;
  }

  /*
   * ADD TAXONOMY TERMS
   *
   */
  protected function add_taxonomy_terms($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    if (!$this->add_marca_modelo($post_id))
      return false;

    if (!$this->add_localizacao($post_id))
      return false;

    if (!$this->add_taxonomy_default($post_id))
      return false;

    if (!$this->add_taxonomy_by_category($post_id))
      return false;

    return true;

  }

  protected function add_marca_modelo($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    // Remover termos antigos da taxonomia 'marca-modelo' antes de adicionar os novos
    // Isso garante que apenas os novos termos sejam associados ao post
    wp_set_object_terms($post_id, array(), 'marca-modelo');

    // insert_parent_child_terms() agora cuida da conversão de IDs para nomes automaticamente
    $add_term = __Bazar_Terms_Manager::insert_parent_child_terms(
      $post_id,
      wp_strip_all_tags($_POST['marcas_modelos']),
      wp_strip_all_tags($_POST['marcas_modelos_child']),
      'marca-modelo'
    );

    if (!$add_term) {
      // Obter mensagem de erro do Terms Manager
      $error_msg = __Bazar_Terms_Manager::get_error_message(true);
      $debug_log = __Bazar_Terms_Manager::get_debug_log();

      // Criar mensagem simples e clara
      $simple_msg = !empty($error_msg)
        ? 'Erro ao inserir marca/modelo: ' . $error_msg
        : 'Erro ao inserir marca/modelo (marca: ' . ($_POST['marcas_modelos'] ?? 'não informado') . ', modelo: ' . ($_POST['marcas_modelos_child'] ?? 'não informado') . ')';


      if (!empty($debug_log)) {
        $this->definir_erro_servidor(
          $simple_msg,
          'add_marca_modelo',
          $debug_log
        );
      } else {
        $this->definir_erro_servidor(
          $simple_msg,
          'add_marca_modelo',
        );
      }
      return false;
    }

    return true;
  }

  /**
   * Resolve estado/cidade/sigla/CEP: em edição, se quem salva não é o autor do post,
   * usa o perfil do autor (coerente com input-location na página de editar).
   *
   * @param int|null $post_id
   * @return array{estado_value:string,cidade_value:string,estado_sigla:string,cep:string}
   */
  protected function resolve_location_data_for_post($post_id = null)
  {
    $estado_value = wp_strip_all_tags($_POST['user_estado'] ?? '');
    $cidade_value = wp_strip_all_tags($_POST['user_cidade'] ?? '');
    $estado_sigla = wp_strip_all_tags($_POST['user_estado_sigla'] ?? '');
    $cep = sanitize_text_field($_POST['user_cep'] ?? '');

    if (!$this->is_edit || empty($post_id)) {
      return array(
        'estado_value' => $estado_value,
        'cidade_value' => $cidade_value,
        'estado_sigla' => $estado_sigla,
        'cep' => $cep,
      );
    }

    $author_id = (int) get_post_field('post_author', $post_id);
    $current_id = (int) get_current_user_id();
    if ($author_id <= 0 || $author_id === $current_id) {
      return array(
        'estado_value' => $estado_value,
        'cidade_value' => $cidade_value,
        'estado_sigla' => $estado_sigla,
        'cep' => $cep,
      );
    }

    $estado_value = (string) get_user_meta($author_id, 'estado', true);
    $cidade_value = (string) get_user_meta($author_id, 'cidade', true);
    $estado_sigla = (string) get_user_meta($author_id, 'estado_sigla', true);
    $cep = (string) get_user_meta($author_id, 'cep', true);

    if ($estado_value !== '' && $estado_sigla === '') {
      $geo_api = BazarBikes_GeoAPI::getInstance();
      $estado_sigla = $geo_api->obter_sigla_estado($estado_value);
      if (empty($estado_sigla) || $estado_sigla === $estado_value) {
        $estado_sigla = (strlen($estado_value) === 2) ? strtoupper($estado_value) : $estado_value;
      }
    }

    return array(
      'estado_value' => $estado_value,
      'cidade_value' => $cidade_value,
      'estado_sigla' => $estado_sigla,
      'cep' => $cep,
    );
  }

  protected function add_localizacao($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    $loc = $this->resolve_location_data_for_post($post_id);
    $estado_value = $loc['estado_value'];
    $cidade_value = $loc['cidade_value'];
    $estado_sigla = $loc['estado_sigla'];

    // Se ainda não há cidade/estado (usuário novo sem preencher Minha Conta), não bloqueia o anúncio
    if ($cidade_value === '' && $estado_value === '') {
      return true;
    }

    $add_cidade = __Bazar_Terms_Manager::insert_parent_child_terms(
      $post_id,
      $estado_value,
      $cidade_value,
      'cidade',
      $estado_sigla,
      null
    );

    if (!$add_cidade) {
      // Obter mensagem de erro do Terms Manager
      $error_msg = __Bazar_Terms_Manager::get_error_message(true);
      $debug_log = __Bazar_Terms_Manager::get_debug_log();

      // Criar mensagem simples e clara
      $simple_msg = !empty($error_msg)
        ? 'Erro ao inserir localização: ' . $error_msg
        : 'Erro ao inserir localização (estado: ' . $estado_value . ', cidade: ' . $cidade_value . ')';

      if (!empty($debug_log)) {
        $this->definir_erro_servidor(
          $simple_msg,
          'add_localizacao',
          $debug_log
        );
      } else {
        $this->definir_erro_servidor(
          $simple_msg,
          'add_localizacao',
        );
      }
      return false;
    }

    return true;
  }

  protected function add_taxonomy_default($post_id = null)
  {

    // SET DEFATUL TAXONOMY TERMS
    wp_set_object_terms($post_id, wp_strip_all_tags($_POST['modalidade']), 'modalidade');
    wp_set_object_terms($post_id, wp_strip_all_tags($_POST['conservacao']), 'conservacao');
    wp_set_object_terms($post_id, wp_strip_all_tags($_POST['material']), 'material');
    wp_set_object_terms($post_id, wp_strip_all_tags($_POST['cor']), 'cor');
    wp_set_object_terms($post_id, wp_strip_all_tags($_POST['genero']), 'genero');
    wp_set_object_terms($post_id, wp_strip_all_tags($_POST['idade']), 'idade');

    if (isset($_POST['negociacao']) && !empty($_POST['negociacao'])) {
      wp_set_object_terms($post_id, $_POST['negociacao'], 'negociacao');
    }

    return true;
  }

  protected function add_taxonomy_by_category($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    if ($this->category === 'bicicleta') {
      return $this->add_components($post_id);
    }

    if ($this->category === 'peca') {
      return $this->add_peca($post_id);
    }

    if ($this->category === 'acessorio') {
      return $this->add_acessorios($post_id);
    }

    return;
  }

  protected function add_components($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    $terms_to_add = [];
    $add_row_check = false;

    if (isset($_POST['componente']) && !empty($_POST['componente'])):

      foreach ($_POST['componente'] as $parent_id => $child_id):

        if ($child_id !== ''):

          $parent_id = intval($parent_id);
          $child_id = intval($child_id);

          $value = [
            'componente_id' => $child_id,
            'parent_id' => $parent_id,
            'marca' => isset($_POST['c_marca'][$parent_id]) ? wp_strip_all_tags($_POST['c_marca'][$parent_id]) : '',
            'modelo' => isset($_POST['c_modelo'][$parent_id]) ? wp_strip_all_tags($_POST['c_modelo'][$parent_id]) : '',
          ];

          if (function_exists('add_row')) {
            $add_row_check = add_row('componentes', $value, $post_id);
            if (!$add_row_check):

              // Obter termos pai e filho
              $parent_term = get_term($parent_id, 'componente');
              $child_term = get_term($child_id, 'componente');

              $componente_nome = (
                $parent_term
                && !is_wp_error($parent_term)
                && $child_term && !is_wp_error($child_term)
              )
                ? $parent_term->name . ' > ' . $child_term->name
                : 'Componente ID ' . $parent_id . ' > ' . $child_id;

              $this->definir_erro_servidor(
                'Oops! Falha ao tentar inserir o Componente ' . $componente_nome,
                'add_row',
                'ACF Fields add_row() falhou'
              );
              return false;
            endif;
          }
          ;

          // Adicionar os termos pai e filho ao array auxiliar
          $terms_to_add[] = $parent_id;
          $terms_to_add[] = $child_id;

        endif;
      endforeach;
    endif;

    if (!empty($terms_to_add)):
      wp_set_object_terms($post_id, $terms_to_add, 'componente');
    endif;

    return true;
  }

  protected function add_peca($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    // VALIDAÇÃO: Verificar campos obrigatórios antes de inserir
    if (
      !isset($_POST['componente_pecas'])
      || $this->is_field_empty($_POST['componente_pecas'])
    ):
      $this->definir_erro_campo_obrigatorio(
        'componente_pecas',
        'Componente'
      );
      return false;
    endif;

    if (
      !isset($_POST['componente_pecas_child'])
      || $this->is_field_empty($_POST['componente_pecas_child'])
    ):
      $this->definir_erro_campo_obrigatorio(
        'componente_pecas_child',
        'Especificação'
      );
      return false;
    endif;

    // INSERÇÃO: Após validação bem-sucedida, inserir dados
    // IMPORTANTE: Passar ambos os IDs em um único array para evitar que a segunda chamada substitua a primeira
    $componentes_ids = array(
      intval($_POST['componente_pecas']),
      intval($_POST['componente_pecas_child'])
    );
    wp_set_object_terms($post_id, $componentes_ids, 'componente');

    // Processar medidas apenas da categoria atual (peca)
    $this->add_medidas($post_id, 'peca');

    return true;
  }

  protected function add_acessorios($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    // VALIDAÇÃO: Verificar campos obrigatórios antes de inserir
    if (
      !isset($_POST['acessorio'])
      || $this->is_field_empty($_POST['acessorio'])
    ):
      $this->definir_erro_campo_obrigatorio(
        'acessorio',
        'Acessório'
      );
      return false;
    endif;


    if (
      !isset($_POST['acessorio_child'])
      || $this->is_field_empty($_POST['acessorio_child'])
    ):
      $this->definir_erro_campo_obrigatorio(
        'acessorio_child',
        'Especificação'
      );
      return false;
    endif;

    // INSERÇÃO: Após validação bem-sucedida, inserir dados
    // IMPORTANTE: Passar ambos os IDs em um único array para evitar que a segunda chamada substitua a primeira
    $acessorios_ids = array(
      intval($_POST['acessorio']),
      intval($_POST['acessorio_child'])
    );
    wp_set_object_terms($post_id, $acessorios_ids, 'acessorio');

    // Processar medidas apenas da categoria atual (acessorio)
    $this->add_medidas($post_id, 'acessorio');

    return true;
  }

  /**
   * Adiciona medidas ao post
   * Processa array de medidas enviado via POST
   * Filtra medidas apenas da categoria ativa para evitar mistura de categorias
   * 
   * @param int $post_id ID do post
   * @param string|null $category Categoria ativa (ex: 'peca', 'acessorio', 'bicicleta')
   * @return bool true se processado com sucesso
   */
  protected function add_medidas($post_id = null, $category = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    // Se não foi passada categoria, usar a categoria atual
    if (!$category) {
      $category = $this->category;
    }

    // Bicicleta não tem medidas, retornar sucesso
    if ($category === 'bicicleta') {
      return true;
    }

    // Determinar qual campo usar baseado na categoria
    $medidas_field = 'medidas';
    $medidas_child_field = 'medidas_child';

    if ($category === 'acessorio') {
      $medidas_field = 'medidas_acessorio';
      $medidas_child_field = 'medidas_acessorio_child';
    }

    // Processar medidas se existirem
    if (isset($_POST[$medidas_field]) && is_array($_POST[$medidas_field]) && !empty($_POST[$medidas_field])) {

      $medidas_ids = array();

      // Para peça e acessório, processar todas as medidas
      // O JavaScript já filtra os campos por categoria antes do envio
      foreach ($_POST[$medidas_field] as $index => $medida_id) {
        $medida_id = intval($medida_id);
        if ($medida_id > 0) {
          $medidas_ids[] = $medida_id;

          // Se houver medida filho correspondente, adicionar também
          if (
            isset($_POST[$medidas_child_field])
            && is_array($_POST[$medidas_child_field])
            && isset($_POST[$medidas_child_field][$index])
            && !empty($_POST[$medidas_child_field][$index])
          ) {
            $medida_child_id = intval($_POST[$medidas_child_field][$index]);
            if ($medida_child_id > 0) {
              $medidas_ids[] = $medida_child_id;
            }
          }
        }
      }

      if (!empty($medidas_ids)) {
        // Remover duplicatas
        $medidas_ids = array_unique($medidas_ids);
        wp_set_object_terms($post_id, $medidas_ids, 'medidas');
      }
    }

    return true;
  }


  /*
   * ADD ACF FIELDS	 
   *
   */
  protected function add_acf_fields($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    $post_valor = wp_strip_all_tags($_POST['valor']);
    $valor = str_replace('.', '', $post_valor);
    $new_valor = str_replace(',', '.', $valor);

    update_field('valor', wp_strip_all_tags($new_valor), $post_id);
    update_field('peso', wp_strip_all_tags($_POST['peso']), $post_id);
    update_field('ano', wp_strip_all_tags($_POST['ano']), $post_id);
    update_field('nota_fiscal', wp_strip_all_tags($_POST['nota_fiscal']) === 'true', $post_id);
    update_field('exibir_contato', wp_strip_all_tags($_POST['exibir_contato']) === 'true', $post_id);

    return true;

  }

  /**
   * Adiciona campos de meta customizados (rating e cep)
   * 
   * @param int $post_id ID do post
   * @return bool true se todos os campos foram processados com sucesso
   */
  protected function add_meta_fields($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    // Array de campos meta a serem processados
    // Rating agora é um valor único (não mais array)
    // CEP: mesmo critério da localização (autor do anúncio quando o editor não é o autor)
    $loc = $this->resolve_location_data_for_post($post_id);
    $meta_fields = array(
      'simple_rating' => '5', // Valor padrão único para rating (1-5)
      'cep' => $loc['cep'],
    );

    return $this->process_meta_fields(
      $post_id,
      $meta_fields
    );

  }

  /**
   * Adiciona dados de proximidade baseados no CEP do anúncio
   * Não é erro fatal se falhar (pode ser problema temporário de API)
   * 
   * @param int $post_id ID do post
   * @return bool true se processado (não é erro fatal se falhar)
   */
  protected function add_proximidade_data($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return true; // Não é erro fatal

    $loc = $this->resolve_location_data_for_post($post_id);
    $cep = $loc['cep'];

    // Se não tem CEP, não é erro (anúncios antigos podem não ter)
    if (empty($cep)) {
      return true; // Sucesso silencioso
    }

    // Obter instância da GeoAPI
    $geo_api = BazarBikes_GeoAPI::getInstance();

    // Chamar método otimizado passando CEP já disponível
    // O método salvar_dados_proximidade_anuncio() aceita CEP como parâmetro
    // Mas ele busca do post_meta se não fornecido, então vamos passar explicitamente
    // Vamos modificar o método para aceitar CEP como parâmetro
    $result = $geo_api->salvar_dados_proximidade_anuncio($post_id, $cep);

    // Não é erro fatal se falhar (pode ser problema temporário de API)
    // Logar erro mas não bloquear criação do anúncio
    if (!$result) {
      error_log('BazarAnuncio: Falha ao gerar dados de proximidade para post_id: ' . $post_id);
    }

    return true; // Sempre retorna true (não bloqueia fluxo)
  }

  /**
   * Summary of process_meta_fields
   * 
   * @param mixed $post_id
   * @param mixed $meta_fields
   * @return bool
   */
  protected function process_meta_fields($post_id = null, $meta_fields = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    if (!isset($meta_fields) || empty($meta_fields))
      return false;

    // Processar cada campo meta
    foreach ($meta_fields as $meta_key => $meta_value) {

      // Ignorar valores vazios (exceto se for intencionalmente 0 ou false)
      // Para strings, verificar se está vazio após sanitização
      if (is_string($meta_value) && trim($meta_value) === '') {
        // Se o campo já existe e o novo valor está vazio, manter o valor existente
        $existing_value = get_post_meta($post_id, $meta_key, true);
        if ($existing_value !== '' && $existing_value !== false) {
          continue; // Manter valor existente, não atualizar com vazio
        }
      }

      // Usar update_post_meta que funciona tanto para criar quanto atualizar
      // Não retorna false quando o campo já existe (diferente de add_post_meta com unique)
      $result = update_post_meta($post_id, $meta_key, $meta_value);

      // update_post_meta retorna false apenas se houve erro real (post_id inválido, etc)
      // Retorna meta_id (int) se criou novo, ou true se atualizou, ou false se erro
      if ($result === false) {
        // Verificar se o post_id é válido antes de considerar erro
        if (!get_post($post_id)) {
          $this->log_debug(
            'add_meta_fields',
            'Falha ao salvar campo ' . $meta_key . ': post_id inválido'
          );
          return false;
        }
        // Se o post existe mas update_post_meta retornou false, pode ser que o valor seja o mesmo
        // Nesse caso, não é um erro, apenas continua
      }
    }

    return true;
  }

  /**
   * Summary of set_qrcode
   * 
   * Esse método é complementar
   * Se falhar não deve ser considerado um erro fatal
   * @param int $post_id - ID do post	 
   * @return bool
   */
  protected function set_qrcode($post_id = null)
  {

    return true;

    if (!$this->default_validation($post_id))
      return false;

    $qr_code = $this->qrcode_generator($post_id);
    if (!$qr_code) {
      $this->log_debug(
        'qrcode_error',
        'Erro ao gerar QR Code',
        false
      );
    }

    $meta_fields = array(
      'qr_code' => $qr_code,
    );

    $result = $this->process_meta_fields(
      $post_id,
      $meta_fields
    );

    // caso falhe, não deve ser considerado um erro fatal
    return true;
  }


  /*
   * ADD TAGS
   * 
   */
  protected function add_tags($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    // Adiciona tags customizadas
    if (!empty($this->custom_tags)) {
      wp_set_object_terms($post_id, $this->custom_tags, 'post_tag');
    }

    return true;
  }

  /*
   * UPLOAD FILES
   * 
   */
  protected function upload_files($post_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    // Usar propriedades globalizadas (já calculadas em check_has_files())
    // Se for edição e não há novas imagens, não fazer upload (retornar true)
    if ($this->is_edit && !$this->has_files) {
      return true;
    }

    // Se não for edição ou há novas imagens, validar que há arquivos
    if (!$this->has_files) {
      $this->definir_erro_servidor(
        'É preciso enviar pelo menos 3 imagens.',
        'upload_files',
        'Add anuncio -> $_FILES ["input-file"] está vazio.'
      );
      return false;
    }

    $files = $_FILES["input-file"];
    if ($files && isset($files['name'])):
      // Garantir que é array para processar corretamente
      $file_names = is_array($files['name']) ? $files['name'] : array($files['name']);
      $file_errors = is_array($files['error']) ? $files['error'] : array($files['error']);

      foreach ($file_names as $key => $value):
        // Pular arquivos vazios ou com erro 4 (UPLOAD_ERR_NO_FILE)
        if (empty($value) || trim($value) === '' || (isset($file_errors[$key]) && $file_errors[$key] === 4))
          continue;

        $file_data = $this->attachment_upload->prepare_file_data(
          $files,
          $key
        );

        $this->attachment_upload->upload_file(
          $file_data,
          $post_id
        );

        if (!$this->attachment_upload):
          $this->attachment_upload->clean_cache_images();
          $this->definir_erro_servidor(
            'Oops! Falha ao tentar fazer upload das imagens!',
            'upload_files',
            'Erro ao fazer upload das imagens. Arquivo: ' . $file_data['name']
          );
          return false;
        endif;

      endforeach;
    endif;

    if (
      isset($this->attachment_upload)
      && isset($this->attachment_upload->file_id)
      && $this->label === 'anuncio_inserir'
    ):
      $this->set_thumbnail(
        $post_id,
        $this->attachment_upload->file_id,
      );
    endif;

    return true;
  }

  protected function set_thumbnail($post_id = null, $attachment_id = null)
  {

    if (!$this->default_validation($post_id))
      return false;

    // Não é um erro fatal, caso não seja possível definir a thumbnail, prossiga com o processo
    $set_thumbnail = set_post_thumbnail($post_id, $attachment_id);
    if (is_wp_error($set_thumbnail)) {
      $this->definir_erro_servidor(
        'Oops! Falha ao tentar definir a thumbnail do anúncio!',
        'set_thumbnail',
        'Erro ao definir a thumbnail do anúncio. ID: ' . $attachment_id . ' - ' . $set_thumbnail->get_error_message()
      );
      return;
    }

    return true;
  }

  // Atualiza a ordem dos anexos
  protected function update_attachments_order()
  {

    if (!isset($_POST['gallery-order']) || empty($_POST['gallery-order']))
      return;

    foreach ($_POST['gallery-order'] as $menu_order => $attachment_id) {
      $update = wp_update_post(array(
        'ID' => $attachment_id,
        'menu_order' => $menu_order,
      ));

      if (!$update) {
        $this->definir_erro_servidor(
          'Oops! Falha ao tentar atualizar a ordem das imagens!',
          'update_attachments_order',
          'Erro ao atualizar a ordem das imagens. ID: ' . $attachment_id
        );
        return;
      }
    }
  }


  protected function process_successful_operation($post_id)
  {

    // DEBUG: $this->log_debug('process_successful_operation', 'Iniciando process_successful_operation() - post_id: ' . ($post_id ?? 'não definido'));
    // DEBUG: $this->log_debug('process_successful_operation', 'operation_success: ' . ($this->operation_success ? 'true' : 'false'));

    if (!$this->default_validation($post_id)) {
      // DEBUG: $this->log_debug('process_successful_operation', 'Falhou em default_validation()');
      return false;
    }

    // DEBUG: $this->log_debug('process_successful_operation', 'default_validation() passou');

    $_SESSION['sucess'] = true;

    $redirect_url = $this->check_user_address($post_id);
    $success_msg = 'Seu anúncio foi processado e está em aprovação.';

    // Edição silenciosa do ADM em anúncio já aprovado: manter no fluxo público do anúncio.
    if ($this->is_silent_admin_edit) {
      $redirect_url = get_permalink((int) $post_id);
      $success_msg = 'Alterações salvas com sucesso.';
    }

    // Definir resposta de sucesso
    $this->data_output = array(
      'submit' => true,
      'title' => 'Operação realizada com sucesso!',
      'msg' => $success_msg,
      'redirect' => $redirect_url,
    );

    $this->set_email_alert();

    // DEBUG: $this->log_debug('process_successful_operation', 'process_successful_operation() concluído com sucesso');
    return true;
  }

  /**
   * Fluxo pós-anúncio: (1) endereço mínimo, se faltar → cadastro-endereco; (2) página de sucesso
   * (anúncio atualizado) com recado de aprovação + escolha Grátis / Turbinar.
   *
   * @param int $post_id
   * @return string
   */
  protected function check_user_address($post_id)
  {
    $success_url = function_exists('bazar_get_anuncio_plano_url')
      ? bazar_get_anuncio_plano_url($post_id)
      : bazar_get_updated_url($post_id);
    $user_id = (int) get_post_field('post_author', (int) $post_id);

    if ($user_id <= 0) {
      $user_id = (int) get_current_user_id();
    }

    if ($user_id <= 0) {
      return $success_url;
    }

    if (function_exists('bazar_user_has_min_address_meta') && !bazar_user_has_min_address_meta($user_id)) {
      return add_query_arg(
        'redirect_to',
        $success_url,
        home_url('/cadastro-endereco/')
      );
    }

    return $success_url;
  }



  /*
   * SEND EMAILS
   * responsável por enviar os emails para o usuário, o administrador e a API
   * caso falhe em algum dos emails, prossiga com o processo
   * caso não seja possível enviar o email, não deve ser considerado um erro fatal
   * @param int $post_id - ID do post
   * @return void | boolean
   */
  protected function process_emails($post_id = null)
  {
    // Edição silenciosa do ADM (pós-aprovação): sem reenvio de e-mails.
    if ($this->is_silent_admin_edit) {
      return true;
    }

    // Enviar email para usuário (não é erro fatal se falhar)
    $result_user = $this->sendMailToUser($post_id);
    if ($result_user === false) {
      // DEBUG: $this->log_debug('process_emails', 'sendMailToUser() retornou false (erro fatal)');
      return false;
    }
    // DEBUG: $this->log_debug('process_emails', 'sendMailToUser() concluído');

    // Enviar email para admin (não é erro fatal se falhar - fail_on_error => false)
    $result_admin = $this->sendMailToAdmin($post_id);
    if ($result_admin === false) {
      // DEBUG: $this->log_debug('process_emails', 'sendMailToAdmin() retornou false (erro fatal)');
      return false;
    }
    // DEBUG: $this->log_debug('process_emails', 'sendMailToAdmin() concluído');

    // Enviar email para adicionar taxonomia (não é erro fatal se falhar)
    $result_tax = $this->sendMailToAddTax();
    if ($result_tax === false) {
      // DEBUG: $this->log_debug('process_emails', 'sendMailToAddTax() retornou false (erro fatal)');
      return false;
    }
    // DEBUG: $this->log_debug('process_emails', 'sendMailToAddTax() concluído');
    // DEBUG: $this->log_debug('process_emails', 'process_emails() concluído com sucesso');
    return true;
  }

  /*
   * VALIDATION SEND MAIL
   *
   * Não é um erro fatal; se falhar, sendMailToUser retorna true (não bloqueia o fluxo).
   * E-mail ao usuário na criação usa pendências de perfil de anuncio-publication-service.php.
   *
   * @return bool
   */
  protected function validation_sendMail($post_id = null)
  {

    if (!$this->default_validation($post_id, 'validation_sendMail')):
      return false;
    endif;

    // Anúncio ainda não publicado e fluxo de edição: não reenviar e-mail ao autor
    $is_post_published = (get_post_status($post_id) === 'publish');
    if ($this->is_edit && !$is_post_published):
      return false;
    endif;

    return true;
  }

  protected function sendMailToUser($post_id)
  {

    // Não é um erro fatal, apenas não envia o email para o usuário
    // Se a validação falhar (ex: edição de post não publicado), retornar true (não é erro)
    if (!$this->validation_sendMail($post_id))
      return true;

    $author_id = (int) get_post_field('post_author', $post_id);

    $user_name = isset($_POST['user_nome']) ? wp_strip_all_tags((string) $_POST['user_nome']) : '';
    if ($user_name === '') {
      $user_name = $author_id > 0 ? (string) get_the_author_meta('user_firstname', $author_id) : '';
    }

    $user_email = isset($_POST['user_email']) ? wp_strip_all_tags((string) $_POST['user_email']) : '';
    if ($user_email === '' || !is_email($user_email)) {
      $user_email = $author_id > 0 ? (string) get_the_author_meta('user_email', $author_id) : '';
    }

    if ($user_email === '' || !is_email($user_email)) {
      return true;
    }

    $email_body_user = '<p>Olá ' . esc_html($user_name) . ',</p>';
    $email_body_user .= '<p>Recebemos seu anúncio e nossa equipe está avaliando. Assim que ele for moderado você receberá as confirmações também por e-mail.</p>';

    if (get_the_title($post_id) !== '') {
      $email_body_user .= '<p style="margin-top: 14px;"><strong>Anúncio:</strong><br><span style="font-size: 14px; color: #333;">' . esc_html(get_the_title($post_id)) . '</span></p>';
    }

    $preview_url = add_query_arg(
      array(
        'p' => $post_id,
        'preview' => 'true',
      ),
      home_url('/')
    );
    $buttons = array(
      array(
        'label' => 'Pré-visualizar anúncio',
        'url' => $preview_url,
        'text' => 'Pré-visualizar anúncio',
      ),
    );

    $mail_data = array(
      'name' => $user_name,
      'to' => $user_email,
      'subject' => "Anúncio cadastrado",
      'msg_header' => "Anúncio enviado para aprovação",
      'email_body' => $email_body_user,
      'buttons' => $buttons,
      'fail_on_error' => 'alert',
    );

    $send_mail_user = new __Bazar_Send_Mail();
    $send_result = $send_mail_user->send_mail_msg($mail_data);

    // Processar retorno (adiciona alerta ao data_output se necessário e registra log de erro)
    return $this->processar_retorno_email(
      $send_result,
      $send_mail_user,
      'sendMailToUser'
    );
  }

  protected function sendMailToAdmin($post_id = null)
  {

    if (!$this->default_validation($post_id, 'sendMailToAdmin')) {
      return false;
    }

    if ($this->is_edit && !$this->send_admin_reavaliacao_email) {
      return true;
    }

    $url = get_bloginfo('url') . '/?p=' . $post_id . '&preview=true';

    $subject_ = ($this->is_edit)
      ? 'Reavaliação'
      : 'Aprovação';

    $email_title = ($this->is_edit)
      ? 'Reavalização de anúncio'
      : 'Aprovação de anúncio';

    $email_body_admin = ($this->is_edit)
      ? 'Um anúncio acabou de ser Editado e aguarda aprovação'
      : 'Um anúncio acabou de ser Publicado e aguarda aprovação';


    $email_body_admin .= (!empty(get_the_title($post_id)))
      ? '<p><label><strong><small>Anúncio:</small></strong></label><br><code>' . get_the_title($post_id) . '</code></p>'
      : '';

    $mail_data_adm = array(
      'name' => "Bazar Bikes",
      'to' => $this->admin_email,
      'subject' => $subject_,
      'msg_header' => $email_title,
      'email_body' => $email_body_admin,
      'buttons' => array(
        0 => array(
          "label" => "Aprovar Anúncio.",
          "url" => $url,
          "text" => "Aprovar Anúncio",
        ),
      ),
      'fail_on_error' => false,
    );

    $send_mail_adm = new __Bazar_Send_Mail();
    $send_result = $send_mail_adm->send_mail_msg($mail_data_adm);

    // Processar retorno (adiciona alerta ao data_output se necessário e registra log de erro)
    return $this->processar_retorno_email(
      $send_result,
      $send_mail_adm,
      'sendMailToAdmin'
    );
  }

  protected function sendMailToAddTax()
  {

    // Não é um erro fatal, caso não exista o campo, não deve ser considerado um erro fatal
    $add_new_tax = wp_strip_all_tags($_POST['marcas_modelos_child_add']);
    if (!$add_new_tax || $add_new_tax == 'false' || empty($add_new_tax))
      return true;

    // if( !$this->validation_sendMail() ) return;

    $brand = wp_strip_all_tags($_POST['marcas_modelos']);
    $child = wp_strip_all_tags($_POST['marcas_modelos_child']);

    $send_name = get_bloginfo('name');
    $send_email = $this->admin_email;
    $send_msg = 'Olá, um novo Modelo foi cadastrado:
			<br><br>' . $brand . ' > <strong style="color:#c9201a; font-size:14px;"><a title="Modelos" target="_blank" href="' . get_admin_url(null, 'edit-tags.php?taxonomy=marca-modelo') . '">' . $child . '</a></strong>';

    $mail_cat = array(
      'name' => $send_name,
      'to' => $send_email,
      'subject' => "Novas Categoria",
      'msg_header' => "Nova categoria para ser registrada na API.",
      'email_body' => $send_msg,
      'buttons' => array(
        0 => array(
          "label" => "Acessar Modelos.",
          "url" => get_admin_url(null, 'edit-tags.php?taxonomy=marca-modelo'),
          "text" => "Acessar Modelos",
        ),
      ),
      'fail_on_error' => false,
    );

    // não é um erro fatal caso não seja possível enviar o email, prossiga com o processo
    $send_mail_cat = new __Bazar_Send_Mail();
    $send_result = $send_mail_cat->send_mail_msg($mail_cat);

    // Processar retorno (adiciona alerta ao data_output se necessário e registra log de erro)
    return $this->processar_retorno_email($send_result, $send_mail_cat, 'sendMailToAddTax');
  }

  /*
   * AUXILIAR METHODS
   *
   */

  protected function default_validation($post_id = null, $key = 'default_validation')
  {

    // DEBUG: Log de debug para identificar problemas
    if (empty($post_id)) {
      // DEBUG: $this->log_debug($key, 'default_validation() falhou: post_id está vazio');
      $this->definir_erro_servidor(
        'Parâmetros vazios. Verifique os campos obrigatórios.',
        $key,
        'Parâmetros vazios. $post_id é obrigatório.'
      );
      return false;
    }

    if (!$this->operation_success) {
      // DEBUG: $this->log_debug($key, 'default_validation() falhou: operation_success = false');
      $this->definir_erro_servidor(
        'Parâmetros vazios. Verifique os campos obrigatórios.',
        $key,
        'Parâmetros vazios. $this->operation_success = true é obrigatório.'
      );
      return false;
    }

    return true;
  }


  protected function qrcode_generator($post_ID)
  {

    if (!$post_ID)
      return false;

    if (class_exists('__Bazar_QR_Code')) {
      $qr_code_generator = new __Bazar_QR_Code($post_ID);
      $result = $qr_code_generator->gerar_qrcode_post();

      if (!$result) {
        $this->log_debug(
          'qrcode_generator',
          $qr_code_generator->get_debug_log()
        );
        return false;
      }

      return $result;
    }

    $this->log_debug(
      'qrcode_error',
      'Classe __Bazar_QR_Code não encontrada'
    );
    return false;
  }

  protected function log_execution_time()
  {
    $end_time = microtime(true);
    $execution_time = ($end_time - $this->start_time);
    $this->log_debug(
      'execution_time',
      $execution_time . ' segundos'
    );
  }

}
?>