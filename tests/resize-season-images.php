<?php
// Local-only generation of responsive copies. Original photographs are retained.
$completed = false;
register_shutdown_function(function () use (&$completed) { if (!$completed) { exit(1); } });
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local' || !extension_loaded('gd')) { throw new RuntimeException('Local and GD required'); }
require_once ABSPATH . 'wp-admin/includes/image.php';
$count = 0;
foreach (['2022-2023','2023-2024','2024-2025'] as $period) {
    $page = get_page_by_path('archive/' . $period);
    foreach (tarhan_page_content($page->ID)['gallery'] as $photo) {
        $id = $photo['image'];
        $metadata = wp_get_attachment_metadata($id);
        if (empty($metadata['sizes']['large'])) {
            $metadata = wp_generate_attachment_metadata($id, get_attached_file($id));
            if (empty($metadata['sizes'])) { throw new RuntimeException('No responsive sizes for ' . $id); }
            wp_update_attachment_metadata($id, $metadata);
        }
        $image = wp_get_attachment_image_src($id, 'large');
        if (!$image || $image[1] > 1600 || !wp_get_attachment_image_srcset($id, 'large')) { throw new RuntimeException('Responsive image check failed: ' . $id); }
        $count++;
    }
    echo $period . ": responsive images ready\n";
}
echo $count . " images verified\n";
$completed = true;
