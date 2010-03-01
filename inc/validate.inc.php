<?php 

// zzform
// (c) Gustaf Mossakowski, <gustaf@koenige.org>, 2005-2010
// Functions for validation of user input

/*
	all functions return false if requirements are not met
	else they will return the value that was checked against
*/

function zz_check_mail($e_mail) {
	// multiple e-mail adresses might be separated with a ','
/* TODO
	if (strstr(',', $e_mail)) {
		$e_mails = explode(',', $e_mail);
		foreach ($e_mails as $mail) {
			$mail = zz_check_mail($mail);
			if (!$mail)
		}
	}
*/
	$e_mail = trim($e_mail); // spaces never belong to Mailadress
//	$e_mail = str_replace(';', ',', $e_mail); // sometimes people separate multiple 
//		// e-mails with ; instead of , - allow this but replace with ','
	if (substr($e_mail, 0, 1) == '<' && substr($e_mail, -1) == '>') 
		$e_mail = substr($e_mail, 1, -1); // remove <>-brackets around address
	if (preg_match('/^[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i', $e_mail, $check))
		return $e_mail;
	return false;
}

function zz_check_enumset($enum_value, $field, $table) {
	$values = zz_get_enumset($field['field_name'], $table);
	if ($values) {
		// it's in the table definition, go for it!
		if (in_array($enum_value, $values)) return $enum_value;
	} else {
		// look like it's neither an ENUM nor a SET type of field
		// so check the $zz['fields']-definition
		if (isset($field['enum'])) {
			if (in_array($enum_value, $field['enum'])) return $enum_value;
		} elseif (isset($field['set'])) {
			if (in_array($enum_value, $field['set'])) return $enum_value;
		}
	}
	// Value is incorrect
	return false;
}

function zz_get_enumset($colum, $table) {
	global $zz_error;
	$values = array();
	if (substr($table, 0, 1) != '`' AND substr($table, -1) != '`') {
		$table = '`'.str_replace('.', '`.`', $table).'`';
	}
	$sql = 'SHOW COLUMNS FROM '.$table.' LIKE "'.$colum.'"';
	$result = mysql_query($sql);
	if (mysql_num_rows($result)) {
		$column_definition = mysql_fetch_assoc($result);
		if (substr($column_definition['Type'], 0, 5) == "set('" 
			AND substr($column_definition['Type'], -2) == "')") {
			// column of type SET
			$values = substr($column_definition['Type'], 5, -2);
			$values = explode("','", $values);
		} elseif (substr($column_definition['Type'], 0, 6) == "enum('" 
			AND substr($column_definition['Type'], -2) == "')") {
			// column of type ENUM
			$values = substr($column_definition['Type'], 6, -2);
			$values = explode("','", $values);
		} else {
			// different column
			$values = false;
		}
	} else {
		$zz_error[] = array(
			'msg_dev' => 'Admin warning: column name given in table definition might not exist.',
			'query' => $sql,
			'mysql' => mysql_error(),
			'level' => E_USER_WARNING
		);
		// todo: check table definition, whether column exists or not.
	}
	return $values;
}

/** checks whether an input is a URL
 * 
 * This function is also part of zzbrick, there it is called brick_check_url()
 * @param $url(string)	URL to be tested, may be a relative URL as well (starting with ../, /)
 *		might add http:// in front of it if this generates a valid URL
 * @return string url if correct, or false
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_check_url($url) {
	$url = trim($url); // remove invalid white space at the beginning and end of URL
	$url = str_replace("\\", "/", $url); // not sure: is \ a legal part of a URL?
	if (substr($url, 0, 1) == "/")
		if (zz_is_url('http://example.com'.$url)) return $url;
		else return false;
	elseif (substr($url, 0, 2) == "./") 
		if (zz_is_url('http://example.com'.substr($url,1))) return $url;
		else return false;
	elseif (substr($url, 0, 3) == "../") 
		if (zz_is_url('http://example.com'.substr($url,2))) return $url;
		else return false;
	else
		if (!zz_is_url($url))  {
			$url = "http://" . $url;
			if (!zz_is_url($url))	return false;
			else				return $url;
		} else return $url;

}

/** checks whether an input is a valid URL
 * 
 * This function is also part of zzbrick, there it is called brick_is_url()
 * @param $url(string)	URL to be tested, only absolute URLs
 * @return string url if correct, or false
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_is_url($url) {
	// todo: give back which part of URL is incorrect
	$possible_schemes = array('http', 'https', 'ftp', 'gopher');
	if (!$url) return false;
	$parts = parse_url($url);
	if (!$parts) return false;
	if (empty($parts['scheme']) OR !in_array($parts['scheme'], $possible_schemes))
		return false;
	elseif (empty($parts['host']) 
		OR (!preg_match("/^[0-9a-z]([-.]?[0-9a-z])*\.[a-z]{2,6}$/i", $parts['host'], $regs)
		AND !preg_match('/[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}/', $parts['host'])))
		return false;
	elseif (!empty($parts['user']) 
		AND !preg_match("/^([0-9a-z-]|[\_])*$/i", $parts['user'], $regs))
		return false;
	elseif (!empty($parts['pass']) 
		AND !preg_match("/^([0-9a-z-]|[\_])*$/i", $parts['pass'], $regs))
		return false;
	elseif (!empty($parts['path']) 
		AND !preg_match("/^[0-9a-z\/_\.@~\-,=%]*$/i", $parts['path']))
		return false;
	elseif (!empty($parts['query'])
		AND !preg_match("/^[A-Za-z0-9\-\._~!$&'\(\)\*+,;=:@?\/%]*$/i", $parts['query'], $regs))
		// not 100% correct: % must only appear in front of HEXDIG, e. g. %2F
		// here it may appear in front of any other sign
		// see 
		// http://www.ietf.org/rfc/rfc3986.txt and 
		// http://www.ietf.org/rfc/rfc2234.txt
		return false;
	return true;
}

function zz_check_for_null($field, $table) {
	if (substr($table, 0, 1) != '`' AND substr($table, -1) != '`') {
		$table = '`'.str_replace('.', '`.`', $table).'`';
	}
	$sql = 'SHOW COLUMNS FROM '.$table.' LIKE "'.$field.'"';
	$result = mysql_query($sql);
	if ($result) {
		$line = mysql_fetch_array($result);
		if ($line['Null'] == 'YES') return true;
		else return false;
	}
}

?>