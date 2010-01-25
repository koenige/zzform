<?php

// zzform
// (c) Gustaf Mossakowski, <gustaf@koenige.org>, 2004-2009
// display of single record as a html form+table or for review as a table

/*
	function zz_display_records
		add, edit, delete, review a record
	function zz_show_field_rows
		will be called from zz_display_records
		shows all table rows for given record
*/

/** Display form to edit a record
 * 
 * @param $zz
 * @param $my_tab			= $zz_tab, won't be changed by this function
 * @param $zz_conf
 * @param $display			(string) 'review': show form with all values for
 *							review; 'form': show form for editing; false: don't
 *							show form at all
 * @param $zz_var
 * @param $zz_conditions
 * @return $string			HTML-Output with all form fields
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_display_records($zz, $my_tab, $zz_conf, $display, $zz_var, $zz_conditions) {
	global $zz_error;
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();

	$output = '';
	if ($zz['formhead'] && $zz_conf['access'] != 'export')
		$output.= "\n<h2>".ucfirst($zz['formhead'])."</h2>\n\n";
	$output.= zz_error();

	// if there is nothing to display, just show errors and formhead or nothing at all and return
	if (!$display) {
		if ($output) $output = '<div id="record">'."\n$output</div>\n";
		return $output;
	}
	
	// there is a form to display
	$zz_conf_record = zz_record_conf($zz_conf);
	// check conditions
	if (!empty($zz_conf_record['conditions']) AND !empty($zz_conditions['bool']))
		$zz_conf_record = zz_conditions_merge($zz_conf_record, $zz_conditions['bool'], $my_tab[0][0]['id']['value']);

	if (($zz['mode'] == 'add' OR $zz['mode'] == 'edit') && !empty($zz_conf['upload_MAX_FILE_SIZE'])) 
		$output.= '<input type="hidden" name="MAX_FILE_SIZE" value="'.$zz_conf['upload_MAX_FILE_SIZE'].'">'."\n";
	$output.= '<table>'."\n";

	$cancelurl = $zz_conf['url_self'];
	if (($zz_conf['url_self_qs_base'].$zz_conf['url_self_qs_zzform'])) {
		$unwanted_keys = array('mode', 'id', 'add');
		$cancelurl.= zz_edit_query_string($zz_conf['url_self_qs_base'].$zz_conf['url_self_qs_zzform'], $unwanted_keys);
	}
	if ($zz['mode'] && $zz['mode'] != 'review' && $zz['mode'] != 'show'  && $zz['mode'] != 'list_only') {
		$output.= '<tfoot>'."\n";
		$output.= '<tr><th>&nbsp;</th> <td><input type="submit" value="';
		$accesskey = 's';
		if		($zz['mode'] == 'edit') 	$output.= zz_text('update_to').' ';
		elseif	($zz['mode'] == 'delete')	$output.= zz_text('delete_from').' ';
		else 								$output.= zz_text('add_to').' ';
		if ($zz['mode'] == 'delete') $accesskey = 'd';
		$output.= zz_text('database').'" accesskey="'.$accesskey.'">';
		if ($cancelurl != $_SERVER['REQUEST_URI'] OR ($zz['action'])) 
			// only show cancel link if it is possible to hide form 
			// todo: expanded to action, not sure if this works on add only forms, 
			// this is for re-edit a record in case of missing field values etc.
			$output.= ' <a href="'.$cancelurl.'">'.zz_text('Cancel').'</a>';
		$output.= '</td></tr>'."\n";
		$output.= '</tfoot>'."\n";
	} else {
		if ($zz_conf_record['access'] != 'add_only') {
			$output.= '<tfoot>'."\n";
			if ($zz_conf_record['edit']) {
				$output.= '<tr><th>&nbsp;</th> <td class="reedit">';
				$output.= '<a href="'.$zz_conf['url_self'].$zz_conf['url_self_qs_base'].$zz_var['url_append']
					.'mode=edit&amp;id='.$my_tab[0][0]['id']['value'].$zz_var['extraGET']
					.'">'.zz_text('edit').'</a>';
				if ($zz_conf_record['delete']) $output.= ' | <a href="'
					.$zz_conf['url_self'].$zz_conf['url_self_qs_base'].$zz_var['url_append'].'mode=delete&amp;id='
					.$my_tab[0][0]['id']['value'].$zz_var['extraGET'].'">'
					.zz_text('delete').'</a>';
				$output.= '</td></tr>'."\n";
			}
			if (!empty($zz_conf_record['details'])) {
				$output.= '<tr><th>&nbsp;</th><td class="editbutton">'
					.zz_show_more_actions($zz_conf_record['details'], 
					$zz_conf_record['details_url'], $zz_conf_record['details_base'], 
					$zz_conf_record['details_target'], $zz_conf_record['details_referer'],
					$my_tab[0][0]['id']['value'], 
					(!empty($my_tab[0][0]['POST']) ? $my_tab[0][0]['POST'] : false))
					.'</td></tr>'."\n";
			}
			if (empty($zz_conf_record['details']) AND ! $zz_conf_record['edit']) {
				$output.= '<tr><th>&nbsp;</th><td class="editbutton">'
					.' <a href="'.$cancelurl.'">'.zz_text('Cancel').'</a>'
					.'</td></tr>'."\n";
			}			
			$output.= '</tfoot>'."\n";
		}
	}
	$output.= zz_show_field_rows($my_tab, 0, 0, $zz['mode'], $display, $zz_var, $zz_conf_record, $zz['action']);
	$output.= '</table>'."\n";
	if ($zz['mode'] == 'delete') $output.= '<input type="hidden" name="'
		.$my_tab[0][0]['id']['field_name'].'" value="'.$my_tab[0][0]['id']['value'].'">'."\n";
	if ($zz['mode'] && $zz['mode'] != 'review' && $zz['mode'] != 'show' AND $zz['mode'] != 'list_only') {
		switch ($zz['mode']) {
			case 'add': $submit = 'insert'; break;
			case 'edit': $submit = 'update'; break;
			case 'delete': $submit = 'delete'; break;
		}
		$output.= '<input type="hidden" name="zz_action" value="'.$submit.'">';
		if ($zz_conf['referer']) $output.= '<input type="hidden" value="'
			.$zz_conf['referer'].'" name="referer">';
		if (isset($_GET['file']) && $_GET['file']) 
			$output.= '<input type="hidden" value="'.htmlspecialchars($_GET['file']).'" name="file">';
	}
	if ($display == 'form') {
		foreach (array_keys($my_tab) as $tabindex) {
			if ($tabindex && isset($my_tab[$tabindex]['records']))
				$output.= '<input type="hidden" name="records['.$tabindex.']" value="'
				.$my_tab[$tabindex]['records'].'">';
			if (isset($my_tab[$tabindex]['subtable_deleted']))
				foreach ($my_tab[$tabindex]['subtable_deleted'] as $deleted_id)
					$output.= '<input type="hidden" name="zz_subtable_deleted['
					.$my_tab[$tabindex]['table_name'].'][]['
					.$my_tab[$tabindex][0]['id']['field_name'].']" value="'
					.$deleted_id.'">';
			if ($tabindex && !isset($my_tab[$tabindex]['subtable_deleted']) 
				&& !isset($my_tab[$tabindex]['records']) && isset($_POST['records'])) 
				// this occurs when a record is not validated. subtable fields 
				// will be validated, so this is not perfect as there are no 
				// more options to enter a record even if not all subrecords were filled in
				$output.= '<input type="hidden" name="records['.$tabindex
				.']" value="'.htmlspecialchars($_POST['records'][$tabindex]).'">';
		}
	}
	if (isset($zz_conf_record['variable']))
		foreach ($zz_conf_record['variable'] as $myvar)
			if (isset($zz['record'][$myvar['field_name']])) $output.= '<input type="hidden" value="'
				.$zz['record'][$myvar['field_name']].'" name="'.$myvar['f_field_name'].'">';
	$output = '<div id="record">'."\n$output</div>\n";
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
	return $output;
}

function zz_show_field_rows($my_tab, $i, $k, $mode, $display, &$zz_var, 
	$zz_conf_record, $action, $formdisplay = 'vertical', $extra_lastcol = false, 
	$table_count = 0, $show_explanation = true) {

	global $zz_error;
	global $zz_conf;	// Config variables
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	$output = '';
	$append_next = '';
	$append_next_type = '';
	$matrix = false;
	$my = $my_tab[$i][$k];
	$firstrow = true;
	$table_name = (!empty($my_tab[$i]['table_name']) ? $my_tab[$i]['table_name'] : $my_tab[$i]['table']);
	$row_display = (!empty($my['access']) ? $my['access'] : $display); // this is for 0 0 main record

	// check if there's a filter with a field_name 
	// this field will get the filter value as default value
	$filter_field_name = array();
	if (!empty($_GET['filter'])) {
		foreach (array_keys($_GET['filter']) AS $filter_identifier) {
			foreach ($zz_conf['filter'] as $filter) {
				if ($filter['identifier'] == $filter_identifier
					AND !empty($filter['field_name']))
				{
					$filter_field_name[$filter_identifier] = $filter['field_name'];
				}
			}
		}
	}
	
	foreach ($my['fields'] as $fieldkey => $field) {
		if (!$field) continue;
		if (!empty($field['hide_in_form'])) continue;

		// initialize variables
		if (!$append_next) {
			$out['tr']['attr'] = array();
			$out['th']['attr'] = array();
			$out['th']['content'] = '';
			$out['th']['show'] = true;
			$out['td']['attr'] = array();
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
			$field['title_desc'] .= ' ['.(!empty($zz_conf['format'][$field['format']]['link']) 
				? '<a href="'.$zz_conf['format'][$field['format']]['link'].'">' : '')
				.(ucfirst($field['format']))
				.(!empty($zz_conf['format'][$field['format']]['link']) ? '</a>' : '').']';
		}
		if ($field['type'] == 'subtable') {
			if (empty($field['form_display'])) $field['form_display'] = 'vertical';
//	Subtable
			$st_display = (!empty($field['access']) ? $field['access'] : $display);
			$out['tr']['attr'][] = (!empty($field['class']) ? $field['class'] : '');
			$out['th']['attr'][] = 'sub-add';
			if ($st_display == 'form' && !isset($my_tab[$field['subtable']]['records']))  // this happens in case $validation is false
				$my_tab[$field['subtable']]['records'] = $_POST['records'][$field['subtable']];
			if (!(isset($field['show_title']) AND !$field['show_title']))
				$out['th']['content'] .= $field['title'];
			if (!empty($field['title_desc']) && $st_display == 'form') 
				$out['th']['content'].= '<p class="desc">'.$field['title_desc'].'</p>';
			$out['td']['attr'][] = 'subtable';
			if ($st_display == 'form' && !empty($field['explanation_top']) && $show_explanation) 
				$out['td']['content'].= '<p class="explanation">'.$field['explanation_top'].'</p>';
			$subtables = array_keys($my_tab[$field['subtable']]);
			foreach ($subtables as $index => $values)
				if (!is_numeric($subtables[$index])) unset($subtables[$index]);
			$zz_var['horizontal_table_head'] = false;
			// go through all detail records
			$table_open = false;
			
			$firstsubtable_no = NULL;
			
			foreach ($subtables as $mytable_no) {
				// show all subtables which are not deleted but 1 record as a minimum
				if ($my_tab[$field['subtable']][$mytable_no]['action'] != 'delete' 
					OR (!empty($my_tab[$field['subtable']]['records']) 
					&& ($mytable_no + 1) == $my_tab[$field['subtable']]['min_records'])) {

					// get first subtable that will be displayed
					// in order to be able to say whether horizontal table shall be openend		
					if (!isset($firstsubtable_no)) $firstsubtable_no = $mytable_no;
					$lastrow = false;
					$show_remove = false;

					$dont_delete_records = (!empty($field['dont_delete_records'])
						? $field['dont_delete_records'] : '');
					if (!empty($field['values'][$mytable_no])) {
						$dont_delete_records = true; // dont delete records with values set
					}
					if ($display == 'form') $lastrow = '&nbsp;'; // just for optical reasons, in case one row allows removing of record
					
					if ($st_display == 'form') {
						if ($my_tab[$field['subtable']]['min_records'] < $my_tab[$field['subtable']]['records']
							&& !$dont_delete_records)
							$show_remove = true;
					}
					$zz_var['class_add'] = ((!empty($field['class_add']) AND
						empty($my_tab[$field['subtable']][$mytable_no]['id']['value'])) 
						? $field['class_add'] : '');
					
					if ($field['form_display'] != 'horizontal' OR $mytable_no == $firstsubtable_no) {
						$out['td']['content'].= '<table class="'.$field['form_display']
							.($field['form_display'] != 'horizontal' ? ' '.$zz_var['class_add'] : '')
							.'">'; // show this for vertical display and for first horizontal record
						$table_open = true;
					}
					if ($field['form_display'] != 'horizontal' OR $mytable_no == count($subtables)-1)
						$h_show_explanation = true;
					else
						$h_show_explanation = false;
					$subtable_mode = $mode;
					if ($subtable_mode == 'edit' AND empty($my_tab[$field['subtable']][$mytable_no]['id']['value']))
						// no saved record exists, so it's add a new record
						$subtable_mode = 'add';
					if ($show_remove) {
						$removebutton = '<input type="submit" value="'
							.sprintf(zz_text('Remove %s'), $field['title'])
							.'" class="sub-remove" name="subtables[remove]['
							.$field['subtable'].']['.$mytable_no.']">';
						if ($field['form_display'] == 'horizontal') {
							$lastrow = $removebutton;	
						}
					}	
					$out['td']['content'].= zz_show_field_rows($my_tab, $field['subtable'], 
						$mytable_no, $subtable_mode, $st_display, $zz_var, $zz_conf_record, $action, 
						$field['form_display'], $lastrow, $mytable_no, $h_show_explanation);
					if ($field['form_display'] != 'horizontal') {
						$out['td']['content'].= '</table>'."\n";
						$table_open = false;
					}
					if ($show_remove) {
						if ($field['form_display'] != 'horizontal') {
							$out['td']['content'] .= $removebutton;
						}
					}
				}
			}
			if ($table_open) {
				$out['td']['content'].= '</table>'."\n";
			}
			if ($st_display == 'form' AND $my_tab[$field['subtable']]['max_records'] > $my_tab[$field['subtable']]['records'])
				$out['td']['content'] .= '<input type="submit" value="'
					.sprintf(zz_text('Add %s'), $field['title'])
					.'" class="sub-add" name="subtables[add]['
					.$field['subtable'].']">';
			if ($st_display == 'form' && $field['explanation'] && $show_explanation)
				$out['td']['content'].= '<p class="explanation">'.$field['explanation'].'</p>';
			if (!empty($field['separator']))
				$out['separator'] = $field['separator'];
		} elseif ($field['type'] == 'foreign_key' OR $field['type'] == 'translation_key' OR $field['type'] == 'detail_key') {
			continue; // this must not be displayed, for internal link only
		} else {
//	"Normal" field
			// option fields must have type_detail set, these are normal fields in form view
			// but won't be saved to database
			if ($field['type'] == 'option') {
				if ($mode != 'edit' AND $mode != 'add') continue; // options will only be shown in edit mode
				$field['type'] = $field['type_detail']; // option as normal field, set to type_detail for display form
				$is_option = true;
			} else $is_option = false;

			// initalize class values
			if (!isset($field['class'])) $field['class'] = array();
			elseif (!is_array($field['class'])) $field['class'] = array($field['class']);

			// add classes
			if ($field['type'] == 'id') $field['class'][] = 'idrow';
			elseif ($firstrow) {
				$field['class'][] = 'firstrow';
				$firstrow = false;
			}
			if ($i AND ($field['type'] == 'id' OR $field['type'] == 'timestamp')) {
				$field['class'][] = 'hidden';
			}
			$field['class'] = implode(" ", $field['class']);

			// append
			if (!$append_next) {
				$out['tr']['attr'][] = $field['class'];
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

			// field size, maxlenght
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
			
			// auto values
			if (isset($field['auto_value'])) {
				if ($field['auto_value'] == 'increment') {
					/*	added 2004-12-06
						maybe easier and faster without sql query - instead rely on table query
					*/
					// get main (sub-)table query, change field order
					$sql_max = zz_edit_sql($my_tab[$i]['sql'], 'ORDER BY', $field['field_name'].' DESC');
					if ($i) { // it's a subtable
						if (!empty($my_tab[0][0]['id']['field_name']) && !empty($my_tab[0][0]['id']['value'])) {
							$sql_max = zz_edit_sql($sql_max, 'WHERE', '('
								.$my_tab[0]['table'].'.'
								.$my_tab[0][0]['id']['field_name'].' = '
								.$my_tab[0][0]['id']['value'].')');
						}
						$field['default'] = $k + 1;
					}
					$myresult = mysql_query($sql_max);
					if ($zz_conf['modules']['debug']) 
						zz_debug(__FUNCTION__, $zz_debug_time_this_function, "next auto_value", $sql_max);
					if ($myresult) {
						if (mysql_num_rows($myresult)) {
							$field['default'] = mysql_result($myresult, 0, $field['field_name']);
							$field['default']++;
						} elseif (!$i) {
							// only if it's the maintable, for subtable default is already set
							$field['default'] = 1;
						}
					}
				}
			}

			// $zz_var, values, defaults
			if (isset($zz_var['where'][$table_name][$field['field_name']])) {
				if ($field['type'] == 'select') $field['type_detail'] = 'select';
				elseif (!isset($field['type_detail'])) $field['type_detail'] = false;
				$field['type'] = 'predefined';
			} elseif (isset($values) && is_array($values) && isset($values[$field['field_name']]))
				$field['default'] = $values[$field['field_name']];
			// Check if filter is applied to this field, set filter value as default value
			if (in_array($field['field_name'], $filter_field_name) AND empty($field['value'])) {
				if (!empty($_GET['filter'][array_search($field['field_name'], $filter_field_name)])) {
					$field['default'] = $_GET['filter'][array_search($field['field_name'], $filter_field_name)];
				}
			}
			if (!empty($field['default']) AND empty($field['value'])) {
				// look at default only if no value is set - value overrides default
//				if (!$my['record'] OR !empty($is_option)) { // set default only if record is empty OR if it's an option field which is always empty
				if (($mode == 'add' && !$my['record']) OR !empty($is_option)
					OR !$my['record'] && !empty($field['def_val_ignore'])) { // set default only if record is empty OR if it's an option field which is always empty OR if default value is set to be ignored in case of no further additions
					$my['record'][$field['field_name']] = $field['default'];
					$default_value = true; // must be unset later on because of this value
				}
			}
			//
			// output all records
			//
			
			if ($row_display == 'form' && !empty($field['explanation_top']))
				$out['td']['content'].= '<p class="explanation">'.$field['explanation_top'].'</p>';
			if ($field['type'] == 'write_once' AND ($mode == 'add' OR $action == 'insert')) {
				$field['type'] = $field['type_detail'];
			}
			$outputf = false;
			switch ($field['type']) {
			case 'id':
				if ($my['id']['value']) $outputf.= '<input type="hidden" value="'.$my['id']['value'].'" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">'.$my['id']['value'];
				else $outputf.= '('.zz_text('will_be_added_automatically').')&nbsp;';
				break;
			case 'predefined':
			case 'identifier':
			case 'hidden':
				$my_element = '<input type="hidden" value="%s" name="%s" id="%s">';
				$my_value = '';
				if (!empty($zz_var['where'][$table_name][$field['field_name']]))
					$my_value = $zz_var['where'][$table_name][$field['field_name']];
				elseif (!empty($field['value']))
					$my_value = $field['value'];
				elseif ($my['record'])
					$my_value = $my['record'][$field['field_name']];
				$outputf .= sprintf($my_element, $my_value, $field['f_field_name'], make_id_fieldname($field['f_field_name']));
				if ($my_value AND !empty($field['type_detail']) AND $field['type_detail'] == 'ipv4') {
					$outputf.= long2ip($my_value);
				} elseif ($my_value AND !empty($field['type_detail']) AND $field['type_detail'] == 'date') {
					$outputf.= datum_de($my_value);
				} elseif ($my_value AND !empty($field['type_detail']) AND $field['type_detail'] == 'select') {
					$detail_key = ($my_value ? $my_value : $field['default']);
					$my_fieldname = $field['field_name'];
					if (isset($field['key_field_name'])) $my_fieldname = $field['key_field_name'];
					if (isset($field['sql'])) {
						$mysql = zz_edit_sql($field['sql'], 'WHERE', '('.$my_fieldname.' = '.$detail_key.')');
						$result_detail = mysql_query($mysql);
						if ($zz_conf['modules']['debug']) 
							zz_debug(__FUNCTION__, $zz_debug_time_this_function, "fieldtype predefined", $mysql);
						if ($result_detail) {
							if (mysql_num_rows($result_detail) == 1) {
								$select_fields = mysql_fetch_assoc($result_detail);
								// remove hierarchy field for display
								if (!empty($field['show_hierarchy'])) {
									unset($select_fields[$field['show_hierarchy']]);
								}
								// remove ID (= first field) for display
								if (count($select_fields) > 1)
									array_shift($select_fields); 
								$outputf .= implode(' | ', $select_fields);
							}
						} else {
							$outputf.= zz_error($zz_error[] = array(
								'mysql' => mysql_error(), 
								'query' => $mysql, 
								'msg_dev' => zz_text('error-sql-incorrect'))
							);
						}
					} elseif (isset($field['enum'])) {
						$outputf .= $my_value;
					}
				} elseif ($my['record']) {
					if (isset($field['timestamp']) && $field['timestamp']) {
						$outputf.= timestamp2date($my_value);
					} elseif (isset($field['display_field'])) {
						if (!empty($my['record'][$field['display_field']]))
							$outputf.= htmlspecialchars($my['record'][$field['display_field']]);
						elseif (!empty($my['record_saved'][$field['display_field']]))
							$outputf.= htmlspecialchars($my['record_saved'][$field['display_field']]);
						else {
							if (empty($field['append_next']))
								if (!empty($field['value'])) $outputf.= $field['value'];
								else $outputf.= '('.zz_text('will_be_added_automatically').')';
						}
					} else {
						if (!empty($my_value)) {
							$outputf.= htmlspecialchars($my_value);
						} elseif (!empty($my['record_saved'][$field['field_name']])) {
							$outputf.= htmlspecialchars($my['record_saved'][$field['field_name']]);
						} else {
							if (empty($field['append_next']))
								if (!empty($field['value'])) $outputf.= $field['value'];
								else $outputf.= '('.zz_text('will_be_added_automatically').')';
						}
					}
				} else {
					if ($my_value) {
						if (!empty($field['type_detail']) && $field['type_detail'] == 'select')
							$outputf.= '('.zz_text('will_be_added_automatically').')&nbsp;';
						else
							$outputf.= $my_value;
					} else $outputf.= '('.zz_text('will_be_added_automatically').')&nbsp;';
				}
				break;
			case 'timestamp':
				$outputf.= '<input type="hidden" value="';
				if (!empty($field['value'])) $outputf.= $field['value'];
				elseif ($my['record']) $outputf.= $my['record'][$field['field_name']];
				$outputf.= '" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
				if (!empty($my['record'][$field['field_name']]))
					$outputf.= timestamp2date($my['record'][$field['field_name']]);
				else
					$outputf.= '('.zz_text('will_be_added_automatically').')&nbsp;';
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
				if (!$my['record'] AND isset($field['value'])) $outputf.= '('.zz_text('will_be_added_automatically').')&nbsp;';
				break;
			case 'foreign':
				$foreign_res = mysql_query($field['sql'].$my['id']['value']);
				if ($zz_conf['modules']['debug']) 
					zz_debug(__FUNCTION__, $zz_debug_time_this_function, "fieldtype foreign", $field['sql'].$my['id']['value']);
				//$outputf.= $field['sql'].$my['id']['value'];
				if ($foreign_res) {
					if (mysql_num_rows($foreign_res) > 0) {
						$my_output = false;
						while ($fline = mysql_fetch_row($foreign_res)) {
							if ($my_output) $outputf.= ', ';
							$my_output.= $fline[0]; // All Data in one Line! via SQL
						}
						if ($my_output) $outputf.= $my_output;
						else $outputf.= zz_text('no-data-available');
					} else {
						$outputf.= zz_text('no-data-available');
					}
				} 
				if (isset($field['add_foreign'])) {
					if ($my['id']['value'])
						$outputf.= ' <a href="'.$field['add_foreign'].$my['id']['value'].'&amp;referer='.urlencode($_SERVER['REQUEST_URI']).'">['.zz_text('edit').' &hellip;]</a>';
					else
						$outputf.= zz_text('edit-after-save');
				}
				break;
			case 'password':
				if ($row_display == 'form') {
					$outputf.= '<input autocomplete="off" type="password" name="'.$field['f_field_name']
						.'" id="'.make_id_fieldname($field['f_field_name']).'" size="'.$field['size'].'" ';
					if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
				}
				if ($my['record'])
					if ($row_display == 'form') $outputf.= 'value="'.$my['record'][$field['field_name']].'"';
					else $outputf .= '('.zz_text('hidden').')';
				if ($row_display == 'form') $outputf.= '>';
				if ($my['record'] && $row_display == 'form' && $action != 'insert') { $outputf .=
					'<input type="hidden" name="'.$field['f_field_name'].
					'--old" value="'.(!empty($my['record'][$field['field_name'].'--old']) ? $my['record'][$field['field_name'].'--old'] : $my['record'][$field['field_name']]).'">';
					// this is for validation purposes
					// take saved password (no matter if it's interefered with maliciously by user - worst case, pwd will be useless)
					// - if old and new value are identical
					// do not apply encryption to password
				}
				break;
			case 'password_change':
				if ($row_display == 'form') {
					$outputf.= '<table class="subtable">'."\n";
					$outputf.= '<tr><th><label for="'.make_id_fieldname($field['f_field_name']).'">'.zz_text('Old:').' </label></th><td><input type="password" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'" size="'.$field['size'].'" ';
					if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
					$outputf.= '></td></tr>'."\n";
					$outputf.= '<tr><th><label for="'.make_id_fieldname($field['f_field_name'].'_new_1').'">'.zz_text('New:').' </label></th><td><input type="password" name="'.$field['f_field_name'].'_new_1" id="'.make_id_fieldname($field['f_field_name'].'_new_1').'" size="'.$field['size'].'" ';
					if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
					$outputf.= '></td></tr>'."\n";
					$outputf.= '<tr><th><label for="'.make_id_fieldname($field['f_field_name'].'_new_2').'">'.zz_text('New:').' </label></th><td><input type="password" name="'.$field['f_field_name'].'_new_2" id="'.make_id_fieldname($field['f_field_name'].'_new_2').'" size="'.$field['size'].'" ';
					if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
					$outputf.= '><p>'.zz_text('(Please confirm your new password twice)').'</td></tr>'."\n";
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
					if ($field['type'] == 'url' AND strlen($my['record'][$field['field_name']]) > $zz_conf_record['max_select_val_len'] AND $row_display != 'form')
						$outputf.= htmlspecialchars(substr($my['record'][$field['field_name']], 0, $zz_conf_record['max_select_val_len'])).'...';
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
							if ($which == 'dms') $outputf.= zz_text('N/A'); // display it only once!
					}
					//	DD
					if ($row_display == 'form') {
						$myid = make_id_fieldname($field['field_name'].'_dec', 'radio');
						$outputf.= '<label for="'.$myid.'"><input type="radio" id="'.$myid.'" name="'.$field['f_field_name'].'[which]" value="dec" '.($w_checked == 'dec' ? $checked: '').'> '.zz_text('dec').'&nbsp; </label></span>';
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
					$my_value = ($my['record'] ? htmlchars($my['record'][$field['field_name']]) : '');
					if ($row_display == 'form') {
						$my_element = '<input type="text" name="%s" id="%s" size="%s" value="%s">';
						$outputf .= sprintf($my_element, $field['f_field_name'], make_id_fieldname($field['f_field_name']), $field['size'], $my_value);
					} else {
						$outputf .= $my_value;
					}
				}
				break;
			case 'date':
				$my_value = ($my['record'] ? datum_de($my['record'][$field['field_name']]) : '');
				if ($row_display == 'form') {
					$my_element = '<input type="text" name="%s" id="%s" size="12" value="%s">';
					$outputf .= sprintf($my_element, $field['f_field_name'], make_id_fieldname($field['f_field_name']), $my_value);
				} else {
					$outputf .= $my_value;
				}
				break;
			case 'memo':
				$field['cols'] = (!empty($field['cols']) ? $field['cols'] : 60);
				$field['rows'] = (!empty($field['rows']) ? $field['rows'] : 8);
				if ($my['record']) {
					$memotext = $my['record'][$field['field_name']];
					$calculated_rows = 2; // always add two extra lines
					$factor = 1.01; // factor for long text to get extra lines because of long words at line breaks
					$parts = explode("\n", $memotext);
					foreach ($parts as $part) {
						if (strlen($part) < $field['cols']+2) $calculated_rows++;
						else $calculated_rows += ceil(strlen($part)/$field['cols']*$factor); 
					}
					if ($calculated_rows >= $field['rows']) $field['rows'] = $calculated_rows;
					if (!empty($field['rows_max']) AND ($field['rows'] > $field['rows_max']))
						$field['rows'] = $field['rows_max'];
					$memotext = htmlspecialchars($memotext);
				}
				if ($row_display == 'form') $outputf.= '<textarea rows="'
					.$field['rows'].'" cols="'.$field['cols'].'" name="'
					.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
				if ($my['record']) {
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
						$my_where = array();
						$add_details_where = ''; // for add_details
						foreach ($field['sql_where'] as $sql_where) {
							// might be several where-clauses
							if (isset($sql_where[2])) {
								foreach (array_keys($zz_var['where'][$table_name]) as $value_key)
									if ($value_key == $sql_where[1]) $sql_where[2].= $zz_var['where'][$table_name][$value_key];
								$result_detail = mysql_query($sql_where[2]);
								if ($zz_conf['modules']['debug']) 
									zz_debug(__FUNCTION__, $zz_debug_time_this_function, "fieldtype select[where]", $sql_where[2]);
								if ($result_detail) {
									//if (mysql_num_rows($result_detail) == 1)
									// might be that there are more results, so that should not be a problem
										$index = mysql_result($result_detail, 0, 0);
									//else $outputf.= $sql_where[2];
								} else {
									$outputf.= zz_error($zz_error[] = array(
										'mysql' => mysql_error(), 
										'query' => $sql_where[2], 
										'msg_dev' => zz_text('error-sql-incorrect')));
								}
								$my_where[] = $sql_where[0]." = '".$index."'";
								$add_details_where .= '&amp;where['.$sql_where[0].']='.$index;
							} elseif (isset($sql_where['where']) AND !empty($zz_var['where'][$table_name][$sql_where['field_name']])) {
								$my_where[] = sprintf($sql_where['where'], $zz_var['where'][$table_name][$sql_where['field_name']]);
							}
						}
						$field['sql'] = zz_edit_sql($field['sql'], 'WHERE', implode(' AND ', $my_where));
					}
					$result_detail = mysql_query($field['sql']);
					if ($zz_conf['modules']['debug']) 
						zz_debug(__FUNCTION__, $zz_debug_time_this_function, "fieldtype select", $field['sql']);
					if (!$result_detail) {
						$outputf.= zz_error($zz_error[] = array(
							'mysql' => mysql_error(), 
							'query' => $field['sql'], 
							'msg_dev' => zz_text('error-sql-incorrect'))
						);
					} elseif ($row_display == 'form' && mysql_num_rows($result_detail) == 1 && !zz_check_for_null($field['field_name'], $my_tab[$i]['table'])) {
						// there is only one result in the array, and this will be pre-selected because FIELD must not be NULL
						$line = mysql_fetch_assoc($result_detail);
						// get ID field_name which must be 1st field in SQL query
						$id_field_name = mysql_field_name($result_detail, 0);
						if ($my['record'] && $line[$id_field_name] != $my['record'][$field['field_name']]) 
							$outputf .= 'Possible Values: '.$line[$id_field_name].' -- Current Value: '
								.htmlspecialchars($my['record'][$field['field_name']]).' -- Error --<br>'.zz_text('no_selection_possible');
						else {
							$outputf.= '<input type="hidden" value="'.$line[$id_field_name].'" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
							$outputf.= zz_draw_select($line, $id_field_name, $my['record'], $field, false, 0, false, false, $zz_conf_record);
						}
					} elseif (mysql_num_rows($result_detail)) {
						$id_field_name = mysql_field_name($result_detail, 0);
						$details = array();
						$count_rows = mysql_num_rows($result_detail);
						$detail_record = array();
						while ($line = mysql_fetch_assoc($result_detail)) {
							if (!empty($my['record'][$field['field_name']]) 
								AND $line[$id_field_name] == $my['record'][$field['field_name']])
								$detail_record = $line;
							// fill $details only if needed, otherwise this will need a lot of memory usage
							if ($count_rows <= $zz_conf_record['max_select'] 
								OR !empty($field['show_hierarchy_subtree'])) 
								$details[$line[$id_field_name]] = $line;
						}
						if ($row_display == 'form') {
							$my_select = false;
							$my_h_field = false;
							$show_hierarchy_subtree = 'NULL';
							// get ID field_name which must be 1st field in SQL query
							if (!empty($field['show_hierarchy'])) {
								foreach ($details as $line) {
									// fill in values, index NULL is for uppermost level
									$my_select[(!empty($line[$field['show_hierarchy']]) ? $line[$field['show_hierarchy']] : 'NULL')][] = $line;
								}
								if (!empty($field['show_hierarchy_subtree'])) {
									$show_hierarchy_subtree = $field['show_hierarchy_subtree'];
									// count fields in subhierarchy, should be less than existing $count_rows
									$count_rows = zz_count_records($my_select, $show_hierarchy_subtree, $id_field_name);
								}
							}
							if ($count_rows > $zz_conf_record['max_select']) {
								// don't show select but text input instead
								$textinput = true;
								if ($my['record'] AND $detail_record) {
									$outputf.= zz_draw_select($detail_record, $id_field_name, $my['record'], $field, false, 0, false, 'reselect', $zz_conf_record);
									$textinput = false;
								}
								// value will not be checked if one detail record is added because in this case validation procedure will be skipped!
								if (!empty($my['record'][$field['field_name']])) $value = htmlspecialchars($my['record'][$field['field_name']]); 
								else $value = '';
								
								if ($textinput) // add new record
									$outputf.= '<input type="text" size="'.(!empty($field['size_select_too_long']) ? $field['size_select_too_long'] : 32)
										.'" value="'.$value.'" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
								$outputf.= '<input type="hidden" value="'.$field['f_field_name'].'" name="zz_check_select[]">';
							} else {
								$outputf.= '<select name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">'."\n";
								$outputf.= '<option value="'
									.((!empty($field['show_hierarchy_subtree']) AND !empty($field['show_hierarchy_use_top_value_instead_NULL'])) 
										? $field['show_hierarchy_subtree'] : '') // normally don't show a value, unless we only look at a part of a hierarchy
									.'"';
								if ($my['record']) if (!$my['record'][$field['field_name']]) $outputf.= ' selected';
								$outputf.= '>'.zz_text('none_selected').'</option>'."\n";
								if (empty($field['show_hierarchy']) AND empty($field['group'])) {
									foreach ($details as $line)
										$outputf.= zz_draw_select($line, $id_field_name, $my['record'], $field, false, 0, false, 'form', $zz_conf_record);
								} elseif (!empty($field['show_hierarchy']) && !empty($my_select[$show_hierarchy_subtree]) AND !empty($field['group'])) {
									// optgroup
									$optgroup = false;
									foreach ($my_select[$show_hierarchy_subtree] as $line) {
										if ($optgroup != $line[$field['group']]) {
											if (!$optgroup) $outputf .= '</optgroup>'."\n";
											$optgroup = $line[$field['group']];
											$outputf .= '<optgroup label="'.$optgroup.'">'."\n";
										}
										unset($line[$field['group']]); // not needed anymore
										$outputf.= zz_draw_select($line, $id_field_name, $my['record'], $field, $my_select, 1, $field['show_hierarchy'], 'form', $zz_conf_record);
									}
									$outputf .= '</optgroup>'."\n";
								} elseif (!empty($field['show_hierarchy']) && !empty($my_select[$show_hierarchy_subtree])) {
									foreach ($my_select[$show_hierarchy_subtree] AS $line)
										$outputf.= zz_draw_select($line, $id_field_name, $my['record'], $field, $my_select, 0, $field['show_hierarchy'], 'form', $zz_conf_record);
								} elseif (!empty($field['group'])) {
									// optgroup
									$optgroup = false;
									foreach ($details as $line) {
										if ($optgroup != $line[$field['group']]) {
											if (!$optgroup) $outputf .= '</optgroup>'."\n";
											$optgroup = $line[$field['group']];
											$outputf .= '<optgroup label="'.$optgroup.'">'."\n";
										}
										unset($line[$field['group']]); // not needed anymore
										$outputf.= zz_draw_select($line, $id_field_name, $my['record'], $field, false, 1, false, 'form', $zz_conf_record);
									}
									$outputf .= '</optgroup>'."\n";
								} elseif ($detail_record) { // re-edit record, something was posted, ignore hierarchy because there's only one record coming back
									$outputf.= zz_draw_select($detail_record, $id_field_name, $my['record'], $field, false, 0, false, 'form', $zz_conf_record);
								} elseif (!empty($field['show_hierarchy'])) {
									$zz_error[] = array(
										'msg' => 'No selection possible',
										'msg_dev' => 'Configuration error: "show_hierarchy" used but there is no highest level in the hierarchy.',
										'level' => E_USER_WARNING
									);
								}
								$outputf.= '</select>'."\n";
								if (!empty($zz_error)) $outputf.= zz_error();
							}
						} else {
							if ($detail_record) {
								$outputf.= zz_draw_select($detail_record, $id_field_name, $my['record'], $field, false, 0, false, false, $zz_conf_record);
							}
						}
					} else {
						$outputf.= '<input type="hidden" value="" name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
						$outputf.= zz_text('no_selection_possible');
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
					$outputf .= $myvalue;
				} elseif (isset($field['enum'])) {
					$myi = 0;
					$sel_option = (count($field['enum']) <=2 ? true : (!empty($field['show_values_as_list']) ? true : false));
					if ($row_display == 'form') {
						if ($sel_option) {
							if (!isset($field['hide_novalue'])) $field['hide_novalue'] = true;
							$myid = make_id_fieldname($field['f_field_name']).'-'.$myi;
							$outputf.= '<label for="'.$myid.'"'
								.($field['hide_novalue'] ? ' class="hidden"' : '')
								.'><input type="radio" id="'.$myid.'" name="'
								.$field['f_field_name'].'" value=""';
							if ($my['record']) { if (!$my['record'][$field['field_name']]) $outputf.= ' checked'; }
							else $outputf.= ' checked'; // no value, no default value (both would be written in my record fieldname)
							$outputf.= '>'.zz_text('no_selection').'</label>';
							if (!empty($field['show_values_as_list'])) $outputf .= "\n".'<ul class="zz_radio_list">'."\n";
						} else {
							$outputf.= '<select name="'.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">'."\n";
							$outputf.= '<option value=""';
							if ($my['record']) { if (!$my['record'][$field['field_name']]) $outputf.= ' selected'; }
							else $outputf.= ' selected'; // no value, no default value (both would be written in my record fieldname)
							$outputf.= '>'.zz_text('none_selected').'</option>'."\n";
						} 
					}
					foreach ($field['enum'] as $key => $set) {
						if ($row_display == 'form') {
							if ($sel_option) {
								$myi++;
								$myid = make_id_fieldname($field['f_field_name']).'-'.$myi;
								if (!empty($field['show_values_as_list'])) $outputf .= '<li>';
								$outputf.= ' <label for="'.$myid.'"><input type="radio" id="'.$myid.'" name="'.$field['f_field_name'].'" value="'.$set.'"';
								if ($my['record']) if ($set == $my['record'][$field['field_name']]) $outputf.= ' checked';
								$outputf.= '> '.(!empty($field['enum_title'][$key]) ? $field['enum_title'][$key] : zz_text($set)).'</label>';
								if (!empty($field['show_values_as_list'])) $outputf .= '</li>'."\n";
							} else {
								$outputf.= '<option value="'.$set.'"';
								if ($my['record']) if ($set == $my['record'][$field['field_name']]) $outputf.= ' selected';
								$outputf.= '>';
								$outputf.= (!empty($field['enum_title'][$key]) ? $field['enum_title'][$key] : zz_text($set));
								$outputf.= '</option>'."\n";
							}
						} else {
							if ($set == $my['record'][$field['field_name']]) $outputf.= (!empty($field['enum_title'][$key]) ? $field['enum_title'][$key] : zz_text($set));
						}
					}
					if (!empty($field['show_values_as_list'])) {
						if (empty($field['append_next']) && $row_display == 'form')
							$outputf .= '</ul>'."\n";
						else $append_next_type = 'list';
					}
					if ($row_display == 'form' && !$sel_option) $outputf.= '</select>'."\n";
				} else {
					$outputf.= zz_text('no_source_defined').'. '.zz_text('no_selection_possible');
				}
				break;
			case 'image':
			case 'upload_image':
				if (($mode != 'add' OR $field['type'] != 'upload_image')
					AND (empty($field['dont_show_image'])) || !$field['dont_show_image']) {
					$img = false;
					$outputf.= '<p>';
					if (isset($field['path']))
						$outputf .= $img = zz_show_image($field['path'], $my['record']);
					if (!$img) $outputf.= '('.zz_text('image_not_display').')';
					$outputf.= '</p>';
				}
				if (($mode == 'add' OR $mode == 'edit') && $field['type'] == 'upload_image') {
					if (!isset($field['image'])) {
						$outputf.= zz_error($zz_error[] = array(
							'msg' => 'Image upload is currently not possible. '.zz_text('An error occured. We are working on the solution of this problem. Sorry for your inconvenience. Please try again later.'),
							'msg_dev' => 'Configuration error. Missing upload_image details.',
							'level' => E_USER_WARNING
						));
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
								$outputf .= '<input type="file" name="'.make_id_fieldname($field['f_field_name']).'['.$image['field_name'].']">';
								if (empty($field['dont_show_file_link']) AND $link = zz_show_link($image['path'], (isset($my['record_saved']) ? $my['record_saved'] : $my['record'])))
									$outputf .= '<br><a href="'.$link.'">'.$link
										.'</a>'
										.(($image_uploads > 1 OR !empty($field['optional_image'])) ?
										'(<small><label for="delete-file-'.$fieldkey.'-'.$imagekey
										.'"><input type="checkbox" name="zz_delete_file['.$fieldkey.'-'.$imagekey
										.']" id="delete-file-'.$fieldkey.'-'.$imagekey.'"> '
										.zz_text('Delete this file').'</label></small>)'
										: '');
								if (!empty($my['images'][$fieldkey][$imagekey]['error']))
									$outputf.= '<br><small>'.implode('<br>', $my['images'][$fieldkey][$imagekey]['error']).'</small>';
								else
									$outputf.= '<br><small>'.zz_text('Maximum allowed filesize is').' '
										.floor($zz_conf['upload_MAX_FILE_SIZE']/1024/1024).'MiB</small>';
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
								if ($link = zz_show_link($image['path'], $my['record'])) {
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
					$val_to_insert = '';
					if (isset($field['display_field'])) {
						if (!empty($my['record'][$field['display_field']])) {
							$val_to_insert = $my['record'][$field['display_field']];
						} elseif (!empty($my['record_saved'][$field['display_field']])) { // empty for new record
							$val_to_insert = $my['record_saved'][$field['display_field']]; // requery
						}
						if ($val_to_insert) {
							if (!empty($field['translate_field_value']))
								$val_to_insert = zz_text($val_to_insert);
							$outputf .= $val_to_insert;
						}
					} elseif (isset($field['field_name'])) {
						if (!empty($my['record'][$field['field_name']]))
							$val_to_insert = $my['record'][$field['field_name']];
						elseif (!empty($my['record_saved'][$field['field_name']])) // empty if new record!
							$val_to_insert = $my['record_saved'][$field['field_name']];
						if (!empty($field['display_title']) && in_array($val_to_insert, array_keys($field['display_title'])))
							$val_to_insert = $field['display_title'][$val_to_insert];
						if (!empty($field['translate_field_value']))
							$val_to_insert = zz_text($val_to_insert);
						$outputf .= htmlspecialchars($val_to_insert);
					} else
						$outputf .= '<span class="error">'.zz_text('Script configuration error. No display field set.').'</span>'; // debug!
				} else $outputf .= zz_text('N/A');
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
				} else $outputf.= '('.zz_text('calculated_field').')';
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
			if (!isset($add_details_where)) $add_details_where = false;
			if ($mode && $mode != 'delete' && $mode != 'show' && $mode != 'review' AND $mode != 'list_only')
				if (isset($field['add_details'])) {
					$add_details_sep = (strstr($field['add_details'], '?') ? '&amp;' : '?');
					$outputf.= ' <a href="'.$field['add_details'].$add_details_sep
						.'mode=add&amp;referer='.urlencode($_SERVER['REQUEST_URI'])
						.$add_details_where.'"'
						.(!empty($field['add_details_target']) ? ' target="'.$field['add_details_target'].'"' : '')
						.' id="zz_add_details_'.$i.'_'.$k.'_'.$fieldkey.'">['.zz_text('new').' &hellip;]</a>';
				}
			if ($outputf && $outputf != ' ') {
				if (isset($field['prefix'])) $out['td']['content'].= $field['prefix'];
				$out['td']['content'].= $outputf;
				if (isset($field['suffix'])) $out['td']['content'].= $field['suffix'];
				$out['td']['content'].= ' ';
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
		if (!$zz_var['class_add'] AND !$zz_var['horizontal_table_head']) { 
			// just first detail record with values: show head
			$output .= '<tr>'."\n";
			foreach ($matrix as $row) { 
				$output .= '<th'.zz_show_class(array_merge($row['th']['attr'], $row['tr']['attr']))
					.'>'.$row['th']['content'].'</th>'."\n";
			}
			if ($extra_lastcol) $output .= '<th>&nbsp;</th>';
			$output .= '</tr>'."\n";
			$zz_var['horizontal_table_head'] = true;
		}
		$output .= '<tr'.($zz_var['class_add'] ? ' class="'.$zz_var['class_add'].'"' : '').'>';
		foreach ($matrix as $row) {
			$output .= '<td'.zz_show_class(array_merge($row['td']['attr'], $row['tr']['attr']))
				.'>'.$row['td']['content'].'</td>'."\n";
		}
		if ($extra_lastcol) $output .= '<td>'.$extra_lastcol.'</td>';
		$output .= '</tr>'."\n";
	}
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
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

function zz_count_records($select, $subtree, $id_field_name) {
	$records = 0;
	foreach ($select[$subtree] AS $field) {
		$records++;
		if (!empty($select[$field[$id_field_name]])) {
			$records += zz_count_records($select, $field[$id_field_name], $id_field_name);
		}
	}
	return $records;
}

?>