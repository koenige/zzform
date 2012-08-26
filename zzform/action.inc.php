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
 * @copyright Copyright © 2004-2012 Gustaf Mossakowski
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
 * @global array $zz_error
 * @global array $zz_conf
 * @return array ($ops, $zz_tab, $validation, $zz_var)
 * @see zz_upload_get(), zz_upload_prepare(), zz_set_subrecord_action(),
 *		zz_validate(), zz_integrity_check(), zz_upload_cleanup(), 
 *		zz_prepare_for_db(), zz_log_sql(), zz_foldercheck(), zz_upload_action()
 */
function zz_action($ops, $zz_tab, $validation, $zz_var) {
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
					$ops = zz_record_info($ops, $zz_tab, $tab, $rec, 'not_validated');
				}
				continue;
			}
			$zz_tab[$tab][$rec]['POST'] = $zz_tab[$tab]['POST'][$rec];
			if (!empty($zz_conf['action']['upload'])) {
				$ops = zz_record_info($ops, $zz_tab, $tab, $rec, 'not_validated');
			}
		}
	}

	// get images from different locations than upload
	// if any other action before insertion/update/delete is required
	if ($change = zz_action_function('upload', $ops)) {
		list($ops, $zz_tab) = zz_action_change($ops, $zz_tab, $change);
		unset($ops['not_validated']);
		unset($ops['record_old']);
		unset($ops['record_new']);
		unset($ops['record_diff']);
	}
	
	//	### Check for validity, do some operations ###
	if (!empty($zz_var['upload_form'])) {
		// do only for zz_tab 0 0 etc. not zz_tab 0 sql etc.
		// read upload image information, as required
		$zz_tab = zz_upload_get($zz_tab); 
		if ($zz_var['action'] !== 'delete') {
			// read upload image information, as required
			$zz_tab = zz_upload_prepare($zz_tab);
		}
	}
	if ($zz_error['error'])
		return zz_return(array($ops, $zz_tab, $validation, $zz_var));

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
			
				// first part of validation where field values are independent
				// from other field values
				$zz_tab[$tab][$rec] = zz_validate($zz_tab[$tab][$rec], $zz_tab[$tab]['db_name']
					.'.'.$zz_tab[$tab]['table'], $zz_tab[$tab]['table_name'], $tab, $rec, $zz_tab); 
				if ($tab) {
					// write changed POST values back to main POST array
					// @todo: let the next functions access the main POST array 
					// differently
					$zz_tab[0][0]['POST'][$zz_tab[$tab]['table_name']][$rec] = $zz_tab[$tab][$rec]['POST'];
					foreach ($zz_tab[$tab][$rec]['extra'] AS $key => $value)
						$zz_tab[0][0]['extra'][$zz_tab[$tab]['table_name'].'['.$rec.']['.$key.']'] = $value;
				}
			}
		}
	}

	// check referential integrity
	if ($zz_conf['check_referential_integrity']) {
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
			if ($zz_error['error'])
				return zz_return(array($ops, $zz_tab, $validation, $zz_var));
			// if something was returned, validation failed because there 
			// probably are records
			if ($zz_var['integrity']) $validation = false;
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
			$zz_error['validation']['msg'][] = sprintf(zz_text('Minimum of records for table `%s` was not met (%d)'), 
				zz_text($zz_tab[0][0]['fields'][$my_tab['no']]['title']), $my_tab['min_records_required']);
			$zz_error['validation']['log_post_data'] = true;
			$validation = false;
		}
	}
	
	if (!$validation) {
		if (!empty($zz_var['upload_form'])) zz_upload_cleanup($zz_tab, false); 
		return zz_return(array($ops, $zz_tab, $validation, $zz_var));
	}

	if ($zz_conf['modules']['debug']) zz_debug("validation successful");

	if (!empty($zz_conf['action']['before_'.$zz_var['action']])) {
		foreach ($zz_tab as $tab => $my_tab) {
			foreach ($my_tab as $rec => $my_rec) {
				if (!is_numeric($rec)) continue;
				$ops = zz_record_info($ops, $zz_tab, $tab, $rec, 'planned');
			}
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
	if ($change = zz_action_function('before_'.$zz_var['action'], $ops)) {
		list($ops, $zz_tab) = zz_action_change($ops, $zz_tab, $change);
		// 'planned' is a variable just for custom 'action' scripts
		unset($ops['planned']);
	}
	unset($ops['record_old']);
	unset($ops['record_new']);
	unset($ops['record_diff']);

	if ($zz_error['error']) { // repeat, might be set in before_action
		$zz_error['error'] = false;
		$validation = false;
		// delete temporary unused files
		if (!empty($zz_var['upload_form'])) zz_upload_cleanup($zz_tab); 
		return zz_return(array($ops, $zz_tab, $validation, $zz_var));
	}

	$sql_edit = '';
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if ($zz_tab[$tab][$rec]['action'] !== 'insert' 
				AND $zz_tab[$tab][$rec]['action'] !== 'update') continue;
			if ($zz_tab[$tab][$rec]['access'] === 'show') continue;
			// do something with the POST array before proceeding
			$zz_tab[$tab][$rec] = zz_prepare_for_db($zz_tab[$tab][$rec], '`'
				.$zz_tab[$tab]['db_name'].'`'.'.'.$zz_tab[$tab]['table'], $zz_tab[0][0]['POST']); 
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

		} elseif ($zz_tab[$tab][$rec]['action'] === 'update') {
			$update_values = array();
			$fields = array();
			$equal = true; // old and new record are said to be equal
			foreach ($zz_tab[$tab][$rec]['fields'] as $field) {
				if ($field['type'] == 'subtable') continue;
				if ($field['type'] == 'id') continue;
				if (!$field['in_sql_query']) continue;
				$update = true;
				// check if field values are different to existing record
				if (isset($zz_tab[$tab][$rec]['POST'][$field['field_name']])
					AND isset($zz_tab[$tab]['existing'][$rec])) {
					// ok, we have values which might be compared
					if ($field['type'] != 'timestamp' 
						AND empty($field['dont_check_on_update'])) {
						// check difference to existing record
						$post = $zz_tab[$tab][$rec]['POST'][$field['field_name']];
						if ($field['type'] == 'select' AND !empty($field['set'])) {
							// to compare it, make array into string
							if (is_array($post)) $post = implode(',', $post);
						}
						if (!isset($zz_tab[$tab]['existing'][$rec][$field['field_name']])) {
							if ($post != NULL) {
								// there's no existing record, sent this query
								$equal = false;
							} else {
								// existing and new value are both NULL or not there
								$update = false;
							}
						} elseif ($post.'' !== $zz_tab[$tab]['existing'][$rec][$field['field_name']].'') {
							// we need to append '' here to compare strings and
							// not numbers (004 !== 4)
							// there's a difference, so we have to sent this query
							$equal = false;
						} else {
							// we don't know yet from this one
							// whether to send the query or not, but we do not
							// need to send the values for this field since they
							// are equal
							$update = false; 
						}
					}
				}
				if (!$update) continue;
				$update_values[] = '`'.$field['field_name'].'` = '.$zz_tab[$tab][$rec]['POST_db'][$field['field_name']];
			}
			if ($update_values AND !$equal) {
				$me_sql = ' UPDATE '.$me_db.$zz_tab[$tab]['table']
					.' SET '.implode(', ', $update_values)
					.' WHERE '.$zz_tab[$tab][$rec]['id']['field_name']
					.' = "'.$zz_tab[$tab][$rec]['id']['value'].'"';
			} else {
				$me_sql = 'SELECT 1'; // nothing to update, just detail records
			}

	// ### Delete a record ###

		} elseif ($zz_tab[$tab][$rec]['action'] === 'delete') {
			// no POST_db, because here, validation is not necessary
			if (is_array($zz_tab[$tab][$rec]['id']['value'])) {
				$me_sql = ' DELETE FROM '.$me_db.$zz_tab[$tab]['table']
					.' WHERE '.$zz_tab[$tab][$rec]['id']['field_name']." IN ("
					.implode(",", $zz_tab[$tab][$rec]['id']['value']).")"
					.' LIMIT '.count($zz_tab[$tab][$rec]['id']['value']);
			} else {
				$me_sql = ' DELETE FROM '.$me_db.$zz_tab[$tab]['table']
					.' WHERE '.$zz_tab[$tab][$rec]['id']['field_name']." = '"
					.$zz_tab[$tab][$rec]['id']['value']."'"
					.' LIMIT 1';
			}

	// ### Again, do nothing with the record, here: detail record

		} elseif ($zz_tab[$tab][$rec]['action'] == false) {
			continue;
		}
		
		if (!$sql_edit) $sql_edit = $me_sql;
		else 			$detail_sqls[$tab][$rec] = $me_sql;
	}
	// ### Perform database query and additional actions ###
	
	$del_msg = array();

	// if delete a record, first delete detail records so that in case of an 
	// error there are no orphans
	// 1. detail records from relations-table
	if (isset($dependent_ids)) {
		foreach ($dependent_ids as $db_name => $tables) {
			$me_db = '';
			if ($zz_conf['int']['db_main']) {
				// the 'main' zzform() database is different from the database for 
				// the main record, so check against db_main
				if ($db_name != $zz_conf['int']['db_main']) 
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
	if ($zz_tab[0][0]['action'] === 'delete' AND isset($detail_sqls)) { 
		foreach (array_keys($detail_sqls) as $tab)
			foreach (array_keys($detail_sqls[$tab]) as $rec) {
				// might already deleted if in dependent IDs but that does not matter
				$result = zz_db_change($detail_sqls[$tab][$rec], $zz_tab[$tab][$rec]['id']['value']);
				if ($result['action']) {
					$del_msg[] = 'zz_tab '.$tab.' '.$rec.': '.$detail_sqls[$tab][$rec].'<br>';
					unset($detail_sqls[$tab][$rec]);
					// save record values for use outside of zzform()
					$ops = zz_record_info($ops, $zz_tab, $tab, $rec);
				} else { // something went wrong, but why?
					$result['error']['msg'] = 'Detail record could not be deleted';
					$zz_error[] = $result['error'];
					$zz_tab[$tab][$rec]['error'] = $result['error'];
					// @todo: not sure whether to cancel any further operations here
				}
			}
	}

	if ($zz_conf['modules']['debug'] AND $zz_conf['debug']) {
		$ops['output'].= '<br>';
		$ops['output'].= 'Main ID value: '.$zz_tab[0][0]['id']['value'].'<br>';
		$ops['output'].= 'Main SQL query: '.$sql_edit.'<br>';
		if ($del_msg) {
			$ops['output'].= 'Further SQL queries:<br>'.(implode('', $del_msg));
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
		$zz_var['record_action'] = true;
		if (isset($detail_sqls)) {
			list($zz_tab, $zz_var, $validation, $ops) 
				= zz_action_details($detail_sqls, $zz_tab, $zz_var, $validation, $ops);
		}
	
		// if any other action after insertion/update/delete is required
		$change = zz_action_function('after_'.$zz_var['action'], $ops);
		list($ops, $zz_tab) = zz_action_change($ops, $zz_tab, $change);

		if (!empty($zz_conf['folder']) && $zz_tab[0][0]['action'] === 'update') {
			// rename connected folder after record has been updated
			$folders = zz_foldercheck($zz_tab);
			if ($folders) $zz_tab[0]['folder'] = $folders;
		}
		if (!empty($zz_var['upload_form'])) {
			// upload images, delete images, as required
			$zz_tab = zz_upload_action($zz_tab);
			$ops['output'] .= zz_error();
			if ($zz_error['error']) {
				zz_upload_cleanup($zz_tab);
				return zz_return(array($ops, $zz_tab, $validation, $zz_var));
			}
		}
		if ($zz_var['record_action']) $ops['result'] = 'successful_'.$zz_tab[0][0]['action'];
	} else {
		// Output Error Message
		if ($zz_var['action'] === 'insert') {
			// for requery
			$zz_tab[0][0]['id']['value'] = false;
		}
		$result['error']['level'] = E_USER_WARNING;
		$zz_error[] = $result['error'];
		$zz_tab[0][0]['error'] = $result['error'];
		$ops = zz_record_info($ops, $zz_tab);
		$validation = false; // show record again!
	}
	if ($zz_var['record_action'] == 'successful_update') {
		$update = false;
		foreach ($ops['return'] as $my_table) {
			// check for action in main record and detail records
			if ($my_table['action'] !== 'nothing') $update = true;
		}
		if (!$update) $ops['result'] = 'no_update';
	}
	
	// delete temporary unused files
	if (!empty($zz_var['upload_form'])) zz_upload_cleanup($zz_tab);
	return zz_return(array($ops, $zz_tab, $validation, $zz_var));
}

/**
 * updates or inserts detail records belonging to the main record
 *
 * @param string $detail_sqls
 * @param array $zz_tab
 * @param array $zz_var
 * @param bool $validation
 * @param array $ops
 * @global array $zz_error
 * @return array
 *		$zz_tab, $zz_var, $validation, $ops
 */
function zz_action_details($detail_sqls, $zz_tab, $zz_var, $validation, $ops) {
	global $zz_error;
	
	foreach (array_keys($detail_sqls) as $tab) {
		foreach (array_keys($detail_sqls[$tab]) as $rec) {
			$my_rec = $zz_tab[$tab][$rec];
			$sql = $detail_sqls[$tab][$rec];
			$sql = str_replace('[FOREIGN_KEY]', '"'.$zz_tab[0][0]['id']['value'].'"', $sql);
			if (!empty($zz_tab[$tab]['detail_key'])) {
				// @todo: allow further detail keys
				// if not all files where uploaded, go up one detail record until
				// we got an uploaded file
				$detail_tab = $zz_tab[$tab]['detail_key'][0]['tab'];
				$detail_rec = $zz_tab[$tab]['detail_key'][0]['rec'];
				while (empty($zz_tab[$detail_tab][$detail_rec]['id']['value'])) {
					$zz_tab[$tab]['detail_key'][0]['rec']--;
				}
				$sql = str_replace('[DETAIL_KEY]', '"'.$zz_tab[$detail_tab][$detail_rec]['id']['value'].'"', $sql);
			}
			// for deleted subtables, id value might not be set, so get it here.
			// @todo: check why it's not available beforehands, might be 
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
				$zz_error[] = $result['error'];
				$zz_tab[$tab][$rec]['error'] = $result['error'];
				$zz_var['record_action'] = false;
				$validation = false; 
				$zz_tab[0][0]['fields'][$zz_tab[$tab]['no']]['check_validation'] = false;
			} elseif ($my_rec['action'] === 'insert') {
				// for requery
				$zz_tab[$tab][$rec]['id']['value'] = $result['id_value'];
			}
			// save record values for use outside of zzform()
			if ($result['action'] === 'nothing')
				$zz_tab[$tab][$rec]['actual_action'] = 'nothing';
			$ops = zz_record_info($ops, $zz_tab, $tab, $rec);
			if ($zz_conf['modules']['debug'] AND $zz_conf['debug']) {
				$ops['output'].= 'Further SQL queries:<br>'
					.'zz_tab '.$tab.' '.$rec.': '.$sql.'<br>';
			}
		}
	}
	return array($zz_tab, $zz_var, $validation, $ops);
}

/**
 * calls a function or includes a file before or after an action takes place
 *
 * @param string $type (upload, before_insert, before_update, before_delete,
 *	'after_insert', 'after_update', 'after_delete', to be set in $zz_conf['action']
 * @param array $ops
 * @global array $zz_conf
 * @global array $zz_error (in case custom error message shall be logged)
 * @return mixed bool true if some action was performed; 
 *	array $change if some values need to be changed
 */
function zz_action_function($type, $ops) {
	global $zz_conf;
	global $zz_error;
	if (empty($zz_conf['action'][$type])) return false;

	$change = array();
	$file = $zz_conf['action_dir'].'/'.$zz_conf['action'][$type].'.inc.php';
	if (file_exists($file)) {
		// a file has to be included
		include $file;
	} else {
		// it's a function
		$change = $zz_conf['action'][$type]($ops);
	}
	if ($change) return $change;
	else return true;
}

/**
 * if the action function returned something, output or record for database
 * will be changed
 *
 * @param array $ops
 * @param array $zz_tab
 * @param array $change string 'output', array 'record_replace'
 * @return array array($ops, $zz_tab)
 */
function zz_action_change($ops, $zz_tab, $change) {
	if (!$change) return array($ops, $zz_tab);
	if ($change === true) return array($ops, $zz_tab);
	
	// output?
	if (!empty($change['output'])) {
		$ops['output'] .= $change['output'];
	}
	
	// record? replace values as needed
	if (!empty($change['record_replace'])) {
		// get record definition from planned or not_validated
		if (!empty($ops['planned'])) $planned = $ops['planned'];
		else $planned = $ops['not_validated'];
		// replace values
		foreach ($change['record_replace'] as $index => $values) {
			list($tab, $rec) = explode('-', $planned[$index]['tab-rec']);
			$zz_tab[$tab][$rec]['POST'] = array_merge($zz_tab[$tab][$rec]['POST'], $values);
		}
	}
	return array($ops, $zz_tab);
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
 *		changed: $zz_tab[$tab][$rec]['action'], $zz_tab[$tab]['zz_subtable_deleted']
 *		may unset($zz_tab[$tab][$rec])
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_set_subrecord_action($zz_tab, $tab, $rec) {
	// initialize variables
	global $zz_conf;
	global $zz_error;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$values = '';
	$my_tab = $zz_tab[$tab];

	// check whether there are values in fields
	// this is done to see what to do with subrecord (insert, update, delete)
	foreach ($my_tab[$rec]['fields'] as $field) {
		// depending on ID, set action
		if ($field['type'] != 'id') continue;
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

	foreach ($my_tab[$rec]['fields'] as $f => $field) {
		// check if some values should be gotten from detail_value/upload fields
		// must be here before setting the action
		if ($zz_tab[$tab][$rec]['access'] === 'show') continue;
		if (!in_array($zz_tab[0][0]['action'], array('insert', 'update'))) continue;
		if (!empty($field['detail_value'])) {
			$value = zz_write_detail_values($zz_tab, $f, $tab, $rec);
			if ($value) $my_tab[$rec]['POST'][$field['field_name']] = $value;
		}
		if (!empty($field['upload_field'])) {
			$value = zz_write_upload_fields($zz_tab, $f, $tab, $rec);
			if ($value) $my_tab[$rec]['POST'][$field['field_name']] = $value;
		}
	}

	foreach ($my_tab[$rec]['fields'] as $field) {
		// check if something was posted and write it down in $values
		// so we know later, if this record should be added
		if (!isset($my_tab[$rec]['POST'][$field['field_name']])) continue;
		$fvalues = $my_tab[$rec]['POST'][$field['field_name']];
		// timestamp, foreign_key and id will always be ignored
		// since there is no user input
		$ignores = array('timestamp', 'id', 'foreign_key', 'translation_key', 'display');
		if (in_array($field['type'], $ignores)) continue;
		if (!empty($field['for_action_ignore'])) continue;
		// check def_val_ignore, some auto values/values/default values will be ignored 
		if (!empty($field['def_val_ignore'])) {
			if (empty($field['value']) AND !empty($field['default'])
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
			$values .= $fvalues;
		}
	}

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

	// @todo: seems to be twice the same operation since $tab and $rec are !0
	if ($my_tab['access'] === 'show') {
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

	if (!empty($my_rec['last_fields'])) { 
	// these fields have to be handled after others because they might get data 
	// from other fields (e. g. upload_fields)
		foreach ($my_rec['last_fields'] as $f)
			//	call function: generate ID
			if ($my_rec['fields'][$f]['type'] == 'identifier') {
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
		$field_name = (!empty($field['field_name']) ? $field['field_name'] : '');
	//	numbers
	//	factor for avoiding doubles
		if ($field['type'] == 'number' 
			AND isset($field['factor']) && $my_rec['POST'][$field_name]) {
			// we need factor and rounding in POST as well because otherwise
			// we won't be able to check against existing record
			$my_rec['POST'][$field_name] *= $field['factor'];
			$my_rec['POST'][$field_name] = round($my_rec['POST'][$field_name], 0);
			$my_rec['POST_db'][$field_name] = $my_rec['POST'][$field_name];
		}
	//	set
		if ($field['type'] == 'select' 
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
		if ($field['type'] == 'password')
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
			// dont' do anything with these
			break;
		case 'geo_point':
			$my_rec['POST_db'][$field_name] = 'GeomFromText("'
				.zz_db_escape($my_rec['POST_db'][$field_name]).'")';
			break;
		default:
			//	slashes, 0 and NULL
			if ($my_rec['POST_db'][$field_name]) {
				$my_rec['POST_db'][$field_name] 
					= '"'.zz_db_escape($my_rec['POST_db'][$field_name]).'"';
			} else {
				// empty values = NULL, treat some special cases differently
				// latitude/longitude: type string, different from 0
				if (isset($field['number_type']) AND ($my_rec['POST'][$field_name] !== '')
					AND in_array($field['number_type'], array('latitude', 'longitude')))
					$my_rec['POST_db'][$field_name] = '0';
				elseif (!empty($field['null'])) 
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
	if ($zz_tab[$tab][$rec]['action'] === 'ignore') return $ops;
	
	if (!isset($ops['record_new'])) $ops['record_new'] = array();
	if (!isset($ops['record_old'])) $ops['record_old'] = array();
	if (!isset($ops['record_diff'])) $ops['record_diff'] = array();
	
	$rn = array();
	$ro = array();
	
	// set index to make sure that main record is always 0
	if (!$tab AND !$rec) $index = 0;
	elseif (!isset($ops[$type])) $index = 1;
	else $index = count($ops[$type]);

	// set information on successful record operation
	$ops[$type][$index] = array(
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
	} else $ops['record_new'][$index] = array();
	
	// set old record
	if (!empty($zz_tab[$tab]['existing'][$rec])) {
		$ops['record_old'][$index] = $zz_tab[$tab]['existing'][$rec];
		$ro = $zz_tab[$tab]['existing'][$rec];
	} else
		$ops['record_old'][$index] = array();
	
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
	$ops['record_diff'][$index] = $rd;

	return $ops;
}

/** 
 * Create, move or delete folders which are connected to records
 * 
 * @param array $zz_tab complete zz_tab array
 * @global array $zz_conf
 * @global array $zz_error
 * @return array $folders => $zz_tab[0]['folder'][] will be set
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_foldercheck($zz_tab) {
	global $zz_conf;
	global $zz_error;
	$folders = array();
	foreach ($zz_conf['folder'] as $folder) {
		$path = zz_makepath($folder, $zz_tab, 'new', 'file');
		$old_path = zz_makepath($folder, $zz_tab, 'old', 'file');
		if ($old_path == $path) continue;
		if (!file_exists($old_path)) continue;
		if (!file_exists($path)) {
			$success = zz_create_topfolders(dirname($path));
			if ($success) {
				$success = rename($old_path, $path);
			}
			if ($success) {
				$folders[] = array('old' => $old_path, 'new' => $path);
			} else { 
				$zz_error[] = array(
					'msg_dev' => 'Folder cannot be renamed.'
				);
				zz_error();
			}
		} else {
			$zz_error[] = array(
				'msg_dev' => 'There is already a folder by that name.'
			);
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_validate($my_rec, $db_table, $table_name, $tab, $rec = 0, $zz_tab) {
	global $zz_conf;
	global $zz_error;

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	// in case validation fails, these values will be send back to user
	$my_rec['POST-notvalid'] = $my_rec['POST']; 
	$my_rec['validation'] = true;
	$my_rec['last_fields'] = array();
	$my_rec['extra'] = array();

	foreach ($my_rec['fields'] as $f => $field) {
	// 	shorthand
		if (isset($field['field_name'])) $field_name = $field['field_name'];
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
				$my_rec['fields'][$f] = $field = array_merge($field, $my_rec['fields'][$field['read_options']]['options'][$submitted_option]);
			}
		}
	//	set detail types for write_once-Fields
		if ($field['type'] == 'write_once' 
			AND empty($my_rec['record'][$field_name])) {
			if (!empty($field['type_detail']))
				$my_rec['fields'][$f]['type'] = $field['type'] = $field['type_detail'];
		}

		//	remove entries which are for display only
		if (!empty($field['display_only'])) {
			$my_rec['fields'][$f]['in_sql_query'] = false;
			$my_rec['fields'][$f]['class'] = 'hidden';
			continue;
		}

		if (!$tab AND !$rec) {
			//	copy value if field detail_value isset
			if (!empty($field['detail_value'])) {
				$value = zz_write_detail_values($zz_tab, $f);
				if ($value) $my_rec['POST'][$field_name] = $value;
			}
	
			// check if some values should be gotten from upload fields
			// here: only for main record, since subrecords already were taken care for
			if (!empty($field['upload_field'])) {
				$value = zz_write_upload_fields($zz_tab, $f);
				if ($value) $my_rec['POST'][$field_name] = $value;
			}
		}

		//	call function
		if (!empty($field['function'])) { // $field['type'] == 'hidden' AND 
			foreach ($field['fields'] as $var)
				if (strstr($var, '.')) {
					$vars = explode('.', $var);
					$func_vars[$var] = $my_rec['POST'][$vars[0]][0][$vars[1]];
				} else
					$func_vars[$var] = $my_rec['POST'][$var];
			$my_rec['POST'][$field_name] = $field['function']($func_vars, $field_name);
		}

		// per default, all fields are becoming part of SQL query
		$my_rec['fields'][$f]['in_sql_query'] = true;

		// get field type, hidden fields with sub_type will be validated against subtype
		$type = $field['type'];
		if ($field['type'] == 'hidden' AND !empty($field['sub_type'])) {
			$type = $field['sub_type'];
		}
		
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
		case 'number':
			//	calculation and choosing of right values in case of coordinates
			if (isset($field['number_type']) 
				AND in_array($field['number_type'], array('latitude', 'longitude'))) {
				// geographical coordinates
				
				$precision = zz_db_decimal_places($db_table, $field);
				$coord = zz_geo_coord_in($my_rec['POST'][$field_name], $field['number_type'], $precision);
				if ($coord['error']) {
					$zz_error['validation']['incorrect_values'][] = array(
						'field_name' => $field_name,
						'msg' => $coord['error']
					);
					$zz_error['validation']['log_post_data'] = true;
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

			//	encrypt passwords, only for changed passwords! therefore string 
			// 		is compared against old pwd
			// action=update: here, we have to check whether submitted password 
			// 		is equal to password in db
			// if so, password won't be touched
			// if not, password will be encrypted
			// action=insert: password will be encrypted
			if ($my_rec['action'] === 'insert') {
				$my_rec['POST']['zz_unencrypted_'.$field_name] = $my_rec['POST'][$field_name];
				$my_rec['POST'][$field_name] = zz_password_hash($my_rec['POST'][$field_name]);
			} elseif ($my_rec['action'] === 'update') {
				$my_rec['POST']['zz_unencrypted_'.$field_name] = $my_rec['POST'][$field_name];
				if (!isset($my_rec['POST'][$field_name.'--old'])
				|| ($my_rec['POST'][$field_name] != $my_rec['POST'][$field_name.'--old']))
					$my_rec['POST'][$field_name] = zz_password_hash($my_rec['POST'][$field_name]);
			}
			break;
		case 'password_change':
			//	change encrypted password
			$pwd = false;
			if ($my_rec['POST'][$field_name] 
				AND $my_rec['POST'][$field_name.'_new_1']
				AND $my_rec['POST'][$field_name.'_new_2']) {
				$my_sql = $field['sql_password_check'].$my_rec['id']['value'];
				$pwd = zz_password_set($my_rec['POST'][$field_name], 
					$my_rec['POST'][$field_name.'_new_1'], 
					$my_rec['POST'][$field_name.'_new_2'], $my_sql);
			} else {
				$zz_error[] = array(
					'msg' => 'Please enter your current password and twice your new password.',
					'level' => E_USER_NOTICE
				);
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
			if (!empty($zz_tab[$tab]['existing'][$rec][$field_name])
				AND $zz_tab[$tab]['existing'][$rec][$field_name] == $my_rec['POST'][$field_name]) {
				// record did not change, so we do not need to check the select value
				break;
			}
			$my_rec = zz_check_select($my_rec, $f, $zz_conf['max_select'], $table_name.'['.$rec.']['.$field_name.']', $db_table);
			//	check for correct enum values
			if (!$my_rec['POST'][$field_name]) break;
			if (isset($field['enum'])) {
				if (!$tempvar = zz_check_enumset($my_rec['POST'][$field_name], $field, $db_table)) {
					$my_rec['validation'] = false;
					$my_rec['fields'][$f]['check_validation'] = false;
				} else {
					$my_rec['POST'][$field_name] = $tempvar;
				}
			} elseif (isset($field['set'])) {
				if (is_array($my_rec['POST'][$field_name])
					AND count($my_rec['POST'][$field_name]) == 1
					AND (!$my_rec['POST'][$field_name][0]))
					$my_rec['POST'][$field_name] = false;
			}
			break;
		case 'date':
			//	internationalize date!
			if (!$my_rec['POST'][$field_name]) break;
			// submit to zz_check_date only if there is a value, else return 
			// would be false and validation true!
			if ($my_date = zz_check_date($my_rec['POST'][$field_name]))
				$my_rec['POST'][$field_name] = $my_date;
			else {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
			break;
		case 'time':
			//	validate time
			if (!$my_rec['POST'][$field_name]) break;
			if ($my_time = zz_check_time($my_rec['POST'][$field_name]))
				$my_rec['POST'][$field_name] = $my_time;
			else {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
			break;
		case 'unix_timestamp':
			//	convert unix_timestamp, if something was posted
			if (!$my_rec['POST'][$field_name]) break;
			$my_date = strtotime($my_rec['POST'][$field_name]); 
			if ($my_date AND $my_date != -1) 
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
					$zz_error['validation']['incorrect_values'][] = array(
						'field_name' => $field_name,
						'msg' => $error
					);
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
			//	remove entries which are for display only
			// 	or will be processed somewhere else
			$my_rec['fields'][$f]['in_sql_query'] = false;
			break;
		default:
			break;
		}

		// types 'identifier' and 'display_only' are out
		// check if $field['post_validation'] is set
		if (!empty($field['post_validation'])) {
			$values = $field['post_validation']($my_rec['POST'][$field_name]);
			if ($values == -1) {
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
			if ($field['type'] == 'foreign_key' 
				OR $field['type'] == 'translation_key'
				OR $field['type'] == 'detail_key') {
				// foreign key will always be empty but most likely also be 
				// required. f. key will be added by script later on (because 
				// sometimes it is not known yet)
				// do nothing, leave $my_rec['validation'] as it is
			} elseif ($field['type'] == 'timestamp') {
				// timestamps will be set to current date, so no check is 
				// necessary. do nothing, leave $my_rec['validation'] as it is.
			} elseif (!isset($field['set']) AND !isset($field['set_sql'])
				 AND !isset($field['set_folder'])) {
				$my_rec['validation'] = false;
			} elseif ($field['required']) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
			}
		} elseif(!$my_rec['POST'][$field_name] 
			AND empty($field['null'])
			AND $field['type'] != 'timestamp') {
			if ($field['required']) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
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
	}

	// finished
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
 * writes values from upload metadata to fields
 *
 * @param array $zz_tab
 * @param int $f
 * @param int $tab (optional)
 * @param int $rec (optional)
 * @global array $zz_error
 * @return string
 */
function zz_write_upload_fields($zz_tab, $f, $tab = 0, $rec = 0) {
	global $zz_error;
	
	$field = $zz_tab[$tab][$rec]['fields'][$f];
	$posted = $zz_tab[$tab][$rec]['POST'][$field['field_name']];
	$images = false;

	if (!strstr($field['upload_field'], '[')) {
		// file from main record
		// check if something was uploaded
		if (empty($zz_tab[$tab][$rec]['file_upload'])) return $posted;
		//	insert data from file upload/convert
		$images = $zz_tab[$tab][$rec]['images'][$field['upload_field']];
	} else {
		// file from detail record
		preg_match('~(\d+)\[(\d+)\]\[(\d+)\]~', $field['upload_field'], $nos);
		// check if definition is correct
		if (count($nos) != 4) {
			$zz_error[] = array(
				'msg_dev' => 'Error in $zz definition for upload_field: ['.$f.']',
				'level' => E_USER_NOTICE
			);
		} elseif (!empty($zz_tab[$nos[1]][$nos[2]]['images'][$nos[3]])) {
			// check if something was uploaded
			if (empty($zz_tab[$nos[1]][$nos[2]]['file_upload'])) return $posted;
			//	insert data from file upload/convert
			$images = $zz_tab[$nos[1]][$nos[2]]['images'][$nos[3]];
		}
	}
	// if there's no such value, return unchanged
	if (!$images) return $posted;
	
	//	insert data from file upload/convert
	return zz_val_get_from_upload($field, $images, $posted);
}

/**
 * if 'upload_field' is set, gets values for fields from this upload field
 * either plain values, exif values or even values with an SQL query from
 * the database
 *
 * @param array $field
 * @param array $images
 * @param array $post
 * @global array $zz_conf
 * @return array $post
 */
function zz_val_get_from_upload($field, $images, $post) {
	global $zz_conf;

	$possible_upload_fields = array('date', 'time', 'text', 'memo', 'hidden', 
		'number', 'select');
	if (!in_array($field['type'], $possible_upload_fields)) 
		return $post;
	// apart from hidden, set only values if no values have been set so far
	if ($field['type'] != 'hidden' AND !empty($post))
		return $post;

	$myval = false;
	$v_arr = false;
	$g = $field['upload_field'];
	$possible_values = $field['upload_value'];
	if (!is_array($possible_values)) $possible_values = array($possible_values);
	
	foreach ($possible_values AS $v) {
		switch ($v) {
		case 'md5':
			if (empty($images[0])) break;
			if (!empty($images[0]['modified']['tmp_name']))
				$myval = md5_file($images[0]['modified']['tmp_name']);
			elseif (!empty($images[0]['upload']['tmp_name']))
				$myval = md5_file($images[0]['upload']['tmp_name']);
			break;
		case 'md5_source_file':
			if (empty($images[0]['upload']['tmp_name'])) break;
			$myval = md5_file($images[0]['upload']['tmp_name']);
			break;
		case 'sha1':
			if (empty($images[0])) break;
			if (!empty($images[0]['modified']['tmp_name']))
				$myval = sha1_file($images[0]['modified']['tmp_name']);
			elseif (!empty($images[0]['upload']['tmp_name']))
				$myval = sha1_file($images[0]['upload']['tmp_name']);
			break;
		case 'sha1_source_file':
			if (empty($images[0]['upload']['tmp_name'])) break;
			$myval = sha1_file($images[0]['upload']['tmp_name']);
			break;
		default:
			if (preg_match('/.+\[.+\]/', $v)) { // construct access to array values
				$myv = explode('[', $v);
				if (!isset($images[0])) break;
				$myval_upload = (isset($images[0]['upload']) ? $images[0]['upload'] : '');
				$myval_altern = $images[0];
				foreach ($myv as $v_var) {
					if (substr($v_var, -1) == ']') $v_var = substr($v_var, 0, -1);
					if (substr($v_var, -1) == '*') {
						$v_var = substr($v_var, 0, -1); // get rid of asterisk
						$arrays = array($myval_upload, $myval_altern);
						$subkeys = array();
						foreach ($arrays as $array) {
							if (!$array) continue;
							foreach ($array as $key => $value) {
								if (substr($key, 0, strlen($v_var)) == $v_var) {
									$subkeys[$key] = $value;
								}
							}
							if ($subkeys) continue; // get from _upload, then from _altern
						}
						$myval_upload = $subkeys;
						$myval_altern = false;
					} else {
						if (isset($myval_upload[$v_var])) {
							$myval_upload = $myval_upload[$v_var];
						} else $myval_upload = false;
						if (isset($myval_altern[$v_var])) {
							$myval_altern = $myval_altern[$v_var];
						} else $myval_altern = false;
					}
				}
				$myval = ($myval_upload ? $myval_upload : $myval_altern);
			} elseif (!empty($images[$v])) {
				// take value from upload-array
				$myval = $images[$v];
			} elseif (!empty($images[0]['upload'][$v])) {
				// or take value from first sub-image
				$myval = $images[0]['upload'][$v];
			} else {
				$mval = '';
			}
			// remove empty values
			if ($myval === '0/0') $myval = false; // e. g. GPS bearing
			// we don't need whitespace (DateTime field may be set to "    ..."
			if (!is_array($myval)) $myval = trim($myval);
			if (!empty($field['upload_func'])) {
				$myval = $field['upload_func']($myval);
				if (is_array($myval)) $myval = $myval['value'];
			} elseif (is_array($myval)) {
				$myval = false;
			}
			if (!$myval AND !empty($field['upload_default']))
				$myval = $field['upload_default'];
			if ($myval && !empty($field['upload_sql'])) {
				$sql = $field['upload_sql'].'"'.$myval.'"';
				$myval = zz_db_fetch($sql, '', 'single value');
				if (!$myval) {
					$myval = ''; // string, not array
					if (!empty($field['upload_insert'])) {
						// ... script_name, values
					}
				}
			}
		}
		// go through this foreach until you have a value
		if ($myval) break;
	}
	if ($zz_conf['modules']['debug']) zz_debug(
		'uploadfield: '.$field['field_name'].' %'.$post.'%<br>'
		.'val: %'.$myval.'%'
	);

	if ($myval) return $myval;
	else return $post;
}

/**
 * validates input against a set of rules
 *
 * @param string $value value entered in form
 * @param array $validate defines against what to validate 
 * @return mixed false: everything is okay, string: error message
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_check_rules($value, $validate) {
	foreach ($validate as $type => $needles) {
		switch ($type) {
		case 'forbidden_strings':
			foreach ($needles as $needle) {
				if (stripos($value, $needle) !== false) // might be 0
					return sprintf(zz_text('String <em>"%s"</em> is not allowed'), htmlspecialchars($needle));
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
 * @global array $zz_error
 * @global array $zz_conf	Configuration variables, here: 'hash_password'
 * @return string false: an error occurred; string: new encrypted password 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_password_set($old, $new1, $new2, $sql) {
	global $zz_error;
	global $zz_conf;
	if ($new1 != $new2) {
		// new passwords do not match
		$zz_error[] = array(
			'msg' => 'New passwords do not match. Please try again.',
			'level' => E_USER_NOTICE
		);
		return false;
	}
	if ($old == $new1) {
		// old password eq new password - this is against identity theft if 
		// someone interferes a password mail
		$zz_error[] = array(
			'msg' => 'New and old password are identical. Please choose a different new password.',
			'level' => E_USER_NOTICE
		);
		return false; 
	}
	$old_hash = zz_db_fetch($sql, '', 'single value', __FUNCTION__);
	if (!$old_hash) return false;
	if (zz_password_check($old, $old_hash)) {
		// new1 = new2, old = old, everything is ok
		$hash = zz_password_hash($new1);
		if ($hash) {
			$zz_error[] = array(
				'msg' => 'Your password has been changed!',
				'level' => E_USER_NOTICE
			);
		} else {
			$zz_error[] = array(
				'msg' => 'Your new password could not be saved. Please try a different one.',
				'level' => E_USER_WARNING
			);
		}
		return $hash;
	} else {
		$zz_error[] = array(
			'msg' => 'Your current password is different from what you entered. Please try again.',
			'msg_dev' => '(Encryption: '.$zz_conf['hash_password'].', existing hash: '
				.$old_hash.', entered hash: '.zz_password_hash($old),
			'level' => E_USER_NOTICE
		);
		return false;
	}
}

/**
 * check given password against database password hash
 *
 * @param string $pass password as entered by user
 * @param string $hash hash as stored in database
 * @global array $zz_conf
 *		'hash_password', 'hash_script'
 * @return bool true: given credentials are correct, false: no access!
 * @see wrap_passsword_check()
 */
function zz_password_check($pass, $hash) {
	global $zz_conf;
	if (!empty($zz_conf['hash_script']))
		require_once $zz_conf['hash_script'];
	if (strlen($pass) > 72) return false;

	switch ($zz_conf['hash_password']) {
	case 'phpass':
		$hasher = new PasswordHash($zz_conf['hash_cost_log2'], $zz_conf['hash_portable']);
		if ($hasher->CheckPassword($pass, $hash)) return true;
		else return false;
	default:
		if ($hash === zz_password_hash($pass)) return true;
		return false;
	}
}

/**
 * hash password
 *
 * @param string $pass password as entered by user
 * @global array $zz_conf
 *		'hash_password', 'password_salt',
 *		'hash_script', 'hash_cost_log2', 'hash_portable'
 * @return string hash
 * @see wrap_passsword_hash()
 */
function zz_password_hash($pass) {
	global $zz_conf;
	if (!empty($zz_conf['hash_script']))
		require_once $zz_conf['hash_script'];
	if (strlen($pass) > 72) return false;

	switch ($zz_conf['hash_password']) {
	case 'phpass':
		$hasher = new PasswordHash($zz_conf['hash_cost_log2'], $zz_conf['hash_portable']);
		$hash = $hasher->HashPassword($pass);
		if (strlen($hash) < 20) return false;
		return $hash;
	default:
		if (!isset($zz_conf['password_salt'])) 
			$zz_conf['password_salt'] = '';
		return $zz_conf['hash_password']($pass.$zz_conf['password_salt']);
	}

	return $zz_conf['hash_password']($pass.$zz_conf['password_salt']);
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
	$relations = zz_db_fetch($sql, array('master_db', 'master_table', 'master_field', 'rel_id'));
	return $relations;
}

/**
 * Checks relational integrity upon a deletion request and says if its ok.
 *
 * @param array $deletable_ids
 * @param array $relations
 * @return mixed bool false: deletion of record possible, integrity will remain
 *		array: 'text' (error message), 'fields' (optional, names of tables
 *		which have a relation to the current record)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_integrity_check($deletable_ids, $relations) {
	if (!$relations) {
		global $zz_conf;
		$response['text'] = sprintf(zz_text('No records in relation table'), '<code>'
			.$zz_conf['relations_table'].'</code>');
		return $response;
	}

	$response = array();
	$response['fields'] = array();
	foreach ($deletable_ids as $master_db => $tables) {
		foreach ($tables as $master_table => $fields) {
			if (!isset($relations[$master_db][$master_table])) {
			//	no relations which have this table as master
			//	do not care about master_field because it has to be PRIMARY key anyways
			//	so only one field name possible
				continue;
			}
			$master_field = key($fields);
			$ids = array_shift($fields);
			foreach ($relations[$master_db][$master_table][$master_field] as $key => $field) {
				$sql = 'SELECT `'.$field['detail_id_field'].'`
					FROM `'.$field['detail_db'].'`.`'.$field['detail_table'].'`
					WHERE `'.$field['detail_field'].'` IN ('.implode(',', $ids).')';
				$detail_ids = zz_db_fetch($sql, $field['detail_id_field'], 'single value');
				if (!$detail_ids) continue;
				
				if (!empty($deletable_ids[$field['detail_db']][$field['detail_table']][$field['detail_id_field']])) {
					$deletable_detail_ids = $deletable_ids[$field['detail_db']][$field['detail_table']][$field['detail_id_field']];
					$remaining_ids = array_diff($detail_ids, $deletable_detail_ids);
				} else {
					$remaining_ids = $detail_ids;
				}
				if ($remaining_ids) {
					// there are still IDs which cannot be deleted
					// check which record they belong to
					// only get unique values
					$response['fields'][$field['detail_table']] = $field['detail_table'];
				}
			}
		}
	}
	if ($response['fields']) {
		$response['fields'] = array_values($response['fields']);
		// we still have detail records
		$response['text'] = zz_text('Detail records exist in the following tables:');
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
	if (!$relations) return array();

	$details = array();
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if (!$zz_tab[$tab][$rec]['id']['value']) continue;
			if ($zz_tab[$tab][$rec]['action'] !== 'delete') continue;
			if (empty($relations[$zz_tab[$tab]['db_name']])) continue;
			if (empty($relations[$zz_tab[$tab]['db_name']][$zz_tab[$tab]['table']])) continue;
			if (empty($relations[$zz_tab[$tab]['db_name']][$zz_tab[$tab]['table']][$zz_tab[$tab][$rec]['id']['field_name']])) continue;

			$my_relations = $relations[$zz_tab[$tab]['db_name']][$zz_tab[$tab]['table']][$zz_tab[$tab][$rec]['id']['field_name']];
			foreach ($my_relations as $rel) {
				// we care just about 'delete'-relations
				if ($rel['delete'] != 'delete') continue;
				if (is_array($zz_tab[$tab][$rec]['id']['value'])) {
					$sql = 'SELECT `'.$rel['detail_id_field'].'`
						FROM `'.$rel['detail_db'].'`.`'.$rel['detail_table'].'`
						WHERE `'.$rel['detail_field'].'` IN ("'
						.implode('","', $zz_tab[$tab][$rec]['id']['value']).'")';
				} else {
					$sql = 'SELECT `'.$rel['detail_id_field'].'`
						FROM `'.$rel['detail_db'].'`.`'.$rel['detail_table'].'`
						WHERE `'.$rel['detail_field'].'` = '.$zz_tab[$tab][$rec]['id']['value'];
				}
				$records = zz_db_fetch($sql, $rel['detail_id_field'], 'single value');
				if (!$records) continue;
				// check if detail records have other detail records
				// if no entry in relations table exists, make no changes
				if (empty($details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']]))
					$details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']] = array();
				$details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']] 
					= array_merge($records, $details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']]);
			}
		}
	}
	if (!$details) return array();
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
	$records = array();
	foreach ($zz_tab as $my_tab) {
		foreach ($my_tab as $rec => $my_rec) {
			if (!is_numeric($rec)) continue;
			if (!$my_rec['id']['value']) continue;
			if ($my_rec['action'] !== 'delete') continue;
			if (is_array($my_rec['id']['value'])) {
				if (!isset($records[$my_tab['db_name']][$my_tab['table']][$my_rec['id']['field_name']]))
					$records[$my_tab['db_name']][$my_tab['table']][$my_rec['id']['field_name']] = array();
				$records[$my_tab['db_name']][$my_tab['table']][$my_rec['id']['field_name']]
					= array_merge($records[$my_tab['db_name']][$my_tab['table']][$my_rec['id']['field_name']], $my_rec['id']['value']);
			} else {
				$records[$my_tab['db_name']][$my_tab['table']][$my_rec['id']['field_name']][]
					= $my_rec['id']['value'];
			}
		}
	}
	return $records;
}

?>