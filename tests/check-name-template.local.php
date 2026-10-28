<?php
$base = dirname(__DIR__) . '/wordpress/wp-content/plugins/tarhan-testing/';
$columns = json_decode(file_get_contents($base . 'data/organizer-columns.json'), true);
$zip = new ZipArchive(); $zip->open($base . 'templates/participants.xlsx');
$doc = new DOMDocument(); $doc->loadXML($zip->getFromName('xl/worksheets/sheet1.xml'));
$xp = new DOMXPath($doc); $xp->registerNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$count = $xp->query('//x:row[@r="5"]/x:c')->length;
if ($count !== count($columns)) { throw new RuntimeException('Template columns mismatch: ' . $count . '/' . count($columns)); }
echo 'PASS template columns: ' . $count . "\n";
$zip->close();
