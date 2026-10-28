<?php
$root = getcwd();
$old = json_decode(file_get_contents($root.'/.fio-columns.local.json'),true);
$new = json_decode(file_get_contents($root.'/wordpress/wp-content/plugins/tarhan-testing/data/organizer-columns.json'),true);
$path=$root.'/wordpress/wp-content/plugins/tarhan-testing/templates/participants.xlsx';
$zip=new ZipArchive(); if($zip->open($path)!==true) throw new RuntimeException('Template missing');
$ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
$letter=static function($n){$s='';for($n++;$n;$n=intdiv($n-1,26))$s=chr(65+($n-1)%26).$s;return $s;};
$doc=new DOMDocument();$doc->loadXML($zip->getFromName('xl/worksheets/sheet1.xml'));
$xp=new DOMXPath($doc);$xp->registerNamespace('x',$ns);
$cols=$xp->query('//x:cols')->item(0);while($cols->firstChild)$cols->removeChild($cols->firstChild);
foreach([4,5] as $r){
 $row=$xp->query('//x:row[@r="'.$r.'"]')->item(0);$cells=[];foreach($xp->query('x:c',$row) as $c)$cells[]=$c->cloneNode(true);
 while($row->firstChild)$row->removeChild($row->firstChild);
 foreach($new as $i=>$column){
  $key=['full_name'=>'last_name','parent_full_name'=>'parent_last_name'][$column[0]]??$column[0];
  $index=array_search($key,array_column($old,0),true);if($index===false)throw new RuntimeException('Column missing');
  $cell=$cells[$index];$cell->setAttribute('r',$letter($i).$r);
  while($cell->firstChild)$cell->removeChild($cell->firstChild);
  $cell->setAttribute('t','inlineStr');$is=$doc->createElementNS($ns,'is');$t=$doc->createElementNS($ns,'t');$t->appendChild($doc->createTextNode($r===4?$column[1]:''));$is->appendChild($t);$cell->appendChild($is);$row->appendChild($cell);
  if($r===4){$c=$doc->createElementNS($ns,'col');foreach(['min'=>$i+1,'max'=>$i+1,'width'=>$column[3],'customWidth'=>1]as $k=>$v)$c->setAttribute($k,(string)$v);$cols->appendChild($c);}
 }
 $row->removeAttribute('spans');
}
$last=$letter(count($new)-1);$xp->query('//x:dimension')->item(0)?->setAttribute('ref','A1:'.$last.'5');
$zip->addFromString('xl/worksheets/sheet1.xml',$doc->saveXML());
$doc=new DOMDocument();$doc->loadXML($zip->getFromName('xl/tables/table1.xml'));$xp=new DOMXPath($doc);$xp->registerNamespace('x',$ns);
$doc->documentElement->setAttribute('ref','A4:'.$last.'5');$xp->query('//x:autoFilter')->item(0)?->setAttribute('ref','A4:'.$last.'5');
$cols=$xp->query('//x:tableColumns')->item(0);while($cols->firstChild)$cols->removeChild($cols->firstChild);
foreach($new as $i=>$column){$c=$doc->createElementNS($ns,'tableColumn');$c->setAttribute('id',(string)($i+1));$c->setAttribute('name',$column[1]);$cols->appendChild($c);}
$cols->setAttribute('count',(string)count($new));$zip->addFromString('xl/tables/table1.xml',$doc->saveXML());$zip->close();echo 'Template: '.count($new).' columns';
