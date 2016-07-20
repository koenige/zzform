<?php 

/**
 * zzform
 * Preparation functions for record- and action-Modules
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2016 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Prepares table definitions, database content and posted data
 *  for 'action' and 'record'-modules
 *
 * @param array $zz
 * @param array $zz_var
 * @param string $mode ($ops['mode'])
 * @return array $zz_tab
 */
function zz_prepare_tables($zz, $zz_var, $mode) {
	global $zz_conf;
	global $zz_error;

	$zz_tab = array();
	// ### variables for main table will be saved in zz_tab[0]
	$zz_tab[0]['db_name'] = $zz_conf['db_name'];
	$zz_tab[0]['table'] = $zz['table'];
	$zz_tab[0]['table_name'] = $zz['table'];
	$zz_tab[0]['sql'] = isset($zz['sqlrecord']) ? $zz['sqlrecord'] : $zz['sql'];
	$zz_tab[0]['sql_without_where'] = $zz['sql_without_where'];
	$zz_tab[0]['sqlextra'] = !empty($zz['sqlextra']) ? $zz['sqlextra'] : array();
	$zz_tab[0]['sql_translate'] = !empty($zz['sql_translate']) ? $zz['sql_translate'] : array();
	$zz_tab[0]['hooks'] = !empty($zz['hooks']) ? $zz['hooks'] : array();
	$zz_tab[0]['folder'] = !empty($zz['folder']) ? $zz['folder'] : array();
	$zz_tab[0]['dynamic_referer'] = !empty($zz['dynamic_referer']) ? $zz['dynamic_referer'] : false;
	$zz_tab[0]['add_from_source_id'] = !empty($zz['add_from_source_id']) ? true : false;
	$zz_tab[0]['filter'] = !empty($zz['filter']) ? $zz['filter'] : array();
	if (!empty($zz['set_redirect'])) {
		// update/insert redirects after_delete and after_update
		$zz_tab[0]['set_redirect'] = $zz['set_redirect'];
		if (!isset($zz_tab[0]['hooks']['after_delete']))
			$zz_tab[0]['hooks']['after_delete'] = true;
		if (!isset($zz_tab[0]['hooks']['after_update']))
			$zz_tab[0]['hooks']['after_update'] = true;
	}
	$zz_tab[0]['dont_reformat'] = !empty($_POST['zz_subtables']) ? true : false;
	foreach ($zz['fields'] as $field) {
		// geocoding?
		if (empty($field['type'])) continue;
		if ($field['type'] === 'subtable') {
			$continue = true;
			foreach ($field['fields'] as $subfield) {
				if (!empty($subfield['geocode'])) {
					$continue = false;
					break;
				}
			}
			if ($continue) continue;
		} elseif (empty($field['geocode'])) {
			continue;
		}
		$zz_tab[0]['geocode'] = true;
		if (!isset($zz_tab[0]['hooks']['after_validation']))
			$zz_tab[0]['hooks']['after_validation'] = true;
		break;
	}
	$zz_tab[0]['record_action'] = false;
	
	$zz_tab[0][0]['action'] = $zz_var['action'];
	$zz_tab[0][0]['fields'] = $zz['fields'];
	$zz_tab[0][0]['validation'] = true;
	$zz_tab[0][0]['record'] = false;
	$zz_tab[0][0]['access'] = !empty($zz['access']) ? $zz['access'] : false;

	// get ID field, unique fields, check for unchangeable fields
	$zz_tab[0][0]['id'] = &$zz_var['id'];
	
	//	### put each table (if more than one) into one array of its own ###
	foreach ($zz_var['subtables'] as $tab => $no) {
		if (!empty($zz['fields'][$no]['hide_in_form'])) continue;
		$zz_tab[$tab] = zz_get_subtable($zz['fields'][$no], $zz_tab[0], $tab, $no);
		if ($mode === 'show' AND $zz_tab[$tab]['values']) {
			// don't show values which are not saved in show-record mode
			$zz_tab[$tab]['values'] = array();
		}
		if ($zz_error['error']) return array();
		$zz_tab[$tab] = zz_get_subrecords(
			$mode, $zz['fields'][$no], $zz_tab[$tab], $zz_tab[0], $zz_var, $tab
		);
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if (empty($zz_tab[$tab]['POST'][$rec])) continue;
			$zz_tab[$tab][$rec]['POST'] = $zz_tab[$tab]['POST'][$rec];
			unset($zz_tab[$tab]['POST'][$rec]);
		}
		if ($zz_error['error']) return array();
		if (isset($zz_tab[$tab]['subtable_focus'])) {
			// set autofocus on subrecord, not on main record
			$zz_tab[0]['subtable_focus'] = 'set';
		}
	}

	if (!$zz_var['query_records']) return $zz_tab;

	if (!empty($zz_var['id']['value'])) {
		$zz_tab[0][0]['existing'] = zz_query_single_record(
			$zz_tab[0]['sql'], $zz_tab[0]['table'], $zz_var['id'], $zz_tab[0]['sqlextra'], $zz_tab[0]['sql_translate']
		);
		if ($zz_var['action'] === 'update' AND !$zz_tab[0][0]['existing']) {
			$zz_error['error'] = true;
			$zz_error[] = array(
				'msg_dev' => 'Trying to update a non-existent record in table `%s` with ID %d.',
				'msg_dev_args' => array($zz_tab[0]['table'], $zz_var['id']['value']),
				'level' => E_USER_ERROR
			);
			return false;
		}
	} elseif (!empty($zz_var['id']['values'])) {
		$sql = wrap_edit_sql($zz_tab[0]['sql'], 'WHERE', $zz_tab[0]['table'].'.'
			.$zz_var['id']['field_name']." IN ('".implode("','", $zz_var['id']['values'])."')");
		$existing = zz_db_fetch($sql, $zz_var['id']['field_name'], 'numeric');
		foreach ($existing as $index => $existing_rec) {
			$zz_tab[0][$index]['existing'] = $existing_rec;
		}
		// @todo think about sqlextra
	} else {
		$zz_tab[0][0]['existing'] = array();
	}

	// Upload
	// if there is a directory which has to be renamed, save old name in array
	// do the same if a file might be renamed, deleted ... via upload
	// or if there is a display or write_once field (so that it can be used
	// e. g. for identifiers):
	if ($zz_var['action'] === 'update' OR $zz_var['action'] === 'delete') {
		if (count($zz_var['save_old_record']) && !empty($zz_tab[0][0]['existing'])) {
			foreach ($zz_var['save_old_record'] as $no) {
				if (empty($zz_tab[0][0]['existing'][$zz['fields'][$no]['field_name']])) continue;
				$_POST[$zz['fields'][$no]['field_name']] 
					= $zz_tab[0][0]['existing'][$zz['fields'][$no]['field_name']];
			}
		}
	}

	// get rid of some POST values that are used at another place
	$zz_tab[0][0]['POST'] = array();
	foreach (array_keys($_POST) AS $key) {
		if (in_array($key, $zz_conf['int']['internal_post_fields'])) continue;
		$zz_tab[0][0]['POST'][$key] = wrap_normalize($_POST[$key]);
	}
	//  POST is secured, now get rid of password fields in case of error_log_post
	foreach ($zz['fields'] AS $field) {
		if (empty($field['type'])) continue;
		if ($field['type'] === 'password') unset($_POST[$field['field_name']]);
		if ($field['type'] === 'password_change') unset($_POST[$field['field_name']]);
	}

	// set defaults and values, clean up POST
	$zz_tab[0][0]['POST'] = zz_check_def_vals(
		$zz_tab[0][0]['POST'], $zz_tab[0][0]['fields'], $zz_tab[0][0]['existing'],
		(!empty($zz_var['where'][$zz_tab[0]['table']]) ? $zz_var['where'][$zz_tab[0]['table']] : array())
	);

	return $zz_tab;
}

