<?php 
/*


	functions for valdidation fo fields

	all functions return false if requirements are not met
	else they will return the value

*/

$do_validation = true; // enables validation, just here for backward compatibility AFAIK

function checkmail($cemail) {
	$cemail = trim($cemail); // spaces never belong to Mailadress
	if (substr($cemail, 0, 1) == '<' && substr($cemail, strlen($cemail) -1) == '>') 
		$cemail = substr($cemail, 1, strlen($cemail)-2); // remove <>-brackets around address
	if (eregi("^[0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.([a-z]{2}|com|edu|gov|int|mil|net|org|shop|aero|biz|coop|info|museum|name|pro)$", $cemail, $check))
		return $cemail;
	return false;
}

function checkenum($enum_value, $field, $table) {
	$values = getenums($field, $table);
	if (in_array($enum_value, $values)) return $enum_value;
	else return false;
}

function getenums($colum, $table) {
	$sql = "SHOW COLUMNS FROM $table LIKE '$colum'";
	$result = mysql_query($sql);
	if (mysql_num_rows($result)) {
		$enums = mysql_fetch_row($result);
		$values = explode("','",preg_replace("/(enum|set)\('(.+?)'\)/","\\2",$enums[1]));
		return $values;
	}
}

function zz_check_url($url) {
	$url = str_replace("\\","/",$url);
	if (substr($url,0,1) == "/") return $url;
	elseif (substr($url,0,2) == "./") return $url;
	elseif (substr($url,0,3) == "../") return $url;
	else
		if (!is_url($url))  {
			$url = "http://" . $url;
			if (!is_url($url))	return false;
			else				return $url;
		} else return $url;

}

function is_url($url) {
	 if (!($parts = @parse_url($url)))
		  return false;
	 else {
	// 	echo "<pre>";
//	 	print_r ($parts);
//	 	echo "</pre>";
	 if ( @$parts['scheme'] != "http" && @$parts['scheme'] != "https" && @$parts['scheme'] != "ftp" && @$parts['scheme'] != "gopher" )
		  return false;
	 else if ( !@eregi( "^[0-9a-z]([-.]?[0-9a-z])*\.[a-z]{2,6}$", $parts['host'], $regs ) )
		  return false;
	 else if ( !@eregi( "^([0-9a-z-]|[\_])*$", $parts['user'], $regs ) )
		  return false;
	 else if ( !@eregi( "^([0-9a-z-]|[\_])*$", $parts['pass'], $regs ) )
		  return false;
	 elseif ($parts['path'] && !preg_match("/^[0-9a-z\/_\.@~\-,=]*$/i", $parts['path']))
		  return false;
	 else if ( !@eregi( "^[0-9a-z?&=#\,]*$", $parts['query'], $regs ) )
		  return false;
	 }
	 return true;
}


function checkfornull($field, $table) {
	$sql = 'SHOW COLUMNS FROM '.$table.' LIKE "'.$field.'"';
	$result = mysql_query($sql);
	if ($result) {
		$line = mysql_fetch_array($result);
		if ($line['Null'] == 'YES') return true;
		else return false;
	}
}

?>