<?php

// Numbers
// Date, Currency

function datum_de($datum) {
	if (isset($datum)) {
		if (!ereg("^[0-9-]+$",$datum)) return $datum; #wenn kein richtiges datum, einfach datum zurückgeben.
		$datum_arr = explode("-", $datum);
		$datum = '';
		if ($datum_arr[2] != "00") {
			$datum .= $datum_arr[2].".";
		}
		if ($datum_arr[1] != "00") {
			$datum .= $datum_arr[1].".";
		}
		if (substr($datum_arr[0], 0, 1) == "0" AND substr($datum_arr[0],0,2) != "00") {
			$datum .= substr($datum_arr[0], 1, 4);
		} else {
			$datum .= $datum_arr[0];
		}
		return $datum;
	}
}

function jahr($datum) {
	$datum_arr = explode ("-", $datum);
	$jahr = $datum_arr[0];
	if (substr($jahr, 0, 1) == 0) $jahr = substr($jahr, 1, 3);
	return $jahr;
}

function datum_int($datum) {
	if (isset($datum)) {
		$datum_test = explode("-", $datum);
		if (isset($datum_test[2])) {
			// datum ist schon im internationalen Format.
			return $datum;
		}
		unset($datum_test);
		$datum_test = explode(".", $datum);
		if (isset($datum_test[2])) {
			// datum in der form 00.00.0000
			if (strlen($datum_test[2]) < 4 AND isset($datum_test[2])) {		#wenn weniger als vier stellen
				if (!ereg("^[0-9]+$",$datum_test[2])) return false;			#zunächst testen, ob nur nummern.
				elseif (strlen($datum_test[2]) < 3) {																												#wenn ein/zweistellige eingabe
					if     (strlen($datum_test[2]) == 2 AND $datum_test[2] > 70) $datum_test[2] = "19".$datum_test[2];											#wenn über 70, dann 19xx
					elseif (strlen($datum_test[2]) == 2 AND $datum_test[2] > 9) $datum_test[2] = "20".$datum_test[2];											#wenn über 9 , dann 20xx
					elseif (strlen($datum_test[2]) <= 2 AND $datum_test[2] < 10) { settype($datum_test[2],"integer"); $datum_test[2] = "200".$datum_test[2];}	#sonst              200x, vorher in integer umwandeln
				}
				else $datum_test[2] = "0".$datum_test[2];
			}
			elseif (strlen($datum_test[2] == 4)) if (!ereg("^[0-9]+$",$datum_test[2])) return false;	#wenn vierstellig
			#else return false;					#wenn gröser 4
			if (strlen($datum_test[1]) < 2 ) {
				$datum_test[1] = "0".$datum_test[1];
			}
			if (strlen($datum_test[0]) < 2 ) {
				$datum_test[0] = "0".$datum_test[0];
			}
			$datum = $datum_test[2]."-".$datum_test[1]."-".$datum_test[0];
			if (strlen($datum) == 10) { return $datum; }
		} elseif (isset($datum_test[1])) {
			// aha, nur monat/jahr
			while (strlen($datum_test[1]) < 4) {
				// jahr auf 4 stellen bringen
				$datum_test[1] = "0".$datum_test[1];
			}
			if (strlen($datum_test[0]) < 2 ) {
				$datum_test[0] = "0".$datum_test[0];
			}
			$datum = $datum_test[1]."-".$datum_test[0]."-00";
			if (strlen($datum) == 10) { return $datum; }
		} elseif ($datum != '') {
			if (!ereg("^[0-9]+$",$datum)) return false;
			while (strlen($datum) < 4) {
				// jahr auf 4 stellen bringen
				$datum = "0".$datum;
			}
			$datum .= "-00-00";
			if (strlen($datum) == 10) { return $datum; }
		}
	}	
}

function validate_time($time) {
	$time = str_replace('.',':',$time);
	if (strlen($time)>8) return false;
	if (ereg("^[0-9]+$",$time))
	{
		if (strlen($time)>4) return false;
		elseif (strlen($time)==1)    {$time = $time . ":00:00";}
		elseif (strlen($time)==2){
			if ($time<25) $time = $time . ":00:00";
			else return false;
		}
		else {
			$tmin = substr($time,-2);
			$th   = substr($time,-4,-2);
			if ($tmin > 60) return false;
			if ($th > 24)   return false;
			$time = $th . ":" . $tmin . ":00";
		}
	}
	elseif (ereg("^[0-9:]+$",$time))
	{
		$timex = explode(":",$time);
		if (count($timex) > 3) return false;
		elseif ($timex[0] > 24 OR $timex[1] > 59) return false;
		elseif (isset($timex[2])) if($timex[2] > 60)  return false;
		elseif (isset($timex[0]) AND $timex[0] != '') {
			if ($timex[1] == '' OR !isset($timex[1])) $timex[1] = "00";
			if ($timex[2] == '' OR !isset($timex[2])) $timex[2] = "00";
			$time = $timex[0] . ":" . $timex[1] . ":" . $timex[2];
		}
		else return false;
	}
	else $time = false;
	return $time;
}

// Currency

function waehrung($int, $unit) {
	$int = number_format($int, 2, ',', '.');
	if (!strstr($int, ',')) $int .= ',&#8211;';
	$int = str_replace (',00', ',&#8211;', $int);
	if ($unit) $int .= '&nbsp;'.$unit;
	return $int;
}

?>