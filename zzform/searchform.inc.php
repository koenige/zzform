<?php

/**
 * zzform
 * Display and evaluation of search form below/above table
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2013, 2015-2021, 2023-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * modifies SQL query according to search
 *
 * @param array $fields
 * @param string $sql
 * @param string $table
 * @param string $search_input (optional)
 * @return string $sql (un-)modified SQL query
 * @todo if there are detail tables, part of this functions code is run redundantly
 */
function zz_search_sql($fields, $sql, $table, $search_input = '') {
	// no changes if there's no query string
	if (!$search_input) $search_input = $_GET['q'] ?? '';
	if (!$search_input) return $sql;

	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	if (wrap_setting('debug')) zz_debug('search query', $sql);
	static $calls = 0;
	$calls++;

	// get scope
	$scope = (!empty($_GET['scope']) AND $calls === 1) ? $_GET['scope'] : '';

	$search = zz_search_parse_term($search_input);

	// get fields
	$searchfields = [];
	$q_search = [];
	// fields that won't be used for search
	$context = false;
	$search_detail_table = [];
	$found = false;
	foreach ($fields as $field) {
		if (empty($field)) continue;
		if (!zz_search_searchable($field)) continue;
		
		if ($scope) {
			$context = zz_search_scope($field, $table, $scope);
		} else {
			if ($field['type'] === 'subtable') $context = 'detail_table';
			elseif ($field['type'] === 'foreign_table') $context = false;
			else $context = 'field';
		}
		if (!$context) continue;
		
		switch ($context) {
		case 'field':
			$q_search[] = zz_search_field($field, $table, $search['operator'], $search['term']);
			$found = true;
			break;
		case 'detail_table':
			$return = zz_search_detail_table($field, $table);
			if (!$return) break;
			$search_detail_table[] = $return;
			$found = true;
			break;
		case 'detail_field':
			list ($detail_table, $detail_field_name) = explode('.', $scope);
			foreach ($field['fields'] as $no => $s_field) {
				if ($s_field['type'] === 'id') {
					$submain_id_fieldname = $s_field['field_name'];
				}
				if ($s_field['field_name'] === $detail_field_name) {
					$detail_field = $s_field;
				} elseif (!empty($s_field['display_field']) AND $s_field['display_field'] === $detail_field_name) {
					$detail_field = $s_field;
				}
			}
			$q_search[] = zz_search_field($detail_field, $detail_table, $submain_id_fieldname, $search['term']);
			$found = true;
			break;
		}
		// additional between search
		if (isset($field['search_between'])) {
			$q_search[] = sprintf($field['search_between'], '"'.$search['term'].'"', '"'.$search['term'].'"');
		}
	}
	
	if ($search_detail_table)
		$q_search = array_merge($q_search, zz_search_detail_table_sql($search_detail_table, $search['operator']));
	foreach ($q_search as $index => $where)
		if (!$where) unset($q_search[$index]);

	if ($scope AND !$found) 
		wrap_static('page', 'status', 404);

	if ($q_search AND zz_search_negative($search['operator'])) {
		$q_search = '('.implode(' AND ', $q_search).')';
	} elseif ($q_search) {
		$q_search = '('.implode(' OR ', $q_search).')';
	} elseif (!$fields) {
		$q_search = 'NULL'; // get empty result, fields not defined
	} else {
		$q_search = NULL;
	}
	if ($q_search) $sql = wrap_edit_sql($sql, 'WHERE', $q_search);

	if (wrap_setting('debug')) zz_debug("end; search query", $sql);
	return $sql;
}

/**
 * parse search word, check if there is an operator, clean up search word
 *
 * @param string $search_input
 * @return array
 */
