<?php

/**
 * zzform
 * Synchronisation functions
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2011-2018 Gustaf Mossakowski
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
	global $zz_conf;
	require_once $zz_conf['dir'].'/functions.inc.php';

	$post = isset($_POST['action']) ? true : false;	// will be overwritten
	$refresh = false;
	
	// set defaults global
	if (!isset($zz_setting['sync_records_per_run'])) {
		if (!empty($import['testing'])) {
			$zz_setting['sync_records_per_run'] = 100;
		} else {
			$zz_setting['sync_records_per_run'] = 1000;
		}
	}
	if (!isset($zz_setting['sync_page_refresh']))
		$zz_setting['sync_page_refresh'] = 2;
	if (!isset($zz_setting['sync_lists_dir']))
		$zz_setting['sync_lists_dir'] = $zz_setting['media_folder'];
	if (!isset($import['show_but_no_import']))
		$import['show_but_no_import'] = [];
	if (!isset($import['deletable_script_url']))
		$import['deletable_script_url'] = [];

	// limits
	if (empty($_GET['limit'])) $import['limit'] = 0;
	else $import['limit'] = zz_check_get_array('limit', 'is_int');
	$import['end'] = $import['limit'] + $zz_setting['sync_records_per_run'];

	$import_types = ['csv', 'sql'];
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
		if (!isset($import['ignore_head_lines']))
			$import['ignore_head_lines'] = 0;
		if (!isset($import['static']))
			$import['static'] = [];
		if (!isset($import['key_concat']))
			$import['key_concat'] = false;
		if (isset($_GET['deletable'])) break;
		list($raw, $i) = zz_sync_csv($import);
		if ($i === $import['end']) {
			$refresh = true;
		}
		break;
	case 'sql':
		if (isset($_GET['deletable'])) break;
		$limit = $import['end'] - $import['limit'];
		$sql = $import['import_sql'];
		$sql .= sprintf(' LIMIT %d, %d', $import['limit'], $limit);
		$raw = wrap_db_fetch($sql, $import['import_id_field_name']);
		if (count($raw) === $limit) {
			$refresh = true;
		}
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

	if (isset($_GET['deletable']) AND !empty($import['deletable_sql'])) {
		return zz_sync_deletable($import);
	}
	
	if (empty($raw)) {
		$page['query_string'] = 'limit';
		$page['status'] = 404;
		$page['text'] = '';
		return $page;
	}

	// sync data
	list($updated, $inserted, $nothing, $errors, $testing) = zz_sync_zzform($raw, $import);

	// output results
	$lines = [];
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
	if ($nothing AND (!$testing OR $post)) {
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
		if (!$post) {
			$lines[] = zz_sync_list($testing, $import);
		} elseif ($refresh) {
			$lines[] = sprintf('<a href="?limit=%s">Go on to next page</a>', $import['end']);
		} else {
			$lines[] = '<a href="?deletable">Possibly deletable records</a>';
		}
		$refresh = false;
	} elseif ($refresh) {
		$lines[] = wrap_text('Please wait for reload &hellip;');
	} else {
		$lines[] = wrap_text('Finished!');
	}

	if (!$lines) {
		$page['text'] = wrap_text('No updates/inserts were made.');
		return $page;
	}

	$zz_setting['extra_http_headers'][] = 'X-Frame-Options: Deny';
	$zz_setting['extra_http_headers'][] = "Content-Security-Policy: frame-ancestors 'self'";

	if ($testing) {
		$page['head'] = wrap_template('zzform-head');
	}
	$page['query_strings'] = ['limit'];
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

	$processed = 0;
	while (!feof($handle)) {
		$line = fgetcsv($handle, 8192, $import['delimiter'], $import['enclosure']);
		$line_complete = $line;
		// ignore empty lines
		if (!$line) continue;
		if (!trim(implode('', $line))) continue;
		// ignore comments
		if ($import['ignore_head_lines']) {
			$import['ignore_head_lines']--;
			continue;
		}
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
			$key = [];
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
		$processed++; // does not necessarily correspond with count($raw)
		if ($processed === ($import['end'] - $import['limit'])) break;
	}
	fclose($handle);
	if (empty($raw)) return [[], $i];
	return [$raw, $i];
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
		$import['ignore_if_null'] = [];

	$updated = 0;
	$inserted = 0;
	$nothing = 0;
	$errors = [];
	$testing = [];

	// get existing keys from database
	$keys = array_keys($raw);
	foreach ($keys as $id => $key) $keys[$id] = wrap_db_escape($key);
	$keys = '"'.implode('", "', $keys).'"';
	$sql = sprintf($import['existing_sql'], $keys);
	$ids = wrap_db_fetch($sql, '_dummy_', 'key/value');

	$action_ids[] = [];
	if (!empty($import['testing']) AND !empty($_POST['action'])) {
		foreach ($_POST['action'] as $index => $value) {
			if ($value !== 'on') continue;
			$action_ids[] = $index;
		}
	}

	foreach ($raw as $identifier => $line) {
		$values = [];
		$values['POST'] = [];
		if (count($line) > count($import['fields'])) {
			// remove whitespace only fields at the end of the line
			do {
				$last = array_pop($line);
			} while (!$last AND count($line) >= count($import['fields']));
			$line[] = $last;
		}
		if (count($line) != count($import['fields'])) {
			$error_line = [];
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
			$errors = array_merge(
				$errors, [$error_msg.' '.wrap_print($error_line).wrap_print($line)]
			);
			continue;
		}
		foreach ($import['fields'] as $pos => $field_name) {
			// don't delete field values if ignore_if_null is set
			if (in_array($pos, $import['ignore_if_null'])
				AND empty($line[$pos]) AND $line[$pos] !== 0 AND $line[$pos] !== '0') continue;
			// do nothing if value is NULL
			if (!isset($line[$pos])) continue;
			$fields = [];
			if (!empty($import['function'][$pos])) {
				$fields[$field_name] = $import['function'][$pos]($line[$pos]);
			} elseif (strstr($field_name, '+') AND !empty($import['split_function'][$pos])) {
				// @todo error handling
				$field_names = explode('+', $field_name);
				$my_values = $import['split_function'][$pos](trim($line[$pos]));
				foreach ($field_names as $field_name) {
					$fields[$field_name] = array_shift($my_values);
				}
			} else {
				$fields[$field_name] = trim($line[$pos]);
			}
			foreach ($fields as $field_name => $value) {
				$head[$field_name] = $field_name;
				$testing[$identifier][$field_name] = $value;
				if (!in_array($pos, $import['show_but_no_import'])) {
					$values['POST'] = zz_check_values($values['POST'], $field_name, $value);
				}
			}
		}
		// static values to import
		foreach ($import['static'] as $field_name => $value) {
			$head[$field_name] = $field_name;
			$testing[$identifier][$field_name] = $value;
			$values['POST'] = zz_check_values($values['POST'], $field_name, $value);
		}
		if (!empty($ids[$identifier])) {
			$testing[$identifier]['_action'] = $values['action'] = 'update';
			$values['GET']['where'][$import['id_field_name']] = $ids[$identifier];
			$testing[$identifier]['_id'] = $ids[$identifier];
		} else {
			$testing[$identifier]['_action'] = $values['action'] = 'insert';
			$testing[$identifier]['_insert'] = true;
		}
		if (!empty($import['testing'])) {
			if (false === array_search($identifier, $action_ids)) {
				$nothing++;
				continue;
			}
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
	$testing['head'] = $head;
	return [$updated, $inserted, $nothing, $errors, $testing];
}

/**
 * display records to import
 *
 * @param array $testing
 * @param array $import
 * @return string
 */
