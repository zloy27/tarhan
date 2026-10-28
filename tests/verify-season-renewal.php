<?php
$done = false;
register_shutdown_function(function () use (&$done) { if (!$done) { exit(1); } });
define('DISABLE_WP_CRON', true);
require 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
function renewal_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function renewal_ok($result) { renewal_check(!is_wp_error($result), is_wp_error($result) ? $result->get_error_code() : ''); return $result; }
function renewal_error($result, $code) { renewal_check(is_wp_error($result) && $result->get_error_code() === $code, 'Expected ' . $code); }
global $wpdb;
renewal_check(wp_parse_url(home_url(), PHP_URL_HOST) === 'tarhan-wp.local' && !wp_using_ext_object_cache(), 'Isolated local site required');
foreach (array($wpdb->options,$wpdb->users,$wpdb->usermeta,$wpdb->posts,$wpdb->postmeta,$wpdb->prefix.'tarhan_enrollments',$wpdb->prefix.'tarhan_season_attempts',$wpdb->prefix.'tarhan_season_essays') as $table) {
    $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s', $table));
    renewal_check($status && strtoupper($status->Engine) === 'INNODB', 'Transactions required');
}
$admin = get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID'))[0];
$original_user = get_current_user_id(); $ids = array(); $question_ids = array(); $buffer = ob_get_level();
$old_ip = $_SERVER['REMOTE_ADDR'] ?? ''; $old_get = $_GET;
$initial_state = tarhan_seasons_state();
$question_filter = static function ($query) use (&$question_ids) { if ($query->get('tarhan_question_bank')) { $query->set('post__in', $question_ids); } };
$provider = static function ($pre,$args,$url) {
    if (!str_starts_with($url,'https://suggestions.dadata.ru/')) { return $pre; }
    return array('response'=>array('code'=>200),'headers'=>array(),'body'=>wp_json_encode(array('suggestions'=>array(array('value'=>'Тестовый адрес','data'=>array('house_fias_id'=>'11111111-1111-1111-1111-111111111111','house'=>'1'))))));
};
add_filter('pre_http_request',$provider,10,3); add_action('pre_get_posts',$question_filter);
$wpdb->query('START TRANSACTION');
try {
    $season_a = wp_generate_uuid4(); $season_b = wp_generate_uuid4();
    $state = array('items'=>array($season_a=>array('id'=>$season_a,'name'=>'Тестовый сезон А','created_at'=>time()),$season_b=>array('id'=>$season_b,'name'=>'Тестовый сезон Б','created_at'=>time())), 'current_id'=>$season_a,'registration_open'=>true,'configured'=>true,'revision'=>1);
    update_option('tarhan_seasons',$state,false); update_option('tarhan_dadata_key','fixture',false); update_option('tarhan_testing_open','1',false);
    update_option('tarhan_essay_settings',array('open'=>true,'topic'=>'Тестовая тема','max_length'=>1000,'max_bytes'=>1048576),false);
    $email = 'renewal_' . bin2hex(random_bytes(8)) . '@example.invalid';
    $p = array('first_name'=>'Иван','last_name'=>'Иванов','middle_name'=>'Иванович','birth_date'=>'2000-01-01','phone'=>'+79991234567','parent_first_name'=>'Пётр','parent_last_name'=>'Иванов','parent_middle_name'=>'Петрович','parent_phone'=>'+79991234567', 'teacher_name' => 'Иванов Иван Иванович', 'teacher_phone' => '+79991234567', 'school_form'=>'МБОУ','school_type'=>'Школа','school_number'=>'12','education'=>'Абитуриент','email'=>$email,'address_fias_id'=>'11111111-1111-1111-1111-111111111111','password'=>'Temporary-Renewal-123!','password_confirmation'=>'Temporary-Renewal-123!','rules'=>'on','season_id'=>$season_a);
    $request = new WP_REST_Request('POST','/tarhan/v1/register'); $request->set_body_params($p);
    wp_set_current_user(0); $_SERVER['REMOTE_ADDR'] = 'renewal_' . bin2hex(random_bytes(8));
    renewal_ok(tarhan_register_participant($request));
    $id = (int) email_exists($email); $ids[] = $id;
    $original_profile = get_user_meta($id,'tarhan_profile',true); $password_hash = get_userdata($id)->user_pass;
    $first_enrollment = tarhan_enrollment($id,$season_a);
    renewal_check((bool)$first_enrollment,'First enrollment missing');
    wp_set_current_user($id);
    for ($i=0;$i<50;$i++) {
        $qid = wp_insert_post(array('post_type'=>'tarhan_question','post_status'=>'draft','post_title'=>'Temporary renewal question'),true);
        renewal_check(!is_wp_error($qid),'Question creation'); $question_ids[] = $qid;
        $wpdb->update($wpdb->posts,array('post_status'=>'publish'),array('ID'=>$qid));
        update_post_meta($qid,'_tarhan_question_valid',1); update_post_meta($qid,'_tarhan_question_data',array('text'=>'Тестовый вопрос '.$i,'options'=>array('Первый','Второй'),'correct'=>array(1))); clean_post_cache($qid);
    }
    $first_attempt = null;
    for ($i=1;$i<=3;$i++) {
        $result = renewal_ok(tarhan_attempt_command('start',array('season_id'=>$season_a,'request_key'=>'season_request_key_'.$i)));
        $attempt_id = $result['attempt']['id']; $first_attempt = $first_attempt ?? $attempt_id;
        $result = renewal_ok(tarhan_attempt_command('finish',array('season_id'=>$season_a,'attempt_id'=>$attempt_id)));
        renewal_check($result['latest_result']['passed'] === false,'Empty test must fail');
    }
    renewal_error(tarhan_attempt_command('start',array('season_id'=>$season_a,'request_key'=>'season_request_key_4')),'attempt_limit');
    $snapshot = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tarhan_season_attempts WHERE user_id=%d AND season_id=%s ORDER BY id",$id,$season_a),ARRAY_A);
    wp_set_current_user((int)$admin);
    renewal_ok(tarhan_seasons_save(array('_wpnonce'=>wp_create_nonce('tarhan_seasons_save'),'revision'=>'1','operation'=>'settings','current_id'=>$season_b,'registration_open'=>'1')));
    wp_set_current_user($id);
    renewal_check(tarhan_needs_enrollment($id),'Next season did not require a new form');
    renewal_error(tarhan_attempt_command('status'),'enrollment_required');
    $auth = new WP_REST_Request('POST','/tarhan/v1/auth/login');
    $auth->set_header('Content-Type','application/json'); $auth->set_body(wp_json_encode(array('email'=>$email,'password'=>$p['password'])));
    $login = renewal_ok(tarhan_auth_command('login', $auth));
    renewal_check($login['redirect'] === home_url('/registration/'), 'Login did not route to new enrollment');
    echo "PASS: First season registration and three failed attempts; limit enforced; login redirects to new enrollment\n";
    renewal_check(wp_check_password($p['password'],get_userdata($id)->user_pass,$id),'Existing password changed');
    $new_form = array_merge($p,array('season_id'=>$season_b,'school_number'=>'34','email'=>'spoof@example.invalid'));
    unset($new_form['password'],$new_form['password_confirmation']); $request->set_body_params($new_form);
    renewal_error(tarhan_register_participant($request),'test_nonce');
    $request->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));
    renewal_ok(tarhan_register_participant($request));
    renewal_error(tarhan_register_participant($request),'already_enrolled');
    renewal_check(email_exists($email)===$id && get_userdata($id)->user_pass===$password_hash && get_user_meta($id,'tarhan_profile',true)===$original_profile,'Account or original profile changed');
    $second = tarhan_enrollment($id,$season_b); $profile_b = json_decode($second->profile,true);
    renewal_check($profile_b['school_number']==='34' && $profile_b['email']===$email && tarhan_enrollment($id,$season_a)->profile===$first_enrollment->profile,'Season profiles overwritten or email spoofed');
    $status = renewal_ok(tarhan_attempt_command('status'));
    renewal_check($status['attempts_used']===0 && $status['attempts_remaining']===3 && $status['latest_result']===null,'Attempts did not reset for new season');
    renewal_error(tarhan_attempt_command('start',array('season_id'=>$season_a,'request_key'=>'season_request_key_1')),'season_changed');
    renewal_error(tarhan_attempt_command('finish',array('season_id'=>$season_b,'attempt_id'=>$first_attempt)),'attempt_not_found');
    echo "PASS: Same email/password, new form, immutable previous profile, full new attempt limit and stale tab isolation\n";
    $start = renewal_ok(tarhan_attempt_command('start',array('season_id'=>$season_b,'request_key'=>'season_request_key_1')));
    renewal_check($start['attempt']['number']===1,'New season numbering');
    $active_id = $start['attempt']['id'];
    foreach ($start['attempt']['questions'] as $question) { renewal_ok(tarhan_attempt_command('answer',array('season_id'=>$season_b,'attempt_id'=>$active_id,'question_id'=>$question['id'],'answer'=>1))); }
    $result = renewal_ok(tarhan_attempt_command('finish',array('season_id'=>$season_b,'attempt_id'=>$active_id)));
    renewal_check($result['essay_allowed'] && !isset($result['score']),'Passing result or public score privacy');
    $essay_request = new WP_REST_Request('POST','/tarhan/v1/essay/submit');
    $essay_request->set_body_params(array('season_id'=>$season_a,'text'=>str_repeat('Тестовое эссе. ',20)));
    renewal_error(tarhan_essay_command('submit',$essay_request),'season_changed');
    $essay_request->set_body_params(array('season_id'=>$season_b,'text'=>str_repeat('Тестовое эссе. ',20)));
    renewal_ok(tarhan_essay_command('submit',$essay_request));
    renewal_check(tarhan_get_essay($id,$season_b) && !tarhan_get_essay($id,$season_a),'Essay mixed between seasons');
    $after = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tarhan_season_attempts WHERE user_id=%d AND season_id=%s ORDER BY id",$id,$season_a),ARRAY_A);
    renewal_check($after===$snapshot,'Previous season attempts were modified');
    wp_set_current_user((int)$admin);
    $rows = iterator_to_array(tarhan_organizer_rows(array('search'=>$email)));
    renewal_check(count($rows)===2 && $rows[0]['score']===0 && $rows[1]['score']===50 && $rows[0]['attempts_used']===3 && $rows[1]['attempts_used']===1,'Organizer seasons mixed');
    renewal_check(count(iterator_to_array(tarhan_organizer_rows(array('search'=>$email,'season'=>$season_b))))===1,'Season filter');
    renewal_check(str_contains($rows[0]['profile_url'],$season_a) && str_contains($rows[1]['essay_url'],$season_b),'Historical links missing season');
    $_GET = array('participant'=>$id,'season'=>$season_b); ob_start(); tarhan_organizer_page(); $html=ob_get_clean();
    renewal_check(str_contains($html,'Тестовый сезон Б') && str_contains($html,'Тестовое эссе'),'Season detail unavailable');
    $output = getenv('TARHAN_RENEWAL_XLSX');
    if ($output) { renewal_check(tarhan_organizer_write_xlsx($rows,$output,'Синтетическая проверка двух сезонов')===2,'Excel rows'); }
    echo "PASS: Independent answers, result and essay, immutable old attempts, two organizer rows, season filter and Excel\n";
    renewal_ok(tarhan_seasons_save(array('_wpnonce'=>wp_create_nonce('tarhan_seasons_save'),'revision'=>(string)tarhan_seasons_state()['revision'],'operation'=>'settings','current_id'=>$season_a,'registration_open'=>'1')));
    wp_set_current_user($id); renewal_check(tarhan_attempt_command('status')['attempts_remaining']===0,'Switch back lost history');
    $outsider = wp_insert_user(array('user_login'=>'renewal_other_'.bin2hex(random_bytes(6)),'user_pass'=>'Temporary-Other-123!','role'=>'subscriber')); $ids[]=$outsider;
    wp_set_current_user($outsider); renewal_error(tarhan_attempt_command('status'),'enrollment_required');
    echo "PASS: Returning to an earlier season restores its state; unenrolled account denied\n";
} finally {
    while(ob_get_level()>$buffer){ob_end_clean();}
    $wpdb->query('ROLLBACK'); remove_filter('pre_http_request',$provider,10); remove_action('pre_get_posts',$question_filter); wp_cache_flush();
    foreach($ids as $uid){clean_user_cache($uid);} foreach($question_ids as $qid){clean_post_cache($qid);}
    wp_set_current_user($original_user); $_SERVER['REMOTE_ADDR']=$old_ip; $_GET=$old_get;
}
renewal_check(tarhan_seasons_state()===$initial_state,'Season settings not restored');
echo "PASS: Fixtures rolled back; original season selection restored\n";
$done=true;
