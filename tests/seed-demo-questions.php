<?php
// Retained demo bank for manual testing; deliberately limited to the Local site.
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') { throw new RuntimeException('Local site required'); }
$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
if (!$admins) { throw new RuntimeException('Administrator missing'); }
wp_set_current_user((int) $admins[0]);
global $wpdb;
$ids = array(); $created = 0;
$wpdb->query('START TRANSACTION');
try {
    for ($i = 1; $i <= 50; $i++) {
        $key = 'tarhan-demo-20261026-' . $i;
        $existing = get_posts(array('post_type' => 'tarhan_question', 'post_status' => array('publish', 'draft', 'private', 'pending', 'future', 'trash'), 'meta_key' => '_tarhan_demo_key', 'meta_value' => $key, 'fields' => 'ids', 'numberposts' => 2));
        if (count($existing) > 1) { throw new RuntimeException('Duplicate demo marker'); }
        if ($existing) { $ids[] = $existing[0]; continue; }
        $a = $i + 10; $b = $i % 7 + 2;
        switch ($i % 4) {
            case 0: $expression = "$a + $b"; $answer = $a + $b; break;
            case 1: $expression = "$a − $b"; $answer = $a - $b; break;
            case 2: $expression = "$a × $b"; $answer = $a * $b; break;
            default: $expression = ($a * $b) . " ÷ $b"; $answer = $a; break;
        }
        $options = array($answer - 2, $answer - 1, $answer + 1, $answer + 2);
        $correct = ($i - 1) % 4 + 1;
        $options[$correct - 1] = $answer;
        $question = "Демонстрационный вопрос $i. Сколько будет $expression?";
        $options_text = implode("\n", $options);
        $valid = tarhan_validate_question($question, $options_text, (string) $correct);
        if (is_wp_error($valid)) { throw new RuntimeException($valid->get_error_message()); }
        $_POST = wp_slash(array('tarhan_question_nonce' => wp_create_nonce('tarhan_save_question'),
            'tarhan_question_text' => $question, 'tarhan_question_options' => $options_text, 'tarhan_question_correct' => (string) $correct));
        $id = wp_insert_post(array('post_type' => 'tarhan_question', 'post_status' => 'publish',
            'post_title' => sprintf('[ДЕМО] Проверка теста — %02d', $i)), true);
        if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
        update_post_meta($id, '_tarhan_demo_key', $key);
        $ids[] = $id; $created++;
        $data = get_post_meta($id, '_tarhan_question_data', true);
        if (!$data || $data['correct'] !== array($correct) || !get_post_meta($id, '_tarhan_question_valid', true)) { throw new RuntimeException('Question save failed'); }
    }
    $bank = tarhan_get_random_questions(50);
    if (is_wp_error($bank) || count($bank) !== 50 || count(array_unique(array_column($bank, 'id'))) !== 50) { throw new RuntimeException('Bank selection failed'); }
    $answers = array(); foreach ($bank as $question) { $answers[$question['id']] = $question['correct'][0]; }
    if (tarhan_calculate_score($bank, $answers) !== 50) { throw new RuntimeException('Scoring failed'); }
    foreach (tarhan_public_questions($bank) as $question) { if (isset($question['correct'])) { throw new RuntimeException('Answer key exposed'); } }
    $wpdb->query('COMMIT');
    echo wp_json_encode(array('created' => $created, 'demo_count' => count($ids), 'published_total' => wp_count_posts('tarhan_question')->publish, 'unique_selection' => 50, 'perfect_score' => 50), JSON_UNESCAPED_UNICODE), "\n";
} catch (Throwable $error) {
    $wpdb->query('ROLLBACK'); foreach ($ids as $id) { clean_post_cache($id); }
    throw $error;
}
