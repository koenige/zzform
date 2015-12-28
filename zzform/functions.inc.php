<?php 

/**
 * zzform
 * Miscellaneous functions
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
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
 * @copyright Copyright © 2004-2015 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/*
 * --------------------------------------------------------------------
 * C - Core functions
 * --------------------------------------------------------------------
 */

/**
 * Adds modules to zzform
 *
 * @param array $modules			list of modules to be added
 * @param string $path				path to where modules can be found
 * @return array $mod
 */
function zz_add_modules($modules, $path) {
	global $zz_conf;
	$debug_started = false;
	if (!empty($zz_conf['modules']['debug'])) {
		zz_debug('start', __FUNCTION__);
		$debug_started = true;
	}
//	initialize variables
	$mod = array();
	$add = false;

//	import modules
	foreach ($modules as $module) {
		if (!empty($zz_conf['modules'][$module])) {
			// we got that already
			$mod[$module] = true;
			continue;
		}
		if ($module === 'debug' AND empty($zz_conf['debug'])) {
			$mod[$module] = false;
			continue;
		}
		if (file_exists($path.'/'.$module.'.inc.php')) {
			include_once $path.'/'.$module.'.inc.php';
			$mod[$module] = true;
			$add = true;
		} elseif (file_exists($path.'/'.$module.'.php')) {
			include_once $path.'/'.$module.'.php';
			$mod[$module] = true;
			$add = true;
		} else {
			$mod[$module] = false;
		}
		if (!empty($mod['debug']) OR !empty($zz_conf['modules']['debug'])) {
			if (!$debug_started) {
				zz_debug('start', __FUNCTION__);
				$debug_started = true;
			}
			if ($mod[$module]) $debug_msg = 'Module %s included';
			else $debug_msg = 'Module %s not included';
			zz_debug(sprintf($debug_msg, $module));
		}
		$config_function = sprintf('zz_%s_config', $module);
		if ($add AND function_exists($config_function)) {
			$config_function();
		}
	}

	// int_modules/ext_modules have debug module at different place
	if ($debug_started) zz_debug('end');
	return $mod;
}

/**
 * includes modules which are dependent on $zz-table definition
 *
 * @param array $zz Table definition
 *		checking 'conditions', 'fields'
 * @global array $zz_conf
 *		array $zz_conf['modules'] will be written, $zz_conf['export'] if
 *		applicable
 *		checking 'translations_of_fields', 'generate_output'
 * @return void
 */
function zz_dependent_modules($zz) {
	global $zz_conf;

	// check if POST is too big, then it will be empty
	$zz_conf['int']['post_too_big'] = $zz_conf['generate_output'] ? zzform_post_too_big() : false;

	$modules = array('translations', 'conditions', 'geo', 'export', 'upload');
	foreach ($modules as $index => $module) {
		// continue if module already loaded
		if (!empty($zz_conf['modules'][$module])) continue;
		$zz_conf['modules'][$module] = false;
		switch ($module) {
		case 'translations':
			if (empty($zz_conf['translations_of_fields'])) {
				unset($modules[$index]);
			}
			break;
		case 'conditions':
			if (!empty($zz['conditions'])) break;
			if (!empty($zz['if'])) break;
			if (!empty($zz['unless'])) break;
			foreach ($zz['fields'] as $field) {
				// Look for shortcuts for conditions
				if (isset($field['if'])) break 2;
				if (isset($field['unless'])) break 2;
			}
			unset($modules[$index]);
			break;
		case 'geo':
			$geo = false;
			foreach ($zz['fields'] as $field) {
				if (!isset($field['geocode'])) continue;
				$geo = true;
				break;
			}
			if (zz_module_fieldcheck($zz, 'number_type', 'latitude')) {
				$geo = true;
			} elseif (zz_module_fieldcheck($zz, 'number_type', 'longitude')) {
				$geo = true;
			} elseif (zz_module_fieldcheck($zz, 'type', 'geo_point')) {
				$geo = true;
			}
			if (!$geo) unset($modules[$index]);
			break;
		case 'export':
			if ($zz_conf['generate_output'] === false) {
				$zz_conf['export'] = false;
				unset($modules[$index]);
				break;
			}
			$export = false;
			if (!empty($zz_conf['export'])) {
				$export = true;
				break;
			}
			$conditionals = array('if', 'unless');
			foreach ($conditionals as $conditional) {
				if (empty($zz_conf[$conditional])) continue;
				foreach ($zz_conf[$conditional] as $condition) {
					if (!empty($condition['export'])) {
						$export = true;
						break;
					}
				}
			}
			if (!$export) unset($modules[$index]);
			break;
		case 'upload':
			// check if there was an upload, so we need this module
			if ($zz_conf['int']['post_too_big']) break;
			if (!empty($_FILES)) break;
			if (!zz_module_fieldcheck($zz, 'type', 'upload_image')) {
				unset($modules[$index]);
			}
			break;
		}
	}
	$zz_conf['modules'] = array_merge(
		$zz_conf['modules'], zz_add_modules($modules, $zz_conf['dir_inc'])
	);
	if (!empty($GLOBALS['zz_saved']['conf'])) {
		$GLOBALS['zz_saved']['conf']['modules'] = $zz_conf['modules'];
	}
	return true;
}

/**
 * checks whether fields contain a value for a certain key
 *
 * @param array @zz
 * @param string @key
 * @param string $type field type
 * @return
 */
function zz_module_fieldcheck($zz, $key, $type) {
	foreach ($zz['fields'] as $field) {
		if (!empty($field[$key]) AND $field[$key] === $type) {
			return true;
		}
		if (!empty($field['if'])) {
			foreach ($field['if'] as $condfield) {
				if (!empty($condfield[$key]) AND $condfield[$key] === $type) {
					return true;
				}
			}
		}
		if (empty($field['fields'])) continue;
		foreach ($field['fields'] as $index => $subfield) {
			if (!is_array($subfield)) continue;
			if (!empty($subfield[$key]) AND $subfield[$key] === $type) {
				return true;
			}
			if (empty($subfield['if'])) continue;
			foreach ($subfield['if'] as $condfield) {
				if (!empty($condfield[$key]) AND $condfield[$key] === $type) {
					return true;
				}
			}
		}
	}
	return false;
}

/**
 * define URL of script
 *
 * @param string $url_self ($zz_conf['url_self'])
 * @return array $url (= $zz_conf['int']['url'])
 *		'self' = own URL for form action
 *		'?&' = either ? or & to append further query strings
 *		'qs' = query string part of URL
 *		'qs_zzform' = query string part of zzform of URL
 *		'full' = full URL with base and request path
 */
function zz_get_url_self($url_self) {
	// some basic settings
	$url['self'] = $url_self;
	// normal situation: there is no query string in the base url, 
	// so add query string starting ?
	$url['?&'] = '?';
	// no base query string which belongs url_self
	$url['qs'] = '';
	$url['scheme'] = (isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] === 'on') 
		? 'https'
		: 'http';
	$host = preg_match('/^[a-zA-Z0-9-\.]+$/', $_SERVER['HTTP_HOST'])
		? $_SERVER['HTTP_HOST']
		: $_SERVER['SERVER_NAME'];
	$url['base'] = $url['scheme'].'://'.$host;

	// get own URI
	$my_uri = parse_url($url['base'].$_SERVER['REQUEST_URI']);

	if (!$url_self) {
		// nothing was defined, we just do it as we like
		$url['self'] = $my_uri['path'];
		// zzform query string
		$url['qs_zzform'] = !empty($my_uri['query']) ? '?'.$my_uri['query'] : '';
		$url['full'] = $url['base'].$url['self'];
		return $url;
	}

	// it's possible to use url_self without http://hostname, so check for that
	$examplebase = (substr($url_self, 0, 1) === '/') ? $url['base'] : '';
	$base_uri = parse_url($examplebase.$url_self);
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
	}
	if (!empty($my_uri['query']) AND !empty($base_uri['query'])) {
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
	} elseif (!empty($my_uri['query']))
		$url['qs_zzform'] = '&'.$my_uri['query'];
	else
		$url['qs_zzform'] = '';
	return $url;
}

/**
 * checks if there is a parameter in the URL (where, add, filter) that
 * results in a WHERE condition applied to the main SQL query
 *
 * @param array $zz ('where', like in $_GET)
 * @param array $zz_var
 * @global array $zz_conf
 *		'filter' will be checked for 'where'-filter and set if there is one
 * @return array array $zz, $zz_var
 *		'where_condition' (conditions set by where, add and filter), 'zz_fields'
 *		(values for fields depending on where conditions)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_get_where_conditions($zz, $zz_var) {
	global $zz_conf;

	// WHERE: Add with suggested values
	$zz_var['where_condition'] = zz_check_get_array('where', 'field_name');
	if (!empty($zz['where'])) {
		// $zz['where'] will be merged to $_GET['where'], identical keys
		// will be overwritten
		$zz_var['where_condition'] = array_merge(
			$zz_var['where_condition'], $zz['where']
		);
	}

	// ADD: overwrite write_once with values, in case there are identical fields
	$add = zz_check_get_array('add', 'field_name');
	if ($add) {
		$zz_var['where_condition'] = array_merge(
			$zz_var['where_condition'], $add
		);
		foreach ($add as $key => $value) {
			$zz_var['zz_fields'][$key]['value'] = $value;
			$zz_var['zz_fields'][$key]['type'] = 'hidden';
		}
	}

	// FILTER: check if there's a 'where'-filter
	foreach ($zz['filter'] AS $index => $filter) {
		if (!empty($filter['where'])
			AND !empty($zz_var['where_condition'])
			AND in_array($filter['where'], array_keys($zz_var['where_condition']))
		) {
			// where-filter makes no sense since already one of the values
			// is filtered by WHERE filter
			// unless it is an add where_condition
			if (!in_array($filter['where'], array_keys($add))) {
				unset($zz['filter'][$index]);
			}
		}
		if ($filter['type'] !== 'where') continue;
		if (!empty($zz_var['filters'][$filter['identifier']])) {
			$zz_var['where_condition'][$filter['where']] 
				= $zz_var['filters'][$filter['identifier']];
		}
		// 'where'-filters are beyond that 'list'-filters
		$zz['filter'][$index]['type'] = 'list';
	}

	return array($zz, $zz_var);
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
 * @return mixed
 * @todo use this function in more places
 */
