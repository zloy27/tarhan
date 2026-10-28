<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';

function attempt_check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
function attempt_ok($result, $message) {
    attempt_check(!is_wp_error($result), $message . (is_wp_error($result) ? ': ' . $result->get_error_code() : ''));
    if (is_array($result)) { attempt_assert_public($result); }
    return $result;
}
function attempt_error_is($result, $code) {
    attempt_check(is_wp_error($result) && $result->get_error_code() === $code, 'Expected error: ' . $code);
}

function attempt_assert_public($value) {
    if (!is_array($value) && !is_object($value)) { return; }
    foreach ($value as $key => $item) {
        attempt_check(!in_array($key, array('score', 'percent', 'correct'), true), 'Private result leaked: ' . $key);
        attempt_assert_public($item);
    }
}

function attempt_private_result($id) {
    global $wpdb;
    $table = tarhan_attempts_table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    attempt_check((bool) $row, 'Stored attempt not found');
    return tarhan_attempt_summary($row);
}

global $wpdb;
attempt_check(function_exists('tarhan_attempt_command'), 'Attempt engine unavailable');
attempt_check(!wp_using_ext_object_cache(), 'Use isolated database with external cache');
// Schema creation is permanent; all participant/question test records below roll back.
attempt_ok(tarhan_install_attempts(), 'Schema installation');
$table = tarhan_attempts_table();
foreach (array($table, $wpdb->posts, $wpdb->postmeta, $wpdb->options, $wpdb->users, $wpdb->usermeta) as $name) {
    $info = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $name));
    attempt_check($info && strtoupper($info->Engine) === 'INNODB', 'Nontransactional table: stopped before fixtures');
}
$ids = array();
$users = array();
$passed = array();
$failed = false;
$restriction = function ($query) use (&$ids) {
    if ($query->get('tarhan_question_bank')) { $query->set('post__in', $ids ?: array(0)); }
};
add_action('pre_get_posts', $restriction);
$initial_user = get_current_user_id();
$initial_open = get_option('tarhan_testing_open', '0');
attempt_check($wpdb->query('START TRANSACTION') !== false, 'Transaction failed');

