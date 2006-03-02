<?php

/*
	zzform Scripts

	functions for validation of user input
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2006

*/

function zz_validate($my, $zz_conf, $table, $table_name) {
	global $text;
	global $zz_error;
	$my['POST-notvalid'] = $my['POST'];
	$my['validation'] = true;
	foreach (array_keys($my['fields']) as $f)
	//	remove entries which are for display only
		switch ($my['fields'][$f]['type']) {
			case 'upload_image':
				$input_filetypes = (isset($my['fields'][$f]['input_filetypes']) 
					? $my['fields'][$f]['input_filetypes'] 
					: array());
				if (!zz_check_upload($my['images'][$f], $my['action'], $zz_conf, $input_filetypes)) {
					$my['validation'] = false;
					$my['fields'][$f]['check_validation'] = false;
				}
			case 'display':
			case 'option':
			case 'calculated':
			case 'image':
			case 'foreign':
			case 'subtable':
				$my['fields'][$f]['in_sql_query'] = false;
				break;
			case 'id':
				$my['fields'][$f]['in_sql_query'] = true;
				if ($my['action'] == 'update') {
					$my['id']['field_name'] = $my['fields'][$f]['field_name'];
					$my['id']['value'] = $my['POST'][$my['id']['field_name']]; // for display of updated record
				} else
					$my['POST'][$my['fields'][$f]['field_name']] = "''";
			default:
				$my['fields'][$f]['in_sql_query'] = true;

			//	copy value if field detail_value isset
				if (isset($my['fields'][$f]['detail_value']))
					$my['POST'][$my['fields'][$f]['field_name']] = $my['POST'][$my['fields'][$f]['detail_value']];
	
			//	calculation and choosing of right values in case of coordinates
				if ($my['fields'][$f]['type'] == 'number' AND isset($my['fields'][$f]['number_type']) 
					AND $my['fields'][$f]['number_type'] == 'latitude' || $my['fields'][$f]['number_type'] == 'longitude') {
					// geographical coordinates
					switch ($my['POST'][$my['fields'][$f]['field_name']]['which']) {
						case 'dec':
							$my['POST'][$my['fields'][$f]['field_name']] = $my['POST'][$my['fields'][$f]['field_name']]['dec'];
							break;
						case 'dm':
						case 'dms':
							$degree = dms2db($my['POST'][$my['fields'][$f]['field_name']], $my['POST'][$my['fields'][$f]['field_name']]['which']); 
							if (empty($degree['wrong']))
								$my['POST'][$my['fields'][$f]['field_name']] = $degree[substr($my['fields'][$f]['number_type'], 0, 3).'_dec'];
							else {
								$my['fields'][$f]['check_validation'] = false;
								$my['fields'][$f]['wrong_fields'] = $degree['wrong']; // for output later on
								$my['validation'] = false;
							}
							break;
					} 
					if (!is_array($my['POST'][$my['fields'][$f]['field_name']]) && strlen($my['POST'][$my['fields'][$f]['field_name']]) == 0) 
						$my['POST'][$my['fields'][$f]['field_name']] = '';

			//	check if numbers are entered with .
				} elseif ($my['fields'][$f]['type'] == 'number') {
					if ($my['POST'][$my['fields'][$f]['field_name']]) { // only check if there is a value, NULL values are checked later on
						$n_val = check_number($my['POST'][$my['fields'][$f]['field_name']]);
						if ($n_val) $my['POST'][$my['fields'][$f]['field_name']] = $n_val;
						else {
							$my['POST'][$my['fields'][$f]['field_name']] = false;
							$my['fields'][$f]['check_validation'] = false;
							$my['validation'] = false;
						}
					}
				}
				
			//	factor for avoiding doubles
				if (isset($my['fields'][$f]['factor']) && $my['POST'][$my['fields'][$f]['field_name']]
					&& !is_array($my['POST'][$my['fields'][$f]['field_name']])) // this line for wrong coordinates
					$my['POST'][$my['fields'][$f]['field_name']] = str_replace(',', '.', $my['POST'][$my['fields'][$f]['field_name']]) * $my['fields'][$f]['factor'];
	
			//	md5 encrypt passwords
				if ($my['fields'][$f]['type'] == 'password')
					if ($my['POST'][$my['fields'][$f]['field_name']])
						$my['POST'][$my['fields'][$f]['field_name']] = md5($my['POST'][$my['fields'][$f]['field_name']]);
		
			//	change md5 encrypted password
				if ($my['fields'][$f]['type'] == 'password_change') {
					$pwd = false;
					if ($my['POST'][$my['fields'][$f]['field_name']] 
						AND $my['POST'][$my['fields'][$f]['field_name'].'_new_1']
						AND $my['POST'][$my['fields'][$f]['field_name'].'_new_2']) {
						$my_sql = $my['fields'][$f]['sql_password_check'].$my['id']['value'];
						$pwd = zz_check_password($my['POST'][$my['fields'][$f]['field_name']], $my['POST'][$my['fields'][$f]['field_name'].'_new_1'], $my['POST'][$my['fields'][$f]['field_name'].'_new_2'], $my_sql);
					} else {
						$zz_error['msg'] = $text['Please enter your current password and twice your new password.'];
					}
					if ($pwd) $my['POST'][$my['fields'][$f]['field_name']] = $pwd;
					else { 
						$my['POST'][$my['fields'][$f]['field_name']] = false;
						$my['fields'][$f]['check_validation'] = false;
						$my['validation'] = false;
					}
				}
	
			//	validate time
				if ($my['fields'][$f]['type'] == 'time')
					if ($my['POST'][$my['fields'][$f]['field_name']])
						if ($my_time = validate_time($my['POST'][$my['fields'][$f]['field_name']]))
							$my['POST'][$my['fields'][$f]['field_name']] = $my_time;
						else {
							//echo $my['POST'][$my['fields'][$f]['field_name']].'<br>';
							$my['fields'][$f]['check_validation'] = false;
							$my['validation'] = false;
						}
	
			//	check select /// workwork
				if (isset($_POST['check_select']) && $my['fields'][$f]['type'] == 'select' 
						&& (in_array($my['fields'][$f]['field_name'], $_POST['check_select']) 
							OR (in_array($table_name.'[0]['.$my['fields'][$f]['field_name'].']', $_POST['check_select']))) // check only for 0, might be problem, but 0 should always be there
						&& $my['POST'][$my['fields'][$f]['field_name']]) { // if null -> accept it
					$my = zz_check_select($my, $f, $zz_conf['max_select']);
				}
				
			//	internationalize date!
				if ($my['fields'][$f]['type'] == 'date')
					if ($my['POST'][$my['fields'][$f]['field_name']])
					// submit to datum_int only if there is a value, else return would be false and validation true!
						if ($my_date = datum_int($my['POST'][$my['fields'][$f]['field_name']]))
							$my['POST'][$my['fields'][$f]['field_name']] = $my_date;
						else {
							//echo $my['POST'][$my['fields'][$f]['field_name']].'<br>';
							$my['fields'][$f]['check_validation'] = false;
							$my['validation'] = false;
						}
	
			//	convert unix_timestamp
				if ($my['fields'][$f]['type'] == 'unix_timestamp')
					if ($my['POST'][$my['fields'][$f]['field_name']])
						if ($my['POST'][$my['fields'][$f]['field_name']]) {
							$my_date = strtotime($my['POST'][$my['fields'][$f]['field_name']]); 
							if ($my_date != -1) // strtotime converts several formats, returns -1 if value is not convertable
								$my['POST'][$my['fields'][$f]['field_name']] = $my_date;
							elseif (preg_match('/^[0-9]+$/', $my['POST'][$my['fields'][$f]['field_name']])) // is already timestamp, does not work with all integer values since some of them are converted with strtotime
								$my['POST'][$my['fields'][$f]['field_name']] = $my['POST'][$my['fields'][$f]['field_name']];
							else {
								$my['fields'][$f]['check_validation'] = false;
								$my['validation'] = false;
							}
						} else {
							$my['fields'][$f]['check_validation'] = false;
							$my['validation'] = false;
						}
	
			//	call function
				if ($my['fields'][$f]['type'] == 'hidden' && isset($my['fields'][$f]['function'])) {
					foreach ($my['fields'][$f]['fields'] as $var)
						if (strstr($var, '.')) {
							$vars = explode('.', $var);
							$func_vars[$var] = $my['POST'][$vars[0]][0][$vars[1]];
						} else
							$func_vars[$var] = $my['POST'][$var];
					$my['POST'][$my['fields'][$f]['field_name']] = $my['fields'][$f]['function']($func_vars);
				}
	
			//	call function: generate ID
				if ($my['fields'][$f]['type'] == 'identifier') {
					foreach ($my['fields'][$f]['fields'] as $var)
						if (strstr($var, '.')) {
							$vars = explode('.', $var);
							$func_vars[$var] = $my['POST'][$vars[0]][0][$vars[1]];
						} else
							$func_vars[$var] = $my['POST'][$var];
					if (!empty($my['fields'][$f]['conf_identifier'])) $conf = $my['fields'][$f]['conf_identifier'];
					else $conf = false;
					$my['POST'][$my['fields'][$f]['field_name']] = zz_create_identifier($func_vars, $my, $table, $f, $conf);
				}
			
			//	insert data from file upload/convert
			//	...
				$possible_upload_fields = array('date', 'time', 'text');
				if (($my['fields'][$f]['type'] == 'hidden' && !empty($my['fields'][$f]['upload_field'])) // type hidden, upload_field set
					OR (in_array($my['fields'][$f]['type'], $possible_upload_fields) && !empty($my['fields'][$f]['upload_field']) && empty($my['POST'][$my['fields'][$f]['field_name']]))) {
					$myval = false;
					$v_arr = false;
					$g = $my['fields'][$f]['upload_field'];
					$v = $my['fields'][$f]['upload_value'];
					if (preg_match('/.+\[.+\]/', $v)) { // construct access to array values
						$myv = explode('[', $v);
						foreach ($myv as $v_var) {
							if (substr($v_var, strlen($v_var) -1) == ']') $v_var = substr($v_var, 0, strlen($v_var) - 1);
							$v_arr[] = $v_var;
						}
						eval('$myval = $my[\'images\'][$g][0][\'upload\'][\''.implode("']['", $v_arr).'\'];');
						if (!$myval) eval('$myval = $my[\'images\'][$g][0][\''.implode("']['", $v_arr).'\'];');
					} else
						$myval = (!empty($my['images'][$g][$v])) 
							? $my['images'][$g][$v] // take value from upload-array
							: (!empty($my['images'][$g][0]['upload'][$v]) ? $my['images'][$g][0]['upload'][$v] : ''); // or take value from first sub-image
					if ($myval && !empty($my['fields'][$f]['upload_sql'])) {
						$sql = $my['fields'][$f]['upload_sql'].'"'.$myval.'"';
						$result = mysql_query($sql);
						if ($result) if (mysql_num_rows($result))
							$myval = mysql_result($result, 0, 0);
					}
					if ($myval) {
						$my['POST'][$my['fields'][$f]['field_name']] = $myval;
					}
					if ($zz_conf['debug']) {
						echo '<br>uploadfield: '.$my['fields'][$f]['field_name'].' %'.$my['POST'][$my['fields'][$f]['field_name']].'%';
						echo '<br>val: %'.$myval.'%';
					}
					// else: POST left empty, old values will remain (is this true?)
				}
	
	
			//	validation
			//	first check for backwards compatibilty - old edit.inc does not include validation
				if ($zz_conf['do_validation']) {
	
			//		check whether is false but must not be NULL
					if (!isset($my['POST'][$my['fields'][$f]['field_name']])) {
						// no set = must be error
						if ($my['fields'][$f]['type'] == 'foreign_key')
							// foreign key will always be empty but most likely also be required.
							// f. key will be added by script later on (because sometimes it is not known yet)
							$my['validation'] = true;
						elseif (!isset($my['fields'][$f]['set']))
							$my['validation'] = false;
						elseif (!checkfornull($my['fields'][$f]['field_name'], $table)) {
							$my['validation'] = false;
							$my['fields'][$f]['check_validation'] = false;
						}
					} elseif(!$my['POST'][$my['fields'][$f]['field_name']] AND empty($my['fields'][$f]['null']))
						if (!checkfornull($my['fields'][$f]['field_name'], $table)) {
							$my['validation'] = false;
							$my['fields'][$f]['check_validation'] = false;
						}

			//		check for correct enum values
					if (isset($my['fields'][$f]['enum'])) {
						if ($my['POST'][$my['fields'][$f]['field_name']]) {
							if (!$tempvar = checkenum($my['POST'][$my['fields'][$f]['field_name']], $my['fields'][$f]['field_name'], $table)) {
								$my['validation'] = false;
								$my['fields'][$f]['check_validation'] = false;
							} else $my['POST'][$my['fields'][$f]['field_name']] = $tempvar;
						}
					}
			//		check for correct url
					if ($my['fields'][$f]['type'] == 'url') {
						if ($my['POST'][$my['fields'][$f]['field_name']]) {
							if (!$tempvar = zz_check_url($my['POST'][$my['fields'][$f]['field_name']])) {
								$my['validation'] = false;
								$my['fields'][$f]['check_validation'] = false;
							} else $my['POST'][$my['fields'][$f]['field_name']] = $tempvar;
						}
					}
	
			//		check for correct mailaddress
					if ($my['fields'][$f]['type'] == 'mail') {
						if ($my['POST'][$my['fields'][$f]['field_name']]) {
							if (!$tempvar = checkmail($my['POST'][$my['fields'][$f]['field_name']])) {
								$my['validation'] = false;
								$my['fields'][$f]['check_validation'] = false;
							} else $my['POST'][$my['fields'][$f]['field_name']] = $tempvar;
						}
					}
				}
		// finished
	}
	if ($my['validation']) {
		foreach (array_keys($my['fields']) as $f) {
		//	set
			if ($my['fields'][$f]['type'] == 'select' && isset($my['fields'][$f]['set'])) {
				if (isset($my['POST'][$my['fields'][$f]['field_name']]) && $my['POST'][$my['fields'][$f]['field_name']])
					$my['POST'][$my['fields'][$f]['field_name']] = implode(',', $my['POST'][$my['fields'][$f]['field_name']]);
				else
					$my['POST'][$my['fields'][$f]['field_name']] = '';
			}
		//	slashes, 0 and NULL
			$unwanted = array('calculated', 'image', 'upload_image', 'id', 'foreign', 'subtable', 'foreign_key', 'display', 'option');
			if (!in_array($my['fields'][$f]['type'], $unwanted)) {
				if ($my['POST'][$my['fields'][$f]['field_name']]) {
					if (get_magic_quotes_gpc()) // sometimes unwanted standard config
						$my['POST'][$my['fields'][$f]['field_name']] = stripslashes($my['POST'][$my['fields'][$f]['field_name']]);
					if (function_exists('mysql_real_escape_string')) // just from 4.3.0 on
						$my['POST'][$my['fields'][$f]['field_name']] = '"'.mysql_real_escape_string($my['POST'][$my['fields'][$f]['field_name']]).'"';
					else
						$my['POST'][$my['fields'][$f]['field_name']] = '"'.addslashes($my['POST'][$my['fields'][$f]['field_name']]).'"';
				} else {
					if (isset($my['fields'][$f]['number_type']) AND ($my['POST'][$my['fields'][$f]['field_name']] !== '') // type string, different from 0
						AND $my['fields'][$f]['number_type'] == 'latitude' || $my['fields'][$f]['number_type'] == 'longitude')
						echo $my['POST'][$my['fields'][$f]['field_name']] = '0';
					elseif (isset($my['fields'][$f]['null']) AND $my['fields'][$f]['null']) 
						$my['POST'][$my['fields'][$f]['field_name']] = '0';
					else 
						$my['POST'][$my['fields'][$f]['field_name']] = 'NULL';
				}
			}
		// foreign_key
			if ($my['fields'][$f]['type'] == 'foreign_key') $my['POST'][$my['fields'][$f]['field_name']] = '[FOREIGN_KEY]';
			if ($my['fields'][$f]['type'] == 'timestamp') $my['POST'][$my['fields'][$f]['field_name']] = 'NULL';
		}
	}
	return $my;
}

