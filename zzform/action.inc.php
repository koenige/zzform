<?php 

/**
 * zzform
 * Action: update, delete, insert or review a record,
 * validation of user input, maintaining referential integrity
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2019 Gustaf Mossakowski
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
 * @param array $zz_var
 * @global array $zz_conf
 * @return array ($ops, $zz_tab, $validation)
 * @see zz_upload_get(), zz_upload_prepare(), zz_set_subrecord_action(),
 *		zz_validate(), zz_integrity_check(), zz_upload_cleanup(), 
 *		zz_prepare_for_db(), zz_log_sql(), zz_foldercheck(), zz_upload_action()
 */
function zz_action($ops, $zz_tab, $validation, $zz_var) {
	global $zz_conf;

	if (file_exists($path = $zz_conf['dir_custom'].'/editing.inc.php')) {
		include_once $path;
	}

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$zz_tab[0]['record_action'] = false;

	// hook, e. g. get images from different locations than upload
	list($ops, $zz_tab) = zz_action_hook($ops, $zz_tab, 'before_upload', 'not_validated');
	
	//	### Check for validity, do some operations ###
	if (!empty($zz_var['upload_form'])) {
		// read upload image information, as required
		$zz_tab = zz_upload_get($zz_tab);
		if ($zz_var['action'] !== 'delete') {
			$ops['file_upload'] = $zz_tab[0]['file_upload'];
			// read upload image information, as required
			$zz_tab = zz_upload_prepare($zz_tab);
		} else {
			$ops['file_upload'] = false;
		}
	}
	if (zz_error_exit())
		return zz_return([$ops, $zz_tab, $validation]);

	$zz_tab = zz_action_validate($zz_tab);

	// hook, if an action directly after validation is required
	// e. g. geocoding
	if ($zz_var['action'] !== 'delete') {
		list($ops, $zz_tab) = zz_action_hook($ops, $zz_tab, 'after_validation', 'validated');
	}

	// check referential integrity
	if ($zz_conf['check_referential_integrity']) {
		// get table relations
		$relations = zz_integrity_relations($zz_conf['relations_table']);
		// get record IDs of all records in table definition (1 main, n sub records)
		$record_ids = zz_integrity_record_ids($zz_tab);
		// if no record IDs = no deletion is possible
		if ($record_ids) {
			// get record IDs of dependent records which have 'delete' set
			// in table relations
			$dependent_ids = zz_integrity_dependent_record_ids($zz_tab, $relations);
			// work with array_merge_recursive even if there are duplicate IDs
			// zz_array_merge() would overwrite IDs
			$zz_tab[0]['integrity'] = zz_integrity_check(
				array_merge_recursive($record_ids, $dependent_ids), $relations
			);
			// return database errors
			if (zz_error_exit())
				return zz_return([$ops, $zz_tab, $validation]);
			// if something was returned, validation failed because there 
			// probably are records
			if ($zz_tab[0]['integrity']['msg_args']) {
				$validation = false;
			} elseif ($zz_var['upload_form']) {
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
			AND $zz_var['action'] !== 'delete') {
			// mark it!
			$zz_tab[0][0]['fields'][$my_tab['no']]['check_validation'] = false;
			// show error message
			if (empty($zz_tab[0][0]['fields'][$my_tab['no']]['dont_show_missing'])) {
				zz_error_validation_log('msg', 'Minimum of records for table `%s` was not met (%d).');
				zz_error_validation_log('msg_args', zz_text($zz_tab[0][0]['fields'][$my_tab['no']]['title']));
				zz_error_validation_log('msg_args', $my_tab['min_records_required']);
				zz_error_validation_log('log_post_data', true);
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
	
	if (!$validation) {
		if (!empty($zz_var['upload_form'])) zz_upload_cleanup($zz_tab, false); 
		return zz_return([$ops, $zz_tab, $validation]);
	}

	if ($zz_conf['modules']['debug']) zz_debug("validation successful");

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
	list($ops, $zz_tab) = zz_action_hook($ops, $zz_tab, 'before_'.$zz_var['action'], 'planned');
	if (!empty($ops['no_validation'])) $validation = false;

	if (zz_error_exit()) { // repeat, might be set in before_action
		zz_error_exit(false);
		$validation = false;
	}
	if (!$validation) {
		// delete temporary unused files
		if (!empty($zz_var['upload_form'])) zz_upload_cleanup($zz_tab); 
		return zz_return([$ops, $zz_tab, $validation]);
	}

	$sql_edit = '';
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if ($zz_tab[$tab][$rec]['action'] !== 'insert' 
				AND $zz_tab[$tab][$rec]['action'] !== 'update') continue;
			if ($zz_tab[$tab][$rec]['access'] === 'show') continue;
			// do something with the POST array before proceeding
			$zz_tab[$tab][$rec] = zz_prepare_for_db(
				$zz_tab[$tab][$rec],
				'`'.$zz_tab[$tab]['db_name'].'`'.'.'.$zz_tab[$tab]['table'],
				$zz_tab[0][0]['POST']
			); 
		}
	}

	foreach (array_keys($zz_tab) as $tab)
		foreach (array_keys($zz_tab[$tab]) as $rec) {
		if (!is_numeric($rec)) continue;
		if ($zz_tab[$tab][$rec]['action'] === 'ignore') continue;
		
		// get database name for query
		$me_db = false;
		if ($zz_conf['int']['db_main']) {
			// the 'main' zzform() database is different from the database for 
			// the main record, so check against db_main
			if ($zz_tab[$tab]['db_name'] !== $zz_conf['int']['db_main']) 
				$me_db = $zz_tab[$tab]['db_name'].'.';
		} else {
			// the 'main' zzform() database is equal to the database for the 
			// main record, so check against db_name
			if ($zz_tab[$tab]['db_name'] !== $zz_conf['db_name']) 
				$me_db = $zz_tab[$tab]['db_name'].'.';
		}
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
		
		if (!$sql_edit) $sql_edit = $me_sql;
		else 			$detail_sqls[$tab][$rec] = $me_sql;
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
			if ($zz_conf['int']['db_main']) {
				// the 'main' zzform() database is different from the database for 
				// the main record, so check against db_main
				if ($db_name !== $zz_conf['int']['db_main']) 
					$me_db = '`'.$db_name.'`.';
			} else {
				// the 'main' zzform() database is equal to the database for the 
				// main record, so check against db_name
				if ($db_name !== $zz_conf['db_name']) 
					$me_db = '`'.$db_name.'`.';
			}
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

	if ($zz_conf['modules']['debug'] AND $zz_conf['debug']) {
		$ops['output'].= '<br>';
		$ops['output'].= 'Main ID value: '.$zz_tab[0][0]['id']['value'].'<br>';
		$ops['output'].= 'Main SQL query: '.$sql_edit.'<br>';
		if ($del_msg) {
			$ops['output'].= 'SQL deletion queries:<br>'.(implode('', $del_msg));
			unset($del_msg);
		}
	}

	$result = zz_db_change($sql_edit, $zz_tab[0][0]['id']['value']);
	if ($result['action']) {
		if ($zz_tab[0][0]['action'] === 'insert') {
			$zz_tab[0][0]['id']['value'] = $result['id_value']; // for requery
		}
		// save record values for use outside of zzform()
		if ($result['action'] === 'nothing') {
			$zz_tab[0][0]['actual_action'] = 'nothing';
		}
		$ops = zz_record_info($ops, $zz_tab);
		$zz_tab[0]['record_action'] = true;
		if (isset($detail_sqls)) {
			list($zz_tab, $validation, $ops) 
				= zz_action_details($detail_sqls, $zz_tab, $validation, $ops);
		}
		zz_action_last_update($zz_tab, $result['action']);

		// if any other action after insertion/update/delete is required
		$change = zz_action_function('after_'.$zz_var['action'], $ops, $zz_tab);
		list($ops, $zz_tab) = zz_action_change($ops, $zz_tab, $change);

		$zz_tab[0]['folder'] = zz_foldercheck($zz_tab);

		if (!empty($zz_var['upload_form'])) {
			// upload images, delete images, as required
			$zz_tab = zz_upload_action($zz_tab);
			$ops['output'] .= zz_error();
			if (zz_error_exit()) {
				zz_upload_cleanup($zz_tab);
				return zz_return([$ops, $zz_tab, $validation]);
			}
			$change = zz_action_function('after_upload', $ops, $zz_tab);
			list($ops, $zz_tab) = zz_action_change($ops, $zz_tab, $change);
		}
		if ($zz_tab[0]['record_action']) {
			$ops['result'] = 'successful_'.$zz_tab[0][0]['action'];
		}
	} else {
		// Output Error Message
		if ($zz_var['action'] === 'insert') {
			// for requery
			$zz_tab[0][0]['id']['value'] = false;
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
	
	// delete temporary unused files
	if (!empty($zz_var['upload_form'])) zz_upload_cleanup($zz_tab);
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
		if ($field['type'] === 'subtable') continue;
		if ($field['type'] === 'id') continue;
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
			} elseif ($field['type'] === 'number') {
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
 * @global array $zz_conf
 * @return array
 *		$zz_tab, $validation, $ops
 */
function zz_action_details($detail_sqls, $zz_tab, $validation, $ops) {
	global $zz_conf;
	
	foreach (array_keys($detail_sqls) as $tab) {
		foreach (array_keys($detail_sqls[$tab]) as $rec) {
			$my_rec = $zz_tab[$tab][$rec];
			$sql = $detail_sqls[$tab][$rec];
			$sql = str_replace('[FOREIGN_KEY]', sprintf('%d', $zz_tab[0][0]['id']['value']), $sql);
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
				$result['error']['msg'] = 'Detail record could not be handled';
				$result['error']['level'] = E_USER_WARNING;
				zz_error_log($result['error']);
				$zz_tab[$tab][$rec]['error'] = $result['error'];
				$zz_tab[0]['record_action'] = false;
				$validation = false; 
				$zz_tab[0][0]['fields'][$zz_tab[$tab]['no']]['check_validation'] = false;
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
			if ($zz_conf['modules']['debug'] AND $zz_conf['debug']) {
				$ops['output'] .= 'SQL query for record '.$tab.'/'.$rec.': '.$sql.'<br>';
			}
		}
	}
	return [$zz_tab, $validation, $ops];
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
	if (empty($zz_tab[0]['hooks'][$position])) return [$ops, $zz_tab];

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
	global $zz_conf;
	if (empty($zz_tab[0]['hooks'][$type])) return false;

	if (file_exists($zz_conf['hooks_dir'].'/hooks.inc.php')) {
		require_once $zz_conf['hooks_dir'].'/hooks.inc.php';
	}

	$change = [];
	foreach ($zz_tab[0]['hooks'][$type] as $hook) {
		if (substr($hook, 0, 3) === 'zz_') {
			// internal hooks get access to $zz_tab as well
			$custom_result = $hook($ops, $zz_tab);
		} else {
			$file = str_replace('_', '-', $hook);
			if (substr($file, 0, 3) === 'my-') $file = substr($file, 3);
			$file = $zz_conf['hooks_dir'].'/'.$file.'.inc.php';
			if (file_exists($file)) require_once $file;
			$custom_result = $hook($ops);
		}
		if (!is_array($custom_result)) continue;
		$change = zz_array_merge($change, $custom_result);
	}
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
 * @param array $change string 'output', array 'record_replace'
 * @return array [$ops, $zz_tab]
 */
function zz_action_change($ops, $zz_tab, $change) {
	if (!$change) return [$ops, $zz_tab];
	if ($change === true) return [$ops, $zz_tab];
	
	// output?
	if (!empty($change['output'])) {
		$ops['output'] .= $change['output'];
	}

	// invalid?
	if (!empty($change['no_validation'])) {
		$ops['no_validation'] = true;
	}
	
	if (!empty($change['integrity_delete'])) {
		if (empty($zz_tab[0]['integrity_delete']))
			$zz_tab[0]['integrity_delete'] = [];
		$zz_tab[0]['integrity_delete'] = array_merge_recursive($zz_tab[0]['integrity_delete'], $change['integrity_delete']);
	}
	
	// record? replace values as needed
	if (!empty($change['record_replace'])) {
		// get record definition from planned or not_validated
		if (!empty($ops['planned'])) $planned = $ops['planned'];
		elseif (!empty($ops['validated'])) $planned = $ops['validated'];
		else $planned = $ops['not_validated'];
		// replace values
		foreach ($change['record_replace'] as $index => $values) {
			list($tab, $rec) = explode('-', $planned[$index]['tab-rec']);
			$zz_tab[$tab][$rec]['POST'] = array_merge($zz_tab[$tab][$rec]['POST'], $values);
			if (!empty($change['change_info'][$index])) {
				$zz_tab[$tab][$rec]['change_info'] = $change['change_info'][$index];
			}
			$zz_tab[$tab][$rec]['was_validated'] = false;
		}
		// revalidate, but not if no validation has taken place before
		if (!array_key_exists('not_validated', $ops)) {
			$zz_tab = zz_action_validate($zz_tab);
		}
	}
	return [$ops, $zz_tab];
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
 * @global array $zz_conf
 * @return array $my_tab ($zz_tab[$tab])
 *		changed: $zz_tab[$tab][$rec]['action'], $zz_tab[$tab]['subtable_deleted']
 *		may unset($zz_tab[$tab][$rec])
 */
function zz_set_subrecord_action($zz_tab, $tab, $rec) {
	// initialize variables
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$values = '';
	$my_tab = $zz_tab[$tab];

	// check whether there are values in fields
	// this is done to see what to do with subrecord (insert, update, delete)
	foreach ($my_tab[$rec]['fields'] as $field) {
		// depending on ID, set action
		if ($field['type'] !== 'id') continue;
		if (($my_tab['tick_to_save'] AND $my_tab[$rec]['save_record'])
			OR empty($my_tab['tick_to_save'])) {
			if (!isset($my_tab[$rec]['POST'][$field['field_name']])
				OR $my_tab[$rec]['POST'][$field['field_name']] === "''")
				$my_tab[$rec]['action'] = 'insert';
			else
				$my_tab[$rec]['action'] = 'update';
		} elseif ($my_tab['tick_to_save'] AND !$my_tab[$rec]['save_record']) {
			if (!isset($my_tab[$rec]['POST'][$field['field_name']])
				OR $my_tab[$rec]['POST'][$field['field_name']] === "''")
				$my_tab[$rec]['action'] = 'ignore'; // ignore subrecord
			else
				$my_tab[$rec]['action'] = 'delete';
		}
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
	if (!empty($my_tab['records_depend_on_upload']) 
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

	if ($zz_conf['modules']['debug']) zz_debug("end, values: ".substr($values, 0, 20));
	return $my_tab;
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
	global $zz_conf;

	$return_val = NULL;
	//	copy value if field detail_value isset
	if (!empty($field['detail_value'])) {
		$value = zz_write_detail_values($zz_tab, $f, $tab, $rec);
		if ($value) $return_val = $value;
	}
	// check if some values should be gotten from upload fields
	if (!empty($field['upload_field']) AND !empty($zz_conf['modules']['upload'])) {
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
 * @param array $main_post		POST values of $zz_tab[0][0]['POST']
 * @global array $zz_conf
 * @return array $my_rec with validated values and marker if validation was successful 
 * 		($my_rec['validation'])
 */
function zz_prepare_for_db($my_rec, $db_table, $main_post) {
	global $zz_conf;

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	// add keyword _binary for these fields
	$binary_fields = ['ip'];

	if (!empty($my_rec['last_fields'])) { 
	// these fields have to be handled after others because they might get data 
	// from other fields (e. g. upload_fields)
		foreach ($my_rec['last_fields'] as $f)
			//	call function: generate ID
			if ($my_rec['fields'][$f]['type'] === 'identifier') {
				require_once $zz_conf['dir_inc'].'/identifier.inc.php';
				$func_vars = zz_identifier_vars($my_rec, $f, $main_post);
				$conf = (!empty($my_rec['fields'][$f]['conf_identifier']) 
					? $my_rec['fields'][$f]['conf_identifier'] : false);
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] 
					= zz_identifier($func_vars, $conf, $my_rec, $db_table, $f);
			}
	}
	unset($my_rec['last_fields']);
	
	$my_rec['POST_db'] = $my_rec['POST'];
	foreach ($my_rec['fields'] as $f => $field) {
		if (!empty($field['display_only'])) continue;

		$field_name = (!empty($field['field_name']) ? $field['field_name'] : '');
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
			$my_rec['POST_db'][$field_name] = '[FOREIGN_KEY]';
			break;
		case 'detail_key':
			$my_rec['POST_db'][$field_name] = '[DETAIL_KEY]';
			break;
		case 'translation_key':
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
		case 'display':
		case 'option':
		case 'write_once':
		case 'list_function':
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
				if (empty($zz_conf['mysql5.5_support']) AND zz_get_fieldtype($field) === 'ip') {
					$my_rec['POST_db'][$field_name] = sprintf(
						'INET6_ATON("%s")', 
						wrap_db_escape($my_rec['POST_db'][$field_name])
					);
				} elseif (!zz_db_numeric_field($db_table, $field_name)) {
					$encoding = '';
					if (in_array(zz_get_fieldtype($field), $binary_fields)) {
						$encoding = '_binary';
					}
					$my_rec['POST_db'][$field_name] = sprintf(
						'%s"%s"', $encoding, wrap_db_escape($my_rec['POST_db'][$field_name])
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
		'id_field_name' => $zz_tab[$tab][$rec]['id']['field_name'], 
		'id_value' => $zz_tab[$tab][$rec]['id']['value'],
		'action' => !empty($zz_tab[$tab][$rec]['actual_action']) 
			? $zz_tab[$tab][$rec]['actual_action'] : $zz_tab[$tab][$rec]['action'],
		'tab-rec' => $tab.'-'.$rec,
		'error' => !empty($zz_tab[$tab][$rec]['error'])
			? $zz_tab[$tab][$rec]['error'] : false,
		'change_info' => !empty($zz_tab[$tab][$rec]['change_info'])
			? $zz_tab[$tab][$rec]['change_info'] : false
	];
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
			$zz_tab[$tab]['sql'], $zz_tab[$tab]['table'], $zz_tab[$tab][$rec]['id'],
			isset($zz_tab[$tab]['sqlextra']) ? $zz_tab[$tab]['sqlextra'] : []
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
			if ($zz_tab[$tab][$rec]['action'] === 'insert' 
				OR $zz_tab[$tab][$rec]['action'] === 'update') {
				// don't validate record which only will be shown!!
				if ($zz_tab[$tab][$rec]['access'] === 'show') continue;
				// no revalidation of already validated records
				if (!empty($zz_tab[$tab][$rec]['was_validated'])) continue;
			
				// first part of validation where field values are independent
				// from other field values
				$zz_tab[$tab][$rec] = zz_validate($zz_tab[$tab][$rec], $zz_tab[$tab]['db_name']
					.'.'.$zz_tab[$tab]['table'], $zz_tab[$tab]['table_name'], $tab, $rec, $zz_tab); 
				if ($tab) {
					// write changed POST values back to main POST array
					// @todo let the next functions access the main POST array 
					// differently
					$zz_tab[0][0]['POST'][$zz_tab[$tab]['table_name']][$rec] = $zz_tab[$tab][$rec]['POST'];
					foreach ($zz_tab[$tab][$rec]['extra'] AS $key => $value)
						$zz_tab[0][0]['extra'][$zz_tab[$tab]['table_name'].'['.$rec.']['.$key.']'] = $value;
				}
			}
		}
	}
	return $zz_tab;
}

/**
 * Validates user input
 * 
 * @param array $my_rec = $zz_tab[$tab][$rec]
 * @param string $db_table [db_name.table]
 * @param string $table_name Alias for table if it occurs in the form more than once
 * @param int $tab
 * @param int $rec
 * @param array $zz_tab = $zz_tab[0][0], keys ['POST'], ['images'] and ['extra']
 * @global array $zz_conf
 * @return array $my_rec with validated values and marker if validation was 
 *		successful ($my_rec['validation'])
 */
function zz_validate($my_rec, $db_table, $table_name, $tab, $rec = 0, $zz_tab) {
	global $zz_conf;

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	// in case validation fails, these values will be send back to user
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
		if (!empty($field['read_options'])) {
			$submitted_option = $my_rec['POST'][$my_rec['fields'][$field['read_options']]['field_name']];
			// if there's something submitted which fits in our scheme, replace 
			// values corresponding to options-field
			if (!empty($my_rec['fields'][$field['read_options']]['options'][$submitted_option])) {
				$my_rec['fields'][$f] = $field = array_merge(
					$field, $my_rec['fields'][$field['read_options']]['options'][$submitted_option]
				);
			}
		}
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
						if (!empty($my_rec['POST'][$vars[0]][0][$vars[1]])) {
							$func_vars[$var] = $my_rec['POST'][$vars[0]][0][$vars[1]];
						} else {
							$func_vars[$var] = '';
						}
					} else {
						$func_vars[$var] = $my_rec['POST'][$var];
					}
				}
			} else {
				$func_vars = [];
			}
			$my_rec['POST'][$field_name] = $field['function']($func_vars, $field_name);
		}
		// formatting_spaces? remove all spaces
		if (!empty($field['formatting_spaces'])) {
			$my_rec['POST'][$field_name] = str_replace(' ', '', $my_rec['POST'][$field_name]);
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
			// don't convert it twice (hooks!)
			if (@inet_ntop($my_rec['POST'][$field_name])) break;
			$value = @inet_pton($my_rec['POST'][$field_name]);
			if (!$value) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
			// @deprecated: old MySQL 5.5 support
			if (!empty($zz_conf['mysql5.5_support'])) {
				$my_rec['POST'][$field_name] = $value;
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
				} else {
					$my_rec['fields'][$f]['check_validation'] = false;
					$my_rec['validation'] = false;
				}
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
			if ($pwd) $my_rec['POST'][$field_name] = $pwd;
			else { 
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
			$long_field_name = zz_long_fieldname($table_name, $rec, $field_name);
			$my_rec = zz_check_select($my_rec, $f, $zz_conf['max_select'], $long_field_name);
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
			if ($my_val = $check_function($my_rec['POST'][$field_name]))
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
			if (!$my_rec['POST'][$field_name] OR !zz_captcha_code($zz_conf['id'], $my_rec['POST'][$field_name])) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
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
			if ($length = mb_strlen($my_rec['POST'][$field_name]) > $field['maxlength']) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['fields'][$f]['validation_error'] = [
					'msg' => 'Text is too long (max. %d characters, %d submitted).',
					'msg_args' => [[$field['maxlength'], $length]]
				];
				$my_rec['validation'] = false;
			}
		}

	//	check against forbidden strings
		if (!empty($field['validate'])
			AND !empty($my_rec['POST'][$field_name])) {
			if ($msg = zz_check_rules($my_rec['POST'][$field_name], $field['validate'])) {
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
	}

	// finished
	$my_rec['was_validated'] = true;
	return zz_return($my_rec);
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
	global $zz_conf;
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
	if ($zz_conf['modules']['debug'])
		zz_debug(__FUNCTION__.'(): field '.$my_field.', value: '.$value);
	return $value;
}

/**
 * validates input against a set of rules
 *
 * @param string $value value entered in form
 * @param array $validate defines against what to validate 
 * @return mixed false: everything is okay, array: error message
 */
function zz_check_rules($value, $validate) {
	foreach ($validate as $type => $needles) {
		switch ($type) {
		case 'forbidden_strings':
			foreach ($needles as $needle) {
				if (stripos($value, $needle) === false) continue; // might be 0
				return [
					'msg' => 'String <em>“%s”</em> is not allowed',
					'msg_args' => zz_htmltag_escape($needle)
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
 * @global array $zz_conf	Configuration variables, here: 'hash_password'
 * @return string false: an error occurred; string: new encrypted password 
 */
function zz_password_set($old, $new1, $new2, $sql, $field) {
	global $zz_conf;
	$zz_conf['error_log_post'] = false; // never log posted passwords
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
				'msg_dev_args' => [$zz_conf['hash_password'], $old_hash, wrap_password_hash($old)],
				'level' => E_USER_NOTICE
			]);
			return false;
		}
	}
	// new1 = new2, old = old, everything is ok
	$hash = wrap_password_hash($new1);
	if ($hash) {
		zz_error_log([
			'msg' => 'Your password has been changed!',
			'level' => E_USER_NOTICE
		]);
	} else {
		zz_error_log([
			'msg' => 'Your new password could not be saved. Please try a different one.',
			'level' => E_USER_WARNING
		]);
	}
	return $hash;
}

/**
 * --------------------------------------------------------------------
 * I - Referential integrity
 * --------------------------------------------------------------------
 *
 * functions for checking for relational integrity of mysql-databases
 * uses table _relations, similar to the PHPMyAdmin table of relations
 * name of table may be set with $zz_conf['relations_table']
 * 
 * detail records if shown with current record will be deleted if they don't 
 * have detail records themselves. In the latter case, the resulting error message
 * is not 100% correct, but these occasions should be very rare anyways
 * 
 */

/**
 * gets all entries from the table where the database relations are stored
 *
 * @param string $relation_table	Name of relation table ($zz_conf['relation_table'])
 * @return array $relations
 */
function zz_integrity_relations($relation_table) {
	static $relations;
	if ($relations) return $relations;

	$sql = 'SELECT * FROM '.$relation_table;
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
		global $zz_conf;
		$response['msg'] = 'No records in relation table `%s`. Please fill in records.';
		$response['msg_args'] = [$zz_conf['relations_table']];
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
 * @global array $zz_conf
 *		change $zz_conf['int']['upload_cleanup_files']
 * @return bool true: files were added, false: nothing was changed
 */
function zz_integrity_check_files($dependent_ids) {
	global $zz_conf;
	if (empty($dependent_ids)) return false;
	$return = false;
	
	foreach ($dependent_ids as $db_name => $tables) {
		foreach ($tables as $table => $ids) {
			$table = str_replace('_', '-', $table);
			$definition_file = zzform_file($table);
			if (!$definition_file OR !$definition_file['tables']) continue;
			$zz = zz_integrity_include_definition($definition_file['tables']);
			if (!$zz) continue;

			// check if this script fits the table and database name
			zz_sql_prefix_change($zz['table']);
			if (strstr($zz['table'], '.')) {
				list($script_db, $script_table) = explode('.', $zz['table']);
			} else {
				$script_db = $zz_conf['db_name'];
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
						$zz_conf['int']['upload_cleanup_files'][] = $path;
						$return = true;
					}
				}
			}
		}
	}
	return $return;
}

/**
 * include zzform table definition file, just $zz without $zz_conf
 *
 * @param string $filename
 * @return array
 */
function zz_integrity_include_definition($filename) {
	$zz_setting = zz_integrity_include_conf('zz_setting');
	$zz_conf = zz_integrity_include_conf('zz_conf');
	require $filename;
	if (!empty($zz)) return $zz;
	if (!empty($zz_sub)) return $zz_sub;
	// @todo error handling?
	return [];
}

/**
 * return configuration variables read only
 *
 * @param string $config name of configuration variable
 * @return array
 */
function zz_integrity_include_conf($config) {
	global $$config;
	return $$config;
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
			}
		}
	}

	$return = !empty($ops['planned']) ? 'planned' : 'return'; // return for deletion
	foreach ($ops[$return] as $index => $table) {
		if (!in_array($table['tab-rec'], array_keys($fields))) continue;
		$my_field = $fields[$table['tab-rec']];
		if ($ops['record_diff'][$index][$my_field['field_name']] === 'same') continue;
		$new_value = !empty($ops['record_new'][$index][$my_field['field_name']])
			? $ops['record_new'][$index][$my_field['field_name']] : false;
		$old_value = !empty($ops['record_old'][$index][$my_field['field_name']])
			? $ops['record_old'][$index][$my_field['field_name']] : false;
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
		//			require_once $zz_conf['dir_inc'].'/list.inc.php';
		//			$sql = zz_list_filter_sql($zz_tab[$tab]['filter'], $sql, $_GET['filter']);
		//		}
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
			$field_def = zz_db_columns(
				$zz_tab[$tab]['db_name'].'.'.$zz_tab[$tab]['table'], $my_field['field_name']
			);
			if (empty($field_def['max_int_value'])) {
				zz_error_log([
					'msg_dev' => 'Field has no maximum integer value (is it an integer?): %s.%s.%s',
					'msg_dev_args' => [$zz_tab[$tab]['db_name'], $zz_tab[$tab]['table'], $my_field['field_name']]
				]);
				continue;
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
		$sql = 'UPDATE %s SET %s = %s %s 1 WHERE %s IN (%s)';
		$sql = sprintf($sql
			, $zz_tab[$tab]['table']
			, $my_field['field_name']
			, $my_field['field_name']
			, ($new_value AND ($new_value < $old_value OR !$old_value)) ? '+': '-'
			, $zz_tab[$tab][$rec]['id']['field_name']
			, implode(',', $updates)
		);
		$result = zz_db_change($sql);
	}
}
