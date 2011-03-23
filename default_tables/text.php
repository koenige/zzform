<?php 

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2009-2010
// Database table for translations of text blocks
// DB-Tabelle zur Eingabe von Uebersetzungen von Textbloecken


// access restriction has to be set in the file including this file
// Bitte Zugriffsbeschränkungen in der Datei, die diese einbindet, definieren!

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

$zz['fields'][20]['title'] = 'Last Update';
$zz['fields'][20]['field_name'] = 'last_update';
$zz['fields'][20]['type'] = 'timestamp';
$zz['fields'][20]['hide_in_list'] = true;

	
$zz['sql'] = 'SELECT * FROM '.$zz_conf['text_table'].'
	ORDER BY text';

$zz_conf['heading'] = 'Text';
$zz_conf['delete'] = true;

?>