<?php 

/**
 * zzform
 * functions that are always available
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * call zzform() once or multiple times without user interaction 
 * 
 * If zzform_multi() is called from within zzform() (e. g. via an action before/
 * after-script), not all values in $zz_conf will be reset to default. Therefore,
 * it is important that the script used for zzform_multi() will set all variables
 * as it needs them.
 * A solution might be for you to run zz_initialize() before setting the record
 * specific variables and calling zzform()
 *
 * @param string $definition_file - script filename
 * @param array $values - values sent to script (instead of $_GET, $_POST and $_FILES)
 *		array	'POST'
 *		array	'GET'
 *		array	'FILES'
 *		string	'action' => 'POST'['zz_action']: insert, delete, update
 *		array	'ids' => List of select-field names that get direct input of an id 
 * @param string $type (optional)
 * @return array $ops
 * @todo do not unset superglobals
 * @todo so far, we have no support for 'values' for subrecords
 * @todo zzform() and zzform_multi() called within an action-script
 * causes not all zz_conf variables to be reset
 */
function zzform_multi($definition_file, $values, $type = 'tables') {
	// unset all variables that are not needed
	// important because there may be multiple zzform calls
	global $zz_conf;
	require_once __DIR__.'/zzform.php';
	
	$old = [
		'conf' => $zz_conf,
		'GET' => $_GET ?? [],
		'POST' => $_POST ?? [],
		'FILES' => $_FILES ?? []
	];
	unset($_GET);
	unset($_POST);
	unset($_FILES);

	// debug, note: this will only start from the second time, zzform_multi()
	// has been called! (modules debug is not set beforehands)
	if (wrap_setting('debug') AND function_exists('zz_debug') AND !empty($zz_conf['id'])) {
		$id = $zz_conf['id'];
		zz_debug('start', __FUNCTION__);
	}
	$ops = [];
	$ops['result'] = '';
	$ops['id'] = 0;
	// keep internal variables
	$int = $zz_conf['int'] ?? [];

	zz_initialize('overwrite');
	unset($zz_conf['if']);
	unset($zz_conf['unless']);
	$zz_conf['generate_output'] = false;
	// set 'multi' so we know the operation mode for other scripts
	$zz_conf['multi'] = true;
	if (!empty($values['GET'])) $_GET = $values['GET'];
	if (!empty($values['POST'])) $_POST = $values['POST'];
	// add some shortcuts easier to remember
	if (!empty($values['action'])) {
		$_POST['zz_action'] = $values['action'];
	}
	if (!empty($values['ids'])) {
		foreach ($values['ids'] as $field_name) {
			$_POST['zz_check_select'][] = $field_name;
		}
	}

	if (!empty($values['FILES'])) $_FILES = $values['FILES'];
	else $_FILES = [];

	if (wrap_setting('debug') AND function_exists('zz_debug') AND !empty($id)) {
		$old['id'] = $zz_conf['id'];	
		$zz_conf['id'] = zz_check_id_value($id);
		zz_debug('find definition file', $definition_file);
	}
	$zz = zzform_include($definition_file, $values, $type);
	wrap_setting('log_username_default', wrap_setting('request_uri'));
	if (wrap_setting('debug') AND function_exists('zz_debug') AND !empty($id)) {
		zz_debug('got definition file');
		$zz_conf['id'] = zz_check_id_value($old['id']);
	}
	// return on error in form script
	if (!empty($ops['error'])) return $ops;
	$ops = zzform($zz);

	// clean up
	zz_initialize('old_conf', $old['conf']);
	$_GET = $old['GET'];
	$_POST = $old['POST'];
	$_FILES = $old['FILES'];

	$zz_conf['int'] = $int;
	if (wrap_setting('debug') AND function_exists('zz_debug') AND !empty($id)) {
		$zz_conf['id'] = zz_check_id_value($id);
		zz_debug('end');
	}
	return $ops;
}

/**
 * include $zz- or $zz_sub-table definition and accept changes for $zz_conf
 * all other local variables will be ignored
 * include zzform/forms.inc.php or zzform/tables.inc.php, too
 *
 * @param string $definition_file filename of table definition
 * @param array $values (optional) values which might be used in table definition
 * @param string $type 'table', 'form' or 'integrity-check'
 * @param array $brick (optional, if called from zzbrick/forms)
 * @global array $zz_conf
 * @return array
 */
