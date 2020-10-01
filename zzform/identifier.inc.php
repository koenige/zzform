<?php

/**
 * zzform
 * Functions to create and manage identifiers, a way to identify records
 * apart from their ID in a more readable manner
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2020 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/** 
 * Creates identifier field that is unique
 * 
 * @param array $vars pairs of field_name => value
 * @param array $conf	Configuration for how to handle the strings
 *		'forceFilename' ('-'); value which will be used for replacing spaces and 
 *			unknown letters
 *		'concat' ('.'); string used for concatenation of variables. might be 
 *			array, values are used in the same order they appear in the array
 *		'exists' ('.'); string used for concatenation if identifier exists
 *		'lowercase' (true); false will not transform all letters to lowercase
 *		'uppercase' (false); true will transform all letters to uppercase
 *		'slashes' (false); true = slashes will be preserved
 *		'where' (false) WHERE-condition to be appended to query that checks 
 *			existence of identifier in database 
 *		'hash_md5' (false); true = hash will be created from field values and
 *			timestamp
 *		array 'replace' (false); key => value; characters in key will be
 *			replaced by value
 *		'function' (false); name of function that identifier will go through finally
 *		'function_parameter' (false); single function parameter to pass to function
 * @param array $my_rec		$zz_tab[$tab][$rec]
 * @param string $db_table	Name of Table [dbname.table]
 * @param int $field		Number of field definition
 * @return string identifier
 */
