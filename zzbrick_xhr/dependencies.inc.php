<?php

/**
 * zzform
 * XHR request for dependecies
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2020-2021, 2023-2024, 2026 Gustaf Mossakowski
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
	$data = [];
	$data['_query_strings'] = ['field_no', 'subtable_no', 'rec'];

	// might be forms, request, ... => process usual way and get script name from there
	$field_no = $_GET['field_no'] ?? 0;
	if (!wrap_is_int($field_no)) return brick_xhr_error(400, 'Malformed field number: %s', $field_no);
	$subtable_no = $_GET['subtable_no'] ?? 0;
	$subtable_parts = zz_xhr_subtable_parse($subtable_no);
	if ($subtable_parts === false)
		return brick_xhr_error(400, 'Malformed subtable number: %s', $subtable_no);

	// @todo use part of zzform to check access rights

	$subtable_fields = $zz['fields'];
	$subtable_definition = NULL;
	if ($subtable_parts) {
		$resolved = zz_xhr_subtable_resolve($subtable_parts, $zz['fields']);
		if (!$resolved)
			return brick_xhr_error(503, 'Subtable %s requested, but it is not in the table definition', [$subtable_no]);
		$subtable_fields = $resolved['fields'];
		$subtable_definition = $resolved['definition'];
		if (!array_key_exists($field_no, $subtable_fields))
			return brick_xhr_error(503, 'Field %s in subtable %s requested, but it is not in the table definition', [$field_no, $subtable_no]);
		$field = $subtable_fields[$field_no];
	} else {
		if (!array_key_exists($field_no, $zz['fields']))
			return brick_xhr_error(503, 'Field %s requested, but it is not in the table definition', [$field_no]);
		$field = $zz['fields'][$field_no];
	}

	if (empty($field['dependencies'])) return $data; // would not make sense
	$sources = [$_GET['field_no']];
	if (!empty($field['dependencies_sources'])) {
		$sources = array_merge($sources, $field['dependencies_sources']);
	}
	$input = $xmlHttpRequest['text'];
	foreach ($sources as $source) {
		if (empty($input[$source])) return $data; // not enough data
		if (is_array($input[$source]))
			return brick_xhr_error(400, 'malformed request', $xmlHttpRequest);
	}

	$values = [];
	foreach ($sources as $source) {
		// get IDs
		if (!empty($subtable_no)) {
			$my_field = $subtable_fields[$source];
		} else {
			$my_field = $zz['fields'][$source];
		}
		if (!empty($my_field['sql'])) {
			$select = zz_check_select_id($my_field, $input[$source]);
			if (empty($select['possible_values'])) return $data;
			if (count($select['possible_values']) !== 1) return $data;
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

	foreach ($field['dependencies'] as $index => $dependency) {
		$dep_fields = !empty($subtable_no) ? $subtable_fields : $zz['fields'];
		$dep_definition = $subtable_definition;
		if (strstr($dependency, '.')) {
			list ($dep_subtable_no, $dependency) = explode('.', $dependency);
			if (array_key_exists(intval($dep_subtable_no), $zz['fields']) AND !empty($zz['fields'][intval($dep_subtable_no)]['fields'])) {
				$dep_fields = $zz['fields'][intval($dep_subtable_no)]['fields'];
				$dep_definition = $zz['fields'][intval($dep_subtable_no)];
			}
		}
		if (!array_key_exists(intval($dependency), $dep_fields)) continue;
		$my_field = $dep_fields[intval($dependency)];
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
		if ($dep_definition AND isset($_GET['rec'])) {
			$table_name = $dep_definition['table_name'] ?? wrap_db_prefix($dep_definition['table']);
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
