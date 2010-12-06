<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2004-2010
// Main functions for validation of user input


/**
 * Validates user input
 * 
 * @param array $my_rec = $zz_tab[$tab][$rec]
 * @param string $db_table [db_name.table]
 * @param string $table_name Alias for table if it occurs in the form more than once
 * @param int $rec
 * @param array $zz_tab = $zz_tab[0][0], keys ['POST'], ['images'] and ['extra']
 * @global array $zz_conf
 * @return array $my_rec with validated values and marker if validation was successful ($my_rec['validation'])
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_validate($my_rec, $db_table, $table_name, $rec = 0, $zz_tab) {
	global $zz_conf;
	global $zz_error;
	
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	// in case validation fails, these values will be send back to user
	$my_rec['POST-notvalid'] = $my_rec['POST']; 
	$my_rec['validation'] = true;
	$my_rec['last_fields'] = array();
	$my_rec['extra'] = array();

	foreach (array_keys($my_rec['fields']) as $f) {
	//	check if some values are to be replaced internally
		if (!empty($my_rec['fields'][$f]['replace_values']) 
			AND !empty($my_rec['POST'][$my_rec['fields'][$f]['field_name']])) {
			if (in_array($my_rec['POST'][$my_rec['fields'][$f]['field_name']],
				array_keys($my_rec['fields'][$f]['replace_values'])))
			$my_rec['POST'][$my_rec['fields'][$f]['field_name']]
				= $my_rec['fields'][$f]['replace_values'][$my_rec['POST'][$my_rec['fields'][$f]['field_name']]];
		}
	
	//	check if there are options-fields and put values into table definition
		if (!empty($my_rec['fields'][$f]['read_options'])) {
			$submitted_option = $my_rec['POST'][$my_rec['fields'][$my_rec['fields'][$f]['read_options']]['field_name']];
			// if there's something submitted which fits in our scheme, replace values corresponding to options-field
			if (!empty($my_rec['fields'][$my_rec['fields'][$f]['read_options']]['options'][$submitted_option])) {
				$my_rec['fields'][$f] = array_merge($my_rec['fields'][$f], $my_rec['fields'][$my_rec['fields'][$f]['read_options']]['options'][$submitted_option]);
			}
		}
	//	set detail types for write_once-Fields
		if ($my_rec['fields'][$f]['type'] == 'write_once' 
			AND empty($my_rec['record'][$my_rec['fields'][$f]['field_name']])) {
			if (!empty($my_rec['fields'][$f]['type_detail']))
				$my_rec['fields'][$f]['type'] = $my_rec['fields'][$f]['type_detail'];
		}

		//	remove entries which are for display only
		if (!empty($my_rec['fields'][$f]['display_only'])) {
			$my_rec['fields'][$f]['in_sql_query'] = false;
			$my_rec['fields'][$f]['class'] = 'hidden';
			continue;
		}

		//	copy value if field detail_value isset
		if (isset($my_rec['fields'][$f]['detail_value'])) {
			$my_field = $my_rec['fields'][$f]['detail_value'];
			if (isset($my_rec['POST'][$my_field])) 
				// first test same subtable
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $my_rec['POST'][$my_field];
			elseif (isset($zz_tab[0][0]['POST'][$my_field])) 
				// main table, currently no other means to access it
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $zz_tab[0][0]['POST'][$my_field];
			elseif (isset($zz_tab[0][0]['extra'][$my_field])) {
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $zz_tab[0][0]['extra'][$my_field];
			}
		}

		// check if some values should be gotten from upload fields
		if (!empty($my_rec['fields'][$f]['upload_field'])) {
			if (strstr($my_rec['fields'][$f]['upload_field'], '[')) {
				preg_match('~(\d+)\[(\d+)\]\[(\d+)\]~', $my_rec['fields'][$f]['upload_field'], $nos);
				if (count($nos) != 4) {
					$zz_error[] = array(
						'msg_dev' => 'Error in $zz definition for upload_field: ['.$f.']',
						'level' => E_USER_NOTICE
					);
				} elseif (!empty($zz_tab[$nos[1]][$nos[2]]['images'][$nos[3]])) {
					//	insert data from file upload/convert
					$my_rec['POST'][$my_rec['fields'][$f]['field_name']] 
						= zz_val_get_from_upload($my_rec['fields'][$f], 
							$zz_tab[$nos[1]][$nos[2]]['images'][$nos[3]],
							$my_rec['POST'][$my_rec['fields'][$f]['field_name']]
						);
				}
			} else {
				//	insert data from file upload/convert
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] 
					= zz_val_get_from_upload($my_rec['fields'][$f], 
						$my_rec['images'][$my_rec['fields'][$f]['upload_field']],
						$my_rec['POST'][$my_rec['fields'][$f]['field_name']]
					);
			}
		}

		//	call function
		if (!empty($my_rec['fields'][$f]['function'])) { // $my_rec['fields'][$f]['type'] == 'hidden' AND 
			foreach ($my_rec['fields'][$f]['fields'] as $var)
				if (strstr($var, '.')) {
					$vars = explode('.', $var);
					$func_vars[$var] = $my_rec['POST'][$vars[0]][0][$vars[1]];
				} else
					$func_vars[$var] = $my_rec['POST'][$var];
			$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $my_rec['fields'][$f]['function']($func_vars, $my_rec['fields'][$f]['field_name']);
		}

		// per default, all fields are becoming part of SQL query
		$my_rec['fields'][$f]['in_sql_query'] = true;

		// get field type, hidden fields with sub_type will be validated against subtype
		$type = $my_rec['fields'][$f]['type'];
		if ($my_rec['fields'][$f]['type'] == 'hidden' 
			AND !empty($my_rec['fields'][$f]['sub_type'])) {
				$type = $my_rec['fields'][$f]['sub_type'];
		}
		
	// 	walk through all fields by type
		switch ($type) {
		case 'id':
			if ($my_rec['action'] == 'update') {
				$my_rec['id']['field_name'] = $my_rec['fields'][$f]['field_name'];
				$my_rec['id']['value'] = $my_rec['POST'][$my_rec['id']['field_name']]; // for display of updated record
			} else
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = "''";
			break;
		case 'ipv4':
			//	convert ipv4 address to long
			if ($my_rec['POST'][$my_rec['fields'][$f]['field_name']])
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = ip2long($my_rec['POST'][$my_rec['fields'][$f]['field_name']]);
			break;
		case 'number':
			//	calculation and choosing of right values in case of coordinates
			if (isset($my_rec['fields'][$f]['number_type']) 
				AND $my_rec['fields'][$f]['number_type'] == 'latitude' || $my_rec['fields'][$f]['number_type'] == 'longitude') {
				// geographical coordinates
				switch ($my_rec['POST'][$my_rec['fields'][$f]['field_name']]['which']) {
				case 'dec':
					$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = str_replace(',', '.', $my_rec['POST'][$my_rec['fields'][$f]['field_name']]['dec']);
					break;
				case 'dm':
				case 'dms':
					$degree = dms2db($my_rec['POST'][$my_rec['fields'][$f]['field_name']], $my_rec['POST'][$my_rec['fields'][$f]['field_name']]['which']); 
					if (empty($degree['wrong']))
						$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $degree[substr($my_rec['fields'][$f]['number_type'], 0, 3).'_dec'];
					else {
						$my_rec['fields'][$f]['check_validation'] = false;
						$my_rec['fields'][$f]['wrong_fields'] = $degree['wrong']; // for output later on
						$my_rec['validation'] = false;
					}
					break;
				} 
				if (!is_array($my_rec['POST'][$my_rec['fields'][$f]['field_name']]) 
					AND strlen($my_rec['POST'][$my_rec['fields'][$f]['field_name']]) == 0) 
					$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = '';
			} elseif ($my_rec['fields'][$f]['type'] == 'number') {
			//	check if numbers are entered with .
				if ($my_rec['POST'][$my_rec['fields'][$f]['field_name']]) { 
					// only check if there is a value, NULL values are checked later on
					$n_val = check_number($my_rec['POST'][$my_rec['fields'][$f]['field_name']]);
					if ($n_val !== NULL) {
						$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $n_val;
					} else {
						$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = false;
						$my_rec['fields'][$f]['check_validation'] = false;
						$my_rec['validation'] = false;
					}
				}
			}

			//	factor for avoiding doubles
			if (isset($my_rec['fields'][$f]['factor']) && $my_rec['POST'][$my_rec['fields'][$f]['field_name']]
				&& !is_array($my_rec['POST'][$my_rec['fields'][$f]['field_name']])) // this line for wrong coordinates
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = str_replace(',', '.', 
					$my_rec['POST'][$my_rec['fields'][$f]['field_name']]) * $my_rec['fields'][$f]['factor'];
			break;
		case 'password':
			//	encrypt passwords, only for changed passwords! therefore string is compared against old pwd
			// action=update: here, we have to check whether submitted password is equal to password in db
			// if so, password won't be touched
			// if not, password will be encrypted
			// action=insert: password will be encrypted
			if ($my_rec['POST'][$my_rec['fields'][$f]['field_name']]) {
				if ($my_rec['action'] == 'insert') {
					$my_rec['POST'][$my_rec['fields'][$f]['field_name']] 
						= $zz_conf['password_encryption']($my_rec['POST'][$my_rec['fields'][$f]['field_name']]);
				} elseif ($my_rec['action'] == 'update') {
					if (!isset($my_rec['POST'][$my_rec['fields'][$f]['field_name'].'--old'])
					|| ($my_rec['POST'][$my_rec['fields'][$f]['field_name']] != $my_rec['POST'][$my_rec['fields'][$f]['field_name'].'--old']))
						$my_rec['POST'][$my_rec['fields'][$f]['field_name']] 
							= $zz_conf['password_encryption']($my_rec['POST'][$my_rec['fields'][$f]['field_name']]);
				}
			}
			break;
		case 'password_change':
			//	change encrypted password
			$pwd = false;
			if ($my_rec['POST'][$my_rec['fields'][$f]['field_name']] 
				AND $my_rec['POST'][$my_rec['fields'][$f]['field_name'].'_new_1']
				AND $my_rec['POST'][$my_rec['fields'][$f]['field_name'].'_new_2']) {
				$my_sql = $my_rec['fields'][$f]['sql_password_check'].$my_rec['id']['value'];
				$pwd = zz_check_password($my_rec['POST'][$my_rec['fields'][$f]['field_name']], 
					$my_rec['POST'][$my_rec['fields'][$f]['field_name'].'_new_1'], 
					$my_rec['POST'][$my_rec['fields'][$f]['field_name'].'_new_2'], $my_sql);
			} else {
				$zz_error[] = array(
					'msg' => zz_text('Please enter your current password and twice your new password.'),
					'level' => E_USER_NOTICE
				);
			}
			if ($pwd) $my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $pwd;
			else { 
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
			break;
		case 'select':
			//	check select /// workwork
			if (isset($_POST['zz_check_select']) 
				&& (in_array($my_rec['fields'][$f]['field_name'], $_POST['zz_check_select']) 
					OR (in_array($table_name.'['.$rec.']['.$my_rec['fields'][$f]['field_name'].']', $_POST['zz_check_select'])))
					// check only for 0, might be problem, but 0 should always be there
				&& $my_rec['POST'][$my_rec['fields'][$f]['field_name']]) { // if null -> accept it
				$my_rec = zz_check_select($my_rec, $f, $zz_conf['max_select']);
			}
			//	check for correct enum values
			if (isset($my_rec['fields'][$f]['enum'])) {
				if ($my_rec['POST'][$my_rec['fields'][$f]['field_name']]) {
					if (!$tempvar = zz_check_enumset($my_rec['POST'][$my_rec['fields'][$f]['field_name']], 
							$my_rec['fields'][$f], $db_table)) {
						$my_rec['validation'] = false;
						$my_rec['fields'][$f]['check_validation'] = false;
					} else $my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $tempvar;
				}
			}
			break;
		case 'date':
			//	internationalize date!
			if (!$my_rec['POST'][$my_rec['fields'][$f]['field_name']]) break;
			// submit to datum_int only if there is a value, else return 
			// would be false and validation true!
			if ($my_date = datum_int($my_rec['POST'][$my_rec['fields'][$f]['field_name']]))
				$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $my_date;
			else {
				//echo $my_rec['POST'][$my_rec['fields'][$f]['field_name']].'<br>';
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
			break;
		case 'time':
			//	validate time
			if ($my_rec['POST'][$my_rec['fields'][$f]['field_name']]) {
				if ($my_time = validate_time($my_rec['POST'][$my_rec['fields'][$f]['field_name']]))
					$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $my_time;
				else {
					//echo $my_rec['POST'][$my_rec['fields'][$f]['field_name']].'<br>';
					$my_rec['fields'][$f]['check_validation'] = false;
					$my_rec['validation'] = false;
				}
			}
			break;
		case 'unix_timestamp':
			//	convert unix_timestamp, if something was posted
			if (!$my_rec['POST'][$my_rec['fields'][$f]['field_name']]) break;
			if ($my_rec['POST'][$my_rec['fields'][$f]['field_name']]) {
				$my_date = strtotime($my_rec['POST'][$my_rec['fields'][$f]['field_name']]); 
				if ($my_date AND $my_date != -1) 
					// strtotime converts several formats, returns -1 if value
					// is not convertable
					$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $my_date;
				elseif (preg_match('/^[0-9]+$/', $my_rec['POST'][$my_rec['fields'][$f]['field_name']])) 
					// is already timestamp, does not work with all integer 
					// values since some of them are converted with strtotime
					$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $my_rec['POST'][$my_rec['fields'][$f]['field_name']];
				else {
					$my_rec['fields'][$f]['check_validation'] = false;
					$my_rec['validation'] = false;
				}
			} else {
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
			if ($my_rec['POST'][$my_rec['fields'][$f]['field_name']]) {
				if (!$tempvar = zz_check_url($my_rec['POST'][$my_rec['fields'][$f]['field_name']])) {
					$my_rec['fields'][$f]['check_validation'] = false;
					$my_rec['validation'] = false;
				} else $my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $tempvar;
			}
			break;
		case 'mail':
		case 'mail+name':
			//	check for correct mailaddress
			if ($my_rec['POST'][$my_rec['fields'][$f]['field_name']]) {
				if (!$tempvar = zz_check_mail($my_rec['POST'][$my_rec['fields'][$f]['field_name']], $type)) {
					$my_rec['fields'][$f]['check_validation'] = false;
					$my_rec['validation'] = false;
				} else $my_rec['POST'][$my_rec['fields'][$f]['field_name']] = $tempvar;
			}
			break;
		case 'upload_image':
			$input_filetypes = (isset($my_rec['fields'][$f]['input_filetypes']) 
				? (is_array($my_rec['fields'][$f]['input_filetypes']) 
					? array_values($my_rec['fields'][$f]['input_filetypes'])
					: array($my_rec['fields'][$f]['input_filetypes']))
				: array());
			if (!zz_upload_check($my_rec['images'][$f], $my_rec['action'], $zz_conf, $input_filetypes, $rec)) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
				if (is_array($my_rec['images'][$f])) foreach ($my_rec['images'][$f] as $key => $image) {
					if (is_numeric($key) AND !empty($image['error'])) {
						foreach ($image['error'] as $error) {
							$zz_error['validation']['incorrect_values'][] = array(
								'field_name' => $my_rec['fields'][$f]['field_name'],
								'msg' => $error
							);
						}
					}
				}
			}
			$my_rec['fields'][$f]['in_sql_query'] = false;
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
		if (!empty($my_rec['fields'][$f]['post_validation'])) {
			$values = $my_rec['fields'][$f]['post_validation']($my_rec['POST'][$my_rec['fields'][$f]['field_name']]);
			if ($values == -1) {
				// in this case, the function explicitly says:
				// record is invalid, so delete values
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			} elseif ($values) {
				// ok, get these values and save them for later use 
				foreach ($values as $key => $value) {
					$my_rec['extra'][$my_rec['fields'][$f]['field_name'].'_'.$key] = $value; 
				}
			}
		}

		//	remove entries which are for input only
		if (!empty($my_rec['fields'][$f]['input_only'])) {
			$my_rec['fields'][$f]['in_sql_query'] = false;
			if (!empty($my_rec['fields'][$f]['required']) 
				AND empty($my_rec['POST'][$my_rec['fields'][$f]['field_name']])) {
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['validation'] = false;
			}
		}
		if (!$my_rec['fields'][$f]['in_sql_query'])	continue;

	//	validation

	//	check whether is false but must not be NULL
		if (!isset($my_rec['POST'][$my_rec['fields'][$f]['field_name']])) {
			// no set = must be error
			if ($my_rec['fields'][$f]['type'] == 'foreign_key' 
				OR $my_rec['fields'][$f]['type'] == 'translation_key'
				OR $my_rec['fields'][$f]['type'] == 'detail_key') {
				// foreign key will always be empty but most likely also be required.
				// f. key will be added by script later on (because sometimes it is not known yet)
				// do nothing, leave $my_rec['validation'] as it is
			} elseif ($my_rec['fields'][$f]['type'] == 'timestamp') {
				// timestamps will be set to current date, so no check is necessary
				// do nothing, leave $my_rec['validation'] as it is
			} elseif (!isset($my_rec['fields'][$f]['set']) AND !isset($my_rec['fields'][$f]['set_sql'])
				 AND !isset($my_rec['fields'][$f]['set_folder']))
				$my_rec['validation'] = false;
			elseif (!zz_check_for_null($my_rec['fields'][$f]['field_name'], $db_table)) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
			} elseif (!empty($my_rec['fields'][$f]['required'])) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
			}
		} elseif(!$my_rec['POST'][$my_rec['fields'][$f]['field_name']] 
			AND empty($my_rec['fields'][$f]['null'])
			AND empty($my_rec['fields'][$f]['null_string'])
			AND $my_rec['fields'][$f]['type'] != 'timestamp')
			if (!zz_check_for_null($my_rec['fields'][$f]['field_name'], $db_table)) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
			} elseif (!empty($my_rec['fields'][$f]['required'])) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
			}

	//	check against forbidden strings
		if (!empty($my_rec['fields'][$f]['validate'])
			AND !empty($my_rec['POST'][$my_rec['fields'][$f]['field_name']])) {
			if ($msg = zz_check_validate($my_rec['POST'][$my_rec['fields'][$f]['field_name']], $my_rec['fields'][$f]['validate'])) {
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
 * validates input against a set of rules
 *
 * @param string $value value entered in form
 * @param array $validate defines against what to validate 
 * @return mixed false: everything is okay, string: error message
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_check_validate($value, $validate) {
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

	$possible_upload_fields = array('date', 'time', 'text', 'memo', 'hidden');
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
				foreach ($myv as $v_var) {
					if (substr($v_var, -1) == ']') $v_var = substr($v_var, 0, -1);
					$v_arr[] = $v_var;
				}
				$key1 = '$images[0][\'upload\'][\''.implode("']['", $v_arr).'\']';
				eval('$myval = (isset('.$key1.') ? '.$key1.': false);');
				if (!$myval) {
					$key1 = '$images[0][\''.implode("']['", $v_arr).'\']';
					eval('$myval = (isset('.$key1.') ? '.$key1.': false);');
				}
			} else {
				$myval = (!empty($images[$v])) 
					? $images[$v] // take value from upload-array
					: (!empty($images[0]['upload'][$v]) ? $images[0]['upload'][$v] : ''); // or take value from first sub-image
			}
			if ($myval && !empty($field['upload_sql'])) {
				$sql = $field['upload_sql'].'"'.$myval.'"';
				$myval = zz_db_fetch($sql, '', 'single value');
				if (!$myval) $myval = ''; // string, not array
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

?>