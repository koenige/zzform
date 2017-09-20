<?php

/**
 * zzform
 * Export module
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2007-2017 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/*		----------------------------------------------
 *					VARIABLES
 *		---------------------------------------------- */

/**
 * Default settings for export module
 */
function zz_export_config() {
	$conf['int']['allowed_params']['mode'][] = 'export';
	$conf['int']['allowed_params']['export'] = ['csv', 'pdf', 'kml', 'geojson'];
	zz_write_conf($conf, true);

	// whether sql result might be exported 
	// (link for export will appear at the end of the page)
	$default['export']			= false;				
	// PDF library to include
	$default['pdflib_path']		= false;

	// CSV defaults
	// Excel requires
	// - tabulator when opening via double-click and Unicode text
	// - semicolon when opening via double-click and ANSI text
	$default['export_csv_delimiter'] = "\t";
	$default['export_csv_enclosure'] = '"';
	zz_write_conf($default);
}

/*		----------------------------------------------
 *					FUNCTIONS
 *		---------------------------------------------- */

/**
 * initializes export, sets a few variables
 *
 * @param array $ops
 * @global array $zz_conf
 * @return array $ops
 */
function zz_export_init($ops) {
	global $zz_conf;
	if (empty($zz_conf['export'])) return $ops;
	if (empty($_GET['export'])) return $ops;

	// no edit modes allowed
	$unwanted_keys = [
		'mode', 'id', 'source_id', 'show', 'edit', 'add', 'delete', 'insert',
		'update', 'revise'
	];
	foreach ($unwanted_keys as $key) {
		if (!isset($_GET[$key])) continue;
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string($zz_conf['int']['url']['qs_zzform'], $unwanted_keys);
		zz_error_log([
			'msg' => 'Please don’t mess with the URL parameters. <code>%s</code> is not allowed here.',
			'msg_args' => [$key],
			'level' => E_USER_NOTICE,
			'status' => 404
		]);
		$ops['mode'] = false;
		return $ops;
	}
	// do not export anything if it's a 404 in export mode
	// and e. g. limit is incorrect
	if (!empty($zz_conf['int']['http_status']) AND $zz_conf['int']['http_status'] === 404) {
		$ops['mode'] = false;
		return $ops;
	}

	// get type and (optional) script name
	$export = false;
	if (!is_array($zz_conf['export'])) {
		$zz_conf['export'] = [$zz_conf['export']];
	}
	foreach ($zz_conf['export'] as $type => $mode) {
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
	if (!in_array($export_param, $zz_conf['int']['allowed_params']['export'])) {
		zz_error_log([
			'msg_dev' => 'Export parameter not allowed: `%s`',
			'msg_dev_args' => [$export ? $export : zz_htmltag_escape($_GET['export'])],
			'level' => E_USER_NOTICE,
			'status' => 404
		]);
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string($zz_conf['int']['url']['qs_zzform'], ['export']);
		$ops['mode'] = false;
		return $ops;
	}
	$character_encoding = $zz_conf['character_set'];
	$ops['mode'] = 'export';
	$zz_conf['list_display'] = $export;

	switch ($export_param) {
	case 'kml':
	case 'geojson':
		// always use UTF-8
		zz_db_charset('UTF8');
		// if kml file is called without limit parameter, it does not default
		// to limit=20 but to no limit instead
		if (empty($_GET['limit'])) {
			$zz_conf['int']['this_limit'] = false; 
		}
		break;
	case 'csv':
	case 'pdf':
		if ($export === 'csv-excel') {
			$character_encoding = 'utf-16le'; // Excel for Win and Mac platforms
		}
		// always export all records
		$zz_conf['int']['this_limit'] = false;
		break;
	}
	$ops['headers'] = zz_export_headers($export_param, $character_encoding);

	return $ops;
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
 * Creates HTTP headers for export depending on type of export
 *
 * @param string $export type of export ('csv', 'pdf', ...)
 * @param string $charset character encoding ($zz_conf['character_set'])
 * @global array $zz_conf 'int'['url']['self']
 * @return array $headers
 */
function zz_export_headers($export, $charset) {
	global $zz_conf;
	$headers = [];
	$filename = basename($zz_conf['int']['url']['self']);

	switch ($export) {
	case 'csv':
		// correct download of csv files
		if (!empty($_SERVER['HTTP_USER_AGENT']) 
			AND strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE'))
		{
			$headers[]['true'] = 'Cache-Control: maxage=1'; // in seconds
			$headers[]['true'] = 'Pragma: public';
		}
		$headers[]['true'] = 'Content-Type: text/csv; charset='.$charset;
		$headers[]['true'] = 'Content-Disposition: attachment; filename="'.$filename.'.csv"';
		break;
	case 'pdf':
		$headers[]['true'] = 'Content-Type: application/pdf;';
		$headers[]['true'] = 'Content-Disposition: attachment; filename="'.$filename.'.pdf"';
		break;
	case 'kml':
		$headers[]['true'] = 'Content-Type: application/vnd.google-earth.kml+xml; charset=utf8';
		if (!empty($_GET['q'])) $filename .= ' '.forceFilename($_GET['q']);
		$headers[]['true'] = 'Content-Disposition: attachment; filename="'.$filename.'.kml"';
		break;
	case 'geojson':
		$headers[]['true'] = 'Content-Type: application/javascript; charset=utf8';
		if (!empty($_GET['q'])) $filename .= ' '.forceFilename($_GET['q']);
		$headers[]['true'] = 'Content-Disposition: attachment; filename="'.$filename.'.js"';
		break;
	}
	return $headers;
}

/**
 * HTML output of links for export
 *
 * @param string $url
 * @param string $querystring
 * @global array $zz_conf
 * @return array $links array of strings with links for export
 */
function zz_export_links($url, $querystring) {
	global $zz_conf;
	$links = false;
	$html = '<a href="%sexport=%s%s">'.zz_text('Export').' (%s)</a>';
	
	// remove some querystrings which have no effect anyways
	$unwanted_querystrings = ['nolist', 'debug', 'referer', 'limit', 'order', 'dir'];
	$qs = zz_edit_query_string($querystring, $unwanted_querystrings);
	if (substr($qs, 1)) {
		$qs = '&amp;'.substr($qs, 1);
	} else {
		$qs = '';
	}

	if (!is_array($zz_conf['export']))
		$zz_conf['export'] = [$zz_conf['export']];
	foreach ($zz_conf['export'] as $type => $mode) {
		if (is_numeric($type)) $type = $mode;
		else $type = $mode.', '.$type;
		$links[] = sprintf($html, $url, zz_export_identifier($mode), $qs, $type);
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
 * @param array $zz_var
 * @global array $zz_conf
 *		$zz_conf['int']['export_script']
 * @return mixed void (direct output) or array $ops
 */
function zz_export($ops, $zz, $zz_var) {
	global $zz_conf;
	// check if we have data
	if (!$zz_conf['int']['show_list']) return false;

	// pdf?
	if ($zz_conf['list_display'] === 'pdf') {
		// include pdf library
		if (!empty($zz_conf['pdflib_path']))
			require_once $zz_conf['pdflib_path'];
	}

	// custom functions
	$function = zz_export_script($zz_conf['list_display']);
	if ($function) {
		// execute and return function
		return $function($ops);
	}

	switch ($zz_conf['list_display']) {
	case 'csv':
	case 'csv-excel':
		$output = '';
		$output .= zz_export_csv_head($ops['output']['head'], $zz_conf);
		$output .= zz_export_csv_body($ops['output']['rows'], $zz_conf);
		if ($zz_conf['list_display'] === 'csv-excel') {
			// @todo check with mb_list_encodings() if available
			$output = mb_convert_encoding($output, 'UTF-16LE', $zz_conf['character_set']);
			// Add BOM, @todo if later zzwrap is used to send out zzform exports
			// the BOM has to be added separately (does not count to Content-Length)
			$output = chr(255).chr(254).$output;
		}
		$ops['output'] = $output;
		return $ops;
	case 'pdf':
		// no script is defined: standard PDF output
		echo 'Sorry, standard PDF support is not yet available. Please use a custom script.';
		exit;
	case 'kml':
		$ops['output'] = zz_export_kml($ops, $zz, $zz_var);
		return $ops;
	case 'geojson':
		$ops['output'] = zz_export_geojson($ops, $zz, $zz_var);
		return $ops;
	}
}

/**
 * KML export
 * works only in conjunction with zzwrap, if this is not available, use a
 * custom function (see zz_export())
 *
 * @param array $ops
 * @param array $zz
 * @param array $zz_var
 * @global array $zz_conf
 * @global array $zz_setting
 * @return array $ops
 */
function zz_export_kml($ops, $zz, $zz_var) {
	global $zz_setting;
	
	$kml['title'] = utf8_encode(zz_nice_title($ops['heading'], $ops['output']['head'], $zz_var));
	$kml['description'] = zz_format($zz['explanation']);
	$kml['styles'] = [];
	$kml['placemarks'] = [];
	
	if (empty($zz_setting['kml_styles'])) {
		if (empty($zz_setting['kml_default_dot'])) {
			$zz_setting['kml_default_dot'] = $zz_setting['layout_path'].'/map/blue-dot.png';
		}
		$kml['styles'][] = [
			'id' => 'default',
			'href' => $zz_setting['kml_default_dot']
		];
	} else {
		$kml['styles'] = $zz_setting['kml_styles'];
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
	global $zz_conf;
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
		if (!empty($head[$no]['title_kml'])) {
			$title = $head[$no]['title_kml'];
		} else {
			$title = $head[$no]['th_nohtml'];
		}
		if ($zz_conf['character_set'] !== 'utf-8')
			$title = utf8_encode($title);
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
 * @param array $zz_var
 * @return array $ops
 */
function zz_export_geojson($ops, $zz, $zz_var) {
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
			$latitude = $line[$fields['latitude']]['value'];
			$longitude = $line[$fields['longitude']]['value'];
		} else {
			continue;
		}
		$properties = [];
		foreach ($p_fields as $type => $no) {
			$properties[$type] = wrap_html_escape($line[$no]['value']);
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
	
	// script may reside in extra file
	// if not, function has to exist already
	$script_filename = $zz_conf['dir_custom'].'/export-'.$type.'-'
		.str_replace( ' ', '-', $zz_conf['int']['export_script']).'.inc.php';
	if (file_exists($script_filename))
		require_once $script_filename;

	// check if custom function exists
	$function = 'export_'.$type.'_'.str_replace(' ', '_', $zz_conf['int']['export_script']);
	if (!function_exists($function)) {
		echo 'Sorry, the required custom '.strtoupper($type).' export function <code>'
			.$function.'()</code> does not exist.';
		exit;
	}
	return $function;
}

/**
 * outputs data as CSV (head)
 *
 * @param array $main_rows main rows (without subtables)
 * @param array $zz_conf configuration
 *		'export_csv_enclosure', 'export_csv_delimiter'
 * @return string CSV output, head
 */
function zz_export_csv_head($main_rows, $zz_conf) {
	$output = '';
	$tablerow = false;
	$continue_next = false;
	foreach ($main_rows as $field) {
		$tablerow[] = $zz_conf['export_csv_enclosure']
			.str_replace($zz_conf['export_csv_enclosure'], $zz_conf['export_csv_enclosure']
				.$zz_conf['export_csv_enclosure'], $field['title'])
			.$zz_conf['export_csv_enclosure'];
	}
	$output .= implode($zz_conf['export_csv_delimiter'], $tablerow)."\r\n";
	return $output;
}

/**
 * outputs data as CSV (body)
 *
 * @param array $rows data in rows
 * @param array $zz_conf configuration
 *		'export_csv_enclosure', 'export_csv_delimiter'
 * @return string CSV output, data
 */
function zz_export_csv_body($rows, $zz_conf) {
	$output = '';
	foreach ($rows as $index => $row) {
		$tablerow = false;
		foreach ($row as $fieldindex => $field) {
			if ($fieldindex AND !is_numeric($fieldindex)) continue; // 0 or 1 or 2 ...
			$myfield = $field['text'];
			$character_encoding = $zz_conf['character_set'];
			if (substr($character_encoding, 0, 9) === 'iso-8859-')
				$character_encoding = 'iso-8859-1'; // others are not recognized
			$myfield = html_entity_decode($myfield, ENT_QUOTES, $character_encoding);
			$myfield = str_replace($zz_conf['export_csv_enclosure'], 
				$zz_conf['export_csv_enclosure'].$zz_conf['export_csv_enclosure'],
				$myfield
			);
			if (!empty($field['export_no_html'])) {
				$myfield = str_replace("&nbsp;", " ", $myfield);
				$myfield = str_replace("<\p>", "\n\n", $myfield);
				$myfield = str_replace("<br>", "\n", $myfield);
				$myfield = strip_tags($myfield);
			}
			if ($myfield)
				$tablerow[] = $zz_conf['export_csv_enclosure'].$myfield
					.$zz_conf['export_csv_enclosure'];
			else
				$tablerow[] = false; // empty value
		}
		$output .= implode($zz_conf['export_csv_delimiter'], $tablerow)."\r\n";
	}
	return $output;
}
