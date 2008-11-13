<?php 

/*
	zzform Scripts

	miscellaneous functions
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2007

*/

function zz_error ($zz_error) {
	global $zz_error;
	global $zz_conf;
	global $text;
	$output = array();
	$mailtext = array();
	
	// browse through all errors
	foreach ($zz_error as $key => $error) {
		if (empty($error['msg'])) continue;
		$output[$key] = '';
		$sql_output[$key] = '';
		if (!empty($error['level']) AND $error['level'] == 'warning')
			$output[$key] .= '<strong>'.$text['Warning'].'!</strong> ';
		if ($errmsg = trim($error['msg'])) {
			if (!empty($text[$errmsg])) $errmsg = $text[$errmsg];
			$output[$key] .= $errmsg;
		} 
		if (!empty($error['mysql_errno'])) {
			switch($error['mysql_errno']) {
			case 1062:
				$output[$key] .= ' '.$text['Duplicate entry'];
					/*
					TODO:
					1. get table_name
					2. parse: Duplicate entry '1-21' for key 2: (e.g. with preg_match)
					$indices = false;
					$sql = 'SHOW INDEX FROM ...';
					$result = mysql_query($sql);
					if ($result) if (mysql_num_rows($result))
						while ($line = mysql_fetch_assoc($result))
							$keys[] = $line;
					if ($keys) {
						// 3. get required key, field_names
					}
					// 4. get title-values from zz['field'], display them
					// 5. show wrong values, if type select: show values after select ...
					*/
				break;
			default:
				$output[$key] .= ' '.$text['database-error'];
			}
		}
		if (!empty($error['mysql'])) $sql_output[$key] .= $error['mysql'].':<br>';
		if (!empty($error['query'])) $sql_output[$key] .= $error['query'];
		if ($sql_output[$key]) {
			if ($zz_conf['error_handling'] == 'mail' && $zz_conf['error_mail_to']) {
				$mailtext[$key] = strip_tags(str_replace('<br>', "\n\n", 
					trim(html_entity_decode($output[$key]))."\n\n".trim($sql_output[$key])));
			} else {
				if ($output[$key]) $output[$key] .= '<br>';
				$output[$key] .= $sql_output[$key];
			}
		}
	}
	
	// mail sql errors if said to do so
	if (count($mailtext)) {
		$mailtext = implode("\n\n", $mailtext);
		$mailtext = sprintf($text['The following error(s) occured in project %s:'], $zz_conf['project'])."\n\n".$mailtext;
		$mailtext .= "\n\n-- \nURL: http://".$_SERVER['SERVER_NAME']
			.$_SERVER['REQUEST_URI']
			."\nIP: ".$_SERVER['REMOTE_ADDR']
			."\nBrowser: ".$_SERVER['HTTP_USER_AGENT'];		
		if ($zz_conf['user'])
			$mailtext .= "\nUser: ".$zz_conf['user'];
		mail ($zz_conf['error_mail_to'], '['.$zz_conf['project'].'] '
			.$text['Error during database operation'], 
			$mailtext, 'MIME-Version: 1.0
Content-Type: text/plain; charset='.$zz_conf['charset'].'
Content-Transfer-Encoding: 8bit
From: '.$zz_conf['error_mail_from']);
	// TODO: check what happens with utf8 mails
	}

	// show errors
	$zz_error = array();
	if (!count($output)) return false;
	$output = '<div class="error">'.implode('<br><br>', $output).'</div>';
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
	global $zz_conf;
	$sql = 'SHOW COLUMNS FROM '.$maintable.' LIKE "'.$field.'"';
	if ($zz_conf['debug_allsql']) echo "<div>check_maxlength_query:<br /><pre>$sql</pre></div>";
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
	if (!preg_match('/^-*[0-9.,]*$/', $number)) return false; // possible feature: return doubleval $number to get at least something
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
	if (isset($field['level'])) $class[] = 'level'.$field['level'];
	if ($field['type'] == 'id' && empty($field['show_id'])) $class[] = 'recordid';
	elseif ($field['type'] == 'number' OR $field['type'] == 'calculated') $class[] = 'number';
	if (!empty($_GET['order'])) 
		if (!empty($field['field_name']) && $field['field_name'] == $_GET['order']) $class[] = 'order';
		elseif (!empty($field['display_field']) && $field['display_field'] == $_GET['order']) $class[] = 'order';
	if ($values)
		if (isset($field['field_name'])) // does not apply for subtables!
			if (field_in_where($field['field_name'], $values)) 
				$class[] = 'where';
	if (!empty($field['class'])) $class[] = $field['class'];
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
	$fragments = false;
	foreach (array_keys($queries) as $query_key) {
		if (is_array($queries[$query_key]))
			foreach (array_keys($queries[$query_key]) as $qq_key)
				$fragments[] = $query_key.'['.$qq_key.']='.$queries[$query_key][$qq_key];
		else $fragments[] = $query_key.'='.$queries[$query_key];
	}
	$new_uri.= implode('&amp;', $fragments);
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

function show_image($path, $record) {
	global $text;
	$img = false;
	if ($record) {
		$img = '<img src="';
		$alt = $text['no_image'];
		$img_src = false;
		$root = false;
		$webroot = false;
		foreach (array_keys($path) as $part) {
			if (substr($part, 0, 4) == 'root')
				$root = $path[$part];
			elseif (substr($part, 0, 7) == 'webroot')
				$webroot = $path[$part];
			elseif (substr($part, 0, 4) == 'mode') {
				$mode[] = $path[$part];
			} elseif (substr($part, 0, 5) == 'field') {
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
			} else
				$img_src.= $path[$part];
		}
		$img.= $webroot.$img_src;
		if (!empty($root)) // check whether image exists
			if (!file_exists($root.'/'.$img_src) 	// file does not exist = false
				OR !filesize($root.'/'.$img_src) 	// filesize is 0 = looks like error
				OR !getimagesize($root.'/'.$img_src)) // getimagesize test whether it's an image
				return false;
		$img.= '" alt="'.$alt.'" class="thumb">';
	}
	return $img;
}

function show_link($path, $record) {
	$link = false;
	$modes = false;
	if (!$record) return false;
	if (!$path) return false;
	
	$root = false;
	foreach (array_keys($path) as $part)
		if (substr($part, 0, 4) == 'root')
			$root = $path[$part];
		elseif (substr($part, 0, 5) == 'field') {
			if ($modes) {
				$myval = $record[$path[$part]];
				foreach ($modes as $mode)
					if (function_exists($mode))
						$myval = $mode($myval);
					else {
						echo 'Configuration Error: mode with not-existing function';
						exit;
					}
				$link.= $myval;
				$modes = false;
			} else
				$link.= $record[$path[$part]];
		} elseif (substr($part, 0, 4) == 'mode') {
			$modes[] = $path[$part];
		} else $link.= $path[$part];
	if ($root && !file_exists($root.$link))
		return false;
	return $link;
}

function zz_show_more_actions($more_actions, $more_actions_url, $more_actions_base, 
		$more_actions_target, $more_actions_referer, $id, $line = false) {
	if (!function_exists('forceFilename')) {
		echo 'Function forceFilename() required but not found! It is as well possible that <code>$zz_conf[\'character_set\']</code> is incorrectly set.';
		exit;
	}
	global $text;
	global $zz_conf;
	$act = false;
	foreach ($more_actions as $key => $new_action) {
		$output = false;
		if ($more_actions_base) $new_action_url = $more_actions_base[$key];
		else $new_action_url = strtolower(forceFilename($new_action));
		$output.= '<a href="'.$new_action_url;
		if (!empty($more_actions_url))
			if (isset($more_actions_url[$key]) && is_array($more_actions_url[$key])) { // values are different for each key
				foreach (array_keys($more_actions_url[$key]) as $part_key)
					if (substr($part_key, 0, 5) == 'field')
						$output.= $line[$more_actions_url[$key][$part_key]];
					else
						$output.= $more_actions_url[$key][$part_key];
			} elseif (is_array($more_actions_url)) // all values are the same
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
			.'>'.($zz_conf['multilang_fieldnames'] ? $text[$new_action] : $new_action).'</a>';
		$act[] = $output;
	}
	$output = implode('&nbsp;| ', $act);
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
	$output.= strip_tags($details); // remove tags, leave &#-Code as is
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
	global $zz_conf;
	$addscope = true;
	$unsearchable = array('image', 'calculated', 'subtable', 'timestamp', 'upload_image', 'option'); // fields that won't be used for search
	if (!empty($_GET['q'])) {
		if (get_magic_quotes_gpc())
			stripslashes($_GET['q']);
		$searchword = addslashes($_GET['q']);
		// search: look at first character to change search method
		if (substr($searchword, 0, 1) == '>') {
			$searchword = trim(substr($searchword, 1));
			$searchop = '>';
			$searchstring = ' '.$searchop.' "'.$searchword.'"';
		} elseif (substr($searchword, 0, 1) == '<') {
			$searchword = trim(substr($searchword, 1));
			$searchop = '<';
			$searchstring = ' < "'.trim(substr($searchword, 1)).'"';
		} elseif (substr($searchword, 0, 1) == '-' AND strstr($searchword, ' ')) {
			$searchword = trim(substr($searchword, 1));
			$searchword = explode(" ", $searchword);
			$searchop = 'BETWEEN';
			$searchstring = $_GET['scope'].' >= "'.trim($searchword[0]).'" AND '.$_GET['scope'].' <= "'.trim($searchword[1]).'"';
			$addscope = false;
		} elseif (preg_match('/q\d(.)[0-9]{4}/i', $searchword, $separator) AND !empty($_GET['scope'])) {
			$searchword = trim(substr($searchword, 1));
			$searchword = explode($separator[1], $searchword);
			$searchop = false;
			$searchstring = ' QUARTER('.$_GET['scope'].') = "'.trim($searchword[0]).'" AND YEAR('.$_GET['scope'].') = "'.trim($searchword[1]).'"';
			$addscope = false;
		} else {
			$searchop = 'LIKE';
			// first slash will be ignored, this is used to escape reserved characters
			if (substr($searchword, 0, 1) == '\\') $searchword = substr($searchword, 1);
			$searchstring = ' '.$searchop.' "%'.$searchword.'%"';
		}
	// Search with q
		if (!empty($_GET['scope'])) {
			$scope = false;
			$fieldtype = false;
			foreach ($query as $field) {
			// todo: check whether scope is in_array($searchfields)
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
			if (empty($searchword[1]) AND 
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
		} else {
			$q_search = '';
			foreach ($query as $field)
				if (!in_array($field['type'], $unsearchable) && empty($field['exclude_from_search'])) {
					if (isset($field['search'])) $fieldname = $field['search'];
					elseif (isset($field['display_field'])) $fieldname = $field['display_field'];
					else $fieldname = $table.'.'.$field['field_name'];
					$q_search[] = $fieldname.$searchstring;
				}
			$q_search = '('.implode(' OR ', $q_search).')';
			$sql = zz_edit_sql($sql, 'WHERE', $q_search);
		}
	}
	if ($zz_conf['debug']) {
		global $zz;
		$zz['output'] .= 'Search query: '.$sql.'<br>';
	}
	return $sql;
}

function zz_search_form($self, $query, $table) {
	global $text;
	global $zz_conf;
	$unsearchable = array('image', 'calculated', 'subtable', 'timestamp', 'upload_image'); // fields that won't be used for search
	$output = "\n";
	$output.= '<form method="GET" action="'.$self.'" id="zzsearch" accept-charset="'.$zz_conf['character_set'].'"><p>';
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
			$output.= '>'.strip_tags($field['title']).'</option>';
		}
	}
	$output.= '</select>';
	if (!empty($_GET['q'])) {
		$parts = array();
		foreach (array_keys($queryparts) as $key) {
			if (is_array($queryparts[$key])) {
				foreach (array_keys($queryparts[$key]) as $subkey)
					$parts[] = $key.'['.$subkey.']'.'='.$queryparts[$key][$subkey];
			} else 
				$parts[] = $key.'='.$queryparts[$key];
		}
		if ($parts) $self .= '?'.implode('&amp;', $parts);
		$output.= ' &nbsp;<a href="'.$self.'">'.$text['Show all records'].'</a>';
	}
	$output.= '</p></form>'."\n";
	return $output;
}

function zz_limit($step, $this_limit, $count_rows, $sql, $zz_lines, $scope) {
	global $text;
	global $zz_conf;
	/*
	
	if LIMIT is set, shows different pages for each $step records
	todo:
	- <link rel="next">, <link rel="previous">
	
	*/
	$output = '';
	if (($this_limit && $count_rows >= $step OR $this_limit > $step) 
		AND ($step != $zz_lines)) {
		$next = false;
		$prev = false;
		if ($zz_lines) {
			$uri = $_SERVER['REQUEST_URI'];
			// remove mode, id
			$my_uri = parse_url($uri);
			if (isset($my_uri['query'])) {
				$uri = $my_uri['path'];		// basis: path
				parse_str($my_uri['query'], $queryparts);
				foreach (array_keys($queryparts) as $key) // remove id, mode, limit from URI
					if ($key == 'mode' OR $key == 'id' OR $key == 'limit')  
						unset ($queryparts[$key]);
				$parts = array();
				foreach ($queryparts as $key => $value) // glue remaining query parts
					if (is_array($value)) // array has to be treated seperately
						foreach ($value as $mykey => $myvalue)
							$parts[] = $key.'['.$mykey.']='.$myvalue;
					else $parts[] = $key.'='.$value;
				if ($parts) $uri .= '?'.implode('&amp;', $parts); // URL without limit, mode, id parameter
			}
			$output .= '<ul class="pages">';
			$output .= '<li class="first">'.($zz_limitlink = limitlink(0, $this_limit, $step, $uri)).'|&lt;'.($zz_limitend = ($zz_limitlink) ? '</a>' : '').'</li>';
			$output .= '<li class="prev">'.($zz_limitlink = limitlink($this_limit-$step, $this_limit, 0, $uri)).'&lt;'.($zz_limitend = ($zz_limitlink) ? '</a>' : '').'</li>';
			$output .= '<li class="all">'.($zz_limitlink = limitlink(-1, $this_limit, 0, $uri)).$text['all'].($zz_limitend = ($zz_limitlink) ? '</a>' : '').'</li>';
			$ellipsis_min = false;
			$ellipsis_max = false;
			if ($zz_conf['limit_show_range'] && $zz_lines >= $zz_conf['limit_show_range']) {
				$i_start = $this_limit - ($zz_conf['limit_show_range']/2 + 2*$step);
				if ($i_start < 0) $i_start = 0;
				$i_end = $this_limit + ($zz_conf['limit_show_range'] + $step);
				if ($i_end > $zz_lines -1) $i_end = $zz_lines -1;
			} else {
				$i_start = 0;
				$i_end = $zz_lines -1; // total_rows -1 because min is + 1 later on
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
				if ($range_max > $zz_lines) $range_max = $zz_lines;
				$output .= '<li>'.($zz_limitlink = limitlink($i, $this_limit, $step, $uri))
					.($range_min == $range_max ? $range_min: $range_min.'-'.$range_max) // if just one above the last limit show this numver only once
					.($zz_limitend = ($zz_limitlink) ? '</a>' : '').'</li>';
			}
			$limit_next = $this_limit+$step;
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

function zz_get_subqueries($subqueries, $zz, &$zz_tab, $zz_conf) {
	if (!$subqueries) return false; // && $zz['action'] != 'delete'
	foreach ($subqueries as $i => $subquery) { // $i starts with 1, as written in edit.inc.php
		// basics for all subrecords of the same table
		if (!empty($zz['fields'][$subquery]['values']))
			$zz_tab[$i]['values'] = $zz['fields'][$subquery]['values'];
		$zz_tab[$i]['table'] = $zz['fields'][$subquery]['table'];
		$zz_tab[$i]['table_name'] = $zz['fields'][$subquery]['table_name'];
		$zz_tab[$i]['max_records'] = (isset($zz['fields'][$subquery]['max_records'])) 
			? $zz['fields'][$subquery]['max_records'] : $zz_conf['max_detail_records'];
		$zz_tab[$i]['min_records'] = (isset($zz['fields'][$subquery]['min_records'])) 
			? $zz['fields'][$subquery]['min_records'] : $zz_conf['min_detail_records'];
		$zz_tab[$i]['no'] = $subquery;
		$zz_tab[$i]['sql'] = $zz['fields'][$subquery]['sql'];
		$zz_tab[$i]['sql_not_unique'] =  (!empty($zz['fields'][$subquery]['sql_not_unique']) ? $zz['fields'][$subquery]['sql_not_unique'] : false);
		// now go into each individual subrecord
		if ($zz['mode']) {
			// first check for review or access, first if must be here because access might override mode here!
			if ($zz['mode'] == 'review' OR $zz['mode'] == 'show'
				OR (!empty($zz['fields'][$zz_tab[$i]['no']]['access']) && $zz['fields'][$zz_tab[$i]['no']]['access'] == 'show'))
				$zz_tab[$i] = zz_subqueries($i, false, false, true, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // sql
			elseif ($zz['mode'] == 'add')
				$zz_tab[$i] = zz_subqueries($i, $zz_tab[$i]['min_records'], true, false, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // min, details
			elseif ($zz['mode'] == 'edit')
				$zz_tab[$i] = zz_subqueries($i, $zz_tab[$i]['min_records'], true, true, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // min, details, sql
			elseif ($zz['mode'] == 'delete')
				$zz_tab[$i] = zz_subqueries($i, false, false, true, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // sql
		} elseif ($zz['action'] && !empty($_POST[$zz['fields'][$subquery]['table_name']])  
			&& is_array($_POST[$zz['fields'][$subquery]['table_name']])) {
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
	global $zz_conf;
	// $subtable is branch of $zz with all data for specific subtable
	// function will be run twice from edit.inc, therefore be careful, programmer!
	global $zz_error;
	$records = false;
	$my = $zz_tab[$i];
	if (isset($_POST[$subtable['table_name']]))
		$myPOST = $_POST[$subtable['table_name']];
	else
		$myPOST = array();
	$deleted_ids = (!empty($my['deleted']) ? $my['deleted'] : array());
	foreach ($subtable['fields'] as $field)
		if (isset($field['type']) && $field['type'] == 'id') $id_field_name = $field['field_name'];
	if (isset($_POST['deleted'][$subtable['table_name']]))
	//	fill existing deleted ids in $deleted_ids
		foreach ($_POST['deleted'][$subtable['table_name']] as $deleted)
			$deleted_ids[] = $deleted[$id_field_name];
	if ($min) $records = $min;
	if ($details) {
		if (isset($_POST['records'][$i]) && $_POST['records'][$i]) {
			$records = $_POST['records'][$i];
			if (!$records) $records = 1;
			// possibly check values if correcht
		}
		if (!empty($_POST['subtables']['add'][$i])) // Value does not matter
			$records++;
		if (isset($_POST['subtables']['remove'][$i])) {
			foreach (array_keys($myPOST) as $k) {
				if (!empty($_POST['subtables']['remove'][$i][$k])) { // Value does not matter
					if (isset($myPOST[$k][$id_field_name])) // has ID
						$deleted_ids[] = $myPOST[$k][$id_field_name];
					unset($myPOST[$k]);
					$records--;
				}
			}
//			$records--;
		}
	}
	if ($sql) {
		$c_sql = zz_edit_sql($zz_tab[$i]['sql'].' '.$zz_tab[$i]['sql_not_unique'], 'WHERE', 
			$zz_tab[0]['table'].'.'.$zz_tab[0][0]['id']['field_name'].' = "'.$zz_tab[0][0]['id']['value'].'"');
		if ($zz_conf['debug_allsql']) echo "<div>zz_subquery:<br /><pre>$c_sql</pre></div>";
		$result = mysql_query($c_sql);
		if ($result) if (mysql_num_rows($result))
			while ($line = mysql_fetch_array($result)) 
				if (!in_array($line[$id_field_name], $deleted_ids)) 
					$ids[] = $line[$id_field_name];
		if (mysql_error()) {
			$zz_error[] = array(
				'msg' => 'There is an error in the sql statement for the detail record.',
				'mysql' => mysql_error(),
				'query' => $c_sql);
		}
		if (isset($ids)) 
			if (count($ids) > $records)
				$records = count($ids);
	}
	if ($my['max_records'])
		if ($records > $my['max_records']) $records = $my['max_records'];
	for ($k = 0; $k<= $records-1; $k++) {
		if (isset($my[$k])) {
			if (!empty($subtable['access']))
				$my[$k]['access'] = $subtable['access'];
			continue; // do not change values if they are already there (important for error messages etc.)
		}
		if (!empty($subtable['access']))
			$my[$k]['access'] = $subtable['access'];
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
	$my['deleted'] = array_unique($deleted_ids); // remove double entries
	return $my;
	
}

/** Requery record 
 * 
 *		if everything was successful, requery record (except in case it was deleted)
 *		if not, change formhead and write POST values back into form
 *
 *		changed fields:
 *		- $zz['record'] (initalized)
 *		- $zz['action']
 *		- $zz['formhead']
 *		- $zz['fields']
 *
 * @param $zz(array) complete zz array
 * @param $validation
 * @param $sql
 * @param $table
 * @param $mode
 * @return $zz
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_requery_record($my, $validation, $sql, $table, $mode) {
	global $text;
	global $zz_error;
	global $zz_conf;
	
	// in case, record was deleted, record requery is not neccessary
	// if $validation is false, this means that the check for referential integrity was not passed
	if ($my['action'] == 'delete') { // no requery neccessary
		if (!$validation) {
			$my['formhead'] = 'Deletion not possible'; // check for referential integrity was not passed
		}
		return $my;
	}
	
	// in case validation was passed or access is 'show'
	// everything's okay.
	if ($validation OR (!empty($my['access']) && $my['access'] == 'show')) {
		// initialize 'record'
		$my['record'] = false;
		// check whether record already exists (this is of course impossible for adding a record!)
		if ($mode != 'add' OR $my['action']) {
			if ($my['id']['value']) {
				$sql_edit = zz_edit_sql($sql, 'WHERE', $table.'.'.$my['fields'][1]['field_name']." = '".$my['id']['value']."'");
				if ($zz_conf['debug_allsql']) echo "<div>zz_requery_record:<br /><pre>$sql_edit</pre></div>";
				$result_edit = mysql_query($sql_edit);
				if ($result_edit) {
					if (mysql_num_rows($result_edit) == 1)
						$my['record'] = mysql_fetch_assoc($result_edit);
					// else $zz_error[]['msg'].= 'Error in Database. Possibly the SQL
					// statement is incorrect: '.$sql_edit;
				} else {
					if ($zz_conf['debug_allsql']) echo "<div>Huch! Ein Fehler:<br /><pre>$sql_edit</pre></div>";
					$zz_error[] = array('msg' => $text['error-sql-incorrect'], 
						'mysql' => mysql_error(),
						'query' => $sql_edit);
				}
			}
		}
	// record has to be passed back to user
	} else {
		$my['record'] = (isset($my['POST-notvalid']) ? $my['POST-notvalid'] : $my['POST']);
		
	//	get record for display fields and maybe others
		$my['record_saved'] = false;
		$sql_edit = zz_edit_sql($sql, 'WHERE', $table.'.'.$my['fields'][1]['field_name']." = '".$my['id']['value']."'");
		$result_edit = mysql_query($sql_edit);
		if ($result_edit) if (mysql_num_rows($result_edit) == 1)
			$my['record_saved'] = mysql_fetch_assoc($result_edit);

	//	display form again			
		$my['formhead'] = 'Review record';
		$my['action'] = 'review';

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
		if ($validate_errors) $zz_error[]['msg'] = $validate_errors.'</ul>';
	}
	return $my;
}

function zz_fill_out(&$tab) {
	global $text;
	global $zz_conf;
	foreach (array_keys($tab['fields']) as $no) {
		if (!$tab['fields'][$no]) {
			unset($tab['fields'][$no]);
			continue;
		}
		if (!isset($tab['fields'][$no]['type'])) // default type: text
			$tab['fields'][$no]['type'] = 'text';
		if (!isset($tab['fields'][$no]['title'])) { // create title
			$tab['fields'][$no]['title'] = ucfirst($tab['fields'][$no]['field_name']);
			$tab['fields'][$no]['title'] = str_replace('_ID', ' ', $tab['fields'][$no]['title']);
			$tab['fields'][$no]['title'] = str_replace('_id', ' ', $tab['fields'][$no]['title']);
			$tab['fields'][$no]['title'] = str_replace('_', ' ', $tab['fields'][$no]['title']);
			$tab['fields'][$no]['title'] = rtrim($tab['fields'][$no]['title']);
		}
		if (($zz_conf['multilang_fieldnames'])) {// translate fieldnames, if set
			$tab['fields'][$no]['title'] = $text[$tab['fields'][$no]['title']];
			if (!empty($tab['fields'][$no]['explanation']))
				$tab['fields'][$no]['explanation'] = $text[$tab['fields'][$no]['explanation']];
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
			zz_fill_out($tab['fields'][$no]);
	}
}

function zz_log_sql($sql, $user, $record_id = false) {
	global $zz_conf;
	// logs each INSERT, UPDATE or DELETE query
	// with record_id
	if (!empty($zz_conf['logging_id']) AND $record_id)
		$sql = 'INSERT INTO '.$zz_conf['logging_table'].' 
			(query, user, record_id) VALUES ("'.mysql_real_escape_string($sql).'", "'.$user.'", '.$record_id.')';
	// without record_id, only for backwards compatibility
	else
		$sql = 'INSERT INTO '.$zz_conf['logging_table'].' 
			(query, user) VALUES ("'.mysql_real_escape_string($sql).'", "'.$user.'")';
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
		if (strstr($sql, 'ORDER BY'))
			// if there's already an order, put new orders in front of this
			$sql = str_replace ('ORDER BY', ' ORDER BY '.implode(',', $order).', ', $sql);
		else
			// if not, just append the order
			$sql.= ' ORDER BY '.implode(', ', $order);
	} 
	return $sql;
}

function zz_create_identifier($vars, $my, $table, $field, $conf) {
	if (empty($vars)) return false;
	if (in_array($my['fields'][$field]['field_name'], array_keys($vars)) && $vars[$my['fields'][$field]['field_name']]) 
		return $vars[$my['fields'][$field]['field_name']]; // do not change anything if there has been a value set once and identifier is in vars array
	$con_filename = isset($conf['forceFilename']) ? substr($conf['forceFilename'], 0, 1) : '-';
	$con_vars = !empty($conf['concat']) ? (is_array($conf['concat']) 
		? $conf['concat'] : substr($conf['concat'], 0, 1)) : '.';
	$con_exists = !empty($conf['exists']) ? substr($conf['exists'], 0, 1) : '.';
	$con_lowercase = isset($conf['lowercase']) ? $conf['lowercase'] : true;
	$i = 0;
	foreach ($vars as $index => $var) {
		if ($var) {
			if (strstr($var, '/') AND $i != count($vars)-1) { // last var will be treated normally, other vars may inherit slashes from dir names
				$dir_vars = explode('/', $var);
				foreach ($dir_vars as $d_var) 
					if ($d_var) {
						$my_var = forceFilename($d_var, $con_filename);
						if ($con_lowercase) $my_var = strtolower($my_var);
						$idf_arr[] = $my_var;
					}
			} else {
				$my_var = forceFilename($var, $con_filename);
				if ($con_lowercase) $my_var = strtolower($my_var);
				$idf_arr[] = $my_var;
			}
		}
		$i++;
	}
	if (empty($idf_arr)) return false;
	if (!is_array($con_vars))
		$idf = implode($con_vars, $idf_arr);
	else { // idf 0 con 0 idf 1 con 1 idf 2 con 1 ...
		$my_con = 0;
		$my_idf = 0;
		$idf = false;
		while (isset($idf_arr[$my_idf])) {
			if (!isset($con_vars[$my_con])) $my_con--; // use last if there are no more
			$idf .= $idf_arr[$my_idf];
			if (count($idf_arr) > $my_idf +1) $idf .= $con_vars[$my_con];
			$my_con++;
			$my_idf++;
		}
	}		
	if (!empty($conf['prefix'])) $idf = $conf['prefix'].$idf;
	$i = (!empty($conf['start']) ? $conf['start'] : 2); // start value, if idf already exists
	if (!empty($conf['start_always'])) $idf .= $conf['exists'].$i;
	else $conf['start_always'] = false;
	if (!empty($my['fields'][$field]['maxlength']) && ($my['fields'][$field]['maxlength'] < strlen($idf)))
		$idf = substr($idf, 0, $my['fields'][$field]['maxlength']);
	$idf = zz_exists_identifier($idf, $i, $table, $my['fields'][$field]['field_name'], 
		$my['fields'][1]['field_name'], $my['POST'][$my['fields'][1]['field_name']], 
		$con_exists, $my['fields'][$field]['maxlength'], $conf['start_always']);
	return $idf;
}

function zz_exists_identifier($idf, $i, $table, $field, $id_field, $id_value, 
	$con_exists = '.', $maxlength = false, $start_always = false) {
	global $zz_conf;
	$sql = 'SELECT '.$field.' FROM '.$table.' WHERE '.$field.' = "'.$idf.'"
		AND '.$id_field.' != '.$id_value;
	if ($zz_conf['debug_allsql']) echo "<div>zz_exists_identifier_query:<br /><pre>$sql</pre></div>";
	$result = mysql_query($sql);
	if ($result) if (mysql_num_rows($result)) {
		if ($i > 2 OR $start_always) // with start_always, we can be sure, that a generated suffix exists so we can safely remove it. for other cases, this is only true for $i > 2.
			$idf = substr($idf, 0, strrpos($idf, $con_exists));
		$suffix = $con_exists.$i;
		if ($maxlength && strlen($idf.$suffix) > $maxlength) 
			$idf = substr($idf, 0, ($maxlength-strlen($suffix))); 
			// in case there is a value for maxlength, make sure that resulting string won't be longer
		$idf = $idf.$suffix;
		$i++;
		$idf = zz_exists_identifier($idf, $i, $table, $field, $id_field, 
			$id_value, $con_exists, $maxlength, $start_always);
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


// puts parts of SQL query in correct order when they have to be added
// this function works only for sql queries without UNION:
// SELECT ... FROM ... JOIN ...
// WHERE ... GROUP BY ... HAVING ... ORDER BY ... LIMIT ...
function zz_edit_sql($sql, $n_part = false, $values = false) {
	// remove whitespace
	$sql = preg_replace("/\s+/", " ", $sql);
	// SQL statements in descending order
	$statements_desc = array('LIMIT', 'ORDER BY', 'HAVING', 'GROUP BY', 'WHERE');
	foreach ($statements_desc as $statement) {
		$search = '/(.+) '.$statement.' (.+?)$/i'; 
		// = look only for last statement
		// and put remaining query in [1] and cut off part in [2]
		if (preg_match($search, $sql, $o_parts[$statement])) {
			// check if there are () outside '' or "" and count them to check
			// whether we are inside a subselect
			$temp_sql = $o_parts[$statement][1]; // look at first part of query

			// 1. remove everything in '' and "" which are not escaped
			// replace \" character sequences which escape "
			$temp_sql = preg_replace('/\\\\"/', '', $temp_sql);
			// replace "strings" without " inbetween, empty "" as well
			$temp_sql = preg_replace('/"[^"]*"/', "weg", $temp_sql);
			// replace \" character sequences which escape "
			$temp_sql = preg_replace("/\\\\'/", '', $temp_sql);
			// replace "strings" without " inbetween, empty '' as well
			$temp_sql = preg_replace("/'[^']*'/", "weg", $temp_sql);

			// 2. count opening and closing ()
			//  if equal ok, if not, it's a statement in a subselect
			// assumption: there must not be brackets outside " or '
			if (substr_count($temp_sql, '(') == substr_count($temp_sql, ')'))
				$sql = $o_parts[$statement][1]; // looks correct, so go on.
			else
				unset($o_parts[$statement]); // ignore all this.
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
				// append old ORDER BY to new ORDER BY
				if (!empty($o_parts['ORDER BY'][2])) 
					$o_parts['ORDER BY'][2] = $values.', '.$o_parts['ORDER BY'][2];
				else
					$o_parts['ORDER BY'][2] = $values;
			break;
			case 'WHERE':
			case 'GROUP BY':
			case 'HAVING':
				if (!empty($o_parts[$n_part][2])) 
					$o_parts[$n_part][2] = '('.$o_parts[$n_part][2].') AND ('.$values.')';
				else 
					$o_parts[$n_part][2] = $values;
			break;
			default:
				echo 'The variable <code>'.$n_part.'</code> is not supported by zz_edit_sql().';
				exit;
			break;
		}
	}
	$statements_asc = array_reverse($statements_desc);
	foreach ($statements_asc as $statement) {
		if (!empty($o_parts[$statement][2])) 
			$sql.= ' '.$statement.' '.$o_parts[$statement][2];
	}
	return $sql;
}

function zz_check_select($my, $f, $max_select) {
	global $text;
	global $zz_error;
	global $zz_conf;
	$sql = $my['fields'][$f]['sql'];
	preg_match('/SELECT (.+) FROM /i', $sql, $fieldstring); // preg_match, case insensitive, space after select, space around from - might not be 100% perfect, but should work always
	$fields = explode(",", $fieldstring[1]);
	$oldfield = false;
	$newfields = false;
	foreach ($fields as $myfield) {
		if ($oldfield) $myfield = $oldfield.','.$myfield; // oldfield, so we are inside parentheses
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
			$field = trim($field);
			if (empty($my['fields'][$f]['show_hierarchy']) OR $field != $my['fields'][$f]['show_hierarchy']) {
				// do not search in show_hierarchy as this field is there for presentation only
				// and might be removed below!
				if (!$wheresql) $wheresql.= $where.'(';
				elseif (!$index) $wheresql.= ' ) AND (';
				else $wheresql.= ' OR ';
				if (preg_match('/^(.+)\.\.\.$/', $value, $short_value)) $value = $short_value[1]; // reduces string with dots which come from values which have been cut beforehands
				$wheresql.= $field.' LIKE "%'.$value.'%"'; 
			}
		}
	$sql.= $wheresql.')';
	if ($sqlorder) $sql.= $sqlorder;
	if ($zz_conf['debug_allsql']) echo "<div>zz_check_select query:<br /><pre>$sql</pre></div>";
	$result = mysql_query($sql);
	if ($zz_conf['debug']) {
		echo '<div class="debug">';
		echo '<h4>Debugging info for zz_check_select():</h4>';
		echo mysql_error().'<br>';
		echo $sql.'<br>';
		if ($result) echo mysql_num_rows($result);
		echo '</div>';
	}
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
			$my['fields'][$f]['class'] = 'reselect';
			if (!empty($my['fields'][$f]['show_hierarchy'])) {
				// since this is only a part of the list, hierarchy does not make sense
				$my['fields'][$f]['sql'] = preg_replace('/,*\s*'.$my['fields'][$f]['show_hierarchy'].'/', '', $my['fields'][$f]['sql']);
				$my['fields'][$f]['show_hierarchy'] = false;
			}
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
		$zz_error[] = array('msg' => mysql_error(), 'query' => $sql);
		$my['fields'][$f]['check_validation'] = false;
		$my['validation'] = false;
	}
	return $my;
}

function zz_check_password($old, $new1, $new2, $sql) {
	global $zz_error;
	global $text;
	if ($new1 != $new2) {
		$zz_error[]['msg'] = $text['New passwords do not match. Please try again.'];
		return false; // new passwords do not match
	}
	if ($old == $new1) {
		$zz_error[]['msg'] = $text['New and old password are identical. Please choose a different new password.'];
		return false; // old password eq new password - this is against identity theft if someone interferes a password mail
	}
	$result = mysql_query($sql);
	if ($result) if (mysql_num_rows($result) == 1)
		$old_pwd = mysql_result($result, 0, 0);
	if (empty($old_pwd)) {
		$zz_error[]['msg'] = $text['database-error'];
		return false;
	}
	if (md5($old) == $old_pwd) {
		$zz_error[]['msg'] = $text['Your password has been changed!'];
		return md5($new1); // new1 = new2, old = old, everything is ok
	} else {
		$zz_error[]['msg'] = $text['Your current password is different from what you entered. Please try again.'];
		return false;
	}
}

function zz_get_identifier_sql_vars($sql, $id, $fieldname = false) {
	global $zz_conf;
	$line = false;
	$line[$fieldname] = false;
	$sqlp = explode(' ORDER BY ', $sql);
	$sql = $sqlp[0];
	if (stristr($sql, ' WHERE ')) $sql.= ' AND ';
	else $sql.= ' WHERE ';
	$sqlc = explode(' ', $sql); // get first token
	if (substr($sqlc[1], -1) == ',') $sqlc[1] = substr($sqlc[1], 0, -1);
	$sql.= $sqlc[1].' = '.$id; // first token is always ID field
	if (!empty($sqlp[1])) $sql.= ' ORDER BY '.$sqlp[1];
	if ($zz_conf['debug_allsql']) echo "<div>zz_get_identifier_sql_vars query:<br /><pre>$sql</pre></div>";
	$result = mysql_query($sql);
	if ($result) if (mysql_num_rows($result) == 1)
		$line = mysql_fetch_assoc($result);
	if ($fieldname) return $line[$fieldname];
	else return $line;
}

function zz_get_identifier_vars(&$my, $f) {
	// content of ['fields']
	// possible syntax: fieldname[sql_fieldname] or tablename.fieldname or fieldname
	$func_vars = false;
	foreach ($my['fields'][$f]['fields'] as $var) {
	//	check for substring parameter
		preg_match('/{(.+)}$/', $var, $substr);
		if ($substr) $var = preg_replace('/{(.+)}$/', '', $var, $substr);
	//	check whether subtable or not
		if (strstr($var, '.')) { // subtable
			$vars = explode('.', $var);
			if (isset($my['POST'][$vars[0]]) && isset($my['POST'][$vars[0]][0][$vars[1]])) {
				// todo: problem: subrecords are being validated after main record, so we might get invalid results
				$func_vars[$var] = $my['POST'][$vars[0]][0][$vars[1]]; // this might not be correct, because it ignores the table_name
				foreach ($my['fields'] as $field)
					if ((!empty($field['table']) && $field['table'] == $vars[0])
						OR (!empty($field['table_name']) && $field['table_name'] == $vars[0]))
						foreach ($field['fields'] as $subfield)
							if (!empty($subfield['field_name']) && $subfield['field_name'] == $vars[1]) 
								if ($subfield['type'] == 'date') {
									$func_vars[$var] = datum_int($func_vars[$var]); 
									$func_vars[$var] = str_replace('-00', '', $func_vars[$var]); 
									$func_vars[$var] = str_replace('-00', '', $func_vars[$var]); 
								}
			}
			if (empty($func_vars[$var])) {
				preg_match('/^(.+)\[(.+)\]$/', $vars[1], $fieldvar); // split array in variable and key
				foreach ($my['fields'] as $field) {
					if ((!empty($field['table']) && $field['table'] == $vars[0])
						OR (!empty($field['table_name']) && $field['table_name'] == $vars[0])) 
						foreach ($field['fields'] as $subfield)
							if (!empty($subfield['sql']) && !empty($subfield['field_name']) // empty: == subtable
								&& $subfield['field_name'] == $fieldvar[1])
								$func_vars[$var] = zz_get_identifier_sql_vars($subfield['sql'], 
									$my['POST'][$vars[0]][0][$subfield['field_name']], $fieldvar[2]);
				}
			}
		} else
			if (isset($my['POST'][$var]))
				$func_vars[$var] = $my['POST'][$var];
			if (empty($func_vars[$var])) { // could be empty because it's an array
				preg_match('/^(.+)\[(.+)\]$/', $var, $fieldvar); // split array in variable and key
				foreach ($my['fields'] as $field)
					if (!empty($field['sql']) && !empty($field['field_name']) // empty: == subtable
						&& !empty($fieldvar[1]) && $field['field_name'] == $fieldvar[1])
						$func_vars[$var] = zz_get_identifier_sql_vars($field['sql'], 
							$my['POST'][$field['field_name']], $fieldvar[2]);
		}
		if ($substr)
			eval ($line ='$func_vars[$var] = substr($func_vars[$var], '.$substr[1].');');
	}
	return $func_vars;
}

function zz_nice_headings(&$zz_fields, &$zz_conf, &$zz_error) {
	global $zz_conf;
	foreach (array_keys($_GET['where']) as $mywh) {
		$wh = explode('.', $mywh);
		if (!isset($wh[1])) $index = 0; // without .
		else $index = 1;
		$zz_conf['heading_addition'] = false;
		if (isset($zz_conf['heading_sql'][$wh[$index]]) && 
			isset($zz_conf['heading_var'][$wh[$index]])) {
		//	create sql query, with $mywh instead of $wh[$index] because first might be ambiguous
			if (strstr($zz_conf['heading_sql'][$wh[$index]], 'WHERE'))
				$wh_sql = str_replace('WHERE', 'WHERE ('.$mywh.' = '.$_GET['where'][$mywh].') AND ', $zz_conf['heading_sql'][$wh[$index]]);
			elseif (strstr($zz_conf['heading_sql'][$wh[$index]], 'ORDER BY'))
				$wh_sql = str_replace('ORDER BY', 'WHERE ('.$mywh.' = '.$_GET['where'][$mywh].') ORDER BY ', $zz_conf['heading_sql'][$wh[$index]]);
			else
				$wh_sql = $zz_conf['heading_sql'][$wh[$index]].' WHERE ('.$mywh.' = '.$_GET['where'][$mywh].') LIMIT 1';
		//	if key_field_name is set
			foreach ($zz_fields as $field)
				if (isset($field['field_name']) && $field['field_name'] == $wh[$index])
					if (isset($field['key_field_name']))
						$wh_sql = str_replace($wh[$index], $field['key_field_name'], $wh_sql);
		//	do query
			if ($zz_conf['debug_allsql']) echo "<div>zz_nice_headings query:<br /><pre>$wh_sql</pre></div>";
			$result = mysql_query($wh_sql);
			if (!$result) {
				$zz_error[] = array('msg' => 'Error',
					'query' => $wh_sql,
					'mysql' => mysql_error());
			} else {
				$wh_array = mysql_fetch_assoc($result);
				foreach ($zz_conf['heading_var'][$wh[$index]] as $myfield)
					$zz_conf['heading_addition'].= ' '.$wh_array[$myfield];
			}
		} elseif (isset($zz_conf['heading_enum'][$wh[$index]]) && 
			isset($zz_conf['heading_var'][$wh[$index]])) {
				$zz_conf['heading_addition'].= ' '.$_GET['where'][$mywh];
				// todo: insert corresponding value in enum_title
		}
		if ($zz_conf['heading_addition']) {
			$zz_conf['heading'].= ':<br>';
			if (!empty($zz_conf['heading_link'][$wh[$index]])) {
				if (strstr($zz_conf['heading_link'][$wh[$index]], '?')) $sep = '&amp;';
				else $sep = '?';
				$zz_conf['heading'].= '<a href="'.$zz_conf['heading_link'][$wh[$index]]
					.$sep.'mode=show&amp;id='.$_GET['where'][$mywh].'">';
			}
			$zz_conf['heading'] .= $zz_conf['heading_addition'];
			if (!empty($zz_conf['heading_link'][$wh[$index]]))
				$zz_conf['heading'] .= '</a>';
		}
	}
	if (!empty($_GET['q'])) {
		global $text;
		$fieldname = false;
		$zz_conf['selection'] .= $text['Search'].': ';
		$add_equal_sign = false;
		if (!empty($_GET['scope'])) {
			$scope = substr($_GET['scope'], strrpos($_GET['scope'], '.') + 1);
			foreach ($zz_fields as $field) {
				if ($field['field_name'] == $scope)
					$fieldname = $field['title'];
			}
			$add_equal_sign = true;
		}
		if (substr($_GET['q'], 0, 1) == '<')
			$zz_conf['selection'] .= '< '.htmlentities(substr($_GET['q'], 1));
		elseif (substr($_GET['q'], 0, 1) == '>')
			$zz_conf['selection'] .= '> '.htmlentities(substr($_GET['q'], 1));
		else {
			if (substr($_GET['q'], 0, 1) == '\\')
				$_GET['q'] = substr($_GET['q'], 1);
			if ($add_equal_sign)
				$zz_conf['selection'] .= $fieldname.' = ';
			$zz_conf['selection'] .= '*'.htmlentities($_GET['q']).'*';
		}
	}
}

function zz_add_modules($modules, $path, $zz_conf_global) {
//	initialize variables
	$mod['modules'] = false;
	$zz = false;
	$zz_default = false;
	$zz_allowed_params = false;
	$zz_conf = false;

//	import modules
	foreach ($modules as $module)
		if (file_exists($path.'/'.$module.'.inc.php')) {
			include_once($path.'/'.$module.'.inc.php');
			$mod['modules'][$module] = true;
		} elseif (file_exists($path.'/'.$module.'.php')) {
			include_once($path.'/'.$module.'.php');
			$mod['modules'][$module] = true;
		} else {
			$mod['modules'][$module] = false;
			if ($zz_conf['debug'])
				echo $path.$module.'.php not found<br>Optional module "'.$module.'" was not included.';
		}
	$mod['vars']['zz'] = $zz;
	$mod['vars']['zz_default'] = $zz_default;
	$mod['vars']['zz_allowed_params'] = $zz_allowed_params;
	$mod['vars']['zz_conf'] = $zz_conf;
	return $mod;
}


/** Prepares moving of folders which are glued to records
 * 
 * 1- retrieve current record from db 
 *    -- TODO: what happens if someone simultaneously accesses this record
 * @param $zz_tab(array) complete zz_tab array
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_foldercheck_before(&$zz_tab) {
	// in case of deletion or update, save old record to be able
	// to get old filename before deletion or update
	$sql = zz_edit_sql($zz_tab[0]['sql'], 'WHERE', $zz_tab[0]['table'].'.'.$zz_tab[0][0]['id']['field_name']
		.' = '.$zz_tab[0][0]['id']['value']);
	$result = mysql_query($sql);
	if ($result) if (mysql_num_rows($result))
		$zz_tab[0][0]['old_record'] = mysql_fetch_assoc($result);
	if ($error = mysql_error())
		echo '<p>Error in script: zz_foldercheck_before() <br>'.$sql
			.'<br>'.$error.'</p>';
}

/** Create, move or delete folders which are connected to records
 * 
 * @param $zz_tab(array) complete zz_tab array
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_foldercheck(&$zz_tab, $zz_conf) {
	foreach ($zz_conf['folder'] as $folder) {
		$path = zz_makepath($folder, $zz_tab, 'new', 'file');
		$old_path = zz_makepath($folder, $zz_tab, 'old', 'file');
	}
	if ($old_path != $path) {
		if (file_exists($old_path)) {
			if (!file_exists($path)) {
				$success = rename($old_path, $path);
				if ($success) {
					$zz_tab[0]['folder'][] = array('old' => $old_path, 'new' => $path);
					return true;
				} else { 
					echo 'Critical error. Folder cannot be renamed.';
					return false;
				}
			} else {
				echo 'Critical error. There is already a folder by that name.';
				return false;
			}
		}
	}
}

/** Construct path from values
 * 
 *	'root'			DOCUMENT_ROOT
 *	'webroot'		relative to webroot /
 *	'mode'			function to do something with strings from now on
 *	'string1...n'	string
 *	'field1...n'	field value
 * @param $path(array) configuration variables
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_makepath($path, $zz_tab, $record = 'new', $do = false) {
	// set variables
	global $text;
	$p = false;
	$modes = false;
	$root = false;
	$webroot = false;

	// put path together
	foreach ($path as $pkey => $pvalue) {
		if ($pkey == 'root') $root = $pvalue;
		elseif ($pkey == 'webroot') $webroot = $pvalue;
		elseif (substr($pkey, 0, 4) == 'mode') $modes[] = $pvalue;
		elseif (substr($pkey, 0, 6) == 'string') $p .= $pvalue;
		elseif (substr($pkey, 0, 5) == 'field') {
			if ($record == 'new')
				$content = (!empty($zz_tab[0][0]['POST'][$pvalue])) 
					? zz_upload_reformat_field($zz_tab[0][0]['POST'][$pvalue])
					: zz_upload_sqlval($pvalue, $zz_tab[0]['sql'], 
						$zz_tab[0][0]['id']['value'], 
						$zz_tab[0]['table'].'.'.$zz_tab[0][0]['id']['field_name']);
			elseif ($record == 'old')
				$content = (!empty($zz_tab[0][0]['old_record']) 
					? $zz_tab[0][0]['old_record'][$pvalue] : '');
			if ($modes) foreach ($modes as $mode)
				if (function_exists($content))
					$content = $mode($content);
				else
					echo 'Configuration Error: mode with not-existing function';
			$p .= $content;
			$alt = $text['File: '].$content;
			$modes = false;
		}
	}

	switch ($do) {
		case 'file':
			$p = $root.$p; // webroot will be ignored
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


?>