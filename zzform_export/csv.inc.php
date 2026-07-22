<?php

/**
 * zzform
 * Export CSV
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2007-2024, 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * export data into CSV format
 *
 * @param array $ops
 */
function zz_export_csv($ops) {
	return zz_export_csv_file($ops, 'csv');
}

/**
 * export data into CSV format for Excel
 *
 * @param array $ops
 */
function zz_export_csv_excel($ops) {
	return zz_export_csv_file($ops, 'csv-excel');
}

/**
 * export data into CSV format for Excel
 *
 * @param array $ops
 * @param string $format
 */
function zz_export_csv_file($ops, $format) {
	// sort head, rows
	zz_export_sort($ops['output']);
	if ($format === 'csv-excel') {
		// Excel requires
		// - tabulator when opening via double-click and Unicode text
		// - semicolon when opening via double-click and ANSI text
		wrap_setting('export_csv_delimiter', "\t");
	}
	$output = '';
	$output .= zz_export_csv_head($ops['output']['head']);
	$output .= zz_export_csv_body($ops['output']['rows'], $format);
	if ($format === 'csv-excel') {
		$headers['character_set'] = 'utf-16le';
		// @todo check with mb_list_encodings() if available
		$output = mb_convert_encoding($output, 'UTF-16LE', wrap_setting('character_set'));
	} else {
		$headers['character_set'] = wrap_setting('character_set');
	}
	$headers['filename'] = zz_export_filename($ops['title'], 'csv');
	return wrap_send_text($output, 'csv', 200, $headers);
}

/**
 * outputs data as CSV (head)
 *
 * @param array $main_rows main rows (without subtables)
 * @return string CSV output, head
 */
function zz_export_csv_head($main_rows) {
	if (!wrap_setting('export_csv_heading')) return '';

	$output = '';
	$tablerow = [];
	$continue_next = false;
	foreach ($main_rows as $field) {
		if (!empty($field['title_export_prefix'])) {
			$field['title'] = $field['title_export_prefix'].' '.$field['title'];
		}
		$tablerow[] = wrap_setting('export_csv_enclosure')
			.str_replace(wrap_setting('export_csv_enclosure'), wrap_setting('export_csv_enclosure')
				.wrap_setting('export_csv_enclosure'), $field['title'])
			.wrap_setting('export_csv_enclosure');
	}
	$output .= implode(wrap_setting('export_csv_delimiter'), $tablerow)."\r\n";
	return $output;
}

/**
 * outputs data as CSV (body)
 *
 * @param array $rows data in rows
 * @param string $export_format
 * @return string CSV output, data
 */
function zz_export_csv_body($rows, $export_format) {
	$output = '';
	foreach ($rows as $index => $row) {
		$tablerow = [];
		foreach ($row as $fieldindex => $field) {
			if ($fieldindex AND !is_numeric($fieldindex)) continue; // 0 or 1 or 2 ...
			$myfield = $field['text'];
			$character_encoding = wrap_setting('character_set');
			if (substr($character_encoding, 0, 9) === 'iso-8859-')
				$character_encoding = 'iso-8859-1'; // others are not recognized
			$myfield = html_entity_decode($myfield, ENT_QUOTES, $character_encoding);
			$myfield = str_replace(wrap_setting('export_csv_enclosure'), 
				wrap_setting('export_csv_enclosure').wrap_setting('export_csv_enclosure'),
				$myfield
			);
			if (!empty($field['export_no_html'])) {
				$myfield = str_replace("&nbsp;", " ", $myfield);
				$myfield = str_replace("<\p>", "\n\n", $myfield);
				$myfield = str_replace("<br>", "\n", $myfield);
				$myfield = strip_tags($myfield);
			}
			if ($myfield) {
				foreach (wrap_setting('export_csv_replace') as $search => $replace)
					$myfield = str_replace($search, $replace, $myfield);
				if (!empty($field['export_csv_maxlength']))
					$myfield = substr($myfield, 0, $field['export_csv_maxlength']);
				$mask = false;
				if ($export_format === 'csv-excel' AND wrap_setting('export_csv_enclosure')) {
					if (preg_match('/^0[0-9]+$/', $myfield)) {
					// - number with leading 0 = TEXT
						$mask = true;
					} elseif (preg_match('/^[0-9]*\.[0-9]+$/', $myfield) AND wrap_setting('decimal_point') === ',') {
					// - number with . while decimal separator is , = TEXT
						$mask = true;
					} elseif (preg_match('/^[1]*[0-9] [AaPp]$/', $myfield)) {
					// 2 A will be converted to 02:00 AM
						$mask = true;
					} elseif (preg_match('/^\+[0-9.,]+$/', $myfield)) {
					// +49000 will be converted to 49000 (e. g. phone numbers)
						$mask = true;
					}
				}
				if ($mask) {
					$tablerow[] = wrap_setting('export_csv_enclosure').'='
					.str_repeat(wrap_setting('export_csv_enclosure'), 2).$myfield
					.str_repeat(wrap_setting('export_csv_enclosure'), 3);
				} else {
					$tablerow[] = wrap_setting('export_csv_enclosure').$myfield
						.wrap_setting('export_csv_enclosure');
				}
			} else {
				$tablerow[] = false; // empty value
			}
		}
		$output .= implode(wrap_setting('export_csv_delimiter'), $tablerow)."\r\n";
	}
	return $output;
}

/**
 * sort output by field_sequence
 *
 * @param array $out
 * @return bool
 */
function zz_export_sort(&$out) {
	$field_sequences = array_column($out['head'], 'field_sequence');
	if (!$field_sequences) return false;
	sort($field_sequences);
	$max_field_sequence = end($field_sequences);
	foreach ($out['head'] as $index => $line) {
		if (!empty($line['field_sequence'])) continue;
		$out['head'][$index]['field_sequence'] = ++$max_field_sequence;
	}
	$field_sequences = array_column($out['head'], 'field_sequence');
	foreach ($out['rows'] as $index => $row) {
		$field_sequences_per_row = $field_sequences;
		$extras = [];
		foreach ($row as $subindex => $value) {
			if (is_numeric($subindex) AND $subindex >= 0) continue;
			// remove -1 array
			if (!is_numeric($subindex)) $extras[$subindex] = $value;
			unset($out['rows'][$index][$subindex]);
		}
		array_multisort($field_sequences_per_row, SORT_ASC, $out['rows'][$index]);
		$out['rows'][$index] += $extras;
	}
	array_multisort($field_sequences, SORT_ASC, $out['head']);
	return true;
}
