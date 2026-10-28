<?php
if (!defined('ABSPATH')) { exit; }

// A single option keeps the active season and admission switch consistent.
function tarhan_seasons_state()
{
    global $wpdb;
    $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'tarhan_seasons'));
    if ($wpdb->last_error) { throw new RuntimeException('Не удалось прочитать сезоны.'); }
    if ($raw === null) { return array('items' => array(), 'current_id' => '', 'registration_open' => false, 'configured' => false,
        'home_status_title' => 'Регистрация скоро начнется', 'home_status_date' => 'начало ожидается 1 ноября', 'revision' => 0); }
    $state = maybe_unserialize($raw);
    if (!is_array($state) || !isset($state['items'], $state['revision']) || !is_array($state['items'])) { throw new RuntimeException('Повреждены настройки сезонов.'); }
    return $state;
}

function tarhan_seasons_lock_name()
{
    global $wpdb;
    return 'tarhan_seasons_' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix), 0, 45);
}

function tarhan_registration_season()
{
    try { $state = tarhan_seasons_state(); }
    catch (Throwable $error) { return tarhan_attempt_error('season_unavailable', 'Регистрация временно недоступна. Попробуйте позже.', 503); }
    // Existing registrations keep working until an organizer explicitly enables season control.
    if (empty($state['configured'])) { return null; }
    $season = $state['items'][$state['current_id']] ?? null;
    if (!$season || empty($state['registration_open'])) { return tarhan_attempt_error('registration_closed', 'Регистрация на конкурс сейчас закрыта.', 403); }
    return $season;
}

function tarhan_seasons_save($input)
{
    if (!current_user_can('manage_options')) { return new WP_Error('season_access', 'Нет доступа.'); }
    $nonce = $input['_wpnonce'] ?? null;
    if (!is_string($nonce) || !wp_verify_nonce($nonce, 'tarhan_seasons_save')) { return new WP_Error('season_nonce', 'Обновите страницу настроек и повторите сохранение.'); }
    global $wpdb;
    $lock = tarhan_seasons_lock_name();
    if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== '1') { return new WP_Error('season_busy', 'Настройки сейчас сохраняются. Повторите позже.'); }
    try {
        $state = tarhan_seasons_state();
        $revision = $input['revision'] ?? null;
        if (!is_scalar($revision) || (string) $revision !== (string) $state['revision']) { return new WP_Error('season_stale', 'Настройки уже изменились в другой вкладке. Обновите страницу.'); }
        if (($input['operation'] ?? '') === 'create') {
            $name = isset($input['name']) && is_string($input['name']) ? sanitize_text_field($input['name']) : '';
            $name = preg_replace('/\s+/u', ' ', trim($name));
            if (!$name || mb_strlen($name) > 100) { return new WP_Error('season_name', 'Введите название сезона длиной до 100 символов.'); }
            $normalize = static function ($value) { return mb_strtolower(preg_replace('/\s*[-–—]\s*/u', '-', $value)); };
            foreach ($state['items'] as $item) {
                if ($normalize($item['name']) === $normalize($name)) { return new WP_Error('season_duplicate', 'Сезон с таким названием уже существует.'); }
            }
            $id = wp_generate_uuid4();
            $state['items'][$id] = array('id' => $id, 'name' => $name, 'created_at' => time());
        } elseif (($input['operation'] ?? '') === 'settings') {
            $id = $input['current_id'] ?? null;
            if (!is_string($id) || ($id !== '' && !isset($state['items'][$id]))) { return new WP_Error('season_unknown', 'Выберите сезон из списка.'); }
            $open = ($input['registration_open'] ?? '') === '1';
            if ($open && !$id) { return new WP_Error('season_required', 'Для открытия регистрации выберите текущий сезон.'); }
            $state['current_id'] = $id;
            $state['registration_open'] = $open;
            $status_defaults = array('home_status_title' => 'Регистрация скоро начнется', 'home_status_date' => 'начало ожидается 1 ноября');
            foreach ($status_defaults as $field => $default) {
                if (!array_key_exists($field, $input)) { $state[$field] = $state[$field] ?? $default; continue; }
                $value = is_string($input[$field]) ? sanitize_text_field($input[$field]) : '';
                $value = preg_replace('/\s+/u', ' ', trim($value));
                if (!$value || mb_strlen($value) > 100) { return new WP_Error('season_status', 'Заполните тексты статуса на главной странице (до 100 символов).'); }
                $state[$field] = $value;
            }
            $state['configured'] = true;
        } else { return new WP_Error('season_operation', 'Неизвестное действие.'); }
        $state['revision']++;
        // State is read directly from DB, so discard a possibly stale request-local option cache before writing.
        wp_cache_delete('tarhan_seasons', 'options');
        if (!update_option('tarhan_seasons', $state, false)) { return new WP_Error('season_storage', 'Не удалось сохранить настройки.'); }
        return true;
    } catch (Throwable $error) { return new WP_Error('season_storage', 'Не удалось сохранить настройки сезонов.'); }
    finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
}

