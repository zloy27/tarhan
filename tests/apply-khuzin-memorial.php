<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';

if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local' || get_stylesheet() !== 'tarhan') {
    throw new RuntimeException('Local Tarhan site required');
}

$page = get_page_by_path('jury');
if (!$page || get_page_template_slug($page) !== 'page-jury.php') {
    throw new RuntimeException('Jury page missing');
}

$relative = 'src/assets/Jury/15_khuzin_memorial.png';
$source = dirname(__DIR__) . '/' . $relative;
if (!is_file($source) || !getimagesize($source)) {
    throw new RuntimeException('Memorial portrait missing or invalid');
}

$attachments = get_posts(array(
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'meta_key' => '_tarhan_source_file',
    'meta_value' => $relative,
));

if ($attachments && wp_attachment_is_image($attachments[0])) {
    $attachment_id = (int) $attachments[0];
} else {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $temporary = wp_tempnam(basename($source));
    if (!$temporary || !copy($source, $temporary)) {
        throw new RuntimeException('Cannot prepare memorial portrait');
    }
    $attachment_id = media_handle_sideload(array(
        'name' => 'fayaz-khuzin-memorial.png',
        'tmp_name' => $temporary,
    ), $page->ID, 'Памяти Фаяза Шариповича Хузина');
    if (is_wp_error($attachment_id)) {
        if (is_file($temporary)) { unlink($temporary); }
        throw new RuntimeException($attachment_id->get_error_message());
    }
    update_post_meta($attachment_id, '_tarhan_source_file', $relative);
}

update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Фаяз Шарипович Хузин. Памятный портрет');
$content = get_post_meta($page->ID, '_tarhan_page_content', true);
$matches = 0;
if (!is_array($content['people'] ?? null)) {
    throw new RuntimeException('Jury content is malformed');
}
foreach ($content['people'] as &$person) {
    if (mb_stripos((string) ($person['name'] ?? ''), 'ХУЗИН') === false) { continue; }
    $person['image'] = $attachment_id;
    $matches++;
}
unset($person);
if ($matches !== 1) {
    throw new RuntimeException('Expected exactly one Khuzin card, found ' . $matches);
}
update_post_meta($page->ID, '_tarhan_page_content', wp_slash(tarhan_sanitize_page_content($content, 'jury')));

$saved = tarhan_page_content($page->ID);
$saved_matches = array_values(array_filter($saved['people'], static function ($person) {
    return mb_stripos((string) ($person['name'] ?? ''), 'ХУЗИН') !== false;
}));
if (count($saved_matches) !== 1 || (int) $saved_matches[0]['image'] !== $attachment_id) {
    throw new RuntimeException('Saved portrait verification failed: matches=' . count($saved_matches) . ', saved=' . (int) ($saved_matches[0]['image'] ?? 0) . ', expected=' . $attachment_id);
}

echo 'Updated Khuzin memorial portrait, attachment ' . $attachment_id . ".\n";
