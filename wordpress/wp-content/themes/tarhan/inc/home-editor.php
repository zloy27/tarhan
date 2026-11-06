<?php
/** Editable content for the custom home-page layout. */
defined('ABSPATH') || exit;

function tarhan_home_schema()
{
    return array(
        'hero' => array('Первый экран', array(
            'hero_title' => array('Заголовок', 'text'),
            'hero_text' => array('Описание организаторов', 'textarea'),
            'hero_button' => array('Текст кнопки регистрации', 'text'),
            'hero_button_url' => array('Ссылка кнопки регистрации', 'url'),
            'hero_date' => array('Срок регистрации', 'text'),
            'hero_logo' => array('Логотип в баннере', 'media'),
        )),
        'prize' => array('Главный приз', array(
            'prize_label' => array('Надзаголовок', 'text'),
            'prize_title' => array('Заголовок', 'textarea'),
            'prize_text' => array('Описание', 'textarea'),
            'prize_image' => array('Изображение плаката', 'media'),
            'prize_image_alt' => array('Описание изображения', 'text'),
        )),
        'audience' => array('Кто может участвовать', array(
            'audience_title' => array('Заголовок', 'text'),
            'audience_text' => array('Описание', 'textarea'),
            'audience_first' => array('Первая группа', 'text'),
            'audience_second' => array('Вторая группа', 'text'),
            'audience_button' => array('Текст кнопки', 'text'),
            'audience_url' => array('Ссылка кнопки', 'url'),
        )),
        'status' => array('Статус конкурса', array(
            'status_label' => array('Подпись', 'text'),
            'status_title' => array('Основной статус', 'text'),
            'status_date' => array('Строка справа', 'text'),
            'status_link' => array('Текст ссылки', 'text'),
            'status_url' => array('Ссылка', 'url'),
        )),
        'invitation' => array('Приглашение', array(
            'invitation_text' => array('Текст', 'textarea'),
            'invitation_button' => array('Текст кнопки', 'text'),
            'invitation_url' => array('Ссылка кнопки', 'url'),
            'invitation_image' => array('Иллюстрация', 'media'),
            'invitation_image_alt' => array('Описание иллюстрации', 'text'),
            'invitation_video' => array('Видео', 'media'),
        )),
        'process' => array('Как проходит конкурс', array(
            'process_label' => array('Надзаголовок', 'text'),
            'process_title' => array('Заголовок', 'textarea'),
            'round_one_title' => array('Название первого тура', 'text'),
            'test_title' => array('Название этапа 1', 'text'),
            'test_text' => array('Описание этапа 1', 'textarea'),
            'essay_title' => array('Название этапа 2', 'text'),
            'essay_text' => array('Описание этапа 2', 'textarea'),
            'round_one_note' => array('Примечание первого тура', 'textarea'),
            'round_two_title' => array('Название второго тура', 'text'),
            'round_route' => array('Этапы через запятую', 'text'),
            'round_two_text_1' => array('Первый абзац второго тура', 'textarea'),
            'round_two_text_2' => array('Второй абзац второго тура', 'textarea'),
            'stat_1_value' => array('Показатель 1 — число', 'text'),
            'stat_1_label' => array('Показатель 1 — подпись', 'text'),
            'stat_2_value' => array('Показатель 2 — число', 'text'),
            'stat_2_label' => array('Показатель 2 — подпись', 'text'),
            'stat_3_value' => array('Показатель 3 — число', 'text'),
            'stat_3_label' => array('Показатель 3 — подпись', 'text'),
            'stat_4_value' => array('Показатель 4 — число', 'text'),
            'stat_4_label' => array('Показатель 4 — подпись', 'text'),
            'stat_5_value' => array('Показатель 5 — число', 'text'),
            'stat_5_label' => array('Показатель 5 — подпись', 'text'),
            'final_note' => array('Примечание о финале', 'textarea'),
            'service_1_title' => array('Карточка 1 — заголовок', 'text'),
            'service_1_text' => array('Карточка 1 — текст', 'textarea'),
            'service_2_title' => array('Карточка 2 — заголовок', 'text'),
            'service_2_text' => array('Карточка 2 — текст', 'textarea'),
            'service_3_title' => array('Карточка 3 — заголовок', 'text'),
            'service_3_text' => array('Карточка 3 — текст', 'textarea'),
        )),
        'news' => array('Новости', array(
            'news_label' => array('Надзаголовок', 'text'),
            'news_title' => array('Заголовок', 'text'),
            'news_text' => array('Описание', 'textarea'),
            'news_button' => array('Текст кнопки', 'text'),
            'news_url' => array('Ссылка кнопки', 'url'),
        )),
    );
}

