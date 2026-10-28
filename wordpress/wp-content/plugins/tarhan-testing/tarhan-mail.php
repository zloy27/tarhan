<?php
defined('ABSPATH') || exit;

function tarhan_mail_table() { global $wpdb; return $wpdb->prefix . 'tarhan_mail'; }

function tarhan_mail_install()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = tarhan_mail_table(); $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_key varchar(100) NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        email varchar(254) NOT NULL,
        message text NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        created_at bigint(20) unsigned NOT NULL,
        updated_at bigint(20) unsigned NOT NULL,
        error_code varchar(80) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY event_key (event_key),
        KEY status_id (status,id)
    ) ENGINE=InnoDB $charset;");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) { return false; }
    update_option('tarhan_mail_schema', '1', false);
    return true;
}

function tarhan_mail_message($kind, $remaining = 0)
{
    if ($kind === 'registration') {
        return 'Спасибо! Ваша регистрация успешно прошла. Рады видеть вас на конкурсе «Тархан»! Для дальнейшего участия необходимо пройти тест и написать эссе. Желаем удачи!';
    }
    if ($kind === 'passed') {
        return 'Спасибо за участие в историко-культурном конкурсе «Тархан»! Вы большой молодец: вы набрали достаточное количество баллов. Обязательно напишите эссе и ожидайте связи от организаторов.';
    }
    if ($kind === 'failed') {
        $text = 'Спасибо за участие в историко-культурном конкурсе «Тархан»! К сожалению, вы пока не набрали необходимое количество баллов. ';
        return $text . ($remaining > 0 ? 'Но есть хорошие новости: можно попробовать ещё! Осталось попыток: ' . (int) $remaining . '.' : 'Оставшихся попыток в этом сезоне нет. Спасибо за ваши старания!');
    }
    return '';
}

function tarhan_mail_enqueue($key, $user_id, $email, $message)
{
    global $wpdb;
    if (get_option('tarhan_mail_schema') !== '1') { return false; }
    $email = sanitize_email($email);
    if (!is_email($email) || !$message) { return false; }
    $table = tarhan_mail_table();
    // One durable event per registration/attempt, including simultaneous requests.
    $result = $wpdb->query($wpdb->prepare("INSERT INTO $table (event_key,user_id,email,message,created_at,updated_at) VALUES (%s,%d,%s,%s,%d,%d) ON DUPLICATE KEY UPDATE event_key=event_key", $key, $user_id, $email, $message, time(), time()));
    if ($result === false) { error_log('Tarhan mail: queue write failed'); }
    return $result !== false;
}

function tarhan_mail_registration($id, $profile)
{
    return tarhan_mail_enqueue('registration:' . $id . ':' . ($profile['season_id'] ?? ''), $id, $profile['email'] ?? '', tarhan_mail_message('registration'));
}

function tarhan_mail_attempt($row)
{
    // Notifications must never undo a saved test result if their storage is unavailable.
    try {
        $enrollment = tarhan_enrollment($row->user_id, $row->season_id ?? '');
        $profile = $enrollment ? json_decode($enrollment->profile, true) : get_user_meta($row->user_id, 'tarhan_profile', true);
        if (!is_array($profile) || empty($profile['email'])) { return false; }
        $rules = tarhan_test_rules();
        $kind = (int) $row->score >= $rules['pass_score'] ? 'passed' : 'failed';
        return tarhan_mail_enqueue('attempt:' . $row->id, $row->user_id, $profile['email'], tarhan_mail_message($kind, max(0, $rules['attempts'] - (int) $row->attempt_number)));
    } catch (Throwable $e) { error_log('Tarhan mail: attempt notification unavailable'); return false; }
}

function tarhan_mail_config()
{
    $config = get_option('tarhan_mail_config', array());
    return is_array($config) ? $config : array();
}

function tarhan_mail_send($row, $config)
{
    $registration = str_starts_with($row->event_key, 'registration:');
    $subject = $registration ? 'Тархан — регистрация прошла успешно' : 'Тархан — результат теста';
    $body = $row->message . "\n\nСайт конкурса: " . home_url('/') . "\n\nОргкомитет конкурса «Тархан»";
    $headers = array('Content-Type: text/plain; charset=UTF-8', 'From: Тархан <' . $config['from'] . '>');
    return array('status' => wp_mail($row->email, $subject, $body, $headers) ? 'accepted' : 'failed', 'error_code' => '');
}

