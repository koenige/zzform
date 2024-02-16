<?php 

/**
 * zzform
 * Preparation functions for record- and action-Modules
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Prepares table definitions, database content and posted data
 *  for 'action' and 'record'-modules
 *
 * @param array $zz
 * @param string $mode ($ops['mode'])
 * @return array $zz_tab
 */
function zz_prepare_tables($zz, $mode) {
	global $zz_conf;

	$zz_tab = [];
	// ### variables for main table will be saved in zz_tab[0]
	$zz_tab[0]['db_name'] = wrap_setting('db_name');
	$zz_tab[0]['table'] = $zz['table'];
	$zz_tab[0]['table_name'] = $zz['table'];
	$zz_tab[0]['sql'] = $zz['sqlrecord'];
	$zz_tab[0]['sql_without_where'] = $zz['sql_without_where'];
	$zz_tab[0]['sqlextra'] = $zz['sqlextra'];
	$zz_tab[0]['sql_translate'] = $zz['sql_translate'];
	$zz_tab[0]['folder'] = $zz['folder'];
	$zz_tab[0]['add_from_source_id'] = $zz['add_from_source_id'];
	$zz_tab[0]['filter'] = $zz['filter'];
	$zz_tab[0]['filter_active'] = $zz['filter_active'];
	$zz_tab[0]['dont_reformat'] = !empty($_POST['zz_subtables']) ? true : false;
	$zz_tab[0]['record_action'] = false;
	$zz_tab[0]['add_details_return_field'] = $zz['add_details_return_field'];
	$zz_tab[0]['where'] = $zz['record']['where'][$zz['table']] ?? [];
	$zz_tab[0]['unique_ignore_null'] = $zz['unique_ignore_null'];

	if (!empty($zz['set_redirect'])) {
		// update/insert redirects after_delete and after_update
		require_once __DIR__.'/identifier.inc.php';
		$zz_tab[0]['set_redirect'] = $zz['set_redirect'];
	}

	$zz_conf['int']['revisions_only'] = $zz['revisions_only'];
	if ($zz['revisions_only'] OR $zz['revisions']) {
		require_once __DIR__.'/revisions.inc.php';
	}
	if ($mode === 'revise') {
		require_once __DIR__.'/revisions.inc.php';
		$zz_tab[0]['revision_id'] = zz_revisions_read_id($zz_tab[0]['table']);
	} elseif (!empty($_POST['zz_revision_id'])) {
		require_once __DIR__.'/revisions.inc.php';
		$zz_tab[0]['revision_id'] = intval($_POST['zz_revision_id']);
	}
	if (!empty($zz_tab[0]['revision_id'])) $zz['revision_hooks'] = true;

	$zz_tab[0]['hooks'] = zz_prepare_hooks($zz);
	$zz_tab[0]['triggers'] = $zz['triggers'];
	
	$zz_tab[0][0]['action'] = $zz['record']['action'];
	$zz_tab[0][0]['fields'] = $zz['fields'];
	$zz_tab[0][0]['validation'] = true;
	$zz_tab[0][0]['record'] = [];
	$zz_tab[0][0]['access'] = $zz['access'];
	// get ID field, unique fields, check for unchangeable fields
	$zz_tab[0][0]['id'] = &$zz_conf['int']['id'];
	$zz_tab[0][0]['check_select_fields'] = zz_prepare_check_select();
	$zz_tab[0][0]['details'] = $zz['details'];
	$zz_tab[0][0]['if'] = $zz['if'];
	$zz_tab[0][0]['unless'] = $zz['unless'];

	if (!empty($zz_conf['int']['revisions_only']))
		$zz_conf['int']['revision_data'] = zz_revisions_tab($zz_tab[0]);
		
	//	### put each table (if more than one) into one array of its own ###
	$integrate_records = 0;
	foreach ($zz['record']['subtables'] as $tab => $no) {
		if (strstr($no, '-')) {
			$nos = explode('-', $no);
			$my_field = &$zz['fields'][$nos[0]]['fields'][$nos[1]];
			$main_tab = array_search($nos[0], $zz['record']['subtables']);
		} else {
			$my_field = &$zz['fields'][$no];
			$main_tab = 0;
		}
		if (!empty($my_field['hide_in_form'])) continue;
		if ($integrate_records) $my_field['integrate_records'] = $integrate_records;
		$zz_tab[$tab] = zz_get_subtable($my_field, $zz_tab[$main_tab], $tab, $no);
		$zz_tab[$tab]['where'] = $zz['record']['where'][$zz_tab[$tab]['table_name']] ?? [];
		if (in_array($mode, ['revise', 'show']) AND $zz_tab[$tab]['values']) {
			// don't show values which are not saved in show-record mode
			$zz_tab[$tab]['values'] = [];
		}
		if (zz_error_exit()) return [];
		$zz_tab[$tab] = zz_get_subrecords(
			$mode, $my_field, $zz_tab, $tab, $zz['record']
		);
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if (empty($zz_tab[$tab]['POST'][$rec])) continue;
			$zz_tab[$tab][$rec]['POST'] = $zz_tab[$tab]['POST'][$rec];
			$zz_tab[$tab][$rec]['check_select_fields'] = $zz_tab[$tab]['check_select_fields'][$rec] ?? [];
			unset($zz_tab[$tab]['POST'][$rec]);
		}
		if (zz_error_exit()) return [];
		if (isset($zz_tab[$tab]['subtable_focus'])) {
			// set autofocus on subrecord, not on main record
			$zz_tab[0]['subtable_focus'] = 'set';
		}
		$integrate_records = $zz_tab[$tab]['integrate_in_next'] ? $zz_tab[$tab]['records'] : 0;
	}

	if (!$zz['record']['query_records']) return $zz_tab;

	if (!empty($zz_conf['int']['id']['value'])) {
		$zz_tab[0][0]['existing'] = zz_query_single_record(
			$zz_tab[0]['sql'], $zz_tab[0]['table'], $zz_tab[0]['table'], $zz_conf['int']['id'], $zz_tab[0]['sqlextra']
		);
		$zz_tab[0][0]['existing'] = zz_prepare_record($zz_tab[0][0]['existing'], $zz_tab[0][0]['fields']);
		if ($zz['record']['action'] === 'update' AND !$zz_tab[0][0]['existing']) {
			zz_error_exit(true);
			$sql = wrap_edit_sql($zz_tab[0]['sql'],
				'WHERE', sprintf('%s.%s = %d',$zz_tab[0]['table'], $zz_conf['int']['id']['field_name'], $zz_conf['int']['id']['value'])
			);
			zz_error_log([
				'msg_dev' => 'Trying to update a non-existent record in table `%s` with ID %d.',
				'msg_dev_args' => [$zz_tab[0]['table'], $zz_conf['int']['id']['value']],
				'level' => E_USER_ERROR,
				'query' => $sql
			]);
			return false;
		}
	} elseif (!empty($zz_conf['int']['id']['values'])) {
		$sql = wrap_edit_sql($zz_tab[0]['sql'], 'WHERE', $zz_tab[0]['table'].'.'
			.$zz_conf['int']['id']['field_name']." IN ('".implode("','", $zz_conf['int']['id']['values'])."')");
		$existing = zz_db_fetch($sql, $zz_conf['int']['id']['field_name'], 'numeric');
		foreach ($existing as $index => $existing_rec) {
			$existing_rec = zz_prepare_record($existing_rec, $zz_tab[0][0]['fields']);
			$zz_tab[0][$index]['existing'] = $existing_rec;
		}
		// @todo think about sqlextra
	} else {
		$zz_tab[0][0]['existing'] = [];
	}

	// Upload
	// if there is a directory which has to be renamed, save old name in array
	// do the same if a file might be renamed, deleted ... via upload
	// or if there is a display or write_once field (so that it can be used
	// e. g. for identifiers):
	if ($zz['record']['action'] === 'update' OR $zz['record']['action'] === 'delete') {
		if (count($zz['record']['save_old_record']) && !empty($zz_tab[0][0]['existing'])) {
			foreach ($zz['record']['save_old_record'] as $no) {
				if (empty($zz_tab[0][0]['existing'][$zz['fields'][$no]['field_name']])) continue;
				$_POST[$zz['fields'][$no]['field_name']] 
					= $zz_tab[0][0]['existing'][$zz['fields'][$no]['field_name']];
			}
		}
	}

	// get rid of some POST values that are used at another place
	$zz_tab[0][0]['POST'] = [];
	foreach (array_keys($_POST) AS $key) {
		if (in_array($key, wrap_setting('zzform_internal_post_fields'))) continue;
		$zz_tab[0][0]['POST'][$key] = wrap_normalize($_POST[$key]);
	}
	//  POST is secured, now get rid of password fields in case of error_log_post
	foreach ($zz['fields'] AS $field) {
		if (empty($field['type'])) continue;
		if (!in_array($field['type'], ['password', 'password_change'])) continue;
		unset($_POST[$field['field_name']]);
	}

	// set defaults and values, clean up POST
	$zz_tab[0][0]['POST'] = zz_prepare_def_vals(
		$zz_tab[0][0]['POST'], $zz_tab[0][0]['fields'], $zz_tab[0][0]['existing']
		, $zz_tab[0]['where']
	);

	return $zz_tab;
}

