<?php 

/**
 * zzform
 * Database functions
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * Contents:
 * D - Database functions (common functions)
 * D - Database functions (MySQL-specific functions)
 *		zz_db_*()
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2013 Gustaf Mossakowski
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_log_sql($sql, $user, $record_id = false) {
	global $zz_conf;
	// logs each INSERT, UPDATE or DELETE query
	// with record_id
	if (!mysql_affected_rows()) return false;
	$sql = trim($sql);
	if ($sql == 'SELECT 1') return false;
	// check if zzform() set db_main, test against !empty because need not be set
	// (zz_log_sql() might be called from outside zzform())
	if (!strstr($zz_conf['logging_table'], '.') AND !empty($zz_conf['int']['db_main'])) {
		$zz_conf['logging_table'] = $zz_conf['int']['db_main'].'.'.$zz_conf['logging_table'];
	}
	if (is_array($record_id)) $record_id = NULL;
	if (!empty($zz_conf['logging_id']) AND $record_id)
		$sql = 'INSERT INTO '.$zz_conf['logging_table'].' 
			(query, user, record_id) VALUES ("'.zz_db_escape($sql).'", "'.$user.'", '.$record_id.')';
	// without record_id, only for backwards compatibility
	else
		$sql = 'INSERT INTO '.$zz_conf['logging_table'].' 
			(query, user) VALUES ("'.zz_db_escape($sql).'", "'.$user.'")';
	$result = mysql_query($sql);
	if (!$result) return false;
	else return true;
	// die if logging is selected but does not work?
}

/**
 * puts parts of SQL query in correct order when they have to be added
 *
 * this function works only for sql queries without UNION:
 * might get problems with backticks that mark fieldname that is equal with SQL 
 * keyword
 * mode = add until now default, mode = replace is only implemented for SELECT
 * identical to wrap_edit_sql()!
 * @param string $sql original SQL query
 * @param string $n_part SQL keyword for part shall be edited or replaced
 *		SELECT ... FROM ... JOIN ...
 * 		WHERE ... GROUP BY ... HAVING ... ORDER BY ... LIMIT ...
 * @param string $values new value for e. g. WHERE ...
 * @param string $mode Mode, 'add' adds new values while keeping the old ones, 
 *		'replace' replaces all old values, 'list' returns existing values
 *		'delete' deletes values
 * @return string $sql modified SQL query
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @see wrap_edit_sql()
 */
