<?php
if (!defined('ABSPATH')) { exit; }

function tarhan_current_season_id()
{
    $state = tarhan_seasons_state();
    if (empty($state['configured'])) { return ''; }
    return !empty($state['current_id']) && isset($state['items'][$state['current_id']]) ? $state['current_id'] : null;
}

function tarhan_enrollment($user_id, $season_id)
{
    if ($season_id === null || get_option('tarhan_enrollment_schema') !== '1') { return null; }
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tarhan_enrollments WHERE user_id=%d AND season_id=%s", $user_id, $season_id));
    if ($wpdb->last_error) { throw new RuntimeException('Enrollment storage unavailable'); }
    return $row;
}

function tarhan_enrollment_context($user_id)
{
    if (get_option('tarhan_enrollment_schema') !== '1') { return tarhan_attempt_error('season_storage', 'Обновление сервиса участников. Попробуйте позже.', 503); }
    $season = tarhan_current_season_id();
    if ($season === null) { return tarhan_attempt_error('season_closed', 'Текущий сезон не выбран организатором.', 403); }
    if (!tarhan_enrollment($user_id, $season)) { return tarhan_attempt_error('enrollment_required', 'Заполните анкету участия в текущем сезоне.', 403); }
    return $season;
}

function tarhan_needs_enrollment($user_id)
{
    $season = tarhan_current_season_id();
    return $season !== null && !tarhan_enrollment($user_id, $season);
}

function tarhan_store_enrollment($user_id, $profile, $consent)
{
    global $wpdb;
    return $wpdb->insert($wpdb->prefix . 'tarhan_enrollments', array('user_id' => $user_id,
        'season_id' => $profile['season_id'] ?? '', 'season_name' => $profile['season_name'] ?? 'До настройки сезонов',
        'profile' => wp_json_encode($profile), 'consent' => wp_json_encode($consent), 'registered_at' => $profile['registered_at'])) !== false;
}

// Explicit installer: old tables and usermeta remain intact as a historical source.
function tarhan_install_enrollments()
{
    if (get_option('tarhan_enrollment_schema') === '1') { return true; }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$wpdb->prefix}tarhan_enrollments (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        season_id varchar(36) NOT NULL DEFAULT '',
        season_name varchar(100) NOT NULL,
        profile longtext NOT NULL,
        consent longtext NOT NULL,
        registered_at bigint(20) unsigned NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_season (user_id,season_id)
    ) ENGINE=InnoDB $charset;");
    dbDelta("CREATE TABLE {$wpdb->prefix}tarhan_season_attempts (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        season_id varchar(36) NOT NULL DEFAULT '',
        attempt_number tinyint(3) unsigned NOT NULL,
        request_key varchar(64) NOT NULL,
        status varchar(16) NOT NULL,
        started_at bigint(20) unsigned NOT NULL,
        expires_at bigint(20) unsigned NOT NULL,
        finished_at bigint(20) unsigned DEFAULT NULL,
        questions longtext NOT NULL,
        answers longtext NOT NULL,
        score tinyint(3) unsigned DEFAULT NULL,
        finish_reason varchar(16) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY user_attempt (user_id,season_id,attempt_number),
        UNIQUE KEY user_request (user_id,season_id,request_key),
        KEY user_status (user_id,season_id,status)
    ) ENGINE=InnoDB $charset;");
    dbDelta("CREATE TABLE {$wpdb->prefix}tarhan_season_essays (
        user_id bigint(20) unsigned NOT NULL,
        season_id varchar(36) NOT NULL DEFAULT '',
        text longtext NOT NULL,
        file longtext NOT NULL,
        submitted_at bigint(20) unsigned NOT NULL,
        PRIMARY KEY  (user_id,season_id)
    ) ENGINE=InnoDB $charset;");
    foreach (array('tarhan_enrollments', 'tarhan_season_attempts', 'tarhan_season_essays') as $name) {
        $table = $wpdb->prefix . $name;
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s', $table));
        if (!$status || strtoupper($status->Engine) !== 'INNODB') { throw new RuntimeException('Season tables unavailable'); }
    }
    $wpdb->query('START TRANSACTION');
    try {
        $users = $wpdb->get_results("SELECT u.ID,u.user_registered FROM {$wpdb->users} u WHERE EXISTS (SELECT 1 FROM {$wpdb->usermeta} m WHERE m.user_id=u.ID AND m.meta_key='tarhan_profile') OR EXISTS (SELECT 1 FROM {$wpdb->prefix}tarhan_attempts a WHERE a.user_id=u.ID) OR EXISTS (SELECT 1 FROM {$wpdb->prefix}tarhan_essays e WHERE e.user_id=u.ID)");
        if ($wpdb->last_error) { throw new RuntimeException('Migration read failed'); }
        foreach ($users as $user) {
            $profile = get_user_meta($user->ID, 'tarhan_profile', true);
            if (!is_array($profile)) { $profile = array(); }
            $profile['registered_at'] = $profile['registered_at'] ?? strtotime($user->user_registered . ' UTC');
            $profile['season_id'] = $profile['season_id'] ?? '';
            if (!tarhan_store_enrollment($user->ID, $profile, get_user_meta($user->ID, 'tarhan_consent_file', true) ?: null)) { throw new RuntimeException('Migration enrollment failed'); }
            $season = $profile['season_id'];
            $copy = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tarhan_season_attempts (id,user_id,season_id,attempt_number,request_key,status,started_at,expires_at,finished_at,questions,answers,score,finish_reason) SELECT id,user_id,%s,attempt_number,request_key,status,started_at,expires_at,finished_at,questions,answers,score,finish_reason FROM {$wpdb->prefix}tarhan_attempts WHERE user_id=%d", $season, $user->ID));
            if ($copy === false) { throw new RuntimeException('Migration attempts failed'); }
            if ($wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tarhan_season_essays (user_id,season_id,text,file,submitted_at) SELECT user_id,%s,text,file,submitted_at FROM {$wpdb->prefix}tarhan_essays WHERE user_id=%d", $season, $user->ID)) === false) { throw new RuntimeException('Migration essays failed'); }
        }
        if (!update_option('tarhan_enrollment_schema', '1', false)) { throw new RuntimeException('Migration version failed'); }
        if ($wpdb->query('COMMIT') === false) { throw new RuntimeException('Migration commit failed'); }
    } catch (Throwable $error) { $wpdb->query('ROLLBACK'); wp_cache_delete('tarhan_enrollment_schema', 'options'); throw $error; }
    return true;
}
