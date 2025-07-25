<?php

/**
 * zzform
 * Output functions
 * will only be included if $zz_conf['generate_output'] = true
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Create HTML output and page title
 * 
 * @param array $ops
 * @param array $zz
 * @return array $ops
 *		string 'heading', string 'title', string 'output', string 'h1',
 *		string 'explanation', string 'selection'
 */
function zz_output_page($ops, $zz) {
	static $calls = 0;
	if ($calls) return $ops;
	$calls++;

	$ops['breadcrumb'] = $ops['heading'];
	// make nicer headings
	$ops['heading'] = zz_output_heading_nice($ops['heading'], $zz);
	// title, in case errors occur and for export
	$ops['title'] = $ops['heading'];
	// add spaces after line breaks
	if (strstr($ops['title'], '<br>'))
		$ops['title'] = str_replace('<br>', ' ', $ops['title']);
	$ops['title'] = strip_tags($ops['title']);
	$ops['title'] = str_replace('  ', ' ', $ops['title']);
	if (trim($ops['heading']) AND !$zz['dont_show_h1'])
		$ops['h1'] = $ops['heading'];
	$ops['explanation'] = $zz['explanation'];
	$ops['explanation_insert'] = $zz['explanation_insert'] ?? NULL;
	$ops['selection'] = zz_nice_selection($zz['fields']);
	$ops['class'] = $zz['class'] ?? '';
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
	if (!empty($ops['footer']['template']))
		$ops['footer_text'] .= wrap_template($ops['footer']['template'], $ops['record'] ?? []);
	elseif (!empty($ops['footer']['text_insert']) AND !empty($_GET['insert']))
		$ops['footer_text'] .= $ops['footer']['text_insert'];
	elseif (!empty($ops['footer']['text']))
		$ops['footer_text'] .= $ops['footer']['text'];
	$ops['error_out'] = zz_error_output();
	if (isset($ops['explanation_insert']) AND !empty($_GET['insert'])) {
		$ops['explanation'] = zz_format($ops['explanation_insert']);
	} else {
		$ops['explanation'] = zz_format($ops['explanation']);
	}

	if ($zz_conf['int']['record']) {
		$ops['upndown_editor'] = zz_output_upndown_editor();
	} else {
		if (isset($_GET['delete']))
			// just show heading that record was deleted
			$ops['record_deleted'] = true;
	}
	return wrap_template('zzform', $ops);
}

/**
 * Gives information which meta tags should be added to HTML head
 *
 * @return array
 */
function zz_output_meta_tags() {
	$meta = [];
	$noindex = false;
	$querystrings = [
		'order', 'group', 'mode', 'q', 'edit', 'add', 'delete', 'show',
		'insert', 'update', 'revise'
	];
	foreach ($querystrings as $string) {
		if (empty($_GET[$string])) continue;
		$noindex = true;
		break;
	}
	if ($noindex) {
		$meta[] = ['name' => 'robots', 'content' => 'noindex, follow'];
	}
	return $meta;
}

/**
 * format a provisional heading if errors occur
 *
 * @param string $heading ($ops['heading'], from $zz['title'])
 * @param string $table table name as set in $zz['table']
 * @return string $heading
 */
function zz_output_heading($heading, $table = '') {
	if (!isset($heading)) {
		$heading = $table;
		$heading = str_replace('_', ' ', $heading);
		$heading = ucfirst($heading);
	}
	$heading = wrap_text($heading, ['source' => wrap_setting('zzform_script_path')]);
	if (wrap_setting('zzform_heading_prefix')) {
		wrap_setting('zzform_heading_prefix', wrap_text(wrap_setting('zzform_heading_prefix'), ['ignore_missing_translation' => true]));
		$heading = wrap_setting('zzform_heading_prefix').' '.$heading;
		}
	return $heading;
}

/** 
 * Formats a heading for WHERE-conditions
 *
 * @param string $heading ($ops['heading'])
 * @param array $zz
 *		array 'subtitle', 'fields'[n]'field_name' / 'key_field_name'
 * @return string $heading
 */
