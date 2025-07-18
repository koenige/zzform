<?php 

/**
 * zzform
 * Action: update, delete, insert or review a record,
 * validation of user input, maintaining referential integrity
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * validates record, if validation is successful, writes record to database
 * if not, returns it to user
 *
 * - check whether fields are empty or not (default and auto values do not count)
 * - if all are empty = for subtables it's action = delete
 * - check if user input is valid
 * - if action = delete, check if referential integrity will be kept
 * - if everything is ok
 * 		- perform additional actions before doing sql query
 * 		- do sql query (queries, in case there are subtables)
 * 		- perform additional actions after doing sql query
 * @param array $ops
 * @param array $zz_tab
 * @param bool $validation
 * @param array $zz_record = $zz['record']
 * @global array $zz_conf
 * @return array ($ops, $zz_tab, $validation)
 */
function zz_action($ops, $zz_tab, $validation, $zz_record) {
	global $zz_conf;
	
	wrap_include('zzform/editing', 'custom/active');

	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	$zz_tab[0]['record_action'] = false;

	// hook, e. g. get images from different locations than upload
	list($ops, $zz_tab) = zz_action_hook($ops, $zz_tab, 'before_upload', 'not_validated');
	if ($ops['ignore'])
		return zz_return([$ops, $zz_tab, $validation]);
	
	//	### Check for validity, do some operations ###
	if (!empty($zz_record['upload_form'])) {
		// read upload image information, as required
		$zz_tab = zz_upload_get($zz_tab);
		if ($zz_record['action'] !== 'delete') {
			$ops['file_upload'] = $zz_tab[0]['file_upload'];
			// read upload image information, as required
			$zz_tab = zz_upload_prepare($zz_tab);
		} else {
			$ops['file_upload'] = false;
		}
	}
	if (zz_error_exit())
		return zz_return([$ops, $zz_tab, $validation]);

	zz_action_dependent_fields($zz_tab);
	$zz_tab = zz_action_validate($zz_tab);
	zz_action_unique_check($zz_tab);

	// hook, if an action directly after validation is required
	// e. g. geocoding
	if ($zz_record['action'] !== 'delete')
		list($ops, $zz_tab) = zz_action_hook($ops, $zz_tab, 'after_validation', 'validated');

	// check referential integrity
	if (wrap_setting('zzform_check_referential_integrity')) {
		// get table relations
		$relations = zz_integrity_relations();
		// get record IDs of all records in table definition (1 main, n sub records)
		$record_ids = zz_integrity_record_ids($zz_tab);
		// if no record IDs = no deletion is possible
		if ($record_ids) {
			// get record IDs of dependent records which have 'delete' set
			// in table relations
			$dependent_ids = zz_integrity_dependent_record_ids($zz_tab, $relations);
			// work with array_merge_recursive even if there are duplicate IDs
			// wrap_array_merge() would overwrite IDs
			$zz_tab[0]['integrity'] = zz_integrity_check(
				array_merge_recursive($record_ids, $dependent_ids), $relations
			);
			// return database errors
			if (zz_error_exit())
				return zz_return([$ops, $zz_tab, $validation]);
			// if something was returned, validation failed because there 
			// probably are records
			if (is_array($zz_tab[0]['integrity']) AND $zz_tab[0]['integrity']['msg_args']) {
				$validation = false;
			} elseif ($zz_record['upload_form']) {
				zz_integrity_check_files($dependent_ids);
				// @todo allow deletion of files as well
				// if there's no upload form in main record
				// this needs to be checked earlier on to include upload module
			}
		}
	}

	foreach ($zz_tab as $tab => $my_tab) {
		$my_recs = 0;
		foreach (array_keys($my_tab) as $rec) {
			if (!is_numeric($rec)) continue;
			if ($my_tab[$rec]['action'] === 'ignore') continue;
			if ($tab AND $my_tab['min_records_required']) {
				// add record count to check if we got enough of them
				if ($my_tab[$rec]['action'] !== 'delete') $my_recs++;
			}
			if ($my_tab[$rec]['validation']) continue;
			$validation = false;
		}
		// check if enough records, just for subtables ($tab = 1...n)
		if ($tab AND $my_recs < $my_tab['min_records_required']
			AND $zz_record['action'] !== 'delete') {
			// mark it!
			$zz_tab[0][0]['fields'][$my_tab['no']]['check_validation'] = false;
			// show error message
			if (empty($zz_tab[0][0]['fields'][$my_tab['no']]['dont_show_missing'])) {
				if (empty($zz_tab[0][0]['fields'][$my_tab['no']]['form_display']) OR $zz_tab[0][0]['fields'][$my_tab['no']]['form_display'] !== 'set') {
					zz_error_validation_log('msg', 'Minimum of records for table `%s` was not met (%d).');
					zz_error_validation_log('msg_args', wrap_text($zz_tab[0][0]['fields'][$my_tab['no']]['title'], ['source' => wrap_setting('zzform_script_path')]));
					zz_error_validation_log('msg_args', $my_tab['min_records_required']);
				} else {
					zz_error_validation_log('msg', 'Value missing in field <strong>%s</strong>.');
					zz_error_validation_log('msg_args', wrap_text($zz_tab[0][0]['fields'][$my_tab['no']]['title'], ['source' => wrap_setting('zzform_script_path')]));
				}
				zz_error_validation_log('log_post_data', true);
				if (is_numeric($rec))
					$zz_tab[$tab][$rec]['validation_error_logged'] = true;
			}
			foreach (array_keys($my_tab) as $rec) {
				if (!is_numeric($rec)) continue;
				foreach ($zz_tab[$tab][$rec]['fields'] as $no => $field) {
					if (empty($field['type'])) continue;
					if (in_array($field['type'], ['foreign_key', 'id', 'timestamp'])) continue;
					if (empty($field['required'])) continue;
					if (!empty($zz_tab[$tab][$rec]['POST'][$field['field_name']])) continue;
					$zz_tab[$tab][$rec]['fields'][$no]['check_validation'] = false;
				}
			}
			$validation = false;
		}
	}

	// check timeframe
	if ($zz_record['action'] === 'insert' AND $validation) {
		$validation = zz_action_timeframe($zz_record);
		if (!$validation) $zz_conf['int']['resend_form_required'] = true;
	}
	if (!empty($zz_conf['int']['resend_form_required']))
		$validation = false;
	
	if (!$validation) {
		if (!empty($zz_record['upload_form'])) zz_upload_cleanup($zz_tab, false); 
		return zz_return([$ops, $zz_tab, $validation]);
	}

	if (wrap_setting('debug')) zz_debug('validation successful');

	// put delete_ids into zz_tab-array to delete them
	foreach ($zz_tab as $tab => $my_tab) {
		if (!$tab) continue; // only subtables
		foreach ($my_tab['subtable_deleted'] AS $del_id) {
			foreach ($my_tab as $rec => $my_rec) {
				if (!is_numeric($rec)) continue;
				// ignore IDs which are already deleted
				if ($my_rec['id']['value'] == $del_id) continue 2;
			}
			unset($my_rec);
			$my_rec['action'] = 'delete';
			$my_rec['access'] = '';
			$my_rec['fields'] = [];
			$my_rec['id']['field_name'] = $my_tab['id_field_name'];
			$my_rec['id']['value'] = $del_id;
			$my_rec['POST'][$my_rec['id']['field_name']] = $del_id;
			$zz_tab[$tab][] = $my_rec;
			unset($my_rec);
		}
	}

	// hook, if any other action before insertion/update/delete is required
	list($ops, $zz_tab) = zz_action_hook($ops, $zz_tab, 'before_'.$zz_record['action'], 'planned');
	if (!empty($ops['no_validation'])) $validation = false;

	if (zz_error_exit()) { // repeat, might be set in before_action
		zz_error_exit(false);
		$validation = false;
	}
	if (!$validation) {
		// delete temporary unused files
		if (!empty($zz_record['upload_form'])) zz_upload_cleanup($zz_tab, false); 
		return zz_return([$ops, $zz_tab, $validation]);
	}

	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if ($zz_tab[$tab][$rec]['action'] !== 'insert' 
				AND $zz_tab[$tab][$rec]['action'] !== 'update') continue;
			if ($zz_tab[$tab][$rec]['access'] === 'show') continue;
			// do something with the POST array before proceeding
			$zz_tab[$tab][$rec] = zz_prepare_for_db(
				$zz_tab[$tab][$rec],
				'`'.$zz_tab[$tab]['db_name'].'`'.'.'.$zz_tab[$tab]['table']
			); 
		}
	}

	$sql_edit = '';
	foreach (array_keys($zz_tab) as $tab)
		foreach (array_keys($zz_tab[$tab]) as $rec) {
		if (!is_numeric($rec)) continue;
		if ($zz_tab[$tab][$rec]['action'] === 'ignore') continue;
		
		// get database name for query
		$me_db = '';
		if ($zz_tab[$tab]['db_name'] !== wrap_setting('db_name')) 
			$me_db = $zz_tab[$tab]['db_name'].'.';
		$me_sql = false;
		
	//	### Do nothing with the record, here: main record ###

		if ($zz_tab[$tab][$rec]['access'] === 'show') {
			$me_sql = 'SELECT 1';
		
	//	### Insert a record ###
	
		} elseif ($zz_tab[$tab][$rec]['action'] === 'insert') {
			$field_values = [];
			$field_list = [];
			foreach ($zz_tab[$tab][$rec]['fields'] as $field) {
				if (!$field['in_sql_query']) continue;
				if ($field['type'] === 'id') {
					// ID is empty anyways and will be set via auto_increment
					// unless 'import_id_value' is set
					if (empty($field['import_id_value'])) continue;
					if (empty($zz_conf['multi'])) continue;
					if (empty($zz_tab[$tab][$rec]['id']['value'])) continue;
					$field_list[] = '`'.$field['field_name'].'`';
					$field_values[] = $zz_tab[$tab][$rec]['id']['value'];
				} else {
					$field_list[] = '`'.$field['field_name'].'`';
					$field_values[] = $zz_tab[$tab][$rec]['POST_db'][$field['field_name']];
				}
			}
			$me_sql = sprintf(
				' INSERT INTO %s (%s) VALUES (%s)'
				, $me_db.$zz_tab[$tab]['table']
				, implode(', ', $field_list)
				, implode(', ', $field_values)
			);
			
	// ### Update a record ###

		} elseif ($zz_tab[$tab][$rec]['action'] === 'update') {
			$update_values = zz_action_equals($zz_tab[$tab][$rec]);
			if ($update_values) {
				$me_sql = sprintf(
					' UPDATE %s SET %s WHERE %s = %d'
					, $me_db.$zz_tab[$tab]['table']
					, implode(', ', $update_values)
					, $zz_tab[$tab][$rec]['id']['field_name']
					, $zz_tab[$tab][$rec]['id']['value']
				);
			} else {
				$me_sql = 'SELECT 1'; // nothing to update, just detail records
			}

	// ### Delete a record ###

		} elseif ($zz_tab[$tab][$rec]['action'] === 'delete') {
			// no POST_db, because here, validation is not necessary
			if (is_array($zz_tab[$tab][$rec]['id']['value'])) {
				$me_sql = sprintf(
					' DELETE FROM %s WHERE %s IN (%s) LIMIT %d'
					, $me_db.$zz_tab[$tab]['table']
					, $zz_tab[$tab][$rec]['id']['field_name']
					, implode(',', $zz_tab[$tab][$rec]['id']['value'])
					, count($zz_tab[$tab][$rec]['id']['value'])
				);
			} else {
				$me_sql = sprintf(
					' DELETE FROM %s WHERE %s = %d LIMIT 1'
					, $me_db.$zz_tab[$tab]['table']
					, $zz_tab[$tab][$rec]['id']['field_name']
					, $zz_tab[$tab][$rec]['id']['value']
				);
			}

	// ### Again, do nothing with the record, here: detail record

		} elseif ($zz_tab[$tab][$rec]['action'] == false) {
			continue;
		}
		
		if (!$sql_edit)
			$sql_edit = $me_sql;
		elseif ($zz_tab[$tab]['type'] === 'foreign_table')
			$foreign_sqls[$tab][$rec] = $me_sql;
		else
			$detail_sqls[$tab][$rec] = $me_sql;
	}
	// ### Perform database query and additional actions ###
	
	$del_msg = [];

	// if delete a record, first delete detail records so that in case of an 
	// error there are no orphans
	// 1. detail records from relations-table which need update
	// (foreign_id = NULL)
	if (!empty($zz_tab[0]['integrity']['updates'])) {
		foreach ($zz_tab[0]['integrity']['updates'] as $null_update) {
			$me_sql = 'UPDATE `%s`.`%s` SET `%s` = NULL WHERE `%s` IN (%s) LIMIT %d';
			$me_sql = sprintf($me_sql,
				$null_update['field']['detail_db'],
				$null_update['field']['detail_table'],
				$null_update['field']['detail_field'],
				$null_update['field']['detail_id_field'],
				implode(',', $null_update['ids']), count($null_update['ids'])
			);
			$id = false;
			if (count($null_update['ids']) === 1) {
				$id = array_shift($null_update['ids']);
			}
			$result = zz_db_change($me_sql, $id);
			if ($result['action']) {
				$del_msg[] = 'integrity update: '.$me_sql.'<br>';
			} else {
				$result['error']['msg'] = 'Detail record could not be updated';
				zz_error_log($result['error']);
			}
		}
	}

	// 2. detail records from relations-table which have to be deleted
	if (isset($dependent_ids)) {
		foreach ($dependent_ids as $db_name => $tables) {
			$me_db = '';
			if ($db_name !== wrap_setting('db_name')) 
				$me_db = '`'.$db_name.'`.';
			foreach ($tables as $table => $fields) {
				$id_field = key($fields);
				$ids = array_shift($fields);
				$ids = array_unique($ids); // cross-relations
				$me_sql = 'DELETE FROM %s%s WHERE `%s` IN (%s) LIMIT %d';
				$me_sql = sprintf($me_sql,
					$me_db, $table, $id_field, implode(',', $ids), count($ids)
				);
				$id = false;
				if (count($ids) === 1) $id = array_shift($ids);
				$result = zz_db_change($me_sql, $id);
				if ($result['action']) {
					$del_msg[] = 'integrity delete: '.$me_sql.'<br>';
				} else {
					$result['error']['msg'] = 'Detail record could not be deleted';
					zz_error_log($result['error']);
				}
			}
		}
	}

	// 3. detail records in form
	if ($zz_tab[0][0]['action'] === 'delete' AND isset($detail_sqls)) { 
		foreach (array_keys($detail_sqls) as $tab)
			foreach (array_keys($detail_sqls[$tab]) as $rec) {
				// might already deleted if in dependent IDs but that does not matter
				$result = zz_db_change(
					$detail_sqls[$tab][$rec], $zz_tab[$tab][$rec]['id']['value']
				);
				if ($result['action']) {
					$del_msg[] = 'zz_tab '.$tab.' '.$rec.': '.$detail_sqls[$tab][$rec].'<br>';
					unset($detail_sqls[$tab][$rec]);
					// save record values for use outside of zzform()
					$ops = zz_record_info($ops, $zz_tab, $tab, $rec);
				} else { // something went wrong, but why?
					$result['error']['msg'] = 'Detail record could not be deleted';
					zz_error_log($result['error']);
					$zz_tab[$tab][$rec]['error'] = $result['error'];
					// @todo not sure whether to cancel any further operations here
				}
			}
	}
	
	// 4. foreign IDs?
	$foreign_ids = [];
	if ($zz_tab[0][0]['action'] === 'insert' AND isset($foreign_sqls)) {
		foreach ($foreign_sqls as $tab => $foreign_sql) {
			$result = zz_db_change($foreign_sql[0], $zz_tab[$tab][0]['id']['value']);
			if ($result['action']) {
				$foreign_ids['[FOREIGN_KEY_'.$zz_tab[$tab]['no'].']'] = $result['id_value'];
				$zz_tab[$tab][0]['id']['value'] = $result['id_value'];
				$zz_tab[$tab][0]['POST'][$zz_tab[$tab][0]['id']['field_name']] = $result['id_value'];
			}
		}
		$sql_edit = zz_action_foreign_ids($sql_edit, $foreign_ids, $zz_tab[0][0]);
	}

	if (wrap_setting('debug')) {
		$ops['output'].= '<br>';
		$ops['output'].= 'Main ID value: '.$zz_conf['int']['id']['value'].'<br>';
		$ops['output'].= 'Main SQL query: '.$sql_edit.'<br>';
		if ($del_msg) {
			$ops['output'].= 'SQL deletion queries:<br>'.(implode('', $del_msg));
			unset($del_msg);
		}
	}

	$result = zz_db_change($sql_edit, $zz_conf['int']['id']['value']);
	if ($result['action']) {
		if ($zz_tab[0][0]['action'] === 'insert' AND $result['id_value']) {
			// for requery
			// id_value might be empty if import_id_value is set and field has no auto increment field
			$zz_conf['int']['id']['value'] = $result['id_value'];
		}
		$foreign_ids['[FOREIGN_KEY]'] = sprintf('%d', $zz_conf['int']['id']['value']); 
		// save record values for use outside of zzform()
		if ($result['action'] === 'nothing') {
			$zz_tab[0][0]['actual_action'] = 'nothing';
		}
		$ops = zz_record_info($ops, $zz_tab);
		$zz_tab[0]['record_action'] = true;
		if (isset($detail_sqls)) {
			list($zz_tab, $validation, $ops) 
				= zz_action_details($detail_sqls, $zz_tab, $validation, $ops, $foreign_ids);
		}
		zz_action_last_update($zz_tab, $result['action']);

		// if any other action after insertion/update/delete is required
		$change = zz_action_function('after_'.$zz_record['action'], $ops, $zz_tab);
		list($ops, $zz_tab) = zz_action_change($ops, $zz_tab, $change);

		$zz_tab[0]['folder'] = zz_foldercheck($zz_tab);

		if (!empty($zz_record['upload_form'])) {
			// upload images, delete images, as required
			$zz_tab = zz_upload_action($zz_tab);
			$error = zz_error();
			if ($error !== true) $ops['output'] .= $error;
			if (zz_error_exit()) {
				zz_upload_cleanup($zz_tab);
				return zz_return([$ops, $zz_tab, $validation]);
			}
			$change = zz_action_function('after_upload', $ops, $zz_tab);
			list($ops, $zz_tab) = zz_action_change($ops, $zz_tab, $change);
		}
		if ($zz_tab[0]['record_action'] === 'partial') {
			$validation = false;
			$ops['result'] = 'partial_'.$zz_tab[0][0]['action'];
		} elseif ($zz_tab[0]['record_action']) {
			$ops['result'] = 'successful_'.$zz_tab[0][0]['action'];
			$change = zz_action_function('successful_'.$zz_tab[0][0]['action'], $ops, $zz_tab);
			list($ops, $zz_tab) = zz_action_change($ops, $zz_tab, $change);
		}
	} else {
		// Output Error Message
		if ($zz_record['action'] === 'insert') {
			// for requery
			$zz_conf['int']['id']['value'] = false;
		}
		$result['error']['level'] = E_USER_WARNING;
		zz_error_log($result['error']);
		$zz_tab[0][0]['error'] = $result['error'];
		$ops = zz_record_info($ops, $zz_tab);
		$validation = false; // show record again!
	}
	if ($ops['result'] === 'successful_update') {
		$update = false;
		foreach ($ops['return'] as $my_table) {
			// check for action in main record and detail records
			if ($my_table['action'] !== 'nothing') $update = true;
		}
		if (!$update) $ops['result'] = 'no_update';
	}
	if (empty($ops['result'])) $ops['id'] = 0;

	foreach (array_keys($zz_tab) as $tab) {
		if (!is_numeric($tab)) continue;
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if (empty($zz_tab[$tab][$rec]['password_change_successful'])) continue;
			if ($ops['result'] === 'successful_update')
				$ops['password_changed'] = true;
			elseif ($ops['result'] === 'successful_insert')
				$ops['password_added'] = true;
			unset($zz_tab['password_change_successful']);
		}
	}
	
	// delete temporary unused files
	if (!empty($zz_record['upload_form'])) zz_upload_cleanup($zz_tab);
	return zz_return([$ops, $zz_tab, $validation]);
}

