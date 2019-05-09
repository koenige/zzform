<?php 

/**
 * zzform
 * Merge functions
 *
 * Part of �Zugzwang Project�
 * http://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright � 2014-2015, 2017, 2019 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function zz_merge_records($zz) {
	global $zz_conf;
	
	if (empty($_POST['zz_action'])) return false;
	if ($_POST['zz_action'] !== 'multiple') return false;
	if (empty($_POST['zz_record_id'])) return false;
	if (!is_array($_POST['zz_record_id'])) return false;
	if (count($_POST['zz_record_id']) < 2) return false;

	$ids = [];
	foreach ($_POST['zz_record_id'] as $id) {
		$ids[] = intval($id);
	}
	$recs = [];
	$msg = [];

	// check if there's a 1:1 record inline, merge this first
	foreach ($zz['fields'] as $no => $field) {
		if ($field['type'] === 'id') {
			$recs[] = [
				'id_field_name' => $field['field_name'],
				'table' => $zz['table'],
				'ids' => $ids,
				'fields' => $zz['fields'],
				'key_field_name' => $field['field_name']
			];
		}
		if ($field['type'] !== 'subtable') continue;
		if (empty($field['form_display'])) continue;
		if ($field['form_display'] !== 'inline') continue;
		foreach ($field['fields'] as $subfield) {
			if ($subfield['type'] !== 'foreign_key') continue;
			$field['key_field_name'] = $subfield['field_name'];
			break;
		}
		foreach ($field['fields'] as $subfield) {
			if ($subfield['type'] !== 'id') continue;
			$sql = 'SELECT %s FROM %s WHERE %s IN (%s)';
			$sql = sprintf($sql, $subfield['field_name'], $field['table'], $field['key_field_name'], implode(',', $ids));
			$recs[] = [
				'id_field_name' => $subfield['field_name'],
				'table' => $field['table'],
				'ids' => wrap_db_fetch($sql, '_dummy_', 'single value'),
				'fields' => $field['fields'],
				'key_field_name' => $field['key_field_name']
			];
			break;
		}
		$msg = array_merge($msg, zz_merge_equal(end($recs)));
	}

	$msg = array_merge($msg, zz_merge_equal($recs[0]));
	if ($msg) {
		return [
			'msg' => $msg, 'uncheck' => false, 'title' => ''
		];
	}

	$msg = [];
	$uncheck = false;
	$error = false;

	// walk through detail record first, main record last
	$recs = array_reverse($recs);
	foreach ($recs as $rec) {
		// keep lowest ID
		sort($rec['ids']);
		$new_id = array_shift($rec['ids']);
		$old_ids = $rec['ids'];
		$sql = 'SELECT rel_id, detail_db, detail_table, detail_field, `delete`, detail_id_field
			FROM %s
			WHERE master_db = "%s"
			AND master_table = "%s"
			AND master_field = "%s"';
		$sql = sprintf($sql, $zz_conf['relations_table'], $zz_conf['db_name'], $rec['table'], $rec['id_field_name']);
		$dependent_records = zz_db_fetch($sql, 'rel_id');

		$dependent_sql = 'SELECT %s, %s FROM %s.%s WHERE %s IN (%s)';
		$record_sql = 'UPDATE %s SET %s = %%d WHERE %s = %%d';
		foreach ($dependent_records as $record) {
			$sql = sprintf($dependent_sql,
				$record['detail_id_field'], $record['detail_field'],
				$record['detail_db'], $record['detail_table'], $record['detail_field'],
				implode(',', $old_ids)
			);
			$records = zz_db_fetch($sql, $record['detail_id_field']);
			if (!$records) continue;

			$this_record_sql = sprintf($record_sql,
				($record['detail_db'] !== $zz_conf['db_name'] 
					? $record['detail_db'].'.'.$record['detail_table'] 
					: $record['detail_table']),
				$record['detail_field'], $record['detail_id_field']
			);
			foreach ($records as $entry) {
				$record_id = $entry[$record['detail_id_field']];
				$sql = sprintf($this_record_sql, $new_id, $record_id);
				$result = zz_db_change($sql, $record_id);
				if ($result['action'] === 'update') {
					$msg[] = sprintf(zz_text('Merging entry in table %s merged (ID: %d)'),
						'<code>'.$record['detail_table'].'</code>', $record_id
					);
					$uncheck = true;
				} else {
					$msg[] = sprintf(zz_text('Merging entry in table %s failed with an error (ID: %d): %s'),
						'<code>'.$record['detail_table'].'</code>', $record_id, $result['error']['db_msg']
					);
					$error = true;
				}
				// @todo catch errors, e. g. if UNIQUE key hinders update
			}
		}

		// merge main record
		$types_case_insensitive = ['mail'];
		$types_ignored = ['id', 'timestamp'];

		$update = true;
		$new_values = [];
		$delete_old_records = false;
		if (!$error) {
			$merge_ignore_fields = [];
			$case_insensitive_fields = [];
			$fields_by_fieldname = [];
			foreach ($rec['fields'] as $no => $field) {
				if (empty($field['field_name'])) continue;
				$fields_by_fieldname[$field['field_name']] = $no;
				if (in_array($field['type'], $types_ignored)) {
					$merge_ignore_fields[] = $field['field_name'];
				} elseif (!empty($field['merge_ignore'])) {
					$merge_ignore_fields[] = $field['field_name'];
				} elseif ($field['field_name'] === $rec['key_field_name']) {
					$merge_ignore_fields[] = $field['field_name'];
				}
				if (in_array($field['type'], $types_case_insensitive)) {
					$case_insensitive_fields[] = $field['field_name'];		
				}
			}
	
			// no detail records exist anymore
			$old_sql = sprintf('SELECT * FROM %s WHERE %s = %%d', $rec['table'], $rec['id_field_name']);
			$delete_sql = sprintf('DELETE FROM %s WHERE %s = %%d LIMIT 1', $rec['table'], $rec['id_field_name']); 
			$sql = sprintf($old_sql, $new_id);
			$new_record = zz_db_fetch($sql);
			foreach ($merge_ignore_fields as $field) {
				if (array_key_exists($field, $new_record)) unset($new_record[$field]);
			}
			foreach ($old_ids as $old_id) {
				$sql = sprintf($old_sql, $old_id);
				$old_record = zz_db_fetch($sql);
				foreach ($merge_ignore_fields as $field) {
					if (array_key_exists($field, $old_record)) unset($old_record[$field]);
				}
				if ($old_record === $new_record) {
					$delete_old_records = true;
				} else {
					$update_errors = [];
					foreach ($old_record as $field_name => $value) {
						if (!$value) continue;
						if ($value === $new_record[$field_name]) continue; // everything ok
						if (in_array($field_name, $case_insensitive_fields)) {
							if (strtolower($value) === strtolower($new_record[$field_name])) continue;
						}
						if (!$new_record[$field_name]) {
							// existing field is empty, we can overwrite it
							if (array_key_exists($field_name, $new_values)) {
								// overwrite with different values is impossible
								if ($value !== $new_values[$field_name]) {
									$update = false;
									$update_errors[] = [
										'field_name' => $field_name,
										'old' => $old_record[$field_name],
										'new' => $new_record[$field_name]
									];
								}
							} else {
								$new_values[$field_name] = $value;
							}
						} else {
							// values differ, no overwriting
							$newval = zz_merge_updateable(
								$old_record[$field_name], $new_record[$field_name],
								$rec['fields'][$fields_by_fieldname[$field_name]]
							);
							if ($newval) {
								if ($newval !== $new_record[$field_name]) {
									$new_values[$field_name] = $newval;
								}
							} else {
								$update = false;
								$update_errors[] = [
									'field_name' => $field_name,
									'old' => $old_record[$field_name],
									'new' => $new_record[$field_name]
								];
							}
						}
					}
					if (!$update) {
						foreach ($update_errors as $index => $error) {
							foreach ($rec['fields'] as $no => $field) {
								if (empty($field['field_name'])) continue;
								if ($field['field_name'] !== $error['field_name']) continue;
								if (empty($field['title'])) {
									$update_errors[$index]['title'] = $field['field_name'];
								} else {
									$update_errors[$index]['title'] = $field['title'];
								}
							}
						}
						$error_text = '';
						foreach ($update_errors as $error) {
							$error_text .= sprintf('<li><strong>%s</strong>: %s &#8211; %s</li>', 
								$error['title'], $error['old'], $error['new']
							)."\n";
						}
						$msg[] = '<p class="error">'.zz_text('Merge not complete, records are different:').'</p>'
							.'<ul>'.$error_text.'</ul>';
						$error = true;
					} elseif (!$new_values) {
						// No changes in new record, so delete it
						$delete_old_records = true;
					}
				}
			}
			if ($update AND $new_values) {
				$update_values = [];
				foreach ($new_values as $field_name => $value) {
					$update_values[] = sprintf('`%s` = "%s"', $field_name, $value);
				}
				$sql = 'UPDATE %s SET %s WHERE %s = %d';
				$sql = sprintf($sql, $rec['table'],
					implode(', ', $update_values), $rec['id_field_name'], $new_id
				);
				$result = zz_db_change($sql, $new_id);
				if ($result['action'] === 'update') {
					$msg[] = sprintf(zz_text('Update entry in table %s (ID: %d)'),
						'<code>'.$rec['table'].'</code>', $old_id
					);
					$delete_old_records = true;
				} else {
					$msg[] = sprintf(zz_text('Update of entry in table %s failed with an error (ID: %d): %s'),
						'<code>'.$rec['table'].'</code>', $old_id, $result['error']['db_msg']
					);
					$error = true;
				}
			}
			if ($delete_old_records AND !$error) {
				foreach ($old_ids as $old_id) {
					$sql = sprintf($delete_sql, $old_id);
					$result = zz_db_change($sql, $old_id);
					if ($result['action'] === 'delete') {
						$msg[] = sprintf(zz_text('Deleted entry in table %s (ID: %d)'),
							'<code>'.$rec['table'].'</code>', $old_id
						);
						$uncheck = true;
					} else {
						$msg[] = sprintf(zz_text('Deletion of entry in table %s failed with an error (ID: %d): %s'),
							'<code>'.$rec['table'].'</code>', $old_id, $result['error']['db_msg']
						);
						$error = true;
					}
				}
			}
		}
	
	}
	if (!$error) {
		// everything okay, so don't output all the details
		$title = sprintf(zz_text('%d records merged successfully'), count($old_ids) + 1);
		$msg = [];
	} else {
		$title = '';
	}

	// @todo redirect on change
	// @todo show main records on error to compare manually
	return [
		'msg' => $msg, 'uncheck' => $uncheck, 'title' => $title
	];
}

/**
 * check if all required values are equal before merging
 *
 * @param array $table_def
 * @return array
 */
