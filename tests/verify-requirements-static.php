<?php
/** Verify customer-approved content corrections without loading WordPress. */
$root = dirname(__DIR__);
$check = static function ($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
};

$information = file_get_contents($root . '/wordpress/wp-content/themes/tarhan/content/information.html');
$ministry = strpos($information, '>Минкультуры РТ<');
$kfu = strpos($information, '>КФУ<');
$committee = strpos($information, '>Оргкомитет<');
$check($ministry !== false && $kfu !== false && $committee !== false && $ministry < $kfu && $kfu < $committee,
    'Organizer order is not Ministry, KFU, committee.');
$check(!str_contains($information, 'Лист согласования'), 'Public consent link remains.');

$participant = file_get_contents($root . '/wordpress/wp-content/themes/tarhan/content/participant.html');
$check(str_contains($participant, 'https://vk.ru/video/playlist/-214070047_8'), 'Lecture playlist is incorrect.');
$check(str_contains($participant, 'https://vk.ru/video/playlist/-214070047_1'), 'First-season format playlist is incorrect.');
$check(!str_contains($participant, 'youtube.com/c/') && !str_contains($participant, 'rutube.ru/channel/25369933'),
    'Unconfirmed format links remain public.');

$playlists = array(
    '2021-2022' => 'https://vk.ru/video/playlist/-214070047_1',
    '2022-2023' => 'https://vk.ru/video/playlist/-214070047_2',
    '2023-2024' => 'https://vk.ru/video/playlist/-214070047_3',
    '2024-2025' => 'https://vk.ru/video/playlist/-214070047_7',
);
foreach ($playlists as $period => $url) {
    $path = $root . '/wordpress/wp-content/themes/tarhan/content/season-' . $period . '.json';
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $check(count($data['watch'] ?? array()) === 1 && ($data['watch'][0]['url'] ?? '') === $url,
        'Wrong playlist for ' . $period . '.');
}

$home = file_get_contents($root . '/wordpress/wp-content/themes/tarhan/inc/home-editor.php');
foreach (array("'stat_1_value' => '40'", "'stat_2_value' => '4'", "'stat_3_value' => '6'",
    "'stat_4_value' => '3'", "'stat_5_value' => '1'") as $expected) {
    $check(str_contains($home, $expected), 'Missing home statistic: ' . $expected);
}
$check(!str_contains($home, 'зарегестрироваться'), 'Registration button spelling is incorrect.');

echo "PASS: approved texts, organizer order, public documents, lectures, season playlists and statistics.\n";
