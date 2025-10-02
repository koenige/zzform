<?php

/**
 * zzform
 * Functions to create and manage identifiers, a way to identify records
 * apart from their ID in a more readable manner
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * initialize configuration and field values for identifier field
 *
 * @param array $my_rec	= $zz_tab[$tab][$rec]
 * @param string $db_table = Name of Table [dbname.table]
 * @param array $post = main POST data
 * @param int $no = Number of field definition
 * @return array
 */
function zz_identifier_prepare($my_rec, $db_table, $post, $no) {
	$field = $my_rec['fields'][$no];
	$field['idf_db_table'] = $db_table;

	$conf = $field['identifier'] ?? [];
	$conf['fields'] = $field['fields'] ?? [];
	if (!empty($conf['replace_fields'])) {
		foreach ($conf['replace_fields'] as $replace_field => $with_field) {
			$pos = array_search($replace_field, $conf['fields']);
			if ($pos !== false) $conf['fields'][$pos] = $with_field;
		}
	}
	$values = zz_identifier_values($conf, $my_rec, $post);
	if (!$values) return $field;
	foreach ($values as $key => $var)
		if ($var === '[TRANSLATION_DUMMY]') $values[$key] = '';

	// read additional configuration from parameters
	$conf_fields = $conf['fields'];
	if (!empty($conf['parameters']) AND !empty($values[$conf['parameters']])) {
		$conf = zz_identifier_configuration($conf, $values[$conf['parameters']]);
		unset($values[$conf['parameters']]);
		// field set might be different now, get new values
		if ($conf_fields !== $conf['fields'])
			$values = zz_identifier_values($conf, $my_rec, $post);
	}
	$field['idf_values'] = $values;

	zz_identifier_defaults($conf);
	$conf['max_length_field'] = $field['maxlength'] ?? NULL;
	$field['idf_conf'] = $conf;
	list($field['idf_conf']['sql'], $field['idf_conf']['sql_other'])
		= zz_identifier_sql($field, $my_rec);
	
	return $field;
}

/** 
 * Creates identifier field that is unique
 * 
 * @param array $field	= $zz_tab[$tab][$rec]['fields'][$no]
 * @return string identifier
 */
