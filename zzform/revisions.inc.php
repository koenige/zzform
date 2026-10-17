<?php 

/**
 * zzform
 * Revision functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2026 Gustaf Mossakowski
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
	$user_id = $_SESSION['user_id'] ?? 'NULL';

	$data = [];
	foreach ($ops['return'] as $index => $table) {
		if ($table['action'] === 'nothing') continue;
		if ($table['action'] === 'delete') {
			$data[] = [
				'table_name' => $table['table_name'],
				'db_table' => $table['table'],
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
			'db_table' => $table['table'],
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
		, (!empty($zz_conf['int']['revisions_url']) ? sprintf('"%s"', $zz_conf['int']['revisions_url']) : 'NULL')
	);
	$rev_id = wrap_db_query($sql);
	if (empty($rev_id['id'])) return [];
	zz_db_log($sql, '', $rev_id['id']);

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
		if ($rows) zz_db_log($sql);
		$open_revisions = [];
	} else {
		$open_revisions = zz_revisions_open($ops['return'][0]['table'], $ops['return'][0]['id_value'], $rev_id['id']);
	}
	zz_revisions_insert_data($data, $rev_id['id'], $open_revisions);
	return [];
}

/**
 * save revision data
 *
 * @param array $data
 * @param int $id
 * @param array $open_revisions
 * @return void
 */
function zz_revisions_insert_data($data, $id, $open_revisions) {
	$sql_rev = 'INSERT INTO /*_PREFIX_*/_revisiondata
		(revision_id, table_name, db_table, record_id, changed_values, complete_values, rev_action)
		VALUES (%d, "%%s", "%%s", %%d, %%s, %%s, "%%s")';
	$sql_rev = sprintf($sql_rev, $id);
	foreach ($data as $line) {
		$ignored = [];
		if (!$line['record_id']) {
			$line['record_id'] = -1; // @todo -2, -3, how?
		}
		if ($line['record_id'] > 0 AND !empty($open_revisions[$line['table_name']][$line['record_id']])) {
			// delete previous revisiondata with rev_action = delete
			// delete parent revision if no other revisiondata exists
			foreach ($open_revisions[$line['table_name']][$line['record_id']] as $open_rev) {
				if ($open_rev['rev_action'] !== 'delete') continue;
				$ignored[] = $open_rev;
			}
		} elseif (!empty($open_revisions[$line['table_name']][$line['record_id']])) {
			// record was added, all previous revisions = historic
			// if action = delete, do not log or log as historic
			
			$ignored = $open_revisions[$line['table_name']][$line['record_id']];
			switch ($line['rev_action']) {
				case 'update': $line['rev_action'] = 'insert'; break;
				case 'delete': $line['rev_action'] = 'ignore'; break; // no record
			}
		}
		foreach ($ignored as $open_rev) {
			if ($open_rev['children'].'' === '1') {
				zz_revisions_historic_update($open_rev['revision_id']);
			} else {
				zz_revisions_ignore_data($open_rev['revisiondata_id']);
			}
		}
		$sql = vsprintf($sql_rev, $line);
		$rev_data_id = wrap_db_query($sql);
		if (empty($rev_data_id['id'])) continue;
		zz_db_log($sql, '', $rev_data_id['id']);
	}
}

/**
 * read revision data for $zz_tab
 *
 * @param array $main_tab = $zz_tab[0]
 * @return array
 */
function zz_revisions_tab($main_tab) {
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
	if (empty($_SESSION)) return [];
	if (!array_key_exists('user_id', $_SESSION)) return [];

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
		ORDER BY revision_id ASC
		LIMIT 1';
	$sql = sprintf($sql, $table, $zz_conf['int']['id']['value']);
	return wrap_db_fetch($sql, '', 'single value');
}

/**
 * read revision date for a corresponding revision and either write data or
 * set action to delete
 *
 * updates and deletes are matched by table and record ID, inserts were
 * assigned to a subtable beforehands (see zz_revisions_insert_tabs())
 * and go into the records without ID
 *
 * @param array $my_tab
 * @param int $revision_id
 * @return array
 */
