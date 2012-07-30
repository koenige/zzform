<?php 

/**
 * zzform
 * Core script
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * required: at least PHP 4.1.2 
 * lower PHP versions have not been tested
 * - mysql_real_escape_string: above 4.3 (addslashes will be used in PHP versions prior)
 * - exif_read_info: above 4.2 (no exif data will be read in PHP versions prior)
 *
 * List of functions in this file
 *	zzform()				main zzform function
 *		zz_initialize()		sets defaults, imports modules, reads URI
 *	zzform_multi()			multi edit for zzform, e. g. import
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2012 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * zzform generates forms for editing single records, list views with several
 * records and does insert, update and delete database operations
 *
 * @param array $zz (if empty, will be taken from global namespace)
 * @global array $zz_conf
 * @global array $zz_error
 * @todo think of zzform($zz, $zz_conf) to get rid of global variables
 */
function zzform($zz = array()) {
	if (!$zz) $zz = $GLOBALS['zz'];	// Table description
	global $zz_conf;				// Config variables

	// This variable signals that zzform is included
	if (empty($zz_conf['zzform_calls'])) $zz_conf['zzform_calls'] = 1;
	else $zz_conf['zzform_calls']++;

	// divert to import if set
	if (!empty($_GET['mode']) AND $_GET['mode'] == 'import' AND !empty($zz_conf['import'])) {
		if (empty($zz_conf['dir_inc'])) 
			$zz_conf['dir_inc'] = $zz_conf['dir'].'/inc';
		require_once $zz_conf['dir_inc'].'/import.inc.php';
		return zzform_exit(zzform_import($ops));
	}

//	Variables which are required by several functions
	global $zz_error;
	$zz_error = array();
	$zz_error['error'] = false;	// if true, exit script immediately
	$zz_error['output'] = array();
	
//
//	Default Configuration
//

//	initialize variables
	$ops = array();
	$ops['result'] = false;
	$ops['headers'] = false;
	$ops['output'] = false;
	$ops['error'] = array();
	$zz_tab = array();

	// set default configuration variables
	// import modules
	// set and get URI
	zz_initialize();

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if ($zz_error['error']) return zzform_exit($ops); // exits script

	// include dependent modules
	$post_too_big = zz_dependent_modules($zz);

	if ($zz_conf['zzform_calls'] > 1 AND empty($zz_conf['multi'])) { 
		// show a warning only if zzform is not explicitly called via zzform_multi()
		$zz_error[] = array(
			'msg_dev' => 'zzform has been called as a function more than once. You might want to check if this is correct.',
			'level' => E_USER_NOTICE
		);
		zz_error();
		$ops['output'] .= zz_error_output();
	}

	$zz_conf = zz_backwards($zz_conf);
	
	// get hash from $zz and $zz_conf to get a unique identification of
	// the settings, e. g. to save time for zzform_multi() or to get a
	// secret key for some cases
	$zz_conf['int']['hash'] = zz_hash($zz, $zz_conf);

//
//	Database connection, set db_name
//

	list($zz_tab[0]['db_name'], $zz['table']) = zz_db_connection($zz['table']);
	if (!$zz_tab[0]['db_name']) return zzform_exit($ops); // exits script
	$zz = zz_sql_prefix($zz);
	$zz_conf = zz_sql_prefix($zz_conf, 'zz_conf');

//
//	Filter, WHERE, ID
//

	// check GET 'filter'
	zz_filter_defaults();

	// get 'where_conditions' for SQL query from GET add, filter oder where
	// get 'zz_fields' from GET add
	$zz_var = zz_get_where_conditions($zz);

	// get 'unique_fields', especially 'id' = PRIMARY KEY
	$zz_var = zz_get_unique_fields($zz_var, $zz['fields']);
	// exit if there's something wrong with the table definition
	if (!$zz_var) return zzform_exit($ops);

	// apply where conditions to SQL query
	if (!isset($zz['table_for_where'])) $zz['table_for_where'] = array();
	list($zz['sql'], $zz_var) = zz_apply_where_conditions($zz_var, $zz['sql'], 
		$zz['table'], $zz['table_for_where']);
	unset($zz['table_for_where']);
	
	// if GET add already set some values, merge them to field
	// definition
	foreach (array_keys($zz['fields']) as $no) {
		if (!empty($zz['fields'][$no]['field_name']) AND
			!empty($zz_var['zz_fields'][$zz['fields'][$no]['field_name']])) {
				$zz['fields'][$no] = array_merge($zz['fields'][$no], 
					$zz_var['zz_fields'][$zz['fields'][$no]['field_name']]);
			}
	}

//
//	Check mode, action, access for record;
//	access for list will be checked later
//

	// internal variables, mode and action
	$ops['mode'] = false;		// mode: what form/view is presented to the user
	$zz_var['action'] = false;	// action: what database operations are to be done

	// initalize export module
	// might set mode to export
	if (!empty($zz_conf['export'])) $ops = zz_export_init($ops);

	// set $ops['mode'], $zz_var['action'], ['id']['value'] and $zz_conf for access
	list($zz, $ops, $zz_var) = zz_record_access($zz, $ops, $zz_var);
	$ops['error'] = zz_error_multi($ops['error']);

	// mode won't be changed anymore before record operations

	// action won't be changed before record operations
	// (there it will be changed depending on outcome of db operations)
	// so we can set it to $zz_tab

	if ($zz_conf['show_record'])
		$zz_tab[0][0]['action'] = $zz_var['action'];
	
	// upload values are only needed for record
	if (!$zz_conf['show_record']) unset($zz_conf['upload']);

//	Required files

	if ($zz_conf['show_record']) {
		require_once $zz_conf['dir_inc'].'/record.inc.php';		// Form
	}
	if ($zz_conf['show_list']) {
		require_once $zz_conf['dir_inc'].'/list.inc.php';		// List
	}
	if ($zz_conf['modules']['debug']) zz_debug('files and database connection ok');


//	Variables

	if ($zz_conf['access'] != 'export') {
		zz_error();
		$ops['output'] .= zz_error_output(); // initialise zz_error
	}	
	
	if ($zz_conf['generate_output'])
		$zz_conf['heading'] = zz_output_heading($zz['table']);

	//	Translation module
	//	check whether or not to include default translation subtables
	//	this will be done after conditions were checked for so to be able to
	//	not include certain fields and not to get translation fields for these
	if ($zz_conf['modules']['translations']) {
		$zz['fields'] = zz_translations_init($zz['table'], $zz['fields']);
		if ($zz_error['error']) {
			// if an error occured in zz_translations_check_for, return
			return zzform_exit($ops);
		}
	}

	foreach ($zz['fields'] as $field) {
		// get write once fields so we can base conditions (scope=values) on them
		if (!empty($field['type']) AND $field['type'] == 'write_once' 
			AND ($ops['mode'] != 'add' AND $zz_var['action'] != 'insert')) { 
			$zz_var['write_once'][$field['field_name']] = '';
		}
	}

// 	WHERE, 2nd part, write_once without values

	// write_once will be checked as well, without values
	// where is more important than write_once, so remove it from array if
	// there is a where equal to write_once
	if (!empty($zz_var['write_once'])) {
		foreach (array_keys($zz_var['write_once']) as $field_name) {
			if (empty($zz_var['where_condition'][$field_name])) {
				$zz_var['where_condition'][$field_name] = '';
				$zz_var['where'][$zz['table']][$field_name] = '';
			} else
				unset($zz_var['write_once'][$field_name]);
		}
	}

//	process table description zz['fields']

	// if no operations with the record are done, remove zz_fields
	if (!$zz_var['action'] AND (!$ops['mode'] OR $ops['mode'] == 'list_only'))
		unset($zz_var['zz_fields']);

	if ($zz_conf['show_record']) {
		// ### variables for main table will be saved in zz_tab[0]
		$zz_tab[0]['table'] = $zz['table'];
		$zz_tab[0]['table_name'] = $zz['table'];
		$zz_tab[0]['sql'] = $zz['sql'].(!empty($zz['sqlorder']) ? ' '.$zz['sqlorder'] : '');
	}
	
//	Add, Update or Delete

	// Module 'conditions': evaluate conditions
	if (!empty($zz_conf['modules']['conditions']) AND !empty($zz['conditions'])) {
		if ($zz_conf['modules']['debug']) zz_debug("conditions start");
		$zz_conditions = zz_conditions_record_check($zz, $ops['mode'], $zz_var);
	} else {
		$zz_conditions = array();
	}

	// conditions for list view will be set later
	if ($zz_conf['show_list'])
		$zz['fields_in_list'] = $zz['fields']; 

	if ($zz_conf['show_record']) {
		if (!empty($zz_conf['modules']['conditions'])) {
			$zz['fields'] = zz_conditions_record($zz, $zz_conditions, $zz_var['id']['value']);
		}
	 	// sets some $zz-definitions for records depending on existing definition for
		// translations, subtabes, uploads, write_once-fields
		list($zz, $zz_var) = zz_set_fielddefs_for_record($zz, $zz_var);
		if (empty($zz_var['upload_form'])) unset($zz_conf['upload']); // values are not needed
	}

	// now we have the correct field definitions	
	// set type, title etc. where unset
	$zz['fields'] = zz_fill_out($zz['fields'], $zz_tab[0]['db_name'].'.'.$zz['table'], false, $ops['mode']); 

//	page output
	if ($zz_conf['generate_output'] AND ($zz_conf['show_record'] OR $zz_conf['show_list'])) {
		// make nicer headings
		$zz_conf['heading'] = zz_nice_headings($zz_conf['heading'], $zz['fields'], $zz_var['where_condition']);
		// provisional title, in case errors occur
		$zz_conf['title'] = strip_tags($zz_conf['heading']);
		if (trim($zz_conf['heading']))
			$ops['output'].= "\n".'<h1>'.$zz_conf['heading'].'</h1>'."\n\n";
		if ($zz_conf['heading_text'] 
			AND (!$zz_conf['heading_text_hidden_while_editing'] OR $ops['mode'] == 'list_only')) 
			$ops['output'] .= zz_format($zz_conf['heading_text']);
	}
	if ($post_too_big) {
		$zz_error[] = array(
			'msg' => zz_text('Transfer failed. Probably you sent a file that was too large.').'<br>'
				.zz_text('Maximum allowed filesize is').' '
				.zz_byte_format($zz_conf['upload_MAX_FILE_SIZE']).' &#8211; '
				.sprintf(zz_text('You sent: %s data.'), zz_byte_format($_SERVER['CONTENT_LENGTH'])),
			'level' => E_USER_WARNING
		);
	}
	zz_error();
	if ($zz_conf['generate_output'] AND ($zz_conf['show_record'] OR $zz_conf['show_list'])) {
		$ops['output'] .= zz_error_output();

		$selection = zz_nice_selection($zz['fields']);
		if ($selection)
			$ops['output'].= "\n".'<h2>'.$selection.'</h2>'."\n\n";
	}

	if ($zz_conf['show_record']) {
		//	Upload
		if (in_array('upload', $zz_conf['modules']) && $zz_conf['modules']['upload'])
			zz_upload_check_max_file_size();

		$validation = true;

		// ### variables for main table will be saved in zz_tab[0]
		$zz_tab[0][0]['fields'] = $zz['fields'];
		$zz_tab[0][0]['validation'] = true;
		$zz_tab[0][0]['record'] = false;
		$zz_tab[0][0]['access'] = !empty($zz['access']) ? $zz['access'] : false;

		// get ID field, unique fields, check for unchangeable fields
		$zz_tab[0][0]['id'] = &$zz_var['id'];
	
		//	### put each table (if more than one) into one array of its own ###
		foreach ($zz_var['subtables'] as $tab => $no) {
			$zz_tab[$tab] = zz_get_subtable($zz['fields'][$no], $zz_tab[0], $tab, $no);
			if ($ops['mode'] == 'show' AND $zz_tab[$tab]['values']) {
				// don't show values which are not saved in show-record mode
				$zz_tab[$tab]['values'] = array();
			}
			if ($zz_error['error']) return zzform_exit($ops);
			$zz_tab[$tab] = zz_get_subrecords($ops['mode'], $zz['fields'][$no], $zz_tab[$tab], $zz_tab[0], $zz_var, $tab);
			if ($zz_error['error']) return zzform_exit($ops);
			if (isset($zz_tab[$tab]['subtable_focus'])) {
				// set autofocus on subrecord, not on main record
				$zz_tab[0]['subtable_focus'] = 'set';
			}
		}

		if ($zz_var['subtables'] && $zz_var['action'] != 'delete')
			if (isset($_POST['zz_subtables'])) $validation = false;
		// just handing over form with values
		if (isset($_POST['zz_review'])) $validation = false;
		
		if (!empty($_POST['zz_action'])) {		
			// POST because $zz_var may be set to '' in case of add/delete subrecord
			// get existing record
			if (!empty($zz_var['id']['value'])) {
				$sql = zz_edit_sql($zz_tab[0]['sql'], 'WHERE', $zz_tab[0]['table'].'.'
					.$zz_var['id']['field_name']." = '".$zz_var['id']['value']."'");
				$zz_tab[0]['existing'][0] = zz_db_fetch($sql);
			} elseif (!empty($zz_var['id']['values'])) {
				$sql = zz_edit_sql($zz_tab[0]['sql'], 'WHERE', $zz_tab[0]['table'].'.'
					.$zz_var['id']['field_name']." IN ('".implode("','", $zz_var['id']['values'])."')");
				$zz_tab[0]['existing'] = zz_db_fetch($sql, $zz_var['id']['field_name'], 'numeric');
			} else {
				$zz_tab[0]['existing'][0] = array();
			}

			// Upload
			// if there is a directory which has to be renamed, save old name in array
			// do the same if a file might be renamed, deleted ... via upload
			// or if there is a display or write_once field (so that it can be used
			// e. g. for identifiers):
			if ($zz_var['action'] == 'update' OR $zz_var['action'] == 'delete') {
				if (count($zz_var['save_old_record']) && !empty($zz_tab[0]['existing'][0])) {
					foreach ($zz_var['save_old_record'] as $no) {
						if (empty($zz_tab[0]['existing'][0][$zz['fields'][$no]['field_name']])) continue;
						$_POST[$zz['fields'][$no]['field_name']] = $zz_tab[0]['existing'][0][$zz['fields'][$no]['field_name']];
					}
				}
			}

			// get rid of some POST values that are used at another place
			$internal_fields = array('MAX_FILE_SIZE', 'zz_check_select', 'zz_action',
				'zz_subtables', 'zz_subtable_deleted', 'zz_delete_file',
				'zz_referer', 'zz_save_record');
			$zz_tab[0][0]['POST'] = array();
			foreach (array_keys($_POST) AS $key) {
				if (in_array($key, $internal_fields)) continue;
				$zz_tab[0][0]['POST'][$key] = $_POST[$key];
			}
			//  POST is secured, now get rid of password fields in case of error_log_post
			foreach ($zz['fields'] AS $field) {
				if (empty($field['type'])) continue;
				if ($field['type'] == 'password') unset($_POST[$field['field_name']]);
				if ($field['type'] == 'password_change') unset($_POST[$field['field_name']]);
			}

			// set defaults and values, clean up POST
			$zz_tab[0][0]['POST'] = zz_check_def_vals($zz_tab[0][0]['POST'], $zz_tab[0][0]['fields'], $zz_tab[0]['existing'][0],
				(!empty($zz_var['where'][$zz_tab[0]['table']]) ? $zz_var['where'][$zz_tab[0]['table']] : ''));
		}
	
	//	Start action
		$zz_var['record_action'] = false;
		if (in_array($zz_var['action'], array('insert', 'update', 'delete'))) {
			// check for validity, insert/update/delete record
			require_once $zz_conf['dir_inc'].'/action.inc.php';		// update/delete/insert
			list($ops, $zz_tab, $validation, $zz_var) = zz_action($ops, $zz_tab, $validation, $zz_var); 
			// if an error occured in zz_action, exit
			if ($zz_error['error']) return zzform_exit($ops); 
			// was action successful?
			if ($ops['result'] AND !$zz_conf['generate_output']) {
				// zzform_multi: exit here, rest is for output only
				return zzform_exit($ops);
			} elseif ($ops['result']) {
				// Redirect, if wanted.
				zz_output_redirect($ops['result'], $ops['return'], $zz_var['id']['value'], $zz_tab);
			}
		}

	//	Query updated, added or editable record
	
		if (!$validation) {
			if ($zz_var['action'] == 'update') $ops['mode'] = 'edit';
			elseif ($zz_var['action'] == 'insert') $ops['mode'] = 'add';
			// this is from zz_access() but since mode has set, has to be
			// checked against again
			if (in_array($ops['mode'], array('edit', 'add')) 
				AND !$zz_conf['show_list_while_edit']) $zz_conf['show_list'] = false;
		}
	
		if ($zz_conf['modules']['debug']) zz_debug('subtables end');

		// query record
		foreach (array_keys($zz_tab) as $tab) {
			foreach (array_keys($zz_tab[$tab]) as $rec) {
				if (!is_numeric($rec)) continue;
				$zz_tab[$tab] = zz_query_record($zz_tab[$tab], $rec, $validation, $ops['mode']);
			}
		}
		if ($zz_error['error']) return zzform_exit($ops);

		// there might be now a where value for this record
		if (!empty($zz_var['where'][$zz['table']])) {
			foreach ($zz_var['where'][$zz['table']] as $field_name => $value) {
				if ($value) continue;
				if (empty($zz_tab[0][0]['record'][$field_name])) continue;
				$zz_var['where'][$zz['table']][$field_name] = $zz_tab[0][0]['record'][$field_name];
			}
		}
	}

	if (!$zz_conf['generate_output']) {
		if ($zz_conf['show_record']) {
			$ops['error'] = zz_error_multi($ops['error']);
			zz_error_validation();
		}
		return zzform_exit($ops);
	}
	
	$zz_var['extraGET'] = zz_extra_get_params($ops['mode'], $zz_conf);

	if ($zz_conf['show_record']) {
		// display updated, added or editable Record
		$ops['output'] .= zz_record($ops, $zz_tab, $zz_var, $zz_conditions);	
	} else {
		// call error function if there's anything
		zz_error();
		$ops['output'] .= zz_error_output();
	}

	$ops['output'] .= zz_output_filter($zz_var);
	if ($ops['mode'] != 'add' AND empty($zz_conf['no_add_above'])) {
		$ops['output'] .= zz_output_add_links($zz_var['extraGET']);
	}
	$ops['output'] .= zz_output_backlink($zz_tab, $zz_var['id']);
	if ($zz_conf['show_list']) {
		// shows table with all records (limited by zz_conf['limit'])
		// and add/nav if limit/search buttons
		list($ops, $zz_var) = zz_list($zz, $ops, $zz_var, $zz_conditions); 
	}
	// if there was no add button in list, add it here
	if (!empty($zz_conf['int']['no_add_button_so_far']) AND !empty($zz_conf['no_add_above'])
		AND $ops['mode'] != 'add') {
		$ops['output'] .= zz_output_add_links($zz_var['extraGET']);
	}
	if ($zz_error['error']) return zzform_exit($ops); // critical error: exit;

	// set title
	if ($zz_conf['heading']) {
		$zz_conf['title'] = zz_nice_title($zz_conf['heading'], $zz['fields'], $zz_var, $ops['mode']);
	}
	return zzform_exit($ops);
}

