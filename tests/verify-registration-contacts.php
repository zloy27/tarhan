<?php
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
function contacts_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
$input = array_fill_keys(array('first_name','last_name','middle_name','parent_first_name','parent_last_name','parent_middle_name'), 'Тест');
$input += array('teacher_name' => 'Иванов Иван Иванович', 'teacher_phone' => '+7 (999) 123-45-67', 'phone' => '89991234567', 'parent_phone' => '+79991234567',
    'email' => 'CONTACT-TEST@example.test', 'birth_date' => '2000-01-01', 'school_form' => 'МБОУ', 'school_type' => 'Школа', 'school_number' => '1', 'education' => 'Абитуриент',
    'address_fias_id' => '12345678-1234-1234-1234-123456789012', 'password' => 'test-fixture-only', 'password_confirmation' => 'test-fixture-only', 'rules' => 'on');
$profile = tarhan_validate_registration($input);
contacts_check(!is_wp_error($profile) && $profile['teacher_name'] === $input['teacher_name'] && $profile['email'] === strtolower($input['email']), 'Valid teacher/contact profile');
foreach (array('teacher_name' => 'Иван123', 'teacher_phone' => '123', 'phone' => '7+9991234567', 'email' => 'wrong-email') as $key => $value) {
    $bad = $input; $bad[$key] = $value; contacts_check(is_wp_error(tarhan_validate_registration($bad)), 'Invalid field accepted: ' . $key);
}
$bad = $input; unset($bad['teacher_name']); contacts_check(is_wp_error(tarhan_validate_registration($bad)), 'Missing teacher accepted');
foreach (array('+7 (999) 123-45-67', '8 999 123 45 67', '9991234567') as $phone) { contacts_check(tarhan_normalize_phone($phone) === '79991234567', 'Phone normalization'); }
global $wpdb;
contacts_check(wp_parse_url(home_url(), PHP_URL_HOST) === 'tarhan-wp.local' && !wp_using_ext_object_cache(), 'Local only');
$fixture_phone = '799' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
$fixture_user = 2147483000;
$wpdb->query('START TRANSACTION');
try {
    $profile['phone'] = $fixture_phone;
    $profile['registered_at'] = time();
    contacts_check($wpdb->insert($wpdb->usermeta, array('user_id' => $fixture_user, 'meta_key' => 'tarhan_profile', 'meta_value' => serialize($profile))) !== false, 'Fixture insert');
    contacts_check(tarhan_registration_phone_exists('8' . substr($fixture_phone, 1)) === true, 'Duplicate legacy phone');
    contacts_check(tarhan_registration_phone_exists($fixture_phone, $fixture_user) === false, 'Own account must be allowed');
    $wpdb->delete($wpdb->usermeta, array('user_id' => $fixture_user, 'meta_key' => 'tarhan_profile'));
    contacts_check(tarhan_store_enrollment($fixture_user, $profile, null), 'Enrollment save');
    contacts_check(tarhan_registration_phone_exists($fixture_phone) === true, 'Duplicate season phone');
    contacts_check(tarhan_registration_phone_exists($fixture_phone, $fixture_user) === false, 'Returning participant phone');
    $user = (object) array('ID' => $fixture_user, 'user_email' => $profile['email'], 'display_name' => 'Тест', 'user_registered' => '2026-01-01 00:00:00');
    $row = tarhan_organizer_row($user, $profile, null, array(), null);
    contacts_check($row['full_name'] === 'Тест Тест Тест' && $row['parent_full_name'] === 'Тест Тест Тест', 'Combined full names');
    contacts_check(!in_array('last_name', array_column(tarhan_organizer_columns(), 0), true), 'Split name columns remain');
    contacts_check($row['teacher_name'] === $input['teacher_name'] && $row['teacher_phone'] === $input['teacher_phone'], 'Teacher missing in organizer profile');
    $out = tempnam(sys_get_temp_dir(), 'tarhan-contacts-');
    try {
        contacts_check(tarhan_organizer_write_xlsx(array($row), $out) === 1, 'Excel export');
        $zip = new ZipArchive(); $zip->open($out);
        contacts_check(str_contains($zip->getFromName('xl/worksheets/sheet1.xml'), $input['teacher_name']), 'Teacher missing in Excel');
        $zip->close();
    } finally { unlink($out); }
} finally { $wpdb->query('ROLLBACK'); }
echo "PASS: validation, normalized duplicate phones, own account, teacher profile and Excel; fixtures rolled back\n";
