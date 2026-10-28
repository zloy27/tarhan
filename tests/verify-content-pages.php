<?php
/** Transactional integration checks: leaves no test pages or edits behind. */
$content_completed = false;
register_shutdown_function(function () use (&$content_completed) { if (!$content_completed) { exit(1); } });
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
function content_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
content_check(wp_parse_url(home_url(), PHP_URL_HOST) === 'tarhan-wp.local', 'Local site required');
content_check(!wp_using_ext_object_cache(), 'External cache unsupported for rollback tests');
global $wpdb;
foreach (array($wpdb->posts, $wpdb->postmeta, $wpdb->options) as $table) {
    $info = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
    content_check($info && strtoupper($info->Engine) === 'INNODB', 'Transactional storage required');
}
$admin = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
content_check((bool) $admin, 'Administrator missing');
$old_user = get_current_user_id(); $old_post = $_POST; $ids = array(); $passed = array();
$wpdb->query('START TRANSACTION');
try {
    wp_set_current_user((int) $admin[0]); $_POST = array();
    $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Content integration fixture',
        'meta_input' => array('_wp_page_template' => 'page-jury.php')), true);
    content_check(!is_wp_error($id), 'Fixture creation failed'); $ids[] = $id;
    $fixture = get_post($id);
    $form = array('tarhan_content_nonce' => wp_create_nonce('tarhan_content_' . $id), 'tarhan_content_kind' => 'jury',
        'tarhan_content_complete' => '1', 'tarhan_data' => array('subtitle' => 'Совет', 'intro' => "Строка 1\nСтрока 2",
            'people' => array(array('name' => 'Второй', 'role' => "O'Neil \\ редактор"), array('name' => 'Первый'))));
    $_POST = wp_slash($form); wp_update_post(array('ID' => $id, 'post_title' => 'Updated fixture'));
    $saved = get_post_meta($id, '_tarhan_page_content', true);
    content_check(array_column($saved['people'], 'name') === array('Второй', 'Первый'), 'Order not preserved');
    content_check($saved['people'][0]['role'] === "O'Neil \\ редактор" && $saved['intro'] === "Строка 1\nСтрока 2", 'Text damaged on save');
    clean_post_cache($id); content_check(tarhan_page_content($id) === $saved, 'Reload differs');
    $passed[] = 'Save hook, reload, row order, quotes, backslashes and multiline text';
    foreach (array('tarhan_content_nonce', 'tarhan_content_kind', 'tarhan_content_complete') as $key) {
        $_POST = wp_slash($form); $_POST[$key] = 'invalid'; $_POST['tarhan_data']['people'] = array();
        wp_update_post(array('ID' => $id));
        content_check(get_post_meta($id, '_tarhan_page_content', true) === $saved, 'Invalid form overwrote content: ' . $key);
    }
    $_POST = array(); wp_update_post(array('ID' => $id));
    content_check(get_post_meta($id, '_tarhan_page_content', true) === $saved, 'Quick edit erased fields');
    wp_set_current_user(0); $_POST = wp_slash($form); $_POST['tarhan_data']['people'] = array();
    tarhan_save_page_content($id, $fixture);
    content_check(get_post_meta($id, '_tarhan_page_content', true) === $saved, 'Unauthorized save');
    wp_set_current_user((int) $admin[0]);
    $passed[] = 'Nonce, page kind, permissions, truncated forms and quick edit protection';
    $_POST = wp_slash($form); $_POST['tarhan_data']['people'] = array(); wp_update_post(array('ID' => $id));
    content_check(tarhan_page_content($id)['people'] === array(), 'Cannot remove all people');
    $passed[] = 'Removing the last record persists an empty list';
    $clean = tarhan_sanitize_page_content(array('period' => '<b>2021</b>', 'final_date' => '2026-02-30', 'unknown' => 'discard',
        'cover' => $id, 'watch' => array(array('url' => 'javascript:alert(1)'), array('url' => 'https://example.com/?a=1&b=2')),
        'jury' => array(array('name' => array('nested')), array('name' => '<img src=x onerror=alert(1)>Иван'))), 'season');
    content_check($clean['period'] === '2021' && $clean['final_date'] === '' && $clean['cover'] === 0 && !isset($clean['unknown']), 'Invalid scalar accepted');
    content_check(count($clean['watch']) === 1 && count($clean['jury']) === 1 && $clean['jury'][0]['name'] === 'Иван', 'Unsafe or nested content accepted');
    ob_start(); tarhan_editor_field('test', array('Поле', 'textarea'), '</textarea><script>alert(1)</script>'); $html = ob_get_clean();
    content_check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Editor output unsafe');
    ob_start(); tarhan_content_metabox($fixture); $html = ob_get_clean();
    content_check(str_contains($html, 'data-add-row') && str_contains($html, '<template>') && str_contains($html, 'tarhan_content_complete'), 'Editor controls missing');
    $passed[] = 'Unsafe markup, image IDs, URLs, dates, nested values and editor escaping';
    $_POST = array();
    foreach (array('publish', 'draft') as $status) {
        $season_id = wp_insert_post(array('post_type' => 'page', 'post_status' => $status, 'post_title' => 'Archive fixture ' . $status,
            'meta_input' => array('_wp_page_template' => 'page-season.php')), true);
        content_check(!is_wp_error($season_id), 'Season fixture failed'); $ids[] = $season_id; $season_ids[$status] = $season_id;
    }
    ob_start(); include get_theme_file_path('/page-archive.php'); $html = ob_get_clean();
    content_check(str_contains($html, 'Archive fixture publish') && !str_contains($html, 'Archive fixture draft'), 'Archive exposes draft or misses published page');
    $passed[] = 'Archive template includes published seasons and excludes drafts';
} finally {
    $wpdb->query('ROLLBACK');
    foreach ($ids as $id) { clean_post_cache($id); }
    $_POST = $old_post; wp_set_current_user($old_user);
}
foreach ($passed as $message) { echo "PASS: $message\n"; }
echo count($passed) . " content checks passed; fixture changes rolled back.\n";
$content_completed = true;