function zz_identifier($field) {
	if (!array_key_exists('idf_conf', $field)) return false;

	$conf = $field['idf_conf'];
	$values = $field['idf_values'];

	// check if identifier is in write_once mode
	if (in_array($field['field_name'], array_keys($values))) {
		$keep_idf = false;
		if ($conf['exists_function'])
			$keep_idf = $conf['exists_function']($values[$field['field_name']], $values);
		elseif ($values[$field['field_name']])
			$keep_idf = true;
		if ($keep_idf)
			// do not change anything if there has been a value set once and 
			// identifier is in vars array
			return $values[$field['field_name']];
		else
			unset($values[$field['field_name']]);
	}

	if ($conf['random_hash'])
		return zz_identifier_random_hash($conf);

	// @todo check if this is practical, this way, translated identifiers
	// will not be removed if translation is removed, to keep identifiers stable
	// move code before check for write_once mode if translated identifiers should be
	// removed even if said otherwise
	$values = zz_identifier_values_check($values, $conf);
	if (!$values) return false;

	$i = 0;
	$idf_arr = [];
	$len = 1;
	foreach ($values as $key => $var) {
		$i++;
		foreach ($conf['remove_strings'] as $remove_string) {
			if (strstr($var, $remove_string))
				$var = str_replace($remove_string, '', $var);
		}
		if ($conf['strip_tags']) $var = strip_tags($var);
		// remove everything after a line break
		if ($pos = strpos($var, "\r")) $var = substr($var, 0, $pos);
		if ($pos = strpos($var, "\n")) $var = substr($var, 0, $pos);
		// check for last element, if max_length is met
		if ($conf['max_length_field']) {
			$remaining_len = $conf['max_length_field'] - $len;
			if (!$conf['max_length']) {
				$conf['max_length'] = $remaining_len;
			} elseif ($conf['max_length'] > $remaining_len) {
				$conf['max_length'] = $remaining_len;
			}
		}
		if ($conf['max_length'] AND strlen($var) > $conf['max_length'] 
			AND $i === count($values)) {
			$var = zz_identifier_cut($var, $conf['max_length']);
		}
		if ((strstr($var, '/') AND $i != count($values))
			OR $conf['slashes']) {
			// last var will be treated normally, other vars may inherit 
			// slashes from dir names
			$dir_vars = explode('/', $var);
		} elseif ($var) {
			$dir_vars = [$var];
		} else {
			// no value, so remove concat elements for the non-existing value
			if (is_array($conf['concat'])) {
				$keys = array_keys($conf['concat']);
				if (array_key_exists($i - 1, $keys)) {
					$remove_key = $keys[$i - 1];
					unset($conf['concat'][$remove_key]);
				}
			}
			continue;
		}
		foreach ($dir_vars as $d_var) {
			if (!$d_var) continue;
			$my_var = wrap_filename(
				$d_var, $conf['forceFilename'], $conf['replace']
			);
			$idf_arr[] = $my_var;
			$len++;
			$len += strlen($my_var);
		}
	}
	if (empty($idf_arr)) return false;

	$idf = zz_identifier_concat($idf_arr, $conf['concat']);
	$idf = zz_identifier_cut($idf, $conf['max_length_field']);

	if ($conf['prefix']) $idf = $conf['prefix'].$idf;
	// start always?
	if ($conf['start_always'])
		$idf .= $conf['exists'].sprintf($conf['exists_format'], $conf['start']);
	// hash md5?
	if (!empty($conf['hash_md5']))
		$idf = md5($idf.date('Ymdhis'));
	// function
	if ($conf['function']) {
		$params = array_merge([$idf], $conf['function_parameter']);
		$idf = call_user_func_array($conf['function'], $params);
	}
	// no - at the beginning of identifier
	if (strlen($idf) > 1) $idf = ltrim($idf, '-');
	if (!$idf) { // all --
		$idf = wrap_setting('format_filename_empty') ?? $conf['forceFilename'];
	}
	if ($conf['lowercase']) $idf = strtolower($idf);
	if ($conf['uppercase']) $idf = strtoupper($idf);
	// check whether identifier already exists
	$idf = zz_identifier_exists($idf, $field);
	return $idf;
}

/**
 * cut identifier length if it is too long
 *
 * @param string $str
 * @param int $max_length
 * @return string
 */
function zz_identifier_cut($str, $max_length) {
    if (mb_strlen($str) <= $max_length)
    	return $str;

    $cut_point = $max_length;
    $break_chars = [' ', '-'];

    while ($cut_point > 0 && !in_array(mb_substr($str, $cut_point, 1), $break_chars))
        $cut_point--;
    if ($cut_point === 0)
        return mb_substr($str, 0, $max_length);
    return mb_substr($str, 0, $cut_point);
}

/**
 * read configuration parameters from a field
 *
 * @param array $vars
 * @param string $parameters
 * @return array
 */
function zz_identifier_configuration($vars, $parameters) {
	parse_str($parameters, $parameters);
	if (empty($parameters['identifier'])) return $vars;
	foreach ($parameters['identifier'] as $key => $value)
		$vars[$key] = wrap_setting_value($value);
	return $vars;
}

/**
 * set defaults, correct types
 *
 * @param array $conf
 */