function zz_sync_list($testing, $import) {
	global $zz_setting;
	global $zz_conf;

	// get head
	$def = zz_sync_formdef($import['form_script'], $zz_setting, $zz_conf);
	$head = zz_sync_fields($def['fields'], $testing['head']);
	$field_names = [];
	$foreign_keys = [];
	$foreign_keys[$def['table']] = $def['fields'][1]['field_name'];
	$id_fields = [];
	$id_fields[$def['table']] = $def['fields'][1]['field_name'];
	foreach ($head as $field) {
		if (empty($field['field_name'])) continue;
		$table = !empty($field['table']) ? $field['table'] : $def['table'];
		$field_names[$table][] = $field['field_name'];
		if (!empty($field['foreign_key'])) {
			$foreign_keys[$table] = $field['foreign_key'];
		}
		if (!empty($field['id_field'])) {
			$id_fields[$table] = $field['id_field'];
		}
	}
	unset($testing['head']);

	// get values
	$ids = [];
	foreach ($testing as $index => $line) {
		foreach ($line as $key => $value) {
			if ($key === '_id') $ids[] = $value;
		}
	}
		
	// get existing records
	foreach ($field_names as $table => $fnames) {
		if (!in_array($foreign_keys[$table], $fnames)) {
			array_unshift($fnames, $foreign_keys[$table]);
		}
		if (!in_array($id_fields[$table], $fnames)) {
			array_unshift($fnames, $id_fields[$table]);
		}
		$sql = 'SELECT %s
			FROM %s
			WHERE %s IN (%s)';
		$sql = sprintf($sql,
			implode(', ', $fnames),
			$table,
			$def['fields'][1]['field_name'],
			implode(',', $ids)
		);
		if ($id_fields[$table] === $foreign_keys[$table]) {
			$existing[$table] = wrap_db_fetch($sql, $id_fields[$table]);
		} else {
			$existing[$table] = wrap_db_fetch($sql, [$foreign_keys[$table], $id_fields[$table]], 'numeric');
		}
	}

	$j = intval($import['limit']);
	foreach ($testing as $index => $line) {
		$j++;
		foreach (array_keys($head) as $num) {
			if (substr($num, 0, 1) === '_') continue;
			$testing[$index]['fields'][$num]['value'] = ''; 
		}
		foreach ($line as $key => $value) {
			if (substr($key, 0, 1) === '_') continue;
			$num = $head['_mapping'][$key];
			if (strstr($num, '-')) {
				$row_index = substr($key, strpos($key, '[') + 1, strpos($key, ']') - strpos($key, '[') - 1);
				$testing[$index]['fields'][$num]['values'][$row_index]['value'] = $value;
				$table = $head[$num]['table'];
				$fname = substr($key, strrpos($key, '[') + 1, -1);
				if (isset($line['_id']) AND !empty($existing[$table][$line['_id']][$row_index][$fname])) {
					$evalue = $existing[$table][$line['_id']][$row_index][$fname];
					$testing[$index]['fields'][$num]['values'][$row_index]['existing'] = $evalue;
					if ($evalue === trim($value)) {
						$testing[$index]['fields'][$num]['values'][$row_index]['identical'] = true;
					}
				}
			} else {
				$testing[$index]['fields'][$num]['value'] = $value;
				$table = $def['table'];
				$fname = $key;
				if (isset($line['_id']) AND !empty($existing[$table][$line['_id']][$fname])) {
					$evalue = $existing[$table][$line['_id']][$fname];
					$testing[$index]['fields'][$num]['existing'] = $evalue;
					if ($evalue === trim($value)) {
						$testing[$index]['fields'][$num]['identical'] = true;
					}
				}
			}
			unset($testing[$index][$key]);
		}
		$testing[$index]['no'] = $j;
		$testing[$index]['index'] = $index;
		$testing[$index]['script_url'] = isset($import['script_url']) ? $import['script_url'] : '';
	}
	
	$testing = array_values($testing);
	foreach ($head as $num => $field) {
		if (substr($num, 0, 1) === '_') continue;
		$testing['head'][$num]['field_name'] = '';
		if (isset($field['table_title'])) {
			$testing['head'][$num]['field_name'] .= $field['table_title'].'<br>';
		}
		$testing['head'][$num]['field_name'] .= isset($field['title']) ? $field['title'] : $field['field_name'];
	}

	$text = wrap_template('sync', $testing);
	return $text;
}

