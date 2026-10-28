<?php
// Extend the existing export template without changing styles or prior column positions.
$path = dirname(__DIR__) . '/wordpress/wp-content/plugins/tarhan-testing/templates/participants.xlsx';
$zip = new ZipArchive();
if ($zip->open($path) !== true) { throw new RuntimeException('Template unavailable'); }
$columns = json_decode(file_get_contents(dirname(__DIR__) . '/wordpress/wp-content/plugins/tarhan-testing/data/organizer-columns.json'), true);
$letter = static function ($n) { $s = ''; for ($n++; $n; $n = intdiv($n - 1, 26)) { $s = chr(65 + ($n - 1) % 26) . $s; } return $s; };
$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
$sheet = new DOMDocument(); $sheet->loadXML($zip->getFromName('xl/worksheets/sheet1.xml'), LIBXML_NONET);
$xp = new DOMXPath($sheet); $xp->registerNamespace('x', $ns);
$header = $xp->query('//x:row[@r="4"]')->item(0);
$prototype = $xp->query('//x:row[@r="5"]')->item(0);
$old_count = $xp->query('x:c', $header)->length;
for ($i = $old_count; $i < count($columns); $i++) {
    foreach (array(4 => $header, 5 => $prototype) as $number => $row) {
        $cell = $xp->query('x:c', $row)->item(1)->cloneNode(true);
        $cell->setAttribute('r', $letter($i) . $number);
        while ($cell->firstChild) { $cell->removeChild($cell->firstChild); }
        $cell->setAttribute('t', 'inlineStr');
        $text = $sheet->createElementNS($ns, 't'); $text->appendChild($sheet->createTextNode($number === 4 ? $columns[$i][1] : ''));
        $is = $sheet->createElementNS($ns, 'is'); $is->appendChild($text); $cell->appendChild($is); $row->appendChild($cell);
    }
    $col = $sheet->createElementNS($ns, 'col');
    foreach (array('min' => $i + 1, 'max' => $i + 1, 'width' => $columns[$i][3], 'customWidth' => 1) as $key => $value) { $col->setAttribute($key, (string) $value); }
    $xp->query('//x:cols')->item(0)->appendChild($col);
}
$last = $letter(count($columns) - 1);
$xp->query('//x:dimension')->item(0)?->setAttribute('ref', 'A1:' . $last . '5');
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet->saveXML());
$table = new DOMDocument(); $table->loadXML($zip->getFromName('xl/tables/table1.xml'), LIBXML_NONET);
$tx = new DOMXPath($table); $tx->registerNamespace('x', $ns);
$table->documentElement->setAttribute('ref', 'A4:' . $last . '5');
$tx->query('//x:autoFilter')->item(0)?->setAttribute('ref', 'A4:' . $last . '5');
$tc = $tx->query('//x:tableColumns')->item(0);
for ($i = $old_count; $i < count($columns); $i++) {
    $column = $table->createElementNS($ns, 'tableColumn'); $column->setAttribute('id', (string) ($i + 1)); $column->setAttribute('name', $columns[$i][1]); $tc->appendChild($column);
}
$tc->setAttribute('count', (string) count($columns));
$zip->addFromString('xl/tables/table1.xml', $table->saveXML()); $zip->close();
echo "Template extended: " . count($columns) . " columns\n";
