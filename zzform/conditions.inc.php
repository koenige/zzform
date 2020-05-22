<?php 

/**
 * zzform
 * Conditions, conditional fields
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * Contents:
 *	main functions (in order in which they are called)
 *
 *  zz_conditions_set()
 *		zz_conditions_set_field()
 *	zz_conditions_check()			set conditions for both list and record
 *	zz_conditions_record()
 *	zz_conditions_record_values()	sets values for record
 *	zz_conditions_record_check()	set conditions for record
 *  zz_conditions_subrecord_check()	set conditions for detail record
 *	zz_conditions_subrecord()
 *	zz_conditions_merge()			merge conditional values with normal values ($zz['fields'], $zz_conf)
 *		zz_conditions_merge_field()		apply to field
 *		zz_conditions_merge_conf()		apply to config
 *	zz_conditions_list_check()		set conditions for list
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2010, 2013-2020 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * set conditions to allow for shortcut conditions
 *
 * @param array $zz
 * @return array
 *		replace $zz['fields'][n]['if']['where'] with index; add $zz['conditions']
 */
function zz_conditions_set($zz) {
	// use negative values for indices
	$new_index = -1;

	// All supported shortcuts
	$shortcuts = [
		'list_empty', 'record_mode', 'export_mode', 'where', 'multi',
		'add', 'edit', 'delete', 'upload', 'noid', 'revise'
	];
	// Some shortcuts depend on a field, get field_name as extra definition
	$shortcuts_depending_on_fields = ['where'];

	$sc = [];
	$conditions = ['if', 'unless'];
	foreach ($shortcuts as $sc['shortcut']) {
		$sc['has_condition'] = false;
		$sc['depending_on_fields']
			= !in_array($sc['shortcut'], $shortcuts_depending_on_fields) ? false : true; 
		foreach ($conditions as $cn) {
			// check $zz
			if (!$sc['depending_on_fields']) {
				if (isset($zz[$cn][$sc['shortcut']])) {
					$zz[$cn][$new_index] = $zz[$cn][$sc['shortcut']];
					unset($zz[$cn][$sc['shortcut']]);
					$sc['has_condition'] = true;
				}
				// check $zz_conf
				if (isset($zz_conf[$cn][$sc['shortcut']])) {
					$zz_conf[$cn][$new_index] = $zz_conf[$cn][$sc['shortcut']];
					unset($zz_conf[$cn][$sc['shortcut']]);
					$sc['has_condition'] = true;
				}
			}
			// check $zz['fields'] individually
			foreach (array_keys($zz['fields']) as $no) {
				$zz['conditions'] += zz_conditions_set_field($zz['fields'][$no], $new_index, $sc, $cn);
				if (!empty($zz['fields'][$no]['type']) AND $zz['fields'][$no]['type'] === 'subtable') {
					foreach (array_keys($zz['fields'][$no]['fields']) as $detail_no) {
						$zz['conditions'] += zz_conditions_set_field($zz['fields'][$no]['fields'][$detail_no], $new_index, $sc, $cn);
					}
				}
			}
		}
		if ($sc['has_condition']) {
			$zz['conditions'][$new_index]['scope'] = $sc['shortcut'];
			$new_index--;
		}
	}
	return $zz;
}


/**
 * set conditions for 'field' array (main and detail records)
 *
 * @param array $field (will be changed)
 * @param int $new_index (will be changed)
 * @param array $sc (will be changed)
 * @param string $cn
 * @return array $conditions
 */
function zz_conditions_set_field(&$field, &$new_index, &$sc, $cn) {
	$conditions = [];
	if (!isset($field[$cn])) return [];
	if (!isset($field[$cn][$sc['shortcut']])) return [];
	$field[$cn][$new_index] = $field[$cn][$sc['shortcut']];
	unset($field[$cn][$sc['shortcut']]);
	if (!$sc['depending_on_fields']) {
		$sc['has_condition'] = true;
		return [];
	}
	$conditions[$new_index]['scope'] = $sc['shortcut'];
	$conditions[$new_index]['field_name'] = $field['field_name'];
	$new_index--;
	return $conditions;
}

