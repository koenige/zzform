<?php

// 34 16 55.5 N
// dms2db($_POST)

/*
still missing:

- check whether strings are entered. Strings result in a 0-Coordinate
- disallow 4.5deg or 50.5 minutes

*/

function dms2db($coords) {

	global $error_message;
	$error_stop = false;
	$error_message = '';

	$mers = array (
		1 => 'lat',
		2 => 'lon'
		);
	
	// test, whether values are ok.
	
	$error_nulls = 0;
	
	foreach ($mers as $mer) {
		if ($mer == 'lat')
			$error_message .= "[Latitude]:  ";
		elseif ($mer == 'lon')
			$error_message .= "[Longitude]: ";
		if (isset($coords[$mer])) {
			if ($coords[$mer]['deg'] != NULL) {
				#if (gettype($coords[$mer]['deg']) != "integer") {
					if ($coords[$mer]['deg'] > 180 ) {
						// Coordinates are out of world range
						$error_message .= "The coordinate system of the world only allows values under 180 degrees. ";
					} elseif ($coords[$mer]['deg'] < 0) {
						// Coordinates are negative
						$error_message .= "Values under zero are not allowed. To enter data for the western or southern hemisphere, please use the orientation field. ";
					}			
				#} else {
				#	$test = gettype($coords[$mer]['deg']);
				#}
			} else {
				$error_message .= "Please enter a value for degrees. ";
				$error_nulls += 1;
			}
			if ($coords[$mer]['min'] != NULL) {
				#if (gettype($coords[$mer]['min']) == "integer") {
					if ($coords[$mer]['min'] >= 60 ) {
						// Coordinates are out of world range
						$error_message .= "There are only 60 minutes in a degree. ";
					} elseif ($coords[$mer]['min'] < 0) {
						// Coordinates are negative
						$error_message .= "Negative values for minutes are not accepted. ";
					}			
				#} else {
				#	$error_message .= "Only integer values are accepted for minutes. ";
				#}
			} else {
				$error_message .= "Please enter a value for minutes. ";
				$error_nulls += 1;
			}
			if ($coords[$mer]['sec'] != NULL) {
				// check, whether a comma has been used instead of a decimal point
				$seconds = explode(",", $coords[$mer]['sec']);
				if (isset($seconds[1])) {
					$coords[$mer]['sec'] = $seconds[0].".".$seconds[1];
				}
				#if (gettype($coords[$mer]['sec']) == "integer" or $coords[$mer]['sec'] == "double") {
					if ($coords[$mer]['sec'] >= 60 ) {
						// Coordinates are out of world range
						$error_message .= "There are only 60 seconds in a minute. ";
					} elseif ($coords[$mer]['sec'] < 0) {
						// Coordinates are negative
						$error_message .= "Negative values for seconds are not accepted. ";
					}			
				#} else {
				#	$error_message .= "Only numerical values are accepted for seconds. ";
				#}
			} else {
				$error_message .= "Please enter a value for seconds. ";
				$error_nulls += 1;
			}
			if ($coords[$mer]['orientation'] != NULL) {
				if ($coords[$mer]['orientation'] != "+" and $coords[$mer]['orientation'] != "-" ) {
					$error_message .= "Wrong input for hemisphere (orientation)";
				}
			} else {
				$error_message .= "Please enter a value for the hemisphere. ";
				$error_nulls += 1;
			}
		}
		if (substr($error_message, strlen($error_message) - 13, 13) == "[Latitude]:  " 
			or substr($error_message, strlen($error_message) - 13, 13) == "[Longitude]: " ) {
			$error_message = substr($error_message, 0, strlen($error_message) -13);
		}
	}
	
	// check if there were any errors
	
	if ($error_message != "") {
		// attention: we need to do something with all null values. They are allowed
		if ($error_nulls >= 6) { // six is not 100% perfect but seems enough for today
			// seems to be a null entry
			$coords['latdec'] = NULL;
			$coords['londec'] = NULL;
			return $coords;
		} else {
			// abort function
			$coords['latdec'] = NULL;
			$coords['londec'] = NULL;
			return $coords;
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
		if (isset($coords['lat'])) {
			$coords['latdec'] = $coords['lat']['deg'] + $coords['lat']['min'] / 60 + $coords['lat']['sec'] / 3600;
			$coords['latitude']  = $coords['lat']['deg'].'&deg;'.$coords['lat']['min']."'".$coords['lat']['sec'].'"';
			if ($coords['lat']['orientation'] = "+")
				$coords['latitude'] .= "N";
			elseif ($coords['lat']['orientation'] = "-")
				$coords['latitude'] .= "S";
			if ($coords['lat']['orientation'] == "-") {$coords['latdec'] = -$coords['latdec'];}
		} else $coords['latdec'] = false;
		if (isset($coords['lon'])) {
			$coords['londec'] = $coords['lon']['deg'] + $coords['lon']['min'] / 60 + $coords['lon']['sec'] / 3600;
			$coords['longitude']  = $coords['lon']['deg'].'&deg;'.$coords['lon']['min']."'".$coords['lon']['sec'].'"';
			if ($coords['lon']['orientation'] = "+")
				$coords['longitude'] .= "E";
			elseif ($coords['lon']['orientation'] = "-")
				$coords['longitude'] .= "W";
			if ($coords['lon']['orientation'] == "-") {$coords['londec'] = -$coords['londec'];}
		} else $coords['londec'] = false;
		return $coords;	
	}
	
}
?>