/**
 * prepare hooks for database operations
 * hooks defined with the table plus zzform's own hooks
 * 
 * @param array $zz
 * @return array = $zz_tab[0]['hooks']
 * @todo don't prepare hooks in record mode (+ exclude from hash)
 */
function zz_prepare_hooks($zz) {
	$hooks = $zz['hooks'];

	// geocoding? sequence?
	$hook_found = [];
	foreach ($zz['fields'] as $field) {
		if (empty($field['type'])) continue;
		if (in_array($field['type'], ['subtable', 'foreign_table'])) {
			foreach ($field['fields'] as $subfield) {
				if (!empty($subfield['geocode'])) $hook_found['zz_geo_geocode'] = true;
				if (empty($subfield['type'])) continue;
				if ($subfield['type'] === 'sequence') $hook_found['zz_sequence_normalize'] = true;
			}
		} elseif ($field['type'] === 'sequence') {
			$hook_found['zz_sequence_normalize'] = true;
		} elseif (!empty($field['geocode'])) {
			$hook_found['zz_geo_geocode'] = true;
		}
		if (count($hook_found) === 2) break;
	}
	if (!empty($zz['set_redirect'])) $hook_found['zz_identifier_redirect'] = true;
	if (!empty($zz['revisions_only'])) $hook_found['zz_revisions'] = true;
	elseif (!empty($zz['revisions'])) $hook_found['zz_revisions'] = true;
	if (!empty($zz['revision_hooks'])) $hook_found['zz_revisions_historic'] = true;

	$types = [
		'zz_geo_geocode' => ['after_validation'],
		'zz_identifier_redirect' => ['after_delete', 'after_update'],
		'zz_revisions' => ['after_insert', 'after_update', 'after_delete'],
		'zz_revisions_historic' => ['after_delete', 'after_update'],
		'zz_sequence_normalize' => ['before_insert', 'before_update', 'after_delete']
	];
	$types = array_reverse($types); // array_unshift sorts arrays backwards
	foreach ($types as $type => $internal_hooks) {
		if (empty($hook_found[$type])) continue;
		foreach ($internal_hooks as $hook) {
			if (empty($hooks[$hook])) $hooks[$hook] = [];
			// process internal hooks first
			array_unshift($hooks[$hook], $type);
		}
	}

	return $hooks;
}

/**
 * creates array for each detail table in $zz_tab
 *
 * @param array $field = $zz['fields'][$no] with subtable definition
 * @param array $main_tab = $zz_tab[0] for main record
 * @param int $tab = number of subtable
 * @param int $no = number of subtable definition in $zz['fields']
 * @global array $_POST
 * @return array $my_tab = $zz_tab[$tab]
 *		'no', 'sql', 'sql_not_unique', 'keep_detailrecord_shown', 'db_name'
 *		'table', 'table_name', 'values', 'fielddefs', 'max_records', 
 *		'min_records', 'records_depend_on_upload', 
 *		'records_depend_on_upload_more_than_one', 'foreign_key_field_name',
 *		'translate_field_name_where', 'detail_key', 'tick_to_save', 'access'
 */
