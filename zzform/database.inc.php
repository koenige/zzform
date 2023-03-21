<?php 

/**
 * zzform
 * Database functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * Contents:
 * D - Database functions (common functions)
 * D - Database functions (MySQL-specific functions)
 *		zz_db_*()
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/*
 * --------------------------------------------------------------------
 * D - Database functions (common functions)
 * --------------------------------------------------------------------
 */

/** 
 * Logs SQL operation in logging table in database
 * 
 * @param string $sql = SQL Query
 * @param string $user = Active user
 * @param int $record_id = record ID, optional, if ID shall be logged
 * @return bool = operation successful or not
 */
function zz_log_sql($sql, $user, $record_id = false) {
	global $zz_conf;

	$sql = trim($sql);
	if ($sql === 'SELECT 1') return false;
	// check if zzform() set db_main, test against !empty because need not be set
	// (zz_log_sql() might be called from outside zzform())
	$logging_table = wrap_sql_table('zzform_logging');
	if (!strstr($logging_table, '.') AND !empty($zz_conf['int']['db_main'])) {
		$logging_table = $zz_conf['int']['db_main'].'.'.$logging_table;
	}
	if (is_array($record_id)) $record_id = NULL;
	if (wrap_setting('zzform_logging_id') AND $record_id) {
		$sql = sprintf(
			'INSERT INTO %s (query, user, record_id) VALUES (_binary "%s", "%s", %d)',
			$logging_table, wrap_db_escape($sql), $user, $record_id
		);
	} else {
		// without record_id, only for backwards compatibility
		$sql = sprintf(
			'INSERT INTO %s (query, user) VALUES (_binary "%s", "%s")',
			$logging_table, wrap_db_escape($sql), $user
		);
	}
	$result = mysqli_query($zz_conf['db_connection'], $sql);
	if (!$result) return false;
	else return true;
	// die if logging is selected but does not work?
}

/**
 * checks if SQL queries use table prefixes and replace them with current
 * table prefix from configuration
 * syntax for prefixes is SQL comment / *PREFIX* /
 *
 * @param array $vars definition of table/configuration
 * @return array $vars (definition with replacements for table prefixes)
 */
function zz_sql_prefix($vars) {
	array_walk_recursive($vars, 'zz_sql_prefix_change_zz');
	return $vars;
}

/**
 * checks each key if it might potentially have a table prefix
 * and replaces all prefixes of its value
 */
function zz_sql_prefix_change(&$item) {
	global $zz_conf;

	if (!is_string($item)) return false;
	$prefix = '/*_PREFIX_*/';
	if (!strstr($item, $prefix)) return false;
	$item = str_replace($prefix, $zz_conf['prefix'], $item);
	return true;
}
 
/**
 * checks each key if it might potentially have a table prefix
 * and replaces all prefixes of its value
 *
 * @param mixed $item
 * @param string $key
 * @global array $zz_conf
 * @return void
 * @todo remove this function and do the replacement in zz_db_fetch() instead
 * for this to happen, all functions getting database names from table etc.
 * must be rewritten
 */
function zz_sql_prefix_change_zz(&$item, $key) {
	$success = zz_sql_prefix_change($item);
	if (!$success) return false;
	
	// numeric keys are okay as well
	// @todo check if we can exclude them (sqlextra)
	if (is_numeric($key)) return false;

	// $zz['conditions'][n]['add']['sql']
	// $zz['conditions'][n]['having']
	// $zz['conditions'][n]['sql']
	// $zz['conditions'][n]['where']
	// $zz['fields'][n]['conf_identifier']['where'] 
	// $zz['fields'][n]['path_sql']
	// $zz['fields'][n]['search']
	// $zz['fields'][n]['search_between']
	// $zz['fields'][n]['set_sql']
	// $zz['fields'][n]['sql']
	// $zz['fields'][n]['sqlorder']
	// $zz['fields'][n]['sql_not_unique']
	// $zz['fields'][n]['sql_password_check']
	// $zz['fields'][n]['subselect']['sql']
	// $zz['fields'][n]['upload_sql']
	// $zz['fields'][n]['image'][n]['options_sql']
	// $zz['fields'][n]['image'][n]['source_path_sql']
	// $zz['sql']
	// $zz['sqlrecord']
	// $zz['sqlorder']
	// $zz['table']
	// $zz['subtitle'][field]['sql']
	// $zz['filter'][n]['sql']
	// $zz['filter'][n]['where']
	// $zz['filter'][n]['sql_join']
	$sql_fields = [
		'sql', 'having', 'where', 'path_sql', 'search', 'search_between',
		'set_sql', 'sqlorder', 'sql_not_unique', 'sql_password_check',
		'upload_sql', 'options_sql', 'source_path_sql', 'table',
		'id_field_name', 'display_field', 'key_field_name', 'order',
		'foreign_key_field_name', 'sqlcount', 'sqlextra', 'geocode_sql',
		'min_records_sql', 'max_records_sql', 'sqlrecord', 'sql_join',
		'dependent_on_add_sql', 'sql_association'
	];
	if (in_array($key, $sql_fields)) return false;
	if (function_exists('wrap_error')) {
		wrap_error(sprintf('Table prefix for key %s (item %s) replaced'
		.' which was not anticipated', $key, $item), E_USER_NOTICE);
	}
}

