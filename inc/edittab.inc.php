<?php

/*
	zzform Scripts

	function zz_display_table
		displays table with records (limited by zz_conf['limit'])
		displays add new record, record navigation (if zz_conf['limit'] = true)
		and search form below table
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2006

*/

function zz_display_table(&$zz, $zz_conf, &$zz_error, $zz_var, $zz_lines) {
	global $text;
	global $zz_conf;
	$subselects = array();
	
	// Display
	// remove elements from table which shall not be shown
	
	if ($zz['mode'] == 'export') {
		foreach ($zz['fields'] as $field)
			if (!isset($field['export']) || $field['export']) $table_query[] = $field;
	} else {
		foreach ($zz['fields'] as $field)
			if (empty($field['hide_in_list'])) $table_query[] = $field;
	}

	// Search Form
	$searchform_top = false;
	$searchform_bottom = false;

	if ($zz_conf['list_display'] != 'csv')
		if ($zz_conf['search'] == true) {
			$html_searchform = false;
			if ($zz_lines OR isset($_GET['q'])) 
				// show search form only if there are records as a result of this query; 
				// q: show search form if empty search result occured as well
				$html_searchform = zz_search_form($zz_conf['url_self'], $zz['fields'], $zz['table']);
			if ($zz_conf['search'] === true) $zz_conf['search'] = 'bottom'; // default!
			switch ($zz_conf['search']) {
				case 'top':
					$searchform_top = $html_searchform;
				break;
				case 'both':
					$searchform_top = $html_searchform;
				case 'bottom':
				default:
					$searchform_bottom = $html_searchform;
			}
		}

	//
	// Table head
	//
	if ($zz_conf['this_limit']) {
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
		$zz['output'].= zz_error($zz_error = array(
			'mysql' => mysql_error(), 
			'query' => $zz['sql'], 
			'msg' => $text['error-sql-incorrect']));
	}
	if (!$count_rows) {
		$zz_conf['show_list'] = false;
		$zz['output'].= '<p>'.$text['table-empty'].'</p>';
	}

	$zz['output'] .= $searchform_top;
	
	if ($zz_conf['show_list'])
		if ($zz_conf['list_display'] == 'table')
			$zz['output'].= '<table class="data">';
		elseif ($zz_conf['list_display'] == 'ul')
			$zz['output'].= '<ul class="data">';

	//
	// Table head
	//	
	
	if ($zz_conf['show_list'] && $zz_conf['list_display'] == 'table') {
		$zz['output'].= '<thead>'."\n";
		$zz['output'].= '<tr>';
		$unsortable_fields = array('calculated', 'image', 'upload_image'); // 'subtable'?
		$show_field = true;
		foreach ($table_query as $index => $field) {
			if ($zz_conf['group']
				AND ((!empty($field['display_field']) && $field['display_field'] == $zz_conf['group']) 
				OR $field['field_name'] == $zz_conf['group'])) {
				$zz_conf['group_field_no'] = $index;
				$show_field = false;
			}
			if ($show_field) {
				$zz['output'].= '<th'.check_if_class($field, (!empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : '')).'>';
				if (!in_array($field['type'], $unsortable_fields) && isset($field['field_name'])) { 
					$zz['output'].= '<a href="';
					if (isset($field['display_field'])) $order_val = $field['display_field'];
					else $order_val = $field['field_name'];
					$uri = addvar($_SERVER['REQUEST_URI'], 'order', $order_val);
					$order_dir = 'asc';
					if (str_replace('&amp;', '&', $uri) == $_SERVER['REQUEST_URI']) {
						$uri.= '&amp;dir=desc';
						$order_dir = 'desc';
					}
					$zz['output'].= $uri;
					$zz['output'].= '" title="'.$text['order by'].' '.strip_tags($field['title']).' ('.$text[$order_dir].')">';
				}
				$zz['output'].= (!empty($field['title_tab']) ? $field['title_tab'] : $field['title']);
				if (!in_array($field['type'], $unsortable_fields) && isset($field['field_name']))
					$zz['output'].= '</a>';
				$zz['output'].= '</th>';
			}
			if (!empty($field['list_append_next'])) $show_field = false;
			else $show_field = true;
		}
		if ($zz_conf['edit'] OR $zz_conf['view'])
			$zz['output'].= ' <th class="editbutton">'.$text['action'].'</th>';
		if (isset($zz_conf['details']) && $zz_conf['details']) 
			$zz['output'].= ' <th class="editbutton">'.$text['detail'].'</th>';
		$zz['output'].= '</tr>';
		$zz['output'].= '</thead>'."\n";
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'csv') {
		$tablerow = false;
		foreach ($table_query as $field)
			$tablerow[] = $zz_conf['export_csv_enclosure'].str_replace($zz_conf['export_csv_enclosure'], $zz_conf['export_csv_enclosure'].$zz_conf['export_csv_enclosure'], $field['title']).$zz_conf['export_csv_enclosure'];
		$zz['output'].= implode($zz_conf['export_csv_delimiter'], $tablerow)."\r\n";
	}

	//
	// Table data
	//	

	if ($zz_conf['show_list']) {
		$z = 0;
		$ids = array();
		$id_fieldname = false;
		while ($line = mysql_fetch_assoc($result)) {
			// put lines in new array, rows.
			//$rows[$z][0]['text'] = '';
			//$rows[$z][0]['class'] = '';
			
			$id = '';
			$sub_id = '';
			if (empty($rows[$z]['group']))
				$rows[$z]['group'] = '';
			if ($zz_conf['group']) {
				foreach ($table_query as $fieldindex => $field) {
				//	check for group function
					if ($fieldindex == $zz_conf['group_field_no']) {
						if (!empty($field['display_field']))
							$rows[$z]['group'] = $line[$field['display_field']];
						elseif (!empty($field['field_name']))
							$rows[$z]['group'] = $line[$field['field_name']];
						break;
					}
				}
			}
			
			foreach ($table_query as $fieldindex => $field) {
				if ($zz_conf['group'] && $fieldindex == $zz_conf['group_field_no'])
					continue;
					
				if (!empty($field['list_append_next'])) $fieldindex++;
			//	initialize variables
				if (empty($rows[$z][$fieldindex]['class']))
					$rows[$z][$fieldindex]['class'] = check_if_class($field, (!empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : ''));
				if (empty($rows[$z][$fieldindex]['text']))
					$rows[$z][$fieldindex]['text'] = '';
				
				if (!empty($field['list_prefix'])) $rows[$z][$fieldindex]['text'] .= $field['list_prefix'];
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
									.$field['default_image'].'"  alt="'.$text['no_image']
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
										$field['subselect']['id_fieldname'] = $subfield['field_name'];
										$id_fieldname = $field['subselect']['id_fieldname'];
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
						elseif ($field['type'] == 'url' && strlen($line[$field['field_name']]) > $zz_conf['max_select_val_len'])
							$rows[$z][$fieldindex]['text'].= substr(htmlchars($line[$field['field_name']]), 0, $zz_conf['max_select_val_len']).'...';
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
						$rows[$z][$fieldindex]['text'] = substr($rows[$z][$fieldindex]['text'], 0, $stringlength - strlen($field['list_prefix']));
				} else
					if (!empty($field['list_suffix'])) $rows[$z][$fieldindex]['text'] .= $field['list_suffix'];

			}
			$ids[$z] = $sub_id; // for subselects
			if ($zz_conf['edit'] OR $zz_conf['view']) {
				 $rows[$z]['editbutton'] = false;
				if ($zz_conf['edit']) 
					$rows[$z]['editbutton'] = '<a href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=edit&amp;id='.$id.$zz['extraGET'].'">'.$text['edit'].'</a>';
				elseif ($zz_conf['view'])
					$rows[$z]['editbutton'] = '<a href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=show&amp;id='.$id.$zz['extraGET'].'">'.$text['show'].'</a>';
				if ($zz_conf['delete']) $rows[$z]['editbutton'] .= '&nbsp;| <a href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=delete&amp;id='.$id.$zz['extraGET'].'">'.$text['delete'].'</a>';
			}
			if (isset($zz_conf['details'])) {
				$rows[$z]['actionbutton'] = show_more_actions($zz_conf['details'], $zz_conf['details_url'],  $zz_conf['details_base'], $zz_conf['details_target'], $zz_conf['details_referer'], $id, $line);
			}
			$z++;
		}
	}
	
	// get values for "subselects" in detailrecords
	
	foreach ($subselects as $subselect) {
		// default values
		if (empty($subselect['prefix'])) $subselect['prefix'] = '<p>';
		if (empty($subselect['concat_rows'])) $subselect['concat_rows'] = "</p>\n<p>";
		if (empty($subselect['suffix'])) $subselect['suffix'] = '</p>';
		if (empty($subselect['concat_fields'])) $subselect['concat_fields'] = ' ';
		
		$lines = false;
		
		$zz_error['query'] = $subselect['sql'];
		$subselect['sql'] .= ' WHERE '.$subselect['id_fieldname'].' 
			IN ('.implode(', ', $ids).')';
		
		$s_result = mysql_query($subselect['sql']);
		if ($s_result) if (mysql_num_rows($s_result))
			while ($line = mysql_fetch_assoc($s_result)) {
				if (empty($line[$subselect['id_fieldname']])) {
					$zz_error['msg'] = 'Subselect SQL definition needs the field which is foreign_key!';
					echo zz_error($zz_error);
					exit;
				}
				$myline = $line;
				unset ($myline[$subselect['id_fieldname']]); // ID field will not be shown
				$lines[$line[$subselect['id_fieldname']]][] = $myline;
			}
		
		foreach ($ids as $z_row => $id) {
			if (!empty($lines[$id])) {
				$linetext = false;
				foreach ($lines[$id] as $linefields) {
					$fieldtext = false;
					foreach ($linefields as $db_fields) {
						if ($fieldtext) $fieldtext .= $subselect['concat_fields'];
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
		$zz['output'].= zz_field_sum($table_query, $z, $my_footer_table, $sum, $zz_conf);
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
			if (!empty($row['group']))
				if ($row['group'] != $rowgroup) {
					if ($rowgroup) {
						if ($zz_conf['tfoot'])
							$zz['output'] .= '<tr class="group_sum">'.zz_field_sum($table_query, $z, $my_footer_table, $sum_group[$rowgroup], $zz_conf).'</tr>'."\n";
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
			$zz['output'] .= '<tr class="group_sum">'.zz_field_sum($table_query, $z, $my_footer_table, $sum_group[$rowgroup], $zz_conf).'</tr>'."\n";
		$zz['output'].= '</tbody>'."\n";
		unset($rows);
	} elseif ($zz_conf['show_list'] && $zz_conf['list_display'] == 'ul') {
		foreach ($rows as $index => $row) {
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
				if (is_numeric($fieldindex)) {
					$myfield = str_replace('"', '""', $field['text']);
					if (!empty($subselect['export_no_html'])) {
						$myfield = str_replace("&nbsp;", " ", $myfield);
						$myfield = str_replace("<\p>", "\n\n", $myfield);
						$myfield = str_replace("<br>", "\n", $myfield);
						$myfield = strip_tags($myfield);
					}
					
					$tablerow[] = $zz_conf['export_csv_enclosure'].$myfield.$zz_conf['export_csv_enclosure'];
				}
			$zz['output'].= implode($zz_conf['export_csv_delimiter'], $tablerow)."\r\n";
		}
	}

	if ($zz_conf['show_list'])
		if ($zz_conf['list_display'] == 'table')
			$zz['output'].= '</table>'."\n";
		elseif ($zz_conf['list_display'] == 'ul')
			$zz['output'].= '</ul>'."\n".'<br clear="all">';

	//
	// Buttons below table (add, record nav, search)
	//

	// Add new record
	if ($zz_conf['list_display'] != 'csv') {
		if ($zz['mode'] != 'add' && $zz_conf['add'] && $zz_conf['show_list'])
			$zz['output'].= '<p class="add-new bottom-add-new"><a accesskey="n" href="'
				.$zz_conf['url_self'].$zz_var['url_append'].'mode=add'.$zz['extraGET'].'">'
				.$text['add_new_record'].'</a></p>';
		// Total records
		if ($zz_lines == 1) $zz['output'].= '<p class="totalrecords">'.$zz_lines.' '.$text['record total'].'</p>'; 
		elseif ($zz_lines) $zz['output'].= '<p class="totalrecords">'.$zz_lines.' '.$text['records total'].'</p>';
		// Limit links
		$zz['output'].= zz_limit($zz_conf['limit'], $zz_conf['this_limit'], $count_rows, $zz['sql'], $zz_lines, 'body');	// NEXT, PREV Links at the end of the page
		// Search form
		$zz['output'] .= $searchform_bottom;
	}
}

function zz_field_sum($table_query, $z, $table, $sum, $zz_conf) {
	$tfoot_line = '';
	foreach ($table_query as $index => $field)
		if ($index != $zz_conf['group_field_no'])
			if ($field['type'] == 'id' && empty($field['show_id']))
				$tfoot_line .= '<td class="recordid">'.$z.'</td>';
			elseif (!empty($field['sum'])) {
				$tfoot_line.= '<td'.check_if_class($field, (!empty($table) ? $table : '')).'>';
				if (isset($field['calculation']) AND $field['calculation'] == 'hours')
					$sum[$field['title']] = hours($sum[$field['title']]);
				if (isset($field['number_type']) && $field['number_type'] == 'currency') 
					$sum[$field['title']] = waehrung($sum[$field['title']], '');

				$tfoot_line.= $sum[$field['title']];
				if (isset($field['unit']) && $sum[$field['title']]) 
					$tfoot_line.= '&nbsp;'.$field['unit'];	
				$tfoot_line.= '</td>';
			} else $tfoot_line.= '<td>&nbsp;</td>';
	return $tfoot_line;
}

?>