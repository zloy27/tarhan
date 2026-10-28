<?php
defined('ABSPATH') || exit;

function tarhan_content_page_url($slug)
{
    $page = get_page_by_path($slug);
    return $page ? get_permalink($page) : home_url('/' . trim($slug, '/') . '/');
}

function tarhan_content_image($id, $class, $alt = '')
{
    if (!$id) { return; }
    echo wp_get_attachment_image((int) $id, 'large', false, array('class' => $class, 'alt' => $alt, 'loading' => 'lazy', 'decoding' => 'async'));
}

function tarhan_content_paragraphs($text)
{
    if ($text !== '') { echo wpautop(esc_html($text)); }
}

function tarhan_render_migrated_html($name)
{
    if (!in_array($name, array('information', 'participant'), true)) { return; }
    $html = file_get_contents(get_theme_file_path('/content/' . $name . '.html'));
    $html = preg_replace_callback('/\b(href|src)="\.\/([^"<>]+)"/', function ($match) {
        $target = $match[2];
        if (str_starts_with($target, 'assets/') || str_starts_with($target, 'documents/')) {
            $url = get_theme_file_uri('/participant-dist/' . $target);
        } else {
            $parts = explode('#', $target, 2);
            $slugs = array('index.html' => '', 'information.html' => 'information', 'participant.html' => 'participant', 'jury.html' => 'jury',
                'ambassadors.html' => 'ambassadors', 'archive.html' => 'archive', 'season.html' => 'archive/2021-2022',
                'entrance.html' => 'entrance', 'registration.html' => 'registration', 'privacy-policy.html' => 'privacy-policy');
            if (!array_key_exists($parts[0], $slugs)) { return $match[0]; }
            $url = $slugs[$parts[0]] === '' ? home_url('/') : tarhan_content_page_url($slugs[$parts[0]]);
            if (isset($parts[1])) { $url .= '#' . $parts[1]; }
        }
        return $match[1] . '="' . esc_url($url) . '"';
    }, $html);
    echo $html;
}

add_filter('template_include', function ($template) {
    $kind = tarhan_content_kind(get_queried_object());
    $templates = array('jury' => 'page-jury.php', 'ambassadors' => 'page-ambassadors.php', 'season' => 'page-season.php',
        'archive' => 'page-archive.php', 'participant' => 'page-preparation.php', 'information' => 'page-information.php');
    return isset($templates[$kind]) ? get_theme_file_path('/' . $templates[$kind]) : $template;
}, 20);

add_action('wp_enqueue_scripts', function () {
    $kind = tarhan_content_kind(get_queried_object());
    if (!$kind) { return; }
    $path = get_theme_file_path('/participant-dist/.vite/manifest.json');
    if (!is_file($path)) { return; }
    $manifest = json_decode(file_get_contents($path), true);
    $entry = $kind . '.html';
    if (!isset($manifest[$entry])) { return; }
    $visited = array();
    $visit = function ($key) use (&$visit, &$visited, $manifest) {
        if (isset($visited[$key]) || !isset($manifest[$key])) { return; }
        $visited[$key] = true;
        foreach ($manifest[$key]['imports'] ?? array() as $dependency) { $visit($dependency); }
        foreach ($manifest[$key]['css'] ?? array() as $css) {
            wp_enqueue_style('tarhan-content-' . md5($css), get_theme_file_uri('/participant-dist/' . $css), array('tarhan-theme'), null);
        }
    };
    $visit($entry);
    wp_enqueue_script_module('tarhan-content-page', get_theme_file_uri('/participant-dist/' . $manifest[$entry]['file']), array(), null);
    wp_enqueue_style('tarhan-content-page', get_theme_file_uri('/assets/css/content-pages.css'), array('tarhan-theme'), filemtime(get_theme_file_path('/assets/css/content-pages.css')));
});

// Known former HTML links continue to resolve after the WordPress migration.
add_action('template_redirect', function () {
    if (!is_404() || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', array('GET', 'HEAD'), true)) { return; }
    $path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $base = rtrim(wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '', '/');
    $map = array('jury' => 'jury', 'ambassadors' => 'ambassadors', 'archive' => 'archive', 'season' => 'archive/2021-2022',
        'participant' => 'participant', 'information' => 'information', 'registration' => 'registration', 'entrance' => 'entrance', 'privacy-policy' => 'privacy-policy');
    foreach ($map as $old => $slug) {
        if ($path === $base . '/' . $old . '.html' && get_page_by_path($slug)) {
            wp_safe_redirect(tarhan_content_page_url($slug), 301);
            exit;
        }
    }
});
