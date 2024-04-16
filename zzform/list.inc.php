<?php

/**
 * zzform
 * Display all or a subset of all records in a list (e. g. table, ul)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * shows records in list view
 * 
 * displays table with records (limited by zz_conf['limit'])
 * displays add new record, record navigation (if zz_conf['limit'] = true)
 * and search form below table
 * @param array $zz				table and field definition
 * @param array $list
 * @param array $ops			operation variables
 * @param array $zz_conditions	configuration variables
 * @global array $zz_conf		Main conifguration parameters, will be modified
 * @return array				modification of $ops
 */
function zz_list($zz, $list, $ops, $zz_conditions) {
	global $zz_conf;
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	if (wrap_setting('zzform_search'))
		require_once __DIR__.'/searchform.inc.php';

	// Turn off hierarchical sorting when using search
	// @todo: implement hierarchical view even when using search
	if (!empty($_GET['q']) AND wrap_setting('zzform_search'))
		$list['hierarchy']['display_in'] = '';

	// zz_fill_out must be outside if show_list, because it is necessary for
	// search results with no resulting records
	// fill_out, but do not unset conditions
	$zz['fields'] = zz_fill_out($zz['fields'], wrap_setting('db_name').'.'.$zz['table'], 1);

	// only if search is allowed and there is something
	// if q modify $zz['sql']: add search query
	if (!empty($_GET['q']) AND wrap_setting('zzform_search')) {
		$old_sql = $zz['sql'];
		$zz['sql'] = zz_search_sql($zz['fields'], $zz['sql'], $zz['table']);
		if ($old_sql !== $zz['sql']) $zz['sqlcount'] = NULL;
	}

	if ($zz_conf['int']['access'] === 'search_but_no_list' AND empty($_GET['q'])) 
		$list['display'] = false;

	// SQL query without limit and filter for conditions etc.!
	$zz['sql_without_limit'] = $zz['sql'];

	// Filters
	if (zz_modules('filter')) {
		$success = zz_filter_list($zz, $ops, $list);
		if (!$success) return zz_return($ops);
	}

	list($lines, $ops['records_total']) = zz_list_query($zz, $list);
	if (zz_error_exit()) return zz_return($ops);
	$count_rows = count($lines);

	if ($count_rows < 8 AND $list['display'] === 'ul')
		$list['no_add_above'] = true;
	elseif ($count_rows < 4 AND $list['display'] === 'table')
		$list['no_add_above'] = true;
	if (!$list['no_add_above'])
		$ops['output'] .= zz_output_add_export_links($zz, $ops, 'above');
	if ($list['display'])
		$ops['output'] .= $ops['filter_top'] ?? '';

	// don't show anything if there is nothing
	if (!$count_rows) {
		$list['display'] = false;
		if (!$list['hide_empty_table'] AND $text = wrap_text('No entries available')) {
			$ops['output'].= '<p class="emptytable">'.$text.'</p>';
		}
		if ($ops['mode'] === 'export') {
			// return 404 not found page (HTML, no export format)
			// because there is no content
			// @todo: output the same page with links, filters etc.
			// as if no export were selected
			wrap_static('page', 'status', 404);
			$ops['mode'] = false;
			unset($ops['headers']);
			return zz_return($ops);
		} elseif ($ops['records_total']) {
			// 404 if limit is too large
			wrap_static('page', 'status', 404);
		}
	}

	//
	// Table definition, data and head
	//

	// Check all conditions whether they are true;
	if (zz_modules('conditions')) {
		$zz_conditions = zz_conditions_list_check($zz, $list, $zz_conditions, array_keys($lines), $ops['mode']);
		if (zz_error_exit()) return zz_return($ops);
	}

	// add 0 as a dummy record for which no conditions will be set
	// reindex $linex from 1 ... n
	array_unshift($lines, '0');
	list($zz['fields'], $lines) = zz_list_inline($zz['fields'], $lines, $ops['mode']);
	if ($list['display']) {
		list($table_defs, $zz['fields']) = zz_list_defs(
			$lines, $zz_conditions, $zz['fields'], $zz['table'], $ops['mode']
		);
	}
	// remove first dummy array
	unset($lines[0]);
	if (wrap_setting('debug')) zz_debug('list definitions set');

	if ($list['display']) {
		$list = zz_list_set($list, $zz, count($lines));

		if ($ops['mode'] === 'export') {
			// no grouping in export files
			$list['group'] = [];
		}

		// mark fields as 'show_field' corresponding to grouping
		$table_defs = zz_list_show_group_fields($table_defs, $list);

		list($rows, $list) = zz_list_data(
			$list, $lines, $table_defs, $zz, $zz_conditions, $zz['table'], $ops['mode']
		);
		if ($list['extra_cols'])
			$table_defs[0] += $list['extra_cols'];
		unset($lines);

		$head = zz_list_head($table_defs[0], $list['columns']);
		unset($table_defs);
	}

	// merge common $zz settings for all records
	if (zz_modules('conditions') AND !empty($zz_conditions['bool']))
		zz_conditions_merge_conf($zz, $zz_conditions['bool'], 0);

	if ($list['display'])
		list($rows, $head) = zz_list_remove_empty_cols($rows, $head, $zz, $list);

	//
	// Export
	//

	if ($ops['mode'] === 'export') {
		if (!$list['display']) zz_return();

		// add empty column from heads in rows as well (for export)
		foreach ($rows as $row_index => $row) {
			foreach (array_keys($head) as $col_index) {
				if (!isset($row[$col_index])) {
					$rows[$row_index][$col_index] = [
						'value' => '', 'class' => [], 'text' => ''
					];
				}
			}
			ksort($rows[$row_index]);
		}
		$ops['output'] = [
			'head' => $head,
			'rows' => $rows
		];
		$ops = zz_export($ops, $zz, $list);
		return zz_return($ops);
	}
	
	//
	// Table head, table foot, list body, closing list
	//

	zz_error();
	$ops['output'] .= zz_error_output();

	if (wrap_setting('zzform_search')) {
		$search_form = zz_search_form($zz['fields'], $zz['table'], $ops['records_total'], $count_rows);
		$ops['output'] .= $search_form['top'];
	}
	
	if ($list['display']) {
		if ($list['select_multiple_records']) {
			wrap_setting_add('extra_http_headers', 'X-Frame-Options: Deny');
			wrap_setting_add('extra_http_headers', "Content-Security-Policy: frame-ancestors 'self'");
			$action_url = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'];
			if ($zz_conf['int']['extra_get']) {
				// without first &amp;!
				$action_url .= $zz_conf['int']['url']['?&'].$zz_conf['int']['extra_get_escaped'];
			}
			$ops['output'] .= sprintf('<form action="%s" method="POST" accept-charset="%s">'."\n",
				$action_url, wrap_setting('character_set'));
			if ($list['multi_edit'])
				$list['buttons'][] = '<input type="submit" value="'.wrap_text('Edit').'" name="zz_multiple_edit">';
			if ($list['multi_delete'])
				$list['buttons'][] = '<input type="submit" value="'.wrap_text('Delete').'" name="zz_multiple_delete">';
			if ($list['merge'])
				$list['buttons'][] = '<input type="submit" value="'.wrap_text('Merge').'" name="zz_merge">';
			if ($list['multi_function']) foreach ($list['multi_function'] as $index => $mfunction)
				$list['buttons'][] = '<input type="submit" value="'.wrap_text($mfunction['title']).'" name="zz_multifunction['.$index.']">';
			if ($list['buttons']) {
				$list['buttons'] = '<input type="hidden" name="zz_action" value="multiple">'.implode(' ', $list['buttons']);
			}
		}
	
		if ($list['display'] === 'table') {
			$ops['output'] .= zz_list_table($list, $rows, $head);
		} elseif ($list['display'] === 'ul') {
			$ops['output'] .= zz_list_ul($list, $rows);
		}

		if ($list['select_multiple_records'])
			$ops['output'] .= '</form>'."\n";
	}

	//
	// Buttons below table (add, record nav, search)
	//

	// Add new record
	if (!($zz_conf['int']['access'] === 'search_but_no_list' AND empty($_GET['q']))) {
		// filter, if there was a list
		if ($list['display'])
			$ops['output'] .= $ops['filter_bottom'] ?? '';
		$ops['output'] .= zz_output_add_export_links($zz, $ops);
		$ops['output'] .= zz_list_total_records($ops['records_total']);
		$ops['output'] .= zz_list_pages($zz_conf['int']['this_limit'], $ops['records_total']);	
		// @todo: NEXT, PREV Links at the end of the page
		// Search form
		if (wrap_setting('zzform_search')) {
			$ops['output'] .= $search_form['bottom'];
		}
	}
	// explanation might have changed due to conditions
	$ops['explanation'] = $zz['explanation'];
	return zz_return($ops);
}

/**
 * if list_display = 'inline', move fields of subtable one level up to main table
 * only works if subtable has a maximum of 1 detail records
 *
 * @param array $fields
 * @param array $lines
 * @param string $mode
 * @return array $fields
 */
function zz_list_inline($fields, $lines, $mode) {
	// hide subtable first, array_splice renumbers numerical indices
	foreach ($fields as $no => $field) {
		if (empty($field['type'])) continue;
		if (!in_array($field['type'], ['subtable', 'foreign_table'])) continue;
		if (empty($field['list_display'])) continue;
		if ($field['list_display'] !== 'inline') continue;
		$fields[$no]['hide_in_list'] = true; // hide subtable
	}

	$pos = 0;
	foreach ($fields as $no => $field) {
		$pos++; // splice at this position
		if (empty($field['type'])) continue;
		if (!in_array($field['type'], ['subtable', 'foreign_table'])) continue;
		if (empty($field['list_display'])) continue;
		if ($field['list_display'] !== 'inline') continue;

		// 1. move definition of subtable to equal level as main table
		$foreign_key = false;
		foreach ($field['fields'] as $subno => $subfield) {
			if ($subfield['type'] === 'foreign_key')
				$foreign_key = $subfield['key_field_name'] ?? $subfield['field_name'];
			if (in_array($subfield['type'], ['foreign_key', 'timestamp', 'subtable'])) continue;
			if ($mode !== 'export' AND $subfield['type'] === 'id') continue;
			$fn = $subfield['display_field'] ?? $subfield['field_name'];
			$subfield['row_value'] = $field['table_name'].'.'.$fn;
			if (!empty($subfield['list_abbr'])) {
				// for this function
				$field['fields'][$subno]['list_abbr_source'] = $subfield['list_abbr'];
				$field['fields'][$subno]['list_abbr'] = $field['table_name'].'.'.$subfield['list_abbr'];
				// for later
				$subfield['list_abbr'] = $field['table_name'].'.'.$subfield['list_abbr'];
			}
			array_splice($fields, $pos, 0, [$subfield]);
			$pos++;
		}
		
		// 2. get data for subtable, add it to main query
		$foreign_keys = [];
		foreach ($lines as $line)
			if (!empty($line[$foreign_key])) $foreign_keys[] = $line[$foreign_key];
		if (!$foreign_keys) continue; // empty table
		$sql = wrap_edit_sql($field['sql'], 'WHERE', sprintf('%s.%s IN (%s)', $field['table'], $foreign_key, implode(',', $foreign_keys)));
		$additional_data = wrap_db_fetch($sql, $foreign_key);
		foreach ($field['fields'] as $subno => $subfield) {
			if (in_array($subfield['type'], ['foreign_key', 'timestamp', 'option', 'subtable'])) continue;
			if ($mode !== 'export' AND $subfield['type'] === 'id') continue;
			$fn = $subfield['display_field'] ?? $subfield['field_name'];
			$fn_key = $field['table_name'].'.'.$fn;
			$list_abbr = $subfield['list_abbr_source'] ?? '';
			$list_abbr_key = $subfield['list_abbr'] ?? '';
			foreach ($lines as $index => $line) {
				if (empty($line)) continue;
				// @todo remove following 7 lines, table_name prefix should be unique
				if (!empty($lines[$index][$fn_key])) {
					zz_error_log([
						'msg_dev' => 'Identical field name would be overwritten: %s',
						'msg_dev_args' => $fn
					]);
					continue;
				}
				if (!array_key_exists($line[$foreign_key], $additional_data)) {
					// No detail record exists, still initialize field
					$lines[$index][$fn_key] = '';
					if ($list_abbr_key)
						$lines[$index][$list_abbr_key] = '';
				} else {
					$lines[$index][$fn_key] = $additional_data[$line[$foreign_key]][$fn];
					if ($list_abbr_key)
						$lines[$index][$list_abbr_key] = $additional_data[$line[$foreign_key]][$list_abbr];
				}
			}
		}
	}
	return [$fields, $lines];
}