/**
 * change timestamp if wanted after an update of detail records
 * if main record was not changed
 *
 * @param array $zz_tab
 * @param string $action
 * @return bool
 */
function zz_action_last_update($zz_tab, $action) {
	if ($action !== 'nothing') return false;
	if (empty($zz_tab[0]['subrecord_action'])) return false;
	foreach ($zz_tab[0][0]['fields'] as $field) {
		if ($field['type'] !== 'timestamp') continue;
		if (empty($field['update_on_detail_update'])) continue;
		$sql = 'UPDATE %s SET %s = NOW() WHERE %s = %d';
		$sql = sprintf($sql,
			$zz_tab[0]['table'],
			$field['field_name'],
			$zz_tab[0][0]['id']['field_name'],
			$zz_tab[0][0]['id']['value']
		);
		$result = zz_db_change($sql, $zz_tab[0][0]['id']['value']);
		if ($result['action'] !== 'update') {
			zz_error_log([
				'msg_dev' => 'Update of timestamp failed (ID %d), query: %s',
				'msg_dev_args' => [$zz_tab[0][0]['id']['value'], $sql]
			]);
		}
	}
	if (!empty($result)) return true;
	return false;
}

/**
 * checks updates if existing values are equal to sent values
 * if yes, no update is necessary
 *
 * @param array $my_rec ($zz_tab[$tab][$rec])
 * @return array $update_values
 */
function zz_action_equals($my_rec) {
	$update_values = [];
	$extra_update_values = [];
	$equal = true; // old and new record are said to be equal

	foreach ($my_rec['fields'] as $field) {
		if (in_array($field['type'], ['id', 'subtable', 'foreign_table'])) continue;
		if (!$field['in_sql_query']) continue;

		// check if field values are different to existing record
		if ($field['type'] === 'timestamp') {
			$field['dont_check_on_update'] = true;
			$update = true;
		} elseif (in_array($field['field_name'], array_keys($my_rec['POST']))
		AND !empty($my_rec['existing'])) {
			// ok, we have values which might be compared
			$update = true;
			// check difference to existing record
			$post = $my_rec['POST'][$field['field_name']];
			if ($field['type'] === 'time'
			AND $my_rec['existing'][$field['field_name']]
			AND strlen($my_rec['existing'][$field['field_name']]) === 5) {
				// time might be written as 08:00 instead of 08:00:00
				$my_rec['existing'][$field['field_name']] .= ':00';
			}
			if ($field['type'] === 'select' AND !empty($field['set'])) {
				// to compare it, make array into string
				if (is_array($post)) $post = implode(',', $post);
			}
			if (!isset($my_rec['existing'][$field['field_name']])) {
				// it's important to test against the prepared string value
				// of 'NULL' here, because allowed 0 and '' are already
				// checked for this
				if ($my_rec['POST_db'][$field['field_name']] != 'NULL') {
					// there's no existing record, send this query
					$equal = false;
				} else {
					// existing and new value are both NULL or not there
					$update = false;
				}
			} elseif ($field['type'] === 'number'
				OR (!empty($field['type_detail']) AND $field['type_detail'] === 'number')
			) {
				// values of type 'number' have to be numeric
				// check if they are, and then, check if they are equal
				// for numbers: 004 = 4, 28.00 = 28
				if (!is_numeric($post)) {
					$equal = false;
				} elseif (!is_numeric($my_rec['existing'][$field['field_name']])) {
					$equal = false;
				} elseif ($post != $my_rec['existing'][$field['field_name']]) {
					$equal = false;
				} else {
					$update = false;
				}
			} elseif (zz_get_fieldtype($field) === 'ip') {
				if (inet_ntop(inet_pton($post)) === $my_rec['existing'][$field['field_name']]) continue;
				else $equal = false;
			} elseif ($post.'' !== $my_rec['existing'][$field['field_name']].'') {
				// we need to append '' here to compare strings and
				// not numbers (004 != 4)
				// there's a difference, so we have to send this query
				$equal = false;
			} else {
				// we don't know yet from this one
				// whether to send the query or not, but we do not
				// need to send the values for this field since they
				// are equal
				$update = false;
			}
		} elseif ($my_rec['id']['value'] < 0) {
			// revision, no existing record
			$update = true;
		} else {
			// we have an update but no existing record
			zz_error_log([
				'msg_dev' => 'Update without existing record? Record: %s',
				'msg_dev_args' => [json_encode($my_rec)]
			]);
			$update = true;
		}
		$query = sprintf('`%s` = %s', $field['field_name'], $my_rec['POST_db'][$field['field_name']]);
		if (!$update) continue;
		if (!empty($field['dont_check_on_update'])) {
			$extra_update_values[] = $query;
			continue;
		}
		$update_values[] = $query;
	}
	if ($update_values AND !$equal) {
		$update_values = array_merge($update_values, $extra_update_values);
		return $update_values;
	}
	return [];
}

/**
 * updates or inserts detail records belonging to the main record
 *
 * @param string $detail_sqls
 * @param array $zz_tab
 * @param bool $validation
 * @param array $ops
 * @param array $foreign_ids: pairs of [FOREIGN_KEY] = ID
 * @return array
 *		$zz_tab, $validation, $ops
 */
function zz_action_details($detail_sqls, $zz_tab, $validation, $ops, $foreign_ids) {
	foreach (array_keys($detail_sqls) as $tab) {
		foreach (array_keys($detail_sqls[$tab]) as $rec) {
			$my_rec = $zz_tab[$tab][$rec];
			$sql = $detail_sqls[$tab][$rec];
			$sql = zz_action_foreign_ids($sql, $foreign_ids, $my_rec);
			if (!empty($zz_tab[$tab]['detail_key'])) {
				// @todo allow further detail keys
				// if not all files where uploaded, go up one detail record until
				// we got an uploaded file
				$detail_tab = $zz_tab[$tab]['detail_key'][0]['tab'];
				while (empty($zz_tab[$detail_tab][$zz_tab[$tab]['detail_key'][0]['rec']]['id']['value'])) {
					$zz_tab[$tab]['detail_key'][0]['rec']--;
				}
				$sql = str_replace('[DETAIL_KEY]'
					, sprintf('%d', $zz_tab[$detail_tab][$zz_tab[$tab]['detail_key'][0]['rec']]['id']['value'])
					, $sql
				);
			}
			// for deleted subtables, id value might not be set, so get it here.
			// @todo check why it's not available beforehands, might be 
			// unnecessary security risk.
			if (empty($my_rec['id']['value'])
				AND !empty($my_rec['POST'][$my_rec['id']['field_name']]))
				$zz_tab[$tab][$rec]['id']['value'] = $my_rec['POST'][$my_rec['id']['field_name']];

			$result = zz_db_change($sql, $my_rec['id']['value']);
			if (!$result['action']) { 
				// This should never occur, since all checks say that 
				// this change is possible
				// only if duplicate entry
				$result['error']['msg'] = 'There was a problem with the detail record.';
				$result['error']['level'] = E_USER_WARNING;
				$result['error']['msg_dev'] = 'Query: %s';
				$result['error']['msg_dev_args'][] = $sql;
				if (empty($result['error']['query']))
					$result['error']['query'] = $sql;
				zz_error_log($result['error']);
				$zz_tab[$tab][$rec]['error'] = $result['error'];
				// main record was already inserted or updated, log as partial
				$zz_tab[0]['record_action'] = 'partial';
				$validation = false;
				if (strstr($zz_tab[$tab]['no'], '-')) {
					$nos = explode('-', $zz_tab[$tab]['no']);
					$zz_tab[0][0]['fields'][$nos[0]]['check_validation'] = false;
				} else {
					$zz_tab[0][0]['fields'][$zz_tab[$tab]['no']]['check_validation'] = false;
				}
			} elseif ($my_rec['action'] === 'insert') {
				// for requery
				$zz_tab[$tab][$rec]['id']['value'] = $result['id_value'];
			}
			// save record values for use outside of zzform()
			if ($result['action'] === 'nothing') {
				$zz_tab[$tab][$rec]['actual_action'] = 'nothing';
			} elseif ($result['action']) {
				$zz_tab[0]['subrecord_action'] = true;
			}
			$ops = zz_record_info($ops, $zz_tab, $tab, $rec);
			if (wrap_setting('debug')) {
				$ops['output'] .= 'SQL query for record '.$tab.'/'.$rec.': '.$sql.'<br>';
			}
		}
	}
	return [$zz_tab, $validation, $ops];
}

/**
 * replace foreign ID placeholders with real IDs
 *
 * @param string $sql
 * @param array $foreign_ids
 * @param array $my_rec
 * @return string
 */
function zz_action_foreign_ids($sql, $foreign_ids, $my_rec) {
	if (empty($my_rec['foreign_key_placeholders'])) return $sql;
	foreach ($my_rec['foreign_key_placeholders'] as $placeholder) {
		if (!array_key_exists($placeholder, $foreign_ids))
			return 'SELECT 1'; // no replacement possible, @todo check if NULL values should be possible
		$sql = str_replace($placeholder, $foreign_ids[$placeholder], $sql);
	}
	return $sql;
}

/**
 * call hook functions for a given position in the code
 *
 * @param array $ops
 * @param array $zz_tab
 * @param string $position
 * @param string $type for @see zz_record_info()
 */