function zz_get_subtable($field, $main_tab, $tab, $no) {
	// basics for all subrecords of the same table
	$my_tab = [];

	// no in $zz['fields']
	$my_tab['no'] = $no;
	$my_tab['type'] = $field['type'];

	// SQL query
	$my_tab['sql'] = $field['sql'];
	if (empty($field['sql_not_unique'])) {
		$my_tab['sql_not_unique'] = false;
		$my_tab['keep_detailrecord_shown'] = false;
	} else {
		$my_tab['sql_not_unique'] = $field['sql_not_unique'];
		$my_tab['keep_detailrecord_shown'] = true;
	}
	$my_tab['sql_translate'] = $field['sql_translate'] ?? [];
	// Hierachy?
	$my_tab['hierarchy'] = $field['hierarchy'] ?? '';

	// database and table name
	$my_tab += zz_db_table($field['table'], $main_tab['db_name']);
	$my_tab['table_name'] = $field['table_name'];
	
	// pre-set values
	$my_tab['values'] = $field['values'] ?? [];
	$my_tab['fielddefs'] = $field['fielddefs'] ?? [];

	// records
	if (!empty($field['integrate_in_next'])) {
		// just display/edit existing records, do not allow here to add new detail records
		$my_tab['min_records_required'] = 0;
		$my_tab['min_records'] = 0;
		$my_tab['max_records'] = 0;
		$my_tab['integrate_in_next'] = true;
	} else {
		$my_tab['integrate_in_next'] = false;
		$settings = ['max', 'min'];
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
				$my_tab[$set.'_records'] = wrap_setting('zzform_'.$set.'_detail_records');
			}
		}
		$my_tab['min_records_required'] = isset($field['min_records_required'])
			? $field['min_records_required'] : 0;
		if ($my_tab['min_records'] < $my_tab['min_records_required'])
			$my_tab['min_records'] = $my_tab['min_records_required'];
		$my_tab['records_depend_on_upload'] = $field['records_depend_on_upload'] ?? false;
		$my_tab['records_depend_on_upload_more_than_one'] = $field['records_depend_on_upload_more_than_one'] ?? false;
		if (!empty($field['integrate_records']) AND $my_tab['max_records'])
			$my_tab['max_records'] -= $field['integrate_records'];
	}
	
	// foreign keys, translation keys, unique keys
	$my_tab['foreign_key_field_name'] = $field['foreign_key_field_name']
		?? $main_tab['table'].'.'.$main_tab[0]['id']['field_name'];
	$my_tab['translate_field_name_where'] = !empty($field['translate_field_name'])
		? (wrap_sql_table('default_translationfields').'.db_name = "'.wrap_setting('db_name').'"
			AND '.wrap_sql_table('default_translationfields').'.table_name = "'.$main_tab['table'].'"
			AND '.wrap_sql_table('default_translationfields').'.field_name = "'
				.$field['translate_field_name'].'"') : '';
	$my_tab['unique'] = $field['unique'] ?? false;

	// get detail key, if there is a field definition with it.
	// get id field name
	$password_fields = [];
	foreach ($field['fields'] AS $subfield) {
		if (!empty($subfield['get_post_value']) AND !empty($_POST[$subfield['get_post_value']])
			AND !empty($subfield['field_name'])) {
			// set POST values depending on another value
			$_POST[$my_tab['table_name']][][$subfield['field_name']] = $_POST[$subfield['get_post_value']];
		}
		if (!isset($subfield['type'])) continue;
		if ($subfield['type'] === 'password') {
			$password_fields[] = $subfield['field_name'];
		}
		if ($subfield['type'] === 'password_change') {
			$password_fields[] = $subfield['field_name'];
		}
		if ($subfield['type'] === 'id') {
			$my_tab['id_field_name'] = $subfield['field_name'];
			$my_tab['id']['field_name'] = $subfield['field_name'];
		}
		if ($subfield['type'] === 'foreign_key') {
			$my_tab['foreign_key'] = $subfield['field_name'];
		}
		if ($subfield['type'] !== 'detail_key') continue;
		if (empty($main_tab[0]['fields'][$subfield['detail_key']])) continue;
		$detail_key_index = isset($subfield['detail_key_index']) 
			? $subfield['detail_key_index'] : 0;
		$my_tab['detail_key'][] = [
			'tab' => $main_tab[0]['fields'][$subfield['detail_key']]['subtable'], 
			'rec' => $detail_key_index
		];
	}

	// tick to save
	$my_tab['tick_to_save'] = $field['tick_to_save'] ?? '';

	// access
	$my_tab['access'] = isset($field['access'])
		? $field['access'] : false;
	
	// POST array
	// buttons: add, remove subrecord
	$my_tab['subtable_deleted'] = [];
	$my_tab['subtable_ids'] = [];
	if (isset($_POST['zz_subtable_ids'][$my_tab['table_name']]))
		$my_tab['subtable_ids'] = explode(',', $_POST['zz_subtable_ids'][$my_tab['table_name']]);
	$my_tab['subtable_add'] = (!empty($_POST['zz_subtables']['add'][$tab]) 
		AND $my_tab['access'] !== 'show')
		? $_POST['zz_subtables']['add'][$tab] : false;
	$my_tab['subtable_remove'] = (!empty($_POST['zz_subtables']['remove'][$tab]) 
		AND $my_tab['access'] !== 'show')
		? $_POST['zz_subtables']['remove'][$tab] : [];

	// tick for save
	$my_tab['zz_save_record'] = $_POST['zz_save_record'][$tab] ?? [];

	$my_tab['POST'] = zz_prepare_post_per_table($my_tab['table_name']);
	foreach ($my_tab['POST'] as $rec => $fields) {
		foreach ($fields as $key => $value)
			$my_tab['POST'][$rec][$key] = wrap_normalize($value);
		if (!empty($my_tab['foreign_key']) AND empty($my_tab['POST'][$rec][$my_tab['foreign_key']])
			AND !empty($main_tab[0]['id']['value']))
			$my_tab['POST'][$rec][$my_tab['foreign_key']] = $main_tab[0]['id']['value'];
	}
	// POST is secured, now get rid of password fields in case of error_log_post
	foreach ($password_fields AS $password_field)
		unset($_POST[$my_tab['table_name']][$password_field]);

	// subtable_remove must meet min_records_required
	if ($my_tab['min_records_required']
		AND count($my_tab['POST']) <= $my_tab['min_records_required']) {
		$my_tab['subtable_remove'] = [];
	}

	// subtable_remove may come with ID
	foreach (array_keys($my_tab['subtable_remove']) as $rec) {
		if (empty($my_tab['subtable_remove'][$rec])) continue;
		if (!empty($my_tab['POST'][$rec][$my_tab['id_field_name']])) // has ID?
			$my_tab['subtable_deleted'][] = $my_tab['POST'][$rec][$my_tab['id_field_name']];
	}
	
	// dependent fields, only if there can be 1 subrecord
	if (!empty($field['dependent_fields']) AND $my_tab['min_records'] === 1
		AND $my_tab['max_records'] === 1) {
		$my_tab['dependent_fields'] = $field['dependent_fields'];
	}
	
	return $my_tab;
} 

