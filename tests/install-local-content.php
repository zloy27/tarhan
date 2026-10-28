<?php
/** Idempotent local migration. Existing authored content is never replaced. */
$content_completed = false;
register_shutdown_function(function () use (&$content_completed) { if (!$content_completed) { exit(1); } });
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local' || get_stylesheet() !== 'tarhan') { throw new RuntimeException('Expected local Tarhan site'); }
$backup_dir = getenv('TARHAN_CONTENT_BACKUP');
if (!$backup_dir || !is_dir($backup_dir)) { throw new RuntimeException('Create a backup directory before installation'); }
$root = dirname(__DIR__);
$specs = array(
    'archive' => array('Архив сезонов', 'page-archive.php', 'publish'),
    'jury' => array('Жюри конкурса', 'page-jury.php', 'publish'),
    'ambassadors' => array('Амбассадоры', 'page-ambassadors.php', 'publish'),
    'participant' => array('Участнику', 'page-preparation.php', 'publish'),
    'information' => array('Контакты и документы', 'page-information.php', 'publish'),
    'archive/2021-2022' => array('Сезон 2021–2022', 'page-season.php', 'publish'),
    'archive/2022-2023' => array('Сезон 2022–2023', 'page-season.php', 'draft'),
    'archive/2023-2024' => array('Сезон 2023–2024', 'page-season.php', 'draft'),
    'archive/2024-2025' => array('Сезон 2024–2025', 'page-season.php', 'draft'),
);
$before = array();
foreach ($specs as $path => $spec) {
    $page = get_page_by_path($path);
    if (!$page) { continue; }
    $template = get_page_template_slug($page);
    if (($template && $template !== 'default' && $template !== $spec[1]) || trim($page->post_content) !== '') {
        throw new RuntimeException('Existing custom page needs explicit migration: ' . $path);
    }
    $before[$path] = array('post' => $page, 'meta' => get_post_meta($page->ID));
}
if (file_put_contents($backup_dir . '/pages-before.json', wp_json_encode($before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) { throw new RuntimeException('Cannot save page backup'); }
$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
if (!$admins) { throw new RuntimeException('No local administrator'); }
wp_set_current_user((int) $admins[0]);
$ids = array();
foreach ($specs as $path => $spec) {
    $page = get_page_by_path($path);
    if (!$page) {
        $id = wp_insert_post(array('post_type' => 'page', 'post_title' => $spec[0], 'post_name' => basename($path), 'post_status' => $spec[2],
            'post_parent' => str_starts_with($path, 'archive/') ? $ids['archive'] : 0,
            'meta_input' => array('_wp_page_template' => $spec[1])), true);
        if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    } else {
        $id = $page->ID;
        update_post_meta($id, '_wp_page_template', $spec[1]);
    }
    $ids[$path] = $id;
    echo $path . ': ' . $id . ' (' . get_post_status($id) . ")\n";
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
function content_import_image($relative, $root, $parent) {
    $existing = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1,
        'meta_key' => '_tarhan_source_file', 'meta_value' => $relative, 'fields' => 'ids'));
    if ($existing && wp_attachment_is_image($existing[0])) { return $existing[0]; }
    $path = realpath($root . '/' . $relative);
    $allowed = strtolower(wp_normalize_path(realpath($root . '/src/assets/photo'))) . '/';
    if (!$path || !str_starts_with(strtolower(wp_normalize_path($path)), $allowed)) { throw new RuntimeException('Invalid source image: ' . $relative); }
    $tmp = wp_tempnam(basename($path));
    if (!$tmp || !copy($path, $tmp)) { throw new RuntimeException('Image copy failed'); }
    $id = media_handle_sideload(array('name' => basename($path), 'tmp_name' => $tmp), $parent);
    if (is_wp_error($id)) { if (is_file($tmp)) { unlink($tmp); } throw new RuntimeException($id->get_error_message()); }
    update_post_meta($id, '_tarhan_source_file', $relative);
    return $id;
}

$season_id = $ids['archive/2021-2022'];
if (!metadata_exists('post', $season_id, '_tarhan_page_content')) {
    $data = json_decode(file_get_contents(get_theme_file_path('/content/season-2021-2022.json')), true, 512, JSON_THROW_ON_ERROR);
    $data['cover'] = content_import_image($data['cover'], $root, $season_id);
    foreach ($data['gallery'] as &$photo) {
        $photo['image'] = content_import_image($photo['image'], $root, $season_id);
        update_post_meta($photo['image'], '_wp_attachment_image_alt', $photo['alt']);
    }
    unset($photo);
    update_post_meta($season_id, '_tarhan_page_content', wp_slash(tarhan_sanitize_page_content($data, 'season')));
}
foreach (array('jury' => 'Экспертный совет', 'ambassadors' => 'Лица конкурса') as $slug => $subtitle) {
    if (!metadata_exists('post', $ids[$slug], '_tarhan_page_content')) {
        update_post_meta($ids[$slug], '_tarhan_page_content', array('subtitle' => $subtitle, 'intro' => '', 'people' => array()));
        update_post_meta($ids[$slug], '_tarhan_source_pending', '1');
    }
}
foreach (array('2022-2023', '2023-2024', '2024-2025') as $period) {
    $id = $ids['archive/' . $period];
    if (!metadata_exists('post', $id, '_tarhan_page_content')) {
        update_post_meta($id, '_tarhan_page_content', array('period' => str_replace('-', '–', $period)));
        update_post_meta($id, '_tarhan_source_pending', '1');
    }
}
flush_rewrite_rules(false);
file_put_contents($backup_dir . '/page-ids.json', wp_json_encode($ids, JSON_PRETTY_PRINT));
echo "Content pages installed. Pending source material remains empty/draft.\n";
$content_completed = true;
