<?php
get_header();
?>
<main>
<?php
$content = file_get_contents(get_theme_file_path('/privacy-policy-content.html'));
echo str_replace(
    array('./documents/privacy-policy.pdf', './documents/geography-districts.zip'),
    array(esc_url(get_theme_file_uri('/dist/documents/privacy-policy.pdf')), esc_url(get_theme_file_uri('/assets/data/geography-districts.zip'))),
    $content
);
?>
</main>
<?php get_footer(); ?>