function zz_merge_equal($table_def) {
	global $zz_conf;

	$equal_fields = [];
	$equal_fields_titles = [];
	foreach ($table_def['fields'] as $field) {
		if (!empty($field['merge_equal'])) {
			$equal_fields[] = $field['field_name'];
			$equal_fields_titles[] = $field['title'];
		}
	}
	if (!$equal_fields) return [];

	$sql = sprintf('SELECT DISTINCT %s FROM %s.%s WHERE %s IN (%s)',
		implode(', ', $equal_fields), $zz_conf['db_name'],
		$table_def['table'], $table_def['id_field_name'], implode(', ', $table_def['ids'])
	);
	$distinct_records = zz_db_fetch($sql, '_dummy_', 'count');
	if ($distinct_records === 1) return [];

	if (count($equal_fields_titles) === 1) {
		$msg[] = '<p class="error">'.
			sprintf(zz_text('For merging, the field %s has to be equal in all records.'),
			'<em>'.$equal_fields_titles[0].'</em>'
		).'</p>';
	} else {
		$last_title = array_pop($equal_fields_titles);
		$msg[] = '<p class="error">'.
			sprintf(zz_text('For merging, the fields %s and %s have to be equal in all records.'),
			'<em>'.implode('</em>, <em>', $equal_fields_titles).'</em>',
			'<em>'.$last_title.'</em>'
		).'</p>';
	}
	return $msg;
}

