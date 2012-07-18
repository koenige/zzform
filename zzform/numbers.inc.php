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
 * @param string $datum date to be converted, international date or output of this function
 * @param string $param without-year: cuts year from date; short: returns short year
 * @param string $language 2-letter-languagecode ISO 639-1 or 3-letter-code ISO 639-2T
 * @return string formatted date
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function datum_de($datum, $param = false, $language = 'de') {
	if (!$datum) return false;

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

	if (preg_match("/^([0-9]{4}-[0-9]{2}-[0-9]{2}) [0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/", $datum, $match)) {
		// DATETIME YYYY-MM-DD HH:ii:ss
		$datum = $match[1]; // ignore time, it's a date function
	} elseif (preg_match("/^([0-9]{4})([0-9]{2})([0-9]{2})[0-2][0-9][0-5][0-9][0-5][0-9]$/", $datum, $match)){
		// YYYYMMDD ...
		$datum = $match[1].'-'.$match[2].'-'.$match[3]; // ignore time, it's a date function
	} elseif (!preg_match("/^[0-9-]+$/", $datum)) 
		return $datum; #wenn kein richtiges datum, einfach datum zurueckgeben.
	elseif (preg_match("/^[0-9]{1,4}$/", $datum)) 
		return $datum; #wenn nur ein bis vier ziffern, d. h. jahr, einfach jahr zurueckgeben

	$date_parts = explode("-", $datum);
	$datum = '';
	$date_parts['day'] = (!empty($date_parts[2]) AND $date_parts[2] != '00') ? $date_parts[2] : false;
	$date_parts['month'] = (!empty($date_parts[1]) AND $date_parts[1] != '00'
		AND $date_parts[1] > 0 AND  $date_parts[1] < 13) ? $my_months[$date_parts[1]] : false;
	
	if (substr($date_parts[0], 0, 1) == "0" AND substr($date_parts[0], 0, 2) != "00")
		$date_parts['year'] = substr($date_parts[0], 1, 4);
	else
		switch ($param) {
		case 'without-year':
			$date_parts['year'] = false;
			break;
		case 'short':
			$date_parts['year'] = substr($date_parts[0],2);
			break;
		default:
			$date_parts['year'] = $date_parts[0];
		}
	foreach ($my_date_order as $part) {
		if ($datum) $datum .= $my_date_separator;
		$datum .= $date_parts[$part];
	}
	return $datum;
}

/**
 * converts user input date into international date string
 * 
 * @param string $datum date in several possible formats
 * @return string international date YYYY-MM-DD
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function datum_int($datum) {
	if ($datum) {
		$months = array(
			1 => array('Januar', 'January', 'janvier', 'Jan', 'I'),
			2 => array('Februar', 'February', 'février', 'Feb', 'F.vrier', 'II'),
			3 => array('März', 'March', 'mars', 'Mar', 'M.r', 'M.rz', 'III'),
			4 => array('April', 'avril', 'Apr', 'IV'),
			5 => array('Mai', 'May', 'fevrier', 'V'),
			6 => array('Juni', 'June', 'juin', 'Jun', 'VI'),
			7 => array('Juli', 'July', 'juilett', 'Jul', 'VII'),
			8 => array('August', 'août', 'ao.t', 'Aug', 'VIII'),
			9 => array('September', 'Septembre', 'Sep', 'IX'),
			10 => array('Oktober', 'October', 'octobre', 'Oct', 'Okt', 'X'),
			11 => array('November', 'novembre', 'Nov', 'XI'),
			12 => array('Dezember', 'December', 'décembre', 'd.cembre', 'Dec', 'Dez', 'XII')
		);
		$datum = trim($datum); // remove unnecessary whitespace
		foreach ($months as $month => $values)
			foreach ($values as $value)
				if (preg_match('/^[^a-z]*'.$value.'[^a-z]/i', $datum)) {
					$datum = preg_replace('/'.$value.'/i', $month, $datum);
					$newdate['month_a'] = $month;
				}
		if (preg_match('/^[0-9]{6}$/', $datum)) {// 250376 or 032576 or 760325
			$newdate['day'] = substr($datum, 0, 2);
			$newdate['month'] = substr($datum, 2, 2);
			$newdate['year'] = substr($datum, 4, 2);
			if ($newdate['month'] > 12) {
				$newdate['day'] = substr($datum, 2, 2);
				$newdate['month'] = substr($datum, 0, 2);
			} elseif ($newdate['day'] > 31) {
				$newdate['year'] = substr($datum, 0, 2);
				$newdate['day'] = substr($datum, 4, 2);
			}
		} elseif (preg_match('/^[0-9]{8}$/', $datum)) { // 25031976
			$newdate['day'] = substr($datum, 0, 2);
			$newdate['month'] = substr($datum, 2, 2);
			$newdate['year'] = substr($datum, 4, 4);
		} elseif (preg_match('/^([0-9]{1,4})[^0-9][ ]*([0-9]{1,2})[^0-9][ ]*([0-9]{1,5})/', $datum, $treffer)) {
			// number 
			if (strlen($treffer[3]) > 4) return false; // error, should be a re-entry
			if (!(empty($newdate['month_a'])) && ($newdate['month_a'] != $treffer[2])) {
				$treffer['bak'] = $treffer[2];
				$treffer[2] = $treffer[1];
				$treffer[1] = $treffer['bak'];
				unset($treffer['bak']);
			}
			$newdate['month'] = $treffer[2];
			if ($treffer[1] > 31) {
				$newdate['year'] = $treffer[1];
				$newdate['day'] = $treffer[3];
			} else {
				$newdate['year'] = $treffer[3];
				$newdate['day'] = $treffer[1];
			}
		} elseif (preg_match('/^([0-9]{1,4})$/', $datum)) {
			$newdate['year'] = $datum;
			while (strlen($newdate['year']) < 4)
				$newdate['year'] = '0'.$newdate['year'];
			$newdate['month'] = '00';
			$newdate['day'] = '00';
		} elseif (preg_match('/^([0-9]{1,4})[^0-9][ ]*([0-9]{1,4})$/', $datum, $treffer)) {
			$newdate['day'] = '00';
			if ($treffer[1] > 31) {
				$newdate['year'] = $treffer[1];
				$newdate['month'] = $treffer[2];
			} else {
				$newdate['month'] = $treffer[1];
				$newdate['year'] = $treffer[2];
			}
		} else {
			$datum = str_replace(' ', '', $datum);
		}
		if (!empty($newdate)) {
			if (strlen($newdate['month']) == 1) $newdate['month'] = '0'.$newdate['month'];
			if (strlen($newdate['day']) == 1) $newdate['day'] = '0'.$newdate['day'];
			if (strlen($newdate['year']) == 3 || strlen($newdate['year']) == 1)
				$newdate['year'] = '0'.$newdate['year'];
			if ($newdate['year'] < 100 && strlen($newdate['year']) < 3)
				$newdate['year'] = ($newdate['year'] > 70) ? '19'.$newdate['year'] : '20'.$newdate['year'];
			return $newdate['year'].'-'.$newdate['month'].'-'.$newdate['day'];
			// $isodatum = sprintf ("%04d-%02d-%02d", $jahr, $monat, $tag);
		}
		return false;
	}	
}

/**
 * formats an integer into a readable byte representation
 *
 * @param int $byts
 * @param int $precision
 * @return string
 */
function zz_format_bytes($bytes, $precision = 1) { 
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