/**
 * check conditions for form and list view
 * Check all conditions whether they are true;
 *
 * @param array $zz
 *		'conditions', 'table', 'sql', 'fields'
 * @param string $mode
 * @param array $zz_var
 *		'id' array ('value', 'name'), 'where', 'zz_fields'
 * @global array $zz_conf
 * @return array $zz_conditions
 */
function zz_conditions_check($zz, $mode, $zz_var) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$zz_conditions = [];
	foreach ($zz['conditions'] AS $index => $condition) {
		switch ($condition['scope']) {
		case 'noid':
			$zz_conditions['bool'][$index] = empty($zz_conf['int']['id']['value']) ? true : false;
			break;
		case 'add':
			if ($mode === 'add' OR $zz_var['action'] === 'insert') {
				$zz_conditions['bool'][$index] = true;
			} elseif ($mode === 'edit' OR $zz_var['action'] === 'update') {
				// and it is a detail record
				$zz_conditions['bool'][$index]['add_detail'] = true;
			}
			break;
		case 'edit':
			if ($mode === 'edit' OR $zz_var['action'] === 'update') {
				$zz_conditions['bool'][$index] = true;
			}
			break;
		case 'delete':
			if ($mode === 'delete' OR $zz_var['action'] === 'delete') {
				$zz_conditions['bool'][$index] = true;
			}
			break;
		case 'upload':
			// if actually a file was uploaded
			// @todo not 100% perfect, but should be enough
			if (!empty($_FILES)) {
				$zz_conditions['bool'][$index] = true;
			}
			break;
		case 'multi':
			if (!empty($zz_conf['multi'])) {
				$zz_conditions['bool'][$index] = true;
			}
			break;
		case 'record_mode':
			if ($mode AND $mode !== 'list_only') {
				$zz_conditions['bool'][$index] = true;
			} elseif ($zz_var['action']) {
				$zz_conditions['bool'][$index] = true;
			}
			break;
		case 'export_mode':
			if ($mode === 'export') {
				$zz_conditions['bool'][$index] = true;
			} else {
				$zz_conditions['bool'][$index] = false;
			}
			break;
		case 'revise':
			if ($mode === 'revise') {
				$zz_conditions['bool'][$index] = true;
			}
			break;
		case 'order_by':
			// @todo: not yet implemented
			break;
		case 'where':
			if (!empty($zz_var['where_condition'][$condition['field_name']]))
				$zz_conditions['bool'][$index] = true;
			break;
		default:
			break;
		}
	}
	return zz_return($zz_conditions);
}

/**
 * applies 'values' and 'bool' conditions to record
 *
 * @param array $zz
 *		array 'fields'
 * @param array $zz_conditions
 * @param int $id_value
 * @global array $zz_conf
 * @return array $zz
 */
function zz_conditions_record($zz, $zz_conditions) {
	global $zz_conf;

	// check for 'values'
	if (!empty($zz_conditions['values'])) {
		$found = false;
		foreach (array_keys($zz['fields']) as $no) {
			if (empty($zz['fields'][$no]['if'])) continue;
			$zz['fields'][$no] = zz_conditions_record_values($zz['fields'][$no], $zz_conditions['values']);
			$found = true;
		}
		if (!$found AND $zz_conf['modules']['debug']) {
			zz_debug('conditions', 'Notice: `values`-condition was set, but there’s no field using it! (Waste of ressources)');
		}
	}
	
	// check if there are any bool-conditions
	if (!empty($zz_conditions['bool'])) {
		zz_conditions_merge_conf($zz, $zz_conditions['bool'], $zz_conf['int']['id']['value']);
		foreach (array_keys($zz['fields']) as $no) {
			zz_conditions_merge_field($zz['fields'][$no], $zz_conditions['bool'], $zz_conf['int']['id']['value']);
		}
	}
	return $zz;
}