try {
    delete_option('tarhan_seasons');
    $_POST = array();
    for ($i = 0; $i < 3; $i++) {
        $suffix = bin2hex(random_bytes(6));
        $user = wp_insert_user(array('user_login' => 'tarhan_verify_' . $suffix,
            'user_pass' => wp_generate_password(32), 'user_email' => $suffix . '@example.invalid', 'role' => 'subscriber'));
        attempt_check(!is_wp_error($user), 'Temporary user creation failed');
        $users[] = $user;
        attempt_check(tarhan_store_enrollment($user, array('registered_at' => time()), null), 'Enrollment fixture');
    }
    $request = new WP_REST_Request('GET', '/tarhan/v1/test/status');
    wp_set_current_user(0);
    attempt_error_is(tarhan_attempt_command('start', array('request_key' => 'start_request_0001')), 'test_login_required');
    $guest = rest_do_request($request);
    attempt_check($guest->get_status() === 401, 'Guest REST access allowed');
    wp_set_current_user($users[0]);
    attempt_check(rest_do_request($request)->get_status() === 403, 'REST nonce not required');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $response = rest_do_request($request);
    attempt_check($response->get_status() === 200, 'Authenticated REST status failed');
    attempt_assert_public($response->get_data());
    $passed[] = 'Guest access and missing REST nonce rejected; authenticated subscriber allowed';

    update_option('tarhan_testing_open', '0');
    attempt_error_is(tarhan_attempt_command('start', array('request_key' => 'start_request_0001')), 'test_closed');
    update_option('tarhan_testing_open', '1');
    attempt_error_is(tarhan_attempt_command('start', array('request_key' => 'start_request_0001')), 'question_bank_shortage');
    attempt_check(tarhan_attempt_command('status')['attempts_used'] === 0, 'Failed start consumed an attempt');
    $passed[] = 'Closed test and shortage reject starts without consuming attempts';

    for ($i = 0; $i < 55; $i++) {
        $id = wp_insert_post(array('post_type' => 'tarhan_question', 'post_status' => 'draft',
            'post_title' => 'Temporary attempt fixture - rollback'), true);
        attempt_check(!is_wp_error($id), 'Question creation failed');
        $ids[] = $id;
        $wpdb->update($wpdb->posts, array('post_status' => 'publish'), array('ID' => $id));
        update_post_meta($id, '_tarhan_question_valid', 1);
        update_post_meta($id, '_tarhan_question_data', array('text' => 'Технический вопрос ' . $i,
            'options' => array('Первый', 'Второй', 'Третий'), 'correct' => array(2)));
        clean_post_cache($id);
    }
    // Incorrect legacy multiple-answer question must not enter a new test.
    update_post_meta($ids[54], '_tarhan_question_data', array('text' => 'Legacy multiple answer',
        'options' => array('A', 'B'), 'correct' => array(1, 2)));

    attempt_error_is(tarhan_attempt_command('start', array('request_key' => 'bad')), 'attempt_request_key');
    $start = attempt_ok(tarhan_attempt_command('start', array('request_key' => 'start_request_0001')), 'Start');
    $first = $start['attempt']['id'];
    $questions = $start['attempt']['questions'];
    attempt_check(count($questions) === 50 && count(array_unique(array_column($questions, 'id'))) === 50, 'Not 50 unique questions');
    attempt_check(!in_array($ids[54], array_column($questions, 'id'), true), 'Legacy multiple answer selected');
    foreach ($questions as $question) {
        $keys = array_keys($question); sort($keys);
        attempt_check($keys === array('id', 'options', 'text'), 'Private question data leaked');
    }
    attempt_check($start['attempt']['expires_at'] - $start['attempt']['started_at'] === 2400, 'Duration is not 40 minutes');
    $again = tarhan_attempt_command('start', array('request_key' => 'start_request_0002'));
    attempt_check($again['attempt']['id'] === $first && $again['attempts_used'] === 1, 'Double start consumed an attempt');
    attempt_check(tarhan_attempt_command('status')['attempt']['questions'] === $questions, 'Reload changed selection');
    $passed[] = 'Exactly 50 unique single-answer questions, no answer keys exposed; reload and double start keep same attempt';

    $qid = $questions[0]['id'];
    foreach (array(0, 4, '2', array(2), true, 1.5) as $bad) {
        attempt_error_is(tarhan_attempt_command('answer', array('attempt_id' => $first, 'question_id' => $qid, 'answer' => $bad)), 'attempt_answer');
    }
    attempt_error_is(tarhan_attempt_command('answer', array('attempt_id' => $first, 'question_id' => 999999999, 'answer' => 2)), 'attempt_question');
    attempt_ok(tarhan_attempt_command('answer', array('attempt_id' => $first, 'question_id' => $qid, 'answer' => 2)), 'Answer save');
    $saved = tarhan_attempt_command('status');
    attempt_check(((array) $saved['attempt']['answers'])[$qid] === 2, 'Answer not restored');
    attempt_ok(tarhan_attempt_command('answer', array('attempt_id' => $first, 'question_id' => $qid, 'answer' => null)), 'Clear answer');
    attempt_check((array) tarhan_attempt_command('status')['attempt']['answers'] === array(), 'Cleared answer remains');
    $passed[] = 'Answers persist across status requests; clearing works; foreign question and invalid answer types rejected';

    wp_set_current_user($users[1]);
    attempt_error_is(tarhan_attempt_command('answer', array('attempt_id' => $first, 'question_id' => $qid, 'answer' => 2)), 'attempt_not_found');
    attempt_error_is(tarhan_attempt_command('finish', array('attempt_id' => $first)), 'attempt_not_found');
    attempt_check(tarhan_attempt_command('status')['attempts_used'] === 0, 'Foreign attempt visible');
    wp_set_current_user($users[0]);
    $passed[] = 'Another subscriber cannot read, answer or finish the first subscriber attempt';

    // Changing the bank must not change the answer key of an already started attempt.
    foreach ($questions as $question) {
        update_post_meta($question['id'], '_tarhan_question_data', array('text' => 'Changed bank',
            'options' => array('A', 'B', 'C'), 'correct' => array(1)));
    }
    update_option('tarhan_testing_open', '0');
    for ($i = 0; $i < 30; $i++) {
        attempt_ok(tarhan_attempt_command('answer', array('attempt_id' => $first, 'question_id' => $questions[$i]['id'], 'answer' => 2)), 'Save passing answers');
    }
    $finish = attempt_ok(tarhan_attempt_command('finish', array('attempt_id' => $first, 'score' => 50)), 'Finish first');
    $private_result = attempt_private_result($first);
    attempt_check($private_result['score'] === 30 && $private_result['percent'] === 60 && $finish['latest_result']['passed'] && $finish['essay_allowed'], 'Snapshot grade incorrect');
    $rest_finished = rest_do_request($request);
    attempt_check($rest_finished->get_status() === 200 && $rest_finished->get_data()['latest_result']['passed'], 'REST finished result unavailable');
    attempt_assert_public($rest_finished->get_data());
    attempt_check(!isset($finish['attempt']['questions']), 'Completed attempt unnecessarily exposes questions');
    $repeat = tarhan_attempt_command('finish', array('attempt_id' => $first));
    attempt_check($repeat['latest_result'] === $finish['latest_result'], 'Double finish changed result');
    attempt_error_is(tarhan_attempt_command('answer', array('attempt_id' => $first, 'question_id' => $qid, 'answer' => 1)), 'attempt_closed');
    $retry = tarhan_attempt_command('start', array('request_key' => 'start_request_0001'));
    attempt_check($retry['attempt']['id'] === $first && $retry['attempts_used'] === 1, 'Retry key created another attempt');
    $passed[] = 'Internal score remains 30/50; participant REST and command responses expose pass/fail only; snapshot and idempotency preserved';

    update_option('tarhan_testing_open', '1');
    attempt_error_is(tarhan_attempt_command('start', array('request_key' => 'passed_retry_blocked')), 'test_already_passed');
    $passed_status = tarhan_attempt_command('status');
    attempt_check(!$passed_status['can_start'] && $passed_status['attempts_remaining'] === 0 && $passed_status['attempts_used'] === 1, 'Passing did not close remaining attempts');
    $passed[] = 'Passing closes remaining attempts and rejects direct API restarts without changing the result';
    wp_set_current_user($users[2]);
    $second = attempt_ok(tarhan_attempt_command('start', array('request_key' => 'start_request_second')), 'Second start');
    for ($i = 0; $i < 24; $i++) {
        $q = $second['attempt']['questions'][$i];
        $snapshot = json_decode($wpdb->get_var($wpdb->prepare("SELECT questions FROM $table WHERE id = %d", $second['attempt']['id'])), true);
        $key_by_id = array_column($snapshot, 'correct', 'id');
        attempt_ok(tarhan_attempt_command('answer', array('attempt_id' => $second['attempt']['id'], 'question_id' => $q['id'], 'answer' => $key_by_id[$q['id']][0])), 'Second answer');
    }
    $second_finish = attempt_ok(tarhan_attempt_command('finish', array('attempt_id' => $second['attempt']['id'])), 'Second finish');
    attempt_check(attempt_private_result($second['attempt']['id'])['score'] === 24 && !$second_finish['latest_result']['passed'] && !$second_finish['essay_allowed'] && $second_finish['can_start'], '24/50 must allow retry but not essay');
    $passed[] = '24/50 fails and retains retry availability';

    $third = attempt_ok(tarhan_attempt_command('start', array('request_key' => 'start_request_third')), 'Third start');
    $third_id = $third['attempt']['id'];
    $wpdb->update($table, array('expires_at' => time() - 1), array('id' => $third_id));
    attempt_error_is(tarhan_attempt_command('answer', array('attempt_id' => $third_id, 'question_id' => $third['attempt']['questions'][0]['id'],
        'answer' => 1, 'expires_at' => time() + 999999)), 'attempt_closed');
    $expired = tarhan_attempt_command('status');
    attempt_check($expired['latest_result']['finish_reason'] === 'timeout' && !$expired['latest_result']['passed'] && attempt_private_result($third_id)['score'] === 0, 'Expiry not finalized');
    attempt_assert_public($expired);
    attempt_check($expired['attempts_remaining'] === 1, 'Attempt count incorrect');
    $last_failed = attempt_ok(tarhan_attempt_command('start', array('request_key' => 'last_failed_attempt')), 'Last allowed attempt');
    $last_finished = attempt_ok(tarhan_attempt_command('finish', array('attempt_id' => $last_failed['attempt']['id'])), 'Last failed finish');
    attempt_check($last_finished['attempts_remaining'] === 0, 'Failed attempt limit not exhausted');
    attempt_error_is(tarhan_attempt_command('start', array('request_key' => 'start_request_fourth')), 'attempt_limit');
    $passed[] = 'Server expiry rejects late answers and ignores forged deadline; unanswered score zero; fourth attempt blocked';

    wp_set_current_user($users[1]);
    $boundary = attempt_ok(tarhan_attempt_command('start', array('request_key' => 'boundary_request_01')), 'Boundary start');
    $snapshot = json_decode($wpdb->get_var($wpdb->prepare("SELECT questions FROM $table WHERE id = %d", $boundary['attempt']['id'])), true);
    for ($i = 0; $i < 25; $i++) {
        attempt_ok(tarhan_attempt_command('answer', array('attempt_id' => $boundary['attempt']['id'], 'question_id' => $snapshot[$i]['id'],
            'answer' => $snapshot[$i]['correct'][0])), 'Boundary answer');
    }
    $boundary_finish = tarhan_attempt_command('finish', array('attempt_id' => $boundary['attempt']['id']));
    attempt_check(attempt_private_result($boundary['attempt']['id'])['score'] === 25 && $boundary_finish['latest_result']['passed'] && $boundary_finish['essay_allowed'], '25/50 does not pass');
    attempt_assert_public($boundary_finish);
    attempt_check(!$boundary_finish['can_start'] && $boundary_finish['attempts_remaining'] === 0, '25/50 did not close retries');
    attempt_error_is(tarhan_attempt_command('start', array('request_key' => 'boundary_retry_blocked')), 'test_already_passed');
    $passed[] = 'Exactly 25/50 opens essay eligibility and blocks further attempts';

    // Verify another connection holds the per-user lock: engine must refuse concurrent work.
    $connection = mysqli_init();
    attempt_check($connection->real_connect('localhost', DB_USER, DB_PASSWORD, DB_NAME,
        (int) $wpdb->get_var('SELECT @@port')), 'Secondary database connection failed');
    $lock = 'tarhan_' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix . ':' . $users[1]), 0, 54);
    $escaped = $connection->real_escape_string($lock);
    $connection->query("SELECT GET_LOCK('$escaped', 0)");
    try { attempt_error_is(tarhan_attempt_command('status'), 'attempt_busy'); }
    finally { $connection->query("SELECT RELEASE_LOCK('$escaped')"); $connection->close(); }
    $passed[] = 'Per-user lock rejects simultaneous work from a second database connection';
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    $failed = true;
} finally {
    remove_action('pre_get_posts', $restriction);
    if ($wpdb->query('ROLLBACK') === false) { $failed = true; }
    foreach ($ids as $id) { clean_post_cache($id); wp_cache_delete($id, 'post_meta'); }
    foreach ($users as $id) {
        clean_user_cache($id); wp_cache_delete($id, 'user_meta');
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id = %d", $id)) ||
            $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID = %d", $id))) {
            fwrite(STDERR, "Temporary records remain\n"); $failed = true;
        }
    }
    foreach ($ids as $id) {
        if ($wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $id))) { $failed = true; }
    }
    wp_cache_delete('tarhan_testing_open', 'options');
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
    wp_set_current_user($initial_user);
    attempt_check(get_option('tarhan_testing_open', '0') === $initial_open, 'Open setting not restored');
}
echo json_encode(array('passed' => $passed, 'test_records_rolled_back' => !$failed), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
exit($failed ? 1 : 0);