/**
 * counts number of records that will be caught by current SQL query
 *
 * @param string $sql
 * @param string $id_field
 * @return int $lines
 */
function zz_sql_count_rows($sql, $id_field = '') {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$sql = trim($sql);
	if (!$id_field) {
		$lines = zz_db_fetch($sql, '', 'single value');
	} elseif (substr($sql, 0, 15) !== 'SELECT DISTINCT'
		AND !stristr($sql, 'GROUP BY') AND !stristr($sql, 'HAVING')) {
		// if it's not a SELECT DISTINCT, we can use COUNT, that's faster
		// GROUP BY, FORCE INDEX also do not work with COUNT
		$sql = wrap_edit_sql($sql, 'ORDER BY', '_dummy_', 'delete');
		$sql = wrap_edit_sql($sql, 'FORCE INDEX', '_dummy_', 'delete');
		$sql = wrap_edit_sql($sql, 'SELECT', 'COUNT(*)', 'replace');
		// unnecessary JOINs may slow down query
		// remove them in case no WHERE, HAVING or GROUP BY is set
		$sql = wrap_edit_sql($sql, 'JOIN', '_dummy_', 'delete');
		$lines = zz_db_fetch($sql, '', 'single value');
	} else {
		$lines = zz_db_fetch($sql, $id_field, 'count');
	}
	if (!$lines) $lines = 0;
	return zz_return($lines);
}

/*
 * --------------------------------------------------------------------
 * D - Database functions (MySQL-specific functions)
 * --------------------------------------------------------------------
 */

/**
 * sets database name (globally) and checks if a database by that name exists
 * in case database name is glued to table name, returns table name without db
 *
 * @param string $table table name, might include database name
 * @global array $zz_conf - 'db_name' might be changed or set
 * @return string $table - name of main table
 */