/**
 * set table definitions (applies conditions and fill_out function)
 *
 * check conditions, these might lead to different field definitions for every
 * line in the list output!
 * that means, $table_defs cannot be used for the rest but $line_defs instead
 *
 * @param array $lines
 * @param array $zz
 * @param array $zz_conditions
 * @param array $fields_in_list ($zz['fields'])
 * @param string $table ($zz['table'])
 * @param string $mode ($ops['mode'])
 * @return array
 *		- array $table_defs
 *		- array $fields_in_list
 */
function zz_list_defs($lines, $zz_conditions, $fields_in_list, $table, $mode) {
	global $zz_conf;

	$conditions_applied = false; // check if there are any conditions
	foreach ($lines as $index => $line) {
		$line_defs[$index] = $fields_in_list;
		// conditions
		if (!zz_modules('conditions')) continue;
		if (empty($zz_conditions['bool'])) continue;
		if (!$index) {
			// only apply conditions to list head if condition
			// is valid for all records (true or false/empty array instead of array of ids)
			$my_bool_conditions = [];
			foreach ($zz_conditions['bool'] as $condition => $ids) {
				if ($ids === true) $my_bool_conditions[$condition] = true;
				elseif (!$ids) $my_bool_conditions[$condition] = false;
			}
			if (!$my_bool_conditions) continue;
		} else {
			$my_bool_conditions = $zz_conditions['bool'];
		}
		foreach (array_keys($line_defs[$index]) as $fieldindex) {
			if (!isset($line[$zz_conf['int']['id']['field_name']])) {
				if ($index !== 0) continue;
				// header
				$applied = zz_conditions_merge_field(
					$line_defs[$index][$fieldindex], $my_bool_conditions, 0
				);
			} else {
				$applied = zz_conditions_merge_field(
					$line_defs[$index][$fieldindex], $my_bool_conditions, $line[$zz_conf['int']['id']['field_name']]
				);
			}
			if ($applied) $conditions_applied = true;
		}
	}
	if (!$conditions_applied) {
		// if there is no condition, remove all the identical stuff
		unset($line_defs);
		$line_defs[0] = $fields_in_list;	
	}
	// table definition is complete
	// so now we need to check which fields are shown in list mode
	// old to new: get a continuous order which we need later on for
	// list_append etc.
	$old_to_new_index = [];
	foreach (array_keys($lines) as $index) {
		if (empty($line_defs[$index])) continue;
		if (wrap_setting('debug')) zz_debug('fill_out start');
		$line_defs[$index] = zz_fill_out($line_defs[$index], wrap_setting('db_name').'.'.$table, 2);
		if (wrap_setting('debug')) zz_debug('fill_out end');
		foreach ($line_defs[$index] as $fieldindex => $field) {
			if (in_array($fieldindex, array_keys($old_to_new_index))) {
				$fi = $old_to_new_index[$fieldindex];
			} else {
				$fi = count($old_to_new_index);
				$old_to_new_index[$fieldindex] = $fi;
			}
			// remove elements from table which shall not be shown
			if ($mode === 'export') {
				if (!isset($field['export']) || $field['export']) {
					$table_defs[$index][$fi] = $field;
				}
			} else {
				if (empty($field['hide_in_list'])) {
					$table_defs[$index][$fi] = $field;
				}
			}
		}
		if (wrap_setting('debug')) zz_debug('table_query end');
	}
	unset($old_to_new_index);
	// now we have the basic stuff in $table_defs[0] and $line_defs[0]
	// if there are conditions, $table_defs[1], [2], [3]... and
	// $line_defs[1], [2], [3] ... are set
	$fields_in_list = $line_defs[0]; // for search form
	unset($line_defs);
	return [$table_defs, $fields_in_list];
}

/**
 * set default values for $list with some data
 *
 * @param array $list
 * @param array $zz
 * @param int $count_rows (number of records)
 * @return array
 */
function zz_list_set($list, $zz, $count_rows) {
	global $zz_conf;

	if ($list['multi_edit'] OR $list['multi_delete'] OR $list['merge'] OR $list['multi_function']) {
		if ($count_rows > 1)
			$list['select_multiple_records'] = true;
	}

	// check 'group'
	$group_from_get = false;
	if (!empty($_GET['group'])) {
		foreach ($zz['fields'] as $field) {
			if ((isset($field['display_field']) && $field['display_field'] === $_GET['group'])
				OR (isset($field['field_name']) && $field['field_name'] === $_GET['group'])
			) {
				if (isset($field['order'])) $group_from_get = $field['order'];
				else $group_from_get = $_GET['group'];
			}
		}
	}
	
	// @deprecated codeblock
	if ($list['group'] AND $group_from_get)
		$list['group'] = [$group_from_get];

	// which order?
	if (!empty($zz['sqlorder'])) {
		$order = explode(',', str_replace('ORDER BY ', '', $zz['sqlorder']));
	} else {
		$order = wrap_edit_sql($zz['sql'], 'ORDER BY', '', 'list');
	}
	if (!empty($zz_conf['int']['order'])) {
		$order = array_merge($zz_conf['int']['order'], $order);
	}
	$order = wrap_edit_sql_fieldnames($order);

	// group in fields?
	$group = [];
	foreach ($zz['fields'] as $index => $field) {
		if (empty($field['field_name'])) continue;
		if (empty($field['group_in_list']) AND $field['field_name'] !== $group_from_get) continue;
		$new_index = array_search($field['field_name'], $order);
		if ($new_index === false) {
			$new_index = array_search($zz['table'].'.'.$field['field_name'], $order);
		}
		if ($new_index === false AND !empty($field['display_field'])) {
			$new_index = array_search($field['display_field'], $order);
		}
		// select? ORDER BY with a table
		if ($new_index === false AND !empty($field['sql'])) {
			$table_name = wrap_edit_sql($field['sql'], 'FROM', '', 'list');
			if ($table_name) $table_name = reset($table_name);
			if (empty($field['group_dependent_tables'])) {
				$field['group_dependent_tables'] = [];
			} elseif (!is_array($field['group_dependent_tables'])) {
				$field['group_dependent_tables'] = [$field['group_dependent_tables']];
			}
			$field['group_dependent_tables'][] = $table_name;
			foreach ($order as $o_index => $o_field) {
				if (!strstr($o_field, '.')) continue;
				$o_table_field = explode('.', $o_field);
				if (!in_array($o_table_field[0], $field['group_dependent_tables'])) continue;
				$new_index = $o_index;
				break; // we found one, enough
			}
		}
		if ($new_index !== false) {
			$group[$new_index] = $field['field_name'];
		}
	}
	ksort($group);
	// remove indices which do not correspond to sort order
	// i. e. group has to start with 0, 1, 2 ... and the list must not have gaps
	$prev_index = -1;
	foreach (array_keys($group) as $index) {
		if ($index !== $prev_index + 1) {
			unset($group[$index]);
		}
		$prev_index++;
	}

	// this check is for backwards compatibility
	// do not use 'group_in_list' with $list['group']
	if (!$list['group']) $list['group'] = $group;

	return $list;
}

/**
 * prepare data for list view
 *
 * @param array $list
 * @param array $lines
 * @param array $table_defs
 * @param array $zz
 * @param array $zz_conditions
 * @param string $table ($zz['table'])
 * @param string $mode ($ops['mode'])
 * @global array $zz_conf
 * @return array
 *	- array $rows data organized in rows
 *	- array $list with some additional information on how to output list
 */
