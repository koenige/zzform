<?php 

/**
 * zzform
 * Logging of the database operations via zzform, function zzlog()
 * Protokoll der Datenbankeingaben mittels zzform, Funktion zz_log
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2007-2010 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// access restriction has to be set in the file including this file
// Bitte Zugriffsbeschränkungen in der Datei, die diese einbindet, definieren!

$zz['table'] = $zz_conf['logging_table'];

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'log_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'query';

if (!empty($zz_conf['logging_id'])) {
	$zz['fields'][3]['title'] = 'Record';
	$zz['fields'][3]['field_name'] = 'record_id';
	$zz['fields'][3]['type'] = 'number';
}

$zz['fields'][4]['field_name'] = 'user';

$zz['fields'][99]['field_name'] = 'last_update';
$zz['fields'][99]['type'] = 'display';
$zz['fields'][99]['type_detail'] = 'timestamp';

$zz['sql'] = 'SELECT * FROM '.$zz_conf['logging_table'];
$zz['sqlorder'] = ' ORDER BY log_id DESC';

$zz_conf['heading'] = 'Logging';
$zz_conf['max_select'] = 200;
$zz_conf['limit'] = 20;
$zz_conf['add'] = false;
$zz_conf['edit'] = false;
$zz_conf['delete'] = false;

?>