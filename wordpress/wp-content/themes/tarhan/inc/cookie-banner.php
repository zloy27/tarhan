<?php
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    foreach (array('css' => 'style', 'js' => 'script') as $extension => $kind) {
        $path = '/assets/' . $extension . '/cookie-consent.' . $extension;
        if ($kind === 'style') {
            wp_enqueue_style('tarhan-cookie-consent', get_theme_file_uri($path), array(), filemtime(get_theme_file_path($path)));
        } else {
            wp_enqueue_script('tarhan-cookie-consent', get_theme_file_uri($path), array(), filemtime(get_theme_file_path($path)), true);
        }
    }
});

add_action('wp_footer', function () {
    ?>
    <section class="tarhan-cookies" id="tarhan-cookies" aria-labelledby="tarhan-cookies-title" hidden>
        <div class="tarhan-cookies__copy">
            <h2 id="tarhan-cookies-title">Файлы cookie</h2>
            <p>Необходимые cookies обеспечивают вход и защиту форм и работают при любом выборе. Необязательные cookies сейчас не используются.</p>
            <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">Политика конфиденциальности</a>
        </div>
        <div class="tarhan-cookies__actions">
            <button type="button" data-cookie-choice="accepted">Принять</button>
            <button type="button" data-cookie-choice="rejected">Отклонить</button>
        </div>
    </section>
    <?php
});