function zz_identifier_defaults(&$conf) {
	$conf = zz_configuration('zz-fields[identifier]', $conf);

	// @todo do this in zz_configuration() via max_length = 1
	$conf_max_length_1 = ['forceFilename', 'exists'];
	foreach ($conf_max_length_1 as $key)
		$conf[$key] = substr($conf[$key], 0, 1);

	$conf_arrays_in_arrays = ['ignore_this_if', 'ignore_this_if_identical'];
	foreach ($conf_arrays_in_arrays as $key) {
		foreach ($conf[$key] as $subkey => $value) {
			if (!is_array($value)) $conf[$key][$subkey] = [$value];
		}
	}
}

/**
 * concatenates values for identifiers
 *
 * @param array $data values to concatencate
 * @param mixed $concat (string or array)
 * @return string
 */
function zz_identifier_concat($data, $concat) {
	if (!is_array($concat)) return implode($concat, $data);
	
	// idf 0 con 0 idf 1 con 1 idf 2 con 1 ...
	$idf = '';
	if (isset($concat['last'])) {
		$last_concat = $concat['last'];
		unset($concat['last']);
	}
	if (isset($concat['repeat'])) {
		while (count($data) > count($concat)) {
			array_unshift($concat, $concat['repeat']);
		}
	}
	foreach ($data as $key => $value) {
		if (!$value) continue;
		if ($idf) {
			if ($key > 1 AND $key == count($data) - 1 AND isset($last_concat)) {
				// last one, but not first one
				$idf .= $last_concat;
			} else {
				// normal order, take actual last one if no other is left
				// add concat separator 0, 1, ...
				// might be '', therefore we use isset
				if (isset($concat[$key-1])) {
					$idf .= $concat[$key-1];
				} else {
					$idf .= $concat[count($concat)-1];
				}
			}
		}
		$idf .= $value;
	}
	return $idf;
}

/**
 * create SQL query to check existing records
 *
 * @param array $field
 * @param array $my_rec
 * @return array
 */
function zz_identifier_sql($field, $my_rec) {
	$wheres = [];
	// identifier does not have to be unique, add where from other keys
	foreach ($field['idf_conf']['unique_with'] as $unique_field)
		$wheres[] = sprintf('%s = "%s"'
			, $unique_field
			, zz_identifier_val($unique_field, $my_rec, $my_rec['POST'])
		);
	if ($wheres) {
		if ($field['idf_conf']['where'])
			$field['idf_conf']['where'] .= ' AND ';
		$field['idf_conf']['where'] .= implode(' AND ', $wheres);
	}
	$sql = 'SELECT %s AS _id
		FROM %s
		WHERE %s = "%%s"
		AND %s != %d
		%s';
	$sql = sprintf($sql
		, $my_rec['id']['field_name']
		, zz_db_table_backticks($field['idf_db_table'])
		, $field['field_name']
		, $my_rec['id']['field_name']
		, $my_rec['POST'][$my_rec['id']['field_name']]
		, $field['idf_conf']['where'] ? ' AND '.$field['idf_conf']['where'] : ''
	);

	// for translations
	$sql_other = 'SELECT %s AS _id
		FROM %s
		WHERE %s = "%%s"
		%s';
	$sql_other = sprintf($sql_other
		, $my_rec['id']['field_name']
		, zz_db_table_backticks($field['idf_db_table'])
		, $field['field_name']
		, $field['idf_conf']['where'] ? ' AND '.$field['idf_conf']['where'] : ''
	);

	return [$sql, $sql_other];
}

/**
 * get queries for translated identifiers
 * here, not only the main identifier needs to be unique but tha values both
 * in the main table and in the translations table need to be
 *
 * @param array $my_tab $zz_tab[$tab]
 * @param array $field field definition, passed by reference so it can get values from later loops
 * @return void
 */
