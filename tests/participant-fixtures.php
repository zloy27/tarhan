<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') { throw new RuntimeException('Local fixtures only'); }
$path = __DIR__ . '/../.participant-fixtures.local';
$mode = $argv[1] ?? '';
global $wpdb;
if ($mode === 'setup') {
    if (is_file($path)) { throw new RuntimeException('Clean previous fixtures first'); }
    $data = array('marker' => bin2hex(random_bytes(8)), 'users' => array(), 'settings' => get_option('tarhan_essay_settings', null));
    file_put_contents($path, wp_json_encode($data));
    for ($i = 0; $i < 4; $i++) {
        $login = 'tarhan_flow_' . $data['marker'] . '_' . $i;
        $password = wp_generate_password(30, true, false);
        $email = $login . '@example.invalid';
        $id = wp_insert_user(array('user_login' => $login, 'user_email' => $email, 'user_pass' => $password,
            'display_name' => 'Технический участник ' . ($i + 1), 'role' => 'subscriber'));
        if (is_wp_error($id)) { throw new RuntimeException('Fixture account failed'); }
        $data['users'][] = array('id' => $id, 'email' => $email, 'login' => $login, 'password' => $password);
        file_put_contents($path, wp_json_encode($data));
        if ($i !== 2) {
            $wpdb->insert($wpdb->prefix . 'tarhan_attempts', array('user_id' => $id, 'attempt_number' => 1,
                'request_key' => 'fixture_' . $data['marker'] . '_' . $i, 'status' => 'finished',
                'started_at' => time() - 120, 'expires_at' => time() + 2400, 'finished_at' => time(),
                'questions' => '[]', 'answers' => '{}', 'score' => 25, 'finish_reason' => 'submitted'));
        }
    }
    update_option('tarhan_essay_settings', array('open' => true, 'topic' => 'Техническая проверка: историческое событие', 'max_length' => 1000, 'max_bytes' => 1048576), false);
    echo "Temporary fixture accounts ready. Credentials are in ignored local manifest.\n";
} elseif ($mode === 'cleanup') {
    if (!is_file($path)) { echo "No fixtures.\n"; exit; }
    $data = json_decode(file_get_contents($path), true);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($data['users'] as $user) {
        $actual = get_userdata($user['id']);
        if (!$actual || $actual->user_login !== $user['login'] || !str_starts_with($actual->user_login, 'tarhan_flow_' . $data['marker'] . '_')) {
            throw new RuntimeException('Fixture ownership mismatch; stopped');
        }
        tarhan_delete_private_file(tarhan_essay_file($user['id']));
        tarhan_delete_private_file(get_user_meta($user['id'], 'tarhan_consent_file', true));
        $wpdb->delete($wpdb->prefix . 'tarhan_essays', array('user_id' => $user['id']));
        $wpdb->delete($wpdb->prefix . 'tarhan_attempts', array('user_id' => $user['id']));
        wp_delete_user($user['id']);
    }
    if ($data['settings'] === null) { delete_option('tarhan_essay_settings'); }
    else { update_option('tarhan_essay_settings', $data['settings'], false); }
    unlink($path);
    echo "Temporary accounts and uploaded files removed; original essay settings restored.\n";
} else { throw new RuntimeException('Use setup or cleanup'); }
