<?php

/*
	zzform Scripts

	functions for validation of user input
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2006

*/

function zz_validate($my, $zz_conf, $table, $table_name) {
	global $text;
	$my['POST-notvalid'] = $my['POST'];
	$my['validation'] = true;
	foreach (array_keys($my['fields']) as $f) {
	//	remove entries which are for display only
		if ($my['fields'][$f]['type'] == 'calculated' OR $my['fields'][$f]['type'] == 'image'
			OR $my['fields'][$f]['type'] == 'upload-image' 
			OR $my['fields'][$f]['type'] == 'foreign' OR $my['fields'][$f]['type'] == 'subtable')
			$my['fields'][$f]['in_sql_query'] = false;
		elseif ($my['fields'][$f]['type'] == 'id') {
			$my['fields'][$f]['in_sql_query'] = true;
			if ($my['action'] == 'update') {
				$my['id']['field_name'] = $my['fields'][$f]['field_name'];
				$my['id']['value'] = $my['POST'][$my['id']['field_name']]; // for display of updated record
			} else
				$my['POST'][$my['fields'][$f]['field_name']] = "''";
		} else {
			$my['fields'][$f]['in_sql_query'] = true;

		//	copy value if field detail_value isset
			if (isset($my['fields'][$f]['detail_value']))
				$my['POST'][$my['fields'][$f]['field_name']] = $my['POST'][$my['fields'][$f]['detail_value']];

		//	calculation and choosing of right values in case of coordinates
			if ($my['fields'][$f]['type'] == 'number' AND isset($my['fields'][$f]['number_type']) 
				AND $my['fields'][$f]['number_type'] == 'latitude' || $my['fields'][$f]['number_type'] == 'longitude') {
				// geographical coordinates
				if ($my['POST'][$my['fields'][$f]['field_name']]['which'] == 'dec') 
					$my['POST'][$my['fields'][$f]['field_name']] = $my['POST'][$my['fields'][$f]['field_name']]['dec'];
				elseif ($my['POST'][$my['fields'][$f]['field_name']]['which'] == 'dms') {
					$degree = dms2db($my['POST'][$my['fields'][$f]['field_name']]); 
					if (empty($degree['wrong']))
						$my['POST'][$my['fields'][$f]['field_name']] = $degree[substr($my['fields'][$f]['number_type'], 0, 3).'dec'];
					else {
						$my['fields'][$f]['check_validation'] = false;
						$my['fields'][$f]['wrong_fields'] = $degree['wrong']; // for output later on
						$my['validation'] = false;
					}
				}
				if (!is_array($my['POST'][$my['fields'][$f]['field_name']]) && strlen($my['POST'][$my['fields'][$f]['field_name']]) == 0) 
					$my['POST'][$my['fields'][$f]['field_name']] = '';
			} 

		//	check if numbers are entered with . 			

		//	factor for avoiding doubles
			if (isset($my['fields'][$f]['factor']) && $my['POST'][$my['fields'][$f]['field_name']]
				&& !is_array($my['POST'][$my['fields'][$f]['field_name']])) // this line for wrong coordinates
				$my['POST'][$my['fields'][$f]['field_name']] = str_replace(',', '.', $my['POST'][$my['fields'][$f]['field_name']]) * $my['fields'][$f]['factor'];

		//	md5 encrypt passwords
			if ($my['fields'][$f]['type'] == 'password')
				if ($my['POST'][$my['fields'][$f]['field_name']])
					$my['POST'][$my['fields'][$f]['field_name']] = md5($my['POST'][$my['fields'][$f]['field_name']]);
	
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
					if ($my_date = strtotime($my['POST'][$my['fields'][$f]['field_name']])) // strtotime converts several formats, returns false if value is not convertable
						$my['POST'][$my['fields'][$f]['field_name']] = $my_date;
					else {
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
		
		//	insert data from file upload/convert
		//	...		
			if ($my['fields'][$f]['type'] == 'hidden' && isset($my['fields'][$f]['upload-field'])) {
				$g = $my['fields'][$f]['upload-field'];
				$v = $my['fields'][$f]['upload-value'];
				if (preg_match('/.+\[.+\]/', $v)) { // construct access to array values
					$myv = explode('[', $v);
					foreach ($myv as $v_var) {
						if (substr($v_var, strlen($v_var) -1) == ']') $v_var = substr($v_var, 0, strlen($v_var) - 1);
						$v_arr[] = $v_var;
					}
					eval('$myval = $my[\'images\'][$g][0][\'upload\'][\''.implode("']['", $v_arr).'\'];');
				} else
					$myval = (!empty($my['images'][$g][$v])) 
						? $my['images'][$g][$v] // take value from upload-array
						: $my['images'][$g][0]['upload'][$v]; // or take value from first sub-image
				$my['POST'][$my['fields'][$f]['field_name']] = $myval;
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
		}
	// finished
	}
	if ($my['validation']) {
		foreach (array_keys($my['fields']) as $f) {
		//	set
			if ($my['fields'][$f]['type'] == 'select' && isset($my['fields'][$f]['set'])) {
				$value = '';
				if (isset($my['POST'][$my['fields'][$f]['field_name']]) && $my['POST'][$my['fields'][$f]['field_name']]) {
					foreach ($my['POST'][$my['fields'][$f]['field_name']] as $this_value) {
						if ($value) $value .= ',';
						$value .= $this_value;
					}
					$my['POST'][$my['fields'][$f]['field_name']] = $value;
				} else
					$my['POST'][$my['fields'][$f]['field_name']] = '';
			}
		//	slashes, 0 and NULL
			$unwanted = array('calculated', 'image', 'upload-image', 'id', 'foreign', 'subtable', 'foreign_key');
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
	$sql = $my['fields'][$f]['sql'];
	preg_match('/SELECT (.+) FROM /i', $sql, $fieldstring); // preg_match, case insensitive, space after select, space around from - might not be 100% perfect, but should work always
	$fields = explode(",", $fieldstring[1]);
	$oldfield = false;
	$newfields = false;
	foreach ($fields as $myfield) {
		if ($oldfield) $myfield = $oldfield.', '.$myfield;
		if (substr_count($myfield, '(') != substr_count($myfield, ')')) $oldfield = $myfield; // not enough brackets, so glue strings together until there are enought - not 100% safe if bracket appears inside string
		else {
			$myfields = '';
			if (stristr($myfield, ') AS')) preg_match('/(.+\)) AS [a-z0-9_]/i', $myfield, $myfields); // replace AS blah against nothing
			if ($myfields) $myfield = $myfields[1];
			$newfields[] = $myfield;
		}
	}
	if (stristr($sql, ' ORDER BY ')) {
		preg_match('/(.+)( ORDER BY .+)/i', $sql, $sqlparts);
		$sql = $sqlparts[1];
		$sqlorder = $sqlparts[2];
	} else
		$sqlorder = false;
	$postvalues = explode(' | ', $my['POST'][$my['fields'][$f]['field_name']]);
	$wheresql = '';
	foreach ($postvalues as $value)
		foreach ($newfields as $index => $field) {
			if (!$wheresql) $wheresql.= ' WHERE (';
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
			$my['validation'] = false;
		} else {
			$my['fields'][$f]['class'] = 'error' ;
			$my['validation'] = false;
		}
	else $my['validation'] = false;
	return $my;
}
?>