function zz_identifier_sql_translated($my_tab, &$field) {
	static $queries = [];
	static $tables = [];

	if (empty($field['translate_subtable'])) return;
	$db_table = sprintf('%s.%s', $my_tab['db_name'], $my_tab['table']);

	if (!empty($field['translate_subtable'])) {
		$t_key = sprintf('%s-%s', $field['translate_subtable'], $field['translate_subtable_field']);
		$tables[$t_key] = $db_table;
	} else {
		$t_key = sprintf('%s-%s', $field['subtable_no'], $field['field_no']);
		if (!array_key_exists($t_key, $tables)) $tables[$t_key] = '';
	}
	$queries[$t_key][$db_table] = $field['idf_conf']['sql_other'];
	$field['idf_conf']['sql_queries'] = &$queries[$t_key];
	$field['idf_conf']['sql_source_table'] = &$tables[$t_key];
}

/**
 * set random hash as identifier
 *
 * @param array $conf
 * @return string
 */
function zz_identifier_random_hash($conf) {
	$duplicate = true;
	while ($duplicate) {
		if ($conf['random_hash_charset'])
			$hash = wrap_random_hash($conf['random_hash'], $conf['random_hash_charset']);
		else
			$hash = wrap_random_hash($conf['random_hash']);
		if ($conf['sql']) {
			$sql = sprintf($conf['sql'], $hash);
			$duplicate = wrap_db_fetch($sql, '', 'single value');
		} else {
			// no check possible
			$duplicate = false;
		}
	}
	return $hash;
}

/**
 * check if an identifier already exists in database, add nuermical suffix
 * until an adequate identifier exists  (john-doe, john-doe-2, john-doe-3 ...)
 *
 * @param array $field
 * @param array $conf
 * @global array $zz_conf
 * @return string $idf
 */
function zz_identifier_exists($idf, $field) {
	global $zz_conf;
	static $existing = [];

	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	if ($field['idf_conf']['sql_queries']) {
		$queries = [];
		foreach ($field['idf_conf']['sql_queries'] as $table_key => $sql) {
			if ($table_key === $field['idf_db_table'])
				// query excluding own record
				$queries[] = $field['idf_conf']['sql'];
			else
				// query without excluding own record
				$queries[] = $sql;
		}
		$sql = implode(' UNION ', $queries);
		$sql = sprintf($sql, $idf, $idf);
		$table_key = $field['idf_conf']['sql_source_table'];
	} else {
		$sql = sprintf($field['idf_conf']['sql'], $idf);
		$table_key = $field['idf_db_table'];
	}
	if (empty($existing[$zz_conf['id']][$table_key]))
		$existing[$zz_conf['id']][$table_key] = [];

	$records = zz_db_fetch($sql, $field['field_name'], 'single value');
	if ($records OR in_array($idf, $existing[$zz_conf['id']][$table_key])) {
		$start = false;
		if (is_numeric($field['idf_conf']['start']) AND $field['idf_conf']['start'] > 2) $start = true;
		elseif (!is_numeric($field['idf_conf']['start']) AND $field['idf_conf']['start'] > 'b') $start = true;
		elseif ($field['idf_conf']['start_always']) $start = true;
		if ($start) {
			// with start_always, we can be sure, that a generated suffix exists
			// so we can safely remove it. 
			// for other cases, this is only true for $field['idf_conf']['start'] > 2.
			if ($field['idf_conf']['exists']) {
				$idf = substr($idf, 0, strrpos($idf, $field['idf_conf']['exists']));
			} else {
				// remove last ending, might be 9 in case $i = 10 or
				// 'z' in case $field['idf_conf']['start'] = 'aa' so make sure not to remove too much
				$j = $field['idf_conf']['start'];
				$j--;
				if ($j === $field['idf_conf']['start']) {
					// -- does not work with alphabet
					if (substr_count($j, 'a') === strlen($j)) {
						$j = substr($j, 0, -1);
					}
				}
				$idf = substr($idf, 0, -strlen($j));
			}
		}
		$suffix = $field['idf_conf']['exists'].sprintf(
			$field['idf_conf']['exists_format'], $field['idf_conf']['start']
		);
		// in case there is a value for maxlen, make sure that resulting
		// string won't be longer
		if ($field['idf_conf']['max_length_field'] && strlen($idf.$suffix) > $field['idf_conf']['max_length_field']) 
			$idf = substr($idf, 0, ($field['idf_conf']['max_length_field'] - strlen($suffix))); 
		$idf = $idf.$suffix;
		$field['idf_conf']['start']++;
		$idf = zz_identifier_exists($idf, $field);
	}
	$existing[$zz_conf['id']][$table_key][] = $idf;
	return zz_return($idf);
}

