<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
function mail_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
mail_check(wp_parse_url(home_url(), PHP_URL_HOST) === 'tarhan-wp.local' && !wp_using_ext_object_cache(), 'Local transactional site only');
mail_check(tarhan_mail_install(), 'Mail schema');
global $wpdb;
$table = tarhan_mail_table();
foreach (array($table, tarhan_attempts_table(), $wpdb->prefix . 'tarhan_enrollments') as $name) {
    $info = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s', $name));
    mail_check($info && strtoupper($info->Engine) === 'INNODB', 'Transactional storage required');
}
$mode = 'accepted'; $requests = 0; $sent = array();
$transport = function ($pre, $args) use (&$mode, &$requests, &$sent) {
    $sent[] = $args;
    $requests++;
    mail_check($args['to'] === 'participant@example.invalid', 'Wrong recipient');
    mail_check(str_contains($args['subject'], 'Тархан'), 'Missing subject');
    mail_check(in_array('From: Тархан <contest@example.invalid>', $args['headers'], true), 'Sender missing');
    if ($mode === 'timeout') { throw new RuntimeException('Synthetic interrupted transport'); }
    return $mode !== 'rejected';
};
$config = function () { return array('enabled' => true, 'from' => 'contest@example.invalid'); };
$disabled = function () { return array(); };
add_filter('pre_wp_mail', $transport, 10, 2);
add_filter('pre_http_request', function () { throw new RuntimeException('Network forbidden in mail test'); });
$wpdb->query('START TRANSACTION');
try {
    // Hide pre-existing pending messages only inside this rolled-back transaction.
    $wpdb->query("UPDATE $table SET status='expired' WHERE status IN ('pending','sending')");
    $uid = 2147482999; $season = 'sms-test-' . bin2hex(random_bytes(4));
    $profile = array('email' => 'participant@example.invalid', 'registered_at' => time(), 'season_id' => $season);
    mail_check(tarhan_store_enrollment($uid, $profile, null), 'Enrollment fixture');
    tarhan_mail_registration($uid, $profile); tarhan_mail_registration($uid, $profile);
    mail_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d", $uid)) === 1, 'Duplicate registration message');
    $questions = array(); $answers = array();
    for ($i = 1; $i <= 50; $i++) { $questions[] = array('id' => $i, 'text' => 'Тест', 'options' => array('Да', 'Нет'), 'correct' => array(0)); $answers[$i] = 0; }
    foreach (array(1 => false, 2 => true, 3 => false) as $number => $passed) {
        $row = array('user_id' => $uid, 'season_id' => $season, 'attempt_number' => $number, 'request_key' => 'sms-' . $number, 'status' => 'in_progress', 'started_at' => time()-10, 'expires_at' => time()+100,
            'questions' => wp_json_encode($questions), 'answers' => wp_json_encode($passed ? $answers : array()));
        mail_check($wpdb->insert(tarhan_attempts_table(), $row) === 1, 'Attempt fixture');
        $row['id'] = $wpdb->insert_id;
        $finished = tarhan_finish_attempt((object) $row, time(), $number === 3 ? 'timeout' : 'submitted');
        tarhan_finish_attempt($finished, time(), 'submitted');
        $mail = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE event_key=%s", 'attempt:' . $row['id']));
        mail_check($mail && $mail->email === 'participant@example.invalid', 'Finished attempt notification missing');
        mail_check(str_contains($mail->message, $passed ? 'напишите эссе' : ($number === 3 ? 'попыток в этом сезоне нет' : 'Осталось попыток: 2')), 'Incorrect result message');
        mail_check(!str_contains($mail->message, '50'), 'Private score exposed');
    }
    mail_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d", $uid)) === 4, 'Duplicate finished message');
    add_filter('pre_option_tarhan_mail_config', $disabled);
    tarhan_mail_process(); mail_check($requests === 0, 'Sent while disabled');
    remove_filter('pre_option_tarhan_mail_config', $disabled);
    add_filter('pre_option_tarhan_mail_config', $config);
    tarhan_mail_process(); tarhan_mail_process();
    mail_check($requests === 4, 'Duplicate transport send');
    mail_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d AND status='accepted'", $uid)) === 4, 'Provider acceptance not recorded');
    foreach (array('rejected' => 'failed', 'timeout' => 'uncertain') as $mode => $expected) {
        tarhan_mail_enqueue('test:' . $mode, $uid, $profile['email'], 'Тест');
        tarhan_mail_process(); $before = $requests; tarhan_mail_process();
        mail_check($before === $requests, 'Unsafe automatic retry');
        mail_check($wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE event_key=%s", 'test:' . $mode)) === $expected, 'Wrong error handling');
    }
    tarhan_mail_enqueue('test:expired', $uid, $profile['email'], 'Тест');
    $wpdb->update($table, array('created_at' => time() - 2*DAY_IN_SECONDS), array('event_key' => 'test:expired'));
    $before = $requests; tarhan_mail_process(); mail_check($requests === $before, 'Stale message sent');
    $templates = tarhan_mail_templates();
    $templates['registration'] = array('subject' => 'Тархан — новая тема', 'body' => "Спасибо!\nТест: {test_url}");
    $templates['failed']['body'] = 'Попыток: {remaining}. {attempts_message}';
    mail_check(!is_wp_error(tarhan_mail_validate_templates($templates)), 'Valid templates rejected');
    $bad = $templates; $bad['passed']['subject'] = '';
    mail_check(is_wp_error(tarhan_mail_validate_templates($bad)), 'Empty subject accepted');
    $bad = $templates; $bad['failed']['body'] = '{unknown}';
    mail_check(is_wp_error(tarhan_mail_validate_templates($bad)), 'Unknown token accepted');
    $custom = function () use (&$templates) { return $templates; };
    add_filter('pre_option_tarhan_mail_templates', $custom);
    mail_check(str_contains(tarhan_mail_message('failed', 0), 'Попыток: 0. Оставшихся попыток'), 'Zero attempts rendering');
    $profile['season_id'] .= '-custom';
    tarhan_mail_registration($uid, $profile);
    $templates['registration']['subject'] = 'Тархан — последующая правка';
    $templates['registration']['body'] = 'Другой текст';
    $mode = 'accepted'; tarhan_mail_process();
    $last = end($sent);
    mail_check($last['subject'] === 'Тархан — новая тема' && $last['message'] === "Спасибо!\nТест: " . home_url('/test/'), 'Queued template snapshot or substitution broken');
    remove_filter('pre_option_tarhan_mail_templates', $custom);
    echo "PASS: editable templates, validation, URLs, zero remaining attempts, immutable queued subject/body.\n";
    echo "PASS: registration, failed/passed/last attempt, timeout completion, deduplication, disabled transport, provider acceptance/errors, no ambiguous retries, expiry. No real email or SMS sent.\n";
} finally {
    $wpdb->query('ROLLBACK');
    remove_filter('pre_wp_mail', $transport, 10);
    remove_filter('pre_option_tarhan_mail_config', $config);
    remove_filter('pre_option_tarhan_mail_config', $disabled);
}