/**
 * replaces %field_name%-placeholders in conditional field definitions 
 * with value/values from database, e. g. '3' or '3,4' ..
 *
 * @param array $field field definition
 * @param array $values conditional values
 * @return array field definition
 */
function zz_conditions_record_values($field, $values) {
	foreach ($values as $condition => $records) {
		if (empty($field['if'][$condition])) continue;
		$all_values = [];
		foreach ($records as $record) {
			foreach ($record as $field_name => $value) {
				$all_values[$field_name][] = $value;
			}
		}
		if ($all_values) foreach ($field['if'][$condition] as $key => $definition) {
			foreach ($all_values as $field_name => $field_values) {
				if (!preg_match('~%'.$field_name.'%~', $definition)) continue;
				// array_keys(array_flip()) is reported to be faster than array_unique()
				$field_values = array_keys(array_flip($field_values));
				$field_values = implode(',', $field_values);
				$definition = preg_replace('~%'.$field_name.'%~', $field_values, $definition);
			}
			unset($field['if'][$condition][$key]);
			$field[$key] = $definition;
		}
		if (empty($field['if'][$condition]))
			unset($field['if'][$condition]);
		if (empty($field['if']))
			unset($field['if']);
	}
	return $field;
}

/**
 * check conditions for form and list view
 * Check all conditions whether they are true;
 *
 * @param array $zz
 *		'conditions', 'table', 'sql', 'fields'
 * @param string $mode
 * @param array $zz_var
 *		'id' array ('value', 'name'), 'where', 'zz_fields'
 * @param array $zz_conditions values from zz_conditions_check()
 * @global array $zz_conf
 * @return array $zz_conditions
 */
