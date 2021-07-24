<?php 

/**
 * zzform module
 * Revisions of database records
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016, 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// access restriction has to be set in the file including this file
// Bitte Zugriffsbeschränkungen in der Datei, die diese einbindet, definieren!

if (empty($zz_conf['revisions'])) wrap_quit(404);

$zz_sub['title'] = 'Revisiondata';
$zz_sub['table'] = '/*_PREFIX_*/_revisiondata';

$zz_sub['fields'][1]['title'] = 'ID';
$zz_sub['fields'][1]['field_name'] = 'revisiondata_id';
$zz_sub['fields'][1]['type'] = 'id';

$zz_sub['fields'][2]['field_name'] = 'revision_id';

$zz_sub['fields'][3]['field_name'] = 'table_name';

$zz_sub['fields'][4]['field_name'] = 'record_id';

$zz_sub['fields'][5]['field_name'] = 'changed_values';
$zz_sub['fields'][5]['type'] = 'memo';
$zz_sub['fields'][5]['rows'] = 3;

$zz_sub['fields'][6]['field_name'] = 'complete_values';
$zz_sub['fields'][6]['type'] = 'memo';
$zz_sub['fields'][6]['hide_in_list'] = true;
$zz_sub['fields'][6]['rows'] = 3;

$zz_sub['fields'][7]['field_name'] = 'rev_action';
$zz_sub['fields'][7]['type'] = 'select';
$zz_sub['fields'][7]['enum'] = ['insert','update','delete'];
$zz_sub['fields'][7]['show_values_as_list'] = true;

$zz_sub['sql'] = 'SELECT _revisiondata.*
		, _revisions.revision_id, _revisions.created
	FROM /*_PREFIX_*/_revisiondata _revisiondata
	LEFT JOIN /*_PREFIX_*/_revisions _revisions USING (revision_id)';
$zz_sub['sqlorder'] = ' ORDER BY created DESC, _revisiondata.revision_id DESC';
