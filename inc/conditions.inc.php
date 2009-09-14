<?php 

/*
	zzform Scripts
	conditions

	(c) Gustaf Mossakowski <gustaf@koenige.org> 2009
*/

/*	----------------------------------------------	*
 *					DESCRIPTION						*
 *	----------------------------------------------	*/

/*
	main functions (in order in which they are called)

	zz_conditions_record_check()	set conditions for record
	zz_conditions_record_fields()	write new fields to $zz['fields'] based on conditions
		zz_replace_conditional_values()
	zz_merge_conditions()			merge conditional values with normal values ($zz['fields'], $zz_conf)
	zz_conditions_list_check()		set conditions for list
	
*/


// check conditions for form and list view
// Check all conditions whether they are true;
function zz_conditions_record_check($zz, $zz_tab, &$zz_var) {
	global $zz_error;
	$zz_conditions = array();
	foreach($zz['conditions'] AS $index => $condition) {
		switch ($condition['scope']) {
		case 'record': // for form view (of saved records), list view comes later in edittab because requery of record 
			$zz_conditions['bool'][$index] = false;
			if (($zz['mode'] == 'add' OR $zz['action'] == 'insert') 
				AND !empty($condition['add'])
				AND !empty($zz_var['where'][$zz['table']][$condition['add']['key_field_name']])) {
				$sql = $condition['add']['sql']
					.'"'.$zz_var['where'][$zz['table']][$condition['add']['key_field_name']].'"';
				if (!empty($condition['where']))
					$sql = zz_edit_sql($sql, 'WHERE', $condition['where']);
				if (!empty($condition['having']))
					$sql = zz_edit_sql($sql, 'HAVING', $condition['having']);
				$result = mysql_query($sql);
				if ($result AND mysql_num_rows($result)) 
					while ($line = mysql_fetch_assoc($result)) {
						$zz_conditions['bool'][$index][0] = true; // 0 = new record
					}
				if (mysql_error())
					$zz_error[] = array(
						'msg_dev' => 'Error in conditions, probably SQL query is incorrect [record-add/insert].',
						'mysql' => mysql_error(), 
						'query' => $sql
					);
			}
			if (($zz['mode'] != 'review' OR $zz['action']) AND !empty($zz_tab[0][0]['id']['value'])) {
				$sql = $zz['sql'];
				if (!empty($condition['where']))
					$sql = zz_edit_sql($sql, 'WHERE', $condition['where']);
				if (!empty($condition['having']))
					$sql = zz_edit_sql($sql, 'HAVING', $condition['having']);
				$result = mysql_query($sql);
				if ($result AND mysql_num_rows($result)) 
					// maybe get all ids instead?
					while ($line = mysql_fetch_assoc($result)) {
						$zz_conditions['bool'][$index][$line[$zz_tab[0][0]['id']['field_name']]] = true;
					}
				if (mysql_error())
					$zz_error[] = array(
						'msg_dev' => 'Error in conditions, probably SQL query is incorrect [record].',
						'mysql' => mysql_error(), 
						'query' => $sql
					);
			}
			break;
		case 'query': // just for form view (of saved records), for list view will be later in edittab.inc
			$zz_conditions['bool'][$index] = false;
			if (($zz['mode'] != 'review' OR $zz['action']) AND !empty($zz_tab[0][0]['id']['value'])) {
				$sql = zz_edit_sql($condition['sql'], 'WHERE', $condition['key_field_name'].' = '.$zz_tab[0][0]['id']['value']);
				$result = mysql_query($sql);
				if ($result AND mysql_num_rows($result)) 
					while ($line = mysql_fetch_assoc($result)) {
						$zz_conditions['bool'][$index][$line[$condition['key_field_name']]] = true;
					}
				if (mysql_error())
					$zz_error[] = array(
						'msg_dev' => 'Error in conditions, probably SQL query is incorrect [query].',
						'mysql' => mysql_error(), 
						'query' => $sql
					);
			}
			break;
		case 'value': // just for form view
			$zz_conditions['values'][$index] = false;
			if ($zz['mode'] != 'review' OR $zz['action']) {
				// get value for $condition['field_name']
				$value = false;
				if (!empty($zz_var['zz_fields'][$condition['field_name']])) {
					// Add, so get it from session
					$value = $zz_var['zz_fields'][$condition['field_name']]['value'];
				} elseif (!empty($zz_var['where'][$zz['table']][$condition['field_name']])) {
					$value = $zz_var['where'][$zz['table']][$condition['field_name']];
				} else {
					$sql = zz_edit_sql($zz['sql'], 'WHERE', $zz['table'].'.'.$zz_tab[0][0]['id']['field_name'].' = '.$zz_tab[0][0]['id']['value']);
					$result = mysql_query($sql);
					if ($result AND mysql_num_rows($result) == 1) {
						$line = mysql_fetch_assoc($result);
						$value = $line[$condition['field_name']];
					}
					if (mysql_error())
						$zz_error[] = array(
							'msg_dev' => 'Error in conditions, probably SQL query is incorrect [value].',
							'mysql' => mysql_error(), 
							'query' => $sql
						);
					if (!$value) break; // attempt to try to delete/edit a value that does not exist
				}
				$sql = sprintf($condition['sql'], $value);
				$result = mysql_query($sql);
				if ($result AND mysql_num_rows($result))
					while ($line = mysql_fetch_assoc($result))
						$zz_conditions['values'][$index][] = $line;
				if (mysql_error())
					$zz_error[] = array(
						'msg_dev' => 'Error in conditions, probably SQL query is incorrect [value/2].',
						'mysql' => mysql_error(), 
						'query' => $sql
					);
			}
			break;
		case 'upload': // just for form view
			$zz_conditions['uploads'][$index] = false;
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
	return $zz_conditions;
}

// treat values-conditions separately from bool-conditions since here 
// we get new field definitions
// get last index to add extra fields
function zz_conditions_record_fields($fields, $conditional_fields, $values) {
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
	return $fields;
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

/** Merge conditional array values with default values if condition is true 
 * 
 * @param $array = $field or $zz_conf
 * @param $bool_conditions	checked conditions
 * @param $record_id		ID of record
 * @return $array			modified $field- or $zz_conf-Array
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_merge_conditions($array, $bool_conditions, $record_id, $reverse = false) {
	
	if (!$reverse) {
		$conditions = $array['conditions'];
		unset($array['conditions']);
	} else {
		$conditions = $array['not_conditions'];
		unset($array['not_conditions']);
	}
	foreach($conditions as $condition => $new_values) {
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
				// it's not neccessarily there, this field
				if (empty($array)) $array['hide_in_list'] = true;
				// add new values for each true condition with values
				$array = zz_array_merge($array, $new_values);
			} else {
				$array = false; // no new values, so unset this field or zz_conf-value
			}
		}
	}
	return $array;
}

// Conditions
// Check all conditions whether they are true;
function zz_conditions_list_check($zz, $zz_conditions, $id_field, $ids) {
	global $zz_conf;
	global $zz_error;
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
				$result = mysql_query($sql);
				if ($result AND mysql_num_rows($result)) 
					// maybe get all ids instead?
					while ($line = mysql_fetch_assoc($result)) {
						$zz_conditions['bool'][$index][$line[$id_field]] = true;
					}
				if (mysql_error())
					$zz_error[] = array(
						'msg_dev' => 'Error in conditions, probably SQL query is incorrect [list-record].',
						'mysql' => mysql_error(), 
						'query' => $sql
					);
				break;
			case 'query':
				$sql = $condition['sql'];
				$sql = zz_edit_sql($sql, 'WHERE', $condition['key_field_name'].' IN ('.implode(', ', $ids).')');
				$result = mysql_query($sql);
				if (mysql_error())
					$zz_error[] = array(
						'msg_dev' => 'Error in conditions, probably SQL query is incorrect [list-query].',
						'mysql' => mysql_error(), 
						'query' => $sql
					);
				if ($result AND mysql_num_rows($result)) 
					while ($line = mysql_fetch_assoc($result)) {
						if (empty($line[$condition['key_field_name']])) {
							$zz_error[] = array(
								'msg_dev' => sprintf(cms_text('Error in condition %s, key_field_name is not in field list'), $index),
								'sql' => $sql,
								'level' => E_USER_ERROR
							);
							return zz_error(); // critical error, exit script
						}
						$zz_conditions['bool'][$index][$line[$condition['key_field_name']]] = true;
					}
				break;
			default:
			}
		}
	}
	return $zz_conditions;
}

?>