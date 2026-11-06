<?php
defined('ABSPATH') || exit;

function tarhan_rich_allowed($block = true)
{
    $tags = array_fill_keys(array('strong', 'b', 'em', 'i', 'u', 's', 'del', 'br', 'mark', 'sub', 'sup'), array());
    $tags['span'] = array('style' => true);
    if ($block) {
        foreach (array('p', 'ul', 'ol', 'li', 'blockquote') as $tag) { $tags[$tag] = array(); }
        $tags['a'] = array('href' => true, 'title' => true);
    }
    return $tags;
}

function tarhan_rich_sanitize($value, $block = true)
{
    return wp_kses($value, tarhan_rich_allowed($block), array('http', 'https', 'mailto', 'tel'));
}

function tarhan_rich_render($value, $block = true)
{
    $html = tarhan_rich_sanitize($value, $block);
    // Historical fields use literal newlines. Formatted paragraphs already have their own spacing.
    return preg_match('/<(?:p|ul|ol|blockquote)\b/i', $html) ? $html : nl2br($html);
}

function tarhan_rich_field($id, $name, $value, $block = true)
{
    echo '<textarea class="large-text" data-tarhan-rich="' . ($block ? 'block' : 'inline') . '" id="' .
        esc_attr($id) . '" name="' . esc_attr($name) . '" rows="4">' . esc_textarea($value) . '</textarea>';
}

add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    if ($hook !== 'pages_page_tarhan-home' && (!$screen || $screen->base !== 'post' || $screen->post_type !== 'page')) { return; }
    wp_enqueue_editor();
    wp_enqueue_script('tarhan-rich-editor', get_theme_file_uri('/assets/js/rich-editor.js'), array('editor', 'quicktags'),
        filemtime(get_theme_file_path('/assets/js/rich-editor.js')), true);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('tarhan-rich-text', get_theme_file_uri('/assets/css/rich-text.css'), array(),
        filemtime(get_theme_file_path('/assets/css/rich-text.css')));
}, 40);
