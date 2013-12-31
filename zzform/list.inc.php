<?php

/**
 * zzform
 * Display all or a subset of all records in a list (e. g. table, ul)
 *
 * Part of �Zugzwang Project�
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright � 2004-2013 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * shows records in list view
 * 
 * displays table with records (limited by zz_conf['limit'])
 * displays add new record, record navigation (if zz_conf['limit'] = true)
 * and search form below table
 * @param array $zz				table and field definition
 * @param array $ops			operation variables
 * @param array $zz_var			Main variables
 * @param array $zz_conditions	configuration variables
 * @global array $zz_error		errorhandling
 * @global array $zz_conf		Main conifguration parameters, will be modified
 * @return array
 *		array $ops
 *		array $zz_var
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_list($zz, $ops, $zz_var, $zz_conditions) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	global $zz_error;
	$zz_conf['int']['no_add_button_so_far'] = true;

	if ($zz_conf['search']) {
		if (file_exists($zz_conf['dir_inc'].'/search.inc.php')) {
			require_once $zz_conf['dir_inc'].'/search.inc.php';
		} else {
			$zz_error[] = array('msg_dev' => 'Search module was not found.');
			$zz_conf['search'] = false;
		}
	}

	// Turn off hierarchical sorting when using search
	// @todo: implement hierarchical view even when using search
	if (!isset($zz['list']['hierarchy'])) {
		$zz['list']['hierarchy'] = array();
	} elseif (!empty($_GET['q']) AND $zz_conf['search'] AND $zz['list']['hierarchy']) {
		$zz['list']['hierarchy'] = array();
	}

	// only if search is allowed and there is something
	// if q modify $zz['sql']: add search query
	if (!empty($_GET['q']) AND $zz_conf['search']) {
		$old_sql = $zz['sql'];
		$zz['sql'] = zz_search_sql($zz['fields_in_list'], $zz['sql'], $zz['table'], $zz_var['id']['field_name']);
		if ($old_sql !== $zz['sql']) $zz['sqlcount'] = '';
	}

	$id_field = $zz_var['id']['field_name'];

	if ($zz_conf['list_access']) {
		$zz_conf = array_merge($zz_conf, $zz_conf['list_access']);
		unset($zz_conf['list_access']);
	}
	if ($zz_conf['access'] == 'search_but_no_list' AND empty($_GET['q'])) 
		$zz_conf['show_list'] = false;

	// SQL query without limit and filter for conditions etc.!
	$zz['sql_without_limit'] = $zz['sql'];

	// Filters
	// set 'selection', $zz['list']['hierarchy']
	$zz = zz_apply_filter($zz);
	// modify SQL query depending on filter
	$old_sql = $zz['sql'];
	$zz['sql'] = zz_list_filter_sql($zz['sql']);
	if ($old_sql !== $zz['sql']) $zz['sqlcount'] = '';
	$ops['output'] .= zz_filter_selection($zz_conf['filter'], 'top');
	if ($ops['mode'] != 'add' AND empty($zz_conf['no_add_above'])) {
		$ops['output'] .= zz_output_add_links($zz_var['extraGET']);
	}
	if (!$zz['sql']) return zz_return(array($ops, $zz_var));

	list($lines, $ops['records_total']) = zz_list_query($zz, $id_field);
	// save total rows in zz_var for use in zz_nice_title()
	$zz_var['limit_total_rows'] = $ops['records_total'];
	if ($zz_error['error']) return zz_return(array($ops, $zz_var));
	$count_rows = count($lines);

	// don't show anything if there is nothing
	if (!$count_rows) {
		$zz_conf['show_list'] = false;
		if ($text = zz_text('table-empty')) {
			$ops['output'].= '<p>'.$text.'</p>';
		}
		if ($ops['mode'] === 'export') {
			// return 404 not found page (HTML, no export format)
			// because there is no content
			// @todo: output the same page with links, filters etc.
			// as if no export were selected
			$zz_conf['int']['http_status'] = 404;
			$ops['mode'] = false;
			unset($ops['headers']);
			return zz_return(array($ops, $zz_var));
		} elseif ($ops['records_total']) {
			// 404 if limit is too large
			$zz_conf['int']['http_status'] = 404;
		}
	}

	// zz_fill_out must be outside if show_list, because it is necessary for
	// search results with no resulting records
	// fill_out, but do not unset conditions
	$zz['fields_in_list'] = zz_fill_out($zz['fields_in_list'], $zz_conf['db_name'].'.'.$zz['table'], 1); 

	//
	// Table definition, data and head
	//

	if ($zz_conf['show_list']) {
		// Check all conditions whether they are true;
		if (!empty($zz_conf['modules']['conditions'])) {
			$zz_conditions = zz_conditions_list_check($zz, $zz_conditions, $id_field, array_keys($lines));
			if ($zz_error['error']) return zz_return(array($ops, $zz_var));
		}

		// add 0 as a dummy record for which no conditions will be set
		// reindex $linex from 1 ... n
		array_unshift($lines, '0');
		list($table_defs, $zz['fields_in_list']) = zz_list_defs(
			$lines, $zz_conditions, $zz['fields_in_list'], $zz['table'], $id_field, $ops['mode']
		);
		// remove first dummy array
		unset($lines[0]);
		if ($zz_conf['modules']['debug']) zz_debug('list definitions set');

		$list = zz_list_set($zz);

		// mark fields as 'show_field' corresponding to grouping
		$table_defs = zz_list_show_group_fields($table_defs, $list);

		list($rows, $list) = zz_list_data(
			$list, $lines, $table_defs, $zz_var, $zz_conditions, $zz['table'], $ops['mode']
		);
		unset($lines);

		$list['where_values'] = !empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : '';
		$head = zz_list_head($table_defs[0], $list['where_values'], $list['columns']);
		unset($table_defs);
	}

	//
	// Export
	//

	if ($ops['mode'] === 'export') {
		if ($zz_conf['show_list']) {
			// add empty column from heads in rows as well (for export)
			foreach ($rows as $row_index => $row) {
				foreach (array_keys($head) as $col_index) {
					if (!isset($row[$col_index])) {
						$rows[$row_index][$col_index] = array(
							'value' => '', 'class' => array(), 'text' => ''
						);
					}
				}
				ksort($rows[$row_index]);
			}
			$ops['output'] = array(
				'head' => $head,
				'rows' => $rows
			);
		}
		if ($zz_conf['modules']['debug']) zz_debug('end');
		$ops = zz_export($ops, $zz);
		return zz_return(array($ops, $zz_var));
	}
	
	//
	// Table head, table foot, list body, closing list
	//

	zz_error();
	$ops['output'] .= zz_error_output();

	if ($zz_conf['search']) {
		$search_form = zz_search_form($zz['fields_in_list'], $zz['table'], $ops['records_total'], $count_rows);
		$ops['output'] .= $search_form['top'];
	}
	
	if ($zz_conf['show_list']) {
		if ($zz_conf['select_multiple_records']) {
			$action_url = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'];
			if ($zz_var['extraGET']) {
				// without first &amp;!
				$action_url .= $zz_conf['int']['url']['?&'].substr($zz_var['extraGET'], 5);
			}
			$ops['output'] .= sprintf('<form action="%s" method="POST" accept-charset="%s">'."\n",
				$action_url, $zz_conf['character_set']);
		}
	
		if ($zz_conf['list_display'] == 'table') {
			$ops['output'] .= zz_list_table($list, $rows, $head);
		} elseif ($zz_conf['list_display'] == 'ul') {
			$ops['output'] .= zz_list_ul($list, $rows);
		}

		if ($zz_conf['select_multiple_records']) {
			$ops['output'] .= '</form>'."\n";
		}
	}

	//
	// Buttons below table (add, record nav, search)
	//

	// Add new record
	if (!($zz_conf['access'] == 'search_but_no_list' AND empty($_GET['q']))) {
		// filter, if there was a list
		if ($zz_conf['show_list']) {
			$ops['output'] .= zz_filter_selection($zz_conf['filter'], 'bottom');
		}
		$toolsline = array();
		$base_url = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
			.$zz_conf['int']['url']['?&'];

		// normal add button, only if list was shown beforehands
		if ($ops['mode'] != 'add' && $zz_conf['add_link'] AND !is_array($zz_conf['add']) && $zz_conf['show_list']) {
			$zz_conf['int']['no_add_button_so_far'] = false;
			$toolsline[] = '<a accesskey="n" href="'.$base_url.'mode=add'
				.$zz_var['extraGET'].'">'.zz_text('Add new record').'</a>';
		}
		// multi-add-button, also show if there was no list, because it will only be shown below records!
		
		if ($ops['mode'] != 'add' && $zz_conf['add_link'] AND is_array($zz_conf['add'])) {
			ksort($zz_conf['add']); // if some 'add' was unset before, here we get new numerical keys
			$ops['output'] .= '<p class="add-new">'.zz_text('Add new record').': ';
			$zz_conf['int']['no_add_button_so_far'] = false;
			foreach ($zz_conf['add'] as $i => $add) {
				if ($add['value']) {
					$value = '&amp;add['.$add['field_name'].']='.$add['value'];
				} else {
					$value = '';
				}
				$ops['output'] .= '<a href="'.$base_url
					.'mode=add'.$zz_var['extraGET'].$value.'"'
					.(!empty($add['title']) ? ' title="'.$add['title'].'"' : '')
					.'>'.$add['type'].'</a>'
					.(!empty($add['explanation']) ? ' ('.$add['explanation'].')' : '');
				if ($i != count($zz_conf['add']) -1) $ops['output'] .= ' | ';
			}
			$ops['output'] .= '</p>'."\n";
		}

		if ($zz_conf['export'] AND $ops['records_total']) 
			$toolsline = array_merge($toolsline, zz_export_links($base_url, $zz_var['extraGET']));
		if ($toolsline)
			$ops['output'] .= '<p class="add-new bottom-add-new">'.implode(' | ', $toolsline).'</p>';
		$ops['output'] .= zz_list_total_records($ops['records_total']);
		$ops['output'] .= zz_list_pages($zz_conf['limit'], $zz_conf['int']['this_limit'], $ops['records_total']);	
		// @todo: NEXT, PREV Links at the end of the page
		// Search form
		if ($zz_conf['search']) {
			$ops['output'] .= $search_form['bottom'];
		}
	}
	return zz_return(array($ops, $zz_var));
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
 * @param string $id_field ($zz_var['id']['field_name'])
 * @param string $mode ($ops['mode'])
 * @return array
 *		- array $table_defs
 *		- array $fields_in_list
 */
