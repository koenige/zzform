<?php 

/*
	zzform Scripts

	miscellaneous functions
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2006

*/

function zz_error ($zz_error) {
	global $zz_error;
	global $zz_conf;
	$output = '';
	if (!isset($zz_error)) $zz_error = array();
	if (!isset($zz_error['msg'])) $zz_error['msg'] = '';
	if (!isset($zz_error['level'])) $zz_error['level'] = '';
	if (!isset($zz_error['type'])) $zz_error['type'] = '';
	if (!isset($zz_error['query'])) $zz_error['query'] = '';
	if (!isset($zz_error['mysql'])) $zz_error['mysql'] = '';
	if (!empty($zz_error['msg'])) {
		$output = '<div class="error">';
		if ($zz_error['level'] == 'warning') $output.= '<strong>'.$text['Warning'].'!</strong> ';
		//$output.= $text[$zz_error['msg']];
		if (trim($zz_error['msg'])) $output.= $zz_error['msg'].'<br>';
		if ($zz_error['mysql']) $output.= $zz_error['mysql'].': ';
		$output.= $zz_error['query'];
		$output.= '</div>';
		$zz_error = '';
	}
	$zz_error['msg'] = '';
	$zz_error['level'] = '';
	$zz_error['type'] = '';
	$zz_error['query'] = '';
	$zz_error['mysql'] = '';
	if ($zz_conf['error_handling'] == 'mail' && $zz_conf['error_mail_to']) {
		mail ($zz_conf['error_mail_to'], '['.$zz_conf['project'].']', $output, 'From: '.$zz_conf['error_mail_from']);
		return false;
	} else
		return $output;
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

function check_maxlength($field, $maintable) {
	$sql = 'SHOW COLUMNS FROM '.$maintable.' LIKE "'.$field.'"';
	$result = mysql_query($sql);
	if ($result)
		if (mysql_num_rows($result) == 1) {
			$maxlength = mysql_fetch_array($result);
			//preg_match('/varchar\((\d+)\)/s', $maxlength['Type'], $my_result);
			//if ($my_result) return $my_result[1];
			preg_match('/\((\d+)\)/s', $maxlength['Type'], $my_result);
			if ($my_result) return ($my_result[1]);
		}
	return false;
}

function check_number($number) {
	$number = trim($number);
	if (!preg_match('/^[0-9.,]*$/', $number)) return false; // possible feature: return doubleval $number to get at least something
	if ($dot = strpos($number, '.') AND $comma = strpos($number, ','))
		if ($dot > $comma) $number = str_replace(',', '', $number);
		else {
			$number = str_replace('.', '', $number);
			$number = str_replace(',', '.', $number);
		}
	elseif (strstr($number, ',')) $number = str_replace(',', '.', $number); // must not: enter values like 1,000 and mean 1000!
	return $number;
}

function check_if_class ($field, $values) {
	$class = false;
	if ($field['type'] == 'id') $class[] = 'recordid';
	elseif ($field['type'] == 'number' OR $field['type'] == 'calculated') $class[] = 'number';
	if (!empty($_GET['order'])) 
		if (!empty($field['field_name']) && $field['field_name'] == $_GET['order']) $class[] = 'order';
		elseif (!empty($field['display_field']) && $field['display_field'] == $_GET['order']) $class[] = 'order';
	if ($values)
		if (isset($field['field_name'])) // does not apply for subtables!
			if (field_in_where($field['field_name'], $values)) 
				$class[] = 'where';
	if ($class) return (' class="'.implode(' ',$class).'"');
	else return false;
}

function addvar($uri, $field, $value) {
	$uri_p = parse_url($uri);
	if (isset($uri_p['query'])) {
		parse_str($uri_p['query'], $queries);
		unset($queries['dir']); // ORDER direction will be removed - attention if function will be used for other purposes
	}
	$queries[$field] = $value;
	$new_uri = $uri_p['path'].'?'; 
	// other uri parts are ignored, may be changed if necessary
	// e. g. fragment.
	foreach (array_keys($queries) as $query_key) {
		if ($new_uri != $uri_p['path'].'?') $new_uri.= '&amp;';
		if (is_array($queries[$query_key]))
			foreach (array_keys($queries[$query_key]) as $qq_key)
				$new_uri.= $query_key.'['.$qq_key.']='.$queries[$query_key][$qq_key];
		else $new_uri.= $query_key.'='.$queries[$query_key];
	}
	return $new_uri;
}

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

function get_to_array($get) {
	$extras = false;
	foreach (array_keys($get) as $where_key)
		$extras.= '&amp;where['.$where_key.']='.$get[$where_key];
	return $extras;
}

function read_fields($array, $mode, $values, $table) {
	foreach (array_keys($array) as $val_key) {
		$values[$val_key] = $array[$val_key];
		if ($mode == 'replace') {
			if (substr($val_key, 0, strlen($table)) == $table) {
				// maintable. aus string entfernen!
				$val_key_new = substr($val_key, strlen($table) +1, (strlen($val_key) - strlen($table)));
				$values[$val_key_new] = $array[$val_key];
				unset ($values[$val_key]);
			}
			if (strpos($val_key, '.')) {
				// macht obere Funktion eigentl. ueberfluessig, oder koennen Feldnamen Punkte enthalten?
				$val_key_new = strstr($val_key, '.');
				$val_key_new = substr($val_key_new, 1, strlen($val_key_new) -1);
				$values[$val_key_new] = $array[$val_key];
				unset ($values[$val_key]);
			}
		}
	}
	return $values;
}

function show_image($path, $record) {
	global $text;
	$img = false;
	if ($record) {
		$img = '<img src="';
		$alt = $text['no_image'];
		$img_src = '';
		foreach (array_keys($path) as $part) {
			if (substr($part,0,4) == 'root')
				$root = $path[$part];
			elseif (substr($part,0,4) == 'mode') {
				$mode[] = $path[$part];
			} elseif (substr($part,0,5) == 'field') {
				if (isset($record[$path[$part]])) {
					if (!isset($mode))
						$img_src.= $record[$path[$part]];
					else {
						$content = $record[$path[$part]];
						foreach ($mode as $mymode)
							$content = $mymode($content);
						$img_src.= $content;
					}
					$alt = $text['File: '].$record[$path[$part]];
				} else return false;
			} else $img_src.= $path[$part];
		}
		if (!isset($root))
			$img.= $img_src;
		else			// check whether image exists
			if (file_exists($root.$img_src) && getimagesize($root.$img_src)) $img.= $img_src; // show only images
			else return false;
		$img.= '" alt="'.$alt.'" class="thumb">';
	}
	return $img;
}

function show_link($path, $record) {
	$link = false;
	if ($record)
		foreach (array_keys($path) as $part)
			if (substr($part,0,5) == 'field') $link.= $record[$path[$part]];
			else $link.= $path[$part];
	return $link;
}

function show_more_actions($more_actions, $more_actions_url, $more_actions_base, $id, $line = '') {
	$act = 0;
	$output = '';
	foreach ($more_actions as $key => $new_action) {
		if ($more_actions_base) $new_action_url = $more_actions_base[$key];
		else $new_action_url = strtolower(forceFilename($new_action));
		if ($act) $output.= '&nbsp;| ';
		$act++;
		$output.= '<a href="'.$new_action_url;
		if (isset($more_actions_url))
			if (is_array($more_actions_url))
				foreach (array_keys($more_actions_url) as $part_key)
					if (substr($part_key, 0, 5) == 'field')
						$output.= $line[$more_actions_url[$part_key]];
					else
						$output.= $more_actions_url[$part_key];
			else
				$output.= $more_actions_url;
		else $output.= '.php?id=';
		if (!isset($more_actions_url) OR !is_array($more_actions_url)) $output.= $id;
		$output.= '&amp;referer='.urlencode($_SERVER['REQUEST_URI']);
		$output.= '">'.$new_action.'</a>';
	}
	return $output;
}

function draw_select($line, $record, $field, $hierarchy, $level, $parent_field_name, $form, $zz_conf) {
	if (!isset($field['sql_ignore'])) $field['sql_ignore'] = array();
	$output = '';
	$i = 1;
	$details = '';
	if ($form == 'reselect')
		$output = '<input type="text" size="32" name="'.$field['f_field_name'].'" value="';
	elseif ($form) {
		$output = '<option value="'.$line[0].'"';
		if ($record) if ($line[0] == $record[$field['field_name']]) $output.= ' selected';
		if ($hierarchy) $output.= ' class="level'.$level.'"';
		$output.= '>';
		$output.= str_repeat('&nbsp;', 6*$level); 
	}
	if (!isset($field['show_hierarchy'])) $field['show_hierarchy'] = false;
	if (!isset($field['sql_index_only']) || !$field['sql_index_only'])
		foreach (array_keys($line) as $key) {	// $i = 1: field['type'] == 'id'!
			if ($key != $parent_field_name && !is_numeric($key) && $key != $field['show_hierarchy'] && !in_array($key, $field['sql_ignore'])) {
				if ($details) $details.= ' | ';
				if ($i > 1) $details.= (strlen($line[$key]) > $zz_conf['max_select_val_len']) ? (substr($line[$key], 0, $zz_conf['max_select_val_len']).'...') : $line[$key]; // cut long values
				$i++;
			}
		}
	else
		$key = 0;
	if (!$details) $details = $line[$key]; // if only the id key is in the query, eg. show databases
	$output.= $details;
	$level++;
	if ($form == 'reselect') 
		$output.= '">';
	elseif ($form) {
		$output.= '</option>';
		if ($hierarchy && isset($hierarchy[$line[0]]))
			foreach ($hierarchy[$line[0]] as $secondline)
				$output.= draw_select($secondline, $record, $field, $hierarchy, $level, $parent_field_name, 'form', $zz_conf);
	}
	return $output;
}

function htmlchars($string) {
	$string = str_replace('&amp;', '&', htmlspecialchars($string));
	//$string = str_replace('&quot;', '"', $string); // does not work 
	return $string;
}

function zz_search_sql($query, $sql, $table) {
	$unsearchable = array('image', 'calculated', 'subtable', 'timestamp', 'upload_image', 'option'); // fields that won't be used for search
	if (isset($_GET['q'])) {
		if (isset($_GET['search']))
			switch ($_GET['search']) {
				case 'gt':
					$searchstring = ' > "'.$_GET['q'].'"';
					break;
				case 'lt';
					$searchstring = ' < "'.$_GET['q'].'"';
					break;
			}
		else
			$searchstring = ' LIKE "%'.$_GET['q'].'%"';
	// Search with q
		if (isset($_GET['scope']) && $_GET['scope']) {
			$scope = false;
			foreach ($query as $field)
			// todo: check whether scope is in_array($searchfields)
				if (!in_array($field['type'], $unsearchable) && empty($field['exclude_from_search']))
					if (!isset($field['sql']) && $_GET['scope'] == $field['field_name'] 
						OR $_GET['scope'] == $table.'.'.$field['field_name']
						OR (isset($field['display_field']) && $_GET['scope'] == $field['display_field'])) {
						$scope = $_GET['scope'];
						if (isset($field['display_field']) && $_GET['scope'] == $field['display_field']) $scope = $_GET['scope'];
						if (isset($field['search'])) $scope = $field['search'];
					}
			if (strstr($sql, 'WHERE')) $sql.= ' AND';
			else $sql.= ' WHERE';
			if ($scope)
				// search results
				$sql.= ' '.$scope.$searchstring;
			else
				$sql.= ' NULL';
		} else {
			$q_search = '';
			foreach ($query as $field)
				if (!in_array($field['type'], $unsearchable) && empty($field['exclude_from_search'])) {
					if (!$q_search)
						if (strstr($sql, 'WHERE')) $q_search = ' AND (';
						else $q_search = ' WHERE (';
					else $q_search .= ' OR ';
					if (isset($field['search'])) $fieldname = $field['search'];
					elseif (isset($field['display_field'])) $fieldname = $field['display_field'];
					else $fieldname = $table.'.'.$field['field_name'];
					$q_search .= $fieldname.$searchstring;
				}
			$q_search.= ')';
			$sql.= $q_search;
		}
	}
	return $sql;
}

function zz_search_form($self, $query, $table) {
	global $text;
	$unsearchable = array('image', 'calculated', 'subtable', 'timestamp', 'upload_image'); // fields that won't be used for search
	$output = "\n";
	$output.= '<form method="GET" action="'.$self.'"><p>';
	$uri = parse_url($_SERVER['REQUEST_URI']);
	if (isset($uri['query'])) { // better than $_GET because of possible applied rewrite rules!
		$unwanted_keys = array('q', 'scope', 'limit', 'this_limit', 'mode', 'id'); // do not show edited record, limit
		parse_str($uri['query'], $queryparts);
		foreach (array_keys($queryparts) as $key)
			if (in_array($key, $unwanted_keys))
				unset($queryparts[$key]);
		foreach (array_keys($queryparts) as $key)
			if (is_array($queryparts[$key]))
				foreach (array_keys($queryparts[$key]) as $subkey)
					$output .= '<input type="hidden" name="'.$key.'['.$subkey.']" value="'.$queryparts[$key][$subkey].'">';
			else
				$output.= '<input type="hidden" name="'.$key.'" value="'.$queryparts[$key].'">';
	}
	$output.= '<input type="text" size="30" name="q"';
	if (isset($_GET['q'])) $output.= ' value="'.htmlchars($_GET['q']).'"';
	$output.= '>';
	$output.= '<input type="submit" value="'.$text['search'].'">';
	$output.= ' '.$text['in'].' ';	
	$output.= '<select name="scope">';
	$output.= '<option value="">'.$text['all fields'].'</option>';
	foreach ($query as $field) {
		if (!in_array($field['type'], $unsearchable) && empty($field['exclude_from_search'])) {
			$fieldname = (isset($field['display_field']) && $field['display_field']) ? $field['display_field'] : $table.'.'.$field['field_name'];
			$output.= '<option value="'.$fieldname.'"';
			if (isset($_GET['scope'])) if ($_GET['scope'] == $fieldname) $output.= ' selected';
			$output.= '>'.$field['title'].'</option>';
		}
	}
	$output.= '</select>';
	$output.= '</p></form>'."\n";
	return $output;
}

function zz_limit($limit, $this_limit, $count_rows, $sql, $zz_lines, $scope) {
	global $text;
	/*
	
	if LIMIT is set, shows different pages for each 20 records
	
	todo:
	- <link rel="next">, <link rel="previous">
	- maybe put class around ul
	- direct jump to page, e. g. 1 | 2 | 3 | 4 | 5
	
	*/
	$output = '';
	$step = $limit;
	if ($this_limit && $count_rows >= ($step) OR $this_limit > $step) {
		$next = false;
		$prev = false;
		$result = mysql_query(preg_replace('/LIMIT \d+, \d+/i', '', $sql));
		if ($result) $total_rows = mysql_num_rows($result);
		if ($total_rows) {
			$uri = $_SERVER['REQUEST_URI'];
			// remove mode, id
			$my_uri = parse_url($uri);
			if (isset($my_uri['query'])) {
				$uri = $my_uri['path'];		// basis: path
				parse_str($my_uri['query'], $queryparts);
				foreach (array_keys($queryparts) as $key) // remove id, mode, limit from URI
					if ($key == 'mode' OR $key == 'id' OR $key == 'limit')  
						unset ($queryparts[$key]);
				$parts = '';
				foreach ($queryparts as $key => $value) { // glue remaining query parts
					if (!$parts) $parts = '?';
					else $parts .= '&amp;';
					if (is_array($value)) // array has to be treated seperately
						foreach ($value as $mykey => $myvalue)
							$parts.= $key.'['.$mykey.']='.$myvalue;
					else $parts.= $key.'='.$value;
				}
				$uri .= $parts; // URL without limit, mode, id parameter
			}
			$output .= '<ul class="pages">';
			$output .= '<li class="first">'.($zz_limitlink = limitlink(0, $this_limit, $step, $uri)).'|&lt;'.($zz_limitend = ($zz_limitlink) ? '</a>' : '').'</li>';
			$output .= '<li class="prev">'.($zz_limitlink = limitlink($this_limit-$step, $this_limit, 0, $uri)).'&lt;'.($zz_limitend = ($zz_limitlink) ? '</a>' : '').'</li>';
			$output .= '<li class="all">'.($zz_limitlink = limitlink(-1, $this_limit, 0, $uri)).$text['all'].($zz_limitend = ($zz_limitlink) ? '</a>' : '').'</li>';
			for ($i = 0; $i <= $total_rows -1; $i = $i+$limit) { // total_rows -1 because min is + 1 later on
				$range_min = $i+1;
				$range_max = $i+$limit;
				if ($range_max > $total_rows) $range_max = $total_rows;
				$output .= '<li>'.($zz_limitlink = limitlink($i, $this_limit, $step, $uri))
					.($range_min == $range_max ? $range_min: $range_min.'-'.$range_max) // if just one above the last limit show this numver only once
					.($zz_limitend = ($zz_limitlink) ? '</a>' : '').'</li>';
			}
			$limit_next = $limit+$step;
			if ($limit_next > $range_max) $limit_next = $i;
			$output .= '<li class="next">'.($zz_limitlink = limitlink($limit_next, $this_limit, 0, $uri)).'&gt;'.($zz_limitend = ($zz_limitlink) ? '</a>' : '').'</li>';
			$output .= '<li class="last">'.($zz_limitlink = limitlink($i, $this_limit, 0, $uri)).'&gt;|'.($zz_limitend = ($zz_limitlink) ? '</a>' : '').'</li>';
			$output .= '</ul>';
			$output .= '<br clear="all">';
		}
	}
	return $output;
}

function limitlink($i, $limit, $step, $uri) {
	if ($i == -1) {  // all records
		if (!$limit) return false;
		else $limit_new = 0;
	} else {
		$limit_new = $i + $step;
		if ($limit_new == $limit) return false; // current page!
		elseif (!$limit_new) return false; // 0 does not exist, means all records
	}
	$uriparts = parse_url($uri);
	if (isset($uriparts['query'])) $uri.= '&amp;';
	else $uri.= '?';
	$uri .= 'limit='.$limit_new;
	return '<a href="'.$uri.'">';
}

function zz_get_subqueries($subqueries, $zz, &$zz_tab, $zz_conf) {
	$i = 0;
	if ($subqueries) // && $zz['action'] != 'delete'
		foreach ($subqueries as $subquery) {
			$i++;
			$zz_tab[$i]['table'] = $zz['fields'][$subquery]['table'];
			$zz_tab[$i]['table_name'] = $zz['fields'][$subquery]['table_name'];
			$zz_tab[$i]['max_records'] = (isset($zz['fields'][$subquery]['max_records'])) ? $zz['fields'][$subquery]['max_records'] : $zz_conf['max_detail_records'];
			$zz_tab[$i]['min_records'] = (isset($zz['fields'][$subquery]['min_records'])) ? $zz['fields'][$subquery]['min_records'] : $zz_conf['min_detail_records'];
			$zz_tab[$i]['no'] = $subquery;
			$zz_tab[$i]['sql'] = $zz['fields'][$subquery]['sql'];
			if ($zz['mode']) {
				if ($zz['mode'] == 'add')
					$zz_tab[$i] = zz_subqueries($i, true, true, false, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // min, details
				elseif ($zz['mode'] == 'edit')
					$zz_tab[$i] = zz_subqueries($i, true, true, true, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // min, details, sql
				elseif ($zz['mode'] == 'delete')
					$zz_tab[$i] = zz_subqueries($i, false, false, true, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // sql
				elseif ($zz['mode'] == 'review' OR $zz['mode'] == 'show')
					$zz_tab[$i] = zz_subqueries($i, false, false, true, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // sql
			} elseif ($zz['action'] && !empty($_POST[$zz['fields'][$subquery]['table_name']])  && is_array($_POST[$zz['fields'][$subquery]['table_name']])) {
				foreach (array_keys($_POST[$zz['fields'][$subquery]['table_name']]) as $subkey) {
					$zz_tab[$i][$subkey]['fields'] = $zz['fields'][$zz_tab[$i]['no']]['fields'];
					$zz_tab[$i][$subkey]['validation'] = true;
					$zz_tab[$i][$subkey]['record'] = false;
					$zz_tab[$i][$subkey]['action'] = false;
					foreach ($zz_tab[$i][$subkey]['fields'] as $field)
						if (isset($field['type']) && $field['type'] == 'id') 
							$zz_tab[$i][$subkey]['id']['field_name'] = $field['field_name'];
					$table = $zz['fields'][$subquery]['table_name'];
					$field_name = $zz_tab[$i][$subkey]['id']['field_name'];
					$zz_tab[$i][$subkey]['id']['value'] = 
						(isset($_POST[$table][$subkey][$field_name])) ? $_POST[$table][$subkey][$field_name]: '';
				}
			}
		}
}


function zz_subqueries($i, $min, $details, $sql, $subtable, $zz_tab) {
	global $zz_error;
	$records = false;
	$my = $zz_tab[$i];
	if (isset($_POST[$subtable['table_name']]))
		$myPOST = $_POST[$subtable['table_name']];
	else
		$myPOST = false;
	$deleted_ids = (!empty($my['deleted']) ? $my['deleted'] : array());
	foreach ($subtable['fields'] as $field)
		if (isset($field['type']) && $field['type'] == 'id') $id_field_name = $field['field_name'];
	if (isset($_POST['deleted'][$subtable['table_name']]))
	//	fill existing deleted ids in $deleted_ids
		foreach ($_POST['deleted'][$subtable['table_name']] as $deleted)
			$deleted_ids[] = $deleted[$id_field_name];
	if ($min) $records = 1;
	if ($details)
		if (isset($_POST['records'][$i]) && $_POST['records'][$i]) {
			$records = $_POST['records'][$i];
			if (!$records) $records = 1;
			// possibly check values if correcht
		}
		if (isset($_POST['subtables']['add'][$i]) && $_POST['subtables']['add'][$i] == '+')
			$records++;
		if (isset($_POST['subtables']['remove'][$i])) {
			foreach (array_keys($myPOST) as $k) {
				if (isset($_POST['subtables']['remove'][$i][$k]) && $_POST['subtables']['remove'][$i][$k] == '-') {
					if (isset($myPOST[$k][$id_field_name])) // has ID
						$deleted_ids[] = $myPOST[$k][$id_field_name];
					unset($myPOST[$k]);
					$records--;
				}
			}
//			$records--;
		}
	if ($sql) {
		$c_sql = $zz_tab[$i]['sql'];
		$where = ' WHERE ';
		if (strstr($c_sql, 'WHERE')) $where = ' AND ';
		$c_sql.= $where.' '.$zz_tab[0]['table'].'.'.$zz_tab[0][0]['id']['field_name'].' = "'.$zz_tab[0][0]['id']['value'].'"';
		$result = mysql_query($c_sql);
		if ($result)
			if (mysql_num_rows($result))
				while ($line = mysql_fetch_array($result)) 
					if (!in_array($line[$id_field_name], $deleted_ids)) 
						$ids[] = $line[$id_field_name];
		if (mysql_error()) {
			$zz_error['msg'] = mysql_error();
			$zz_error['query'] = $c_sql;
		}
		if (isset($ids)) 
			if (count($ids) > $records)
				$records = count($ids);
	}
	if ($my['max_records'])
		if ($records > $my['max_records']) $records = $my['max_records'];
	for ($k = 0; $k<= $records-1; $k++) {
		if (isset($my[$k])) continue; // do not change values if they are already there (important for error messages etc.)
		$my[$k]['fields'] = $subtable['fields'];
		$my[$k]['record'] = false;
		$my[$k]['validation'] = true;
		$my[$k]['action'] = false;
		if (isset($ids[$k])) $idval = $ids[$k];
		else $idval = false;
		$my[$k]['id']['value'] = $idval;
		$my[$k]['id']['field_name'] = $id_field_name;
		$my[$k]['POST'] = '';
		if ($_POST)
			if ($idval) {
				foreach (array_keys($myPOST) as $key)
					if (isset($myPOST[$key][$id_field_name]))
						if ($myPOST[$key][$id_field_name] == $idval) {
							$my[$k]['POST'] = $myPOST[$key];
							unset($myPOST[$key]);
						}
			} else
				foreach (array_keys($myPOST) as $key)
					if (!isset($myPOST[$key][$id_field_name]) OR !$myPOST[$key][$id_field_name])
						// find first value pair that matches and put it into POST
						if (!($my[$k]['POST'])) {
							$my[$k]['POST'] = $myPOST[$key];
							unset($myPOST[$key]);
						} 
	}
	$my['records'] = $records;
	$my['deleted'] = $deleted_ids;
	return $my;
}

function zz_requery_record($my, $validation, $sql, $table, $mode) {
	global $text;
	global $zz_error;
	/*
		if everything was successful, requery record (except in case it was deleted)
		if not, change formhead and write POST values back into form
		
		changed fields:
		- $zz['record'] (initalized)
		- $zz['action']
		- $zz['formhead']
		- $zz['fields']
	*/
	if ($my['action'] != 'delete') {
		if ($validation) {
			if ($mode != 'add' OR $my['action']) {
				if ($my['id']['value']) {
					$where = ' WHERE ';
					if (strstr($sql, 'WHERE')) $where = ' AND ';
					$sql_edit = $sql.$where.$table.'.'.$my['fields'][1]['field_name']." = '";
					$sql_edit .= $my['id']['value']."'";
					$result_edit = mysql_query($sql_edit);
					if ($result_edit) {
						if (mysql_num_rows($result_edit) == 1)
							$my['record'] = mysql_fetch_array($result_edit, MYSQL_ASSOC);
						// else $zz_error['msg'].= 'Error in Database. Possibly the SQL
						// statement is incorrect: '.$sql_edit;
					} else {
						$zz_error['msg'] = $text['error-sql-incorrect'];
						$zz_error['mysql'] .= mysql_error();
						$zz_error['query'] .= $sql_edit;
					}
				} else
					$my['record'] = false;
			}
		} else {
			if (isset($my['POST-notvalid']))
				$my['record'] = $my['POST-notvalid'];
			else
				$my['record'] = $my['POST'];
			$my['formhead'] = 'Review record';
			$my['action'] = 'review';	// display form again
		//	print out all records which were wrong, set class to error
			$validate_errors = false;
			foreach (array_keys($my['fields']) as $qf) {
				if (isset($my['fields'][$qf]['check_validation'])) {
					if (!$my['fields'][$qf]['check_validation']) {
						if (isset($my['fields'][$qf]['class']))
							$my['fields'][$qf]['class'].= ' error';
						else $my['fields'][$qf]['class'] = 'error';
						if ($my['fields'][$qf]['type'] != 'password_change') {
							if (!$validate_errors) 
								$validate_errors = '<p>'.$text['Following_errors_occured'].':</p><ul>';
							$validate_errors.= '<li>'.$text['Value_incorrect_in_field'].' <strong>'.$my['fields'][$qf]['title'].'</strong></li>';
						}
					} else
						echo $my['fields'][$qf]['check_validation'];
				}
			}
			if ($validate_errors) $zz_error['msg'].= $validate_errors.'</ul>';
		}
	} else {
		if (!$validation) {
		//	check for referential integrity was not passed
			$my['formhead'] = 'Deletion not possible';
		}
	}
	return $my;
}

function fill_out(&$tab) {
	global $text;
	global $zz_conf;
	foreach (array_keys($tab['fields']) as $no) {
		if (!isset($tab['fields'][$no]['type'])) // default type: text
			$tab['fields'][$no]['type'] = 'text';
		if (!isset($tab['fields'][$no]['title'])) { // create title
			$tab['fields'][$no]['title'] = ucfirst($tab['fields'][$no]['field_name']);
			$tab['fields'][$no]['title'] = str_replace('_ID', ' ', $tab['fields'][$no]['title']);
			$tab['fields'][$no]['title'] = str_replace('_id', ' ', $tab['fields'][$no]['title']);
			$tab['fields'][$no]['title'] = str_replace('_', ' ', $tab['fields'][$no]['title']);
		}
		if (($zz_conf['multilang_fieldnames'])) {// translate fieldnames, if set
			$tab['fields'][$no]['title'] = $text[$tab['fields'][$no]['title']];
			if (!empty($tab['fields'][$no]['title_append'])) 
				$tab['fields'][$no]['title_append'] = $text[$tab['fields'][$no]['title_append']];
		}
		if ($tab['fields'][$no]['type'] == 'option') { 
			$tab['fields'][$no]['hide_in_list'] = true; // do not show option-fiels in tab
			$tab['fields'][$no]['class'] = 'option'; // format option-fields with css
		}
		if (!isset($tab['fields'][$no]['explanation'])) $tab['fields'][$no]['explanation'] = false; // initialize
		if (!isset($tab['fields'][$no]['maxlength']) && isset($tab['fields'][$no]['field_name'])) 
			$tab['fields'][$no]['maxlength'] = check_maxlength($tab['fields'][$no]['field_name'], $tab['table']);
		if (!empty($tab['fields'][$no]['sql'])) // replace whitespace with space
			$tab['fields'][$no]['sql'] = preg_replace("/\s+/", " ", $tab['fields'][$no]['sql']);
		if ($tab['fields'][$no]['type'] == 'subtable') // for subtables, do this as well
			fill_out($tab['fields'][$no]);
	}
}

function zz_log_sql($sql, $user) {
	global $zz_conf;
	// logs each INSERT, UPDATE or DELETE query
	$sql = 'INSERT INTO '.$zz_conf['logging_table'].' 
		(query, user) VALUES ("'.addslashes($sql).'", "'.$user.'")';
	$result = mysql_query($sql);
	echo mysql_error();
	if (!$result) return false;
	else return true;
	// die if logging is selected but does not work?
}

function zz_sql_order($fields, $sql) {
	if (!empty($_GET['order'])) {
		$dir = (isset($_GET['dir'])) ? $_GET['dir'] : false;
		$my_order = $_GET['order'];
		foreach ($fields as $field)
			if ((isset($field['display_field']) && $field['display_field'] == $my_order) 
				OR (isset($field['field_name']) && $field['field_name'] == $my_order))
				if (isset($field['order'])) {
					$my_order = $field['order'];
					if ($dir)
					if ($dir == 'asc') $my_order = str_replace('DESC', 'ASC', $my_order);
					elseif ($dir == 'desc') $my_order = str_replace('ASC', 'DESC', $my_order);
					unset($dir);
				}
		if (isset($dir))
			if ($dir == 'asc') $my_order.= ' ASC';
			elseif ($dir == 'desc') $my_order.= ' DESC';
		if (strstr($sql, 'ORDER BY'))
			$sql = str_replace ('ORDER BY', ' ORDER BY '.$my_order.', ', $sql);
		else
			$sql.= ' ORDER BY '.$my_order;
	} 
	return $sql;
}

function zz_create_identifier($vars, $my, $table, $field, $conf) {
	if (in_array($my['fields'][$field]['field_name'], array_keys($vars)) && $vars[$my['fields'][$field]['field_name']]) 
		return $vars[$my['fields'][$field]['field_name']]; // do not change anything if there has been a value set once and identifier is in vars array
	$con_filename = !empty($conf['forceFilename']) ? substr($conf['forceFilename'],0,1) : '-';
	$con_vars = !empty($conf['concat']) ? substr($conf['concat'],0,1) : '.';
	$con_exists = !empty($conf['exists']) ? substr($conf['exists'],0,1) : '.';
	foreach ($vars as $var)
		if ($var) {
			if (strstr($var, '/')) {
				$dir_vars = explode('/', $var);
				foreach ($dir_vars as $d_var)
					if ($d_var) $idf_arr[] = strtolower(forceFilename($d_var, $con_filename));
			} else
				$idf_arr[] = strtolower(forceFilename($var, $con_filename));
		}
	if (empty($idf_arr)) return false;
	$idf = implode($con_vars, $idf_arr);
	if (!empty($conf['prefix'])) $idf = $conf['prefix'].$idf;
	$i = 2; // start value, if idf already exists
	if (!empty($my['fields'][$field]['maxlength']) && ($my['fields'][$field]['maxlength'] < strlen($idf)))
		$idf = substr($idf, 0, $my['fields'][$field]['maxlength']);
	$idf = zz_exists_identifier($idf, $i, $table, $my['fields'][$field]['field_name'], $my['fields'][1]['field_name'], $my['POST'][$my['fields'][1]['field_name']], $con_exists, $my['fields'][$field]['maxlength']);
	return $idf;
}

function zz_exists_identifier($idf, $i, $table, $field, $id_field, $id_value, $con_exists = '.', $maxlength = false) {
	$sql = 'SELECT * FROM '.$table.' WHERE '.$field.' = "'.$idf.'"';
	$sql.= ' AND '.$id_field.' != '.$id_value;
	$result = mysql_query($sql);
	if ($result) if (mysql_num_rows($result)) {
		if ($i > 2)	$idf = substr($idf, 0, strrpos($idf, $con_exists));
		$suffix = $con_exists.$i;
		if ($maxlength && strlen($idf.$suffix) > $maxlength) $idf = substr($idf, 0, ($maxlength-strlen($suffix))); 
			// in case there is a value for maxlength, make sure that resulting string won't be longer
		$idf = $idf.$suffix;
		$i++;
		$idf = zz_exists_identifier($idf, $i, $table, $field, $id_field, $id_value, $con_exists, $maxlength);
	}
	return $idf;
}

// make_id_fieldname
// converts fieldnames with [ and ] into allowed id values
// prepends field_ or other prefix, if wanted

function make_id_fieldname($fieldname, $prefix = 'field') {
	$fieldname = str_replace('][', '_', $fieldname);
	$fieldname = str_replace('[', '_', $fieldname);
	$fieldname = str_replace(']', '', $fieldname);
	if ($prefix) $fieldname = $prefix.'_'.$fieldname;
	return $fieldname;
}

function magic_quotes_strip($mixed) {
   if(is_array($mixed))
       return array_map('magic_quotes_strip', $mixed);
   return stripslashes($mixed);
}

function zz_edit_sql($sql, $part = false, $values = false) {
	// puts parts of SQL query in correct order when they have to be added
	$sql = preg_replace("/\s+/", " ", $sql);
	if (preg_match('/ ORDER BY (.*)/i', $sql, $order_by))
		$sql = (preg_replace('/ ORDER BY (.*)/i', '', $sql));
	if (preg_match('/ GROUP BY (.*)/i', $sql, $group_by))
		$sql = (preg_replace('/ GROUP BY (.*)/i', '', $sql));
	if (preg_match('/ WHERE (.*)/i', $sql, $where))
		$sql = (preg_replace('/ WHERE (.*)/i', '', $sql));
	if ($part && $values) {
		$part = strtoupper($part);
		switch ($part) {
			case 'WHERE':
				if (!empty($where[1])) $where[1] = '('.$where[1].') AND ('.$values.')';
				else $where[1] = $values;
			break;
			case 'ORDER BY':
				// ... later
			break;
			case 'GROUP BY':
				// ... later
			break;
		}
	}
	if (!empty($where[1])) $sql.= ' WHERE '.$where[1];
	if (!empty($group_by[1])) $sql.= ' GROUP BY '.$group_by[1];
	if (!empty($order_by[1])) $sql.= ' ORDER BY '.$order_by[1];
	return $sql;
}

?>