/**
 * creates array for each detail table in $zz_tab
 *
 * @param array $field = $zz['fields'][$no] with subtable definition
 * @param array $main_tab = $zz_tab[0] for main record
 * @param int $tab = number of subtable
 * @param int $no = number of subtable definition in $zz['fields']
 * @global array $zz_conf
 *		'max_detail_records', 'min_detail_records'
 * @global array $_POST
 * @return array $my_tab = $zz_tab[$tab]
 *		'no', 'sql', 'sql_not_unique', 'keep_detailrecord_shown', 'db_name'
 *		'table', 'table_name', 'values', 'fielddefs', 'max_records', 
 *		'min_records', 'records_depend_on_upload', 
 *		'records_depend_on_upload_more_than_one', 'foreign_key_field_name',
 *		'translate_field_name', 'detail_key', 'tick_to_save', 'access'
 */
function zz_get_subtable($field, $main_tab, $tab, $no) {
	global $zz_conf;

	// basics for all subrecords of the same table
	$my_tab = array();

	// no in $zz['fields']
	$my_tab['no'] = $no;

	// SQL query
	$my_tab['sql'] = $field['sql'];
	if (empty($field['sql_not_unique'])) {
		$my_tab['sql_not_unique'] = false;
		$my_tab['keep_detailrecord_shown'] = false;
	} else {
		$my_tab['sql_not_unique'] = $field['sql_not_unique'];
		$my_tab['keep_detailrecord_shown'] = true;
	}
	$my_tab['sql_translate'] = !empty($field['sql_translate']) ? $field['sql_translate'] : array();
	// Hierachy?
	$my_tab['hierarchy'] = !empty($field['hierarchy']) ? $field['hierarchy'] : '';

	// database and table name
	if (strstr($field['table'], '.')) {
		$table = explode('.', $field['table']);
		$my_tab['db_name'] = $table[0];
		$my_tab['table'] = $table[1];
	} else {
		$my_tab['db_name'] = $main_tab['db_name'];
		$my_tab['table'] = $field['table'];
	}
	$my_tab['table_name'] = $field['table_name'];
	
	// pre-set values
	$my_tab['values'] = !empty($field['values']) ? $field['values'] : array();
	$my_tab['fielddefs'] = !empty($field['fielddefs']) ? $field['fielddefs'] : array();

	// records
	$settings = array('max', 'min');
	foreach ($settings as $set) {
		// max_detail_records, max_records, max_records_sql
		// min_detail_records, min_records, min_records_sql
		if ($my_tab['hierarchy']) {
			$my_tab[$set.'_records'] = 0;
			continue;
		}
		if (isset($field[$set.'_records'])) {
			$my_tab[$set.'_records'] = $field[$set.'_records'];
		} elseif (isset($field[$set.'_records_sql'])) {
			$my_tab[$set.'_records'] = zz_db_fetch($field[$set.'_records_sql'], '', 'single value');
		} else {
			$my_tab[$set.'_records'] = $zz_conf[$set.'_detail_records'];
		}
	}
	$my_tab['min_records_required'] = isset($field['min_records_required'])
		? $field['min_records_required'] : 0;
	if ($my_tab['min_records'] < $my_tab['min_records_required'])
		$my_tab['min_records'] = $my_tab['min_records_required'];
	$my_tab['records_depend_on_upload'] = isset($field['records_depend_on_upload'])
		? $field['records_depend_on_upload'] : false;
	$my_tab['records_depend_on_upload_more_than_one'] = 
		isset($field['records_depend_on_upload_more_than_one'])
		? $field['records_depend_on_upload_more_than_one'] : false;
	
	// foreign keys, translation keys, unique keys
	$my_tab['foreign_key_field_name'] = (!empty($field['foreign_key_field_name']) 
		? $field['foreign_key_field_name'] 
		: $main_tab['table'].'.'.$main_tab[0]['id']['field_name']);
	$my_tab['translate_field_name'] = !empty($field['translate_field_name']) 
		? $field['translate_field_name'] : false;
	$my_tab['unique'] = !empty($field['unique']) ? $field['unique'] : false;

	// get detail key, if there is a field definition with it.
	// get id field name
	$password_fields = array();
	foreach ($field['fields'] AS $subfield) {
		if (!isset($subfield['type'])) continue;
		if ($subfield['type'] === 'password') {
			$password_fields[] = $subfield['field_name'];
		}
		if ($subfield['type'] === 'password_change') {
			$password_fields[] = $subfield['field_name'];
		}
		if ($subfield['type'] === 'id') {
			$my_tab['id_field_name'] = $subfield['field_name'];
		}
		if ($subfield['type'] !== 'detail_key') continue;
		if (empty($main_tab[0]['fields'][$subfield['detail_key']])) continue;
		$detail_key_index = isset($subfield['detail_key_index']) 
			? $subfield['detail_key_index'] : 0;
		$my_tab['detail_key'][] = array(
			'tab' => $main_tab[0]['fields'][$subfield['detail_key']]['subtable'], 
			'rec' => $detail_key_index
		);
	}

	// tick to save
	$my_tab['tick_to_save'] = !empty($field['tick_to_save']) 
		? $field['tick_to_save'] : '';

	// access
	$my_tab['access'] = isset($field['access'])
		? $field['access'] : false;
	
	// POST array
	// buttons: add, remove subrecord
	$my_tab['subtable_deleted'] = array();
	$my_tab['subtable_ids'] = array();
	if (isset($_POST['zz_subtable_ids'][$my_tab['table_name']]))
		$my_tab['subtable_ids'] = explode(',', $_POST['zz_subtable_ids'][$my_tab['table_name']]);
	$my_tab['subtable_add'] = (!empty($_POST['zz_subtables']['add'][$tab]) 
		AND $my_tab['access'] !== 'show')
		? $_POST['zz_subtables']['add'][$tab] : false;
	$my_tab['subtable_remove'] = (!empty($_POST['zz_subtables']['remove'][$tab]) 
		AND $my_tab['access'] !== 'show')
		? $_POST['zz_subtables']['remove'][$tab] : array();

	// tick for save
	$my_tab['zz_save_record'] = !empty($_POST['zz_save_record'][$tab])
		? $_POST['zz_save_record'][$tab] : array();

	$my_tab['POST'] = (!empty($_POST) AND !empty($_POST[$my_tab['table_name']]) 
		AND is_array($_POST[$my_tab['table_name']]))
		? $_POST[$my_tab['table_name']] : array();
	foreach ($my_tab['POST'] as $rec => $fields) {
		foreach ($fields as $key => $value) {
			$my_tab['POST'][$rec][$key] = wrap_normalize($value);
		}
	}
	// POST is secured, now get rid of password fields in case of error_log_post
	foreach ($password_fields AS $password_field)
		unset($_POST[$my_tab['table_name']][$password_field]);

	// subtable_remove may come with ID
	foreach (array_keys($my_tab['subtable_remove']) as $rec) {
		if (empty($my_tab['subtable_remove'][$rec])) continue;
		if (!empty($my_tab['POST'][$rec][$my_tab['id_field_name']])) // has ID?
			$my_tab['subtable_deleted'][] = $my_tab['POST'][$rec][$my_tab['id_field_name']];
	}
	
	return $my_tab;
} 

