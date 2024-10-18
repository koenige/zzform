<?php 

/**
 * zzform
 * URL functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * get zzform URL
 *
 * @param string $type (optional)
 * @param string $new_value (optional)
 * @return string
 */
function zzform_url($type = 'full+qs+qs_zzform', $new_value = '') {
	global $zz_conf;
	if (empty($zz_conf['int']['url'])) {
		$zz_conf['int']['url'] = zz_get_url_self();
		zz_extra_get_params();
	}
	
	switch ($type) {
	case 'extra_get':
		return $zz_conf['int']['extra_get'];
	case 'full':
		return $zz_conf['int']['url']['full'];
	case 'full+qs+qs_zzform':
		$url = $zz_conf['int']['url']['full'].$zz_conf['int']['url']['qs'];
		if ($zz_conf['int']['url']['qs'] AND $zz_conf['int']['url']['qs_zzform'])
			$url .= '&';
		$url .= $zz_conf['int']['url']['qs_zzform'];
		return $url;
	case 'full+qs_zzform':
		return $zz_conf['int']['url']['full'].$zz_conf['int']['url']['qs_zzform'];
	case 'qs+qs_zzform':
		$url = $zz_conf['int']['url']['qs'];
		if ($url AND $zz_conf['int']['url']['qs_zzform'])
			$url .= '&';
		$url .= $zz_conf['int']['url']['qs_zzform'];
		return $url;
	case 'qs_zzform':
		return $zz_conf['int']['url']['qs_zzform'];
	case 'self':
		return $zz_conf['int']['url']['self'];
	case 'self+extra':
		$url = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'];
		if ($zz_conf['int']['extra_get']) {
			$url .= $zz_conf['int']['url']['qs'] ? '&' : '?';
			$url .= $zz_conf['int']['extra_get'];
		}
		return $url;
	case 'self+qs':
		$url = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'];
		return $url;
	case 'self+qs+qs_zzform':
		$url = $zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'];
		if ($zz_conf['int']['url']['qs'] AND $zz_conf['int']['url']['qs_zzform'])
			$url .= '&';
		$url .= $zz_conf['int']['url']['qs_zzform'];
		return $url;
	}
	return '';
}

/**
 * add query string
 *
 * @param $add array keys of query string to be added
 * @param string $url (URL or just query string)
 * @return string
 */
function zzform_url_add($add, $url = NULL) {
	if (is_null($url)) $url = zzform_url();
	$build = zzform_url_with_path($url);
	$url = $build ? parse_url($url) : ['query' => $url];
	$url['query'] = zz_edit_query_string($url['query'] ?? '', [], $add);
	if ($build) {
		if (str_starts_with($url['query'], '?')) $url['query'] = substr($url['query'], 1);
		return wrap_build_url($url);
	}
	return $url['query'];
}

/**
 * remove query string
 *
 * @param $remove array keys of query string to be removed
 * @param string $key (full query string or shortcut, defaults to 'qs_zzform')
 * @param string $action
 * @return string
 */
function zzform_url_remove($remove, $key = 'qs_zzform', $action = 'change') {
	global $zz_conf;
	
	switch ($key) {
		case 'extra_get':
		case 'qs_zzform':
		case 'qs+qs_zzform':
			$query = zzform_url($key);
			if (strstr($key, '+')) $action = 'return'; // merged keys, only return possible
			break;
		default:
			if ($build = zzform_url_with_path($key)) {
				$url = parse_url($key);
				$query = $url['query'] ?? '';
			} else {
				$query = $key;
			}
			$action = 'return';
			break;
	}
	$new = zz_edit_query_string($query, $remove);

	switch ($action) {
	case 'change':
		if ($key === 'extra_get')
			$zz_conf['int'][$key] = $new;
		else
			$zz_conf['int']['url'][$key] = $new;
		break;
	case 'return':
		if (empty($build)) return $new;
		$url['query'] = $new;
		if (str_starts_with($url['query'], '?')) $url['query'] = substr($url['query'], 1);
		return wrap_build_url($url);
	}
}

/**
 * check if URL has path
 *
 * @param string $url
 * @return bool
 */
function zzform_url_with_path($url) {
	if (str_starts_with($url, '/')) return true;
	if (preg_match('/^[a-z]+:(.+)/', $url)) return true;
}

/**
 * escape URL characters
 *
 * @param string $url
 * @return string
 */
