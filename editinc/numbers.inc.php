<?php

// Numbers
// Date, Currency

function datum_de($datum) {
	if (isset($datum)) {
		$datum_arr = explode("-", $datum);
		$datum = '';
		if ($datum_arr[2] != "00") {
			$datum .= $datum_arr[2].".";
		}
		if ($datum_arr[1] != "00") {
			$datum .= $datum_arr[1].".";
		}
		if (substr($datum_arr[0], 0, 1) == "0") {
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
			while (strlen($datum_test[2]) < 4) {
				// jahr auf 4 stellen bringen
				$datum_test[2] = "0".$datum_test[2];
			}
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
			while (strlen($datum) < 4) {
				// jahr auf 4 stellen bringen
				$datum = "0".$datum;
			}
			$datum .= "-00-00";
			if (strlen($datum) == 10) { return $datum; }
		}
	}
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