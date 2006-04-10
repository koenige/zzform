<?php

/*
	zzform Scripts

	function zz_display_records
		add, edit, delete, review a record
	function show_field_rows
		will be called from zz_display_records
		shows all table rows for given record
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2006

*/

function zz_display_records($my, $my_tab, $zz_conf, $display, $zz_var) {
	global $text;
	global $zz_error;
	$output = '';
	if ($my['formhead']) $output.= "\n<h3>".ucfirst($my['formhead'])."</h3>\n\n";
	$output.= zz_error($zz_error);

	if ($display) {
		if (($my['mode'] == 'add' OR $my['mode'] == 'edit') && !empty($zz_conf['upload_MAX_FILE_SIZE'])) 
			$output.= '<input type="hidden" name="MAX_FILE_SIZE" value="'.$zz_conf['upload_MAX_FILE_SIZE'].'">'."\n";
		$output.= '<table>'."\n";
		$output.= show_field_rows($my_tab, 0, 0, $my['mode'], $display, $zz_var, $zz_conf);

		if ($my['mode'] && $my['mode'] != 'review' && $my['mode'] != 'show') {
			$output.= '<tfoot>'."\n";
			$output.= '<tr><th>&nbsp;</th> <td><input type="submit" value="';
			$accesskey = 's';
			if		($my['mode'] == 'edit') 	$output.= $text['update_to'].' ';
			elseif	($my['mode'] == 'delete')	$output.= $text['delete_from'].' ';
			else 								$output.= $text['add_to'].' ';
			if ($my['mode'] == 'delete') $accesskey = 'd';
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
			if ($cancelurl != $_SERVER['REQUEST_URI'] OR ($my['action'])) // only show cancel link if it is possible to hide form // todo: expanded to action, not sure if this works on add only forms, this is for re-edit a record in case of missing field values etc.
				$output.= ' <a href="'.$cancelurl.'">'.$text['Cancel'].'</a>';
			$output.= '</td></tr>'."\n";
			$output.= '</tfoot>'."\n";
		} else {
			if ($zz_conf['list']) {
				$output.= '<tfoot>'."\n";
				$output.= '<tr><th>&nbsp;</th> <td class="reedit">';
				if ($zz_conf['edit']) {
					$output.= '<a href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=edit&amp;id='.$my_tab[0][0]['id']['value'].$my['extraGET'].'">'.$text['edit'].'</a>';
					if ($zz_conf['delete']) $output.= ' | <a href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=delete&amp;id='.$my_tab[0][0]['id']['value'].$my['extraGET'].'">'.$text['delete'].'</a>';
				}
				$output.= '</td></tr>'."\n";
				if (isset($zz_conf['details'])) {
					$output.= '<tr><th>&nbsp;</th><td class="editbutton">';
					if (isset($my_tab[0][0]['POST']))
						$output.= show_more_actions($zz_conf['details'], $zz_conf['details_url'], $zz_conf['details_base'], $my_tab[0][0]['id']['value'], $my_tab[0][0]['POST']);
					else
						$output.= show_more_actions($zz_conf['details'], $zz_conf['details_url'], $zz_conf['details_base'], $my_tab[0][0]['id']['value']);
					$output.= '</td></tr>';
				}
				$output.= '</tfoot>'."\n";
			}
		}
		$output.= '</table>'."\n";
		if ($my['mode'] == 'delete') $output.= '<input type="hidden" name="'.$my_tab[0][0]['id']['field_name'].'" value="'.$my_tab[0][0]['id']['value'].'">'."\n";
		if ($my['mode'] && $my['mode'] != 'review' && $my['mode'] != 'show') {
			switch ($my['mode']) {
				case 'add': $submit = 'insert'; break;
				case 'edit': $submit = 'update'; break;
				case 'delete': $submit = 'delete'; break;
			}
			$output.= '<input type="hidden" name="action" value="'.$submit.'">';
			if ($zz_conf['referer']) $output.= '<input type="hidden" value="'.$zz_conf['referer'].'" name="referer">';
			if (isset($_GET['file']) && $_GET['file']) 
				$output.= '<input type="hidden" value="'.$_GET['file'].'" name="file">';
		}
		if ($display == 'form') {
			foreach (array_keys($my_tab) as $tabindex) {
				if ($tabindex && isset($my_tab[$tabindex]['records']))
					$output.= '<input type="hidden" name="records['.$tabindex.']" value="'.$my_tab[$tabindex]['records'].'">';
				if (isset($my_tab[$tabindex]['deleted']))
					foreach ($my_tab[$tabindex]['deleted'] as $deleted_id)
						$output.= '<input type="hidden" name="deleted['.$my_tab[$tabindex]['table_name'].'][]['.$my_tab[$tabindex][0]['id']['field_name'].']" value="'.$deleted_id.'">';
				if ($tabindex && !isset($my_tab[$tabindex]['deleted']) && !isset($my_tab[$tabindex]['records']) && isset($_POST['records'])) 
					// this occurs when a record is not validated. subtable fields will be validated, so this is not perfect as there are no more options to enter a record even if not all subrecords were filled in
					$output.= '<input type="hidden" name="records['.$tabindex.']" value="'.$_POST['records'][$tabindex].'">';
			}
		}
		if (isset($zz_conf['variable']))
			foreach ($zz_conf['variable'] as $myvar)
				if (isset($my['record'][$myvar['field_name']])) $output.= '<input type="hidden" value="'.$my['record'][$myvar['field_name']].'" name="'.$myvar['f_field_name'].'">';
	}
	if ($output) $output = '<div id="record">'."\n$output</div>\n";
	return $output;
}

