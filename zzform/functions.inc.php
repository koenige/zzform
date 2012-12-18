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
 * I - Internationalisation functions
 * O - Output functions
 * R - Record functions used by several modules
 * V - Validation, preparation for database
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2012 Gustaf Mossakowski
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
	$debug_started = false;
	if (!empty($GLOBALS['zz_conf']['modules']['debug'])) {
		zz_debug('start', __FUNCTION__);
		$debug_started = true;
	}
//	initialize variables
	$mod = array();
	$zz_default = array();
	$zz_conf = array();
	$add = false;

//	import modules
	foreach ($modules as $module) {
		if (!empty($GLOBALS['zz_conf']['modules'][$module])) {
			// we got that already
			$mod[$module] = true;
			continue;
		}
		if ($module == 'debug' AND empty($GLOBALS['zz_conf']['debug'])) {
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
		if (!empty($mod['debug']) OR !empty($GLOBALS['zz_conf']['modules']['debug'])) {
			if (!$debug_started) {
				zz_debug('start', __FUNCTION__);
				$debug_started = true;
			}
			if ($mod[$module]) $debug_msg = 'Module %s included';
			else $debug_msg = 'Module %s not included';
			zz_debug(sprintf($debug_msg, $module));
		}
	}

	if ($add) {
		// import variables from internal modules
		$GLOBALS['zz_conf'] = zz_array_merge($GLOBALS['zz_conf'], $zz_conf);
		zz_write_defaults($zz_default, $GLOBALS['zz_conf']);
		// zzform_multi: module might be added later, so add default variables
		// for $zz_saved as well
		if (!empty($GLOBALS['zz_saved']['conf'])) {
			$GLOBALS['zz_saved']['conf'] = zz_array_merge(
				$GLOBALS['zz_saved']['conf'], $zz_conf
			);
			zz_write_defaults($zz_default, $GLOBALS['zz_saved']['conf']);
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
 * @return bool $post_too_big
 */
function zz_dependent_modules($zz) {
	global $zz_conf;

	// check if POST is too big, then it will be empty
	$post_too_big = $zz_conf['generate_output'] ? zzform_post_too_big() : false;

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
			if (!empty($zz_conf['if'])) {
				foreach ($zz_conf['if'] as $condition) {
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
			if ($post_too_big) break;
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
	return $post_too_big;
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
	$domains = array('fields', 'conditional_fields');
	foreach ($domains as $domain) {
		if (empty($zz[$domain])) continue;
		foreach ($zz[$domain] as $field) {
			if (!empty($field[$key]) AND $field[$key] == $type) {
				return true;
			}
			if (!empty($field['if'])) {
				foreach ($field['if'] as $condfield) {
					if (!empty($condfield[$key]) AND $condfield[$key] == $type) {
						return true;
					}
				}
			}
			if (empty($field['fields'])) continue;
			foreach ($field['fields'] as $index => $subfield) {
				if (!is_array($subfield)) continue;
				if (!empty($subfield[$key]) AND $subfield[$key] == $type) {
					return true;
				}
				if (empty($subfield['if'])) continue;
				foreach ($subfield['if'] as $condfield) {
					if (!empty($condfield[$key]) AND $condfield[$key] == $type) {
						return true;
					}
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
	$url['scheme'] = (isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] == 'on') 
		? 'https'
		: 'http';
	$host = $_SERVER['HTTP_HOST']
		? htmlspecialchars($_SERVER['HTTP_HOST'])
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
	$examplebase = (substr($url_self, 0, 1) == '/') ? $url['base'] : '';
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
			if (!empty($base_uri_query[$key]) AND $base_uri_query[$key] == $value) {
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
 * @global array $zz_conf
 *		'filter' will be checked for 'where'-filter and set if there is one
 * @return array $zz_var
 *		'where_condition' (conditions set by where, add and filter), 'zz_fields'
 *		(values for fields depending on where conditions)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_get_where_conditions($zz) {
	global $zz_conf;

	$zz_var = array();
	// WHERE: Add with suggested values
	$zz_var['where_condition'] = array();
	if (!empty($_GET['where'])) {
		$zz_var['where_condition'] = $_GET['where'];
	}
	if (!empty($zz['where'])) {
		// $zz['where'] will be merged to $_GET['where'], identical keys
		// will be overwritten
		$zz_var['where_condition'] = array_merge(
			$zz_var['where_condition'], $zz['where']
		);
	}

	// ADD: overwrite write_once with values, in case there are identical fields
	if (!empty($_GET['add'])) {
		$zz_var['where_condition'] = array_merge(
			$zz_var['where_condition'], $_GET['add']
		);
		foreach ($_GET['add'] as $key => $value) {
			$zz_var['zz_fields'][$key]['value'] = $value;
			$zz_var['zz_fields'][$key]['type'] = 'hidden';
		}
	}

	// FILTER: check if there's a 'where'-filter
	if (empty($zz_conf['filter'])) $zz_conf['filter'] = array();
	foreach ($zz_conf['filter'] AS $index => $filter) {
		if (!empty($filter['where'])
			AND !empty($zz_var['where_condition'])
			AND in_array($filter['where'], array_keys($zz_var['where_condition']))
		) {
			// where-filter makes no sense since already one of the values
			// is filtered by WHERE filter
			unset($zz_conf['filter'][$index]);
		}
		if ($filter['type'] != 'where') continue;
		if (!empty($_GET['filter'][$filter['identifier']])) {
			$zz_var['where_condition'][$filter['where']] 
				= $_GET['filter'][$filter['identifier']];
		}
		// 'where'-filters are beyond that 'list'-filters
		$zz_conf['filter'][$index]['type'] = 'list';
	}

	return $zz_var;
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
		'access', 'edit', 'delete', 'add', 'view', 'if', 'details', 
		'details_url', 'details_base', 'details_target', 'details_referer',
		'details_sql', 'max_select', 'max_select_val_len', 'copy', 'no_ok',
		'cancel_link'
	);
	$zz_conf_record = array();
	foreach ($wanted_keys as $key) {
		if (isset($zz_conf[$key])) {
			$zz_conf_record[$key] = $zz_conf[$key];
		} elseif ($key == 'access') {
			$zz_conf_record['access'] = '';
		}
	}
	return $zz_conf_record;
}

/**
 * checks filter, sets default values and identifier
 *
 * @global array $zz_conf 'filter'
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_filter_defaults() {
	global $zz_conf;
	// initialize, don't return because we'll check for $_GET later
	if (empty($zz_conf['filter'])) $zz_conf['filter'] = array();
	$identifiers = array();

	// if there are filters:
	// initialize filter, set defaults
	foreach ($zz_conf['filter'] AS $index => $filter) {
		// get identifier from title if not set
		if (empty($filter['identifier'])) {
			$filter['identifier'] = urlencode(strtolower($filter['title']));
			$zz_conf['filter'][$index]['identifier'] = $filter['identifier'];
		}
		$identifiers[] = $filter['identifier'];
		// set default filter, default default filter is 'all'
		if (empty($filter['default_selection'])) continue;
		if (isset($_GET['filter'][$filter['identifier']])) continue;
		$_GET['filter'][$filter['identifier']] = is_array($filter['default_selection'])
			? key($filter['default_selection'])
			: $filter['default_selection'];
	}

	// check for invalid filters
	$zz_conf['int']['invalid_filters'] = array();
	if (empty($_GET['filter'])) {
		// just in case it's a ?filter -request with no filter set
		if (isset($_GET['filter'])) unset($_GET['filter']);
		return true;
	}
	foreach (array_keys($_GET['filter']) AS $identifier) {
		if (in_array($identifier, $identifiers)) continue;
		$zz_conf['int']['http_status'] = 404;
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
			$zz_conf['int']['url']['qs_zzform'], array('filter['.$identifier.']')
		);
		$zz_conf['int']['invalid_filters'][] = htmlspecialchars($identifier);
		// get rid of filter
		unset($_GET['filter'][$identifier]);
	}
	return true;
}

/**
 * checks filter, gets selection, sets hierarchy values
 *
 * @param array $zz
 * @global array $zz_conf ('filter', might be changed)
 * @global array $zz_error
 * @return array ($zz, 'hierarchy' will be changed if corresponding filter)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_apply_filter($zz) {
	global $zz_conf;
	global $zz_error;
	if (empty($zz_conf['filter'])) return $zz;

	// set filter for complete form
	foreach ($zz_conf['filter'] AS $index => &$filter) {
		if (!isset($filter['selection'])) $filter['selection'] = array();
		// get 'selection' if sql query is given
		if (!empty($filter['sql'])) {
			if (!empty($filter['depends_on']) 
			AND isset($zz_conf['filter'][$filter['depends_on']])) {
				$depends_on = $zz_conf['filter'][$filter['depends_on']];
				if (!empty($_GET['filter'][$depends_on['identifier']])) {
					$where = $depends_on['where'].' = '.zz_db_escape(
						$_GET['filter'][$depends_on['identifier']]
					);
					$filter['sql'] = zz_edit_sql($filter['sql'], 'WHERE', $where);
				}
				$zz_conf['filter'][$filter['depends_on']]['subfilter'][] = $index;
			}
			$elements = zz_db_fetch($filter['sql'], '_dummy_id_', 'key/value');
			if ($zz_error['error']) continue;
			// don't show filter if we have only one element
			if (count($elements) <= 1) {
				unset($filter);
				continue;
			}
			foreach ($elements as $key => $value) {
				$filter['selection'][$key] = $value;
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
		if (empty($_GET['filter'])) continue;
		if (!in_array($filter['identifier'], array_keys($_GET['filter']))) continue;
		if ($filter['type'] !== 'show_hierarchy') continue;

		$selection = zz_in_array_str(
			$_GET['filter'][$filter['identifier']], array_keys($filter['selection'])
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
					htmlspecialchars($_GET['filter'][$filter['identifier']])),
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
 * applies where conditions to get different sql query, id values and some
 * further variables for nice headings etc.
 *
 * @param array $zz_var
 *		'where_condition' from zz_get_where_conditions(), 'unique_fields'
 * @param string $sql Main SQL query
 * @param string $table Name of main table
 * @param array $table_for_where (optional)
 * @global array $zz_conf checks for 'modules'['debug']
 * @return array
 *		string $sql = modified main query (if applicable)
 *		array $zz_var
 *			'where', 'where_with_unique_id', 'where_condition', 'id', 
 *			'unique_fields'
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @see zz_get_where_conditions(), zz_get_unique_fields()
 */
function zz_apply_where_conditions($zz_var, $sql, $table, $table_for_where = array()) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	// set some keys
	$zz_var['where'] = false;
	$zz_var['where_with_unique_id'] = false;
	
	if (!$zz_var['where_condition']) return zz_return(array($sql, $zz_var));

	foreach ($zz_var['where_condition'] as $field_name => $value) {
		// check for illegal characters
		if (strstr($field_name, ' ') OR strstr($field_name, ';')) {
			unset($zz_var['where_condition'][$field_name]);
			continue;
		}
		$submitted_field_name = $field_name;
		// check if field_name comprises table_name
		if (strstr($field_name, '.')) {
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
				AND $zz_var['where_condition'][$field_name] == 'NULL')
			{
				$sql = zz_edit_sql($sql, 'WHERE', 
					'ISNULL('.$field_reference.')');
				continue; // don't use NULL as where variable!
			} elseif (!empty($zz_var['where_condition'][$field_name])
				AND $zz_var['where_condition'][$field_name] == '!NULL')
			{
				$sql = zz_edit_sql($sql, 'WHERE', 
					'!ISNULL('.$field_reference.')');
				continue; // don't use !NULL as where variable!
			} else {
				$sql = zz_edit_sql($sql, 'WHERE', 
					$field_reference." = '".zz_db_escape($value)."'");
			}
		}

// hier auch fuer write_once
		$zz_var['where'][$table_name][$field_name] = $value;

		if ($field_name == $zz_var['id']['field_name']) {
			$zz_var['where_with_unique_id'] = true;
			$zz_var['id']['value'] = $value;
		} elseif (in_array($field_name, array_keys($zz_var['unique_fields']))) {
			$zz_var['where_with_unique_id'] = true;
		}
	}
	// in case where is not combined with ID field but UNIQUE field
	// (e. g. identifier with UNIQUE KEY) retrieve value for ID field from 
	// database
	if (!$zz_var['id']['value'] AND $zz_var['where_with_unique_id']) {
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
		if (!$zz_var['id']['value']) $zz_var['where_with_unique_id'] = false;
	}
	
	// where with unique ID: remove filters, they do not make sense here
	// (single record will be shown)
	if ($zz_var['where_with_unique_id']) {
		unset($zz_conf['filter']);
		unset($_GET['filter']);
	}
	
	return zz_return(array($sql, $zz_var));
}

/** 
 * Fills field definitions with default definitions and infos from database
 * 
 * @param array $fields
 * @param string $db_table [i. e. db_name.table]
 * @param bool $multiple_times marker for conditions
 * @param string $mode (optional, $ops['mode'])
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_fill_out($fields, $db_table, $multiple_times = false, $mode = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) {
		zz_debug('start', __FUNCTION__.$multiple_times);
	}
	static $defs;
	$hash = md5(serialize($fields).$db_table.$multiple_times.$mode);
	if (!empty($defs[$hash])) return zz_return($defs[$hash]);

	foreach (array_keys($fields) as $no) {
		if (!empty($fields[$no]['if'])) {
			if (!$multiple_times) {
				// we don't need these anymore
				unset($fields[$no]['if']);
			} elseif ($multiple_times == 1) {
				 // if there are only conditions, go on
				if (count($fields[$no]) == 1) continue;
			}
		}
		if (!$fields[$no]) {
			// allow placeholder for fields to get them into the wanted order
			unset($fields[$no]);
			continue;
		}
		if (!isset($fields[$no]['type'])) // default type: text
			$fields[$no]['type'] = 'text';
		if ($fields[$no]['type'] == 'id' AND !isset($fields[$no]['dont_sort'])) {
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

		if ($zz_conf['multilang_fieldnames'] AND empty($fields[$no]['translated'])) {
			// translate fieldnames, if set
			$to_translates = array(
				'title', 'explanation', 'title_append', 'title_tab'
			);
			foreach ($to_translates as $to_translate) {
				if (empty($fields[$no][$to_translate])) continue;
				$fields[$no][$to_translate] = zz_text($fields[$no][$to_translate]);
			}
			$fields[$no]['translated'] = true;
		}
		if ($fields[$no]['type'] == 'option') {
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
		}
		// initialize
		if (!isset($fields[$no]['explanation'])) {
			$fields[$no]['explanation'] = false;
		}
		if (!$multiple_times) {
			if (!isset($fields[$no]['maxlength']) 
				&& isset($fields[$no]['field_name'])
				AND $mode !== 'list_only' AND $mode !== 'show') 
			{
				// no need to check maxlength in list view only 
				$fields[$no]['maxlength'] = zz_db_field_maxlength(
					$fields[$no]['field_name'], $fields[$no]['type'], $db_table
				);
			} else {
				$fields[$no]['maxlength'] = 32;
			}
			if (!empty($fields[$no]['sql'])) // replace whitespace with space
				$fields[$no]['sql'] = preg_replace("/\s+/", " ", $fields[$no]['sql']);
		}
		if ($fields[$no]['type'] == 'subtable') {
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
				$mode
			);
		}
		$fields[$no]['required'] = zz_fill_out_required($fields[$no], $db_table);
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
	// might be empty string
	if (!empty($field['null_string'])) return false;
	// no field name = not in database
	if (empty($field['field_name'])) return false;
	// might be NULL
	if (zz_db_field_null($field['field_name'], $db_table)) return false;
	// some field types never can be required
	$never_required = array('calculated', 'display', 'option', 'image', 
		'foreign', 'subtable');
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
		'zzform_calls', 'int', 'id', 'footer_text', 'additional_text', 
		'breadcrumbs', 'dont_show_title_as_breadcrumb', 'error_handling',
		'error_log', 'format', 'group_html_table', 'list_display',
		'limit_display', 'logging', 'logging_id', 'logging_table',
		'log_missing_text', 'mail_subject_prefix', 'title_separator',
		'referer', 'access', 'heading_prefix'
	);
	foreach ($uninteresting_zz_conf_keys as $key) unset($zz_conf[$key]);
	$uninteresting_zz_keys = array(
		'title', 'explanation', 'subtitle', 'explanation_hidden_while_editing',
		'list'
	);
	foreach ($uninteresting_zz_keys as $key) unset($zz[$key]);
	$my['zz'] = $zz;
	$my['zz_conf'] = $zz_conf;
	$hash = sha1(serialize($my));
	return $hash;
}

/**
 * gets unique and id fields for further processing
 *
 * @param array $zz_var
 * @param array $fields
 * @global array $zz_error
 * @return array $zz_var
 *		'id'[value], 'id'[field_name], 'unique_fields'
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_get_unique_fields($zz_var, $fields) {
	global $zz_error;

	// set id to false
	$zz_var['id']['value'] = false;
	$zz_var['id']['field_name'] = false;
	$zz_var['unique_fields'] = array(); // for WHERE

	foreach ($fields AS $field) {
		// set ID fieldname
		if (!empty($field['type']) AND $field['type'] == 'id') {
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
 * creates array for each detail table in $zz_tab
 *
 * @param array $field = $zz['fields'][$no] with subtable definition
 * @param array $main_tab = $zz_tab[0] for main record
 * @param int $tab = number of subtable
 * @param int $no = number of subtable definition in $zz['fields']
 * @global array $zz_conf
 *		'max_detail_records', 'min_detail_records'
 * @global array $_POST
 * @return array $my_tab = $zz_tab[$tab]
 *		'no', 'sql', 'sql_not_unique', 'keep_detailrecord_shown', 'db_name'
 *		'table', 'table_name', 'values', 'fielddefs', 'max_records', 
 *		'min_records', 'records_depend_on_upload', 
 *		'records_depend_on_upload_more_than_one', 'foreign_key_field_name',
 *		'translate_field_name', 'detail_key', 'tick_to_save', 'access'
 */
function zz_get_subtable($field, $main_tab, $tab, $no) {
	global $zz_conf;

	// basics for all subrecords of the same table
	$my_tab = array();

	// no in $zz['fields']
	$my_tab['no'] = $no;

	// SQL query
	$my_tab['sql'] = $field['sql'];
	if (empty($field['sql_not_unique'])) {
		$my_tab['sql_not_unique'] = false;
		$my_tab['keep_detailrecord_shown'] = false;
	} else {
		$my_tab['sql_not_unique'] = $field['sql_not_unique'];
		$my_tab['keep_detailrecord_shown'] = true;
	}

	// database and table name
	if (strstr($field['table'], '.')) {
		$table = explode('.', $field['table']);
		$my_tab['db_name'] = $table[0];
		$my_tab['table'] = $table[1];
	} else {
		$my_tab['db_name'] = $main_tab['db_name'];
		$my_tab['table'] = $field['table'];
	}
	$my_tab['table_name'] = $field['table_name'];
	
	// pre-set values
	$my_tab['values'] = !empty($field['values']) ? $field['values'] : array();
	$my_tab['fielddefs'] = !empty($field['fielddefs']) ? $field['fielddefs'] : array();

	// records
	$my_tab['max_records'] = isset($field['max_records'])
		? $field['max_records'] : $zz_conf['max_detail_records'];
	$my_tab['min_records'] = isset($field['min_records'])
		? $field['min_records'] : $zz_conf['min_detail_records'];
	$my_tab['min_records_required'] = isset($field['min_records_required'])
		? $field['min_records_required'] : 0;
	if ($my_tab['min_records'] < $my_tab['min_records_required'])
		$my_tab['min_records'] = $my_tab['min_records_required'];
	$my_tab['records_depend_on_upload'] = isset($field['records_depend_on_upload'])
		? $field['records_depend_on_upload'] : false;
	$my_tab['records_depend_on_upload_more_than_one'] = 
		isset($field['records_depend_on_upload_more_than_one'])
		? $field['records_depend_on_upload_more_than_one'] : false;
	
	// foreign keys, translation keys, unique keys
	$my_tab['foreign_key_field_name'] = (!empty($field['foreign_key_field_name']) 
		? $field['foreign_key_field_name'] 
		: $main_tab['table'].'.'.$main_tab[0]['id']['field_name']);
	$my_tab['translate_field_name'] = !empty($field['translate_field_name']) 
		? $field['translate_field_name'] : false;
	$my_tab['unique'] = !empty($field['unique']) ? $field['unique'] : false;

	// get detail key, if there is a field definition with it.
	// get id field name
	$password_fields = array();
	foreach ($field['fields'] AS $subfield) {
		if (!isset($subfield['type'])) continue;
		if ($subfield['type'] == 'password') {
			$password_fields[] = $subfield['field_name'];
		}
		if ($subfield['type'] == 'password_change') {
			$password_fields[] = $subfield['field_name'];
		}
		if ($subfield['type'] == 'id') {
			$my_tab['id_field_name'] = $subfield['field_name'];
		}
		if ($subfield['type'] != 'detail_key') continue;
		if (empty($main_tab[0]['fields'][$subfield['detail_key']])) continue;
		$detail_key_index = isset($subfield['detail_key_index']) 
			? $subfield['detail_key_index'] : 0;
		$my_tab['detail_key'][] = array(
			'tab' => $main_tab[0]['fields'][$subfield['detail_key']]['subtable'], 
			'rec' => $detail_key_index
		);
	}

	// tick to save
	$my_tab['tick_to_save'] = !empty($field['tick_to_save']) 
		? $field['tick_to_save'] : '';

	// access
	$my_tab['access'] = isset($field['access'])
		? $field['access'] : false;
	
	// POST array
	// buttons: add, remove subrecord
	$my_tab['subtable_deleted'] = array();
	if (isset($_POST['zz_subtable_deleted'][$my_tab['table_name']]))
	//	fill existing zz_subtable_deleted ids in $my_tab['subtable_deleted']
		foreach ($_POST['zz_subtable_deleted'][$my_tab['table_name']] as $deleted)
			$my_tab['subtable_deleted'][] = $deleted[$my_tab['id_field_name']];
	$my_tab['subtable_add'] = (!empty($_POST['zz_subtables']['add'][$tab]) 
		AND $my_tab['access'] != 'show')
		? $_POST['zz_subtables']['add'][$tab] : false;
	$my_tab['subtable_remove'] = (!empty($_POST['zz_subtables']['remove'][$tab]) 
		AND $my_tab['access'] != 'show')
		? $_POST['zz_subtables']['remove'][$tab] : array();

	// tick for save
	$my_tab['zz_save_record'] = !empty($_POST['zz_save_record'][$tab])
		? $_POST['zz_save_record'][$tab] : array();

	$my_tab['POST'] = (!empty($_POST) AND !empty($_POST[$my_tab['table_name']]) 
		AND is_array($_POST[$my_tab['table_name']]))
		? $_POST[$my_tab['table_name']] : array();
	// POST is secured, now get rid of password fields in case of error_log_post
	foreach ($password_fields AS $password_field)
		unset($_POST[$my_tab['table_name']][$password_field]);

	// subtable_remove may come with ID
	foreach (array_keys($my_tab['subtable_remove']) as $rec) {
		if (empty($my_tab['subtable_remove'][$rec])) continue;
		if (!empty($my_tab['POST'][$rec][$my_tab['id_field_name']])) // has ID?
			$my_tab['subtable_deleted'][] = $my_tab['POST'][$rec][$my_tab['id_field_name']];
	}
	
	return $my_tab;
} 

/**
 * creates array for each detail record in $zz_tab[$tab]
 *
 * @param string $mode ($ops['mode'])
 * @param array $field
 * @param array $my_tab = $zz_tab[$tab]
 * @param array $main_tab = $zz_tab[0]
 * @param array $zz_var
 * @param array $tab = tabindex
 * @return array $my_tab
 */
function zz_get_subrecords($mode, $field, $my_tab, $main_tab, $zz_var, $tab) {
	global $zz_error;
	global $zz_conf;
	
	// set general definition for all $my_tab[$rec] (kind of a record template)
	$rec_tpl = array();
	$rec_tpl['fields'] = $field['fields'];
	$rec_tpl['access'] = $my_tab['access'];
	$rec_tpl['id']['field_name'] = $my_tab['id_field_name'];
	$rec_tpl['validation'] = true;
	$rec_tpl['record'] = false;
	$rec_tpl['action'] = false;

	// get state
	if ($mode == 'add' OR $zz_var['action'] == 'insert')
		$state = 'add';
	elseif ($mode == 'edit' OR $zz_var['action'] == 'update')
		$state = 'edit';
	elseif ($mode == 'delete' OR $zz_var['action'] == 'delete')
		$state = 'delete';
	else
		$state = 'show';

	// records may only be removed in state 'edit' or 'add'
	// but not with access = show
	if (($state == 'add' OR $state == 'edit') AND $rec_tpl['access'] != 'show') {
		// remove deleted subtables
		foreach (array_keys($my_tab['subtable_remove']) as $rec) {
			if (empty($my_tab['subtable_remove'][$rec])) continue;
			unset($my_tab['POST'][$rec]);
			$my_tab['subtable_focus'] = $rec-1;
		}
		$my_tab['subtable_remove'] = array();
	} else {
		// existing records might not be deleted in this mode!
		$my_tab['subtable_deleted'] = array();
	}

	// get detail records from database 
	// subtable_deleted is empty in case of 'action'
	// remove records which have been deleted by user interaction
	if (in_array($state, array('edit', 'delete', 'show'))) {
		// add: no record exists so far
		$my_tab['existing'] = zz_query_subrecord(
			$my_tab, $main_tab['table'], $main_tab[0]['id']['value'],
			$rec_tpl['id']['field_name'], $my_tab['subtable_deleted']
		); 
	} else {
		$my_tab['existing'] = array();
	}
	if (!empty($zz_error['error'])) return $my_tab;
	// get detail records for source_id
	$source_values = array();
	if ($mode == 'add' AND !empty($main_tab[0]['id']['source_value'])) {
		$my_tab['POST'] = zz_query_subrecord(
			$my_tab, $main_tab['table'], $main_tab[0]['id']['source_value'],
			$rec_tpl['id']['field_name'], $my_tab['subtable_deleted']
		);
		if (!empty($zz_error['error'])) return $my_tab;
		// get rid of foreign_keys and ids
		foreach ($my_tab['POST'] as $post_id => &$post_field) {
			foreach ($rec_tpl['fields'] AS $my_field) {
				if (empty($my_field['type'])) continue;
				if ($my_field['type'] == 'id') {
					$source_values[$post_id] = $post_field[$my_field['field_name']];
					$post_field[$my_field['field_name']] = '';
				} elseif ($my_field['type'] == 'foreign_key') {
					$post_field[$my_field['field_name']] = '';
				}
			}
		}
	}

	// check if we have a sync or so and there's a detail record with
	// a unique field: get the existing detail record id if there's one
	if (!empty($zz_conf['multi'])) {
		$my_tab['POST'] = zz_subrecord_unique($my_tab, $field['fields']);
	}

	if ($my_tab['values']) {
		// get field names for values
		$values = zz_values_get_fields($my_tab['values'], $rec_tpl['fields']);
		// look for matches between values and existing records
		list($records, $existing_ids, $my_tab['existing'], $values) 
			= zz_subrecord_values_existing($values, $my_tab['existing']);
	} else {
		$values = array();
		$records = array_values($my_tab['existing']);
		// saved ids separately for later use
		$existing_ids = array_keys($my_tab['existing']);
		// save existing records without IDs as key but numeric
		$my_tab['existing'] = array_values($my_tab['existing']);
	}
	
	$start_new_recs = count($records);
	if ($my_tab['max_records'] < $start_new_recs) $start_new_recs = -1;

	// now go into each individual subrecord
	// assign POST array, first existing records, then new records,
	// ignore illegally sent records
	$post = array();
	
	foreach ($my_tab['POST'] as $rec => $posted) {
		if (!empty($posted[$my_tab['id_field_name']])) {
			// this will only occur if main record is updated or deleted!
			// check if posted ID is in existing IDs
			$key = array_search($posted[$my_tab['id_field_name']], $existing_ids);
			if ($key === false) {
				// illegal ID, this will only occur if user manipulated the form
				$zz_error[] = array(
					'msg_dev' => 'Detail record with invalid ID was posted '
						.'(ID was said to be '.$posted[$my_tab['id_field_name']]
						.', main record was ID '.$zz_var['id']['value'].')',
					'level' => E_USER_NOTICE
				);
				unset($my_tab['POST'][$rec]);
				continue;
			}
		} elseif (in_array($state, array('add', 'edit')) 
			AND $rec_tpl['access'] != 'show' AND $values 
			AND false !== $my_key = zz_values_get_equal_key($values, $my_tab['POST'][$rec])) {
			$key = $my_key;
		} elseif (in_array($state, array('add', 'edit')) 
			AND $rec_tpl['access'] != 'show'
			AND $start_new_recs >= 0) {
			// this is a new record, append it
			$key = $start_new_recs;
			$my_tab['existing'][$key] = array(); // no existing record exists
			// get source_value key
			if ($mode == 'add' AND !empty($main_tab[0]['id']['source_value'])) {
				$my_tab['source_values'][$key] = $source_values[$rec];
			}
			$start_new_recs++;
			if ($my_tab['max_records'] < $start_new_recs) $start_new_recs = -1;
		} else {
			// this is not allowed (wrong state or access: show, 
			// too many detail records)
			unset($my_tab['POST'][$rec]);
			continue;
		}
		$post[$key] = $my_tab['POST'][$rec];
		$records[$key] = $my_tab['POST'][$rec];
		unset($my_tab['POST'][$rec]);
	}
	$my_tab['POST'] = $post;
	unset($post);
	
	// get all keys (some may only be in existing, some only in POST (new ones))
	$my_tab['records'] = count($records);

	foreach (array_keys($records) AS $rec) {
		if (empty($my_tab['POST'][$rec]) AND !empty($my_tab['existing'][$rec])) {
			$my_tab['POST'][$rec] = $my_tab['existing'][$rec];
		} elseif (empty($my_tab['POST'][$rec]) AND !empty($records[$rec])) {
			$my_tab['POST'][$rec] = $records[$rec];
		}
		// set values, defaults if forgotten or overwritten
		$my_tab['POST'][$rec] = zz_check_def_vals(
			$my_tab['POST'][$rec], $field['fields'], $my_tab['existing'][$rec],
			(!empty($zz_var['where'][$my_tab['table_name']]) 
				? $zz_var['where'][$my_tab['table_name']] 
				: ''
			)
		);
	}

	// first check for review or access, 
	// first if must be here because access might override mode here!
	if (in_array($state, array('add', 'edit')) AND $rec_tpl['access'] != 'show') {
		// check if user wants one record more (subtable_remove was already
		// checked beforehands)
		if ($my_tab['subtable_add']) {
			$my_tab['subtable_add'] = array();
			$my_tab['subtable_focus'] = $my_tab['records'];
			$my_tab['records']++;
			$tempvar = array_keys($records);
			$records[] = end($tempvar)+1;
		}
		if ($my_tab['records'] < $my_tab['min_records']) 
			$my_tab['records'] = $my_tab['min_records'];
		// always show one record minimum
		if ($zz_conf['always_show_empty_detail_record'])
			if (!$my_tab['records']) $my_tab['records'] = 1;
	}

	// check records against database, if we have values, check number of records
	if ($mode) {
		$my_tab = zz_get_subrecords_mode(
			$my_tab, $rec_tpl, $zz_var, $existing_ids
		);
	} elseif ($zz_var['action'] AND !empty($my_tab['POST'])) {
		// individual definition
		foreach (array_keys($records) as $rec) {
			$my_tab[$rec] = $rec_tpl;
			$my_tab[$rec]['save_record']
				= isset($my_tab['zz_save_record'][$rec])
				? $my_tab['zz_save_record'][$rec]
				: '';
			$my_tab[$rec]['id']['value'] 
				= isset($my_tab['POST'][$rec][$rec_tpl['id']['field_name']])
				? $my_tab['POST'][$rec][$rec_tpl['id']['field_name']]
				: '';
			// set values, rewrite POST-Array
			if (!empty($my_tab['values'])) {
				$my_tab = zz_set_values($my_tab, $rec, $zz_var);
				if (!empty($my_tab['fielddefs'])) {
					$my_tab[$rec]['fields'] = zz_set_fielddefs(
						$my_tab['fielddefs'], $my_tab[$rec]['fields']
					);
				}
			}
		}
	}

	return $my_tab;
}

/**
 * gets ID of subrecord if one of the fields in the subrecord definition
 * is defined as unique
 * 
 * @param array $my_tab = $zz_tab[$tab]
 * @param array $fields = $zz_tab[$tab]['fields'] for a subtable
 * @global array $zz_conf
 * @global array $zz_error
 * @return array $my_tab['POST']
 */
function zz_subrecord_unique($my_tab, $fields) {
	global $zz_conf;
	global $zz_error;
	// check if a GET is set on the foreign key
	$foreign_key = $my_tab['foreign_key_field_name'];
	if ($pos = strrpos($foreign_key, '.')) {
		$foreign_key = substr($foreign_key, $pos + 1);
	}
	if (!empty($_GET['where'][$foreign_key])) {
		$my_tab['sql'] = zz_edit_sql($my_tab['sql'], 
			'WHERE', $foreign_key.' = '.intval($_GET['where'][$foreign_key]));
	}
	if (!empty($my_tab['unique']) AND $zz_conf['multi']) {
		// this is only important for UPDATEs of the main record
		// @todo: 'unique' on a subtable level will currently only work
		// with IDs sent via zzform_multi()
		// @todo: merge with code for 'unique' on a field level

		foreach ($my_tab['unique'] AS $unique) {
			if (empty($my_tab['existing'])) continue;
			// check if there's a foreign key and remove it from unique key
			foreach ($fields as $field) {
				if ($field['type'] !== 'foreign_key') continue;
				$key = array_search($field['field_name'], $unique);
				if ($key === false) continue;
				unset($unique[$key]);
			}
			$values = array();
			foreach ($my_tab['POST'] as $no => $record) {
				foreach ($unique as $field_name) {
					if (!isset($record[$field_name])) {
						$zz_error[] = array('msg_dev' => 'UNIQUE was set but field %s is not in POST');
						continue;
					}
					$values[$field_name] = $record[$field_name];
				}
				foreach ($my_tab['existing'] as $id => $record_in_db) {
					$found = true;
					foreach ($values as $field_name => $value) {
						if ($record_in_db[$field_name] != $value) $found = false;
					}
					if ($found) {
						$my_tab['POST'][$no][$my_tab['id_field_name']] = $id;
					}
				}
			}
		}
	}
	foreach ($fields as $f => $field) {
		if (empty($field['unique'])) continue;
		// look at fields that have to be unique, get id_field_value if
		// record with a value like this exists
		foreach ($my_tab['POST'] as $no => $record) {
			if (empty($record[$field['field_name']])) continue;
			if (!empty($record[$my_tab['id_field_name']])) continue;
			if ($field['type'] === 'select') {
				$db_table = $my_tab['db_name'].'.'.$my_tab['table'];
				$field = zz_check_select_id(
					$field, $record[$field['field_name']], $db_table
				);
				if (count($field['possible_values']) === 1) {
					$value = reset($field['possible_values']);
				} elseif (count($field['possible_values']) === 0) {
					$value = '';
				} else {
					$value = '';
					$zz_error[] = array(
						'msg_dev' => sprintf('Field marked as unique, but '
							.'could not find corresponding value: %s',
							$field['field_name']),
						'level' => E_USER_NOTICE
					);
				}
				// we are not writing this value back to POST here
				// because there's no way telling the script that this
				// value was already replaced
				// AND: we do not generate error messages here.
				// $my_tab['POST'][$no][$field['field_name']] = $value;
			} else {
				$value = $record[$field['field_name']];
			}
			$sql = zz_edit_sql(
				$my_tab['sql'], 'WHERE', $field['field_name'].' = '.$value
			);
			$existing = zz_db_fetch($sql, $my_tab['id_field_name']);
			if (count($existing) === 1) {
				$my_tab['POST'][$no][$my_tab['id_field_name']] = key($existing); 
			} elseif (count($existing)) {
				$zz_error[] = array(
					'msg_dev' => sprintf('Field marked as unique, but '
						.'value appears more than once in record: %s (SQL %s)',
						$value, $sql),
					'level' => E_USER_NOTICE
				);
			}
		}
	}
	return $my_tab['POST'];
}

/**
 * checks which existing records match value records and reorders array of
 * existing records correspondingly; sets $records as a set of all detail
 * records that have to be thought about
 *
 * @param array $values
 * @param array $existing
 * @return array (everything indexed by $records)
 *		array $records (combination of value-records and existing records)
 *		array $existing_ids (array of existing record ids)
 *		array $existing (array of existing records)
 *		array $values (remaining values which have no corresponding existing
 *			record)
 */
function zz_subrecord_values_existing($values, $existing) {
	$my_existing = $existing; // save for later use
	$order = array();
	$records = $values;
	
	// look for corresponding existing records for values
	// set correct position in array
	foreach ($my_existing as $id => $record) {
		$key = zz_values_get_equal_key($values, $record);
		if ($key !== false) {
			// save order to reorder the existing records later
			$order[$key] = $id;
			$records[$key] = $my_existing[$id];
			unset($my_existing[$id]);
		}
	}
	$next_key = count($records);

	// if there are more existing records, append them
	if (!empty($my_existing)) {
		foreach ($my_existing as $id => $fields) {
			$order[$next_key] = $id;
			$next_key++;
		}
		$records = array_merge($records, $my_existing);
	}

	$my_existing = $existing;
	unset($existing);
	// initialize array
	foreach (array_keys($records) as $index)
		$existing[$index] = array();
	// fill array with values
	foreach ($order as $index => $id) {
		$existing[$index] = $my_existing[$id];
	}
	$existing_ids = $order;
	ksort($existing_ids);

	return array($records, $existing_ids, $existing, $values);
}

/**
 * reformats 'values'-array: field names instead of field ids
 *
 * @param array $values ($zz_tab[tab]['values'])
 *		e. g. $zz_tab[tab]['values'][1][12] = 23
 * @param array $fields ($zz_tab[tab]['fields'])
 * @return array $values, reformatted
 *		e. g. $zz_tab[tab]['values'][1]['some_id'] = 23
 */
function zz_values_get_fields($values, $fields) {
	$my_values = array();
	foreach ($values as $index => $line) {
		foreach ($line as $f => $value) {
			$my_values[$index][$fields[$f]['field_name']] = $value;
		}
	}
	return $my_values;
}

/**
 * checks $zz_tab[$tab]['values'] with $record if there are equal values
 *
 * @param array $values
 * @param array $record
 * @return int $key
 */
function zz_values_get_equal_key(&$values, $record) {
	foreach ($values as $key => $line) {
		$equal = false;
		foreach ($line as $field_name => $value) {
			if (isset($record[$field_name]) 
				AND $record[$field_name] == $value)
				$equal = true;
			else {
				$equal = false;
				break;
			}
		}
		if ($equal) {
			unset($values[$key]);
			return $key;
		}
	}
	return false;
}

/**
 * sets values from 'values' to current $my_rec-Array
 *
 * @param array $my_tab
 * @param int $rec
 * @param array $zz_var
 * @return array $my_tab
 */
function zz_set_values($my_tab, $rec, $zz_var) {
	$my_values = array_shift($my_tab['values']);
	$table = $my_tab['table_name'];
	foreach ($my_tab[$rec]['fields'] AS $f => &$field) {
		if (!empty($my_values[$f])) {
			if ($field['type'] != 'hidden')
				$field['type_detail'] = $field['type'];
			$field['type'] = 'hidden';
			$field['value'] = $my_values[$f];
		}
	}
	// we have new values, so check whether these are set!
	// it's not possible to do this beforehands!
	if (!empty($my_tab['POST'][$rec])) {
		$my_tab['POST'][$rec] = zz_check_def_vals(
			$my_tab['POST'][$rec], $my_tab[$rec]['fields'], array(),
			(!empty($zz_var['where'][$table]) ? $zz_var['where'][$table] : '')
		);
	}
	return $my_tab;
}

/**
 * sets records in form, also depending on values and fielddefs
 *
 * @param array $my_tab = $zz_tab[$tab]
 * @param array $rec_tpl
 * @param array $zz_var
 * @param array $existing_ids
 * @return array $my_tab
 */
function zz_get_subrecords_mode($my_tab, $rec_tpl, $zz_var, $existing_ids) {
	global $zz_conf;
	// function will be run twice from zzform(), therefore be careful, 
	// programmer!

	for ($rec = 0; $rec < $my_tab['records']; $rec++) {
		// do not change other values if they are already there 
		// (important for error messages etc.)
		$continue_fast = (isset($my_tab[$rec]) ? true: false);
		if (!$continue_fast) // reset fields only if necessary
			$my_tab[$rec] = $rec_tpl;
		if (isset($my_tab['values'])) {	// isset because might be empty
			$my_tab = zz_set_values($my_tab, $rec, $zz_var);
			if (!empty($my_tab['fielddefs'])) {
				$my_tab[$rec]['fields'] = zz_set_fielddefs(
					$my_tab['fielddefs'], $my_tab[$rec]['fields']
				);
			}
		}
		// ok, after we got the values, continue, rest already exists.
		if ($continue_fast) continue;

		if (isset($existing_ids[$rec])) $idval = $existing_ids[$rec];
		else $idval = false;
		$my_tab[$rec]['id']['value'] = $idval;
		if (!empty($my_tab['source_values'][$rec]))
			$my_tab[$rec]['id']['source_value'] = $my_tab['source_values'][$rec];
		$my_tab[$rec]['save_record'] = isset($my_tab['zz_save_record'][$rec])
			? $my_tab['zz_save_record'][$rec] : '';

		$my_tab[$rec]['POST'] = '';
		if ($my_tab['POST']) {
			foreach ($my_tab['POST'] as $key => $my_rec) {
				if ($idval) {
					if (!isset($my_rec[$rec_tpl['id']['field_name']])) continue;
					if ($my_rec[$rec_tpl['id']['field_name']] != $idval) continue;
					$my_tab[$rec]['POST'] = $my_rec;
					unset($my_tab['POST'][$key]);
				} else {
					if (!empty($my_rec[$rec_tpl['id']['field_name']])) continue;
					if ($my_tab[$rec]['POST']) continue;
					// find first value pair that matches and put it into POST
					$my_tab[$rec]['POST'] = $my_rec;
					unset($my_tab['POST'][$key]);
				}
			}
		}
	}
	// array_keys(array_flip()) is reported to be faster than array_unique()
	// remove double entries
	$my_tab['subtable_deleted'] = array_keys(
		array_flip($my_tab['subtable_deleted'])
	);
	if (!empty($my_tab['values'])) unset($my_tab['values']);
	// we need these two arrays in correct order (0, 1, 2, ...) to display the
	// subtables correctly when requeried
	ksort($my_tab);
	unset($my_tab['zz_save_record']); // not needed anymore
	return $my_tab;
}

/** 
 * query a detail record
 * 
 * @param array $my_tab = $zz_tab[$tab] = where $tab is the detail record to query
 * @param string $zz_tab[0]['table'] = main table name
 * @param int $zz_tab[0][0]['id']['value'] = main id value	
 * @param string $id_field_name = ID field name of detail record
 * @param array $deleted_ids = IDs that were deleted by user
 * @global array $zz_conf
 * @return array $records, indexed by ID
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_query_subrecord($my_tab, $main_table, $main_id_value,
	$id_field_name, $deleted_ids = array()) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	
	if ($my_tab['sql_not_unique']) {
		if (substr(trim($my_tab['sql_not_unique']), 0, 9) == 'LEFT JOIN') {
			$sql = zz_edit_sql(
				$my_tab['sql'], 'LEFT JOIN', $my_tab['sql_not_unique']
			);
		} else {
			// quick and dirty version
			$sql = $my_tab['sql'].' '.$my_tab['sql_not_unique'];
		}
	} else {
		$sql = $my_tab['sql'];
	}
	if (!empty($my_tab['translate_field_name'])) {
		// translation subtable
		$sql = zz_edit_sql($sql, 'WHERE', 
			$zz_conf['translations_table'].'.db_name = "'.$zz_conf['db_name'].'"
			AND '.$zz_conf['translations_table'].'.table_name = "'.$main_table.'"
			AND '.$zz_conf['translations_table'].'.field_name = "'
				.$my_tab['translate_field_name'].'"');
	}
	$sql = zz_edit_sql(
		$sql, 'WHERE', $my_tab['foreign_key_field_name'].' = "'.$main_id_value.'"'
	);

	$records = zz_db_fetch($sql, $id_field_name, '', '', E_USER_WARNING);
	foreach ($records as $id => $line) {
		if (!in_array($line[$id_field_name], $deleted_ids)) continue;
		// get rid of deleted records
		unset($records[$id]);
	}
	return zz_return($records);
}

/**
 * sets some $zz-definitions for records depending on existing definition for
 * translations, subtabes, uploads, write_once-fields
 *
 * @param array $zz
 * @param array $zz_var
 * @return array 
 *		array $zz
 *		'subtables', 'save_old_record', , some minor 'fields' 
 *		changes
 *		array $zz_var
 *			'upload_form'
 */
function zz_set_fielddefs_for_record($zz, $zz_var) {
	$rec = 1;
	$zz_var['subtables'] = array();			// key: $rec, value: $no
	$zz_var['save_old_record'] = array();	// key: int, value: $no
	$zz_var['upload_form'] = false;			// false: no upload, true: upload possible

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
			// save number of subtable, get table_name and check whether sql
			// is unique, look for upload form as well
			$zz_var['subtables'][$rec] = $no;
			if (!isset($zz['fields'][$no]['table_name']))
				$zz['fields'][$no]['table_name'] = $zz['fields'][$no]['table'];
			$zz['fields'][$no]['subtable'] = $rec;
			$rec++;
			if (!empty($zz['fields'][$no]['sql_not_unique'])) {
				// must not change record where main record is not directly 
				// superior to detail record 
				// - foreign ID would be changed to main record's id
				$zz['fields'][$no]['access'] = 'show';
			}
			foreach ($zz['fields'][$no]['fields'] as $subfield) {
				if (empty($subfield['type'])) continue;
				if ($subfield['type'] != 'upload_image') continue;
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
	return array($zz, $zz_var);
}

function zz_set_fielddefs(&$fielddefs, $fields) {
	$my_field_def = array_shift($fielddefs);
	foreach ($my_field_def as $f => $field) {
		if (!$field) {
			unset($fields[$f]);
		} else {
			$fields[$f] = array_merge($fields[$f], $my_field_def[$f]);
		}
	}
	return $fields;
}

/** 
 * Sets $ops['mode'], $zz_var['action'] and several $zz_conf-variables
 * according to what the user request and what the user is allowed to request
 * 
 * @param array $zz
 * @param array $zz_conf
 *		'show_record', 'access', 'list_access' etc. pp.
 *		'modules'[debug]
 * @param array $zz_var --> will be changed as well
 *		'where_with_unique_id' bool if it's just one record to be shown (true)
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
	$zz_var['action'] = false;
	$zz_conf['show_record'] = true; // show record somehow (edit, view, ...)
	
	if (!empty($_POST['zz_action'])) {
		if (!in_array(
			$_POST['zz_action'], $zz_conf['int']['allowed_params']['action'])
		) {
			unset($_POST['zz_action']);
		}
	}
	
	// set mode and action according to $_GET and $_POST variables
	// do not care yet if actions are allowed
	if ($ops['mode'] == 'export') {
		// Export overwrites all
		$zz_conf['access'] = 'export'; 	
		$zz_conf['show_record'] = false;
	} elseif (isset($_POST['zz_subtables'])) {
		// ok, no submit button was hit but only add/remove form fields for
		// detail records in subtable, so set mode accordingly (no action!)
		if (!empty($_POST['zz_action']) AND $_POST['zz_action'] == 'insert') {
			$ops['mode'] = 'add';
		} elseif (!empty($_POST['zz_action']) AND $_POST['zz_action'] == 'update'
			AND !empty($_POST[$zz_var['id']['field_name']])) {
			$ops['mode'] = 'edit';
			$id_value = $_POST[$zz_var['id']['field_name']];
		} else {
			// this should not occur if form is used legally
			$ops['mode'] = false;
		}
	} elseif (!empty($_GET['mode'])) {
		// standard case, get mode from URL
		if (in_array($_GET['mode'], $zz_conf['int']['allowed_params']['mode'])) {
			$ops['mode'] = $_GET['mode']; // set mode from URL
			if (in_array($ops['mode'], array('edit', 'delete', 'show'))
				AND !empty($_GET['id'])) {
				$id_value = $_GET['id'];
			} elseif ($ops['mode'] == 'add' AND $zz_conf['copy']
				AND !empty($_GET['source_id'])) {
				$zz_var['id']['source_value'] = $_GET['source_id'];
			}
		} else {
			// illegal parameter, don't set a mode at all
			$ops['mode'] = false;
		}
	} elseif (!empty($_GET['zzaction']) AND !empty($_GET['id'])) {
		// last record operation was successful
		$ops['mode'] = 'show';
		$id_value = $_GET['id'];
	} elseif (!empty($_POST['zz_action'])) {
		if ($_POST['zz_action'] === 'multiple') {
			if (!empty($_POST['zz_record_id'])) {
				if (!empty($_POST['multiple_edit'])) {
					$ops['mode'] = 'edit';
				} elseif (!empty($_POST['multiple_delete'])) {
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
	} elseif ($zz_var['where_with_unique_id']) {
		// just review the record
		$ops['mode'] = 'review'; 
	} else {
		// no record is selected, basic view when starting to edit data
		// list mode only
		$ops['mode'] = 'list_only';
	}

	// write main id value, might have been written by a more trustful instance
	// beforehands ($_GET['where'] etc.)
	if (empty($zz_var['id']['value']) AND !empty($id_value))
		$zz_var['id']['value'] = $id_value;
	elseif (!isset($zz_var['id']['value']))
		$zz_var['id']['value'] = '';

	// now that we have the ID value, we can calculate the secret key
	if (!empty($zz_var['id']['values'])) {
		$idval = implode(',', $zz_var['id']['values']);
	} else {
		$idval = $zz_var['id']['value'];
	}
	$zz_conf['int']['secret_key'] = sha1($zz_conf['int']['hash'].$idval);

	// if conditions in $zz_conf['if'] -- check them
	// get conditions if there are any, for access
	$zz_conf['list_access'] = array(); // for old variables

	if (!empty($zz_conf['modules']['conditions'])
		AND !empty($zz_conf['if']) AND $zz_var['id']['value']) {
		$zz_conditions = zz_conditions_record_check($zz, $ops['mode'], $zz_var);
		// save old variables for list view
		$saved_variables = array(
			'access', 'add', 'edit', 'delete', 'view', 'details'
		);
		foreach ($saved_variables as $var) {
			if (!isset($zz_conf[$var])) continue;
			$zz_conf['list_access'][$var] = $zz_conf[$var];
		}
		// overwrite new variables
		$zz_conf = zz_conditions_merge(
			$zz_conf, $zz_conditions['bool'], $zz_var['id']['value'], false, 'conf'
		);
	}


	// set (and overwrite if necessary) access variables, i. e.
	// $zz_conf['add'], $zz_conf['edit'], $zz_conf['delete']

	if ($zz_conf['access'] === 'add_only' AND zz_valid_request('insert')) {
		$zz_conf['access'] = 'show_after_add';
	}
	if ($zz_conf['access'] === 'edit_only' AND zz_valid_request(
		array('update', 'noupdate'))
	) {
		$zz_conf['access'] = 'show_after_edit';
	}
	if ($zz_conf['access'] === 'add_then_edit') {
		if ($zz_var['id']['value'] AND zz_valid_request()) {
			$zz_conf['access'] = 'show+edit';
		} elseif ($zz_var['id']['value']) {
			$zz_conf['access'] = 'edit_only';
		} else {
			$zz_conf['access'] = 'add_only';
		}
	}

	// @todo think about multiple_edit
	switch ($zz_conf['access']) { // access overwrites individual settings
	// first the record specific or overall settings
	case 'export':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['show_list'] = true;		// list
		$zz_conf['show_record'] = false;	// don't show record
		$zz_conf['backlink'] = false; 		// don't show back to overview link
		break;
	case 'show_after_add';
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['search'] = false;			// no search form
		$zz_conf['show_list'] = false;		// no list
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
		$zz_conf['show_list'] = false;		// no list
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
		$zz_conf['show_list'] = false;		// no list
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
		$zz_conf['show_list'] = false;		// no list
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
		$zz_conf['show_list'] = false;		// no list
		$zz_conf['no_ok'] = true;			// no OK button
		$zz_conf['cancel_link'] = false; 	// no cancel link
		$zz_conf['int']['hash_id'] = true;	// user cannot view all IDs
		if (empty($_POST)) $ops['mode'] = 'edit';
		break;
	default:
		// now the settings which apply to both record and list
		$zz_conf = zz_listandrecord_access($zz_conf);
		break;
	}

	if ($zz_var['where_with_unique_id']) { // just for record, not for list
		// in case of where and not unique, ie. only one record in table, 
		// don't do this.
		$zz_conf['show_list'] = false;		// don't show table
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
		if (!$zz_conf[$mode] AND $ops['mode'] == $mode) {
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
		if (!$zz_conf[$mode] AND $zz_var['action'] == $action) {
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

	if ($zz_conf['access'] == 'edit_details_only') $zz['access'] = 'show';
	if ($zz_conf['access'] == 'edit_details_and_add' 
		AND $ops['mode'] != 'add' AND $zz_var['action'] != 'insert')
		$zz['access'] = 'show';

	// now, mode is set, do something depending on mode
	
	if (in_array($ops['mode'], array('edit', 'delete', 'add')) 
		AND !$zz_conf['show_list_while_edit']) $zz_conf['show_list'] = false;
	if (!$zz_conf['generate_output']) $zz_conf['show_list'] = false;

	if ($ops['mode'] == 'list_only' AND empty($_GET['zzaction'])) {
		$zz_conf['show_record'] = false;	// don't show record
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
	switch ($zz_conf['access']) {
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
		$zz_conf['show_record'] = false;	// don't show record
		break;
	case 'search_but_no_list':
		$zz_conf['add'] = false;			// don't add record (form+links)
		$zz_conf['edit'] = false;			// don't edit record (form+links)
		$zz_conf['delete'] = false;			// don't delete record (form+links)
		$zz_conf['view'] = false;			// don't show record (links)
		$zz_conf['show_record'] = false;	// don't show record
		$zz_conf['show_list'] = true;		// show list, further steps in zz_list()
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
 * query record 
 * 
 * if everything was successful, query record (except in case it was deleted)
 * if not, write POST values back into form
 *
 * @param array $my_tab complete zz_tab[$tab] array
 * @param int $rec Number of detail record
 * @param bool $validation true/false
 * @param string $mode ($ops['mode'])
 * @return array $zz_tab[$tab]
 *		might unset $zz_tab[$tab][$rec]
 *		$zz_tab[$tab][$rec]['record'], $zz_tab[$tab][$rec]['record_saved'], 
 *		$zz_tab[$tab][$rec]['fields'], $zz_tab[$tab][$rec]['action']
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_query_record($my_tab, $rec, $validation, $mode) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$my_rec = &$my_tab[$rec];
	$table = $my_tab['table'];
	// detail records don't have 'extra'
	if (!isset($my_tab['sqlextra'])) $my_tab['sqlextra'] = array();

	// in case, record was deleted, query record is not necessary
	if ($my_rec['action'] == 'delete') {
		unset($my_rec);
		return zz_return($my_tab);
	}
	// in case validation was passed or access is 'show'
	// everything's okay.
	if ($validation OR $my_rec['access'] == 'show') {
		// initialize 'record'
		$my_rec['record'] = false;
		// check whether record already exists (this is of course impossible 
		// for adding a record!)
		if ($mode != 'add' OR $my_rec['action']) {
			if ($my_rec['id']['value']) {
				$my_rec['record'] = zz_query_single_record(
					$my_tab['sql'], $table, $my_rec['id'], $my_tab['sqlextra']
				);
			} elseif (!empty($my_rec['id']['values'])) {
				$my_rec['record'] = zz_query_multiple_records(
					$my_tab['sql'], $table, $my_rec['id']
				);
				// @todo: think about sqlextra
			}
		} elseif ($mode == 'add' AND !empty($my_rec['id']['source_value'])) {
			if (!empty($my_rec['POST'])) {
				// no need to requery, we already did query a fresh record
				// as a template
				$my_rec['record'] = $my_rec['POST'];
			} else {
				$my_rec['record'] = zz_query_single_record(
					$my_tab['sql'], $table, $my_rec['id'], $my_tab['sqlextra'], 'source_value'
				);
				$my_rec['record'][$my_rec['id']['field_name']] = false;
			}
			// remove some values which cannot be copied
			foreach ($my_rec['fields'] as $my_field) {
				if (empty($my_field['type'])) continue;
				// identifier must be created from scratch
				if ($my_field['type'] == 'identifier')
					$my_rec['record'][$my_field['field_name']] = false;
			}
		}
	// record has to be passed back to user
	} else {
		if (isset($my_rec['POST-notvalid'])) {
			$my_rec['record'] = $my_rec['POST-notvalid'];
		} elseif (isset($my_rec['POST'])) {
			$my_rec['record'] = $my_rec['POST'];
		} else {
			$my_rec['record'] = array();
		}
		
	//	get record for display fields and maybe others
		$my_rec['record_saved'] = zz_query_single_record(
			$my_tab['sql'], $table, $my_rec['id'], $my_tab['sqlextra']
		);

	//	display form again			
		$my_rec['action'] = 'review';

	//	print out all records which were wrong, set class to error
		foreach ($my_rec['fields'] as $no => $field) {
			// just look for check_validation set but false
			if (!isset($field['check_validation']) 
				OR $field['check_validation']) continue;
			// append error to 'class'
			if (isset($my_rec['fields'][$no]['class'])) {
				$my_rec['fields'][$no]['class'].= ' error';
			} else {
				$my_rec['fields'][$no]['class'] = 'error';
			}
		}
	}
	zz_log_validation_errors($my_rec, $validation);
	return zz_return($my_tab);
}

/**
 * Query single record
 *
 * @param string $sql $zz['sql']
 * @param string $table $zz['table']
 * @param array $id	$zz_var['id']
 * @param array $sqlextra $zz['sqlextra']
 * @param string $type
 * @return array
 */
function zz_query_single_record($sql, $table, $id, $sqlextra, $type = 'value') {		
	global $zz_error;
	
	$sql = zz_edit_sql($sql, 'WHERE', $table.'.'
		.$id['field_name']." = '".$id[$type]."'");
	$record = zz_db_fetch($sql, '', '', 'record exists? ('.$type.')');
	foreach ($sqlextra as $sql) {
		if (empty($id[$type])) {
			$zz_error[]['msg_dev'] = sprintf('No ID %s found (Query: %s).', $type, $sql);
			continue;
		}
		$sql = sprintf($sql, $id[$type]);
		$record = array_merge($record, zz_db_fetch($sql));
	}
	return $record;
}

/**
 * Query multiple records, return identical values in all records
 *
 * @param string $sql
 * @return array
 */
function zz_query_multiple_records($sql, $table, $id) {
	$sql = zz_edit_sql($sql, 'WHERE', $table.'.'
		.$id['field_name']." IN ('".implode("','", $id['values'])."')");
	$records = wrap_db_fetch($sql, $id['field_name'], '', 'multiple records exist?');
	// use first record as basis for checking identical values
	$existing = array_shift($records);
	foreach ($records as $record) {
		foreach ($record as $field_name => $field_value) {
			if ($existing[$field_name] !== $field_value) {
				$existing[$field_name] = '';
			}
		}
	}
	return $existing;
}

/**
 * Log validation errors (incorrect or missing values)
 *
 * @param array $my_rec = $zz_tab[$tab][$rec]
 * @param bool $validation
 * @global array $zz_error
 * @return bool true: some errors were logged; false no errors were logged
 */
function zz_log_validation_errors($my_rec, $validation) {
	global $zz_error;
	if ($my_rec['action'] == 'delete') return false;
	if ($validation) return false;
	if ($my_rec['access'] == 'show') return false;
	
	foreach ($my_rec['fields'] as $no => $field) {
		if ($field['type'] == 'password_change') continue;
		if ($field['type'] == 'subtable') continue;
		if (!empty($field['mark_reselect'])) {
			// oh, it's a reselect, add some validation message
			$zz_error['validation']['reselect'][] = sprintf(
				zz_text('Please select one of the values for field %s'),
				'<strong>'.$field['title'].'</strong>'
			);
			continue;
		}
		// just look for check_validation set but false
		if (!isset($field['check_validation'])) continue;
		if ($field['check_validation']) continue;
		if ($my_rec['record'][$field['field_name']]) {
			// there's a value, so this is an incorrect value
			$zz_error['validation']['msg'][] = sprintf(
				zz_text('Value incorrect in field %s'), 
				'<strong>'.$field['title'].'</strong>'
			).(
				!empty($field['validation_error'])
				? ' ('.$field['validation_error'].')'
				: ''
			);
			$zz_error['validation']['incorrect_values'][] = array(
				'field_name' => $field['field_name'],
				'msg' => zz_text('incorrect value').': '
					.$my_rec['record'][$field['field_name']]
			);
			$zz_error['validation']['log_post_data'] = true;
		} elseif (empty($field['dont_show_missing'])) {
			if ($field['type'] === 'upload_image') {
				$zz_error['validation']['msg'][] = sprintf(
					zz_text('Nothing was uploaded in field %s'),
					'<strong>'.$field['title'].'</strong>'
				);
			} else {
				// there's a value missing
				$zz_error['validation']['msg'][] = sprintf(
					zz_text('Value missing in field %s'),
					'<strong>'.$field['title'].'</strong>'
				);
				$zz_error['validation']['log_post_data'] = true;
			}
		}
	}
	return true;
}

/** 
 * Creates link or HTML img from path
 * 
 * @param array $path
 *		'root', 'webroot', 'field1...fieldn', 'string1...stringn', 'mode1...n',
 *		'ignore_record' will cause record to be ignored
 * @param array $record current record
 * @param string $type (optional) link or image, image will be returned in
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

	if ($type == 'image') {
		$alt = zz_text('no_image');
	}
	if (!is_array($path)) $path = array('string' => $path);
	foreach ($path as $part => $value) {
		if (!$value) continue;
		if (substr($part, 0, 4) == 'root') {
			$check_against_root = true;
			// root has to be first element, everything before will be ignored
			$path_full = $value;
			if (substr($path_full, -1) != '/')
				$path_full .= '/';
		} elseif (substr($part, 0, 7) == 'webroot') {
			// web might come later, ignore parts before for web and add them
			// to full path
			$path_web = $value;
			$path_full .= $url;
			$url = '';
		} elseif (substr($part, 0, 5) == 'field') {
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
			$url .= $content;
			if ($type == 'image') {
				$alt = zz_text('File: ').$record[$value];
			}
		} elseif (substr($part, 0, 6) == 'string') {
			$url .= $value;
		} elseif (substr($part, 0, 4) == 'mode') {
			$modes[] = $value;
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
		if ($type == 'image') {
			// filesize is 0 = looks like error
			if (!$size = filesize($path_full.$url)) return false;
			// getimagesize tests whether it's a web image
			if (!getimagesize($path_full.$url)) {
				// if not, return EXT (4.4 MB)
				return $ext.' ('.zz_byte_format($size).')';
			}
		}
	}
	$url = $path_web.$url;
	if ($type != 'image') return $url;
	if (!$url) return false;
	$img = '<img src="'.$url.'" alt="'.$alt.'" class="thumb">';
	return $img;
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
 * @param array $zz_tab
 * @param string $record (optional) default 'new', other: 'old' (use updated
 *		record or old record)
 * @param bool $do (optional)
 * @param int $tab (optional)
 * @param int $rec (optional)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_makepath($path, $zz_tab, $record = 'new', $do = false, $tab = 0, $rec = 0) {
	// set variables
	$p = false;
	$modes = false;
	$root = false;		// root
	$rootp = false;		// path just for root
	$webroot = false;	// web root

	// put path together
	foreach ($path as $pkey => $pvalue) {
		if (!$pvalue) continue;
		if ($pkey == 'root') {
			$root = $pvalue;
		} elseif ($pkey == 'webroot') {
			$webroot = $pvalue;
			$rootp = $p;
			$p = '';
		} elseif (substr($pkey, 0, 4) == 'mode') {
			$modes[] = $pvalue;
		} elseif (substr($pkey, 0, 6) == 'string') {
			$p .= $pvalue;
		} elseif (substr($pkey, 0, 5) == 'field') {
			$my_tab = $zz_tab[$tab];
			if ($record == 'new') {
				$content = (!empty($my_tab[$rec]['POST'][$pvalue])) 
					? $my_tab[$rec]['POST'][$pvalue]
					: zz_get_record($pvalue, $my_tab['sql'], 
						$my_tab[$rec]['id']['value'], 
						$my_tab['table'].'.'.$my_tab[$rec]['id']['field_name']);
			} elseif ($record == 'old') {
				$content = (!empty($my_tab['existing'][$rec]) 
					? $my_tab['existing'][$rec][$pvalue] : '');
			}
			if ($modes) {
				$content = zz_make_mode($modes, $content);
				if (!$content) return false;
			}
			$p .= $content;
			$alt = zz_text('File: ').$content;
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
		$sql = zz_edit_sql($sql, 'WHERE', $idfield.' = "'.$idvalue.'"');
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function make_id_fieldname($fieldname, $prefix = 'field') {
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function magic_quotes_strip($mixed) {
   if(is_array($mixed))
       return array_map('magic_quotes_strip', $mixed);
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
 * Protection against overwritten values, set values and defaults for 
 * zzform_multi()
 * Writes values, default values and where-values into POST-Array
 * initializes unset field names
 * 
 * @param array $post		POST records of main table or subtable
 * @param array $fields		$zz ...['fields']-definitions of main or subtable
 * @param array $existing values of existing record in case record is not set
 * @param array $where
 * @return array $post		POST
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_check_def_vals($post, $fields, $existing = array(), $where = false) {
	foreach ($fields as $field) {
		if (empty($field['field_name'])) continue;
		$field_name = $field['field_name'];
		// for all values, overwrite posted values with needed values
		if (!empty($field['value'])) 
			$post[$field_name] = $field['value'];
		// just for values which are not set (!) set existing value (on update)
		// if there is one
		// (not for empty strings!)
		if (!empty($existing[$field_name]) AND !isset($post[$field_name]))
			$post[$field_name] = $existing[$field_name];
		// just for values which are not set (!) set default value
		// (not for empty strings!, not for update)
		if (!empty($field['default']) AND !isset($post[$field_name]))
			$post[$field_name] = $field['default'];
		// most important, therefore last: [where]
		if (!empty($where[$field_name]))
			$post[$field_name] = $where[$field_name];
		// if it's a mass upload or someone cuts out field_names, 
		// treat these fields as if nothing was posted
		// some fields must not be initialized, so ignore them
		$unwanted_field_types = array(
			'id', 'foreign_key', 'translation_key', 'display'
		);
		if (!isset($post[$field_name])
			AND !in_array($field['type'], $unwanted_field_types))
			$post[$field_name] = '';
	}
	return $post;
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
 * 		$zz_conf['error_mail_to'],  $zz_conf['error_mail_from'] - mail addresses
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
		$zz_error['error'] = ($return == 'exit') ? true : false;
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
		if ($error['status'] != 200) {
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
		} elseif ($level == 'error' OR $level == 'warning') {
			$user[$key] = '<strong>'.zz_text('Warning!').'</strong> '.$user[$key];
		}
		if ($admin[$key] AND ($level == 'error' OR $level == 'warning')) {
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
	
	$zz_error['error'] = ($return == 'exit') ? true : false;
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
		."\n".'<ul><li>'.implode(".</li>\n<li>", $zz_error['validation']['msg'])
		.'.</li></ul>';
	// if we got wrong values entered, put this into a developer message
	if (!empty($zz_error['validation']['incorrect_values'])) {
		foreach ($zz_error['validation']['incorrect_values'] as $incorrect_value) {
			$this_dev_msg[] = zz_text('Field name').': '.$incorrect_value['field_name']
				.' / '.htmlspecialchars($incorrect_value['msg']);
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
	if (substr($dir, -1) == '/')	//	removes / from the end
		$dir = substr($dir, 0, -1);
	if (file_exists($dir)) return true;

	// if dir does not exist, do a recursive check/makedir on parent directories
	$upper_dir = substr($dir, 0, strrpos($dir, '/'));
	$success = zz_create_topfolders($upper_dir);
	if ($success) {
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
 * I - Internationalisation functions
 * --------------------------------------------------------------------
 */

/** 
 * Translate text if possible or write back text string to be translated
 * 
 * @param string $string		Text string to be translated
 * @global array $zz_conf
 * @return string $string		Translation of text
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_text($string) {
	static $text;				// $text will only be available to this function
	global $zz_conf;
	if (empty($zz_conf['generate_output'])) return $string;

	$language = isset($zz_conf['language']) ? $zz_conf['language'] : 'en';
	if (isset($zz_conf['default_language_for'][$language]))
		$language = $zz_conf['default_language_for'][$language];

	if (empty($zz_conf['int']['text_included'])) {
		if (!isset($zz_conf['lang_dir'])) {
			$zz_conf['lang_dir'] = $zz_conf['dir_custom'];
		}
		// base: include english text
		require $zz_conf['dir_inc'].'/text-en.inc.php';
		if (!empty($zz_conf['additional_text']) 
			AND file_exists($langfile = $zz_conf['lang_dir'].'/text-en.inc.php')) {
			// translated text must not be include_once since $text is cleared
			// beforehands
			include $langfile;
		}

		// text in other languages
		if ($language != 'en') {
			$langfile = $zz_conf['dir_inc'].'/text-'.$language.'.inc.php';
			if (file_exists($langfile)) {
				include $langfile;
			} else {
				// no zz_text() here, or script will recurse indefinitely!
				$zz_error[] = array(
					'msg_dev' => sprintf(
						'No language file for "%s" found. Using English instead.', 
						'<strong>'.$language.'</strong>'
					),
					'level' => E_USER_NOTICE
				);
			}
			if (!empty($zz_conf['additional_text']) AND file_exists(
				$langfile = $zz_conf['lang_dir'].'/text-'.$language.'.inc.php'
			)) {
				// must not be include_once since $text is cleared beforehands
				include $langfile;
			}
		}
		// todo: if file exists else lang = en
		$zz_conf['int']['text_included'] = true;
	}
	if (!empty($zz_conf['text'][$language])) {
		$text = array_merge($text, $zz_conf['text'][$language]);
	}

	if (!isset($text[$string])) {
		if (function_exists('wrap_text'))
			return wrap_text($string);
		// write missing translation to somewhere.
		// TODO: check logfile for duplicates
		// TODO: optional log directly in database
		if (!empty($zz_conf['log_missing_text'])) {
			$log_message = '$text["'.addslashes($string).'"] = "'.$string.'";'."\n";
			$log_file = sprintf($zz_conf['log_missing_text'], $language);
			error_log($log_message, 3, $log_file);
			chmod($log_file, 0664);
		}
		return $string;
	}
	return $text[$string];
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
 * formats timestamp to readable date
 * 
 * @param string $timestamp
 * @return string reformatted date
 * @todo use date functions instead
 */
function timestamp2date($timestamp) {
	if (!$timestamp) return false;
	if (strstr($timestamp, '-')) {
		// SQL DATETIME format, YYYY-MM-DD HH:ii:ss
		$date = substr($timestamp, 8, 2).'.'.substr($timestamp, 5, 2).'.'.substr($timestamp, 0, 4).' ';
		$date.= substr($timestamp, 11, 2).':'.substr($timestamp, 14, 2).':'.substr($timestamp, 17, 2);
	} else {
		// YYYYMMDDHHiiss format
		$date = substr($timestamp, 6, 2).'.'.substr($timestamp, 4, 2).'.'.substr($timestamp, 0, 4).' ';
		$date.= substr($timestamp, 8, 2).':'.substr($timestamp, 10, 2).':'.substr($timestamp, 12, 2);
	}
	return $date;
}

function htmlchars($string) {
	$string = str_replace('&amp;', '&', htmlspecialchars($string));
	//$string = str_replace('&quot;', '"', $string); // does not work 
	return $string;
}

/**
 * checks string length, cuts string if too long
 *
 * @param string $string
 * @param int $max_length maximum length of string that is allowed
 * @return string
 */
function zz_cut_length($string, $max_length) {
	if (mb_strlen($string) <= $max_length) return $string;
	// cut long values
	$string = mb_substr($string, 0, $max_length).'...';
	return $string;
}

/**
 * formats an integer into a readable byte representation
 *
 * @param int $byts
 * @param int $precision
 * @return string
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
 * Creates identifier field that is unique
 * 
 * @param array $vars pairs of field_name => value
 * @param array $conf	Configuration for how to handle the strings
 *		'forceFilename' ('-'); value which will be used for replacing spaces and 
 *			unknown letters
 *		'concat' ('.'); string used for concatenation of variables. might be 
 *			array, values are used in the same order they appear in the array
 *		'exists' ('.'); string used for concatenation if identifier exists
 *		'lowercase' (true); false will not transform all letters to lowercase
 *		'slashes' (false); true = slashes will be preserved
 *		'where' (false) WHERE-condition to be appended to query that checks 
 *			existence of identifier in database 
 *		'hash_md5' (false); true = hash will be created from field values and
 *			timestamp
 *		array 'replace' (false); key => value; characters in key will be
 *			replaced by value
 * @param array $my_rec		$zz_tab[$tab][$rec]
 * @param string $db_table	Name of Table [dbname.table]
 * @param int $field		Number of field definition
 * @return string identifier
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_identifier($vars, $conf, $my_rec = false, $db_table = false, $field = false) {
	if (empty($vars)) return false;
	if ($my_rec AND $field AND $db_table) {
		// there's a record, check if identifier is in write_once mode
		$field_name = $my_rec['fields'][$field]['field_name'];
		if (in_array($field_name, array_keys($vars))) {
			$keep_idf = false;
			if (!empty($conf['exists_function'])) {
				$keep_idf = $conf['exists_function']($vars[$field_name], $vars);
			} elseif ($vars[$field_name]) {
				$keep_idf = true;
			}
			if ($keep_idf) {
				// do not change anything if there has been a value set once and 
				// identifier is in vars array
				return $vars[$field_name];
			} else {
				unset ($vars[$field_name]);
			}
		}
	}
	
	// set defaults, correct types
	$default_configuration = array(
		'forceFilename' => '-', 'concat' => '.', 'exists' => '.',
		'lowercase' => true, 'slashes' => false, 'replace' => array(),
		'hash_md5' => false, 'ignore' => array(), 'max_length' => 36,
		'ignore_this_if' => array(), 'empty' => array()
	);
	foreach ($default_configuration as $key => $value) {
		if (!isset($conf[$key])) $conf[$key] = $value;
	}
	$conf_max_length_1 = array('forceFilename', 'exists');
	foreach ($conf_max_length_1 as $key) {
		$conf[$key] = substr($conf[$key], 0, 1);
	}
	$conf_arrays = array('ignore');
	foreach ($conf_arrays as $key) {
		if (!is_array($conf[$key])) $conf[$key] = array($conf[$key]);
	}
	$conf_arrays_in_arrays = array('ignore_this_if');
	foreach ($conf_arrays_in_arrays as $key) {
		foreach ($conf[$key] as $subkey => $value) {
			if (!is_array($value)) $conf[$key][$subkey] = array($value);
		}
	}

	$i = 0;
	$idf_arr = array();
	foreach ($vars as $key => $var) {
		$i++;
		if (in_array($key, $conf['ignore'])) continue;
		if (!empty($conf['ignore_this_if'][$key])) {
			foreach ($conf['ignore_this_if'][$key] as $my_field_name) {
				if (!empty($vars[$my_field_name])) continue 2;
			}
		}
		if (!$var) {
			if (!empty($conf['empty'][$key])) {
				$var = $conf['empty'][$key];
			} else {
				if (is_array($conf['concat'])) {
					$idf_arr[] = ''; // in case concat is an array
				}
				continue;
			}
		}
		// check for last element, if max_length is met
		if ($conf['max_length'] AND strlen($var) > $conf['max_length'] 
			AND $i === count($vars)) {
			$vparts = explode(' ', $var);
			if (count($vparts) > 1) {
				// always use first part, even if it's too long
				$var = array_shift($vparts);
				// < and not <= because space is always added
				while (strlen($var.reset($vparts)) < $conf['max_length']) {
					$var .= ' '.array_shift($vparts);
				}
				// cut off if first word is too long
				$var = substr($var, 0, $conf['max_length']);
			} else {
				// there are no words, cut off in the middle of the word
				$var = substr($var, 0, $conf['max_length']);
			}
		}
		if ((strstr($var, '/') AND $i != count($vars))
			OR $conf['slashes']) {
			// last var will be treated normally, other vars may inherit 
			// slashes from dir names
			$dir_vars = explode('/', $var);
			foreach ($dir_vars as $d_var) {
				if (!$d_var) continue;
				$my_var = forceFilename(
					$d_var, $conf['forceFilename'], $conf['replace']
				);
				if ($conf['lowercase']) $my_var = strtolower($my_var);
				$idf_arr[] = $my_var;
			}
		} else {
			$my_var = forceFilename(
				$var, $conf['forceFilename'], $conf['replace']
			);
			if ($conf['lowercase']) $my_var = strtolower($my_var);
			$idf_arr[] = $my_var;
		}
	}
	if (empty($idf_arr)) return false;

	$idf = zz_identifier_concat($idf_arr, $conf['concat']);
	if (!empty($conf['prefix'])) $idf = $conf['prefix'].$idf;
	// start value, if idf already exists
	$i = !empty($conf['start']) ? $conf['start'] : 2;
	// start always?
	if (!empty($conf['start_always'])) $idf .= $conf['exists'].$i;
	else $conf['start_always'] = false;
	// hash md5?
	if (!empty($conf['hash_md5'])) {
		$idf = md5($idf.date('Ymdhis'));
	}
	// ready, last checks
	if ($my_rec AND $field AND $db_table) {
		// check length
		if ($my_rec AND !empty($my_rec['fields'][$field]['maxlength']) 
			AND ($my_rec['fields'][$field]['maxlength'] < strlen($idf))) {
			$idf = substr($idf, 0, $my_rec['fields'][$field]['maxlength']);
		}
		// check whether identifier exists
		$idf = zz_identifier_exists(
			$idf, $i, $db_table, $field_name, $my_rec['id']['field_name'],
			$my_rec['POST'][$my_rec['id']['field_name']], $conf,
			$my_rec['fields'][$field]['maxlength']
		);
	}
	return $idf;
}

/**
 * concatenates values for identifiers
 *
 * @param array $data values to concatencate
 * @param mixed $concat (string or array)
 * @return string
 */
function zz_identifier_concat($data, $concat) {
	if (!is_array($concat)) return implode($concat, $data);
	
	// idf 0 con 0 idf 1 con 1 idf 2 con 1 ...
	$idf = '';
	if (isset($concat['last'])) {
		$last_concat = $concat['last'];
		unset($concat['last']);
	}
	foreach ($data as $key => $value) {
		if (!$value) continue;
		if ($idf) {
			if ($key > 1 AND $key == count($data) - 1 AND isset($last_concat)) {
				// last one, but not first one
				$idf .= $last_concat;
			} else {
				// normal order, take actual last one if no other is left
				// add concat separator 0, 1, ...
				// might be '', therefore we use isset
				if (isset($concat[$key-1])) {
					$idf .= $concat[$key-1];
				} else {
					$idf .= $concat[count($concat)-1];
				}
			}
		}
		$idf .= $value;
	}
	return $idf;
}

/**
 * check if an identifier already exists in database, add nuermical suffix
 * until an adequate identifier exists  (john-doe, john-doe-2, john-doe-3 ...)
 *
 * @param string $idf
 * @param mixed $i (integer or letter)
 * @param string $db_table [dbname.table]
 * @param string $field
 * @param string $id_field
 * @param string $id_value
 * @param array $conf
 * @param int $maxlen
 * @global array $zz_conf
 * @return string $idf
 */
function zz_identifier_exists($idf, $i, $db_table, $field, $id_field, $id_value,
	$conf, $maxlen = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$sql = 'SELECT '.$field.' FROM '.zz_db_table_backticks($db_table).'
		WHERE '.$field.' = "'.$idf.'"
		AND '.$id_field.' != '.$id_value
		.(!empty($conf['where']) ? ' AND '.$conf['where'] : '');
	$records = zz_db_fetch($sql, $field, 'single value');
	if ($records) {
		$start = false;
		if (is_numeric($i) AND $i > 2) $start = true;
		elseif (!is_numeric($i) AND $i > 'b') $start = true;
		elseif ($conf['start_always']) $start = true;
		if ($start) {
			// with start_always, we can be sure, that a generated suffix exists
			// so we can safely remove it. 
			// for other cases, this is only true for $i > 2.
			if ($conf['exists']) {
				$idf = substr($idf, 0, strrpos($idf, $conf['exists']));
			} else {
				// remove last ending, might be 9 in case $i = 10 or
				// 'z' in case $i = 'aa' so make sure not to remove too much
				$j = $i;
				$j--;
				if ($j === $i) {
					// -- does not work with alphabet
					if (substr_count($j, 'a') == strlen($j)) {
						$j = substr($j, 0, -1);
					}
				}
				$idf = substr($idf, 0, -strlen($j));
			}
		}
		$suffix = $conf['exists'].$i;
		// in case there is a value for maxlen, make sure that resulting
		// string won't be longer
		if ($maxlen && strlen($idf.$suffix) > $maxlen) 
			$idf = substr($idf, 0, ($maxlen - strlen($suffix))); 
		$idf = $idf.$suffix;
		$i++;
		$idf = zz_identifier_exists(
			$idf, $i, $db_table, $field, $id_field, $id_value, $conf, $maxlen
		);
	}
	return zz_return($idf);
}

/**
 * extracts substring information from field_name
 *
 * @param string $field_name (e. g. test{0,4})
 * @return array
 *		string $field_name (e. g. test)
 *		string $substr (e. g. 0,4)
 */
function zz_identifier_substr($field_name) {
	if (!strstr($field_name, '}')) return array($field_name, '');
	if (!strstr($field_name, '{')) return array($field_name, '');
	preg_match('/{(.+)}$/', $field_name, $substr);
	if (!$substr) return array($field_name, '');
	$field_name = preg_replace('/{.+}$/', '', $field_name);
	$substr = $substr[1];
	return array($field_name, $substr);
}

/**
 * gets all variables for identifier field to use them in zz_identifier()
 *
 * @param array $my_rec = $zz_tab[$tab][$rec]
 * 		$my_rec['fields'][$f]['fields']:
 * 		possible syntax: fieldname[sql_fieldname] or tablename.fieldname or 
 *		fieldname; index not numeric but string: name of function to call
 * @param int $f = $zz['fields'][n]
 * @param array $main_post POST values of $zz_tab[0][0]['POST']
 * @return array $values
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo Funktion ist nicht ganz korrekt, da sie auf unvaldierte 
 * 		Detaildatensätze zugreift. Problem: Hauptdatens. wird vor Detaildatens.
 * 		geprüft (andersherum geht wohl auch nicht)
 */ 
function zz_identifier_vars($my_rec, $f, $main_post) {
	$values = array();
	foreach ($my_rec['fields'][$f]['fields'] as $field_name) {
 		// get full field_name with {}, [] and . as index
 		$index = $field_name;

		// check for substring parameter
		list($field_name, $substr) = zz_identifier_substr($field_name);
		// get value
		$values[$index] = zz_identifier_var($field_name, $my_rec, $main_post);

		if (!$substr) continue;
		eval ($line ='$values[$index] = substr($values[$index], '.$substr.');');
	}
	foreach ($my_rec['fields'][$f]['fields'] as $function => $field_name) {
		if (is_numeric($function)) continue;
		if (function_exists($function))
			$values[$field_name] = $function($values[$field_name], $values);
	}
	return $values;
}

/**
 * gets a single variable for an identifier field
 *
 * @param string $field_name
 * @param array $my_rec $zz_tab[$tab][$rec]
 * @param array $main_post POST values of $zz_tab[0][0]['POST']
 * @return string $value
 */
function zz_identifier_var($field_name, $my_rec, $main_post) {
	// 1. it's just a field name of the main record
	if (!empty($my_rec['POST'][$field_name]))
		return $my_rec['POST'][$field_name];

	// 2. it's a field name of a detail record
	$field_names = false;
	if (strstr($field_name, '.')) {
		list($table, $field_name) = explode('.', $field_name);
		if (!isset($my_rec['POST'][$table])) return false;

		if (!empty($my_rec['POST'][$table][0][$field_name])) {
			// this might not be correct, because it ignores the table_name
			$value = $my_rec['POST'][$table][0][$field_name]; 

			// todo: problem: subrecords are being validated after main record, 
			// so we might get invalid results
			$field = zz_get_subtable_fielddef($my_rec['fields'], $table);
			if ($field) {
				$type = zz_get_fielddef($field['fields'], $field_name, 'type');
				if ($type == 'date') {
					$value = zz_check_date($value); 
					$value = str_replace('-00', '', $value); 
					$value = str_replace('-00', '', $value); 
				}
			}
			return $value;
		}
		$field_names = zz_split_fieldname($field_name);
		if (!$field_names) return false;
		if (empty($my_rec['POST'][$table][0][$field_names[0]])) return false;
		
		$field = zz_get_subtable_fielddef($my_rec['fields'], $table);
		if (!$field) return false;
		
		$id = $my_rec['POST'][$table][0][$field_names[0]];
		$sql = zz_get_fielddef($field['fields'], $field_names[0], 'sql');
		return zz_identifier_vars_db($sql, $id, $field_names[1]);
	}
	
	// 3. it's a field name of a main or a detail record
	if (!$field_names)
		$field_names = zz_split_fieldname($field_name);
	if (!$field_names) return false;
	
	if ($field_names[0] == '0') {
		if (empty($main_post[$field_names[1]])) return false;
		if (is_array($main_post[$field_names[1]])) return false;
		$value = $main_post[$field_names[1]];
		// remove " "
		if (substr($value, 0, 1)  == '"' AND substr($value, -1) == '"')
			$value = substr($value, 1, -1);
		return $value;
	} 
	if (empty($my_rec['POST'][$field_names[0]])) return false;

	$id = $my_rec['POST'][$field_names[0]];
	$sql = zz_get_fielddef($my_rec['fields'], $field_names[0], 'sql');
	return zz_identifier_vars_db($sql, $id, $field_names[1]);
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
	if (substr($field_name, -1) != ']') return false;
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
		if ($field['field_name'] != $field_name) continue;
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
		if (!empty($field['table']) AND $field['table'] == $table)
			return $field;
		if (!empty($field['table_name']) AND $field['table_name'] == $table)
			return $field;
	}
	return false;
}

/** 
 * Gets values for identifier from database
 * 
 * @param string $sql SQL query
 * @param int $id record ID
 * @param string $fieldname (optional) if set, returns just fieldname
 * @return mixed array: full line from database, string: just field if fieldname
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_identifier_vars_db($sql, $id, $fieldname = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	// remove whitespace
	$sql = preg_replace("/\s+/", " ", $sql); // first blank needed for SELECT
	$sql_tokens = explode(' ', trim($sql)); // remove whitespace
	$unwanted = array('SELECT', 'DISTINCT');
	foreach ($sql_tokens as $token) {
		if (!in_array($token, $unwanted)) {
			$id_fieldname = trim($token);
			if (substr($id_fieldname, -1) == ',')
				$id_fieldname = substr($id_fieldname, 0, -1);
			break;
		}
	}
	$sql = zz_edit_sql($sql, 'WHERE', $id_fieldname.' = '.$id);
	$line = zz_db_fetch($sql);
	if ($fieldname) {
		if (isset($line[$fieldname])) return zz_return($line[$fieldname]);
		zz_return(false);
	} else {
		if ($line) zz_return($line);
		zz_return(false);
	}
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
 * @param string $long_field_name // $table_name.'['.$rec.']['.$field_name.']'
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
	
	$my_rec['fields'][$f] = zz_check_select_id($my_rec['fields'][$f],
		$my_rec['POST'][$field_name], $db_table);
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
	} elseif (count($possible_values) == 1) {
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
 * @global array $zz_conf bool 'multi'
 * @return array $field
 */
function zz_check_select_id($field, $postvalue, $db_table) {
	global $zz_conf;
	
	// 1. get field names from SQL query
	$field['sql_fieldnames'] = zz_sql_fieldnames($field['sql']);
	foreach ($field['sql_fieldnames'] as $index => $sql_fieldname) {
		$sql_fieldname = trim($sql_fieldname);
		if (!empty($field['show_hierarchy'])
			AND $sql_fieldname == $field['show_hierarchy']) {
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
	$wheresql .= ')';
	$field['sql_new'] = zz_edit_sql($field['sql'], 'WHERE', $wheresql);
	$field['possible_values'] = zz_db_fetch(
		$field['sql_new'], 'dummy_id', 'single value'
	);
	return $field;
}

?>