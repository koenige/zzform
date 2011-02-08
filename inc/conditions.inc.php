<?php 

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2009-2010
// conditions


/*	----------------------------------------------	*
 *					DESCRIPTION						*
 *	----------------------------------------------	*/

/*
	main functions (in order in which they are called)

	zz_conditions_record_check()	set conditions for record
	zz_conditions_record_fields()	write new fields to $zz['fields'] based on conditions
		zz_replace_conditional_values()
	zz_conditions_merge()			merge conditional values with normal values ($zz['fields'], $zz_conf)
	zz_conditions_list_check()		set conditions for list
*/

/**
 * check conditions for form and list view
 * Check all conditions whether they are true;
 *
 * @param array $zz
 *		'conditions', 'table', 'sql', 'fields'
 * @param string $mode
 * @param array $zz_var
 *		'id' array ('value', 'name'), 'where', 'zz_fields'
 * @global array $zz_error
 * @global array $zz_conf
 * @return array $zz_conditions
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_conditions_record_check($zz, $mode, $zz_var) {
	global $zz_error;
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$zz_conditions = array();
	foreach($zz['conditions'] AS $index => $condition) {
		switch ($condition['scope']) {
		case 'record': // for form view (of saved records), list view comes later in zz_list() because requery of record 
			$zz_conditions['bool'][$index] = array();
			if (($mode == 'add' OR $zz_var['action'] == 'insert') AND !empty($condition['add'])) {
				if (!empty($condition['add']['always'])) {
					// mode = 'add': this condition is always true
					// (because condition is true for this record after being 
					// inserted and it's not yet possible to check that)
					$zz_conditions['bool'][$index][0] = true;
				} elseif (!empty($zz_var['where'][$zz['table']][$condition['add']['key_field_name']])) {
					$sql = $condition['add']['sql']
						.'"'.$zz_var['where'][$zz['table']][$condition['add']['key_field_name']].'"';
					if (!empty($condition['where']))
						$sql = zz_edit_sql($sql, 'WHERE', $condition['where']);
					if (!empty($condition['having']))
						$sql = zz_edit_sql($sql, 'HAVING', $condition['having']);
					if (zz_db_fetch($sql, '', '', 'record-new ['.$index.']')) {
						$zz_conditions['bool'][$index][0] = true;
					} else {
						$zz_conditions['bool'][$index][0] = false;
					}
					if ($zz_error['error']) return zz_return($zz_conditions); // DB error
				}
			}
			if ($mode != 'list_only' AND !empty($zz_var['id']['value'])) {
				$sql = $zz['sql'];
				if (!empty($condition['where']))
					$sql = zz_edit_sql($sql, 'WHERE', $condition['where']);
				if (!empty($condition['having']))
					$sql = zz_edit_sql($sql, 'HAVING', $condition['having']);
				// just get this single record
				$sql = zz_edit_sql($sql, 'WHERE', '`'.$zz['table'].'`.`'
					.$zz_var['id']['field_name'].'` = '.$zz_var['id']['value']);
				$lines = zz_db_fetch($sql, $zz_var['id']['field_name'], 'id as key', 'record-list ['.$index.']');
				if ($zz_error['error']) return zz_return($zz_conditions); // DB error
				if (empty($zz_conditions['bool'][$index]))
					$zz_conditions['bool'][$index] = $lines;
				else
					$zz_conditions['bool'][$index] = zz_array_merge($zz_conditions['bool'][$index], $lines);
			}
			break;
		case 'query': // just for form view (of saved records), for list view will be later in zz_list()
			$zz_conditions['bool'][$index] = array();
			if ($mode != 'list_only' AND !empty($zz_var['id']['value'])) {
				$sql = zz_edit_sql($condition['sql'], 'WHERE', $condition['key_field_name'].' = '.$zz_var['id']['value']);
				$lines = zz_db_fetch($sql, $condition['key_field_name'], 'id as key', 'query ['.$index.']');
				if ($zz_error['error']) return zz_return($zz_conditions); // DB error
				if (empty($zz_conditions['bool'][$index]))
					$zz_conditions['bool'][$index] = $lines;
				else
					$zz_conditions['bool'][$index] = zz_array_merge($zz_conditions['bool'][$index], $lines);
			}
			break;
		case 'value': // just for record view
			$zz_conditions['values'][$index] = array();
			if ($mode != 'list_only') {
				// get value for $condition['field_name']
				$value = false;
				if (!empty($zz_var['zz_fields'][$condition['field_name']])) {
					// Add, so get it from session
					$value = $zz_var['zz_fields'][$condition['field_name']]['value'];
				} elseif (!empty($zz_var['where'][$zz['table']][$condition['field_name']])) {
					$value = $zz_var['where'][$zz['table']][$condition['field_name']];
				} else {
					$sql = zz_edit_sql($zz['sql'], 'WHERE', $zz['table'].'.'
						.$zz_var['id']['field_name'].' = '.$zz_var['id']['value']);
					$line = zz_db_fetch($sql, '', '', 'value/1 ['.$index.']');
					if ($zz_error['error']) return zz_return($zz_conditions); // DB error
					if ($line) {
						$value = $line[$condition['field_name']];
					} else {
						// attempt to try to delete/edit a value that does not exist
						break; 
					}
				}
				$sql = sprintf($condition['sql'], $value);
				$lines = zz_db_fetch($sql, 'dummy_id', 'numeric', 'value/2 ['.$index.']');
				if ($zz_error['error']) return zz_return($zz_conditions); // DB error
				if (empty($zz_conditions['values'][$index]))
					$zz_conditions['values'][$index] = $lines;
				else
					$zz_conditions['values'][$index] = array_merge($zz_conditions['values'][$index], $lines);
			}
			break;
		case 'upload': // just for form view
			$zz_conditions['uploads'][$index] = array();
			$table = 0;
			foreach ($zz['fields'] as $f => $field) {
				if (!empty($field['type']) AND $field['type'] == 'upload_image') {
					foreach ($subfield['image'] as $key => $upload_image) {
						if (!empty($upload_image['save_as_record'])) {
							$upload_image['table_no'] = 0;
							$upload_image['field_no'] = $f;
							$upload_image['image_no'] = $key;
							$zz_conditions['fields'][$index][] = $upload_image;
						}
					}
				} elseif (!empty($field['type']) AND $field['type'] == 'subtable') {
					$table++;
					foreach ($field['fields'] as $subf => $subfield) {
						if (!empty($subfield['type']) AND $subfield['type'] == 'upload_image') {
							foreach ($subfield['image'] as $key => $upload_image) {
								if (!empty($upload_image['save_as_record'])) {
									$upload_image['table_no'] = $table;
									$upload_image['field_no'] = $subf;
									$upload_image['image_no'] = $key;
									$zz_conditions['fields'][$index][] = $upload_image;
								}
							}
						}
					}
				}
			}
			break;
		default:
		}
	}
	return zz_return($zz_conditions);
}


/**
 * treat values-conditions separately from bool-conditions since here 
 * we get new field definitions; get last index to add extra fields
 *
 * @param array $fields
 * @param array $conditional_fields
 * @param array $values
 * @global array $zz_conf
 * @return array $fields
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_conditions_record_fields($fields, $conditional_fields, $values) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$all_indices = array_keys($fields);
	asort($all_indices);
	$last_index = array_pop($all_indices);
	$index = $last_index + 1; // last index, increment 1
	// initialize variables
	$remove_fields = array();
	$new_fields = array();
	// create new field definitions
	foreach ($conditional_fields as $condition => $definitions) {
		$template = $definitions['template_field'];
		if (!empty($definitions['remove_template'])) $remove_fields[$template] = true;
		if (empty($values[$condition])) continue;
		foreach ($values[$condition] as $placeholder_record) {
			$thisrecord = $definitions;
			$placeholder_record['index'] = $index;
			// get counter of array index for $template for glueing and cutting array
			$new_fields[$template][$index] = false;
			$new_fields[$template][$index] = $fields[$template];
			array_walk($thisrecord, 'zz_replace_conditional_values', $placeholder_record);
			$new_fields[$template][$index] = zz_array_merge($new_fields[$template][$index], $thisrecord);
			$index++;
		}
	}
	$all_indices = array_flip($all_indices);
	// glue into right position
	foreach ($new_fields as $fieldindex => $fields_to_add) {
		$zz_fields = array_merge(array_slice($fields, 0, $all_indices[$fieldindex]), $fields_to_add, 
			array_slice($fields, $all_indices[$fieldindex]));
		// old PHP 4 support
		$zz_fields_keys = array_merge(array_slice(array_keys($fields), 0, $all_indices[$fieldindex]), 
			array_keys($fields_to_add), array_slice(array_keys($fields), $all_indices[$fieldindex]));
		unset($fields);
		foreach($zz_fields_keys as $f_index => $real_index) {
			$fields[$real_index] = $zz_fields[$f_index];
		}
		unset ($zz_fields);
		// old PHP 4 support end, might be replaced by variables in array_slice
	}
	// remove unnecessary definitions
	unset($all_indices);
	foreach (array_keys($remove_fields) as $fieldkey) {
		unset($fields[$fieldkey]);
	}
	return zz_return($fields);
}

function zz_replace_conditional_values(&$item, $key, $records) {
	if (is_array($item)) array_walk($item, 'zz_replace_conditional_values', $records);
	else {
		foreach ($records as $field_name => $record) {
			if (preg_match('~%'.$field_name.'%~', $item))
				$item = preg_replace('~%'.$field_name.'%~', $record, $item);
		}
	}
}

/**
 * Merge conditional array values with default values if condition is true 
 * 
 * @param array $array = $field or $zz_conf
 * @param array $bool_conditions	checked conditions
 * @param int $record_id		ID of record
 * @param bool $reverse optional; false: conditions (default), true: not_conditions
 * @param string $type 'field' => field definition will be changed, 'conf' =>
 *			$zz_conf or $zz_conf_record will be changed
 * @global array $zz_conf
 * @return array $array			modified $field- or $zz_conf-Array
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_conditions_merge($array, $bool_conditions, $record_id, $reverse = false, $type = 'field') {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start ID'.$record_id, __FUNCTION__);

	if (!$reverse) {
		$conditions = $array['conditions'];
	} else {
		$conditions = $array['not_conditions'];
	}

	foreach ($conditions as $condition => $new_values) {
		// only change arrays if there is something
		// whole fields might be unset!
		if ($type == 'field' AND !isset($new_values)) continue;
		// whole configuration might not be unset!
		if ($type == 'conf' AND empty($new_values)) continue;

		// only change arrays if $zz['conditions'] was set! (might be empty!)
		if (!isset($bool_conditions[$condition])) continue;

		// if reverse check ('not-condition'), bring all keys to reverse
		if ($reverse) {
			if (empty($bool_conditions[$condition][$record_id])) 
				$bool_conditions[$condition][$record_id] = true;
			else 
				$bool_conditions[$condition][$record_id] = false;
		} else {
			// if there's no condition defined, ignore this one!
			if (empty($bool_conditions[$condition])) continue;
		}
		// else check it and if it's true, do something
		if (!empty($bool_conditions[$condition][$record_id])) {
			if ($new_values) {
				// if normally there is no field like this, you can't show it in list view
				// it's not necessarily there, this field
				if (empty($array)) $array['hide_in_list'] = true;
				// add new values for each true condition with values
				$array = zz_array_merge($array, $new_values);
			} else {
				$array = false; // no new values, so unset this field or zz_conf-value
			}
		}
	}
	return zz_return($array);
}

/**
 * Check all conditions whether they are true
 * 
 * @param array $zz
 * @param array $zz_conditions
 * @param string $id_field
 * @param array $ids
 * @global array $zz_conf
 * @global array $zz_error
 * @return array $zz_conditions
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_conditions_list_check($zz, $zz_conditions, $id_field, $ids) {
	global $zz_conf;
	global $zz_error;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if ($zz_conf['show_list'] AND !empty($zz['conditions'])) {
		// improve database performace, for this query we only need ID field
		$zz['sql_without_limit'] = zz_edit_sql($zz['sql_without_limit'], 'SELECT', $zz['table'].'.'.$id_field, 'replace');
		// get rid of ORDER BY because we don't have the fields and we don't need it
		$zz['sql_without_limit'] = zz_edit_sql($zz['sql_without_limit'], 'ORDER BY', ' ', 'delete');

		foreach($zz['conditions'] AS $index => $condition) {
			switch ($condition['scope']) {
			// case record remains the same as in form view
			// case query covers more ids
			case 'record':
				$sql = $zz['sql_without_limit'];
				if (!empty($condition['where']))
					$sql = zz_edit_sql($sql, 'WHERE', $condition['where']);
				if (!empty($condition['having']))
					$sql = zz_edit_sql($sql, 'HAVING', $condition['having']);
				if (count($ids) < 200) {
					// using IDs is faster than getting the full query
					// not sure if WHERE .. IN () is slowing things down with
					// a big number of IDs
					// this restriction might be removed in later versions of zzform
					$sql = zz_edit_sql($sql, 'WHERE', '`'.$zz['table'].'`.'.$id_field.' IN ('.implode(',', $ids).')');
				}
				$lines = zz_db_fetch($sql, $id_field, 'id as key', 'list-record ['.$index.']');
				if ($zz_error['error']) return zz_return($zz_conditions); // DB error
				break;
			case 'query':
				$sql = $condition['sql'];
				$sql = zz_edit_sql($sql, 'WHERE', $condition['key_field_name'].' IN ('.implode(', ', $ids).')');
				$lines = zz_db_fetch($sql, $condition['key_field_name'], 'id as key', 'list-query ['.$index.']');
				if ($zz_error['error']) return zz_return($zz_conditions); // DB error
				break;
			default:
				$lines = array();
				break;
			}
			if (empty($zz_conditions['bool'][$index]))
				$zz_conditions['bool'][$index] = $lines;
			else
				$zz_conditions['bool'][$index] = zz_array_merge($zz_conditions['bool'][$index], $lines);
		}
	}
	return zz_return($zz_conditions);
}

?>