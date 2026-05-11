<?php
/**
 * CPT Ads (Afiliados) – Amazon, Mercado Livre, Shopee, Centauro, Decathlon
 * Campos 100% via meta (sem ACF).
 *
 * @package XXXXXX
 */

/**
 * Migração única: campos ACF antigos → meta atuais do CPT ads.
 * Mapa: meta_antigo => meta_atual. Só copia se o meta atual estiver vazio.
 * Roda uma vez no admin; depois não faz nada.
 */
function bazar_ads_migrate_acf_to_meta()
{
  if (!is_admin() || get_option('bazar_ads_acf_migrated')) {
    return;
  }
  $map = array(
    'link' => 'link_amazon', // ACF antigo "link" (Amazon) → link_amazon
    // Incluir aqui outros campos se no ACF antigo tiverem nome diferente do meta atual
  );
  $posts = get_posts(array(
    'post_type' => 'ads',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
  ));
  foreach ($posts as $post_id) {
    foreach ($map as $old_key => $new_key) {
      $new_value = get_post_meta($post_id, $new_key, true);
      if ($new_value !== '' && $new_value !== null) {
        continue;
      }
      $old_value = get_post_meta($post_id, $old_key, true);
      if ($old_value !== '' && $old_value !== null) {
        update_post_meta($post_id, $new_key, $old_value);
      }
    }
  }
  update_option('bazar_ads_acf_migrated', true);
}

add_action('init', 'cpt_ads_afiliados');
function cpt_ads_afiliados()
{
  bazar_ads_migrate_acf_to_meta();
  $labels = array(
    'name' => 'Ads (Afiliados)',
    'all_items' => 'Todos',
    'add_new_item' => 'Adicionar item',
    'edit_item' => 'Editar item',
    'new_item' => 'Novo item',
    'view_item' => 'Visualizar item',
    'search_items' => 'Buscar item',
    'not_found' => 'Nada encontrado',
    'not_found_in_trash' => 'Nada encontrado na lixeira',
  );
  $args = array(
    'labels' => $labels,
    'hierarchical' => true,
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'menu_position' => 10,
    'menu_icon' => 'dashicons-megaphone',
    'show_in_nav_menus' => true,
    'exclude_from_search' => true,
    'has_archive' => false,
    'query_var' => false,
    'can_export' => true,
    'rewrite' => false,
    'capability_type' => 'post',
    'supports' => array('title', 'revisions', 'page-attributes', 'thumbnail', 'custom-fields'),
  );
  register_post_type('ads', $args);
}

// Meta box: conteúdo do ad (marca, descrição, imagem, target)
add_action('add_meta_boxes', 'bazar_ads_conteudo_meta_box');
function bazar_ads_conteudo_meta_box()
{
  add_meta_box(
    'bazar_ads_conteudo',
    'Conteúdo do anúncio',
    'bazar_ads_conteudo_meta_box_cb',
    'ads',
    'normal',
    'high'
  );
}

function bazar_ads_conteudo_meta_box_cb($post)
{
  wp_nonce_field('bazar_ads_conteudo_save', 'bazar_ads_conteudo_nonce');
  $marca = get_post_meta($post->ID, 'marca', true);
  $descricao = get_post_meta($post->ID, 'descricao', true);
  $url_imagem = get_post_meta($post->ID, 'url_imagem', true);
  $target_blank = get_post_meta($post->ID, 'target_blank', true);
  if ($target_blank === '') {
    $target_blank = '1'; // Padrão: todos os ads abrem em nova aba
  }
  ?>
  <p>
    <label for="bazar_ads_marca"><strong>Marca</strong></label><br>
    <input type="text" id="bazar_ads_marca" name="marca" value="<?php echo esc_attr($marca); ?>" class="regular-text"
      placeholder="Ex: Caloi">
  </p>
  <p>
    <label for="bazar_ads_descricao"><strong>Descrição</strong></label><br>
    <textarea id="bazar_ads_descricao" name="descricao" rows="3"
      class="large-text"><?php echo esc_textarea($descricao); ?></textarea>
  </p>
  <p>
    <label for="bazar_ads_url_imagem"><strong>URL da imagem</strong> <span class="description">(opcional – use a imagem em
        destaque se deixar em branco)</span></label><br>
    <input type="url" id="bazar_ads_url_imagem" name="url_imagem" value="<?php echo esc_url($url_imagem); ?>"
      class="large-text" placeholder="https://...">
  </p>
  <p>
    <label>
      <input type="checkbox" name="target_blank" value="1" <?php checked($target_blank, '1'); ?>>
      Abrir link em nova aba
    </label>
  </p>
  <?php
}

