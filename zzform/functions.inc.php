<?php 

/**
 * zzform
 * Miscellaneous functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 * 
 * Contents:
 * C - Core functions
 * E - Error functions
 * F - Filesystem functions
 * H - Hierarchy functions
 * I - Internationalisation functions
 * O - Output functions
 * R - Record functions used by several modules
 * V - Validation, preparation for database
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/*
 * --------------------------------------------------------------------
 * C - Core functions
 * --------------------------------------------------------------------
 */

/**
 * check and add modules
 *
 * @param string $module
 * @param array $new_modules
 * @return bool
 */
function zz_modules($module, $new_modules = []) {
	static $active_modules = [];

	$debug_started = false;
	if (wrap_setting('debug') AND function_exists('zz_debug')) {
		zz_debug('start', __FUNCTION__);
		$debug_started = true;
	}
	
	foreach ($new_modules as $new_module) {
		// do we have that module already?
		if (array_key_exists($new_module, $active_modules) AND $active_modules[$new_module]) continue;
		// debug only if setting is true
		if ($new_module === 'debug' AND !wrap_setting('debug')) continue;
		if (!file_exists(__DIR__.'/'.$new_module.'.inc.php')) {
			if (wrap_setting('debug') AND function_exists('zz_debug'))
				zz_debug(sprintf('Module %s not included', $new_module));
			continue;
		}
		require_once __DIR__.'/'.$new_module.'.inc.php';
		if (wrap_setting('debug') AND function_exists('zz_debug')) {
			if (!$debug_started) {
				zz_debug('start', __FUNCTION__);
				$debug_started = true;
			}
			zz_debug(sprintf('Module %s included', $new_module));
		}
		$active_modules[$new_module] = true;
	}

	if ($debug_started) zz_debug('end');
	
	if ($module and !empty($active_modules[$module])) return true;
	return false;
}

/**
 * includes modules which are dependent on $zz-table definition
 *
 * @param array $zz Table definition
 *		checking 'conditions', 'fields'
 * @global array $zz_conf
 *		checking 'generate_output'
 * @return void
 */
function zz_dependent_modules(&$zz) {
	global $zz_conf;

	// check if POST is too big, then it will be empty
	$zz_conf['int']['post_too_big'] = $zz_conf['generate_output'] ? zzform_post_too_big() : false;

	$modules = [
		'translations', 'conditions', 'geo', 'export', 'filter', 'upload', 'captcha'
	];
	foreach ($modules as $index => $module) {
		// continue if module already loaded
		if (zz_modules($module)) continue;
		switch ($module) {
		case 'translations':
			if (!wrap_setting('translate_fields'))
				unset($modules[$index]);
			break;
		case 'conditions':
			if (!empty($zz['conditions'])) break;
			if (!empty($zz['if'])) break;
			if (!empty($zz['unless'])) break;
			foreach ($zz['fields'] as $field) {
				// Look for shortcuts for conditions
				if (isset($field['if'])) break 2;
				if (isset($field['unless'])) break 2;
				if (!empty($field['fields'])) {
					foreach ($field['fields'] as $subfield) {
						if (isset($subfield['if'])) break 3;
						if (isset($subfield['unless'])) break 3;
					}
				}
			}
			unset($modules[$index]);
			break;
		case 'geo':
			$geo = false;
			if (zz_module_fieldcheck($zz['fields'], 'geocode'))
				$geo = true;
			elseif (zz_module_fieldcheck($zz['fields'], 'number_type', 'latitude'))
				$geo = true;
			elseif (zz_module_fieldcheck($zz['fields'], 'number_type', 'longitude'))
				$geo = true;
			elseif (zz_module_fieldcheck($zz['fields'], 'type', 'geo_point'))
				$geo = true;
			if (!$geo) unset($modules[$index]);
			break;
		case 'export':
		case 'filter':
			$found = zz_module_key_check($zz, $module);
			if ($found) break;
			if ($module === 'export') zz_module_remove_mode($module);
			$zz[$module] = [];
			unset($modules[$index]);
			if (isset($_GET[$module])) {
				wrap_static('page', 'status', 404);
				$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
					$zz_conf['int']['url']['qs_zzform'], [$module]
				);
			}
			break;
		case 'upload':
			// check if there was an upload, so we need this module
			if ($zz_conf['int']['post_too_big']) break;
			if (!empty($_FILES)) break;
			if (!zz_module_fieldcheck($zz['fields'], 'type', 'upload_image')) {
				unset($modules[$index]);
			}
			break;
		case 'captcha':
			if (!zz_module_fieldcheck($zz['fields'], 'type', 'captcha'))
				unset($modules[$index]);
			break;
		}
	}
	zz_modules('', $modules);
	return true;
}

/**
 * check if there is a key for the module in the $zz definition
 * including if/unless, return false if no output is shown
 *
 * @param array $zz
 * @param string $module
 * @return bool
 */
function zz_module_key_check($zz, $module) {
	global $zz_conf;

	// module is for output only
	if ($zz_conf['generate_output'] === false) return false;

	// check if module is used
	if (!empty($zz[$module])) return true;
	$conditionals = ['if', 'unless'];
	foreach ($conditionals as $conditional) {
		if (empty($zz[$conditional])) continue;
		foreach ($zz[$conditional] as $condition) {
			if (!empty($condition[$module])) return true;
		}
	}
	return false;
}

/**
 * remove a mode depending on module
 *
 * @param string $mode
 * @return void
 */
function zz_module_remove_mode($mode) {
	$allowed_modes = wrap_setting('zzform_allowed_modes');
	$index = array_search($mode, $allowed_modes);
	unset($allowed_modes[$index]);
	wrap_setting('zzform_allowed_modes', $allowed_modes);
}

/**
 * checks whether fields contain a value for a certain key
 *
 * @param array $definition
 * @param string $key
 * @param string $type field type
 * @return
 */
function zz_module_fieldcheck($definition, $key, $type = '') {
	foreach ($definition as $field) {
		if (zz_module_fieldchecks($field, $key, $type)) {
			return true;
		}
		if (!empty($field['if'])) {
			foreach ($field['if'] as $condfield) {
				if (zz_module_fieldchecks($condfield, $key, $type)) {
					return true;
				}
			}
		}
		if (empty($field['fields'])) continue;
		foreach ($field['fields'] as $index => $subfield) {
			if (!is_array($subfield)) continue;
			if (zz_module_fieldchecks($subfield, $key, $type)) {
				return true;
			}
			if (!empty($subfield['if'])) {
				foreach ($subfield['if'] as $condfield) {
					if (zz_module_fieldchecks($condfield, $key, $type)) {
						return true;
					}
				}
			}
			if (empty($subfield['fields'])) continue;
			foreach ($subfield['fields'] as $sindex => $ssubfield) {
				if (!is_array($ssubfield)) continue;
				if (zz_module_fieldchecks($ssubfield, $key, $type)) {
					return true;
				}
				if (!empty($ssubfield['if'])) {
					foreach ($ssubfield['if'] as $scondfield) {
						if (zz_module_fieldchecks($scondfield, $key, $type)) {
							return true;
						}
					}
				}
			}
		}
	}
	return false;
}

/**
 * check a field if key exists or if key equals type
 *
 * @param array $field
 * @param string $key
 * @param string $type field type
 * @return bool
 */
function zz_module_fieldchecks($field, $key, $type) {
	if (!is_array($field)) return false;
	if (!array_key_exists($key, $field)) return false;
	if (!$field[$key]) return false;
	if (!$type) return true;
	if ($field[$key] === $type) return true;
	return false;
}

/**
 * define URL of script
 *
 * @return array $url (= $zz_conf['int']['url'])
 *		'self' = own URL for form action
 *		'?&' = either ? or & to append further query strings
 *		'qs' = query string part of URL
 *		'qs_zzform' = query string part of zzform of URL
 *		'full' = full URL with base and request path
 */
function zz_get_url_self() {
	global $zz_page;
	global $zz_conf;
	
	$my_uri = $zz_page['url']['full'];
	if (!empty($my_uri['path_forwarded']) AND str_starts_with($my_uri['path'], $my_uri['path_forwarded'])) {
		$my_uri['path'] = substr($my_uri['path'], strlen($my_uri['path_forwarded']));
	}
	$my_uri['path'] = wrap_setting('base').$my_uri['path'];

	// some basic settings
	// normal situation: there is no query string in the base url, 
	// so add query string starting ?
	$url['?&'] = '?';
	// no base query string which belongs url_self
	$url['qs'] = '';
	$url['scheme'] = $my_uri['scheme'];
	$url['base'] = $url['scheme'].'://'.$my_uri['host'];
	if (!in_array($_SERVER['SERVER_PORT'], [80, 443])) {
		$url['base'] .= sprintf(':%s', $_SERVER['SERVER_PORT']);
	}

	if (empty($zz_conf['url_self'])) {
		// nothing was defined, we just do it as we like
		$url['self'] = $my_uri['path'];
		// zzform query string
		$url['qs_zzform'] = !empty($my_uri['query']) ? '?'.$my_uri['query'] : '';
		$url['full'] = $url['base'].$url['self'];
		if (wrap_setting('zzform_host_base'))
			$url['self'] = $url['base'].$url['self'];
		return $url;
	}

	// it's possible to use url_self without http://hostname, so check for that
	$examplebase = (substr($zz_conf['url_self'], 0, 1) === '/') ? $url['base'] : '';
	$base_uri = parse_url($examplebase.$zz_conf['url_self']);
	if ($examplebase) {
		$url['self'] = $base_uri['path'];
		$url['full'] = $url['base'].$url['self'];
	} else {
		$url['self'] = $base_uri['scheme'].'://'.$base_uri['host'].$base_uri['path'];
		$url['full'] = $url['self'];
	}
	if (!empty($base_uri['query'])) {
		// no base query string which belongs url_self
		$url['qs'] = '?'.$base_uri['query'];
		$url['?&'] = '&amp;';
		if (!empty($my_uri['query'])) {
			parse_str($my_uri['query'], $my_uri_query);
			parse_str($base_uri['query'], $base_uri_query);
			foreach ($my_uri_query AS $key => $value) {
				if (!empty($base_uri_query[$key]) AND $base_uri_query[$key] === $value) {
					unset($my_uri_query[$key]);
				}
			}
			unset($base_uri_query);
			$url['qs_zzform'] = http_build_query($my_uri_query);
			if ($url['qs_zzform']) $url['qs_zzform'] = '&'.$url['qs_zzform'];
		} else {
			$url['qs_zzform'] = '';
		}
	} elseif (!empty($my_uri['query'])) {
		$url['qs_zzform'] = '?'.$my_uri['query'];
	} else {
		$url['qs_zzform'] = '';
	}

	if (wrap_setting('zzform_host_base') AND str_starts_with($url['self'], '/'))
		$url['self'] = $url['base'].$url['self'];
	return $url;
}

/**
 * checks if there is a parameter in the URL (where, add, filter) that
 * results in a WHERE condition applied to the main SQL query
 *
 * @param array $zz ('where', like in $_GET)
 *		array 'where_condition' (conditions set by where, add and filter),
 *		array 'zz_fields' (values for fields depending on where conditions)
 * @return bool
 */
function zz_get_where_conditions(&$zz) {
	// WHERE: Add with suggested values
	$zz['where_condition']['list+record'] = zz_check_get_array('where', 'field_name');
	if (!empty($zz['where'])) {
		// $zz['where'] will be merged to $_GET['where'], identical keys
		// will be overwritten
		$zz['where_condition']['list+record'] = array_merge(
			$zz['where_condition']['list+record'], $zz['where']
		);
	}

	// ADD: overwrite write_once with values, in case there are identical fields
	// use add conditions only for record
	$zz['where_condition']['record'] = zz_check_get_array('add', 'field_name', [], false);
	if (!$zz['where_condition']['record'] AND !empty($_POST['zz_fields'])
		AND (empty($_POST['zz_action']) OR $_POST['zz_action'] === 'insert') // empty => coming from 'details'
		AND !empty($zz['add'])) {
		$error_fieldname = '';
		foreach ($zz['add'] as $addwhere) {
			if (!array_key_exists($addwhere['field_name'], $_POST['zz_fields'])) continue;
			$error_fieldname = $addwhere['field_name'];
			if ($_POST['zz_fields'][$addwhere['field_name']].'' !== $addwhere['value'].'') continue;
			$zz['where_condition']['record'][$addwhere['field_name']] = $addwhere['value'];
		}
		if (!$zz['where_condition']['record']) {
			$error_value = $error_fieldname ? $_POST['zz_fields'][$error_fieldname] : '';
			// illegal add here, quit 403
			zz_error_log([
				'msg' => 'Adding value %s in field %s is forbidden here',
				'msg_args' => [$error_value, $error_fieldname],
				'level' => E_USER_WARNING,
				'status' => 403
			]);
			zz_error_exit(true);
			return false;
		}
		// dependent fields?
		foreach ($zz['fields'] as $no => $field) {
			// @todo add support for fields appearing in subtables if needed
			if (empty($field['dependent_on_add_field'])) continue;
			if (empty($field['dependent_on_add_sql'])) continue;
			if (!array_key_exists($field['dependent_on_add_field'], $zz['where_condition']['record'])) continue;
			$sql = sprintf($field['dependent_on_add_sql'], $zz['where_condition']['record'][$field['dependent_on_add_field']]);
			$value = zz_db_fetch($sql, '', 'single value');
			if (!$value) continue;
			$zz['where_condition']['record'][$field['field_name']] = $value;
		}
	}

	// FILTER: check if there's a 'where'-filter
	foreach ($zz['filter'] AS $index => $filter) {
		if (!empty($filter['where'])
			AND !empty($zz['where_condition']['list+record'])
			AND in_array($filter['where'], array_keys($zz['where_condition']['list+record']))
		) {
			// where-filter makes no sense since already one of the values
			// is filtered by WHERE filter
			// unless it is an add where_condition
			if (!in_array($filter['where'], array_keys($zz['where_condition']['record']))) {
				unset($zz['filter'][$index]);
			}
		}
		if ($filter['type'] !== 'where') continue;
		if (!empty($zz['filter_active'][$filter['identifier']])) {
			$zz['where_condition']['list+record'][$filter['where']] 
				= $zz['filter_active'][$filter['identifier']];
		}
		// 'where'-filters are beyond that 'list'-filters
		$zz['filter'][$index]['type'] = 'list';
	}

	if ($zz['where_condition']['record']) {
		foreach ($zz['where_condition']['record'] as $key => $value) {
			if (in_array($value, ['NULL', '!NULL'])) continue;
			$zz['record']['zz_fields'][$key]['value'] = $value;
			$zz['record']['zz_fields'][$key]['type'] = 'hidden';
		}
	}

	return true;
}

/**
 * check $_GET array if user input is valid
 *
 * @param string $key
 * @param string $type
 *		'field_name': check if there's something like a field_name as subkey
 *		'values': check against $values if value is valid
 *		'is_numeric': checks if value is numeric
 *		'is_int': checks if value is integer / string that looks like integer
 * @param array $values (optional) list of possible values
 * @param bool $exit_on_error defaults to true
 * @return mixed
 * @todo use this function in more places
 */
function zz_check_get_array($key, $type, $values = [], $exit_on_error = true) {
	global $zz_conf;
	$return = $type === 'field_name' ? [] : '';
	if (!isset($_GET[$key])) return $return;

	$error_in = [];
	switch ($type) {
	case 'field_name':
		if (!is_array($_GET[$key])) {
			$error_in[$key] = true;
			break;
		}
		foreach ($_GET[$key] AS $name => $value) {
			$correct = true;
			if (strstr($name, ' ')) $correct = false;
			elseif (strstr($name, ';')) $correct = false;
			if (!$correct) $error_in[$key][$name] = true;
		}
		break;
	case 'values':
		if (!in_array($_GET[$key], $values)) $error_in[$key] = true;
		break;
	case 'is_int':
		$intval = intval($_GET[$key]).'';
		if ($intval !== $_GET[$key]) $error_in[$key] = true;
		break;
	case 'is_numeric':
		if (!is_numeric($_GET[$key])) $error_in[$key] = true;
		break;
	}
	if (!$error_in) return $_GET[$key];
	if (!$exit_on_error) return $return;

	wrap_static('page', 'status', 404);
	$unwanted_keys = [];
	foreach ($error_in as $key => $values) {
		if (is_array($values)) {
			foreach (array_keys($values) as $subkey) {
				$unwanted_keys = $key.'['.$subkey.']';
				unset($_GET[$key][$subkey]);
			}
		} else {
			$unwanted_keys[] = $key;
			unset($_GET[$key]);
		}
	}
	$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
		$zz_conf['int']['url']['qs_zzform'], $unwanted_keys
	);
	if (!isset($_GET[$key])) return $return;
	return $_GET[$key];
}

