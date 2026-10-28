<?php
if (!defined('ABSPATH')) { exit; }

function tarhan_organizer_xml($text)
{
    // Excel reserves _xNNNN_ sequences even inside ordinary strings.
    $text = preg_replace('/_x([0-9a-fA-F]{4})_/', '_x005F_x$1_', (string) $text);
    $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
    return htmlspecialchars($text ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function tarhan_organizer_excel_date($value, $type)
{
    if ($type === 'datetime') {
        $date = wp_date('Y-m-d H:i:s', (int) $value);
    } else {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) { return null; }
        $date = $value . ' 00:00:00';
    }
    // Excel stores local wall time, without a timezone. WordPress supplies the timezone.
    $serial = strtotime($date . ' UTC') / DAY_IN_SECONDS + 25569;
    return $serial >= 1 ? $serial : null;
}

function tarhan_organizer_temp($directory)
{
    $path = $directory . '/organizers-' . bin2hex(random_bytes(18)) . '.tmp';
    $handle = fopen($path, 'x');
    if (!$handle) { throw new RuntimeException('Не удалось создать временный файл.'); }
    fclose($handle); @chmod($path, 0600);
    register_shutdown_function(static function () use ($path) { if (is_file($path)) { @unlink($path); } });
    return $path;
}

// Fill the styled, artifact-tool-authored template on the PHP host; no Node runtime is needed.
function tarhan_organizer_write_xlsx($rows, $destination, $note = '')
{
    if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) { throw new RuntimeException('Для Excel нужны расширения PHP zip и dom.'); }
    $template = __DIR__ . '/templates/participants.xlsx';
    if (!copy($template, $destination)) { throw new RuntimeException('Шаблон Excel недоступен.'); }
    $zip = new ZipArchive();
    if ($zip->open($destination) !== true) { throw new RuntimeException('Не удалось открыть Excel.'); }
    $sheet_path = 'xl/worksheets/sheet1.xml';
    $table_path = 'xl/tables/table1.xml';
    $relations_path = 'xl/worksheets/_rels/sheet1.xml.rels';
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $sheet_file = tarhan_organizer_temp(dirname($destination));
    $stream = null;
    try {
        $doc = new DOMDocument();
        if (!$doc->loadXML($zip->getFromName($sheet_path), LIBXML_NONET)) { throw new RuntimeException('Повреждён шаблон Excel.'); }
        $xpath = new DOMXPath($doc); $xpath->registerNamespace('x', $ns);
        $prototype = $xpath->query('//x:row[@r="5"]')->item(0);
        if (!$prototype) { throw new RuntimeException('Отсутствует строка шаблона.'); }
        $cells = array();
        foreach ($prototype->childNodes as $cell) {
            if ($cell instanceof DOMElement && $cell->localName === 'c') { $cells[] = array(preg_replace('/\d+/', '', $cell->getAttribute('r')), $cell->getAttribute('s')); }
        }
        $columns = tarhan_organizer_columns();
        if (count($cells) !== count($columns)) { throw new RuntimeException('Колонки шаблона не совпадают со схемой.'); }
        $prototype->parentNode->removeChild($prototype);
        $a2 = $xpath->query('//x:c[@r="A2"]/x:v')->item(0);
        while ($a2->firstChild) { $a2->removeChild($a2->firstChild); }
        $a2->appendChild($doc->createTextNode('Сформировано ' . wp_date('d.m.Y H:i') . '. Документы — после входа организатора. ' . $note));
        $xml = $doc->saveXML();
        $split = explode('</x:sheetData>', $xml, 2);
        if (count($split) !== 2) { throw new RuntimeException('Неизвестная структура шаблона.'); }
        $stream = fopen($sheet_file, 'wb');
        if (!$stream || fwrite($stream, $split[0]) === false) { throw new RuntimeException('Не удалось записать Excel.'); }
        $rels = new DOMDocument(); $rels->loadXML($zip->getFromName($relations_path), LIBXML_NONET);
        $hyperlinks = ''; $count = 0; $link_count = 0;
        foreach ($rows as $row) {
            $count++; $n = $count + 4;
            if ($n > 1048576) { throw new RuntimeException('Превышен предел строк Excel. Сузьте фильтры.'); }
            $line = '<x:row r="' . $n . '" ht="60" customHeight="1">';
            foreach ($columns as $index => $column) {
                list($key, $label, $type) = $column;
                list($letter, $style) = $cells[$index];
                $ref = $letter . $n;
                $value = $row[$key] ?? null;
                $start = '<x:c r="' . $ref . '" s="' . (int) $style . '"';
                if ($value === null || $value === '') { $line .= $start . '/>'; continue; }
                if (in_array($type, array('date', 'datetime'), true)) { $value = tarhan_organizer_excel_date($value, $type); }
                if (in_array($type, array('number', 'percent', 'date', 'datetime'), true)) {
                    $line .= $value === null ? $start . '/>' : $start . '><x:v>' . json_encode($value, JSON_THROW_ON_ERROR) . '</x:v></x:c>';
                } else {
                    if (mb_strlen((string) $value, 'UTF-8') > 32767) { throw new RuntimeException('Значение превышает длину ячейки Excel.'); }
                    $line .= $start . ' t="inlineStr"><x:is><x:t xml:space="preserve">' . tarhan_organizer_xml($type === 'link' ? $label : $value) . '</x:t></x:is></x:c>';
                    if ($type === 'link') {
                        if (++$link_count > 65530) { throw new RuntimeException('Превышен предел ссылок Excel. Сузьте фильтры.'); }
                        $rid = 'organizerLink' . $link_count;
                        $hyperlinks .= '<x:hyperlink ref="' . $ref . '" r:id="' . $rid . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>';
                        $rel = $rels->createElementNS('http://schemas.openxmlformats.org/package/2006/relationships', 'Relationship');
                        foreach (array('Id' => $rid, 'Type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink', 'Target' => $value, 'TargetMode' => 'External') as $attr => $val) { $rel->setAttribute($attr, $val); }
                        $rels->documentElement->appendChild($rel);
                    }
                }
            }
            $line .= '</x:row>';
            if (fwrite($stream, $line) !== strlen($line)) { throw new RuntimeException('Недостаточно места для Excel.'); }
        }
        if (!$count) { fwrite($stream, $doc->saveXML($prototype)); }
        $end = '</x:sheetData>' . ($hyperlinks ? '<x:hyperlinks>' . $hyperlinks . '</x:hyperlinks>' : '') . $split[1];
        if (fwrite($stream, $end) !== strlen($end)) { throw new RuntimeException('Не удалось завершить Excel.'); }
        fclose($stream); $stream = null;
        $table = new DOMDocument(); $table->loadXML($zip->getFromName($table_path), LIBXML_NONET);
        $range = 'A4:' . $cells[count($cells) - 1][0] . max(5, $count + 4);
        $table->documentElement->setAttribute('ref', $range);
        $filter = $table->createElementNS($ns, 'x:autoFilter'); $filter->setAttribute('ref', $range);
        $table->documentElement->insertBefore($filter, $table->documentElement->firstChild);
        if (!$zip->addFile($sheet_file, $sheet_path) || !$zip->addFromString($table_path, $table->saveXML()) || !$zip->addFromString($relations_path, $rels->saveXML())) { throw new RuntimeException('Не удалось собрать Excel.'); }
        if (!$zip->close()) { throw new RuntimeException('Не удалось сохранить Excel.'); }
        $zip = null;
        return $count;
    } finally {
        if (is_resource($stream)) { fclose($stream); }
        if ($zip instanceof ZipArchive) { $zip->close(); }
        if (is_file($sheet_file)) { unlink($sheet_file); }
    }
}

