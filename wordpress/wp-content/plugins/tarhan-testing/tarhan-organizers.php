<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/tarhan-organizer-xlsx.php';

function tarhan_organizer_columns()
{
    static $columns;
    if ($columns === null) {
        $columns = json_decode(file_get_contents(__DIR__ . '/data/organizer-columns.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    return $columns;
}

function tarhan_organizer_require_access()
{
    if (!current_user_can('manage_options')) { wp_die('Доступ только для организаторов.', '', array('response' => 403)); }
}

function tarhan_organizer_filters($input)
{
    $out = array();
    foreach (array('search', 'region', 'result', 'essay', 'consent', 'season') as $key) {
        $out[$key] = isset($input[$key]) && is_string($input[$key]) ? sanitize_text_field(wp_unslash($input[$key])) : '';
    }
    foreach (array('result' => array('', 'none', 'passed', 'failed'), 'essay' => array('', 'yes', 'no'), 'consent' => array('', 'yes', 'no')) as $key => $values) {
        if (!in_array($out[$key], $values, true)) { $out[$key] = ''; }
    }
    return $out;
}

function tarhan_organizer_url($id = 0)
{
    return add_query_arg(array_filter(array('page' => 'tarhan-organizers', 'participant' => (int) $id)), admin_url('admin.php'));
}

function tarhan_organizer_essay_url($id, $season)
{
    return add_query_arg(array('action' => 'tarhan_organizer_essay', 'participant' => (int) $id, 'season' => (string) $season), admin_url('admin-post.php'));
}

function tarhan_organizer_attempt_state($attempt)
{
    if ($attempt->status === 'finished') { return 'Завершена'; }
    return (int) $attempt->expires_at <= time() ? 'Время истекло, результат ещё не сохранён' : 'В процессе';
}

function tarhan_organizer_row($user, $profile, $consent, $attempts, $essay)
{
    $profile = is_array($profile) ? $profile : array();
    $address = isset($profile['address']) && is_array($profile['address']) ? $profile['address'] : array();
    $row = array_fill_keys(array_column(tarhan_organizer_columns(), 0), null);
    foreach (array('last_name', 'first_name', 'middle_name', 'phone', 'birth_date', 'apartment', 'school_form', 'school_type', 'school_number', 'education', 'parent_last_name', 'parent_first_name', 'parent_middle_name', 'parent_phone', 'teacher_name', 'teacher_phone') as $key) {
        $row[$key] = isset($profile[$key]) && is_scalar($profile[$key]) ? (string) $profile[$key] : '';
    }
    foreach (array('region', 'district', 'locality', 'street', 'house') as $key) { $row[$key] = is_scalar($address[$key] ?? null) ? (string) $address[$key] : ''; }
    $row['id'] = (string) $user->ID;
    $row['email'] = $user->user_email;
    if (!$row['last_name'] && !$row['first_name']) { $row['last_name'] = $user->display_name; }
    $row['full_name'] = trim(implode(' ', array_filter(array($row['last_name'], $row['first_name'], $row['middle_name']), static function ($value) { return trim($value) !== ''; })));
    $row['parent_full_name'] = trim(implode(' ', array_filter(array($row['parent_last_name'], $row['parent_first_name'], $row['parent_middle_name']), static function ($value) { return trim($value) !== ''; })));
    $row['minor'] = array_key_exists('minor', $profile) ? ($profile['minor'] ? 'Да' : 'Нет') : '';
    $row['registered_at'] = !empty($profile['registered_at']) ? (int) $profile['registered_at'] : strtotime($user->user_registered . ' UTC');
    // Keep the saved year; legacy profiles use their registration date, never the export date.
    $year = $profile['participation_year'] ?? null;
    $row['participation_year'] = is_scalar($year) && preg_match('/^[1-9][0-9]{3}$/D', (string) $year)
        ? (int) $year : ($row['registered_at'] ? (int) wp_date('Y', $row['registered_at']) : null);
    $row['rules_accepted_at'] = !empty($profile['rules_accepted_at']) ? (int) $profile['rules_accepted_at'] : null;
    $last = tarhan_latest_finished_attempt($attempts);
    $rules = tarhan_test_rules();
    $row['score'] = $last && $last->score !== null ? (int) $last->score : null;
    $row['percent'] = $row['score'] === null ? null : $row['score'] / $rules['questions'];
    $row['last_number'] = $last ? (int) $last->attempt_number : null;
    $row['_result'] = $row['score'] === null ? 'none' : ($row['score'] >= $rules['pass_score'] ? 'passed' : 'failed');
    $row['result'] = array('none' => 'Нет результата', 'passed' => 'Тест пройден', 'failed' => 'Тест не пройден')[$row['_result']];
    $row['attempts_used'] = count($attempts);
    $row['attempts_remaining'] = $row['_result'] === 'passed' ? 0 : max(0, $rules['attempts'] - count($attempts));
    $row['current_state'] = 'Нет активной попытки';
    foreach ($attempts as $attempt) {
        $state = tarhan_organizer_attempt_state($attempt);
        if ($attempt->status !== 'finished') { $row['current_state'] = $state; }
        $row['attempt_' . (int) $attempt->attempt_number] = $state .
            ($attempt->score !== null ? '; баллы: ' . (int) $attempt->score : '') .
            '; начало: ' . wp_date('d.m.Y H:i', (int) $attempt->started_at) .
            ($attempt->finished_at ? '; завершение: ' . wp_date('d.m.Y H:i', (int) $attempt->finished_at) : '');
    }
    $file = $essay ? json_decode($essay->file, true) : null;
    $row['essay_state'] = $essay ? ($file ? 'Отправлен файл' : 'Отправлен текст') : 'Не отправлено';
    $row['essay_date'] = $essay ? (int) $essay->submitted_at : null;
    $row['essay_name'] = $essay ? (is_array($file) ? ($file['name'] ?? 'Файл эссе') : 'Эссе участника.docx') : '';
    $row['essay_url'] = $essay ? tarhan_organizer_essay_url($user->ID, $profile['season_id'] ?? '') : '';
    $row['_essay'] = $essay ? 'yes' : 'no';
    $has_consent = is_array($consent) && !empty($consent['key']);
    $row['_consent'] = $has_consent ? 'yes' : 'no';
    $row['consent_state'] = $has_consent ? 'Загружено' : (!empty($profile['minor']) ? 'Не загружено' : 'Нет файла');
    $row['consent_name'] = $has_consent ? ($consent['name'] ?? '') : '';
    $row['consent_url'] = $has_consent ? tarhan_organizer_url($user->ID) . '#consent' : '';
    $row['profile_url'] = tarhan_organizer_url($user->ID);
    return $row;
}

function tarhan_organizer_matches($row, $filters)
{
    if (!empty($filters['season']) && ($row['_season_id'] ?: 'legacy') !== $filters['season']) { return false; }
    foreach (array('result', 'essay', 'consent') as $key) {
        if (!empty($filters[$key]) && $row['_' . $key] !== $filters[$key]) { return false; }
    }
    if (!empty($filters['region']) && mb_stripos($row['region'], $filters['region']) === false) { return false; }
    if (!empty($filters['search'])) {
        $values = array_intersect_key($row, array_flip(array('id', 'last_name', 'first_name', 'middle_name', 'email', 'phone', 'locality', 'school_form', 'school_type', 'school_number')));
        if (mb_stripos(implode(' ', $values), $filters['search']) === false) { return false; }
    }
    return true;
}

// One row per enrollment: a participant may appear in several seasons.
function tarhan_organizer_rows($filters = array(), $participant = 0)
{
    tarhan_organizer_require_access();
    global $wpdb;
    $after = 0;
    do {
        $only = $participant ? $wpdb->prepare(' AND u.ID = %d', $participant) : '';
        $users = $wpdb->get_results($wpdb->prepare("SELECT u.ID,u.user_email,u.display_name,u.user_registered,e.id AS enrollment_id,e.season_id,e.season_name,e.profile,e.consent FROM {$wpdb->prefix}tarhan_enrollments e JOIN {$wpdb->users} u ON u.ID=e.user_id WHERE e.id > %d $only ORDER BY e.id LIMIT 200", $after));
        if ($wpdb->last_error) { throw new RuntimeException('Не удалось прочитать участников.'); }
        if (!$users) { break; }
        $ids = implode(',', array_map('intval', array_column($users, 'ID')));
        $attempts = $wpdb->get_results("SELECT user_id,season_id,attempt_number,status,started_at,expires_at,finished_at,score FROM {$wpdb->prefix}tarhan_season_attempts WHERE user_id IN ($ids) ORDER BY attempt_number");
        if ($wpdb->last_error) { throw new RuntimeException('Не удалось прочитать попытки.'); }
        $grouped = array();
        foreach ($attempts as $attempt) { $grouped[$attempt->user_id . ':' . $attempt->season_id][] = $attempt; }
        $essays = $wpdb->get_results("SELECT user_id,season_id,file,submitted_at FROM {$wpdb->prefix}tarhan_season_essays WHERE user_id IN ($ids)");
        if ($wpdb->last_error) { throw new RuntimeException('Не удалось прочитать эссе.'); }
        $essay_map = array();
        foreach ($essays as $essay) { $essay_map[$essay->user_id . ':' . $essay->season_id] = $essay; }
        foreach ($users as $user) {
            $after = (int) $user->enrollment_id;
            $key = $user->ID . ':' . $user->season_id;
            $row = tarhan_organizer_row($user, json_decode($user->profile, true), json_decode($user->consent, true), $grouped[$key] ?? array(), $essay_map[$key] ?? null);
            $row['_season_id'] = $user->season_id;
            $row['season_name'] = $user->season_name;
            $row['profile_url'] = add_query_arg('season', $user->season_id ?: 'legacy', tarhan_organizer_url($user->ID));
            if ($row['essay_url']) { $row['essay_url'] = tarhan_organizer_essay_url($user->ID, $user->season_id); }
            if ($row['consent_url']) { $row['consent_url'] = $row['profile_url'] . '#consent'; }
            if (tarhan_organizer_matches($row, $filters)) { yield $row; }
        }
    } while (count($users) === 200);
}

function tarhan_organizer_display($value, $type)
{
    if ($value === null || $value === '') { return '—'; }
    if ($type === 'datetime') { return wp_date('d.m.Y H:i', (int) $value); }
    if ($type === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { return implode('.', array_reverse(explode('-', $value))); }
    if ($type === 'percent') { return number_format_i18n($value * 100, 1) . '%'; }
    return (string) $value;
}

function tarhan_organizer_detail($id)
{
    // Links exported before seasons existed still open the participant's oldest enrollment.
    $season_filter = is_string($_GET['season'] ?? null) ? sanitize_text_field(wp_unslash($_GET['season'])) : '';
    $rows = iterator_to_array(tarhan_organizer_rows(array('season' => $season_filter), $id));
    if (!$rows) { echo '<p>Участник не найден.</p>'; return; }
    $row = $rows[0];
    $season = $row['_season_id'];
    echo '<p><a href="' . esc_url(tarhan_organizer_url()) . '">← Все участники</a></p><h2>' . esc_html(trim($row['last_name'] . ' ' . $row['first_name'] . ' ' . $row['middle_name'])) . '</h2>';
    echo '<table class="widefat striped tarhan-organizer-detail"><tbody>';
    foreach (tarhan_organizer_columns() as $column) {
        if ($column[2] === 'link') { continue; }
        echo '<tr><th scope="row">' . esc_html($column[1]) . '</th><td>' . esc_html(tarhan_organizer_display($row[$column[0]], $column[2])) . '</td></tr>';
    }
    echo '</tbody></table><h2 id="essay">Эссе</h2>';
    $essay = tarhan_get_essay($id, $season);
    if (!$essay) { echo '<p>Эссе пока не отправлено.</p>'; }
    else {
        echo '<p><a class="button button-primary" href="' . esc_url(tarhan_organizer_essay_url($id, $season)) . '">Скачать ' . esc_html($row['essay_name']) . '</a></p>';
        if (!json_decode($essay->file, true)) { echo '<div class="tarhan-organizer-essay">' . esc_html($essay->text) . '</div>'; }
    }
    echo '<h2 id="consent">Согласие</h2>';
    echo $row['_consent'] === 'yes' ? '<a class="button" href="' . esc_url(tarhan_private_url('consent', $id, $season)) . '">Скачать ' . esc_html($row['consent_name']) . '</a>' : '<p>Файл согласия не загружен.</p>';
}

function tarhan_organizer_page()
{
    tarhan_organizer_require_access();
    echo '<div class="wrap tarhan-organizers"><h1>Участники конкурса</h1>';
    try {
        $id = isset($_GET['participant']) && is_scalar($_GET['participant']) ? absint($_GET['participant']) : 0;
        if ($id) { tarhan_organizer_detail($id); echo '</div>'; return; }
        $filters = tarhan_organizer_filters($_GET);
        $page = max(1, isset($_GET['paged']) && is_scalar($_GET['paged']) ? absint($_GET['paged']) : 1);
        $selected = array(); $count = 0;
        foreach (tarhan_organizer_rows($filters) as $row) {
            if ($count >= ($page - 1) * 50 && $count < $page * 50) { $selected[] = $row; }
            $count++;
        }
        echo '<p>Одна строка — участие в конкретном сезоне. Баллы относятся к последней завершённой попытке этого сезона. В карточке доступны анкета, попытки и документы.</p>';
        echo '<form method="get" class="tarhan-organizer-filters"><input type="hidden" name="page" value="tarhan-organizers">';
        echo '<label>Сезон<select name="season"><option value="">Все сезоны</option>';
        $season_choices = array('legacy' => 'До настройки сезонов');
        foreach (tarhan_seasons_state()['items'] as $item) { $season_choices[$item['id']] = $item['name']; }
        foreach ($season_choices as $value => $label) { echo '<option value="' . esc_attr($value) . '"' . selected($filters['season'], $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label>';
        foreach (array('search' => 'Поиск: ФИО, email, телефон, населённый пункт, учреждение', 'region' => 'Регион') as $key => $label) {
            echo '<label>' . esc_html($label) . '<input type="search" name="' . esc_attr($key) . '" value="' . esc_attr($filters[$key]) . '"></label>';
        }
        foreach (array('result' => array('Результат теста', array('' => 'Все', 'none' => 'Нет результата', 'passed' => 'Пройден', 'failed' => 'Не пройден')), 'essay' => array('Эссе', array('' => 'Все', 'yes' => 'Отправлено', 'no' => 'Не отправлено')), 'consent' => array('Файл согласия', array('' => 'Все', 'yes' => 'Загружен', 'no' => 'Нет файла'))) as $key => $config) {
            echo '<label>' . esc_html($config[0]) . '<select name="' . esc_attr($key) . '">';
            foreach ($config[1] as $value => $label) { echo '<option value="' . esc_attr($value) . '"' . selected($filters[$key], $value, false) . '>' . esc_html($label) . '</option>'; }
            echo '</select></label>';
        }
        echo '<button class="button button-primary">Найти</button><a class="button" href="' . esc_url(tarhan_organizer_url()) . '">Сбросить</a></form>';
        echo '<p>Найдено участников: <strong>' . (int) $count . '</strong></p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('tarhan_organizer_export');
        echo '<input type="hidden" name="action" value="tarhan_organizer_export">';
        foreach ($filters as $key => $value) { echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">'; }
        echo '<button class="button">Скачать Excel (.xlsx)</button> <span>Все найденные участники, включая другие страницы списка.</span></form>';
        echo '<div class="tarhan-organizer-scroll" tabindex="0" role="region" aria-label="Таблица участников"><table class="widefat striped"><thead><tr>';
        foreach (array('Участник', 'Контакты', 'Регион / населённый пункт', 'Результат теста', 'Попытки', 'Эссе', 'Согласие') as $label) { echo '<th scope="col">' . esc_html($label) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ($selected as $row) {
            echo '<tr><td><a href="' . esc_url($row['profile_url']) . '">' . esc_html(trim($row['last_name'] . ' ' . $row['first_name'] . ' ' . $row['middle_name'])) . '</a><br>ID ' . esc_html($row['id']) . '<br>Сезон: ' . esc_html($row['season_name']) . '</td>';
            foreach (array($row['email'] . "\n" . $row['phone'], $row['region'] . "\n" . $row['locality'], $row['result'] . ($row['score'] !== null ? "\n" . $row['score'] . ' балл.; ' . tarhan_organizer_display($row['percent'], 'percent') : ''), $row['attempts_used'] . ' начато, ' . $row['attempts_remaining'] . " осталось\n" . $row['current_state']) as $value) { echo '<td>' . nl2br(esc_html($value)) . '</td>'; }
            foreach (array('essay', 'consent') as $key) { echo '<td>' . ($row[$key . '_url'] ? '<a href="' . esc_url($row[$key . '_url']) . '">' . esc_html($row[$key . '_state']) . '</a>' : esc_html($row[$key . '_state'])) . '</td>'; }
            echo '</tr>';
        }
        if (!$selected) { echo '<tr><td colspan="7">Участники не найдены.</td></tr>'; }
        echo '</tbody></table></div>';
        echo wp_kses_post(paginate_links(array('base' => add_query_arg(array_merge($filters, array('paged' => '%#%')), tarhan_organizer_url()), 'format' => '', 'current' => $page, 'total' => max(1, (int) ceil($count / 50)))));
    } catch (Throwable $error) { echo '<div class="notice notice-error"><p>Не удалось загрузить таблицу участников. Проверьте доступность базы данных.</p></div>'; }
    echo '</div>';
}

add_action('admin_menu', function () { add_submenu_page('tarhan-testing', 'Участники конкурса', 'Участники', 'manage_options', 'tarhan-organizers', 'tarhan_organizer_page'); });
add_action('admin_post_tarhan_organizer_essay', function () {
    tarhan_organizer_require_access();
    $id = absint($_GET['participant'] ?? 0);
    $season = is_string($_GET['season'] ?? null) ? sanitize_text_field(wp_unslash($_GET['season'])) : '';
    if (!$id) { wp_die('Эссе не найдено.', '', array('response' => 404)); }
    tarhan_send_essay_document($id, $season);
});
add_action('admin_post_nopriv_tarhan_organizer_essay', function () { auth_redirect(); exit; });
add_action('admin_enqueue_scripts', function ($hook) {
    if (str_ends_with($hook, '_page_tarhan-organizers')) { wp_enqueue_style('tarhan-organizers', plugins_url('assets/organizers.css', __FILE__), array(), (string) filemtime(__DIR__ . '/assets/organizers.css')); }
});
