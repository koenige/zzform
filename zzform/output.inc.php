<?php

/**
 * zzform
 * Output functions
 * will only be included if $zz_conf['generate_output'] = true
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2012 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * format a provisional heading if errors occur
 *
 * @param string $heading ($ops['heading'], from $zz['title'])
 * @param string $table table name as set in $zz['table']
 * @global array $zz_conf 'heading'
 * @return string $heading
 */
function zz_output_heading($heading, $table = '') {
	global $zz_conf;
	if (!isset($heading)) {
		$heading = $table;
		$heading = str_replace('_', ' ', $heading);
		$heading = ucfirst($heading);
	}
	if ($zz_conf['multilang_fieldnames']) {
		$heading = zz_text($heading);
		$zz_conf['heading_prefix'] = zz_text($zz_conf['heading_prefix']);
	}
	if ($zz_conf['heading_prefix'])
		$heading = $zz_conf['heading_prefix'].' '.$heading;
	return $heading;
}

/** 
 * Formats a heading for WHERE-conditions
 *
 * @param string $heading ($ops['heading'])
 * @param array $zz
 * @param array $where_condition, optional
 * @global array $zz_conf
 * @global array $zz_error
 * @return string $heading
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_nice_headings($heading, $zz, $where_condition = array()) {
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
		if (!isset($zz['subtitle'][$wh[$index]])) continue;
		$subheading = $zz['subtitle'][$wh[$index]];
		if (!isset($subheading['var']) AND !isset($subheading['value'])) continue;
		$heading_addition[$i] = array();
		if (isset($subheading['sql']) AND $where_condition[$mywh]) {
			// only if there is a value! (might not be the case if 
			// write_once-fields come into play)
			// create sql query, with $mywh instead of $wh[$index] because first 
			// might be ambiguous
			$wh_sql = zz_edit_sql($subheading['sql'], 'WHERE', 
				$mywh.' = '.zz_db_escape($where_condition[$mywh]));
			$wh_sql .= ' LIMIT 1';
			//	if key_field_name is set
			foreach ($zz['fields'] as $field)
				if (isset($field['field_name']) && $field['field_name'] == $wh[$index])
					if (isset($field['key_field_name']))
						$wh_sql = str_replace($wh[$index], $field['key_field_name'], $wh_sql);
			// just send a notice if this doesn't work as it's not crucial
			$heading_values = zz_db_fetch($wh_sql, '', '', '', E_USER_NOTICE);
			if ($heading_values) {
				foreach ($subheading['var'] as $myfield)
					$heading_addition[$i][] = $heading_values[$myfield];
			}
		} elseif (isset($subheading['enum'])) {
			$heading_addition[$i][] = htmlspecialchars($where_condition[$mywh]);
			// @todo: insert corresponding value in enum_title
		} elseif (isset($subheading['value'])) {
			$heading_addition[$i][] = htmlspecialchars($where_condition[$mywh]);
		}
		if (empty($subheading['concat'])) $subheading['concat'] = ' ';
		if (is_array($subheading['concat'])) {
			$addition = '';
			foreach ($heading_addition[$i] AS $index => $text) {
				if (!isset($subheading['concat'][$index-1])) {
					$subheading['concat'][$index-1] = end($subheading['concat']);
				}
				if ($index) $addition .= $subheading['concat'][$index-1];
				$addition .= $text;
			}
			$heading_addition[$i] = $addition;
		} else {
			$heading_addition[$i] = implode($subheading['concat'], $heading_addition[$i]);
		}
		if ($heading_addition[$i] AND !empty($subheading['link'])) {
			$append = '';
			if (empty($subheading['link_no_append'])) {
				if (strstr($subheading['link'], '?')) $sep = '&amp;';
				else $sep = '?';
				$append = $sep.'mode=show&amp;id='.urlencode($where_condition[$mywh]);
			}
			$heading_addition[$i] = '<a href="'.$subheading['link'].$append.'">'
				.$heading_addition[$i].'</a>';
		}
		if (empty($heading_addition[$i])) unset($heading_addition[$i]);
		else {
			if (!empty($subheading['prefix']))
				$heading_addition[$i] = ' '.$subheading['prefix'].$heading_addition[$i];
			if (!empty($subheading['suffix']))
				$heading_addition[$i] .= $subheading['suffix'];
		}
		$i++;
	}
	if ($heading_addition) {
		$heading .= ':<br>'.implode(' &#8211; ', $heading_addition); 
	}
	return zz_return($heading);
}

/**
 * HTML output of detail-links for list view
 *
 * @param array $conf = $zz_conf_record
 * 	- array 'details'
 * 	- mixed 'details_url'
 * 	- array 'details_base'
 *		optional; must be set for each key in 'details', if unset, the link base
 *		will be created from 'details'
 * 	- string 'details_target'
 * 	- bool 'details_referer'
 *  - array 'details_sql'
 * @param int $id
 * @param array $line
 * @global array $zz_conf
 * @return string HTML output of all detail links
 */
