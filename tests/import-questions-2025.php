<?php
// Imports the organizer-provided 2025 question bank into the Local WordPress site.
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';

if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') {
    throw new RuntimeException('Local site required');
}

$source_path = dirname(__DIR__) . '/wordpress/wp-content/plugins/tarhan-testing/data/questions-2025.json';
$payload = json_decode(file_get_contents($source_path), true, 512, JSON_THROW_ON_ERROR);
$questions = $payload['questions'] ?? null;
if (!is_array($questions) || count($questions) !== 50) {
    throw new RuntimeException('Expected exactly 50 source questions');
}

$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
if (!$admins) {
    throw new RuntimeException('Administrator missing');
}
wp_set_current_user((int) $admins[0]);

global $wpdb;
$created = 0;
$updated = 0;
$disabled_demo = 0;
$disabled_placeholder = 0;
$source_ids = array();
$wpdb->query('START TRANSACTION');

try {
    foreach ($questions as $question) {
        $number = (int) ($question['number'] ?? 0);
        $text = $question['text'] ?? '';
        $options = $question['options'] ?? null;
        $correct_values = $question['correct'] ?? null;
        if (!is_string($text) || !is_array($options) || !is_array($correct_values) || count($correct_values) !== 1) {
            throw new RuntimeException("Malformed source question $number");
        }

        $correct = (int) $correct_values[0];
        $validated = tarhan_validate_question($text, implode("\n", $options), (string) $correct);
        if (is_wp_error($validated) || $validated['options'] !== $options) {
            $message = is_wp_error($validated) ? $validated->get_error_message() : 'Option normalization changed data';
            throw new RuntimeException("Question $number failed validation: $message");
        }

        $source_key = sprintf('tarhan-2025-%02d', $number);
        $existing = get_posts(array(
            'post_type' => 'tarhan_question',
            'post_status' => array('publish', 'draft', 'private', 'pending', 'future', 'trash'),
            'meta_key' => '_tarhan_question_source',
            'meta_value' => $source_key,
            'fields' => 'ids',
            'numberposts' => 2,
        ));
        if (count($existing) > 1) {
            throw new RuntimeException("Duplicate source marker $source_key");
        }

        $post = array(
            'post_type' => 'tarhan_question',
            'post_status' => 'publish',
            'post_title' => sprintf('Тархан 2025 — %02d', $number),
        );
        if ($existing) {
            $post['ID'] = (int) $existing[0];
            $id = wp_update_post(wp_slash($post), true);
            $updated++;
        } else {
            $id = wp_insert_post(wp_slash($post), true);
            $created++;
        }
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message());
        }

        update_post_meta($id, '_tarhan_question_text', wp_slash($validated['text']));
        update_post_meta($id, '_tarhan_question_options', wp_slash(implode("\n", $validated['options'])));
        update_post_meta($id, '_tarhan_question_correct', (string) $correct);
        update_post_meta($id, '_tarhan_question_data', wp_slash($validated));
        update_post_meta($id, '_tarhan_question_valid', 1);
        update_post_meta($id, '_tarhan_question_source', $source_key);
        update_post_meta($id, '_tarhan_question_source_number', $number);
        clean_post_cache($id);
        $source_ids[] = (int) $id;
    }

    $demo_ids = get_posts(array(
        'post_type' => 'tarhan_question',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(array('key' => '_tarhan_demo_key', 'compare' => 'EXISTS')),
        'no_found_rows' => true,
    ));
    foreach ($demo_ids as $demo_id) {
        $result = wp_update_post(array('ID' => (int) $demo_id, 'post_status' => 'draft'), true);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
        clean_post_cache((int) $demo_id);
        $disabled_demo++;
    }

    $other_ids = get_posts(array(
        'post_type' => 'tarhan_question',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'post__not_in' => $source_ids,
        'no_found_rows' => true,
    ));
    foreach ($other_ids as $other_id) {
        $data = get_post_meta($other_id, '_tarhan_question_data', true);
        $is_placeholder = get_the_title($other_id) === '1' &&
            is_array($data) && ($data['text'] ?? '') === '1';
        if (!$is_placeholder) {
            continue;
        }
        $result = wp_update_post(array('ID' => (int) $other_id, 'post_status' => 'draft'), true);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
        clean_post_cache((int) $other_id);
        $disabled_placeholder++;
    }

    if (count(array_unique($source_ids)) !== 50) {
        throw new RuntimeException('Imported source IDs are not unique');
    }
    foreach ($source_ids as $id) {
        $data = get_post_meta($id, '_tarhan_question_data', true);
        if (!is_array($data) || count($data['options'] ?? array()) !== 4 || count($data['correct'] ?? array()) !== 1) {
            throw new RuntimeException("Saved question $id is invalid");
        }
        if (array_key_exists('correct', tarhan_public_question(array_merge($data, array('id' => $id))))) {
            throw new RuntimeException("Answer key leaked for question $id");
        }
    }

    $wpdb->query('COMMIT');
    echo wp_json_encode(array(
        'created' => $created,
        'updated' => $updated,
        'source_questions' => count($source_ids),
        'disabled_demo' => $disabled_demo,
        'disabled_placeholder' => $disabled_placeholder,
        'published_total' => (int) wp_count_posts('tarhan_question')->publish,
    ), JSON_UNESCAPED_UNICODE), "\n";
} catch (Throwable $error) {
    $wpdb->query('ROLLBACK');
    foreach ($source_ids as $id) {
        clean_post_cache($id);
    }
    throw $error;
}
