<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/tarhan-eassay.php';

function tarhan_essay_rules()
{
    $saved = get_option('tarhan_essay_settings', array());
    return array('open' => !empty($saved['open']), 'topic' => $saved['topic'] ?? '',
        'max_length' => (int) ($saved['max_length'] ?? 0), 'max_bytes' => (int) ($saved['max_bytes'] ?? 0),
        'extensions' => array('pdf', 'doc', 'docx', 'txt'));
}

function tarhan_install_essays()
{
    if (get_option('tarhan_essay_schema') === '1') { return true; }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = $wpdb->prefix . 'tarhan_essays';
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        user_id bigint(20) unsigned NOT NULL,
        text longtext NOT NULL,
        file longtext NOT NULL,
        submitted_at bigint(20) unsigned NOT NULL,
        PRIMARY KEY  (user_id)
    ) ENGINE=InnoDB $charset;");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
        return tarhan_attempt_error('essay_storage', 'Хранилище эссе недоступно.', 503);
    }
    update_option('tarhan_essay_schema', '1', false);
    return true;
}

function tarhan_get_essay($id, $season = null)
{
    global $wpdb;
    $season = $season ?? tarhan_current_season_id();
    if ($season === null) { return null; }
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tarhan_season_essays WHERE user_id = %d AND season_id = %s", $id, $season));
}

function tarhan_essay_file($id, $season = null)
{
    $essay = tarhan_get_essay($id, $season);
    return $essay ? json_decode($essay->file, true) : null;
}

function tarhan_essay_response($id, $test, $season)
{
    $row = tarhan_get_essay($id, $season);
    $file = $row ? json_decode($row->file, true) : null;
    $rules = tarhan_essay_rules();
    return array('rules' => $rules, 'allowed' => !empty($test['essay_allowed']),
        'can_submit' => !$row && !empty($test['essay_allowed']) && $rules['open'] && $rules['topic'] !== '' && $rules['max_length'] > 0 && $rules['max_bytes'] > 0,
        'submission' => $row ? array('text' => $row->text, 'submitted_at' => (int) $row->submitted_at,
            'file' => $file ? array('name' => $file['name'], 'url' => tarhan_private_url('essay', $id, $season)) : null) : null);
}