/**
 * creates array for each detail record in $zz_tab[$tab]
 *
 * @param string $mode ($ops['mode'])
 * @param array $field
 * @param array $my_tab = $zz_tab[$tab]
 * @param array $main_tab = $zz_tab[0]
 * @param array $zz_var
 * @param array $tab = tabindex
 * @return array $my_tab
 */
function zz_get_subrecords($mode, $field, $my_tab, $main_tab, $zz_var, $tab) {
	global $zz_error;
	global $zz_conf;
	
	if ($my_tab['subtable_ids']) {
		$existing_ids = array_flip($my_tab['subtable_ids']);
		foreach ($my_tab['POST'] as $rec => $my_rec) {
			if (!array_key_exists($my_tab['id_field_name'], $my_rec)) continue;
			if (in_array($my_rec[$my_tab['id_field_name']], array_keys($existing_ids))) {
				unset($existing_ids[$my_rec[$my_tab['id_field_name']]]);
			}
		}
		foreach (array_keys($existing_ids) as $id) {
			// add to existing deleted IDs (may come from somewhere else!)
			$my_tab['subtable_deleted'][] = $id;
		}
	}
	
	// set general definition for all $my_tab[$rec] (kind of a record template)
	$rec_tpl = array();
	$rec_tpl['fields'] = $field['fields'];
	$rec_tpl['if'] = !empty($field['if']) ? $field['if'] : array();
	$rec_tpl['unless'] = !empty($field['unless']) ? $field['unless'] : array();
	$rec_tpl['access'] = $my_tab['access'];
	$rec_tpl['id']['field_name'] = $my_tab['id_field_name'];
	$rec_tpl['validation'] = true;
	$rec_tpl['record'] = false;
	$rec_tpl['action'] = false;

	// get state
	if ($mode === 'add' OR $zz_var['action'] === 'insert')
		$state = 'add';
	elseif ($mode === 'edit' OR $zz_var['action'] === 'update')
		$state = 'edit';
	elseif ($mode === 'delete' OR $zz_var['action'] === 'delete')
		$state = 'delete';
	else
		$state = 'show';

	// records may only be removed in state 'edit' or 'add'
	// but not with access = show
	if (($state === 'add' OR $state === 'edit') AND $rec_tpl['access'] !== 'show') {
		// remove deleted subtables
		foreach (array_keys($my_tab['subtable_remove']) as $rec) {
			if (empty($my_tab['subtable_remove'][$rec])) continue;
			unset($my_tab['POST'][$rec]);
			$my_tab['subtable_focus'] = $rec-1;
		}
		$my_tab['subtable_remove'] = array();
	} else {
		// existing records might not be deleted in this mode!
		$my_tab['subtable_deleted'] = array();
	}

	// get detail records from database 
	// subtable_deleted is empty in case of 'action'
	// remove records which have been deleted by user interaction
	if (in_array($state, array('edit', 'delete', 'show'))) {
		// add: no record exists so far
		$existing = zz_query_subrecord(
			$my_tab, $main_tab['table'], $main_tab[0]['id']['value'],
			$rec_tpl['id']['field_name'], $my_tab['subtable_deleted']
		);
	} else {
		$existing = array();
	}
	if (!empty($zz_error['error'])) return $my_tab;
	// get detail records for source_id
	$source_values = array();
	if ($mode === 'add' AND !empty($main_tab[0]['id']['source_value'])) {
		$my_tab['POST'] = zz_query_subrecord(
			$my_tab, $main_tab['table'], $main_tab[0]['id']['source_value'],
			$rec_tpl['id']['field_name'], $my_tab['subtable_deleted']
		);
		if (!empty($zz_error['error'])) return $my_tab;
		// get rid of foreign_keys and ids
		foreach ($my_tab['POST'] as $post_id => &$post_field) {
			foreach ($rec_tpl['fields'] AS $my_field) {
				if (empty($my_field['type'])) continue;
				if ($my_field['type'] !== 'id') continue;
				$source_values[$post_id] = $post_field[$my_field['field_name']];
			}
			$post_field = zz_prepare_clean_copy($rec_tpl['fields'], $post_field);
		}
	}

	// check if we have a sync or so and there's a detail record with
	// a unique field: get the existing detail record id if there's one
	if (!empty($zz_conf['multi'])) {
		$my_tab['POST'] = zz_subrecord_unique($my_tab, $existing, $field['fields']);
	}

	if ($my_tab['values'] AND $state !== 'delete') {
		// get field names for values
		// but not for records which will be deleted anyways
		$values = zz_values_get_fields($my_tab['values'], $rec_tpl['fields']);
		// look for matches between values and existing records
		list($records, $existing_ids, $existing, $values) 
			= zz_subrecord_values_existing($values, $existing);
	} elseif ($my_tab['hierarchy']) {
		list($my_lines, $total_rows) = zz_hierarchy($my_tab['sql'], $my_tab['hierarchy']);
		$values = array();
		$existing_ids = array();
		$ids = array();
		foreach ($my_lines as $line) {
			$ids[] = $line[$my_tab['hierarchy']['id_field_name']];
		}
		$sql = $my_tab['sql'];
		$sql = wrap_edit_sql($sql, 'WHERE', $my_tab['hierarchy']['id_field_name']
			.' IN ('.implode(',', $ids).')');
		$sql = wrap_edit_sql($sql, 'WHERE', $main_tab[0]['id']['field_name']
			.' = '.$main_tab[0]['id']['value'].' OR ISNULL('.$main_tab[0]['id']['field_name'].')');
		$records = zz_db_fetch($sql, $my_tab['hierarchy']['id_field_name']);
		$existing = array();
		foreach ($ids as $id) {
			// sort, could probably done easier by one of PHPs sort functions
			$existing[$id] = $records[$id];
		}
		$records = $existing = array_values($existing);
		foreach ($existing as $record) {
			$existing_ids[] = $record[$my_tab['id_field_name']];
		}
	} else {
		$values = array();
		// saved ids separately for later use
		$existing_ids = array_keys($existing);
		// save existing records without IDs as key but numeric
		$existing = $records = array_values($existing);
	}
	
	$start_new_recs = count($records);
	if ($my_tab['max_records'] < $start_new_recs) $start_new_recs = -1;

	// now go into each individual subrecord
	// assign POST array, first existing records, then new records,
	// ignore illegally sent records
	$post = array();
	
	foreach ($my_tab['POST'] as $rec => $posted) {
		if (!empty($posted[$my_tab['id_field_name']])) {
			// this will only occur if main record is updated or deleted!
			// check if posted ID is in existing IDs
			$key = array_search($posted[$my_tab['id_field_name']], $existing_ids);
			if ($key === false) {
				// illegal ID, this will only occur if user manipulated the form
				$zz_error[] = array(
					'msg_dev' => 'Detail record with invalid ID was posted (ID was said to be %s, main record was ID %s)',
					'msg_dev_args' => array($posted[$my_tab['id_field_name']], $zz_var['id']['value']),
					'level' => E_USER_NOTICE
				);
				unset($my_tab['POST'][$rec]);
				continue;
			}
		} elseif (in_array($state, array('add', 'edit')) 
			AND $rec_tpl['access'] !== 'show' AND $values 
			AND false !== $my_key = zz_values_get_equal_key($values, $my_tab['POST'][$rec])) {
			$key = $my_key;
		} elseif (in_array($state, array('add', 'edit')) 
			AND $rec_tpl['access'] !== 'show'
			AND $start_new_recs >= 0) {
			// this is a new record, append it
			$key = $start_new_recs;
			$existing[$key] = array(); // no existing record exists
			// get source_value key
			if ($mode === 'add' AND !empty($main_tab[0]['id']['source_value'])) {
				$my_tab['source_values'][$key] = $source_values[$rec];
			}
			$start_new_recs++;
			if ($my_tab['max_records'] < $start_new_recs) $start_new_recs = -1;
		} else {
			// this is not allowed (wrong state or access: show, 
			// too many detail records)
			unset($my_tab['POST'][$rec]);
			continue;
		}
		$post[$key] = $my_tab['POST'][$rec];
		$records[$key] = $my_tab['POST'][$rec];
		unset($my_tab['POST'][$rec]);
	}
	$my_tab['POST'] = $post;
	unset($post);
	
	// get all keys (some may only be in existing, some only in POST (new ones))
	$my_tab['records'] = count($records);

	foreach (array_keys($records) AS $rec) {
		if (empty($my_tab['POST'][$rec]) AND !empty($existing[$rec])) {
			$my_tab['POST'][$rec] = $existing[$rec];
		} elseif (empty($my_tab['POST'][$rec]) AND !empty($records[$rec])) {
			$my_tab['POST'][$rec] = $records[$rec];
		}
		// set values, defaults if forgotten or overwritten
		$my_tab['POST'][$rec] = zz_check_def_vals(
			$my_tab['POST'][$rec], $field['fields'], $existing[$rec],
			(!empty($zz_var['where'][$my_tab['table_name']]) 
				? $zz_var['where'][$my_tab['table_name']] 
				: array()
			)
		);
	}

	// first check for review or access, 
	// first if must be here because access might override mode here!
	if (in_array($state, array('add', 'edit')) AND $rec_tpl['access'] !== 'show') {
		// check if user wants one record more (subtable_remove was already
		// checked beforehands)
		if ($my_tab['subtable_add']) {
			$my_tab['subtable_add'] = array();
			$my_tab['subtable_focus'] = $my_tab['records'];
			$my_tab['records']++;
			$tempvar = array_keys($records);
			$records[] = end($tempvar)+1;
		}
		if ($my_tab['records'] < $my_tab['min_records']) 
			$my_tab['records'] = $my_tab['min_records'];
		// always show one record minimum
		if ($zz_conf['always_show_empty_detail_record'])
			if (!$my_tab['records']) $my_tab['records'] = 1;
	}

	// check records against database, if we have values, check number of records
	if ($mode) {
		$my_tab = zz_get_subrecords_mode(
			$my_tab, $rec_tpl, $zz_var, $existing_ids
		);
	} elseif ($zz_var['action'] AND !empty($my_tab['POST'])) {
		// individual definition
		foreach (array_keys($records) as $rec) {
			$my_tab[$rec] = $rec_tpl;
			$my_tab[$rec]['save_record']
				= isset($my_tab['zz_save_record'][$rec])
				? $my_tab['zz_save_record'][$rec]
				: '';
			$my_tab[$rec]['id']['value'] 
				= isset($my_tab['POST'][$rec][$rec_tpl['id']['field_name']])
				? $my_tab['POST'][$rec][$rec_tpl['id']['field_name']]
				: '';
			// set values, rewrite POST-Array
			$my_tab = zz_set_values($my_tab, $rec, $zz_var);
		}
	}
	if ($my_tab['hierarchy']) {
		foreach ($my_lines as $line) {
			foreach ($my_tab as $rec => $my_rec) {
				if (!is_numeric($rec)) continue;
				// != because POST is string
				if (!empty($my_rec['POST'])) {
					$id_field_name = $my_rec['POST'][$my_tab['hierarchy']['id_field_name']];
				} elseif (!empty($my_tab['POST'][$rec])) {
					$id_field_name = $my_tab['POST'][$rec][$my_tab['hierarchy']['id_field_name']];
				} else {
					$id_field_name = '';
				}
				if ($id_field_name!= $line[$my_tab['hierarchy']['id_field_name']]) {
					continue;
				}
				foreach ($my_rec['fields'] as $index => $field) {
					if ($field['field_name'] !== $my_tab['hierarchy']['display_in']) continue;
					if (empty($my_rec['fields'][$index]['class']))	
						$my_tab[$rec]['fields'][$index]['class'] = '';
					else
						$my_tab[$rec]['fields'][$index]['class'] .= ' ';
					$my_tab[$rec]['fields'][$index]['class'] .= 'level'.(!empty($line['zz_level']) ? $line['zz_level'] : '0');
				}
			}
		}
	}

	// get all IDs from detail records when record was first sent to user
	// and add IDs which where added from different user in the meantime as well
	// so as to be able to remove these again
	foreach (array_keys($records) as $rec) {
		if (empty($my_tab[$rec]['id']['value'])) continue;
		if (in_array($my_tab[$rec]['id']['value'], $my_tab['subtable_ids'])) continue;
		$my_tab['subtable_ids'][] = $my_tab[$rec]['id']['value'];
	}

	// put $existing into $my_tab
	foreach ($existing as $index => $existing_rec) {
		$my_tab[$index]['existing'] = $existing_rec;
	}

	return $my_tab;
}