function show_field_rows($my_tab, $i, $k, $mode, $display, $zz_var, $zz_conf) {
	global $text;
	global $zz_error;
	$output = '';
	$append_next = '';
	$my = $my_tab[$i][$k];
	$firstrow = true;
	foreach ($my['fields'] as $fieldkey => $field) {
		$outputf = '';
		// $i means subtable, since main table has $i = 0
		if ($i) $field['f_field_name'] = $my_tab[$i]['table_name'].'['.$k.']['.$field['field_name'].']';
		elseif (isset($field['field_name'])) $field['f_field_name'] = $field['field_name'];
		if (!empty($field['format'])) { // formatted fields: show that they are being formatted!
			if (!isset($field['title_desc'])) $field['title_desc'] = '';
			$field['title_desc'] .= " [".ucfirst($field['format']).']';
		}
		if ($field['type'] == 'subtable') {
//	Subtable
			$output.= '<tr'.(!empty($field['class']) ? ' class="'.$field['class'].'"' : '').'><th class="sub-add">';
			if ($display == 'form' && !isset($my_tab[$field['subtable']]['records']))  // this happens in case $validation is false
				$my_tab[$field['subtable']]['records'] = $_POST['records'][$field['subtable']];
			if ($display == 'form' && $my_tab[$field['subtable']]['max_records'] > $my_tab[$field['subtable']]['records'])
				$output.= '<input type="submit" value="+" name="subtables[add]['.$field['subtable'].']">';
			$output.= $field['title'];
			if (!empty($field['title_desc']) && $display == 'form') $output.= '<p class="desc">'.$field['title_desc'].'</p>';
			$output.= '</th><td class="subtable">';
			$subtables = array_keys($my_tab[$field['subtable']]);
			foreach (array_keys($subtables) as $index)
				if (!is_numeric($subtables[$index])) unset($subtables[$index]);
			foreach ($subtables as $mytable_no) {
				// show all subtables which are not deleted but 1 record as a minimum
				if ($my_tab[$field['subtable']][$mytable_no]['action'] != 'delete' 
					OR (!empty($my_tab[$field['subtable']]['records']) && ($mytable_no + 1) == $my_tab[$field['subtable']]['min_records'])) {
					if ($display == 'form' && $my_tab[$field['subtable']]['min_records'] < $my_tab[$field['subtable']]['records'])
						$output.= '<input type="submit" value="-" class="sub-remove" name="subtables[remove]['.$field['subtable'].']['.$mytable_no.']">';
					$output.= '<table>'; 
					$output.= show_field_rows($my_tab, $field['subtable'], $mytable_no, $mode, $display, $zz_var, $zz_conf);
					$output.= '</table>';
				}
			}
			if ($display == 'form' && $field['explanation']) $output.= '<p class="explanation">'.$field['explanation'].'</p>';
			$output.= '</td></tr>';
		} elseif (!($field['type'] == 'id' AND !$zz_conf['list']) AND $field['type'] != 'foreign_key') {
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
				$output.= '<tr'.($field['class'] ? ' class="'.$field['class'].'"' : '').'>';
				if (!(isset($field['show_title']) && !$field['show_title'])) {
					$output.= '<th>';
					if (!empty($field['title_append'])) $output.= $field['title_append']; // just for form, change title
					else $output.= $field['title'];
					if (!empty($field['title_desc']) && $display == 'form') $output.= '<p class="desc">'.$field['title_desc'].'</p>';
					$output.= '</th>';
				} elseif (!$i) {
					$output.= '<th></th>'; // for main record, show empty cells
				}
				$output.= ' <td>';
				$close_span = false;
			} else {
				$close_span = true;
				$output.= '<span'.($field['class'] ? ' class="'.$field['class'].'"' : '').'>'; // so error class does not get lost
			}
			if (isset($field['append_next']) && $field['append_next']) $append_next = true;
			else $append_next = false;
			if (!isset($field['size']))
				if ($field['type'] == 'number') $field['size'] = 16;
		 		else $field['size'] = 32;
			if ($field['type'] == 'time') $field['size'] = 8;
			if ($field['maxlength'] && $field['maxlength'] < $field['size']) $field['size'] = $field['maxlength'];
			if ($my['record'] && isset($field['factor']) && $my['record'][$field['field_name']])
				if (!is_array($my['record'][$field['field_name']])) // for incorrect values
					$my['record'][$field['field_name']] /=$field['factor'];
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
						if (stristr($sql_max, ' WHERE '))
							$sql_max = str_replace(' WHERE ', ' WHERE ('.$sql_max_where.') AND ', $sql_max);
						else
							$sql_max.= ' WHERE '.$sql_max_where;
					$sql_max .= ' ORDER BY '.$field['field_name'].' DESC';
					$myresult = mysql_query($sql_max);
					if (!$i OR $sql_max_where) // query only if maintable or saved record
						if ($myresult)
							if (mysql_num_rows($myresult)) {
								$field['default'] = mysql_result($myresult, 0, $field['field_name']);
								$field['default']++;
							} else $field['default'] = 1;
				}
			}
			if (isset($zz_var['where'][$field['field_name']])) {
				if ($field['type'] == 'select') $field['type_detail'] = 'select';
				else $field['type_detail'] = false;
				$field['type'] = 'predefined';
			} elseif (isset($values) && is_array($values) && isset($values[$field['field_name']]))
				$field['default'] = $values[$field['field_name']];
			if (isset($field['default']))