function zz_edit_sql($sql, $n_part = false, $values = false, $mode = 'add') {
	global $zz_conf; // for debug only
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	
	$recursion = false; // in case of LEFT JOIN
	
	if (substr(trim($sql), 0, 4) === 'SHOW' AND $n_part === 'LIMIT') {
	// LIMIT, WHERE etc. is only allowed with SHOW
	// not allowed e. g. for SHOW DATABASES(), SHOW TABLES FROM ...
		return zz_return($sql);
	}
	if (substr(trim($sql), 0, 14) === 'SHOW DATABASES' AND $n_part === 'WHERE') {
		// this is impossible and will automatically trigger an error
		return zz_return(false); 
		// @todo implement LIKE here.
	}

	// remove whitespace
	$sql = ' '.preg_replace("/\s+/", " ", $sql); // first blank needed for SELECT
	// SQL statements in descending order
	$statements_desc = array('LIMIT', 'ORDER BY', 'HAVING', 'GROUP BY', 'WHERE', 
		'LEFT JOIN', 'FROM', 'SELECT DISTINCT', 'SELECT');
	foreach ($statements_desc as $statement) {
		// add whitespace in between brackets and statements to make life easier
		$sql = str_replace(')'.$statement.' ', ') '.$statement.' ', $sql);
		$sql = str_replace(')'.$statement.'(', ') '.$statement.' (', $sql);
		$sql = str_replace(' '.$statement.'(', ' '.$statement.' (', $sql);
		// check for statements
		$explodes = explode(' '.$statement.' ', $sql);
		if (count($explodes) > 1) {
			// = look only for last statement
			// and put remaining query in [1] and cut off part in [2]
			$o_parts[$statement][2] = array_pop($explodes);
			// last blank needed for exploding SELECT from DISTINCT
			$o_parts[$statement][1] = implode(' '.$statement.' ', $explodes).' '; 
		}
		$search = '/(.+) '.$statement.' (.+?)$/i'; 
//		preg_match removed because it takes way too long if nothing is found
//		if (preg_match($search, $sql, $o_parts[$statement])) {
		if (empty($o_parts[$statement])) continue;
		$found = false;
		$lastpart = false;
		while (!$found) {
			// check if there are () outside '' or "" and count them to check
			// whether we are inside a subselect
			$temp_sql = $o_parts[$statement][1]; // look at first part of query

			// 1. remove everything in '' and "" which are not escaped
			// replace \" character sequences which escape "
			$temp_sql = preg_replace('/\\\\"/', '', $temp_sql);
			// replace "strings" without " inbetween, empty "" as well
			$temp_sql = preg_replace('/"[^"]*"/', "away", $temp_sql);
			// replace \" character sequences which escape '
			$temp_sql = preg_replace("/\\\\'/", '', $temp_sql);
			// replace "strings" without " inbetween, empty '' as well
			$temp_sql = preg_replace("/'[^']*'/", "away", $temp_sql);

			// 2. count opening and closing ()
			//  if equal ok, if not, it's a statement in a subselect
			// assumption: there must not be brackets outside " or '
			if (substr_count($temp_sql, '(') == substr_count($temp_sql, ')')) {
				$sql = $o_parts[$statement][1]; // looks correct, so go on.
				$found = true;
			} else {
				// remove next last statement, and go on until you found 
				// either something with correct bracket count
				// or no match anymore at all
				$lastpart = ' '.$statement.' '.$o_parts[$statement][2];
				// check first with strstr if $statement (LIMIT, WHERE etc.)
				// is still part of the remaining sql query, because
				// preg_match will take 2000 times longer if there is no match
				// at all (bug in php?)
				if (stristr($o_parts[$statement][1], $statement) 
					AND preg_match($search, $o_parts[$statement][1], $o_parts[$statement])) {
					$o_parts[$statement][2] = $o_parts[$statement][2].' '.$lastpart;
				} else {
					unset($o_parts[$statement]); // ignore all this.
					$found = true;
				}
			}
		}
	}
	if (($n_part && $values) OR $mode === 'list') {
		$n_part = strtoupper($n_part);
		switch ($n_part) {
		case 'LIMIT':
			// replace complete old LIMIT with new LIMIT
			$o_parts['LIMIT'][2] = $values;
			break;
		case 'ORDER BY':
			if ($mode == 'add') {
				// append old ORDER BY to new ORDER BY
				if (!empty($o_parts['ORDER BY'][2])) 
					$o_parts['ORDER BY'][2] = $values.', '.$o_parts['ORDER BY'][2];
				else
					$o_parts['ORDER BY'][2] = $values;
			} elseif ($mode == 'delete') {
				unset($o_parts['ORDER BY']);
			}
			break;
		case 'WHERE':
		case 'GROUP BY':
		case 'HAVING':
			if ($mode == 'add') {
				if (!empty($o_parts[$n_part][2])) 
					$o_parts[$n_part][2] = '('.$o_parts[$n_part][2].') AND ('.$values.')';
				else 
					$o_parts[$n_part][2] = $values;
			}  elseif ($mode == 'delete') {
				unset($o_parts[$n_part]);
			}
			break;
		case 'LEFT JOIN':
			if ($mode == 'delete') {
				// don't remove LEFT JOIN in case of WHERE, HAVING OR GROUP BY
				// SELECT and ORDER BY should be removed beforehands!
				// use at your own risk
				if (isset($o_parts['WHERE'])) break;
				if (isset($o_parts['HAVING'])) break;
				if (isset($o_parts['GROUP BY'])) break;
				// there may be several LEFT JOINs, remove them all
				if (isset($o_parts['LEFT JOIN'])) $recursion = true;
				unset($o_parts['LEFT JOIN']);
			} elseif ($mode == 'add') {
				$o_parts[$n_part][2] .= $values;
			} elseif ($mode == 'replace') {
				$o_parts[$n_part][2] = $values;
			}
			break;
		case 'FROM':
			if ($mode === 'list') {
				$tables = array();
				$tables[] = $o_parts['FROM'][2];
				if (stristr($o_parts['FROM'][2], 'JOIN')) {
					$test = explode('JOIN', $o_parts['FROM'][2]);
					unset($test[0]);
					$tables = array_merge($tables, $test);
				}
				if (isset($o_parts['LEFT JOIN'][2])) {
					$tables[] = $o_parts['LEFT JOIN'][2];
				}
			}
			break;
		case 'SELECT':
			if (!empty($o_parts['SELECT DISTINCT'][2])) {
				if ($mode == 'add')
					$o_parts['SELECT DISTINCT'][2] .= ','.$values;
				elseif ($mode == 'replace')
					$o_parts['SELECT DISTINCT'][2] = $values;
			} else {
				if ($mode == 'add')
					$o_parts['SELECT'][2] = ','.$values;
				elseif ($mode == 'replace')
					$o_parts['SELECT'][2] = $values;
			}
			break;
		default:
			echo 'The variable <code>'.$n_part.'</code> is not supported by zz_edit_sql().';
			exit;
		}
	}
	if ($mode === 'list') {
		if (!isset($tables)) return zz_return(array());
		foreach (array_keys($tables) as $index) {
			$tables[$index] = trim($tables[$index]);
			if (strstr($tables[$index], ' ')) {
				$tables[$index] = trim(substr($tables[$index], 0, strpos($tables[$index], ' ')));
			}
		}
		return zz_return($tables);
	}
	$statements_asc = array_reverse($statements_desc);
	foreach ($statements_asc as $statement) {
		if (!empty($o_parts[$statement][2])) 
			$sql.= ' '.$statement.' '.$o_parts[$statement][2];
	}
	if ($recursion) $sql = zz_edit_sql($sql, $n_part, $values, $mode);
	return zz_return($sql);
}

