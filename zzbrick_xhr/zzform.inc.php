<?php

/**
 * zzform
 * XHR request
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2018 Gustaf Mossakowski
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
	zz_initialize();

	$data = [];
	$text = mb_strtolower($xmlHttpRequest['text']);
	$limit = $xmlHttpRequest['limit'] + 1;
	
	// might be forms, request, ... => process usual way and get script name from there
	$field_no = isset($_GET['field_no']) ? intval($_GET['field_no']) : '';
	$subtable_no = isset($_GET['subtable_no']) ? intval($_GET['subtable_no']) : '';
	$unrestricted = !empty($_GET['unrestricted']) ? true : false;

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
	// @todo use common concat function for all occurences!
	$concat = isset($field['concat_fields']) ? $field['concat_fields'] : ' | ';
	$equal = substr($text, -1) === ' ' ? true : false;
	$text = trim($text);
	if (strstr($text, $concat)) {
		$text = explode($concat, $text);
	} else {
		$text = [$text];
	}

	// @todo modify SQL query according to zzform()
	
	$sql = $field['sql'];
	if (array_key_exists('add', $_GET) AND !empty($field['if']['add']['sql']))
		$sql = $field['if']['add']['sql'];
	$sql = wrap_db_prefix($sql);
	$sql_fields = wrap_edit_sql($sql, 'SELECT', false, 'list');
	$where = [];
	foreach ($sql_fields as $no => $sql_field) {
		// get sql_character_set etc.
		$sql_field = array_merge($field, $sql_field);
		foreach ($text as $index => $value) {
			// first field must be id field, so if value is not numeric, ignore it
			if (!$no AND !is_numeric($value)) continue;
			$collation = zz_db_field_collation('xhr', false, $sql_field, $no);
			$query = $equal ? 'LOWER(%s) = %s"%s"' : 'LOWER(%s) LIKE %s"%%%s%%"';
			$where[$index][] = sprintf($query, $sql_field['field_name'], $collation, wrap_db_escape($value));
			if (!empty($field['sql_translate'])) {
				$condition = zz_check_select_translated($field, $sql_field['field_name'], $value, $equal);
				if ($condition) $where[$index][] = $condition;
			}
		}
	}
	$conditions = [];
	foreach ($where as $condition) {
		$conditions[] = sprintf('(%s)', implode(' OR ', $condition));
	}
	$sql = wrap_edit_sql($sql, 'WHERE', implode(' AND ', $conditions));
	$ids = zz_hierarchy_subtree_ids($field);
	if ($ids) {
		$sql = wrap_edit_sql($sql, 'WHERE', sprintf('%s IN (%s)'
			, !empty($field['key_field_name']) ? $field['key_field_name'] : $field['field_name']
			, implode(',', $ids))
		);
	}
	wrap_db_query('SET NAMES utf8'); // JSON is UTF-8
	$records = wrap_db_fetch($sql, $sql_fields[0]['field_name']);
	if ($field['sql_translate']) {
		foreach ($field['sql_translate'] as $t_id_field => $t_table) {
			$records = wrap_translate($records, $t_table, $t_id_field);
		}
	}
	$records = array_values($records);
	if (count($records) > $limit) {
		// more records than we might show
		$data['entries'] = [];
		$data['entries'][] = ['text' => htmlspecialchars($xmlHttpRequest['text'])];
		$data['entries'][] = [
			'text' => wrap_text('Please enter more characters.'),
			'elements' => [
				0 => [
					'node' => 'div',
					'properties' => [
						'className' => 'xhr_foot',
						'text' => wrap_text('Please enter more characters.')
					]
				]
			]
		];
		return $data;
	}

	if (!$records) {
		$data['entries'][] = ['text' => htmlspecialchars($xmlHttpRequest['text'])];
		if (!$unrestricted) {
			$data['entries'][] = [
				'text' => wrap_text('No record was found.'),
				'elements' => [
					0 => [
						'node' => 'div',
						'properties' => [
							'className' => 'xhr_foot',
							'text' => wrap_text('No record was found.')
						]
					]
				]
			];
		}
		return $data;
	}
	
	// remove ID field	
	array_shift($sql_fields);

	foreach ($sql_fields as $index => $sql_field) {
		$sql_fieldnames[$index] = $sql_field['as']; 
		if (!strstr($sql_field['as'], '.')) continue;
		$sql_field['as'] = explode('.', $sql_field['as']);
		$sql_fields[$index]['as'] = $sql_field['as'][1];
	}
	
	$removable = ['sql_ignore', 'show_hierarchy'];
	foreach ($removable as $remove_key) {
		if (!array_key_exists($remove_key, $field)) continue;
		$remove_fields = $field[$remove_key];
		if (!is_array($remove_fields)) $remove_fields = [$remove_fields];
		foreach ($remove_fields as $remove_field) {
			$index = array_search($remove_field, $sql_fieldnames);
			if ($index !== false) {
				unset($sql_fields[$index]);
			}
		}
	}
	
	$i = 0;
	foreach ($records as $record) {
		$j = 0;
		$text = [];
		foreach ($sql_fields as $sql_field) {
			if (!array_key_exists($sql_field['as'], $record)) continue;
			if (empty($record[$sql_field['as']])) continue;
			$text[] = $record[$sql_field['as']];
			$data['entries'][$i]['elements'][$j] = [
				'node' => 'div',
				'properties' => [
					'className' => 'xhr_record',
					'text' => $record[$sql_field['as']]
				]
			];
			$j++;
		}
		// search entry for zzform, concatenated and space at the end
		$data['entries'][$i]['text'] = implode($concat, $text).' ';
		$i++;
	}
	return $data;
}