function zz_show_more_actions($conf, $id, $line = false) {
	global $zz_conf;
	if (!function_exists('forceFilename')) {
		echo zz_text('Function forceFilename() required but not found! It is as well '
			.'possible that <code>$zz_conf[\'character_set\']</code> is incorrectly set.');
		exit;
	}
 	if (empty($conf['details_url'])) $conf['details_url'] = '.php?id=';
	$act = array();
	foreach ($conf['details'] as $key => $new_action) {
		$output = false;
		if ($conf['details_base']) $new_action_url = $conf['details_base'][$key];
		else $new_action_url = strtolower(forceFilename($new_action));
		$output .= '<a href="'.$new_action_url;
		if (isset($conf['details_url'][$key]) && is_array($conf['details_url'][$key])) {
		// values are different for each key
			foreach ($conf['details_url'][$key] as $part_key => $value) {
				if (substr($part_key, 0, 5) == 'field') {
					if (empty($line)) continue 2;
					$output .= $line[$value];
				} else {
					$output .= $value;
				}
			}
		} elseif (is_array($conf['details_url'])) {
		// all values are the same
			foreach ($conf['details_url'] as $part_key => $value) {
				if (substr($part_key, 0, 5) == 'field') {
					if (empty($line)) continue 2;
					$output .= $line[$value];
				} else {
					$output .= $value;
				}
			}
		} else
			$output .= $conf['details_url'];
		if (!isset($conf['details_url']) OR !is_array($conf['details_url'])) $output .= $id;
		$output .= ($conf['details_referer'] ? '&amp;referer='.urlencode($_SERVER['REQUEST_URI']) : '')
			.'"'
			.(!empty($conf['details_target']) ? ' target="'.$conf['details_target'].'"' : '')
			.'>'.($zz_conf['multilang_fieldnames'] ? zz_text($new_action) : $new_action).'</a>';
		if (!empty($conf['details_sql'][$key])) {
			$count = zz_db_fetch($conf['details_sql'][$key].$id, '', 'single value');
			if ($count) $output .= '&nbsp;('.$count.')';
		}
		$act[] = $output;
	}
	$output = implode('&nbsp;| ', $act);
	return $output;
}

/**
 * sends a HTTP status header corresponding to server settings and HTTP version
 *
 * @param int $code
 * @return bool true if header was sent, false if not
 * @see wrap_http_status_header() (duplicate function)
 */
function zz_http_status_header($code) {
	// Set protocol
	$protocol = $_SERVER['SERVER_PROTOCOL'];
	if (!$protocol) $protocol = 'HTTP/1.0'; // default value
	if (substr(php_sapi_name(), 0, 3) == 'cgi') $protocol = 'Status:';
	
	switch ($code) {
	case '301':
		header($protocol." 301 Moved Permanently");
		return true;
	case '302':
		if ($protocol == 'HTTP/1.0')
			header($protocol." 302 Moved Temporarily");
		else
			header($protocol." 302 Found");
		return true;
	case '303':
		if ($protocol == 'HTTP/1.0')
			header($protocol." 302 Moved Temporarily");
		else
			header($protocol." 303 See Other");
		return true;
	case '304':
		header($protocol." 304 Not Modified");
		return true;
	case '307':
		if ($protocol == 'HTTP/1.0')
			header($protocol." 302 Moved Temporarily");
		else
			header($protocol." 307 Temporary Redirect");
		return true;
	}
	return false;
}

