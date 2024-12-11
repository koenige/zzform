<?php

/**
 * zzform
 * Export module
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2007-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/*		----------------------------------------------
 *					FUNCTIONS
 *		---------------------------------------------- */

/**
 * initializes export, sets a few variables
 *
 * @param array $ops
 * @param array $zz
 * @global array $zz_conf
 * @return array $ops
 */
function zz_export_init(&$ops, &$zz) {
	global $zz_conf;
	if (!$zz['export']) return;
	if (empty($_GET['export'])) return;

	// no edit modes allowed
	$unwanted_keys = [
		'mode', 'id', 'source_id', 'show', 'edit', 'add', 'delete', 'insert',
		'update', 'revise'
	];
	foreach ($unwanted_keys as $key) {
		if (!isset($_GET[$key])) continue;
		zzform_url_remove($unwanted_keys);
		zz_error_log([
			'msg' => 'Please don’t mess with the URL parameters. <code>%s</code> is not allowed here.',
			'msg_args' => [$key],
			'level' => E_USER_NOTICE,
			'status' => 404
		]);
		$ops['mode'] = false;
		return;
	}
	// do not export anything if it's a 404 in export mode
	// and e. g. limit is incorrect
	if (wrap_static('page', 'status') === 404) {
		$ops['mode'] = false;
		return;
	}

	// get type and (optional) script name
	$export = false;
	foreach ($zz['export'] as $type => $mode) {
		$mode = zz_export_identifier($mode);
		if ($_GET['export'] !== $mode) continue;
		if ($pos = strpos($mode, '-') AND $mode !== 'csv-excel') {
			$export = substr($mode, 0, $pos);
			$zz_conf['int']['export_script'] = substr($mode, $pos + 1);
		} elseif (is_numeric($type)) {
			$export = $mode;
			$zz_conf['int']['export_script'] = '';
		} else {
			$export = zz_export_identifier($type);
			$zz_conf['int']['export_script'] = $mode;
		}
	}
	$export_param = strpos($export, '-') ? substr($export, 0, strpos($export, '-')) : $export;
	if (!in_array($export_param, wrap_setting('zzform_export_formats'))) {
		zz_error_log([
			'msg_dev' => 'Export parameter not allowed: `%s`',
			'msg_dev_args' => [$export ? $export : zz_htmltag_escape($_GET['export'])],
			'level' => E_USER_NOTICE,
			'status' => 404
		]);
		zzform_url_remove(['export']);
		$ops['mode'] = false;
		return;
	}
	$ops['mode'] = 'export';
	$zz['list']['display'] = $export;
	$zz_conf['int']['export'] = true;

	switch ($export_param) {
	case 'kml':
	case 'geojson':
		// always use UTF-8
		wrap_db_charset('utf8');
		// if kml file is called without limit parameter, it does not default
		// to limit=20 but to no limit instead
		if (empty($_GET['limit'])) {
			$zz_conf['int']['this_limit'] = false; 
		}
		break;
	case 'csv':
	case 'pdf':
	case 'zip':
		// always export all records
		$zz_conf['int']['this_limit'] = false;
		break;
	}
}

/**
 * Create identifier for export from mode
 *
 * @param string
 * @return string
 */
function zz_export_identifier($mode) {
	$mode = strtolower($mode);
	$mode = str_replace(' ', '-', $mode);
	return $mode;
}

/**
 * HTML output of links for export
 *
 * @param array $export ($zz['export'])
 * @return array $links array of strings with links for export
 */
function zz_export_links($export) {
	$links = [];
	
	// remove some querystrings which have no effect anyways
	$unwanted_querystrings = ['nolist', 'debug', 'referer', 'limit', 'order', 'dir'];
	$qs = zzform_url_remove($unwanted_querystrings, 'extra_get');
	if ($qs) $qs = substr($qs, 1);

	foreach ($export as $type => $mode) {
		if (is_numeric($type)) $type = $mode;
		else $type = $mode.', '.$type;
		$links[] = [
			'mode' => zz_export_identifier($mode),
			'qs' => $qs,
			'type' => $type
		];
	}
	return $links;
}

/**
 * export data
 *
 * @param array $ops
 *		$ops['headers'] = HTTP headers which might be used for sending PDF to browser
 *		$ops['output']['head'] = Table definition, each field has an index
 *		$ops['output']['rows'] = Table data, lines 0...n, each line has fields
 *			with numerical index corresponding to 'head', each field is array
 *			made of 'class' (= HTML attribute values) and 'text' (= content)
 * @param array $zz
 * @param array $list
 * @return mixed void (direct output) or array $ops
 */