/**
 * gets ID of subrecord if one of the fields in the subrecord definition
 * is defined as unique (only for multi-operations)
 * 
 * @param array $my_tab = $zz_tab[$tab]
 * @param array $existing
 * @param array $fields = $zz_tab[$tab]['fields'] for a subtable
 * @global array $zz_error
 * @return array $my_tab['POST']
 */
function zz_subrecord_unique($my_tab, $existing, $fields) {
	global $zz_error;
	// check if a GET is set on the foreign key
	$foreign_key = $my_tab['foreign_key_field_name'];
	if ($pos = strrpos($foreign_key, '.')) {
		$foreign_key = substr($foreign_key, $pos + 1);
	}
	if (!empty($_GET['where'][$foreign_key])) {
		$my_tab['sql'] = wrap_edit_sql($my_tab['sql'], 
			'WHERE', $foreign_key.' = '.intval($_GET['where'][$foreign_key]));
	}
	$id_field = array('field_name' => $my_tab['id_field_name'], 'value' => '');
	if (!empty($my_tab['unique'])) {
		// this is only important for UPDATEs of the main record
		// @todo merge with code for 'unique' on a field level

		foreach ($my_tab['unique'] AS $unique) {
			if (empty($existing)) continue;
			// check if there's a foreign key and remove it from unique key
			foreach ($fields as $field) {
				if ($field['type'] !== 'foreign_key') continue;
				$key = array_search($field['field_name'], $unique);
				if ($key === false) continue;
				unset($unique[$key]);
			}
			$values = array();
			foreach ($my_tab['POST'] as $no => $record) {
				foreach ($unique as $field_name) {
					if (!isset($record[$field_name])) {
						$zz_error[] = array(
							'msg_dev' => 'UNIQUE was set but field %s is not in POST',
							'msg_dev_args' => array($field_name)
						);
						continue;
					}
					$values[$field_name] = $record[$field_name];
					// check if we have to get the corresponding ID for a string
					if (intval($values[$field_name]).'' === $values[$field_name].'') continue;
					foreach ($fields as $field) {
						if ($field['field_name'] !== $field_name) continue;
						if ($field['type'] !== 'select') break;
						if (empty($field['sql'])) break;

						$check = true;
						$long_field_name = $my_tab['table'].'[]['.$field_name.']';
						$db_table = $my_tab['db_name'].'.'.$my_tab['table'];
						if (isset($_POST['zz_check_select'])) {
							// ... unless explicitly said not to check
							if (in_array($field_name, $_POST['zz_check_select']))
								$check = false;
							elseif (in_array($long_field_name, $_POST['zz_check_select']))
								$check = false;
						}
						if (!$check) break;
						
						$my_id_field = $id_field;
						if (array_key_exists('field_name', $id_field) AND isset($record[$id_field['field_name']])) {
							$my_id_field['value'] = $record[$id_field['field_name']];
						} else {
							$my_id_field['value'] = '';
						}
						$field = zz_check_select_id($field, $values[$field_name].' ', $db_table, $my_id_field);
						if (count($field['possible_values']) !== 1) continue;
						$values[$field_name] = reset($field['possible_values']);
					}
				}
				foreach ($existing as $id => $record_in_db) {
					$found = true;
					foreach ($values as $field_name => $value) {
						if ($record_in_db[$field_name] != $value) $found = false;
					}
					if ($found) {
						$my_tab['POST'][$no][$my_tab['id_field_name']] = $id;
					}
				}
			}
		}
	}
	foreach ($fields as $f => $field) {
		if (empty($field['unique'])) continue;
		// look at fields that have to be unique, get id_field_value if
		// record with a value like this exists
		foreach ($my_tab['POST'] as $no => $record) {
			if (empty($record[$field['field_name']])) continue;
			if (!empty($record[$my_tab['id_field_name']])) continue;
			if ($field['type'] === 'select') {
				$db_table = $my_tab['db_name'].'.'.$my_tab['table'];
				$my_id_field = $id_field;
				$my_id_field['value'] = isset($record[$id_field['field_name']]) ? $record[$id_field['field_name']] : '';
				$field = zz_check_select_id(
					$field, $record[$field['field_name']], $db_table, $my_id_field
				);
				if (count($field['possible_values']) === 1) {
					$value = reset($field['possible_values']);
				} elseif (count($field['possible_values']) === 0) {
					$value = '';
				} else {
					$value = '';
					$zz_error[] = array(
						'msg_dev' => 'Field marked as unique, but could not find corresponding value: %s',
						'msg_dev_args' => array($field['field_name']),
						'level' => E_USER_NOTICE
					);
				}
				// we are not writing this value back to POST here
				// because there's no way telling the script that this
				// value was already replaced
				// AND: we do not generate error messages here.
				// $my_tab['POST'][$no][$field['field_name']] = $value;
			} else {
				$value = $record[$field['field_name']];
			}
			$sql = wrap_edit_sql(
				$my_tab['sql'], 'WHERE', $field['field_name'].' = '.$value
			);
			$existing_recs = zz_db_fetch($sql, $my_tab['id_field_name']);
			if (count($existing_recs) === 1) {
				$my_tab['POST'][$no][$my_tab['id_field_name']] = key($existing_recs); 
			} elseif (count($existing_recs)) {
				$zz_error[] = array(
					'msg_dev' => 'Field marked as unique, but value appears more than once in record: %s (SQL %s)',
					'msg_dev_args' => array($value, $sql),
					'level' => E_USER_NOTICE
				);
			}
		}
	}
	return $my_tab['POST'];
}

