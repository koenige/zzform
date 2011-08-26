<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2007-2010
// Module: export


/*		----------------------------------------------
 *					VARIABLES
 *		---------------------------------------------- */

$zz_conf['int']['allowed_params']['mode'][] = 'export';
$zz_conf['int']['allowed_params']['export'] = array('csv', 'pdf', 'kml');

// whether sql result might be exported 
// (link for export will appear at the end of the page)
$zz_default['export']			= false;				
// PDF library to include
$zz_default['pdflib_path']		= false;

// csv standards
$zz_default['export_csv_delimiter'] = ',';
$zz_default['export_csv_enclosure'] = '"';


/*		----------------------------------------------
 *					FUNCTIONS
 *		---------------------------------------------- */

/**
 * initializes export, sets a few variables
 *
 * @param array $ops
 * @global array $zz_conf
 * @global array $zz_error
 * @return array $ops
 */
function zz_export_init($ops) {
	global $zz_conf;
	global $zz_error;
	if (empty($zz_conf['export'])) return $ops;
	
	//	export
	if (!empty($_GET['mode']) AND $_GET['mode'] == 'export') {
		// should not happen, but just in case
		if (empty($_GET['export'])) $_GET['export'] = 'csv';
	}
	if (empty($_GET['export'])) return $ops;

	// get type and (optional) script name
	$export = false;
	if (!is_array($zz_conf['export'])) {
		$zz_conf['export'] = array($zz_conf['export']);
	}
	foreach ($zz_conf['export'] as $type => $mode) {
		if ($_GET['export'] != strtolower($mode)) continue;
		if (is_numeric($type)) {
			$export = strtolower($mode);
			$zz_conf['int']['export_script'] = '';
		} else {
			$export = strtolower($type);
			$zz_conf['int']['export_script'] = strtolower($mode);
		}
	}
	if (!in_array($export, $zz_conf['int']['allowed_params']['export'])) {
		$zz_error[] = array(
			'msg_dev' => 'Export parameter not allowed: <code>'
				.($export ? $export : htmlspecialchars($_GET['export'])).'</code>',
			'level' => E_USER_NOTICE
		);
		return $ops;
	}
	$ops['headers'] = zz_make_headers($export, $zz_conf['character_set']);
	$ops['mode'] = 'export';
	$zz_conf['list_display'] = $export;
	$zz_conf['group'] = false; // no grouping in export files

	switch ($export) {
	case 'kml':
		// always use UTF-8
		mysql_query('SET NAMES UTF8');
	case 'csv':
	case 'pdf':
		// always export all records, don't add query string to link (limit, order)
		$zz_conf['int']['this_limit'] = false; 
		$zz_conf['int']['link_remove_limit_order'] = true;
		break;
	}

	return $ops;
}

/**
 * Creates HTTP headers for export depending on type of export
 *
 * @param string $export type of export ('csv', 'pdf', ...)
 * @param string $charset character encoding ($zz_conf['character_set'])
 * @global array $zz_onf 'int'['url']['self']
 * @return array $headers
 */
