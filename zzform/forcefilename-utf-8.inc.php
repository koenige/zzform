<?php
// ---------------------------------------------------------
// function forceFilename($str, $spaceChar)
//
// convert $str to a UNIX/Windows-conform filename
// a char for $spaceChar will replace the default whitespace '_'
// note when using '.' internet exploer adds automatically "[1]"
// for e.g. "This[1].is.a.filename.ext" in the save as dialog.
// ---------------------------------------------------------

function forceFilename($str, $spaceChar = '-') {
	mb_internal_encoding("UTF-8");

	$str = trim($str);

	$_str = '';
	$i_max = mb_strlen($str);
	for ($i = 0; $i < mb_strlen($str); $i++) {
		$ch = mb_substr($str, $i, 1);
		switch ($ch) {
		case 'Ä':case 'Æ':
			$_str .= 'AE'; break;	 
		
		case 'ä':case 'æ':
			$_str .= 'ae'; break;
		
		case 'à':case 'á':	case 'â':case 'ã':	case 'å':
		case 'ā': case 'â':
			$_str .= 'a'; break;	 
		case 'À': case 'Á':	case 'Â':case 'Ã':	case 'Å':
		case 'Ā': case 'Â':
		 	$_str .= 'A'; break;	 
		
		case 'Ç':case 'ç':
			$_str .= 'c'; break;
 
		case 'è':case 'é':	case 'ê':case 'ë': case 'ē':
			$_str .= 'e'; break;	 
		
		case 'È':case 'É':	case 'Ê':case 'Ë': case 'Ē':
			$_str .= 'E'; break;	 
		
		case 'Ì':case 'Í':	case 'Î':case 'Ï':
		case 'Ī':
			$_str .= 'I'; break;	 
		case 'ì':case 'í':	case 'î':case 'ï':
		case 'ī':
			$_str .= 'i'; break;	 
		
		case 'Ñ':case 'ñ':
			$_str .= 'n'; break;
		
		case 'Ö': 
			$_str .= 'OE'; break;
		
		case 'ö':
			$_str .= 'oe'; break;
		
		case 'Ò':case 'Ó':	case 'Ô':case 'Õ':
			$_str .= 'O'; break;	 
		case 'ò':case 'ó':	case 'ô':case 'õ':
			$_str .= 'o'; break;	 
		
		case 'ß':
			$_str .= 'ss'; break;
		
		case 'Ù':case 'Ú':	case 'Û':
		case 'Ū':
			$_str .= 'U'; break;	 
		case 'ù':case 'ú':	case 'û':
		case 'ū':
			$_str .= 'u'; break;	 
		
		case 'Ü':
			$_str .= 'UE'; break;
			
		case 'ü':
			$_str .= 'ue'; break;
		
		case 'Ý':
				$_str .= 'Y'; break;
			
		case 'ý':case 'ÿ':
			$_str .= 'y'; break;
		
		case 'Ð':
			$_str .= 'D'; break;
		
		case ' ':	$_str .= $spaceChar; break;

		case '/':case '\'':case '-':case ':':
			$_str .= '-'; break;
		
		default : if (preg_match('/[A-Za-z0-9\(\)\.]/', $ch)) {	$_str .= $ch;	} break;
		}
	}	 
	
	$_str = str_replace("{$spaceChar}{$spaceChar}", "{$spaceChar}",	$_str);
	$_str = str_replace("{$spaceChar}-", '-',	$_str);
	$_str = str_replace("-{$spaceChar}", '-',	$_str);
 
	return	$_str;
}

?>