/**
 * write POST data to 'POST' key per detail table
 *
 * also look for FILES array
 * @param string $table
 * @return array
 */
function zz_prepare_post_per_table($table) {
	$post = [];
	if (!empty($_FILES)) {
		$table_key = sprintf('field_%s_', $table);
		foreach (array_keys($_FILES) as $key) {
			if (!str_starts_with($key, $table_key)) continue;
			$key = substr($key, strlen($table_key));
			$key = explode('-', $key);
			$post[$key[0]] = [];
		}
	}
	if (empty($_POST)) return $post;
	if (empty($_POST[$table])) return $post;
	if (!is_array($_POST[$table])) return $post;
	
	foreach ($_POST[$table] as $index => $data)
		$post[$index] = $data;
	return $post;
}

/**
 * creates array for each detail record in $zz_tab[$tab]
 *
 * @param string $mode ($ops['mode'])
 * @param array $field
 * @param array $zz_tab
 * @param int $tab = tabindex
 * @param array $zz_record = $zz['record']
 * @return array $my_tab
 */
function zz_get_subrecords($mode, $field, $zz_tab, $tab, $zz_record) {
	global $zz_conf;
	
	$my_tab = $zz_tab[$tab];

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
	$rec_tpl = [];
	$rec_tpl['fields'] = $field['fields'];
	$rec_tpl['if'] = $field['if'] ?? [];
	$rec_tpl['unless'] = $field['unless'] ?? [];
	$rec_tpl['access'] = $my_tab['access'];
	$rec_tpl['id']['field_name'] = $my_tab['id_field_name'];
	$rec_tpl['validation'] = true;
	$rec_tpl['record'] = [];
	$rec_tpl['action'] = false;

	// get state
	if ($mode === 'add' OR $zz_record['action'] === 'insert')
		$state = 'add';
	elseif ($mode === 'edit' OR $mode === 'revise' OR $zz_record['action'] === 'update')
		$state = 'edit';
	elseif ($mode === 'delete' OR $zz_record['action'] === 'delete')
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
		$my_tab['subtable_remove'] = [];
	} else {
		// existing records might not be deleted in this mode!
		$my_tab['subtable_deleted'] = [];
	}

	// get detail records from database 
	// subtable_deleted is empty in case of 'action'
	// remove records which have been deleted by user interaction
	if (in_array($state, ['edit', 'delete', 'show'])) {
		// add: no record exists so far
		if (strstr($my_tab['no'], '-')) {
			$no = substr($my_tab['no'], 0, strpos($my_tab['no'], '-'));
			$id_tab = array_search($no, $zz_record['subtables']);
		} else {
			$id_tab = 0;
		}
		$existing = zz_query_subrecord(
			$my_tab, $zz_tab[$id_tab][0]['id']['value'],
			$rec_tpl['id']['field_name'], $my_tab['subtable_deleted']
		);
	} else {
		$existing = [];
	}
	if (zz_error_exit()) return $my_tab;
	// get detail records for source_id
	$source_values = [];
	if ($mode === 'add' AND !empty($zz_tab[0][0]['id']['source_value'])) {
		$my_tab['POST'] = zz_query_subrecord(
			$my_tab, $zz_tab[0][0]['id']['source_value'],
			$rec_tpl['id']['field_name'], $my_tab['subtable_deleted']
		);
		if (zz_error_exit()) return $my_tab;
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
		$values = [];
		$existing_ids = [];
		$ids = [];
		foreach ($my_lines as $line) {
			$ids[] = $line[$my_tab['hierarchy']['id_field_name']];
		}
		$sql = $my_tab['sql'];
		$sql = wrap_edit_sql($sql, 'WHERE', $my_tab['hierarchy']['id_field_name']
			.' IN ('.implode(',', $ids).')');
		$sql = wrap_edit_sql($sql, 'WHERE', $zz_tab[0][0]['id']['field_name']
			.' = '.$zz_tab[0][0]['id']['value'].' OR ISNULL('.$zz_tab[0][0]['id']['field_name'].')');
		$records = zz_db_fetch($sql, $my_tab['hierarchy']['id_field_name']);
		$existing = [];
		foreach ($ids as $id) {
			// sort, could probably done easier by one of PHPs sort functions
			$existing[$id] = $records[$id];
		}
		$records = $existing = array_values($existing);
		foreach ($existing as $record) {
			$existing_ids[] = $record[$my_tab['id_field_name']];
		}
	} else {
		$values = [];
		// saved ids separately for later use
		$existing_ids = array_keys($existing);
		// save existing records without IDs as key but numeric
		$existing = $records = array_values($existing);
	}
	
	zz_subrecords_post($my_tab, $records, $existing, $existing_ids, $mode, $state, $values, $source_values, $zz_tab[0][0]['id']);

	// first check for review or access, 
	// first if must be here because access might override mode here!
	$tempvar = false;
	if (in_array($state, ['add', 'edit']) AND $rec_tpl['access'] !== 'show') {
		// check if user wants one record more (subtable_remove was already
		// checked beforehands)
		if ($my_tab['subtable_add']) {
			$my_tab['subtable_add'] = [];
			$my_tab['subtable_focus'] = $my_tab['records'];
			$my_tab['records']++;
			$tempvar = array_keys($records);
			$tempvar = end($tempvar) + 1;
		}
		if ($my_tab['records'] < $my_tab['min_records']) 
			$my_tab['records'] = $my_tab['min_records'];
		// always show one record minimum
		if (!empty($zz_record['always_show_empty_detail_record']))
			if (!$my_tab['records']) $my_tab['records'] = 1;
	}
	
	// sequence? hidden if records = 1
	if ($my_tab['records'] === 1) {
		foreach ($field['fields'] as $no => $subfield) {
			if (!empty($subfield['type']) AND $subfield['type'] === 'sequence') {
				$field['fields'][$no]['type'] = 'hidden';
				$field['fields'][$no]['value'] = 1;
				$field['fields'][$no]['for_action_ignore'] = true;
				$field['fields'][$no]['hide_in_form'] = true;
				$rec_tpl['fields'][$no] = $field['fields'][$no];
			}
			if (!empty($subfield['if_single_record'])) {
				$field['fields'][$no] = array_merge($field['fields'][$no], $subfield['if_single_record']);
				$rec_tpl['fields'][$no] = $field['fields'][$no];
			}
		}
	}
	
	foreach (array_keys($records) AS $rec) {
		$existing[$rec] = zz_prepare_record($existing[$rec], $field['fields']);
		if (empty($my_tab['POST'][$rec]) AND !empty($existing[$rec])) {
			$my_tab['POST'][$rec] = $existing[$rec];
		} elseif (empty($my_tab['POST'][$rec]) AND !empty($records[$rec])) {
			$my_tab['POST'][$rec] = $records[$rec];
		}
		// set values, defaults if forgotten or overwritten
		$my_tab['POST'][$rec] = zz_prepare_def_vals(
			$my_tab['POST'][$rec], $field['fields'], $existing[$rec], $my_tab['where']
		);
	}
	if ($tempvar)
		$records[] = $tempvar;

	// check records against database, if we have values, check number of records
	if ($mode) {
		$my_tab = zz_get_subrecords_mode(
			$my_tab, $rec_tpl, $existing_ids
		);
	} elseif ($zz_record['action'] AND !empty($my_tab['POST'])) {
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
			$my_tab = zz_set_values($my_tab, $rec);
		}
	} elseif ($zz_record['action'] AND !empty($field['form_display']) AND $field['form_display'] === 'set') {
		// here, we might need an empty record if field is required (min_records_required = 1)
		$my_tab[0] = $rec_tpl;
		$my_tab[0]['save_record'] = '';
		$my_tab[0]['id']['value'] = '';
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
					$my_tab[$rec]['fields'][$index]['class'][] = 'level'.($line['zz_level'] ?? '0');
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
	if (!empty($my_tab['dependent_fields'])) {
		foreach ($my_tab['dependent_fields'] as $field_no => $dependent_field) {
			foreach ($my_tab[0]['fields'] as $my_field_no => $my_field) {
				if ($my_field['field_name'] !== $dependent_field['field_name']) continue;
				$my_tab[0]['fields'][$my_field_no]['dependent_fields'] = $my_tab['dependent_fields'];
			}
		}
	}

	return $my_tab;
}