/**
 * checks which existing records match value records and reorders array of
 * existing records correspondingly; sets $records as a set of all detail
 * records that have to be thought about
 *
 * @param array $values
 * @param array $existing
 * @return array (everything indexed by $records)
 *		array $records (combination of value-records and existing records)
 *		array $existing_ids (array of existing record ids)
 *		array $existing (array of existing records)
 *		array $values (remaining values which have no corresponding existing
 *			record)
 */
function zz_subrecord_values_existing($values, $existing) {
	$my_existing = $existing; // save for later use
	$order = array();
	$records = $values;
	
	// look for corresponding existing records for values
	// set correct position in array
	foreach ($my_existing as $id => $record) {
		$key = zz_values_get_equal_key($values, $record);
		if ($key !== false) {
			// save order to reorder the existing records later
			$order[$key] = $id;
			$records[$key] = $my_existing[$id];
			unset($my_existing[$id]);
		}
	}
	$next_key = count($records);

	// if there are more existing records, append them
	if (!empty($my_existing)) {
		foreach ($my_existing as $id => $fields) {
			$order[$next_key] = $id;
			$next_key++;
		}
		$records = array_merge($records, $my_existing);
	}

	$my_existing = $existing;
	unset($existing);
	// initialize array
	foreach (array_keys($records) as $index)
		$existing[$index] = array();
	// fill array with values
	foreach ($order as $index => $id) {
		$existing[$index] = $my_existing[$id];
	}
	$existing_ids = $order;
	ksort($existing_ids);

	return array($records, $existing_ids, $existing, $values);
}