add_action('save_post_ads', 'bazar_ads_conteudo_save');
function bazar_ads_conteudo_save($post_id)
{
  if (!isset($_POST['bazar_ads_conteudo_nonce']) || !wp_verify_nonce($_POST['bazar_ads_conteudo_nonce'], 'bazar_ads_conteudo_save')) {
    return;
  }
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }
  if (!current_user_can('edit_post', $post_id)) {
    return;
  }
  if (isset($_POST['marca'])) {
    update_post_meta($post_id, 'marca', sanitize_text_field($_POST['marca']));
  }
  if (isset($_POST['descricao'])) {
    update_post_meta($post_id, 'descricao', sanitize_textarea_field($_POST['descricao']));
  }
  if (isset($_POST['url_imagem'])) {
    update_post_meta($post_id, 'url_imagem', esc_url_raw($_POST['url_imagem']));
  }
  update_post_meta($post_id, 'target_blank', !empty($_POST['target_blank']) ? '1' : '0');
}

// Meta box: ofertas por marketplace (links + descontos)
add_action('add_meta_boxes', 'bazar_ads_marketplace_meta_box');
function bazar_ads_marketplace_meta_box()
{
  add_meta_box(
    'bazar_ads_marketplace',
    'Ofertas por marketplace',
    'bazar_ads_marketplace_meta_box_cb',
    'ads',
    'normal'
  );
}

function bazar_ads_marketplace_meta_box_cb($post)
{
  wp_nonce_field('bazar_ads_marketplace_save', 'bazar_ads_marketplace_nonce');
  $link_amazon = get_post_meta($post->ID, 'link_amazon', true);
  $desconto_amazon = get_post_meta($post->ID, 'desconto_amazon', true);
  $link_ml = get_post_meta($post->ID, 'link_ml', true);
  $desconto_ml = get_post_meta($post->ID, 'desconto_ml', true);
  $link_shopee = get_post_meta($post->ID, 'link_shopee', true);
  $desconto_shopee = get_post_meta($post->ID, 'desconto_shopee', true);
  $link_centauro = get_post_meta($post->ID, 'link_centauro', true);
  $desconto_centauro = get_post_meta($post->ID, 'desconto_centauro', true);
  $link_decathlon = get_post_meta($post->ID, 'link_decathlon', true);
  $desconto_decathlon = get_post_meta($post->ID, 'desconto_decathlon', true);
  ?>
  <p><strong>Amazon</strong></p>
  <p>
    <label>Link Amazon</label><br>
    <input type="url" name="link_amazon" value="<?php echo esc_url($link_amazon); ?>" class="large-text"
      placeholder="https://...">
  </p>
  <p>
    <label>Desconto Amazon (ex: 15% ou R$ 20 off)</label><br>
    <input type="text" name="desconto_amazon" value="<?php echo esc_attr($desconto_amazon); ?>" class="regular-text"
      placeholder="15%">
  </p>
  <hr>
  <p><strong>Mercado Livre</strong></p>
  <p>
    <label>Link ML</label><br>
    <input type="url" name="link_ml" value="<?php echo esc_url($link_ml); ?>" class="large-text"
      placeholder="https://...">
  </p>
  <p>
    <label>Desconto ML (ex: 10%)</label><br>
    <input type="text" name="desconto_ml" value="<?php echo esc_attr($desconto_ml); ?>" class="regular-text"
      placeholder="10%">
  </p>
  <hr>
  <p><strong>Shopee</strong></p>
  <p>
    <label>Link Shopee</label><br>
    <input type="url" name="link_shopee" value="<?php echo esc_url($link_shopee); ?>" class="large-text"
      placeholder="https://...">
  </p>
  <p>
    <label>Desconto Shopee (ex: 20%)</label><br>
    <input type="text" name="desconto_shopee" value="<?php echo esc_attr($desconto_shopee); ?>" class="regular-text"
      placeholder="20%">
  </p>
  <hr>
  <p><strong>Centauro</strong></p>
  <p>
    <label>Link Centauro</label><br>
    <input type="url" name="link_centauro" value="<?php echo esc_url($link_centauro); ?>" class="large-text"
      placeholder="https://...">
  </p>
  <p>
    <label>Desconto Centauro (ex: 10%)</label><br>
    <input type="text" name="desconto_centauro" value="<?php echo esc_attr($desconto_centauro); ?>" class="regular-text"
      placeholder="10%">
  </p>
  <hr>
  <p><strong>Decathlon</strong></p>
  <p>
    <label>Link Decathlon</label><br>
    <input type="url" name="link_decathlon" value="<?php echo esc_url($link_decathlon); ?>" class="large-text"
      placeholder="https://...">
  </p>
  <p>
    <label>Desconto Decathlon (ex: 15%)</label><br>
    <input type="text" name="desconto_decathlon" value="<?php echo esc_attr($desconto_decathlon); ?>" class="regular-text"
      placeholder="15%">
  </p>
  <?php
}