/**
 * go into each individual subrecord
 * assign POST array, first existing records, then new records,
 * ignore illegally sent records
 *
 * @param array $my_tab definition of subtable
 * @param array $records
 * @param array $existing
 * @param array $existing_ids
 * @param string $mode
 * @param string $state
 * @param array $values
 * @param array $source_values
 * @param array $main_id = $zz_tab[0][0]['id']
 */
function zz_subrecords_post(&$my_tab, &$records, &$existing, $existing_ids, $mode, $state, $values, $source_values, $main_id) {
	global $zz_conf;

	$start_new_recs = count($records);
	if ($my_tab['max_records'] < $start_new_recs) $start_new_recs = -1;

	$post = [];
	foreach ($my_tab['POST'] as $rec => $posted) {
		if (!empty($posted[$my_tab['id_field_name']]) AND $posted[$my_tab['id_field_name']] !== "''") {
			// this will only occur if main record is updated or deleted!
			// check if posted ID is in existing IDs
			$key = array_search($posted[$my_tab['id_field_name']], $existing_ids);
			if ($key === false) {
				// illegal ID, this will only occur if user manipulated the form
				zz_error_log([
					'msg_dev' => 'Detail record with invalid ID was posted (ID of table_name %s was said to be %s, main record was ID %s)',
					'msg_dev_args' => [$my_tab['table_name'], $posted[$my_tab['id_field_name']], $zz_conf['int']['id']['value']],
					'level' => E_USER_NOTICE
				]);
				continue;
			}
		} elseif (in_array($state, ['add', 'edit']) 
			AND $my_tab['access'] !== 'show' AND $values 
			AND false !== $my_key = zz_values_get_equal_key($values, $posted)) {
			$key = $my_key;
		} elseif (in_array($state, ['add', 'edit']) 
			AND $my_tab['access'] !== 'show'
			AND $start_new_recs >= 0) {
			// this is a new record, append it
			$key = $start_new_recs;
			$existing[$key] = []; // no existing record exists
			// get source_value key
			if ($mode === 'add' AND !empty($main_id['source_value']))
				$my_tab['source_values'][$key] = $source_values[$rec];
			$start_new_recs++;
			if ($my_tab['max_records'] < $start_new_recs) $start_new_recs = -1;
		} else {
			// this is not allowed (wrong state or access: show, 
			// too many detail records)
			continue;
		}
		$post[$key] = $my_tab['POST'][$rec];
		$my_tab['check_select_fields'][$key] = zz_prepare_check_select($my_tab['table_name'], $rec);
		$records[$key] = $my_tab['POST'][$rec];
	}
	$my_tab['POST'] = $post;

	// get all keys (some may only be in existing, some only in POST (new ones))
	$my_tab['records'] = count($records);
}

/**
 * get field names for selechts which have to be checked
 *
 * @param string $table_name
 * @param int $rec
 * @return array
 */
function zz_prepare_check_select($table_name = '', $rec = 0) {
	$matches = [];
	if (empty($_POST['zz_check_select'])) return $matches;
	if ($table_name) {
		$pattern = sprintf('/^%s\[%d\]\[(.+)\]$/', $table_name, $rec);
	} else {
		$pattern = '/^([^[]+)$/';
	}
	foreach ($_POST['zz_check_select'] as $field_name) {
		preg_match($pattern, $field_name, $match);
		if (!empty($match[1])) $matches[] = $match[1];
	}
	return $matches;
}

/**
 * gets ID of subrecord if one of the fields in the subrecord definition
 * is defined as unique (only for multi-operations)
 * 
 * @param array $my_tab = $zz_tab[$tab]
 * @param array $existing
 * @param array $fields = $zz_tab[$tab]['fields'] for a subtable
 * @return array $my_tab['POST']
 */
