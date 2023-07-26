<?php 

/**
 * zzform
 * Validation of user input
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * all functions return false if requirements are not met
 * otherwise they will return the value that was checked
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2005-2014, 2016-2018, 2020-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
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
	$e_mail = strtolower($e_mail); // case insensitive, save it lowercase

	if ($type === 'mail+name') {
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
		$correct_mails = [];
		foreach ($mails as $mail) {
			$mail = trim($mail);
			$parts = explode(' ', $mail);
			$this_name = '';
			$this_mail = '';
			foreach ($parts as $index => $part) {
				if ($index < count($parts)-1)
					$this_name .= ' '.$part;
				else {
					$this_mail = wrap_mail_valid($part);
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
		$e_mail = wrap_mail_valid($e_mail);
		return $e_mail;
	}

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
	$values = [];
	$column_definition = zz_db_columns($db_table, $colum);
	if (!$column_definition) return false;
	if (str_starts_with($column_definition['Type'], "set('")
		AND str_ends_with($column_definition['Type'], "')")) {
		// column of type SET
		$values = substr($column_definition['Type'], 5, -2);
		$values = explode("','", $values);
	} elseif (str_starts_with($column_definition['Type'], "enum('")
		AND str_ends_with($column_definition['Type'], "')")) {
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
 * @param string $url	URL to be tested, may be a relative URL as well 
 *		(starting with ../, /) might add http:// in front of it if this  
 *		generates a valid URL
 * @return string url if correct, or false
 */
function zz_check_url($url) {
	// remove invalid white space at the beginning and end of URL
	$url = trim($url);
	// not sure: is \ a legal part of a URL?
	$url = str_replace("\\", "/", $url);
	if (str_starts_with($url, '/')) {
		if (zz_is_url('http://example.com'.$url)) return $url;
		else return false;
	} elseif (str_starts_with($url, './')) {
		if (zz_is_url('http://example.com'.substr($url, 1))) return $url;
		else return false;
	} elseif (str_starts_with($url, '../')) {
		if (zz_is_url('http://example.com'.substr($url, 2))) return $url;
		else return false;
	}
	$parts = zz_is_url($url);
	if (!$parts) {
		$url = 'http://'.$url;
		$parts = zz_is_url($url);
		if (!$parts) return false;
	}
	$url = wrap_build_url($parts);
	return $url;
}

/**
 * checks whether an input is a URL or a placeholder identifier for a URL
 * 
 * @param string $url
 * @return string url if correct, or false
 */
function zz_check_url_placeholder($url) {
	// remove invalid white space at the beginning and end of URL
	$url = trim($url);
	if ($url === '/') return $url;

	// full URL?
	$parts = zz_is_url($url);
	if ($parts) return $url;
	// looks like full URL but must be broken
	if (strstr($url, '://')) return false;

	// placeholder URL starts with / and gets ending from field ending
	if (!str_starts_with($url, '/')) $url = sprintf('/%s', $url);
	if (str_ends_with($url, '/')) $url = substr($url, 0, -1);
	if (str_ends_with($url, '.html')) $url = substr($url, 0, -5);
	
	// no query strings allowed, just [a-z0-9] plus some special characters
	// replace space with -
	$allowed = ['/', '*', '%', '.', '_'];
	foreach ($allowed as $char) $replacements[$char] = $char;
	$url = wrap_filename($url, '-', $replacements);
	$url = strtolower($url);
	return $url;
}

/**
 * checks whether an input is a valid URI
 * 
 * This function is also part of zzbrick, there it is called brick_is_url()
 * @param string $url	URL to be tested, only absolute URLs
 * @return mixed array url if correct, or bool false
 * @todo return which part of URL is incorrect
 * @todo support IPv6, new domain endings
 * @todo rewrite diacritical marks to %-encoding
 */