function tarhan_home_defaults()
{
    $information = function_exists('tarhan_content_page_url') ? tarhan_content_page_url('information') : home_url('/information/');
    return array(
        'hero_title' => 'Историко-культурный конкурс «Тархан»',
        'hero_text' => "Знаешь историю? Докажи!\nЕсли ты в 10–11 классе и любишь историю — это твой шанс стать студентом КФУ.\nТема конкурса: история и культура России, Татарстана и тюркских народов.\nПройди два тура — стань победителем.\nОрганизаторы: Министерство культуры Республики Татарстан и ИМОиВ КФУ.",
        'hero_button' => 'Зарегистрироваться', 'hero_button_url' => home_url('/registration/'), 'hero_date' => 'Срок регистрации уточняется',
        'hero_logo' => get_theme_file_uri('/dist/assets/tarhan-logo-editorial-jb9pnA3V.png'),
        'prize_label' => 'ГЛАВНЫЙ ПРИЗ', 'prize_title' => 'Прими участие в историко-культурном конкурсе «Тархан»',
        'prize_text' => "Главный приз: поступление в КФУ на бесплатной основе на профиль «История тюркских народов» Института международных отношений, истории и востоковедения КФУ.\nДва тура — заочный и очный телевизионный. Вопросы по истории России, Татарстана и культуре тюркских народов.\nКонкурс популяризирует историю России, Татарстана и тюркских народов, повышает интерес к культуре и библиотечному наследию, продвигает культуру чтения.",
        'prize_image' => get_theme_file_uri('/dist/assets/competition-flyer-DWWtDLXn.png'), 'prize_image_alt' => 'Информационный плакат конкурса «Тархан»',
        'audience_title' => 'Кто может участвовать?', 'audience_text' => 'В конкурсе могут принять участие школьники 10–11 классов и/или студенты средних профессиональных образовательных учреждений всех субъектов Российской Федерации, а также абитуриенты из стран ближнего и дальнего зарубежья.',
        'audience_first' => 'Школьники 10–11 классов', 'audience_second' => 'Студенты СПО и абитуриенты', 'audience_button' => 'Узнать условия участия', 'audience_url' => $information . '#documents',
        'status_label' => 'Статус конкурса', 'status_title' => 'Регистрация скоро начнется', 'status_date' => 'начало ожидается 1 ноября', 'status_link' => 'Личный кабинет', 'status_url' => home_url('/entrance/'),
        'invitation_text' => 'Если ты любишь историю, уверен, что это дело твоей жизни и выбираешь куда поступить - прими участие в историко-культурном конкурсе «Тархан».',
        'invitation_button' => 'подробнее о конкурсе', 'invitation_url' => $information . '#documents', 'invitation_image' => get_theme_file_uri('/dist/assets/oncomputer-gray-BxoW7xCC.png'),
        'invitation_image_alt' => 'Участник конкурса работает за ноутбуком', 'invitation_video' => get_theme_file_uri('/dist/assets/9241536695013-C3CLkEmo.mp4'),
        'process_label' => 'Как проходит конкурс', 'process_title' => 'Два тура — от заявки до телевизионного финала', 'round_one_title' => 'I тур · заочный',
        'test_title' => 'Тестирование', 'test_text' => 'Вопросы по истории и культуре России, Татарстана и тюркских народов.', 'essay_title' => 'Эссе',
        'essay_text' => 'Тема: «Почему я выбрал историю». Работы оценивает жюри.', 'round_one_note' => 'Участники, успешно прошедшие отборочные задания, переходят в очный тур.',
        'round_two_title' => 'II тур · очно-телевизионный', 'round_route' => '1/8, 1/4, 1/2, Финал',
        'round_two_text_1' => 'В каждом этапе участвуют 6 конкурсантов. В следующий этап проходят 3 участника.', 'round_two_text_2' => 'Вопросы посвящены истории и культуре России, Татарстана и тюркских народов.',
        'stat_1_value' => '40', 'stat_1_label' => 'участников очного тура', 'stat_2_value' => '4', 'stat_2_label' => 'конкурсных этапа', 'stat_3_value' => '6', 'stat_3_label' => 'финалистов',
        'stat_4_value' => '3', 'stat_4_label' => 'призёра', 'stat_5_value' => '1', 'stat_5_label' => 'победитель', 'final_note' => 'В финале оцениваются знания, артистизм и харизма участников.',
        'service_1_title' => 'Льготное поступление', 'service_1_text' => 'Профиль «История тюркских народов», направление 46.03.01. Необходимы действующие ЕГЭ или внутренние вступительные испытания: русский язык, история и обществознание либо иностранный язык.',
        'service_2_title' => '', 'service_2_text' => 'Испытания очного тура сопровождаются онлайн-трансляцией и получают телевизионную версию.',
        'service_3_title' => 'Телеверсии', 'service_3_text' => 'Профиль «История тюркских народов», направление 46.03.01. Необходимы действующие ЕГЭ или внутренние вступительные испытания: русский язык, история и обществознание либо иностранный язык.',
        'news_label' => 'Новости конкурса', 'news_title' => 'Все новости — во ВКонтакте', 'news_text' => 'Анонсы этапов, результаты, фотографии со съёмок и важные сообщения организаторов публикуются в официальном сообществе конкурса.',
        'news_button' => 'Перейти в сообщество', 'news_url' => 'https://vk.com/tarhan_konkurs',
    );
}

