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
 * @param array $zz_var			Main variables
 * @param array $zz_conditions	configuration variables
 * @global array $zz_error		errorhandling
 * @global array $zz_conf		Main conifguration parameters, will be modified
 * @return array
 *		array $zz
 *		array $zz_var
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_list($zz, $zz_var, $zz_conditions) {
	global $zz_conf;
	global $zz_error;

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	// Turn off hierarchical sorting when using search
	// TODO: implement hierarchical view even when using search
	if (!empty($_GET['q']) AND $zz_conf['search'] AND $zz_conf['show_hierarchy']) {
		$zz_conf['show_hierarchy'] = false;
	}

	// only if search is allowed and there is something
	// if q modify $zz['sql']: add search query
	if (!empty($_GET['q']) AND $zz_conf['search']) 
		$zz['sql'] = zz_search_sql($zz['fields'], $zz['sql'], $zz['table'], $zz_var['id']['field_name']);	

	$id_field = $zz_var['id']['field_name'];

	if ($zz_conf['list_access']) {
		$zz_conf = array_merge($zz_conf, $zz_conf['list_access']);
		unset($zz_conf['list_access']);
	}
	if ($zz_conf['access'] == 'search_but_no_list' AND empty($_GET['q'])) 
		$zz_conf['show_list'] = false;

	$subselects = array();

	// SQL query without limit and filter for conditions etc.!
	$zz['sql_without_limit'] = $zz['sql'];

	// list filter
	if (!empty($zz_conf['filter']) AND isset($_GET['filter'])) {
		foreach ($zz_conf['filter'] AS $filter) {
			if (in_array($filter['identifier'], array_keys($_GET['filter']))
				AND in_array($_GET['filter'][$filter['identifier']], array_keys($filter['selection']))
				AND $filter['type'] == 'list'
				AND !empty($filter['where'])
			) {	// it's a valid filter, so apply it.
				if ($_GET['filter'][$filter['identifier']] == 'NULL') {
					$zz['sql'] = zz_edit_sql($zz['sql'], 'WHERE', 'ISNULL('.$filter['where'].')');
				} elseif ($_GET['filter'][$filter['identifier']] == '!NULL') {
					$zz['sql'] = zz_edit_sql($zz['sql'], 'WHERE', '!ISNULL('.$filter['where'].')');
				} else {
					$zz['sql'] = zz_edit_sql($zz['sql'], 'WHERE', $filter['where'].' = "'.$_GET['filter'][$filter['identifier']].'"');
				}
			} elseif (in_array($filter['identifier'], array_keys($_GET['filter']))
				AND $filter['type'] == 'list'
				AND !empty($filter['where'])
				AND is_array($filter['where'])
			) { // valid filter with several wheres
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
				$zz['sql'] = zz_edit_sql($zz['sql'], 'WHERE', implode(' OR ', $wheres));
			} elseif (in_array($filter['identifier'], array_keys($_GET['filter']))
				AND $filter['type'] == 'like'
				AND !empty($filter['where'])
			) { // valid filter with LIKE
				$zz['sql'] = zz_edit_sql($zz['sql'], 'WHERE', $filter['where'].' LIKE "%'.$_GET['filter'][$filter['identifier']].'%"');
			}
		}
	}
	// must be behind update, insert etc. or it will return the wrong number
	$total_rows = zz_count_rows($zz['sql'], $zz['table'].'.'.$id_field);	
	$zz['sql'].= (!empty($zz['sqlorder']) ? ' '.$zz['sqlorder'] : ''); 	// must be here because of where-clause
	$zz['sql'] = zz_sql_order($zz['fields'], $zz['sql']); // Alter SQL query if GET order (AND maybe GET dir) are set

	//
	// Query records
	//
	if ($zz_conf['this_limit'] && empty($zz_conf['show_hierarchy'])) { // limit, but not for hierarchical sets
		if (!$zz_conf['limit']) $zz_conf['limit'] = 20; // set a standard value for limit
			// this standard value will only be used on rare occasions, when NO limit is set
			// but someone tries to set a limit via URL-parameter
		$zz['sql'].= ' LIMIT '.($zz_conf['this_limit']-$zz_conf['limit']).', '.($zz_conf['limit']);
	}

	// read rows from database. depending on hierarchical or normal list view
	// put rows in $lines or $h_lines.
	$h_lines = false;
	if (empty($zz_conf['show_hierarchy'])) {
		$lines = zz_db_fetch($zz['sql'], $id_field);
		if ($zz_error['error']) {
			global $zz;
			return zz_return(array($zz, $zz_var));
		}
	} else {
		// for performance reasons, we only get the fields which are important
		// for the hierarchy (we need to get all records)
		$lines = zz_db_fetch($zz['sql'], array($id_field, $zz_conf['hierarchy']['mother_id_field_name']), 'key/value'); 
		if ($zz_error['error']) {
			global $zz;
			return zz_return(array($zz, $zz_var));
		}
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
	}

	if ($h_lines) {
		$lines = array(); // unset and initialize
		$level = 0; // level (hierarchy)
		$i = 0; // number of record, for LIMIT
		$my_lines = zz_list_hierarchy($h_lines, $zz_conf['show_hierarchy'], $id_field, $level, $i);
		$total_rows = $i; // sometimes, more rows might be selected beforehands,
		// but if show_hierarchy has ID value, not all rows are shown
		if ($my_lines) {
			if (!$zz_conf['this_limit']) {
				$start = 0;
				$end = $total_rows -1;
			} else {
				$start = $zz_conf['this_limit'] - $zz_conf['limit'];
				$end = $zz_conf['this_limit'] -1;
			}
			foreach (range($start, $end) as $index) {
				if (!empty($my_lines[$index])) 
					$lines[$my_lines[$index][$id_field]] = $my_lines[$index];
			}
			// for performance reasons, we didn't save the full result set,
			// so we have to requery it again.
			if ($zz_conf['this_limit']) {
				$sql = zz_edit_sql($zz['sql'], 'WHERE', '`'.$zz['table'].'`.'.$id_field
					.' IN ('.implode(',', array_keys($lines)).')');
			} else {
				$sql = $zz['sql'];
			}
			$lines = zz_array_merge($lines, zz_db_fetch($sql, $id_field));
		}
		foreach ($lines as $line) {
			if (empty($line['zz_hidden_line'])) continue;
			// get record which is normally beyond our scope via ID
			$sql = zz_edit_sql($zz['sql'], 'WHERE', 'nothing', 'delete');
			$sql = zz_edit_sql($sql, 'WHERE', '`'.$zz['table'].'`.'.$id_field.' = '.$line[$id_field]);
			$line = zz_db_fetch($sql);
			if ($line) {
				$lines[$line[$id_field]] = array_merge($lines[$line[$id_field]], $line);
			}
		}
	}

	$count_rows = count($lines);

	// don't show anything if there is nothing
	if (!$count_rows) {
		$zz_conf['show_list'] = false;
		$zz['output'].= '<p>'.zz_text('table-empty').'</p>';
	}

	if ($zz_conf['modules']['debug']) zz_debug("conditions start");
	// Check all conditions whether they are true;
	if (!empty($zz_conf['modules']['conditions']))
		$zz_conditions = zz_conditions_list_check($zz, $zz_conditions, $id_field, array_keys($lines));
	if ($zz_error['error']) {
		$zz['output'].= zz_error();
		return zz_return(array($zz, $zz_var));
	}
	$zz['output'].= zz_error();
	if ($zz_conf['modules']['debug']) zz_debug("conditions finished");

	// check conditions, these might lead to different field definitions for every
	// line in the list output!
	// that means, $table_query cannot be used for the rest but $line_query instead
	// zz_fill_out must be outside if show_list, because it is neccessary for
	// search results with no resulting records
	// fill_out, but do not unset conditions
	$zz['fields_in_list'] = zz_fill_out($zz['fields_in_list'], $zz_conf['db_name'].'.'.$zz['table'], true); 
	if ($zz_conf['show_list']) {
		$conditions_applied = array(); // check if there are any conditions
		array_unshift($lines, '0'); // 0 as a dummy record for which no conditions will be set
		foreach ($lines as $index => $line) {
			$line_query[$index] = $zz['fields_in_list'];
			if ($index) foreach ($line_query[$index] as $fieldindex => $field) {
				// conditions
				if (empty($zz_conf['modules']['conditions'])) continue;
				if (empty($field['conditions'])) continue;
				$line_query[$index][$fieldindex] = zz_conditions_merge($field, $zz_conditions['bool'], $line[$id_field]);
				$conditions_applied[$index] = true;
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
			if ($zz_conf['modules']['debug']) zz_debug("fill_out start");
			$line_query[$index] = zz_fill_out($line_query[$index], $zz_conf['db_name'].'.'.$zz['table'], 2);
			if ($zz_conf['modules']['debug']) zz_debug("fill_out end");
			foreach ($line_query[$index] as $fieldindex => $field) {
				// remove elements from table which shall not be shown
				if ($zz['mode'] == 'export') {
					if (!isset($field['export']) || $field['export']) {
						$table_query[$index][] = $field;
					}
				} else {
					if (empty($field['hide_in_list'])) {
						$table_query[$index][] = $field;
					}
				}
			}
			if ($zz_conf['modules']['debug']) zz_debug("table_query end");
		}
		// now we have the basic stuff in $table_query[0] and $line_query[0]
		// if there are conditions, $table_query[1], [2], [3]... and
		// $line_query[1], [2], [3] ... are set
		$zz['fields_in_list'] = $line_query[0]; // for search form
		unset($line_query);
		unset($lines[0]); // remove first dummy array
	}

	if ($zz_conf['modules']['debug']) zz_debug("table_query set");
	$search_form = zz_search_form($zz['fields_in_list'], $zz['table'], $total_rows, $zz['mode'], $count_rows);
	$zz['output'] .= $search_form['top'];
	
	if ($zz_conf['show_list'] AND $zz_conf['select_multiple_records'] AND $zz['mode'] != 'export') {
		$zz['output'].= '<form action="'.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'];
		if ($zz_var['extraGET']) $zz['output'].= $zz_conf['int']['url']['?&'].substr($zz_var['extraGET'], 5); // without first &amp;!
		$zz['output'].= '" method="POST"';
		$zz['output'].= ' accept-charset="'.$zz_conf['character_set'].'">'."\n";
	}

	//
	// Table head
	//
	
	// allow $zz_conf['group'] to be a string
	if (!is_array($zz_conf['group']) AND $zz_conf['group'])
		$zz_conf['group'] = array($zz_conf['group']);

	if ($zz_conf['show_list'] && $zz_conf['list_display'] == 'table') {
		$zz['output'].= '<table class="data">';
		$zz['output'].= '<thead>'."\n";
		$zz['output'].= '<tr>';
		if ($zz_conf['select_multiple_records']) $zz['output'].= '<th>[]</th>';
		$unsortable_fields = array('calculated', 'image', 'upload_image'); // 'subtable'?
		$show_field = true;
		foreach ($table_query[0] as $index => $field) {
			if ($zz_conf['group']) {
				$show_field_group = zz_list_group_field_no($field, $index);
				if ($show_field AND !$show_field_group) $show_field = false;
			}
			if ($show_field) {
				$zz['output'].= '<th'.check_if_class($field, (!empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : '')).'>';
				if (!in_array($field['type'], $unsortable_fields) && isset($field['field_name'])) { 
					$zz['output'].= '<a href="';
					if (isset($field['display_field'])) $order_val = $field['display_field'];
					else $order_val = $field['field_name'];
					$unwanted_keys = array('dir');
					$new_keys = array('order' => $order_val);
					$uri = $zz_conf['int']['url']['self'].zz_edit_query_string($zz_conf['int']['url']['qs']	
						.$zz_conf['int']['url']['qs_zzform'], $unwanted_keys, $new_keys);
					$order_dir = 'asc';
					if (str_replace('&amp;', '&', $uri) == $_SERVER['REQUEST_URI']) {
						$uri.= '&amp;dir=desc';
						$order_dir = 'desc';
					}
					$zz['output'].= $uri;
					$zz['output'].= '" title="'.zz_text('order by').' '.strip_tags($field['title']).' ('.zz_text($order_dir).')">';
				}
				$zz['output'].= (!empty($field['title_tab']) 
					? ($zz_conf['multilang_fieldnames'] ? zz_text($field['title_tab']) : $field['title_tab']) 
					: $field['title']);
				if (!in_array($field['type'], $unsortable_fields) && isset($field['field_name']))
					$zz['output'].= '</a>';
				$zz['output'].= '</th>';
				foreach (array_keys($table_query) as $tq_index) { // each line seperately
					if (!empty($table_query[$tq_index][$index])) // only if field exists
						$table_query[$tq_index][$index]['show_field'] = true;
				}
			} else {
				foreach (array_keys($table_query) as $tq_index) { // each line seperately
					if (!empty($table_query[$tq_index][$index])) // only if field exists
						$table_query[$tq_index][$index]['show_field'] = false;
				}
			}
			if (!empty($field['list_append_next'])) $show_field = false;
			else $show_field = true;
		}
		// Rest cannot be set yet because we do not now details/mode-links
		// of individual records

	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'ul') {
		if ($zz_conf['group']) {
			foreach ($table_query[0] as $index => $field)
				zz_list_group_field_no($field, $index);
		} else {
			$zz['output'].= '<ul class="data">'."\n";
		}
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'csv') {
		$zz['output'] .= zz_export_csv_head($table_query[0], $zz_conf);
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'pdf') {
		$zz['output']['head'] = $table_query[0];
	}

	//
	// Table data
	//	

	$current_record = NULL;
	$sum_group = array();
	$modes = false;		// don't show a table head for link to modes until neccessary
	$details = false;	// don't show a table head for link to details until neccessary
	if ($zz_conf['show_list']) {
		$id_fieldname = false;
		$z = 0;
		$ids = array();
		//$group_hierarchy = false; // see below, hierarchical grouping
		$lastline = false;
	
		foreach ($lines as $index => $line) {
			// put lines in new array, rows.
			//$rows[$z][0]['text'] = '';
			//$rows[$z][0]['class'] = '';
			
			$tq_index = (count($table_query) > 1 ? $index : 0);
			$id = '';
			$sub_id = '';
			if (empty($rows[$z]['group']))
				$rows[$z]['group'] = array();
			if ($zz_conf['group']) {
				$group_count = count($zz_conf['group']);
				foreach ($table_query[$tq_index] as $fieldindex => $field) {
				//	check for group function
					$pos = array_search($fieldindex, $zz_conf['group_field_no']);
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
					} elseif (!empty($field['enum_title']) AND $field['type'] == 'select') {
						foreach ($field['enum'] as $mkey => $mvalue)
							if ($mvalue == $line[$field['field_name']]) 
								$rows[$z]['group'][$pos] = $field['enum_title'][$mkey];
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
				$rows[$z][-1]['class'] = ' class="select_multiple_records"';
			}

			foreach ($table_query[$tq_index] as $fieldindex => $field) {
				if ($zz_conf['modules']['debug']) zz_debug("table_query foreach ".$fieldindex);
				// conditions
				if (!empty($zz_conf['modules']['conditions'])) {
					if (!empty($field['conditions'])) {
						$field = zz_conditions_merge($field, $zz_conditions['bool'], $line[$id_field]);
					}
					if (!empty($zz_conf_record['conditions'])) {
						$zz_conf_record = zz_conditions_merge($zz_conf_record, $zz_conditions['bool'], $line[$id_field], false, 'conf');
						$zz_conf_record = zz_listandrecord_access($zz_conf_record);
						if (!isset($zz_conf_record['add_link']))
							// Link Add new ...
							$zz_conf_record['add_link'] = ($zz_conf_record['add'] ? true : false); 
						// $zz_conf is set regarding add, edit, delete
						if (!$zz_conf['add']) $zz_conf['copy'] = false;			// don't copy record (form+links)
					}
				}
				if ($zz_conf['modules']['debug']) zz_debug("table_query foreach cond set ".$fieldindex);
				
				// check all fields next to each other with list_append_next					
				while (!empty($table_query[$tq_index][$fieldindex]['list_append_next'])) $fieldindex++;
				
			//	initialize variables
				if (isset($line['zz_level'])) {
					if (!empty($field['field_name'])  // occurs in case of subtables
						AND $field['field_name'] == $zz_conf['hierarchy']['display_in']) {
						$field['level'] = $line['zz_level'];
					} elseif (!empty($field['table_name']) 
						AND $field['table_name'] == $zz_conf['hierarchy']['display_in']) {
						$field['level'] = $line['zz_level'];
					}
				}
				if (empty($rows[$z][$fieldindex]['class']))
					$rows[$z][$fieldindex]['class'] = check_if_class($field, 
						(!empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : ''));
				if (!empty($field['field_name']) AND !empty($lastline[$field['field_name']]) 
					AND $line[$field['field_name']] == $lastline[$field['field_name']])
					$rows[$z][$fieldindex]['class'] = ($rows[$z][$fieldindex]['class'] 
						? substr($rows[$z][$fieldindex]['class'], 0, -1).' identical_value"' 
						: ' class="identical_value"');
				if (empty($rows[$z][$fieldindex]['text']))
					$rows[$z][$fieldindex]['text'] = '';
				
				if (!empty($field['list_prefix'])) 
					$rows[$z][$fieldindex]['text'] .= zz_text($field['list_prefix']);
				if (!empty($field['list_abbr'])) 
					$rows[$z][$fieldindex]['text'] .= '<abbr title="'.htmlspecialchars($line[$field['list_abbr']]).'">';
				$stringlength = strlen($rows[$z][$fieldindex]['text']);
			//	if there's a link, glue parts together
				$link = false;
				if ($zz['mode'] != 'export') $link = zz_set_link($field, $line);
					
				if ($zz_conf['modules']['debug']) zz_debug("table_query before switch ".$fieldindex.'-'.$field['type']);

			//	go for type of field!
				switch ($field['type']) {
				case 'calculated':
					if ($field['calculation'] == 'hours') {
						$diff = 0;
						foreach ($field['calculation_fields'] as $calc_field)
							if (!$diff) $diff = strtotime($line[$calc_field]);
							else $diff -= strtotime($line[$calc_field]);
						if ($diff < 0) $rows[$z][$fieldindex]['text'] .= '<em class="negative">';
						$rows[$z][$fieldindex]['text'].= hours($diff);
						if ($diff < 0) $rows[$z][$fieldindex]['text'] .= '</em>';
						if (isset($field['sum']) && $field['sum'] == true) {
							if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
							$sum[$field['title']] += $diff;
							$sum_group = zz_list_group_sum($rows[$z]['group'], $sum_group, $field['title'], $diff);
						}
					} elseif ($field['calculation'] == 'sum') {
						$my_sum = 0;
						foreach ($field['calculation_fields'] as $calc_field)
							$my_sum += $line[$calc_field];
						$rows[$z][$fieldindex]['text'] .= $my_sum;
						if (isset($field['sum']) && $field['sum'] == true) {
							if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
							$sum[$field['title']] .= $my_sum;
							$sum_group = zz_list_group_sum($rows[$z]['group'], $sum_group, $field['title'], $my_sum);
						}
					} elseif ($field['calculation'] == 'sql')
						$rows[$z][$fieldindex]['text'].= $line[$field['field_name']];
					break;
				case 'image':
				case 'upload_image':
					if (isset($field['path'])) {
						if ($img = zz_makelink($field['path'], $line, 'image'))
							$rows[$z][$fieldindex]['text'].= $link.$img.($link ? '</a>' : '');
						elseif (isset($field['default_image'])) {
							if (is_array($field['default_image'])) {
								$default_image = zz_makelink($field['default_image'], $line);
							} else {
								$default_image = $field['default_image'];
							}
							$rows[$z][$fieldindex]['text'].= $link.'<img src="'
								.$default_image.'"  alt="'.zz_text('no_image')
								.'" class="thumb">'.($link ? '</a>' : '');
						}
						if (!empty($field['image'])) foreach ($field['image'] as $image)
							if (!empty($image['show_link']) && $zz['mode'] != 'export')
								if ($imglink = zz_makelink($image['path'], $line))
									$rows[$z][$fieldindex]['text'] .= ' <a href="'.$imglink.'">'.$image['title'].'</a><br>' ;
					} elseif (isset($field['path_json_request'])) {
						$img = zz_makelink($field['path_json_request'], $line);
						if ($img = brick_request_getjson($img)) {
							$rows[$z][$fieldindex]['text'].= $link.'<img src="'
								.(!empty($field['path_json_base']) ? $field['path_json_base'] : '')
								.$img.'"  alt="" class="thumb">'
								.($link ? '</a>' : '');
						}
					}
					break;
				case 'subtable':
					if (!empty($field['subselect']['sql'])) {
						// fill array subselects, just in row 0, will always be the same!
						if (!$z) {
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
							$field['subselect']['id_table_and_fieldname'] = $field['table'].'.'.$id_fieldname;
							// just field_name
							$field['subselect']['id_fieldname'] = $id_fieldname;
							$field['subselect']['fieldindex'] = $fieldindex;
							$subselects[] = $field['subselect'];
						}
						if (empty($line[$key_fieldname])) {
							$zz_error[] = array(
								'msg_dev' => 'Wrong key_field_name. Please set $zz_sub["fields"]['
								.'n]["key_field_name"] to something different: '.implode(', ', array_keys($line)));
						}
						$sub_id = $line[$key_fieldname]; // get correct ID
					} elseif (!empty($field['display_field'])) {
						$rows[$z][$fieldindex]['text'].= zz_mark_search_string($line[$field['display_field']], $field['display_field'], $field);
					}
					break;
				case 'url':
				case 'mail':
				case 'mail+name':
					if ($link) $rows[$z][$fieldindex]['text'].= $link;
					if (!empty($field['display_field']))
						$rows[$z][$fieldindex]['text'].= zz_mark_search_string(htmlchars($line[$field['display_field']]), $field['display_field'], $field);
					elseif ($field['type'] == 'url' && strlen($line[$field['field_name']]) > $zz_conf_record['max_select_val_len'])
						$rows[$z][$fieldindex]['text'].= zz_mark_search_string(
							mb_substr(htmlchars($line[$field['field_name']]), 0, 
							$zz_conf_record['max_select_val_len']).'...', $field['field_name'], $field);
					else
						$rows[$z][$fieldindex]['text'].= zz_mark_search_string(htmlspecialchars($line[$field['field_name']]), $field['field_name'], $field);
					if ($link) $rows[$z][$fieldindex]['text'].= '</a>';
					break;
				case 'id':
					$id = $line[$field['field_name']];
					if ($id == $zz_var['id']['value']) $current_record = $z;
				default:
					if ($link) $rows[$z][$fieldindex]['text'].= $link;
					$val_to_insert = '';
					if ($zz_conf['modules']['debug']) zz_debug("table_query switch default start ".$fieldindex.'-'.$field['type']);
					if (!empty($field['display_field'])) {
						$val_to_insert = $line[$field['display_field']];
						if (!empty($field['translate_field_value']))
							$val_to_insert = zz_text($val_to_insert);
						$rows[$z][$fieldindex]['text'].= zz_mark_search_string(htmlchars($val_to_insert), $field['display_field'], $field);
					} else {
						if ($zz_conf['modules']['debug']) zz_debug("table_query switch default 1 ".$fieldindex.'-'.$field['type']);

						// replace field content with display_title, if set.
						if (!empty($field['display_title']) 
							&& in_array($line[$field['field_name']], array_keys($field['display_title'])))
							$line[$field['field_name']] = $field['display_title'][$line[$field['field_name']]];
						if (isset($field['factor']) && $line[$field['field_name']]) 
							$line[$field['field_name']] /=$field['factor'];
						if ($field['type'] == 'unix_timestamp') {
							$rows[$z][$fieldindex]['text'].= zz_mark_search_string(date('Y-m-d H:i:s', $line[$field['field_name']]), $field['field_name'], $field);
						} elseif ($field['type'] == 'timestamp') {
							$rows[$z][$fieldindex]['text'].= zz_mark_search_string(timestamp2date($line[$field['field_name']]), $field['field_name'], $field);
						} elseif ($field['type'] == 'select' 
							AND (!empty($field['set']) OR !empty($field['set_sql']))) {
							$rows[$z][$fieldindex]['text'].= zz_mark_search_string(str_replace(',', ', ', $line[$field['field_name']]), $field['field_name'], $field);
						} elseif ($field['type'] == 'select' && !empty($field['enum']) && !empty($field['enum_title'])) { // show enum_title instead of enum
							foreach ($field['enum'] as $mkey => $mvalue)
								if ($mvalue == $line[$field['field_name']]) 
									$rows[$z][$fieldindex]['text'] .= zz_mark_search_string($field['enum_title'][$mkey], $field['field_name'], $field);
						} elseif ($field['type'] == 'select' && !empty($field['enum'])) {
							$rows[$z][$fieldindex]['text'] .= zz_mark_search_string(zz_text($line[$field['field_name']]), $field['field_name'], $field); // translate field value
						} elseif ($field['type'] == 'date') {
							$rows[$z][$fieldindex]['text'].= zz_mark_search_string(datum_de($line[$field['field_name']]), $field['field_name'], $field);
						} elseif (isset($field['number_type']) && $field['number_type'] == 'currency') {
							$rows[$z][$fieldindex]['text'].= zz_mark_search_string(waehrung($line[$field['field_name']], ''), $field['field_name'], $field);
						} elseif (isset($field['number_type']) && $field['number_type'] == 'latitude' && $line[$field['field_name']]) {
							$deg = dec2dms($line[$field['field_name']], '');
							$rows[$z][$fieldindex]['text'].= zz_mark_search_string($deg['latitude_dms'], $field['field_name'], $field);
						} elseif (isset($field['number_type']) && $field['number_type'] == 'longitude' && $line[$field['field_name']]) {
							$deg = dec2dms('', $line[$field['field_name']]);
							$rows[$z][$fieldindex]['text'].= zz_mark_search_string($deg['longitude_dms'], $field['field_name'], $field);
						} elseif (!empty($field['display_value'])) {
							// translations should be done in $zz-definition-file
							$rows[$z][$fieldindex]['text'].= $field['display_value'];
						} elseif ($zz['mode'] == 'export') {
							$rows[$z][$fieldindex]['text'].= $line[$field['field_name']];
						} elseif (!empty($field['list_format'])) {
							if (!empty($zz_conf['modules']['debug'])) zz_debug('start', $field['list_format']);
							$rows[$z][$fieldindex]['text'].= zz_mark_search_string($field['list_format']($line[$field['field_name']]), $field['field_name'], $field);
							if (!empty($zz_conf['modules']['debug'])) zz_debug('end');
						} elseif (empty($field['hide_zeros']) OR $line[$field['field_name']]) {
							// show field, but not if hide_zeros is set
							$val_to_insert = $line[$field['field_name']];
							if (!empty($field['translate_field_value']))
								$val_to_insert = zz_text($val_to_insert);
							$rows[$z][$fieldindex]['text'].= zz_mark_search_string(nl2br(htmlchars($val_to_insert)), $field['field_name'], $field);
						}
					}
					if ($link) $rows[$z][$fieldindex]['text'].= '</a>';
					if (isset($field['sum']) && $field['sum'] == true) {
						if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
						$sum[$field['title']] += $line[$field['field_name']];
						$sum_group = zz_list_group_sum($rows[$z]['group'], $sum_group, $field['title'], $line[$field['field_name']]);
					}
				}
				if (isset($field['unit']) && $rows[$z][$fieldindex]['text']) 
					$rows[$z][$fieldindex]['text'].= '&nbsp;'.$field['unit'];	
				if (strlen($rows[$z][$fieldindex]['text']) == $stringlength) { // string empty or nothing appended
					if (!empty($field['list_prefix']))
						$rows[$z][$fieldindex]['text'] = substr($rows[$z][$fieldindex]['text'], 0, $stringlength - strlen(zz_text($field['list_prefix'])));
				} else {
					if (!empty($field['list_suffix'])) $rows[$z][$fieldindex]['text'] .= zz_text($field['list_suffix']);
				}
				if (!empty($field['list_abbr'])) $rows[$z][$fieldindex]['text'] .= '</abbr>';

				// group: go through everything but don't show it in list
				// TODO: check that it does not collide with append_next
				if ($zz_conf['group']) {
					$pos = array_search($fieldindex, $zz_conf['group_field_no']);
					if ($pos !== false) {
						$grouptitles[$z][$pos] = $rows[$z][$fieldindex]['text'];
						unset ($rows[$z][$fieldindex]);
						ksort($grouptitles[$z]);
					}
				}

				if ($zz_conf['modules']['debug']) zz_debug("table_query end ".$fieldindex.'-'.$field['type']);

			}
			$ids[$z] = $sub_id; // for subselects
			if ($zz_conf_record['edit'] OR $zz_conf_record['view'] OR $zz_conf_record['delete']) {
				$rows[$z]['modes'] = false;
				if ($zz_conf_record['edit']) {
					$rows[$z]['modes'] = '<a href="'.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
						.$zz_conf['int']['url']['?&'].'mode=edit&amp;id='.$id
						.$zz_var['extraGET'].'">'.zz_text('edit').'</a>';
					$modes = true; // need a table row for this
				} elseif ($zz_conf_record['view']) {
					$rows[$z]['modes'] = '<a href="'.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
						.$zz_conf['int']['url']['?&'].'mode=show&amp;id='.$id
						.$zz_var['extraGET'].'">'.zz_text('show').'</a>';
					$modes = true; // need a table row for this
				}
				if ($zz_conf_record['copy']) {
					if ($rows[$z]['modes']) $rows[$z]['modes'] .= '&nbsp;| ';
					$rows[$z]['modes'] .= '<a href="'
						.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'].$zz_conf['int']['url']['?&'].'mode=add&amp;source_id='
						.$id.$zz_var['extraGET'].'">'.zz_text('Copy').'</a>';
					$modes = true; // need a table row for this
				}
				if ($zz_conf_record['delete']) {
					if ($rows[$z]['modes']) $rows[$z]['modes'] .= '&nbsp;| ';
					$rows[$z]['modes'] .= '<a href="'
						.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'].$zz_conf['int']['url']['?&'].'mode=delete&amp;id='
						.$id.$zz_var['extraGET'].'">'.zz_text('delete').'</a>';
					$modes = true; // need a table row for this
				}
			}
			if (!empty($zz_conf_record['details'])) {
				$rows[$z]['details'] = zz_show_more_actions($zz_conf_record['details'], 
					$zz_conf_record['details_url'],  $zz_conf_record['details_base'], 
					$zz_conf_record['details_target'], $zz_conf_record['details_referer'], $id, $line);
				$details = true; // need a table row for this
			}
			$z++;
			$lastline = $line;
		}
	}
	unset($lines);
		
	// get values for "subselects" in detailrecords
	
	foreach ($subselects as $subselect) {
		// default values
		if (!isset($subselect['prefix'])) $subselect['prefix'] = '<p>';
		if (!isset($subselect['concat_rows'])) $subselect['concat_rows'] = "</p>\n<p>";
		if (!isset($subselect['suffix'])) $subselect['suffix'] = '</p>';
		if (!isset($subselect['concat_fields'])) $subselect['concat_fields'] = ' ';
		if (!isset($subselect['show_empty_cells'])) $subselect['show_empty_cells'] = false;
		
		$subselect['sql'] = zz_edit_sql($subselect['sql'], 'WHERE', $subselect['id_table_and_fieldname'].' 
			IN ('.implode(', ', $ids).')');
		if (!empty($subselect['translation_key']))
			$subselect['sql']  = zz_edit_sql($subselect['sql'], 'WHERE', 
				'translationfield_id = '.$subselect['translation_key']);
		$lines = zz_db_fetch($subselect['sql'], array($subselect['id_fieldname'], '_dummy_id_'), 'numeric', false, E_USER_WARNING);
		// E_USER_WARNING might return message, we do not want to see this message
		// but in the logs
		if (!is_array($lines)) $lines = array();

		foreach ($ids as $z_row => $id) {
			if (empty($lines[$id])) continue;
			$linetext = false;
			foreach ($lines[$id] as $linefields) {
				unset($linefields[$subselect['id_fieldname']]); // ID field will not be shown
				$fieldtext = false;
				foreach ($linefields as $db_fields) {
					if ($subselect['show_empty_cells'] AND !$db_fields) $db_fields = '&nbsp;';
					if ($fieldtext AND $db_fields) $fieldtext .= $subselect['concat_fields'];
					$fieldtext .= $db_fields;
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
			$rows[$z_row][$subselect['fieldindex']]['text'].= zz_mark_search_string($subselect_text, '', $field);
			if (!empty($subselect['export_no_html'])) {
				$rows[$z_row][$subselect['fieldindex']]['export_no_html'] = true;
			}
		}
	}
	
	//
	// Remaining table header
	//
	
	if ($zz_conf['show_list'] && $zz_conf['list_display'] == 'table') {
		if ($modes)
			$zz['output'].= ' <th class="editbutton">'.zz_text('action').'</th>';
		if ($details) 
			$zz['output'].= ' <th class="editbutton">'.zz_text('detail').'</th>';
		$zz['output'].= '</tr>';
		$zz['output'].= '</thead>'."\n";
	}

	//
	// Table footer
	//
	
	$my_footer_table = (!empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : false);
	if ($zz_conf['show_list'] && $zz_conf['tfoot'] && $zz_conf['list_display'] == 'table') {
		$zz['output'].= '<tfoot>'."\n".'<tr>';
		$zz['output'].= zz_field_sum($table_query[0], $z, $my_footer_table, $sum);
		$zz['output'].= '<td class="editbutton">&nbsp;</td>';
		$zz['output'].= '</tr>'."\n".'</tfoot>'."\n";
		
	}

	//
	// Table body
	//
	
	if ($zz_conf['show_list'] && $zz_conf['list_display'] == 'table') {
		$zz['output'].= '<tbody>'."\n";
		$rowgroup = false;
		foreach ($rows as $index => $row) {
			if ($zz_conf['group'] AND $row['group'] != $rowgroup) {
				foreach ($zz_conf['group'] as $pos => $my_group) {
					if (empty($row['group'][$pos])) 
						$grouptitles[$index][$pos] = zz_text('- unknown -');
				}
				if ($rowgroup) {
					$my_groups = $rowgroup;
					$my_old_groups = $row['group'];
					while ($my_groups) {
						if ($zz_conf['tfoot'])
							$zz['output'] .= zz_list_group_foot($my_groups, $table_query[0], $z, $my_footer_table, $sum_group);
						array_pop($my_groups);
						array_pop($my_old_groups);
						if ($my_groups == $my_old_groups) break;
					}
					$zz['output'] .= '</tbody><tbody>'."\n";
				}
				$zz['output'].= '<tr class="group"><td colspan="'.(count($row)-1)
					.'"><strong>'.implode(' &#8211; ', $grouptitles[$index]).'</strong></td></tr>'."\n";
				$rowgroup = $row['group'];
			}
			$zz['output'].= '<tr class="'.($index & 1 ? 'uneven':'even')
				.(($index+1) == $count_rows ? ' last' : '')
				.((isset($current_record) AND $current_record == $index) ? ' current_record' : '')
				.'">'; //onclick="Highlight();"
			foreach ($row as $fieldindex => $field)
				if (is_numeric($fieldindex)) $zz['output'].= '<td'.$field['class'].'>'.$field['text'].'</td>';
			if (!empty($row['modes']))
				$zz['output'].= '<td class="editbutton">'.$row['modes'].'</td>';
			if (!empty($row['details']))
				$zz['output'].= '<td class="editbutton">'.$row['details'].'</td>';
			$zz['output'].= '</tr>'."\n";
		}
		if ($zz_conf['tfoot'] AND $rowgroup) {
			$my_groups = $rowgroup;
			while ($my_groups) {
				$zz['output'] .= zz_list_group_foot($my_groups, $table_query[0], $z, $my_footer_table, $sum_group);
				array_pop($my_groups);
			}
		}
		$zz['output'].= '</tbody>'."\n";
		unset($rows);
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'ul') {
		$rowgroup = false;
		foreach ($rows as $index => $row) {
			if ($zz_conf['group'] AND $row['group'] != $rowgroup) {
				foreach ($zz_conf['group'] as $pos => $my_group) {
					if (empty($row['group'][$pos])) 
						$grouptitles[$index][$pos] = zz_text('- unknown -');
				}
				if ($rowgroup) {
					$zz['output'] .= '</ul><br clear="all">'."\n";
				}
				$zz['output'].= "\n".'<h2>'.implode(' &#8211; ', $grouptitles[$index]).'</h2>'."\n"
					.'<ul class="data">'."\n";
				$rowgroup = $row['group'];
			}
			$zz['output'].= '<li class="'.($index & 1 ? 'uneven':'even')
				.((isset($current_record) AND $current_record == $index) ? ' current_record' : '')
				.(($index+1) == $count_rows ? ' last' : '').'">'; //onclick="Highlight();"
			foreach ($row as $fieldindex => $field)
				if (is_numeric($fieldindex) && $field['text'])
					$zz['output'].= '<p'.$field['class'].'>'.$field['text'].'</p>';
			if (!empty($row['modes']))
				$zz['output'].= '<p class="editbutton">'.$row['modes'].'</p>';
			if (!empty($row['details']))
				$zz['output'].= '<p class="editbutton">'.$row['details'].'</p>';
			$zz['output'].= '</li>'."\n";
		}
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'csv') {
		$zz['output'] .= zz_export_csv_body($rows, $zz_conf);
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'pdf') {
		$zz['output']['rows'] = $rows;
	}

	if ($zz_conf['show_list'])
		if ($zz_conf['list_display'] == 'table')
			$zz['output'].= '</table>'."\n";
		elseif ($zz_conf['list_display'] == 'ul')
			$zz['output'].= '</ul>'."\n".'<br clear="all">';

	if ($zz_conf['show_list'] AND $zz_conf['select_multiple_records'] AND $zz['mode'] != 'export') {
		$zz['output'].= '<input type="hidden" name="zz_action" value="Multiple action"><input type="submit" value="'
			.zz_text('Delete selected records').'" name="multiple_delete">'
			.'</form>'."\n";
	}
	//
	// Buttons below table (add, record nav, search)
	//

	// Add new record
	if ($zz['mode'] != 'export' AND (!($zz_conf['access'] == 'search_but_no_list' AND empty($_GET['q'])))) {
		// filter, if there was a list
		if ($zz_conf['filter'] AND $zz_conf['show_list'] 
			AND in_array($zz_conf['filter_position'], array('bottom', 'both')))
			$zz['output'] .= zz_filter_selection($zz_conf['filter']);
		$toolsline = array();
		// normal add button, only if list was shown beforehands
		if ($zz['mode'] != 'add' && $zz_conf['add_link'] AND !is_array($zz_conf['add']) && $zz_conf['show_list']) {
			$toolsline[] = '<a accesskey="n" href="'
				.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
				.$zz_conf['int']['url']['?&'].'mode=add'.$zz_var['extraGET'].'">'
				.zz_text('Add new record').'</a>';
			if ($zz_conf['import']) {
				$toolsline[] = '<a href="'
					.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
					.$zz_conf['int']['url']['?&'].'mode=import'.$zz_var['extraGET'].'">'
					.zz_text('Import data').'</a>';
			}
		}
		// multi-add-button, also show if there was no list, because it will only be shown below records!
		
		if ($zz['mode'] != 'add' && $zz_conf['add_link'] AND is_array($zz_conf['add'])) {
			ksort($zz_conf['add']); // if some 'add' was unset before, here we get new numerical keys
			$zz['output'].= '<p class="add-new">'.zz_text('Add new record').': ';
			foreach ($zz_conf['add'] as $i => $add) {
				$zz['output'].= '<a href="'.$zz_conf['int']['url']['self']
					.$zz_conf['int']['url']['qs'].$zz_conf['int']['url']['?&']
					.'mode=add'.$zz_var['extraGET'].'&amp;add['.$add['field_name'].']='
					.$add['value'].'"'
					.(!empty($add['title']) ? ' title="'.$add['title'].'"' : '')
					.'>'.$add['type'].'</a>';
				if ($i != count($zz_conf['add']) -1) $zz['output'].= ' | ';
			}
			$zz['output'] .= '</p>'."\n";
		}

		if ($zz_conf['export'] AND $total_rows) 
			$toolsline = array_merge($toolsline, zz_export_links($zz_conf['int']['url']['self']
				.$zz_conf['int']['url']['qs'].$zz_conf['int']['url']['?&'], $zz_var['extraGET']));
		if ($toolsline)
			$zz['output'].= '<p class="add-new bottom-add-new">'.implode(' | ', $toolsline).'</p>';
		// Total records
		if ($total_rows == 1) $zz['output'].= '<p class="totalrecords">'.$total_rows.' '.zz_text('record total').'</p>'; 
		elseif ($total_rows) $zz['output'].= '<p class="totalrecords">'.$total_rows.' '.zz_text('records total').'</p>';
		// Limit links
		$zz['output'].= zz_limit($zz_conf['limit'], $zz_conf['this_limit'], $total_rows);	
		// TODO: NEXT, PREV Links at the end of the page
		// Search form
		$zz['output'] .= $search_form['bottom'];
	} elseif ($zz_conf['list_display'] == 'pdf') {
		if ($zz_conf['modules']['debug']) zz_debug("end");
		zz_pdf($zz);
		exit;
	}
	// save total rows in zz_var for use in zz_nice_title()
	$zz_var['limit_total_rows'] = $total_rows;
	if ($zz_conf['modules']['debug']) zz_debug("end");
	return zz_return(array($zz, $zz_var));
}

/**
 * adds values, outputs HTML table foot
 *
 * @param array $table_query
 * @param int $z
 * @param array $table (foreign_key_field_name => value)
 * @param array $sum (field_name => value)
 * @global array $zz_conf ($zz_conf['group_field_no'])
 * @return string HTML output of table foot
 */
function zz_field_sum($table_query, $z, $table, $sum) {
	global $zz_conf;
	$tfoot_line = '';
	foreach ($table_query as $index => $field) {
		if (!$field['show_field']) continue;
		if (in_array($index, $zz_conf['group_field_no'])) continue;
		if ($field['type'] == 'id' && empty($field['show_id'])) {
			$tfoot_line .= '<td class="recordid">'.$z.'</td>';
		} elseif (!empty($field['sum'])) {
			$tfoot_line.= '<td'.check_if_class($field, (!empty($table) ? $table : '')).'>';
			if (isset($field['calculation']) AND $field['calculation'] == 'hours')
				$sum[$field['title']] = hours($sum[$field['title']]);
			if (isset($field['number_type']) && $field['number_type'] == 'currency') 
				$sum[$field['title']] = waehrung($sum[$field['title']], '');

			$tfoot_line.= $sum[$field['title']];
			if (isset($field['unit']) && $sum[$field['title']]) 
				$tfoot_line.= '&nbsp;'.$field['unit'];	
			$tfoot_line.= '</td>';
		} else $tfoot_line.= '<td'.(!empty($field['class']) ? ' class="'
			.$field['class'].'"' : '').'>&nbsp;</td>';
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
	} elseif (isset($field['link'])) {
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
function zz_mark_search_string($value, $field_name = false, $field) {
	global $zz_conf;
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
	$preg_q = str_replace('\\', '\\\\', $_GET['q']);
	$preg_q = str_replace('[', '\[', $preg_q);
	$preg_q = str_replace('$', '\$', $preg_q);
	$value = preg_replace('~('.$preg_q.')~i', '<span class="highlight">\1</span>', $value);
	return $value;
}

/**
 * checks field indices of fields which will be grouped
 *
 * @param array $field $zz['fields'][n]-field definition
 * @param int $index index 'n' of $zz['fields']
 * @global array $zz_conf
 *		string 'group', array 'group_field_no' (will be set to 'n') 
 * @return bool true/false if field will be shown (group: false, otherwise true)
 */
function zz_list_group_field_no($field, $index) {
	global $zz_conf;
	if (!isset($zz_conf['group_field_no'])) 
		$zz_conf['group_field_no'] = array();
	if (!empty($field['display_field'])) {
		$pos = array_search($field['display_field'], $zz_conf['group']);
		if ($pos !== false) {
			$zz_conf['group_field_no'][$pos] = $index;
			ksort($zz_conf['group_field_no']);
			return false;
		}
	}
	if (!empty($field['field_name'])) {
		$pos = array_search($field['field_name'], $zz_conf['group']);
		if ($pos !== false) {
			$zz_conf['group_field_no'][$pos] = $index;
			ksort($zz_conf['group_field_no']);
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
 * @param array $main_table_query ($table_query[0] from zz_list)
 * @param int $z
 * @param string $my_footer_table
 * @param array $sum_group
 */
function zz_list_group_foot($rowgroup, $main_table_query, $z, $my_footer_table, $sum_group) {
	$my_index = '';
	foreach ($rowgroup as $my_group) {
		if ($my_index) $my_index .= '['.$my_group.']';
		else $my_index = $my_group;
	}
	return '<tr class="group_sum">'
		.zz_field_sum($main_table_query, $z, $my_footer_table, $sum_group[$my_index])
		.'</tr>'."\n";
}

?>