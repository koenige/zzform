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
		$where[] = sprintf('%s LIKE "%%%s%%"', $sql_field['field'], wrap_db_escape($text));
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
		$sql_fieldnames[$index] = $sql_field['field']; 
		if (!strstr($sql_field['as'], '.')) continue;
		$sql_field['as'] = explode('.', $sql_field['as']);
		$sql_fields[$index]['as'] = $sql_field['as'][1];
	}
	
	array_shift($sql_fields);
	if (!empty($field['show_hierarchy'])) {
		$index = array_search($field['show_hierarchy'], $sql_fieldnames);
		if ($index !== false) {
			unset($sql_fields[$index]);
		}
	}
	
	$i = 0;
	// @todo use common concat function for all occurences!
	$concat = isset($field['concat_fields']) ? $field['concat_fields'] : ' | ';
	foreach ($records as $record) {
		$j = 0;
		$text = array();
		foreach ($sql_fields as $sql_field) {
			if (!array_key_exists($sql_field['as'], $record)) continue;
			if (empty($record[$sql_field['as']])) continue;
			$text[] = $record[$sql_field['as']];
			$data['entries'][$i]['elements'][$j] = array(
				'node' => 'div',
				'properties' => array(
					'className' => 'xhr_record',
					'text' => $record[$sql_field['as']]
				)
			);
			$j++;
		}
		// search entry for zzform, concatenated and space at the end
		$data['entries'][$i]['text'] = implode($concat, $text).' ';
		$i++;
	}
	return $data;
}
