<?php

/**
 * zzform
 * Functions to create and manage identifiers, a way to identify records
 * apart from their ID in a more readable manner
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/** 
 * Creates identifier field that is unique
 * 
 * @param array $my_rec		$zz_tab[$tab][$rec]
 * @param string $db_table	Name of Table [dbname.table]
 * @param int $no		Number of field definition
 * @param array $post	main POST data
 * @return string identifier
 */
function zz_identifier($my_rec, $db_table = false, $post = [], $no = 0) {
	$conf = (!isset($my_rec['fields'][$no])) ? $my_rec : $my_rec['fields'][$no]['identifier'] ?? [];
	$conf_fields = $conf['fields'] = $my_rec['fields'][$no]['fields'] ?? [];
	$values = $conf['values'] ?? zz_identifier_values($conf, $my_rec, $post);
	if (!$values) return false;

	// read additional configuration from parameters
	if (!empty($conf['parameters']) AND !empty($values[$conf['parameters']])) {
		$conf = zz_identifier_configuration($conf, $values[$conf['parameters']]);
		unset($values[$conf['parameters']]);
		// field set might be different now, get new values
		if ($conf_fields !== $conf['fields'])
			$values = zz_identifier_values($conf, $my_rec, $post);
	}

	zz_identifier_defaults($conf);

	if ($db_table) {
		// there's a record, check if identifier is in write_once mode
		$field_name = $my_rec['fields'][$no]['field_name'];
		if (in_array($field_name, array_keys($values))) {
			$keep_idf = false;
			if ($conf['exists_function'])
				$keep_idf = $conf['exists_function']($values[$field_name], $values);
			elseif ($values[$field_name])
				$keep_idf = true;
			if ($keep_idf)
				// do not change anything if there has been a value set once and 
				// identifier is in vars array
				return $values[$field_name];
			else
				unset($values[$field_name]);
		}
		$conf['sql'] = zz_identifier_sql($db_table, $field_name, $my_rec, $conf);
	}

	if ($conf['random_hash'])
		return zz_identifier_random_hash($conf);

	$i = 0;
	$idf_arr = [];
	$len = 0;
	foreach ($values as $key => $var) {
		$i++;
		if (in_array($key, $conf['ignore'])) continue;
		if (!empty($conf['ignore_this_if'][$key])) {
			foreach ($conf['ignore_this_if'][$key] as $my_field_name) {
				if (!empty($values[$my_field_name])) continue 2;
			}
		}
		if (!empty($conf['ignore_this_if_identical'][$key])) {
			foreach ($conf['ignore_this_if_identical'][$key] as $my_field_name) {
				if ($values[$my_field_name] === $values[$key]) continue 2;
			}
		}
		if (!$var AND $var !== '0') {
			if (!empty($conf['empty'][$key])) {
				$var = $conf['empty'][$key];
			} else {
				if (is_array($conf['concat'])) {
					$idf_arr[] = ''; // in case concat is an array
				}
				continue;
			}
		}
		foreach ($conf['remove_strings'] as $remove_string) {
			if (strstr($var, $remove_string))
				$var = str_replace($remove_string, '', $var);
		}
		if ($conf['strip_tags']) $var = strip_tags($var);
		// remove everything after a line break
		if ($pos = strpos($var, "\r")) $var = substr($var, 0, $pos);
		if ($pos = strpos($var, "\n")) $var = substr($var, 0, $pos);
		// check for last element, if max_length is met
		if (!empty($my_rec['fields'][$no]['maxlength'])) {
			$remaining_len = $my_rec['fields'][$no]['maxlength'] - $len;
			if (!$conf['max_length']) {
				$conf['max_length'] = $remaining_len;
			} elseif ($conf['max_length'] > $remaining_len) {
				$conf['max_length'] = $remaining_len;
			}
		}
		if ($conf['max_length'] AND strlen($var) > $conf['max_length'] 
			AND $i === count($values)) {
			$vparts = explode(' ', $var);
			if (count($vparts) > 1) {
				// always use first part, even if it's too long
				$var = array_shift($vparts);
				// < and not <= because space is always added
				while (strlen($var.reset($vparts)) < $conf['max_length']) {
					$var .= ' '.array_shift($vparts);
				}
				// cut off if first word is too long
				$var = substr($var, 0, $conf['max_length']);
			} else {
				// there are no words, cut off in the middle of the word
				$var = substr($var, 0, $conf['max_length']);
			}
		}
		if ((strstr($var, '/') AND $i != count($values))
			OR $conf['slashes']) {
			// last var will be treated normally, other vars may inherit 
			// slashes from dir names
			$dir_vars = explode('/', $var);
			foreach ($dir_vars as $d_var) {
				if (!$d_var) continue;
				$my_var = wrap_filename(
					$d_var, $conf['forceFilename'], $conf['replace']
				);
				if ($conf['lowercase']) $my_var = strtolower($my_var);
				if ($conf['uppercase']) $my_var = strtoupper($my_var);
				$idf_arr[] = $my_var;
			}
		} else {
			$my_var = wrap_filename(
				$var, $conf['forceFilename'], $conf['replace']
			);
			if ($conf['lowercase']) $my_var = strtolower($my_var);
			if ($conf['uppercase']) $my_var = strtoupper($my_var);
			$idf_arr[] = $my_var;
		}
		$len += strlen($my_var);
	}
	if (empty($idf_arr)) return false;

	$idf = zz_identifier_concat($idf_arr, $conf['concat']);
	if ($conf['prefix']) $idf = $conf['prefix'].$idf;
	// start always?
	if ($conf['start_always'])
		$idf .= $conf['exists'].sprintf($conf['exists_format'], $conf['start']);
	// hash md5?
	if (!empty($conf['hash_md5']))
		$idf = md5($idf.date('Ymdhis'));

	if ($conf['function']) {
		switch (count($conf['function_parameter'])) {
		case 0:
			$idf = $conf['function']($idf);
			break;
		case 1:
			$idf = $conf['function']($conf['function_parameter'][0]);
			break;
		case 2:
			$idf = $conf['function']($conf['function_parameter'][0]
				, $conf['function_parameter'][1]
			);
			break;
		case 3:
			$idf = $conf['function']($conf['function_parameter'][0]
				, $conf['function_parameter'][1]
				, $conf['function_parameter'][2]
			);
			break;
		case 4:
			$idf = $conf['function']($conf['function_parameter'][0]
				, $conf['function_parameter'][1]
				, $conf['function_parameter'][2]
				, $conf['function_parameter'][3]
			);
			break;
		}
	}
	// ready, last checks
	if ($db_table) {
		// check whether identifier exists
		$idf = zz_identifier_exists(
			$idf, $conf['start'], $db_table, $field_name, $conf, $my_rec['fields'][$no]['maxlength']
		);
	}
	return $idf;
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
	$conf = zz_init_cfg('zz-fields[identifier]', $conf);

	// @todo do this in zz_init_cfg() via max_length = 1
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
 * @param string $db_table
 * @param string $field_name
 * @param array $my_rec
 * @param array $conf
 * @return string
 */
function zz_identifier_sql($db_table, $field_name, $my_rec, $conf) {
	$wheres = [];
	// identifier does not have to be unique, add where from other keys
	foreach ($conf['unique_with'] as $unique_field)
		$wheres[] = sprintf('%s = "%s"', $unique_field, zz_identifier_val('filetype_id', $my_rec, $my_rec['POST']));
	if ($wheres) {
		if ($conf['where']) $conf['where'] .= ' AND ';
		$conf['where'] .= implode(' AND ', $wheres);
	}
	$sql = 'SELECT %s
		FROM %s
		WHERE %s = "%%s"
		AND %s != %d
		%s';
	$sql = sprintf($sql
		, $my_rec['id']['field_name']
		, zz_db_table_backticks($db_table)
		, $field_name
		, $my_rec['id']['field_name']
		, $my_rec['POST'][$my_rec['id']['field_name']]
		, $conf['where'] ? ' AND '.$conf['where'] : ''
	);
	return $sql;
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
 * @param string $idf
 * @param mixed $i (integer or letter)
 * @param string $db_table [dbname.table]
 * @param string $field
 * @param array $conf
 * @param int $maxlen
 * @global array $zz_conf
 * @return string $idf
 */
function zz_identifier_exists($idf, $i, $db_table, $field, $conf, $maxlen = false) {
	global $zz_conf;
	static $existing = [];
	if (empty($existing[$zz_conf['id']][$db_table]))
		$existing[$zz_conf['id']][$db_table] = [];

	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	$sql = sprintf($conf['sql'], $idf);
	$records = zz_db_fetch($sql, $field, 'single value');
	if ($records OR in_array($idf, $existing[$zz_conf['id']][$db_table])) {
		$start = false;
		if (is_numeric($i) AND $i > 2) $start = true;
		elseif (!is_numeric($i) AND $i > 'b') $start = true;
		elseif ($conf['start_always']) $start = true;
		if ($start) {
			// with start_always, we can be sure, that a generated suffix exists
			// so we can safely remove it. 
			// for other cases, this is only true for $i > 2.
			if ($conf['exists']) {
				$idf = substr($idf, 0, strrpos($idf, $conf['exists']));
			} else {
				// remove last ending, might be 9 in case $i = 10 or
				// 'z' in case $i = 'aa' so make sure not to remove too much
				$j = $i;
				$j--;
				if ($j === $i) {
					// -- does not work with alphabet
					if (substr_count($j, 'a') === strlen($j)) {
						$j = substr($j, 0, -1);
					}
				}
				$idf = substr($idf, 0, -strlen($j));
			}
		}
		$suffix = $conf['exists'].sprintf($conf['exists_format'], $i);
		// in case there is a value for maxlen, make sure that resulting
		// string won't be longer
		if ($maxlen && strlen($idf.$suffix) > $maxlen) 
			$idf = substr($idf, 0, ($maxlen - strlen($suffix))); 
		$idf = $idf.$suffix;
		$i++;
		$idf = zz_identifier_exists(
			$idf, $i, $db_table, $field, $conf, $maxlen
		);
	}
	$existing[$zz_conf['id']][$db_table][] = $idf;
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
 * @todo Funktion ist nicht ganz korrekt, da sie auf unvaldierte 
 * 		Detaildatensätze zugreift. Problem: Hauptdatens. wird vor Detaildatens.
 * 		geprüft (andersherum geht wohl auch nicht)
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

			// todo: problem: subrecords are being validated after main record, 
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
	$sql = wrap_edit_sql($sql, 'WHERE', $id_fieldname.' = '.$id);
	$line = zz_db_fetch($sql);
	if ($fieldname) {
		if (isset($line[$fieldname])) return zz_return($line[$fieldname]);
		zz_return(false);
	} else {
		if ($line) zz_return($line);
		zz_return(false);
	}
}
