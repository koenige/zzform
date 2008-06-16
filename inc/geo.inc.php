<?php

/*
	zzform Scripts

	handling of geographic coordinates (input, output, transformation DMS - DD and vice versa)
	- dec2dms
		- dec2dms_sub
	- dms2db
	- geo_editform
		- zz_geo_error
		- make_id
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2006

*/

/*	This function converts decimal degrees in
	degrees, minutes, seconds, tenth of a second and hemisphere
	This is written for six digits after the point
	
	output (the same for longitude, "lon"):
	$var['latdec'] = +69.176778
	$var['lat']['deg'] = 69
	$var['lat']['min'] = 10
	$var['lat']['sec'] = 36.4
	$var['lat']['hemisphere'] = 'N'
	$var['latitude'] = 69¡10'36.4"N	
*/

function dec2dms_sub($mer_dec, $which = 'dms') {
	$mer_dms = array ('deg' => '', 'min' => '', 'sec' => '', 'hemisphere' => '');
	if ($mer_dec < 0) {
		// southern hemisphere
		$mer_dms['hemisphere'] = '-';
		$mer_dec = -$mer_dec;
	} elseif ($mer_dec == 0)
		$mer_dms['hemisphere'] = ' ';
	else
		$mer_dms['hemisphere'] = '+';
	$mer_dms['deg'] = floor($mer_dec);	// degree is part before point
	$mer_dec -= $mer_dms['deg'];				// so minutes and seconds remain
	$mer_dec *= 60;								// new unit is minutes
	$mer_dec = round($mer_dec,4);				// so we don't get any rounding errors
	if ($which == 'dms') {
		$mer_dms['min'] = floor($mer_dec);	
		$mer_dec -= $mer_dms['min'];
		$mer_dec *= 600;
		$mer_dms['sec'] = round($mer_dec) / 10;
	} else {
		$mer_dms['min'] = $mer_dec;	
	}
	return $mer_dms;
}

// Now you would like to have latitude and longitude treated at the same time
// and of course, differently

function dec2dms($lat_dec, $lon_dec, $precision = false) {
	global $text;
	if (!is_null($lat_dec) && !is_null($lon_dec) && !is_array($lat_dec) && !is_array($lon_dec)) {
		$lat_dms = dec2dms_sub($lat_dec);
		$lat_dm = dec2dms_sub($lat_dec, 'dm');
		if ($lat_dms['hemisphere'] == "+") $lat_dm['hemisphere'] = $lat_dms['hemisphere'] = $text["N"];
		if ($lat_dms['hemisphere'] == "-") $lat_dm['hemisphere'] = $lat_dms['hemisphere'] = $text["S"];
		$lon_dms = dec2dms_sub($lon_dec);
		$lon_dm = dec2dms_sub($lon_dec, 'dm');
		if ($lon_dms['hemisphere'] == "+") $lon_dm['hemisphere'] = $lon_dms['hemisphere'] = $text["E"];
		if ($lon_dms['hemisphere'] == "-") $lon_dm['hemisphere'] = $lon_dms['hemisphere'] = $text["W"];
		$coords_dms = array (
			"lat_dms" => $lat_dms,
			"lat_dm" => $lat_dm,
			"lat_dec" => $lat_dec,
			"latitude_dms" => $lat_dms['deg']."&deg;".$lat_dms['min']."'".$lat_dms['sec'].'"'.$lat_dms['hemisphere'], 
			"latitude_dm" => $lat_dm['deg']."&deg;".$lat_dm['min']."'".$lat_dm['hemisphere'], 
			"lon_dms" => $lon_dms,
			"lon_dm" => $lon_dm,
			"lon_dec" => $lon_dec,
			"longitude_dms" => $lon_dms['deg']."&deg;".$lon_dms['min']."'".$lon_dms['sec'].'"'.$lon_dms['hemisphere'],
			"longitude_dm" => $lon_dm['deg']."&deg;".$lon_dm['min']."'".$lon_dm['hemisphere']
		);
		if ($precision) {
			$coords_dms['lat_dec_'.$precision] = $lat_dec * pow(10, $precision);
			$coords_dms['lon_dec_'.$precision] = $lon_dec * pow(10, $precision);
		}
	} else
		$coords_dms = '';
	return $coords_dms;
}

