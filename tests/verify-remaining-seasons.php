<?php
$completed = false;
register_shutdown_function(function () use (&$completed) { if (!$completed) { exit(1); } });
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') { throw new RuntimeException('Local only'); }
foreach (['2022-2023'=>13,'2023-2024'=>14,'2024-2025'=>16] as $period=>$expected) {
 $page = get_page_by_path('archive/' . $period);
 $data = tarhan_page_content($page->ID);
 if ($page->post_status !== 'publish' || count($data['gallery']) !== $expected || count($data['finalists']) !== 6) { throw new RuntimeException('Incomplete season: '.$period); }
 foreach ($data['gallery'] as $photo) {
  if (!is_file(get_attached_file($photo['image']))) { throw new RuntimeException('Missing photo'); }
  $image = wp_get_attachment_image_src($photo['image'], 'large');
  if (!$image || $image[1] > 1600 || !wp_get_attachment_image_srcset($photo['image'], 'large')) { throw new RuntimeException('Missing responsive image'); }
 }
 $response = wp_remote_get(get_permalink($page));
 if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { throw new RuntimeException('Page unavailable'); }
 $html = wp_remote_retrieve_body($response);
 if (substr_count($html, 'srcset=') < $expected) { throw new RuntimeException('Responsive images missing from page HTML'); }
 foreach ($data['finalists'] as $person) { if (!str_contains($html, esc_html($person['name']))) { throw new RuntimeException('Missing finalist'); } }
 echo $period . ": content, responsive images and HTTP 200 verified\n";
}
$completed = true;
