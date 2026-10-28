<?php
// Local-only import, preserving a restorable snapshot and refusing authored pages.
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local' || get_stylesheet() !== 'tarhan') { throw new RuntimeException('Local only'); }
$root = dirname(__DIR__);
$backup = $argv[1] ?? '';
if (!is_dir($backup)) { throw new RuntimeException('Backup directory required'); }
$periods = ['2022-2023','2023-2024','2024-2025'];
$before = [];
foreach ($periods as $period) {
 $page = get_page_by_path('archive/' . $period);
 if (!$page || get_page_template_slug($page) !== 'page-season.php') { throw new RuntimeException('Missing expected season: ' . $period); }
 $old = get_post_meta($page->ID, '_tarhan_page_content', true);
 if (array_filter(array_diff_key((array)$old, ['period'=>true])) && !get_post_meta($page->ID, '_tarhan_remaining_import', true)) { throw new RuntimeException('Authored season requires review: ' . $period); }
 $before[$period] = ['post'=>$page,'meta'=>get_post_meta($page->ID)];
}
if (file_put_contents($backup . '/seasons-before.json', wp_json_encode($before, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) === false) { throw new RuntimeException('Backup failed'); }
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
function tarhan_import_season_photo($relative, $root, $parent) {
 $found = get_posts(['post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>1,'meta_key'=>'_tarhan_source_file','meta_value'=>$relative,'fields'=>'ids']);
 if ($found && wp_attachment_is_image($found[0])) { return $found[0]; }
 $path = realpath($root . '/' . $relative);
 $allowed = wp_normalize_path(realpath($root . '/src/assets/photo/seasons')) . '/';
 if (!$path || !str_starts_with(wp_normalize_path($path), $allowed) || !getimagesize($path)) { throw new RuntimeException('Invalid photo: ' . $relative); }
 $tmp = wp_tempnam(basename($path));
 if (!$tmp || !copy($path,$tmp)) { throw new RuntimeException('Photo copy failed'); }
 $id = media_handle_sideload(['name'=>basename(dirname($path)) . '-' . basename($path),'tmp_name'=>$tmp], $parent);
 if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
 update_post_meta($id,'_tarhan_source_file',$relative);
 return $id;
}
foreach ($periods as $period) {
 $id = $before[$period]['post']->ID;
 $data = json_decode(file_get_contents($root . '/wordpress/wp-content/themes/tarhan/content/season-' . $period . '.json'), true, 512, JSON_THROW_ON_ERROR);
 $data['cover'] = tarhan_import_season_photo($data['cover'], $root, $id);
 foreach ($data['gallery'] as &$photo) {
  $photo['image'] = tarhan_import_season_photo($photo['image'], $root, $id);
  update_post_meta($photo['image'],'_wp_attachment_image_alt',$photo['alt']);
 }
 unset($photo);
 $clean = tarhan_sanitize_page_content($data, 'season');
 update_post_meta($id, '_tarhan_page_content', wp_slash($clean));
 update_post_meta($id, '_tarhan_remaining_import', $data['source_sha256']);
 update_post_meta($id, '_tarhan_content_source', $data['source']);
 if (tarhan_page_content($id) != $clean) { throw new RuntimeException('Content verification failed'); }
 $result = wp_update_post(['ID'=>$id,'post_status'=>'publish'],true);
 if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
 echo $period . ': published; ' . count($clean['gallery']) . ' photos, ' . count($clean['finalists']) . " finalists\n";
}