function zzform_include($file, $values = [], $type = 'tables', $brick = []) {
	if ($type === 'integrity-check') {
		$zz_conf = zzform_not_global();
		$type = 'tables';
	} else {
		global $zz_conf;
	}
	if (str_starts_with($file, '../zzbrick_forms/')) {
		$file = substr($file, 17);
		$type = 'forms';
		wrap_error('calling zzform_include() with `../zzbrick_forms/` is deprecated', E_USER_DEPRECATED);
	}

	if (!in_array($type, ['tables', 'forms']))
		wrap_error(sprintf('%s is not a possible type for %s().', ucfirst($type), __FUNCTION__), E_USER_ERROR);
	
	$path = 'zzbrick_%s/%s.php';
	$files = wrap_collect_files(sprintf($path, $type, $file));
	if (!$files and strstr($file, '/')) {
		$parts = explode('/', $file);
		$files = wrap_collect_files(sprintf($path, $type, $parts[1]), $parts[0]);
	}
	if (!$files)
		if (!empty($brick['parameter'])) wrap_quit(404); // looking with *
		else wrap_error(sprintf('%s definition for %s: file is missing.', ucfirst($type), $file), E_USER_ERROR);

	// allow to use script without recursion
	$backtrace = debug_backtrace();
	foreach ($backtrace as $step) {
		if (!array_key_exists('file', $step)) continue;
		if (!in_array($step['file'], $files)) continue;
		$key = array_search($step['file'], $files);
		unset($files[$key]);
	}
	if (!$files)
		wrap_error(sprintf('%s definition for %s: file is missing.', ucfirst($type), $file), E_USER_ERROR);

	if (key($files) === 'default' AND count($files) > 1)
		array_shift($files);

	if (key($files) === 'default') {
		if (!$default_tables = wrap_setting('default_tables')) return [];
		if (is_array($default_tables) AND !in_array($file, $default_tables)) return [];
	}
	if ($package = key($files) AND $package !== 'custom')
		wrap_package_activate($package);

	$path = 'zzform/%s.inc.php';
	$common_files = wrap_include_files(sprintf($path, $type, $file));
	$definition = reset($files);
	
	global $zz_conf;
	include $definition;

	$def = $zz ?? $zz_sub ?? [];
	if (!$def)
		wrap_error(sprintf('No %s definition in file %s found.', $type, $file), E_USER_ERROR);
	return $def;
}

/**
 * get read only $zz_conf
 *
 * @return array
 */
function zzform_not_global() {
	global $zz_conf;
	return $zz_conf;
}

/**
 * check if ID is valid (if returned via browser), if invalid: create new ID
 *
 * @param string
 * @return string
 */
function zz_check_id_value($string) {
	if (is_array($string))
		return zz_check_id_value_error();
	for ($i = 0; $i < mb_strlen($string); $i++) {
		$letter = mb_substr($string, $i, 1);
		if (!strstr('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', $letter))
			return zz_check_id_value_error();
	}
	return $string;
}

/**
 * if ID is invalid, create new ID and if it was received via POST, log as error
 *
 * @return string
 */
function zz_check_id_value_error() {
	if (!empty($_POST['zz_id'])) {
		wrap_setting('log_username_suffix', wrap_setting('remote_ip'));
		wrap_error(sprintf('POST data removed because of illegal zz_id value `%s`', json_encode($_POST['zz_id'])), E_USER_NOTICE);
		unset($_POST);
	}
	return wrap_random_hash(6);
}

/**
 * read or write secret_key connected to zzform ID
 *
 * @param string $mode ('read', 'write', 'timecheck')
 * @param string $id
 * @param string $hash
 */
function zz_secret_id($mode, $id = '', $hash = '') {
	global $zz_conf;
	if (!empty($zz_conf['multi'])) return;

	$logfile = wrap_setting('log_dir').'/zzform-ids.log';
	if (!file_exists($logfile)) touch($logfile);
	$logs = file($logfile);
	if (!$id) $id = $zz_conf['id'];

	$now = time();
	// keep IDs for a maximum of one day
	$keep_max = $now - 60 * 60 * 24;
	$found = '';
	$timestamp = 0;
	$delete_lines = [];
	foreach ($logs as $index => $line) {
		// 0 = timestamp, 1 = zz_id, 2 = secret
		$file = explode(' ', trim($line));
		if ($file[0] < $keep_max) $delete_lines[] = $index;
		if ($file[1] !== $id) continue;
		$found = $file[2];
		$timestamp = $file[0];
		break;
	}
	if ($delete_lines) {
		require_once wrap_setting('core').'/file.inc.php';
		wrap_file_delete_line($logfile, $delete_lines);
	}

	if ($mode === 'read') return $found;
	elseif ($mode === 'timecheck') return $now - $timestamp;
	if ($found) return;
	error_log(sprintf("%s %s %s\n", $now, $id, $hash), 3, $logfile);
}

/**
 * prepare batch operation per table
 *
 * @param string $table
 * @param string $msg
 * @param string $msg_2 (optional)
 * @return array
 */
