<?php

/**
 * zzform
 * Export KML files
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2007-2024, 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * KML export
 *
 * @param array $ops
 */
function zz_export_kml($ops) {
	wrap_setting('character_set', 'utf-8');
	$kml['title'] = zz_nice_title($ops['heading'], $ops['output']['head'], $ops);
	if (wrap_setting('character_set') !== 'utf-8')
		$kml['title'] = mb_convert_encoding($kml['title'], 'UTF-8', wrap_setting('character_set'));
	$kml['description'] = $ops['explanation'];
	$kml['styles'] = [];
	$kml['placemarks'] = [];
	
	if (!wrap_setting('kml_styles')) {
		$kml['styles'][] = [
			'id' => 'default',
			'href' => wrap_setting('kml_default_dot')
		];
	} else {
		$kml['styles'] = wrap_setting('kml_styles');
	}
	
	foreach ($ops['output']['head'] as $no => $column) {
		if (!empty($column['kml'])) {
			$fields[$column['kml']] = $no;
		}
	}
	
	foreach ($ops['output']['rows'] as $line) {
		$latitude = '';
		$longitude = '';
		$extended_data = [];
		if (!empty($fields['point'])) {
			$point = $line[$fields['point']]['value'];
			$point = zz_geo_coord_sql_out($point, 'dec', ' ');
			$point = explode(' ', $point);
			if (count($point) === 2) {
				$latitude = $point[0];
				$longitude = $point[1];
			}
		} else {
			$latitude = $line[$fields['latitude']]['value'];
			$longitude = $line[$fields['longitude']]['value'];
		}
		foreach ($ops['output']['head'] as $index => $field) {
			if (empty($field['kml_extendeddata'])) continue;
			$extended_data[] = [
				'field_name' => $field['field_name'],
				'title' => $field['title'],
				'value' => $line[$index]['text']
			];
		}
		$title = $line[$fields['title']]['text'];
		$title = strip_tags($title);
		$title = str_replace('&', '&amp;', $title);
		$kml['placemarks'][] = [
			'title' => $title,
			'description' => zz_export_kml_description($ops['output']['head'], $line, $fields),
			'longitude' => $longitude,
			'latitude' => $latitude,
			'altitude' => (isset($fields['altitude']) ? $line[$fields['altitude']]['value'] : ''),
			'style' => (isset($fields['style']) ? $line[$fields['style']]['text'] : 'default'),
			'extended_data' => $extended_data ? $extended_data : NULL
		];
	}
	$output = wrap_template('kml-coordinates', $kml);
	$headers['filename'] = zz_export_filename($ops['title'], 'kml');
	return wrap_send_text($output, 'kml', 200, $headers);
}

/**
 * put all fields that are not used for other purposes into DL list
 *
 * @param array $head = $ops['output']['head']
 * @param array $line = record
 * @param array $fields = fields definition
 * @return string HTML output, definition list	
 */
function zz_export_kml_description($head, $line, $fields) {
	$set = [
		'title', 'longitude', 'latitude', 'altitude', 'point', 'style',
		'description'
	];
	$description = '';
	foreach ($set as $field) {
		if (!isset($fields[$field])) continue;
		if ($field === 'description') $description = $line[$fields[$field]]['text'];
		unset($line[$fields[$field]]);
	}
	$desc = [];
	foreach ($line as $no => $values) {
		if (!is_numeric($no)) continue;
		if (empty($values['text'])) continue;
		$title = $head[$no]['title_kml'] ?? $head[$no]['th_nohtml'];
		if (wrap_setting('character_set') !== 'utf-8')
			$title = mb_convert_encoding($title, 'UTF-8', wrap_setting('character_set'));
		$desc[] = '<tr><th>'.$title.'</th><td>'.$values['text'].'</td></tr>';
	}
	$text = '<table class="kml_description">'.implode("\n", $desc).'</table>'
		.$description;
	return $text;
}
