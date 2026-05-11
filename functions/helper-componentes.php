<?php
/**
 * Helper centralizado para componentes
 * Gerencia cache, busca e ordenação de componentes
 * 
 * @package XXXXXX
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('bazar_get_component_title_ids')) {
  /**
   * IDs dos componentes relevantes para geração de títulos
   * 
   * Este arquivo centraliza os IDs das taxonomias de componentes que são usados
   * na geração de títulos de anúncios. Altere os valores aqui para atualizar
   * em todo o sistema.
   * 
   * @return array Array associativo com os IDs dos componentes
   */
  function bazar_get_component_title_ids()
  {
    return array(
      'aro' => 13376,
      'quadro' => 13389,
      'cambio_dianteiro' => 13398,
      'cambio_traseiro' => 13402,
    );
  }
}

if (!function_exists('bazar_get_all_components')) {
  /**
   * Busca TODOS os componentes (pais e filhos) com cache
   * Wrapper que acessa a classe __Bazar_Component_Helper (fonte de verdade)
   * 
   * @return array Array de objetos WP_Term ordenados hierarquicamente
   */
  function bazar_get_all_components()
  {
    if (!class_exists('__Bazar_Component_Helper')) {
      return array();
    }
    return __Bazar_Component_Helper::get_all_components();
  }
}

if (!function_exists('bazar_get_componentes_parents')) {
  /**
   * Busca apenas os componentes PAIS (não filhos) com cache
   * Wrapper que acessa a classe __Bazar_Component_Helper (fonte de verdade)
   * Reutiliza o array em memória sem fazer nova query
   * 
   * @return array Array de objetos WP_Term (apenas pais) ordenados
   */
  function bazar_get_componentes_parents()
  {
    if (!class_exists('__Bazar_Component_Helper')) {
      return array();
    }
    return __Bazar_Component_Helper::get_parent_components();
  }
}

if (!function_exists('bazar_get_componentes_default')) {
  /**
   * Helper para buscar componentes OBRIGATÓRIOS (default_bicicletas = true) com cache
   * Wrapper que acessa a classe __Bazar_Component_Helper (fonte de verdade)
   * Reutiliza o array em memória sem fazer nova query
   * 
   * @return array Array de objetos WP_Term ordenados (apenas obrigatórios)
   */
  function bazar_get_componentes_default()
  {
    if (!class_exists('__Bazar_Component_Helper')) {
      return array();
    }
    return __Bazar_Component_Helper::get_default_components();
  }
}

if (!function_exists('bazar_clear_componentes_cache')) {
  /**
   * Limpa TODOS os caches de componentes
   * Wrapper que acessa a classe __Bazar_Component_Helper (fonte de verdade)
   * 
   * @return bool True se os caches foram limpos com sucesso
   */
  function bazar_clear_componentes_cache()
  {
    if (!class_exists('__Bazar_Component_Helper')) {
      return false;
    }
    return __Bazar_Component_Helper::clear_cache();
  }
}

// Hooks para limpar cache automaticamente quando termos de componente forem editados
add_action('edited_componente', 'bazar_clear_componentes_cache');
add_action('created_componente', 'bazar_clear_componentes_cache');
add_action('delete_componente', 'bazar_clear_componentes_cache');
?>