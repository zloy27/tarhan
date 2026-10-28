<?php
/** Structured content fields in the standard WordPress page editor. */
defined('ABSPATH') || exit;

function tarhan_content_kind($post = null)
{
    $post = get_post($post);
    if (!$post || $post->post_type !== 'page') { return ''; }
    $templates = array('page-jury.php' => 'jury', 'page-ambassadors.php' => 'ambassadors', 'page-season.php' => 'season',
        'page-archive.php' => 'archive', 'page-preparation.php' => 'participant', 'page-information.php' => 'information');
    $template = get_page_template_slug($post);
    if (isset($templates[$template])) { return $templates[$template]; }
    return in_array($post->post_name, array('jury', 'ambassadors', 'archive', 'participant', 'information'), true) ? $post->post_name : '';
}

function tarhan_content_schema($kind)
{
    $person = array('name' => array('Имя и фамилия', 'text'), 'role' => array('Должность / род занятий', 'textarea'),
        'description' => array('Описание', 'textarea'), 'image' => array('Фотография', 'image'));
    if (in_array($kind, array('jury', 'ambassadors'), true)) {
        return array('fields' => array('subtitle' => array('Надзаголовок', 'text'), 'intro' => array('Вступление', 'textarea')),
            'groups' => array('people' => array('Люди на странице', 'Добавить человека', $person, 'name')));
    }
    if ($kind !== 'season') { return array('fields' => array(), 'groups' => array()); }
    return array('fields' => array(
        'period' => array('Годы сезона, например 2021–2022', 'text'),
        'cover' => array('Обложка для архива', 'image'), 'intro' => array('Краткое вступление', 'textarea'),
        'process_before' => array('Как проходил конкурс — перед этапами', 'textarea'),
        'process_after' => array('Как проходил конкурс — после этапов', 'textarea'),
        'final_date' => array('Дата финала', 'date'), 'results_text' => array('Текст об итогах', 'textarea'),
        'watch_text' => array('Где посмотреть — пояснение', 'textarea')),
        'groups' => array(
            'stats' => array('Цифры сезона', 'Добавить показатель', array('value' => array('Значение', 'text'), 'label' => array('Подпись', 'text')), 'value'),
            'stages' => array('Этапы конкурса', 'Добавить этап', array('label' => array('Название этапа', 'text')), 'label'),
            'jury' => array('Жюри этого сезона', 'Добавить члена жюри', array('name' => array('Имя', 'text'), 'role' => array('Должность', 'textarea')), 'name'),
            'finalists' => array('Финалисты', 'Добавить финалиста', array('name' => array('Имя', 'text'), 'city' => array('Город / район', 'text'), 'result' => array('Место / результат', 'text')), 'name'),
            'watch' => array('Ссылки на выпуски', 'Добавить ссылку', array('label' => array('Подпись', 'text'), 'url' => array('Адрес ссылки', 'url')), 'url'),
            'gallery' => array('Фотографии', 'Добавить фотографию', array('image' => array('Фотография', 'image'), 'alt' => array('Описание фотографии', 'text')), 'image'),
            'media' => array('СМИ о нас', 'Добавить публикацию', array('label' => array('Название публикации', 'text'), 'url' => array('Адрес публикации', 'url')), 'url')
        ));
}

function tarhan_content_value($value, $type)
{
    if (!is_scalar($value)) { return ''; }
    if ($type === 'image') {
        $id = absint($value);
        return $id && wp_attachment_is_image($id) ? $id : 0;
    }
    $value = (string) $value;
    if ($type === 'url') { return esc_url_raw($value, array('http', 'https')); }
    if ($type === 'date') {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $date)) { return ''; }
        return checkdate((int) $date[2], (int) $date[3], (int) $date[1]) ? $value : '';
    }
    return $type === 'textarea' ? sanitize_textarea_field($value) : sanitize_text_field($value);
}

