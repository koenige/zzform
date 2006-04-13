<?php 

require_once '../inc/konfiguration.inc.php';

$level = '..';

$maintable = 'ausgabe';

$query[1]['title'] = 'ID';
$query[1]['field_name'] = 'ausgabe_id';
$query[1]['type'] = 'id';

$query[2]['field_name'] = 'anzeigen';
$query[2]['type'] = 'select';
$query[2]['set'] = array('Datum', 'Uhrzeit', 'Land', 'Ort', 'Beschreibung');
$query[2]['default'] = array('Datum', 'Uhrzeit', 'Land', 'Ort', 'Beschreibung');

$query[3]['field_name'] = 'datumsformat';
$query[3]['type'] = 'select';
$query[3]['enum'] = array('deutsch', 'englisch');
$query[3]['default'] = 'deutsch';

$query[4]['field_name'] = 'zeilen';
$query[4]['type'] = 'number';

$sql = 'SELECT * ';
$sql.= ' FROM ausgabe';
$sqlorder = '';

$editvar['search'] = true;
$h1 = 'Ausgabeeinstellungen';
$delete = false;
$add = false;
$language = 'de';

include ($level.'/inc/head.inc.php');
include ($level.'/inc/edit.inc.php'); 
include ($level.'/inc/foot.inc.php');

?>