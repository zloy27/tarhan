<?php
if (!defined('ABSPATH')) { exit; }

function tarhan_address_key()
{
    return defined('TARHAN_DADATA_KEY') ? TARHAN_DADATA_KEY : get_option('tarhan_dadata_key', '');
}

function tarhan_address_request($method, $query)
{
    if (!tarhan_address_key()) { return tarhan_attempt_error('address_unavailable', 'Адресный справочник ещё не настроен организатором.', 503); }
    $response = wp_remote_post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/' . $method . '/address', array(
        'timeout' => 12, 'headers' => array('Authorization' => 'Token ' . tarhan_address_key(),
            'Content-Type' => 'application/json', 'Accept' => 'application/json'),
        'body' => wp_json_encode(array('query' => $query, 'count' => 8))));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return tarhan_attempt_error('address_unavailable', 'Справочник временно недоступен. Повторите запрос позже.', 503);
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || !isset($data['suggestions']) || !is_array($data['suggestions'])) {
        return tarhan_attempt_error('address_unavailable', 'Справочник вернул некорректный ответ.', 503);
    }
    return array_values(array_filter($data['suggestions'], function ($item) {
        return is_array($item) && is_string($item['value'] ?? null) && !empty($item['data']['house_fias_id']);
    }));
}

function tarhan_validate_registration($input, $returning = false)
{
    $profile = array();
    $required = array('first_name', 'last_name', 'middle_name', 'birth_date', 'phone',
        'parent_first_name', 'parent_last_name', 'parent_middle_name', 'parent_phone',
        'school_form', 'school_type', 'school_number', 'education', 'email', 'teacher_name', 'teacher_phone');
    foreach ($required as $name) {
        if (!is_string($input[$name] ?? null) || trim($input[$name]) === '' || mb_strlen($input[$name]) > 250) {
            return tarhan_attempt_error('registration_fields', 'Заполните обязательные поля анкеты.');
        }
        $profile[$name] = trim($input[$name]);
    }
    foreach (array('first_name', 'last_name', 'middle_name', 'parent_first_name', 'parent_last_name', 'parent_middle_name', 'teacher_name') as $name) {
        if (!preg_match("/^[\p{L}\p{M}]+(?:[ '\x{2019}-][\p{L}\p{M}]+)*$/uD", $profile[$name])) {
            return tarhan_attempt_error('registration_name', 'ФИО должно содержать буквы, без цифр.');
        }
    }
    foreach (array('phone', 'parent_phone', 'teacher_phone') as $name) {
        if (!preg_match('/^\+?[\d\s()-]+$/D', $profile[$name]) || !preg_match('/^\d{10,15}$/D', preg_replace('/\D/', '', $profile[$name]))) {
            return tarhan_attempt_error('registration_phone', 'Телефон должен содержать от 10 до 15 цифр.');
        }
    }
    if (!is_email($profile['email'])) { return tarhan_attempt_error('registration_email', 'Проверьте электронную почту.'); }
    $profile['email'] = strtolower($profile['email']);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $profile['birth_date'], wp_timezone());
    $today = new DateTimeImmutable('today', wp_timezone());
    if (!$date || $date->format('Y-m-d') !== $profile['birth_date'] || $date > $today) {
        return tarhan_attempt_error('registration_date', 'Проверьте дату рождения.');
    }
    if (!in_array($profile['school_form'], array('МБОУ', 'МАОУ', 'МКОУ', 'ГБОУ', 'ГАОУ', 'ГКОУ', 'ГБПОУ', 'ГАПОУ', 'ЧОУ'), true) ||
        !in_array($profile['school_type'], array('Школа', 'Гимназия', 'Лицей', 'Колледж', 'Техникум'), true) ||
        !in_array($profile['education'], array('10 класс', '11 класс', 'Студент СПО', 'Абитуриент'), true) ||
        !preg_match('/^[1-9][0-9]{0,4}$/D', $profile['school_number'])) {
        return tarhan_attempt_error('registration_school', 'Выберите учебное заведение и категорию из формы.');
    }
    if (($input['address_mode'] ?? '') === 'directory') {
        $address = tarhan_geography_address($input);
        if (is_wp_error($address)) { return $address; }
        $profile['address'] = $address;
        $profile['address_mode'] = 'directory';
    } else {
        // Retain the existing, strictly server-verified full-address provider for old clients.
        $fias = $input['address_fias_id'] ?? null;
        if (!is_string($fias) || !preg_match('/^[a-f0-9-]{36}$/iD', $fias)) { return tarhan_attempt_error('registration_address', 'Выберите регион и населённый пункт из справочника.'); }
        $profile['address_fias_id'] = $fias;
        $profile['address_mode'] = 'dadata';
    }
    $password = $input['password'] ?? null;
    if (!$returning && (!is_string($password) || mb_strlen($password) < 8 || strlen($password) > 4096 || trim($password) === '' ||
        $password !== ($input['password_confirmation'] ?? null))) {
        return tarhan_attempt_error('registration_password', 'Пароли должны совпадать и содержать не менее 8 символов.');
    }
    if (!in_array($input['rules'] ?? null, array('on', '1'), true)) { return tarhan_attempt_error('registration_rules', 'Подтвердите ознакомление с положением.'); }
    $apartment = $input['apartment'] ?? '';
    if (!is_string($apartment) || mb_strlen($apartment) > 20) { return tarhan_attempt_error('registration_apartment', 'Проверьте номер квартиры.'); }
    $profile['apartment'] = sanitize_text_field($apartment);
    $profile['minor'] = $date->diff($today)->y < 18;
    foreach ($required as $name) { $profile[$name] = sanitize_text_field($profile[$name]); }
    return $profile;
}

