<?php

/**
 * zzform
 * XHR request for dependecies
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

/**
 * return matching values for setlink
 *
 * @param array $xmlHttpRequest
 *		int limit
 *		string text
 *		int	echo
 * @param array $zz
 * @return array
 */
function mod_zzform_xhr_dependencies($xmlHttpRequest, $zz) {
	global $zz_conf;
	zz_initialize();

	$data = [];
	
	// might be forms, request, ... => process usual way and get script name from there
	$field_no = isset($_GET['field_no']) ? intval($_GET['field_no']) : '';
	$subtable_no = isset($_GET['subtable_no']) ? intval($_GET['subtable_no']) : '';

	// @todo use part of zzform to check access rights
	
	if (!empty($subtable_no)) {
		if (!array_key_exists($subtable_no, $zz['fields'])) {
			wrap_error(sprintf('Subtable %s requested, but it is not in the table definition', $subtable_no));
		}
		if (!array_key_exists($field_no, $zz['fields'][$subtable_no]['fields'])) {
			wrap_error(sprintf('Field %s in subtable %s requested, but it is not in the table definition', $field_no, $subtable_no));
		}
		$field = $zz['fields'][$subtable_no]['fields'][$field_no];
	} else {
		if (!array_key_exists($field_no, $zz['fields'])) {
			wrap_error(sprintf('Field %s requested, but it is not in the table definition', $field_no));
		}
		$field = $zz['fields'][$field_no];
	}

	if (empty($field['dependencies'])) return false; // would not make sense
	$sources = [$_GET['field_no']];
	if (!empty($field['dependencies_sources'])) {
		$sources += $field['dependencies_sources'];
	}
	$input = $xmlHttpRequest['text'];
	foreach ($sources as $source) {
		if (empty($input[$source])) return false; // not enough data
	}

	$values = [];
	foreach ($sources as $source) {
		// get IDs
		if (!empty($subtable_no)) {
			$my_field = $zz['fields'][$subtable_no]['fields'][$source];
		} else {
			$my_field = $zz['fields'][$source];
		}
		if ($my_field['sql']) {
			$select = zz_check_select_id($my_field, $input[$source], $zz_conf['db_name'].'.'.$zz['table']);
			if (empty($select['possible_values'])) return false;
			if (count($select['possible_values']) !== 1) return false;
			$values[] = reset($select['possible_values']);
		} else {
			$values[] = $input[$source];
		}
	}
	foreach ($field['dependencies'] as $dependency) {
		if (!empty($subtable_no)) {
			$my_field = $zz['fields'][$subtable_no]['fields'][$dependency];
		} else {
			$my_field = $zz['fields'][$dependency];
		}
		if (!empty($my_field['sql_dependency'][$_GET['field_no']])) {
			$sql = vsprintf($my_field['sql_dependency'][$_GET['field_no']], $values);
			$value = wrap_db_fetch($sql, '', 'single value');
			if (!$value) continue;
		} else {
			$value = count($values) === 1 ? reset($values) : $values;
		}
		// @todo with subtables!
		$id_field_name = zz_make_id_fieldname($my_field['field_name']);
		$data[$id_field_name] = $value;
	}
	return $data;
}
