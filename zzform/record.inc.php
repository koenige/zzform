<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2004-2010
// display of single record as a html form+table or for review as a table


/*
	function zz_display_records
		add, edit, delete, review a record
	function zz_show_field_rows
		will be called from zz_display_records
		shows all table rows for given record
*/

/**
 * HTML output of a single record and its detail recors, inside of a FORM with
 * input elements or only for display
 *
 * @param array $ops
 *		'output', 'mode', 'result'
 * @param array $zz_tab
 * @param array $zz_var
 *		'upload_form', 'integrity', 'action'
 * @param array $zz_conditions
 * @global array $zz_conf
 *		'url_self', 'url_self_qs_base', 'url_append', 'character_set'
 * @global array $zz_error
 * @return string $output
 */
function zz_record($ops, $zz_tab, $zz_var, $zz_conditions) {
	global $zz_conf;
	global $zz_error;

	$formhead = false;
	$action_before_redirect = !empty($_GET['zzaction']) ? $_GET['zzaction'] : '';
	if ($zz_var['record_action'] OR $action_before_redirect) {
		if ($zz_var['action'] == 'insert' OR $action_before_redirect == 'insert') {
			$formhead = zz_text('record_was_inserted');
		} elseif (($zz_var['action'] == 'update' AND $ops['result'] == 'successful_update')
			OR $action_before_redirect == 'update') {
			$formhead = zz_text('record_was_updated');
		} elseif ($zz_var['action'] == 'delete' OR $action_before_redirect == 'delete') {
			$formhead = zz_text('record_was_deleted');
		} elseif (($zz_var['action'] == 'update' AND $ops['result'] == 'no_update')
			OR $action_before_redirect == 'noupdate') {
			$formhead = zz_text('Record was not updated (no changes were made)');
		}
	}

	// open HTML form element
	// in these cases, no form element will be shown

	$output = '';
	$record_form = array('edit', 'delete', 'add');
	// Variable to correctly close form markup in case of error
	$form_open = false;
	$div_record_open = false;
	if (in_array($ops['mode'], $record_form)) {
		$form_open = true;
		$output.= '<form action="'.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'];
		// without first &amp;!
		if ($zz_var['extraGET']) 
			$output .= $zz_conf['int']['url']['?&'].substr($zz_var['extraGET'], 5); 
		$output .= '" method="POST"';
		if (!empty($zz_var['upload_form'])) 
			$output .= ' enctype="multipart/form-data"';
		$output .= ' accept-charset="'.$zz_conf['character_set'].'">';
	}

	// Heading inside HTML form element
	if (($ops['mode'] == 'edit' OR $ops['mode'] == 'delete' OR $ops['mode'] == 'review'
		OR $ops['mode'] == 'show') AND !$zz_tab[0][0]['record']
		AND ($action_before_redirect != 'delete')) {
		$formhead = '<span class="error">'.zz_text('There is no record under this ID:')
			.' '.htmlspecialchars($zz_tab[0][0]['id']['value']).'</span>';	
	} elseif (!empty($zz_var['integrity'])) {
		$formhead = zz_text('Warning!');
		$tmp_error_msg = 
			zz_text('This record could not be deleted because there are details about this record in other records.')
			.' '.$zz_var['integrity']['text']."\n";

		if (isset($zz_var['integrity']['fields'])) {
			$tmp_error_msg .= '<ul>'."\n";
			foreach ($zz_var['integrity']['fields'] as $del_tab) {
				$tmp_error_msg .= '<li>'.zz_nice_tablenames($del_tab).'</li>'."\n";
			}
			$tmp_error_msg .= '</ul>'."\n";
		} 
		$zz_error[]['msg'] = $tmp_error_msg;
	} elseif (in_array($ops['mode'], $record_form) OR 
		($ops['mode'] == 'show' AND !$action_before_redirect)) {
	//	mode = add | edit | delete: show form
		$formhead = zz_text($ops['mode']).' '.zz_text('a_record');
	} elseif ($zz_var['action'] OR $action_before_redirect) {	
	//	action = insert update review: show form with new values
		if (!$formhead) {
			$formhead = ucfirst(zz_text($zz_var['action']).' '.zz_text('failed'));
		}
	} elseif ($ops['mode'] == 'review') {
		$formhead = zz_text('show_record');
	}
	if ($formhead) {
		$output .= '<div id="record">'."\n<h2>".ucfirst($formhead)."</h2>\n\n";
		$div_record_open = true;
	}

	// output error messages to the user
	if (!empty($zz_error['validation']['msg']) AND is_array($zz_error['validation']['msg'])) {
		// user error message, visible to everyone
		// line breaks \n important for mailing errors
		$this_error['msg'] = '<p>'.zz_text('Following_errors_occured').': </p>'."\n".'<ul><li>'
			.implode(".</li>\n<li>", $zz_error['validation']['msg']).'.</li></ul>';
		// if we got wrong values entered, put this into a developer message
		if (!empty($zz_error['validation']['incorrect_values'])) {
			foreach ($zz_error['validation']['incorrect_values'] as $incorrect_value) {
				$this_dev_msg[] = zz_text('Field name').': '.$incorrect_value['field_name']
					.' / '.htmlspecialchars($incorrect_value['msg']);
			}
			$this_error['msg_dev'] = "\n\n".implode("\n", $this_dev_msg);
		}
		$zz_error[] = $this_error;
		unset($this_error);
	}
	unset ($zz_error['validation']);
	$error = zz_error();
	if ($error) {
		if (!$div_record_open) {
			$output .= '<div id="record">';
			$div_record_open = true;
		}
		$output .= zz_error_output();
	}

	// set display of record (review, form, not at all)

	if ($ops['mode'] == 'delete' OR $ops['mode'] == 'show') {
		$display_form = 'review';
	} elseif (in_array($ops['mode'], $record_form)) {
		$display_form = 'form';
	} elseif ($zz_var['action'] == 'delete') {
		$display_form = false;
	} elseif ($zz_var['action'] AND $formhead) {
		$display_form = 'review';
	} elseif ($zz_var['action']) {
		$display_form = false;
	} elseif ($ops['mode'] == 'review') {
		$display_form = 'review';
	} else
		$display_form = false;
	if (($ops['mode'] == 'edit' OR $ops['mode'] == 'delete' OR $ops['mode'] == 'review'
		OR $ops['mode'] == 'show') 
		AND !$zz_tab[0][0]['record']) {
		$display_form = false;
	}

	if ($display_form) {
		if (!$div_record_open) {
			$output .= '<div id="record">';
			$div_record_open = true;
		}
		// output form if necessary
		$output .= zz_display_records($ops['mode'], $zz_tab, $display_form, $zz_var, $zz_conditions);
	}

	// close HTML form element

	if ($div_record_open) $output.= "</div>\n";
	if ($form_open) $output.= "</form>\n";
	return $output;
}


/**
 * Display form to edit a record
 * 
 * @param string $mode
 * @param array $zz_tab		
 * @param string $display	'review': show form with all values for
 *							review; 'form': show form for editing; 
 * @param array $zz_var
 * @param array $zz_conditions
 * @global array $zz_conf
 * @global array $zz_error
 * @return string $string			HTML-Output with all form fields
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_display_records($mode, $zz_tab, $display, $zz_var, $zz_conditions) {
	global $zz_conf;
	global $zz_error;
	
	if (!$display) return false;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$output = '';

	// there is a form to display
	$zz_conf_record = zz_record_conf($zz_conf);
	// check conditions
	if (!empty($zz_conf_record['conditions']) AND !empty($zz_conditions['bool']))
		$zz_conf_record = zz_conditions_merge($zz_conf_record, $zz_conditions['bool'], $zz_var['id']['value'], false, 'conf');

	if (($mode == 'add' OR $mode == 'edit') && !empty($zz_conf['upload_MAX_FILE_SIZE'])
		AND !empty($zz_var['upload_form'])) 
		$output.= '<input type="hidden" name="MAX_FILE_SIZE" value="'.$zz_conf['upload_MAX_FILE_SIZE'].'">'."\n";
	$output.= '<table>'."\n";

	$cancelurl = $zz_conf['int']['url']['self'];
	if ($base_qs = $zz_conf['int']['url']['qs'].$zz_conf['int']['url']['qs_zzform']) {
		$unwanted_keys = array('mode', 'id', 'add', 'zzaction', 'zzhash');
		$cancelurl.= zz_edit_query_string($base_qs, $unwanted_keys);
	}
	if ($mode && $mode != 'review' && $mode != 'show') {
		$output.= '<tfoot>'."\n";
		$output.= '<tr><th>&nbsp;</th> <td><input type="submit" value="';
		$accesskey = 's';
		if		($mode == 'edit') 	$output.= zz_text('update_to').' ';
		elseif	($mode == 'delete')	$output.= zz_text('delete_from').' ';
		else 								$output.= zz_text('add_to').' ';
		if ($mode == 'delete') $accesskey = 'd';
		$output.= zz_text('database').'" accesskey="'.$accesskey.'">';
		if (($cancelurl != $_SERVER['REQUEST_URI'] OR ($zz_var['action']))
			AND $zz_conf_record['cancel_link']) 
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
				if (empty($zz_conf_record['no_ok']))
					$output.= '<a href="'.$cancelurl.'">'.zz_text('OK').'</a> | ';
				$id_link = '&amp;id='.$zz_var['id']['value'];
				if (!empty($zz_var['where_with_unique_id'])) $id_link = '';
				$edit_link = 'mode=edit'.$id_link.$zz_var['extraGET'];
				if ($zz_conf['access'] === 'show_after_edit')
					$edit_link = substr($zz_var['extraGET'], 5); // remove &amp;
				$output .= '<a href="'.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
					.$zz_conf['int']['url']['?&'].$edit_link.'">'.zz_text('edit').'</a>';
				if ($zz_conf_record['delete']) $output.= ' | <a href="'
					.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
					.$zz_conf['int']['url']['?&'].'mode=delete'.$id_link
					.$zz_var['extraGET'].'">'.zz_text('delete').'</a>';
				if ($zz_conf_record['copy']) $output.= ' | <a href="'
					.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
					.$zz_conf['int']['url']['?&'].'mode=add&amp;source_id='
					.$zz_var['id']['value'].$zz_var['extraGET'].'">'
					.zz_text('Copy').'</a>';
				$output.= '</td></tr>'."\n";
			}
			if (!empty($zz_conf_record['details'])) {
				$output.= '<tr><th>&nbsp;</th><td class="editbutton">'
					.zz_show_more_actions($zz_conf_record['details'], 
					$zz_conf_record['details_url'], $zz_conf_record['details_base'], 
					$zz_conf_record['details_target'], $zz_conf_record['details_referer'],
					$zz_conf_record['details_sql'], $zz_var['id']['value'], 
					(!empty($zz_tab[0][0]['POST']) ? $zz_tab[0][0]['POST'] : false))
					.'</td></tr>'."\n";
			}
			if (empty($zz_conf_record['details']) AND !$zz_conf_record['edit']
				AND $zz_conf_record['cancel_link']) {
				$output.= '<tr><th>&nbsp;</th><td class="editbutton">'
					.' <a href="'.$cancelurl.'">'.zz_text('Cancel').'</a>'
					.'</td></tr>'."\n";
			}			
			$output.= '</tfoot>'."\n";
		}
	}
	$output.= zz_show_field_rows($zz_tab, 0, 0, $mode, $display, $zz_var, $zz_conf_record);
	if ($zz_error['error']) return zz_return(false);
	$output.= '</table>'."\n";
	if ($mode == 'delete') $output.= '<input type="hidden" name="'
		.$zz_var['id']['field_name'].'" value="'.$zz_var['id']['value'].'">'."\n";
	if ($mode && $mode != 'review' && $mode != 'show') {
		switch ($mode) {
			case 'add': $submit = 'insert'; break;
			case 'edit': $submit = 'update'; break;
			case 'delete': $submit = 'delete'; break;
		}
		$output.= '<input type="hidden" name="zz_action" value="'.$submit.'">';
		if ($zz_conf['referer']) $output.= '<input type="hidden" value="'
			.$zz_conf['referer'].'" name="zz_referer">';
		if (isset($_GET['file']) && $_GET['file']) 
			$output.= '<input type="hidden" value="'.htmlspecialchars($_GET['file'])
				.'" name="file">';
	}
	if ($display == 'form') {
		foreach ($zz_tab as $tab => $my_tab) {
			if (empty($my_tab['subtable_deleted'])) continue;
			foreach ($my_tab['subtable_deleted'] as $deleted_id)
				$output.= '<input type="hidden" name="zz_subtable_deleted['
				.$my_tab['table_name'].'][]['.$my_tab['id_field_name']
				.']" value="'.$deleted_id.'">';
		}
	}
	return zz_return($output);
}

/**
 * HTML output of all field rows
 *
 * @param array $zz_ab
 * @param int $tab
 * @param int $rec
 * @param string $mode
 * @param array $zz_var 
 *		function calls itself and uses 'horizontal_table_head', 'class_add'
 *		internally, therefore &$zz_var
 * @param array $zz_conf_record
 * @param string $formdisplay (optional)
 * @param string $extra_lastcol (optional)
 * @param int $table_count (optional)
 * @param bool $show_explanation (optional)
 * @return string HTML output
 */
