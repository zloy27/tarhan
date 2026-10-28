<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') { throw new RuntimeException('Local only'); }
$bank = tarhan_get_random_questions(50);
$essay = tarhan_essay_rules();
$seasons = tarhan_seasons_state();
$pages = array();
foreach (array('jury','ambassadors','archive/2021-2022','archive/2022-2023','archive/2023-2024','archive/2024-2025') as $slug) {
    $page = get_page_by_path($slug);
    if (!$page) { $pages[$slug] = null; continue; }
    $content = get_post_meta($page->ID, '_tarhan_page_content', true);
    $people = is_array($content['people'] ?? null) ? $content['people'] : array();
    $pages[$slug] = array('status' => $page->post_status, 'people_count' => count($people), 'people_with_photos' => count(array_filter($people, static function ($person) { return !empty($person['image']); })));
}
echo wp_json_encode(array('question_counts' => wp_count_posts('tarhan_question'), 'bank_ready' => !is_wp_error($bank),
    'testing_open' => get_option('tarhan_testing_open','0') === '1',
    'essay' => array('open'=>$essay['open'],'topic_set'=>$essay['topic']!=='','max_length'=>$essay['max_length'],'max_bytes'=>$essay['max_bytes']),
    'seasons' => array('count'=>count($seasons['items']),'configured'=>$seasons['configured'],'current_selected'=>!empty($seasons['current_id']),'registration_open'=>$seasons['registration_open']),
    'pages'=>$pages), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