function zz_db_connection($table) {
	global $zz_conf;

	// get current db to SELECT it again before exiting
	// might be that there was no database connection established so far
	// therefore the @, but it does not matter because we simply want to
	// revert to the current database after exiting this script
	$result = @mysqli_query($zz_conf['db_connection'], 'SELECT DATABASE()');
	if ($result) {
		mysqli_data_seek($result, 0);
		$line = mysqli_fetch_row($result);
		$zz_conf['int']['db_current'] = reset($line);
	} else {
		$zz_conf['int']['db_current'] = '';
	}
	// main database normally is the same db that zzform() uses for its
	// operations, but if you use several databases, this is the one which
	// is the main db, i. e. the one that will be used if no other database
	// name is specified
	$zz_conf['int']['db_main'] = false;

	// get db_name.
	// 1. best way: put it in zz_conf['db_name']
	if (!empty($zz_conf['db_name'])) {
		$db = zz_db_select($zz_conf['db_name']);
		if (!$db) {
			zz_error_log([
				'db_msg' => mysqli_error($zz_conf['db_connection']),
				'query' => 'SELECT DATABASE("'.$zz_conf['db_name'].'")',
				'level' => E_USER_ERROR
			]);
			$zz_conf['db_name'] = '';
			return false;
		}
	// 2. alternative: use current database
	} else {
		$result = mysqli_query($zz_conf['db_connection'], 'SELECT DATABASE()');
		if (mysqli_error($zz_conf['db_connection'])) {
			zz_error_log([
				'db_msg' => mysqli_error($zz_conf['db_connection']),
				'query' => 'SELECT DATABASE()',
				'level' => E_USER_ERROR
			]);
			return false;
		}
		mysqli_data_seek($result, 0);
		$line = mysqli_fetch_row($result);
		$zz_conf['db_name'] = reset($line);
	}

	// 3. alternative plus foreign db: put it in zz['table']
	if (preg_match('~(.+)\.(.+)~', $table, $db_name)) { // db_name is already in zz['table']
		if ($zz_conf['db_name'] AND $zz_conf['db_name'] !== $db_name[1]) {
			// this database is different from main database, so save it here
			// for later
			$zz_conf['int']['db_main'] = $zz_conf['db_name'];
		} elseif (!$zz_conf['db_name']) { 
			// no database selected, get one, quick!
			$dbname = zz_db_select($db_name[1]);
			if (!$dbname) {
				zz_error_log([
					'db_msg' => mysqli_error($zz_conf['db_connection']),
					'query' => 'SELECT DATABASE("'.$db_name[1].'")',
					'level' => E_USER_ERROR
				]);
				$zz_conf['db_name'] = '';
				return false;
			}
		}
		$zz_conf['db_name'] = $db_name[1];
		$table = $db_name[2];
	}

	if (empty($zz_conf['db_name'])) {
		zz_error_log([
			'msg_dev' => 'Please set the variable <code>$zz_conf[\'db_name\']</code>.'
				.' It has to be set to the main database name used for zzform.',
			'level' => E_USER_ERROR
		]);
		return false;
	}
	return $table;
}

/**
 * Fetches records from database and returns array
 * identical to wrap_db_fetch, more or less
 * 
 * - without $id_field_name: expects exactly one record and returns
 * the values of this record as an array
 * - with $id_field_name: uses this name as unique key for all records
 * and returns an array of values for each record under this key
 * - with $id_field_name and $array_format = "key/value": returns key/value-pairs
 * - with $id_field_name = 'dummy' and $array_format = "single value": returns
 * just first value as an array e. g. [3] => 3
 * @param string $sql SQL query string
 * @param string $id_field_name optional, if more than one record will be 
 *	returned: required; field_name for array keys
 *  if it's an array with two strings, this will be used to construct a 
 *  hierarchical array for the returned array with both keys
 * @param string $format optional, currently implemented
 *  'count' = returns count of rows
 *	'id as key' = returns [$id_field_value => true]
 *	"key/value" = returns [$key => $value]
 *	"single value" = returns $value
 *	"object" = returns object
 *	"numeric" = returns lines in numerical array [0 ... n] instead of using field ids
 * @param string $info (optional) information about where this query was called
 * @param int $error_type let's you set error level, default = E_USER_ERROR
 * @return array with queried database content
 * @todo give a more detailed explanation of how function works
 */
