<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') { throw new RuntimeException('Local site required'); }
$page = get_page_by_path('ambassadors');
if (!$page || tarhan_content_kind($page) !== 'ambassadors') { throw new RuntimeException('Ambassador page missing'); }
$data = tarhan_page_content($page->ID);
if (!empty($data['people'])) { echo "Existing ambassador records preserved.\n"; exit; }
$seed = json_decode(file_get_contents(dirname(__DIR__) . '/wordpress/wp-content/themes/tarhan/content/ambassadors.json'), true, 512, JSON_THROW_ON_ERROR);
$data['people'] = $seed['people'];
update_post_meta($page->ID, '_tarhan_page_content', wp_slash(tarhan_sanitize_page_content($data, 'ambassadors')));
delete_post_meta($page->ID, '_tarhan_source_pending');
update_post_meta($page->ID, '_tarhan_source_note', $seed['source_note']);
if (count(tarhan_page_content($page->ID)['people']) !== 8) { throw new RuntimeException('Import verification failed'); }
echo "Imported and reloaded 8 ambassador records from the original page's cached text. Photos pending.\n";