/**
 * get list of fields from zzform definition
 *
 * @param string $form_script
 * @param array $zz_setting (not to be changed, therefore not global here)
 * @param array $zz_conf (not to be changed, therefore not global here)
 * @return array $zz
 */
function zz_sync_formdef($form_script, $zz_setting, $zz_conf) {
	$file = zzform_file($form_script);
	require $file['tables'];
	if (empty($zz)) {
		return $zz_sub;
	} else {
		return $zz;
	}
}

/**
 * get fields out of zzform definition that are displayed in sync table
 *
 * @param array $fields = $zz['fields']
 * @param array $old_head = $testing['head'], list of field names
 * 		or subtable + index + field_name
 * @return array $head
 */
function zz_sync_fields($fields, $old_head) {
	$head = [];
	foreach ($fields as $no => $field) {
		if (!empty($field['field_name']) AND in_array($field['field_name'], $old_head)) {
			$head[$no] = $field;
			$head['_mapping'][$field['field_name']] = $no;
		} elseif (!empty($field['type']) AND $field['type'] === 'subtable') {
			// @todo write zzform function that puts all definitions in table
			// like fill out
			$table_name = isset($field['table_name']) ? $field['table_name'] : $field['table'];
			$foreign_key = false;
			foreach ($field['fields'] as $subno => $subfield) {
				if (!empty($subfield['type'])) {
					switch ($subfield['type']) {
					case 'foreign_key':
						$foreign_key = $subfield['field_name'];
						break;
					case 'id':
						$id_field_name = $subfield['field_name'];
						break;
					}
				}
				$field_name = sprintf('%s[%%d][%s]', $table_name, $subfield['field_name']);
				$first_row = sprintf($field_name, 0);
				if (!in_array($first_row, $old_head)) continue;
				$head[$no.'-'.$subno] = $subfield;
				$head[$no.'-'.$subno]['table_title'] = isset($field['title']) ? $field['title'] : ucfirst($field['table']);
				$head[$no.'-'.$subno]['table'] = $field['table'];
				$head[$no.'-'.$subno]['id_field'] = $id_field_name;
				$head[$no.'-'.$subno]['foreign_key'] = $foreign_key;
				$head['_mapping'][$first_row] = $no.'-'.$subno;
				$i = 1;
				while (true) {
					$row = sprintf($field_name, $i);
					if (!in_array($row, $old_head)) break;
					$head['_mapping'][$row] = $no.'-'.$subno;
					$i++;
				}
			}
		}
	}
	return $head;
}