function zz_is_url($url) {
	if (!$url) return false;
	$parts = parse_url($url);
	if (!$parts) return false;

	$tested_parts = ['scheme', 'host', 'port', 'user', 'pass', 'path', 'query'];
	foreach ($tested_parts as $key) {
		$part = $parts[$key] ?? '';
		switch ($key) {
		case 'scheme':
			if (!$part) return false;
			break;

		case 'host':
			if (!$part) break;
			$hostname = "/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/";
			if (preg_match($hostname, $part)) break;
			$ip_v4 = "/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/";
			if (preg_match($ip_v4, $part)) break;
			$ldap = '/\[[0-9a-zA-Z:]*\]/';
			if (preg_match($ldap, $part)) break;
			// punycode domain name?
			$parts['host'] = wrap_punycode_encode($part);
			// here, we'll check if this is not only a wrong entry
			// for all records, checkdnsrr would take too long
			if ($part !== $parts['host']) {
				if (checkdnsrr($parts['host'], 'ANY')) break;
			}
			return false;

		case 'port':
			if (!$part) break;
			if (intval($part).'' !== $part) return false;
			break;

		case 'user':
			if (!$part) break;
			if (!preg_match("/^([0-9a-z-]|[\_])*$/i", $part)) return false;
			break;

		case 'pass':
			if (!$part) break;
			if (!preg_match("/^([0-9a-z-]|[\_])*$/i", $part)) return false;
			break;

		case 'path':
			if (!$part) break;
			if (!preg_match("/^[0-9a-z\/_\.@~\-,=%;:+\(\)]*$/i", $part)) return false;
			break;

		case 'query':
			if (!$part) break;
			// not 100% correct: % must only appear in front of HEXDIG, e. g. %2F
			// here it may appear in front of any other sign
			// see 
			// http://www.ietf.org/rfc/rfc3986.txt and 
			// http://www.ietf.org/rfc/rfc2234.txt
			if (!preg_match("/^[A-Za-z0-9\-\._~!$&'\(\)\*+,;=:@?\/%]*$/i", $part)) return false;
			break;
		}
	}
	return $parts;
}

/**
 * converts user input time into HH:MM:SS or returns false if given time is illegal
 * 
 * @param string $time time in several possible formats
 * @return mixed false if input is illegal, time-string if input is correct
 */
function zz_check_time($time) {
	$time = trim($time);
	$time = str_replace(' ', ':', $time);
	$time = str_replace('::', ':', $time);
	if (strlen($time) === 19) {
		// might be a date
		$time = substr($time, 11);
	} elseif (strlen($time) === 25
		AND preg_match('/^\+\d\d:\d\d$/', substr($time, 19))
	) {
		// timestamp with timezone offset
		// get rid of timezone offset, @todo save timezone offset
		$time = substr($time, 0, 10).' '.substr($time, 11, 8);
	} elseif (preg_match('~^\d{1,2}$~', $time)) {
		$time .= ':00:00';
	}
	// allow input like 855 for 08:55:00
	if (strlen($time) === 3) $time = sprintf('0%s', $time);
	$timestamp = strtotime($time);
	if (!$timestamp) return false;
	$time = date("H:i:s", $timestamp);
	return $time;
}

/**
 * converts user input date into international date string
 * 
 * @param string $date date in several possible formats
 * @return string international date (ISO 8601) YYYY-MM-DD
 * @todo return ambiguous dates with a warning
 * @todo check if date is a valid calendar date (e. g. 2011-02-29 is invalid)
 */
