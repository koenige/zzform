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
 * @param string $type name of query from queries.sql
 * @return array
 */
function zz_logging_read($start, $type = 'log_id') {
	$query = sprintf('zzform_logging_%s_read', $type);
	$sql = wrap_sql_query($query);
	$sql = sprintf($sql, $start);
	$data = wrap_db_fetch($sql, 'log_id');
	
	if (count($data) > wrap_setting('zzform_logging_max_read')) {
		$limit = wrap_setting('zzform_logging_max_read');
	} elseif (count($data) === wrap_setting('zzform_logging_max_read')) {
		$query = sprintf('zzform_logging_%s_count', $type);
		$sql = wrap_sql_query($query);
		$sql = sprintf($sql, $start);
		$logcount = wrap_db_fetch($sql, '', 'single value');
		if ($logcount > wrap_setting('zzform_logging_max_read'))
			$limit = wrap_setting('zzform_logging_max_read');
		else
			$limit = 0;
	} else {
		$limit = 0;
	}

	return [$data, $limit];
}

/*
 * add logging entries to logging table, execute queries
 *
 * @param string $json
 * @return array
 */
function zz_logging_add($json) {
	$json = json_decode($json, true);
	if (!$json) return ['no_json' => 1];

	$first_id = key($json);
	$max_logs = zz_logging_max();
	if ($max_logs + 1 !== $first_id) {
		return ['max_logs' => $max_logs, 'first_id' => $first_id];
	}
	
	// Everything ok, we can import
	foreach ($json as $line) {
		$success = wrap_db_query($line['query']);
		if (empty($success['id']) AND empty($success['rows']) AND $success !== true) {
			return ['log_id' => $line['log_id'], 'add_error' => 1];
		}
		$logging = zz_logging_insert($line);
		if (empty($logging['id'])) {
			return ['log_id' => $line['log_id'], 'log_add_error' => 1];
		}
		if ($line['log_id'].'' !== $logging['id'].'') {
			return ['log_id' => $line['log_id'], 'local_log_id' => $logging['id']];
		}
	}
	return ['log_id' => $line['log_id'], 'total_count' => count($json)];
}

/**
 * insert logging entry from other system into logging table
 *
 * @param array $line
 */
function zz_logging_insert($line) {
	$sql = 'INSERT INTO /*_TABLE zzform_logging _*/ (query, record_id, user, last_update)
		VALUES (_binary "%s", %s, "%s", "%s")';
	$sql = sprintf($sql
		, wrap_db_escape($line['query'])
		, ($line['record_id'] ?? 'NULL')
		, wrap_db_escape($line['user'])
		, $line['last_update']
	);
	return wrap_db_query($sql);
}

/*
 * get last log entry from logging table
 *
 * @param void
 * @return array
 */
function zz_logging_last() {
	$sql = 'SELECT * FROM /*_TABLE zzform_logging _*/ ORDER BY log_id DESC LIMIT 1';
	return wrap_db_fetch($sql);
}

/*
 * get maximum ID value of logging table
 *
 * @param void
 * @return int
 */
function zz_logging_max() {
	$sql = 'SELECT MAX(log_id) FROM /*_TABLE zzform_logging _*/';
	return wrap_db_fetch($sql, '', 'single value');
}
