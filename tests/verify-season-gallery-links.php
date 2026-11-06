<?php
/** Read-only verification of season gallery buttons and their exact URLs. */
if ($argc < 2) { fwrite(STDERR, "Usage: php verify-season-gallery-links.php <wp-load.php>\n"); exit(2); }
require $argv[1];

$expected = array(
    '2021–2022' => 'https://vk.ru/tarhan_konkurs?z=album-214070047_283641259',
    '2022–2023' => 'https://vk.ru/tarhan_konkurs?z=album-214070047_309804854',
    '2023–2024' => 'https://vk.ru/tarhan_konkurs?z=album-214070047_309805049',
    '2024–2025' => 'https://vk.ru/tarhan_konkurs?z=album-214070047_309805127',
    '2025–2026' => 'https://vk.ru/tarhan_konkurs?z=album-214070047_310359015',
);
foreach ($expected as $period => $url) {
    $slug = str_replace('–', '-', $period);
    $page = get_page_by_path('archive/' . $slug);
    if (!$page) { throw new RuntimeException('Missing season page: ' . $period); }
    $data = tarhan_page_content($page->ID);
    if (($data['gallery_url'] ?? '') !== $url) { throw new RuntimeException('Wrong gallery URL for ' . $period); }
    echo 'PASS: ' . $period . ' gallery URL' . PHP_EOL;
}
echo '5 season gallery links verified.' . PHP_EOL;
