<?php 

/**
 * zzform
 * Revision functions
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function zz_revisions($ops) {
	global $zz_conf;
	global $zz_error;
	$user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NULL';

	$data = array();
	foreach ($ops['return'] as $index => $table) {
		if ($table['action'] === 'nothing') continue;
		$changed = [];
		foreach ($ops['record_diff'][$index] as $field_name => $diff) {
			if ($diff === 'same') continue;
			$changed[$field_name] = $ops['record_new'][$index][$field_name];
		}
		if (!$changed) continue;
		$data[] = array(
			'table_name' => $table['table'],
			'record_id' => $table['id_value'],
			'changed_values' => wrap_db_escape(json_encode($changed)),
			'complete_values' => wrap_db_escape(json_encode($ops['record_new'][$index])),
			'rev_action' => $table['action']
		);
	}
	if (!$data) return array();

	$sql = 'INSERT INTO %s (main_table_name, user_id, rev_status, created, last_update)
		VALUES ("%s", %s, "live", NOW(), NOW())';
	$sql = sprintf($sql, $zz_conf['revisions_table'], $ops['return'][0]['table'], $user_id);
	$result = wrap_db_query($sql);
	if (!$result) return array();
	$rev_id = mysqli_insert_id($zz_conf['db_connection']);
	zz_log_sql($sql, $zz_conf['user'], $rev_id);

	$sql = 'UPDATE %s SET rev_status = "historic", last_update = NOW()
		WHERE rev_status = "live" AND main_table_name = "%s" AND revision_id < %d';
	$sql = sprintf($sql, $zz_conf['revisions_table'], $ops['return'][0]['table'], $rev_id);
	$result = wrap_db_query($sql);
	$rows = mysqli_affected_rows($zz_conf['db_connection']);
	if ($rows) {
		zz_log_sql($sql, $zz_conf['user']);
	}

	$sql_rev = 'INSERT INTO %s (revision_id, table_name, record_id, changed_values,
		complete_values, rev_action) VALUES (%d, "%%s", %%d, "%%s", "%%s", "%%s")';
	$sql_rev = sprintf($sql_rev, $zz_conf['revisions_data_table'], $rev_id);
	foreach ($data as $line) {
		$sql = vsprintf($sql_rev, $line);
		$result = wrap_db_query($sql);
		if (!$result) continue;
		$rev_data_id = mysqli_insert_id($zz_conf['db_connection']);
		zz_log_sql($sql, $zz_conf['user'], $rev_data_id);
	}
	return array();
}
