<?php

/**
 * zzform
 * Module geo: handling of geographic coordinates
 * (input, output, transformation DMS - DD and vice versa)
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2011 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * checks whether a given input is a valid geo coordinate
 * and transforms it into a decimal number
 *
 * @param string $value coordinate input
 * @param string $orientation ('lat', 'lon')
 * @param int $precision precision of double, 0 means will be left as is
 * @return array
 *		'value' => decimal value of coordinate
 *		'error' => error code
 */
function zz_geo_coord_in($value, $orientation = 'lat', $precision = 0) {
	$my['value'] = '';
	$my['error'] = '';
	if ($value == NULL) {
		return $my;
	}
	$orientation = zz_geo_orientation($orientation);
	if (!$orientation) {
		$my['error'] = 'Development error. Orientation '.htmlspecialchars($orientation).' is not valid.';
		return $my;
	}

	// get rid of HTML entities
	$value = preg_replace('~&[^;]+;~', ' ', $value); 

	// set possible values for hemisphere
	$hemispheres['lat'] = array('N' => '+', 'S' => '-',
		zz_text('N') => '+', zz_text('S') => '-');
	$hemispheres['lon'] = array('E' => '+', 'W' => '-',
		zz_text('E') => '+', zz_text('W') => '-');
	$hemisphere = '';

	// set some values depending on orientation
	$possible_hemispheres = $hemispheres[$orientation];
	if ($orientation == 'lat') {
		$degree_max = array(-90, 90);
		$degree_convert = false;
		$other_orientation = 'lon';
	} else {
		$degree_max = array(-360, 360);
		$degree_convert = array(-180, 180);
		$other_orientation = 'lat';
	}

	// check if last letter matches hemisphere
	if (in_array(substr($value, -1), array_keys($possible_hemispheres))) {
		$hemisphere_letter = substr($value, -1); 
		$hemisphere = $possible_hemispheres[$hemisphere_letter];
		$value = substr($value, 0, -1);
	} elseif (in_array(substr($value, -1), array_keys($hemispheres[$other_orientation]))) {
		$my['error'] = zz_text('It looks like this coordinate has a different orientation. Maybe latitude and longitude were interchanged?');
		return $my;
	}

	// check if first letter matches hemisphere
	if (in_array(substr($value, 0, 1), $possible_hemispheres)) {
		if ($hemisphere AND substr($value, 0, 1) != $hemisphere) {
			// mismatch
			$my['error'] = sprintf(zz_text('Mismatch: %s signals different hemisphere than %s.'),
				$hemisphere_letter, htmlspecialchars(substr($value, 0, 1)));
			return $my;
		} elseif (!$hemisphere) {
			$hemisphere = substr($value, 0, 1);
		}
		$value = substr($value, 1);
	}

	// get rid of all non numeric strings
	preg_match_all('~([0-9,.-]+)~', $value, $parts);
	$decimal = $hemisphere;

	// transform input into decimal number
	foreach ($parts[0] as $index => $part) {
		// normalize number
		$part = str_replace(',', '.', $part);
		// only one decimal separator is possible
		if (substr_count($part, '.') > 1) {
			$my['error'] = zz_text('There are too many decimal points (or commas) in this value.');
			return $my;
		}
		// all values apart from the last value must be integers
		if ($index != count($parts[0])-1 AND strstr($part, '.')) {
			$my['error'] = zz_text('Only the last number might have a decimal point (or comma).');
			return $my;
		}

		switch ($index) {
		case 0: // degrees
			$decimal = ($decimal == '-') ? -$part : $part;
			break;
		case 1: // minutes
		case 2: // seconds
			// test range, 0-59.9999 is allowed
			if ($part < 0) {
				$part = htmlspecialchars($part);
				$type = ($index == 2) ? 'seconds' : 'minutes';
				$my['error'] = sprintf(zz_text('%s is too small. Please enter for '.$type.' a positive value or 0.'), $part);
				return $my;
			} elseif ($part >= 60) {
				$part = htmlspecialchars($part);
				$type = ($index == 2) ? 'seconds' : 'minutes';
				$my['error'] = sprintf(zz_text('%s is too big. Please enter for '.$type.' a value smaller than 60.'), $part);
				return $my;
			}			
			// add or substract correct value to/from degrees
			if ($decimal < 0) $decimal -= $part/(pow(60, $index));
			else $decimal += $part/(pow(60, $index));
			break;
		case 3:
			// no more than three parts
			$my['error'] = zz_text('Sorry, there are too many numbers. We cannot interpret what you entered.');
			return $my;
		}
	}
	// check range
	if ($decimal < $degree_max[0]) {
		$my['error'] = sprintf(zz_text('Minimum value for degrees is %s. The value you entered is too small: %s.'),
			$degree_max[0], $decimal);
		return $my;
	} elseif ($decimal > $degree_max[1]) {
		$my['error'] = sprintf(zz_text('Maximum value for degrees is %s. The value you entered is too big: %s.'),
			$degree_max[1], $decimal);
		return $my;
	}
	if ($degree_convert) {
		if ($decimal < $degree_convert[0]) {
			$decimal += 360; // -183°W = +177°E
		} elseif ($decimal > $degree_convert[1]) {
			$decimal -= 360; // +210°E = -150°W
		}
	}
	if ($precision) $decimal = round($decimal, $precision);
	$my['value'] = $decimal;
	return $my;
}