add_action('save_post_ads', 'bazar_ads_marketplace_save');
function bazar_ads_marketplace_save($post_id)
{
  if (!isset($_POST['bazar_ads_marketplace_nonce']) || !wp_verify_nonce($_POST['bazar_ads_marketplace_nonce'], 'bazar_ads_marketplace_save')) {
    return;
  }
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }
  if (!current_user_can('edit_post', $post_id)) {
    return;
  }
  $url_keys = array('link_amazon', 'link_ml', 'link_shopee', 'link_centauro', 'link_decathlon');
  $text_keys = array('desconto_amazon', 'desconto_ml', 'desconto_shopee', 'desconto_centauro', 'desconto_decathlon');
  foreach ($url_keys as $key) {
    if (isset($_POST[$key])) {
      update_post_meta($post_id, $key, esc_url_raw($_POST[$key]));
    }
  }
  foreach ($text_keys as $key) {
    if (isset($_POST[$key])) {
      update_post_meta($post_id, $key, sanitize_text_field($_POST[$key]));
    }
  }
}

// Colunas de cliques na listagem
add_filter('manage_ads_posts_columns', 'bazar_add_count_column_ADS', 10, 1);
function bazar_add_count_column_ADS($columns)
{
  $columns['count_click'] = 'Clcks Amz';
  $columns['count_click_ml'] = 'Clcks ML';
  $columns['count_click_shopee'] = 'Clcks Shopee';
  $columns['count_click_centauro'] = 'Clcks Centauro';
  $columns['count_click_decathlon'] = 'Clcks Decathlon';
  return $columns;
}

add_action('manage_ads_posts_custom_column', 'bazar_show_count_column_ADS', 10, 2);
function bazar_show_count_column_ADS($column_name, $post_id)
{
  $meta_map = array(
    'count_click' => '_count_click_amazon',
    'count_click_ml' => '_count_click_ml',
    'count_click_shopee' => '_count_click_shopee',
    'count_click_centauro' => '_count_click_centauro',
    'count_click_decathlon' => '_count_click_decathlon',
  );
  if (isset($meta_map[$column_name])) {
    $count = get_post_meta($post_id, $meta_map[$column_name], true);
    echo empty($count) ? '0' : (int) $count;
  }
}

add_action('admin_head-edit.php', 'estilos_coluna_visualizacoes_ADS');
function estilos_coluna_visualizacoes_ADS()
{
  $screen = get_current_screen();
  if (!$screen || $screen->post_type !== 'ads') {
    return;
  }
  echo '<style>
		.column-count_click, .column-count_click_ml, .column-count_click_shopee, .column-count_click_centauro, .column-count_click_decathlon { width: 70px; text-align: center; }
	</style>';
}

add_filter('manage_edit-ads_sortable_columns', 'bazar_sortable_count_column_ADS');
function bazar_sortable_count_column_ADS($columns)
{
  $columns['count_click'] = 'count_click';
  $columns['count_click_ml'] = 'count_click_ml';
  $columns['count_click_shopee'] = 'count_click_shopee';
  $columns['count_click_centauro'] = 'count_click_centauro';
  $columns['count_click_decathlon'] = 'count_click_decathlon';
  return $columns;
}