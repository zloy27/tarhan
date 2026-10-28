<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <header class="header">
        <div class="container">
            <div class="header__container">
                <ul class="header__logos">
                    <li class="header__logo"><img class="header__logo--tarhan" width="579" height="95"
                            src="<?php echo esc_url(get_theme_file_uri('/dist/assets/tarhan-logo-new-BCOlHxkg.png')); ?>" alt="тарахан конкурс"></li>
                    <li class="header__logo"><img class="header__logo--kfu" src="<?php echo esc_url(get_theme_file_uri('/dist/assets/kfu-logo-CtUFskgE.png')); ?>"
                            width="487" height="95" alt="Казанский федеральный университет"></li>
                    <li class="header__logo"><img class="header__logo--mincult" width="462" height="72"
                            src="<?php echo esc_url(get_theme_file_uri('/dist/assets/minkult-logo-CA3nMIkK.png')); ?>" alt="минестерство культуры"></li>
                    <li class="header__logo"><img class="header__logo--alien" width="307" height="87"
                            src="<?php echo esc_url(get_theme_file_uri('/dist/assets/alien-logo-DI15Txve.png')); ?>" alt="alien studio"></li>
                </ul><button class="header__burger" type="button" aria-expanded="false"
                    aria-controls="header-navigation" aria-label="Открыть меню"><span></span> <span></span>
                    <span></span></button>
                <ul class="header__titles" id="header-navigation">
                    <li class="header__title"><a href="<?php echo esc_url(home_url('/#participate')); ?>" class="header__title-a">Главная</a>
                    </li>
                    <li class="header__title"><a href="<?php echo esc_url(tarhan_content_page_url('archive')); ?>" class="header__title-a">Архив</a></li>
                    <li class="header__title"><a href="<?php echo esc_url(tarhan_content_page_url('jury')); ?>" class="header__title-a">Эксперты</a></li>
                    <li class="header__title"><a href="<?php echo esc_url(tarhan_content_page_url('participant')); ?>" class="header__title-a">Участнику</a></li>
                    <li class="header__title"><a href="<?php echo esc_url(tarhan_content_page_url('information') . '#contacts'); ?>"
                            class="header__title-a">Информация</a></li>
                    <li class="header__title"><a href="<?php echo esc_url(home_url('/entrance/')); ?>" class="header__title-a">Участвовать</a></li>
                </ul>
            </div>
        </div>
    </header>