/**
 * extracts substring information from field_name
 *
 * @param string $field_name (e. g. test{0,4})
 * @return array
 *		string $field_name (e. g. test)
 *		string $substr (e. g. 0,4)
 */
function zz_identifier_substr($field_name) {
	if (!strstr($field_name, '}')) return [$field_name, ''];
	if (!strstr($field_name, '{')) return [$field_name, ''];
	preg_match('/{(.+)}$/', $field_name, $substr);
	if (!$substr) return [$field_name, ''];
	$field_name = preg_replace('/{.+}$/', '', $field_name);
	$substr = $substr[1];
	return [$field_name, $substr];
}

/**
 * gets all variables for identifier field to use them in zz_identifier()
 *
 * @param array $conf
 * @param array $my_rec = $zz_tab[$tab][$rec]
 * 		$my_rec['fields'][$f]['fields']:
 * 		possible syntax: fieldname[sql_fieldname] or tablename.fieldname or 
 *		fieldname; index not numeric but string: name of function to call
 * @param array $main_post POST values of $zz_tab[0][0]['POST']
 * @return array $values
 * @todo The function is not entirely correct because it accesses unvalidated
 * 		detail records. Problem: Main data is checked before detail data
 *		(it probably doesn't work the other way around either).
 */ 
function zz_identifier_values($conf, $my_rec, $main_post) {
	$values = [];
	foreach ($conf['fields'] as $field_name) {
 		// get full field_name with {}, [] and . as index
 		$index = $field_name;
		// check for substring parameter
		list($field_name, $substr) = zz_identifier_substr($field_name);
		// get value
		$values[$index] = zz_identifier_val($field_name, $my_rec, $main_post, $conf['preferred'] ?? []);
		if (!$substr) continue;
		eval ($line ='$values[$index] = substr($values[$index], '.$substr.');');
	}
	foreach ($conf['fields'] as $function => $field_name) {
		if (is_numeric($function)) continue;
		if (function_exists($function))
			$values[$field_name] = $function($values[$field_name], $values);
	}
	return $values;
}

/**
 * gets a single variable for an identifier field
 *
 * @param string $field_name
 * @param array $my_rec $zz_tab[$tab][$rec]
 * @param array $main_post POST values of $zz_tab[0][0]['POST']
 * @param array $preferred
 * @return string $value
 */
