<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') { throw new RuntimeException('Local site only'); }
foreach (array('tarhan_install_attempts', 'tarhan_install_essays') as $install) {
    $result = $install();
    if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
}
foreach (array('registration' => 'Регистрация участника', 'entrance' => 'Вход участника',
    'forgot-password' => 'Восстановление пароля', 'reset-password' => 'Новый пароль', 'essay' => 'Эссе') as $slug => $title) {
    $existing = get_page_by_path($slug);
    if ($existing) {
        if ($existing->post_status !== 'publish') { throw new RuntimeException('Existing unpublished page requires review: ' . $slug); }
        echo "EXISTS $slug\n";
        continue;
    }
    $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title), true);
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    echo "CREATED $slug\n";
}
require __DIR__ . '/install-local-policy.php';
flush_rewrite_rules(false);
echo 'Local address directory: ' . (is_wp_error(tarhan_geography_regions()) ? 'unavailable' : 'ready (no DaData key required)') . "\n";
