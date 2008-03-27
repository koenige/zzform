<?php

/*

zzform: Number/Date Functions
(c) Gustaf Mossakowski <gustaf@koenige.org>, 2005-2006

*/

/** converts given iso date to d.m.Y or returns date as is if incomplete
 * 
 * @param $datum(string) date to be converted, international date or output of this function
 * @param $param(string) without-year: cuts year from date; short: returns short year
 * @return string formatted date
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function datum_de($datum, $param = false) {
	if (!$datum) return false;
	if (preg_match("/^([0-9]{4}-[0-9]{2}-[0-9]{2}) [0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/", $datum, $match)) {
		// DATETIME
		$datum = $match[1]; // ignore time, it's a date function
	} elseif (preg_match("/^([0-9]{4})([0-9]{2})([0-9]{2})[0-2][0-9][0-5][0-9][0-5][0-9]$/", $datum, $match)){
		// old MySQL TIMESTAMP
		$datum = $match[1].'-'.$match[2].'-'.$match[3]; // ignore time, it's a date function
	} elseif (!preg_match("/^[0-9-]+$/", $datum)) 
		return $datum; #wenn kein richtiges datum, einfach datum zurueckgeben.
	elseif (preg_match("/^[0-9]{1,4}$/", $datum)) 
		return $datum; #wenn nur ein bis vier ziffern, d. h. jahr, einfach jahr zurueckgeben
	$datum_arr = explode("-", $datum);
	$datum = '';
	if (isset($datum_arr[2]) && $datum_arr[2] != "00")
		$datum .= $datum_arr[2].".";
	if (isset($datum_arr[1]) && $datum_arr[1] != "00")
		$datum .= $datum_arr[1].".";
	if (substr($datum_arr[0], 0, 1) == "0" AND substr($datum_arr[0],0,2) != "00")
		$datum .= substr($datum_arr[0], 1, 4);
	else
		switch ($param) {
		case 'without-year':
			break;
		case 'short':
			$datum .= substr($datum_arr[0],2);
			break;
		default:
			$datum .= $datum_arr[0];
		}
	return $datum;
}

/** returns year of given iso date, removes trailing 0 if neccessary
 * 
 * @param $datum(string) date in international date format YYYY-MM-DD
 * @return string year
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function jahr($datum) {
	$datum_arr = explode ("-", $datum);
	$jahr = $datum_arr[0];
	if (substr($jahr, 0, 1) == 0) $jahr = substr($jahr, 1, 3);
	return $jahr;
}

/** converts user input date into international date string
 * 
 * @param $datum(string) date in several possible formats
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

/** converts user input time into HH:MM:SS or returns false if given time is illegal
 * 
 * @param $time(string) time in several possible formats
 * @return mixed false if input is illegal, time-string if input is correct
 */
function validate_time($time) {
	$time = str_replace('.',':',$time);
	if (strlen($time)>8) return false;
	if (ereg("^[0-9]+$",$time)) {
		if (strlen($time)>4) return false;
		elseif (strlen($time)==1)    {$time = $time . ":00:00";}
		elseif (strlen($time)==2)
			if ($time<25) $time = $time . ":00:00";
			else return false;
		else {
			$tmin = substr($time,-2);
			$th   = substr($time,-4,-2);
			if ($tmin > 60) return false;
			if ($th > 24)   return false;
			$time = $th . ":" . $tmin . ":00";
		}
	} elseif (ereg("^[0-9:]+$",$time)) {
		$timex = explode(":",$time);
		if (count($timex) > 3) return false;
		elseif ($timex[0] > 24 OR $timex[1] > 59) return false;
		elseif (isset($timex[2])) if($timex[2] > 60)  return false;
		elseif (isset($timex[0]) AND $timex[0] != '') {
			if ($timex[1] == '' OR !isset($timex[1])) $timex[1] = "00";
			if ($timex[2] == '' OR !isset($timex[2])) $timex[2] = "00";
			$time = $timex[0] . ":" . $timex[1] . ":" . $timex[2];
		} else return false;
	} else $time = false;
	return $time;
}

/** converts number into currency
 * 
 * @param $int(int) amount of money
 * @param $unit(string) currency unit
 * @return string formatted combination of amount and unit
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function waehrung($int, $unit) {
	if (!$int) return false;
	$int = number_format($int, 2, ',', '.');
	if (!strstr($int, ',')) $int .= ',00';
	//$int = str_replace (',00', ',&#8211;', $int);
	if ($unit) $int .= '&nbsp;'.$unit;
	return $int;
}

?>