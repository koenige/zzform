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
		case 'Á': case 'Â': case 'Ã': case '¡':
			$_str .= 'A'; break;
		case 'á': case 'â': case 'ã': case '±':
			$_str .= 'a'; break;

		case 'Ä':
			$_str .= 'Ae'; break;
		case 'ä':
			$_str .= 'ae'; break;
 
		case 'Ç': case 'Æ': case 'È': case 'Æ':
			$_str .= 'C'; break;
		case 'ç': case 'ç': case 'è': case 'æ':
			$_str .= 'c'; break;

		case 'Ð': case 'Ï':
			$_str .= 'D'; break;
		case 'ð': case 'ï':
			$_str .= 'd'; break;
 
		case 'É': case 'Ë': case 'Ê': case 'Ì': 
			$_str .= 'E'; break;
		case 'é': case 'ë': case 'ê': case 'ì': 
			$_str .= 'e'; break;
 
		case 'Í': case 'Î':
			$_str .= 'I'; break;
		case 'í': case 'î':
			$_str .= 'i'; break;
 
		case '£': case '¥': case 'Å':
			$_str .= 'L'; break;
		case '³': case 'µ': case 'å':
			$_str .= 'l'; break;

		case 'Ò': case 'Ñ':
			$_str .= 'N'; break;
		case 'ò': case 'ñ':
			$_str .= 'n'; break;
 
		case 'Ó': case 'Ô': case 'Õ':
			$_str .= 'O'; break;
		case 'ó': case 'ô': case 'õ':
			$_str .= 'o'; break;

		case 'Ö': 
			$_str .= 'Oe'; break;
		case 'ö':
			$_str .= 'oe'; break;
 
		case 'À': case 'Ø': 
			$_str .= 'R'; break;
		case 'à': case 'ø': 
			$_str .= 'r'; break;
 
		case 'ß':
			$_str .= 'ss'; break;
 
		case '¦': case '©': case 'ª':
			$_str .= 'S'; break;
		case '¶': case '¹': case 'º':
			$_str .= 's'; break;
 
		case '«': case 'Þ':
			$_str .= 'T'; break;
		case '»': case 'þ':
			$_str .= 't'; break;
 
		case 'Ú': case 'Û': case 'Ù':
			$_str .= 'U'; break;
		case 'ú': case 'û': case 'ù':
			$_str .= 'u'; break;
 
		case 'Ü':
			$_str .= 'Ue'; break;
		case 'ü':
			$_str .= 'ue'; break;
 
		case 'Ý':
			$_str .= 'Y'; break;
		case 'ý':
			$_str .= 'y'; break;

		case '¬': case '®': case '¯':
			$_str .= 'Z'; break;
		case '¼': case '¾': case '¿':
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