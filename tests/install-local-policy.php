<?php
if (!defined('DISABLE_WP_CRON')) { define('DISABLE_WP_CRON', true); }
require_once 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') { throw new RuntimeException('Local site only'); }
$page = get_page_by_path('privacy-policy');
if ($page && $page->post_status === 'publish') {
    echo "EXISTS privacy-policy\n";
    return;
}
if ($page) {
    if ($page->post_status !== 'draft' || $page->post_title !== 'Privacy Policy' || strpos($page->post_content, 'privacy-policy-tutorial') === false) {
        throw new RuntimeException('Custom unpublished policy page found; refusing to replace it.');
    }
    $backup = 'C:/Users/zloy27/.codex/visualizations/2026/10/17/01a14b37-4bc1-7591-b79e-7ebeceaf4e34/policy-draft-before-' . gmdate('Ymd-His') . '.json';
    if (file_put_contents($backup, wp_json_encode($page, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) { throw new RuntimeException('Cannot back up draft'); }
}
$result = wp_insert_post(array('ID' => $page ? $page->ID : 0, 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'privacy-policy', 'post_title' => 'Политика', 'post_content' => ''), true);
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
flush_rewrite_rules(false);
echo "READY privacy-policy\n";
