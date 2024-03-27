<?php

/**
 * zzform
 * XHR request for dependecies
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2020-2021, 2023-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

/**
 * return matching values for setlink
 *
 * @param array $xmlHttpRequest
 *		string text
 * @param array $zz
 * @return array
 */
function mod_zzform_xhr_dependencies($xmlHttpRequest, $zz) {
	zz_initialize();

	// might be forms, request, ... => process usual way and get script name from there
	$field_no = isset($_GET['field_no']) ? intval($_GET['field_no']) : '';
	$subtable_no = isset($_GET['subtable_no']) ? intval($_GET['subtable_no']) : '';

	// @todo use part of zzform to check access rights
	
	if (!empty($subtable_no)) {
		if (!array_key_exists($subtable_no, $zz['fields']) OR !array_key_exists('fields', $zz['fields'][$subtable_no]))
			return brick_xhr_error(503, 'Subtable %s requested, but it is not in the table definition', [$subtable_no]);
		if (!array_key_exists($field_no, $zz['fields'][$subtable_no]['fields'])) {
			return brick_xhr_error(503, 'Field %s in subtable %s requested, but it is not in the table definition', [$field_no, $subtable_no]);
		$field = $zz['fields'][$subtable_no]['fields'][$field_no];
	} else {
		if (!array_key_exists($field_no, $zz['fields']))
			return brick_xhr_error(503, 'Field %s requested, but it is not in the table definition', [$field_no]);
		$field = $zz['fields'][$field_no];
	}

	if (empty($field['dependencies'])) return []; // would not make sense
	$sources = [$_GET['field_no']];
	if (!empty($field['dependencies_sources'])) {
		$sources = array_merge($sources, $field['dependencies_sources']);
	}
	$input = $xmlHttpRequest['text'];
	foreach ($sources as $source) {
		if (empty($input[$source])) return []; // not enough data
		if (is_array($input[$source]))
			return brick_xhr_error(400, 'malformed request', $xmlHttpRequest);
	}

	$values = [];
	foreach ($sources as $source) {
		// get IDs
		if (!empty($subtable_no)) {
			$my_field = $zz['fields'][$subtable_no]['fields'][$source];
		} else {
			$my_field = $zz['fields'][$source];
		}
		if (!empty($my_field['sql'])) {
			$select = zz_check_select_id($my_field, $input[$source]);
			if (empty($select['possible_values'])) return [];
			if (count($select['possible_values']) !== 1) return [];
			$values[] = reset($select['possible_values']);
		} elseif (!empty($my_field['cfg'])) {
			if (array_key_exists(trim($input[$source]), $my_field['cfg']))
				$values = $my_field['cfg'][trim($input[$source])];
		} else {
			$values[] = $input[$source];
		}
	}
	if (!empty($field['dependencies_function'])) {
		$values = $field['dependencies_function']($values);
	}
	
	$data = [];
	foreach ($field['dependencies'] as $index => $dependency) {
		$this_subtable_no = !empty($subtable_no) ? $subtable_no : false;
		if (strstr($dependency, '.'))
			list ($this_subtable_no, $dependency) = explode('.', $dependency);
		if ($this_subtable_no) {
			$my_field = $zz['fields'][$this_subtable_no]['fields'][$dependency];
		} else {
			$my_field = $zz['fields'][$dependency];
		}
		if (!empty($my_field['sql_dependency'][$_GET['field_no']])) {
			foreach ($values as $index => $value)
				$values[$index] = wrap_db_escape($value);
			$sql = vsprintf($my_field['sql_dependency'][$_GET['field_no']], $values);
			$value = wrap_db_fetch($sql);
			if (!$value) continue;
			if (!empty($my_field['sql_translate'])) {
				foreach ($my_field['sql_translate'] as $t_id_field => $t_table) {
					$value = wrap_translate($value, $t_table, $t_id_field);
				}
			}
			$value = reset($value);			
		} elseif (count($values) === count($field['dependencies'])) {
			$value = $values[$index];
		}
		if ($this_subtable_no AND isset($_GET['rec'])) {
			$table_name = $zz['fields'][$this_subtable_no]['table_name'] ?? wrap_db_prefix($zz['fields'][$this_subtable_no]['table']);
			$rec = intval($_GET['rec']);
			$id_field_name = zz_long_fieldname($table_name, $rec, $my_field['field_name']);
			$id_field_name = zz_make_id_fieldname($id_field_name);
		} else {
			$id_field_name = zz_make_id_fieldname($my_field['field_name']);
		}
		$data[$id_field_name] = $value;
	}
	return $data;
}
