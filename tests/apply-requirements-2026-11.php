<?php
/** Apply the customer-approved November 2026 content corrections to WordPress data. */
if ($argc < 2) { fwrite(STDERR, "Usage: php apply-requirements-2026-11.php <wp-load.php>\n"); exit(2); }
require $argv[1];

if (!function_exists('tarhan_home_defaults') || !function_exists('tarhan_page_content')) {
    throw new RuntimeException('Deploy the current Tarhan theme before applying content corrections.');
}

$home_keys = array('hero_title', 'hero_text', 'hero_button', 'hero_date', 'prize_title', 'prize_text', 'stat_1_value', 'stat_1_label',
    'stat_2_value', 'stat_2_label', 'stat_3_value', 'stat_3_label', 'stat_4_value', 'stat_4_label',
    'stat_5_value', 'stat_5_label');
$home = get_option('tarhan_home_content', array());
$home = is_array($home) ? $home : array();
$defaults = tarhan_home_defaults();
foreach ($home_keys as $key) { $home[$key] = $defaults[$key]; }
update_option('tarhan_home_content', $home, false);

$jury = get_page_by_path('jury');
if (!$jury) { throw new RuntimeException('Jury page is missing.'); }
$jury_data = tarhan_page_content($jury->ID);
$role_updates = array(
    'РАИЛЬ РАВИЛОВИЧ ФАХРУТДИНОВ' => 'профессор, ведущий проекта',
    'АЙРАТ ШАМИЛЕВИЧ ФАЙЗРАХМАНОВ' => 'директор Национального музея Республики Татарстан',
    'ДАМИР ДАНИЛОВИЧ НАТФУЛЛИН' => 'директор Музея изобразительных искусств Республики Татарстан',
    'МИХАИЛ ДМИТРИЕВИЧ ЩЕЛКУНОВ' => 'профессор',
);
$jury_updated = array();
foreach ($jury_data['people'] as &$person) {
    $name = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $person['name'])), 'UTF-8');
    if (isset($role_updates[$name])) { $person['role'] = $role_updates[$name]; $jury_updated[] = $name; }
}
unset($person);
if (count($jury_updated) !== count($role_updates)) {
    throw new RuntimeException('Not every requested jury member was found; no jury changes were saved.');
}
update_post_meta($jury->ID, '_tarhan_page_content', wp_slash($jury_data));

$playlist_by_period = array(
    '2021–2022' => array('ВК · 1-й сезон', 'https://vk.ru/video/playlist/-214070047_1'),
    '2022–2023' => array('ВК · 2-й сезон', 'https://vk.ru/video/playlist/-214070047_2'),
    '2023–2024' => array('ВК · 3-й сезон', 'https://vk.ru/video/playlist/-214070047_3'),
    '2024–2025' => array('ВК · 4-й сезон', 'https://vk.ru/video/playlist/-214070047_7'),
);
$season_updates = 0;
foreach (get_pages(array('post_status' => array('publish', 'draft', 'private'))) as $page) {
    if (get_page_template_slug($page) !== 'page-season.php') { continue; }
    $data = tarhan_page_content($page->ID);
    if (!isset($playlist_by_period[$data['period']])) { continue; }
    list($label, $url) = $playlist_by_period[$data['period']];
    $data['watch'] = array(array('label' => $label, 'url' => $url));
    update_post_meta($page->ID, '_tarhan_page_content', wp_slash($data));
    $season_updates++;
}
if ($season_updates !== count($playlist_by_period)) {
    throw new RuntimeException("Updated {$season_updates} of " . count($playlist_by_period) . ' season pages.');
}

echo 'Updated home content, four jury roles and four season playlists.' . PHP_EOL;
