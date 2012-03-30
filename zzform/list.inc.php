<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2004-2010
// Display all or a subset of all records in a list (e. g. table, ul)


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

	// check 'group'
	if (!empty($_GET['group'])) {
		foreach ($zz['fields_in_list'] as $field)
			if ((isset($field['display_field']) && $field['display_field'] === $_GET['group'])
				OR (isset($field['field_name']) && $field['field_name'] === $_GET['group'])
			) {
				if (isset($field['order'])) $zz_conf['group'] = $field['order'];
				else $zz_conf['group'] = $_GET['group'];
			}
	}

	// allow $zz_conf['group'] to be a string
	if (!is_array($zz_conf['group']) AND $zz_conf['group'])
		$zz_conf['group'] = array($zz_conf['group']);
	// initialize internal group_field_no
	$zz_conf['int']['group_field_no'] = array();

	// Turn off hierarchical sorting when using search
	// TODO: implement hierarchical view even when using search
	if (!empty($_GET['q']) AND $zz_conf['search'] AND $zz_conf['show_hierarchy']) {
		$zz_conf['show_hierarchy'] = false;
	}

	// only if search is allowed and there is something
	// if q modify $zz['sql']: add search query
	if (!empty($_GET['q']) AND $zz_conf['search']) 
		$zz['sql'] = zz_search_sql($zz['fields_in_list'], $zz['sql'], $zz['table'], $zz_var['id']['field_name']);	

	$id_field = $zz_var['id']['field_name'];

	if ($zz_conf['list_access']) {
		$zz_conf = array_merge($zz_conf, $zz_conf['list_access']);
		unset($zz_conf['list_access']);
	}
	if ($zz_conf['access'] == 'search_but_no_list' AND empty($_GET['q'])) 
		$zz_conf['show_list'] = false;

	// SQL query without limit and filter for conditions etc.!
	$zz['sql_without_limit'] = $zz['sql'];

	$zz['sql'] = zz_list_filter_sql($zz['sql']);
	if (!$zz['sql']) return zz_return(array($ops, $zz_var));

	list($lines, $total_rows) = zz_list_query($zz, $id_field);
	// save total rows in zz_var for use in zz_nice_title()
	$zz_var['limit_total_rows'] = $total_rows;
	if ($zz_error['error']) return zz_return(array($ops, $zz_var));
	$count_rows = count($lines);

	// don't show anything if there is nothing
	if (!$count_rows) {
		$zz_conf['show_list'] = false;
		if ($text = zz_text('table-empty')) {
			$ops['output'].= '<p>'.$text.'</p>';
		}
		// 404 if limit is too large
		if ($total_rows) $zz_conf['int']['http_status'] = 404;
	}

	// Check all conditions whether they are true;
	if (!empty($zz_conf['modules']['conditions'])) {
		$zz_conditions = zz_conditions_list_check($zz, $zz_conditions, $id_field, array_keys($lines));
	}
	if ($zz_error['error']) return zz_return(array($ops, $zz_var));
	zz_error();
	$ops['output'] .= zz_error_output();

	// check conditions, these might lead to different field definitions for every
	// line in the list output!
	// that means, $table_query cannot be used for the rest but $line_query instead
	// zz_fill_out must be outside if show_list, because it is necessary for
	// search results with no resulting records
	// fill_out, but do not unset conditions
	$zz['fields_in_list'] = zz_fill_out($zz['fields_in_list'], $zz_conf['db_name'].'.'.$zz['table'], 1); 
	if ($zz_conf['show_list']) {
		$conditions_applied = array(); // check if there are any conditions
		array_unshift($lines, '0'); // 0 as a dummy record for which no conditions will be set
		foreach ($lines as $index => $line) {
			$line_query[$index] = $zz['fields_in_list'];
			if ($index) foreach ($line_query[$index] as $fieldindex => $field) {
				// conditions
				if (empty($zz_conf['modules']['conditions'])) continue;
				if (!empty($field['conditions'])) {
					$line_query[$index][$fieldindex] = zz_conditions_merge($field, $zz_conditions['bool'], $line[$id_field]);
					$conditions_applied[$index] = true;
				}
				if (!empty($field['not_conditions'])) {
					$line_query[$index][$fieldindex] = zz_conditions_merge($line_query[$index][$fieldindex], $zz_conditions['bool'], $line[$id_field], true);
					$conditions_applied[$index] = true;
				}
			}
		}
		if (empty($conditions_applied)) {
			// if there is no condition, remove all the identical stuff
			unset($line_query);
			$line_query[0] = $zz['fields_in_list'];	
		}
		// table definition is complete
		// so now we need to check which fields are shown in list mode
		foreach (array_keys($lines) as $index) {
			if (empty($line_query[$index])) continue;
			if ($zz_conf['modules']['debug']) zz_debug('fill_out start');
			$line_query[$index] = zz_fill_out($line_query[$index], $zz_conf['db_name'].'.'.$zz['table'], 2);
			if ($zz_conf['modules']['debug']) zz_debug('fill_out end');
			foreach ($line_query[$index] as $fieldindex => $field) {
				// remove elements from table which shall not be shown
				if ($ops['mode'] == 'export') {
					if (!isset($field['export']) || $field['export']) {
						$table_query[$index][] = $field;
					}
				} else {
					if (empty($field['hide_in_list'])) {
						$table_query[$index][] = $field;
					}
				}
			}
			if ($zz_conf['modules']['debug']) zz_debug('table_query end');
		}
		// now we have the basic stuff in $table_query[0] and $line_query[0]
		// if there are conditions, $table_query[1], [2], [3]... and
		// $line_query[1], [2], [3] ... are set
		$zz['fields_in_list'] = $line_query[0]; // for search form
		unset($line_query);
		unset($lines[0]); // remove first dummy array
		// mark fields as 'show_field' corresponding to grouping
		$table_query = zz_list_show_group_fields($table_query);
	}

	if ($zz_conf['modules']['debug']) zz_debug('table_query set');

	//
	// Table data
	//	

	if ($zz_conf['show_list']) {
		$rows = array();
		$list['current_record'] = NULL;
		$list['sum'] = array();
		$list['sum_group'] = array();
		$list['modes'] = false;		// don't show a table head for link to modes until necessary
		$list['details'] = false;	// don't show a table head for link to details until necessary

		$subselects = array();
		$id_fieldname = false;
		$z = 0;
		$ids = array();
		//$group_hierarchy = false; // see below, hierarchical grouping
		$lastline = false;
		$subselect_init = array();
	
		foreach ($lines as $index => $line) {
			// put lines in new array, rows.
			//$rows[$z][0]['text'] = '';
			//$rows[$z][0]['class'] = array();
			
			$tq_index = (count($table_query) > 1) ? $index : 0;
			$id = $line[$zz_var['id']['field_name']];
			if ($id == $zz_var['id']['value']) $list['current_record'] = $z;
			$sub_id = '';
			if (empty($rows[$z]['group']))
				$rows[$z]['group'] = array();
			if ($zz_conf['group']) {
				$group_count = count($zz_conf['group']);
				foreach ($table_query[$tq_index] as $fieldindex => $field) {
				//	check for group function
					$pos = array_search($fieldindex, $zz_conf['int']['group_field_no']);
					if ($pos === false) continue;
					/*	
						TODO: hierarchical grouping!
						if (!empty($field['show_hierarchy'])) {
							if (!$group_hierarchy) $group_hierarchy = zz_list_get_group_hierarchy($field);
							$rows[$z]['group'][$pos] = zz_list_show_group_hierarchy($line[$field['field_name']], $group_hierarchy);
						} else
					*/
					if (!empty($field['display_field'])) {
						$rows[$z]['group'][$pos] = $line[$field['display_field']];
						// TODOgroup
					} elseif (!empty($field['enum']) AND $field['type'] == 'select') {
						$rows[$z]['group'][$pos] = zz_print_enum($field, $line[$field['field_name']], 'full');
					} elseif (!empty($field['field_name'])) {
						$rows[$z]['group'][$pos] = $line[$field['field_name']];
					}
					$group_count--;
					// we don't need to go throug all records if we found all
					// group records already
					if (!$group_count) {
						ksort($rows[$z]['group']);
						break;
					}
				}
			}
			$zz_conf_record = zz_record_conf($zz_conf); // configuration variables just for this line
			if (!empty($line['zz_conf'])) // check whether there are different configuration variables e. g. for hierarchies
				$zz_conf_record = array_merge($zz_conf_record, $line['zz_conf']);
			if ($zz_conf['select_multiple_records']) { // checkbox for records
				$rows[$z][-1]['text'] = '<input type="checkbox" name="zz_record_id[]" value="'.$line[$id_field].'">'; // $id
				$rows[$z][-1]['class'][] = 'select_multiple_records';
			}

			foreach ($table_query[$tq_index] as $fieldindex => $field) {
				$subselect_index = $fieldindex;
				if ($zz_conf['modules']['debug']) zz_debug("table_query foreach ".$fieldindex);
				// conditions
				if (!empty($zz_conf['modules']['conditions'])) {
					if (!empty($field['conditions'])) {
						$field = zz_conditions_merge($field, $zz_conditions['bool'], $line[$id_field]);
					}
					if (!empty($field['not_conditions'])) {
						$field = zz_conditions_merge($field, $zz_conditions['bool'], $line[$id_field], true);
					}
					if (!empty($zz_conf_record['conditions'])) {
						$zz_conf_record = zz_conditions_merge($zz_conf_record, $zz_conditions['bool'], $line[$id_field], false, 'conf');
						$zz_conf_record = zz_listandrecord_access($zz_conf_record);
						if (!isset($zz_conf_record['add_link']))
							// Link Add new ...
							$zz_conf_record['add_link'] = $zz_conf_record['add'] ? true : false; 
						// $zz_conf is set regarding add, edit, delete
						if (!$zz_conf['add']) $zz_conf['copy'] = false;			// don't copy record (form+links)
					}
				}
				if ($zz_conf['modules']['debug']) zz_debug("table_query foreach cond set ".$fieldindex);
				
				// check all fields next to each other with list_append_next					
				while (!empty($table_query[$tq_index][$fieldindex]['list_append_next'])) {
					$fieldindex++;
				}

				if ($zz_conf['modules']['debug']) zz_debug("table_query before switch ".$fieldindex.'-'.$field['type']);
				$my_row = isset($rows[$z][$fieldindex]) ? $rows[$z][$fieldindex] : array();
				$rows[$z][$fieldindex] = zz_list_field($my_row, $field, $line, $lastline, $zz_var, $zz['table'], $ops['mode'], $zz_conf_record);

				// Sums
				if (isset($field['sum']) AND $field['sum'] == true 
					AND (empty($field['calculation']) OR $field['calculation'] != 'sql')) {
					if (!isset($list['sum'][$field['title']])) $list['sum'][$field['title']] = 0;
					$value = $rows[$z][$fieldindex]['value'];
					if (strstr($value, ':')) {
						$value = explode(':', $value);
						$value = $value[0] + ($value[1]/60);
					}
					$list['sum'][$field['title']] += $value;
					$list['sum_group'] = zz_list_group_sum($rows[$z]['group'], $list['sum_group'], $field['title'], $value);
				}
				
				if ($field['type'] == 'subtable' AND !empty($field['subselect']['sql'])) {
					// fill array subselects, just in row 0, will always be the same!
					if (empty($subselect_init[$subselect_index])) {
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
						$id_fieldname = $foreign_key_field['field_name'];
						if ($translation_key_field) {
							$key_fieldname = $zz_var['id']['field_name'];
							$field['subselect']['translation_key'] = $translation_key_field['translation_key'];
						} else { // $foreign_key_field
							// if main field name and foreign field name differ, use main ID for requests
							if (!empty($foreign_key_field['key_field_name'])) // different fieldnames
								$key_fieldname = $foreign_key_field['key_field_name'];
							else
								$key_fieldname = $foreign_key_field['field_name'];
						}
						// id_field = joined_table.field_name
						if (empty($field['subselect']['table'])) {
							$field['subselect']['table'] = $field['table'];
						}
						$field['subselect']['id_table_and_fieldname'] = $field['subselect']['table'].'.'.$id_fieldname;
						// just field_name
						$field['subselect']['id_fieldname'] = $id_fieldname;
						$field['subselect']['fieldindex'] = $fieldindex;
						$field['subselect']['table_name'] = $field['table_name'];
						$subselects[] = $field['subselect'];
						$subselect_init[$subselect_index] = true;
					}
					if (empty($line[$key_fieldname])) {
						$zz_error[] = array(
							'msg_dev' => 'Wrong key_field_name. Please set $zz_sub["fields"]['
							.'n]["key_field_name"] to something different: '.implode(', ', array_keys($line)));
					}
					$sub_id = $line[$key_fieldname]; // get correct ID
				}

				// group: go through everything but don't show it in list
				// TODO: check that it does not collide with append_next
				if ($zz_conf['group']) {
					$pos = array_search($fieldindex, $zz_conf['int']['group_field_no']);
					if ($pos !== false) {
						$list['group_titles'][$z][$pos] = implode(' &#8211; ', $rows[$z]['group']);
						unset ($rows[$z][$fieldindex]);
						ksort($list['group_titles'][$z]);
					}
				}
				if ($zz_conf['modules']['debug']) zz_debug('table_query end '.$fieldindex.'-'.$field['type']);
			}
			if ($sub_id) $ids[$z] = $sub_id; // for subselects
			$lastline = $line;

			$rows[$z]['modes'] = zz_list_modes($id, $zz_var, $zz_conf_record);
			if ($rows[$z]['modes']) $list['modes'] = true; // need a table row for this

			if (!empty($zz_conf_record['details'])) {
				$rows[$z]['details'] = zz_show_more_actions($zz_conf_record['details'], 
					$zz_conf_record['details_url'],  $zz_conf_record['details_base'], 
					$zz_conf_record['details_target'], $zz_conf_record['details_referer'], 
					$zz_conf_record['details_sql'], $id, $line);
				$list['details'] = true; // need a table row for this
			}
			$z++;
		}
		$rows = zz_list_get_subselects($rows, $subselects, $ids);
	}
	unset($lines);
	
	//
	// Table head
	//

	if ($zz_conf['show_list']) {
		$list['where_values'] = !empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : '';
		$head = zz_list_head($table_query[0], $list['where_values']);
		unset($table_query);
	}

	//
	// Export
	//

	if ($ops['mode'] === 'export') {
		if ($zz_conf['show_list']) {
			$ops['output'] = array(
				'head' => $head,
				'rows' => $rows
			);
		}
		if ($zz_conf['modules']['debug']) zz_debug('end');
		$ops = zz_export($ops);
		return zz_return(array($ops, $zz_var));
	}
	
	//
	// Table head, table foot, list body, closing list
	//

	$search_form = zz_search_form($zz['fields_in_list'], $zz['table'], $total_rows, $count_rows);
	$ops['output'] .= $search_form['top'];
	
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
			$ops['output'] .= '<input type="hidden" name="zz_action" value="Multiple action"><input type="submit" value="'
				.zz_text('Delete selected records').'" name="multiple_delete">'
				.'</form>'."\n";
		}
	}

	//
	// Buttons below table (add, record nav, search)
	//

	// Add new record
	if (!($zz_conf['access'] == 'search_but_no_list' AND empty($_GET['q']))) {
		// filter, if there was a list
		if ($zz_conf['filter'] AND $zz_conf['show_list'] 
			AND in_array($zz_conf['filter_position'], array('bottom', 'both')))
			$ops['output'] .= zz_filter_selection($zz_conf['filter']);
		$toolsline = array();
		$base_url = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
			.$zz_conf['int']['url']['?&'];

		// normal add button, only if list was shown beforehands
		if ($ops['mode'] != 'add' && $zz_conf['add_link'] AND !is_array($zz_conf['add']) && $zz_conf['show_list']) {
			$toolsline[] = '<a accesskey="n" href="'.$base_url.'mode=add'
				.$zz_var['extraGET'].'">'.zz_text('Add new record').'</a>';
			if ($zz_conf['import']) {
				$toolsline[] = '<a href="'.$base_url.'mode=import'
					.$zz_var['extraGET'].'">'.zz_text('Import data').'</a>';
			}
		}
		// multi-add-button, also show if there was no list, because it will only be shown below records!
		
		if ($ops['mode'] != 'add' && $zz_conf['add_link'] AND is_array($zz_conf['add'])) {
			ksort($zz_conf['add']); // if some 'add' was unset before, here we get new numerical keys
			$ops['output'] .= '<p class="add-new">'.zz_text('Add new record').': ';
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

		if ($zz_conf['export'] AND $total_rows) 
			$toolsline = array_merge($toolsline, zz_export_links($base_url, $zz_var['extraGET']));
		if ($toolsline)
			$ops['output'] .= '<p class="add-new bottom-add-new">'.implode(' | ', $toolsline).'</p>';
		$ops['output'] .= zz_list_total_records($total_rows);
		$ops['output'] .= zz_list_pages($zz_conf['limit'], $zz_conf['int']['this_limit'], $total_rows);	
		// TODO: NEXT, PREV Links at the end of the page
		// Search form
		$ops['output'] .= $search_form['bottom'];
	}
	return zz_return(array($ops, $zz_var));
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
 * @global array $zz_conf
 *		$zz_conf['int']['url']
 * @return string HTML output, all filters
 */
function zz_filter_selection($filter) {
	if (!is_array($filter)) return false;
	global $zz_conf;
	
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
		$other_filters['filter'] = !empty($_GET['filter']) ? $_GET['filter'] : array();
		unset($other_filters['filter'][$f['identifier']]);
		$qs = zz_edit_query_string($qs, array(), $other_filters);
		
		if (!empty($f['selection'])) {
			// $f['selection'] might be empty if there's no record in the database
			foreach ($f['selection'] as $id => $selection) {
				$is_selected = ((isset($_GET['filter'][$f['identifier']]) 
					AND $_GET['filter'][$f['identifier']] == $id))
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
		} elseif (isset($_GET['filter'][$f['identifier']])) {
			// no filter selections are shown, but there is a current filter, 
			// so show this
			$filter[$index]['output'][] = array(
				'title' => htmlspecialchars($_GET['filter'][$f['identifier']]),
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
		if (isset($_GET['filter'][$f['identifier']])
			AND $_GET['filter'][$f['identifier']] !== '0') $link_all = true;
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
 */
function zz_list_filter_sql($sql) {
	global $zz_conf;
	global $zz_error;

	if (empty($zz_conf['filter'])) {
		if (!empty($_GET['filter'])) {
			$filter = array_keys($_GET['filter']);
			$filter = htmlspecialchars(end($filter));
			$zz_conf['int']['http_status'] = 404;
			$zz_error[] = array(
				'msg' => sprintf(zz_text('A filter for the selection "%s" does not exist.'), $filter),
				'level' => E_USER_NOTICE
			);
		}
		return $sql;
	}
	if (!isset($_GET['filter'])) return $sql;

	$identifiers = array();
	foreach ($zz_conf['filter'] AS $filter) {
		$identifiers[] = $filter['identifier'];
		if (!in_array($filter['identifier'], array_keys($_GET['filter']))) continue;
		if (empty($filter['where'])) continue;
		
		if ($_GET['filter'][$filter['identifier']] === '0' AND $filter['default_selection'] !== '0'
			AND $filter['default_selection'] !== 0) {
			// do nothing
		} elseif (zz_in_array_str($_GET['filter'][$filter['identifier']], array_keys($filter['selection']))
			AND $filter['type'] == 'list') {
			// it's a valid filter, so apply it.
			$filter_value = $_GET['filter'][$filter['identifier']];
			// allow ! as a symbol (may be escaped by \)
			// for !=
			$equals = ' = ';
			if (substr($filter_value, 0, 1) === '!') {
				$filter_value = substr($filter_value, 1);
				$equals = ' != ';
			} elseif (substr($filter_value, 0, 1) === '\\') {
				$filter_value = substr($filter_value, 1);
			}
			if ($filter_value == 'NULL') {
				$sql = zz_edit_sql($sql, 'WHERE', 'ISNULL('.$filter['where'].')');
			} elseif ($filter_value == '!NULL') {
				$sql = zz_edit_sql($sql, 'WHERE', '!ISNULL('.$filter['where'].')');
			} else {
				$sql = zz_edit_sql($sql, 'WHERE', $filter['where'].$equals.'"'.$filter_value.'"');
			}
		} elseif ($filter['type'] == 'list' AND is_array($filter['where'])) {
			// valid filter with several wheres
			$wheres = array();
			foreach ($filter['where'] AS $filter_where) {
				if ($_GET['filter'][$filter['identifier']] == 'NULL') {
					$wheres[] = 'ISNULL('.$filter_where.')';
				} elseif ($_GET['filter'][$filter['identifier']] == '!NULL') {
					$wheres[] = '!ISNULL('.$filter_where.')';
				} else {
					$wheres[] = $filter_where.' = "'.$_GET['filter'][$filter['identifier']].'"';
				}
			}
			$sql = zz_edit_sql($sql, 'WHERE', implode(' OR ', $wheres));
		} elseif ($filter['type'] == 'like') {
			// valid filter with LIKE
			$sql = zz_edit_sql($sql, 'WHERE', $filter['where'].' LIKE "%'.$_GET['filter'][$filter['identifier']].'%"');
		} else {
			// invalid filter value
			$zz_conf['int']['http_status'] = 404;
			$zz_error[] = array(
				'msg' => sprintf(zz_text('"%s" is not a valid value for the selection "%s". Please select a different filter.'), 
					htmlspecialchars($_GET['filter'][$filter['identifier']]), $filter['title']),
				'level' => E_USER_NOTICE
			);
			$sql = false;
		}
	}
	// test filter identifiers if they exist
	foreach (array_keys($_GET['filter']) AS $identifier) {
		if (in_array($identifier, $identifiers)) continue;
		$filter = htmlspecialchars($identifier);
		$zz_conf['int']['http_status'] = 404;
		$zz_error[] = array(
			'msg' => sprintf(zz_text('A filter for the selection "%s" does not exist.'), $filter),
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

	$total_rows = zz_count_rows($zz['sql'], $zz['table'].'.'.$id_field);
	if (!$total_rows) return array(array(), 0);
	
	// ORDER must be here because of where-clause
	$zz['sql'] .= !empty($zz['sqlorder']) ? ' '.$zz['sqlorder'] : '';
	// Alter SQL query if GET order (AND maybe GET dir) are set
	$zz['sql'] = zz_sql_order($zz['fields_in_list'], $zz['sql']);

	if (empty($zz_conf['show_hierarchy'])) {
		return array(zz_list_query_flat($zz['sql'], $id_field), $total_rows);
	} else {
		return zz_list_query_hierarchy($zz['sql'], $id_field, $zz['table']);
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
function zz_list_query_flat($sql, $id_field) {
	global $zz_conf;

	if ($zz_conf['int']['this_limit']) { 
		// set a standard value for limit
		// this standard value will only be used on rare occasions, when NO limit is set
		// but someone tries to set a limit via URL-parameter
		if (!$zz_conf['limit']) $zz_conf['limit'] = 20; 
		$sql .= ' LIMIT '.($zz_conf['int']['this_limit']-$zz_conf['limit']).', '.($zz_conf['limit']);
	}

	// read rows from database
	return zz_db_fetch($sql, $id_field);
}

/**
 * Query records for list view, flat mode
 *
 * @param string $sql SQL query ($zz['sql'])
 * @param string $id_field ($zz_var['id']['field_name'])
 * @param string $table ($zz['table'])
 * @global array $zz_conf
 * @return array $lines
 */
function zz_list_query_hierarchy($sql, $id_field, $table) {
	global $zz_conf;

	// hierarchical list view
	// for performance reasons, we only get the fields which are important
	// for the hierarchy (we need to get all records)
	$lines = zz_db_fetch($sql, array($id_field, $zz_conf['hierarchy']['mother_id_field_name']), 'key/value'); 
	if (!$lines) return array(array(), 0);

	$h_lines = array();
	foreach ($lines as $id => $mother_id) {
		// sort lines by mother_id
		if ($zz_conf['show_hierarchy'] === true) 
			$zz_conf['show_hierarchy'] = 'NULL';
		if ($id == $zz_conf['show_hierarchy']) {
			// get uppermost line if show_hierarchy is not NULL!
			$mother_id = 'TOP';
		} elseif (empty($mother_id))
			$mother_id = 'NULL';
		$h_lines[$mother_id][$id] = $id;
	}
	if (!$h_lines) return array(array(), 0);

	$lines = array(); // unset and initialize
	$level = 0; // level (hierarchy)
	$i = 0; // number of record, for LIMIT
	$my_lines = zz_list_hierarchy($h_lines, $zz_conf['show_hierarchy'], $id_field, $level, $i);
	$total_rows = $i; // sometimes, more rows might be selected beforehands,
	// but if show_hierarchy has ID value, not all rows are shown
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
		if ($zz_conf['int']['this_limit']) {
			$sql = zz_edit_sql($sql, 'WHERE', '`'.$table.'`.'.$id_field
				.' IN ('.implode(',', array_keys($lines)).')');
		} // else sql remains same
		$lines = zz_array_merge($lines, zz_db_fetch($sql, $id_field));
	}
	foreach ($lines as $line) {
		if (empty($line['zz_hidden_line'])) continue;
		// get record which is normally beyond our scope via ID
		$sql = zz_edit_sql($sql, 'WHERE', 'nothing', 'delete');
		$sql = zz_edit_sql($sql, 'WHERE', '`'.$table.'`.'.$id_field.' = '.$line[$id_field]);
		$line = zz_db_fetch($sql);
		if ($line) {
			$lines[$line[$id_field]] = array_merge($lines[$line[$id_field]], $line);
		}
	}
	return array($lines, $total_rows);
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
	if ($modes) return implode('&nbsp;| ', $modes);
	else return false;
}

/**
 * Output and formatting of a single table cell in list mode
 *
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
function zz_list_field($row, $field, $line, $lastline, $zz_var, $table, $mode, $zz_conf_record) {
	global $zz_conf;
	// shortcuts, isset: value might be 0
	if (!empty($field['field_name']) AND isset($line[$field['field_name']]))
		$row['value'] = $line[$field['field_name']];
	else
		$row['value'] = '';

	// set 'class'
	if (!isset($row['class'])) $row['class'] = array();
	elseif (!is_array($row['class'])) $row['class'] = array($row['class']);
	// if table row is affected by where, mark this
	$where_table = !empty($zz_var['where'][$table]) ? $zz_var['where'][$table] : '';
	// set class depending on where and field info
	$field['level'] = zz_list_field_level($field, $line);
	$row['class'] = array_merge($row['class'], zz_field_class($field, $where_table));
	if (!empty($field['field_name']) AND !empty($lastline[$field['field_name']]) 
		AND $row['value'] == $lastline[$field['field_name']]) {
		$row['class'][] = 'identical_value';
	}
				
	// set 'text'
	if (empty($row['text'])) $row['text'] = '';

	//	if there's a link, glue parts together
	$link = false;
	if ($mode != 'export' OR $_GET['export'] == 'kml') $link = zz_set_link($field, $line);

	$mark_search_string = 'field_name';
	$text = false;

	if (isset($field['display_field'])) {
		$text = $line[$field['display_field']];
		if (!empty($field['translate_field_value'])) $text = zz_text($text);
		$text = htmlchars($text);
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
				$text .= hours($row['value']);
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
			if (isset($field['path'])) {
				if ($img = zz_makelink($field['path'], $line, 'image')) {
					$text .= $link.$img.($link ? '</a>' : '');
				} elseif (isset($field['default_image'])) {
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
				$img = zz_makelink($field['path_json_request'], $line);
				if ($img = brick_request_getjson($img)) {
					$text = '<img src="'
						.(!empty($field['path_json_base']) ? $field['path_json_base'] : '')
						.$img.'"  alt="" class="thumb">';
				}
			}
			break;
		case 'subtable':
			// main stuff will come from 'subselect', otherwise this field has no content
			$link = false;
			break;
		case 'url':
		case 'mail':
		case 'mail+name':
			if ($field['type'] === 'url') {
				$text = zz_cut_length(htmlchars($row['value']), $zz_conf_record['max_select_val_len']);
			} else {
				$text = htmlspecialchars($row['value']);
			}
			break;
		case 'ipv4':
			$text = long2ip($row['value']);
			break;
		case 'unix_timestamp':
			$text = date('Y-m-d H:i:s', $row['value']);
			break;
		case 'timestamp':
			$text = timestamp2date($row['value']);
			break;
		case 'date':
			$text = datum_de($row['value']);
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
				switch ($field['number_type']) {
				case 'currency':
					$text = waehrung($row['value'], '');
					break;
				case 'latitude':
				case 'longitude':
					if (!$row['value']) break;
					if (empty($field['geo_format'])) $field['geo_format'] = 'dms';
					$text = zz_geo_coord_out($row['value'], $field['number_type'], $field['geo_format']);
					break;
				default:
					$text = $row['value'];
				}
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
				$text = nl2br(htmlchars($text));
			}
			break;
		default:
			$text = $row['value'];
			if (empty($field['list_format'])) {
				$text = nl2br(htmlchars($text));
			}
			break;
		}
	}
	if (!empty($field['translate_field_value'])) {
		$text = zz_text($text);
	}
	if (!empty($field['list_format']) AND $text) {
		if (!empty($zz_conf['modules']['debug'])) zz_debug('start', $field['list_format']);
		$text = $field['list_format']($text);
		if (!empty($zz_conf['modules']['debug'])) zz_debug('end');
	}
	if (!empty($field['hide_zeros']) AND !$text) {
		$text = '';
	}
	if ($text === '' OR $text === false) return $row;

	if ($mark_search_string AND $mode != 'export') {
		$text = zz_mark_search_string($text, $field[$mark_search_string], $field);
	}

	// add prefixes etc. to 'text'		
	if (!empty($field['list_prefix'])) {
		$row['text'] .= zz_text($field['list_prefix']);
	}
	if (!empty($field['list_abbr']) AND $mode != 'export') {
		$row['text'] .= '<abbr title="'.htmlspecialchars($line[$field['list_abbr']]).'">';
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
 * @param array $table_query
 * @param int $z
 * @param array $table (foreign_key_field_name => value)
 * @param array $sum (field_name => value)
 * @global array $zz_conf ($zz_conf['int']['group_field_no'])
 * @return string HTML output of table foot
 */
function zz_field_sum($table_query, $z, $table, $sum) {
	global $zz_conf;
	$tfoot_line = '';
	foreach ($table_query as $index => $field) {
		if (!$field['show_field']) continue;
		if (in_array($index, $zz_conf['int']['group_field_no'])) continue;
		if ($field['type'] == 'id' && empty($field['show_id'])) {
			$tfoot_line .= '<td class="recordid">'.$z.'</td>';
		} elseif (!empty($field['sum'])) {
			$tfoot_line .= '<td'.zz_field_class($field, (!empty($table) ? $table : ''), true).'>';
			$value = $sum[$field['title']];
			if (isset($field['calculation']) AND $field['calculation'] == 'hours')
				$value = hours($value);
			if (isset($field['number_type']) && $field['number_type'] == 'currency') 
				$value = waehrung($value, '');
			if (!empty($field['list_format']) AND $value)
				$value = $field['list_format']($value);

			$tfoot_line.= $value;
			if (isset($field['unit']) && $value) 
				$tfoot_line .= '&nbsp;'.$field['unit'];	
			$tfoot_line .= '</td>';
		} else {
			$tfoot_line .= '<td'
				.(!empty($field['class']) ? ' class="'.$field['class'].'"' : '')
				.'>&nbsp;</td>';
		}
	}
	return $tfoot_line;
}

/**
 * sorts $lines hierarchically
 *
 * @param array $h_lines
 * @param string $show_hierarchy
 * @param string $id_field
 * @param int $level
 * @param int $i
 * @return array $my_lines
 */
function zz_list_hierarchy($h_lines, $show_hierarchy, $id_field, $level, &$i) {
	$my_lines = array();
	$show_only = array();
	if (!$level AND $show_hierarchy != 'NULL' AND !empty($h_lines['TOP'])) {
		// show uppermost line
		$h_lines['TOP'][0]['zz_level'] = $level;
		$my_lines[$i][$id_field] = $h_lines['TOP'][$show_hierarchy];
		// this page has child pages, don't allow deletion
		$my_lines[$i]['zz_conf']['delete'] = false; 
		$i++;
	}
	if ($show_hierarchy != 'NULL') $level++; // don't indent uppermost level if top category is NULL
	if ($show_hierarchy == 'NULL' AND empty($h_lines[$show_hierarchy])) {
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
	if (!empty($h_lines[$show_hierarchy])) {
		foreach ($h_lines[$show_hierarchy] as $h_line) {
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
 *		'type', 'field_name', 'link', 'link_no_append', 'link_referer',
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
		$link = zz_makelink($field['link'], $line)
			.(empty($field['link_no_append']) ? $line[$field['field_name']] : '');
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
 * @param array $field $zz['fields'][n]-field definition
 * @param int $index index 'n' of $zz['fields']
 * @global array $zz_conf
 *		string 'group', array 'int'['group_field_no'] (will be set to 'n') 
 * @return bool true/false if field will be shown (group: false, otherwise true)
 */
function zz_list_group_field_no($field, $index) {
	global $zz_conf;
	if (!isset($zz_conf['int']['group_field_no'])) 
		$zz_conf['int']['group_field_no'] = array();
	if (!empty($field['display_field'])) {
		$pos = array_search($field['display_field'], $zz_conf['group']);
		if ($pos !== false) {
			$zz_conf['int']['group_field_no'][$pos] = $index;
			ksort($zz_conf['int']['group_field_no']);
			return false;
		}
	}
	if (!empty($field['field_name'])) {
		$pos = array_search($field['field_name'], $zz_conf['group']);
		if ($pos !== false) {
			$zz_conf['int']['group_field_no'][$pos] = $index;
			ksort($zz_conf['int']['group_field_no']);
			return false;
		}
	}
	return true;
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
 * @param string $scope 'body', todo: 'head' (not yet implemented)
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
	$links[] = array(
		'link'	=> zz_list_pagelink($this_limit-$limit_step, $this_limit, 0, $url),
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

	if ($zz_conf['limit_show_range'] AND $total_rows >= $zz_conf['limit_show_range']) {
		$rec_start = $this_limit - ($zz_conf['limit_show_range']/2 + 2*$limit_step);
		if ($rec_start < 0) $rec_start = 0;
		elseif ($rec_start > 0) {
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

	for ($i = $rec_start; $i <= $rec_end; $i = $i+$limit_step) { 
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
	if ($zz_conf['modules']['debug']) zz_debug('search query', $sql);
	static $calls;
	$calls++;

	// get scope
	$scope = (!empty($_GET['scope']) AND $calls === 1) ? $_GET['scope'] : '';
	
	// get search operator, globally for all fields
	$searchword = $_GET['q'];
	if (substr($searchword, 0, 1) == ' ' AND substr($searchword, -1) == ' ') {
		$searchop = '=';
		$searchword = trim($searchword);
	} elseif (substr($searchword, 0, 1) == ' ') {
		$searchop = 'LIKE%';
		$searchword = trim($searchword);
	} elseif (substr($searchword, -1) == ' ') {
		$searchop = '%LIKE';
		$searchword = trim($searchword);
	} elseif (substr($searchword, 0, 2) == '> ') {
		$searchop = '>';
		$searchword = trim(substr($searchword, 1));
	} elseif (substr($searchword, 0, 2) == '< ') {
		$searchop = '<';
		$searchword = trim(substr($searchword, 1));
	} elseif (substr($searchword, 0, 3) == '<= ') {
		$searchop = '<=';
		$searchword = trim(substr($searchword, 2));
	} elseif (substr($searchword, 0, 3) == '>= ') {
		$searchop = '>=';
		$searchword = trim(substr($searchword, 2));
	} elseif (substr($searchword, 0, 2) == '= ') {
		$searchop = '=';
		$searchword = trim(substr($searchword, 1));
	} elseif (substr($searchword, 0, 2) == '- ' 
		AND strstr(trim(substr($searchword, 1)), ' ')) {
		$searchop = 'BETWEEN';
		$searchword = trim(substr($searchword, 1));
		$searchword = explode(" ", $searchword);
	} elseif ($searchword === '!NULL' OR $searchword === 'NULL') {
		$searchop = $searchword;
		$searchword = false;
	} else {
		$searchop = '%LIKE%';
		// first slash will be ignored, this is used to escape reserved characters
		if (substr($searchword, 0, 1) == '\\') $searchword = substr($searchword, 1);
	}
	$searchword = zz_db_escape($searchword);

	// get fields
	$searchfields = array();
	$q_search = array();
	// fields that won't be used for search
	$unsearchable_fields = array('image', 'calculated', 'timestamp', 'upload_image', 'option'); 
	$search = false;
	$found = false;
	foreach ($fields as $field) {
		if (empty($field)) continue;
		// this field is explicitly excluded from search
		if (!empty($field['exclude_from_search'])) continue;
		// this is a field which cannot be searched
		if (empty($field['type'])) $field['type'] = 'text';
		if (in_array($field['type'], $unsearchable_fields)) continue;
		
		if ($scope) {
			$search = zz_search_scope($field, $table, $scope);
		} else {
			if ($field['type'] === 'subtable') $search = 'subtable';
			else $search = 'field';
		}
		$subsearch = false;
		switch ($search) {
		case 'field':
			$subsearch = zz_search_field($field, $table, $searchop, $searchword);
			$found = true;
			break;
		case 'subtable':
			$subsearch = zz_search_subtable($field, $table, $main_id_fieldname);
			$found = true;
			break;
		default:
			continue;
		}
		if ($subsearch) $q_search[] = $subsearch;
		// additional between search
		if (isset($field['search_between'])) {
			$q_search[] = sprintf($field['search_between'], '"'.$searchword.'"', '"'.$searchword.'"');
		}
	}

	if ($scope AND !$found) 
		$zz_conf['int']['http_status'] = 404;

	if ($q_search) {
		$q_search = '('.implode(' OR ', $q_search).')';
	} else {
		$q_search = 'NULL';
	}
	$sql = zz_edit_sql($sql, 'WHERE', $q_search);

	if ($zz_conf['modules']['debug']) zz_debug("end; search query", $sql);
	return $sql;
}

/**
 * get part of SQL query for search in a field
 *
 * @param array $field field definition
 * @param string $table
 * @param string $searchop
 * @param string $searchword
 * @return string part of query
 */
function zz_search_field($field, $table, $searchop, $searchword) {
	global $zz_conf;
	// get field name
	if (isset($field['search'])) {
		$fieldname = $field['search'];
	} elseif (isset($field['display_field'])) {
		// it makes more sense to search through values than IDs
		$fieldname = $field['display_field'];
	} elseif (!empty($field['field_name'])) {
		// standard: use table- and field name
		$fieldname = $table.'.'.$field['field_name'];
		if ($searchword) {
			$searchword = zz_search_checkfield($field['field_name'], $table, $searchword);
			if (!$searchword) return '';
		}
	} else {
		return '';
	}

	// get searchword/operator, per field type
	$datetime = in_array($field['type'], array('date', 'datetime', 'time', 'timestamp')) ? true : false;
	if ($datetime and $searchword AND !is_array($searchword)) {
		$timesearch = zz_search_time($searchword);
		if ($timesearch) {
			switch ($field['type']) {
			case 'datetime':
				$searchword = date('Y-m-d', $timesearch).'%';
				if ($searchop == '%LIKE%') $searchop = 'LIKE%';
				break;
			case 'time':
				$searchword = date('H:i:s', $timesearch);
				if ($searchop == '%LIKE%') $searchop = '=';
				break;
			case 'date':
				$searchword = date('Y-m-d', $timesearch);
				if ($searchop == '%LIKE%') $searchop = '=';
				break;
			}
		} elseif (preg_match('/q\d(.)[0-9]{4}/i', $searchword, $separator)) {
			$searchword = trim(substr($searchword, 1));
			$searchword = explode($separator[1], $searchword);
			$searchop = "QUARTER";
		}
	}

	// build search query part
	switch ($searchop) {
	case 'NULL':
		if (!zz_db_field_null($field['field_name'], $table)) return '';
		return sprintf('ISNULL(%s)', $fieldname);
	case '!NULL':
		if (!zz_db_field_null($field['field_name'], $table)) return '';
		return sprintf('!ISNULL(%s)', $fieldname);
	case '<':
		return sprintf('%s < "%s"', $fieldname, $searchword);
	case '<=':
		return sprintf('%s <= "%s"', $fieldname, $searchword);
	case '>':
		return sprintf('%s > "%s"', $fieldname, $searchword);
	case '>=':
		return sprintf('%s >= "%s"', $fieldname, $searchword);
	case 'BETWEEN':
		return sprintf('%s >= "%s" AND %s <= "%s"', $fieldname, $searchword[0],
			$fieldname, $searchword[1]);
	case 'QUARTER':
		// todo: improve to use indices, BETWEEN year_begin and year_end ...
		return sprintf('(YEAR(%s) = "%s" AND QUARTER(%s) = "%s")', $fieldname, 
			$searchword[1], $fieldname, $searchword[0]);
	case '=':
		return sprintf('%s = "%s"', $fieldname, $searchword);
	case '%LIKE':
		$collation = zz_db_field_collation('search', $table, $field);
		if ($collation === NULL) return '';
		return sprintf('%s LIKE %s"%%%s"', $fieldname, $collation, $searchword);
	case 'LIKE%':
		$collation = zz_db_field_collation('search', $table, $field);
		if ($collation === NULL) return '';
		return sprintf('%s LIKE %s"%s%%"', $fieldname, $collation, $searchword);
	case '%LIKE%':
	default:
		$collation = zz_db_field_collation('search', $table, $field);
		if ($collation === NULL) return '';
		return sprintf('%s LIKE %s"%%%s%%"', $fieldname, $collation, $searchword);
	}
	return '';
}

/**
 * check if field_name matches scope
 *
 * @param array $field field defintion
 * @param string $scope
 * @return string where to search: field | subtable
 */
function zz_search_scope($field, $table, $scope) {
	$search_field = false;
	if (!isset($field['sql']) AND !empty($field['field_name'])) {
		// check if scope = field_name but don't search in IDs
		// check if scope = table.field_name but don't search in IDs
		if ($scope === $field['field_name']) {
			$search_field = true;
		} elseif ($scope === $table.'.'.$field['field_name']) {
			$search_field = true;
		}
	} elseif (isset($field['display_field']) AND $scope === $field['display_field']) {
		$search_field = true;
	}

	if ($search_field)
		return 'field';
	if (isset($field['table_name']) AND $scope === $field['table_name'])
		return 'subtable';
	return false;
}

/**
 * checks if searchword is date, i. e. usable in searching for time/date 
 *
 * @param mixed $searchword
 * @return int time
 */
function zz_search_time($searchword) {
	// allow searching with strtotime, but do not convert years (2000)
	// or year-month (2004-12)
	if (is_array($searchword)) return false;
	if (preg_match('/^\d{1,4}-*\d{0,2}-*\d{0,2}$/', trim($searchword))) return false;
	return strtotime($searchword);
}

/**
 * checks whether search string will match field at all
 * removes it from search query if not
 *
 * @param string $field_name
 * @param string $table
 * @param string $searchword
 * @global array $zz_conf
 * @return string
 */
function zz_search_checkfield($field_name, $table, $searchword) {
	global $zz_conf;
	if (!strstr('.', $table)) $table = $zz_conf['db_name'].'.'.$table;
	$column = zz_db_columns($table, $field_name);
	$type = $column['Type'];
	if ($pos = strpos($type, '('))
		$type = substr($type, 0, $pos);
	$unsigned = substr($column['Type'], -8) == 'unsigned' ? true: false;
	
	// check if numeric value
	switch ($type) {
	case 'int':
	case 'tinyint':
	case 'smallint':
	case 'mediumint':
	case 'bigint':
	case 'decimal':
	case 'float':
	case 'double':
	case 'real':
		if (strstr($searchword, ',')) {
			// oversimple solution for . or , as decimal separator
			// now you won't be able to search for 1,000,000 etc.
			$number = str_replace(',', '.', $searchword);
			if (is_numeric($number)) {
				$searchword = $number;
			}
		}
		if (!is_numeric($searchword)) return false;
		if ($unsigned AND substr($searchword, 0, 1) == '-') return false;
		break;
	case 'binary':
	case 'varbinary':
	case 'tinyblob':
	case 'mediumblob':
	case 'longblob':
		// no search here
		return false;

	case 'enum':
	case 'set':
		break;
	case 'geometry':
	case 'point':
	case 'linestring':
	case 'polygon':
	case 'multipoint':
	case 'multilinestring':
	case 'multipolygon':
	case 'geometrycollection':
		return false;
	default:
		break;
	}

	// BIT BOOLEAN SERIAL
	// DATE DATETIME TIMESTAMP TIME YEAR
	// CHAR VARCHAR TEXT TINYTEXT MEDIUMTEXT LONGTEXT

	return $searchword;
}

/**
 * performs a search in a subtable and returns IDs of main record to include
 * in search results
 *
 * @param array $field
 * @param string $main_id_fieldname
 * @return string part of SQL query
 */
function zz_search_subtable($field, $table, $main_id_fieldname) {
	$foreign_key = '';
	foreach ($field['fields'] as $f_index => $subfield) {
		if (empty($subfield['type'])) continue;
		if ($subfield['type'] != 'foreign_key') continue;
		$foreign_key = $subfield['field_name'];
		// do not search in foreign_key since this is the same
		// as the main record
		unset($field['fields'][$f_index]);
	}
	if (!$foreign_key) {
		global $zz_error;
		$zz_error[]['msg_dev'] = zz_text('Subtable definition is wrong. There must be a field which is defined as "foreign_key".');
		zz_error();
		exit;
	}
	$subsql = zz_search_sql($field['fields'], $field['sql'], $field['table'], $main_id_fieldname);
	$ids = zz_db_fetch($subsql, $foreign_key, '', 'Search query for subtable.', E_USER_WARNING);
	if (!$ids) return false;
	return $table.'.'.$main_id_fieldname.' IN ('.implode(',', array_keys($ids)).')';
}

/** 
 * Generates search form and link to show all records
 * 
 * @param array $fields			field definitions ($zz)
 * @param string $table			name of database table
 * @param int $total_rows		total rows in database selection
 * @param string $count_rows	number of rows shown on html page
 * @return string $output		HTML output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_search_form($fields, $table, $total_rows, $count_rows) {
	global $zz_conf;
	// Search Form
	$search_form['top'] = false;
	$search_form['bottom'] = false;
	if (!$zz_conf['search']) return $search_form;
	// don't show search form if all records are already shown
	if ($total_rows <= $count_rows AND empty($zz_conf['search_form_always'])
		AND empty($_GET['q'])) {
		return $search_form;
	}

	// show search form only if there are records as a result of this query; 
	// q: show search form if empty search result occured as well
	if (!$total_rows AND !isset($_GET['q'])) return $search_form;

	$output = '';
	$self = $zz_conf['int']['url']['self'];
	// fields that won't be used for search
	$unsearchable_fields = array('image', 'calculated', 'timestamp', 'upload_image', 'option');
	$output = "\n".'<form method="GET" action="'.$self
		.'" id="zzsearch" accept-charset="'.$zz_conf['character_set'].'"><p>';
	if ($qs = $zz_conf['int']['url']['qs'].$zz_conf['int']['url']['qs_zzform']) { 
		// do not show edited record, limit, ...
		$unwanted_keys = array('q', 'scope', 'limit', 'mode', 'id', 'add', 'zzaction', 'zzhash'); 
		$output .= zz_querystring_to_hidden(substr($qs, 1), $unwanted_keys);
		// remove unwanted keys from link
		$self .= zz_edit_query_string($qs, $unwanted_keys); 
	}
	$output.= '<input type="search" size="30" name="q"';
	if (isset($_GET['q'])) $output.= ' value="'.htmlchars($_GET['q']).'"';
	$output.= '>';
	$output.= '<input type="submit" value="'.zz_text('search').'">';
	$output.= ' '.zz_text('in').' ';	
	$output.= '<select name="scope">';
	$output.= '<option value="">'.zz_text('all fields').'</option>'."\n";
	foreach ($fields as $field) {
		if (in_array($field['type'], $unsearchable_fields)) continue;
		if (!empty($field['exclude_from_search'])) continue;
		if ($field['type'] === 'subtable') {
			if (empty($field['subselect'])) continue;
			$fieldname = $field['table_name'];
		} else {
			$fieldname = (isset($field['display_field']) && $field['display_field']) 
				? $field['display_field'] : $table.'.'.$field['field_name'];
		}
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
 * Adds ORDER BY to query string, if set via URL
 *
 * @param array $fields
 * @param string $sql
 * @return string $sql
 */
function zz_sql_order($fields, $sql) {
	if (empty($_GET['order']) AND empty($_GET['group'])) return $sql;

	$order = false;
	$my_order = false;
	if (!empty($_GET['dir']))
		if ($_GET['dir'] == 'asc') $my_order = ' ASC';
		elseif ($_GET['dir'] == 'desc') $my_order = ' DESC';
	foreach ($fields as $field) {
		if (!empty($field['dont_sort'])) continue;
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
	$sql = zz_edit_sql($sql, 'ORDER BY', implode(',', $order), 'add');
	return $sql;
}

/**
 * counts number of records that will be caught by current SQL query
 *
 * @param string $sql
 * @param string $id_field
 * @return int $lines
 */
function zz_count_rows($sql, $id_field) {
	$sql = trim($sql);
	// if it's not a SELECT DISTINCT, we can use COUNT, that's faster
	// GROUP BY also does not work with COUNT
	if (substr($sql, 0, 15) != 'SELECT DISTINCT'
		AND !stristr($sql, 'GROUP BY') AND !stristr($sql, 'HAVING')) {
		$sql = zz_edit_sql($sql, 'ORDER BY', '_dummy_', 'delete');
		$sql = zz_edit_sql($sql, 'SELECT', 'COUNT('.$id_field.')', 'replace');
		// unnecessary LEFT JOINs may slow down query
		// remove them in case no WHERE, HAVING or GROUP BY is set
		$sql = zz_edit_sql($sql, 'LEFT JOIN', '_dummy_', 'delete');
		$lines = zz_db_fetch($sql, '', 'single value');
	} else {
		$lines = zz_db_fetch($sql, $id_field, 'count');
	}
	if (!$lines) $lines = 0;
	return $lines;
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
function zz_list_get_subselects($rows, $subselects, $ids) {
	global $zz_conf;
	
	if (!$subselects) return $rows;
	
	foreach ($subselects as $subselect) {
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
			$subselect['sql']  = zz_edit_sql($subselect['sql'], 'WHERE', 
				'translationfield_id = '.$subselect['translation_key']);
		// E_USER_WARNING might return message, we do not want to see this message
		// but in the logs
		$lines = zz_db_fetch($subselect['sql'], array($subselect['id_fieldname'], '_dummy_id_'), 'numeric', false, E_USER_WARNING);
		if (!is_array($lines)) $lines = array();

		foreach ($ids as $z_row => $id) {
			if (empty($lines[$id])) continue;
			$linetext = false;
			foreach ($lines[$id] as $linefields) {
				unset($linefields[$subselect['id_fieldname']]); // ID field will not be shown
				$fieldtext = false;
				$index = 0;
				foreach ($linefields as $db_fields) {
					if ($subselect['show_empty_cells'] AND !$db_fields) $db_fields = '&nbsp;';
					if ($db_fields) {
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
			$subselect_text = $subselect['prefix'].$subselect_text.$subselect['suffix'];
			if (!empty($subselect['list_format'])) {
				if (!empty($zz_conf['modules']['debug'])) zz_debug('start', $subselect['list_format']);
				$subselect_text = $subselect['list_format']($subselect_text);
				if (!empty($zz_conf['modules']['debug'])) zz_debug('end');
			}
			$rows[$z_row][$subselect['fieldindex']]['text'] .= zz_mark_search_string($subselect_text, $subselect['table_name'], $subselect);
			if (!empty($subselect['export_no_html'])) {
				$rows[$z_row][$subselect['fieldindex']]['export_no_html'] = true;
			}
		}
	}
	return $rows;
}

/**
 * sets level for a field where a hierarchy of records shall be displayed in
 *
 * @param array $field
 * @param array $line
 * @global array $zz_conf
 * @return string level or ''
 */
function zz_list_field_level($field, $line) {
	if (!isset($line['zz_level'])) return '';

	global $zz_conf;
	if (!empty($field['decrease_level'])) $line['zz_level'] -= $field['decrease_level'];

	if (!empty($field['field_name']) // occurs in case of subtables
		AND $field['field_name'] == $zz_conf['hierarchy']['display_in']) {
		return $line['zz_level'];
	} elseif (!empty($field['table_name']) 
		AND $field['table_name'] == $zz_conf['hierarchy']['display_in']) {
		return $line['zz_level'];
	}
	return '';
}

/**
 * check depending on grouping whether a field will be shown or not
 *
 * @param array $table_query
 * @global array $zz_conf
 * @return array $table_query ('show_field' set for each field)
 */
function zz_list_show_group_fields($table_query) {
	global $zz_conf;
	if (!$zz_conf['show_list']) return $table_query;

	$show_field = true;
	foreach ($table_query[0] as $index => $field) {
		if ($zz_conf['group']) {
			$show_field_group = zz_list_group_field_no($field, $index);
			if ($show_field AND !$show_field_group) $show_field = false;
		}
		// show or hide field
		foreach (array_keys($table_query) as $row) { // each line seperately
			if (!empty($table_query[$row][$index])) // only if field exists
				$table_query[$row][$index]['show_field'] = $show_field;
		}
		if (!empty($field['list_append_next'])) $show_field = false;
		else $show_field = true;
	}
	return $table_query;
}

function zz_list_head($head, $where_values) {
	$thead = array();
	$j = 0;

	foreach ($head as $index => $field) {
		if ($field['show_field']) {
			$j++;
			$thead[$j]['class'] = zz_field_class($field, $where_values);
			$thead[$j]['th'] = zz_list_th($field);
			$thead[$j]['th_nohtml'] = zz_list_th($field, 'nohtml');
		} elseif (!empty($field['list_append_show_title'])) {
			$thead[$j]['class'] = array_merge($thead[$j]['class'], zz_field_class($field, $where_values));
			$thead[$j]['th'] .= ' / '.zz_list_th($field);
			$thead[$j]['th_nohtml'] .= ' / '.zz_list_th($field, 'nohtml');
		}
		$head[$index]['th'] = $thead[$j]['th'];
		$head[$index]['th_nohtml'] = $thead[$j]['th_nohtml'];
		$head[$index]['class'] = $thead[$j]['class'];
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
		if (isset($field['field_name'])) // does not apply for subtables!
			if (zz_field_in_where($field['field_name'], $values)) 
				$class[] = 'where';
	if (!empty($field['class'])) {
		// we may go through this twice
		if (is_array($field['class']))
			$class = array_merge($class, $field['class']);
		else
			$class[] = $field['class'];
	}
	$class = array_unique($class);

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
	if ($zz_conf['select_multiple_records']) $output .= '<th>[]</th>';

	// Rest cannot be set yet because we do not now details/mode-links
	// of individual records
	foreach ($head as $col) {
		if (!$col['show_field']) continue;
		if ($col['class']) $col['class'] = ' class="'.implode(' ', $col['class']).'"';
		else $col['class'] = '';
		$output .= '<th'.$col['class'].'>'.$col['th'].'</th>';
	}
	if ($list['modes'])
		$output .= ' <th class="editbutton">'.zz_text('action').'</th>';
	if ($list['details']) 
		$output .= ' <th class="editbutton">'.zz_text('detail').'</th>';
	$output .= '</tr></thead>'."\n";

	//
	// Table footer
	//
	if ($zz_conf['tfoot'] AND $list['sum']) {
		$output .= '<tfoot>'."\n".'<tr>';
		$output .= zz_field_sum($head, count($rows), $list['where_values'], $list['sum']);
		if ($list['modes'] OR $list['details'])
			$output .= '<td class="editbutton">&nbsp;</td>';
		$output .= '</tr>'."\n".'</tfoot>'."\n";
	}

	$output .= '<tbody>'."\n";
	$rowgroup = false;
	foreach ($rows as $index => $row) {
		if ($zz_conf['group'] AND $row['group'] != $rowgroup) {
			foreach ($zz_conf['group'] as $pos => $my_group) {
				if (empty($row['group'][$pos])) 
					$list['group_titles'][$index][$pos] = zz_text('- unknown -');
			}
			if ($rowgroup) {
				$my_groups = $rowgroup;
				$my_old_groups = $row['group'];
				while ($my_groups) {
					if ($zz_conf['tfoot'])
						$output .= zz_list_group_foot($my_groups, $head, count($rows), $list['where_values'], $list['sum_group']);
					array_pop($my_groups);
					array_pop($my_old_groups);
					if ($my_groups == $my_old_groups) break;
				}
				$output .= '</tbody><tbody>'."\n";
			}
			$output .= '<tr class="group"><td colspan="'.(count($row)-1)
				.'">'.sprintf($zz_conf['group_html_table'], implode(' &#8211; ', $list['group_titles'][$index]))
				.'</td></tr>'."\n";
			$rowgroup = $row['group'];
		}
		$output .= '<tr class="'.($index & 1 ? 'uneven':'even')
			.(($index+1) == count($rows) ? ' last' : '')
			.((isset($list['current_record']) AND $list['current_record'] == $index) ? ' current_record' : '')
			.'">'; //onclick="Highlight();"
		foreach ($row as $fieldindex => $field) {
			if (is_numeric($fieldindex)) 
				$output .= '<td'
					.($field['class'] ? ' class="'.implode(' ', $field['class']).'"' : '')
					.'>'.$field['text'].'</td>';
		}
		if (!empty($row['modes']))
			$output .= '<td class="editbutton">'.$row['modes'].'</td>';
		if (!empty($row['details']))
			$output .= '<td class="editbutton">'.$row['details'].'</td>';
		$output .= '</tr>'."\n";
	}
	if ($zz_conf['tfoot'] AND $rowgroup) {
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
	if (!$zz_conf['group']) {
		$output .= '<ul class="data">'."\n";
	}
	$rowgroup = false;
	foreach ($rows as $index => $row) {
		if ($zz_conf['group'] AND $row['group'] != $rowgroup) {
			foreach ($zz_conf['group'] as $pos => $my_group) {
				if (empty($row['group'][$pos])) 
					$list['group_titles'][$index][$pos] = zz_text('- unknown -');
			}
			if ($rowgroup) {
				$output .= '</ul><br clear="all">'."\n";
			}
			$output .= "\n".'<h2>'.implode(' &#8211; ', $list['group_titles'][$index]).'</h2>'."\n"
				.'<ul class="data">'."\n";
			$rowgroup = $row['group'];
		}
		$output .= '<li class="'.($index & 1 ? 'uneven':'even')
			.((isset($list['current_record']) AND $list['current_record'] == $index) ? ' current_record' : '')
			.(($index+1) == count($rows) ? ' last' : '').'">'; //onclick="Highlight();"
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

?>