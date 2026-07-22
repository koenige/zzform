<?php

/**
 * zzform
 * Export GeoJSON
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2007-2024, 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * GeoJSON export
 *
 * needs 'geojson' field definitions, at least id and either latitude/longitude
 * in one field or seperate latitude and longitude
 * further fields are saved in 'properties'
 * @param array $ops
 */
function zz_export_geojson($ops) {
	wrap_setting('character_set', 'utf-8');

	$fields = [];
	foreach ($ops['output']['head'] as $no => $column) {
		if (!empty($column['geojson'])) {
			$fields[$column['geojson']] = $no;
		}
	}

	$p_fields = [];
	foreach ($fields as $type => $no) {
		if (in_array($type, ['id', 'latitude', 'longitude', 'latitude/longitude'])) continue;
		$p_fields[$type] = $no;
	}

	$data = [];
	$data['type'] = 'FeatureCollection';
	foreach ($ops['output']['rows'] as $line) {
		if (array_key_exists('latitude/longitude', $fields)) {
			if (empty($line[$fields['latitude/longitude']]['value'])) continue;
			list($latitude, $longitude) = explode(',', $line[$fields['latitude/longitude']]['value']);
		} elseif (array_key_exists('latitude', $fields) AND array_key_exists('longitude', $fields)) {
			// accept 0, but not empty values
			if ($line[$fields['latitude']]['value'] === '') continue;
			if ($line[$fields['longitude']]['value'] === '') continue;
			$latitude = $line[$fields['latitude']]['value'];
			$longitude = $line[$fields['longitude']]['value'];
		} else {
			continue;
		}
		$properties = [];
		foreach ($p_fields as $type => $no) {
			$properties[$type] = wrap_html_escape($line[$no]['text']);
		}
		$data['features'][] = [
			'type' => 'Feature',
			'id' => $line[$fields['id']]['value'],
			'properties' => $properties,
			'geometry' => [
				'type' => 'Point',
				'coordinates' => [
					floatval($longitude),
					floatval($latitude)
				]
			]
		];
	}
	$output = json_encode($data);
	$output = 'var locations = '.$output.';';
	$headers['filename'] = zz_export_filename($ops['title'], 'geojson');
	return wrap_send_text($output, 'js', 200, $headers);
}
