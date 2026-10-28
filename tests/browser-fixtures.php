<?php
// Local-only, disposable browser QA data. Never install this file in WordPress.
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
$manifest = __DIR__ . '/.browser-fixtures.local';
if (($argv[1] ?? '') === 'cleanup') {
    $fixture = json_decode(file_get_contents($manifest), true);
    $user = get_userdata($fixture['user_id']);
    if (!$user || $user->user_login !== $fixture['login'] || strpos($user->user_login, 'tarhan_browser_') !== 0) {
        throw new RuntimeException('Fixture ownership mismatch');
    }
    global $wpdb;
    $wpdb->delete(tarhan_attempts_table(), array('user_id' => $fixture['user_id']));
    foreach ($fixture['questions'] as $id) {
        if (get_post_meta($id, '_tarhan_browser_fixture', true) !== $fixture['login']) {
            throw new RuntimeException('Question ownership mismatch');
        }
        wp_delete_post($id, true);
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($fixture['user_id']);
    update_option('tarhan_testing_open', $fixture['initial_open']);
    unlink($manifest);
    echo "Browser fixtures removed; original opening setting restored.\n";
    exit;
}
if (file_exists($manifest)) { throw new RuntimeException('Clean up previous fixtures first'); }
$suffix = bin2hex(random_bytes(5));
$fixture = array('login' => 'tarhan_browser_' . $suffix, 'password' => wp_generate_password(24, false),
    'initial_open' => get_option('tarhan_testing_open', '0'), 'questions' => array());
$fixture['user_id'] = wp_insert_user(array('user_login' => $fixture['login'], 'user_pass' => $fixture['password'],
    'user_email' => $suffix . '@example.invalid', 'role' => 'subscriber'));
if (is_wp_error($fixture['user_id'])) { throw new RuntimeException('Could not create fixture user'); }
file_put_contents($manifest, wp_json_encode($fixture));
$_POST = array();
for ($i = 1; $i <= 55; $i++) {
    $id = wp_insert_post(array('post_type' => 'tarhan_question', 'post_status' => 'draft',
        'post_title' => 'Временная проверка интерфейса ' . $i), true);
    if (is_wp_error($id)) { throw new RuntimeException('Could not create fixture question'); }
    update_post_meta($id, '_tarhan_browser_fixture', $fixture['login']);
    $fixture['questions'][] = $id;
    file_put_contents($manifest, wp_json_encode($fixture));
    update_post_meta($id, '_tarhan_question_valid', 1);
    update_post_meta($id, '_tarhan_question_data', array('text' => 'Техническая проверка №' . $i . ': выберите второй вариант.',
        'options' => array('Первый вариант', 'Второй вариант', 'Третий вариант'), 'correct' => array(2)));
    global $wpdb;
    $wpdb->update($wpdb->posts, array('post_status' => 'publish'), array('ID' => $id));
    clean_post_cache($id);
}
update_option('tarhan_testing_open', '1');
echo wp_json_encode(array('login' => $fixture['login'], 'password' => $fixture['password'], 'user_id' => $fixture['user_id'])), "\n";