/** 
 * Sets record specific configuration variables that might be changed 
 * individually
 * 
 * @param array $zz
 * @global array $zz_conf
 * @return array $zz_conf_record subset of $zz
 */
function zz_record_conf($zz) {
	global $zz_conf;
	$zz_conf_record = [];

	// read access from $zz_conf
	$zz_conf_record['access'] = $zz_conf['int']['access'] ?? '';

	// read some keys from $zz['record']
	$keys = ['edit', 'delete', 'add', 'view', 'copy', 'no_ok', 'cancel_link'];
	foreach ($keys as $key)
		$zz_conf_record[$key] = $zz['record'][$key] ?? false;

	// read some keys from $zz
	$keys = ['if', 'unless', 'details'];
	foreach ($keys as $key)
		$zz_conf_record[$key] = $zz[$key] ?? [];

	// replace if[record] with if
	if ($zz_conf_record['if']) {
		foreach ($zz_conf_record['if'] as $no => $condition) {
			if (empty($condition['record'])) continue;
			unset($zz_conf_record['if'][$no]['record']);
			$zz_conf_record['if'][$no] += $condition['record'];
		}
	}
	return $zz_conf_record;
}

/**
 * initalize a variable from .cfg file
 *
 * @param string $key
 * @param array $ext existing definition by user
 * @param array $int existing internal definition by system (optional)
 * @return array
 */
function zz_init_cfg($cfg_key, $ext, $int = []) {
	$cfg = zz_init_cfg_file($cfg_key);
	$settings = [];
	foreach ($cfg as $key => $def) {
		// ignore deprecated keys
		if (!empty($def['deprecated'])) continue;
		// just for completeness?
		if (!empty($def['no_init'])) continue;
		// value?
		if (empty($def['type'])) $def['type'] = 'text';
		$value = zz_init_cfg_value($key, $def, $ext, $int);
		if (!$value AND !empty($def['no_auto_init'])) continue;
		$new_settings = wrap_setting_key($key, $value);
		$settings = wrap_array_merge($settings, $new_settings, false);
	}
	// variables in $ext left?
	zz_init_cfg_unused($cfg_key, $ext, $settings);
	return $settings;
}

/**
 * rewrite deprecated variables from .cfg file
 *
 * @param string $key
 * @param array $ext existing definition by user
 * @return array
 * @todo $zz_conf['if'][1]['add'] is not re-written, as is $zz_conf['if'][1]['search']
 */
function zz_init_cfg_deprecated($cfg_key, &$ext) {
	$cfg = zz_init_cfg_file($cfg_key);
	$settings = [];
	foreach ($cfg as $key => $def) {
		if (empty($def['deprecated'])) continue;
		if (empty($def['type'])) $def['type'] = 'text';
		$value = zz_init_cfg_value($key, $def, $ext);
		if ($value === NULL) continue;
		if (!empty($def['moved_to_zz'])) {
			$new_key = $def['moved_to_zz'] === '1' ? $key : $def['moved_to_zz'];
			$settings = wrap_array_merge($settings, wrap_setting_key($new_key, $value));
			// @todo better display of error message for arrays
			wrap_error(wrap_text(
				'Deprecated notation $%s["%s"] for  found. Please use $zz["%s"] instead.'
				, ['values' => [$cfg_key, $key, $new_key]]
			), E_USER_DEPRECATED);
		} elseif (!empty($def['moved_to_setting'])) {
			$new_key = $def['moved_to_setting'] === '1' ? $key : $def['moved_to_setting'];
			// invert_setting
			if (!empty($def['invert_setting'])) $value = !$value;
			if (empty($def['ignore_if_true']) OR $value !== true) {
				if (is_array(wrap_setting($new_key)) AND $value)
					wrap_setting_add($new_key, $value);
				else
					wrap_setting($new_key, $value);
			}
			wrap_error(wrap_text(
				'Deprecated notation $%s["%s"] found. Please use wrap_setting("%s") instead.'
				, ['values' => [$cfg_key, $key, $new_key]]
			), E_USER_DEPRECATED);
		}
		// @todo support keys inside arrays
		unset($ext[$key]);
	}
	return $settings;
}

/**
 * check for unused (mistyped?) keys in definition
 *
 * @param string $cfg_key
 * @param array $values
 * @param array $settings
 * @return void
 */
function zz_init_cfg_unused($cfg_key, $values, $settings) {
	foreach ($values as $key => $value) {
		if ($key === '*') continue; // might come from brick forms, merging local_settings
		if (is_array($value)) zz_init_cfg_unused($cfg_key.'["'.$key.'"]', $value, $settings[$key] ?? []);
		if (array_key_exists($key, $settings)) continue;
		if (!empty($values['init_ignore_log']) AND in_array($key, $values['init_ignore_log'])) continue;
		wrap_error(wrap_text('Key $%s["%s"] is set, but not used.', ['values' => [$cfg_key, $key]]), E_USER_NOTICE);
	}
}

/**
 * get configuration file
 *
 * @param string $key
 * @return array
 */
function zz_init_cfg_file($key) {
	if (strstr($key, '[')) {
		$keys = rtrim($key, ']');
		$keys = explode('[', $keys);
		// own cfg file
		$cfg = wrap_cfg_files(implode('-', $keys), ['package' => 'zzform']);
		if (!$cfg) {
			// subset of main file
			$cfg = [];
			$cfg_complete = wrap_cfg_files($keys[0], ['package' => 'zzform']);
			if (!$cfg_complete) return [];
			foreach ($cfg_complete as $cfg_key => $def) {
				if (!str_starts_with($cfg_key, $keys[1].'[')) continue;
				$cfg_keys = wrap_setting_key_array($cfg_key);
				$cfg_key = '';
				foreach ($cfg_keys as $index => $sub_key)
					if ($index === 0) continue;
					elseif ($index === 1) $cfg_key .= $sub_key;
					else $cfg_key .= sprintf('[%s]', $sub_key);
				$cfg[$cfg_key] = $def;
			}
		}
	} else {
		$cfg = wrap_cfg_files($key, ['package' => 'zzform']);
	}
	return $cfg;
}

/**
 * get configuration value
 *
 * @param string $key
 * @param array $def definition of a single config key
 * @param array $ext
 * @param array $int
 * @return mixed
 */
function zz_init_cfg_value($key, $def, $ext, $int = []) {
	// get values from array
	$ext = zz_init_cfg_array_value($key, $ext);
	$int = zz_init_cfg_array_value($key, $int);
	// get value, in order int, ext, default
	if ($int)
		$value = $int;
	elseif (isset($ext) AND (empty($def['scope']) OR !in_array('internal', $def['scope'])))
		$value = $ext;
	elseif (isset($def['default']))
		$value = $def['default'];
	elseif (!empty($def['list']))
		$value = [];
	else
		switch ($def['type']) {
		case 'list':
			$value = [];
			break;
		case 'int':
		case 'text':
		default:
			$value = NULL; break;
		}
	
	// array?
	if (!empty($def['list']) AND !is_array($value))
		if ($value) $value = [$value];
		else $value = [];

	// check values if they match type
	switch ($def['type']) {
	case 'enum':
		if (!in_array($value, $def['enum'])) $value = $def['default'] ?? '';
		break;
	}

	return $value;
}

/**
 * get value from array, key might be footer[text], look for $values['footer']['text']
 *
 * @param string $key
 * @param array $value
 * @return mixed
 */
function zz_init_cfg_array_value($key, $values) {
	if (!strstr($key, '[')) return $values[$key] ?? NULL;
	$keys = rtrim($key, ']');
	$keys = explode('[', $keys);
	$value = $values;
	foreach ($keys as $key) {
		$key = rtrim($key, ']');
		$value = $value[$key] ?? NULL;
	}
	return $value;
}

/**
 * compares if needle is in haystack by comparison as string
 *
 * @param string $needle
 * @param array $haystack
 * @return mixed string: needle was found in haystack, false: not found
 */
function zz_in_array_str($needle, $haystack) {
	$found = false;
	foreach ($haystack AS $a_needle) {
		if ($a_needle.'' !== $needle.'') continue;
		return $a_needle;
	}
	return false;
}

/**
 * get and apply where conditions to SQL query and fields
 *
 * @param array $zz
 * @return bool
 */
function zz_where_conditions(&$zz) {
	global $zz_conf;

	// get 'where_conditions' for SQL query from GET add, filter oder where
	// get 'zz_fields' from GET add
	zz_get_where_conditions($zz);

	// apply where conditions to SQL query
	$zz['sql_without_where'] = $zz['sql'];
	$zz['record']['where'] = [];
	zz_apply_where_conditions($zz);
	if ($zz['record']['where']) {
		// shortcout sqlcount is no longer possible
		$zz['sqlcount'] = NULL;
	}

	// where with unique ID: remove filters, they do not make sense here
	// (single record will be shown)
	if ($zz_conf['int']['where_with_unique_id']) {
		$zz['filter'] = [];
		$zz['filter_active'] = [];
	}

	// if GET add already set some values, merge them to field
	// definition
	foreach (array_keys($zz['fields']) as $no) {
		if (empty($zz['fields'][$no]['field_name'])) continue;
		if (empty($zz['record']['zz_fields'][$zz['fields'][$no]['field_name']])) continue;
		// get old type definition and use it as type_detail if not set
		if (empty($zz['fields'][$no]['type_detail'])) {
			$zz['fields'][$no]['type_detail'] = $zz['fields'][$no]['type'];
		}
		$zz['fields'][$no] = array_merge($zz['fields'][$no], 
			$zz['record']['zz_fields'][$zz['fields'][$no]['field_name']]);
	}

	return true;
}

/**
 * applies where conditions to get different sql query, id values and some
 * further variables for nice headings etc.
 *
 * @param array $zz
 *		'where_condition' from zz_get_where_conditions()
 *		change:
 *		string $sql = modified main query (if applicable)
 *			'where', 'where_condition', 'id', 
 * @global array $zz_conf checks for 'modules'['debug']
 *		change: 'where_with_unique_id'
 *		int[unique_fields]
 * @return bool
 * @see zz_get_where_conditions(), zz_get_unique_fields()
 */
function zz_apply_where_conditions(&$zz) {
	global $zz_conf;
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	$table_for_where = $zz['table_for_where'] ?? [];

	$sql_keys['list'] = 'sql';
	$sql_keys['record'] = 'sqlrecord';

	// set some keys
	$zz_conf['int']['where_with_unique_id'] = false;

	if (empty($zz['where_condition']))
		return zz_return(true);

	foreach ($zz['where_condition'] as $area => $where_condition) {
		foreach ($where_condition as $field_name => $value) {
			$submitted_field_name = $field_name;
			if (preg_match('/[a-z_]+\(.+\)/i', trim($field_name))) {
				// check if field_name comprises some function
				// CONCAT(bla, blubb), do not change this
				$table_name = '';
			} elseif (strstr($field_name, '.')) {
				// check if field_name comprises table_name
				$field_tab = explode('.', $field_name);
				$table_name = wrap_db_escape($field_tab[0]);
				$field_name = wrap_db_escape($field_tab[1]);
				unset($field_tab);
			} else {
				// allows you to set a different (or none at all) table name 
				// for WHERE queries
				if (isset($table_for_where[$field_name]))
					$table_name = $table_for_where[$field_name];
				else
					$table_name = $zz['table'];
				$field_name = wrap_db_escape($field_name);
			}
			$field_reference = $table_name ? $table_name.'.'.$field_name : $field_name;
			// restrict list view to where, but not to add
			foreach ($sql_keys as $type => $sql_key) {
				if (empty($_GET['add'][$submitted_field_name])) {
					if (!empty($zz['where_condition']['list+record'][$field_name])
						AND $zz['where_condition']['list+record'][$field_name] === 'NULL')
					{
						$zz[$sql_key] = wrap_edit_sql($zz[$sql_key], 'WHERE', 
							sprintf('ISNULL(%s)', $field_reference)
						);
						continue; // don't use NULL as where variable!
					} elseif (!empty($zz['where_condition']['list+record'][$field_name])
						AND $zz['where_condition']['list+record'][$field_name] === '!NULL')
					{
						$zz[$sql_key] = wrap_edit_sql($zz[$sql_key], 'WHERE', 
							sprintf('NOT ISNULL(%s)', $field_reference)
						);
						continue; // don't use !NULL as where variable!
					} elseif (strstr($area, $type)) {
						$zz[$sql_key] = wrap_edit_sql($zz[$sql_key], 'WHERE', 
							sprintf('%s = "%s"', $field_reference, wrap_db_escape($value))
						);
					}
				}
			}

			$zz['record']['where'][$table_name][$field_name] = $value;

			// if table row is affected by where, mark this
			if ($zz['table'] === $table_name) {
				// just for main table
				foreach ($zz['fields'] as $no => $field) {
					if (empty($field['field_name'])) continue;
					if ($field['field_name'] !== $field_name) continue;
					if (isset($zz['fields'][$no]['class']) AND !is_array($zz['fields'][$no]['class']))
						$zz['fields'][$no]['class'] = [$zz['fields'][$no]['class']];
					$zz['fields'][$no]['class'][] = 'where';
				}
			}

			if ($field_name === $zz_conf['int']['id']['field_name']) {
				if (intval($value).'' === $value.'') {
					$zz_conf['int']['where_with_unique_id'] = true;
					$zz_conf['int']['id']['value'] = $value;
				} else {
					$zz_conf['int']['id']['invalid_value'] = $value;
				}
			} elseif (in_array($field_name, $zz_conf['int']['unique_fields'])) {
				$zz_conf['int']['where_with_unique_id'] = true;
			}
		}
	}
	// in case where is not combined with ID field but UNIQUE field
	// (e. g. identifier with UNIQUE KEY) retrieve value for ID field from 
	// database
	if (!$zz_conf['int']['id']['value'] AND $zz_conf['int']['where_with_unique_id']) {
		if (wrap_setting('debug')) zz_debug('where_conditions', $zz['sql']);
		$line = zz_db_fetch($zz['sql'], '_dummy_', 'numeric', 'WHERE; ambiguous values in ID?');
		// 0 (=add) or 1 records: 'where_with_unique_id' remains true
		if (count($line) === 1 AND !empty($line[0][$zz_conf['int']['id']['field_name']])) {
			$zz_conf['int']['id']['value'] = $line[0][$zz_conf['int']['id']['field_name']];
		} elseif (count($line)) {
			$zz_conf['int']['where_with_unique_id'] = false;
		}
	}
	
	return zz_return(true);
}

/**
 * WHERE, 2nd part, write_once without values
 * 
 * write_once will be checked as well, without values
 * where is more important than write_once, so remove it from array if
 * there is a where equal to write_once
 * @param array $zz
 * @return bool
 */
function zz_write_onces(&$zz) {
	foreach ($zz['fields'] as $field) {
		// get write once fields so we can base conditions (scope=values) on them
		if (empty($field['type'])) continue;
		if ($field['type'] !== 'write_once') continue;
		$field_name = $field['field_name'];
		if (!empty($zz['where_condition']['list+record'][$field_name])) continue;

		$zz['where_condition']['list+record'][$field_name] = '';
		$zz['record']['where'][$zz['table']][$field_name] = '';
	}
	return true;
}

/** 
 * Fills field definitions with default definitions and infos from database
 * 
 * @param array $fields
 * @param string $db_table [i. e. db_name.table]
 * @param bool $multiple_times marker for conditions
 * @param string $mode (optional, $ops['mode'])
 * @param string $action (optional, $zz['record']['action'])
 * @param int $subtable_no number of subtable in definition
 * @return array $fields
 */
