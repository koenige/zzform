<?php

/**
 * zzform
 * Filter functions
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2010-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * checks filter, sets default values and identifier
 *
 * @param array $zz
 * @return void (modified array filter, array filter_active in $zz) 
 * @global array $zz_conf
 */
function zz_filter_defaults(&$zz) {
	global $zz_conf;
	if ($zz['filter'] AND !empty($_GET['filter']) AND is_array($_GET['filter']))
		$zz['filter_active'] = $_GET['filter'];
	$identifiers = [];

	// if there are filters:
	// initialize filter, set defaults
	foreach ($zz['filter'] AS $index => $filter) {
		if (!$filter) {
			unset($zz['filter'][$index]);
			continue;
		}
		// get identifier from title if not set
		if (empty($filter['identifier'])) {
			$filter['identifier'] = urlencode(strtolower($filter['title']));
			$zz['filter'][$index]['identifier'] = $filter['identifier'];
		}
		$identifiers[] = $filter['identifier'];
		// set default filter, default default filter is 'all'
		if (empty($filter['default_selection'])) continue;
		if (isset($zz['filter_active'][$filter['identifier']])) continue;
		$zz['filter_active'][$filter['identifier']] = is_array($filter['default_selection'])
			? key($filter['default_selection'])
			: $filter['default_selection'];
	}

	// check for invalid filters
	foreach (array_keys($zz['filter_active']) AS $identifier) {
		if (in_array($identifier, $identifiers)) continue;
		wrap_static('page', 'status', 404);
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
			$zz_conf['int']['url']['qs_zzform'], ['filter['.$identifier.']']
		);
		zz_filter_invalid(zz_htmltag_escape($identifier));
		// get rid of filter
		unset($zz['filter_active'][$identifier]);
	}
}

/**
 * checks filter, gets selection, sets hierarchy values
 *
 * @param array $zz
 * @param array $list
 * @return void ($list, 'hierarchy' will be changed if corresponding filter,
 *	$zz 'filter', might be changed)
 */
function zz_apply_filter(&$zz, &$list) {
	global $zz_conf;
	if (!$zz['filter']) return $zz;

	// set filter for complete form
	foreach ($zz['filter'] AS $index => &$filter) {
		if (!isset($filter['selection'])) $filter['selection'] = [];
		// get 'selection' if sql query is given
		if (!empty($filter['sql'])) {
			if (!empty($filter['depends_on']) 
			AND isset($zz['filter'][$filter['depends_on']])) {
				$depends_on = $zz['filter'][$filter['depends_on']];
				if (!empty($zz['filter_active'][$depends_on['identifier']])) {
					$where = sprintf('%s = %s',
						$depends_on['where'],
						wrap_db_escape($zz['filter_active'][$depends_on['identifier']])
					);
					$filter['sql'] = wrap_edit_sql($filter['sql'], 'WHERE', $where);
				}
				$zz['filter'][$filter['depends_on']]['subfilter'][] = $index;
			}
			if (!empty($filter['sql_translate'])) {
				$elements_t = zz_db_fetch($filter['sql'], '_dummy_id_', 'numeric');
				$elements_t = zz_translate($filter, $elements_t);
				$elements = [];
				foreach ($elements_t as $element) {
					// first value = ID, second value = label
					$elements[array_shift($element)] = array_shift($element);
				}
			} else {
				$elements = zz_db_fetch($filter['sql'], '_dummy_id_', 'key/value');
			}
			if (zz_error_exit()) continue;
			// don't show filter if we have only one element
			if (count($elements) <= 1) {
				unset($zz['filter'][$index]);
				continue;
			}
			foreach ($elements as $key => $value) {
				if (is_null($value)) {
					$filter['selection']['NULL'] = wrap_text('(no value)');
				} else {
					$filter['selection'][$key] = $value;
				}
			}
		} elseif ($filter['type'] === 'function') {
			$records = zz_filter_function($filter, $zz['sql']);
			if (empty($records['unset'])) {
				unset($zz['filter'][$index]);
				continue;
			}
			if (count($records['all']) === count($records['unset'])) {
				unset($zz['filter'][$index]);
				continue;
			}
		} elseif (!empty($filter['remove_if_empty'])) {
			$sql = wrap_edit_sql($zz['sql'], 'SELECT', 'DISTINCT '.$filter['where'], 'replace');
			$data = wrap_db_fetch($sql, '_dummy_', 'numeric');
			if (count($data) <= 1) {
				unset($zz['filter'][$index]);
				continue;
			}
		}

		if (!$filter['selection'] AND !empty($filter['default_selection'])) {
			if (is_array($filter['default_selection'])) {
				$filter['selection'] = $filter['default_selection'];
			} else {
				$filter['selection'] = [
					$filter['default_selection'] => $filter['default_selection']
				];
			}
		}
		if (!$zz['filter_active']) continue;
		if (!in_array($filter['identifier'], array_keys($zz['filter_active']))) continue;
		if ($filter['type'] !== 'show_hierarchy') continue;

		$selection = zz_in_array_str(
			$zz['filter_active'][$filter['identifier']], array_keys($filter['selection'])
		);
		if ($selection) {
			if ($list['hierarchy']['display_in'])
				$list['hierarchy']['id'] = $selection;
			// @todo if user searches something, the hierarchical view
			// will be ignored and therefore this hierarchical filter does
			// not work. think about a better solution.
		} else {
			if (str_starts_with($zz_conf['int']['url']['qs_zzform'], '?'))
				$zz_conf['int']['url']['qs_zzform'] = substr($zz_conf['int']['url']['qs_zzform'], 1);
			parse_str($zz_conf['int']['url']['qs_zzform'], $qs);
			unset($qs['filter'][$filter['identifier']]);
			if (empty($qs['filter'])) unset($qs['filter']);
			$zz_conf['int']['url']['qs_zzform'] = http_build_query($qs);
			$link = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
				.$zz_conf['int']['url']['?&'].$zz_conf['int']['url']['qs_zzform'];

			zz_error_log([
				'msg' => ['This filter does not exist: %s', '<a href="%s">List without this filter</a>'],
				'msg_args' => [zz_htmltag_escape($zz['filter_active'][$filter['identifier']]), $link],
				'level' => E_USER_NOTICE,
				'status' => 404
			]);
			zz_error_exit(true);
		}
	}
}