function tarhan_essay_command($command, $request)
{
    if (!is_user_logged_in() || !current_user_can('read')) { return tarhan_attempt_error('essay_login', 'Сначала войдите в аккаунт.', 401); }
    $id = get_current_user_id();
    $season = tarhan_enrollment_context($id);
    if (is_wp_error($season)) { return $season; }
    if ($command !== 'status' && ($request->get_body_params()['season_id'] ?? '') !== $season) { return tarhan_attempt_error('season_changed', 'Сезон изменился. Обновите страницу эссе.', 409); }
    if (get_option('tarhan_essay_schema') !== '1') { return tarhan_attempt_error('essay_storage', 'Хранилище эссе ещё не подготовлено.', 503); }
    $test = tarhan_attempt_command('status');
    if (is_wp_error($test)) { return $test; }
    if ($command === 'status') { return tarhan_essay_response($id, $test, $season); }
    // Use the same lock as test mutations: an essay cannot race a new test result.
    global $wpdb;
    $lock = 'tarhan_' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix . ':' . $id), 0, 54);
    if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== '1') { return tarhan_attempt_error('essay_busy', 'Предыдущий запрос ещё обрабатывается.', 409); }
    $file = null;
    $stored = false;
    try {
        // Re-read under the lock; status above has already finalized any expired attempt.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tarhan_season_attempts WHERE user_id = %d AND season_id = %s ORDER BY attempt_number", $id, $season));
        $summary = tarhan_participant_test_summary($rows);
        $test['essay_allowed'] = $summary['essay_allowed'];
        if (tarhan_get_essay($id, $season)) { return tarhan_essay_response($id, $test, $season); }
        if (!$summary['essay_allowed']) { return tarhan_attempt_error('essay_forbidden', 'Сначала успешно пройдите тест.', 403); }
        $rules = tarhan_essay_rules();
        if (!$rules['open'] || !$rules['topic'] || $rules['max_length'] <= 0 || $rules['max_bytes'] <= 0) {
            return tarhan_attempt_error('essay_closed', 'Приём эссе пока закрыт.', 403);
        }
        $input = $request->get_body_params();
        $text = $input['text'] ?? '';
        if (!is_string($text)) { return tarhan_attempt_error('essay_text', 'Введите текст эссе.'); }
        $files = $request->get_file_params();
        $has_file = isset($files['essay_file']) && ($files['essay_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($has_file && trim($text) !== '') { return tarhan_attempt_error('essay_choice', 'Отправьте текст или файл — выберите один способ.'); }
        if ($has_file) {
            $file = tarhan_store_private_upload($files['essay_file'], $rules['extensions'], $rules['max_bytes']);
            if (is_wp_error($file)) { return $file; }
            $text = '';
        } else {
            $validated = tarhan_validate_essay_text($text, $rules['max_length']);
            if ($validated['error']) { return tarhan_attempt_error('essay_text', $validated['error']); }
            $text = $validated['text'];
        }
        if ($wpdb->insert($wpdb->prefix . 'tarhan_season_essays', array('user_id' => $id, 'season_id' => $season, 'text' => $text,
            'file' => wp_json_encode($file), 'submitted_at' => time())) === false) {
            return tarhan_attempt_error('essay_storage', 'Не удалось сохранить эссе. Повторите отправку.', 500);
        }
        $stored = true;
        return tarhan_essay_response($id, $test, $season);
    } finally {
        if (!$stored && is_array($file)) { tarhan_delete_private_file($file); }
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

add_action('rest_api_init', function () {
    foreach (array('status', 'submit') as $command) {
        register_rest_route('tarhan/v1', '/essay/' . $command, array('methods' => $command === 'status' ? 'GET' : 'POST',
            'permission_callback' => 'tarhan_test_permission', 'callback' => function ($request) use ($command) { return tarhan_essay_command($command, $request); }));
    }
});

add_action('admin_init', function () { if (current_user_can('manage_options')) { tarhan_install_essays(); } });
add_action('admin_menu', function () {
    add_submenu_page('tarhan-testing', 'Эссе и регистрация', 'Эссе и регистрация', 'manage_options', 'tarhan-participants', 'tarhan_participants_admin');
});

function tarhan_participants_admin()
{
    if (!current_user_can('manage_options')) { return; }
    $rules = tarhan_essay_rules();
    echo '<div class="wrap"><h1>Эссе и регистрация</h1>';
    if (isset($_GET['saved'])) { echo '<div class="notice notice-success"><p>Настройки сохранены.</p></div>'; }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('tarhan_participant_settings');
    echo '<input type="hidden" name="action" value="tarhan_participant_settings">';
    echo '<h2>Приём эссе</h2><p>Окончательная отправка один раз: замена и черновики пока не предусмотрены. Сохранённые работы не удаляются при последующей пересдаче теста.</p>';
    echo '<p><label>Тема<br><textarea name="topic" rows="3" cols="80">' . esc_textarea($rules['topic']) . '</textarea></label></p>';
    echo '<p><label>Лимит символов <input type="number" min="1" max="1000000" name="max_length" value="' . (int) $rules['max_length'] . '"></label></p>';
    echo '<p><label>Лимит файла в байтах (не больше 10485760) <input type="number" min="1" max="10485760" name="max_bytes" value="' . (int) $rules['max_bytes'] . '"></label></p>';
    echo '<p><label><input type="checkbox" name="open" value="1" ' . checked($rules['open'], true, false) . '> Открыть приём эссе</label></p>';
    echo '<h2>Адресный справочник</h2><p>Регистрация использует локальный справочник регионов и населённых пунктов GeoNames. Ключ не требуется. Улица и дом вводятся вручную.</p><p>Дополнительный ключ DaData для прежнего полного адреса: ' . (tarhan_address_key() ? 'настроен' : 'не настроен; на текущую регистрацию это не влияет') . '.</p>';
    if (!defined('TARHAN_DADATA_KEY')) { echo '<p><label>Новый ключ <input type="password" name="dadata_key" autocomplete="new-password" value=""></label> Оставьте пустым, чтобы сохранить прежний.</p>'; }
    echo '<p>PHP допускает загрузку: ' . esc_html(ini_get('upload_max_filesize')) . ', весь запрос: ' . esc_html(ini_get('post_max_size')) . '. Настройки хостинга должны соответствовать лимиту.</p>';
    submit_button('Сохранить');
    echo '</form><h2>Полученные работы</h2>';
    global $wpdb;
    $page = max(1, absint($_GET['paged'] ?? 1));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT e.*, u.display_name FROM {$wpdb->prefix}tarhan_season_essays e LEFT JOIN {$wpdb->users} u ON e.user_id=u.ID ORDER BY e.submitted_at DESC LIMIT 50 OFFSET %d", ($page - 1) * 50));
    echo '<p>ФИО и сезон указаны по анкете, с которой отправлена работа. Нажмите на имя, чтобы открыть анкету и результаты этого сезона.</p>';
    echo '<table class="widefat striped"><thead><tr><th>ФИО участника</th><th>Сезон</th><th>Сдано</th><th>Эссе</th><th>Согласие</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $file = json_decode($row->file, true);
        $enrollment = tarhan_enrollment($row->user_id, $row->season_id);
        $profile = $enrollment ? json_decode($enrollment->profile, true) : array();
        $names = array();
        foreach (array('last_name', 'first_name', 'middle_name') as $key) {
            if (is_string($profile[$key] ?? null) && trim($profile[$key]) !== '') { $names[] = trim($profile[$key]); }
        }
        $name = $names ? implode(' ', $names) : ($row->display_name ?: 'Участник #' . (int) $row->user_id);
        $season_name = $enrollment && $enrollment->season_name ? $enrollment->season_name : ($row->season_id === '' ? 'До настройки сезонов' : 'Сезон не найден');
        $profile_url = add_query_arg('season', $row->season_id ?: 'legacy', tarhan_organizer_url($row->user_id));
        echo '<tr><td><strong><a href="' . esc_url($profile_url) . '">' . esc_html($name) . '</a></strong><br><small>ID участника: ' . (int) $row->user_id . ($names ? '' : ' · ФИО в анкете не заполнено') . '</small></td><td>' . esc_html($season_name) . '</td><td>' . esc_html(wp_date('d.m.Y H:i', $row->submitted_at)) . '</td><td>';
        if ($file) { echo '<a class="button" href="' . esc_url(tarhan_private_url('essay', $row->user_id, $row->season_id)) . '">Скачать ' . esc_html($file['name']) . '</a>'; }
        else { echo '<p><a class="button" href="' . esc_url(tarhan_private_url('essay', $row->user_id, $row->season_id)) . '">Скачать эссе (.docx)</a></p><details><summary>Предпросмотр текста</summary><div style="white-space:pre-wrap;max-width:800px">' . esc_html($row->text) . '</div></details>'; }
        echo '</td><td>';
        if ($enrollment && json_decode($enrollment->consent, true)) { echo '<a href="' . esc_url(tarhan_private_url('consent', $row->user_id, $row->season_id)) . '">Скачать согласие</a>'; }
        else { echo 'Не загружено'; }
        echo '</td></tr>';
    }
    if (!$rows) { echo '<tr><td colspan="5">Работ пока нет.</td></tr>'; }
    echo '</tbody></table><p>';
    if ($page > 1) { echo '<a href="' . esc_url(add_query_arg('paged', $page - 1)) . '">Назад</a> '; }
    if (count($rows) === 50) { echo '<a href="' . esc_url(add_query_arg('paged', $page + 1)) . '">Далее</a>'; }
    echo '</p></div>';
}

add_action('admin_post_tarhan_participant_settings', function () {
    if (!current_user_can('manage_options')) { wp_die('Нет доступа.', '', array('response' => 403)); }
    check_admin_referer('tarhan_participant_settings');
    $topic = is_string($_POST['topic'] ?? null) ? sanitize_textarea_field(wp_unslash($_POST['topic'])) : '';
    $length = min(1000000, absint($_POST['max_length'] ?? 0));
    $bytes = min(10485760, absint($_POST['max_bytes'] ?? 0));
    $open = isset($_POST['open']);
    if ($open && (!$topic || !$length || !$bytes)) { wp_die('Для открытия укажите тему и оба лимита. Вернитесь к настройкам.'); }
    update_option('tarhan_essay_settings', array('open' => $open, 'topic' => $topic, 'max_length' => $length, 'max_bytes' => $bytes), false);
    if (!defined('TARHAN_DADATA_KEY') && is_string($_POST['dadata_key'] ?? null) && trim($_POST['dadata_key'])) {
        update_option('tarhan_dadata_key', sanitize_text_field(wp_unslash($_POST['dadata_key'])), false);
    }
    wp_safe_redirect(admin_url('admin.php?page=tarhan-participants&saved=1'));
    exit;
});
