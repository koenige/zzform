<?php

/*
	zzform Scripts

	function zz_display_records
		add, edit, delete, review a record
	function zz_show_field_rows
		will be called from zz_display_records
		shows all table rows for given record
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2006

*/


// $zz['conditions'][1]['fields'][5]['value']['<'] = date('Y-m-d');

function check_zz_conditions($conditions, $record) {
	foreach ($conditions as $key => $condition) {
		echo '<pre>';
		print_r($condition);
		print_r($record);
		echo '</pre>';
		
		//		
	}
	// $zz_conditions[1] = true;
}

function conditional_zzconf($zz_conditions, $zz_conf) {
	// write $zz_conf['if_condition'][$key] to $zz_conf if expression is true
	foreach ($zz_conditions as $key => $bool)
		if ($bool)
			if (isset($zz_conf['if_condition'][$key])) {
				$zz_conf = array_merge($zz_conf, $zz_conf['if_condition'][$key]);
				unset($zz_conf['if_condition'][$key]);
			}
	return $zz_conf;	
}

function zz_display_records($zz, $my_tab, $zz_conf, $display, $zz_var) {
	global $text;
	global $zz_error;
	$output = '';
	if ($zz['formhead'] && $zz['mode'] != 'export')
		$output.= "\n<h3>".ucfirst($zz['formhead'])."</h3>\n\n";
	$output.= zz_error($zz_error);

//		echo '<pre align="left" style="text-align: left;">';
//		print_r($my_tab);
//		echo '</pre>';
	// check options
//	$zz_conditions = check_zz_conditions($zz['conditions'], $zz['record']);
//	$zz_conditions[1] = true;	
	// rewrite zz_conf
	$zz_conf_thisrec = (!empty($zz_conditions) 
		? conditional_zzconf($zz_conditions, $zz_conf)
		: $zz_conf);

	if ($display) {
		if (($zz['mode'] == 'add' OR $zz['mode'] == 'edit') && !empty($zz_conf_thisrec['upload_MAX_FILE_SIZE'])) 
			$output.= '<input type="hidden" name="MAX_FILE_SIZE" value="'.$zz_conf_thisrec['upload_MAX_FILE_SIZE'].'">'."\n";
		$output.= '<table>'."\n";

		if ($zz['mode'] && $zz['mode'] != 'review' && $zz['mode'] != 'show') {
			$output.= '<tfoot>'."\n";
			$output.= '<tr><th>&nbsp;</th> <td><input type="submit" value="';
			$accesskey = 's';
			if		($zz['mode'] == 'edit') 	$output.= $text['update_to'].' ';
			elseif	($zz['mode'] == 'delete')	$output.= $text['delete_from'].' ';
			else 								$output.= $text['add_to'].' ';
			if ($zz['mode'] == 'delete') $accesskey = 'd';
			$my_url = parse_url($_SERVER['REQUEST_URI']);
			$cancelurl = $my_url['path'];
			if (isset($my_url['query'])) {
				$queries = explode('&', $my_url['query']);
				$cancelquery = '';
				foreach ($queries as $query) {
					$queryparts = explode('=', $query);
					if ($queryparts[0] != 'mode' AND $queryparts[0] != 'id')
						$cancelquery[] = $query;
				}
				if ($cancelquery) $cancelurl.= '?'.implode('&amp;', $cancelquery);
			}
			$output.= $text['database'].'" accesskey="'.$accesskey.'">';
			if ($cancelurl != $_SERVER['REQUEST_URI'] OR ($zz['action'])) // only show cancel link if it is possible to hide form // todo: expanded to action, not sure if this works on add only forms, this is for re-edit a record in case of missing field values etc.
				$output.= ' <a href="'.$cancelurl.'">'.$text['Cancel'].'</a>';
			$output.= '</td></tr>'."\n";
			$output.= '</tfoot>'."\n";
		} else {
			if ($zz_conf_thisrec['access'] != 'add_only') {
				$output.= '<tfoot>'."\n";
				$output.= '<tr><th>&nbsp;</th> <td class="reedit">';
				if ($zz_conf_thisrec['edit']) {
					$output.= '<a href="'.$zz_conf_thisrec['url_self'].$zz_var['url_append'].'mode=edit&amp;id='.$my_tab[0][0]['id']['value'].$zz['extraGET'].'">'.$text['edit'].'</a>';
					if ($zz_conf_thisrec['delete']) $output.= ' | <a href="'.$zz_conf_thisrec['url_self'].$zz_var['url_append'].'mode=delete&amp;id='.$my_tab[0][0]['id']['value'].$zz['extraGET'].'">'.$text['delete'].'</a>';
				}
				$output.= '</td></tr>'."\n";
				if (isset($zz_conf_thisrec['details'])) {
					$output.= '<tr><th>&nbsp;</th><td class="editbutton">'
						.show_more_actions($zz_conf_thisrec['details'], 
						$zz_conf_thisrec['details_url'], $zz_conf_thisrec['details_base'], 
						$zz_conf_thisrec['details_target'], $zz_conf_thisrec['details_referer'],
						$my_tab[0][0]['id']['value'], 
						(!empty($my_tab[0][0]['POST']) ? $my_tab[0][0]['POST'] : false))
						.'</td></tr>'."\n";
				}
				$output.= '</tfoot>'."\n";
			}
		}
		$output.= zz_show_field_rows($my_tab, 0, 0, $zz['mode'], $display, $zz_var, $zz_conf_thisrec, $zz['action']);
		$output.= '</table>'."\n";
		if ($zz['mode'] == 'delete') $output.= '<input type="hidden" name="'.$my_tab[0][0]['id']['field_name'].'" value="'.$my_tab[0][0]['id']['value'].'">'."\n";
		if ($zz['mode'] && $zz['mode'] != 'review' && $zz['mode'] != 'show') {
			switch ($zz['mode']) {
				case 'add': $submit = 'insert'; break;
				case 'edit': $submit = 'update'; break;
				case 'delete': $submit = 'delete'; break;
			}
			$output.= '<input type="hidden" name="action" value="'.$submit.'">';
			if ($zz_conf_thisrec['referer']) $output.= '<input type="hidden" value="'.$zz_conf_thisrec['referer'].'" name="referer">';
			if (isset($_GET['file']) && $_GET['file']) 
				$output.= '<input type="hidden" value="'.htmlspecialchars($_GET['file']).'" name="file">';
		}
		if ($display == 'form') {
			foreach (array_keys($my_tab) as $tabindex) {
				if ($tabindex && isset($my_tab[$tabindex]['records']))
					$output.= '<input type="hidden" name="records['.$tabindex.']" value="'
					.$my_tab[$tabindex]['records'].'">';
				if (isset($my_tab[$tabindex]['deleted']))
					foreach ($my_tab[$tabindex]['deleted'] as $deleted_id)
						$output.= '<input type="hidden" name="deleted['
						.$my_tab[$tabindex]['table_name'].'][]['
						.$my_tab[$tabindex][0]['id']['field_name'].']" value="'
						.$deleted_id.'">';
				if ($tabindex && !isset($my_tab[$tabindex]['deleted']) 
					&& !isset($my_tab[$tabindex]['records']) && isset($_POST['records'])) 
					// this occurs when a record is not validated. subtable fields 
					// will be validated, so this is not perfect as there are no 
					// more options to enter a record even if not all subrecords were filled in
					$output.= '<input type="hidden" name="records['.$tabindex
					.']" value="'.htmlspecialchars($_POST['records'][$tabindex]).'">';
			}
		}
		if (isset($zz_conf_thisrec['variable']))
			foreach ($zz_conf_thisrec['variable'] as $myvar)
				if (isset($zz['record'][$myvar['field_name']])) $output.= '<input type="hidden" value="'.$zz['record'][$myvar['field_name']].'" name="'.$myvar['f_field_name'].'">';
	}
	if ($output) $output = '<div id="record">'."\n$output</div>\n";
	return $output;
}