function zz_db_fetch($sql, $id_field_name = false, $format = false, $info = false, $error_type = E_USER_ERROR) {
	global $zz_conf;
	if (!empty($zz_conf['debug']) AND function_exists('wrap_error')) {
		$time = microtime(true);
	}
	$lines = [];
	$error = false;
	$result = mysqli_query($zz_conf['db_connection'], $sql);
	if ($result) {
		if (!$id_field_name) {
			// only one record
			if (mysqli_num_rows($result) === 1) {
	 			if ($format === 'single value') {
					mysqli_data_seek($result, 0);
					$lines = mysqli_fetch_row($result);
					$lines = reset($lines);
	 			} elseif ($format === 'object') {
					$lines = mysqli_fetch_object($result);
				} else {
					$lines = mysqli_fetch_assoc($result);
				}
			}
 		} elseif (is_array($id_field_name) AND mysqli_num_rows($result)) {
			if ($format === 'object') {
				while ($line = mysqli_fetch_object($result)) {
					if (count($id_field_name) === 3) {
						if ($error = zz_db_field_in_query($line, $id_field_name, 3)) break;
						$lines[$line->$id_field_name[0]][$line->$id_field_name[1]][$line->$id_field_name[2]] = $line;
					} else {
						if ($error = zz_db_field_in_query($line, $id_field_name, 2)) break;
						$lines[$line->$id_field_name[0]][$line->$id_field_name[1]] = $line;
					}
				}
 			} else {
 				// default or unknown format
				while ($line = mysqli_fetch_assoc($result)) {
		 			if ($format === 'single value') {
						// just get last field, make sure that it's not one of the id_field_names!
		 				$values = array_pop($line);
		 			} else {
		 				$values = $line;
		 			}
					if (count($id_field_name) === 4) {
						if ($error = zz_db_field_in_query($line, $id_field_name, 4)) break;
						$lines[$line[$id_field_name[0]]][$line[$id_field_name[1]]][$line[$id_field_name[2]]][$line[$id_field_name[3]]] = $values;
					} elseif (count($id_field_name) === 3) {
						if ($error = zz_db_field_in_query($line, $id_field_name, 3)) break;
						$lines[$line[$id_field_name[0]]][$line[$id_field_name[1]]][$line[$id_field_name[2]]] = $values;
					} else {
						if ($format === 'key/value') {
							if ($error = zz_db_field_in_query($line, $id_field_name, 2)) break;
							$lines[$line[$id_field_name[0]]] = $line[$id_field_name[1]];
						} elseif ($format === 'numeric') {
							if ($error = zz_db_field_in_query($line, $id_field_name, 1)) break;
							$lines[$line[$id_field_name[0]]][] = $values;
						} else {
							if ($error = zz_db_field_in_query($line, $id_field_name, 2)) break;
							$lines[$line[$id_field_name[0]]][$line[$id_field_name[1]]] = $values;
						}
					}
				}
			}
 		} elseif (mysqli_num_rows($result)) {
 			if ($format === 'count') {
 				$lines = mysqli_num_rows($result);
 			} elseif ($format === 'single value') {
 				// you can reach this part here with a dummy id_field_name
 				// because no $id_field_name is needed!
				while ($line = mysqli_fetch_array($result)) {
					$lines[$line[0]] = $line[0];
				}
 			} elseif ($format === 'id as key') {
				while ($line = mysqli_fetch_array($result)) {
					if ($error = zz_db_field_in_query($line, $id_field_name)) break;
					$lines[$line[$id_field_name]] = true;
				}
 			} elseif ($format === 'key/value') {
 				// return array in pairs
				while ($line = mysqli_fetch_array($result)) {
					$lines[$line[0]] = $line[1];
				}
			} elseif ($format === 'object') {
				while ($line = mysqli_fetch_object($result)) {
					if ($error = zz_db_field_in_query($line, $id_field_name)) break;
					$lines[$line->$id_field_name] = $line;
				}
			} elseif ($format === 'numeric') {
				while ($line = mysqli_fetch_assoc($result))
					$lines[] = $line;
 			} else {
 				// default or unknown format
				while ($line = mysqli_fetch_assoc($result)) {
					if ($error = zz_db_field_in_query($line, $id_field_name)) break;
					$lines[$line[$id_field_name]] = $line;
				}
			}
		}
	} else $error = true;
	if ($error AND $error !== true) $info .= $error;
	if ($zz_conf['modules']['debug']) zz_debug('sql (rows: '
		.($result ? mysqli_num_rows($result) : 0).')'.($info ? ': '.$info : ''), $sql);
	if (!empty($zz_conf['debug']) AND function_exists('wrap_error')) {
		// @todo: check if it's easier to do it with zz_error()
		$time = microtime(true) - $time;
		wrap_error('SQL query in '.$time.' - '.$sql, E_USER_NOTICE);
	}
	if ($error) {
		if ($zz_conf['modules']['debug']) {
			global $zz_debug;
			$current = end($zz_debug[$zz_conf['id']]['function']);
		} else {
			$current['function'] = '';
		}
		$msg_dev = 'Error in SQL query';
		$msg_dev_args = [];
		if (!empty($current['function'])) {
			$msg_dev .= ' in function %s';
			$msg_dev_args[] = $current['function'];
		}
		if ($info) {
			$msg_dev .= ' - %s.';
			$msg_dev_args[] = $info;
		}

		zz_error_log([
			'msg_dev' => $msg_dev,
			'msg_dev_args' => $msg_dev_args,
			'db_msg' => mysqli_error($zz_conf['db_connection']), 
			'query' => $sql,
			'level' => $error_type,
			'status' => 503
		]);
		zz_error();
		return [];
	}
	return $lines;
}

