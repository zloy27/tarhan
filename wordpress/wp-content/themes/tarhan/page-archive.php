<?php
/* Template Name: Архив сезонов */
get_header();
$seasons = get_posts(array('post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1,
    'meta_key' => '_wp_page_template', 'meta_value' => 'page-season.php', 'orderby' => array('menu_order' => 'ASC', 'post_title' => 'DESC')));
?>
<main><section class="seasons"><div class="seasons__container container">
    <p class="seasons__subtitle">История конкурса</p>
    <h1 class="seasons__title"><?php echo esc_html(get_the_title(get_queried_object_id())); ?></h1>
    <p class="seasons__text">Участники, выпуски и памятные моменты</p>
    <div class="seasons__cards">
    <?php foreach ($seasons as $season) : $data = tarhan_page_content($season->ID); ?>
        <article class="seasons__card tarhan-season-card"><a class="seasons__card--link" href="<?php echo esc_url(get_permalink($season)); ?>">
            <?php tarhan_content_image($data['cover'], 'seasons__card--img', ''); ?>
            <h2 class="seasons__card--title"><?php echo esc_html($data['period'] ?: $season->post_title); ?></h2>
            <span class="seasons__card--link-box"><span class="seasons__card--link--text">Посмотреть сезон</span><span aria-hidden="true">→</span></span>
        </a></article>
    <?php endforeach; ?>
    </div>
    <?php if (!$seasons) : ?><p>Архив готовится к публикации.</p><?php endif; ?>
</div></section></main>
<?php get_footer(); ?>