/**
 * Redirect to a different URL after successful action
 *
 * @param string $result ($ops['result'])
 * @param array $return ($ops['return'])
 * @param int $id_value ($zz_var['id']['value'])
 * @param array $zz_tab
 * @global array $zz_conf
 * @return bool false if nothing was done (redirect otherwise)
 */
function zz_output_redirect($result, $return, $id_value, $zz_tab) {
	global $zz_conf;
	if (!empty($zz_conf['redirect'][$result])) {
		if ($zz_conf['modules']['debug'] AND $zz_conf['debug_time']) {
			zz_debug_time($return);
		}
		if (is_array($zz_conf['redirect'][$result])) {
			$zz_conf['redirect'][$result] = zz_makepath($zz_conf['redirect'][$result], $zz_tab);
		}
		if (substr($zz_conf['redirect'][$result], 0, 1) == '/') {
			$zz_conf['redirect'][$result] = $zz_conf['int']['url']['base']
				.$zz_conf['redirect'][$result];
		}
		zz_http_status_header(303);
		header('Location: '.$zz_conf['redirect'][$result]);
		exit;
	} elseif (!$zz_conf['debug'] AND $zz_conf['redirect_on_change']) {
	// redirect to same URL, as to protect against reloading the POST variables
	// don't do so in case of debugging
		// multiple edit?
		$nos = '';
		if (is_array($id_value)) {
			$nos = '-'.count($id_value);
			$id_value = implode(',', $id_value);
		}
		$self = $zz_conf['int']['url']['full']
			.$zz_conf['int']['url']['qs'].$zz_conf['int']['url']['qs_zzform']
			.($zz_conf['int']['url']['qs_zzform'] ? '&' : $zz_conf['int']['url']['?&'])
			.'zzaction=';
		$secure = false;
		if (!empty($zz_conf['int']['hash_id'])) {
			// secret key has to be recalculated for insert operations
			// because there did not exist an id value before = hash was different
			$zz_conf['int']['secret_key'] = sha1($zz_conf['int']['hash'].$id_value);
			$secure = '&zzhash='.$zz_conf['int']['secret_key'];
		}
		switch ($result) {
		case 'successful_delete':
			if (!empty($zz_conf['redirect_to_referer_zero_records'])
				AND !empty($zz_conf['int']['referer']['path'])) {
				// redirect to referer if there are no records in list
				$id_field_name = $zz_tab[0]['table'].'.'.$zz_tab[0][0]['id']['field_name'];
				if (!zz_count_rows($zz_tab[0]['sql'], $id_field_name)) {
					if (empty($zz_conf['int']['referer']['scheme'])) {
						$self = $zz_conf['int']['url']['base'];
					} else {
						$self = $zz_conf['int']['referer']['scheme'].'://'
							.$zz_conf['int']['referer']['host'];
					}
					$self .= $zz_conf['int']['referer']['path'];
					if (empty($zz_conf['int']['referer']['query'])) {
						$self .= '?';
					} else {
						$self .= $zz_conf['int']['referer']['query'].'&';
					}
					$self .= 'zzaction=';
				}
			}
			zz_http_status_header(303);
			header('Location: '.$self.'delete'.$nos);
			exit;
		case 'successful_insert':
			zz_http_status_header(303);
			header('Location: '.$self.'insert&id='.$id_value.$secure);
			exit;
		case 'successful_update':
			zz_http_status_header(303);
			header('Location: '.$self.'update&id='.$id_value.$secure);
			exit;
		case 'no_update':
			zz_http_status_header(303);
			header('Location: '.$self.'noupdate&id='.$id_value.$secure);
			exit;
		}
	}
	return false;
}

/**
 * Output for HTML title element
 *
 * @param string $heading ($ops['heading'])
 * @param array $zz['fields']
 * @param array $zz_var
 *		'where_with_unique_id', 'limit_total_rows', 'id'
 * @param string $mode ($ops['mode'])
 * @global array $zz_conf
 * @return string $title
 */
