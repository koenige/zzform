<?php 

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2004-2010
// Miscellaneous functions


/**
 * error logging for zzform()
 * will display error messages for the current user on HTML webpage
 * depending on settings, will log errors in logfile and/or send errors by mail
 *
 * @global array $zz_error
 * 		$zz_error[]['msg'] message that always will be sent back to browser
 * 		$zz_error[]['msg_dev'] message that will be sent to browser, log and mail, 
 * 			depending on settings
 * 		$zz_error[]['level'] for error level: currently implemented:
 * 			- E_USER_ERROR: critical error, action could not be finished,
 *				unrecoverable error
 * 			- E_USER_WARNING: error, we need some extra user input
 * 			- E_USER_NOTICE: some default settings will be used because user 
 * 				input was not enough; e. g. date formats etc.
 * 		$zz_error[]['mysql_errno'] mySQL: error number from mysql_errno()
 * 		$zz_error[]['mysql'] mySQL: error message from mysql_error()
 * 		$zz_error[]['query'] SQL-Query
 * @global array $zz_conf
 *		$zz_conf['error_log']['notice'], $zz_conf['error_log']['warning'], 
 * 		$zz_conf['error_log']['error'] = path to error_log, default from php.ini
 * 		$zz_conf['error_handling'] = value for admin error logging
 * 			- false: no output, just write into log if set
 * 			- 'mail': send admin errors via mail
 * 			- 'output': send admin erros via html
 * 		$zz_conf['error_mail_to'],  $zz_conf['error_mail_from'] - mail addresses
 * @return mixed bool false or string HTML output for user
 */
