<?php
/** Real WordPress save/read/validation checks, rolled back after completion. */
$complete = false;
register_shutdown_function(function () use (&$complete) { if (!$complete) { exit(1); } });
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
function question_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
question_check(wp_parse_url(home_url(), PHP_URL_HOST) === 'tarhan-wp.local', 'Local site required');
global $wpdb;
question_check(!wp_using_ext_object_cache(), 'External cache unsupported');
foreach (array($wpdb->posts, $wpdb->postmeta, $wpdb->options) as $table) {
    $info = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
    question_check($info && strtoupper($info->Engine) === 'INNODB', 'Transaction required');
}
$admin = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
question_check((bool) $admin, 'Administrator missing');
$old_user = get_current_user_id(); $old_post = $_POST; $id = 0;
$restriction = function ($query) use (&$id) { if ($query->get('tarhan_question_bank')) { $query->set('post__in', array($id)); } };
add_action('pre_get_posts', $restriction);
$wpdb->query('START TRANSACTION');
try {
    wp_set_current_user((int) $admin[0]); $_POST = array();
    $id = wp_insert_post(array('post_type' => 'tarhan_question', 'post_title' => 'Editor test fixture', 'post_status' => 'publish'), true);
    question_check(!is_wp_error($id), 'Fixture failed');
    $form = array('tarhan_question_nonce' => wp_create_nonce('tarhan_save_question'),
        'tarhan_question_text' => "Текст 'вопроса' \\ строка", 'tarhan_question_options' => "Первый\nВторой\nТретий", 'tarhan_question_correct' => '2');
    $_POST = wp_slash($form); wp_update_post(array('ID' => $id)); clean_post_cache($id);
    $data = get_post_meta($id, '_tarhan_question_data', true);
    question_check($data['text'] === $form['tarhan_question_text'] && $data['correct'] === array(2) && count($data['options']) === 3, 'Roundtrip damaged');
    $bank = tarhan_get_random_questions(1);
    question_check(!is_wp_error($bank) && $bank[0]['correct'] === array(2), 'Test bank did not receive the saved answer');
    question_check(tarhan_calculate_score($bank, array($id => 2)) === 1 && tarhan_calculate_score($bank, array($id => 1)) === 0, 'Answer number not used for scoring');
    question_check(!array_key_exists('correct', tarhan_public_question($bank[0])), 'Correct answer leaked to participant');
    echo "PASS: Save, reload, bank selection, numeric scoring and private answer key\n";
    foreach (array('0', '-1', '4', '1.5', '2,2', '1,2', '', '2e0') as $invalid) {
        question_check(is_wp_error(tarhan_validate_question('Question', "One\nTwo\nThree", $invalid)), 'Invalid number accepted: ' . $invalid);
    }
    question_check(is_wp_error(tarhan_validate_question('Question', "One\n\nThree", '2')), 'Empty middle option accepted');
    question_check(is_wp_error(tarhan_validate_question('', "One\nTwo", '1')), 'Empty question accepted');
    $_POST = wp_slash($form); $_POST['tarhan_question_correct'] = '4'; wp_update_post(array('ID' => $id));
    question_check(get_post_meta($id, '_tarhan_question_data', true) === $data, 'Bad submission damaged saved question');
    question_check(is_wp_error(tarhan_get_random_questions(1)), 'Invalid question remained in selection');
    echo "PASS: Invalid numbers and incomplete questions rejected, previous data retained\n";
    $_POST = wp_slash($form); wp_update_post(array('ID' => $id));
    $_POST['tarhan_question_nonce'] = 'invalid'; $_POST['tarhan_question_correct'] = '1'; wp_update_post(array('ID' => $id));
    question_check(get_post_meta($id, '_tarhan_question_data', true) === $data, 'Invalid nonce saved');
    wp_set_current_user(0); $_POST = wp_slash($form); $_POST['tarhan_question_correct'] = '1'; tarhan_save_question($id);
    question_check(get_post_meta($id, '_tarhan_question_data', true) === $data, 'Unauthorized save');
    wp_set_current_user((int) $admin[0]);
    ob_start(); tarhan_questions_fields(get_post($id)); $html = ob_get_clean();
    question_check(str_contains($html, 'type="number"') && str_contains($html, 'data-question-preview'), 'Numeric editor missing');
    echo "PASS: Permissions, nonce, correction after an error and numeric editor controls\n";
} finally {
    $wpdb->query('ROLLBACK');
    remove_action('pre_get_posts', $restriction);
    if (is_int($id) && $id) { clean_post_cache($id); }
    $_POST = $old_post; wp_set_current_user($old_user);
}
$counts = wp_count_posts('tarhan_question');
echo 'Existing published questions: ' . $counts->publish . '; drafts: ' . $counts->draft . ".\n";
$complete = true;
