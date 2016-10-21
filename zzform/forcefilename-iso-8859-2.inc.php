<?php
// ---------------------------------------------------------
// function forceFilename($str, $spaceChar)
//
//  iso 8859 2 version
//
// convert $str to a UNIX/Windows-conform filename
// a char for $spaceChar will replace the default whitespace '_'
// note when using '.' internet explorer adds automatically "[1]"
// for e.g. "This[1].is.a.filename.ext" in the save as dialog.
// ---------------------------------------------------------

function forceFilename($str, $spaceChar = '-', $replacements = array()) {

	// get rid of html entities
	$str = html_entity_decode($str);
	$str = preg_replace('~&#x([0-9a-f]+);~i', '', $str);
	$str = preg_replace('~&#([0-9]+);~', '', $str);
	$str = trim($str);

	$_str = '';
	$i_max = strlen($str);
	for ($i = 0; $i < strlen($str); $i++) {
		$ch = $str[$i];
		if (in_array($ch, array_keys($replacements))) {
			$_str .= $replacements[$ch];
			continue;
		}
		switch ($ch) {
		case '�': case '�': case '�': case '�':
			$_str .= 'A'; break;
		case '�': case '�': case '�': case '�':
			$_str .= 'a'; break;

		case '�':
			$_str .= 'Ae'; break;
		case '�':
			$_str .= 'ae'; break;
 
		case '�': case '�': case '�': case '�':
			$_str .= 'C'; break;
		case '�': case '�': case '�': case '�':
			$_str .= 'c'; break;

		case '�': case '�':
			$_str .= 'D'; break;
		case '�': case '�':
			$_str .= 'd'; break;
 
		case '�': case '�': case '�': case '�': 
			$_str .= 'E'; break;
		case '�': case '�': case '�': case '�': 
			$_str .= 'e'; break;
 
		case '�': case '�':
			$_str .= 'I'; break;
		case '�': case '�':
			$_str .= 'i'; break;
 
		case '�': case '�': case '�':
			$_str .= 'L'; break;
		case '�': case '�': case '�':
			$_str .= 'l'; break;

		case '�': case '�':
			$_str .= 'N'; break;
		case '�': case '�':
			$_str .= 'n'; break;
 
		case '�': case '�': case '�':
			$_str .= 'O'; break;
		case '�': case '�': case '�':
			$_str .= 'o'; break;

		case '�': 
			$_str .= 'Oe'; break;
		case '�':
			$_str .= 'oe'; break;
 
		case '�': case '�': 
			$_str .= 'R'; break;
		case '�': case '�': 
			$_str .= 'r'; break;
 
		case '�':
			$_str .= 'ss'; break;
 
		case '�': case '�': case '�':
			$_str .= 'S'; break;
		case '�': case '�': case '�':
			$_str .= 's'; break;
 
		case '�': case '�':
			$_str .= 'T'; break;
		case '�': case '�':
			$_str .= 't'; break;
 
		case '�': case '�': case '�':
			$_str .= 'U'; break;
		case '�': case '�': case '�':
			$_str .= 'u'; break;
 
		case '�':
			$_str .= 'Ue'; break;
		case '�':
			$_str .= 'ue'; break;
 
		case '�':
			$_str .= 'Y'; break;
		case '�':
			$_str .= 'y'; break;

		case '�': case '�': case '�':
			$_str .= 'Z'; break;
		case '�': case '�': case '�':
			$_str .= 'z'; break;

		case ' ': $_str .= $spaceChar; break;

		case '/': case '\'': case '-': case ':':
			$_str .= '-'; break;

		default:
			if (preg_match('/[A-Za-z0-9]/', $ch)) { $_str .= $ch; } break;
		}
	}
	
	$_str = str_replace("{$spaceChar}{$spaceChar}", "{$spaceChar}", $_str);
	$_str = str_replace("{$spaceChar}-", '-', $_str);
	$_str = str_replace("-{$spaceChar}", '-', $_str);
 
	return $_str;
}

?>