function zz_list_defs($lines, $zz_conditions, $fields_in_list, $table, $id_field, $mode) {
	global $zz_conf;

	$conditions_applied = false; // check if there are any conditions
	foreach ($lines as $index => $line) {
		$line_defs[$index] = $fields_in_list;
		// conditions
		if (empty($zz_conf['modules']['conditions'])) continue;
		if (!$index) {
			// only apply conditions to list head if condition
			// is valid for all records (true instead of array of ids)
			$my_bool_conditions = array();
			foreach ($zz_conditions['bool'] as $condition => $ids) {
				if ($ids === true) $my_bool_conditions[$condition] = true;
			}
			if (!$my_bool_conditions) continue;
		} else {
			$my_bool_conditions = $zz_conditions['bool'];
		}
		foreach (array_keys($line_defs[$index]) as $fieldindex) {
			if (!isset($line[$id_field])) continue;
			$applied = zz_conditions_merge_field(
				$line_defs[$index][$fieldindex], $my_bool_conditions, $line[$id_field]
			);
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
	$old_to_new_index = array();
	foreach (array_keys($lines) as $index) {
		if (empty($line_defs[$index])) continue;
		if ($zz_conf['modules']['debug']) zz_debug('fill_out start');
		$line_defs[$index] = zz_fill_out($line_defs[$index], $zz_conf['db_name'].'.'.$table, 2);
		if ($zz_conf['modules']['debug']) zz_debug('fill_out end');
		foreach ($line_defs[$index] as $fieldindex => $field) {
			if (in_array($fieldindex, array_keys($old_to_new_index))) {
				$fi = $old_to_new_index[$fieldindex];
			} else {
				$fi = count($old_to_new_index);
				$old_to_new_index[$fieldindex] = $fi;
			}
			// remove elements from table which shall not be shown
			if ($mode == 'export') {
				if (!isset($field['export']) || $field['export']) {
					$table_defs[$index][$fi] = $field;
				}
			} else {
				if (empty($field['hide_in_list'])) {
					$table_defs[$index][$fi] = $field;
				}
			}
		}
		if ($zz_conf['modules']['debug']) zz_debug('table_query end');
	}
	unset($old_to_new_index);
	// now we have the basic stuff in $table_defs[0] and $line_defs[0]
	// if there are conditions, $table_defs[1], [2], [3]... and
	// $line_defs[1], [2], [3] ... are set
	$fields_in_list = $line_defs[0]; // for search form
	unset($line_defs);
	return array($table_defs, $fields_in_list);
}

/**
 * set default values for $list with some data
 *
 * @param array $zz => $zz['list'] will be used as default
 * @return array
 *		int 'current_record'
 *		array 'sum'
 *		array 'sum_group'
 *		bool 'modes'
 *		bool 'details'
 *		bool 'tfoot'
 */
function zz_list_set($zz) {
	global $zz_conf;

	$list = !empty($zz['list']) ? $zz['list'] : array();
	// defaults, might be overwritten by $zz['list']
	$list = array_merge(array(
		'current_record' => NULL,
		'sum' => array(),
		'sum_group' => array(),
		'modes' => false, // don't show a table head for link to modes until necessary
		'details' => false, // don't show a table head for link to details until necessary
		'tfoot' => false, // shows table foot, e. g. for sums of individual values
		'group' => array(),
		'hierarchy' => array('display_in' => '')
	), $list);

	// check 'group'
	if (!empty($_GET['group'])) {
		foreach ($zz['fields_in_list'] as $field) {
			if ((isset($field['display_field']) && $field['display_field'] === $_GET['group'])
				OR (isset($field['field_name']) && $field['field_name'] === $_GET['group'])
			) {
				if (isset($field['order'])) $list['group'] = $field['order'];
				else $list['group'] = $_GET['group'];
			}
		}
	}
	
	// allow $list['group'] to be a string
	if (!is_array($list['group']) AND $list['group'])
		$list['group'] = array($list['group']);
	// initialize internal group_field_no
	$zz_conf['int']['group_field_no'] = array();

	return $list;
}

/**
 * prepare data for list view
 *
 * @param array $list
 * @param array $lines
 * @param array $table_defs
 * @param array $zz_var
 * @param array $zz_conditions
 * @param string $table ($zz['table'])
 * @param string $mode ($ops['mode'])
 * @global array $zz_conf
 * @global array $zz_error
 * @return array
 *	- array $rows data organized in rows
 *	- array $list with some additional information on how to output list
 */
function zz_list_data($list, $lines, $table_defs, $zz_var, $zz_conditions, $table, $mode) {
	global $zz_conf;
	global $zz_error;
	
	$rows = array();
	$subselects = array();
	$ids = array();
	$z = 0;
	//$group_hierarchy = false; // see below, hierarchical grouping
	$lastline = false;
	$id_field = $zz_var['id']['field_name'];

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
		if (!empty($zz_conf['modules']['conditions'])) {
			zz_conditions_merge_field($field, $zz_conditions['bool'], $line[$id_field]);
		}
		if ($field['type'] !== 'subtable') continue;
		if (empty($field['subselect']['sql'])) continue;

		$subselect = zz_list_init_subselects($field, $fieldindex, $id_field);
		if ($subselect) $subselects[] = $subselect;
		if (empty($line[$subselect['key_fieldname']])) {
			$zz_error[] = array(
				'msg_dev' => 'Wrong key_field_name. Please set $zz_sub["fields"]['
				.'n]["key_field_name"] to something different: '.implode(', ', array_keys($line)));
		}
	}
	
	$lines = zz_list_get_subselects($lines, $subselects);
	
	// put lines in new array, rows.
	// $rows[$z][0]['text'] = '';
	// $rows[$z][0]['class'] = array();
	foreach ($lines as $index => $line) {
		$id = $line[$id_field];
		if ($id == $zz_var['id']['value']) {
			$list['current_record'] = $z;
		} elseif (!empty($zz_var['id']['values'])) {
			if (in_array($id, $zz_var['id']['values'])) {
				$list['current_records'][] = $z; 
			}
		}
		$def_index = (count($table_defs) > 1) ? $index : 0;
		$rows[$z]['group'] = zz_list_group_titles($list, $table_defs[$def_index], $line);
		// configuration variables just for this line
		$zz_conf_record = zz_record_conf($zz_conf);
		if (!empty($line['zz_conf'])) {
			// check whether there are different configuration variables 
			// e. g. for hierarchies
			$zz_conf_record = array_merge($zz_conf_record, $line['zz_conf']);
		}
		if ($zz_conf['select_multiple_records']) {
			// checkbox for records
			$checked = false;
			if (!empty($zz_var['id']['values'])) {
				if (in_array($id, $zz_var['id']['values'])) $checked = true;
			}
			$rows[$z][-1]['text'] = '<input type="checkbox" name="zz_record_id[]" value="'
				.$line[$id_field].'"'.($checked ? ' checked="checked"' : '').'>'; // $id
			$rows[$z][-1]['class'][] = 'select_multiple_records';
		}

		foreach ($table_defs[$def_index] as $fieldindex => $field) {
			if ($zz_conf['modules']['debug']) zz_debug("table_query foreach ".$fieldindex);
			// conditions
			if (!empty($zz_conf['modules']['conditions'])) {
				zz_conditions_merge_field($field, $zz_conditions['bool'], $line[$id_field]);
				if (!empty($zz_conf_record['if']) OR !empty($zz_conf_record['unless'])) {
					zz_conditions_merge_conf($zz_conf_record, $zz_conditions['bool'], $line[$id_field]);
					$zz_conf_record = zz_listandrecord_access($zz_conf_record);
					if (!isset($zz_conf_record['add_link']))
						// Link Add new ...
						$zz_conf_record['add_link'] = $zz_conf_record['add'] ? true : false; 
					// $zz_conf is set regarding add, edit, delete
					if (!$zz_conf['add']) $zz_conf['copy'] = false;			// don't copy record (form+links)
				}
			}
			if ($zz_conf['modules']['debug']) {
				zz_debug('table_query foreach cond set '.$fieldindex);
			}
			
			// check all fields next to each other with list_append_next					
			while (!empty($table_defs[$def_index][$fieldindex]['list_append_next'])) {
				$list['columns'][$fieldindex] = true;
				$keys = array_keys($table_defs[$def_index]);
				$current_key_index = array_search($fieldindex, $keys);
				$next_key_index = $current_key_index + 1;
				if (isset($keys[$next_key_index])) $fieldindex = $keys[$next_key_index];
				else break;
			}

			if ($zz_conf['modules']['debug']) {
				zz_debug('table_query before switch '.$fieldindex.'-'.$field['type']);
			}
			$my_row = isset($rows[$z][$fieldindex]) ? $rows[$z][$fieldindex] : array();
			$rows[$z][$fieldindex] = zz_list_field(
				$list, $my_row, $field, $line, $lastline, $zz_var, $table, $mode, $zz_conf_record
			);
			$list['columns'][$fieldindex] = true;

			// Sums
			$list = zz_list_sum($field, $list, $rows[$z][$fieldindex]['value'], $rows[$z]['group']);

			// group: go through everything but don't show it in list
			// @todo: check that it does not collide with append_next
			if ($list['group']) {
				$pos = array_search($fieldindex, $zz_conf['int']['group_field_no']);
				if ($pos !== false) {
					unset($rows[$z][$fieldindex]);
					$list['group_titles'][$z][$pos] = implode(' &#8211; ', $rows[$z]['group']);
				}
			}
			if ($zz_conf['modules']['debug']) {
				zz_debug('table_query end '.$fieldindex.'-'.$field['type']);
			}
		}
		$lastline = $line;

		$rows[$z]['modes'] = zz_list_modes($id, $zz_var, $zz_conf_record);
		if ($rows[$z]['modes']) $list['modes'] = true; // need a table row for this

		if (!empty($zz_conf_record['details'])) {
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
	$previous_row = array();
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

	return array($rows, $list);
}

/**
 * gets values for titles for grouping
 *
 * @param array $list
 * @param array $fields zzform definition for fields of this line
 * @param array $line = current database record
 * @global array $zz_conf
 * @return array ($group)
 */
function zz_list_group_titles($list, $fields, $line) {
	global $zz_conf;
	$group = array();
	if (!$list['group']) return $group;

	$group_count = count($list['group']);
	foreach ($fields as $no => $field) {
	//	check for group function
		$pos = array_search($no, $zz_conf['int']['group_field_no']);
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
		} elseif (!empty($field['enum']) AND $field['type'] == 'select') {
			$group[$pos] = zz_print_enum($field, $line[$field['field_name']], 'full');
		} elseif (!empty($field['field_name'])) {
			$group[$pos] = $line[$field['field_name']];
		}
		// Formatting of fields
		// @todo use practically the same as in list display
		switch ($field['type']) {
			case 'date': $group[$pos] = zz_date_format($group[$pos]); break;
		}
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

function zz_list_group_titles_out($group_titles, $concat = ' &#8211; ') {
	// just show every title only once
	$group_titles = array_unique($group_titles);
	ksort($group_titles);
	$output = implode($concat, $group_titles);
	return $output;
}

/**
 * prints out a list of filters to click
 *
 * @param array $filter
 *	array index =>
 *		string 'title'
 *		string 'identifier'
 *		string 'where'
 *		array 'selection'
 *			id => title
 * @param string $pos = 'top', 'bottom', or 'both'
 * @global array $zz_conf
 *		$zz_conf['int']['url']
 * @return string HTML output, all filters
 */
function zz_filter_selection($filter, $pos) {
	global $zz_conf;

	if (!$filter) return '';
	if (!is_array($filter)) return '';
	if (!$zz_conf['show_list']) return '';
	if ($zz_conf['access'] === 'export') return '';
	if (!in_array($zz_conf['filter_position'], array($pos, 'both'))) return '';
	
	// create base URL for links
	$self = $zz_conf['int']['url']['self'];
	// remove unwanted keys from link
	// do not show edited record, limit
	$unwanted_keys = array('q', 'scope', 'limit', 'mode', 'id', 'add', 'filter',
		'zzaction', 'zzhash');
	$qs = zz_edit_query_string($zz_conf['int']['url']['qs']
		.$zz_conf['int']['url']['qs_zzform'], $unwanted_keys);

	$filter_output = false;
	foreach ($filter as $index => $f) {
		// remove this filter from query string
		$other_filters['filter'] = $zz_conf['int']['filter'];
		unset($other_filters['filter'][$f['identifier']]);
		if (!empty($f['subfilter'])) {
			// this filter has a subfilter
			// exclude subfilter from links as it will produce 404 errors
			// since the combinations are not possible
			foreach ($f['subfilter'] AS $subfilter) {
				// filter does exist?
				if (!isset($filter[$subfilter])) continue;
				unset($other_filters['filter'][$filter[$subfilter]['identifier']]);
			}
		}
		$qs = zz_edit_query_string($qs, array(), $other_filters);
		
		if (!empty($f['selection'])) {
			// $f['selection'] might be empty if there's no record in the database
			foreach ($f['selection'] as $id => $selection) {
				$is_selected = ((isset($zz_conf['int']['filter'][$f['identifier']]) 
					AND $zz_conf['int']['filter'][$f['identifier']] == $id))
					? true : false;
				if ($is_selected) {
					// active filter: don't show a link
					$link = false;
				} elseif (!empty($f['default_selection']) 
					AND ((is_array($f['default_selection']) AND key($f['default_selection']) == $id)
					OR $f['default_selection'] == $id)) {
					// default selection does not need parameter
					$link = $self.$qs;
				} else {
					// ID might be string as well, so better urlencode it
					$link = $self.($qs ? $qs.'&amp;' : '?').'filter['.$f['identifier'].']='.urlencode($id);
				}
				$filter[$index]['output'][] = array(
					'title' => $selection,
					'link' => $link
				);
				$filter_output = true;
			}
		} elseif (isset($zz_conf['int']['filter'][$f['identifier']])) {
			// no filter selections are shown, but there is a current filter, 
			// so show this
			$filter[$index]['output'][] = array(
				'title' => zz_htmltag_escape($zz_conf['int']['filter'][$f['identifier']]),
				'link' => false
			);
			$filter_output = false;
		} else {
			// nothing to output: like-filter, so don't display anything
			unset($filter[$index]);
			continue;
		}

		// create '- all -'-Link
		if (!empty($filter[$index]['hide_all_link'])) continue;
		if (empty($f['default_selection'])) {
			$link = $self.$qs;
		} else {
			// there is a default selection, so we need a parameter = 0!
			$link = $self.($qs ? $qs.'&amp;' : '?').'filter['.$f['identifier'].']=0';
		}
		$link_all = false;
		if (isset($zz_conf['int']['filter'][$f['identifier']])
			AND $zz_conf['int']['filter'][$f['identifier']] !== '0'
			AND $zz_conf['int']['filter'][$f['identifier']] !== 0) $link_all = true;
		if (!$link_all) $link = false;

		$filter[$index]['output'][] = array(
			'title' => '&#8211;&nbsp;'.zz_text('all').'&nbsp;&#8211;',
			'link' => $link,
			'class' => 'filter_all'
		);
	}
	if (!$filter_output) return false;

	// HTML output
	$output = '<div class="zzfilter">'."\n<dl>\n";
	foreach ($filter as $f) {
		$output .= '<dt>'.zz_text('Selection').' '.$f['title'].':</dt>';
		foreach ($f['output'] as $item) {
			$output .= '<dd'
				.(!empty($item['class']) ? ' class="'.$item['class'].'"' : '')
				.'>'
				.($item['link'] ? '<a href="'.$item['link'].'">' : '<strong>')
				.$item['title']
				.($item['link'] ? '</a>' : '</strong>')
				."</dd>\n";
		}
	}
	$output .= '</dl></div>'."\n";
	return $output;
}

/**
 * Apply filter to SQL query
 * test if all filters are valid filters
 *
 * @param string $sql
 * @global array $zz_conf
 * @global array $zz_error
 * @return string $sql
 * @see zz_filter_defaults() for check for invalid filters
 */
function zz_list_filter_sql($sql) {
	global $zz_conf;
	global $zz_error;

	// no filter was selected, no change
	if (!$zz_conf['int']['filter']) return $sql;

	foreach ($zz_conf['filter'] AS $filter) {
		if (!in_array($filter['identifier'], array_keys($zz_conf['int']['filter']))) continue;
		if (empty($filter['where'])) continue;
		if (!isset($filter['default_selection'])) $filter['default_selection'] = '';
		$old_sql = $sql;
		if (isset($filter['sql_join'])) {
			$sql = zz_edit_sql($sql, 'LEFT JOIN', $filter['sql_join']);
		}
		
		if ($filter['type'] === 'show_hierarchy'
			AND false !== zz_in_array_str($zz_conf['int']['filter'][$filter['identifier']], array_keys($filter['selection']))
		) {
			$filter_value = $zz_conf['int']['filter'][$filter['identifier']];
			$sql = zz_edit_sql($sql, 'WHERE', $filter['where'].' = "'.$filter_value.'"');
		} elseif (false !== zz_in_array_str($zz_conf['int']['filter'][$filter['identifier']], array_keys($filter['selection']))
			AND $filter['type'] === 'list') {
			// it's a valid filter, so apply it.
			$filter_value = $zz_conf['int']['filter'][$filter['identifier']];
			if ($filter_value == 'NULL') {
				$sql = zz_edit_sql($sql, 'WHERE', 'ISNULL('.$filter['where'].')');
			} elseif ($filter_value == '!NULL') {
				$sql = zz_edit_sql($sql, 'WHERE', '!ISNULL('.$filter['where'].')');
			} else {
				// allow ! as a symbol (may be escaped by \)
				// for !=
				$equals = ' = ';
				if (substr($filter_value, 0, 1) === '!') {
					$filter_value = substr($filter_value, 1);
					$equals = ' != ';
				} elseif (substr($filter_value, 0, 1) === '\\') {
					$filter_value = substr($filter_value, 1);
				}
				$sql = zz_edit_sql($sql, 'WHERE', $filter['where'].$equals.'"'.$filter_value.'"');
			}
		} elseif ($zz_conf['int']['filter'][$filter['identifier']] === '0' AND $filter['default_selection'] !== '0'
			AND $filter['default_selection'] !== 0) {
			// do nothing
		} elseif ($filter['type'] == 'list' AND is_array($filter['where'])) {
			// valid filter with several wheres
			$wheres = array();
			foreach ($filter['where'] AS $filter_where) {
				if ($zz_conf['int']['filter'][$filter['identifier']] == 'NULL') {
					$wheres[] = 'ISNULL('.$filter_where.')';
				} elseif ($zz_conf['int']['filter'][$filter['identifier']] == '!NULL') {
					$wheres[] = '!ISNULL('.$filter_where.')';
				} else {
					$wheres[] = $filter_where.' = "'.$zz_conf['int']['filter'][$filter['identifier']].'"';
				}
			}
			$sql = zz_edit_sql($sql, 'WHERE', implode(' OR ', $wheres));
		} elseif ($filter['type'] == 'like') {
			// valid filter with LIKE
			$sql = zz_edit_sql($sql, 'WHERE', $filter['where'].' LIKE "%'.$zz_conf['int']['filter'][$filter['identifier']].'%"');
		} else {
			// invalid filter value, show list without filter
			$sql = $old_sql;
			if (empty($filter['ignore_invalid_filters'])) {
				$zz_conf['int']['http_status'] = 404;
				$zz_error[] = array(
					'msg' => sprintf(zz_text('"%s" is not a valid value for the selection "%s". Please select a different filter.'), 
						zz_htmltag_escape($zz_conf['int']['filter'][$filter['identifier']]), $filter['title']),
					'level' => E_USER_NOTICE
				);
			}
			// remove invalid filter from query string
			unset($zz_conf['int']['filter'][$filter['identifier']]);
			// remove invalid filter from internal query string
			$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
				$zz_conf['int']['url']['qs_zzform'], sprintf('filter[%s]', $filter['identifier'])
			);
		}
	}

	// test filter identifiers if they exist
	foreach ($zz_conf['int']['invalid_filters'] AS $identifier) {
		$filter = zz_htmltag_escape($identifier);
		$link = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
			.$zz_conf['int']['url']['?&'].$zz_conf['int']['url']['qs_zzform'];
		$zz_error[] = array(
			'msg' => sprintf(zz_text('A filter for the selection "%s" does not exist.'), $filter)
				.' <a href="'.$link.'">'.sprintf(zz_text('List without this filter')).'</a>',
			'level' => E_USER_NOTICE
		);
		$sql = false;
	}
	return $sql;
}

/**
 * Query records for list view
 *
 * @param array $zz
 * 		string 'sql' SQL query ($zz['sql'])
 *		string 'sqlorder'
 * 		string 'table' name of database table ($zz['table'])
 * 		array 'fields_in_list' list of fields ($zz['fields_in_list'])
 * @param string $id_field ($zz_var['id']['field_name'])
 * @global array $zz_conf
 * @return array (array $lines, int $total_rows)
 */
function zz_list_query($zz, $id_field) {
	global $zz_conf;

	if (!isset($zz['sqlextra'])) $zz['sqlextra'] = array();
	if (!empty($zz['sqlcount'])) {
		$total_rows = zz_sql_count_rows($zz['sqlcount']);
	} else {
		$total_rows = zz_sql_count_rows($zz['sql'], $zz['table'].'.'.$id_field);
	}
	if (!$total_rows) return array(array(), 0);
	
	// ORDER must be here because of where-clause
	$zz['sql'] .= !empty($zz['sqlorder']) ? ' '.$zz['sqlorder'] : '';
	// Alter SQL query if GET order (AND maybe GET dir) are set
	$zz['sql'] = zz_sql_order($zz['fields_in_list'], $zz['sql']);

	if (empty($zz['list']['hierarchy'])) {
		return array(zz_list_query_flat($zz['sql'], $id_field, $zz['sqlextra']), $total_rows);
	} else {
		return zz_list_query_hierarchy($zz, $id_field);
	}
}

/**
 * Query records for list view, flat mode
 *
 * @param string $sql SQL query ($zz['sql'])
 * @param string $id_field ($zz_var['id']['field_name'])
 * @global array $zz_conf
 * @return array $lines
 */
function zz_list_query_flat($sql, $id_field, $extra_sqls) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	if ($zz_conf['int']['this_limit']) { 
		// set a standard value for limit
		// this standard value will only be used on rare occasions, when NO limit is set
		// but someone tries to set a limit via URL-parameter
		if (!$zz_conf['limit']) $zz_conf['limit'] = 20; 
		$sql .= ' LIMIT '.($zz_conf['int']['this_limit']-$zz_conf['limit']).', '.($zz_conf['limit']);
	}

	// read rows from database
	$lines = zz_db_fetch($sql, $id_field);
	$lines = zz_list_query_extras($lines, $id_field, $extra_sqls);
	return zz_return($lines);
}

/**
 * Query extra fields
 *
 * @param array $lines
 * @param string $id_field
 * @param array $extra_sqls
 * @return array $lines
 */
function zz_list_query_extras($lines, $id_field, $extra_sqls) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	if (!$extra_sqls) return $lines;
	foreach ($extra_sqls as $sql) {
		$sql = sprintf($sql, implode(',', array_keys($lines)));
		$extras = zz_db_fetch($sql, $id_field);
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
 * @param string $id_field ($zz_var['id']['field_name'])
 * @global array $zz_conf
 * @return array $lines
 */
function zz_list_query_hierarchy($zz, $id_field) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	// hierarchical list view
	// for performance reasons, we only get the fields which are important
	// for the hierarchy (we need to get all records)
	$lines = zz_db_fetch($zz['sql'], array($id_field, $zz['list']['hierarchy']['mother_id_field_name']), 'key/value'); 
	if (!$lines) return zz_return(array(array(), 0));

	$h_lines = array();
	foreach ($lines as $id => $mother_id) {
		// sort lines by mother_id
		if (empty($zz['list']['hierarchy']['id'])) 
			$zz['list']['hierarchy']['id'] = 'NULL';
		if ($id == $zz['list']['hierarchy']['id']) {
			// get uppermost line if hierarchy id is not NULL!
			$mother_id = 'TOP';
		} elseif (empty($mother_id))
			$mother_id = 'NULL';
		$h_lines[$mother_id][$id] = $id;
	}
	if (!$h_lines) return zz_return(array(array(), 0));

	$lines = array(); // unset and initialize
	$level = 0; // level (hierarchy)
	$i = 0; // number of record, for LIMIT
	$my_lines = zz_list_hierarchy($h_lines, $zz['list']['hierarchy']['id'], $id_field, $level, $i);
	$total_rows = $i; // sometimes, more rows might be selected beforehands,
	// but if hierarchy has ID value, not all rows are shown
	if ($my_lines) {
		if (!$zz_conf['int']['this_limit']) {
			$start = 0;
			$end = $total_rows -1;
		} else {
			$start = $zz_conf['int']['this_limit'] - $zz_conf['limit'];
			$end = $zz_conf['int']['this_limit'] -1;
		}
		foreach (range($start, $end) as $index) {
			if (!empty($my_lines[$index])) 
				$lines[$my_lines[$index][$id_field]] = $my_lines[$index];
		}
		// for performance reasons, we didn't save the full result set,
		// so we have to requery it again.
		if ($zz_conf['int']['this_limit'] !== '') {
			$zz['sql'] = zz_edit_sql($zz['sql'], 'WHERE', '`'.$zz['table'].'`.'.$id_field
				.' IN ('.implode(',', array_keys($lines)).')');
		} // else sql remains same
		$lines = zz_array_merge($lines, zz_db_fetch($zz['sql'], $id_field));
	}
	foreach ($lines as $line) {
		if (empty($line['zz_hidden_line'])) continue;
		// get record which is normally beyond our scope via ID
		$zz['sql'] = zz_edit_sql($zz['sql'], 'WHERE', 'nothing', 'delete');
		$zz['sql'] = zz_edit_sql($zz['sql'], 'WHERE', '`'.$zz['table'].'`.'.$id_field.' = "'.$line[$id_field].'"');
		$line = zz_db_fetch($zz['sql']);
		if ($line) {
			$lines[$line[$id_field]] = array_merge($lines[$line[$id_field]], $line);
		}
	}
	$lines = zz_list_query_extras($lines, $id_field, $zz['sqlextra']);
	return zz_return(array($lines, $total_rows));
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
function zz_list_modes($id, $zz_var, $zz_conf_record) {
	global $zz_conf;
	$link = '<a href="%smode=%s&amp;%s=%s">%s</a>';
	$base_url = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
		.$zz_conf['int']['url']['?&'];
	$suffix = $id.$zz_var['extraGET'];
	$modes = array();

	if ($zz_conf_record['edit']) {
		$modes[] = sprintf($link, $base_url, 'edit', 'id', $suffix, zz_text('edit'));
	} elseif ($zz_conf_record['view']) {
		$modes[] = sprintf($link, $base_url, 'show', 'id', $suffix, zz_text('show'));
	}
	if ($zz_conf_record['copy']) {
		$modes[] = sprintf($link, $base_url, 'add', 'source_id', $suffix, zz_text('Copy'));
	}
	if ($zz_conf_record['delete']) {
		$modes[] = sprintf($link, $base_url, 'delete', 'id', $suffix, zz_text('delete'));
	}
	if ($modes) return implode('&nbsp;&middot; ', $modes);
	else return false;
}

/**
 * Output and formatting of a single table cell in list mode
 *
 * @param array $list
 * @param array $row
 * @param array $field field definition
 * @param array $line current record from database
 * @param array $lastline previous record from database
 * @param array $zz_var
 * @param string $table
 * @param array $zz_conf_record
 * @global array $zz_conf
 * @return array $row
 *		string 'value'	= raw value in database, modified by factor/display if applicable
 *		array 'class'	= Array of class names for cell
 *		string 'text'	= HTML output for cell
 */
function zz_list_field($list, $row, $field, $line, $lastline, $zz_var, $table, $mode, $zz_conf_record) {
	global $zz_conf;
	static $append_field;
	static $append_string_first;
	
	if (!empty($field['export_no_html'])) {
		$row['export_no_html'] = true;
	}
	
	// check if one of these fields has a type_detail
	$display_fields = array('hidden', 'write_once', 'display');
	if (in_array($field['type'], $display_fields) AND !empty($field['type_detail']))
		$field['type'] = $field['type_detail'];
	
	// shortcuts, isset: value might be 0
	if (!empty($field['table_name']) AND isset($line[$field['table_name']]))
		$row['value'] = $line[$field['table_name']];
	elseif (!empty($field['field_name']) AND isset($line[$field['field_name']]))
		$row['value'] = $line[$field['field_name']];
	else
		$row['value'] = '';

	// set 'class'
	if (!isset($row['class'])) $row['class'] = array();
	elseif (!is_array($row['class'])) $row['class'] = array($row['class']);
	// if table row is affected by where, mark this
	$where_table = !empty($zz_var['where'][$table]) ? $zz_var['where'][$table] : '';
	// set class depending on where and field info
	$field['level'] = zz_list_field_level($list, $field, $line);
	$row['class'] = array_merge($row['class'], zz_field_class($field, $where_table));
				
	// set 'text'
	if (empty($row['text'])) $row['text'] = '';

	//	if there's a link, glue parts together
	$link = false;
	if ($mode != 'export' OR $_GET['export'] == 'kml') $link = zz_set_link($field, $line);

	$mark_search_string = 'field_name';
	$text = '';

	if (isset($field['display_field'])) {
		$text = $line[$field['display_field']];
		$text = zz_htmltag_escape($text);
		$mark_search_string = 'display_field';
	} else {
		//	go for type of field if no display field is set
		switch ($field['type']) {
		case 'calculated':
			if ($field['calculation'] == 'hours') {
				$row['value'] = 0;
				foreach ($field['calculation_fields'] as $calc_field)
					if (!$row['value']) $row['value'] = strtotime($line[$calc_field]);
					else $row['value'] -= strtotime($line[$calc_field]);
				if ($row['value'] < 0) $text = '<em class="negative">';
				$text .= zz_hour_format($row['value']);
				if ($row['value'] < 0) $text .= '</em>';
			} elseif ($field['calculation'] == 'sum') {
				$row['value'] = 0;
				foreach ($field['calculation_fields'] as $calc_field)
					$row['value'] += $line[$calc_field];
				$text = $row['value'];
			} elseif ($field['calculation'] == 'sql') {
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
						.'"  alt="'.zz_text('no_image').'" class="thumb">'.($link ? '</a>' : '');
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
			$text = $row['value']; // field was already formatted etc. in subselect
			$mark_search_string = false;
			$link = false;
			break;
		case 'url':
		case 'mail':
		case 'mail+name':
			if ($field['type'] === 'url') {
				$text = zz_cut_length(zz_htmltag_escape($row['value']), $zz_conf_record['max_select_val_len']);
			} else {
				$text = zz_htmltag_escape($row['value']);
			}
			break;
		case 'ipv4':
			$text = long2ip($row['value']);
			break;
		case 'ip':
			$text = @inet_ntop($row['value']);
			break;
		case 'unix_timestamp':
			$text = date('Y-m-d H:i:s', $row['value']);
			break;
		case 'timestamp':
			$text = timestamp2date($row['value']);
			break;
		case 'date':
			$text = zz_date_format($row['value']);
			break;
		case 'select':
			if (!empty($field['set']) 
				OR !empty($field['set_sql']) 
				OR !empty($field['set_folder'])) {
				$values_old = explode(',', $row['value']);
				$values_new = array();
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
			if (isset($field['number_type'])) {
				$text = zz_number_format($row['value'], $field);
			} else {
				$text = $row['value'];
			}
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
			} else {
				$text = $row['value'];
				$text = nl2br(zz_htmltag_escape($text));
			}
			break;
		case 'list_function':
			$text = $field['list_function']($field, $row['value'], $line);
			break;
		default:
			$text = $row['value'];
			if (empty($field['list_format'])) {
				$text = nl2br(zz_htmltag_escape($text));
			}
			break;
		}
	}
	if (!empty($field['translate_field_value'])) {
		$text = zz_text($text);
	}
	if (!empty($field['list_format']) AND $text) {
		$text = zz_list_format($text, $field['list_format']);
	}
	if (!empty($field['hide_zeros']) AND !$text) {
		$text = '';
	}
	if ($text === '' OR $text === false) return $row;

	if ($mark_search_string AND $mode != 'export') {
		$text = zz_mark_search_string($text, $field[$mark_search_string], $field);
	}

	// add prefixes etc. to 'text'
	if (!empty($field['list_append_if_first']) AND !$append_string_first) {
		$row['text'] .= zz_text($field['list_append_if_first']);
		$append_string_first = true;
	} elseif (!empty($field['list_append_if_middle']) AND $append_string_first) {
		$row['text'] .= zz_text($field['list_append_if_middle']);
	}
	if (!empty($field['list_append_next'])) {
		$append_field = true;
	} else {
		$append_field = false;
		$append_string_first = false;
	}
	if (!empty($field['list_prefix'])) {
		$row['text'] .= zz_text($field['list_prefix']);
	}
	if (!empty($field['list_abbr']) AND $mode != 'export') {
		$row['text'] .= '<abbr title="'.zz_html_escape($line[$field['list_abbr']]).'">';
	}

	if ($link) $row['text'] .= $link;
	$row['text'] .= $text;
	if ($link) $row['text'] .= '</a>';

	if (isset($field['unit'])) 
		$row['text'] .= '&nbsp;'.$field['unit'];	
	if (!empty($field['list_suffix'])) {
		$row['text'] .= zz_text($field['list_suffix']);
	}
	if (!empty($field['list_abbr']) AND $mode != 'export') {
		$row['text'] .= '</abbr>';
	}

	return $row;
}

/**
 * adds values, outputs HTML table foot
 *
 * @param array $table_defs
 * @param int $z
 * @param array $table (foreign_key_field_name => value)
 * @param array $sum (field_name => value)
 * @global array $zz_conf ($zz_conf['int']['group_field_no'])
 * @return string HTML output of table foot
 */
function zz_field_sum($table_defs, $z, $table, $sum) {
	global $zz_conf;
	$tfoot_line = '';
	foreach ($table_defs as $index => $field) {
		if (!$field['show_field']) continue;
		if (in_array($index, $zz_conf['int']['group_field_no'])) continue;
		if ($field['type'] == 'id' && empty($field['show_id'])) {
			$tfoot_line .= '<td class="recordid">'.$z.'</td>';
		} elseif (!empty($field['sum'])) {
			$tfoot_line .= '<td'.zz_field_class($field, (!empty($table) ? $table : ''), true).'>';
			$value = $sum[$field['title']];
			if (isset($field['calculation']) AND $field['calculation'] == 'hours') {
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
			$tfoot_line .= '<td'
				.zz_field_class($field, (!empty($table) ? $table : ''), true)
				.'>&nbsp;</td>';
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
	global $zz_conf;
	if (!is_array($list_format)) $list_format = array($list_format);
	foreach ($list_format as $format) {
		if (!empty($zz_conf['modules']['debug'])) zz_debug('start', $format);
		$text = $format($text);
		if (!empty($zz_conf['modules']['debug'])) zz_debug('end');
	}
	return $text;
}

/**
 * sorts $lines hierarchically
 *
 * @param array $h_lines
 * @param string $hierarchy ($zz['list']['hierarchy']['id'])
 * @param string $id_field
 * @param int $level
 * @param int $i
 * @return array $my_lines
 */
function zz_list_hierarchy($h_lines, $hierarchy, $id_field, $level, &$i) {
	$my_lines = array();
	$show_only = array();
	if (!$level AND $hierarchy != 'NULL' AND !empty($h_lines['TOP'])) {
		// show uppermost line
		$h_lines['TOP'][0]['zz_level'] = $level;
		$my_lines[$i][$id_field] = $h_lines['TOP'][$hierarchy];
		// this page has child pages, don't allow deletion
		$my_lines[$i]['zz_conf']['delete'] = false; 
		$i++;
	}
	if ($hierarchy != 'NULL') $level++; // don't indent uppermost level if top category is NULL
	if ($hierarchy == 'NULL' AND empty($h_lines[$hierarchy])) {
		// Looks like a WHERE condition took some vital records from our hierarchy
		// at least for the top level, get them back somehow.
		foreach (array_keys($h_lines) as $main_id) {
			$nulls[$main_id] = $main_id; // put all main_ids in Array
			foreach ($h_lines[$main_id] as $id) {
				// remove from Array if id has already a from NULL different main_id
				unset($nulls[$id]); 
			}
		}
		foreach ($nulls as $id) {
			// put all ids with missing main_ids into NULL-Array
			$h_lines['NULL'] = array($id => $id);
			$show_only[] = $id;
		}
	}
	if (!empty($h_lines[$hierarchy])) {
		foreach ($h_lines[$hierarchy] as $h_line) {
			$my_lines[$i] = array(
				$id_field => $h_line,
				'zz_level' => $level
			);
			if (in_array($h_line, $show_only)) {
				// added nulls are not editable, won't be shown
				$my_lines[$i]['zz_conf']['access'] = 'none';
				$my_lines[$i]['zz_hidden_line'] = true;
			}
			$i++;
			if (!empty($h_lines[$h_line])) {
				// this page has child pages, don't allow deletion
				$my_lines[($i-1)]['zz_conf']['delete'] = false; 
				$my_lines = array_merge($my_lines, 
					zz_list_hierarchy($h_lines, $h_line, $id_field, $level, $i));
			}
		}
	}
	return $my_lines;
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_set_link($field, $line) {
	$link = false;
	if ($field['type'] == 'url') {
		$link = $line[$field['field_name']];
	} elseif ($field['type'] == 'mail' AND $line[$field['field_name']]) {
		// mailto-Link only if there is an address in that field
		$link = 'mailto:'.$line[$field['field_name']];
	} elseif ($field['type'] == 'mail+name' AND $line[$field['field_name']]) {
		// mailto-Link only if there is an address in that field
		$link = 'mailto:'.rawurlencode($line[$field['field_name']]);
	} elseif (isset($field['link']) AND is_array($field['link'])) {
		$link = zz_makelink($field['link'], $line);
	} elseif (!empty($field['link'])) {
		$link = $field['link'].$line[$field['field_name']];
	}
	if ($link AND !empty($field['link_referer'])) 
		$link .= '&amp;referer='.urlencode($_SERVER['REQUEST_URI']);
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
 * marks search string in list display on webpage
 *
 * @param string $value value to mark
 * @param string $field_name (optional) if set, only values in this column
 *			will be marked
 * @param array $field field definition
 * @global array $zz_conf
 * @return string $value value with marks
 */
function zz_mark_search_string($value, $field_name = false, $field = array()) {
	global $zz_conf;
	// check if field should be marked
	if (!$zz_conf['show_list']) return $value;
	if (!empty($field['dont_mark_search_string'])) return $value;
	if ($zz_conf['list_display'] != 'table' AND $zz_conf['list_display'] != 'ul') return $value;
	if (empty($_GET['q'])) return $value;
	if (!empty($_GET['scope'])) {
		if (strstr($_GET['scope'], '.'))
			$my_field_name = substr($_GET['scope'], strrpos($_GET['scope'], '.')+1);
		else
			$my_field_name = $_GET['scope'];
		if ($my_field_name != $field_name) return $value;
	}

	// meta characters which must be escaped for preg_replace
	$needle = preg_quote(trim($_GET['q']));
	$highlight = '<span class="highlight">\1</span>';
	$pattern = '#(?!<.*?)(%s)(?![^<>]*?>)#i';
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
 * @global array $zz_conf
 *		string 'group', array 'int'['group_field_no'] (will be set to 'n') 
 * @return bool true/false if field will be shown (group: false, otherwise true)
 */
function zz_list_group_field_no($list, $field, $index) {
	global $zz_conf;
	if (!isset($zz_conf['int']['group_field_no'])) 
		$zz_conf['int']['group_field_no'] = array();
	$keys = array('display_field', 'field_name');
	// field_name will overwrite display_field!
	foreach ($keys as $key) {
		if (empty($field[$key])) continue;
		$pos = array_search($field[$key], $list['group']);
		if ($pos === false) continue;
		$zz_conf['int']['group_field_no'][$pos] = $index;
		ksort($zz_conf['int']['group_field_no']);
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
	$list['sum'][$field['title']] += $value;
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
		$sum_group[$index][$field_title] += $sum;
	}
	return $sum_group;
}

/**
 * shows footer line for group with calculated sums
 * 
 * @param array $rowgroup
 * @param array $main_table_query ($head from zz_list)
 * @param int $z
 * @param array $where_values
 * @param array $sum_group
 */
function zz_list_group_foot($rowgroup, $main_table_query, $z, $where_values, $sum_group) {
	$my_index = '';
	foreach ($rowgroup as $my_group) {
		if ($my_index) $my_index .= '['.$my_group.']';
		else $my_index = $my_group;
	}
	if (empty($sum_group[$my_index])) return false;
	return '<tr class="group_sum">'
		.zz_field_sum($main_table_query, $z, $where_values, $sum_group[$my_index])
		.'</tr>'."\n";
}

/**
 * outputs number of records in list
 *
 * @param int $total_rows
 * @global array $zz_conf
 * @return string HTML code with text
 */
function zz_list_total_records($total_rows) {
	global $zz_conf;
	if (!empty($zz_conf['dont_show_total_records'])) return '';

	$text = '';
	if ($total_rows == 1) $text = '<p class="totalrecords">'.$total_rows.' '.zz_text('record total').'</p>'; 
	elseif ($total_rows) $text = '<p class="totalrecords">'.$total_rows.' '.zz_text('records total').'</p>';
	return $text;
}

/**
 * if LIMIT is set, shows different pages for each $step records
 *
 * @param int $limit_step = $zz_conf['limit'] how many records shall be shown on each page
 * @param int $this_limit = $zz_conf['int']['this_limit'] last record no. on this page
 * @param int $total_rows	count of total records that might be shown
 * @param string $scope 'body', @todo: 'head' (not yet implemented)
 * @global array $zz_conf 'limit_show_range'
 * @return string HTML output
 * @todo
 * 	- <link rel="next">, <link rel="previous">
 */
function zz_list_pages($limit_step, $this_limit, $total_rows, $scope = 'body') {
	global $zz_conf;

	// check whether there are records
	if (!$total_rows) return false;
	
	// check whether records shall be limited or not
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
	$links = array();
	$links[] = array(
		'link'	=> zz_list_pagelink(0, $this_limit, $limit_step, $url),
		'text'	=> '|&lt;',
		'class' => 'first',
		'title' => zz_text('First page')
	);
	$prev = $this_limit - $limit_step;
	if ($prev > $total_rows) {
		$prev = ceil($total_rows/$limit_step)*$limit_step;
	}
	$links[] = array(
		'link'	=> zz_list_pagelink($prev, $this_limit, 0, $url),
		'text'	=> '&lt;',
		'class' => 'prev',
		'title' => 	zz_text('Previous page')
	);
	if ($total_rows < $zz_conf['limit_all_max']) {
		$links[] = array(
			'link'	=> zz_list_pagelink(-1, $this_limit, 0, $url),
			'text'	=> zz_text('all'),
			'class' => 'all',
			'title' => 	zz_text('All records on one page')
		);
	}

	// set links for each step
	$ellipsis_min = false;
	$ellipsis_max = false;
	// last step, = next integer from total_rows which can be divided by limit_step
	$rec_last = 0; 

	// missing lines on last page to make it a full page
	$offset = $limit_step - ($total_rows % $limit_step);
	$max_limit = $total_rows + $offset;

	if ($zz_conf['limit_show_range']
		AND $total_rows >= $zz_conf['limit_show_range']
		AND $this_limit <= $max_limit)
	{
		$rec_start = $this_limit - ($zz_conf['limit_show_range']/2 + 2 * $limit_step);
		if ($rec_start < 0) {
			$rec_start = 0;
		} elseif ($rec_start > 0) {
			// set rec start to something which can be divided through step
			$rec_start = ceil($rec_start/$limit_step)*$limit_step;
		}
		$rec_end = $this_limit + ($zz_conf['limit_show_range'] + $limit_step);
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
		if ($this_limit + ceil($zz_conf['limit_show_range'] / 2) < $range_min) {
			if (!$ellipsis_max) {
				$links[] = array('text' => '&hellip;', 'link' => '');
				$ellipsis_max = true;
			}
			continue;
		}
		if ($this_limit > $range_max + floor($zz_conf['limit_show_range'] / 2)) {
			if (!$ellipsis_min) {
				$links[] = array('text' => '&hellip;', 'link' => '');
				$ellipsis_min = true;
			}
			continue;
		}
		if ($range_max > $total_rows) $range_max = $total_rows;
		// if just one above the last limit show this number only once
		switch ($zz_conf['limit_display']) {
		case 'entries':
			$text = ($range_min == $range_max) ? $range_min : $range_min.'-'.$range_max;
		default:
		case 'pages':
			$text = $i/$zz_conf['limit']+1;
		}
		$links[] = array(
			'link'	=> zz_list_pagelink($i, $this_limit, $limit_step, $url),
			'text'	=> $text
		);
	}
	$limit_next = $this_limit + $limit_step;
	if ($limit_next > $range_max) $limit_next = $i;
	if (!$rec_last) $rec_last = $i;

	// set more standard links
	$links[] = array(
		'link'	=> zz_list_pagelink($limit_next, $this_limit, 0, $url),
		'text'	=> '&gt;',
		'class' => 'next',
		'title' => zz_text('Next page')
	);
	$links[] = array(
		'link'	=> zz_list_pagelink($rec_last, $this_limit, 0, $url),
		'text'	=> '&gt;|',
		'class' => 'last',
		'title' => zz_text('Last page')
	);

	// output links
	$output = '<ul class="pages">'."\n";
	$no_pages = array('&hellip;', '&gt;', '&gt;|', '|&lt;', '&lt;');
	foreach ($links as $link) {
		// mark current page, but not ellipsis
		$span = in_array($link['text'], $no_pages) ? 'span' : 'strong';
		$output .= '<li'.(!empty($link['class']) ? ' class="'.$link['class'].'"' : '').'>'
			.($link['link'] ? '<a href="'.$link['link'].'"'
				.(!empty($link['title']) ? '  title="'.$link['title'].'"' : '')
				.'>' : '<'.$span.'>')
			.$link['text']
			.($link['link'] ? '</a>' : '</'.$span.'>').'</li>'."\n";
	}
	$output .= '</ul>'."\n";
	$output .= '<br clear="all">'."\n";
	return $output;
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
	$unwanted_keys = array('mode', 'id', 'limit', 'add', 'zzaction', 'zzhash');
	$url['base'] = $zz_conf['int']['url']['self']
		.zz_edit_query_string($zz_conf['int']['url']['qs']
		.$zz_conf['int']['url']['qs_zzform'], $unwanted_keys);
	$parts = parse_url($url['base']);
	if (isset($parts['query'])) $url['query'] = '&amp;';
	else $url['query'] = '?';
	$url['query'] .= 'limit=';
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
 * @global array $zz_conf 'limit'
 * @return string $url with limit=n
 */
function zz_list_pagelink($start, $limit, $limit_step, $url) {
	global $zz_conf;
	if ($start == -1) {
		// all records
		if (!$limit) return false;
		else $limit_new = 0;
	} else {
		$limit_new = $start + $limit_step;
		if ($limit_new == $limit) {
			// current page
			return false;
		} elseif (!$limit_new) {
			// 0 does not exist, means all records
			return false;
		}
	}
	$url_out = $url['base'];
	if ($limit_new != $zz_conf['limit']) {
		$url_out .= $url['query'].$limit_new;
	}
	return $url_out;
}

/**
 * Adds ORDER BY to SQL string, if set via URL
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
		$possible_values = array();
	} else {
		$possible_values = array('desc', 'asc');
		$my_order = '';
	}
	$dir = zz_check_get_array('dir', 'values', $possible_values);
	if ($dir === 'asc') {
		$my_order = ' ASC';
	} elseif ($dir === 'desc') {
		$my_order = ' DESC';
	}

	if (!isset($_GET['order']) AND !isset($_GET['group'])) return $sql;

	$order = array();
	$get_order_used = false;
	$get_group_used = false;
	foreach ($fields as $field) {
		if (!empty($field['dont_sort'])) continue;
		if (!empty($_GET['order'])
			AND ((isset($field['display_field']) && $field['display_field'] === $_GET['order'])
			OR (isset($field['field_name']) && $field['field_name'] === $_GET['order']))
		) {
			if (isset($field['order'])) $order[] = $field['order'].$my_order;
			else $order[] = $_GET['order'].$my_order;
			$get_order_used = true;
		}
		if (!empty($_GET['group'])
			AND ((isset($field['display_field']) && $field['display_field'] === $_GET['group'])
			OR (isset($field['field_name']) && $field['field_name'] === $_GET['group']))
		) {
			if (isset($field['order'])) $order[] = $field['order'].$my_order;
			else $order[] = $_GET['group'].$my_order;
			$get_group_used = true;
		}
	}
	
	// check variables if valid
	$unwanted_keys = array();
	if (isset($_GET['order']) AND !$get_order_used) $unwanted_keys[] = 'order';
	if (isset($_GET['group']) AND !$get_group_used) $unwanted_keys[] = 'group';
	if ($unwanted_keys) {
		$zz_conf['int']['http_status'] = 404;
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
			$zz_conf['int']['url']['qs_zzform'], $unwanted_keys
		);
	}
	
	if (!$order) return $sql;
	$sql = zz_edit_sql($sql, 'ORDER BY', implode(',', $order), 'add');
	return $sql;
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

	$out = !empty($field['title_tab']) ? $field['title_tab'] : $field['title'];
	if (!empty($field['dont_sort'])) return $out;
	if (!isset($field['field_name'])) return $out;
	$unsortable_fields = array('calculated', 'image', 'upload_image'); // 'subtable'?
	if (in_array($field['type'], $unsortable_fields)) return $out;
	if ($mode === 'nohtml') return $out;
	
	// create a link to order this column if desired
	if (isset($field['display_field'])) $order_val = $field['display_field'];
	else $order_val = $field['field_name'];
	$unwanted_keys = array('dir', 'zzaction', 'zzhash');
	$new_keys = array('order' => $order_val);
	$uri = $zz_conf['int']['url']['self'].zz_edit_query_string($zz_conf['int']['url']['qs']	
		.$zz_conf['int']['url']['qs_zzform'], $unwanted_keys, $new_keys);
	$order_dir = 'asc';
	if (str_replace('&amp;', '&', $uri) == $_SERVER['REQUEST_URI']) {
		$uri.= '&amp;dir=desc';
		$order_dir = 'desc';
	}
	$link_open = '<a href="'.$uri.'" title="'.zz_text('order by').' '
		.strip_tags($field['title']).' ('.zz_text($order_dir).')">';
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
 * @param string $table_id_field_name
 * @return array
 *	- string key_field_name
 *	- array subselect definition
 */
function zz_list_init_subselects($field, $fieldindex, $table_id_field_name) {
	$subselect = $field['subselect'];
	$foreign_key_field = array();
	$translation_key_field = array();
	foreach ($field['fields'] as $subfield) {
		if ($subfield['type'] == 'foreign_key') {
			$foreign_key_field = $subfield;
		} elseif ($subfield['type'] == 'translation_key') {
			$translation_key_field = $subfield;
		}
	}
	// get field name of foreign key
	$subselect['id_fieldname'] = $foreign_key_field['field_name'];
	if ($translation_key_field) {
		$subselect['key_fieldname'] = $table_id_field_name;
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
	$subselect['id_table_and_fieldname'] = $subselect['table'].'.'.$subselect['id_fieldname'];
	$subselect['fieldindex'] = $fieldindex;
	$subselect['table_name'] = $field['table_name'];

	return $subselect;
}

/**
 * get values for "subselects" in detailrecords
 *
 * @param array $rows
 * @param array $subselects List of detail records with an SQL query
 *		$zz['fields'][n]['subselect'] = ...;
 *			required keys: 'sql', 'id_table_and_fieldname', 'id_fieldname'
 *			optional keys: 'translation_key', 'list_format', 'export_no_html',
 *			'prefix', 'concat_rows', 'suffix', 'concat_fields', 'show_empty_cells'
 * @param array $ids
 * @param array $field
 * @return array $rows
 */
function zz_list_get_subselects($lines, $subselects) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	
	if (!$subselects) return zz_return($lines);
	
	foreach ($subselects as $subselect) {
		// IDs
		$ids = array();
		foreach ($lines as $no => $line) {
			$ids[$no] = $line[$subselect['key_fieldname']];
		}
	
		// default values
		if (!isset($subselect['prefix'])) $subselect['prefix'] = '<p>';
		if (!isset($subselect['concat_rows'])) $subselect['concat_rows'] = "</p>\n<p>";
		if (!isset($subselect['suffix'])) $subselect['suffix'] = '</p>';
		if (!isset($subselect['concat_fields'])) $subselect['concat_fields'] = ' ';
		if (!isset($subselect['show_empty_cells'])) $subselect['show_empty_cells'] = false;
		if (!isset($subselect['link'])) $subselect['link'] = array();
		
		$subselect['sql'] = zz_edit_sql($subselect['sql'], 'WHERE', 
			$subselect['id_table_and_fieldname'].' IN ('.implode(', ', $ids).')');
		if (!empty($subselect['translation_key']))
			$subselect['sql'] = zz_edit_sql($subselect['sql'], 'WHERE', 
				'translationfield_id = '.$subselect['translation_key']);
		// E_USER_WARNING might return message, we do not want to see this message
		// but in the logs
		$sub_lines = zz_db_fetch($subselect['sql'], array($subselect['id_fieldname'], '_dummy_id_'), 'numeric', false, E_USER_WARNING);
		if (!is_array($sub_lines)) $sub_lines = array();

		foreach ($ids as $no => $id) {
			if (empty($sub_lines[$id])) continue;
			$linetext = array();
			foreach ($sub_lines[$id] as $linefields) {
				unset($linefields[$subselect['id_fieldname']]); // ID field will not be shown
				$fieldtext = false;
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
						if (!empty($subselect['field_prefix'][$index]))
							$db_fields = $subselect['field_prefix'][$index].$db_fields;
						if (!empty($subselect['field_suffix'][$index]))
							$db_fields .= $subselect['field_suffix'][$index];
						if ($fieldtext) $fieldtext .= $subselect['concat_fields'];
					}
					$fieldtext .= $db_fields;
					$index++;
				}
				if ($subselect['link']) {
					$link = zz_makelink($subselect['link'], $linefields);
					if ($link) $fieldtext = sprintf('<a href="%s">%s</a>', $link, $fieldtext);
				}
				$linetext[] = $fieldtext;
			}
			$subselect_text = implode($subselect['concat_rows'], $linetext);
			if ($subselect_text) {
				$subselect_text = $subselect['prefix'].$subselect_text.$subselect['suffix'];
			}
			if (!empty($subselect['list_format'])) {
				$subselect_text = zz_list_format($subselect_text, $subselect['list_format']);
			}
			$lines[$no][$subselect['table_name']] = zz_mark_search_string(
				$subselect_text, $subselect['table_name'], $subselect
			);
		}
	}
	return zz_return($lines);
}

/**
 * sets level for a field where a hierarchy of records shall be displayed in
 *
 * @param array $list
 * @param array $field
 * @param array $line
 * @global array $zz_conf
 * @return string level or ''
 */
function zz_list_field_level($list, $field, $line) {
	if (!isset($line['zz_level'])) return '';

	global $zz_conf;
	if (!empty($field['decrease_level'])) $line['zz_level'] -= $field['decrease_level'];

	if (!empty($field['field_name']) // occurs in case of subtables
		AND $field['field_name'] == $list['hierarchy']['display_in']) {
		return $line['zz_level'];
	} elseif (!empty($field['table_name']) 
		AND $field['table_name'] == $list['hierarchy']['display_in']) {
		return $line['zz_level'];
	}
	return '';
}

/**
 * check depending on grouping whether a field will be shown or not
 *
 * @param array $table_defs
 * @param array $list
 * @global array $zz_conf
 * @return array $table_defs ('show_field' set for each field)
 */
function zz_list_show_group_fields($table_defs, $list) {
	global $zz_conf;
	if (!$zz_conf['show_list']) return $table_defs;

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
 * @param array $where_values
 * @param array $columns (list of columns that should appear)
 * @return array
 */
function zz_list_head($old_head, $where_values, $columns) {
	$j = 0;

	$continue_next = false;
	$head = array();
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
			$head[$j]['class'] = zz_field_class($field, $where_values);
			$head[$j]['th'] = zz_list_th($field);
		} elseif (!empty($field['list_append_show_title'])) {
			// Add to previous field
			$head[$j]['class'] = array_merge($head[$j]['class'], zz_field_class($field, $where_values));
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
 * @param array $values
 * @param bool $html (optional; true: output of HTML attribute)
 * @return mixed array $class list of strings with class names /
 *		string HTML output class="..."
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_field_class($field, $values, $html = false) {
	$class = array();
	if (!empty($field['level']))
		$class[] = 'level'.$field['level'];
	if ($field['type'] == 'id' && empty($field['show_id']))
		$class[] = 'recordid';
	elseif ($field['type'] == 'number' OR $field['type'] == 'calculated')
		$class[] = 'number';
	if (!empty($_GET['order']) AND empty($field['dont_sort'])) 
		if (!empty($field['field_name']) AND $field['field_name'] == $_GET['order'])
			$class[] = 'order';
		elseif (!empty($field['display_field']) AND $field['display_field'] == $_GET['order']) 
			$class[] = 'order';
	if ($values)
		if (isset($field['field_name']) AND empty($field['dont_show_where_class'])) 
		// does not apply for subtables!
			if (zz_field_in_where($field['field_name'], $values)) 
				$class[] = 'where';
	if (!empty($field['class'])) {
		// we may go through this twice
		if (is_array($field['class']))
			$class = array_merge($class, $field['class']);
		else
			$class[] = $field['class'];
	}
	// array_keys(array_flip()) is reported to be faster than array_unique()
	$class = array_keys(array_flip($class));

	if (!$html) return $class;
	if (!$class) return false;
	return ' class="'.implode(' ', $class).'"';
}

function zz_field_in_where($field, $values) {
	$where = false;
	foreach (array_keys($values) as $value)
		if ($value == $field) $where = true;
	return $where;
}

/**
 * outputs data in table format
 *
 * @param array $list
 *		array 'where_values'
 *		bool 'modes'
 *		bool 'details'
 *		string 'sum'
 *		string 'sum_group'
 *		array 'group_titles'
 *		int 'current_record'
 * @param array $rows
 * @param array $head
 * @global array $zz_conf
 * @return string
 */
function zz_list_table($list, $rows, $head) {
	global $zz_conf;
	
	// Header
	$output = '<table class="data"><thead>'."\n".'<tr>';
	if ($zz_conf['select_multiple_records']) $output .= '<th></th>';

	// Rest cannot be set yet because we do not now details/mode-links
	// of individual records
	$columns = 0;
	foreach ($head as $col) {
		if (!$col['show_field']) continue;
		if ($col['class']) $col['class'] = ' class="'.implode(' ', $col['class']).'"';
		else $col['class'] = '';
		$output .= '<th'.$col['class'].'>'.$col['th'].'</th>';
		$columns++;
	}
	if ($list['modes'] OR $list['details']) {
		$output .= ' <th class="editbutton">'.zz_text('action').'</th>';
		$columns++;
	}
	$output .= '</tr></thead>'."\n";

	//
	// Table footer
	//
	if (($list['tfoot'] AND $list['sum'])
		OR $zz_conf['select_multiple_records']) {
		$output .= '<tfoot>'."\n";
		if ($list['sum']) {
			$output .= '<tr class="sum">';
			$output .= zz_field_sum($head, count($rows), $list['where_values'], $list['sum']);
			if ($list['modes'] OR $list['details'])
				$output .= '<td class="editbutton">&nbsp;</td>';
			$output .= '</tr>'."\n";
		}
		if ($zz_conf['select_multiple_records']) {
			$buttons = array();
			if ($zz_conf['edit'])
				$buttons[] = '<input type="submit" value="'.zz_text('Edit records').'" name="multiple_edit">';
			if ($zz_conf['delete'])
				$buttons[] = '<input type="submit" value="'.zz_text('Delete records').'" name="multiple_delete">';
			if ($buttons) {
				$output .= '<tr class="multiple"><td><input type="checkbox" onclick="zz_set_checkboxes(this.checked);"></td>'
				.'<td colspan="'.$columns.'"><em>'.zz_text('Selection').':</em> '
				.'<input type="hidden" name="zz_action" value="multiple">'
				.implode(' ', $buttons)
				.'</td></tr>';
			}
		}
		$output .= '</tfoot>'."\n";
	}

	$output .= '<tbody>'."\n";
	$rowgroup = false;
	foreach ($rows as $index => $row) {
		if ($list['group'] AND $row['group'] != $rowgroup) {
			foreach ($list['group'] as $pos => $my_group) {
				if (!empty($row['group'][$pos])) continue;
				$list['group_titles'][$index][$pos] = zz_text('- unknown -');
			}
			if ($rowgroup) {
				$my_groups = $rowgroup;
				$my_old_groups = $row['group'];
				while ($my_groups) {
					if ($list['tfoot'])
						$output .= zz_list_group_foot($my_groups, $head, count($rows), $list['where_values'], $list['sum_group']);
					array_pop($my_groups);
					array_pop($my_old_groups);
					if ($my_groups == $my_old_groups) break;
				}
				$output .= '</tbody><tbody>'."\n";
			}
			$output .= '<tr class="group"><td colspan="'.(count($row)-1)
				.'">'.sprintf($zz_conf['group_html_table'], zz_list_group_titles_out($list['group_titles'][$index]))
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
			.(($index+1) == count($rows) ? ' last' : '')
			.($current_field ? ' current_record' : '')
			.'">'; //onclick="Highlight();"
		foreach ($row as $fieldindex => $field) {
			if (is_numeric($fieldindex)) 
				$output .= '<td'
					.($field['class'] ? ' class="'.implode(' ', $field['class']).'"' : '')
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
			$output .= zz_list_group_foot($my_groups, $head, count($rows), $list['where_values'], $list['sum_group']);
			array_pop($my_groups);
		}
	}
	$output .= "</tbody>\n</table>\n";
	return $output;
}

/**
 * outputs data in ul format
 *
 * @param array $list
 *		bool 'modes'
 *		bool 'details'
 *		array 'group_titles'
 *		int 'current_record'
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
			foreach ($list['group'] as $pos => $my_group) {
				if (empty($row['group'][$pos])) 
					$list['group_titles'][$index][$pos] = zz_text('- unknown -');
			}
			if ($rowgroup) {
				$output .= '</ul><br clear="all">'."\n";
			}
			$output .= sprintf(
				"\n<h2>%s</h2>\n<ul class='data'>\n",
				zz_list_group_titles_out($list['group_titles'][$index])
			);
			$rowgroup = $row['group'];
		}
		$output .= '<li class="'.($index & 1 ? 'uneven':'even')
			.((isset($list['current_record']) AND $list['current_record'] == $index) ? ' current_record' : '')
			.(($index + 1) == count($rows) ? ' last' : '').'">'; //onclick="Highlight();"
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
	$output .= "</ul>\n<br clear='all'>";
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
	global $zz_setting;
	require_once $zz_setting['lib'].'/zzwrap/syndication.inc.php';

	$img = zz_makelink($field['path_json_request'], $line);
	$img = wrap_syndication_get($img);
	if (!$img) return false;
	$text = '<img src="'
		.(!empty($field['path_json_base']) ? $field['path_json_base'] : '')
		.$img.'"  alt="" class="thumb">';
	return $text;
}

?>