/**
 * get IDs for 'function' filters, save which are unset
 *
 * @param array $filter
 * @param string $sql
 * @return array
 */
function zz_filter_function($filter, $sql) {
	$sql = wrap_edit_sql($sql, 'SELECT', $filter['where'], 'replace');
	$record_ids = wrap_db_fetch($sql, '_dummy_', 'single value');
	$unset = [];
	foreach ($record_ids as $record_id) {
		$result = $filter['function']($record_id);
		if ($result === NULL) unset($record_ids[$record_id]);
		elseif (!$result) $unset[$record_id] = $record_id;
	}
	return ['all' => $record_ids, 'unset' => $unset];
}

/**
 * Apply filter to SQL query
 * test if all filters are valid filters
 *
 * @param array $filters
 * @param string $sql
 * @param array $filter_active = $zz['filter_active']
 *		wrong filters may be unset
 * @return string $sql
 * @see zz_filter_defaults() for check for invalid filters
 */
function zz_filter_sql($filters, $sql, &$filter_active) {
	if (zz_filter_invalid()) return '';

	// no filter was selected, no change
	if (!$filter_active) return $sql;

	foreach ($filters AS $filter) {
		if (!in_array($filter['identifier'], array_keys($filter_active))) continue;
		$filter_value = $filter_active[$filter['identifier']];

		$old_sql = $sql;
		if (isset($filter['sql_join']))
			$sql = wrap_edit_sql($sql, 'JOIN', $filter['sql_join']);

		// where_if-Filter?
		if (!empty($filter['where_if'])) {
			if (!array_key_exists($filter_value, $filter['where_if'])) {
				zz_filter_invalid_value($filter, $filter_value);
				// remove invalid filter
				unset($filter_active[$filter['identifier']]);
				continue;
			}
			$sql = wrap_edit_sql($sql, 'WHERE', $filter['where_if'][$filter_value]);
			continue;
		}
		
		// where-Filter?
		if (empty($filter['where'])) continue;
		if (!isset($filter['default_selection'])) $filter['default_selection'] = '';
		
		if ($filter['type'] === 'show_hierarchy'
			AND false !== zz_in_array_str($filter_value, array_keys($filter['selection']))
		) {
			$sql = wrap_edit_sql($sql, 'WHERE', $filter['where'].' = "'.$filter_value.'"');
		} elseif (false !== zz_in_array_str($filter_value, array_keys($filter['selection']))
			AND $filter['type'] === 'list') {
			// it's a valid filter, so apply it.
			if ($filter_value === 'NULL') {
				$sql = wrap_edit_sql($sql, 'WHERE', 'ISNULL('.$filter['where'].')');
			} elseif ($filter_value === '!NULL') {
				$sql = wrap_edit_sql($sql, 'WHERE', 'NOT ISNULL('.$filter['where'].')');
			} elseif (strstr($filter['where'], '%s')) {
				$sql = wrap_edit_sql($sql, 'WHERE', sprintf($filter['where'], wrap_db_escape($filter_value)));
			} else {
				// allow ! as a symbol (may be escaped by \)
				// for !=
				$equals = ' = ';
				if (substr($filter_value, 0, 1) === '!') {
					$filter_value = substr($filter_value, 1);
					$equals = ' != ';
				} elseif (substr($filter_value, 0, 1) === '\\') {
					$filter_value = substr($filter_value, 1);
				}
				$sql = wrap_edit_sql($sql, 'WHERE', $filter['where'].$equals.'"'.wrap_db_escape($filter_value).'"');
			}
		} elseif ($filter['type'] === 'list' AND $filter_values = zz_filter_or($filter_value, array_keys($filter['selection']))) {
			// @todo support all of the code above, too
			// @todo allow to select this somehow, currently only available via URL manipulation
			$or_conditions = [];
			foreach ($filter_values as $f_value) {
				$or_conditions[] = $filter['where'].' = "'.wrap_db_escape($f_value).'"';
			}
			$sql = wrap_edit_sql($sql, 'WHERE', implode(' OR ', $or_conditions));
		} elseif ($filter['type'] === 'function') {
			$records = zz_filter_function($filter, $sql);
			foreach ($records['all'] as $record_id) {
				if ($filter_value AND in_array($record_id, $records['unset'])) {
					unset($records['all'][$record_id]);
				} elseif (!$filter_value AND !in_array($record_id, $records['unset'])) {
					unset($records['all'][$record_id]);
				}
			}
			if ($records['all']) {
				$sql = wrap_edit_sql($sql, 'WHERE', sprintf('%s IN (%s)', $filter['where'], implode(',', $records['all'])));
			}
		} elseif ($filter_value === '0' AND $filter['default_selection'] !== '0'
			AND $filter['default_selection'] !== 0) {
			// do nothing
		} elseif ($filter['type'] === 'list' AND is_array($filter['where'])) {
			// valid filter with several wheres
			$wheres = [];
			foreach ($filter['where'] AS $filter_where) {
				if ($filter_value === 'NULL') {
					$wheres[] = 'ISNULL('.$filter_where.')';
				} elseif ($filter_value === '!NULL') {
					$wheres[] = 'NOT ISNULL('.$filter_where.')';
				} else {
					$wheres[] = $filter_where.' = "'.$filter_value.'"';
				}
			}
			$sql = wrap_edit_sql($sql, 'WHERE', implode(' OR ', $wheres));
		} elseif ($filter['type'] === 'like') {
			// valid filter with LIKE
			if (empty($filter['like'])) {
				$filter['like'] = '%%%s%%';
			}
			$like = sprintf($filter['like'], $filter_value);
			$sql = wrap_edit_sql($sql, 'WHERE', $filter['where'].' LIKE "'.$like.'"');
		} else {
			// invalid filter value, show list without filter
			$sql = $old_sql;
			zz_filter_invalid_value($filter, $filter_value);
			// remove invalid filter
			unset($filter_active[$filter['identifier']]);
		}
	}
	return $sql;
}