function zz_fill_out($fields, $db_table, $multiple_times = false, $mode = false, $action = false, $subtable_no = false) {
	if (wrap_setting('debug')) {
		zz_debug('start', __FUNCTION__.$multiple_times);
	}
	static $defs = [];
	$hash = md5(serialize($fields).$db_table.$multiple_times.$mode.$subtable_no);
	if (!empty($defs[$hash])) return zz_return($defs[$hash]);

	$to_translates = [
		'explanation', 'explanation_top', 'title_append', 'title_tab'
	];

	foreach (array_keys($fields) as $no) {
		if (!empty($fields[$no]['if'])) {
			if ($multiple_times === 1) {
				 // if there are only conditions, go on
				if (count($fields[$no]) === 1) continue;
			}
		}
		if (!$fields[$no]) {
			// allow placeholder for fields to get them into the wanted order
			unset($fields[$no]);
			continue;
		}
		$fields[$no]['field_no'] = $no;
		$fields[$no]['subtable_no'] = $subtable_no;
		if (!isset($fields[$no]['type'])) {
			// default type: text
			$fields[$no]['type'] = 'text';
		}
		$fields[$no]['title'] = zz_field_title($fields[$no]);
		if (empty($fields[$no]['class'])) $fields[$no]['class'] = [];
		elseif (!is_array($fields[$no]['class'])) $fields[$no]['class'] = [$fields[$no]['class']];

		if (empty($fields[$no]['translated'])) {
			// translate fieldnames, if set
			foreach ($to_translates as $to_translate) {
				if (empty($fields[$no][$to_translate])) continue;
				$fields[$no][$to_translate] = wrap_text($fields[$no][$to_translate]);
			}
			$fields[$no]['translated'] = true;
		}

		if (!isset($fields[$no]['explanation'])) {
			$fields[$no]['explanation'] = '';
		}
		if (!empty($fields[$no]['explanation_vars'])) {
			if (!is_array($fields[$no]['explanation_vars']))
				$fields[$no]['explanation_vars'] = [$fields[$no]['explanation_vars']];
			$fields[$no]['explanation'] = vsprintf($fields[$no]['explanation'], $fields[$no]['explanation_vars']);
		}
		if (!$multiple_times) {
			if (!empty($fields[$no]['sql'])) // replace whitespace with space
				$fields[$no]['sql'] = preg_replace("/\s+/", " ", $fields[$no]['sql']);
		}

		// settings depending on field type
		switch ($fields[$no]['type']) {
		case 'option':
			// do not show option-fields in tab
			$fields[$no]['hide_in_list'] = true;
			// makes no sense to export a form field
			$fields[$no]['export'] = false;
			// format option-fields with CSS
			if (!in_array('option', $fields[$no]['class'])) {
				$fields[$no]['class'][] = 'option';
			}
			break;
		}		
		
		$type = $fields[$no]['type_detail'] ?? $fields[$no]['type'];
		switch ($type) {
		case 'write_once':
			// would not be here if it had a type_detail set
			$fields[$no]['type_detail'] = 'text';
			break;
		case 'id':
			// set dont_sort as a default for ID columns
			if (!isset($fields[$no]['dont_sort'])) $fields[$no]['dont_sort'] = true;
			// hide empty ID fields on add
			if ($mode === 'add') $fields[$no]['hide_in_form'] = true;
			break;
		case 'memo':
			$fields[$no]['class'][] = 'hyphenate';
			break;
		case 'subtable':
		case 'foreign_table':
			if (empty($fields[$no]['subselect']) AND !isset($fields[$no]['export'])) {
				// subtables have no output by default unless there is a subselect
				// definition; however in rare cases (e. g. with a condition set)
				// you might want to overwrite this manually
				$fields[$no]['export'] = false;
			}
			// for subtables, do this as well; here we still should have a
			// different db_name in 'table' if using multiples dbs so it's no
			// need to prepend the db name of this table
			if (empty($fields[$no]['table_name'])) {
				$fields[$no]['table_name'] = $fields[$no]['table'];
			}
			$fields[$no]['fields'] = zz_fill_out(
				$fields[$no]['fields'], $fields[$no]['table'], $multiple_times,
				$mode, $action, !empty($fields[$no]['subtable_no']) ? $fields[$no]['subtable_no'].'-'.$no : $no
			);
			break;
		case 'translation_key':
		case 'foreign_key':
			// these do not exist in main record
			if ($subtable_no) break;
			if (!empty($fields[$no]['type_detail'])) {
				$fields[$no]['type'] = $fields[$no]['type_detail'];
			} else {
				$fields[$no]['type'] = 'number';
			}
			break;
		case 'captcha':
			if (!empty($action) AND $action !== 'insert') {
				unset($fields[$no]);
				continue 2;
			}
			if (!empty($mode) AND $mode !== 'add') {
				unset($fields[$no]);
				continue 2;
			}
			$fields[$no]['hide_in_list'] = true;
			$fields[$no]['export'] = false;
			break;
		case 'password':
		case 'password_change':
			if (!isset($fields[$no]['minlength'])) $fields[$no]['minlength'] = 8;
			if (!isset($fields[$no]['maxlength'])) $fields[$no]['maxlength'] = 60;
			break;
		case 'upload_image':
			$fields[$no]['upload_max_filesize'] = zz_upload_max_filesize($fields[$no]['upload_max_filesize'] ?? 0);
			break;
		case 'select':
			if (!isset($fields[$no]['max_select']))
				$fields[$no]['max_select'] = wrap_setting('zzform_max_select');
			if (!isset($fields[$no]['max_select_val_len']))
				$fields[$no]['max_select_val_len'] = wrap_setting('zzform_max_select_val_len');
			if (!isset($fields[$no]['key_field_name']) AND str_ends_with($fields[$no]['field_name'], '_id')
				AND substr_count($fields[$no]['field_name'], '_') > 1) {
				$pos = strrpos(substr($fields[$no]['field_name'], 0, -3), '_') + 1;
				$fields[$no]['key_field_name'] = substr($fields[$no]['field_name'], $pos);
			}
			break;
		case 'time':
		case 'datetime':
			if (empty($fields[$no]['time_format']))
				$fields[$no]['time_format'] = 'H:i';
			break;
		case 'identifier':
			if (!empty($fields[$no]['conf_identifier'])) {
				$fields[$no]['identifier'] = $fields[$no]['conf_identifier'];
				wrap_error('Deprecated: Use key `identifier` instead of `conf_identifier`', E_USER_DEPRECATED);
			}
			break;
		}

		if (in_array($mode, ['add', 'edit', 'revise']) OR in_array($action, ['insert', 'update'])) {
			if (!isset($fields[$no]['maxlength'])) {
				if (isset($fields[$no]['field_name'])) {
					// no need to check maxlength in list view only 
					zz_db_field_maxlength($fields[$no], $db_table);
				} else {
					$fields[$no]['maxlength'] = 32;
				}
			}
			$fields[$no]['required'] = zz_fill_out_required($fields[$no], $db_table);
		} else {
			if (!isset($fields[$no]['maxlength'])) $fields[$no]['maxlength'] = 0;
			if (!isset($fields[$no]['required'])) $fields[$no]['required'] = false;
		}
		// save 'required' status for validation of subrecords as well,
		// where required attribute might be set to false
		$fields[$no]['required_in_db'] = $fields[$no]['required'];
	}
	$defs[$hash] = $fields;
	return zz_return($fields);
}

/**
 * sets attribute 'required' to fields which do not have one yet
 * depending on field type, NULL and null_string
 *
 * @param array $field field definition from $zz['fields'][$no]
 * @param string $db_table [i. e. db_name.table]
 * @return bool true: field is required, false: field is optional
 */
function zz_fill_out_required($field, $db_table) {
	if (!empty($field['required'])) return true;
	if (isset($field['required'])) return false;
	// might be empty string
	if (!empty($field['null_string'])) return false;
	// no field name = not in database
	if (empty($field['field_name'])) return false;
	// might be NULL
	if (zz_db_field_null($field['field_name'], $db_table)) return false;
	// some field types never can be required
	$never_required = [
		'calculated', 'display', 'option', 'image', 'foreign', 'subtable', 'foreign_table'
	];
	if (in_array($field['type'], $never_required)) return false;

	return true;
}

/**
 * return field title
 *
 * @param array $field
 * @return string
 */
function zz_field_title($field) {
	static $translations = [];

	// title exists, translate if not already done
	if (isset($field['title'])) {
		$title = $field['title'];
		if (in_array($title, $translations)) return $title;
		$title = wrap_text($title);
		$translations[] = $title;
		return $title;
	}
	
	// title will be created from field_name, translate it
	if (!isset($field['field_name'])) {
		wrap_error(sprintf(
			'zzform field definition incorrect, field has neither field_name nor title: %s'
			, json_encode($field, JSON_PRETTY_PRINT)
		), E_USER_ERROR);
	}
	$title = ucfirst($field['field_name']);
	$title = str_replace('_ID', ' ', $title);
	$title = str_replace('_id', ' ', $title);
	$title = str_replace('_', ' ', $title);
	$title = rtrim($title);
	$title = wrap_text($title);
	$translations[] = $title;
	return $title;
}

/**
 * get a unique hash for a specific set of table definition ($zz) and
 * configuration ($zz_conf) to be able to save time for zzform_multi() and
 * to get a possible hash for a secret key
 *
 * @param array $zz
 * @param array $zz_conf
 * @return string $hash
 * @todo check if $_GET['id'], $_GET['where'] and so on need to be included
 */
function zz_hash($zz, $zz_conf) {
	// get rid of varying and internal settings
	// get rid of configuration settings which are not important for
	// the definition of the database table(s)
	$id = $zz_conf['id'];
	$uninteresting_zz_conf_keys = [
		'int', 'id', 'format', 'access', 'limit', 'url_self'
	];
	foreach ($uninteresting_zz_conf_keys as $key) unset($zz_conf[$key]);
	$uninteresting_zz_keys = [
		'title', 'explanation', 'explanation_top', 'subtitle', 'list', 'access',
		'explanation_insert', 'export', 'details', 'footer', 'page'
	];
	foreach ($uninteresting_zz_keys as $key) unset($zz[$key]);
	foreach ($zz['fields'] as $no => $field) {
		// defaults might change, e. g. dates
		if (isset($field['default'])) unset($zz['fields'][$no]['default']);
		if (!empty($field['type']) AND in_array($field['type'], ['subtable', 'foreign_table'])) {
			foreach ($field['fields'] as $sub_no => $sub_field) {
				if (isset($sub_field['default'])) unset($zz['fields'][$no]['fields'][$sub_no]['default']);
			}
		}
		// @todo remove if[no][default] too
	}
	$my['zz'] = $zz;
	$my['zz_conf'] = $zz_conf;
	$hash = sha1(serialize($my));
	zz_secret_id('write', $id, $hash);
	return $hash;
}

/**
 * hash a secret key and make it small
 *
 * @param int @id
 * @global string $zz_conf['int']['hash']
 * @return string
 */
function zz_secret_key($id) {
	global $zz_conf;
	$hash = sha1($zz_conf['int']['hash'].$id);
	$hash = wrap_base_convert($hash, 16, 62);
	return $hash;
}

/**
 * gets unique and id fields for further processing
 *
 * @param array $fields
 * @return bool
 */
function zz_get_unique_fields($fields) {
	global $zz_conf;

	$zz_conf['int']['id']['value'] = false;
	$zz_conf['int']['id']['field_name'] = false;
	$zz_conf['int']['unique_fields'] = []; // for WHERE

	foreach ($fields AS $field) {
		// set ID fieldname
		if (!empty($field['type']) AND $field['type'] === 'id') {
			if ($zz_conf['int']['id']['field_name']) {
				zz_error_log(['msg' => 'Only one field may be defined as `id`!']);
				return false;
			}
			$zz_conf['int']['id']['field_name'] = $field['field_name'];
		}
		if (!empty($field['unique']) AND !is_array($field['unique'])) {
			// 'unique' might be array for subtables
			$zz_conf['int']['unique_fields'][] = $field['field_name'];
		}
	}
	return true;
}

/**
 * sets some $zz-definitions for records depending on existing definition for
 * translations, subtabes, uploads, write_once-fields
 *
 * changes in 'subtables', 'save_old_record', some minor 'fields' 
 * @param array $fields = $zz['fields']
 * @return bool 
 */
function zz_set_fielddefs_for_record(&$zz) {
	$tab = 1;
	foreach (array_keys($zz['fields']) as $no) {
		// translations
		if (!empty($zz['fields'][$no]['translate_field_index'])) {
			$t_index = $zz['fields'][$no]['translate_field_index'];
			if (isset($zz['fields'][$t_index]['translation'])
				AND !$zz['fields'][$t_index]['translation']) {
				unset ($zz['fields'][$no]);
				continue;
			}
		}
		if (!isset($zz['fields'][$no]['type'])) continue;
		switch ($zz['fields'][$no]['type']) {
		case 'subtable':
		case 'foreign_table':
			// save number of subtable, get table_name and check whether sql
			// is unique, look for upload form as well
			$zz['record']['subtables'][$tab] = $no;
			if (!isset($zz['fields'][$no]['table_name']))
				$zz['fields'][$no]['table_name'] = $zz['fields'][$no]['table'];
			$zz['fields'][$no]['subtable'] = $tab;
			$tab++;
			if (!empty($zz['fields'][$no]['sql_not_unique'])) {
				// must not change record where main record is not directly 
				// superior to detail record 
				// - foreign ID would be changed to main record's id
				$zz['fields'][$no]['access'] = 'show';
			}
			foreach ($zz['fields'][$no]['fields'] as $subno => $subfield) {
				if (empty($subfield['type'])) continue;
				switch ($subfield['type']) {
				case 'upload_image':
					$zz['record']['upload_form'] = true;
					break;
				case 'subtable': 
					$zz['record']['subtables'][$tab] = $no.'-'.$subno;
					if (!isset($subfield['table_name']))
						$zz['fields'][$no]['fields'][$subno]['table_name'] = $subfield['table'];
					$zz['fields'][$no]['fields'][$subno]['subtable'] = $tab;
					$tab++;
					break;
				}
			}
			break;
		case 'upload_image':
			$zz['record']['upload_form'] = true;
			break;
		case 'write_once':
		case 'display':
			$zz['record']['save_old_record'][] = $no;
			break;
		}
	}
	return true;
}

/** 
 * Sets $ops['mode'], $zz['record']['action'] and several $zz_conf-variables
 * according to what the user request and what the user is allowed to request
 * 
 * @param array $zz
 * @param array $zz_conf
 *		int['record'], 'access' etc. pp.
 *		'modules'[debug]
 *		'where_with_unique_id' bool if it's just one record to be shown (true)
 * @global array $zz_conf
 * @return array 
 *		$zz array
 *		$ops array
 */