/**
 * checks if SQL queries use table prefixes and replace them with current
 * table prefix from configuration
 * syntax for prefixes is SQL comment / *PREFIX* /
 *
 * @param array $vars definition of table/configuration
 * @param string $type 'zz' or 'zz_conf'
 * @return array $vars (definition with replacements for table prefixes)
 */
function zz_sql_prefix($vars, $type = 'zz') {
	global $zz_conf;

	$zz_conf['int']['prefix_change'] = $type;
	array_walk_recursive($vars, 'zz_sql_prefix_change');
	unset($zz_conf['int']['prefix_change']);

	return $vars;
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
function zz_sql_prefix_change(&$item, $key) {
	global $zz_conf;
	$prefix = '/*_PREFIX_*/';
	switch ($zz_conf['int']['prefix_change']) {
	case 'zz':
		// $zz['conditional_fields'][n]['sql']
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
		$sql_fields = array(
			'sql', 'having', 'where', 'path_sql', 'search', 'search_between',
			'set_sql', 'sqlorder', 'sql_not_unique', 'sql_password_check',
			'upload_sql', 'options_sql', 'source_path_sql', 'table',
			'id_field_name', 'display_field', 'key_field_name', 'order',
			'foreign_key_field_name', 'sqlcount', 'sqlextra', 'geocode_sql',
			'min_records_sql', 'max_records_sql', 'sqlrecord'
		);
		break;
	case 'zz_conf':
		// $zz_conf['filter'][n]['sql']
		// $zz_conf['filter'][n]['where']
		$sql_fields = array(
			'sql', 'where', 'logging_table', 'relations_table',
			'text_table', 'translations_table', 'sql_join'
		);
		break;
	}

	if (is_numeric($key) OR in_array($key, $sql_fields)) {
		// numeric keys are okay as well
		// @todo check if we can exclude them (sqlextra)
		if (strstr($item, $prefix)) {
			$item = str_replace($prefix, $zz_conf['prefix'], $item);
		}
	} else {
		// still do the same until we are sure enough what to think of
		if (is_string($item) AND strstr($item, $prefix)) {
			$item = str_replace($prefix, $zz_conf['prefix'], $item);
			if (function_exists('wrap_error')) {
				wrap_error(sprintf('Table prefix for key %s (item %s) replaced'
				.' which was not anticipated', $key, $item), E_USER_NOTICE);
			}
		}
	}
}

/**
 * gets fieldnames from SQL query
 *
 * @param string $sql SQL query
 * @return array list of field names, e. g.
 * Array
 * (
 *    [0] =>  object_id 
 *    [1] =>  objects.identifier
 *    [2] =>  categories.identifier
 * )
 */
function zz_sql_fieldnames($sql) {
	// preg_match, case insensitive, space after select, space around from 
	// - might not be 100% perfect, but should work always
	preg_match('/SELECT( DISTINCT|) *(.+) FROM /Ui', $sql, $fieldstring); 
	$fields = explode(",", $fieldstring[2]);
	unset($fieldstring);
	$oldfield = false;
	$newfields = false;
	foreach ($fields as $myfield) {
		// oldfield, so we are inside parentheses
		if ($oldfield) $myfield = $oldfield.','.$myfield; 
		// not enough brackets, so glue strings together until there are enough 
		// - not 100% safe if bracket appears inside string
		if (substr_count($myfield, '(') != substr_count($myfield, ')')) {
			$oldfield = $myfield; 
		} else {
			$myfields = '';
			// replace AS blah against nothing
			if (stristr($myfield, ') AS')) 
				preg_match('/(.+\)) AS [a-z0-9_]/i', $myfield, $myfields); 
			if ($myfields) $myfield = $myfields[1];
			$myfields = '';
			if (stristr($myfield, ' AS ')) 
				preg_match('/(.+) AS [a-z0-9_]/i', $myfield, $myfields); 
			if ($myfields) $myfield = $myfields[1];
			$newfields[$myfield] = $myfield; // eliminate duplicates
			$oldfield = false; // now that we've written it to array, empty it
		}
	}
	$newfields = array_values($newfields);
	return $newfields;
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
	} elseif (substr($sql, 0, 15) != 'SELECT DISTINCT'
		AND !stristr($sql, 'GROUP BY') AND !stristr($sql, 'HAVING')) {
		// if it's not a SELECT DISTINCT, we can use COUNT, that's faster
		// GROUP BY also does not work with COUNT
		$sql = zz_edit_sql($sql, 'ORDER BY', '_dummy_', 'delete');
		$sql = zz_edit_sql($sql, 'SELECT', 'COUNT('.$id_field.')', 'replace');
		// unnecessary LEFT JOINs may slow down query
		// remove them in case no WHERE, HAVING or GROUP BY is set
		$sql = zz_edit_sql($sql, 'LEFT JOIN', '_dummy_', 'delete');
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
 * @global array $zz_error
 * @return string $table - name of main table
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_db_connection($table) {
	global $zz_error;
	global $zz_conf;

	// get current db to SELECT it again before exitting
	// might be that there was no database connection established so far
	// therefore the @, but it does not matter because we simply want to
	// revert to the current database after exitting this script
	$result = @mysql_query('SELECT DATABASE()');
	$zz_conf['int']['db_current'] = $result ? mysql_result($result, 0, 0) : '';
	// main database normally is the same db that zzform() uses for its
	// operations, but if you use several databases, this is the one which
	// is the main db, i. e. the one that will be used if no other database
	// name is specified
	$zz_conf['int']['db_main'] = false;

	if (!isset($zz_conf['db_connection'])) {
		include_once $zz_conf['dir_custom'].'/db.inc.php';
	}
	// get db_name.
	// 1. best way: put it in zz_conf['db_name']
	if (!empty($zz_conf['db_name'])) {
		$db = zz_db_select($zz_conf['db_name']);
		if (!$db) {
			$zz_error[] = array(
				'db_msg' => mysql_error(),
				'query' => 'SELECT DATABASE("'.$zz_conf['db_name'].'")',
				'level' => E_USER_ERROR
			);
			$zz_conf['db_name'] = '';
			return false;
		}
	// 2. alternative: use current database
	} else {
		$result = mysql_query('SELECT DATABASE()');
		if (mysql_error()) {
			$zz_error[] = array(
				'db_msg' => mysql_error(),
				'query' => 'SELECT DATABASE()',
				'level' => E_USER_ERROR
			);
			return false;
		}
		$zz_conf['db_name'] = mysql_result($result, 0, 0);
	}

	// 3. alternative plus foreign db: put it in zz['table']
	if (preg_match('~(.+)\.(.+)~', $table, $db_name)) { // db_name is already in zz['table']
		if ($zz_conf['db_name'] AND $zz_conf['db_name'] != $db_name[1]) {
			// this database is different from main database, so save it here
			// for later
			$zz_conf['int']['db_main'] = $zz_conf['db_name'];
		} elseif (!$zz_conf['db_name']) { 
			// no database selected, get one, quick!
			$dbname = zz_db_select($db_name[1]);
			if (!$dbname) {
				$zz_error[] = array(
					'db_msg' => mysql_error(),
					'query' => 'SELECT DATABASE("'.$db_name[1].'")',
					'level' => E_USER_ERROR
				);
				$zz_conf['db_name'] = '';
				return false;
			}
		}
		$zz_conf['db_name'] = $db_name[1];
		$table = $db_name[2];
	}

	if (empty($zz_conf['db_name'])) {
		$zz_error[] = array(
			'msg_dev' => 'Please set the variable <code>$zz_conf[\'db_name\']</code>.'
				.' It has to be set to the main database name used for zzform.',
			'level' => E_USER_ERROR
		);
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
 *	'id as key' = returns array($id_field_value => true)
 *	"key/value" = returns array($key => $value)
 *	"single value" = returns $value
 *	"object" = returns object
 *	"numeric" = returns lines in numerical array [0 ... n] instead of using field ids
 * @param string $info (optional) information about where this query was called
 * @param int $errorcode let's you set error level, default = E_USER_ERROR
 * @return array with queried database content
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo give a more detailed explanation of how function works
 */
function zz_db_fetch($sql, $id_field_name = false, $format = false, $info = false, $errorcode = E_USER_ERROR) {
	global $zz_conf;
	if (!empty($zz_conf['debug']) AND function_exists('wrap_error')) {
		$time = microtime_float();
	}
	$lines = array();
	$error = false;
	$result = mysql_query($sql);
	if ($result) {
		if (!$id_field_name) {
			// only one record
			if (mysql_num_rows($result) == 1) {
	 			if ($format === 'single value') {
					$lines = mysql_result($result, 0, 0);
	 			} elseif ($format === 'object') {
					$lines = mysql_fetch_object($result);
				} else {
					$lines = mysql_fetch_assoc($result);
				}
			}
 		} elseif (is_array($id_field_name) AND mysql_num_rows($result)) {
			if ($format === 'object') {
				while ($line = mysql_fetch_object($result)) {
					if (count($id_field_name) == 3) {
						if ($error = zz_db_field_in_query($line, $id_field_name, 3)) break;
						$lines[$line->$id_field_name[0]][$line->$id_field_name[1]][$line->$id_field_name[2]] = $line;
					} else {
						if ($error = zz_db_field_in_query($line, $id_field_name, 2)) break;
						$lines[$line->$id_field_name[0]][$line->$id_field_name[1]] = $line;
					}
				}
 			} else {
 				// default or unknown format
				while ($line = mysql_fetch_assoc($result)) {
		 			if ($format === 'single value') {
						// just get last field, make sure that it's not one of the id_field_names!
		 				$values = array_pop($line);
		 			} else {
		 				$values = $line;
		 			}
					if (count($id_field_name) == 4) {
						if ($error = zz_db_field_in_query($line, $id_field_name, 4)) break;
						$lines[$line[$id_field_name[0]]][$line[$id_field_name[1]]][$line[$id_field_name[2]]][$line[$id_field_name[3]]] = $values;
					} elseif (count($id_field_name) == 3) {
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
 		} elseif (mysql_num_rows($result)) {
 			if ($format === 'count') {
 				$lines = mysql_num_rows($result);
 			} elseif ($format === 'single value') {
 				// you can reach this part here with a dummy id_field_name
 				// because no $id_field_name is needed!
				while ($line = mysql_fetch_array($result)) {
					$lines[$line[0]] = $line[0];
				}
 			} elseif ($format === 'id as key') {
				while ($line = mysql_fetch_array($result)) {
					if ($error = zz_db_field_in_query($line, $id_field_name)) break;
					$lines[$line[$id_field_name]] = true;
				}
 			} elseif ($format === 'key/value') {
 				// return array in pairs
				while ($line = mysql_fetch_array($result)) {
					$lines[$line[0]] = $line[1];
				}
			} elseif ($format === 'object') {
				while ($line = mysql_fetch_object($result)) {
					if ($error = zz_db_field_in_query($line, $id_field_name)) break;
					$lines[$line->$id_field_name] = $line;
				}
			} elseif ($format === 'numeric') {
				while ($line = mysql_fetch_assoc($result))
					$lines[] = $line;
 			} else {
 				// default or unknown format
				while ($line = mysql_fetch_assoc($result)) {
					if ($error = zz_db_field_in_query($line, $id_field_name)) break;
					$lines[$line[$id_field_name]] = $line;
				}
			}
		}
	} else $error = true;
	if ($error AND $error !== true) $info .= $error;
	if ($zz_conf['modules']['debug']) zz_debug('sql (rows: '
		.($result ? mysql_num_rows($result) : 0).')'.($info ? ': '.$info : ''), $sql);
	if (!empty($zz_conf['debug']) AND function_exists('wrap_error')) {
		// @todo: check if it's easier to do it with zz_error()
		$time = microtime_float() - $time;
		wrap_error('SQL query in '.$time.' - '.$sql, E_USER_NOTICE);
	}
	if ($error) {
		if ($zz_conf['modules']['debug']) {
			global $zz_debug;
			$current = end($zz_debug[$zz_conf['id']]['function']);
		} else {
			$current['function'] = '';
		}
		$msg_dev = 'Error in SQL query'
			.(!empty($current['function']) ? ' in function '.$current['function'] : '')
			.($info ? ' - '.$info.'.' : '');

		global $zz_error;
		$zz_error[] = array(
			'msg_dev' => $msg_dev,
			'db_msg' => mysql_error(), 
			'query' => $sql,
			'level' => $errorcode
		);
		zz_error();
		return array();
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
	$missing_fields = array();
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
 * Escapes values for database input
 *
 * @param string $value
 * @return string escaped $value
 */
function zz_db_escape($value) {
	// should never happen, just during development
	if (!$value) return '';
	if (is_array($value) OR is_object($value)) {
		global $zz_error;
		$zz_error[] = array(
			'msg_dev' => 'zz_db_escape() - value is not a string: '.json_encode($value)
		);
		return '';
	}
	if (function_exists('mysql_real_escape_string')) { 
		// just from PHP 4.3.0 on
		return mysql_real_escape_string($value);
	} else {
		return addslashes($value);
	}
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
	global $zz_error;
	if (!empty($zz_conf['debug'])) {
		$time = microtime_float();
	}

	// initialize $db
	$db = array();
	$db['action'] = 'nothing';

	// write back ID value if it's there
	$db['id_value'] = $id;
	// dummy SQL means nothing will be done
	if ($sql == 'SELECT 1') return $db;
	
	// get rid of extra whitespace, just to check statements
	$sql_ws = preg_replace('~\s~', ' ', trim($sql));
	$tokens = explode(' ', $sql_ws);
	// check if statement is allowed
	$allowed_statements = array('INSERT', 'DELETE', 'UPDATE');
	if (!in_array($tokens[0], $allowed_statements)) {
		$db['action'] = '';
		$db['error'] = array(
			'query' => $sql,
			'msg_dev' => 'Statement not supported'
		);
		return $db;
	}

	// check
	$result = mysql_query($sql);
	if ($result) {
		if (!mysql_affected_rows()) {
			$db['action'] = 'nothing';
		} else {
			$db['rows'] = mysql_affected_rows();
			$db['action'] = strtolower($tokens[0]);
			if ($db['action'] == 'insert') // get ID value
				$db['id_value'] = mysql_insert_id();
			// Logs SQL Query, must be after insert_id was checked
			if (!empty($zz_conf['logging']))
				zz_log_sql($sql, $zz_conf['user'], $db['id_value']);
		}
		$warnings = zz_db_fetch('SHOW WARNINGS', '_dummy_', 'numeric');
		foreach ($warnings as $warning) {
			$zz_error[] = array(
				'msg_dev' => 'MySQL reports a problem.',
				'query' => $sql,
				'db_msg' => $warning['Level'].': '.$warning['Message'],
				'level' => E_USER_WARNING
			);
		}
	} else { 
		// something went wrong, but why?
		$db['action'] = '';
		$db['error'] = array(
			'query' => $sql,
			'db_msg' => mysql_error(),
			'db_errno' => mysql_errno()
		);
	}
	if (!empty($zz_conf['debug']) AND function_exists('wrap_error')) {
		// @todo: check if it's easier to do it with zz_error()
		$time = microtime_float() - $time;
		wrap_error('SQL query in '.$time.' - '.$sql, E_USER_NOTICE);
	}
	return $db;	
}

/**
 * puts backticks around database and table name
 *
 * @param string $db_table = db_name, table name or both concatenated
 *		with a dot
 * @return string $db_table `database`.`table` or `database` or `table`
 */
function zz_db_table_backticks($db_table) {
	if (substr($db_table, 0, 1) != '`' AND substr($db_table, -1) != '`') {
		$db_table = '`'.str_replace('.', '`.`', $db_table).'`';
	}
	return $db_table;
}

/** 
 * checks maximum field length in MySQL database table
 * 
 * @param string $field	field name
 * @param string $db_table	table name [i. e. db_name.table]
 * @return maximum length of field or false if no field length is set
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_db_field_maxlength($field, $type, $db_table) {
	if (!$field) return false;
	// just if it's a field with a field_name
	// for some field types it makes no sense to check for maxlength
	$dont_check = array('image', 'display', 'timestamp', 'hidden', 'foreign_key',
		'select', 'id', 'date', 'time', 'option');
	if (in_array($type, $dont_check)) return false;

	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$maxlength = false;
	$field_def = zz_db_columns($db_table, $field);
	if ($field_def) {
		preg_match('/\((\d+)\)/s', $field_def['Type'], $my_result);
		if (isset($my_result[1])) $maxlength = $my_result[1];
	}
	if ($zz_conf['modules']['debug']) zz_debug($type.($maxlength ? '-'.$maxlength : ''));
	return zz_return($maxlength);
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
	if (!$db_table) return array();
	if (!isset($columns[$db_table])) {
		$sql = 'SHOW FULL COLUMNS FROM '.zz_db_table_backticks($db_table);
		$columns[$db_table] = zz_db_fetch($sql, 'Field', false, false, E_USER_WARNING);
	}
	if ($field) {
		if (!empty($columns[$db_table][$field])) {
			return $columns[$db_table][$field];
		} else {
			return false;
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
		$msg = zz_text('database-error');
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
	if ($line AND $line['Null'] == 'YES') return true;
	else return false;
}

/**
 * prefix different charset if necessary for LIKE
 *
 * @param string $type 'search', 'reselect'
 * @param string $db_table
 * @param array $field
 *		'character_set'
 * @param int $index (for 'reselect')
 * @return string
 */
function zz_db_field_collation($type, $table, $field, $index = 0) {
	global $zz_conf;
	if (empty($zz_conf['character_set_db_multiple'])) return '';
	
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
		$tables[] = $table;
		break;
	case 'reselect':
		$collate_fieldname = $field['sql_fieldnames'][$index];
		$error_msg = '';
		if (isset($field['sql_character_set']) AND
			isset($field['sql_character_set'][$index])) {
			$charset = $field['sql_character_set'][$index];
			$tables = array();
		} elseif (isset($field['sql_table']) AND
			isset($field['sql_table'][$index])) {
			$tables[] = $field['sql_table'][$index];
		} else {
			$tables = zz_edit_sql($field['sql'], 'FROM', '', 'list');
		}
		break;
	}
	if (!$collate_fieldname) return '';
	if (!isset($zz_conf['int']['character_set_db'])) {
		zz_db_get_charset();
	}

	if (!$charset) {
		$db_tables = array();
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
			if (strstr($db_tables[0], '(')) $db_tables = array();
		} else {
			foreach ($tables as $index => $table) {
				$table = trim($table);
				if (strstr($table, '.')) $db_tables[] = $table;
				else $db_tables[] = $zz_conf['db_name'].'.'.$table;
			}
		}
		$cols = array();
		$collate_fieldname = trim($collate_fieldname);
		foreach ($db_tables as $db_table) {
			$cols = zz_db_columns($db_table, $collate_fieldname);
			// check all tables if field exists, write first match in $cols
			if ($cols) break;
		}
	
		// column is not in db, we cannot check the collation, therefore we
		// better exclude this field from search
		if (!$cols OR !in_array('Collation', array_keys($cols))) {
			global $zz_error;
			$zz_error[] = array(
				'msg_dev' => 
					sprintf('Cannot get character set information for %s.', $collate_fieldname)
					.$error_msg,
				'level' => E_USER_NOTICE
			);
			return NULL;
		}
		$charset = substr($cols['Collation'], 0, strpos($cols['Collation'], '_'));
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
	if (substr($field_def['Type'], 0, 7) == 'decimal') {
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
	return mysql_select_db($db_name);
}

/**
 * select a MySQL database character set
 *
 * @param string $db_name
 * @return bool
 */
function zz_db_charset($character_set) {
	return mysql_set_charset($character_set);
}

?>