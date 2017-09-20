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
		case '¤':
			$_str .= 'EUR'; break;

		case 'À': case 'Á': case 'Â': case 'Ã': case 'Å':
			$_str .= 'A'; break;
		case 'à': case 'á': case 'â': case 'ã': case 'å':
			$_str .= 'a'; break;

		case 'Ä': case 'Æ':
			$_str .= 'Ae'; break;
		case 'ä': case 'æ':
			$_str .= 'ae'; break;

		case 'Ç':
			$_str .= 'C'; break;
		case 'ç':
			$_str .= 'c'; break;

		case 'Ð':
			$_str .= 'D'; break;
		case 'ð':
			$_str .= 'd'; break;

		case 'È': case 'É': case 'Ê': case 'Ë':
			$_str .= 'E'; break;
		case 'è': case 'é': case 'ê': case 'ë':
			$_str .= 'e'; break;
 
		case 'Ì': case 'Í': case 'Î': case 'Ï':
			$_str .= 'I'; break;
		case 'ì': case 'í': case 'î': case 'ï':
			$_str .= 'i'; break;

		case 'Ñ':
			$_str .= 'N'; break;
		case 'ñ':
			$_str .= 'n'; break;

		case 'Ò': case 'Ó': case 'Ô': case 'Õ': case 'Ø':
			$_str .= 'O'; break;
		case 'ò': case 'ó': case 'ô': case 'õ': case 'ø':
			$_str .= 'o'; break;

		case 'Ö': case '¼': 
			$_str .= 'Oe'; break;
		case 'ö': case '½': 
			$_str .= 'oe'; break;

		case 'ß':
			$_str .= 'ss'; break;

		case '¦':
			$_str .= 'S'; break;
		case '¨':
			$_str .= 's'; break;

		case 'Ù': case 'Ú': case 'Û':
			$_str .= 'U'; break;
		case 'ù': case 'ú': case 'û':
			$_str .= 'u'; break;

		case 'Ü':
			$_str .= 'Ue'; break;
		case 'ü':
			$_str .= 'ue'; break;

		case 'Ý': case '¾':
			$_str .= 'Y'; break;
		case 'ý': case 'ÿ':
			$_str .= 'y'; break;
 
		case '´':
			$_str .= 'Z'; break;
		case '¸':
			$_str .= 'z'; break;

		case ' ':
			$_str .= $spaceChar; break;

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