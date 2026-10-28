<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';

if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') {
    throw new RuntimeException('Local site required');
}

$ids = get_posts(array(
    'post_type' => 'tarhan_question',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'orderby' => 'ID',
    'order' => 'ASC',
    'no_found_rows' => true,
));

$groups = array('source_2025' => array(), 'demo' => array(), 'other' => array());
foreach ($ids as $id) {
    $row = array(
        'id' => (int) $id,
        'title' => get_the_title($id),
        'valid' => (bool) get_post_meta($id, '_tarhan_question_valid', true),
    );
    if (get_post_meta($id, '_tarhan_question_source', true)) {
        $groups['source_2025'][] = $row;
    } elseif (get_post_meta($id, '_tarhan_demo_key', true)) {
        $groups['demo'][] = $row;
    } else {
        $data = get_post_meta($id, '_tarhan_question_data', true);
        $row['text'] = is_array($data) && isset($data['text']) ? $data['text'] : '';
        $row['options_count'] = is_array($data) && isset($data['options']) && is_array($data['options']) ? count($data['options']) : 0;
        $groups['other'][] = $row;
    }
}

echo wp_json_encode(array(
    'counts' => array_map('count', $groups),
    'other' => $groups['other'],
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
