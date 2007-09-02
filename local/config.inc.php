<?php 
/*
	Zugzwang Project
	Konfigurationsdatei

	(c) 02.02.2006 Gustaf Mossakowski, <gustaf@koenige.org>
*/

$zz_conf['dir'] 			= $_SERVER['DOCUMENT_ROOT'].'/www/_scripts/zzform';
$zz_conf['project']			= 'project';
$zz_conf['language']		= 'de';
$zz_conf['search']			= true;
$zz_conf['root']			= $_SERVER['DOCUMENT_ROOT'].'/www';
$zz_conf['relations_table']	= 'prefix__beziehungen';
$zz_conf['prefix']			= 'prefix_';

$zz_setting['scripts']		= $zz_conf['root'].'/_scripts';
$zz_setting['http_errors']	= $zz_setting['scripts'].'/errors';
$zz_setting['db_inc']		= $zz_setting['scripts'].'/zzform/local/db.inc.php';

$zz_page['head']			= $zz_setting['scripts'].'/zzform/local/intern-kopf.inc.php';
$zz_page['foot']			= $zz_setting['scripts'].'/zzform/local/intern-fuss.inc.php';

?>
