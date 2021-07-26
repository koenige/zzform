<?php 

/**
 * zzform
 * Revision functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * write data to revisions from a hook function
 * (custom hook or internal zzform hook)
 *
 * @param array $ops
 * @param array $zz_tab (not used here, but for all hook functions in zz_action_function())
 * @param bool $rev_only
 * @return
 */
function zz_revisions($ops, $zz_tab = [], $rev_only = false) {
	global $zz_conf;
	$user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NULL';

	$data = [];
	foreach ($ops['return'] as $index => $table) {
		if ($table['action'] === 'nothing') continue;
		if ($table['action'] === 'delete') {
			$data[] = [
				'table_name' => $table['table_name'],
				'record_id' => $table['id_value'],
				'changed_values' => 'NULL',
				'complete_values' => 'NULL',
				'rev_action' => $table['action']
			];
			continue;
		}
		$changed = [];
		foreach ($ops['record_diff'][$index] as $field_name => $diff) {
			if ($diff === 'same') continue;
			$changed[$field_name] = $ops['record_new'][$index][$field_name];
		}
		if (!$changed) continue;
		$data[] = [
			'table_name' => $table['table_name'],
			'record_id' => $table['id_value'],
			'changed_values' => sprintf('"%s"', wrap_db_escape(json_encode($changed))),
			'complete_values' => sprintf('"%s"', wrap_db_escape(json_encode($ops['record_new'][$index]))),
			'rev_action' => $table['action']
		];
	}
	if (!$data) return [];

	$status = !empty($zz_conf['int']['revisions_only']) ? 'pending' : 'live';
	if ($rev_only) $status = 'pending'; // overwrite internal settings
	$sql = 'INSERT INTO /*_PREFIX_*/_revisions
		(main_table_name, main_record_id, user_id, rev_status, created, script_url, last_update)
		VALUES ("%s", %d, %s, "%s", NOW(), %s, NOW())';
	$sql = sprintf($sql,
		$ops['return'][0]['table'],
		$ops['return'][0]['id_value'], $user_id, $status
		, (!empty($zz_conf['revisions_url']) ? sprintf('"%s"', $zz_conf['revisions_url']) : 'NULL')
	);
	$rev_id = wrap_db_query($sql);
	if (empty($rev_id['id'])) return [];
	zz_log_sql($sql, $zz_conf['user'], $rev_id['id']);

	if ($status === 'live') {
		$sql = 'UPDATE /*_PREFIX_*/_revisions
			SET rev_status = "historic", last_update = NOW()
			WHERE rev_status = "live" AND main_table_name = "%s"
			AND main_record_id = %d AND revision_id < %d';
		$sql = sprintf($sql,
			$ops['return'][0]['table'],
			$ops['return'][0]['id_value'], $rev_id['id']
		);
		$rows = wrap_db_query($sql);
		if ($rows) zz_log_sql($sql, $zz_conf['user']);
	}

	zz_revisions_insert_data($data, $rev_id['id']);
	return [];
}

/**
 * save revision data
 *
 * @param array $data
 * @param int $id
 * @return void
 */
function zz_revisions_insert_data($data, $id) {
	global $zz_conf;

	$sql_rev = 'INSERT INTO /*_PREFIX_*/_revisiondata
		(revision_id, table_name, record_id, changed_values, complete_values, rev_action)
		VALUES (%d, "%%s", %%d, %%s, %%s, "%%s")';
	$sql_rev = sprintf($sql_rev, $id);
	foreach ($data as $line) {
		$sql = vsprintf($sql_rev, $line);
		$rev_data_id = wrap_db_query($sql);
		if (empty($rev_data_id['id'])) continue;
		zz_log_sql($sql, $zz_conf['user'], $rev_data_id['id']);
	}
}

/**
 * read revision data for $zz_tab
 *
 * @param array $main_tab = $zz_tab[0]
 * @return array
 */
function zz_revisions_tab($main_tab) {
	global $zz_conf;
	$revision_data = [];

	$data = zz_revisions_read($main_tab['table'], $main_tab[0]['id']['value']);
	if ($data === NULL) {
		$revision_data[$main_tab['table']] = [];
		return $revision_data;
	}
	foreach ($data as $field => $value) {
		if (!is_array($value)) {
			$revision_data[$main_tab['table']][$main_tab[0]['id']['value']][$field] = $value;
			continue;
		}
		// it’s a detail record
		foreach ($value as $my_record_id => $my_record) {
			if (empty($revision_data[$field][$my_record_id]))
				$revision_data[$field][$my_record_id] = [];
			if ($my_record) {
				$revision_data[$field][$my_record_id]
					= array_merge($revision_data[$field][$my_record_id], $my_record);
			} else {
				$revision_data[$field][$my_record_id] = NULL;
			}
		}
	}
	return $revision_data;
}

/**
 * overwrite or add subrecords
 *
 * @param array $my_tab
 * @param array $records
 */