/**
 * output of a geographical coordinate
 * 19°41'59"N 98°50'38"W / 19.6996°N 98.8440°W / 19.6996; -98.8440
 *
 * @param double $decimal value of coordinate, e. g. 69.34829922
 * @param string $orientation ('lat' or 'lon')
 * @param string $out output
 *		'dms' = degree + minute + second; 'deg' = degree, 'dm' = degree + minute,
 *		'dec' = decimal value; all may be appended by =other, e. g. dms=deg
 * @global array $zz_conf
 *		$zz_conf['geo']['rounding']
 * @return string $coord
 */
function zz_geo_coord_out($decimal, $orientation = 'lat', $out = false) {
	global $zz_conf;

	if ($decimal == NULL) return false;
	if (function_exists('wrap_text')) $textfunc = 'wrap_text';
	else $textfunc = 'zz_text';
	$coord = false;
	$round = isset($zz_conf['geo']['rounding']) ? $zz_conf['geo']['rounding'] : 2;
	$spacer = isset($zz_conf['geo']['spacer']) ? $zz_conf['geo']['spacer'] : '&#160;';
	if ($decimal === false) return false;
	
	// 1. Test orientation
	$orientation = zz_geo_orientation($orientation);
	if (!$orientation) return false;
	
	// 2. get some information
	$hemisphere = ($decimal >= 0) ? '+' : '-';
	if ($decimal < 0) $decimal = substr($decimal, 1); // get rid of - sign)
	switch ($orientation) {
		case 'lat':
			$hemisphere_text = ($hemisphere == '+') ? strip_tags($textfunc('N')) : strip_tags($textfunc('S'));
			break;
		case 'lon':
			$hemisphere_text = ($hemisphere == '+') ? strip_tags($textfunc('E')) : strip_tags($textfunc('W'));
			break;
	}
	
	// 3. Output in desired format
	$formats = explode('=', $out);
	foreach ($formats as $format) {
		switch ($format) {
		case 'o':
			$coord[] = $hemisphere_text;
			break;
		case 'deg':	// 98.8440°W
			$coord[] = zz_decimal($decimal).'&#176;'.$spacer.$hemisphere_text;
			break;
		case 'dec':	// -98.8440
			$coord[] = $hemisphere.zz_decimal($decimal);
			break;
		case 'dm':	// 98°50.6333'W
			$min = zz_decimal(round(($decimal-floor($decimal))*60, $round));
			$coord[] = floor($decimal).'&#176;'.$spacer.($min ? $min.'&#8242;'.$spacer : '').$hemisphere_text;
			break;
		case 'dms':	// 98°50'38"W
		default:
			// transform decimal value to seconds and round first!
			$sec = round($decimal * 3600, $round);
			$remaining_sec = $sec % 3600;
			$deg = $sec - $remaining_sec;
			$remaining_sec_parts = round($deg - floor($deg), $round);
			$deg = floor($deg / 3600);
			$sec = $remaining_sec % 60;
			$min = ($remaining_sec - $sec) / 60;
			$sec += $remaining_sec_parts;
			$coord[] = $deg.'&#176;'.$spacer
				.(($min OR $sec) ? $min.'&#8242;'.$spacer : '')
				.($sec ? zz_decimal($sec).'&#8243;'.$spacer : '')
				.$hemisphere_text;
			break;
		}
	}
	if (!$coord) return false;
	if (count($coord) == 1) {
		$coord = $coord[0];
	} else {
		$coord = '('.$coord[0].' = '.$coord[1].')';
	}
	return $coord;
}

/**
 * output of a geographical coordinate from SQL
 *
 * @param string $point POINT(42.28 1.3)
 * @param $out output, see zz_geo_coord_out()
 * @param $concat HTML code between output of latitude and longitude
 * @return string $coord
 * @see zz_geo_coord_out()
 */
function zz_geo_coord_sql_out($point, $out = false, $concat = ', ') {
	if (substr($point, 0, 6) != 'POINT(') return false;
	if (substr($point, -1) != ')') return false;
	$point = substr($point, 6, -1);
	$point = explode(' ', $point);
	$text = zz_geo_coord_out($point[0], 'lat', $out);
	$text .= $concat;
	$text .= zz_geo_coord_out($point[1], 'lon', $out);
	return $text;
}

