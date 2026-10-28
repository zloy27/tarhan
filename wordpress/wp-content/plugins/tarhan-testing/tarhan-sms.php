<?php
defined('ABSPATH') || exit;

function tarhan_sms_table() { global $wpdb; return $wpdb->prefix . 'tarhan_sms'; }

function tarhan_sms_install()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = tarhan_sms_table(); $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_key varchar(100) NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        phone varchar(20) NOT NULL,
        message text NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        created_at bigint(20) unsigned NOT NULL,
        updated_at bigint(20) unsigned NOT NULL,
        provider_id varchar(100) NOT NULL DEFAULT '',
        error_code varchar(80) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY event_key (event_key),
        KEY status_id (status,id)
    ) ENGINE=InnoDB $charset;");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) { return false; }
    update_option('tarhan_sms_schema', '1', false);
    return true;
}

function tarhan_sms_message($kind, $remaining = 0)
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

function tarhan_sms_enqueue($key, $user_id, $phone, $message)
{
    global $wpdb;
    if (get_option('tarhan_sms_schema') !== '1') { return false; }
    $phone = tarhan_normalize_phone($phone);
    if (!preg_match('/^[1-9][0-9]{9,14}$/D', $phone) || !$message) { return false; }
    $table = tarhan_sms_table();
    // One durable event per registration/attempt, including simultaneous requests.
    $result = $wpdb->query($wpdb->prepare("INSERT INTO $table (event_key,user_id,phone,message,created_at,updated_at) VALUES (%s,%d,%s,%s,%d,%d) ON DUPLICATE KEY UPDATE event_key=event_key", $key, $user_id, $phone, $message, time(), time()));
    if ($result === false) { error_log('Tarhan SMS: queue write failed'); }
    return $result !== false;
}

function tarhan_sms_registration($id, $profile)
{
    return tarhan_sms_enqueue('registration:' . $id . ':' . ($profile['season_id'] ?? ''), $id, $profile['phone'], tarhan_sms_message('registration'));
}

function tarhan_sms_attempt($row)
{
    // Notifications must never undo a saved test result if their storage is unavailable.
    try {
        $enrollment = tarhan_enrollment($row->user_id, $row->season_id ?? '');
        $profile = $enrollment ? json_decode($enrollment->profile, true) : get_user_meta($row->user_id, 'tarhan_profile', true);
        if (!is_array($profile) || empty($profile['phone'])) { return false; }
        $rules = tarhan_test_rules();
        $kind = (int) $row->score >= $rules['pass_score'] ? 'passed' : 'failed';
        return tarhan_sms_enqueue('attempt:' . $row->id, $row->user_id, $profile['phone'], tarhan_sms_message($kind, max(0, $rules['attempts'] - (int) $row->attempt_number)));
    } catch (Throwable $e) { error_log('Tarhan SMS: attempt notification unavailable'); return false; }
}

function tarhan_sms_config()
{
    $config = get_option('tarhan_sms_config', array());
    if (!is_array($config)) { $config = array(); }
    if (defined('TARHAN_SMS_API_KEY')) { $config['key'] = TARHAN_SMS_API_KEY; }
    return $config;
}

function tarhan_sms_send($row, $config)
{
    // Fixed HTTPS endpoint: credentials and participant data never enter URLs or logs.
    $body = array('api_id' => $config['key'], 'to' => $row->phone, 'msg' => $row->message, 'json' => 1);
    if (!empty($config['sender'])) { $body['from'] = $config['sender']; }
    $response = wp_remote_post('https://sms.ru/sms/send', array('timeout' => 15, 'redirection' => 0, 'body' => $body));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return array('status' => 'uncertain', 'error_code' => 'transport'); }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    $sms = $data['sms'][$row->phone] ?? null;
    if (($data['status'] ?? '') === 'OK' && ($sms['status'] ?? '') === 'OK' && !empty($sms['sms_id'])) {
        return array('status' => 'accepted', 'provider_id' => substr(sanitize_text_field($sms['sms_id']), 0, 100), 'error_code' => '');
    }
    if (($data['status'] ?? '') === 'ERROR' || ($sms['status'] ?? '') === 'ERROR') {
        return array('status' => 'failed', 'error_code' => 'provider_' . (int) ($sms['status_code'] ?? $data['status_code'] ?? 0));
    }
    return array('status' => 'uncertain', 'error_code' => 'invalid_response');
}

function tarhan_sms_process()
{
    global $wpdb;
    $config = tarhan_sms_config();
    if (empty($config['enabled']) || ($config['provider'] ?? '') !== 'smsru' || empty($config['key']) || get_option('tarhan_sms_schema') !== '1') { return; }
    $table = tarhan_sms_table(); $now = time();
    $wpdb->query($wpdb->prepare("UPDATE $table SET status='expired',updated_at=%d WHERE status='pending' AND created_at < %d", $now, $now - DAY_IN_SECONDS));
    // A crashed/ambiguous send is not retried blindly: the provider may already have charged/sent it.
    $wpdb->query($wpdb->prepare("UPDATE $table SET status='uncertain',error_code='interrupted',updated_at=%d WHERE status='sending' AND updated_at < %d", $now, $now - 300));
    $rows = $wpdb->get_results("SELECT * FROM $table WHERE status='pending' ORDER BY id LIMIT 10");
    foreach ($rows as $row) {
        if ($wpdb->update($table, array('status' => 'sending', 'updated_at' => time()), array('id' => $row->id, 'status' => 'pending')) !== 1) { continue; }
        try { $result = tarhan_sms_send($row, $config); }
        catch (Throwable $e) { $result = array('status' => 'uncertain', 'error_code' => 'transport'); }
        $result['updated_at'] = time();
        $wpdb->update($table, $result, array('id' => $row->id, 'status' => 'sending'));
    }
}