function zz_conditions_record_check($zz, $mode, $zz_var, $zz_conditions) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	foreach ($zz['conditions'] AS $index => $condition) {
		switch ($condition['scope']) {
		case 'editing':
			// get value
			$value = '';
			if (!empty($zz['where']) AND array_key_exists($condition['field_name'], $zz['where'])) {
				$value = $zz['where'][$condition['field_name']];
			} elseif (!empty($zz_var['where_condition']) AND array_key_exists($condition['field_name'], $zz_var['where_condition'])) {
				$value = $zz_var['where_condition'][$condition['field_name']];
			} elseif (!empty($_POST) AND array_key_exists($condition['field_name'], $_POST)) {
				$value = $_POST[$condition['field_name']];
			}
			if (!$value) {
				$zz_conditions['bool'][$index] = false;
				break;
			}
			$sql = sprintf($condition['sql'], $value);
			// get false or true
			$bool = zz_db_fetch($sql, '', 'single value');
			$zz_conditions['bool'][$index] = $bool ? true : false;
			break;
		case 'record': // for form view (of saved records), list view comes later in zz_list() because requery of record 
			$zz_conditions['bool'][$index] = [];
			if (($mode === 'add' OR $zz_var['action'] === 'insert') AND !empty($condition['add'])) {
				if (!empty($condition['add']['where']) AND !$condition['where']) {
					// where = '' is a means to make a condition always valid,
					// this also works for add
					$zz_conditions['bool'][$index][0] = true;
				} elseif (!empty($condition['add']['where'])) {
					// WHERE with ISNULL and $_GET['where']
					if (preg_match('/^NOT ISNULL\((.+)\)$/', $condition['where'], $matches)) {
						if (isset($matches[1])) {
							if (!empty($zz_var['where'][$zz['table']][$matches[1]])) {
								$zz_conditions['bool'][$index][0] = true;
								break;
							}
						}
					}
					// check directly if where-condition equals where for record
					$cond_fields = explode(' ', $condition['where']);
					// 0: table.field_name or field_name
					$cond_fields[0] = trim($cond_fields[0]);
					if (strstr($cond_fields[0], '.')) {
						$cond_fields[0] = substr($cond_fields[0], strlen($zz['table'])+1);
					}
					// 1: only = supported
					if (empty($cond_fields[1]) OR trim($cond_fields[1]) !== '=') break;
					// 2: value may be enclosed in ""
					if (empty($cond_fields[2])) break;
					$cond_fields[2] = trim($cond_fields[2]);
					if (substr($cond_fields[2], 0, 1) === '"' AND substr($cond_fields[2], -1) === '"') {
						$cond_fields[2] = substr($cond_fields[2], 1, -1);
					}
					if (!empty($zz_var['where'][$zz['table']])) {
						foreach ($zz_var['where'][$zz['table']] as $field => $id) {
							if ($field !== $cond_fields[0]) continue;
							if ($id !== $cond_fields[2]) continue;
							$zz_conditions['bool'][$index][0] = true;
						}
					}
				} elseif (!empty($condition['add']['always'])) {
					// mode = 'add': this condition is always true
					// (because condition is true for this record after being 
					// inserted and it's not yet possible to check that)
					$zz_conditions['bool'][$index][0] = true;
					break;
				} elseif (!empty($zz_var['where'][$zz['table']][$condition['add']['key_field_name']])) {
					$sql = $condition['add']['sql']
						.'"'.$zz_var['where'][$zz['table']][$condition['add']['key_field_name']].'"';
					if (!empty($condition['where']))
						$sql = wrap_edit_sql($sql, 'WHERE', $condition['where']);
					if (!empty($condition['having']))
						$sql = wrap_edit_sql($sql, 'HAVING', $condition['having']);

					if (zz_db_fetch($sql, '', '', 'record-new ['.$index.']')) {
						$zz_conditions['bool'][$index][0] = true;
					} else {
						$zz_conditions['bool'][$index][0] = false;
					}
					if (zz_error_exit()) return zz_return($zz_conditions); // DB error
				}
			}
			if (empty($zz_conf['int']['id']['value'])) break;

			$sql = isset($zz['sqlrecord']) ? $zz['sqlrecord'] : $zz['sql'];
			// for performance, remove force index
			$sql = wrap_edit_sql($sql, 'FORCE INDEX', ' ', 'delete');
			if (!empty($condition['where']))
				$sql = wrap_edit_sql($sql, 'WHERE', $condition['where']);
			if (!empty($condition['having']))
				$sql = wrap_edit_sql($sql, 'HAVING', $condition['having']);
			else
				$sql = wrap_edit_sql($sql, 'SELECT', $zz['table'].'.'.$zz_conf['int']['id']['field_name'], 'replace');
			// just get this single record
			$sql = wrap_edit_sql($sql, 'WHERE', sprintf(
				'`%s`.`%s` = %d', $zz['table'], $zz_conf['int']['id']['field_name'], $zz_conf['int']['id']['value']
			));
			$lines = zz_db_fetch($sql, $zz_conf['int']['id']['field_name'], 'id as key', 'record-list ['.$index.']');
			if (zz_error_exit()) return zz_return($zz_conditions); // DB error
			if (empty($zz_conditions['bool'][$index]))
				$zz_conditions['bool'][$index] = $lines;
			else
				$zz_conditions['bool'][$index] = zz_array_merge($zz_conditions['bool'][$index], $lines);
			break;
		case 'query': // just for form view (of saved records), for list view will be later in zz_list()
			$zz_conditions['bool'][$index] = [];
			if (empty($zz_conf['int']['id']['value'])) break;

			$sql = wrap_edit_sql($condition['sql'], 'WHERE', sprintf(
				'%s = %d', $condition['key_field_name'], $zz_conf['int']['id']['value']
			));
			$lines = zz_db_fetch($sql, $condition['key_field_name'], 'id as key', 'query ['.$index.']');
			if (zz_error_exit()) return zz_return($zz_conditions); // DB error
			if (empty($zz_conditions['bool'][$index]))
				$zz_conditions['bool'][$index] = $lines;
			else
				$zz_conditions['bool'][$index] = zz_array_merge($zz_conditions['bool'][$index], $lines);
			break;
		case 'value': // just for record view
			$zz_conditions['values'][$index] = [];

			// get value for $condition['field_name']
			$value = false;
			if (!empty($zz_var['zz_fields'][$condition['field_name']])) {
				// Add, so get it from session
				$value = $zz_var['zz_fields'][$condition['field_name']]['value'];
			} elseif (!empty($zz_var['where'][$zz['table']][$condition['field_name']])) {
				$value = $zz_var['where'][$zz['table']][$condition['field_name']];
			} else {
				$sql = isset($zz['sqlrecord']) ? $zz['sqlrecord'] : $zz['sql'];
				$sql = wrap_edit_sql($sql, 'WHERE', sprintf(
					'%s.%s = %d', $zz['table'], $zz_conf['int']['id']['field_name'], $zz_conf['int']['id']['value']
				));
				$line = zz_db_fetch($sql, '', '', 'value/1 ['.$index.']');
				if (zz_error_exit()) return zz_return($zz_conditions); // DB error
				if ($line) {
					if (isset($line[$condition['field_name']])) {
						$value = $line[$condition['field_name']];
					} elseif (isset($zz['sqlextra'])) {
						foreach ($zz['sqlextra'] as $sql) {
							$sql = sprintf($sql, $zz_conf['int']['id']['value']);
							$line = zz_db_fetch($sql, '', '', 'value/1b ['.$index.']');
							if (isset($line[$condition['field_name']])) {
								$value = $line[$condition['field_name']];
								break;
							}
						}
					} else {
						zz_error_log([
							'msg_dev' => 'Value condition can’t get corresponding value from database (field %s)',
							'msg_dev_args' => [$condition['field_name']]
						]);
						return zz_return($zz_conditions);
					}
				} else {
					// attempt to try to delete/edit a value that does not exist
					break; 
				}
			}
			if (!$value) {
				zz_error_log([
					'msg_dev' => 'Value condition has empty value in database (field %s)',
					'msg_dev_args' => [$condition['field_name']]
				]);
				return zz_return($zz_conditions);
			}
			$sql = sprintf($condition['sql'], $value);
			$lines = zz_db_fetch($sql, 'dummy_id', 'numeric', 'value/2 ['.$index.']');
			if (zz_error_exit()) return zz_return($zz_conditions); // DB error
			if (empty($zz_conditions['values'][$index]))
				$zz_conditions['values'][$index] = $lines;
			else
				$zz_conditions['values'][$index] = array_merge($zz_conditions['values'][$index], $lines);
			break;
		case 'upload': // just for form view
			$zz_conditions['uploads'][$index] = [];
			$table = 0;
			foreach ($zz['fields'] as $f => $field) {
				if (!empty($field['type']) AND $field['type'] === 'upload_image') {
					foreach ($subfield['image'] as $key => $upload_image) {
						if (empty($upload_image['save_as_record'])) continue;
						$zz_conditions['fields'][$index][] = [
							'table_no' => 0, 'field_no' => $f, 'image_no' => $key
						];
					}
				} elseif (!empty($field['type']) AND $field['type'] === 'subtable') {
					$table++;
					foreach ($field['fields'] as $subf => $subfield) {
						if (empty($subfield['type'])) continue;
						if ($subfield['type'] !== 'upload_image') continue;
						foreach ($subfield['image'] as $key => $upload_image) {
							if (empty($upload_image['save_as_record'])) continue;
							$zz_conditions['fields'][$index][] = [
								'table_no' => $table, 'field_no' => $subf, 'image_no' => $key
							];
						}
					}
				}
			}
			break;
		case 'access':
			// get access rights for current ID with user function
			if (empty($zz_conf['int']['id']['value'])) {
				$zz_conditions['bool'][$index] = [];
				break;
			}
			$zz_conditions['bool'][$index] = $condition['function']([$zz_conf['int']['id']['value']], $condition);
			break;
		case 'subrecord': // ignore here
		default:
			break;
		}
	}
	return zz_return($zz_conditions);
}

