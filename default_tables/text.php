<?php 

/**
 * zzform
 * Database table for translations of text blocks
 * DB-Tabelle zur Eingabe von Uebersetzungen von Textbloecken
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2010 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// access restriction has to be set in the file including this file
// Bitte Zugriffsbeschränkungen in der Datei, die diese einbindet, definieren!

$zz['title'] = 'Text';
$zz['table'] = $zz_conf['text_table'];

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'text_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'text';
$zz['fields'][2]['type'] = 'write_once';
$zz['fields'][2]['type_detail'] = 'text';
$zz['fields'][2]['translation']['hide_in_list'] = false;

$zz['fields'][3]['title'] = 'More Text';
$zz['fields'][3]['field_name'] = 'more_text';
$zz['fields'][3]['type'] = 'memo';

$zz['fields'][4]['field_name'] = 'area';

$zz['fields'][20]['title'] = 'Last Update';
$zz['fields'][20]['field_name'] = 'last_update';
$zz['fields'][20]['type'] = 'timestamp';
$zz['fields'][20]['hide_in_list'] = true;

	
$zz['sql'] = 'SELECT * FROM '.$zz_conf['text_table'].'
	ORDER BY area, text';

$zz['list']['group'] = 'area';

$zz_conf['delete'] = true;

?>