function tarhan_sanitize_page_content($raw, $kind)
{
    $schema = tarhan_content_schema($kind);
    $raw = is_array($raw) ? $raw : array();
    $clean = array();
    foreach ($schema['fields'] as $key => $field) { $clean[$key] = tarhan_content_value($raw[$key] ?? '', $field[1]); }
    foreach ($schema['groups'] as $key => $group) {
        $clean[$key] = array();
        foreach (is_array($raw[$key] ?? null) ? $raw[$key] : array() as $row) {
            if (!is_array($row)) { continue; }
            $item = array();
            foreach ($group[2] as $field => $spec) { $item[$field] = tarhan_content_value($row[$field] ?? '', $spec[1]); }
            if ($item[$group[3]] !== '' && $item[$group[3]] !== 0) { $clean[$key][] = $item; }
        }
    }
    return $clean;
}

function tarhan_page_content($post_id = null)
{
    $post_id = $post_id ?: get_the_ID();
    $data = get_post_meta($post_id, '_tarhan_page_content', true);
    return tarhan_sanitize_page_content($data, tarhan_content_kind($post_id));
}

function tarhan_editor_field($name, $field, $value = '')
{
    $id = 'tarhan-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
    echo '<div class="tarhan-field"><label for="' . esc_attr($id) . '">' . esc_html($field[0]) . '</label>';
    if ($field[1] === 'textarea') {
        echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="3">' . esc_textarea($value) . '</textarea>';
    } elseif ($field[1] === 'image') {
        $url = $value ? wp_get_attachment_image_url((int) $value, 'thumbnail') : '';
        echo '<div class="tarhan-image"><input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        echo '<img alt="Предпросмотр выбранной фотографии"' . ($url ? ' src="' . esc_url($url) . '"' : ' hidden') . '>';
        echo '<button type="button" class="button" data-pick-image>Выбрать фотографию</button> <button type="button" class="button-link-delete" data-clear-image>Убрать фото</button></div>';
    } else {
        echo '<input id="' . esc_attr($id) . '" type="' . esc_attr($field[1]) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
    }
    echo '</div>';
}

function tarhan_editor_row($key, $fields, $index, $values)
{
    echo '<fieldset class="tarhan-row"><legend>Запись <span data-row-number>' . esc_html(is_numeric($index) ? $index + 1 : '') . '</span></legend>';
    foreach ($fields as $name => $field) { tarhan_editor_field('tarhan_data[' . $key . '][' . $index . '][' . $name . ']', $field, $values[$name] ?? ''); }
    echo '<div class="tarhan-row-actions"><button type="button" class="button" data-move="up">Выше</button> <button type="button" class="button" data-move="down">Ниже</button> <button type="button" class="button-link-delete" data-remove-row>Удалить запись</button></div></fieldset>';
}

function tarhan_content_metabox($post)
{
    $kind = tarhan_content_kind($post);
    wp_nonce_field('tarhan_content_' . $post->ID, 'tarhan_content_nonce');
    echo '<input type="hidden" name="tarhan_content_kind" value="' . esc_attr($kind) . '">';
    echo '<p>Заголовок страницы редактируется сверху. Добавляйте записи ниже и нажмите «Обновить» или «Опубликовать». Пустые записи не выводятся на сайте.</p>';
    if (get_post_meta($post->ID, '_tarhan_source_pending', true)) {
        echo '<p><strong>Материалы старого сайта ещё не перенесены.</strong> Заполните поля проверенными сведениями перед публикацией.</p>';
    }
    if ($source_note = get_post_meta($post->ID, '_tarhan_source_note', true)) {
        echo '<p>' . esc_html($source_note) . '</p>';
    }
    $schema = tarhan_content_schema($kind);
    $data = tarhan_page_content($post->ID);
    echo '<div class="tarhan-editor" data-tarhan-editor>';
    foreach ($schema['fields'] as $key => $field) { tarhan_editor_field('tarhan_data[' . $key . ']', $field, $data[$key] ?? ''); }
    foreach ($schema['groups'] as $key => $group) {
        echo '<section class="tarhan-group" data-group="' . esc_attr($key) . '"><h3>' . esc_html($group[0]) . '</h3><div data-rows>';
        foreach ($data[$key] ?? array() as $index => $row) { tarhan_editor_row($key, $group[2], $index, $row); }
        echo '</div><template>';
        tarhan_editor_row($key, $group[2], '__INDEX__', array());
        echo '</template><button type="button" class="button button-secondary" data-add-row>' . esc_html($group[1]) . '</button></section>';
    }
    echo '<p role="status" data-editor-status></p></div>';
    // End marker prevents silently deleting rows if PHP truncates a large form.
    echo '<input type="hidden" name="tarhan_content_complete" value="1">';
}