/**
 * check conditions for subrecords
 * (experimental)
 *
 * @param array $zz
 * @param array $zz_tab
 * @param array $zz_conditions
 * @return array zz_conditions
 *		$zz_conditions['bool']['subrecord'][$index][id] = 1
 */
function zz_conditions_subrecord_check($zz, $zz_tab, $zz_conditions) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	foreach ($zz['conditions'] AS $index => $condition) {
		switch ($condition['scope']) {
		case 'subrecord':
			foreach ($zz_tab as $tab) {
				if (!$tab) continue;
				if (empty($tab['no'])) continue;
				if ($tab['no'] !== $condition['subrecord']) continue;
				
				if (!empty($condition['where'])) {
					if (!empty($condition['where_with_main_id'])) {
						// this reduces the length of the list of IDs returned by database
						$condition['where'] = sprintf($condition['where'], $zz_conf['int']['id']['value']);
					}
					$sql = wrap_edit_sql($tab['sql'], 'WHERE', $condition['where']);
					if (!empty($tab['hierarchy']['id_field_name'])) {
						$id_field_name = $tab['hierarchy']['id_field_name'];
					} else {
						$id_field_name = $tab['id_field_name'];
					}
					$zz_conditions['bool']['subrecord-'.$tab['no']][$index] = zz_db_fetch($sql, $id_field_name, 'id as key', 'subrecord');
				}
			}
			break;
		}
	}
	return zz_return($zz_conditions);
}

