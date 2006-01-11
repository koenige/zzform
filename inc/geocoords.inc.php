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

function dec2dms_sub($mer_dec) {
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
	$mer_dms['min'] = floor($mer_dec);	
	$mer_dec -= $mer_dms['min'];
	$mer_dec *= 600;
	$mer_dms['sec'] = round($mer_dec) / 10;
	return $mer_dms;
}

// Now you would like to have latitude and longitude treated at the same time
// and of course, differently

function dec2dms($lat_dec, $lon_dec, $precision = false) {
	if (!is_null($lat_dec) && !is_null($lon_dec) && !is_array($lat_dec) && !is_array($lon_dec)) {
		$lat_dms = dec2dms_sub($lat_dec);
		if ($lat_dms['hemisphere'] == "+") $lat_dms['hemisphere'] = "N";
		if ($lat_dms['hemisphere'] == "-") $lat_dms['hemisphere'] = "S";
		$lon_dms = dec2dms_sub($lon_dec);
		if ($lon_dms['hemisphere'] == "+") $lon_dms['hemisphere'] = "E";
		if ($lon_dms['hemisphere'] == "-") $lon_dms['hemisphere'] = "W";
		$coords_dms = array (
			"lat" => $lat_dms,
			"latdec" => $lat_dec,
			"latitude" => $lat_dms['deg']."&deg;".$lat_dms['min']."'".$lat_dms['sec'].'"'.$lat_dms['hemisphere'], 
			"lon" => $lon_dms,
			"londec" => $lon_dec,
			"longitude" => $lon_dms['deg']."&deg;".$lon_dms['min']."'".$lon_dms['sec'].'"'.$lon_dms['hemisphere']
		);
		if ($precision) {
			$coords_dms['latdec'.$precision] = $lat_dec * pow(10, $precision);
			$coords_dms['londec'.$precision] = $lon_dec * pow(10, $precision);
		}
	} else
		$coords_dms = '';
	return $coords_dms;
}

