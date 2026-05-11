<?php
/**
 * Normalização: um único telefone (fone) + whatsapp_ativo
 * Regra: se fone != whatsapp, prevalece whatsapp (número de WhatsApp vira o fone)
 *
 * Acesso: Ferramentas → Normalizar Fone/WhatsApp
 *
 * @package XXXXXX
 */

if (!defined('ABSPATH')) {
    exit;
}

function bazar_normalize_fone_whatsapp_digits($valor) {
    if ($valor === '' || $valor === null) {
        return '';
    }
    return preg_replace('/\D/', '', trim($valor));
}

/**
 * Normaliza fone e whatsapp_ativo de um usuário (whatsapp prevalece se diferente)
 *
 * @param int $user_id
 * @return array ['updated' => bool, 'fone_antes', 'fone_depois', 'whatsapp_ativo']
 */
function bazar_normalize_user_fone_whatsapp($user_id) {
    $fone = get_user_meta($user_id, 'fone', true);
    $whatsapp = get_user_meta($user_id, 'whatsapp', true);
    $whatsapp = is_array($whatsapp) && isset($whatsapp[0]) ? $whatsapp[0] : $whatsapp;
    $fone = $fone !== null && $fone !== false ? (string) $fone : '';
    $whatsapp = $whatsapp !== null && $whatsapp !== false ? (string) $whatsapp : '';

    $fone_digits = bazar_normalize_fone_whatsapp_digits($fone);
    $whatsapp_digits = bazar_normalize_fone_whatsapp_digits($whatsapp);

    $fone_final = $fone;
    $whatsapp_ativo = false;

    if ($whatsapp_digits !== '') {
        if ($fone_digits === '' || $fone_digits !== $whatsapp_digits) {
            $fone_final = $whatsapp;
            $whatsapp_ativo = true;
        } else {
            $whatsapp_ativo = true;
        }
    } elseif ($fone_digits !== '') {
        $whatsapp_ativo = false;
    }

    $updated = false;
    if ($fone_final !== $fone) {
        update_user_meta($user_id, 'fone', $fone_final);
        $updated = true;
    }
    update_user_meta($user_id, 'whatsapp_ativo', $whatsapp_ativo ? 'true' : 'false');
    if (get_user_meta($user_id, 'whatsapp_ativo', true) !== ($whatsapp_ativo ? 'true' : 'false')) {
        $updated = true;
    }

    return array(
        'updated' => $updated,
        'fone_antes' => $fone,
        'fone_depois' => $fone_final,
        'whatsapp_ativo' => $whatsapp_ativo,
    );
}

/**
 * Normaliza todos os usuários e retorna estatísticas
 *
 * @return array
 */
function bazar_normalize_all_users_fone_whatsapp() {
    $users = get_users(array('fields' => 'ID'));
    $stats = array(
        'total' => count($users),
        'updated' => 0,
        'details' => array(),
    );
    foreach ($users as $user_id) {
        $r = bazar_normalize_user_fone_whatsapp($user_id);
        if ($r['updated']) {
            $stats['updated']++;
            $stats['details'][] = array(
                'user_id' => $user_id,
                'fone_antes' => $r['fone_antes'],
                'fone_depois' => $r['fone_depois'],
                'whatsapp_ativo' => $r['whatsapp_ativo'],
            );
        }
    }
    return $stats;
}

add_action('admin_menu', 'bazar_add_normalize_fone_whatsapp_menu');
function bazar_add_normalize_fone_whatsapp_menu() {
    add_management_page(
        'Normalizar Fone / WhatsApp',
        'Normalizar Fone/WhatsApp',
        'manage_options',
        'bazar-normalize-fone-whatsapp',
        'bazar_normalize_fone_whatsapp_page'
    );
}

function bazar_normalize_fone_whatsapp_page() {
    $already_normalized = get_option('bazar_fone_whatsapp_normalized');
    $last_stats = get_option('bazar_fone_whatsapp_normalized_stats', array());

    if (isset($_POST['bazar_execute_normalize_fone_whatsapp']) && check_admin_referer('bazar_normalize_fone_whatsapp_action')) {
        $force = isset($_POST['force']) && $_POST['force'] === '1';
        if ($force) {
            delete_option('bazar_fone_whatsapp_normalized');
        }
        $stats = bazar_normalize_all_users_fone_whatsapp();
        update_option('bazar_fone_whatsapp_normalized', true);
        update_option('bazar_fone_whatsapp_normalized_stats', $stats);
        echo '<div class="notice notice-success is-dismissible"><p><strong>Normalização concluída.</strong> ' . esc_html($stats['total']) . ' usuários processados, ' . esc_html($stats['updated']) . ' atualizados.</p></div>';
        $last_stats = $stats;
        $already_normalized = true;
    }
    ?>
    <div class="wrap">
        <h1>Normalizar Fone / WhatsApp</h1>
        <div class="card">
            <h2>O que este script faz?</h2>
            <p><strong>Regra:</strong> Um único telefone (<code>fone</code>) + indicador <code>whatsapp_ativo</code>. Se <code>fone</code> ≠ <code>whatsapp</code>, prevalece o número de WhatsApp (vira o <code>fone</code>).</p>
            <p>Este script unifica os metas antigos (fone + whatsapp) no novo modelo: um número em <code>fone</code> e <code>whatsapp_ativo</code> = true/false.</p>
            <h3>Como funciona:</h3>
            <ol>
                <li>Percorre todos os usuários</li>
                <li>Se há <code>whatsapp</code> e (fone vazio ou fone ≠ whatsapp): define <code>fone</code> = whatsapp e <code>whatsapp_ativo</code> = true</li>
                <li>Se fone e whatsapp iguais: <code>whatsapp_ativo</code> = true</li>
                <li>Se só tem fone: <code>whatsapp_ativo</code> = false</li>
                <li>Se só tem whatsapp: copia para <code>fone</code> e <code>whatsapp_ativo</code> = true</li>
            </ol>
            <?php if ($already_normalized && $last_stats) : ?>
                <div class="notice notice-info">
                    <p><strong>Status:</strong> A normalização já foi executada.</p>
                    <p><strong>Última execução:</strong> <?php echo (int) $last_stats['total']; ?> usuários processados, <?php echo (int) $last_stats['updated']; ?> atualizados.</p>
                    <p>Marque "Forçar execução" abaixo para rodar novamente.</p>
                </div>
            <?php endif; ?>
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('bazar_normalize_fone_whatsapp_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="force" value="1" <?php checked($already_normalized); ?>>
                        Forçar execução (executar mesmo se já foi normalizado)
                    </label>
                </p>
                <p>
                    <button type="submit" name="bazar_execute_normalize_fone_whatsapp" class="button button-primary button-large">Executar normalização</button>
                </p>
            </form>
            <hr>
            <p><strong>Recomendação:</strong> Faça backup do banco antes de executar.</p>
        </div>
    </div>
    <?php
}
