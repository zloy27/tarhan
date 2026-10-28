<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
function redirect_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
global $wpdb;
redirect_check(wp_parse_url(home_url(), PHP_URL_HOST) === 'tarhan-wp.local' && !wp_using_ext_object_cache(), 'Local only');
$user_before = get_current_user_id(); $id = 0; $cookie_user = 0;
$cookie = function ($value, $expire, $expiration, $user_id) use (&$cookie_user) { $cookie_user = $user_id; };
$http = function ($pre, $args, $url) {
    if (!str_starts_with($url, 'https://suggestions.dadata.ru/')) { return new WP_Error('blocked_test_http'); }
    return array('response' => array('code' => 200), 'body' => wp_json_encode(array('suggestions' => array(array('value' => 'Тестовый адрес', 'data' => array('house_fias_id' => '11111111-1111-1111-1111-111111111111', 'house' => '1'))))));
};
add_action('set_logged_in_cookie', $cookie, 10, 4); add_filter('pre_http_request', $http, 10, 3);
$wpdb->query('START TRANSACTION');
try {
    wp_set_current_user(0); $_SERVER['REMOTE_ADDR'] = 'redirect_' . bin2hex(random_bytes(8));
    $season = wp_generate_uuid4();
    update_option('tarhan_seasons', array('items' => array($season => array('id' => $season, 'name' => 'Тест', 'created_at' => time())), 'current_id' => $season, 'registration_open' => true, 'configured' => true, 'revision' => 1), false);
    update_option('tarhan_dadata_key', 'fixture', false);
    $p = array_fill_keys(array('first_name', 'last_name', 'middle_name', 'parent_first_name', 'parent_last_name', 'parent_middle_name'), 'Тест');
    $p += array('teacher_name' => 'Тест Тест Тест', 'teacher_phone' => '+79991234567', 'phone' => '799' . random_int(10000000,99999999), 'parent_phone' => '+79991234567', 'birth_date' => '2000-01-01', 'school_form' => 'МБОУ', 'school_type' => 'Школа', 'school_number' => '1', 'education' => 'Абитуриент', 'email' => 'redirect_' . bin2hex(random_bytes(6)) . '@example.invalid', 'password' => 'Test-fixture-only-123', 'password_confirmation' => 'Test-fixture-only-123', 'rules' => 'on', 'season_id' => $season, 'address_fias_id' => '11111111-1111-1111-1111-111111111111');
    $request = new WP_REST_Request('POST', '/tarhan/v1/register'); $request->set_body_params($p);
    $result = tarhan_register_participant($request);
    redirect_check(!is_wp_error($result), is_wp_error($result) ? $result->get_error_code() : 'Registration');
    $id = (int) email_exists($p['email']);
    redirect_check($id && get_current_user_id() === $id && (int) $cookie_user === $id, 'Auto-login missing');
    redirect_check($result['next_url'] === home_url('/test/'), 'Wrong redirect');
    redirect_check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . tarhan_attempts_table() . ' WHERE user_id=%d', $id)) === 0, 'Attempt started automatically');
    $row = tarhan_organizer_row(get_userdata($id), get_user_meta($id, 'tarhan_profile', true), null, array(), null);
    redirect_check($row['full_name'] === 'Тест Тест Тест', 'Full name not combined');
    echo "PASS: registration auto-login cookie, /test/ redirect, no attempt started, combined full name.\n";
} finally {
    $wpdb->query('ROLLBACK'); if ($id) { clean_user_cache($id); }
    wp_cache_flush(); wp_set_current_user($user_before);
    remove_action('set_logged_in_cookie', $cookie, 10); remove_filter('pre_http_request', $http, 10);
}
