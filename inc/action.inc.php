<?php 

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2004-2010
// scripts for action: update, delete, insert or review a record


/*
	function zz_action
		- check whether fields are empty or not (default and auto values do not count)
		- if all are empty = for subtables it's action = delete
		- check if user input is valid
		- if action = delete, check if referential integrity will be kept
		- if everything is ok
			- perform additional actions before doing sql query
			- do sql query (queries, in case there are subtables)
			- perform additional actions after doing sql query
	
	custom functions called: 
		- zz_upload_get()
		- zz_upload_prepare()
		- zz_set_subrecord_action()
		- zz_validate()
		- zz_prepare_for_db()
		- zz_check_integrity()
		- zz_foldercheck()
		- zz_upload_cleanup()
		
	common functions
		- zz_log_sql()
		- zz_text()
*/

function zz_action(&$zz_tab, $zz_conf, &$zz, &$validation, $upload_form) {
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	global $zz_error;
	$operation_success = false;
	
	//	### Check for validity, do some operations ###
	if (!empty($upload_form)) { // do only for zz_tab 0 0 etc. not zz_tab 0 sql etc.
		zz_upload_get($zz_tab); // read upload image information, as required
		zz_upload_prepare($zz_tab, $zz_conf); // read upload image information, as required
	}
	if ($zz_error['error']) return zz_return(false);

	foreach (array_keys($zz_tab) as $i) {
		if (!isset($zz_tab[$i]['table_name'])) 
			$zz_tab[$i]['table_name'] = $zz_tab[$i]['table'];		
		foreach (array_keys($zz_tab[$i]) as $k) {
			if (!is_numeric($k)) continue;
			if ($i) {
				// only if $i and $k != 0, i. e. only for subtables!
				// assign $_POST to subtable array
				$zz_tab[$i][$k]['POST'] = $_POST[$zz['fields'][$zz_tab[$i]['no']]['table_name']][$k];
				// set action field in zz_tab-array, 
				$zz_tab[$i][$k] = zz_set_subrecord_action($zz_tab, $i, $k, $zz);
				if (!$zz_tab[$i][$k]) {
					unset($zz_tab[$i][$k]); // empty subtable, not needed
					continue;
				} else {
					// we don't need POST array anymore, just the ones for the empty subtables later
					// could do it differently as well, just don't walk through POST there ...
					// but that's more difficult since zz_requery_record is called twice 
					// if db operation was unsuccessful
					unset($_POST[$zz['fields'][$zz_tab[$i]['no']]['table_name']][$k]);
				}
			}
			if ($zz_tab[$i][$k]['action'] == 'insert' 
				OR $zz_tab[$i][$k]['action'] == 'update')
			{
				// don't validate record which only will be shown!!
				if (!empty($zz_tab[$i][$k]['access']) 
					AND $zz_tab[$i][$k]['access'] == 'show' ) continue;
			
				// first part of validation where field values are independent
				// from other field values
				$zz_tab[$i][$k] = zz_validate($zz_tab[$i][$k], $zz_tab[$i]['db_name']
					.'.'.$zz_tab[$i]['table'], $zz_tab[$i]['table_name'], $k); 
				if ($i) {
					// write changed POST values back to main POST array
					// todo: let the next functions access the main POST array differently
					$zz_tab[0][0]['POST'][$zz['fields'][$zz_tab[$i]['no']]['table_name']][$k] = $zz_tab[$i][$k]['POST'];
				}
			} elseif (is_numeric($k)) {
			//	Check referential integrity
				if (!file_exists($zz_conf['dir_inc'].'/integrity.inc.php')) continue;
				include_once $zz_conf['dir_inc'].'/integrity.inc.php';
				$record_idfield = $zz_tab[$i][$k]['id']['field_name'];
				$detailrecords = array();
				if ($zz['subqueries']) foreach ($zz['subqueries'] as $subkey) {
					$det_key = $zz['fields'][$subkey]['table'];
					if (!strstr('.', $det_key)) $det_key = $zz_tab[0]['db_name'].'.'.$det_key;
					$detailrecords[$det_key]['table'] = $zz['fields'][$subkey]['table'];
					// might be more than one detail record from the same table
					$detailrecords[$det_key]['sql'][] = $zz['fields'][$subkey]['sql'];
				}
				if (!$zz_tab[$i][$k]['no-delete'] = zz_check_integrity($zz_tab[$i]['db_name'], 
						$zz_tab[$i]['table'], $record_idfield, $zz_tab[$i][$k]['POST'][$record_idfield], 
						$zz_conf['relations_table'], $detailrecords)) {
					if ($zz_error['error']) return zz_return(false);
					$zz_tab[$i][$k]['validation'] = true;
				} else {
					$zz_tab[$i][$k]['validation'] = false;
					$zz['no-delete'][] = $i.','.$k;
				}
				if ($zz_error['error']) return zz_return(false);
			}
		}
	}

	foreach ($zz_tab as $subtab) foreach (array_keys($subtab) as $subset) {
		if (is_numeric($subset) AND !$subtab[$subset]['validation']) {
			$validation = false;
			if (!empty($upload_form)) zz_upload_cleanup($zz_tab); // delete temporary unused files
			return zz_return(false);
		}
	}

	if ($zz_conf['modules']['debug']) zz_debug("validation successful");

	foreach (array_keys($zz_tab) as $i) {
		foreach (array_keys($zz_tab[$i]) as $k) {
			if (!is_numeric($k)) continue;
			if ($zz_tab[$i][$k]['action'] != 'insert' 
				AND $zz_tab[$i][$k]['action'] != 'update') continue;
			// do something with the POST array before proceeding
			if (!empty($zz_tab[$i][$k]['access']) 
				AND $zz_tab[$i][$k]['access'] == 'show' ) continue;
			$zz_tab[$i][$k] = zz_prepare_for_db($zz_tab[$i][$k], $zz_tab[$i]['db_name']
				.'.'.$zz_tab[$i]['table'], $zz_tab[0][0]['POST']); 
		}
	}
	
	// if any other action before insertion/update/delete is required
	if (!empty($zz_conf['action']['before_'.$zz['action']])) 
		include $zz_conf['action_dir'].'/'.$zz_conf['action']['before_'.$zz['action']].'.inc.php';

	// put delete_ids into zz_tab-array
	if (isset($_POST['zz_subtable_deleted']))
		foreach (array_keys($_POST['zz_subtable_deleted']) as $del_tab) {
			foreach (array_keys($zz_tab) as $i)
				if ($i) if ($zz_tab[$i]['table_name'] == $del_tab) $tabindex = $i;
			foreach ($_POST['zz_subtable_deleted'][$del_tab] as $idfield) {
				$my['action'] = 'delete';
				$my['id']['field_name'] = key($idfield);
				$my['POST'][$my['id']['field_name']] = $idfield[$my['id']['field_name']];
				$zz_tab[$tabindex][] = $my;
			}
		}
	$sql_edit = '';
	foreach (array_keys($zz_tab) as $i)
		foreach (array_keys($zz_tab[$i]) as $me) if (is_numeric($me)) {
			//echo 'rec '.$i.' '.$me.'<br>';
		
		// get database name for query
		$me_db = false;
		if ($zz_conf['db_main']) {
			// the 'main' zzform() database is different from the database for the main
			// record, so check against db_main
			if ($zz_tab[$i]['db_name'] != $zz_conf['db_main']) $me_db = $zz_tab[$i]['db_name'].'.';
		} else {
			// the 'main' zzform() database is equal to the database for the main
			// record, so check against db_name
			if ($zz_tab[$i]['db_name'] != $zz_conf['db_name']) $me_db = $zz_tab[$i]['db_name'].'.';
		}
		$me_sql = false;
		
	//	### Do nothing with the record, here: main record ###

		if (!empty($zz_tab[$i][$me]['access']) AND $zz_tab[$i][$me]['access'] == 'show') {
			$me_sql = 'SELECT 1';
		
	//	### Insert a record ###
	
		} elseif ($zz_tab[$i][$me]['action'] == 'insert') {
			$field_values = '';
			$field_list = '';
			foreach ($zz_tab[$i][$me]['fields'] as $field)
				if ($field['in_sql_query']) {
					if ($field_list) $field_list .= ', ';
					$field_list .= $field['field_name'];
					if ($field_values && $field['type']) $field_values.= ', ';
					//if ($me == 0 OR $field['type'] != 'foreign_key')
						$field_values .= $zz_tab[$i][$me]['POST'][$field['field_name']];
				}
			$me_sql = ' INSERT INTO '.$me_db.$zz_tab[$i]['table'];
			$me_sql .= ' ('.$field_list.') VALUES ('.$field_values.')';
			
	// ### Update a record ###

		} elseif ($zz_tab[$i][$me]['action'] == 'update') {
			$update_values = '';
			foreach ($zz_tab[$i][$me]['fields'] as $field)
				if ($field['type'] != 'subtable' AND $field['type'] != 'id' && $field['in_sql_query']) {
					if ($update_values) $update_values.= ', ';
					$update_values.= $field['field_name'].' = '.$zz_tab[$i][$me]['POST'][$field['field_name']];
				}
			if ($update_values) {
				$me_sql = ' UPDATE '.$me_db.$zz_tab[$i]['table']
					.' SET '.$update_values.' WHERE '.$zz_tab[$i][$me]['id']['field_name']
					.' = "'.$zz_tab[$i][$me]['id']['value'].'"';
			} else {
				$me_sql = 'SELECT 1'; // nothing to update, just detail records
			}
	// ### Delete a record ###

		} elseif ($zz_tab[$i][$me]['action'] == 'delete') {
			$me_sql= ' DELETE FROM '.$me_db.$zz_tab[$i]['table'];
			$id_field = $zz_tab[$i][$me]['id']['field_name'];
			$me_sql.= ' WHERE '.$id_field." = '".$zz_tab[$i][$me]['POST'][$id_field]."'";
			$me_sql.= ' LIMIT 1';

	// ### Again, do nothing with the record, here: detail record

		} elseif ($zz_tab[$i][$me]['action'] == false) {
			continue;
		}

		if (!$sql_edit) $sql_edit = $me_sql;
		else 			$detail_sql_edit[$i][$me] = $me_sql;
	}
	// ### Do mysql-query and additional actions ###
	
	if ($zz_tab[0][0]['action'] == 'delete' && isset($detail_sql_edit)) { 
	// if delete a record, first delete detail records so that in case of an 
	// error there are no orphans
		foreach (array_keys($detail_sql_edit) as $i)
			foreach (array_keys($detail_sql_edit[$i]) as $k) {
				$del_result = mysql_query($detail_sql_edit[$i][$k]);
				if ($del_result) {
					if ($zz_conf['logging']) 
						zz_log_sql($detail_sql_edit[$i][$k], $zz_conf['user'], $zz_tab[$i][$k]['id']['value']); // Logs SQL Query
					unset($detail_sql_edit[$i][$k]);
				} else { // something went wrong, but why?
					$zz['formhead'] = false;
					$zz_error[] = array(
						'msg' => 'Detail record could not be deleted',
						'query' => $detail_sql_edit[$i][$k],
						'mysql' =>	mysql_error());
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
		if (isset($detail_sql_edit)) {
			$zz['output'].= 'Further SQL queries:<br>';
			foreach (array_keys($detail_sql_edit) as $i)
				foreach (array_keys($detail_sql_edit[$i]) as $k)
					$zz['output'].= 'zz_tab '.$i.' '.$k.': '.str_replace('[FOREIGN_KEY]', '"'
						.$zz_tab[0][0]['id']['value'].'"', $detail_sql_edit[$i][$k]).'<br>';
		}
	}

	$result = mysql_query($sql_edit);
	if ($result) {
		// todo: check for affected rows, problem: also check for affected subrecords how?
		// echo 'affected: '.mysql_affected_rows();
		if ($zz_tab[0][0]['action'] == 'insert') {
			$zz['formhead'] = zz_text('record_was_inserted');
			$zz_tab[0][0]['id']['value'] = mysql_insert_id(); // for requery
		} elseif ($zz_tab[0][0]['action'] == 'update') $zz['formhead'] = zz_text('record_was_updated');
		elseif ($zz_tab[0][0]['action'] == 'delete') $zz['formhead'] = zz_text('record_was_deleted');
		if ($zz_conf['logging'] && $sql_edit != 'SELECT 1')
			// Logs SQL Query, must be after insert_id was checked
			zz_log_sql($sql_edit, $zz_conf['user'], $zz_tab[0][0]['id']['value']);
		if ($sql_edit != 'SELECT 1')
			$zz['return'][] = array(
				'table' => $zz_tab[0]['table'],
				'id_field_name' => $zz_tab[0][0]['id']['field_name'], 
				'id_value' => $zz_tab[0][0]['id']['value'],
				'action' => $zz_tab[0][0]['action']
			);
		$operation_success = true;
		if (isset($detail_sql_edit))
			foreach (array_keys($detail_sql_edit) as $i)
				foreach (array_keys($detail_sql_edit[$i]) as $k) {
					$detail_sql = $detail_sql_edit[$i][$k];
					$detail_sql = str_replace('[FOREIGN_KEY]', '"'.$zz_tab[0][0]['id']['value'].'"', $detail_sql);
					if (!empty($zz_tab[$i]['detail_key'])) {
						// TODO: allow further detail keys
						// if not all files where uploaded, go up one detail record until
						// we got an uploaded file
						while (empty($zz_tab[$zz_tab[$i]['detail_key'][0]['i']][$zz_tab[$i]['detail_key'][0]['k']]['id']['value'])) {
							$zz_tab[$i]['detail_key'][0]['k']--;
						}
						$detail_sql = str_replace('[DETAIL_KEY]', '"'.$zz_tab[$zz_tab[$i]['detail_key'][0]['i']][$zz_tab[$i]['detail_key'][0]['k']]['id']['value'].'"', $detail_sql);
					}
					$detail_result = mysql_query($detail_sql);
					if (!$detail_result) { 
						// This should never occur, since all checks say that 
						// this change is possible
						// only if duplicate entry
						$zz['formhead']		= false;
						$zz_error[] = array('msg' => 'Detail record could not be handled',
							'level' => E_USER_WARNING,
							'query' => $detail_sql,
							'mysql' => mysql_error(),
							'mysql_errno' => mysql_errno());
						$operation_success = false;
						$validation = false; 
						$zz_tab[0][0]['fields'][$zz_tab[$i]['no']]['check_validation'] = false;
					} elseif ($zz_tab[$i][$k]['action'] == 'insert') 
						$zz_tab[$i][$k]['id']['value'] = mysql_insert_id(); // for requery
					if ($zz_conf['logging'] AND $detail_result) {
						// for deleted subtables, id value might not be set, so get it here.
						// TODO: check why it's not available beforehands, might be unneccessary security risk.
						if (empty($zz_tab[$i][$k]['id']['value']))
							$zz_tab[$i][$k]['id']['value'] = $zz_tab[$i][$k]['POST'][$zz_tab[$i][$k]['id']['field_name']];
						zz_log_sql($detail_sql, $zz_conf['user'], $zz_tab[$i][$k]['id']['value']); // Logs SQL Query
					}
					$zz['return'][] = array(
						'table' => $zz_tab[$i]['table'],
						'id_field_name' => $zz_tab[$i][$k]['id']['field_name'], 
						'id_value' => $zz_tab[$i][$k]['id']['value'],
						'action' => $zz_tab[$i][$k]['action']
					);
				}
		if (!empty($zz_conf['action']['after_'.$zz['action']])) 
			include $zz_conf['action_dir'].'/'.$zz_conf['action']['after_'.$zz['action']].'.inc.php'; 
			// if any other action after insertion/update/delete is required
		if (!empty($zz_conf['folder']) && $zz_tab[0][0]['action'] == 'update')
			// rename connected folder after record has been updated
			zz_foldercheck($zz_tab, $zz_conf);
		if (!empty($upload_form)) {
			zz_upload_action($zz_tab, $zz_conf); // upload images, delete images, as required
			if ($zz_error['error']) return zz_return(false);
		}
		if ($operation_success) $zz['result'] = 'successful_'.$zz_tab[0][0]['action'];
	} else {
		// Output Error Message
		$zz['formhead'] = false;
		if ($zz['action'] == 'insert') $zz_tab[0][0]['id']['value'] = false; // for requery
		$zz_error[] = array(
			'level' => E_USER_WARNING,
			'query' => $sql_edit,
			'mysql' => mysql_error(),
			'mysql_errno' => mysql_errno());
		$validation = false; // show record again!
	}

	if (!empty($upload_form)) zz_upload_cleanup($zz_tab); // delete temporary unused files
	return zz_return($operation_success);
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
 * 		values affected by this function:
 *		$zz_tab[$i][$k]['action']
 *		$zz_tab[$i]['zz_subtable_deleted']
 *		may unset($zz_tab[$i][$k])
 * @param int $i
 * @param int $k
 * @param array $zz
 * @global array $zz_conf
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_set_subrecord_action(&$zz_tab, $i, $k, $zz) {
	// initialize variables
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$subtable = $zz_tab[$i][$k];
	$values = '';

	// check whether there are values in fields
	// this is done to see what to do with subrecord (insert, update, delete)
	foreach ($subtable['fields'] as $field) {
		if (isset($subtable['POST'][$field['field_name']]))
			if ($field['type'] == 'number' && isset($field['number_type']) 
				&& ($field['number_type'] == 'latitude' OR $field['number_type'] == 'longitude')) {
				// coordinates:
				// rather problematic stuff because this input is divided into several fields
				$t_coord = $subtable['POST'][$field['field_name']];
				if (isset($t_coord['lat_dms']) OR isset($t_coord['lat_dm']))
					$t_sub = 'lat_'.$t_coord['which'];
				else
					$t_sub = 'lon_'.$t_coord['which'];
				switch ($t_coord['which']) {
					case 'dms':
						$values .= $t_coord[$t_sub]['sec']; // seconds only in dms
					case 'dm':
						$values .= $t_coord[$t_sub]['deg']; // degrees and minutes in dm and dms
						$values .= $t_coord[$t_sub]['min'];
					break;
					default:
						$values .= $t_coord['dec']; // dd will be default
				}
			} elseif ($field['type'] != 'timestamp' && $field['type'] != 'id') {
				// check def_val_ignore, some auto values/values/default values will be ignored 
				if (!empty($field['def_val_ignore'])) {
					if (empty($field['value']) AND !empty($field['default'])
						AND $field['default'] != $subtable['POST'][$field['field_name']]) {
					// defaults will only be ignored if different from default value
					// but only if no value is set!
						$values .= $subtable['POST'][$field['field_name']];				
					} else {
					// values need not to be checked, they'll always be ignored
					// (and it's not easy to check them because they might be in a 'values'-array)
						$values .= '';
					}
				} else {
					$values .= $subtable['POST'][$field['field_name']];
				}
			}
		if ($field['type'] == 'id') {
			if (($zz_tab[$i]['tick_to_save'] AND !empty($_POST['zz_save_record'][$i][$k]))
				OR empty($zz_tab[$i]['tick_to_save'])) {
				if (!isset($subtable['POST'][$field['field_name']]))
					$subtable['action'] = 'insert';
				else
					$subtable['action'] = 'update';
			} elseif ($zz_tab[$i]['tick_to_save'] AND empty($_POST['zz_save_record'][$i][$k])) {
				if (!isset($subtable['POST'][$field['field_name']]))
					$subtable['action'] = 'ignore'; // ignore subrecord
				else
					$subtable['action'] = 'delete';
			}
		}
	}

	if (!empty($zz_tab[$i]['records_depend_on_upload']) AND !empty($subtable['no_file_upload'])) {
		$values = false;
	} elseif (!empty($zz_tab[$i]['records_depend_on_upload']) AND $subtable['action'] == 'insert'
		AND empty($subtable['file_upload'])) {
		$values = false;
	} elseif (!empty($zz_tab[$i]['records_depend_on_upload_more_than_one']) AND $subtable['action'] == 'insert'
		AND empty($subtable['file_upload']) AND $k) {
		$values = false;
	}
	// todo: seems to be twice the same operation since $i and $k are !0
	if (!empty($zz['fields'][$zz_tab[$i]['no']]['access'])
		&& $zz['fields'][$zz_tab[$i]['no']]['access'] == 'show') {
		$values = true; // only display subrecords, no deletion, no change!
		$subtable['action'] = false; // no action insert or update, values are only shown!
	}
	if (!$values) {
		if ($subtable['id']['value']) {
			$subtable['action'] = 'delete';
			$zz_tab[$i]['subtable_deleted'][] = $subtable['id']['value']; // only for requery record on error!
		} else {
			$subtable = false;
		}
	}
	
	if ($zz_tab[0][0]['action'] == 'delete') {
		if ($subtable['id']['value'] 			// is there a record?
			&& empty($zz['fields'][$zz_tab[$i]['no']]['keep_detailrecord_shown']))		
			$subtable['action'] = 'delete';
		else									// no data in subtable
			$subtable = false;
	}
	if ($subtable['action'] == 'ignore') unset($subtable);

	if ($zz_conf['modules']['debug']) zz_debug("end, values: ".substr($values, 0, 20));
	return $subtable;
}

/**
 * Preparation of record for database
 *
 * checks last fields which rely on fields beforehand; field type set; sets
 * slashes; NULL and 0; foreign key, translation key, detail key and timestamp
 * @param array $my = $zz_tab[$i][$k]
 * @param string $db_table [db_name.table]
 * @param array $main_post		POST values of $zz_tab[0][0]['POST']
 * @global array $zz_conf
 * @return array $my with validated values and marker if validation was successful 
 * 		($my['validation'])
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_prepare_for_db($my, $db_table, $main_post) {
	global $zz_conf;
	global $zz_error;

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	if ($my['last_fields']) { 
	// these fields have to be handled after others because they might get data 
	// from other fields (e. g. upload_fields)
		foreach ($my['last_fields'] as $f)
			//	call function: generate ID
			if ($my['fields'][$f]['type'] == 'identifier') {
				$func_vars = zz_get_identifier_vars($my, $f, $main_post);
				$conf = (!empty($my['fields'][$f]['conf_identifier']) 
					? $my['fields'][$f]['conf_identifier'] : false);
				$my['POST'][$my['fields'][$f]['field_name']] 
					= zz_create_identifier($func_vars, $conf, $my, $db_table, $f);
			}
	}
	unset($my['last_fields']);
	
	foreach (array_keys($my['fields']) as $f) {
	//	set
		if ($my['fields'][$f]['type'] == 'select' 
			AND (isset($my['fields'][$f]['set']) OR isset($my['fields'][$f]['set_sql']))) {
			if (!empty($my['POST'][$my['fields'][$f]['field_name']]))
				$my['POST'][$my['fields'][$f]['field_name']] = implode(',', $my['POST'][$my['fields'][$f]['field_name']]);
			else
				$my['POST'][$my['fields'][$f]['field_name']] = '';
		}
	//	slashes, 0 and NULL
		$unwanted = array('calculated', 'image', 'upload_image', 'id', 
			'foreign', 'subtable', 'foreign_key', 'translation_key', 
			'detail_key', 'display', 'option', 'write_once');
		if (!in_array($my['fields'][$f]['type'], $unwanted)) {
			if ($my['POST'][$my['fields'][$f]['field_name']]) {
				//if (get_magic_quotes_gpc()) // sometimes unwanted standard config
				//	$my['POST'][$my['fields'][$f]['field_name']] = stripslashes($my['POST'][$my['fields'][$f]['field_name']]);
				if (function_exists('mysql_real_escape_string')) // just from 4.3.0 on
					$my['POST'][$my['fields'][$f]['field_name']] = '"'
						.mysql_real_escape_string($my['POST'][$my['fields'][$f]['field_name']]).'"';
				else
					$my['POST'][$my['fields'][$f]['field_name']] = '"'
						.addslashes($my['POST'][$my['fields'][$f]['field_name']]).'"';
			} else {
				if (isset($my['fields'][$f]['number_type']) AND ($my['POST'][$my['fields'][$f]['field_name']] !== '') // type string, different from 0
					AND $my['fields'][$f]['number_type'] == 'latitude' 
					|| $my['fields'][$f]['number_type'] == 'longitude')
					$my['POST'][$my['fields'][$f]['field_name']] = '0';
				elseif (!empty($my['fields'][$f]['null'])) 
					$my['POST'][$my['fields'][$f]['field_name']] = '0';
				elseif (!empty($my['fields'][$f]['null-string'])) 
					$my['POST'][$my['fields'][$f]['field_name']] = '""';
				else 
					$my['POST'][$my['fields'][$f]['field_name']] = 'NULL';
			}
		}
	// foreign_key
		if ($my['fields'][$f]['type'] == 'foreign_key') 
			$my['POST'][$my['fields'][$f]['field_name']] = '[FOREIGN_KEY]';
	// detail_key
		if ($my['fields'][$f]['type'] == 'detail_key') 
			$my['POST'][$my['fields'][$f]['field_name']] = '[DETAIL_KEY]';
	// translation_key
		if ($my['fields'][$f]['type'] == 'translation_key') 
			$my['POST'][$my['fields'][$f]['field_name']] = $my['fields'][$f]['translation_key'];
	// timestamp
		if ($my['fields'][$f]['type'] == 'timestamp') 
			$my['POST'][$my['fields'][$f]['field_name']] = 'NOW()';
	}

	return zz_return($my);
}

?>