function zz_nice_title($heading, $fields, $zz_var = array(), $mode = false) {
	global $zz_conf;

	// basic title
	$title = strip_tags($heading);

	// addition: filters
	if (!empty($_GET['filter']) AND !empty($zz_conf['filter'])) {
		foreach ($zz_conf['filter'] as $index => $f) {
			if (empty($_GET['filter'][$f['identifier']])) continue;
			$title .= $zz_conf['title_separator'].$f['title'].': ';
			if (!empty($f['selection']) AND !empty($f['selection'][$_GET['filter'][$f['identifier']]])) {
				$title .= $f['selection'][$_GET['filter'][$f['identifier']]];
			} else {
				$title .= htmlspecialchars($_GET['filter'][$f['identifier']]);
			}
		}
	}
	
	// addition: search
	if ($selection = zz_nice_selection($fields))
		$title .= $zz_conf['title_separator'].$selection;

	// addition: page
	if (!empty($zz_conf['limit'])) {
		if (isset($_GET['limit'])) 
			$page = $_GET['limit'] / $zz_conf['limit'];
		else
			$page = 1;
		// in case someone writes manually limit=85 where conf['limit'] = 20
		// don't add limit to page title
		if (is_int($page) AND $page AND !empty($zz_var['limit_total_rows'])) {
			$max_page = ceil($zz_var['limit_total_rows'] / $zz_conf['limit']);
			if ($max_page != 1) {
				if ($zz_conf['limit_display'] == 'entries') {
					$title .= $zz_conf['title_separator'].zz_text('records').' '
						.(($page-1)*$zz_conf['limit']).'-'
						.($page*$zz_conf['limit'] > $zz_var['limit_total_rows']
							? $zz_var['limit_total_rows'] : $page*$zz_conf['limit'])
						.'/'.$zz_var['limit_total_rows'];
				} else {
					$title .= $zz_conf['title_separator'].zz_text('page').' '.$page.'/'.$max_page;
				}
			}
		}
	}
	
	// addition: mode
	// don't show if zzhash is set (add_only, edit_only: too much information)
	$show_id = true;
	if (!$mode) $show_id = false;
	if ($mode == 'list_only') $show_id = false;
	if (!empty($_GET['zzhash'])) $show_id = false;
	if (!empty($zz_var['where_with_unique_id'])) $show_id = false;
	if ($show_id) {
		$title .= $zz_conf['title_separator'].zz_text($mode)
			.($zz_var['id']['value'] ? ': ID '.$zz_var['id']['value'] : '');
	}

	return $title;
}

/** 
 * Formats 'selection' for search results
 *
 * @param array $zz_fields
 * @global array $zz_conf
 * @return string $selection
 * @author Gustaf Mossakowski <gustaf@koenige.org>
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
		$scope = $_GET['scope'];
		if (strstr($scope, '.')) 
			$scope = substr($scope, strrpos($scope, '.') + 1);
		foreach ($zz_fields as $field) {
			if (!empty($field['field_name']) AND $field['field_name'] == $scope) {
				$fieldname = $field['title'];
				break;
			}
			if (!empty($field['display_field']) AND $field['display_field'] == $scope) {
				$fieldname = $field['title'];
				break;
			}
			if (!empty($field['table_name']) AND $field['table_name'] == $scope) {
				$fieldname = $field['title'];
				break;
			}
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
	$html_template = '<input type="hidden" name="%s" value="%s">'."\n";
	// parse_str just for first call of this function, not for recursive calls
	if (!$level) parse_str($query_string, $qp);
	$qp = zz_print_multiarray($qp);
	foreach ($qp as $line) {
		if (strstr($line['key'], '['))
			$top_key = substr($line['key'], 0, strpos($line['key'], '['));
		else
			$top_key = $line['key'];
		if (in_array($top_key, $unwanted_keys)) continue;
		$output.= sprintf($html_template, $line['key'], $line['value']);
	}
	return $output;
}

/**
 * displays array data in a more readable way in a table
 *
 * @param array $array
 * @param string $color CSS color
 * @param string $caption
 * @return string HTML output
 */