add_filter('cron_schedules', function ($schedules) { $schedules['tarhan_sms_minute'] = array('interval' => 60, 'display' => 'SMS Тархан'); return $schedules; });
add_action('tarhan_sms_tick', 'tarhan_sms_process');
add_action('init', function () {
    if (get_option('tarhan_sms_schema') === '1' && !wp_next_scheduled('tarhan_sms_tick')) { wp_schedule_event(time() + 60, 'tarhan_sms_minute', 'tarhan_sms_tick'); }
});
add_action('admin_init', function () {
    if (current_user_can('manage_options') && get_option('tarhan_sms_schema') !== '1') { tarhan_sms_install(); }
});
add_action('admin_menu', function () { add_submenu_page('tarhan-testing', 'SMS участникам', 'SMS участникам', 'manage_options', 'tarhan-sms', 'tarhan_sms_page'); });

function tarhan_sms_page()
{
    if (!current_user_can('manage_options')) { wp_die('Недостаточно прав.', '', array('response' => 403)); }
    $config = tarhan_sms_config();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('tarhan_sms_settings');
        $config['provider'] = ($_POST['provider'] ?? '') === 'smsru' ? 'smsru' : '';
        $config['enabled'] = isset($_POST['enabled']) && $config['provider'] !== '';
        $config['sender'] = sanitize_text_field(wp_unslash($_POST['sender'] ?? ''));
        $key = trim(wp_unslash($_POST['api_key'] ?? ''));
        if ($key !== '' && !defined('TARHAN_SMS_API_KEY')) { $config['key'] = sanitize_text_field($key); }
        $saved = $config; if (defined('TARHAN_SMS_API_KEY')) { unset($saved['key']); }
        update_option('tarhan_sms_config', $saved, false);
        echo '<div class="notice notice-success"><p>Настройки сохранены.</p></div>';
    }
    echo '<div class="wrap"><h1>SMS участникам</h1><p>Три сообщения: регистрация, тест не пройден, тест пройден. Получатель — личный телефон ученика.</p>';
    echo '<p>Отправка начнётся только после выбора сервиса, добавления ключа и включения. Сообщения старше суток не отправляются. Длинные тексты могут тарифицироваться как несколько SMS.</p><form method="post">';
    wp_nonce_field('tarhan_sms_settings');
    echo '<p><label>Сервис <select name="provider"><option value="">Не выбран</option><option value="smsru" ' . selected($config['provider'] ?? '', 'smsru', false) . '>SMS.ru</option></select></label></p>';
    echo '<p><label>API-ключ <input type="password" name="api_key" value="" autocomplete="new-password"></label> ' . (!empty($config['key']) ? 'Ключ сохранён. Пустое поле оставляет его прежним.' : 'Ключ не задан.') . '</p>';
    echo '<p><label>Имя отправителя <input name="sender" value="' . esc_attr($config['sender'] ?? '') . '"></label> Согласованное с сервисом; пустое — отправитель по умолчанию.</p>';
    echo '<p><label><input type="checkbox" name="enabled" ' . checked(!empty($config['enabled']), true, false) . '> Включить отправку SMS (включая очередь за последние сутки)</label></p>';
    submit_button('Сохранить'); echo '</form><h2>Тексты сообщений</h2>';
    foreach (array('registration', 'failed', 'passed') as $kind) { echo '<p>' . esc_html(tarhan_sms_message($kind, 2)) . '</p>'; }
    echo '<h2>Последние сообщения</h2><p>«Принято сервисом» не означает доставку на телефон. Неопределённый статус проверьте в кабинете SMS-сервиса; автоматического повтора нет.</p>';
    global $wpdb; $table = tarhan_sms_table();
    $labels = array('pending' => 'В очереди', 'sending' => 'Отправляется', 'accepted' => 'Принято сервисом', 'failed' => 'Ошибка сервиса', 'uncertain' => 'Требует проверки', 'expired' => 'Устарело');
    echo '<table class="widefat striped"><thead><tr><th>Дата</th><th>Участник</th><th>Телефон</th><th>Событие</th><th>Статус</th><th>Код / ID сервиса</th></tr></thead><tbody>';
    foreach ($wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 50") as $row) {
        echo '<tr><td>' . esc_html(wp_date('d.m.Y H:i', $row->created_at)) . '</td><td>' . (int) $row->user_id . '</td><td>••••' . esc_html(substr($row->phone, -4)) . '</td><td>' . esc_html(str_starts_with($row->event_key, 'registration:') ? 'Регистрация' : 'Завершение теста') . '</td><td>' . esc_html($labels[$row->status] ?? $row->status) . '</td><td>' . esc_html($row->error_code ?: $row->provider_id) . '</td></tr>';
    }
    echo '</tbody></table><p>Обработка очереди зависит от фоновых задач WordPress. На сервере настройте запуск WP-Cron каждую минуту.</p></div>';
}
