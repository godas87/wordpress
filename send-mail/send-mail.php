<?php
/* send mail */
/* $values = array(
// 	'name' => "Bazar Bikes",
// 	'from' => "XXXXXX",
// 	'to' => $this->admin_email,
// 	'subject' => "Aprovação",
// 	'msg_header' => "Solicitação para aprovação de anúncio.",
// 	'email_body' => $email_body_admin,
//  'buttons' => array(
// 			0 => array(
// 					"label" => "Acessar o seu anúncio.",
// 					"url" => get_bloginfo('url') . '/?p='.$post_id,					
// 					"text" => "Ver meu anúncio",
// 			),
// 		),
//	);
*/
class __Bazar_Send_Mail
{

  private $logo = 'https://XXXXXX/src/imgs/bazar-bikes-logomarca.png';
  private $title = "XXXXXX";
  private $from = "XXXXXX";
  private $url_privacy_policy = "https://XXXXXX/politica-de-privacidade/";
  private $url_terms_of_service = "https://XXXXXX/termos-de-uso/";
  private $headers;
  private $wp_error;
  private $table_master;
  private $table_wrap;
  private $table_clean;
  private $td_style;
  private $td_small_style;
  private $email_error_info = null; // Armazena informações do erro para retornar ao chamador
  private $smtp_config = array(
    'localhost' => array(
      'host' => 'XXXXXX',
      'port' => 465,
      'username' => 'XXXXXX',
      'password' => 'XXXXXX',
      'secure' => 'ssl',
    ),
    'brevo_prod' => array(
      'host' => 'smtp-relay.brevo.com',
      'port' => 587,
      'username' => 'XXXXXX',
      'password' => 'XXXXXX',
      'secure' => 'tls',
    ),
    'brevo_dev' => array(
      'host' => 'smtp-relay.brevo.com',
      'port' => 587,
      'username' => 'XXXXXX',
      'password' => 'XXXXXX',
      'secure' => 'tls',
    ),
    'brevo_dev_2' => array(
      'host' => 'smtp-relay.brevo.com',
      'port' => 587,
      'username' => 'XXXXXX',
      'password' => 'XXXXXX',
      'secure' => 'tls',
    ),
  );

  private $values = array(
    'name' => "XXXXXX",
    'to' => "",
    'subject' => "Emails administrativos",
    'msg_header' => "Email enviado automaticamente pelo sistema",
    'email_body' => "",
    "box_footer" => "",
    'attachment' => "",
    "buttons" => array(),
  );

  public function __construct()
  {
    $this->create_html_default();
  }

  public function test_layout($values = array())
  {
    // Mesclar valores passados com os valores padrão
    return $this->create_content_mail($values);
  }

  /*
   * Envia e-mail
   * @param array $values Array de valores para o e-mail
   * @return bool|array Retorna true se sucesso, false se erro fatal ou array se sucesso com alerta
   */
  public function send_mail_msg($values = array())
  {

    // Bloqueia envio de email em ambiente local
    // if ($this->is_local_environment()) {
    //   return true;
    // }

    // Extrair fail_on_error dos valores (se fornecido)
    // Default: true (erro que bloqueia) para manter compatibilidade
    $fail_on_error = isset($values['fail_on_error']) ? $values['fail_on_error'] : true;

    // Remover fail_on_error dos values para não passar para validate_data
    unset($values['fail_on_error']);

    if (!$this->validate_data($values)) {
      return false;
    }

    if (!$this->create_headers()) {
      return false;
    }

    $email_html_content = $this->create_content_mail($this->values);

    $result = $this->send_mail($email_html_content, $fail_on_error);
    if (!$result) {
      return $this->handle_email_error($fail_on_error);
    }

    return true;

  }