/**
 * checks whether field_name is in record
 *
 * @param array $line
 * @param string $id_field_name
 * @param int $count if it's an array, no. of id_field_names
 * @return bool true = error_message; false: everything ok
 */
function zz_db_field_in_query($line, $id_field_name, $count = 0) {
	$missing_fields = [];
	if (!$count)
		if (!in_array($id_field_name, array_keys($line))) {
			$missing_fields[] = $id_field_name;
		}
	for ($count; $count; $count--) {
		if (!in_array($id_field_name[($count-1)], array_keys($line))) {
			$missing_fields[] = $id_field_name[($count-1)];
		}
	}
	if ($missing_fields) {
		return sprintf(zz_text('Field <code>%s</code> is missing in SQL query')
			, implode(', ', $missing_fields));
	}
	return false;
}

/**
 * Change database content via INSERT, DELETE or UPDATE
 *
 * @param string $sql
 * @param int $id
 * @global array $zz_conf
 * @return array $db
 *		'action' (false = fail, 'nothing', 'insert', update', 'delete'), 
 *		'id_value', 'error', ...
 */
function zz_db_change($sql, $id = false) {
	global $zz_conf;
	if (!empty($zz_conf['debug'])) {
		$time = microtime(true);
	}

	// initialize $db
	$db = [];
	$db['action'] = 'nothing';

	// write back ID value if it's there
	$db['id_value'] = $id;
	// dummy SQL means nothing will be done
	if ($sql === 'SELECT 1') return $db;
	
	$statement = zz_db_statement($sql);
	// check if statement is allowed
	$allowed_statements = [
		'INSERT', 'DELETE', 'UPDATE', 'CREATE TABLE', 'ALTER TABLE',
		'CREATE VIEW', 'ALTER VIEW', 'SET'
	];
	$no_rows_affected = [
		'CREATE TABLE', 'ALTER TABLE', 'CREATE VIEW', 'ALTER VIEW', 'SET'
	];
	if (!in_array($statement, $allowed_statements)) {
		$db['action'] = '';
		$db['error'] = [
			'query' => $sql,
			'msg_dev' => 'Statement not supported: %s',
			'msg_dev_args' => [$statement]
		];
		return $db;
	}

	// revisions only
	if (!empty($zz_conf['int']['revisions_only'])) {
		$db['action'] = $statement;
		$db['id_value'] = -1; // @todo allow -2, -3
		return $db;
	}

	// check
	$result = mysqli_query($zz_conf['db_connection'], $sql);
	if ($result) {
		if (in_array($statement, $no_rows_affected)) {
			$db['action'] = strtolower($statement);
			if (wrap_setting('zzform_logging'))
				zz_log_sql($sql, $zz_conf['user'], $db['id_value']);
		} elseif (!mysqli_affected_rows($zz_conf['db_connection'])) {
			$db['action'] = 'nothing';
		} else {
			$db['rows'] = mysqli_affected_rows($zz_conf['db_connection']);
			$db['action'] = strtolower($statement);
			if ($db['action'] === 'insert') // get ID value
				$db['id_value'] = mysqli_insert_id($zz_conf['db_connection']);
			// Logs SQL Query, must be after insert_id was checked
			if (wrap_setting('zzform_logging') AND $db['rows'])
				zz_log_sql($sql, $zz_conf['user'], $db['id_value']);
		}
		$warnings = zz_db_fetch('SHOW WARNINGS', '_dummy_', 'numeric');
		foreach ($warnings as $warning) {
			zz_error_log([
				'msg_dev' => 'MySQL reports a problem.',
				'query' => $sql,
				'db_msg' => $warning['Level'].': '.$warning['Message'],
				'level' => E_USER_WARNING
			]);
		}
	} else { 
		// something went wrong, but why?
		$db['action'] = '';
		$db['error'] = [
			'query' => $sql,
			'db_msg' => mysqli_error($zz_conf['db_connection']),
			'db_errno' => mysqli_errno($zz_conf['db_connection'])
		];
	}
	if (!empty($zz_conf['debug']) AND function_exists('wrap_error')) {
		// @todo: check if it's easier to do it with zz_error()
		$time = microtime(true) - $time;
		wrap_error('SQL query in '.$time.' - '.$sql, E_USER_NOTICE);
	}
	return $db;	
}

