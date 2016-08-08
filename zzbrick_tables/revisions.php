<?php 

/**
 * zzform module
 * Revisions of database records
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// access restriction has to be set in the file including this file
// Bitte Zugriffsbeschränkungen in der Datei, die diese einbindet, definieren!

if (empty($zz_conf['revisions_table'])) wrap_quit(404);

$zz['title'] = 'Revisions';
$zz['table'] = $zz_conf['revisions_table'];

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'revision_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'main_table_name';

$zz['fields'][3]['field_name'] = 'user_id';
$zz['fields'][3]['type'] = 'write_once';
$zz['fields'][3]['default'] = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';

$zz['fields'][4]['field_name'] = 'rev_status';
$zz['fields'][4]['type'] = 'select';
$zz['fields'][4]['enum'] = ['live','pending','historic'];
$zz['fields'][4]['show_values_as_list'] = true;

$zz['fields'][5]['field_name'] = 'created';
$zz['fields'][5]['type'] = 'write_once';
$zz['fields'][5]['type_detail'] = 'datetime';
$zz['fields'][5]['default'] = date('Y-m-d H:i:s');

include __DIR__.'/revisiondata.php';
$zz['fields'][6] = $zz_sub;
unset($zz_sub);
$zz['fields'][6]['type'] = 'subtable';
$zz['fields'][6]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][6]['subselect']['sql'] = sprintf('SELECT revision_id, table_name, record_id, rev_action
	FROM %s
	LEFT JOIN %s USING (revision_id)',
	$zz_conf['revisions_data_table'], $zz_conf['revisions_table']
);

$zz['fields'][99]['field_name'] = 'last_update';
$zz['fields'][99]['type'] = 'timestamp';
$zz['fields'][99]['class'] = 'block480';
$zz['fields'][99]['hide_in_list'] = true;

$zz['sql'] = 'SELECT * FROM '.$zz_conf['revisions_table'];
$zz['sqlorder'] = ' ORDER BY created DESC, revision_id DESC';