/**
 * reformats 'values'-array: field names instead of field ids
 *
 * @param array $values ($zz_tab[tab]['values'])
 *		e. g. $zz_tab[tab]['values'][1][12] = 23
 * @param array $fields ($zz_tab[tab]['fields'])
 * @return array $values, reformatted
 *		e. g. $zz_tab[tab]['values'][1]['some_id'] = 23
 */
function zz_values_get_fields($values, $fields) {
	$my_values = array();
	foreach ($values as $index => $line) {
		foreach ($line as $f => $value) {
			$my_values[$index][$fields[$f]['field_name']] = $value;
		}
	}
	return $my_values;
}

/**
 * checks $zz_tab[$tab]['values'] with $record if there are equal values
 *
 * @param array $values
 * @param array $record
 * @return int $key
 */
function zz_values_get_equal_key(&$values, $record) {
	foreach ($values as $key => $line) {
		$equal = false;
		foreach ($line as $field_name => $value) {
			if (isset($record[$field_name]) 
				AND $record[$field_name] == $value)
				$equal = true;
			else {
				$equal = false;
				break;
			}
		}
		if ($equal) {
			unset($values[$key]);
			return $key;
		}
	}
	return false;
}

/**
 * sets records in form, also depending on values and fielddefs
 *
 * @param array $my_tab = $zz_tab[$tab]
 * @param array $rec_tpl
 * @param array $zz_var
 * @param array $existing_ids
 * @return array $my_tab
 */
function zz_get_subrecords_mode($my_tab, $rec_tpl, $zz_var, $existing_ids) {
	global $zz_conf;
	// function will be run twice from zzform(), therefore be careful, 
	// programmer!

	for ($rec = 0; $rec < $my_tab['records']; $rec++) {
		// do not change other values if they are already there 
		// (important for error messages etc.)
		$continue_fast = (isset($my_tab[$rec]) ? true: false);
		if (!$continue_fast) // reset fields only if necessary
			$my_tab[$rec] = $rec_tpl;
		$my_tab = zz_set_values($my_tab, $rec, $zz_var);
		// ok, after we got the values, continue, rest already exists.
		if ($continue_fast) continue;

		if (isset($existing_ids[$rec])) $idval = $existing_ids[$rec];
		else $idval = false;
		$my_tab[$rec]['id']['value'] = $idval;
		if (!empty($my_tab['source_values'][$rec]))
			$my_tab[$rec]['id']['source_value'] = $my_tab['source_values'][$rec];
		$my_tab[$rec]['save_record'] = isset($my_tab['zz_save_record'][$rec])
			? $my_tab['zz_save_record'][$rec] : '';

		$my_tab[$rec]['POST'] = '';
		if ($my_tab['POST']) {
			foreach ($my_tab['POST'] as $key => $my_rec) {
				if ($idval) {
					if (!isset($my_rec[$rec_tpl['id']['field_name']])) continue;
					if ($my_rec[$rec_tpl['id']['field_name']] != $idval) continue;
					$my_tab[$rec]['POST'] = $my_rec;
					unset($my_tab['POST'][$key]);
				} else {
					if (!empty($my_rec[$rec_tpl['id']['field_name']])) continue;
					if ($my_tab[$rec]['POST']) continue;
					// find first value pair that matches and put it into POST
					$my_tab[$rec]['POST'] = $my_rec;
					unset($my_tab['POST'][$key]);
				}
			}
		}
	}
	// array_keys(array_flip()) is reported to be faster than array_unique()
	// remove double entries
	$my_tab['subtable_deleted'] = array_keys(
		array_flip($my_tab['subtable_deleted'])
	);
	if (!empty($my_tab['values'])) unset($my_tab['values']);
	// we need these two arrays in correct order (0, 1, 2, ...) to display the
	// subtables correctly when requeried
	ksort($my_tab);
	unset($my_tab['zz_save_record']); // not needed anymore
	return $my_tab;
}

/**
 * copys values from fielddefs-Array to fields where appropriate
 *
 * @param array $fielddefs = $zz_tab[$tab]['fielddefs']
 * @param array $fields = $zz_tab[$tab][$rec]['fields']
 * @param int $rec number of detail record
 * @return array $fields
 */
function zz_set_fielddefs(&$fielddefs, $fields, $rec) {
	if (!array_key_exists($rec, $fielddefs)) return $fields;
	$my_field_def = $fielddefs[$rec];
	unset($fielddefs[$rec]);
	foreach ($my_field_def as $f => $field) {
		if (!$field) {
			unset($fields[$f]);
		} else {
			$fields[$f] = array_merge($fields[$f], $my_field_def[$f]);
		}
	}
	return $fields;
}

/**
 * sets values from 'values' to current $my_rec-Array
 *
 * @param array $my_tab
 * @param int $rec
 * @param array $zz_var
 * @return array $my_tab
 */
function zz_set_values($my_tab, $rec, $zz_var) {
	// isset because might be empty
	if (!isset($my_tab['values'])) return $my_tab;
	
	$my_values = array_shift($my_tab['values']);
	$table = $my_tab['table_name'];
	foreach ($my_tab[$rec]['fields'] AS $f => &$field) {
		if (!empty($my_values[$f])) {
			if ($field['type'] !== 'hidden')
				$field['type_detail'] = $field['type'];
			$field['type'] = 'hidden';
			$field['value'] = $my_values[$f];
		}
	}
	// we have new values, so check whether these are set!
	// it's not possible to do this beforehands!
	if (!empty($my_tab['POST'][$rec])) {
		$my_tab['POST'][$rec] = zz_check_def_vals(
			$my_tab['POST'][$rec], $my_tab[$rec]['fields'], array(),
			(!empty($zz_var['where'][$table]) ? $zz_var['where'][$table] : array())
		);
	}
	if (!empty($my_tab['fielddefs'])) {
		$my_tab[$rec]['fields'] = zz_set_fielddefs(
			$my_tab['fielddefs'], $my_tab[$rec]['fields'], $rec
		);
	}
	return $my_tab;
}