function tarhan_seasons_admin()
{
    if (!current_user_can('manage_options')) { wp_die('Нет доступа.', '', array('response' => 403)); }
    echo '<div class="wrap"><h1>Сезоны конкурса</h1>';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $result = tarhan_seasons_save(wp_unslash($_POST));
        echo '<div class="notice ' . (is_wp_error($result) ? 'notice-error' : 'notice-success') . '"><p>' . esc_html(is_wp_error($result) ? $result->get_error_message() : 'Настройки сохранены.') . '</p></div>';
    }
    try { $state = tarhan_seasons_state(); }
    catch (Throwable $error) { echo '<div class="notice notice-error"><p>Настройки сезонов недоступны.</p></div></div>'; return; }
    echo '<p>Создайте сезон, затем выберите его текущим и сохраните настройки регистрации. Создание сезона само по себе не открывает регистрацию.</p>';
    echo '<div class="notice notice-info inline"><p>В каждом сезоне участник подаёт новую анкету с прежним аккаунтом. Попытки, эссе и согласия хранятся отдельно. После переключения результаты прежнего сезона остаются в таблице участников; вернуться к ним можно через фильтр сезона.</p></div>';
    if (empty($state['configured'])) { echo '<p><strong>Управление сезонами ещё не включено.</strong> Пока вы не сохраните настройки ниже, регистрация работает в прежнем режиме.</p>'; }
    echo '<h2>Текущий сезон и регистрация</h2><form method="post" action="' . esc_url(admin_url('admin.php?page=tarhan-seasons')) . '">';
    wp_nonce_field('tarhan_seasons_save');
    echo '<input type="hidden" name="operation" value="settings"><input type="hidden" name="revision" value="' . (int) $state['revision'] . '">';
    echo '<p><label for="tarhan-current-season">Текущий сезон</label><br><select id="tarhan-current-season" name="current_id"><option value="">Не выбран — регистрация закрыта</option>';
    foreach ($state['items'] as $item) { echo '<option value="' . esc_attr($item['id']) . '"' . selected($state['current_id'], $item['id'], false) . '>' . esc_html($item['name']) . '</option>'; }
    echo '</select></p><p><label><input type="checkbox" name="registration_open" value="1"' . checked(!empty($state['registration_open']), true, false) . '> Открыть регистрацию на выбранный сезон</label></p>';
    echo '<h3>Статус на главной странице</h3>';
    echo '<p><label for="tarhan-home-status-title">Основной статус</label><br><input class="regular-text" id="tarhan-home-status-title" name="home_status_title" maxlength="100" required value="' . esc_attr($state['home_status_title'] ?? 'Регистрация скоро начнется') . '"></p>';
    echo '<p><label for="tarhan-home-status-date">Дополнительная строка справа</label><br><input class="regular-text" id="tarhan-home-status-date" name="home_status_date" maxlength="100" required value="' . esc_attr($state['home_status_date'] ?? 'начало ожидается 1 ноября') . '"></p>';
    submit_button('Сохранить настройки'); echo '</form><h2>Создать сезон</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=tarhan-seasons')) . '">';
    wp_nonce_field('tarhan_seasons_save');
    echo '<input type="hidden" name="operation" value="create"><input type="hidden" name="revision" value="' . (int) $state['revision'] . '"><p><label>Название сезона<br><input class="regular-text" name="name" required maxlength="100" placeholder="Например, 2026–2027"></label></p>';
    submit_button('Создать сезон', 'secondary'); echo '</form><h2>Все сезоны</h2><table class="widefat striped"><thead><tr><th>Сезон</th><th>Статус</th><th>Регистрация</th></tr></thead><tbody>';
    foreach ($state['items'] as $item) {
        $current = $state['current_id'] === $item['id'];
        echo '<tr><td>' . esc_html($item['name']) . '</td><td>' . ($current ? 'Текущий' : 'Не текущий') . '</td><td>' . ($current && $state['registration_open'] ? 'Открыта' : 'Закрыта') . '</td></tr>';
    }
    if (!$state['items']) { echo '<tr><td colspan="3">Сезоны пока не созданы.</td></tr>'; }
    echo '</tbody></table></div>';
}
add_action('admin_menu', function () { add_submenu_page('tarhan-testing', 'Сезоны конкурса', 'Сезоны', 'manage_options', 'tarhan-seasons', 'tarhan_seasons_admin'); });
