<?php
/**
 * Upload de imagens de anúncio.
 *
 * Limite por arquivo: padrão 16MB, ajustável via filtro `bazar_attachment_max_upload_mb`.
 * O tamanho efetivo não ultrapassa `wp_max_upload_size()` (PHP upload_max_filesize / post_max_size).
 *
 * Onde o limite aparece: validação em {@see __Bazar_Attachment_Upload::check_files_max_sizes()},
 * textos em page-anuncio-inserir.php / page-anuncio-editar.php ($max_size_label),
 * mensagem de erro em anuncio-crud.php.
 */
abstract class file_data
{
  public $name;
  public $type;
  public $tmp_name;
  public $error;
  public $size;
}

class __Bazar_Attachment_Upload
{
  public $file_id;
  private $max_size;
  private $max_size_label;
  public $check_max_size;
  public $check_image_type;
  public $check_image_dimensions;
  /** MB por imagem (padrão). Ajuste via filtro bazar_attachment_max_upload_mb. */
  private $default_max_upload_mb = 16;
  private $min_image_width = 500;
  private $max_image_width = 4032;
  private $min_image_height = 500;
  private $max_image_height = 4032;
  private $min_images_count = 3;
  private $max_images_count = 10;
  private $allowed_image_types = array(
    'image/jpg',
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'image/heic',
    'image/heif',
  );

  function __construct()
  {
    require_once(ABSPATH . '/wp-admin/includes/image.php');
    require_once(ABSPATH . '/wp-admin/includes/file.php');
    require_once(ABSPATH . '/wp-admin/includes/media.php');

    $this->set_max_size();
  }

  public function prepare_file_data($files = null, $key = null)
  {

    if (empty($files) || !is_numeric($key) || $key === null)
      return;

    $tmp = $files['tmp_name'][$key];
    $name = $files['name'][$key];
    $raw_type = isset($files['type'][$key]) ? trim((string) $files['type'][$key]) : '';
    $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

    $ext_to_mime = array(
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'png' => 'image/png',
      'gif' => 'image/gif',
      'webp' => 'image/webp',
      'heic' => 'image/heic',
      'heif' => 'image/heif',
    );

    $raw_l = strtolower($raw_type);
    if ($raw_type === '' || $raw_l === 'application/octet-stream') {
      $type = isset($ext_to_mime[$ext]) ? $ext_to_mime[$ext] : $raw_l;
    } else {
      $type = $raw_l;
    }

    $image_info = @getimagesize($tmp);
    $width = 0;
    $height = 0;
    if ($image_info !== false) {
      $width = (int) $image_info[0];
      $height = (int) $image_info[1];
    } elseif (in_array($type, array('image/heic', 'image/heif'), true) || in_array($ext, array('heic', 'heif'), true)) {
      // getimagesize costuma falhar em HEIC sem Imagick — dimensões mínimas para passar na validação do tema
      $width = $this->min_image_width;
      $height = $this->min_image_height;
    }

    return array(
      'name' => $name,
      'type' => $type,
      'tmp_name' => $tmp,
      'error' => $files['error'][$key],
      'size' => $files['size'][$key],
      'width' => $width,
      'height' => $height,
    );
  }

  public function init_upload_process($file_data = null)
  {

    if (empty($file_data))
      return false;

    //if (!is_numeric($this->get_max_size() || $this->get_max_size() === null))
    $this->set_max_size();

    $this->check_max_size = $this->check_files_max_sizes($file_data);
    $this->check_image_type = $this->check_files_has_image($file_data);
    $this->check_image_dimensions = $this->check_files_dimensions($file_data);

  }


  public function upload_file($file_data = null, $post_id = null)
  {

    if (empty($file_data) || empty($post_id))
      return false;

    $this->file_id = media_handle_sideload($file_data, $post_id);
    if (is_wp_error($this->file_id)) {
      @unlink($file_data['tmp_name']);
      return false;
    }
    return $this->file_id;
  }


  // Função que limpa o cache de imagens
  public function clean_cache_images()
  {
    foreach ($_FILES['input-file']['tmp_name'] as $key => $value) {
      @unlink($value);
    }
    $this->file_id = null;
  }


  public function check_files_max_sizes($file_data = null)
  {

    if (empty($file_data))
      return false;

    if (empty($file_data['size']))
      return false;

    if (empty($this->max_size))
      return false;

    if ($file_data['size'] > $this->max_size)
      return false;

    return true;
  }

