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
 * modifies SQL query according to search results
 *
 * @param array $fields
 * @param string $sql
 * @param string $table
 * @return string $sql (un-)modified SQL query
 * @todo if there are subtables, part of this functions code is run redundantly
 */
function zz_search_sql($fields, $sql, $table) {
	// no changes if there's no query string
	if (empty($_GET['q'])) return $sql;

	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	if (wrap_setting('debug')) zz_debug('search query', $sql);
	static $calls = 0;
	$calls++;

	// get scope
	$scope = (!empty($_GET['scope']) AND $calls === 1) ? $_GET['scope'] : '';

	// replace tabs, duplicate spaces	
	if (strstr($_GET['q'], chr(9)))
		$_GET['q'] = str_replace(chr(9), ' ', $_GET['q']);
	while (strstr($_GET['q'], '  '))
		$_GET['q'] = str_replace('  ', ' ', $_GET['q']);

	// get search operator, globally for all fields
	$searchword = $_GET['q'];
	if (substr($searchword, 0, 1) == ' ' AND substr($searchword, -1) == ' ') {
		$searchop = '=';
		$searchword = trim($searchword);
	} elseif (substr($searchword, 0, 1) == ' ') {
		$searchop = 'LIKE%';
		$searchword = trim($searchword);
	} elseif (substr($searchword, -1) == ' ') {
		$searchop = '%LIKE';
		$searchword = trim($searchword);
	} elseif (substr($searchword, 0, 2) == '> ') {
		$searchop = '>';
		$searchword = trim(substr($searchword, 1));
	} elseif (substr($searchword, 0, 2) == '< ') {
		$searchop = '<';
		$searchword = trim(substr($searchword, 1));
	} elseif (substr($searchword, 0, 3) == '<= ') {
		$searchop = '<=';
		$searchword = trim(substr($searchword, 2));
	} elseif (substr($searchword, 0, 3) == '>= ') {
		$searchop = '>=';
		$searchword = trim(substr($searchword, 2));
	} elseif (substr($searchword, 0, 2) == '= ') {
		$searchop = '=';
		$searchword = trim(substr($searchword, 1));
	} elseif (substr($searchword, 0, 2) == '- ' 
		AND strstr(trim(substr($searchword, 1)), ' ')) {
		$searchop = 'BETWEEN';
		$searchword = trim(substr($searchword, 1));
		$searchword = explode(" ", $searchword);
	} elseif ($searchword === '!NULL' OR $searchword === 'NULL') {
		$searchop = $searchword;
		$searchword = false;
	} else {
		$searchop = '%LIKE%';
		// first slash will be ignored, this is used to escape reserved characters
		if (substr($searchword, 0, 1) == '\\') $searchword = substr($searchword, 1);
	}
	if (!is_array($searchword))
		$searchword = wrap_db_escape($searchword);
	else
		foreach (array_keys($searchword) as $index)
			$searchword[$index] = wrap_db_escape($searchword[$index]);

	// get fields
	$searchfields = [];
	$q_search = [];
	// fields that won't be used for search
	$search = false;
	$found = false;
	foreach ($fields as $field) {
		if (empty($field)) continue;
		if (!zz_search_searchable($field)) continue;
		
		if ($scope) {
			$search = zz_search_scope($field, $table, $scope);
		} else {
			if ($field['type'] === 'subtable') $search = 'subtable';
			elseif ($field['type'] === 'foreign_table') $search = false;
			else $search = 'field';
		}
		$subsearch = false;
		switch ($search) {
		case 'field':
			$subsearch = zz_search_field($field, $table, $searchop, $searchword);
			$found = true;
			break;
		case 'subtable':
			$subsearch = zz_search_subtable($field, $table);
			$found = true;
			break;
		case 'subfield':
			list ($subtable, $subfield_name) = explode('.', $scope);
			foreach ($field['fields'] as $no => $s_field) {
				if ($s_field['type'] === 'id') {
					$submain_id_fieldname = $s_field['field_name'];
				}
				if ($s_field['field_name'] === $subfield_name) {
					$subfield = $s_field;
				} elseif (!empty($s_field['display_field']) AND $s_field['display_field'] === $subfield_name) {
					$subfield = $s_field;
				}
			}
			$subsearch = zz_search_field($subfield, $subtable, $submain_id_fieldname, $searchword);
			$found = true;
			break;
		default:
			continue 2;
		}
		if ($subsearch) $q_search[] = $subsearch;
		// additional between search
		if (isset($field['search_between'])) {
			$q_search[] = sprintf($field['search_between'], '"'.$searchword.'"', '"'.$searchword.'"');
		}
	}

	if ($scope AND !$found) 
		wrap_static('page', 'status', 404);

	if ($q_search) {
		$q_search = '('.implode(' OR ', $q_search).')';
	} else {
		$q_search = 'NULL';
	}
	$sql = wrap_edit_sql($sql, 'WHERE', $q_search);

	if (wrap_setting('debug')) zz_debug("end; search query", $sql);
	return $sql;
}

