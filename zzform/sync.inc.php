<?php

/**
 * zzform
 * Synchronisation functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2011-2018, 2021-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Sync some data with other database content
 *
 * @param array $setting
 *		int		'limit'
 *		int		'end'
 *		string	'type' (csv, sql)
 * @return array
 */
function zz_sync($setting) {
	$refresh = false;
	wrap_include('batch', 'zzform');

	if (!empty($setting['identifier']))
		$setting += zz_sync_queries($setting['identifier']);
	
	$setting = zz_init_cfg('sync-settings', $setting);
	$setting = zz_sync_defaults($setting);

	if (isset($_GET['deletable'])) {
		if (!$setting['deletable']) wrap_quit(404, 'Deletions are not possible for this synchronization.');
		return zz_sync_deletable($setting);
	}

	switch ($setting['type']) {
	case 'csv':
		if (!file_exists($setting['csv_source']))
			wrap_quit(503, wrap_text('Import: File %s does not exist. '
				.'Please set a different filename.', ['values' => $setting['csv_source']]));
		list($raw, $i) = zz_sync_csv($setting);
		if ($i === $setting['end']) $refresh = true;
		break;
	case 'sql':
		$limit = $setting['end'] - $setting['limit'];
		$sql = $setting['source'];
		$sql .= sprintf(' LIMIT %d, %d', $setting['limit'], $limit);
		$raw = wrap_db_fetch($sql, $setting['source_id_field_name']);
		if (count($raw) === $limit) $refresh = true;
		foreach ($raw as $id => $line) {
			// we need fields as numeric values
			unset($raw[$id]);
			foreach ($line as $value) {
				$raw[$id][] = $value;
			}
		}
		break;
	}

	if (empty($raw)) return [];

	// sync data
	if ($setting['testing'] OR $_SERVER['REQUEST_METHOD'] === 'POST')
		$data = zz_sync_zzform($raw, $setting);
	$data['begin'] = $setting['limit'] + 1;
	$data['end'] = $setting['end'];

	// only show processed records if records were processed, no 0 values
	if (empty($data['nothing'])) $data['nothing'] = NULL;
	if (empty($data['updated'])) $data['updated'] = NULL;
	if (empty($data['inserted'])) $data['inserted'] = NULL;

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		if ($setting['testing'])
			$data = zz_sync_list($data, $setting);
		else
			unset($data['records']);
	} else {
		$data['post'] = true;
		$data['errors_count'] = count($data['errors']);
		$data['refresh'] = $refresh;
		$data['last'] = !$refresh;
	}
	return $data;
}

/**
 * read data from sync.sql
 *
 * @param string $identifier
 * @return array
 */
function zz_sync_queries($identifier) {
	$queries = [];
	$files = wrap_collect_files('configuration/sync.sql');
	foreach ($files as $file)
		$queries = array_merge_recursive($queries, wrap_sql_file($file, '_'));

	if (!array_key_exists($identifier, $queries)) return [];
	$queries = $queries[$identifier];
	$queries = wrap_sql_placeholders($queries);

	// get ids
	$ids = [];
	foreach ($queries as $key => $query) {
		if (!str_starts_with($key, 'static')) continue;
		unset($queries[$key]);
		list($field_name, $value) = explode(' = ', $query);
		$field_name = trim($field_name);
		$value = trim($value);
		$value = trim($value, "'");
		$value = trim($value, '"');
		$queries['static'][$field_name] = $value;
	}

	// get field
	foreach ($queries as $key => $query) {
		if (!str_starts_with($key, 'field_')) continue;
		unset($queries[$key]);
		$keys = explode('_', $key);
		$new_keys = [];
		$new_keys[] = array_shift($keys); // remove `field`
		$pos = array_search('', $keys);
		if ($pos !== false) {
			while(count($keys) > $pos) {
				$new_key = array_pop($keys);
				if ($new_key) array_unshift($new_keys, $new_key);
			}
		}
		$new_keys = array_reverse($new_keys);
		$queries[implode('_', $new_keys)][implode('_', $keys)] = $query;
	}

	return $queries;
}