  private function send_mail($email_html_content = '', $fail_on_error = true)
  {

    if (empty($email_html_content)) {
      $this->log_wp_error('ERRO: Conteúdo do email HTML não foi gerado.');
      return false;
    }

    // Configurar PHPMailer antes de enviar
    $this->configure_phpmailer_init();

    $return = false;

    // Configurar PHPMailer diretamente através do hook global
    // Prioridade 9999 para garantir que nossa configuração seja aplicada após outros plugins
    add_action('phpmailer_init', array($this, 'setup_phpmailer_smtp'), 9999);

    if (
      isset($this->values['attachment'])
      && $this->values['attachment'] != ''
    ) {
      $return = wp_mail(
        $this->values['to'],
        '[Bazar Bikes] ' . $this->values['subject'],
        $email_html_content,
        $this->headers,
        $this->values['attachment']
      );

    } else {

      $return = wp_mail(
        $this->values['to'],
        '[Bazar Bikes] ' . $this->values['subject'],
        $email_html_content,
        $this->headers
      );
    }

    // Remover o hook após o envio para evitar conflitos
    remove_action('phpmailer_init', array($this, 'setup_phpmailer_smtp'), 999);

    if (!$return) {
      $this->send_mail_error();
      return false;
    }

    return true;
  }

  private function is_local_environment()
  {
    return !bazar_is_production();
  }

  private function select_smtp_config()
  {

    return ($this->is_local_environment())
      ? $this->smtp_config['brevo_dev']
      : $this->smtp_config['brevo_prod'];

  }

  /**
   * Configura PHPMailer para usar SMTP
   * Método público para ser usado como callback do hook
   */
  public function setup_phpmailer_smtp($phpmailer)
  {

    // Forçar nossa configuração SMTP, sobrescrevendo qualquer configuração de outros plugins
    $this->smtp_config = $this->select_smtp_config();

    $phpmailer->isSMTP();
    $phpmailer->Host = $this->smtp_config['host'];
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = $this->smtp_config['username'];
    $phpmailer->Password = $this->smtp_config['password'];
    $phpmailer->SMTPSecure = $this->smtp_config['secure']; // 'tls' para porta 587
    $phpmailer->Port = $this->smtp_config['port'];

    // Configurações de timeout e conexão
    $phpmailer->Timeout = 30; // Timeout de 30 segundos
    $phpmailer->SMTPKeepAlive = false;

    // Configurações de SSL/TLS
    // Para evitar erro "Path cannot be empty", não especificamos cafile
    // O PHP usará os certificados do sistema automaticamente se disponíveis
    // Em ambiente local/desenvolvimento, desabilitamos verificação rigorosa
    $phpmailer->SMTPOptions = array(
      'ssl' => array(
        'verify_peer' => false, // Desabilitar verificação rigorosa para evitar erro de caminho vazio
        'verify_peer_name' => false, // Desabilitar verificação de nome do peer
        'allow_self_signed' => true, // Permitir certificados auto-assinados
        // Não especificar 'cafile' para evitar erro "Path cannot be empty"
      )
    );

    // Configurações de encoding
    $phpmailer->CharSet = 'UTF-8';
    $phpmailer->Encoding = 'base64';

    $phpmailer->SMTPDebug = 0; // Desabilitar debug em produção
    $phpmailer->Debugoutput = function ($str, $level) {
      error_log("[PHPMailer Debug] " . $str);
    };
  }

  private function configure_phpmailer_init()
  {
    // Remover hooks anteriores para evitar conflitos
    // Usar prioridade alta para garantir que nossa configuração seja aplicada
    remove_all_actions('phpmailer_init');
    // O hook será adicionado em send_mail() para garantir timing correto
  }

  private function validate_data($values = array())
  {

    if (
      empty($values['to'])
      || empty($values['subject'])
      || empty($values['email_body'])
    ) {
      $this->log_wp_error("Dados incompletos para envio de email: ");
      $this->log_wp_error("values: " . print_r($values, true));
      return false;
    }

    $this->values = array_merge($this->values, $values);
    return true;
  }

