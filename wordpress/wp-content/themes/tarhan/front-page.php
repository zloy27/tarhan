<?php
get_header();
$tarhan_home_status = array(
    'home_status_title' => 'Регистрация скоро начнется',
    'home_status_date' => 'начало ожидается 1 ноября',
);
if (function_exists('tarhan_seasons_state')) {
    try { $tarhan_home_status = array_merge($tarhan_home_status, array_intersect_key(tarhan_seasons_state(), $tarhan_home_status)); }
    catch (Throwable $error) { /* Keep the public page available with safe defaults. */ }
}
?>

<main>
    <!-- Здесь будет содержимое главной -->
         <div class="competition-hero" id="participate">
        <div class="container competition-hero__container">
            <div class="competition-hero__info">
                <div class="competition-hero__title">Историко-культурный конкурс «Тархан» 2026-2027</div>
                <p class="competition-hero__p">От Министерство культуры Республики Татарстан совместно с Высшей школой
                    исторических наук и всемирного культурного наследия Института международных отношений Казанского
                    (Приволжского) федерального университета</p>
                <div class="competition-hero__concurs"><a href="<?php echo esc_url(home_url('/registration/')); ?>"
                        class="competition-hero__concurs--button">зарегестрироваться</a>
                    <div class="competition-hero__concurs--data">
                        <div class="competition-hero__concurs-icon"><img
                                src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20width='24'%20height='24'%20viewBox='0%200%2024%2024'%20fill='none'%3e%3crect%20x='3'%20y='5'%20width='18'%20height='16'%20rx='2'%20stroke='%23C87552'%20stroke-width='2'/%3e%3cpath%20d='M3%209H21'%20stroke='%23C87552'%20stroke-width='2'/%3e%3cpath%20d='M8%203V7M16%203V7'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'/%3e%3cpath%20d='M7%2013H9M11%2013H13M15%2013H17M7%2017H9M11%2017H13M15%2017H17'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'/%3e%3c/svg%3e"
                                alt="" aria-hidden="true"></div>
                        <div class="competition-hero__concurs--text">регистрация до 11.11.12</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="competition-hero__banner">
            <div class="competition-hero__container">
                <div class="competition-hero__logo"><img src="<?php echo esc_url(get_theme_file_uri('/dist/assets/tarhan-logo-editorial-jb9pnA3V.png')); ?>" alt=""
                        aria-hidden="true"></div>
            </div>
        </div>
    </div>
