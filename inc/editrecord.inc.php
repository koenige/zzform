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
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2006

*/

function zz_action(&$zz_tab, $zz_conf, &$zz, &$validation, $upload_form, &$no_delete_reason) {
	global $text;
	//	### Check for validity, do some operations ###
	foreach (array_keys($zz_tab) as $i)
		foreach (array_keys($zz_tab[$i]) as $k) {
			if (!empty($upload_form) && $zz_tab[0][0]['action'] != 'delete' && is_numeric($k)) // do only for zz_tab 0 0 etc. not zz_tab 0 sql
				$zz_tab[$i][$k]['images'] = zz_get_upload($zz_tab[$i][$k]); // read upload image information, as required
			if ($i && is_numeric($k)) {
			// only if $i and $k != 0, i. e. only for subtables!
				$zz_tab[$i][$k]['POST'] = $_POST[$zz['fields'][$zz_tab[$i]['no']]['table_name']][$k];
				$values = '';
				// check whether there are values in fields
				foreach ($zz_tab[$i][$k]['fields'] as $field) {
					if (isset($zz_tab[$i][$k]['POST'][$field['field_name']]))
						if ($field['type'] == 'number' && isset($field['number_type']) && ($field['number_type'] == 'latitude' OR $field['number_type'] == 'longitude')) {
							// coordinates:
							// rather problematic stuff because this input is divided into several fields
							$t_coord = $zz_tab[$i][$k]['POST'][$field['field_name']];
							if ($t_coord['which'] == 'dms') {
								if (isset($t_coord['lat'])) $t_sub = 'lat';
							else $t_sub = 'lon';
								$values .= $t_coord[$t_sub]['deg'];
								$values .= $t_coord[$t_sub]['min'];
								$values .= $t_coord[$t_sub]['sec'];
							} else 
								$values .= $t_coord['dec'];
						} elseif ($field['type'] != 'timestamp' && $field['type'] != 'id')
							if (!(isset($field['default']) && $field['default'] && $field['default'] == $zz_tab[$i][$k]['POST'][$field['field_name']]) // default values will be ignored
								AND !isset($field['auto_value'])) // auto values will be ignored 
								$values .= $zz_tab[$i][$k]['POST'][$field['field_name']];
					if ($field['type'] == 'id')
						if (!isset($zz_tab[$i][$k]['POST'][$field['field_name']]))
							$zz_tab[$i][$k]['action'] = 'insert';
						else
							$zz_tab[$i][$k]['action'] = 'update';
				}
				// todo: seems to be twice the same operation since $i and $k are !0
				if (!$values)
					if ($zz_tab[$i][$k]['id']['value'])
						$zz_tab[$i][$k]['action'] = 'delete';
					else
						unset($zz_tab[$i][$k]);
				if ($zz_tab[0][0]['action'] == 'delete') 
					if ($zz_tab[$i][$k]['id']['value'])
						$zz_tab[$i][$k]['action'] = 'delete';
					else
						unset($zz_tab[$i][$k]);
			}
			if (!isset($zz_tab[$i]['table_name'])) $zz_tab[$i]['table_name'] = $zz_tab[$i]['table'];
			if (isset($zz_tab[$i][$k]))
				if ($zz_tab[$i][$k]['action'] == 'insert' OR $zz_tab[$i][$k]['action'] == 'update')
				// do something with the POST array before proceeding
					$zz_tab[$i][$k] = zz_validate($zz_tab[$i][$k], $zz_conf, $zz_tab[$i]['table'], $zz_tab[$i]['table_name']); 
				elseif (is_numeric($k))
				//	Check referential integrity
					if (file_exists($zz_conf['dir'].'/inc/integrity.inc.php')) {
						include_once($zz_conf['dir'].'/inc/integrity.inc.php');
				//test
						$record_idfield = $zz_tab[$i][$k]['id']['field_name'];
						$detailrecords = '';
						if (!$no_delete_reason = check_integrity($zz_conf['db_name'], $zz_tab[$i]['table'], $record_idfield, $zz_tab[$i][$k]['POST'][$record_idfield], $zz_conf['relations_table'], $detailrecords))
						// todo: remove db_name maybe?
							$zz_tab[$i][$k]['validation'] = true;
						else $zz_tab[$i][$k]['validation'] = false;
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
		//	### Insert a record ###
		
			if ($zz_tab[$i][$me]['action'] == 'insert') {
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
			}

			if (!$sql_edit) $sql_edit = $me_sql;
			else 			$detail_sql_edit[$i][$me] = $me_sql;
		}
		// ### Do mysql-query and additional actions ###
		
		$result = mysql_query($sql_edit);
		if ($result) {
			if ($zz_tab[0][0]['action'] == 'insert') $zz['formhead'] = $text['record_was_inserted'];
			elseif ($zz_tab[0][0]['action'] == 'update') $zz['formhead'] = $text['record_was_updated'];
			elseif ($zz_tab[0][0]['action'] == 'delete') $zz['formhead'] = $text['record_was_deleted'];
			if ($zz_tab[0][0]['action'] == 'insert') $zz_tab[0][0]['id']['value'] = mysql_insert_id(); // for requery
			if ($zz_conf['logging']) zz_log_sql($sql_edit, $zz_conf['user']); // Logs SQL Query, must be after insert_id was checked
			if (isset($detail_sql_edit))
				foreach (array_keys($detail_sql_edit) as $i)
					foreach (array_keys($detail_sql_edit[$i]) as $k) {
						$detail_sql = $detail_sql_edit[$i][$k];
						$detail_sql = str_replace('[FOREIGN_KEY]', '"'.$zz_tab[0][0]['id']['value'].'"', $detail_sql);
						//if ($zz['action'] == 'insert') $detail_sql .= $zz_tab[0][0]['id']['value'].');';
						$detail_result = mysql_query($detail_sql);
						if (!$detail_result) {
							$zz['formhead'] = false;
							$zz_error['msg']	.= 'Detail record could not be handled';
							$zz_error['level']	.= 'crucial';
							$zz_error['type']	.= 'mysql';
							$zz_error['query']	.= $detail_sql;
							$zz_error['mysql']	.= mysql_error();

						} elseif ($zz_tab[$i][$k]['action'] == 'insert') 
							$zz_tab[$i][$k]['id']['value'] = mysql_insert_id(); // for requery
						if ($zz_conf['logging']) zz_log_sql($detail_sql, $zz_conf['user']); // Logs SQL Query
					}
			if (isset($zz_conf['action']['after_'.$zz['action']])) 
				include ($zz_conf['action_dir'].'/'.$zz_conf['action']['after_'.$zz['action']].'.inc.php'); 
				// if any other action after insertion/update/delete is required
			if (!empty($upload_form) && $zz_tab[0][0]['action'] != 'delete')
				zz_write_upload($zz); // upload images, as required
			//elseif(!empty($upload_form) && $zz_tab[0][0]['action'] == 'delete') 
				// todo: delete images
		} else {
			// Output Error Message
			$zz['formhead'] = false;
			if ($zz['action'] == 'insert') $zz_tab[0][0]['id']['value'] = false; // for requery
			$zz_error['msg']	.= $zz['action'].' failed';
			$zz_error['level']	.= 'crucial';
			$zz_error['type']	.= 'mysql';
			$zz_error['query']	.= $sql_edit;
			$zz_error['mysql']	.= mysql_error();
		}
	}	
}

?>