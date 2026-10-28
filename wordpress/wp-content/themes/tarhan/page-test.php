<?php
/* Template Name: Тестирование */
nocache_headers();
$test_config = array(
    'endpoint' => esc_url_raw(rest_url('tarhan/v1/test/')),
    'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
    'loggedIn' => is_user_logged_in(),
    'available' => function_exists('tarhan_attempt_command'),
    'loginUrl' => home_url('/entrance/'),
    'essayUrl' => home_url('/essay/'),
    'seasonId' => function_exists('tarhan_current_season_id') ? tarhan_current_season_id() : '',
);
get_header();
if (is_user_logged_in() && function_exists('tarhan_enrollment_context')) {
    $context = tarhan_enrollment_context(get_current_user_id());
    if (is_wp_error($context)) {
        echo '<main class="container"><h1>Участие в конкурсе</h1><p>' . esc_html($context->get_error_message()) . '</p><p><a href="' . esc_url(home_url('/registration/')) . '">Регистрация на сезон →</a></p></main>';
        get_footer(); return;
    }
}
?>

<main>
<section class="competition-test" id="competition-test" data-test-config="<?php echo esc_attr(wp_json_encode($test_config)); ?>" aria-labelledby="competition-test-title">
    <div class="competition-test__container container">
        <div class="competition-test__heading">
            <span class="competition-test__subtitle">Историко-культурный конкурс «Тархан»</span>
            <h1 class="competition-test__title" id="competition-test-title">Тестирование</h1>
        </div>
        <p class="competition-test__notice" role="status" data-test-status>50 вопросов, один правильный вариант. Ответы сохраняются при выборе.</p>
        <noscript><p class="competition-test__notice">Для прохождения теста включите JavaScript.</p></noscript>

        <div class="competition-test__card" data-test-screen="start">
            <h2 class="competition-test__card--title" tabindex="-1">Перед началом</h2>
            <ul class="competition-test__rules">
                <li class="competition-test__rule"><span class="competition-test__rule--icon" aria-hidden="true">◷</span><div><h3>40 минут</h3><p>Время на выполнение одной попытки.</p></div></li>
                <li class="competition-test__rule"><span class="competition-test__rule--icon" aria-hidden="true">↻</span><div><h3>3 попытки</h3><p>Если тест не пройден, его можно повторить.</p></div></li>
                <li class="competition-test__rule"><span class="competition-test__rule--icon" aria-hidden="true">→</span><div><h3>Следующий этап — эссе</h3><p>После успешного прохождения теста вы получите допуск к эссе.</p></div></li>
                <li class="competition-test__rule"><span class="competition-test__rule--icon" aria-hidden="true">→</span><div><h3>Автоматическое завершение</h3><p>Когда время истечёт, попытка завершится. Правильные ответы не показываются.</p></div></li>
            </ul>
            <div class="competition-test__actions">
                <button class="competition-test__button" type="button" data-test-action="start" disabled>Начать тест <span aria-hidden="true">→</span></button>
                <span class="competition-test__attempts">Осталось попыток: <strong data-test-remaining>3</strong></span>
            </div>
        </div>

        <div class="competition-test__card" data-test-screen="question" hidden>
            <div class="competition-test__toolbar">
                <span class="competition-test__counter" data-test-counter>Вопрос 1 из 50</span>
                <div class="competition-test__timer" role="timer" aria-label="Осталось времени" aria-live="off"><span aria-hidden="true">◷</span><span data-test-timer>40:00</span></div>
            </div>
            <progress class="competition-test__progress" max="50" value="0" aria-label="Количество заполненных вопросов"></progress>
            <form class="competition-test__form">
                <fieldset class="competition-test__question">
                    <legend class="competition-test__question--title" tabindex="-1">Здесь будет текст вопроса</legend>
                    <p class="competition-test__question--note">Выберите один вариант ответа.</p>
                    <div class="competition-test__answers" data-test-answers></div>
                </fieldset>
                <nav class="competition-test__navigation" aria-label="Вопросы теста" data-test-navigation></nav>
                <p class="competition-test__answered" data-test-answered>Сохранено ответов: 0 из 50</p>
                <div class="competition-test__actions">
                    <button class="competition-test__button competition-test__button--secondary" type="button" data-test-action="previous">Назад</button>
                    <div class="competition-test__actions--right">
                        <button class="competition-test__button" type="button" data-test-action="next">Далее <span aria-hidden="true">→</span></button>
                        <button class="competition-test__button competition-test__button--secondary" type="button" data-test-action="confirm">Завершить тест</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="competition-test__card competition-test__card--result" data-test-screen="result" hidden>
            <h2 class="competition-test__card--title" tabindex="-1" data-test-result-title>Тест завершён</h2>
            <p class="competition-test__result--reason" data-test-reason></p>
            <p class="competition-test__result--status" data-test-result-status></p>
            <span class="competition-test__attempts" data-test-result-attempts>Осталось попыток: <strong data-test-remaining>2</strong></span>
            <div class="competition-test__actions">
                <button class="competition-test__button" type="button" data-test-action="essay" disabled>Перейти к эссе <span aria-hidden="true">→</span></button>
                <button class="competition-test__button competition-test__button--secondary" type="button" data-test-action="retry">Пройти ещё раз</button>
            </div>
            <p class="competition-test__message" role="status" data-test-message></p>
        </div>
        <p class="competition-test__live" role="status" aria-live="polite" data-test-live></p>

        <dialog class="competition-test__dialog" aria-labelledby="test-confirm-title">
            <button class="competition-test__dialog--close" type="button" data-test-action="cancel" aria-label="Закрыть подтверждение">×</button>
            <h2 id="test-confirm-title">Завершить тест?</h2>
            <p>После завершения изменить ответы этой попытки нельзя.</p>
            <p class="competition-test__dialog--summary" data-test-summary></p>
            <div class="competition-test__actions">
                <button class="competition-test__button competition-test__button--secondary" type="button" data-test-action="cancel">Вернуться к тесту</button>
                <button class="competition-test__button" type="button" data-test-action="submit">Завершить попытку</button>
            </div>
        </dialog>
    </div>
</section>
</main>

<?php get_footer(); ?>