function geo_editform($form_coords, $coords, $format = 'dms', $wrong_coords = false) {
	global $text;
	// Coordinates[0][X_Latitude][lat
	// X_Latitude[lat

	$form_coords_ext = substr($form_coords, strrpos($form_coords, '[')+1).'_'.$format;
	
	$output = '';
	$output .= '<input type="text" size="3" maxlength="3" name="'.$form_coords.'_'.$format.'][deg]" id="'
		.make_id_fieldname($form_coords.'_'.$format.'][deg]', false)
		.'" value="'.($coords ? $coords[$form_coords_ext]['deg'] : '').'">&deg; ';
	if ($format == 'dm')
		$output .= '<input type="text" size="6" maxlength="6" name="'.$form_coords.'_'.$format.'][min]" id="'
			.make_id_fieldname($form_coords.'_'.$format.'][min]', false)
			.'" value="'.($coords ? $coords[$form_coords_ext]['min'] : '').'">\' ';
	else {
		$output .= '<input type="text" size="3" maxlength="3" name="'.$form_coords.'_'.$format.'][min]" id="'
			.make_id_fieldname($form_coords.'_'.$format.'][min]', false)
			.'" value="'.($coords ? $coords[$form_coords_ext]['min'] : '').'">\' ';
		$output .= '<input type="text" size="4" maxlength="4" name="'.$form_coords.'_'.$format.'][sec]" id="'
			.make_id_fieldname($form_coords.'_'.$format.'][sec]', false)
			.'" value="'.($coords ? $coords[$form_coords_ext]['sec'] : '').'">&quot; ';
	}
	
	if ($form_coords_ext == "lat_".$format)
		$hemispheres = array('+' => 'N', '-' => 'S');
	elseif ($form_coords_ext == "lon_".$format)
		$hemispheres = array('+' => 'E', '-' => 'W');
	else
		$output.= "Programmer's fault. Variable must have lat or lon in its name";

	$output.= '<select name="'.$form_coords.'_'.$format.'][hemisphere]" id="'
		.make_id_fieldname($form_coords.'_'.$format.'][hemisphere]').'" size="1">'."\n";
	$output.= '<option '; 
	if ($coords && ($coords[$form_coords_ext]['hemisphere'] == $hemispheres['+'] 
		OR $coords[$form_coords_ext]['hemisphere'] == '+'))
		$output.= "selected ";
	$output.= 'value="+">'.$text[$hemispheres['+']].'</option>';
	$output.= '<option '; 
	if ($coords && ($coords[$form_coords_ext]['hemisphere'] == $hemispheres['-'] 
		OR $coords[$form_coords_ext]['hemisphere'] == '-'))
		$output.= "selected ";
	$output.= 'value="-">'.$text[$hemispheres['-']].'</option>
</select>';
	if ($wrong_coords) $output.= zz_geo_error($coords[$form_coords_ext], $wrong_coords[$form_coords_ext], $form_coords_ext);
	return $output;
}

function zz_geo_error($coords, $wrong_coords, $ll) {
	global $text;
	$correct_values['sec'] = '0 - 59.99';
	$correct_values['min'] = '0 - 59';
	$correct_values['deg'] = ($ll == 'lon') ? '0 - 180' : '0 - 90';
	$correct_values['hemisphere'] = ($ll == 'lon') ? ($text['E'].', '.$text['W']) : ($text['N'].', '.$text['S']);
	$output = '';
	foreach (array_keys($wrong_coords) as $coord)
		$output.= '<br><small>'.($coords[$coord] ? $coords[$coord] : $text['Nothing']).' '.$text['is not a correct value for'].' '.$text[$coord].$text['. Correct values are: '].$correct_values[$coord].'</small>';
	return $output;
}



/*
	dms2db
		- input $coords is array: (key: subkeys), further keys are ignored
			lat: deg | min | sec | hemisphere
			lon: deg | min | sec | hemisphere
*/