/**
 * set default values for sync
 *
 * @param array $setting
 * @return array
 * @todo solve this via .cfg file
 */
function zz_sync_defaults($setting) {
	// limits
	if (!$setting['sync_records_per_run']) {
		$setting['sync_records_per_run'] = wrap_setting('sync_records_per_run');
		if (!$setting['testing']) $setting['sync_records_per_run'] *= 10;
	}
	$setting['limit'] = empty($_GET['limit']) ? 0 : zz_check_get_array('limit', 'is_int');
	$setting['end'] = $setting['limit'] + $setting['sync_records_per_run'];

	switch ($setting['type']) {
	case 'csv':
		// get source file
		if (empty($setting['csv_filename']))
			wrap_error('Please set an import filename via $setting["csv_filename"].', E_USER_ERROR);
		if (str_starts_with($setting['csv_filename'], '%'))
			$setting['csv_source'] = wrap_setting_value_placeholder($setting['csv_filename']);
		else
			$setting['csv_source'] = wrap_setting('cms_dir').'/_sync/'.$setting['csv_filename'];
		break;
	case 'sql':
		break;
	default:
		wrap_error(
			sprintf(
				'Please set an import type via <code>$setting["type"]</code>. Possible types are: %s'
				, implode(', ', ['csv', 'sql'])
			), E_USER_ERROR
		);
	}

	if (empty($setting['existing']))
		wrap_error(wrap_text('Please define a query for the existing records in the database with -- %s_existing --.',
			['values' => [$setting['identifier']]]
		), E_USER_ERROR);
	if (empty($setting['fields']))
		wrap_error('Please set which fields should be imported in `fields`.', E_USER_ERROR);	
	if (empty($setting['form_script']))
		wrap_error('Please tell us the name of the form script in `form_script`.', E_USER_ERROR);	

	// set correct table_name for subtables
	$setting = zz_sync_table_shortcut($setting);
	
	if (!$setting['logging']) wrap_setting('zzform_logging', false);
	return $setting;
}

/**
 * allow for shortcut notation, just table, not table name
 *
 * @param array $setting
 * @return array
 */
function zz_sync_table_shortcut($setting) {
	$def = zzform_batch_def($setting['form_script']);
	
	$static_values = [];
	foreach ($setting['static'] as $field_name => $value)
		$static_values = zz_check_values($static_values, $field_name, $value);
	foreach ($static_values as $table => $values) {
		if (in_array($table, $def['subtable_tables'])) continue;
		// check which of the subtables is correct here
		foreach ($def['subtable_tables'] as $field_no => $subtable) {
			if (!str_starts_with($subtable, $table)) continue;
			foreach ($def['fields'][$field_no]['fields'] as $sub_no => $sub_field) {
				if (empty($sub_field['field_name'])) continue;
				if (!array_key_exists($sub_field['field_name'], $values[0])) continue;
				if (empty($sub_field['value'])) continue;
				if ($sub_field['value'] !== $values[0][$sub_field['field_name']]) continue;
				
				// we found it
				foreach ($setting['fields'] as $index => $field) {
					if (!str_starts_with($field, $table.'[')) continue;
					$setting['fields'][$index] = $subtable.substr($field, strlen($table));
				}
				$keys = ['field', 'field_implode', 'static'];
				foreach ($keys as $key) {
					foreach ($setting[$key] as $identifier => $sql) {
						if (!str_starts_with($identifier, $table.'[')) continue;
						unset($setting[$key][$identifier]);
						$identifier = $subtable.substr($identifier, strlen($table));
						$setting[$key][$identifier] = $sql;
					}
				}
			}
		}
	}
	
	return $setting;
}

/**
 * Sync data from CSV file with database content
 *
 * @param array $setting
 * @return array $raw
 */