/**
 * formats a number depending on language with . or ,
 *
 * @param string $number
 * @global array $zz_conf ($zz_conf['language'])
 * @return string $number
 */
function zz_decimal($number) {
	global $zz_conf;
	// replace . with , where appropriate
	$number = str_replace('.', $zz_conf['decimal_point'], $number);
	return $number;
}

/**
 * allows shorthand notation for orientation, returns corresponding value
 *
 * @param string $orientation
 * @return string $orientation, long notation
 */
function zz_geo_orientation($orientation) {
	// test orientation
	$orientations = array('latitude' => 'lat', 'longitude' => 'lon');
	if (in_array($orientation, array_keys($orientations))) {
		return $orientations[$orientation];
	} elseif (!in_array($orientation, $orientations)) {
		return false;
	}
	return $orientation;
}

/**
 * transforms GPS coordinate information into decimal value
 *
 * @param string $type
 * @param string $ref
 * @param array $values
 * @return double
 */
function zz_geo_coords_gps_in($type, $ref, $values) {
	switch ($type) {
		case 'latitude':
		case 'longitude':
			foreach ($values as $key => $val) {
				$val = explode('/', $val);
				if (!$val[1]) continue;
				if (!$key) {
					$num = $val[0]/$val[1];
				} elseif ($key == 1) {
					$num += $val[0]/$val[1]/60;
				} elseif ($key == 2) {
					$num += $val[0]/$val[1]/3600;
				}
			}
			if (!isset($num)) return NULL;
			// western and southern hemisphere have negative values
			if ($ref == 'S' OR $ref == 'W') $num = -$num;
			return $num;
			break;
		case 'altitude':
			$val = explode('/', $values);
			if ($val[1])
				$num = $val[0]/$val[1];
			else return false;
			if ($ref == 1) $num = -$num;
			return $num;
			break;
		default:
			return false;
	}
}

/**
 * checks whether a given input is a valid latitude coordinate
 * and transforms it into a decimal number
 *
 * @param mixed $value coordinate input
 * @return array
 *		'value' => decimal value of coordinate
 *		'error' => error code
 * @see zz_geo_coord_in()
 */
function zz_geo_latitude_in($value) {
	// string: use function zz_geo_coord_in()
	if (!is_array($value)) return zz_geo_coord_in($value, 'lat');
	$my = array('value' => false, 'error' => false);

	// array: import from EXIF GPS
	if (!in_array('GPSLatitudeRef', array_keys($value))) return $my;
	if (!in_array('GPSLatitude', array_keys($value))) return $my;

	// check if values are valid
	$orientation = array('N' => '+', 'S' => '-');
	if (!in_array($value['GPSLatitudeRef'], array_keys($orientation))) return $my;
	if (count($value['GPSLatitude']) != 3) return $my;
	
	$my['value'] = zz_geo_coords_gps_in('latitude', $value['GPSLatitudeRef'], $value['GPSLatitude']);
	return $my;
}

/**
 * checks whether a given input is a valid longitude coordinate
 * and transforms it into a decimal number
 *
 * @param mixed $value coordinate input
 * @return array
 *		'value' => decimal value of coordinate
 *		'error' => error code
 * @see zz_geo_coord_in()
 */
function zz_geo_longitude_in($value) {
	// string: use function zz_geo_coord_in()
	if (!is_array($value)) return zz_geo_coord_in($value, 'lon');
	
	$my = array('value' => false, 'error' => false);

	// array: import from EXIF GPS
	if (!in_array('GPSLongitudeRef', array_keys($value))) return $my;
	if (!in_array('GPSLongitude', array_keys($value))) return $my;

	// check if values are valid
	$orientation = array('E' => '+', 'W' => '-');
	if (!in_array($value['GPSLongitudeRef'], array_keys($orientation))) return $my;
	if (count($value['GPSLongitude']) != 3) return $my;
	
	$my['value'] = zz_geo_coords_gps_in('longitude', $value['GPSLongitudeRef'], $value['GPSLongitude']);
	return $my;
}

/**
 * checks whether a given input is a valid altitude
 * and transforms it into a decimal number
 *
 * @param mixed $value coordinate input
 * @return array
 *		'value' => decimal value of coordinate
 *		'error' => error code
 */
