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
 * @copyright Copyright © 2004-2019 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Create HTML output and page title
 * 
 * @param array $ops
 * @param array $zz
 * @param array $where_condition = $zz_var['where_condition']
 * @return array $ops
 *		string 'heading', string 'title', string 'output', string 'h1',
 *		string 'explanation', string 'selection'
 */
function zz_output_page($ops, $zz, $where_condition) {
	// make nicer headings
	$ops['heading'] = zz_nice_headings($ops['heading'], $zz, $where_condition);
	// provisional title, in case errors occur
	$ops['title'] = strip_tags($ops['heading']);
	if (trim($ops['heading']) AND empty($zz['dont_show_h1']))
		$ops['h1'] = $ops['heading'];
	$ops['explanation'] = zz_format($zz['explanation']);
	$ops['selection'] = zz_nice_selection($zz['fields']);
	$ops['class'] = !empty($zz['class']) ? $zz['class'] : '';
	return $ops;
}

/**
 * Output HTML via template
 * 
 * @param array $ops
 * @return string
 */
function zz_output_full($ops) {
	global $zz_conf;
	if ($ops['mode'] === 'export') return $ops['output'];
	if ($zz_conf['footer_text']) $ops['footer_text'] .= $zz_conf['footer_text'];
	$ops['error_out'] = zz_error_output();

	if ($zz_conf['int']['record']) {
		$ops['wmd_editor'] = zz_output_wmd_editor();
		$ops['upndown_editor'] = zz_output_upndown_editor();
	}
	return wrap_template('zzform', $ops);
}

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
	$heading = zz_text($heading);
	if ($zz_conf['heading_prefix']) {
		$zz_conf['heading_prefix'] = zz_text($zz_conf['heading_prefix']);
		$heading = $zz_conf['heading_prefix'].' '.$heading;
		}
	return $heading;
}

/** 
 * Formats a heading for WHERE-conditions
 *
 * @param string $heading ($ops['heading'])
 * @param array $zz
 *		array 'subtitle', 'fields'[n]'field_name' / 'key_field_name'
 * @param array $where_condition, optional
 * @global array $zz_conf
 * @return string $heading
 */
