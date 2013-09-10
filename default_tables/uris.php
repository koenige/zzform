<?php 

/**
 * zzform
 * Database table for list of URIs
 * DB-Tabelle für Liste von URIs
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2013 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// access restriction has to be set in the file including this file
// Bitte Zugriffsbeschränkungen in der Datei, die diese einbindet, definieren!

$zz['title'] = 'URIs';
$zz['table'] = '/*_PREFIX_*/_uris';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'uri_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['title'] = 'Scheme';
$zz['fields'][2]['field_name'] = 'uri_scheme';
//$zz['fields'][2]['list_append_next'] = true;
//$zz['fields'][2]['list_suffix'] = '://';
$zz['fields'][2]['hide_in_list'] = true;

$zz['fields'][3]['title'] = 'Host';
$zz['fields'][3]['field_name'] = 'uri_host';
//$zz['fields'][3]['list_append_next'] = true;
//$zz['fields'][3]['list_suffix'] = '<br>';

$zz['fields'][4]['title'] = 'Path';
$zz['fields'][4]['field_name'] = 'uri_path';
$zz['fields'][4]['list_append_next'] = true;

$zz['fields'][5]['title'] = 'Query';
$zz['fields'][5]['field_name'] = 'uri_query';
$zz['fields'][5]['list_prefix'] = '?';

$zz['fields'][6]['title_tab'] = 'Type';
$zz['fields'][6]['title'] = 'Content Type';
$zz['fields'][6]['field_name'] = 'content_type';
$zz['fields'][6]['list_append_next'] = true;

$zz['fields'][7]['title'] = 'Character Encoding';
$zz['fields'][7]['field_name'] = 'character_encoding';
$zz['fields'][7]['list_prefix'] = '; charset=';

$zz['fields'][8]['title'] = 'Content Length';
$zz['fields'][8]['title_tab'] = 'Length';
$zz['fields'][8]['field_name'] = 'content_length';
$zz['fields'][8]['type'] = 'number';

$zz['fields'][9]['field_name'] = 'user';
$zz['fields'][9]['hide_in_list'] = true;

$zz['fields'][10]['title_tab'] = 'Status';
$zz['fields'][10]['title'] = 'Status Code';
$zz['fields'][10]['field_name'] = 'status_code';
$zz['fields'][10]['type'] = 'number';
$zz['fields'][10]['hide_in_list'] = true;
/*
$zz['fields'][10]['type'] = 'select';
$zz['fields'][10]['default'] = 301;
$zz['fields'][10]['enum'] = array(301, 303, 307, 403, 410);
$zz['fields'][10]['enum_abbr'] = array(wrap_text('Moved Permanently'), 
	wrap_text('See Other'), wrap_text('Temporary Redirect'), 
	wrap_text('Forbidden'), wrap_text('Gone'));
*/

$zz['fields'][11]['title'] = 'ETag (MD5)';
$zz['fields'][11]['field_name'] = 'etag_md5';
$zz['fields'][11]['hide_in_list'] = true;

$zz['fields'][13]['field_name'] = 'hits';
$zz['fields'][13]['type'] = 'number';

$zz['fields'][12]['title'] = 'Last Modified';
$zz['fields'][12]['field_name'] = 'last_modified';
$zz['fields'][12]['hide_in_list'] = true;
$zz['fields'][12]['type'] = 'datetime';

$zz['fields'][14]['title'] = 'First Access';
$zz['fields'][14]['field_name'] = 'first_access';
$zz['fields'][14]['type'] = 'datetime';
$zz['fields'][14]['hide_in_list'] = true;

$zz['fields'][15]['title'] = 'Last Access';
$zz['fields'][15]['field_name'] = 'last_access';
$zz['fields'][15]['type'] = 'datetime';

$zz['fields'][20]['field_name'] = 'last_update';
$zz['fields'][20]['type'] = 'timestamp';
$zz['fields'][20]['hide_in_list'] = true;


$zz['sql'] = 'SELECT * 
	FROM /*_PREFIX_*/_uris';
$zz['sqlorder'] = ' ORDER BY uri_host, uri_path, uri_query';

$zz['list']['group'] = 'uri_host';

$zz_conf['multilang_fieldnames'] = true;

$zz_conf['filter'][1]['title'] = wrap_text('Status');
$zz_conf['filter'][1]['identifier'] = 'status';
$zz_conf['filter'][1]['type'] = 'list';
$zz_conf['filter'][1]['where'] = 'status_code';
$zz_conf['filter'][1]['field_name'] = 'status_code';
$zz_conf['filter'][1]['sql'] = 'SELECT DISTINCT status_code, status_code
	FROM /*_PREFIX_*/_uris';

?>