/**
 * log if a filter has an invalid value
 * edit internal query string to remove that invalid value
 *
 * @param array $filter
 * @param string $value
 * @return void
 */
function zz_filter_invalid_value($filter, $value) {
	global $zz_conf;

	if (empty($filter['ignore_invalid_filters'])) {
		wrap_static('page', 'status', 404);
		wrap_static('page', 'error_type', E_USER_NOTICE);
		zz_error_log([
			'msg' => '“%s” is not a valid value for the selection “%s”. Please select a different filter.', 
			'msg_args' => [zz_htmltag_escape($value), $filter['title']],
			'level' => E_USER_NOTICE
		]);
	}
	// remove invalid filter from internal query string
	$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
		$zz_conf['int']['url']['qs_zzform'], sprintf('filter[%s]', $filter['identifier'])
	);
}

/**
 * test filter identifiers if they exist
 *
 * @param string $filter (opitonal, add to list of invalid filters)
 * @return bool true if there are invalid filters
 */
function zz_filter_invalid($filter = false) {
	static $invalid_filters = [];
	global $zz_conf;
	if ($filter) {
		$invalid_filters[] = $filter;
		return true;
	}

	$error = false;
	foreach ($invalid_filters AS $identifier) {
		$filter = zz_htmltag_escape($identifier);
		$link = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
			.$zz_conf['int']['url']['?&'].$zz_conf['int']['url']['qs_zzform'];
		zz_error_log([
			'msg' => [
				'A filter for the selection “%s” does not exist.',
				'<a href="%s">List without this filter</a>'
			],
			'msg_args' => [$filter, $link],
			'level' => E_USER_NOTICE
		]);
		$error = true;
	}
	return $error;
}

/**
 * check if it is an OR filter with a | and valid values
 *
 * @param string $filter_value
 * @param array $selections
 * @return array
 */
function zz_filter_or($filter_value, $selections) {
	if (!strstr($filter_value, '|')) return [];
	$filter_values = explode('|', $filter_value);
	foreach ($filter_values as $value) {
		if (!in_array($value, $selections)) return false;
	}
	return $filter_values;
}