function zz_action_hook($ops, $zz_tab, $position, $type) {
	if (empty($zz_tab[0]['hooks'][$position])
		AND empty($zz_tab[0]['triggers'][$position]))
		return [$ops, $zz_tab];

	// get information
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			$ops = zz_record_info($ops, $zz_tab, $tab, $rec, $type);
		}
	}

	// check if something is about to change
	$change = zz_action_function($position, $ops, $zz_tab);
	if (!$change) return [$ops, $zz_tab];

	// apply changes
	list($ops, $zz_tab) = zz_action_change($ops, $zz_tab, $change);
	unset($ops[$type]);
	unset($ops['record_old']);
	unset($ops['record_new']);
	unset($ops['record_diff']);
	return [$ops, $zz_tab];
}

/**
 * calls a function or includes a file before or after an action takes place
 *
 * @param string $type (upload, before_insert, before_update, before_delete,
 *	after_insert, after_update, after_delete, to be set in $zz['hooks']
 * @param array $ops
 * @param array $zz_tab
 * @global array $zz_conf
 * @return mixed bool true if some action was performed; 
 *	array $change if some values need to be changed
 */
function zz_action_function($type, $ops, $zz_tab) {
	static $hook_files = [];

	if (empty($zz_tab[0]['hooks'][$type])) {
		if (!empty($zz_tab[0]['triggers'][$type]))
			zz_action_trigger($zz_tab[0]['triggers'][$type]);
		return false;
	}

	if (!$hook_files)
		$hook_files = wrap_include('zzform/hooks', 'custom/active');

	$change = [];
	foreach ($zz_tab[0]['hooks'][$type] as $hook) {
		if (str_starts_with($hook, 'zz_')) {
			// internal hooks get access to $zz_tab as well
			$custom_result = $hook($ops, $zz_tab);
		} else {
			if (str_starts_with($hook, 'mf_')) {
				// module hook
				$module = explode('_', $hook);
				$module = $module[1];
				wrap_include('zzform/hooks', $module);
			} else {
				$file = str_replace('_', '-', $hook);
				if (str_starts_with($file, 'my-')) $file = substr($file, 3);
				wrap_include('zzform/'.$file, 'custom');
			}
			$found = true;
			if (!function_exists($hook)) {
				if (wrap_setting('active_module') AND str_starts_with($hook, 'my_')) {
					$active_mod_prefix = sprintf('mod_%s_', wrap_setting('active_module'));
					$module_hook = str_replace('my_', $active_mod_prefix, $hook);
					if (function_exists($module_hook)) $hook = $module_hook;
					else $found = false;
				} else {
					$found = false;
				}
			}
			if (!$found) {
				zz_error_log([
					'msg_dev' => 'Hook function %s was not found. Continuing without hook.',
					'msg_dev_args' => [$hook],
					'level' => E_USER_WARNING
				]);
				return false;
			}
			$custom_result = $hook($ops);
		}
		if (!is_array($custom_result)) continue;
		$change = wrap_array_merge($change, $custom_result);
	}

	if (!empty($zz_tab[0]['triggers'][$type]))
		zz_action_trigger($zz_tab[0]['triggers'][$type]);

	if (!$change) return true;
	$record_replace = [
		'before_upload', 'after_validation', 'before_insert', 'before_update'
	];
	if (!in_array($type, $record_replace)) {
		if (array_key_exists('record_replace', $change)
			AND !empty($change['record_replace'])) {
			unset($change['record_replace']);
			zz_error_log([
				'msg_dev' => 'Function for hook (%s) tries to set record_replace. Will not be evaluated at this point.',
				'msg_dev_args' => [$type]
			]);
		}
	}
	return $change;
}

/**
 * if the action function returned something, output or record for database
 * will be changed
 *
 * @param array $ops
 * @param array $zz_tab
 * @param array $change string 'output', array 'record_replace',
 *		array 'validation_fields', bool 'no_validation', array 'integrity_delete',
 *		array 'change_info', string 'output_form'
 * @return array [$ops, $zz_tab]
 */
function zz_action_change($ops, $zz_tab, $change) {
	if (!$change) return [$ops, $zz_tab];
	if ($change === true) return [$ops, $zz_tab];
	
	// output?
	if (!empty($change['output'])) {
		// before form
		$ops['output'] .= $change['output'];
	}
	if (!empty($change['output_form'])) {
		// inside form
		$ops['form'] = $change['output_form'];
	}
	
	// ignore?
	if (!empty($change['ignore'])) {
		$ops['ignore'] = true;
		return [$ops, $zz_tab];
	}

	// get record definition from planned or not_validated
	if (!empty($ops['planned'])) $planned = $ops['planned'];
	elseif (!empty($ops['validated'])) $planned = $ops['validated'];
	else $planned = $ops['not_validated'];

	// invalid?
	if (!empty($change['no_validation'])) {
		$ops['no_validation'] = true;
		// validation message
		// = $change['no_validation_msg'];
		// mark invalid fields (reselect or error)
		if (!empty($change['validation_fields'])) {
			foreach ($change['validation_fields'] as $index => $fields) {
				list($tab, $rec) = explode('-', $planned[$index]['tab-rec']);
				foreach ($zz_tab[$tab][$rec]['fields'] as $no => $field) {
					if (empty($field['field_name'])) continue;
					if (!array_key_exists($field['field_name'], $fields)) continue;
					if (!empty($fields[$field['field_name']]['class'])) {
						$zz_tab[$tab][$rec]['fields'][$no]['class'][] = $fields[$field['field_name']]['class'];
						if ($fields[$field['field_name']]['class'] === 'reselect')
							$zz_tab[$tab][$rec]['fields'][$no]['mark_reselect'] = true;
					}
					if (!empty($fields[$field['field_name']]['explanation'])) {
						if (empty($zz_tab[$tab][$rec]['fields'][$no]['explanation']))
							$zz_tab[$tab][$rec]['fields'][$no]['explanation'] = '';
						$zz_tab[$tab][$rec]['fields'][$no]['explanation'] .= wrap_text($fields[$field['field_name']]['explanation'], ['source' => wrap_setting('zzform_script_path')]);
					}
				}
			}
		}
	}
	
	if (!empty($change['integrity_delete'])) {
		if (empty($zz_tab[0]['integrity_delete']))
			$zz_tab[0]['integrity_delete'] = [];
		$zz_tab[0]['integrity_delete'] = array_merge_recursive($zz_tab[0]['integrity_delete'], $change['integrity_delete']);
	}
	
	// record? replace values as needed
	if (!empty($change['record_replace'])) {
		// replace values
		foreach ($change['record_replace'] as $index => $values) {
			list($tab, $rec) = explode('-', $planned[$index]['tab-rec']);
			$zz_tab[$tab][$rec]['POST'] = array_merge($zz_tab[$tab][$rec]['POST'], $values);
			if (!empty($change['change_info'][$index])) {
				$zz_tab[$tab][$rec]['change_info'] = $change['change_info'][$index];
			}
			// remove 'possible_values' if set
			zz_action_change_reset_possible_values($zz_tab[$tab][$rec]['fields'], $values);
			$zz_tab[$tab][$rec]['was_validated'] = false;
			if (!empty($change['no_check_select_fields'][$index])) {
				foreach ($change['no_check_select_fields'][$index] as $field_name) {
					$key = array_search($field_name, $zz_tab[$tab][$rec]['check_select_fields']);
					if ($key !== false)
						unset($zz_tab[$tab][$rec]['check_select_fields'][$key]);
				}
		
			}
		}
		// revalidate, but not if no validation has taken place before
		if (!array_key_exists('not_validated', $ops))
			$zz_tab = zz_action_validate($zz_tab);
	}
	return [$ops, $zz_tab];
}

/**
 * reset possible_values if record_replace has set a new value
 *
 * @param array $fields
 * @param array $values
 */
function zz_action_change_reset_possible_values(&$fields, $values) {
	foreach ($values as $field_name => $value) {
		foreach ($fields as $field_no => $field) {
			if (empty($field['field_name'])) continue;
			if ($field['field_name'] !== $field_name) continue;
			if (empty($field['possible_values'])) continue;
			$fields[$field_no]['possible_values'] = [$value];
		}
	}
}

/**
 * trigger URL at a certain point in action process
 *
 * @param array $triggers list of URLs
 */
function zz_action_trigger($triggers) {
	foreach ($triggers as $trigger) {
		$data = wrap_job($trigger);
	}
}

/**
 * check if form with new record is sent after a certain timeframe
 *
 * @param array $zz_record = $zz['record']
 * @return bool false: no validation, resend form
 */
function zz_action_timeframe($zz_record) {
	global $zz_conf;
	if (!empty($zz_conf['multi'])) return true;
	if (!empty($_SESSION['logged_in'])) return true; // just for public forms
	if (!empty($zz_record['no_timeframe'])) return true;

	$timeframe = zz_secret_id('timecheck');
	// @todo calculate timeframe based on required fields, e. g. 2 seconds per field
	$min_seconds_per_form = 5;
	if ($timeframe > $min_seconds_per_form) return true;
	return false;
}

/**
 * Defines which action will be performed on subrecord
 *
 *	1.	action = insert | update | delete
 *		update if id field is not empty and there are values in fields
 *		insert if there is no id field and there are values in fields
 *		delete if there is nothing written in field, default- or value-fields
 *			might be ignored with def_val_ingore
 *	2.	in case of action delete check whether subrecord is in database
 *	2a.	if yes: mark for deletion
 *	2b.	if no: just remove it from the cache
 *	3.	in case main record shall be deleted, do the same as in 2a and 2b.
 *
 * @param array $zz_tab
 * @param int $tab
 * @param int $rec
 * @return array $my_tab ($zz_tab[$tab])
 *		changed: $zz_tab[$tab][$rec]['action'], $zz_tab[$tab]['subtable_deleted']
 *		may unset($zz_tab[$tab][$rec])
 */
function zz_set_subrecord_action($zz_tab, $tab, $rec) {
	// initialize variables
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	$values = '';
	$my_tab = $zz_tab[$tab];

	// check whether there are values in fields
	// this is done to see what to do with subrecord (insert, update, delete)
	foreach ($my_tab[$rec]['fields'] as $field) {
		// depending on ID, set action
		if ($field['type'] !== 'id') continue;
		if (!isset($my_tab[$rec]['POST'][$field['field_name']])
			OR $my_tab[$rec]['POST'][$field['field_name']] === "''")
			$my_tab[$rec]['action'] = 'insert';
		else
			$my_tab[$rec]['action'] = 'update';
	}

	foreach ($my_tab[$rec]['fields'] as $f => $field) {
		// check if some values should be gotten from detail_value/upload fields
		// must be here before setting the action
		if ($zz_tab[$tab][$rec]['access'] === 'show') continue;
		if (!in_array($zz_tab[0][0]['action'], ['insert', 'update'])) continue;
		$value = zz_write_values($field, $zz_tab, $f, $tab, $rec);
		if (isset($value)) $my_tab[$rec]['POST'][$field['field_name']] = $value;
	}

	foreach ($my_tab[$rec]['fields'] as $field) {
		// write_onces
		if ($field['type'] === 'write_once' AND !empty($my_tab[$rec]['existing'][$field['field_name']])) {
			$values .= $my_tab[$rec]['existing'][$field['field_name']];
			continue;
		}
		// check if something was posted and write it down in $values
		// so we know later, if this record should be added
		if ($field['type'] === 'subtable') continue;
		if (!isset($my_tab[$rec]['POST'][$field['field_name']])) continue;
		$fvalues = $my_tab[$rec]['POST'][$field['field_name']];
		// some fields will always be ignored since there is no user input
		$ignores = [
			'timestamp', 'id', 'foreign_key', 'translation_key', 'display', 'image'
		];
		if (in_array($field['type'], $ignores)) continue;
		if (!empty($field['for_action_ignore'])) continue;
		// check def_val_ignore, some auto values/values/default values will be ignored 
		if (!empty($field['def_val_ignore'])) {
			if (empty($field['value']) AND zz_has_default($field)
				AND $field['default'] != trim($my_tab[$rec]['POST'][$field['field_name']])) {
			// defaults will only be ignored if different from default value
			// but only if no value is set!
				$values .= $fvalues;				
			} else {
			// values need not to be checked, they'll always be ignored
			// (and it's not easy to check them because they might be in a 'values'-array)
				$values .= '';
			}
		} else {
			if (is_array($fvalues) AND (!empty($field['set']) OR !empty($field['enum']))) {
				if (count($fvalues) !== 1 OR !empty($fvalues[0])) {
					$values .= json_encode($fvalues);
				}
			} elseif (is_array($fvalues)) {
				$values .= json_encode($fvalues);
			} else {
				if (!empty($field['null']) AND $fvalues === '0') $fvalues .= 'null';
				$values .= $fvalues;
			}
		}
	}

	// show records with upload error again
	if (!empty($my_tab[$rec]['file_upload_error'])) $values = true;
	// do not overwrite this if it is a foreign table, otherwise, if it is a subtable
	// overwrite this
	if (!empty($my_tab[$rec]['file_upload_error']) AND $my_tab['type'] === 'foreign_table')
		$values = true;
	elseif (!empty($my_tab['records_depend_on_upload']) 
		AND !empty($my_tab[$rec]['no_file_upload'])) {
		$values = false;
	} elseif (!empty($my_tab['records_depend_on_upload']) 
		AND $my_tab[$rec]['action'] === 'insert'
		AND empty($my_tab[$rec]['file_upload'])) {
		$values = false;
	} elseif (!empty($my_tab['records_depend_on_upload_more_than_one']) 
		AND $my_tab[$rec]['action'] === 'insert'
		AND empty($my_tab[$rec]['file_upload']) AND $rec) {
		$values = false;
	}
	
	// foreign action, no values?
	if (zz_foreign_id_action($my_tab[$rec]['fields'], $zz_tab)) {
		$values = true;
	}

	// @todo seems to be twice the same operation since $tab and $rec are !0
	if ($my_tab['access'] === 'show' OR
		(!empty($my_tab[$rec]['access']) AND $my_tab[$rec]['access'] === 'show')) {
		$values = true; // only display subrecords, no deletion, no change!
		$my_tab[$rec]['action'] = false; // no action insert or update, values are only shown!
	}
	if (!$values) {
		if ($my_tab[$rec]['id']['value']) {
			$my_tab[$rec]['action'] = 'delete';
 			// if main tab will be deleted, this record will be deleted anyways
 			if ($zz_tab[0][0]['action'] !== 'delete') {
 				// only for requery record on error!
				$my_tab['subtable_deleted'][] = $my_tab[$rec]['id']['value'];
			}
		} else {
			$my_tab[$rec]['action'] = 'ignore';
		}
	}
	
	if ($zz_tab[0][0]['action'] === 'delete') {
		if ($my_tab[$rec]['id']['value'] 			// is there a record?
			&& empty($my_tab['keep_detailrecord_shown']))		
			$my_tab[$rec]['action'] = 'delete';
		else									// no data in subtable
			$my_tab[$rec]['action'] = 'ignore';
	}

	if (wrap_setting('debug'))
		zz_debug(sprintf('end table %s, rec %d, values: %s', $my_tab['table_name'], $rec, substr($values, 0, 20)));
	return $my_tab;
}

