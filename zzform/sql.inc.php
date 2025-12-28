<?php 

/**
 * zzform
 * SQL utility functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


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
	if (!is_string($item)) return false;
	$changed = wrap_db_prefix($item);
	if ($changed === $item) return false;
	$item = $changed;
	return true;
}
 
/**
 * checks each key if it might potentially have a table prefix
 * and replaces all prefixes of its value
 *
 * @param mixed $item
 * @param string $key
 * @return void
 * @todo remove this function and do the replacement in zz_db_fetch() instead
 * for this to happen, all functions getting database names from table etc.
 * must be rewritten
 */
function zz_sql_prefix_change_zz(&$item, $key) {
	$success = zz_sql_prefix_change($item);
	if (!$success) return false;
	
	// numeric keys are okay as well
	// @todo check if we can exclude them (sql_extra)
	if (is_numeric($key)) return false;

	// $zz['conditions'][n]['add']['sql']
	// $zz['conditions'][n]['having']
	// $zz['conditions'][n]['sql']
	// $zz['conditions'][n]['where']
	// $zz['fields'][n]['identifier']['where'] 
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
	// $zz['sql_record']
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
		'foreign_key_field_name', 'sql_count', 'sql_extra', 'geocode_sql',
		'min_records_sql', 'max_records_sql', 'sql_record', 'sql_join',
		'dependent_on_add_sql'
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
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

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

/**
 * replace LIKE asterisks with regular expressions
 *
 * @param string $query
 * @return string
 */
function zz_sql_like_to_pcre($query) {
	$query = str_replace('%d', '\d+', $query);
	$query = str_replace('%s', '\w+', $query);
	$query = str_replace('%', '.+', $query);
	$query = str_replace('(', '\(', $query);
	$query = str_replace(')', '\)', $query);
	$pattern = sprintf('/%s/', $query);
	return $pattern;
}

