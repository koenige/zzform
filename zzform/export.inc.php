<?php

/**
 * zzform
 * Export module
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2007-2010 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


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

// CSV defaults
// Excel requires
// - tabulator when opening via double-click and Unicode text
// - semicolon when opening via double-click and ANSI text
$zz_default['export_csv_delimiter'] = "\t";
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
		$mode = strtolower($mode);
		if ($_GET['export'] != $mode) continue;
		if (is_numeric($type)) {
			$export = $mode;
			$zz_conf['int']['export_script'] = '';
		} else {
			$export = strtolower($type);
			$zz_conf['int']['export_script'] = $mode;
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
	$ops['headers'] = zz_export_headers($export, $zz_conf['character_set']);
	$ops['mode'] = 'export';
	$zz_conf['list_display'] = $export;
	$zz_conf['group'] = false; // no grouping in export files

	switch ($export) {
	case 'kml':
		// always use UTF-8
		zz_db_charset('UTF8');
		break;
	case 'csv':
	case 'pdf':
		// always export all records
		$zz_conf['int']['this_limit'] = false; 
		break;
	}

	return $ops;
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
	$unwanted_querystrings = array('nolist', 'debug', 'referer', 'limit', 'order', 'dir');
	$qs = zz_edit_query_string($querystring, $unwanted_querystrings);
	if (substr($qs, 1)) {
		$qs = '&amp;'.substr($qs, 1);
	} else {
		$qs = '';
	}

	if (!is_array($zz_conf['export']))
		$zz_conf['export'] = array($zz_conf['export']);
	foreach ($zz_conf['export'] as $type => $mode) {
		if (is_numeric($type)) $type = $mode;
		else $type = $mode.', '.$type;
		$links[] = sprintf($html, $url, strtolower($mode), $qs, $type);
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
	
	$kml['title'] = utf8_encode(zz_nice_title($ops['heading'], $ops['output']['head']));
	$kml['description'] = zz_format($zz_conf['heading_text']);
	$kml['styles'] = array();
	$kml['placemarks'] = array();
	
	if (empty($zz_setting['kml_styles'])) {
		if (empty($zz_setting['kml_default_dot'])) {
			$zz_setting['kml_default_dot'] = '/_layout/map/blue-dot.png';
		}
		$kml['styles'][] = array(
			'id' => 'default',
			'href' => $zz_setting['kml_default_dot']
		);
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
			'title' => strip_tags($line[$fields['title']]['text']),
			'description' => zz_export_kml_description($ops['output']['head'], $line, $fields),
			'longitude' => $longitude,
			'latitude' => $latitude,
			'altitude' => (isset($fields['altitude']) ? $line[$fields['altitude']]['value'] : ''),
			'style' => (isset($fields['style']) ? $line[$fields['style']]['text'] : 'default')
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
	$set = array('title', 'longitude', 'latitude', 'altitude', 'point', 'style',
		'description');
	$description = '';
	foreach ($set as $field) {
		if (!isset($fields[$field])) continue;
		if ($field === 'description') $description = $line[$fields[$field]]['text'];
		unset($line[$fields[$field]]);
	}
	$desc = array();
	foreach ($line as $no => $values) {
		if (!is_numeric($no)) continue;
		if (empty($values['text'])) continue;
		if (!empty($head[$no]['title_kml'])) {
			$title = $head[$no]['title_kml'];
		} else {
			$title = $head[$no]['th_nohtml'];
		}
		if ($zz_conf['character_set'] != 'utf-8')
			$title = utf8_encode($title);
		$desc[] = '<tr><th>'.$title.'</th><td>'.$values['text'].'</td></tr>';
	}
	$text = '<table class="kml_description">'.implode("\n", $desc).'</table>'
		.$description;
	return $text;
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
			$myfield = str_replace($zz_conf['export_csv_enclosure'], 
				$zz_conf['export_csv_enclosure'].$zz_conf['export_csv_enclosure'],
				$field['text']
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

?>