function zz_record_access($zz, $ops) {
	global $zz_conf;

	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	// initialize variables
	$create_new_zzform_secret_key = true;
	if (!empty($_POST['zz_id'])) {
		$zz_conf['id'] = zz_check_id_value($_POST['zz_id']);
		$zz_conf['int']['secret_key'] = zz_secret_id('read');
		if ($zz_conf['int']['secret_key']) {
			$create_new_zzform_secret_key = false;
			require_once __DIR__.'/session.inc.php';
			$_FILES = zz_session_read('filedata', $_FILES);
		}
	}

	$zz['record']['action'] = false;		// action: what database operations are to be done
	$zz_conf['int']['record'] = true; // show record somehow (edit, view, ...)
	
	if (!empty($_POST['zz_action'])) {
		if (!in_array($_POST['zz_action'], wrap_setting('zzform_allowed_actions')))
			unset($_POST['zz_action']);
		$zz['record']['query_records'] = true;
	} elseif (!empty($zz_conf['int']['add_details_return'])) {
		$zz['record']['query_records'] = true;
	} else {
		$zz['record']['query_records'] = false;
	}
	
	// set mode and action according to $_GET and $_POST variables
	// do not care yet if actions are allowed
	$keys = [];

	switch (true) {
	case $ops['mode'] === 'export':
		// Export overwrites all
		$zz_conf['int']['access'] = 'export'; 	
		$zz_conf['int']['record'] = false;
		break;

	case isset($_POST['zz_subtables']):
		if (empty($zz_conf['int']['id']['value']) AND !empty($_POST[$zz_conf['int']['id']['field_name']])) {
			$zz_conf['int']['id']['value'] = $_POST[$zz_conf['int']['id']['field_name']];
		}
		// ok, no submit button was hit but only add/remove form fields for
		// detail records in subtable, so set mode accordingly (no action!)
		if (!empty($_POST['zz_action']) AND $_POST['zz_action'] === 'insert') {
			$ops['mode'] = 'add';
		} elseif (!empty($_POST['zz_action']) AND $_POST['zz_action'] === 'update'
			AND $zz_conf['int']['id']['value']) {
			$ops['mode'] = 'edit';
			$id_value = $zz_conf['int']['id']['value'];
		} else {
			// this should not occur if form is used legally
			$ops['mode'] = false;
		}
		if (!empty($_FILES)) {
			require_once __DIR__.'/session.inc.php';
			zz_session_write('filedata', $_FILES);
		}
		break;

	case isset($_GET['edit']):
		$ops['mode'] = 'edit';
		if ($zz_conf['int']['where_with_unique_id']) {
			$id_value = $zz_conf['int']['id']['value'];
		} else {
			$id_value = zz_check_get_array('edit', 'is_int');
		}
		break;

	case !empty($_GET['delete']):
		$ops['mode'] = 'delete';
		$id_value = zz_check_get_array('delete', 'is_int');
		break;

	case isset($_GET['delete']) AND $zz_conf['int']['where_with_unique_id']:
		$ops['mode'] = 'delete';
		$id_value = $zz_conf['int']['id']['value'];
		// was record already deleted?
		$record_id = wrap_db_fetch($zz['sqlrecord'], '_dummy_', 'single value');
		if (!$record_id) $ops['mode'] = 'show';
		break;

	case !empty($_GET['show']):
		$ops['mode'] = 'show';
		$id_value = zz_check_get_array('show', 'is_int');
		break;

	case !empty($_GET['revise']):
		$ops['mode'] = 'revise';
		$id_value = zz_check_get_array('revise', 'is_int');
		break;

	case isset($_GET['add']) AND empty($_POST['zz_action']):
		$ops['mode'] = 'add';
		if ($zz['record']['copy'])
			$zz_conf['int']['id']['source_value'] = zz_check_get_array('add', 'is_int', [], false);
		break;

	case !empty($_GET['mode']):
		// standard case, get mode from URL
		if (in_array($_GET['mode'], wrap_setting('zzform_allowed_modes'))) {
			$ops['mode'] = $_GET['mode']; // set mode from URL
			if (in_array($ops['mode'], ['edit', 'delete', 'show'])
				AND !empty($_GET['id'])) {
				$id_value = zz_check_get_array('id', 'is_int');
			}
		} else {
			// illegal parameter, don't set a mode at all
			wrap_static('page', 'status', 404);
			$keys = ['id', 'mode'];
		}
		break;

	case isset($_GET['delete']):
	case isset($_GET['insert']):
	case isset($_GET['update']):
	case isset($_GET['noupdate']):
		// last record operation was successful
		$ops['mode'] = 'show';
		if (isset($_GET['delete'])) $ops['mode'] = 'list_only';
		$keys = ['delete', 'insert', 'update', 'noupdate'];
		$found = 0;
		foreach ($keys as $key) {
			if (!isset($_GET[$key])) continue;
			if ($key !== 'delete') $id_value = zz_check_get_array($key, 'is_int');
			$found++;
		}
		if ($found > 1) {
			wrap_static('page', 'status', 404);
		}
		break;

	case !empty($_POST['zz_action']):
		if ($_POST['zz_action'] === 'multiple') {
			if (!empty($_POST['zz_record_id'])) {
				if (!empty($_POST['zz_multiple_edit'])) {
					$ops['mode'] = 'edit';
				} elseif (!empty($_POST['zz_multiple_delete'])) {
					$ops['mode'] = 'delete';
				}
				$zz_conf['int']['id']['values'] = $_POST['zz_record_id'];
			}
		} else {
			// triggers valid database action
			$zz['record']['action'] = $_POST['zz_action']; 
			if (!empty($_POST[$zz_conf['int']['id']['field_name']]))
				$id_value = $_POST[$zz_conf['int']['id']['field_name']];
			$ops['mode'] = false;
		}
		break;
	
	case !empty($_GET['thumbs']):
		$keys = ['thumbs', 'field'];
		$ops['mode'] = 'thumbnails';
		$id_value = zz_check_get_array('thumbs', 'is_int');
		if (empty($_GET['field'])) {
			wrap_static('page', 'status', 404);
			break;
		}
		break;

	case $zz_conf['int']['where_with_unique_id']:
		// just review the record
		if (!empty($zz_conf['int']['id']['value'])) $ops['mode'] = 'review'; 
		else $ops['mode'] = 'add';
		break;

	case !empty($_GET['field']):
		$keys = ['thumbs', 'field'];
		wrap_static('page', 'status', 404);
		break;

	default:
		// no record is selected, basic view when starting to edit data
		// list mode only
		$ops['mode'] = 'list_only';
		break;
	}
	
	if (wrap_static('page', 'status') === 404) {
		$id_value = false;
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string($zz_conf['int']['url']['qs_zzform'], $keys);
		$ops['mode'] = false;
		$zz_conf['int']['record'] = false;
	}

	// write main id value, might have been written by a more trustful instance
	// beforehands ($_GET['where'] etc.)
	if (empty($zz_conf['int']['id']['value']) AND !empty($id_value)) {
		if (!is_numeric($id_value)) {
			$zz_conf['int']['id']['invalid_value'] = $id_value;
		} else {
			$zz_conf['int']['id']['value'] = $id_value;
		}
	} elseif (!isset($zz_conf['int']['id']['value'])) {
		$zz_conf['int']['id']['value'] = '';
	}

	// now that we have the ID value, we can calculate the secret key
	if (!empty($zz_conf['int']['id']['values'])) {
		$idval = implode(',', $zz_conf['int']['id']['values']);
	} else {
		$idval = $zz_conf['int']['id']['value'];
	}
	if ($create_new_zzform_secret_key)
		$zz_conf['int']['secret_key'] = zz_secret_key($idval);

	// if conditions in $zz_conf['if'] -- check them
	// get conditions if there are any, for access
	$ops['list']['unchanged'] = []; // for old variables

	if (zz_modules('conditions')
		AND (!empty($zz['if']) OR !empty($zz['unless']))
		AND $zz_conf['int']['id']['value']) {
		$zz_conditions = zz_conditions_check($zz, $ops['mode']);
		// @todo do we need to check record conditions here?
		$zz_conditions = zz_conditions_record_check($zz, $ops['mode'], $zz_conditions);
		// save old variables for list view
		$saved_variables = [
			'access', 'details'
		];
		foreach ($saved_variables as $var) {
			if (!isset($zz[$var])) continue;
			$ops['list']['unchanged'][$var] = $zz[$var];
		}
		// overwrite new variables
		if (!empty($zz_conditions['bool'])) {
			zz_conditions_merge_conf($zz, $zz_conditions['bool'], $zz_conf['int']['id']['value']);
		}
	}

	// set (and overwrite if necessary) access variables, i. e.
	// $zz['record']['add'], $zz['record']['edit'], $zz['record']['delete']

	if ($zz_conf['int']['access'] === 'add_only' AND zz_valid_request('insert')) {
		$zz_conf['int']['access'] = 'show_after_add';
	}
	if ($zz_conf['int']['access'] === 'edit_only'
		AND zz_valid_request(['update', 'noupdate'])) {
		$zz_conf['int']['access'] = 'show_after_edit';
	}
	if ($zz_conf['int']['access'] === 'add_then_edit') {
		if ($zz_conf['int']['id']['value'] AND zz_valid_request()) {
			$zz_conf['int']['access'] = 'show+edit';
		} elseif ($zz_conf['int']['id']['value']) {
			$zz_conf['int']['access'] = 'edit_only';
		} else {
			$zz_conf['int']['access'] = 'add_only';
		}
	}
	
	if ($ops['mode'] === 'thumbnails') {
		$not_allowed = [
			'show', 'show_and_delete', 'edit_details_only', 'edit_details_and_add',
			'none', 'search_but_no_list', 'add_only', 'edit_only', 'add_then_edit',
			'show_after_add', 'show_after_edit', 'show+edit', 'forbidden'
		];
		$only_allowed_with_id = [
			'add_only', 'edit_only', 'add_then_edit'
		];
		if (!in_array($zz_conf['int']['access'], $not_allowed)) {
			$zz_conf['int']['access'] = 'thumbnails';
		} elseif (in_array($zz_conf['int']['access'], $only_allowed_with_id)
			AND $zz_conf['int']['where_with_unique_id']) {
			$zz_conf['int']['access'] = 'thumbnails';
		} elseif (in_array($zz_conf['int']['access'], $only_allowed_with_id)
			AND zz_valid_request()) {
			// e. g. public access
			$zz_conf['int']['access'] = 'thumbnails';
		} else {
			$zz_conf['int']['access'] = 'forbidden';
			wrap_static('page', 'status', 403);
			global $zz_page;
			$zz_page['error_msg'] = 'You are not allowed to create these thumbnails.';
			$ops['mode'] = false;
			$zz['record']['action'] = false;
		}
	}

	// @todo think about multiple_edit
	switch ($zz_conf['int']['access']) { // access overwrites individual settings
	// first the record specific or overall settings
	case 'export':
		$zz['record']['add'] = false;			// don't add record (form+links)
		$zz['record']['edit'] = false;			// don't edit record (form+links)
		$zz['record']['delete'] = false;		// don't delete record (form+links)
		$zz['record']['view'] = false;			// don't show record (links)
		$zz_conf['int']['record'] = false;		// don't show record
		break;
	case 'show_after_add';
		$zz['record']['add'] = false;			// don't add record (form+links)
		$zz['record']['edit'] = false;			// edit record (form+links)
		$zz['record']['delete'] = false;		// don't delete record (form+links)
		$zz['record']['view'] = false;			// don't show record (links)
		wrap_setting('zzform_search', false);	// no search form
		$zz['list']['display'] = false;			// no list
		$zz['record']['no_ok'] = true;			// no OK button
		$zz['record']['cancel_link'] = false; 	// no cancel link
		if (empty($_POST)) $ops['mode'] = 'show';
		break;
	case 'show_after_edit';
		$zz['record']['add'] = false;			// don't add record (form+links)
		$zz['record']['edit'] = true;			// edit record (form+links)
		$zz['record']['delete'] = false;		// don't delete record (form+links)
		$zz['record']['view'] = false;			// don't show record (links)
		wrap_setting('zzform_search', false);	// no search form
		$zz['list']['display'] = false;			// no list
		$zz['record']['no_ok'] = true;			// no OK button
		$zz['record']['cancel_link'] = false; 	// no cancel link
		if (empty($_POST)) $ops['mode'] = 'show';
		break;
	case 'show+edit';
		$zz['record']['add'] = false;			// don't add record (form+links)
		$zz['record']['edit'] = true;			// edit record (form+links)
		$zz['record']['delete'] = false;		// don't delete record (form+links)
		$zz['record']['view'] = false;			// don't show record (links)
		wrap_setting('zzform_search', false);	// no search form
		$zz['list']['display'] = false;			// no list
		$zz['record']['no_ok'] = true;			// no OK button
		if (empty($_POST)) $ops['mode'] = 'show';
		break;
	case 'add+delete';
		$zz['record']['add'] = true;			// add record (form)
		$zz['record']['edit'] = false;			// don't edit record (form+links)
		$zz['record']['delete'] = true;			// don't delete record (form+links)
		$zz['record']['view'] = false;			// don't show record (links)
		break;
	case 'add_only';
		$zz['record']['add'] = true;			// add record (form)
		$zz['record']['edit'] = false;			// don't edit record (form+links)
		$zz['record']['delete'] = false;		// don't delete record (form+links)
		$zz['record']['view'] = false;			// don't show record (links)
		wrap_setting('zzform_search', false);	// no search form
		$zz['list']['display'] = false;			// no list
		$zz['record']['cancel_link'] = false; 	// no cancel link
		$zz['record']['no_ok'] = true;			// no OK button
		if (empty($zz_conf['int']['where_with_unique_id'])) {
			$zz_conf['int']['hash_id'] = true;	// user cannot view all IDs
		}
		if (empty($_POST)) $ops['mode'] = 'add';
		break;
	case 'edit_only';
		$zz['record']['add'] = false;			// don't add record (form+links)
		$zz['record']['edit'] = true;			// edit record (form+links)
		$zz['record']['delete'] = false;		// don't delete record (form+links)
		$zz['record']['view'] = false;			// don't show record (links)
		wrap_setting('zzform_search', false);	// no search form
		$zz['list']['display'] = false;			// no list
		$zz['record']['no_ok'] = true;			// no OK button
		$zz['record']['cancel_link'] = false; 	// no cancel link
		if (empty($zz_conf['int']['where_with_unique_id'])) {
			$zz_conf['int']['hash_id'] = true;	// user cannot view all IDs
		}
		if (empty($_POST)) $ops['mode'] = 'edit';
		break;
	case 'thumbnails':
		$zz['record']['add'] = false;
		$zz['record']['edit'] = false;
		$zz['record']['delete'] = false;
		$zz['record']['view'] = false;
		$zz['list']['display'] = false;
		$zz_conf['generate_output'] = false;
		$zz_conf['int']['record'] = true;
		$zz['record']['action'] = 'thumbnails';
		$zz['record']['query_records'] = true;
		break;
	case 'forbidden':
		$zz['list']['display'] = false;	// don't show record, further in listandrecord_access
	default:
		// now the settings which apply to both record and list
		$zz['record'] = zz_listandrecord_access($zz['record']);
		break;
	}

	// @deprecated
	if ($ops['mode'] === 'add' AND $zz['record']['copy'] AND !empty($_GET['source_id'])) {
		$zz_conf['int']['id']['source_value'] = zz_check_get_array('source_id', 'is_int');
	}

	if ($zz_conf['int']['where_with_unique_id']) { // just for record, not for list
		// in case of where and not unique, ie. only one record in table, 
		// don't do this.
		$zz['list']['display'] = false;		// don't show table
		$no_add = true;
		if ($ops['mode'] === 'add') $no_add = false;
		if ($zz['record']['action'] === 'insert') $no_add = false;
		if ($no_add) $zz['record']['add'] = false; 		// don't show add record (form+links)
	}

	// $zz['record'] is set regarding add, edit, delete
	// don't copy record (form+links)
	if (!$zz['record']['add']) $zz['record']['copy'] = false;

	// check unallowed modes and actions
	$modes = ['add' => 'insert', 'edit' => 'update', 'delete' => 'delete'];
	foreach ($modes as $mode => $action) {
		if (!$zz['record'][$mode] AND $ops['mode'] === $mode) {
			$ops['mode'] = false;
			zz_error_log([
				'msg_dev' => 'Configuration does not allow this mode: %s',
				'msg_dev_args' => [$mode],
				'status' => 403,
				'level' => E_USER_NOTICE
			]);
			$zz_conf['int']['record'] = false;
		}
		if (!$zz['record'][$mode] AND $zz['record']['action'] === $action) {
			$zz['record']['action'] = false;
			zz_error_log([
				'msg_dev' => 'Configuration does not allow this action: %s',
				'msg_dev_args' => [$action],
				'status' => 403,
				'level' => E_USER_NOTICE
			]);
			$zz_conf['int']['record'] = false;
		}
	}

	if ($zz_conf['int']['access'] === 'edit_details_only') $zz['access'] = 'show';
	if ($zz_conf['int']['access'] === 'edit_details_and_add' 
		AND $ops['mode'] !== 'add' AND $zz['record']['action'] !== 'insert')
		$zz['access'] = 'show';

	// now, mode is set, do something depending on mode
	if ($ops['mode'] === 'list_only')
		$zz_conf['int']['record'] = false;	// don't show record

	return zz_return([$zz, $ops]);
}

