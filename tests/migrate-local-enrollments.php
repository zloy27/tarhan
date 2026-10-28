<?php
$done = false;
register_shutdown_function(function () use (&$done) { if (!$done) { exit(1); } });
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'tarhan-wp.local') { throw new RuntimeException('Local only'); }
global $wpdb;
if (get_option('tarhan_enrollment_schema') !== '1') {
    $private = tarhan_private_directory();
    if (is_wp_error($private)) { throw new RuntimeException('Private backup storage unavailable'); }
    $backup = array();
    $wpdb->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    foreach (array('tarhan_attempts', 'tarhan_essays') as $name) {
        $backup[$name] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}$name", ARRAY_A);
        if ($wpdb->last_error) { throw new RuntimeException('Backup read failed'); }
    }
    $backup['profiles'] = $wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('tarhan_profile','tarhan_consent_file')", ARRAY_A);
    $backup['seasons'] = tarhan_seasons_state();
    $wpdb->query('ROLLBACK');
    $path = $private . '/before-enrollments-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.json';
    $json = wp_json_encode($backup);
    if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) { throw new RuntimeException('Backup write failed'); }
    chmod($path, 0600);
    echo "Private database backup saved outside webroot.\n";
    tarhan_install_enrollments();
    foreach (array('tarhan_attempts' => 'tarhan_season_attempts', 'tarhan_essays' => 'tarhan_season_essays') as $old => $new) {
        $original = $wpdb->get_results("SELECT o.* FROM {$wpdb->prefix}$old o JOIN {$wpdb->users} u ON o.user_id=u.ID", ARRAY_A);
        foreach ($original as $row) {
            $key = isset($row['id']) ? 'id' : 'user_id';
            $copied = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}$new WHERE $key=%d", $row[$key]), ARRAY_A);
            unset($copied['season_id']);
            if ($copied != $row) { throw new RuntimeException('Migrated record differs from original'); }
        }
        if ($wpdb->get_results("SELECT * FROM {$wpdb->prefix}$old", ARRAY_A) != $backup[$old]) { throw new RuntimeException('Original table changed'); }
    }
    echo "PASS: Old attempts/essays copied without changes; original tables retained.\n";
} else { echo "Enrollment migration already installed.\n"; }
$done = true;
