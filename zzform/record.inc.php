<?php

/**
 * zzform
 * Display of single record as a html form+table or for review as a table
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * HTML output of a single record and its detail records, inside of a FORM with
 * input elements or only for display
 *
 * @param array $ops
 *		'output', 'mode', 'result'
 * @param array $record = $zz['record']
 * @param array $zz_tab
 * @param array $zz_conditions
 * @global array $zz_conf
 * @return string $output
 */
function zz_record($ops, $record, $zz_tab, $zz_conditions) {
	global $zz_conf;

	zz_record_dynamic_referer($ops['mode'], $ops['record']);

	// there might be now a where value for this record
	if (!empty($record['where'][$zz_tab[0]['table']])) {
		foreach ($record['where'][$zz_tab[0]['table']] as $field_name => $value) {
			if ($value) continue;
			if (empty($zz_tab[0][0]['record'][$field_name])) continue;
			$record['where'][$zz_tab[0]['table']][$field_name] = $zz_tab[0][0]['record'][$field_name];
		}
	}

	$record['formhead'] = false;
	$records = false;
	if (!empty($_GET['delete'])) {
		$records = zz_check_get_array('delete', 'is_int');
	} elseif (is_array($zz_conf['int']['id']['value'])) {
		$records = count($zz_conf['int']['id']['value']);
	}
	if (isset($_GET['delete'])) {
		$action_before_redirect = 'delete';
	} elseif (isset($_GET['insert'])) {
		$action_before_redirect = 'insert';
	} elseif (isset($_GET['update'])) {
		$action_before_redirect = 'update';
	} elseif (isset($_GET['noupdate'])) {
		$action_before_redirect = 'noupdate';
	} else {
		$action_before_redirect = '';
	}
	if ($zz_tab[0]['record_action'] OR $action_before_redirect) {
		if ($record['action'] === 'insert' OR $action_before_redirect === 'insert') {
			$record['formhead'] = wrap_text('Record was inserted');
		} elseif (($record['action'] === 'update' AND $ops['result'] === 'successful_update')
			OR $action_before_redirect === 'update') {
			$record['formhead'] = wrap_text('Record was updated');
		} elseif ($record['action'] === 'delete' OR $action_before_redirect === 'delete') {
			if ($records) {
				if ($records === 1) {
					$record['formhead'] = wrap_text('1 record was deleted');
				} else {
					$record['formhead'] = wrap_text('%d records were deleted', ['values' => $records]);
				}
				$action_before_redirect = '';
			} else {
				$record['formhead'] = wrap_text('Record was deleted');
			}
		} elseif (($record['action'] === 'update' AND $ops['result'] === 'no_update')
			OR $action_before_redirect === 'noupdate') {
			$record['formhead'] = wrap_text('Record was not updated (no changes were made)');
		}
	}

	$record_form = ['edit', 'delete', 'add', 'revise'];
	if (in_array($ops['mode'], $record_form)) {
		$record['form'] = true;
		if ($csp_frame_ancestors = wrap_setting('csp_frame_ancestors')) {
			wrap_setting_add('extra_http_headers', "Content-Security-Policy: frame-ancestors 'self' ".$csp_frame_ancestors);
		} else {
			wrap_setting_add('extra_http_headers', 'X-Frame-Options: Deny');
			wrap_setting_add('extra_http_headers', "Content-Security-Policy: frame-ancestors 'self'");
		}
		$record['upload'] = !empty($record['upload_form']) ? true : false;
		if (!empty($ops['form'])) $record['hook_output'] = $ops['form'];
	} else {
		$record['form'] = false;
	}

	// Heading inside HTML form element
	if (!empty($zz_conf['int']['id']['invalid_value'])) {
		$record['formhead'] = wrap_text('Invalid ID for a record (must be an integer): %s',
			['values' => wrap_html_escape($zz_conf['int']['id']['invalid_value'])]);
		$record['formhead_error'] = true;
		wrap_static('page', 'status', 404);
	} elseif (in_array($ops['mode'], ['edit', 'delete', 'review', 'show', 'revise'])
		AND !$zz_tab[0][0]['record'] AND $action_before_redirect !== 'delete') {
		$sql = 'SELECT %s FROM %s WHERE %s = %d';
		$sql = sprintf($sql, $zz_conf['int']['id']['field_name'], $zz_tab[0]['table'], $zz_conf['int']['id']['field_name'], $zz_conf['int']['id']['value']);
		$id_exists = zz_db_fetch($sql, '', 'single value');
		if ($id_exists) {
			$record['formhead'] = wrap_text('Sorry, it is not possible to access the ID %d from here.',
				['values' => wrap_html_escape($zz_conf['int']['id']['value'])]);
			$record['formhead_error'] = true;
			wrap_static('page', 'status', 403);
		} else {
			$sql = 'SELECT MAX(%s) FROM %s';
			$sql = sprintf($sql, $zz_conf['int']['id']['field_name'], $zz_tab[0]['table']);
			$max_id = zz_db_fetch($sql, '', 'single value');
			if ($max_id > $zz_conf['int']['id']['value']
				AND $zz_conf['int']['id']['value'] > 0) {
				// This of course is only 100% correct if it is an incremental ID
				$record['formhead'] = wrap_text('The record with the ID %d was already deleted.',
					['values' => $zz_conf['int']['id']['value']]);
				$record['formhead_error'] = true;
				wrap_static('page', 'status', 410);
			} else {
				$record['formhead'] = wrap_text('A record with the ID %d does not exist.',
					['values' => $zz_conf['int']['id']['value']]);
				$record['formhead_error'] = true;
				wrap_static('page', 'status', 404);
			}
		}
	} elseif (!empty($zz_tab[0]['integrity']['msg_args'])) {
		// check for 'msg', 'updates' might contain values
		$record['formhead'] = wrap_text('Attention!');
		if (!empty($zz_tab[0]['integrity']['msg_no_list'])) {
			zz_error_log([
				'msg' => $zz_tab[0]['integrity']['msg'],
				'msg_args' => $zz_tab[0]['integrity']['msg_args']
			]);
		} else {
			if (isset($zz_tab[0]['integrity']['msg_args'])) {
				$tmp_error_msg = sprintf(
					"<ul>\n<li>%s</li>\n</ul>\n",
					implode("</li>\n<li>", $zz_tab[0]['integrity']['msg_args'])
				);
			} else {
				$tmp_error_msg = '';
			}
			zz_error_log([
				'msg' => [
					'This record could not be deleted because it has other data associated with it.',
					$zz_tab[0]['integrity']['msg'], "\n%s"],
				'msg_args' => [$tmp_error_msg]
			]);
		}
	} elseif (in_array($ops['mode'], $record_form) OR 
		(in_array($ops['mode'], ['show']) AND !$action_before_redirect)) {
	//	mode = add | edit | delete: show form
		if (isset($zz_conf['int']['id']['values'])) {
			$record['formhead'] = wrap_text(ucfirst($ops['mode']).' several records');
		} else {
			$record['formhead'] = wrap_text(ucfirst($ops['mode']) .' a record');
		}
	} elseif ($record['action'] OR $action_before_redirect) {	
	//	action = insert update review: show form with new values
		if (!$record['formhead'] AND $record['action']) {
			$record['formhead'] = wrap_text(ucfirst($record['action']).' failed');
		}
	} elseif ($ops['mode'] === 'review') {
		$record['formhead'] = wrap_text('Show a record');
	}

	// output reselect warning messages to user
	$reselect_errors = zz_log_reselect_errors();
	if ($reselect_errors) {
		$record['reselect_errors'] = wrap_template('zzform-record-reselect', $reselect_errors);
	}

	// output validation and database error messages to the user
	zz_error_validation();
	zz_error();
	$record['errors'] = zz_error_output();

	// set display of record (review, form, not at all)

	if (in_array($ops['mode'], ['delete', 'show'])) {
		$display_form = 'review';
	} elseif (in_array($ops['mode'], $record_form)) {
		$display_form = 'form';
	} elseif ($record['action'] === 'delete') {
		$display_form = false;
	} elseif ($record['action'] AND $record['formhead']) {
		$display_form = 'review';
	} elseif ($record['action']) {
		$display_form = false;
	} elseif ($ops['mode'] === 'review') {
		$display_form = 'review';
	} else
		$display_form = false;
	if (in_array($ops['mode'], ['edit', 'delete', 'review', 'revise', 'show']) 
		AND !$zz_tab[0][0]['record']) {
		$display_form = false;
	}
	$record['formhead'] = trim($record['formhead']); // can be ' ' to hide it

	if ($display_form) {
		// output form if necessary
		$record += zz_display_records($zz_tab, $ops['mode'], $display_form, $record, $zz_conditions);
	}

	if (!empty($record['footer']['insert']) AND zz_valid_request('insert')) {
		$record['footer'] = $record['footer']['insert'];
	} elseif (!empty($record['footer']['update']) AND zz_valid_request(['update', 'noupdate'])) {
		$record['footer'] = $record['footer']['update'];
	} elseif (!empty($record['footer']['delete']) AND zz_valid_request('delete')) {
		$record['footer'] = $record['footer']['delete'];
	} else {
		$record['footer'] =	'';
	}

	if (wrap_setting('zzform_xhr_vxjs')) {
		if (!empty($zz_conf['int']['selects'])) {
			$record['js_xhr_selects'] = wrap_template('xhr-selects', $zz_conf['int']['selects']);
		}
		if (!empty($zz_conf['int']['dependencies'])) {
			if (!empty($zz_conf['int']['selects'])) {
				$zz_conf['int']['dependencies']['xhr_selects'] = true;
			}
			 $record['js_xhr_dependencies'] = wrap_template('xhr-dependencies', $zz_conf['int']['dependencies']);
		}
	}
	if (!empty($zz_conf['int']['js_field_dependencies']) AND $record['form'])
		$record['js_field_dependencies'] = wrap_template('zzform-js-field-dependencies', $zz_conf['int']['js_field_dependencies']);

	if (!in_array($ops['mode'], ['add', 'edit'])) {
		$record['upload_form'] = false;
	}
	zz_record_wmd_editor();
	return wrap_template('zzform-record', $record);
}

/**
 * Settings for WMD Editor
 *
 * @global array $zz_conf
 */
function zz_record_wmd_editor() {
	global $zz_conf;
	
	if (empty($zz_conf['wmd_editor'])) return '';
	if ($zz_conf['wmd_editor'] === true) return '';
	wrap_setting('zzform_wmd_editor_instances', $zz_conf['wmd_editor'] - 1);
	
	if (in_array(wrap_setting('lang'), wrap_setting('zzform_wmd_editor_languages')))
		wrap_setting('zzform_wmd_editor_lang', wrap_setting('lang'));
}

/**
 * Display form to add, edit, delete, review a record
 * 
 * @param array $zz_tab		
 * @param string $mode
 * @param string $display	'review': show form with all values for
 *							review; 'form': show form for editing; 
 * @param array $zz_record
 * @param array $zz_conditions
 * @global array $zz_conf
 * @return array $output			HTML-Output with all form fields
 */
function zz_display_records($zz_tab, $mode, $display, $zz_record, $zz_conditions) {
	global $zz_conf;
	
	if (!$display) return [];
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	$output = [];
	$output['hidden'] = [];

	// there is a form to display
	// write 'record' key from $zz to $my_rec
	$zz_conf_record = zz_record_conf(array_merge($zz_tab[0][0], ['record' => $zz_record]));
	// check conditions
	if (!empty($zz_conditions['bool'])) {
		zz_conditions_merge_conf($zz_conf_record, $zz_conditions['bool'], $zz_conf['int']['id']['value']);
	}

	if (in_array($mode, ['add', 'edit', 'revise']) AND !empty($zz_record['upload_form'])) {
		$output['hidden'][] = [
			'name' => 'MAX_FILE_SIZE',
			'value' => zz_upload_max_filesize()
		];
	}
	$multiple = !empty($zz_conf['int']['id']['values']) ? true : false;
	$output['tbody'] = zz_show_field_rows($zz_tab, $mode, $display, $zz_record);
	$output += zz_record_tfoot($mode, $zz_record, $zz_conf_record, $zz_tab, $multiple);
	if (zz_error_exit()) return zz_return([]);
	if ($multiple) {
		foreach ($zz_conf['int']['id']['values'] as $id_value) {
			$output['hidden'][] = [
				'name' => $zz_conf['int']['id']['field_name'].'[]',
				'value' => $id_value
			];
		}
	} elseif ($mode === 'delete') {
		$output['hidden'][] = [
			'name' => $zz_conf['int']['id']['field_name'],
			'value' => $zz_conf['int']['id']['value']
		];
	}
	if ($mode && !in_array($mode, ['review', 'show'])) {
		switch ($mode) {
			case 'add': $submit = 'insert'; break;
			case 'edit': case 'revise': $submit = 'update'; break;
			case 'delete': $submit = 'delete'; break;
		}
		$output['hidden'][] = ['name' => 'zz_action', 'value' => $submit];
		if (in_array($mode, ['revise', 'delete']) AND !empty($zz_tab[0]['revision_id'])) {
			$output['hidden'][] = [
				'name' => 'zz_revision_id',
				'value' => $zz_tab[0]['revision_id']
			];
		}
		if (!empty($zz_record['zz_fields'])) {
			foreach ($zz_record['zz_fields'] as $field_name => $field) {
				$output['hidden'][] = [
					'name' => 'zz_fields['.$field_name.']',
					'value' => $field['value']
				];
			}
		}
		if (wrap_static('page', 'referer') AND wrap_static('page', 'zz_referer'))
			$output['hidden'][] = [
				'name' => 'zz_referer', 'value' => wrap_static('page', 'referer')
			];
		if (isset($_GET['file']) && $_GET['file']) 
			$output['hidden'][] = [
				'name' => 'file', 'value' => wrap_html_escape($_GET['file'])
			];
	}
	if ($display === 'form') {
		foreach ($zz_tab as $tab => $my_tab) {
			if (empty($my_tab['subtable_ids'])) continue;
			$output['hidden'][] = [
				'name' => sprintf('zz_subtable_ids[%s]', $my_tab['table_name']),
				'value' => implode(',', $my_tab['subtable_ids'])
			];
		}
	}
	return zz_return($output);
}

/**
 * as soon as we got the updated record, create dynamic_referer
 *
 * @param string $mode
 * @param array $record
 * @return bool
 */
function zz_record_dynamic_referer($mode, $record) {
	if (!$record) return false;
	if (!array_key_exists('nolist', $_GET)) return false;
	if (!wrap_static('page', 'dynamic_referer')) return false;
	wrap_static('page', 'referer', zz_makelink(wrap_static('page', 'dynamic_referer'), $record));
	wrap_static('page', 'referer_esc', str_replace('&', '&amp;', wrap_static('page', 'referer')));
	if ($mode === 'delete') wrap_static('page', 'zz_referer', false);
	return true;
}

/**
 * show table foot for record
 *
 * @param string $mode
 * @param array $zz_record
 * @param array $zz_conf_record
 * @param array $zz_tab
 * @param bool $multiple
 * @global array $zz_conf
 * @return array
 */
function zz_record_tfoot($mode, $zz_record, $zz_conf_record, $zz_tab, $multiple) {
	global $zz_conf;
	$output = [];
	
	if (wrap_static('page', 'referer') AND array_key_exists('nolist', $_GET)) {
		$cancelurl = wrap_static('page', 'referer');
	} elseif (!empty($zz_conf['int']['cancel_url'])) {
		$cancelurl = $zz_conf['int']['cancel_url'];
	} else {
		$unwanted_keys = [
			'mode', 'id', 'add', 'delete', 'insert', 'update', 'noupdate',
			'zzhash', 'edit', 'show', 'revise', 'merge'
		];
		$cancelurl = zzform_url_remove($unwanted_keys, zzform_url('self+qs+qs_zzform'));
		// do not show cancel URL if it is equal to current URL
		if ($cancelurl === zzform_url('self+qs+qs_zzform') AND empty($_POST['zz_html_fragment'])) {
			$cancelurl = false;
		}
	}
	if ($mode && !in_array($mode, ['review', 'show'])) {
		$fieldattr = [];
		switch ($mode) {
		case 'revise':
		case 'edit':
			if (!$multiple) {
				$elementvalue = wrap_text('Update record');
			} else {
				$elementvalue = wrap_text('Update records');
			}
			$fieldattr['accesskey'] = 's';
			break;
		case 'delete':
			if (!$multiple) {
				$elementvalue = wrap_text('Delete record');
			} else {
				$elementvalue = wrap_text('Delete records');
			}
			$fieldattr['accesskey'] = 'd';
			break;
		default:
			if (!$multiple) {
				$elementvalue = wrap_text('Add record');
			} else {
				$elementvalue = wrap_text('Add records');
			}
			$fieldattr['accesskey'] = 's';
			break;
		}
		$output['submit'] = zz_form_element('', $elementvalue, 'submit', false, $fieldattr);
		if (($cancelurl !== wrap_setting('request_uri') OR ($zz_record['action']) OR !empty($_POST))
			AND $zz_conf_record['cancel_link']) 
			// only show cancel link if it is possible to hide form 
			// @todo expanded to action, not sure if this works on add only forms, 
			// this is for re-edit a record in case of missing field values etc.
			$output['cancel_url'] = $cancelurl;
		$output['tfoot'] = true;
	} elseif ($zz_conf_record['access'] === 'add_only') {
		return [];
	} else {
		if ($zz_conf_record['edit']) {
			$output['tfoot_class'] = 'reedit';
			if (!$zz_conf_record['no_ok'] AND $cancelurl)
				$output['cancel_ok'] = $cancelurl;
			// record link?
			foreach ($zz_tab[0][0]['fields'] as $field) {
				if (empty($field['link_record']) OR empty($field['link'])) continue;
				$output['link_record'] = zz_makelink($field['link'], $zz_tab[0][0]['record']);
			}
			$output['modes'] = zz_output_modes($zz_conf['int']['id']['value'], $zz_conf_record);
			$output['tfoot'] = true;
		}
		if ($zz_conf_record['details']) {
			$output['tfoot_class'] = 'editbutton';
			$output['actions'] = zz_show_more_actions($zz_conf_record, $zz_conf['int']['id']['value'], $zz_tab[0][0]['record']);
			$output['tfoot'] = true;
		}
		if (!$zz_conf_record['details'] AND !$zz_conf_record['edit']
			AND $zz_conf_record['cancel_link']) {
			$output['tfoot_class'] = 'editbutton';
			$output['cancel_url'] = $cancelurl;
			$output['tfoot'] = true;
		}			
	}
	
	return $output;
}