/**
 * get SQL statement from query
 *
 * @param string $sql
 * @return string
 */
function zz_db_statement($sql) {
	// get rid of extra whitespace, just to check statements
	$sql_ws = preg_replace('~\s~', ' ', trim($sql));
	$tokens = explode(' ', $sql_ws);
	$multitokens = [
		'UNION', 'CREATE', 'DROP', 'ALTER', 'RENAME', 'TRUNCATE', 'LOAD'
	];
	if (in_array($tokens[0], $multitokens)) {
		$keyword = $tokens[0].' '.$tokens[1];
	} else {
		$keyword = $tokens[0];
	}
	return strtoupper($keyword);
}

/**
 * get table and database name
 *
 * @param string $table
 * @param string $db_name (optional, use if db_name is not part of $table)
 * @return array
 */
function zz_db_table($table, $db_name = false) {
	global $zz_conf;
	if (strstr($table, '.')) {
		$table = explode('.', $table);
		$my['db_name'] = $table[0];
		$my['table'] = $table[1];
	} else {
		$my['db_name'] = $db_name ? $db_name : $zz_conf['db_name'];
		$my['table'] = $table;
	}
	return $my;	
}

/**
 * puts backticks around database and table name
 *
 * @param string $db_table = db_name, table name or both concatenated
 *		with a dot
 * @return string $db_table `database`.`table` or `database` or `table`
 */
function zz_db_table_backticks($db_table) {
	global $zz_conf;
	$db_table = explode('.', $db_table);
	if (count($db_table) === 1) {
		array_unshift($db_table, $zz_conf['db_name']);
	}
	foreach ($db_table as $index => $table) {
		if (substr($table, 0, 1) === '`' AND substr($table, -1) === '`') continue;
		$db_table[$index] = sprintf('`%s`', $table);
	}
	$db_table = implode('.', $db_table);
	return $db_table;
}

/** 
 * checks maximum field length in MySQL database table
 * 
 * @param string $field	field name
 * @param string $db_table	table name [i. e. db_name.table]
 * @return maximum length of field or false if no field length is set
 */
function zz_db_field_maxlength($field, $type, $db_table) {
	if (!$field) return false;
	// just if it's a field with a field_name
	// for some field types it makes no sense to check for maxlength
	$dont_check = [
		'image', 'display', 'timestamp', 'hidden', 'foreign_key', 'select',
		'id', 'date', 'time', 'option', 'ip'
	];
	if (in_array($type, $dont_check)) return false;

	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$maxlength = false;
	$field_def = zz_db_columns($db_table, $field);
	if ($field_def) {
		preg_match('/\((\d+)\)/s', $field_def['Type'], $my_result);
		if (isset($my_result[1])) $maxlength = $my_result[1];
		else {
			// from MySQL 8.0.19, there are no default lengths for ints
			$typed = explode(' ', $field_def['Type']);
			if (str_ends_with($typed[0], 'int')) {
				$maxlength = zz_db_int_length($typed);
			}
		}
	}
	if ($zz_conf['modules']['debug']) zz_debug($type.($maxlength ? '-'.$maxlength : ''));
	return zz_return($maxlength);
}

/**
 * get integer length per field 
 * default field lengths are not stored in the table definition from MySQL 8 on
 *
 * @param array
 * @return int
 */
function zz_db_int_length($typed) {
	switch ($typed[0]) {
	case 'tinyint':
		$length = 3;
		if (!empty($typed[1]) AND $typed[1] === 'unsigned') $length++;
		break;
	case 'smallint':
		$length = 5;
		if (!empty($typed[1]) AND $typed[1] === 'unsigned') $length++;
		break;
	case 'mediumint':
		$length = 8; break;
	case 'int':
		$length = 10;
		if (!empty($typed[1]) AND $typed[1] === 'unsigned') $length++;
		break;
	case 'bigint':
		$length = 20; break;
	}
	return $length;
}

/**
 * gets table definition from database to do further checks
 *
 * @param string $db_table
 * @param string $field (optional)
 * @return array definition for whole table or just field
 */
