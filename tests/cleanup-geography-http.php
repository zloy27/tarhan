<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') { throw new RuntimeException('Local test only'); }
$path = __DIR__ . '/../.geography-test.local';
if (!is_file($path)) { echo "No geography HTTP fixture.\n"; exit; }
$fixture = json_decode(file_get_contents($path), true);
if (!preg_match('/^[a-f0-9]{32}$/D', $fixture['marker'] ?? '') || $fixture['email'] !== 'geo_http_' . $fixture['marker'] . '@example.invalid') {
    throw new RuntimeException('Unsafe fixture manifest');
}
$user = get_user_by('email', $fixture['email']);
$failure = null;
if ($user) {
    $profile = get_user_meta($user->ID, 'tarhan_profile', true);
    if ($user->roles !== array('subscriber') || ($profile['address_mode'] ?? '') !== 'directory') { throw new RuntimeException('Fixture ownership mismatch'); }
    if ($profile['address']['locality'] !== 'Казань' || $profile['address']['region'] !== 'Республика Татарстан') { $failure = 'Stored canonical address mismatch'; }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    if (!wp_delete_user($user->ID)) { throw new RuntimeException('Fixture cleanup failed'); }
}
unlink($path);
if ($failure) { throw new RuntimeException($failure); }
echo "Stored address verified; temporary geography account removed.\n";