function zz_list_data($list, $lines, $table_defs, $zz, $zz_conditions, $table, $mode) {
	global $zz_conf;
	
	$rows = [];
	$subselects = [];
	$ids = [];
	$z = 0;
	//$group_hierarchy = false; // see below, hierarchical grouping
	$lastline = false;

	// prepare content of subrecords ahead, because they have to be
	// queried together from the database
	$first_row = reset($table_defs);
	$line = reset($lines);
	// use first line only
	// @todo note: this does not allow to make a subselect conditional
	// because if it does not appear in the first line, it won't appear at all
	// we would have to go through all lines to fix this, but so far it's not
	// good to have conditional fields which do not display in list view anyways
	foreach ($first_row as $fieldindex => $field) {
		// Apply conditions
		if (zz_modules('conditions') AND !empty($zz_conditions['bool'])) {
			zz_conditions_merge_field($field, $zz_conditions['bool'], $line[$zz_conf['int']['id']['field_name']]);
		}
		if ($field AND !in_array($field['type'], ['subtable', 'foreign_table'])) continue;
		if (empty($field['subselect']['sql'])) continue;

		$subselect = zz_list_init_subselects($field, $fieldindex);
		if ($subselect) $subselects[] = $subselect;
		if (empty($line[$subselect['key_fieldname']])) {
			zz_error_log([
				'msg_dev' => 'Wrong key_field_name. Please set $zz_sub["fields"][n]["key_field_name"] to something different: %s',
				'msg_dev_args' => [implode(', ', array_keys($line))]
			]);
		}
	}
	
	list($lines, $extra) = zz_list_get_subselects($lines, $subselects, $mode);
	
	// put lines in new array, rows.
	// $rows[$z][0]['text'] = '';
	// $rows[$z][0]['class'] = [];
	foreach ($lines as $index => $line) {
		$rows[$z]['index'] = $index;
		if ($zz_conf['int']['id']['field_name']) {
			$rows[$z]['id_value'] = $id = $line[$zz_conf['int']['id']['field_name']];
			if ($id == $zz_conf['int']['id']['value']) {
				$list['current_record'] = $z;
			} elseif (!empty($zz_conf['int']['id']['values'])) {
				if (in_array($id, $zz_conf['int']['id']['values'])) {
					$list['current_records'][] = $z; 
				}
			} elseif (!empty($_GET['merge']) AND $list['merge']) {
				if ($id === substr($_GET['merge'], 0, strpos($_GET['merge'], '-')))
					$list['current_record'] = $z;
			}
		} else {
			$id = false;
		}
		if ($list['dnd']) {
			if ($list['dnd_id_field'] AND array_key_exists($list['dnd_id_field'], $line))
				$rows[$z]['dnd_id'] = $line[$list['dnd_id_field']];
			else
				$rows[$z]['dnd_id'] = $rows[$z]['id_value'];
			if ($list['dnd_sequence_field'] AND array_key_exists($list['dnd_sequence_field'], $line))
				$rows[$z]['sequence'] = $line[$list['dnd_sequence_field']];
			else
				$rows[$z]['sequence'] = $index;
		}
		$def_index = (count($table_defs) > 1) ? $index : 0;
		$rows[$z]['group'] = zz_list_group_titles($list, $table_defs[$def_index], $line);
		// configuration variables just for this line
		$zz_conf_record = zz_record_conf($zz);
		if (!empty($line['zz_conf'])) {
			// check whether there are different configuration variables 
			// e. g. for hierarchies
			$zz_conf_record = array_merge($zz_conf_record, $line['zz_conf']);
		}
		if ($list['select_multiple_records'] AND in_array($list['display'], ['ul', 'table'])) {
			// checkbox for records
			$checked = false;
			if (!empty($zz_conf['int']['id']['values']) AND !$list['dont_check_records']) {
				if (in_array($id, $zz_conf['int']['id']['values'])) $checked = true;
			}
			$rows[$z][-1]['text'] = sprintf(
				'<input type="checkbox" name="zz_record_id[]" value="%s"%s>',
				$line[$zz_conf['int']['id']['field_name']], ($checked ? ' checked="checked"' : '')
			);
			$rows[$z][-1]['class'][] = 'select_multiple_records';
		}

		// check for .cfg file with private = 1, hide list_dependency field
		if ($mode !== 'export') {
			foreach ($table_defs[$def_index] as $fieldindex => $field) {
				if (!isset($field['cfg'])) continue;
				if (empty($field['cfg'][$line[$field['field_name']]]['private'])) continue;
				if (!isset($field['list_dependency'])) continue;
				$line[$field['list_dependency']] = '********';
			}
		}

		foreach ($table_defs[$def_index] as $fieldindex => $field) {
			if (wrap_setting('debug')) zz_debug("table_query foreach ".$fieldindex);
			// conditions
			if (zz_modules('conditions') AND !empty($zz_conditions['bool'])) {
				zz_conditions_merge_field($field, $zz_conditions['bool'], $line[$zz_conf['int']['id']['field_name']]);
				if ($zz_conf_record['if'] OR $zz_conf_record['unless']) {
					zz_conditions_merge_conf($zz_conf_record, $zz_conditions['bool'], $line[$zz_conf['int']['id']['field_name']]);
					$zz_conf_record = zz_listandrecord_access($zz_conf_record);
					// $zz_conf is set regarding add, edit, delete
					if (!$zz_conf_record['add']) $zz_conf_record['copy'] = false;	// don't copy record (form+links)
				}
			}
			if (wrap_setting('debug'))
				zz_debug('table_query foreach cond set '.$fieldindex);
			
			// check all fields next to each other with list_append_next					
			while (!empty($table_defs[$def_index][$fieldindex]['list_append_next'])) {
				$list['columns'][$fieldindex] = true;
				$keys = array_keys($table_defs[$def_index]);
				$current_key_index = array_search($fieldindex, $keys);
				$next_key_index = $current_key_index + 1;
				if (isset($keys[$next_key_index])) $fieldindex = $keys[$next_key_index];
				else break;
			}

			if (wrap_setting('debug'))
				zz_debug('table_query before switch '.$fieldindex.'-'.$field['type']);
			$my_row = isset($rows[$z][$fieldindex]) ? $rows[$z][$fieldindex] : [];
			$rows[$z][$fieldindex] = zz_list_field(
				$list, $my_row, $field, $line, $lastline, $table, $mode
			);
			$list['columns'][$fieldindex] = true;

			// Sums
			$list = zz_list_sum($field, $list, $rows[$z][$fieldindex]['value'], $rows[$z]['group']);

			// group: go through everything but don't show it in list
			// @todo check that it does not collide with append_next
			if ($list['group']) {
				$pos = array_search($fieldindex, $list['group_field_no']);
				if ($pos !== false) {
					unset($rows[$z][$fieldindex]);
					$list['group_titles'][$z][$pos] = implode(' – ', $rows[$z]['group']);
					if (empty($list['group_titles'][$z][$pos])) {
						$list['group_titles'][$z][$pos] = wrap_text('- unknown -');
					}
				}
			}
			if (wrap_setting('debug')) {
				zz_debug('table_query end '.$fieldindex.'-'.$field['type']);
			}
		}
		$lastline = $line;

		$rows[$z]['modes'] = zz_output_modes($id, $zz_conf_record);
		if ($rows[$z]['modes']) $list['modes'] = true; // need a table row for this

		if ($zz_conf_record['details']) {
			$rows[$z]['details'] = zz_show_more_actions($zz_conf_record, $id, $line);
			if ($rows[$z]['modes']) {
				// we need a table row for this
				$list['details'] = true;
			} else {
				// if there's nothing in 'modes', put it in here as well
				$list['modes'] = true;
			}
		}
		$z++;
	}
	
	// mark identical fields
	$previous_row = [];
	foreach ($rows as $row_index => $row) {
		foreach ($row as $field_index => $field) {
			if (!is_numeric($field_index)) continue;
			if (!$previous_row) continue;
			// following line for gallery mode, where you can have
			// different numbers of fields per record
			if (!isset($previous_row[$field_index])) continue;
			if ($previous_row[$field_index]['text'] 
				!== $row[$field_index]['text']) continue;
			if (!$row[$field_index]['text']) continue;
			$rows[$row_index][$field_index]['class'][] = 'identical_value';
		}
		$previous_row = $row;
	}

	if ($extra) {
		end($list['columns']);
		$last = key($list['columns']);
		foreach ($extra as $table => $cols) {
			foreach ($cols as $title => $value) {
				$last++;
				$list['columns'][$last] = 1;
				$list['extra_cols'][$last] = [
					'title_tab' => $title,
					'type' => 'display',
					'hide_in_list_if_empty' => 1,
					'title' => $title,
					'show_field' => 1,
					'class' => []
				];
			}
			foreach ($rows as $index => $row) {
				if (!is_numeric($index)) continue;
				foreach ($list['extra_cols'] as $last => $col) {
					if (!array_key_exists($table.'_'.$col['title'], $lines[$row['index']]))
						$val = '';
					else
						$val = $lines[$row['index']][$table.'_'.$col['title']];
					$rows[$index][$last] = [
						'value' => $val,
						'class' => [],
						'text' => $val
					];
				}
			}
		}
	}

	return [$rows, $list];
}

/**
 * gets values for titles for grouping
 *
 * @param array $list
 * @param array $fields zzform definition for fields of this line
 * @param array $line = current database record
 * @return array ($group)
 */
function zz_list_group_titles($list, $fields, $line) {
	$group = [];
	if (!$list['group']) return $group;

	$group_count = count($list['group']);
	foreach ($fields as $no => $field) {
	//	check for group function
		$pos = array_search($no, $list['group_field_no']);
		if ($pos === false) continue;
		/*	
			@todo: hierarchical grouping!
			if (!empty($field['show_hierarchy'])) {
				if (!$group_hierarchy) $group_hierarchy = zz_list_get_group_hierarchy($field);
				$group[$pos] = zz_list_show_group_hierarchy($line[$field['field_name']], $group_hierarchy);
			} else
		*/
		if (!empty($field['display_field'])) {
			$group[$pos] = $line[$field['display_field']];
			// @todo group
		} elseif (!empty($field['enum']) AND $field['type'] === 'select') {
			$group[$pos] = zz_print_enum($field, $line[$field['field_name']], 'full');
		} elseif (!empty($field['field_name'])) {
			$group[$pos] = $line[$field['field_name']];
		}
		// Formatting of fields
		$group[$pos] = zz_field_format($group[$pos], $field);
		if (!empty($field['link'])) {
			$link = zz_makelink($field['link'], $line);
			if ($link) {
				$group[$pos] = sprintf('<a href="%s">%s</a>', $link, $group[$pos]);
			}
		}
		$group_count--;
		// we don't need to go throug all records if we found all
		// group records already
		if (!$group_count) {
			ksort($group);
			break;
		}
	}
	return $group;
}

function zz_list_group_titles_out($group_titles, $concat = ' – ') {
	// just show every title only once
	$group_titles = array_unique($group_titles);
	ksort($group_titles);
	$output = implode($concat, $group_titles);
	return $output;
}

/**
 * Query records for list view
 *
 * @param array $zz
 * 		string 'sql' SQL query ($zz['sql'])
 *		string 'sqlorder'
 * 		string 'table' name of database table ($zz['table'])
 * 		array 'fields' list of fields
 * @param array $list
 * @global array $zz_conf
 * @return array (array $lines, int $total_rows)
 */
function zz_list_query($zz, $list) {
	global $zz_conf;

	if ($zz['sqlcount'])
		$total_rows = zz_sql_count_rows($zz['sqlcount']);
	else
		$total_rows = zz_sql_count_rows($zz['sql'], $zz['table'].'.'.$zz_conf['int']['id']['field_name']);
	if (!$total_rows) return [[], 0];
	
	// ORDER must be here because of where-clause
	$zz['sql'] .= ' '.($zz['sqlorder'] ?? '');
	// Alter SQL query if GET order (AND maybe GET dir) are set
	$zz['sql'] = zz_sql_order($zz['fields'], $zz['sql']);

	if (!$list['hierarchy']['display_in']) {
		zz_list_limit_last($total_rows);
		return [zz_list_query_flat($zz), $total_rows];
	} else {
		return zz_list_query_hierarchy($zz, $list);
	}
}

/**
 * Query records for list view, flat mode
 *
 * @param string $sql SQL query ($zz['sql'])
 * @global array $zz_conf
 * @return array $lines
 */
function zz_list_query_flat($zz) {
	global $zz_conf;
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	if ($zz_conf['int']['this_limit']) { 
		// set a standard value for limit
		// this standard value will only be used on rare occasions, when NO limit is set
		// but someone tries to set a limit via URL-parameter
		$limit = wrap_setting('zzform_limit') ?? 20;
		$limit_start = $zz_conf['int']['this_limit'] - $limit;
		if ($limit_start < 0) $limit_start = 0;
		$zz['sql'] .= sprintf(' LIMIT %d, %d', $limit_start, $limit);
	}

	// read rows from database
	if ($zz_conf['int']['id']['field_name']) {
		$lines = zz_db_fetch($zz['sql'], $zz_conf['int']['id']['field_name']);
	} else {
		$lines = zz_db_fetch($zz['sql'], '_dummy_', 'numeric');
	}
	$lines = zz_list_query_extras($lines, $zz['sqlextra']);
	if ($zz['sql_translate'])
		$lines = zz_translate($zz, $lines);
	return zz_return($lines);
}

/**
 * Query extra fields
 *
 * @param array $lines
 * @param array $extra_sqls
 * @return array $lines
 */
function zz_list_query_extras($lines, $extra_sqls) {
	global $zz_conf;
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	if (!$extra_sqls) return $lines;
	foreach ($extra_sqls as $sql) {
		$sql = sprintf($sql, implode(',', array_keys($lines)));
		$extras = zz_db_fetch($sql, $zz_conf['int']['id']['field_name']);
		foreach ($extras as $id => $fields) {
			$lines[$id] = array_merge($lines[$id], $fields);
		}
	}
	return zz_return($lines);
}

