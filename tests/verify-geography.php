<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
function geo_check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function geo_ok($result) { geo_check(!is_wp_error($result), is_wp_error($result) ? $result->get_error_code() : ''); return $result; }
function geo_error($result, $code) { geo_check(is_wp_error($result) && $result->get_error_code() === $code, 'Expected error ' . $code); }
$passed = array();
$index = geo_ok(tarhan_geography_regions());
geo_check(count($index['regions']) >= 80, 'Missing region catalogue');
foreach ($index['regions'] as $region) {
    $rows = geo_ok(tarhan_geography_localities($region['id']));
    geo_check(count($rows) === $region['count'], 'Missing regional data file');
}
$passed[] = 'All regional shards readable and record counts consistent';
$kazan = geo_ok(tarhan_geography_search('484048', 'КАЗАНЬ'))['localities'];
geo_check($kazan[0]['id'] === '551487' && $kazan[0]['name'] === 'Казань', 'Exact city not ranked first');
$rural = geo_ok(tarhan_geography_search('484048', 'Актаныш'))['localities'];
geo_check(in_array('583578', array_column($rural, 'id'), true), 'Rural locality absent');
geo_check(tarhan_geography_search('484048', 'Несуществующийгородцфж')['localities'] === array(), 'Invented city found');
geo_error(tarhan_geography_search('../metadata', 'Казань'), 'geography_region');
geo_error(tarhan_geography_search('484048', array('Казань')), 'geography_query');
geo_error(tarhan_geography_search('484048', 'К'), 'geography_query');
$passed[] = 'City/rural search, Unicode, exact ranking, nonexistent names and invalid queries';
$same_names = geo_ok(tarhan_geography_search('484048', 'альм'))['localities'];
$by_id = array_column($same_names, null, 'id');
geo_check(isset($by_id['582433'], $by_id['582434']), 'A real same-name locality was removed');
geo_check($by_id['582433']['district'] === 'Елабужский район' && $by_id['582434']['district'] === 'Сармановский район', 'Same-name places lack distinct district labels');
$passed[] = 'Real same-name villages retained and distinguished by district';
$address = array('region_id' => '484048', 'locality_id' => '551487', 'street' => 'ул. Баумана', 'house' => '12а/1');
$valid = geo_ok(tarhan_geography_address($address));
geo_check($valid['region'] === 'Республика Татарстан' && $valid['locality'] === 'Казань' && !$valid['house_verified'] && !$valid['street_verified'], 'Wrong canonical address or false house-verification claim');
geo_error(tarhan_geography_address(array_merge($address, array('region_id' => '524925'))), 'geography_locality');
geo_error(tarhan_geography_address(array_merge($address, array('locality_id' => '999999999999'))), 'geography_locality');
geo_error(tarhan_geography_address(array_merge($address, array('house' => ''))), 'registration_address');
$passed[] = 'Canonical region/locality, cross-region forgery denied, street/house explicitly unverified';
global $wpdb;
foreach (array($wpdb->users, $wpdb->usermeta, $wpdb->options) as $table) {
    $info = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
    geo_check($info && strtoupper($info->Engine) === 'INNODB', 'Use transactional database for fixtures');
}
geo_check(!wp_using_ext_object_cache(), 'Use local test database without external object cache');
$initial = get_current_user_id();
$id = 0;
$wpdb->query('START TRANSACTION');
try {
    $_SERVER['REMOTE_ADDR'] = 'geo_verify_' . bin2hex(random_bytes(8));
    wp_set_current_user(0);
    // Assert the new registration genuinely works without calling any remote provider.
    $block_remote = function () { throw new RuntimeException('Directory registration called an external service'); };
    add_filter('pre_http_request', $block_remote);
    delete_option('tarhan_dadata_key');
    $p = array_merge($address, array('address_mode' => 'directory', 'first_name' => 'Иван', 'last_name' => 'Иванов', 'middle_name' => 'Иванович',
        'birth_date' => '2000-01-01', 'phone' => '+79991234567', 'parent_first_name' => 'Пётр', 'parent_last_name' => 'Иванов',
        'parent_middle_name' => 'Петрович', 'parent_phone' => '+79991234567', 'teacher_name' => 'Иванов Иван Иванович', 'teacher_phone' => '+79991234567', 'school_form' => 'МБОУ', 'school_type' => 'Школа',
        'school_number' => '12', 'education' => 'Абитуриент', 'email' => 'geo_' . bin2hex(random_bytes(8)) . '@example.invalid',
        'password' => 'Technical-Password-123!', 'password_confirmation' => 'Technical-Password-123!', 'rules' => 'on',
        'region' => 'Поддельный регион', 'locality' => 'Поддельное название', 'district' => 'Поддельный район', 'role' => 'administrator'));
    $request = new WP_REST_Request('POST', '/tarhan/v1/register');
    $request->set_body_params($p);
    geo_ok(tarhan_register_participant($request));
    $id = (int) email_exists($p['email']);
    geo_check($id > 0, 'Account not created');
    $profile = get_user_meta($id, 'tarhan_profile', true);
    geo_check($profile['address']['locality'] === 'Казань' && $profile['address']['region'] === 'Республика Татарстан', 'Forged client labels trusted');
    geo_check(get_userdata($id)->roles === array('subscriber'), 'Forged role trusted');
    $bad = array_merge($p, array('region_id' => '524925'));
    $request->set_body_params($bad);
    geo_error(tarhan_register_participant($request), 'geography_locality');
    $passed[] = 'Real account/profile without DaData or remote requests; labels and role server-controlled';
} finally {
    if (isset($block_remote)) remove_filter('pre_http_request', $block_remote);
    $wpdb->query('ROLLBACK');
    wp_cache_flush();
    if ($id) clean_user_cache($id);
    wp_set_current_user($initial);
}
foreach ($passed as $item) echo 'PASS ' . $item . "\n";
echo "Temporary registration rolled back.\n";