/**
 * check for record with foreign_id if record that this is dependent on will be added
 *
 * @param array $fields
 * @param array $zz_tab
 * @return bool
 */
function zz_foreign_id_action($fields, $zz_tab) {
	foreach ($fields as $field) {
		if (empty($field['foreign_id_field'])) continue;
		foreach ($zz_tab as $tab => $my_tab) {
			if (!is_numeric($tab)) continue;
			if (empty($my_tab['no'])) continue;
			if ($my_tab['no'].'' !== $field['foreign_id_field'].'') continue;
			
			foreach ($my_tab as $rec => $my_rec) {
				if (!is_numeric($rec)) continue;
				if ($my_rec['action'] === 'insert') return true;
			}
		}
	}
	return false;
}

/**
 * get values from 'value', 'default' or 'upload'
 *
 * @param array $field
 * @param array $zz_tab
 * @param array $f
 * @param int $tab
 * @param int $rec
 */
function zz_write_values($field, $zz_tab, $f, $tab = 0, $rec = 0) {
	$return_val = NULL;
	//	copy value if field detail_value isset
	if (!empty($field['detail_value'])) {
		$value = zz_write_detail_values($zz_tab, $f, $tab, $rec);
		if ($value) $return_val = $value;
	}
	// check if some values should be gotten from upload fields
	if (!empty($field['upload_field']) AND zz_modules('upload')) {
		$value = zz_write_upload_fields($zz_tab, $f, $tab, $rec);
		if ($value) $return_val = $value;
	}
	if (!isset($return_val) AND !empty($field['upload_default']))
		$return_val = $field['upload_default'];
	return $return_val;
}

/**
 * Preparation of record for database
 *
 * checks last fields which rely on fields beforehand; field type set; sets
 * slashes; NULL and 0; foreign key, translation key, detail key and timestamp
 * @param array $my_rec = $zz_tab[$tab][$rec]
 * @param string $db_table [db_name.table]
 * @return array $my_rec with validated values and marker if validation was successful 
 * 		($my_rec['validation'])
 */
function zz_prepare_for_db($my_rec, $db_table) {
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	
	$my_rec['POST_db'] = $my_rec['POST'];
	foreach ($my_rec['fields'] as $f => $field) {
		if (!empty($field['display_only'])) continue;
		if (!empty($field['input_only'])) continue;

		$field_name = $field['field_name'] ?? '';
	// text: convert encoding for some field types
		if (in_array($field['type'], ['text', 'memo'])) {
			$my_rec['POST'][$field_name]
				= $my_rec['POST_db'][$field_name] 
				= wrap_convert_string($my_rec['POST_db'][$field_name]);
		}

	//	numbers
	//	factor for avoiding doubles
		if ($field['type'] === 'number' 
			AND isset($field['factor']) && $my_rec['POST'][$field_name]) {
			// we need factor and rounding in POST as well because otherwise
			// we won't be able to check against existing record
			$my_rec['POST'][$field_name] *= $field['factor'];
			$my_rec['POST'][$field_name] = round($my_rec['POST'][$field_name], 0);
			$my_rec['POST_db'][$field_name] = $my_rec['POST'][$field_name];
		}
	//	set
		if ($field['type'] === 'select' 
			AND (isset($field['set']) OR isset($field['set_sql'])
			OR isset($field['set_folder']))) {
			if (!empty($my_rec['POST'][$field_name])) {
				// remove empty set values, we needed them to check whether 
				// set is NULL or ""
				if (isset($my_rec['POST'][$field_name][0])
					AND !($my_rec['POST'][$field_name][0]))
					array_shift($my_rec['POST'][$field_name]);
				if (is_array($my_rec['POST'][$field_name]))
					$my_rec['POST_db'][$field_name] = implode(',', $my_rec['POST'][$field_name]);
			} else
				$my_rec['POST_db'][$field_name] = '';
		}
	//	password: remove unencrypted password
		if ($field['type'] === 'password')
			unset($my_rec['POST_db']['zz_unencrypted_'.$field_name]);

		switch ($field['type']) {
		case 'foreign_key':
			$sno = strstr($field['subtable_no'], '-') ? '_'.substr($field['subtable_no'], 0, strpos($field['subtable_no'], '-')): '';
			$my_rec['foreign_key_placeholders'][] = 
			$my_rec['POST_db'][$field_name] = '[FOREIGN_KEY'.$sno.']';
			break;
		case 'foreign_id':
			$my_rec['foreign_key_placeholders'][] = 
			$my_rec['POST_db'][$field_name] = '[FOREIGN_KEY_'.$field['foreign_id_field'].']';
			break;
		case 'detail_key':
			$my_rec['POST_db'][$field_name] = '[DETAIL_KEY]';
			break;
		case 'translation_key':
			if (isset($field['translation_key'])) // not set if multi copy
				$my_rec['POST_db'][$field_name] = $field['translation_key'];
			break;
		case 'timestamp':
			$my_rec['POST_db'][$field_name] = 'NOW()';
			break;
		case 'calculated':
		case 'image':
		case 'upload_image':
		case 'id':
		case 'foreign':
		case 'subtable':
		case 'foreign_table':
		case 'display':
		case 'option':
		case 'write_once':
		case 'list_function':
		case 'captcha':
			// dont' do anything with these
			break;
		case 'geo_point':
			$my_rec['POST_db'][$field_name] = sprintf(
				'GeomFromText("%s")', 
				wrap_db_escape($my_rec['POST_db'][$field_name])
			);
			break;
		default:
			//	slashes, 0 and NULL
			if ($my_rec['POST_db'][$field_name]) {
				if (zz_get_fieldtype($field) === 'ip') {
					if (mysqli_get_server_info(wrap_db_connection()) >= '5.6.0') {
						$my_rec['POST_db'][$field_name] = sprintf(
							'INET6_ATON("%s")', 
							wrap_db_escape($my_rec['POST_db'][$field_name])
						);
					} else {
						$my_rec['POST_db'][$field_name] = sprintf(
							'UNHEX("%s")', wrap_db_escape(bin2hex(inet_pton($my_rec['POST_db'][$field_name])))
						);
					}
				} elseif (!zz_db_numeric_field($db_table, $field_name)) {
					$my_rec['POST_db'][$field_name] = sprintf(
						'"%s"', wrap_db_escape($my_rec['POST_db'][$field_name])
					);
				}
			} else {
				// empty values = NULL, treat some special cases differently
				// latitude/longitude: type string, different from 0
				if (isset($field['number_type']) AND $my_rec['POST'][$field_name] !== ''
					AND in_array($field['number_type'], ['latitude', 'longitude']))
					$my_rec['POST_db'][$field_name] = '0';
				elseif (!empty($field['null']) AND $my_rec['POST'][$field_name] !== '') 
					$my_rec['POST_db'][$field_name] = '0';
				elseif (!empty($field['null_string'])) 
					$my_rec['POST_db'][$field_name] = '""';
				else 
					$my_rec['POST_db'][$field_name] = 'NULL';
			}
		}
	}
	return zz_return($my_rec);
}

/**
 * save record information in array for return and use in user action-scripts 
 *
 * @param array $ops
 * @param array $my_rec $zz_tab[$tab][$rec]
 * @param int $tab
 * @param int $rec
 * @param string $type (optional) 'return', 'planned'
 * @return array $ops
 *		'return' ('action' might be nothing if update, but nothing was updated),
 *		'record_new', 'record_old'
 */
function zz_record_info($ops, $zz_tab, $tab = 0, $rec = 0, $type = 'return') {
	if ($type !== 'validated' AND $zz_tab[$tab][$rec]['action'] === 'ignore') return $ops;
	
	if (!isset($ops['record_new'])) $ops['record_new'] = [];
	if (!isset($ops['record_old'])) $ops['record_old'] = [];
	if (!isset($ops['record_diff'])) $ops['record_diff'] = [];
	
	$rn = [];
	$ro = [];
	
	// set index to make sure that main record is always 0
	if (!$tab AND !$rec) $index = 0;
	elseif (!isset($ops[$type])) $index = 1;
	else $index = count($ops[$type]) + 1; 
	// + 1 because main record will/might be last one that's handled

	// set information on successful record operation
	$ops[$type][$index] = [
		'table' => $zz_tab[$tab]['table'],
		'table_name' => $zz_tab[$tab]['table_name'] ?? $zz_tab[$tab]['table'],
		'id_field_name' => $zz_tab[$tab][$rec]['id']['field_name'], 
		'id_value' => $zz_tab[$tab][$rec]['id']['value'],
		'action' => $zz_tab[$tab][$rec]['actual_action'] ?? $zz_tab[$tab][$rec]['action'],
		'tab-rec' => $tab.'-'.$rec,
		'error' => $zz_tab[$tab][$rec]['error'] ?? false,
		'change_info' => $zz_tab[$tab][$rec]['change_info'] ?? false
	];
	if (!empty($zz_tab[$tab][$rec]['images'])) {
		foreach ($zz_tab[$tab][$rec]['images'] as $no => $images) {
			foreach ($images as $image_index => $image) {
				if (!is_numeric($image_index)) continue;
				$ops['uploads'][$index][] = [
					'field_no' => $no,
					'upload_index' => $image_index,
					'tmp_name' => $image['upload']['tmp_name'] ?? $image['files']['tmp_file'] ?? NULL,
					'size' => $image['upload']['size'] ?? NULL,
					'validated' => $image['upload']['validated'] ?? NULL,
					'filetype' => $image['upload']['filetype'] ?? NULL,
					'error' => $image['upload']['error'] ?? NULL,
					'field_name' => $zz_tab[$tab][$rec]['fields'][$no]['field_name']
				];
			}
		}
	}

	if ($type === 'return' AND $index === 0) {
		// shortcut for ID
		$ops['id'] = $ops['return'][0]['id_value'];
	}

	// set new record (no new record if record was deleted)
	if (!empty($zz_tab[$tab][$rec]['POST']) 
		AND $zz_tab[$tab][$rec]['action'] !== 'delete') {
		$rn = $zz_tab[$tab][$rec]['POST'];
		// write ID
		$rn[$zz_tab[$tab][$rec]['id']['field_name']] = $zz_tab[$tab][$rec]['id']['value'];
		// remove subtables
		if ($tab == 0 AND $rec == 0) {
			foreach (array_keys($zz_tab) as $my_tab) {
				if (!is_numeric($my_tab)) continue;
				if ($zz_tab[$my_tab]['table_name']) unset($rn[$zz_tab[$my_tab]['table_name']]);
			}
		}
		$ops['record_new'][$index] = $rn;
	} else {
		$ops['record_new'][$index] = [];
	}
	
	// set old record
	if (!empty($zz_tab[$tab][$rec]['existing'])) {
		$ops['record_old'][$index] = $zz_tab[$tab][$rec]['existing'];
		$ro = $zz_tab[$tab][$rec]['existing'];
	} elseif (!empty($zz_tab[$tab][$rec]['id']['value'])) {
		// get a record that was deleted with JavaScript
		$ops['record_old'][$index] = zz_query_single_record(
			$zz_tab[$tab]['sql'], $zz_tab[$tab]['table'], $zz_tab[$tab]['table_name'], $zz_tab[$tab][$rec]['id'],
			$zz_tab[$tab]['sql_extra'] ?? []
		);
	} else {
		$ops['record_old'][$index] = [];
	}
	
	// diff old record and new record
	$rd = [];
	if (!$rn) {
		$fields = $zz_tab[$tab][$rec]['fields'];
		if (!$fields AND isset($zz_tab[0][0]['fields'][$zz_tab[$tab]['no']]['fields'])) {
			// get a record that was deleted with JavaScript
			$fields = $zz_tab[0][0]['fields'][$zz_tab[$tab]['no']]['fields'];
		}
		foreach ($fields as $field) {
			if (empty($field['field_name'])) continue;
			$rd[$field['field_name']] = 'delete';
		}
	} else {
		foreach ($rn as $field => $value) {
			if (!key_exists($field, $ro)) {
				$rd[$field] = 'insert';
			} elseif (is_array($ro[$field]) AND $ro[$field] !== $value) {
				$rd[$field] = 'diff';
			} elseif (is_array($value) AND $ro[$field] !== $value) {
				$rd[$field] = 'diff';
			} elseif ($ro[$field].'' != $value.'') {
				// string comparison instead of !==
				// to match numerical values against identical strings
				$rd[$field] = 'diff';
			} else {
				$rd[$field] = 'same';
			}
		}
	}
	$ops['record_diff'][$index] = $rd;

	return $ops;
}

/** 
 * Create, move or delete folders which are connected to records
 * 
 * @param array $zz_tab complete zz_tab array
 * @return array $folders => $zz_tab[0]['folder'][] will be set
 */
function zz_foldercheck($zz_tab) {
	$folders = [];
	if (empty($zz_tab[0]['folder'])) return $folders;
	if ($zz_tab[0][0]['action'] !== 'update') return $folders;

	foreach ($zz_tab[0]['folder'] as $folder) {
		$path = zz_makepath($folder, $zz_tab, 'new', 'file');
		$old_path = zz_makepath($folder, $zz_tab, 'old', 'file');
		if ($old_path === $path) continue;
		if (!file_exists($old_path)) continue;
		if (!file_exists($path)) {
			$success = zz_create_topfolders(dirname($path));
			if ($success) {
				$success = rename($old_path, $path);
			}
			if ($success) {
				$folders[] = ['old' => $old_path, 'new' => $path];
			} else {
				zz_error_log([
					'msg_dev' => 'Folder cannot be renamed.'
				]);
				zz_error();
			}
		} else {
			zz_error_log([
				'msg_dev' => 'There is already a folder by that name.'
			]);
			zz_error();
		}
	}
	return $folders;
}

/*
 * --------------------------------------------------------------------
 * V - Validation
 * --------------------------------------------------------------------
 */

/**
 * check if dependent fields are invisible
 * then no value for these must be saved, existing values need to be deleted
 * 
 * $zz['fields'][5]['dependent_fields'][9]['if_selected'] = 'crm_category';
 * $zz['fields'][5]['dependent_fields'][9]['required] = true;
 * @param array $zz_tab
 * @return void
 */