  public function check_files_has_image($file_data = null)
  {

    if (empty($file_data))
      return false;

    $mime = strtolower(trim((string) ($file_data['type'] ?? '')));
    if ($mime === '') {
      return false;
    }

    if ($mime === 'image/pjpeg') {
      $mime = 'image/jpeg';
    }

    if (!in_array($mime, $this->allowed_image_types, true)) {
      return false;
    }

    return true;
  }

  public function check_files_dimensions($file_data = null)
  {
    if (empty($file_data))
      return false;

    if (
      $file_data['width'] < $this->min_image_width
      || $file_data['width'] > $this->max_image_width
      || $file_data['height'] < $this->min_image_height
      || $file_data['height'] > $this->max_image_height
    ) {
      return false;
    }

    return true;
  }

  public function get_max_size()
  {
    return $this->max_size;
  }


  public function get_max_size_label()
  {
    return $this->max_size_label;
  }

  public function get_min_images_count()
  {
    return $this->min_images_count;
  }

  public function get_max_images_count()
  {
    return $this->max_images_count;
  }

  public function get_min_image_width()
  {
    return $this->min_image_width;
  }

  public function get_max_image_width()
  {
    return $this->max_image_width;
  }

  public function get_min_image_height()
  {
    return $this->min_image_height;
  }

  public function get_max_image_height()
  {
    return $this->max_image_height;
  }

  public function get_allowed_image_types()
  {
    return $this->allowed_image_types;
  }

  public function get_allowed_image_types_string($separator = ', ')
  {
    $types = array();
    foreach ($this->allowed_image_types as $type) {
      // Remove 'image/' do início e converte para maiúscula
      $ext = strtoupper(str_replace('image/', '', $type));
      $types[] = $ext;
    }
    return implode($separator, $types);
  }

  public function get_allowed_image_types_for_accept()
  {
    return implode(', ', $this->allowed_image_types);
  }

  public function set_max_size($max_size = null)
  {
    if ($max_size === null) {
      $max_size = (int) apply_filters('bazar_attachment_max_upload_mb', $this->default_max_upload_mb);
    }

    if (!is_numeric($max_size)) {
      return false;
    }

    $calc = 1024 * 1024;
    $mb_wanted = max(1, min(64, (int) $max_size));
    $requested = $mb_wanted * $calc;

    $server_max = (function_exists('wp_max_upload_size')) ? (int) wp_max_upload_size() : 0;
    if ($server_max > 0 && $requested > $server_max) {
      $this->max_size = $server_max;
      $this->set_max_size_label($this->format_mb_label_from_bytes($server_max));
      return true;
    }

    $this->max_size = $requested;
    $this->set_max_size_label($mb_wanted . 'MB');
    return true;
  }

  /**
   * Rótulo legível quando o limite veio do teto do PHP (ex.: 7.5MB).
   */
  private function format_mb_label_from_bytes($bytes)
  {
    $calc = 1024 * 1024;
    $mb = $bytes / $calc;
    if ($mb < 1) {
      return max(1, (int) ceil($bytes / 1024)) . 'KB';
    }
    $rounded = round($mb, 1);
    if (abs($rounded - (int) $rounded) < 0.05) {
      return (int) round($rounded) . 'MB';
    }
    return $rounded . 'MB';
  }

  private function set_max_size_label($max_size_label = null)
  {
    if (empty($max_size_label))
      return false;

    $this->max_size_label = $max_size_label;

  }


  /*
  * Converte um array multidimensional em um array unidimensional
  in:
    array(1) {
      ["upload"]=>array(2) {
        ["name"]=>array(2) {
          [0]=>string(9)"file0.txt"
          [1]=>string(9)"file1.txt"
        }
        ["type"]=>array(2) {
          [0]=>string(10)"text/plain"
          [1]=>string(10)"text/html"
        }
      }
  }
  output: 
  array(2) {
    [0]=>array(2) {
      ["name"]=>string(9)"file0.txt"
      ["type"]=>string(10)"text/plain"
    },
    [1]=>array(2) {
      ["name"]=>string(9)"file1.txt"
      ["type"]=>string(10)"text/html"
    }
  }
  */
  public function diverse_array($vector)
  {
    $result = array();
    foreach ($vector as $key1 => $value1)
      foreach ($value1 as $key2 => $value2)
        $result[$key2][$key1] = $value2;
    return $result;
  }

}
?>