<?php
// ---------------------------------------------------------
// function forceFilename($str, $spaceChar)
//
// convert $str to a UNIX/Windows-conform filename
// a char for $spaceChar will replace the default whitespace '_'
// note when using '.' internet explorer adds automatically "[1]"
// for e.g. "This[1].is.a.filename.ext" in the save as dialog.
// ---------------------------------------------------------

function forceFilename($str, $spaceChar = '-', $replacements = []) {
	global $zz_setting;
	static $characters;
	if (!$characters) {
		$data = file($zz_setting['core'].'/transliteration-characters.tsv');
		foreach ($data as $line) {
			if (!trim($line)) continue;
			if (substr($line, 0, 1) === '#') continue;
			$line = explode("\t", $line);
			$characters[trim($line[0])] = trim($line[1]);
		}
	}
	$str = wrap_convert_string($str);
	$str = trim($str);

	// get rid of html entities
	$str = html_entity_decode($str);
	if (strstr($str, '&#')) {
		if (strstr($str, '&#x')) {
			$str = preg_replace('~&#x([0-9a-f]+);~i', '', $str);
		}
		$str = preg_replace('~&#([0-9]+);~', '', $str);
	}

	$_str = '';
	$i_max = mb_strlen($str);
	for ($i = 0; $i < $i_max; $i++) {
		$ch = mb_substr($str, $i, 1);
		if (in_array($ch, array_keys($replacements))) {
			$_str .= $replacements[$ch];
			continue;
		}
		switch ($ch) {
		case array_key_exists($ch, $characters):
			$_str .= $characters[$ch]; break;
		case ' ':
			$_str .= $spaceChar; break;
		default:
			if (preg_match('/[A-Za-z0-9]/u', $ch)) { $_str .= $ch; }
			break;
		}
	}	 

	$_str = str_replace("{$spaceChar}{$spaceChar}", "{$spaceChar}",	$_str);
	$_str = str_replace("{$spaceChar}-", '-',	$_str);
	$_str = str_replace("-{$spaceChar}", '-',	$_str);
	if (substr($_str, -1) === $spaceChar) {
		$_str = substr($_str, 0, -1);
	}

	return $_str;
}