<div class="competition-prize">
        <div class="competition-prize__container container">
            <div class="competition-prize__main">
                <div class="competition-prize__info">
                    <div class="competition-prize__info--subtitle">
                        <div class="competition-prize__info--line"></div><img class="competition-prize__info--marker"
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%3e%3cpath%20d='M12%202%2022%2012%2012%2022%202%2012%2012%202Z'%20stroke='%23C87552'%20stroke-width='1.8'/%3e%3cpath%20d='M12%207%2017%2012%2012%2017%207%2012%2012%207Z'%20fill='%23C87552'/%3e%3c/svg%3e"
                            width="18" height="18" alt="" aria-hidden="true"> <span
                            class="competition-prize__info--subtitle-text">ГЛАВНЫЙ ПРИЗ</span> <img
                            class="competition-prize__info--marker"
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%3e%3cpath%20d='M12%202%2022%2012%2012%2022%202%2012%2012%202Z'%20stroke='%23C87552'%20stroke-width='1.8'/%3e%3cpath%20d='M12%207%2017%2012%2012%2017%207%2012%2012%207Z'%20fill='%23C87552'/%3e%3c/svg%3e"
                            width="18" height="18" alt="" aria-hidden="true">
                        <div class="competition-prize__info--line"></div>
                    </div>
                    <div class="competition-prize__info--title">Стать студентом КФУ и учиться бесплатно — это реально!
                    </div>
                    <div class="competition-prize__info--text">Прими участие в историко-культурном конкурсе «Тархан».
                        Докажи, что ты знаешь историю и культуру тюркских народов. Пройди два тура — заочный и очный
                        телевизионный. Заяви о себе! Победи соперников, стань призёром — и поступи на бесплатной основе*
                        на профиль «История тюркских народов» Института международных отношений Казанского
                        (Приволжского) федерального университета. Конкурс направлен на популяризацию истории России,
                        Татарстана, истории и культуры тюркских народов, продвижение культуры чтения, повышение интереса
                        к историко-культурному библиотечному наследию.</div>
                </div>
                <div class="competition-prize__flyer"><img class="competition-prize__flyer-image"
                        src="<?php echo esc_url(get_theme_file_uri('/dist/assets/competition-flyer-DWWtDLXn.png')); ?>" width="1046" height="1068"
                        alt="Информационный плакат конкурса «Тархан»"></div>
            </div>
            <div class="competition-prize__bunner">
                <div class="competition-prize__bunner--info">
                    <div class="competition-prize__bunner--title">Кто может участвовать?</div>
                    <div class="competition-prize__bunner--text">В конкурсе могут принять участие школьники 10–11
                        классов и/или студенты средних профессиональных образовательных учреждений всех субъектов
                        Российской Федерации, а также абитуриенты из стран ближнего и дальнего зарубежья.</div>
                </div>
                <div class="competition-prize__bunner--icons">
                    <div class="competition-prize__bunner--icon"><img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2064%2064'%20fill='none'%3e%3cpath%20d='M32%203%2039%2010H49V20L56%2027%2049%2034V44H39L32%2051%2025%2044H15V34L8%2027%2015%2020V10H25L32%203Z'%20stroke='%23C87552'%20stroke-width='1.8'/%3e%3cpath%20d='M20%2021.5C25%2021.5%2028.5%2023%2032%2026V41C28.5%2038%2025%2036.5%2020%2036.5V21.5Z'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linejoin='round'/%3e%3cpath%20d='M44%2021.5C39%2021.5%2035.5%2023%2032%2026V41C35.5%2038%2039%2036.5%2044%2036.5V21.5Z'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linejoin='round'/%3e%3cpath%20d='M32%2026V41'%20stroke='%23C87552'%20stroke-width='2'/%3e%3c/svg%3e"
                            alt="">
                        <p class="competition-prize__bunner--span">Школьники 10–11 классов</p>
                    </div>
                    <div class="competition-prize__bunner--icon"><img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2064%2064'%20fill='none'%3e%3cpath%20d='M32%203%2039%2010H49V20L56%2027%2049%2034V44H39L32%2051%2025%2044H15V34L8%2027%2015%2020V10H25L32%203Z'%20stroke='%23C87552'%20stroke-width='1.8'/%3e%3cpath%20d='m17%2024%2015-7%2015%207-15%207-15-7Z'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linejoin='round'/%3e%3cpath%20d='M23%2028.5V35C28.5%2039%2035.5%2039%2041%2035V28.5'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'/%3e%3cpath%20d='M47%2024V34'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'/%3e%3ccircle%20cx='47'%20cy='36.5'%20r='2'%20fill='%23C87552'/%3e%3c/svg%3e"
                            alt="">
                        <p class="competition-prize__bunner--span">Студенты СПО и абитуриенты</p>
                    </div>
                </div>
                <div class="competition-prize__bunner--line" aria-hidden="true"></div><a
                    class="competition-prize__bunner--button" href="<?php echo esc_url(tarhan_content_page_url('information') . '#documents'); ?>">Узнать условия участия</a>
            </div>
        </div>
    </div>
    <section class="status">
        <div class="status__container container">
            <div class="status__left">
                <div class="status__icon"><img
                        src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20width='24'%20height='24'%20viewBox='0%200%2024%2024'%20fill='none'%3e%3crect%20x='3'%20y='5'%20width='18'%20height='16'%20rx='2'%20stroke='%23C87552'%20stroke-width='2'/%3e%3cpath%20d='M3%209H21'%20stroke='%23C87552'%20stroke-width='2'/%3e%3cpath%20d='M8%203V7M16%203V7'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'/%3e%3cpath%20d='M7%2013H9M11%2013H13M15%2013H17M7%2017H9M11%2017H13M15%2017H17'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'/%3e%3c/svg%3e"
                        alt=""></div>
                <div class="status__box"><span class="status__subtitle">Статус конкурса </span><span
                        class="status__title"><?php echo esc_html($tarhan_home_status['home_status_title']); ?></span></div>
            </div>
            <div class="status__right"><span class="status__data"><?php echo esc_html($tarhan_home_status['home_status_date']); ?> </span><a
                    href="<?php echo esc_url(home_url('/entrance/')); ?>" class="status__link"><span class="status__link--span">Личный кабинет</span>
                    <img src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%20fill='none'%3e%3cpath%20d='M5%2016h21m-7-7%207%207-7%207'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                        alt="" class="status__link--img"></a></div>
        </div>
    </section>
    <div class="competition-invitation">
        <div class="container competition-invitation__container">
            <div class="competition-invitation__info">
                <p class="competition-invitation__info--text">Если ты любишь историю, уверен, что это дело твоей жизни и
                    выбираешь куда поступить - прими участие в историко-культурном конкурсе «Тархан».</p>
                <div class="competition-invitation__info--content"><a
                        class="competition-invitation__button" href="<?php echo esc_url(tarhan_content_page_url('information') . '#documents'); ?>">подробнее о конкурсе</a>
                    <div class="competition-invitation__student-photo"><img src="<?php echo esc_url(get_theme_file_uri('/dist/assets/oncomputer-gray-BxoW7xCC.png')); ?>"
                            alt="Участник конкурса работает за ноутбуком"></div>
                </div>
            </div>
            <div class="competition-invitation__video--box">
                <div class="competition-invitation__video"><video src="<?php echo esc_url(get_theme_file_uri('/dist/assets/9241536695013-C3CLkEmo.mp4')); ?>" controls
                        preload="auto" muted playsinline></video></div>
            </div>
        </div>
    </div>
    <section class="competition-process" aria-labelledby="competition-process-title">
        <div class="competition-process__container container">
            <header class="competition-process__header">
                <div class="competition-process__eyebrow"><span class="competition-process__eyebrow-line"
                        aria-hidden="true"></span> <img class="competition-process__marker"
                        src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%3e%3cpath%20d='M12%202%2022%2012%2012%2022%202%2012%2012%202Z'%20stroke='%23C87552'%20stroke-width='1.6'/%3e%3cpath%20d='M12%207%2017%2012%2012%2017%207%2012%2012%207Z'%20fill='%23C87552'/%3e%3c/svg%3e"
                        width="18" height="18" alt="" aria-hidden="true"> <span
                        class="competition-process__eyebrow-text">Как проходит конкурс</span> <img
                        class="competition-process__marker"
                        src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%3e%3cpath%20d='M12%202%2022%2012%2012%2022%202%2012%2012%202Z'%20stroke='%23C87552'%20stroke-width='1.6'/%3e%3cpath%20d='M12%207%2017%2012%2012%2017%207%2012%2012%207Z'%20fill='%23C87552'/%3e%3c/svg%3e"
                        width="18" height="18" alt="" aria-hidden="true"> <span
                        class="competition-process__eyebrow-line" aria-hidden="true"></span></div>
                <h2 class="competition-process__title" id="competition-process-title">Два тура — от заявки до
                    телевизионного финала</h2>
            </header>
            <div class="competition-process__main">
                <article class="competition-process__round competition-process__round--remote">
                    <div class="competition-process__round-heading"><span
                            class="competition-process__round-number">I</span>
                        <h3 class="competition-process__round-title">I тур · заочный</h3>
                    </div>
                    <div class="competition-process__divider" aria-hidden="true"><span></span> <img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%3e%3cpath%20d='M12%202%2022%2012%2012%2022%202%2012%2012%202Z'%20stroke='%23C87552'%20stroke-width='1.6'/%3e%3cpath%20d='M12%207%2017%2012%2012%2017%207%2012%2012%207Z'%20fill='%23C87552'/%3e%3c/svg%3e"
                            width="12" height="12" alt=""> <span></span></div>
                    <ol class="competition-process__stage-list">
                        <li class="competition-process__stage">
                            <div class="competition-process__stage-icon"><img
                                    src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2048%2048'%20fill='none'%3e%3crect%20x='9'%20y='6'%20width='30'%20height='36'%20rx='4'%20stroke='%23C87552'%20stroke-width='2.3'/%3e%3cpath%20d='m15%2017%202.5%202.5L22%2014M26%2017h7M15%2028l2.5%202.5L22%2025M26%2028h7'%20stroke='%23C87552'%20stroke-width='2.3'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                                    width="42" height="42" alt="" aria-hidden="true"></div>
                            <div class="competition-process__stage-content"><span
                                    class="competition-process__stage-index">01</span>
                                <h4 class="competition-process__stage-title">Тестирование</h4>
                                <p class="competition-process__stage-text">Вопросы по истории и культуре России,
                                    Татарстана и тюркских народов.</p>
                            </div>
                        </li>
                        <li class="competition-process__stage">
                            <div class="competition-process__stage-icon"><img
                                    src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2048%2048'%20fill='none'%3e%3cpath%20d='M10%206h21l7%207v29H10V6Z'%20stroke='%23C87552'%20stroke-width='2.3'%20stroke-linejoin='round'/%3e%3cpath%20d='M31%206v8h7M16%2021h16M16%2027h11'%20stroke='%23C87552'%20stroke-width='2.3'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3cpath%20d='m18%2037%202.4-6.2L34.2%2017l4%204-13.8%2013.8L18%2037Z'%20fill='%23FDFCF8'%20stroke='%23C87552'%20stroke-width='2.3'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                                    width="42" height="42" alt="" aria-hidden="true"></div>
                            <div class="competition-process__stage-content"><span
                                    class="competition-process__stage-index">02</span>
                                <h4 class="competition-process__stage-title">Эссе</h4>
                                <p class="competition-process__stage-text">Тема: «Почему я выбрал историю». Работы
                                    оценивает жюри.</p>
                            </div>
                        </li>
                    </ol>
                    <p class="competition-process__round-note">Участники, успешно прошедшие отборочные задания,
                        переходят в очный тур.</p>
                </article>
                <article class="competition-process__round competition-process__round--onsite">
                    <div class="competition-process__round-heading"><span
                            class="competition-process__round-number">II</span>
                        <h3 class="competition-process__round-title">II тур · очно-телевизионный</h3>
                    </div>
                    <div class="competition-process__divider" aria-hidden="true"><span></span> <img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%3e%3cpath%20d='M12%202%2022%2012%2012%2022%202%2012%2012%202Z'%20stroke='%23C87552'%20stroke-width='1.6'/%3e%3cpath%20d='M12%207%2017%2012%2012%2017%207%2012%2012%207Z'%20fill='%23C87552'/%3e%3c/svg%3e"
                            width="12" height="12" alt=""> <span></span></div>
                    <ol class="competition-process__round-route" aria-label="Этапы очного тура">
                        <li class="competition-process__round-stage">1/8</li>
                        <li class="competition-process__route-arrow" aria-hidden="true"><img
                                src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%20fill='none'%3e%3cpath%20d='M5%2016h21m-7-7%207%207-7%207'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                                width="28" height="28" alt=""></li>
                        <li class="competition-process__round-stage">1/4</li>
                        <li class="competition-process__route-arrow" aria-hidden="true"><img
                                src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%20fill='none'%3e%3cpath%20d='M5%2016h21m-7-7%207%207-7%207'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                                width="28" height="28" alt=""></li>
                        <li class="competition-process__round-stage">1/2</li>
                        <li class="competition-process__route-arrow" aria-hidden="true"><img
                                src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%20fill='none'%3e%3cpath%20d='M5%2016h21m-7-7%207%207-7%207'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                                width="28" height="28" alt=""></li>
                        <li class="competition-process__round-stage competition-process__round-stage--final">Финал</li>
                    </ol>
                    <div class="competition-process__round-copy">
                        <p>В каждом этапе участвуют 6 конкурсантов. В следующий этап проходят 3 участника.</p>
                        <div class="competition-process__divider" aria-hidden="true"><span></span> <img
                                src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%3e%3cpath%20d='M12%202%2022%2012%2012%2022%202%2012%2012%202Z'%20stroke='%23C87552'%20stroke-width='1.6'/%3e%3cpath%20d='M12%207%2017%2012%2012%2017%207%2012%2012%207Z'%20fill='%23C87552'/%3e%3c/svg%3e"
                                width="12" height="12" alt=""> <span></span></div>
                        <p>Вопросы посвящены истории и культуре России, Татарстана и тюркских народов.</p>
                    </div>
                </article>
            </div>
            <div class="competition-process__banner" aria-label="Путь участников конкурса">
                <ol class="competition-process__stats">
                    <li class="competition-process__stat"><span class="competition-process__stat-icon"><img
                                src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2048%2048'%20fill='none'%3e%3ccircle%20cx='24'%20cy='15'%20r='6'%20stroke='%23C87552'%20stroke-width='2.2'/%3e%3ccircle%20cx='11.5'%20cy='20'%20r='4'%20stroke='%23C87552'%20stroke-width='2.2'/%3e%3ccircle%20cx='36.5'%20cy='20'%20r='4'%20stroke='%23C87552'%20stroke-width='2.2'/%3e%3cpath%20d='M13%2039v-4c0-6.1%204.9-11%2011-11s11%204.9%2011%2011v4H13ZM3.5%2038v-3.5c0-4.7%203.8-8.5%208.5-8.5%201.3%200%202.5.3%203.6.8M44.5%2038v-3.5c0-4.7-3.8-8.5-8.5-8.5-1.3%200-2.5.3-3.6.8'%20stroke='%23C87552'%20stroke-width='2.2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                                width="42" height="42" alt="" aria-hidden="true"> </span><span
                            class="competition-process__stat-value">48</span> <span
                            class="competition-process__stat-label">участников</span></li>
                    <li class="competition-process__stat-arrow" aria-hidden="true"><img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%20fill='none'%3e%3cpath%20d='M5%2016h21m-7-7%207%207-7%207'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                            width="28" height="28" alt=""></li>
                    <li class="competition-process__stat"><span class="competition-process__stat-icon"><img
                                src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2048%2048'%20fill='none'%3e%3cg%20fill='%23C87552'%3e%3ccircle%20cx='12'%20cy='15'%20r='4'/%3e%3ccircle%20cx='24'%20cy='12'%20r='4'/%3e%3ccircle%20cx='36'%20cy='15'%20r='4'/%3e%3ccircle%20cx='12'%20cy='29'%20r='4'/%3e%3ccircle%20cx='24'%20cy='32'%20r='4'/%3e%3ccircle%20cx='36'%20cy='29'%20r='4'/%3e%3c/g%3e%3cpath%20d='M5%2023c1.8-2.5%204.1-3.8%207-3.8%202.7%200%205%201.2%206.8%203.5M17%2021c1.8-2.5%204.1-3.8%207-3.8%202.7%200%205%201.2%206.8%203.5M29%2023c1.8-2.5%204.1-3.8%207-3.8%202.7%200%205%201.2%206.8%203.5M5%2040c1.8-4%204.1-6%207-6M43%2040c-1.8-4-4.1-6-7-6M17%2042c1.8-3.8%204.1-5.8%207-5.8%202.7%200%205%201.9%206.8%205.8'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'/%3e%3c/svg%3e"
                                width="42" height="42" alt="" aria-hidden="true"> </span><span
                            class="competition-process__stat-value">6</span> <span
                            class="competition-process__stat-label">в этапе</span></li>
                    <li class="competition-process__stat-arrow" aria-hidden="true"><img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%20fill='none'%3e%3cpath%20d='M5%2016h21m-7-7%207%207-7%207'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                            width="28" height="28" alt=""></li>
                    <li class="competition-process__stat"><span class="competition-process__stat-icon"><img
                                src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2048%2048'%20fill='none'%3e%3ccircle%20cx='19'%20cy='15'%20r='6'%20stroke='%23C87552'%20stroke-width='2.3'/%3e%3cpath%20d='M8%2039v-5c0-6.1%204.9-11%2011-11s11%204.9%2011%2011v5H8Z'%20stroke='%23C87552'%20stroke-width='2.3'%20stroke-linejoin='round'/%3e%3cpath%20d='M30%2017h12m-5-5%205%205-5%205'%20stroke='%23C87552'%20stroke-width='2.3'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                                width="42" height="42" alt="" aria-hidden="true"> </span><span
                            class="competition-process__stat-value">3</span> <span
                            class="competition-process__stat-label">проходят дальше</span></li>
                    <li class="competition-process__stat-arrow" aria-hidden="true"><img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%20fill='none'%3e%3cpath%20d='M5%2016h21m-7-7%207%207-7%207'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                            width="28" height="28" alt=""></li>
                    <li class="competition-process__stat"><span class="competition-process__stat-icon"><img
                                src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2048%2048'%20fill='none'%3e%3cpath%20d='M20%2039C12%2035%207%2027%207%2018M28%2039c8-4%2013-12%2013-21M12%2028l-5-3M14%2034l-4%201M10%2021l-4-5M36%2028l5-3M34%2034l4%201M38%2021l4-5'%20stroke='%23C87552'%20stroke-width='2.2'%20stroke-linecap='round'/%3e%3cpath%20d='m24%2010%202.8%205.7%206.2.9-4.5%204.4%201.1%206.2-5.6-2.9-5.6%202.9%201.1-6.2-4.5-4.4%206.2-.9L24%2010Z'%20stroke='%23C87552'%20stroke-width='2.2'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                                width="42" height="42" alt="" aria-hidden="true"> </span><span
                            class="competition-process__stat-value">6</span> <span
                            class="competition-process__stat-label">финалистов</span></li>
                    <li class="competition-process__stat-arrow" aria-hidden="true"><img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%20fill='none'%3e%3cpath%20d='M5%2016h21m-7-7%207%207-7%207'%20stroke='%23C87552'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                            width="28" height="28" alt=""></li>
                    <li class="competition-process__stat competition-process__stat--accent"><span
                            class="competition-process__stat-icon"><img
                                src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2048%2048'%20fill='none'%3e%3cpath%20d='M15%207h18v8c0%207-3.7%2012-9%2012s-9-5-9-12V7Z'%20stroke='%23FDFCF8'%20stroke-width='2.4'%20stroke-linejoin='round'/%3e%3cpath%20d='M15%2011H8v3c0%205.5%203.7%209%209%209M33%2011h7v3c0%205.5-3.7%209-9%209M24%2027v7M17%2041h14M19%2034h10v7H19z'%20stroke='%23FDFCF8'%20stroke-width='2.4'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                                width="42" height="42" alt="" aria-hidden="true"> </span><span
                            class="competition-process__stat-value">3</span> <span
                            class="competition-process__stat-label">призёра</span></li>
                </ol>
            </div>
            <p class="competition-process__final-note">В финале оцениваются знания, артистизм и харизма участников.</p>
            <footer class="competition-process__footer">
                <div class="competition-process__service competition-process__service--admission"><span
                        class="competition-process__service-icon"><img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2048%2048'%20fill='none'%3e%3cpath%20d='m24%206%2018%209H6l18-9ZM9%2039h30M5%2043h38M12%2019v16M20%2019v16M28%2019v16M36%2019v16'%20stroke='%23C87552'%20stroke-width='2.3'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                            width="44" height="44" alt="" aria-hidden="true"></span>
                    <div>
                        <h3 class="competition-process__service-title">Льготное поступление</h3>
                        <p class="competition-process__service-text">Профиль «История тюркских народов», направление
                            46.03.01. Необходимы действующие ЕГЭ или внутренние вступительные испытания: русский язык,
                            история и обществознание либо иностранный язык.</p>
                    </div>
                </div>
                <div class="competition-process__service competition-process__service--broadcast"><span
                        class="competition-process__service-icon"><img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2048%2048'%20fill='none'%3e%3cpath%20d='M24%2020v21M18%2041h12M20%2029h8l-4-9-4%209Z'%20stroke='%23C87552'%20stroke-width='2.3'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3ccircle%20cx='24'%20cy='13'%20r='3'%20fill='%23C87552'/%3e%3cpath%20d='M16.5%206.5a9.2%209.2%200%200%200%200%2013M31.5%206.5a9.2%209.2%200%200%201%200%2013M11%202a15.7%2015.7%200%200%200%200%2022M37%202a15.7%2015.7%200%200%201%200%2022'%20stroke='%23C87552'%20stroke-width='2.3'%20stroke-linecap='round'/%3e%3c/svg%3e"
                            width="44" height="44" alt="" aria-hidden="true"></span>
                    <p class="competition-process__service-text">Испытания очного тура сопровождаются онлайн-трансляцией
                        и получают телевизионную версию.</p>
                </div>
                <div class="competition-process__service competition-process__service--admission"><span
                        class="competition-process__service-icon"><img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2048%2048'%20fill='none'%3e%3cpath%20d='m24%206%2018%209H6l18-9ZM9%2039h30M5%2043h38M12%2019v16M20%2019v16M28%2019v16M36%2019v16'%20stroke='%23C87552'%20stroke-width='2.3'%20stroke-linecap='round'%20stroke-linejoin='round'/%3e%3c/svg%3e"
                            width="44" height="44" alt="" aria-hidden="true"></span>
                    <div>
                        <h3 class="competition-process__service-title">Телеверсии</h3>
                        <p class="competition-process__service-text">Профиль «История тюркских народов», направление
                            46.03.01. Необходимы действующие ЕГЭ или внутренние вступительные испытания: русский язык,
                            история и обществознание либо иностранный язык.</p>
                    </div>
                </div>
            </footer>
        </div>
    </section>
    <section class="competition-news" id="news">
        <div class="competition-news__container container">
            <div class="competition-news__heading"><span class="competition-news__subtitle">Новости конкурса</span>
                <h2 class="competition-news__title">Все новости — во ВКонтакте</h2>
            </div>
            <div class="competition-news__content">
                <p class="competition-news__text">Анонсы этапов, результаты, фотографии со съёмок и важные сообщения
                    организаторов публикуются в официальном сообществе конкурса.</p><a class="competition-news__link"
                    href="https://vk.com/tarhan_konkurs" target="_blank" rel="noopener noreferrer"><span
                        class="competition-news__link--icon"><img
                            src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%20576%20512'%20fill='%23101010'%3e%3c!--%20Font%20Awesome%20Free%205.15.4,%20Brands:%20CC%20BY%204.0%20--%3e%3cpath%20d='M545%20117.7c3.7-12.5%200-21.7-17.8-21.7h-58.9c-15%200-21.9%207.9-25.6%2016.7%200%200-30%2073.1-72.4%20120.5-13.7%2013.7-20%2018.1-27.5%2018.1-3.7%200-9.4-4.4-9.4-16.9V117.7c0-15-4.2-21.7-16.6-21.7h-92.6c-9.4%200-15%207-15%2013.5%200%2014.2%2021.2%2017.5%2023.4%2057.5v86.8c0%2019-3.4%2022.5-10.9%2022.5-20%200-68.6-73.4-97.4-157.4-5.8-16.3-11.5-22.9-26.6-22.9H38.8c-16.8%200-20.2%207.9-20.2%2016.7%200%2015.6%2020%2093.1%2093.1%20195.5C160.4%20378.1%20229%20416%20291.4%20416c37.5%200%2042.1-8.4%2042.1-22.9%200-66.8-3.4-73.1%2015.4-73.1%208.7%200%2023.7%204.4%2058.7%2038.1%2040%2040%2046.6%2057.9%2069%2057.9h58.9c16.8%200%2025.3-8.4%2020.4-25-11.2-34.9-86.9-106.7-90.3-111.5-8.7-11.2-6.2-16.2%200-26.2.1-.1%2072-101.3%2079.4-135.6z'/%3e%3c/svg%3e"
                            alt=""> </span><span>Перейти в сообщество</span> <span class="competition-news__link--arrow"
                        aria-hidden="true">→</span></a>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
