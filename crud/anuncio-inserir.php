<?php
// Ações AJAX para inserção de anúncios de bicicletas
add_action('wp_ajax_bazar_anuncio_inserir', 'bazar_anuncio_inserir');
add_action('wp_ajax_nopriv_bazar_anuncio_inserir', 'bazar_anuncio_inserir');

// Função que inicializa a classe de inserção de anúncios
function bazar_anuncio_inserir()
{
  $object = new __Bazar_Anuncio_Inserir();
  wp_die();
}

// Classe principal para inserção de anúncios de bicicletas
class __Bazar_Anuncio_Inserir extends __Bazar_Anuncio_Crude
{
  private $utm_service;

  public function __construct()
  {
    $this->label = 'anuncio_inserir';
    $this->utm_service = new __BazarUtmService();
    parent::__construct();
  }

  protected function process_form()
  {

    if (!$this->validation_form()) {
      return false;
    }

    // Título gerado no JavaScript ( evita queries no backend )
    // Fallback: gerar título no PHP se não foi enviado pelo JS
    $title = isset($_POST['title']) && !empty($_POST['title'])
      ? wp_strip_all_tags($_POST['title'])
      : $this->generate_title();

    $new_post_id = $this->insert_post_operation($title);
    if (!$new_post_id) {
      return false;
    }

    $this->salvar_utm_anuncio($new_post_id);

    // DEFAULT ACTIONS
    if (!$this->add_acf_fields($new_post_id)) {
      return false;
    }

    if (!$this->add_taxonomy_terms($new_post_id)) {
      return false;
    }

    if (!$this->add_meta_fields($new_post_id)) {
      return false;
    }

    $this->add_proximidade_data($new_post_id);

    if (!$this->upload_files($new_post_id)) {
      return false;
    }

    if (!$this->process_emails($new_post_id)) {
      return false;
    }

    $this->set_qrcode($new_post_id);

    $this->process_successful_operation($new_post_id);

    return true;
  }

  /**
   * Salva UTM da criacao do anuncio no post_meta.
   * Nao bloqueia o fluxo caso nao haja cookie.
   *
   * @param int $post_id
   * @return void
   */
  private function salvar_utm_anuncio($post_id)
  {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !$this->utm_service) {
      return;
    }
    $this->utm_service->save_ad_utm($post_id);
  }

}
?>