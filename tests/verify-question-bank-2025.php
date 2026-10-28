<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';

if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') {
    throw new RuntimeException('Local site required');
}

function bank_check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$source_path = dirname(__DIR__) . '/wordpress/wp-content/plugins/tarhan-testing/data/questions-2025.json';
$payload = json_decode(file_get_contents($source_path), true, 512, JSON_THROW_ON_ERROR);
$source = $payload['questions'] ?? array();
bank_check(count($source) === 50, 'Source count mismatch');

$ids = array();
$answers = array();
foreach ($source as $question) {
    $key = sprintf('tarhan-2025-%02d', (int) $question['number']);
    $matches = get_posts(array(
        'post_type' => 'tarhan_question',
        'post_status' => 'publish',
        'meta_key' => '_tarhan_question_source',
        'meta_value' => $key,
        'fields' => 'ids',
        'numberposts' => 2,
    ));
    bank_check(count($matches) === 1, "Published source marker mismatch: $key");
    $id = (int) $matches[0];
    $data = get_post_meta($id, '_tarhan_question_data', true);
    bank_check(is_array($data), "Question data missing: $key");
    bank_check($data['text'] === $question['text'], "Question text mismatch: $key");
    bank_check($data['options'] === $question['options'], "Question options mismatch: $key");
    bank_check($data['correct'] === $question['correct'], "Correct answer mismatch: $key");
    $ids[] = $id;
    $answers[$id] = $data['correct'][0];
}

$bank = tarhan_get_random_questions(50);
bank_check(!is_wp_error($bank), 'Random bank is unavailable');
bank_check(count($bank) === 50, 'Random bank count mismatch');
bank_check(count(array_unique(array_column($bank, 'id'))) === 50, 'Random bank contains repeated IDs');
bank_check(array_diff(array_column($bank, 'id'), $ids) === array(), 'Random bank contains a non-source question');
bank_check(tarhan_calculate_score($bank, $answers) === 50, 'Perfect score mismatch');

foreach (tarhan_public_questions($bank) as $question) {
    bank_check(!array_key_exists('correct', $question), 'Correct answer leaked to participant data');
}

$normalized = array();
foreach ($source as $question) {
    $key = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($question['text']));
    $normalized[$key][] = (int) $question['number'];
}
$duplicates = array_values(array_filter($normalized, static fn($numbers) => count($numbers) > 1));

echo wp_json_encode(array(
    'published_total' => (int) wp_count_posts('tarhan_question')->publish,
    'source_questions' => count($ids),
    'unique_ids' => count(array_unique($ids)),
    'perfect_score' => 50,
    'answer_keys_exposed' => false,
    'duplicate_source_texts' => $duplicates,
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
