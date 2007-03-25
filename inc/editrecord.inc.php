<?php 
/*
	zzform Scripts

	scripts for action: update, delete, insert or review a record

	function zz_action
		- check whether fields are empty or not (default and auto values do not count)
		- if all are empty = for subtables it's action = delete
		- check if user input is valid
		- if action = delete, check if referential integrity will be kept
		- if everything is ok
			- perform additional actions before doing sql query
			- do sql query (queries, in case there are subtables)
			- perform additional actions after doing sql query
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2007

*/

function zz_action(&$zz_tab, $zz_conf, &$zz, &$validation, $upload_form, $subqueries) {
	global $text;
	global $zz_error;
	//	### Check for validity, do some operations ###
	foreach (array_keys($zz_tab) as $i)
		foreach (array_keys($zz_tab[$i]) as $k) {
			if (!isset($zz_tab[$i]['table_name'])) 
				$zz_tab[$i]['table_name'] = $zz_tab[$i]['table'];
			if (!empty($upload_form) && !$i && !$k) {// do only for zz_tab 0 0 etc. not zz_tab 0 sql, not for subtables
				zz_upload_get($zz_tab[$i][$k], $zz_tab[0][0]['action'], $zz_tab[0]['sql'], $zz_tab, $zz_tab[$i]['table']); // read upload image information, as required
				zz_upload_prepare($zz_tab, $zz_conf); // read upload image information, as required
			}
			if ($i && is_numeric($k)) {
				// only if $i and $k != 0, i. e. only for subtables!
				// assign $_POST to subtable array
				$zz_tab[$i][$k]['POST'] = $_POST[$zz['fields'][$zz_tab[$i]['no']]['table_name']][$k];
				// set action field in zz_tab-array, 
				$zz_tab[$i][$k] = zz_set_subrecord_action($zz_tab[$i][$k], $zz_tab, $i, $zz);
				if (!$zz_tab[$i][$k]) unset($zz_tab[$i][$k]);
			}
			if (isset($zz_tab[$i][$k]))
				if ($zz_tab[$i][$k]['action'] == 'insert' OR $zz_tab[$i][$k]['action'] == 'update') {
				// do something with the POST array before proceeding
					if (empty($zz_tab[$i][$k]['access']) 
						|| $zz_tab[$i][$k]['access'] != 'show' ) // don't validate record which only will be shown!!
						$zz_tab[$i][$k] = zz_validate($zz_tab[$i][$k], $zz_conf, $zz_tab[$i]['table'], $zz_tab[$i]['table_name']); 
				} elseif (is_numeric($k))
				//	Check referential integrity
					if (file_exists($zz_conf['dir'].'/inc/integrity.inc.php')) {
						include_once($zz_conf['dir'].'/inc/integrity.inc.php');
				//test
						$record_idfield = $zz_tab[$i][$k]['id']['field_name'];
						$detailrecords = '';
						if ($subqueries) foreach ($subqueries as $subkey) {
							$det_key = $zz['fields'][$subkey]['table'];
							if (!strstr('.', $det_key)) $det_key = $zz_conf['db_name'].'.'.$det_key;
							$detailrecords[$det_key]['table'] = $zz['fields'][$subkey]['table'];
							$detailrecords[$det_key]['sql'][] = $zz['fields'][$subkey]['sql']; // might be more than one detail record from the same table
						}
						if (!$zz_tab[$i][$k]['no-delete'] = check_integrity($zz_conf['db_name'], $zz_tab[$i]['table'], $record_idfield, $zz_tab[$i][$k]['POST'][$record_idfield], $zz_conf['relations_table'], $detailrecords))
						// todo: remove db_name maybe?
							$zz_tab[$i][$k]['validation'] = true;
						else {
							$zz_tab[$i][$k]['validation'] = false;
							$zz['no-delete'][] = $i.','.$k;
						}
					}
		}

	foreach ($zz_tab as $subtab) foreach (array_keys($subtab) as $subset)
		if (is_numeric($subset))
			if (!$subtab[$subset]['validation'])
				$validation = false;

	if ($validation) {
		if (isset($zz_conf['action']['before_'.$zz['action']])) // if any other action before insertion/update/delete is required
			include ($zz_conf['action_dir'].'/'.$zz_conf['action']['before_'.$zz['action']].'.inc.php'); 

		// put delete_ids into zz_tab-array
		if (isset($_POST['deleted']))
			foreach (array_keys($_POST['deleted']) as $del_tab) {
				foreach (array_keys($zz_tab) as $i)
					if ($i) if ($zz_tab[$i]['table_name'] == $del_tab) $tabindex = $i;
				foreach ($_POST['deleted'][$del_tab] as $idfield) {
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
				$me_sql = ' INSERT INTO '.$zz_tab[$i]['table'];
				$me_sql .= ' ('.$field_list.') VALUES ('.$field_values.')';
				
		// ### Update a record ###
	
			} elseif ($zz_tab[$i][$me]['action'] == 'update') {
				$update_values = '';
				foreach ($zz_tab[$i][$me]['fields'] as $field)
					if ($field['type'] != 'subtable' AND $field['type'] != 'id' && $field['in_sql_query']) {
						if ($update_values) $update_values.= ', ';
						$update_values.= $field['field_name'].' = '.$zz_tab[$i][$me]['POST'][$field['field_name']];
					}
				$me_sql = ' UPDATE '.$zz_tab[$i]['table'];
				$me_sql.= ' SET '.$update_values.' WHERE '.$zz_tab[$i][$me]['id']['field_name'].' = "'.$zz_tab[$i][$me]['id']['value'].'"';
			
		// ### Delete a record ###
	
			} elseif ($zz_tab[$i][$me]['action'] == 'delete') {
				$me_sql= ' DELETE FROM '.$zz_tab[$i]['table'];
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
		
		if ($zz_tab[0][0]['action'] == 'delete' && isset($detail_sql_edit)) { // if delete a record, first delete detail records so that in case of an error there are no orphans
			foreach (array_keys($detail_sql_edit) as $i)
				foreach (array_keys($detail_sql_edit[$i]) as $k) {
					$del_result = mysql_query($detail_sql_edit[$i][$k]);
					if ($del_result) {
						if ($zz_conf['logging']) zz_log_sql($detail_sql_edit[$i][$k], $zz_conf['user']); // Logs SQL Query
						unset($detail_sql_edit[$i][$k]);
					} else { // something went wrong, but why?
						$zz['formhead'] = false;
						$zz_error['msg']	.= 'Detail record could not be deleted';
						$zz_error['type']	.= 'mysql';
						$zz_error['query']	.= $detail_sql_edit[$i][$k];
						$zz_error['mysql']	.= mysql_error();
						return false; // get out of function, ignore rest (this should never happen, just if there are database errors etc.)
					}
				}
		}

		if ($zz_conf['debug']) {
			$zz['output'].= '<br>';
			$zz['output'].= 'Main ID value: '.$zz_tab[0][0]['id']['value'].'<br>';
			$zz['output'].= 'Main SQL query: '.$sql_edit.'<br>';
			if (isset($detail_sql_edit)) {
				$zz['output'].= 'Further SQL queries:<br>';
				foreach (array_keys($detail_sql_edit) as $i)
					foreach (array_keys($detail_sql_edit[$i]) as $k)
						$zz['output'].= 'zz_tab '.$i.' '.$k.': '.str_replace('[FOREIGN_KEY]', '"'.$zz_tab[0][0]['id']['value'].'"', $detail_sql_edit[$i][$k]).'<br>';
			}
		}

		$result = mysql_query($sql_edit);
		if ($result) {
			// todo: check for affected rows, problem: also check for affected subrecords how?
			// echo 'affected: '.mysql_affected_rows();
			if ($zz_tab[0][0]['action'] == 'insert') {
				$zz['formhead'] = $text['record_was_inserted'];
				$zz_tab[0][0]['id']['value'] = mysql_insert_id(); // for requery
			} elseif ($zz_tab[0][0]['action'] == 'update') $zz['formhead'] = $text['record_was_updated'];
			elseif ($zz_tab[0][0]['action'] == 'delete') $zz['formhead'] = $text['record_was_deleted'];
			if ($zz_conf['logging'] && $sql_edit != 'SELECT 1')
				zz_log_sql($sql_edit, $zz_conf['user']); // Logs SQL Query, must be after insert_id was checked
			$operation_success = true;
			if (isset($detail_sql_edit))
				foreach (array_keys($detail_sql_edit) as $i)
					foreach (array_keys($detail_sql_edit[$i]) as $k) {
						$detail_sql = $detail_sql_edit[$i][$k];
						$detail_sql = str_replace('[FOREIGN_KEY]', '"'.$zz_tab[0][0]['id']['value'].'"', $detail_sql);
						//if ($zz['action'] == 'insert') $detail_sql .= $zz_tab[0][0]['id']['value'].');';
						$detail_result = mysql_query($detail_sql);
						if (!$detail_result) { // This should never occur, since all checks say that this change is possible
							$zz['formhead']		= false;
							$zz_error['msg']	.= 'Detail record could not be handled';
							$zz_error['level']	.= 'crucial';
							$zz_error['type']	.= 'mysql';
							$zz_error['query']	.= $detail_sql;
							$zz_error['mysql']	.= mysql_error();
							$operation_success = false;
						} elseif ($zz_tab[$i][$k]['action'] == 'insert') 
							$zz_tab[$i][$k]['id']['value'] = mysql_insert_id(); // for requery
						if ($zz_conf['logging']) zz_log_sql($detail_sql, $zz_conf['user']); // Logs SQL Query
					}
			if (isset($zz_conf['action']['after_'.$zz['action']])) 
				include ($zz_conf['action_dir'].'/'.$zz_conf['action']['after_'.$zz['action']].'.inc.php'); 
				// if any other action after insertion/update/delete is required
			if (!empty($upload_form))
				zz_upload_action($zz_tab, $zz_conf); // upload images, delete images, as required
			if ($operation_success) $zz['result'] = 'successful_'.$zz_tab[0][0]['action'];
		} else {
			// Output Error Message
			$zz['formhead'] = false;
			if ($zz['action'] == 'insert') $zz_tab[0][0]['id']['value'] = false; // for requery
			$zz_error['msg']	.= ' ';
			$zz_error['level']	.= 'crucial';
			$zz_error['type']	.= 'mysql';
			$zz_error['query']	.= $sql_edit;
			$zz_error['mysql']	.= mysql_error();
			$validation = false; // show record again!
		}
	}
	if (!empty($upload_form)) zz_upload_cleanup($zz_tab); // delete temporary unused files
}

function zz_set_subrecord_action($subtable, &$zz_tab, $i, &$zz) {
/*	defines which action will be performed on subrecord:
	1.	action = insert | update | delete
		update if id field is not empty and there are values in fields
		insert if there is no id field and there are values in fields
		delete if there is nothing written in field, default- or value-fields
			might be ignored with def_val_ingore
	2.	in case of action delete check whether subrecord is in database
	2a.	if yes: mark for deletion
	2b.	if no: just remove it from the cache
	3.	in case main record shall be deleted, do the same as in 2a and 2b.

	values affected by this function:
		$zz_tab[$i][$k]['action']
		$zz_tab[$i]['deleted']
		may unset($zz_tab[$i][$k])

*/
	// initialize variables
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
			} elseif ($field['type'] != 'timestamp' && $field['type'] != 'id')
				// 	old: !(!empty($field['default']) && $field['default'] == $subtable['POST'][$field['field_name']]) // default values will be ignored
				if (empty($field['def_val_ignore'])) // some auto values/values/default values will be ignored 
					$values .= $subtable['POST'][$field['field_name']];
		if ($field['type'] == 'id')
			if (!isset($subtable['POST'][$field['field_name']]))
				$subtable['action'] = 'insert';
			else
				$subtable['action'] = 'update';
	}
	// todo: seems to be twice the same operation since $i and $k are !0
	if (!empty($zz['fields'][$zz_tab[$i]['no']]['access'])
		&& $zz['fields'][$zz_tab[$i]['no']]['access'] == 'show') {
		$values = true; // only display subrecords, no deletion, no change!
		$subtable['action'] = false; // no action insert or update, values are only shown!
	}
	if (!$values)
		if ($subtable['id']['value']) {
			$subtable['action'] = 'delete';
			$zz_tab[$i]['deleted'][] = $subtable['id']['value']; // only for requery record on error!
		} else
			$subtable = false;
	if ($zz_tab[0][0]['action'] == 'delete') 
		if ($subtable['id']['value'])
			$subtable['action'] = 'delete';
		else
			$subtable = false;
	return $subtable;
}

?>