function tarhan_normalize_phone($value)
{
    $digits = preg_replace('/\D/', '', (string) $value);
    if (strlen($digits) === 10) { $digits = '7' . $digits; }
    if (strlen($digits) === 11 && $digits[0] === '8') { $digits = '7' . substr($digits, 1); }
    return $digits;
}

function tarhan_registration_phone_exists($phone, $exclude_user = 0)
{
    global $wpdb;
    $phone = tarhan_normalize_phone($phone);
    // Include historical season profiles and legacy accounts; never compare shared teacher/parent phones.
    foreach (array('enrollments', 'legacy') as $source) {
        $after = 0;
        do {
            $sql = $source === 'enrollments'
                ? "SELECT id, profile FROM {$wpdb->prefix}tarhan_enrollments WHERE id > %d AND user_id <> %d ORDER BY id LIMIT 200"
                : "SELECT umeta_id AS id, meta_value AS profile FROM {$wpdb->usermeta} WHERE umeta_id > %d AND user_id <> %d AND meta_key = 'tarhan_profile' ORDER BY umeta_id LIMIT 200";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $after, $exclude_user));
            if ($wpdb->last_error) { return tarhan_attempt_error('registration_storage', 'Не удалось проверить телефон. Повторите позже.', 503); }
            foreach ($rows as $row) {
                $after = (int) $row->id;
                $profile = $source === 'enrollments' ? json_decode($row->profile, true) : maybe_unserialize($row->profile);
                if (is_array($profile) && isset($profile['phone']) && tarhan_normalize_phone($profile['phone']) === $phone) { return true; }
            }
        } while (count($rows) === 200);
    }
    return false;
}