function zz_action_dependent_fields(&$zz_tab) {
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			$my_rec = $zz_tab[$tab][$rec];
			$dependent_fields_ids = zz_dependent_field_ids($my_rec['fields'], $tab, $rec);
			if (!$dependent_fields_ids) continue;

			foreach ($my_rec['fields'] as $f => $field) {
				if (empty($dependent_fields_ids[$f])) continue;
				// 	shorthand
				$field_name = $field['field_name'] ?? '';

				foreach ($dependent_fields_ids[$f] as $dependency) {
					// do not use $my_rec to change, $zz_tab[$tab][$rec] might have changed
					$source_value = zz_dependent_value($dependency, $zz_tab[$tab][$rec], $zz_tab);
					if ($source_value AND !empty($dependency['values']) AND in_array($source_value, $dependency['values'])) {
						// visible, i. e. value is possible
						if ($dependency['required']) {
							$zz_tab[$tab][$rec]['fields'][$f]['required']
								= $zz_tab[$tab][$rec]['fields'][$f]['required_in_db']
								= true;
							if (!empty($field['subtable']))
								$zz_tab[$field['subtable']]['min_records_required'] = 1;
						}
					} elseif (!empty($dependency['set_values']) AND $source_value
						AND array_key_exists($source_value, $dependency['set_values'])) {
						$values = $dependency['set_values'][$source_value];
						if (array_key_exists($field_name, $values)) {
							if (str_ends_with($field_name, '_id') AND !is_numeric($values[$field_name]))
								$values[$field_name] = wrap_id(wrap_sql_plural($field_name), $values[$field_name]);
							$zz_tab[$tab][$rec]['POST'][$field_name] = $values[$field_name];
						} else {
							if (!empty($my_rec['fields'][$f]['required_in_db']) AND !empty($my_rec['fields'][$f]['dependent_empty_value'])) {
								$zz_tab[$tab][$rec]['POST'][$field_name] = $my_rec['fields'][$f]['dependent_empty_value'];
							} else {
								$zz_tab[$tab][$rec]['POST'][$field_name] = false;
							}
							$zz_tab[$tab][$rec]['fields'][$f]['required']
								= $zz_tab[$tab][$rec]['fields'][$f]['required_in_db']
								= false;
						}
					} else {
						// invisible, remove existing value if there is one
						if (!empty($my_rec['fields'][$f]['required_in_db']) AND !empty($my_rec['fields'][$f]['dependent_empty_value'])) {
							$zz_tab[$tab][$rec]['POST'][$field_name] = $my_rec['fields'][$f]['dependent_empty_value'];
						} elseif (!$field_name AND !empty($field['table_name'])) {
							// it is a subtable. delete existing records, do not add new records
							$deleted_ids = zz_action_dependent_subtables($field, $my_rec['POST']);
							if ($deleted_ids) {
								// since it is a conditional field
								// check if ID is in another subtable with same table
								$all_deleted_ids = $deleted_ids;
								foreach ($zz_tab as $index => $my_tab) {
									if (!$index) continue; // main record
									if ($my_tab['table_name'] === $field['table_name']) continue; // this is the subtable
									if ($my_tab['table'] !== $field['table']) continue;
									foreach ($deleted_ids as $index => $deleted_id) {
										if (!in_array($deleted_id, $my_tab['subtable_ids'])) continue;
										unset($deleted_ids[$index]); // we still need that record
									}
								}
								$zz_tab[$field['subtable']]['subtable_deleted'] += $deleted_ids;
								$zz_tab[$field['subtable']]['subtable_ids'] = [];
							}
							foreach ($zz_tab[$field['subtable']] as $sub_rec => $subtable) {
								if (!is_numeric($sub_rec)) continue;
								// remove data
								$zz_tab[$field['subtable']][$sub_rec]['POST'] = [];
								$zz_tab[$field['subtable']][$sub_rec]['action'] = 'ignore'; // for new records with default values
								if ($deleted_ids) {
									if (empty($subtable['id']['value'])
										OR !in_array($subtable['id']['value'], $all_deleted_ids)) {
										continue;
									}
									$zz_tab[$field['subtable']][$sub_rec]['id']['value'] = false;
								}
							}
							// do not require records
							$zz_tab[$field['subtable']]['min_records_required'] = 0;
						} else {
							$zz_tab[$tab][$rec]['POST'][$field_name] = false;
						}
						$zz_tab[$tab][$rec]['fields'][$f]['required']
							= $zz_tab[$tab][$rec]['fields'][$f]['required_in_db']
							= false;
					}
				}
			}
		}
	}
}

/**
 * check if there are dependent subtables that are hidden, get IDs
 *
 * @param array $field
 * @param array $post
 * @return array
 */
function zz_action_dependent_subtables($field, $post) {
	// subtable, if there is an ID, remove it
	$id_field_name = '';
	foreach ($field['fields'] as $sub_field) {
		if (empty($sub_field['type'])) continue;
		if ($sub_field['type'] !== 'id') continue;
		$id_field_name = $sub_field['field_name'];
		break;
	}
	if (!$id_field_name) return [];
	if (empty($post[$field['table_name']])) return [];
	$to_delete = [];
	foreach ($post[$field['table_name']] as $record) {
		if (empty($record[$id_field_name])) continue;
		$to_delete[] = $record[$id_field_name];
	}
	return $to_delete;
}

/**
 * Set action for subrecords and call validation for all records
 *
 * @param array $zz_tab
 * @return array
 */
function zz_action_validate($zz_tab) {
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if ($tab) {
				// only if $tab and $rec != 0, i. e. only for subtables!
				// set action field in zz_tab-array, 
				$zz_tab[$tab] = zz_set_subrecord_action($zz_tab, $tab, $rec);
				if ($zz_tab[$tab][$rec]['action'] === 'ignore') continue;
			}
			if (!in_array($zz_tab[$tab][$rec]['action'], ['insert', 'update'])) continue;

			// don't validate record which only will be shown!!
			if ($zz_tab[$tab][$rec]['access'] === 'show') continue;
			// no revalidation of already validated records
			if (!empty($zz_tab[$tab][$rec]['was_validated'])) continue;
		
			// first part of validation where field values are independent
			// from other field values
			$zz_tab[$tab][$rec] = zz_validate($zz_tab, $tab, $rec);
			if ($tab) {
				// write changed POST values back to main POST array
				// @todo let the next functions access the main POST array 
				// differently
				$zz_tab[0][0]['POST'][$zz_tab[$tab]['table_name']][$rec] = $zz_tab[$tab][$rec]['POST'];
				foreach ($zz_tab[$tab][$rec]['extra'] AS $key => $value)
					$zz_tab[0][0]['extra'][$zz_tab[$tab]['table_name'].'['.$rec.']['.$key.']'] = $value;
				// translated identifier might have been deleted, so re-evaluate action
				$zz_tab[$tab] = zz_set_subrecord_action($zz_tab, $tab, $rec);
			}
		}
	}

	// handle last fields after all values have been validated
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			$zz_tab[$tab][$rec] = zz_validate_last_fields($zz_tab, $tab, $rec);
		}
	}
	return $zz_tab;
}

/**
 * Validates user input
 * 
 * @param array $zz_tab
 * @param int $tab
 * @param int $rec
 * @global array $zz_conf
 * @return array $my_rec with validated values and marker if validation was 
 *		successful ($my_rec['validation'])
 */