/**
 * merges conditions for detail records into $zz_tab
 *
 * @param array $zz_tab
 * @param array $zz_conditions
 * @return array $zz_tab
 */
function zz_conditions_subrecord($zz_tab, $zz_conditions) {
	if (empty($zz_conditions['bool'])) return $zz_tab;
	foreach (array_keys($zz_tab) as $tab) {
		if (!$tab) continue;
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if (!empty($zz_conditions['bool']['subrecord-'.$zz_tab[$tab]['no']])) {
				$id_value = -1;
				if ($zz_tab[$tab]['hierarchy']) {
					if (!empty($zz_tab[$tab][$rec]['POST'])) {
						$id_value = $zz_tab[$tab][$rec]['POST'][$zz_tab[$tab]['hierarchy']['id_field_name']];
					}
				} else {
					$id_value = $zz_tab[$tab][$rec]['id']['value'];
				}
				if ($id_value !== -1) {
					zz_conditions_merge_field(
						$zz_tab[$tab][$rec],
						$zz_conditions['bool']['subrecord-'.$zz_tab[$tab]['no']],
						$id_value, 'detail'
					);
					foreach (array_keys($zz_tab[$tab][$rec]['fields']) as $sub_no) {
						zz_conditions_merge_field(
							$zz_tab[$tab][$rec]['fields'][$sub_no],
							$zz_conditions['bool']['subrecord-'.$zz_tab[$tab]['no']],
							$id_value, 'detail'
						);
					}
				}
			}
			if (empty($zz_tab[$tab][$rec]['fields'])) {
				if (empty($zz_tab[$tab][$rec]['existing']) AND isset($zz_tab[$tab][$rec]['existing'])) {
					// leftovers, @todo make them not appear in first case
					unset($zz_tab[$tab][$rec]['existing']);
				}
				if (empty($zz_tab[$tab][$rec])) unset($zz_tab[$tab][$rec]);
				continue;
			}
			foreach (array_keys($zz_tab[$tab][$rec]['fields']) as $sub_no) {
				zz_conditions_merge_field(
					$zz_tab[$tab][$rec]['fields'][$sub_no],
					$zz_conditions['bool'],
					$zz_tab[$tab][$rec]['id']['value'], 'detail'
				);
			}
		}
	}
	return $zz_tab;
}

/**
 * Merge conditional array values with default values if condition is true 
 * 
 * @param array $array = $field or $zz_conf
 * @param array $bool_conditions	checked conditions
 * @param int $record_id		ID of record
 * @param bool $reverse optional; false: if (default), true: unless
 * @param string $type 'field' => field definition will be changed, 'conf' =>
 *			$zz_conf or $zz_conf_record will be changed
 * @global array $zz_conf
 * @return array $array			modified $field- or $zz_conf-Array
 */