/** 
 * exit function for zzform, will always be called to adjust some settings,
 * write div id zzform, call zz_error a last time
 *
 * @param array $ops
 * @return array $ops, 'output' modified
 */
function zzform_exit($ops) {
	global $zz_conf;
	global $zz_error;
	
	// last time check for errors
	zz_error();
	if (!isset($ops['output'])) $ops['output'] = '';
	$ops['output'] .= zz_error_output();
	$ops['critical_error'] = $zz_error['error'] ? true : false;
	$ops['error_mail'] = array();
	if (!empty($zz_conf['int']['error']))
		$ops['error_mail'] = $zz_conf['int']['error'];

	// return to old database
	if ($zz_conf['int']['db_current']) zz_db_select($zz_conf['int']['db_current']);

	// end debug mode
	if ($zz_conf['modules']['debug']) {
		zz_debug('end');
		// debug time only if there's a result and before leaving the page
		if ($ops['result'] AND $zz_conf['debug_time']) {
			zz_debug_time($ops['return']);
		}
		if ($zz_conf['debug'] AND $zz_conf['access'] != 'export') {
			$ops['output'] .= '<div class="debug">'.zz_debug_htmlout().'</div>'."\n";
		}
		zz_debug_unset();
	}
	// output footer text
	if ($zz_conf['access'] != 'export') {
		if ($zz_conf['footer_text']) $ops['output'].= $zz_conf['footer_text'];
	}

	// prepare HTML output, not for export
	if ($zz_conf['access'] != 'export')
		$ops['output'] = '<div id="zzform">'."\n".$ops['output'].'</div>'."\n";
	if ($zz_conf['show_output']) echo $ops['output'];
	
	// HTML head
	$ops['meta'] = zz_meta_tags();
	
	// HTTP status
	if (!empty($zz_conf['int']['http_status'])) {
		$ops['status'] = $zz_conf['int']['http_status'];
	} else {
		$ops['status'] = 200;
	}

	// check if request is valid
	$zz_conf['valid_request'] = zz_valid_request();

	// get rid of internal variables
	unset($zz_conf['int']);

	return $ops;
}

