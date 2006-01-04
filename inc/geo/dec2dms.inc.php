<?php

/*	This function converts decimal degrees in
	degrees, minutes, seconds, tenth of a second and orientation
	This is written for six digits after the point
	
	output (the same for longitude, "lon"):
	$var['latdec'] = +69.176778
	$var['lat']['deg'] = 69
	$var['lat']['min'] = 10
	$var['lat']['sec'] = 36.4
	$var['lat']['orientation'] = 'N'
	$var['latitude'] = 69¡10'36.4"N	
*/

function dec2dms_sub($mer_dec) {
	$mer_dms = array ('deg' => '', 'min' => '', 'sec' => '', 'orientation' => '');
	if ($mer_dec < 0) {
		// southern hemisphere
		$mer_dms['orientation'] = '-';
		$mer_dec = -$mer_dec;
	} elseif ($mer_dec == 0)
		$mer_dms['orientation'] = ' ';
	else
		$mer_dms['orientation'] = '+';
	$mer_dms['deg'] = floor($mer_dec);	// degree is part before point
	$mer_dec -= $mer_dms['deg'];				// so minutes and seconds remain
	$mer_dec *= 60;								// new unit is minutes
	$mer_dec = round($mer_dec,4);				// so we don't get any rounding errors
	$mer_dms['min'] = floor($mer_dec);	
	$mer_dec -= $mer_dms['min'];
	$mer_dec *= 600;
	$mer_dms['sec'] = round($mer_dec) / 10;
	return $mer_dms;
}

// Now you would like to have latitude and longitude treated at the same time
// and of course, differently

function dec2dms($lat_dec, $lon_dec) {
	global $precision;
	if (!is_null($lat_dec) && !is_null($lon_dec) && !is_array($lat_dec) && !is_array($lon_dec)) {
		$lat_dms = dec2dms_sub($lat_dec);
		if ($lat_dms['orientation'] == "+") $lat_dms['orientation'] = "N";
		if ($lat_dms['orientation'] == "-") $lat_dms['orientation'] = "S";
		$lon_dms = dec2dms_sub($lon_dec);
		if ($lon_dms['orientation'] == "+") $lon_dms['orientation'] = "E";
		if ($lon_dms['orientation'] == "-") $lon_dms['orientation'] = "W";
		$coords_dms = array (
			"lat" => $lat_dms,
			"latdec" => $lat_dec,
			"latitude" => $lat_dms['deg']."&deg;".$lat_dms['min']."'".$lat_dms['sec'].'"'.$lat_dms['orientation'], 
			"lon" => $lon_dms,
			"londec" => $lon_dec,
			"longitude" => $lon_dms['deg']."&deg;".$lon_dms['min']."'".$lon_dms['sec'].'"'.$lon_dms['orientation']
		);
		if ($precision) {
			$coords_dms['latdec'.$precision] = $lat_dec * pow(10, $precision);
			$coords_dms['londec'.$precision] = $lon_dec * pow(10, $precision);
		}
	} else
		$coords_dms = '';
	return $coords_dms;
}
?>
