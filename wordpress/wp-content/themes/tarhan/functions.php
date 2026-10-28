<?php

require_once __DIR__ . '/inc/content-editor.php';
require_once __DIR__ . '/inc/content-pages.php';

function tarhan_enqueue_assets()
{
    $manifest_path = get_theme_file_path('/dist/.vite/manifest.json');

    if (!file_exists($manifest_path)) {
        return;
    }

    $manifest = json_decode(file_get_contents($manifest_path), true);
    $entry = $manifest['index.html'] ?? null;

    if (!$entry) {
        return;
    }

    $styles = array();

    foreach ($entry['imports'] ?? array() as $import) {
        $styles = array_merge(
            $styles,
            $manifest[$import]['css'] ?? array()
        );
    }

    $styles = array_merge($styles, $entry['css'] ?? array());

    foreach (array_unique($styles) as $index => $file) {
        wp_enqueue_style(
            'tarhan-css-' . $index,
            get_theme_file_uri('/dist/' . $file),
            array(),
            null
        );
    }
    wp_enqueue_style(
        'tarhan-theme',
        get_stylesheet_uri(),
        array(),
        filemtime(get_stylesheet_directory() . '/style.css')
    );

    if (tarhan_content_kind(get_queried_object()) || is_page(array('registration', 'entrance', 'forgot-password', 'reset-password', 'essay', 'privacy-policy'))) { return; }
    wp_enqueue_script_module(
        'tarhan-main',
        get_theme_file_uri('/dist/' . $entry['file']),
        array(),
        null
    );
}

add_action('wp_enqueue_scripts', 'tarhan_enqueue_assets');

function tarhan_enqueue_test_assets()
{
    if (!is_page('test') && !is_page_template('page-test.php')) {
        return;
    }

    wp_enqueue_style(
        'tarhan-test',
        get_theme_file_uri('/assets/css/competition-test.css'),
        array('tarhan-theme'),
        filemtime(get_theme_file_path('/assets/css/competition-test.css'))
    );

    wp_enqueue_script_module(
        'tarhan-test',
        get_theme_file_uri('/assets/js/competition-test-server.js'),
        array(),
        filemtime(get_theme_file_path('/assets/js/competition-test-server.js'))
    );
}

add_action('wp_enqueue_scripts', 'tarhan_enqueue_test_assets');

function tarhan_test_demo_robots($robots)
{
    if (is_page('test') || is_page_template('page-test.php')) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
    }

    return $robots;
}

add_filter('wp_robots', 'tarhan_test_demo_robots');

function tarhan_enqueue_participant_assets()
{
    if (!is_page(array('registration', 'entrance', 'forgot-password', 'reset-password', 'essay'))) { return; }
    $manifest_path = get_theme_file_path('/participant-dist/.vite/manifest.json');
    if (!is_file($manifest_path)) { return; }
    $manifest = json_decode(file_get_contents($manifest_path), true);
    $slug = get_post_field('post_name', get_queried_object_id());
    $account_screen = is_user_logged_in() && in_array($slug, array('entrance', 'registration'), true);
    if ($slug === 'registration' && is_user_logged_in() && function_exists('tarhan_needs_enrollment') && tarhan_needs_enrollment(get_current_user_id())) { $account_screen = false; }
    $entry_name = $slug === 'essay' || $account_screen ? 'registration.html' : $slug . '.html';
    // Vite shares one password module between both HTML pages; it has no HTML entry key.
    if (in_array($slug, array('forgot-password', 'reset-password'), true)) {
        foreach ($manifest as $name => $item) {
            if (($item['name'] ?? '') === 'password') { $entry_name = $name; break; }
        }
    }
    $entry = $manifest[$entry_name] ?? null;
    if (!$entry) { return; }
    $visit = function ($name) use (&$visit, $manifest) {
        foreach ($manifest[$name]['imports'] ?? array() as $import) { $visit($import); }
        foreach ($manifest[$name]['css'] ?? array() as $css) {
            wp_enqueue_style('tarhan-participant-' . md5($css), get_theme_file_uri('/participant-dist/' . $css), array('tarhan-theme'), null);
        }
    };
    $visit($entry_name);
    $registration_closed = $slug === 'registration' && function_exists('tarhan_registration_season') && is_wp_error(tarhan_registration_season());
    if ($slug !== 'essay' && !$account_screen && !$registration_closed) {
        wp_enqueue_script_module('tarhan-participant-form', get_theme_file_uri('/participant-dist/' . $entry['file']), array(), null);
    }
    wp_enqueue_script_module('tarhan-participant-common', get_theme_file_uri('/assets/js/participant-account.js'), array(), filemtime(get_theme_file_path('/assets/js/participant-account.js')));
}
add_action('wp_enqueue_scripts', 'tarhan_enqueue_participant_assets');

function tarhan_enqueue_footer_assets()
{
    wp_enqueue_style(
        'tarhan-footer',
        get_theme_file_uri('/assets/css/footer.css'),
        array('tarhan-theme'),
        filemtime(get_theme_file_path('/assets/css/footer.css'))
    );
}
add_action('wp_enqueue_scripts', 'tarhan_enqueue_footer_assets', 20);

// Load shared navigation last so older page bundles cannot override its colors.
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('tarhan-header', get_theme_file_uri('/assets/css/header.css'), array('tarhan-theme'), filemtime(get_theme_file_path('/assets/css/header.css')));
    if (is_user_logged_in() && is_page(array('entrance', 'registration'))) {
        wp_enqueue_style('tarhan-account', get_theme_file_uri('/assets/css/participant-account.css'), array('tarhan-theme'), filemtime(get_theme_file_path('/assets/css/participant-account.css')));
    }
}, 30);

function tarhan_enqueue_policy_assets()
{
    if (!is_page('privacy-policy')) { return; }
    wp_enqueue_style('tarhan-policy', get_theme_file_uri('/assets/css/privacy-policy.css'), array('tarhan-theme'), filemtime(get_theme_file_path('/assets/css/privacy-policy.css')));
    wp_enqueue_script_module('tarhan-policy', get_theme_file_uri('/assets/js/privacy-policy-page.js'), array(), filemtime(get_theme_file_path('/assets/js/privacy-policy-page.js')));
}
add_action('wp_enqueue_scripts', 'tarhan_enqueue_policy_assets');

add_filter('template_include', function ($template) {
    if (is_page(array('registration', 'entrance', 'forgot-password', 'reset-password', 'essay'))) {
        return get_theme_file_path('/page-participant.php');
    }
    return $template;
});

add_filter('wp_robots', function ($robots) {
    if (is_page(array('registration', 'entrance', 'forgot-password', 'reset-password', 'essay'))) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
    }
    return $robots;
});

add_filter('show_admin_bar', function ($show) {
    return current_user_can('edit_posts') ? $show : false;
});
require_once __DIR__ . '/inc/cookie-banner.php';