function zz_db_columns($db_table, $field = false) {
	static $columns;
	static $max_integers;
	if (!$db_table) return [];
	if (empty($max_integers)) {
		$sql = 'SELECT ~0 as bigint_unsigned
			, ~0 >> 32 as int_unsigned
			, ~0 >> 40 as mediumint_unsigned
			, ~0 >> 48 as smallint_unsigned
			, ~0 >> 56 as tinyint_unsigned
			, ~0 >> 1  as bigint_signed
			, ~0 >> 33 as int_signed
			, ~0 >> 41 as mediumint_signed
			, ~0 >> 49 as smallint_signed
			, ~0 >> 57 as tinyint_signed';
		$max_integers = zz_db_fetch($sql);
	}
	$db_table = zz_db_table_backticks($db_table);
	if (!isset($columns[$db_table])) {
		$sql = sprintf('SHOW FULL COLUMNS FROM %s', $db_table);
		$columns[$db_table] = zz_db_fetch($sql, 'Field', false, false, E_USER_WARNING);
		foreach ($columns[$db_table] as $index => $my_field) {
			preg_match('/(.*int)\(*\d*\)* (.+signed)/', $my_field['Type'], $fieldtype);
			if (empty($fieldtype[1]) OR empty($fieldtype[2])) {
				preg_match('/^(.*int)\(*\d*\)*$/', $my_field['Type'], $fieldtype);
				if (!$fieldtype) continue;
				if ($fieldtype[0] === 'point') continue;
				$fieldtype[2] = 'signed';
			}
			$columns[$db_table][$index]['max_int_value'] = $max_integers[$fieldtype[1].'_'.$fieldtype[2]];
		}
	}
	if ($field) {
		if (!empty($columns[$db_table][$field])) {
			return $columns[$db_table][$field];
		} else {
			return [];
		}
	}
	return $columns[$db_table];
}

/**
 * Outputs error text depending on mySQL error number
 *
 * @param int $errno mySQL error number
 * @return string $msg Error message
 */
function zz_db_error($errno) {
	switch($errno) {
	case 1062:
		$msg = zz_text('Duplicate entry');
		/*
			@todo:
			1. get table_name
			2. parse: Duplicate entry '1-21' for key 2: (e.g. with preg_match)
			$indices = false;
			$sql = 'SHOW INDEX FROM ...';
			$keys = zz_db_fetch($sql, '...')
			if ($keys) {
				// 3. get required key, field_names
			}
			// 4. get title-values from zz['field'], display them
			// 5. show wrong values, if type select: show values after select ...
		*/
		break;
	default:
		$msg = zz_text('Database error');
	}
	return $msg;
}

/**
 * Checks whether a database field might be NULL or not
 *
 * @param string $field field name
 * @param string $db_table database name.table name
 * @return bool true: might be null, false: must not be null
 */
function zz_db_field_null($field, $db_table) {
	$line = zz_db_columns($db_table, $field);
	if ($line AND $line['Null'] === 'YES') return true;
	else return false;
}

/**
 * prefix different charset if necessary for LIKE
 *
 * @param string $type 'search', 'reselect', 'xhr'
 * @param array $field
 *		'character_set'
 * @param string $db_table (optional, for search)
 * @param int $index (optional, for reselect and xhr)
 * @return string
 */
