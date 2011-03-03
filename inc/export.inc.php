<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2007-2010
// Module: export


/*		----------------------------------------------
 *					VARIABLES
 *		---------------------------------------------- */

$zz_conf['int']['allowed_params']['mode'][] = 'export';
$zz_conf['int']['allowed_params']['export'] = array('csv', 'pdf');

$zz_default['export']			= false;				// if sql result might be exported (link for export will appear at the end of the page)
$zz_default['export_filetypes']	= array('csv', 'pdf');	// possible filetypes for export
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
			'msg_dev' => 'Export parameter not allowed: <code>'.($export ? $export : $_GET['export']).'</code>',
			'level' => E_USER_NOTICE
		);
		return $ops;
	}
	$ops['headers'] = zz_make_headers($export, $zz_conf['character_set']);
	$ops['mode'] = 'export';
	$zz_conf['list_display'] = $export;
	$zz_conf['group'] = false; // no grouping in export files
	return $ops;
}

/**
 * Creates HTTP headers for export depending on type of export
 *
 * @param string $export type of export ('csv', 'pdf', ...)
 * @param string $charset character encoding ($zz_conf['character_set'])
 * @return array $headers
 */
function zz_make_headers($export, $charset) {
	$headers = array();
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
		$filename = parse_url('http://www.example.org/'.$_SERVER['REQUEST_URI']);
		$headers[]['true'] = 'Content-Disposition: attachment; filename='.basename($filename['path']).'.csv';
		break;
	case 'pdf':
		$headers[]['true'] = 'Content-Type: application/pdf;';
		$filename = parse_url('http://www.example.org/'.$_SERVER['REQUEST_URI']);
		$headers[]['true'] = 'Content-Disposition: attachment; filename='.basename($filename['path']).'.pdf';
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
	if (!is_array($zz_conf['export']))
		$zz_conf['export'] = array($zz_conf['export']);
	$html = '<a href="%sexport=%s%s">'.zz_text('Export').' (%s)</a>';
	foreach ($zz_conf['export'] as $type => $exportmode) {
		if (is_numeric($type)) $type = $exportmode;
		else $type = $exportmode.', '.$type;
		$links[] = sprintf($html, $url, strtolower($exportmode), $querystring, $type);
	}
	return $links;
}

/**
 * Create PDF with table data
 * 
 * @param array $ops
 *		$ops['headers'] = HTTP headers which might be used for sending PDF to browser
 *		$ops['output']['head'] = Table definition, each field has an index
 *		$ops['output']['rows'] = Table data, lines 0...n, each line has fields
 *			with numerical index corresponding to 'head', each field is array
 *			made of 'class' (= HTML attribute values) and 'text' (= content)
 * @global array $zz_conf
 *		$zz_conf['int']['export_script']
 */
function zz_pdf($ops) {
	global $zz_conf;

	// check if a specific script should be called
	if (!empty($zz_conf['int']['export_script'])) {
		// script may reside in extra file
		// if not, function has to exist already
		$script_filename = $zz_conf['dir_custom'].'/export-pdf-'
			.str_replace( ' ', '-', $zz_conf['int']['export_script']).'.inc.php';
		if (file_exists($script_filename))
			require_once $script_filename;

		// check if custom function exists
		$function = 'export_pdf_'.str_replace(' ', '_', $zz_conf['int']['export_script']);
		if (!function_exists($function)) {
			echo 'Sorry, the required custom PDF export function <code>'
				.$function.'()</code> does not exist.';
			exit;
		}
		// include pdf library
		if (!empty($zz_conf['pdflib_path'])) require_once $zz_conf['pdflib_path'];
		// execute and return function
		return $function($ops);
	}

	// no script is defined: standard PDF output
	echo 'Sorry, standard PDF support is not yet available. Please use a custom script.';
	exit;
/*
	require_once $zz_conf['pdflib_path'];
	$pdf = new FPDF();
	foreach ($ops['output']['rows'] as $row) {
		$pdf->AddPage();
		// Logo
		$pdf->Image($zz_conf['dir_custom'].'/img/gfps-logo.png',10,10,120);
		$pdf->setY(130);
		$pdf->SetFont('Arial','B',16);
		$pdf->Cell(40,10,'ZERTIFIKAT', 0, 1, 'C');
		$pdf->Ln();
		$pdf->Cell(40,10,$row[2]['text']);	// Name
		$pdf->Ln();
		$pdf->Cell(40,10,'hat bei der Veranstaltung');
		$pdf->Ln();
		$pdf->Cell(40,10,$row[5]['text']);	// Termin
		$pdf->Ln();
		$pdf->Cell(40,10,'erfolgreich teilgenommen.');	
		$pdf->Ln();
		// Themen
		// Unterschrift
	}
	$pdf->Output();
*/
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