function zz_search_parse_term($search_input) {
	$search = [];

	// replace tabs, duplicate spaces	
	if (strstr($search_input, chr(9)))
		$search_input = str_replace(chr(9), ' ', $search_input);
	while (strstr($search_input, '  '))
		$search_input = str_replace('  ', ' ', $search_input);

	// get search operator, globally for all fields
	if (substr($search_input, 0, 1) === ' ' AND substr($search_input, -1) === ' ') {
		$search = [
			'operator' => '=',
			'term' => trim($search_input)
		];
	} elseif (str_starts_with($search_input, '! ')) {
		$search = [
			'operator' => '%NOT LIKE%',
			'term' => trim(substr($search_input, 1))
		];
	} elseif (substr($search_input, 0, 1) === ' ') {
		$search = [
			'operator' => 'LIKE%',
			'term' => trim($search_input)
		];
	} elseif (substr($search_input, -1) === ' ') {
		$search = [
			'operator' => '%LIKE',
			'term' => trim($search_input)
		];
	} elseif (substr($search_input, 0, 2) === '> ') {
		$search = [
			'operator' => '>',
			'term' => trim(substr($search_input, 1))
		];
	} elseif (substr($search_input, 0, 2) === '< ') {
		$search = [
			'operator' => '<',
			'term' => trim(substr($search_input, 1))
		];
	} elseif (substr($search_input, 0, 3) === '<= ') {
		$search = [
			'operator' => '<=',
			'term' => trim(substr($search_input, 2))
		];
	} elseif (substr($search_input, 0, 3) === '>= ') {
		$search = [
			'operator' => '>=',
			'term' => trim(substr($search_input, 2))
		];
	} elseif (substr($search_input, 0, 2) === '= ') {
		$search = [
			'operator' => '=',
			'term' => trim(substr($search_input, 1))
		];
	} elseif (substr($search_input, 0, 2) === '- ' 
		AND strstr(trim(substr($search_input, 1)), ' ')) {
		$search = [
			'operator' => 'BETWEEN',
			'term' => explode(' ', trim(substr($search_input, 1)))
		];
	} elseif ($search_input === '!NULL' OR $search_input === 'NULL') {
		$search = [
			'operator' => $search_input,
			'term' => false
		];
	} else {
		// first slash will be ignored, this is used to escape reserved characters
		$search = [
			'operator' => '%LIKE%',
			'term' => (substr($search_input, 0, 1) == '\\') ? substr($search_input, 1) : $search_input
		];
	}
	
	// escape term
	if (!is_array($search['term']))
		$search['term'] = wrap_db_escape($search['term']);
	else
		foreach (array_keys($search['term']) as $index)
			$search['term'][$index] = wrap_db_escape($search['term'][$index]);
	
	return $search;
}

/**
 * get part of SQL query for search in a field
 *
 * @param array $field field definition
 * @param string $table
 * @param string $operator
 * @param string $search_term
 * @return string part of query
 */
