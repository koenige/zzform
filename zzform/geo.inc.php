<?php

/**
 * zzform
 * Module geo: handling of geographic coordinates
 * (input, output, transformation DMS - DD and vice versa)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2011, 2015-2017, 2019-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * checks whether a given input is a valid geo coordinate
 * and transforms it into a decimal number
 *
 * @param string $value coordinate input
 * @param string $orientation ('lat', 'lon', 'alt')
 * @param int $precision precision of double, 0 means will be left as is
 * @return array
 *		'value' => decimal value of coordinate
 *		'error' => error code
 */
function zz_geo_coord_in($value, $orientation = 'lat', $precision = 0) {
	if ($orientation === 'alt') return $value;

	$my['value'] = '';
	$my['error'] = '';
	if (is_null($value)) return $my;

	// set possible values for hemisphere
	$hemispheres = [
		'lat' => [
			'N' => '+',
			'S' => '-',
			wrap_text('N', ['context' => 'North']) => '+',
			wrap_text('S', ['context' => 'South']) => '-'
		],
		'lon' => [
			'E' => '+',
			'W' => '-',
			wrap_text('E', ['context' => 'East']) => '+',
			wrap_text('W', ['context' => 'West']) => '-'
		]
	];
	$hemisphere = '';

	// set some values depending on orientation
	switch ($orientation) {
	case 'lat':
	case 'latitude':
		$degree_max = [-90, 90];
		$degree_convert = false;
		$other_orientation = 'lon';
		$possible_hemispheres = $hemispheres['lat'];
		break;
	case 'lon':
	case 'longitude':
		$degree_max = [-360, 360];
		$degree_convert = [-180, 180];
		$other_orientation = 'lat';
		$possible_hemispheres = $hemispheres['lon'];
		break;
	default:
		$my['error'] = wrap_text(
			'Development error. Orientation %s is not valid.',
			['values' => zz_htmltag_escape($orientation)]
		);
		return $my;
	}

	// get rid of HTML entities
	$value = preg_replace('~&[^;]+;~', ' ', $value); 

	// check if last letter matches hemisphere
	if (in_array(substr($value, -1), array_keys($possible_hemispheres))) {
		$hemisphere_letter = substr($value, -1);
		$hemisphere = $possible_hemispheres[$hemisphere_letter];
		$value = substr($value, 0, -1);
	} elseif (in_array(substr($value, -1), array_keys($hemispheres[$other_orientation]))) {
		$my['error'] = wrap_text(
			'It looks like this coordinate has a different orientation. Maybe latitude and longitude were interchanged?'
		);
		return $my;
	}

	// check if first letter matches hemisphere
	if (in_array(substr($value, 0, 1), $possible_hemispheres)) {
		if ($hemisphere AND substr($value, 0, 1) != $hemisphere) {
			// mismatch
			$my['error'] = wrap_text(
				'Mismatch: %s signals different hemisphere than %s.', 
				['values' => [$hemisphere_letter, zz_htmltag_escape(substr($value, 0, 1))]]
			);
			return $my;
		} elseif (!$hemisphere) {
			$hemisphere = substr($value, 0, 1);
		}
		$value = substr($value, 1);
	}

	// get rid of all non numeric strings
	preg_match_all('~([0-9,.-]+)~', $value, $parts);
	
	// transform input into decimal number
	foreach ($parts[0] as $index => $part) {
		// normalize number
		$part = str_replace(',', '.', $part);
		// only one decimal separator is possible
		if (substr_count($part, '.') > 1) {
			$my['error'] = wrap_text(
				'There are too many decimal points (or commas) in this value.'
			);
			return $my;
		}
		// all values apart from the last value must be integers
		if ($index !== count($parts[0]) - 1 AND strstr($part, '.')) {
			$my['error'] = wrap_text(
				'Only the last number might have a decimal point (or comma).'
			);
			return $my;
		}

		switch ($index) {
		case 0: // degrees
			$decimal = $part;
			break;
		case 1: // minutes
		case 2: // seconds
			// test range, 0-59.9999 is allowed
			if ($part < 0) {
				$part = wrap_html_escape($part);
				$type = ($index === 2) ? 'seconds' : 'minutes';
				$my['error'] = wrap_text(
					'%s is too small. Please enter for '.$type.' a positive value or 0.',
					['values' => $part]
				);
				return $my;
			} elseif ($part >= 60) {
				$part = wrap_html_escape($part);
				$type = ($index === 2) ? 'seconds' : 'minutes';
				$my['error'] = wrap_text(
					'%s is too big. Please enter for '.$type.' a value smaller than 60.',
					['values' => $part]
				);
				return $my;
			}			
			// add or substract correct value to/from degrees
			$decimal += $part/(pow(60, $index));
			break;
		case 3:
			// no more than three parts
			$my['error'] = wrap_text(
				'Sorry, there are too many numbers. We cannot interpret what you entered.'
			);
			return $my;
		}
	}
	// add - sign
	if ($hemisphere === '-')
		$decimal = $hemisphere.$decimal;
	
	// check range
	if ($decimal < $degree_max[0]) {
		$my['error'] = wrap_text(
			'Minimum value for degrees is %s. The value you entered is too small: %s.',
			['values' => [$degree_max[0], $decimal]]
		);
		return $my;
	} elseif ($decimal > $degree_max[1]) {
		$my['error'] = wrap_text(
			'Maximum value for degrees is %s. The value you entered is too big: %s.',
			['values' => [$degree_max[1], $decimal]]
		);
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
 * output of a geographical coordinate from SQL
 *
 * @param string $point POINT(42.28 1.3)
 * @param $out output, see wrap_coordinate()
 * @param $concat HTML code between output of latitude and longitude
 * @return string $coord
 * @see wrap_coordinate()
 */
function zz_geo_coord_sql_out($point, $out = false, $concat = ', ') {
	if (substr($point, 0, 6) != 'POINT(') return false;
	if (substr($point, -1) != ')') return false;
	$point = substr($point, 6, -1);
	$point = explode(' ', $point);
	$text = wrap_latitude($point[0], $out);
	$text .= $concat;
	$text .= wrap_longitude($point[1], $out);
	return $text;
}

/**
 * transforms GPS coordinate array or string into decimal value
 *
 * @param mixed $values
 * @return double
 */
function zz_geo_calculate_fraction($values) {
	$num = false;
	if (is_array($values)) {
		if (count($values) !== 3) return $num;
		foreach ($values as $key => $val) {
			$val = explode('/', $val);
			if (!$val[1]) continue;
			if (!$key) {
				$num = $val[0] / $val[1];
			} elseif ($key === 1) {
				$num += $val[0] / $val[1] / 60;
			} elseif ($key === 2) {
				$num += $val[0] / $val[1] / 3600;
			}
		}
	} else {
		$val = explode('/', $values);
		if ($val[1]) $num = $val[0] / $val[1];
	}
	return $num;
}

/**
 * checks whether a given input is a valid value for a part of a coordinate
 * and transforms it into a decimal number
 *
 * @param mixed $value coordinate input
 * @param string $which
 * @return array
 *		'value' => decimal value of coordinate
 *		'error' => error code
 */
function zz_geo_coords_from_exif($value, $which) {
	// string: use function zz_geo_coord_in()
	if (!is_array($value)) return zz_geo_coord_in($value, substr($which, 0, 3));

	$my = ['value' => false, 'error' => false];
	$field = sprintf('GPS%s', ucfirst($which));
	$field_ref = sprintf('GPS%sRef', ucfirst($which));

	// array: import from EXIF GPS
	if (!in_array($field, array_keys($value))) return $my;
	if (!in_array($field_ref, array_keys($value))) return $my;

	if (wrap_setting('zzform_upload_tools_exiftool')) {
		$value[$field] = zz_exiftool_normalize($value[$field]);
		if ($value[$field] === false) return $my;
		$value[$field_ref] = zz_exiftool_normalize($value[$field_ref]);
	}

	switch ($which) {
		case 'longitude': $orientation = ['E' => '+', 'W' => '-']; break;
		case 'latitude':  $orientation = ['N' => '+', 'S' => '-']; break;
		case 'altitude':  $orientation = ['0' => '+', '1' => '-']; break;
	}

	// check if values are valid
	if (!in_array($value[$field_ref], array_keys($orientation))) return $my;

	$my['value'] = $value[$field];
	if (is_array($value[$field]) OR strstr($value[$field], '/')) {
		$my['value'] = zz_geo_calculate_fraction($value[$field]);
	}
	if ($my['value']) {
		// GPSAltitudeRef might be space or empty
		if (empty(trim($value[$field_ref]))) $my['value'] = '+'.$my['value'];
		else $my['value'] = $orientation[$value[$field_ref]].$my['value'];
	}
	return $my;
}

/**
 * normalize EXIF values from ExifTool
 * which come in 'num', 'val', and 'desc' if run with -l
 *
 * @param mixed $value
 * @return string $value
 */
function zz_exiftool_normalize($value) {
	if (!is_array($value)) return $value;
	if (isset($value['num'])) return $value['num'];
	if (isset($value['val'])) {
		if ($value['val'] === 'undef') return false;
		return $value['val'];
	}
	return $value;
}

/**
 * checks whether a given input is a valid latitude coordinate
 * and transforms it into a decimal number
 *
 * @param mixed $value coordinate input
 * @see zz_geo_coord_in()
 */
function zz_geo_latitude_in($value) {
	return zz_geo_coords_from_exif($value, 'latitude');
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
	return zz_geo_coords_from_exif($value, 'longitude');
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
	return zz_geo_coords_from_exif($value, 'altitude');
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
 * @param array $ops
 * @param array $zz_tab
 * @return array $change
 */
function zz_geo_geocode($ops, $zz_tab) {
	$geocoding = zz_geo_geocode_fields($ops['validated'], $ops['record_new'], $zz_tab);
	if (!$geocoding) return [];
	if (!array_key_exists('latlon', $geocoding)) return [];
	if (!array_key_exists('source', $geocoding)) return [];

	// update coordinates:
	$update = false;
	foreach ($geocoding['latlon'] as $type => $f) {
		// - if either latitude or longitude are NULL
		$my_field = $zz_tab[$f['tab']][$f['rec']]['fields'][$f['no']];
		$field = $ops['record_new'][$f['index']][$my_field['field_name']];
		if (!$field AND $field !== 0 AND $field !== '0') {
			$update = true;
		} elseif ($ops['record_old'][$f['index']]) {
			// do not update if coordinates were changed by user
			// test against output strings, there may be rounding errors
			if (wrap_coordinate($ops['record_old'][$f['index']][$my_field['field_name']], $type)
				!== wrap_coordinate($ops['record_new'][$f['index']][$my_field['field_name']], $type)) {
				return [];
			}
		}
	}
	if (!$update) {
		foreach ($geocoding['source'] as $type => $f) {
			$my_field = $zz_tab[$f['tab']][$f['rec']]['fields'][$f['no']];
			if (!empty($my_field['geocode_ignore_update'])) continue;
			$field = $ops['record_diff'][$f['index']][$my_field['field_name']];
			if ($field !== 'same') $update = true;
		}
	}
	if (!$update) return [];

	$address = zz_geo_geocode_address($geocoding, $zz_tab, $ops['record_new']);
	if (count($address) === 1 AND array_key_exists('place', $address)) return [];
	if (!$address) return [];

	require_once wrap_setting('core').'/syndication.inc.php';
	$result = wrap_syndication_geocode($address);
	if (!$result) return [];

	$change = [];
	if ($result['longitude']) {
		$f = $geocoding['latlon']['longitude'];
		$my_field = $zz_tab[$f['tab']][$f['rec']]['fields'][$f['no']];
		$change['record_replace'][$f['index']][$my_field['field_name']] = $result['longitude'];
		$change['change_info'][$f['index']]['geocode'] = true;
	}
	if ($result['latitude']) {
		$f = $geocoding['latlon']['latitude'];
		$my_field = $zz_tab[$f['tab']][$f['rec']]['fields'][$f['no']];
		$change['record_replace'][$f['index']][$my_field['field_name']] = $result['latitude'];
		$change['change_info'][$f['index']]['geocode'] = true;
	}
	if ($result['postal_code'] AND isset($geocoding['source']['postal_code'])) {
		$f = $geocoding['source']['postal_code'];
		$my_field = $zz_tab[$f['tab']][$f['rec']]['fields'][$f['no']];
		$change['record_replace'][$f['index']][$my_field['field_name']] = $result['postal_code'];
		$change['change_info'][$f['index']]['geocode'] = true;
	}
	return $change;
}

/**
 * get fields for geocoding
 *
 * @param array $list (= $ops['planned'])
 * @param array $new (= $ops['record'])
 * @param array $zz_tab
 * @return array
 */
function zz_geo_geocode_fields($list, $new, $zz_tab) {
	$geocoding = [];
	foreach ($list as $index => $planned) {
		$tabrec = explode('-', $planned['tab-rec']);
		// get fields with latitude and longitude
		// get input fields
		$my_fields = $zz_tab[$tabrec[0]][$tabrec[1]]['fields'];
		foreach ($my_fields as $no => $field) {
			if (empty($field['field_name'])) continue;
			if (empty($field['geocode'])) continue;
			$type = in_array($field['geocode'], ['latitude', 'longitude']) ? 'latlon' : 'source';
			if (!array_key_exists($type, $geocoding))
				$geocoding[$type] = [];
			if (zz_geo_geocode_ignore($field, $my_fields, $new, $index)) {
				continue;
			}
			if (array_key_exists($field['geocode'], $geocoding[$type])) {
				// Important: we only look at the first occurence of each geocoding field!
				continue;
			}
			$geocoding[$type][$field['geocode']] = [
				'tab' => $tabrec[0], 'rec' => $tabrec[1], 'no' => $no, 'index' => $index
			];
		}
	}
	if (empty($geocoding['latlon'])) return [];
	if (count($geocoding['latlon']) !== 2) {
		zz_error_log([
			'msg_dev' => 'Record definition incorrect, only one of latitude/longitude present.'
		]);
		return [];
	}
	return $geocoding;
}

/**
 * get values for address from geocoding fields
 *
 * @param array $geocoding (result of zz_geo_geocode_fields())
 * @param array $zz_tab
 * @param array $new ($ops['record_new'])
 * @return array
 */
function zz_geo_geocode_address($geocoding, $zz_tab, $new) {
	// - if address fields have changed
	$address = [];
	foreach ($geocoding['source'] as $type => $f) {
	// street, street_number, locality, postal_code, country
	// each with _id
		$my_field = $zz_tab[$f['tab']][$f['rec']]['fields'][$f['no']];
		if (isset($new[$f['index']][$my_field['field_name']])) {
			$value = $new[$f['index']][$my_field['field_name']];
		} elseif (isset($my_field['geocode_default'])) {
			$value = $my_field['geocode_default'];
		}
		if (substr($type, -3) === '_id') {
			$type = substr($type, 0, -3);
			if (!isset($my_field['geocode_sql'])) {
				zz_error_log([
					'msg_dev' => 'Error: geocode_sql not defined for field no %d',
					'msg_dev_args' => [$f['no']]
				]);
				continue;
			}
			if (!is_numeric($value)) {
				$values = zz_check_select_id($my_field, $value);
				if (!empty($values['possible_values']) AND count($values['possible_values']) === 1) {
					$value = reset($values['possible_values']);
				} else {
					continue;
				}
			}
			$sql = sprintf($my_field['geocode_sql'], $value);
			$value = zz_db_fetch($sql, '', 'single value');
		}
		if (!$value) continue;
		if (wrap_setting('character_set') !== 'utf-8') {
			// @todo support more encodings than iso-8859-1 and utf-8
			$value = iconv(strtoupper(wrap_setting('character_set')), 'UTF-8', $value);
		}
		$address[$type] = $value;
	}
	return $address;
}

/**
 * check if geocode will be ignored because of another value in the same record
 *
 * @param array $field current field
 * @param array $fields list of fields of this record
 * @param array $new new record as in $ops['record_new']
 * @param int $index
 * @return bool
 */
function zz_geo_geocode_ignore($field, $fields, $new, $index) {
	if (empty($field['geocode_ignore_if'])) return false;
	reset($field['geocode_ignore_if']);
	$ignore_field_name = key($field['geocode_ignore_if']);
	$ignore_value = $field['geocode_ignore_if'][$ignore_field_name];

	foreach ($fields as $ignore_no => $ignore_field) {
		if (empty($ignore_field['field_name'])) continue;
		if ($ignore_field['field_name'] !== $ignore_field_name) continue;
		$value = $new[$index][$ignore_field_name];
		if ($ignore_field['type'] === 'select' AND !is_numeric($value)) {
			$p_values = zz_check_select_id($ignore_field, $value);
			if (!empty($p_values['possible_values']) AND count($p_values['possible_values']) === 1) {
				$value = reset($p_values['possible_values']);
			} else {
				continue;
			}
		}
		if ($ignore_value === $value) return true;
	}
	return false;
}