function zz_error() {
	global $zz_conf;
	global $zz_error;	// we need this global, because it's global everywhere, 
						// so we can clear the variable here
	
	$user_output = array();
	$admin_output = array();
	$log_output = array();
	$mail_output = array();
	$return = 'html';
	unset($zz_error['error']); // we don't need this here
	
	if (empty($zz_conf['user'])) $zz_conf['user'] = zz_text('No user');
	$user = ' ['.zz_text('User').': '.$zz_conf['user'].']';

	// browse through all errors
	foreach ($zz_error as $key => $error) {
		// initialize error_level
		if (empty($error['level'])) $error['level'] = '';

		// initialize and translate error messages
		$error['msg'] = (!empty($error['msg']) ? zz_text(trim($error['msg'])) : '');
		$error['msg_dev'] = (!empty($error['msg_dev']) ? zz_text(trim($error['msg_dev'])) : '');

		$user_output[$key] = false;
		$admin_output[$key] = false;

		if (!empty($error['mysql_errno'])) {
			switch($error['mysql_errno']) {
			case 1062:
				$error['msg'] = zz_text('Duplicate entry').'<br>'.$error['msg'];
				/*
					TODO:
					1. get table_name
					2. parse: Duplicate entry '1-21' for key 2: (e.g. with preg_match)
					$indices = false;
					$sql = 'SHOW INDEX FROM ...';
					$keys = zz_db_fetch($sql, '...')
					if ($keys) {
						// 3. get required key, field_names
					}
					// 4. get title-values from zz['field'], display them
					// 5. show wrong values, if type select: show values after select ...
				*/
				break;
			default:
				$error['msg'] = zz_text('database-error').'<br>'.$error['msg'];
			}
		}

		switch ($error['level']) {
		case E_USER_ERROR:
			if (!$error['msg']) $user_output[$key] .= zz_text('An error occured.'
				.' We are working on the solution of this problem. '
				.'Sorry for your inconvenience. Please try again later.');
			$level = 'error';
			$return = 'exit'; // get out of this function immediately
			break;

		default:
		case E_USER_WARNING:
			$level = 'warning';
			break;

		case E_USER_NOTICE:
			$level = 'notice';
			break;
		}

		// User output
		$user_output[$key] .= $error['msg'];

		// Admin output
		if (!empty($error['msg_dev'])) 
			$admin_output[$key] .= $error['msg_dev'].'<br>';
		if (!empty($error['mysql'])) 
			$admin_output[$key] .= $error['mysql'].':<br>';
		if (!empty($error['query'])) 
			$admin_output[$key] .= preg_replace("/\s+/", " ", $error['query']).'<br>';
		if ($admin_output[$key] AND $error['msg'])
			$admin_output[$key] = $error['msg'].'<br>'.$admin_output[$key];
		elseif (!$admin_output[$key])
			$admin_output[$key] = $error['msg'];

		// Log output
		$log_output[$key] = trim(html_entity_decode($admin_output[$key]));
		$log_output[$key] = str_replace('<br>', "\n\n", $log_output[$key]);
		$log_output[$key] = str_replace('<br class="nonewline_in_mail">', "; ", $log_output[$key]);
		$log_output[$key] = strip_tags($log_output[$key]);
		// reformat log output
		if (!empty($zz_conf['error_log'][$level]) AND $zz_conf['log_errors']) {
			$error_line = '['.date('d-M-Y H:i:s').'] zzform '.ucfirst($level)
				.': '.preg_replace("/\s+/", " ", $log_output[$key]);
			$error_line = substr($error_line, 0, $zz_conf['log_errors_max_len'] -(strlen($user)+1)).$user."\n";
			error_log($error_line, 3, $zz_conf['error_log'][$level]);
		}
		// Mail output
		if (isset($zz_conf['error_mail_level']) AND in_array($level, $zz_conf['error_mail_level']))
			$mail_output[$key] = $log_output[$key];

		// Heading
		if (!$user_output[$key]) 
			unset($user_output[$key]); // there is nothing, so nothing will be shown
		elseif ($level == 'error' OR $level == 'warning')
			$user_output[$key] = '<strong>'.zz_text('Warning!').'</strong> '.$user_output[$key];
		if ($admin_output[$key] AND ($level == 'error' OR $level == 'warning'))		
			$admin_output[$key] = '<strong>'.zz_text('Warning!').'</strong> '.$admin_output[$key];
		
	}
	
	// mail errors if said to do so
	if (!empty($zz_conf['error_handling']) AND $zz_conf['error_handling'] == 'mail' 
		AND $zz_conf['error_mail_to']
		AND count($mail_output)) {
		$mailtext = implode("\n\n", $mail_output);
		$mailtext = sprintf(zz_text('The following error(s) occured in project %s:'), $zz_conf['project'])."\n\n".$mailtext;
		$mailtext .= "\n\n-- \nURL: http://".$_SERVER['SERVER_NAME']
			.$_SERVER['REQUEST_URI']
			."\nIP: ".$_SERVER['REMOTE_ADDR']
			."\nBrowser: ".$_SERVER['HTTP_USER_AGENT'];		
		if ($zz_conf['user'])
			$mailtext .= "\nUser: ".$zz_conf['user'];
		mail ($zz_conf['error_mail_to'], '['.$zz_conf['project'].'] '
			.zz_text('Error during database operation'), 
			$mailtext, 'MIME-Version: 1.0
Content-Type: text/plain; charset='.$zz_conf['character_set'].'
Content-Transfer-Encoding: 8bit
From: '.$zz_conf['error_mail_from']);
	// TODO: check what happens with utf8 mails
	} elseif ((!empty($zz_conf['error_handling']) AND $zz_conf['error_handling'] == 'output')
		OR empty($zz_conf['error_handling'])) {
		$user_output = $admin_output;
	}

	$zz_error = array(); // Went through all errors, so we do not need them anymore
	$zz_error['error'] = false;

	if ($return == 'exit') {
		$zz_error['error'] = true;
		global $zz;
		if (empty($zz['output'])) $zzform_id = true;
		else $zzform_id = false;
		$zz['output'] .= ($zzform_id ? '<div id="zzform">'."\n" : '')
			.'<div class="error">'.implode('<br><br>', $user_output).'</div>'."\n"
			.($zzform_id ? '</div>'."\n" : '');
		return false;
	}

	if (!count($user_output)) return false;
	$user_output = '<div class="error">'.implode('<br><br>', $user_output).'</div>'."\n";
	return $user_output;
}


function zz_form_heading($string) {
	$string = str_replace('_', ' ', $string);
	$string = ucfirst($string);
	return $string;
}

function unhtmlspecialchars( $string ) {
	$string = str_replace ( '&amp;', '&', $string );
	$string = str_replace ( '&#039;', '\'', $string );
	$string = str_replace ( '&quot;', '\"', $string );
	$string = str_replace ( '&lt;', '<', $string );
	$string = str_replace ( '&gt;', '>', $string );
	return $string;
}
   
function hours($seconds) {
	$hours = 0;
	$minutes = 0;
	while ($seconds >= 60) {
		$seconds -= 60;
		$minutes++;
	}
	while ($minutes >= 60) {
		$minutes -= 60;
		$hours++;
	}
	if (strlen($minutes) == 1) $minutes = '0'.$minutes;
	$time = $hours.':'.$minutes;
	return $time;
}

function field_in_where($field, $values) {
	$where = false;
	foreach (array_keys($values) as $value)
		if ($value == $field) $where = true;
	return $where;
}

/** 
 * checks maximum field length in MySQL database table
 * 
 * @param string $field	field name
 * @param string $table	table name [i. e. db_name.table]
 * @return maximum length of field or false if no field length is set
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_check_maxlength($field, $type, $table) {
	if (!$field) return false;
	// just if it's a field with a field_name
	// for some field types it makes no sense to check for maxlength
	$dont_check = array('image', 'display', 'timestamp', 'hidden', 'foreign_key',
		'select', 'id', 'date', 'time');
	if (in_array($type, $dont_check)) return false;

	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if (substr($table, 0, 1) != '`' AND substr($table, -1) != '`') {
		$table = '`'.str_replace('.', '`.`', $table).'`';
	}
	$sql = 'SHOW COLUMNS FROM '.$table.' LIKE "'.$field.'"';
	$maxlength = false;
	$field_def = zz_db_fetch($sql);
	if ($field_def) {
		preg_match('/\((\d+)\)/s', $field_def['Type'], $my_result);
		if (isset($my_result[1])) $maxlength = $my_result[1];
	}
	if ($zz_conf['modules']['debug']) zz_debug($type.($maxlength ? '-'.$maxlength : ''));
	return zz_return($maxlength);
}

/**
 * checks whether an input is a number or a simple calculation
 * 
 * @param string $number	number or calculation, may contain +-/* 0123456789 ,.
 * @return string number, with calculation performed / false if incorrect format
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function check_number($number) {
	// remove whitespace, it's nice to not have to care about this
	$number = trim($number);
	$number = str_replace(' ', '', $number);
	// first charater must not be / or *
	// NULL: possible feature: return doubleval $number to get at least something
	if (!preg_match('~^[0-9.,+-][0-9.,\+\*\/-]*$~', $number)) return NULL;
	// put a + at the beginning, so all parts with real numbers start with 
	// arithmetic symbols
	if (substr($number, 0, 1) != '-') $number = '+'.$number;
	preg_match_all('~[-+/*]+[0-9.,]+~', $number, $parts);
	$parts = $parts[0];
	// go through all parts and solve the '.' and ',' problem
	foreach ($parts as $index => $part) {
		if ($dot = strpos($part, '.') AND $comma = strpos($part, ','))
			if ($dot > $comma) $parts[$index] = str_replace(',', '', $part);
			else {
				$parts[$index] = str_replace('.', '', $part);
				$parts[$index] = str_replace(',', '.', $parts[$index]);
			}
		// must not: enter values like 1,000 and mean 1000!
		elseif (strstr($part, ',')) $parts[$index] = str_replace(',', '.', $part);
	}
	eval('$sum = '.implode('', $parts).';');
	return $sum;
}

/**
 * sets class attribute if neccessary
 * 
 * @param array $field
 * @param array $values
 * @return string HTML-output with class="..."
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function check_if_class ($field, $values) {
	$class = false;
	if (isset($field['level'])) $class[] = 'level'.$field['level'];
	if ($field['type'] == 'id' && empty($field['show_id'])) $class[] = 'recordid';
	elseif ($field['type'] == 'number' OR $field['type'] == 'calculated')
		$class[] = 'number';
	if (!empty($_GET['order'])) 
		if (!empty($field['field_name']) && $field['field_name'] == $_GET['order'])
			$class[] = 'order';
		elseif (!empty($field['display_field']) && $field['display_field'] == $_GET['order'])
			$class[] = 'order';
	if ($values)
		if (isset($field['field_name'])) // does not apply for subtables!
			if (field_in_where($field['field_name'], $values)) 
				$class[] = 'where';
	if (!empty($field['class'])) $class[] = $field['class'];
	if ($class) return (' class="'.implode(' ',$class).'"');
	else return false;
}

/** 
 * formats timestamp to readable date
 * 
 * @param string $timestamp
 * @return string reformatted date
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function timestamp2date($timestamp) {
	if ($timestamp) {
		if (strstr($timestamp, '-')) { // new timestamp format, mysql 4 datetime
			$date = substr($timestamp,8,2).'.'.substr($timestamp,5,2).'.'.substr($timestamp, 0,4).' ';
			$date.= substr($timestamp,11,2).':'.substr($timestamp,14,2).':'.substr($timestamp,17,2);
		} else {
			$date = substr($timestamp,6,2).'.'.substr($timestamp,4,2).'.'.substr($timestamp, 0,4).' ';
			$date.= substr($timestamp,8,2).':'.substr($timestamp,10,2).':'.substr($timestamp,12,2);
		}
		return $date;
	} else return false;
}

/** 
 * Reformats GET variable into URL query
 * 
 * @param array $get
 * @param string $which
 * @return string URL query
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_get_to_array($get, $which) {
	$extras = array();
	foreach (array_keys($get) as $where_key)
		$extras[] = $which.'['.$where_key.']='.urlencode($get[$where_key]);
	$extras = implode('&amp;', $extras);
	return $extras;
}

/** 
 * Creates link or HTML img from path
 * 
 * @param array $path
 *		'root', 'webroot', 'field1...fieldn', 'string1...stringn', 'mode1...n'
 * @param array $record current record
 * @param string $type (optional) link or image, image will be returned in
 *		<img src="" alt="">
 * @return string URL or HTML-code for image
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_makelink($path, $record, $type = 'link') {
	if (!$record) return false;
	if (!$path) return false;

	$url = '';
	$modes = array();
	$path_full = '';		// absolute path in filesystem
	$path_web = '';			// relative path on website
	$check_against_root = false;

	if ($type == 'image') {
		$alt = zz_text('no_image');
	}
	foreach ($path as $part => $value) {
		if (!$value) continue;
		if (substr($part, 0, 4) == 'root') {
			$check_against_root = true;
			// root has to be first element, everything before will be ignored
			$path_full = $value;
			if (substr($path_full, -1) != '/')
				$path_full .= '/';
		} elseif (substr($part, 0, 7) == 'webroot') {
			// web might come later, ignore parts before for web and add them
			// to full path
			$path_web = $value;
			$path_full .= $url;
			$url = '';
		} elseif (substr($part, 0, 5) == 'field') {
			if (!isset($record[$value])) {
				// we don't have that field, so we can't build the path
				// and return with nothing
				return false;
			}
			$content = $record[$value];
			if ($modes) {
				foreach ($modes as $mode) {
					if (!function_exists($mode)) {
						global $zz_error;
						$zz_error[] = array(
							'msg_dev' => sprintf(zz_text('Configuration Error: mode with not-existing function "%s"'), $mode),
							'level' => E_USER_ERROR
						);
						return false;
					}
					$content = $mode($content);
				}
				$modes = array();
			}
			$url .= $content;
			if ($type == 'image') {
				$alt = zz_text('File: ').$record[$value];
			}
		} elseif (substr($part, 0, 6) == 'string') {
			$url .= $value;
		} elseif (substr($part, 0, 4) == 'mode') {
			$modes[] = $value;
		}
	}

	if ($check_against_root) { // check whether file exists
		if (!file_exists($path_full.$url)) { // file does not exist = false
			return false;
		}
		if ($type == 'image'
			AND (!filesize($path_full.$url) 	// filesize is 0 = looks like error
			OR !getimagesize($path_full.$url))) { // getimagesize test whether it's an image
			return false;
		}
	}
	$url = $path_web.$url;
	if ($type != 'image') return $url;
	if (!$url) return false;
	$img = '<img src="'.$url.'" alt="'.$alt.'" class="thumb">';
	return $img;
}

function zz_show_more_actions($more_actions, $more_actions_url, $more_actions_base, 
		$more_actions_target, $more_actions_referer, $id, $line = false) {
	if (!function_exists('forceFilename')) {
		echo zz_text('Function forceFilename() required but not found! It is as well '
			.'possible that <code>$zz_conf[\'character_set\']</code> is incorrectly set.');
		exit;
	}
	global $zz_conf;
	$act = false;
	foreach ($more_actions as $key => $new_action) {
		$output = false;
		if ($more_actions_base) $new_action_url = $more_actions_base[$key];
		else $new_action_url = strtolower(forceFilename($new_action));
		$output.= '<a href="'.$new_action_url;
		if (!empty($more_actions_url))
			if (isset($more_actions_url[$key]) && is_array($more_actions_url[$key])) {
			// values are different for each key
				foreach (array_keys($more_actions_url[$key]) as $part_key)
					if (substr($part_key, 0, 5) == 'field')
						$output.= $line[$more_actions_url[$key][$part_key]];
					else
						$output.= $more_actions_url[$key][$part_key];
			} elseif (is_array($more_actions_url)) 
			// all values are the same
				foreach (array_keys($more_actions_url) as $part_key)
					if (substr($part_key, 0, 5) == 'field')
						$output.= $line[$more_actions_url[$part_key]];
					else
						$output.= $more_actions_url[$part_key];
			else
				$output.= $more_actions_url;
		else $output.= '.php?id=';
		if (!isset($more_actions_url) OR !is_array($more_actions_url)) $output.= $id;
		$output.= ($more_actions_referer ? '&amp;referer='.urlencode($_SERVER['REQUEST_URI']) : '')
			.'"'
			.(!empty($more_actions_target) ? ' target="'.$more_actions_target.'"' : '')
			.'>'.($zz_conf['multilang_fieldnames'] ? zz_text($new_action) : $new_action).'</a>';
		$act[] = $output;
	}
	$output = implode('&nbsp;| ', $act);
	return $output;
}

function zz_draw_select($line, $id_field_name, $record, $field, $hierarchy, $level, $parent_field_name, $form, $zz_conf_record) {
	// initialize variables
	if (!isset($field['sql_ignore'])) $field['sql_ignore'] = array();
	$output = '';
	$i = 1;
	$details = array();
	if ($form == 'reselect')
		$output .= '<input type="text" size="'.(!empty($field['size_select_too_long']) ? $field['size_select_too_long'] : 32)
			.'" name="'.$field['f_field_name'].'" value="';
	elseif ($form) {
		$output .= '<option value="'.$line[$id_field_name].'"';
		if ($record AND $line[$id_field_name] == $record[$field['field_name']]) {
			$output.= ' selected="selected"';
		} elseif (!empty($field['disabled_ids']) 
			AND is_array($field['disabled_ids'])
			AND in_array($line[$id_field_name], $field['disabled_ids'])) {
			$output.= ' disabled="disabled"';
		}
		
		if ($hierarchy) $output.= ' class="level'.$level.'"';
		$output.= '>';
	}
	if (!isset($field['show_hierarchy'])) $field['show_hierarchy'] = false;
	if (empty($field['sql_index_only'])) {
		foreach (array_keys($line) as $key) {	// $i = 1: field['type'] == 'id'!
			if ($key != $parent_field_name 
				&& !is_numeric($key) 
				&& $key != $field['show_hierarchy'] 
				&& !in_array($key, $field['sql_ignore'])
			) {
				$line[$key] = htmlspecialchars($line[$key]);
				if ($i > 1 AND $line[$key]) $details[] = (strlen($line[$key]) > $zz_conf_record['max_select_val_len']) 
					? (mb_substr($line[$key], 0, $zz_conf_record['max_select_val_len']).'...') : $line[$key]; // cut long values
				$i++;
			}
		}
	} else {
		$key = $id_field_name;
	}
	if (!$details) $details = $line[$key]; // if only the id key is in the query, eg. show databases
	if (is_array($details)) $details = implode(' | ', $details);
	$output.= strip_tags($details); // remove tags, leave &#-Code as is
	$level++;
	if ($form == 'reselect') {
		$output.= ' ">'; // extra space, so that there won't be a LIKE operator that this value will be checked against!
	} elseif ($form) {
		$output.= '</option>'."\n";
		if ($hierarchy && isset($hierarchy[$line[$id_field_name]]))
			foreach ($hierarchy[$line[$id_field_name]] as $secondline) {
				if (!empty($field['group'])) {
					unset($secondline[$field['group']]); // not needed anymore
				}
				$output.= zz_draw_select($secondline, $id_field_name, $record, $field, $hierarchy, $level, $parent_field_name, 'form', $zz_conf_record);
			}
	}
	return $output;
}

function htmlchars($string) {
	$string = str_replace('&amp;', '&', htmlspecialchars($string));
	//$string = str_replace('&quot;', '"', $string); // does not work 
	return $string;
}

/**
 * modifies SQL query according to search results
 *
 * @param array $fields
 * @param string $sql
 * @param string $table
 * @param string $main_id_fieldname
 * @global array $zz_conf main configuration variables
 * @global array $zz_error
 * @return string $sql (un-)modified SQL query
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo if there are subtables, part of this functions code is run redundantly
 */
function zz_search_sql($fields, $sql, $table, $main_id_fieldname) {
	// no changes if there's no query string
	if (empty($_GET['q'])) return $sql;

	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$addscope = true;
	// fields that won't be used for search
	$unsearchable = array('image', 'calculated', 'timestamp', 'upload_image', 'option'); 
	if ($zz_conf['modules']['debug']) zz_debug("search query", $sql);

	// there is something, process it.
	$searchword = $_GET['q'];
	// search: look at first character to change search method
	if (substr($searchword, 0, 1) == '>') {
		$searchword = trim(substr($searchword, 1));
		$searchop = '>';
		$searchstring = ' '.$searchop.' "'.zz_db_escape($searchword).'"';
	} elseif (substr($searchword, 0, 1) == '<') {
		$searchword = trim(substr($searchword, 1));
		$searchop = '<';
		$searchstring = ' < "'.zz_db_escape(trim(substr($searchword, 1))).'"';
	} elseif (substr($searchword, 0, 1) == '-' AND strstr($searchword, ' ')) {
		$searchword = trim(substr($searchword, 1));
		$searchword = explode(" ", $searchword);
		$searchop = 'BETWEEN';
		$searchstring = $_GET['scope'].' >= "'.zz_db_escape(trim($searchword[0]))
			.'" AND '.$_GET['scope'].' <= "'.zz_db_escape(trim($searchword[1])).'"';
		$addscope = false;
	} elseif (preg_match('/q\d(.)[0-9]{4}/i', $searchword, $separator) AND !empty($_GET['scope'])) {
		// search for quarter of year
		$searchword = trim(substr($searchword, 1));
		$searchword = explode($separator[1], $searchword);
		$searchop = false;
		$searchstring = ' QUARTER('.$_GET['scope'].') = "'.trim($searchword[0])
			.'" AND YEAR('.$_GET['scope'].') = "'.trim($searchword[1]).'"';
		$addscope = false;
	} elseif ($searchword == '!NULL') {
		$addscope = false;
		$searchstring = ' !ISNULL('.$_GET['scope'].')';
	} elseif ($searchword == 'NULL') {
		$addscope = false;
		$searchstring = ' ISNULL('.$_GET['scope'].')';
	} else {
		$searchop = 'LIKE';
		// first slash will be ignored, this is used to escape reserved characters
		if (substr($searchword, 0, 1) == '\\') $searchword = substr($searchword, 1);
		$searchstring = ' '.$searchop.' "%'.zz_db_escape($searchword).'%"';
	}

	// Search with q and scope
	// so look only at one field!
	if (!empty($_GET['scope'])) {
		$scope = false;
		$fieldtype = false;
		foreach ($fields as $field) {
		// todo: check whether scope is in_array($searchfields)
			if (empty($field)) continue;
			if (empty($field['type'])) $field['type'] = 'text';
			if (empty($field['field_name'])) $field['field_name'] = '';
			if (!in_array($field['type'], $unsearchable) && empty($field['exclude_from_search'])) {
				if (!isset($field['sql']) && $_GET['scope'] == $field['field_name'] 
					OR $_GET['scope'] == $table.'.'.$field['field_name']
					OR (isset($field['display_field']) && $_GET['scope'] == $field['display_field'])) {
					$scope = $_GET['scope'];
					$fieldtype = $field['type'];
					if (!empty($field['search'])) $scope = $field['search'];
				}
			}
		}
		// allow searching with strtotime, but do not convert years (2000)
		// or year-month (2004-12)
		if (!is_array($searchword) AND // no array
			!preg_match('/^\d{1,4}-*\d{0,2}-*\d{0,2}$/', trim($searchword))) 
			$timesearch = strtotime($searchword);
		else $timesearch = false;
		if ($addscope)
			$sql_search_part = $scope.$searchstring; // default here
		else
			$sql_search_part = $searchstring; // default here
		switch ($fieldtype) {
		case 'datetime':
			if ($timesearch)
				$sql_search_part = $scope.' '.$searchop.' "'.date('Y-m-d', $timesearch).'%"';
			break;
		case 'time':
			if ($timesearch)
				$sql_search_part = $scope.' '.$searchop.' "'.date('H:i:s', $timesearch);
			break;
		case 'date':
			if ($timesearch)
			$sql_search_part = $scope.' '.$searchop.' "'.date('Y-m-d', $timesearch).'%"';
			break;
		case '': // scope is false, fieldtype is false
			$sql_search_part = 'NULL';
			break;
		}
		$sql = zz_edit_sql($sql, 'WHERE', $sql_search_part);
		if ($zz_conf['modules']['debug']) zz_debug("end; search query", $sql);
		return $sql;
	}
	
	// no scope is set, so search with q
	// Look at _all_ fields
	$q_search = '';
	foreach ($fields as $index => $field) {
		// skip certain fields
		if (empty($field)) continue;
		if (!empty($field['exclude_from_search'])) continue;
		if (empty($field['type'])) $field['type'] = 'text';
		if (in_array($field['type'], $unsearchable)) continue;


		// check what to search for
		$fieldname = false;
		if (isset($field['search'])) {
			$fieldname = $field['search'];
		} elseif (isset($field['display_field'])) {
			$fieldname = $field['display_field'];
		} elseif ($field['type'] == 'subtable') {
			$foreign_key = '';
			foreach ($field['fields'] as $f_index => $subfield) {
				if (!empty($subfield['type']) AND $subfield['type'] == 'foreign_key') {
					$foreign_key = $subfield['field_name'];
					// do not search in foreign_key since this is the same
					// as the main record
					unset($field['fields'][$f_index]);
				}
			}
			if (!$foreign_key) {
				echo zz_text('Subtable definition is wrong. There must be a field which is defined as "foreign_key".');
				exit;
			}
			$subsql = zz_search_sql($field['fields'], $field['sql'], $field['table'], $main_id_fieldname);
			if ($ids = zz_db_fetch($subsql, $foreign_key, '', 'Search query for subtable.', E_USER_WARNING)) {
				$q_search[] = $table.'.'.$main_id_fieldname.' IN ('.implode(',', array_keys($ids)).')';
			}
		} elseif (!empty($field['field_name'])) {
			// standard: use table- and field name
			$fieldname = $table.'.'.$field['field_name'];
		}
		if ($fieldname) $q_search[] = $fieldname.$searchstring;
	}
	$q_search = '('.implode(' OR ', $q_search).')';
	$sql = zz_edit_sql($sql, 'WHERE', $q_search);

	if ($zz_conf['modules']['debug']) zz_debug("end; search query", $sql);
	return $sql;
}

/** 
 * Generates search form and link to show all records
 * 
 * @param array $fields			field definitions ($zz)
 * @param string $table			name of database table
 * @param int $total_rows		total rows in database selection
 * @param string $mode			db mode
 * @param string $count_rows	number of rows shown on html page
 * @return string $output		HTML output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_search_form($fields, $table, $total_rows, $mode, $count_rows) {
	global $zz_conf;
	// Search Form
	$search_form['top'] = false;
	$search_form['bottom'] = false;
	if (!$zz_conf['search']) return $search_form;

	$output = '';
	if ($total_rows OR isset($_GET['q'])) {
		// show search form only if there are records as a result of this query; 
		// q: show search form if empty search result occured as well
		$self = $zz_conf['url_self'];
		// fields that won't be used for search
		$unsearchable = array('image', 'calculated', 'subtable', 'timestamp', 'upload_image');
		$output = "\n".'<form method="GET" action="'.$self
			.'" id="zzsearch" accept-charset="'.$zz_conf['character_set'].'"><p>';
		if ($zz_conf['url_self_qs_base'].$zz_conf['url_self_qs_zzform']) { 
			// do not show edited record, limit, ...
			$unwanted_keys = array('q', 'scope', 'limit', 'this_limit', 'mode', 'id', 'add'); 
			$output .= zz_querystring_to_hidden(substr($zz_conf['url_self_qs_base']
				.$zz_conf['url_self_qs_zzform'], 1), $unwanted_keys);
			// remove unwanted keys from link
			$self .= zz_edit_query_string($zz_conf['url_self_qs_base']
				.$zz_conf['url_self_qs_zzform'], $unwanted_keys); 
		}
		$output.= '<input type="text" size="30" name="q"';
		if (isset($_GET['q'])) $output.= ' value="'.htmlchars($_GET['q']).'"';
		$output.= '>';
		$output.= '<input type="submit" value="'.zz_text('search').'">';
		$output.= ' '.zz_text('in').' ';	
		$output.= '<select name="scope">';
		$output.= '<option value="">'.zz_text('all fields').'</option>'."\n";
		foreach ($fields as $field) {
			if (in_array($field['type'], $unsearchable)) continue;
			if (!empty($field['exclude_from_search'])) continue;
			$fieldname = (isset($field['display_field']) && $field['display_field']) 
				? $field['display_field'] : $table.'.'.$field['field_name'];
			$output.= '<option value="'.$fieldname.'"';
			if (isset($_GET['scope']) AND $_GET['scope'] == $fieldname) 
				$output.= ' selected="selected"';
			$output.= '>'.strip_tags($field['title']).'</option>'."\n";
		}
		$output.= '</select>';
		if (!empty($_GET['q'])) {
			$output.= ' &nbsp;<a href="'.$self.'">'.zz_text('Show all records').'</a>';
		}
		$output.= '</p></form>'."\n";
	}

	if ($zz_conf['search'] === true) $zz_conf['search'] = 'bottom'; // default!
	switch ($zz_conf['search']) {
	case 'top':
		// show form on top only if there are records!
		if ($count_rows) $search_form['top'] = $output;
		break;
	case 'both':
		// show form on top only if there are records!
		if ($count_rows) $search_form['top'] = $output;
	case 'bottom':
	default:
		$search_form['bottom'] = $output;
	}
	return $search_form;
}

/**
 * takes GET parameter from URL query string and writes them into hidden input
 * fields to use in a form
 *
 * @param string $query_string URL query string
 * @param array $unwanted_keys (will be ignored)
 * @return HTML output containing hidden input tags
 * @see zz_search_form(), zz_print_multiarray()
 */
function zz_querystring_to_hidden($query_string, $unwanted_keys = array(), $level = 0) {
	$output = '';
	// parse_str just for first call of this function, not for recursive calls
	if (!$level) parse_str($query_string, $qp);
	$qp = zz_print_multiarray($qp);
	foreach ($qp as $line) {
		if (strstr($line['key'], '['))
			$top_key = substr($line['key'], 0, strpos($line['key'], '['));
		else
			$top_key = $line['key'];
		if (in_array($top_key, $unwanted_keys)) continue;
		$output.= '<input type="hidden" name="'.$line['key'].'" value="'.$line['value'].'">'."\n";
	}
	return $output;
}

/**
 * prints out a list of filters to click
 *
 * @param array $filter
 * @global array $zz_conf
 * @return string HTML output, all filters
 */
function zz_filter_selection($filter) {
	if (!is_array($filter)) return false;
	global $zz_conf;
	$self = $zz_conf['url_self'];
	// remove unwanted keys from link
	// do not show edited record, limit
	$unwanted_keys = array('q', 'scope', 'limit', 'this_limit', 'mode', 'id', 'add', 'filter');
	$qs = zz_edit_query_string($zz_conf['url_self_qs_base'].$zz_conf['url_self_qs_zzform'], $unwanted_keys);
	$output = '';
	$output .= '<div class="zzfilter">'."\n";
	$output .= '<dl>'."\n";
	foreach ($filter as $f) {
		$other_filters['filter'] = (!empty($_GET['filter']) ? $_GET['filter'] : array());
		unset($other_filters['filter'][$f['identifier']]);
		$qs = zz_edit_query_string($qs, array(), $other_filters);
		$output .= '<dt>'.zz_text('Selection').' '.$f['title'].':</dt>';
		// $f['selection'] might be empty if there's no record in the database
		if (!empty($f['selection'])) { 
			foreach ($f['selection'] as $id => $selection) {
				$is_selected = ((isset($_GET['filter'][$f['identifier']]) 
					AND $_GET['filter'][$f['identifier']] == $id))
					? true : false;
				if (!empty($f['default_selection']) AND $f['default_selection'] == $id) {
					// default selection does not need parameter
					$link = $self.$qs;
				} else {
					$link = $self.($qs ? $qs.'&amp;' : '?').'filter['.$f['identifier'].']='.$id;
				}
				$output .= '<dd>'
					.(!$is_selected ? '<a href="'.$link.'">' : '<strong>')
					.$selection
					.(!$is_selected ? '</a>' : '</strong>')
					.'</dd>'."\n";
			}
		} elseif (isset($_GET['filter'][$f['identifier']])) {
			// no filter selections are shown, but there is a current filter, so show this
			$output .= '<dd><strong>'.htmlspecialchars($_GET['filter'][$f['identifier']]).'</strong></dd>'."\n";
		}
		if (empty($f['default_selection'])) {
			$link = $self.$qs;
		} else {
			// there is a default selection, so we need a parameter = 0!
			$link = $self.($qs ? $qs.'&amp;' : '?').'filter['.$f['identifier'].']=0';
		}
		$output .= '<dd class="filter_all">&#8211;&nbsp;'
			.(isset($_GET['filter'][$f['identifier']]) ? '<a href="'.$link.'">' : '<strong>')
			.zz_text('all')
			.(isset($_GET['filter'][$f['identifier']]) ? '</a>' : '</strong>')
			.'&nbsp;&#8211;</dd>'."\n";
	}
	$output .= '</dl><br clear="all"></div>'."\n";
	return $output;
}

/** 
 * Removes unwanted keys from QUERY_STRING
 * 
 * @param string $query			query-part of URI
 * @param array $unwanted_keys	keys that shall be removed
 * @param array $new_keys		keys and values in pairs that shall be added or overwritten
 * @return string $string		New query string without removed keys
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_edit_query_string($query, $unwanted_keys = array(), $new_keys = array()) {
	$query = str_replace('&amp;', '&', $query);
	if (substr($query, 0, 1) == '?' OR substr($query, 0, 1) == '&')
		$query = substr($query, 1);
	parse_str($query, $queryparts);
	// remove unwanted keys from URI
	foreach (array_keys($queryparts) as $key) 
		if (in_array($key, $unwanted_keys)) 
			unset ($queryparts[$key]);
	// add new keys or overwrite existing keys
	foreach ($new_keys as $new_key => $new_value)
		$queryparts[$new_key] = $new_value; 
	// glue everything back together
	$query_string = http_build_query($queryparts);
	if ($query_string) return '?'.$query_string; // URL without unwanted keys
	else return false;
}

/**
 * if LIMIT is set, shows different pages for each $step records
 *
 * @param int $step = $zz_conf['limit'] how many records shall be shown on each page
 * @param int $this_limit = $zz_conf['this_limit'] last record no. on this page
 * @param int $count_rows total records ?
 * @param int $total_rows total records ? 
 * @param string $scope 'body', todo: 'head' (not yet implemented)
 * @global array $zz_conf
 *		url_self, url_self_qs_base, url_self_qs_zzform, limit_show_range
 * @return string HTML output
 * @todo
 * 	- <link rel="next">, <link rel="previous">
 */
function zz_limit($step, $this_limit, $count_rows, $total_rows, $scope = 'body') {
	global $zz_conf;
	
	$output = '';
	if (($this_limit && $count_rows >= $step OR $this_limit > $step) 
		AND ($step != $total_rows)) {
		$next = false;
		$prev = false;
		if ($total_rows) {
			// remove mode, id
			$unwanted_keys = array('mode', 'id', 'limit', 'add');
			$uri = $zz_conf['url_self'].zz_edit_query_string($zz_conf['url_self_qs_base'].$zz_conf['url_self_qs_zzform'], $unwanted_keys);
			$output .= '<ul class="pages">';
			$output .= '<li class="first">'.($zz_limitlink = limitlink(0, $this_limit, $step, $uri)).'|&lt;'.($zz_limitlink ? '</a>' : '').'</li>';
			$output .= '<li class="prev">'.($zz_limitlink = limitlink($this_limit-$step, $this_limit, 0, $uri)).'&lt;'.($zz_limitlink ? '</a>' : '').'</li>';
			$output .= '<li class="all">'.($zz_limitlink = limitlink(-1, $this_limit, 0, $uri)).zz_text('all').($zz_limitlink ? '</a>' : '').'</li>';
			$ellipsis_min = false;
			$ellipsis_max = false;
			$i_last = 0;
			if ($zz_conf['limit_show_range'] && $total_rows >= $zz_conf['limit_show_range']) {
				$i_start = $this_limit - ($zz_conf['limit_show_range']/2 + 2*$step);
				if ($i_start < 0) $i_start = 0;
				$i_end = $this_limit + ($zz_conf['limit_show_range'] + $step);
				if ($i_end > $total_rows -1) $i_end = $total_rows -1;
				$i_last = (ceil($total_rows/$step)*$step); // total_rows -1 because min is + 1 later on
			} else {
				$i_start = 0;
				$i_end = $total_rows -1; // total_rows -1 because min is + 1 later on
			}
			for ($i = $i_start; $i <= $i_end; $i = $i+$step) { 
				$range_min = $i+1;
				$range_max = $i+$step;
				if ($this_limit + 400 < $range_min) {
					if (!$ellipsis_max)
						$output .= $ellipsis_max = '<li>&hellip;</li>';
					continue;
				}
				if ($this_limit > $range_max + 400) {
					if (!$ellipsis_min)
						$output .= $ellipsis_min = '<li>&hellip;</li>';
					continue;
				}
				if ($range_max > $total_rows) $range_max = $total_rows;
				$output .= '<li>'.($zz_limitlink = limitlink($i, $this_limit, $step, $uri))
					.($range_min == $range_max ? $range_min: $range_min.'-'.$range_max) // if just one above the last limit show this numver only once
					.($zz_limitlink ? '</a>' : '').'</li>';
			}
			$limit_next = $this_limit+$step;
			if ($limit_next > $range_max) $limit_next = $i;
			if (!$i_last) $i_last = $i;
			$output .= '<li class="next">'.($zz_limitlink = limitlink($limit_next, $this_limit, 0, $uri)).'&gt;'.($zz_limitlink ? '</a>' : '').'</li>';
			$output .= '<li class="last">'.($zz_limitlink = limitlink($i_last, $this_limit, 0, $uri)).'&gt;|'.($zz_limitlink ? '</a>' : '').'</li>';
			$output .= '</ul>';
			$output .= '<br clear="all">';
		}
	}
	return $output;
}

function limitlink($i, $limit, $step, $uri) {
	global $zz_conf;
	if ($i == -1) {  // all records
		if (!$limit) return false;
		else $limit_new = 0;
	} else {
		$limit_new = $i + $step;
		if ($limit_new == $limit) return false; // current page!
		elseif (!$limit_new) return false; // 0 does not exist, means all records
	}
	$uriparts = parse_url($uri);
	if ($limit_new != $zz_conf['limit']) {
		if (isset($uriparts['query'])) $uri.= '&amp;';
		else $uri.= '?';
		$uri .= 'limit='.$limit_new;
	}
	return '<a href="'.$uri.'">';
}

/**
 * creates array for each detail table in $zz_tab
 *
 * @param array $field = $zz['fields'][$no] with subtable definition
 * @param array $main_tab = $zz_tab[0] for main record
 * @param int $tab = number of subtable
 * @param int $no = number of subtable definition in $zz['fields']
 * @global array $zz_conf
 *		'max_detail_records', 'min_detail_records'
 * @global array $_POST
 * @return array $my_tab = $zz_tab[$tab]
 *		'no', 'sql', 'sql_not_unique', 'keep_detailrecord_shown', 'db_name'
 *		'table', 'table_name', 'values', 'fielddefs', 'max_records', 
 *		'min_records', 'records_depend_on_upload', 
 *		'records_depend_on_upload_more_than_one', 'foreign_key_field_name',
 *		'translate_field_name', 'detail_key', 'tick_to_save', 'access'
 */
function zz_get_subtable($field, $main_tab, $tab, $no) {
	global $zz_conf;

	// basics for all subrecords of the same table
	$my_tab = array();

	// no in $zz['fields']
	$my_tab['no'] = $no;

	// SQL query
	$my_tab['sql'] = $field['sql'];
	if (empty($field['sql_not_unique'])) {
		$my_tab['sql_not_unique'] = false;
		$my_tab['keep_detailrecord_shown'] = false;
	} else {
		$my_tab['sql_not_unique'] = $field['sql_not_unique'];
		$my_tab['keep_detailrecord_shown'] = true;
	}

	// database and table name
	if (strstr($field['table'], '.')) {
		$table = explode('.', $field['table']);
		$my_tab['db_name'] = $table[0];
		$my_tab['table'] = $table[1];
	} else {
		$my_tab['db_name'] = $main_tab['db_name'];
		$my_tab['table'] = $field['table'];
	}
	$my_tab['table_name'] = $field['table_name'];
	
	// pre-set values
	$my_tab['values'] = (!empty($field['values']) ? $field['values'] : array());
	$my_tab['fielddefs'] = (!empty($field['fielddefs']) ? $field['fielddefs'] : array());

	// records
	$my_tab['max_records'] = (isset($field['max_records'])) 
		? $field['max_records'] : $zz_conf['max_detail_records'];
	$my_tab['min_records'] = (isset($field['min_records'])) 
		? $field['min_records'] : $zz_conf['min_detail_records'];
	$my_tab['records_depend_on_upload'] = (isset($field['records_depend_on_upload'])) 
		? $field['records_depend_on_upload'] : false;
	$my_tab['records_depend_on_upload_more_than_one'] = 
		(isset($field['records_depend_on_upload_more_than_one'])) 
		? $field['records_depend_on_upload_more_than_one'] : false;
	
	// foreign keys, translation keys
	$my_tab['foreign_key_field_name'] = (!empty($field['foreign_key_field_name']) 
		? $field['foreign_key_field_name'] 
		: $main_tab['table'].'.'.$main_tab[0]['id']['field_name']);
	$my_tab['translate_field_name'] = (!empty($field['translate_field_name']) 
		? $field['translate_field_name'] : false);

	// get detail key, if there is a field definition with it.
	// get id field name
	foreach ($field['fields'] AS $subfield) {
		if (!isset($subfield['type'])) continue;
		if ($subfield['type'] == 'id') $my_tab['id_field_name'] = $subfield['field_name'];
		if ($subfield['type'] != 'detail_key') continue;
		if (empty($main_tab[0]['fields'][$subfield['detail_key']])) continue;
		$detail_key_index = (isset($subfield['detail_key_index']) 
			? $subfield['detail_key_index'] : 0);
		$my_tab['detail_key'][] = array(
			'tab' => $main_tab[0]['fields'][$subfield['detail_key']]['subtable'], 
			'rec' => $detail_key_index
		);
	}

	// tick to save
	$my_tab['tick_to_save'] = (!empty($field['tick_to_save']) 
		? $field['tick_to_save'] : '');

	// access
	// TODO: check if that's ok or 'access' should get a different default value
	$my_tab['access'] = (isset($field['access'])
		? $field['access'] : false);
	
	// POST array
	// zz_records
	$my_tab['zz_records'] = (!empty($_POST['zz_records'][$tab]) ?
		intval($_POST['zz_records'][$tab]) : 0);

	// buttons: add, remove subrecord
	$my_tab['subtable_deleted'] = array();
	if (isset($_POST['zz_subtable_deleted'][$my_tab['table_name']]))
	//	fill existing zz_subtable_deleted ids in $my_tab['subtable_deleted']
		foreach ($_POST['zz_subtable_deleted'][$my_tab['table_name']] as $deleted)
			$my_tab['subtable_deleted'][] = $deleted[$my_tab['id_field_name']];
	$my_tab['subtable_add'] = (!empty($_POST['zz_subtables']['add'][$tab]) 
		? $_POST['zz_subtables']['add'][$tab] : array());
	$my_tab['subtable_remove'] = (!empty($_POST['zz_subtables']['remove'][$tab]) 
		? $_POST['zz_subtables']['remove'][$tab] : array());
	
	// tick for save
	$my_tab['zz_save_record'] = (!empty($_POST['zz_save_record'][$tab])
		? $_POST['zz_save_record'][$tab] : array());

	$my_tab['POST'] = ((!empty($_POST) AND !empty($_POST[$my_tab['table_name']]) 
		AND is_array($_POST[$my_tab['table_name']]))
		? $_POST[$my_tab['table_name']] : array());

	return $my_tab;
} 

/**
 * creates array for each detail record in $zz_tab[$tab]
 *
 * @param array $zz
 * @param array $field
 * @param array $my_tab = $zz_tab[$tab]
 * @param array $main_tab = $zz_tab[0]
 * @param array $zz_var
 * @param array $tab = tabindex
 * @return array $my_tab
 */
function zz_get_subrecords($zz, $field, $my_tab, $main_tab, $zz_var, $tab) {
	// set values, defaults if forgotten or overwritten
	foreach (array_keys($my_tab['POST']) as $rec) {
		$my_tab['POST'][$rec] = zz_check_def_vals($my_tab['POST'][$rec], $field['fields'],
			(!empty($zz_var['where'][$my_tab['table_name']]) ? $zz_var['where'][$my_tab['table_name']] : ''));
	}

	// set general definition for all $my_tab[$rec]
	$rec_tpl = array();
	$rec_tpl['fields'] = $field['fields'];
	$rec_tpl['access'] = $my_tab['access'];
	$rec_tpl['id']['field_name'] = $my_tab['id_field_name'];
	$rec_tpl['validation'] = true;
	$rec_tpl['record'] = false;
	$rec_tpl['action'] = false;

	// now go into each individual subrecord
	// first check for review or access, first if must be here because access might override mode here!
	if ($zz['mode'] AND ($zz['mode'] == 'add' OR $zz['mode'] == 'edit') 
		AND $rec_tpl['access'] != 'show') {
		if ($my_tab['zz_records'] < $my_tab['min_records']) 
			$my_tab['zz_records'] = $my_tab['min_records'];
		if (!$my_tab['zz_records']) $my_tab['zz_records'] = 1;
		$my_tab = zz_count_subrecords_form($my_tab);
	}

	// check records against database, if we have values, check number of records
	$saved = array();
	if (($zz['mode'] AND ($zz['mode'] != 'add' OR $rec_tpl['access'] == 'show'))
		OR ($zz['action'] AND !($my_tab['POST']) AND !empty($my_tab['values']))) {
		// TODO: get $ids, get $db_records
		// subtable_deleted is empty in case of 'action'
		$saved = zz_query_subrecord($my_tab, $main_tab['table'], $main_tab[0]['id']['value'], 
			$rec_tpl['id']['field_name'], $my_tab['subtable_deleted']); 
		if (!empty($my_tab['values'])) // sort $my_tab['values'] and $ids corresponding
			zz_sort_values($my_tab['values'], $saved, $rec_tpl['fields'], 
				$rec_tpl['id']['field_name']);
	}

	// check if there are more records in database than on website
	if ($zz['mode'] AND ($zz['mode'] != 'add' OR $rec_tpl['access'] == 'show')) {
		if (!empty($saved['ids'])) {
			$existing_records = count($saved['ids']);
			if ($existing_records > $my_tab['zz_records'])
				$my_tab['zz_records'] = $existing_records;
		}
	}
	if ($zz['mode']) {
		if ($my_tab['max_records'] AND $my_tab['zz_records'] > $my_tab['max_records']) 
			$my_tab['zz_records'] = $my_tab['max_records'];
		$my_tab = zz_get_subrecords_mode($my_tab, $rec_tpl, $zz_var, $saved);
	} elseif ($zz['action'] && !empty($my_tab['POST'])) {
		// individual definition
		foreach ($my_tab['POST'] as $rec => $my_rec) {
			$my_tab[$rec] = $rec_tpl;
			$my_tab[$rec]['save_record'] = (isset($my_tab['zz_save_record'][$rec])
				? $my_tab['zz_save_record'][$rec] : '');
			$my_tab[$rec]['id']['value'] = 
				(isset($my_rec[$rec_tpl['id']['field_name']])) ? $my_rec[$rec_tpl['id']['field_name']]: '';
			// set values, rewrite POST-Array
			if (!empty($my_tab['values'])) {
				$my_tab = zz_set_values($my_tab, $rec, $zz_var);
				if (!empty($my_tab['fielddefs']))
					$my_tab[$rec]['fields'] = zz_set_fielddefs($my_tab['fielddefs'], $my_tab[$rec]['fields']);
			}
		}
	}
	return $my_tab;
}

/**
 * get number of detail records, check which of these are deleted
 * this is for mode = edit and mode = delete
 *
 * @param array $my_tab
 *		'zz_records', 'min_records', 'table_name', 'id_field_name'
 * @return array $my_tab
 *		'zz_records', 'subtable_deleted'
 */
function zz_count_subrecords_form($my_tab) {
	if ($my_tab['subtable_add']) { // Value does not matter
		$my_tab['zz_records']++;
		$my_tab['subtable_add'] = array(); // ok, do this just once!
	}
	if ($my_tab['subtable_remove']) {
		foreach (array_keys($my_tab['subtable_remove']) as $rec) {
			if (empty($my_tab['subtable_remove'][$rec])) continue;
			// Value does not matter
			if (isset($my_tab['POST'][$rec][$my_tab['id_field_name']])) // has ID
				$my_tab['subtable_deleted'][] = $my_tab['POST'][$rec][$my_tab['id_field_name']];
			unset($my_tab['POST'][$rec]);
			$my_tab['zz_records']--;
			// zz_get_subrecords is called twice, so make sure, that $my_tab['POST'] is
			// changed as well since subtable is already unset!
		}
	}
	return $my_tab;
}

/**
 * sets records in form, also depending on values and fielddefs
 *
 * @param array $my_tab = $zz_tab[$tab]
 * @param array $rec_tpl
 * @param array $zz_var
 * @param array $saved
 * @return array $my_tab
 */
function zz_get_subrecords_mode($my_tab, $rec_tpl, $zz_var, $saved) {
	global $zz_conf;
	// function will be run twice from zzform(), therefore be careful, programmer!

	for ($rec = 0; $rec < $my_tab['zz_records']; $rec++) {
		// do not change other values if they are already there (important for error messages etc.)
		$continue_fast = (isset($my_tab[$rec]) ? true: false);
		if (!$continue_fast) // reset fields only if neccessary
			$my_tab[$rec] = $rec_tpl;
		if (isset($my_tab['values'])) {	// isset because might be empty
			$my_tab = zz_set_values($my_tab, $rec, $zz_var);
			if (!empty($my_tab['fielddefs']))
				$my_tab[$rec]['fields'] = zz_set_fielddefs($my_tab['fielddefs'], $my_tab[$rec]['fields']);
		}
		// ok, after we got the values, continue, rest already exists.
		if ($continue_fast) continue;

		if (isset($saved['ids'][$rec])) $idval = $saved['ids'][$rec];
		else $idval = false;
		$my_tab[$rec]['id']['value'] = $idval;
		$my_tab[$rec]['save_record'] = (isset($my_tab['zz_save_record'][$rec])
			? $my_tab['zz_save_record'][$rec] : '');

		$my_tab[$rec]['POST'] = '';
		if ($my_tab['POST']) {
			foreach ($my_tab['POST'] as $key => $my_rec) {
				if ($idval) {
					if (!isset($my_rec[$rec_tpl['id']['field_name']])) continue;
					if ($my_rec[$rec_tpl['id']['field_name']] != $idval) continue;
					$my_tab[$rec]['POST'] = $my_rec;
					unset($my_tab['POST'][$key]);
				} else {
					if (!empty($my_rec[$rec_tpl['id']['field_name']])) continue;
					if ($my_tab[$rec]['POST']) continue;
					// find first value pair that matches and put it into POST
					$my_tab[$rec]['POST'] = $my_rec;
					unset($my_tab['POST'][$key]);
				}
			}
		}
	}
	$my_tab['records'] = $my_tab['zz_records'];
	$my_tab['subtable_deleted'] = array_unique($my_tab['subtable_deleted']); // remove double entries
	if (!empty($my_tab['values'])) unset($my_tab['values']);
	// we need these two arrays in correct order (0, 1, 2, ...) to display the
	// subtables correctly when requeried
	ksort($my_tab);
	unset($my_tab['zz_save_record']); // not needed anymore
	return $my_tab;
}

/** 
 * query a detail record
 * 
 * @param array $my_tab = $zz_tab[$tab] = where $tab is the detail record to query
 * @param string $zz_tab[0]['table'] = main table name
 * @param int $zz_tab[0][0]['id']['value'] = main id value	
 * @param string $id_field_name = ID field name of detail record
 * @param array $deleted_ids = IDs that were deleted by user
 * @return array $saved = array with 'records' and 'ids' in detail records
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_query_subrecord($my_tab, $main_table, $main_id_value, $id_field_name, $deleted_ids = array()) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	global $zz_error;
	
	if (!empty($my_tab['translate_field_name'])) {
		$sql = zz_edit_sql($my_tab['sql'].' '.$my_tab['sql_not_unique'], 'WHERE', 
			$zz_conf['translations_table'].'.db_name = "'.$zz_conf['db_name'].'"
			AND '.$zz_conf['translations_table'].'.table_name = "'.$main_table.'"
			AND '.$zz_conf['translations_table'].'.field_name = "'.$my_tab['translate_field_name'].'"');
		$sql = zz_edit_sql($sql, 'WHERE', $my_tab['foreign_key_field_name'].' = "'.$main_id_value.'"');
	} else {
		$sql = zz_edit_sql($my_tab['sql'].' '.$my_tab['sql_not_unique'], 'WHERE', 
			$my_tab['foreign_key_field_name'].' = "'.$main_id_value.'"');
	}

	$saved['records'] = array();
	$saved['ids'] = array();
	$records = zz_db_fetch($sql, $id_field_name, '', '', E_USER_WARNING);
	foreach ($records as $line) {
		if (!in_array($line[$id_field_name], $deleted_ids)) {
			$saved['ids'][] = $line[$id_field_name];
		}
		$saved['records'][] = $line;
	}
	return zz_return($saved);
}

/**
 * sets values from 'values' to current $my_rec-Array
 *
 * @param array $my_tab
 * @param int $rec
 * @param array $zz_var
 * @return array $my_tab
 */
function zz_set_values($my_tab, $rec, $zz_var) {
	$my_values = array_shift($my_tab['values']);
	$fields = &$my_tab[$rec]['fields'];
	$table = $my_tab['table_name'];
	foreach (array_keys($fields) AS $f) {
		if (!empty($my_values[$f])) {
			if ($fields[$f]['type'] != 'hidden')
				$fields[$f]['type_detail'] = $fields[$f]['type'];
			$fields[$f]['type'] = 'hidden';
			$fields[$f]['value'] = $my_values[$f];
		}
	}
	// we have new values, so check whether these are set!
	// it's not possible to do this beforehands!
	if (!empty($my_tab['POST'][$rec])) {
		$my_tab['POST'][$rec] = zz_check_def_vals($my_tab['POST'][$rec], $fields,
			(!empty($zz_var['where'][$table]) ? $zz_var['where'][$table] : ''));
	}
	return $my_tab;
}

function zz_set_fielddefs(&$fielddefs, $fields) {
	$my_field_def = array_shift($fielddefs);
	foreach ($my_field_def as $f => $field) {
		if (!$field) {
			unset($fields[$f]);
		} else {
			$fields[$f] = array_merge($fields[$f], $my_field_def[$f]);
		}
	}
	return $fields;
}


/** 
 * sort values 
 * 
 *		changed variables:
 *		- $zz_tab[$tab]['values']
 *		- $saved['ids']
 *		deletes $saved['records']
 *
 * @param array $zz_tab[$tab]['values']
 * @param array $saved			Existing record IDs
 * @param array $fields
 * @param string $id_field_name
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_sort_values(&$values, &$saved, $fields, $id_field_name) {
	// check values against record, to get the correct number of detail records
	// important: go first through values, then through records to maintain
	// the original order of values
	$values_sorted = array();
	$ids_sorted = array();
	
	// example for $values: array(0 => array(6 => "val1"), 1 => array(6 => "val2"));
	// where 0, 1 = index, 6 = field_index and val1, val2 = values
	foreach ($values AS $index => $field) {
		$equal = false;
		foreach ($saved['records'] as $line) {
			foreach ($field as $f => $value) {
				// check whether all values correspond to a record entry
				$fieldname = $fields[$f]['field_name'];
				if (!empty($line[$fieldname]) AND $line[$fieldname] == $value) {
					$equal = true;
				} else {
					$equal = false;
					break; // once false is of course enough
				}
			}
			if ($equal) { // ok, here we go!
				$values_sorted[$index] = $values[$index];
				unset($values[$index]);
				// put ids in sorted array
				$ids_sorted[$index] = $line[$id_field_name];
				// remove ids from ids array
				if (false !== array_search($line[$id_field_name], $saved['ids'])) { // might be 0
					$id_index = array_search($line[$id_field_name], $saved['ids']);
					unset($saved['ids'][$id_index]);
				}
			} else { // initialize array to show that there is a record but no value should be set
				if (!isset($values_sorted[$index])) {
					$values_sorted[$index] = '';
					$ids_sorted[$index] = '';
				}
			}
		}
	}
	foreach (array_keys($values) as $index) {
		if (isset($values_sorted[$index]) AND !$values_sorted[$index]) {
			$values_sorted[$index] = $values[$index];
			unset($values[$index]);
		}
	}
	// append remaining $id at the end of the
	$saved['ids'] = array_merge($ids_sorted, $saved['ids']);

	if ($values_sorted)
		$values = array_merge($values_sorted, $values);
	unset($saved['records']); // not needed anymore
}


/** 
 * query record 
 * 
 * if everything was successful, query record (except in case it was deleted)
 * if not, change formhead and write POST values back into form
 *
 * @param array $my_tab complete zz_tab[$tab] array
 * @param int $rec Number of detail record
 * @param bool $validation true/false
 * @param string $mode
 * @return array $zz_tab[$tab]
 *		might unset $zz_tab[$tab][$rec]
 *		$zz_tab[$tab][$rec]['record'], $zz_tab[$tab][$rec]['record_saved'], 
 *		$zz_tab[$tab][$rec]['fields'], $zz_tab[$tab][$rec]['action']
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_query_record($my_tab, $rec, $validation, $mode) {
	global $zz_error;
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$my_rec = &$my_tab[$rec];
	$sql = $my_tab['sql'];
	$table = $my_tab['table'];

	// in case, record was deleted, query record is not neccessary
	if ($my_rec['action'] == 'delete') {
		unset($my_rec);
		return zz_return($my_tab);
	}
	// in case validation was passed or access is 'show'
	// everything's okay.
	if ($validation OR $my_rec['access'] == 'show') {
		// initialize 'record'
		$my_rec['record'] = false;
		// check whether record already exists (this is of course impossible for adding a record!)
		if ($mode != 'add' OR $my_rec['action']) {
			if ($my_rec['id']['value']) {
				$sql = zz_edit_sql($sql, 'WHERE', $table.'.'
					.$my_rec['id']['field_name']." = '".$my_rec['id']['value']."'");
				$my_rec['record'] = zz_db_fetch($sql, '', '', 'record exists?');
			}
		}
	// record has to be passed back to user
	} else {
		$my_rec['record'] = (isset($my_rec['POST-notvalid']) ? $my_rec['POST-notvalid'] : 
			isset($my_rec['POST']) ? $my_rec['POST'] : array());
		
	//	get record for display fields and maybe others
		$sql = zz_edit_sql($sql, 'WHERE', $table.'.'.$my_rec['id']['field_name']." = '".$my_rec['id']['value']."'");
		$my_rec['record_saved'] = zz_db_fetch($sql);

	//	display form again			
		$my_rec['action'] = 'review';

	//	print out all records which were wrong, set class to error
		$validate_errors = false;
		foreach ($my_rec['fields'] as $no => $field) {
			// just look for check_validation set but false
			if (!isset($field['check_validation']) 
				OR $field['check_validation']) continue;
			// append error to 'class'
			if (isset($my_rec['fields'][$no]['class'])) {
				$my_rec['fields'][$no]['class'].= ' error';
			} else {
				$my_rec['fields'][$no]['class'] = 'error';
			}
			if ($field['type'] == 'password_change') continue;
			if ($field['type'] == 'subtable') continue;
			if ($my_rec['record'][$field['field_name']]) {
				// there's a value, so this is an incorrect value
				$zz_error['validation']['msg'][] = zz_text('Value_incorrect_in_field')
					.' <strong>'.$field['title'].'</strong>'
					.(!empty($field['validation_error']) ? ' ('
					.$field['validation_error'].')' : '');
				$zz_error['validation']['incorrect_values'][] = array(
					'field_name' => $field['field_name'],
					'msg' => zz_text('incorrect value').': '.$my_rec['record'][$field['field_name']]
				);
			} elseif (empty($field['dont_show_missing'])) {
				// there's a value missing
				$zz_error['validation']['msg'][] = zz_text('Value missing in field')
					.' <strong>'.$field['title'].'</strong>';
			}
		}
	}
	return zz_return($my_tab);
}

/** 
 * Fills field definitions with default definitions and infos from database
 * 
 * @param array $fields
 * @param string $table [i. e. db_name.table]
 * @param bool $multiple_times marker for conditions
 * @param array $fields
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_fill_out($fields, $table, $multiple_times = false, $mode = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	foreach (array_keys($fields) as $no) {
		if (!empty($fields[$no]['conditions'])) {
			if (!$multiple_times) 
				unset($fields[$no]['conditions']); // we don't need these anymore
			elseif ($multiple_times == 1) {
				if (count($fields[$no]) == 1) continue; // if there are only conditions, go on
			}
		}
		if (!$fields[$no]) { 	// allow placeholder for fields to get them into the wanted order
			unset($fields[$no]);
			continue;
		}
		if (!isset($fields[$no]['type'])) // default type: text
			$fields[$no]['type'] = 'text';
		if (!isset($fields[$no]['title'])) { // create title
			$fields[$no]['title'] = ucfirst($fields[$no]['field_name']);
			$fields[$no]['title'] = str_replace('_ID', ' ', $fields[$no]['title']);
			$fields[$no]['title'] = str_replace('_id', ' ', $fields[$no]['title']);
			$fields[$no]['title'] = str_replace('_', ' ', $fields[$no]['title']);
			$fields[$no]['title'] = rtrim($fields[$no]['title']);
		}

		if (($zz_conf['multilang_fieldnames']) 
			AND (!$multiple_times OR $multiple_times > 1)) {// translate fieldnames, if set
			$fields[$no]['title'] = zz_text($fields[$no]['title']);
			if (!empty($fields[$no]['explanation']))
				$fields[$no]['explanation'] = zz_text($fields[$no]['explanation']);
			if (!empty($fields[$no]['title_append'])) 
				$fields[$no]['title_append'] = zz_text($fields[$no]['title_append']);
		}
		if ($fields[$no]['type'] == 'option') { 
			$fields[$no]['hide_in_list'] = true; // do not show option-fiels in tab
			if (!empty($fields[$no]['class']))
				$fields[$no]['class'] .= ' option'; // format option-fields with css
			else
				$fields[$no]['class'] = 'option'; // format option-fields with css
		}
		if (!isset($fields[$no]['explanation'])) $fields[$no]['explanation'] = false; // initialize
		if (!$multiple_times) {
			if (!isset($fields[$no]['maxlength']) && isset($fields[$no]['field_name'])
				AND $mode != 'list_only') // no need to check maxlength in list view only 
			{
				$fields[$no]['maxlength'] = zz_check_maxlength($fields[$no]['field_name'], $fields[$no]['type'], $table);
			}
			if (!empty($fields[$no]['sql'])) // replace whitespace with space
				$fields[$no]['sql'] = preg_replace("/\s+/", " ", $fields[$no]['sql']);
		}
		if ($fields[$no]['type'] == 'subtable') // for subtables, do this as well
			// here we still should have a different db_name in 'table' if using multiples dbs
			// so it's no need to prepend the db name of this table
			$fields[$no]['fields'] = zz_fill_out($fields[$no]['fields'], $fields[$no]['table'], $multiple_times, $mode);
	}
	return zz_return($fields);
}

/** 
 * Logs SQL operation in logging table in database
 * 
 * @param string $sql = SQL Query
 * @param string $user = Active user
 * @param int $record_id = record ID, optional, if ID shall be logged
 * @return bool = operation successful or not
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_log_sql($sql, $user, $record_id = false) {
	global $zz_conf;
	// logs each INSERT, UPDATE or DELETE query
	// with record_id
	if (!mysql_affected_rows()) return false;
	$sql = trim($sql);
	if ($sql == 'SELECT 1') return false;
	// check if zzform() set db_main, test against !empty because need not be set
	// (zz_log_sql() might be called from outside zzform())
	if (!strstr($zz_conf['logging_table'], '.') AND !empty($zz_conf['db_main'])) {
		$zz_conf['logging_table'] = $zz_conf['db_main'].'.'.$zz_conf['logging_table'];
	}
	if (!empty($zz_conf['logging_id']) AND $record_id)
		$sql = 'INSERT INTO '.$zz_conf['logging_table'].' 
			(query, user, record_id) VALUES ("'.zz_db_escape($sql).'", "'.$user.'", '.$record_id.')';
	// without record_id, only for backwards compatibility
	else
		$sql = 'INSERT INTO '.$zz_conf['logging_table'].' 
			(query, user) VALUES ("'.zz_db_escape($sql).'", "'.$user.'")';
	$result = mysql_query($sql);
	if (!$result) return false;
	else return true;
	// die if logging is selected but does not work?
}

function zz_sql_order($fields, $sql) {
	$order = false;
	if (!empty($_GET['order']) OR !empty($_GET['group'])) {
		$my_order = false;
		if (!empty($_GET['dir']))
			if ($_GET['dir'] == 'asc') $my_order = ' ASC';
			elseif ($_GET['dir'] == 'desc') $my_order = ' DESC';
		foreach ($fields as $field) {
			if (!empty($_GET['order'])
				AND ((isset($field['display_field']) && $field['display_field'] == $_GET['order'])
				OR (isset($field['field_name']) && $field['field_name'] == $_GET['order']))
			)
				if (isset($field['order'])) $order[] = $field['order'].$my_order;
				else $order[] = $_GET['order'].$my_order;
			if (!empty($_GET['group'])
				AND ((isset($field['display_field']) && $field['display_field'] == $_GET['group'])
				OR (isset($field['field_name']) && $field['field_name'] == $_GET['group']))
			)
				if (isset($field['order'])) $order[] = $field['order'].$my_order;
				else $order[] = $_GET['group'].$my_order;
		}
		if (!$order) return $sql;
		if (strstr($sql, 'ORDER BY'))
			// if there's already an order, put new orders in front of this
			$sql = str_replace ('ORDER BY', ' ORDER BY '.implode(',', $order).', ', $sql);
		else
			// if not, just append the order
			$sql.= ' ORDER BY '.implode(', ', $order);
	} 
	return $sql;
}

/**
 * gets all variables for identifier field to use them in zz_create_identifier()
 *
 * @param array $my = $zz_tab[$tab][$rec]
 * @param int $f = $zz['fields'][n]
 * @param array $main_post POST values of $zz_tab[0][0]['POST']
 * @return array $func_vars
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */ 
function zz_get_identifier_vars(&$my_rec, $f, $main_post) {
	// content of ['fields']
	// possible syntax: fieldname[sql_fieldname] or tablename.fieldname or fieldname
	$func_vars = false;
	foreach ($my_rec['fields'][$f]['fields'] as $function => $var) {
	//	check for substring parameter
		preg_match('/{(.+)}$/', $var, $substr);
		if ($substr) $var = preg_replace('/{(.+)}$/', '', $var, $substr);
	//	check whether subtable or not
		if (strstr($var, '.')) { // subtable
			$vars = explode('.', $var);
			if (isset($my_rec['POST'][$vars[0]]) && isset($my_rec['POST'][$vars[0]][0][$vars[1]])) {
				// todo: problem: subrecords are being validated after main record, so we might get invalid results
				$func_vars[$var] = $my_rec['POST'][$vars[0]][0][$vars[1]]; // this might not be correct, because it ignores the table_name
				foreach ($my_rec['fields'] as $field) {
					if (empty($field['table']) OR $field['table'] != $vars[0]
						AND (empty($field['table_name']) OR $field['table_name'] != $vars[0])) continue;
					foreach ($field['fields'] as $subfield)
						if (empty($subfield['field_name']) OR $subfield['field_name'] != $vars[1]) continue;
						if ($subfield['type'] != 'date') continue;
						$func_vars[$var] = datum_int($func_vars[$var]); 
						$func_vars[$var] = str_replace('-00', '', $func_vars[$var]); 
						$func_vars[$var] = str_replace('-00', '', $func_vars[$var]); 
				}
			}
			if (empty($func_vars[$var])) {
				preg_match('/^(.+)\[(.+)\]$/', $vars[1], $fieldvar); // split array in variable and key
				if ($fieldvar) foreach ($my_rec['fields'] as $field) {
					if ((!empty($field['table']) && $field['table'] == $vars[0])
						OR (!empty($field['table_name']) && $field['table_name'] == $vars[0])) 
						foreach ($field['fields'] as $subfield) {
							if (empty($subfield['sql'])) continue;
							if (empty($subfield['field_name'])) continue; // empty: == subtable
							if (empty($my_rec['POST'][$vars[0]][0][$subfield['field_name']])) continue;
							if ($subfield['field_name'] == $fieldvar[1]) {
								$func_vars[$var] = zz_get_identifier_sql_vars($subfield['sql'], 
									$my_rec['POST'][$vars[0]][0][$subfield['field_name']], $fieldvar[2]);
							}
						}
				}
			}
		} else {
			if (isset($my_rec['POST'][$var]))
				$func_vars[$var] = $my_rec['POST'][$var];
			if (empty($func_vars[$var])) { // could be empty because it's an array
				preg_match('/^(.+)\[(.+)\]$/', $var, $fieldvar); // split array in variable and key
				if (isset($fieldvar[1]) AND $fieldvar[1] == '0'
					AND !empty($main_post[$fieldvar[2]]) AND !is_array($main_post[$fieldvar[2]])) {
					$func_vars[$var] = $main_post[$fieldvar[2]];
					if (substr($func_vars[$var], 0, 1)  == '"' AND substr($func_vars[$var], -1) == '"')
						$func_vars[$var] = substr($func_vars[$var], 1, -1); // remove " "
				} else {
					foreach ($my_rec['fields'] as $field) {
						if (!empty($field['sql']) && !empty($field['field_name']) // empty: == subtable
							&& !empty($fieldvar[1]) && $field['field_name'] == $fieldvar[1]
							&& !empty($my_rec['POST'][$field['field_name']])) {
							$func_vars[$var] = zz_get_identifier_sql_vars($field['sql'], 
								$my_rec['POST'][$field['field_name']], $fieldvar[2]);
						}
					}
				}
			}
		}
		if ($substr)
			eval ($line ='$func_vars[$var] = substr($func_vars[$var], '.$substr[1].');');
		if (function_exists($function)) $func_vars[$var] = $function($func_vars[$var]);
	}
	return $func_vars;
}

/** 
 * Gets values for identifier from database
 * 
 * @param string $sql SQL query
 * @param int $id record ID
 * @param string $fieldname (optional) if set, returns just fieldname
 * @return mixed array: full line from database, string: just field if fieldname
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_get_identifier_sql_vars($sql, $id, $fieldname = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	// remove whitespace
	$sql = preg_replace("/\s+/", " ", $sql); // first blank needed for SELECT
	$sql_tokens = explode(' ', trim($sql)); // remove whitespace
	$unwanted = array('SELECT', 'DISTINCT');
	foreach ($sql_tokens as $token) {
		if (!in_array($token, $unwanted)) {
			$id_fieldname = trim($token);
			if (substr($id_fieldname, -1) == ',')
				$id_fieldname = substr($id_fieldname, 0, -1);
			break;
		}
	}
	$sql = zz_edit_sql($sql, 'WHERE', $id_fieldname.' = '.$id);
	$line = zz_db_fetch($sql);
	if ($fieldname) {
		if (isset($line[$fieldname])) return zz_return($line[$fieldname]);
		zz_return(false);
	} else {
		if ($line) zz_return($line);
		zz_return(false);
	}
}

/** 
 * Creates identifier field that is unique
 * 
 * @param array $vars
 * @param array $conf	Configuration for how to handle the strings
 *		'forceFilename' = '-'; value which will be used for replacing spaces and unknown letters
 *			$conf['concat'] '.'; string used for concatenation of variables. might be array, values are used in the same order they appear in the array
 *			$conf['exists'] '.'; string used for concatenation if identifier exists
 *			$conf['lowercase'] true; false will not transform all letters to lowercase
 *			$conf['slashes'] false; true = slashes will be preserved
 *			$conf['where'] WHERE-condition to be appended to query that checks existence of identifier in database 
 * @param array $my_rec		$zz_tab[$tab][$rec]
 * @param string $table	Name of Table
 * @param int $field		Number of field definition
 * @return string identifier
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_create_identifier($vars, $conf, $my_rec = false, $table = false, $field = false) {
	if (empty($vars)) return false;
	if ($my_rec AND $field AND $table) {
		if (in_array($my_rec['fields'][$field]['field_name'], array_keys($vars)) && $vars[$my_rec['fields'][$field]['field_name']]) 
			// do not change anything if there has been a value set once and identifier is in vars array
			return $vars[$my_rec['fields'][$field]['field_name']]; 
	}
	$conf['forceFilename'] = isset($conf['forceFilename']) ? substr($conf['forceFilename'], 0, 1) : '-';
	$conf['concat'] = isset($conf['concat']) ? (is_array($conf['concat']) 
		? $conf['concat'] : substr($conf['concat'], 0, 1)) : '.';
	$conf['exists'] = isset($conf['exists']) ? substr($conf['exists'], 0, 1) : '.';
	$conf['lowercase'] = isset($conf['lowercase']) ? $conf['lowercase'] : true;
	$conf['slashes'] = isset($conf['slashes']) ? $conf['slashes'] : false;
	$i = 0;

	foreach ($vars as $var) {
		$i++;
		if (!$var) continue;
		if ((strstr($var, '/') AND $i != count($vars))
			OR $conf['slashes']) { // last var will be treated normally, other vars may inherit slashes from dir names
			$dir_vars = explode('/', $var);
			foreach ($dir_vars as $d_var) {
				if (!$d_var) continue;
				$my_var = forceFilename($d_var, $conf['forceFilename']);
				if ($conf['lowercase']) $my_var = strtolower($my_var);
				$idf_arr[] = $my_var;
			}
		} else {
			$my_var = forceFilename($var, $conf['forceFilename']);
			if ($conf['lowercase']) $my_var = strtolower($my_var);
			$idf_arr[] = $my_var;
		}
	}
	if (empty($idf_arr)) return false;
	$idf = '';
	if (!is_array($conf['concat'])) {
		$idf = implode($conf['concat'], $idf_arr);
	} else { // idf 0 con 0 idf 1 con 1 idf 2 con 1 ...
		$last_concat = array_pop($conf['concat']);
		foreach ($idf_arr as $key => $value) {
			if ($idf) {
				if ($key == count($idf_arr)-1) {
					// last one
					$idf .= $last_concat;
				} else {
					// normal order, take actual last one if no other is left
					// add concat separator 0, 1, ...
					if (!empty($conf['concat'][$key-1]))
						$idf .= $conf['concat'][$key-1];
					else
						$idf .= $conf['concat'][count($conf['concat'])-1];
				}
			}
			$idf .= $value;
		}
	}		
	if (!empty($conf['prefix'])) $idf = $conf['prefix'].$idf;
	$i = (!empty($conf['start']) ? $conf['start'] : 2); // start value, if idf already exists
	if (!empty($conf['start_always'])) $idf .= $conf['exists'].$i;
	else $conf['start_always'] = false;
	if ($my_rec AND $field AND $table) {
		if ($my_rec AND !empty($my_rec['fields'][$field]['maxlength']) && ($my_rec['fields'][$field]['maxlength'] < strlen($idf)))
			$idf = substr($idf, 0, $my_rec['fields'][$field]['maxlength']);
		// check whether identifier exists
		$idf = zz_exists_identifier($idf, $i, $table, $my_rec['fields'][$field]['field_name'], 
			$my_rec['id']['field_name'], $my_rec['POST'][$my_rec['id']['field_name']], 
			$conf, $my_rec['fields'][$field]['maxlength']);
	}
	return $idf;
}

/**
 * check if an identifier already exists in database, add nuermical suffix
 * until an adequate identifier exists  (john-doe, john-doe-2, john-doe-3 ...)
 *
 * @param string $idf
 * @param int $i
 * @param string $table
 * @param string $field
 * @param string $id_field
 * @param string $id_value
 * @param array $conf
 * @param int $maxlength
 * @global array $zz_conf
 * @return string $idf
 */
function zz_exists_identifier($idf, $i, $table, $field, $id_field, $id_value, $conf, $maxlength = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$sql = 'SELECT '.$field.' FROM '.$table.' WHERE '.$field.' = "'.$idf.'"
		AND '.$id_field.' != '.$id_value.(!empty($conf['where']) ? ' AND '.$conf['where'] : '');
	$records = zz_db_fetch($sql, $field);
	if ($records) {
		if ($i > 2 OR $conf['start_always']) {
			// with start_always, we can be sure, that a generated suffix exists so we can safely remove it. 
			// for other cases, this is only true for $i > 2.
			$idf = substr($idf, 0, strrpos($idf, $conf['exists']));
		}
		$suffix = $conf['exists'].$i;
		if ($maxlength && strlen($idf.$suffix) > $maxlength) 
			$idf = substr($idf, 0, ($maxlength-strlen($suffix))); 
			// in case there is a value for maxlength, make sure that resulting string won't be longer
		$idf = $idf.$suffix;
		$i++;
		$idf = zz_exists_identifier($idf, $i, $table, $field, $id_field, $id_value, $conf, $maxlength);
	}
	return zz_return($idf);
}

/** 
 * converts fieldnames with [ and ] into valid HTML id values
 * 
 * @param string $fieldname field name with []-brackets
 * @param string $prefix prepends 'field_' as default or other prefix
 * @return string valid HTML id value
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function make_id_fieldname($fieldname, $prefix = 'field') {
	$fieldname = str_replace('][', '_', $fieldname);
	$fieldname = str_replace('[', '_', $fieldname);
	$fieldname = str_replace(']', '', $fieldname);
	if ($prefix) $fieldname = $prefix.'_'.$fieldname;
	return $fieldname;
}

/** 
 * strips magic quotes from multidimensional arrays
 * 
 * @param array $mixed Array with magic_quotes
 * @return array Array without magic_quotes
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function magic_quotes_strip($mixed) {
   if(is_array($mixed))
       return array_map('magic_quotes_strip', $mixed);
   return stripslashes($mixed);
}


/**
 * puts parts of SQL query in correct order when they have to be added
 *
 * this function works only for sql queries without UNION:
 * might get problems with backticks that mark fieldname that is equal with SQL 
 * keyword
 * mode = add until now default, mode = replace is only implemented for SELECT
 * identical to wrap_edit_sql()!
 * @param string $sql original SQL query
 * @param string $n_part SQL keyword for part shall be edited or replaced
 *		SELECT ... FROM ... JOIN ...
 * 		WHERE ... GROUP BY ... HAVING ... ORDER BY ... LIMIT ...
 * @param string $values new value for e. g. WHERE ...
 * @param string $mode Mode, 'add' adds new values while keeping the old ones, 
 *		'replace' replaces all old values
 * @return string $sql modified SQL query
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @see wrap_edit_sql()
 */
function zz_edit_sql($sql, $n_part = false, $values = false, $mode = 'add') {
	global $zz_conf; // for debug only
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	if (substr(trim($sql), 0, 4) == 'SHOW' AND $n_part == 'LIMIT') {
	// LIMIT, WHERE etc. is only allowed with SHOW
	// not allowed e. g. for SHOW DATABASES(), SHOW TABLES FROM ...
		return zz_return($sql);
	}
	if (substr(trim($sql), 0, 14) == 'SHOW DATABASES' AND $n_part == 'WHERE') {
		return zz_return(false); // this is impossible and will automatically trigger an error
		// TODO: implement LIKE here.
	}

	// remove whitespace
	$sql = ' '.preg_replace("/\s+/", " ", $sql); // first blank needed for SELECT
	// SQL statements in descending order
	$statements_desc = array('LIMIT', 'ORDER BY', 'HAVING', 'GROUP BY', 'WHERE', 'FROM', 'SELECT DISTINCT', 'SELECT');
	foreach ($statements_desc as $statement) {
		// add whitespace in between brackets and statements to make life easier
		$sql = str_replace(')'.$statement.' ', ') '.$statement.' ', $sql);
		$sql = str_replace(')'.$statement.'(', ') '.$statement.' (', $sql);
		$sql = str_replace(' '.$statement.'(', ' '.$statement.' (', $sql);
		// check for statements
		$explodes = explode(' '.$statement.' ', $sql);
		if (count($explodes) > 1) {
		// = look only for last statement
		// and put remaining query in [1] and cut off part in [2]
			$o_parts[$statement][2] = array_pop($explodes);
			$o_parts[$statement][1] = implode(' '.$statement.' ', $explodes).' '; // last blank needed for exploding SELECT from DISTINCT
		}
		$search = '/(.+) '.$statement.' (.+?)$/i'; 
//		preg_match removed because it takes way too long if nothing is found
//		if (preg_match($search, $sql, $o_parts[$statement])) {
		if (empty($o_parts[$statement])) continue;
		$found = false;
		$lastpart = false;
		while (!$found) {
			// check if there are () outside '' or "" and count them to check
			// whether we are inside a subselect
			$temp_sql = $o_parts[$statement][1]; // look at first part of query

			// 1. remove everything in '' and "" which are not escaped
			// replace \" character sequences which escape "
			$temp_sql = preg_replace('/\\\\"/', '', $temp_sql);
			// replace "strings" without " inbetween, empty "" as well
			$temp_sql = preg_replace('/"[^"]*"/', "away", $temp_sql);
			// replace \" character sequences which escape '
			$temp_sql = preg_replace("/\\\\'/", '', $temp_sql);
			// replace "strings" without " inbetween, empty '' as well
			$temp_sql = preg_replace("/'[^']*'/", "away", $temp_sql);

			// 2. count opening and closing ()
			//  if equal ok, if not, it's a statement in a subselect
			// assumption: there must not be brackets outside " or '
			if (substr_count($temp_sql, '(') == substr_count($temp_sql, ')')) {
				$sql = $o_parts[$statement][1]; // looks correct, so go on.
				$found = true;
			} else {
				// remove next last statement, and go on until you found 
				// either something with correct bracket count
				// or no match anymore at all
				$lastpart = ' '.$statement.' '.$o_parts[$statement][2];
				// check first with strstr if $statement (LIMIT, WHERE etc.)
				// is still part of the remaining sql query, because
				// preg_match will take 2000 times longer if there is no match
				// at all (bug in php?)
				if (strstr($o_parts[$statement][1], $statement) 
					AND preg_match($search, $o_parts[$statement][1], $o_parts[$statement])) {
					$o_parts[$statement][2] = $o_parts[$statement][2].' '.$lastpart;
				} else {
					unset($o_parts[$statement]); // ignore all this.
					$found = true;
				}
			}
		}
	}
	if ($n_part && $values) {
		$n_part = strtoupper($n_part);
		switch ($n_part) {
		case 'LIMIT':
			// replace complete old LIMIT with new LIMIT
			$o_parts['LIMIT'][2] = $values;
			break;
		case 'ORDER BY':
			if ($mode == 'add') {
				// append old ORDER BY to new ORDER BY
				if (!empty($o_parts['ORDER BY'][2])) 
					$o_parts['ORDER BY'][2] = $values.', '.$o_parts['ORDER BY'][2];
				else
					$o_parts['ORDER BY'][2] = $values;
			} elseif ($mode == 'delete') {
				unset($o_parts['ORDER BY']);
			}
			break;
		case 'WHERE':
		case 'GROUP BY':
		case 'HAVING':
			if ($mode == 'add') {
				if (!empty($o_parts[$n_part][2])) 
					$o_parts[$n_part][2] = '('.$o_parts[$n_part][2].') AND ('.$values.')';
				else 
					$o_parts[$n_part][2] = $values;
			}  elseif ($mode == 'delete') {
				unset($o_parts[$n_part]);
			}
			break;
		case 'SELECT':
			if (!empty($o_parts['SELECT DISTINCT'][2])) {
				if ($mode == 'add')
					$o_parts['SELECT DISTINCT'][2] .= ','.$values;
				elseif ($mode == 'replace')
					$o_parts['SELECT DISTINCT'][2] = $values;
			} else {
				if ($mode == 'add')
					$o_parts['SELECT'][2] = ','.$values;
				elseif ($mode == 'replace')
					$o_parts['SELECT'][2] = $values;
			}
			break;
		default:
			echo 'The variable <code>'.$n_part.'</code> is not supported by zz_edit_sql().';
			exit;
		}
	}
	$statements_asc = array_reverse($statements_desc);
	foreach ($statements_asc as $statement) {
		if (!empty($o_parts[$statement][2])) 
			$sql.= ' '.$statement.' '.$o_parts[$statement][2];
	}
	return zz_return($sql);
}

/**
 * Checks whether values entered in text feld are valid records in other
 * tables and if true, replaces these values with the correct foreign ID
 *
 * @param array $my_rec
 * @param int $f Key of current field
 * @param int $max_select = e. g. $zz_conf['max_select'], maximum entries in
 *		option-Field before we offer a blank text field to enter values
 * @global array $zz_error
 * @global array $zz_conf
 * @return array $my_rec changed keys:
 *		'fields'[$f], 'POST', 'POST-notvalid', 'validation'
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_check_select($my_rec, $f, $max_select) {
	global $zz_error;
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$sql = $my_rec['fields'][$f]['sql'];
	// preg_match, case insensitive, space after select, space around from 
	// - might not be 100% perfect, but should work always
	preg_match('/SELECT( DISTINCT|) *(.+) FROM /Ui', $sql, $fieldstring); 
	$fields = explode(",", $fieldstring[2]);
	unset($fieldstring);
	$oldfield = false;
	$newfields = false;
	foreach ($fields as $myfield) {
		// oldfield, so we are inside parentheses
		if ($oldfield) $myfield = $oldfield.','.$myfield; 
		// not enough brackets, so glue strings together until there are enought 
		// - not 100% safe if bracket appears inside string
		if (substr_count($myfield, '(') != substr_count($myfield, ')')) {
			$oldfield = $myfield; 
		} else {
			$myfields = '';
			// replace AS blah against nothing
			if (stristr($myfield, ') AS')) 
				preg_match('/(.+\)) AS [a-z0-9_]/i', $myfield, $myfields); 
			if ($myfields) $myfield = $myfields[1];
			$newfields[] = $myfield;
			$oldfield = false; // now that we've written it to array, empty it
		}
	}

	$postvalues = explode(' | ', $my_rec['POST'][$my_rec['fields'][$f]['field_name']]);
	$wheresql = '';
	foreach ($postvalues as $value) {
		foreach ($newfields as $index => $field) {
			$field = trim($field);
			if (!empty($my_rec['fields'][$f]['show_hierarchy'])
				AND $field == $my_rec['fields'][$f]['show_hierarchy']) continue;
			// do not search in show_hierarchy as this field is there for presentation only
			// and might be removed below!
			if (!$wheresql) $wheresql.= '(';
			elseif (!$index) $wheresql.= ' ) AND (';
			else $wheresql.= ' OR ';
			// preg_match: "... ", extra space will be added in zz_draw_select!
			if (preg_match('/^(.+?) *\.\.\. *$/', $value, $short_value)) 
				// reduces string with dots which come from values which have 
				// been cut beforehands
				$value = $short_value[1];
			if (substr($value, -1) != ' ') 
				// if there is a space at the end of the string, don't do LIKE with %!
				$wheresql.= $field.' LIKE "%'.zz_db_escape(trim($value)).'%"'; 
			else
				$wheresql.= $field.' LIKE "'.zz_db_escape(trim($value)).'"'; 
		}
	}
	$wheresql .= ')';
	$sql = zz_edit_sql($sql, 'WHERE', $wheresql);
	$possible_values = zz_db_fetch($sql, 'dummy_id', 'single value');
	if (!count($possible_values)) {
		// no records, user must re-enter values
		$my_rec['fields'][$f]['type'] = 'select';
		$my_rec['fields'][$f]['class'] = 'reselect' ;
		$my_rec['fields'][$f]['suffix'] = '<br>'.zz_text('No entry found. Try less characters.');
		$my_rec['validation'] = false;
	} elseif (count($possible_values) == 1) {
		// exactly one record found, so this is the value we want
		$my_rec['POST'][$my_rec['fields'][$f]['field_name']] = current($possible_values);
		$my_rec['POST-notvalid'][$my_rec['fields'][$f]['field_name']] = current($possible_values);
		$my_rec['fields'][$f]['sql'] = $sql; // if other fields contain errors
	} elseif (count($possible_values) <= $max_select) {
		// let user reselect value from dropdown select
		$my_rec['fields'][$f]['type'] = 'select';
		$my_rec['fields'][$f]['sql'] = $sql;
		$my_rec['fields'][$f]['class'] = 'reselect';
		if (!empty($my_rec['fields'][$f]['show_hierarchy'])) {
			// since this is only a part of the list, hierarchy does not make sense
			$my_rec['fields'][$f]['sql'] = preg_replace('/,*\s*'.$my_rec['fields'][$f]['show_hierarchy'].'/', '', $my_rec['fields'][$f]['sql']);
			$my_rec['fields'][$f]['show_hierarchy'] = false;
		}
		$my_rec['validation'] = false;
	} elseif (count($possible_values)) {
		// still too many records, require more characters
		$my_rec['fields'][$f]['default'] = 'reselect' ;
		$my_rec['fields'][$f]['class'] = 'reselect' ;
		$my_rec['fields'][$f]['suffix'] = ' '.zz_text('Please enter more characters.');
		$my_rec['fields'][$f]['check_validation'] = false;
		$my_rec['validation'] = false;
	} else {
		$my_rec['fields'][$f]['class'] = 'error' ;
		$my_rec['fields'][$f]['check_validation'] = false;
		$my_rec['validation'] = false;
	}
	return zz_return($my_rec);
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
 * @global array $zz_conf	Configuration variables, here: 'password_encryption'
 * @return string false: an error occurred; string: new encrypted password 
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_check_password($old, $new1, $new2, $sql) {
	global $zz_error;
	global $zz_conf;
	if ($new1 != $new2) {
		$zz_error[] = array(
			'msg' => 'New passwords do not match. Please try again.',
			'level' => E_USER_NOTICE
		);
		return false; // new passwords do not match
	}
	if ($old == $new1) {
		$zz_error[] = array(
			'msg' => 'New and old password are identical. Please choose a different new password.',
			'level' => E_USER_NOTICE
		);
		// old password eq new password - this is against identity theft if 
		// someone interferes a password mail
		return false; 
	}
	$old_pwd = zz_db_fetch($sql, '', 'single value', __FUNCTION__);
	if (!$old_pwd) return false;
	if ($zz_conf['password_encryption']($old) == $old_pwd) {
		$zz_error[] = array(
			'msg' => zz_text('Your password has been changed!'),
			'level' => E_USER_NOTICE
		);
		return $zz_conf['password_encryption']($new1); // new1 = new2, old = old, everything is ok
	} else {
		$zz_error[] = array(
			'msg' => 'Your current password is different from what you entered. Please try again.',
			'level' => E_USER_NOTICE
		);
		return false;
	}
}

/** 
 * Formats a heading for WHERE-conditions
 *
 * @param string $heading ($zz_conf['heading'])
 * @param array $zz_fields
 * @param array $where_condition, optional
 * @global array $zz_conf
 * @global array $zz_error
 * @return string $heading
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_nice_headings($heading, $zz_fields, $where_condition = array()) {
	global $zz_conf;
	global $zz_error;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$i = 0;
	$heading_addition = array();
	// depending on WHERE-Condition
	foreach (array_keys($where_condition) as $mywh) {
		$mywh = zz_db_escape($mywh);
		$wh = explode('.', $mywh);
		if (!isset($wh[1])) $index = 0; // without .
		else $index = 1;
		$heading_addition[$i] = false;
		if (isset($zz_conf['heading_sql'][$wh[$index]]) && 
			isset($zz_conf['heading_var'][$wh[$index]]) AND
			$where_condition[$mywh]) { // only if there is a value! (might not be the case if write_once-fields come into play)
		//	create sql query, with $mywh instead of $wh[$index] because first might be ambiguous
			$wh_sql = zz_edit_sql($zz_conf['heading_sql'][$wh[$index]], 'WHERE', 
				$mywh.' = '.zz_db_escape($where_condition[$mywh]));
			$wh_sql .= ' LIMIT 1';
		//	if key_field_name is set
			foreach ($zz_fields as $field)
				if (isset($field['field_name']) && $field['field_name'] == $wh[$index])
					if (isset($field['key_field_name']))
						$wh_sql = str_replace($wh[$index], $field['key_field_name'], $wh_sql);
			// just send a notice if this doesn't work as it's not crucial
			$heading_values = zz_db_fetch($wh_sql, '', '', '', E_USER_NOTICE);
			if ($heading_values) {
				foreach ($zz_conf['heading_var'][$wh[$index]] as $myfield)
					$heading_addition[$i] .= ' '.$heading_values[$myfield];
			}
		} elseif (isset($zz_conf['heading_enum'][$wh[$index]]) && 
			isset($zz_conf['heading_var'][$wh[$index]])) {
				$heading_addition[$i] .= ' '.htmlspecialchars($where_condition[$mywh]);
				// todo: insert corresponding value in enum_title
		}
		if ($heading_addition[$i] AND !empty($zz_conf['heading_link'][$wh[$index]])) {
			if (strstr($zz_conf['heading_link'][$wh[$index]], '?')) $sep = '&amp;';
			else $sep = '?';
			$heading_addition[$i] = '<a href="'.$zz_conf['heading_link'][$wh[$index]]
				.$sep.'mode=show&amp;id='.urlencode($where_condition[$mywh]).'">'
				.$heading_addition[$i].'</a>';
		}
		if (empty($heading_addition[$i])) unset($heading_addition[$i]);
		$i++;
	}
	if ($heading_addition) {
		$heading .= ':<br>'.implode(' &#8211; ', $heading_addition); 
	}
	return zz_return($heading);
}

/** 
 * Formats 'selection' for search results
 *
 * @param array $zz_fields
 * @global array $zz_conf
 * @return string $selection
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_nice_selection($zz_fields) {
	if (empty($_GET['q'])) return false;
	global $zz_conf;

	// Display search filter
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$fieldname = false;
	$selection = zz_text('Search').': ';
	$add_equal_sign = false;
	if (!empty($_GET['scope'])) {
		$scope = substr($_GET['scope'], strrpos($_GET['scope'], '.') + 1);
		foreach ($zz_fields as $field) {
			if (!empty($field['field_name']) AND $field['field_name'] == $scope)
				$fieldname = $field['title'];
		}
		$add_equal_sign = true;
	}
	if (substr($_GET['q'], 0, 1) == '<')
		$selection .= '<strong>&lt;</strong> '.htmlspecialchars(substr($_GET['q'], 1));
	elseif (substr($_GET['q'], 0, 1) == '>')
		$selection .= '<strong>&gt;</strong> '.htmlspecialchars(substr($_GET['q'], 1));
	else {
		$q = $_GET['q'];
		if (substr($q, 0, 2) == '\\')
			$q = substr($q, 1);
		if ($add_equal_sign)
			$selection .= $fieldname.' <strong>=</strong> ';
		$selection .= '*'.htmlspecialchars($q).'*';
	}
	return zz_return($selection);
}

function zz_add_modules($modules, $path, $zz_conf_global) {
	$debug_started = false;
	if (!empty($mod['modules']['debug']) OR !empty($zz_conf_global['modules']['debug'])) {
		zz_debug('start', __FUNCTION__);
		$debug_started = true;
	}
//	initialize variables
	$mod['modules'] = false;
	$zz = false;
	$zz_default = false;
	$zz_allowed_params = false;
	$zz_conf = false;

//	import modules
	foreach ($modules as $module) {
		if (file_exists($path.'/'.$module.'.inc.php')) {
			include_once $path.'/'.$module.'.inc.php';
			$mod['modules'][$module] = true;
		} elseif (file_exists($path.'/'.$module.'.php')) {
			include_once $path.'/'.$module.'.php';
			$mod['modules'][$module] = true;
		} else {
			$mod['modules'][$module] = false;
			// int_modules/ext_modules have debug module at different place
			if (!empty($mod['modules']['debug']) OR !empty($zz_conf_global['modules']['debug'])) {
				if (!$debug_started) {
					zz_debug('start', __FUNCTION__);
					$debug_started = true;
				}
				zz_debug("optional module ".$path.'/'.$module.'(.inc).php not included');
			}
		}
		if (!empty($mod['modules']['debug']) OR !empty($zz_conf_global['modules']['debug'])) {
			if (!$debug_started) {
				zz_debug('start', __FUNCTION__);
				$debug_started = true;
			}
			zz_debug($module);
		}
	}
	$mod['vars']['zz'] = $zz;
	$mod['vars']['zz_default'] = $zz_default;
	$mod['vars']['zz_allowed_params'] = $zz_allowed_params;
	$mod['vars']['zz_conf'] = $zz_conf;
	// int_modules/ext_modules have debug module at different place
	if (function_exists('zz_debug')) zz_debug('end');
	return $mod;
}


/** 
 * Prepares moving of folders which are glued to records
 * 
 * 1- retrieve current record from db 
 *    -- TODO: what happens if someone simultaneously accesses this record
 * @param array $zz_tab complete zz_tab array
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_foldercheck_before(&$zz_tab) {
	// in case of deletion or update, save old record to be able
	// to get old filename before deletion or update
	// field_name of ID field for subtables is foreign_key
	foreach (array_keys($zz_tab) as $tab) {
		foreach ($zz_tab[$tab] as $rec => $def) {
			if (!is_numeric($rec)) continue; // we'd like to see only numeric 0 1 2 ...
			if (empty($zz_tab[$tab][$rec]['id']['value'])) continue; // just look for existing records
			$sql = zz_edit_sql($zz_tab[$tab]['sql'], 'WHERE', 
				$zz_tab[$tab]['table'].'.'.$zz_tab[$tab][$rec]['id']['field_name']
				.' = '.$zz_tab[$tab][$rec]['id']['value']);
			$zz_tab[$tab][$rec]['old_record'] = zz_db_fetch($sql, '', '', 'zz_foldercheck_before()');
		}
	}
}

/** 
 * Create, move or delete folders which are connected to records
 * 
 * @param array $zz_tab complete zz_tab array
 *		$zz_tab[0]['folder'][] will be set
 * @param array $zz_conf
 * @return bool true: renaming was successful, false: not successful
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_foldercheck(&$zz_tab, $zz_conf) {
	global $zz_error;
	foreach ($zz_conf['folder'] as $folder) {
		$path = zz_makepath($folder, $zz_tab, 'new', 'file');
		$old_path = zz_makepath($folder, $zz_tab, 'old', 'file');
		if ($old_path == $path) continue;
		if (!file_exists($old_path)) continue;
		if (!file_exists($path)) {
			$success = rename($old_path, $path);
			if ($success) {
				$zz_tab[0]['folder'][] = array('old' => $old_path, 'new' => $path);
			} else { 
				$zz_error[] = array(
					'msg_dev' => 'Folder cannot be renamed.'
				);
				zz_error();
				return false;
			}
		} else {
			$zz_error[] = array(
				'msg_dev' => 'There is already a folder by that name.'
			);
			zz_error();
			return false;
		}
	}
	return true;
}

/** 
 * Construct path from values
 * 
 * @param array $path array with variables which make path
 *		'root' (DOCUMENT_ROOT), 'webroot' (different root for web, all fields
 *		and strings before webroot will be ignored for this), 'mode' (function  
 *		to do something with strings from now on), 'string1...n' (string, number
 *		has no meaning, no sorting will take place, will be shown 1:1),
 *		'field1...n' (field value from record)
 * @param array $zz_tab
 * @param string $record (optional) default 'new', other: 'old' (use updated
 *		record or old record)
 * @param bool $do (optional)
 * @param int $tab (optional)
 * @param int $rec (optional)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_makepath($path, $zz_tab, $record = 'new', $do = false, $tab = 0, $rec = 0) {
	// set variables
	$p = false;
	$modes = false;
	$root = false;		// root
	$rootp = false;		// path just for root
	$webroot = false;	// web root
	global $zz_error;

	// put path together
	foreach ($path as $pkey => $pvalue) {
		if (!$pvalue) continue;
		if ($pkey == 'root') {
			$root = $pvalue;
		} elseif ($pkey == 'webroot') {
			$webroot = $pvalue;
			$rootp = $p;
			$p = '';
		} elseif (substr($pkey, 0, 4) == 'mode') {
			$modes[] = $pvalue;
		} elseif (substr($pkey, 0, 6) == 'string') {
			$p .= $pvalue;
		} elseif (substr($pkey, 0, 5) == 'field') {
			if ($record == 'new') {
				$content = (!empty($zz_tab[$tab][$rec]['POST'][$pvalue])) 
					? $zz_tab[$tab][$rec]['POST'][$pvalue]
					: zz_upload_sqlval($pvalue, $zz_tab[$tab]['sql'], 
						$zz_tab[$tab][$rec]['id']['value'], 
						$zz_tab[$tab]['table'].'.'.$zz_tab[$tab][$rec]['id']['field_name']);
			} elseif ($record == 'old')
				$content = (!empty($zz_tab[$tab][$rec]['old_record']) 
					? $zz_tab[$tab][$rec]['old_record'][$pvalue] : '');
			if ($modes) foreach ($modes as $mode)
				if (function_exists($mode))
					$content = $mode($content);
				else {
					$zz_error[] = array(
						'msg_dev' => sprintf(zz_text('Configuration Error: mode with not-existing function "%s"'), $mode)
					);
				}
			$p .= $content;
			$alt = zz_text('File: ').$content;
			$modes = false;
		}
	}

	switch ($do) {
		case 'file':
			$p = $root.$rootp.$p; // webroot will be ignored
			break;
		case 'local':
			$p = $webroot.$p;
			// return alt as well
			break;
		default:

//	if ($root && !file_exists($root.$link))
//		return false;
//	return $link;

	}
	return $p;
}

/** 
 * Translate text if possible or write back text string to be translated
 * 
 * @param string $string		Text string to be translated
 * @return string $string		Translation of text
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_text($string) {
	global $text;
	global $zz_conf;
	if (!isset($text[$string])) {
		// write missing translation to somewhere.
		// TODO: check logfile for duplicates
		// TODO: optional log directly in database
		if (!empty($zz_conf['log_missing_text'])) {
			$log_message = '$text["'.addslashes($string).'"] = "'.$string.'";'."\n";
			$log_file = sprintf($zz_conf['log_missing_text'], $zz_conf['language']);
			error_log($log_message, 3, $log_file);
			chmod($log_file, 0664);
		}
		return $string;
	} else
		return $text[$string];
}

/** 
 * Merges Array recursively: replaces old with new keys, adds new keys
 * 
 * @param array $old			Old array
 * @param array $new			New array
 * @return array $merged		Merged array
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_array_merge($old, $new) {
	foreach ($new as $index => $value) {
		if (is_array($value)) {
			if (!empty($old[$index])) {
				$old[$index] = zz_array_merge($old[$index], $new[$index]);
			} else
				$old[$index] = $new[$index];
		} else {
			if (is_numeric($index) AND (!in_array($value, $old))) {
				$old[] = $value;		// numeric keys will be appended, if new
			} else {
				$old[$index] = $value;	// named keys will be replaced
			}
		}
	}
	return $old;
}

/**
 * counts number of records that will be caught by current SQL query
 *
 * @param string $sql
 * @param string $id_field
 * @return int $lines;
 */
function zz_count_rows($sql, $id_field) {
	$sql = trim($sql);
	// if it's not a SELECT DISTINCT, we can use COUNT, that's faster
	// GROUP BY also does not work with COUNT
	if (substr($sql, 0, 15) != 'SELECT DISTINCT'
		AND !stristr($sql, 'GROUP BY')) {
		$sql = zz_edit_sql($sql, 'SELECT', 'COUNT('.$id_field.')', 'replace');
		$lines = zz_db_fetch($sql, '', 'single value');
	} else {
		$lines = zz_db_fetch($sql, $id_field, 'count');
	}
	if (!$lines) $lines = 0;
	return $lines;
}

function zz_print_r($array, $color = false, $caption = 'Variables') {
	echo '<table class="zzvariables" style="text-align: left;'.($color ? ' background: '.$color.';' : '').'">';
	echo '<caption>'.$caption.'</caption>';
	$vars = zz_print_multiarray($array);
	foreach ($vars as $var) {
		echo '<tr><th' // style="padding-left: '
			//.((substr_count($var['key'], '[')-1)*1)
			//.'em;"
			.'>'.$var['key'].'</th><td>'.$var['value'].'</td></tr>'."\n";
	}
	echo '</table>';
}

/**
 * transforms a multidimensional array into an array with key => value 
 * where key includes not only the key but also all parent keys in []
 *
 * @param array array to be printed
 * @param string $parent_key (optional, internal value, hierarchy of parent keys)
 * @return array $vars
 *		'key' => full key, 'value' => html'escaped value
 * @see zz_print_r(), zz_querystring_to_hidden()
 */
function zz_print_multiarray($array, $parent_key = '') {
	$vars = array();
	foreach ($array as $key => $value) {
		if ($parent_key !== '')
			$mykey = $parent_key.'['.$key.']';
		else
			$mykey = $key;
		if (is_array($value)) {
			$vars = array_merge($vars, zz_print_multiarray($value, $mykey));
		} else {
			$vars[] = array(
				'key' => $mykey,
				'value' => htmlspecialchars($value)
			);
		}
	}
	return $vars;
}

/** 
 * Protection against overwritten values, set values and defaults for zzform_multi()
 * Writes values, default values and where-values into POST-Array
 * initializes unset field names
 * 
 * @param array $post		POST records of main table or subtable
 * @param array $fields		$zz ...['fields']-definitions of main or subtable
 * @return array $post		POST
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_check_def_vals($post, $fields, $where = false) {
	foreach ($fields as $field) {
		if (empty($field['field_name'])) continue;
		// for all values, overwrite posted values with needed values
		if (!empty($field['value'])) 
			$post[$field['field_name']] = $field['value'];
		// just for values which are not set (!) set default value
		// (not for empty strings!)
		if (!empty($field['default']) AND !isset($post[$field['field_name']]))
			$post[$field['field_name']] = $field['default'];
		// most important, therefore last: [where]
		if (!empty($where[$field['field_name']]))
			$post[$field['field_name']] = $where[$field['field_name']];
		// if it's a mass upload or someone cuts out field_names, treat these fields as if
		// nothing was posted
		// some fields must not be initialized, so ignore them
		$unwanted_field_types = array('id', 'foreign_key', 'translation_key', 'display');
		if (!isset($post[$field['field_name']])
			AND !in_array($field['type'], $unwanted_field_types))
			$post[$field['field_name']] = '';
	}
	return $post;
}

/** 
 * Initialize $_FILES-Array for each uploaded file so that zzform knows
 * that there's something to do
 * 
 * @param array $files		FILES array
 * @return array $files		FILES array, initialized where neccessary
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_check_def_files($files) {
	foreach ($files as $key => $values) {
		if (count($values) == 1 AND !empty($values['name'])) {
			$files[$key]['type'] = $values['name'];
			$files[$key]['tmp_name'] = $values['name'];
			$files[$key]['size'] = $values['name'];
			$files[$key]['error'] = $values['name'];
		} elseif (count($values) == 1 AND isset($values['name'])) {
			$files[$key]['type'] = false;
			$files[$key]['tmp_name'] = false;
			$files[$key]['size'] = 0;
			$files[$key]['error'] = 4; // no file was uploaded
		}
	}
	return $files;
}

/** 
 * Sets $zz['mode'], $zz['action'] and several $zz_conf-variables
 * according to what the user request and what the user is allowed to request
 * 
 * @param array $zz
 * @param array $zz_conf
 *		'show_record', 'access', 'list_access' etc. pp.
 *		'modules'[debug]
 * @param array $zz_var --> will be changed as well
 *		'where_with_unique_id' bool if it's just one record to be shown (true)
 * @param array $zz_allowed_params
 * @global array $zz_conf
 * @global array $_POST
 * @return array 
 *		$zz array
 *		$zz_var array
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_record_access($zz, $zz_var, $zz_allowed_params) {
	global $zz_conf;
	
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	// initialize variables
	$zz['action'] = false;
	$zz_conf['show_record'] = true; // show record somehow (edit, view, ...)
	
	// set mode and action according to $_GET and $_POST variables
	// do not care yet if actions are allowed

	if ($zz['mode'] == 'export') {
		$zz_conf['access'] = 'export'; 	// Export overwrites all
		$zz_conf['show_record'] = false;
	} elseif (isset($_POST['zz_subtables'])) {
		// ok, no submit button was hit but only add/remove form fields for
		// detail records in subtable, so set mode accordingly (no action!)
		if (!empty($_POST['zz_action']) AND $_POST['zz_action'] == 'insert') {
			$zz['mode'] = 'add';
		} elseif (!empty($_POST['zz_action']) AND $_POST['zz_action'] == 'update'
			AND !empty($_POST[$zz_var['id']['field_name']])) {
			$zz['mode'] = 'edit';
			$id_value = $_POST[$zz_var['id']['field_name']];
		} else {
			$zz['mode'] = false; // this should not occur if form is used legally
		}
	} else {
		// standard case, get mode from URL
		if (!empty($_GET['mode'])) {
			if (in_array($_GET['mode'], $zz_allowed_params['mode'])) {
				$zz['mode'] = $_GET['mode']; // set mode from URL
				if (($zz['mode'] == 'edit' OR $zz['mode'] == 'delete' OR $zz['mode'] == 'show')
					AND !empty($_GET['id'])) {
					$id_value = $_GET['id'];
				}
			} else {
				// illegal parameter, don't set a mode at all
				$zz['mode'] = false;
			}
		} else {
			if (!empty($_POST['zz_action']) 
				AND in_array($_POST['zz_action'], $zz_allowed_params['action'])) {
				// triggers valid database action
				$zz['action'] = $_POST['zz_action']; 
				if (!empty($_POST[$zz_var['id']['field_name']]))
					$id_value = $_POST[$zz_var['id']['field_name']];
				$zz['mode'] = false;
			} elseif ($zz_var['where_with_unique_id']) {
				// just review the record
				$zz['mode'] = 'review'; 
			} else {
				// no record is selected, basic view when starting to edit data
				// list mode only
				$zz['mode'] = 'list_only';
			}
		}
	}
	// write main id value, might have been written by a more trustful instance
	// beforehands ($_GET['where'] etc.)
	if (empty($zz_var['id']['value']) AND !empty($id_value))
		$zz_var['id']['value'] = $id_value;
	elseif (!isset($zz_var['id']['value']))
		$zz_var['id']['value'] = '';

	// if $zz_conf['conditions'] -- check them
	// get conditions if there are any, for access
	$zz_conf['list_access'] = array(); // for old variables

	if (!empty($zz_conf['modules']['conditions']) AND !empty($zz['conditions'])
		AND !empty($zz_conf['conditions']) AND $zz_var['id']['value']) {
		$zz_conditions = zz_conditions_record_check($zz, $zz_var);
		// save old variables for list view
		$saved_variables = array('access', 'add', 'edit', 'delete', 'view', 'details');
		foreach ($saved_variables as $var) {
			if (isset($zz_conf[$var])) $zz_conf['list_access'][$var] = $zz_conf[$var];
		}
		// overwrite new variables
		$zz_conf = zz_conditions_merge($zz_conf, $zz_conditions['bool'], $zz_var['id']['value']);
	}


	// set (and overwrite if neccessary) access variables, i. e.
	// $zz_conf['add'], $zz_conf['edit'], $zz_conf['delete']
	
	if ($zz_conf['access'] == 'add_then_edit') {
		if ($zz_var['id']['value']) {
			$zz_conf['access'] = 'edit_only';
		} else {
			$zz_conf['access'] = 'add_only';
		}
	}

	switch ($zz_conf['access']) { // access overwrites individual settings
	// first the record specific or overall settings
	case 'export':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['show_list'] = true;		// list
		$zz_conf['show_record'] = false;	// don't show record
		$zz_conf['this_limit'] = false; 	// always export all records
		$zz_conf['backlink'] = false; 		// don't show back to overview link
		$zz_conf['search'] = false; 		// don't show search form
		break;
	case 'add_only';
		if (!is_array($zz_conf['add'])) $zz_conf['add'] = true;	// add record (form)
		$zz_conf['add_link'] = false;		// add record (links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['search'] = false;			// no search form
		$zz_conf['show_list'] = false;		// no list
		if (empty($_POST)) $zz['mode'] = 'add';
		break;
	case 'edit_only';
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['search'] = false;			// no search form
		$zz_conf['show_list'] = false;		// no list
		if (empty($_POST)) $zz['mode'] = 'edit';
		break;
	default:
		// now the settings which apply to both record and list
		$zz_conf = zz_listandrecord_access($zz_conf);
		break;
	}

	if ($zz_var['where_with_unique_id']) { // just for record, not for list
		// in case of where and not unique, ie. only one record in table, don't do this.
		$zz_conf['show_list'] = false;		// don't show table
		$zz_conf['add'] = false;			// don't show add record (form+links)
	}

	if (!isset($zz_conf['add_link']))
		$zz_conf['add_link'] = ($zz_conf['add'] ? true : false); // Link Add new ...

	if (!$zz_conf['add'] AND $zz['mode'] == 'add') $zz['mode'] = false;
	if (!$zz_conf['edit'] AND $zz['mode'] == 'edit') $zz['mode'] = false; // show?
	if (!$zz_conf['delete'] AND $zz['mode'] == 'delete') $zz['mode'] = false; // show?

	if (!$zz_conf['add'] AND $zz['action'] == 'insert') $zz['action'] = false;
	if (!$zz_conf['edit'] AND $zz['action'] == 'update') $zz['action'] = false;
	if (!$zz_conf['delete'] AND $zz['action'] == 'delete') $zz['action'] = false;

	if ($zz_conf['access'] == 'edit_details_only') $zz['access'] = 'show';
	if ($zz_conf['access'] == 'edit_details_and_add' 
		AND $zz['mode'] != 'add' AND $zz['action'] != 'insert')
		$zz['access'] = 'show';

	// now, mode is set, do something depending on mode
	
	if (in_array($zz['mode'], array('edit', 'delete', 'add')) 
		AND !$zz_conf['show_list_while_edit']) $zz_conf['show_list'] = false;

	if ($zz['mode'] == 'list_only') {
		$zz_conf['show_record'] = false;	// don't show record
	}

	return zz_return(array($zz, $zz_var));
}

/** 
 * Sets configuration variables depending on $var['access']
 * Access possible for list and for record view
 * 
 * @param array $zz_conf
 * @return array $zz_conf changed zz_conf-variables
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_listandrecord_access($zz_conf) {
	switch ($zz_conf['access']) {
	case 'show':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = true;			// show record (links)
		break;
	case 'show_and_add':
		if (!is_array($zz_conf['add'])) $zz_conf['add'] = true; // add record (form+links)
		$zz_conf['edit'] = false;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = true;			// show record (links)
		break;
	case 'show_edit_add';
		if (!is_array($zz_conf['add'])) $zz_conf['add'] = true; // add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = true;			// show record (links)
		break;
	case 'show_and_delete';
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = true;			// delete record (form+links)
		$zz_conf['view'] = true;			// show record (links)
		break;
	case 'edit_details_only':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		break;
	case 'edit_details_and_add':
		if (!is_array($zz_conf['add'])) $zz_conf['add'] = true; // add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		break;
	case 'none':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['show_record'] = false;	// don't show record
		break;
	case 'search_but_no_list':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['show_record'] = false;	// don't show record
		$zz_conf['show_list'] = true;		// show list, further steps will set in zz_list()
		break;
	case 'all':
		if (!is_array($zz_conf['add'])) $zz_conf['add'] = true;	// add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = true;			// delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		break;
	default:
		// do not change anything, just initalize if required
		if (!isset($zz_conf['add'])) $zz_conf['add'] = true;
		if (!isset($zz_conf['edit'])) $zz_conf['edit'] = true;
		if (!isset($zz_conf['delete'])) $zz_conf['delete'] = false;
		if (!isset($zz_conf['view'])) $zz_conf['view'] = false;
	}
	if (!isset($zz_conf['add_link']))
		$zz_conf['add_link'] = ($zz_conf['add'] ? true : false); // Link Add new ...
	return $zz_conf;
}


/** 
 * Sets record specific configuration variables that might be changed individually
 * 
 * @param array $zz_conf
 * @return array $zz_conf_record subset of $zz_conf
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_record_conf($zz_conf) {
	$wanted_keys = array('access', 'edit', 'delete', 'add', 'view', 'conditions',
		'details', 'details_url', 'details_base', 'details_target', 'details_referer',
		'max_select', 'max_select_val_len'
	);
	$zz_conf_record = array();
	foreach ($wanted_keys as $key) {
		if (isset($zz_conf[$key])) {
			$zz_conf_record[$key] = $zz_conf[$key];
		} elseif ($key == 'access') {
			$zz_conf_record['access'] = '';
		}
	}
	return $zz_conf_record;
}

/**
 * Fetches records from database and returns array
 * identical to wrap_db_fetch, more or less
 * 
 * - without $id_field_name: expects exactly one record and returns
 * the values of this record as an array
 * - with $id_field_name: uses this name as unique key for all records
 * and returns an array of values for each record under this key
 * - with $id_field_name and $array_format = "key/value": returns key/value-pairs
 * - with $id_field_name = 'dummy' and $array_format = "single value": returns
 * just first value as an array e. g. [3] => 3
 * @param string $sql SQL query string
 * @param string $id_field_name optional, if more than one record will be 
 *	returned: required; field_name for array keys
 *  if it's an array with two strings, this will be used to construct a 
 *  hierarchical array for the returned array with both keys
 * @param string $format optional, currently implemented
 *  'count' = returns count of rows
 *	'id as key' = returns array($id_field_value => true)
 *	"key/value" = returns array($key => $value)
 *	"single value" = returns $value
 *	"object" = returns object
 *	"numeric" = returns lines in numerical array [0 ... n] instead of using field ids
 * @param string $info (optional) information about where this query was called
 * @param int $error let's you set error level, default = E_USER_ERROR
 * @return array with queried database content
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo give a more detailed explanation of how function works
 */
function zz_db_fetch($sql, $id_field_name = false, $format = false, $info = false, $error = E_USER_ERROR) {
	global $zz_conf;
	$lines = array();
	$error = false;
	$result = mysql_query($sql);
	if ($result) {
		if (!$id_field_name) {
			// only one record
			if (mysql_num_rows($result) == 1) {
	 			if ($format == 'single value') {
					$lines = mysql_result($result, 0, 0);
	 			} elseif ($format == 'object') {
					$lines = mysql_fetch_object($result);
				} else {
					$lines = mysql_fetch_assoc($result);
				}
			}
 		} elseif (is_array($id_field_name) AND mysql_num_rows($result)) {
			if ($format == 'object') {
				while ($line = mysql_fetch_object($result)) {
					if (count($id_field_name) == 3) {
						$lines[$line->$id_field_name[0]][$line->$id_field_name[1]][$line->$id_field_name[2]] = $line;
					} else {
						$lines[$line->$id_field_name[0]][$line->$id_field_name[1]] = $line;
					}
				}
 			} else {
 				// default or unknown format
				while ($line = mysql_fetch_assoc($result)) {
		 			if ($format == 'single value') {
						// just get last field, make sure that it's not one of the id_field_names!
		 				$values = array_pop($line);
		 			} else {
		 				$values = $line;
		 			}
					if (count($id_field_name) == 4) {
						$lines[$line[$id_field_name[0]]][$line[$id_field_name[1]]][$line[$id_field_name[2]]][$line[$id_field_name[3]]] = $values;
					} elseif (count($id_field_name) == 3) {
						$lines[$line[$id_field_name[0]]][$line[$id_field_name[1]]][$line[$id_field_name[2]]] = $values;
					} else {
						$lines[$line[$id_field_name[0]]][$line[$id_field_name[1]]] = $values;
					}
				}
			}
 		} elseif (mysql_num_rows($result)) {
 			if ($format == 'count') {
 				$lines = mysql_num_rows($result);
 			} elseif ($format == 'single value') {
 				// you can reach this part here with a dummy id_field_name
 				// because no $id_field_name is needed!
				while ($line = mysql_fetch_array($result)) {
					$lines[$line[0]] = $line[0];
				}
 			} elseif ($format == 'id as key') {
				while ($line = mysql_fetch_array($result)) {
					$lines[$line[$id_field_name]] = true;
				}
 			} elseif ($format == 'key/value') {
 				// return array in pairs
				while ($line = mysql_fetch_array($result)) {
					$lines[$line[0]] = $line[1];
				}
			} elseif ($format == 'object') {
				while ($line = mysql_fetch_object($result))
					$lines[$line->$id_field_name] = $line;
			} elseif ($format == 'numeric') {
				while ($line = mysql_fetch_assoc($result))
					$lines[] = $line;
 			} else {
 				// default or unknown format
				while ($line = mysql_fetch_assoc($result)) {
					if (!isset($line[$id_field_name])) {
		 				$error = true;
 						$info .= ' '.sprintf(zz_text('Field <code>%s</code> does not exist'), $id_field_name);
					}
					$lines[$line[$id_field_name]] = $line;
				}
			}
		}
	} else $error = true;
	if ($zz_conf['modules']['debug']) zz_debug('sql (rows: '
		.($result ? mysql_num_rows($result) : 0).')'.($info ? ': '.$info : ''), $sql);
	if ($error) {
		$error_functions = array('zz_error', 'wrap_error');
		$my_error_func = 'zz_error';
		if (substr($_SERVER['SERVER_NAME'], -6) == '.local'
			AND $my_error_func == 'wrap_error') {
			echo mysql_error();
			echo '<br>'.$sql;
		}
		global $zz_debug;
		$current = end($zz_debug['function']);
		$msg_dev = 'Error in SQL query in function'
			.(!empty($current['function']) ? ' '.$current['function'] : '')
			.($info ? ' - '.$info.'.' : '');

		if (function_exists('wwrap_error')) {
			wrap_error(sprintf($msg_dev."\n\n%s\n\n%s", mysql_error(), $sql), $error);
		} elseif (function_exists('zz_error')) {
			global $zz_error;
			$zz_error[] = array(
				'msg_dev' => $msg_dev,
				'mysql' => mysql_error(), 
				'query' => $sql,
				'level' => $error
			);
			return zz_error();
		}
	}
	return $lines;
}

/**
 * Escapes values for database input
 *
 * @param string $value
 * @return string escaped $value
 */
function zz_db_escape($value) {
	// should never happen, just during development
	if (!$value) return '';
	if (is_array($value) OR is_object($value)) {
//		global $zz_conf;
//		if (!empty($zz_conf['modules']['debug']) AND $zz_conf['debug']) {
			echo 'Value is not string: ';
			zz_print_r($value);
//		}
	}
	if (function_exists('mysql_real_escape_string')) { 
		// just from PHP 4.3.0 on
		return mysql_real_escape_string($value);
	} else {
		return addslashes($value);
	}
}

/**
 * Formats names of tables, first letter uppercase, replaces _ against /
 *
 * @param string $table name of table
 * @return string formatted table name
 */
function zz_nice_tablenames($table) {
	global $zz_conf;
	if ($zz_conf['prefix']) { // makes the response look nicer
		if (strtolower(substr($table, 0, strlen($zz_conf['prefix']))) == strtolower($zz_conf['prefix']))
			$table = substr($table, strlen($zz_conf['prefix']));
		else {
			$zz_error[] = array(
				'msg_dev' => sprintf(zz_text('Table prefix is incorrect somehow: %s'), 
					substr($table, 0, strlen($zz_conf['prefix'])))
			);
		}
	}
	
	$table = explode('_', $table);
	foreach (array_keys($table) as $id) $table[$id] = ucfirst($table[$id]);
	$table = implode('/', $table);
	return $table;
}

/**
 * changes own URL, adds some extra parameter
 *
 * @param string $mode (if = 'add', keeps add-parameter in URL)
 * @param array $zz_conf
 * @return string extra GET parameters for links
 */
function zz_extra_get_params($mode, $zz_conf) {
	// Extra GET Parameter
	$keep_query = array();
	$keep_fields = array('where', 'var', 'order', 'group', 'q', 'scope', 'dir', 
		'referer', 'url', 'nolist', 'filter', 'debug');
	if ($mode == 'add') {
		$keep_fields[] = 'add';
	}
	foreach ($keep_fields AS $key) {
		if (!empty($_GET[$key])) $keep_query[$key] = $_GET[$key];
	}
	// write some query strings differently
	if (isset($_GET['nolist'])) 
		$keep_query['nolist'] = true;
	if ($zz_conf['this_limit'] && $zz_conf['this_limit'] != $zz_conf['limit'])
		$keep_query['limit'] = $zz_conf['this_limit'];

	$extra_get = http_build_query($keep_query);
	if ($extra_get) 
		$extra_get = '&amp;'.str_replace('&', '&amp;', $extra_get);
	return $extra_get;
}

/**
 * sets database name and checks if a database by that name exists
 *
 * @param string $table table name, might include database name
 * @return array $dbname, $table - names of main database and main table
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_db_connection($table) {
	global $zz_error;
	global $zz_conf;

	// get current db to SELECT it again before exitting
	// might be that there was no database connection established so far
	// therefore the @, but it does not matter because we simply want to
	// revert to the current database after exitting this script
	$result = @mysql_query('SELECT DATABASE()');
	$zz_conf['db_current'] = $result ? mysql_result($result, 0, 0) : '';
	// main database normally is the same db that zzform() uses for its
	// operations, but if you use several databases, this is the one which
	// is the main db, i. e. the one that will be used if no other database
	// name is specified
	$zz_conf['db_main'] = false;

	if (!isset($zz_conf['db_connection'])) include_once $zz_conf['dir_custom'].'/db.inc.php';
	// get db_name.
	// 1. best way: put it in zz_conf['db_name']
	if (!empty($zz_conf['db_name'])) {
		$db = mysql_select_db($zz_conf['db_name']);
		if (!$db) {
			$zz_error[] = array(
				'mysql' => mysql_error(),
				'query' => 'SELECT DATABASE("'.$zz_conf['db_name'].'")',
				'level' => E_USER_ERROR
			);
			return false;
		}
		$dbname = $zz_conf['db_name'];
	// 2. alternative: use current database
	} else {
		$result = mysql_query('SELECT DATABASE()');
		if (mysql_error()) {
			$zz_error[] = array(
				'mysql' => mysql_error(),
				'query' => 'SELECT DATABASE()',
				'level' => E_USER_ERROR
			);
			return false;
		}
		$zz_conf['db_name'] = mysql_result($result, 0, 0);
		$dbname = $zz_conf['db_name'];
	}

	// 3. alternative plus foreign db: put it in zz['table']
	if (preg_match('~(.+)\.(.+)~', $table, $db_name)) { // db_name is already in zz['table']
		if ($zz_conf['db_name'] AND $zz_conf['db_name'] != $db_name[1]) {
			// this database is different from main database, so save it here
			// for later
			$zz_conf['db_main'] = $zz_conf['db_name'];
		} elseif (!$zz_conf['db_name']) { 
			// no database selected, get one, quick!
			$dbname = mysql_select_db($db_name[1]);
			if (!$dbname) {
				$zz_error[] = array(
					'mysql' => mysql_error(),
					'query' => 'SELECT DATABASE("'.$db_name[1].'")',
					'level' => E_USER_ERROR
				);
				return false;
			}
		}
		$zz_conf['db_name'] = $db_name[1];
		$dbname = $db_name[1];
		$table = $db_name[2];
	}

	if (empty($zz_conf['db_name'])) {
		$zz_error[] = array(
			'msg_dev' => 'Please set the variable <code>$zz_conf[\'db_name\']</code>.'
				.' It has to be set to the main database name used for zzform.',
			'level' => E_USER_ERROR
		);
		return false;
	}
	return array($dbname, $table);
}

/**
 * checks filter, sets default values and hierarchy values
 *
 * @global array $zz_conf
 *		'filter', 'show_hierarchy' (will be changed if corresponding filter)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_apply_filter() {
	global $zz_conf;
	if (empty($zz_conf['filter'])) return false;

	// initialize filter, set defaults
	foreach ($zz_conf['filter'] AS $filter) {
		if (empty($filter['identifier'])) $filter['identifier'] = urlencode($filter['title']);
		// set default filter, default default filter is 'all'
		if (!empty($filter['default_selection']) AND !isset($_GET['filter'][$filter['identifier']])) {
			$_GET['filter'][$filter['identifier']] = $filter['default_selection'];
		}
	}
	// set filter for complete form
	if (!empty($_GET['filter'])) {
		foreach ($zz_conf['filter'] AS $index => $filter) {
			if (!isset($filter['selection'])) 
				$filter['selection'] = $zz_conf['filter'][$index]['selection'] = array();
			if (in_array($filter['identifier'], array_keys($_GET['filter']))
				AND in_array($_GET['filter'][$filter['identifier']], array_keys($filter['selection']))
				AND $filter['type'] == 'show_hierarchy') {
			// it's a valid filter, so apply it.
				$zz_conf['show_hierarchy'] = $_GET['filter'][$filter['identifier']];
			}
		}
	}
}

/**
 * checks if there is a parameter in the URL (where, add, filter) that
 * results in a WHERE condition applied to the main SQL query
 *
 * @global array $zz_conf
 *		'filter' will be checked for 'where'-filter and set if there is one
 * @return array $zz_var
 *		'where_condition' (conditions set by where, add and filter), 'zz_fields'
 *		(values for fields depending on where conditions)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_get_where_conditions() {
	global $zz_conf;

	$zz_var = array();
	// WHERE: Add with suggested values
	$zz_var['where_condition'] = array();
	if (!empty($_GET['where'])) {
		$zz_var['where_condition'] = $_GET['where'];
	}

	// ADD: overwrite write_once with values, in case there are identical fields
	if (!empty($_GET['add'])) {
		$zz_var['where_condition'] = array_merge($zz_var['where_condition'], $_GET['add']);
		foreach ($_GET['add'] as $key => $value) {
			$zz_var['zz_fields'][$key]['value'] = $value;
			$zz_var['zz_fields'][$key]['type'] = 'hidden';
		}
	}

	// FILTER: check if there's a 'where'-filter
	if (empty($zz_conf['filter'])) $zz_conf['filter'] = array();
	foreach ($zz_conf['filter'] AS $index => $filter) {
		if ($filter['type'] != 'where') continue;
		if (!empty($_GET['filter'][$filter['identifier']])) {
			$zz_var['where_condition'][$filter['where']] = $_GET['filter'][$filter['identifier']];
		} elseif (!empty($filter['default_selection'])) {
			$zz_var['where_condition'][$filter['where']] = $filter['default_selection'];
		}
		// 'where'-filters are beyond that 'list'-filters
		$zz_conf['filter'][$index]['type'] = 'list';
	}

	return $zz_var;
}

/**
 * gets unique and id fields for further processing
 *
 * @param array $zz_var
 * @param array $fields
 * @global array $zz_error
 * @return array $zz_var
 *		'id'[value], 'id'[field_name], 'unique_fields'
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_get_unique_fields($zz_var, $fields) {
	global $zz_error;

	// set id to false
	$zz_var['id']['value'] = false;
	$zz_var['id']['field_name'] = false;
	$zz_var['unique_fields'] = array(); // for WHERE

	foreach ($fields AS $field) {
		// set ID fieldname
		if (!empty($field['type']) AND $field['type'] == 'id') {
			if ($zz_var['id']['field_name']) {
				$zz_error['msg'] = 'Only one field may be defined as "id"!';
				return false;
			}
			$zz_var['id']['field_name'] = $field['field_name'];
		}
		if (!empty($field['unique']))
			$zz_var['unique_fields'][$field['field_name']] = true;
	}
	return $zz_var;
}

/**
 * applies where conditions to get different sql query, id values and some
 * further variables for nice headings etc.
 *
 * @param array $zz_var
 *		'where_condition' from zz_get_where_conditions(), 'unique_fields'
 * @param string $sql Main SQL query
 * @param string $table Name of main table
 * @param array $table_for_where (optional)
 * @global array $zz_conf checks for 'modules'['debug']
 * @return array
 *		string $sql = modified main query (if applicable)
 *		array $zz_var
 *			'where', 'where_with_unique_id', 'where_condition', 'id', 
 *			'unique_fields'
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @see zz_get_where_conditions(), zz_get_unique_fields()
 */
function zz_apply_where_conditions($zz_var, $sql, $table, $table_for_where = array()) {
	global $zz_conf;

	// set some keys
	$zz_var['where'] = false;
	$zz_var['where_with_unique_id'] = false;
	
	if (!$zz_var['where_condition']) return array($sql, $zz_var);

	foreach ($zz_var['where_condition'] as $field_name => $value) {
		// check for illegal characters
		if (strstr($field_name, ' ') OR strstr($field_name, ';')) {
			unset($zz_var['where_condition'][$field_name]);
			continue;
		}
		$submitted_field_name = $field_name;
		// check if field_name comprises table_name
		if (strstr($field_name, '.')) {
			$field_tab = explode('.', $field_name);
			$table_name = zz_db_escape($field_tab[0]);
			$field_name = zz_db_escape($field_tab[1]);
			unset($field_tab);
		} else {
			// allows you to set a different (or none at all) table name for WHERE queries
			if (isset($table_for_where[$field_name]))
				$table_name = $table_for_where[$field_name];
			else
				$table_name = $table;
			$field_name = zz_db_escape($field_name);
		}
		$field_reference = ($table_name ? $table_name.'.'.$field_name : $field_name);
		// restrict list view to where, but not to add
		if (empty($_GET['add'][$submitted_field_name])) {
			if (!empty($zz_var['where_condition'][$field_name])
				AND $zz_var['where_condition'][$field_name] == 'NULL') {
				$sql = zz_edit_sql($sql, 'WHERE', 
					'ISNULL('.$field_reference.')');
				continue; // don't use NULL as where variable!
			} else {
				$sql = zz_edit_sql($sql, 'WHERE', 
					$field_reference." = '".zz_db_escape($value)."'");
			}
		}

// hier auch fuer write_once
		$zz_var['where'][$table_name][$field_name] = $value;

		if ($field_name == $zz_var['id']['field_name']) {
			$zz_var['where_with_unique_id'] = true;
			$zz_var['id']['value'] = $value;
		} elseif (in_array($field_name, $zz_var['unique_fields'])) {
			$zz_var['where_with_unique_id'] = true;
		}
	}
	// in case where is not combined with ID field but UNIQUE field
	// (e. g. identifier with UNIQUE KEY) retrieve value for ID field from database
	if (!($zz_var['id']['value'])) {
		if ($zz_conf['modules']['debug']) zz_debug("where_conditions", $sql);
		$line = zz_db_fetch($sql, '', '', 'WHERE; ambiguous values in ID?');
		if ($line) {
			$zz_var['id']['value'] = $line[$zz_var['id']['field_name']];
//		} else {
//			$zz_error[] = array(
//				'msg_dev' => zz_text('Database error. This database has ambiguous values in ID field.'),
//				'level' => E_USER_ERROR
//			);
//			$zz['output'].= '</div>';
//			return zz_error(); // exit script
		}
		if (!$zz_var['id']['value']) $zz_var['where_with_unique_id'] = false;
	}
	
	return array($sql, $zz_var);
}

/** 
 * exit function for zzform functions, should always be called to adjust some settings
 *
 * @param mixed $return return parameter
 * @return mixed return parameter
 */
function zz_return($return = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('end');
	return $return;
}

/**
 * sets some $zz-definitions for records depending on existing definition for
 * translations, subtabes, uploads, write_once-fields
 *
 * @param array $zz
 * @return array $zz
 *		'subtables', 'save_old_record', 'upload_form', some minor 'fields' 
 *		changes
 */
function zz_set_fielddefs_for_record($zz) {
	$rec = 1;
	$zz['subtables'] = array();			// key: $rec, value: $no
	$zz['save_old_record'] = array();	// key: int, value: $no
	$zz['upload_form'] = false;			// false: no upload, true: upload possible

	foreach (array_keys($zz['fields']) as $no) {
		// translations
		if (!empty($zz['fields'][$no]['translate_field_index'])
			AND isset($zz['fields'][$zz['fields'][$no]['translate_field_index']]['translation'])
			AND !$zz['fields'][$zz['fields'][$no]['translate_field_index']]['translation'])
		{
			unset ($zz['fields'][$no]);
			continue;
		}
		if (!isset($zz['fields'][$no]['type'])) continue;
		switch ($zz['fields'][$no]['type']) {
		case 'subtable':
			// save number of subtable, get table_name and check whether sql
			// is unique, look for upload form as well
			$zz['subtables'][$rec] = $no;
			if (!isset($zz['fields'][$no]['table_name']))
				$zz['fields'][$no]['table_name'] = $zz['fields'][$no]['table'];
			$zz['fields'][$no]['subtable'] = $rec;
			$rec++;
			if (!empty($zz['fields'][$no]['sql_not_unique'])) {
				// must not change record where main record is not directly 
				// superior to detail record 
				// - foreign ID would be changed to main record's id
				$zz['fields'][$no]['access'] = 'show';
			}
			foreach ($zz['fields'][$no]['fields'] as $subfield) {
				if (empty($subfield['type'])) continue;
				if ($subfield['type'] != 'upload_image') continue;
				$zz['upload_form'] = true;
			}
			break;
		case 'upload_image':
			$zz['upload_form'] = true;
			break;
		case 'write_once':
		case 'display':
			$zz['save_old_record'][] = $no;
			break;
		}
	}
	return $zz;
}
?>