function zz_revisions_read_data($my_tab, $revision_id) {
	$rows = zz_revisions_rows($revision_id);
	if (!$rows) return $my_tab;

	$inserts = [];
	foreach ($my_tab['revision_inserts'] ?? [] as $revisiondata_id)
		$inserts[] = $rows[$revisiondata_id];

	foreach ($my_tab as $index => $rec) {
		if (!is_numeric($index)) continue;
		if (!$rec['id']['value']) {
			$row = array_shift($inserts);
			if (!$row) continue;
			$my_tab[$index]['revision'] = json_decode($row['changed_values'], true);
			continue;
		}
		$row = zz_revisions_record_row($rows, $my_tab, $rec['id']['value']);
		if (!$row) continue;
		if ($row['rev_action'] === 'delete') {
			$my_tab[0]['action'] = 'delete';
			continue;
		}
		$my_tab[$index]['revision'] = json_decode($row['changed_values'], true);
	}
	return $my_tab;
}

/**
 * get revision data for an existing record of a table
 *
 * @param array $rows
 * @param array $my_tab
 * @param int $record_id
 * @return array
 */
function zz_revisions_record_row($rows, $my_tab, $record_id) {
	foreach ($rows as $row) {
		if ($row['rev_action'] === 'insert') continue;
		if ($row['record_id'].'' !== $record_id.'') continue;
		if ($row['table_name'] === $my_tab['table_name']) return $row;
		if (zz_revisions_row_table($row) === $my_tab['table']) return $row;
	}
	return [];
}

/**
 * assign inserted records of a revision to the subtables of the current form
 *
 * the layout of subtables may differ from the form the revision was made
 * with; a subtable with the same name is used, otherwise the first subtable
 * of the same table whose field definitions accept the values
 *
 * @param int $revision_id
 * @param array $tabs list of subtables, indexed by tab
 *		string 'table', string 'table_name', array 'fields'
 * @return array list of revisiondata IDs, indexed by tab
 */
function zz_revisions_insert_tabs($revision_id, $tabs) {
	$assigned = [];
	foreach (zz_revisions_rows($revision_id) as $revisiondata_id => $row) {
		if ($row['rev_action'] !== 'insert') continue;
		$tab = zz_revisions_insert_tab($row, $tabs);
		if ($tab === NULL) {
			zz_error_log([
				'_msg' => 'A pending change for %s cannot be shown in this form.',
				'_msg_values' => [zz_nice_tablenames(zz_revisions_row_table($row))],
				'level' => E_USER_NOTICE
			]);
			continue;
		}
		$assigned[$tab][] = $revisiondata_id;
	}
	return $assigned;
}

/**
 * get subtable for an inserted record
 *
 * @param array $row
 * @param array $tabs
 * @return int
 */
function zz_revisions_insert_tab($row, $tabs) {
	foreach ($tabs as $tab => $def)
		if ($def['table_name'] === $row['table_name']) return $tab;

	$values = json_decode($row['complete_values'] ?? $row['changed_values'], true) ?? [];
	$table = zz_revisions_row_table($row);
	foreach ($tabs as $tab => $def) {
		if ($def['table'] !== $table) continue;
		if (!zz_revisions_fields_accept($def['fields'], $values)) continue;
		return $tab;
	}
	return NULL;
}

/**
 * check if field definitions accept the values of a record
 *
 * fields with a fixed value must have this value, selects must offer it
 *
 * @param array $fields
 * @param array $values
 * @return bool
 */
function zz_revisions_fields_accept($fields, $values) {
	foreach ($fields as $field) {
		if (empty($field['field_name'])) continue;
		if (!array_key_exists($field['field_name'], $values)) continue;
		if (!zz_revisions_field_accepts($field, $values[$field['field_name']])) return false;
	}
	return true;
}

/**
 * check if a field definition accepts a value
 *
 * @param array $field
 * @param mixed $value
 * @return bool
 */
function zz_revisions_field_accepts($field, $value) {
	if (isset($field['value'])) return $field['value'].'' === $value.'';
	if (($field['type'] ?? '') !== 'select') return true;
	if ($value === '' OR $value === NULL) return true;
	if (!empty($field['enum'])) return in_array($value, $field['enum']);
	if (empty($field['sql'])) return true;
	return in_array($value.'', zz_revisions_select_keys($field['sql']), true);
}

/**
 * get keys (first column) of a select query
 *
 * @param string $sql
 * @return array
 */
function zz_revisions_select_keys($sql) {
	static $keys = [];
	if (array_key_exists($sql, $keys)) return $keys[$sql];
	$keys[$sql] = [];
	foreach (zz_db_fetch($sql, '_dummy_id_', 'numeric') as $line)
		$keys[$sql][] = reset($line).'';
	return $keys[$sql];
}

