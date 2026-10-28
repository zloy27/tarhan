<?php
$complete = false;
register_shutdown_function(function () use (&$complete) { if (!$complete) { exit(1); } });
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (!function_exists('tarhan_organizer_rows')) { require_once dirname(__DIR__) . '/wordpress/wp-content/plugins/tarhan-testing/tarhan-organizers.php'; }
function organizer_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
global $wpdb;
organizer_check(wp_parse_url(home_url(), PHP_URL_HOST) === 'tarhan-wp.local' && !wp_using_ext_object_cache(), 'Local transactional site required');
foreach (array($wpdb->users, $wpdb->usermeta, $wpdb->prefix . 'tarhan_enrollments', $wpdb->prefix . 'tarhan_season_attempts', $wpdb->prefix . 'tarhan_season_essays') as $table) {
    $info = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
    organizer_check($info && strtoupper($info->Engine) === 'INNODB', 'Transaction required');
}
$admin = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
organizer_check((bool) $admin, 'Administrator missing');
$old_user = get_current_user_id(); $old_get = $_GET; $old_post = $_POST; $old_request = $_REQUEST; $old_method = $_SERVER['REQUEST_METHOD'] ?? '';
$die_filter = static function () { return static function ($message, $title = '', $args = array()) { throw new RuntimeException('DENIED:' . ($args['response'] ?? 403)); }; };
add_filter('wp_die_handler', $die_filter);
$token = 'organizer_fixture_' . bin2hex(random_bytes(6));
$ids = array(); $now = time();
$out = getenv('TARHAN_ORGANIZER_OUTPUT') ?: sys_get_temp_dir() . '/tarhan-organizer-fixture.xlsx';
$wpdb->query('START TRANSACTION');
try {
    for ($i = 0; $i < 205; $i++) {
        organizer_check($wpdb->insert($wpdb->users, array('user_login' => $token . '_' . $i, 'user_pass' => '!', 'user_email' => $token . '_' . $i . '@example.invalid', 'user_registered' => gmdate('Y-m-d H:i:s', $now), 'display_name' => 'Тестовый участник ' . $i)) !== false, 'User fixture');
        $id = (int) $wpdb->insert_id; $ids[] = $id;
        $profile = array('first_name' => 'Иван', 'last_name' => 'Тестовый ' . $i, 'middle_name' => 'Иванович', 'phone' => '+79990000000', 'birth_date' => '2009-01-02', 'minor' => true, 'registered_at' => $now, 'rules_accepted_at' => $now,
            'school_number' => '001', 'school_type' => 'Школа', 'education' => '10 класс', 'address' => array('region' => 'Тестовый регион', 'locality' => 'Тестовый город', 'house' => '001А'));
        if ($i === 0) { $profile['last_name'] = '=1+1'; $profile['middle_name'] = '<script>alert(1)</script> _x0041_'; }
        $wpdb->insert($wpdb->usermeta, array('user_id' => $id, 'meta_key' => 'tarhan_profile', 'meta_value' => serialize($profile)));
        organizer_check(tarhan_store_enrollment($id, $profile, null), 'Enrollment fixture');
    }
    $add_attempt = static function ($id, $number, $score, $active = false) use ($wpdb, $now) {
        organizer_check($wpdb->insert($wpdb->prefix . 'tarhan_season_attempts', array('user_id' => $id, 'attempt_number' => $number, 'request_key' => 'fixture_' . $number, 'status' => $active ? 'in_progress' : 'finished', 'started_at' => $now - 3000, 'expires_at' => $now - 600, 'finished_at' => $active ? null : $now - 1000, 'questions' => '[]', 'answers' => '{}', 'score' => $score, 'finish_reason' => $active ? '' : 'submit')) !== false, 'Attempt fixture');
    };
    $add_attempt($ids[0], 1, 40); $add_attempt($ids[0], 2, 0); $add_attempt($ids[0], 3, null, true);
    $add_attempt($ids[1], 1, 25);
    $wpdb->insert($wpdb->prefix . 'tarhan_season_essays', array('user_id' => $ids[0], 'text' => '<script>essay</script>\nТестовый текст', 'file' => '', 'submitted_at' => $now));
    $file = array('key' => str_repeat('a', 48) . '.pdf', 'name' => 'Тестовый документ.pdf');
    $wpdb->insert($wpdb->prefix . 'tarhan_season_essays', array('user_id' => $ids[1], 'text' => '', 'file' => wp_json_encode($file), 'submitted_at' => $now));
    $wpdb->update($wpdb->prefix . 'tarhan_enrollments', array('consent' => wp_json_encode($file)), array('user_id' => $ids[1], 'season_id' => ''));
    $wpdb->insert($wpdb->usermeta, array('user_id' => $ids[1], 'meta_key' => 'tarhan_consent_file', 'meta_value' => serialize($file)));
    wp_set_current_user((int) $admin[0]);
    $filters = tarhan_organizer_filters(array('search' => $token));
    $rows = iterator_to_array(tarhan_organizer_rows($filters));
    organizer_check(count($rows) === 205 && count(array_unique(array_column($rows, 'id'))) === 205, 'Batching or duplicate rows');
    organizer_check($rows[0]['participation_year'] === (int) wp_date('Y', $now), 'Legacy registration year missing');
    $year_user = (object) array('ID' => $ids[0], 'user_email' => 'fixture@example.invalid', 'display_name' => 'Fixture', 'user_registered' => '2023-06-01 12:00:00');
    $year_row = tarhan_organizer_row($year_user, array(), null, array(), null);
    organizer_check($year_row['participation_year'] === 2023, 'Year incorrectly follows export date');
    $year_row = tarhan_organizer_row($year_user, array('participation_year' => 2024), null, array(), null);
    organizer_check($year_row['participation_year'] === 2024, 'Saved participation year ignored');
    organizer_check($rows[0]['score'] === 0 && $rows[0]['last_number'] === 2 && $rows[0]['attempts_used'] === 3 && $rows[0]['attempts_remaining'] === 0 && $rows[0]['_result'] === 'failed', 'Latest finished, zero or attempts incorrect');
    organizer_check($rows[2]['score'] === null && $rows[2]['percent'] === null && $rows[2]['_result'] === 'none', 'No result became zero');
    organizer_check(str_contains($rows[0]['current_state'], 'ещё не сохранён') && $rows[0]['_essay'] === 'yes' && str_contains($rows[0]['essay_url'], 'tarhan_organizer_essay') && str_ends_with($rows[0]['essay_name'], '.docx'), 'Expired state or downloadable text essay');
    organizer_check(!isset($rows[0]['user_pass'], $rows[0]['questions'], $rows[0]['answers']), 'Private fields included');
    foreach (array('passed' => 1, 'failed' => 1, 'none' => 203) as $result => $expected) { organizer_check(count(iterator_to_array(tarhan_organizer_rows(array_merge($filters, array('result' => $result))))) === $expected, 'Result filter'); }
    organizer_check(count(iterator_to_array(tarhan_organizer_rows(array_merge($filters, array('essay' => 'yes'))))) === 2, 'Essay filter');
    organizer_check(count(iterator_to_array(tarhan_organizer_rows(array_merge($filters, array('consent' => 'yes'))))) === 1, 'Consent filter');
    organizer_check(count(iterator_to_array(tarhan_organizer_rows(array_merge($filters, array('region' => 'другой'))))) === 0, 'Region filter');
    organizer_check(tarhan_organizer_matches($rows[1], array('search' => 'тестовый город')), 'Russian search');
    echo "PASS: 205 unique participants across batches, latest result, zero/null, attempts and all filters\n";
    $_GET = array('search' => $token, 'paged' => 2);
    ob_start(); tarhan_organizer_page(); $html = ob_get_clean();
    organizer_check(substr_count($html, '<tr><td><a href=') === 50 && str_contains($html, '205') && str_contains($html, 'tarhan_organizer_export'), 'Pagination or export form');
    if (getenv('TARHAN_ORGANIZER_PREVIEW')) {
        $preview = preg_replace('/<input[^>]*name="_wpnonce"[^>]*>/', '', $html);
        $styles = '';
        foreach (array('wp-includes/css/buttons.min.css', 'wp-admin/css/common.min.css', 'wp-admin/css/forms.min.css', 'wp-admin/css/list-tables.min.css') as $css) { $styles .= file_get_contents(ABSPATH . $css); }
        $styles .= file_get_contents(dirname(__DIR__) . '/wordpress/wp-content/plugins/tarhan-testing/assets/organizers.css');
        file_put_contents(getenv('TARHAN_ORGANIZER_PREVIEW'), '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Проверка таблицы организаторов</title><style>' . $styles . 'body{padding:20px;margin:0}</style><body class="wp-admin"><p>Визуальная проверка · синтетические данные</p>' . $preview . '</body></html>');
    }
    $_GET = array('participant' => $ids[0]); ob_start(); tarhan_organizer_page(); $detail = ob_get_clean();
    organizer_check(!str_contains($detail, '<script>') && str_contains($detail, '&lt;script&gt;') && str_contains($detail, 'tarhan_organizer_essay') && str_contains($detail, 'id="essay"'), 'Detail escaping or text essay download');
    $_GET = array('participant' => $ids[1]); ob_start(); tarhan_organizer_page(); $detail = ob_get_clean();
    organizer_check(str_contains($detail, 'tarhan_organizer_essay'), 'Stable authenticated essay link absent');
    echo "PASS: Pagination, detail, escaped user input and protected document links\n";
    $count = tarhan_organizer_write_xlsx(tarhan_organizer_rows($filters), $out, 'Только синтетические тестовые данные');
    organizer_check($count === 205, 'Export limited by UI pagination');
    $zip = new ZipArchive(); organizer_check($zip->open($out) === true, 'Invalid zip');
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $dom = new DOMDocument(); organizer_check($dom->loadXML($xml, LIBXML_NONET), 'Invalid worksheet XML');
    organizer_check(!str_contains($xml, '<x:f>') && str_contains($xml, '=1+1') && str_contains($xml, '+79990000000'), 'Formula injection or phone loss');
    organizer_check(str_contains($zip->getFromName('xl/tables/table1.xml'), 'A4:AP209'), 'Wrong table range');
    $links = $zip->getFromName('xl/worksheets/_rels/sheet1.xml.rels');
    organizer_check(!str_contains($links, '_wpnonce') && !str_contains($links, str_repeat('a', 48)) && str_contains($links, 'tarhan_organizer_essay') && str_contains($links, '#consent'), 'Unstable or private file links');
    $zip->close();
    $empty = $out . '.empty.xlsx';
    try { organizer_check(tarhan_organizer_write_xlsx(array(), $empty) === 0, 'Empty export failed'); }
    finally { if (is_file($empty)) { unlink($empty); } }
    echo "PASS: XLSX all matching rows, ZIP/XML, no formulas, text phones, stable links, empty export\n";
    foreach (array(0, $ids[0]) as $denied_user) {
        wp_set_current_user($denied_user);
        try { iterator_to_array(tarhan_organizer_rows()); throw new RuntimeException('Unauthorized data access'); }
        catch (RuntimeException $e) { organizer_check($e->getMessage() === 'DENIED:403', $e->getMessage()); }
        try { tarhan_organizer_export(); throw new RuntimeException('Unauthorized export'); }
        catch (RuntimeException $e) { organizer_check($e->getMessage() === 'DENIED:403', $e->getMessage()); }
    }
    wp_set_current_user((int) $admin[0]); $_SERVER['REQUEST_METHOD'] = 'POST'; $_REQUEST = array('_wpnonce' => 'invalid');
    try { tarhan_organizer_export(); throw new RuntimeException('Invalid nonce accepted'); }
    catch (RuntimeException $e) { organizer_check(str_starts_with($e->getMessage(), 'DENIED:'), $e->getMessage()); }
    echo "PASS: Guest and participant denied; administrator export requires nonce\n";
} finally {
    $wpdb->query('ROLLBACK');
    foreach ($ids as $id) { clean_user_cache($id); }
    wp_set_current_user($old_user); $_GET = $old_get; $_POST = $old_post; $_REQUEST = $old_request; $_SERVER['REQUEST_METHOD'] = $old_method;
    remove_filter('wp_die_handler', $die_filter);
}
organizer_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->users} WHERE user_login LIKE %s", $wpdb->esc_like($token) . '%')) === 0, 'Fixtures not rolled back');
echo "PASS: Database fixtures rolled back\n";
$complete = true;