function zz_search_field($field, $table, $operator, $search_term) {
	// get field name
	if (isset($field['search'])) {
		$fieldname = $field['search'];
	} elseif (isset($field['display_field'])) {
		// it makes more sense to search through values than IDs
		$fieldname = $field['display_field'];
	} elseif (!empty($field['field_name'])) {
		// standard: use table- and field name
		$fieldname = $table.'.'.$field['field_name'];
		if ($search_term) {
			$search_term = zz_search_check_field($field['field_name'], $table, $search_term);
			if (!$search_term) return '';
		}
	} else {
		return '';
	}

	if ($field['type'] === 'number' AND !is_numeric($search_term)) return '';

	// get searchword/operator, per field type
	$field_type = zz_get_fieldtype($field);
	
	list($search_term, $operator) = zz_search_field_datetime($field_type, $search_term, $operator);
	if (!$operator) return '';

	// build search query part
	switch ($operator) {
	case 'NULL':
		if (!zz_db_field_null($field['field_name'], wrap_setting('db_name').'.'.$table)) return '';
		return sprintf('ISNULL(%s)', $fieldname);
	case '!NULL':
		if (!zz_db_field_null($field['field_name'], wrap_setting('db_name').'.'.$table)) return '';
		return sprintf('NOT ISNULL(%s)', $fieldname);
	case '<':
		return sprintf('%s < "%s"', $fieldname, $search_term);
	case '<=':
		return sprintf('%s <= "%s"', $fieldname, $search_term);
	case '>':
		return sprintf('%s > "%s"', $fieldname, $search_term);
	case '>=':
		return sprintf('%s >= "%s"', $fieldname, $search_term);
	case 'BETWEEN':
		if (in_array($field['type'], ['datetime', 'timestamp']))
			return sprintf('%s BETWEEN "%s" AND "%s"', $fieldname, $search_term[0], $search_term[1]);
		return sprintf('%s >= "%s" AND %s <= "%s"', $fieldname, $search_term[0],
			$fieldname, $search_term[1]);
	case 'NOT BETWEEN':
		return sprintf('%s NOT BETWEEN "%s" AND "%s"', $fieldname, $search_term[0], $search_term[1]);
	case 'QUARTER':
		// @todo: improve to use indices, BETWEEN year_begin and year_end ...
		return sprintf('(YEAR(%s) = "%s" AND QUARTER(%s) = "%s")', $fieldname, 
			$search_term[1], $fieldname, $search_term[0]);
	case 'NOT QUARTER':
		return sprintf('(YEAR(%s) != "%s" OR QUARTER(%s) != "%s")', $fieldname, 
			$search_term[1], $fieldname, $search_term[0]);
	case 'YEAR':
		return sprintf('YEAR(%s) = %d', $fieldname, $search_term);
	case 'NOT YEAR':
		return sprintf('YEAR(%s) != %d', $fieldname, $search_term);
	case 'YEAR-MONTH':
		return sprintf('(YEAR(%s) = %d AND MONTH(%s) = %d)', $fieldname, 
			$search_term[1], $fieldname, $search_term[2]);
	case 'NOT YEAR-MONTH':
		return sprintf('(YEAR(%s) != %d AND MONTH(%s) = %d)', $fieldname, 
			$search_term[1], $fieldname, $search_term[2]);
	case 'MONTH':
		return sprintf('MONTH(%s) = %d', $fieldname, $search_term);
	case 'NOT MONTH':
		return sprintf('MONTH(%s) = %d', $fieldname, $search_term);
	case '=':
		if (!zz_search_set_enum($operator, $search_term, $field_type, $field)) return '';
		return sprintf('%s = "%s"', $fieldname, $search_term);
	case '!=':
		if (!zz_search_set_enum($operator, $search_term, $field_type, $field)) return '';
		return sprintf('%s != "%s" OR ISNULL(%s)', $fieldname, $search_term, $fieldname);
	case '%NOT LIKE%':
		if (!zz_search_set_enum($operator, $search_term, $field_type, $field)) return '';
		// @todo think about using NOT IN() in query as a result of zz_search_set_enum()
		$collation = zz_db_field_collation($field, $table);
		if ($collation === NULL) return '';
		if ($field['type'] === 'datetime') // bug in MySQL 
			$fieldname = sprintf('DATE_FORMAT(%s, "%%Y-%%m-%%d %%H:%%i:%%s")', $fieldname);
		return sprintf('(%s NOT LIKE %s"%%%s%%" OR ISNULL(%s))', $fieldname, $collation, $search_term, $fieldname);
	case '%LIKE':
		if (!zz_search_set_enum($operator, $search_term, $field_type, $field)) return '';
		$collation = zz_db_field_collation($field, $table);
		if ($collation === NULL) return '';
		if ($field['type'] === 'datetime') // bug in MySQL 
			$fieldname = sprintf('DATE_FORMAT(%s, "%%Y-%%m-%%d %%H:%%i:%%s")', $fieldname);
		return sprintf('%s LIKE %s"%%%s"', $fieldname, $collation, $search_term);
	case 'LIKE%':
		if (!zz_search_set_enum($operator, $search_term, $field_type, $field)) return '';
		$collation = zz_db_field_collation($field, $table);
		if ($collation === NULL) return '';
		if ($field['type'] === 'datetime') // bug in MySQL
			$fieldname = sprintf('DATE_FORMAT(%s, "%%Y-%%m-%%d %%H:%%i:%%s")', $fieldname);
		return sprintf('%s LIKE %s"%s%%"', $fieldname, $collation, $search_term);
	case '%LIKE%':
	default:
		if (!zz_search_set_enum($operator, $search_term, $field_type, $field)) return '';
		$collation = zz_db_field_collation($field, $table);
		if ($collation === NULL) return '';
		if ($field['type'] === 'datetime') // bug in MySQL
			$fieldname = sprintf('DATE_FORMAT(%s, "%%Y-%%m-%%d %%H:%%i:%%s")', $fieldname);
		return sprintf('%s LIKE %s"%%%s%%"', $fieldname, $collation, $search_term);
	}
	return '';
}

