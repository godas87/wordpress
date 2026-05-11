<?php
if (!defined('ABSPATH')) {
  exit;
}
function bazar_is_production()
{

  // return true;
  static $is_production = null;

  if ($is_production !== null) {
    return $is_production;
  }

  $is_production = (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'www.XXXXXX' || $_SERVER['SERVER_NAME'] == 'XXXXXX');
  return $is_production;
}
?>