/**
 * checks if request is valid while accessing a restricted table
 *
 * @param mixed $action check if this action is matching
 * @global array $zz_conf ($zz_conf['int']['secret_key'])
 * @return bool 
 *		true: request is valid
 *		false: request is invalid (or no restriction is in place)
 */
function zz_valid_request($action = false) {
	if (empty($_GET['zzaction'])) return false;
	if ($action) {
		if (!is_array($action)) $action = array($action);
		if (!in_array($_GET['zzaction'], $action)) return false;
	}
	if (empty($_GET['zzhash'])) return false;
	
	global $zz_conf;
	static $dont_log_error;
	if ($_GET['zzhash'] !== $zz_conf['int']['secret_key']) {
		global $zz_error;
		if (!$dont_log_error) {
			$zz_error[] = array(
				'msg_dev' => sprintf('Hash of script and ID differs from secret key (hash %s, secret %s).'
					, $_GET['zzhash'], $zz_conf['int']['secret_key']),
				'level' => E_USER_NOTICE
			);
			$dont_log_error = true;
		}
		return false;
	}
	return true;
}

/**
 * initalize zzform, sets default configuration variables if not set by user
 * includes modules
 *
 * @param string $mode (default: false; others: 'overwrite' overwrites $zz_conf
 *		array with $zz_saved)
 * @global array $zz_conf
 * @global array $zz_error
 * @global array $zz_saved
 * @global array $zz_debug see zz_debug()
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_initialize($mode = false) {
	global $zz_conf;
	global $zz_error;
	global $zz_saved;
	global $zz_debug;	// debug module

	if (!empty($zz_conf['zzform_init'])) {
		// get clean $zz_conf without changes from different zzform calls or included scripts
		if (!empty($zz_conf['zzform_calls']) AND !empty($zz_saved) AND $mode == 'overwrite') {
			$calls = $zz_conf['zzform_calls'];
			$zz_saved['old_conf'] = $zz_conf;
			$zz_conf = $zz_saved['conf'];
			$zz_conf['zzform_calls'] = $calls;
		}
		$zz_conf['id'] = mt_rand();
		return true;
	}
	$zz_conf['id'] = mt_rand();

	//	allowed parameters
	// initialize internal variables
	$zz_conf['int'] = array();
	$zz_conf['int']['this_limit']		= false;	// current range which records are shown
	$zz_conf['int']['allowed_params']['mode'] = array(
		'edit', 'delete', 'show', 'add', 'review', 'list_only'
	);
	// action parameters, 'review' is for internal use only
	$zz_conf['int']['allowed_params']['action'] = array(
		'insert', 'delete', 'update', 'multiple'
	); 
	
	// Configuration on project level: Core defaults and functions
	$zz_default['character_set']	= 'utf-8';					// character set
	$zz_default['dir_ext']			= $zz_conf['dir'].'/ext';	// directory for extensions
	$zz_default['dir_custom']		= $zz_conf['dir'].'/local';
	$zz_default['dir_inc']			= $zz_conf['dir'].'/inc';
	$zz_default['generate_output']	= true;
	$zz_default['error_mail_level']	= array('error', 'warning', 'notice');
	$zz_default['ext_modules']		= array('markdown', 'textile');
	$zz_default['int_modules'] 		= array('debug', 'compatibility', 'validate');
	zz_write_defaults($zz_default, $zz_conf);
	
	// modules depending on settings
	if ($zz_conf['generate_output']) $zz_conf['int_modules'][] = 'output';

	// Configuration on project level: shorthand values
	if (!is_array($zz_conf['error_mail_level'])) {
		if ($zz_conf['error_mail_level'] == 'error')
			$zz_conf['error_mail_level'] = array('error');
		elseif ($zz_conf['error_mail_level'] == 'warning')
			$zz_conf['error_mail_level'] = array('error', 'warning');
		elseif ($zz_conf['error_mail_level'] == 'notice')
			$zz_conf['error_mail_level'] = array('error', 'warning', 'notice');
	}
	// include core functions
	require_once $zz_conf['dir_inc'].'/functions.inc.php';
	require_once $zz_conf['dir_inc'].'/database.inc.php';

	// optional functions
	if (file_exists($zz_conf['dir_inc'].'/forcefilename-'.$zz_conf['character_set'].'.inc.php'))
		include_once $zz_conf['dir_inc'].'/forcefilename-'.$zz_conf['character_set'].'.inc.php';

//	Modules

	// Modules on project level
	// debug module must come first because of debugging reasons!
	$zz_conf['modules'] = zz_add_modules($zz_conf['int_modules'], $zz_conf['dir_inc']);
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$zz_conf['ext_modules'] = zz_add_modules($zz_conf['ext_modules'], $zz_conf['dir_ext']);

	// stop if there were errors while adding modules
	if ($zz_error['error']) zz_return(false);

	$zz_default['action_dir']		= $zz_conf['dir_custom'];	// directory for included scripts after action has been taken
	$zz_default['lang_dir']			= $zz_conf['dir_custom'];	// directory for additional text

	$zz_default['always_show_empty_detail_record'] = false;
	$zz_default['additional_text']	= false;
	$zz_default['backlink']			= true;		// show back-to-overview link
	$zz_default['access']			= '';		// nothing, does not need to be set, might be set individually
	$zz_default['add']				= true;		// add or do not add data.
	$zz_default['cancel_link']		= true;
	$zz_default['check_referential_integrity'] = true;
	$zz_default['copy']				= false;	// show action: copy
	$zz_default['decimal_point']	= '.';
	$zz_default['delete']			= false;	// show action: delete
	$zz_default['details']			= false;	// column details; links to detail records with foreign key
	$zz_default['details_base']		= false;
	$zz_default['details_referer']	= true;		// add referer to details link
	$zz_default['details_url']		= array(); // might be array, therefore no $zz_default
	$zz_default['details_sql']		= array();
	$zz_default['details_target']	= false;	// target-window for details link
	$zz_default['edit']				= true;		// show Action: Edit
	$zz_default['group']			= false;
	$zz_default['import']			= false;	// import files

	$zz_default['error_handling']		= 'output';
	$zz_default['error_log']['error']	= ini_get('error_log');
	$zz_default['error_log']['notice']	= ini_get('error_log');
	$zz_default['error_log']['warning']	= ini_get('error_log');
	$zz_default['error_mail_from']		= false;
	$zz_default['error_mail_to']		= false;
	$zz_default['log_errors_max_len'] 	= ini_get('log_errors_max_len');
	$zz_default['log_errors'] 			= ini_get('log_errors');
	$zz_default['error_log_post']		= false;

	$zz_default['export']				= false;
	$zz_default['filter_position'] 		= 'top';
	$zz_default['filter'] 				= array();
	$zz_default['footer_text']			= false;		// text at the end of all
	$zz_default['group_html_table']		= '<strong>%s</strong>';
	$zz_default['hash_cost_log2']		= 8;
	$zz_default['hash_portable']		= false;
	$zz_default['hash_password']		= 'md5';
	$zz_default['heading_text'] 		= '';
	$zz_default['heading_text_hidden_while_editing'] 	= false;
	$zz_default['heading_prefix']		= false;
	$zz_default['html_autofocus']		= true;
	$zz_default['limit']				= false;	// only n records are shown at once
	$zz_default['limit_show_range'] 	= 800;		// range in which links to records around current selection will be shown
	$zz_default['limit_display']		= 'pages';
	$zz_default['limit_all_max']		= 1500;		// maximum records on one page
	$zz_default['list_display']			= 'table';
	$zz_default['logging'] 				= false;	//	if logging should occur, turned off by default 
	$zz_default['logging_table'] 		= '_logging';	// name of table where INSERT, DELETE and UPDATE actions will be logged
	$zz_default['max_detail_records']	= 20;		// max 20 detail records, might be expanded later on
	$zz_default['max_select_val_len']	= 60;		// maximum length of values in select
	$zz_default['max_select'] 			= 60;		// maximum entries in select/option, if bigger than sub-select
	$zz_default['min_detail_records']	= 0;		// min 0 detail records, might be expanded later on
	$zz_default['multi'] 				= false;		// zzform_multi
	$zz_default['multilang_fieldnames'] = false;	// translate fieldnames via zz_text($fieldname)
	$zz_default['prefix'] 				= false;	//	prefix for ALL tables like zz_
	$zz_default['project']				= $_SERVER['HTTP_HOST'] ? htmlspecialchars($_SERVER['HTTP_HOST']) : $_SERVER['SERVER_NAME'];
	$zz_default['redirect']['successful_delete'] = false;	// redirect to diff. page after delete
	$zz_default['redirect']['successful_insert'] = false;	// redirect to diff. page after insert
	$zz_default['redirect']['successful_update'] = false;	// redirect to diff. page after update
	$zz_default['redirect']['no_update'] = false;	// redirect to diff. page after update without changes
	$zz_default['redirect_on_change']	= true;
	$zz_default['relations_table'] 		= '_relations';	//	name of relations table for referential integrity
	$zz_default['search'] 				= false;	// search for records possible or not
	$zz_default['search_form_always']	= false;
	$zz_default['select_multiple_records'] = false;
	$zz_default['show_hierarchy']	= false;
	$zz_default['show_list_while_edit'] = true;	
	$zz_default['show_list']		= true;		// display list of records in database				
	$zz_default['show_output']		= true;		// ECHO output or keep it in $ops['output']
	$zz_default['tfoot']			= false;  	// shows table foot, e. g. for sums of individual values
	$zz_default['title_separator']	= ' &#8211; ';
	$zz_default['thousands_separator']	= ' ';
	$zz_default['user']				= (isset($_SERVER['PHP_AUTH_USER'])) ? $_SERVER['PHP_AUTH_USER'] : '';
	$zz_default['view']				= false;	// 	show Action: View
	$zz_default['translate_log_encodings'] = array(
		'iso-8859-2' => 'iso-8859-1'
	);
	$zz_default['url_self']			= false;
	
	zz_write_defaults($zz_default, $zz_conf);

	if ($zz_conf['generate_output']) {
		zz_init_limit();
		zz_init_referer();

		// don't show list in case 'nolist' parameter is set
		if (isset($_GET['nolist'])) $zz_conf['show_list'] = false;
	}

	$zz_conf['int']['url'] = zz_get_url_self($zz_conf['url_self']);

	//	URL parameter
	if (get_magic_quotes_gpc()) { // sometimes unwanted standard config
		if (!empty($_POST)) $_POST = magic_quotes_strip($_POST);
		if (!empty($_GET)) $_GET = magic_quotes_strip($_GET);
		if (!empty($_FILES)) $_FILES = magic_quotes_strip($_FILES);
		// _COOKIE and _REQUEST are not being used
	}

	if ($zz_conf['character_set'] == 'utf-8') {
		mb_internal_encoding("UTF-8");
	}

	$zz_conf['zzform_init'] = true;
	$zz_saved['conf'] = $zz_conf;
	zz_return(true);
}

/**
 * call zzform() once or multiple times without user interaction 
 * 
 * If zzform_multi() is called from within zzform() (e. g. via an action before/
 * after-script), not all values in $zz_conf will be reset to default. Therefore,
 * it is important that the script used for zzform_multi() will set all variables
 * as it needs them.
 * A solution might be for you to run zz_initialize() before setting the record
 * specific variables and calling zzform()
 *
 * @param string $definition_file - script filename
 * @param array $values - values sent to script (instead of $_GET, $_POST and $_FILES)
 * @param string $type - what to do
 * @return array $ops
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zzform_multi($definition_file, $values, $type = 'record', $params = false) {
	// unset all variables that are not needed
	// important because there may be multiple zzform calls
	global $zz_conf;
	global $zz_setting;
	global $zz_saved;
	
	// debug, note: this will only start from the second time, zzform_multi()
	// has been called! (modules debug is not set beforehands)
	if (!empty($zz_conf['modules']['debug']) AND !empty($zz_conf['id'])) {
		$id = $zz_conf['id'];
		zz_debug('start', __FUNCTION__);
	}

	// Allowed:
	$allowed_types = array('csv', 'xml', 'files', 'record', 'form');
	if (!in_array($type, $allowed_types)) {
		echo 'Illegal type set for function zzform_multi(): '.htmlspecialchars($type);
		return false;
	}
	
	unset($_GET);
	unset($_POST);
	unset($_FILES);
	$ops = array();
	// keep internal variables
	$int = !empty($zz_conf['int']) ? $zz_conf['int'] : array();

	switch ($type) {
	case 'form': // hand back form to user, just fill out values
		// causes not all zz_conf variables to be reset
		zz_initialize('overwrite');
		$zz_conf['show_output'] = false; // do not show output as it will be included after page head
		$zz_conf['show_list'] = false;	// no output, so list view is not necessary
		$zz_conf['multi'] = true;		// so we know the operation mode for other scripts
		if (!empty($values['GET'])) $_GET = $values['GET'];
		if (!empty($values['POST'])) $_POST = $values['POST'];
		if (!empty($values['FILES'])) $_FILES = $values['FILES'];
		else $_FILES = array();
		// set action to form view
		$_POST['zz_review'] = true;
		if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
			$old_id = $zz_conf['id'];	
			$zz_conf['id'] = $id;
			zz_debug('before including definition file');
		}
		require $zz_conf['form_scripts'].'/'.$definition_file.'.php';
		if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
			zz_debug('definition file included');
			$zz_conf['id'] = $old_id;
		}
		// return on error in form script
		if (!empty($ops['error'])) return $ops;
		$ops = zzform($zz);
		break;
	case 'record':  // one operation only
		// @todo: so far, we have no support for 'values' for subrecords
		// @todo: zzform() and zzform_multi() called within an action-script
		// causes not all zz_conf variables to be reset
		zz_initialize('overwrite');
		$zz_conf['generate_output'] = false; // don't generate output
//		if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__)
		$zz_conf['show_output'] = false; // do not show output as it will be included after page head
		$zz_conf['multi'] = true;		// so we know the operation mode for other scripts
		if (!empty($values['GET'])) $_GET = $values['GET'];
		if (!empty($values['POST'])) $_POST = $values['POST'];
		if (!empty($values['FILES'])) $_FILES = $values['FILES'];
		else $_FILES = array();
		if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
			$old_id = $zz_conf['id'];	
			$zz_conf['id'] = $id;
			zz_debug('before including definition file');
		}
		require $zz_conf['form_scripts'].'/'.$definition_file.'.php';
		if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
			zz_debug('definition file included');
			$zz_conf['id'] = $old_id;
		}
		// return on error in form script
		if (!empty($ops['error'])) return $ops;
		$ops = zzform($zz);
		// in case zzform was called from within zzform, get the old conf back
		if ($zz_conf['zzform_calls'] > 1) {
			$zz_conf = $zz_saved['old_conf'];
		} else {
			$zz_conf['generate_output'] = true;
		}
		break;
	case 'files':
		// @todo: generate output?
		require_once $zz_conf['dir_inc'].'/functions.inc.php';
		require_once $zz_conf['dir_inc'].'/database.inc.php';
		require_once $zz_conf['dir_inc'].'/import.inc.php';
		require_once $zz_conf['dir_inc'].'/forcefilename-'.$zz_conf['character_set'].'.inc.php';
		$ops['output'] = zz_import_files($definition_file, $values, $params);
		return $ops['output'];
	}
	
	// clean up
	unset($_GET);
	unset($_POST);
	unset($_FILES);

	// on success: remove entry in csv file
	// on success: delete files, move files ...
	// on failure: output record, output error ?
	// @todo: export, might go into extra file?

	// what to return:
	// array whith all record_ids that were inserted, sorted by operation (so to include subrecords)

	$zz_conf['int'] = $int;
	if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
		$zz_conf['id'] = $id;
		zz_debug('end');
	}
	return $ops;
}

/* Create config variables from defaults
 *
 * @param array $zz_default	default configuration variables
 * @param array $zz_conf	definitive configuration variables
 * @return - writes directly to $zz_conf
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_write_defaults($zz_default, &$zz_conf) {
	foreach (array_keys($zz_default) as $key) {
		// no key set, so write default values in configuration
		if (!isset($zz_conf[$key])) {
			$zz_conf[$key] = $zz_default[$key];
		} elseif (is_array($zz_default[$key])) {
			// check if it's an array, it might be that some of the subkeys
			// are already set, others not
			foreach (array_keys($zz_default[$key]) as $subkey) {
				if (is_numeric($subkey)) continue;
				if (!isset($zz_conf[$key][$subkey])) {
					$zz_conf[$key][$subkey] = $zz_default[$key][$subkey];
				}
			}
		}
	}
}

/**
 * returns integer byte value from PHP shorthand byte notation
 *
 * @param string $val
 * @return int
 * @see wrap_return_bytes(), identical
 */