/**
 * check if a field is date or time, only check against valid dates
 *
 * @param string $field_type
 * @param mixed $search_term
 * @param string $operator
 * @return array
 */
function zz_search_field_datetime($field_type, $search_term, $operator) {
	if (!in_array($field_type, ['date', 'datetime', 'time', 'timestamp']))
		return [$search_term, $operator];
	if (!$search_term) return [$search_term, $operator];
	if (is_array($search_term)) return [$search_term, $operator];

	// Quarter?
	if (preg_match('/q\d(.)[0-9]{4}/i', $search_term, $separator)) {
		$search_term = trim(substr($search_term, 1));
		$search_term = explode($separator[1], $search_term);
		return [$search_term, zz_search_negative($operator).'QUARTER'];
	}
	
	$timesearch = zz_search_time($search_term);
	switch ($field_type) {
	case 'datetime':
	case 'timestamp':
		if ($timesearch) {
			$search_term = date('Y-m-d H:i:s', strtotime($timesearch));
			switch ($operator) {
			case '>':
			case '<=':
				if (str_ends_with($search_term, ' 00:00:00'))
					$search_term = date('Y-m-d', strtotime($timesearch)).' 23:59:59';
				break;
			case '>=':
			case '<':
				break;
			default:
				if (str_ends_with($search_term, ' 00:00:00')) {
					$search_term = [
						date('Y-m-d', strtotime($timesearch)).' 00:00:00',
						date('Y-m-d', strtotime($timesearch)).' 23:59:59'
					];
					$operator = zz_search_negative($operator).'BETWEEN';
				}
			}
		} elseif (preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/', $search_term)
			AND in_array($operator,  ['%LIKE%', '%NOT LIKE%'])) {
			$search_term = [
				$search_term.' 00:00:00',
				$search_term.' 23:59:59'
			];
			$operator = zz_search_negative($operator).'BETWEEN';
		}
		break;
	case 'time':
		if ($timesearch) {
			if (str_ends_with($timesearch, ' 00:00:00') AND strstr($timesearch, ' '))
				return [false, false];
			$search_term = date('H:i:s', strtotime($timesearch));
		}
		if (preg_match('/^[0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/', $search_term)) {
			if ($operator === '%LIKE%') $operator = '=';
			elseif ($operator === '%NOT LIKE%') $operator = '!=';
		}
		break;
	case 'date':
		preg_match('/^([0-9]+)$/', $search_term, $matches);
		if ($timesearch) {
			$search_term = date('Y-m-d', strtotime($timesearch));
		} elseif (preg_match('/^([0-9]+)\.([0-9]+)\.$/', $search_term, $matches)) {
			$search_term = sprintf('%1$02d-%2$02d', $matches[2], $matches[1]);
		} elseif (preg_match('/^(\d{1,4})-(\d{0,2})$/', $search_term, $matches)) {
			$operator = zz_search_negative($operator).'YEAR-MONTH';
			$search_term = $matches;
		} elseif (preg_match('/^(\d{1,2})$/', $search_term, $matches)) {
			$operator = zz_search_negative($operator).'MONTH';
			$search_term = $matches[1];
		} elseif (preg_match('/^(\d{1,4})$/', $search_term, $matches)) {
			$operator = zz_search_negative($operator).'YEAR';
			$search_term = $matches[1];
		}
		if (preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/', $search_term)) {
			if ($operator === '%LIKE%') $operator = '=';
			elseif ($operator === '%NOT LIKE%') $operator = '!=';
		} 
		break;
	}
	if (!is_array($search_term) AND !preg_match('/^[0-9:\-% ]+$/', $search_term)) return [false, false];
	return [$search_term, $operator];
}

/**
 * for ENUM and SET fields, check if value to search for exists directly
 * to improve SQL query
 *
 * @param string $operator
 * @param string $search_term
 * @param string $field_type
 * @param array $field
 * @return bool false: don't query, search word does not exist
 */