/**
 * get all lines of a revision
 *
 * @param int $revision_id
 * @return array
 */
function zz_revisions_rows($revision_id) {
	static $rows = [];
	if (array_key_exists($revision_id, $rows)) return $rows[$revision_id];
	$sql = 'SELECT revisiondata_id, table_name, db_table, record_id
			, changed_values, complete_values, rev_action
		FROM /*_PREFIX_*/_revisiondata
		WHERE revision_id = %d
		AND rev_action != "ignore"
		ORDER BY revisiondata_id';
	$sql = sprintf($sql, $revision_id);
	$rows[$revision_id] = wrap_db_fetch($sql, 'revisiondata_id');
	return $rows[$revision_id];
}

/**
 * get table of a revision line
 *
 * older lines without db_table: table name of subtable without
 * numerical suffix, e. g. contactdetails_585 = contactdetails
 *
 * @param array $row
 * @return string
 */
function zz_revisions_row_table($row) {
	if (!empty($row['db_table'])) return $row['db_table'];
	$table = preg_replace('/_\d+$/', '', $row['table_name']);
	if (!str_starts_with($table, '/*_PREFIX_*/')) $table = '/*_PREFIX_*/'.$table;
	return wrap_db_prefix($table);
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
	$sql = 'UPDATE /*_PREFIX_*/_revisions
		SET rev_status = "historic", last_update = NOW()
		WHERE revision_id = %d';
	$sql = sprintf($sql, $id_value);
	$result = wrap_db_query($sql, $id_value);
	if (!$result) return;
	zz_db_log($sql, '', $id_value);
}

/**
 * return a corresponding URL for a table where a table can be edited
 * defaults to URL in the same folder with table-name instead of table_name
 *
 * @param array $fields
 *		string revisions_url
 *		int main_record_id
 * @return string
 */
function zz_revisions_table_to_url($fields) {
	$url = $fields['revisions_url'];
	$qs = '?revise=%d&nolist&referer=%s';
	$qs = sprintf($qs, $fields['main_record_id'], urlencode(wrap_setting('request_uri')));
	
	// get path
	$setting = wrap_setting('zzform_revisions_table_to_url['.$url.']');
	if ($setting) {
		$path = $setting;
	} elseif (str_starts_with($url, '/')) {
		$path = $url;
	} else {
		$path = str_replace('_', '-', $url);
		$path = './'.$path;
	}
	$path .= $qs;

	// parameter?
	$setting = wrap_setting('zzform_revisions_table_to_query['.$url.']');
	if (!$setting) return $path;

	$sql = sprintf(wrap_sql_query($setting), $fields['main_record_id']);
	$result = wrap_db_fetch($sql, '', 'single value');
	if (!$result) return $path;

	// we need to know what to do with the parameter
	$setting = wrap_setting('zzform_revisions_table_to_qs['.$url.']');
	if (!$setting) return $path;
	
	$path = sprintf('%s&%s', $path, sprintf($setting, $result));
	return $path;
}

/**
 * get all open revisions for detail records of this record
 *
 * @param string $main_table_name
 * @param int $main_record_id
 * @param int $revision_id
 * @return array
 */
function zz_revisions_open($main_table_name, $main_record_id, $revision_id) {
	$sql = 'SELECT revisiondata_id, revision_id, table_name, record_id, rev_action
			, (SELECT COUNT(*) FROM /*_PREFIX_*/_revisiondata rd
				WHERE rd.revision_id = /*_PREFIX_*/_revisiondata.revision_id
			) AS children
		FROM /*_PREFIX_*/_revisiondata
		LEFT JOIN /*_PREFIX_*/_revisions USING (revision_id)
		WHERE rev_status = "pending" AND main_table_name = "%s"
		AND main_record_id = %d AND revision_id < %d
		AND table_name != main_table_name';
	$sql = sprintf($sql, $main_table_name, $main_record_id, $revision_id);
	return wrap_db_fetch($sql, ['table_name', 'record_id', 'revisiondata_id']);
}

/**
 * remove a line from _revisiondata by setting it to ignore
 *
 * @param int $id_value
 * @return bool
 */
function zz_revisions_ignore_data($id_value) {
	$sql = 'UPDATE /*_PREFIX_*/_revisiondata
		SET rev_action = "ignore"
		WHERE revisiondata_id = %d';
	$sql = sprintf($sql, $id_value);
	$result = wrap_db_query($sql);
	if (!$result) return false;
	zz_db_log($sql, '', $id_value);
	return true;
}
