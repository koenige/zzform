<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2007-2010
// Module: export


/*		----------------------------------------------
 *					VARIABLES
 *		---------------------------------------------- */

$zz_conf['allowed_params']['mode'][] = 'export';
$zz_conf['allowed_params']['export'] = array('csv', 'pdf');

$zz_default['export']			= false;				// if sql result might be exported (link for export will appear at the end of the page)
$zz_default['export_filetypes']	= array('csv', 'pdf');	// possible filetypes for export
$zz_default['list_display'] 	= 'csv';				// standard export

// csv standards
$zz_default['export_csv_delimiter'] = ',';
$zz_default['export_csv_enclosure'] = '"';


/*		----------------------------------------------
 *					FUNCTIONS
 *		---------------------------------------------- */

function zz_export_init($ops) {
	global $zz_conf;
	
	//	export
	if (empty($zz_conf['export'])) return false;
	if (!empty($_GET['mode']) && 	$_GET['mode'] == 'export') {
		// should not happen, but just in case
		if (empty($_GET['export'])) $_GET['export'] = 'csv';
	}
	if (!empty($_GET['export']) && in_array($_GET['export'], $zz_conf['allowed_params']['export'])) {
		$ops['headers'] = zz_make_headers($_GET['export'], $zz_conf['character_set']);
		$ops['mode'] = 'export';
		$zz_conf['list_display'] = $_GET['export'];
		$zz_conf['group'] = false; // no grouping in export files
	}
	return $ops;
}

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
	}
	return $headers;
}

function zz_export_links($url, $querystring) {
	global $zz_conf;
	$links = false;
	if (!is_array($zz_conf['export']))
		$zz_conf['export'] = array($zz_conf['export']);
	foreach ($zz_conf['export'] as $exportmode)
		$links[] = '<a href="'.$url.'export='.$exportmode.$querystring.'">'.zz_text('Export').' ('.$exportmode.')</a>';
	return $links;
}

function zz_pdf($ops) {
	global $zz_conf;
	// table definitions in $ops
	// values in $ops['output']

	require_once $zz_conf['dir_ext'].'/fpdf/fpdf.php';

// GFPS-Zertifikat

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