/** 
 * Sets configuration variables depending on $zz['access']
 * Access possible for list and for record view
 * 
 * @param array $conf
 * @return array
 */
function zz_listandrecord_access($conf) {
	global $zz_conf;
	switch ($zz_conf['int']['access']) {
	case 'show':
		$conf['add'] = false;				// don't add record (form+links)
		$conf['edit'] = false;				// don't edit record (form+links)
		$conf['delete'] = false;			// don't delete record (form+links)
		$conf['view'] = true;				// show record (links)
		break;
	case 'show_and_add':
		$conf['add'] = true; 				// add record (form+links)
		$conf['edit'] = false;				// edit record (form+links)
		$conf['delete'] = false;			// don't delete record (form+links)
		$conf['view'] = true;				// show record (links)
		break;
	case 'show_edit_add';
		$conf['add'] = true; 				// add record (form+links)
		$conf['edit'] = true;				// edit record (form+links)
		$conf['delete'] = false;			// don't delete record (form+links)
		$conf['view'] = true;				// show record (links)
		break;
	case 'show_and_delete';
		$conf['add'] = false;				// don't add record (form+links)
		$conf['edit'] = false;				// don't edit record (form+links)
		$conf['delete'] = true;				// delete record (form+links)
		$conf['view'] = true;				// show record (links)
		break;
	case 'edit_details_only':
		$conf['add'] = false;				// don't add record (form+links)
		$conf['edit'] = true;				// edit record (form+links)
		$conf['delete'] = false;			// don't delete record (form+links)
		$conf['view'] = false;				// don't show record (links)
		break;
	case 'edit_details_and_add':
		$conf['add'] = true; 				// add record (form+links)
		$conf['edit'] = true;				// edit record (form+links)
		$conf['delete'] = false;			// don't delete record (form+links)
		$conf['view'] = false;				// don't show record (links)
		break;
	case 'none':
		$conf['add'] = false;				// don't add record (form+links)
		$conf['edit'] = false;				// don't edit record (form+links)
		$conf['delete'] = false;			// don't delete record (form+links)
		$conf['view'] = false;				// don't show record (links)
		$zz_conf['int']['record'] = false;	// don't show record
		break;
	case 'forbidden':
		$conf['add'] = false;				// don't add record (form+links)
		$conf['edit'] = false;				// don't edit record (form+links)
		$conf['delete'] = false;			// don't delete record (form+links)
		$conf['view'] = false;				// don't show record (links)
		$zz_conf['int']['record'] = false;	// don't show record
		break;
	case 'search_but_no_list':
		$conf['add'] = false;				// don't add record (form+links)
		$conf['edit'] = false;				// don't edit record (form+links)
		$conf['delete'] = false;			// don't delete record (form+links)
		$conf['view'] = false;				// don't show record (links)
		$zz_conf['int']['record'] = false;	// don't show record
		break;
	case 'all':
		$conf['add'] = true;				// add record (form+links)
		$conf['edit'] = true;				// edit record (form+links)
		$conf['delete'] = true;				// delete record (form+links)
		$conf['view'] = false;				// don't show record (links)
		break;
	default:
		// do not change anything, just initalize if required
		if (!isset($conf['add'])) $conf['add'] = true;
		if (!isset($conf['edit'])) $conf['edit'] = true;
		if (!isset($conf['delete'])) $conf['delete'] = false;
		if (!isset($conf['view'])) $conf['view'] = false;
		break;
	}

	return $conf;
}

/** 
 * Creates link or HTML img from path
 * 
 * @param array $path
 *		'root', 'webroot', 'field1...fieldn', 'string1...stringn', 'mode1...n',
 *		'extension', 'x_field[]', 'x_webfield[]', 'x_extension[]'
 *		'ignore_record' will cause record to be ignored
 *		'alternate_root' will check for an alternate root
 * @param array $record current record
 * @param string $type (optional) link, path or image, image will be returned in
 *		<img src="" alt="">
 * @return string URL or HTML-code for image
 */
function zz_makelink($path, $record, $type = 'link') {
	if (empty($path['ignore_record']) AND !$record) return false;
	if (!$path) return false;

	$url = '';
	$modes = [];
	$path_full = '';		// absolute path in filesystem
	$path_alternate = '';
	$path_web[1] = '';		// relative path on website
	$sets = [];
	foreach (array_keys($path) as $part) {
		if (substr($part, 0, 2) !== 'x_') continue;
		$part = explode('[', $part);
		$part = substr($part[1], 0, strpos($part[1], ']'));
		$sets[$part] = $part;
	}
	foreach ($sets as $myset) {
		$path_web[$myset] = '';		// relative path to retina image on website
		$set[$myset] = NULL;			// show 2x image
	}
	
	$check_against_root = false;

	if ($type === 'image') {
		$alt = wrap_text('No image');
		// lock if there is something definitely called extension
		$alt_locked = false; 
	}
	if (!is_array($path)) $path = ['string' => $path];
	
	// check if extension field is given but has no value
	if (!empty($path['extension_missing']) AND !empty($path['extension'])
		AND empty($record[$path['extension']])) {
		// check if extension_missing[extension] is webimage, otherwise return false
		if ($type === 'image' AND !empty($record[$path['extension_missing']['extension']])) {
			$def = wrap_filetypes($record[$path['extension_missing']['extension']], 'read-per-extension');
			if (empty($def['webimage']) AND empty($def['php'])) return false;
		}
		$path = array_merge($path, $path['extension_missing']);
	}
	
	foreach ($path as $part => $value) {
		if (!$value) continue;
		// remove numbers at the end of the part type
		while (is_numeric(substr($part, -1))) $part = substr($part, 0, -1);
		if (substr($part, -1) === ']') {
			$current_set = substr($part, strpos($part, '[') + 1, -1); 
			$part = substr($part, 0, strpos($part, '['));
		}
		switch ($part) {
		case 'area':
			$path_values = [];
			if (empty($path['fields'])) $path['fields'] = [];
			elseif (!is_array($path['fields'])) $path['fields'] = [$path['fields']];
			foreach ($path['fields'] as $index => $this_field) {
				if (empty($record[$this_field])) break 2;
				if (!empty($path['target'][$index]))
					// placeholder for later use
					$path_values[] = '*'.$record[$this_field].'*';
				else
					$path_values[] = $record[$this_field];
			}
			if (strstr($value, '[%s]') AND !empty($path['area_fields'])) {
				$area_values = [];
				foreach ($path['area_fields'] as $this_field)
					$area_values[] = $record[$this_field];
				$value = vsprintf($value, $area_values);
			}
			$rights = true;
			if (!empty($path['restrict_to']) AND !empty($record[$path['restrict_to']]))
				$rights = sprintf('%s:%d', $path['restrict_to'], $record[$path['restrict_to']]);
			$path_web[1] .= wrap_path($value, $path_values, $rights);
			break;
		case 'function':
			if (function_exists($value) AND !empty($path['fields'])) {
				$params = [];
				foreach ($path['fields'] as $function_field) {
					if (!isset($record[$function_field])) continue;
					$params[$function_field] = $record[$function_field];
				}
				$path_web[1] .= $value($params);
			}
			break;
		case 'fields':
		case 'restrict_to':
			break;
		case 'root':
			$check_against_root = true;
			// root has to be first element, everything before will be ignored
			$path_full = $value;
			if (substr($path_full, -1) !== '/')
				$path_full .= '/';
			break;
		case 'alternate_root':
			$path_alternate = $value;
			if (substr($path_alternate, -1) !== '/')
				$path_alternate .= '/';
			break;
		case 'webroot':
			// web might come later, ignore parts before for web and add them
			// to full path
			$path_web[1] = $value;
			foreach ($sets as $myset) {
				$path_web[$myset] = $value;
			}
			$path_full .= $url;
			$path_alternate .= $url;
			$url = '';
			break;
		case 'extension':
		case 'field':
		case 'webfield':
			// we don't have that field or it is NULL, so we can't build the
			// path and return with nothing
			// if you need an empty field, use IFNULL(field_name, "")
			if (!isset($record[$value])) return false;
			$content = $record[$value];
			if ($modes) {
				$content = zz_make_mode($modes, $content, E_USER_ERROR);
				if (!$content) return false;
				$modes = [];
			}
			if ($part !== 'webfield') {
				$url .= $content;
			}
			$path_web[1] .= $content;
			if ($type === 'image' AND !$alt_locked) {
				$alt = wrap_text('File: ').$record[$value];
				if ($part === 'extension') $alt_locked = true;
			}
			break;
		case 'x_extension':
		case 'x_field':
		case 'x_webfield':
			if ($set[$current_set] === false) break;
			if (!isset($record[$value])) { $set[$current_set] = false; break; }
			$set[$current_set] = true;
			$content = $record[$value];
			if ($modes) {
				$content = zz_make_mode($modes, $content, E_USER_ERROR);
				if (!$content) break;
				$modes = [];
			}
			$path_web[$current_set] .= $content;
			break;
		case 'string':
			$url .= $value;
		case 'webstring':
			$path_web[1] .= $value;
			foreach ($sets as $myset) {
				$path_web[$myset] .= $value;
			}
			break;
		case 'mode':
			$modes[] = $value;
			break;
		}
	}

	// get filetype from extension
	if (strstr($url, '.')) {
		$ext = strtoupper(substr($url, strrpos($url, '.') + 1));
	} else {
		$ext = wrap_text('- unknown -');
	}
	
	if ($check_against_root) {
		// check whether file exists
		if (!file_exists($path_full.$url)) {
			// file does not exist = false
			if (!$path_alternate) return false;
			if (!file_exists($path_alternate.$url)) return false;
			$path_full = $path_alternate;
		}
		if ($type === 'image') {
			// filesize is 0 = looks like error
			if (!$size = filesize($path_full.$url)) return false;
			// getimagesize tests whether it's a web image
			$filetype_def = wrap_filetypes(strtolower($ext), 'read-per-extension');
			if (empty($filetype_def['webimage']) AND !getimagesize($path_full.$url)) {
				// if not, return EXT (4.4 MB)
				return $ext.' ('.wrap_bytes($size).')';
			}
		}
	}

	switch ($type) {
	case 'path':
		return $path_full.$url;
	case 'image':
		if (!$path_web[1]) return false;
		$srcset = [];
		foreach ($sets as $myset) {
			if ($set[$myset]) $srcset[] = $path_web[$myset].' '.$myset.'x';
		}
		$srcset = $srcset ? sprintf(' srcset="%s 1x, %s"', $path_web[1], implode(', ', $srcset)) : '';
		$img = '<img src="'.$path_web[1].'"'.$srcset.' alt="'.$alt.'" class="thumb">';
		return $img;
	default:
	case 'link':
		return $path_web[1];
	}
}

/**
 * apply all modes as a function to content
 *
 * @param array $modes
 * @param string $content
 * @return string
 */
function zz_make_mode($modes, $content, $error = E_USER_WARNING) {
	foreach ($modes as $mode) {
		if (!function_exists($mode)) {
			zz_error_log([
				'msg_dev' => 'Configuration Error: mode with non-existing function `%s`',
				'msg_dev_args' => [$mode],
				'level' => $error
			]);
			return false;
		}
		$content = $mode($content);
	}
	return $content;
}

/** 
 * Construct path from values
 * 
 * @param array $path array with variables which make path
 *		'root' (DOCUMENT_ROOT), 'webroot' (different root for web, all fields
 *		and strings before webroot will be ignored for this), 'mode' (function  
 *		to do something with strings from now on), 'string1...n' (string, number
 *		has no meaning, no sorting will take place, will be shown 1:1),
 *		'field1...n' (field value from record)
 * @param array $data (= $zz_tab or simple line)
 * @param string $record (optional) default 'new', other: 'old' (use updated
 *		record or old record), 'line': use the input data as a complete record
 * @param bool $do (optional)
 * @param int $tab (optional)
 * @param int $rec (optional)
 * @return string
 */
function zz_makepath($path, $data, $record = 'new', $do = false, $tab = 0, $rec = 0) {
	// set variables
	$p = false;
	$modes = false;
	$root = false;		// root
	$rootp = false;		// path just for root
	$webroot = false;	// web root
	$sql_fields = [];

	// record data
	switch ($record) {
	case 'old':
		$my_tab = $data[$tab];
		$line = $my_tab[$rec]['existing'] ?? [];
		break;
	case 'new':
		$my_tab = $data[$tab];
		$line = $my_tab[$rec]['POST'] ?? [];
		break;
	case 'line':
		$line = $data;
		break;
	}

	// put path together
	$alt_locked = false;
	foreach ($path as $part => $pvalue) {
		if (!$pvalue) continue;
		while (is_numeric(substr($part, -1))) $part = substr($part, 0, -1);
		switch ($part) {
		case 'root':
			$root = $pvalue;
			break;

		case 'webroot':
			$webroot = $pvalue;
			$rootp = $p;
			$p = '';
			break;

		case 'mode':
			$modes[] = $pvalue;
			break;
		
		case 'string':
			$p .= $pvalue;
			break;

		case 'sql_field':
			$sql_fields[] = $line[$pvalue] ?? '';
			break;

		case 'sql':
			$sql = $pvalue;
			if ($sql_fields) $sql = vsprintf($sql, $sql_fields);
			$result = wrap_db_fetch($sql, '', 'single value');
			if ($result) $p .= $result;
			$sql_fields = [];
			break;

		case 'extension':
		case 'field':
			$content = $line[$pvalue] ?? '';
			if (!$content AND $content !== '0' AND $record === 'new') {
				$content = zz_get_record(
					$pvalue, $my_tab['sql'], $my_tab[$rec]['id']['value'], 
					$my_tab['table'].'.'.$my_tab[$rec]['id']['field_name']
				);
			}
			if ($modes) {
				$content = zz_make_mode($modes, $content);
				if (!$content AND $content !== '0') return '';
			}
			$p .= $content;
			if (!$alt_locked) {
				$alt = wrap_text('File: ').$content;
				if ($part === 'extension') $alt_locked = true;
			}
			$modes = false;
			break;

		case 'webstring':
		case 'webfield':
		case 'extension_missing':
			break;

		default:
			wrap_error(sprintf('Unknown mode %s in %s', $part, __FUNCTION__), E_USER_NOTICE);
			break;
		}
	}

	switch ($do) {
		case 'file':
			// webroot will be ignored
			$p = $root.$rootp.$p;
			break;
		case 'local':
			$p = $webroot.$p;
			// return alt as well
			break;
		default:

//	if ($root && !file_exists($root.$link))
//		return false;
//	return $link;

	}
	return $p;
}

/** 
 * gets value from a single record
 * 
 * @param string $field_name
 * @param string $sql
 * @param string $idvalue (optional)
 * @param string $idfield (optional)
 * @return string
 */
function zz_get_record($field_name, $sql, $idvalue = false, $idfield = false) { 
	static $queried = [];
	$key = sprintf('%s-%s-%s', $sql, $idvalue, $idfield);
	// if idvalue is not set: note: all values should be the same!
	// First value is taken
	if (!array_key_exists($key, $queried)) {
		if ($idvalue) 
			$sql = wrap_edit_sql($sql, 'WHERE', sprintf('%s = %d', $idfield, $idvalue));
		$queried[$key] = zz_db_fetch($sql, '', '', __FUNCTION__);
	}
	return $queried[$key][$field_name] ?? false;
}

/** 
 * exit function for zzform functions, should always be called to adjust some 
 * settings
 *
 * @param mixed $return return parameter
 * @return mixed return parameter
 */
function zz_return($return = false) {
	if (wrap_setting('debug')) zz_debug('end');
	return $return;
}

/** 
 * converts fieldnames with [ and ] into valid HTML id values
 * 
 * @param string $fieldname field name with []-brackets
 * @param string $prefix prepends 'field_' as default or other prefix
 * @return string valid HTML id value
 */
