<?php

/*
	zzform Scripts

	function zz_display_table
		displays table with records (limited by zz_conf['limit'])
		displays add new record, record navigation (if zz_conf['limit'] = true)
		and search form below table
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2006

*/


/** shows records in list view
 * 
 * @param $zz(array)		table and field definition
 * @param $zz_conf			configuration variables
 * @param $zz_error			errorhandling
 * @param $zz_var			internal variables
 * @param $total_rows		total rows in main table
 * @param $id_field			name of ID field for main table
 * @return array $zz		Output for page, ...
 * @return array $zz_conf	Modified conifguration parameters
 * @return array $zz_error	Error-Output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_display_table(&$zz, $zz_conf, &$zz_error, $zz_var, $total_rows, $id_field, $zz_conditions, $zz_timer) {
	global $zz_conf;
	$subselects = array();

	//
	// Query records
	//
	$zz['sql_without_limit'] = $zz['sql'];
	if ($zz_conf['this_limit'] && empty($zz_conf['show_hierarchy'])) { // limit, but not for hierarchical sets
		if (!$zz_conf['limit']) $zz_conf['limit'] = 20; // set a standard value for limit
			// this standard value will only be used on rare occasions, when NO limit is set
			// but someone tries to set a limit via URL-parameter
		$zz['sql'].= ' LIMIT '.($zz_conf['this_limit']-$zz_conf['limit']).', '.($zz_conf['limit']);
	}
	$result = mysql_query($zz['sql']);
	if ($result) {
		$count_rows = mysql_num_rows($result);
	} else {
		$count_rows = 0;
		if ($zz_conf['debug_allsql']) echo "<div>Oh no. An error:<br /><pre>".$zz['sql']."</pre></div>";
		$zz['output'].= zz_error($zz_error[] = array(
			'mysql' => mysql_error(), 
			'query' => $zz['sql'], 
			'msg' => zz_text('error-sql-incorrect')));
	}

	// read rows from database. depending on hierarchical or normal list view
	// put rows in $lines or $h_lines.
	$ids = array();
	$h_lines = false;
	if ($result) while ($line = mysql_fetch_assoc($result)) {
		if (empty($zz_conf['show_hierarchy'])) {
			$lines[] = $line;
			$ids[] = $line[$id_field];
		} else {
			// sort lines by mother_id
			if ($zz_conf['show_hierarchy'] === true) 
				$zz_conf['show_hierarchy'] = 'NULL';
			if ($line[$id_field] == $zz_conf['show_hierarchy']) { // get uppermost line
				// if show_hierarchy is not NULL!
				$line[$zz_conf['hierarchy']['mother_id_field_name']] = 'TOP';
			} elseif (empty($line[$zz_conf['hierarchy']['mother_id_field_name']]))
				$line[$zz_conf['hierarchy']['mother_id_field_name']] = 'NULL';
			$h_lines[$line[$zz_conf['hierarchy']['mother_id_field_name']]][$line[$id_field]] = array(
				$id_field => $line[$id_field],
				$zz_conf['hierarchy']['mother_id_field_name'] => $line[$zz_conf['hierarchy']['mother_id_field_name']]);
			$lines[$line[$id_field]] = $line;
		}
	}
	if ($h_lines) {
		$level = 0; // level (hierarchy)
		$i = 0; // number of record, for LIMIT
		$my_lines = zz_list_hierarchy($h_lines, $zz_conf['show_hierarchy'], $id_field, $level, $i);
		$total_rows = $i; // sometimes, more rows might be selected beforehands,
		// but if show_hierarchy has ID value, not all rows are shown
		if ($my_lines) {
			$new_lines = array();
			foreach (range($zz_conf['this_limit'] - $zz_conf['limit'], $zz_conf['this_limit']-1) as $index) {
				if (!empty($my_lines[$index])) $new_lines[$my_lines[$index][$id_field]] = $my_lines[$index];
			}
//			$limited_lines = array();
			foreach (array_keys($new_lines) as $key_value) {
				$limited_lines[] = array_merge($new_lines[$key_value], $lines[$key_value]);
			}
			$lines = $limited_lines;
			$count_rows = count($lines);
		}
		foreach ($lines as $line) {
			$ids[] = $line[$id_field];
		}
	}

	// don't show anything if there is nothing
	if (!$count_rows) {
		$zz_conf['show_list'] = false;
		$zz['output'].= '<p>'.zz_text('table-empty').'</p>';
	}

	if ($zz_conf['debug']) 
		$zz['output'] .= zz_show_microtime('Before conditions are set in table', $zz_timer);

	// Conditions
	// Check all conditions whether they are true;
	if ($zz_conf['show_list'] AND !empty($zz['conditions'])) {
		foreach($zz['conditions'] AS $index => $condition) {
			switch ($condition['scope']) {
			// case record remains the same as in form view
			// case query covers more ids
			case 'record':
				$sql = $zz['sql_without_limit'];
				if (!empty($condition['where']))
					$sql = zz_edit_sql($sql, 'WHERE', $condition['where']);
				if (!empty($condition['having']))
					$sql = zz_edit_sql($sql, 'HAVING', $condition['having']);
				$result = mysql_query($sql);
				if ($result AND mysql_num_rows($result)) 
					// maybe get all ids instead?
					while ($line = mysql_fetch_assoc($result)) {
						$zz_conditions['bool'][$index][$line[$id_field]] = true;
					}
				if (mysql_error())
					$zz_error[] = array('msg' => mysql_error(), 'query' => $sql);
				break;
			case 'query':
				$sql = $condition['sql'];
				$sql = zz_edit_sql($sql, 'WHERE', $condition['key_field_name'].' IN ('.implode(', ', $ids).')');
				$result = mysql_query($sql);
				if (mysql_error())
					$zz_error[] = array('msg' => mysql_error(), 'query' => $sql);
				if ($result AND mysql_num_rows($result)) 
					while ($line = mysql_fetch_assoc($result)) {
						if (empty($line[$condition['key_field_name']])) {
							echo 'Error in condition '.$index.', key_field_name is not in field list<br>';
							echo $sql;
							exit;
						}
						$zz_conditions['bool'][$index][$line[$condition['key_field_name']]] = true;
					}
				break;
			default:
			}
		}
		$zz['output'].= zz_error($zz_error);
	}

	// check conditions, these might lead to different field definitions for every
	// line in the list output!
	// that means, $table_query cannot be used for the rest but $line_query instead
	// zz_fill_out must be outside if show_list, because it is neccessary for
	// search results with no resulting records
	zz_fill_out($zz['fields_in_list'], $zz['table'], true); // fill_out, but do not unset conditions
	if ($zz_conf['show_list']) {
		$conditions_applied = array(); // check if there are any conditions
		array_unshift($lines, '0'); // 0 as a dummy record for which no conditions will be set
		foreach ($lines as $index => $line) {
			$line_query[$index] = $zz['fields_in_list'];
			if ($index) foreach ($line_query[$index] as $fieldindex => $field) {
				// conditions
				if (!empty($field['conditions'])) {
					$line_query[$index][$fieldindex] = zz_merge_conditions($field, $zz_conditions['bool'], $line[$id_field]);
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
			if (!empty($line_query[$index])) {
				if ($zz_conf['debug']) 
					$zz['output'] .= zz_show_microtime('Before fill_out', $zz_timer);
				zz_fill_out($line_query[$index], $zz['table'], 2);
				if ($zz_conf['debug']) 
					$zz['output'] .= zz_show_microtime('After fill_out', $zz_timer);
				//zz_print_r($line_query);
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
				if ($zz_conf['debug']) 
					$zz['output'] .= zz_show_microtime('After table_query '.$index, $zz_timer);
			}
		}
		// now we have the basic stuff in $table_query[0] and $line_query[0]
		// if there are conditions, $table_query[1], [2], [3]... and
		// $line_query[1], [2], [3] ... are set
		$zz['fields_in_list'] = $line_query[0]; // for search form
		unset($line_query);
		unset($lines[0]); // remove first dummy array
	}

	if ($zz_conf['debug']) 
		$zz['output'] .= zz_show_microtime('After table_query is set in edittab', $zz_timer);

	// Search Form
	$searchform_top = false;
	$searchform_bottom = false;
	if ($zz['mode'] != 'export' AND $zz_conf['search'] == true) {
		$html_searchform = false;
		if ($total_rows OR isset($_GET['q'])) 
			// show search form only if there are records as a result of this query; 
			// q: show search form if empty search result occured as well
			$html_searchform = zz_search_form($zz['fields_in_list'], $zz['table']);
		if ($zz_conf['search'] === true) $zz_conf['search'] = 'bottom'; // default!
		switch ($zz_conf['search']) {
			case 'top':
				// show form on top only if there are records!
				if ($count_rows) $searchform_top = $html_searchform;
			break;
			case 'both':
				// show form on top only if there are records!
				if ($count_rows) $searchform_top = $html_searchform;
			case 'bottom':
			default:
				$searchform_bottom = $html_searchform;
		}
	}
	$zz['output'] .= $searchform_top;
	
	if ($zz_conf['show_list'] AND $zz_conf['select_multiple_records'] AND $zz['mode'] != 'export') {
		$zz['output'].= '<form action="'.$zz_conf['url_self'].$zz_conf['url_self_qs_base'];
		if ($zz_var['extraGET']) $zz['output'].= $zz_var['url_append'].substr($zz_var['extraGET'], 5); // without first &amp;!
		$zz['output'].= '" method="POST"';
		$zz['output'].= ' accept-charset="'.$zz_conf['character_set'].'">'."\n";
	}

	//
	// Table head
	//	
	if ($zz_conf['show_list'] && $zz_conf['list_display'] == 'table') {
		$zz['output'].= '<table class="data">';
		$zz['output'].= '<thead>'."\n";
		$zz['output'].= '<tr>';
		if ($zz_conf['select_multiple_records']) $zz['output'].= '<th>[]</th>';
		$unsortable_fields = array('calculated', 'image', 'upload_image'); // 'subtable'?
		$show_field = true;
		foreach ($table_query[0] as $index => $field) {
			if ($zz_conf['group']
				AND ((!empty($field['display_field']) && $field['display_field'] == $zz_conf['group']) 
				OR (!empty($field['field_name']) AND $field['field_name'] == $zz_conf['group']))) {
				$zz_conf['group_field_no'] = $index;
				$show_field = false;
			}
			if ($show_field) {
				$zz['output'].= '<th'.check_if_class($field, (!empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : '')).'>';
				if (!in_array($field['type'], $unsortable_fields) && isset($field['field_name'])) { 
					$zz['output'].= '<a href="';
					if (isset($field['display_field'])) $order_val = $field['display_field'];
					else $order_val = $field['field_name'];
					$unwanted_keys = array('dir');
					$new_keys = array('order' => $order_val);
					$uri = $zz_conf['url_self'].zz_edit_query_string($zz_conf['url_self_qs_base'].$zz_conf['url_self_qs_zzform'], $unwanted_keys, $new_keys);
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
		if ($zz_conf['edit'] OR $zz_conf['view'])
			$zz['output'].= ' <th class="editbutton">'.zz_text('action').'</th>';
		$show_details_head = false;
		if (!empty($zz_conf['details'])) $show_details_head = true;
		if (!empty($zz_conf['conditions'])) {
			foreach ($zz_conf['conditions'] as $condition)
				if (!empty($condition['details'])) $show_details_head = true;
		}
		if ($show_details_head) 
			$zz['output'].= ' <th class="editbutton">'.zz_text('detail').'</th>';
		$zz['output'].= '</tr>';
		$zz['output'].= '</thead>'."\n";
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'ul') {
		if ($zz_conf['group']) {
			foreach ($table_query[0] as $index => $field) {
				if ((!empty($field['display_field']) && $field['display_field'] == $zz_conf['group']) 
					OR (!empty($field['field_name']) AND $field['field_name'] == $zz_conf['group']))
				$zz_conf['group_field_no'] = $index;
			}
		} else {
			$zz['output'].= '<ul class="data">';
		}
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'csv') {
		$tablerow = false;
		foreach ($table_query[0] as $field)
			$tablerow[] = $zz_conf['export_csv_enclosure'].str_replace($zz_conf['export_csv_enclosure'], $zz_conf['export_csv_enclosure'].$zz_conf['export_csv_enclosure'], $field['title']).$zz_conf['export_csv_enclosure'];
		$zz['output'].= implode($zz_conf['export_csv_delimiter'], $tablerow)."\r\n";
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'pdf') {
		$zz['output']['head'] = $table_query[0];
	}

	//
	// Table data
	//	

	if ($zz_conf['show_list']) {
		$id_fieldname = false;
		$z = 0;
		$ids = array();
		//$group_hierarchy = false; // see below, hierarchical grouping

		foreach ($lines as $index => $line) {
			// put lines in new array, rows.
			//$rows[$z][0]['text'] = '';
			//$rows[$z][0]['class'] = '';
			
			$tq_index = (count($table_query) > 1 ? $index : 0);
			$id = '';
			$sub_id = '';
			if (empty($rows[$z]['group']))
				$rows[$z]['group'] = '';
			if ($zz_conf['group']) {
				foreach ($table_query[$tq_index] as $fieldindex => $field) {
				//	check for group function
					if ($fieldindex == $zz_conf['group_field_no']) {
					/*	
						TODO: hierarchical grouping!
						if (!empty($field['show_hierarchy'])) {
							if (!$group_hierarchy) $group_hierarchy = zz_tab_get_group_hierarchy($field);
							$rows[$z]['group'] = zz_tab_show_group_hierarchy($line[$field['field_name']], $group_hierarchy);
						} else
					*/
						if (!empty($field['display_field']))
							$rows[$z]['group'] = $line[$field['display_field']];
						elseif (!empty($field['enum_title']) AND $field['type'] == 'select') {
							foreach ($field['enum'] as $mkey => $mvalue)
								if ($mvalue == $line[$field['field_name']]) 
									$rows[$z]['group'] = $field['enum_title'][$mkey];
						} elseif (!empty($field['field_name']))
							$rows[$z]['group'] = $line[$field['field_name']];
						break;
					}
				}
			}
			$zz_conf_thisrec = $zz_conf; // configuration variables just for this line
			if (!empty($line['zz_conf'])) // check whether there are different configuration variables e. g. for hierarchies
				$zz_conf_thisrec = array_merge($zz_conf_thisrec, $line['zz_conf']);
			if ($zz_conf_thisrec['select_multiple_records']) { // checkbox for records
				$rows[$z][-1]['text'] = '<input type="checkbox" name="zz_record[]" value="'.$line[$id_field].'">'; // $id
				$rows[$z][-1]['class'] = ' class="select_multiple_records"';
			}

			foreach ($table_query[$tq_index] as $fieldindex => $field) {
				// conditions
				if (!empty($field['conditions']))
					$field = zz_merge_conditions($field, $zz_conditions['bool'], $line[$id_field]);
				if (!empty($zz_conf_thisrec['conditions']))
					$zz_conf_thisrec = zz_merge_conditions($zz_conf_thisrec, $zz_conditions['bool'], $line[$id_field]);

				// group
				if ($zz_conf['group'] && $fieldindex == $zz_conf['group_field_no'])
					continue;

				// check all fields next to each other with list_append_next					
				while (!empty($table_query[$tq_index][$fieldindex]['list_append_next'])) $fieldindex++;
				
			//	initialize variables
				if (isset($line['zz_level']) 
					AND !empty($field['field_name'])  // occurs in case of subtables
					AND $field['field_name'] == $zz_conf['hierarchy']['display_in']) {
					$field['level'] = $line['zz_level'];
				}
				if (empty($rows[$z][$fieldindex]['class']))
					$rows[$z][$fieldindex]['class'] = check_if_class($field, 
						(!empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : ''));
				if (empty($rows[$z][$fieldindex]['text']))
					$rows[$z][$fieldindex]['text'] = '';
				
				if (!empty($field['list_prefix'])) $rows[$z][$fieldindex]['text'] .= zz_text($field['list_prefix']);
				$stringlength = strlen($rows[$z][$fieldindex]['text']);
			//	if there's a link, glue parts together
				$link = false;
				if (isset($field['link']) && $zz['mode'] != 'export') {
					if (is_array($field['link']))
						$link = show_link($field['link'], $line).(empty($field['link_no_append']) ? $line[$field['field_name']] : '');
					else
						$link = $field['link'].$line[$field['field_name']];
					$link_title = false;
					if (!empty($field['link_title'])) {
						if (is_array($field['link_title']))
							$link_title = show_link($field['link_title'], $line);
						else
							$link_title = $field['link_title'];
					}
				}
				if ($link)
					$link = '<a href="'.$link.'"'
						.(!empty($field['link_target']) ? ' target="'.$field['link_target'].'"' : '')
						.($link_title ? ' title="'.$link_title.'"' : '').'>';
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
								if ($rows[$z]['group']) {
									if (!isset($sum_group[$rows[$z]['group']][$field['title']])) 
										$sum_group[$rows[$z]['group']][$field['title']] = 0;
									$sum_group[$rows[$z]['group']][$field['title']] += $diff;
								}
							}
						} elseif ($field['calculation'] == 'sum') {
							$my_sum = 0;
							foreach ($field['calculation_fields'] as $calc_field)
								$my_sum += $line[$calc_field];
							$rows[$z][$fieldindex]['text'].= $my_sum;
							if (isset($field['sum']) && $field['sum'] == true) {
								if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
								$sum[$field['title']] .= $my_sum;
								if ($rows[$z]['group']) {
									if (!isset($sum_group[$rows[$z]['group']][$field['title']])) 
										$sum_group[$rows[$z]['group']][$field['title']] = 0;
									$sum_group[$rows[$z]['group']][$field['title']] .= $my_sum;
								}

							}
						} elseif ($field['calculation'] == 'sql')
							$rows[$z][$fieldindex]['text'].= $line[$field['field_name']];
						break;
					case 'image':
					case 'upload_image':
						if (isset($field['path'])) {
							if ($img = show_image($field['path'], $line))
								$rows[$z][$fieldindex]['text'].= ($link ? $link : '').$img.($link ? '</a>' : '');
							elseif (isset($field['default_image']))
								$rows[$z][$fieldindex]['text'].= ($link ? $link : '').'<img src="'
									.$field['default_image'].'"  alt="'.zz_text('no_image')
									.'" class="thumb">'.($link ? '</a>' : '');
							if (!empty($field['image'])) foreach ($field['image'] as $image)
								if (!empty($image['show_link']) && $zz['mode'] != 'export')
									if ($link = show_link($image['path'], $line))
										$rows[$z][$fieldindex]['text'] .= ' <a href="'.$link.'">'.$image['title'].'</a><br>' ;
						}
						break;
					case 'subtable':
						if (!empty($field['subselect']['sql'])) {
							// fill array subselects, just in row 0, will always be the same!
							if (!$z) {
								foreach ($field['fields'] as $subfield) {
									if ($subfield['type'] == 'foreign_key') {
										if (!empty($subfield['key_field_name'])) // different fieldnames
											$id_fieldname = $subfield['key_field_name'];
										else
											$id_fieldname = $subfield['field_name'];
										// id_field = joined_table.field_name
										$field['subselect']['id_table_and_fieldname'] = $zz['table'].'.'.$id_fieldname;
										// just field_name
										$field['subselect']['id_fieldname'] = $id_fieldname;
									}
								}
								$field['subselect']['fieldindex'] = $fieldindex;
								$subselects[] = $field['subselect'];
							}
							$sub_id = $line[$id_fieldname]; // get correct ID
						} elseif (!empty($field['display_field'])) {
							$rows[$z][$fieldindex]['text'].= $line[$field['display_field']];
						}
						break;
					case 'url':
					case 'mail':
						if ($zz['mode'] != 'export')
							$rows[$z][$fieldindex]['text'].= '<a href="'
								.($field['type'] == 'mail' ? 'mailto:' : '')
								.$line[$field['field_name']].'">';
						if (!empty($field['display_field']))
							$rows[$z][$fieldindex]['text'].= htmlchars($line[$field['display_field']]);
						elseif ($field['type'] == 'url' && strlen($line[$field['field_name']]) > $zz_conf_thisrec['max_select_val_len'])
							$rows[$z][$fieldindex]['text'].= substr(htmlchars($line[$field['field_name']]), 0, $zz_conf_thisrec['max_select_val_len']).'...';
						else
							$rows[$z][$fieldindex]['text'].= htmlchars($line[$field['field_name']]);
						if ($zz['mode'] != 'export')
							$rows[$z][$fieldindex]['text'].= '</a>';
						break;
					case 'id':
						$id = $line[$field['field_name']];
					default:
						if ($link) $rows[$z][$fieldindex]['text'].= $link;
						if (!empty($field['display_field'])) $rows[$z][$fieldindex]['text'].= htmlchars($line[$field['display_field']]);
						else {
							// replace field content with display_title, if set.
							if (!empty($field['display_title']) 
								&& in_array($line[$field['field_name']], array_keys($field['display_title'])))
								$line[$field['field_name']] = $field['display_title'][$line[$field['field_name']]];

							if (isset($field['factor']) && $line[$field['field_name']]) $line[$field['field_name']] /=$field['factor'];
							if ($field['type'] == 'unix_timestamp') $rows[$z][$fieldindex]['text'].= date('Y-m-d H:i:s', $line[$field['field_name']]);
							elseif ($field['type'] == 'select' && !empty($field['set']))
								$rows[$z][$fieldindex]['text'].= str_replace(',', ', ', $line[$field['field_name']]);
							elseif ($field['type'] == 'select' && !empty($field['enum']) && !empty($field['enum_title'])) { // show enum_title instead of enum
								foreach ($field['enum'] as $mkey => $mvalue)
									if ($mvalue == $line[$field['field_name']]) $rows[$z][$fieldindex]['text'] .= $field['enum_title'][$mkey];
							} elseif ($field['type'] == 'date') $rows[$z][$fieldindex]['text'].= datum_de($line[$field['field_name']]);
							elseif (isset($field['number_type']) && $field['number_type'] == 'currency') $rows[$z][$fieldindex]['text'].= waehrung($line[$field['field_name']], '');
							elseif (isset($field['number_type']) && $field['number_type'] == 'latitude' && $line[$field['field_name']]) {
								$deg = dec2dms($line[$field['field_name']], '');
								$rows[$z][$fieldindex]['text'].= $deg['latitude_dms'];
							} elseif (isset($field['number_type']) && $field['number_type'] == 'longitude' &&  $line[$field['field_name']]) {
								$deg = dec2dms('', $line[$field['field_name']]);
								$rows[$z][$fieldindex]['text'].= $deg['longitude_dms'];
							} elseif (!empty($field['display_value'])) {
								$rows[$z][$fieldindex]['text'].= $field['display_value'];
							} elseif ($zz['mode'] == 'export') {
								$rows[$z][$fieldindex]['text'].= $line[$field['field_name']];
							} elseif (!empty($field['list_format'])) {
								$rows[$z][$fieldindex]['text'].= $field['list_format']($line[$field['field_name']]);
							} else
								$rows[$z][$fieldindex]['text'].= nl2br(htmlchars($line[$field['field_name']]));
						}
						if ($link) $rows[$z][$fieldindex]['text'].= '</a>';
						if (isset($field['sum']) && $field['sum'] == true) {
							if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
							$sum[$field['title']] += $line[$field['field_name']];
							if ($rows[$z]['group']) {
								if (!isset($sum_group[$rows[$z]['group']][$field['title']])) 
									$sum_group[$rows[$z]['group']][$field['title']] = 0;
								$sum_group[$rows[$z]['group']][$field['title']] += $line[$field['field_name']];
							}
						}
					}
					if (isset($field['unit']) && $rows[$z][$fieldindex]['text']) 
						$rows[$z][$fieldindex]['text'].= '&nbsp;'.$field['unit'];	
				if (strlen($rows[$z][$fieldindex]['text']) == $stringlength) { // string empty or nothing appended
					if (!empty($field['list_prefix']))
						$rows[$z][$fieldindex]['text'] = substr($rows[$z][$fieldindex]['text'], 0, $stringlength - strlen(zz_text($field['list_prefix'])));
				} else
					if (!empty($field['list_suffix'])) $rows[$z][$fieldindex]['text'] .= zz_text($field['list_suffix']);

			}
			$ids[$z] = $sub_id; // for subselects
			if ($zz_conf_thisrec['edit'] OR $zz_conf_thisrec['view']) {
				$rows[$z]['editbutton'] = false;
				if ($zz_conf_thisrec['edit']) 
					$rows[$z]['editbutton'] = '<a href="'.$zz_conf['url_self'].$zz_conf['url_self_qs_base']
						.$zz_var['url_append'].'mode=edit&amp;id='.$id
						.$zz_var['extraGET'].'">'.zz_text('edit').'</a>';
				elseif ($zz_conf_thisrec['view'])
					$rows[$z]['editbutton'] = '<a href="'.$zz_conf['url_self'].$zz_conf['url_self_qs_base']
						.$zz_var['url_append'].'mode=show&amp;id='.$id
						.$zz_var['extraGET'].'">'.zz_text('show').'</a>';

				if ($zz_conf_thisrec['delete']) {
					$rows[$z]['editbutton'] .= '&nbsp;| <a href="'
						.$zz_conf['url_self'].$zz_conf['url_self_qs_base'].$zz_var['url_append'].'mode=delete&amp;id='
						.$id.$zz_var['extraGET'].'">'.zz_text('delete').'</a>';
				}
			}
			if (isset($zz_conf_thisrec['details'])) {
				$rows[$z]['actionbutton'] = zz_show_more_actions($zz_conf_thisrec['details'], 
					$zz_conf_thisrec['details_url'],  $zz_conf_thisrec['details_base'], 
					$zz_conf_thisrec['details_target'], $zz_conf_thisrec['details_referer'], $id, $line);
			}
			$z++;
		}
	}
	
	// get values for "subselects" in detailrecords
	
	foreach ($subselects as $subselect) {
		// default values
		if (!isset($subselect['prefix'])) $subselect['prefix'] = '<p>';
		if (!isset($subselect['concat_rows'])) $subselect['concat_rows'] = "</p>\n<p>";
		if (!isset($subselect['suffix'])) $subselect['suffix'] = '</p>';
		if (!isset($subselect['concat_fields'])) $subselect['concat_fields'] = ' ';
		if (!isset($subselect['show_empty_cells'])) $subselect['show_empty_cells'] = false;
		
		$lines = false;
		
		$subselect['sql'] = zz_edit_sql($subselect['sql'], 'WHERE', $subselect['id_table_and_fieldname'].' 
			IN ('.implode(', ', $ids).')');
		
		$s_result = mysql_query($subselect['sql']);
		if ($s_result) if (mysql_num_rows($s_result))
			while ($line = mysql_fetch_assoc($s_result)) {
				if (empty($line[$subselect['id_fieldname']])) {
					$zz_error[] = array('msg' => 'Subselect SQL definition needs the field which is foreign_key!',
						'query' => $subselect['sql']);
					echo zz_error($zz_error);
					exit;
				}
				$myline = $line;
				unset ($myline[$subselect['id_fieldname']]); // ID field will not be shown
				$lines[$line[$subselect['id_fieldname']]][] = $myline;
			}
		if (mysql_error()) {
			echo (mysql_error());
			echo '<br>'.$subselect['sql'];
		}
		
		foreach ($ids as $z_row => $id) {
			if (!empty($lines[$id])) {
				$linetext = false;
				foreach ($lines[$id] as $linefields) {
					$fieldtext = false;
					foreach ($linefields as $db_fields) {
						if ($subselect['show_empty_cells'] AND !$db_fields) $db_fields = '&nbsp;';
						if ($fieldtext AND $db_fields) $fieldtext .= $subselect['concat_fields'];
						$fieldtext .= $db_fields;
					}
					$linetext[] = $fieldtext;
				}
				$rows[$z_row][$subselect['fieldindex']]['text'].= 
					$subselect['prefix'].implode($subselect['concat_rows'], $linetext).$subselect['suffix'];
			}
		}
	}

	//
	// Table footer
	//
	
	$my_footer_table = (!empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : false);
	if ($zz_conf['show_list'] && $zz_conf['tfoot'] && $zz_conf['list_display'] == 'table') {
		$zz['output'].= '<tfoot>'."\n".'<tr>';
		$zz['output'].= zz_field_sum($table_query[0], $z, $my_footer_table, $sum, $zz_conf);
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
			if (!empty($zz_conf['group']))
				if (empty($row['group'])) $row['group'] = zz_text('- unknown -');
				if ($row['group'] != $rowgroup) {
					if ($rowgroup) {
						if ($zz_conf['tfoot'])
							$zz['output'] .= '<tr class="group_sum">'.zz_field_sum($table_query[0], $z, $my_footer_table, $sum_group[$rowgroup], $zz_conf).'</tr>'."\n";
						$zz['output'] .= '</tbody><tbody>'."\n";
					}
					$zz['output'].= '<tr class="group"><td colspan="'.(count($row)-1).'"><strong>'.$row['group'].'</strong></td></tr>'."\n";
					$rowgroup = $row['group'];
				}
			$zz['output'].= '<tr class="'.($index & 1 ? 'uneven':'even').
				(($index+1) == $count_rows ? ' last' : '').'">'; //onclick="Highlight();"
			foreach ($row as $fieldindex => $field)
				if (is_numeric($fieldindex)) $zz['output'].= '<td'.$field['class'].'>'.$field['text'].'</td>';
			if (!empty($row['editbutton']))
				$zz['output'].= '<td class="editbutton">'.$row['editbutton'].'</td>';
			if (!empty($row['actionbutton']))
				$zz['output'].= '<td class="editbutton">'.$row['actionbutton'].'</td>';
			$zz['output'].= '</tr>'."\n";
		}
		if ($zz_conf['tfoot'] && $rowgroup)
			$zz['output'] .= '<tr class="group_sum">'.zz_field_sum($table_query[0], $z, $my_footer_table, $sum_group[$rowgroup], $zz_conf).'</tr>'."\n";
		$zz['output'].= '</tbody>'."\n";
		unset($rows);
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'ul') {
		$rowgroup = false;
		foreach ($rows as $index => $row) {
			if (!empty($zz_conf['group']))
				if (empty($row['group'])) $row['group'] = zz_text('- unknown -');
				if ($row['group'] != $rowgroup) {
					if ($rowgroup) {
						$zz['output'] .= '</ul><br clear="all">'."\n";
					}
					$zz['output'].= "\n".'<h3>'.$row['group'].'</h3>'."\n"
						.'<ul class="data">';
					$rowgroup = $row['group'];
				}
			$zz['output'].= '<li class="'.($index & 1 ? 'uneven':'even').
				(($index+1) == $count_rows ? ' last' : '').'">'; //onclick="Highlight();"
			foreach ($row as $fieldindex => $field)
				if (is_numeric($fieldindex) && $field['text'])
					$zz['output'].= '<p'.$field['class'].'>'.$field['text'].'</p>';
			if (!empty($row['editbutton']))
				$zz['output'].= '<p class="editbutton">'.$row['editbutton'].'</p>';
			if (!empty($row['actionbutton']))
				$zz['output'].= '<p class="editbutton">'.$row['actionbutton'].'</p>';
			$zz['output'].= '</li>'."\n";
		}
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'csv') {
		foreach ($rows as $index => $row) {
			$tablerow = false;
			foreach ($row as $fieldindex => $field)
				if (!$fieldindex OR is_numeric($fieldindex)) { // 0 or 1 or 2 ...
					$myfield = str_replace('"', '""', $field['text']);
					if (!empty($subselect['export_no_html'])) {
						$myfield = str_replace("&nbsp;", " ", $myfield);
						$myfield = str_replace("<\p>", "\n\n", $myfield);
						$myfield = str_replace("<br>", "\n", $myfield);
						$myfield = strip_tags($myfield);
					}
					if ($myfield)
						$tablerow[] = $zz_conf['export_csv_enclosure'].$myfield.$zz_conf['export_csv_enclosure'];
					else
						$tablerow[] = false; // empty value
				}
			$zz['output'].= implode($zz_conf['export_csv_delimiter'], $tablerow)."\r\n";
		}
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'pdf') {
		$zz['output']['rows'] = $rows;
	}

	if ($zz_conf['show_list'])
		if ($zz_conf['list_display'] == 'table')
			$zz['output'].= '</table>'."\n";
		elseif ($zz_conf['list_display'] == 'ul')
			$zz['output'].= '</ul>'."\n".'<br clear="all">';

	if ($zz_conf['show_list'] AND $zz_conf['select_multiple_records'] AND $zz['mode'] != 'export') {
		$zz['output'].= '<input type="hidden" name="action" value="Multiple action"><input type="submit" value="'
			.zz_text('Delete selected records').'" name="multiple_delete">'
			.'</form>'."\n";
	}
	//
	// Buttons below table (add, record nav, search)
	//

	// Add new record
	if ($zz['mode'] != 'export') {
		$toolsline = array();
		// normal add button, only if list was shown beforehands
		if ($zz['mode'] != 'add' && $zz_conf['add'] AND !is_array($zz_conf['add']) && $zz_conf['show_list'])
			$toolsline[] = '<a accesskey="n" href="'
				.$zz_conf['url_self'].$zz_conf['url_self_qs_base'].$zz_var['url_append'].'mode=add'.$zz_var['extraGET'].'">'
				.zz_text('Add new record').'</a>';
		// multi-add-button, also show if there was no list, because it will only be shown below records!
		
		if ($zz['mode'] != 'add' && $zz_conf['add'] AND is_array($zz_conf['add']))
			foreach ($zz_conf['add'] as $add)
				$zz['output'].= '<p class="add-new"><a href="'.$zz_conf['url_self']
					.$zz_conf['url_self_qs_base'].$zz_var['url_append']
					.'mode=add'.$zz_var['extraGET'].'&amp;add['.$add['field_name'].']='
					.$add['value'].'">'.sprintf(zz_text('Add new %s'), $add['type']).'</a></p>'."\n";

		if ($zz_conf['export'] AND $total_rows) 
			$toolsline = array_merge($toolsline, zz_export_links($zz_conf['url_self']
				.$zz_conf['url_self_qs_base'].$zz_var['url_append'], $zz_var['extraGET']));
		if ($toolsline)
			$zz['output'].= '<p class="add-new bottom-add-new">'.implode(' | ', $toolsline).'</p>';
		// Total records
		if ($total_rows == 1) $zz['output'].= '<p class="totalrecords">'.$total_rows.' '.zz_text('record total').'</p>'; 
		elseif ($total_rows) $zz['output'].= '<p class="totalrecords">'.$total_rows.' '.zz_text('records total').'</p>';
		// Limit links
		$zz['output'].= zz_limit($zz_conf['limit'], $zz_conf['this_limit'], $count_rows, $zz['sql'], $total_rows, 'body');	// NEXT, PREV Links at the end of the page
		// Search form
		$zz['output'] .= $searchform_bottom;
	} elseif ($zz_conf['list_display'] == 'pdf') {
		zz_pdf($zz);
		exit;
	}
}

