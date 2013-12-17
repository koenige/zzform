<?php

/**
 * zzform
 * Display of single record as a html form+table or for review as a table
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2013 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * HTML output of a single record and its detail records, inside of a FORM with
 * input elements or only for display
 *
 * @param array $ops
 *		'output', 'mode', 'result'
 * @param array $zz_tab
 * @param array $zz_var
 *		'upload_form', 'action'
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
	$records = false;
	if (!empty($_GET['zzaction']) AND strstr($_GET['zzaction'], '-')) {
		$records = explode('-', $_GET['zzaction']);
		$_GET['zzaction'] = $records[0];
		$records = $records[1];
	} elseif (is_array($zz_var['id']['value'])) {
		$records = count($zz_var['id']['value']);
	}
	$action_before_redirect = !empty($_GET['zzaction']) ? $_GET['zzaction'] : '';
	if ($zz_tab[0]['record_action'] OR $action_before_redirect) {
		if ($zz_var['action'] === 'insert' OR $action_before_redirect === 'insert') {
			$formhead = zz_text('record_was_inserted');
		} elseif (($zz_var['action'] === 'update' AND $ops['result'] === 'successful_update')
			OR $action_before_redirect === 'update') {
			$formhead = zz_text('record_was_updated');
		} elseif ($zz_var['action'] === 'delete' OR $action_before_redirect === 'delete') {
			if ($records) {
				if ($records === 1) {
					$formhead = '1 '.zz_text('record_was_deleted');
				} else {
					$formhead = sprintf(zz_text('%s records were deleted'), $records);
				}
			} else {
				$formhead = zz_text('record_was_deleted');
			}
		} elseif (($zz_var['action'] === 'update' AND $ops['result'] === 'no_update')
			OR $action_before_redirect === 'noupdate') {
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
		$output .= '<form action="'.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'];
		// without first &amp;!
		if ($zz_var['extraGET']) 
			$output .= $zz_conf['int']['url']['?&'].substr($zz_var['extraGET'], 5); 
		$output .= '" method="POST"';
		if (!empty($zz_var['upload_form'])) 
			$output .= ' enctype="multipart/form-data"';
		$output .= ' accept-charset="'.$zz_conf['character_set'].'">';
	}

	// Heading inside HTML form element
	if (!empty($zz_var['id']['invalid_value'])) {
		$formhead = '<span class="error">'.sprintf(zz_text('Invalid ID for a record (must be an integer): %s'),
			zz_html_escape($zz_var['id']['invalid_value'])).'</span>';
	} elseif (in_array($ops['mode'], array('edit', 'delete', 'review', 'show'))
		AND !$zz_tab[0][0]['record'] AND $action_before_redirect !== 'delete') {
		$formhead = '<span class="error">'.sprintf(zz_text('There is no record under this ID: %s'),
			zz_html_escape($zz_tab[0][0]['id']['value'])).'</span>';
	} elseif (!empty($zz_tab[0]['integrity'])) {
		$formhead = zz_text('Warning!');
		$tmp_error_msg = 
			zz_text('This record could not be deleted because there are details about this record in other records.')
			.' '.$zz_tab[0]['integrity']['text']."\n";

		if (isset($zz_tab[0]['integrity']['fields'])) {
			$tmp_error_msg .= '<ul>'."\n";
			foreach ($zz_tab[0]['integrity']['fields'] as $del_tab) {
				$tmp_error_msg .= '<li>'.zz_nice_tablenames($del_tab).'</li>'."\n";
			}
			$tmp_error_msg .= '</ul>'."\n";
		} 
		$zz_error[]['msg'] = $tmp_error_msg;
	} elseif (in_array($ops['mode'], $record_form) OR 
		($ops['mode'] === 'show' AND !$action_before_redirect)) {
	//	mode = add | edit | delete: show form
		if (isset($zz_var['id']['values'])) {
			$formhead = zz_text($ops['mode'].' several records');
		} else {
			$formhead = zz_text($ops['mode']).' '.zz_text('a_record');
		}
	} elseif ($zz_var['action'] OR $action_before_redirect) {	
	//	action = insert update review: show form with new values
		if (!$formhead) {
			$formhead = ucfirst(zz_text($zz_var['action']).' '.zz_text('failed'));
		}
	} elseif ($ops['mode'] === 'review') {
		$formhead = zz_text('show_record');
	}
	if ($formhead) {
		$output .= '<div id="record">'."\n<h2>".ucfirst($formhead)."</h2>\n\n";
		$div_record_open = true;
	}

	// output reselect warning messages to user
	$error = zz_error_recheck();
	if ($error) {
		if (!$div_record_open) {
			$output .= '<div id="record">';
			$div_record_open = true;
		}
		$output .= $error;
	}

	// output validation error messages to the user
	zz_error_validation();
	$error = zz_error();
	if ($error) {
		if (!$div_record_open) {
			$output .= '<div id="record">';
			$div_record_open = true;
		}
		$output .= zz_error_output();
	}

	// set display of record (review, form, not at all)

	if ($ops['mode'] === 'delete' OR $ops['mode'] === 'show') {
		$display_form = 'review';
	} elseif (in_array($ops['mode'], $record_form)) {
		$display_form = 'form';
	} elseif ($zz_var['action'] === 'delete') {
		$display_form = false;
	} elseif ($zz_var['action'] AND $formhead) {
		$display_form = 'review';
	} elseif ($zz_var['action']) {
		$display_form = false;
	} elseif ($ops['mode'] === 'review') {
		$display_form = 'review';
	} else
		$display_form = false;
	if (($ops['mode'] === 'edit' OR $ops['mode'] === 'delete' OR $ops['mode'] === 'review'
		OR $ops['mode'] === 'show') 
		AND !$zz_tab[0][0]['record']) {
		$display_form = false;
	}

	if ($display_form) {
		if (!$div_record_open) {
			$output .= '<div id="record">';
			$div_record_open = true;
		}
		// output form if necessary
		$output .= zz_display_records($zz_tab, $ops['mode'], $display_form, $zz_var, $zz_conditions);
	}

	// close HTML form element

	if ($div_record_open) $output .= "</div>\n";
	if ($form_open) $output .= "</form>\n";

	if (!empty($zz_conf['footer_record']['insert']) AND zz_valid_request('insert')) {
		$output .= $zz_conf['footer_record']['insert'];
	} elseif (!empty($zz_conf['footer_record']['update']) AND zz_valid_request(array('update', 'noupdate'))) {
		$output .= $zz_conf['footer_record']['update'];
	} elseif (!empty($zz_conf['footer_record']['delete']) AND zz_valid_request('delete')) {
		$output .= $zz_conf['footer_record']['delete'];
	}	
	
	return $output;
}

/**
 * Display form to add, edit, delete, review a record
 * 
 * @param array $zz_tab		
 * @param string $mode
 * @param string $display	'review': show form with all values for
 *							review; 'form': show form for editing; 
 * @param array $zz_var
 * @param array $zz_conditions
 * @global array $zz_conf
 * @global array $zz_error
 * @return string $string			HTML-Output with all form fields
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_display_records($zz_tab, $mode, $display, $zz_var, $zz_conditions) {
	global $zz_conf;
	global $zz_error;
	
	if (!$display) return false;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$output = '';

	// there is a form to display
	$zz_conf_record = zz_record_conf($zz_conf);
	// check conditions
	if (!empty($zz_conditions['bool'])) {
		zz_conditions_merge_conf($zz_conf_record, $zz_conditions['bool'], $zz_var['id']['value']);
	}

	if (($mode === 'add' OR $mode === 'edit') && !empty($zz_conf['upload_MAX_FILE_SIZE'])
		AND !empty($zz_var['upload_form'])) 
		$output .= zz_form_element('MAX_FILE_SIZE', $zz_conf['upload_MAX_FILE_SIZE'], 'hidden')."\n";
	$output .= '<table>'."\n";

	$cancelurl = $zz_conf['int']['url']['self'];
	if ($base_qs = $zz_conf['int']['url']['qs'].$zz_conf['int']['url']['qs_zzform']) {
		$unwanted_keys = array('mode', 'id', 'add', 'zzaction', 'zzhash');
		$cancelurl.= zz_edit_query_string($base_qs, $unwanted_keys);
	}
	$multiple = !empty($zz_var['id']['values']) ? true : false;
	if ($mode && $mode !== 'review' && $mode !== 'show') {
		$output .= '<tfoot>'."\n";
		$output .= '<tr><th>&nbsp;</th> <td>'; 
		$fieldattr = array();
		switch ($mode) {
		case 'edit':
			if (!$multiple) {
				$elementvalue = zz_text('Update record');
			} else {
				$elementvalue = zz_text('Update records');
			}
			$fieldattr['accesskey'] = 's';
			break;
		case 'delete':
			if (!$multiple) {
				$elementvalue = zz_text('Delete record');
			} else {
				$elementvalue = zz_text('Delete records');
			}
			$fieldattr['accesskey'] = 'd';
			break;
		default:
			if (!$multiple) {
				$elementvalue = zz_text('Add record');
			} else {
				$elementvalue = zz_text('Add records');
			}
			$fieldattr['accesskey'] = 's';
			break;
		}
		$output .= zz_form_element('', $elementvalue, 'submit', false, $fieldattr);
		if (($cancelurl !== $_SERVER['REQUEST_URI'] OR ($zz_var['action']) OR !empty($_POST))
			AND $zz_conf_record['cancel_link']) 
			// only show cancel link if it is possible to hide form 
			// @todo: expanded to action, not sure if this works on add only forms, 
			// this is for re-edit a record in case of missing field values etc.
			$output .= ' <a href="'.$cancelurl.'">'.zz_text('Cancel').'</a>';
		$output .= '</td></tr>'."\n";
		$output .= '</tfoot>'."\n";
	} else {
		if ($zz_conf_record['access'] !== 'add_only') {
			$output .= '<tfoot>'."\n";
			if ($zz_conf_record['edit']) {
				$output .= '<tr><th>&nbsp;</th> <td class="reedit">';
				if (empty($zz_conf_record['no_ok']))
					$output .= '<a href="'.$cancelurl.'">'.zz_text('OK').'</a> | ';
				$id_link = sprintf('&amp;id=%d', $zz_var['id']['value']);
				if (!empty($zz_var['where_with_unique_id'])) $id_link = '';
				$edit_link = 'mode=edit'.$id_link.$zz_var['extraGET'];
				if ($zz_conf['access'] === 'show_after_edit')
					$edit_link = substr($zz_var['extraGET'], 5); // remove &amp;
				$output .= '<a href="'.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
					.$zz_conf['int']['url']['?&'].$edit_link.'">'.zz_text('edit').'</a>';
				if ($zz_conf_record['delete']) $output .= ' | <a href="'
					.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
					.$zz_conf['int']['url']['?&'].'mode=delete'.$id_link
					.$zz_var['extraGET'].'">'.zz_text('delete').'</a>';
				if ($zz_conf_record['copy']) {
					$output .= sprintf(
						' | <a href="%s%s%smode=add&amp;source_id=%d%s">'.zz_text('Copy').'</a>'
						, $zz_conf['int']['url']['self'], $zz_conf['int']['url']['qs']
						, $zz_conf['int']['url']['?&'], $zz_var['id']['value']
						, $zz_var['extraGET']
					);
				}
				$output .= '</td></tr>'."\n";
			}
			if (!empty($zz_conf_record['details'])) {
				$output .= '<tr><th>&nbsp;</th><td class="editbutton">'
					.zz_show_more_actions($zz_conf_record, $zz_var['id']['value'], 
					(!empty($zz_tab[0][0]['POST']) ? $zz_tab[0][0]['POST'] : array()))
					.'</td></tr>'."\n";
			}
			if (empty($zz_conf_record['details']) AND !$zz_conf_record['edit']
				AND $zz_conf_record['cancel_link']) {
				$output .= '<tr><th>&nbsp;</th><td class="editbutton">'
					.' <a href="'.$cancelurl.'">'.zz_text('Cancel').'</a>'
					.'</td></tr>'."\n";
			}			
			$output .= '</tfoot>'."\n";
		}
	}
	$output .= zz_show_field_rows($zz_tab, $mode, $display, $zz_var, $zz_conf_record);
	if ($zz_error['error']) return zz_return(false);
	$output .= '</table>'."\n";
	if ($multiple) {
		foreach ($zz_var['id']['values'] as $id_value) {
			$output .= zz_form_element($zz_var['id']['field_name'].'[]', $id_value, 'hidden')."\n";
		}
	} elseif ($mode === 'delete') {
		$output .= zz_form_element($zz_var['id']['field_name'], $zz_var['id']['value'], 'hidden')."\n";
	}
	if ($mode && $mode !== 'review' && $mode !== 'show') {
		switch ($mode) {
			case 'add': $submit = 'insert'; break;
			case 'edit': $submit = 'update'; break;
			case 'delete': $submit = 'delete'; break;
		}
		$output .= zz_form_element('zz_action', $submit, 'hidden');
		if ($zz_conf['referer'])
			$output .= zz_form_element('zz_referer', $zz_conf['referer'], 'hidden');
		if (isset($_GET['file']) && $_GET['file']) 
			$output .= zz_form_element('file', zz_html_escape($_GET['file']), 'hidden');
	}
	if ($display === 'form') {
		foreach ($zz_tab as $tab => $my_tab) {
			if (empty($my_tab['subtable_deleted'])) continue;
			foreach ($my_tab['subtable_deleted'] as $deleted_id)
				$output .= zz_form_element('zz_subtable_deleted['
					.$my_tab['table_name'].'][]['.$my_tab['id_field_name']
					.']', $deleted_id, 'hidden');
		}
	}
	return zz_return($output);
}

/**
 * HTML output of all field rows
 *
 * @param array $zz_ab
 * @param string $mode
 * @param array $zz_var 
 *		function calls itself and uses 'horizontal_table_head'
 *		internally, therefore &$zz_var
 * @param array $zz_conf_record
 * @param int $tab (optional, default = 0 = main table)
 * @param int $rec (optional, default = 0 = main record)
 * @param string $formdisplay (optional)
 * @param string $extra_lastcol (optional)
 * @param int $table_count (optional)
 * @param bool $show_explanation (optional)
 * @return string HTML output
 */