function tarhan_mail_process()
{
    global $wpdb;
    $config = tarhan_mail_config();
    if (empty($config['enabled']) || !is_email($config['from'] ?? '') || get_option('tarhan_mail_schema') !== '1') { return; }
    $table = tarhan_mail_table(); $now = time();
    $wpdb->query($wpdb->prepare("UPDATE $table SET status='expired',updated_at=%d WHERE status='pending' AND created_at < %d", $now, $now - DAY_IN_SECONDS));
    // Do not retry an interrupted send: the mail server may already have accepted it.
    $wpdb->query($wpdb->prepare("UPDATE $table SET status='uncertain',error_code='interrupted',updated_at=%d WHERE status='sending' AND updated_at < %d", $now, $now - 300));
    foreach ($wpdb->get_results("SELECT * FROM $table WHERE status='pending' ORDER BY id LIMIT 10") as $row) {
        if ($wpdb->update($table, array('status' => 'sending', 'updated_at' => time()), array('id' => $row->id, 'status' => 'pending')) !== 1) { continue; }
        try { $result = tarhan_mail_send($row, $config); }
        catch (Throwable $error) { $result = array('status' => 'uncertain', 'error_code' => 'transport'); }
        if ($result['status'] === 'failed') { $result['error_code'] = 'mail_rejected'; }
        $result['updated_at'] = time();
        $wpdb->update($table, $result, array('id' => $row->id, 'status' => 'sending'));
    }
}

add_filter('cron_schedules', function ($schedules) { $schedules['tarhan_mail_minute'] = array('interval' => 60, 'display' => 'Письма Тархан'); return $schedules; });
add_action('tarhan_mail_tick', 'tarhan_mail_process');
add_action('init', function () {
    // Replacing the transport never replays the previous SMS queue.
    if (wp_next_scheduled('tarhan_sms_tick')) { wp_clear_scheduled_hook('tarhan_sms_tick'); }
    if (get_option('tarhan_mail_schema') === '1' && !wp_next_scheduled('tarhan_mail_tick')) { wp_schedule_event(time() + 60, 'tarhan_mail_minute', 'tarhan_mail_tick'); }
});
add_action('admin_init', function () {
    if (current_user_can('manage_options') && get_option('tarhan_mail_schema') !== '1') { tarhan_mail_install(); }
});
add_action('admin_menu', function () { add_submenu_page('tarhan-testing', 'Письма участникам', 'Письма участникам', 'manage_options', 'tarhan-mail', 'tarhan_mail_page'); });

function tarhan_mail_page()
{
    if (!current_user_can('manage_options')) { wp_die('Недостаточно прав.', '', array('response' => 403)); }
    $config = tarhan_mail_config();
    echo '<div class="wrap"><h1>Письма участникам</h1>';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('tarhan_mail_settings');
        $from = trim(wp_unslash($_POST['from'] ?? ''));
        if (!is_email($from)) {
            echo '<div class="notice notice-error"><p>Укажите корректный адрес отправителя.</p></div>';
        } else {
            $config = array('from' => sanitize_email($from), 'enabled' => isset($_POST['enabled']));
            update_option('tarhan_mail_config', $config, false);
            echo '<div class="notice notice-success"><p>Настройки сохранены.</p></div>';
        }
    }
    echo '<p>Письма о регистрации и результате теста отправляются на email из анкеты соответствующего сезона. SMS отключены.</p><p>Сначала настройте SMTP WordPress и подтвердите адрес отправителя у почтового сервиса. Включение ниже само по себе не настраивает доставку.</p><form method="post">';
    wp_nonce_field('tarhan_mail_settings');
    echo '<p><label>Email отправителя <input type="email" name="from" required value="' . esc_attr($config['from'] ?? '') . '"></label></p>';
    echo '<p><label><input type="checkbox" name="enabled" ' . checked(!empty($config['enabled']), true, false) . '> Включить письма (в том числе очередь за последние сутки)</label></p>';
    submit_button('Сохранить');
    echo '</form><h2>Тексты писем</h2>';
    foreach (array('registration', 'failed', 'passed') as $kind) { echo '<p>' . esc_html(tarhan_mail_message($kind, 2)) . '</p>'; }
    echo '<h2>Последние письма</h2><p>«Принято почтовой системой» не означает доставку получателю. Ошибки и прерванные отправки автоматически не повторяются. Очередь старше суток не отправляется.</p>';
    global $wpdb; $table = tarhan_mail_table();
    $labels = array('pending' => 'В очереди', 'sending' => 'Отправляется', 'accepted' => 'Принято почтовой системой', 'failed' => 'Ошибка отправки', 'uncertain' => 'Требует проверки', 'expired' => 'Устарело');
    echo '<table class="widefat striped"><thead><tr><th>Дата</th><th>Участник</th><th>Email</th><th>Событие</th><th>Статус</th></tr></thead><tbody>';
    if (get_option('tarhan_mail_schema') === '1') {
        foreach ($wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 50") as $row) {
            echo '<tr><td>' . esc_html(wp_date('d.m.Y H:i', $row->created_at)) . '</td><td>' . (int) $row->user_id . '</td><td>' . esc_html($row->email) . '</td><td>' . esc_html(str_starts_with($row->event_key, 'registration:') ? 'Регистрация' : 'Завершение теста') . '</td><td>' . esc_html($labels[$row->status] ?? $row->status) . '</td></tr>';
        }
    }
    echo '</tbody></table><p>На хостинге настройте запуск фоновых задач WordPress каждую минуту.</p></div>';
}

