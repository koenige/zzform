<?php

/**
 * zzform
 * Display and evaluation of search form below/above table
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2013, 2015-2016 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * modifies SQL query according to search results
 *
 * @param array $fields
 * @param string $sql
 * @param string $table
 * @param string $main_id_fieldname
 * @global array $zz_conf main configuration variables
 * @global array $zz_error
 * @return string $sql (un-)modified SQL query
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo if there are subtables, part of this functions code is run redundantly
 */
function zz_search_sql($fields, $sql, $table, $main_id_fieldname) {
	// no changes if there's no query string
	if (empty($_GET['q'])) return $sql;

	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if ($zz_conf['modules']['debug']) zz_debug('search query', $sql);
	static $calls;
	$calls++;

	// get scope
	$scope = (!empty($_GET['scope']) AND $calls === 1) ? $_GET['scope'] : '';
	
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
	$searchword = wrap_db_escape($searchword);

	// get fields
	$searchfields = array();
	$q_search = array();
	// fields that won't be used for search
	$unsearchable_fields = array('image', 'calculated', 'timestamp', 'upload_image', 'option'); 
	$search = false;
	$found = false;
	foreach ($fields as $field) {
		if (empty($field)) continue;
		// is it a field explicitly excluded from search?
		if (!empty($field['exclude_from_search'])) continue;
		// is it a field which cannot be searched?
		if (in_array($field['type'], $unsearchable_fields)) continue;
		
		if ($scope) {
			$search = zz_search_scope($field, $table, $scope);
		} else {
			if ($field['type'] === 'subtable') $search = 'subtable';
			else $search = 'field';
		}
		$subsearch = false;
		switch ($search) {
		case 'field':
			$subsearch = zz_search_field($field, $table, $searchop, $searchword);
			$found = true;
			break;
		case 'subtable':
			$subsearch = zz_search_subtable($field, $table, $main_id_fieldname);
			$found = true;
			break;
		default:
			continue;
		}
		if ($subsearch) $q_search[] = $subsearch;
		// additional between search
		if (isset($field['search_between'])) {
			$q_search[] = sprintf($field['search_between'], '"'.$searchword.'"', '"'.$searchword.'"');
		}
	}

	if ($scope AND !$found) 
		$zz_conf['int']['http_status'] = 404;

	if ($q_search) {
		$q_search = '('.implode(' OR ', $q_search).')';
	} else {
		$q_search = 'NULL';
	}
	$sql = zz_edit_sql($sql, 'WHERE', $q_search);

	if ($zz_conf['modules']['debug']) zz_debug("end; search query", $sql);
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
	global $zz_conf;
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

	// get searchword/operator, per field type
	$field_type = zz_get_fieldtype($field);
	$datetime = in_array($field_type, array('date', 'datetime', 'time', 'timestamp')) ? true : false;
	if ($datetime and $searchword AND !is_array($searchword)) {
		$timesearch = zz_search_time($searchword);
		if ($timesearch) {
			switch ($field_type) {
			case 'datetime':
				$searchword = date('Y-m-d', $timesearch).'%';
				if ($searchop == '%LIKE%') $searchop = 'LIKE%';
				break;
			case 'time':
				$searchword = date('H:i:s', $timesearch);
				if ($searchop == '%LIKE%') $searchop = '=';
				break;
			case 'date':
				$searchword = date('Y-m-d', $timesearch);
				if ($searchop == '%LIKE%') $searchop = '=';
				break;
			}
		} elseif (preg_match('/q\d(.)[0-9]{4}/i', $searchword, $separator)) {
			$searchword = trim(substr($searchword, 1));
			$searchword = explode($separator[1], $searchword);
			$searchop = "QUARTER";
		}
	}

	// build search query part
	switch ($searchop) {
	case 'NULL':
		if (!zz_db_field_null($field['field_name'], $zz_conf['db_name'].'.'.$table)) return '';
		return sprintf('ISNULL(%s)', $fieldname);
	case '!NULL':
		if (!zz_db_field_null($field['field_name'], $zz_conf['db_name'].'.'.$table)) return '';
		return sprintf('!ISNULL(%s)', $fieldname);
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
	case '=':
		return sprintf('%s = "%s"', $fieldname, $searchword);
	case '%LIKE':
		$collation = zz_db_field_collation('search', $table, $field);
		if ($collation === NULL) return '';
		if ($field['type'] === 'datetime') // bug in MySQL 
			$fieldname = sprintf('DATE_FORMAT(%s, "%%Y-%%m-%%d %%H:%%i:%%s")', $fieldname);
		return sprintf('%s LIKE %s"%%%s"', $fieldname, $collation, $searchword);
	case 'LIKE%':
		$collation = zz_db_field_collation('search', $table, $field);
		if ($collation === NULL) return '';
		if ($field['type'] === 'datetime') // bug in MySQL
			$fieldname = sprintf('DATE_FORMAT(%s, "%%Y-%%m-%%d %%H:%%i:%%s")', $fieldname);
		return sprintf('%s LIKE %s"%s%%"', $fieldname, $collation, $searchword);
	case '%LIKE%':
	default:
		$collation = zz_db_field_collation('search', $table, $field);
		if ($collation === NULL) return '';
		if ($field['type'] === 'datetime') // bug in MySQL
			$fieldname = sprintf('DATE_FORMAT(%s, "%%Y-%%m-%%d %%H:%%i:%%s")', $fieldname);
		return sprintf('%s LIKE %s"%%%s%%"', $fieldname, $collation, $searchword);
	}
	return '';
}