function zz_search_set_enum($operator, $search_term, $field_type, $field) {
	if ($field_type !== 'select') return true;
	$set = $field['enum'] ?? $field['set'] ?? [];
	if (!$set) return true;
	
	switch ($operator) {
	case '=':
	case '!=':
		if (!in_array($search_term, $set)) return false;
		return true;
	case '%LIKE':
		foreach ($set as $word) {
			if (substr($word, -strlen($search_term)) === $search_term) return true;
		}
		return false;
	case 'LIKE%':
		foreach ($set as $word) {
			if (substr($word, 0, strlen($search_term)) === $search_term) return true;
		}
		return false;
	case '%LIKE%':
	case '%NOT LIKE%':
		foreach ($set as $word) {
			if (stristr($search_term, strval($word))) return true;
		}
		return false;
	}
	return true;
}

/**
 * check if field_name matches scope
 *
 * @param array $field field defintion
 * @param string $scope
 * @return string context where to search: field | detail_table | detail_field
 */
function zz_search_scope($field, $table, $scope) {
	$search_field = false;
	if (isset($field['display_field']) AND $scope === $field['display_field']) {
		$search_field = true;
	} elseif (!isset($field['sql']) AND !empty($field['field_name'])) {
		// check if scope = field_name but don't search in IDs
		// check if scope = table.field_name but don't search in IDs
		if ($scope === $field['field_name']) {
			$search_field = true;
		} elseif ($scope === $table.'.'.$field['field_name']) {
			$search_field = true;
		}
	} elseif (!empty($field['table']) AND $field['table'] === substr($scope, 0, strpos($scope, '.'))) {
		$look_for = substr($scope, strpos($scope, '.') + 1);
		foreach ($field['fields'] as $no => $detail_field) {
			if ($detail_field['field_name'] === $look_for) {
				if (!empty($field['search_in_subtable_query'])) return 'detail_table';
				return 'detail_field';
			}
			if (isset($detail_field['display_field']) AND $detail_field['display_field'] === $look_for) {
				if (!empty($field['search_in_subtable_query'])) return 'detail_table';
				return 'detail_field';
			}
		}
	}

	if ($search_field)
		return 'field';
	if (isset($field['table_name']) AND $scope === $field['table_name'])
		return 'detail_table';
	return false;
}

/**
 * checks if searchword is date, i. e. usable in searching for time/date 
 *
 * @param mixed $search_term
 * @return string
 */
function zz_search_time($search_term) {
	// allow searching with strtotime, but do not convert years (2000)
	// or year-month (2004-12)
	if (is_array($search_term)) return false;
	if (preg_match('/^\d{1,4}-*\d{0,2}-*\d{0,2}$/', trim($search_term))) return false;
	return zz_check_datetime($search_term);
}

/**
 * checks whether search string will match field at all
 * removes it from search query if not
 *
 * @param string $field_name
 * @param string $table
 * @param string $search_term
 * @return string
 */
