<?php

/**
 * zzform
 * Synchronisation functions
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2011-2016 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Sync some data with other database content
 *
 * @param array $import
 *		int		'limit'
 *		int		'end'
 *		string	'type' (csv, sql)
 * @global array $zz_setting
 *		int		'sync_records_per_run'
 *		int		'sync_page_refresh'
 *		string	'sync_lists_dir'
 * @global array $zz_page		'url'['full']['path']
 * @return array $page
 */
function zz_sync($import) {
	global $zz_setting;
	global $zz_page;
	
	$refresh = false;
	
	// set defaults global
	if (!isset($zz_setting['sync_records_per_run']))
		$zz_setting['sync_records_per_run'] = 1000;
	if (!isset($zz_setting['sync_page_refresh']))
		$zz_setting['sync_page_refresh'] = 2;
	if (!isset($zz_setting['sync_lists_dir']))
		$zz_setting['sync_lists_dir'] = $zz_setting['media_folder'];

	// limits
	if (empty($_GET['limit'])) $import['limit'] = 0;
	else $import['limit'] = intval($_GET['limit']);
	$import['end'] = $import['limit'] + $zz_setting['sync_records_per_run'];

	$import_types = array('csv', 'sql');
	if (empty($import['type']) OR !in_array($import['type'], $import_types)) {
		wrap_error(sprintf(
			'Please set an import type via $import["type"]. Possible types are: %s',
			implode(', ', $import_types)
		), E_USER_ERROR);
	}

	switch ($import['type']) {
	case 'csv':
		// get source file
		if (empty($import['filename'])) {
			wrap_error('Please set an import filename via $import["filename"].', E_USER_ERROR);
		}
		$import['source'] = $zz_setting['sync_lists_dir'].'/'.$import['filename'];
		if (!file_exists($import['source'])) {
			$page['text'] = sprintf(wrap_text('Import: File %s does not exist. '
				.'Please set a different filename'), $import['source']);
			return $page;
		}
		// set defaults per file
		if (!isset($import['comments']))
			$import['comments'] = '#';
		if (!isset($import['enclosure']))
			$import['enclosure'] = '"';
		if (!isset($import['delimiter']))
			$import['delimiter'] = ',';
		if (!isset($import['first_line_headers']))
			$import['first_line_headers'] = true;
		if (!isset($import['static']))
			$import['static'] = array();
		if (!isset($import['key_concat']))
			$import['key_concat'] = false;
		$raw = zz_sync_csv($import);
		if (count($raw) === $zz_setting['sync_records_per_run']) {
			$refresh = true;
		}
		break;
	case 'sql':
		$raw = wrap_db_fetch($import['import_sql'], $import['import_id_field_name']);
		foreach ($raw as $id => $line) {
			// we need fields as numeric values
			unset($raw[$id]);
			foreach ($line as $value) {
				$raw[$id][] = $value;
			}
		}
		break;
	default:
		wrap_error('Please set an import type via <code>$import["type"]</code>.', E_USER_ERROR);
	}

	// sync data
	list($updated, $inserted, $nothing, $errors, $testing) = zz_sync_zzform($raw, $import);

	// output results
	$lines = array();
	$lines[] = sprintf(wrap_text('Processing entries %s&#8211;%s &hellip;'), $import['limit'] + 1, $import['end']);
	if ($updated) {
		if ($updated === 1) {
			$lines[] = wrap_text('1 update was made.');
		} else {
			$lines[] = sprintf(wrap_text('%s updates were made.'), $updated);
		}
	}
	if ($inserted) {
		if ($inserted === 1) {
			$lines[] = wrap_text('1 insert was made.');
		} else {
			$lines[] = sprintf(wrap_text('%s inserts were made.'), $inserted);
		}
	}
	if ($nothing) {
		if ($nothing === 1) {
			$lines[] = wrap_text('1 record was left as is.');
		} else {
			$lines[] = sprintf(wrap_text('%s records were left as is.'), $nothing);
		}
	}
	if ($errors) {
		if (count($errors) == 1) {
			$lines[] = sprintf(wrap_text('1 record had errors. (%s)'), implode(', ', $errors));
		} else {
			$lines[] = sprintf(wrap_text('%s records had errors.'), count($errors))
				."<ul><li>\n".implode("</li>\n<li>", $errors)."</li>\n</ul>\n";
		}
	}
	if ($testing) {
		$lines[] = wrap_print($testing);
	}
	if ($refresh)
		$lines[] = wrap_text('Please wait for reload &hellip;');
	else
		$lines[] = wrap_text('Finished!');

	if (!$lines) {
		$page['text'] = wrap_text('No updates/inserts were made.');
		return $page;
	}

	$page['query_strings'] = array('limit');
	$page['text'] = implode('<br>', $lines);
	if ($refresh) {
		$page['head'] = sprintf("\t".'<meta http-equiv="refresh" content="%s; URL=%s?limit=%s">'."\n",
			$zz_setting['sync_page_refresh'], 
			$zz_setting['host_base'].$zz_page['url']['full']['path'], $import['end']);
	}
	return $page;
}