  private function create_headers($values = null)
  {

    if (empty($this->title) || empty($this->from)) {
      $this->log_wp_error('ERRO: Título ou remetente de email não informado');
      return false;
    }

    if ($values && !is_null($values['from']) && !empty($values['from'])) {
      $this->from = sanitize_email($values['from']);
    }

    $this->headers = "From: " . $this->title . "<" . $this->from . "> \r\n";
    $this->headers .= "MIME-Version: 1.0\r\n";
    $this->headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    //$this->headers .= "Reply-To: ".$values['to']."\r\n";			
    return true;
  }

  private function create_css_style()
  {
    // CSS mínimo para Outlook desktop - remover propriedades não suportadas
    // Gmail ignora CSS no <head>, então background precisa estar no HTML
    return '
		<!--[if mso]>
		<style type="text/css">
		body, table, td {font-family: Arial, sans-serif !important;}
		body { background-color: #f9f9f9 !important; }
		</style>
		<![endif]-->
		<style type="text/css">
		body{ background-color: #f9f9f9 !important; margin: 0; padding: 0; }
		.titulo-email{ font-size: 20px; margin: 0; padding: 0; font-weight: bold; }
		/* Gmail ignora CSS do head, então background precisa estar inline no HTML */
		.email-wrapper { 
			background-color: #f9f9f9; 
			padding: 16px; 
			margin: 0; 
			width: 100%; 
			max-width: 100%; 
			box-sizing: border-box; 
		}
		@media only screen and (max-width: 600px) {
			.email-wrapper { 
				padding: 16px 8px !important; 
				margin: 0 !important; 
			}
			.email-container { 
				width: 100% !important; 
				max-width: 100% !important; 
			}
		}
		</style>
		';
  }

  private function create_html_default()
  {

    // Outlook desktop compatible - usar width fixo ao invés de max-width
    // Remover overflow, height, margin auto, e usar align="center" na tabela
    // Mobile: usar max-width 100% para responsividade, remover margin vertical
    $this->table_master = 'border="0" cellspacing="0" cellpadding="0" bgcolor="#f9f9f9" width="540" align="center" style="width: 100%; max-width: 540px; background-color: #f9f9f9; margin: 0 auto;"';

    $this->table_clean = 'border="0" cellspacing="0" cellpadding="0" bgcolor="#f9f9f9" width="540" align="center" style="width: 100%; max-width: 540px; background-color: #f9f9f9;"';

    // Outlook desktop: usar width fixo, remover overflow e box-shadow/border-radius
    // Mobile: usar max-width 100% para responsividade
    $this->table_wrap = 'border="0" cellspacing="0" cellpadding="0" bgcolor="#FFFFFF" width="540" align="center" style="width: 100%; max-width: 540px; background-color: #FFFFFF; margin-bottom: 16px;"';

    // Usar padding em px ao invés de rem para melhor compatibilidade
    $this->td_style = 'style="padding: 20px 24px 32px 24px; font-size: 14px; line-height: 1.6; color: #333333;"';

    $this->td_small_style = 'style="padding: 16px 24px; font-size: 12px; line-height: 1.6; color: #333333;"';

  }

  private function generate_buttons($buttons = array())
  {

    if (empty($buttons))
      return '';

    $buttons_html = ''; // Inicializar variável

    foreach ($buttons as $button) {
      $buttons_html .= '<p style="margin-top: .75rem; margin-bottom: 0; display: block; width: 100%; position: relative;"><a href="' . $button['url'] . '" style="display:block; width:100%; max-width: 100%; padding: 7px 0; background-color:#333; color:#FFF; border-radius:3px; text-decoration:none; text-align:center; margin-bottom:1.5rem;" title="' . $button['label'] . '" target="_blank">' . $button['text'] . '</a></p>';
    }

    return $buttons_html;

  }

  /**
   * Gera seção destacada para email (compatível com Outlook/Gmail)
   * Layout limpo e profissional
   *
   * @param array $section {
   *   @type string       $title              Título (texto puro; escapado salvo se title_html)
   *   @type string       $title_html         Se preenchido, usado no lugar do título escapado (HTML confiável só do tema)
   *   @type string       $title_image_url    URL de imagem ao lado do título (ex.: ícone de sucesso)
   *   @type string       $content            Corpo em texto ou HTML limitado
   *   @type bool         $content_allow_html true = $content com tags permitidas (strong, em, a)
   *   @type array        $product_card       Opcional: image_url, title, price (ex.: card estilo modal impulsionar)
   *   @type string       $button_url
   *   @type string       $button_text
   *   @type string       $highlight_color
   *   @type string       $bg_color
   *   @type string       $border_color
   *   @type string       $promo_notice       Aviso extra (caixa verde)
   * }
   * @return string HTML da seção
   */
  private function generate_highlighted_section($section = array())
  {
    if (empty($section) || empty($section['title'])) {
      return '';
    }

    $title = (string) $section['title'];
    $title_html_custom = isset($section['title_html']) ? (string) $section['title_html'] : '';
    $title_image_url = isset($section['title_image_url']) ? esc_url($section['title_image_url']) : '';
    $content = $section['content'] ?? '';
    $content_allow_html = !empty($section['content_allow_html']);
    $button_url = isset($section['button_url']) ? esc_url($section['button_url']) : '';
    $button_text = isset($section['button_text']) ? esc_html($section['button_text']) : '';
    $highlight_color = $section['highlight_color'] ?? '#769F51';
    $bg_color = $section['bg_color'] ?? '#f8f9fa';
    $border_color = $section['border_color'] ?? '#e0e0e0';
    $promo_notice = $section['promo_notice'] ?? '';

    $button_bg = $highlight_color;
    $button_text_color = '#FFFFFF';

    $title_inner = $title_html_custom !== ''
      ? $title_html_custom
      : esc_html($title);

    if ($title_image_url !== '') {
      $title_block = '<table border="0" cellspacing="0" cellpadding="0" width="100%" role="presentation" style="margin: 0 0 18px 0;">
				<tr>
					<td valign="middle" style="width: 24px; padding-right: 14px;">
						<img src="' . $title_image_url . '" alt="" width="24" height="24" style="display: block; width: 24px; height: 24px; border: 0;">
					</td>
					<td valign="middle" style="padding: 0;">
						<h3 style="margin: 0; padding: 0; font-size: 19px; font-weight: 600; color: #202124; line-height: 1.3;">
							' . $title_inner . '
						</h3>
					</td>
				</tr>
			</table>';
    } else {
      $title_block = '<h3 style="margin: 0 0 14px 0; padding: 0; font-size: 19px; font-weight: 600; color: #202124; line-height: 1.3;">
					' . $title_inner . '
				</h3>';
    }

    $content_html = '';
    if ($content !== '' && $content !== null) {
      $content_safe = $content_allow_html
        ? wp_kses(
          (string) $content,
          array(
            'strong' => array(),
            'em' => array(),
            'b' => array(),
            'i' => array(),
            'a' => array(
              'href' => array(),
              'title' => array(),
              'target' => array(),
            ),
            'br' => array(),
          )
        )
        : esc_html((string) $content);
      $content_html = '<p style="margin: 0 0 20px 0; padding: 0; font-size: 14px; line-height: 1.65; color: #5f6368;">' . $content_safe . '</p>';
    }

    $product_card_html = '';
    if (!empty($section['product_card']) && is_array($section['product_card'])) {
      $pc = $section['product_card'];
      $pimg = !empty($pc['image_url']) ? esc_url($pc['image_url']) : '';
      $ptitle = !empty($pc['title']) ? esc_html((string) $pc['title']) : '';
      $pprice = !empty($pc['price']) ? esc_html((string) $pc['price']) : '';

      if ($ptitle !== '' || $pprice !== '' || $pimg !== '') {
        $thumb_cell = '';
        if ($pimg !== '') {
          $thumb_cell = '<td valign="top" style="width: 92px; padding-right: 16px;">
						<img src="' . $pimg . '" alt="" width="80" height="80" style="display: block; width: 80px; height: 80px; border-radius: 6px; border: 0; object-fit: cover;">
					</td>';
        }
        $product_card_html = '<table border="0" cellspacing="0" cellpadding="0" width="100%" role="presentation" style="margin: 0 0 22px 0; background-color: #ffffff; border: 1px solid ' . esc_attr($border_color) . '; border-radius: 8px;">
					<tr>
						<td style="padding: 16px 18px;">
							<table border="0" cellspacing="0" cellpadding="0" width="100%" role="presentation">
								<tr>
									' . $thumb_cell . '
									<td valign="middle" style="font-size: 14px; line-height: 1.45; color: #202124;">
										' . ($ptitle !== '' ? '<p style="margin: 0 0 8px 0; padding: 0; font-weight: 500; font-size: 14px; color: #202124;">' . $ptitle . '</p>' : '') . '
										' . ($pprice !== '' ? '<p style="margin: 0; padding: 0; font-size: 12px; font-weight: 700; color: #4a4a4a; letter-spacing: 0;">' . $pprice . '</p>' : '') . '
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>';
      }
    }

    $promo_block = '';
    if ($promo_notice !== '') {
      $promo_block = '<table border="0" cellspacing="0" cellpadding="0" width="100%" role="presentation" style="margin-bottom: 22px;">
					<tr>
						<td style="padding: 14px 18px; background-color: #e8f5e9; border-left: 3px solid #769F51; border-radius: 3px;">
							<p style="margin: 0; padding: 0; font-size: 13px; line-height: 1.6; color: #2e7d32;">
								' . $promo_notice . '
							</p>
						</td>
					</tr>
				</table>';
    }

    $button_block = '';
    if ($button_url !== '' && $button_text !== '') {
      $button_compact = !empty($section['button_compact']);
      if ($button_compact) {
        $button_block = '<table border="0" cellspacing="0" cellpadding="0" width="100%" role="presentation">
					<tr>
						<td align="center" style="padding: 0;">
							<a href="' . $button_url . '" style="display: inline-block; width: 100%; max-width: 100%; padding: 8px 22px; background-color: ' . esc_attr($button_bg) . '; color: ' . esc_attr($button_text_color) . '; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: 600; text-align: center; line-height: 1.35; box-sizing: border-box; letter-spacing: 0.02em;" target="_blank" rel="noopener noreferrer">' . $button_text . '</a>
						</td>
					</tr>
				</table>';
      } else {
        $button_block = '<table border="0" cellspacing="0" cellpadding="0" width="100%" role="presentation">
					<tr>
						<td style="padding: 0;">
							<a href="' . $button_url . '" style="display: block; width: 100%; padding: 15px 24px; background-color: ' . esc_attr($button_bg) . '; color: ' . esc_attr($button_text_color) . '; text-decoration: none; border-radius: 5px; font-size: 15px; font-weight: 600; text-align: center; line-height: 1.4; box-sizing: border-box; letter-spacing: 0.3px;" target="_blank" rel="noopener noreferrer">' . $button_text . '</a>
						</td>
					</tr>
				</table>';
      }
    }

    $section_html = '<table ' . $this->table_wrap . ' role="presentation" style="margin-bottom: 24px;">
			<tr>
				<td style="padding: 0;">
					<table border="0" cellspacing="0" cellpadding="0" width="100%" role="presentation" style="background-color: ' . esc_attr($bg_color) . '; border: 1px solid ' . esc_attr($border_color) . '; border-radius: 8px;">
						<tr>
							<td style="padding: 26px 22px;">
								' . $title_block . '
								' . $content_html . '
								' . $product_card_html . '
								' . $promo_block . '
								' . $button_block . '
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>';

    return $section_html;
  }

  private function create_content_mail($values = array())
  {

    if (!$values)
      return false;

    // Verificar se o HTML default foi criado
    $this->validate_html_default();

    $css = $this->create_css_style();

    $buttons_html = (isset($values['buttons']) && !empty($values['buttons']))
      ? $this->generate_buttons($values['buttons'])
      : '';

    // Seções destacadas (para Impulsionamento e Promoção)
    $highlighted_sections = '';
    if (isset($values['highlighted_sections']) && !empty($values['highlighted_sections'])) {
      foreach ($values['highlighted_sections'] as $section) {
        $highlighted_sections .= $this->generate_highlighted_section($section);
      }
    }

    $sections_before_buttons = !empty($values['highlighted_sections_before_buttons']);
    $blocks_after_body = $sections_before_buttons
      ? ($highlighted_sections . $buttons_html)
      : ($buttons_html . $highlighted_sections);

    // Gmail: precisa de wrapper com background-color inline
    // Outlook desktop: remover div wrapper, usar tabela diretamente
    // Adicionar condicionais MSO para Outlook
    // Mobile: usar padding ao invés de margin para evitar sobreposição
    $openTable = '<div class="email-wrapper" style="background-color: #f9f9f9; padding: 16px; margin: 0; width: 100%; max-width: 100%; box-sizing: border-box;">
		<!--[if mso]>
		<table role="presentation" border="0" cellspacing="0" cellpadding="0" width="100%" bgcolor="#f9f9f9">
		<tr>
		<td style="padding: 16px;">
		<![endif]-->
		<table class="email-container" ' . $this->table_master . ' role="presentation"><tr><td>';

    $closeTable = '</td></tr></table>
		<!--[if mso]>
		</td></tr></table>
		<![endif]-->
		</div>';

    // Outlook desktop: usar width e height em atributos HTML, não CSS
    $logoMail = '<table ' . $this->table_clean . ' role="presentation">
			<tr>
				<td style="padding-bottom: 16px;">
					<img src="' . $this->logo . '" alt="' . $this->title . '" width="200" height="38" style="display: block; width: 200px; height: 38px;">
				</td>
			</tr>
		</table>';

    // Outlook desktop: estilos inline ao invés de classes
    $headerTitleMail = '<table ' . $this->table_wrap . ' role="presentation">
			<tr>
				<td style="font-size: 18px; font-weight: bold; margin: 0; padding: 12px 24px;">
					' . $values['msg_header'] . '
				</td>
			</tr>
		</table>';


    // Outlook desktop: usar <br> ao invés de <br/>, e garantir estilos inline
    $bodyMail = '<table ' . $this->table_wrap . ' role="presentation">
			<tr>
				<td ' . $this->td_style . '>
					<p>' . $values['email_body'] . '</p>
					' . $blocks_after_body . '
					<p style="margin:0; padding: 0;">
						Bons negócios, <br>
						equipe <b>' . $this->title . '</b>
					</p>
				</td>
			</tr>
		</table>';

    $boxFooter = (!empty($values['box_footer'])) ? '<table ' . $this->table_wrap . ' role="presentation">
		<tr><td ' . $this->td_small_style . '>' . $values['box_footer'] . '</td></tr></table>' : '';

    $footerMail = $this->create_footer_mail();

    return $css
      . $openTable
      . $logoMail
      . $headerTitleMail
      . $bodyMail
      . $boxFooter
      . $footerMail
      . $closeTable;
  }

  private function create_footer_mail()
  {
    // Outlook desktop: usar font-size em px, adicionar estilos inline para links
    return '
		<br><table border="0" cellspacing="0" cellpadding="0" bgcolor="#f9f9f9" width="540" align="center" style="width: 100%; max-width: 540px; background-color: #f9f9f9;margint-top:24px" role="presentation">
			<tr>
				<td>
					<p style="color: #646464; font-size: 10px; margin: 0 0 8px 0; padding: 0;">
						<b>ATENÇÃO:</b> Este e-mail é automático e <b>não deve ser respondido</b>
					</p>
					<p style="color: #646464; font-size: 10px; margin: 0; padding: 0;">
						. O ' . $this->title . ' somente envia e-mails de forma sistemática quando há necessidade de comunicar assunto de interesse dos inscrito.<br>
						. Todas informações recolhidas serão usadas exclusivamente para possibilitar melhor atendimento.<br>
						. Caso não tenha preenchido nenhum formulário em nosso site, favor desconsiderar este e-mail.<br>
						. Para mais informações, consulte nossa <a href="' . $this->url_privacy_policy . '" target="_blank" style="color: #646464; text-decoration: underline;" title="Política de Privacidade">Política de Privacidade</a> e <a href="' . $this->url_terms_of_service . '" target="_blank" style="color: #646464; text-decoration: underline;" title="Termos de Uso">Termos de Uso</a>.
					</p>
				</td>
			</tr>
		</table>';
  }


  private function validate_html_default()
  {

    if (empty($this->table_wrap) || empty($this->table_clean) || empty($this->td_style) || empty($this->td_small_style)) {
      $this->create_html_default();
    }

  }

  private function send_mail_error()
  {

    $this->create_log_configuration();
    // Obter erro do wp_mail usando a variável global $phpmailer
    global $phpmailer;
    $error_message = 'Erro desconhecido no envio de email';

    if (
      isset($phpmailer)
      && is_object($phpmailer)
    ) {
      if (isset($phpmailer->ErrorInfo) && !empty($phpmailer->ErrorInfo)) {
        $error_message = $phpmailer->ErrorInfo;
      }

      // Log informações adicionais do PHPMailer
      $this->log_wp_error("PHPMailer ErrorInfo: " . (isset($phpmailer->ErrorInfo) ? $phpmailer->ErrorInfo : 'N/A'));
      $this->log_wp_error("PHPMailer Host: " . (isset($phpmailer->Host) ? $phpmailer->Host : 'N/A'));
      $this->log_wp_error("PHPMailer Port: " . (isset($phpmailer->Port) ? $phpmailer->Port : 'N/A'));
      $this->log_wp_error("PHPMailer SMTPAuth: " . (isset($phpmailer->SMTPAuth) ? ($phpmailer->SMTPAuth ? 'true' : 'false') : 'N/A'));
      $this->log_wp_error("PHPMailer Username: " . (isset($phpmailer->Username) ? $phpmailer->Username : 'N/A'));
    } else {
      $this->log_wp_error("PHPMailer global não está disponível ou não é um objeto");
    }

    $this->log_wp_error("Erro do wp_mail: " . $error_message);
    return false;

  }

  private function create_log_configuration()
  {
    $msgs = $this->get_error_messages();
    $err_detail = (is_array($msgs) && $msgs !== array())
      ? implode(' | ', array_map('strval', $msgs))
      : '(sem mensagens no WP_Error)';
    $this->log_wp_error('Erro no envio de email: ' . $err_detail);
    $this->log_wp_error("Configurações PHP:");
    $this->log_wp_error("- SMTP: " . ini_get('SMTP'));
    $this->log_wp_error("- smtp_port: " . ini_get('smtp_port'));
    $this->log_wp_error("- sendmail_from: " . ini_get('sendmail_from'));
    $this->log_wp_error("- sendmail_path: " . ini_get('sendmail_path'));
  }

  /**
   * Método para registrar logs usando o sistema nativo do WordPress
   */
  private function log_wp_error($message = '')
  {

    if (empty($message)) {
      $message = 'class __Bazar_Send_Mail() -> Mensagem de erro não informada';
    }

    $this->initialize_wp_error();

    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[Bazar Email] [{$timestamp}] {$message}";
    // Usar error_log() do WordPress que vai para wp-content/debug.log
    error_log($log_message);
    // Adicionar ao WP_Error para debug
    if ($this->wp_error) {
      $this->wp_error->add('debug_log', $log_message);
    }
  }

  private function initialize_wp_error()
  {
    if (!$this->wp_error) {
      $this->wp_error = new WP_Error();
    }
    return true;
  }

  /**
   * Obter erros do WP_Error
   */
  public function get_errors()
  {
    return $this->wp_error;
  }

  /**
   * Verificar se há erros
   */
  public function has_errors()
  {
    return $this->wp_error && $this->wp_error->has_errors();
  }

  /**
   * Obter mensagens de erro como array
   */
  public function get_error_messages()
  {
    if (!$this->wp_error) {
      return array();
    }
    return $this->wp_error->get_error_messages();
  }

  /**
   * Obter logs de debug do WordPress
   */
  public function get_wp_debug_log()
  {
    // Usar função do WordPress para obter o diretório de conteúdo
    $debug_log_file = wp_upload_dir()['basedir'] . '/../debug.log';
    if (file_exists($debug_log_file)) {
      $content = file_get_contents($debug_log_file);
      // Filtrar apenas logs relacionados ao Bazar Email
      $lines = explode("\n", $content);
      $bazar_logs = array_filter($lines, function ($line) {
        return strpos($line, '[Bazar Email]') !== false;
      });
      return implode("\n", $bazar_logs);
    }
    return "Arquivo de debug.log não encontrado";
  }

  /**
   * Obter logs de debug como array para exibição no console
   */
  public function get_debug_log_array()
  {
    $debug_log_file = wp_upload_dir()['basedir'] . '/../debug.log';
    if (file_exists($debug_log_file)) {
      $content = file_get_contents($debug_log_file);
      $lines = explode("\n", $content);
      $bazar_logs = array_filter($lines, function ($line) {
        return strpos($line, '[Bazar Email]') !== false;
      });
      return array_values($bazar_logs);
    }
    return array("Arquivo de debug.log não encontrado");
  }

  /**
   * Trata erro de email conforme o tipo especificado em fail_on_error
   * @param mixed $fail_on_error - false/'silent', 'alert'/'warning', true/'error'
   * @return mixed - true (silent), array (alert), false (error)
   */
  private function handle_email_error($fail_on_error)
  {

    // Sempre logar o erro (já foi logado em send_mail_error)

    // Normalizar valor
    if ($fail_on_error === false || $fail_on_error === 'silent') {
      $fail_type = 'silent';
    } elseif ($fail_on_error === 'alert' || $fail_on_error === 'warning') {
      $fail_type = 'alert';
    } else {
      $fail_type = 'error'; // true, 'error', ou qualquer outro valor
    }

    // Armazenar informações do erro para retorno
    $this->email_error_info = array(
      'type' => $fail_type,
      'error_messages' => $this->get_error_messages(),
      'subject' => isset($this->values['subject']) ? $this->values['subject'] : '',
      'to' => isset($this->values['to']) ? $this->values['to'] : '',
    );

    switch ($fail_type) {
      case 'silent':
        // Retorna true (sucesso silencioso)
        return true;

      case 'alert':
        // Retorna array especial que indica sucesso mas com alerta
        return array(
          'success' => true,
          'alert' => true,
          'alert_type' => 'info',
          'alert_title' => 'Atenção',
          'alert_msg' => 'O cadastro foi realizado com sucesso, porém não conseguimos enviar o email de confirmação.',
          'error_info' => $this->email_error_info
        );

      case 'error':
      default:
        // Retorna false (erro que bloqueia)
        return false;
    }
  }

  /**
   * Retorna informações do último erro de email (se houver)
   * Útil para classes que precisam tratar alertas
   * @return array|null
   */
  public function get_email_error_info()
  {
    return $this->email_error_info;
  }

}
;