//				if (!$my['record'] OR !empty($is_option)) { // set default only if record is empty OR if it's an option field which is always empty
				if (($mode == 'add' && !$my['record']) OR !empty($is_option)
					OR !$my['record'] && !empty($field['def_val_ignore'])) { // set default only if record is empty OR if it's an option field which is always empty OR if default value is set to be ignored in case of no further additions
					$my['record'][$field['field_name']] = $field['default'];
					$default_value = true; // must be unset later on because of this value
				}
			//
			// output all records
			//
			
			switch ($field['type']) {
				case 'id':
					if ($my['id']['value']) $outputf.= '<input type="hidden" value="'.$my['id']['value'].'" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">'.$my['id']['value'];
					else $outputf.= '('.$text['will_be_added_automatically'].')&nbsp;';
					break;
				case 'identifier':
				case 'hidden':
					$outputf.= '<input type="hidden" value="';
					if (isset($field['value'])) $outputf.= $field['value'];
					elseif ($my['record']) $outputf.= $my['record'][$field['field_name']];
					$outputf.= '" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
					if ($my['record']) {
						if (isset($field['timestamp']) && $field['timestamp'])
							$outputf.= timestamp2date($my['record'][$field['field_name']]);
						elseif (isset($field['display_field'])) $outputf.= $my['record'][$field['display_field']];
						else $outputf.= $my['record'][$field['field_name']];
					} else
						$outputf.= '('.$text['will_be_added_automatically'].')&nbsp;';
					break;
				case 'timestamp':
					$outputf.= '<input type="hidden" value="';
					if (isset($field['value'])) $outputf.= $field['value'];
					elseif ($my['record']) $outputf.= $my['record'][$field['field_name']];
					$outputf.= '" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
					if ($my['record'])
						$outputf.= timestamp2date($my['record'][$field['field_name']]);
					else
						$outputf.= '('.$text['will_be_added_automatically'].')&nbsp;';
					break;
				case 'unix_timestamp':
					if (isset($field['value'])) {
						if ($display == 'form') $outputf.= '<input type="hidden" value="';
						$outputf.= $field['value'];
					} else {
						if ($display == 'form') $outputf.= '<input type="text" value="';
						if ($my['record']) {
							$timestamp = strtotime($my['record'][$field['field_name']]);
							if ($timestamp != -1)
								$my['record'][$field['field_name']] = $timestamp;
							$outputf.= date('Y-m-d H:i:s', $my['record'][$field['field_name']]);
						}
					}
					if ($display == 'form') $outputf.= '" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
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
					$outputf.= '<input type="hidden" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'" value="'.$zz_var['where'][$field['field_name']].'">';
					if ($field['type_detail'] == 'select') {
						$my_fieldname = $field['field_name'];
						if (isset($field['key_field_name'])) $my_fieldname = $field['key_field_name'];
						if (isset($field['sql'])) {
							if (strstr($field['sql'], 'WHERE')) 
								$mysql = str_replace('WHERE', (' WHERE ('.$my_fieldname.' = '.$zz_var['where'][$field['field_name']].') AND'), $field['sql']);
							elseif (strstr($field['sql'], 'ORDER BY'))
								$mysql = str_replace('ORDER BY', (' WHERE '.$my_fieldname.' = '.$zz_var['where'][$field['field_name']].' ORDER BY'), $field['sql']);
							else
								$mysql = $field['sql'].' WHERE '.$my_fieldname.' = '.$zz_var['where'][$field['field_name']];
							$result_detail = mysql_query($mysql);
							if ($result_detail) {
								if (mysql_num_rows($result_detail) == 1)
									$outputf .= implode(' | ', mysql_fetch_row($result_detail));
							} else $outputf.= zz_error($zz_error = array('mysql' => mysql_error(), 'query' => $mysql, 'msg' => $text['error-sql-incorrect']));
						} elseif (isset($field['enum'])) {
							$outputf.= $zz_var['where'][$field['field_name']];
						}
					} else
						$outputf.= $zz_var['where'][$field['field_name']];
					break;
				case 'password':
					if ($display == 'form') {
						$outputf.= '<input type="password" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'" size="'.$field['size'].'" ';
						if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
					}
					if ($my['record'])
						if ($display == 'form') $outputf.= 'value="'.$my['record'][$field['field_name']].'"';
						else $outputf .= '('.$text['hidden'].')';
					if ($display == 'form') $outputf.= '>';
					break;
				case 'password_change':
					if ($display == 'form') {
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
					if ($display == 'form') {
						$outputf.= '<input type="text" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'" size="'.$field['size'].'" ';
						if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
					}
					if ($my['record']) {
						if ($display == 'form') $outputf.= 'value="';
						elseif ($field['type'] == 'url' && !empty($my['record'][$field['field_name']])) 
							$outputf.= '<a href="'.$my['record'][$field['field_name']].'">';
						elseif ($field['type'] == 'mail' && !empty($my['record'][$field['field_name']]))
							$outputf.= '<a href="mailto:'.$my['record'][$field['field_name']].'">';
						if ($field['type'] == 'url' AND strlen($my['record'][$field['field_name']]) > $zz_conf['max_select_val_len'] AND $display != 'form')
							$outputf.= htmlchars(substr($my['record'][$field['field_name']], 0, $zz_conf['max_select_val_len'])).'...';
						else
							$outputf.= htmlchars($my['record'][$field['field_name']]);
						if (($field['type'] == 'url' OR $field['type'] == 'mail')
							&& !empty($my['record'][$field['field_name']]) && $display != 'form') $outputf.= '</a>';
						if ($display == 'form') $outputf.= '"';
					} elseif ($mode == 'add' AND $field['type'] == 'datetime')
						$outputf.= 'value="'.date('Y-m-d H:i:s', time()).'"';
					if ($display == 'form') $outputf.= '>';
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
							if ($display == 'form') {
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
						if ($display == 'form') {
							$myid = make_id_fieldname($field['field_name'].'_dec', 'radio');
							$outputf.= '<label for="'.$myid.'"><input type="radio" id="'.$myid.'" name="'.$field['f_field_name'].'[which]" value="dec" '.($w_checked == 'dec' ? $checked: '').'> '.$text['dec'].'&nbsp; </label></span>';
							$outputf.= '<input type="text" name="'.$field['f_field_name'].'[dec]" id="'.make_id_fieldname($field['f_field_name']).'_dec" size="12" ';
						} 
						if ($my['record']) {
							if ($display == 'form') $outputf.= 'value="';
							if(!is_array($my['record'][$field['field_name']])) 
								$outputf.= $my['record'][$field['field_name']];
							else // this would happen if record is not validated
								$outputf.= $my['record'][$field['field_name']]['dec'];
							if ($display == 'form') $outputf.= '"';
						}
						if ($display == 'form') $outputf.= '>';
					
					
					} else {
						if ($display == 'form') {
							$outputf.= '<input type="text" ';
							$outputf.=  'name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'" size="'.$field['size'].'" ';
						}
						if ($my['record']) {
							if ($display == 'form') $outputf.= 'value="';
							$outputf.= htmlchars($my['record'][$field['field_name']]);
							if ($display == 'form') $outputf.= '"';
						}
						if ($display == 'form') $outputf.= '>';
					}
					break;
				case 'date':
					if ($display == 'form') $outputf.= '<input type="text" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'" size="12" ';
					if ($my['record']) {
						if ($display == 'form') $outputf.= 'value="';
						$outputf.= datum_de($my['record'][$field['field_name']]);
						if ($display == 'form') $outputf.= '"';
					} 
					if ($display == 'form') $outputf.= '>';
					break;
				case 'memo':
					if (!isset($field['rows'])) $field['rows'] = 8;
					if ($display == 'form') $outputf.= '<textarea rows="'.$field['rows'].'" cols="60" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'"';
					if ($display == 'form') $outputf.= '>';
					if ($my['record']) {
//						$memotext = stripslashes($my['record'][$field['field_name']]);
						$memotext = $my['record'][$field['field_name']];
						$memotext = htmlchars($memotext);
						if ($display != 'form' && isset($field['format'])) $memotext = $field['format']($memotext);
						$outputf.= $memotext;
					}
					if ($display == 'form') $outputf.= '</textarea>';
					break;
				//case 'enum':
				//	$outputf.= mysql_field_flags($field['field_name']);
				//	break;
				case 'select':
					if (!empty($field['sql_without_id'])) $field['sql'] .= $my['id']['value'];
					if (!empty($field['sql'])) {
						if (!empty($field['sql_where']) && $zz_var['where']) { // add WHERE to sql clause if necessary
							$my_where = '';
							$add_details_where = ''; // for add_details
							foreach ($field['sql_where'] as $sql_where) {
								// might be several where-clauses
								if (!$my_where) $my_where = ' WHERE ';
								else $my_where .= ' AND ';
								if (isset($sql_where[2])) {
									foreach (array_keys($zz_var['where']) as $value_key)
										if ($value_key == $sql_where[1]) $sql_where[2].= $zz_var['where'][$value_key];
									$result_detail = mysql_query($sql_where[2]);
									if ($result_detail) {
										//if (mysql_num_rows($result_detail) == 1)
										// might be that there are more results, so that should not be a problem
											$index = mysql_result($result_detail,0,0);
										//else $outputf.= $sql_where[2];
									} else $outputf.= zz_error($zz_error = array('mysql' => mysql_error(), 'query' => $sql_where[2], 
										'msg' => $text['error-sql-incorrect']));
								}
								$my_where .= $sql_where[0]." = '".$index."'";
								$add_details_where .= '&amp;where['.$sql_where[0].']='.$index;
							}
							if (strstr($field['sql'], 'ORDER BY'))
								$field['sql'] = str_replace('ORDER BY', ($my_where.' ORDER BY'), $field['sql']);
							else
								$field['sql'] .= ' '.$my_where;
						}
						$result_detail = mysql_query($field['sql']);
						if (!$result_detail) $outputf.= zz_error($zz_error = array('mysql' => mysql_error(), 'query' => $field['sql'], 'msg' => $text['error-sql-incorrect']));
						elseif ($display == 'form' && mysql_num_rows($result_detail) == 1 && !checkfornull($field['field_name'], $my_tab[$i]['table'])) {
							// there is only one result in the array, and this will be pre-selected because FIELD must not be NULL
							$line = mysql_fetch_array($result_detail); // need both numeric and assoc keys
							if ($my['record'] && $line[0] != $my['record'][$field['field_name']]) $outputf .= 'Possible Values: '.$line[0].' -- Current Value: '.$my['record'][$field['field_name']].' -- Error --<br>'.$text['no_selection_possible'];
							else {
								$outputf.= '<input type="hidden" value="'.$line[0].'" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
								$outputf.= draw_select($line, $my['record'], $field, false, 0, false, false, $zz_conf);
							}
						} elseif ($display == 'form' && mysql_num_rows($result_detail) > $zz_conf['max_select']) {
							$textinput = true;
							if ($my['record'])
								while ($line = mysql_fetch_array($result_detail, MYSQL_BOTH))
									if ($line[0] == $my['record'][$field['field_name']]) {
										$outputf.= draw_select($line, $my['record'], $field, false, 0, false, 'reselect', $zz_conf);
										$textinput = false;
									}
							if (!empty($my['record'][$field['field_name']])) $value = $my['record'][$field['field_name']]; // value will not be checked if one detail record is added because in this case validation procedure will be skipped!
							else $value = '';
							if ($textinput) // add new record
								$outputf.= '<input type="text" size="32" value="'.$value.'" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
							$outputf.= '<input type="hidden" value="'.$field['f_field_name'].'" name="check_select[]">';
						} elseif (mysql_num_rows($result_detail) > 0) {
							if ($display == 'form') {
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
										$outputf.= draw_select($line, $my['record'], $field, false, 0, false, 'form', $zz_conf);
								if (!empty($field['show_hierarchy']))
									foreach ($my_select['NULL'] AS $my_field)
										$outputf.= draw_select($my_field, $my['record'], $field, $my_select, 0, $field['show_hierarchy'], 'form', $zz_conf);
								$outputf.= '</select>'."\n";
							} else 
								while ($line = mysql_fetch_array($result_detail, MYSQL_BOTH))
									if ($line[0] == $my['record'][$field['field_name']])
										$outputf.= draw_select($line, $my['record'], $field, false, 0, false, false, $zz_conf);
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
							if ($display == 'form') {
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
						if ($display == 'form') {
							if (count($field['enum']) <= 2) {
								$myid = 'radio-'.$field['field_name'].'-'.$myi;
								$outputf.= '<label for="'.$myid.'" class="hidden"><input type="radio" id="'.$myid.'" name="'.$field['f_field_name'].'" value=""';
								if ($my['record']) if (!$my['record'][$field['field_name']]) $outputf.= ' checked';
								$outputf.= '>'.$text['no_selection'].'</label>';
							} else {
								$outputf.= '<select name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">'."\n";
								$outputf.= '<option value=""';
								if ($my['record']) if (!$my['record'][$field['field_name']]) $outputf.= ' selected';
								$outputf.= '>'.$text['none_selected'].'</option>';
							} 
						}
						foreach ($field['enum'] as $key => $set) {
							if ($display == 'form') {
								if (count($field['enum']) <= 2) {
									$myi++;
									$myid = 'radio-'.$field['field_name'].'-'.$myi;
									$outputf.= ' <label for="'.$myid.'"><input type="radio" id="'.$myid.'" name="'.$field['f_field_name'].'" value="'.$set.'"';
									if ($my['record']) if ($set == $my['record'][$field['field_name']]) $outputf.= ' checked';
									$outputf.= '> '.(!empty($field['enum_title'][$key]) ? $field['enum_title'][$key] : $set).'</label>';
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
						if ($display == 'form' && count($field['enum']) > 2) $outputf.= '</select>'."\n";
					} else {
						$outputf.= $text['no_source_defined'].'. '.$text['no_selection_possible'];
					}
					break;
				case 'image':
				case 'upload_image':
					if ($mode != 'add' OR $field['type'] != 'upload_image') {
						$img = false;
						$outputf.= '<p>';
						if (isset($field['path']))
							$outputf .= $img = show_image($field['path'], $my['record']);
						if (!$img) $outputf.= '('.$text['image_not_display'].')';
						$outputf.= '</p>';
					}
					if (($mode == 'add' OR $mode == 'edit') && $field['type'] == 'upload_image') {
						if (!isset($field['image'])) {
							$outputf.= zz_error($zz_error = array('msg' => 'Configuration error. Missing upload_image details.'));
						} else {
							$image_uploads = 0;
							foreach ($field['image'] as $imagekey => $image)
								if (isset($image['source']))
									$image_uploads++;
							if (count($image_uploads) > 1) $outputf.= '<table class="upload">';
							foreach ($field['image'] as $imagekey => $image) {
								if (!isset($image['source'])) {
									// todo: if only one image, table is unneccessary
									// title and field_name of image might be empty
									if (count($image_uploads) > 1) $outputf.= '<tr><th>'.$image['title'].'</th> <td>';
									$outputf .= '<input type="file" name="'.$field['field_name'].'['.$image['field_name'].']">';
									if (!empty($my['images'][$fieldkey][$imagekey]['error']))
										$outputf.= '<br><small>'.implode('<br>', $my['images'][$fieldkey][$imagekey]['error']).'</small>';
									if ($display == 'form' && !empty($image['explanation'])) $outputf.= '<p class="explanation">'.$image['explanation'].'</p>';
									if (count($image_uploads) > 1) $outputf.= '</td></tr>'."\n";
								}
							}
							if (count($image_uploads) > 1) $outputf.= '</table>';
						}
					}
					break;
				case 'display':
					if ($my['record']) $outputf .= $my['record'][$field['display_field']];
					else $outputf .= $text['N/A'];
					break;
				case 'calculated':
					if (!$mode OR $mode == 'show') {
						// identischer Code mit weiter unten, nur statt $line $my['record']!!
						if ($field['calculation'] == 'hours') {
							$diff = 0;
							foreach ($field['calculation_fields'] as $calc_field)
								if (!$diff) $diff = strtotime($my['record'][$calc_field]);
								else $diff -= strtotime($my['record'][$calc_field]);
							$outputf.= gmdate('H:i', $diff);
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
			if ($mode && $mode != 'delete' && $mode != 'show')
				if (isset($field['add_details'])) $outputf.= ' <a href="'.$field['add_details'].'?mode=add&amp;referer='.urlencode($_SERVER['REQUEST_URI']).$add_details_where.'">['.$text['new'].' &hellip;]</a>';
			if ($outputf && $outputf != ' ') {
				if (isset($field['prefix'])) $output.= ' '.$field['prefix'].' ';
				$output.= $outputf;
				if (isset($field['suffix'])) $output.= ' '.$field['suffix'].' ';
				if ($display == 'form') if (isset($field['suffix_function'])) {
					$vars = '';
					if (isset($field['suffix_function_var']))
						foreach ($field['suffix_function_var'] as $var)
							$vars .= $var; // todo: does this really make sense? looks more like $vars[] = $var. maybe use implode.
					$output.= $field['suffix_function']($vars);
				}
			} else
				$output.= $outputf;
			if (!empty($close_span)) $output.= '</span>';
			if (!$append_next) {
				if ($display == 'form' && $field['explanation']) $output.= '<p class="explanation">'.$field['explanation'].'</p>';
				$output.= '</td></tr>'."\n";
			}
		}
	}
	return $output;
}

?>