function zz_make_id_fieldname($fieldname, $prefix = 'field') {
	$fieldname = str_replace('][', '_', $fieldname);
	$fieldname = str_replace('[', '_', $fieldname);
	$fieldname = str_replace(']', '', $fieldname);
	if ($prefix) $fieldname = $prefix.'_'.$fieldname;
	return $fieldname;
}

/**
 * get type of field
 *
 * @param array $field
 * @return string $field_type
 */
function zz_get_fieldtype($field) {
	if (empty($field)) return;
	if (in_array($field['type'], ['hidden', 'predefined', 'write_once', 'display'])) {
		if (isset($field['type_detail'])) {
			return $field['type_detail'];
		}
	}
	return $field['type'];
}

/**
 * --------------------------------------------------------------------
 * E - Error functions
 * --------------------------------------------------------------------
 */

/**
 * error logging for zzform()
 * will display error messages for the current user on HTML webpage
 * depending on settings, will log errors in logfile and/or send errors by mail
 *
 * @global array $zz_conf
 * @return bool false if no error was detected, true if error was detected
 */
function zz_error() {
	global $zz_conf;
	
	if (!wrap_setting('error_handling'))
		wrap_setting('error_handling', 'output');
	$user = [];
	$admin = [];
	$log = [];
	$message = [];
	$return = zz_error_exit() ? 'exit' : 'html';
	
	$logged_errors = zz_error_log();
	if (!$logged_errors) {
		zz_error_exit(($return === 'exit') ? true : false);
		return false;
	}
	
	$log_encoding = wrap_log_encoding();
	
	// browse through all errors
	foreach ($logged_errors as $key => $error) {
		if (!is_numeric($key)) continue;
		
		// initialize error_level
		if (empty($error['level'])) $error['level'] = '';
		if (empty($error['status'])) $error['status'] = 200;
		
		// log POST data?
		if (!isset($error['log_post_data'])) {
			$error['log_post_data'] = true;
		}
		if (!wrap_setting('error_log_post')) $error['log_post_data'] = false;
		elseif (empty($_POST)) $error['log_post_data'] = false;

		// page http status
		if ($error['status'] !== 200)
			wrap_static('page', 'status', $error['status']);

		// initialize and translate error messages
		if (!empty($error['msg'])) {
			// allow 'msg' to be an array to translate each sentence individually
			if (!is_array($error['msg'])) $error['msg'] = [$error['msg']];
			foreach ($error['msg'] as $index => $msg) {
				if (is_array($msg)) {
					$mymsg = [];
					foreach ($msg as $submsg) {
						$mymsg[] = wrap_text(trim($submsg));
					}
					$error['msg'][$index] = implode(' ', $mymsg);
				} else {
					$error['msg'][$index] = wrap_text(trim($msg));
				}
			}
			if (empty($error['html'])) {
				$error['msg'] = implode(' ', $error['msg']);
			} else {
				$mymsg = [];
				foreach ($error['html'] as $index => $html) {
					if (array_key_exists($index, $error['msg'])) {
						$mymsg[] = sprintf($html, $error['msg'][$index]);
					} else {
						$mymsg[] = $html;
					}
				}
				$error['msg'] = implode(' ', $mymsg);
			}
		} else {
			$error['msg'] = '';
		}
		if (!empty($error['msg_args'])) {
			// flatten msg_args because msg is concatenated already
			$args = [];
			foreach ($error['msg_args'] as $arg) {
				if (is_array($arg)) $args = array_merge($args, $arg);
				else $args[] = $arg;
			}
			$error['msg'] = vsprintf($error['msg'], $args);
		}
		// @todo think about translating dev messages for administrators
		// in a centrally set (not user defined) language
		$error['msg_dev'] = $error['msg_dev'] ?? '';
		if (is_array($error['msg_dev'])) $error['msg_dev'] = implode(' ', $error['msg_dev']);
		$error['msg_dev'] = trim($error['msg_dev']);
		if (!empty($error['msg_dev_args'])) {
			$error['msg_dev'] = vsprintf($error['msg_dev'], $error['msg_dev_args']);
		}

		$user[$key] = false;
		$admin[$key] = false;

		if (!empty($error['db_errno'])) {
			$error['msg'] = zz_db_error($error['db_errno'])
				.($error['msg'] ? '<br>'.$error['msg'] : '');
		}

		switch ($error['level']) {
		case E_USER_ERROR:
			if (!$error['msg']) $user[$key] .= wrap_text('An error occured.'
				.' We are working on the solution of this problem. '
				.'Sorry for your inconvenience. Please try again later.');
			$level = 'error';
			// get out of this function immediately:
			$return = 'exit';
			break;

		default:
		case E_USER_WARNING:
			$level = 'warning';
			break;

		case E_USER_NOTICE:
			$level = 'notice';
			break;
		}

		// User output
		$user[$key] .= $error['msg'];

		// Admin output
		if ($error['msg_dev']) 
			$admin[$key] .= $error['msg_dev'].'<br>';
		if (!empty($error['db_msg'])) 
			$admin[$key] .= $error['db_msg'].':<br>';
		if (!empty($error['query'])) {
			// since we have an SQL query, we do not need roughly the same
			// information from the POST data
			$error['log_post_data'] = false;
			$admin[$key] .= preg_replace("/\s+/", " ", $error['query']).'<br>';
		}
		if ($admin[$key] AND $error['msg'])
			$admin[$key] = $error['msg'].'<br>'.$admin[$key];
		elseif (!$admin[$key])
			$admin[$key] = $error['msg'];

		// Log output
		$log[$key] = trim($admin[$key]);
		// preserve &lt; for some reasons (Value incorrect in field: ... 
		// (String "<a href=" is not allowed).)
		$log[$key] = str_replace('&lt;', '&amp;lt;', $log[$key]);
		$log[$key] = html_entity_decode($log[$key], ENT_QUOTES, $log_encoding);
		$log[$key] = str_replace('<br>', "\n\n", $log[$key]);
		$log[$key] = str_replace('&lt;br class="nonewline_in_mail">', "; ", $log[$key]);
		$log[$key] = strip_tags($log[$key]);
		$log[$key] = str_replace('&lt;', '<', $log[$key]);
		// reformat log output
		if (wrap_setting('error_log['.$level.']') AND wrap_setting('log_errors')) {
			wrap_log('['.wrap_setting('request_uri').'] '.$log[$key],  $level, 'zzform');
			if ($error['log_post_data']) wrap_log('postdata', 'notice', 'zzform');
		}
		// Mail output
		if (in_array($level, wrap_setting('error_mail_level')))
			$message[$key] = $log[$key];

		// Heading
		if (!$user[$key]) {
			unset($user[$key]); // there is nothing, so nothing will be shown
		} elseif ($level === 'error' OR $level === 'warning') {
			$user[$key] = '<strong>'.wrap_text('Attention!').'</strong> '.$user[$key];
		}
		if ($admin[$key] AND ($level === 'error' OR $level === 'warning')) {
			$admin[$key] = '<strong>'.wrap_text('Attention!').'</strong> '.$admin[$key];
		}
	}
	foreach ($admin as $line) {
		$zz_conf['int']['ops_error_msg'][] = strip_tags($line);
	}

	// mail errors if said to do so
	$mail = [];
	switch (wrap_setting('error_handling')) {
	case 'mail':	
		if (!wrap_setting('error_mail_to')) break;
		if (!count($message)) break;
		$mail['message'] = wrap_text('The following error(s) occured in project %s:', ['values' => wrap_setting('project')]);
		$mail['message'] .= "\n\n".implode("\n\n", $message);
		$mail['message'] = html_entity_decode($mail['message'], ENT_QUOTES, $log_encoding);		
		$mail['message'] .= "\n\n-- \nURL: ".$zz_conf['int']['url']['base']
			.wrap_setting('request_uri')
			."\nIP: ".wrap_setting('remote_ip')
			.(!empty($_SERVER['HTTP_USER_AGENT']) ? "\nBrowser: ".$_SERVER['HTTP_USER_AGENT'] : '');		
		if ($username = wrap_username())
			$mail['message'] .= sprintf("\nUser: %s", $username);

		$mail['subject'] = wrap_text('Database access error');
		if (!wrap_setting('mail_subject_prefix'))
			$mail['subject'] = '['.wrap_setting('project').'] '.$mail['subject'];
		$mail['to'] = wrap_setting('error_mail_to');
		$mail['queue'] = true;
		wrap_mail($mail);
		break;
	case 'output':
		$user = $admin;
		break;
	case 'save_mail':
		if (!count($mail)) break;
		$zz_conf['int']['error'][] = $mail;
		break;
	}

	// Went through all errors, so we do not need them anymore
	zz_error_log(false);
	
	zz_error_exit(($return === 'exit') ? true : false);
	zz_error_out($user);

	return true;
}

/**
 * log an error message
 *
 * @param array $msg
 *		array for each error:
 * 		mixed 'msg' message(s) that always will be sent back to browser
 *		array 'msg_args' vsprintf arguments for msg
 * 		string 'msg_dev' message that will be sent to browser, log and mail, 
 * 			depending on settings
 *		array 'msg_dev_args' vsprintf arguments for msg_dev
 * 		int 'level' for error level: currently implemented:
 * 			- E_USER_ERROR: critical error, action could not be finished,
 *				unrecoverable error
 * 			- E_USER_WARNING: error, we need some extra user input
 * 			- E_USER_NOTICE: some default settings will be used because user 
 * 				input was not enough; e. g. date formats etc.
 * 		int 'db_errno' database: error number
 * 		string 'db_msg' database: error message
 * 		string 'query' SQL-Query
 * 		bool 'log_post_data': true (default); false: do not log POST
 * @static array $errors
 * @return array
 */
function zz_error_log($msg = []) {
	static $errors = [];
	if ($msg === false) $errors = [];
	elseif ($msg) $errors[] = $msg;
	return $errors;
}

/**
 * set exit variable to signal the script to stop
 *
 * @param mixed $set
 *	true: exit script;
 *	false: do not exit script
 *	'check': print out current status (default)
 * @return bool
 */
function zz_error_exit($set = 'check') {
	static $exit = false;
	if ($set === true) $exit = true;
	elseif ($set === false) $exit = false;
	return $exit;
}

/**
 * save error message output for later
 *
 * @param mixed
 *	array: add to output array
 *	bool: false deletes or initializes the output
 * @return array
 */
function zz_error_out($data = []) {
	static $output = [];
	if ($data === false) $output = [];
	elseif ($data) $output = array_merge($output, $data);
	return $output;
}

/**
 * outputs error messages
 *
 * @return string
 */
function zz_error_output() {
	$text = zz_error_out();
	if (!$text) return '';
	$text = '<div class="error">'.implode('<br><br>', $text).'</div>'."\n";
	zz_error_out(false);
	return $text;
}

/**
 * log validation errors
 *
 * @param string $key
 *		'msg', 'msg_args', 'msg_dev', 'msg_dev_args', log_post_data' => log for this key
 *		'delete' => delete all values
 * @param mixed $value
 *		bool, array, string
 * @return array
 */
function zz_error_validation_log($key = false, $value = []) {
	static $errors = [];
	if (!$errors OR $key === 'delete') {
		$errors = [
			'msg' => [], 'msg_args' => [], 'msg_dev' => [],
			'msg_dev_args' => [], 'log_post_data' => false
		];
		if ($key === 'delete') $key = false;
	}
	if ($key) {
		if (is_bool($value)) $errors[$key] = $value;
		elseif (is_array($value)) $errors[$key] = array_merge($errors[$key], $value);
		else $errors[$key][] = $value;
	}
	return $errors;
}

/**
 * output and log validation error messages
 *
 * @return void
 */
function zz_error_validation() {
	global $zz_conf;
	$errors = zz_error_validation_log();
	if (!$errors['msg']) return false;
	if (!empty($zz_conf['multi'])) return false;

	// user error message, visible to everyone
	// line breaks \n important for mailing errors
	$errors['html'][] = "<p>%s</p>\n<ul>";
	foreach ($errors['msg'] as $msg) {
		$errors['html'][] = "<li>%s</li>\n";
	}
	$errors['html'][] = "</ul>\n";
	array_unshift($errors['msg'], 'These problems occured:');
	// if we got wrong values entered, put this into a developer message
	$dev_msgs = $errors['msg_dev'];
	unset($errors['msg_dev']);
	foreach ($dev_msgs as $msg_dev) {
		$errors['msg_dev'][] = 'Field name: %s / ';
		$errors['msg_dev'][] = $msg_dev;
	}
	$errors['level'] = E_USER_NOTICE;
	zz_error_log($errors);
	zz_error_validation_log('delete');
}

/**
 * log errors in $ops['error'] if zzform_multi() was called, because errors
 * won't be shown on screen in this mode
 *
 * @param array $errors = $ops['error']
 * @global array $zz_conf
 * @return array $errors
 */
function zz_error_multi($errors) {
	global $zz_conf;
	if (!$zz_conf['multi']) return $errors;

	$logged_errors = zz_error_log();
	foreach ($logged_errors as $index => $error) {
		if (empty($error['msg_dev'])) continue;
		if (!empty($error['msg_dev_args'])) {
			$error['msg_dev'] = vsprintf($error['msg_dev'], $error['msg_dev_args']);
		}
		$errors[] = $error['msg_dev'];
	}
	$validation_errors = zz_error_validation_log();
	if ($validation_errors['msg']) {
		foreach ($validation_errors['msg'] as $index => $msg)
			$validation_errors['msg'][$index] = strip_tags($msg);
		if (!empty($validation_errors['msg_args'])) {
			$glue = 'SOME_NEVER_APPEARING_SEQUENCE_IN_ERROR_MSG';
			$msgs = implode($glue, $validation_errors['msg']);
			$msgs = vsprintf($msgs, $validation_errors['msg_args']);
			$validation_errors['msg'] = explode($glue, $msgs);
		}
		$errors = array_merge($errors, $validation_errors['msg']);
	}
	return $errors;
}

/**
 * Generate error message if POST is too big
 *
 * @return bool
 */
function zz_trigger_error_too_big() {
	global $zz_conf;
	
	if (empty($zz_conf['int']['post_too_big'])) return true;
	zz_error_log([
		'msg' => [
			'Transfer failed. Probably you sent a file that was too large.',
			'<br>',
			'Maximum allowed filesize is %s.',
			' – You sent: %s data.'
		],
		'msg_args' => [
			wrap_bytes(zz_upload_max_filesize()),
			wrap_bytes($_SERVER['CONTENT_LENGTH'])
		],
		'level' => E_USER_NOTICE
	]);
	return false;
}

/*
 * --------------------------------------------------------------------
 * F - Filesystem functions
 * --------------------------------------------------------------------
 */

/**
 * Creates new directory (and dirs above, if necessary)
 * 
 * @param string $dir directory to be created
 * @return bool true/false = successful/fail
 */
function zz_create_topfolders($dir) {
	if (!$dir) return false;
	// checks if directories above current exist and creates them if necessary
	while (strpos($dir, '//'))
		$dir = str_replace('//', '/', $dir);
	if (substr($dir, -1) === '/')	//	removes / from the end
		$dir = substr($dir, 0, -1);
	if (file_exists($dir)) return true;

	// if dir does not exist, do a recursive check/makedir on parent directories
	$upper_dir = substr($dir, 0, strrpos($dir, '/'));
	$success = zz_create_topfolders($upper_dir);
	if ($success) {
		if (!is_writable($upper_dir)) {
			zz_error_log([
				'msg_dev' => 'Creation of directory %s failed: Parent directory is not writable.',
				'msg_dev_args' => [$dir],
				'level' => E_USER_ERROR
			]);
			zz_error_exit(true);
			return false;
		}
		$success = mkdir($dir, 0777);
		if ($success) return true;
		//else $success = chown($dir, getmyuid());
		//if (!$success) echo 'Change of Ownership of '.$dir.' failed.<br>';
	}

	zz_error_log([
		'msg_dev' => 'Creation of directory %s failed.',
		'msg_dev_args' => [$dir],
		'level' => E_USER_ERROR
	]);
	zz_error_exit(true);
	return false;
}

/*
 * --------------------------------------------------------------------
 * H - Hierarchy functions
 * --------------------------------------------------------------------
 */