function zz_validate($zz_tab, $tab, $rec = 0) {
	global $zz_conf;
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	$my_rec = $zz_tab[$tab][$rec];
	$db_table = $zz_tab[$tab]['db_name'].'.'.$zz_tab[$tab]['table'];
	
	// in case validation fails, these values will be sent back to user
	$my_rec['POST-notvalid'] = $my_rec['POST'];
	$my_rec['last_fields'] = [];
	$my_rec['extra'] = [];

	foreach ($my_rec['fields'] as $f => $field) {
	// 	shorthand
		$field_name = isset($field['field_name']) ? $field['field_name'] : '';

	//	check if some values are to be replaced internally
		if (!empty($field['replace_values']) 
			AND !empty($my_rec['POST'][$field_name])) {
			if (in_array($my_rec['POST'][$field_name], array_keys($field['replace_values'])))
				$my_rec['POST'][$field_name]
					= $field['replace_values'][$my_rec['POST'][$field_name]];
		}
	
	//	check if there are options-fields and put values into table definition
		if (!empty($field['read_options']))
			$my_rec['fields'][$f] = $field = zz_validate_read_options($field, $zz_tab, $tab, $rec);

	//	set detail types for write_once-Fields
		if ($field['type'] === 'write_once' 
			AND empty($my_rec['record'][$field_name])
			AND empty($my_rec['existing'][$field_name])) {
			if (!empty($field['type_detail']))
				$my_rec['fields'][$f]['type'] = $field['type'] = $field['type_detail'];
		}

		//	remove entries which are for display only
		if (!empty($field['display_only'])) {
			$my_rec['fields'][$f]['in_sql_query'] = false;
			$my_rec['fields'][$f]['class'][] = 'hidden';
			continue;
		}

		if (!$tab AND !$rec) {
			// here: only for main record, since subrecords already were taken care for
			$value = zz_write_values($field, $zz_tab, $f);
			if (isset($value)) $my_rec['POST'][$field_name] = $value;
		}

		//	call function
		if (!empty($field['function'])) { // $field['type'] === 'hidden' AND 
			if (!empty($field['fields'])) {
				foreach ($field['fields'] as $var) {
					if (strstr($var, '.')) {
						$vars = explode('.', $var);
						$func_vars[$var] = $my_rec['POST'][$vars[0]][0][$vars[1]] ?? '';
					} else {
						$func_vars[$var] = $my_rec['POST'][$var] ?? '';
					}
				}
			} else {
				$func_vars = [];
			}
			$my_rec['POST'][$field_name] = $field['function']($func_vars, $field_name);
		}
		// check for content that is illegaly submitted as array
		if (array_key_exists($field_name, $my_rec['POST']) AND $my_rec['POST'][$field_name]
			AND is_array($my_rec['POST'][$field_name]) AND empty($field['set'])) {
			if (!in_array($field['type'], ['identifier', 'hidden'])) {
				// some fields cannot be changed manually anyways
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
			$my_rec['POST'][$field_name] = '';
			continue;
		}
		// formatting_spaces? remove all spaces
		if (!empty($field['formatting_spaces'])) {
			$my_rec['POST'][$field_name] = str_replace(' ', '', $my_rec['POST'][$field_name]);
		}
		if (!empty($field['typo_cleanup'])) {
			$my_rec['POST'][$field_name] = wrap_typo_cleanup($my_rec['POST'][$field_name], zz_typo_cleanup_language($my_rec['POST']));
		}
		if (!empty($field['typo_remove_double_spaces'])) {
			while (strstr($my_rec['POST'][$field_name], '  ')) {
				$my_rec['POST'][$field_name] = str_replace('  ', ' ', $my_rec['POST'][$field_name]);
			}
		}
		if (!empty($field['replace_substrings'])) {
			if (!is_array($field['replace_substrings']))
				$field['replace_substrings'] = [$field['replace_substrings']];
			foreach ($field['replace_substrings'] as $search => $replace) {
				$my_rec['POST'][$field_name] = str_replace($search, $replace, $my_rec['POST'][$field_name]);
			}
		}

		// per default, all fields are becoming part of SQL query
		$my_rec['fields'][$f]['in_sql_query'] = true;

		// get field type, hidden fields with sub_type will be validated against subtype
		$type = $field['type'];
		
	// 	walk through all fields by type
		switch ($type) {
		case 'id':
			if ($my_rec['action'] === 'update') {
				$my_rec['id']['field_name'] = $field_name;
				// for display of updated record:
				$my_rec['id']['value'] = $my_rec['POST'][$my_rec['id']['field_name']];
			} else
				$my_rec['POST'][$field_name] = "''";
			break;
		case 'foreign_id':
			continue 2;
		case 'ipv4':
			//	convert ipv4 address to long
			if ($my_rec['POST'][$field_name])
				$my_rec['POST'][$field_name] = ip2long($my_rec['POST'][$field_name]);
			break;
		case 'hidden':
			// IP fields are transformed because they are binary
			if (empty($field['type_detail'])) break;
			if ($field['type_detail'] !== 'ip') break;
		case 'ip':
			if (!$my_rec['POST'][$field_name]) break;
			if (!empty($my_rec['existing'][$field_name])) {
				// if it's the same value as in database, ok. it's already in binary form
				if ($my_rec['existing'][$field_name] === $my_rec['POST'][$field_name]) break;
			}
			if (!filter_var($my_rec['POST'][$field_name], FILTER_VALIDATE_IP)) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
			break;			
		case 'number':
			//	calculation and choosing of right values in case of coordinates
			if (isset($field['number_type']) 
				AND in_array($field['number_type'], ['latitude', 'longitude'])) {
				// geographical coordinates
				
				$precision = zz_db_decimal_places($db_table, $field);
				$coord = zz_geo_coord_in(
					$my_rec['POST'][$field_name], $field['number_type'], $precision
				);
				if ($coord['error']) {
					zz_error_validation_log('msg_dev', $coord['error']);
					zz_error_validation_log('msg_dev_args', $field_name);
					zz_error_validation_log('log_post_data', true);
					$my_rec['fields'][$f]['explanation'] = $coord['error'];
					$my_rec['fields'][$f]['check_validation'] = false;
					$my_rec['validation'] = false;
				} else {
					$my_rec['POST'][$field_name] = $coord['value'];
				}
				// coordinates may have 0 as value
				$field['null'] = true;
			} else {
				// only check if there is a value, NULL values are checked later on
				if (!$my_rec['POST'][$field_name]) break;

				// check if numbers are entered with .
				$n_val = zz_check_number($my_rec['POST'][$field_name]);
				if ($n_val !== NULL) {
					$my_rec['POST'][$field_name] = $n_val;
					if (!empty($field['max_int_value']) AND $n_val > $field['max_int_value']) {
						$my_rec['validation'] = false;
						$my_rec['fields'][$f]['check_validation'] = false;
						$my_rec['fields'][$f]['validation_error']['msg'] = 'The number %d is too high. Maximum value is %d.';
						$my_rec['fields'][$f]['validation_error']['msg_args'] = [$n_val, $field['max_int_value']];
					}
				} else {
					$my_rec['fields'][$f]['check_validation'] = false;
					$my_rec['validation'] = false;
				}
			}
			break;
		case 'sequence':
			if (!$my_rec['POST'][$field_name]) break;
			$value = intval($my_rec['POST'][$field_name]);
			if ($value.'' !== $my_rec['POST'][$field_name].'') {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			} elseif (!empty($field['max_int_value']) AND $value > $field['max_int_value']) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['fields'][$f]['validation_error']['msg'] = 'The number %d is too high. Maximum value is %d.';
				$my_rec['fields'][$f]['validation_error']['msg_args'] = [$value, $field['max_int_value']];
			}
			break;
		case 'password':
			if (!$my_rec['POST'][$field_name]) break;
			// password already encrypted (multiple zz_validate() calls?)
			if (!empty($my_rec['POST']['zz_unencrypted_'.$field_name])) break;

			//	encrypt passwords, only for changed passwords! therefore string 
			// 		is compared against old pwd
			// action=update: here, we have to check whether submitted password 
			// 		is equal to password in db
			// if so, password won't be touched
			// if not, password will be encrypted
			// action=insert: password will be encrypted
			if ($my_rec['action'] === 'insert') {
				$my_rec['POST']['zz_unencrypted_'.$field_name] = $my_rec['POST'][$field_name];
				$my_rec['POST'][$field_name] = wrap_password_hash($my_rec['POST'][$field_name]);
			} elseif ($my_rec['action'] === 'update') {
				$my_rec['POST']['zz_unencrypted_'.$field_name] = $my_rec['POST'][$field_name];
				$new_password = false;
				if (!isset($my_rec['POST'][$field_name.'--old'])) {
					if (!isset($my_rec['existing'][$field_name])) {
						$new_password = true;
					} elseif ($my_rec['existing'][$field_name] !== $my_rec['POST'][$field_name]) {
						$new_password = true;
					}
				} elseif ($my_rec['POST'][$field_name] !== $my_rec['POST'][$field_name.'--old']) {
					$new_password = true;
				}
				if ($new_password) {
					$my_rec['POST'][$field_name] = wrap_password_hash($my_rec['POST'][$field_name]);
				}
			}
			break;
		case 'password_change':
			//	change encrypted password
			$pwd = false;
			if (($my_rec['POST'][$field_name] OR !empty($field['dont_require_old_password']))
				AND $my_rec['POST'][$field_name.'_new_1']
				AND $my_rec['POST'][$field_name.'_new_2']) {
				$my_sql = $field['sql_password_check'].$my_rec['id']['value'];
				$pwd = zz_password_set($my_rec['POST'][$field_name], 
					$my_rec['POST'][$field_name.'_new_1'], 
					$my_rec['POST'][$field_name.'_new_2'], $my_sql, $field);
			} else {
				zz_error_log([
					'msg' => 'Please enter your current password and twice your new password.',
					'level' => E_USER_NOTICE
				]);
			}
			if ($pwd) {
				$my_rec['POST'][$field_name] = $pwd;
				$my_rec['password_change_successful'] = true;
				// @todo message
			} else { 
				$my_rec['POST'][$field_name] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
			break;
		case 'select':
			//	check select /// workwork
			if (!empty($my_rec['existing'][$field_name])
				AND $my_rec['existing'][$field_name] == $my_rec['POST'][$field_name]) {
				// record did not change, so we do not need to check the select value
				break;
			}
			if (!empty($field['select_save_value'])) {
				if (is_numeric($my_rec['POST'][$field_name])) {
					$sql = wrap_edit_sql($field['sql'], 'WHERE',
						sprintf('%s = %d', $field['key_field'], $my_rec['POST'][$field_name])
					);
					$line = wrap_db_fetch($sql);
					array_shift($line); // remove ID
					$my_rec['POST'][$field_name] = implode(' | ', $line);
				}
				if (!$my_rec['POST'][$field_name]) break;
				// might have all kinds of values
				$my_rec['fields'][$f]['check_validation'] = true;
				break;
			}
			$my_rec = zz_check_select($my_rec, $f);
			//	check for correct enum values
			if (!$my_rec['POST'][$field_name]) break;
			if (isset($field['enum'])) {
				if (!empty($field['enum_textinput']) AND $my_rec['POST'][$field_name] === end($field['enum'])) {
					if (!empty($my_rec['POST'][$field_name.'--text'])) {
						$my_rec['POST'][$field_name] = $my_rec['POST'][$field_name.'--text'];
					} else {
						$my_rec['POST'][$field_name] = '';
					}
				} elseif (!$tempvar = zz_check_enumset($my_rec['POST'][$field_name], $field, $db_table)) {
					$my_rec['validation'] = false;
					$my_rec['fields'][$f]['check_validation'] = false;
				} else {
					$my_rec['POST'][$field_name] = $tempvar;
				}
			} elseif (isset($field['set'])) {
				if (is_array($my_rec['POST'][$field_name])
					AND count($my_rec['POST'][$field_name]) === 1
					AND (!$my_rec['POST'][$field_name][0]))
					$my_rec['POST'][$field_name] = false;
			}
			break;
		case 'date':
		case 'time':
		case 'datetime':
			if (!$my_rec['POST'][$field_name]) break;
			// zz_check_date, zz_check_time, zz_check_datetime
			$check_function = sprintf('zz_check_%s', $type);
			if ($my_val = $check_function($my_rec['POST'][$field_name], $field))
				$my_rec['POST'][$field_name] = $my_val;
			else {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
			break;
		case 'unix_timestamp':
			//	convert unix_timestamp, if something was posted
			if (!$my_rec['POST'][$field_name]) break;
			$my_date = strtotime($my_rec['POST'][$field_name]); 
			if ($my_date AND $my_date !== -1) 
				// strtotime converts several formats, returns -1 if value
				// is not convertable
				$my_rec['POST'][$field_name] = $my_date;
			elseif (preg_match('/^[0-9]+$/', $my_rec['POST'][$field_name])) 
				// is already timestamp, does not work with all integer 
				// values since some of them are converted with strtotime
				$my_rec['POST'][$field_name] = $my_rec['POST'][$field_name];
			else {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
			break;
		case 'identifier':
			// will be dealt with at the end, when all other values are clear
			$my_rec['last_fields'][] = $f;
			continue 2;
		case 'url':
			//	check for correct url
			if ($my_rec['POST'][$field_name]) {
				if (!$tempvar = zz_check_url($my_rec['POST'][$field_name])) {
					$my_rec['fields'][$f]['check_validation'] = false;
					$my_rec['validation'] = false;
				} else {
					$tempvar = zz_remove_local_hostname($tempvar, $field);
					$my_rec['POST'][$field_name] = $tempvar;
				}
			}
			break;
		case 'url+placeholder':
			if (!$my_rec['POST'][$field_name]) break;
			$tempvar = zz_check_url_placeholder($my_rec['POST'][$field_name]);
			if (!$tempvar) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			} else {
				$my_rec['POST'][$field_name] = $tempvar;
			}
			break;
		case 'username':
			if ($my_rec['POST'][$field_name]) {
				if (!$tempvar = zz_check_username($my_rec['POST'][$field_name], $field)) {
					$my_rec['fields'][$f]['check_validation'] = false;
					$my_rec['validation'] = false;
				} else $my_rec['POST'][$field_name] = $tempvar;
			}
			break;
		case 'mail':
		case 'mail+name':
			//	check for correct mailaddress
			if ($my_rec['POST'][$field_name]) {
				if (!$tempvar = zz_check_mail($my_rec['POST'][$field_name], $type)) {
					$my_rec['fields'][$f]['check_validation'] = false;
					$my_rec['validation'] = false;
				} else $my_rec['POST'][$field_name] = $tempvar;
			}
			break;
		case 'upload_image':
			$my_rec['fields'][$f]['in_sql_query'] = false;
			if (zz_upload_check($my_rec['images'][$f], $my_rec['action'], $rec)) break;
			
			// check failed
			$my_rec['validation'] = false;
			$my_rec['fields'][$f]['check_validation'] = false;
			if (!is_array($my_rec['images'][$f])) break;

			// get detailed error message
			foreach ($my_rec['images'][$f] as $key => $image) {
				if (!is_numeric($key)) continue;
				if (empty($image['error'])) continue;
				foreach ($image['error'] as $error) {
					zz_error_validation_log('msg_dev', $error);
					zz_error_validation_log('msg_dev_args', $field_name);
				}
			}
			break;
		case 'display':
		case 'write_once':
		case 'option':
		case 'calculated':
		case 'image':
		case 'foreign':
		case 'subtable':
		case 'foreign_table':
		case 'list_function':
			//	remove entries which are for display only
			// 	or will be processed somewhere else
			$my_rec['fields'][$f]['in_sql_query'] = false;
			break;
		case 'text':
		case 'memo':
			// normalize linebreaks
			$my_rec['POST'][$field_name] = str_replace("\r\n", "\n", $my_rec['POST'][$field_name]);
			$my_rec['POST'][$field_name] = str_replace("\r", "\n", $my_rec['POST'][$field_name]);
			// trim text
			if (empty($field['dont_trim']) AND $type === 'text') {
				$my_rec['POST'][$field_name] = trim($my_rec['POST'][$field_name]);
			}
			if (!empty($field['trim']) AND $type === 'memo') {
				$my_rec['POST'][$field_name] = trim($my_rec['POST'][$field_name]);
			}
			break;
		case 'captcha':
			$my_rec['fields'][$f]['in_sql_query'] = false;
			if (!$my_rec['POST'][$field_name]) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			} elseif (!zz_captcha_code($zz_conf['id'], $my_rec['POST'][$field_name])) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			} else {
				$my_rec['fields'][$f]['captcha_solved'] = true;
			}
			break;
		case 'parameter':
			$my_rec['POST'][$field_name] = zz_validate_parameter($my_rec['POST'][$field_name]);
			break;
		default:
			break;
		}

		// types 'identifier' and 'display_only' are out
		// check if $field['post_validation'] is set
		if (!empty($field['post_validation'])) {
			$values = $field['post_validation']($my_rec['POST'][$field_name]);
			if ($values === -1) {
				// in this case, the function explicitly says:
				// record is invalid, so delete values
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			} elseif ($values) {
				// ok, get these values and save them for later use 
				foreach ($values as $key => $value) {
					$my_rec['extra'][$field_name.'_'.$key] = $value; 
				}
			}
		}

		//	remove entries which are for input only
		if (!empty($field['input_only'])) {
			$my_rec['fields'][$f]['in_sql_query'] = false;
			if ($field['required']
				AND empty($my_rec['POST'][$field_name])) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
		}
		if (!$my_rec['fields'][$f]['in_sql_query'])	continue;

	//	validation

	//	check whether is false but must not be NULL
		if (!isset($my_rec['POST'][$field_name])) {
			// no set = must be error
			if ($field['type'] === 'foreign_key' 
				OR $field['type'] === 'translation_key'
				OR $field['type'] === 'detail_key') {
				// foreign key will always be empty but most likely also be 
				// required. f. key will be added by script later on (because 
				// sometimes it is not known yet)
				// do nothing, leave $my_rec['validation'] as it is
			} elseif ($field['type'] === 'timestamp') {
				// timestamps will be set to current date, so no check is 
				// necessary. do nothing, leave $my_rec['validation'] as it is.
			} elseif (!isset($field['set']) AND !isset($field['set_sql'])
				 AND !isset($field['set_folder'])) {
				$my_rec['validation'] = false;
			} elseif ($field['required_in_db']) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
			}
		} elseif (!$my_rec['POST'][$field_name] 
			AND (empty($field['null']) OR $my_rec['POST'][$field_name] === '')
			AND $field['type'] !== 'timestamp') {
			if ($field['required_in_db']) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
			}
		}

	// check length
	// (check here against array because field might be used as 'set' or 'enum',
	// then a check is not necessary because the person who created the field
	// approved the values beforehands)
		if (!empty($field['maxlength'])
			AND !empty($my_rec['POST'][$field_name])
			AND !is_array($my_rec['POST'][$field_name])) {
			if (($length = mb_strlen($my_rec['POST'][$field_name])) > $field['maxlength']) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['fields'][$f]['validation_error'] = [
					'msg' => 'Text is too long (max. %d characters, %d submitted).',
					'msg_args' => [$field['maxlength'], $length]
				];
				$my_rec['validation'] = false;
			}
		}
		if (!empty($field['minlength'])
			AND !empty($my_rec['POST'][$field_name])
			AND !is_array($my_rec['POST'][$field_name])) {
			if (($length = mb_strlen($my_rec['POST'][$field_name])) < $field['minlength']) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['fields'][$f]['validation_error'] = [
					'msg' => 'Text is too short (min. %d characters, %d submitted).',
					'msg_args' => [$field['minlength'], $length]
				];
				$my_rec['validation'] = false;
			}
		}

	//	check against forbidden strings
		if (!empty($field['validate'])
			AND !empty($my_rec['POST'][$field_name])) {
			if ($msg = zz_check_rules($my_rec['POST'][$field_name], $field, $my_rec['POST'])) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['fields'][$f]['validation_error'] = $msg;
			}
		}

	//	check against pattern
		if (!empty($field['pattern'])
			AND !empty($my_rec['POST'][$field_name])) {
			if (is_array($my_rec['POST'][$field_name])) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['fields'][$f]['validation_error'] = [
					'msg' => 'Array <em>“%s”</em> does not match pattern <em>“%s”</em>',
					'msg_args' => [json_encode($my_rec['POST'][$field_name]), $field['pattern']]
				];
			} elseif (!zz_validate_pattern($my_rec['POST'][$field_name], $field['pattern'])) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['fields'][$f]['validation_error'] = [
					'msg' => 'Value <em>“%s”</em> does not match pattern <em>“%s”</em>',
					'msg_args' => [zz_htmltag_escape($my_rec['POST'][$field_name]), $field['pattern']]
				];
			}
		}

		// check not_identical_with
		if (!empty($field['not_identical_with'])) {
			$identical_value = false;
			$second_f = false;
			foreach ($my_rec['fields'] as $f_no => $my_field) {
				if (empty($my_field['field_name'])) continue;
				if ($my_field['field_name'] !== $field['not_identical_with']) continue;
				$second_f = $f_no;
			}

			if (!empty($my_rec['POST'][$field['not_identical_with']])) {
				$identical_value = $my_rec['POST'][$field['not_identical_with']];
			} elseif ($my_rec['fields'][$second_f]['type'] === 'foreign_key') {
				if ($zz_tab[0][0]['id']['field_name'] === $my_rec['fields'][$second_f]['field_name'])
					$identical_value = $zz_tab[0][0]['id']['value'];
				elseif (!empty($my_rec['fields'][$second_f]['foreign_key_field_name'])
					AND $zz_tab[0][0]['id']['field_name'] === $my_rec['fields'][$second_f]['foreign_key_field_name'])
					$identical_value = $zz_tab[0][0]['id']['value'];
			}
			if ($identical_value === $my_rec['POST'][$field_name]) {
				$second_field_name = wrap_text('unkown');
				if ($second_f)
					$second_field_name = $my_rec['fields'][$second_f]['title'];
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['fields'][$f]['validation_error'] = [
					'msg' => 'Value in field <em>“%s”</em> must not be identical to field <em>“%s”</em>.',
					'msg_args' => [$my_rec['fields'][$f]['title'], $second_field_name]
				];
				$my_rec['POST'][$field['not_identical_with']] = false;
				$my_rec['POST'][$field_name] = false;
				if (!empty($my_rec['fields'][$f]['sql_before']))
					$my_rec['fields'][$f]['sql'] = $my_rec['fields'][$f]['sql_before'];
			}
		}
	}

	// finished
	$my_rec['was_validated'] = true;
	return zz_return($my_rec);
}