function zz_db_field_collation($type, $field, $db_table = '', $index = 0) {
	global $zz_conf;
	
	$charset = '';
	$collate_fieldname = '';
	switch ($type) {
	case 'search':
		if (isset($field['search'])) {
			$collate_fieldname = $field['search'];
		} elseif (isset($field['display_field'])) {
			$collate_fieldname = $field['display_field'];
		} elseif (!empty($field['field_name'])) {
			$collate_fieldname = $field['field_name'];
		}
		$error_msg = ' This field will be excluded from search.';
		if (isset($field['character_set'])) $charset = $field['character_set'];
		$tables[] = $db_table;
		break;
	case 'reselect':
	case 'xhr':
		if ($type === 'xhr') {
			$collate_fieldname = $field['field_name'];
		} else {
			$collate_fieldname = $field['sql_fieldnames'][$index];
		}
		$error_msg = '';
		if (isset($field['sql_character_set']) AND
			isset($field['sql_character_set'][$index])) {
			$charset = $field['sql_character_set'][$index];
			$tables = [];
		} elseif (isset($field['sql_table']) AND
			isset($field['sql_table'][$index])) {
			$tables[] = $field['sql_table'][$index];
		} else {
			$tables = wrap_edit_sql($field['sql'], 'FROM', '', 'list');
		}
		break;
	}
	if (!$collate_fieldname) return '';
	if (!isset($zz_conf['int']['character_set_db'])) {
		zz_db_get_charset();
	}

	if (!$charset) {
		$db_tables = [];
		// get db table
		// check collate fieldname, might be unusable, but only if not some
		// function (e. g. CONCAT()) is in the way
		if (strstr($collate_fieldname, '.') AND !strstr($collate_fieldname, '(')) {
			$table_field = explode('.', $collate_fieldname);
			switch (count($table_field)) {
			case 2:
				$db_tables[0] = $zz_conf['db_name'].'.'.trim($table_field[0]);
				$collate_fieldname = $table_field[1];
				break;
			case 3:
				$db_tables[0] = $table_field[0].'.'.trim($table_field[1]);
				$collate_fieldname = $table_field[2];
				break;
			default:
				// leave collate fieldname as is, we cannot do anything with 
				// more than four dots. this will appear as error below
				break;
			}
			if (strstr($db_tables[0], '(')) $db_tables = [];
		} else {
			foreach ($tables as $index => $table) {
				$table = trim($table);
				if (strstr($table, '.')) $db_tables[] = $table;
				else $db_tables[] = $zz_conf['db_name'].'.'.$table;
			}
		}
		$cols = [];
		$collate_fieldname = trim($collate_fieldname);
		foreach ($db_tables as $table) {
			$table = wrap_db_prefix($table);
			$cols = zz_db_columns($table, $collate_fieldname);
			// check all tables if field exists, write first match in $cols
			if ($cols) break;
		}
	
		if (!$cols OR !in_array('Collation', array_keys($cols))) {
			// column is not in db, we cannot check the collation, therefore we
			// assume it is the same as the standard collation
			// attention: might generate errors if this is not the case
			$charset = $zz_conf['int']['character_set_db'];
		} elseif ($cols['Collation']) {
			$charset = substr($cols['Collation'], 0, strpos($cols['Collation'], '_'));
		}
	}
	if (!$charset) return '';
	if ($charset !== $zz_conf['int']['character_set_db']) return '_'.$charset;
	return '';	
}

/**
 * gets character set which is used for current db connection
 *
 * @param array $zz_conf
 *	function will write key 'character_set_db'
 */
function zz_db_get_charset() {
	global $zz_conf;
	$sql = 'SHOW VARIABLES LIKE "character_set_connection"';
	$character_set = zz_db_fetch($sql);
	$zz_conf['int']['character_set_db'] = $character_set['Value'];
}

/**
 * check how many decimal places a number has and round it later accordingly
 *
 * @param string $db_table
 * @param array $field
 * @return int number of decimal places
 * @todo support POINT as well
 */
function zz_db_decimal_places($db_table, $field) {
	if (!empty($field['factor'])) {
		$n = 0;
		while(pow(10, $n) < $field['factor']) $n++;
		return $n;
	}
	$field_def = zz_db_columns($db_table, $field['field_name']);
	if (substr($field_def['Type'], 0, 7) === 'decimal') {
		$length = substr($field_def['Type'], 8, -1);
		$length = explode(',', $length);
		if (count($length) === 2) return $length[1];
	}
	return false;
}

/**
 * select a MySQL database
 *
 * @param string $db_name
 * @return bool
 */
function zz_db_select($db_name) {
	global $zz_conf;
	return mysqli_select_db($zz_conf['db_connection'], $db_name);
}

/**
 * check if a field is numeric
 *
 * @param string $db_table
 * @param string $field_name
 * @return bool, true: it is numeric
 */
function zz_db_numeric_field($db_table, $field_name) {
	$fielddef = zz_db_columns($db_table, $field_name);
	if (empty($fielddef)) {
		// field not available
		zz_error_log([
			'msg_dev' => 'Field %s not found in table %s.',
			'msg_dev_args' => [$field_name, $db_table]
		]);
		return false;
	}
	$numeric_types = [
		'int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float'
	];
	foreach ($numeric_types as $type) {
		if ($fielddef['Type'] === $type) return true;
		if ($fielddef['Type'] === $type.' unsigned') return true;
		if (substr($fielddef['Type'], 0, strlen($type) + 1) === $type.'(') {
			return true;
		}
	}
	return false;
}
