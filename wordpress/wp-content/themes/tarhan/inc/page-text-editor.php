<?php
defined('ABSPATH') || exit;

function tarhan_page_text_source($post)
{
    $post = get_post($post);
    if (!$post) { return ''; }
    $kind = tarhan_content_kind($post);
    if (in_array($kind, array('information', 'participant'), true)) {
        return file_get_contents(get_theme_file_path('/content/' . $kind . '.html'));
    }
    if (get_page_template_slug($post) === 'page-privacy-policy.php' || $post->post_name === 'privacy-policy') {
        return file_get_contents(get_theme_file_path('/privacy-policy-content.html'));
    }
    return '';
}

function tarhan_page_text_pattern()
{
    // Select text blocks only; preserve the page layout, forms, images and navigation.
    return '~<(p|h[1-6]|summary|address)\b([^>]*)>((?:(?!</?(?:div|section|article|ul|ol|li|p|h[1-6])\b).)*?)</\1>~is';
}

function tarhan_page_text_blocks($html)
{
    preg_match_all(tarhan_page_text_pattern(), $html, $matches, PREG_SET_ORDER);
    $blocks = array();
    foreach ($matches as $match) {
        if (trim(wp_strip_all_tags($match[3])) !== '') {
            $blocks[sha1($match[0])] = $match[3];
        }
    }
    return $blocks;
}

function tarhan_page_text_render($html, $id = null)
{
    $saved = get_post_meta($id ?: get_queried_object_id(), '_tarhan_text_blocks', true);
    if (!is_array($saved)) { return $html; }
    return preg_replace_callback(tarhan_page_text_pattern(), function ($match) use ($saved) {
        $key = sha1($match[0]);
        if (!array_key_exists($key, $saved)) { return $match[0]; }
        return '<' . $match[1] . $match[2] . '>' . tarhan_rich_render($saved[$key], false) . '</' . $match[1] . '>';
    }, $html);
}

add_filter('use_block_editor_for_post', function ($use, $post) {
    return tarhan_page_text_source($post) !== '' ? false : $use;
}, 20, 2);

add_action('add_meta_boxes_page', function ($post) {
    if (tarhan_page_text_source($post) === '') { return; }
    remove_post_type_support('page', 'editor');
    add_meta_box('tarhan-page-text', 'Тексты страницы', function ($post) {
        wp_nonce_field('tarhan_text_' . $post->ID, 'tarhan_text_nonce');
        $saved = get_post_meta($post->ID, '_tarhan_text_blocks', true);
        echo '<p>Выберите поле, чтобы открыть панель форматирования. После редактирования нажмите «Обновить».</p>';
        foreach (tarhan_page_text_blocks(tarhan_page_text_source($post)) as $key => $text) {
            echo '<div class="tarhan-field"><label for="tarhan-text-' . esc_attr($key) . '"><strong>' .
                esc_html(wp_trim_words(wp_strip_all_tags($text), 12, '…')) . '</strong></label>';
            tarhan_rich_field('tarhan-text-' . $key, 'tarhan_text[' . $key . ']', $saved[$key] ?? $text, false);
            echo '</div>';
        }
        echo '<input type="hidden" name="tarhan_text_complete" value="1">';
    }, 'page', 'normal', 'high');
});

add_action('save_post_page', function ($id) {
    if (wp_is_post_revision($id) || wp_is_post_autosave($id) || !current_user_can('edit_post', $id) ||
        ($_POST['tarhan_text_complete'] ?? '') !== '1' || !isset($_POST['tarhan_text_nonce']) ||
        !is_string($_POST['tarhan_text_nonce']) || !wp_verify_nonce(wp_unslash($_POST['tarhan_text_nonce']), 'tarhan_text_' . $id)) { return; }
    $raw = wp_unslash($_POST['tarhan_text'] ?? array());
    if (!is_array($raw)) { return; }
    $clean = array();
    foreach (tarhan_page_text_blocks(tarhan_page_text_source($id)) as $key => $original) {
        if (!isset($raw[$key]) || !is_string($raw[$key])) { continue; }
        $value = tarhan_rich_sanitize($raw[$key], false);
        if ($value !== tarhan_rich_sanitize($original, false)) { $clean[$key] = $value; }
    }
    update_post_meta($id, '_tarhan_text_blocks', wp_slash($clean));
});
