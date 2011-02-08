<?php 

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2005-2010
// Functions for validation of user input

/*
	all functions return false if requirements are not met
	else they will return the value that was checked against
*/

/**
 * checks whether a given string is a valid e-mail address
 *
 * @param string $e_mail
 * @param string $type (optional): mail, mail+name
 * @return string $e_mail if correct, '' if not correct
 */
function zz_check_mail($e_mail, $type = 'mail') {
	// remove whitespace from address(es)
	$e_mail = trim($e_mail);

	if ($type == 'mail+name') {
		// bla@example.org
		// bla@example.org, blubb@example.org
		// bla@example.org,blubb@example.org
		// bla@example.org,<blubb@example.org>
		// Bla Blubb <bla@example.org>
		// Bla Blubb <bla@example.org>, Blobb blubb <blubb@example.org>
		// Bla Blubb <bla@example.org>, <blubb@example.org>
		// Bla Blubb <bla@example.org>, blubb@example.org
		// Bla, Dept. Blubb <bla@example.org>, blubb@example.org
		
		// treat , and ; alike
		$e_mail = str_replace(';', ',', $e_mail);
		
		// get individual addresses
		$mails = explode(',', $e_mail);
		foreach ($mails as $index => $mail) {
			if (!strstr($mail, '@')) {
				// last part must be e-mail address
				if (empty($mails[$index+1])) return false;
				$mails[$index+1] = $mail.','.$mails[$index+1];
				unset($mails[$index]);
			}
		}
		
		// check indivual addresses
		$correct_mails = array();
		foreach ($mails as $mail) {
			$mail = trim($mail);
			$parts = explode(' ', $mail);
			$this_name = '';
			$this_mail = '';
			foreach ($parts as $index => $part) {
				if ($index < count($parts)-1)
					$this_name .= ' '.$part;
				else {
					$this_mail = zz_check_mail_single($part);
					if (!$this_mail) return false;
				}
			}
			if (trim($this_name)) {
				$this_name = trim($this_name);
				if (substr($this_name, 0, 1) != '"' AND substr($this_name, -1) != '"')
					$this_name = '"'.$this_name.'"';
				$correct_mails[] = $this_name.' <'.$this_mail.'>';
			} else { 
				$correct_mails[] =  $this_mail;
			}
		}
		$e_mail = implode(', ', $correct_mails);
		return $e_mail;
	} else {
		// single e-mail-address
		$e_mail = zz_check_mail_single($e_mail);
		return $e_mail;
	}

	return false;
}

function zz_check_mail_single($e_mail) {
	// remove <>-brackets around address
	if (substr($e_mail, 0, 1) == '<' && substr($e_mail, -1) == '>') 
		$e_mail = substr($e_mail, 1, -1); 
	// check address
	$e_mail_pm = '/^[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i';
	if (preg_match($e_mail_pm, $e_mail, $check))
		return $e_mail;
	return false;
}

/**
 * checks whether a value is correct for enum/set
 *
 * @param string $enum_value
 * @param array $field field definition
 * @param string $db_table [db_name.table]
 * @return mixed string $enum_value if correct, bool false if not
 */
function zz_check_enumset($enum_value, $field, $db_table) {
	$values = zz_db_get_enumset($field['field_name'], $db_table);
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

/**
 * gets values for enum/set-fields from database
 *
 * @param string $column Name of column
 * @param string $db_table [db_name.table]
 * @return mixed array $values, bool false if no values
 */
function zz_db_get_enumset($colum, $db_table) {
	$values = array();
	$sql = 'SHOW COLUMNS FROM '.zz_db_table_backticks($db_table).' LIKE "'.$colum.'"';
	$column_definition = zz_db_fetch($sql, '', '', __FUNCTION__);
	if (!$column_definition) return false;
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
	return $values;
}

/**
 * checks whether an input is a URL
 * 
 * This function is also part of zzbrick, there it is called brick_check_url()
 * @param string $url	URL to be tested, may be a relative URL as well (starting with ../, /)
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

/**
 * checks whether an input is a valid URI
 * 
 * This function is also part of zzbrick, there it is called brick_is_url()
 * @param string $url	URL to be tested, only absolute URLs
 * @return string url if correct, or false
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo return which part of URL is incorrect
 */
function zz_is_url($url) {
	if (!$url) return false;
	$parts = parse_url($url);
	if (!$parts) return false;
	if (empty($parts['scheme'])) { // OR !in_array($parts['scheme'], $possible_schemes))
		return false;
	} elseif (!empty($parts['host']) 
		AND (!preg_match("/^[0-9a-z]([-.]?[:0-9a-z])*\.[a-z]{2,6}$/i", $parts['host'], $regs)
		AND !preg_match('/[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}/', $parts['host']) // IP
		AND !preg_match('/\[[0-9a-zA-Z:]*\]/', $parts['host']))) {	// LDAP
		return false;
	} elseif (!empty($parts['user']) 
		AND !preg_match("/^([0-9a-z-]|[\_])*$/i", $parts['user'], $regs)) {
		return false;
	} elseif (!empty($parts['pass']) 
		AND !preg_match("/^([0-9a-z-]|[\_])*$/i", $parts['pass'], $regs)) {
		return false;
	} elseif (!empty($parts['path']) 
		AND !preg_match("/^[0-9a-z\/_\.@~\-,=%;:+]*$/i", $parts['path'])) {
		return false;
	} elseif (!empty($parts['query']) 
		AND !preg_match("/^[A-Za-z0-9\-\._~!$&'\(\)\*+,;=:@?\/%]*$/i", $parts['query'], $regs)) {
		// not 100% correct: % must only appear in front of HEXDIG, e. g. %2F
		// here it may appear in front of any other sign
		// see 
		// http://www.ietf.org/rfc/rfc3986.txt and 
		// http://www.ietf.org/rfc/rfc2234.txt
		return false;
	}
	return true;
}

?>