/**
 * HTML output of all field rows
 *
 * @param array $zz_ab
 * @param string $mode
 * @param string $display
 * @param array $zz_record
 * @param array $data (optional)
 * @return mixed (array, bool, or string HTML output)
 */
function zz_show_field_rows($zz_tab, $mode, $display, $zz_record, $data = []) {
	global $zz_conf;	// Config variables
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	// @todo merge Tab, $rec, $ec_data
	if (!array_key_exists('tab', $data))
		$data['tab'] = 0;
	if (!array_key_exists('rec', $data))
		$data['rec'] = 0;
	$my_rec = $zz_tab[$data['tab']][$data['rec']];
	if (empty($my_rec['fields'])) zz_return(false);
	if (!array_key_exists('form_display', $data))
		$data['form_display'] = 'vertical';

	$append_next = '';
	$integrate_in_next = false;
	$old_append_next_type = '';
	$old_add_details_where = '';
	if ($data['tab']) {
		if (!empty($zz_conf['int']['append_next_type']))
			$old_append_next_type = $zz_conf['int']['append_next_type'];
		if (!empty($zz_conf['int']['add_details_where']))
			$old_add_details_where = $zz_conf['int']['add_details_where'];
	}
	$zz_conf['int']['append_next_type'] = '';
	$zz_conf['int']['add_details_where'] = '';
	$append_explanation = [];
	$matrix = [];

	zz_record_focus($zz_tab, $data['tab'], $data['rec']);
	
	$firstrow = true;
	$my_where_fields = $zz_record['where'][$zz_tab[$data['tab']]['table_name']] ?? [];
	// this is for 0 0 main record:
	// @todo check if this is correct, if there are other 'access' modes
	if (in_array($my_rec['access'], ['show', 'none']))
		$row_display = 'show';
	elseif ($my_rec['access'] === 'all' AND $display === 'all')
		$row_display = 'form';
	else
		$row_display = $display;

	$dependent_fields_ids = zz_dependent_field_ids($my_rec['fields'], $data['tab'], $data['rec']);
	$multiple = !empty($zz_conf['int']['id']['values']) ? true : false;
	$my_fields = [];
	$hidden_field_nos = [];
	foreach ($my_rec['fields'] as $fieldkey => $field) {
		if (!$field) continue;
		if (!empty($field['field_name']) AND array_key_exists($field['field_name'], $my_where_fields)) {
			switch ($my_where_fields[$field['field_name']]) {
			case '!NULL':
				$field['required'] = $my_rec['fields'][$fieldkey]['required'] = true;
				break;
			}
			// WHERE overrides default (which would be used for display only, but still confusing)
			unset($field['default']);
		}

		if (!empty($field['hide_in_form'])) continue;
		if (isset($field['multiple_edit']) AND !$field['multiple_edit']
			AND $multiple) continue;
		if ($field['type'] === 'timestamp' AND empty($my_rec['id']['value'])) {
			// don't show timestamp in add mode
			continue;
		}
		if (in_array($field['type'],
			['foreign_key', 'translation_key', 'detail_key', 'foreign_id'])
		) {
			// this must not be displayed, for internal link only
			continue; 
		}
		if ($field['type'] === 'option'
			AND !in_array($mode, ['edit', 'add', 'revise'])) {
			// options will only be shown in edit mode
			continue;
		}

		// dependent field?
		if (array_key_exists($fieldkey, $dependent_fields_ids)) {
			$hidden = false;
			foreach ($dependent_fields_ids[$fieldkey] as $dependency) {
				if (!empty($zz_record['where'][$zz_tab[$data['tab']]['table_name']][$dependency['source_field_name']])) {
					// WHERE
					$source_field_value = $zz_record['where'][$zz_tab[$data['tab']]['table_name']][$dependency['source_field_name']];
					if (empty($dependency['values']) OR !in_array($source_field_value, $dependency['values']))
						$hidden = true;
				} elseif ($my_rec['action'] === 'review') {
					$source_field_value = zz_dependent_value($dependency, $my_rec, $zz_tab);
					if (empty($dependency['values']) OR !in_array($source_field_value, $dependency['values']))
						$hidden = true;
				} elseif (empty($my_rec['id']['value']) AND (empty($my_rec['id']['source_value']))) { // add mode
					// default?
					$default_selected = false;
					// check $my_fields, not $my_rec['fields']
					// = it is always top down (upper fields can change visibility
					// of fields below, not other way round), $my_fields does not
					// include fields below yet but if a field is hidden by another
					// dependency
					foreach ($my_fields as $my_no => $my_field) {
						if (empty($my_field['field_name'])) continue;
						if ($my_field['field_name'] !== $dependency['source_field_name']) continue;
						if (in_array('hidden', $my_field['class'])) continue; // hidden by another dependency?
						if (empty($my_field['default'])) continue;
						if (empty($dependency['values']) OR !in_array($my_field['default'], $dependency['values'])) continue;
						$default_selected = true;
					}
					if (!$default_selected) $hidden = true;
				} elseif (empty($dependency['values'])) {
					$hidden = true;
				} else {
					$source_field_value = zz_dependent_value($dependency, $my_rec, $zz_tab);
					if (!in_array($source_field_value, $dependency['values']))
						$hidden = true;
				}
			}
			if ($hidden) {
				$hidden_field_nos[] = $fieldkey;
				$field['class'][] = 'hidden';
				$field['required'] = false;
			}
		} elseif (!empty($field['translate_field_index']) AND in_array($field['translate_field_index'], $hidden_field_nos)) {
			$field['class'][] = 'hidden';
			$my_fields[$field['translate_field_index']]['has_translation'] = true;
		}

		// $data['tab'] means subtable, since main table has $data['tab'] = 0
		if ($data['tab'] AND !in_array($field['type'], ['subtable', 'foreign_table'])) {
			$field['f_field_name'] =
			$field['select_field_name'] = zz_long_fieldname($zz_tab[$data['tab']]['table_name'], $data['rec'], $field['field_name']);
		} elseif (isset($field['field_name'])) {
			$field['f_field_name'] =
			$field['select_field_name'] = $field['field_name'];
		} elseif (in_array($field['type'], ['subtable', 'foreign_table'])) {
			$field['f_field_name'] = $field['table_name'];
		}

		if (!empty($field['js'])) {
			$field['explanation'] .= zz_record_js($field);
		}

		$my_fields[$fieldkey] = $field;
	}

	foreach ($my_fields as $fieldkey => $field) {
		// initialize variables
		if (!$append_next) {
			$out = zz_record_init_out($field);
			if (!empty($data['remove_button']))
				$out['data']['remove_button'] = $data['remove_button'];
		}
		
		if (in_array($field['type'], ['subtable', 'foreign_table'])) {
			$field_display = (!empty($field['access']) AND $field['access'] !== 'all') ? $field['access'] : $display;
			if (empty($field['form_display'])) $field['form_display'] = 'vertical';
			if (!empty($field['hierarchy'])) $field['form_display'] = 'horizontal';
		} else {
			$field_display = $row_display;
		}
		if ($mode === 'revise') {
			if (!empty($my_rec['revision']) AND !empty($field['field_name'])
				AND in_array($field['field_name'], array_keys($my_rec['revision']))) {
				$field['explanation'] = wrap_text(
					'Old value: %s',
					['values' => wrap_html_escape($my_rec['record'][$field['field_name']] ?? wrap_text('– empty –'))]
				);
				$my_rec['record'][$field['field_name']] = $my_rec['revision'][$field['field_name']];
				$field['class'][] = 'reselect';
			} elseif ($my_rec['action'] === 'delete') {
				$my_rec['record'] = [];
			} elseif (!in_array($field['type'], ['subtable', 'foreign_table'])) {
				$field_display = 'show';
			}
		}

		// show explanation?		
		if (!empty($field['always_show_explanation'])) $show = true;
		elseif ($field_display !== 'form') $show = false; // hide explanation if mode = view
		elseif (in_array($data['form_display'], ['horizontal', 'lines']) AND empty($data['is_last_rec'])) $show = false;
		else $show = true;
		if (!$show) {
			$field['explanation'] = '';
			$field['explanation_top'] = '';
		}
		if (!empty($field['explanation_top']))
			$out['data']['explanation_top'] = $field['explanation_top'];

		// dependencies?
		if ($field_display === 'form' AND !empty($field['dependencies'])) {
			$field = zz_xhr_dependencies($field, $my_fields, $data['rec']);
			zz_xhr_add('dependencies', $field);
		}

		// add class values from record
		if (isset($my_rec['class'])) $field['class'][] = $my_rec['class'];

		// add classes
		if ($field['type'] === 'id') {
			if (empty($field['show_id']))
				$field['class'][] = 'idrow';
		} elseif ($firstrow) {
			$field['class'][] = 'firstrow';
			$firstrow = false;
		}
		if ($data['tab'] AND in_array($field['type'], ['id', 'timestamp'])) {
			$field['class'][] = 'hidden';
		}

		if (!$append_next) {
			$out['tr']['attr'][] = implode(' ', $field['class']);
			if ($data['form_display'] === 'horizontal' AND in_array(zz_get_fieldtype($field), ['number', 'sequence'])) {
				$out['td']['attr'][] = 'number';
			}
			if (!(isset($field['show_title']) && !$field['show_title'])) {
				if (!empty($field['title_append'])) {
					// just for form, change title for all appended fields
					$out['th']['content'] .= $field['title_append'];
				} else { 
					$out['th']['content'] .= $field['title'];
				}
				if (!empty($field['title_desc']) && $field_display === 'form') {
					$out['data']['title_desc'] = $field['title_desc'];
				}
				if (!empty($field['format']) AND empty($field['hide_format_in_title_desc']) AND $field_display === 'form') { 
					// formatted fields: show that they are being formatted!
					$out['data']['format'] = $field['format'];
					$out['data']['format_link'] = wrap_path_helptext($field['format']);
					if (!$out['data']['format_link']) {
						$out['data']['format_link'] = wrap_setting('zzform_format['.$field['format'].'][link]');
						if ($out['data']['format_link'])
							wrap_error(sprintf(
								'Please use a file in help folder instead of `zzformat[%s][link]`', $field['format']
							), E_USER_DEPRECATED);
					}
				}
			} elseif (!$data['tab']) {
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
			$my_tab = $zz_tab[$field['subtable']];
			// don't print out anything if record is empty
			if (array_key_exists(0, $my_tab)) {
				$out['td']['content'] .= zz_field_subtable_set($field, $field_display, $my_tab);
				// check for errors
				if (zz_record_subtable_error($my_tab))
					$out['tr']['attr'][] = 'error'; 
			}

		} elseif ($field['type'] === 'subtable' AND $field['form_display'] === 'inline') {
			$subtable_rows = zz_show_field_rows(
				$zz_tab, $mode, $field_display, $zz_record,
				['tab' => $field['subtable'], 'form_display' => 'inline']
			);
			if ($subtable_rows)
				$matrix = array_merge($matrix, $subtable_rows);
			$out = [];

			// @todo check if this would work – inline fields shown as dependency
			// e. g. persons table depending on contact_category_id
			// zz_record_subtable_dependencies($field, $my_fields, $my_rec['id']['value'] ?? 0);

		} elseif (in_array($field['type'], ['subtable', 'foreign_table'])) {
			$integrate_in_next = !empty($field['integrate_in_next']) ? true : false;
			//	Subtable
			$sub_tab = $field['subtable'];
			$out['th']['attr'][] = 'sub-add';
			$out['td']['attr'][] = 'subtable';
			$out['td']['id'] = $field['f_field_name'];
			// go through all detail records
			$c_subtables = 0;

			$subtables = array_keys($zz_tab[$sub_tab]);
			foreach (array_keys($subtables) as $this_rec)
				if (!is_numeric($subtables[$this_rec])) unset($subtables[$this_rec]);
			$details = [];
			$d_index = 0;
			foreach ($subtables as $sub_rec) {
				// show all subtables which are not deleted but 1 record as a minimum
				if ($zz_tab[$sub_tab][$sub_rec]['action'] === 'delete'
					AND (empty($zz_tab[$sub_tab]['records'])
						AND ($sub_rec + 1) !== $zz_tab[$sub_tab]['min_records'])) continue;
				// don't show records which are being ignored
				if ($zz_tab[$sub_tab][$sub_rec]['action'] === 'ignore'
					AND $field_display !== 'form') continue;
				if ($zz_tab[$sub_tab][$sub_rec]['action'] === 'delete'
					AND $field_display !== 'form' AND $zz_record['action']) continue;

				$c_subtables++;
				
				$rec_data = [
					'remove_button' => '',
					'tab' => $sub_tab,
					'rec' => $sub_rec,
					'is_last_rec' => ($sub_rec === count($subtables) - 1) ? true : false,
					'form_display' => $field['form_display']
				];

				$dont_delete_records = $field['dont_delete_records'] ?? false;
				if (!empty($field['hierarchy'])) {
					// hierarchy never allows adding/removing of records
					$dont_delete_records = true;
				}
				if (!empty($field['values'][$sub_rec])) {
					$dont_delete_records = true; // dont delete records with values set
				}
				// just for optical reasons, in case one row allows removing of record
				// @todo check if this last row is needed dynamically
				if ($display === 'form' AND !$dont_delete_records)
					$rec_data['dummy_last_column'] = true;	
				
				if ($field_display === 'form') {
					if ($zz_tab[$sub_tab]['min_records'] <= $zz_tab[$sub_tab]['records']
						AND $zz_tab[$sub_tab]['records'] > $zz_tab[$sub_tab]['min_records_required']
						&& !$dont_delete_records AND $mode !== 'revise')
						// do not show remove button for single inline records,
						// bit too much
						if ($zz_tab[$sub_tab]['records'] !== 1 
							OR ($field['form_display'] !== 'lines' AND $mode !== 'add')) {
							$rec_data['remove_button'] = zz_record_subtable_submit('remove', $field, $sub_tab, $sub_rec);
						}
				}

				// Mode
				$subtable_mode = $mode;
				if ($subtable_mode === 'edit' AND empty($zz_tab[$sub_tab][$sub_rec]['id']['value'])) {
					// no saved record exists, so it's add a new record
					$subtable_mode = 'add';
				}

				if ($rec_data['remove_button']) {
					if (in_array($field['form_display'], ['lines', 'horizontal'])) {
						$rec_data['dummy_last_column'] = false;	
					}
				}
				$details[$d_index] = zz_show_field_rows(
					$zz_tab, $subtable_mode, $field_display, $zz_record, $rec_data
				);
				$d_index++;
			}

			$out['td']['content'] .= implode('', $details);
			if (!$c_subtables AND !empty($field['msg_no_subtables'])) {
				// There are no subtables, optional: show a message here
				$out['td']['content'] .= $field['msg_no_subtables'];
			}
			if ($field_display === 'form' 
				AND $zz_tab[$sub_tab]['max_records'] > $zz_tab[$sub_tab]['records']) {
				if ($field['form_display'] === 'lines' AND $details) {
					// add spacer only if there's something above and below spacer
					$out['td']['content'] .= '<div class="subrecord_spacer"></div>';
				}
				if ($mode !== 'revise') {
					$out['td']['content'] .= zz_record_subtable_submit('add', $field, $sub_tab);
				}
			}
			if ($field['form_display'] === 'lines') {
				$all_have_errors = zz_record_subtable_check_error($zz_tab[$field['subtable']]);
				if ($all_have_errors) {
					// mark full table row as having an error
					$out['th']['attr'][] = 'error';
					$out['td']['attr'][] = 'error';
				}
			}

			zz_record_subtable_dependencies($field, $my_fields, $my_rec['id']['value'] ?? 0);

		} else {
			//	"Normal" field
			$hidden_element = '';

			// write values into record, if detail record entry shall be preset
			if (!empty($zz_tab[$data['tab']]['values'][$data['rec']][$fieldkey])) {
				$field['value'] = $zz_tab[$data['tab']]['values'][$data['rec']][$fieldkey];
				if ($field['type'] === 'select') {
					$field['type_detail'] = $field['type'];
					$field['type'] = 'predefined';
				}
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
			if (!$append_next OR !$field['class']) {
				$close_span = false;
			} else {
				$close_span = true;
				$out['td']['content'] .= '<span class="'.implode(' ', $field['class']).'">'; 
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

			$field['required'] = zz_record_field_required($field, $zz_tab, $data['tab']);
			$field = zz_record_field_size($field); // size, maxlength
			$field['placeholder'] = zz_record_field_placeholder($field);

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
				$field['default'] = zz_set_auto_value($field, $zz_tab[$data['tab']]['sql'], 
					$zz_tab[$data['tab']]['table'], $data['tab'], $data['rec'], $zz_tab[0]['table']);
			}

			// values, defaults
			if (isset($my_where_fields[$field['field_name']])) {
				switch ($my_where_fields[$field['field_name']]) {
				case '!NULL':
					break;
				default:
					if ($field['type'] === 'select') $field['type_detail'] = 'select';
					elseif (!isset($field['type_detail'])) $field['type_detail'] = false;
					$field['type'] = 'predefined';
					break;
				}
			}
			if (empty($field['value'])) {
				// Check if filter is applied to this field, set filter value as default value
				$default = zz_record_filter_as_default($field['field_name'], $zz_tab[0]['filter'], $zz_tab[0]['filter_active']);
				if ($default) $field['default'] = $default;
			}

			if (zz_has_default($field) AND empty($field['value'])) {
				// look at default only if no value is set - value overrides default
				if (($mode === 'add' && !$my_rec['record'])
					OR (!empty($is_option) AND empty($my_rec['record'][$field['field_name']]))
					OR !$my_rec['record'] && !empty($field['def_val_ignore'])) { 
					// set default only if record is empty 
					// OR if it's an option field which is always empty on creation (but not on reedit)
					// OR if default value is set to be ignored in case of no 
					// further additions
					$my_rec['record'][$field['field_name']] = $field['default'];
					$default_value = true; // must be unset later on because of this value
				}
			}
			//
			// output all records
			//
			
			if ($field['type'] === 'write_once' AND ($mode === 'add' OR $zz_record['action'] === 'insert')) {
				$field['type'] = $field['type_detail'];
			}
			if (!isset($my_rec['record_saved'])) $my_rec['record_saved'] = NULL;
			if (!isset($my_rec['images'])) $my_rec['images'] = NULL;

			switch ($field['type']) {
			case 'id':
				$outputf = zz_field_id($field, $my_rec['id']['value'], $mode, $data['tab']);
				break;

			case 'predefined':
			case 'identifier':
			case 'hidden':
				list($outputf, $hidden_element) = zz_field_hidden($field, $my_rec['record'], $my_rec['record_saved'], $mode);
				if (zz_error_exit()) {
					zz_error();
					return zz_return(false);
				}
				break;

			case 'timestamp':
				$outputf = zz_field_timestamp($field, $my_rec['record'], $mode);
				break;

			case 'foreign':
				$outputf = zz_field_foreign($field, $my_rec['id']['value']);
				break;

			case 'password':
				$outputf = zz_field_password($field, $field_display, $my_rec['record'], $zz_record['action']);
				break;

			case 'password_change':
				$outputf = zz_field_password_change($field, $field_display);
				break;

			case 'url':
			case 'url+placeholder':
				$field['max_select_val_len'] = wrap_setting('zzform_max_select_val_len');
			case 'text':
			case 'parameter':
			case 'time':
			case 'enum':
			case 'mail':
			case 'mail+name':
			case 'ipv4':
			case 'sequence':
			case 'phone':
			case 'username':
				$outputf = zz_field_text($field, $field_display, $my_rec['record'], !$my_rec['validation'] ? true : $zz_tab[0]['dont_reformat']);
				break;

			case 'unix_timestamp': // zz_field_unix_timestamp
			case 'ip': // zz_field_ip
			case 'number': // zz_field_number
			case 'date': // zz_field_date
			case 'datetime': // zz_field_datetime
			case 'memo': // zz_field_memo
				$function_name = sprintf('zz_field_%s', $field['type']);
				$outputf = $function_name($field, $field_display, $my_rec['record'], $zz_tab[0]['dont_reformat']);
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
					if (!empty($field['sql_where_with_id']) AND !empty($zz_conf['int']['id']['value'])) {
						$field['sql'] = wrap_edit_sql($field['sql'], 'WHERE', 
							sprintf("%s = %d", $zz_conf['int']['id']['field_name'], $zz_conf['int']['id']['value'])
						);
					}
					if (!empty($field['sql_where_without_id']) AND !empty($zz_conf['int']['id']['value'])) {
						$field['sql'] = wrap_edit_sql($field['sql'], 'WHERE',
							sprintf("%s != %d", $zz_conf['int']['id']['field_name'], $zz_conf['int']['id']['value'])
						);
					}

					$outputf = zz_field_select_sql($field, $field_display, $my_rec['record'], 
						$zz_tab[$data['tab']]['db_name'].'.'.$zz_tab[$data['tab']]['table']);

				} elseif (isset($field['set_folder'])) {
					// #2a SELECT with set_folder
					$outputf = zz_field_select_set_folder($field, $field_display, $my_rec['record'], $data['rec']);

				} elseif (isset($field['set_sql'])) {
					// #2 SELECT with set_sql
					$field['sql'] = $field['set_sql'];
					// check for 'sql_where'
					if ($my_where_fields) {
						$field['sql'] = zz_form_select_sql_where($field, $my_where_fields);
					}
					$outputf = zz_field_select_set_sql($field, $field_display, $my_rec['record'], $data['rec']);

				} elseif (isset($field['set'])) {
					// #3 SELECT with set
					$outputf = zz_field_select_set($field, $field_display, $my_rec['record'], $data['rec']);

				} elseif (isset($field['enum'])) {
					// #4 SELECT with enum
					$outputf = zz_field_select_enum($field, $field_display, $my_rec['record']);

				} else {
					// #5 SELECT without any source = that won't work ...
					$outputf = wrap_text('No source defined').'. '.wrap_text('No selection possible.');
				}
				if (!empty($field['dependent_fields'])) {
					foreach ($field['dependent_fields'] as $field_no => $dependent_field) {
						if (empty($my_fields[$field_no])) continue;
						$zz_conf['int']['js_field_dependencies'][] = [
							'main_field_id' => zz_make_id_fieldname($field['f_field_name']),
							'dependent_field_id' => zz_make_id_fieldname($my_fields[$field_no]['f_field_name']),
							'required' => !empty($dependent_field['required']) ? true : false,
							'field_no' => $field_no,
							'has_translation' => !empty($my_fields[$field_no]['has_translation']) ? true : false
						];
					}
				}
				break;

			case 'image':
			case 'upload_image':
				$outputf = zz_field_file($field, $field_display, $my_rec['record'], 
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

			case 'captcha':
				$outputf = zz_field_captcha($field, $my_rec['record'], $mode);
				if (!$outputf) continue 2;
				$field['explanation'] = '';
				break;

			default:
				$outputf = '';
			}
			if (!empty($field['unit'])) {
				//if ($my_rec['record']) { 
				//	if ($my_rec['record'][$field['field_name']]) // display unit if record not null
				//		$outputf .= ' '.$field['unit']; 
				//} else {
					$outputf .= '&nbsp;'.$field['unit']; 
				//}
			}
			if (!empty($default_value)) // unset $my_rec['record'] so following fields are empty
				unset($my_rec['record'][$field['field_name']]);
			if ($field_display === 'form' AND !empty($field['add_details'])) {
				$check = zz_record_add_details_check($field, $mode);
				if ($check) {
					if (is_array($field['add_details'])) {
						require_once __DIR__.'/details.inc.php';
						$field['add_details'] = zz_details_link($field['add_details'], $zz_tab[0][0]['record']);
					}
					if ($field['add_details'])
						$outputf .= zz_record_add_details($field, $data['tab'], $data['rec'], $fieldkey);
				}
			}
			if (($outputf AND trim($outputf)) OR $outputf === '0') {
				if (isset($field['prefix'])) $out['td']['content'] .= $field['prefix'];
				$out['td']['content'] .= $outputf.$hidden_element;
				if (isset($field['suffix'])) $out['td']['content'] .= $field['suffix'];
				else $out['td']['content'] .= ' ';
				if ($field_display === 'form') if (isset($field['suffix_function'])) {
					$vars = '';
					if (isset($field['suffix_function_var']))
						foreach ($field['suffix_function_var'] as $var) {
							$vars .= $var; 
							// @todo does this really make sense? 
							// looks more like $vars[] = $var. maybe use implode.
						}
					$out['td']['content'] .= $field['suffix_function']($vars);
				}
			} else {
				$out['td']['content'] .= $outputf.$hidden_element;
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
				$append_explanation = [];
			}
		}
		if ($field['explanation'])
			$out['data']['explanation'] = $field['explanation'];
		if (!empty($field['separator'])) {
			if (!$out) $out = zz_record_init_out($field);
			$out['separator'] .= $field['separator'];
		}
		if (!empty($field['separator_before'])) {
			if (!$out) $out = zz_record_init_out($field);
			$out['separator_before'] .= $field['separator_before'];
		}
		if ($out AND !$append_next AND !$integrate_in_next) {
			if (!empty($integrate_out)) {
				foreach ($integrate_out as $integrate) {
					if ($out['td']['content'] AND $integrate['td']['content'])
						$out['td']['content'] = '<div class="subrecord_spacer"></div>'.$out['td']['content'];
					$out['td']['content'] = $integrate['td']['content']."\n".$out['td']['content'];
					$out += $integrate['data'] ?? [];
				}
				$integrate_out = [];
			}
			if ($field['type'] === 'id' AND $out['td']['content'] === '') continue;
			$matrix[] = $out;
		} elseif ($integrate_in_next) {
			$integrate_out[] = $out;
		}
	}
	if ($data['form_display'] === 'inline') return $matrix;
	$output = zz_record_fields($matrix, $data);
	// append_next_type is only valid for single table
	$zz_conf['int']['append_next_type'] = $old_append_next_type;
	$zz_conf['int']['add_details_where'] = $old_add_details_where;
	return zz_return($output);
}

/**
 * set 'required' attribute of a field
 *
 * @param array $field
 * @return bool
 */
function zz_record_field_required($field, $zz_tab, $tab) {
	if (empty($field['required'])) return false;
	if ($tab AND $field['required']
		AND ($zz_tab[$tab]['max_records'] !== $zz_tab[$tab]['min_records_required'] OR !$zz_tab[$tab]['min_records_required'])) {
		// support for required for subtable is too complicated so far, 
		// because the whole subtable record may be optional
		// just allow this for all required subrecords
		return false;
	}
	if ($field['required'] AND !empty($field['upload_value']))
		// in case there is no value, it will come from an upload field
		return false;
	return $field['required'];
}

/**
 * set 'size' and 'maxlength' of a field
 *
 * @param array $field
 * @return array
 */
function zz_record_field_size($field) {
	// field size, maxlength
	if (!isset($field['size'])) {
		if (in_array($field['type'], ['number', 'sequence'])) {
			$field['size'] = 16;
		} elseif (in_array($field['type'], ['datetime', 'timestamp'])) {
			$field['size'] = 18;
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
		AND (empty($field['number_type']) OR !in_array($field['number_type'], ['latitude', 'longitude']))) {
		$field['size'] = $field['maxlength'];
	}
	if (!empty($field['formatting_spaces'])) {
		$field['size'] += $field['formatting_spaces'];
		if (isset($field['maxlength'])) {
			$field['maxlength'] += $field['formatting_spaces'];
		}
	}
	return $field;
}

/**
 * set 'placeholder' of a field
 *
 * @param array $field
 * @return string
 */
function zz_record_field_placeholder($field) {
	if (empty($field['placeholder'])) return false;
	if ($field['placeholder'] === true) $placeholder = $field['title'];
	else $placeholder = wrap_text($field['placeholder'], ['source' => wrap_setting('zzform_script_path')]);
	return strip_tags($placeholder);
}

/**
 * initialize $out variable
 *
 * @param array $field
 * @return array
 */
function zz_record_init_out($field) {
	$out['tr']['attr'] = [];
	$out['th']['attr'] = [];
	$out['th']['content'] = '';
	$out['th']['show'] = true;
	$out['td']['attr'] = [];
	$out['td']['content'] = '';
	$out['separator'] = '';
	$out['separator_before'] = '';
	$out['sequence'] = $field['field_sequence'] ?? 1;
	return $out;
}

/**
 * sorts the matrix of fields, if 'field_sequence' is set in any field
 *
 * @param array $matrix
 * @return array $matrix
 */
function zz_record_sort_matrix($matrix) {
	if (count($matrix) === 1) return $matrix;
	$sort = false;
	foreach ($matrix as $field) {
		if ($field['sequence'] === 1) continue;
		$sort = true;
		break;
	}
	if (!$sort) return $matrix;
	$sort = [];
	foreach ($matrix as $field) {
		$sort[] = $field['sequence'];
	}
	array_multisort($sort, $matrix);
	return $matrix;
}

/**
 * check if New … link should be displayed
 *
 * @param array $field
 * @param string $mode
 * @return bool
 */
function zz_record_add_details_check($field, $mode) {
	if (!isset($field['add_details'])) return false;
	if (!$mode) return false;
	if (in_array($mode, ['delete', 'show', 'review'])) return false;
	if (in_array($mode, ['edit', 'revise'])) {
		if (in_array($field['type'], [
			'hidden', 'predefined', 'write_once', 'display'
		])) return false;
	} elseif ($mode === 'add' AND !empty($field['value'])) {
		// $zz['add'] with 'value'
		return false;
	}
	return true;
}

/**
 * put new ... link next to field to add missing detail records
 *
 * @param array $field
 * @param int $tab
 * @param int $rec
 * @param int $fieldkey
 * @return string
 */
function zz_record_add_details($field, $tab, $rec, $fieldkey) {
	global $zz_conf;
	
	if (!empty($_SESSION['logged_in'])) {
		if ($tab) {
			$name = sprintf('%s-%d-%d-%d-%d',
				$zz_conf['id'], $field['subtable_no'], $fieldkey, $tab, $rec
			);
		} else {
			$name = sprintf('%s-%d-%d-%d',
				$zz_conf['id'], $fieldkey, $tab, $rec
			);
		}
		$text = ' <input type="submit" name="zz_add_details[%s]" value="%s" formnovalidate class="zz_add_details_add">';
		$text .= ' <input type="submit" name="zz_edit_details[%s]" value="%s" formnovalidate class="zz_add_details_edit">';
		$text = sprintf($text, $name, wrap_text('New …'), $name, wrap_text('Edit …'));
	} else {
		$add_details_sep = strstr($field['add_details'], '?') ? '&amp;' : '?';
		$text = ' <a href="'.$field['add_details'].$add_details_sep
			.'add&amp;referer='.urlencode(wrap_setting('request_uri'))
			.$zz_conf['int']['add_details_where'].'"'
			.(!empty($field['add_details_target']) ? ' target="'.$field['add_details_target'].'"' : '')
			.' id="zz_add_details_'.$tab.'_'.$rec.'_'.$fieldkey.'">['. wrap_text('New …').']</a>';
	}
	return $text;
}

/**
 * sets HTML attribute autofocus where appropriate
 * i. e. first element of record; if subrecord is added: first element of
 * subrecord; if subrecord is deleted: first element of previous subrecord
 *
 * @param array $zz_tab (optional; required for initalizing $field_focus)
 * @param int $tab (optional; required for initalizing $field_focus)
 * @param int $rec (optional; required for initalizing $field_focus)
 * @return bool true: $field_focus was initalized; false: do not focus
 */
function zz_record_focus($zz_tab = false, $tab = 0, $rec = 0) {
	static $field_focus = NULL;
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
	if (!$field_focus) return false;
	$field_focus = false;
	return true;
}

/**
 * set a focus on a field
 *
 * @param string $name field name
 * @param string $type field type
 * @return bool
 */
function zz_record_field_focus($name, $type) {
	if (!$focus = wrap_setting('zzform_autofocus')) return false;
	if (!empty($_GET['focus']) AND $_GET['focus'] !== $name) return false;
	if (!in_array($type, $focus)) return false; 
	return zz_record_focus();
}

/**
 * HTML output of table rows for form
 *
 * @param array $matrix matrix of rows
 * @param array $data
 * @return string HTML output
 */
function zz_record_fields($matrix, $data) {
	global $zz_conf;
	static $table_head = [];
	static $table_separator = [];
	if (!$matrix) return ''; // detail record was deleted: no matrix
	if (!array_key_exists($data['tab'], $table_head)) $table_head[$data['tab']] = true;

	$data['separator_colspan_horizontal'] = count($matrix);
	$data['th_content'] = false;
	$data['error'] = false;
	$data['head'] = $table_head[$data['tab']]; // just first detail record with values: show head
	$data['detailrecord'] = $data['tab'] ? true : false; // main 0: otherwise 1 … n
	$table_head[$data['tab']] = false;

	$last_row = [
		'separator_before' => false,
		'separator' => false
	];
	$i = 0;
	$matrix = zz_record_sort_matrix($matrix);
	foreach ($matrix as $index => $row) {
		if (!is_numeric($index)) continue;
		if ($row['th']['content'] AND $row['th']['show']) $data['th_content'] = true;
		// add separator_before to matrix
		if ($row['separator_before'] AND $last_row['separator'] !== $row['separator_before']) {
			$separator = zz_record_separator($row['separator_before']);
			if (!$index OR (!empty($last_row['tr']['attr'][0]) AND strstr($last_row['tr']['attr'][0], 'idrow') AND $index === 1))
				$separator['separator_hr'] = false;
			switch ($data['form_display']) {
				case 'vertical':
					$data[$i] = $separator;
					$i++;
					break;
				case 'horizontal':
					if ($index !== 0) break;
					if (!empty($table_separator[$data['tab']])) break;
					$data['separator_before'] = true;
					break;
			}
		}
		$last_row = $row;
		foreach ($row['tr']['attr'] as $attr)
			if (strstr($attr, 'error')) $data['error'] = true;
		$data[$i]['tr_class'] = zz_record_field_class($row['tr']['attr']);
		$data[$i]['th_content'] = $row['th']['content'];
		if (!$row['th']['show'] AND $data['form_display'] === 'vertical')
			$data[$i]['th_content'] = NULL;
		$data[$i]['th_class'] = zz_record_field_class($row['th']['attr']);
		$data[$i]['th+tr_class'] = zz_record_field_class(array_merge($row['th']['attr'], $row['tr']['attr']));
		// get non-array values from $row, e. g. title_desc, description etc.
		$data[$i] += $row['data'] ?? [];
		$data[$i]['td_content'] = $row['td']['content'];
		$data[$i]['td_id'] = !empty($row['td']['id']) ? zz_make_id_fieldname($row['td']['id']) : '';
		$data[$i]['td_class'] = zz_record_field_class($row['td']['attr']);
		$data[$i]['td+tr_class'] = zz_record_field_class(array_merge($row['td']['attr'], $row['tr']['attr']));
		// add separator to matrix
		if ($row['separator']) {
			// @todo hide hr for first record, too?
			$separator = zz_record_separator($row['separator']);
			if (!$index) $separator['separator_hr'] = false; // no hr before first line
			switch ($data['form_display']) {
				case 'vertical':
					$i++;
					$data[$i] = $separator;
					break;
				case 'horizontal':
					if ($index !== count($matrix) -1) break;
					$data['separator'] = true;
					$table_separator[$data['tab']] = true;
					break;
			}
		}
		$i++;
	}
	if (!$data['tab'] AND !$data['th_content']) $zz_conf['int']['hide_tfoot_th'] = true;
	return wrap_template('zzform-record-fields', $data);
}

/**
 * returns filter value as default, if set
 *
 * @param string $field_name
 * @param array $filters = $zz['filter']
 * @param array $filter_active = $zz['filter_active']
 * @return string
 */
function zz_record_filter_as_default($field_name, $filters, $filter_active) {
	if (!$filter_active) return false;
	if (!$filters) return false;

	// check if there's a filter with a field_name 
	// this field will get the filter value as default value
	$filter_field_name = [];
	$unwanted_filter_values = ['NULL', '!NULL'];
	foreach (array_keys($filter_active) AS $filter_identifier) {
		foreach ($filters as $filter) {
			if ($filter_identifier !== $filter['identifier']) continue;
			if (empty($filter['field_name'])) continue;
			if ($filter['field_name'] !== $field_name) continue;
			if (in_array($filter_active[$filter_identifier], $unwanted_filter_values)) continue;
			return $filter_active[$filter_identifier];
		}
	}
	return false;
}

/**
 * return list of classes, separated by space, from an array of class names
 *
 * @param array class names
 * @return string
 */
function zz_record_field_class($attr) {
	if (!$attr) return false;
	$attr = trim(implode(' ', $attr));
	if (!$attr) return false;
	return $attr;
}

/**
 * decides what separator to show
 *
 * @param mixed $separator
 *		1 or true: simple HR line
 *		'column_begin', 'column', 'column_end': allows to put form into two columns
 *		'text '.*** like true, but with text printed behind HR
 * @return array
 */
function zz_record_separator($separator) {
	if (substr($separator, 0, 5) === 'text ')
		return ['separator_text' => substr($separator, 4), 'separator_hr' => true];
	elseif ($separator == 1)
		return ['separator_hr' => true];
	return ['separator_'.$separator => true];
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
 * @param string $table name of main table
 * @return int value for default field
 */
function zz_set_auto_value($field, $sql, $table, $tab, $rec, $main_table) {

	// currently, only 'increment' is supported for auto values
	if ($field['auto_value'] !== 'increment') return $field['default'];
	
	$field['default'] = 1;
	
	// get main (sub-)table query, change field order
	$sql = wrap_edit_sql($sql, 'ORDER BY', $field['field_name'].' DESC');
	// we just need the field increment is based on
	$sql = wrap_edit_sql($sql, 'SELECT', $table.'.'.$field['field_name'], 'replace');
	// we just need the field with the highest value
	$sql = wrap_edit_sql($sql, 'LIMIT', '1');

	if ($tab) { 
		// subtable
		if (!empty($zz_conf['int']['id']['field_name']) AND !empty($zz_conf['int']['id']['value'])) {
			$sql = wrap_edit_sql($sql, 'WHERE', sprintf(
				'%s.%s = %d', $main_table, $zz_conf['int']['id']['field_name'], $zz_conf['int']['id']['value']
			));
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
 * @param array $input
 *		string 'name' name=""
 *		string 'value' value=""
 *		string 'type' type="" (default: text)
 *		string 'id' id=""
 *		bool 'create_id' will create id for id attribute
 *		array 'attributes' indexed by name => value
 * @return string
 */
function zz_record_element($input) {
	if (empty($input['type'])) $input['type'] = 'text';

	// id?
	if (!empty($input['create_id']))
		$input['id'] = zz_make_id_fieldname($input['name']);
	unset($input['create_id']);

	// autocomplete?
	if (in_array($input['type'], ['password']))
		$input['autocomplete'] = 'off';

	// autofocus?
	if (!isset($input['autofocus']))
		$input['autofocus'] = zz_record_field_focus($input['name'], $input['type']);

	// multiple?
	if (!isset($input['multiple']) AND in_array($input['type'], ['email']))
		$input['multiple'] = true;
	
	switch ($input['type']) {
	case 'textarea':
		$element['tag'] = 'textarea';
		$element['html'] = '<textarea%s>%s</textarea>';
		$element['text'] = $input['value'];
		unset($input['value']);
		break;
	case 'select':
		$element['tag'] = 'select';
		$element['html'] = '<select%s>';
		break;
	case 'option':
		$element['tag'] = 'option';
		$element['html'] = '<option%s>%s</option>';
		$element['text'] = $input['name'];
		unset($input['name']);
		break;
	case 'file':
		$element['tag'] = 'input';
		$element['html'] = '<input%s>';
		unset($input['value']);
		break;
	case 'checkbox':
		$element['tag'] = 'input';
		$element['html'] = '<input%s>';
		// value just sometimes? @todo check if this is still necessary
		if (isset($input['value']) AND !$input['value']) unset($input['value']);
		break;
	default:
		$element['tag'] = 'input';
		$element['html'] = '<input%s>';
		// escaping for some 'text' elements, not all
		// e. g. geo coordinates and reselect-elements don't need &-values
		// escaped and look better without escaping
		if ($input['type'] === 'text' AND !empty($input['value']))
			$input['value'] = str_replace('&', '&amp;', $input['value']);
		if ($input['type'] === 'text_noescape')
			$input['type'] = 'text';
		break;
	}
	if (!empty($element['text'])) {
		$element['text'] = str_replace('&', '&amp;', $element['text']);
		$element['text'] = str_replace('<', '&lt;', $element['text']);
	}
	if (!empty($input['value']))
		$input['value'] = str_replace('"', '&quot;', $input['value']);
	$element['attributes'] = $input;
	return $element;
}
 
/**
 * creates a HTML form element
 *
 * @param string $name name=""
 * @param string $value value=""
 * @param string $type (default: text)
 * @param mixed $id (optional; bool: create from $name; string: use as id)
 * @param array $fieldattr (further attributes, indexed by name => value)
 * @return string HTML code
 */
function zz_form_element($name, $value, $type = 'text', $id = false, $fieldattr = []) {
	// escaping for some 'text' elements, not all
	// e. g. geo coordinates and reselect-elements don't need &-values
	// escaped and look better without escaping
	if ($type === 'text' AND $value) $value = str_replace('&', '&amp;', $value);
	if ($type === 'text_noescape') $type = 'text';
	
	// name
	if ($name AND $type !== 'option') $fieldattr['name'] = $name;

	// prepare ID
	if ($id AND empty($fieldattr['id'])) {
		if ($id === true) $id = zz_make_id_fieldname($name);
		$fieldattr['id'] = $id;
	}

	// autocomplete?
	$autocomplete = ['password'];
	if (in_array($type, $autocomplete)) {
		$fieldattr['autocomplete'] = 'off';
	}

	// autofocus?
	if (!isset($fieldattr['autofocus'])) {
		$fieldattr['autofocus'] = zz_record_field_focus($name, $type);
	}

	// multiple?
	$multiple = ['email'];
	if (!isset($fieldattr['multiple']) AND in_array($type, $multiple)) {
		$fieldattr['multiple'] = true;
	}
	
	// value just sometimes?
	$values = ['checkbox'];
	if ($value AND in_array($type, $values)) {
		$fieldattr['value'] = $value;
	}

	wrap_include('format', 'zzform');
	$attr = zzform_attributes($fieldattr);

	// return HTML depending on type
	switch ($type) {
	case 'textarea':
		if ($value) {
			$value = str_replace('&', '&amp;', $value);
			$value = str_replace('<', '&lt;', $value);
		}
		return sprintf('<textarea%s>%s</textarea>', $attr, $value);
	case 'select':
		return sprintf('<select%s>', $attr);
	case 'option':
		$name = str_replace('<', '&lt;', $name);
		if ($value) {
			$value = str_replace('"', '&quot;', $value);
		}
		return sprintf('<option value="%s"%s>%s</option>', $value, $attr, $name);
	case 'checkbox':
	case 'file':
		// no value attribute (file) or just sometimes (checkbox)
		return sprintf('<input type="%s"%s>', $type, $attr);
	default:
		if ($value) {
			$value = str_replace('"', '&quot;', $value);
		}
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
		$text = ' <a onclick="zz_set_checkboxes(true, \'%s[]\'); return false;" href="#">'.wrap_text('Select all').'</a> |
			<a onclick="zz_set_checkboxes(false, \'%s[]\'); return false;" href="#">'.wrap_text('Deselect all').'</a>';
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
 * show text 'add automatically' or not
 *
 * @param array $field
 * @return string
 */
function zz_field_will_add_auto($field) {
	if (!empty($field['hide_auto_add_msg'])) return '';
	return '('.wrap_text('will be added automatically').')&nbsp;';
}

/**
 * record output of field type 'id'
 *
 * just show ID of main record, detail records only hidden
 * @param array $field
 * @param int $id_value
 * @param string $mode
 * @param int $tab
 * @return string
 */
function zz_field_id($field, $id_value, $mode, $tab) {
	if (!$id_value) return zz_field_will_add_auto($field);
	$out = '';
	if ($mode !== 'show') {
		$out = zz_form_element($field['f_field_name'], $id_value, 'hidden', true);
	}
	if (!$tab) $out .= $id_value;
	return $out;
}

/**
 * record output of field type 'hidden'
 *
 * @param array $field
 * @param array $record
 * @param array $record_saved
 * @param string $mode
 * @return array
 *		string some value if any
 *		string hidden element
 */
function zz_field_hidden($field, $record, $record_saved, $mode) {
	$value = '';
	$display_value = '';
	$mark_italics = false;
	if (!empty($field['value'])) {
		if ($record AND $field['value'] !== $record[$field['field_name']]) {
			$display_value = $record[$field['field_name']];
			$mark_italics = true;
		}
		$value = $field['value'];
	} elseif ($record) {
		$value = $record[$field['field_name']];
	}

	$text = '';
	$field_type = zz_get_fieldtype($field);
	if (!$display_value) $display_value = $value;

	if ($value AND in_array($field_type, ['number', 'ipv4', 'date', 'datetime', 'time'])) {
		$text .= zz_htmltag_escape(zz_field_format($display_value, $field));
	} elseif ($value AND $field_type === 'select') {
		$detail_key = $display_value ? $display_value : $field['default'];
		if (isset($field['sql'])) {
			$sql = wrap_edit_sql($field['sql'], 'WHERE', sprintf('%s = %d', $field['key_field_name'], $detail_key));
			$select_fields = zz_db_fetch($sql);
			$select_fields = zz_translate($field, $select_fields);
			if ($select_fields) {
				// remove hierarchy field for display
				if (!empty($field['show_hierarchy'])) {
					unset($select_fields[$field['show_hierarchy']]);
				}
				// remove ID (= first field) for display
				if (count($select_fields) > 1)
					array_shift($select_fields);
				$select_fields = zz_field_select_ignore($select_fields, $field, 'sql');
				$select_fields = zz_field_select_ignore($select_fields, $field, 'unique');
				$text .= zz_field_concat($field, $select_fields);
			} else {
				zz_error_log([
					'msg' => 'Record for <strong>%s</strong> does not exist. (ID: %s)',
					'msg_args' => [$field['title'], wrap_html_escape($value)]
				]);
				zz_error_exit(true);
				return ['', ''];
			}
		} elseif (isset($field['enum'])) {
			$text .= zz_htmltag_escape($display_value);
		}
	} elseif ($record) {
		if (isset($field['timestamp']) && $field['timestamp']) {
			$text .= zz_timestamp_format($display_value);
		} elseif (isset($field['display_field'])) {
			if (!empty($record[$field['display_field']]))
				$text .= zz_htmltag_escape($record[$field['display_field']]);
			elseif (!empty($record_saved[$field['display_field']]))
				$text .= zz_htmltag_escape($record_saved[$field['display_field']]);
			else {
				if (empty($field['append_next']))
					if (!empty($field['value'])) $text .= $field['value'];
					else $text .= zz_field_will_add_auto($field);
			}
		} else {
			if (!empty($display_value)) {
				$text .= zz_htmltag_escape($display_value);
			} elseif (!empty($record_saved[$field['field_name']])) {
				$text .= zz_htmltag_escape($record_saved[$field['field_name']]);
			} elseif (!empty($field['null'])) {
				$text .= zz_htmltag_escape($display_value);
			} else {
				if (empty($field['append_next']))
					if (!empty($field['value'])) $text .= $field['value'];
					else $text .= zz_field_will_add_auto($field);
			}
		}
	} else {
		if ($display_value) {
			if ($field_type === 'select')
				$text .= zz_field_will_add_auto($field);
			else
				$text .= zz_htmltag_escape($display_value);
		} else $text .= zz_field_will_add_auto($field);
	}
	if ($mark_italics) $text = zz_record_mark_italics($text, $mode);
	if (!in_array($mode, ['delete', 'show'])) {
		$hidden = zz_form_element($field['f_field_name'], $value, 'hidden', true);
	} else {
		$hidden = '';
	}
	return [$text, $hidden];
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
	if (!empty($field['value']))
		$value = $field['value'];
	elseif ($record AND array_key_exists($field['field_name'], $record))
		$value = $record[$field['field_name']];
	else
		$value = '';

	// return form element
	if (!in_array($mode, ['delete', 'show'])) {
		$text = zz_form_element($field['f_field_name'], $value, 'hidden', true);
	} else {
		$text = '';
	}
	// + return text
	if (!empty($record[$field['field_name']])) {
		$text .= zz_record_mark_italics(zz_timestamp_format($record[$field['field_name']]), $mode);
	} else {
		$text .= zz_field_will_add_auto($field);
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
			$text .= zz_field_will_add_auto($field);
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

	$fieldattr = [];
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
		$text = wrap_text('No data available.');
	} 

	// return text
	if (!isset($field['add_foreign'])) return $text;
	if (!$id_value) return $text.wrap_text('No entry possible. First save this record.');
	return $text.' <a href="'.$field['add_foreign'].$id_value
		.'&amp;referer='.urlencode(wrap_setting('request_uri')).'">['
		.wrap_text('Edit …').']</a>';
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
		if ($record) return '('.wrap_text('hidden').')';
		else return '';
	}

	// get value
	$value = $record ? $record[$field['field_name']] : '';
	
	// return form element
	$fieldattr = [];
	$fieldattr['size'] = $field['size'];
	if (!empty($field['maxlength']))
		$fieldattr['maxlength'] = $field['maxlength'];
	if (!empty($field['minlength']))
		$fieldattr['minlength'] = $field['minlength'];
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
	// new: the know-it-alls of all big browsers decided that autocomplete is evil
	// to hinder the browser fill in your password in a new login record, we need
	// an extra password and username field where these
	$text = '<input type="text" class="hidden"><input type="password" class="hidden">'
		.$text;
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
	$fieldattr = [];
	$fieldattr['size'] = $field['size'];
	$fieldattr['required'] = true;
	if (empty($field['dont_require_old_password'])) {
		$field['old_password'] = zz_form_element($field['f_field_name'], '', 'password', true, $fieldattr);
	}
	if (!empty($field['maxlength']))
		$fieldattr['maxlength'] = $field['maxlength'];
	if (!empty($field['minlength']))
		$fieldattr['minlength'] = $field['minlength'];
	$field['new_password_1'] = zz_form_element($field['f_field_name'].'_new_1', '', 'password', true, $fieldattr);
	$field['new_password_2'] = zz_form_element($field['f_field_name'].'_new_2', '', 'password', true, $fieldattr);
	$field['id'] = zz_make_id_fieldname($field['f_field_name']);
	return wrap_template('zzform-field-password-change', $field);
}

/**
 * record output of field type 'text' and others
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @param bool $dont_reformat
 * @return string
 */
function zz_field_text($field, $display, $record, $dont_reformat = false) {
	// get value
	$value = $record[$field['field_name']] ?? '';
	if (!$dont_reformat) {
		$value = zz_field_format($value, $field);
	}

	// return text
	if ($display !== 'form') {
		// show zeros
		if ($value === '') return '';
		if ($value === NULL) return '';
		switch ($field['type']) {
		case 'url':
		case 'url+placeholder':
			$linktitle = zz_cut_length($value, $field['max_select_val_len']);
			$linktitle = str_replace('<', '&lt;', $linktitle);
			$linktitle = wrap_punycode_decode($linktitle);
			return '<a href="'.wrap_html_escape($value).'">'.$linktitle.'</a>';
		case 'mail':
			$value = str_replace('<', '&lt;', $value);
			return '<a href="mailto:'.$value.'">'.$value.'</a>';
		case 'mail+name':
			$value = str_replace('<', '&lt;', $value);
			return '<a href="mailto:'.rawurlencode($value).'">'.$value.'</a>';
		case 'parameter':
			return zz_parameter_format($value);
		case 'phone':
			return zz_phone_format($value);
		case 'username':
			return zz_username_format($value, $field);
		default:
			// escape HTML elements
			$value = str_replace('<', '&lt;', $value);
			return $value;
		}
	}

	// return form element
	$fieldtype = 'text';
	switch ($field['type']) {
	case 'mail':
		$fieldtype = 'email'; break;
	case 'url':
	case 'url+placeholder':
		$value = wrap_punycode_decode($value); break;
	case 'text':
		if (!empty($field['sql']) OR !empty($field['cfg'])) {
			$field['unrestricted'] = true;
			if (!empty($field['cfg']))
				$field['xhr_command'] = 'zzform-configs';
			zz_xhr_add('selects', $field);
		}
		break;
	case 'parameter':
		$field['rows'] = 1;
		return zz_field_memo($field, $display, $record);
	}

	// 'url' in Opera does not support relative URLs
	// elseif ($field['type'] === 'url') $fieldtype = 'url';
	// time is not supported correctly by Google Chrome (adds AM, PM to time
	// and then complains that there's an AM, PM. Great programming, guys!)
//	elseif ($field['type'] === 'time') $fieldtype = 'time';
	$fieldattr = [];
	$fieldattr['size'] = $field['size'];
	$fieldattr['placeholder'] = $field['placeholder'];
	if ($field['required']) $fieldattr['required'] = true;
	if (!empty($field['maxlength']))
		$fieldattr['maxlength'] = $field['maxlength'];
	if (!empty($field['minlength']))
		$fieldattr['minlength'] = $field['minlength'];
	if (!empty($field['pattern'])) $fieldattr['pattern'] = $field['pattern'];
	elseif ($field['type'] === 'phone') $fieldattr['pattern'] = '[+()0-9-/ ]+';
	return zz_form_element($field['f_field_name'], $value, $fieldtype, true, $fieldattr);
}

/**
 * record output of field type 'number'
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @param bool $dont_reformat
 * @return string
 */
function zz_field_number($field, $display, $record, $dont_reformat) {
	// get value
	$value = $record ? $record[$field['field_name']] : '';
	$suffix = false;
	$formtype = 'text';
	$fieldattr = [];

	if (!isset($field['number_type'])) $field['number_type'] = false;
	switch ($field['number_type']) {
	case 'bytes':
		// do not reformat bytes as it will result in a loss of information
		break;
	case 'range':
		$formtype = 'range';
		$num = zz_check_number($value);
		if ($num.'' === '0') $value = 0;
		else $value = intval($value);
		$fieldattr['step'] = $field['step'] ?? 1;
		break;
	case 'rating':
		return zz_field_rating($field, $display, $record);
	case 'latitude':
	case 'longitude':
		if (!$record) break;
		if ($value === NULL) {
			$value = '';
			break;
		} elseif (isset($field['check_validation']) AND !$field['check_validation']) {
			// validation was not passed, hand back invalid field
			break;
		} elseif ($dont_reformat) {
			// just a detail record was added, value is already formatted
			break;
		}
		// calculate numeric value, hand back formatted value
		$value = zz_geo_coord_in($value, $field['number_type']);
		$value = $value['value'];
		if (!empty($field['geo_display_behind'])) {
			$suffix = wrap_coordinate($value, $field['number_type'], $field['geo_display_behind']);
			if ($suffix) $suffix = ' <small>( = '.$suffix.')</small>';
		}
		// no escaping please
	default: // this is for latitude and longitude as well!
		$formtype = 'text_noescape';
		// reformat 1 434,00 EUR and similar values
		$num = zz_check_number($value);
		if ($num.'' === '0') {
			$value = 0;
		} elseif ($num !== NULL) {
			// only apply number_format if it's a valid number
			$value = zz_number_format($num, $field);
		}
		break;
	}
	
	// return text
	if ($display !== 'form') return zz_htmltag_escape($value, ENT_QUOTES).$suffix;

	// return form element
	$fieldattr['size'] = $field['size'];
	$fieldattr['placeholder'] = $field['placeholder'];
	if ($field['required']) $fieldattr['required'] = true;
	if (!empty($field['maxlength']))
		$fieldattr['maxlength'] = $field['maxlength'];
	if (!empty($field['minlength']))
		$fieldattr['minlength'] = $field['minlength'];
	if (isset($field['max'])) {
		$fieldattr['max'] = $field['max'];
		if ($formtype !== 'range') $formtype = 'number';
	}
	if (isset($field['min'])) {
		$fieldattr['min'] = $field['min'];
		if ($formtype !== 'range') $formtype = 'number';
	}
	if (!empty($field['pattern'])) $fieldattr['pattern'] = $field['pattern'];
	$text = zz_form_element($field['f_field_name'], $value, $formtype, true, $fieldattr);
	return $text.$suffix;
}

/**
 * record output of field type 'number', number_type rating
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @return string
 */
function zz_field_rating($field, $display, $record) {
	$value = $record[$field['field_name']] ?? 0;
	$value = intval($value);

	$data = [];
	$symbols = $field['max'] ?? wrap_setting('zzform_rating_max');
	for ($i = 1; $i <= $symbols; $i++) {
		if ($display !== 'form') {
			$data[]['selected'] = $i <= $value ? true : false;
		} else {
			$element = [
				'type' => 'radio',
				'name' => $field['f_field_name'],
				'value' => $i,
				'id' => zz_make_id_fieldname($field['f_field_name'].'_'.$i),
				'checked' => $value === $i ? true : false,
				'title' => $i,
				'autofocus' => false
			];
			$data[] = zz_record_element($element);
		}
	}
	$text = wrap_template('zzform-record-field-rating', $data);
	return $text;
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
	$value = $record ? $record[$field['field_name']] : '';

	// return text
	if ($display !== 'form') return $value;

	// return form element
	$fieldattr = [];
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
	$fieldattr = [];
	$fieldattr['size'] = 12;
	$fieldattr['placeholder'] = !empty($field['placeholder']) ? trim($field['placeholder']) : wrap_text('Date');
	if ($field['required']) $fieldattr['required'] = true;
	// HTML5 fieldtype date has bad usability in Opera (calendar only!)
	return zz_form_element($field['f_field_name'], $value, 'text_noescape', true, $fieldattr);
}

/**
 * record output of field type 'datetime'
 *
 * @param array $field
 * @param string $display
 * @param array $record
 * @return string
 */
function zz_field_datetime($field, $display, $record) {
	// get value
	$value = $record ? zz_datetime_format($record[$field['field_name']], $field) : '';

	// return text
	if ($display !== 'form') return $value;


	// return form element
	$fieldattr = [];
	$fieldattr['size'] = $field['size'];
	$fieldattr['placeholder'] = !empty($field['placeholder']) ? trim($field['placeholder']) : wrap_text('Date and time');
	if ($field['required']) $fieldattr['required'] = true;
	// datetime in Safari is like 2011-09-06T20:50Z
	// $fieldtype = 'datetime';
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
	global $zz_conf;

	// get value
	$value = $record ? $record[$field['field_name']] : '';
	if ($field['type'] === 'parameter' AND $value) {
		$value = str_replace('&', "\n\n", ltrim($value, '&'));
	}

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
	$fieldattr = [];
	$fieldattr['placeholder'] = $field['placeholder'] ?? false;
	!empty($field['cols']) OR $field['cols'] = 60;
	!empty($field['rows']) OR $field['rows'] = 8;
	if ($record AND $value) {
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
	if (!empty($field['maxlength'])) {
		$fieldattr['maxlength'] = $field['maxlength'];
		$displayed = $field['rows'] * $field['cols'];
		if ($displayed AND $displayed > $field['maxlength']) {
			$fieldattr['rows'] = ceil($field['maxlength'] / $field['cols']);
		}
	}
	if (!empty($field['minlength']))
		$fieldattr['minlength'] = $field['minlength'];
	if ($fieldattr['rows'] < 2) $fieldattr['rows'] = 2;
	if (!empty($field['format']) AND $field['format'] === 'markdown'
		AND !empty($zz_conf['wmd_editor'])) {
		$fieldattr['class'] = 'wmd-input';
		$fieldattr['id'] = 'wmd-input-'.$zz_conf['wmd_editor'];
	}
	if (!empty($field['format']) AND $field['format'] === 'markdown'
		AND !empty($zz_conf['upndown_editor'])) {
		$fieldattr['class'] = 'markdown';
		$fieldattr['id'] = 'markdown-'.$zz_conf['upndown_editor'];
	}

	if (!empty($field['sql'])) {
		$field['unrestricted'] = true;
		$field['field_id'] = $fieldattr['id'];
		zz_xhr_add('selects', $field);
	}

	$text = zz_form_element($field['f_field_name'], $value, 'textarea', true, $fieldattr);
	if (!empty($field['format']) AND $field['format'] === 'markdown'
		AND !empty($zz_conf['wmd_editor'])) {
		$text = sprintf('<div class="wmd-panel"><div id="wmd-button-bar-%s"></div>', $zz_conf['wmd_editor'])
			.$text.'</div>'."\n";
		if ($zz_conf['wmd_editor'] === true) $zz_conf['wmd_editor'] = 1;
		$zz_conf['wmd_editor']++;
	}
	if (!empty($field['format']) AND $field['format'] === 'markdown'
		AND !empty($zz_conf['upndown_editor'])) {
		if ($zz_conf['upndown_editor'] === true) $zz_conf['upndown_editor'] = 1;
		$text = sprintf(
			'<div id="upndown-wysiwyg-%d"><textarea id="wysiwyg-%d" rows="%d" class="upndown-wysiwyg" cols="%d"></textarea></div>',
			$zz_conf['upndown_editor'], $zz_conf['upndown_editor'], $fieldattr['rows'], $fieldattr['cols']
		)."\n".'<div id="upndown-markdown-%d">'.$text.'</div>';
		$zz_conf['upndown_editor']++;
	}
	return $text;
}

/**
 * record output of field type 'set', but as a subtable
 *
 * @param array $subtable $zz field definition of subtable
 * @param string $display
 * @param array $my_tab
 * @return string
 */
function zz_field_subtable_set($subtable, $display, $my_tab) {
	global $zz_conf;

	// get select field
	$field = [];
	$field_names = [];
	foreach ($my_tab[0]['fields'] as $index => $my_field) {
		$field_names[$my_field['type']] = $my_field['field_name'];
		if ($my_field['type'] !== 'select') continue;
		$field = $my_field;
		break;
	}
	if (!$field) {
		zz_error_log([
			'msg_dev' => 'For a subtable with a form_display = `set`, there needs to be a field with a field type `select`.',
			'level' => E_USER_ERROR
		]);
		zz_error();
		return;
	}

	$sets = zz_field_query($field);
	if (!$sets) return;
	foreach ($sets as $index => $line)
		$sets[$index] = zz_field_select_ignore($line, $field, 'sql');

	$sets = zz_field_select_hierarchy($field, $sets);

	if (!empty($field['show_hierarchy'])) {
		foreach ($sets as $index => $set) {
			unset($sets[$index][$field['show_hierarchy']]);
		}
	}
	if (!$sets) return;

	$sets = zz_translate($field, $sets);
	$group = $field['group'] ?? false;

	$exemplary_set = reset($sets);
	$set_id_field_name = '';
	$set_field_names = [];
	foreach (array_keys($exemplary_set) as $key) {
		if (!$set_id_field_name) $set_id_field_name = $key;
		elseif ($key !== $group) $set_field_names[] = $key;
	}
	foreach ($sets as $set) {
		$title = [];
		foreach ($set_field_names as $set_field_name) {
			if (!$set[$set_field_name]) continue;
			if (!empty($field['sql_replace'][$set_id_field_name]) 
				AND $set_field_name === $field['sql_replace'][$set_id_field_name]) continue;
			if ($set_field_name === 'zz_level') continue;
			$title[] = $set[$set_field_name];
		}
		$sets_indexed[$set[$set_id_field_name]] = [
			'id' => !empty($field['sql_replace'][$set_id_field_name])
				? $set[$field['sql_replace'][$set_id_field_name]] : $set[$set_id_field_name],
			'title' => zz_field_concat($field, $title),
			'group' => $group ? $set[$group] : '',
			'zz_level' => zz_field_select_level_set($set['zz_level'] ?? 0)
		];
	}
	$rec_max = 0;
	foreach ($my_tab as $rec_no => $rec) {
		if (!is_numeric($rec_no)) continue;
		if (!empty($rec['record'])) {
			$rec = $rec['record'];
			if (!empty($field['sql_replace'][$set_id_field_name])) {
				foreach ($sets_indexed as $id => $set_indexed) {
					if ($set_indexed['id'] !== $rec[$field_names['select']]) continue;
					$sets_indexed[$id]['rec_id'] = $rec[$field_names['id']];
					$sets_indexed[$id]['checked'] = true;
					$sets_indexed[$id]['rec_no'] = $rec_no;
				}
			} elseif (array_key_exists($field_names['select'], $rec)) {
				$sets_indexed[$rec[$field_names['select']]]['rec_id'] = $rec[$field_names['id']] ?? '';
				$sets_indexed[$rec[$field_names['select']]]['checked'] = true;
				$sets_indexed[$rec[$field_names['select']]]['rec_no'] = $rec_no;
			}
			if ($rec_no > $rec_max) $rec_max = $rec_no;
		} elseif ((!empty($rec['POST']) AND !empty($zz_conf['int']['id']['source_value'])
			OR (!empty($rec['POST']) AND $rec['action'] === 'review'))) {
			// add from source
			$rec = $rec['POST'];
			foreach ($subtable['fields'] as $subfield) {
				if (!array_key_exists('field_name', $subfield)) continue;
				if (empty($rec[$subfield['field_name']])) continue;
				if (!array_key_exists($rec[$subfield['field_name']], $sets_indexed)) continue;
				// value exists, so say it's a default value
				$sets_indexed[$rec[$subfield['field_name']]]['default'] = true;
			}
		}
	}
	foreach ($sets_indexed as $index => $set) {
		if (isset($set['rec_no'])) continue;
		$sets_indexed[$index]['rec_no'] = ++$rec_max;
	}
	// set defaults if mode (no id value) = add but not add from source (also no source_value)
	if (empty($zz_conf['int']['id']['value']) AND empty($zz_conf['int']['id']['source_value'])
		AND !empty($subtable['default']) AND $display === 'form') {
		foreach ($subtable['default'] as $def)
			$sets_indexed[$def]['checked'] = true;
	}
	$last_group = '';
	$last_index = NULL;
	
	$data = [];
	foreach ($sets_indexed as $index => $set) {
		if ($display === 'form') {
			if ($group AND $set['group'] !== $last_group) {
				$list_open = true;
				$last_group = $set['group'];
				if (!is_null($last_index)) $data[$last_index]['list_close'][]['close'] = true;
			} else {
				$list_open = false;
			}
			$last_index = $index;
			if (!empty($set['rec_id'])) {
				$data['hidden'][] = [
					'name' => sprintf('%s[%d][%s]',
						$subtable['table_name'], $set['rec_no'], $field_names['id']
					),
					'value' => $set['rec_id']
				];
				$data['hidden'][] = [
					'name' => sprintf('%s[%d][%s]',
						$subtable['table_name'], $set['rec_no'], $field_names['select']
					)
				];
			}
			if (!empty($set['id'])) {
				$data[$index] = [
					'id' => sprintf('check-%s-%d', $subtable['table_name'], $set['rec_no']),
					'name' => sprintf('%s[%d][%s]', $subtable['table_name'], $set['rec_no'], $field_names['select']),
					'value' => $set['id'],
					'title' => $set['title'],
					'checked' => !empty($set['checked']) ? true : false,
					'group' => $set['group'] ?? NULL,
					'list_open' => $list_open
				
				];
			} elseif (!empty($set['rec_id']) AND !wrap_is_int($set['rec_id'])) {
				wrap_quit(400, 'Malformed request, ID for a set needs to be numeric');
			} else {
				zz_error_log([
					'msg_dev' => 'Found a value selected that is set to non-selectable (table %s, ID %d)',
					'msg_dev_args' => [$subtable['table'], $set['rec_id']]
				]);
			}
		} elseif (!empty($set['rec_id']) AND !empty($set['title'])) {
			// title might be empty for non-selectable IDs
			$data[$index]['title'] = $set['title'];
		}
		$data[$index]['level'] = $set['zz_level'];
	}
	if (!$data) return '';
	$data = zz_field_select_levels($data);
	if ($group AND $display === 'form') $data[$last_index]['list_close'][]['close'] = true;
	if ($display === 'form') $data['form_display'] = true;
	return wrap_template('zzform-record-checkbox', $data);
}

/**
 * Output form element type="select", foreign_key with sql query
 * 
 * @param array $field field that will be checked
 * @param string $display
 * @param array $record $my_rec['record']
 * @param string $db_table db_name.table
 * @return string HTML output for form
 */
function zz_field_select_sql($field, $display, $record, $db_table) {
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	$lines = zz_field_query($field);
	$lines = zz_field_unique_ignore($lines, $field);
	$too_many_records = array_key_exists('too_many_records', $lines) ? true : false;
// #1.4 SELECT has no result
	if (!$lines) {
		$outputf = zz_form_element($field['f_field_name'], '', 'hidden', true)
			.wrap_text('No selection possible.');
		zz_error();
		$outputf .= zz_error_output();
		return zz_return($outputf);
	}

	// translate values?
	$lines = zz_translate($field, $lines);

// #1.2 SELECT has only one result in the array, and this will be pre-selected 
// because FIELD must not be NULL
	if ($display === 'form' AND count($lines) === 1 
		AND (!zz_db_field_null($field['field_name'], $db_table)
			OR !empty($field['required']))
		AND empty($field['select_dont_force_single_value'])
	) {
		return zz_return(zz_field_select_single($lines, $record, $field));
	}

// #1.3 SELECT has one or several results, let user select something

	$detail_record = zz_field_select_get_record($field, $record);
	$detail_record = zz_translate($field, $detail_record);

	// 1.3.1: no form display = no selection, just display the values in the record
	if ($display !== 'form') {
		if (!$detail_record) return zz_return('');
		$outputf = zz_draw_select($field, $record, $detail_record);
		return zz_return($outputf);
	}

	// ok, we display something!
	// re-index lines by id_field_name if it makes sense
	if (!$too_many_records) {
		$lines = zz_field_select_lines($field, $lines);
		// do we have to display the results hierarchical?
		if (!empty($field['show_hierarchy'])) {
			$lines = zz_field_select_hierarchy($field, $lines, $record);
		} else {
			$field['show_hierarchy'] = false;
		}
		// subtree might change the amount of lines
		$count_rows = count($lines);
	} else {
		$count_rows = $lines[0];
	}

	if ($display === 'form' AND $count_rows === 1 
		AND (!zz_db_field_null($field['field_name'], $db_table)
			OR !empty($field['required']))
		AND empty($field['select_dont_force_single_value'])
	) {
		return zz_return(zz_field_select_single($lines, $record, $field));
	}

	// 1.3.2: more records than we'd like to display
	if ($count_rows > $field['max_select']) {
		return zz_return(zz_field_select_sql_too_long(
			$field, $record, $detail_record
		));
	}

	// 1.3.3: draw RADIO buttons
	if (!empty($field['show_values_as_list'])) {
		return zz_return(zz_field_select_sql_radio($field, $record, $lines));
	}
		
	// 1.3.4: draw a SELECT element
	$fieldattr = zz_field_dependent_fields($field, $lines);
	if ($field['required']) $fieldattr['required'] = true;
	$outputf = zz_form_element($field['f_field_name'], '', 'select', true, $fieldattr)."\n";

	// first OPTION element
	// normally don't show a value, unless we only look at a part of a hierarchy
	$fieldvalue = zz_field_select_value_hierarchy($field, $record, $field['key_field']);
	if (!$fieldvalue) $field['show_hierarchy_use_top_value_instead_NULL'] = false;

	$fieldattr = [];
	if ($record) if (!$record[$field['field_name']]) $fieldattr['selected'] = true;
	if (isset($field['text_none_selected'])) {
		$display = wrap_text($field['text_none_selected'], ['source' => wrap_setting('zzform_script_path')]);
	} else {
		$display = wrap_text('None selected');
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
				$outputf .= zz_draw_select($field, $record, $line, 'form', 1);
			}
			$outputf .= '</optgroup>'."\n";
		} else {
			foreach ($lines as $line) {
				$outputf .= zz_draw_select($field, $record, $line, 'form');
			}
		}
	} elseif ($detail_record) {
		// re-edit record, something was posted, ignore hierarchy because 
		// there's only one record coming back
		$outputf .= zz_draw_select($field, $record, $detail_record, 'form');
	} elseif (!empty($field['show_hierarchy_use_top_value_instead_NULL'])) {
		$outputf = zz_form_element($field['f_field_name'], $field['show_hierarchy_subtree'], 'hidden', true);
		$close_select = false;
	} elseif (!empty($field['show_hierarchy_subtree']) OR ($field['show_hierarchy'])) {
		$outputf = zz_form_element($field['f_field_name'], '', 'hidden', true)
			.wrap_text('(This entry is the highest entry in the hierarchy.)');
		$close_select = false;
	} else {
		$outputf = zz_form_element($field['f_field_name'], '', 'hidden', true)
			.wrap_text('No selection possible.');
		$close_select = false;
	}

	if ($close_select) $outputf .= '</select>'."\n";
	zz_error();
	$outputf .= zz_error_output();
	return zz_return($outputf);
}

/**
 * just one line for select: return preselected
 *
 * @param array $lines
 * @param array $record
 * @param array $field
 * @return string
 */
function zz_field_select_single($lines, $record, $field) {
	$line = array_shift($lines);
	// compare as strings here!
	if ($record AND $record[$field['field_name']] AND $line[$field['key_field']].'' !== $record[$field['field_name']].'') {
		$outputf = 'Possible Values: '.$line[$field['key_field']]
			.' -- Current Value: '
			.wrap_html_escape($record[$field['field_name']])
			.' -- Error --<br>'.wrap_text('No selection possible.');
	} elseif (!empty($field['disabled']) AND in_array($line[$field['key_field']], $field['disabled'])) {
		$outputf = wrap_text('No selection possible.');
	} else {
		$outputf = zz_form_element($field['f_field_name'], $line[$field['key_field']],
			'hidden', true).zz_draw_select($field, $record, $line);
	}
	return $outputf;
}

/**
 * remove fields which are not required if values are already unique
 *
 * @param array $lines
 * @param array $field
 * @return array $lines
 */
function zz_field_unique_ignore($lines, $field) {
	if (empty($field['unique_ignore'])) return $lines;
	if (!is_array($field['unique_ignore'])) {
		$field['unique_ignore'] = [$field['unique_ignore']];
	}
	$keep_next = [];
	foreach ($lines as $index => $line) {
		array_shift($line); // get rid of index
		$unique = false;
		$last_field_name = '';
		foreach ($line as $field_name => $value) {
			if ($unique AND in_array($field_name, $field['unique_ignore'])) {
				if (empty($keep_next[$index][$field_name])) {
					unset($lines[$index][$field_name]);
				}
				continue;
			}
			if (!array_key_exists($index + 1, $lines)) {
				$unique = true;
				continue;
			}
			if ($lines[$index + 1][$field_name] !== $value) {
				$unique = true;
				if ($last_field_name) {
					$keep_next[$index + 1][$last_field_name] = true;
					$keep_next[$index + 1][$field_name] = true;
				}
				continue;
			}
			$last_field_name = $field_name;
		}
	}
	return $lines;
}

/**
 * Query records for select element
 *
 * save results as there might be more than one sub record using the same select
 * element
 * @param array $field 'sql', 'show_hierarchy_subtree', 'max_select'
 * @return array lines from database or 'too_many_records' count is too high
 */
function zz_field_query($field) {
	static $results = [];
	// we do not show all fields if query is bigger than $field['max_select']
	// so no need to query them (only if show_hierarchy_subtree is empty)
	if (empty($field['show_hierarchy_subtree']) AND empty($field['show_hierarchy'])
		AND isset($field['max_select'])) {
		if (isset($field['sqlcount'])) {
			$count_records = zz_db_fetch($field['sqlcount']);
			if (reset($count_records) > $field['max_select']) {
				$lines[] = $count_records;
				$lines['too_many_records'] = true;
				return $lines;
			}
		}
		$sql = wrap_edit_sql($field['sql'], 'LIMIT', '0, '.($field['max_select'] + 1));
	} else {
		$sql = $field['sql'];
	}
	if (array_key_exists($sql, $results)) return $results[$sql];
	// return with warning, don't exit here
	$results[$sql] = zz_db_fetch($sql, '_dummy_id_', 'numeric', '', E_USER_WARNING);
	return $results[$sql];
}

/**
 * draws a single INPUT field instead of SELECT/OPTION
 * in case there are too many values
 *
 * @param array $field
 * @param array $record
 * @param array $detail_record
 * @return string
 * @todo AJAX typeaheadfind
 */
function zz_field_select_sql_too_long($field, $record, $detail_record) {
	$outputf = zz_form_element('zz_check_select[]', $field['select_field_name'], 'hidden');

	zz_xhr_add('selects', $field);

	// don't show select but text input instead
	if ($detail_record) {
		$outputf .= zz_draw_select($field, $record, $detail_record, 'reselect');
		return $outputf;
	}

	// value will not be checked if one detail record is added because 
	// in this case validation procedure will be skipped!
	$value = $record[$field['field_name']] ?? '';
	// add new record
	$fieldattr = [];
	$fieldattr['size'] = !empty($field['size_select_too_long']) ? $field['size_select_too_long'] : 32;
	$fieldattr['placeholder'] = $field['placeholder'];
	if ($field['required']) $fieldattr['required'] = true;
	$outputf .= zz_form_element($field['f_field_name'], $value, 'text_noescape', true, $fieldattr);

	return $outputf;
}

/**
 * add a field to possible XHR selects or dependencies
 *
 * @param array $field
 * @global array $zz_conf
 */
function zz_xhr_add($type, $field) {
	global $zz_conf;

	$default_command = ($type === 'selects') ? 'zzform' : 'zzform-'.$type;
	$zz_conf['int'][$type][] = [
		'field_no' => $field['field_no'],
		'subtable_no' => $field['subtable_no'],
		'field_id' => $field['field_id'] ?? zz_make_id_fieldname($field['f_field_name']),
		'url_self' => zz_xhr_url_self(),
		'destination_field_ids' => $field['destination_field_ids'] ?? [],
		'source_field_ids' => $field['source_field_ids'] ?? [],
		'unrestricted' => $field['unrestricted'] ?? false,
		'command' => $field['xhr_command'] ?? $default_command,
		'rec' => $field['rec'] ?? false
	];
}

/**
 * get own URL for XHR
 *
 * @global array $zz_conf
 */
function zz_xhr_url_self() {
	$extra = [];
	if (!empty($_POST) AND array_key_exists('zz_fields', $_POST) AND $_POST['zz_action'] === 'insert') {
		foreach ($_POST['zz_fields'] as $field_name => $field_id)
			$extra['add'][$field_name] = $field_id;
	} elseif (!empty($_GET['add'])) {
		$extra['add'] = $_GET['add'];
	}
	// remove single quotes (= escaping for JavaScript)
	foreach ($extra as $key => $values) {
		if (!is_array($values)) continue; // e. g. add = ID for copying
		foreach ($values as $field_name => $value)
			if (is_array($value)) unset($extra[$key][$field_name]);
			else $extra[$key][$field_name] = str_replace("'", '', $value);
	}
	$url = zzform_url_add($extra, zzform_url('self+qs'));
	$url .= parse_url($url, PHP_URL_QUERY) ? '&' : '?';
	return $url;
}

/**
 * get field IDs for XHR request for dependencies
 *
 * @param array $field
 * @param array $fields
 * @param int $rec
 * @return array $field
 */
function zz_xhr_dependencies($field, $fields, $rec) {
	foreach ($field['dependencies'] as $dependency) {
		if (strstr($dependency, '.')) {
			$nos = explode('.', $dependency);
			if (empty($fields[$nos[0]]['fields'][$nos[1]])) continue;
			$field_id = zz_make_id_fieldname(sprintf('%s[0][%s]'
				, $fields[$nos[0]]['f_field_name']
				, $fields[$nos[0]]['fields'][$nos[1]]['field_name'] 
			));
			$field['destination_field_ids'][] = [
				'field_id' => $field_id,
				'field_no' => $dependency
			];
			continue;
		}
		if (empty($fields[$dependency])) continue;
		$field_id = zz_make_id_fieldname($fields[$dependency]['f_field_name']);
		if (!empty($fields[$dependency]['enum'])) {
			$field_ids = [];
			foreach ($fields[$dependency]['enum'] as $index => $value) {
				$field_ids[] = [
					'field_id' => $field_id,
					'sub_field_id' => sprintf('%s-%d', $field_id, $index + 1),
					'value' => $value
				];
			}
			$field['destination_field_ids'][] = [
				'field_id' => $field_id,
				'field_ids' => $field_ids,
				'field_no' => $dependency
			];
		} else {
			$field['destination_field_ids'][] = [
				'field_id' => $field_id,
				'field_no' => $dependency
			];
		}
	}
	if (!empty($field['dependencies_sources'])) {
		foreach ($field['dependencies_sources'] as $dependency) {
			$field['source_field_ids'][] = [
				'field_id' => zz_make_id_fieldname($fields[$dependency]['f_field_name']),
				'field_no' => $dependency
			];
		}
	}
	$field['rec'] = $rec;
	return $field;
}

/**
 * re-order $lines hierarchically, i. e. as $lines[$parent_id][$id] = $line
 * to avoid infinite recursion, show_hierarchy_same_table will be checked
 *
 * @param array $field
 * @param array $lines
 * @param array $record
 * @return array
 */
function zz_field_select_hierarchy($field, $lines, $record = []) {
	if (!$lines) return [];
	$my_select = [];
	foreach ($lines as $line) {
		// if hierarchy is hierarchy of same table, don't allow to set
		// IDs in hierarchy or below to avoid recursion
		if (!empty($field['show_hierarchy_same_table']) AND !empty($record[$field['key_field']])) {
			if ($line[$field['key_field']] === $record[$field['key_field']]) continue;
			if ($line[$field['show_hierarchy']] === $record[$field['key_field']]) continue;
		}
		// fill in values, index NULL is for uppermost level
		$index = array_key_exists('show_hierarchy', $field) ? ($line[$field['show_hierarchy']] ?? 'NULL') : 'NULL';
		$my_select[$index][$line[$field['key_field']]] = $line;
	}

	// if there are no values for subtree, set subtree to false
	if (!empty($field['show_hierarchy_subtree'])
		AND empty($my_select[$field['show_hierarchy_subtree']])) {
		if (!empty($field['possible_values'])) {
			// it's a check_select, not all values are available
			// so probably hierarchy is impossible to create
			return [];
		}
		if (empty($lines[$field['show_hierarchy_subtree']])) {
			zz_error_log([
				'msg_dev' => 'Subtree with ID %s does not exist.',
				'msg_dev_args' => [$field['show_hierarchy_subtree']],
				'error' => E_USER_WARNING
			]);
		}
		return [];
	}
	$lines = zz_field_sethierarchy($field, $my_select, $field['show_hierarchy_subtree'] ?? false);
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
	static $levels = 0;
	if ($level) $levels = $level;

	if ($subtree) {
		$branches = $lines[$subtree];
	} elseif (!empty($lines['NULL'])) {
		$branches = $lines['NULL'];
	} else {
		// there are no NULL-values, so we either have the uppermost
		// element in the hierarchy or simply no elements at all
		return [];
	}

	foreach ($branches as $id => $line) {
		$line['zz_level'] = $level;
		$tree[$id] = $line;
		if (!empty($lines[$id])) {
			$tree += zz_field_sethierarchy($field, $lines, $id, $level + 1);
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
	$radios = [];
	$lines = zz_field_unique_ignore($lines, $field);
	$last_group = '';
	foreach ($lines as $id => $line) {
		$pos++;
		array_shift($line); // get rid of ID, is already in $id
		$line = zz_field_select_ignore($line, $field, 'sql');
		if ($field['show_hierarchy']) unset($line[$field['show_hierarchy']]);
		// group display
		if (!empty($field['group']) AND $line[$field['group']]) {
			if (!$last_group OR $last_group !== $line[$field['group']]) {
				$radios[] = [
					'level' => $line['zz_level'] ?? 0,
					'element' => $line[$field['group']],
					'id' => ''
				];
				$last_group = $line[$field['group']];
			}
			unset($line[$field['group']]);
		}
		// level
		$field['zz_level'] = zz_field_select_level_set($line['zz_level'] ?? 0);
		unset($line['zz_level']);
		$radios[] = zz_field_select_radio_value($field, $record, $id, zz_field_concat($field, $line), $pos);
	}
	if (!empty($field['show_no_option']))
		$radios[] = zz_field_select_radio_value($field, $record, 0, wrap_text('No selection'), ++$pos);
	$fieldattr = zz_field_dependent_fields($field, $lines);
	return zz_field_select_radio($field, $record, $radios, $fieldattr);
}

/**
 * calculate relative level of element
 *
 * @param int $new_level
 * @return int
 */
function zz_field_select_level_set($new_level = 0) {
	static $level = 0;
	$oldlevel = $level;
	$level = $new_level;
	return $level - $oldlevel;
}

/**
 * get $lines (i. e. all records that will be presented in an
 * SELECT/OPTION HTML element) only if needed, otherwise this will need 
 * a lot of memory usage
 *
 * @param array $field
 * @param array $lines
 * @return array
 */
function zz_field_select_lines($field, $lines) {
	if (count($lines) <= $field['max_select'] 
		OR !empty($field['show_hierarchy_subtree'])) {
		$details = [];
		// re-index $lines by value from id_field_name
		foreach ($lines as $line)
			$details[$line[$field['key_field']]] = $line;
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
	if (!is_dir($field['set_folder']))
		wrap_error('`'.$field['set_folder'].'` is not a folder. Check `["set_folder"]` definition.', E_USER_ERROR);
	$files = [];
	$handle = opendir($field['set_folder']);
	while ($file = readdir($handle)) {
		if (substr($file, 0, 1) === '.') continue;
		$files[] = $file;
	}
	if (!$files) {
		$field['set'] = [];
	} elseif ($field['set_title'] === true) {
		$field['set_title'] = [];
		foreach ($files as $file) {
			$size = filesize($field['set_folder'].'/'.$file);
			$size = wrap_bytes($size);
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
function zz_field_select_get_record($field, $record) {
	if (empty($record[$field['field_name']])) return [];
	
	// get value
	$db_value = $record[$field['field_name']];
	if (substr($db_value, 0, 1) === '"' && substr($db_value, -1) === '"')
		$db_value = substr($db_value, 1, -1);

	if (substr($field['sql'], 0, 4) === 'SHOW') {
		if (strstr($field['sql'], 'LIKE'))
			$sql = $field['sql'];
		else
			$sql = wrap_edit_sql($field['sql'], 'WHERE', $field['key_field_name']
				.sprintf(' LIKE "%s"', $db_value));
	} else {
		// only check numeric values, others won't give a valid result
		// for these, just display the given values again
		if (!is_numeric($db_value)) return [];
		// get SQL query
		$sql = wrap_edit_sql($field['sql'], 'WHERE', $field['key_field_name']
			.sprintf(' = %d', $db_value));
	}

	if (!$sql) $sql = $field['sql'];

	// fetch query
	$detail_records = zz_db_fetch($sql, $field['key_field'], '', "record: "
		.$field['field_name'].' (probably \'id_field_name\' needs to be set)');
	
	// only one record?
	if (count($detail_records) === 1) {
		$detail_record = reset($detail_records);
		return $detail_record;
	}
	
	// check for equal record values
	foreach ($detail_records as $line) {
		if ($line[$field['key_field']] !== $record[$field['field_name']]) continue;
		return $line;
	}
	return [];
}

/**
 * outputs radio button list
 *
 * @param array $field
 * @param array $record
 * @param array $data (output of zz_field_select_radio_value())
 * @param array $fieldattr (optional)
 * @global array $zz_conf
 * @return string $text
 */
function zz_field_select_radio($field, $record, $data, $fieldattr = []) {
	// variant: only one value with a possible NULL value
	if (count($data) === 1) {
		$data[0]['attributes']['type'] = 'checkbox';
		array_unshift($data, zz_record_element([
			'type' => 'hidden',
			'name' => $field['f_field_name']
		]));
		return wrap_template('zzform-record-radio', $data);
	}
	
	if (!empty($field['enum_textinput']))
		$data = zz_field_select_radio_text($field, $record, $data);

	$data['id'] = zz_make_id_fieldname($field['f_field_name']);
	$data['attributes'] = $fieldattr ? $fieldattr : ''; // string for zzbrick
	$none = zz_field_radio_none($field, $record);
	if ($none) array_unshift($data, $none);

	$data = zz_field_select_levels($data);

	if (empty($field['show_values_as_list'])) {
		// variant: only two or three values next to each other
		$data['inline'] = true;
	} else {
		// variant: more values as a list
		$data['list'] = true;
		if (!empty($field['append_next'])) {
			global $zz_conf;
			$data['append_next'] = true;
			$zz_conf['int']['append_next_type'] = 'list';
		}
	}
	return wrap_template('zzform-record-radio', $data);
}

/**
 * radio button list: display first radio button with no value
 *
 * @param array $field
 * @param array $record
 * @return string
 */
function zz_field_radio_none($field, $record) {
	// if it is required to select one of the radio button values,
	// the empty value is illegal so it will not be shown
	if ($field['required']) return [];
	if (!isset($field['hide_novalue'])) $field['hide_novalue'] = true;

	$element = [
		'type' => 'radio',
		'value' => '', // there needs to be an empty value, or it will be 'on'
		'name' => $field['f_field_name'],
		'id' => zz_make_id_fieldname($field['f_field_name']).'-0',
		'class' => $field['hide_novalue'] ? 'hidden' : NULL,
	];
	if (!$record) {
		// no value, no default value 
		// (both would be written in my record fieldname)
		$element['checked'] = true;
	} elseif (!$record[$field['field_name']]) {
		$element['checked'] = true;
	}
	$element = zz_record_element($element);
	$line = [
		'id' => zz_make_id_fieldname($field['f_field_name']).'-0',
		'label_attributes' => $field['hide_novalue'] ? ['class' => 'hidden'] : NULL,
		'label_none' => true,
		'tag' => $element['tag'],
		'attributes' => $element['attributes']
	];
	return $line;
}

/**
 * radio button list: display radio button with value
 *
 * @param array $field
 * @param array $record
 * @param mixed $value (int, string)
 * @param string $label
 * @param int $pos
 * @return array
 */
function zz_field_select_radio_value($field, $record, $value, $label, $pos) {
	$id = zz_make_id_fieldname($field['f_field_name']).'-'.$pos;
	$selected = zz_field_selected($field, $record, $value);
	if (!is_bool($selected)) $value = $selected;

	$element = [
		'type' => 'radio',
		'value' => $value,
		'checked' => $selected ? true : false,
		'required' => $field['required'],
		'name' => $field['f_field_name'],
		'id' => $id,
		'disabled' => !empty($field['disabled']) AND in_array($value, $field['disabled']) ? true : false
	];
	$element = zz_record_element($element);
	
	return [
		'level' => $field['zz_level'] ?? 0,
		'label' => $label,
		'id' => $id,
		'tag' => $element['tag'],
		'attributes' => $element['attributes'],
	];
}

/**
 * determine whether a field is selected/checked or not
 * allow field to have a translated value via 'enum_translated'
 *
 * @param array $field
 * @param array $record
 * @param mixed $value
 * @return mixed
 *		bool checked or not checked
 *		string value, might change if translated
 */
function zz_field_selected($field, $record, $value) {
	if (!$record) return false;
	// no === comparison here!
	// because of numeric values which might be
	// represented by a string
	if (!empty($field['enum'])) {
		if ($value == $record[$field['field_name']]) return true;
		if (!empty($field['enum_translated'])) {
			$key = array_search($value, $field['enum']);
			foreach ($field['enum_translated'] as $translations) {
				if ($translations[$key] != $record[$field['field_name']]) continue;
				return $translations[$key];
			}
		}
	} elseif (!empty($field['set'])) {
		if (!$record[$field['field_name']]) {
			$set = [];
		} elseif (!is_array($record[$field['field_name']])) {
			//	won’t be array normally
			$set = explode(',', $record[$field['field_name']]);
		} else {
			// just if a field did not pass validation, 
			// set fields become arrays
			$set = $record[$field['field_name']];
		}
		if (in_array($value, $set)) return true;
		if (!empty($field['set_translated'])) {
			$key = array_search($value, $field['set']);
			foreach ($field['set_translated'] as $translations) {
				if (!in_array($translations[$key], $set)) continue;
				return $translations[$key];
			}
		}
	} elseif (!empty($field['sql'])) {
		if ($record[$field['field_name']].'' === $value.'') return true;
	}
	return false;
}

/**
 * add a text input after the last element for free entry of data
 * via $zz['fields'][$no]['enum_textinput'] = true;
 *
 * @param array $field
 * @param array $record
 * @param array $data
 * @return array
 */
function zz_field_select_radio_text($field, $record, $data) {
	end($data);
	$last_index = key($data);

	$element = [
		'type' => 'text',
		'create_id' => true,
		'size' => 32,
		'class' => 'js-checkable',
		'data-check-id' => $data[$last_index]['id'],
	];
	// get name
	$element['name'] = $field['f_field_name'];
	if (substr($element['name'], -1) === ']') {
		$element['name'] = substr($element['name'], 0, -1).'--text]';
	} else {
		$element['name'] .= '--text';
	}

	// get value
	$element['value'] = '';
	if (!empty($record[$field['field_name']])) {
		if (!in_array($record[$field['field_name']], $field['enum'])) {
			$element['value'] = $record[$field['field_name']];
		}
	}
	$element = zz_record_element($element);
	$data[$last_index]['append_next'] = true; // no closing list item
	$data[] = [
		'level' => $data[$last_index]['level'],
		'tag' => $element['tag'],
		'attributes' => $element['attributes'],
		'extra' => true
	];
	return $data;
}

/**
 * add markers if elements should be shown in hierarchy
 * append_next, list_open, list_close
 *
 * @param array $data
 * @return array
 */
function zz_field_select_levels($data) {
	$last = NULL;
	$list_open = 0;
	foreach ($data as $index => $line) {
		if (!is_numeric($index)) continue;
		if (!array_key_exists('level', $line))
			$line['level'] = $data[$index]['level'] = 0;
		switch ($line['level']) {
		case 1:
			$data[$last]['append_next'] = true;
			$data[$index]['list_open'] = true;
			$list_open++;
			break;
		case 0:
			break;
		default:
			// negative values
			for ($i = 0; $i > $line['level']; $i--) {
				$data[$last]['list_close'][]['close'] = true;
				$list_open--;
			}
		}
		$last = $index;
	}
	while ($list_open > 0) {
		$data[$last]['list_close'][]['close'] = true;
		$list_open--;
	}
	return $data;
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
 */
function zz_field_select_set($field, $display, $record, $rec) {
	$data = [];
	$field['id'] = zz_make_id_fieldname($field['f_field_name']);
	if ($display === 'form')
		// send dummy field to get a response if field content should be deleted
		$data['hidden'][] = ['name' => $field['f_field_name'].'[]'];

	$i = 0;
	foreach ($field['set'] as $key => $set) {
		if ($display === 'form') {
			$i++;
			$data[$key] = [
				'title' => zz_print_enum($field, $set, 'full'),
				'id' => 'check-'.$field['id'].'-'.$i,
				'name' => $field['f_field_name'].'[]',
				'value' => $set,
			];
			if ($record AND isset($record[$field['field_name']])) {
				$selected = zz_field_selected($field, $record, $set);
				if ($selected) $data[$key]['checked'] = true;
				if (!is_bool($selected)) $data[$key]['value'] = $selected;
			} elseif (!empty($field['default_select_all'])) {
				$data[$key]['checked'] = true;
			} else {
				$data[$key]['checked'] = false;
			}
			if (empty($data[$key]['checked']) AND !empty($field['disabled_ids']) 
				AND is_array($field['disabled_ids'])
				AND in_array($set, $field['disabled_ids'])) {
				$data[$key]['disabled'] = true;
			}
			// required does not work at least in Firefox with set
			// because identical 'name'-attributes are not recognized
			// if ($field['required']) $data[$key]['required'] = true;
		} elseif (empty($field['set_show_all_values']))  {
			$selected = zz_field_selected($field, $record, $set);
			if (!$selected) continue;
			$data[$key]['title'] = zz_print_enum($field, $set, 'full');
		}
	}
	if ($display !== 'form' AND !empty($field['set_show_all_values'])) {
		// show folders even if they are deleted
		$lines = explode(',', $record[$field['field_name']]);
		foreach ($lines as $line)
			$data[]['title'] = $line;
	}

	return wrap_template('zzform-record-checkbox', $data);
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
			$selected = zz_field_selected($field, $record, $set);
			if (!$selected) continue;
			$text = zz_print_enum($field, $set, 'full', $key);
		}
		if (!$text AND !empty($field['enum_textinput'])) {
			$text = $record[$field['field_name']];
		}
		if (!empty($field['show_values_as_list'])) {
			$zz_conf['int']['append_next_type'] = 'list';
		}
		return $text;
	}

	// check if should be shown as a list
	// and if yes, return a list
	if (!empty($field['enum_textinput'])) {
		$field['show_values_as_list'] = true;
	}

	$fieldattr = zz_field_dependent_fields($field, $field['enum']);
	
	if (count($field['enum']) <= 2) {
		$sel_option = true;
	} elseif (!empty($field['show_values_as_list'])) {
		$sel_option = true;
	} else {
		$sel_option = false;
	}
	if ($sel_option) {
		$myi = 0;
		$radios = [];
		foreach ($field['enum'] as $key => $set) {
			$myi++;
			$label = zz_print_enum($field, $set, 'full', $key);
			if (!empty($field['enum_textinput']) AND $key + 1 === count($field['enum'])
				AND !empty($record[$field['field_name']])
				AND !in_array($record[$field['field_name']], $field['enum'])) {
				// check last item by turning value of set into saved value
				$set = $record[$field['field_name']];
			}
			$radios[] = zz_field_select_radio_value($field, $record, $set, $label, $myi);
		}
		return zz_field_select_radio($field, $record, $radios, $fieldattr);
	}

	if ($field['required']) $fieldattr['required'] = true;
	$text = zz_form_element($field['f_field_name'], '', 'select', true, $fieldattr)."\n";
	$fieldattr = [];
	if ($record) { 
		if (!$record[$field['field_name']])
			$fieldattr['selected'] = true;
	} else {
		// no value, no default value (both would be 
		// written in my record fieldname)
		$fieldattr['selected'] = true;
	}
	if (isset($field['text_none_selected'])) {
		$display = wrap_text($field['text_none_selected'], ['source' => wrap_setting('zzform_script_path')]);
	} else {
		$display = wrap_text('None selected');
	}
	$text .= zz_form_element($display, '', 'option', false, $fieldattr)."\n";
	foreach ($field['enum'] as $key => $set) {
		$fieldattr = [];
		$selected = zz_field_selected($field, $record, $set);
		$internal_value = $set;
		if ($selected !== false) {
			$fieldattr['selected'] = true;
			if ($selected !== true) $internal_value = $selected;
		}
		if (empty($fieldattr['selected']) AND !empty($field['disabled_ids']) 
			AND is_array($field['disabled_ids'])
			AND in_array($set, $field['disabled_ids'])) {
			$fieldattr['disabled'] = true;
		}
		$text .= zz_form_element(zz_print_enum($field, $set, 'full', $key), $internal_value, 'option', false, $fieldattr)."\n";
	}
	$text .= '</select>'."\n";
	return $text;
}

/**
 * add WHERE to $zz['fields'][n]['sql'] clause if necessary
 * 
 * @param array $field field that will be checked
 * @param array $where_fields = $zz['record']['where'][$table_name]
 * @global array $zz_conf
 *		$zz_conf['int']['add_details_where']
 * @return array string $field['sql']
 */
function zz_form_select_sql_where($field, $where_fields) {
	if (empty($field['sql_where'])) return $field['sql'];

	global $zz_conf;
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	$where_conditions = [];
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
	$field['sql'] = wrap_edit_sql($field['sql'], 'WHERE', implode(' AND ', $where_conditions));

	return zz_return($field['sql']);
}

/**
 * HTML output of values, either in <option>, <input> or as plain text
 *
 * @param array $field field definition
 * @param array $record $my_rec['record']
 * @param array $line record from database
 * @param string $form (optional) 
 *		false => outputs just the selected and saved value
 *		'reselect' => outputs input element in case there are too many elements,
 *		'form' => outputs option fields
 * @param int $addlevel
 * @return string $output HTML output
 * @see zz_field_select_sql()
 */
function zz_draw_select($field, $record, $line, $form = false, $addlevel = 0) {
	// initialize variables
	$i = 1;
	$details = [];
	if (!isset($field['show_hierarchy'])) $field['show_hierarchy'] = false;
	if (isset($line['zz_level'])) {
		$level = $line['zz_level'];
		unset($line['zz_level']);
	} else {
		$level = 0;
	}
	if ($addlevel) $level++;
	if (empty($field['sql_index_only'])) {
		$line = zz_field_select_ignore($line, $field, 'sql');
		$line = zz_field_select_format($line, $field);
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
		$key = $field['key_field'];
	}
	// remove empty fields, makes no sense
	foreach ($details as $my_key => $value)
		if (!$value) unset ($details[$my_key]);
	// if only the id key is in the query, eg. show databases:
	if (!$details) $details = $line[$key];
	if (is_array($details)) $details = zz_field_concat($field, $details);
	$fieldvalue = $details;
	// remove linebreaks
	if ($fieldvalue)
		$fieldvalue = str_replace("\r\n", " ", $fieldvalue);
	if ($form === 'reselect') {
		$fieldattr = [];
		$fieldattr['size'] = $field['size_select_too_long'] ?? 32;
		if ($field['required']) $fieldattr['required'] = true;
		// extra space, so that there won't be a LIKE operator that this value
		// will be checked against!
		$output = zz_form_element($field['f_field_name'], $fieldvalue.' ', 'text_noescape', true, $fieldattr);
	} elseif ($form) {
		// remove tags, leave &#-Code as is
		$fieldvalue = strip_tags($fieldvalue);
		$fieldattr = [];
		// check == to compare strings with numbers as well
		if ($record AND $line[$field['key_field']] == $record[$field['field_name']]) {
			$fieldattr['selected'] = true;
		} elseif (!empty($field['disabled_ids']) 
			AND is_array($field['disabled_ids'])
			AND in_array($line[$field['key_field']], $field['disabled_ids'])) {
			$fieldattr['disabled'] = true;
		}
		if ($level !== 0) $fieldattr['class'] = 'level'.$level;
		// database, table or field names which do not come with an ID?
		if ($line[$field['key_field']] === $fieldvalue)
			$line[$field['key_field']] = sprintf(' %s ', $line[$field['key_field']]);
		$output = zz_form_element($fieldvalue, $line[$field['key_field']], 'option', true, $fieldattr)."\n";
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
 * @param string $type ('sql' for 'sql_ignore' or 'unique' for 'unique_ignore') 
 * @return array ($line, modified)
 */
function zz_field_select_ignore($line, $field, $type) {
	$ignore = $type.'_ignore';
	if (empty($field[$ignore])) return $line;
	if (!is_array($field[$ignore]))
		$field[$ignore] = [$field[$ignore]];
	if ($keys = array_intersect(array_keys($line), $field[$ignore]))
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
 * @return string
 */
function zz_field_file($field, $display, $record, $record_saved, $images, $mode, $fieldkey) {				
	$data = [];

	if (($mode !== 'add' OR $field['type'] !== 'upload_image')
		AND (empty($field['dont_show_image'])) || !$field['dont_show_image']) {
		if (isset($field['path'])) {
			$data['image'] = zz_makelink($field['path'], $record, 'image');
			if (!$data['image']) unset($data['image']);
		}
		if (empty($data['image']) AND !empty($record_saved)) {
			$data['image'] = zz_makelink($field['path'], $record_saved, 'image');
			if (!$data['image']) unset($data['image']);
		}
		if (empty($data['image']) AND (!isset($field['dont_show_missing']) OR !$field['dont_show_missing'])) {
			if (!isset($field['dont_show_missing_img']) OR !$field['dont_show_missing_img']) {
				$data['no_image'] = true;
			}
		}
	}
	
	$uploads = zz_field_file_uploads($field['image'] ?? []);
	if (in_array($mode, ['add', 'edit', 'revise']) && $field['type'] === 'upload_image') {
		if (!isset($field['image'])) {
			zz_error_log([
				'msg' => [
					'File upload is currently impossible.',
					'An error occured. We are working on the '
					.'solution of this problem. Sorry for your '
					.'inconvenience. Please try again later.'],
				'msg_dev' => 'Configuration error. Missing upload_image details.',
				'level' => E_USER_WARNING
			]);
			return '';
		}
		if (count($uploads) > 1) $data['multiple_uploads'] = true;
		$i = 0;
		foreach ($uploads as $imagekey => $image) {
			// @todo if only one image, table is unnecessary
			// title and field_name of image might be empty
			$data[$i]['title'] = $image['title'] ?? '';
			$elementname = zz_make_id_fieldname($field['f_field_name']).'['.$image['field_name'].']';
			$data[$i]['input'] = zz_form_element($elementname, '', 'file', false);
			if (empty($field['dont_show_file_link'])
				AND $data[$i]['link'] = zz_makelink($image['path'], $record_saved ?? $record)) {
				if (count($uploads) > 1 OR !empty($field['optional_image'])) {
					$data[$i]['delete_checkbox_id'] = 'delete-file-'.$fieldkey.'-'.$imagekey;
					$element = zz_record_element([
						'type' => 'checkbox',
						'name' => 'zz_delete_file['.$fieldkey.'-'.$imagekey.']',
						'id' => $data[$i]['delete_checkbox_id'],
						'autofocus' => false
 					]);
					$data[$i]['delete_checkbox'] = $element['attributes'];
				}
			}
			if (!empty($images[$fieldkey][$imagekey]['error'])) {
				foreach ($images[$fieldkey][$imagekey]['error'] as $error_msg)
					$data[$i]['error'][]['error_msg'] = $error_msg;
			} else {
				if (!empty($images[$fieldkey][$imagekey]['upload']['size'])) {
					$data[$i]['size'] = $images[$fieldkey][$imagekey]['upload']['size'];
					$data[$i]['filetype'] = $images[$fieldkey][$imagekey]['upload']['filetype'];
				} elseif (!empty($_FILES)) {
					$file_key = zz_make_id_fieldname($field['f_field_name']);
					$img_key = $field['image'][0]['field_name'];
					if (!empty($_FILES[$file_key]['tmp_name'][$img_key])) {
						$data[$i]['size'] = $_FILES[$file_key]['size'][$img_key];
						$data[$i]['filetype'] = zz_upload_file_extension($_FILES[$file_key]['tmp_name'][$img_key]);
					}
				}
				$data[$i]['upload_max_filesize'] = $field['upload_max_filesize'];
				$data[$i]['upload_filetypes'] = zz_upload_supported_filetypes($field['input_filetypes']);
			}
			if ($display === 'form' && !empty($image['explanation'])) 
				$data[$i]['explanation'] = $image['explanation'];
			$i++;
		}
	} elseif (isset($field['image']) AND count($uploads) > 1) {
		$data['multiple_uploads'] = true;
		$i = 0;
		foreach ($uploads as $imagekey => $image) {
			$link = zz_makelink($image['path'], $record);
			if (!$link) continue;
			$data[$i]['title'] = $image['title'] ?? '';
			$data[$i]['link'] = $link;
			$i++;
		}
	}
	if (!$data) return '';
	return wrap_template('zzform-record-field-file', $data);
}

/**
 * get uploads from file upload field
 *
 * @param array $files
 * @return array
 */
function zz_field_file_uploads($files) {
	$uploads = [];
	if (!$files) return $uploads;
	foreach ($files as $key => $file) {
		if (isset($file['source'])) continue;
		if (isset($file['source_field'])) continue;
		$uploads[$key] = $file;
	}
	return $uploads;
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
	if (isset($field['hidden_value']))
		return zz_form_element($field['f_field_name'], $field['hidden_value'], 'hidden', true);

	// return text
	// display_value ?
	// internationalization has to be done in zz-fields-definition
	if (isset($field['display_value'])) {
		if (zz_get_fieldtype($field) === 'number') {
			$field['display_value'] = zz_number_format($field['display_value'], $field);
		}
		return zz_htmltag_escape($field['display_value']);
	}
	// no record
	if (!$record) {
		if (isset($field['display_empty'])) return $field['display_empty'];
		else return wrap_text('<abbr title="Not available">N/A</abbr>');
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

		$value = zz_htmltag_escape($value);
		if (!empty($field['translate_field_value']))
			$value = wrap_text($value, ['source' => wrap_setting('zzform_script_path')]);
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
			$value = wrap_text($value);

		$value = wrap_html_escape($value);
		if (isset($field['format'])) {
			$value = $field['format']($value);
		} else {
			$value = zz_field_format($value, $field);
		}
		return $value;
	}

	// @todo debug!
	return '<span class="error">'
		.wrap_text('Script configuration error. No display field set.').'</span>';
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
	if ($mode AND !in_array($mode, ['revise', 'show'])) {
		return '('.wrap_text('calculated field').')';
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
 * Output form element type="captcha"
 *
 * @param array $field
 * @param array $record
 * @param string $mode
 * @return string
 */
function zz_field_captcha($field, $record, $mode) {
	global $zz_conf;
	// captcha only for adding, otherwise hide field
	if ($mode !== 'add') return '';
	if (!empty($field['captcha_solved'])) {
		$field['captcha_solved'] = wrap_set_hash($zz_conf['id'], 'zzform_captcha_key');
	} else {
		$field['zz_id'] = $zz_conf['id'];
		$field['alt_text'] = zz_captcha_alt_text(zz_captcha_code($zz_conf['id']));
	}
	return wrap_template('zzform-captcha', $field);
}

/**
 * sets concat string for fields in select
 *
 * @param array $field
 * @param array $values
 * @return string
 */
function zz_field_concat($field, $values) {
	$concat = zz_select_concat($field);
	// only concat existing values
	$count = count($values);
	$values = array_values($values);
	foreach ($values as $index => $value)
		$values[$index] = zz_htmltag_escape($value);

	// check values for line breaks, existing |
	// but only if content is displayed as option, not if there are radio buttons
	if (empty($field['show_values_as_list'])) {
		foreach ($values as $index => $value)
			$values[$index] = zz_select_escape_value($value, $concat);
	}

	for ($i = 0; $i < $count; $i++) {
		if (isset($field['concat_'.$i]) AND !empty($values[$i]))
			$values[$i] = sprintf($field['concat_'.$i], $values[$i]);
		if (isset($field['format_'.$i]) AND !empty($values[$i]) AND function_exists($field['format_'.$i]))
			$values[$i] = $field['format_'.$i]($values[$i]);
	}
	$values = array_filter($values);
	return implode($concat, $values);
}

/**
 * mark record in italics if possible change on update
 *
 * @param string $out
 * @param string $mode
 * @return string
 */
function zz_record_mark_italics($out, $mode) {
	if (in_array($mode, ['delete', 'show'])) return $out;
	return sprintf('<em title="%s">%s</em>', wrap_text('Would be changed on update'), $out);
}

/**
 * set field attributes for dependent fields for use with JavaScript
 *
 * @param array $field
 * @param array $lines
 * @return array field attributes
 */
function zz_field_dependent_fields($field, $lines) {
	if (empty($field['dependent_fields'])) return [];

	$fieldattr = [];
	foreach ($field['dependent_fields'] as $field_no => $dependent_field) {
		foreach ($lines as $field_id => $line) {
			if (!is_array($line)) {
				// it’s an 'enum'
				if ($dependent_field['if_selected'] !== $line) continue;
				$field_id = $line;
			} else {
				if (empty($line[$dependent_field['if_selected']])) continue;
			}
			$fieldattr['data-dependent_field_'.$field_no][] = $field_id;
		}
		if (!empty($fieldattr['data-dependent_field_'.$field_no]))
			$fieldattr['data-dependent_field_'.$field_no] = implode(',', $fieldattr['data-dependent_field_'.$field_no]);
	}
	return $fieldattr;
}

/**
 * --------------------------------------------------------------------
 * S – Subtable functions
 * --------------------------------------------------------------------
 */

/**
 * outputs input form element for subtable add/remove
 *
 * @param string $mode add | remove
 * @param array $field
 * @param int $tab
 * @param int $rec (optional)
 * @return string HTML
 */
function zz_record_subtable_submit($mode, $field, $tab, $rec = 0) {
	$fieldattr = [];
	// $zz['fields'][2]['select_empty_no_add'] = true;
	foreach ($field['fields'] as $subfield) {
		if (empty($subfield['select_empty_no_add'])) continue;
		if (empty($subfield['sql'])) continue;
		$records = zz_db_fetch($subfield['sql'], '_dummy_', 'numeric');
		if (!$records) return '';
	}

	if (empty($field['title_button']))
		$field['title_button'] = strip_tags($field['title']); 
	else
		$field['title_button'] = wrap_text(
			$field['title_button'], ['source' => wrap_setting('zzform_script_path')]
		);

	switch ($mode) {
	case 'add':
		$value = wrap_text('Add %s', ['values' => $field['title_button']]);
		$name = sprintf('zz_subtables[add][%s]', $tab);
		$fieldattr['class'] = 'sub-add';
		$fieldattr['formnovalidate'] = true;
		break;
	case 'remove':
		$value = wrap_text('Remove');
		$name = sprintf('zz_subtables[remove][%s][%s]', $tab, $rec);
		$fieldattr['class'] = 'sub-remove-'.$field['form_display'];
		$fieldattr['formnovalidate'] = true;
		$fieldattr['title'] = wrap_text('Remove %s', ['values' => $field['title_button']]);
		break;
	}
	return zz_form_element($name, $value, 'submit', false, $fieldattr);
}

/**
 * prepare JS for dependent fields
 *
 * @param array $field
 * @param array $my_fields
 * @param int $id_value = $my_rec['id']['value']
 * @return
 */
function zz_record_subtable_dependencies($field, $my_fields, $id_value) {
	global $zz_conf;
	if (empty($field['dependent_fields'])) return false;

	// check if subtable
	// check if field = write_once
	foreach ($field['dependent_fields'] as $field_no => $dependent_field) {
		if (empty($my_fields[$field_no])) continue;
		$show_dependency = true;
		// check for write_once fields that cannot change,
		// change eventListener obviously not working there
		foreach ($field['fields'] as $subfield) {
			if ($subfield['field_name'] !== $dependent_field['field_name']) continue;
			if ($subfield['type'] !== 'write_once') continue;
			if (!$id_value) continue;
			$show_dependency = false;
		}
		if (!$show_dependency) continue;
		$zz_conf['int']['js_field_dependencies'][] = [
			'main_field_id' => zz_make_id_fieldname($field['table_name'].'[0]['.$dependent_field['field_name'].']'),
			'dependent_field_id' => zz_make_id_fieldname($my_fields[$field_no]['f_field_name']),
			'required' => !empty($dependent_field['required']) ? true : false,
			'field_no' => $field_no,
			'has_translation' => !empty($my_fields[$field_no]['has_translation']) ? true : false
		];
	}
}

/**
 * check for subtables with form_display = 'lines' if there’s an error somewhere
 * mark full row accordingly
 *
 * @param array $my_tab
 * @return bool
 */
function zz_record_subtable_check_error($my_tab) {
	$error_found = [];
	foreach ($my_tab as $no => $rec) {
		if (!is_numeric($no)) continue;
		$error_found[$no] = false;
		foreach ($rec['fields'] as $field) {
			if (empty($field['class'])) continue;
			foreach ($field['class'] as $class) {
				if (strstr($class, 'error')) {
					$error_found[$no] = true;
					continue 3;
				}
			}
		}
	}
	$all_have_errors = true;
	if (!$error_found) $all_have_errors = false;
	foreach ($error_found as $found) {
		if (!$found) {
			$all_have_errors = false;
			break;
		}
	}
	return $all_have_errors;
}

/**
 * check if there is an error inside a subtable
 *
 * @param array $my_tab
 * @return bool
 */
function zz_record_subtable_error($my_tab) {
	foreach ($my_tab as $rec_no => $rec) {
		if (!is_numeric($rec_no)) continue;
		foreach ($rec['fields'] as $no => $field)
			if (in_array('error', $field['class'])) return true;
	}
	return false;
}
