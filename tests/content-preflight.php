<?php
/** Read-only check before copying content templates into the running site. */
$ready = false;
register_shutdown_function(function () use (&$ready) { if (!$ready) { exit(1); } });
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local' || get_stylesheet() !== 'tarhan') {
    throw new RuntimeException('Expected local Tarhan WordPress site');
}
global $wpdb;
if ((string) $wpdb->get_var('SELECT 1') !== '1') { throw new RuntimeException('Database unavailable'); }
$ready = true;
echo "Local WordPress and database ready.\n";
