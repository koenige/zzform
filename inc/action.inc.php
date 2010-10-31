<?php 

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2004-2010
// scripts for action: update, delete, insert or review a record


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
 * @param array $zz
 * @param array $zz_tab
 * @param bool $validation
 * @param array $zz_var
 * @global array $zz_error
 * @global array $zz_conf
 * @return array ($zz, $zz_tab, $validation, $zz_var)
 * @see zz_upload_get(), zz_upload_prepare(), zz_set_subrecord_action(),
 *		zz_validate(), zz_check_integrity(), zz_upload_cleanup(), 
 *		zz_prepare_for_db(), zz_log_sql(), zz_foldercheck(), zz_upload_action()
 */
function zz_action($zz, $zz_tab, $validation, $zz_var) {
	global $zz_conf;
	global $zz_error;
	
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$zz_var['record_action'] = false;

	// assign POST values to each subrecord
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if (!$tab) {  // main record already assigned
				if (!empty($zz_conf['action']['upload'])) {
					$zz = zz_record_info($zz, $zz_tab, $tab, $rec, 'not_validated');
				}
				continue;
			}
			$zz_tab[$tab][$rec]['POST'] = $zz_tab[$tab]['POST'][$rec];
			if (!empty($zz_conf['action']['upload'])) {
				$zz = zz_record_info($zz, $zz_tab, $tab, $rec, 'not_validated');
			}
		}
	}

	// get images from different locations than upload
	// if any other action before insertion/update/delete is required
	if (!empty($zz_conf['action']['upload'])) {
		include $zz_conf['action_dir'].'/'.$zz_conf['action']['upload'].'.inc.php';
		unset($zz['not_validated']);
		unset($zz['record_old']);
		unset($zz['record_new']);
		unset($zz['record_diff']);
	}
	
	//	### Check for validity, do some operations ###
	if (!empty($zz_var['upload_form'])) { // do only for zz_tab 0 0 etc. not zz_tab 0 sql etc.
		zz_upload_get($zz_tab); // read upload image information, as required
		if ($zz_var['action'] != 'delete') {
			$zz_tab = zz_upload_prepare($zz_tab); // read upload image information, as required
		}
	}
	if ($zz_error['error']) return zz_return(array($zz, $zz_tab, $validation, $zz_var));

	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if ($tab) {
				// only if $tab and $rec != 0, i. e. only for subtables!
				// set action field in zz_tab-array, 
				$zz_tab[$tab] = zz_set_subrecord_action($zz_tab[$tab], $rec, $zz_tab[0][0]['action']);
				if ($zz_tab[$tab][$rec]['action'] == 'ignore') continue;
			}
			if ($zz_tab[$tab][$rec]['action'] == 'insert' 
				OR $zz_tab[$tab][$rec]['action'] == 'update') {
				// don't validate record which only will be shown!!
				if ($zz_tab[$tab][$rec]['access'] == 'show') continue;
			
				// first part of validation where field values are independent
				// from other field values
				$zz_tab[$tab][$rec] = zz_validate($zz_tab[$tab][$rec], $zz_tab[$tab]['db_name']
					.'.'.$zz_tab[$tab]['table'], $zz_tab[$tab]['table_name'], $rec, $zz_tab[0][0]); 
				if ($tab) {
					// write changed POST values back to main POST array
					// todo: let the next functions access the main POST array 
					// differently
					$zz_tab[0][0]['POST'][$zz_tab[$tab]['table_name']][$rec] = $zz_tab[$tab][$rec]['POST'];
					foreach ($zz_tab[$tab][$rec]['extra'] AS $key => $value)
						$zz_tab[0][0]['extra'][$zz_tab[$tab]['table_name'].'['.$rec.']['.$key.']'] = $value;
				}
			}
		}
	}

	// check referential integrity
	if (file_exists($zz_conf['dir_inc'].'/integrity.inc.php')) {
		require_once $zz_conf['dir_inc'].'/integrity.inc.php';
		// get table relations
		$zz_var['relations'] = zz_integrity_relations($zz_conf['relations_table']);
		// get record IDs of all records in table definition (1 main, n sub records)
		$record_ids = zz_integrity_record_ids($zz_tab);
		// if no record IDs = no deletion is possible
		if ($record_ids) {
			// get record IDs of dependent records which have 'delete' set
			// in table relations
			$dependent_ids = zz_integrity_dependent_record_ids($zz_tab, $zz_var['relations']);
			// merge arrays for later
			$deletable_ids = zz_array_merge($record_ids, $dependent_ids);
			$zz_var['integrity'] = zz_integrity_check($deletable_ids, $zz_var['relations']);
			// return database errors
			if ($zz_error['error']) return zz_return(array($zz, $zz_tab, $validation, $zz_var));
			// if something was returned, validation failed because there probably are records
			if ($zz_var['integrity']) $validation = false;
		}
	}

	foreach ($zz_tab as $tab => $my_tab) {
		$my_recs = 0;
		foreach (array_keys($my_tab) as $rec) {
			if (!is_numeric($rec)) continue;
			if ($my_tab[$rec]['action'] == 'ignore') continue;
			if ($tab AND $my_tab['min_records_required']) {
				// add record count to check if we got enough of them
				if ($my_tab[$rec]['action'] != 'delete') $my_recs++;
			}
			if ($my_tab[$rec]['validation']) continue;
			$validation = false;
		}
		// check if enough records, just for subtables ($tab = 1...n)
		if ($tab AND $my_recs < $my_tab['min_records_required']
			AND $zz_var['action'] != 'delete') {
			// mark it!
			$zz_tab[0][0]['fields'][$my_tab['no']]['check_validation'] = false;
			// show error message
			$zz_error['validation']['msg'][] = sprintf(zz_text('Minimum of records for table `%s` was not met (%d)'), 
				zz_text($zz['fields'][$my_tab['no']]['title']), $my_tab['min_records_required']);
			$validation = false;
		}
	}
	
	if (!$validation) {
		// delete temporary unused files
		if (!empty($zz_var['upload_form'])) zz_upload_cleanup($zz_tab); 
		return zz_return(array($zz, $zz_tab, $validation, $zz_var));
	}

	if ($zz_conf['modules']['debug']) zz_debug("validation successful");

	foreach ($zz_tab as $tab => $my_tab) {
		foreach ($my_tab as $rec => $my_rec) {
			if (!is_numeric($rec)) continue;
			$zz = zz_record_info($zz, $zz_tab, $tab, $rec, 'planned');
			if ($my_rec['action'] != 'insert' 
				AND $my_rec['action'] != 'update') continue;
			if ($my_rec['access'] == 'show') continue;
			// do something with the POST array before proceeding
			$zz_tab[$tab][$rec] = zz_prepare_for_db($my_rec, '`'.$my_tab['db_name'].'`'
				.'.'.$my_tab['table'], $zz_tab[0][0]['POST']); 
		}
	}

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
			$my_rec['fields'] = array();
			$my_rec['id']['field_name'] = $my_tab['id_field_name'];
			$my_rec['id']['value'] = $del_id;
			$my_rec['POST'][$my_rec['id']['field_name']] = $del_id;
			$zz_tab[$tab][] = $my_rec;
			unset($my_rec);
		}
	}

	// if any other action before insertion/update/delete is required
	if (!empty($zz_conf['action']['before_'.$zz_var['action']]))
		include $zz_conf['action_dir'].'/'.$zz_conf['action']['before_'.$zz_var['action']].'.inc.php';
	// 'planned' is a variable just for custom 'action' scripts
	unset($zz['planned']);
	unset($zz['record_old']);
	unset($zz['record_new']);
	unset($zz['record_diff']);

	$sql_edit = '';
	foreach (array_keys($zz_tab) as $tab)
		foreach (array_keys($zz_tab[$tab]) as $rec) if (is_numeric($rec)) {
		if ($zz_tab[$tab][$rec]['action'] == 'ignore') continue;
		
		// get database name for query
		$me_db = false;
		if ($zz_conf['db_main']) {
			// the 'main' zzform() database is different from the database for 
			// the main record, so check against db_main
			if ($zz_tab[$tab]['db_name'] != $zz_conf['db_main']) 
				$me_db = $zz_tab[$tab]['db_name'].'.';
		} else {
			// the 'main' zzform() database is equal to the database for the 
			// main record, so check against db_name
			if ($zz_tab[$tab]['db_name'] != $zz_conf['db_name']) 
				$me_db = $zz_tab[$tab]['db_name'].'.';
		}
		$me_sql = false;
		
	//	### Do nothing with the record, here: main record ###

		if ($zz_tab[$tab][$rec]['access'] == 'show') {
			$me_sql = 'SELECT 1';
		
	//	### Insert a record ###
	
		} elseif ($zz_tab[$tab][$rec]['action'] == 'insert') {
			$field_values = array();
			$field_list = array();
			foreach ($zz_tab[$tab][$rec]['fields'] as $field) {
				if (!$field['in_sql_query']) continue;
				if ($field['type'] == 'id') continue; // ID is empty anyways
				$field_list[] = '`'.$field['field_name'].'`';
				$field_values[] = $zz_tab[$tab][$rec]['POST_db'][$field['field_name']];
			}
			$me_sql = ' INSERT INTO '.$me_db.$zz_tab[$tab]['table'].' ('
				.implode(', ', $field_list).') VALUES ('.implode(', ', $field_values).')';
			
	// ### Update a record ###

		} elseif ($zz_tab[$tab][$rec]['action'] == 'update') {
			$update_values = '';
			$fields = array();
			foreach ($zz_tab[$tab][$rec]['fields'] as $field)
				if ($field['type'] != 'subtable' AND $field['type'] != 'id' && $field['in_sql_query']) {
					if ($field['type'] != 'timestamp' AND empty($field['dont_check_on_update']))
						$fields[] = $field['field_name'];
					if ($update_values) $update_values.= ', ';
					$update_values.= '`'.$field['field_name'].'` = '.$zz_tab[$tab][$rec]['POST_db'][$field['field_name']];
				}
			if ($update_values) {
				$equal = true; // old and new record are equal
				// check existing record
				foreach ($fields as $field_name) {
					if (!isset($zz_tab[$tab][$rec]['POST'][$field_name])) continue;
					// 'existing' not set: should not happen
					if (!isset($zz_tab[$tab]['existing'][$rec])) continue;
					if ($zz_tab[$tab][$rec]['POST'][$field_name] != $zz_tab[$tab]['existing'][$rec][$field_name])
						$equal = false;
				}
				if (!$equal)
					$me_sql = ' UPDATE '.$me_db.$zz_tab[$tab]['table']
						.' SET '.$update_values.' WHERE '.$zz_tab[$tab][$rec]['id']['field_name']
						.' = "'.$zz_tab[$tab][$rec]['id']['value'].'"';
				else
					$me_sql = 'SELECT 1'; // nothing to update, existing record is equal
			} else {
				$me_sql = 'SELECT 1'; // nothing to update, just detail records
			}

	// ### Delete a record ###

		} elseif ($zz_tab[$tab][$rec]['action'] == 'delete') {
			// no POST_db, because here, validation is not neccessary
			$me_sql = ' DELETE FROM '.$me_db.$zz_tab[$tab]['table']
				.' WHERE '.$zz_tab[$tab][$rec]['id']['field_name']." = '"
				.$zz_tab[$tab][$rec]['id']['value']."'"
				.' LIMIT 1';

	// ### Again, do nothing with the record, here: detail record

		} elseif ($zz_tab[$tab][$rec]['action'] == false) {
			continue;
		}
		
		if (!$sql_edit) $sql_edit = $me_sql;
		else 			$detail_sql_edit[$tab][$rec] = $me_sql;
	}
	// ### Perform database query and additional actions ###
	
	$del_msg = array();

	// if delete a record, first delete detail records so that in case of an 
	// error there are no orphans
	// 1. detail records from relations-table
	if (isset($dependent_ids)) {
		foreach ($dependent_ids as $db_name => $tables) {
			$me_db = '';
			if ($zz_conf['db_main']) {
				// the 'main' zzform() database is different from the database for 
				// the main record, so check against db_main
				if ($db_name != $zz_conf['db_main']) 
					$me_db = '`'.$db_name.'`.';
			} else {
				// the 'main' zzform() database is equal to the database for the 
				// main record, so check against db_name
				if ($db_name != $zz_conf['db_name']) 
					$me_db = '`'.$db_name.'`.';
			}
			foreach ($tables as $table => $fields) {
				$id_field = key($fields);
				$ids = array_shift($fields);
				$me_sql = ' DELETE FROM '.$me_db.$table
					.' WHERE `'.$id_field.'` IN ('.implode(',', $ids).')'
					.' LIMIT '.count($ids);
				$id = false;
				if (count($ids) == 1) $id = array_shift($ids);
				$result = zz_db_change($me_sql, $id);
				if ($result['action']) {
					$del_msg[] = 'integrity delete: '.$me_sql.'<br>';
				} else {
					$result['error']['msg'] = 'Detail record could not be deleted';
					$zz_error[] = $result['error'];
				}
			}
		}
	}
	// 2. detail records in form
	if ($zz_tab[0][0]['action'] == 'delete' AND isset($detail_sql_edit)) { 
		foreach (array_keys($detail_sql_edit) as $tab)
			foreach (array_keys($detail_sql_edit[$tab]) as $rec) {
				// might already deleted if in dependent IDs but that does not matter
				$result = zz_db_change($detail_sql_edit[$tab][$rec], $zz_tab[$tab][$rec]['id']['value']);
				if ($result['action']) {
					$del_msg[] = 'zz_tab '.$tab.' '.$rec.': '.$detail_sql_edit[$tab][$rec].'<br>';
					unset($detail_sql_edit[$tab][$rec]);
					// save record values for use outside of zzform()
					$zz = zz_record_info($zz, $zz_tab, $tab, $rec);
				} else { // something went wrong, but why?
					$result['error']['msg'] = 'Detail record could not be deleted';
					$zz_error[] = $result['error'];
					$zz_tab[$tab][$rec]['error'] = $result['error'];
//					not sure whether to cancel any further operations here, TODO
//					return zz_error(); // get out of function, ignore rest 
					// (this should never happen, just if there are database errors etc.)
				}
			}
	}

	if ($zz_conf['modules']['debug'] AND $zz_conf['debug']) {
		$zz['output'].= '<br>';
		$zz['output'].= 'Main ID value: '.$zz_tab[0][0]['id']['value'].'<br>';
		$zz['output'].= 'Main SQL query: '.$sql_edit.'<br>';
		if ($del_msg) {
			$zz['output'].= 'Further SQL queries:<br>'.(implode('', $del_msg));
			unset($del_msg);
		}
	}

	$result = zz_db_change($sql_edit, $zz_tab[0][0]['id']['value']);
	if ($result['action']) {
		if ($zz_tab[0][0]['action'] == 'insert') {
			$zz_tab[0][0]['id']['value'] = $result['id_value']; // for requery
		}
		// save record values for use outside of zzform()
		if ($result['action'] == 'nothing') $zz_tab[0][0]['actual_action'] = 'nothing';
		$zz = zz_record_info($zz, $zz_tab);
		$zz_var['record_action'] = true;
		if (isset($detail_sql_edit))
			foreach (array_keys($detail_sql_edit) as $tab)
				foreach (array_keys($detail_sql_edit[$tab]) as $rec) {
					$detail_sql = $detail_sql_edit[$tab][$rec];
					$detail_sql = str_replace('[FOREIGN_KEY]', '"'.$zz_tab[0][0]['id']['value'].'"', $detail_sql);
					if (!empty($zz_tab[$tab]['detail_key'])) {
						// TODO: allow further detail keys
						// if not all files where uploaded, go up one detail record until
						// we got an uploaded file
						while (empty($zz_tab[$zz_tab[$tab]['detail_key'][0]['tab']][$zz_tab[$tab]['detail_key'][0]['rec']]['id']['value'])) {
							$zz_tab[$tab]['detail_key'][0]['rec']--;
						}
						$detail_sql = str_replace('[DETAIL_KEY]', '"'.$zz_tab[$zz_tab[$tab]['detail_key'][0]['tab']][$zz_tab[$tab]['detail_key'][0]['rec']]['id']['value'].'"', $detail_sql);
					}
					// for deleted subtables, id value might not be set, so get it here.
					// TODO: check why it's not available beforehands, might be unneccessary security risk.
					if (empty($zz_tab[$tab][$rec]['id']['value'])
						AND !empty($zz_tab[$tab][$rec]['POST'][$zz_tab[$tab][$rec]['id']['field_name']]))
						$zz_tab[$tab][$rec]['id']['value'] = $zz_tab[$tab][$rec]['POST'][$zz_tab[$tab][$rec]['id']['field_name']];

					$result = zz_db_change($detail_sql, $zz_tab[$tab][$rec]['id']['value']);
					if (!$result['action']) { 
						// This should never occur, since all checks say that 
						// this change is possible
						// only if duplicate entry
						$result['error']['msg'] = 'Detail record could not be handled';
						$result['error']['level'] = E_USER_WARNING;
						$zz_error[] = $result['error'];
						$zz_tab[$tab][$rec]['error'] = $result['error'];
						$zz_var['record_action'] = false;
						$validation = false; 
						$zz_tab[0][0]['fields'][$zz_tab[$tab]['no']]['check_validation'] = false;
					} elseif ($zz_tab[$tab][$rec]['action'] == 'insert') {
						$zz_tab[$tab][$rec]['id']['value'] = $result['id_value']; // for requery
					}
					// save record values for use outside of zzform()
					if ($result['action'] == 'nothing')
						$zz_tab[$tab][$rec]['actual_action'] = 'nothing';
					$zz = zz_record_info($zz, $zz_tab, $tab, $rec);
					if ($zz_conf['modules']['debug'] AND $zz_conf['debug']) {
						$zz['output'].= 'Further SQL queries:<br>'
							.'zz_tab '.$tab.' '.$rec.': '.$detail_sql.'<br>';
					}
				}
		if (!empty($zz_conf['action']['after_'.$zz_var['action']])) 
			include $zz_conf['action_dir'].'/'.$zz_conf['action']['after_'.$zz_var['action']].'.inc.php'; 
			// if any other action after insertion/update/delete is required
		if (!empty($zz_conf['folder']) && $zz_tab[0][0]['action'] == 'update')
			// rename connected folder after record has been updated
			zz_foldercheck($zz_tab, $zz_conf);
		if (!empty($zz_var['upload_form'])) {
			zz_upload_action($zz_tab, $zz_conf); // upload images, delete images, as required
			if ($zz_error['error']) return zz_return(array($zz, $zz_tab, $validation, $zz_var));
		}
		if ($zz_var['record_action']) $zz['result'] = 'successful_'.$zz_tab[0][0]['action'];
	} else {
		// Output Error Message
		if ($zz_var['action'] == 'insert') $zz_tab[0][0]['id']['value'] = false; // for requery
		$result['error']['level'] = E_USER_WARNING;
		$zz_error[] = $result['error'];
		$zz_tab[0][0]['error'] = $result['error'];
		$zz = zz_record_info($zz, $zz_tab);
		$validation = false; // show record again!
	}
	if ($zz_var['record_action'] == 'successful_update') {
		$update = false;
		foreach ($zz['return'] as $my_table) {
			// check for action in main record and detail records
			if ($my_table['action'] != 'nothing') $update = true;
		}
		if (!$update) $zz['result'] = 'no_update';
	}
	
	if (!empty($zz_var['upload_form'])) zz_upload_cleanup($zz_tab); // delete temporary unused files
	return zz_return(array($zz, $zz_tab, $validation, $zz_var));
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
 * @param array $my_tab = $zz_tab[$tab]
 * @param int $rec
 * @param string $action action of main table ($zz_tab[0][0]['action']
 * @global array $zz_conf
 * @return array $my_tab ($zz_tab[$tab])
 *		changed: $zz_tab[$tab][$rec]['action'], $zz_tab[$tab]['zz_subtable_deleted']
 *		may unset($zz_tab[$tab][$rec])
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_set_subrecord_action($my_tab, $rec, $action) {
	// initialize variables
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$values = '';

	// check whether there are values in fields
	// this is done to see what to do with subrecord (insert, update, delete)
	foreach ($my_tab[$rec]['fields'] as $field) {
		// depending on ID, set action
		if ($field['type'] == 'id') {
			if (($my_tab['tick_to_save'] AND $my_tab[$rec]['save_record'])
				OR empty($my_tab['tick_to_save'])) {
				if (!isset($my_tab[$rec]['POST'][$field['field_name']]))
					$my_tab[$rec]['action'] = 'insert';
				else
					$my_tab[$rec]['action'] = 'update';
			} elseif ($my_tab['tick_to_save'] AND !$my_tab[$rec]['save_record']) {
				if (!isset($my_tab[$rec]['POST'][$field['field_name']]))
					$my_tab[$rec]['action'] = 'ignore'; // ignore subrecord
				else
					$my_tab[$rec]['action'] = 'delete';
			}
		}
		// check if something was posted and write it down in $values
		// so we know later, if this record should be added
		if (!isset($my_tab[$rec]['POST'][$field['field_name']])) continue;
		if ($field['type'] == 'number' && isset($field['number_type']) 
			&& ($field['number_type'] == 'latitude' OR $field['number_type'] == 'longitude')) {
			// coordinates:
			// rather problematic stuff because this input is divided into several fields
			$fvalues = zz_geo_check_if_coords($my_tab[$rec]['POST'][$field['field_name']]);
		} else {
			$fvalues = $my_tab[$rec]['POST'][$field['field_name']];
		} 
		// timestamp, foreign_key and id will always be ignored
		// since there is no user input
		if ($field['type'] == 'timestamp' OR $field['type'] == 'id'
			OR $field['type'] == 'foreign_key') continue;
		// check def_val_ignore, some auto values/values/default values will be ignored 
		if (!empty($field['def_val_ignore'])) {
			if (empty($field['value']) AND !empty($field['default'])
				AND $field['default'] != $my_tab[$rec]['POST'][$field['field_name']]) {
			// defaults will only be ignored if different from default value
			// but only if no value is set!
				$values .= $fvalues;				
			} else {
			// values need not to be checked, they'll always be ignored
			// (and it's not easy to check them because they might be in a 'values'-array)
				$values .= '';
			}
		} else {
			$values .= $fvalues;
		}
	}

	if (!empty($my_tab['records_depend_on_upload']) AND !empty($my_tab[$rec]['no_file_upload'])) {
		$values = false;
	} elseif (!empty($my_tab['records_depend_on_upload']) AND $my_tab[$rec]['action'] == 'insert'
		AND empty($my_tab[$rec]['file_upload'])) {
		$values = false;
	} elseif (!empty($my_tab['records_depend_on_upload_more_than_one']) AND $my_tab[$rec]['action'] == 'insert'
		AND empty($my_tab[$rec]['file_upload']) AND $rec) {
		$values = false;
	}

	// todo: seems to be twice the same operation since $tab and $rec are !0
	if ($my_tab['access'] == 'show') {
		$values = true; // only display subrecords, no deletion, no change!
		$my_tab[$rec]['action'] = false; // no action insert or update, values are only shown!
	}
	if (!$values) {
		if ($my_tab[$rec]['id']['value']) {
			$my_tab[$rec]['action'] = 'delete';
 			// if main tab will be deleted, this record will be deleted anyways
 			if ($action != 'delete')
				$my_tab['subtable_deleted'][] = $my_tab[$rec]['id']['value']; // only for requery record on error!
		} else {
			$my_tab[$rec]['action'] = 'ignore';
		}
	}
	
	if ($action == 'delete') {
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_prepare_for_db($my_rec, $db_table, $main_post) {
	global $zz_conf;
	global $zz_error;

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	if ($my_rec['last_fields']) { 
	// these fields have to be handled after others because they might get data 
	// from other fields (e. g. upload_fields)
		foreach ($my_rec['last_fields'] as $f)
			//	call function: generate ID
			if ($my_rec['fields'][$f]['type'] == 'identifier') {
				$func_vars = zz_get_identifier_vars($my_rec, $f, $main_post);
				$conf = (!empty($my_rec['fields'][$f]['conf_identifier']) 
					? $my_rec['fields'][$f]['conf_identifier'] : false);
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] 
					= zz_create_identifier($func_vars, $conf, $my_rec, $db_table, $f);
			}
	}
	unset($my_rec['last_fields']);
	
	$my_rec['POST_db'] = $my_rec['POST'];
	foreach (array_keys($my_rec['fields']) as $f) {
	//	set
		if ($my_rec['fields'][$f]['type'] == 'select' 
			AND (isset($my_rec['fields'][$f]['set']) OR isset($my_rec['fields'][$f]['set_sql']))) {
			if (!empty($my_rec['POST'][$my_rec['fields'][$f]['field_name']]))
				$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = implode(',', $my_rec['POST'][$my_rec['fields'][$f]['field_name']]);
			else
				$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = '';
		}
	//	slashes, 0 and NULL
		$unwanted = array('calculated', 'image', 'upload_image', 'id', 
			'foreign', 'subtable', 'foreign_key', 'translation_key', 
			'detail_key', 'display', 'option', 'write_once');
		if (!in_array($my_rec['fields'][$f]['type'], $unwanted)) {
			if ($my_rec['POST_db'][$my_rec['fields'][$f]['field_name']]) {
				$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = '"'
					.zz_db_escape($my_rec['POST_db'][$my_rec['fields'][$f]['field_name']]).'"';
			} else {
				if (isset($my_rec['fields'][$f]['number_type']) AND ($my_rec['POST'][$my_rec['fields'][$f]['field_name']] !== '') // type string, different from 0
					AND $my_rec['fields'][$f]['number_type'] == 'latitude' 
					|| $my_rec['fields'][$f]['number_type'] == 'longitude')
					$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = '0';
				elseif (!empty($my_rec['fields'][$f]['null'])) 
					$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = '0';
				elseif (!empty($my_rec['fields'][$f]['null_string'])) 
					$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = '""';
				else 
					$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = 'NULL';
			}
		}
	// foreign_key
		if ($my_rec['fields'][$f]['type'] == 'foreign_key') 
			$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = '[FOREIGN_KEY]';
	// detail_key
		if ($my_rec['fields'][$f]['type'] == 'detail_key') 
			$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = '[DETAIL_KEY]';
	// translation_key
		if ($my_rec['fields'][$f]['type'] == 'translation_key') 
			$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = $my_rec['fields'][$f]['translation_key'];
	// timestamp
		if ($my_rec['fields'][$f]['type'] == 'timestamp') 
			$my_rec['POST_db'][$my_rec['fields'][$f]['field_name']] = 'NOW()';
	}
	return zz_return($my_rec);
}

/**
 * save record information in array for return and use in user action-scripts 
 *
 * @param array $zz
 * @param array $my_rec $zz_tab[$tab][$rec]
 * @param int $tab
 * @param int $rec
 * @param string $type (optional) 'return', 'planned'
 * @return array $zz
 *		'return' ('action' might be nothing if update, but nothing was updated),
 *		'record_new', 'record_old'
 */
function zz_record_info($zz, $zz_tab, $tab = 0, $rec = 0, $type = 'return') {
	if ($zz_tab[$tab][$rec]['action'] == 'ignore') return $zz;
	
	if (!isset($zz['record_new'])) $zz['record_new'] = array();
	if (!isset($zz['record_old'])) $zz['record_old'] = array();
	if (!isset($zz['record_diff'])) $zz['record_diff'] = array();
	
	$rn = array();
	$ro = array();
	
	// set index to make sure that main record is always 0
	if (!$tab AND !$rec) $index = 0;
	elseif (!isset($zz[$type])) $index = 1;
	else $index = count($zz[$type]);

	// set information on successful record operation
	$zz[$type][$index] = array(
		'table' => $zz_tab[$tab]['table'],
		'id_field_name' => $zz_tab[$tab][$rec]['id']['field_name'], 
		'id_value' => $zz_tab[$tab][$rec]['id']['value'],
		'action' => !empty($zz_tab[$tab][$rec]['actual_action']) 
			? $zz_tab[$tab][$rec]['actual_action'] : $zz_tab[$tab][$rec]['action'],
		'tab-rec' => $tab.'-'.$rec,
		'error' => !empty($zz_tab[$tab][$rec]['error'])
			? $zz_tab[$tab][$rec]['error'] : false
	);

	// set new record (no new record if record was deleted)
	if (!empty($zz_tab[$tab][$rec]['POST']) 
		AND $zz_tab[$tab][$rec]['action'] != 'delete') {
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
		$zz['record_new'][$index] = $rn;
	} else $zz['record_new'][$index] = array();
	
	// set old record
	if (!empty($zz_tab[$tab]['existing'][$rec])) {
		$zz['record_old'][$index] = $zz_tab[$tab]['existing'][$rec];
		$ro = $zz_tab[$tab]['existing'][$rec];
	} else
		$zz['record_old'][$index] = array();
	
	// diff old record and new record
	$rd = array();
	if (!$rn) {
		foreach ($zz_tab[$tab][$rec]['fields'] as $field) {
			if (empty($field['field_name'])) continue;
			$rd[$field['field_name']] = 'delete';
		}
	} else {
		foreach ($rn as $field => $value) {
			if (!key_exists($field, $ro)) {
				$rd[$field] = 'insert';
			} elseif ($ro[$field] != $value) {
				$rd[$field] = 'diff';
			} else {
				$rd[$field] = 'same';
			}
		}
	}
	$zz['record_diff'][$index] = $rd;

	return $zz;
}

?>