function zz_check_get_array($key, $type, $values = array()) {
	global $zz_conf;
	$return = $type === 'field_name' ? array() : '';
	if (!isset($_GET[$key])) return $return;

	$error_in = array();
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

	$zz_conf['int']['http_status'] = 404;
	$unwanted_keys = array();
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
 * @param array $zz_conf
 * @return array $zz_conf_record subset of $zz_conf
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_record_conf($zz_conf) {
	$wanted_keys = array(
		'int[access]', 'edit', 'delete', 'add', 'view', 'if', 'details', 
		'details_url', 'details_base', 'details_target', 'details_referer',
		'details_sql', 'max_select', 'max_select_val_len', 'copy', 'no_ok',
		'cancel_link', 'unless'
	);
	$zz_conf_record = array();
	foreach ($wanted_keys as $key) {
		if (substr($key, 0, 4) === 'int[' AND substr($key, -1) === ']') {
			$key = substr($key, 4, -1);
			$zz_conf_record['int'][$key] = isset($zz_conf['int'][$key])
				? $zz_conf['int'][$key] : ''; 
		} elseif (isset($zz_conf[$key])) {
			$zz_conf_record[$key] = $zz_conf[$key];
		}
	}
	return $zz_conf_record;
}

/**
 * checks filter, sets default values and identifier
 *
 * @param array $zz
 * @return array array $zz['filter'], $filter = $zz_var['filters']
 * @global array $zz_conf
 */
function zz_filter_defaults($zz) {
	global $zz_conf;
	// initialize, don't return because we'll check for $_GET later
	if (empty($zz['filter'])) {
		$zz['filter'] = array();
	}
	if ($zz['filter'] AND !empty($_GET['filter']) AND is_array($_GET['filter'])) {
		$filter_params = $_GET['filter'];
	} else {
		// just in case it's a ?filter -request with no filter set
		$filter_params = array();
		if (isset($_GET['filter'])) {
			$zz_conf['int']['http_status'] = 404;
			$unwanted_keys = array('filter');
			$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string($zz_conf['int']['url']['qs_zzform'], $unwanted_keys);
		}
	}
	$identifiers = array();

	// if there are filters:
	// initialize filter, set defaults
	foreach ($zz['filter'] AS $index => $filter) {
		if (!$filter) {
			unset($zz['filter'][$index]);
			continue;
		}
		// get identifier from title if not set
		if (empty($filter['identifier'])) {
			$filter['identifier'] = urlencode(strtolower($filter['title']));
			$zz['filter'][$index]['identifier'] = $filter['identifier'];
		}
		$identifiers[] = $filter['identifier'];
		// set default filter, default default filter is 'all'
		if (empty($filter['default_selection'])) continue;
		if (isset($filter_params[$filter['identifier']])) continue;
		$filter_params[$filter['identifier']] = is_array($filter['default_selection'])
			? key($filter['default_selection'])
			: $filter['default_selection'];
	}

	// check for invalid filters
	$zz_conf['int']['invalid_filters'] = array();
	foreach (array_keys($filter_params) AS $identifier) {
		if (in_array($identifier, $identifiers)) continue;
		$zz_conf['int']['http_status'] = 404;
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
			$zz_conf['int']['url']['qs_zzform'], array('filter['.$identifier.']')
		);
		$zz_conf['int']['invalid_filters'][] = zz_htmltag_escape($identifier);
		// get rid of filter
		unset($filter_params[$identifier]);
	}
	return array($zz['filter'], $filter_params);
}

/**
 * checks filter, gets selection, sets hierarchy values
 *
 * @param array $zz
 * @param array $filter_params = $zz_var['filters']
 * @global array $zz_error
 * @return array ($zz, 'hierarchy' will be changed if corresponding filter,
 *	'filter', might be changed)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_apply_filter($zz, $filter_params) {
	global $zz_error;
	if (!$zz['filter']) return $zz;

	// set filter for complete form
	foreach ($zz['filter'] AS $index => &$filter) {
		if (!isset($filter['selection'])) $filter['selection'] = array();
		// get 'selection' if sql query is given
		if (!empty($filter['sql'])) {
			if (!empty($filter['depends_on']) 
			AND isset($zz['filter'][$filter['depends_on']])) {
				$depends_on = $zz['filter'][$filter['depends_on']];
				if (!empty($filter_params[$depends_on['identifier']])) {
					$where = $depends_on['where'].' = '.zz_db_escape(
						$filter_params[$depends_on['identifier']]
					);
					$filter['sql'] = zz_edit_sql($filter['sql'], 'WHERE', $where);
				}
				$zz['filter'][$filter['depends_on']]['subfilter'][] = $index;
			}
			$elements = zz_db_fetch($filter['sql'], '_dummy_id_', 'key/value');
			if ($zz_error['error']) continue;
			// don't show filter if we have only one element
			if (count($elements) <= 1) {
				unset($filter);
				continue;
			}
			foreach ($elements as $key => $value) {
				if (is_null($value)) {
					$filter['selection']['NULL'] = zz_text('(no value)');
				} else {
					$filter['selection'][$key] = $value;
				}
			}
		}
		if (!$filter['selection'] AND !empty($filter['default_selection'])) {
			if (is_array($filter['default_selection'])) {
				$filter['selection'] = $filter['default_selection'];
			} else {
				$filter['selection'] = array(
					$filter['default_selection'] => $filter['default_selection']
				);
			}
		}
		if (!$filter_params) continue;
		if (!in_array($filter['identifier'], array_keys($filter_params))) continue;
		if ($filter['type'] !== 'show_hierarchy') continue;

		$selection = zz_in_array_str(
			$filter_params[$filter['identifier']], array_keys($filter['selection'])
		);
		if ($selection) {
			if (!empty($zz['list']['hierarchy'])) {
				$zz['list']['hierarchy']['id'] = $selection;
			}
			// @todo: if user searches something, the hierarchical view
			// will be ignored and therefore this hierarchical filter does
			// not work. think about a better solution.
		} else {
			$zz_error[] = array(
				'msg' => sprintf(zz_text('This filter does not exist: %s'),
					zz_htmltag_escape($filter_params[$filter['identifier']])),
				'level' => E_USER_NOTICE,
				'status' => 404
			);
			$zz_error['error'] = true;
		}
	}
	return $zz;
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
 * @param array $zz_var
 * @return array
 *		array $zz
 *		array $zz_var
 */
function zz_where_conditions($zz, $zz_var) {
	global $zz_conf;

	// get 'where_conditions' for SQL query from GET add, filter oder where
	// get 'zz_fields' from GET add
	list($zz, $zz_var) = zz_get_where_conditions($zz, $zz_var);

	// apply where conditions to SQL query
	$zz['sql_without_where'] = $zz['sql'];
	$table_for_where = isset($zz['table_for_where']) ? $zz['table_for_where'] : array();
	list($zz['sql'], $zz_var) = zz_apply_where_conditions(
		$zz_var, $zz['sql'], $zz['table'], $table_for_where
	);
	// where with unique ID: remove filters, they do not make sense here
	// (single record will be shown)
	if ($zz_conf['int']['where_with_unique_id']) {
		$zz['filter'] = array();
		$zz_var['filters'] = array();
	}
	if (isset($zz['sqlrecord'])) {
		list($zz['sqlrecord'], $zz_var) = zz_apply_where_conditions(
			$zz_var, $zz['sqlrecord'], $zz['table'], $table_for_where
		);
	}
	if (!empty($zz_var['where'])) {
		// shortcout sqlcount is no longer possible
		unset($zz['sqlcount']);
	}

	// if GET add already set some values, merge them to field
	// definition
	foreach (array_keys($zz['fields']) as $no) {
		if (empty($zz['fields'][$no]['field_name'])) continue;
		if (empty($zz_var['zz_fields'][$zz['fields'][$no]['field_name']])) continue;
		// get old type definition and use it as type_detail if not set
		if (empty($zz['fields'][$no]['type_detail'])) {
			$zz['fields'][$no]['type_detail'] = $zz['fields'][$no]['type'];
		}
		$zz['fields'][$no] = array_merge($zz['fields'][$no], 
			$zz_var['zz_fields'][$zz['fields'][$no]['field_name']]);
	}

	return array($zz, $zz_var);
}

/**
 * applies where conditions to get different sql query, id values and some
 * further variables for nice headings etc.
 *
 * @param array $zz_var
 *		'where_condition' from zz_get_where_conditions(), 'unique_fields'
 * @param string $sql Main SQL query
 * @param string $table Name of main table
 * @param array $table_for_where (optional)
 * @global array $zz_conf checks for 'modules'['debug']
 *		change: 'where_with_unique_id'
 * @return array
 *		string $sql = modified main query (if applicable)
 *		array $zz_var
 *			'where', 'where_condition', 'id', 
 *			'unique_fields'
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @see zz_get_where_conditions(), zz_get_unique_fields()
 */
function zz_apply_where_conditions($zz_var, $sql, $table, $table_for_where = array()) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	// set some keys
	$zz_var['where'] = false;
	$zz_conf['int']['where_with_unique_id'] = false;
	
	if (!$zz_var['where_condition']) return zz_return(array($sql, $zz_var));

	foreach ($zz_var['where_condition'] as $field_name => $value) {
		$submitted_field_name = $field_name;
		if (preg_match('/[a-z_]+\(.+\)/i', trim($field_name))) {
			// check if field_name comprises some function
			// CONCAT(bla, blubb), do not change this
			$table_name = '';
		} elseif (strstr($field_name, '.')) {
			// check if field_name comprises table_name
			$field_tab = explode('.', $field_name);
			$table_name = zz_db_escape($field_tab[0]);
			$field_name = zz_db_escape($field_tab[1]);
			unset($field_tab);
		} else {
			// allows you to set a different (or none at all) table name 
			// for WHERE queries
			if (isset($table_for_where[$field_name]))
				$table_name = $table_for_where[$field_name];
			else
				$table_name = $table;
			$field_name = zz_db_escape($field_name);
		}
		$field_reference = $table_name ? $table_name.'.'.$field_name : $field_name;
		// restrict list view to where, but not to add
		if (empty($_GET['add'][$submitted_field_name])) {
			if (!empty($zz_var['where_condition'][$field_name])
				AND $zz_var['where_condition'][$field_name] === 'NULL')
			{
				$sql = zz_edit_sql($sql, 'WHERE', 
					'ISNULL('.$field_reference.')');
				continue; // don't use NULL as where variable!
			} elseif (!empty($zz_var['where_condition'][$field_name])
				AND $zz_var['where_condition'][$field_name] === '!NULL')
			{
				$sql = zz_edit_sql($sql, 'WHERE', 
					'!ISNULL('.$field_reference.')');
				continue; // don't use !NULL as where variable!
			} else {
				$sql = zz_edit_sql($sql, 'WHERE', 
					$field_reference.' = "'.zz_db_escape($value).'"');
			}
		}

// hier auch fuer write_once
		$zz_var['where'][$table_name][$field_name] = $value;

		if ($field_name === $zz_var['id']['field_name']) {
			if (intval($value).'' === $value.'') {
				$zz_conf['int']['where_with_unique_id'] = true;
				$zz_var['id']['value'] = $value;
			} else {
				$zz_var['id']['invalid_value'] = $value;
			}
		} elseif (in_array($field_name, array_keys($zz_var['unique_fields']))) {
			$zz_conf['int']['where_with_unique_id'] = true;
		}
	}
	// in case where is not combined with ID field but UNIQUE field
	// (e. g. identifier with UNIQUE KEY) retrieve value for ID field from 
	// database
	if (!$zz_var['id']['value'] AND $zz_conf['int']['where_with_unique_id']) {
		if ($zz_conf['modules']['debug']) zz_debug("where_conditions", $sql);
		$line = zz_db_fetch($sql, '', '', 'WHERE; ambiguous values in ID?');
		if ($line) {
			$zz_var['id']['value'] = $line[$zz_var['id']['field_name']];
//		} else {
//			$zz_error[] = array(
//				'msg_dev' => zz_text('Database error. 
//					This database has ambiguous values in ID field.'),
//				'level' => E_USER_ERROR
//			);
//			return zz_error(); // exit script
		}
		if (!$zz_var['id']['value']) $zz_conf['int']['where_with_unique_id'] = false;
	}
	
	return zz_return(array($sql, $zz_var));
}