/**
 * Check if different values might still get an update, because information
 * is missing in one of the records
 *
 * @param string $old_value
 * @param string $new_value
 * @param array $field
 * @return mixed false if no update is possible, string on update to that value
 */
function zz_merge_updateable($old_value, $new_value, $field) {
	switch ($field['type']) {
	case 'date': // ignore 00
		$old_date = zz_merge_get_date($old_value);
		$new_date = zz_merge_get_date($new_value);
		$updated_date = [];
		for ($i = 0; $i < 3; $i++) {
			if (empty($old_date[$i]) AND empty($new_date[$i])) {
				$updated_date[$i] = '00';
			} elseif (empty($old_date[$i])) {
				$updated_date[$i] = $new_date[$i];
			} elseif (empty($new_date[$i])) {
				$updated_date[$i] = $old_date[$i];
			} elseif ($old_date[$i] !== $new_date[$i]) {
				return false;
			} else { // equal, doesn't matter which value we take
				$updated_date[$i] = $old_date[$i];
			}
		}
		return implode('-', $updated_date);
	default:
		return false;
	}
}

/**
 * explodes a date in its parts, (year, month, day), ignores 00 fields
 * 
 * @param string $date
 * @return array
 */
function zz_merge_get_date($date) {
	$date = explode('-', $date);
	foreach ($date as $pos => $values) {
		if ($values === '00') unset($date[$pos]);
	}
	return $date;
}

/**
 * output merge message
 *
 * @param array $merge
 * @return string
 */
function zz_merge_message($merge) {
	$output = '<div id="record">'."\n";
	$output .= '<h2>'.($merge['title'] ? $merge['title'] : zz_text('Merge')).'</h2>'."\n";
	if ($merge['msg']) {
		$output .= implode('<br>', $merge['msg']);
	}
	$output .= '</div>'."\n";
	return $output;
}
