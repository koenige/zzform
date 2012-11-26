<?php 

/**
 * zzform
 * Database table for redirects of URLs
 * DB-Tabelle zur Eingabe von Umleitungen von URLs
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2006-2012 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// access restriction has to be set in the file including this file
// Bitte Zugriffsbeschränkungen in der Datei, die diese einbindet, definieren!

$zz['title'] = 'Redirects';
$zz['explanation'] = '%%% text Information about redirects %%%';
$zz['table'] = '/*_PREFIX_*/redirects';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'redirect_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['title'] = 'Old URL';
$zz['fields'][2]['field_name'] = 'old_url';
$zz['fields'][2]['type'] = 'text';

$zz['fields'][3]['title'] = 'New URL';
$zz['fields'][3]['field_name'] = 'new_url';
$zz['fields'][3]['type'] = 'text';

$zz['fields'][4]['field_name'] = 'code';
$zz['fields'][4]['type'] = 'select';
$zz['fields'][4]['default'] = 301;
$zz['fields'][4]['enum'] = array(301, 303, 307, 403, 410);
$zz['fields'][4]['enum_abbr'] = array(wrap_text('Moved Permanently'), 
	wrap_text('See Other'), wrap_text('Temporary Redirect'), 
	wrap_text('Forbidden'), wrap_text('Gone'));

$zz['fields'][5]['field_name'] = 'area';

$zz['fields'][20]['field_name'] = 'last_update';
$zz['fields'][20]['type'] = 'timestamp';
$zz['fields'][20]['hide_in_list'] = true;

$zz['sql'] = 'SELECT * 
	FROM /*_PREFIX_*/redirects';
$zz['sqlorder'] = ' ORDER BY old_url, new_url';

$zz['list']['group'] = 'area';

$zz_conf['multilang_fieldnames'] = true;

?>