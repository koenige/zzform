<?php 

/**
 * zzform module
 * Revisions of database records
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016, 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Revisiondata';
$zz['table'] = '/*_PREFIX_*/_revisiondata';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'revisiondata_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'revision_id';

$zz['fields'][3]['title'] = 'Table';
$zz['fields'][3]['field_name'] = 'table_name';

$zz['fields'][4]['field_name'] = 'record_id';

$zz['fields'][5]['field_name'] = 'changed_values';
$zz['fields'][5]['type'] = 'memo';
$zz['fields'][5]['rows'] = 3;

$zz['fields'][6]['field_name'] = 'complete_values';
$zz['fields'][6]['type'] = 'memo';
$zz['fields'][6]['hide_in_list'] = true;
$zz['fields'][6]['rows'] = 3;

$zz['fields'][7]['title'] = 'Action';
$zz['fields'][7]['field_name'] = 'rev_action';
$zz['fields'][7]['type'] = 'select';
$zz['fields'][7]['enum'] = ['insert', 'update', 'delete', 'ignore'];
$zz['fields'][7]['show_values_as_list'] = true;

$zz['sql'] = 'SELECT _revisiondata.*
		, _revisions.revision_id, _revisions.created
	FROM /*_PREFIX_*/_revisiondata _revisiondata
	LEFT JOIN /*_PREFIX_*/_revisions _revisions USING (revision_id)';
$zz['sqlorder'] = ' ORDER BY created DESC, _revisiondata.revision_id DESC';
