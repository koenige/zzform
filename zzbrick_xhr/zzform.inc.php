<?php

/**
 * zzform
 * XHR request
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016 Gustaf Mossakowski
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
function mod_zzform_xhr_zzform($xmlHttpRequest, $zz) {
	global $zz_conf;
	
	$data = array();
	$text = strtolower($xmlHttpRequest['text']);
	$limit = $xmlHttpRequest['limit'] + 1;
	
	// might be forms, request, ... => process usual way and get script name from there
	$field_no = isset($_GET['field_no']) ? intval($_GET['field_no']) : '';
	$subtable_no = isset($_GET['subtable_no']) ? intval($_GET['subtable_no']) : '';

	// @todo use part of zzform to check access rights
	
	if (!empty($subtable_no)) {
		$field = $zz['fields'][$subtable_no]['fields'][$field_no];
	} else {
		$field = $zz['fields'][$field_no];
	}

	// @todo modify SQL query according to zzform()
	
	$sql = $field['sql'];
	$sql_fields = wrap_edit_sql($sql, 'SELECT', false, 'list');
	$where = array();
	foreach ($sql_fields as $sql_field) {
		$where[] = sprintf('%s LIKE "%%%s%%"', $sql_field, wrap_db_escape($text));
	}
	$sql = wrap_edit_sql($sql, 'WHERE', implode(' OR ', $where));
	$records = wrap_db_fetch($sql, '_dummy_', 'numeric');
	if (count($records) > $limit) {
		// more records than we might show
		$data['entries'] = array();
		$data['entries'][] = array('text' => htmlspecialchars($xmlHttpRequest['text']));
		$data['entries'][] = array(
			'text' => wrap_text('Please enter more characters.'),
			'elements' => array(
				0 => array(
					'node' => 'div',
					'properties' => array(
						'className' => 'xhr_foot',
						'text' => wrap_text('Please enter more characters.')
					)
				)
			)
		);
		return $data;
	}

	if (!$records) {
		$data['entries'][] = array('text' => htmlspecialchars($xmlHttpRequest['text']));
		$data['entries'][] = array(
			'text' => wrap_text('No record was found.'),
			'elements' => array(
				0 => array(
					'node' => 'div',
					'properties' => array(
						'className' => 'xhr_foot',
						'text' => wrap_text('No record was found.')
					)
				)
			)
		);
		return $data;
	}
	
	foreach ($sql_fields as $index => $sql_field) {
		if (!strstr($sql_field, '.')) continue;
		$sql_field = explode('.', $sql_field);
		$sql_fields[$index] = $sql_field[1];
	}
	
	$id_field = array_shift($sql_fields);
	if (!empty($field['show_hierarchy'])) {
		$index = array_search($field['show_hierarchy'], $sql_fields);
		if ($index !== false) unset($sql_fields[$index]);
	}
	
	$i = 0;
	foreach ($records as $record) {
		wrap_error(json_encode($record));
		$data['entries'][$i] = array(
			'text' => $record[$sql_fields[0]]
		);
		foreach ($sql_fields as $index => $sql_field) {
			if (!$index) continue;
			if (!array_key_exists($sql_field, $record)) continue;
			$data['entries'][$i]['elements'][$index] = array(
				'node' => 'div',
				'properties' => array(
					'className' => 'xhr_record',
					'text' => $record[$sql_field]
				)
			);
		}
		$i++;
	}
	return $data;
}