function tarhan_home_content()
{
    $saved = get_option('tarhan_home_content', array());
    $saved = is_array($saved) ? $saved : array();
    $content = array_merge(tarhan_home_defaults(), $saved);
    if (!array_key_exists('status_title', $saved) && function_exists('tarhan_seasons_state')) {
        try {
            $state = tarhan_seasons_state();
            $content['status_title'] = $state['home_status_title'] ?? $content['status_title'];
            $content['status_date'] = $state['home_status_date'] ?? $content['status_date'];
        } catch (Throwable $error) { /* Keep safe defaults. */ }
    }
    return $content;
}

function tarhan_home_sanitize($raw)
{
    $raw = is_array($raw) ? $raw : array();
    $clean = array();
    foreach (tarhan_home_schema() as $section) {
        foreach ($section[1] as $key => $field) {
            $value = isset($raw[$key]) && is_scalar($raw[$key]) ? (string) $raw[$key] : '';
            if ($field[1] === 'url' || $field[1] === 'media') { $clean[$key] = esc_url_raw(trim($value)); }
            elseif ($field[1] === 'textarea') { $clean[$key] = tarhan_rich_sanitize($value, !in_array($key, array('prize_title', 'process_title'), true)); }
            else { $clean[$key] = sanitize_text_field($value); }
        }
    }
    return $clean;
}

function tarhan_home_admin_page()
{
    if (!current_user_can('edit_theme_options')) { wp_die('Недостаточно прав.', '', array('response' => 403)); }
    $notice = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        check_admin_referer('tarhan_home_save');
        $content = tarhan_home_sanitize(wp_unslash($_POST['tarhan_home'] ?? array()));
        update_option('tarhan_home_content', $content, false);
        $seasons = get_option('tarhan_seasons');
        if (is_array($seasons)) {
            $seasons['home_status_title'] = $content['status_title'];
            $seasons['home_status_date'] = $content['status_date'];
            $seasons['revision'] = (int) ($seasons['revision'] ?? 0) + 1;
            update_option('tarhan_seasons', $seasons, false);
        }
        $notice = 'Изменения сохранены.';
    }
    $content = tarhan_home_content();
    echo '<div class="wrap tarhan-home-admin"><h1>Главная страница</h1><p>Здесь меняется содержимое главной без правки кода. Пустое поле будет показано пустым.</p>';
    if ($notice) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>'; }
    echo '<form method="post">'; wp_nonce_field('tarhan_home_save');
    foreach (tarhan_home_schema() as $section) {
        echo '<section class="tarhan-home-section"><h2>' . esc_html($section[0]) . '</h2><div class="tarhan-home-grid">';
        foreach ($section[1] as $key => $field) {
            $id = 'tarhan-home-' . str_replace('_', '-', $key);
            echo '<div class="tarhan-home-field"><label for="' . esc_attr($id) . '"><strong>' . esc_html($field[0]) . '</strong></label>';
            if ($field[1] === 'textarea') {
                tarhan_rich_field($id, 'tarhan_home[' . $key . ']', $content[$key], !in_array($key, array('prize_title', 'process_title'), true));
            } else {
                echo '<div class="tarhan-home-input"><input class="large-text" type="' . ($field[1] === 'url' ? 'url' : 'text') . '" id="' . esc_attr($id) . '" name="tarhan_home[' . esc_attr($key) . ']" value="' . esc_attr($content[$key]) . '">';
                if ($field[1] === 'media') { echo '<button type="button" class="button" data-tarhan-media>Выбрать файл</button>'; }
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div></section>';
    }
    submit_button('Сохранить главную страницу'); echo '</form></div>';
}

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=page', 'Главная страница', 'Главная страница', 'edit_theme_options', 'tarhan-home', 'tarhan_home_admin_page');
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'pages_page_tarhan-home') { return; }
    wp_enqueue_media();
    wp_enqueue_script('tarhan-home-editor', get_theme_file_uri('/assets/js/home-editor.js'), array('media-editor'), filemtime(get_theme_file_path('/assets/js/home-editor.js')), true);
    wp_enqueue_style('tarhan-home-editor', get_theme_file_uri('/assets/css/home-editor.css'), array(), filemtime(get_theme_file_path('/assets/css/home-editor.css')));
});
