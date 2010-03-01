<?php

/*
	zzform scripts
	module: export
	(c) 2007-2009 Gustaf Mossakowski, gustaf@koenige.org
*/

/*		----------------------------------------------
 *					VARIABLES
 *		---------------------------------------------- */

$zz_allowed_params['mode'][] = 'export';
$zz_allowed_params['export'] = array('csv', 'pdf');

$zz_default['export']			= false;				// if sql result might be exported (link for export will appear at the end of the page)
$zz_default['export_filetypes']	= array('csv', 'pdf');	// possible filetypes for export
$zz_default['list_display'] 	= 'csv';				// standard export

// csv standards
$zz_default['export_csv_delimiter'] = ',';
$zz_default['export_csv_enclosure'] = '"';


/*		----------------------------------------------
 *					FUNCTIONS
 *		---------------------------------------------- */

function zz_export_init($zz_allowed_params) {
	global $zz_conf;
	global $zz;
	
	//	export
	if (empty($zz_conf['export'])) return false;
	if (!empty($_GET['mode']) && 	$_GET['mode'] == 'export') {
		// should not happen, but just in case
		if (empty($_GET['export'])) $_GET['export'] = 'csv';
	}
	if (!empty($_GET['export']) && in_array($_GET['export'], $zz_allowed_params['export'])) {
		$zz['headers'] = zz_make_headers($_GET['export'], $zz_conf['character_set']);
		$zz['mode'] = 'export';
		$zz_conf['list_display'] = $_GET['export'];
		$zz_conf['group'] = false; // no grouping in export files
	}
	return true;
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

function zz_pdf($zz) {
	global $zz_conf;
	// table definitions in $zz
	// values in $zz['output']

	require_once $zz_conf['dir_ext'].'/fpdf/fpdf.php';

/*
	echo '<pre>';
	print_r($zz);
	echo '</pre>';
	exit;
*/

// GFPS-Zertifikat

	$pdf = new FPDF();
	foreach ($zz['output']['rows'] as $row) {
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


//	echo '<pre>';
//	print_r($zz);
//	echo '</pre>';
}

?>