function tarhan_save_page_content($post_id, $post)
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { return; }
    if (!current_user_can('edit_post', $post_id) || !isset($_POST['tarhan_content_nonce']) ||
        !is_string($_POST['tarhan_content_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tarhan_content_nonce'])), 'tarhan_content_' . $post_id)) { return; }
    $kind = tarhan_content_kind($post);
    if (!in_array($kind, array('jury', 'ambassadors', 'season'), true) || ($_POST['tarhan_content_kind'] ?? '') !== $kind) { return; }
    if (($_POST['tarhan_content_complete'] ?? '') !== '1') {
        set_transient('tarhan_content_error_' . get_current_user_id(), 'Поля не сохранены: сервер обрезал большую форму. Предыдущие данные сохранены. Увеличьте max_input_vars и повторите сохранение.', 120);
        return;
    }
    $data = tarhan_sanitize_page_content(wp_unslash($_POST['tarhan_data'] ?? array()), $kind);
    update_post_meta($post_id, '_tarhan_page_content', wp_slash($data));
}
add_action('save_post_page', 'tarhan_save_page_content', 20, 2);

add_filter('use_block_editor_for_post', function ($use, $post) {
    return in_array(tarhan_content_kind($post), array('jury', 'ambassadors', 'season'), true) ? false : $use;
}, 10, 2);

add_action('add_meta_boxes_page', function ($post) {
    if (in_array(tarhan_content_kind($post), array('jury', 'ambassadors', 'season'), true)) {
        remove_post_type_support('page', 'editor');
        add_meta_box('tarhan-page-content', 'Содержимое страницы «Тархан»', 'tarhan_content_metabox', 'page', 'normal', 'high');
    }
    if (in_array(tarhan_content_kind($post), array('archive', 'season'), true)) {
        add_meta_box('tarhan-add-season', 'Архив сезонов', function () {
            $url = wp_nonce_url(admin_url('admin-post.php?action=tarhan_add_season'), 'tarhan_add_season');
            echo '<p>Опубликованные страницы с шаблоном «Сезон конкурса» автоматически появляются в архиве.</p><a class="button" href="' . esc_url($url) . '">Создать новый сезон</a>';
        }, 'page', 'side');
    }
});

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'page') { return; }
    global $post;
    if (!in_array(tarhan_content_kind($post), array('jury', 'ambassadors', 'season'), true)) { return; }
    wp_enqueue_media(array('post' => $post->ID));
    wp_enqueue_script('tarhan-content-editor', get_theme_file_uri('/assets/js/content-editor.js'), array('media-editor'), filemtime(get_theme_file_path('/assets/js/content-editor.js')), true);
    wp_enqueue_style('tarhan-content-editor', get_theme_file_uri('/assets/css/content-editor.css'), array(), filemtime(get_theme_file_path('/assets/css/content-editor.css')));
});

add_action('admin_notices', function () {
    $key = 'tarhan_content_error_' . get_current_user_id();
    if ($message = get_transient($key)) { delete_transient($key); echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>'; }
});

add_action('admin_post_tarhan_add_season', function () {
    if (!current_user_can('edit_pages')) { wp_die('Недостаточно прав.', '', array('response' => 403)); }
    check_admin_referer('tarhan_add_season');
    $archive = get_page_by_path('archive');
    $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Новый сезон', 'post_parent' => $archive ? $archive->ID : 0,
        'meta_input' => array('_wp_page_template' => 'page-season.php')), true);
    if (is_wp_error($id)) { wp_die(esc_html($id->get_error_message())); }
    wp_safe_redirect(admin_url('post.php?post=' . $id . '&action=edit'));
    exit;
});
