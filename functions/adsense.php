<?php
/**
 * Helpers para AdSense: script uma vez por página; slots por posição com random para evitar repetição.
 * Cada unidade (<ins>) deve ter seu próprio (adsbygoogle = ...).push({}); após o ins.
 *
 * Para variar os anúncios: crie várias unidades no painel do AdSense (formato responsivo e/ou 728x90)
 * e coloque os IDs nos arrays abaixo. Em cada posição da página será sorteado um slot da lista.
 */
if (!defined('ABSPATH')) {
  exit;
}
const BAZAR_ADSENSE_CLIENT_ID = 'ca-pub-9613173072002426';
const BAZAR_ADSENSE_SLOTS_TOP = ['3716585990', '3788762537', '8709916729'];
const BAZAR_ADSENSE_SLOTS_FOOTER = ['6168994791', '8916490770', '2635462963'];
const BAZAR_ADSENSE_SLOTS_SIDEBAR = ['4234603199', '2542654117', '4770671713'];

/**
 * Retorna um slot ainda não usado nesta página (evita repetição).
 * Quando todos os slots da posição já foram usados, permite repetir (fallback).
 *
 * @param array $slots Lista de slot IDs da posição
 * @return string Slot ID
 */
function bazar_adsense_pick_unused_slot($slots)
{
  static $used = array();
  $slots = array_values($slots);
  $available = array_values(array_diff($slots, $used));
  if (empty($available)) {
    $available = $slots;
  }
  $slot = $available[array_rand($available)];
  $used[] = $slot;
  return $slot;
}

/**
 * Retorna um slot para a posição "top" (não repete na mesma página).
 * @return string
 */
function bazar_adsense_top()
{
  return bazar_adsense_pick_unused_slot(BAZAR_ADSENSE_SLOTS_TOP);
}

/**
 * Retorna um slot para a posição "footer" (não repete na mesma página).
 * @return string
 */
function bazar_adsense_footer()
{
  return bazar_adsense_pick_unused_slot(BAZAR_ADSENSE_SLOTS_FOOTER);
}

/**
 * Retorna um slot para a posição "sidebar" (não repete na mesma página).
 * @return string
 */
function bazar_adsense_sidebar()
{
  return bazar_adsense_pick_unused_slot(BAZAR_ADSENSE_SLOTS_SIDEBAR);
}

/**
 * Imprime o script da biblioteca AdSense (adsbygoogle.js) apenas uma vez por request.
 */
function bazar_adsense_script_once()
{
  static $loaded = false;
  if ($loaded) {
    return;
  }
  $loaded = true;
  $client = BAZAR_ADSENSE_CLIENT_ID;
  echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . esc_attr($client) . '" crossorigin="anonymous"></script>' . "\n";
}
