<?php

/*
	zzform scripts
	module: export
	(c) 2007 Gustaf Mossakowski, gustaf@koenige.org
*/

//
//	Parameters
//

$zz_allowed_params['export'] = array('csv');

$zz_default['export']			= false;				// if sql result might be exported (link for export will appear at the end of the page)
$zz_default['export_filetypes']	= array('csv');			// possible filetypes for export

//	export

if (!empty($_GET['mode']) && 	$_GET['mode'] == 'export') {
	// should not happen, but just in case
	if (empty($_GET['export'])) $_GET['export'] = 'csv';
}

if (!empty($_GET['export']) && in_array($_GET['export'], $zz_allowed_params['export'])) {
	$zz['headers'] = zz_make_headers($_GET['export'], $zz_conf_global['character_set']);
	$zz['mode'] = 'export';
}


//
//	Functions
//

function zz_make_headers($export, $charset) {
	switch ($export) {
		case 'csv':
			$headers[]['true'] = 'Content-Type: text/plain; charset='.$charset;
		break;
	}
	return $headers;
}

?>