function zz_make_headers($export, $charset) {
	global $zz_conf;
	$headers = array();
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
	$unwanted_querystrings = array('nolist', 'debug', 'referer');
	if (!empty($zz_conf['int']['link_remove_limit_order'])) {
		$unwanted_querystrings[] = 'limit';
		$unwanted_querystrings[] = 'order';
		$unwanted_querystrings[] = 'dir';
	}
	$querystring = zz_edit_query_string($querystring, $unwanted_querystrings);
	if (!is_array($zz_conf['export']))
		$zz_conf['export'] = array($zz_conf['export']);
	foreach ($zz_conf['export'] as $type => $exportmode) {
		if (is_numeric($type)) $type = $exportmode;
		else $type = $exportmode.', '.$type;
		$links[] = sprintf($html, $url, strtolower($exportmode), $querystring, $type);
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
 * @global array $zz_conf
 *		$zz_conf['int']['export_script']
 * @return mixed void (direct output) or array $ops
 */
function zz_export($ops) {
	global $zz_conf;
	// check if we have data
	if (!$zz_conf['show_list']) return false;

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
		$output = '';
		$output .= zz_export_csv_head($ops['output']['head'], $zz_conf);
		$output .= zz_export_csv_body($ops['output']['rows'], $zz_conf);
		$ops['output'] = $output;
		return $ops;
	case 'pdf':
		// no script is defined: standard PDF output
		echo 'Sorry, standard PDF support is not yet available. Please use a custom script.';
		exit;
	case 'kml':
		$ops['output'] = zz_export_kml($ops);
		return $ops;
	}
}

/**
 * KML export
 * works only in conjunction with zzwrap, if this is not available, use a
 * custom function (see zz_export())
 *
 * @param array $ops
 * @global array $zz_conf
 * @global array $zz_setting
 * @return array $ops
 */
function zz_export_kml($ops) {
	global $zz_conf;
	global $zz_setting;
	
	$kml['title'] = zz_nice_title($zz_conf['heading'], $ops['output']['head']);
	$kml['description'] = $zz_conf['heading_text'];
	$kml['styles'] = array();
	$kml['placemarks'] = array();
	
	if (empty($zz_setting['kml_default_dot'])) {
		$zz_setting['kml_default_dot'] = '/_layout/map/blue-dot.png';
	}
	$kml['styles'][] = array(
		'id' => 'default',
		'href' => $zz_setting['kml_default_dot']
	);
	
	foreach ($ops['output']['head'] as $no => $column) {
		if (!empty($column['kml'])) {
			$fields[$column['kml']] = $no;
		}
	}
	
	foreach ($ops['output']['rows'] as $line) {
		$latitude = '';
		$longitude = '';
		if (!empty($fields['point'])) {
			$point = $line[$fields['point']]['value'];
			$point = zz_geo_coord_sql_out($point, 'dec', ' ');
			$point = explode(' ', $point);
			if (count($point) == 2) {
				$latitude = $point[0];
				$longitude = $point[1];
			}
		} else {
			$latitude = $line[$fields['latitude']]['value'];
			$longitude = $line[$fields['longitude']]['value'];
		}
		$kml['placemarks'][] = array(
			'title' => $line[$fields['title']]['text'],
			'description' => zz_export_kml_description($ops['output']['head'], $line, $fields),
			'longitude' => $longitude,
			'latitude' => $latitude,
			'altitude' => (isset($fields['altitude']) ? $line[$fields['altitude']]['value'] : ''),
			'style' => 'default'
		);
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
	$set = array('title', 'longitude', 'latitude', 'altitude', 'point');
	foreach ($set as $field) {
		if (!isset($fields[$field])) continue;
		unset($line[$fields[$field]]);
	}
	$desc = array();
	foreach ($line as $no => $values) {
		if (!is_numeric($no)) continue;
		if (empty($values['text'])) continue;
		$title = (!empty($head[$no]['title_kml']) ? $head[$no]['title_kml'] : $head[$no]['title']);
		if ($zz_conf['character_set'] != 'utf-8')
			$title = utf8_encode($title);
		$desc[] = '<tr><th>'.$title.'</th><td>'.$values['text'].'</td></tr>';
	}
	return '<table class="kml_description">'.implode("\n", $desc).'</table>';
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_export_csv_head($main_rows, $zz_conf) {
	$output = '';
	$tablerow = false;
	$continue_next = false;
	foreach ($main_rows as $field) {
		if ($continue_next) {
			$continue_next = false;
			continue;
		}
		if (!empty($field['list_append_next'])) {
			$continue_next = true;
			if (!empty($field['title_append'])) {
				$field['title'] = $field['title_append'];
			}
		}
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_export_csv_body($rows, $zz_conf) {
	$output = '';
	foreach ($rows as $index => $row) {
		$tablerow = false;
		foreach ($row as $fieldindex => $field) {
			if ($fieldindex AND !is_numeric($fieldindex)) continue; // 0 or 1 or 2 ...
			$myfield = str_replace('"', '""', $field['text']);
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

?>