function zz_output_heading_nice($heading, $zz) {
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	$i = 0;
	$heading_addition = [];
	// depending on WHERE-Condition
	
	$zz['fields'] = zz_fill_out($zz['fields'], $zz['table']);
	foreach ($zz['where_condition'] as $where_condition) {
		foreach (array_keys($where_condition) as $field_name) {
			$field_name = wrap_db_escape($field_name);
			$wh = explode('.', $field_name);
			if (!isset($wh[1])) $index = 0; // without .
			else $index = 1;
			if (!isset($zz['subtitle'][$wh[$index]])) continue;
			$subheading = $zz['subtitle'][$wh[$index]];
			if (!isset($subheading['var']) AND !isset($subheading['value'])) continue;
			$heading_addition[$i] = [];
			if (isset($subheading['sql']) AND $where_condition[$field_name]) {
				// only if there is a value! (might not be the case if 
				// write_once-fields come into play)
				// create sql query, with $field_name instead of $wh[$index] because first 
				// might be ambiguous
				// extra space before mywh for replacement with key_field_name
				//check if key_field_name is set
				$key_field_name = $field_name;
				foreach ($zz['fields'] as $field) {
					if (!isset($field['field_name'])) continue;
					if ($field['field_name'] !== $wh[$index]) continue;
					if (!isset($field['key_field_name'])) continue;
					$key_field_name = $field['key_field_name'];
				}
				$wh_sql = wrap_edit_sql($subheading['sql'], 'WHERE',
					sprintf(' %s = "%s"', $key_field_name, wrap_db_escape($where_condition[$field_name]))
				);
				$wh_sql .= ' LIMIT 1';
				// just send a notice if this doesn't work as it's not crucial
				$heading_values = zz_db_fetch($wh_sql, '', '', '', E_USER_NOTICE);
				if ($heading_values) {
					$tables = wrap_edit_sql($wh_sql, 'FROM', '', 'list');
					if (!empty($tables[0]))
						$heading_values = wrap_translate($heading_values, $tables[0]);
					foreach ($subheading['var'] as $myfield)
						$heading_addition[$i][] = $heading_values[$myfield];
				}
			} elseif (isset($subheading['enum'])) {
				$heading_addition[$i][] = zz_htmltag_escape($where_condition[$field_name]);
				// @todo insert corresponding value in enum_title
			} elseif (isset($subheading['value'])) {
				$heading_addition[$i][] = zz_htmltag_escape($where_condition[$field_name]);
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
					if (!$heading_addition[$i][$index])
						unset($heading_addition[$i][$index]);
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
					$append = $sep.'show='.urlencode($where_condition[$field_name]);
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
	}
	if (array_key_exists('text', $zz['subtitle']))
		$heading_addition[] = $zz['subtitle']['text'];
	if ($heading_addition)
		$heading .= ': <br>'.implode(' – ', $heading_addition);
	return zz_return($heading);
}

/**
 * HTML output of detail-links for list view
 *
 * @param array $conf = $zz_conf_record, using ['details]
 * 	- string 'title'
 * 	- mixed 'link' (optional)
 *		- missing: link will be created from title
 *		- string: main ID field is added to link
 *		- array: 'field', 'string' as in 'path' construction
 * 	- string 'target'
 * 	- bool 'referer'
 *  - string 'sql'
 * @param int $id
 * @param array $line
 * @return array
 */
function zz_output_details($conf, $id, $line) {
	global $zz_conf;

	$act = [];
	foreach ($conf['details'] as $key => &$detail) {
		if (!is_array($detail))
			$detail = ['title' => $detail];
		if (empty($detail['link'])) {
			$detail['link'] = [
				'string' => sprintf('%s?where[%s]=', strtolower(wrap_filename($detail['title'])), $zz_conf['int']['id']['field_name']),
				'field' => $zz_conf['int']['id']['field_name']
			];
		} elseif (!is_array($detail['link'])) {
			$detail['link'] = [
				'string' => $detail['link'],
				'field' => $zz_conf['int']['id']['field_name']
			];
		}
		$detail['url'] = zz_makelink($detail['link'], $line);
		if (!empty($detail['sql']))
			$detail['count'] = zz_db_fetch(sprintf($detail['sql'], $id), '', 'single value');
	}
	return $conf['details'];
}

/**
 * Redirect to a different URL after successful action
 *
 * @param array $ops
 * @param array $zz
 * @param array $zz_tab
 * @global array $zz_conf
 * @return mixed bool false if nothing was done or string redirect URL
 */
function zz_output_redirect($ops, $zz, $zz_tab) {
	global $zz_conf;

	if (!empty($zz['record']['redirect'][$ops['result']])) {
		$redirect = $zz['record']['redirect'][$ops['result']];
		if (wrap_setting('debug'))
			zz_debug('_time', $ops['return']);
		if (is_array($redirect))
			$redirect = zz_makepath($redirect, $zz_tab);
		if (substr($redirect, 0, 1) === '/')
			$redirect = wrap_setting('host_base').$redirect;
		wrap_redirect_change($redirect);
	}

	// debug mode? no redirect
	if (wrap_setting('debug')) return false;

	// redirect to same URL, as to protect against reloading the POST variables
	// don't do so in case of debugging
	// multiple edit?
	$nos = '';
	$id_value = $zz_conf['int']['id']['value'];
	if (is_array($id_value)) {
		$nos = '-'.count($id_value);
		$id_value = implode(',', $id_value);
	}
	// it’s a URL, so replace &amp; with & via substr()
	// on delete, remove nolist, we don’t want to end with empty list
	if ($ops['result'] === 'successful_delete')
		zzform_url_remove(['nolist']);
	$keys = [];
	if (!empty($zz_conf['int']['hash_id'])) {
		// secret key has to be recalculated for insert operations
		// because there did not exist an id value before = hash was different
		$zz_conf['int']['secret_key'] = zz_secret_key($id_value);
		$keys['zzhash'] = $zz_conf['int']['secret_key'];
	}
	switch ($ops['result']) {
	case 'successful_delete':
		// always redirect to referer if in revision mode
		if (!empty($_POST['zz_revision_id']) AND isset($_GET['nolist']))
			$zz['record']['redirect_to_referer_zero_records'] = true;
		$self = NULL;
		if (!empty($zz['record']['redirect_to_referer_zero_records'])
			AND wrap_static('page', 'referer')) {
			// redirect to referer if there are no records in list
			$id_field_name = $zz_tab[0]['table'].'.'.$zz_conf['int']['id']['field_name'];
			if (isset($_GET['nolist']) OR !zz_sql_count_rows($zz_tab[0]['sql'], $id_field_name))
				$self = wrap_static('page', 'referer');
		}
		if ($nos) {
			$_GET['delete'] = '='.$nos; // for JS fragment
		} else {
			$_GET['delete'] = false;  // for JS fragment
		}
		if ($nos) $keys['delete'] = $nos;
		else $keys['delete'] = NULL;
		return zzform_url_add($keys, $self ?? zzform_url());
	case 'successful_insert':
		$_GET['insert'] = $id_value;  // for JS fragment
		$keys['insert'] = $id_value;
		return zzform_url_add($keys);
	case 'successful_update':
		$_GET['update'] = $id_value;  // for JS fragment
		$keys['update'] = $id_value;
		return zzform_url_add($keys);
	case 'no_update':
		$_GET['noupdate'] = $id_value;  // for JS fragment
		$keys['noupdate'] = $id_value;
		return zzform_url_add($keys);
	}
	return false;
}

/**
 * Output for HTML title element
 *
 * @param string $heading ($ops['heading'])
 * @param array $fields = $zz['fields']
 * @param array $ops
 *		'records_total', 'filter_titles'
 * @param string $mode ($ops['mode'])
 * @global array $zz_conf
 *		'int['where_with_unique_id]' and others
 * @return string $title
 */
function zz_nice_title($heading, $fields, $ops, $mode = false) {
	global $zz_conf;

	// basic title
	$title = str_replace(': <br>', ': ', $heading);
	$title = str_replace('<br>', ': ', $title);
	$title = strip_tags($title);

	// addition: filters
	if (!empty($ops['filter_titles'])) {
		$title .= wrap_setting('zzform_title_separator').implode(wrap_setting('zzform_title_separator'), $ops['filter_titles']);
	}
	
	// addition: search
	if ($selection = zz_nice_selection($fields))
		$title .= wrap_setting('zzform_title_separator').$selection;

	// addition: page
	if (wrap_setting('zzform_limit') AND wrap_page_limit() !== 0) {
		$page = wrap_page_limit('page');
		// in case someone writes manually limit=85 where `zzform_limit` = 20
		// don't add limit to page title
		if (is_int($page) AND $page AND !empty($ops['records_total'])) {
			$max_page = ceil($ops['records_total'] / wrap_setting('zzform_limit'));
			if ($max_page.'' !== '1') {
				if (wrap_setting('zzform_limit_display') === 'entries') {
					$title .= wrap_setting('zzform_title_separator').wrap_text('records').' '
						.(($page-1) * wrap_setting('zzform_limit')).'-'
						.($page * wrap_setting('zzform_limit') > $ops['records_total']
							? $ops['records_total'] : $page * wrap_setting('zzform_limit'))
						.'/'.$ops['records_total'];
				} else {
					$title .= wrap_setting('zzform_title_separator').wrap_text('page').' '.$page.'/'.$max_page;
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
		$title .= wrap_setting('zzform_title_separator').wrap_text(ucfirst($mode))
			.($zz_conf['int']['id']['value'] ? ': ID '.$zz_conf['int']['id']['value'] : '');
	}

	$title = html_entity_decode($title);
	$title = strip_tags($title);
	return $title;
}

/** 
 * Formats 'selection' for search results
 *
 * @param array $zz_fields
 * @return string $selection
 */
function zz_nice_selection($zz_fields) {
	if (empty($_GET['q'])) return false;
	if (is_array($_GET['q'])) return false;

	// Display search filter
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	$fieldname = false;
	$selection = wrap_text('Search').': ';
	$add_equal_sign = false;
	if (!empty($_GET['scope'])) {
		$scope = $_GET['scope'];
		if (strstr($scope, '.')) 
			$scope = substr($scope, strrpos($scope, '.') + 1);
		foreach ($zz_fields as $field) {
			if (!empty($field['field_name']) AND $field['field_name'] === $scope) {
				$fieldname = zz_field_title($field);
				break;
			}
			if (!empty($field['display_field']) AND $field['display_field'] === $scope) {
				$fieldname = zz_field_title($field);
				break;
			}
			if (!empty($field['table_name']) AND $field['table_name'] === $scope) {
				$fieldname = zz_field_title($field);
				break;
			}
		}
		$add_equal_sign = true;
	}
	if (substr($_GET['q'], 0, 1) === '<')
		$selection .= '<strong>&lt;</strong> '.wrap_html_escape(substr($_GET['q'], 1));
	elseif (substr($_GET['q'], 0, 1) === '>')
		$selection .= '<strong>&gt;</strong> '.wrap_html_escape(substr($_GET['q'], 1));
	else {
		$q = $_GET['q'];
		if (substr($q, 0, 2) === '\\')
			$q = substr($q, 1);
		if ($add_equal_sign)
			$selection .= $fieldname.' <strong>=</strong> ';
		$selection .= '*'.wrap_html_escape($q).'*';
	}
	return zz_return($selection);
}

/**
 * Show filters with selection in title
 *
 * @param array $filters = $zz['filter']
 * @param array $filter_active = $zz['filter_active']
 * @return array
 */
function zz_output_filter_title($filters, $filter_active) {
	$titles = [];
	if (!$filters) return $titles;
	if (!$filter_active) return $titles;
	foreach ($filters as $index => $f) {
		if (empty($filter_active[$f['identifier']])) continue;
		if (!empty($f['selection']) AND !empty($f['selection'][$filter_active[$f['identifier']]])) {
			$titles[] = $f['title'].': '.$f['selection'][$filter_active[$f['identifier']]];
		} else {
			$titles[] = $f['title'].': '.zz_htmltag_escape($filter_active[$f['identifier']]);
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
				'value' => wrap_html_escape($value)
			];
		}
	}
	return $vars;
}

/**
 * formats an enum field
 *
 * @param array $field
 *		enum_abbr, set_abbr, enum_title, set_title, …
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
		$text = wrap_text($value, ['source' => wrap_setting('zzform_script_path')]);
	} else {
		$text = $value;
	}
	if (!empty($field[$ft.'_abbr'][$key])) {
		if ($type === 'full') {
			$text .= ' – '.$field[$ft.'_abbr'][$key];
		} elseif ($type === 'abbr') {
			if (stristr($text, '<abbr')) $text = strip_tags($text);
			$text = sprintf(
				'<abbr title="%s">%s</abbr>',
				zz_htmltag_escape($field[$ft.'_abbr'][$key], ENT_QUOTES), $text 
			);
		}
	}
	return $text;
}

/**
 * HTML output of Add-New-Link at the bottom of the list
 *
 * @param array $zz
 * @param array $ops
 * @param string $posititon ('above', 'below' list)
 * @global array $zz_conf
 * @return string
 */
function zz_output_add_export_links($zz, $ops, $position = 'below') {
	global $zz_conf;
	static $add_links_shown = false;
	if ($ops['mode'] === 'export') return '';
	if (function_exists('zz_details_add_link'))
		if (!zz_details_add_link()) return '';

	$links = [];
	switch ($position) {
	case 'below':
		// only if list was shown beforehands
		if (!$ops['records_total']) return '';
		if (!$zz['export']) break;
		$links['export'] = zz_export_links($zz['export']);
		break;
	case 'nolist':
		if ($ops['mode'] AND $ops['mode'] !== 'list_only' AND !wrap_setting('zzform_show_list_while_edit')) return '';
		// only show add links if no links where shown before
		if (isset($_GET['nolist'])) return '';
		if ($add_links_shown) return '';
		break;
	}

	if ($ops['mode'] !== 'add' AND $zz['record']['add'] AND $zz_conf['int']['access'] !== 'add_only') {
		$add_links_shown = true;
		if (empty($zz['add'])) {
			// normal add button
			$links['add_record'] = true;
		} else {
			// multi-add-button
			// if some 'add' was unset before, here we get new numerical keys
			$links['add'] = $zz['add'];
			ksort($links['add']);
		}
	}
	if (!$links) return '';
	return wrap_template('zzform-list-add', $links);
}

/**
 * HTML output of a backlink
 *
 * @return string HTML output Back to overview
 */
function zz_output_backlink() {
	if (!$link = wrap_static('page', 'referer_esc')) return '';
	return sprintf(
		'<p id="back-overview"><a href="%s">%s</a></p>'."\n",
		$link, wrap_text(wrap_setting('zzform_referer_text'))
	);
}

/**
 * Formats names of tables, first letter uppercase, replaces _ against /
 *
 * @param string $table name of table
 * @return string formatted table name
 */
function zz_nice_tablenames($table) {
	// get it from config
	if ($table_name = wrap_setting('zzform_nice_tablename['.$table.']'))
		return wrap_text($table_name, ['source' => wrap_setting('zzform_script_path')]);

	// or format it here
	if (wrap_setting('db_prefix')) { // makes the response look nicer
		if (strtolower(substr($table, 0, strlen(wrap_setting('db_prefix')))) === strtolower(wrap_setting('db_prefix')))
			$table = substr($table, strlen(wrap_setting('db_prefix')));
		else {
			zz_error_log([
				'msg_dev' => 'Table prefix is incorrect somehow: %s',
				'msg_dev_args' => [substr($table, 0, strlen(wrap_setting('db_prefix')))]
			]);
		}
	}
	
	$table = explode('_', $table);
	foreach (array_keys($table) as $id) $table[$id] = ucfirst($table[$id]);
	$table = implode('/', $table);
	return wrap_text($table, ['source' => wrap_setting('zzform_script_path')]);
}

/**
 * initializes 'limit' for display of records
 *
 * @param array $zz (might be empty)
 * @global array $zz_conf
 * @return void
 */
function zz_init_limit($zz) {
	// set default limit in case 'hierarchy' is used because hierarchies need more memory
	if (!wrap_setting('zzform_limit') AND !empty($zz['list']['hierarchy']))
		wrap_setting('zzform_limit', 40);
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
	// remove HTML formatting, multilines, might break when cut
	$string = strip_tags($string);
	$string = str_replace("\n", " ", $string);
	if (mb_strlen($string) <= $max_length) return $string;
	// cut long values
	$string = mb_substr($string, 0, $max_length).'…';
	return $string;
}

/**
 * creates link target for 'referer'
 *
 * @return void
 */
function zz_init_referer() {
	// get referer // @todo add support for SESSIONs as well
	if (is_null(wrap_static('page', 'referer'))) {
		wrap_static('page', 'referer', false);
		if (isset($_GET['referer'])) wrap_static('page', 'referer', $_GET['referer']);
		if (isset($_POST['zz_referer'])) wrap_static('page', 'referer', $_POST['zz_referer']);
	} elseif (isset($_POST['zz_referer'])) {
		wrap_static('page', 'referer', $_POST['zz_referer']);
	}
	// remove actions from referer if set
	$url = parse_url(wrap_static('page', 'referer'));
	if (!empty($url['query'])) {
		$removes = ['delete', 'insert', 'update', 'noupdate'];
		$url['query'] = zzform_url_remove($removes, $url['query'], 'return', '&');
	}
	wrap_static('page', 'referer', (
		(!empty($url['scheme']) ? $url['scheme'].'://'.$url['host'] : '').$url['path'].($url['query'] ?? '')
	));
	if (!wrap_static('page', 'referer')) return;
	wrap_static('page', 'referer_esc', str_replace('&', '&amp;', wrap_static('page', 'referer')));
	wrap_static('page', 'zz_referer', true);
}

/**
 * formats a string, currently only available: translate text from inside zzform
 *
 * @param string $text
 * @return string
 */
function zz_format($text) {
	if (!$text) return $text;
	if (!str_starts_with($text, '%%% text')) return $text;
	if (!str_ends_with($text, '%%%')) return $text;

	$text = trim(substr($text, 8, -3));
	if (substr($text, 0, 1) === '"' AND substr($text, -1) === '"')
		$text = trim(substr($text, 1, -1));
	return wrap_text($text, ['source' => wrap_setting('zzform_script_path')]);
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
	if (!empty($field['preformat'])) {
		if (!function_exists($field['preformat']))
			wrap_error(sprintf('Preformat function %s does not exist.', $field['preformat']), E_USER_ERROR);
		if (!empty($field['preformat_parameters'])) {
			$value = $field['preformat']($value, $field['preformat_parameters']);
		} else {
			$value = $field['preformat']($value);
		}
	}
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
	if (!$int) return false;
	$int = number_format($int, 2, wrap_setting('decimal_point'), wrap_setting('thousands_separator'));
	if (!strstr($int, wrap_setting('decimal_point'))) {
		$int .= wrap_setting('decimal_point').'00';
	}
	//$int = str_replace (',00', ',–', $int);
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
	if (!$date) return '';

	// convert ISO 639-1 codes to ISO 639-2T
	if (wrap_setting('lang') === 'de') $language = 'deu';
	elseif (wrap_setting('lang') === 'en') $language = 'eng';
	else $language = '---';

	// international format, ISO 8601
	$date_separator['---'] = '-';
	$months['---'] = [
		1 => '01', 2 => '02', 3 => '03', 4 => '04', 5 => '05', 6 => '06',
		7 => '07', 8 => '08', 9 => '09', 10 => '10', 11 => '11', 12 => '12'
	];
	$date_order['---'] = ['year', 'month', 'day'];

	// german format (deu)
	$date_separator['deu'] = '.';
	$date_order['deu'] = ['day', 'month', 'year'];

	// english format (eng)
	$date_separator['eng'] = '&nbsp;';
	$months['eng'] = [
		1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
		7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
	];
	$date_order['eng'] = ['day', 'month', 'year'];

	// default values: international format, or use language specific format
	$my_date_separator = $date_separator[$language] ?? $date_separator['---'];
	$my_months = $months[$language] ?? $months['---'];
	$my_date_order = $date_order[$language] ?? $date_order['---'];

	if (preg_match("/^([0-9]{4}-[0-9]{2}-[0-9]{2}) [0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/", $date, $match)) {
		// DATETIME YYYY-MM-DD HH:ii:ss
		$date = $match[1]; // ignore time, it's a date function
	} elseif (preg_match("/^([0-9]{4})([0-9]{2})([0-9]{2})[0-2][0-9][0-5][0-9][0-5][0-9]$/", $date, $match)){
		// YYYYMMDD …
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
		AND $date_parts[1] > 0 AND $date_parts[1] < 13) ? $my_months[intval($date_parts[1])] : false;
	
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
	if (empty($field['number_type'])) return $value;
	if (!$value AND !empty($field['hide_zeros'])) return '';
	
	switch ($field['number_type']) {
	case 'currency':
		$text = zz_money_format($value);
		break;
	case 'latitude':
	case 'longitude':
		if ($value === NULL) return '';
		$text = wrap_coordinate($value, $field['number_type'], $field['geo_format'] ?? 'dms');
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
		$text = number_format($value, $decimals, wrap_setting('decimal_point'), wrap_setting('thousands_separator'));
		$text = str_replace(' ', '&nbsp;', $text);
		break;
	case 'rating':
		$text = zz_rating_format($value, $field);
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
	if (!preg_match('/\d+:\d+:\d+/', $value)) {
		$value = zz_check_time($value);
	}
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
			$text[] = sprintf('<li><em>%s:</em> %s</li>', $key, wrap_html_escape($parameter));
		}
	}
	return $text;
}

/**
 * format a phone number, return a phone link if possible
 *
 * @param string $value
 * @return string
 */
function zz_phone_format($value) {
	if (substr($value, 0, 1) !== '+') return $value;
	$tel = preg_replace('/[^0-9]/', '', $value);
	return sprintf('<a href="tel:+%s">%s</a>', $tel, $value);
}

/**
 * format a username, return a link
 *
 * @param string $value
 * @param array $field
 * @return string
 */
function zz_username_format($value, $field) {
	if (empty($field['url'])) return '@'.$value;
	$url = sprintf($field['url'], $value);
	return sprintf('<a href="%s">@%s</a>', $url, $value);
}

/**
 * format a star rating
 *
 * @param string $value
 * @return string
 */
function zz_rating_format($value, $field) {
	wrap_include('record', 'zzform');
	$record[$field['field_name']] = $value;
	return zz_field_rating($field, 'review', $record);
}

/**
 * Output upndown with TinyMCE editor
 *
 * @return string HTML code for JavaScript
 */
function zz_output_upndown_editor() {
	if (!wrap_setting('zzform_upndown_editor_instances')) return '';

	for ($i = 1; $i <= wrap_setting('zzform_upndown_editor_instances'); $i++)
		$data[$i]['no'] = $i;

	$output = wrap_template('upndown-editor', $data);
	return $output;
}

/**
 * Create links to edit, show, delete or copy a record
 *
 * @param int $id ID of this record
 * @param array $zz_conf_record
 *		'edit', 'view', 'copy', 'delete'
 * @global array $zz_conf
 * @return array
 */
function zz_output_modes($id, $zz_conf_record) {
	global $zz_conf;
	
	if (!empty($zz_conf['int']['where_with_unique_id'])) $id = NULL;
	$link = zzform_url('self+extra');

	$modes = [];
	if ($zz_conf_record['edit'])
		if ($zz_conf['int']['access'] === 'show_after_edit') {
			$modes[] = ['link' => zzform_url_add([], $link), 'edit' => 1];
		} else {
			$modes[] = ['link' => zzform_url_add(['edit' => $id], $link), 'edit' => 1];
		}
	elseif ($zz_conf_record['view'])
		$modes[] = ['link' => zzform_url_add(['show' => $id], $link), 'show' => 1];
	if ($zz_conf_record['copy'])
		$modes[] = ['link' => zzform_url_add(['add' => $id], $link), 'copy' => 1];
	if ($zz_conf_record['delete'])
		$modes[] = ['link' => zzform_url_add(['delete' => $id], $link), 'delete' => 1];
	if ($modes) return $modes;
	else return [];
}