/**
 * check if field_name matches scope
 *
 * @param array $field field defintion
 * @param string $scope
 * @return string where to search: field | subtable
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
	return strtotime($searchword);
}

/**
 * checks whether search string will match field at all
 * removes it from search query if not
 *
 * @param string $field_name
 * @param string $table
 * @param string $searchword
 * @global array $zz_conf
 * @return string
 */
function zz_search_checkfield($field_name, $table, $searchword) {
	global $zz_conf;
	if (!strstr($table, '.')) $table = $zz_conf['db_name'].'.'.$table;
	$column = zz_db_columns($table, $field_name);
	$type = $column['Type'];
	if ($pos = strpos($type, '('))
		$type = substr($type, 0, $pos);
	$unsigned = substr($column['Type'], -8) == 'unsigned' ? true: false;
	
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
 * @param string $main_id_fieldname
 * @return string part of SQL query
 */
function zz_search_subtable($field, $table, $main_id_fieldname) {
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
		global $zz_error;
		$zz_error[]['msg_dev'] = zz_text('Subtable definition is wrong. There must be a field which is defined as "foreign_key".');
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
			$table, $main_id_fieldname, $table,
			$field['table'],
			$table, $main_id_fieldname, $field['table'], $foreign_key,
			$sub_id_fieldname
		);
		if ($_GET['q'] === 'NULL') {
			$subsql = sprintf($subsql, 'ISNULL');
		} else {
			$subsql = sprintf($subsql, '!ISNULL');
		}
		break;
	default:
		$subsql = zz_search_sql($field['fields'], $field['sql'], $field['table'], $main_id_fieldname);
		break;
	}
	$ids = zz_db_fetch($subsql, $foreign_key, '', 'Search query for subtable.', E_USER_WARNING);
	if (!$ids) return false;
	return $table.'.'.$main_id_fieldname.' IN ('.implode(',', array_keys($ids)).')';
}

/** 
 * Generates search form and link to show all records
 * 
 * @param array $fields			field definitions ($zz)
 * @param string $table			name of database table
 * @param int $total_rows		total rows in database selection
 * @param string $count_rows	number of rows shown on html page
 * @return string $output		HTML output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_search_form($fields, $table, $total_rows, $count_rows) {
	global $zz_conf;
	// Search Form
	$search_form['top'] = '';
	$search_form['bottom'] = '';
	// don't show search form if all records are already shown
	if ($total_rows <= $count_rows AND empty($zz_conf['search_form_always'])
		AND empty($_GET['q'])) {
		return $search_form;
	}

	// show search form only if there are records as a result of this query; 
	// q: show search form if empty search result occured as well
	if (!$total_rows AND !isset($_GET['q'])) return $search_form;

	$output = '';
	$self = $zz_conf['int']['url']['self'];
	// fields that won't be used for search
	$unsearchable_fields = array('image', 'calculated', 'timestamp', 'upload_image', 'option');
	$output = "\n".'<form method="GET" action="%s" class="zzsearch" accept-charset="%s"><p>';
	$output = sprintf($output, $self, $zz_conf['character_set']);
	if ($qs = $zz_conf['int']['url']['qs'].$zz_conf['int']['url']['qs_zzform']) { 
		// do not show edited record, limit, ...
		$unwanted_keys = array(
			'q', 'scope', 'limit', 'mode', 'id', 'add', 'delete', 'insert',
			'update', 'noupdate', 'zzhash'
		); 
		$output .= zz_querystring_to_hidden(substr($qs, 1), $unwanted_keys);
		// remove unwanted keys from link
		$self .= zz_edit_query_string($qs, $unwanted_keys); 
	}
	$output.= '<input type="search" size="30" name="q"';
	if (isset($_GET['q'])) $output.= ' value="'.zz_html_escape($_GET['q']).'"';
	$output.= '>';
	$output.= '<input type="submit" value="'.zz_text('search').'">';
	$output.= ' '.zz_text('in').' ';	
	$output.= '<select name="scope">';
	$output.= '<option value="">'.zz_text('all fields').'</option>'."\n";
	foreach ($fields as $field) {
		if (in_array($field['type'], $unsearchable_fields)) continue;
		if (!empty($field['exclude_from_search'])) continue;
		if ($field['type'] === 'subtable') {
			if (empty($field['subselect'])) continue;
			$fieldname = $field['table_name'];
		} else {
			$fieldname = (isset($field['display_field']) && $field['display_field']) 
				? $field['display_field'] : $table.'.'.$field['field_name'];
		}
		$output.= '<option value="'.$fieldname.'"';
		if (isset($_GET['scope']) AND $_GET['scope'] == $fieldname) 
			$output.= ' selected="selected"';
		$output.= '>'.strip_tags($field['title']).'</option>'."\n";
	}
	$output.= '</select>';
	if (!empty($_GET['q'])) {
		$output.= ' &nbsp;<a href="'.$self.'">'.zz_text('Show all records').'</a>';
	}
	$output.= '</p></form>'."\n";

	if ($zz_conf['search'] === true) $zz_conf['search'] = 'bottom'; // default!
	switch ($zz_conf['search']) {
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

?>