function zz_identifier($vars, $conf, $my_rec = false, $db_table = false, $field = false) {
	if (empty($vars)) return false;
	if ($my_rec AND $field AND $db_table) {
		// there's a record, check if identifier is in write_once mode
		$field_name = $my_rec['fields'][$field]['field_name'];
		if (in_array($field_name, array_keys($vars))) {
			$keep_idf = false;
			if (!empty($conf['exists_function'])) {
				$keep_idf = $conf['exists_function']($vars[$field_name], $vars);
			} elseif ($vars[$field_name]) {
				$keep_idf = true;
			}
			if ($keep_idf) {
				// do not change anything if there has been a value set once and 
				// identifier is in vars array
				return $vars[$field_name];
			} else {
				unset ($vars[$field_name]);
			}
		}
	}
	
	// set defaults, correct types
	$default_configuration = [
		'forceFilename' => '-', 'concat' => '.', 'exists' => '.',
		'lowercase' => true, 'slashes' => false, 'replace' => [],
		'hash_md5' => false, 'ignore' => [], 'max_length' => 36,
		'ignore_this_if' => [], 'empty' => [], 'uppercase' => false,
		'function' => false, 'function_parameter' => false,
		'unique_with' => [], 'where' => '', 'strip_tags' => false,
		'exists_format' => '%s', 'ignore_this_if_identical' => []
	];
	foreach ($default_configuration as $key => $value) {
		if (!isset($conf[$key])) $conf[$key] = $value;
	}
	$conf_max_length_1 = ['forceFilename', 'exists'];
	foreach ($conf_max_length_1 as $key) {
		$conf[$key] = substr($conf[$key], 0, 1);
	}
	$conf_arrays = ['ignore', 'unique_with'];
	foreach ($conf_arrays as $key) {
		if (!is_array($conf[$key])) $conf[$key] = [$conf[$key]];
	}
	$conf_arrays_in_arrays = ['ignore_this_if', 'ignore_this_if_identical'];
	foreach ($conf_arrays_in_arrays as $key) {
		foreach ($conf[$key] as $subkey => $value) {
			if (!is_array($value)) $conf[$key][$subkey] = [$value];
		}
	}
	$wheres = [];
	foreach ($conf['unique_with'] as $unique_field) {
		// identifier does not have to be unique, add where from other keys
		$wheres[] = sprintf('%s = "%s"', $unique_field, zz_identifier_var('filetype_id', $my_rec, $my_rec['POST']));
	}
	if ($wheres) {
		if ($conf['where']) $conf['where'] .= ' AND ';
		$conf['where'] .= implode(' AND ', $wheres);
	}

	$i = 0;
	$idf_arr = [];
	$len = 0;
	foreach ($vars as $key => $var) {
		$i++;
		if (in_array($key, $conf['ignore'])) continue;
		if (!empty($conf['ignore_this_if'][$key])) {
			foreach ($conf['ignore_this_if'][$key] as $my_field_name) {
				if (!empty($vars[$my_field_name])) continue 2;
			}
		}
		if (!empty($conf['ignore_this_if_identical'][$key])) {
			foreach ($conf['ignore_this_if_identical'][$key] as $my_field_name) {
				if ($vars[$my_field_name] === $vars[$key]) continue 2;
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
		if ($conf['strip_tags']) $var = strip_tags($var);
		// remove everything after a line break
		if ($pos = strpos($var, "\r")) $var = substr($var, 0, $pos);
		if ($pos = strpos($var, "\n")) $var = substr($var, 0, $pos);
		// check for last element, if max_length is met
		if ($my_rec AND !empty($my_rec['fields'][$field]['maxlength'])) {
			$remaining_len = $my_rec['fields'][$field]['maxlength'] - $len;
			if (!$conf['max_length']) {
				$conf['max_length'] = $remaining_len;
			} elseif ($conf['max_length'] > $remaining_len) {
				$conf['max_length'] = $remaining_len;
			}
		}
		if ($conf['max_length'] AND strlen($var) > $conf['max_length'] 
			AND $i === count($vars)) {
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
		if ((strstr($var, '/') AND $i != count($vars))
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
	if (!empty($conf['prefix'])) $idf = $conf['prefix'].$idf;
	// start value, if idf already exists
	$i = !empty($conf['start']) ? $conf['start'] : 2;
	// start always?
	if (!empty($conf['start_always'])) $idf .= $conf['exists'].sprintf($conf['exists_format'], $i);
	else $conf['start_always'] = false;
	// hash md5?
	if (!empty($conf['hash_md5'])) {
		$idf = md5($idf.date('Ymdhis'));
	}
	if (!empty($conf['function'])) {
		if (!empty($conf['function_parameter'])) {
			if (is_array($conf['function_parameter'])) {
				switch (count($conf['function_parameter'])) {
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
				
			} else {
				$idf = $conf['function']($conf['function_parameter']);
			}
		} else {
			$idf = $conf['function']($idf);
		}
	}
	// ready, last checks
	if ($my_rec AND $field AND $db_table) {
		// check whether identifier exists
		$idf = zz_identifier_exists(
			$idf, $i, $db_table, $field_name, $my_rec['id']['field_name'],
			$my_rec['POST'][$my_rec['id']['field_name']], $conf,
			$my_rec['fields'][$field]['maxlength']
		);
	}
	return $idf;
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
 * check if an identifier already exists in database, add nuermical suffix
 * until an adequate identifier exists  (john-doe, john-doe-2, john-doe-3 ...)
 *
 * @param string $idf
 * @param mixed $i (integer or letter)
 * @param string $db_table [dbname.table]
 * @param string $field
 * @param string $id_field
 * @param string $id_value
 * @param array $conf
 * @param int $maxlen
 * @global array $zz_conf
 * @return string $idf
 */
function zz_identifier_exists($idf, $i, $db_table, $field, $id_field, $id_value, $conf, $maxlen = false) {
	global $zz_conf;
	static $existing;
	if (empty($existing[$db_table])) $existing[$db_table] = [];

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$sql = 'SELECT %s FROM %s
		WHERE %s = "%s"
		AND %s != %d
		%s';
	$sql = sprintf($sql,
		$field, zz_db_table_backticks($db_table),
		$field, $idf, $id_field, $id_value,
		$conf['where'] ? ' AND '.$conf['where'] : ''
	);
	$records = zz_db_fetch($sql, $field, 'single value');
	if ($records OR in_array($idf, $existing[$db_table])) {
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
			$idf, $i, $db_table, $field, $id_field, $id_value, $conf, $maxlen
		);
	}
	$existing[$db_table][] = $idf;
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
 * @param array $my_rec = $zz_tab[$tab][$rec]
 * 		$my_rec['fields'][$f]['fields']:
 * 		possible syntax: fieldname[sql_fieldname] or tablename.fieldname or 
 *		fieldname; index not numeric but string: name of function to call
 * @param int $f = $zz['fields'][n]
 * @param array $main_post POST values of $zz_tab[0][0]['POST']
 * @return array $values
 * @todo Funktion ist nicht ganz korrekt, da sie auf unvaldierte 
 * 		Detaildatensätze zugreift. Problem: Hauptdatens. wird vor Detaildatens.
 * 		geprüft (andersherum geht wohl auch nicht)
 */ 
function zz_identifier_vars($my_rec, $f, $main_post) {
	$values = [];
	foreach ($my_rec['fields'][$f]['fields'] as $field_name) {
 		// get full field_name with {}, [] and . as index
 		$index = $field_name;

		// check for substring parameter
		list($field_name, $substr) = zz_identifier_substr($field_name);
		// get value
		$values[$index] = zz_identifier_var($field_name, $my_rec, $main_post);

		if (!$substr) continue;
		eval ($line ='$values[$index] = substr($values[$index], '.$substr.');');
	}
	foreach ($my_rec['fields'][$f]['fields'] as $function => $field_name) {
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
 * @return string $value
 */
function zz_identifier_var($field_name, $my_rec, $main_post) {
	// 1. it's just a field name of the main record
	if (!empty($my_rec['POST'][$field_name])
		OR (isset($my_rec['POST'][$field_name]) AND $my_rec['POST'][$field_name] === '0'))
		return $my_rec['POST'][$field_name];

	// 2. it's a field name of a detail record
	$field_names = false;
	if (strstr($field_name, '.')) {
		list($table, $field_name) = explode('.', $field_name);
		if (!isset($my_rec['POST'][$table])) return false;

		if (!empty($my_rec['POST'][$table][0][$field_name])) {
			// this might not be correct, because it ignores the table_name
			$value = $my_rec['POST'][$table][0][$field_name]; 

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
		if (empty($my_rec['POST'][$table][0][$field_names[0]])) return false;
		
		$field = zz_get_subtable_fielddef($my_rec['fields'], $table);
		if (!$field) return false;
		
		$id = $my_rec['POST'][$table][0][$field_names[0]];
		$sql = zz_get_fielddef($field['fields'], $field_names[0], 'sql');
		return zz_identifier_vars_db($sql, $id, $field_names[1]);
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
	return zz_identifier_vars_db($sql, $id, $field_names[1]);
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
	global $zz_setting;

	$action = $ops['return'][0]['action'];
	
	if (!in_array($action, ['update', 'delete'])) return false;
	foreach ($zz_tab[0]['set_redirect'] as $redirect) {
		if (!is_array($redirect)) {
			$old = $redirect;
			$new = $redirect;
		} else {
			$old = $redirect['old'];
			$new = $redirect['new'];
			if (isset($redirect['field_name'])) {
				$field_name = $redirect['field_name'];
			}
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
		if (!$ops['record_old'][0][$field_name]) continue;
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
		if (!empty($zz_setting['multiple_websites'])) {
			if (empty($values['POST']['website_id']))
				$values['POST']['website_id'] = $zz_setting['website_id'];
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
function zz_identifier_vars_db($sql, $id, $fieldname = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
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
