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
	$user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NULL';

	$data = array();
	foreach ($ops['return'] as $index => $table) {
		if ($table['action'] === 'nothing') continue;
		if ($table['action'] === 'delete') {
			$data[] = array(
				'table_name' => $table['table'],
				'record_id' => $table['id_value'],
				'changed_values' => 'NULL',
				'complete_values' => 'NULL',
				'rev_action' => $table['action']
			);
			continue;
		}
		$changed = [];
		foreach ($ops['record_diff'][$index] as $field_name => $diff) {
			if ($diff === 'same') continue;
			$changed[$field_name] = $ops['record_new'][$index][$field_name];
		}
		if (!$changed) continue;
		$data[] = array(
			'table_name' => $table['table'],
			'record_id' => $table['id_value'],
			'changed_values' => sprintf('"%s"', wrap_db_escape(json_encode($changed))),
			'complete_values' => sprintf('"%s"', wrap_db_escape(json_encode($ops['record_new'][$index]))),
			'rev_action' => $table['action']
		);
	}
	if (!$data) return array();

	$status = !empty($zz_conf['int']['revisions_only']) ? 'pending' : 'live';
	$sql = 'INSERT INTO %s (main_table_name, main_record_id, user_id, rev_status, created, last_update)
		VALUES ("%s", %d, %s, "%s", NOW(), NOW())';
	$sql = sprintf($sql,
		$zz_conf['revisions_table'], $ops['return'][0]['table'],
		$ops['return'][0]['id_value'], $user_id, $status
	);
	$result = wrap_db_query($sql);
	if (!$result) return array();
	$rev_id = mysqli_insert_id($zz_conf['db_connection']);
	zz_log_sql($sql, $zz_conf['user'], $rev_id);

	if ($status === 'live') {
		$sql = 'UPDATE %s SET rev_status = "historic", last_update = NOW()
			WHERE rev_status = "live" AND main_table_name = "%s" AND main_record_id = %d AND revision_id < %d';
		$sql = sprintf($sql,
			$zz_conf['revisions_table'], $ops['return'][0]['table'],
			$ops['return'][0]['id_value'], $rev_id
		);
		$result = wrap_db_query($sql);
		$rows = mysqli_affected_rows($zz_conf['db_connection']);
		if ($rows) {
			zz_log_sql($sql, $zz_conf['user']);
		}
	}

	$sql_rev = 'INSERT INTO %s (revision_id, table_name, record_id, changed_values,
		complete_values, rev_action) VALUES (%d, "%%s", %%d, %%s, %%s, "%%s")';
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

/**
 * read revisions from database
 *
 * @param string $table
 * @param int $record_id
 * @return array
 */
function zz_revisions_read($table, $record_id) {
	$sql = 'SELECT revisiondata_id
			, table_name, record_id, changed_values, rev_action
		FROM _revisiondata
		LEFT JOIN _revisions USING (revision_id)
		WHERE user_id = %d
		AND rev_status = "pending"
		AND main_table_name = "%s"
		AND main_record_id = %d
		ORDER BY created ASC';
	$sql = sprintf($sql, $_SESSION['user_id'], $table, $record_id);
	$revisions = wrap_db_fetch($sql, 'revisiondata_id');
	$data = [];
	foreach ($revisions as $rev) {
		switch ($rev['rev_action']) {
		case 'update':
			$changed_values = json_decode($rev['changed_values']);
			foreach ($changed_values as $field => $value) {
				if ($table === $rev['table_name'] AND $record_id.'' === $rev['record_id'].'') {
					$data[$field] = $value;
				} else {
					$data[$rev['table_name']][$rev['record_id']][$field] = $value;
				}
			}
			break;
		case 'delete':
			if ($table === $rev['table_name'] AND $record_id.'' === $rev['record_id'].'') {
				$data = NULL;
			} else {
				$data[$rev['table_name']][$rev['record_id']] = NULL;
			}
			break 2; // once a record is deleted, the rest is uninteresting
		case 'insert':
			// @todo
			break;
		}
	}
	return $data;
}
