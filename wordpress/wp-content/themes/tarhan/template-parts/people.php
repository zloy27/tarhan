<?php
defined('ABSPATH') || exit;
get_header();
$data = tarhan_page_content(get_queried_object_id());
$prefix = $tarhan_people_kind === 'jury' ? 'jury-main' : 'ambassadors-main';
?>
<main><section class="<?php echo esc_attr($prefix); ?>" id="<?php echo esc_attr($prefix); ?>">
    <div class="<?php echo esc_attr($prefix); ?>__container container">
        <nav class="<?php echo esc_attr($prefix); ?>__switch" aria-label="Эксперты конкурса">
            <?php foreach (array('jury' => 'Жюри', 'ambassadors' => 'Амбассадоры') as $slug => $title) : ?>
                <a class="<?php echo esc_attr($prefix . '__switch--choose' . ($slug === $tarhan_people_kind ? '-page' : '')); ?>" href="<?php echo esc_url(tarhan_content_page_url($slug)); ?>" <?php if ($slug === $tarhan_people_kind) { echo 'aria-current="page"'; } ?>><?php echo esc_html($title); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($data['subtitle']) : ?><p class="<?php echo esc_attr($prefix); ?>__subtitle"><?php echo esc_html($data['subtitle']); ?></p><?php endif; ?>
        <h1 class="<?php echo esc_attr($prefix); ?>__title"><?php echo esc_html(get_the_title(get_queried_object_id())); ?></h1>
        <?php if ($data['intro']) : ?><div class="tarhan-content-intro"><?php tarhan_content_paragraphs($data['intro']); ?></div><?php endif; ?>
        <?php if (!$data['people']) : ?>
            <p>Информация будет опубликована после уточнения состава.</p>
        <?php else : ?>
            <div class="<?php echo esc_attr($prefix); ?>__cards">
                <?php foreach ($data['people'] as $person) : ?>
                    <article class="<?php echo esc_attr($prefix); ?>__card tarhan-person<?php echo !$person['image'] ? ' tarhan-person--no-photo' : ''; ?>">
                        <?php tarhan_content_image($person['image'], $prefix . '__card--img', $person['name']); ?>
                        <h2 class="<?php echo esc_attr($prefix); ?>__card--title"><?php echo esc_html($person['name']); ?></h2>
                        <div class="<?php echo esc_attr($prefix); ?>__card--description">
                            <?php tarhan_content_paragraphs($person['role']); tarhan_content_paragraphs($person['description']); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section></main>
<?php get_footer(); ?>