/** 
 * Protection against overwritten values, set values and defaults for 
 * zzform_multi()
 * Writes values, default values and where-values into POST-Array
 * initializes unset field names
 * 
 * @param array $post		POST records of main table or subtable
 * @param array $fields		$zz ...['fields']-definitions of main or subtable
 * @param array $existing values of existing record in case record is not set
 * @param array $where
 * @return array $post		POST
 */
function zz_check_def_vals($post, $fields, $existing = array(), $where = array()) {
	foreach ($fields as $field) {
		if (empty($field['field_name'])) continue;
		$field_name = $field['field_name'];
		// don't overwrite write_once with value if a value exists
		if ($field['type'] === 'write_once' AND !empty($existing[$field_name])) continue;
		// for all values, overwrite posted values with needed values
		if (!empty($field['value'])) 
			$post[$field_name] = $field['value'];
		// just for values which are not set (!) set existing value (on update)
		// if there is one
		// (not for empty strings!)
		if (!empty($existing[$field_name]) AND !isset($post[$field_name]))
			$post[$field_name] = $existing[$field_name];
		// just for values which are not set (!) set default value
		// (not for empty strings!, not for update)
		if (!empty($field['default']) AND !isset($post[$field_name]))
			$post[$field_name] = $field['default'];
		// most important, therefore last: [where]
		if (!empty($where[$field_name]))
			$post[$field_name] = $where[$field_name];
		// if it's a mass upload or someone cuts out field_names, 
		// treat these fields as if nothing was posted
		// some fields must not be initialized, so ignore them
		$unwanted_field_types = array(
			'id', 'foreign_key', 'translation_key', 'display'
		);
		if (!isset($post[$field_name])
			AND !in_array($field['type'], $unwanted_field_types))
			$post[$field_name] = '';
	}
	return $post;
}

/** 
 * query record 
 * 
 * if everything was successful, query record (except in case it was deleted)
 * if not, write POST values back into form
 *
 * @param array $my_tab complete zz_tab[$tab] array
 * @param int $rec Number of detail record
 * @param bool $validation true/false
 * @param string $mode ($ops['mode'])
 * @return array $zz_tab[$tab]
 *		might unset $zz_tab[$tab][$rec]
 *		$zz_tab[$tab][$rec]['record'], $zz_tab[$tab][$rec]['record_saved'], 
 *		$zz_tab[$tab][$rec]['fields'], $zz_tab[$tab][$rec]['action']
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_query_record($my_tab, $rec, $validation, $mode) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$my_rec = &$my_tab[$rec];
	$table = $my_tab['table'];
	// detail records don't have 'extra'
	if (!isset($my_tab['sqlextra'])) $my_tab['sqlextra'] = array();

	// in case, record was deleted, query record is not necessary
	if ($my_rec['action'] === 'delete') {
		unset($my_rec);
		return zz_return($my_tab);
	}
	// in case validation was passed or access is 'show'
	// everything's okay.
	if ($validation OR $my_rec['access'] === 'show') {
		// initialize 'record'
		$my_rec['record'] = array();
		// check whether record already exists (this is of course impossible 
		// for adding a record!)
		if ($mode !== 'add' OR $my_rec['action']) {
			if ($my_rec['id']['value']) {
				$my_rec['record'] = zz_query_single_record(
					$my_tab['sql'], $table, $my_rec['id'], $my_tab['sqlextra'], $my_tab['sql_translate']
				);
			} elseif (!empty($my_rec['id']['values'])) {
				$my_rec['record'] = zz_query_multiple_records(
					$my_tab['sql'], $table, $my_rec['id']
				);
				// @todo: think about sqlextra
			} elseif ($my_rec['access'] === 'show' AND !empty($my_rec['POST'])) {
				$my_rec['record'] = $my_rec['POST'];
			}
		} elseif ($mode === 'add' AND !empty($zz_conf['int']['add_details_return'])) {
			if (!empty($my_rec['POST'])) {
				$my_rec['record'] = $my_rec['POST'];
			}
		} elseif ($mode === 'add' AND !empty($my_rec['id']['source_value'])) {
			if (!empty($my_rec['POST'])) {
				// no need to requery, we already did query a fresh record
				// as a template
				$my_rec['record'] = $my_rec['POST'];
			} else {
				$sql = $my_tab['add_from_source_id'] ? $my_tab['sql_without_where'] : $my_tab['sql'];
				$my_rec['record'] = zz_query_single_record(
					$sql, $table, $my_rec['id'], $my_tab['sqlextra'], $my_tab['sql_translate'], 'source_value'
				);
				if (empty($my_rec['record'])) {
					$my_tab['id']['source_value'] = false;
					// source record does not exist
				} else {
					$my_rec['record'][$my_rec['id']['field_name']] = false;
				}
			}
			// remove some values which cannot be copied
			$my_rec['record'] = zz_prepare_clean_copy($my_rec['fields'], $my_rec['record']);
		}
	// record has to be passed back to user
	} else {
		if (isset($my_rec['POST-notvalid'])) {
			$my_rec['record'] = $my_rec['POST-notvalid'];
		} elseif (isset($my_rec['POST'])) {
			$my_rec['record'] = $my_rec['POST'];
		} else {
			$my_rec['record'] = array();
		}
		
	//	get record for display fields and maybe others
		$my_rec['record_saved'] = zz_query_single_record(
			$my_tab['sql'], $table, $my_rec['id'], $my_tab['sqlextra'], $my_tab['sql_translate']
		);

	//	display form again			
		$my_rec['action'] = 'review';

	//	print out all records which were wrong, set class to error
		foreach ($my_rec['fields'] as $no => $field) {
			// just look for check_validation set but false
			if (!isset($field['check_validation']) 
				OR $field['check_validation']) continue;
			// append error to 'class'
			if (isset($my_rec['fields'][$no]['class'])) {
				$my_rec['fields'][$no]['class'].= ' error';
			} else {
				$my_rec['fields'][$no]['class'] = 'error';
			}
		}
	}
	zz_log_validation_errors($my_rec, $validation);
	return zz_return($my_tab);
}

/**
 * remove some values which cannot be copied
 *
 * @param array $fields
 * @param array $record
 * @return array
 */
function zz_prepare_clean_copy($fields, $record) {
	foreach ($fields as $my_field) {
		if (!empty($my_field['dont_copy'])) {
			$record[$my_field['field_name']] = NULL;
			$defvals = zz_check_def_vals($record, array($my_field));
			if (!empty($defvals[$my_field['field_name']])) {
				$record[$my_field['field_name']] = $defvals[$my_field['field_name']];
			} else {
				$record[$my_field['field_name']] = '';
			}
			continue;
		}
		if (empty($my_field['type'])) continue;
		// identifier must be created from scratch
		switch ($my_field['type']) {
		case 'id':
		case 'identifier':
		case 'foreign_key':
			$record[$my_field['field_name']] = false;
			break;
		}
	}
	return $record;
}