/**
 * Query records for list view, flat mode
 *
 * @param array $zz
 * @param array $list
 * @global array $zz_conf
 * @return array array $lines, $total_rows
 */
function zz_list_query_hierarchy($zz, $list) {
	global $zz_conf;
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	$list['hierarchy']['id_field_name'] = $zz_conf['int']['id']['field_name'];
	list($my_lines, $total_rows) = zz_hierarchy($zz['sql'], $list['hierarchy']);
	zz_list_limit_last($total_rows);
	if ($zz_conf['int']['this_limit'] - wrap_setting('zzform_limit') >= $total_rows) {
		wrap_static('page', 'status', 404);
		return [[], $total_rows];
	}

	$lines = []; // unset and initialize
	// but if hierarchy has ID value, not all rows are shown
	if ($my_lines) {
		if (!$zz_conf['int']['this_limit']) {
			$start = 0;
			$end = $total_rows -1;
		} else {
			$start = $zz_conf['int']['this_limit'] - wrap_setting('zzform_limit');
			$end = $zz_conf['int']['this_limit'] -1;
		}
		foreach (range($start, $end) as $index) {
			if (!empty($my_lines[$index])) 
				$lines[$my_lines[$index][$zz_conf['int']['id']['field_name']]] = $my_lines[$index];
		}
		// for performance reasons, we didn't save the full result set,
		// so we have to requery it again.
		if ($zz_conf['int']['this_limit'] !== '') {
			$zz['sql'] = wrap_edit_sql($zz['sql'], 'WHERE', '`'.$zz['table'].'`.'.$zz_conf['int']['id']['field_name']
				.' IN ('.implode(',', array_keys($lines)).')');
		} // else sql remains same
		$lines = wrap_array_merge($lines, zz_db_fetch($zz['sql'], $zz_conf['int']['id']['field_name']));
	}
	foreach ($lines as $line) {
		if (empty($line['zz_hidden_line'])) continue;
		// get record which is normally beyond our scope via ID
		$zz['sql'] = wrap_edit_sql($zz['sql'], 'WHERE', 'nothing', 'delete');
		$zz['sql'] = wrap_edit_sql($zz['sql'], 'WHERE', '`'.$zz['table'].'`.'.$zz_conf['int']['id']['field_name'].' = "'.$line[$zz_conf['int']['id']['field_name']].'"');
		$line = zz_db_fetch($zz['sql']);
		if ($line) {
			$lines[$line[$zz_conf['int']['id']['field_name']]] = array_merge($lines[$line[$zz_conf['int']['id']['field_name']]], $line);
		}
	}
	$lines = zz_list_query_extras($lines, $zz['sqlextra']);
	if ($zz['sql_translate'])
		$lines = zz_translate($zz, $lines);
	return zz_return([$lines, $total_rows]);
}

/**
 * Output and formatting of a single table cell in list mode
 *
 * @param array $list
 * @param array $row
 * @param array $field field definition
 * @param array $line current record from database
 * @param array $lastline previous record from database
 * @param string $table
 * @return array $row
 *		string 'value'	= raw value in database, modified by factor/display if applicable
 *		array 'class'	= Array of class names for cell
 *		string 'text'	= HTML output for cell
 */
function zz_list_field($list, $row, $field, $line, $lastline, $table, $mode) {
	static $append_field = false;
	static $append_string_first = false;
	static $append_prefix = '';
	static $append_suffix = '';
	
	$export_fields = ['export_no_html', 'export_csv_maxlength'];
	foreach ($export_fields as $export_field) {
		if (!empty($field[$export_field]))
			$row[$export_field] = $field[$export_field];
	}
	
	// check if one of these fields has a type_detail
	$field['type'] = zz_get_fieldtype($field);
	
	// shortcuts, isset: value might be 0
	if (!empty($field['table_name']) AND isset($line[$field['table_name']]))
		$row['value'] = $line[$field['table_name']];
	elseif (!empty($field['field_name']) AND isset($line[$field['field_name']]))
		$row['value'] = $line[$field['field_name']];
	else
		$row['value'] = '';

	// set 'class'
	if (!isset($row['class'])) $row['class'] = [];
	// set class depending on where and field info
	$field['level'] = zz_list_field_level($list, $field, $line);
	$row['class'] = array_merge($row['class'], zz_field_class($field));
				
	// set 'text'
	if (empty($row['text'])) $row['text'] = '';

	//	if there's a link, glue parts together
	$link = false;
	if ($mode !== 'export' OR $list['display'] === 'kml')
		$link = zz_set_link($field, $line);

	$mark_search_string = 'field_name';
	$text = '';

	if (isset($field['display_field'])) {
		if (!empty($line[$field['display_field']])) {
			$text = $line[$field['display_field']];
		} elseif (isset($field['row_value']) AND !empty($line[$field['row_value']])) {
			$text = $line[$field['row_value']];
		}
		$text = zz_htmltag_escape($text);
		$mark_search_string = 'display_field';
	} else {
		if (isset($field['row_value'])) {
			$row['value'] = $line[$field['row_value']];
		}
		//	go for type of field if no display field is set
		switch ($field['type']) {
		case 'calculated':
			if ($field['calculation'] === 'hours') {
				$row['value'] = 0;
				foreach ($field['calculation_fields'] as $calc_field)
					if (!$row['value']) $row['value'] = strtotime($line[$calc_field]);
					else $row['value'] -= strtotime($line[$calc_field]);
				if ($row['value'] < 0) $text = '<em class="negative">';
				$text .= zz_hour_format($row['value']);
				if ($row['value'] < 0) $text .= '</em>';
			} elseif ($field['calculation'] === 'sum') {
				$row['value'] = 0;
				foreach ($field['calculation_fields'] as $calc_field)
					$row['value'] += $line[$calc_field];
				$text = $row['value'];
			} elseif ($field['calculation'] === 'sql') {
				$text = $row['value'];
			}
			$mark_search_string = false;
			break;
		case 'image':
		case 'upload_image':
			$mark_search_string = false;
			$type = $mode === 'export' ? 'link' : 'image';
			if (isset($field['path'])) {
				if ($img = zz_makelink($field['path'], $line, $type)) {
					$text .= $link.$img.($link ? '</a>' : '');
				} elseif (isset($field['default_image']) AND $type === 'image') {
					if (is_array($field['default_image'])) {
						$default_image = zz_makelink($field['default_image'], $line);
					} else {
						$default_image = $field['default_image'];
					}
					$text .= $link.'<img src="'.$default_image
						.'"  alt="'.wrap_text('No image').'" class="thumb">'.($link ? '</a>' : '');
				}
				if (!empty($field['image']) AND $mode != 'export') {
					foreach ($field['image'] as $image) {
						if (empty($image['show_link'])) continue;
						if ($imglink = zz_makelink($image['path'], $line))
							$text .= ' <a href="'.$imglink.'">'.$image['title'].'</a><br>';
					}
				}
				$link = false;
			} elseif (isset($field['path_json_request'])) {
				$text = zz_list_syndication_get($field, $line);
			}
			break;
		case 'subtable':
		case 'foreign_table':
			$text = $row['value']; // field was already formatted etc. in subselect
			$mark_search_string = false;
			$link = false;
			break;
		case 'url':
		case 'url+placeholder':
			$text = wrap_punycode_decode($row['value']);
			$text = zz_cut_length(zz_htmltag_escape($text), wrap_setting('zzform_max_select_val_len'));
			break;
		case 'mail':
		case 'mail+name':
			$text = zz_htmltag_escape($row['value']);
			break;
		case 'ipv4':
		case 'date':
		case 'time':
		case 'datetime':
			$text = zz_field_format($row['value'], $field);
			break;
		case 'ip':
			$text = @inet_ntop($row['value']);
			break;
		case 'unix_timestamp':
			$text = date('Y-m-d H:i:s', $row['value']);
			break;
		case 'timestamp':
			$text = zz_timestamp_format($row['value']);
			break;
		case 'select':
			if (!empty($field['set']) 
				OR !empty($field['set_sql']) 
				OR !empty($field['set_folder'])) {
				$values_old = explode(',', $row['value']);
				$values_new = [];
				foreach ($values_old as $value) {
					$values_new[] = zz_print_enum($field, $value);
				}
				$text = implode(', ', $values_new);
			} elseif (!empty($field['enum'])) {
				$text = zz_print_enum($field, $row['value']);
			} else {
				$text = $row['value'];
			}
			break;
		case 'number':
			if (isset($field['factor']) && $row['value']) {
				$row['value'] /= $field['factor'];
			}
			$text = zz_number_format($row['value'], $field);
			break;
		case 'geo_point':
			// don't display anything in binary format
			if (empty($field['text_field'])) break;
			$row['value'] = $line[$field['text_field']];
			if (!$row['value']) break;
			if (empty($field['geo_format'])) $field['geo_format'] = 'dms';
			if (empty($field['list_concat_fields'])) $field['list_concat_fields'] = '<br>';
			$text = zz_geo_coord_sql_out($row['value'], $field['geo_format'], $field['list_concat_fields']);
			break;
		case 'display':
			if (!empty($field['display_title']) 
				&& in_array($row['value'], array_keys($field['display_title']))) {
				// replace field content with display_title, if set.
				// display values depending on database value
				$text = $field['display_title'][$row['value']];
			} elseif (!empty($field['display_value'])) {
				// translations should be done in $zz-definition-file
				$text = $field['display_value'];
				if (!empty($field['type_detail']) AND $field['type_detail'] === 'number') {
					$text = zz_number_format($text, $field);
				}
			} else {
				$text = $row['value'];
				if ($text) $text = nl2br(zz_htmltag_escape($text));
			}
			break;
		case 'list_function':
			$text = $field['list_function']($field, $row['value'], $line);
			break;
		case 'parameter':
			$text = zz_parameter_format($row['value']);
			break;
		case 'phone':
			$text = zz_phone_format($row['value']);
			break;
		case 'username':
			$text = zz_username_format($row['value'], $field);
			break;
		default:
			$text = $row['value'];
			if ($text AND empty($field['list_format']) AND empty($field['export_no_html'])) {
				$text = nl2br(zz_htmltag_escape($text));
			}
			break;
		}
	}
	if (!empty($field['translate_field_value']))
		$text = wrap_text($text);
	if (!empty($field['list_format']) AND $text)
		$text = zz_list_format($text, $field['list_format']);
	if (!empty($field['hide_zeros']) AND !$text)
		$text = '';

	if (!empty($field['list_prefix_append']))
		$append_prefix = $field['list_prefix_append'];
	if (!empty($field['list_suffix_append']))
		$append_suffix = $field['list_suffix_append'];
	
	if ($text === '' OR $text === false OR $text === NULL) {
		// always append suffix on last appended field, even if it is empty
		if ($append_suffix AND empty($append_prefix) AND empty($field['list_append_next'])) {
			$row['text'] .= wrap_text($append_suffix);
			$append_suffix = '';
			$append_prefix = '';
		}
		return $row;
	}

	if ($mode !== 'export' AND (!isset($field['word_split']) OR $field['word_split'] === true)) {
		$text = zz_list_word_split($text);
		if ($mark_search_string) {
			$text = zz_mark_search_string($text, $field[$mark_search_string], $field);
		}
	}

	// add prefixes etc. to 'text'
	if (!empty($field['list_append_if_first']) AND !$append_string_first) {
		$row['text'] .= wrap_text($field['list_append_if_first']);
		$append_string_first = true;
	} elseif (!empty($field['list_append_if_middle']) AND $append_string_first) {
		$row['text'] .= wrap_text($field['list_append_if_middle']);
	}
	if (!empty($field['list_append_next'])) {
		$append_field = true;
	} else {
		$append_field = false;
		$append_string_first = false;
	}
	if (!empty($field['list_prefix'])) {
		$row['text'] .= wrap_text($field['list_prefix']);
	}
	if (!empty($append_prefix)) {
		$row['text'] .= wrap_text($append_prefix);
		$append_prefix = '';
	}
	if (!empty($field['list_abbr']) AND $mode != 'export') {
		$row['text'] .= '<abbr title="'.wrap_html_escape($line[$field['list_abbr']]).'">';
	}

	if ($link) $row['text'] .= $link;
	if (empty($field['list_hide_value']))
		$row['text'] .= $text;
	if ($link) $row['text'] .= '</a>';

	if (isset($field['list_unit'])) {
		$row['text'] .= '&nbsp;'.$field['list_unit'];
	} elseif (isset($field['unit'])) {
		$row['text'] .= '&nbsp;'.$field['unit'];
	}
	if (!empty($field['list_suffix'])) {
		$row['text'] .= wrap_text($field['list_suffix']);
	}
	if (!empty($append_suffix) AND empty($field['list_append_next'])) {
		$row['text'] .= wrap_text($append_suffix);
		$append_suffix = '';
	}
	if (!empty($field['list_abbr']) AND $mode !== 'export') {
		$row['text'] .= '</abbr>';
	}

	return $row;
}