function zz_conditions_merge($array, $bool_conditions, $record_id, $reverse = false, $type = 'field') {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start ID'.$record_id, __FUNCTION__);

	if (!$reverse) {
		$conditions = $array['if'];
	} else {
		$conditions = $array['unless'];
	}

	foreach ($conditions as $condition => $new_values) {
		// only change arrays if there is something
		// whole fields might be unset!
		if (in_array($type, ['field', 'detail']) AND !isset($new_values)) continue;
		// whole configuration might not be unset!
		if ($type === 'conf' AND empty($new_values)) continue;

		// only change arrays if $zz['conditions'] was set! (might be empty!)
		if (!isset($bool_conditions[$condition])) continue;

		// if reverse check ('not-condition'), bring all keys to reverse
		if ($reverse) {
			if ($bool_conditions[$condition] === true)
				$bool_conditions[$condition] = false;
			elseif (empty($bool_conditions[$condition][$record_id])) 
				$bool_conditions[$condition][$record_id] = true;
			else 
				$bool_conditions[$condition][$record_id] = false;
		} else {
			// if there's no condition defined, ignore this one!
			if (empty($bool_conditions[$condition])) continue;
		}
		if ($type === 'detail' AND !empty($bool_conditions[$condition]['add_detail'])) {
			if (!$reverse AND !$record_id) {
				$bool_conditions[$condition][$record_id] = true;
			} elseif ($reverse AND $record_id) {
				$bool_conditions[$condition][$record_id] = true;
			}
		}
		// else check it and if it's true, do something
		if (!empty($bool_conditions[$condition][$record_id])
			OR $bool_conditions[$condition] === true) {
			if ($new_values) {
				// if normally there is no field like this, you can't show it in list view
				// it's not necessarily there, this field
				if (empty($array)) $array['hide_in_list'] = true;
				// add new values for each true condition with values
				$array = zz_array_merge($array, $new_values);
			} else {
				$array = false; // no new values, so unset this field or zz_conf-value
				break; // don't add values from other ifs
			}
		}
	}
	// fill_out
	if (!empty($array['class']) AND !is_array($array['class'])) {
		$array['class'] = [$array['class']];
	}
	return zz_return($array);
}

/**
 * merge $field with if and unless conditions
 *
 * @param array $field (will change if there are conditions)
 * @param array $bool_conditions	checked conditions
 * @param int $record_id		ID of record
 * @return bool true: field definition was changed; false: nothing was changed
 */
function zz_conditions_merge_field(&$field, $bool_conditions, $record_id, $type = 'field') {
	$merged = false;
	if (!empty($field['if'])) {
		$field = zz_conditions_merge($field, $bool_conditions, $record_id, false, $type);
		$merged = true;
	}
	if (!empty($field['unless'])) {
		$field = zz_conditions_merge($field, $bool_conditions, $record_id, true, $type);
		$merged = true;
	}
	return $merged;
}

/**
 * merge $zz_conf with if and unless conditions
 *
 * @param array $conf (e. g. $zz_conf, $zz_conf_record: will change if 
 *		there are conditions)
 * @param array $bool_conditions	checked conditions
 * @param int $record_id		ID of record
 * @return bool true: configuration was changed; false: nothing was changed
 */
function zz_conditions_merge_conf(&$conf, $bool_conditions, $record_id) {
	$merged = false;
	if (!empty($conf['if'])) {
		$conf = zz_conditions_merge($conf, $bool_conditions, $record_id, false, 'conf');
		$merged = true;
	}
	if (!empty($conf['unless'])) {
		$conf = zz_conditions_merge($conf, $bool_conditions, $record_id, true, 'conf');
		$merged = true;
	}
	return $merged;
}

