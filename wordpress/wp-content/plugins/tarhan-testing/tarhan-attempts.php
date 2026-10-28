<?php
if (!defined('ABSPATH')) { exit; }

function tarhan_test_rules()
{
    return array('questions' => 50, 'seconds' => 40 * MINUTE_IN_SECONDS, 'attempts' => 3, 'pass_score' => 25);
}

function tarhan_attempts_table()
{
    global $wpdb;
    return $wpdb->prefix . 'tarhan_season_attempts';
}

function tarhan_install_attempts()
{
    if (get_option('tarhan_attempts_schema') === '1') { return true; }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = $wpdb->prefix . 'tarhan_attempts';
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        attempt_number tinyint(3) unsigned NOT NULL,
        request_key varchar(64) NOT NULL,
        status varchar(16) NOT NULL,
        started_at bigint(20) unsigned NOT NULL,
        expires_at bigint(20) unsigned NOT NULL,
        finished_at bigint(20) unsigned DEFAULT NULL,
        questions longtext NOT NULL,
        answers longtext NOT NULL,
        score tinyint(3) unsigned DEFAULT NULL,
        finish_reason varchar(16) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY user_attempt (user_id,attempt_number),
        UNIQUE KEY user_request (user_id,request_key),
        KEY user_status (user_id,status)
    ) ENGINE=InnoDB $charset;");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
        return new WP_Error('attempt_storage', 'Не удалось подготовить хранилище попыток.');
    }
    update_option('tarhan_attempts_schema', '1', false);
    return true;
}

function tarhan_attempts_admin_init()
{
    if (current_user_can('manage_options')) { tarhan_install_attempts(); }
}
add_action('admin_init', 'tarhan_attempts_admin_init');

function tarhan_testing_save_settings()
{
    if (!current_user_can('manage_options')) { wp_die('Недостаточно прав.', '', array('response' => 403)); }
    check_admin_referer('tarhan_testing_settings');
    update_option('tarhan_testing_open', isset($_POST['testing_open']) && $_POST['testing_open'] === '1' ? '1' : '0', false);
    wp_safe_redirect(admin_url('admin.php?page=tarhan-testing'));
    exit;
}
add_action('admin_post_tarhan_testing_settings', 'tarhan_testing_save_settings');

function tarhan_attempt_error($code, $message, $status = 400)
{
    return new WP_Error($code, $message, array('status' => $status));
}

// Данные читаются из снимка попытки, а не из редактируемых вопросов банка.
function tarhan_attempt_questions($row)
{
    $questions = json_decode($row->questions, true);
    if (!is_array($questions) || count($questions) !== tarhan_test_rules()['questions']) {
        throw new RuntimeException('Invalid question snapshot');
    }
    return $questions;
}

function tarhan_attempt_answers($row)
{
    $answers = json_decode($row->answers, true);
    if (!is_array($answers)) { throw new RuntimeException('Invalid answer storage'); }
    return $answers;
}

function tarhan_finish_attempt($row, $now, $reason)
{
    if ($row->status === 'finished') { return $row; }
    global $wpdb;
    $questions = tarhan_attempt_questions($row);
    $answers = tarhan_attempt_answers($row);
    $score = tarhan_calculate_score($questions,$answers);
    $changes = array('status' => 'finished', 'score' => $score,
        'finished_at' => min($now, (int) $row->expires_at), 'finish_reason' => $reason);
    if ($wpdb->update(tarhan_attempts_table(), $changes, array('id' => $row->id, 'user_id' => $row->user_id)) === false) {
        throw new RuntimeException('Attempt finalization failed');
    }
    foreach ($changes as $key => $value) { $row->$key = $value; }
    tarhan_mail_attempt($row);
    return $row;
}

// Internal result for organisers. Never send this array directly to a participant.
function tarhan_attempt_summary($row)
{
    return array('id' => (int) $row->id, 'number' => (int) $row->attempt_number,
        'status' => $row->status, 'started_at' => (int) $row->started_at, 'expires_at' => (int) $row->expires_at,
        'finished_at' => $row->finished_at === null ? null : (int) $row->finished_at,
        'score' => $row->score === null ? null : (int) $row->score,
        'percent' => $row->score === null ? null : (int) round((int) $row->score / tarhan_test_rules()['questions'] * 100),
        'passed' => $row->score !== null && (int) $row->score >= tarhan_test_rules()['pass_score'],
        'finish_reason' => $row->finish_reason);
}

function tarhan_latest_finished_attempt($attempts)
{
    $latest = null;
    foreach ($attempts as $attempt) {
        if($attempt -> status === 'finished'){
            if ($latest === null){
                $latest = $attempt;
            }
            if($latest->attempt_number < $attempt->attempt_number){
                $latest = $attempt;
            }
            }
           
        }
    return $latest;
}