/**
 * adds values, outputs HTML table foot
 *
 * @param array $table_defs
 * @param int $z
 * @param array $sum (field_name => value)
 * @param array $list
 * @return string HTML output of table foot
 */
function zz_field_sum($table_defs, $z, $sum, $list) {
	$tfoot_line = '';
	foreach ($table_defs as $index => $field) {
		if (!$field['show_field']) continue;
		if (in_array($index, $list['group_field_no'])) continue;
		if ($field['type'] === 'id' && empty($field['show_id'])) {
			$tfoot_line .= '<td class="recordid">'.$z.'</td>';
		} elseif (!empty($field['sum'])) {
			$tfoot_line .= '<td'.zz_field_class($field, true).'>';
			$value = $sum[$field['title']];
			if (isset($field['calculation']) AND $field['calculation'] === 'hours') {
				$value = zz_hour_format($value);
			} elseif (isset($field['number_type'])) {
				$value = zz_number_format($value, $field);
			}
			if (!empty($field['list_format']) AND $value) {
				$value = zz_list_format($value, $field['list_format']);
			}

			$tfoot_line.= $value;
			if (isset($field['unit']) && $value) 
				$tfoot_line .= '&nbsp;'.$field['unit'];	
			$tfoot_line .= '</td>';
		} else {
			$tfoot_line .= '<td'.zz_field_class($field, true).'>&nbsp;</td>';
		}
	}
	return $tfoot_line;
}

/**
 * formats a field's content with one or more functions
 *
 * @param string $text
 * @param mixed $list_format (array = list of formatting functions)
 */
function zz_list_format($text, $list_format) {
	wrap_page_format_files();
	if (!is_array($list_format)) $list_format = [$list_format];
	foreach ($list_format as $format) {
		if (wrap_setting('debug')) zz_debug('start', $format);
		$text = $format($text);
		if (wrap_setting('debug')) zz_debug('end');
	}
	return $text;
}

/**
 * creates HTML for a link behind a field value
 * set link depending on $field['type'] or $field['link']
 *
 * @param array $field definition of field
 *		'type', 'field_name', 'link', 'link_referer',
 *		'link_title', 'link_target', 'link_attributes'
 * @param array $line values of current record
 * @return string $link opening A tag HTML code for link (false if there is no link)
 */
function zz_set_link($field, $line) {
	$link = false;
	if (!empty($field['list_no_link'])) return false;
	if ($field['type'] === 'url') {
		$link = $line[$field['field_name']];
	} elseif ($field['type'] === 'mail' AND $line[$field['field_name']]) {
		// mailto-Link only if there is an address in that field
		$link = 'mailto:'.$line[$field['field_name']];
	} elseif ($field['type'] === 'mail+name' AND $line[$field['field_name']]) {
		// mailto-Link only if there is an address in that field
		$link = 'mailto:'.rawurlencode($line[$field['field_name']]);
	} elseif (isset($field['link']) AND is_array($field['link'])) {
		$link = zz_makelink($field['link'], $line);
	} elseif (!empty($field['link'])) {
		$link = $field['link'].$line[$field['field_name']];
	}
	if ($link AND !empty($field['link_referer'])) 
		$link .= '&amp;referer='.urlencode(wrap_setting('request_uri'));
	if (!$link) return false;

	// if there's something, go on and put HTML for link together
	$link_title = false;
	if (!empty($field['link_title'])) {
		if (is_array($field['link_title']))
			$link_title = zz_makelink($field['link_title'], $line);
		else
			$link_title = $field['link_title'];
	}
	$link = '<a href="'.$link.'"'
		.(!empty($field['link_target']) ? ' target="'.$field['link_target'].'"' : '')
		.($link_title ? ' title="'.$link_title.'"' : '')
		.(!empty($field['link_attributes']) ? ' '.$field['link_attributes'] : '')
		.'>';
	return $link;
}

/**
 * add zero width spaces to very long words to make list readable
 *
 * @param string $text
 * @return string
 */
function zz_list_word_split($text) {
	if (!wrap_setting('zzform_word_split')) return $text;
	if (!$text) return $text;

	$words = explode(' ', $text);
	if (substr($words[0], 0, 1) === '<') return $text; // no splitting in HTML code
	foreach ($words as $index => $word) {
		if (strlen($word) < wrap_setting('zzform_word_split')) continue;
		if (!strstr($word, '<') AND !strstr($word, '&')) {
			$parts = mb_str_split($word, wrap_setting('zzform_word_split'));
		} else {
			// no break inside entities
			// no break inside HTML
			$word_length = 0;
			$last_split = -1;
			$parts = [];
			$stop_char = false;
			$remaining = $word;
			for ($i = 0; $i < mb_strlen($word); $i++) {
				if (!$stop_char) $word_length++;
				switch (mb_substr($word, $i, 1)) {
				case '<':
					$stop_char = '>';
					break;
				case '>':
					if ($stop_char === '>') $stop_char = false;
					break;
				case '&':
					$stop_char = ';';
					break;
				case ';':
					if ($stop_char === ';') $stop_char = false;
					break;
				}
				if ($word_length === wrap_setting('zzform_word_split')) {
					$parts[] = mb_substr($remaining, 0, $i - $last_split);
					$word_length = 0;
					$remaining = mb_substr($remaining, $i - $last_split);
					$last_split = $i;
				}
			}
			if ($remaining) $parts[] = $remaining;
			$word = [$word];
		}
		$words[$index] = implode('<wbr>', $parts);
	}
	$text = implode(' ', $words);
	return $text;
}

/**
 * marks search string in list display on webpage
 *
 * @param string $value value to mark
 * @param string $field_name (optional) if set, only values in this column
 *			will be marked
 * @param array $field field definition
 * @global array $zz_conf
 * @return string $value value with marks
 * @todo support strings which are splitted by <wbr>
 */
function zz_mark_search_string($value, $field_name = false, $field = []) {
	global $zz_conf;
	// check if field should be marked
	if (!empty($zz_conf['int']['export'])) return $value;
	if (!empty($field['dont_mark_search_string'])) return $value;
	if (empty($_GET['q'])) return $value;
	if (!empty($_GET['scope'])) {
		if (strstr($_GET['scope'], '.'))
			$my_field_name = substr($_GET['scope'], strrpos($_GET['scope'], '.') + 1);
		else
			$my_field_name = $_GET['scope'];
		if (!empty($field['mark_scope']) AND in_array($my_field_name, $field['mark_scope']))
			$my_field_name = $field_name;
		
		if ($my_field_name != $field_name) return $value;
	}

	// meta characters which must be escaped for preg_replace
	$needle = preg_quote(trim($_GET['q']));
	$needle = str_replace('/', '\\/', $needle);
	$highlight = '<span class="highlight">\1</span>';
	$pattern = '/(?!<.*?)(%s)(?![^<>]*?>)/i';
	$regex = sprintf($pattern, $needle);
	$value = preg_replace($regex, $highlight, $value);
	return $value;
}

/**
 * checks field indices of fields which will be grouped
 *
 * @param array $list
 * @param array $field $zz['fields'][n]-field definition
 * @param int $index index 'n' of $zz['fields']
 * @return bool true/false if field will be shown (group: false, otherwise true)
 */
function zz_list_group_field_no(&$list, $field, $index) {
	$keys = ['display_field', 'field_name'];
	// field_name will overwrite display_field!
	foreach ($keys as $key) {
		if (empty($field[$key])) continue;
		$pos = array_search($field[$key], $list['group']);
		if ($pos === false) continue;
		$list['group_field_no'][$pos] = $index;
		ksort($list['group_field_no']);
		return false;
	}
	return true;
}

/**
 * adds values for sums if 'sum' is set for a field
 *
 * @param array $field
 * @param array $list
 * @param mixed $value
 * @param array $group ($rows[$z]['group'])
 * @return array ($list)
 */
function zz_list_sum($field, $list, $value, $group) {
	if (empty($field['sum'])) return $list;
	if (empty($field['calculation'])) $field['calculation'] = '';
	if ($field['calculation'] === 'sql') return $list;

	if (!isset($list['sum'][$field['title']])) {
		$list['sum'][$field['title']] = 0;
	}
	if ($field['calculation'] === 'hours' AND strstr($value, ':')) {
		$value = explode(':', $value);
		if (!isset($value[1])) $value[1] = 0;
		if (!isset($value[2])) $value[2] = 0;
		$value = 3600 * $value[0] + 60 * $value[1] + $value[2];
	}
	if ($value) $list['sum'][$field['title']] += $value;
	$list['sum_group'] = zz_list_group_sum($group, $list['sum_group'], $field['title'], $value);
	return $list;
}

/**
 * calculates sums for fields which will be grouped
 *
 * @param array $row_group ($rows[$z]['group'])
 * @param array $sum_group (will be created/amended by this function)
 * @param string $field_title (for there may be more than one field per row
 *		which we would like to get the sum from)
 * @param double $sum sum to be added
 * @return array $sum_group
 *		e. g. Architecture = 40; Architecture[income] = 100; 
 *		Architecture[expenses] = 60 etc.
 */
function zz_list_group_sum($row_group, $sum_group, $field_title, $sum) {
	$index = '';
	foreach ($row_group as $my_group) {
		if ($index) $index .= '['.$my_group.']';
		else $index = $my_group;
		if (!isset($sum_group[$index][$field_title])) 
			$sum_group[$index][$field_title] = 0;
		if ($sum) $sum_group[$index][$field_title] += $sum;
	}
	return $sum_group;
}

/**
 * shows footer line for group with calculated sums
 * 
 * @param array $rowgroup
 * @param array $main_table_query ($head from zz_list)
 * @param int $z
 * @param array $list
 * @return string
 */
function zz_list_group_foot($rowgroup, $main_table_query, $z, $list) {
	$my_index = '';
	foreach ($rowgroup as $my_group) {
		if ($my_index) $my_index .= '['.$my_group.']';
		else $my_index = $my_group;
	}
	if (empty($list['sum_group'][$my_index])) return false;
	return '<tr class="group_sum">'
		.zz_field_sum($main_table_query, $z, $list['sum_group'][$my_index], $list)
		.'</tr>'."\n";
}

