<?php
/** Package the existing HTML and season data; does not load or change WordPress. */
$root = dirname(__DIR__);
$theme = $root . '/wordpress/wp-content/themes/tarhan';
if (!is_dir($theme . '/content')) { mkdir($theme . '/content', 0777, true); }
function content_dom($html) {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $before = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors(); libxml_use_internal_errors($before);
    return $dom;
}
function content_class($class) { return 'contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")'; }
function content_text($xpath, $query, $node = null) {
    $found = $xpath->query($query, $node)->item(0);
    return $found ? trim(preg_replace('/\s+/u', ' ', $found->textContent)) : '';
}
foreach (array('information', 'participant') as $name) {
    $file = $root . '/.participant-build/' . $name . '.html';
    if (!is_file($file)) { throw new RuntimeException('Run the Vite build first'); }
    $dom = content_dom(file_get_contents($file));
    $main = $dom->getElementsByTagName('main')->item(0);
    if (!$main) { throw new RuntimeException('Missing main: ' . $name); }
    file_put_contents($theme . '/content/' . $name . '.html', $dom->saveHTML($main));
}
$dom = content_dom(file_get_contents($root . '/src/html/components/archive/season/season.html'));
$x = new DOMXPath($dom);
$data = array('period' => '2021–2022', 'cover' => 'src/assets/photo/seasons/21-22.jpg',
    'intro' => content_text($x, '//*[' . content_class('season__intro') . ']'),
    'process_before' => content_text($x, '//section[@aria-labelledby="season-process"]/p[1]'),
    'process_after' => content_text($x, '//section[@aria-labelledby="season-process"]/p[2]'),
    'final_date' => $x->query('//time')->item(0)->getAttribute('datetime'),
    'results_text' => content_text($x, '//section[@aria-labelledby="season-results"]/p'),
    'watch_text' => content_text($x, '//section[@aria-labelledby="season-watch"]/p'));
foreach (array('stats', 'stages', 'jury', 'finalists', 'watch', 'gallery', 'media') as $key) { $data[$key] = array(); }
foreach ($x->query('//ul[' . content_class('season__stats') . ']/li') as $row) { $data['stats'][] = array('value' => content_text($x, './strong', $row), 'label' => content_text($x, './span', $row)); }
foreach ($x->query('//ol[' . content_class('season__stages') . ']/li') as $row) { $data['stages'][] = array('label' => trim($row->textContent)); }
foreach ($x->query('//dl[' . content_class('season__jury') . ']/div') as $row) { $data['jury'][] = array('name' => content_text($x, './dt', $row), 'role' => content_text($x, './dd', $row)); }
foreach ($x->query('//li[' . content_class('season__person') . ']') as $row) {
    $data['finalists'][] = array('name' => content_text($x, './/h3', $row), 'city' => content_text($x, './/p', $row), 'result' => content_text($x, './span', $row));
}
foreach (array('watch' => 'season__watch', 'media' => 'season__media') as $key => $class) {
    foreach ($x->query('//*[' . content_class($class) . ']//a') as $link) {
        $label = preg_replace('/\s+/u', ' ', trim(str_replace('↗', '', $link->textContent)));
        $data[$key][] = array('label' => $label, 'url' => $link->getAttribute('href'));
    }
}
foreach ($x->query('//ul[' . content_class('season__gallery') . ']//img') as $img) {
    $data['gallery'][] = array('image' => preg_replace('#^\./#', '', $img->getAttribute('src')), 'alt' => $img->getAttribute('alt'));
}
if (count($data['finalists']) !== 6 || count($data['gallery']) !== 14 || count($data['jury']) !== 5) { throw new RuntimeException('Source structure changed; review the import'); }
file_put_contents($theme . '/content/season-2021-2022.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo "Prepared participant, information and season 2021–2022 from current project files.\n";
