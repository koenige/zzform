<?php

/**
 * zzform
 * logging functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/*
 * read logging entries from logging table
 *
 * @param int $start
 * @return array
 */
function zz_logging_read($start) {
	$limit = 0;

	$sql = 'SELECT COUNT(*) FROM %s WHERE log_id >= %d ORDER BY log_id';
	$sql = sprintf($sql, wrap_sql_table('zzform_logging'), $start);
	$logcount = wrap_db_fetch($sql, '', 'single value');
	if ($logcount > 10000) {
		$limit = 10000;
	}

	$sql = 'SELECT * FROM %s WHERE log_id >= %d ORDER BY log_id';
	$sql = sprintf($sql, wrap_sql_table('zzform_logging'), $start);
	if ($limit) $sql .= sprintf(' LIMIT %d', 10000);
	$data = wrap_db_fetch($sql, 'log_id');
	return [$data, $limit];
}

/*
 * add logging entries to logging table
 *
 * @param string $json
 * @return array
 */
function zz_logging_add($json) {
	$json = json_decode($json, true);
	if (!$json) return ['no_json' => 1];

	$first_id = key($json);
	$sql = 'SELECT MAX(log_id) FROM %s';
	$sql = sprintf($sql, wrap_sql_table('zzform_logging'));
	$max_logs = wrap_db_fetch($sql, '', 'single value');
	if ($max_logs + 1 !== $first_id) {
		return ['max_logs' => $max_logs, 'first_id' => $first_id];
	}
	
	// Everything ok, we can import
	$log_template = 'INSERT INTO %s (query, record_id, user, last_update) VALUES (_binary "%s", %s, "%s", "%s")';
	foreach ($json as $line) {
		$success = wrap_db_query($line['query']);
		if (empty($success['id']) AND empty($success['rows']) AND $success !== true) {
			return ['log_id' => $line['log_id'], 'add_error' => 1];
		}
		$sql = sprintf($log_template,
			wrap_sql_table('zzform_logging'), wrap_db_escape($line['query'])
			, ($line['record_id'] ? $line['record_id'] : 'NULL')
			, wrap_db_escape($line['user']), $line['last_update']
		);
		$log_id = wrap_db_query($sql);
		if (empty($log_id['id'])) {
			return ['log_id' => $line['log_id'], 'log_add_error' => 1];
		}
		if ($line['log_id'].'' !== $log_id['id'].'') {
			return ['log_id' => $line['log_id'], 'local_log_id' => $log_id['id']];
		}
	}
	return ['log_id' => $line['log_id'], 'total_count' => count($json)];
}

/*
 * get last log entry from logging table
 *
 * @param void
 * @return array
 */
function zz_logging_last() {
	$sql = 'SELECT * FROM %s ORDER BY log_id DESC LIMIT 1';
	$sql = sprintf($sql, wrap_sql_table('zzform_logging'));
	return wrap_db_fetch($sql);
}

/*
 * get maximum ID value of logging table
 *
 * @param void
 * @return int
 */
function zz_logging_max() {
	$sql = 'SELECT MAX(log_id) FROM %s';
	$sql = sprintf($sql, wrap_sql_table('zzform_logging'));
	return wrap_db_fetch($sql, '', 'single value');
}
