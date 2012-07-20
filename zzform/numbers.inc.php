<?php

/**
 * zzform
 * Number/date functions
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2005-2010 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * converts given iso date to d.m.Y or returns date as is if incomplete
 * 
 * @param string $date date to be converted, international date or output of this function
 * @param string $language 2-letter-languagecode ISO 639-1 or 3-letter-code ISO 639-2T
 * @return string formatted date
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_date_format($date, $language = 'de') {
	global $zz_conf;
	if (!$date) return '';

	// convert ISO 639-1 codes to ISO 639-2T
	if ($language == 'de') $language = 'deu';
	if ($language == 'en') $language = 'eng';

	// international format, ISO 8601
	$date_separator['---'] = '-';
	$months['---'] = array('01' => '01', '02' => '02', '03' => '03', '04' => '04', 
		'05' => '05', '06' => '06', '07' => '07', '08' => '08', '09' => '09', 
		'10' => '10', '11' => '11', '12' => '12');
	$date_order['---'] = array('year', 'month', 'day');

	// german format (deu)
	$date_separator['deu'] = '.';
	$date_order['deu'] = array('day', 'month', 'year');

	// english format (eng)
	$date_separator['eng'] = '&nbsp;';
	$months['eng'] = array('01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', 
		'05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', 
		'10' => 'Oct', '11' => 'Nov', '12' => 'Dec');
	$date_order['eng'] = array('day', 'month', 'year');

	// default values: international format, or use language specific format
	$my_date_separator = !empty($date_separator[$language]) ? $date_separator[$language] : $date_separator['---'];
	$my_months = !empty($months[$language]) ? $months[$language] : $months['---'];
	$my_date_order = !empty($date_order[$language]) ? $date_order[$language] : $date_order['---'];

	if (preg_match("/^([0-9]{4}-[0-9]{2}-[0-9]{2}) [0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/", $date, $match)) {
		// DATETIME YYYY-MM-DD HH:ii:ss
		$date = $match[1]; // ignore time, it's a date function
	} elseif (preg_match("/^([0-9]{4})([0-9]{2})([0-9]{2})[0-2][0-9][0-5][0-9][0-5][0-9]$/", $date, $match)){
		// YYYYMMDD ...
		$date = $match[1].'-'.$match[2].'-'.$match[3]; // ignore time, it's a date function
	} elseif (!preg_match("/^[0-9-]+$/", $date)) 
		return $date; #wenn kein richtiges datum, einfach datum zurueckgeben.
	elseif (preg_match("/^[0-9]{1,4}$/", $date)) 
		return $date; #wenn nur ein bis vier ziffern, d. h. jahr, einfach jahr zurueckgeben

	$date_parts = explode("-", $date);
	$date = '';
	$date_parts['day'] = (!empty($date_parts[2]) AND $date_parts[2] != '00') ? $date_parts[2] : false;
	$date_parts['month'] = (!empty($date_parts[1]) AND $date_parts[1] != '00'
		AND $date_parts[1] > 0 AND  $date_parts[1] < 13) ? $my_months[$date_parts[1]] : false;
	
	if (substr($date_parts[0], 0, 1) == "0" AND substr($date_parts[0], 0, 2) != "00") {
		$date_parts['year'] = substr($date_parts[0], 1, 4);
	} else {
		$date_parts['year'] = $date_parts[0];
	}
	foreach ($my_date_order as $part) {
		if ($date) $date .= $my_date_separator;
		$date .= $date_parts[$part];
	}
	return $date;
}

/**
 * formats an integer into a readable byte representation
 *
 * @param int $byts
 * @param int $precision
 * @return string
 */
function zz_byte_format($bytes, $precision = 1) { 
	global $zz_conf;
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB'); 

    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 

    // Uncomment one of the following alternatives
    // $bytes /= pow(1024, $pow);
    $bytes /= (1 << (10 * $pow)); 

    $text = round($bytes, $precision) . '&nbsp;' . $units[$pow]; 
    if ($zz_conf['decimal_point'] !== '.')
    	$text = str_replace('.', $zz_conf['decimal_point'], $text);
    return $text;
}


?>