/**
 * get part of SQL query for search in a field
 *
 * @param array $field field definition
 * @param string $table
 * @param string $searchop
 * @param string $searchword
 * @return string part of query
 */
function zz_search_field($field, $table, $searchop, $searchword) {
	// get field name
	if (isset($field['search'])) {
		$fieldname = $field['search'];
	} elseif (isset($field['display_field'])) {
		// it makes more sense to search through values than IDs
		$fieldname = $field['display_field'];
	} elseif (!empty($field['field_name'])) {
		// standard: use table- and field name
		$fieldname = $table.'.'.$field['field_name'];
		if ($searchword) {
			$searchword = zz_search_checkfield($field['field_name'], $table, $searchword);
			if (!$searchword) return '';
		}
	} else {
		return '';
	}

	if ($field['type'] === 'number' AND !is_numeric($searchword)) return '';

	// get searchword/operator, per field type
	$field_type = zz_get_fieldtype($field);
	
	list($searchword, $searchop) = zz_search_field_datetime($field_type, $searchword, $searchop);
	if (!$searchop) return '';

	// build search query part
	switch ($searchop) {
	case 'NULL':
		if (!zz_db_field_null($field['field_name'], wrap_setting('db_name').'.'.$table)) return '';
		return sprintf('ISNULL(%s)', $fieldname);
	case '!NULL':
		if (!zz_db_field_null($field['field_name'], wrap_setting('db_name').'.'.$table)) return '';
		return sprintf('NOT ISNULL(%s)', $fieldname);
	case '<':
		return sprintf('%s < "%s"', $fieldname, $searchword);
	case '<=':
		return sprintf('%s <= "%s"', $fieldname, $searchword);
	case '>':
		return sprintf('%s > "%s"', $fieldname, $searchword);
	case '>=':
		return sprintf('%s >= "%s"', $fieldname, $searchword);
	case 'BETWEEN':
		return sprintf('%s >= "%s" AND %s <= "%s"', $fieldname, $searchword[0],
			$fieldname, $searchword[1]);
	case 'QUARTER':
		// @todo: improve to use indices, BETWEEN year_begin and year_end ...
		return sprintf('(YEAR(%s) = "%s" AND QUARTER(%s) = "%s")', $fieldname, 
			$searchword[1], $fieldname, $searchword[0]);
	case 'YEAR':
		return sprintf('YEAR(%s) = %d', $fieldname, $searchword);
	case 'YEAR-MONTH':
		return sprintf('(YEAR(%s) = %d AND MONTH(%s) = %d)', $fieldname, 
			$searchword[1], $fieldname, $searchword[2]);
	case 'MONTH':
		return sprintf('MONTH(%s) = %d', $fieldname, $searchword);
	case '=':
		if (!zz_search_set_enum($searchop, $searchword, $field_type, $field)) return '';
		return sprintf('%s = "%s"', $fieldname, $searchword);
	case '%LIKE':
		if (!zz_search_set_enum($searchop, $searchword, $field_type, $field)) return '';
		$collation = zz_db_field_collation('search', $field, $table);
		if ($collation === NULL) return '';
		if ($field['type'] === 'datetime') // bug in MySQL 
			$fieldname = sprintf('DATE_FORMAT(%s, "%%Y-%%m-%%d %%H:%%i:%%s")', $fieldname);
		return sprintf('%s LIKE %s"%%%s"', $fieldname, $collation, $searchword);
	case 'LIKE%':
		if (!zz_search_set_enum($searchop, $searchword, $field_type, $field)) return '';
		$collation = zz_db_field_collation('search', $field, $table);
		if ($collation === NULL) return '';
		if ($field['type'] === 'datetime') // bug in MySQL
			$fieldname = sprintf('DATE_FORMAT(%s, "%%Y-%%m-%%d %%H:%%i:%%s")', $fieldname);
		return sprintf('%s LIKE %s"%s%%"', $fieldname, $collation, $searchword);
	case 'BETWEEN':
		if (in_array($field['type'], ['datetime', 'timestamp'])) {
			return sprintf('%s BETWEEN "%s" AND "%s"', $fieldname, $searchword[0], $searchword[1]);
		}
	case '%LIKE%':
	default:
		if (!zz_search_set_enum($searchop, $searchword, $field_type, $field)) return '';
		$collation = zz_db_field_collation('search', $field, $table);
		if ($collation === NULL) return '';
		if ($field['type'] === 'datetime') // bug in MySQL
			$fieldname = sprintf('DATE_FORMAT(%s, "%%Y-%%m-%%d %%H:%%i:%%s")', $fieldname);
		return sprintf('%s LIKE %s"%%%s%%"', $fieldname, $collation, $searchword);
	}
	return '';
}