/**
 * handle some fields after all other fields have been validated
 * because they might get data from these other fields (e. g. upload_fields)
 *
 * @param array $zz_tab
 * @param int $tab
 * @param int $rec
 * @return array
 */
function zz_validate_last_fields($zz_tab, $tab, $rec) {
	$my_rec = $zz_tab[$tab][$rec];
	if (!array_key_exists('last_fields', $my_rec)) return $my_rec;
	$db_table = $zz_tab[$tab]['db_name'].'.'.$zz_tab[$tab]['table'];
	
	foreach ($my_rec['last_fields'] as $f)
		//	call function: generate ID
		if ($my_rec['fields'][$f]['type'] === 'identifier') {
			require_once __DIR__.'/identifier.inc.php';
			$my_rec['POST'][$my_rec['fields'][$f]['field_name']] 
				= zz_identifier($my_rec, $db_table, $zz_tab[0][0]['POST'], $f);
			if (!empty($my_rec['fields'][$f]['log_username']) AND !is_array($my_rec['POST'][$my_rec['fields'][$f]['field_name']]))
				wrap_setting('log_username_default', $my_rec['POST'][$my_rec['fields'][$f]['field_name']]);
		}
	return $my_rec;
}

/**
 * read options
 *
 * @param array $field
 * @param array $zz_tab
 * @param int $tab
 * @param int $rec
 * @return array
 */
function zz_validate_read_options($field, $zz_tab, $tab, $rec) {
	if (str_starts_with($field['read_options'], '0[')) {
		$option_no = substr($field['read_options'], 2, -1);
		// check if field exists, was not unset via condition
		if (!array_key_exists($option_no, $zz_tab[0][0]['fields'])) return $field;
		$option_field = $zz_tab[0][0]['fields'][$option_no];
		$submitted_option = $zz_tab[0][0]['POST'][$option_field['field_name']];
	} else {
		$option_no = $field['read_options'];
		$option_field = $zz_tab[$tab][$rec]['fields'][$option_no];
		$submitted_option = $zz_tab[$tab][$rec]['POST'][$option_field['field_name']];
	}
	// if there's something submitted which fits in our scheme, replace 
	// values corresponding to options-field
	if (empty($option_field['options'][$submitted_option])) return $field;
	return array_merge($field, $option_field['options'][$submitted_option]);
}

/**
 * validate parameters field
 *
 * @param string $value
 * @return string
 */
function zz_validate_parameter($fvalue) {
	// replace multi line notation
	$fvalue = str_replace(["\r\n\r\n", "\r\n"], "&", $fvalue);
	// escape + sign
	$fvalue = str_replace("+", "%2B", $fvalue);
	// keep %20 encoding
	$percent20 = 'somerarelyappearingsequenceofcharacters20';
	$fvalue = str_replace('%20', $percent20, $fvalue);

	// replace : with =
	$pattern = '/&(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/';
	$fvalue = preg_split($pattern, $fvalue);
	foreach ($fvalue as $index => $pair) {
		$fvalue[$index] = $pair = str_replace('&', '%26', $pair);
		if (strstr($pair, '=')) continue;
		if (!strstr($pair, ':')) continue;
		$pair = explode(':', $pair);
		$key = array_shift($pair);
		$value = trim(implode(':', $pair), '_');
		$fvalue[$index] = sprintf('%s=%s', trim($key), trim($value));
	}
	$fvalue = implode('&', $fvalue);

	// check if there's whitespace at the end of one of the keys/values
	parse_str($fvalue, $parameters);
	zz_recursive_ksort($parameters);
	$fvalue = zz_validate_http_build_query($parameters);
	if ($fvalue) $fvalue = '&'.$fvalue; // add leading ampersand for simpler queries
	
	// escape + sign again	
	$fvalue = str_replace('+', '%2B', $fvalue);
	$fvalue = str_replace($percent20, '%20', $fvalue);
	return $fvalue;
}

/**
 * build query string, with some modifications
 * no key and value escaping, replacing of & values
 *
 * @param array $array
 * @param string $prefix (optional)
 * @return string
 */
function zz_validate_http_build_query($array, $prefix = '') {
	$qs = [];
    foreach ($array as $key => $value) {
        if (!$prefix) {
			// main key always has to be lowercase, other keys might contain uppercase letters
			$new_key = strtolower($key);
        	$new_key = trim($new_key, '_');
        } else {
	        $new_key = sprintf('%s[%s]', $prefix, trim($key));
        }

        if (is_array($value)) {
            // recursively build the query string for nested arrays
            $qs[] = zz_validate_http_build_query($value, $new_key);
        } else {
			$value = str_replace('&', '%26', $value);
            $qs[] = sprintf('%s=%s', $new_key, $value);
        }
    }
    return implode('&', $qs);
}

/**
 * sort array by key, recursively
 *
 * @param array $array
 */
function zz_recursive_ksort(&$array) {
    if (is_array($array)) {
        ksort($array);
        foreach ($array as &$value) {
            zz_recursive_ksort($value);
        }
    }
}

/**
 * copies value from other field (field name is value of 'detail_value')
 *
 * @param array $zz_tab
 * @param int $f
 * @param int $tab (optional, for detail record)
 * @param int $rec (optional, for detail record)
 * @return string $value
 */
function zz_write_detail_values($zz_tab, $f, $tab = 0, $rec = 0) {
	$my_field = $zz_tab[$tab][$rec]['fields'][$f]['detail_value'];
	$value = false;
	if (isset($zz_tab[$tab][$rec]['POST'][$my_field])) 
		// first test same subtable
		$value = $zz_tab[$tab][$rec]['POST'][$my_field];
	elseif (isset($zz_tab[0][0]['POST'][$my_field])) 
		// main table, currently no other means to access it
		$value = $zz_tab[0][0]['POST'][$my_field];
	elseif (isset($zz_tab[0][0]['extra'][$my_field])) {
		$value = $zz_tab[0][0]['extra'][$my_field];
	}
	if (!$value) {
		$field_name = $zz_tab[$tab][$rec]['fields'][$f]['field_name'];
		$value = $zz_tab[$tab][$rec]['POST'][$field_name];
	}
	if (wrap_setting('debug'))
		zz_debug(__FUNCTION__.'(): field '.$my_field.', value: '.$value);
	return $value;
}

/**
 * validates input against a set of rules
 *
 * @param string $value value entered in form
 * @param array $field
 *		'validate' defines against what to validate 
 *		'validate_msg' (optional) set validation error message
 * @param array $post
 * @return mixed false: everything is okay, array: error message
 */
function zz_check_rules($value, $field, $post) {
	foreach ($field['validate'] as $type => $data) {
		switch ($type) {
		case 'forbidden_strings':
			foreach ($data as $needle) {
				if (stripos($value, $needle) === false) continue; // might be 0
				return [
					'msg' => $field['validate_msg'][$type] ?? 'String <em>“%s”</em> is not allowed',
					'msg_args' => zz_htmltag_escape($needle)
				];
			}
			break;
		case '>':
		case '>=':
		case '<':
		case '<=':
			if (!is_array($data)) $data = [$data];
			foreach ($data as $field) {
				if (empty($post[$field])) continue;
				if ($type === '>' AND $value > $post[$field]) continue;
				if ($type === '>=' AND $value >= $post[$field]) continue;
				if ($type === '<' AND $value < $post[$field]) continue;
				if ($type === '<=' AND $value <= $post[$field]) continue;
				$msg['>'] = 'greater than';
				$msg['>='] = 'greater than or equal to';
				$msg['<'] = 'smaller than';
				$msg['<='] = 'smaller than or equal to';
				return [
					'msg' => $field['validate_msg'][$type] ?? 'Value “%s” needs to be '.$msg[$type].' “%s”.',
					'msg_args' => [zz_htmltag_escape($value), zz_htmltag_escape($post[$field])]
				];
			}
			break;
		}
	}
	return false;
}

/**
 * Password change, checks old and new passwords and returns encrypted new
 * password if everything was successful
 *
 * @param string $old	Old password
 * @param string $new1	New password, first time entered
 * @param string $new2	New password, second time entered, to check if match
 * @param string $sql	SQL query to check whether passwords match
 * @param array $field
 * @return string false: an error occurred; string: new encrypted password 
 */
function zz_password_set($old, $new1, $new2, $sql, $field) {
	wrap_setting('error_log_post', false); // never log posted passwords
	if ($new1 !== $new2) {
		// new passwords do not match
		zz_error_log([
			'msg' => 'New passwords do not match. Please try again.',
			'level' => E_USER_NOTICE
		]);
		return false;
	}
	if ($old === $new1) {
		// old password eq new password - this is against identity theft if 
		// someone interferes a password mail
		zz_error_log([
			'msg' => 'New and old password are identical. Please choose a different new password.',
			'level' => E_USER_NOTICE
		]);
		return false; 
	}
	if (empty($field['dont_require_old_password'])) {
		$old_hash = zz_db_fetch($sql, '', 'single value', __FUNCTION__);
		if (!$old_hash) return false;
		if (!wrap_password_check($old, $old_hash)) {
			zz_error_log([
				'msg' => 'Your current password is different from what you entered. Please try again.',
				'msg_dev' => '(Encryption: %s, existing hash: %s, entered hash: %s)',
				'msg_dev_args' => [wrap_setting('hash_password'), $old_hash, wrap_password_hash($old)],
				'level' => E_USER_NOTICE
			]);
			return false;
		}
	}
	// new1 = new2, old = old, everything is ok
	$hash = wrap_password_hash($new1);
	if ($hash) return $hash;

	zz_error_log([
		'msg' => 'Your new password could not be saved. Please try a different one.',
		'level' => E_USER_WARNING
	]);
	return false;
}

/**
 * check against unique restraints for main record only
 *
 * @param array $zz_tab
 */
function zz_action_unique_check(&$zz_tab) {
	$sql = 'SHOW INDEXES FROM `%s`.`%s` WHERE Key_name != "PRIMARY" AND Non_unique = 0';
	$sql = sprintf($sql, $zz_tab[0]['db_name'] ?? $_SESSION['db_name_local'], $zz_tab[0]['table']);
	// Key_name, Column_name, Null
	$data = wrap_db_fetch($sql, '_dummy_', 'numeric');
	$uniques = [];
	foreach ($data as $line)
		$uniques[$line['Key_name']][$line['Seq_in_index']] = [
			'field_name' => $line['Column_name'],
			'null' => $line['Null'] === 'YES' ? 1 : NULL
		];
	foreach ($uniques as $fields) {
		$new_values = [];
		foreach ($fields as $field) {
			// check if value was validated
			foreach ($zz_tab[0][0]['fields'] as $no => $fielddef) {
				if (empty($fielddef['field_name'])) continue;
				if ($field['field_name'] !== $fielddef['field_name']) continue;
				if (!isset($zz_tab[0][0]['fields'][$no]['check_validation'])) continue;
				if ($zz_tab[0][0]['fields'][$no]['check_validation']) continue;
				continue 3;
			}
			$value = $zz_tab[0][0]['POST'][$field['field_name']] ?? false;
			if (!$value) {
				$value = 'NULL';
				if ($field['null'])
					if ($zz_tab[0]['unique_ignore_null']) continue; // check without field
					else continue 2; // no check, since multiple NULL values are always allowed
			} elseif (!is_int($value)) {
				$value = sprintf('"%s"', wrap_db_escape($value));
			}
			$new_values[] = sprintf('`%s` = %s', $field['field_name'], $value);
		}
		$sql = 'SELECT `%s` FROM `%s`.`%s` WHERE %s AND `%s` != %d';
		$sql = sprintf($sql
			, $zz_tab[0][0]['id']['field_name'], $zz_tab[0]['db_name'], $zz_tab[0]['table']
			, implode(' AND ', $new_values)
			, $zz_tab[0][0]['id']['field_name'], $zz_tab[0][0]['id']['value']
		);
		$existing_id = wrap_db_fetch($sql, '', 'single value');
		if ($existing_id) {
			$zz_tab[0][0]['validation'] = false;
			foreach ($zz_tab[0][0]['fields'] as $no => $fielddef) {
				foreach ($fields as $field) {
					if (empty($fielddef['field_name'])) continue;
					if ($field['field_name'] !== $fielddef['field_name']) continue;
					if (!empty($zz_tab[0][0]['fields'][$no]['hide_in_form'])) continue;
					if (empty($zz_tab[0][0]['fields'][$no]['required_in_db'])) continue;
					$zz_tab[0][0]['fields'][$no]['check_validation'] = false;
					$zz_tab[0][0]['fields'][$no]['validation_error'] = [
						'msg' => 'Duplicate entry',
					];
				}
			}
		}
	}
}

/**
 * --------------------------------------------------------------------
 * I - Referential integrity
 * --------------------------------------------------------------------
 *
 * functions for checking for relational integrity of mysql-databases
 * uses table _relations, similar to the PHPMyAdmin table of relations
 * name of table may be set with `zzform_relations__table`
 * 
 * detail records if shown with current record will be deleted if they don't 
 * have detail records themselves. In the latter case, the resulting error message
 * is not 100% correct, but these occasions should be very rare anyways
 * 
 */

/**
 * gets all entries from the table where the database relations are stored
 *
 * @return array $relations
 */
function zz_integrity_relations() {
	static $relations = [];
	if ($relations) return $relations;

	$sql = 'SELECT * FROM /*_TABLE zzform_relations _*/';
	$relations = zz_db_fetch($sql, ['master_db', 'master_table', 'master_field', 'rel_id']);
	return $relations;
}

/**
 * Checks relational integrity upon a deletion request and says if its ok.
 *
 * @param array $deletable_ids
 * @param array $relations
 * @return mixed bool false: deletion of record possible, integrity will remain
 *		array: 'msg' (error message), 'msg_args' (optional, names of tables
 *		which have a relation to the current record)
 */
