<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
function participant_check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function participant_ok($value, $message) {
    participant_check(!is_wp_error($value), $message . (is_wp_error($value) ? ': ' . $value->get_error_code() : ''));
    return $value;
}
function participant_error($value, $code) { participant_check(is_wp_error($value) && $value->get_error_code() === $code, 'Expected ' . $code); }
global $wpdb;
participant_ok(tarhan_install_essays(), 'Essay schema');
foreach (array($wpdb->users, $wpdb->usermeta, $wpdb->options, $wpdb->prefix . 'tarhan_enrollments', $wpdb->prefix . 'tarhan_season_attempts', $wpdb->prefix . 'tarhan_season_essays') as $table) {
    $info = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
    participant_check($info && strtoupper($info->Engine) === 'INNODB', 'Nontransactional storage');
}
participant_check(!wp_using_ext_object_cache(), 'Use isolated database without external object cache');
$passed = array();
$ids = array();
$original = get_current_user_id();
$settings = get_option('tarhan_essay_settings', null);
$key_before = get_option('tarhan_dadata_key', null);
$mock = null;
$email_filter = null;
$wpdb->query('START TRANSACTION');
try {
    delete_option('tarhan_seasons');
    $_SERVER['REMOTE_ADDR'] = 'test_' . bin2hex(random_bytes(12));
    wp_set_current_user(0);
    $token = tarhan_site_token();
    $request = new WP_REST_Request('POST', '/tarhan/v1/register');
    participant_error(tarhan_public_permission($request), 'form_expired');
    $request->set_header('X-Tarhan-Token', $token);
    participant_check(tarhan_public_permission($request) === true, 'Browser token rejected');
    $request->set_header('origin', 'https://other.example');
    participant_error(tarhan_public_permission($request), 'form_origin');
    $request->set_header('origin', home_url());
    participant_check(tarhan_public_permission($request) === true, 'Same origin rejected');
    $passed[] = 'Browser-bound token and same-origin checks';

    $email = 'verify_' . bin2hex(random_bytes(6)) . '@example.invalid';
    $p = array('first_name' => 'Иван', 'last_name' => 'Иванов', 'middle_name' => 'Иванович',
        'birth_date' => '2000-01-01', 'phone' => '+7 (999) 123-45-67', 'parent_first_name' => 'Пётр',
        'parent_last_name' => 'Иванов', 'parent_middle_name' => 'Петрович', 'parent_phone' => '+79991234567', 'teacher_name' => 'Иванов Иван Иванович', 'teacher_phone' => '+79991234567',
        'school_form' => 'МБОУ', 'school_type' => 'Школа', 'school_number' => '12', 'education' => 'Абитуриент',
        'email' => $email, 'address_fias_id' => '11111111-1111-1111-1111-111111111111',
        'password' => 'Strong-Pass-123!', 'password_confirmation' => 'Strong-Pass-123!', 'rules' => 'on');
    participant_ok(tarhan_validate_registration($p), 'Valid form');
    foreach (array('birth_date' => '2026-02-30', 'email' => 'bad', 'first_name' => 'Иван1', 'phone' => '123',
        'school_number' => '12<script>', 'password_confirmation' => 'different', 'rules' => '', 'address_fias_id' => array()) as $field => $bad) {
        participant_check(is_wp_error(tarhan_validate_registration(array_merge($p, array($field => $bad)))), 'Invalid field accepted: ' . $field);
    }
    $passed[] = 'Registration names, date, email, phone, school choices, password, consent checks';

    // The provider is mocked only in this CLI test, never in the deployed plugin.
    update_option('tarhan_dadata_key', 'test-provider-key', false);
    $mock = function ($pre, $args, $url) use ($p) {
        if (!str_starts_with($url, 'https://suggestions.dadata.ru/')) { return $pre; }
        return array('response' => array('code' => 200), 'headers' => array(), 'body' => wp_json_encode(array('suggestions' => array(
            array('value' => 'Технический адрес', 'data' => array('house_fias_id' => $p['address_fias_id'], 'house' => '1', 'region_with_type' => 'Тест'))))));
    };
    add_filter('pre_http_request', $mock, 10, 3);
    $request->set_body_params($p);
    $registration = participant_ok(tarhan_register_participant($request), 'Create participant');
    $id = (int) email_exists($email);
    $ids[] = $id;
    participant_check(get_current_user_id() === $id && $registration['next_url'] === home_url('/test/'), 'Registration login and redirect');
    participant_check($id > 0 && get_user_by('id', $id)->roles === array('subscriber'), 'Wrong role');
    $profile = get_user_meta($id, 'tarhan_profile', true);
    participant_check($profile['address']['value'] === 'Технический адрес' && !isset($profile['password']), 'Profile or password storage');
    participant_check(wp_check_password($p['password'], get_userdata($id)->user_pass, $id), 'Password hash');
    wp_set_current_user(0);
    participant_error(tarhan_register_participant($request), 'registration_exists');
    $minor = array_merge($p, array('email' => 'minor_' . $email, 'birth_date' => '2010-01-01'));
    $request->set_body_params($minor);
    participant_error(tarhan_register_participant($request), 'registration_consent');
    participant_check(!email_exists($minor['email']), 'Minor account created without signed consent');
    $passed[] = 'Real account creation, subscriber role, profile, hashed password, duplicate and minor rejection (provider fixture)';

    $auth = new WP_REST_Request('POST', '/tarhan/v1/auth/login');
    $auth->set_header('Content-Type', 'application/json');
    $auth->set_body(wp_json_encode(array('email' => $email, 'password' => 'wrong')));
    participant_error(tarhan_auth_command('login', $auth), 'login_failed');
    $auth->set_body(wp_json_encode(array('email' => $email, 'password' => $p['password'])));
    participant_ok(tarhan_auth_command('login', $auth), 'Sign in');
    $mail = null;
    $email_filter = function ($pre, $atts) use (&$mail) { $mail = $atts; return true; };
    add_filter('pre_wp_mail', $email_filter, 10, 2);
    $auth->set_body(wp_json_encode(array('email' => $email)));
    $known = participant_ok(tarhan_auth_command('forgot', $auth), 'Recover');
    participant_check($mail && str_contains($mail['message'], '/reset-password/?key='), 'Reset email missing custom URL');
    preg_match('~https?://[^\s]+/reset-password/\?[^\s]+~', $mail['message'], $match);
    parse_str(parse_url($match[0], PHP_URL_QUERY), $link);
    $auth->set_body(wp_json_encode(array('email' => 'absent_' . $email)));
    participant_check($known === tarhan_auth_command('forgot', $auth), 'Account existence exposed by response');
    $reset = array_merge($link, array('password' => 'New-Strong-Pass-456!', 'password_confirmation' => 'New-Strong-Pass-456!'));
    $auth->set_body(wp_json_encode($reset));
    participant_ok(tarhan_auth_command('reset', $auth), 'Reset');
    participant_check(wp_check_password($reset['password'], get_userdata($id)->user_pass, $id), 'New password not saved');
    participant_check(count(WP_Session_Tokens::get_instance($id)->get_all()) === 0, 'Password reset did not revoke sessions');
    participant_error(tarhan_auth_command('reset', $auth), 'reset_link');
    $passed[] = 'Login and recovery, email link, uniform unknown-email response, new password and single-use key';

    wp_set_current_user($id);
    $essay_request = new WP_REST_Request('POST', '/tarhan/v1/essay/submit');
    $essay_request->set_body_params(array('text' => 'Эссе'));
    participant_error(tarhan_essay_command('submit', $essay_request), 'essay_forbidden');
    $new = array('user_id' => $id, 'attempt_number' => 1, 'request_key' => 'verify_essay_request', 'status' => 'finished',
        'started_at' => time() - 30, 'expires_at' => time() + 2400, 'finished_at' => time(), 'questions' => '[]', 'answers' => '{}', 'score' => 25, 'finish_reason' => 'submitted');
    participant_check($wpdb->insert($wpdb->prefix . 'tarhan_season_attempts', $new) === 1, 'Test result fixture');
    update_option('tarhan_essay_settings', array('open' => false, 'topic' => 'Техническая тема', 'max_length' => 10, 'max_bytes' => 1024));
    participant_error(tarhan_essay_command('submit', $essay_request), 'essay_closed');
    update_option('tarhan_essay_settings', array('open' => true, 'topic' => 'Техническая тема', 'max_length' => 10, 'max_bytes' => 1024));
    $essay_request->set_body_params(array('text' => '           '));
    participant_error(tarhan_essay_command('submit', $essay_request), 'essay_text');
    $essay_request->set_body_params(array('text' => str_repeat('я', 11)));
    participant_error(tarhan_essay_command('submit', $essay_request), 'essay_text');
    $essay_request->set_body_params(array('text' => '  Привет мир  '));
    $saved = participant_ok(tarhan_essay_command('submit', $essay_request), 'Essay submission');
    participant_check($saved['submission']['text'] === 'Привет мир' && !$saved['can_submit'], 'Text not stored or still editable');
    $docx = tempnam(sys_get_temp_dir(), 'tarhan-essay-');
    participant_check(tarhan_create_essay_docx($docx, tarhan_get_essay($id, ''), $id, ''), 'Text essay DOCX creation');
    $docx_zip = new ZipArchive();
    participant_check($docx_zip->open($docx) === true && str_contains($docx_zip->getFromName('word/document.xml'), 'Привет мир'), 'Text essay DOCX content');
    $docx_zip->close(); unlink($docx);
    $essay_request->set_body_params(array('text' => 'Замена'));
    participant_check(tarhan_essay_command('submit', $essay_request)['submission']['text'] === 'Привет мир', 'Double request overwrote essay');
    $other = wp_insert_user(array('user_login' => 'verify_other_' . bin2hex(random_bytes(5)), 'user_pass' => wp_generate_password(), 'role' => 'subscriber'));
    $ids[] = $other;
    participant_check(tarhan_store_enrollment($other, array('registered_at' => time()), null), 'Enrollment fixture');
    wp_set_current_user($other);
    participant_check(tarhan_essay_command('status', $essay_request)['submission'] === null, 'Other user can read essay');
    participant_error(tarhan_essay_command('submit', $essay_request), 'essay_forbidden');
    $passed[] = 'Essay admission, closed intake, Unicode limits, durable submission, idempotence and user isolation';
    ob_start();
    tarhan_participants_admin();
    participant_check(ob_get_clean() === '', 'Subscriber can read organiser page');
    wp_update_user(array('ID' => $other, 'role' => 'administrator'));
    wp_set_current_user(0);
    wp_set_current_user($other);
    ob_start();
    tarhan_participants_admin();
    $admin_view = ob_get_clean();
    participant_check(str_contains($admin_view, 'Привет мир') && str_contains($admin_view, 'Скачать эссе (.docx)') && str_contains($admin_view, 'Полученные работы'), 'Organiser cannot read or download submitted text');
    $passed[] = 'Organiser-only work list renders stored essay and generated Word download; subscribers denied';
    participant_error(tarhan_store_private_upload(array('error' => 0, 'name' => 'a.pdf', 'tmp_name' => __FILE__), array('pdf'), 100000), 'upload_failed');
    foreach (array(array('WORK.PDF', 10, null), array('work.pdf.exe', 10, 'bad'), array('noextension', 10, 'bad'), array('empty.pdf', 0, 'bad'), array('large.pdf', 11, 'bad')) as $case) {
        $r = tarhan_validate_essay_file_info($case[0], $case[1], array('pdf'), 10);
        participant_check(($r['error'] === null) === ($case[2] === null), 'File metadata validation');
    }
    for ($i = 0; $i < 2; $i++) participant_ok(tarhan_rate_limit('test_limited', 2), 'Rate budget');
    participant_error(tarhan_rate_limit('test_limited', 2), 'rate_limit');
    $passed[] = 'Upload origin and file metadata checks; enforced request limit';
} finally {
    if ($mock) remove_filter('pre_http_request', $mock, 10);
    if ($email_filter) remove_filter('pre_wp_mail', $email_filter, 10);
    $wpdb->query('ROLLBACK');
    wp_cache_flush();
    foreach ($ids as $id) clean_user_cache($id);
    wp_set_current_user($original);
}
foreach ($passed as $message) echo 'PASS ' . $message . "\n";
echo "Temporary database records rolled back; no real email sent.\n";