/**
 * Log validation errors (incorrect or missing values)
 *
 * @param array $my_rec = $zz_tab[$tab][$rec]
 * @param bool $validation
 * @global array $zz_error
 * @return bool true: some errors were logged; false no errors were logged
 */
function zz_log_validation_errors($my_rec, $validation) {
	global $zz_error;
	if ($my_rec['action'] === 'delete') return false;
	if ($validation) return false;
	if ($my_rec['access'] === 'show') return false;
	
	foreach ($my_rec['fields'] as $no => $field) {
		if ($field['type'] === 'password_change') continue;
		if ($field['type'] === 'subtable') continue;
		if (!empty($field['mark_reselect'])) {
			// oh, it's a reselect, add some validation message
			$zz_error['validation']['reselect'][] = sprintf(
				zz_text('Please select one of the values for field %s'),
				'<strong>'.$field['title'].'</strong>'
			);
			continue;
		}
		// just look for check_validation set but false
		if (!isset($field['check_validation'])) continue;
		if ($field['check_validation']) continue;
		if ($my_rec['record'][$field['field_name']]) {
			// there's a value, so this is an incorrect value
			if (!empty($field['error_msg'])) {
				$error = $field['error_msg'];
			} else {
				$error = sprintf(
					zz_text('Value incorrect in field <strong>%s</strong>.'), 
					$field['title']
				);
			}
			$zz_error['validation']['msg'][] = $error.(
				!empty($field['validation_error'])
				? ' ('.$field['validation_error'].')'
				: ''
			);
			$zz_error['validation']['incorrect_values'][] = array(
				'field_name' => $field['field_name'],
				'msg' => sprintf(zz_text('Incorrect value: %s'), 
					is_array($my_rec['record'][$field['field_name']])
					? json_encode($my_rec['record'][$field['field_name']])
					: $my_rec['record'][$field['field_name']])
			);
			$zz_error['validation']['log_post_data'] = true;
		} elseif (empty($field['dont_show_missing'])) {
			if ($field['type'] === 'upload_image') {
				$zz_error['validation']['msg'][] = sprintf(
					zz_text('Nothing was uploaded in field <strong>%s</strong>.'),
					$field['title']
				).(!empty($my_rec['images'][$no][0]['upload']['msg'])
					? ' '.$my_rec['images'][$no][0]['upload']['msg'] : '');
			} else {
				// there's a value missing
				$zz_error['validation']['msg'][] = sprintf(
					zz_text('Value missing in field <strong>%s</strong>.'),
					$field['title']
				);
				$zz_error['validation']['log_post_data'] = true;
			}
		}
	}
	return true;
}

/**
 * Query single record
 *
 * @param string $sql $zz_tab[tab]['sql']
 * @param string $table $zz['table']
 * @param array $id	$zz_var['id']
 * @param array $sqlextra $zz['sqlextra']
 * @param array $sql_translate $zz['sql_translate']
 * @param string $type
 * @return array
 */
function zz_query_single_record($sql, $table, $id, $sqlextra, $sql_translate, $type = 'value') {		
	global $zz_error;
	
	if (!$id[$type]) return array();
	$sql = wrap_edit_sql($sql,
		'WHERE', sprintf('%s.%s = %d', $table, $id['field_name'], $id[$type])
	);
	$sql = wrap_edit_sql($sql, 'FORCE INDEX', ' ', 'delete');
	$record = zz_db_fetch($sql, '', '', 'record exists? ('.$type.')');
	// if record is not yet in database, we will not get extra data because
	// no ID exists yet
	if (!$record) return array();
	$record = zz_translate(array('sql_translate' => $sql_translate), $record);
	foreach ($sqlextra as $sql) {
		if (empty($id[$type])) {
			$zz_error[] = array(
				'msg_dev' => 'No ID %s found (Query: %s).',
				'msg_dev_args' => array($type, $sql)
			);
			continue;
		}
		$sql = sprintf($sql, $id[$type]);
		$record = array_merge($record, zz_db_fetch($sql));
	}
	return $record;
}

/**
 * Query multiple records, return identical values in all records
 *
 * @param string $sql
 * @param string $table
 * @param array $id
 * @return array
 */
function zz_query_multiple_records($sql, $table, $id) {
	$sql = wrap_edit_sql($sql, 'WHERE', '%s.%s IN ("%s")',
		$table, $id['field_name'], implode('","', $id['values'])
	);
	$records = wrap_db_fetch($sql, $id['field_name'], '', 'multiple records exist?');
	// use first record as basis for checking identical values
	$existing = array_shift($records);
	foreach ($records as $record) {
		foreach ($record as $field_name => $field_value) {
			if ($existing[$field_name] !== $field_value) {
				$existing[$field_name] = '';
			}
		}
	}
	return $existing;
}

/** 
 * query a detail record
 * 
 * @param array $my_tab = $zz_tab[$tab] = where $tab is the detail record to query
 * @param string $zz_tab[0]['table'] = main table name
 * @param int $zz_tab[0][0]['id']['value'] = main id value	
 * @param string $id_field_name = ID field name of detail record
 * @param array $deleted_ids = IDs that were deleted by user
 * @global array $zz_conf
 * @return array $records, indexed by ID
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_query_subrecord($my_tab, $main_table, $main_id_value,
	$id_field_name, $deleted_ids = array()) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	
	if ($my_tab['sql_not_unique']) {
		if (substr(trim($my_tab['sql_not_unique']), 0, 9) === 'LEFT JOIN') {
			$sql = wrap_edit_sql(
				$my_tab['sql'], 'JOIN', $my_tab['sql_not_unique']
			);
		} else {
			// quick and dirty version
			$sql = $my_tab['sql'].' '.$my_tab['sql_not_unique'];
		}
	} else {
		$sql = $my_tab['sql'];
	}
	if (!empty($my_tab['translate_field_name'])) {
		// translation subtable
		$sql = wrap_edit_sql($sql, 'WHERE', 
			$zz_conf['translations_table'].'.db_name = "'.$zz_conf['db_name'].'"
			AND '.$zz_conf['translations_table'].'.table_name = "'.$main_table.'"
			AND '.$zz_conf['translations_table'].'.field_name = "'
				.$my_tab['translate_field_name'].'"');
	}
	$sql = wrap_edit_sql(
		$sql, 'WHERE', sprintf('%s = %d', $my_tab['foreign_key_field_name'], $main_id_value)
	);

	$records = zz_db_fetch($sql, $id_field_name, '', '', E_USER_WARNING);
	foreach ($records as $id => $line) {
		if (!in_array($line[$id_field_name], $deleted_ids)) continue;
		// get rid of deleted records
		unset($records[$id]);
	}
	return zz_return($records);
}