function geo_editform($form_coords, $coords, $wrong_coords = false) {
	global $text;
	
	// Coordinates[0][X_Latitude][lat
	// X_Latitude[lat
	$form_coords_ll = substr($form_coords, strrpos($form_coords, '[')+1);

	$output = '';
	$output .= '<input type="text" size="3" maxlength="3" name="'.$form_coords.'][deg]" id="'.make_id($form_coords.'][deg]').'" value="'.$coords[$form_coords_ll]['deg'].'">&deg; ';
	$output .= '<input type="text" size="3" maxlength="3" name="'.$form_coords.'][min]" id="'.make_id($form_coords.'][min]').'" value="'.$coords[$form_coords_ll]['min'].'">\' ';
	$output .= '<input type="text" size="4" maxlength="4" name="'.$form_coords.'][sec]" id="'.make_id($form_coords.'][sec]').'" value="'.$coords[$form_coords_ll]['sec'].'">&quot; ';

	if ($form_coords_ll == "lat")
		$hemispheres = array('+' => 'N', '-' => 'S');
	elseif ($form_coords_ll == "lon")
		$hemispheres = array('+' => 'E', '-' => 'W');
	else
		$output.= "Programmer's fault. Variable must have lat or lon in its name";

	$output.= '<select name="'.$form_coords.'][hemisphere]" id="'.$form_coords.'][hemisphere]" size="1">'."\n";
	$output.= '<option '; 
	if ($coords[$form_coords_ll]['hemisphere'] == $hemispheres['+'] OR $coords[$form_coords_ll]['hemisphere'] == '+')
		$output.= "selected ";
	$output.= 'value="+">'.$text[$hemispheres['+']].'</option>';
	$output.= '<option '; 
	if ($coords[$form_coords_ll]['hemisphere'] == $hemispheres['-'] OR $coords[$form_coords_ll]['hemisphere'] == '-')
		$output.= "selected ";
	$output.= 'value="-">'.$text[$hemispheres['-']].'</option>
</select>';
	if ($wrong_coords) $output.= zz_geo_error($coords[$form_coords_ll], $wrong_coords[$form_coords_ll], $form_coords_ll);
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

function make_id($string) {
	$string = str_replace('][', '_', $string);
	$string = str_replace('[', '', $string);
	$string = str_replace(']', '', $string);
	return $string;
}


/*
	dms2db
		- input $coords is array: (key: subkeys), further keys are ignored
			lat: deg | min | sec | hemisphere
			lon: deg | min | sec | hemisphere
*/

function dms2db($input) {
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
	$empty = false;

	foreach ($gcs as $coord => $coordinate) {
		if (!empty($input[$coord])) // only if this coordinate is present
			foreach ($parts as $part => $range) {
				if ($part == 'sec' && strstr($input[$coord][$part], ','))
					$input[$coord][$part] = str_replace(',', '.', $input[$coord][$part]); // replace decimal comma with decimal point
				switch ($part) { // check for integer or double
					case 'sec':
						$my = doubleval ($input[$coord][$part]); // type = string
						if ((string) $my != $input[$coord][$part]) // does not work directly
							$wrong[$coord][$part] = true;
					break;
					case 'min': 
					case 'deg': 
						$my = intval ($input[$coord][$part]); // type = string
						if ((string) $my != $input[$coord][$part]) // does not work directly
							$wrong[$coord][$part] = true;
					break;
				}
				if (isset($range['max_'.$coord])) 
					$range['max'] = $range['max_'.$coord]; // insert max range as required
				if (isset($range['max'])) { // check for min/max
					if ($input[$coord][$part] < $range['min'] OR 
						$input[$coord][$part] >= $range['max'])
						$wrong[$coord][$part] = true;
				} elseif (!in_array($input[$coord][$part], $range)) // check if in_array
					$wrong[$coord][$part] = true;
				if (isset($range['max']) && $input[$coord]['deg'] == $range['max'] - 1) {
					if ($input[$coord]['min']) $wrong[$coord]['min'] = true;
					if ($input[$coord]['min']) $wrong[$coord]['sec'] = true;
				}
			}
		else
			$empty[] = $coord;
	}
	if (count($empty) == 2) return false; // no input
	elseif (count($empty == 1)) // no real input, since hemisphere will always be sent from the browser
		foreach (array_keys($gcs) as $coord)
			if (!in_array($coord, $empty))
				if (empty($input[$coord]['deg']) && empty($input[$coord]['min']) && empty($input[$coord]['sec'])) return false;
		
	if (!empty($wrong)) { // there were errors, hand coordinates back
		$ouput['input'] = $input;
		$output['wrong'] = $wrong;
		return $output;
	} else { // okay, values seem to be correct

	/*	output (the same for longitude, "lon"):
		$var['latdec'] = +69.176778
		$var['lat']['deg'] = 69
		$var['lat']['min'] = 10
		$var['lat']['sec'] = 36.4
		$var['lat']['hemisphere'] = 'N'
		$var['latitude'] = 69¡10'36.4"N	
	*/
		$hemisphere['lat']['+'] = 'N';
		$hemisphere['lat']['-'] = 'S';
		$hemisphere['lon']['+'] = 'E';
		$hemisphere['lon']['-'] = 'W';
		$output = $input; // take all values back
		foreach ($gcs as $coord => $coordinate)
			if (!empty($input[$coord])) { // only if this coordinate is present
				// latdec,londec
				$output[$coord.'dec'] = $input[$coord]['deg'] + $input[$coord]['min'] / 60 + $input[$coord]['sec'] / 3600;
				if ($input[$coord]['hemisphere'] == "-") $output[$coord.'dec'] = -$output[$coord.'dec'];
				// latitude, longitude
				$output[$coordinate] = $input[$coord]['deg'].'&deg;'.$input[$coord]['min']."'"
					.$input[$coord]['sec'].'"'.$text[$hemisphere[$coord][$input[$coord]['hemisphere']]];
			}
		return $output;
	}	
}
?>