/**
 * Sort SQL query for hierarchical view
 *
 * @param string $sql
 * @param array $hierarchy
 * @return array
 *		array $my_lines
 *		int $total_rows
 */
function zz_hierarchy($sql, $hierarchy) {
	// for performance reasons, we only get the fields which are important
	// for the hierarchy (we need to get all records)
	$lines = zz_db_fetch($sql, [$hierarchy['id_field_name'], $hierarchy['mother_id_field_name']], 'key/value'); 
	if (!$lines) return zz_return([[], 0]);

	if (empty($hierarchy['id'])) 
		$hierarchy['id'] = 'NULL';

	$h_lines = [];
	foreach ($lines as $id => $mother_id) {
		// sort lines by mother_id
		if ($id == $hierarchy['id']) {
			// get uppermost line if hierarchy id is not NULL!
			$mother_id = 'TOP';
		} elseif (empty($mother_id)) {
			$mother_id = 'NULL';
		} elseif (!in_array($mother_id, array_keys($lines))) {
			// incomplete hierarchy, show element nevertheless
			$mother_id = $hierarchy['id'];
		}
		$h_lines[$mother_id][$id] = $id;
	}
	if (!empty($hierarchy['hide_top_value']) AND !empty($h_lines[$hierarchy['id']])) {
		$h_lines['NULL'] = $h_lines[$hierarchy['id']];
		unset($h_lines[$hierarchy['id']]);
		$hierarchy['id'] = 'NULL';
		unset($h_lines['TOP']);
	}
	if (!$h_lines) return zz_return([[], 0]);
	$my_lines = zz_hierarchy_sort($h_lines, $hierarchy['id'], $hierarchy['id_field_name']);
	$total_rows = count($my_lines); // sometimes, more rows might be selected beforehands,
	return [$my_lines, $total_rows];
}

/**
 * sorts $lines hierarchically
 *
 * @param array $h_lines
 * @param string $hierarchy ($zz['list']['hierarchy']['id'])
 * @param string $id_field
 * @param int $level
 * @param int $i
 * @return array $my_lines
 */
function zz_hierarchy_sort($h_lines, $hierarchy, $id_field, $level = 0, &$i = 0) {
	$my_lines = [];
	$show_only = [];
	if (!$level AND $hierarchy !== 'NULL' AND !empty($h_lines['TOP'])) {
		// show uppermost line
		$h_lines['TOP'][0]['zz_level'] = $level;
		$my_lines[$i][$id_field] = $h_lines['TOP'][$hierarchy];
		// this page has child pages, don't allow deletion
		$my_lines[$i]['zz_conf']['delete'] = false; 
		$i++;
	}
	if ($hierarchy !== 'NULL') $level++; // don't indent uppermost level if top category is NULL
	if ($hierarchy === 'NULL' AND empty($h_lines[$hierarchy])) {
		// Looks like a WHERE condition took some vital records from our hierarchy
		// at least for the top level, get them back somehow.
		foreach (array_keys($h_lines) as $main_id) {
			$nulls[$main_id] = $main_id; // put all main_ids in Array
			foreach ($h_lines[$main_id] as $id) {
				// remove from Array if id has already a from NULL different main_id
				unset($nulls[$id]); 
			}
		}
		foreach ($nulls as $id) {
			// put all ids with missing main_ids into NULL-Array
			$h_lines['NULL'] = [$id => $id];
			$show_only[] = $id;
		}
	}
	if (!empty($h_lines[$hierarchy])) {
		foreach ($h_lines[$hierarchy] as $h_line) {
			$my_lines[$i] = [
				$id_field => $h_line,
				'zz_level' => $level
			];
			if (in_array($h_line, $show_only)) {
				// added nulls are not editable, won't be shown
				$my_lines[$i]['zz_conf']['access'] = 'none';
				$my_lines[$i]['zz_hidden_line'] = true;
			}
			$i++;
			if (!empty($h_lines[$h_line])) {
				// this page has child pages, don't allow deletion
				$my_lines[($i-1)]['zz_conf']['delete'] = false; 
				$my_lines = array_merge($my_lines, 
					zz_hierarchy_sort($h_lines, $h_line, $id_field, $level, $i));
			}
		}
	}
	return $my_lines;
}

/**
 * get possible IDs for a select if 'show_hierarchy_subtree' is set
 *
 * @param array $field
 * @return array
 */
function zz_hierarchy_subtree_ids($field) {
	if (empty($field['show_hierarchy_subtree'])) return [];
	$tables = wrap_edit_sql($field['sql'], 'FROM', false, 'list');
	$fieldnames = wrap_edit_sql($field['sql'], 'SELECT', '', 'list');
	$sql = 'SELECT %s FROM %s WHERE %s IN (%%s)';
	$sql = sprintf($sql, $fieldnames[0]['field_name'], $tables[0], $field['show_hierarchy']);
	$ids = wrap_db_children($field['show_hierarchy_subtree'], $sql);
	return $ids;
}


/*
 * --------------------------------------------------------------------
 * I - Internationalisation functions
 * --------------------------------------------------------------------
 */

/**
 * Translate values with wrap_translate() from zzwrap module
 *
 * @param array $def ($field or $zz)
 * @param array $values
 * @return array $values (translated)
 */
function zz_translate($def, $values) {
	if (empty($values)) return $values;
	if (empty($def['sql_translate'])) return $values;
	if (!is_array($def['sql_translate'])) {
		$def['sql_translate'] = [$def['sql_translate']];
	}
	foreach ($def['sql_translate'] as $id_field_name => $table) {
		if (is_numeric($id_field_name)) {
			$values = wrap_translate($values, $table);
		} else {
			$values = wrap_translate($values, $table, $id_field_name);
		}
	}
	foreach (array_keys($values) as $index) {
		if (!is_numeric($index)) {
			unset($values['wrap_source_language']);
			unset($values['wrap_source_content']);
			break;
		}
		unset($values[$index]['wrap_source_language']);
		unset($values[$index]['wrap_source_content']);
	}
	return $values;
}


/*
 * --------------------------------------------------------------------
 * O - Output functions
 * --------------------------------------------------------------------
 */


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
function zz_edit_query_string($query, $unwanted_keys = [], $new_keys = [], $and = '&amp;') {
	$query = str_replace('&amp;', '&', $query);
	if (substr($query, 0, 1) === '?') {
		$query = substr($query, 1);
	}
	if (!is_array($unwanted_keys)) $unwanted_keys = [$unwanted_keys];
	if (!is_array($new_keys)) $new_keys = [$new_keys];
	parse_str($query, $parts);
	// remove unwanted keys from URI
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
	foreach ($new_keys as $new_key => $new_value)
		$parts[$new_key] = $new_value; 
	// glue everything back together
	$query_string = http_build_query($parts, '', $and);
	if ($query_string) return '?'.$query_string; // URL without unwanted keys
	else return false;
}

/**
 * Escapes strings for HTML text (< >)
 * ENT_QUOTES: Escapes validated or custom set strings for HTML values (< > " ')
 *
 * @param string $string
 * @return string $string
 */
function zz_htmltag_escape($string, $flags = ENT_NOQUOTES) {
	if (!$string) return $string;
	$character_set = wrap_setting('character_set');
	if ($character_set === 'iso-8859-2') $character_set = 'ISO-8859-1';
	$new_string = @htmlspecialchars($string, $flags, $character_set);
	if (!$new_string) $new_string = htmlspecialchars($string, $flags, 'ISO-8859-1');
	$new_string = str_replace('&amp;', '&', $new_string);
	return $new_string;
}


/*
 * --------------------------------------------------------------------
 * R - Record functions used by several modules
 * --------------------------------------------------------------------
 */

/**
 * create a long field name for subtables
 *
 * @param string $table_name
 * @param int $rec
 * @param string $field_name
 * @return string
 */
function zz_long_fieldname($table_name, $rec, $field_name) {
	return sprintf('%s[%d][%s]', $table_name, $rec, $field_name);
}

/**
 * splits a string some_id[some_field] into an array 
 *
 * @param string $field_name 'some_id[some_field]'
 * @return	mixed false: splitting was impossible
 *			array: (0 => some_id, 1 => some_field);
 */
function zz_split_fieldname($field_name) {
	if (!strstr($field_name, '[')) return false;
	if (substr($field_name, -1) !== ']') return false;
	// split array in variable and key
	preg_match('/^(.+)\[(.+)\]$/', $field_name, $field_names);
	if (!isset($field_names[1]) OR !isset($field_names[2])) return false;
	return [$field_names[1], $field_names[2]];
}

/**
 * Returns the field definition for a given field name from a list of 
 * field definitions, optionally checks if a key exists
 *
 * @param array $fields field definitions ($zz['fields'] etc.)
 * @param string $field_name name of the field
 * @param string $key (optional); if set, will return field definition
 *		only if this key exists
 * @return mixed false: nothing was found; array $field = definition of field
 *		if $key is set: mixed value of this key
 */
function zz_get_fielddef($fields, $field_name, $key = false) {
	foreach ($fields as $field) {
		if (empty($field['field_name'])) continue;
		if ($field['field_name'] !== $field_name) continue;
		if (!$key) return $field;
		if (!in_array($key, array_keys($field))) return false;
		return $field[$key];
	}
	return false;
}

/**
 * Returns the field definition for a given subtable from a list of 
 * field definitions
 *
 * @param array $fields field definitions ($zz['fields'] etc.)
 * @param string $table name of the table
 * @return mixed false: nothing was found; array $field = definition of subtable
 */
function zz_get_subtable_fielddef($fields, $table) {
	foreach ($fields as $field) {
		if (!empty($field['table']) AND $field['table'] === $table)
			return $field;
		if (!empty($field['table_name']) AND $field['table_name'] === $table)
			return $field;
	}
	return false;
}

/**
 * get IDs for which fields are shown
 *
 * @param array $fields
 * @param int $tab
 * @param int $rec
 * @return array
 */
function zz_dependent_field_ids($fields, $tab, $rec) {
	global $zz_conf;
	static $dependent_ids = [];
	$unique = sprintf('%s/%d/%d', $zz_conf['id'], $tab, $rec);
	// save just for this request, therefore we use $zz_conf['id']
	if (!empty($dependent_ids[$unique]))
		return $dependent_ids[$unique];

	$dependent_ids[$unique] = [];
	foreach ($fields as $index => $field) {
		if (empty($field['dependent_fields'])) continue;
		if (!empty($field['enum'])) {
			// select with enum
			$records = [];
			foreach ($field['enum'] as $enum) {
				$records[][$enum] = $enum;
			}
		} elseif (!empty($field['fields'])) {
			// subtable
			$field_names = [];
			foreach ($field['dependent_fields'] as $field_no => $dependent_field) {
				$field_names[] = $dependent_field['field_name'];
			}
			$field_names = array_unique($field_names);
			if (count($field_names) > 1) {
				zz_error_log([
					'msg_dev' => 'It is not possible to depend on different fields in a single subtable (%s). Second field is ignored.',
					'msg_dev_args' => [implode(', ', $field_names)]
				]);
			}
			$field_names = reset($field_names);
			foreach ($field['fields'] as $subfield) {
				if (empty($subfield['field_name'])) continue;
				if ($subfield['field_name'] === $field_names) {
					$records = zz_db_fetch($subfield['sql'], '_dummy_', 'numeric');
				}
			}
		} else {
			// select with sql
			$records = zz_db_fetch($field['sql'], '_dummy_', 'numeric');
		}
		foreach ($field['dependent_fields'] as $field_no => $dependent_field) {
			if (empty($field['field_name']) AND !empty($field['table_name'])) {
				$dependent_ids[$unique][$field_no][$index]['source_table'] = $field['table_name'];
				$dependent_ids[$unique][$field_no][$index]['source_field_name'] = $dependent_field['field_name'];
			} else {
				$dependent_ids[$unique][$field_no][$index]['source_field_name'] = $field['field_name'];
			}
			$dependent_ids[$unique][$field_no][$index]['required'] = !empty($dependent_field['required']) ? true : false;
			foreach ($records as $record) {
				// is record hidden but a value needs to be set?
				if (!empty($dependent_field['value']) AND array_key_exists($dependent_field['value'], $record)
					AND ($record[$dependent_field['value']])) {
					parse_str($record[$dependent_field['value']], $parameters);
					if (!empty($parameters['value'])) {
						$dependent_ids[$unique][$field_no][$index]['set_values'][reset($record)] = $parameters['value'];
					}
				}
				if (empty($record[$dependent_field['if_selected']])) continue;
				$dependent_ids[$unique][$field_no][$index]['values'][] = reset($record);
			}
		}
	}
	return $dependent_ids[$unique];
}

/**
 * get value for field on which dependency is based upon
 * might be in any of record, POST, existing since write_once fields
 * do not send their value via POST
 *
 * @param array $dependency
 * @param array $my_rec
 * @param array $zz_tab
 * @return string
 */
function zz_dependent_value($dependency, $my_rec, $zz_tab) {
	$look_inside = ['record', 'POST', 'existing'];
	foreach ($look_inside as $type) {
		if (!empty($dependency['source_table'])) {
			foreach ($zz_tab as $my_tab_no => $my_tab) {
				if ($my_tab['table_name'] !== $dependency['source_table']) continue;
				if (empty($zz_tab[$my_tab_no][0][$type][$dependency['source_field_name']])) break;
				return $zz_tab[$my_tab_no][0][$type][$dependency['source_field_name']];
			}
		} elseif (!empty($my_rec[$type][$dependency['source_field_name']])) {
			return $my_rec[$type][$dependency['source_field_name']];
		}
	}
	return '';
}

/*
 * --------------------------------------------------------------------
 * V - Validation, preparation for database
 * --------------------------------------------------------------------
 */

/**
 * Checks whether values entered in text feld are valid records in other
 * tables and if true, replaces these values with the correct foreign ID
 *
 * @param array $my_rec
 * @param int $f Key of current field
 * @global array $zz_conf
 * @return array $my_rec changed keys:
 *		'fields'[$f], 'POST', 'POST-notvalid', 'validation'
 */