/**
 * check if a field is date or time, only check against valid dates
 *
 * @param string $field_type
 * @param mixed $searchword
 * @param string $searchop
 * @return array
 */
function zz_search_field_datetime($field_type, $searchword, $searchop) {
	if (!in_array($field_type, ['date', 'datetime', 'time', 'timestamp'])) return [$searchword, $searchop];
	if (!$searchword) return [$searchword, $searchop];
	if (is_array($searchword)) return [$searchword, $searchop];

	// Quarter?
	if (preg_match('/q\d(.)[0-9]{4}/i', $searchword, $separator)) {
		$searchword = trim(substr($searchword, 1));
		$searchword = explode($separator[1], $searchword);
		return [$searchword, 'QUARTER'];
	}
	
	$timesearch = zz_search_time($searchword);
	switch ($field_type) {
	case 'datetime':
	case 'timestamp':
		if ($timesearch) {
			$searchword = date('Y-m-d H:i:s', $timesearch);
			switch ($searchop) {
			case '>':
			case '<=':
				if (str_ends_with($searchword, ' 00:00:00'))
					$searchword = date('Y-m-d', $timesearch).' 23:59:59';
				break;
			case '>=':
			case '<':
				break;
			default:
				if (str_ends_with($searchword, ' 00:00:00')) {
					$searchword = [
						date('Y-m-d', $timesearch).' 00:00:00',
						date('Y-m-d', $timesearch).' 23:59:59'
					];
					$searchop = 'BETWEEN';
				}
			}
		} elseif (preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/', $searchword) AND $searchop === '%LIKE%') {
			$searchword = [
				$searchword.' 00:00:00',
				$searchword.' 23:59:59'
			];
			$searchop = 'BETWEEN';
		}
		break;
	case 'time':
		if ($timesearch) $searchword = date('H:i:s', $timesearch);
		if (preg_match('/^[0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/', $searchword) AND $searchop === '%LIKE%') $searchop = '=';
		break;
	case 'date':
		preg_match('/^([0-9]+)$/', $searchword, $matches);
		if ($timesearch) {
			$searchword = date('Y-m-d', $timesearch);
		} elseif (preg_match('/^([0-9]+)\.([0-9]+)\.$/', $searchword, $matches)) {
			$searchword = sprintf('%1$02d-%2$02d', $matches[2], $matches[1]);
		} elseif (preg_match('/^(\d{1,4})-(\d{0,2})$/', $searchword, $matches)) {
			$searchop = 'YEAR-MONTH';
			$searchword = $matches;
		} elseif (preg_match('/^(\d{1,2})$/', $searchword, $matches)) {
			$searchop = 'MONTH';
			$searchword = $matches[1];
		} elseif (preg_match('/^(\d{1,4})$/', $searchword, $matches)) {
			$searchop = 'YEAR';
			$searchword = $matches[1];
		}
		if ($searchop === '%LIKE%' AND preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/', $searchword)) $searchop = '=';
		break;
	}
	if (!is_array($searchword) AND !preg_match('/^[0-9:\-% ]+$/', $searchword)) return [false, false];
	return [$searchword, $searchop];
}

/**
 * for ENUM and SET fields, check if value to search for exists directly
 * to improve SQL query
 *
 * @param string $searchop
 * @param string $searchword
 * @param string $field_type
 * @param array $field
 * @return bool false: don't query, search word does not exist
 */
function zz_search_set_enum($searchop, $searchword, $field_type, $field) {
	if ($field_type !== 'select') return true;
	if (!empty($field['enum'])) $set = $field['enum'];
	elseif (!empty($field['set'])) $set = $field['set'];
	else return true;
	
	switch ($searchop) {
	case '=':
		if (!in_array($searchword, $set)) return false;
		return true;
	case '%LIKE':
		foreach ($set as $word) {
			if (substr($word, -strlen($searchword)) === $searchword) return true;
		}
		return false;
	case 'LIKE%':
		foreach ($set as $word) {
			if (substr($word, 0, strlen($searchword)) === $searchword) return true;
		}
		return false;
	case '%LIKE%':
		foreach ($set as $word) {
			if (stristr($searchword, strval($word))) return true;
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
 * @return string where to search: field | subtable | subfield
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
		foreach ($field['fields'] as $no => $subfield) {
			if ($subfield['field_name'] === $look_for) {
				if (!empty($field['search_in_subtable_query'])) return 'subtable';
				return 'subfield';
			}
			if (isset($subfield['display_field']) AND $subfield['display_field'] === $look_for) {
				if (!empty($field['search_in_subtable_query'])) return 'subtable';
				return 'subfield';
			}
		}
	}

	if ($search_field)
		return 'field';
	if (isset($field['table_name']) AND $scope === $field['table_name'])
		return 'subtable';
	return false;
}

/**
 * checks if searchword is date, i. e. usable in searching for time/date 
 *
 * @param mixed $searchword
 * @return int time
 */
function zz_search_time($searchword) {
	// allow searching with strtotime, but do not convert years (2000)
	// or year-month (2004-12)
	if (is_array($searchword)) return false;
	if (preg_match('/^\d{1,4}-*\d{0,2}-*\d{0,2}$/', trim($searchword))) return false;
	$date = zz_check_datetime($searchword);
	return strtotime($date);
}

/**
 * checks whether search string will match field at all
 * removes it from search query if not
 *
 * @param string $field_name
 * @param string $table
 * @param string $searchword
 * @return string
 */
function zz_search_checkfield($field_name, $table, $searchword) {
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
		if (is_array($searchword)) {
			foreach (array_keys($searchword) as $index) {
				if (!is_numeric($searchword[$index])) return false;
				if ($unsigned AND substr($searchword[$index], 0, 1) == '-') return false;
			}
			break;
		}
		if (strstr($searchword, ',')) {
			// oversimple solution for . or , as decimal separator
			// now you won't be able to search for 1,000,000 etc.
			$number = str_replace(',', '.', $searchword);
			if (is_numeric($number)) {
				$searchword = $number;
			}
		}
		if (!is_numeric($searchword)) return false;
		if ($unsigned AND substr($searchword, 0, 1) == '-') return false;
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

	return $searchword;
}

/**
 * performs a search in a subtable and returns IDs of main record to include
 * in search results
 *
 * @param array $field
 * @param string $table
 * @return string part of SQL query
 */
function zz_search_subtable($field, $table) {
	global $zz_conf;

	$foreign_key = '';
	$sub_id_fieldname = '';
	foreach ($field['fields'] as $f_index => $subfield) {
		if (empty($subfield['type'])) continue;
		if ($subfield['type'] === 'foreign_key') {
			$foreign_key = $subfield['field_name'];
			// do not search in foreign_key since this is the same
			// as the main record
			unset($field['fields'][$f_index]);
		} elseif ($subfield['type'] === 'id') {
			$sub_id_fieldname = $subfield['field_name'];
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
		$subsql = 'SELECT %s.%s FROM %s
			LEFT JOIN %s
				ON %s.%s = %s.%s
			WHERE %%s(%s)';
		$subsql = sprintf($subsql,
			$table, $zz_conf['int']['id']['field_name'], $table,
			$field['table'],
			$table, $zz_conf['int']['id']['field_name'], $field['table'], $foreign_key,
			$sub_id_fieldname
		);
		if ($_GET['q'] === 'NULL') {
			$subsql = sprintf($subsql, 'ISNULL');
		} else {
			$subsql = sprintf($subsql, 'NOT ISNULL');
		}
		break;
	default:
		$sql = '';
		if (!empty($field['sql'])) $sql = $field['sql'];
		elseif (!empty($field['subselect']['sql'])) $sql = $field['subselect']['sql'];
		else return false;
		$subsql = zz_search_sql($field['fields'], $sql, $field['table']);
		break;
	}
	$ids = zz_db_fetch($subsql, $foreign_key, '', 'Search query for subtable.', E_USER_WARNING);
	if (!$ids) return false;
	if (in_array('', array_keys($ids))) {
		zz_error_log([
			'msg_dev' => 'Search: empty key for %s found in query',
			'msg_dev_args' => [$foreign_key],
			'query' => $subsql
		]);
		unset($ids['']);
	}
	return $table.'.'.$zz_conf['int']['id']['field_name'].' IN ('.implode(',', array_keys($ids)).')';
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
	$search['q'] = isset($_GET['q']) ? $_GET['q'] : NULL;
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
		if ($field['type'] === 'subtable') {
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

	// is it a field which cannot be searched?
	if (in_array($field['type'], [
		'image', 'calculated', 'timestamp', 'upload_image', 'option', 'captcha'
	]))
		return false;

	// all other fields
	return true;
}
