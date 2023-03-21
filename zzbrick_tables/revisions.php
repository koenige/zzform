<?php 

/**
 * zzform module
 * Revisions of database records
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016, 2019, 2021, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// access restriction has to be set in the file including this file
// Bitte Zugriffsbeschränkungen in der Datei, die diese einbindet, definieren!

if (empty($zz_conf['revisions'])) wrap_quit(404);

require_once $zz_conf['dir'].'/revisions.inc.php';

$zz['title'] = 'Revisions';
$zz['table'] = '/*_PREFIX_*/_revisions';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'revision_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['title'] = 'Table';
$zz['fields'][2]['field_name'] = 'main_table_name';

$zz['fields'][3]['title'] = 'Record';
$zz['fields'][3]['field_name'] = 'main_record_id';
$zz['fields'][3]['link'] = [
	'mode1' => 'zz_revisions_table_to_url',
	'field1' => 'revisions_url',
	'string2' => '?revise=',
	'field2' => 'main_record_id',
	'string3' => '&nolist&referer='.urlencode(wrap_setting('request_uri'))
];

$zz['fields'][4]['field_name'] = 'user_id';
$zz['fields'][4]['type'] = 'write_once';
$zz['fields'][4]['default'] = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';

$zz['fields'][5]['title'] = 'Status';
$zz['fields'][5]['field_name'] = 'rev_status';
$zz['fields'][5]['type'] = 'select';
$zz['fields'][5]['enum'] = ['live', 'pending', 'historic'];
$zz['fields'][5]['show_values_as_list'] = true;

$zz['fields'][6]['field_name'] = 'created';
$zz['fields'][6]['type'] = 'write_once';
$zz['fields'][6]['type_detail'] = 'datetime';
$zz['fields'][6]['default'] = date('Y-m-d H:i:s');

$zz['fields'][7] = zzform_include_table('revisiondata');
$zz['fields'][7]['title'] = 'Data';
$zz['fields'][7]['type'] = 'subtable';
$zz['fields'][7]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][7]['subselect']['sql'] = 'SELECT revision_id, table_name, record_id, rev_action
	FROM /*_PREFIX_*/_revisiondata
	LEFT JOIN /*_PREFIX_*/_revisions USING (revision_id)';

$zz['fields'][8]['title'] = 'Script URL';
$zz['fields'][8]['field_name'] = 'script_url';
$zz['fields'][8]['hide_in_list'] = true;

$zz['fields'][99]['field_name'] = 'last_update';
$zz['fields'][99]['type'] = 'timestamp';
$zz['fields'][99]['class'] = 'block480';
$zz['fields'][99]['hide_in_list'] = true;

$zz['sql'] = 'SELECT *
	, IFNULL(script_url, main_table_name) AS revisions_url
	FROM /*_PREFIX_*/_revisions';
$zz['sqlorder'] = ' ORDER BY created ASC, revision_id ASC';

foreach ($zz['fields'][5]['enum'] as $enum) {
	$zz['filter'][1]['selection'][$enum] = $enum;
}
$zz['filter'][1]['title'] = 'Status';
$zz['filter'][1]['identifier'] = 'status';
$zz['filter'][1]['type'] = 'list';
$zz['filter'][1]['field_name'] = 'rev_status';
$zz['filter'][1]['where'] = 'rev_status';
$zz['filter'][1]['default_selection'] = 'pending';
$zz['filter'][1]['translate_field_value'] = true;

$zz_conf['no_add_above'] = true;
