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
	$post = $_SERVER['REQUEST_METHOD'] === 'POST' ? true : false;	// will be overwritten
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

	if (empty($raw)) {
		$page['query_string'] = 'limit';
		$page['status'] = 404;
		$page['text'] = '';
		return $page;
	}

	// sync data
	list($updated, $inserted, $nothing, $errors, $data) = zz_sync_zzform($raw, $setting);

	// output results
	$lines = [];
	$lines[] = wrap_text('Processing entries %s–%s …', ['values' => [$setting['limit'] + 1, $setting['end']]]);
	if ($updated)
		$lines[] = wrap_text('%d updates were made.', ['values' => $updated]);
	if ($inserted)
		$lines[] = wrap_text('%d inserts were made.', ['values' => $inserted]);
	if ($nothing AND (!$data OR $post))
		$lines[] = wrap_text('%d records were left as is.', ['values' => $nothing]);
	if ($errors) {
		if (count($errors) === 1) {
			$lines[] = wrap_text('%d records had errors.', ['values' => 1]).sprintf('(%s)', implode(', ', $errors));
		} else {
			$lines[] = wrap_text('%d records had errors.', ['values' => count($errors)])
				."<ul><li>\n".implode("</li>\n<li>", $errors)."</li>\n</ul>\n";
		}
	}
	if ($data) {
		if (!$post) {
			$lines[] = zz_sync_list($data, $setting);
		} elseif ($refresh) {
			$lines[] = sprintf('<a href="?limit=%s">%s</a>', $setting['end'], wrap_text('Go on to next page'));
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
		$page['head'] = sprintf("\t".'<meta http-equiv="refresh" content="%s; URL=%s%s?limit=%s">'."\n",
			wrap_setting('sync_page_refresh'), 
			wrap_setting('host_base'), parse_url(wrap_setting('request_uri'), PHP_URL_PATH), $setting['end']);
	}
	return $page;
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
	$setting['sync_records_per_run'] = wrap_setting('sync_records_per_run') * ($setting['testing'] ? 1 : 10);
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
 * @return array $updated, $inserted, $nothing = count of records, $errors,
 *		$data
 */
function zz_sync_zzform($raw, $setting) {
	$updated = 0;
	$inserted = 0;
	$nothing = 0;
	$raw_count = count($raw);
	$errors = [];
	$data = [];

	if (empty($_POST['action'])) $_POST['action'] = [];
	elseif (!is_array($_POST['action'])) $_POST['action'] = [$_POST['action']];

	// normalise lines
	// remove lines which have errors or are not active here
	foreach ($raw as $identifier => $line) {
		$line = $raw[$identifier] = zz_sync_line($line, $setting['fields']);
		if (count($line) !== count($setting['fields'])) {
			// there’s an error
			$errors[] = zz_sync_line_errors($line, $setting['fields']);
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
				$nothing++;
			}
		}
	}
	if ($nothing === $raw_count)
		return [$updated, $inserted, $nothing, $errors, true];
	
	$raw = zz_sync_field_queries($raw, $setting);

	// get existing keys from database
	$ids = zz_sync_ids($raw, $setting['existing']);
	$def = zzform_batch_def($setting['form_script']);

	foreach ($raw as $identifier => $line_raw) {
		$line = [];
		foreach ($setting['fields'] as $pos => $field_name) {
			// don’t delete field values if ignore_if_null is set
			if (in_array($pos, $setting['ignore_if_null'])
				AND empty($line_raw[$pos]) AND $line_raw[$pos] !== 0 AND $line_raw[$pos] !== '0') continue;
			// do nothing if value is NULL
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
				$head[$field_name] = $field_name;
				$data[$identifier][$field_name] = $value;
				if (!in_array($pos, $setting['show_but_no_import']))
					$line = zz_check_values($line, $field_name, $value);
			}
		}
		// static values to sync
		foreach ($setting['static'] as $field_name => $value) {
			$head[$field_name] = $field_name;
			$data[$identifier][$field_name] = $value;
			$line = zz_check_values($line, $field_name, $value);
		}
		if (!empty($ids[$identifier])) {
			$data[$identifier]['_action'] = 'update';
			$data[$identifier]['_id'] = $ids[$identifier];
			$line[$def['primary_key']] = $ids[$identifier];
		} else {
			$data[$identifier]['_action'] = 'insert';
			$data[$identifier]['_insert'] = true;
		}
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') continue;
		if ($data[$identifier]['_action'] === 'update') {
			$success = zzform_update($setting['form_script'], $line);
			if ($success) $updated++;
			else $nothing++;
		} else {
			$success = zzform_insert($setting['form_script'], $line);
			if ($success) $inserted++;
			else $nothing++;
		}
	}
	$data['head'] = $head ?? [];
	return [$updated, $inserted, $nothing, $errors, $data];
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
 * get fields out of zzform definition that are displayed in sync table
 *
 * @param array $fields = $zz['fields']
 * @param array $old_head = $data['head'], list of field names
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
 * @param array $head
 * @param array $ids
 * @return array
 */