function zz_check_date($date) {
	// remove unnecessary whitespace
	$date = trim($date);
	if (!$date) return false;
	if ($date === '0000-00-00') return false;
	if ($date === '0000/00/00') return false;
	// replace non breaking space with space
	$date = str_replace("\xc2\xa0", ' ', $date);

	// @todo: allow addition of months in different languages via config
	$months = [
		1 => ['Januar', 'January', 'janvier', 'Jan', 'I', 'Styczeń'],
		2 => ['Februar', 'February', 'février', 'Feb', 'F.vrier', 'II', 'Luty'],
		3 => ['März', 'March', 'mars', 'Mar', 'M.r', 'M.rz', 'III', 'Marzec'],
		4 => ['April', 'avril', 'Apr', 'IV', 'Kwiecień'],
		5 => ['Mai', 'May', 'fevrier', 'V', 'Maj'],
		6 => ['Juni', 'June', 'juin', 'Jun', 'VI', 'Czerwiec'],
		7 => ['Juli', 'July', 'juilett', 'Jul', 'VII', 'Lipiec'],
		8 => ['August', 'août', 'ao.t', 'Aug', 'VIII', 'Sierpień'],
		9 => ['September', 'Septembre', 'Sep', 'IX', 'Wrzesień'],
		10 => ['Oktober', 'October', 'octobre', 'Oct', 'Okt', 'X', 'Październik'],
		11 => ['November', 'novembre', 'Nov', 'XI', 'Listopad'],
		12 => ['Dezember', 'December', 'décembre', 'd.cembre', 'Dec', 'Dez', 'XII', 'Grudzień']
	];
	foreach ($months as $month => $values) {
		foreach ($values as $value) {
			if (preg_match('/^[^a-z]*'.$value.'[^a-z]/i', $date)) {
				$date = preg_replace('/'.$value.'/i', $month, $date);
				$new['month_a'] = $month;
			}
		}
	}
	if (preg_match('/^[0-9]{6}$/', $date)) {// 250376 or 032576 or 760325
		$new['day'] = substr($date, 0, 2);
		$new['month'] = substr($date, 2, 2);
		$new['year'] = substr($date, 4, 2);
		if ($new['month'] > 12) {
			$new['day'] = substr($date, 2, 2);
			$new['month'] = substr($date, 0, 2);
		} elseif ($new['day'] > 31) {
			$new['year'] = substr($date, 0, 2);
			$new['day'] = substr($date, 4, 2);
		}
	} elseif (preg_match('/^[0-9]{8}$/', $date)) { // 25031976 or 19760325
		if (substr($date, 4, 2) > 12) {
			// 25031976, on from year 1300
			$new['day'] = substr($date, 0, 2);
			$new['month'] = substr($date, 2, 2);
			$new['year'] = substr($date, 4, 4);
		} else {
			$new['year'] = substr($date, 0, 4);
			$new['month'] = substr($date, 4, 2);
			$new['day'] = substr($date, 6, 2);
		}
	} elseif (preg_match('/^([0-9]{1,4})[^0-9][ ]*([0-9]{1,2})[^0-9][ ]*([0-9]{1,5})/', $date, $matches)) {
		// number 
		if (strlen($matches[3]) > 4) return false; // error, should be a re-entry
		if (!(empty($new['month_a'])) && ($new['month_a'] != $matches[2])) {
			$matches['bak'] = $matches[2];
			$matches[2] = $matches[1];
			$matches[1] = $matches['bak'];
			unset($matches['bak']);
		}
		$new['month'] = $matches[2];
		if ($matches[1] > 31) {
			$new['year'] = $matches[1];
			$new['day'] = $matches[3];
		} else {
			$new['year'] = $matches[3];
			$new['day'] = $matches[1];
		}
	} elseif (preg_match('/^([0-9]{1,4})$/', $date)) {
		// year only
		$new['year'] = $date;
		while (strlen($new['year']) < 4)
			$new['year'] = '0'.$new['year'];
		$new['month'] = '00';
		$new['day'] = '00';
	} elseif (preg_match('/^([0-9]{1,4})[^0-9][ ]*([0-9]{1,4})$/', $date, $matches)) {
		$new['day'] = '00';
		if ($matches[1] > 31) {
			$new['year'] = $matches[1];
			$new['month'] = $matches[2];
		} else {
			$new['month'] = $matches[1];
			$new['year'] = $matches[2];
		}
	} elseif (in_array($date, ['yesterday', 'hier', 'gestern', 'wczoraj'])) {
		return date('Y-m-d', strtotime('yesterday'));
	} elseif (in_array($date, ['today', 'aujourd\'hui', 'heute', 'dzisiaj', 'dziś'])) {
		return date('Y-m-d', strtotime('today'));
	} elseif (in_array($date, ['tomorrow', 'demain', 'morgen', 'jutro'])) {
		return date('Y-m-d', strtotime('tomorrow'));
	}
	if (empty($new)) return false;
	// only month: not enough! (could be "IX magazine" which is interpreted as
	// month or similar)
	if (count($new) === 1 AND !empty($new['month_a'])) return false;
	if ($new['year'] < 100 AND strlen($new['year']) < 3) {
		// this is for convenience, historic dates must be entered with at least
		// three digits for the year (leading zeros)
		$barrier = substr(date('Y'), 2) + 10;
		$current_century = substr(date('Y'), 0, 2);
		if ($new['year'] > $barrier) $century = $current_century - 1;
		else $century = $current_century;
		$new['year'] = sprintf('%d%02d', $century, $new['year']);
	}
	if ($new['month'] > 12) return false;
	if ($new['day'] > 31) return false;
	if ($new['day'] > 30 AND in_array($new['month'], [4, 6, 9, 11])) return false;
	if ($new['day'] > 29 AND $new['month'] === 2) return false;

	return sprintf("%04d-%02d-%02d", $new['year'], $new['month'], $new['day']);
}

