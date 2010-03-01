<?php

// zzform
// (c) Gustaf Mossakowski, <gustaf@koenige.org>, 2004-2010
// Main function for validation of user input


/** Validates user input
 * 
 * @param array $my = $zz_tab[$i][$k]
 * @param array $zz_conf
 * @param string $db_table [db_name.table]
 * @param string $table_name Alias for table if it occurs in the form more than once
 * @param int $k
 * @param array $main_post		POST values of $zz_tab[0][0]['POST']
 * @return array $my with validated values and marker if validation was successful ($my['validation'])
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_validate($my, $zz_conf, $db_table, $table_name, $k = 0, $main_post) {
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	global $zz_error;
	$my['POST-notvalid'] = $my['POST'];
	$my['validation'] = true;
	$last_fields = false;

	foreach (array_keys($my['fields']) as $f) {
	//	check if there are options-fields and put values into table definition
		if (!empty($my['fields'][$f]['read_options'])) {
			$submitted_option = $my['POST'][$my['fields'][$my['fields'][$f]['read_options']]['field_name']];
			// if there's something submitted which fits in our scheme, replace values corresponding to options-field
			if (!empty($my['fields'][$my['fields'][$f]['read_options']]['options'][$submitted_option])) {
				$my['fields'][$f] = array_merge($my['fields'][$f], $my['fields'][$my['fields'][$f]['read_options']]['options'][$submitted_option]);
			}
		}
	//	remove entries which are for display only
		if ($my['fields'][$f]['type'] == 'write_once' 
			AND empty($my['record'][$my['fields'][$f]['field_name']])) {
			if (!empty($my['fields'][$f]['type_detail']))
				$my['fields'][$f]['type'] = $my['fields'][$f]['type_detail'];
		}
		switch ($my['fields'][$f]['type']) {
			case 'upload_image':
				$input_filetypes = (isset($my['fields'][$f]['input_filetypes']) 
					? $my['fields'][$f]['input_filetypes'] 
					: array());
				if (!zz_upload_check($my['images'][$f], $my['action'], $zz_conf, $input_filetypes, $k)) {
					$my['validation'] = false;
					$my['fields'][$f]['check_validation'] = false;
					if (is_array($my['images'][$f])) foreach ($my['images'][$f] as $key => $image) {
						if (is_numeric($key) AND !empty($image['error'])) {
							foreach ($image['error'] as $error) {
								$zz_error['validation']['incorrect_values'][] = array(
									'field_name' => $my['fields'][$f]['field_name'],
									'msg' => $error
								);
							}
						}
					}
				}
			case 'display':
			case 'write_once':
			case 'option':
			case 'calculated':
			case 'image':
			case 'foreign':
			case 'subtable':
				$my['fields'][$f]['in_sql_query'] = false;
				break;
			case 'timestamp':
				if (!empty($my['fields'][$f]['display']))
					$my['fields'][$f]['in_sql_query'] = false;
				else
					$my['fields'][$f]['in_sql_query'] = true;
				break;
			case 'id':
				$my['fields'][$f]['in_sql_query'] = true;
				if ($my['action'] == 'update') {
					$my['id']['field_name'] = $my['fields'][$f]['field_name'];
					$my['id']['value'] = $my['POST'][$my['id']['field_name']]; // for display of updated record
				} else
					$my['POST'][$my['fields'][$f]['field_name']] = "''";
			default:
				if (!empty($my['fields'][$f]['display_only'])) {
					$my['fields'][$f]['in_sql_query'] = false;
					$my['fields'][$f]['class'] = 'hidden';
					break;
				}

				$my['fields'][$f]['in_sql_query'] = true;

			//	copy value if field detail_value isset
				if (isset($my['fields'][$f]['detail_value']))
					if (isset($my['POST'][$my['fields'][$f]['detail_value']])) // first test same subtable
						$my['POST'][$my['fields'][$f]['field_name']] = $my['POST'][$my['fields'][$f]['detail_value']];
					elseif (isset($_POST[$my['fields'][$f]['detail_value']])) // main table, currently no other means to access it
						$my['POST'][$my['fields'][$f]['field_name']] = $_POST[$my['fields'][$f]['detail_value']];
	
			//	convert ipv4 address to long
				if ($my['fields'][$f]['type'] == 'ipv4' 
					OR ($my['fields'][$f]['type'] == 'hidden' && !empty($my['fields'][$f]['sub_type'])
						&& $my['fields'][$f]['sub_type'] == 'ipv4')) {
						if ($my['POST'][$my['fields'][$f]['field_name']])
							$my['POST'][$my['fields'][$f]['field_name']] = ip2long($my['POST'][$my['fields'][$f]['field_name']]);
					}
					
			//	calculation and choosing of right values in case of coordinates
				elseif ($my['fields'][$f]['type'] == 'number' AND isset($my['fields'][$f]['number_type']) 
					AND $my['fields'][$f]['number_type'] == 'latitude' || $my['fields'][$f]['number_type'] == 'longitude') {
					// geographical coordinates
					switch ($my['POST'][$my['fields'][$f]['field_name']]['which']) {
						case 'dec':
							$my['POST'][$my['fields'][$f]['field_name']] = str_replace(',', '.', $my['POST'][$my['fields'][$f]['field_name']]['dec']);
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
						if ($n_val !== NULL) {
							$my['POST'][$my['fields'][$f]['field_name']] = $n_val;
						} else {
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
	
			//	encrypt passwords, only for changed passwords! therefore string is compared against old pwd
				// action=update: here, we have to check whether submitted password is equal to password in db
				// if so, password won't be touched
				// if not, password will be encrypted
				// action=insert: password will be encrypted
				if ($my['fields'][$f]['type'] == 'password')
					if ($my['POST'][$my['fields'][$f]['field_name']])
						if ($my['action'] == 'insert')
							$my['POST'][$my['fields'][$f]['field_name']] = $zz_conf['password_encryption']($my['POST'][$my['fields'][$f]['field_name']]);
						elseif ($my['action'] == 'update') {
							if (!isset($my['POST'][$my['fields'][$f]['field_name'].'--old'])
							|| ($my['POST'][$my['fields'][$f]['field_name']] != $my['POST'][$my['fields'][$f]['field_name'].'--old']))
								$my['POST'][$my['fields'][$f]['field_name']] = $zz_conf['password_encryption']($my['POST'][$my['fields'][$f]['field_name']]);
						}
	
			//	change encrypted password
				if ($my['fields'][$f]['type'] == 'password_change') {
					$pwd = false;
					if ($my['POST'][$my['fields'][$f]['field_name']] 
						AND $my['POST'][$my['fields'][$f]['field_name'].'_new_1']
						AND $my['POST'][$my['fields'][$f]['field_name'].'_new_2']) {
						$my_sql = $my['fields'][$f]['sql_password_check'].$my['id']['value'];
						$pwd = zz_check_password($my['POST'][$my['fields'][$f]['field_name']], $my['POST'][$my['fields'][$f]['field_name'].'_new_1'], $my['POST'][$my['fields'][$f]['field_name'].'_new_2'], $my_sql);
					} else {
						$zz_error[] = array(
							'msg' => zz_text('Please enter your current password and twice your new password.'),
							'level' => E_USER_NOTICE
						);
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
				if (isset($_POST['zz_check_select']) && $my['fields'][$f]['type'] == 'select' 
						&& (in_array($my['fields'][$f]['field_name'], $_POST['zz_check_select']) 
							OR (in_array($table_name.'['.$k.']['.$my['fields'][$f]['field_name'].']', $_POST['zz_check_select']))) // check only for 0, might be problem, but 0 should always be there
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

				if ($my['fields'][$f]['type'] == 'identifier') {
					$last_fields[] = $f;
					continue; // will be dealt with at the end, when all other values are clear
				}
			//	insert data from file upload/convert
			//	...
				$possible_upload_fields = array('date', 'time', 'text', 'memo');
				if (($my['fields'][$f]['type'] == 'hidden' && !empty($my['fields'][$f]['upload_field'])) // type hidden, upload_field set
					OR (in_array($my['fields'][$f]['type'], $possible_upload_fields) && !empty($my['fields'][$f]['upload_field']) && empty($my['POST'][$my['fields'][$f]['field_name']]))) {
					$myval = false;
					$v_arr = false;
					$g = $my['fields'][$f]['upload_field'];
					$possible_values = $my['fields'][$f]['upload_value'];
					if (!is_array($possible_values)) $possible_values = array($possible_values);
					foreach ($possible_values AS $v) {
						if ($v == 'md5' AND !empty($my['images'])) {
							if (!empty($my['images'][$g][0]['modified']['tmp_name']))
								$myval = md5_file($my['images'][$g][0]['modified']['tmp_name']);
							else
								$myval = md5_file($my['images'][$g][0]['upload']['tmp_name']);
						} elseif ($v == 'md5_source_file' AND !empty($my['images'])) {
							$myval = md5_file($my['images'][$g][0]['upload']['tmp_name']);
						} elseif ($v == 'sha1' AND !empty($my['images'])) {
							if (!empty($my['images'][$g][0]['modified']['tmp_name']))
								$myval = sha1_file($my['images'][$g][0]['modified']['tmp_name']);
							else
								$myval = sha1_file($my['images'][$g][0]['upload']['tmp_name']);
						} elseif ($v == 'sha1_source_file' AND !empty($my['images'])) {
							$myval = sha1_file($my['images'][$g][0]['upload']['tmp_name']);
						} else {
							if (preg_match('/.+\[.+\]/', $v)) { // construct access to array values
								$myv = explode('[', $v);
								foreach ($myv as $v_var) {
									if (substr($v_var, -1) == ']') $v_var = substr($v_var, 0, -1);
									$v_arr[] = $v_var;
								}
								$key1 = '$my[\'images\'][$g][0][\'upload\'][\''.implode("']['", $v_arr).'\']';
								eval('$myval = (isset('.$key1.') ? '.$key1.': false);');
								if (!$myval) {
									$key1 = '$my[\'images\'][$g][0][\''.implode("']['", $v_arr).'\']';
									eval('$myval = (isset('.$key1.') ? '.$key1.': false);');
								}
							} else {
								$myval = (!empty($my['images'][$g][$v])) 
									? $my['images'][$g][$v] // take value from upload-array
									: (!empty($my['images'][$g][0]['upload'][$v]) ? $my['images'][$g][0]['upload'][$v] : ''); // or take value from first sub-image
							}
							if ($myval && !empty($my['fields'][$f]['upload_sql'])) {
								$sql = $my['fields'][$f]['upload_sql'].'"'.$myval.'"';
								$result = mysql_query($sql);
								if ($result) if (mysql_num_rows($result))
									$myval = mysql_result($result, 0, 0);
							}
						}
						// go through this foreach until you have a value
						if ($myval) break;
					}
					if ($myval) {
						$my['POST'][$my['fields'][$f]['field_name']] = $myval;
					}
					if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, 
						'uploadfield: '.$my['fields'][$f]['field_name'].' %'.$my['POST'][$my['fields'][$f]['field_name']].'%<br>'
						.'val: %'.$myval.'%'
					);
					// else: POST left empty, old values will remain (is this true?)
				}
	
	
			//	validation
			//	first check for backwards compatibilty - old edit.inc does not include validation
				if ($zz_conf['do_validation']) {
	
			//		check whether is false but must not be NULL
					if (!isset($my['POST'][$my['fields'][$f]['field_name']])) {
						// no set = must be error
						if ($my['fields'][$f]['type'] == 'foreign_key' 
							OR $my['fields'][$f]['type'] == 'translation_key'
							OR $my['fields'][$f]['type'] == 'detail_key') {
							// foreign key will always be empty but most likely also be required.
							// f. key will be added by script later on (because sometimes it is not known yet)
							// do nothing, leave $my['validation'] as it is
						} elseif ($my['fields'][$f]['type'] == 'timestamp') {
							// timestamps will be set to current date, so no check is necessary
							// do nothing, leave $my['validation'] as it is
						} elseif (!isset($my['fields'][$f]['set']))
							$my['validation'] = false;
						elseif (!zz_check_for_null($my['fields'][$f]['field_name'], $db_table)) {
							$my['validation'] = false;
							$my['fields'][$f]['check_validation'] = false;
						} elseif (!empty($my['fields'][$f]['required'])) {
							$my['validation'] = false;
							$my['fields'][$f]['check_validation'] = false;
						}
					} elseif(!$my['POST'][$my['fields'][$f]['field_name']] 
						AND empty($my['fields'][$f]['null'])
						AND empty($my['fields'][$f]['null-string'])
						AND $my['fields'][$f]['type'] != 'timestamp')
						if (!zz_check_for_null($my['fields'][$f]['field_name'], $db_table)) {
							$my['validation'] = false;
							$my['fields'][$f]['check_validation'] = false;
						} elseif (!empty($my['fields'][$f]['required'])) {
							$my['validation'] = false;
							$my['fields'][$f]['check_validation'] = false;
						}

			//		check for correct enum values
					if (isset($my['fields'][$f]['enum'])) {
						if ($my['POST'][$my['fields'][$f]['field_name']]) {
							if (!$tempvar = zz_check_enumset($my['POST'][$my['fields'][$f]['field_name']], 
									$my['fields'][$f], $db_table)) {
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
							if (!$tempvar = zz_check_mail($my['POST'][$my['fields'][$f]['field_name']])) {
								$my['validation'] = false;
								$my['fields'][$f]['check_validation'] = false;
							} else $my['POST'][$my['fields'][$f]['field_name']] = $tempvar;
						}
					}
				}
		// finished
		}
	}
	if ($last_fields) { // these fields have to be handled after others because they might get data from other fields (e. g. upload_fields)
		foreach ($last_fields as $f)
			//	call function: generate ID
			if ($my['fields'][$f]['type'] == 'identifier') {
				$func_vars = zz_get_identifier_vars($my, $f, $main_post);
				$conf = (!empty($my['fields'][$f]['conf_identifier']) 
					? $my['fields'][$f]['conf_identifier'] : false);
				$my['POST'][$my['fields'][$f]['field_name']] 
					= zz_create_identifier($func_vars, $conf, $my, $db_table, $f);
			}
	}
	
	if ($my['validation']) {
		foreach (array_keys($my['fields']) as $f) {
		//	set
			if ($my['fields'][$f]['type'] == 'select' && isset($my['fields'][$f]['set'])) {
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
						$my['POST'][$my['fields'][$f]['field_name']] = '"'.mysql_real_escape_string($my['POST'][$my['fields'][$f]['field_name']]).'"';
					else
						$my['POST'][$my['fields'][$f]['field_name']] = '"'.addslashes($my['POST'][$my['fields'][$f]['field_name']]).'"';
				} else {
					if (isset($my['fields'][$f]['number_type']) AND ($my['POST'][$my['fields'][$f]['field_name']] !== '') // type string, different from 0
						AND $my['fields'][$f]['number_type'] == 'latitude' || $my['fields'][$f]['number_type'] == 'longitude')
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
	}
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
	return $my;
}

?>