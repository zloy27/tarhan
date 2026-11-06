<?php
if ($argc < 2) { fwrite(STDERR, "Usage: php verify-home-editor.php <wp-load.php>\n"); exit(2); }
require $argv[1];

$fail = static function ($message) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); };
if (!function_exists('tarhan_home_content') || !function_exists('tarhan_home_schema')) { $fail('home editor functions are not loaded'); }

$content = tarhan_home_content();
$field_count = 0;
foreach (tarhan_home_schema() as $section) {
    foreach ($section[1] as $key => $field) {
        $field_count++;
        if (!array_key_exists($key, $content)) { $fail("missing content key {$key}"); }
        if (!in_array($field[1], array('text', 'textarea', 'url', 'media'), true)) { $fail("unknown field type for {$key}"); }
    }
}
if ($field_count < 50) { $fail('the editor does not cover all home-page sections'); }
if (strpos($content['hero_title'], 'Тархан') === false) { $fail('hero default is missing'); }
if (!wp_http_validate_url($content['news_url'])) { $fail('news URL is invalid'); }

if (!function_exists('tarhan_home_admin_page')) { $fail('admin page callback is not registered'); }

$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
if (!$admins) { $fail('administrator account is missing'); }
wp_set_current_user((int) $admins[0]);
require_once ABSPATH . 'wp-admin/includes/template.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
tarhan_home_admin_page();
$admin_html = ob_get_clean();
if (substr_count($admin_html, 'class="tarhan-home-section"') !== count(tarhan_home_schema())) { $fail('admin sections were not rendered'); }
if (strpos($admin_html, 'name="tarhan_home[hero_title]"') === false) { $fail('hero field was not rendered'); }

echo "OK: {$field_count} editable fields and the complete admin form rendered.\n";
