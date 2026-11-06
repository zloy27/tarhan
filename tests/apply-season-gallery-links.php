<?php
/** Apply the customer-provided VK photo album to every season page. */
if ($argc < 2) { fwrite(STDERR, "Usage: php apply-season-gallery-links.php <wp-load.php>\n"); exit(2); }
require $argv[1];

$albums = array(
    '2021–2022' => 'https://vk.ru/tarhan_konkurs?z=album-214070047_283641259',
    '2022–2023' => 'https://vk.ru/tarhan_konkurs?z=album-214070047_309804854',
    '2023–2024' => 'https://vk.ru/tarhan_konkurs?z=album-214070047_309805049',
    '2024–2025' => 'https://vk.ru/tarhan_konkurs?z=album-214070047_309805127',
    '2025–2026' => 'https://vk.ru/tarhan_konkurs?z=album-214070047_310359015',
);

$updated = array();
foreach (get_pages(array('post_status' => array('publish', 'draft', 'private'))) as $page) {
    if (get_page_template_slug($page) !== 'page-season.php') { continue; }
    $data = tarhan_page_content($page->ID);
    $period = $data['period'] ?? '';
    if (!isset($albums[$period])) { continue; }
    $data['gallery_url'] = $albums[$period];
    update_post_meta($page->ID, '_tarhan_page_content', wp_slash(tarhan_sanitize_page_content($data, 'season')));
    $updated[$period] = $albums[$period];
}

if (count($updated) !== count($albums)) {
    throw new RuntimeException('Updated ' . count($updated) . ' of ' . count($albums) . ' season gallery links.');
}
foreach ($updated as $period => $url) { echo $period . ': ' . $url . PHP_EOL; }