/**
 * Check all conditions whether they are true
 * 
 * @param array $zz (table definition)
 * @param array $zz_conditions (existing conditions from zz_conditions_record_check)
 * @param array $ids (record IDs)
 * @param string $mode ($ops['mode'])
 * @global array $zz_conf
 * @return array $zz_conditions 
 *		['bool'][$index of condition] = [$record ID1 => true, $record ID2 
 *		=> true, ..., $record IDn => true] or true = all true // false = fall false
 * @see zz_conditions_record_check()
 */
function zz_conditions_list_check($zz, $zz_conditions, $ids, $mode) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if (empty($zz['conditions'])) return zz_return($zz_conditions);

	// improve database performace, for this query we only need ID field
	$zz['sql_without_limit'] = wrap_edit_sql($zz['sql_without_limit'], 'SELECT', $zz['table'].'.'.$zz_conf['int']['id']['field_name'], 'replace');
	// get rid of ORDER BY because we don't have the fields and we don't need it
	$zz['sql_without_limit'] = wrap_edit_sql($zz['sql_without_limit'], 'ORDER BY', ' ', 'delete');
	$zz['sql_without_limit'] = wrap_edit_sql($zz['sql_without_limit'], 'FORCE INDEX', ' ', 'delete');

	foreach ($zz['conditions'] AS $index => $condition) {
		switch ($condition['scope']) {
		// case record remains the same as in form view
		// case query covers more ids
		case 'multi':
			if (!empty($zz_conf['multi'])) {
				$zz_conditions['bool'][$index] = true;
			} else {
				$zz_conditions['bool'][$index] = false;
			}
			break;
		case 'record':
			if (!$zz_conf['int']['show_list']) break;
			$sql = $zz['sql_without_limit'];
			if (!empty($condition['where']))
				$sql = wrap_edit_sql($sql, 'WHERE', $condition['where']);
			if (!empty($condition['having']))
				$sql = wrap_edit_sql($sql, 'HAVING', $condition['having']);
			if (count($ids) < 200) {
				// using IDs is faster than getting the full query
				// not sure if WHERE .. IN () is slowing things down with
				// a big number of IDs
				// this restriction might be removed in later versions of zzform
				$sql = wrap_edit_sql($sql, 'WHERE', '`'.$zz['table'].'`.'.$zz_conf['int']['id']['field_name'].' IN ('.implode(',', $ids).')');
			}
			$lines = zz_db_fetch($sql, $zz_conf['int']['id']['field_name'], 'id as key', 'list-record ['.$index.']');
			if (zz_error_exit()) return zz_return($zz_conditions); // DB error
			$zz_conditions['bool'][$index] = $lines;
			break;
		case 'query':
			if (!$zz_conf['int']['show_list']) break;
			$sql = $condition['sql'];
			$sql = wrap_edit_sql($sql, 'WHERE', $condition['key_field_name'].' IN ('.implode(', ', $ids).')');
			$lines = zz_db_fetch($sql, $condition['key_field_name'], 'id as key', 'list-query ['.$index.']');
			if (zz_error_exit()) return zz_return($zz_conditions); // DB error
			$zz_conditions['bool'][$index] = $lines;
			break;
		case 'access':
			// get access rights for each ID with user function
			$zz_conditions['bool'][$index] = $condition['function']($ids, $condition);
			break;
		case 'export_mode':
			if ($mode === 'export') {
				$zz_conditions['bool'][$index] = true;
			} else {
				$zz_conditions['bool'][$index] = false;
			}
			break;
		case 'list_empty':
			if (!empty($zz['sqlcount'])) {
				$total_rows = zz_sql_count_rows($zz['sqlcount']);
			} else {
				$total_rows = zz_sql_count_rows($zz['sql'], $zz['table'].'.'.$zz_conf['int']['id']['field_name']);
			}
			if (!$total_rows) {
				$zz_conditions['bool'][$index] = true;
			} else {
				$zz_conditions['bool'][$index] = false;
			}
			break;
		default:
			if (!isset($zz_conditions['bool'][$index])) {
				// condition might be set by zz_conditions_record_check(), e. g. 'where'
				$zz_conditions['bool'][$index] = [];
			}
			break;
		}
	}
	return zz_return($zz_conditions);
}