function zz_return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}

/**
 * Gives information which meta tags should be added to HTML head
 *
 * @return array
 */
function zz_meta_tags() {
	$meta = array();
	$noindex = false;
	if (!empty($_GET['order'])) $noindex = true;
	if (!empty($_GET['group'])) $noindex = true;
	if (!empty($_GET['mode'])) $noindex = true;
	if (!empty($_GET['q'])) $noindex = true;
	if ($noindex) {
		$meta[] = array('name' => 'robots', 'content' => 'noindex, follow');
	}
	return $meta;
}

/**
 * change some values, just for backwards compatibility
 *
 * @param array $zz_conf
 * @return array
 */
function zz_backwards($zz_conf) {
	$headings = array('var', 'sql', 'enum', 'link', 'link_no_append');
	foreach ($headings as $suffix) {
		if (isset($zz_conf['heading_'.$suffix])) {
			if (function_exists('wrap_error')) {
				wrap_error(sprintf('Use of deprecated variable $zz_conf["heading_'.$suffix.'"] (URL: %s)', $_SERVER['REQUEST_URI']));
			}
			foreach ($zz_conf['heading_'.$suffix] as $field => $value) {
				$zz_conf['heading_sub'][$field][$suffix] = $value;
				unset ($zz_conf['heading_'.$suffix][$field]);
			}
		}
	}
	return $zz_conf;
}

/**
 * checks whether an error occured because too much was POSTed
 * will try to get GET-Variables from HTTP_REFERER
 *
 * @return bool true: error, false: everything ok
 */
function zzform_post_too_big() {	
	if ($_SERVER['REQUEST_METHOD'] == 'POST' AND empty($_POST)
		AND $_SERVER['CONTENT_LENGTH'] > zz_return_bytes(ini_get('post_max_size'))) {
		// without sessions, we can't find out where the user has come from
		// just if we have a REFERER
		if (!empty($_SERVER['HTTP_REFERER'])) {
			$url = parse_url($_SERVER['HTTP_REFERER']);
			if (!empty($url['query'])) {
				parse_str($url['query'], $query);
				$_GET = array_merge($_GET, $query);
			}
		}
		return true;
	}
	return false;
}

?>