function zz_search_check_field($field_name, $table, $search_term) {
	if (!strstr($table, '.')) $table = wrap_setting('db_name').'.'.$table;
	$column = zz_db_columns($table, $field_name);
	if (!$column) {
		zz_error_log([
			'msg_dev' => 'Column definition for field `%s.%s` cannot be read.',
			'msg_dev_args' => [$table, $field_name],
			'log_post_data' => false,
			'level' => E_USER_NOTICE
		]);
		return '';
	}
	$type = $column['Type'];
	if (str_ends_with($type, 'unsigned')) {
		$unsigned = true;
		$type = substr($type, 0, -9);
	} else {
		if (str_ends_with($type, 'signed')) {
			$type = substr($type, 0, -7);
		}
		$unsigned = false;
	}
	if ($pos = strpos($type, '('))
		$type = substr($type, 0, $pos);
	
	// check if numeric value
	switch ($type) {
	case 'int':
	case 'tinyint':
	case 'smallint':
	case 'mediumint':
	case 'bigint':
	case 'decimal':
	case 'float':
	case 'double':
	case 'real':
		if (is_array($search_term)) {
			foreach (array_keys($search_term) as $index) {
				if (!is_numeric($search_term[$index])) return false;
				if ($unsigned AND substr($search_term[$index], 0, 1) == '-') return false;
			}
			break;
		}
		if (strstr($search_term, ',')) {
			// oversimple solution for . or , as decimal separator
			// now you won't be able to search for 1,000,000 etc.
			$number = str_replace(',', '.', $search_term);
			if (is_numeric($number)) {
				$search_term = $number;
			}
		}
		if (!is_numeric($search_term)) return false;
		if ($unsigned AND substr($search_term, 0, 1) == '-') return false;
		break;
	case 'binary':
	case 'varbinary':
	case 'tinyblob':
	case 'mediumblob':
	case 'longblob':
		// no search here
		return false;

	case 'enum':
	case 'set':
		break;
	case 'geometry':
	case 'point':
	case 'linestring':
	case 'polygon':
	case 'multipoint':
	case 'multilinestring':
	case 'multipolygon':
	case 'geometrycollection':
		return false;
	default:
		break;
	}

	// BIT BOOLEAN SERIAL
	// DATE DATETIME TIMESTAMP TIME YEAR
	// CHAR VARCHAR TEXT TINYTEXT MEDIUMTEXT LONGTEXT

	return $search_term;
}

/**
 * performs a search in a detail table and returns IDs of main record to include
 * in search results
 *
 * @param array $field
 * @param string $table
 * @return array
 */
function zz_search_detail_table($field, $table) {
	global $zz_conf;

	$foreign_key = '';
	$detail_id_field_name = '';
	foreach ($field['fields'] as $f_index => $detail_field) {
		if (empty($detail_field['type'])) continue;
		if ($detail_field['type'] === 'foreign_key') {
			$foreign_key = $detail_field['field_name'];
			// do not search in foreign_key since this is the same
			// as the main record
			unset($field['fields'][$f_index]);
		} elseif ($detail_field['type'] === 'id') {
			$detail_id_field_name = $detail_field['field_name'];
		}
	}
	if (!$foreign_key) {
		zz_error_log([
			'msg_dev' => 'Subtable definition is wrong. There must be a field which is defined as "foreign_key".'
		]);
		zz_error();
		exit;
	}

	switch ($_GET['q']) {
	case 'NULL':
	case '!NULL':
		$detail_sql = 'SELECT %s.%s FROM %s
			LEFT JOIN %s
				ON %s.%s = %s.%s
			WHERE %%s(%s.%s)';
		$detail_sql = sprintf($detail_sql,
			$table, $zz_conf['int']['id']['field_name'], $table,
			$field['table'],
			$table, $zz_conf['int']['id']['field_name'], $field['table'], $foreign_key,
			$field['table'], $detail_id_field_name
		);
		if ($_GET['q'] === 'NULL') {
			$detail_sql = sprintf($detail_sql, 'ISNULL');
		} else {
			$detail_sql = sprintf($detail_sql, 'NOT ISNULL');
		}
		break;
	default:
		$sql = $field['sql'] ?? $field['subselect']['sql'] ?? '';
		if (!$sql) return [];
		$search_term = $_GET['q'];
		if (str_starts_with($search_term, '! ')) $search_term = substr($search_term, 2);
		$detail_sql = zz_search_sql($field['fields'], $sql, $field['table'], $search_term);
		break;
	}
	$ids = zz_db_fetch($detail_sql, $foreign_key, '', 'Search query for detail table.', E_USER_WARNING);
	if (!$ids) return [];
	if (in_array('', array_keys($ids))) {
		zz_error_log([
			'msg_dev' => 'Search: empty key for %s found in query',
			'msg_dev_args' => [$foreign_key],
			'query' => $detail_sql
		]);
		unset($ids['']);
	}
	return [$table.'.'.$zz_conf['int']['id']['field_name'] => array_keys($ids)];
}

/**
 * create query part out of detail table IDs
 *
 * @param array $data
 * @param string $operator
 * @return string
 */
