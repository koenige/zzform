<?php

/**
 * zzform
 * Filter functions
 *
 * – zz_filter_defaults() is called at the beginning, to check GET variables and
 * set WHERE condition if applicable
 * – zz_filter_list() evaluates filter for list query and prepares HTML output
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2010-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * checks filter, sets default values and identifier
 *
 * @param array $zz
 * @return void (modified array filter, array filter_active in $zz) 
 */
function zz_filter_defaults(&$zz) {
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
		zzform_url_remove([sprintf('filter[%s]', $identifier)]);
		zz_filter_invalid(zz_htmltag_escape($identifier));
		// get rid of filter
		unset($zz['filter_active'][$identifier]);
	}
}

/**
 * use filters in list view
 *
 * @param array $zz
 * @param array $ops
 * @param array $list
 * @return void
 */
function zz_filter_list(&$zz, &$ops, &$list) {
	// set 'selection', $list['hierarchy']
	zz_filter_apply($zz, $list);
	$ops['filter_titles'] = zz_output_filter_title($zz['filter'], $zz['filter_active']);
	if (zz_filter_invalid()) return false;

	// modify SQL query depending on filter
	$old_sql = $zz['sql'];
	$zz['sql'] = zz_filter_sql($zz['filter'], $zz['sql'], $zz['filter_active']);
	if ($old_sql !== $zz['sql']) $zz['sqlcount'] = NULL;

	// output filter
	if (!$zz['filter']) return true;
	if ($ops['mode'] === 'export') return true;
	if (!wrap_setting('zzform_filter_position')) return true;
	$filter = zz_filter_selection($zz['filter'], $zz['filter_active']);
	if (in_array(wrap_setting('zzform_filter_position'), ['top', 'both']))
		$ops['filter_top'] = $filter;
	if (in_array(wrap_setting('zzform_filter_position'), ['bottom', 'both']))
		$ops['filter_bottom'] = $filter;

	return true;
}

/**
 * checks filter, gets selection, sets hierarchy values
 *
 * @param array $zz
 * @param array $list
 * @return void ($list, 'hierarchy' will be changed if corresponding filter,
 *	$zz 'filter', might be changed)
 */
function zz_filter_apply(&$zz, &$list) {
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
			$link = zzform_url_remove([sprintf('filter[%s]', $filter['identifier'])], zzform_url('self'));
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
	zzform_url_remove(sprintf('filter[%s]', $filter['identifier']));
}

/**
 * test filter identifiers if they exist
 *
 * @param string $filter (opitonal, add to list of invalid filters)
 * @return bool true if there are invalid filters
 */
function zz_filter_invalid($filter = false) {
	static $invalid_filters = [];
	if ($filter) {
		$invalid_filters[] = $filter;
		return true;
	}

	$error = false;
	foreach ($invalid_filters AS $identifier) {
		zz_error_log([
			'msg' => [
				'A filter for the selection “%s” does not exist.',
				'<a href="%s">List without this filter</a>'
			],
			'msg_args' => [zz_htmltag_escape($identifier), zzform_url('self+qs')],
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

/**
 * prints out a list of filters to click
 *
 * @param array $filter
 *	array index =>
 *		string 'title'
 *		string 'identifier'
 *		string 'where'
 *		array 'selection'
 *			id => title
 * @param array $filter_active = $zz['filter_active']
 * @return string HTML output, all filters
 */
function zz_filter_selection($filter, $filter_active) {
	// create base URL for links
	// remove unwanted keys from link
	// do not show edited record, limit
	$self = zzform_url_remove([
		'q', 'scope', 'limit', 'mode', 'id', 'add', 'filter', 'delete',
		'insert', 'update', 'noupdate', 'zzhash', 'merge'], zzform_url('self+qs')
	);

	$filter_output = false;
	foreach ($filter as $index => $f) {
		$filter[$index]['length'] = 0;
		// remove this filter from query string
		$other_filters['filter'] = $filter_active;
		unset($other_filters['filter'][$f['identifier']]);
		if (!empty($f['subfilter'])) {
			// this filter has a subfilter
			// exclude subfilter from links as it will produce 404 errors
			// since the combinations are not possible
			foreach ($f['subfilter'] AS $subfilter) {
				// filter does exist?
				if (!isset($filter[$subfilter])) continue;
				unset($other_filters['filter'][$filter[$subfilter]['identifier']]);
			}
		}
		$filter_self = zzform_url_add($other_filters, $self);
		
		if (!empty($f['selection'])) {
			// $f['selection'] might be empty if there's no record in the database
			$sequence = 1;
			foreach ($f['selection'] as $id => $selection) {
				$is_selected = ((isset($filter_active[$f['identifier']]) 
					AND $filter_active[$f['identifier']] == $id))
					? true : false;
				if ($is_selected) {
					// active filter: don't show a link
					$link = false;
				} elseif (!empty($f['default_selection']) 
					AND ((is_array($f['default_selection']) AND key($f['default_selection']) == $id)
					OR $f['default_selection'] == $id)) {
					// default selection does not need parameter
					$link = $filter_self;
				} else {
					// ID might be string as well, so better urlencode it
					$link = zzform_url_add(['filter' => [$f['identifier'] => urlencode($id)]], $filter_self);
				}
				if (!empty($filter[$index]['translate_field_value'])) {
					$selection = wrap_text($selection, ['source' => wrap_setting('zzform_script_path')]);
				}
				$filter[$index]['values'][] = [
					'title' => $selection,
					'link' => $link,
					'index' => $sequence
				];
				$filter[$index]['length'] += strlen($selection);
				$filter_output = true;
				$sequence++;
			}
		} elseif (isset($filter_active[$f['identifier']])) {
			// no filter selections are shown, but there is a current filter, 
			// so show this
			$filter[$index]['values'][] = [
				'title' => zz_htmltag_escape($filter_active[$f['identifier']]),
				'link' => false,
				'index' => 1
			];
			$filter[$index]['length'] += strlen($filter_active[$f['identifier']]);
			$filter_output = false;
		} else {
			// nothing to output: like-filter, so don't display anything
			unset($filter[$index]);
			continue;
		}

		// create '- all -'-Link
		if (!empty($filter[$index]['hide_all_link'])) continue;
		if (empty($f['default_selection'])) {
			$link = $filter_self;
		} else {
			// there is a default selection, so we need a parameter = 0!
			$link = zzform_url_add(['filter' => [$f['identifier'] => 0]], $filter_self);
		}
		$link_all = false;
		if (isset($filter_active[$f['identifier']])) {
			if ($filter[$index]['type'] === 'function') $link_all = true;
			elseif ($filter_active[$f['identifier']] !== '0'
				AND $filter_active[$f['identifier']] !== 0) $link_all = true;
		}
		
		if (!$link_all) $link = false;

		$filter[$index]['values'][] = [
			'link' => $link,
			'all' => true,
			'index' => 0
		];
		if ($filter[$index]['length'] > 200)
			$filter[$index]['dropdown_filter'] = true;
	}
	if (!$filter_output) return false;

	return wrap_template('zzform-list-filter', $filter, 'ignore positions');
}