/**
 * show deletable records
 *
 * @param array $import
 * @return array
 */
function zz_sync_deletable($import) {
	global $zz_setting;

	switch ($import['type']) {
	case 'csv':
		$import['end'] = 0;
		list($raw, $i) = zz_sync_csv($import);
		break;
	case 'sql':
		$raw = wrap_db_fetch($import['import_sql'], $import['import_id_field_name']);
		break;
	}
	$keys = array_keys($raw);
	foreach ($keys as $index => $key) {
		$keys[$index] = wrap_db_escape($key);
	}
	$sql = sprintf($import['deletable_sql'], '"'.implode('","', $keys).'"');
	$existing = wrap_db_fetch($sql, '_dummy_', 'numeric');

	$j = 0;
	foreach ($existing as $index => $record) {
		$i = 0;
		$j++;
		foreach ($record as $field_name => $value) {
			if ($field_name === $import['id_field_name']) {
				$data[$index]['_id'] = $value;
			} else {
				$data['head'][$field_name]['field_name'] = $field_name;
				$data[$index]['fields'][$i]['value'] = $value;
				if (in_array($field_name, array_keys($import['deletable_script_url']))) {
					$data[$index]['fields'][$i]['my_script_url'] = $import['deletable_script_url'][$field_name];
				}
				$i++;
			}
		}
		$data[$index]['no'] = $j;
		$data[$index]['script_url'] = isset($import['script_url']) ? $import['script_url'] : '';
	}
	$data['head'] = array_values($data['head']);

	$zz_setting['extra_http_headers'][] = 'X-Frame-Options: Deny';
	$zz_setting['extra_http_headers'][] = "Content-Security-Policy: frame-ancestors 'self'";

	$page['query_strings'] = ['deletable'];
	$page['text'] = wrap_template('sync-deletable', $data);
	$page['head'] = wrap_template('zzform-head');
	$page['title'] = 'Deletable records';
	return $page;
}