function zz_check_select($my, $f, $max_select) {
	global $text;
	global $zz_error;
	$sql = $my['fields'][$f]['sql'];
	preg_match('/SELECT (.+) FROM /i', $sql, $fieldstring); // preg_match, case insensitive, space after select, space around from - might not be 100% perfect, but should work always
	$fields = explode(",", $fieldstring[1]);
	$oldfield = false;
	$newfields = false;
	foreach ($fields as $myfield) {
		if ($oldfield) $myfield = $oldfield.', '.$myfield; // oldfield, so we are inside parentheses
		if (substr_count($myfield, '(') != substr_count($myfield, ')')) $oldfield = $myfield; // not enough brackets, so glue strings together until there are enought - not 100% safe if bracket appears inside string
		else {
			$myfields = '';
			if (stristr($myfield, ') AS')) preg_match('/(.+\)) AS [a-z0-9_]/i', $myfield, $myfields); // replace AS blah against nothing
			if ($myfields) $myfield = $myfields[1];
			$newfields[] = $myfield;
			$oldfield = false; // now that we've written it to array, empty it
		}
	}
	if (stristr($sql, ' ORDER BY ')) {
		preg_match('/(.+)( ORDER BY .+)/i', $sql, $sqlparts);
		$sql = $sqlparts[1];
		$sqlorder = $sqlparts[2];
	} else
		$sqlorder = false;
	$postvalues = explode(' | ', trim($my['POST'][$my['fields'][$f]['field_name']]));
	if (stristr($sql, ' WHERE ')) $where = ' AND ';
	else $where = ' WHERE ';
	$wheresql = '';
	foreach ($postvalues as $value)
		foreach ($newfields as $index => $field) {
			if (!$wheresql) $wheresql.= $where.'(';
			elseif (!$index) $wheresql.= ' ) AND (';
			else $wheresql.= ' OR ';
			$wheresql.= $field.' LIKE "%'.$value.'%"'; 
		}
	$sql.= $wheresql.')';
	if ($sqlorder) $sql.= $sqlorder;
	$result = mysql_query($sql);
	if ($result)
		if (!mysql_num_rows($result)) {
			// no records, user must re-enter values
			$my['fields'][$f]['type'] = 'select';
			$my['fields'][$f]['class'] = 'reselect' ;
			$my['fields'][$f]['suffix'] = '<br>'.$text['No entry found. Try less characters.'];
			$my['validation'] = false;
		} elseif (mysql_num_rows($result) == 1) {
			$my['POST'][$my['fields'][$f]['field_name']] = mysql_result($result, 0, 0);
			$my['POST-notvalid'][$my['fields'][$f]['field_name']] = mysql_result($result, 0, 0);
			$my['fields'][$f]['sql'] = $sql; // if other fields contain errors
		} elseif (mysql_num_rows($result) <= $max_select) {
			$my['fields'][$f]['type'] = 'select';
			$my['fields'][$f]['sql'] = $sql;
			$my['fields'][$f]['class'] = 'reselect' ;
			$my['validation'] = false;
		} elseif (mysql_num_rows($result)) {
			$my['fields'][$f]['default'] = 'reselect' ;
			$my['fields'][$f]['class'] = 'reselect' ;
			$my['fields'][$f]['suffix'] = $text['Please enter more characters.'];
			$my['fields'][$f]['check_validation'] = false;
			$my['validation'] = false;
		} else {
			$my['fields'][$f]['class'] = 'error' ;
			$my['fields'][$f]['check_validation'] = false;
			$my['validation'] = false;
		}
	else {
		$zz_error['msg'] .= mysql_error();
		$zz_error['query'] .= $sql;
		$my['fields'][$f]['check_validation'] = false;
		$my['validation'] = false;
	}
	return $my;
}

function zz_check_password($old, $new1, $new2, $sql) {
	global $zz_error;
	global $text;
	if ($new1 != $new2) {
		$zz_error['msg'] = $text['New passwords do not match. Please try again.'];
		return false; // new passwords do not match
	}
	$result = mysql_query($sql);
	if ($result) if (mysql_num_rows($result) == 1)
		$old_pwd = mysql_result($result, 0, 0);
	//echo mysql_error();
	if (empty($old_pwd)) {
		$zz_error['msg'] = $text['Database error'];
		return false;
	}
	if (md5($old) == $old_pwd) {
		$zz_error['msg'] = $text['Your password has been changed!'];
		return md5($new1); // new1 = new2, old = old, everything is ok
	} else {
		$zz_error['msg'] = $text['Your current password is different from what you entered. Please try again.'];
		return false;
	}
}


?>