/**
 * converts user input date and time into datetime format (Y-m-d H:i:s)
 * 
 * @param string $datetime
 * @return string
 */
function zz_check_datetime($datetime, $field = []) {
	if (strstr($datetime, ' ')) {
		$datetime = explode(' ', $datetime);
		if (count($datetime) === 2) {
			$date = $datetime[0];
			$time = $datetime[1];
		} else {
			$time = array_pop($datetime);
			$date = implode(' ', $datetime);
		}
	} else {
		$date = $datetime;
		$time = $field['default_time'] ?? '00:00:00';
	}
	$date = zz_check_date($date);
	if (!$date) return false;
	$time = zz_check_time($time);
	if (!$time) return false;
	return $date.' '.$time;
}

/**
 * checks whether an input is a number or a simple calculation
 * perform a calculation with a given string
 * supports * / + - but no roots, powers, brackets etc.
 * 
 * @param string $number number or calculation, may contain +-/* 0123456789 ,.
 * @return mixed number, with calculation performed / false if incorrect format
 */
function zz_check_number($number) {
	if (!$number) return $number;
	// remove whitespace, it's nice to not have to care about this
	$check = trim($number);
	$check = str_replace(' ', '', $check);
	if (wrap_setting('character_set') === 'utf-8') {
		$check = str_replace(chr(194).chr(160), '', $check); // non-breaking space
	} else {
		$check = str_replace(chr(160), '', $check); // non-breaking space
	}

	// first character must not be / or *
	// NULL: possible feature: return doubleval $check to get at least something
	if (!preg_match('~^[0-9.,+-][0-9.,\+\*\/-]*$~', $check)) return NULL;
	// put a + at the beginning, so all parts with real numbers start with 
	// arithmetic symbols
	if (!in_array(substr($check, 0, 1), ['-', '+'])) {
		$check = '+'.$check;
	}

	preg_match_all('~([-+/*])([0-9.,]+)~', $check, $tokens);

	$values = $tokens[2];
	// go through all parts and solve the '.' and ',' problem
	foreach ($values as $index => $value) {
		if ($dot = strpos($value, '.') AND $comma = strpos($value, ',')) {
			if ($dot > $comma) $values[$index] = str_replace(',', '', $value);
			else {
				$values[$index] = str_replace('.', '', $value);
				$values[$index] = str_replace(',', '.', $values[$index]);
			}
		// must not: enter values like 1,000 and mean 1000!
		} elseif (strstr($value, ',')) {
			$values[$index] = str_replace(',', '.', $value);
		}
		// too many dots: this does not work (could be a mistyped date)
		if (substr_count($values[$index], '.') > 1) return NULL;
	}

	$sum = 0;
	$operators = $tokens[1];
	$index = count($operators) - 1;
	
	// 1: division, replace it with multiplication (*1/n)
	// in order to be able to check multiplication tokens backwards
	foreach ($operators as $index => $operator) {
		if ($operator !== '/') continue;
		// division by zero? e. g. in GPS EXIF data 0/0
		if (!$values[$index]) return NULL;
		$operators[$index] = '*';
		$values[$index] = 1 / $values[$index];
	}
	
	
	// 2: multiplication
	while ($index >= 0) {
		$operator = $operators[$index];
		if ($operator === '*') {
			$values[$index - 1] *= $values[$index];
		}
		$index--;
	}

	// 3: addition and substraction
	foreach ($operators as $index => $operator) {
		switch ($operator) {
			case '-': $sum -= $values[$index]; break;
			case '+': $sum += $values[$index]; break;
		}
	}

	// in case some error occured, check what it is
	if (!$sum AND $sum.'' !== '0') {
		zz_error_log([
			'msg_dev' => '%s(): calculation did not work. [%s]',
			'msg_dev_args' => [__FUNCTION__, $number],
			'level' => E_USER_NOTICE
		]);
		$sum = false;
	}
	return $sum;
}