function zz_integrity_check($deletable_ids, $relations) {
	if (!$relations) {
		$response['msg'] = 'No records in relation table `%s`. Please fill in records.';
		$response['msg_args'] = [wrap_sql_table('zzform_relations')];
		$response['msg_no_list'] = true;
		return $response;
	}

	$response = [];
	$response['msg_args'] = [];
	$response['updates'] = [];
	foreach ($deletable_ids as $master_db => $tables) {
		foreach ($tables as $master_table => $fields) {
			if (!isset($relations[$master_db][$master_table])) {
			//	no relations which have this table as master
			//	do not care about master_field because it has to be PRIMARY key
			//	anyways, so only one field name possible
				continue;
			}
			$master_field = key($fields);
			$ids = array_shift($fields);
			foreach ($relations[$master_db][$master_table][$master_field] as $key => $field) {
				$sql = 'SELECT `%s` FROM `%s`.`%s` WHERE `%s` IN (%s)';
				$sql = sprintf($sql,
					$field['detail_id_field'], $field['detail_db'],
					$field['detail_table'], $field['detail_field'], implode(',', $ids)
				);
				$detail_ids = zz_db_fetch($sql, $field['detail_id_field'], 'single value');
				if (!$detail_ids) continue;
				
				if (!empty($deletable_ids[$field['detail_db']][$field['detail_table']][$field['detail_id_field']])) {
					$deletable_detail_ids = $deletable_ids[$field['detail_db']][$field['detail_table']][$field['detail_id_field']];
					$remaining_ids = array_diff($detail_ids, $deletable_detail_ids);
				} else {
					$remaining_ids = $detail_ids;
				}
				if ($remaining_ids) {
					if ($field['delete'] === 'update') {
						$response['updates'][] = [
							'ids' => $remaining_ids,
							'field' => $field
						];
					} elseif (!array_key_exists($field['detail_table'], $response['msg_args'])) {
						// there are still IDs which cannot be deleted
						// check which record they belong to
						// only get unique values
						$response['msg_args'][$field['detail_table']]
							= zz_nice_tablenames($field['detail_table']).sprintf(' (%s)', wrap_number(count($detail_ids)));
					}
				}
			}
		}
	}
	if ($response['msg_args'] OR $response['updates']) {
		if ($response['msg_args']) {
			// we still have detail records
			$response['msg_args'] = array_values($response['msg_args']);
			$response['msg'] = '';
		}
		return $response;
	} else {
		// everything is okay
		return false;
	}
}

/**
 * checks tables if there are records in other tables which will be deleted
 * with their main records in this table together (relation = delete)
 *
 * @param array $zz_tab (all table definitions and records)
 * @param array $relations		All entries of relations table
 * @return array $details
 *		[db][table][id_field] = array with IDs that can be deleted safely
 * @see zz_get_detail_record_ids()
 */
function zz_integrity_dependent_record_ids($zz_tab, $relations) {
	if (!$relations) return [];

	$details = [];
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if (!$zz_tab[$tab][$rec]['id']['value']) continue;
			if ($zz_tab[$tab][$rec]['action'] !== 'delete') continue;
			$details = zz_integrity_deletable(
				$zz_tab[$tab]['db_name'], $zz_tab[$tab]['table'], 
				$zz_tab[$tab][$rec]['id']['field_name'], $zz_tab[$tab][$rec]['id']['value'],
				$relations, $details
			);
		}
	}
	if (!empty($zz_tab[0]['integrity_delete']))
		$details = array_merge_recursive($details, $zz_tab[0]['integrity_delete']);
	return $details;
}

/**
 * recursive function to get deletable records, n levels deep
 *
 * @param string $db_name
 * @param string $table
 * @param string $id_field_name
 * @param mixed $id_value (integer or list of integers)
 * @param array $relations
 * @param array $details
 * @param int $level
 * @return array ($details)
 */
function zz_integrity_deletable($db_name, $table, $id_field_name, $id_value, $relations, $details, $level = 0) {
	// check level to avoid indefinite recursion
	if ($level > 3) return $details;
	if (empty($relations[$db_name])) return $details;
	if (empty($relations[$db_name][$table])) return $details;
	if (empty($relations[$db_name][$table][$id_field_name])) return $details;

	foreach ($relations[$db_name][$table][$id_field_name] as $rel) {
		// we care just about 'delete'-relations
		if ($rel['delete'] !== 'delete') continue;
		if (is_array($id_value)) {
			$sql = 'SELECT `%s` FROM `%s`.`%s` WHERE `%s` IN (%s)';
			$sql = sprintf($sql,
				$rel['detail_id_field'], $rel['detail_db'], 
				$rel['detail_table'], $rel['detail_field'],
				implode(',', $id_value)
			);
		} else {
			$sql = 'SELECT `%s` FROM `%s`.`%s` WHERE `%s` = %d';
			$sql = sprintf($sql,
				$rel['detail_id_field'], $rel['detail_db'], 
				$rel['detail_table'], $rel['detail_field'],
				$id_value
			);
		}
		$records = zz_db_fetch($sql, $rel['detail_id_field'], 'single value');
		if (!$records) continue;

		if (empty($details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']]))
			$details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']] = [];
		$details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']] 
			= array_merge($records, $details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']]);

		// check if detail records have other detail records
		$details = zz_integrity_deletable(
			$rel['detail_db'], $rel['detail_table'], $rel['detail_id_field'],
			$records, $relations, $details, $level + 1
		);
	}
	return $details;
}

/**
 * reads all existing ID values from main record and subrecords which
 * are going to be deleted 
 * 
 * (if main record will be deleted, subrecords will all
 * be deleted; it is possible that only some subrecords will be deleted while
 * main record gets updated)
 *
 * @param array $zz_tab (all table definitions and records)
 * @return array $details
 *		[db][table][id_field] = array with IDs that can be deleted safely
 * @see zz_get_depending_records()
 */
function zz_integrity_record_ids($zz_tab) {
	$records = [];
	foreach ($zz_tab as $tab) {
		foreach ($tab as $tab_no => $rec) {
			if (!is_numeric($tab_no)) continue;
			if (!$rec['id']['value']) continue;
			if ($rec['action'] !== 'delete') continue;
			if (is_array($rec['id']['value'])) {
				if (!isset($records[$tab['db_name']][$tab['table']][$rec['id']['field_name']]))
					$records[$tab['db_name']][$tab['table']][$rec['id']['field_name']] = [];
				$records[$tab['db_name']][$tab['table']][$rec['id']['field_name']]
					= array_merge($records[$tab['db_name']][$tab['table']][$rec['id']['field_name']], $rec['id']['value']);
			} else {
				$records[$tab['db_name']][$tab['table']][$rec['id']['field_name']][]
					= $rec['id']['value'];
			}
		}
	}
	return $records;
}

/**
 * check detail records which can be deleted if there are any files
 * attached to them that need to be deleted as well
 *
 * @param array $dependent_ids
 * @return bool true: files were added, false: nothing was changed
 */
function zz_integrity_check_files($dependent_ids) {
	if (empty($dependent_ids)) return false;
	$return = false;
	
	foreach ($dependent_ids as $db_name => $tables) {
		foreach ($tables as $table => $ids) {
			$table = str_replace('_', '-', $table);
			$zz = zzform_include($table, [], 'integrity-check');
			if (!$zz) continue;

			// check if this script fits the table and database name
			$zz['table'] = wrap_db_prefix($zz['table']);
			if (strstr($zz['table'], '.')) {
				list($script_db, $script_table) = explode('.', $zz['table']);
			} else {
				$script_db = wrap_setting('db_name');
				$script_table = $zz['table'];
			}
			if ($table !== $script_table) continue;
			if ($db_name !== $script_db) continue;

			foreach ($zz['fields'] as $no => $field) {
				if (empty($field['type'])) continue;
				if ($field['type'] !== 'upload_image') continue;
				if (empty($field['image'])) continue;
				
				$id_field_name = key($ids);
				$ids = $ids[$id_field_name];
				$sql = wrap_edit_sql($zz['sql'], 'WHERE', $id_field_name.' IN ('.implode(',', $ids).')');
				$data = zz_db_fetch($sql, $id_field_name);
				
				foreach ($field['image'] as $image) {
					foreach ($data as $line) {
						$path = zz_makepath($image['path'], $line, 'line', 'file');
						if (!$path) continue;
						zz_upload_cleanup_files($path);
						$return = true;
					}
				}
			}
		}
	}
	return $return;
}

/**
 * return URL without hostname
 * standard behaviour, turn off with
 * $zz['fields'][n]['remove_local_hostname'] = false
 *
 * @param string $tempvar URL
 * @param array $field field definition
 * @return string
 */
function zz_remove_local_hostname($tempvar, $field) {
	if (isset($field['remove_local_hostname']) AND $field['remove_local_hostname'] === false) {
		return $tempvar;
	}
	$removals = [
		'http://'.$_SERVER['HTTP_HOST'], 'https://'.$_SERVER['HTTP_HOST']
	];
	foreach ($removals as $removal) {
		if (substr($tempvar, 0, strlen($removal)) !== $removal) continue;
		$tempvar = substr($tempvar, strlen($removal));
		return $tempvar;
	}
	return $tempvar;
}

/**
 * normalize a sequence
 * called as an internal hook function
 *
 * @param array $ops
 * @param array $zz_tab
 * @return array
 * @todo if sequence numbers are missing, update numbers as well (optional)
 */
function zz_sequence_normalize($ops, $zz_tab) {
	global $zz_conf;
	static $used_maxint_values = [];

	// which fields are the sequence fields?
	$fields = [];
	foreach ($zz_tab as $tab => $records) {
		if (!is_numeric($tab)) continue;
		foreach ($records as $rec => $record) {
			if (!is_numeric($rec)) continue;
			foreach ($record['fields'] as $no => $field) {
				if (empty($field['type'])) continue;
				if ($field['type'] !== 'sequence') continue;
				$fields[$tab.'-'.$rec]['field_name'] = $field['field_name'];
				if (!empty($field['sequence_sql']))
					$fields[$tab.'-'.$rec]['sql'] = $field['sequence_sql'];
				if (!empty($field['max_int_value']))
					$fields[$tab.'-'.$rec]['max_int_value'] = $field['max_int_value'];
			}
		}
	}

	$return = !empty($ops['planned']) ? 'planned' : 'return'; // return for deletion
	foreach ($ops[$return] as $index => $table) {
		if (!in_array($table['tab-rec'], array_keys($fields))) continue;
		$my_field = $fields[$table['tab-rec']];
		if ($ops['record_diff'][$index][$my_field['field_name']] === 'same') continue;
		$new_value = $ops['record_new'][$index][$my_field['field_name']] ?? false;
		$old_value = $ops['record_old'][$index][$my_field['field_name']] ?? false;
		list($tab, $rec) = explode('-', $table['tab-rec']);
		$sql = $zz_tab[$tab]['sql'];
		if (!empty($my_field['sql']['join'])) {
			$sql = wrap_edit_sql($sql, 'JOIN', $my_field['sql']['join']);
		}
		if (!empty($my_field['sql']['values'])) {
			if (!$new_value) continue; // @todo does not work for DELETE (after_delete)
			$values = [];
			foreach ($my_field['sql']['values'] as $field) {
				$values[] = zz_check_values($zz_tab[0][0]['POST'], $field);
			}
			$w_sql = vsprintf($my_field['sql']['values_sql'], $values);
			$record = wrap_db_fetch($w_sql);
			$my_field['sql']['where'] = vsprintf($my_field['sql']['where'], $record);
		}
		if (!empty($my_field['sql']['where'])) {
			$sql = wrap_edit_sql($sql, 'WHERE', $my_field['sql']['where']);
		}
		// @todo support filter
		//		if (!empty($_GET['filter'])) {
		//			require_once __DIR__.'/list.inc.php';
		//			$sql = zz_list_filter_sql($zz_tab[$tab]['filter'], $sql, $_GET['filter']);
		//		}
		if ($tab) {
			$sql =  wrap_edit_sql($sql, 'WHERE', sprintf('%s = %d', $zz_tab[$tab]['foreign_key_field_name'], $zz_conf['int']['id']['value']));
		}
		$data = wrap_db_fetch($sql, $zz_tab[$tab][$rec]['id']['field_name']);
		// does new sequence value already exist?
		// then update existing and following values +/- 1
		if ($new_value) {
			$key = array_search($new_value, array_column($data, $my_field['field_name']));
			if ($key === false) continue;
		}

		// get IDs for updates
		$updates = [];
		foreach ($data as $id => $line) {
			if ($line[$my_field['field_name']].'' === $old_value.'') continue; // current record
			if (!$new_value) {
				if ($line[$my_field['field_name']] < $old_value) continue;
			} elseif ($new_value < $old_value OR !$old_value) {
				if ($line[$my_field['field_name']] < $new_value) continue;
				if ($old_value AND $line[$my_field['field_name']] > $old_value) continue;
			} else {
				if ($line[$my_field['field_name']] > $new_value) continue;
				if ($old_value AND $line[$my_field['field_name']] < $old_value) continue;
			}
			$updates[] = $id;
		}
		if (!$updates) continue;

		// update current record temporarily to max value of column; this is to avoid
		// problems with unique keys
		if ($old_value AND $new_value) {
			$full_field = sprintf('%s.%s.%s', $zz_tab[$tab]['db_name'], $zz_tab[$tab]['table'], $my_field['field_name']);
			if (array_key_exists($full_field, $used_maxint_values)) {
				$field_def['max_int_value'] = --$used_maxint_values[$full_field];
			} else {
				if (!empty($my_field['max_int_value'])) {
					$field_def['max_int_value'] = $my_field['max_int_value'];
				} else {
					$field_def = zz_db_columns(
						$zz_tab[$tab]['db_name'].'.'.$zz_tab[$tab]['table'], $my_field['field_name']
					);
				}
				if (empty($field_def['max_int_value'])) {
					zz_error_log([
						'msg_dev' => 'Field has no maximum integer value (is it an integer?): %s.%s.%s',
						'msg_dev_args' => [$zz_tab[$tab]['db_name'], $zz_tab[$tab]['table'], $my_field['field_name']]
					]);
					continue;
				}
				$used_maxint_values[$full_field] = $field_def['max_int_value'];
			}
			$sql = 'UPDATE %s SET %s = %d WHERE %s = %d';
			$sql = sprintf($sql
				, $zz_tab[$tab]['table']
				, $my_field['field_name']
				, $field_def['max_int_value']
				, $zz_tab[$tab][$rec]['id']['field_name']
				, $ops['record_new'][$index][$zz_tab[$tab][$rec]['id']['field_name']]
			);
			wrap_db_query($sql);
		}

		// update other records between old and new value, either increase or decrease
		$sql = 'UPDATE %s SET %s = %s %s 1 WHERE %s IN (%s) ORDER BY %s %s';
		$sql = sprintf($sql
			, $zz_tab[$tab]['table']
			, $my_field['field_name']
			, $my_field['field_name']
			, ($new_value AND ($new_value < $old_value OR !$old_value)) ? '+': '-'
			, $zz_tab[$tab][$rec]['id']['field_name']
			, implode(',', $updates)
			, $my_field['field_name']
			, ($new_value AND ($new_value < $old_value OR !$old_value)) ? 'DESC': 'ASC'
		);
		$result = zz_db_change($sql);
	}
}

/**
 * get language for translation
 * look for field 'language_id' or first field ending with '_language_id'
 *
 * @param array $post POST data of translated or main record
 * @return array
 */
function zz_typo_cleanup_language($post) {
	$language_id = false;
	$language_code = '';
	foreach ($post as $field_name => $value) {
		if ($field_name === 'language_id') {
			$language_id = $value;
			break;
		}
		if (!str_ends_with($field_name, '_language_id')) continue;
		$language_id = $value;
		break;
	}
	if (!$language_id) return '';
	$languages = wrap_id('languages', '', 'list');
	$language_code = array_search($language_id, $languages);
	return $language_code;
}