function zzform_batch_def($table, $msg = '', $msg_2 = '') {
	wrap_include_files('zzform/definition');

	$def['table_script'] = str_replace('_', '-', $table);
	$def['msg'] = $msg;
	if ($def['msg']) $def['msg'] .= ' ';
	if ($msg_2) $msg_2 = sprintf(' %s', $msg_2);

	$zz = zzform_include($def['table_script']);
	$def['table'] = wrap_db_prefix($zz['table']);

	// read table structure from database
	$sql = 'SHOW COLUMNS FROM `%s`';
	$sql = sprintf($sql, $def['table']);
	$structure = wrap_db_fetch($sql, '_dummy_', 'numeric');
	foreach ($structure as $field) {
		if ($field['Key'] === 'PRI') $def['primary_key'] = $field['Field'];
		elseif ($field['Key'] === 'UNI') $def['uniques'][] = $field['Field'];
	}

	// get table definition
	$def['ids'] = [];
	foreach ($zz['fields'] as $no => $field) {
		if (!empty($field['unique'])) $def['uniques'][] = $field['field_name'];
		if (empty($field['type'])) continue;
		if ($field['type'] === 'subtable') {
			$def['subtable_sqls'][$no] = $field['sql'];
			$def['subtable_tables'][$no] = $field['table_name'] ?? $field['table'];
			foreach ($field['fields'] as $subfield) {
				if (empty($subfield['type'])) continue;
				switch ($subfield['type']) {
				case 'id':
					$def['subtable_primary_keys'][$no] = $subfield['field_name'];
					break;
				case 'foreign_key':
					$def['subtable_foreign_keys'][$no] = $subfield['field_name'];
					break;
				}
			}
		}
		if ($field['type'] === 'id') {
			$def['primary_key'] = $field['field_name']; // duplicate from above, just in case
		} elseif (!empty($field['field_name']) AND str_ends_with($field['field_name'], '_id')) {
			$def['ids'][] = $field['field_name'];
		}
	}
	$def['sql'] = $zz['sql'];
	if (empty($def['primary_key'])) {
		wrap_error($def['msg'].wrap_text(
			'Unable to find ID field name for table %s.',
			['values' => [$def['table'], implode(', ', $ids)]]
		).$msg_2, E_USER_ERROR);
	}
	if (!empty($zz['add'])) {
		foreach ($zz['add'] as $add) {
			$def['add'][$add['field_name']][] = $add['value'];
		}
	} else {
		$def['add'] = [];
	}

	return $def;
}

/**
 * delete one or more records of a table
 *
 * examples:
 * zzform_delete('rooms_contacts', 23); // delete ID 23
 * delete UNIQUE record with room_id = 10 and contact_id = 14
 * zzform_delete('rooms_contacts', ['room_id' => 10, 'contact_id' => 14]); 
 * @param string $table
 * @param mixed $ids
 * @param int $error_type (optional)
 * @param string $msg (optional)
 * @return array
 */
function zzform_delete($table, $ids, $error_type = E_USER_NOTICE, $msg = '') {
	if (!is_array($ids)) $ids = [$ids];
	// @todo add support for UNIQUE ids with else

	$msg_2 = wrap_text('Deletion of IDs %s impossible.', ['values' => [implode(', ', $ids)]]);
	$def = zzform_batch_def($table, $msg, $msg_2);

	$deleted_ids = [];
	$values = [];
	$values['action'] = 'delete';
	foreach ($ids as $id) {
		$values['POST'][$def['primary_key']] = $id;
		$ops = zzform_multi($def['table_script'], $values);
		if (!$ops['id']) {
			wrap_error($def['msg'].wrap_text(
				'Unable to delete ID %s from table %s. Reason: %s',
				['values' => [$id, $def['table'], json_encode($ops['error'])]]
			), $error_type);
		} else {
			$deleted_ids[] = $ops['id'];
		}
	}
	return $deleted_ids;
}

/**
 * insert a record into a table
 *
 * examples:
 * zzform_insert('categories', $data); // insert into categories tables, values => keys
 * @param string $table
 * @param array $data
 * @param int $error_type (optional)
 * @param string $msg (optional)
 * @return int
 */
function zzform_insert($table, $data, $error_type = E_USER_NOTICE, $msg = '') {
	$msg_2 = wrap_text('Insertion of record into table %s impossible.', ['values' => [$table]]);
	$def = zzform_batch_def($table, $msg, $msg_2);

	$values = [];
	$values['action'] = 'insert';
	$values['ids'] = $def['ids'];
	foreach ($def['add'] as $field_name => $ids) {
		if (!array_key_exists($field_name, $data)) continue;
		if (in_array($data[$field_name], $ids)) {
			$values['GET']['add'][$field_name] = $data[$field_name];
			unset($data[$field_name]);
		} elseif (in_array('', $ids)) {
			// do nothing
		} else {
			wrap_error($def['msg'].wrap_text(
				'Forbidden to insert data %s into table %s: Value %s is not allowed for field %s. Reason: %s',
				['values' => [json_encode($data), $def['table'], $data[$field_name], $field_name, json_encode($ops['error'])]]
			), $error_type);
			return NULL;
		}
	}
	$values['POST'] = $data;
	$ops = zzform_multi($def['table_script'], $values);
	if (!$ops['id'] AND !$ops['ignore']) {
		wrap_error($def['msg'].wrap_text(
			'Unable to insert data %s into table %s. Reason: %s',
			['values' => [json_encode($data), $def['table'], json_encode($ops['error'])]]
		), $error_type);
		return NULL;
	}
	return $ops['id'];
}

