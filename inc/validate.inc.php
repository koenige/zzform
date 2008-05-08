<?php 
/*


	functions for valdidation fo fields

	all functions return false if requirements are not met
	else they will return the value

*/

function checkmail($cemail) {
	$cemail = trim($cemail); // spaces never belong to Mailadress
	if (substr($cemail, 0, 1) == '<' && substr($cemail, -1) == '>') 
		$cemail = substr($cemail, 1, -1); // remove <>-brackets around address
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
	global $zz_error;
	$values = array();
	$sql = "SHOW COLUMNS FROM $table LIKE '$colum'";
	$result = mysql_query($sql);
	if (mysql_num_rows($result)) {
		$enums = mysql_fetch_row($result);
		$values = explode("','",preg_replace("/(enum|set)\('(.+?)'\)/","\\2",$enums[1]));
	} else {
		$zz_error[]['msg'] = 'Admin warning: column name given in table definition might not exist.';
		// todo: check table definition, whether column exists or not.
	}
	return $values;
}

function zz_check_url($url) {
	$url = trim($url); // remove invalid white space at the beginning and end of URL
	$url = str_replace("\\", "/", $url); // not sure: is \ a legal part of a URL?
	if (substr($url, 0, 1) == "/")
		if (is_url('http://example.com'.$url)) return $url;
		else return false;
	elseif (substr($url, 0, 2) == "./") 
		if (is_url('http://example.com'.substr($url,1))) return $url;
		else return false;
	elseif (substr($url, 0, 3) == "../") 
		if (is_url('http://example.com'.substr($url,2))) return $url;
		else return false;
	else
		if (!is_url($url))  {
			$url = "http://" . $url;
			if (!is_url($url))	return false;
			else				return $url;
		} else return $url;

}

function is_url($url) {
	// todo: give back which part of URL is incorrect
	$possible_schemes = array('http', 'https', 'ftp', 'gopher');
	if (!$url) return false;
	$parts = parse_url($url);
	if (!$parts) return false;
	if (empty($parts['scheme']) OR !in_array($parts['scheme'], $possible_schemes))
		return false;
	elseif (empty($parts['host']) 
		OR (!eregi("^[0-9a-z]([-.]?[0-9a-z])*\.[a-z]{2,6}$", $parts['host'], $regs)
		AND !preg_match('/[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}/', $parts['host'])))
		return false;
	elseif (!empty($parts['user']) 
		AND !eregi( "^([0-9a-z-]|[\_])*$", $parts['user'], $regs))
		return false;
	elseif (!empty($parts['pass']) 
		AND !eregi( "^([0-9a-z-]|[\_])*$", $parts['pass'], $regs))
		return false;
	elseif (!empty($parts['path']) 
		AND !preg_match("/^[0-9a-z\/_\.@~\-,=]*$/i", $parts['path']))
		return false;
	elseif (!empty($parts['query'])
		AND !eregi("^[A-Za-z0-9\-\._~!$&'\(\)\*+,;=:@?\/%]*$", $parts['query'], $regs))
		// not 100% correct: % must only appear in front of HEXDIG, e. g. %2F
		// here it may appear in front of any other sign
		// see 
		// http://www.ietf.org/rfc/rfc3986.txt and 
		// http://www.ietf.org/rfc/rfc2234.txt
		return false;
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