/**
 * outputs number of records in list
 *
 * @param int $total_rows
 * @return string HTML code with text
 */
function zz_list_total_records($total_rows) {
	if (wrap_setting('zzform_hide_total_records')) return '';
	if ($total_rows) return '<p class="totalrecords">'.wrap_text('%d records total', ['values' => [$total_rows]]).'</p>';
	return '';
}

/**
 * if LIMIT is set, shows different pages for each $step records
 *
 * @param int $limit_step = wrap_setting('zzform_limit') how many records shall be shown on each page
 * @param int $this_limit = $zz_conf['int']['this_limit'] last record no. on this page
 * @param int $total_rows	count of total records that might be shown
 * @param string $scope 'body', @todo: 'head' (not yet implemented)
 * @return string HTML output
 * @todo
 * 	- <link rel="next">, <link rel="previous">
 */
function zz_list_pages($this_limit, $total_rows, $scope = 'body') {
	// check whether there are records
	if (!$total_rows) return false;
	
	// check whether records shall be limited or not
	$limit_step = wrap_setting('zzform_limit');
	if (!$limit_step) return false;

	// check whether a limit is set (all records shown won't need a navigation)
	// for performance reasons, next time a record is edited, limit will be reset
	if (!$this_limit) return false;

	// check whether all records fit on one page
	// and this limit is the current limit (if bigger, show page links to
	// allow access to first page)
	if ($limit_step >= $total_rows AND $this_limit === $limit_step) return false;

	$url = zz_list_pageurl();

	// set standard links
	$links = [];
	$links[] = [
		'link'	=> zz_list_pagelink(0, $this_limit, $limit_step, $url),
		'text'	=> '|&lt;',
		'class' => 'first',
		'title' => wrap_text('First page')
	];
	$prev = $this_limit - $limit_step;
	if ($prev > $total_rows) {
		$prev = ceil($total_rows/$limit_step)*$limit_step;
	}
	$links[] = [
		'link'	=> zz_list_pagelink($prev, $this_limit, 0, $url),
		'text'	=> '&lt;',
		'class' => 'prev',
		'title' => 	wrap_text('Previous page')
	];
	if ($total_rows < wrap_setting('zzform_limit_all_max')) {
		$links[] = [
			'link'	=> zz_list_pagelink(-1, $this_limit, 0, $url),
			'text'	=> wrap_text('all'),
			'class' => 'all',
			'title' => 	wrap_text('All records on one page')
		];
	}

	// set links for each step
	$ellipsis_min = false;
	$ellipsis_max = false;
	// last step, = next integer from total_rows which can be divided by limit_step
	$rec_last = 0; 

	// missing lines on last page to make it a full page
	$offset = $limit_step - ($total_rows % $limit_step);
	$max_limit = $total_rows + $offset;

	$limit_show_range = wrap_setting('zzform_limit_show_range');
	if ($limit_show_range
		AND $total_rows >= $limit_show_range
		AND $this_limit <= $max_limit)
	{
		$rec_start = $this_limit - ($limit_show_range / 2 + 2 * $limit_step);
		if ($rec_start < 0) {
			$rec_start = 0;
		} elseif ($rec_start > 0) {
			// set rec start to something which can be divided through step
			$rec_start = ceil($rec_start/$limit_step)*$limit_step;
		}
		$rec_end = $this_limit + ($limit_show_range + $limit_step);
		// total_rows -1 because min is + 1 later on
		if ($rec_end > $total_rows -1) $rec_end = $total_rows -1;
		$rec_last = (ceil($total_rows/$limit_step)*$limit_step);
	} else {
		$rec_start = 0;
		$rec_end = $total_rows -1; // total_rows -1 because min is + 1 later on
	}

	for ($i = $rec_start; $i <= $rec_end; $i = $i + $limit_step) { 
		$range_min = $i + 1;
		$range_max = $i + $limit_step;
		if ($this_limit + ceil($limit_show_range / 2) < $range_min) {
			if (!$ellipsis_max) {
				$links[] = ['text' => '&hellip;', 'link' => ''];
				$ellipsis_max = true;
			}
			continue;
		}
		if ($this_limit > $range_max + floor($limit_show_range / 2)) {
			if (!$ellipsis_min) {
				$links[] = ['text' => '&hellip;', 'link' => ''];
				$ellipsis_min = true;
			}
			continue;
		}
		if ($range_max > $total_rows) $range_max = $total_rows;
		// if just one above the last limit show this number only once
		switch (wrap_setting('zzform_limit_display')) {
		case 'entries':
			$text = ($range_min === $range_max) ? $range_min : $range_min.'-'.$range_max;
			break;
		default:
		case 'pages':
			$text = $i / wrap_setting('zzform_limit') + 1;
		}
		$links[] = [
			'link'	=> zz_list_pagelink($i, $this_limit, $limit_step, $url),
			'text'	=> $text,
			'mark_current' => true
			
		];
	}
	$limit_next = $this_limit + $limit_step;
	if ($limit_next > $range_max) $limit_next = $i;
	if (!$rec_last) $rec_last = $i;

	// set more standard links
	$links[] = [
		'link'	=> zz_list_pagelink($limit_next, $this_limit, 0, $url),
		'text'	=> '&gt;',
		'class' => 'next',
		'title' => wrap_text('Next page')
	];
	$links[] = [
		'link'	=> zz_list_pagelink($rec_last, $this_limit, 0, $url, 'last'),
		'text'	=> '&gt;|',
		'class' => 'last',
		'title' => wrap_text('Last page')
	];

	return wrap_template('zzform-list-pages', $links);
}

/**
 * creates the URLs for the limit links
 *
 * @global array $zz_conf
 * 		'int'['url']['self'], 'int'['url']['qs'], 'int'['url']['qs_zzform']
 * @return array $url
 *		string 'base' => base URL, string 'query' => query string &?limit=
 */
function zz_list_pageurl() {
	global $zz_conf;

	// remove mode, id
	$unwanted_keys = [
		'mode', 'id', 'limit', 'add', 'delete', 'insert', 'update', 'noupdate',
		'zzhash', 'edit', 'show', 'revise', 'merge'
	];
	$url['base'] = $zz_conf['int']['url']['self']
		.zz_edit_query_string($zz_conf['int']['url']['qs']
		.$zz_conf['int']['url']['qs_zzform'], $unwanted_keys);
	$url['query'] = sprintf('%slimit=', (parse_url($url['base'], PHP_URL_QUERY) ? '&amp;' : '?'));
	return $url;
}

/**
 * creates URLs for links in page navigation
 *
 * @param int $start record no. whith which we start, -1 = show all records
 * @param int $limit current limit
 * @param int $limit_step 
 * @param array $url string 'base' = bare URL without unwanted query strings,
 *		string 'query' = querystring for limit
 * @param string $pos special position
 * @return string $url with limit=n
 */
function zz_list_pagelink($start, $limit, $limit_step, $url, $pos = '') {
	if ($start == -1) {
		// all records
		if (!$limit) return false;
		else $limit_new = 0;
	} else {
		$limit_new = $start + $limit_step;
		if ($limit_new == $limit) { // no === possible!
			// current page
			return false;
		} elseif (!$limit_new) {
			// 0 does not exist, means all records
			return false;
		}
	}
	$url_out = $url['base'];
	if ($limit_new != wrap_setting('zzform_limit')) {
		$url_out .= $url['query'].($pos ? $pos : $limit_new);
	}
	return $url_out;
}

/**
 * adds ORDER BY to SQL string, if set via URL
 * checks URL parameter for vaildity as well
 *
 * @param array $fields
 * @param string $sql
 * @global array $zz_conf
 * @return string $sql
 */
function zz_sql_order($fields, $sql) {
	global $zz_conf;

	// direction
	if (!isset($_GET['order']) AND !isset($_GET['group'])) {
		// accept 'dir' only in combination with order or group
		$possible_values = [];
	} else {
		$possible_values = ['desc', 'asc'];
		$my_order = '';
	}
	$dir = zz_check_get_array('dir', 'values', $possible_values);
	if ($dir === 'asc') {
		$my_order = ' ASC';
	} elseif ($dir === 'desc') {
		$my_order = ' DESC';
	}

	if (!isset($_GET['order']) AND !isset($_GET['group'])) return $sql;

	$order = [];
	$types = ['order', 'group'];
	foreach ($types as $type) {
		$get_used[$type] = false;
	}
	foreach ($types as $type) {
		if (empty($_GET[$type])) continue;
		foreach ($fields as $field) {
			if (!empty($field['dont_sort'])) continue;
			$sort = zz_sql_order_check($field, $type, $_GET[$type]);
			if (!$sort) continue;
			$get_used[$type] = true;
			if (strstr($sort, ',')) {
				$sort = explode(',', $sort);
			} else {
				$sort = [$sort];
			}
			$sort = implode($my_order.', ', $sort);
			$order[] = $sort.$my_order;
		}
	}
	
	// check variables if valid
	$unwanted_keys = [];
	foreach ($types as $type) {
		if (isset($_GET[$type]) AND !$get_used[$type]) $unwanted_keys[] = $type;
	}
	if ($unwanted_keys) {
		wrap_static('page', 'status', 404);
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
			$zz_conf['int']['url']['qs_zzform'], $unwanted_keys
		);
	}
	
	if (!$order) return $sql;
	$zz_conf['int']['order'] = $order;
	$sql = wrap_edit_sql($sql, 'ORDER BY', implode(',', $order), 'add');
	return $sql;
}

/**
 * check sorting field against field name or display field name, return order by
 *
 * @param array $field
 * @param string $type
 * @param string $field_name (from $_GET['order'] or $_GET['group'])
 * @return string
 */
function zz_sql_order_check($field, $type, $field_name) {
	if (isset($field['display_field']) AND $field['display_field'] === $field_name) {
		$found = true;
	} elseif (isset($field['field_name']) AND $field['field_name'] === $field_name) {
		$found = true;
	} elseif (in_array($field['type'], ['subtable', 'foreign_table'])) {
		if (empty($field['list_display'])) return '';
		if ($field['list_display'] !== 'inline') return '';
		if (!strstr($field_name, '.')) return '';
		list($detail_table, $detail_field_name) = explode('.', $field_name);
		if ($detail_table !== $field['table']) return '';
		if (empty($field['fields'])) return '';
		foreach ($field['fields'] as $detailfield) {
			$sort = zz_sql_order_check($detailfield, $type, $detail_field_name);
			if ($sort) return $sort;
		}
		return '';
	} else {
		return '';
	}

	if (isset($field['order'])) return $field['order'];
	return $_GET[$type];
}

/**
 * HTML output inside of <th> field in <thead>
 *
 * @param array $field
 * @param string $mode 'html' = HTML output, order by; 'nohtml' = plain text
 * @global $zz_conf
 * @return string HTML output
 */