function dms2db($input, $which = 'dms') {
	setlocale(LC_ALL, 'en_GB'); // we would get into trouble with set locale
	global $text;
	$gcs = array (
		'lat' => 'latitude',
		'lon' => 'longitude'
	);
	$parts = array(
		'deg' => array('min' => 0, 'max_lon' => 181, 'max_lat' => 91), // one more than allowed (int)
		'min' => array('min' => 0, 'max' => 60), // one more than allowed (int)
		'sec' => array('min' => 0, 'max' => 60), // sligthly more than allowed (float)
		'hemisphere' => array('-', '+')
	);
	if ($which == 'dm') unset($parts['sec']); // no second field in DM
	$empty = false;

	foreach ($gcs as $coord => $coordinate) {
		$coordf = $coord.'_'.$which;
		if (!empty($input[$coordf])) // only if this coordinate is present
			foreach ($parts as $part => $range) {
				if (!empty($input[$coordf][$part]) && $part == 'sec' && strstr($input[$coordf][$part], ','))
					$input[$coordf][$part] = str_replace(',', '.', $input[$coordf][$part]); // replace decimal comma with decimal point
				if (!empty($input[$coordf][$part]) && $part == 'min' && strstr($input[$coordf][$part], ','))
					$input[$coordf][$part] = str_replace(',', '.', $input[$coordf][$part]); // replace decimal comma with decimal point
				switch ($part) { // check for integer or double
					case 'sec':
						$my = doubleval ($input[$coordf][$part]); // type = string
						if ((string) $my != $input[$coordf][$part]) // does not work directly
							$wrong[$coordf][$part] = true;
						break;
					case 'min':
						if ($which == 'dm') {
							$my = doubleval ($input[$coordf][$part]); // type = string
							if ((string) $my != $input[$coordf][$part]) // does not work directly
								$wrong[$coordf][$part] = true;
							break;
						}
					case 'deg': 
						$my = intval ($input[$coordf][$part]); // type = string
						if ((string) $my != $input[$coordf][$part]) // does not work directly
							$wrong[$coordf][$part] = true;
						break;
				}
				if (isset($range['max_'.$coord])) 
					$range['max'] = $range['max_'.$coord]; // insert max range as required
				if (isset($range['max'])) { // check for min/max
					if ($input[$coordf][$part] < $range['min'] OR 
						$input[$coordf][$part] >= $range['max'])
						$wrong[$coordf][$part] = true;
				} elseif (!in_array($input[$coordf][$part], $range)) // check if in_array
					$wrong[$coordf][$part] = true;
				if (isset($range['max']) && $input[$coordf]['deg'] == $range['max'] - 1) {
					if ($input[$coordf]['min']) $wrong[$coordf]['min'] = true;
					if ($input[$coordf]['min']) $wrong[$coordf]['sec'] = true;
				}
			}
		else
			$empty[] = $coord;
	}
	if (count($empty) == 2) return false; // no input
	elseif (count($empty == 1)) // no real input, since hemisphere will always be sent from the browser
		foreach (array_keys($gcs) as $coord) {
			$coordf = $coord.'_'.$which;
			if (!in_array($coord, $empty))
				if (empty($input[$coordf]['deg']) && empty($input[$coordf]['min']) && empty($input[$coordf]['sec'])) return false;
		}

	if (!empty($wrong)) { // there were errors, hand coordinates back
		$ouput['input'] = $input;
		$output['wrong'][$which] = $wrong;
		return $output;
	} else { // okay, values seem to be correct

	/*	output (the same for longitude, "lon"):
		$var['lat_dec'] = +69.176778
		$var['lat_dms']['deg'] = 69
		$var['lat_dms']['min'] = 10
		$var['lat_dms']['sec'] = 36.4
		$var['lat_dms']['hemisphere'] = 'N'
		$var['lat_dm']['deg'] = 69
		$var['lat_dm']['min'] = 10.434
		$var['lat_dm']['hemisphere'] = 'N'
		$var['latitude'] = 69¡10'36.4"N	
	*/
		$hemisphere['lat']['+'] = 'N';
		$hemisphere['lat']['-'] = 'S';
		$hemisphere['lon']['+'] = 'E';
		$hemisphere['lon']['-'] = 'W';
		$output = $input; // take all values back
		foreach ($gcs as $coord => $coordinate) {
			$coordf = $coord.'_'.$which;
			if (!empty($input[$coordf])) { // only if this coordinate is present
				// latdec,londec
				$output[$coord.'_dec'] = $input[$coordf]['deg'] + $input[$coordf]['min'] / 60;
				if (!empty($input[$coordf]['sec'])) $output[$coord.'_dec'] += $input[$coordf]['sec'] / 3600;
				if ($input[$coordf]['hemisphere'] == "-") $output[$coord.'_dec'] = -$output[$coord.'_dec'];
				// latitude, longitude
				$output[$coordinate] = $input[$coordf]['deg'].'&deg;';
				if ($which == 'dms') {
					$output[$coordinate].= $input[$coordf]['min']."'";
					$output[$coordinate].= $input[$coordf]['sec'].'"';
				} else {
					$output[$coordinate].= (int) $input[$coordf]['min']."'";
					$output[$coordinate].= ((($input[$coordf]['min'] - (int) $input[$coordf]['min']))*60).'"';
				}
				$output[$coordinate].= $text[$hemisphere[$coord][$input[$coordf]['hemisphere']]];
			}
		}
		return $output;
	}	
}
?>