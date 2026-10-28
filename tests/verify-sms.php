<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
function sms_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
sms_check(wp_parse_url(home_url(), PHP_URL_HOST) === 'tarhan-wp.local' && !wp_using_ext_object_cache(), 'Local transactional site only');
sms_check(tarhan_sms_install(), 'SMS schema');
global $wpdb;
$table = tarhan_sms_table();
foreach (array($table, tarhan_attempts_table(), $wpdb->prefix . 'tarhan_enrollments') as $name) {
    $info = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s', $name));
    sms_check($info && strtoupper($info->Engine) === 'INNODB', 'Transactional storage required');
}
$mode = 'accepted'; $requests = 0;
$transport = function ($pre, $args, $url) use (&$mode, &$requests) {
    // Intercept ALL HTTP: no real SMS, charges or outside transmission in this test.
    sms_check($url === 'https://sms.ru/sms/send', 'Unexpected outbound request blocked');
    $requests++;
    sms_check($args['body']['api_id'] === 'test-only', 'Wrong test credentials');
    if ($mode === 'timeout') { return new WP_Error('timeout', 'Synthetic timeout'); }
    if ($mode === 'rejected') { return array('response' => array('code' => 200), 'body' => '{"status":"ERROR","status_code":201}'); }
    return array('response' => array('code' => 200), 'body' => wp_json_encode(array('status' => 'OK', 'sms' => array($args['body']['to'] => array('status' => 'OK', 'sms_id' => 'fixture-id')))));
};
$config = function () { return array('enabled' => true, 'provider' => 'smsru', 'key' => 'test-only'); };
$disabled = function () { return array(); };
add_filter('pre_http_request', $transport, 10, 3);
$wpdb->query('START TRANSACTION');
try {
    // Hide pre-existing pending messages only inside this rolled-back transaction.
    $wpdb->query("UPDATE $table SET status='expired' WHERE status IN ('pending','sending')");
    $uid = 2147482999; $season = 'sms-test-' . bin2hex(random_bytes(4));
    $profile = array('phone' => '+79991234567', 'registered_at' => time(), 'season_id' => $season);
    sms_check(tarhan_store_enrollment($uid, $profile, null), 'Enrollment fixture');
    tarhan_sms_registration($uid, $profile); tarhan_sms_registration($uid, $profile);
    sms_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d", $uid)) === 1, 'Duplicate registration message');
    $questions = array(); $answers = array();
    for ($i = 1; $i <= 50; $i++) { $questions[] = array('id' => $i, 'text' => 'Тест', 'options' => array('Да', 'Нет'), 'correct' => array(0)); $answers[$i] = 0; }
    foreach (array(1 => false, 2 => true, 3 => false) as $number => $passed) {
        $row = array('user_id' => $uid, 'season_id' => $season, 'attempt_number' => $number, 'request_key' => 'sms-' . $number, 'status' => 'in_progress', 'started_at' => time()-10, 'expires_at' => time()+100,
            'questions' => wp_json_encode($questions), 'answers' => wp_json_encode($passed ? $answers : array()));
        sms_check($wpdb->insert(tarhan_attempts_table(), $row) === 1, 'Attempt fixture');
        $row['id'] = $wpdb->insert_id;
        $finished = tarhan_finish_attempt((object) $row, time(), $number === 3 ? 'timeout' : 'submitted');
        tarhan_finish_attempt($finished, time(), 'submitted');
        $sms = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE event_key=%s", 'attempt:' . $row['id']));
        sms_check($sms && $sms->phone === '79991234567', 'Finished attempt notification missing');
        sms_check(str_contains($sms->message, $passed ? 'напишите эссе' : ($number === 3 ? 'попыток в этом сезоне нет' : 'Осталось попыток: 2')), 'Incorrect result message');
        sms_check(!str_contains($sms->message, '50'), 'Private score exposed');
    }
    sms_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d", $uid)) === 4, 'Duplicate finished message');
    add_filter('pre_option_tarhan_sms_config', $disabled);
    tarhan_sms_process(); sms_check($requests === 0, 'Sent while disabled');
    remove_filter('pre_option_tarhan_sms_config', $disabled);
    add_filter('pre_option_tarhan_sms_config', $config);
    tarhan_sms_process(); tarhan_sms_process();
    sms_check($requests === 4, 'Duplicate transport send');
    sms_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d AND status='accepted'", $uid)) === 4, 'Provider acceptance not recorded');
    foreach (array('rejected' => 'failed', 'timeout' => 'uncertain') as $mode => $expected) {
        tarhan_sms_enqueue('test:' . $mode, $uid, $profile['phone'], 'Тест');
        tarhan_sms_process(); $before = $requests; tarhan_sms_process();
        sms_check($before === $requests, 'Unsafe automatic retry');
        sms_check($wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE event_key=%s", 'test:' . $mode)) === $expected, 'Wrong error handling');
    }
    tarhan_sms_enqueue('test:expired', $uid, $profile['phone'], 'Тест');
    $wpdb->update($table, array('created_at' => time() - 2*DAY_IN_SECONDS), array('event_key' => 'test:expired'));
    $before = $requests; tarhan_sms_process(); sms_check($requests === $before, 'Stale message sent');
    echo "PASS: registration, failed/passed/last attempt, timeout completion, deduplication, disabled transport, provider acceptance/errors, no ambiguous retries, expiry. No real SMS sent.\n";
} finally {
    $wpdb->query('ROLLBACK');
    remove_filter('pre_http_request', $transport, 10);
    remove_filter('pre_option_tarhan_sms_config', $config);
    remove_filter('pre_option_tarhan_sms_config', $disabled);
}