function zz_identifier_val($field_name, $my_rec, $main_post, $preferred = []) {
	// 1. it's just a field name of the main record
	if (!empty($my_rec['POST'][$field_name])
		OR (isset($my_rec['POST'][$field_name]) AND $my_rec['POST'][$field_name] === '0'))
		return $my_rec['POST'][$field_name];

	// 2. it's a field name of a detail record
	$field_names = false;
	if (strstr($field_name, '.')) {
		list($table, $field_name) = explode('.', $field_name);
		if (!isset($my_rec['POST'][$table])) return false;

		$no = 0;
		if (!empty($preferred[$table])) {
			$key_field_name = key($preferred[$table]);
			$values = $preferred[$table][$key_field_name];
			$priority = count($values) + 1;
			foreach ($my_rec['POST'][$table] as $index => $fields) {
				if (!array_key_exists($key_field_name, $fields)) continue;
				if (!in_array($fields[$key_field_name], $values)) continue;
				if (array_search($fields[$key_field_name], $values) >= $priority) continue;
				$priority = array_search($fields[$key_field_name], $values);
				$no = $index;
			}
		}

		if (!empty($my_rec['POST'][$table][$no][$field_name])) {
			// this might not be correct, because it ignores the table_name
			$value = $my_rec['POST'][$table][$no][$field_name]; 

			// @todo problem: subrecords are being validated after main record, 
			// so we might get invalid results
			$field = zz_get_subtable_fielddef($my_rec['fields'], $table);
			if ($field) {
				$type = zz_get_fielddef($field['fields'], $field_name, 'type');
				if ($type === 'date') {
					$value = zz_check_date($value); 
					$value = str_replace('-00', '', $value); 
					$value = str_replace('-00', '', $value); 
				}
			}
			return $value;
		}
		$field_names = zz_split_fieldname($field_name);
		if (!$field_names) return false;
		if (empty($my_rec['POST'][$table][$no][$field_names[0]])) return false;
		
		$field = zz_get_subtable_fielddef($my_rec['fields'], $table);
		if (!$field) return false;
		
		$id = $my_rec['POST'][$table][$no][$field_names[0]];
		$sql = zz_get_fielddef($field['fields'], $field_names[0], 'sql');
		return zz_identifier_values_db($sql, $id, $field_names[1]);
	}
	
	// 3. it's a field name of a main or a detail record
	if (!$field_names)
		$field_names = zz_split_fieldname($field_name);
	if (!$field_names) return false;
	
	if ($field_names[0] === '0') {
		if (empty($main_post[$field_names[1]])) return false;
		if (is_array($main_post[$field_names[1]])) return false;
		$value = $main_post[$field_names[1]];
		// remove " "
		if (substr($value, 0, 1)  === '"' AND substr($value, -1) === '"')
			$value = substr($value, 1, -1);
		return $value;
	} 
	if (empty($my_rec['POST'][$field_names[0]])) return false;

	$id = $my_rec['POST'][$field_names[0]];
	$sql = zz_get_fielddef($my_rec['fields'], $field_names[0], 'sql');
	return zz_identifier_values_db($sql, $id, $field_names[1]);
}

/**
 * check if values should be ignored or are required
 *
 * @param array $values
 * @param array $conf
 * @return array
 */
function zz_identifier_values_check($values, $conf) {
	foreach ($values as $key => $var) {
		// remove ignored values
		if (in_array($key, $conf['ignore'])) {
			unset($values[$key]);
			continue;
		}
		if (!empty($conf['ignore_this_if'][$key])) {
			foreach ($conf['ignore_this_if'][$key] as $my_field_name) {
				if (!empty($values[$my_field_name])) {
					unset($values[$key]);
					continue 2;
				}
			}
		}
		if (!empty($conf['ignore_this_if_identical'][$key])) {
			foreach ($conf['ignore_this_if_identical'][$key] as $my_field_name) {
				if ($values[$my_field_name] === $values[$key]) {
					unset($values[$key]);
					continue 2;
				}
			}
		}

		// check if value is not empty
		if (!$var AND $var !== '0') {
			if (!empty($conf['empty'][$key])) {
				$values[$key] = $conf['empty'][$key];
			} else {
				// check if value is required
				// this removes identifier even if there is an existing one that
				// should be unchanged; important for translations
				if ($conf['values_required'] === true) return [];
				if (is_array($conf['values_required']) AND !empty($conf['values_required'][$key])) return [];
				// remove or keep empty value, in case concat is an array
				if (is_array($conf['concat'])) {
					$values[$key] = '';
				} else {
					unset($values[$key]);
				}
			}
		}
	}
	return $values;
}

/**
 * update redirects table after identifier update or deletion of record
 *
 * @param string $type
 * @param array $ops
 * @param array $zz_tab
 * @return bool true = redirect was added
 */