function tarhan_register_participant($request)
{
    $returning = is_user_logged_in();
    if (get_option('tarhan_enrollment_schema') !== '1') { return tarhan_attempt_error('season_storage', 'Сервис участников обновляется. Попробуйте позже.', 503); }
    if ($returning) {
        $permission = tarhan_test_permission($request);
        if (is_wp_error($permission)) { return $permission; }
    }
    $season = tarhan_registration_season();
    if (is_wp_error($season)) { return $season; }
    $rate = tarhan_rate_limit('register', 5);
    if (is_wp_error($rate)) { return $rate; }
    $input = $request->get_body_params();
    if ($returning) { $input['email'] = wp_get_current_user()->user_email; }
    if (($input['season_id'] ?? '') !== ($season['id'] ?? '')) { return tarhan_attempt_error('season_changed', 'Сезон регистрации изменился. Обновите страницу и заполните анкету заново.', 409); }
    $profile = tarhan_validate_registration($input, $returning);
    if (is_wp_error($profile)) { return $profile; }
    if ($profile['address_mode'] === 'dadata') {
    $addresses = tarhan_address_request('findById', $profile['address_fias_id']);
    if (is_wp_error($addresses)) { return $addresses; }
    $address = null;
    foreach ($addresses as $candidate) {
        if (strtolower($candidate['data']['house_fias_id']) === strtolower($profile['address_fias_id'])) { $address = $candidate; break; }
    }
    if (!$address) { return tarhan_attempt_error('registration_address', 'Адрес не подтверждён справочником. Выберите его заново.'); }
    $d = $address['data'];
    $profile['address'] = array('value' => $address['value'], 'fias_id' => $d['house_fias_id'],
        'region' => $d['region_with_type'] ?? '', 'district' => $d['area_with_type'] ?? $d['city_district_with_type'] ?? '',
        'locality' => $d['settlement_with_type'] ?? $d['city_with_type'] ?? '', 'street' => $d['street_with_type'] ?? '',
        'house' => trim(($d['house_type'] ?? '') . ' ' . ($d['house'] ?? '') . ' ' . ($d['block_type'] ?? '') . ' ' . ($d['block'] ?? '')));
    }
    $files = $request->get_file_params();
    $file = null;
    if (isset($files['consent_file']) && ($files['consent_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = tarhan_store_private_upload($files['consent_file'], array('pdf', 'jpg', 'jpeg', 'png'), 10 * 1024 * 1024);
        if (is_wp_error($file)) { return $file; }
    } elseif ($profile['minor']) { return tarhan_attempt_error('registration_consent', 'Прикрепите подписанное согласие представителя.'); }
    global $wpdb;
    $lock = 'tarhan_register_' . substr(hash('sha256', $profile['email']), 0, 40);
    if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== '1') {
        tarhan_delete_private_file($file);
        return tarhan_attempt_error('registration_busy', 'Регистрация обрабатывается. Попробуйте войти или повторите позже.', 409);
    }
    $id = 0;
    $season_locked = false;
    try {
        $season_locked = (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', tarhan_seasons_lock_name())) === '1';
        if (!$season_locked) { return tarhan_attempt_error('registration_busy', 'Регистрация обрабатывается. Повторите позже.', 409); }
        $current_season = tarhan_registration_season();
        if (is_wp_error($current_season)) { return $current_season; }
        if (($season['id'] ?? '') !== ($current_season['id'] ?? '')) { return tarhan_attempt_error('season_changed', 'Сезон регистрации изменился. Обновите страницу.', 409); }
        if ($returning && tarhan_enrollment(get_current_user_id(), $season['id'] ?? '')) { return tarhan_attempt_error('already_enrolled', 'Вы уже зарегистрированы на этот сезон.', 409); }
        if (!$returning && email_exists($profile['email'])) { return tarhan_attempt_error('registration_exists', 'Эта почта уже зарегистрирована. Войдите в аккаунт, чтобы подать анкету на новый сезон.', 409); }
        $phone_exists = tarhan_registration_phone_exists($profile['phone'], $returning ? get_current_user_id() : 0);
        if (is_wp_error($phone_exists)) { return $phone_exists; }
        if ($phone_exists) { return tarhan_attempt_error('registration_phone_exists', 'Этот личный телефон уже зарегистрирован. Войдите в свой аккаунт или укажите другой номер.', 409); }
        $id = $returning ? get_current_user_id() : wp_insert_user(array('user_login' => 'participant_' . bin2hex(random_bytes(12)),
            'user_email' => $profile['email'], 'user_pass' => $input['password'], 'role' => 'subscriber',
            'first_name' => $profile['first_name'], 'last_name' => $profile['last_name'],
            'display_name' => $profile['last_name'] . ' ' . $profile['first_name']));
        if (is_wp_error($id)) { $id = 0; return tarhan_attempt_error('registration_failed', 'Не удалось создать аккаунт. Повторите позже.', 500); }
        $profile['registered_at'] = time();
        $profile['participation_year'] = (int) wp_date('Y', $profile['registered_at']);
        if ($season) { $profile['season_id'] = $season['id']; $profile['season_name'] = $season['name']; }
        $profile['rules_accepted_at'] = time();
        if ((!$returning && (!update_user_meta($id, 'tarhan_profile', $profile) || ($file && !update_user_meta($id, 'tarhan_consent_file', $file)))) || !tarhan_store_enrollment($id, $profile, $file)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            if (!$returning) { wp_delete_user($id); }
            $id = 0;
            return tarhan_attempt_error('registration_failed', 'Анкета не сохранена. Повторите регистрацию.', 500);
        }
        tarhan_mail_registration($id, $profile);
        if (!$returning) {
            wp_set_current_user($id);
            wp_set_auth_cookie($id, false, is_ssl());
            do_action('wp_login', get_userdata($id)->user_login, get_userdata($id));
        }
        return array('success' => true, 'login_url' => home_url('/entrance/'), 'next_url' => home_url('/test/'));
    } finally {
        if (!$id) { tarhan_delete_private_file($file); }
        if ($season_locked) { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', tarhan_seasons_lock_name())); }
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

function tarhan_auth_command($command, $request)
{
    $rate = tarhan_rate_limit($command, $command === 'login' ? 15 : 5);
    if (is_wp_error($rate)) { return $rate; }
    $p = $request->get_json_params();
    if (!is_array($p)) { return tarhan_attempt_error('auth_payload', 'Проверьте заполнение формы.'); }
    if ($command === 'login') {
        $email = $p['email'] ?? null;
        $password = $p['password'] ?? null;
        if (!is_string($email) || !is_email($email) || !is_string($password) || strlen($password) > 4096) {
            return tarhan_attempt_error('login_failed', 'Неверный email или пароль.', 401);
        }
        $rate = tarhan_rate_limit('login_account', 15, strtolower($email));
        if (is_wp_error($rate)) { return $rate; }
        $user = wp_signon(array('user_login' => $email, 'user_password' => $password, 'remember' => false), is_ssl());
        if (is_wp_error($user)) { return tarhan_attempt_error('login_failed', 'Неверный email или пароль.', 401); }
        return array('success' => true, 'redirect' => home_url(tarhan_needs_enrollment($user->ID) ? '/registration/' : '/test/'));
    }
    if ($command === 'forgot') {
        if (!is_string($p['email'] ?? null) || !is_email($p['email'])) { return tarhan_attempt_error('auth_email', 'Введите корректный email.'); }
        // Always use the same public response, including an unknown email or mail transport error.
        retrieve_password($p['email']);
        return array('success' => true, 'message' => 'Если аккаунт существует, на его почту отправлена ссылка для смены пароля.');
    }
    if ($command === 'reset') {
        $password = $p['password'] ?? null;
        if (!is_string($password) || mb_strlen($password) < 8 || strlen($password) > 4096 || !trim($password) ||
            $password !== ($p['password_confirmation'] ?? null)) { return tarhan_attempt_error('auth_password', 'Пароли должны совпадать и содержать не менее 8 символов.'); }
        if (!is_string($p['key'] ?? null) || !is_string($p['login'] ?? null)) { return tarhan_attempt_error('reset_link', 'Ссылка недействительна. Запросите новую.'); }
        $user = check_password_reset_key($p['key'], $p['login']);
        if (is_wp_error($user)) { return tarhan_attempt_error('reset_link', 'Ссылка недействительна или устарела. Запросите новую.'); }
        reset_password($user, $password);
        WP_Session_Tokens::get_instance($user->ID)->destroy_all();
        return array('success' => true, 'redirect' => home_url('/entrance/'));
    }
    return tarhan_attempt_error('auth_command', 'Неизвестное действие.');
}

add_filter('retrieve_password_message', function ($message, $key, $login, $user) {
    $url = add_query_arg(array('key' => $key, 'login' => $login), home_url('/reset-password/'));
    return "Для смены пароля на сайте «Тархан» откройте ссылку:\n\n" . $url . "\n\nЕсли вы не запрашивали смену пароля, проигнорируйте письмо.\n";
}, 10, 4);

add_action('rest_api_init', function () {
    register_rest_route('tarhan/v1', '/register', array('methods' => 'POST',
        'permission_callback' => 'tarhan_public_permission', 'callback' => 'tarhan_register_participant'));
    register_rest_route('tarhan/v1', '/address', array('methods' => 'POST',
        'permission_callback' => 'tarhan_public_permission', 'callback' => function ($request) {
            $rate = tarhan_rate_limit('address', 80);
            if (is_wp_error($rate)) { return $rate; }
            $query = $request->get_param('query');
            if (!is_string($query) || mb_strlen(trim($query)) < 3 || mb_strlen($query) > 250) { return tarhan_attempt_error('address_query', 'Уточните адрес.'); }
            $items = tarhan_address_request('suggest', $query);
            return is_wp_error($items) ? $items : array('suggestions' => $items);
        }));
    foreach (array('login', 'forgot', 'reset') as $command) {
        register_rest_route('tarhan/v1', '/auth/' . $command, array('methods' => 'POST',
            'permission_callback' => 'tarhan_public_permission', 'callback' => function ($request) use ($command) { return tarhan_auth_command($command, $request); }));
    }
    register_rest_route('tarhan/v1', '/auth/logout', array('methods' => 'POST',
        'permission_callback' => 'tarhan_test_permission', 'callback' => function () {
            wp_logout();
            return array('success' => true, 'redirect' => home_url('/entrance/'));
        }));
});