function tarhan_participant_test_summary($attempts)
{
    $rules = tarhan_test_rules();
    if(tarhan_latest_finished_attempt($attempts) === null){
        $result = array(
            'attempts_used' => count($attempts),       // Сколько попыток начато.
            'attempts_remaining' => max(0,$rules['attempts'] - count($attempts)),  // Сколько ещё можно начать.
            'score' => null,               // Последний завершённый балл.
            'percent' => null,             // Процент этого результата.
            'essay_allowed' => false,   
        );
    }
    if(tarhan_latest_finished_attempt($attempts) !== null){
            $rules = tarhan_test_rules();
            $latest_attempt = tarhan_latest_finished_attempt($attempts);
            $result = array(
            'attempts_used' => count($attempts),       // Сколько попыток начато.
            'attempts_remaining' => max(0,$rules['attempts'] - count($attempts)),  // Сколько ещё можно начать.
            'score' => $latest_attempt->score,               // Последний завершённый балл.
            'percent' => ($latest_attempt->score / $rules['questions']) * 100,            // Процент этого результата.
            'essay_allowed' => $latest_attempt->score >= $rules['pass_score'],
            );

    };
    if ($result['essay_allowed']) { $result['attempts_remaining'] = 0; }
    return $result;
    
}

function tarhan_public_attempt($row)
{
    $summary = tarhan_attempt_summary($row);
    return array_intersect_key($summary, array_flip(array(
        'id', 'number', 'status', 'started_at', 'expires_at', 'finished_at', 'passed', 'finish_reason'
    )));
}

function tarhan_attempt_response($rows, $selected, $now)
{
    $latest = tarhan_latest_finished_attempt($rows);
    $summary = tarhan_participant_test_summary($rows);
    $active = null;
    foreach ($rows as $row) {
        if ($row->status === 'in_progress') { $active = $row; }
    }
    $attempt = $selected ?: $active;
    $public = $attempt ? tarhan_public_attempt($attempt) : null;
    if ($attempt && $attempt->status === 'in_progress') {
        $public['questions'] = tarhan_public_questions(tarhan_attempt_questions($attempt));
        $public['answers'] = (object) tarhan_attempt_answers($attempt);
    }
    $remaining = $summary['attempts_remaining'];
    $open = get_option('tarhan_testing_open', '0') === '1';
    return array('rules' => tarhan_test_rules(), 'server_time' => $now, 'open' => $open,
        'attempts_used' => $summary['attempts_used'], 'attempts_remaining' => $remaining,
        'can_start' => $open && !$active && $remaining > 0 && !$summary['essay_allowed'],
        'essay_allowed' => $summary['essay_allowed'],
        'latest_result' => $latest ? tarhan_public_attempt($latest) : null, 'attempt' => $public);
}

function tarhan_attempt_positive_id($value)
{
    if (!is_int($value) && !(is_string($value) && ctype_digit($value))) { return false; }
    return filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
}