function zz_nice_headings($heading, $zz, $where_condition = []) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$i = 0;
	$heading_addition = [];
	// depending on WHERE-Condition
	foreach (array_keys($where_condition) as $mywh) {
		$mywh = wrap_db_escape($mywh);
		$wh = explode('.', $mywh);
		if (!isset($wh[1])) $index = 0; // without .
		else $index = 1;
		if (!isset($zz['subtitle'][$wh[$index]])) continue;
		$subheading = $zz['subtitle'][$wh[$index]];
		if (!isset($subheading['var']) AND !isset($subheading['value'])) continue;
		$heading_addition[$i] = [];
		if (isset($subheading['sql']) AND $where_condition[$mywh]) {
			// only if there is a value! (might not be the case if 
			// write_once-fields come into play)
			// create sql query, with $mywh instead of $wh[$index] because first 
			// might be ambiguous
			// extra space before mywh for replacement with key_field_name
			$wh_sql = wrap_edit_sql($subheading['sql'], 'WHERE',
				sprintf(' %s = "%s"', $mywh, wrap_db_escape($where_condition[$mywh]))
			);
			$wh_sql .= ' LIMIT 1';
			//	if key_field_name is set
			foreach ($zz['fields'] as $field) {
				if (!isset($field['field_name'])) continue;
				if ($field['field_name'] !== $wh[$index]) continue;
				if (!isset($field['key_field_name'])) continue;
				$wh_sql = str_replace(' '.$wh[$index].' ', ' '.$field['key_field_name'].' ', $wh_sql);
			}
			// just send a notice if this doesn't work as it's not crucial
			$heading_values = zz_db_fetch($wh_sql, '', '', '', E_USER_NOTICE);
			if ($heading_values) {
				foreach ($subheading['var'] as $myfield)
					$heading_addition[$i][] = $heading_values[$myfield];
			}
		} elseif (isset($subheading['enum'])) {
			$heading_addition[$i][] = zz_htmltag_escape($where_condition[$mywh]);
			// @todo insert corresponding value in enum_title
		} elseif (isset($subheading['value'])) {
			$heading_addition[$i][] = zz_htmltag_escape($where_condition[$mywh]);
		}
		if (empty($subheading['concat'])) $subheading['concat'] = ' ';
		if (!empty($subheading['format'])) {
			foreach ($heading_addition[$i] as $index => $value) {
				if (is_array($subheading['format'])) {
					if (empty($subheading['format'][$index])) continue;
					$heading_addition[$i][$index] = $subheading['format'][$index]($value);
				} else {
					$heading_addition[$i][$index] = $subheading['format']($value);
				}
			}
		}
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
				$append = $sep.'show='.urlencode($where_condition[$mywh]);
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
		$heading .= ': <br>'.implode(' &#8211; ', $heading_addition); 
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
	$act = [];
	foreach ($conf['details'] as $key => $new_action) {
		$output = false;
		if ($conf['details_base']) $new_action_url = $conf['details_base'][$key];
		else $new_action_url = strtolower(forceFilename($new_action));
		$output .= '<a href="'.$new_action_url;
		if (isset($conf['details_url'][$key]) && is_array($conf['details_url'][$key])) {
		// values are different for each key
			foreach ($conf['details_url'][$key] as $part_key => $value) {
				if (substr($part_key, 0, 5) === 'field') {
					if (empty($line)) continue 2;
					$output .= $line[$value];
				} else {
					$output .= $value;
				}
			}
		} elseif (is_array($conf['details_url'])) {
		// all values are the same
			foreach ($conf['details_url'] as $part_key => $value) {
				if (substr($part_key, 0, 5) === 'field') {
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
			.'>'.zz_text($new_action).'</a>';
		if (!empty($conf['details_sql'][$key])) {
			$count = zz_db_fetch($conf['details_sql'][$key].$id, '', 'single value');
			if ($count) $output .= '&nbsp;('.$count.')';
		}
		$act[] = $output;
	}
	$output = implode('&nbsp;&middot; ', $act);
	return $output;
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
		if (substr($zz_conf['redirect'][$result], 0, 1) === '/') {
			$zz_conf['redirect'][$result] = $zz_conf['int']['url']['base']
				.$zz_conf['redirect'][$result];
		}
		wrap_http_status_header(303);
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
			.($zz_conf['int']['url']['qs_zzform'] ? '&' : $zz_conf['int']['url']['?&']);
		$secure = false;
		if (!empty($zz_conf['int']['hash_id'])) {
			// secret key has to be recalculated for insert operations
			// because there did not exist an id value before = hash was different
			$zz_conf['int']['secret_key'] = zz_secret_key($id_value);
			$secure = '&zzhash='.$zz_conf['int']['secret_key'];
		}
		switch ($result) {
		case 'successful_delete':
			if (!empty($zz_conf['redirect_to_referer_zero_records'])
				AND !empty($zz_conf['int']['referer']['path'])) {
				// redirect to referer if there are no records in list
				$id_field_name = $zz_tab[0]['table'].'.'.$zz_tab[0][0]['id']['field_name'];
				if (!zz_sql_count_rows($zz_tab[0]['sql'], $id_field_name)) {
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
				}
			}
			wrap_http_status_header(303);
			if ($nos) $nos = '='.$nos;
			header('Location: '.$self.'delete'.$nos);
			exit;
		case 'successful_insert':
			wrap_http_status_header(303);
			header('Location: '.$self.'insert='.$id_value.$secure);
			exit;
		case 'successful_update':
			wrap_http_status_header(303);
			header('Location: '.$self.'update='.$id_value.$secure);
			exit;
		case 'no_update':
			wrap_http_status_header(303);
			header('Location: '.$self.'noupdate='.$id_value.$secure);
			exit;
		}
	}
	return false;
}

/**
 * Output for HTML title element
 *
 * @param string $heading ($ops['heading'])
 * @param array $fields = $zz['fields']
 * @param array $zz_var
 *		'limit_total_rows', 'id', 'filters'
 * @param string $mode ($ops['mode'])
 * @global array $zz_conf
 *		'int['where_with_unique_id]' and others
 * @return string $title
 */
function zz_nice_title($heading, $fields, $zz_var, $mode = false) {
	global $zz_conf;

	// basic title
	$title = strip_tags($heading);

	// addition: filters
	if (!empty($zz_var['filter_titles'])) {
		$title .= $zz_conf['title_separator'].implode($zz_conf['title_separator'], $zz_var['filter_titles']);
	}
	
	// addition: search
	if ($selection = zz_nice_selection($fields))
		$title .= $zz_conf['title_separator'].$selection;

	// addition: page
	if (!empty($zz_conf['limit']) AND $zz_conf['int']['this_limit'] !== '0') {
		if ($zz_conf['int']['this_limit']) 
			$page = $zz_conf['int']['this_limit'] / $zz_conf['limit'];
		else
			$page = 1;
		// in case someone writes manually limit=85 where conf['limit'] = 20
		// don't add limit to page title
		if (is_int($page) AND $page AND !empty($zz_var['limit_total_rows'])) {
			$max_page = ceil($zz_var['limit_total_rows'] / $zz_conf['limit']);
			if ($max_page.'' !== '1') {
				if ($zz_conf['limit_display'] === 'entries') {
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
	if ($mode === 'list_only') $show_id = false;
	if (!empty($_GET['zzhash'])) $show_id = false;
	if (!empty($zz_conf['int']['where_with_unique_id'])) $show_id = false;
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
			if (!empty($field['field_name']) AND $field['field_name'] === $scope) {
				$fieldname = $field['title'];
				break;
			}
			if (!empty($field['display_field']) AND $field['display_field'] === $scope) {
				$fieldname = $field['title'];
				break;
			}
			if (!empty($field['table_name']) AND $field['table_name'] === $scope) {
				$fieldname = $field['title'];
				break;
			}
		}
		$add_equal_sign = true;
	}
	if (substr($_GET['q'], 0, 1) === '<')
		$selection .= '<strong>&lt;</strong> '.zz_html_escape(substr($_GET['q'], 1));
	elseif (substr($_GET['q'], 0, 1) === '>')
		$selection .= '<strong>&gt;</strong> '.zz_html_escape(substr($_GET['q'], 1));
	else {
		$q = $_GET['q'];
		if (substr($q, 0, 2) === '\\')
			$q = substr($q, 1);
		if ($add_equal_sign)
			$selection .= $fieldname.' <strong>=</strong> ';
		$selection .= '*'.zz_html_escape($q).'*';
	}
	return zz_return($selection);
}

/**
 * Show filters with selection in title
 *
 * @param array $filters = $zz['filter']
 * @param array $filter_params = $zz_var['filters']
 * @return array
 */
function zz_output_filter_title($filters, $filter_params) {
	$titles = [];
	if (!$filters) return $titles;
	if (!$filter_params) return $titles;
	foreach ($filters as $index => $f) {
		if (empty($filter_params[$f['identifier']])) continue;
		if (!empty($f['selection']) AND !empty($f['selection'][$filter_params[$f['identifier']]])) {
			$titles[] = $f['title'].': '.$f['selection'][$filter_params[$f['identifier']]];
		} else {
			$titles[] = $f['title'].': '.zz_htmltag_escape($filter_params[$f['identifier']]);
		}
	}
	return $titles;
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
function zz_querystring_to_hidden($query_string, $unwanted_keys = [], $level = 0) {
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
 * transforms a multidimensional array into an array with key => value 
 * where key includes not only the key but also all parent keys in []
 *
 * @param array array to be printed
 * @param string $parent_key (optional, internal value, hierarchy of parent keys)
 * @return array $vars
 *		'key' => full key, 'value' => html'escaped value
 * @see zz_querystring_to_hidden()
 */
function zz_print_multiarray($array, $parent_key = '') {
	$vars = [];
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
			$vars[] = [
				'key' => $mykey,
				'value' => zz_html_escape($value)
			];
		}
	}
	return $vars;
}

/**
 * formats an enum field
 *
 * @param array $field
 *		enum_abbr, set_abbr, enum_title, set_title, ...
 * @param string $value
 * @param string $type 'full', 'abbr'
 * @param string $key (optional)
 * @return string
 * @todo rename this function
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
			if (stristr($text, '<abbr')) $text = strip_tags($text);
			$text = sprintf(
				'<abbr title="%s">%s</abbr>',
				zz_htmlnoand_escape($field[$ft.'_abbr'][$key]), $text 
			);
		}
	}
	return $text;
}

/**
 * HTML output of Add-New-Link at the top of the list
 *
 * @param array $zz
 * @param string $extra_get = $zz_var['extraGET']
 * @global array $zz_conf
 * @return string HTML output add new link
 */
function zz_output_add_links($zz, $extra_get) {
	global $zz_conf;
	if (!$zz_conf['add_link']) return false;
	if (!empty($zz['add'])) return false;
	if ($zz_conf['int']['access'] === 'export') return false;
	
	$toolsline = [];
	$toolsline[] = '<a accesskey="n" href="'.$zz_conf['int']['url']['self']
		.$zz_conf['int']['url']['qs'].$zz_conf['int']['url']['?&'].'add'
		.$extra_get.'">'.zz_text('Add new record').'</a>';
	return '<p class="add-new">'.implode(' | ', $toolsline).'</p>'."\n";
}

/**
 * HTML output of a backlink
 *
 * @param array $zz_tab
 * @global array $zz_conf
 * @return string HTML output Back to overview
 */
function zz_output_backlink($zz_tab = []) {
	global $zz_conf;
	$link = false;	

	if (!empty($zz_tab)) {
		// backlink below record form, just dynamic_referer
		if (empty($zz_tab[0]['dynamic_referer'])) return '';
		$link = zz_makepath($zz_tab[0]['dynamic_referer'], $zz_tab, 'new', 'local');
		// don't show second referer below list/form
		$zz_conf['referer'] = false;
	} elseif ($zz_conf['referer']) {
		$link = $zz_conf['int']['referer_esc'];
	}
	if (!$link) return false;

	return sprintf(
		'<p id="back-overview"><a href="%s">%s</a></p>'."\n",
		$link, zz_text($zz_conf['referer_text'])
	);
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
		$table = wrap_text($zz_conf['nice_tablename'][$table]);
		return $table;
	}
	// or format it here
	if ($zz_conf['prefix']) { // makes the response look nicer
		if (strtolower(substr($table, 0, strlen($zz_conf['prefix']))) === strtolower($zz_conf['prefix']))
			$table = substr($table, strlen($zz_conf['prefix']));
		else {
			zz_error_log([
				'msg_dev' => 'Table prefix is incorrect somehow: %s',
				'msg_dev_args' => [substr($table, 0, strlen($zz_conf['prefix']))]
			]);
		}
	}
	
	$table = explode('_', $table);
	foreach (array_keys($table) as $id) $table[$id] = ucfirst($table[$id]);
	$table = implode('/', $table);
	return wrap_text($table);
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
	$keep_query = [];
	$keep_fields = [
		'where', 'var', 'order', 'group', 'q', 'scope', 'dir', 'referer',
		'url', 'nolist', 'filter', 'debug', 'zz'
	];
	foreach ($keep_fields AS $key) {
		if (!empty($_GET[$key])) $keep_query[$key] = $_GET[$key];
	}
	// write some query strings differently
	if (isset($_GET['nolist'])) 
		$keep_query['nolist'] = true;
	if ($zz_conf['int']['this_limit'] AND $zz_conf['int']['this_limit'] != $zz_conf['limit'])
		$keep_query['limit'] = $zz_conf['int']['this_limit'];
	elseif (!empty($zz_conf['int']['limit_last']))
		$keep_query['limit'] = 'last';

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
function zz_init_limit($zz = []) {
	global $zz_conf;

	// set default limit in case 'hierarchy' is used because hierarchies need more memory
	if (!$zz_conf['limit'] AND !empty($zz['list']['hierarchy']))
		$zz_conf['limit'] = 40;

	// current range which records are shown
	$zz_conf['int']['this_limit']		= false;
	// get LIMIT from URI
	if ($zz_conf['limit']) 
		$zz_conf['int']['this_limit'] = $zz_conf['limit'];
	if (!empty($_GET['limit']) AND $_GET['limit'] === 'last') {
		$zz_conf['int']['limit_last'] = true;
	} else {
		$limit = zz_check_get_array('limit', 'is_int');
		if ($limit !== '') $zz_conf['int']['this_limit'] = $limit;
	}
	if ($zz_conf['int']['this_limit'] AND $zz_conf['int']['this_limit'] < $zz_conf['limit'])
		$zz_conf['int']['this_limit'] = $zz_conf['limit'];
}	

/**
 * checks string length, cuts string if too long
 *
 * @param string $string
 * @param int $max_length maximum length of string that is allowed
 * @return string
 */
function zz_cut_length($string, $max_length) {
	if (mb_strlen($string) <= $max_length) return $string;
	// cut long values
	$string = mb_substr($string, 0, $max_length).'...';
	return $string;
}

/**
 * creates link target for 'referer'
 *
 * @global array $zz_conf
 * @return void
 */
function zz_init_referer() {
	global $zz_conf;
	// get referer // @todo add support for SESSIONs as well
	if (!isset($zz_conf['referer'])) {
		$zz_conf['referer'] = false;
		if (isset($_GET['referer'])) $zz_conf['referer'] = $_GET['referer'];
		if (isset($_POST['zz_referer'])) $zz_conf['referer'] = $_POST['zz_referer'];
	} elseif (isset($_POST['zz_referer'])) {
		$zz_conf['referer'] = $_POST['zz_referer'];
	}
	// remove actions from referer if set
	$zz_conf['int']['referer'] = parse_url($zz_conf['referer']);
	if (!empty($zz_conf['int']['referer']['query'])) {
		$removes = ['delete', 'insert', 'update', 'noupdate'];
		$zz_conf['int']['referer']['query'] = zz_edit_query_string($zz_conf['int']['referer']['query'], $removes);
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
 * format a field with a corresponding formatting function
 *
 * @param string $value
 * @param array $field
 * @return string
 */
function zz_field_format($value, $field) {
	$field_type = zz_get_fieldtype($field);
	switch ($field_type) {
		case 'number':
			return zz_number_format($value, $field);
		case 'ipv4':
			return long2ip($value);
		case 'date':
			return zz_date_format($value);
		case 'datetime':
			return zz_datetime_format($value, $field);
		case 'time':
			return zz_time_format($value, $field);
		default:
			return $value;
	}
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
 * @todo cleanup
 */
function zz_date_format($date) {
	global $zz_conf;
	if (!$date) return '';

	// convert ISO 639-1 codes to ISO 639-2T
	if ($zz_conf['language'] === 'de') $language = 'deu';
	elseif ($zz_conf['language'] === 'en') $language = 'eng';
	else $language = '---';

	// international format, ISO 8601
	$date_separator['---'] = '-';
	$months['---'] = [
		'01' => '01', '02' => '02', '03' => '03', '04' => '04', '05' => '05',
		'06' => '06', '07' => '07', '08' => '08', '09' => '09', '10' => '10',
		'11' => '11', '12' => '12'
	];
	$date_order['---'] = ['year', 'month', 'day'];

	// german format (deu)
	$date_separator['deu'] = '.';
	$date_order['deu'] = ['day', 'month', 'year'];

	// english format (eng)
	$date_separator['eng'] = '&nbsp;';
	$months['eng'] = [
		'01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
		'05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', 
		'10' => 'Oct', '11' => 'Nov', '12' => 'Dec'
	];
	$date_order['eng'] = ['day', 'month', 'year'];

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
	$date_parts['day'] = (!empty($date_parts[2]) AND $date_parts[2] !== '00') ? $date_parts[2] : false;
	$date_parts['month'] = (!empty($date_parts[1]) AND $date_parts[1] !== '00'
		AND $date_parts[1] > 0 AND  $date_parts[1] < 13) ? $my_months[$date_parts[1]] : false;
	
	if (substr($date_parts[0], 0, 1) === "0" AND substr($date_parts[0], 0, 2) !== "00") {
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
 * formats timestamp to readable date
 * 
 * @param string $timestamp
 * @return string reformatted date
 * @todo use date functions instead
 */
function zz_timestamp_format($timestamp) {
	if (!$timestamp) return false;
	if (!strstr($timestamp, '-')) {
		// YYYYMMDDHHiiss format
		$date = substr($timestamp, 0, 4).'-'.substr($timestamp, 4, 2).'-'.substr($timestamp, 6, 2).' '
			.substr($timestamp, 8, 2).':'.substr($timestamp, 10, 2).':'.substr($timestamp, 12, 2);
	} else {
		$date = $timestamp;
	}
	$date = explode(' ', $date);
	$date[0] = zz_date_format($date[0]);
	$date = implode(' ', $date);
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
	global $zz_conf;

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
		$text = wrap_bytes($value);
		break;
	case 'number':
		if ($value === '') return $value;
		if (strstr($value, '.')) {
			$number = explode('.', $value);
			$decimals = strlen($number[1]);
		} else {
			$decimals = 0;
		}
		$text = number_format($value, $decimals, $zz_conf['decimal_point'], $zz_conf['thousands_separator']);
		$text = str_replace(' ', '&nbsp;', $text);
		break;
	default:
		$text = $value;
		break;
	}
	return $text;
}

/**
 * format a time according to time_format
 *
 * @param string $value
 * @param array $field
 * @return string
 */
function zz_time_format($value, $field) {
	if (!$value) return $value;
	$value = date($field['time_format'], strtotime($value));
	return $value;
}

/**
 * format a datetime according to time_format
 *
 * @param string $value
 * @param array $field
 * @return string
 */
function zz_datetime_format($value, $field) {
	if (!$value) return $value;
	if (!strstr($value, ' ')) return $value;
	if (array_key_exists('check_validation', $field) AND !$field['check_validation']) return $value;
	$text = explode(' ', $value);
	$text = zz_date_format($text[0]).' '.zz_time_format($text[1], $field);
	return $text;
}

/**
 * format a list of query string parameters
 *
 * @param string $value
 * @return string
 */
function zz_parameter_format($value) {
	if (!$value) return $value;
	parse_str($value, $parameters);
	if ($parameters) {
		$text = zz_parameter_format_recursive($parameters);
		$text = implode('', $text);
	} else {
		$text = $value;
	}
	$text = sprintf('<code><ul>%s</ul></code>', $text);
	return $text;
}

function zz_parameter_format_recursive($parameters, $prefix = '') {
	$text = [];
	foreach ($parameters as $key => $parameter) {
		if ($prefix) $key = sprintf('%s[%s]', $prefix, $key);
		if (is_array($parameter)) {
			$text = array_merge($text, zz_parameter_format_recursive($parameter, $key));
		} else {
			$text[] = sprintf('<li><em>%s:</em> %s</li>', $key, $parameter);
		}
	}
	return $text;
}

/**
 * Output WMD Editor
 *
 * @global array $zz_conf
 * @return string HTML code for JavaScript
 */
function zz_output_wmd_editor() {
	global $zz_conf;
	
	if (empty($zz_conf['wmd_editor'])) return '';
	if ($zz_conf['wmd_editor'] === true) return '';
	
	$options = [];
	if (!empty($zz_conf['wmd_editor_languages'])) {
		if (in_array($zz_conf['language'], $zz_conf['wmd_editor_languages'])) {
			$options[] = 'strings: Markdown.local.'.$zz_conf['language'];
		}
	}
	if ($options) $options = ', { '.implode(', ', $options).' }';
	else $options = '';

	$output = '<script type="text/javascript">'."\n";
	$output .= '(function () {'."\n";
	for ($i = 1; $i < $zz_conf['wmd_editor']; $i++) {
		$output .= 'var converter'.$i.' = new Markdown.Converter();'."\n";
		$output .= 'var editor'.$i.' = new Markdown.Editor(converter'.$i.', "-'.$i.'"'.$options.');'."\n";
		$output .= 'editor'.$i.'.run()'."\n";
	}
	$output .= '})();'."\n";
	$output .= '</script>'."\n";
	return $output;	
}

/**
 * Output upndown with TinyMCE editor
 *
 * @global array $zz_conf
 * @return string HTML code for JavaScript
 */
function zz_output_upndown_editor() {
	global $zz_conf;
	global $zz_setting;

	if (empty($zz_conf['upndown_editor'])) return '';
	if ($zz_conf['upndown_editor'] === true) return '';

	for ($i = 1; $i <= $zz_conf['upndown_editor']; $i++) {
		$data[$i]['no'] = $i;
	}

	$output = wrap_template('upndown-editor', $data);
	return $output;
}

/**
 * Create links to edit, show, delete or copy a record
 *
 * @param int $id ID of this record
 * @param array $zz_var ('extraGET')
 * @param array $zz_conf_record
 *		'edit', 'view', 'copy', 'delete'
 * @global array $zz_conf
 * @return string
 */
function zz_output_modes($id, $zz_var, $zz_conf_record, $cancelurl = '') {
	global $zz_conf;
	
	if (!empty($zz_conf['int']['where_with_unique_id'])) $id = '';
	$qs = ($id ? sprintf('=%d', $id) : '').$zz_var['extraGET'];
	$qs_extra = $zz_conf['int']['url']['?&'].substr($zz_var['extraGET'], 5);
	$link = sprintf(
		'<a href="%s%s%%s%%s%%s">%%s</a>',
		$zz_conf['int']['url']['self'],
		$zz_conf['int']['url']['qs']
	);

	$modes = [];
	if (empty($zz_conf_record['no_ok']) AND $cancelurl)
		$modes[] = '<a href="'.$cancelurl.'">'.zz_text('OK').'</a>';
	if ($zz_conf_record['edit']) {
		if ($zz_conf['int']['access'] === 'show_after_edit') {
			$modes[] = sprintf($link, '', '', $qs_extra, zz_text('edit'));
		} else {
			$modes[] = sprintf($link, $zz_conf['int']['url']['?&'], 'edit', $qs, zz_text('edit'));
		}
	} elseif ($zz_conf_record['view']) {
		$modes[] = sprintf($link, $zz_conf['int']['url']['?&'], 'show', $qs, zz_text('show'));
	}
	if ($zz_conf_record['copy']) {
		$modes[] = sprintf($link, $zz_conf['int']['url']['?&'], 'add', $qs, zz_text('Copy'));
	}
	if ($zz_conf_record['delete']) {
		$modes[] = sprintf($link, $zz_conf['int']['url']['?&'], 'delete', $qs, zz_text('delete'));
	}
	if ($modes) return implode('&nbsp;&middot; ', $modes);
	else return false;
}