function zz_list_th($field, $mode = 'html') {
	global $zz_conf;

	$out = $field['title_tab'] ?? $field['title'];
	if (!empty($field['dont_sort'])) return $out;
	if (!isset($field['field_name'])) return $out;
	$unsortable_fields = ['calculated', 'image', 'upload_image']; // 'subtable'?
	if (in_array($field['type'], $unsortable_fields)) return $out;
	if ($mode === 'nohtml') return $out;
	
	// create a link to order this column if desired
	if (isset($field['row_value'])) $order_val = $field['row_value'];
	elseif (isset($field['display_field'])) $order_val = $field['display_field'];
	else $order_val = $field['field_name'];
	$unwanted_keys = [
		'dir', 'delete', 'insert', 'update', 'noupdate', 'zzhash'
	];
	$new_keys = ['order' => $order_val];
	$uri = $zz_conf['int']['url']['self'].zz_edit_query_string($zz_conf['int']['url']['qs']	
		.$zz_conf['int']['url']['qs_zzform'], $unwanted_keys, $new_keys);
	if (str_replace('&amp;', '&', $uri) === wrap_setting('request_uri')) {
		$uri.= '&amp;dir=desc';
		$order_dir = wrap_text('descending');
	} else {
		$order_dir = wrap_text('ascending');
	}
	$link_open = '<a href="'.$uri.'" title="'.wrap_text('Order by').' '
		.strip_tags($field['title']).' ('.$order_dir.')">';
	$link_close = '</a>';

	// HTML output
	$out = $link_open.$out.$link_close;
	return $out;
}

/**
 * init "subselects" in detailrecords
 *
 * @param array $field
 * @param int $fieldindex no. of field where content appears in list
 * @return array
 *	- string key_field_name
 *	- array subselect definition
 */
function zz_list_init_subselects($field, $fieldindex) {
	global $zz_conf;

	$subselect = $field['subselect'];
	$foreign_key_field = [];
	$translation_key_field = [];
	foreach ($field['fields'] as $subfield) {
		if ($subfield['type'] === 'foreign_key') {
			$foreign_key_field = $subfield;
		} elseif ($subfield['type'] === 'translation_key') {
			$translation_key_field = $subfield;
		}
	}
	// get field name of foreign key
	$subselect['id_field_name'] = $foreign_key_field['field_name'];
	if ($translation_key_field) {
		$subselect['key_fieldname'] = $zz_conf['int']['id']['field_name'];
		$subselect['translation_key'] = $translation_key_field['translation_key'];
	} else { // $foreign_key_field
		// if main field name and foreign field name differ, use main ID
		// for requests
		if (!empty($foreign_key_field['key_field_name'])) {
			// different fieldnames
			$subselect['key_fieldname'] = $foreign_key_field['key_field_name'];
		} else {
			$subselect['key_fieldname'] = $foreign_key_field['field_name'];
		}
	}
	// id_field = joined_table.field_name
	if (empty($subselect['table'])) {
		$subselect['table'] = $field['table'];
	}
	$subselect['id_table_and_fieldname'] = $subselect['table'].'.'.$subselect['id_field_name'];
	$subselect['fieldindex'] = $fieldindex;
	$subselect['table_name'] = $field['table_name'];

	return $subselect;
}

/**
 * get values for "subselects" in detailrecords
 *
 * @param array $lines
 * @param array $subselects List of detail records with an SQL query
 *		$zz['fields'][n]['subselect'] = ...;
 *			required keys: 'sql', 'id_table_and_fieldname', 'id_field_name'
 *			optional keys: 'translation_key', 'list_format', 'export_no_html',
 *			'prefix', 'concat_rows', 'suffix', 'concat_fields', 'show_empty_cells'
 * @param string $mode
 * @return array
 */
function zz_list_get_subselects($lines, $subselects, $mode) {
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	$extra = [];
	
	if (!$subselects) return zz_return([$lines, $extra]);
	
	foreach ($subselects as $subselect) {
		// IDs
		$ids = [];
		foreach ($lines as $no => $line) {
			$ids[$no] = $line[$subselect['key_fieldname']];
		}
	
		// default values
		if (!empty($subselect['export_no_html']) OR $mode === 'export') {
			if (!isset($subselect['prefix'])) $subselect['prefix'] = '';
			if (!isset($subselect['concat_rows'])) $subselect['concat_rows'] = "\n";
			if (!isset($subselect['suffix'])) $subselect['suffix'] = '';
		} else {
			if (!isset($subselect['prefix'])) $subselect['prefix'] = '<p>';
			if (!isset($subselect['concat_rows'])) $subselect['concat_rows'] = "</p>\n<p>";
			if (!isset($subselect['suffix'])) $subselect['suffix'] = '</p>';
		}
		if (!isset($subselect['concat_fields'])) $subselect['concat_fields'] = ' ';
		if (!isset($subselect['show_empty_cells'])) $subselect['show_empty_cells'] = false;
		if (!isset($subselect['link'])) $subselect['link'] = [];
		if (!isset($subselect['sql_ignore'])) $subselect['sql_ignore'] = [];
		if (!is_array($subselect['sql_ignore'])) $subselect['sql_ignore'] = [$subselect['sql_ignore']];
		// ID field will not be shown
		$subselect['sql_ignore'][] = $subselect['id_field_name'];
		
		$subselect['sql'] = wrap_edit_sql($subselect['sql'], 'WHERE', 
			$subselect['id_table_and_fieldname'].' IN ('.implode(', ', $ids).')');
		if (!empty($subselect['translation_key']))
			$subselect['sql'] = wrap_edit_sql($subselect['sql'], 'WHERE', 
				'translationfield_id = '.$subselect['translation_key']);
		// E_USER_WARNING might return message, we do not want to see this message
		// but in the logs
		$records = zz_db_fetch($subselect['sql'], '_dummy_id_', 'numeric', false, E_USER_WARNING);
		$records = zz_translate($subselect, $records);
		$sub_lines = [];
		foreach ($records as $record) {
			// sort by record ID
			$sub_lines[$record[$subselect['id_field_name']]][] = $record;
		}
		if (!is_array($sub_lines)) $sub_lines = [];

		foreach ($ids as $no => $id) {
			if (empty($sub_lines[$id])) continue;
			$linetext = [];
			foreach ($sub_lines[$id] as $linefields) {
				$link = $subselect['link'] ? zz_makelink($subselect['link'], $linefields) : '';
				$image = isset($subselect['image']) ? zz_makelink($subselect['image'], $linefields, 'image') : '';
				if ($image) {
					if ($link) $image = sprintf('<a href="%s">%s</a>', $link, $image);
					$linetext[] = $image;
					$subselect['dont_mark_search_string'] = true; // no search marking here
					continue;
				}
				if (isset($subselect['field_link']))
					foreach ($subselect['field_link'] as $index => $field_link)
						$subselect['field_link_parsed'][$index] = zz_makelink($field_link, $linefields);
				foreach ($subselect['sql_ignore'] as $ignored_fieldname)
					unset($linefields[$ignored_fieldname]); 
				if (!empty($subselect['display_inline'])) {
					$key = array_shift($linefields);
					$value = array_shift($linefields);
					$lines[$no][$subselect['table_name'].'_'.$key] = zz_mark_search_string(
						$value, $subselect['table_name'], $subselect
					);
					$extra[$subselect['table_name']][$key] = $key;
					continue;
				}
				$fieldtext = '';
				$index = 0;
				foreach ($linefields as $field_name => $db_fields) {
					if ($subselect['show_empty_cells'] AND !$db_fields) $db_fields = '&nbsp;';
					if ($db_fields) {
						if (!empty($subselect['list_field_format'])) {
							if (is_array($subselect['list_field_format'])) {
								if (!empty($subselect['list_field_format'][$field_name])) {
									$db_fields = $subselect['list_field_format'][$field_name]($db_fields);
								}
							} else {
								$db_fields = $subselect['list_field_format']($db_fields);
							}
						}
						if (!empty($subselect['field_link_parsed'][$index]))
							$db_fields = sprintf('<a href="%s">%s</a>', $subselect['field_link_parsed'][$index], $db_fields);
						if (!empty($subselect['field_prefix'][$index]))
							$db_fields = $subselect['field_prefix'][$index].$db_fields;
						if (!empty($subselect['field_suffix'][$index]))
							$db_fields .= $subselect['field_suffix'][$index];
						if ($fieldtext) $fieldtext .= $subselect['concat_fields'];
					}
					$fieldtext .= $db_fields;
					$index++;
				}
				if ($link) $fieldtext = sprintf('<a href="%s">%s</a>', $link, $fieldtext);
				$linetext[] = $fieldtext;
			}
			if (!empty($subselect['display_inline'])) continue;
			$subselect_text = implode($subselect['concat_rows'], $linetext);
			$prefix = $subselect['prefix'];
			if (!empty($subselect['count'])) {
				$count = count($sub_lines[$id]);
				if (strstr($prefix, '%d')) {
					$prefix = sprintf($subselect['prefix'], $count);
				} else {
					$prefix .= $count.' ';
				}

			}
			if ($subselect_text) {
				$subselect_text = $prefix.$subselect_text.$subselect['suffix'];
			}
			if (!empty($subselect['list_format'])) {
				$subselect_text = zz_list_format($subselect_text, $subselect['list_format']);
			}
			$lines[$no][$subselect['table_name']] = zz_mark_search_string(
				$subselect_text, $subselect['table_name'], $subselect
			);
		}
	}
	return zz_return([$lines, $extra]);
}

/**
 * sets level for a field where a hierarchy of records shall be displayed in
 *
 * @param array $list
 * @param array $field
 * @param array $line
 * @return string level or ''
 */
function zz_list_field_level($list, $field, $line) {
	if (!isset($line['zz_level'])) return '';

	if (!empty($field['decrease_level'])) $line['zz_level'] -= $field['decrease_level'];

	if (!empty($field['field_name']) // occurs in case of subtables
		AND $field['field_name'] === $list['hierarchy']['display_in']) {
		return $line['zz_level'];
	} elseif (!empty($field['table_name']) 
		AND $field['table_name'] === $list['hierarchy']['display_in']) {
		return $line['zz_level'];
	}
	return '';
}

/**
 * check depending on grouping whether a field will be shown or not
 *
 * @param array $table_defs
 * @param array $list
 * @return array $table_defs ('show_field' set for each field)
 */
function zz_list_show_group_fields($table_defs, &$list) {
	if (!$list['display']) return $table_defs;

	$show_field = true;
	foreach ($table_defs[0] as $index => $field) {
		if ($list['group']) {
			$show_field_group = zz_list_group_field_no($list, $field, $index);
			if ($show_field AND !$show_field_group) $show_field = false;
		}
		// show or hide field
		foreach (array_keys($table_defs) as $row) { // each line seperately
			if (!empty($table_defs[$row][$index])) // only if field exists
				$table_defs[$row][$index]['show_field'] = $show_field;
		}
		if (!empty($field['list_append_next'])) $show_field = false;
		else $show_field = true;
	}
	return $table_defs;
}

/**
 * sets attributes class, th and th_nohtml for table head
 * note: for export, all columns have to be returned
 *
 * @param array $head
 * @param array $columns (list of columns that should appear)
 * @return array
 */
function zz_list_head($old_head, $columns) {
	$j = 0;

	$continue_next = false;
	$head = [];
	foreach ($old_head as $index => $field) {
		// ignore empty columns (e. g. empty through conditional where)
		if (empty($columns[$index])) continue;
		$col_index = $index;
		// analogous to zz_list_data():
		while (!empty($old_head[$col_index]['list_append_next'])) {
			$col_index++;
		}
		if (!isset($head[$col_index])) $head[$col_index] = $field;
		$head[$col_index]['th_nohtml'] = zz_list_th($field, 'nohtml');
		if ($field['show_field']) {
			$j = $col_index;
			$head[$j]['class'] = zz_field_class($field);
			$head[$j]['th'] = zz_list_th($field);
		} elseif (!empty($field['list_append_show_title'])) {
			// Add to previous field
			$head[$j]['class'] = array_merge($head[$j]['class'], zz_field_class($field));
			$head[$j]['th'] .= ' / '.zz_list_th($field);
			$head[$j]['th_nohtml'] .= ' / '.zz_list_th($field, 'nohtml');
		}
	}
	return $head;
}

