<?php
// ---------------------------------------------------------
// function forceFilename($str, $spaceChar)
//
// convert $str to a UNIX/Windows-conform filename
// a char for $spaceChar will replace the default whitespace '_'
// note when using '.' internet explorer adds automatically "[1]"
// for e.g. "This[1].is.a.filename.ext" in the save as dialog.
// ---------------------------------------------------------

function forceFilename($str, $spaceChar = '-', $replacements = array()) {
	mb_internal_encoding("UTF-8");

	// get rid of html entities
	$str = html_entity_decode($str);
	$str = preg_replace('~&#x([0-9a-f]+);~ei', '', $str);
	$str = preg_replace('~&#([0-9]+);~e', '', $str);
	$str = trim($str);

	$_str = '';
	$i_max = mb_strlen($str);
	for ($i = 0; $i < mb_strlen($str); $i++) {
		$ch = mb_substr($str, $i, 1);
		if (in_array($ch, array_keys($replacements))) {
			$_str .= $replacements[$ch];
			continue;
		}
		switch ($ch) {
		case 'À': case 'Á': case 'Â': case 'Ã': case 'Å':
		case 'Ā': case 'Â': case 'Ą': case 'Ă':
		 	$_str .= 'A'; break;	 
		case 'à': case 'á': case 'â': case 'ã': case 'å':
		case 'ā': case 'â': case 'ą': case 'ă':
			$_str .= 'a'; break;	 

		case 'Ä': case 'Æ':
			$_str .= 'AE'; break;	 
		case 'ä': case 'æ':
			$_str .= 'ae'; break;

		case 'Ç': case 'Ć': case 'Č': case 'Ć':
			$_str .= 'C'; break;
		case 'ç': case 'ç': case 'č': case 'ć':
			$_str .= 'c'; break;

		case 'Ð': case 'Ď':
			$_str .= 'D'; break;
		case 'đ': case 'ď':
			$_str .= 'd'; break;

		case 'È': case 'É': case 'Ê': case 'Ë': case 'Ē':
		case 'Ę': case 'Ě': 
			$_str .= 'E'; break;	 
		case 'è': case 'é': case 'ê': case 'ë': case 'ē':
		case 'ę': case 'ě':
			$_str .= 'e'; break;	 

 		case 'Ğ':
 			$_str .= 'G'; break;
 		case 'ğ':
 			$_str .= 'g'; break;

		case 'Ì': case 'Í': case 'Î': case 'Ï': case 'Ī': case 'İ':
			$_str .= 'I'; break;	 
		case 'ì': case 'í': case 'î': case 'ï': case 'ī': case 'ı':
			$_str .= 'i'; break;	 

		case 'Ł': case 'Ľ': case 'Ĺ':
			$_str .= 'L'; break;
		case 'ł': case 'ľ': case 'ĺ':
			$_str .= 'l'; break;

		case 'Ñ': case 'Ň': case 'Ń':
			$_str .= 'N'; break;
		case 'ñ': case 'ň': case 'ń':
			$_str .= 'n'; break;

		case 'Ò': case 'Ó': case 'Ô': case 'Õ': case 'Ő': case 'Ø':
			$_str .= 'O'; break;	 
		case 'ò': case 'ó': case 'ô': case 'õ': case 'ő': case 'ø':
			$_str .= 'o'; break;	 

		case 'Ö': case 'Œ':
			$_str .= 'OE'; break;
		case 'ö': case 'œ':
			$_str .= 'oe'; break;

		case 'Ŕ': case 'Ř': 
			$_str .= 'R'; break;
		case 'ŕ': case 'ř': 
			$_str .= 'r'; break;

		case 'ß':
			$_str .= 'ss'; break;

		case 'Ś': case 'Š': case 'Ş':
			$_str .= 'S'; break;
		case 'ś': case 'š': case 'ş':
			$_str .= 's'; break;

		case 'Ť': case 'Ţ':
			$_str .= 'T'; break;
		case 'ť': case 'ţ':
			$_str .= 't'; break;

		case 'Ù': case 'Ú': case 'Û': case 'Ū': case 'Ű': case 'Ů':
			$_str .= 'U'; break;	 
		case 'ù': case 'ú': case 'û': case 'ū': case 'ű': case 'ů':
			$_str .= 'u'; break;	 

		case 'Ü':
			$_str .= 'UE'; break;
		case 'ü':
			$_str .= 'ue'; break;

		case 'Ý': case 'Ÿ':
			$_str .= 'Y'; break;
		case 'ý': case 'ÿ':
			$_str .= 'y'; break;

		case 'Ź': case 'Ž': case 'Ż':
			$_str .= 'Z'; break;
		case 'ź': case 'ž': case 'ż':
			$_str .= 'z'; break;

		case ' ':	$_str .= $spaceChar; break;

		case '/': case '\'': case '-': case ':':
			$_str .= '-'; break;

		default:
			if (preg_match('/[A-Za-z0-9\(\)]/', $ch)) {	$_str .= $ch;	} break;
		}
	}	 

	$_str = str_replace("{$spaceChar}{$spaceChar}", "{$spaceChar}",	$_str);
	$_str = str_replace("{$spaceChar}-", '-',	$_str);
	$_str = str_replace("-{$spaceChar}", '-',	$_str);

	return $_str;
}

?>