function zz_subrecord_unique($my_tab, $existing, $fields) {
	// check if a GET is set on the foreign key
	$foreign_key = $my_tab['foreign_key_field_name'];
	if ($pos = strrpos($foreign_key, '.')) {
		$foreign_key = substr($foreign_key, $pos + 1);
	}
	if (!empty($_GET['where'][$foreign_key])) {
		$my_tab['sql'] = wrap_edit_sql($my_tab['sql'], 
			'WHERE', $foreign_key.' = '.intval($_GET['where'][$foreign_key]));
	}
	$id_field = ['field_name' => $my_tab['id_field_name'], 'value' => ''];
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
			$values = [];
			foreach ($my_tab['POST'] as $no => $record) {
				$check_select_fields = zz_prepare_check_select($my_tab['table_name'], $no);
				foreach ($unique as $field_name) {
					if (!isset($record[$field_name])) {
						// check if there's a value for this field which cannot be changed
						foreach ($fields as $field) {
							if ($field['field_name'] !== $field_name) continue;
							if (empty($field['value'])) continue;
							$values[$field_name] = $field['value'];
							break;
						}
						if (empty($values[$field_name])) {
							zz_error_log([
								'msg_dev' => 'UNIQUE was set but field %s is not in POST',
								'msg_dev_args' => [$field_name]
							]);
							continue;
						}
					} else {
						$values[$field_name] = $record[$field_name];
					}
					// check if we have to get the corresponding ID for a string
					if (intval($values[$field_name]).'' === $values[$field_name].'') continue;
					foreach ($fields as $field) {
						if ($field['field_name'] !== $field_name) continue;
						if ($field['type'] !== 'select') break;
						if (empty($field['sql'])) break;

						if (in_array($field_name, $check_select_fields)) {
							// check unless explicitly said not to check
							break;
						}
						
						$my_id_field = $id_field;
						if (array_key_exists('field_name', $id_field) AND isset($record[$id_field['field_name']])) {
							$my_id_field['value'] = $record[$id_field['field_name']];
						} else {
							$my_id_field['value'] = '';
						}
						$field = zz_check_select_id($field, $values[$field_name].' ', $my_id_field);
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
				$my_id_field = $id_field;
				$my_id_field['value'] = isset($record[$id_field['field_name']]) ? $record[$id_field['field_name']] : '';
				$field = zz_check_select_id(
					$field, $record[$field['field_name']], $my_id_field
				);
				if (count($field['possible_values']) === 1) {
					$value = reset($field['possible_values']);
				} elseif (count($field['possible_values']) === 0) {
					$value = '';
				} else {
					$value = '';
					zz_error_log([
						'msg_dev' => 'Field marked as unique, but could not find corresponding value: %s',
						'msg_dev_args' => [$field['field_name']],
						'level' => E_USER_NOTICE
					]);
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
				zz_error_log([
					'msg_dev' => 'Field marked as unique, but value appears more than once in record: %s (SQL %s)',
					'msg_dev_args' => [$value, $sql],
					'level' => E_USER_NOTICE
				]);
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
	$order = [];
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
		$existing[$index] = [];
	// fill array with values
	foreach ($order as $index => $id) {
		$existing[$index] = $my_existing[$id];
	}
	$existing_ids = $order;
	ksort($existing_ids);

	return [$records, $existing_ids, $existing, $values];
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
	$my_values = [];
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
 * @param array $existing_ids
 * @return array $my_tab
 */
function zz_get_subrecords_mode($my_tab, $rec_tpl, $existing_ids) {
	// function will be run twice from zzform(), therefore be careful, 
	// programmer!

	for ($rec = 0; $rec < $my_tab['records']; $rec++) {
		// do not change other values if they are already there 
		// (important for error messages etc.)
		$continue_fast = (isset($my_tab[$rec]) ? true: false);
		if (!$continue_fast) // reset fields only if necessary
			$my_tab[$rec] = $rec_tpl;
		$my_tab = zz_set_values($my_tab, $rec);
		// ok, after we got the values, continue, rest already exists.
		if ($continue_fast) continue;

		if (isset($existing_ids[$rec])) $idval = $existing_ids[$rec];
		else $idval = false;
		$my_tab[$rec]['id']['value'] = $idval;
		if (!empty($my_tab['source_values'][$rec]))
			$my_tab[$rec]['id']['source_value'] = $my_tab['source_values'][$rec];
		$my_tab[$rec]['save_record'] = isset($my_tab['zz_save_record'][$rec])
			? $my_tab['zz_save_record'][$rec] : '';

		$my_tab[$rec]['POST'] = [];
		if ($my_tab['POST']) {
			foreach ($my_tab['POST'] as $key => $my_rec) {
				if ($idval) {
					if (!isset($my_rec[$rec_tpl['id']['field_name']])) continue;
					if ($my_rec[$rec_tpl['id']['field_name']] != $idval) continue;
				} else {
					if (!empty($my_rec[$rec_tpl['id']['field_name']])) continue;
					if ($my_tab[$rec]['POST']) continue;
				}
				// find first value pair that matches and put it into POST
				$my_tab[$rec]['POST'] = $my_rec;
				$my_tab[$rec]['check_select_fields'] = $my_tab['check_select_fields'][$key] ?? [];
				unset($my_tab['POST'][$key]);
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
 * @return array $my_tab
 */
function zz_set_values($my_tab, $rec) {
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
		$my_tab['POST'][$rec] = zz_prepare_def_vals(
			$my_tab['POST'][$rec], $my_tab[$rec]['fields'], [], $my_tab['where']
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
function zz_prepare_def_vals($post, $fields, $existing = [], $where = []) {
	foreach ($fields as $field) {
		if (empty($field['field_name'])) continue;
		$field_name = $field['field_name'];
		// don't overwrite write_once with value if a value exists
		if ($field['type'] === 'write_once' AND !empty($existing[$field_name])) continue;
		// for all values, overwrite posted values with needed values
		if (!empty($field['value'])) 
			$post[$field_name] = $field['value'];
		// values which are not set in $post: read from $existing (e. g. if zzform_multi() is called)
		if (array_key_exists($field_name, $existing) AND !array_key_exists($field_name, $post))
			$post[$field_name] = $existing[$field_name];
		// just for values which are not set (!) set default value
		// (not for empty strings!, not for update)
		if (zz_prepare_use_default($post, $field))
			$post[$field_name] = $field['default'];
		// most important, therefore last: [where]
		if (!empty($where[$field_name]))
			$post[$field_name] = $where[$field_name];
		// if it's a mass upload or someone cuts out field_names, 
		// treat these fields as if nothing was posted
		// some fields must not be initialized, so ignore them
		$unwanted_field_types = [
			'id', 'foreign_key', 'translation_key', 'display'
		];
		if (!isset($post[$field_name])
			AND !in_array($field['type'], $unwanted_field_types))
			$post[$field_name] = '';
		// add 1 to first sequence field (no. was hidden)
		if ($field['type'] === 'sequence') {
			if (isset($post[$field_name]) AND !$post[$field_name]) $post[$field_name] = 1;
		}
	}
	return $post;
}

/**
 * check whether to set default value or not
 *
 * @param array $post
 * @param array $field
 * @return bool
 */
function zz_prepare_use_default($post, $field) {
	if (!zz_has_default($field)) return false; // no default value
	if (!array_key_exists($field['field_name'], $post)) return true; // key does not exist: use default
	if ($post[$field['field_name']]) return false; // has value
	if ($post[$field['field_name']] === 0) return false; // has value
	if ($post[$field['field_name']] === '0') return false; // has value
	if (!empty($field['required_in_db'])) return true; // no value sent, but a value is required: use default
	return false;
}

/** 
 * query record 
 * 
 * if everything was successful, query record (except in case it was deleted)
 * if not, write POST values back into form
 *
 * @param array $zz_tab
 * @param int $tab number of table
 * @param int $rec number of detail record
 * @param bool $validation true/false
 * @param string $mode ($ops['mode'])
 * @return array $zz_tab[$tab]
 *		might unset $zz_tab[$tab][$rec]
 *		$zz_tab[$tab][$rec]['record'], $zz_tab[$tab][$rec]['record_saved'], 
 *		$zz_tab[$tab][$rec]['fields'], $zz_tab[$tab][$rec]['action']
 */
function zz_query_record($zz_tab, $tab, $rec, $validation, $mode) {
	global $zz_conf;
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	$my_tab = $zz_tab[$tab];
	$my_rec = &$my_tab[$rec];
	$table = $my_tab['table'];
	// detail records don't have 'extra'
	if (!isset($my_tab['sqlextra'])) $my_tab['sqlextra'] = [];

	// in case, record was deleted, query record is not necessary
	if ($my_rec['action'] === 'delete') {
		unset($my_rec);
		return zz_return($my_tab);
	}
	// in case validation was passed or access is 'show'
	// everything's okay.
	if ($validation OR $my_rec['access'] === 'show') {
		// initialize 'record'
		$my_rec['record'] = [];
		// check whether record already exists (this is of course impossible 
		// for adding a record!)
		if (in_array($mode, ['edit', 'add']) AND !empty($zz_conf['int']['add_details_return'])) {
			if (!empty($my_rec['POST'])) {
				$my_rec['record'] = $my_rec['POST'];
			}
		} elseif ($mode !== 'add' OR $my_rec['action']) {
			if ($my_rec['id']['value'] < 0
				AND !empty($zz_conf['int']['revision_data'][$zz_tab[$tab]['table_name']][$my_rec['id']['value']])) {
				$my_rec['record'] = $zz_conf['int']['revision_data'][$zz_tab[$tab]['table_name']][$my_rec['id']['value']];
				$my_rec['record'] = zz_prepare_record($my_rec['record'], $my_rec['fields']);
			} elseif ($my_rec['id']['value']) {
				$my_rec['record'] = zz_query_single_record(
					$my_tab['sql'], $table, $my_tab['table_name'], $my_rec['id'], $my_tab['sqlextra']
				);
				$my_rec['record'] = zz_prepare_record($my_rec['record'], $my_rec['fields']);
			} elseif (!empty($my_rec['id']['values'])) {
				$my_rec['record'] = zz_query_multiple_records(
					$my_tab['sql'], $table, $my_rec['id']
				);
				$my_rec['record'] = zz_prepare_record($my_rec['record'], $my_rec['fields']);
				// @todo: think about sqlextra
			} elseif ($my_rec['access'] === 'show' AND !empty($my_rec['POST'])) {
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
					$sql, $table, $my_tab['table_name'], $my_rec['id'], $my_tab['sqlextra'], 'source_value'
				);
				if (empty($my_rec['record'])) {
					$my_tab['id']['source_value'] = false;
					// source record does not exist
				} else {
					$my_rec['record'][$my_rec['id']['field_name']] = false;
					$my_rec['record'] = zz_prepare_record($my_rec['record'], $my_rec['fields']);
				}
			}
			// remove some values which cannot be copied
			$my_rec['record'] = zz_prepare_clean_copy($my_rec['fields'], $my_rec['record']);
		}
	// record has to be passed back to user
	} else {
		if (isset($my_rec['POST-notvalid'])) {
			$my_rec['record'] = $my_rec['POST-notvalid'];
			// remove illegal data
			foreach ($my_rec['record'] as $field_name => $value)
				if (is_array($value)) $my_rec['record'][$field_name] = '';
		} elseif (isset($my_rec['POST'])) {
			$my_rec['record'] = $my_rec['POST'];
		} else {
			$my_rec['record'] = [];
		}
		
	//	get record for display fields and maybe others
		$my_rec['record_saved'] = zz_query_single_record(
			$my_tab['sql'], $table, $my_tab['table_name'], $my_rec['id'], $my_tab['sqlextra']
		);

	//	display form again			
		$my_rec['action'] = 'review';

	//	print out all records which were wrong, set class to error
		foreach ($my_rec['fields'] as $no => $field) {
			// just look for check_validation set but false
			if (!isset($field['check_validation']) 
				OR $field['check_validation']) continue;
			// append error to 'class'
			$my_rec['fields'][$no]['class'][] = 'error';
		}
	}
	// revision?
	if ($mode === 'revise' AND !empty($zz_tab[0]['revision_id'])) {
		$my_tab = zz_revisions_read_data($my_tab, $zz_tab[0]['revision_id']);
	}
	zz_log_validation_errors($my_rec, $validation);
	return zz_return($my_tab);
}

/**
 * prepare record from database, convert binary fields
 * to make life easy
 *
 * @param array $record
 * @param array $fields
 * @return array $record
 */
function zz_prepare_record($record, $fields) {
	foreach ($fields as $field) {
		if (zz_get_fieldtype($field) === 'ip' AND !empty($record[$field['field_name']])) {
			$record[$field['field_name']] = inet_ntop($record[$field['field_name']]);
		}
	}
	return $record;
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
			unset($record[$my_field['field_name']]);
			$defvals = zz_prepare_def_vals($record, [$my_field]);
			$record[$my_field['field_name']] = $defvals[$my_field['field_name']] ?? NULL;
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
 * @return bool true: some errors were logged; false no errors were logged
 */
function zz_log_validation_errors($my_rec, $validation) {
	if ($my_rec['action'] === 'delete') return false;
	if ($validation) return false;
	if ($my_rec['access'] === 'show') return false;
	$dev_msg = [];
	$somelogs = $my_rec['validation_error_logged'] ?? false;
	
	foreach ($my_rec['fields'] as $no => $field) {
		if (!empty($field['type']) AND in_array($field['type'], ['password_change', 'subtable', 'foreign_table'])) continue;
		if (!empty($field['mark_reselect'])) {
			// oh, it's a reselect, add some validation message
			zz_log_reselect_errors($field['title'], $field['type']);
			$somelogs = true;
			continue;
		}
		// just look for check_validation set but false
		if (!isset($field['check_validation'])) continue;
		if ($field['check_validation']) continue;
		if (!empty($my_rec['record'][$field['field_name']]) AND is_string($my_rec['record'][$field['field_name']])) {
			$my_rec['record'][$field['field_name']] = trim($my_rec['record'][$field['field_name']]);
		}
		if (!empty($my_rec['record'][$field['field_name']])) {
			// there's a value, so this is an incorrect value
			if (!empty($field['error_msg'])) {
				$error = $field['error_msg'];
			} else {
				$error = 'Value incorrect in field <strong>%s</strong>.'; 
				zz_error_validation_log('msg_args', $field['title']);
			}
			if (!empty($field['validation_error'])) {
				$error = [$error];
				$error[] = $field['validation_error']['msg'];
				if (!empty($field['validation_error']['msg_args'])) {
					zz_error_validation_log('msg_args', $field['validation_error']['msg_args']);
				}
			}
			zz_error_validation_log('msg', $error);
			zz_error_validation_log('msg_dev', 'Incorrect value: %s');
			zz_error_validation_log('msg_dev_args', $field['field_name']);
			zz_error_validation_log('msg_dev_args', is_array($my_rec['record'][$field['field_name']])
				? json_encode($my_rec['record'][$field['field_name']])
				: $my_rec['record'][$field['field_name']]
			);
			zz_error_validation_log('log_post_data', true);
			$somelogs = true;
		} elseif (empty($field['dont_show_missing'])) {
			if ($field['type'] === 'upload_image') {
				$msg = 'Nothing was uploaded in field <strong>%s</strong>.';
				zz_error_validation_log('msg_args', $field['title']);
				if (!empty($my_rec['images'][$no][0]['upload']['msg'])) {
					$msg = [$msg];
					$msg[] = $my_rec['images'][$no][0]['upload']['msg'];
					zz_error_validation_log('msg_args', $my_rec['images'][$no][0]['upload']['msg_args']);
				}
				zz_error_validation_log('msg', $msg);
			} else {
				// there's a value missing
				zz_error_validation_log('msg', 'Value missing in field <strong>%s</strong>.');
				zz_error_validation_log('msg_args', $field['title']);
				zz_error_validation_log('log_post_data', true);
			}
			$somelogs = true;
		} else {
			if ($field['type'] === 'upload_image') {
				$dev_msg['upload'][] = $field['title'];
			} else {
				$dev_msg['field'][] = $field['title'];
			}
		}
	}
	if ($dev_msg AND !$somelogs) {
		// show dev error message if there's some error but nothing is shown
		if (!empty($dev_msg['field'])) {
			zz_error_log([
				'msg_dev' => 'Validation error, value missing in field `%s`. Error was hidden (table misconfiguration?).',
				'msg_dev_args' => $dev_msg['field']
			]);
		} elseif (!empty($dev_msg['upload'])) {
			zz_error_log([
				'msg_dev' => 'Validation error, image upload missing in field `%s`. Error was hidden (table misconfiguration?).',
				'msg_dev_args' => $dev_msg['upload']
			]);
		}
	}
	return true;
}

/**
 * saves titles of fields with 'reselect' errors
 *
 * @param string $field_name (optional, will be added to list)
 * @param string $type
 * @return array list of field titles
 */
function zz_log_reselect_errors($field_name = false, $type = 'select') {
	static $field_names = [];
	if ($field_name)
		$field_names[] = [
			'title' => $field_name,
			$type => true
		];
	return $field_names;
}

/**
 * Query single record
 *
 * @param string $sql $zz_tab[tab]['sql']
 * @param string $table $zz['table']
 * @param array $id	id[value] and [id]field_name from record
 * @param array $sqlextra $zz['sqlextra']
 * @param string $type
 * @return array
 */
function zz_query_single_record($sql, $table, $table_name, $id, $sqlextra, $type = 'value') {
	global $zz_conf;
	if (!$id[$type]) return [];
	$sql = wrap_edit_sql($sql,
		'WHERE', sprintf('%s.%s = %d', $table, $id['field_name'], $id[$type])
	);
	$sql = wrap_edit_sql($sql, 'FORCE INDEX', ' ', 'delete');
	$record = zz_db_fetch($sql, '', '', 'record exists? ('.$type.')');
	// if record is not yet in database, we will not get extra data because
	// no ID exists yet
	if (!$record) return [];
	foreach ($sqlextra as $sql) {
		if (empty($id[$type])) {
			zz_error_log([
				'msg_dev' => 'No ID %s found (Query: %s).',
				'msg_dev_args' => [$type, $sql]
			]);
			continue;
		}
		$sql = sprintf($sql, $id[$type]);
		$record = array_merge($record, zz_db_fetch($sql));
	}
	if (isset($zz_conf['int']['revision_data'][$table_name])
		AND array_key_exists($id['value'], $zz_conf['int']['revision_data'][$table_name])) {
		$data = $zz_conf['int']['revision_data'][$table_name][$id['value']];
		if ($data === NULL) {
			$record = [];
		} else {
			foreach ($data as $field => $value) {
				if (is_array($value)) continue;
				$record[$field] = $value;
			}
		}		
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
 * @param int main id value	(source value or own value)
 * @param string $id_field_name = ID field name of detail record
 * @param array $deleted_ids = IDs that were deleted by user
 * @global array $zz_conf
 * @return array $records, indexed by ID
 */
function zz_query_subrecord($my_tab, $id_value, $id_field_name, $deleted_ids = []) {
	global $zz_conf;
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	
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
	if (!empty($my_tab['translate_field_name_where'])) {
		// translation subtable
		$sql = wrap_edit_sql($sql, 'WHERE', $my_tab['translate_field_name_where']);
	}
	$sql = wrap_edit_sql(
		$sql, 'WHERE', sprintf('%s = %d', $my_tab['foreign_key_field_name'], $id_value)
	);

	$records = zz_db_fetch($sql, $id_field_name, '', '', E_USER_WARNING);
	foreach ($records as $id => $line) {
		if (!in_array($line[$id_field_name], $deleted_ids)) continue;
		// get rid of deleted records
		unset($records[$id]);
	}
	if (!empty($zz_conf['int']['revisions_only']))
		$records = zz_revisions_subrecord($my_tab, $records);
	return zz_return($records);
}