/**
 * sets class attribute if necessary
 * 
 * @param array $field
 * @param bool $html (optional; true: output of HTML attribute)
 * @return mixed array $class list of strings with class names /
 *		string HTML output class="..."
 */
function zz_field_class($field, $html = false) {
	static $append = 0;
	$class = [];

	if (!empty($field['list_append_next'])) $append = 2;
	if (!empty($field['level']))
		$class[] = 'level'.$field['level'];
	if ($field['type'] === 'id' && empty($field['show_id']))
		$class[] = 'recordid';
	elseif (in_array($field['type'], ['number', 'calculated', 'sequence', 'date'])
		AND !$append)
		$class[] = 'number';
	if (!empty($_GET['order']) AND empty($field['dont_sort'])) {
		if (!empty($field['field_name']) AND $field['field_name'] === $_GET['order'])
			$class[] = 'order';
		elseif (!empty($field['display_field']) AND $field['display_field'] === $_GET['order']) 
			$class[] = 'order';
		elseif (!empty($field['row_value']) AND $field['row_value'] === $_GET['order'])
			$class[] = 'order';
	}
	if (!empty($field['class'])) {
		// we may go through this twice
		$class = array_merge($class, $field['class']);
	}
	// array_keys(array_flip()) is reported to be faster than array_unique()
	$class = array_keys(array_flip($class));
	
	$append--;
	if ($append < 0) $append = 0;
	if (!$html) return $class;
	if (!$class) return false;
	return ' class="'.implode(' ', $class).'"';
}

/**
 * outputs data in table format
 *
 * @param array $list
 * @param array $rows
 * @param array $head
 * @return string
 */
function zz_list_table($list, $rows, $head) {
	// Header
	$output = '<div class="list"><table class="data"><thead>'."\n".'<tr>';
	if ($list['select_multiple_records']) $output .= '<th></th>';

	// Rest cannot be set yet because we do not now details/mode-links
	// of individual records
	$columns = 0;
	foreach ($head as $no => $col) {
		if (!$col['show_field']) continue;
		if ($col['class']) $col['class'] = ' class="'.implode(' ', $col['class']).'"';
		else $col['class'] = '';
		$output .= '<th'.$col['class'].'>'.$col['th'].'</th>';
		$columns++;
	}
	if ($list['modes'] OR $list['details']) {
		$output .= ' <th class="editbutton">'.wrap_text('Action').'</th>';
		$columns++;
	}
	$output .= '</tr></thead>'."\n";

	//
	// Table footer
	//
	if (($list['tfoot'] AND $list['sum'])
		OR $list['select_multiple_records']) {
		$output .= '<tfoot>'."\n";
		if ($list['sum']) {
			$output .= '<tr class="sum">';
			$output .= zz_field_sum($head, count($rows), $list['sum'], $list);
			if ($list['modes'] OR $list['details'])
				$output .= '<td class="editbutton">&nbsp;</td>';
			$output .= '</tr>'."\n";
		}
		if ($list['buttons']) {
			$output .= '<tr class="multiple"><td>'
			.'<input type="checkbox" onclick="zz_set_checkboxes(this.checked);"></td>'
			.'<td colspan="'.$columns.'"><em>'.wrap_text('Selection').':</em> '
			.$list['buttons']
			.'</td></tr>';
		}
		$output .= '</tfoot>'."\n";
	}

	$output .= '<tbody>'."\n";
	$rowgroup = false;
	foreach ($rows as $index => $row) {
		if ($list['group'] AND $row['group'] != $rowgroup) {
			if ($rowgroup) {
				$my_groups = $rowgroup;
				$my_old_groups = $row['group'];
				while ($my_groups) {
					if ($list['tfoot'])
						$output .= zz_list_group_foot($my_groups, $head, count($rows), $list);
					array_pop($my_groups);
					array_pop($my_old_groups);
					if ($my_groups == $my_old_groups) break;
				}
				$output .= '</tbody><tbody>'."\n";
			}
			$output .= '<tr class="group"><td colspan="'.(count($row)-1)
				.'">'.sprintf(wrap_setting('zzform_group_html_table'), zz_list_group_titles_out($list['group_titles'][$index]))
				.'</td></tr>'."\n";
			$rowgroup = $row['group'];
		}
		$current_field = false;
		if (isset($list['current_record']) AND $list['current_record'] == $index) {
			$current_field = true;
		} elseif (isset($list['current_records']) AND in_array($index, $list['current_records'])) {
			$current_field = true;
		}
		$output .= '<tr class="'.($index & 1 ? 'uneven':'even')
			.(($index + 1) === count($rows) ? ' last' : '')
			.($current_field ? ' current_record' : '')
			.'">'; //onclick="Highlight();"
		foreach ($row as $fieldindex => $field) {
			if (!is_numeric($fieldindex)) continue;
			$output .= '<td'
				.($field['class'] ? ' class="'.implode(' ', array_unique($field['class'])).'"' : '')
				.'>'.$field['text'].'</td>';
		}
		if (!empty($row['modes']) OR !empty($row['details'])) {
			$output .= '<td class="editbutton">';
			if (!empty($row['modes'])) {
				$output .= $row['modes'];
				if (!empty($row['details'])) $output .= '&nbsp;<span class="br">||</span> ';
			}
			if (!empty($row['details']))
				$output .= $row['details'];
			$output .= '</td>';
		}
		$output .= '</tr>'."\n";
	}
	if ($list['tfoot'] AND $rowgroup) {
		$my_groups = $rowgroup;
		while ($my_groups) {
			$output .= zz_list_group_foot($my_groups, $head, count($rows), $list);
			array_pop($my_groups);
		}
	}
	$output .= "</tbody>\n</table></div>\n";
	return $output;
}

/**
 * remove empty columns from head and rows
 *
 * @param array $rows
 * @param array $head
 * @param array $zz
 * @param array $list
 * @return array
 */
function zz_list_remove_empty_cols($rows, $head, $zz, $list) {
	// Check for empty columns
	$column_content = [];
	$hidden_columns = [];
	foreach ($rows as $row) {
		foreach ($row as $no => $col) {
			if (!is_numeric($no)) continue;
			if (!array_key_exists($no, $column_content)) $column_content[$no] = false;
			if ($col['text']) $column_content[$no] = true;
		}
	}

	// remove empty column head
	$last_empty_cols = [];
	foreach ($head as $no => $col) {
		if ($list['hide_columns_if_empty']) $col['hide_in_list_if_empty'] = true;
		if (empty($column_content[$no]) AND !empty($col['hide_in_list_if_empty'])) {
			if (empty($col['list_append_next'])) {
				unset($head[$no]); // for zz_field_sum()
				$hidden_columns[$no] = true;
				foreach ($last_empty_cols as $last_no) {
					unset($head[$last_no]);
					$hidden_columns[$last_no] = true;
				}
				$last_empty_cols = [];
			} else {
				// save column for later removal if appended column is empty, too
				$last_empty_cols[] = $no;
			}
		} else {
			// reset last columns if there's a column with content inbetween
			$last_empty_cols = [];
		}
	}

	// remove empty column rows
	foreach ($rows as $index => $row) {
		foreach ($row as $fieldindex => $field) {
			if (!empty($hidden_columns[$fieldindex])) unset($rows[$index][$fieldindex]);
		}
	}
	return [$rows, $head];
}

/**
 * outputs data in ul format
 *
 * @param array $list
 * @param array $rows
 * @global array $zz_conf
 * @return string
 */
function zz_list_ul($list, $rows) {
	global $zz_conf;
	$output = '';
	if (!$list['group']) {
		$output .= '<ul class="data">'."\n";
	}
	$rowgroup = false;
	foreach ($rows as $index => $row) {
		if ($list['group'] AND $row['group'] != $rowgroup) {
			if ($rowgroup) {
				$output .= "</ul>\n";
			}
			$output .= sprintf(
				"\n<h2>%s</h2>\n<ul class='data'>\n",
				zz_list_group_titles_out($list['group_titles'][$index])
			);
			$rowgroup = $row['group'];
		}
		$output .= '<li';
		if (!empty($list['dnd']))
			$output .= ' draggable="true"';
		$output .= ' class="'.($index & 1 ? 'uneven':'even')
			.($list['current_record'] === $index ? ' current_record' : '')
			.(($index + 1) === count($rows) ? ' last' : '').'"';
		if ($list['dnd']) 
			$output .= ' data-sequence="'.$row['sequence'].'" data-id="'.$row['dnd_id'].'"';
		$output .= '>'; //onclick="Highlight();"
		foreach ($row as $fieldindex => $field) {
			if (is_numeric($fieldindex) && $field['text'])
				$output .= '<p'.($field['class'] ? ' class="'.implode(' ', $field['class']).'"' : '')
					.'>'.$field['text'].'</p>';
		}
		if (!empty($row['modes']))
			$output .= '<p class="editbutton">'.$row['modes'].'</p>';
		if (!empty($row['details']))
			$output .= '<p class="editbutton">'.$row['details'].'</p>';
		$output .= '</li>'."\n";
	}
	$output .= "</ul>\n";

	if ($list['buttons']) {
		$output .= '<p class="multiple"><input type="checkbox" onclick="zz_set_checkboxes(this.checked);">'
		.' <em>'.wrap_text('Selection').':</em> '.$list['buttons'].'</p>';
	}
	$list['dnd_start'] = $zz_conf['int']['this_limit'] - wrap_setting('zzform_limit');
	if (!empty($list['dnd'])) {
		$output .= '<script>
			var zz_dnd_id_field = "'.$list['dnd_id_field'].'";
			var zz_dnd_sequence_field = "'.$list['dnd_sequence_field'].'";
			var zz_dnd_target_url = "'.$list['dnd_target_url'].'";
			var zz_dnd_dnd_start = "'.$list['dnd_start'].'";
		</script>';
		$output .= '<script src="'.wrap_setting('behaviour_path').'/zzform/drag.js"></script>';
	}
	return $output;
}

/**
 * get image path via syndication 
 * (from zzwrap module)
 *
 * @param array $field
 * @param array $line
 * @return string
 */
function zz_list_syndication_get($field, $line) {
	require_once wrap_setting('core').'/syndication.inc.php';

	$img = zz_makelink($field['path_json_request'], $line);
	$img = wrap_syndication_get($img);
	if (!$img) return false;
	$text = '<img src="'.($field['path_json_base'] ?? '').$img.'"  alt="" class="thumb">';
	return $text;
}

/**
 * replace keyword limit=last with the correct numeric value 
 *
 * @param int $total_rows
 * @return void
 */
function zz_list_limit_last($total_rows) {
	global $zz_conf;
	if (empty($zz_conf['int']['limit_last'])) return;
	if ($total_rows <= $zz_conf['int']['this_limit']) return;
	$zz_conf['int']['this_limit'] = (ceil($total_rows / wrap_setting('zzform_limit')) * wrap_setting('zzform_limit'));
}