/**
 * Sync data from CSV file with database content
 *
 * @param array $import
 *		string	'source' = local filename of import file
 *		string	'delimiter' = delimiter of fields
 *		string	'enclosure' = enclosure of field value
 *		int		'key' = row with unique key (0...n)
 *		string	'comments' = character that marks commenting lines
 * @return array $raw
 */
function zz_sync_csv($import) {
	// open CSV file
	$i = 0;
	$first = false;
	$handle = fopen($import['source'], "r");

	if (!isset($import['key'])) {
		wrap_error('Please set one or more fields as key fields in $import["key"].', E_USER_ERROR);
	}

	while (!feof($handle)) {
		$line = fgetcsv($handle, 8192, $import['delimiter'], $import['enclosure']);
		$line_complete = $line;
		// ignore empty lines
		if (!$line) continue;
		if (!trim(implode('', $line))) continue;
		// ignore comments
		if ($import['comments']) {
			if (substr($line[0], 0, 1) == $import['comments']) continue;
		}
		// ignore first line = field names
		if ($import['first_line_headers'] AND !$i AND !$first) {
			$first = true;
			continue;
		}
		// start counting lines
		$i++;
		// ignore lines that were already processed
		if ($i <= $import['limit']) continue;
		// do not import some fields which should be ignored
		if (!empty($import['ignore_fields'])) {
			foreach ($import['ignore_fields'] as $no) unset($line[$no]);
		}
		// save lines in $raw
		foreach (array_keys($line) AS $id) {
			$line[$id] = trim($line[$id]);
			if (empty($line[$id]) AND isset($import['empty_fields_use_instead'][$id])) {
				$line[$id] = trim($line_complete[$import['empty_fields_use_instead'][$id]]);
			}
		}
		if (is_array($import['key'])) {
			$key = array();
			foreach ($import['key'] AS $no) {
				if (!isset($line[$no])) {
					wrap_error(sprintf(
						'New record has not enough values for the key. (%d expected, record looks as follows: %s)',
						count($line), implode(' -- ', $line)
					), E_USER_ERROR);
				}
				$key[] = $line[$no];
			}
			$key = implode($import['key_concat'], $key);
		} else {
			$key = $line[$import['key']];
		}
		$key = trim($key);
		$raw[$key] = $line;
		if (count($raw) === ($import['end'] - $import['limit'])) break;
	}
	fclose($handle);
	if (empty($raw)) return array();
	return $raw;
}


/**
 * Sync of raw data to import with existing data, updates or inserts raw data
 * as required
 *
 * @param array $raw raw data, indexed by identifier
 * @param array $import import settings
 *		string	'existing_sql' = SQL query to get pairs of identifier/IDs
 *		array 	'fields' = list of fields, indexed by position
 *		array 	'static' = values for fields, indexed by field name
 *		string	'id_field_name' = field name of PRIMARY KEY of database table
 *		string	'form_script' = table script for sync
 *		array	'ignore_if_null' = list of field nos which will be ignored if
 *				no value is set
 * @global array $zz_conf string 'dir'
 * @return array $updated, $inserted, $nothing = count of records, $errors,
 *		$testing
 */