/**
 * checks whether an input is a public username for a website
 * 
 * @param string $username (username or URL)
 * @param array $field
 *		string parse_url
 *		string url
 *		bool dont_check_username_online
 * @return string
 */
function zz_check_username($username, $field) {
	// URL or username?
	$url = parse_url($username);
	$field_value = '';
	if ($url['path'] AND str_starts_with($url['path'], '/') AND !empty($field['parse_url'])) {
		if (strstr($field['parse_url'], '[')) {
			$parse_url = explode('[', $field['parse_url']);
			foreach ($parse_url as $index => $value) {
				if (!$index) continue;
				if (substr($value, -1) !== ']') continue;
				$parse_url[$index] = substr($value, 0, -1);
			}
		} elseif (!is_array($field['parse_url'])) {
			$parse_url = [$field['parse_url']];
		} else {
			$parse_url = $field['parse_url'];
		}
		if (array_key_exists($parse_url[0], $url)) {
			if (!isset($parse_url[1])) {
				$field_value = $url[$parse_url[0]];
			} else {
				switch ($parse_url[0]) {
				case 'path':
					if (substr($url['path'], 0, 1) === '/')
						$url['path'] = substr($url['path'], 1);
					$path = explode('/', $url['path']);
					if (str_ends_with($parse_url[1], '+')) {
						$fragment = substr($parse_url[1], 0, -1);
						$field_value = [];
						while (!empty($path[$fragment])) {
							$field_value[] = $path[$fragment];
							$fragment++;
						}
						$field_value = implode('/', $field_value);
					} else {
						if (empty($path[$parse_url[1]])) break;
						$field_value = $path[$parse_url[1]];
					}
					break;
				case 'query':
					parse_str($url['query'], $query);
					if (empty($query[$parse_url[1]])) break;
					$field_value = $query[$parse_url[1]];
					break;
				}
			}
		}
	}
	if (!$field_value) $field_value = $username;

	$is_ascii = mb_detect_encoding($field_value, 'ASCII', true);
	if (!$is_ascii) {
		$field_value = urlencode($field_value);
	}
	
	// does username exist?
	$url = sprintf($field['url'], $field_value);
	if (!zz_is_url($url)) return false;

	if (empty($field['dont_check_username_online'])) {
		require_once wrap_setting('core').'/syndication.inc.php';
		$success = wrap_syndication_get($url, 'html');
		if (empty($success['_']['data'])) return false;
	}

	if (strstr($field_value, '%')) $field_value = urldecode($field_value);
	return $field_value;
}


/**
 * check against pattern
 * 
 * @param mixed $value
 * @param string $pattern
 * @return bool true = valid
 */
function zz_validate_pattern($value, $pattern) {
	if (is_array($value)) return false;
	if (!preg_match('/'.$pattern.'/', $value, $matches)) return false;
	if (!$matches[0].'' === $value) return false;
	return $value;
}