function zz_export($ops, $zz, $list) {
	// custom functions
	$function = zz_export_script($list['display']);
	if ($function) {
		// execute and return function
		return $function($ops);
	}

	$filename = wrap_filename($ops['title'], " ", [':' => ' ', '.' => ' ', '–' => '-']);

	switch ($list['display']) {
	case 'csv':
	case 'csv-excel':
		// sort head, rows
		zz_export_sort($ops['output']);
		if ($list['display'] === 'csv-excel') {
			// Excel requires
			// - tabulator when opening via double-click and Unicode text
			// - semicolon when opening via double-click and ANSI text
			wrap_setting('export_csv_delimiter', "\t");
		}
		$output = '';
		$output .= zz_export_csv_head($ops['output']['head']);
		$output .= zz_export_csv_body($ops['output']['rows'], $list['display']);
		if ($list['display'] === 'csv-excel') {
			$headers['character_set'] = 'utf-16le';
			// @todo check with mb_list_encodings() if available
			$output = mb_convert_encoding($output, 'UTF-16LE', wrap_setting('character_set'));
		} else {
			$headers['character_set'] = wrap_setting('character_set');
		}
		$headers['filename'] = $filename.'.csv';
		return wrap_send_text($output, 'csv', 200, $headers);
	case 'pdf':
	case 'zip':
		wrap_quit(503, wrap_text(
			'Standard %s export support is not supported. Please use a custom script.',
			['values' => [strtoupper($list['display'])]]
		));
	case 'kml':
		wrap_setting('character_set', 'utf-8');
		$output = zz_export_kml($ops, $zz);
		if (!empty($_GET['q'])) $filename .= ' '.wrap_filename($_GET['q']);
		$headers['filename'] = $filename.'.kml';
		return wrap_send_text($output, 'kml', 200, $headers);
	case 'geojson':
		wrap_setting('character_set', 'utf-8');
		$output = zz_export_geojson($ops, $zz);
		if (!empty($_GET['q'])) $filename .= ' '.wrap_filename($_GET['q']);
		$headers['filename'] = $filename.'.geojson';
		return wrap_send_text($output, 'js', 200, $headers);
	}
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

/**
 * KML export
 * works only in conjunction with zzwrap, if this is not available, use a
 * custom function (see zz_export())
 *
 * @param array $ops
 * @param array $zz
 * @return array $ops
 */
function zz_export_kml($ops, $zz) {
	$kml['title'] = zz_nice_title($ops['heading'], $ops['output']['head'], $ops);
	if (wrap_setting('character_set') !== 'utf-8')
		$kml['title'] = mb_convert_encoding($kml['title'], 'UTF-8', wrap_setting('character_set'));
	$kml['description'] = zz_format($zz['explanation']);
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
	return $output;
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

/**
 * GeoJSON export
 *
 * needs 'geojson' field definitions, at least id and either latitude/longitude
 * in one field or seperate latitude and longitude
 * further fields are saved in 'properties'
 * @param array $ops
 * @param array $zz
 * @return array $ops
 */
function zz_export_geojson($ops, $zz) {
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
	return $output;
}

/**
 * get custom function for export
 *
 * @param string $type
 * @global array $zz_conf
 * @return string name of the function if there is one
 */
function zz_export_script($type) {
	global $zz_conf;
	// check if a specific script should be called
	if (empty($zz_conf['int']['export_script'])) return false;
	$prefix = '';
	
	// script may reside in extra file
	// if not, function has to exist already
	$filename = sprintf('export-%s-%s', $type, $zz_conf['int']['export_script']);
	$files = wrap_include('zzform/'.$filename, 'custom/active');
	if (!$files AND wrap_setting('active_module')) {
		// look for export-[type], e. g. export-pdf.inc.php in module folder
		$files = wrap_include('zzform/export-'.$type, 'active');
	}
	if ($files) {
		$package = key($files['packages']);
		if ($package !== 'custom')
			$prefix = sprintf('mf_%s_', $package);
	}

	// check if custom function exists
	$function = sprintf('%sexport_%s_%s'
		, $prefix, $type, str_replace('-', '_', $zz_conf['int']['export_script'])
	);
	if (!function_exists($function)) {
		wrap_quit(503, wrap_text(
			'The required %s export function <code>%s()</code> does not exist.',
			['values' => [strtoupper($type), $function]]
		));
	}
	return $function;
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