function zzform_url_escape($url) {
	if (!$url) return $url;
	$url = str_replace('&amp;', '&', $url);
	$url = str_replace('&', '&amp;', $url);
	$url = str_replace('[', urlencode('['), $url);
	$url = str_replace(']', urlencode(']'), $url);
	return $url;
}

/**
 * define URL of script
 *
 * @return array $url (= $zz_conf['int']['url'])
 *		'self' = own URL for form action
 *		'qs' = query string part of URL
 *		'qs_zzform' = query string part of zzform of URL
 *		'full' = full URL with base and request path
 */
function zz_get_url_self() {
	global $zz_page;
	
	$my_uri = $zz_page['url']['full'];
	if (!empty($my_uri['path_forwarded']) AND str_starts_with($my_uri['path'], $my_uri['path_forwarded'])) {
		$my_uri['path'] = substr($my_uri['path'], strlen($my_uri['path_forwarded']));
	}
	$my_uri['path'] = wrap_setting('base').$my_uri['path'];

	// query string: existing and from zzform
	$url['qs'] = '';
	$url['qs_zzform'] = '';
	$qs_key = wrap_setting('zzform_url_keep_query') ? 'qs' : 'qs_zzform';
	$url[$qs_key] = !empty($my_uri['query']) ? '?'.$my_uri['query'] : '';

	$url['full'] = wrap_setting('host_base').$my_uri['path'];
	$url['self'] = wrap_setting('zzform_host_base') ? $url['full'] : $my_uri['path'];
	return $url;
}

/** 
 * Removes unwanted keys from QUERY_STRING
 * 
 * @param string $query			query-part of URI
 * @param array $unwanted_keys	keys that shall be removed, subkeys might be
 *		removed writing key[subkey]
 * @param array $new_keys		keys and values in pairs that shall be added or
 *		overwritten
 * @return string $string		New query string without removed keys
 */
function zz_edit_query_string($query, $unwanted_keys = [], $new_keys = []) {
	$query = str_replace('&amp;', '&', $query);
	if (substr($query, 0, 1) === '?')
		$query = substr($query, 1);
	parse_str($query, $parts);

	// remove unwanted keys from URI
	if (!is_array($unwanted_keys)) $unwanted_keys = [$unwanted_keys];
	foreach (array_keys($parts) as $key) {
		if (in_array($key, $unwanted_keys)) {
			unset($parts[$key]);
		} elseif (is_array($parts[$key])) {
			foreach (array_keys($parts[$key]) as $subkey) {
				foreach ($unwanted_keys as $unwanted) {
					if ($unwanted === $key.'['.$subkey.']') {
						unset($parts[$key][$subkey]);
					}
				}
			}
		}
	}

	// add new keys or overwrite existing keys
	if (!is_array($new_keys)) $new_keys = [$new_keys];
	foreach ($new_keys as $new_key => $new_value) {
		if (array_key_exists($new_key, $parts) AND is_array($parts[$new_key])) {
			$parts[$new_key] = array_merge($parts[$new_key], $new_value);
		} else {
			$parts[$new_key] = $new_value;
		}
	}

	// glue everything back together
	$query_string = http_build_query($parts);
	// set keys without values, too (e. g. delete = NULL)
	foreach ($parts as $part => $value)
		if (is_null($value)) $query_string .= ($query_string ? '&' : '').$part;
	if (!$query_string) return '';
	$query_string = wrap_url_normalize_percent_encoding($query_string, 'query');
	return '?'.$query_string; // URL without unwanted keys
}

/**
 * changes own URL, adds some extra parameter
 *
 * @global array $zz_conf
 *		string 'extra_get' for extra GET parameters for links
 * @return void
 */
function zz_extra_get_params() {
	global $zz_conf;

	// Extra GET Parameter
	$keep_query = [];
	$keep_fields = [
		'where', 'var', 'order', 'group', 'q', 'scope', 'dir', 'referer',
		'url', 'nolist', 'filter', 'debug', 'zz'
	];
	foreach ($keep_fields AS $key) {
		if (!empty($_GET[$key])) $keep_query[$key] = $_GET[$key];
	}
	// write some query strings differently
	if (isset($_GET['nolist'])) 
		$keep_query['nolist'] = true;
	if ($zz_conf['int']['this_limit'] AND $zz_conf['int']['this_limit'] != wrap_setting('zzform_limit'))
		$keep_query['limit'] = $zz_conf['int']['this_limit'];
	elseif (!empty($zz_conf['int']['limit_last']))
		$keep_query['limit'] = 'last';

	$zz_conf['int']['extra_get'] = http_build_query($keep_query);
}
