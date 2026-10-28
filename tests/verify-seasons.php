<?php
$complete = false;
register_shutdown_function(function () use (&$complete) { if (!$complete) { exit(1); } });
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
function season_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function season_error($result, $code) { season_check(is_wp_error($result) && $result->get_error_code() === $code, 'Expected error: ' . $code); }
global $wpdb;
season_check(wp_parse_url(home_url(), PHP_URL_HOST) === 'tarhan-wp.local' && !wp_using_ext_object_cache(), 'Local site required');
foreach (array($wpdb->options, $wpdb->users, $wpdb->usermeta, $wpdb->prefix . 'tarhan_attempts', $wpdb->prefix . 'tarhan_essays') as $table) {
    $info = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
    season_check($info && strtoupper($info->Engine) === 'INNODB', 'Transactional tables required');
}
$admin = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
season_check((bool) $admin, 'Administrator required');
$original_user = get_current_user_id(); $original_post = $_POST; $original_method = $_SERVER['REQUEST_METHOD'] ?? '';
$original_ip = $_SERVER['REMOTE_ADDR'] ?? ''; $ids = array(); $mock = null;
$initial = tarhan_seasons_state();
$buffer_level = ob_get_level();
$wpdb->query('START TRANSACTION');
try {
    delete_option('tarhan_seasons');
    season_check(tarhan_registration_season() === null, 'Existing registration must work before explicit setup');
    wp_set_current_user((int) $admin[0]);
    $save = static function ($fields) { return tarhan_seasons_save(array_merge(array('_wpnonce' => wp_create_nonce('tarhan_seasons_save'), 'revision' => (string) tarhan_seasons_state()['revision']), $fields)); };
    season_error($save(array('operation' => 'create', 'name' => '')), 'season_name');
    season_check($save(array('operation' => 'create', 'name' => '2026–2027')) === true, 'Create first season');
    $first = array_key_first(tarhan_seasons_state()['items']);
    season_check(tarhan_registration_season() === null, 'Creating a season must not change active registration');
    season_error($save(array('operation' => 'create', 'name' => '2026 - 2027')), 'season_duplicate');
    season_error($save(array('operation' => 'settings', 'current_id' => '', 'registration_open' => '1')), 'season_required');
    season_error($save(array('operation' => 'settings', 'current_id' => 'unknown')), 'season_unknown');
    season_error($save(array('operation' => 'settings', 'current_id' => $first, 'revision' => '0')), 'season_stale');
    season_error($save(array('operation' => 'settings', 'current_id' => $first, '_wpnonce' => 'wrong')), 'season_nonce');
    season_check($save(array('operation' => 'settings', 'current_id' => $first)) === true, 'Select closed season');
    wp_set_current_user(0);
    season_error(tarhan_registration_season(), 'registration_closed');
    $request = new WP_REST_Request('POST', '/tarhan/v1/register');
    season_error(tarhan_register_participant($request), 'registration_closed');
    season_error(tarhan_seasons_save(array()), 'season_access');
    wp_set_current_user((int) $admin[0]);
    season_check($save(array('operation' => 'settings', 'current_id' => $first, 'registration_open' => '1')) === true, 'Open season');
    season_check(tarhan_registration_season()['id'] === $first, 'Wrong active season');
    echo "PASS: Season creation, duplicate validation, explicit selection, closed admission, nonce, access and stale form protection\n";

    $email = 'season_fixture_' . bin2hex(random_bytes(8)) . '@example.invalid';
    $p = array('first_name' => 'Иван', 'last_name' => 'Иванов', 'middle_name' => 'Иванович', 'birth_date' => '2000-01-01', 'phone' => '+79991234567',
        'parent_first_name' => 'Пётр', 'parent_last_name' => 'Иванов', 'parent_middle_name' => 'Петрович', 'parent_phone' => '+79991234567', 'teacher_name' => 'Иванов Иван Иванович', 'teacher_phone' => '+79991234567', 'school_form' => 'МБОУ', 'school_type' => 'Школа', 'school_number' => '12', 'education' => 'Абитуриент',
        'email' => $email, 'address_fias_id' => '11111111-1111-1111-1111-111111111111', 'password' => 'Temporary-fixture-123!', 'password_confirmation' => 'Temporary-fixture-123!', 'rules' => 'on', 'season_id' => $first);
    update_option('tarhan_dadata_key', 'test-only-provider', false);
    $mock = static function ($pre, $args, $url) use ($p) {
        if (!str_starts_with($url, 'https://suggestions.dadata.ru/')) { return $pre; }
        return array('response' => array('code' => 200), 'headers' => array(), 'body' => wp_json_encode(array('suggestions' => array(array('value' => 'Тестовый адрес', 'data' => array('house_fias_id' => $p['address_fias_id'], 'house' => '1'))))));
    };
    add_filter('pre_http_request', $mock, 10, 3);
    wp_set_current_user(0); $_SERVER['REMOTE_ADDR'] = 'season-test-' . bin2hex(random_bytes(8));
    $request->set_body_params(array_merge($p, array('season_id' => 'wrong')));
    season_error(tarhan_register_participant($request), 'season_changed');
    $request->set_body_params($p);
    $result = tarhan_register_participant($request);
    season_check(!is_wp_error($result), 'Registration failed: ' . (is_wp_error($result) ? $result->get_error_code() : ''));
    $id = (int) email_exists($email); $ids[] = $id;
    $profile = get_user_meta($id, 'tarhan_profile', true);
    season_check($profile['season_id'] === $first && $profile['season_name'] === '2026–2027', 'New profile did not retain season');
    wp_set_current_user($id);
    season_error(tarhan_seasons_save(array()), 'season_access');
    wp_set_current_user((int) $admin[0]);
    season_check($save(array('operation' => 'create', 'name' => '2027–2028')) === true, 'Create next season');
    $next = array_key_last(tarhan_seasons_state()['items']);
    season_check(tarhan_registration_season()['id'] === $first, 'Creation unexpectedly switched season');
    season_check($save(array('operation' => 'settings', 'current_id' => $next, 'registration_open' => '1')) === true, 'Switch season');
    season_check(tarhan_registration_season()['id'] === $next && get_user_meta($id, 'tarhan_profile', true) === $profile, 'Switch changed existing profile');
    wp_set_current_user(0);
    season_error(tarhan_register_participant($request), 'season_changed');
    $request->set_body_params(array_merge($p, array('season_id' => $next)));
    season_error(tarhan_register_participant($request), 'registration_exists');
    wp_set_current_user((int) $admin[0]);
    $registration_season = tarhan_registration_season();
    ob_start(); include get_theme_file_path('/participant-forms/registration.php'); $html = ob_get_clean();
    season_check(str_contains($html, 'name="season_id" value="' . $next . '"') && str_contains($html, '2027–2028'), 'Season missing in public form');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start(); tarhan_seasons_admin(); $html = ob_get_clean();
    season_check(str_contains($html, '2026–2027') && str_contains($html, '2027–2028') && substr_count($html, '<td>Текущий</td>') === 1, 'Admin list or current season incorrect');
    season_check($save(array('operation' => 'settings', 'current_id' => '')) === true, 'Close all registration');
    season_error(tarhan_registration_season(), 'registration_closed');
    echo "PASS: Registration stores season, next season does not overwrite profile, old forms rejected, duplicate account blocked, form and admin rendering\n";
} finally {
    while (ob_get_level() > $buffer_level) { ob_end_clean(); }
    $wpdb->query('ROLLBACK');
    if ($mock) { remove_filter('pre_http_request', $mock, 10); }
    wp_cache_flush();
    foreach ($ids as $id) { clean_user_cache($id); }
    wp_set_current_user($original_user); $_POST = $original_post; $_SERVER['REQUEST_METHOD'] = $original_method; $_SERVER['REMOTE_ADDR'] = $original_ip;
}
season_check(tarhan_seasons_state() === $initial, 'Season settings not restored');
foreach ($ids as $id) { season_check(!$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID = %d", $id)), 'Test user remains'); }
echo "PASS: Test accounts and all season settings rolled back\n";
$complete = true;