function zz_search_detail_table_sql($data, $operator) {
	$new_data = [];
	foreach ($data as $detail_table) {
		foreach ($detail_table as $key => $ids) {
			$new_data[$key] = array_merge($ids, $new_data[$key] ?? []);
			$new_data[$key] = array_unique($new_data[$key]);
		}
	}
	$return = [];
	$template = in_array($operator, ['%NOT LIKE%']) ? '%s NOT IN (%s)' : '%s IN (%s)';
	foreach ($new_data as $key => $ids) {
		if (!$ids) continue;
		$return[] = sprintf($template, $key, implode(',', $ids));
	}
	return $return;
}

/** 
 * Generates search form and link to show all records
 * 
 * @param array $fields			field definitions ($zz)
 * @param string $table			name of database table
 * @param int $total_rows		total rows in database selection
 * @param string $count_rows	number of rows shown on html page
 * @return array				HTML output
 */
function zz_search_form($fields, $table, $total_rows, $count_rows) {
	// Search Form
	$search_form['top'] = '';
	$search_form['bottom'] = '';
	// don't show search form if all records are already shown
	if ($total_rows <= $count_rows AND !wrap_setting('zzform_search_form_always')
		AND empty($_GET['q'])) {
		return $search_form;
	}

	// show search form only if there are records as a result of this query; 
	// q: show search form if empty search result occured as well
	if (!$total_rows AND !isset($_GET['q'])) return $search_form;
	$search['q'] = $_GET['q'] ?? NULL;
	if ($search['q'] AND strstr($search['q'], '%%%'))
		$search['q'] = str_replace('%%%', '%\%\%', $search['q']);

	// fields that won't be used for search
	if ($qs = zzform_url('qs+qs_zzform')) { 
		// do not show edited record, limit, ...
		$unwanted_keys = [
			'q', 'scope', 'limit', 'mode', 'id', 'add', 'delete', 'insert',
			'update', 'noupdate', 'zzhash', 'edit', 'show', 'revise', 'merge'
		];
		$search['hidden_fields'] = zz_querystring_to_hidden(substr($qs, 1), $unwanted_keys);
		// remove unwanted keys from link
		$search['url_qs'] = zzform_url_remove($unwanted_keys, 'qs+qs_zzform'); 
	}
	$search['fields'] = [];
	foreach ($fields as $index => $field) {
		if (!zz_search_searchable($field)) continue;
		if (in_array($field['type'], ['subtable', 'foreign_table'])) {
			if (empty($field['subselect'])) continue;
			$fieldname = $field['table_name'];
		} elseif (!empty($field['row_value'])) {
			$fieldname = $field['row_value'];
		} else {
			$fieldname = (isset($field['display_field']) && $field['display_field']) 
				? $field['display_field'] : $table.'.'.$field['field_name'];
		}
		$search['fields'][$index] = $field;
		$search['fields'][$index]['field_name'] = $fieldname;
		$search['fields'][$index]['selected']
			= (isset($_GET['scope']) AND $_GET['scope'] === $fieldname) ? true : false;
	}
	$output = wrap_template('zzform-search', $search);

	$setting = wrap_setting('zzform_search');
	if ($setting === true) $setting = 'bottom'; // default!
	switch ($setting) {
	case 'top':
		// show form on top only if there are records!
		if ($count_rows) $search_form['top'] = $output;
		break;
	case 'both':
		// show form on top only if there are records!
		if ($count_rows) $search_form['top'] = $output;
	case 'bottom':
	default:
		$search_form['bottom'] = $output;
	}
	return $search_form;
}

/**
 * unsearchable fields
 *
 * @return bool false: no search possible
 */
function zz_search_searchable($field) {
	// is it a field explicitly excluded from search?
	if (!empty($field['exclude_from_search']))
		return false;

	// do not search IDs of detail tables
	if ($field['type'] === 'id' AND !empty($field['subtable_no']))
		return false;

	// is it a field which cannot be searched?
	if (in_array($field['type'], [
		'image', 'calculated', 'timestamp', 'upload_image', 'option', 'captcha'
	]))
		return false;

	// all other fields
	return true;
}

/**
 * check if a search operation is negative
 *
 * @param string $operator
 * @return string
 */
function zz_search_negative($operator) {
	if (in_array($operator, ['%NOT LIKE%'])) return 'NOT ';
	return '';
}