// Единственная точка изменения попыток. Блокировка на пользователя защищает
// от одновременных запросов из двух вкладок и двойного нажатия «Начать».
function tarhan_attempt_command($command, $payload = array())
{
    $user_id = get_current_user_id();
    if (!$user_id || !current_user_can('read')) {
        return tarhan_attempt_error('test_login_required', 'Сначала войдите в аккаунт.', 401);
    }
    if (get_option('tarhan_attempts_schema') !== '1') {
        return tarhan_attempt_error('attempt_storage', 'Хранилище теста ещё не подготовлено.', 503);
    }
    global $wpdb;
    $season = tarhan_enrollment_context($user_id);
    if (is_wp_error($season)) { return $season; }
    if ($command !== 'status' && ($payload['season_id'] ?? '') !== $season) { return tarhan_attempt_error('season_changed', 'Сезон изменился. Обновите страницу теста.', 409); }
    $lock = 'tarhan_' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix . ':' . $user_id), 0, 54);
    if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== '1') {
        return tarhan_attempt_error('attempt_busy', 'Предыдущий запрос ещё обрабатывается. Повторите запрос.', 409);
    }

    try {
        $now = time();
        $table = tarhan_attempts_table();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d AND season_id = %s ORDER BY attempt_number ASC", $user_id, $season));
        if ($wpdb->last_error) { throw new RuntimeException('Attempt read failed'); }
        $active = null;
        foreach ($rows as $row) {
            if ($row->status === 'in_progress' && $now >= (int) $row->expires_at) {
                tarhan_finish_attempt($row, $now, 'timeout');
            }
            if ($row->status === 'in_progress') { $active = $row; }
        }

        if ($command === 'status') { return tarhan_attempt_response($rows, $active, $now); }

        if ($command === 'start') {
            $key = $payload['request_key'] ?? null;
            if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{16,64}$/D', $key)) {
                return tarhan_attempt_error('attempt_request_key', 'Не передан корректный идентификатор запроса.');
            }
            foreach ($rows as $row) {
                if ($row->request_key === $key) { return tarhan_attempt_response($rows, $row, $now); }
            }
            if (tarhan_participant_test_summary($rows)['essay_allowed']) {
                return tarhan_attempt_error('test_already_passed', 'Тест уже пройден. Перейдите к эссе.', 403);
            }
            if ($active) { return tarhan_attempt_response($rows, $active, $now); }
            if (get_option('tarhan_testing_open', '0') !== '1') {
                return tarhan_attempt_error('test_closed', 'Начало новых попыток сейчас закрыто.', 403);
            }
            if (count($rows) >= tarhan_test_rules()['attempts']) {
                return tarhan_attempt_error('attempt_limit', 'Все три попытки уже использованы.', 409);
            }
            $questions = tarhan_get_random_questions(tarhan_test_rules()['questions']);
            if (is_wp_error($questions)) {
                return tarhan_attempt_error($questions->get_error_code(), $questions->get_error_message(), 409);
            }
            $new = array('user_id' => $user_id, 'season_id' => $season, 'attempt_number' => count($rows) + 1, 'request_key' => $key,
                'status' => 'in_progress', 'started_at' => $now, 'expires_at' => $now + tarhan_test_rules()['seconds'],
                'questions' => wp_json_encode($questions), 'answers' => '{}');
            if ($wpdb->insert($table, $new) === false) { throw new RuntimeException('Attempt creation failed'); }
            $row = (object) array_merge($new, array('id' => $wpdb->insert_id, 'score' => null, 'finished_at' => null, 'finish_reason' => ''));
            $rows[] = $row;
            return tarhan_attempt_response($rows, $row, $now);
        }

        $id = tarhan_attempt_positive_id($payload['attempt_id'] ?? null);
        $attempt = null;
        foreach ($rows as $row) { if ((int) $row->id === $id) { $attempt = $row; break; } }
        if (!$attempt) { return tarhan_attempt_error('attempt_not_found', 'Попытка не найдена.', 404); }

        if ($command === 'finish') {
            tarhan_finish_attempt($attempt, $now, 'submitted');
            return tarhan_attempt_response($rows, $attempt, $now);
        }
        if ($command !== 'answer') { return tarhan_attempt_error('attempt_command', 'Неизвестное действие.'); }
        if ($attempt->status !== 'in_progress') {
            return tarhan_attempt_error('attempt_closed', 'Попытка уже завершена. Ответ не сохранён.', 409);
        }
        $question_id = tarhan_attempt_positive_id($payload['question_id'] ?? null);
        $question = null;
        foreach (tarhan_attempt_questions($attempt) as $item) {
            if ($item['id'] === $question_id) { $question = $item; break; }
        }
        if (!$question || !array_key_exists('answer', $payload)) {
            return tarhan_attempt_error('attempt_question', 'Не указан вопрос из этой попытки или выбранный ответ.');
        }
        $answer = $payload['answer'];
        if ($answer !== null && (!is_int($answer) || $answer < 1 || $answer > count($question['options']))) {
            return tarhan_attempt_error('attempt_answer', 'Выберите один существующий вариант ответа.');
        }
        $answers = tarhan_attempt_answers($attempt);
        if ($answer === null) { unset($answers[$question_id]); } else { $answers[$question_id] = $answer; }
        $json = wp_json_encode((object) $answers);
        if ($wpdb->update($table, array('answers' => $json), array('id' => $attempt->id, 'user_id' => $user_id)) === false) {
            throw new RuntimeException('Answer save failed');
        }
        $attempt->answers = $json;
        return tarhan_attempt_response($rows, $attempt, $now);
    } catch (Throwable $error) {
        return tarhan_attempt_error('attempt_storage', 'Не удалось обработать попытку. Повторите запрос; не начинайте новую попытку.', 500);
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

function tarhan_test_permission($request)
{
    if (!is_user_logged_in() || !current_user_can('read')) {
        return tarhan_attempt_error('test_login_required', 'Сначала войдите в аккаунт.', 401);
    }
    if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
        return tarhan_attempt_error('test_nonce', 'Обновите страницу и повторите действие.', 403);
    }
    return true;
}

function tarhan_register_test_routes()
{
    foreach (array('status', 'start', 'answer', 'finish') as $command) {
        register_rest_route('tarhan/v1', '/test/' . $command, array(
            'methods' => $command === 'status' ? 'GET' : 'POST',
            'permission_callback' => 'tarhan_test_permission',
            'callback' => function ($request) use ($command) {
                $payload = $command === 'status' ? array() : $request->get_json_params();
                if (!is_array($payload)) { return tarhan_attempt_error('test_payload', 'Ожидается JSON-объект с данными запроса.'); }
                $result = tarhan_attempt_command($command, $payload);
                if (is_wp_error($result)) { return $result; }
                $response = new WP_REST_Response($result);
                $response->header('Cache-Control', 'no-store, private');
                return $response;
            },
        ));
    }
}
add_action('rest_api_init', 'tarhan_register_test_routes');

function tarhan_test_no_cache($response, $server, $request)
{
    if (strpos($request->get_route(), '/tarhan/v1/test/') === 0) {
        $response->header('Cache-Control', 'no-store, private');
    }
    return $response;
}
add_filter('rest_post_dispatch', 'tarhan_test_no_cache', 10, 3);