function zz_show_field_rows($zz_tab, $mode, $display, &$zz_var, $zz_conf_record,
	$tab = 0, $rec = 0, $formdisplay = 'vertical', $extra_lastcol = false,
	$table_count = 0, $show_explanation = true) {

	global $zz_error;
	global $zz_conf;	// Config variables
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$my_rec = $zz_tab[$tab][$rec];
	if (empty($my_rec['fields'])) zz_return(false);

	$append_next = '';
	$old_append_next_type = '';
	$old_add_details_where = '';
	if ($tab) {
		if (!empty($zz_conf['int']['append_next_type']))
			$old_append_next_type = $zz_conf['int']['append_next_type'];
		if (!empty($zz_conf['int']['add_details_where']))
			$old_add_details_where = $zz_conf['int']['add_details_where'];
	}
	$zz_conf['int']['append_next_type'] = '';
	$zz_conf['int']['add_details_where'] = '';
	$append_explanation = array();
	$matrix = array();

	zz_record_field_focus($zz_tab, $tab, $rec);
	
	$firstrow = true;
	$my_where_fields = isset($zz_var['where'][$zz_tab[$tab]['table_name']])
		? $zz_var['where'][$zz_tab[$tab]['table_name']] : array();
	// this is for 0 0 main record:
	$row_display = $my_rec['access'] ? $my_rec['access'] : $display;

	$multiple = !empty($zz_var['id']['values']) ? true : false;
	foreach ($my_rec['fields'] as $fieldkey => $field) {
		if (!$field) continue;
		if (!empty($field['hide_in_form'])) continue;
		if (isset($field['multiple_edit']) AND !$field['multiple_edit']
			AND $multiple) continue;
		if ($field['type'] === 'timestamp' AND empty($my_rec['id']['value'])) {
			// don't show timestamp in add mode
			continue;
		}
		if ($field['type'] === 'foreign_key' 
			OR $field['type'] === 'translation_key' 
			OR $field['type'] === 'detail_key') {
			// this must not be displayed, for internal link only
			continue; 
		}
		if ($field['type'] === 'option'
			AND $mode !== 'edit' AND $mode !== 'add') {
			// options will only be shown in edit mode
			continue;
		}

		// initialize variables
		if (!$append_next) {
			$out['tr']['attr'] = array();
			$out['th']['attr'] = array();
			$out['th']['content'] = '';
			$out['th']['show'] = true;
			$out['td']['attr'] = array();
			$out['td']['content'] = '';
			$out['separator'] = '';
			$out['separator_before'] = '';
		}
		
		// $tab means subtable, since main table has $tab = 0
		if ($tab) {
			$field['f_field_name'] = $zz_tab[$tab]['table_name'].'['.$rec.']['.$field['field_name'].']';
			$field['select_field_name'] = $zz_tab[$tab]['table_name'].'[]['.$field['field_name'].']';
		} elseif (isset($field['field_name'])) {
			$field['f_field_name'] = $field['field_name'];
			$field['select_field_name'] = $field['field_name'];
		}
		if (!empty($field['format']) AND empty($field['hide_format_in_title_desc'])) { 
			// formatted fields: show that they are being formatted!
			if (!isset($field['title_desc'])) $field['title_desc'] = '';
			$field['title_desc'] .= ' ['.(!empty($zz_conf['format'][$field['format']]['link']) 
				? '<a href="'.$zz_conf['format'][$field['format']]['link'].'" target="help">' : '')
				.(ucfirst($field['format']))
				.(!empty($zz_conf['format'][$field['format']]['link']) ? '</a>' : '').']';
		}
		if (!empty($field['js'])) {
			$field['explanation'] .= zz_record_js($field);
		}

		if ($field['type'] === 'subtable') {
			$field_display = !empty($field['access']) ? $field['access'] : $display;
			if (empty($field['form_display'])) $field['form_display'] = 'vertical';
		} else {
			$field_display = $row_display;
		}
		if ($field_display !== 'form' OR !$show_explanation) {
			$field['explanation'] = '';
			$field['explanation_top'] = '';
		}
		if (!empty($field['explanation_top']))
			$out['td']['content'] .= '<p class="explanation">'.$field['explanation_top'].'</p>';

		// initalize class values
		if (!isset($field['class'])) $field['class'] = array();
		elseif (!is_array($field['class'])) $field['class'] = array($field['class']);

		// add classes
		if ($field['type'] === 'id') {
			if (empty($field['show_id']))
				$field['class'][] = 'idrow';
		} elseif ($firstrow) {
			$field['class'][] = 'firstrow';
			$firstrow = false;
		}
		if ($tab AND in_array($field['type'], array('id', 'timestamp'))) {
			$field['class'][] = 'hidden';
		}

		if (!$append_next) {
			$out['tr']['attr'][] = implode(' ', $field['class']);
			if (!(isset($field['show_title']) && !$field['show_title'])) {
				if (!empty($field['title_append'])) {
					// just for form, change title for all appended fields
					$out['th']['content'] .= $field['title_append'];
				} else { 
					$out['th']['content'] .= $field['title'];
				}
				if (!empty($field['title_desc']) && $field_display === 'form') {
					$out['th']['content'] .= '<p class="desc">'.$field['title_desc'].'</p>';
				}
			} elseif (!$tab) {
				// for main record, show empty cells
				$out['th']['content'] = '';
			} else {
				$out['th']['show'] = false;
			}
		} else {
			// check that error class does not get lost (but only error, no hidden classes)
			if (in_array('error', $field['class']))
				$out['tr']['attr'][] = 'error'; 
		}

		if ($field['type'] === 'subtable' AND $field['form_display'] === 'set') {
			$sub_tab = $field['subtable'];
			$fields = $zz_tab[$sub_tab][0]['fields'];
			$out['td']['content'] .= zz_field_set($field, $fields, $field_display, $zz_tab[$sub_tab]['existing']);
		} elseif ($field['type'] === 'subtable') {
			//	Subtable
			$sub_tab = $field['subtable'];
			if (empty($field['title_button'])) $field['title_button'] = strip_tags($field['title']); 
			$out['th']['attr'][] = 'sub-add';
			if (empty($field['tick_to_save'])) {
				// no formatting as a subtable if tick_to_save is used
				$out['td']['attr'][] = 'subtable';
			}
			$zz_var['horizontal_table_head'] = false;
			// go through all detail records
			$table_open = false;
			$firstsubtable_no = NULL;
			$c_subtables = 0;

			$subtables = array_keys($zz_tab[$sub_tab]);
			foreach (array_keys($subtables) as $this_rec)
				if (!is_numeric($subtables[$this_rec])) unset($subtables[$this_rec]);
			foreach ($subtables as $sub_rec) {
				// show all subtables which are not deleted but 1 record as a minimum
				if ($zz_tab[$sub_tab][$sub_rec]['action'] === 'delete'
					AND (empty($zz_tab[$sub_tab]['records'])
						AND ($sub_rec + 1) !== $zz_tab[$sub_tab]['min_records'])) continue;
				// don't show records which are being ignored
				if ($zz_tab[$sub_tab][$sub_rec]['action'] === 'ignore'
					AND $field_display !== 'form') continue;
				// don't show records which are deleted with tick_to_save
				if ($zz_tab[$sub_tab][$sub_rec]['action'] === 'delete'
					AND $field_display !== 'form'
					AND !empty($field['tick_to_save'])) continue;
				if ($zz_tab[$sub_tab][$sub_rec]['action'] === 'delete'
					AND $field_display !== 'form' AND $zz_var['action']) continue;

				$c_subtables++;

				// get first subtable that will be displayed
				// in order to be able to say whether horizontal table shall be openend		
				if (!isset($firstsubtable_no)) $firstsubtable_no = $sub_rec;
				$lastrow = false;
				$show_remove = false;

				$dont_delete_records = !empty($field['dont_delete_records'])
					? $field['dont_delete_records'] : '';
				if (!empty($field['values'][$sub_rec])) {
					$dont_delete_records = true; // dont delete records with values set
				}
				// just for optical reasons, in case one row allows removing of record
				if ($display === 'form') $lastrow = '&nbsp;'; 
				
				if ($field_display === 'form') {
					if ($zz_tab[$sub_tab]['min_records'] < $zz_tab[$sub_tab]['records']
						&& !$dont_delete_records)
						$show_remove = true;
				}

				// Mode
				if (!empty($field['tick_to_save'])) $show_tick = true;
				$subtable_mode = $mode;
				if ($subtable_mode === 'edit' AND empty($zz_tab[$sub_tab][$sub_rec]['id']['value'])) {
					// no saved record exists, so it's add a new record
					$subtable_mode = 'add';
					if ($field['form_display'] !== 'horizontal' AND !empty($field['tick_to_save'])) {
						$show_tick = false;
					}
				} elseif (empty($zz_tab[$sub_tab][$sub_rec]['id']['value'])) {
					if ($field['form_display'] !== 'horizontal' AND !empty($field['tick_to_save'])) {
						$show_tick = false;
					}
				}
				if (!empty($zz_tab[$sub_tab][$sub_rec]['save_record'])) {
					$show_tick = true;
				}

				if ($field['form_display'] !== 'horizontal' OR $sub_rec == $firstsubtable_no) {
					$out['td']['content'] .= '<div class="detailrecord">';
				}
				if (!empty($field['tick_to_save'])) {
					$fieldattr = array();
					if ($show_tick) $fieldattr['checked'] = true;
					if ($field_display !== 'form') $fieldattr['disabled'] = true;
					
					$out['td']['content'] .= '<p class="tick_to_save">'
						.zz_form_element('zz_save_record['.$sub_tab.']['.$sub_rec.']',
							'', 'checkbox', 'zz_tick_'.$sub_tab.'_'.$sub_rec, $fieldattr).'</p>';
				}
				
				// HTML output depending on form display
				if ($field['form_display'] !== 'horizontal' OR $sub_rec == $firstsubtable_no) {
					// show this for vertical display and for first horizontal record
					$out['td']['content'] .= '<table class="'.$field['form_display'].'">';
					$table_open = true;
				}
				if ($field['form_display'] !== 'horizontal' OR $sub_rec === count($subtables)-1)
					$h_show_explanation = true;
				else
					$h_show_explanation = false;
				if ($show_remove) {
					$removebutton = zz_output_subtable_submit('remove', $field, $sub_tab, $sub_rec);
					if ($field['form_display'] === 'horizontal') {
						$lastrow = $removebutton;	
					}
				}	
				$out['td']['content'] .= zz_show_field_rows($zz_tab, $subtable_mode, 
					$field_display, $zz_var, $zz_conf_record, $sub_tab, $sub_rec,
					$field['form_display'], $lastrow, $sub_rec, $h_show_explanation);
				if ($field['form_display'] !== 'horizontal') {
					$out['td']['content'] .= '</table></div>'."\n";
					$table_open = false;
				}
				if ($show_remove) {
					if ($field['form_display'] !== 'horizontal') {
						$out['td']['content'] .= $removebutton;
					}
				}
			}
			if ($table_open) {
				$out['td']['content'] .= '</table></div>'."\n";
			}
			if (!$c_subtables AND !empty($field['msg_no_subtables'])) {
				// There are no subtables, optional: show a message here
				$out['td']['content'] .= $field['msg_no_subtables'];
			}
			if ($field_display === 'form' 
				AND $zz_tab[$sub_tab]['max_records'] > $zz_tab[$sub_tab]['records'])
				$out['td']['content'] .= zz_output_subtable_submit('add', $field, $sub_tab);
		} else {
			//	"Normal" field

			// write values into record, if detail record entry shall be preset
			if (!empty($zz_tab[$tab]['values'][$table_count][$fieldkey])) {
				$field['value'] = $zz_tab[$tab]['values'][$table_count][$fieldkey];
				if ($field['type'] === 'select') {
					$field['type_detail'] = $field['type'];
					$field['type'] = 'predefined';
				}
			}
			
			if ($tab AND $field['required']
				AND $zz_tab[$tab]['max_records'] !== $zz_tab[$tab]['min_records_required']) {
				// support for required for subtable is too complicated so far, 
				// because the whole subtable record may be optional
				// just allow this for all required subrecords
				$field['required'] = false;
			}
			if ($field['required'] AND !empty($field['upload_value'])) {
				// in case there is no value, it will come from an upload field
				$field['required'] = false;
			}

			// option fields must have type_detail set, these are normal fields in form view
			// but won't be saved to database
			if ($field['type'] === 'option') {
				// option as normal field, set to type_detail for display form
				$field['type'] = $field['type_detail'];
				$is_option = true;
			} else {
				$is_option = false;
			}

			// append
			if (!$append_next) {
				$close_span = false;
			} else {
				$close_span = true;
				$out['td']['content'] .= '<span'
					.($field['class'] ? ' class="'.implode(' ', $field['class']).'"' : '')
					.'>'; 
			}
			if (!empty($field['append_next'])) {
				$append_next = true;
				if (!empty($field['explanation'])) {
					$append_explanation[] = $field['explanation'];
					$field['explanation'] = '';
				}
			} else {
				$append_next = false;
			}

			// field size, maxlength
			if (!isset($field['size'])) {
				if ($field['type'] === 'number') {
					$field['size'] = 16;
		 		} else {
		 			$field['size'] = 32;
		 		}
			}
		 	if ($field['type'] === 'ipv4') {
		 		$field['size'] = 16;
		 		$field['maxlength'] = 16;
			} elseif ($field['type'] === 'time') {
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
					&& ($zz_tab[0][0]['action'] !== 'review')) { //  OR )
					
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
				if ($field['type'] === 'select') $field['type_detail'] = 'select';
				elseif (!isset($field['type_detail'])) $field['type_detail'] = false;
				$field['type'] = 'predefined';
			}
			if (empty($field['value'])) {
				// Check if filter is applied to this field, set filter value as default value
				$default = zz_record_filter_as_default($field['field_name']);
				if ($default) $field['default'] = $default;
			}

			if (!empty($field['default']) AND empty($field['value'])) {
				// look at default only if no value is set - value overrides default
				if (($mode === 'add' && !$my_rec['record']) OR !empty($is_option)
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
			
			if ($field['type'] === 'write_once' AND ($mode === 'add' OR $zz_var['action'] === 'insert')) {
				$field['type'] = $field['type_detail'];
			}
			if (!isset($my_rec['record_saved'])) $my_rec['record_saved'] = NULL;
			if (!isset($my_rec['images'])) $my_rec['images'] = NULL;

			switch ($field['type']) {
			case 'id':
				$outputf = zz_field_id($field, $my_rec['id']['value']);
				break;

			case 'predefined':
			case 'identifier':
			case 'hidden':
				if (!empty($my_where_fields[$field['field_name']])) {
					$field['value'] = $my_where_fields[$field['field_name']];
				}

				$outputf = zz_field_hidden($field, $my_rec['record'], $my_rec['record_saved'], $mode);
				if (!empty($zz_error['error'])) {
					zz_error();
					return zz_return(false);
				}
				break;

			case 'timestamp':
				$outputf = zz_field_timestamp($field, $my_rec['record'], $mode);
				break;

			case 'unix_timestamp':
				$outputf = zz_field_unix_timestamp($field, $field_display, $my_rec['record']);
				break;

			case 'foreign':
				$outputf = zz_field_foreign($field, $my_rec['id']['value']);
				break;

			case 'password':
				$outputf = zz_field_password($field, $field_display, $my_rec['record'], $zz_var['action']);
				break;

			case 'password_change':
				$outputf = zz_field_password_change($field, $field_display);
				break;

			case 'url':
				$field['max_select_val_len'] = $zz_conf_record['max_select_val_len'];
			case 'text':
			case 'time':
			case 'enum':
			case 'mail':
			case 'mail+name':
			case 'datetime':
			case 'ipv4':
				$outputf = zz_field_text($field, $field_display, $my_rec['record']);
				break;

			case 'ip':
				$outputf = zz_field_ip($field, $field_display, $my_rec['record']);
				break;

			case 'number':
				$outputf = zz_field_number($field, $field_display, $my_rec['record']);
				break;

			case 'date':
				$outputf = zz_field_date($field, $field_display, $my_rec['record']);
				break;

			case 'memo':
				$outputf = zz_field_memo($field, $field_display, $my_rec['record']);
				break;

			case 'select':
				// SELECT field, might be #1 foreign_key (sql query needed), enum or set
				if (!empty($field['sql'])) {
					// #1 SELECT with foreign key

					// set SQL
					if (!empty($field['sql_without_id'])) $field['sql'] .= $my_rec['id']['value'];
					// check for 'sql_where'
					if ($my_where_fields) {
						$field['sql'] = zz_form_select_sql_where($field, $my_where_fields);
					}
					// check for 'sql_where_with_id'
					if (!empty($field['sql_where_with_id']) AND !empty($zz_var['id']['value'])) {
						$field['sql'] = zz_edit_sql($field['sql'], 'WHERE', 
							sprintf("%s = %d", $zz_var['id']['field_name'], $zz_var['id']['value'])
						);
					}

					// write some values into $fields
					$field['max_select'] = $zz_conf_record['max_select'];
					$field['max_select_val_len'] = $zz_conf_record['max_select_val_len'];

					$outputf = zz_field_select_sql($field, $display, $my_rec['record'], 
						$zz_tab[$tab]['db_name'].'.'.$zz_tab[$tab]['table']);

				} elseif (isset($field['set_folder'])) {
					// #2a SELECT with set_folder
					$outputf = zz_field_select_set_folder($field, $field_display, $my_rec['record'], $rec);

				} elseif (isset($field['set_sql'])) {
					// #2 SELECT with set_sql
					$field['sql'] = $field['set_sql'];
					// check for 'sql_where'
					if ($my_where_fields) {
						$field['sql'] = zz_form_select_sql_where($field, $my_where_fields);
					}
					$outputf = zz_field_select_set_sql($field, $field_display, $my_rec['record'], $rec);

				} elseif (isset($field['set'])) {
					// #3 SELECT with set
					$outputf = zz_field_select_set($field, $field_display, $my_rec['record'], $rec);

				} elseif (isset($field['enum'])) {
					// #4 SELECT with enum
					$outputf = zz_field_select_enum($field, $field_display, $my_rec['record']);

				} else {
					// #5 SELECT without any source = that won't work ...
					$outputf = zz_text('no_source_defined').'. '.zz_text('no_selection_possible');
				}
				break;

			case 'image':
			case 'upload_image':
				$outputf = zz_field_image($field, $field_display, $my_rec['record'], 
					$my_rec['record_saved'], $my_rec['images'], $mode, $fieldkey);
				zz_error();
				$outputf .= zz_error_output();
				break;

			case 'write_once':
			case 'display':
				$outputf = zz_field_display($field, $my_rec['record'], $my_rec['record_saved']);
				break;

			case 'calculated':
				$outputf = zz_field_calculated($field, $my_rec['record'], $mode);
				break;

			default:
				$outputf = '';
			}
			if (!empty($field['unit'])) {
				//if ($my_rec['record']) { 
				//	if ($my_rec['record'][$field['field_name']]) // display unit if record not null
				//		$outputf .= ' '.$field['unit']; 
				//} else {
					$outputf .= ' '.$field['unit']; 
				//}
			}
			if (!empty($default_value)) // unset $my_rec['record'] so following fields are empty
				unset($my_rec['record'][$field['field_name']]); 
			if ($mode && $mode !== 'delete' && $mode !== 'show' && $mode !== 'review'
				AND isset($field['add_details'])) {
				$add_details_sep = strstr($field['add_details'], '?') ? '&amp;' : '?';
				$outputf .= ' <a href="'.$field['add_details'].$add_details_sep
					.'mode=add&amp;referer='.urlencode($_SERVER['REQUEST_URI'])
					.$zz_conf['int']['add_details_where'].'"'
					.(!empty($field['add_details_target']) ? ' target="'.$field['add_details_target'].'"' : '')
					.' id="zz_add_details_'.$tab.'_'.$rec.'_'.$fieldkey.'">['.zz_text('new').' &hellip;]</a>';
			}
			if (($outputf OR $outputf === '0') AND $outputf !== ' ') {
				if (isset($field['prefix'])) $out['td']['content'] .= $field['prefix'];
				if (!empty($field['use_as_label'])) {
					$outputf = '<label for="zz_tick_'.$tab.'_'.$rec.'">'.$outputf.'</label>';
				}
				$out['td']['content'] .= $outputf;
				if (isset($field['suffix'])) $out['td']['content'] .= $field['suffix'];
				else $out['td']['content'] .= ' ';
				if ($field_display === 'form') if (isset($field['suffix_function'])) {
					$vars = '';
					if (isset($field['suffix_function_var']))
						foreach ($field['suffix_function_var'] as $var) {
							$vars .= $var; 
							// @todo: does this really make sense? 
							// looks more like $vars[] = $var. maybe use implode.
						}
					$out['td']['content'] .= $field['suffix_function']($vars);
				}
			} else {
				$out['td']['content'] .= $outputf;
			}
			if (!empty($close_span)) $out['td']['content'] .= '</span>';
			if ($zz_conf['int']['append_next_type'] === 'list' && $field_display === 'form') {
				$out['td']['content'] .= '<li>';
				$zz_conf['int']['append_next_type'] = 'list_end';
			} elseif ($zz_conf['int']['append_next_type'] === 'list_end' && $field_display === 'form') {
				$out['td']['content'] .= '</li>'."\n".'</ul>'."\n";
				$zz_conf['int']['append_next_type'] = false;
			}
			if (!$append_next AND !empty($append_explanation)) {
				$field['explanation'] = implode('<br>', $append_explanation)
					.($field['explanation'] ? '<br>'.$field['explanation'] : '');
				$append_explanation = array();
			}
		}
		if ($field['explanation'])
			$out['td']['content'] .= '<p class="explanation">'.$field['explanation'].'</p>';
		if (!empty($field['separator']))
			$out['separator'] .= $field['separator'];
		if (!empty($field['separator_before']))
			$out['separator_before'] .= $field['separator_before'];
		if (!$append_next) $matrix[] = $out;
	}
	$output = zz_output_field_rows($matrix, $zz_var, $formdisplay, $extra_lastcol);
	// append_next_type is only valid for single table
	$zz_conf['int']['append_next_type'] = $old_append_next_type;
	$zz_conf['int']['add_details_where'] = $old_add_details_where;
	return zz_return($output);
}

/**
 * sets HTML5 element autofocus=true where appropriate
 * i. e. first element of record; if subrecord is added: first element of
 * subrecord; if subrecord is deleted: first element of previous subrecord
 *
 * @param array $zz_tab (optional; required for initalizing $field_focus)
 * @param int $tab (optional; required for initalizing $field_focus)
 * @param int $rec (optional; required for initalizing $field_focus)
 * @return mixed bool true: $field_focus was initalized; string: HTML code
 */
function zz_record_field_focus($zz_tab = false, $tab = 0, $rec = 0) {
	static $field_focus;
	if ($zz_tab) {
		// set field focus
		// set autofocus = true (HTML5)
		if ($tab AND isset($zz_tab[$tab]['subtable_focus'])) {
			if ($zz_tab[$tab]['subtable_focus'] === $rec) {
				// set focus on first field of subrecord if some new subrecord was added
				$field_focus = true;
			}
		} elseif (!$tab AND empty($zz_tab[0]['subtable_focus'])) {
			// set focus on first field of main record
			$field_focus = true;
		} else {
			$field_focus = false;			
		}
		return true;
	}

	// set autofocus = true, in case it's wanted
	if (!$field_focus) return '';
	$field_focus = false;
	return true;
}

/**
 * HTML output of table rows for form
 *
 * @param array $matrix matrix of rows
 * @param array $zz_var 'horizontal_table_head'
 * @param string $formdisplay vertical | horizontal
 * @param string $extra_lastcol (optional)
 * @return string HTML output
 */
function zz_output_field_rows($matrix, &$zz_var, $formdisplay, $extra_lastcol) {
	$output = false;
	
	switch ($formdisplay) {
	case 'vertical':
		foreach ($matrix as $index => $row) {
			if ($row['separator_before']) {
				$output .= zz_show_separator($row['separator_before'], $index);
			}
			$output .= '<tr'.zz_show_class($row['tr']['attr']).'>';
			if ($row['th']['show']) {
				$output .= '<th'.zz_show_class($row['th']['attr']).'>'
					.$row['th']['content'].'</th>'."\n";
			}
			$output .=	"\t".'<td'.zz_show_class($row['td']['attr']).'>'
				.$row['td']['content'].'</td></tr>'."\n";
			if ($row['separator']) {
				$output .= zz_show_separator($row['separator'], $index);
			}
		}
		break;
	case 'horizontal':
		if (!empty($matrix) AND $matrix[0]['separator_before']) {
			$output .= zz_show_separator($matrix[0]['separator_before'], 1, count($matrix));
		}
		if (!$zz_var['horizontal_table_head']) { 
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
		$output .= '<tr>';
		foreach ($matrix as $row) {
			$output .= '<td'.zz_show_class(array_merge($row['td']['attr'], $row['tr']['attr']))
				.'>'.$row['td']['content'].'</td>'."\n";
		}
		if ($extra_lastcol) $output .= '<td>'.$extra_lastcol.'</td>';
		$output .= '</tr>'."\n";
		if ($row['separator']) {
			$output .= zz_show_separator($row['separator'], 1, count($matrix));
		}
		break;
	}
	return $output;
}

/**
 * outputs input form element for subtable add/remove
 *
 * @param string $mode add | remove
 * @param array $field
 * @param int $tab
 * @param int $rec (optional)
 * @return string HTML
 */
function zz_output_subtable_submit($mode, $field, $tab, $rec = 0) {
	$fieldattr = array();
	switch ($mode) {
	case 'add':
		$value = sprintf(zz_text('Add %s'), $field['title_button']);
		$name = sprintf('zz_subtables[add][%s]', $tab);
		$fieldattr['class'] = 'sub-add';
		$fieldattr['formnovalidate'] = true;
		return zz_form_element($name, $value, 'submit', false, $fieldattr);
	case 'remove':
		$value = sprintf(zz_text('Remove %s'), $field['title_button']);
		$name = sprintf('zz_subtables[remove][%s][%s]', $tab, $rec);
		$fieldattr['class'] = 'sub-remove-'.$field['form_display'];
		$fieldattr['formnovalidate'] = true;
		return zz_form_element($name, $value, 'submit', false, $fieldattr);
	}
	return '';
}

/**
 * returns filter value as default, if set
 *
 * @param string $field_name
 * @global array $zz_conf
 * @return string
 */
function zz_record_filter_as_default($field_name) {
	global $zz_conf;
	if (!$zz_conf['int']['filter']) return false;

	// check if there's a filter with a field_name 
	// this field will get the filter value as default value
	$filter_field_name = array();
	$unwanted_filter_values = array('NULL', '!NULL');
	foreach (array_keys($zz_conf['int']['filter']) AS $filter_identifier) {
		foreach ($zz_conf['filter'] as $filter) {
			if ($filter_identifier !== $filter['identifier']) continue;
			if (empty($filter['field_name'])) continue;
			if ($filter['field_name'] !== $field_name) continue;
			if (in_array($zz_conf['int']['filter'][$filter_identifier], $unwanted_filter_values)) continue;
			return $zz_conf['int']['filter'][$filter_identifier];
		}
	}
	return false;
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
 * @param int $row index of row, first field will be 0
 * @param int $span colspan
 * @return HTML string
 */
function zz_show_separator($separator, $row, $span = 2) {
	if ($separator == 1 AND $row)
		return '<tr><td colspan="'.$span.'" class="separator"><hr></td></tr>'."\n";
	elseif ($separator === 'column_begin')
		return '<tr><td><table><tbody>'."\n";
	elseif ($separator === 'column')
		return "</tbody></table>\n</td>\n\n".'<td class="left_separator"><table><tbody>'."\n";
	elseif ($separator === 'column_end')
		return "</tbody></table>\n</td></tr>\n";
	elseif (substr($separator, 0, 5) === 'text ')
		return '<tr><td colspan="'.$span.'" class="separator">'
			.($row ? '<hr>' : '').substr($separator, 4).'</td></tr>'."\n";
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_set_auto_value($field, $sql, $table, $tab, $rec, $id_field, $main_table) {

	// currently, only 'increment' is supported for auto values
	if ($field['auto_value'] !== 'increment') return $field['default'];
	
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
 * creates a HTML form element
 *
 * @param string $name name=""
 * @param string $value value=""
 * @param string $type (default: text)
 * @param mixed $id (optional; bool: create from $name; string: use as id)
 * @param array $fieldattr (further attributes, indexed by name => value)
 * @global array $zz_conf
 * @return string HTML code
 */
function zz_form_element($name, $value, $type = 'text', $id = false, $fieldattr = array()) {
	global $zz_conf;

	// escaping for some 'text' elements, not all
	// e. g. geo coordinates and reselect-elements don't need &-values
	// escaped and look better without escaping
	if ($type === 'text') $value = str_replace('&', '&amp;', $value);
	if ($type === 'text_noescape') $type = 'text';
	
	// name
	if ($name AND $type !== 'option') $fieldattr['name'] = $name;

	// prepare ID
	if ($id) {
		if ($id === true) $id = zz_make_id_fieldname($name);
		$fieldattr['id'] = $id;
	}

	// autocomplete?
	$autocomplete = array('password');
	if (in_array($type, $autocomplete)) {
		$fieldattr['autocomplete'] = 'off';
	}

	// autofocus?	
	if ($zz_conf['html_autofocus']) {
		$focus = array('text', 'checkbox', 'radio', 'password', 'textarea', 'select',
			'date', 'datetime', 'email', 'url', 'time');
		if (!isset($fieldattr['autofocus']) AND in_array($type, $focus) 
			AND zz_record_field_focus()) {
			$fieldattr['autofocus'] = true;
		}
	} else {
		$fieldattr['autofocus'] = false;
	}

	// multiple?
	$multiple = array('email');
	if (!isset($fieldattr['multiple']) AND in_array($type, $multiple)) {
		$fieldattr['multiple'] = true;
	}
	
	// value just sometimes? (e. g. tick_to_save does not work with empty value="")
	$values = array('checkbox');
	if ($value AND in_array($type, $values)) {
		$fieldattr['value'] = $value;
	}

	// prepare attributes for HTML
	$attr = '';
	foreach ($fieldattr as $attr_name => $attr_value) {
		if ($attr_value === false) {
			// boolean false
			continue;
		} elseif ($attr_value === true) {
			// boolean true
			$attr .= ' '.$attr_name.'="'.$attr_name.'"';
		} else {
			// default
			$attr_value = str_replace('"', '&quot;', $attr_value);
			$attr .= ' '.$attr_name.'="'.$attr_value.'"';
		}
	}
	
	// return HTML depending on type
	switch ($type) {
	case 'textarea':
		$value = str_replace('&', '&amp;', $value);
		$value = str_replace('<', '&lt;', $value);
		return sprintf('<textarea%s>%s</textarea>', $attr, $value);
	case 'select':
		return sprintf('<select%s>', $attr);
	case 'option':
		$name = str_replace('<', '&lt;', $name);
		$value = str_replace('"', '&quot;', $value);
		return sprintf('<option value="%s"%s>%s</option>', $value, $attr, $name);
	case 'checkbox':
	case 'file':
		// no value attribute (file) or just sometimes (checkbox)
		return sprintf('<input type="%s"%s>', $type, $attr);
	default:
		$value = str_replace('"', '&quot;', $value);
		return sprintf('<input type="%s" value="%s"%s>', $type, $value, $attr);
	}
	return '';
}

/**
 * outputs javascript snippets that improve the usability for records
 *
 * @param array $field
 * @return string HTML code
 */
function zz_record_js($field) {
	switch ($field['js']) {
	case 'select/deselect':
		// works only on type select with set
		if ($field['type'] !== 'select' OR empty($field['set'])) return false;
		$text = ' <a onclick="zz_set_checkboxes(true, \'%s[]\'); return false;" href="#">'.zz_text('Select all').'</a> |
			<a onclick="zz_set_checkboxes(false, \'%s[]\'); return false;" href="#">'.zz_text('Deselect all').'</a>';
		$text = sprintf($text, $field['f_field_name'], $field['f_field_name']);
		return $text;
	default:
		return false;
	}
}

/**
 * --------------------------------------------------------------------
 * F - Field output functions
 * --------------------------------------------------------------------
 */

/**
 * record output of field type 'id'
 *
 * @param array $field
 * @param int $id_value
 * @return string
 */
function zz_field_id($field, $id_value) {
	if (!$id_value) return '('.zz_text('will_be_added_automatically').')&nbsp;';
	return zz_form_element($field['f_field_name'], $id_value, 'hidden', true).$id_value;
}

/**
 * record output of field type 'hidden'
 *
 * @param array $field
 * @param array $record
 * @param array $record_saved
 * @param string $mode
 * @return string
 */
function zz_field_hidden($field, $record, $record_saved, $mode) {
	$value = '';
	$display_value = '';
	$mark_italics = false;
	if (!empty($field['value'])) {
		if ($record AND $field['value'] !== $record[$field['field_name']])
		$display_value = $record[$field['field_name']];
		$value = $field['value'];
		if ($mode !== 'delete') $mark_italics = true;
	} elseif ($record) {
		$value = $record[$field['field_name']];
	}
	if (!$display_value) $display_value = $value;

	$text = '';
	if ($mark_italics) $text .= '<em title="'.zz_text('Would be changed on update').'">';
	if ($value AND !empty($field['type_detail']) AND $field['type_detail'] === 'ipv4') {
		$text .= long2ip($display_value);
	} elseif ($value AND !empty($field['type_detail']) AND $field['type_detail'] === 'date') {
		$text .= zz_date_format($display_value);
	} elseif ($value AND !empty($field['type_detail']) AND $field['type_detail'] === 'select') {
		$detail_key = $display_value ? $display_value : $field['default'];
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
				if (!empty($field['sql_ignore'])) {
					if (!is_array($field['sql_ignore'])) $field['sql_ignore'] = array($field['sql_ignore']);
					foreach ($field['sql_ignore'] as $ignored) {
						unset($select_fields[$ignored]);
					}
				}
				$text .= zz_field_concat($field, $select_fields);
			} else {
				global $zz_error;
				$zz_error[]['msg'] = sprintf(zz_text('Record for %s does not exist.')
					, '<strong>'.$field['title'].'</strong>')
					.' (ID: '.zz_html_escape($value).')';
				$zz_error['error'] = true;
				return false;
			}
		} elseif (isset($field['enum'])) {
			$text .= $display_value;
		}
	} elseif ($record) {
		if (isset($field['timestamp']) && $field['timestamp']) {
			$text .= timestamp2date($display_value);
		} elseif (isset($field['display_field'])) {
			if (!empty($record[$field['display_field']]))
				$text .= zz_htmltag_escape($record[$field['display_field']]);
			elseif (!empty($record_saved[$field['display_field']]))
				$text .= zz_htmltag_escape($record_saved[$field['display_field']]);
			else {
				if (empty($field['append_next']))
					if (!empty($field['value'])) $text .= $field['value'];
					else $text .= '('.zz_text('will_be_added_automatically').')';
			}
		} else {
			if (!empty($display_value)) {
				$text .= zz_htmltag_escape($display_value);
			} elseif (!empty($record_saved[$field['field_name']])) {
				$text .= zz_htmltag_escape($record_saved[$field['field_name']]);
			} else {
				if (empty($field['append_next']))
					if (!empty($field['value'])) $text .= $field['value'];
					else $text .= '('.zz_text('will_be_added_automatically').')';
			}
		}
	} else {
		if ($display_value) {
			if (!empty($field['type_detail']) && $field['type_detail'] === 'select')
				$text .= '('.zz_text('will_be_added_automatically').')&nbsp;';
			else
				$text .= $display_value;
		} else $text .= '('.zz_text('will_be_added_automatically').')&nbsp;';
	}
	if ($mark_italics) $text .= '</em>';
	$text .= zz_form_element($field['f_field_name'], $value, 'hidden', true);
	return $text;
}

/**
 * record output of field type 'timestamp'
 *
 * @param array $field
 * @param array $record
 * @param string $mode
 * @return string
 */
function zz_field_timestamp($field, $record, $mode) {
	// get value
	if (!empty($field['value'])) $value = $field['value'];
	elseif ($record) $value = $record[$field['field_name']];
	else $value = '';

	// return form element
	$text = zz_form_element($field['f_field_name'], $value, 'hidden', true);
	// + return text
	if (!empty($record[$field['field_name']])) {
		$text .= ($mode !== 'delete' ? '<em title="'.zz_text('Would be changed on update').'">' : '')
			.timestamp2date($record[$field['field_name']])
			.($mode !== 'delete' ? '</em>' : '');
	} else {
		$text .= '('.zz_text('will_be_added_automatically').')&nbsp;';
	}
	return $text;
}

/**
 * record output of field type 'unix_timestamp'
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @return string
 */
function zz_field_unix_timestamp($field, $display, $record) {
	if (isset($field['value'])) {
		if ($display === 'form') {
			$text = zz_form_element($field['f_field_name'], $field['value'], 'hidden', true);
		} else {
			$text = $field['value'];
		}
		if (!$record) 
			$text .= '('.zz_text('will_be_added_automatically').')&nbsp;';
		return $text;
	}

	$value = '';
	if ($record AND !empty($record[$field['field_name']])) {
		$timestamp = strtotime($record[$field['field_name']]);
		if ($timestamp AND $timestamp !== -1)
			$record[$field['field_name']] = $timestamp;
		if ($record[$field['field_name']] 
			AND is_numeric($record[$field['field_name']]))
			$value = date('Y-m-d H:i:s', $record[$field['field_name']]);
	}
	if ($display !== 'form') return $value;

	$fieldattr = array();
	if ($field['required']) $fieldattr['required'] = true;
	return zz_form_element($field['f_field_name'], $value, 'text', true, $fieldattr);
}

/**
 * record output of field type 'foreign'
 *
 * @param array $field
 * @param int $id_value
 * @return string
 */
function zz_field_foreign($field, $id_value) {
	// get value
	$sql = $field['sql'].$id_value;
	$foreign_lines = zz_db_fetch($sql, 'dummy_id', 'single value', 'fieldtype foreign');
	if ($foreign_lines) {
		// All Data in one Line! via SQL
		$text = implode(', ', $foreign_lines);
	} else {
		$text = zz_text('no-data-available');
	} 

	// return text
	if (!isset($field['add_foreign'])) return $text;
	if (!$id_value) return $text.zz_text('edit-after-save');
	return $text.' <a href="'.$field['add_foreign'].$id_value
		.'&amp;referer='.urlencode($_SERVER['REQUEST_URI']).'">['
		.zz_text('edit').' &hellip;]</a>';
}

/**
 * record output of field type 'foreign'
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @param string $action
 * @return string
 */
function zz_field_password($field, $display, $record, $action) {
	// return text
	if ($display !== 'form') {
		if ($record) return '('.zz_text('hidden').')';
		else return '';
	}

	// get value
	$value = $record ? $record[$field['field_name']] : '';
	
	// return form element
	$fieldattr = array();
	$fieldattr['size'] = $field['size'];
	if (!empty($field['maxlength']))
		$fieldattr['maxlength'] = $field['maxlength'];
	if ($field['required']) $fieldattr['required'] = true;
	$text = zz_form_element($field['f_field_name'], $value, 'password', true, $fieldattr);
	if ($record AND $action !== 'insert') {
		$value = (!empty($record[$field['field_name'].'--old']) 
		? $record[$field['field_name'].'--old'] 
		: $record[$field['field_name']]);
		$text .= zz_form_element($field['f_field_name'].'--old', $value, 'hidden');
		// this is for validation purposes
		// take saved password (no matter if it's interefered with 
		// maliciously by user - worst case, pwd will be useless)
		// - if old and new value are identical
		// do not apply encryption to password
	}
	return $text;
}

/**
 * record output of field type 'password_change'
 *
 * @param array $field
 * @param string $display
 * @return string
 */
function zz_field_password_change($field, $display) {
	// return text
	if ($display !== 'form') return '********';

	// return form element
	$fieldattr = array();
	$fieldattr['size'] = $field['size'];
	if (!empty($field['maxlength']))
		$fieldattr['maxlength'] = $field['maxlength'];
	$fieldattr['required'] = true;
	return '<table class="subtable">'."\n"
		.'<tr><th><label for="'.zz_make_id_fieldname($field['f_field_name']).'">'
		.zz_text('Old:').' </label></th><td>'
		.zz_form_element($field['f_field_name'], '', 'password', true, $fieldattr)
		.'</td></tr>'."\n"
		.'<tr><th><label for="'.zz_make_id_fieldname($field['f_field_name'].'_new_1').'">'
		.zz_text('New:').' </label></th><td>'
		.zz_form_element($field['f_field_name'].'_new_1', '', 'password', true, $fieldattr)
		.'</td></tr>'."\n"
		.'<tr><th><label for="'.zz_make_id_fieldname($field['f_field_name'].'_new_2').'">'
		.zz_text('New:').' </label></th><td>'
		.zz_form_element($field['f_field_name'].'_new_2', '', 'password', true, $fieldattr)
		.'<p>'.zz_text('(Please confirm your new password twice)').'</td></tr>'."\n"
		.'</table>'."\n";
}

/**
 * record output of field type 'text' and others
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @return string
 */
function zz_field_text($field, $display, $record) {
	// get value
	$value = $record ? $record[$field['field_name']] : '';
	if ($field['type'] === 'ipv4') {
		$value = long2ip($value);
	}

	// return text
	if ($display !== 'form') {
		// show zeros
		if ($value === '') return '';	
		if ($field['type'] === 'url') {
			$linktitle = zz_cut_length($value, $field['max_select_val_len']);
			$linktitle = str_replace('<', '&lt;', $linktitle);
			return '<a href="'.zz_html_escape($value).'">'.$linktitle.'</a>';
		} elseif ($field['type'] === 'mail') {
			$value = str_replace('<', '&lt;', $value);
			return '<a href="mailto:'.$value.'">'.$value.'</a>';
		} elseif ($field['type'] === 'mail+name') {
			$value = str_replace('<', '&lt;', $value);
			return '<a href="mailto:'.rawurlencode($value).'">'.$value.'</a>';
		} else {
			// escape HTML elements
			$value = str_replace('<', '&lt;', $value);
			return $value;
		}
	}

	// return form element
	$fieldtype = 'text';
	if ($field['type'] === 'mail') $fieldtype = 'email';
	// 'url' in Opera does not support relative URLs
	// elseif ($field['type'] === 'url') $fieldtype = 'url';
	// datetime in Safari is like 2011-09-06T20:50Z
	// elseif ($field['type'] === 'datetime') $fieldtype = 'datetime';
	// time is not supported correctly by Google Chrome (adds AM, PM to time
	// and then complains that there's an AM, PM. Great programming, guys!)
//	elseif ($field['type'] === 'time') $fieldtype = 'time';
	$fieldattr = array();
	$fieldattr['size'] = $field['size'];
	if ($field['required']) $fieldattr['required'] = true;
	if (!empty($field['maxlength']))
		$fieldattr['maxlength'] = $field['maxlength'];
	return zz_form_element($field['f_field_name'], $value, $fieldtype, true, $fieldattr);
}

/**
 * record output of field type 'number'
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @return string
 */
function zz_field_number($field, $display, $record) {
	// get value
	$value = $record ? $record[$field['field_name']] : '';
	$suffix = false;
	$formtype = 'text';

	if (!isset($field['number_type'])) $field['number_type'] = false;
	switch ($field['number_type']) {
	case 'bytes':
		// do not reformat bytes as it will result in a loss of information
		break;
	case 'latitude':
	case 'longitude':
		if (!$record) break;
		if ($value === NULL) {
			$value = '';
			break;
		} elseif (isset($field['check_validation']) AND !$field['check_validation']) {
			// validation was not passed, hand back invalid field
			break;
		} elseif (!empty($_POST['zz_subtables'])) {
			// just a detail record was added, value is already formatted
			break;
		}
		// calculate numeric value, hand back formatted value
		$value = zz_geo_coord_in($value, $field['number_type']);
		$value = $value['value'];
		if (!empty($field['geo_display_behind'])) {
			$suffix = zz_geo_coord_out($value, $field['number_type'], $field['geo_display_behind']);
			if ($suffix) $suffix = ' <small>( = '.$suffix.')</small>';
		}
		// no escaping please
	default: // this is for latitude and longitude as well!
		$formtype = 'text_noescape';
		// reformat 1 434,00 EUR and similar values
		$num = zz_check_number($value);
		if ($num === 0) {
			$value = 0;
		} elseif ($num !== NULL) {
			// only apply number_format if it's a valid number
			$value = zz_number_format($num, $field);
		}
		break;
	}
	
	// return text
	if ($display !== 'form') return zz_htmlnoand_escape($value).$suffix;

	// return form element
	$fieldattr = array();
	$fieldattr['size'] = $field['size'];
	if ($field['required']) $fieldattr['required'] = true;
	if (!empty($field['maxlength']))
		$fieldattr['maxlength'] = $field['maxlength'];
	$text = zz_form_element($field['f_field_name'], $value, $formtype, true, $fieldattr);
	return $text.$suffix;
}

/**
 * record output of field type 'ip'
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @return string
 */
function zz_field_ip($field, $display, $record) {
	// get value
	$value = $record ? @inet_ntop($record[$field['field_name']]) : '';
	if (!empty($record[$field['field_name']]) AND !$value) {
		// reselect, value does not need to be converted
		$value = $record[$field['field_name']];
	}

	// return text
	if ($display !== 'form') return $value;

	// return form element
	$fieldattr = array();
	$fieldattr['size'] = 39;
	if ($field['required']) $fieldattr['required'] = true;
	return zz_form_element($field['f_field_name'], $value, 'text', true, $fieldattr);
}

/**
 * record output of field type 'date'
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @return string
 */
function zz_field_date($field, $display, $record) {
	// get value
	$value = $record ? zz_date_format($record[$field['field_name']]) : '';

	// return text
	if ($display !== 'form') return $value;

	// return form element
	$fieldattr = array();
	$fieldattr['size'] = 12;
	if ($field['required']) $fieldattr['required'] = true;
	// HTML5 fieldtype date has bad usability in Opera (calendar only!)
	return zz_form_element($field['f_field_name'], $value, 'text_noescape', true, $fieldattr);
}

/**
 * record output of field type 'memo'
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @return string
 */
function zz_field_memo($field, $display, $record) {
	// get value
	$value = $record ? $record[$field['field_name']] : '';

	// return text
	if ($display !== 'form') {
		if (!$value) return '';
		// always escape html elements, even with format, or
		// results will be weird
		$value = str_replace('<', '&lt;', $value);
		// format will only be applied to non-form output
		if (isset($field['format']))
			$value = $field['format']($value);
		return $value;
	}

	// return form element
	$fieldattr = array();
	!empty($field['cols']) OR $field['cols'] = 60;
	!empty($field['rows']) OR $field['rows'] = 8;
	if ($record) {
		// always add two extra lines
		$calculated_rows = 2;
		// factor for long text to get extra lines because of 
		// long words at line breaks
		$factor = 1.01;
		$parts = explode("\n", $value);
		foreach ($parts as $part) {
			if (strlen($part) < $field['cols']+2) $calculated_rows++;
			else $calculated_rows += ceil(strlen($part)/$field['cols']*$factor); 
		}
		if ($calculated_rows >= $field['rows']) $field['rows'] = $calculated_rows;
		if (!empty($field['rows_max']) AND ($field['rows'] > $field['rows_max']))
			$field['rows'] = $field['rows_max'];
	}
	$fieldattr['rows'] = $field['rows'];
	$fieldattr['cols'] = $field['cols'];
	if ($field['required']) $fieldattr['required'] = true;
	return zz_form_element($field['f_field_name'], $value, 'textarea', true, $fieldattr);
}

/**
 * record output of field type 'set', but as a subtable
 *
 * @param array $fields
 * @param string $display
 * @param array $existing
 * @return string
 */
function zz_field_set($field, $fields, $display, $existing) {
	foreach ($fields as $index => $my_field) {
		$field_names[$my_field['type']] = $my_field['field_name'];
		if ($my_field['type'] === 'select') {
			$sql = $field['sql'];
			$sets = zz_field_query($my_field);
		}
	}
	$exemplary_set = reset($sets);
	$set_id_field_name = '';
	$set_field_names = array();
	foreach (array_keys($exemplary_set) as $key) {
		if (!$set_id_field_name) $set_id_field_name = $key;
		else $set_field_names[] = $key;
	}
	foreach ($sets as $set) {
		$title = array();
		foreach ($set_field_names as $set_field_name) {
			$title[] = $set[$set_field_name];
		}
		$sets_indexed[$set[$set_id_field_name]]['id'] = $set[$set_id_field_name];
		$sets_indexed[$set[$set_id_field_name]]['title'] = implode(' | ', $title);
	}
	$rec_max = 0;
	foreach ($existing as $rec_no => $rec) {
		$sets_indexed[$rec[$field_names['select']]]['rec_id'] = $rec[$field_names['id']];
		$sets_indexed[$rec[$field_names['select']]]['rec_no'] = $rec_no;
		if ($rec_no > $rec_max) $rec_max = $rec_no;
	}
	foreach ($sets_indexed as $index => $set) {
		if (isset($set['rec_no'])) continue;
		$sets_indexed[$index]['rec_no'] = ++$rec_max;
	}
	$outputf = '';
	foreach ($sets_indexed as $set) {
		if ($display === 'form') {
			if (!empty($set['rec_id'])) {
				$outputf .= sprintf(
					'<input type="hidden" name="%s[%d][%s]" value="%d">'
					, $field['table_name'], $set['rec_no'], $field_names['id']
					, $set['rec_id']
				);
				$outputf .= sprintf(
					'<input type="hidden" name="%s[%d][%s]" value="">'
					, $field['table_name'], $set['rec_no'], $field_names['select']
				);
			}
			if (!empty($outputf)) $outputf .= "<br>\n";
			$outputf .= sprintf(
				'<label for="check-%s-%d">'
				.'<input type="checkbox" name="%s[%d][%s]" id="check-%s-%d" value="%d"%s>&nbsp;%s'
				.'</label>'
				, $field['table_name'], $set['rec_no']
				, $field['table_name'], $set['rec_no'], $field_names['select']
				, $field['table_name'], $set['rec_no'], $set['id']
				, (!empty($set['rec_id']) ? ' checked="checked"' : ''), $set['title']
			);
		} elseif (!empty($set['rec_id'])) {
			if (!empty($outputf)) $outputf .= "<br>\n";
			$outputf .= $set['title'];
		}
	}
	return $outputf;
}

/**
 * Output form element type="select", foreign_key with sql query
 * 
 * @param array $field field that will be checked
 *		$field['max_select'] = $zz_conf_record['max_select']
 * @param string $display
 * @param array $record $my_rec['record']
 * @param string $db_table db_name.table
 * @global array $zz_conf just checks for 'modules'[debug]
 * @global array $zz_error
 * @return string HTML output for form
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_field_select_sql($field, $display, $record, $db_table) {
	global $zz_conf;
	global $zz_error;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$lines = zz_field_query($field);
// #1.4 SELECT has no result
	if (!$lines) {
		$outputf = zz_form_element($field['f_field_name'], '', 'hidden', true)
			.zz_text('no_selection_possible');
		zz_error();
		$outputf .= zz_error_output();
		return zz_return($outputf);
	}

// #1.2 SELECT has only one result in the array, and this will be pre-selected 
// because FIELD must not be NULL
	if ($display === 'form' AND count($lines) === 1 
		AND (!zz_db_field_null($field['field_name'], $db_table)
			OR !empty($field['required']))
	) {
		$line = array_shift($lines);
		// get ID field_name which must be 1st field in SQL query
		$id_field_name = array_keys($line);
		$id_field_name = current($id_field_name);
		if ($record AND $record[$field['field_name']] AND $line[$id_field_name] !== $record[$field['field_name']]) {
			$outputf = 'Possible Values: '.$line[$id_field_name]
				.' -- Current Value: '
				.zz_html_escape($record[$field['field_name']])
				.' -- Error --<br>'.zz_text('no_selection_possible');
		} else {
			$outputf = zz_form_element($field['f_field_name'], $line[$id_field_name],
				'hidden', true).zz_draw_select($field, $record, $line, $id_field_name);
		}
		return zz_return($outputf);
	}

// #1.3 SELECT has one or several results, let user select something

	$id_field_name = zz_field_get_id_field_name($lines);
	$detail_record = zz_field_select_get_record($field, $record, $id_field_name);

	// 1.3.1: no form display = no selection, just display the values in the record
	if ($display !== 'form') {
		if (!$detail_record) return zz_return('');
		$outputf = zz_draw_select($field, $record, $detail_record, $id_field_name);
		return zz_return($outputf);
	}

	// ok, we display something!
	// re-index lines by id_field_name if it makes sense
	$lines = zz_field_select_lines($field, $lines, $id_field_name);

	// do we have to display the results hierarchical?
	if (!empty($field['show_hierarchy'])) {
		$lines = zz_field_select_hierarchy($field, $lines, $record, $id_field_name);
	} else {
		$field['show_hierarchy'] = false;
	}
	// subtree might change the amount of lines
	$count_rows = count($lines);

	// 1.3.2: more records than we'd like to display
	if ($count_rows > $field['max_select']) {
		return zz_return(zz_field_select_sql_too_long($field, $record, 
			$detail_record, $id_field_name));
	}

	// 1.3.3: draw RADIO buttons
	if (!empty($field['show_values_as_list'])) {
		return zz_return(zz_field_select_sql_radio($field, $record, $lines));
	}
		
	// 1.3.4: draw a SELECT element
	$fieldattr = array();
	if ($field['required']) $fieldattr['required'] = true;
	$outputf = zz_form_element($field['f_field_name'], '', 'select', true, $fieldattr)."\n";

	// first OPTION element
	// normally don't show a value, unless we only look at a part of a hierarchy
	$fieldvalue = (!empty($field['show_hierarchy_subtree']) 
		AND !empty($field['show_hierarchy_use_top_value_instead_NULL'])) 
		? $field['show_hierarchy_subtree'] : '';
	$fieldattr = array();
	if ($record) if (!$record[$field['field_name']]) $fieldattr['selected'] = true;
	if (isset($field['text_none_selected'])) {
		$display = zz_text($field['text_none_selected']);
	} else {
		$display = zz_text('none_selected');
	}
	$outputf .= zz_form_element($display, $fieldvalue, 'option', '', $fieldattr);

	// further OPTION elements
	$close_select = true;
	if ($count_rows OR !$field['show_hierarchy']) {
		if (!empty($field['group'])) {
			$optgroup = false;
			foreach ($lines as $line) {
				if ($optgroup !== $line[$field['group']]) {
					if ($optgroup) $outputf .= '</optgroup>'."\n";
					$optgroup = $line[$field['group']];
					$outputf .= '<optgroup label="'.$optgroup.'">'."\n";
				}
				unset($line[$field['group']]); // not needed anymore
				$outputf .= zz_draw_select($field, $record, $line, $id_field_name, 'form', 1);
			}
			$outputf .= '</optgroup>'."\n";
		} else {
			foreach ($lines as $line) {
				$outputf .= zz_draw_select($field, $record, $line, $id_field_name, 'form');
			}
		}
	} elseif ($detail_record) {
		// re-edit record, something was posted, ignore hierarchy because 
		// there's only one record coming back
		$outputf .= zz_draw_select($field, $record, $detail_record, $id_field_name, 'form');
	} elseif (!empty($field['show_hierarchy_subtree']) OR ($field['show_hierarchy'])) {
		$outputf = zz_form_element($field['f_field_name'], '', 'hidden', true)
			.zz_text('(This entry is the highest entry in the hierarchy.)');
		$close_select = false;
	} else {
		$outputf = zz_form_element($field['f_field_name'], '', 'hidden', true)
			.zz_text('no_selection_possible');
		$close_select = false;
	}

	if ($close_select) $outputf .= '</select>'."\n";
	zz_error();
	$outputf .= zz_error_output();
	return zz_return($outputf);
}

/**
 * Query records for select element
 *
 * @param array $field 'sql', 'show_hierarchy_subtree', 'max_select'
 * @return array lines from database
 */
function zz_field_query($field) {
	// we do not show all fields if query is bigger than $field['max_select']
	// so no need to query them (only if show_hierarchy_subtree is empty)
	if (empty($field['show_hierarchy_subtree']) AND empty($field['show_hierarchy'])
		AND isset($field['max_select'])) {
		$sql = zz_edit_sql($field['sql'], 'LIMIT', '0, '.($field['max_select']+1));
	} else {
		$sql = $field['sql'];
	}
	// return with warning, don't exit here
	return zz_db_fetch($sql, '_dummy_id_', 'numeric', '', E_USER_WARNING);
}

/**
 * get ID field name, for convenience this may be simply the first
 * field name in the SQL query; sometimes you need to set a field_name
 * for WHERE separately depending on database design
 *
 * @param array $lines
 * @return string
 */
function zz_field_get_id_field_name($lines) {
	$line = current($lines);
	$line = array_keys($line);
	return current($line);
}

/**
 * draws a single INPUT field instead of SELECT/OPTION
 * in case there are too many values
 *
 * @param array $field
 * @param array $record
 * @param array $detail_record
 * @param string $id_field_name
 * @return string
 * @todo AJAX typeaheadfind
 */
function zz_field_select_sql_too_long($field, $record, $detail_record, $id_field_name) {		
	$outputf = zz_form_element('zz_check_select[]', $field['select_field_name'], 'hidden');

	// don't show select but text input instead
	if ($detail_record) {
		$outputf .= zz_draw_select($field, $record, $detail_record, $id_field_name, 'reselect');
		return $outputf;
	}

	// value will not be checked if one detail record is added because 
	// in this case validation procedure will be skipped!
	if (!empty($record[$field['field_name']])) 
		$value = $record[$field['field_name']]; 
	else
		$value = '';
	// add new record
	$fieldattr = array();
	$fieldattr['size'] = !empty($field['size_select_too_long']) ? $field['size_select_too_long'] : 32;
	if ($field['required']) $fieldattr['required'] = true;
	$outputf .= zz_form_element($field['f_field_name'], $value, 'text_noescape', true, $fieldattr);
	return $outputf;
}

/**
 * re-order $lines hierarchically, i. e. as $lines[$parent_id][$id] = $line
 * to avoid infinite recursion, show_hierarchy_same_table will be cheked
 *
 * @param array $field
 * @param array $lines
 * @param array $record
 * @param string $id_field_name
 * @return array
 */
function zz_field_select_hierarchy($field, $lines, $record, $id_field_name) {
	if (!$lines) return array();
	foreach ($lines as $line) {
		// if hierarchy is hierarchy of same table, don't allow to set
		// IDs in hierarchy or below to avoid recursion
		if (!empty($record[$id_field_name])) {
			if (!empty($field['show_hierarchy_same_table'])
				AND $line[$id_field_name] === $record[$id_field_name]) continue;
			if (!empty($field['show_hierarchy_same_table'])
				AND $line[$field['show_hierarchy']] === $record[$id_field_name]) continue;
		}
		// fill in values, index NULL is for uppermost level
		$my_select[(!empty($line[$field['show_hierarchy']]) 
			? $line[$field['show_hierarchy']] : 'NULL')][$line[$id_field_name]] = $line;
	}

	// initalize subtree
	if (!isset($field['show_hierarchy_subtree'])) {
		$field['show_hierarchy_subtree'] = false;
	}
	// if there are no values for subtree, set subtree to false
	if (!empty($field['show_hierarchy_subtree'])
		AND empty($my_select[$field['show_hierarchy_subtree']])) {
		if (empty($lines[$field['show_hierarchy_subtree']])) {
			global $zz_error;
			$zz_error[] = array(
				'msg_dev' => sprintf('Subtree with ID %s does not exist.',
					$field['show_hierarchy_subtree']),
				'error' => E_USER_WARNING
			);
		}
		return array();
	}
	$lines = zz_field_sethierarchy($field, $my_select, $field['show_hierarchy_subtree']);
	return $lines;
}

/**
 * turn hierarchical list with [$parent_id][$id] = $line into
 * [$id] = $line + 'zz_level', reorder values
 *
 * @param array $field
 * @param array $lines
 * @param int $level
 * @return array
 */
function zz_field_sethierarchy($field, $lines, $subtree, $level = 0) {
	static $levels;
	if ($level) $levels = $level;

	if ($subtree) {
		$branches = $lines[$subtree];
	} elseif (!empty($lines['NULL'])) {
		$branches = $lines['NULL'];
	} else {
		// there are no NULL-values, so we either have the uppermost
		// element in the hierarchy or simply no elements at all
		return array();
	}

	foreach ($branches as $id => $line) {
		$line['zz_level'] = $level;
		$tree[$id] = $line;
		if (!empty($lines[$id])) {
			$tree += zz_field_sethierarchy($field, $lines, $id, $level+1);
		}
	}
	if (!$levels) {
		// remove zz_level, it's only top level
		foreach (array_keys($tree) as $id) {
			unset($tree[$id]['zz_level']);
		}
	}
	return $tree;
}	


/**
 * outputs RADIO buttons instead of OPTION/SELECT
 *
 * @param array $field
 * @param array $record
 * @param array $lines
 * @return string
 */
function zz_field_select_sql_radio($field, $record, $lines) {
	$pos = 0;
	$radios = array();
	$level = 0;
	foreach ($lines as $id => $line) {
		$pos++;
		$label = '';
		array_shift($line); // get rid of ID, is already in $id
		$line = zz_field_select_sql_ignore($line, $field);
		if ($field['show_hierarchy']) unset($line[$field['show_hierarchy']]);
		$oldlevel = $level;
		$level = !empty($line['zz_level']) ? $line['zz_level'] : 0;
		unset($line['zz_level']);
		if (!empty($field['group'])) { 
			// group display
			if ($line[$field['group']])
				$label .= '<em>'.$line[$field['group']].':</em> ';
			unset($line[$field['group']]);
		}
		$label .= zz_field_concat($field, $line);
		$field['zz_level'] = $level - $oldlevel;
		$radios[] = zz_field_select_radio_value($field, $record, $id, $label, $pos);
	}
	return zz_field_select_radio($field, $record, $radios);
}

/**
 * get $lines (i. e. all records that will be presented in an
 * SELECT/OPTION HTML element) only if needed, otherwise this will need 
 * a lot of memory usage
 *
 * @param array $field
 * @param array $lines
 * @param string $id_field_name
 * @return array
 */
function zz_field_select_lines($field, $lines, $id_field_name) {
	if (count($lines) <= $field['max_select'] 
		OR !empty($field['show_hierarchy_subtree'])) {
		$details = array();
		// re-index $lines by value from id_field_name
		foreach ($lines as $line)
			$details[$line[$id_field_name]] = $line;
		return $details;
	}
	return $lines;
}

/**
 * record output of field type 'select' with 'set_folder'
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @param int $rec
 * @return string
 */
function zz_field_select_set_folder($field, $display, $record, $rec) {
// #2a SELECT with set_folder
	if (!is_dir($field['set_folder'])) {
		echo '`'.$field['set_folder'].'` is not a folder. Check `["set_folder"]` definition.';
		exit;
	}
	$files = array();
	$handle = opendir($field['set_folder']);
	while ($file = readdir($handle)) {
		if (substr($file, 0, 1) === '.') continue;
		$files[] = $file;
	}
	if (!$files) {
		$field['set'] = array();
	} elseif ($field['set_title'] === true) {
		$field['set_title'] = array();
		foreach ($files as $file) {
			$size = filesize($field['set_folder'].'/'.$file);
			$size = zz_byte_format($size);
			$field['set'][] = $file;
			$field['set_title'][] = $file.' ['.$size.']';
		}
	} else {
		$field['set'][] = $files;
	}
	return zz_field_select_set($field, $display, $record, $rec);
}

/**
 * record output of field type 'select' with 'set_folder'
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @param int $rec
 * @return string $text
 */
function zz_field_select_set_sql($field, $display, $record, $rec) {
	//$field['set_sql'] or key/value
	if (isset($field['set_title']) AND $field['set_title'] === true) {
		$sets = zz_db_fetch($field['sql'], 'dummy_field_name', 'key/value');
		foreach ($sets as $key => $value) {
			$field['set'] = explode(',', $key);
			$field['set_title'] = explode(',', $value);
		}
	} else {
		$sets = zz_db_fetch($field['sql'], '', 'single value');
		$field['set'] = explode(',', $sets);
	}
	$text = zz_field_select_set($field, $display, $record, $rec);
	return $text;
}

/**
 * get single record if there is already something in the database
 *
 * @param array $field field definition
 * @param array $record
 * @return array
 */
function zz_field_select_get_record($field, $record, $id_field_name) {
	if (empty($record[$field['field_name']])) return array();
	
	// get value
	$db_value = $record[$field['field_name']];
	if (substr($db_value, 0, 1) === '"' && substr($db_value, -1) === '"')
		$db_value = substr($db_value, 1, -1);

	// allow to set id_field_name
	if (!empty($field['id_field_name']))
		$where_field_name = $field['id_field_name'];
	else
		$where_field_name = $id_field_name;

	if (substr($field['sql'], 0, 4) === 'SHOW') {
		$sql = zz_edit_sql($field['sql'], 'WHERE', $where_field_name
			.sprintf(' LIKE "%s"', $db_value));
	} else {
		// only check numeric values, others won't give a valid result
		// for these, just display the given values again
		if (!is_numeric($db_value)) return array();
		// get SQL query
		$sql = zz_edit_sql($field['sql'], 'WHERE', $where_field_name
			.sprintf(' = %d', $db_value));
	}

	if (!$sql) $sql = $field['sql'];

	// fetch query
	$detail_records = zz_db_fetch($sql, $id_field_name, '', "record: "
		.$field['field_name'].' (probably \'id_field_name\' needs to be set)');
	
	// only one record?
	if (count($detail_records) === 1) {
		$detail_record = reset($detail_records);
		return $detail_record;
	}
	
	// check for equal record values
	foreach ($detail_records as $line) {
		if ($line[$id_field_name] !== $record[$field['field_name']]) continue;
		return $line;
	}
	return array();
}

/**
 * outputs radio button list
 *
 * @param array $field
 * @param array $record
 * @param array $radios (output of zz_field_select_radio_value())
 * @global array $zz_conf
 * @return string $text
 * @see zz_field_select_radio_none(), zz_field_select_radio_value()
 */
function zz_field_select_radio($field, $record, $radios) {
	// variant: only two or three values next to each other
	if (empty($field['show_values_as_list'])) {
		$text = zz_field_select_radio_none($field, $record);
		foreach ($radios as $radio)
			$text .= $radio[1]."\n";
		return $text;
	}
	
	// variant: more values as a list
	$text = "\n".'<ul class="zz_radio_list">'."\n"
		.'<li>'.zz_field_select_radio_none($field, $record)."</li>\n";
	foreach ($radios as $index => $radio) {
		switch ($radio[0]) {
		case 1:
			$text .= "\n<ul><li>";
			break;
		case 0:
			if ($index) $text .= "</li>\n<li>"; 
			else $text .= "<li>";
			break;
		default:
			for ($i = 0; $i > $radio[0]; $i--) {
				$text .= "</li></ul><li>";
			}
			break;
		}
		$text .= $radio[1];
	}
	$text .= "</li>\n";

	if (empty($field['append_next'])) {
		$text .= '</ul>'."\n";
	} else {
		global $zz_conf;
		$zz_conf['int']['append_next_type'] = 'list';
	}
	return $text;
}

/**
 * radio button list: display first radio button with no value
 *
 * @param array $field
 * @param array $record
 * @return string
 */
function zz_field_select_radio_none($field, $record) {
	$fieldattr = array();
	if ($record) {
		if (!$record[$field['field_name']]) $fieldattr['checked'] = true;
	} else {
		// no value, no default value 
		// (both would be written in my record fieldname)
		$fieldattr['checked'] = true;
	}
	if ($field['required']) $fieldattr['required'] = true;

	$id = zz_make_id_fieldname($field['f_field_name']).'-0';
	if (!isset($field['hide_novalue'])) $field['hide_novalue'] = true;
	return '<label for="'.$id.'"'
		.($field['hide_novalue'] ? ' class="hidden"' : '')
		.'>'
		.zz_form_element($field['f_field_name'], '', 'radio', $id, $fieldattr)
		.'&nbsp;'.zz_text('no_selection').'</label>'."\n";
}

/**
 * radio button list: display radio button with value
 *
 * @param array $field
 * @param array $record
 * @param mixed $value (int, string)
 * @param string $label
 * @param int $pos
 * @return string
 */
function zz_field_select_radio_value($field, $record, $value, $label, $pos) {
	$id = zz_make_id_fieldname($field['f_field_name']).'-'.$pos;
	$fieldattr = array();
	// no === comparison here!
	if ($record AND $value == $record[$field['field_name']]) 
		$fieldattr['checked'] = true;
	if ($field['required']) $fieldattr['required'] = true;
	$element = zz_form_element($field['f_field_name'], $value, 'radio', $id, $fieldattr);
	$level = isset($field['zz_level']) ? $field['zz_level'] : 0;
	return array($level, sprintf(' <label for="%s">%s&nbsp;%s</label>', $id, $element, $label));
	return $text;
}

/**
 * Output form element type="select" with set
 *
 * @param array $field
 *		'set', 'field_name', 'f_field_name'
 * @param string $display
 * @param array $record
 * @param int $rec
 * @return string $output HTML output for form
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_field_select_set($field, $display, $record, $rec) {
	$myvalue = array();
	$output = '';
	$myi = 0;
	if ($display === 'form') {
		// send dummy field to get a response if field content should be deleted
		$myid = 'check-'.$field['field_name'].'-'.$myi.'-'.$rec;
		$output .= zz_form_element($field['f_field_name'].'[]', '', 'hidden', $myid);
	}
	foreach ($field['set'] as $key => $set) {
		$myi++;
		$myid = 'check-'.$field['field_name'].'-'.$myi.'-'.$rec;
		$set_display = zz_print_enum($field, $set, 'full');
		if ($display === 'form') {
			$fieldattr = array();
			if ($record AND isset($record[$field['field_name']])) {
				if (!is_array($record[$field['field_name']])) {
					//	won't be array normally
					$set_array = explode(',', $record[$field['field_name']]);
				} else {
					//just if a field did not pass validation, 
					// set fields become arrays
					$set_array = $record[$field['field_name']];
				}
				$fieldattr['checked'] = false;
				if (!empty($set_array) && is_array($set_array)) {
					if (in_array($set, $set_array)) 
						$fieldattr['checked'] = true;
				}
			} elseif (!empty($field['default_select_all'])) {
				$fieldattr['checked'] = true;
			} else {
				$fieldattr['checked'] = false;
			}
			if (!$fieldattr['checked'] AND !empty($field['disabled_ids']) 
				AND is_array($field['disabled_ids'])
				AND in_array($set, $field['disabled_ids'])) {
				$fieldattr['disabled'] = true;
			}
			// required does not work at least in Firefox with set
			// because identical 'name'-attributes are not recognized
			// if ($field['required']) $fieldattr['required'] = true;
			$output .= ' <label for="'.$myid.'">'
				.zz_form_element($field['f_field_name'].'[]', $set, 'checkbox', $myid, $fieldattr)
				.'&nbsp;'.$set_display.'</label>';
			if (count($field['set']) >= 4 OR !empty($field['show_values_as_list']))
				$output .= '<br>';
		} else {
			if (in_array($set, explode(',', $record[$field['field_name']]))
				AND empty($field['set_show_all_values'])) {
				$myvalue[] = $set_display;
			}
		}
	}
	if ($display !== 'form' AND !empty($field['set_show_all_values'])) {
		// @todo: use set_title!
		$myvalue = explode(',', $record[$field['field_name']]);
	}
	if ($myvalue) {
		$output .= zz_field_concat($field, $myvalue);
	}
	return $output;
}

/**
 * Output form element type="select" with enum
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @global array $zz_conf
 *		string $zz_conf['int']['append_next_type']
 * @return string
 */
function zz_field_select_enum($field, $display, $record) {
	global $zz_conf;

	if ($display !== 'form') {
		$text = '';
		foreach ($field['enum'] as $key => $set) {
			// instead of !== because of numeric values which might be
			// represented by a string
			if ($set != $record[$field['field_name']]) continue;
			$text .= zz_print_enum($field, $set, 'full', $key);
		}
		if (!empty($field['show_values_as_list'])) {
			$zz_conf['int']['append_next_type'] = 'list';
		}
		return $text;
	}

	// check if should be shown as a list
	// and if yes, return a list
	if (count($field['enum']) <= 2) {
		$sel_option = true;
	} elseif (!empty($field['show_values_as_list'])) {
		$sel_option = true;
	} else {
		$sel_option = false;
	}
	if ($sel_option) {
		$myi = 0;
		$radios = array();
		foreach ($field['enum'] as $key => $set) {
			$myi++;
			$label = zz_print_enum($field, $set, 'full', $key);
			$radios[] = zz_field_select_radio_value($field, $record, $set, $label, $myi);
		}
		return zz_field_select_radio($field, $record, $radios);
	}

	$fieldattr = array();
	if ($field['required']) $fieldattr['required'] = true;
	$text = zz_form_element($field['f_field_name'], '', 'select', true, $fieldattr)."\n";
	$fieldattr = array();
	if ($record) { 
		if (!$record[$field['field_name']])
			$fieldattr['selected'] = true;
	} else {
		// no value, no default value (both would be 
		// written in my record fieldname)
		$fieldattr['selected'] = true;
	}
	if (isset($field['text_none_selected'])) {
		$display = zz_text($field['text_none_selected']);
	} else {
		$display = zz_text('none_selected');
	}
	$text .= zz_form_element($display, '', 'option', false, $fieldattr)."\n";
	foreach ($field['enum'] as $key => $set) {
		$fieldattr = array();
		if ($record AND $set == $record[$field['field_name']]) {
			$fieldattr['selected'] = true;
		} elseif (!empty($field['disabled_ids']) 
			AND is_array($field['disabled_ids'])
			AND in_array($set, $field['disabled_ids'])) {
			$fieldattr['disabled'] = true;
		}
		$text .= zz_form_element(zz_print_enum($field, $set, 'full', $key), $set, 'option', false, $fieldattr)."\n";
	}
	$text .= '</select>'."\n";
	return $text;
}

/**
 * add WHERE to $zz['fields'][n]['sql'] clause if necessary
 * 
 * @param array $field field that will be checked
 * @param array $where_fields = $zz_var['where'][$table_name]
 * @global array $zz_conf
 *		$zz_conf['int']['add_details_where']
 * @return array string $field['sql']
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_form_select_sql_where($field, $where_fields) {
	if (empty($field['sql_where'])) return $field['sql'];

	global $zz_conf;
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
				$zz_conf['int']['add_details_where'] .= '&amp;where['.$sql_where[0].']='.$index;
			}
		} elseif (isset($sql_where['where']) AND !empty($where_fields[$sql_where['field_name']])) {
			$where_conditions[] = sprintf($sql_where['where'], $where_fields[$sql_where['field_name']]);
		}
	}
	$field['sql'] = zz_edit_sql($field['sql'], 'WHERE', implode(' AND ', $where_conditions));

	return zz_return($field['sql']);
}

/**
 * HTML output of values, either in <option>, <input> or as plain text
 *
 * @param array $field field definition
 *		'max_select_val_len' = $zz_conf_record['max_select_val_len']
 * @param array $record $my_rec['record']
 * @param array $line record from database
 * @param string $id_field_name
 * @param string $form (optional) 
 *		false => outputs just the selected and saved value
 *		'reselect' => outputs input element in case there are too many elements,
 *		'form' => outputs option fields
 * @param int $addlevel
 * @return string $output HTML output
 * @see zz_field_select_sql()
 */
function zz_draw_select($field, $record, $line, $id_field_name, $form = false, $addlevel = 0) {
	// initialize variables
	$i = 1;
	$details = array();
	if (!isset($field['show_hierarchy'])) $field['show_hierarchy'] = false;
	if (isset($line['zz_level'])) {
		$level = $line['zz_level'];
		unset($line['zz_level']);
	} else {
		$level= '';
	}
	if ($addlevel) $level++;
	if (empty($field['sql_index_only'])) {
		$line = zz_field_select_sql_ignore($line, $field);
		foreach (array_keys($line) as $key) {	
			// $i = 1: field['type'] === 'id'!
			if (is_numeric($key)) continue;
			if ($key === $field['show_hierarchy']) continue;
			if ($i > 1 AND $line[$key]) {
				$details[] = zz_cut_length($line[$key], $field['max_select_val_len']);
			}
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
	if (is_array($details)) $details = zz_field_concat($field, $details);
	$fieldvalue = strip_tags($details); // remove tags, leave &#-Code as is
	if ($form === 'reselect') {
		$fieldattr = array();
		$fieldattr['size'] = !empty($field['size_select_too_long']) ? $field['size_select_too_long'] : 32;
		if ($field['required']) $fieldattr['required'] = true;
		// extra space, so that there won't be a LIKE operator that this value
		// will be checked against!
		$output = zz_form_element($field['f_field_name'], $fieldvalue.' ', 'text_noescape', false, $fieldattr);
	} elseif ($form) {
		$fieldattr = array();
		// check == to compare strings with numbers as well
		if ($record AND $line[$id_field_name] == $record[$field['field_name']]) {
			$fieldattr['selected'] = true;
		} elseif (!empty($field['disabled_ids']) 
			AND is_array($field['disabled_ids'])
			AND in_array($line[$id_field_name], $field['disabled_ids'])) {
			$fieldattr['disabled'] = true;
		}
		if ($level !== '') $fieldattr['class'] = 'level'.$level;
		$output = zz_form_element($fieldvalue, $line[$id_field_name], 'option', false, $fieldattr)."\n";
	} else {
		$output = $fieldvalue;
	}
	return $output;
}

/**
 * remove fields from display which should be ignored
 *
 * @param array $line
 * @param array $field
 * @return array ($line, modified)
 */
function zz_field_select_sql_ignore($line, $field) {
	if (empty($field['sql_ignore'])) return $line;
	if (!is_array($field['sql_ignore']))
		$field['sql_ignore'] = array($field['sql_ignore']);
	if ($keys = array_intersect(array_keys($line), $field['sql_ignore']))
		foreach ($keys as $key) unset($line[$key]);
	return $line;
}

/**
 * Output form element type="image" or "upload_image"
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @param array $record_saved
 * @param array $images
 * @param string $mode
 * @param int $fieldkey
 * @global array $zz_conf
 * @global array $zz_error
 * @return string
 */
function zz_field_image($field, $display, $record, $record_saved, $images, $mode, $fieldkey) {				
	global $zz_conf;
	global $zz_error;
	$text = '';

	if (($mode !== 'add' OR $field['type'] !== 'upload_image')
		AND (empty($field['dont_show_image'])) || !$field['dont_show_image']) {
		$img = false;
		if (isset($field['path']))
			$text = $img = zz_makelink($field['path'], $record, 'image');
		if (!$img AND !empty($record_saved)) {
			$text = $img = zz_makelink($field['path'], $record_saved, 'image');
		}
		if (!$img AND (!isset($field['dont_show_missing']) OR !$field['dont_show_missing'])) {
			$text = '('.zz_text('image_not_display').')';
		}
		if ($text) $text = '<p>'.$text.'</p>';
	}
	if (($mode === 'add' OR $mode === 'edit') && $field['type'] === 'upload_image') {
		if (!isset($field['image'])) {
			$zz_error[] = array(
				'msg' => 'File upload is currently impossible. '
					.zz_text('An error occured. We are working on the '
					.'solution of this problem. Sorry for your '
					.'inconvenience. Please try again later.'),
				'msg_dev' => 'Configuration error. Missing upload_image details.',
				'level' => E_USER_WARNING
			);
			return false;
		}

		$image_uploads = 0;
		foreach ($field['image'] as $imagekey => $image) {
			if (!isset($image['source']) AND !isset($image['source_field'])) {
				$image_uploads++;
			}
		}
		if ($image_uploads > 1) $text .= '<table class="upload">';
		foreach ($field['image'] as $imagekey => $image) {
			if (isset($image['source'])) continue;
			if (isset($image['source_field'])) continue;
			// @todo: if only one image, table is unnecessary
			// title and field_name of image might be empty
			if ($image_uploads > 1) $text .= '<tr><th>'.$image['title'].'</th> <td>';
			$elementname = zz_make_id_fieldname($field['f_field_name']).'['.$image['field_name'].']';
			$text .= zz_form_element($elementname, '', 'file', false);
			if (empty($field['dont_show_file_link'])
				AND $link = zz_makelink($image['path'], (!empty($record_saved) 
					? $record_saved : $record))) {
				$fieldattr = array();
				$fieldattr['autofocus'] = false;
				$text .= '<br><a href="'.$link.'">'.$link
					.'</a>'
					.(($image_uploads > 1 OR !empty($field['optional_image'])) ?
					' (<small><label for="delete-file-'.$fieldkey.'-'.$imagekey
					.'">'.zz_form_element('zz_delete_file['.$fieldkey.'-'.$imagekey.']', 
						'', 'checkbox', 'delete-file-'.$fieldkey.'-'.$imagekey, $fieldattr)
					.'&nbsp;'.zz_text('Delete this file').'</label></small>)'
					: '');
			}
			if (!empty($images[$fieldkey][$imagekey]['error'])) {
				$text .= '<br><small>'.implode('<br>', 
					$images[$fieldkey][$imagekey]['error']).'</small>';
			} else {
				$text .= '<br><small>'.zz_text('Maximum allowed filesize is').' '
					.zz_byte_format($zz_conf['upload_MAX_FILE_SIZE']).'</small>';
			}
			if ($display === 'form' && !empty($image['explanation'])) 
				$text .= '<p class="explanation">'.$image['explanation'].'</p>';
			if ($image_uploads > 1) $text .= '</td></tr>'."\n";
		}
		if ($image_uploads > 1) $text .= '</table>'."\n";
	} elseif (isset($field['image'])) {
		$image_uploads = 0;
		foreach ($field['image'] as $imagekey => $image)
			if (!isset($image['source'])) $image_uploads++;
		if ($image_uploads > 1) {
			$text .= '<table class="upload">';
			foreach ($field['image'] as $imagekey => $image) {
				if (isset($image['source'])) continue;
				if ($link = zz_makelink($image['path'], $record)) {
					$text .= '<tr><th>'.$image['title'].'</th> <td>'
						.'<a href="'.$link.'">'.$link.'</a>'
						.'</td></tr>'."\n";
				}
			}
			$text .= '</table>'."\n";
		}
	}
	return $text;
}

/**
 * Output form element type="display" or "write_once"
 *
 * @param array $field
 * @param array $record
 * @param array $record_saved
 * @return string
 */
function zz_field_display($field, $record, $record_saved) {
	// return text
	// display_value ?
	// internationalization has to be done in zz-fields-definition
	if (isset($field['display_value'])) return $field['display_value']; 
	// no record
	if (!$record) {
		if (isset($field['display_empty'])) return $field['display_empty'];
		else return zz_text('N/A');
	}

	// get value, return text
	$value = '';
	if (isset($field['display_field'])) {
		if (!empty($record[$field['display_field']])) {
			$value = $record[$field['display_field']];
		} elseif (!empty($record_saved[$field['display_field']])) {
			// empty for new record
			$value = $record_saved[$field['display_field']]; // requery
		}
		if (!$value) return '';

		if (!empty($field['translate_field_value']))
			$value = zz_text($value);
		return $value;

	} elseif (isset($field['field_name'])) {
		if (!empty($record[$field['field_name']])) {
			$value = $record[$field['field_name']];
		} elseif (!empty($record_saved[$field['field_name']])) {
			// empty if new record!
			$value = $record_saved[$field['field_name']];
		}
		if (!$value) return '';

		if (!empty($field['display_title']) && in_array($value, 
			array_keys($field['display_title'])))
			$value = $field['display_title'][$value];
		if (!empty($field['translate_field_value']))
			$value = zz_text($value);

		$value = zz_html_escape($value);
		if (isset($field['format'])) {
			$value = $field['format']($value);
		} elseif (isset($field['type_detail'])) {
			switch ($field['type_detail']) {
			case 'date':
				$value = zz_date_format($value);
				break;
			case 'number':
				$value = zz_number_format($value, $field);
				break;
			}	
		}
		return $value;
	}

	// @todo: debug!
	return '<span class="error">'
		.zz_text('Script configuration error. No display field set.').'</span>';
}

/**
 * Output form element type="calculated"
 *
 * @param array $field
 * @param array $record
 * @param string $mode
 * @return string
 */
function zz_field_calculated($field, $record, $mode) {
	if ($mode AND $mode !== 'show') {
		return '('.zz_text('calculated_field').')';
	}
	switch ($field['calculation']) {
	case 'hours':
		$diff = 0;
		foreach ($field['calculation_fields'] as $calc_field)
			if (!$diff) $diff = strtotime($record[$calc_field]);
			else $diff -= strtotime($record[$calc_field]);
		$text = gmdate('H:i', $diff);
		if ($diff < 0) $text = sprintf('<em class="negative">%s</em>', $text);
		return $text;

	case 'sum':
		$sum = 0;
		foreach ($field['calculation_fields'] as $calc_field)
			$sum += $record[$calc_field];
		return $sum;
	}
	// type not supported
	return '';
}

/**
 * sets concat string for fields in select
 *
 * @param array $field
 * @param array $values
 * @return string
 */
function zz_field_concat($field, $values) {
	if (!isset($field['concat_fields'])) $concat = ' | ';
	else $concat = $field['concat_fields'];
	// only concat existing values
	$count = count($values);
	$values = array_values($values);
	for ($i = 0; $i < $count; $i++) {
		if (isset($field['concat_'.$i]) AND !empty($values[$i])) {
			$values[$i] = sprintf($field['concat_'.$i], $values[$i]);
		}
	}
	$values = array_filter($values);
	return implode($concat, $values);
}


?>