function zz_identifier_redirect($ops, $zz_tab) {
	$action = $ops['return'][0]['action'];
	
	if (!in_array($action, ['update', 'delete'])) return false;
	foreach ($zz_tab[0]['set_redirect'] as $redirect) {
		if (!is_array($redirect)) {
			$old = $redirect;
			$new = $redirect;
		} elseif (!is_array($redirect['old'])) {
			$old = $redirect['old'];
			$new = $redirect['new'];
			if (isset($redirect['field_name'])) {
				$field_name = $redirect['field_name'];
			}
		} else {
			// @todo $field_name is actually defined twice
			// get data from record_old that is not available yet in record_new
			// @todo solve differently; currently a change of e. g. a publication category
			// will not change the corresponding path
			// maybe make full record available in 'new', too?
			$record_new = $ops['record_new'][0] + $ops['record_old'][0];
			$old = zz_makelink($redirect['old'], $ops['record_old'][0]);
			$new = zz_makelink($redirect['new'], $record_new);
		}
		if (empty($field_name)) {
			foreach ($zz_tab[0][0]['fields'] as $field) {
				if ($field['type'] !== 'identifier') continue;
				$field_name = $field['field_name'];
				break;
			}
		}
		if (empty($field_name)) {
			zz_error_log(['msg_dev' => 'Missing field name for redirect']);
			continue;
		}
		if ($action === 'update') {
			if (empty($ops['record_diff'][0][$field_name])) continue;
			if ($ops['record_diff'][0][$field_name] != 'diff') continue;
		}
		if (empty($ops['record_old'][0][$field_name])) continue;
		$old = sprintf($old, $ops['record_old'][0][$field_name]);
		$sql = 'SELECT redirect_id FROM /*_PREFIX_*/redirects WHERE old_url = "%s"';
		$sql = sprintf($sql, wrap_db_escape($old));
		$redirect_id = zz_db_fetch($sql, '', 'single value');

		$values = [];
		if ($redirect_id) {
			$values['action'] = 'update';
			$values['POST']['redirect_id'] = $redirect_id;
		} else {
			$values['action'] = 'insert';
			$values['POST']['old_url'] = $old;
		}
		switch ($action) {
		case 'update':
			$values['POST']['new_url'] = sprintf($new, $ops['record_new'][0][$field_name]);
			$values['POST']['code'] = 301;
			break;
		case 'delete':
			$values['POST']['new_url'] = '-';
			$values['POST']['code'] = 410;
			break;
		default:
			return false;
		}
		if (is_array($redirect) AND count($redirect) > 3) {
			foreach ($redirect as $field_name => $value) {
				if (in_array($field_name, ['old', 'new', 'field_name'])) continue;
				if ($value !== $field_name) {
					$values['POST'][$field_name] = $value;
				} elseif ($action === 'delete') {
					$values['POST'][$field_name] = $ops['record_old'][0][$field_name];
				} else {
					$values['POST'][$field_name] = $ops['record_new'][0][$field_name];
				}
			}
		}
		if (wrap_setting('multiple_websites')) {
			if (empty($values['POST']['website_id']))
				$values['POST']['website_id'] = wrap_setting('website_id');
		}

		zzform_multi('redirects', $values);
	}
	return true;
}

/** 
 * Gets values for identifier from database
 * 
 * @param string $sql SQL query
 * @param int $id record ID
 * @param string $fieldname (optional) if set, returns just fieldname
 * @return mixed array: full line from database, string: just field if fieldname
 */
function zz_identifier_values_db($sql, $id, $fieldname = false) {
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	// remove whitespace
	$sql = preg_replace("/\s+/", " ", $sql); // first blank needed for SELECT
	$sql_tokens = explode(' ', trim($sql)); // remove whitespace
	$unwanted = ['SELECT', 'DISTINCT'];
	foreach ($sql_tokens as $token) {
		if (!in_array($token, $unwanted)) {
			$id_fieldname = trim($token);
			if (substr($id_fieldname, -1) === ',')
				$id_fieldname = substr($id_fieldname, 0, -1);
			break;
		}
	}
	$sql = wrap_edit_sql($sql, 'WHERE', sprintf('%s = %d', $id_fieldname, $id));
	$line = zz_db_fetch($sql);
	if ($fieldname) {
		if (isset($line[$fieldname])) return zz_return($line[$fieldname]);
		zz_return(false);
	} else {
		if ($line) zz_return($line);
		zz_return(false);
	}
}