function zz_sync_csv($setting) {
	// open CSV file
	$i = 0;
	$first = false;
	$handle = fopen($setting['csv_source'], "r");

	if (!count($setting['csv_key']))
		wrap_error('Please set one or more fields as unique key fields in `csv_key`.', E_USER_ERROR);

	$processed = 0;
	while (!feof($handle)) {
		if ($setting['csv_fixed_width']) {
			$line = zz_sync_csv_fixed_width_line($handle, $setting);
		} else {
			$line = fgetcsv($handle, 8192, $setting['csv_delimiter'], $setting['csv_enclosure']);
		}
		$line_complete = $line;
		// ignore empty lines
		if (!$line) continue;
		if (!trim(implode('', $line))) continue;
		// ignore comments
		if ($setting['csv_ignore_head_lines']) {
			$setting['csv_ignore_head_lines']--;
			continue;
		}
		if ($setting['csv_comments'] AND str_starts_with(reset($line), $setting['csv_comments'])) continue;
		// ignore first line = field names
		if ($setting['csv_first_line_headers'] AND !$i AND !$first) {
			$first = true;
			continue;
		}
		// start counting lines
		$i++;
		// ignore lines that were already processed
		if ($i <= $setting['limit']) continue;
		// do not import some fields which should be ignored
		foreach ($setting['csv_ignore_fields'] as $no) unset($line[$no]);
		// save lines in $raw
		foreach (array_keys($line) AS $id) {
			$line[$id] = trim($line[$id]);
			if (empty($line[$id]) AND isset($setting['csv_empty_fields_use_instead'][$id])) {
				$line[$id] = trim($line_complete[$setting['csv_empty_fields_use_instead'][$id]]);
			}
		}
		$key = [];
		foreach ($setting['csv_key'] AS $no) {
			if (!isset($line[$no])) {
				wrap_error(sprintf(
					'New record has not enough values for the key. (%d expected, record looks as follows: %s)',
					count($line), implode(' -- ', $line)
				), E_USER_ERROR);
			}
			$key[] = $line[$no];
		}
		$key = trim(implode($setting['csv_key_concat'], $key));
		$raw[$key] = $line;
		$processed++; // does not necessarily correspond with count($raw)
		if ($processed === ($setting['end'] - $setting['limit'])) break;
	}
	fclose($handle);
	if (empty($raw)) return [[], $i];
	return [$raw, $i];
}

/**
 * get lines in fixed width format
 *
 * @param resource $handle
 * @param array $setting
 */
function zz_sync_csv_fixed_width_line($handle, $setting) {
	static $head = [];
	if (!array_key_exists($setting['identifier'], $head)) {
		// get first line
		$line = fgets($handle, 8192);
		foreach ($setting['csv_fixed_width_replace'] as $old => $new)
			$line = str_replace($old, $new, $line);
		preg_match_all('~([-\w]+)\s+~', $line, $matches);
		$head[$setting['identifier']]['fields'] = $matches[1];
		$pos = 0;
		foreach ($matches[0] as $index => $field) {
			$head[$setting['identifier']]['length'][$index] = strlen($field);
			$head[$setting['identifier']]['start'][$index] = $pos;
			$pos += strlen($field);
		}
	}
	$line = fgets($handle, 8192);
	if ($line === false) return $line;
	$new_line = [];
	foreach ($head[$setting['identifier']]['fields'] as $index => $field) {
		if ($setting['csv_id_only'] and !in_array($field, $setting['csv_key'])) continue;
		$value = trim(substr($line, $head[$setting['identifier']]['start'][$index], $head[$setting['identifier']]['length'][$index]));
		$new_line[$field] = $value;
	}
	return $new_line;
}

/**
 * Sync of raw data to import with existing data, updates or inserts raw data
 * as required
 *
 * @param array $raw raw data, indexed by identifier
 * @param array $setting sync settings
 * @return array
 */