function zz_revisions_subrecord($my_tab, $records) {
	global $zz_conf;
	if (empty($zz_conf['int']['revision_data'][$my_tab['table_name']])) return $records;

	// overwrite existing records
	foreach ($records as $id => $line) {
		if (!array_key_exists($id, $zz_conf['int']['revision_data'][$my_tab['table_name']])) continue;
		if (!isset($zz_conf['int']['revision_data'][$my_tab['table_name']][$id])) {
			foreach ($line as $field => $value) {
				if ($field === $my_tab['id']['field_name']) continue;
				if (!empty($my_tab['foreign_key']) AND $field === $my_tab['foreign_key']) continue;
				$records[$id][$field] = '';
			}
		} else {
			$records[$id] = array_merge(
				$line, $zz_conf['int']['revision_data'][$my_tab['table_name']][$id]
			);
		}
	}

	// add new virtual records
	foreach ($zz_conf['int']['revision_data'][$my_tab['table_name']] as $id => $line) {
		if (in_array($id, array_keys($records))) continue;
		if ($line) $records[$id] = $line;
	}
	return $records;
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
			, table_name, record_id, changed_values, complete_values, rev_action
		FROM /*_PREFIX_*/_revisiondata
		LEFT JOIN /*_PREFIX_*/_revisions USING (revision_id)
		WHERE user_id = %d
		AND rev_status = "pending"
		AND main_table_name = "%s"
		AND main_record_id = %d
		AND rev_action != "ignore"
		ORDER BY created ASC';
	$sql = sprintf($sql, $_SESSION['user_id'], $table, $record_id);
	$revisions = wrap_db_fetch($sql, 'revisiondata_id');
	$data = [];
	$dummy_ids = [];
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
			if ($table === $rev['table_name']) break; // @todo
			$changed_values = json_decode($rev['complete_values']);
			if (empty($dummy_ids[$rev['table_name']]))
				$dummy_ids[$rev['table_name']] = 0;
			$dummy_id = $dummy_ids[$rev['table_name']] -1;
			foreach ($changed_values as $field => $value) {
				$data[$rev['table_name']][$dummy_id][$field] = $value;
			}
			break;
		}
	}
	return $data;
}

/**
 * read a revision from the table if there is something that fits to this record
 *
 * @param string $table
 * @return int
 */
function zz_revisions_read_id($table) {
	global $zz_conf;
	$sql = 'SELECT revision_id
		FROM /*_PREFIX_*/_revisions
		WHERE main_table_name = "%s"
		AND main_record_id = %d
		AND rev_status = "pending"
		LIMIT 1';
	$sql = sprintf($sql, $table, $zz_conf['int']['id']['value']);
	return wrap_db_fetch($sql, '', 'single value');
}

/**
 * read revision date for a corresponding revision and either write data or
 * set action to delete
 *
 * @param array $my_tab
 * @param int $revision_id
 * @return array
 */
function zz_revisions_read_data($my_tab, $revision_id) {
	$sql = 'SELECT record_id, changed_values, rev_action
		FROM /*_PREFIX_*/_revisiondata
		WHERE table_name = "%s"
		AND revision_id = %d
		AND rev_action != "ignore"';
	$sql = sprintf($sql
		, $my_tab['table_name']
		, $revision_id
	);
	$revision_data = wrap_db_fetch($sql, 'record_id');
	if (!$revision_data) return $my_tab;
	foreach ($my_tab as $index => $rec) {
		if (!is_numeric($index)) continue;
		if (!in_array($rec['id']['value'], array_keys($revision_data))) continue;
		$revision = $revision_data[$rec['id']['value']];
		if ($revision['rev_action'] === 'update') {
			$my_tab[$index]['revision'] = json_decode($revision['changed_values'], true);
		} elseif ($revision['rev_action'] === 'delete') {
			$my_tab[0]['action'] = 'delete';
		} elseif ($revision['rev_action'] === 'insert') {
			$my_tab[$index]['revision'] = json_decode($revision['changed_values'], true);
		}
	}
	return $my_tab;
}

/**
 * update a pending revision to historic status
 * hook function
 *
 * @param array $ops
 * @param array $zz_tab
 * @return void
 */
function zz_revisions_historic($ops, $zz_tab) {
	zz_revisions_historic_update($zz_tab[0]['revision_id']);
}

/**
 * update a pending revision to historic status
 *
 * @param int $id_value
 * @return void
 */
function zz_revisions_historic_update($id_value) {
	global $zz_conf;

	$sql = 'UPDATE /*_PREFIX_*/_revisions
		SET rev_status = "historic", last_update = NOW()
		WHERE revision_id = %d';
	$sql = sprintf($sql, $id_value);
	$result = wrap_db_query($sql, $id_value);
	if (!$result) return;
	zz_log_sql($sql, $zz_conf['user'], $id_value);
}

/**
 * return a corresponding URL for a table where a table can be edited
 * defaults to URL in the same folder with table-name instead of table_name
 *
 * @param string $table name of table
 * @return string
 */
function zz_revisions_table_to_url($table) {
	$setting = wrap_get_setting('revisions_table_to_url');
	parse_str($setting, $setting);
	if (!empty($setting[$table])) return $setting[$table];
	if (substr($table, 0, 1) === '/') return $table;
	$table = str_replace('_', '-', $table);
	$table = './'.$table;
	return $table;
}