function zz_sync_zzform($raw, $import) {
	global $zz_conf;
	// include form scripts
	require_once $zz_conf['dir'].'/zzform.php';

	if (empty($import['existing_sql'])) {
		wrap_error('Please define a query for the existing records in the database with $import["existing_sql"].', E_USER_ERROR);
	}
	if (empty($import['fields'])) {
		wrap_error('Please set which fields should be imported in $import["fields"].', E_USER_ERROR);	
	}
	if (empty($import['form_script'])) {
		wrap_error('Please tell us the name of the form script in $import["form_script"].', E_USER_ERROR);	
	}
	if (empty($import['id_field_name'])) {
		wrap_error('Please set the id field name of the table in $import["id_field_name"].', E_USER_ERROR);	
	}
	if (empty($import['ignore_if_null']))
		$import['ignore_if_null'] = array();

	$updated = 0;
	$inserted = 0;
	$nothing = 0;
	$errors = array();
	$testing = array();

	// get existing keys from database
	$keys = array_keys($raw);
	foreach ($keys as $id => $key) $keys[$id] = wrap_db_escape($key);
	$keys = '"'.implode('", "', $keys).'"';
	$sql = sprintf($import['existing_sql'], $keys);
	$ids = wrap_db_fetch($sql, '_dummy_', 'key/value');

	foreach ($raw as $identifier => $line) {
		$values = array();
		$values['POST'] = array();
		if (count($line) > count($import['fields'])) {
			// remove whitespace only fields at the end of the line
			do {
				$last = array_pop($line);
			} while (!$last AND count($line) >= count($import['fields']));
			$line[] = $last;
		}
		if (count($line) != count($import['fields'])) {
			$error_line = array();
			foreach ($import['fields'] as $pos => $field_name) {
				if (!isset($line[$pos])) {
					$error_line[$field_name] = '<strong>=>||| '.wrap_text('not set').' |||<=</strong>';
				} else {
					$error_line[$field_name] = $line[$pos];
				}
			}
			if (count($line) > count($import['fields'])) {
				$error_msg = 'too many values:';
			} else {
				$error_msg = 'not enough values:';
			}
			$errors = array_merge($errors, array($error_msg.' '
				.wrap_print($error_line).wrap_print($line)));
			continue;
		}
		foreach ($import['fields'] as $pos => $field_name) {
			// don't delete field values if ignore_if_null is set
			if (in_array($pos, $import['ignore_if_null'])
				AND empty($line[$pos]) AND $line[$pos] !== 0 AND $line[$pos] !== '0') continue;
			// do nothing if value is NULL
			if (!isset($line[$pos])) continue;
			$values['POST'] = zz_sync_values($values['POST'], $field_name, trim($line[$pos]));
		}
		// static values to import
		foreach ($import['static'] as $field_name => $value) {
			$values['POST'] = zz_sync_values($values['POST'], $field_name, $value);
		}
		if (!empty($ids[$identifier])) {
			$values['action'] = 'update';
			$values['GET']['where'][$import['id_field_name']] = $ids[$identifier];
		} else {
			$values['action'] = 'insert';
		}
		if (!empty($import['testing'])) {
			$nothing++;
			$testing[] = $values;
			continue;
		}
		$ops = zzform_multi($import['form_script'], $values);
		if ($ops['id']) {
			$ids[$identifier] = $ops['id'];
		}
		if ($ops['result'] === 'successful_insert') {
			$inserted++;
		} elseif ($ops['result'] === 'successful_update') {
			$updated++;
		} elseif (!$ops['id']) {
			if ($ops['error']) {
				foreach ($ops['error'] as $error) {
					$errors[] = sprintf('Record "%s": ', $identifier).$error;
				}
			} else {
				$errors[] = 'Unknown error: '.$ops['output'];
			}
		} else {
			$nothing++;
		}
	}
	return array($updated, $inserted, $nothing, $errors, $testing);
}

/**
 * reformat hierarchical field names to array
 * table_name[0][field_name]
 *
 * @param array $post
 * @param string $field_name
 * @param string $value
 * @return array
 */
function zz_sync_values($post, $field_name, $value) {
	if (strstr($field_name, '[')) {
		$fields = explode('[', $field_name);
		foreach ($fields as $index => $field) {
			if (!$index) continue;
			$fields[$index] = trim($field, ']');
		}
		if (count($fields === 3)) {
			$post[$fields[0]][$fields[1]][$fields[2]] = $value;
		}
	} else {
		$post[$field_name] = $value;
	}
	return $post;
}