function zz_check_select($my_rec, $f) {
	global $zz_conf;

	// only for 'select'-fields with SQL query (not for enums neither for sets)
	if (empty($my_rec['fields'][$f]['sql'])) return $my_rec;
	// check if we have a value
	// check only for 0, might be problem, but 0 should always be there
	// if null -> accept it
	$field_name = $my_rec['fields'][$f]['field_name'];
	if (!$my_rec['POST'][$field_name]) {
		$my_rec['POST'][$field_name]
			= zz_field_select_value_hierarchy($my_rec['fields'][$f], $my_rec['POST'], $my_rec['id']['field_name']);
		return $my_rec;
	}
	if (is_string($my_rec['POST'][$field_name]) AND !trim($my_rec['POST'][$field_name])) {
		$my_rec['POST'][$field_name] = '';
		return $my_rec;
	}

	// check if we need to check
	if (is_string($my_rec['POST'][$field_name])
		AND intval($my_rec['POST'][$field_name]).'' !== $my_rec['POST'][$field_name].'')
		$my_rec['fields'][$f]['always_check_select'] = true;
	$check_string = true;
	if (empty($my_rec['fields'][$f]['always_check_select'])) {
		// with zzform_multi(), no form exists, so check per default yes
		// unless explicitly said not to check; with form its otherway round
		$check_string = $zz_conf['multi'] ? true : false;
		if (in_array($field_name, $my_rec['check_select_fields'])) {
			$check_string = !$check_string;
			if ($check_string) {
				// do not check multiple times if zz_validate() is called more than once
				$index = array_search($field_name, $my_rec['check_select_fields']);
				unset($my_rec['check_select_fields'][$index]);
			}
		}
	}
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	
	if ($check_string) {
		$my_rec['fields'][$f] = zz_check_select_id(
			$my_rec['fields'][$f], $my_rec['POST'][$field_name], $my_rec['id']
		);
		$my_rec['fields'][$f]['sql_before'] = $my_rec['fields'][$f]['sql'];
		$possible_values = $my_rec['fields'][$f]['possible_values'];
	} else {
		$possible_values = [$my_rec['POST'][$field_name]];
	}
	
	$error = false;
	if (!count($possible_values)) {
		// no records, user must re-enter values
		$my_rec['fields'][$f]['type'] = 'select';
		$my_rec['fields'][$f]['class'][] = 'reselect';
		$my_rec['fields'][$f]['suffix'] = '<br>'
			.wrap_text('No entry found. Try less characters.');
		$my_rec['fields'][$f]['mark_reselect'] = true;
		$my_rec['validation'] = false;
		$error = true;
	} elseif (count($possible_values) === 1) {
		// exactly one record found, so this is the value we want
		$my_rec['POST'][$field_name] = current($possible_values);
		$my_rec['POST-notvalid'][$field_name] = current($possible_values);
		// if other fields contain errors:
		if (!empty($my_rec['fields'][$f]['sql_new']))
			$my_rec['fields'][$f]['sql'] = $my_rec['fields'][$f]['sql_new'];
		if (!empty($my_rec['fields'][$f]['disabled_ids'])
			AND in_array($my_rec['POST'][$field_name], $my_rec['fields'][$f]['disabled_ids'])) {
			// @todo racing conditions possible
			$my_rec['validation'] = false;
			$my_rec['fields'][$f]['check_validation'] = false;
			$my_rec['fields'][$f]['suffix'] = ' '.wrap_text('Please make a different selection.');
			if (!empty($my_rec['fields'][$f]['disabled_ids_error_msg']))
				$my_rec['fields'][$f]['suffix'] .= ' '.wrap_text($my_rec['fields'][$f]['disabled_ids_error_msg']);
		} elseif (!$check_string) {
			$sql_fields = wrap_edit_sql($my_rec['fields'][$f]['sql'], 'SELECT', '', 'list');
			$sql = wrap_edit_sql($my_rec['fields'][$f]['sql'], 'WHERE', sprintf(
				'%s = %d', $sql_fields[0]['field_name'], $my_rec['POST'][$field_name]
			));
			$exists = wrap_db_fetch($sql, '', 'single value');
			if (!$exists) {
				$my_rec['validation'] = false;
				$my_rec['fields'][$f]['check_validation'] = false;
				$my_rec['fields'][$f]['suffix'] = ' '.wrap_text('Please make a different selection.');
			}
		}
	} elseif (count($possible_values) <= $my_rec['fields'][$f]['max_select']) {
		// let user reselect value from dropdown select
		$my_rec['fields'][$f]['type'] = 'select';
		$my_rec['fields'][$f]['sql'] = $my_rec['fields'][$f]['sql_new'];
		$my_rec['fields'][$f]['class'][] = 'reselect';
		if (!empty($my_rec['fields'][$f]['show_hierarchy'])) {
			// since this is only a part of the list, hierarchy does not make sense
			if (!isset($my_rec['fields'][$f]['sql_ignore'])) {
				$my_rec['fields'][$f]['sql_ignore'] = [];
			} elseif (!is_array($my_rec['fields'][$f]['sql_ignore'])) {
				$my_rec['fields'][$f]['sql_ignore'] = [$my_rec['fields'][$f]['sql_ignore']];
			}
			$my_rec['fields'][$f]['sql_ignore'][] = $my_rec['fields'][$f]['show_hierarchy'];
			$my_rec['fields'][$f]['show_hierarchy'] = false;
		}
		$my_rec['fields'][$f]['mark_reselect'] = true;
		$my_rec['validation'] = false;
		$error = true;
	} elseif (count($possible_values)) {
		// still too many records, require more characters
		$my_rec['fields'][$f]['default'] = 'reselect';
		$my_rec['fields'][$f]['class'][] = 'reselect';
		$my_rec['fields'][$f]['suffix'] = ' '.wrap_text('Please enter more characters.');
		$my_rec['fields'][$f]['mark_reselect'] = true;
		$my_rec['validation'] = false;
		$error = true;
	} else {
		$my_rec['fields'][$f]['class'][] = 'error';
		$my_rec['fields'][$f]['check_validation'] = false;
		$my_rec['validation'] = false;
		$error = true;
	}
	if ($error AND $zz_conf['multi']) {
		if (count($possible_values) > 1) {
			$errormsg = 'Multiple records matching (maybe set "ids" for zzform_multi?)';
		} else {
			$errormsg = 'No entry found';
		}
		zz_error_log([
			'msg_dev' => '%s: value %s in field %s. <br>SQL: %s',
			'msg_dev_args' => [$errormsg, $my_rec['POST'][$field_name], $field_name, $my_rec['fields'][$f]['sql_new']]
		]);
	}
	return zz_return($my_rec);
}

/**
 * check if there's a hierarchy ID that should be used instead of an empty value
 *
 * @param array $field
 * @param array $record
 * @param string $id_field_name
 * @return mixed
 */
function zz_field_select_value_hierarchy($field, $record, $id_field_name) {
	if (empty($field['show_hierarchy_subtree'])) return '';
	if (empty($field['show_hierarchy_use_top_value_instead_NULL'])) return '';
	if (!empty($field['show_hierarchy_same_table']) AND !empty($record)
		AND array_key_exists($id_field_name, $record)) {
		if ($record[$id_field_name] === $field['show_hierarchy_subtree']) return '';
	}
	return $field['show_hierarchy_subtree'];
}

/**
 * Query possible values from database for a given SQL query and a given value
 *
 * @param array $field
 *		'sql', 'sql_fieldnames_ignore', 'concat_fields', 'show_hierarchy',
 *		'show_hierarchy_same_table', 'show_hierarchy_subtree'
 *	(for collation only:)
 *		'search', 'display_field', 'field_name', 'character_set',
 *		'sql_character_set', 'sql_table'
 * @param string $postvalue
 * @param array $id = $zz_tab[$tab][$rec]['id'] optional; for hierarchy in same table
 * @global array $zz_conf bool 'multi'
 * @return array $field
 *		'possible_values', 'sql_fieldnames', 'sql_new'
 */
function zz_check_select_id($field, $postvalue, $id = []) {
	global $zz_conf;
	
	if (!empty($field['select_checked'])) return $field;
	// 1. get field names from SQL query
	if (empty($field['sql_fieldnames'])) $field['sql_fieldnames'] = [];
	$sql_fieldnames = wrap_edit_sql($field['sql'], 'SELECT', '', 'list');
	foreach ($sql_fieldnames as $index => $sql_fieldname) {
		if (!empty($field['show_hierarchy'])
			AND $sql_fieldname['field_name'] === $field['show_hierarchy']) {
			// do not search in show_hierarchy as this field is there for 
			// presentation only and might be removed below!
			continue;
		}
		// write trimmed value back to sql_fieldnames
		$field['sql_fieldnames'][$index] = $sql_fieldname['field_name'];
	}
	if (!empty($field['sql_format'])) {
		// formatted fields look different, remove
		foreach (array_keys($field['sql_format']) as $index)
			unset($field['sql_fieldnames'][$index]);
	}

	// 2. get posted values, field by field
	$concat = zz_select_concat($field);
	$postvalues = explode($concat, $postvalue);
	if (!empty($field['sql_format']) AND count($postvalues) === count($field['sql_fieldnames'])) {
		foreach (array_keys($postvalues) as $index) {
			if (array_key_exists($index + 1, $field['sql_fieldnames'])) continue;
			unset($postvalues[$index]);
		}
	}

	$use_single_comparison = false;
	if (!empty($field['sql_fieldnames'][0]))
		// save for later use
		$id_field_name = $field['sql_fieldnames'][0];
	if (substr($postvalue, -1) !== ' ' AND !$zz_conf['multi']) {
		$search_equal = false;
		// if there is a space at the end of the string, don't do LIKE 
		// with %!
		if (str_starts_with(trim($field['sql']), 'SHOW')) {
			$likestring = '%s LIKE %s"%%%s%%"';
		} else {
			$likestring = ' LIKE %s"%%%s%%"';
		}
	} else {
		$search_equal = true;
		if (str_starts_with(trim($field['sql']), 'SHOW')) {
			$likestring = '%s = %s"%s"';
		} else {
			$likestring = ' = %s"%s"';
		}
		if (count($field['sql_fieldnames']) -1 === count($postvalues)
			AND !$zz_conf['multi']) {
			// multi normally sends ID
			// get rid of ID field name, it's first in list
			// do not use array_shift here because index is needed below
			unset($field['sql_fieldnames'][0]);
			$use_single_comparison = true;
		}
	}

	$wheresql = '';
	$sql_fieldnames = $field['sql_fieldnames'];
	if (!empty($field['sql_fieldnames_ignore'])) {
		$sql_fieldnames = array_diff($sql_fieldnames, $field['sql_fieldnames_ignore']);
	}
	foreach ($postvalues as $value) {
		// preg_match: "... ", extra space will be added in zz_draw_select!
		$my_likestring = $likestring;
		if (preg_match('/^(.+?) *… *$/', $value, $short_value)) {
			// reduces string with dots which come from values which have 
			// been cut beforehands, use LIKE!
			$value = $short_value[1];
			$my_likestring = ' LIKE %s"%s%%"';
		}
		if (str_starts_with($my_likestring, ' ')) {
			if (strstr(trim($value), ' ')) {
				// remove tags, remove line breaks when comparing
				$my_likestring = sprintf(
					'REPLACE(REPLACE(REPLACE(%%s, "\r\n", " "), "\n", " "), "%s", " ") %s'
					, $concat, $my_likestring
				);
			} else {
				$my_likestring = sprintf('%%s %s', $my_likestring);
			}
		}
		// maybe there is no index 0, therefore we need a new variable $i
		$i = 0;
		foreach ($sql_fieldnames as $index => $sql_fieldname) {
			// first field must be id field, so if value is not numeric, ignore it
			if (!$index AND !is_numeric(trim($value))) continue;
			// don't trim value here permanently (or you'll have a problem with
			// reselect)
			if (is_numeric(trim($value))) {
				// no character set needed for numeric values
				$collation = '';
			} else {
				$collation = zz_db_field_collation(
					'reselect', $field, false, $index
				);
			}
			if (!$wheresql) $wheresql .= '(';
			elseif (!$i) $wheresql .= ' ) AND (';
			elseif ($use_single_comparison) $wheresql .= ' AND ';
			else $wheresql .= ' OR ';

			$wheresql .= sprintf($my_likestring, $sql_fieldname, $collation,
				wrap_db_escape(trim($value)));
			if (!empty($field['sql_translate'])) {
				$condition = zz_check_select_translated($field, $sql_fieldname, $value, $search_equal);
				if ($condition) $wheresql .= sprintf(' OR %s', $condition);
			}
			if ($use_single_comparison) {
				unset ($sql_fieldnames[$index]);
				continue 2;
			}
			$i++;
		}
	}
	if ($wheresql) $wheresql .= ')';
	if (!empty($field['show_hierarchy_same_table']) AND !empty($id['value'])) {
		$id_field_name = $field['id_field_name'] ?? $id['field_name'];
		$wheresql .= sprintf(' AND %s != %d', $id_field_name, $id['value']);
	}
	$ids = zz_hierarchy_subtree_ids($field);
	if ($ids) {
		// just allow chosing of records under the ID set in 'show_hierarchy_subtree'
		if (empty($field['show_hierarchy_use_top_value_instead_NULL']))
			unset($ids[0]); // top hierarchy ID
		$wheresql .= sprintf(' AND %s IN (%s)',
			$id_field_name, implode(',', $ids)
		);
	}
	if ($wheresql) {
		$field['sql_new'] = wrap_edit_sql($field['sql'], 'WHERE', $wheresql);
	} elseif (str_starts_with(trim($field['sql']), 'SHOW')) {
		$likestring = str_replace('=', 'LIKE', $likestring);
		$field['sql_new'] = sprintf($likestring, $field['sql'], '', trim($value));
	} else {
		$field['sql_new'] = $field['sql'];
	}
	$field['possible_values'] = zz_db_fetch(
		$field['sql_new'], 'dummy_id', 'single value'
	);
	$field['select_checked'] = true;
	return $field;
}

/**
 * format field values
 *
 * @param array $line
 * @param array $field
 */
function zz_field_select_format($line, $field) {
	if (empty($field['sql_format'])) return $line;
	$line_keys = array_keys($line);
	foreach ($field['sql_format'] as $index => $format) {
		if (!isset($line_keys[$index])) continue;
		$line[$line_keys[$index]] = $format($line[$line_keys[$index]]);
	}
	return $line;
}

/**
 * check if there are matches for a query in its translated values as well
 *
 * @param array $field
 *		array 'sql_translate' e. g. ['country_id' => 'countries']
 *		string 'key_field_name' OR 'field_name'
 * @param string $sql_fieldname field name to look for
 * @param string $value value to look for
 * @param bool $search_equal search with = or LIKE
 * @return string SQL query part with ID or empty
 */
function zz_check_select_translated($field, $sql_fieldname, $value, $search_equal) {
	if (!wrap_setting('translate_fields')) return '';
	require_once __DIR__.'/translations.inc.php'; // for XHR

	// set conditions
	$tconditions = [];
	if ($search_equal) {
		$tconditions[] = sprintf('translation = "%s"', wrap_db_escape(trim($value)));
	} else {
		$tconditions[] = sprintf('translation LIKE "%%%s%%"', wrap_db_escape($value));
	}

	// set query
	$sql_translations = wrap_sql_query('default_translations');
	if (!$sql_translations) return '';
	$sql_translations = str_replace('AND field_id IN (%s)', '', $sql_translations);

	// get translations
	$translationfields = zz_translations_fields($field['sql_translate']);
	$records = [];
	foreach ($translationfields as $type => $t_fields) {
		foreach ($t_fields as $t_field) {
			if ($t_field['field_name'] !== $sql_fieldname) continue;
			$sql = sprintf($sql_translations,
				$type, implode(',', array_keys($t_fields)), wrap_setting('lang')
			);
			$sql = wrap_edit_sql($sql, 'WHERE', implode(' OR ', $tconditions));
			$records += wrap_db_fetch($sql, '_dummy_', 'numeric');
		}
	}
	if (!$records) return '';

	// return IDs
	$field_ids = [];
	foreach ($records as $record) {
		$field_ids[$record['field_id']] = $record['field_id'];
	}
	$my_fieldname = $field['key_field_name'] ?? $field['field_name'];
	return sprintf('%s IN (%s)', $my_fieldname, implode(',', $field_ids));
}

/**
 * replace some values for SELECT or INPUT with check_select
 *
 * @param string $value
 * @param string $concat
 * @return string
 */
function zz_select_escape_value($value, $concat) {
	if (!$value) return $value;
	$strings = ["\n", "\r"];
	if ($concat) array_unshift($strings, $concat);
	foreach ($strings as $string) {
		if (strpos($value, $string)) {
			$value = str_replace($string, ' ', $value);
		}
	}
	while (strpos($value, '  ')) {
		$value = str_replace('  ', ' ', $value);
	}
	return $value;
}

/**
 * get concat value
 *
 * @param array $field field definition
 * @return string
 */
function zz_select_concat($field) {
	$concat = isset($field['concat_fields']) ? $field['concat_fields'] : ' | ';
	return $concat;
}

/**
 * reformat hierarchical field names to array
 * table_name[0][field_name]
 *
 * @param array $post
 * @param string $field_name
 * @param string $value (NULL: get value, other: set value)
 * @return array
 */
function zz_check_values($post, $field_name, $value = NULL) {
	if (strstr($field_name, '[')) {
		$fields = explode('[', $field_name);
		foreach ($fields as $index => $field) {
			if (!$index) continue;
			$fields[$index] = trim($field, ']');
		}
		if (count($fields) === 3) {
			if (is_null($value)) {
				return $post[$fields[0]][$fields[1]][$fields[2]];
			} else {
				$post[$fields[0]][$fields[1]][$fields[2]] = $value;
			}
		}
	} else {
		if (is_null($value)) {
			return($post[$field_name]);
		} else {
			$post[$field_name] = $value;
		}
	}
	return $post;
}

/**
 * check if a field has a default value
 *
 * @param array $field
 * @return bool true = has default value
 */
function zz_has_default($field) {
	if (!isset($field['default'])) return false;
	if ($field['default'] === 0) return true;
	if (!empty($field['default'])) return true;
	return false;
}