function zz_geo_altitude_in($value) {
	// string: use function zz_geo_coord_in()
	if (!is_array($value)) return $value;

	$my = array('value' => false, 'error' => false);

	// array: import from EXIF GPS
	if (!in_array('GPSAltitudeRef', array_keys($value))) return $my;
	if (!in_array('GPSAltitude', array_keys($value))) return $my;

	$orientation = array('0' => '+', '1' => '-');
	if (!in_array($value['GPSAltitudeRef'], array_keys($orientation))) return $my;
	if (count($value['GPSAltitude']) != 1) return $my;
	
	$my['value'] = zz_geo_coords_gps_in('altitude', $value['GPSAltitudeRef'], $value['GPSAltitude']);
	return $my;
}

/**
 * transforms a GPS timestamp into a normal timestamp HH:mm:ss
 *
 * @param array $value, e. g.
 *		[0] => 13/1, [1] => 23/1, [2] => 4203/100
 * @return string $time
 */
function zz_geo_timestamp_in($value) {
	if (!is_array($value)) return $value;
	if (count($value) != 3) return false;
	foreach ($value as $part) {
		$parts = explode('/', $part);
		if (!$parts[0]) $time[] = '00';
		else $time[] = round($parts[0]/$parts[1]);
	}
	$time = implode(':', $time);
	return $time;
}

/**
 * get latitude and longitude from a geocoding API depending on some fields
 *
 * @param string $type ('after_insert' or 'after_update')
 * @param array $ops
 * @param array $zz_tab
 * @return array $change
 */
function zz_geo_geocode($type, $ops, $zz_tab) {
	global $zz_error;
	global $zz_conf;
	
	if (!in_array($type, array('before_insert', 'before_update'))) {
		$zz_error[]['msg_dev'] = sprintf(
			'Calling %s with wrong type %s', __FUNCTION__, $type
		);
		return array();
	}

	if (empty($zz_conf['geocoding_function'])) {
		// you'll need a function that returns from Array $address
		// an Array with latitude, longitude and postal_code (optional)
		require_once $zz_setting['lib'].'/zzwrap/syndication.inc.php';
		$zz_conf['geocoding_function'] = 'wrap_syndication_geocode';
	}

	$change = array();
	foreach ($ops['planned'] as $index => $planned) {
		$tabrec = explode('-', $planned['tab-rec']);
		$geocoding = array();
		$latlon = array();
		// get fields with latitude and longitude
		// get input fields
		$my_fields = $zz_tab[$tabrec[0]][$tabrec[1]]['fields'];
		foreach ($my_fields as $no => $field) {
			if (empty($field['field_name'])) continue;
			if (empty($field['geocode'])) continue;
			if (in_array($field['geocode'], array('latitude', 'longitude'))) {
				$latlon[$field['geocode']] = $no;
			} else {
				$geocoding[$field['geocode']] = $no;
			}
		}
		if (!$latlon) continue;
		if (count($latlon) !== 2) {
			$zz_error[]['msg_dev'] = 'Record definition incorrect, only one of latitude/longitude present.';
			continue;
		}
		// update coordinates:
		$update = false;

		// - if either latitude or longitude are NULL
		foreach ($latlon as $type => $no) {
			$field = $ops['record_new'][$index][$my_fields[$no]['field_name']];
			if (!$field AND $field !== 0 AND $field !== '0') $update = true;
		}

		// - if address fields have changed
		if (!$update) {
			foreach ($geocoding as $type => $no) {
				$field = $ops['record_diff'][$index][$my_fields[$no]['field_name']];
				if ($field !== 'same') $update = true;
			}
		}
		if ($update) {
			$address = array();
			foreach ($geocoding as $type => $no) {
			// street, street_number, locality, postal_code, country
			// each with _id
				$value = $ops['record_new'][$index][$my_fields[$no]['field_name']];
				if (substr($type, -3) === '_id') {
					$type = substr($type, 0, -3);
					if (!isset($my_fields[$no]['geocode_sql'])) {
						$zz_error[]['msg_dev'] = sprintf('Error: geocode_sql not defined for field no %d', $no);
						continue 2;
					}
					$sql = sprintf($my_fields[$no]['geocode_sql'], $value);
					$value = zz_db_fetch($sql, '', 'single value');
				}
				if ($zz_conf['character_set'] !== 'utf-8') {
					// @todo: support more encodings than iso-8859-1 and utf-8
					$value = utf8_encode($value);
				}
				$address[$type] = $value;
			}
			$result = $zz_conf['geocoding_function']($address);
			if ($result) {
				if ($result['longitude'])
					$change['record_replace'][$index][$my_fields[$latlon['longitude']]['field_name']] = $result['longitude'];
				if ($result['latitude'])
					$change['record_replace'][$index][$my_fields[$latlon['latitude']]['field_name']] = $result['latitude'];
				if ($result['postal_code'] AND isset($geocoding['postal_code']))
					$change['record_replace'][$index][$my_fields[$geocoding['postal_code']]['field_name']] = $result['postal_code'];
			}
		}
	}
	return $change;
}

?>