function tarhan_organizer_export()
{
    tarhan_organizer_require_access();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('Используйте кнопку выгрузки в таблице участников.', '', array('response' => 405)); }
    check_admin_referer('tarhan_organizer_export');
    $path = null; $error = null;
    global $wpdb;
    try {
        $directory = tarhan_private_directory();
        if (is_wp_error($directory)) { throw new RuntimeException($directory->get_error_message()); }
        $path = tarhan_organizer_temp($directory);
        $filters = tarhan_organizer_filters($_POST);
        $labels = array('search' => 'Поиск', 'region' => 'Регион', 'result' => 'Тест', 'essay' => 'Эссе', 'consent' => 'Согласие', 'season' => 'Сезон');
        $note = array();
        foreach ($filters as $key => $value) { if ($value !== '') { $note[] = $labels[$key] . ': ' . $value; } }
        if ($wpdb->query('START TRANSACTION WITH CONSISTENT SNAPSHOT') === false) { throw new RuntimeException('База данных недоступна.'); }
        try { tarhan_organizer_write_xlsx(tarhan_organizer_rows($filters), $path, implode('; ', $note)); }
        finally { $wpdb->query('ROLLBACK'); }
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="tarhan-participants-' . wp_date('Y-m-d-His') . '.xlsx"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    } catch (Throwable $exception) { $error = 'Не удалось сформировать Excel. Проверьте расширения PHP zip/dom, свободное место и доступность базы данных. При большой выборке сузьте фильтры.'; }
    finally { if ($path && is_file($path)) { unlink($path); } }
    if ($error) { wp_die(esc_html($error), '', array('response' => 500)); }
    exit;
}
add_action('admin_post_tarhan_organizer_export', 'tarhan_organizer_export');
add_action('admin_post_nopriv_tarhan_organizer_export', 'tarhan_organizer_export');
