<?php 

require_once '../inc/konfiguration.inc.php';

$level = '..';

$maintable = 'terminkalender';

$query[1]['title'] = 'ID';
$query[1]['field_name'] = 'termin_id';
$query[1]['type'] = 'id';

$query[2]['field_name'] = 'datum';
$query[2]['type'] = 'date';

$query[3]['field_name'] = 'uhrzeit';
$query[3]['type'] = 'time';

$query[4]['field_name'] = 'land';
$query[4]['type'] = 'text';

$query[5]['field_name'] = 'ort';
$query[5]['type'] = 'text';

$query[6]['field_name'] = 'beschreibung';
$query[6]['type'] = 'memo';
$query[6]['format'] = 'markdown';

$query[7]['field_name'] = 'anzeigen';
$query[7]['type'] = 'select';
$query[7]['enum'] = array('ja', 'nein');
$query[7]['default'] = 'ja';

$sql = 'SELECT * ';
$sql.= ' FROM terminkalender';
$sqlorder = ' ORDER BY datum DESC, uhrzeit DESC';

$editvar['search'] = true;
$h1 = 'Terminkalender';
$delete = true;
$language = 'de';

include ($level.'/inc/head.inc.php');
include ($level.'/inc/edit.inc.php'); 
include ($level.'/inc/foot.inc.php');

?>