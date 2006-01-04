<?php

// 34 16 55.5 N
// dms2db($_POST)

/*
still missing:

- check whether strings are entered. Strings result in a 0-Coordinate
- disallow 4.5deg or 50.5 minutes

*/

function dms2db($source_array, $fieldname) {

	$zz_error['msg'] = '';
	$error_stop = false;
/*
	$dms_details = array (
		1 => $fieldname.'_lat_deg', 
		2 => $fieldname.'_lat_min', 
		3 => $fieldname.'_lat_sec', 
		4 => $fieldname.'_lat_orientation', 
		5 => $fieldname.'_lon_deg', 
		6 => $fieldname.'_lon_min', 
		7 => $fieldname.'_lon_sec', 
		8 => $fieldname.'_lon_orientation'
	);
*/
	$dms_details[$fieldname] = array (
		1 => 'lat_deg', 
		2 => 'lat_min', 
		3 => 'lat_sec', 
		4 => 'lat_orientation', 
		5 => 'lon_deg', 
		6 => 'lon_min', 
		7 => 'lon_sec', 
		8 => 'lon_orientation'
	);

	foreach ($dms_details[$fieldname] as $dms_detail) {
		$dms_exploded = explode ("_", $dms_detail);
		$new_coords[$fieldname[0]][$dms_exploded[1]] = $source_array[$dms_detail];
	}

	$mers = array (
		1 => 'lat',
		2 => 'lon'
		);
	
	// test, whether values are ok.
	
	$error_nulls = 0;
	
	foreach ($mers as $mer) {
		if ($mer == 'lat')
			$zz_error['msg'] .= "[Latitude]:  ";
		elseif ($mer == 'lon')
			$zz_error['msg'] .= "[Longitude]: ";
		if ($new_coords[$mer]['deg'] != NULL) {
			#if (gettype($new_coords[$mer]['deg']) != "integer") {
				if ($new_coords[$mer]['deg'] > 180 ) // Coordinates are out of world range
					$zz_error['msg'] .= "The coordinate system of the world only allows values under 180 degrees. ";
				elseif ($new_coords[$mer]['deg'] < 0) // Coordinates are negative
					$zz_error['msg'] .= "Values under zero are not allowed. To enter data for the western or southern hemisphere, please use the orientation field. ";
			#} else {
			#	$test = gettype($new_coords[$mer]['deg']);
			#}
		} else {
			$zz_error['msg'] .= "Please enter a value for degrees. ";
			$error_nulls += 1;
		}
		if ($new_coords[$mer]['min'] != NULL) {
			#if (gettype($new_coords[$mer]['min']) == "integer") {
				if ($new_coords[$mer]['min'] >= 60 ) {
					// Coordinates are out of world range
					$zz_error['msg'] .= "There are only 60 minutes in a degree. ";
				} elseif ($new_coords[$mer]['min'] < 0) {
					// Coordinates are negative
					$zz_error['msg'] .= "Negative values for minutes are not accepted. ";
				}			
			#} else {
			#	$zz_error['msg'] .= "Only integer values are accepted for minutes. ";
			#}
		} else {
			$zz_error['msg'] .= "Please enter a value for minutes. ";
			$error_nulls += 1;
		}
		if ($new_coords[$mer]['sec'] != NULL) {
			// check, whether a comma has been used instead of a decimal point
			$seconds = explode(",", $new_coords[$mer]['sec']);
			if (isset($seconds[1])) {
				$new_coords[$mer]['sec'] = $seconds[0].".".$seconds[1];
			}
			#if (gettype($new_coords[$mer]['sec']) == "integer" or $new_coords[$mer]['sec'] == "double") {
				if ($new_coords[$mer]['sec'] >= 60 ) {
					// Coordinates are out of world range
					$zz_error['msg'] .= "There are only 60 seconds in a minute. ";
				} elseif ($new_coords[$mer]['sec'] < 0) {
					// Coordinates are negative
					$zz_error['msg'] .= "Negative values for seconds are not accepted. ";
				}			
			#} else {
			#	$zz_error['msg'] .= "Only numerical values are accepted for seconds. ";
			#}
		} else {
			$zz_error['msg'] .= "Please enter a value for seconds. ";
			$error_nulls += 1;
		}
		if ($new_coords[$mer]['orientation'] != NULL) {
			if ($new_coords[$mer]['orientation'] != "+" and $new_coords[$mer]['orientation'] != "-" ) {
				$zz_error['msg'] .= "Wrong input for hemisphere (orientation)";
			}
		} else {
			$zz_error['msg'] .= "Please enter a value for the hemisphere. ";
			$error_nulls += 1;
		}
		if (substr($zz_error['msg'], strlen($zz_error['msg']) - 13, 13) == "[Latitude]:  " 
			or substr($zz_error['msg'], strlen($zz_error['msg']) - 13, 13) == "[Longitude]: " ) {
			$zz_error['msg'] = substr($zz_error['msg'], 0, strlen($zz_error['msg']) -13);
		}
	}
	
	// check if there were any errors
	
	if ($zz_error['msg'] != "") {
		// attention: we need to do something with all null values. They are allowed
		if ($error_nulls >= 6) { // six is not 100% perfect but seems enough for today
			// seems to be a null entry
			$new_coords['latdec'] = NULL;
			$new_coords['londec'] = NULL;
			return $new_coords;
		} else {
			// abort function
			return $new_coords;
		}
	} else {
		// okay, values seem to be correct

/*	output (the same for longitude, "lon"):
	$var['latdec'] = +69.176778
	$var['lat']['deg'] = 69
	$var['lat']['min'] = 10
	$var['lat']['sec'] = 36.4
	$var['lat']['orientation'] = 'N'
	$var['latitude'] = 69¡10'36.4"N	
*/

		$new_coords['latdec'] = $new_coords['lat']['deg'] + $new_coords['lat']['min'] / 60 + $new_coords['lat']['sec'] / 3600;
		$new_coords['latitude']  = $new_coords['lat']['deg'].'&deg;'.$new_coords['lat']['min']."'".$new_coords['lat']['sec'].'"';
		if ($new_coords['lat']['orientation'] = "+")
			$new_coords['latitude'] .= "N";
		elseif ($new_coords['lat']['orientation'] = "-")
			$new_coords['latitude'] .= "S";
		if ($new_coords['lat']['orientation'] == "-") {$new_coords['latdec'] = -$new_coords['latdec'];}
		$new_coords['londec'] = $new_coords['lon']['deg'] + $new_coords['lon']['min'] / 60 + $new_coords['lon']['sec'] / 3600;
		$new_coords['longitude']  = $new_coords['lon']['deg'].'&deg;'.$new_coords['lon']['min']."'".$new_coords['lon']['sec'].'"';
		if ($new_coords['lon']['orientation'] = "+")
			$new_coords['longitude'] .= "E";
		elseif ($new_coords['lon']['orientation'] = "-")
			$new_coords['longitude'] .= "W";
		if ($new_coords['lon']['orientation'] == "-") {$new_coords['londec'] = -$new_coords['londec'];}
		return $new_coords;	
	}
	
}
?>