function zz_field_sum($table_query, $z, $table, $sum, $zz_conf) {
	$tfoot_line = '';
	foreach ($table_query as $index => $field)
		if ((!$zz_conf['group_field_no'] OR $index != $zz_conf['group_field_no'])
			AND $field['show_field']) {
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
			} else $tfoot_line.= '<td'.(!empty($field['class']) ? ' class="'.$field['class'].'"' : '').'>&nbsp;</td>';
		}
	return $tfoot_line;
}

function zz_list_hierarchy($h_lines, $show_hierarchy, $id_field, $level, &$i) {
	$my_lines = array();
	if (!$level AND $show_hierarchy != 'NULL' AND !empty($h_lines['TOP'])) {
		// show uppermost line
		$h_lines['TOP'][0]['zz_level'] = $level;
		$my_lines[$i] = $h_lines['TOP'][$show_hierarchy];
		$my_lines[$i]['zz_conf']['delete'] = false; // this page has child pages, don't allow deletion
		$i++;
	}
	if ($show_hierarchy != 'NULL') $level++; // don't indent uppermost level if top category is NULL
	if (!empty($h_lines[$show_hierarchy])) foreach ($h_lines[$show_hierarchy] as $h_line) {
		$h_line['zz_level'] = $level;
		$my_lines[$i] = $h_line;
		$i++;
		if (!empty($h_lines[$h_line[$id_field]])) {
			$my_lines[($i-1)]['zz_conf']['delete'] = false; // this page has child pages, don't allow deletion
			$my_lines = array_merge($my_lines, 
				zz_list_hierarchy($h_lines, $h_line[$id_field], $id_field, $level, $i));
		}
	}
	return $my_lines;
}

?>