function zz_sync_zzform($raw, $setting) {
	$data = [
		'updated' => 0,
		'inserted' => 0,
		'nothing' => 0,
		'errors' => []
	];
	$raw_count = count($raw);

	if (empty($_POST['action'])) $_POST['action'] = [];
	elseif (!is_array($_POST['action'])) $_POST['action'] = [$_POST['action']];

	// normalise lines
	// remove lines which have errors or are not active here
	foreach ($raw as $identifier => $line) {
		$line = $raw[$identifier] = zz_sync_line($line, $setting['fields']);
		if (count($line) !== count($setting['fields'])) {
			// there’s an error
			$data['errors'][]['error'] = zz_sync_line_errors($line, $setting['fields']);
			unset($raw[$identifier]);
		} elseif ($setting['testing'] AND $_SERVER['REQUEST_METHOD'] === 'POST') {
			$ignore = false;
			// records need to be activated via checkboxes
			if (!array_key_exists($identifier, $_POST['action']))
				$ignore = true;
			elseif ($_POST['action'][$identifier] !== 'on')
				$ignore = true;
			if ($ignore) {
				unset($raw[$identifier]);
				$data['nothing']++;
			}
		}
	}
	if ($data['nothing'] === $raw_count)
		return $data;
	
	$raw = zz_sync_field_queries($raw, $setting);

	// get existing keys from database
	$ids = zz_sync_ids($raw, $setting['existing']);
	$def = zzform_batch_def($setting['form_script']);
	if (!empty($setting['existing_order_by']))
		$def['order_by'] = $setting['existing_order_by'];

	$data['field_names'] = zz_sync_field_names($setting);
	$data['fields'] = zz_sync_fields($def['fields'], $data['field_names']);
	$existing = zz_sync_existing($def, $data['fields'], $ids);

	$lines = [];
	foreach ($raw as $identifier => $line_raw) {
		$line = [];
		foreach ($setting['fields'] as $pos => $field_name) {
			// don’t delete field values if ignore_if_null is set
			if (in_array($pos, $setting['ignore_if_null'])
				AND empty($line_raw[$pos]) AND $line_raw[$pos] !== 0 AND $line_raw[$pos] !== '0') continue;
			// do nothing if value is NULL
			if ($line_raw[$pos]) $line_raw[$pos] = trim($line_raw[$pos]);
			if (!$line_raw[$pos]) $line_raw[$pos] = zz_sync_null_value($line_raw[$pos], $field_name, $def['fields']);
			if (!isset($line_raw[$pos])) continue;
			$fields = [];
			if (!empty($setting['function'][$pos])) {
				$fields[$field_name] = $setting['function'][$pos]($line_raw[$pos]);
			} elseif (strstr($field_name, '+') AND !empty($setting['split_function'][$pos])) {
				// @todo error handling
				$field_names = explode('+', $field_name);
				$my_values = $setting['split_function'][$pos](trim($line_raw[$pos]));
				foreach ($field_names as $field_name) {
					$fields[$field_name] = array_shift($my_values);
				}
			} else {
				$fields[$field_name] = trim($line_raw[$pos]);
			}
			foreach ($fields as $field_name => $value) {
				if ($setting['testing'])
					$data['records'][$identifier]['line_flat'][$field_name] = $value;
				if (!in_array($pos, $setting['show_but_no_import']))
					$line = zz_check_values($line, $field_name, $value);
			}
		}
		// static values to sync
		foreach ($setting['static'] as $field_name => $value) {
			if ($setting['testing'])
				$data['records'][$identifier]['line_flat'][$field_name] = $value;
			$line = zz_check_values($line, $field_name, $value);
		}
		if (!empty($ids[$identifier])) {
			if ($setting['testing'])
				list($data['records'][$identifier]['identical'], $data['records'][$identifier]['identical_fields'])
					= zz_sync_identical($line, $existing[$ids[$identifier]] ?? []);
			else
				$data['records'][$identifier]['identical'] = zz_sync_identical($line, $existing[$ids[$identifier]] ?? [], false);
			if ($data['records'][$identifier]['identical'])
				$data['records'][$identifier]['action'] = 'ignore';
			else
				$data['records'][$identifier]['action'] = 'update';
			$data['records'][$identifier]['id'] = $ids[$identifier];
			$data['records'][$identifier]['existing'] = $existing[$ids[$identifier]] ?? [];
			$line[$def['primary_key']] = $ids[$identifier];
		} else {
			$data['records'][$identifier]['action'] = 'insert';
			$data['records'][$identifier]['insert'] = true;
		}
		$lines[$identifier] = $line;
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST')
		return $data;

	foreach ($raw as $identifier => $line_raw) {
		if ($data['records'][$identifier]['action'] === 'update') {
			$success = zzform_update($setting['form_script'], $lines[$identifier]);
			if ($success) $data['updated']++;
			else $data['nothing']++;
		} elseif ($data['records'][$identifier]['action'] === 'insert') {
			$success = zzform_insert($setting['form_script'], $lines[$identifier]);
			if ($success) $data['inserted']++;
			else $data['nothing']++;
		} else {
			$data['nothing']++;
		}
	}
	unset($data['records']);
	return $data;
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
 * read corresponding ID values for fields from database
 *
 * @param array $raw
 * @param array $setting
 */
function zz_sync_field_queries($raw, $setting) {
	foreach ($setting['fields'] as $index => $field) {
		if (!array_key_exists($field, $setting['field'])) continue;
		$values = [];
		foreach ($raw as $line)
			$values[trim($line[$index])] = trim($line[$index]);
		$implode = $setting['field_implode'][$field] ?? ',';
		$implode = ltrim($implode, '/* ');
		$implode = rtrim($implode, ' */');
		$sql = sprintf($setting['field'][$field], implode($implode, $values));
		$ids = wrap_db_fetch($sql, '_dummy_', 'key/value');
		foreach ($raw as $identifier => $line)
			$raw[$identifier][$index] = $ids[trim($line[$index])];
	}
	return $raw;
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

/**
 * get all field names
 *
 * @param array $setting
 * @return array
 */
function zz_sync_field_names($setting) {
	$fields = [];
	foreach ($setting['fields'] as $pos => $field_name) {
		if (strstr($field_name, '+') AND !empty($setting['split_function'][$pos])) {
			$field_names = explode('+', $field_name);
			foreach ($field_names as $field_name)
				$fields[$field_name] = $field_name;
		} else
			$fields[$field_name] = $field_name;
	}
	foreach (array_keys($setting['static']) as $field_name)
		$fields[$field_name] = $field_name;
	return array_values($fields);
}

/**
 * get fields out of zzform definition that are displayed in sync table
 *
 * @param array $fields = $zz['fields']
 * @param array $old_head = $data['field_names'], list of field names
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
			$table_name = $field['table_name'] ?? $field['table'];
			$foreign_key = false;
			foreach ($field['fields'] as $subno => $subfield) {
				if (!$subfield) continue;
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
				$subfield += [
					'table_title' => $field['title'] ?? ucfirst($field['table']),
					'table' => $field['table'],
					'table_name' => $field['table_name'] ?? $field['table'],
					'id_field' => $id_field_name,
					'foreign_key' => $foreign_key
				];
				$head[$no.'-'.$subno] = $subfield;
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
 * get existing records
 *
 * @param array $def
 * @param array $fields
 * @param array $ids
 * @return array
 */
function zz_sync_existing($def, $fields, $ids) {
	if (!$ids) return [];
	$tables = [];
	$foreign_keys = [];
	$foreign_keys[$def['table']] = $def['primary_key'];
	$id_fields = [];
	$id_fields[$def['table']] = $def['primary_key'];

	foreach ($fields as $field) {
		if (empty($field['field_name'])) continue;
		$table = $field['table'] ?? $def['table'];
		$tables[$table]['table'] = $table;
		$tables[$table]['table_name'] =  $field['table_name'] ?? $table;
		$tables[$table]['fields'][] = $field['field_name'];
		$tables[$table]['order_by'] = $def['order_by'][$table] ?? '';
		if (!empty($field['foreign_key'])) {
			$foreign_keys[$table] = $field['foreign_key'];
		}
		if (!empty($field['id_field'])) {
			$id_fields[$table] = $field['id_field'];
		}
	}

	$existing = [];
	foreach ($tables as $tdef) {
		if (!in_array($foreign_keys[$tdef['table']], $tdef['fields'])) {
			array_unshift($tdef['fields'], $foreign_keys[$tdef['table']]);
		}
		if (!in_array($id_fields[$tdef['table']], $tdef['fields'])) {
			array_unshift($tdef['fields'], $id_fields[$tdef['table']]);
		}
		$sql = 'SELECT %s
			FROM %s
			WHERE %s IN (%s)';
		$sql = sprintf($sql
			, implode(', ', $tdef['fields'])
			, $tdef['table']
			, $def['primary_key']
			, implode(',', $ids)
		);
		if ($tdef['order_by']) $sql .= sprintf(' ORDER BY %s', $tdef['order_by']);
		if ($id_fields[$tdef['table']] === $foreign_keys[$tdef['table']]) {
			$existing = wrap_db_fetch($sql, $id_fields[$tdef['table']]);
		} else {
			$details = wrap_db_fetch($sql, [$foreign_keys[$tdef['table']], $id_fields[$tdef['table']]], 'numeric');
			foreach ($details as $foreign_key => $record)
				$existing[$foreign_key][$tdef['table_name']] = $record;
		}
	}
	return $existing;
}

/**
 * set field value to NULL if empty
 *
 * @param string $field_name
 * @param array $fields
 * @return array
 */
function zz_sync_null_value($value, $field_name, $fields) {
	$fielddef = zz_sync_def_field($field_name, $fields);
	if ($value === 0 OR $value === '0' AND !empty($fielddef['null'])) return $value;
	if ($value === '' AND !empty($fielddef['null_string'])) return $value;
	return NULL;
}

/**
 * get definition for field by field name
 *
 * @param string $field_name
 * @param array $fields
 * @return array
 */
function zz_sync_def_field($field_name, $fields) {
	foreach ($fields as $field) {
		if (empty($field['field_name'])) continue;
		if ($field['field_name'] !== $field_name) continue;
		return $field;
	}
	return [];
}
/**
 * check record if it is identical to existing record
 *
 * @param array $new
 * @param array $existing
 * @param bool $show_field_data show data for each field if it is identical or not
 * @return array
 */
function zz_sync_identical($new, $existing, $show_field_data = true) {
	if ($show_field_data) $check = [];
	$identical = true;
	if (!$existing) {
		$identical = false;
		return [$identical, $check];
	}
	foreach ($new as $field_name => $value) {
		if (!is_array($value)) {
			$identical_field = $value === $existing[$field_name] ? true : false;
			if ($show_field_data)
				$check[$field_name] = $identical_field;
			if (!$identical_field) {
				if (!$show_field_data) return false;
				$identical = false;
			}
		} else {
			foreach ($value as $index => $details) {
				foreach ($details as $detail_field_name => $detail_value) {
					if (!isset($existing[$field_name][$index][$detail_field_name]))
						$identical_field = false;
					else
						$identical_field = $detail_value === $existing[$field_name][$index][$detail_field_name] ? true : false;
					if ($show_field_data)
						$check[$field_name][$index][$detail_field_name] = $identical_field;
					if (!$identical_field) {
						if (!$show_field_data) return false;
						$identical = false;
					}
				}
			}
		}
	}
	if (!$show_field_data) return $identical;
	return [$identical, $check];
}

/**
 * display records to import
 *
 * @param array $data
 * @param array $setting
 * @return string
 */
function zz_sync_list($data, $setting) {
	$j = intval($setting['limit']);
	$missing_fields = [];
	foreach ($data['records'] as $index => &$line) {
		$j++;
		foreach (array_keys($data['fields']) as $num) {
			if (substr($num, 0, 1) === '_') continue;
			$line['fields'][$num]['value'] = ''; 
		}
		foreach ($line['line_flat'] as $key => $value) {
			if (substr($key, 0, 1) === '_') continue;
			if (!array_key_exists($key, $data['fields']['_mapping'])) {
				$missing_fields[$key] = $key;
				continue;
			}
			$num = $data['fields']['_mapping'][$key];
			if (strstr($num, '-')) {
				$field = explode('[', $key);
				foreach ($field as $field_index => $part)
					$field[$field_index] = rtrim($part, ']');
				$line['fields'][$num]['values'][$field[1]]['value'] = $value;
				$line['fields'][$num]['values'][$field[1]]['existing']
					= $line['existing'][$field[0]][$field[1]][$field[2]] ?? NULL;
				$line['fields'][$num]['values'][$field[1]]['identical']
					= $line['identical_fields'][$field[0]][$field[1]][$field[2]] ?? false;
			} else {
				$line['fields'][$num]['value'] = $value;
				$line['fields'][$num]['existing'] = $line['existing'][$key] ?? NULL;
				$line['fields'][$num]['identical'] = $line['identical_fields'][$key] ?? NULL;
			}
		}
		$line['no'] = $j;
		$line['index'] = $index;
		$line['script_url'] = zz_sync_script_url($setting);
	}

	foreach ($missing_fields as $field)
		wrap_error(wrap_text('Field %s is missing in table definition.', ['values' => [$field]]));
	
	foreach ($data['fields'] as $num => $field) {
		if (substr($num, 0, 1) === '_') continue;
		$data['head'][$num]['field_name'] = '';
		if (isset($field['table_title'])) {
			$data['head'][$num]['field_name'] .= $field['table_title'].'<br>';
		}
		$data['head'][$num]['field_name'] .= $field['title'] ?? $field['field_name'];
	}
	return $data;
}

/**
 * show deletable records
 *
 * @param array $setting
 * @return array
 */
function zz_sync_deletable($setting) {
	$data = [];
	$def = zzform_batch_def($setting['form_script']);

	switch ($setting['type']) {
	case 'csv':
		$setting['end'] = 0;
		$setting['csv_id_only'] = true;
		list($raw, $i) = zz_sync_csv($setting);
		break;
	case 'sql':
		$raw = wrap_db_fetch($setting['source'], $setting['source_id_field_name']);
		break;
	}
	$existing = zz_sync_ids($raw, $setting['deletable'], 'numeric');
	if ($_SERVER['REQUEST_METHOD'] === 'POST' AND !$setting['testing']) {
		$deleted_ids = zzform_delete('fide-players', array_column($existing, 'player_id'));
		$data['deleted'] = count($deleted_ids);
	} else {
		$j = 0;
		foreach ($existing as $index => $record) {
			$i = 0;
			$j++;
			foreach ($record as $field_name => $value) {
				if ($field_name === $def['primary_key']) {
					$data['records'][$index]['id'] = $value;
				} else {
					$data['head'][$field_name]['field_name'] = $field_name;
					$data['records'][$index]['fields'][$i]['value'] = $value;
					if (array_key_exists($field_name, $setting['deletable_script_path'])) {
						$data['records'][$index]['fields'][$i]['my_script_url'] = $setting['deletable_script_path'][$field_name];
					}
					$i++;
				}
			}
			$data['records'][$index]['no'] = $j;
			$data['records'][$index]['script_url'] = zz_sync_script_url($setting);
		}
		if (!empty($data['head'])) $data['head'] = array_values($data['head']);
		if (!$data) $data['no_deletable_records'] = true;
	}

	$data['testing'] = $setting['testing'];
	return $data;
}

/**
 * create script URL
 *
 * @param array $setting
 * @return string
 */
function zz_sync_script_url($setting) {
	if ($setting['script_path']) return wrap_path($setting['script_path']);
	return $setting['script_url'];
}
