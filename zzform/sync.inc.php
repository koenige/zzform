<?php

/**
 * zzform
 * Synchronisation functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2011-2018, 2021-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Sync some data with other database content
 *
 * @param array $import
 *		int		'limit'
 *		int		'end'
 *		string	'type' (csv, sql)
 * @global array $zz_page		'url'['full']['path']
 * @return array $page
 */
function zz_sync($import) {
	global $zz_page;
	require_once __DIR__.'/zzform.php';
	
	$post = isset($_POST['action']) ? true : false;	// will be overwritten
	$refresh = false;
	
	// set defaults global
	if (!isset($import['show_but_no_import']))
		$import['show_but_no_import'] = [];
	if (!isset($import['deletable_script_url']))
		$import['deletable_script_url'] = [];

	// limits
	if (empty($_GET['limit'])) $import['limit'] = 0;
	else $import['limit'] = zz_check_get_array('limit', 'is_int');
	$import['end'] = $import['limit'] + wrap_setting('sync_records_per_run') * ($import['testing'] ? 1 : 10);

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
		$import['source'] = wrap_setting('cms_dir').'/_sync/'.$import['filename'];
		if (!file_exists($import['source'])) {
			$page['text'] = wrap_text('Import: File %s does not exist. '
				.'Please set a different filename', ['values' => $import['source']]);
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
		if (!isset($import['static1']))
			$import['static1'] = false;
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
		$sql = $import['source'];
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

	if (isset($_GET['deletable']) AND !empty($import['deletable'])) {
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
	$lines[] = wrap_text('Processing entries %s–%s …', ['values' => [$import['limit'] + 1, $import['end']]]);
	if ($updated)
		$lines[] = wrap_text('%d updates were made.', ['values' => $updated]);
	if ($inserted)
		$lines[] = wrap_text('%d inserts were made.', ['values' => $inserted]);
	if ($nothing AND (!$testing OR $post))
		$lines[] = wrap_text('%d records were left as is.', ['values' => $nothing]);
	if ($errors) {
		if (count($errors) === 1) {
			$lines[] = wrap_text('%d records had errors.', ['values' => 1]).sprintf('(%s)', implode(', ', $errors));
		} else {
			$lines[] = wrap_text('%d records had errors.', ['values' => count($errors)])
				."<ul><li>\n".implode("</li>\n<li>", $errors)."</li>\n</ul>\n";
		}
	}
	if ($testing) {
		if (!$post) {
			$lines[] = zz_sync_list($testing, $import);
		} elseif ($refresh) {
			$lines[] = sprintf('<a href="?limit=%s">%s</a>', $import['end'], wrap_text('Go on to next page'));
		} else {
			$lines[] = sprintf('<a href="?deletable">%s</a>', wrap_text('Possibly deletable records'));
		}
		$refresh = false;
	} elseif ($refresh) {
		$lines[] = wrap_text('Please wait for reload …');
	} else {
		$lines[] = wrap_text('Finished!');
	}

	if (!$lines) {
		$page['text'] = wrap_text('No updates/inserts were made.');
		return $page;
	}

	wrap_setting_add('extra_http_headers', 'X-Frame-Options: Deny');
	wrap_setting_add('extra_http_headers', "Content-Security-Policy: frame-ancestors 'self'");

	$page['query_strings'] = ['limit'];
	$page['text'] = implode('<br>', $lines);
	if ($refresh) {
		$page['head'] = sprintf("\t".'<meta http-equiv="refresh" content="%s; URL=%s?limit=%s">'."\n",
			wrap_setting('sync_page_refresh'), 
			wrap_setting('host_base').$zz_page['url']['full']['path'], $import['end']);
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
 *		string	sql[existing] = SQL query to get pairs of identifier/IDs
 *		array 	'fields' = list of fields, indexed by position
 *		array 	'static1', 'static2' = values for fields, 'field_name = value'
 *		string	'id_field_name' = field name of PRIMARY KEY of database table
 *		string	'form_script' = table script for sync
 *		array	'ignore_if_null' = list of field nos which will be ignored if
 *				no value is set
 * @return array $updated, $inserted, $nothing = count of records, $errors,
 *		$testing
 */
function zz_sync_zzform($raw, $import) {
	if (empty($import['existing'])) {
		wrap_error('Please define a query for the existing records in the database with -- identifier_existing --.', E_USER_ERROR);
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

	if (empty($_POST['action'])) $_POST['action'] = [];
	elseif (!is_array($_POST['action'])) $_POST['action'] = [$_POST['action']];

	// normalise lines
	// remove lines which have errors or are not active here
	foreach ($raw as $identifier => $line) {
		$line = $raw[$identifier] = zz_sync_line($line, $import['fields']);
		if (count($line) !== count($import['fields'])) {
			// there’s an error
			$errors[] = zz_sync_line_errors($line, $import['fields']);
			unset($raw[$identifier]);
		} elseif (!empty($import['testing']) AND !empty($_POST['action'])) {
			$ignore = false;
			// records need to be activated via checkboxes
			if (!array_key_exists($identifier, $_POST['action']))
				$ignore = true;
			elseif ($_POST['action'][$identifier] !== 'on')
				$ignore = true;
			if ($ignore) {
				unset($raw[$identifier]);
				$nothing++;
			}
		}
	}
	
	$raw = zz_sync_field_queries($raw, $import);

	// get existing keys from database
	$ids = zz_sync_ids($raw, $import['existing']);

	foreach ($raw as $identifier => $line) {
		$values = [];
		$values['POST'] = [];
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
				if (!in_array($pos, $import['show_but_no_import']))
					$values['POST'] = zz_check_values($values['POST'], $field_name, $value);
			}
		}
		// static values to import
		$static = 'static1';
		while (array_key_exists($static, $import)) {
			list($field_name, $value) = explode(' = ', $import[$static]);
			$field_name = trim($field_name);
			$value = trim($value);
			$value = trim($value, "'");
			$value = trim($value, '"');
			$head[$field_name] = $field_name;
			$testing[$identifier][$field_name] = $value;
			$values['POST'] = zz_check_values($values['POST'], $field_name, $value);

			$static = substr($static, 6);
			$static++;
			$static = sprintf('static%d', $static);
		}
		if (!empty($ids[$identifier])) {
			$testing[$identifier]['_action'] = $values['action'] = 'update';
			$values['GET']['where'][$import['id_field_name']] = $ids[$identifier];
			$testing[$identifier]['_id'] = $ids[$identifier];
		} else {
			$testing[$identifier]['_action'] = $values['action'] = 'insert';
			$testing[$identifier]['_insert'] = true;
		}
		if (empty($_POST['action'])) continue;
		if (!empty($import['ids'])) $values['ids'] = $import['ids'];
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
	$testing['head'] = $head ?? [];
	return [$updated, $inserted, $nothing, $errors, $testing];
}

/**
 * remove empty fields at end of line
 *
 * @param array $line
 * @param array $fields
 * @return array
 */
function zz_sync_line($line, $fields) {
	if (count($line) > count($fields)) {
		// remove whitespace only fields at the end of the line
		do {
			$last = array_pop($line);
		} while (!$last AND count($line) >= count($fields));
		$line[] = $last;
	}
	return $line;
}

/**
 * read corresponding ID values for fields from database
 *
 * @param array $raw
 * @param array $import
 */
function zz_sync_field_queries($raw, $import) {
	foreach ($import['fields'] as $index => $field) {
		$key = 'field_'.$field;
		if (!array_key_exists($key, $import)) continue;
		$values = [];
		foreach ($raw as $line)
			$values[trim($line[$index])] = trim($line[$index]);
		$implode = $import[$key.'__implode'] ?? ',';
		$implode = ltrim($implode, '/* ');
		$implode = rtrim($implode, ' */');
		$sql = sprintf($import[$key], implode($implode, $values));
		$ids = wrap_db_fetch($sql, '_dummy_', 'key/value');
		foreach ($raw as $identifier => $line)
			$raw[$identifier][$index] = $ids[trim($line[$index])];
	}
	return $raw;
}

/**
 * set error message per line
 *
 * @param array $line
 * @param array $fields
 * @return string
 */
function zz_sync_line_errors($line, $fields) {
	$error_line = [];
	foreach ($fields as $pos => $field_name)
		$error_line[$field_name] = $line[$pos] ??  '<strong>=>||| '.wrap_text('not set').' |||<=</strong>';
	$error_msg = (count($line) > count($fields)) ? 'too many values:' : 'not enough values:';
	return $error_msg.' '.wrap_print($error_line).wrap_print($line);
}


/**
 * display records to import
 *
 * @param array $testing
 * @param array $import
 * @return string
 */
function zz_sync_list($testing, $import) {
	// get head
	$def = zzform_include($import['form_script']);
	$head = zz_sync_fields($def['fields'], $testing['head']);
	$field_names = [];
	$foreign_keys = [];
	$foreign_keys[$def['table']] = $def['fields'][1]['field_name'];
	$id_fields = [];
	$id_fields[$def['table']] = $def['fields'][1]['field_name'];
	foreach ($head as $field) {
		if (empty($field['field_name'])) continue;
		$table = $field['table'] ?? $def['table'];
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
	$data = [];

	switch ($import['type']) {
	case 'csv':
		$import['end'] = 0;
		list($raw, $i) = zz_sync_csv($import);
		break;
	case 'sql':
		$raw = wrap_db_fetch($import['source'], $import['import_id_field_name']);
		break;
	}
	$existing = zz_sync_ids($raw, $import['deletable'], 'numeric');

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
				if (array_key_exists($field_name, $import['deletable_script_url'])) {
					$data[$index]['fields'][$i]['my_script_url'] = $import['deletable_script_url'][$field_name];
				}
				$i++;
			}
		}
		$data[$index]['no'] = $j;
		$data[$index]['script_url'] = isset($import['script_url']) ? $import['script_url'] : '';
	}
	if (!empty($data['head'])) $data['head'] = array_values($data['head']);
	if (!$data) $data['no_deletable_records'] = true;

	wrap_setting_add('extra_http_headers', 'X-Frame-Options: Deny');
	wrap_setting_add('extra_http_headers', "Content-Security-Policy: frame-ancestors 'self'");

	$page['query_strings'] = ['deletable'];
	$page['text'] = wrap_template('sync-deletable', $data);
	$page['title'] = wrap_text('Deletable Records');
	return $page;
}

/**
 * get IDs from raw keys
 *
 * @param array $raw
 * @param string $query
 * @return array
 */
function zz_sync_ids($raw, $query, $format = 'key/value') {
	$keys = array_keys($raw);
	foreach ($keys as $id => $key) $keys[$id] = wrap_db_escape($key);
	$keys = '"'.implode('", "', $keys).'"';
	$sql = sprintf($query, $keys);
	$ids = wrap_db_fetch($sql, '_dummy_', $format);
	return $ids;
}
