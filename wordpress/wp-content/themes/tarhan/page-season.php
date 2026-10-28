<?php
/* Template Name: Сезон конкурса */
get_header();
$data = tarhan_page_content(get_queried_object_id());
?>
<main><article class="season" id="season"><div class="container season__container">
    <nav class="season__breadcrumbs" aria-label="Хлебные крошки"><a href="<?php echo esc_url(tarhan_content_page_url('archive')); ?>">Архив сезонов</a><span aria-hidden="true">/</span><span aria-current="page"><?php echo esc_html($data['period'] ?: get_the_title()); ?></span></nav>
    <header class="season__header"><p class="season__subtitle">Как это было</p><h1 class="season__title"><?php echo esc_html(get_the_title()); ?></h1>
        <?php if ($data['intro']) : ?><div class="season__intro"><?php tarhan_content_paragraphs($data['intro']); ?></div><?php endif; ?>
    </header>
    <?php if ($data['stats']) : ?><ul class="season__stats" aria-label="Итоги отбора">
        <?php foreach ($data['stats'] as $stat) : ?><li><strong><?php echo esc_html($stat['value']); ?></strong><span><?php echo esc_html($stat['label']); ?></span></li><?php endforeach; ?>
    </ul><?php endif; ?>
    <?php if ($data['process_before'] || $data['process_after'] || $data['stages']) : ?>
        <section class="season__section" aria-labelledby="season-process"><h2 class="season__heading" id="season-process">Как проходил конкурс</h2>
            <?php tarhan_content_paragraphs($data['process_before']); ?>
            <?php if ($data['stages']) : ?><ol class="season__stages"><?php foreach ($data['stages'] as $stage) : ?><li><?php echo esc_html($stage['label']); ?></li><?php endforeach; ?></ol><?php endif; ?>
            <?php tarhan_content_paragraphs($data['process_after']); ?>
        </section>
    <?php endif; ?>
    <?php if ($data['final_date'] || $data['jury']) : ?>
        <section class="season__section" aria-labelledby="season-final"><div><h2 class="season__heading" id="season-final">Финал</h2>
            <?php if ($data['final_date']) : ?><time class="season__date" datetime="<?php echo esc_attr($data['final_date']); ?>"><?php echo esc_html(wp_date('j F Y', strtotime($data['final_date'] . ' 12:00:00'))); ?></time><?php endif; ?>
        </div>
        <?php if ($data['jury']) : ?><h3 class="season__subheading">Экспертное жюри</h3><dl class="season__jury">
            <?php foreach ($data['jury'] as $person) : ?><div class="season__jury--row"><dt><?php echo esc_html($person['name']); ?></dt><dd><?php echo esc_html($person['role']); ?></dd></div><?php endforeach; ?>
        </dl><?php endif; ?></section>
    <?php endif; ?>
    <?php if ($data['finalists'] || $data['results_text']) : ?>
        <section class="season__section" aria-labelledby="season-results"><h2 class="season__heading" id="season-results">Финалисты и результаты</h2>
            <?php if ($data['finalists']) : ?><ul class="season__finalists">
                <?php foreach ($data['finalists'] as $person) : ?><li class="season__person"><div><h3 class="season__person--name"><?php echo esc_html($person['name']); ?></h3><p class="season__person--city"><?php echo esc_html($person['city']); ?></p></div>
                    <?php if ($person['result']) : ?><span class="season__person--result<?php echo $person['result'] === 'Финалист' ? ' season__person--finalist' : ''; ?>"><?php echo esc_html($person['result']); ?></span><?php endif; ?></li><?php endforeach; ?>
            </ul><?php endif; ?>
            <?php tarhan_content_paragraphs($data['results_text']); ?>
        </section>
    <?php endif; ?>
    <?php if ($data['watch'] || $data['watch_text']) : ?><section class="season__section" aria-labelledby="season-watch"><h2 class="season__heading" id="season-watch">Где посмотреть</h2>
        <?php tarhan_content_paragraphs($data['watch_text']); ?><div class="season__watch">
            <?php foreach ($data['watch'] as $link) : ?><a href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($link['label'] ?: $link['url']); ?> <span aria-hidden="true">↗</span></a><?php endforeach; ?>
        </div></section><?php endif; ?>
    <?php if ($data['gallery']) : ?><section class="season__section" aria-labelledby="season-photos"><h2 class="season__heading" id="season-photos">Фотографии</h2><ul class="season__gallery">
        <?php foreach ($data['gallery'] as $index => $photo) : ?><li><a href="<?php echo esc_url(wp_get_attachment_image_url($photo['image'], 'full')); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr('Открыть фотографию ' . ($index + 1)); ?>"><?php tarhan_content_image($photo['image'], '', $photo['alt']); ?></a></li><?php endforeach; ?>
    </ul><p class="season__caption">Нажмите на снимок, чтобы открыть его полностью</p></section><?php endif; ?>
    <?php if ($data['media']) : ?><section class="season__section" aria-labelledby="season-media"><h2 class="season__heading" id="season-media">СМИ о нас</h2><ul class="season__media">
        <?php foreach ($data['media'] as $link) : ?><li><a href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($link['label'] ?: $link['url']); ?><span aria-hidden="true">↗</span></a></li><?php endforeach; ?>
    </ul></section><?php endif; ?>
</div></article></main>
<?php get_footer(); ?>
