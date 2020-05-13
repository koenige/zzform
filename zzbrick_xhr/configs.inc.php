<?php

/**
 * zzform
 * XHR request for cfg files
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2020 Gustaf Mossakowski
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
function mod_zzform_xhr_configs($xmlHttpRequest, $zz) {
	global $zz_conf;

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

	$equal = substr($text, -1) === ' ' ? true : false;
	$text = trim($text);

	// find key in $cfg
	foreach ($field['cfg'] as $key => $values) {
		if ($equal) {
			if ($key === $text) $records[] = $key;
		} else {
			if (wrap_substr($key, $text)) $records[] = $key;
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
	
	foreach ($records as $record) {
		$data['entries'][] = [
			'text' => $record.' ', // search entry for zzform, concatenated and space at the end
			'elements' => [
				0 => [
					'node' => 'div',
					'properties' => [
						'className' => 'xhr_record',
						'text' => $record
					]
				]
			]
		];
	}
	return $data;
}
