<?php
/**
 * CLI opcional — mesma lógica que Ferramentas → Zerar UTM (utm-relatorio.php).
 *
 *   php wp-content/themes/bazar/scripts/zerar-utm-atribuicao.php --dry-run
 *   php wp-content/themes/bazar/scripts/zerar-utm-atribuicao.php --confirm
 */
if (!defined('ABSPATH')) {
  $d = __DIR__;
  for ($i = 0; $i < 8; $i++) {
    if (file_exists($d . '/wp-load.php')) {
      require_once $d . '/wp-load.php';
      break;
    }
    $p = dirname($d);
    if ($p === $d) {
      exit("wp-load.php nao encontrado.\n");
    }
    $d = $p;
  }
}

if (PHP_SAPI !== 'cli') {
  exit("So CLI.\n");
}

$argv = $_SERVER['argv'] ?? array();
$dry = in_array('--dry-run', $argv, true);
$ok = in_array('--confirm', $argv, true) || in_array('-y', $argv, true);
if (!$dry && !$ok) {
  exit("Uso: --dry-run | --confirm\n");
}

if (!function_exists('bazar_utm_zerar_contagens')) {
  exit("Tema inativo ou utm-relatorio nao carregado.\n");
}

$c = bazar_utm_zerar_contagens();
echo "usermeta: {$c['user']} | postmeta: {$c['post']}\n";
if ($dry) {
  exit(0);
}

$r = bazar_utm_zerar_executar();
echo "apagado: usermeta {$r['user']}, postmeta {$r['post']}\n";