function zz_print_r($array, $color = false, $caption = 'Variables') {
	if (!$array) {
		echo 'Variable is empty.<br>';
		return false;
	}
	echo '<table class="zzvariables" style="text-align: left;',
		($color ? ' background: '.$color.';' : ''), '">',
		'<caption>', $caption, '</caption>';
	$vars = zz_print_multiarray($array);
	foreach ($vars as $var) {
		echo '<tr><th', // style="padding-left: '
			//.((substr_count($var['key'], '[')-1)*1)
			//.'em;"
			'>', $var['key'], '</th><td>', $var['value'], '</td></tr>', "\n";
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
	if (!is_array($array)) {
		$vars[] = $array;
		return $vars;
	}
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
 * formats an enum field
 *
 * @param array $field
 * @param string $value
 * @param string $type 'full', 'abbr'
 * @param string $key (optional)
 * @return string
 */
function zz_print_enum($field, $value, $type = 'abbr', $key = false) {
	if (!empty($field['enum'])) {
		$ft = 'enum';
	} elseif (!empty($field['set'])) {
		$ft = 'set';
	}
	if (!$key) $key = array_search($value, $field[$ft]);
	// key 0 means first key, so rule out that key was simply not found
	if ($key === '' OR $key === false) return '';

	if (!empty($field[$ft.'_title'][$key])) {
		$text = $field[$ft.'_title'][$key];
	} elseif ($value !== 0) {
		$text = zz_text($value);
	} else {
		$text = $value;
	}
	if (!empty($field[$ft.'_abbr'][$key])) {
		if ($type === 'full') {
			$text .= ' &#8211; '.$field[$ft.'_abbr'][$key];
		} elseif ($type === 'abbr') {
			if (strstr($text, '<abbr')) $text = strip_tags($text);
			$text = '<abbr title="'.htmlspecialchars($field[$ft.'_abbr'][$key])
				.'">'.$text.'</abbr>';
		}
	}
	return $text;
}

/**
 * HTML output of Add-New-Link at the top of the list
 *
 * @param string $extra_get = $zz_var['extraGET']
 * @global array $zz_conf
 * @return string HTML output add new link
 */
function zz_output_add_links($extra_get) {
	global $zz_conf;
	if (!$zz_conf['add_link']) return false;
	if (is_array($zz_conf['add'])) return false;
	if ($zz_conf['access'] == 'export') return false;
	
	$toolsline = array();
	$toolsline[] = '<a accesskey="n" href="'.$zz_conf['int']['url']['self']
		.$zz_conf['int']['url']['qs'].$zz_conf['int']['url']['?&'].'mode=add'
		.$extra_get.'">'.zz_text('Add new record').'</a>';
	if ($zz_conf['import']) {
		$toolsline[] = '<a href="'
			.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
			.$zz_conf['int']['url']['?&'].'mode=import'.$extra_get.'">'
			.zz_text('Import data').'</a>';
	}
	return '<p class="add-new">'.implode(' | ', $toolsline).'</p>'."\n";
}

/**
 * HTML output of a backlink
 *
 * @param array $zz_tab
 * @param array $id = $zz_var['id']
 * @global array $zz_conf
 * @return string HTML output Back to overview
 */
function zz_output_backlink($zz_tab, $id) {
	global $zz_conf;
	if (!$zz_conf['backlink']) return false;
	if (!empty($zz_conf['dynamic_referer'])) {
		if (empty($zz_tab[0][0]['id'])) $zz_tab[0][0]['id'] = $id;
		return '<p id="back-overview"><a href="'
			.zz_makepath($zz_conf['dynamic_referer'], $zz_tab, 'new', 'local')
			.'">'.zz_text('back-to-overview').'</a></p>'."\n";
	} elseif ($zz_conf['referer'])
		return '<p id="back-overview"><a href="'.$zz_conf['int']['referer_esc'].'">'
			.zz_text('back-to-overview').'</a></p>'."\n";
	return false;
}

/**
 * Formats names of tables, first letter uppercase, replaces _ against /
 *
 * @param string $table name of table
 * @return string formatted table name
 */
function zz_nice_tablenames($table) {
	global $zz_conf;
	// get it from config
	if (!empty($zz_conf['nice_tablename'][$table])) {
		$table = $zz_conf['nice_tablename'][$table];
		return $table;
	}
	// or format it here
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
 * @param string $mode ($ops['mode'], if = 'add', keeps add-parameter in URL)
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
	if ($zz_conf['int']['this_limit'] && $zz_conf['int']['this_limit'] != $zz_conf['limit'])
		$keep_query['limit'] = $zz_conf['int']['this_limit'];

	$extra_get = http_build_query($keep_query);
	if ($extra_get) 
		$extra_get = '&amp;'.str_replace('&', '&amp;', $extra_get);
	return $extra_get;
}

/**
 * initializes 'limit' for display of records
 *
 * @param array $zz (might be empty)
 * @global array $zz_conf
 * @return void
 */
function zz_init_limit($zz) {
	global $zz_conf;
	// set default limit in case 'hierarchy' is used because hierarchies need more memory
	if (!$zz_conf['limit'] AND !empty($zz['list']['hierarchy']))
		$zz_conf['limit'] = 40;

	// get LIMIT from URI
	if (!$zz_conf['int']['this_limit'] && $zz_conf['limit']) 
		$zz_conf['int']['this_limit'] = $zz_conf['limit'];
	if (isset($_GET['limit']) && is_numeric($_GET['limit']))	
		$zz_conf['int']['this_limit'] = (int) $_GET['limit'];
	if ($zz_conf['int']['this_limit'] AND $zz_conf['int']['this_limit'] < $zz_conf['limit'])
		$zz_conf['int']['this_limit'] = $zz_conf['limit'];
}	

/**
 * creates link target for 'referer'
 *
 * @global array $zz_conf
 * @return void
 */
function zz_init_referer() {
	global $zz_conf;
	// get referer // @todo: add support for SESSIONs as well
	if (!isset($zz_conf['referer'])) {
		$zz_conf['referer'] = false;
		if (isset($_GET['referer'])) $zz_conf['referer'] = $_GET['referer'];
		if (isset($_POST['zz_referer'])) $zz_conf['referer'] = $_POST['zz_referer'];
	} elseif (isset($_POST['zz_referer']))
		$zz_conf['referer'] = $_POST['zz_referer'];
	elseif (isset($_SERVER['HTTP_REFERER']))
		$zz_conf['referer'] = $_SERVER['HTTP_REFERER'];
	// remove 'zzaction' from referer if set
	$zz_conf['int']['referer'] = parse_url($zz_conf['referer']);
	if (!empty($zz_conf['int']['referer']['query'])) {
		$zz_conf['int']['referer']['query'] = zz_edit_query_string($zz_conf['int']['referer']['query'], array('zzaction'));
		$zz_conf['int']['referer']['query'] = str_replace('&amp;', '&', $zz_conf['int']['referer']['query']);
	}
	$zz_conf['referer'] = (
		(!empty($zz_conf['int']['referer']['scheme']) ? $zz_conf['int']['referer']['scheme'].'://'
			.$zz_conf['int']['referer']['host'] : '')
		.$zz_conf['int']['referer']['path']
		.(!empty($zz_conf['int']['referer']['query']) ? $zz_conf['int']['referer']['query'] : ''));
	$zz_conf['int']['referer_esc'] = str_replace('&', '&amp;', $zz_conf['referer']);
}

/**
 * formats a string, currently only available: translate text from inside zzform
 *
 * @param string $text
 * @return string
 */
function zz_format($text) {
	if (substr($text, 0, 8) === '%%% text' AND substr($text, -3) === '%%%') {
		$text = trim(substr($text, 8, -3));
		if (substr($text, 0, 1) === '"' AND substr($text, -1) === '"')
			$text = trim(substr($text, 1, -1));
		return zz_text($text);
	}
	return $text;
}

/**
 * converts number into currency
 * 
 * @param int $int amount of money
 * @param string $unit currency unit (optional)
 * @return string formatted combination of amount and unit
 */
function zz_money_format($int, $unit = '') {
	global $zz_conf;
	if (!$int) return false;
	$int = number_format($int, 2, $zz_conf['decimal_point'], $zz_conf['thousands_separator']);
	if (!strstr($int, $zz_conf['decimal_point'])) {
		$int .= $zz_conf['decimal_point'].'00';
	}
	//$int = str_replace (',00', ',&#8211;', $int);
	if ($unit) $int .= ' '.$unit;
	$int = str_replace(' ', '&nbsp;', $int);
	return $int;
}

/**
 * converts given iso date to d.m.Y or returns date as is if incomplete
 * 
 * @param string $date date to be converted, international date or output of this function
 * @param string $language 2-letter-languagecode ISO 639-1 or 3-letter-code ISO 639-2T
 * @return string formatted date
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo cleanup
 */
function zz_date_format($date) {
	global $zz_conf;
	if (!$date) return '';

	// convert ISO 639-1 codes to ISO 639-2T
	if ($zz_conf['language'] == 'de') $language = 'deu';
	elseif ($zz_conf['language'] == 'en') $language = 'eng';
	else $language = '---';

	// international format, ISO 8601
	$date_separator['---'] = '-';
	$months['---'] = array('01' => '01', '02' => '02', '03' => '03', '04' => '04', 
		'05' => '05', '06' => '06', '07' => '07', '08' => '08', '09' => '09', 
		'10' => '10', '11' => '11', '12' => '12');
	$date_order['---'] = array('year', 'month', 'day');

	// german format (deu)
	$date_separator['deu'] = '.';
	$date_order['deu'] = array('day', 'month', 'year');

	// english format (eng)
	$date_separator['eng'] = '&nbsp;';
	$months['eng'] = array('01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', 
		'05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', 
		'10' => 'Oct', '11' => 'Nov', '12' => 'Dec');
	$date_order['eng'] = array('day', 'month', 'year');

	// default values: international format, or use language specific format
	$my_date_separator = !empty($date_separator[$language]) ? $date_separator[$language] : $date_separator['---'];
	$my_months = !empty($months[$language]) ? $months[$language] : $months['---'];
	$my_date_order = !empty($date_order[$language]) ? $date_order[$language] : $date_order['---'];

	if (preg_match("/^([0-9]{4}-[0-9]{2}-[0-9]{2}) [0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/", $date, $match)) {
		// DATETIME YYYY-MM-DD HH:ii:ss
		$date = $match[1]; // ignore time, it's a date function
	} elseif (preg_match("/^([0-9]{4})([0-9]{2})([0-9]{2})[0-2][0-9][0-5][0-9][0-5][0-9]$/", $date, $match)){
		// YYYYMMDD ...
		$date = $match[1].'-'.$match[2].'-'.$match[3]; // ignore time, it's a date function
	} elseif (!preg_match("/^[0-9-]+$/", $date)) {
		return $date; #wenn kein richtiges datum, einfach datum zurueckgeben.
	} elseif (preg_match("/^[0-9]{1,4}$/", $date)) {
		return $date; #wenn nur ein bis vier ziffern, d. h. jahr, einfach jahr zurueckgeben
	}

	$date_parts = explode("-", $date);
	$date = '';
	$date_parts['day'] = (!empty($date_parts[2]) AND $date_parts[2] != '00') ? $date_parts[2] : false;
	$date_parts['month'] = (!empty($date_parts[1]) AND $date_parts[1] != '00'
		AND $date_parts[1] > 0 AND  $date_parts[1] < 13) ? $my_months[$date_parts[1]] : false;
	
	if (substr($date_parts[0], 0, 1) == "0" AND substr($date_parts[0], 0, 2) != "00") {
		$date_parts['year'] = substr($date_parts[0], 1, 4);
	} else {
		$date_parts['year'] = $date_parts[0];
	}
	foreach ($my_date_order as $part) {
		if ($date) $date .= $my_date_separator;
		$date .= $date_parts[$part];
	}
	return $date;
}

/**
 * prints out seconds as hours:minutes
 *
 * @param int $seconds
 * @return string
 */
function zz_hour_format($seconds) {
	$minutes = floor($seconds / 60);
	$seconds = $seconds % 60;
	$hours = floor($minutes / 60);
	$minutes = $minutes % 60;

	$time = sprintf('%01d:%02d', $hours, $minutes);
	if ($seconds) $time .= sprintf(':%02d', $seconds);
	return $time;
}

/**
 * format a value according to number_type
 *
 * @param string $value
 * @param array $field
 *		string 'number_type'
 *		string 'geo_format'
 * @return string
 */
function zz_number_format($value, $field) {
	if (empty($field['number_type'])) return $value;
	if (!$value AND !empty($field['hide_zeros'])) return '';
	
	switch ($field['number_type']) {
	case 'currency':
		$text = zz_money_format($value);
		break;
	case 'latitude':
	case 'longitude':
		if ($value === NULL) return '';
		if (empty($field['geo_format'])) $field['geo_format'] = 'dms';
		$text = zz_geo_coord_out($value, $field['number_type'], $field['geo_format']);
		break;
	case 'bytes':
		$text = zz_byte_format($value);
		break;
	default:
		$text = $value;
	}
	return $text;
}

?>