/**
 * WHERE, 2nd part, write_once without values
 * 
 * write_once will be checked as well, without values
 * where is more important than write_once, so remove it from array if
 * there is a where equal to write_once
 * @param array $zz
 * @param array $zz_var
 * @return array $zz_var
 */
function zz_write_onces($zz, $zz_var) {
	foreach ($zz['fields'] as $field) {
		// get write once fields so we can base conditions (scope=values) on them
		if (empty($field['type'])) continue;
		if ($field['type'] !== 'write_once') continue;
		$field_name = $field['field_name'];
		if (!empty($zz_var['where_condition'][$field_name])) continue;

		$zz_var['write_once'][$field_name] = '';
		$zz_var['where_condition'][$field_name] = '';
		$zz_var['where'][$zz['table']][$field_name] = '';
	}
	return $zz_var;
}

/** 
 * Fills field definitions with default definitions and infos from database
 * 
 * @param array $fields
 * @param string $db_table [i. e. db_name.table]
 * @param bool $multiple_times marker for conditions
 * @param string $mode (optional, $ops['mode'])
 * @param string $action (optional, $zz_var['action'])
 * @return array $fields
 */
function zz_fill_out($fields, $db_table, $multiple_times = false, $mode = false, $action = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) {
		zz_debug('start', __FUNCTION__.$multiple_times);
	}
	static $defs;
	$hash = md5(serialize($fields).$db_table.$multiple_times.$mode);
	if (!empty($defs[$hash])) return zz_return($defs[$hash]);

	$to_translates = array(
		'title', 'explanation', 'explanation_top', 'title_append', 'title_tab'
	);

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
		if (!isset($fields[$no]['type'])) {
			// default type: text
			$fields[$no]['type'] = 'text';
		}
		if ($fields[$no]['type'] === 'write_once' AND empty($fields[$no]['type_detail'])) {
			$fields[$no]['type_detail'] = 'text';
		}
		if ($fields[$no]['type'] === 'id' AND !isset($fields[$no]['dont_sort'])) {
			// set dont_sort as a default for ID columns
			$fields[$no]['dont_sort'] = true;
		}
		if (!isset($fields[$no]['title'])) { // create title
			if (!isset($fields[$no]['field_name'])) {
				global $zz_error;
				$zz_error[] = array(
					'msg_dev' => 'Field definition incorrect: [No. '.$no.'] '
						.serialize($fields[$no])
				);
			}
			$fields[$no]['title'] = ucfirst($fields[$no]['field_name']);
			$fields[$no]['title'] = str_replace('_ID', ' ', $fields[$no]['title']);
			$fields[$no]['title'] = str_replace('_id', ' ', $fields[$no]['title']);
			$fields[$no]['title'] = str_replace('_', ' ', $fields[$no]['title']);
			$fields[$no]['title'] = rtrim($fields[$no]['title']);
		}

		if (empty($fields[$no]['translated'])) {
			// translate fieldnames, if set
			foreach ($to_translates as $to_translate) {
				if (empty($fields[$no][$to_translate])) continue;
				$fields[$no][$to_translate] = zz_text($fields[$no][$to_translate]);
			}
			$fields[$no]['translated'] = true;
		}
		if ($fields[$no]['type'] === 'option') {
			// do not show option-fields in tab
			$fields[$no]['hide_in_list'] = true;
			// makes no sense to export a form field
			$fields[$no]['export'] = false;
			// format option-fields with CSS
			if (!empty($fields[$no]['class'])
				AND $fields[$no]['class'] !== 'option') {
				$fields[$no]['class'] .= ' option';
			} else {
				$fields[$no]['class'] = 'option';
			}
		} elseif (in_array(zz_get_fieldtype($fields[$no]), array('time', 'datetime'))) {
			if (empty($fields[$no]['time_format'])) {
				$fields[$no]['time_format'] = 'H:i';
			}
		}
		// initialize
		if (!isset($fields[$no]['explanation'])) {
			$fields[$no]['explanation'] = false;
		}
		if (!$multiple_times) {
			if (!empty($fields[$no]['sql'])) // replace whitespace with space
				$fields[$no]['sql'] = preg_replace("/\s+/", " ", $fields[$no]['sql']);
		}
		if ($fields[$no]['type'] === 'subtable') {
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
				$mode, $action
			);
		}

		if (in_array($mode, array('add', 'edit')) OR in_array($action, array('insert', 'update'))) {
			if (!isset($fields[$no]['maxlength']) && isset($fields[$no]['field_name'])) {
				// no need to check maxlength in list view only 
				$fields[$no]['maxlength'] = zz_db_field_maxlength(
					$fields[$no]['field_name'], $fields[$no]['type'], $db_table
				);
			} else {
				$fields[$no]['maxlength'] = 32;
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
	$never_required = array(
		'calculated', 'display', 'option', 'image', 'foreign', 'subtable'
	);
	if (in_array($field['type'], $never_required)) return false;

	return true;
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
	$uninteresting_zz_conf_keys = array(
		'zzform_calls', 'int', 'id', 'footer_text', 
		'breadcrumbs', 'dont_show_title_as_breadcrumb', 'error_handling',
		'error_log', 'format', 'group_html_table', 'list_display',
		'limit_display', 'logging', 'logging_id', 'logging_table',
		'mail_subject_prefix', 'title_separator',
		'referer', 'access', 'heading_prefix', 'redirect', 'search_form_always',
		'redirect_on_change', 'filter', 'filter_position', 'text', 'file_types',
		'translate_log_encodings'
	);
	foreach ($uninteresting_zz_conf_keys as $key) unset($zz_conf[$key]);
	$uninteresting_zz_keys = array(
		'title', 'explanation', 'explanation_top', 'subtitle', 'list'
	);
	foreach ($uninteresting_zz_keys as $key) unset($zz[$key]);
	foreach ($zz['fields'] as $no => $field) {
		// defaults might change, e. g. dates
		if (isset($field['default'])) unset($zz['fields'][$no]);
	}
	$my['zz'] = $zz;
	$my['zz_conf'] = $zz_conf;
	$hash = sha1(serialize($my));
	return $hash;
}

/**
 * gets unique and id fields for further processing
 *
 * @param array $fields
 * @global array $zz_error
 * @return array $zz_var
 *		'id'[value], 'id'[field_name], 'unique_fields'
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_get_unique_fields($fields) {
	global $zz_error;

	$zz_var = array();
	$zz_var['id']['value'] = false;
	$zz_var['id']['field_name'] = false;
	$zz_var['unique_fields'] = array(); // for WHERE

	foreach ($fields AS $field) {
		// set ID fieldname
		if (!empty($field['type']) AND $field['type'] === 'id') {
			if ($zz_var['id']['field_name']) {
				$zz_error['msg'] = 'Only one field may be defined as "id"!';
				return false;
			}
			$zz_var['id']['field_name'] = $field['field_name'];
		}
		if (!empty($field['unique']) AND !is_array($field['unique'])) {
			// 'unique' might be array for subtables
			$zz_var['unique_fields'][$field['field_name']] = true;
		}
	}
	return $zz_var;
}

/**
 * sets some $zz-definitions for records depending on existing definition for
 * translations, subtabes, uploads, write_once-fields
 *
 * @param array $fields = $zz['fields']
 * @param array $zz_var
 * @return array 
 *		array $zz
 *		'subtables', 'save_old_record', , some minor 'fields' 
 *		changes
 *		array $zz_var
 *			'upload_form'
 */
function zz_set_fielddefs_for_record($fields, $zz_var) {
	$rec = 1;
	$zz_var['subtables'] = array();			// key: $rec, value: $no
	$zz_var['save_old_record'] = array();	// key: int, value: $no
	$zz_var['upload_form'] = false;			// false: no upload, true: upload possible

	foreach (array_keys($fields) as $no) {
		// translations
		if (!empty($fields[$no]['translate_field_index'])) {
			$t_index = $fields[$no]['translate_field_index'];
			if (isset($fields[$t_index]['translation'])
				AND !$fields[$t_index]['translation']) {
				unset ($fields[$no]);
				continue;
			}
		}
		if (!isset($fields[$no]['type'])) continue;
		switch ($fields[$no]['type']) {
		case 'subtable':
			// save number of subtable, get table_name and check whether sql
			// is unique, look for upload form as well
			$zz_var['subtables'][$rec] = $no;
			if (!isset($fields[$no]['table_name']))
				$fields[$no]['table_name'] = $fields[$no]['table'];
			$fields[$no]['subtable'] = $rec;
			$rec++;
			if (!empty($fields[$no]['sql_not_unique'])) {
				// must not change record where main record is not directly 
				// superior to detail record 
				// - foreign ID would be changed to main record's id
				$fields[$no]['access'] = 'show';
			}
			foreach ($fields[$no]['fields'] as $subfield) {
				if (empty($subfield['type'])) continue;
				if ($subfield['type'] !== 'upload_image') continue;
				$zz_var['upload_form'] = true;
			}
			break;
		case 'upload_image':
			$zz_var['upload_form'] = true;
			break;
		case 'write_once':
		case 'display':
			$zz_var['save_old_record'][] = $no;
			break;
		}
	}
	return array($fields, $zz_var);
}

/** 
 * Sets $ops['mode'], $zz_var['action'] and several $zz_conf-variables
 * according to what the user request and what the user is allowed to request
 * 
 * @param array $zz
 * @param array $zz_conf
 *		int['record'], 'access', int['list_access'] etc. pp.
 *		'modules'[debug]
 *		'where_with_unique_id' bool if it's just one record to be shown (true)
 * @param array $zz_var --> will be changed as well
 * @global array $zz_conf
 * @global array $zz_error
 * @global array $_POST
 * @return array 
 *		$zz array
 *		$ops array
 *		$zz_var array
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_record_access($zz, $ops, $zz_var) {
	global $zz_conf;
	global $zz_error;

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	// initialize variables
	$zz_var['action'] = false;		// action: what database operations are to be done
	$zz_conf['int']['record'] = true; // show record somehow (edit, view, ...)
	
	if (!empty($_POST['zz_action'])) {
		if (!in_array(
			$_POST['zz_action'], $zz_conf['int']['allowed_params']['action'])
		) {
			unset($_POST['zz_action']);
		}
		$zz_var['query_records'] = true;
	} else {
		$zz_var['query_records'] = false;
	}
	
	// set mode and action according to $_GET and $_POST variables
	// do not care yet if actions are allowed
	$keys = array();
	switch (true) {
	case $ops['mode'] === 'export':
		// Export overwrites all
		$zz_conf['int']['access'] = 'export'; 	
		$zz_conf['int']['record'] = false;
		break;

	case isset($_POST['zz_subtables']):
		// ok, no submit button was hit but only add/remove form fields for
		// detail records in subtable, so set mode accordingly (no action!)
		if (!empty($_POST['zz_action']) AND $_POST['zz_action'] === 'insert') {
			$ops['mode'] = 'add';
		} elseif (!empty($_POST['zz_action']) AND $_POST['zz_action'] === 'update'
			AND !empty($_POST[$zz_var['id']['field_name']])) {
			$ops['mode'] = 'edit';
			$id_value = $_POST[$zz_var['id']['field_name']];
		} else {
			// this should not occur if form is used legally
			$ops['mode'] = false;
		}
		break;

	case !empty($_GET['mode']):
		// standard case, get mode from URL
		if (in_array($_GET['mode'], $zz_conf['int']['allowed_params']['mode'])) {
			$ops['mode'] = $_GET['mode']; // set mode from URL
			if (in_array($ops['mode'], array('edit', 'delete', 'show'))
				AND !empty($_GET['id'])) {
				$id_value = $_GET['id'];
			} elseif ($ops['mode'] === 'add' AND $zz_conf['copy']
				AND !empty($_GET['source_id'])) {
				$zz_var['id']['source_value'] = $_GET['source_id'];
			}
		} else {
			// illegal parameter, don't set a mode at all
			$zz_conf['int']['http_status'] = 404;
			$keys = array('id', 'mode');
		}
		break;

	case isset($_GET['delete']):
	case isset($_GET['insert']):
	case isset($_GET['update']):
	case isset($_GET['noupdate']):
		// last record operation was successful
		$ops['mode'] = 'show';
		if (isset($_GET['delete'])) $ops['mode'] = '';
		$keys = array('delete', 'insert', 'update', 'noupdate');
		$found = 0;
		foreach ($keys as $key) {
			if (!isset($_GET[$key])) continue;
			if ($key !== 'delete') $id_value = $_GET[$key];
			$found++;
		}
		if ($found > 1) {
			$zz_conf['int']['http_status'] = 404;
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
				$zz_var['id']['values'] = $_POST['zz_record_id'];
			}
		} else {
			// triggers valid database action
			$zz_var['action'] = $_POST['zz_action']; 
			if (!empty($_POST[$zz_var['id']['field_name']]))
				$id_value = $_POST[$zz_var['id']['field_name']];
			$ops['mode'] = false;
		}
		break;
	
	case $zz_conf['int']['where_with_unique_id']:
		// just review the record
		$ops['mode'] = 'review'; 
		break;

	case !empty($_GET['thumbs']):
		if (empty($_POST)) {
			$zz_conf['int']['http_status'] = 404;
			break;
		}
		$keys = array('thumbs', 'field');
		$ops['mode'] = 'thumbnails';
		$id_value = $_GET['thumbs'];
		if (empty($_GET['field'])) {
			$zz_conf['int']['http_status'] = 404;
			break;
		}
		$zz_var['thumb_field'] = explode('-', $_GET['field']);
		if (count($zz_var['thumb_field']) !== 2) {
			$zz_conf['int']['http_status'] = 404;
		}
		break;

	case !empty($_GET['field']):
		$keys = array('thumbs', 'field');
		$zz_conf['int']['http_status'] = 404;
		break;

	default:
		// no record is selected, basic view when starting to edit data
		// list mode only
		$ops['mode'] = 'list_only';
		break;
	}
	
	if (!empty($zz_conf['int']['http_status']) AND $zz_conf['int']['http_status'] === 404) {
		$id_value = false;
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string($zz_conf['int']['url']['qs_zzform'], $keys);
		$ops['mode'] = false;
	}

	// write main id value, might have been written by a more trustful instance
	// beforehands ($_GET['where'] etc.)
	if (empty($zz_var['id']['value']) AND !empty($id_value)) {
		if (!is_numeric($id_value)) {
			$zz_var['id']['invalid_value'] = $id_value;
		} else {
			$zz_var['id']['value'] = $id_value;
		}
	} elseif (!isset($zz_var['id']['value'])) {
		$zz_var['id']['value'] = '';
	}

	// now that we have the ID value, we can calculate the secret key
	if (!empty($zz_var['id']['values'])) {
		$idval = implode(',', $zz_var['id']['values']);
	} else {
		$idval = $zz_var['id']['value'];
	}
	$zz_conf['int']['secret_key'] = sha1($zz_conf['int']['hash'].$idval);

	// if conditions in $zz_conf['if'] -- check them
	// get conditions if there are any, for access
	$zz_conf['int']['list_access'] = array(); // for old variables

	if (!empty($zz_conf['modules']['conditions'])
		AND (!empty($zz_conf['if']) OR !empty($zz_conf['unless']))
		AND $zz_var['id']['value']) {
		$zz_conditions = zz_conditions_check($zz, $ops['mode'], $zz_var);
		// @todo do we need to check record conditions here?
		$zz_conditions = zz_conditions_record_check($zz, $ops['mode'], $zz_var, $zz_conditions);
		// save old variables for list view
		$saved_variables = array(
			'access', 'add', 'edit', 'delete', 'view', 'details'
		);
		foreach ($saved_variables as $var) {
			if (!isset($zz_conf[$var])) continue;
			$zz_conf['int']['list_access'][$var] = $zz_conf[$var];
		}
		// overwrite new variables
		zz_conditions_merge_conf($zz_conf, $zz_conditions['bool'], $zz_var['id']['value']);
	}


	// set (and overwrite if necessary) access variables, i. e.
	// $zz_conf['add'], $zz_conf['edit'], $zz_conf['delete']

	if ($zz_conf['int']['access'] === 'add_only' AND zz_valid_request('insert')) {
		$zz_conf['int']['access'] = 'show_after_add';
	}
	if ($zz_conf['int']['access'] === 'edit_only'
		AND zz_valid_request(array('update', 'noupdate'))) {
		$zz_conf['int']['access'] = 'show_after_edit';
	}
	if ($zz_conf['int']['access'] === 'add_then_edit') {
		if ($zz_var['id']['value'] AND zz_valid_request()) {
			$zz_conf['int']['access'] = 'show+edit';
		} elseif ($zz_var['id']['value']) {
			$zz_conf['int']['access'] = 'edit_only';
		} else {
			$zz_conf['int']['access'] = 'add_only';
		}
	}
	
	if ($ops['mode'] === 'thumbnails') {
		$not_allowed = array(
			'show', 'show_and_delete', 'edit_details_only', 'edit_details_and_add',
			'none', 'search_but_no_list', 'add_only', 'edit_only', 'add_then_edit',
			'show_after_add', 'show_after_edit', 'show+edit'
		);
		// @todo check for valid ID in case of add_only, edit_only, add_then_edit
		// and allow these, too.
		if (!in_array($zz_conf['int']['access'], $not_allowed)) {
			$zz_conf['int']['access'] = 'thumbnails';
		} else {
			$zz_conf['int']['access'] = 'none';
			$zz_conf['int']['http_status'] = 403;
			$ops['mode'] = false;
			$zz_var['action'] = false;
		}
	}

	// @todo think about multiple_edit
	switch ($zz_conf['int']['access']) { // access overwrites individual settings
	// first the record specific or overall settings
	case 'export':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['int']['show_list'] = true;		// list
		$zz_conf['int']['record'] = false;	// don't show record
		break;
	case 'show_after_add';
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['search'] = false;			// no search form
		$zz_conf['int']['show_list'] = false;		// no list
		$zz_conf['no_ok'] = true;			// no OK button
		$zz_conf['cancel_link'] = false; 	// no cancel link
		if (empty($_POST)) $ops['mode'] = 'show';
		break;
	case 'show_after_edit';
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['search'] = false;			// no search form
		$zz_conf['int']['show_list'] = false;		// no list
		$zz_conf['no_ok'] = true;			// no OK button
		$zz_conf['cancel_link'] = false; 	// no cancel link
		if (empty($_POST)) $ops['mode'] = 'show';
		break;
	case 'show+edit';
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['search'] = false;			// no search form
		$zz_conf['int']['show_list'] = false;		// no list
		$zz_conf['no_ok'] = true;			// no OK button
		if (empty($_POST)) $ops['mode'] = 'show';
		break;
	case 'add_only';
		if (!is_array($zz_conf['add'])) $zz_conf['add'] = true;	// add record (form)
		$zz_conf['add_link'] = false;		// add record (links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['search'] = false;			// no search form
		$zz_conf['int']['show_list'] = false;		// no list
		$zz_conf['cancel_link'] = false; 	// no cancel link
		$zz_conf['no_ok'] = true;			// no OK button
		$zz_conf['int']['hash_id'] = true;	// user cannot view all IDs
		if (empty($_POST)) $ops['mode'] = 'add';
		break;
	case 'edit_only';
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['search'] = false;			// no search form
		$zz_conf['int']['show_list'] = false;		// no list
		$zz_conf['no_ok'] = true;			// no OK button
		$zz_conf['cancel_link'] = false; 	// no cancel link
		if (empty($zz_conf['int']['where_with_unique_id'])) {
			$zz_conf['int']['hash_id'] = true;	// user cannot view all IDs
		}
		if (empty($_POST)) $ops['mode'] = 'edit';
		break;
	case 'thumbnails':
		$zz_conf['add'] = false;
		$zz_conf['edit'] = false;
		$zz_conf['delete'] = false;
		$zz_conf['view'] = false;
		$zz_conf['int']['show_list'] = false;
		$zz_conf['generate_output'] = false;
		$zz_conf['int']['record'] = true;
		$zz_var['action'] = 'thumbnails';
		$zz_var['query_records'] = true;
		break;
	default:
		// now the settings which apply to both record and list
		$zz_conf = zz_listandrecord_access($zz_conf);
		break;
	}

	if ($zz_conf['int']['where_with_unique_id']) { // just for record, not for list
		// in case of where and not unique, ie. only one record in table, 
		// don't do this.
		$zz_conf['int']['show_list'] = false;		// don't show table
		$zz_conf['add'] = false;			// don't show add record (form+links)
	}

	// $zz_conf is set regarding add, edit, delete
	// don't copy record (form+links)
	if (!$zz_conf['add']) $zz_conf['copy'] = false;

	if (!isset($zz_conf['add_link'])) {
		// Link Add new ...
		$zz_conf['add_link'] = $zz_conf['add'] ? true : false;
	}

	// check unallowed modes and actions
	$modes = array('add' => 'insert', 'edit' => 'update', 'delete' => 'delete');
	foreach ($modes as $mode => $action) {
		if (!$zz_conf[$mode] AND $ops['mode'] === $mode) {
			$ops['mode'] = false;
			$zz_error[] = array(
				'msg_dev' => sprintf(
					zz_text('Configuration does not allow this mode: %s'),
					zz_text($mode)
				),
				'status' => 403,
				'level' => E_USER_NOTICE
			);
		}
		if (!$zz_conf[$mode] AND $zz_var['action'] === $action) {
			$zz_var['action'] = false;
			$zz_error[] = array(
				'msg_dev' => sprintf(
					zz_text('Configuration does not allow this action: %s'),
					zz_text($action)
				),
				'status' => 403,
				'level' => E_USER_NOTICE
			);
		}
	}

	if ($zz_conf['int']['access'] === 'edit_details_only') $zz['access'] = 'show';
	if ($zz_conf['int']['access'] === 'edit_details_and_add' 
		AND $ops['mode'] !== 'add' AND $zz_var['action'] !== 'insert')
		$zz['access'] = 'show';

	// now, mode is set, do something depending on mode
	
	if (in_array($ops['mode'], array('edit', 'delete', 'add')) 
		AND !$zz_conf['show_list_while_edit']) $zz_conf['int']['show_list'] = false;
	if (!$zz_conf['generate_output']) $zz_conf['int']['show_list'] = false;

	if ($ops['mode'] === 'list_only') {
		$zz_conf['int']['record'] = false;	// don't show record
	}
	return zz_return(array($zz, $ops, $zz_var));
}

/** 
 * Sets configuration variables depending on $var['access']
 * Access possible for list and for record view
 * 
 * @param array $zz_conf
 * @return array $zz_conf changed zz_conf-variables
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_listandrecord_access($zz_conf) {
	switch ($zz_conf['int']['access']) {
	case 'show':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = true;			// show record (links)
		break;
	case 'show_and_add':
		if (!is_array($zz_conf['add'])) $zz_conf['add'] = true; // add record (form+links)
		$zz_conf['edit'] = false;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = true;			// show record (links)
		break;
	case 'show_edit_add';
		if (!is_array($zz_conf['add'])) $zz_conf['add'] = true; // add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = true;			// show record (links)
		break;
	case 'show_and_delete';
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = true;			// delete record (form+links)
		$zz_conf['view'] = true;			// show record (links)
		break;
	case 'edit_details_only':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		break;
	case 'edit_details_and_add':
		if (!is_array($zz_conf['add'])) $zz_conf['add'] = true; // add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		break;
	case 'none':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['int']['record'] = false;	// don't show record
		break;
	case 'search_but_no_list':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['int']['record'] = false;	// don't show record
		$zz_conf['int']['show_list'] = true;		// show list, further steps in zz_list()
		break;
	case 'all':
		if (!is_array($zz_conf['add'])) $zz_conf['add'] = true;	// add record (form+links)
		$zz_conf['edit'] = true;			// edit record (form+links)
		$zz_conf['delete'] = true;			// delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		break;
	default:
		// do not change anything, just initalize if required
		if (!isset($zz_conf['add'])) $zz_conf['add'] = true;
		if (!isset($zz_conf['edit'])) $zz_conf['edit'] = true;
		if (!isset($zz_conf['delete'])) $zz_conf['delete'] = false;
		if (!isset($zz_conf['view'])) $zz_conf['view'] = false;
		break;
	}

	return $zz_conf;
}

/** 
 * Creates link or HTML img from path
 * 
 * @param array $path
 *		'root', 'webroot', 'field1...fieldn', 'string1...stringn', 'mode1...n',
 *		'ignore_record' will cause record to be ignored
 * @param array $record current record
 * @param string $type (optional) link, path or image, image will be returned in
 *		<img src="" alt="">
 * @return string URL or HTML-code for image
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_makelink($path, $record, $type = 'link') {
	if (empty($path['ignore_record']) AND !$record) return false;
	if (!$path) return false;

	$url = '';
	$modes = array();
	$path_full = '';		// absolute path in filesystem
	$path_web = '';			// relative path on website
	$check_against_root = false;

	if ($type === 'image') {
		$alt = zz_text('no_image');
		// lock if there is something definitely called extension
		$alt_locked = false; 
	}
	if (!is_array($path)) $path = array('string' => $path);
	foreach ($path as $part => $value) {
		if (!$value) continue;
		// remove numbers at the end of the part type
		while (is_numeric(substr($part, -1))) $part = substr($part, 0, -1);
		switch ($part) {
		case 'root':
			$check_against_root = true;
			// root has to be first element, everything before will be ignored
			$path_full = $value;
			if (substr($path_full, -1) !== '/')
				$path_full .= '/';
			break;
		case 'webroot':
			// web might come later, ignore parts before for web and add them
			// to full path
			$path_web = $value;
			$path_full .= $url;
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
				$modes = array();
			}
			if ($part !== 'webfield') {
				$url .= $content;
			}
			$path_web .= $content;
			if ($type === 'image' AND !$alt_locked) {
				$alt = zz_text('File: ').$record[$value];
				if ($part === 'extension') $alt_locked = true;
			}
			break;
		case 'string':
			$url .= $value;
			$path_web .= $value;
			break;
		case 'webstring':
			$path_web .= $value;
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
		$ext = zz_text('- unknown -');
	}
	
	if ($check_against_root) {
		// check whether file exists
		if (!file_exists($path_full.$url)) {
			// file does not exist = false
			return false;
		}
		if ($type === 'image') {
			// filesize is 0 = looks like error
			if (!$size = filesize($path_full.$url)) return false;
			// getimagesize tests whether it's a web image
			if (!getimagesize($path_full.$url)) {
				// if not, return EXT (4.4 MB)
				return $ext.' ('.zz_byte_format($size).')';
			}
		}
	}

	switch ($type) {
	case 'path':
		return $path_full.$url;
	case 'image':
		if (!$path_web) return false;
		$img = '<img src="'.$path_web.'" alt="'.$alt.'" class="thumb">';
		return $img;
	default:
	case 'link':
		return $path_web;
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
			global $zz_error;
			$zz_error[] = array(
				'msg_dev' => sprintf(
					zz_text('Configuration Error: mode with not-existing function "%s"'),
					$mode
				),
				'level' => $error
			);
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_makepath($path, $data, $record = 'new', $do = false, $tab = 0, $rec = 0) {
	// set variables
	$p = false;
	$modes = false;
	$root = false;		// root
	$rootp = false;		// path just for root
	$webroot = false;	// web root

	// record data
	switch ($record) {
	case 'old':
		$my_tab = $data[$tab];
		$line = !empty($my_tab[$rec]['existing']) ? $my_tab[$rec]['existing'] : array();
		break;
	case 'new':
		$my_tab = $data[$tab];
		$line = !empty($my_tab[$rec]['POST']) ? $my_tab[$rec]['POST'] : array();
		break;
	case 'line':
		$line = $data;
		break;
	}

	// put path together
	$alt_locked = false;
	foreach ($path as $pkey => $pvalue) {
		if (!$pvalue) continue;
		if ($pkey === 'root') {
			$root = $pvalue;
		} elseif ($pkey === 'webroot') {
			$webroot = $pvalue;
			$rootp = $p;
			$p = '';
		} elseif (substr($pkey, 0, 4) === 'mode') {
			$modes[] = $pvalue;
		} elseif (substr($pkey, 0, 6) === 'string') {
			$p .= $pvalue;
		} elseif (substr($pkey, 0, 5) === 'field' OR $pkey === 'extension') {
			$content = !empty($line[$pvalue]) ? $line[$pvalue] : '';
			if (!$content AND $record === 'new') {
				$content = zz_get_record(
					$pvalue, $my_tab['sql'], $my_tab[$rec]['id']['value'], 
					$my_tab['table'].'.'.$my_tab[$rec]['id']['field_name']
				);
			}
			if ($modes) {
				$content = zz_make_mode($modes, $content);
				if (!$content) return false;
			}
			$p .= $content;
			if (!$alt_locked) {
				$alt = zz_text('File: ').$content;
				if ($pkey === 'extension') $alt_locked = true;
			}
			$modes = false;
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
 * @global array $zz_error
 * @return string
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_get_record($field_name, $sql, $idvalue = false, $idfield = false) { 
	global $zz_error;
	// if idvalue is not set: note: all values should be the same!
	// First value is taken
	if ($idvalue) 
		$sql = zz_edit_sql($sql, 'WHERE', sprintf('%s = %d', $idfield, $idvalue));
	$line = zz_db_fetch($sql, '', '', __FUNCTION__);
	if (!empty($line[$field_name])) return $line[$field_name];
	else return false;
}

/** 
 * exit function for zzform functions, should always be called to adjust some 
 * settings
 *
 * @param mixed $return return parameter
 * @return mixed return parameter
 */
function zz_return($return = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('end');
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
 * strips magic quotes from multidimensional arrays
 * 
 * @param array $mixed Array with magic_quotes
 * @return array Array without magic_quotes
 */
function zz_magic_quotes_strip($mixed) {
   if(is_array($mixed))
       return array_map('zz_magic_quotes_strip', $mixed);
   return stripslashes($mixed);
}

/** 
 * Merges Array recursively: replaces old with new keys, adds new keys
 * 
 * @param array $old			Old array
 * @param array $new			New array
 * @return array $merged		Merged array
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_array_merge($old, $new) {
	foreach ($new as $index => $value) {
		if (is_array($value)) {
			if (!empty($old[$index])) {
				$old[$index] = zz_array_merge($old[$index], $new[$index]);
			} else
				$old[$index] = $new[$index];
		} else {
			if (is_numeric($index) AND (!in_array($value, $old))) {
				// numeric keys will be appended, if new
				$old[] = $value;
			} else {
				// named keys will be replaced
				$old[$index] = $value;
			}
		}
	}
	return $old;
}

/**
 * get type of field
 *
 * @param array $field
 * @return string $field_type
 */
function zz_get_fieldtype($field) {
	if (in_array($field['type'], array('hidden', 'predefined', 'write_once', 'display'))) {
		if (isset($field['type_detail'])) {
			return $field['type_detail'];
		}
	}
	return $field['type'];
}

/**
 * change some values, just for backwards compatibility
 *
 * @param array $zz_conf
 * @param array $zz
 * @return array
 */
function zz_backwards($zz_conf, $zz) {
	$headings = array('var', 'sql', 'enum', 'link', 'link_no_append');
	foreach ($headings as $suffix) {
		if (isset($zz_conf['heading_'.$suffix])) {
			if (function_exists('wrap_error')) {
				wrap_error(sprintf(
					'Use of deprecated variable $zz_conf["heading_%s"], use $zz["subtitle"][$key][%s] instead. (URL: %s)',
					$suffix, $suffix, $_SERVER['REQUEST_URI']));
			}
			foreach ($zz_conf['heading_'.$suffix] as $field => $value) {
				$zz['subtitle'][$field][$suffix] = $value;
				unset ($zz_conf['heading_'.$suffix][$field]);
			}
		}
	}
	$moved_to_zz = array(
		'heading' => 'title',
		'heading_text' => 'explanation',
		'heading_text_hidden_while_editing', array('if', 'record_mode', 'explanation'),
		'heading_sub' => 'subtitle',
		'action' => 'hooks',
		'tfoot' => array('list', 'tfoot'),
		'group' => array('list', 'group'),
		'folder' => 'folder',
		'filter' => 'filter'
	);
	foreach ($moved_to_zz as $old => $new) {
		if (isset($zz_conf[$old])) {
			if (is_array($new) AND count($new === 2)) {
				$zz[$new[0]][$new[1]] = $zz_conf[$old];
				$new = implode('"]["', $new);
			} else {
				$zz[$new] = $zz_conf[$old];
			}
			unset($zz_conf[$old]);
			wrap_error(sprintf(
				'Use of deprecated variable $zz_conf["%s"], use $zz["%s"] instead. (URL: %s)',
				$old, $new, $_SERVER['REQUEST_URI']
			));
		}
	}
	if (!empty($zz_conf['show_hierarchy'])) {
		$zz['list']['hierarchy'] = $zz_conf['hierarchy'];
		if (is_numeric($zz_conf['show_hierarchy'])) {
			$zz['list']['hierarchy']['id'] = $zz_conf['show_hierarchy'];
		}
		unset($zz_conf['show_hierarchy']);
		unset($zz_conf['hierarchy']);
		wrap_error(sprintf(
			'Use of deprecated variable $zz_conf["show_hierarchy"], use $zz["list"]["hierarchy"] instead. (URL: %s)',
			$_SERVER['REQUEST_URI']
		));
	}
	// conditions applied = if
	$cond_renamed = array('conditions' => 'if', 'not_conditions' => 'unless');
	foreach ($cond_renamed as $old => $new) {
		if (isset($zz_conf[$old])) {
			$zz_conf[$new] = $zz_conf[$old];
			unset($zz_conf[$old]);
			wrap_error(sprintf(
				'Use of deprecated variable $zz_conf["%s"], use $zz_conf["%s"] instead. (URL: %s)',
				$old, $new, $_SERVER['REQUEST_URI']
			));
		}
		foreach ($zz['fields'] as $no => $field) {
			if (!empty($field['type']) AND $field['type'] === 'subtable' AND !empty($field['fields'])) {
				foreach ($field['fields'] as $sub_no => $sub_field) {
					if (!isset($sub_field[$old])) continue;
					$zz['fields'][$no]['fields'][$sub_no][$new] = $sub_field[$old];
					unset($zz['fields'][$no]['fields'][$sub_no][$old]);
					wrap_error(sprintf(
						'Use of deprecated variable $zz["fields"][%d]["fields"][%d]["%s"], use $zz["fields"][%d]["fields"][%d]["%s"] instead. (URL: %s)',
						$no, $sub_no, $old, $no, $sub_no, $new, $_SERVER['REQUEST_URI']
					));
				}
			}
			if (!isset($field[$old])) continue;
			$zz['fields'][$no][$new] = $field[$old];
			unset($zz['fields'][$no][$old]);
			wrap_error(sprintf(
				'Use of deprecated variable $zz["fields"][%d]["%s"], use $zz["fields"][%d]["%s"] instead. (URL: %s)',
				$no, $old, $no, $new, $_SERVER['REQUEST_URI']
			));
		}
	}
	// renamed $zz variables
	$zz_renamed = array('extra_action' => 'hooks');
	$zz = zz_backwards_rename($zz, $zz_renamed, 'zz');
	// renamed $zz_conf variables
	$zz_conf_renamed = array('action_dir' => 'hooks_dir');
	$zz_conf = zz_backwards_rename($zz_conf, $zz_conf_renamed, 'zz_conf');
	return array($zz_conf, $zz);
}

/**
 * rename old variables to keep backwards compatibility
 * but send an error message
 *
 * @param array $var
 * @param array $var_renamed (list of old => new)
 * @param array $var_name for error logging
 * @return array updated $var
 */
function zz_backwards_rename($var, $var_renamed, $var_name) {
	foreach ($var_renamed as $old => $new) {
		if (!isset($var[$old])) continue;
		$var[$new] = $var[$old];
		unset($var[$old]);
		wrap_error(sprintf(
			'Use of deprecated variable $%s["%s"], use $%s["%s"] instead. (URL: %s)',
			$var_name, $old, $var_name, $new, $_SERVER['REQUEST_URI']
		));
	}
	return $var;
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
 * @global array $zz_error
 *		array for each error:
 * 		string 'msg' message that always will be sent back to browser
 * 		string 'msg_dev' message that will be sent to browser, log and mail, 
 * 			depending on settings
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
 * @global array $zz_conf
 *		$zz_conf['error_log']['notice'], $zz_conf['error_log']['warning'], 
 * 		$zz_conf['error_log']['error'] = path to error_log, default from php.ini
 * 		$zz_conf['error_handling'] = value for admin error logging
 * 			- false: no output, just write into log if set
 * 			- 'mail': send admin errors via mail
 * 			- 'output': send admin erros via html
 * 		$zz_conf['error_mail_to'], $zz_conf['error_mail_from'] - mail addresses
 * @return bool false if no error was detected, true if error was detected
 */
function zz_error() {
	global $zz_conf;
	global $zz_error;	// we need this global, because it's global everywhere, 
						// so we can clear the variable here
	static $post_errors_logged;
	
	if (empty($zz_conf['error_handling'])) {
		$zz_conf['error_handling'] = 'output';
	}
	$user = array();
	$admin = array();
	$log = array();
	$mail = array();
	$return = !empty($zz_error['error']) ? 'exit' : 'html';
	$output = !empty($zz_error['output']) ? $zz_error['output'] : array();
	unset($zz_error['error']); // we don't need this here
	unset($zz_error['output']); // this neither
	
	if (!$zz_error) {
		$zz_error['error'] = ($return === 'exit') ? true : false;
		$zz_error['output'] = $output;
		return false;
	}
	
	$log_encoding = $zz_conf['character_set'];
	// PHP does not support all encodings
	if (in_array($log_encoding, array_keys($zz_conf['translate_log_encodings'])))
		$log_encoding = $zz_conf['translate_log_encodings'][$log_encoding];
	
	$username = ' ['.zz_text('User').': '
		.($zz_conf['user'] ? $zz_conf['user'] : zz_text('No user')).']';

	// browse through all errors
	foreach ($zz_error as $key => $error) {
		if (!is_numeric($key)) continue;
		
		// initialize error_level
		if (empty($error['level'])) $error['level'] = '';
		if (empty($error['status'])) $error['status'] = 200;
		
		// log POST data?
		if (!isset($error['log_post_data'])) {
			$error['log_post_data'] = true;
		}
		if (!$zz_conf['error_log_post']) $error['log_post_data'] = false;
		elseif (empty($_POST)) $error['log_post_data'] = false;
		elseif ($post_errors_logged) $error['log_post_data'] = false;

		// page http status
		if ($error['status'] !== 200) {
			$zz_conf['int']['http_status'] = $error['status'];
		}

		// initialize and translate error messages
		$error['msg'] = !empty($error['msg']) ? zz_text(trim($error['msg'])) : '';
		$error['msg_dev'] = !empty($error['msg_dev']) ? zz_text(trim($error['msg_dev'])) : '';

		$user[$key] = false;
		$admin[$key] = false;

		if (!empty($error['db_errno'])) {
			$error['msg'] = zz_db_error($error['db_errno'])
				.($error['msg'] ? '<br>'.$error['msg'] : '');
		}

		switch ($error['level']) {
		case E_USER_ERROR:
			if (!$error['msg']) $user[$key] .= zz_text('An error occured.'
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
		if (!empty($error['msg_dev'])) 
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
		if (!empty($zz_conf['error_log'][$level]) AND $zz_conf['log_errors']) {
			$line = '['.date('d-M-Y H:i:s').'] zzform '.ucfirst($level)
				.': ['.$_SERVER['REQUEST_URI'].'] '.preg_replace("/\s+/", " ", $log[$key]);
			$line = substr($line, 0, $zz_conf['log_errors_max_len'] - (strlen($username) + 1));
			$line .= $username."\n";
			error_log($line, 3, $zz_conf['error_log'][$level]);
			if ($error['log_post_data']) {
				$line = '['.date('d-M-Y H:i:s').'] zzform Notice: POST';
				if (function_exists('json_encode')) {
					$line .= '[json] '.json_encode($_POST);
				} else {
					$line .= ' '.serialize($_POST);
				}
				$line = substr($line, 0, $zz_conf['log_errors_max_len'] 
					- (strlen($username)+4)).' '.$username."\n";
				error_log($line, 3, $zz_conf['error_log'][$level]);
				$post_errors_logged = true;
			}
		}
		// Mail output
		if (isset($zz_conf['error_mail_level']) AND in_array(
			$level, $zz_conf['error_mail_level'])
		) {
			$mail[$key] = $log[$key];
		}

		// Heading
		if (!$user[$key]) {
			unset($user[$key]); // there is nothing, so nothing will be shown
		} elseif ($level === 'error' OR $level === 'warning') {
			$user[$key] = '<strong>'.zz_text('Warning!').'</strong> '.$user[$key];
		}
		if ($admin[$key] AND ($level === 'error' OR $level === 'warning')) {
			$admin[$key] = '<strong>'.zz_text('Warning!').'</strong> '.$admin[$key];
		}
	}

	// mail errors if said to do so
	switch ($zz_conf['error_handling']) {
	case 'mail':	
		if (!$zz_conf['error_mail_to']) break;
		if (!count($mail)) break;
		$mailtext = sprintf(
			zz_text('The following error(s) occured in project %s:'), $zz_conf['project']
		);
		$mailtext .= "\n\n".implode("\n\n", $mail);
		$mailtext = html_entity_decode($mailtext, ENT_QUOTES, $log_encoding);		
		$mailtext .= "\n\n-- \nURL: ".$zz_conf['int']['url']['base']
			.$_SERVER['REQUEST_URI']
			."\nIP: ".$_SERVER['REMOTE_ADDR']
			.(!empty($_SERVER['HTTP_USER_AGENT']) ? "\nBrowser: ".$_SERVER['HTTP_USER_AGENT'] : '');		
		if ($zz_conf['user'])
			$mailtext .= "\nUser: ".$zz_conf['user'];
		$subject = (!empty($zz_conf['mail_subject_prefix']) 
			? $zz_conf['mail_subject_prefix'] : '['.$zz_conf['project'].']').' '
			.zz_text('Error during database operation');
		$from = '"'.$zz_conf['project'].'" <'.$zz_conf['error_mail_from'].'>';
		mail($zz_conf['error_mail_to'], $subject, 
			$mailtext, 'MIME-Version: 1.0
Content-Type: text/plain; charset='.$zz_conf['character_set'].'
Content-Transfer-Encoding: 8bit
From: '.$from);
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
	$zz_error = array();
	
	$zz_error['error'] = ($return === 'exit') ? true : false;
	$zz_error['output'] = array_merge($output, $user);

	return true;
}

/**
 * outputs error messages
 *
 * @global array $zz_error ('output')
 * @return string $output
 */
function zz_error_output() {
	global $zz_error;
	if (!$zz_error['output']) return false;
	$output = '<div class="error">'.implode('<br><br>', $zz_error['output']).'</div>'."\n";
	$zz_error['output'] = array();
	return $output;
}

/**
 * output and log validation error messages
 *
 * @global array $zz_error
 */
function zz_error_validation() {
	global $zz_error;
	if (empty($zz_error['validation']['msg'])) return false;
	if (!is_array($zz_error['validation']['msg'])) return false;

	// user error message, visible to everyone
	// line breaks \n important for mailing errors
	$this_error['msg'] = '<p>'.zz_text('Following_errors_occured').': </p>'
		."\n".'<ul><li>'.implode("</li>\n<li>", $zz_error['validation']['msg'])
		.'</li></ul>';
	// if we got wrong values entered, put this into a developer message
	if (!empty($zz_error['validation']['incorrect_values'])) {
		foreach ($zz_error['validation']['incorrect_values'] as $incorrect_value) {
			$this_dev_msg[] = zz_text('Field name').': '.$incorrect_value['field_name']
				.' / '.zz_htmltag_escape($incorrect_value['msg']);
		}
		$this_error['msg_dev'] = "\n\n".implode("\n", $this_dev_msg);
	}
	if (!empty($zz_error['validation']['log_post_data'])) {
		// must be set explicitly, do not log $_POST for file upload errors
		$this_error['log_post_data'] = true;
	} else {
		$this_error['log_post_data'] = false;
	}
	$this_error['level'] = E_USER_NOTICE;
	$zz_error[] = $this_error;
	unset($zz_error['validation']);
}

/**
 * creates HTML output for 'reselect' errors which need to be checked again
 *
 * @global array $zz_error
 * @return string
 */
function zz_error_recheck() {
	global $zz_error;
	if (empty($zz_error['validation']['reselect'])) return '';
	if (!is_array($zz_error['validation']['reselect'])) return '';
	$text = '<div class="reselect"><p>'
		.zz_text('Please check these values again').': </p>'."\n"
		.'<ul><li>'.implode(".</li>\n<li>", $zz_error['validation']['reselect'])
		.'.</li></ul></div>';
	unset ($zz_error['validation']['reselect']);
	if (!$zz_error['validation']) unset($zz_error['validation']);
	return $text;
}


/**
 * log errors in $ops['error'] if zzform_multi() was called, because errors
 * won't be shown on screen in this mode
 *
 * @param array $errors = $ops['error']
 * @global array $zz_conf
 * @global array $zz_error
 * @return array $errors
 */
function zz_error_multi($errors) {
	global $zz_conf;
	if (!$zz_conf['multi']) return $errors;

	global $zz_error;
	foreach ($zz_error as $index => $error) {
		if (!is_numeric($index)) {
			if ($index === 'validation') {
				if (!empty($zz_error[$index]['msg']))
					$errors = array_merge($errors, $zz_error[$index]['msg']);
			}
			continue;
		}
		if (empty($error['msg_dev'])) continue;
		$errors[] = $error['msg_dev'];
	}
	return $errors;
}

/**
 * Generate error message if POST is too big
 *
 * @return bookl
 */
function zz_trigger_error_too_big() {
	global $zz_conf;
	global $zz_error;
	
	if (empty($zz_conf['int']['post_too_big'])) return true;
	$zz_error[] = array(
		'msg' => zz_text('Transfer failed. Probably you sent a file that was too large.').'<br>'
			.zz_text('Maximum allowed filesize is').' '
			.zz_byte_format($zz_conf['upload_MAX_FILE_SIZE']).' &#8211; '
			.sprintf(zz_text('You sent: %s data.'), zz_byte_format($_SERVER['CONTENT_LENGTH'])),
		'level' => E_USER_NOTICE
	);
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_create_topfolders($dir) {
	global $zz_error;
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
			$zz_error[] = array(
				'msg_dev' => sprintf(zz_text('Creation of directory %s failed: Parent directory is not writable.'), $dir),
				'level' => E_USER_ERROR
			);
			$zz_error['error'] = true;
			return false;
		}
		$success = mkdir($dir, 0777);
		if ($success) return true;
		//else $success = chown($dir, getmyuid());
		//if (!$success) echo 'Change of Ownership of '.$dir.' failed.<br>';
	}

	$zz_error[] = array(
		'msg_dev' => sprintf(zz_text('Creation of directory %s failed.'), $dir),
		'level' => E_USER_ERROR
	);
	$zz_error['error'] = true;
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
	$lines = zz_db_fetch($sql, array($hierarchy['id_field_name'], $hierarchy['mother_id_field_name']), 'key/value'); 
	if (!$lines) return zz_return(array(array(), 0));

	if (empty($hierarchy['id'])) 
		$hierarchy['id'] = 'NULL';

	$h_lines = array();
	foreach ($lines as $id => $mother_id) {
		// sort lines by mother_id
		if ($id == $hierarchy['id']) {
			// get uppermost line if hierarchy id is not NULL!
			$mother_id = 'TOP';
		} elseif (empty($mother_id)) {
			$mother_id = 'NULL';
		}
		$h_lines[$mother_id][$id] = $id;
	}
	if (!empty($hierarchy['hide_top_value']) AND !empty($h_lines[$hierarchy['id']])) {
		$h_lines['NULL'] = $h_lines[$hierarchy['id']];
		unset($h_lines[$hierarchy['id']]);
		$hierarchy['id'] = 'NULL';
		unset($h_lines['TOP']);
	}
	if (!$h_lines) return zz_return(array(array(), 0));
	$my_lines = zz_hierarchy_sort($h_lines, $hierarchy['id'], $hierarchy['id_field_name']);
	$total_rows = count($my_lines); // sometimes, more rows might be selected beforehands,
	return array($my_lines, $total_rows);
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
	$my_lines = array();
	$show_only = array();
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
			$h_lines['NULL'] = array($id => $id);
			$show_only[] = $id;
		}
	}
	if (!empty($h_lines[$hierarchy])) {
		foreach ($h_lines[$hierarchy] as $h_line) {
			$my_lines[$i] = array(
				$id_field => $h_line,
				'zz_level' => $level
			);
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

/*
 * --------------------------------------------------------------------
 * I - Internationalisation functions
 * --------------------------------------------------------------------
 */

/** 
 * Translate text if possible or write back text string to be translated
 * 
 * @param string $string		Text string to be translated
 * @return string $string		Translation of text
 */
function zz_text($string) {
	global $zz_conf;
	if (empty($zz_conf['generate_output'])) return $string;
	return wrap_text($string);
}

/**
 * Translate values with wrap_translate() from zzwrap library
 *
 * @param array $def ($field or $zz)
 * @param array $values
 * @return array $values (translated)
 */
function zz_translate($def, $values) {
	if (empty($def['sql_translate'])) return $values;
	if (!is_array($def['sql_translate'])) {
		$def['sql_translate'] = array($def['sql_translate']);
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
			break;
		}
		unset($values[$index]['wrap_source_language']);
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_edit_query_string($query, $unwanted_keys = array(), $new_keys = array()) {
	$query = str_replace('&amp;', '&', $query);
	if (in_array(substr($query, 0, 1), array('?', '&'))) {
		$query = substr($query, 1);
	}
	if (!is_array($unwanted_keys)) $unwanted_keys = array($unwanted_keys);
	if (!is_array($new_keys)) $new_keys = array($new_keys);
	parse_str($query, $queryparts);
	// remove unwanted keys from URI
	foreach (array_keys($queryparts) as $key) {
		if (in_array($key, $unwanted_keys)) {
			unset($queryparts[$key]);
		} elseif (is_array($queryparts[$key])) {
			foreach (array_keys($queryparts[$key]) as $subkey) {
				foreach ($unwanted_keys as $unwanted) {
					if ($unwanted === $key.'['.$subkey.']') {
						unset($queryparts[$key][$subkey]);
					}
				}
			}
		}
	}
	// add new keys or overwrite existing keys
	foreach ($new_keys as $new_key => $new_value)
		$queryparts[$new_key] = $new_value; 
	// glue everything back together
	$query_string = http_build_query($queryparts, '', '&amp;');
	if ($query_string) return '?'.$query_string; // URL without unwanted keys
	else return false;
}

/**
 * Escapes unvalidated strings for HTML values (< > & " ')
 *
 * @param string $string
 * @return string $string
 * @global array $zz_conf
 */
function zz_html_escape($string) {
	global $zz_conf;
	switch ($zz_conf['character_set']) {
		case 'iso-8859-2': $character_set = 'ISO-8859-1'; break;
		default: $character_set = $zz_conf['character_set']; break;
	}
	$new_string = @htmlspecialchars($string, ENT_QUOTES, $character_set);
	if (!$new_string) $new_string = htmlspecialchars($string, ENT_QUOTES, 'ISO-8859-1');
	return $new_string;
}

/**
 * Escapes strings for HTML text (< >)
 *
 * @param string $string
 * @return string $string
 * @global array $zz_conf
 */
function zz_htmltag_escape($string) {
	global $zz_conf;
	switch ($zz_conf['character_set']) {
		case 'iso-8859-2': $character_set = 'ISO-8859-1'; break;
		default: $character_set = $zz_conf['character_set']; break;
	}
	$new_string = @htmlspecialchars($string, ENT_NOQUOTES, $character_set);
	if (!$new_string) $new_string = htmlspecialchars($string, ENT_NOQUOTES, 'ISO-8859-1');
	$new_string = str_replace('&amp;', '&', $new_string);
	return $new_string;
}

/**
 * Escapes validated or custom set strings for HTML values (< > " ')
 *
 * @param string $string
 * @return string $string
 * @global array $zz_conf
 */
function zz_htmlnoand_escape($string) {
	global $zz_conf;
	switch ($zz_conf['character_set']) {
		case 'iso-8859-2': $character_set = 'ISO-8859-1'; break;
		default: $character_set = $zz_conf['character_set']; break;
	}
	$new_string = @htmlspecialchars($string, ENT_QUOTES, $character_set);
	if (!$new_string) $string = htmlspecialchars($string, ENT_QUOTES, 'ISO-8859-1');
	$new_string = str_replace('&amp;', '&', $new_string);
	return $new_string;
}

/**
 * formats an integer into a readable byte representation
 *
 * @param int $byts
 * @param int $precision
 * @return string
 * @see wrap_bytes
 */
function zz_byte_format($bytes, $precision = 1) { 
	global $zz_conf;
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB'); 

    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 

    // Uncomment one of the following alternatives
    // $bytes /= pow(1024, $pow);
    $bytes /= (1 << (10 * $pow)); 

    $text = round($bytes, $precision) . '&nbsp;' . $units[$pow]; 
    if ($zz_conf['decimal_point'] !== '.')
    	$text = str_replace('.', $zz_conf['decimal_point'], $text);
    return $text;
}


/*
 * --------------------------------------------------------------------
 * R - Record functions used by several modules
 * --------------------------------------------------------------------
 */

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
	return array($field_names[1], $field_names[2]);
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
 * @param int $max_select = e. g. $zz_conf['max_select'], maximum entries in
 *		option-Field before we offer a blank text field to enter values
 * @param string $long_field_name // $table_name.'[]['.$field_name.']'
 * @param string $db_table
 * @global array $zz_error
 * @global array $zz_conf
 * @return array $my_rec changed keys:
 *		'fields'[$f], 'POST', 'POST-notvalid', 'validation'
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_check_select($my_rec, $f, $max_select, $long_field_name, $db_table) {
	global $zz_error;
	global $zz_conf;

	// only for 'select'-fields with SQL query (not for enums neither for sets)
	if (empty($my_rec['fields'][$f]['sql'])) return $my_rec;
	// check if we have a value
	// check only for 0, might be problem, but 0 should always be there
	// if null -> accept it
	$field_name = $my_rec['fields'][$f]['field_name'];
	if (!$my_rec['POST'][$field_name]) return $my_rec;

	// check if we need to check
	if (empty($my_rec['fields'][$f]['always_check_select'])) {
		if (!$zz_conf['multi']) {
			if (empty($_POST['zz_check_select'])) return $my_rec;
			$check = false;
			if (in_array($field_name, $_POST['zz_check_select']))
				$check = true;
			elseif (in_array($long_field_name, $_POST['zz_check_select']))
				$check = true;
		} else {
			// with zzform_multi(), no form exists, so check per default yes
			$check = true;
			if (isset($_POST['zz_check_select'])) {
				// ... unless explicitly said not to check
				if (in_array($field_name, $_POST['zz_check_select']))
					$check = false;
				elseif (in_array($long_field_name, $_POST['zz_check_select']))
					$check = false;
			}
		}
		if (!$check) return $my_rec;
	}
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$my_rec['fields'][$f] = zz_check_select_id(
		$my_rec['fields'][$f], $my_rec['POST'][$field_name], $db_table, $my_rec['id']
	);
	$possible_values = $my_rec['fields'][$f]['possible_values'];

	$error = false;
	if (!count($possible_values)) {
		// no records, user must re-enter values
		$my_rec['fields'][$f]['type'] = 'select';
		$my_rec['fields'][$f]['class'] = 'reselect';
		$my_rec['fields'][$f]['suffix'] = '<br>'
			.zz_text('No entry found. Try less characters.');
		$my_rec['fields'][$f]['mark_reselect'] = true;
		$my_rec['validation'] = false;
		$error = true;
	} elseif (count($possible_values) === 1) {
		// exactly one record found, so this is the value we want
		$my_rec['POST'][$field_name] = current($possible_values);
		$my_rec['POST-notvalid'][$field_name] = current($possible_values);
		// if other fields contain errors:
		$my_rec['fields'][$f]['sql'] = $my_rec['fields'][$f]['sql_new'];
	} elseif (count($possible_values) <= $max_select) {
		// let user reselect value from dropdown select
		$my_rec['fields'][$f]['type'] = 'select';
		$my_rec['fields'][$f]['sql'] = $my_rec['fields'][$f]['sql_new'];
		$my_rec['fields'][$f]['class'] = 'reselect';
		if (!empty($my_rec['fields'][$f]['show_hierarchy'])) {
			// since this is only a part of the list, hierarchy does not make sense
			if (!isset($my_rec['fields'][$f]['sql_ignore'])) {
				$my_rec['fields'][$f]['sql_ignore'] = array();
			} elseif (!is_array($my_rec['fields'][$f]['sql_ignore'])) {
				$my_rec['fields'][$f]['sql_ignore'] = array($my_rec['fields'][$f]['sql_ignore']);
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
		$my_rec['fields'][$f]['class'] = 'reselect';
		$my_rec['fields'][$f]['suffix'] = ' '.zz_text('Please enter more characters.');
		$my_rec['fields'][$f]['mark_reselect'] = true;
		$my_rec['validation'] = false;
		$error = true;
	} else {
		$my_rec['fields'][$f]['class'] = 'error' ;
		$my_rec['fields'][$f]['check_validation'] = false;
		$my_rec['validation'] = false;
		$error = true;
	}
	if ($error AND $zz_conf['multi']) {
		$zz_error[] = array(
			'msg_dev' => sprintf('No entry found: value %s in field %s.',
				$my_rec['POST'][$field_name], $field_name)
				.' <br>SQL: '.$my_rec['fields'][$f]['sql_new']
		);
	}
	return zz_return($my_rec);
}

/**
 * Query possible values from database for a given SQL query and a given value
 *
 * @param array $field
 * @param string $postvalue
 * @param string $db_table
 * @param array $id = $zz_tab[$tab][$rec]['id']
 * @global array $zz_conf bool 'multi'
 * @return array $field
 */
function zz_check_select_id($field, $postvalue, $db_table, $id) {
	global $zz_conf;
	
	// 1. get field names from SQL query
	$field['sql_fieldnames'] = zz_sql_fieldnames($field['sql']);
	foreach ($field['sql_fieldnames'] as $index => $sql_fieldname) {
		$sql_fieldname = trim($sql_fieldname);
		if (!empty($field['show_hierarchy'])
			AND $sql_fieldname === $field['show_hierarchy']) {
			// do not search in show_hierarchy as this field is there for 
			// presentation only and might be removed below!
			unset($field['sql_fieldnames'][$index]);	
		} else {
			// write trimmed value back to sql_fieldnames
			$field['sql_fieldnames'][$index] = $sql_fieldname;
		}
	}

	// 2. get posted values, field by field
	if (!isset($field['concat_fields'])) $concat = ' | ';
	else $concat = $field['concat_fields'];
	$postvalues = explode($concat, $postvalue);

	$use_single_comparison = false;
	if (substr($postvalue, -1) !== ' ' AND !$zz_conf['multi']) {
		// if there is a space at the end of the string, don't do LIKE 
		// with %!
		$likestring = '%s LIKE %s"%%%s%%"';
	} else {
		$likestring = '%s = %s"%s"';
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
		if (preg_match('/^(.+?) *\.\.\. *$/', $value, $short_value)) {
			// reduces string with dots which come from values which have 
			// been cut beforehands, use LIKE!
			$value = $short_value[1];
			$my_likestring = '%s LIKE %s"%s%%"';
		}
		// maybe there is no index 0, therefore we need a new variable $i
		$i = 0;
		foreach ($sql_fieldnames as $index => $sql_fieldname) {
			// don't trim value here permanently (or you'll have a problem with
			// reselect)
			if (is_numeric(trim($value))) {
				// no character set needed for numeric values
				$collation = '';
			} else {
				$collation = zz_db_field_collation(
					'reselect', $db_table, $field, $index
				);
			}
			if (!$wheresql) $wheresql .= '(';
			elseif (!$i) $wheresql .= ' ) AND (';
			elseif ($use_single_comparison) $wheresql .= ' AND ';
			else $wheresql .= ' OR ';

			$wheresql .= sprintf($my_likestring, $sql_fieldname, $collation,
				zz_db_escape(trim($value)));
			if ($use_single_comparison) {
				unset ($sql_fieldnames[$index]);
				continue 2;
			}
			$i++;
		}
	}
	if ($wheresql) $wheresql .= ')';
	if (!empty($field['show_hierarchy_same_table']) AND !empty($id['value'])) {
		$wheresql .= sprintf(' AND `%s` != %d', $id['field_name'], $id['value']);
	}
	if ($wheresql) {
		$field['sql_new'] = zz_edit_sql($field['sql'], 'WHERE', $wheresql);
	} elseif ($field['sql'] === 'SHOW DATABASES') {
		$likestring = str_replace('=', 'LIKE', $likestring);
		$field['sql_new'] = sprintf($likestring, 'SHOW DATABASES', '', trim($value));
	} else {
		$field['sql_new'] = $field['sql'];
	}
	$field['possible_values'] = zz_db_fetch(
		$field['sql_new'], 'dummy_id', 'single value'
	);
	return $field;
}
