<?php

/**
 * Plugin Name: Тархан — тестирование
 * Description: Тестирование участников конкурса.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}
function tarhan_testing_menu()
{
    add_menu_page(
        'Тестирование Тархан',
        'Тестирование',
        'manage_options',
        'tarhan-testing',
        'tarhan_testing_page',
        'dashicons-welcome-learn-more'
    );
    add_submenu_page(
        'tarhan-testing',
        'Настройки теста',
        'Настройки теста',
        'manage_options',
        'tarhan-testing',
        'tarhan_testing_page'
    );
}
add_action('admin_menu', 'tarhan_testing_menu');
function tarhan_testing_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    echo '<div class="wrap">';
    echo '<h1>Настройки теста</h1>';
    echo '<p>Публикация страницы сезона и открытие регистрации не открывают тест автоматически. Чтобы участники могли начать тест, включите «Разрешить новые попытки» и сохраните настройки.</p>';
    echo '<p><a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_type=tarhan_question')) . '">Редактировать вопросы</a> <a class="button" href="' . esc_url(admin_url('post-new.php?post_type=tarhan_question')) . '">Добавить вопрос</a></p>';
    echo '<p>50 случайных вопросов, 40 минут, максимум 3 попытки. Допуск к эссе — от 25 баллов. Учитывается последняя завершённая попытка.</p>';
    echo '<p>При закрытии теста новые попытки не начинаются. Уже начатые можно закончить до истечения их времени.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('tarhan_testing_settings');
    echo '<input type="hidden" name="action" value="tarhan_testing_settings">';
    echo '<label><input type="checkbox" name="testing_open" value="1" ' . checked(get_option('tarhan_testing_open', '0'), '1', false) . '> Разрешить новые попытки</label>';
    submit_button('Сохранить');
    echo '</form>';
    echo '<p>Для старта требуется не меньше 50 опубликованных корректных вопросов. Попытки, ответы и результат сохраняются на сервере.</p>';
    echo '</div>';
}

function tarhan_register_question(){
    register_post_type('tarhan_question',array(
        'labels'=>array(
            'name' => 'Вопросы теста',
            'singular_name' => 'Вопрос',
            'add_new' => 'Добавить вопрос',
            'add_new_item' => 'Добавить вопрос',
            'edit_item' => 'Редактировать вопрос',
            'all_items' => 'Вопросы теста',
            'not_found' => 'Вопросов пока нет. Нажмите «Добавить вопрос».',
            'search_items' => 'Найти вопрос'
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'tarhan-testing',
        'show_in_rest' => false,
        'supports' => array('title'),
        'capabilities' => array(
            'edit_post' => 'manage_options',
            'read_post' => 'manage_options',
            'delete_post' => 'manage_options',
            'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options',
            'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options',
            'create_posts' => 'manage_options'
        ),'map_meta_cap' => false
    ));
}
add_action('init', 'tarhan_register_question');


function tarhan_qustions_boxes(){
    add_meta_box(
        'tarhan-questions-content',
        'Содержимое вопроса',
        'tarhan_questions_fields',
        'tarhan_question',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes_tarhan_question','tarhan_qustions_boxes');

function tarhan_questions_fields($post){
    wp_nonce_field('tarhan_save_question', 'tarhan_question_nonce');
    $draft = get_transient('tarhan_question_draft_' . get_current_user_id() . '_' . $post->ID);
    if (is_array($draft)) {
        echo '<div class="notice notice-error inline"><p>Поля вопроса не сохранены: ' .
            esc_html($draft['message']) . ' Исправьте данные и сохраните ещё раз. До исправления вопрос исключён из новых тестов. Введённые значения временно показаны ниже.</p></div>';
    }
    $text = is_array($draft) ? $draft['text'] : get_post_meta($post->ID, '_tarhan_question_text', true);

    echo '<p>Заполните вопрос и варианты, укажите номер правильного ответа и нажмите «Опубликовать» или «Обновить». Изменения применяются к новым попыткам.</p>';
    echo '<label for="tarhan-question-text">Текст вопроса</label>';

     echo '<textarea
        id="tarhan-question-text"
        name="tarhan_question_text"
        rows="5"
        class="large-text"
    >' . esc_textarea($text) . '</textarea>';

    $options = is_array($draft) ? $draft['options'] : get_post_meta($post->ID, '_tarhan_question_options', true);
    $correct = is_array($draft) ? $draft['correct'] : get_post_meta($post->ID, '_tarhan_question_correct', true);

    echo '<p><label for="tarhan-question-options">
        Варианты ответа — каждый с новой строки
    </label></p>';

    echo '<textarea
        id="tarhan-question-options"
        name="tarhan_question_options"
        rows="6"
        class="large-text"
    >' . esc_textarea($options) . '</textarea>';
    echo '<p class="description">Пишите только текст вариантов, без цифр перед ними. Первая строка — ответ № 1, вторая — № 2 и так далее. Между вариантами не оставляйте пустых строк.</p>';

    echo '<p><label for="tarhan-question-correct">
        Номер единственного правильного варианта, начиная с 1
    </label></p>';

    echo '<input
        id="tarhan-question-correct"
        name="tarhan_question_correct"
        type="number"
        min="1"
        step="1"
        class="small-text"
        aria-describedby="tarhan-correct-help"
        placeholder="Например: 2"
        value="' . esc_attr($correct) . '"
    >';
    echo '<p class="description" id="tarhan-correct-help">Например, введите 2, если правильный ответ записан во второй строке.</p>';
    echo '<div data-question-preview hidden><h3>Нумерация вариантов</h3><ol data-question-options-preview></ol><p role="status" aria-live="polite" data-question-answer-preview></p></div>';
}

add_filter('enter_title_here', function ($title, $post) {
    return $post->post_type === 'tarhan_question' ? 'Название для списка (например, «Вопрос о Казани»)' : $title;
}, 10, 2);

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'tarhan_question') { return; }
    wp_enqueue_script('tarhan-question-editor', plugins_url('assets/question-editor.js', __FILE__), array(), filemtime(__DIR__ . '/assets/question-editor.js'), true);
});

add_action('add_meta_boxes_page', function ($post) {
    if ($post->post_name !== 'test' && get_page_template_slug($post) !== 'page-test.php') { return; }
    if (!current_user_can('manage_options')) { return; }
    add_meta_box('tarhan-test-bank', 'Вопросы теста', function () {
        echo '<p>Тест получает вопросы из банка. У каждого вопроса редактируются текст, варианты и номер правильного ответа.</p><a class="button" href="' . esc_url(admin_url('edit.php?post_type=tarhan_question')) . '">Редактировать вопросы</a>';
    }, 'page', 'normal', 'high');
});

function tarhan_validate_question($text, $options_text, $correct_text)
{
    if (!is_string($text) || !is_string($options_text) || !is_string($correct_text)) {
        return new WP_Error('question_fields', 'Поля вопроса должны содержать текст.');
    }
    if (trim($text) === '') {
        return new WP_Error('question_text', 'Введите текст вопроса.');
    }

    $options = array_map('trim', preg_split('/\R/u', trim($options_text)));
    if (count($options) < 2 || in_array('', $options, true)) {
        return new WP_Error('question_options', 'Добавьте минимум два варианта, каждый с новой строки. Пустых строк между вариантами быть не должно.');
    }

    $number = trim($correct_text);
    if (str_contains($number, ',')) {
        return new WP_Error('question_single_answer', 'Укажите только один правильный вариант ответа.');
    }
    if (!preg_match('/^[1-9][0-9]*$/D', $number) || (int) $number > count($options)) {
        return new WP_Error('question_correct', 'Введите целый номер существующего варианта, начиная с 1.');
    }
    $correct = array((int) $number);

    return array('text' => $text, 'options' => $options, 'correct' => $correct);
}

function tarhan_save_question($post_id)
{
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
        get_post_type($post_id) !== 'tarhan_question' ||
        !current_user_can('edit_post', $post_id)) {
        return;
    }

    if (!isset($_POST['tarhan_question_nonce']) ||
        !is_string($_POST['tarhan_question_nonce']) ||
        !wp_verify_nonce(wp_unslash($_POST['tarhan_question_nonce']), 'tarhan_save_question')) {
        return;
    }

    $fields = array('tarhan_question_text', 'tarhan_question_options', 'tarhan_question_correct');
    foreach ($fields as $field) {
        if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
            return;
        }
    }

    $text = sanitize_textarea_field(wp_unslash($_POST['tarhan_question_text']));
    $options = sanitize_textarea_field(wp_unslash($_POST['tarhan_question_options']));
    $correct = sanitize_text_field(wp_unslash($_POST['tarhan_question_correct']));
    $validated = tarhan_validate_question($text, $options, $correct);
    $draft_key = 'tarhan_question_draft_' . get_current_user_id() . '_' . $post_id;

    if (is_wp_error($validated)) {
        set_transient($draft_key, array(
            'message' => $validated->get_error_message(),
            'text' => $text,
            'options' => $options,
            'correct' => $correct,
        ), 15 * MINUTE_IN_SECONDS);
        update_post_meta($post_id, '_tarhan_question_valid', 0);
        return;
    }

    update_post_meta($post_id, '_tarhan_question_text', wp_slash($validated['text']));
    update_post_meta($post_id, '_tarhan_question_options', wp_slash(implode("\n", $validated['options'])));
    update_post_meta($post_id, '_tarhan_question_correct', implode(', ', $validated['correct']));
    update_post_meta($post_id, '_tarhan_question_data', wp_slash($validated));
    update_post_meta($post_id, '_tarhan_question_valid', 1);
    delete_transient($draft_key);
}

add_action('save_post_tarhan_question', 'tarhan_save_question');

function tarhan_public_question($qustions){
    $result = array(
    'text' => $qustions['text'],
    'options' => $qustions['options']
    );
    if (isset($qustions['id'])) {
        $result['id'] = $qustions['id'];
    }
    return $result;
}

function tarhan_public_questions($questions){
    $result = array();   
    foreach($questions as $question){
    $value = tarhan_public_question($question);
    $result[] = $value;
    }
    return $result;
    
}

// Только для серверного кода: результат содержит правильные ответы.
// Перед отправкой участнику использовать tarhan_public_questions().
function tarhan_get_random_questions($count)
{
    if (!is_int($count) || $count < 1) {
        return new WP_Error('question_count', 'Количество вопросов должно быть положительным целым числом.');
    }

    $ids = get_posts(array(
        'post_type' => 'tarhan_question',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'rand',
        'meta_key' => '_tarhan_question_valid',
        'meta_value' => '1',
        'no_found_rows' => true,
        'tarhan_question_bank' => true,
    ));

    $questions = array();
    foreach ($ids as $id) {
        $data = get_post_meta($id, '_tarhan_question_data', true);
        if (!is_array($data) || !isset($data['text'], $data['options'], $data['correct']) ||
            !is_string($data['text']) || !is_array($data['options']) || !is_array($data['correct']) ||
            array_values($data['options']) !== $data['options'] ||
            count(array_filter($data['options'], 'is_string')) !== count($data['options']) ||
            count(array_filter($data['correct'], 'is_int')) !== count($data['correct'])) {
            continue;
        }

        $question = tarhan_validate_question($data['text'], implode("\n", $data['options']), implode(',', $data['correct']));
        if (is_wp_error($question) || $question['options'] !== $data['options']) {
            continue;
        }

        $question['id'] = (int) $id;
        $questions[] = $question;
        if (count($questions) === $count) {
            return $questions;
        }
    }

    return new WP_Error('question_bank_shortage', 'Недостаточно опубликованных корректных вопросов для начала теста.');
}

require_once __DIR__ . '/tarhan-attempts.php';
require_once __DIR__ . '/tarhan-participant.php';
require_once __DIR__ . '/tarhan-geography.php';
require_once __DIR__ . '/tarhan-seasons.php';
require_once __DIR__ . '/tarhan-enrollments.php';
require_once __DIR__ . '/tarhan-auth.php';
require_once __DIR__ . '/tarhan-essay.php';
require_once __DIR__ . '/tarhan-organizers.php';
require_once __DIR__ . '/tarhan-mail.php';


function tarhan_calculate_score($questions, $answers)
{
    $count = 0;
    for($i = 0;$i<count($questions);$i++) {
    if (
    (isset($answers[$questions[$i]['id']])) &&    
    (($questions[$i]['correct'][0]) === ($answers[$questions[$i]['id']]))) {
        $count++;
    }

}
return $count;
}