function zz_show_field_rows($zz_tab, $tab, $rec, $mode, $display, &$zz_var, 
	$zz_conf_record, $formdisplay = 'vertical', $extra_lastcol = false, 
	$table_count = 0, $show_explanation = true) {

	global $zz_error;
	global $zz_conf;	// Config variables
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$output = '';
	$append_next = '';
	$append_next_type = '';
	$append_explanation = array();
	$matrix = array();
	$my_rec = $zz_tab[$tab][$rec];
	$firstrow = true;
	$my_where_fields = (isset($zz_var['where'][$zz_tab[$tab]['table_name']])
		? $zz_var['where'][$zz_tab[$tab]['table_name']] : array());
	$row_display = ($my_rec['access'] ? $my_rec['access'] : $display); // this is for 0 0 main record

	// check if there's a filter with a field_name 
	// this field will get the filter value as default value
	$filter_field_name = array();
	$unwanted_filter_values = array('NULL', '!NULL');
	if (!empty($_GET['filter'])) {
		foreach (array_keys($_GET['filter']) AS $filter_identifier) {
			foreach ($zz_conf['filter'] as $filter) {
				if ($filter['identifier'] == $filter_identifier
					AND !empty($filter['field_name']) 
					AND !in_array($_GET['filter'][$filter_identifier], $unwanted_filter_values))
				{
					$filter_field_name[$filter_identifier] = $filter['field_name'];
				}
			}
		}
	}
	
	if (!empty($my_rec['fields'])) foreach ($my_rec['fields'] as $fieldkey => $field) {
		if (!$field) continue;
		if (!empty($field['hide_in_form'])) continue;
		if (!empty($field['hide_in_form_add']) 
			AND empty($zz_tab[$sub_tab][$sub_rec]['id']['value'])) continue;

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
		if (!empty($zz_tab[$tab]['values'][$table_count][$fieldkey])) {
			$field['value'] = $zz_tab[$tab]['values'][$table_count][$fieldkey];
			if ($field['type'] == 'select') {
				$field['type_detail'] = $field['type'];
				$field['type'] = 'predefined';
			}
		}
		
		// $tab means subtable, since main table has $tab = 0
		if ($tab) $field['f_field_name'] = $zz_tab[$tab]['table_name'].'['.$rec.']['.$field['field_name'].']';
		elseif (isset($field['field_name'])) $field['f_field_name'] = $field['field_name'];
		if (!empty($field['format']) AND empty($field['hide_format_in_title_desc'])) { 
			// formatted fields: show that they are being formatted!
			if (!isset($field['title_desc'])) $field['title_desc'] = '';
			$field['title_desc'] .= ' ['.(!empty($zz_conf['format'][$field['format']]['link']) 
				? '<a href="'.$zz_conf['format'][$field['format']]['link'].'" target="help">' : '')
				.(ucfirst($field['format']))
				.(!empty($zz_conf['format'][$field['format']]['link']) ? '</a>' : '').']';
		}
		if ($field['type'] == 'subtable') {
			$sub_tab = $field['subtable'];
			if (empty($field['title_button'])) $field['title_button'] = strip_tags($field['title']); 
			if (empty($field['form_display'])) $field['form_display'] = 'vertical';
//	Subtable
			$st_display = (!empty($field['access']) ? $field['access'] : $display);
			$out['tr']['attr'][] = (!empty($field['class']) ? $field['class'] : '');
			$out['th']['attr'][] = 'sub-add';
			if (!(isset($field['show_title']) AND !$field['show_title']))
				$out['th']['content'] .= $field['title'];
			if (!empty($field['title_desc']) && $st_display == 'form') 
				$out['th']['content'].= '<p class="desc">'.$field['title_desc'].'</p>';
			$out['td']['attr'][] = 'subtable';
			if ($st_display == 'form' && !empty($field['explanation_top']) && $show_explanation) 
				$out['td']['content'].= '<p class="explanation">'.$field['explanation_top'].'</p>';
			$subtables = array_keys($zz_tab[$sub_tab]);
			foreach ($subtables as $this_rec => $values)
				if (!is_numeric($subtables[$this_rec])) unset($subtables[$this_rec]);
			$zz_var['horizontal_table_head'] = false;
			// go through all detail records
			$table_open = false;
			
			$firstsubtable_no = NULL;
			
			$c_subtables = 0;
			foreach ($subtables as $sub_rec) {
				// show all subtables which are not deleted but 1 record as a minimum
				if ($zz_tab[$sub_tab][$sub_rec]['action'] == 'delete'
					AND (empty($zz_tab[$sub_tab]['records'])
						AND ($sub_rec + 1) != $zz_tab[$sub_tab]['min_records'])) continue;
				// don't show records which are being ignored
				if ($zz_tab[$sub_tab][$sub_rec]['action'] == 'ignore'
					AND $st_display != 'form') continue;
				// don't show records which are deleted with tick_to_save
				if ($zz_tab[$sub_tab][$sub_rec]['action'] == 'delete'
					AND $st_display != 'form'
					AND !empty($field['tick_to_save'])) continue;
				if ($zz_tab[$sub_tab][$sub_rec]['action'] == 'delete'
					AND $st_display != 'form' AND $zz_var['action']) continue;

				$c_subtables++;
				$my_st_display = $st_display;

				// get first subtable that will be displayed
				// in order to be able to say whether horizontal table shall be openend		
				if (!isset($firstsubtable_no)) $firstsubtable_no = $sub_rec;
				$lastrow = false;
				$show_remove = false;

				$dont_delete_records = (!empty($field['dont_delete_records'])
					? $field['dont_delete_records'] : '');
				if (!empty($field['values'][$sub_rec])) {
					$dont_delete_records = true; // dont delete records with values set
				}
				// just for optical reasons, in case one row allows removing of record
				if ($display == 'form') $lastrow = '&nbsp;'; 
				
				if ($my_st_display == 'form') {
					if ($zz_tab[$sub_tab]['min_records'] < $zz_tab[$sub_tab]['records']
						&& !$dont_delete_records)
						$show_remove = true;
				}
				$zz_var['class_add'] = ((!empty($field['class_add']) AND
					empty($zz_tab[$sub_tab][$sub_rec]['id']['value'])) 
					? $field['class_add'] : '');

				// Mode
				if (!empty($field['tick_to_save'])) $show_tick = true;
				$subtable_mode = $mode;
				if ($subtable_mode == 'edit' AND empty($zz_tab[$sub_tab][$sub_rec]['id']['value'])) {
					// no saved record exists, so it's add a new record
					$subtable_mode = 'add';
					if ($field['form_display'] != 'horizontal' AND !empty($field['tick_to_save'])) {
						$show_tick = false;
					}
				} elseif (empty($zz_tab[$sub_tab][$sub_rec]['id']['value'])) {
					if ($field['form_display'] != 'horizontal' AND !empty($field['tick_to_save'])) {
						$show_tick = false;
					}
				}
				if (!empty($zz_tab[$sub_tab][$sub_rec]['save_record'])) {
					$show_tick = true;
				}

				if ($field['form_display'] != 'horizontal' OR $sub_rec == $firstsubtable_no) {
					$out['td']['content'].= '<div class="detailrecord">';
				}
				if (!empty($field['tick_to_save'])) {
					$out['td']['content'].= '<p class="tick_to_save"><input type="checkbox"'
						.($show_tick ? ' checked="checked"' : '')
						.($my_st_display != 'form' ? ' disabled="disabled"' : '')
						.' name="zz_save_record['.$sub_tab.']['.$sub_rec.']"></p>';
				}
				
				// HTML output depending on form display
				if ($field['form_display'] != 'horizontal' OR $sub_rec == $firstsubtable_no) {
					$out['td']['content'].= '<table class="'.$field['form_display']
						.($field['form_display'] != 'horizontal' ? ' '.$zz_var['class_add'] : '')
						.'">'; // show this for vertical display and for first horizontal record
					$table_open = true;
				}
				if ($field['form_display'] != 'horizontal' OR $sub_rec == count($subtables)-1)
					$h_show_explanation = true;
				else
					$h_show_explanation = false;
				if ($show_remove) {
					$removebutton = '<input type="submit" value="'
						.sprintf(zz_text('Remove %s'), $field['title_button'])
						.'" class="sub-remove" name="zz_subtables[remove]['
						.$sub_tab.']['.$sub_rec.']">';
					if ($field['form_display'] == 'horizontal') {
						$lastrow = $removebutton;	
					}
				}	
				$out['td']['content'].= zz_show_field_rows($zz_tab, $sub_tab, 
					$sub_rec, $subtable_mode, $my_st_display, $zz_var, $zz_conf_record, 
					$field['form_display'], $lastrow, $sub_rec, $h_show_explanation);
				if ($field['form_display'] != 'horizontal') {
					$out['td']['content'].= '</table></div>'."\n";
					$table_open = false;
				}
				if ($show_remove) {
					if ($field['form_display'] != 'horizontal') {
						$out['td']['content'] .= $removebutton;
					}
				}
			}
			if ($table_open) {
				$out['td']['content'].= '</table></div>'."\n";
			}
			if (!$c_subtables AND !empty($field['msg_no_subtables'])) {
				// There are no subtables, optional: show a message here
				$out['td']['content'].= $field['msg_no_subtables'];
			}
			if ($st_display == 'form' 
				AND $zz_tab[$sub_tab]['max_records'] > $zz_tab[$sub_tab]['records'])
				$out['td']['content'] .= '<input type="submit" value="'
					.sprintf(zz_text('Add %s'), $field['title_button'])
					.'" class="sub-add" name="zz_subtables[add]['
					.$sub_tab.']">';
			if ($st_display == 'form' && $field['explanation'] && $show_explanation)
				$out['td']['content'].= '<p class="explanation">'.$field['explanation'].'</p>';
			if (!empty($field['separator']))
				$out['separator'] = $field['separator'];
		} elseif ($field['type'] == 'foreign_key' 
			OR $field['type'] == 'translation_key' 
			OR $field['type'] == 'detail_key') {
			continue; // this must not be displayed, for internal link only
		} else {
//	"Normal" field
			// option fields must have type_detail set, these are normal fields in form view
			// but won't be saved to database
			if ($field['type'] == 'option') {
				// options will only be shown in edit mode
				if ($mode != 'edit' AND $mode != 'add') continue; 
				// option as normal field, set to type_detail for display form
				$field['type'] = $field['type_detail'];
				$is_option = true;
			} else $is_option = false;

			// initalize class values
			if (!isset($field['class'])) $field['class'] = array();
			elseif (!is_array($field['class'])) $field['class'] = array($field['class']);

			// add classes
			if ($field['type'] == 'id') {
				if (empty($field['show_id']))
					$field['class'][] = 'idrow';
			} elseif ($firstrow) {
				$field['class'][] = 'firstrow';
				$firstrow = false;
			}
			if ($tab AND ($field['type'] == 'id' OR $field['type'] == 'timestamp')) {
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
				} elseif (!$tab) {
					$out['th']['content'] = ''; // for main record, show empty cells
				} else
					$out['th']['show'] = false;
				$close_span = false;
			} else {
				$close_span = true;
				// so error class does not get lost (but only error, no hidden classes)
				if ($field['class'] == 'error')
					$out['tr']['attr'][]  = $field['class']; 
				$out['td']['content'].= '<span'.($field['class'] ? ' class="'.$field['class'].'"' : '').'>'; 
			}
			if (!empty($field['append_next'])) {
				$append_next = true;
				if (!empty($field['explanation']))
					$append_explanation[] = $field['explanation'];
			} else {
				$append_next = false;
			}

			// field size, maxlength
			if (!isset($field['size'])) {
				if ($field['type'] == 'number') {
					$field['size'] = 16;
		 		} else {
		 			$field['size'] = 32;
		 		}
			}
		 	if ($field['type'] == 'ipv4') {
		 		$field['size'] = 16;
		 		$field['maxlength'] = 16;
			} elseif ($field['type'] == 'time') {
				$field['size'] = 8;
			}
			if ($field['maxlength'] && $field['maxlength'] < $field['size']
				AND (empty($field['number_type']) OR !in_array($field['number_type'], array('latitude', 'longitude')))) {
				$field['size'] = $field['maxlength'];
			}

			// apply factor only if there is a value in field
			// don't apply it if it's a re-edit
			if ($my_rec['record'] && isset($field['factor']) && $my_rec['record'][$field['field_name']]) {
				if (!is_array($my_rec['record'][$field['field_name']]) 
					&& ($zz_tab[0][0]['action'] != 'review')) { //  OR )
					
					// for incorrect values; !action means only divide once
					// for review, e. g. if record has been updated, division 
					// has to be done to show the correct value	
					$my_rec['record'][$field['field_name']] /=$field['factor'];
				}
			}
			
			// auto values
			if (isset($field['auto_value'])) {
				$field['default'] = zz_set_auto_value($field, $zz_tab[$tab]['sql'], 
					$zz_tab[$tab]['table'], $tab, $rec, $zz_var['id'], $zz_tab[0]['table']);
			}

			// $zz_var, values, defaults
			if (isset($my_where_fields[$field['field_name']])) {
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
				if (($mode == 'add' && !$my_rec['record']) OR !empty($is_option)
					OR !$my_rec['record'] && !empty($field['def_val_ignore'])) { 
					// set default only if record is empty 
					// OR if it's an option field which is always empty 
					// OR if default value is set to be ignored in case of no 
					// further additions
					$my_rec['record'][$field['field_name']] = $field['default'];
					$default_value = true; // must be unset later on because of this value
				}
			}
			//
			// output all records
			//
			
			if ($row_display == 'form' && !empty($field['explanation_top']))
				$out['td']['content'].= '<p class="explanation">'.$field['explanation_top'].'</p>';
			if ($field['type'] == 'write_once' AND ($mode == 'add' OR $zz_var['action'] == 'insert')) {
				$field['type'] = $field['type_detail'];
			}
			$outputf = false;
			switch ($field['type']) {
			case 'id':
				if ($my_rec['id']['value']) 
					$outputf.= '<input type="hidden" value="'.$my_rec['id']['value']
						.'" name="'.$field['f_field_name'].'" id="'
						.make_id_fieldname($field['f_field_name']).'">'.$my_rec['id']['value'];
				else
					$outputf.= '('.zz_text('will_be_added_automatically').')&nbsp;';
				break;
			case 'predefined':
			case 'identifier':
			case 'hidden':
				$my_element = '<input type="hidden" value="%s" name="%s" id="%s">';
				$db_value = '';
				$display_value = '';
				$mark_italics = false;
				if (!empty($my_where_fields[$field['field_name']])) {
					$db_value = $my_where_fields[$field['field_name']];
				} elseif (!empty($field['value'])) {
					if ($my_rec['record'] AND $field['value'] != $my_rec['record'][$field['field_name']])
					$display_value = $my_rec['record'][$field['field_name']];
					$db_value = $field['value'];
					if ($mode != 'delete') $mark_italics = true;
				} elseif ($my_rec['record']) {
					$db_value = $my_rec['record'][$field['field_name']];
				}
				if (!$display_value) $display_value = $db_value;
				if ($mark_italics) $outputf .= '<em title="'.zz_text('Would be changed on update').'">';
				if ($db_value AND !empty($field['type_detail']) AND $field['type_detail'] == 'ipv4') {
					$outputf.= long2ip($display_value);
				} elseif ($db_value AND !empty($field['type_detail']) AND $field['type_detail'] == 'date') {
					$outputf.= datum_de($display_value);
				} elseif ($db_value AND !empty($field['type_detail']) AND $field['type_detail'] == 'select') {
					$detail_key = ($display_value ? $display_value : $field['default']);
					$my_fieldname = $field['field_name'];
					if (isset($field['key_field_name'])) $my_fieldname = $field['key_field_name'];
					if (isset($field['sql'])) {
						$sql = zz_edit_sql($field['sql'], 'WHERE', '('.$my_fieldname.' = '.$detail_key.')');
						$select_fields = zz_db_fetch($sql);
						if ($select_fields) {
							// remove hierarchy field for display
							if (!empty($field['show_hierarchy'])) {
								unset($select_fields[$field['show_hierarchy']]);
							}
							// remove ID (= first field) for display
							if (count($select_fields) > 1)
								array_shift($select_fields); 
							$outputf .= implode(' | ', $select_fields);
						} else {
							$zz_error[]['msg'] = sprintf(zz_text('Record for %s does not exist.')
								, '<strong>'.$field['title'].'</strong>')
								.' (ID: '.htmlspecialchars($db_value).')';
							$zz_error['error'] = true;
							zz_error();
							return zz_return(false);
						}
					} elseif (isset($field['enum'])) {
						$outputf .= $display_value;
					}
				} elseif ($my_rec['record']) {
					if (isset($field['timestamp']) && $field['timestamp']) {
						$outputf.= timestamp2date($display_value);
					} elseif (isset($field['display_field'])) {
						if (!empty($my_rec['record'][$field['display_field']]))
							$outputf.= htmlspecialchars($my_rec['record'][$field['display_field']]);
						elseif (!empty($my_rec['record_saved'][$field['display_field']]))
							$outputf.= htmlspecialchars($my_rec['record_saved'][$field['display_field']]);
						else {
							if (empty($field['append_next']))
								if (!empty($field['value'])) $outputf.= $field['value'];
								else $outputf.= '('.zz_text('will_be_added_automatically').')';
						}
					} else {
						if (!empty($display_value)) {
							$outputf.= htmlspecialchars($display_value);
						} elseif (!empty($my_rec['record_saved'][$field['field_name']])) {
							$outputf.= htmlspecialchars($my_rec['record_saved'][$field['field_name']]);
						} else {
							if (empty($field['append_next']))
								if (!empty($field['value'])) $outputf.= $field['value'];
								else $outputf.= '('.zz_text('will_be_added_automatically').')';
						}
					}
				} else {
					if ($display_value) {
						if (!empty($field['type_detail']) && $field['type_detail'] == 'select')
							$outputf.= '('.zz_text('will_be_added_automatically').')&nbsp;';
						else
							$outputf.= $display_value;
					} else $outputf.= '('.zz_text('will_be_added_automatically').')&nbsp;';
				}
				if ($mark_italics) $outputf .= '</em>';
				$outputf .= sprintf($my_element, htmlspecialchars($db_value), $field['f_field_name'], 
					make_id_fieldname($field['f_field_name']));
				break;
			case 'timestamp':
				$outputf.= '<input type="hidden" value="';
				if (!empty($field['value'])) $outputf.= $field['value'];
				elseif ($my_rec['record']) $outputf.= $my_rec['record'][$field['field_name']];
				$outputf.= '" name="'.$field['f_field_name'].'" id="'
					.make_id_fieldname($field['f_field_name']).'">';
				if (!empty($my_rec['record'][$field['field_name']])) {
					$outputf .= ($mode != 'delete' ? '<em title="'.zz_text('Would be changed on update').'">' : '')
						.timestamp2date($my_rec['record'][$field['field_name']])
						.($mode != 'delete' ? '</em>' : '');
				} else
					$outputf.= '('.zz_text('will_be_added_automatically').')&nbsp;';
				break;
			case 'unix_timestamp':
				if (isset($field['value'])) {
					if ($row_display == 'form') $outputf.= '<input type="hidden" value="';
					$outputf.= $field['value'];
				} else {
					if ($row_display == 'form') $outputf.= '<input type="text" value="';
					if ($my_rec['record'] AND !empty($my_rec['record'][$field['field_name']])) {
						$timestamp = strtotime($my_rec['record'][$field['field_name']]);
						if ($timestamp AND $timestamp != -1)
							$my_rec['record'][$field['field_name']] = $timestamp;
						if ($my_rec['record'][$field['field_name']] 
							AND is_numeric($my_rec['record'][$field['field_name']]))
							$outputf.= date('Y-m-d H:i:s', $my_rec['record'][$field['field_name']]);
					}
				}
				if ($row_display == 'form') 
					$outputf.= '" name="'.$field['f_field_name'].'" id="'
						.make_id_fieldname($field['f_field_name']).'">';
				if (!$my_rec['record'] AND isset($field['value'])) 
					$outputf.= '('.zz_text('will_be_added_automatically').')&nbsp;';
				break;
			case 'foreign':
				$sql = $field['sql'].$my_rec['id']['value'];
				$foreign_lines = zz_db_fetch($sql, 'dummy_id', 'single value', 'fieldtype foreign');
				if ($foreign_lines) {
					// All Data in one Line! via SQL
					$outputf .= implode(', ', $foreign_lines);
				} else {
					$outputf.= zz_text('no-data-available');
				} 
				if (isset($field['add_foreign'])) {
					if ($my_rec['id']['value'])
						$outputf.= ' <a href="'.$field['add_foreign'].$my_rec['id']['value']
							.'&amp;referer='.urlencode($_SERVER['REQUEST_URI']).'">['
							.zz_text('edit').' &hellip;]</a>';
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
				if ($my_rec['record'])
					if ($row_display == 'form') $outputf.= 'value="'.$my_rec['record'][$field['field_name']].'"';
					else $outputf .= '('.zz_text('hidden').')';
				if ($row_display == 'form') $outputf.= '>';
				if ($my_rec['record'] && $row_display == 'form' && $zz_var['action'] != 'insert') {
					$outputf .= '<input type="hidden" name="'.$field['f_field_name'].
						'--old" value="'.(!empty($my_rec['record'][$field['field_name'].'--old']) 
						? $my_rec['record'][$field['field_name'].'--old'] : $my_rec['record'][$field['field_name']]).'">';
					// this is for validation purposes
					// take saved password (no matter if it's interefered with 
					// maliciously by user - worst case, pwd will be useless)
					// - if old and new value are identical
					// do not apply encryption to password
				}
				break;
			case 'password_change':
				if ($row_display == 'form') {
					$outputf.= '<table class="subtable">'."\n";
					$outputf.= '<tr><th><label for="'.make_id_fieldname($field['f_field_name']).'">'
						.zz_text('Old:').' </label></th><td><input type="password" name="'
						.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name'])
						.'" size="'.$field['size'].'" ';
					if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
					$outputf.= '></td></tr>'."\n";
					$outputf.= '<tr><th><label for="'.make_id_fieldname($field['f_field_name'].'_new_1').'">'
						.zz_text('New:').' </label></th><td><input type="password" name="'
						.$field['f_field_name'].'_new_1" id="'.make_id_fieldname($field['f_field_name']
						.'_new_1').'" size="'.$field['size'].'" ';
					if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
					$outputf.= '></td></tr>'."\n";
					$outputf.= '<tr><th><label for="'.make_id_fieldname($field['f_field_name'].'_new_2').'">'
						.zz_text('New:').' </label></th><td><input type="password" name="'
						.$field['f_field_name'].'_new_2" id="'.make_id_fieldname($field['f_field_name']
						.'_new_2').'" size="'.$field['size'].'" ';
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
			case 'mail+name':
			case 'datetime':
			case 'ipv4':
				if ($row_display == 'form') {
					$outputf.= '<input type="text" name="'.$field['f_field_name']
						.'" id="'.make_id_fieldname($field['f_field_name']).'" size="'.$field['size'].'" ';
					if (!empty($field['maxlength'])) $outputf.= ' maxlength="'.$field['maxlength'].'" ';
				}
				if ($my_rec['record']) {
					if ($row_display == 'form') $outputf.= 'value="';
					elseif ($field['type'] == 'url' && !empty($my_rec['record'][$field['field_name']])) 
						$outputf.= '<a href="'.htmlspecialchars($my_rec['record'][$field['field_name']]).'">';
					elseif ($field['type'] == 'mail' && !empty($my_rec['record'][$field['field_name']]))
						$outputf.= '<a href="mailto:'.$my_rec['record'][$field['field_name']].'">';
					elseif ($field['type'] == 'mail+name' && !empty($my_rec['record'][$field['field_name']]))
						$outputf.= '<a href="mailto:'.rawurlencode($my_rec['record'][$field['field_name']]).'">';
					if ($field['type'] == 'url' 
						AND strlen($my_rec['record'][$field['field_name']]) > $zz_conf_record['max_select_val_len'] 
						AND $row_display != 'form')
						$outputf.= htmlspecialchars(mb_substr($my_rec['record'][$field['field_name']], 0, 
							$zz_conf_record['max_select_val_len'])).'...';
					elseif ($field['type'] == 'ipv4')
						$outputf.= long2ip($my_rec['record'][$field['field_name']]);
					else
						$outputf.= htmlspecialchars($my_rec['record'][$field['field_name']]);
					if (($field['type'] == 'url' OR $field['type'] == 'mail' OR $field['type'] == 'mail+name')
						&& !empty($my_rec['record'][$field['field_name']]) && $row_display != 'form')
						$outputf.= '</a>';
					if ($row_display == 'form') $outputf.= '"';
				}
				if ($row_display == 'form') $outputf.= '>';
				break;
			case 'number':
				$suffix = false;
				if (isset($field['number_type']) 
					AND $field['number_type'] == 'latitude' || $field['number_type'] == 'longitude') {
					if (!isset($field['geo_format'])) $field['geo_format'] = 'dms';
					if ($my_rec['record']) {
						if ($my_rec['record'][$field['field_name']] === NULL) {
							$my_value = '';
						} elseif (isset($field['check_validation']) AND !$field['check_validation']) {
							// validation was not passed, hand back invalid field
							$my_value = htmlchars($my_rec['record'][$field['field_name']]);
						} else {
							$my_value = zz_geo_coord_out($my_rec['record'][$field['field_name']], $field['number_type'], $field['geo_format']);
							if (!empty($field['geo_display_behind'])) {
								$suffix = zz_geo_coord_out($my_rec['record'][$field['field_name']], $field['number_type'], $field['geo_display_behind']);
							}
						}
					} else {
						$my_value = '';
					}
				} else {
					$my_value = ($my_rec['record'] ? htmlchars($my_rec['record'][$field['field_name']]) : '');
				}
				if ($row_display == 'form') {
					$my_element = '<input type="text" name="%s" id="%s" size="%s" value="%s">';
					$outputf .= sprintf($my_element, $field['f_field_name'], 
						make_id_fieldname($field['f_field_name']), $field['size'], $my_value);
				} else {
					$outputf .= $my_value;
				}
				if ($suffix) $outputf .= ' <small>( = '.$suffix.')</small>';
				break;
			case 'date':
				$my_value = ($my_rec['record'] ? datum_de($my_rec['record'][$field['field_name']]) : '');
				if ($row_display == 'form') {
					$my_element = '<input type="text" name="%s" id="%s" size="12" value="%s">';
					$outputf .= sprintf($my_element, $field['f_field_name'], 
						make_id_fieldname($field['f_field_name']), $my_value);
				} else {
					$outputf .= $my_value;
				}
				break;
			case 'memo':
				$field['cols'] = (!empty($field['cols']) ? $field['cols'] : 60);
				$field['rows'] = (!empty($field['rows']) ? $field['rows'] : 8);
				if ($my_rec['record']) {
					$memotext = $my_rec['record'][$field['field_name']];
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
				}
				if ($row_display == 'form') $outputf.= '<textarea rows="'
					.$field['rows'].'" cols="'.$field['cols'].'" name="'
					.$field['f_field_name'].'" id="'.make_id_fieldname($field['f_field_name']).'">';
				if ($my_rec['record']) {
					// format in case it's not editable and won't be saved in db
					if ($row_display != 'form' AND isset($field['format']))
						$memotext = $field['format'](htmlspecialchars($memotext));
					else
						$memotext = htmlspecialchars($memotext);
					$outputf.= $memotext;
				}
				if ($row_display == 'form') $outputf.= '</textarea>';
				break;
			case 'select':
			// SELECT field, might be #1 foreign_key (sql query needed), enum or set
			// #1 SELECT with foreign key
				if (!empty($field['sql'])) {
					if (!empty($field['sql_without_id'])) $field['sql'] .= $my_rec['id']['value'];

					// check for 'sql_where'
					if ($my_where_fields) {
						list($field['sql'], $add_details_where) 
							= zz_form_select_sql_where($field, $my_where_fields);
					}
					// check for 'sql_where_with_id'
					if (!empty($field['sql_where_with_id']) AND !empty($zz_var['id']['value']))
						$field['sql'] = zz_edit_sql($field['sql'], 'WHERE', 
							$zz_var['id']['field_name'].' = "'.$zz_var['id']['value'].'"');

					$outputf .= zz_form_select_sql($field, $zz_tab[$tab]['db_name'].'.'.$zz_tab[$tab]['table'], 
						$my_rec['record'], $row_display, $zz_conf_record);

			// #2a SELECT with set_folder
				} elseif (isset($field['set_folder'])) {
					if (!is_dir($field['set_folder'])) {
						echo '`'.$field['set_folder'].'` is not a folder. Check `["set_folder"]` definition.';
						exit;
					}
					$files = array();
					$handle = opendir($field['set_folder']);
					while ($file = readdir($handle)) {
						if (substr($file, 0, 1) == '.') continue;
						$files[] = $file;
					}
					if (!$files)
						$field['set'] = array();
					elseif ($field['set_title'] === true) {
						$field['set_title'] = array();
						foreach ($files as $file) {
							$size = filesize($field['set_folder'].'/'.$file);
							$size = zz_format_bytes($size);
							$field['set'][] = $file;
							$field['set_title'][] = $file.' ['.$size.']';
						}
					} else {
						$field['set'][] = $files;
					}
					$outputf .= zz_form_select_set($field, $row_display, $my_rec['record']);

			// #2 SELECT with set_sql
				} elseif (isset($field['set_sql'])) {
					$field['sql'] = $field['set_sql'];

					// check for 'sql_where'
					if ($my_where_fields) {
						list($field['sql'], $add_details_where) 
							= zz_form_select_sql_where($field, $my_where_fields);
					}
					
					//$field['set_sql'] or key/value
					if ($field['set_title'] === true) {
						$sets = zz_db_fetch($field['sql'], 'dummy_field_name', 'key/value');
						foreach ($sets as $key => $value) {
							$field['set'] = explode(',', $key);
							$field['set_title'] = explode(',', $value);
//							$my_rec['record'][$field['field_name']] = $field['set'];
						}
					} else {
						$sets = zz_db_fetch($field['sql'], '', 'single value');
						$field['set'] = explode(',', $sets);
					}
					$outputf .= zz_form_select_set($field, $row_display, $my_rec['record']);

			// #3 SELECT with set
				} elseif (isset($field['set'])) {
					$outputf .= zz_form_select_set($field, $row_display, $my_rec['record']);

			// #4 SELECT with enum
				} elseif (isset($field['enum'])) {
					$myi = 0;
					$sel_option = (count($field['enum']) <=2 ? true : 
						(!empty($field['show_values_as_list']) ? true : false));
					if ($row_display == 'form') {
						if ($sel_option) {
							if (!isset($field['hide_novalue'])) $field['hide_novalue'] = true;
							$myid = make_id_fieldname($field['f_field_name']).'-'.$myi;
							$outputf.= '<label for="'.$myid.'"'
								.($field['hide_novalue'] ? ' class="hidden"' : '')
								.'><input type="radio" id="'.$myid.'" name="'
								.$field['f_field_name'].'" value=""';
							if ($my_rec['record']) { 
								if (!$my_rec['record'][$field['field_name']]) 
									$outputf.= ' checked="checked"'; 
							} else {
								// no value, no default value (both would be 
								// written in my record fieldname)
								$outputf.= ' checked="checked"'; 
							}
							$outputf.= '>&nbsp;'.zz_text('no_selection').'</label>';
							if (!empty($field['show_values_as_list'])) 
								$outputf .= "\n".'<ul class="zz_radio_list">'."\n";
						} else {
							$outputf.= '<select name="'.$field['f_field_name'].'" id="'
								.make_id_fieldname($field['f_field_name']).'">'."\n";
							$outputf.= '<option value=""';
							if ($my_rec['record']) { 
								if (!$my_rec['record'][$field['field_name']])
									$outputf.= ' selected="selected"';
							} else {
								// no value, no default value (both would be 
								// written in my record fieldname)
								$outputf.= ' selected="selected"';
							}
							$outputf.= '>'.zz_text('none_selected').'</option>'."\n";
						} 
					}
					foreach ($field['enum'] as $key => $set) {
						if ($row_display == 'form') {
							if ($sel_option) {
								$myi++;
								$myid = make_id_fieldname($field['f_field_name']).'-'.$myi;
								if (!empty($field['show_values_as_list'])) $outputf .= '<li>';
								$outputf.= ' <label for="'.$myid.'"><input type="radio" id="'
									.$myid.'" name="'.$field['f_field_name'].'" value="'.$set.'"';
								if ($my_rec['record']) if ($set == $my_rec['record'][$field['field_name']]) 
									$outputf.= ' checked="checked"';
								$outputf.= '>&nbsp;'.(!empty($field['enum_title'][$key]) 
									? $field['enum_title'][$key] : zz_text($set)).'</label>';
								if (!empty($field['show_values_as_list'])) $outputf .= '</li>'."\n";
							} else {
								$outputf.= '<option value="'.$set.'"';
								if ($my_rec['record'] AND $set == $my_rec['record'][$field['field_name']]) {
									$outputf.= ' selected="selected"';
								} elseif (!empty($field['disabled_ids']) 
									AND is_array($field['disabled_ids'])
									AND in_array($set, $field['disabled_ids'])) {
									$output.= ' disabled="disabled"';
								}
								$outputf.= '>';
								$outputf.= (!empty($field['enum_title'][$key]) 
									? $field['enum_title'][$key] : zz_text($set));
								$outputf.= '</option>'."\n";
							}
						} else {
							if ($set != $my_rec['record'][$field['field_name']]) continue;
							$outputf.= (!empty($field['enum_title'][$key]) 
								? $field['enum_title'][$key] : zz_text($set));
						}
					}
					if (!empty($field['show_values_as_list'])) {
						if (empty($field['append_next']) && $row_display == 'form')
							$outputf .= '</ul>'."\n";
						else $append_next_type = 'list';
					}
					if ($row_display == 'form' && !$sel_option) $outputf.= '</select>'."\n";

			// #5 SELECT without any source = that won't work ...
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
						$outputf .= $img = zz_makelink($field['path'], $my_rec['record'], 'image');
					if (!$img AND !empty($my_rec['record_saved'])) {
						$outputf .= $img = zz_makelink($field['path'], $my_rec['record_saved'], 'image');
					}
					if (!$img)
						$outputf.= '('.zz_text('image_not_display').')';
					$outputf.= '</p>';
				}
				if (($mode == 'add' OR $mode == 'edit') && $field['type'] == 'upload_image') {
					if (!isset($field['image'])) {
						$zz_error[] = array(
							'msg' => 'Image upload is currently not possible. '
								.zz_text('An error occured. We are working on the '
								.'solution of this problem. Sorry for your '
								.'inconvenience. Please try again later.'),
							'msg_dev' => 'Configuration error. Missing upload_image details.',
							'level' => E_USER_WARNING
						);
						zz_error();
						$outputf.= zz_error_output();
					} else {
						$image_uploads = 0;
						foreach ($field['image'] as $imagekey => $image)
							if (!isset($image['source']) AND !isset($image['source_field'])) $image_uploads++;
						if ($image_uploads > 1) $outputf.= '<table class="upload">';
						foreach ($field['image'] as $imagekey => $image) {
							if (isset($image['source'])) continue;
							if (isset($image['source_field'])) continue;
							// todo: if only one image, table is unnecessary
							// title and field_name of image might be empty
							if ($image_uploads > 1) $outputf.= '<tr><th>'.$image['title'].'</th> <td>';
							$outputf .= '<input type="file" name="'
								.make_id_fieldname($field['f_field_name']).'['.$image['field_name'].']">';
							if (empty($field['dont_show_file_link']) 
								AND $link = zz_makelink($image['path'], (isset($my_rec['record_saved']) 
									? $my_rec['record_saved'] : $my_rec['record'])))
								$outputf .= '<br><a href="'.$link.'">'.$link
									.'</a>'
									.(($image_uploads > 1 OR !empty($field['optional_image'])) ?
									' (<small><label for="delete-file-'.$fieldkey.'-'.$imagekey
									.'"><input type="checkbox" name="zz_delete_file['.$fieldkey.'-'.$imagekey
									.']" id="delete-file-'.$fieldkey.'-'.$imagekey.'">&nbsp;'
									.zz_text('Delete this file').'</label></small>)'
									: '');
							if (!empty($my_rec['images'][$fieldkey][$imagekey]['error']))
								$outputf.= '<br><small>'.implode('<br>', 
									$my_rec['images'][$fieldkey][$imagekey]['error']).'</small>';
							else
								$outputf.= '<br><small>'.zz_text('Maximum allowed filesize is').' '
									.zz_format_bytes($zz_conf['upload_MAX_FILE_SIZE']).'</small>';
							if ($row_display == 'form' && !empty($image['explanation'])) 
								$outputf.= '<p class="explanation">'.$image['explanation'].'</p>';
							if ($image_uploads > 1) $outputf.= '</td></tr>'."\n";
						}
						if ($image_uploads > 1) $outputf.= '</table>'."\n";
					}
				} else if (isset($field['image'])) {
					$image_uploads = 0;
					foreach ($field['image'] as $imagekey => $image)
						if (!isset($image['source'])) $image_uploads++;
					if ($image_uploads > 1) {
						$outputf.= '<table class="upload">';
						foreach ($field['image'] as $imagekey => $image) {
							if (isset($image['source'])) continue;
							if ($link = zz_makelink($image['path'], $my_rec['record'])) {
								$outputf.= '<tr><th>'.$image['title'].'</th> <td>';
								$outputf .= '<a href="'.$link.'">'.$link.'</a>';
								$outputf.= '</td></tr>'."\n";
							}
						}
						$outputf.= '</table>'."\n";
					}
				}
				break;
			case 'write_once':
			case 'display':
				if (isset($field['display_value']))
					// internationalization has to be done in zz-fields-definition
					$outputf .= $field['display_value']; 
				elseif ($my_rec['record']) {
					$val_to_insert = '';
					if (isset($field['display_field'])) {
						if (!empty($my_rec['record'][$field['display_field']])) {
							$val_to_insert = $my_rec['record'][$field['display_field']];
						} elseif (!empty($my_rec['record_saved'][$field['display_field']])) { // empty for new record
							$val_to_insert = $my_rec['record_saved'][$field['display_field']]; // requery
						}
						if ($val_to_insert) {
							if (!empty($field['translate_field_value']))
								$val_to_insert = zz_text($val_to_insert);
							$outputf .= $val_to_insert;
						}
					} elseif (isset($field['field_name'])) {
						if (!empty($my_rec['record'][$field['field_name']]))
							$val_to_insert = $my_rec['record'][$field['field_name']];
						elseif (!empty($my_rec['record_saved'][$field['field_name']])) // empty if new record!
							$val_to_insert = $my_rec['record_saved'][$field['field_name']];
						if (!empty($field['display_title']) && in_array($val_to_insert, 
							array_keys($field['display_title'])))
							$val_to_insert = $field['display_title'][$val_to_insert];
						if (!empty($field['translate_field_value']))
							$val_to_insert = zz_text($val_to_insert);
						$val_to_insert = htmlspecialchars($val_to_insert);
						if (isset($field['format']))
							$val_to_insert = $field['format']($val_to_insert);
						$outputf .= $val_to_insert;
					} else
						$outputf .= '<span class="error">'
							.zz_text('Script configuration error. No display field set.').'</span>'; // debug!
				} else $outputf .= zz_text('N/A');
				break;
			case 'calculated':
				if ($mode AND $mode != 'show') {
					$outputf.= '('.zz_text('calculated_field').')';
					break;
				}
				if ($field['calculation'] == 'hours') {
					$diff = 0;
					foreach ($field['calculation_fields'] as $calc_field)
						if (!$diff) $diff = strtotime($my_rec['record'][$calc_field]);
						else $diff -= strtotime($my_rec['record'][$calc_field]);
					if ($diff < 0) $outputf .= '<em class="negative">';
					$outputf.= gmdate('H:i', $diff);
					if ($diff < 0) $outputf .= '</em>';
				} elseif ($field['calculation'] == 'sum') {
					$sum = 0;
					foreach ($field['calculation_fields'] as $calc_field)
						$sum += $my_rec['record'][$calc_field];
					$outputf.= $sum;
				}
				break;
			}
			if (!empty($field['unit'])) {
				//if ($my_rec['record']) { 
				//	if ($my_rec['record'][$field['field_name']]) // display unit if record not null
				//		$outputf.= ' '.$field['unit']; 
				//} else {
					$outputf.= ' '.$field['unit']; 
				//}
			}
			if (!empty($default_value)) // unset $my_rec['record'] so following fields are empty
				unset($my_rec['record'][$field['field_name']]); 
			if (!isset($add_details_where)) $add_details_where = false;
			if ($mode && $mode != 'delete' && $mode != 'show' && $mode != 'review'
				AND isset($field['add_details'])) {
				$add_details_sep = (strstr($field['add_details'], '?') ? '&amp;' : '?');
				$outputf.= ' <a href="'.$field['add_details'].$add_details_sep
					.'mode=add&amp;referer='.urlencode($_SERVER['REQUEST_URI'])
					.$add_details_where.'"'
					.(!empty($field['add_details_target']) ? ' target="'.$field['add_details_target'].'"' : '')
					.' id="zz_add_details_'.$tab.'_'.$rec.'_'.$fieldkey.'">['.zz_text('new').' &hellip;]</a>';
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
							$vars .= $var; // todo: does this really make sense? 
							// looks more like $vars[] = $var. maybe use implode.
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
				if ($row_display == 'form' && $show_explanation) {
					if (!empty($append_explanation)) {
						$field['explanation'] = implode('<br>', $append_explanation)
							.($field['explanation'] ? '<br>'.$field['explanation'] : '');
						$append_explanation = array();
					}
					if ($field['explanation'])
						$out['td']['content'].= '<p class="explanation">'.$field['explanation'].'</p>';
				}
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
		if ($row['separator']) $output .= zz_show_separator($row['separator'], count($matrix));
	}
	return zz_return($output);
}

/**
 * add WHERE to $zz['fields'][n]['sql'] clause if necessary
 * 
 * @param array $field field that will be checked
 * @param array $where_fields = $zz_var['where'][$table_name]
 * @return array string $field['sql'], string $add_details_where
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_form_select_sql_where($field, $where_fields) {
	global $zz_conf;

	$add_details_where = ''; // for add_details
	if (empty($field['sql_where'])) {
		return array($field['sql'], $add_details_where);
	}
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$where_conditions = array();
	foreach ($field['sql_where'] as $sql_where) {
		// might be several where-clauses
		if (isset($sql_where[2])) {
			$sql = $sql_where[2];
			if (!empty($where_fields[$sql_where[1]]))
				$sql .= $where_fields[$sql_where[1]];
			$index = zz_db_fetch($sql, '', 'single value');
			if ($index) {
				$where_conditions[] = $sql_where[0]." = '".$index."'";
				$add_details_where .= '&amp;where['.$sql_where[0].']='.$index;
			}
		} elseif (isset($sql_where['where']) AND !empty($where_fields[$sql_where['field_name']])) {
			$where_conditions[] = sprintf($sql_where['where'], $where_fields[$sql_where['field_name']]);
		}
	}
	$field['sql'] = zz_edit_sql($field['sql'], 'WHERE', implode(' AND ', $where_conditions));

	return zz_return(array($field['sql'], $add_details_where));
}

/**
 * Output form element type="select", foreign_key with sql query
 * 
 * @param array $field field that will be checked
 * @param string $db_table db_name.table
 * @param array $record $my_rec['record']
 * @param string $row_display
 * @param array $zz_conf_record
 * @global array $zz_conf just checks for 'modules'[debug]
 * @global array $zz_error
 * @return string HTML output for form
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_form_select_sql($field, $db_table, $record, $row_display, $zz_conf_record) {
	global $zz_conf;
	global $zz_error;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$outputf = '';

	// we do not show all fields if query is bigger than $zz_conf_record['max_select']
	// so no need to query them (only if show_hierarchy_subtree is empty)
	if (empty($field['show_hierarchy_subtree'])) {
		$sql = zz_edit_sql($field['sql'], 'LIMIT', '0, '.($zz_conf_record['max_select']+1));
	} else {
		$sql = $field['sql'];
	}
	$lines = zz_db_fetch($sql, '_dummy_id_', 'numeric');
	unset($sql);

// #1.2 SELECT has only one result in the array, and this will be pre-selected 
// because FIELD must not be NULL
	if ($row_display == 'form' && count($lines) == 1 
		&& !zz_db_field_null($field['field_name'], $db_table)) {
		$line = array_shift($lines);
		// get ID field_name which must be 1st field in SQL query
		$id_field_name = array_keys($line);
		$id_field_name = current($id_field_name);
		if ($record && $line[$id_field_name] != $record[$field['field_name']]) 
			$outputf .= 'Possible Values: '.$line[$id_field_name]
				.' -- Current Value: '
				.htmlspecialchars($record[$field['field_name']])
				.' -- Error --<br>'.zz_text('no_selection_possible');
		else {
			$outputf.= '<input type="hidden" value="'.$line[$id_field_name]
				.'" name="'.$field['f_field_name'].'" id="'
				.make_id_fieldname($field['f_field_name']).'">'
				.zz_draw_select($line, $id_field_name, $record, $field, $zz_conf_record);
		}

// #1.3 SELECT has one or several results, let user select something
	} elseif ($lines) {
		$details = array();
		$detail_record = array();

		$count_rows = count($lines);
		// get ID field name, for convenience this may be simply the first
		// field name in the SQL query; sometimes you need to set a field_name
		// for WHERE separately depending on database design
		$line = current($lines);
		$line = array_keys($line);
		$id_field_name = current($line);
		unset($line);
		if (!empty($field['id_field_name']))
			$where_field_name = $field['id_field_name'];
		else
			$where_field_name = $id_field_name;

		// get single record if there is already something in the database
		if (!empty($record[$field['field_name']])) {
			$db_value = $record[$field['field_name']];
			if (substr($db_value, 0, 1) == '"' && substr($db_value, -1) == '"')
				$db_value = substr($db_value, 1, -1);
			$sql = zz_edit_sql($field['sql'], 'WHERE', $where_field_name
				.' = "'.$db_value.'"');
			if (!$sql) $sql = $field['sql'];
			$detail_records = zz_db_fetch($sql, $id_field_name, '', "record: "
				.$field['field_name'].' (probably \'id_field_name\' needs to be set)');
			if (count($detail_records) == 1) 
				$detail_record = reset($detail_records);
			else {
				// check for equal record values
				foreach ($detail_records as $line) {
					if ($line[$id_field_name] != $record[$field['field_name']]) continue;
					$detail_record = $line;
				}
			}
		}

		// no form display = no selection, just display the values in the record
		if ($row_display != 'form') {
			if ($detail_record) {
				$outputf.= zz_draw_select($detail_record, $id_field_name, $record, 
					$field, $zz_conf_record);
			}
			return zz_return($outputf);
		}
		
		// fill $details (i. e. all records that will be presented in an
		// SELECT/OPTION HTML element) only if needed, otherwise this will need 
		// a lot of memory usage
		if ($count_rows <= $zz_conf_record['max_select'] 
			OR !empty($field['show_hierarchy_subtree'])) {
			foreach ($lines as $line)
				$details[$line[$id_field_name]] = $line;
		}
		unset($lines);

		// ok, we display something!
		
		// do we have to display the results hierarchical in a SELECT?
		if (!empty($field['show_hierarchy'])) {
			$my_select = array();
			$show_hierarchy_subtree = 'NULL';
			foreach ($details as $line) {
				// if hierarchy is hierarchy of same table, don't allow to set
				// IDs in hierarchy or below to avoid recursion
				if (!empty($record[$id_field_name])) {
					if (!empty($field['show_hierarchy_same_table'])
						AND $line[$id_field_name] == $record[$id_field_name]) continue;
					if (!empty($field['show_hierarchy_same_table'])
						AND $line[$field['show_hierarchy']] == $record[$id_field_name]) continue;
				}
				// fill in values, index NULL is for uppermost level
				$my_select[(!empty($line[$field['show_hierarchy']]) 
					? $line[$field['show_hierarchy']] : 'NULL')][$line[$id_field_name]] = $line;
			}
			if (!empty($field['show_hierarchy_subtree']) 
				AND !empty($my_select[$field['show_hierarchy_subtree']])) {
				$show_hierarchy_subtree = $field['show_hierarchy_subtree'];
				// count fields in subhierarchy, should be less than existing $count_rows
				$count_rows = zz_count_records($my_select, $show_hierarchy_subtree);
			} else
				$field['show_hierarchy_subtree'] = false;
		}

		// more records than we'd like to display		
		if ($count_rows > $zz_conf_record['max_select']) {
			// don't show select but text input instead
			$textinput = true;
			if ($detail_record) {
				$outputf.= zz_draw_select($detail_record, $id_field_name, $record, 
					$field, $zz_conf_record, 'reselect');
				$textinput = false;
			}
			// value will not be checked if one detail record is added because 
			// in this case validation procedure will be skipped!
			if (!empty($record[$field['field_name']])) 
				$value = htmlspecialchars($record[$field['field_name']]); 
			else
				$value = '';
			
			if ($textinput) // add new record
				$outputf.= '<input type="text" size="'
					.(!empty($field['size_select_too_long']) ? $field['size_select_too_long'] : 32)
					.'" value="'.$value.'" name="'.$field['f_field_name'].'" id="'
					.make_id_fieldname($field['f_field_name']).'">';
			$outputf.= '<input type="hidden" value="'.$field['f_field_name']
				.'" name="zz_check_select[]">';

		// draw RADIO buttons
		} elseif (!empty($field['show_values_as_list'])) {
			$myi = 0;
			if ($row_display == 'form') {
				if (!isset($field['hide_novalue'])) $field['hide_novalue'] = true;
				$myid = make_id_fieldname($field['f_field_name']).'-'.$myi;
				$outputf.= '<label for="'.$myid.'"'
					.($field['hide_novalue'] ? ' class="hidden"' : '')
					.'><input type="radio" id="'.$myid.'" name="'
					.$field['f_field_name'].'" value=""';
				if ($record) { if (!$record[$field['field_name']]) $outputf.= ' checked="checked"'; }
				else {
					// no value, no default value 
					// (both would be written in my record fieldname)
					$outputf.= ' checked="checked"'; 
				}
				$outputf.= '>&nbsp;'.zz_text('no_selection').'</label>';
				$outputf .= "\n".'<ul class="zz_radio_list">'."\n";
			}
			
			foreach ($details as $id => $fields) {
				array_shift($fields); // get rid of ID, is already in $id
				if (!empty($field['sql_ignore'])) {
					if (!is_array($field['sql_ignore']))
						$field['sql_ignore'] = array($field['sql_ignore']);
					if ($keys = array_intersect(array_keys($fields), $field['sql_ignore']))
						foreach ($keys as $key) unset($fields[$key]);
				}
				if ($row_display == 'form') {
					$myi++;
					$myid = make_id_fieldname($field['f_field_name']).'-'.$myi;
					$outputf .= '<li>';
					$outputf.= ' <label for="'.$myid.'"><input type="radio" id="'
						.$myid.'" name="'.$field['f_field_name'].'" value="'.$id.'"';
					if ($record AND $id == $record[$field['field_name']]) 
						$outputf.= ' checked="checked"';
					$outputf.= '>&nbsp;';
					if (!empty($field['group'])) { // group display
						if ($fields[$field['group']])
							$outputf .= '<em>'.$fields[$field['group']].':</em> ';
						unset($fields[$field['group']]);
					}
					$outputf .= implode(' | ', $fields).'</label>';
					$outputf .= '</li>'."\n";
				} else {
					if ($id == $record[$field['field_name']]) 
						$outputf.= implode(' | ', $fields);
				}
			}
			if (empty($field['append_next']) && $row_display == 'form')
				$outputf .= '</ul>'."\n";
			else $append_next_type = 'list';

		// draw a SELECT element
		} else {
			$outputf.= '<select name="'.$field['f_field_name'].'" id="'
				.make_id_fieldname($field['f_field_name']).'">'."\n";
			// normally don't show a value, unless we only look at a part of a hierarchy
			$outputf.= '<option value="'
				.((!empty($field['show_hierarchy_subtree']) 
					AND !empty($field['show_hierarchy_use_top_value_instead_NULL'])) 
					? $field['show_hierarchy_subtree'] : '') 
				.'"';
			if ($record) if (!$record[$field['field_name']]) $outputf.= ' selected="selected"';
			$outputf.= '>'.zz_text('none_selected').'</option>'."\n";
			$close_select = true;
			if (empty($field['show_hierarchy']) AND empty($field['group'])) {
				foreach ($details as $line)
					$outputf.= zz_draw_select($line, $id_field_name, $record, 
						$field, $zz_conf_record, 'form');
			} elseif (!empty($field['show_hierarchy']) 
				AND !empty($my_select[$show_hierarchy_subtree]) AND !empty($field['group'])) {
				// optgroup
				$optgroup = false;
				foreach ($my_select[$show_hierarchy_subtree] as $line) {
					if ($optgroup != $line[$field['group']]) {
						if ($optgroup) $outputf .= '</optgroup>'."\n";
						$optgroup = $line[$field['group']];
						$outputf .= '<optgroup label="'.$optgroup.'">'."\n";
					}
					unset($line[$field['group']]); // not needed anymore
					$outputf.= zz_draw_select($line, $id_field_name, $record, 
						$field, $zz_conf_record, 'form', 1, $my_select, $field['show_hierarchy']);
				}
				$outputf .= '</optgroup>'."\n";
			} elseif (!empty($field['show_hierarchy']) AND !empty($my_select[$show_hierarchy_subtree])) {
				foreach ($my_select[$show_hierarchy_subtree] AS $line) {
					$outputf.= zz_draw_select($line, $id_field_name, $record, 
						$field, $zz_conf_record, 'form', 0, $my_select, $field['show_hierarchy']);
				}
			} elseif (!empty($field['show_hierarchy']) AND $count_rows == 1 AND $my_select) {
				// just one line, change multidimensional array into simple array
				$line = array_shift($my_select); // first hierarchy
				$line = array_shift($line); // first record in hierarchy
				$outputf.= zz_draw_select($line, $id_field_name, $record, 
					$field, $zz_conf_record, 'form', 0, $my_select, $field['show_hierarchy']);
			} elseif (!empty($field['show_hierarchy']) AND $count_rows == 1) {
				// could only select itself, so treat as if no selection possible
				$outputf = '<input type="hidden" value="" name="'.$field['f_field_name']
					.'" id="'.make_id_fieldname($field['f_field_name']).'">'
					.zz_text('no_selection_possible');
				$close_select = false;
			} elseif (!empty($field['group'])) {
				// optgroup
				$optgroup = false;
				foreach ($details as $line) {
					if ($optgroup != $line[$field['group']]) {
						if ($optgroup) $outputf .= '</optgroup>'."\n";
						$optgroup = $line[$field['group']];
						$outputf .= '<optgroup label="'.$optgroup.'">'."\n";
					}
					unset($line[$field['group']]); // not needed anymore
					$outputf.= zz_draw_select($line, $id_field_name, $record, 
						$field, $zz_conf_record, 'form', 1);
				}
				$outputf .= '</optgroup>'."\n";
			} elseif ($detail_record) {
			// re-edit record, something was posted, ignore hierarchy because 
			// there's only one record coming back
				$outputf.= zz_draw_select($detail_record, $id_field_name, $record, 
					$field, $zz_conf_record, 'form');
			} elseif (!empty($field['show_hierarchy']) AND $show_hierarchy_subtree == 'NULL') {
				// could only select itself, so treat as if no selection possible
				$outputf = '<input type="hidden" value="" name="'.$field['f_field_name']
					.'" id="'.make_id_fieldname($field['f_field_name']).'">'
					.zz_text('no_selection_possible').' '
					.zz_text('(This entry is the highest entry in the hierarchy.)');
				$close_select = false;
			} elseif (!empty($field['show_hierarchy'])) {
				$zz_error[] = array(
					'msg' => 'no_selection_possible',
					'msg_dev' => 'Configuration error: "show_hierarchy" used but '
						.'there is no highest level in the hierarchy.',
					'level' => E_USER_WARNING
				);
			}
			if ($close_select) $outputf.= '</select>'."\n";
			zz_error();
			$outputf.= zz_error_output();
		}
	
	// #1.4 SELECT has no result
	} else {
		$outputf.= '<input type="hidden" value="" name="'.$field['f_field_name']
			.'" id="'.make_id_fieldname($field['f_field_name']).'">'
			.zz_text('no_selection_possible');
	}

	return zz_return($outputf);
}

/**
 * Output form element type="select" with set
 *
 * @param array $field
 *		'set', 'field_name', 'f_field_name'
 * @param string $row_display
 * @param array $record
 * @return string $output HTML output for form
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_form_select_set($field, $row_display, $record = false) {
	$myvalue = array();
	$output = '';
	$myi = 0;
	if ($row_display == 'form') {
		// send dummy field to get a response if field content should be deleted
		$myid = 'check-'.$field['field_name'].'-'.$myi;
		$output .= '<input type="hidden" id="'
			.$myid.'" name="'.$field['f_field_name'].'[]" value="">';
	}
	foreach ($field['set'] as $key => $set) {
		$myi++;
		$myid = 'check-'.$field['field_name'].'-'.$myi;
		$set_display = !empty($field['set_title'][$key]) ? $field['set_title'][$key] : $set;
		if ($row_display == 'form') {
			$output.= ' <label for="'.$myid.'"><input type="checkbox" id="'
				.$myid.'" name="'.$field['f_field_name'].'[]" value="'.$set.'"';
			if ($record AND isset($record[$field['field_name']])) {
				if (!is_array($record[$field['field_name']])) {
					//	won't be array normally
					$set_array = explode(',', $record[$field['field_name']]);
				} else {
					//just if a field did not pass validation, 
					// set fields become arrays
					$set_array = $record[$field['field_name']];
				}
				$checked = false;
				if (!empty($set_array) && is_array($set_array)) {
					if (in_array($set, $set_array)) 
						$checked = true;
				}
			} elseif (!empty($field['default_select_all'])) {
				$checked = true;
			} else {
				$checked = false;
			}
			if ($checked) {
				$output.= ' checked="checked"';
			} elseif (!empty($field['disabled_ids']) 
				AND is_array($field['disabled_ids'])
				AND in_array($set, $field['disabled_ids'])) {
				$output.= ' disabled="disabled"';
			}
			$output.= '>&nbsp;'.$set_display.'</label>';
			if (count($field['set']) >= 4 OR !empty($field['show_values_as_list']))
				$output.= '<br>';
		} else {
			if (in_array($set, explode(',', $record[$field['field_name']]))
				AND empty($field['set_show_all_values'])) {
				$myvalue[] = $set_display;
			}
		}
	}
	if ($row_display != 'form' AND !empty($field['set_show_all_values'])) {
		// TODO: use set_title!
		$myvalue = explode(',', $record[$field['field_name']]);
	}
	if ($myvalue) {
		$output .= implode(' | ', $myvalue);
	}
	return $output;
}

/**
 * outputs HTML code for the class-attribute from an array of class names
 *
 * @param array class names
 * @return HTML string with class="classes" or ""
 */
function zz_show_class($attr) {
	if (!$attr) return false;
	$attr = trim(implode(" ", $attr));
	if (!$attr) return false;
	return ' class="'.$attr.'"';
}

/**
 * outputs HTML code for a separation line between fields in a record
 *
 * @param mixed
 *		1 or true: simple HR line
 *		'column_begin', 'column', 'column_end': allows to put form into two columns
 *		'text '.*** like true, but with text printed behind HR
 * @param int colspan
 * @return HTML string
 */
function zz_show_separator($separator, $span = 2) {
	if ($separator == 1)
		return '<tr><td colspan="'.$span.'" class="separator"><hr></td></tr>'."\n";
	elseif ($separator == 'column_begin')
		return '<tr><td><table><tbody>'."\n";
	elseif ($separator == 'column')
		return "</tbody></table>\n</td>\n\n".'<td class="left_separator"><table><tbody>'."\n";
	elseif ($separator == 'column_end')
		return "</tbody></table>\n</td></tr>\n";
	elseif (substr($separator, 0, 5) == 'text ')
		return '<tr><td colspan="'.$span.'" class="separator"><hr>'.substr($separator, 4).'</td></tr>'."\n";
}

/**
 * counts records in hierarchical select
 *
 * @param array $select
 * @param int $subtree
 * @return HTML string
 */
function zz_count_records($select, $subtree) {
	$records = 0;
	// no records below this ID
	if (empty($select[$subtree])) {
		foreach ($select as $mother_id => $field) {
			foreach (array_keys($field) as $id)
				// if there is an ID in this SELECT but no subtree, that
				// means there's only one record
				if ($id == $subtree) return 1;
		}
	}
	foreach ($select[$subtree] AS $id => $field) {
		$records++;
		if (!empty($select[$id])) {
			$records += zz_count_records($select, $id);
		}
	}
	return $records;
}

/**
 * sets auto value depending on existing records
 *
 * @param array $field field for which auto value shall be set
 *		'field_name', 'auto_value', 'default'
 * @param string $sql SQL query of main record
 * @param int $tab number of table (0 = main record, 1...n = detail tables)
 * @param int $rec number of detail record in table $tab
 * @param array $id_field 'value', 'field_name' of main table
 * @param string $table name of main table
 * @return int value for default field
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_set_auto_value($field, $sql, $table, $tab, $rec, $id_field, $main_table) {

	// currently, only 'increment' is supported for auto values
	if ($field['auto_value'] != 'increment') return $field['default'];
	
	$field['default'] = 1;
	
	// get main (sub-)table query, change field order
	$sql = zz_edit_sql($sql, 'ORDER BY', $field['field_name'].' DESC');
	// we just need the field increment is based on
	$sql = zz_edit_sql($sql, 'SELECT', $table.'.'.$field['field_name'], 'replace');
	// we just need the field with the highest value
	$sql = zz_edit_sql($sql, 'LIMIT', '1');

	if ($tab) { 
		// subtable
		if (!empty($id_field['field_name']) AND !empty($id_field['value'])) {
			$sql = zz_edit_sql($sql, 'WHERE', '('.$main_table.'.'
				.$id_field['field_name'].' = '.$id_field['value'].')');
			$last_record = zz_db_fetch($sql, '', 'single value');
			if ($last_record) {
				if ($rec > $last_record)
					$field['default'] = $rec + 1;
				else
					$field['default'] = $last_record + 1;
			} else {
				$field['default'] = $rec + 1;
			}
		} else {
			$field['default'] = $rec + 1;
		}
	} else {
		// main table
		$last_record = zz_db_fetch($sql, '', 'single value');
		if ($last_record) $field['default'] = $last_record + 1;
	}

	return $field['default'];
}

/**
 * HTML output of values, either in <option>, <input> or as plain text
 *
 * @param array $line record from database
 * @param string $id_field_name
 * @param array $record $my_rec['record']
 * @param array $field field definition
 * @param array $zz_conf_record configuration variables adjusted to this record
 * @param string $form (optional) 
 *		false => outputs just the selected and saved value
 *		'reselect' => outputs input element in case there are too many elements,
 *		'form' => outputs option fields
 * @param int $level
 * @param array $hierarchy (optional)
 * @param string $parent_field_name
 * @return string $output HTML output
 * @see zz_form_select_sql()
 */
function zz_draw_select($line, $id_field_name, $record, $field, $zz_conf_record,
	$form = false, $level = 0, $hierarchy = false, $parent_field_name = false) {
	// initialize variables
	if (!isset($field['sql_ignore'])) $field['sql_ignore'] = array();
	elseif (!is_array($field['sql_ignore'])) $field['sql_ignore'] = array($field['sql_ignore']);
	$output = '';
	$i = 1;
	$details = array();
	if ($form == 'reselect')
		$output .= '<input type="text" size="'
			.(!empty($field['size_select_too_long']) ? $field['size_select_too_long'] : 32)
			.'" name="'.$field['f_field_name'].'" value="';
	elseif ($form) {
		$output .= '<option value="'.$line[$id_field_name].'"';
		if ($record AND $line[$id_field_name] == $record[$field['field_name']]) {
			$output.= ' selected="selected"';
		} elseif (!empty($field['disabled_ids']) 
			AND is_array($field['disabled_ids'])
			AND in_array($line[$id_field_name], $field['disabled_ids'])) {
			$output.= ' disabled="disabled"';
		}
		
		if ($hierarchy) $output.= ' class="level'.$level.'"';
		$output.= '>';
	}
	if (!isset($field['show_hierarchy'])) $field['show_hierarchy'] = false;
	if (empty($field['sql_index_only'])) {
		foreach (array_keys($line) as $key) {	
			// $i = 1: field['type'] == 'id'!
			if ($key == $parent_field_name) continue;
			if (is_numeric($key)) continue;
			if ($key == $field['show_hierarchy']) continue;
			if (in_array($key, $field['sql_ignore'])) continue;
			$line[$key] = htmlspecialchars($line[$key]);
			if ($i > 1 AND $line[$key]) 
				$details[] = (strlen($line[$key]) > $zz_conf_record['max_select_val_len']) 
					? (mb_substr($line[$key], 0, $zz_conf_record['max_select_val_len']).'...') 
					: $line[$key]; // cut long values
			$i++;
		}
	} else {
		$key = $id_field_name;
	}
	// remove empty fields, makes no sense
	foreach ($details as $my_key => $value)
		if (!$value) unset ($details[$my_key]);
	// if only the id key is in the query, eg. show databases:
	if (!$details) $details = $line[$key]; 
	if (is_array($details)) $details = implode(' | ', $details);
	$output.= strip_tags($details); // remove tags, leave &#-Code as is
	$level++;
	if ($form == 'reselect') {
		// extra space, so that there won't be a LIKE operator that this value
		// will be checked against!
		$output.= ' ">'; 
	} elseif ($form) {
		$output.= '</option>'."\n";
		if ($hierarchy && isset($hierarchy[$line[$id_field_name]]))
			foreach ($hierarchy[$line[$id_field_name]] as $secondline) {
				if (!empty($field['group'])) {
					unset($secondline[$field['group']]); // not needed anymore
				}
				$output.= zz_draw_select($secondline, $id_field_name, $record, 
					$field, $zz_conf_record, $form, $level, $hierarchy, $parent_field_name);
			}
	}
	return $output;
}

?>