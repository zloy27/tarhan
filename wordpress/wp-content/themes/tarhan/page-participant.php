<?php
/* Template Name: Участник — формы */
nocache_headers();
header('Referrer-Policy: no-referrer');
if (!function_exists('tarhan_site_token')) {
    get_header();
    echo '<main class="container"><p>Сервис участников временно недоступен. Попробуйте позже.</p></main>';
    get_footer();
    return;
}
$slug = get_post_field('post_name', get_queried_object_id());
$registration_season = $slug === 'registration' && function_exists('tarhan_registration_season') ? tarhan_registration_season() : null;
$needs_enrollment = is_user_logged_in() && function_exists('tarhan_needs_enrollment') && tarhan_needs_enrollment(get_current_user_id());
$renewal_form = $slug === 'registration' && $needs_enrollment && !is_wp_error($registration_season);
$config = array('endpoint' => rest_url('tarhan/v1/'), 'token' => tarhan_site_token(), 'page' => $slug,
    'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '', 'loggedIn' => is_user_logged_in(), 'renewalForm' => $renewal_form, 'registrationClosed' => is_wp_error($registration_season),
    'seasonId' => function_exists('tarhan_current_season_id') ? tarhan_current_season_id() : '');
if ($slug === 'registration') {
    $config['addressEndpoint'] = tarhan_address_key() ? rest_url('tarhan/v1/address') : '';
    $config['registerEndpoint'] = rest_url('tarhan/v1/register');
    $config['geographyEndpoint'] = rest_url('tarhan/v1/geography/');
    $config['loginUrl'] = home_url('/entrance/');
}
get_header();
?>
<script>window.tarhanParticipant = <?php echo wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>; window.tarhanRegistration = window.tarhanParticipant;</script>
<main>
<?php if (is_user_logged_in() && in_array($slug, array('entrance', 'registration'), true) && !$renewal_form && !($slug === 'registration' && is_wp_error($registration_season))) : ?>
<section class="participant-account"><div class="participant-account__container container">
    <p class="participant-account__eyebrow">Участнику конкурса</p>
    <h1 class="participant-account__title">Продолжим участие</h1>
    <div class="participant-account__card">
        <div class="participant-account__identity">
            <span class="participant-account__avatar" aria-hidden="true"><?php echo esc_html(mb_substr(wp_get_current_user()->display_name, 0, 1, 'UTF-8')); ?></span>
            <div><p class="participant-account__label">Вы вошли в аккаунт</p><h2 class="participant-account__name"><?php echo esc_html(wp_get_current_user()->display_name); ?></h2></div>
            <span class="participant-account__badge">Вход выполнен</span>
        </div>
        <div class="participant-account__next">
            <p class="participant-account__eyebrow">Следующий шаг</p>
            <h3 class="participant-account__heading"><?php echo $needs_enrollment ? 'Заполните анкету участия' : 'Перейдите к заданиям'; ?></h3>
            <p class="participant-account__description"><?php echo $needs_enrollment ? 'Вы ещё не зарегистрированы на текущий сезон. Заполните анкету, чтобы перейти к заданиям конкурса.' : 'Продолжите тестирование. После успешного прохождения вам станет доступна отправка эссе.'; ?></p>
            <div class="participant-account__actions">
                <a class="participant-account__primary" href="<?php echo esc_url(home_url($needs_enrollment ? '/registration/' : '/test/')); ?>"><?php echo $needs_enrollment ? 'Заполнить анкету' : 'Продолжить участие'; ?><span aria-hidden="true">→</span></a>
                <?php if (!$needs_enrollment) : ?><a class="participant-account__secondary" href="<?php echo esc_url(home_url('/essay/')); ?>">Перейти к эссе</a><?php endif; ?>
            </div>
        </div>
        <div class="participant-account__footer"><p>Хотите войти под другим аккаунтом?</p><button type="button" class="participant-account__logout" data-participant-logout>Выйти из аккаунта</button></div>
        <p class="participant-account__message" role="status" data-account-message></p>
    </div>
</div></section>
<?php elseif ($slug === 'essay') : ?>
<section class="registration"><div class="registration__container container"><div class="registration__subtitle">Следующий этап</div><h1 class="registration__title">Эссе</h1>
<?php if (!is_user_logged_in()) : ?>
<p>Для сдачи эссе <a href="<?php echo esc_url(home_url('/entrance/')); ?>">войдите в аккаунт</a>.</p>
<?php elseif ($needs_enrollment) : ?><p>Для участия в текущем сезоне <a href="<?php echo esc_url(home_url('/registration/')); ?>">заполните анкету</a>.</p>
<?php else : ?>
<div class="registration__form" data-essay><p role="status" data-essay-message>Загружаем состояние работы…</p><h2 data-essay-topic></h2>
<div class="essay-success" data-essay-submission role="status" tabindex="-1" hidden><span class="essay-success__icon" aria-hidden="true">✓</span><p class="essay-success__eyebrow">Работа принята</p><h2 class="essay-success__title">Вы успешно прошли все этапы!</h2><p class="essay-success__text">Спасибо за участие в историко-культурном конкурсе «Тархан». Эссе сохранено и доступно организаторам.</p><a class="essay-success__button" href="<?php echo esc_url(home_url('/')); ?>">Вернуться на главную <span aria-hidden="true">→</span></a></div>
<form data-essay-form hidden enctype="multipart/form-data"><p>Выберите один способ: напишите текст или приложите файл. После окончательной отправки изменить работу через сайт нельзя.</p>
<label class="registration__field"><span>Текст эссе</span><textarea name="text" rows="14" data-essay-text></textarea></label><p class="registration__hint" data-essay-counter></p>
<label class="registration__upload"><span>Либо файл эссе</span><input name="essay_file" type="file" accept=".pdf,.doc,.docx,.txt"><small data-essay-file-hint></small></label>
<label class="registration__agreement"><input type="checkbox" name="confirm" required><span>Подтверждаю окончательную отправку этой работы</span></label><button type="submit" class="registration__submit">Отправить эссе →</button></form>
<p data-essay-navigation><a href="<?php echo esc_url(home_url('/test/')); ?>">Вернуться к тесту</a> · <button type="button" data-participant-logout>Выйти</button></p></div>
<?php endif; ?></div></section>
<?php elseif ($slug === 'registration' && is_wp_error($registration_season)) : ?>
<section class="registration registration--closed"><div class="registration__container container"><div class="registration-closed"><span class="registration-closed__icon" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none"><rect x="7" y="10" width="34" height="31" rx="5"/><path d="M7 18h34M16 6v8M32 6v8M16 27h16M16 33h11"/></svg></span><p class="registration-closed__eyebrow">Приём заявок</p><h1 class="registration-closed__title">Регистрация сейчас закрыта</h1><p class="registration-closed__text"><?php echo esc_html($registration_season->get_error_message()); ?> Информация об открытии появится на главной странице.</p><div class="registration-closed__actions"><a class="registration-closed__primary" href="<?php echo esc_url(home_url('/entrance/')); ?>">Войти в аккаунт <span aria-hidden="true">→</span></a><a class="registration-closed__secondary" href="<?php echo esc_url(home_url('/')); ?>">Вернуться на главную</a></div></div></div></section>
<?php else :
    $partials = array('registration' => 'registration', 'entrance' => 'entrance', 'forgot-password' => 'forgot-password', 'reset-password' => 'reset-password');
    if (isset($partials[$slug])) { include get_theme_file_path('/participant-forms/' . $partials[$slug] . '.php'); }
endif; ?>
</main>
<?php get_footer(); ?>