function zz_show_field_rows($my_tab, $i, $k, $mode, $display, $zz_var, 
	$zz_conf_thisrec, $action, $formdisplay = 'vertical', $extra_lastcol = false, 
	$table_count = 0, $show_explanation = true) {

	global $text;
	global $zz_error;
	global $zz_conf;	// Config variables
	$output = '';
	$append_next = '';
	$append_next_type = '';
	$matrix = false;
	$my = $my_tab[$i][$k];
	$firstrow = true;
	$debugger = false;
	$table_name = (!empty($my_tab[$i]['table_name']) ? $my_tab[$i]['table_name'] : $my_tab[$i]['table']);
	$row_display = (!empty($my['access']) ? $my['access'] : $display); // this is for 0 0 main record
	foreach ($my['fields'] as $fieldkey => $field) {
		if (!empty($field['hide_in_form'])) continue;

		// initialize variables
		if (!$append_next) {
			$out['tr']['attr'] = false;
			$out['th']['attr'] = false;
			$out['th']['content'] = '';
			$out['th']['show'] = true;
			$out['td']['attr'] = false;
			$out['td']['content'] = '';
			$out['separator'] = '';
		}
		// write values into record, if detail record entry shall be preset
		if (!empty($my_tab[$i]['values'][$table_count][$fieldkey])) {
			$field['value'] = $my_tab[$i]['values'][$table_count][$fieldkey];
			if ($field['type'] == 'select') {
				$field['type_detail'] = $field['type'];
				$field['type'] = 'predefined';
			}
		}
		
		// $i means subtable, since main table has $i = 0
		if ($i) $field['f_field_name'] = $my_tab[$i]['table_name'].'['.$k.']['.$field['field_name'].']';
		elseif (isset($field['field_name'])) $field['f_field_name'] = $field['field_name'];
		if (!empty($field['format'])) { // formatted fields: show that they are being formatted!
			if (!isset($field['title_desc'])) $field['title_desc'] = '';
			$field['title_desc'] .= " [".ucfirst($field['format']).']';
		}
		if ($field['type'] == 'subtable') {
			if (empty($field['form_display'])) $field['form_display'] = 'vertical';
//	Subtable
			$st_display = (!empty($field['access']) ? $field['access'] : $display);
			$out['tr']['attr'][] = (!empty($field['class']) ? $field['class'] : '');
			$out['th']['attr'][] = 'sub-add';
			if ($st_display == 'form' && !isset($my_tab[$field['subtable']]['records']))  // this happens in case $validation is false
				$my_tab[$field['subtable']]['records'] = $_POST['records'][$field['subtable']];
			$out['th']['content'] .= $field['title'];
			if (!empty($field['title_desc']) && $st_display == 'form') 
				$out['th']['content'].= '<p class="desc">'.$field['title_desc'].'</p>';
			$out['td']['attr'][] = 'subtable';
			if ($st_display == 'form' && !empty($field['explanation_top']) && $show_explanation) 
				$out['td']['content'].= '<p class="explanation">'.$field['explanation_top'].'</p>';
			$subtables = array_keys($my_tab[$field['subtable']]);
			foreach (array_keys($subtables) as $index)
				if (!is_numeric($subtables[$index])) unset($subtables[$index]);
			foreach ($subtables as $mytable_no) {
				// show all subtables which are not deleted but 1 record as a minimum
				if ($my_tab[$field['subtable']][$mytable_no]['action'] != 'delete' 
					OR (!empty($my_tab[$field['subtable']]['records']) 
					&& ($mytable_no + 1) == $my_tab[$field['subtable']]['min_records'])) {
					
					$lastrow = false;
					$show_add = false;
					$show_remove = false;

					$dont_delete_records = (!empty($field['dont_delete_records'])
						? $field['dont_delete_records'] : '');
					if (!empty($field['values'][$mytable_no])) {
						$dont_delete_records = true; // dont delete records with values set
						if ($display == 'form') $lastrow = '&nbsp;'; // just for optical reasons, in case one row allows removing of record
					}
					
					if ($st_display == 'form') {
						if ($my_tab[$field['subtable']]['min_records'] < $my_tab[$field['subtable']]['records']
							&& !$dont_delete_records)
							$show_remove = true;
						if ($my_tab[$field['subtable']]['max_records'] > $my_tab[$field['subtable']]['records']
							AND $mytable_no == $my_tab[$field['subtable']]['records']-1)
							$show_add = true;
					}
					if ($show_remove) {
						$removebutton = '<input type="submit" value="'
							.sprintf($text['Remove %s'], $field['title'])
							.'" class="sub-remove" name="subtables[remove]['
							.$field['subtable'].']['.$mytable_no.']">';
						if ($field['form_display'] != 'horizontal') {
							$out['td']['content'] .= $removebutton;
						} else {
							$lastrow = $removebutton;	
						}
					}
					if ($field['form_display'] != 'horizontal' OR !$mytable_no)
						$out['td']['content'].= '<table class="'.$field['form_display'].'">'; // show this for vertical display and for first horizontal record
					if ($field['form_display'] != 'horizontal' OR $mytable_no == count($subtables)-1)
						$h_show_explanation = true;
					else
						$h_show_explanation = false;
					$out['td']['content'].= zz_show_field_rows($my_tab, $field['subtable'], 
						$mytable_no, $mode, $st_display, $zz_var, $zz_conf_thisrec, $action, 
						$field['form_display'], $lastrow, $mytable_no, $h_show_explanation);
					if ($field['form_display'] != 'horizontal' OR $mytable_no == count($subtables)-1)
						$out['td']['content'].= '</table>'."\n";
	
					if ($show_add) $out['td']['content'] .= '<input type="submit" value="'
						.sprintf($text['Add %s'], $field['title'])
						.'" class="sub-add" name="subtables[add]['
						.$field['subtable'].']">';
				}
			}
			if ($st_display == 'form' && $field['explanation'] && $show_explanation)
				$out['td']['content'].= '<p class="explanation">'.$field['explanation'].'</p>';
			if (!empty($field['separator']))
				$out['separator'] = $field['separator'];
		} elseif ($field['type'] == 'foreign_key') {
			continue; // this must not be displayed, for internal link only
		} else {
//	"Normal" field
			if ($field['type'] == 'option') {
				if ($mode != 'edit' AND $mode != 'add') continue; // options will only be shown in edit mode
				$field['type'] = $field['type_detail']; // option as normal field, set to type_detail for display form
				$is_option = true;
			} else $is_option = false;
			if (!isset($field['class'])) $field['class'] = '';
			if ($field['type'] == 'id') {
				if ($field['class']) $field['class'] .= ' ';
				$field['class'] .= 'idrow';
			} else
				if ($firstrow) {
					if ($field['class']) $field['class'] .= ' ';
					$field['class'] .= 'firstrow';
					$firstrow = false;
				}
			if ($i) if ($field['type'] == 'id' OR $field['type'] == 'timestamp') {
				if ($field['class']) $field['class'] .= ' ';
				$field['class'] .= 'hidden';
			}
			if (!$append_next) {
				$out['tr']['attr'][] = ($field['class'] ? $field['class'] : '');
				if (!(isset($field['show_title']) && !$field['show_title'])) {
					if (!empty($field['title_append'])) 
						$out['th']['content'] .= $field['title_append']; // just for form, change title
					else 
						$out['th']['content'].= $field['title'];
					if (!empty($field['title_desc']) && $row_display == 'form') 
						$out['th']['content'].= '<p class="desc">'.$field['title_desc'].'</p>';
				} elseif (!$i) {
					$out['th']['content'] = ''; // for main record, show empty cells
				} else
					$out['th']['show'] = false;
				$close_span = false;
			} else {
				$close_span = true;
				$out['td']['content'].= '<span'.($field['class'] ? ' class="'.$field['class'].'"' : '').'>'; // so error class does not get lost
			}
			if (!empty($field['append_next'])) $append_next = true;
			else $append_next = false;
			if (!isset($field['size']))
				if ($field['type'] == 'number') $field['size'] = 16;
		 		else $field['size'] = 32;
		 	if ($field['type'] == 'ipv4') {
		 		$field['size'] = 16;
		 		$field['maxlength'] = 16;
			} elseif ($field['type'] == 'time') $field['size'] = 8;
			if ($field['maxlength'] && $field['maxlength'] < $field['size']) 
				$field['size'] = $field['maxlength'];
			// apply factor only if there is a value in field
			// don't apply it if it's a re-edit
			if ($my['record'] && isset($field['factor']) && $my['record'][$field['field_name']]) {
				if (!is_array($my['record'][$field['field_name']]) 
					&& ($my_tab[0][0]['action'] != 'review')) { //  OR )
					
					// for incorrect values; !action means only divide once
					// for review, e. g. if record has been updated, division has to be done to show the correct value	
					$my['record'][$field['field_name']] /=$field['factor'];
				}
			}
			if (isset($field['auto_value'])) {
				if ($field['auto_value'] == 'increment') {
					/*	added 2004-12-06
						maybe easier and faster without sql query - instead rely on table query
					*/
					$sql_max = $my_tab[$i]['sql'];
					if ($i) { // it's a subtable
						$sql_max_where = '';
						if (!empty($my_tab[0][0]['id']['field_name']) && !empty($my_tab[0][0]['id']['value']))
							$sql_max_where = $my_tab[0]['table'].'.'.$my_tab[0][0]['id']['field_name'].' = '.$my_tab[0][0]['id']['value'];
						$field['default'] = $k + 1;
					}
					if (stristr($sql_max, 'ORDER BY')) {
						preg_match('/(.*) ORDER BY.*/i', $sql_max, $sql_result);
						$sql_max = $sql_result[1];
					}
					if ($i && $sql_max_where) 
						$sql_max = zz_edit_sql($sql_max, 'WHERE', '('.$sql_max_where.')');
					$sql_max = zz_edit_sql($sql_max, 'ORDER BY', $field['field_name'].' DESC');
					$myresult = mysql_query($sql_max);
					if (!$i OR $sql_max_where) // query only if maintable or saved record
						if ($myresult)
							if (mysql_num_rows($myresult)) {
								$field['default'] = mysql_result($myresult, 0, $field['field_name']);
								$field['default']++;
							} else $field['default'] = 1;
				}
			}
			if (isset($zz_var['where'][$table_name][$field['field_name']])) {
				if ($field['type'] == 'select') $field['type_detail'] = 'select';
				else $field['type_detail'] = false;
				$field['type'] = 'predefined';
			} elseif (isset($values) && is_array($values) && isset($values[$field['field_name']]))
				$field['default'] = $values[$field['field_name']];
			if (!empty($field['default']) AND empty($field['value']))
				// look at default only if no value is set - value overrides default
//				if (!$my['record'] OR !empty($is_option)) { // set default only if record is empty OR if it's an option field which is always empty
				if (($mode == 'add' && !$my['record']) OR !empty($is_option)
					OR !$my['record'] && !empty($field['def_val_ignore'])) { // set default only if record is empty OR if it's an option field which is always empty OR if default value is set to be ignored in case of no further additions
					$my['record'][$field['field_name']] = $field['default'];
					$default_value = true; // must be unset later on because of this value
				}
			//
			// output all records
			//
			
			if ($row_display == 'form' && !empty($field['explanation_top']))
				$out['td']['content'].= '<p class="explanation">'.$field['explanation_top'].'</p>';
			if ($field['type'] == 'write_once' AND empty($my['record'][$field['field_name']])) {
				$field['type'] = $field['type_detail'];
			}

			$outputf = false;
			switch ($field['type']) {
				case 'id':
					if ($my['id']['value']) $outputf.= '<input type="hidden" value="'.$my['id']['value'].'" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">'.$my['id']['value'];
					else $outputf.= '('.$text['will_be_added_automatically'].')&nbsp;';
					break;
				case 'identifier':
				case 'hidden':
					$outputf.= '<input type="hidden" value="';
					if (!empty($field['value'])) $outputf.= $field['value'];
					elseif ($my['record']) $outputf.= $my['record'][$field['field_name']];
					$outputf.= '" name="'.$field['f_field_name'].'" id="'
						.make_id_fieldname($field['f_field_name']).'">';
					if ($my['record']) {
						if (!empty($field['type_detail']) && $field['type_detail'] == 'ipv4')
							$outputf.= long2ip($my['record'][$field['field_name']]);
						elseif (isset($field['timestamp']) && $field['timestamp'])
							$outputf.= timestamp2date($my['record'][$field['field_name']]);
						elseif (isset($field['display_field'])) {
							if (!empty($my['record'][$field['display_field']]))
								$outputf.= htmlspecialchars($my['record'][$field['display_field']]);
							elseif (!empty($my['record_saved'][$field['display_field']]))
								$outputf.= htmlspecialchars($my['record_saved'][$field['display_field']]);
							else {
								if (empty($field['append_next']))
									if (!empty($field['value'])) $outputf.= $field['value'];
									else $outputf.= '('.$text['will_be_added_automatically'].')';
							}
						} else {
							if (!empty($my['record'][$field['field_name']]))
								$outputf.= htmlspecialchars($my['record'][$field['field_name']]);
							elseif (!empty($my['record_saved'][$field['field_name']]))
								$outputf.= htmlspecialchars($my['record_saved'][$field['field_name']]);
							else {
								if (empty($field['append_next']))
									if (!empty($field['value'])) $outputf.= $field['value'];
									else $outputf.= '('.$text['will_be_added_automatically'].')';
							}
						}
					} else
						if (!empty($field['value'])) {
							if (!empty($field['type_detail']) && $field['type_detail'] == 'ipv4')
								$outputf.= long2ip($field['value']);
							else
								$outputf.= $field['value'];
						} else $outputf.= '('.$text['will_be_added_automatically'].')&nbsp;';
					break;
				case 'timestamp':
					$outputf.= '<input type="hidden" value="';
					if (!empty($field['value'])) $outputf.= $field['value'];
					elseif ($my['record']) $outputf.= $my['record'][$field['field_name']];
					$outputf.= '" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
					if ($my['record'] && !empty($my['record'][$field['field_name']]))
						$outputf.= timestamp2date($my['record'][$field['field_name']]);
					else
						$outputf.= '('.$text['will_be_added_automatically'].')&nbsp;';
					break;
				case 'unix_timestamp':
					if (isset($field['value'])) {
						if ($row_display == 'form') $outputf.= '<input type="hidden" value="';
						$outputf.= $field['value'];
					} else {
						if ($row_display == 'form') $outputf.= '<input type="text" value="';
						if ($my['record']) {
							$timestamp = strtotime($my['record'][$field['field_name']]);
							if ($timestamp != -1)
								$my['record'][$field['field_name']] = $timestamp;
							$outputf.= date('Y-m-d H:i:s', $my['record'][$field['field_name']]);
						}
					}
					if ($row_display == 'form') $outputf.= '" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
					if (!$my['record'] AND isset($field['value'])) $outputf.= '('.$text['will_be_added_automatically'].')&nbsp;';
					break;
				case 'foreign':
					$foreign_res = mysql_query($field['sql'].$my['id']['value']);
					//$outputf.= $field['sql'].$my['id']['value'];
					if ($foreign_res) {
						if (mysql_num_rows($foreign_res) > 0) {
							$my_output = false;
							while ($fline = mysql_fetch_row($foreign_res)) {
								if ($my_output) $outputf.= ', ';
								$my_output.= $fline[0]; // All Data in one Line! via SQL
							}
							if ($my_output) $outputf.= $my_output;
							else $outputf.= $text['no-data-available'];
						} else {
							$outputf.= $text['no-data-available'];
						}
					} 
					if (isset($field['add_foreign'])) {
						if ($my['id']['value'])
							$outputf.= ' <a href="'.$field['add_foreign'].$my['id']['value'].'&amp;referer='.urlencode($_SERVER['REQUEST_URI']).'">['.$text['edit'].' &hellip;]</a>';
						else
							$outputf.= $text['edit-after-save'];
					}
					break;
				case 'predefined':
					$detail_key = (!empty($zz_var['where'][$table_name][$field['field_name']])
						? $zz_var['where'][$table_name][$field['field_name']] : $field['value']);
					$outputf.= '<input type="hidden" name="'
						.$field['f_field_name'].'" id="'
						.make_id_fieldname($field['f_field_name']).'" value="'
						.$detail_key.'">';
					if ($field['type_detail'] == 'select') {
						$my_fieldname = $field['field_name'];
						if (isset($field['key_field_name'])) $my_fieldname = $field['key_field_name'];
						if (isset($field['sql'])) {
							$mysql = zz_edit_sql($field['sql'], 'WHERE', '('.$my_fieldname.' = '.$detail_key.')');
							$result_detail = mysql_query($mysql);
							if ($result_detail) {
								if (mysql_num_rows($result_detail) == 1) {
									$select_fields = mysql_fetch_row($result_detail);
									if (count($select_fields) > 2)
										unset($select_fields[0]); // remove ID for display
									$outputf .= implode(' | ', $select_fields);
								}
							} else {
								if ($zz_conf['debug_allsql']) echo "<div>a cool piece of query ... but buggy!:<br /><pre>$mysql</pre></div>";
								$outputf.= zz_error($zz_error[] = array('mysql' => mysql_error(), 'query' => $mysql, 'msg' => $text['error-sql-incorrect']));
							}
						} elseif (isset($field['enum'])) {
							$outputf.= $zz_var['where'][$table_name][$field['field_name']];
						}
					} else
						$outputf.= $zz_var['where'][$table_name][$field['field_name']];
					break;
				case 'password':
					if ($row_display == 'form') {
						$outputf.= '<input type="password" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'" size="'.$field['size'].'" ';
						if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
					}
					if ($my['record'])
						if ($row_display == 'form') $outputf.= 'value="'.$my['record'][$field['field_name']].'"';
						else $outputf .= '('.$text['hidden'].')';
					if ($row_display == 'form') $outputf.= '>';
					if ($my['record'] && $row_display == 'form' && $action != 'insert') { $outputf .=
						'<input type="hidden" name="'.$field['f_field_name'].
						'--old" value="'.(!empty($my['record'][$field['field_name'].'--old']) ? $my['record'][$field['field_name'].'--old'] : $my['record'][$field['field_name']]).'">';
						// this is for validation purposes
						// take saved password (no matter if it's interefered with maliciously by user - worst case, pwd will be useless)
						// - if old and new value are identical
						// do not apply md5 encoding to password
					}
					break;
				case 'password_change':
					if ($row_display == 'form') {
						$outputf.= '<table class="subtable">'."\n";
						$outputf.= '<tr><th><label for="'.make_id_fieldname($field['f_field_name']).'">'.$text['Old:'].' </label></th><td><input type="password" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'" size="'.$field['size'].'" ';
						if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
						$outputf.= '></td></tr>'."\n";
						$outputf.= '<tr><th><label for="'.make_id_fieldname($field['f_field_name'].'_new_1').'">'.$text['New:'].' </label></th><td><input type="password" name="'.$field['f_field_name'].'_new_1" id="'.make_id_fieldname($field['f_field_name'].'_new_1').'" size="'.$field['size'].'" ';
						if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
						$outputf.= '></td></tr>'."\n";
						$outputf.= '<tr><th><label for="'.make_id_fieldname($field['f_field_name'].'_new_2').'">'.$text['New:'].' </label></th><td><input type="password" name="'.$field['f_field_name'].'_new_2" id="'.make_id_fieldname($field['f_field_name'].'_new_2').'" size="'.$field['size'].'" ';
						if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
						$outputf.= '><p>'.$text['(Please confirm your new password twice)'].'</td></tr>'."\n";
						$outputf.= '</table>'."\n";
					} else {
						$outputf.= '********';
					}
					break;
				case 'text':
				case 'url':
				case 'time':
				case 'enum':
				case 'mail':
				case 'datetime':
				case 'ipv4':
				if ($row_display == 'form') {
						$outputf.= '<input type="text" name="'.$field['f_field_name']
							.'" id="'.make_id_fieldname($field['f_field_name']).'" size="'.$field['size'].'" ';
						if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
					}
					if ($my['record']) {
						if ($row_display == 'form') $outputf.= 'value="';
						elseif ($field['type'] == 'url' && !empty($my['record'][$field['field_name']])) 
							$outputf.= '<a href="'.htmlspecialchars($my['record'][$field['field_name']]).'">';
						elseif ($field['type'] == 'mail' && !empty($my['record'][$field['field_name']]))
							$outputf.= '<a href="mailto:'.$my['record'][$field['field_name']].'">';
						if ($field['type'] == 'url' AND strlen($my['record'][$field['field_name']]) > $zz_conf_thisrec['max_select_val_len'] AND $row_display != 'form')
							$outputf.= htmlspecialchars(substr($my['record'][$field['field_name']], 0, $zz_conf_thisrec['max_select_val_len'])).'...';
						elseif ($field['type'] == 'ipv4')
							$outputf.= long2ip($my['record'][$field['field_name']]);
						else
							$outputf.= htmlspecialchars($my['record'][$field['field_name']]);
						if (($field['type'] == 'url' OR $field['type'] == 'mail')
							&& !empty($my['record'][$field['field_name']]) && $row_display != 'form') $outputf.= '</a>';
						if ($row_display == 'form') $outputf.= '"';
					} elseif ($mode == 'add' AND $field['type'] == 'datetime')
						$outputf.= 'value="'.date('Y-m-d H:i:s', time()).'"';
					if ($row_display == 'form') $outputf.= '>';
					break;
				case 'number':
					if (isset($field['number_type']) AND $field['number_type'] == 'latitude' || $field['number_type'] == 'longitude') {
						$var = false;
						if ($my['record']) {
							if (!is_array($my['record'][$field['field_name']])) {
							// only if values come directly from db, not if values entered are incorrect
								if ($field['number_type'] == 'latitude') $var = dec2dms($my['record'][$field['field_name']], '');
								elseif ($field['number_type'] == 'longitude') $var = dec2dms('', $my['record'][$field['field_name']]);
							} else
								$var = $my['record'][$field['field_name']];
						}
						//	DMS, DM
						$input_systems = array('dms' => "&deg; ' ''&nbsp; ", 'dm' => "&deg; '&nbsp; ");
						if (!empty($my['record'][$field['field_name']]['which']))
							$w_checked = $my['record'][$field['field_name']]['which'];
						else $w_checked = 'dms';
						$checked = ' checked="checked"';
						foreach ($input_systems as $which => $which_display) {
							if ($row_display == 'form') {
								$myid = make_id_fieldname($field['field_name'].'_'.$which, 'radio');
								if ($which == 'dms') $outputf.= '<span class="edit-coord-degree">'; // for hiding both degree input fields
								$outputf.= '<label for="'.$myid.'"><input type="radio" id="'.$myid.'" name="'
									.$field['f_field_name'].'[which]"  value="'.$which.'"'.($w_checked == $which ? $checked: '').'>'." ".$which_display."</label>";
								if (!isset($field['wrong_fields'][$which])) $field['wrong_fields'][$which] = '';
								$outputf.= geo_editform($field['f_field_name'].'['.substr($field['number_type'],0,3), $var, $which, $field['wrong_fields'][$which]);
								$outputf.= ' <br> ';
							} elseif ($var) {
								$outputf.= $var[$field['number_type'].'_'.$which];
								$outputf.= ' || ';
							} else
								if ($which == 'dms') $outputf.= $text['N/A']; // display it only once!
						}
						//	DD
						if ($row_display == 'form') {
							$myid = make_id_fieldname($field['field_name'].'_dec', 'radio');
							$outputf.= '<label for="'.$myid.'"><input type="radio" id="'.$myid.'" name="'.$field['f_field_name'].'[which]" value="dec" '.($w_checked == 'dec' ? $checked: '').'> '.$text['dec'].'&nbsp; </label></span>';
							$outputf.= '<input type="text" name="'.$field['f_field_name'].'[dec]" id="'.make_id_fieldname($field['f_field_name']).'_dec" size="12" ';
						} 
						if ($my['record']) {
							if ($row_display == 'form') $outputf.= 'value="';
							if(!is_array($my['record'][$field['field_name']])) 
								$outputf.= $my['record'][$field['field_name']];
							else // this would happen if record is not validated
								$outputf.= $my['record'][$field['field_name']]['dec'];
							if ($row_display == 'form') $outputf.= '"';
						}
						if ($row_display == 'form') $outputf.= '>';
					
					
					} else {
						if ($row_display == 'form') {
							$outputf.= '<input type="text" ';
							$outputf.=  'name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'" size="'.$field['size'].'" ';
						}
						if ($my['record']) {
							if ($row_display == 'form') $outputf.= 'value="';
							$outputf.= htmlchars($my['record'][$field['field_name']]);
							if ($row_display == 'form') $outputf.= '"';
						}
						if ($row_display == 'form') $outputf.= '>';
					}
					break;
				case 'date':
					if ($row_display == 'form') $outputf.= '<input type="text" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'" size="12" ';
					if ($my['record']) {
						if ($row_display == 'form') $outputf.= 'value="';
						$outputf.= datum_de($my['record'][$field['field_name']]);
						if ($row_display == 'form') $outputf.= '"';
					} 
					if ($row_display == 'form') $outputf.= '>';
					break;
				case 'memo':
					if (!isset($field['rows'])) $field['rows'] = 8;
					if ($row_display == 'form') $outputf.= '<textarea rows="'
						.$field['rows'].'" cols="'.(!empty($field['cols']) ? $field['cols'] : '60').'" name="'
						.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'"';
					if ($row_display == 'form') $outputf.= '>';
					if ($my['record']) {
//						$memotext = stripslashes($my['record'][$field['field_name']]);
						$memotext = $my['record'][$field['field_name']];
						$memotext = htmlspecialchars($memotext);
						if ($row_display != 'form' && isset($field['format'])) $memotext = $field['format']($memotext);
						$outputf.= $memotext;
					}
					if ($row_display == 'form') $outputf.= '</textarea>';
					break;
				//case 'enum':
				//	$outputf.= mysql_field_flags($field['field_name']);
				//	break;
				case 'select':
					if (!empty($field['sql_without_id'])) $field['sql'] .= $my['id']['value'];
					if (!empty($field['sql'])) {
						if (!empty($field['sql_where']) && !empty($zz_var['where'][$table_name])) { // add WHERE to sql clause if necessary
							$my_where = '';
							$add_details_where = ''; // for add_details
							foreach ($field['sql_where'] as $sql_where) {
								// might be several where-clauses
								if (isset($sql_where[2])) {
									foreach (array_keys($zz_var['where'][$table_name]) as $value_key)
										if ($value_key == $sql_where[1]) $sql_where[2].= $zz_var['where'][$table_name][$value_key];
									$result_detail = mysql_query($sql_where[2]);
									if ($result_detail) {
										//if (mysql_num_rows($result_detail) == 1)
										// might be that there are more results, so that should not be a problem
											$index = mysql_result($result_detail,0,0);
										//else $outputf.= $sql_where[2];
									} else {
										if ($zz_conf['debug_allsql']) echo "<div>damned...there was an error in this query:<br /><pre>".$sql_where[2]."</pre></div>";
										$outputf.= zz_error($zz_error[] = array('mysql' => mysql_error(), 'query' => $sql_where[2], 
											'msg' => $text['error-sql-incorrect']));
									}
								}
								$my_where[] = $sql_where[0]." = '".$index."'";
								$add_details_where .= '&amp;where['.$sql_where[0].']='.$index;
							}
							$field['sql'] = zz_edit_sql($field['sql'], 'WHERE', implode(' AND ', $my_where));
						}
						$result_detail = mysql_query($field['sql']);
						if (!$result_detail){
							if ($zz_conf['debug_allsql']) echo "<div>Errors are bad... :<br /><pre>".$field['sql']."</pre></div>";
							$outputf.= zz_error($zz_error[] = array('mysql' => mysql_error(), 'query' => $field['sql'], 'msg' => $text['error-sql-incorrect']));
						}
						elseif ($row_display == 'form' && mysql_num_rows($result_detail) == 1 && !checkfornull($field['field_name'], $my_tab[$i]['table'])) {
							// there is only one result in the array, and this will be pre-selected because FIELD must not be NULL
							$line = mysql_fetch_array($result_detail); // need both numeric and assoc keys
							if ($my['record'] && $line[0] != $my['record'][$field['field_name']]) $outputf .= 'Possible Values: '.$line[0].' -- Current Value: '.htmlspecialchars($my['record'][$field['field_name']]).' -- Error --<br>'.$text['no_selection_possible'];
							else {
								$outputf.= '<input type="hidden" value="'.$line[0].'" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
								$outputf.= draw_select($line, $my['record'], $field, false, 0, false, false, $zz_conf_thisrec);
							}
						} elseif ($row_display == 'form' && mysql_num_rows($result_detail) > $zz_conf_thisrec['max_select']) {
							$textinput = true;
							if ($my['record'])
								while ($line = mysql_fetch_array($result_detail, MYSQL_BOTH))
									if ($line[0] == $my['record'][$field['field_name']]) {
										$outputf.= draw_select($line, $my['record'], $field, false, 0, false, 'reselect', $zz_conf_thisrec);
										$textinput = false;
									}
							if (!empty($my['record'][$field['field_name']])) $value = htmlspecialchars($my['record'][$field['field_name']]); // value will not be checked if one detail record is added because in this case validation procedure will be skipped!
							else $value = '';
							if ($textinput) // add new record
								$outputf.= '<input type="text" size="32" value="'.$value.'" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
							$outputf.= '<input type="hidden" value="'.$field['f_field_name'].'" name="check_select[]">';
						} elseif (mysql_num_rows($result_detail) > 0) {
							if ($row_display == 'form') {
								$outputf.= '<select name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">'."\n";
								$outputf.= '<option value=""';
								if ($my['record']) if (!$my['record'][$field['field_name']]) $outputf.= ' selected';
								$outputf.= '>'.$text['none_selected'].'</option>';
								$my_select = false;
								$my_h_field = false;
								while ($line = mysql_fetch_array($result_detail, MYSQL_BOTH))
									if (!empty($field['show_hierarchy'])) {
										if (!empty($line[$field['show_hierarchy']]))
											$my_h_field = $line[$field['show_hierarchy']];
										else
											$my_h_field = 'NULL'; // this ist the case for uppermost level
										$my_select[$my_h_field][] = $line;
									} else
										$outputf.= draw_select($line, $my['record'], $field, false, 0, false, 'form', $zz_conf_thisrec);
								$show_hierarchy_subtree = (!empty($field['show_hierarchy_subtree']) 
									? $field['show_hierarchy_subtree'] : "NULL");
								if (!empty($field['show_hierarchy']) && $my_select[$show_hierarchy_subtree])
									foreach ($my_select[$show_hierarchy_subtree] AS $my_field)
										$outputf.= draw_select($my_field, $my['record'], $field, $my_select, 0, $field['show_hierarchy'], 'form', $zz_conf_thisrec);
								elseif (!empty($field['show_hierarchy'])) {
									$zz_error[]['msg'] = 'Configuration error: "show_hierarchy" used but there is no highest level in the hierarchy.';
								}
								$outputf.= '</select>'."\n";
								if (!empty($zz_error)) $outputf.= zz_error($zz_error);
							} else 
								while ($line = mysql_fetch_array($result_detail, MYSQL_BOTH))
									if ($line[0] == $my['record'][$field['field_name']])
										$outputf.= draw_select($line, $my['record'], $field, false, 0, false, false, $zz_conf_thisrec);
						} else {
							$outputf.= '<input type="hidden" value="" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
							$outputf.= $text['no_selection_possible'];
						}
					} elseif (isset($field['set'])) {
						$myvalue = '';
						$sets = count($field['set']);
						$myi=0;
						foreach ($field['set'] as $set) {
							$myi++;
							$myid = 'check-'.$field['field_name'].'-'.$myi;
							if ($row_display == 'form') {
								$outputf.= ' <label for="'.$myid.'"><input type="checkbox" id="'.$myid.'" name="'.$field['f_field_name'].'[]" value="'.$set.'"';
								if ($my['record']) {
									if (isset($my['record'][$field['field_name']]))
										if (!is_array($my['record'][$field['field_name']])) 
										//	won't be array normally
											$set_array = explode(',', $my['record'][$field['field_name']]);
										else
										//	just if a field did not pass validation, set fields become arrays
											$set_array = $my['record'][$field['field_name']];
										if (!empty($set_array) && is_array($set_array)) if (in_array($set, $set_array)) $outputf.= ' checked';
								} 
								$outputf.= '> '.$set.'</label>';
								if (count($field['set']) >=4) $outputf.= '<br>';
							} else {
								if (in_array($set, explode(',', $my['record'][$field['field_name']]))) {
									if ($myvalue) $myvalue .= ' | ';
									$myvalue.= $set;
								}
							}
						}
						$outputf.=$myvalue;
					} elseif (isset($field['enum'])) {
						$myi = 0;
						$sel_option = (count($field['enum']) <=2 ? true : (!empty($field['show_values_as_list']) ? true : false));
						if ($row_display == 'form') {
							if ($sel_option) {
								if (!isset($field['hide_novalue'])) $field['hide_novalue'] = true;
								$myid = 'radio-'.$field['field_name'].'-'.$myi;
								$outputf.= '<label for="'.$myid.'"'
									.($field['hide_novalue'] ? ' class="hidden"' : '')
									.'><input type="radio" id="'.$myid.'" name="'
									.$field['f_field_name'].'" value=""';
								if ($my['record']) { if (!$my['record'][$field['field_name']]) $outputf.= ' checked'; }
								else $outputf.= ' checked'; // no value, no default value (both would be written in my record fieldname)
								$outputf.= '>'.$text['no_selection'].'</label>';
								if (!empty($field['show_values_as_list'])) $outputf .= "\n".'<ul class="zz_radio_list">'."\n";
							} else {
								$outputf.= '<select name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">'."\n";
								$outputf.= '<option value=""';
								if ($my['record']) { if (!$my['record'][$field['field_name']]) $outputf.= ' selected'; }
								else $outputf.= ' selected'; // no value, no default value (both would be written in my record fieldname)
								$outputf.= '>'.$text['none_selected'].'</option>';
							} 
						}
						foreach ($field['enum'] as $key => $set) {
							if ($row_display == 'form') {
								if ($sel_option) {
									$myi++;
									$myid = 'radio-'.$field['field_name'].'-'.$myi;
									if (!empty($field['show_values_as_list'])) $outputf .= '<li>';
									$outputf.= ' <label for="'.$myid.'"><input type="radio" id="'.$myid.'" name="'.$field['f_field_name'].'" value="'.$set.'"';
									if ($my['record']) if ($set == $my['record'][$field['field_name']]) $outputf.= ' checked';
									$outputf.= '> '.(!empty($field['enum_title'][$key]) ? $field['enum_title'][$key] : $set).'</label>';
									if (!empty($field['show_values_as_list'])) $outputf .= '</li>'."\n";
								} else {
									$outputf.= '<option value="'.$set.'"';
									if ($my['record']) if ($set == $my['record'][$field['field_name']]) $outputf.= ' selected';
									$outputf.= '>';
									$outputf.= (!empty($field['enum_title'][$key]) ? $field['enum_title'][$key] : $set);
									$outputf.= '</option>';
								}
							} else {
								if ($set == $my['record'][$field['field_name']]) $outputf.= (!empty($field['enum_title'][$key]) ? $field['enum_title'][$key] : $set);
							}
						}
						if (!empty($field['show_values_as_list'])) {
							if (empty($field['append_next']) && $row_display == 'form')
								$outputf .= '</ul>'."\n";
							else $append_next_type = 'list';
						}
						if ($row_display == 'form' && !$sel_option) $outputf.= '</select>'."\n";
					} else {
						$outputf.= $text['no_source_defined'].'. '.$text['no_selection_possible'];
					}
					break;
				case 'image':
				case 'upload_image':
					if (($mode != 'add' OR $field['type'] != 'upload_image')
						AND (empty($field['dont_show_image'])) || !$field['dont_show_image']) {
						$img = false;
						$outputf.= '<p>';
						if (isset($field['path']))
							$outputf .= $img = show_image($field['path'], $my['record']);
						if (!$img) $outputf.= '('.$text['image_not_display'].')';
						$outputf.= '</p>';
					}
					if (($mode == 'add' OR $mode == 'edit') && $field['type'] == 'upload_image') {
						if (!isset($field['image'])) {
							$outputf.= zz_error($zz_error[] = array('msg' => 'Configuration error. Missing upload_image details.'));
						} else {
							$image_uploads = 0;
							foreach ($field['image'] as $imagekey => $image)
								if (!isset($image['source'])) $image_uploads++;
							if ($image_uploads > 1) $outputf.= '<table class="upload">';
							foreach ($field['image'] as $imagekey => $image) {
								if (!isset($image['source'])) {
									// todo: if only one image, table is unneccessary
									// title and field_name of image might be empty
									if ($image_uploads > 1) $outputf.= '<tr><th>'.$image['title'].'</th> <td>';
									$outputf .= '<input type="file" name="'.$field['field_name'].'['.$image['field_name'].']">';
									if ($link = show_link($image['path'], (isset($my['record_saved']) ? $my['record_saved'] : $my['record'])))
										$outputf .= '<br><a href="'.$link.'">'.$link
											.'</a>'
											.($image_uploads > 1 ?
											'(<small><label for="delete-file-'.$fieldkey.'-'.$imagekey
											.'"><input type="checkbox" name="zz_delete_file['.$fieldkey.'-'.$imagekey
											.']" id="delete-file-'.$fieldkey.'-'.$imagekey.'"> '
											.$text['Delete this file'].'</label></small>)'
											: '');
									if (!empty($my['images'][$fieldkey][$imagekey]['error']))
										$outputf.= '<br><small>'.implode('<br>', $my['images'][$fieldkey][$imagekey]['error']).'</small>';
									if ($row_display == 'form' && !empty($image['explanation'])) 
										$outputf.= '<p class="explanation">'.$image['explanation'].'</p>';
									if ($image_uploads > 1) $outputf.= '</td></tr>'."\n";
								}
							}
							if ($image_uploads > 1) $outputf.= '</table>'."\n";
						}
					} else if (isset($field['image'])) {
						$image_uploads = 0;
						foreach ($field['image'] as $imagekey => $image)
							if (!isset($image['source'])) $image_uploads++;
						if ($image_uploads > 1) {
							$outputf.= '<table class="upload">';
							foreach ($field['image'] as $imagekey => $image)
								if (!isset($image['source']))
									if ($link = show_link($image['path'], $my['record'])) {
										$outputf.= '<tr><th>'.$image['title'].'</th> <td>';
										$outputf .= '<a href="'.$link.'">'.$link.'</a>';
										$outputf.= '</td></tr>'."\n";
									}
							$outputf.= '</table>'."\n";
						}
					}
					break;
				case 'write_once':
				case 'display':
					if (isset($field['display_value']))
						$outputf .= $field['display_value']; // internationalization has to be done in zz-fields-definition
					elseif ($my['record']) {
						if (isset($field['display_field'])) {
							if (!empty($my['record'][$field['display_field']]))
								$outputf .= $my['record'][$field['display_field']];
							elseif (!empty($my['record_saved'][$field['display_field']])) // empty for new record
								$outputf .= $my['record_saved'][$field['display_field']]; // requery
						} elseif (isset($field['field_name'])) {
							$tempval_to_insert = false;
							if (!empty($my['record'][$field['field_name']]))
								$tempval_to_insert = $my['record'][$field['field_name']];
							elseif (!empty($my['record_saved'][$field['field_name']])) // empty if new record!
								$tempval_to_insert = $my['record_saved'][$field['field_name']];
							if (!empty($field['display_title']) && in_array($tempval_to_insert, array_keys($field['display_title'])))
								$tempval_to_insert = $field['display_title'][$tempval_to_insert];
							$outputf .= htmlspecialchars($tempval_to_insert);
						} else
							$outputf .= '<span class="error">'.$text['Script configuration error. No display field set.'].'</span>'; // debug!
					} else $outputf .= $text['N/A'];
					break;
				case 'calculated':
					if (!$mode OR $mode == 'show') {
						// identischer Code mit weiter unten, nur statt $line $my['record']!!
						if ($field['calculation'] == 'hours') {
							$diff = 0;
							foreach ($field['calculation_fields'] as $calc_field)
								if (!$diff) $diff = strtotime($my['record'][$calc_field]);
								else $diff -= strtotime($my['record'][$calc_field]);
							if ($diff < 0) $outputf .= '<em class="negative">';
							$outputf.= gmdate('H:i', $diff);
							if ($diff < 0) $outputf .= '</em>';
						} elseif ($field['calculation'] == 'sum') {
							$sum = 0;
							foreach ($field['calculation_fields'] as $calc_field)
								$sum += $my['record'][$calc_field];
							$outputf.= $sum;
						}
					} else $outputf.= '('.$text['calculated_field'].')';
					break;
			}
			if (!empty($field['unit'])) {
				//if ($my['record']) { 
				//	if ($my['record'][$field['field_name']]) // display unit if record not null
				//		$outputf.= ' '.$field['unit']; 
				//} else {
					$outputf.= ' '.$field['unit']; 
				//}
			}
			if (!empty($default_value)) // unset $my['record'] so following fields are empty
				unset($my['record'][$field['field_name']]); 
			$outputf.= ' ';
			if (!isset($add_details_where)) $add_details_where = false;
			if ($mode && $mode != 'delete' && $mode != 'show'  && $mode != 'review')
				if (isset($field['add_details'])) {
					$add_details_sep = (strstr($field['add_details'], '?') ? '&amp;' : '?');
					$outputf.= ' <a href="'.$field['add_details'].$add_details_sep
						.'mode=add&amp;referer='.urlencode($_SERVER['REQUEST_URI'])
						.$add_details_where.'"'
						.(!empty($field['add_details_target']) ? ' target="'.$field['add_details_target'].'"' : '')
						.' id="zz_add_details_'.$i.'_'.$k.'_'.$fieldkey.'">['.$text['new'].' &hellip;]</a>';
				}
			if ($outputf && $outputf != ' ') {
				if (isset($field['prefix'])) $out['td']['content'].= ' '.$field['prefix'].' ';
				$out['td']['content'].= $outputf;
				if (isset($field['suffix'])) $out['td']['content'].= ' '.$field['suffix'].' ';
				if ($row_display == 'form') if (isset($field['suffix_function'])) {
					$vars = '';
					if (isset($field['suffix_function_var']))
						foreach ($field['suffix_function_var'] as $var)
							$vars .= $var; // todo: does this really make sense? looks more like $vars[] = $var. maybe use implode.
					$out['td']['content'].= $field['suffix_function']($vars);
				}
			} else
				$out['td']['content'].= $outputf;
			if (!empty($close_span)) $out['td']['content'].= '</span>';
			if ($append_next_type == 'list' && $row_display == 'form') {
				$out['td']['content'] .= '<li>';
				$append_next_type = 'list_end';
			} elseif ($append_next_type == 'list_end' && $row_display == 'form') {
				$out['td']['content'] .= '</li>'."\n".'</ul>'."\n";
				$append_next_type = false;
			}
			if (!$append_next) {
				if ($row_display == 'form' && $field['explanation'] && $show_explanation) 
					$out['td']['content'].= '<p class="explanation">'.$field['explanation'].'</p>';
//				$output.= '</td></tr>'."\n";
			}
			if (!empty($field['separator']))
				$out['separator'].= $field['separator'];
		}
		if (!$append_next) $matrix[] = $out;
	}
	$output = false;
	if ($formdisplay == 'vertical') {
		foreach ($matrix as $row) {
			$output .= '<tr'.zz_show_class($row['tr']['attr']).'>';
			if ($row['th']['show']) {
				$output .= '<th'.zz_show_class($row['th']['attr']).'>'
					.$row['th']['content'].'</th>'."\n";
			}
			$output .=	"\t".'<td'.zz_show_class($row['td']['attr']).'>'
				.$row['td']['content'].'</td></tr>'."\n";
			if ($row['separator']) {
				$output .= zz_show_separator($row['separator']);
				
			}
		}
	} elseif ($formdisplay == 'horizontal') {
		if (!$table_count) { // just first detail record: show head
			$output .= '<tr>'."\n";
			foreach ($matrix as $row) { 
				$output .= '<th'.zz_show_class(array_merge($row['th']['attr'], $row['tr']['attr']))
					.'>'.$row['th']['content'].'</th>'."\n";
			}
			if ($extra_lastcol) $output .= '<th>&nbsp;</th>';
			$output .= '</tr>'."\n";
		}
		$output .= '<tr>';
		foreach ($matrix as $row) {
			$output .= '<td'.zz_show_class(array_merge($row['td']['attr'], $row['tr']['attr']))
				.'>'.$row['td']['content'].'</td>'."\n";
		}
		if ($extra_lastcol) $output .= '<td>'.$extra_lastcol.'</td>';
		$output .= '</tr>'."\n";
	}
	return $output;
}

function zz_show_class($attr) {
	if (!$attr) return false;
	$attr = trim(implode(" ", $attr));
	if (!$attr) return false;
	return ' class="'.$attr.'"';
}

function zz_show_separator($separator) {
	if ($separator == 1)
		return '<tr><td colspan="2" class="separator"><hr></td></tr>'."\n";
	elseif ($separator == 'column_begin')
		return '<tr><td><table><tbody>'."\n";
	elseif ($separator == 'column')
		return "</tbody></table>\n</td>\n\n".'<td class="left_separator"><table><tbody>'."\n";
	elseif ($separator == 'column_end')
		return "</tbody></table>\n</td></tr>\n";
}

?>