/**
 * update a record in a table
 *
 * @param string $table
 * @param array $data
 * @param int $error_type (optional)
 * @param string $msg (optional)
 * @return int
 */
function zzform_update($table, $data, $error_type = E_USER_NOTICE, $msg = '') {
	$def = zzform_batch_def($table, $msg);
	
	// allow to update with unique value instead of primary key, too
	if (empty($data[$def['primary_key']])) {
		if (empty($def['uniques'])) {
			wrap_error($def['msg'].wrap_text(
				'Unable to update record with data %s in table %s. No unique key given.',
				['values' => [json_encode($data), $def['table']]]
			), $error_type);
			return NULL;
		}
		foreach ($def['uniques'] as $unique_field) {
			if (!array_key_exists($unique_field, $data)) continue;
			$sql = 'SELECT `%s` FROM `%s` WHERE `%s` = "%s"';
			$sql = sprintf($sql, $def['primary_key'], $def['table'], $unique_field, $data[$unique_field]);
			$data[$def['primary_key']] = wrap_db_fetch($sql, '', 'single value');
			if ($data[$def['primary_key']]) break;
		}
		if (empty($data[$def['primary_key']])) {
			wrap_error($def['msg'].wrap_text(
				'Unable to update record with data %s in table %s. No primary key found.',
				['values' => [json_encode($data), $def['table']]]
			), $error_type);
			return NULL;
		}
	}
	
	$values = [];
	$values['action'] = 'update';
	$values['ids'] = $def['ids'];
	$values['POST'] = $data;
	$ops = zzform_multi($def['table_script'], $values);
	if (!$ops['id'] AND !$ops['ignore']) {
		wrap_error($def['msg'].wrap_text(
			'Unable to update data %s in table %s. Reason: %s',
			['values' => [json_encode($data), $def['table'], json_encode($ops['error'])]]
		), $error_type);
		return false;
	}
	return $ops['id'];
}

/**
 * copy records from one ID to another
 *
 * @param string $table
 * @param string $foreign_id_field
 * @param int $source_id
 * @param int $destination_id
 * @return array
 * @todo add support for translations, as in zz_copy_records()
 */
function zzform_copy($table, $foreign_id_field, $source_id, $destination_id) {
	$def = zzform_batch_def($table);
	
	// get existing data
	$sql = wrap_edit_sql($def['sql'], 'WHERE', sprintf('%s = %d', $foreign_id_field, $source_id));
	$records = wrap_db_fetch($sql, $def['primary_key']);
	if (!$records) return [];
	$details = [];
	if (!empty($def['subtable_sqls'])) {
		foreach ($def['subtable_sqls'] as $index => $sql) {
			$sql = wrap_edit_sql($sql, 'WHERE', sprintf('%s IN (%s)', $def['primary_key'], implode(',', array_keys($records))));
			$details[$index] = wrap_db_fetch($sql, '_dummy_', 'numeric');
		}
	}

	$dont_copy = wrap_setting('zzform_copy_fields_exclude');
	$dont_copy[] = $def['primary_key'];
	$dont_copy[] = $foreign_id_field;
	
	// insert data
	$results = [];
	foreach ($records as $id => $record) {
		$data = [];
		// main record
		foreach ($record as $field_name => $value) {
			if (in_array($field_name, $dont_copy)) continue;
			$data[$field_name] = $value;
			// @todo exclude display fields?
		}
		$data[$foreign_id_field] = $destination_id;

		// detail records
		foreach ($details as $index => $detailrecords) {
			foreach ($detailrecords as $detail_index => $detailrecord) {
				if ($detailrecord[$def['subtable_foreign_keys'][$index]] !== $record[$def['primary_key']]) continue;
				foreach ($detailrecord as $field_name => $value) {
					if (in_array($field_name, $dont_copy)) continue;
					if ($field_name === $def['subtable_foreign_keys'][$index]) continue;
					if ($field_name === $def['subtable_primary_keys'][$index]) continue;
					$data[$def['subtable_tables'][$index]][$detail_index][$field_name] = $value;
				}
			}
		}
		$results[] = zzform_insert($def['table'], $data);
	}
	return $results;
}