function zz_sync_existing($def, $head, $ids) {
	$field_names = [];
	$foreign_keys = [];
	$foreign_keys[$def['table']] = $def['primary_key'];
	$id_fields = [];
	$id_fields[$def['table']] = $def['primary_key'];

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
		$sql = sprintf($sql
			, implode(', ', $fnames)
			, $table
			, $def['primary_key']
			, implode(',', $ids)
		);
		if ($id_fields[$table] === $foreign_keys[$table]) {
			$existing[$table] = wrap_db_fetch($sql, $id_fields[$table]);
		} else {
			$existing[$table] = wrap_db_fetch($sql, [$foreign_keys[$table], $id_fields[$table]], 'numeric');
		}
	}
	return $existing;
}

/**
 * get all IDs
 *
 * @param array $data
 * @return array
 */
function zz_sync_id_values($data) {
	$ids = [];
	foreach ($data as $index => $line) {
		foreach ($line as $key => $value) {
			if ($key === '_id') $ids[] = $value;
		}
	}
	return $ids;
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
 * display records to import
 *
 * @param array $data
 * @param array $setting
 * @return string
 */
function zz_sync_list($data, $setting) {
	// get head
	$def = zzform_batch_def($setting['form_script']);
	$head = zz_sync_fields($def['fields'], $data['head']);
	unset($data['head']);

	// get existing records
	$ids = zz_sync_id_values($data);
	$existing = zz_sync_existing($def, $head, $ids);

	$j = intval($setting['limit']);
	$missing_fields = [];
	foreach ($data as $index => $line) {
		$j++;
		foreach (array_keys($head) as $num) {
			if (substr($num, 0, 1) === '_') continue;
			$data[$index]['fields'][$num]['value'] = ''; 
		}
		$identical = true;
		foreach ($line as $key => $value) {
			if (substr($key, 0, 1) === '_') continue;
			if (!array_key_exists($key, $head['_mapping'])) {
				$missing_fields[$key] = $key;
				continue;
			}
			$num = $head['_mapping'][$key];
			if (strstr($num, '-')) {
				$row_index = substr($key, strpos($key, '[') + 1, strpos($key, ']') - strpos($key, '[') - 1);
				$data[$index]['fields'][$num]['values'][$row_index]['value'] = $value;
				$table = $head[$num]['table'];
				$fname = substr($key, strrpos($key, '[') + 1, -1);
				if (isset($line['_id']) AND !empty($existing[$table][$line['_id']][$row_index][$fname])) {
					$evalue = $existing[$table][$line['_id']][$row_index][$fname];
					$data[$index]['fields'][$num]['values'][$row_index]['existing'] = $evalue;
					if ($evalue === trim($value)) {
						$data[$index]['fields'][$num]['values'][$row_index]['identical'] = true;
					} else $identical = false;
				} else $identical = false;
			} else {
				if ($value) $value = trim($value);
				if (!$value) $value = zz_sync_null_value($value, $key, $def['fields']);
				$data[$index]['fields'][$num]['value'] = $value;
				$table = $def['table'];
				$fname = $key;
				if (isset($line['_id']) AND array_key_exists($fname, $existing[$table][$line['_id']])) {
					$evalue = $existing[$table][$line['_id']][$fname];
					$data[$index]['fields'][$num]['existing'] = $evalue;
					if (is_null($value) AND is_null($evalue)) {
						$data[$index]['fields'][$num]['identical'] = true;
					} elseif ($evalue === $value) {
						$data[$index]['fields'][$num]['identical'] = true;
					} else $identical = false;
				} else $identical = false;
			}
			unset($data[$index][$key]);
		}
		$data[$index]['identical'] = $identical;
		$data[$index]['no'] = $j;
		$data[$index]['index'] = $index;
		$data[$index]['script_url'] = zz_sync_script_url($setting);
	}
	foreach ($missing_fields as $field) {
		wrap_error(wrap_text('Field %s is missing in table definition.', ['values' => [$field]]));
	}
	
	$data = array_values($data);
	foreach ($head as $num => $field) {
		if (substr($num, 0, 1) === '_') continue;
		$data['head'][$num]['field_name'] = '';
		if (isset($field['table_title'])) {
			$data['head'][$num]['field_name'] .= $field['table_title'].'<br>';
		}
		$data['head'][$num]['field_name'] .= $field['title'] ?? $field['field_name'];
	}

	$text = wrap_template('sync', $data);
	return $text;
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
		list($raw, $i) = zz_sync_csv($setting);
		break;
	case 'sql':
		$raw = wrap_db_fetch($setting['source'], $setting['source_id_field_name']);
		break;
	}
	$existing = zz_sync_ids($raw, $setting['deletable'], 'numeric');

	$j = 0;
	foreach ($existing as $index => $record) {
		$i = 0;
		$j++;
		foreach ($record as $field_name => $value) {
			if ($field_name === $def['primary_key']) {
				$data[$index]['_id'] = $value;
			} else {
				$data['head'][$field_name]['field_name'] = $field_name;
				$data[$index]['fields'][$i]['value'] = $value;
				if (array_key_exists($field_name, $setting['deletable_script_path'])) {
					$data[$index]['fields'][$i]['my_script_url'] = $setting['deletable_script_path'][$field_name];
				}
				$i++;
			}
		}
		$data[$index]['no'] = $j;
		$data[$index]['script_url'] = zz_sync_script_url($setting);
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
 * create script URL
 *
 * @param array $setting
 * @return string
 */
function zz_sync_script_url($setting) {
	if ($setting['script_path']) return wrap_path($setting['script_path']);
	return $setting['script_url'];
}