/**
 * prepare translations for identifiers
 *
 * @param array $fields
 * @param array $identifier_fields
 *
 */
function zz_identifier_translation_fields($fields, $identifier_fields) {
	if (empty($_POST)) return;

	foreach ($identifier_fields as $no => $sub_no) {
		$field = $fields[$no]['fields'][$sub_no];
		$table_name = $fields[$no]['table_name'];
		
		$post_values = [];
		// get values
		foreach ($field['fields'] as $field_name) {
			if ($pos = strpos($field_name, '{'))
				$field_name = substr($field_name, 0, $pos);
			$values = zz_identifier_translation_find($fields, $field_name, $no);
			$post_values[$field_name] = $values;
		}
		// put values together
		$post = [];
		foreach ($post_values as $field_name => $field_values) {
			if (!is_array($field_values)) continue;
			foreach ($field_values as $record_no => $record_values) {
				$post[$record_values['language_id']][$field_name] = $record_values['translation'];
				$post[$record_values['language_id']]['language_id'] = $record_values['language_id'];
				$post[$record_values['language_id']]['translation'] = '[TRANSLATION_DUMMY]';
			}
		}
		foreach ($post_values as $field_name => $field_values) {
			if (is_array($field_values)) continue;
			// set value for translation to avoid record being ignored
			if ($field_name === 'translation' AND !$field_values)
				$field_values = '[TRANSLATION_DUMMY]';
			foreach (array_keys($post) as $language_id)
				$post[$language_id][$field_name] = $field_values;
		}
		if (!array_key_exists($table_name, $_POST)) {
			$_POST[$table_name] = array_values($post);
		} else {
			foreach ($_POST[$table_name] as $index => $line) {
				if (array_key_exists('translation_id', $line)) {
					// get language ID
					$sql = wrap_edit_sql($fields[$no]['sql'], 'WHERE',
						sprintf('translation_id = %d', $line['translation_id'])
					);
					$line = wrap_db_fetch($sql);
				}
				if (!array_key_exists('language_id', $line)) {
					unset($_POST[$table_name][$index]);
					continue;
				} elseif (array_key_exists($line['language_id'], $post)) {
					$_POST[$table_name][$index] = array_merge($post[$line['language_id']], $line);
					// set value for translation to avoid record being ignored
					if (empty($_POST[$table_name][$index]['translation']))
						$_POST[$table_name][$index]['translation'] = '[TRANSLATION_DUMMY]';
					unset($post[$line['language_id']]);
				}
			}
			$_POST[$table_name] = array_merge($_POST[$table_name], $post);
		}
	}
}

/**
 * get translation or direct value of field
 *
 * @param array $fields
 * @param string $field
 * @param int $own_no own field no, to avoid recursion
 * @return mixed
 */
function zz_identifier_translation_find($fields, $field_name, $own_no) {
	foreach ($fields as $no => $field) {
		$field_identifier = zzform_field_identifier($field);
		if ($field_identifier !== $field_name) continue;
		if (array_key_exists('translate_subtable', $field)) {
			if ($field['translate_subtable'] === $own_no) return '';
			return $_POST[$fields[$field['translate_subtable']]['table_name']] ?? '';
		}
		$value = $_POST[$field_name] ?? '';
		if (!$value) return $value;
		if ($field['type'] === 'date' OR ($field['type_detail'] ?? '') === 'date')
			$value = zz_check_date($value);
		return $value;
	}
}
