<?php 

/**
 * zzform
 * Core script
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * List of functions in this file
 *	zzform()				main zzform function
 *		zz_initialize()		sets defaults, imports modules, reads URI
 *	zzform_multi()			multi edit for zzform, e. g. import
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2015 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * zzform generates forms for editing single records, list views with several
 * records and does insert, update and delete database operations
 *
 * @param array $zz
 * @global array $zz_conf	configuration variables
 * @global array $zz_error	error handling
 * @todo think of zzform($zz, $zz_conf) to get rid of global variables
 */
function zzform($zz) {
	global $zz_conf;
	global $zz_error;

//
//	Initialize variables & modules
//
	// This variable signals that zzform is included
	if (empty($zz_conf['zzform_calls'])) $zz_conf['zzform_calls'] = 1;
	else $zz_conf['zzform_calls']++;

	//	initialize variables
	$zz_error = array(
		'error' => false,		// if true, exit script immediately
		'output' => array()
	);
	$ops = array(
		'result' => '',
		'headers' => false,
		'output' => false,
		'error' => array(),
		'id' => 0,
		'mode' => false
	);
	// set default configuration variables
	// import modules, set and get URI
	zz_initialize();
	$zz = zz_defaults($zz);

	if (empty($zz['fields'])) {
		$zz_error[] = array(
			'msg_dev' => 'There is no table definition available (\'fields\'). Please check.',
			'level' => E_USER_NOTICE
		);
		zz_error();
		$ops['output'] .= zz_error_output();
		return zzform_exit($ops);
	}

	$zz_conf['int']['access'] = isset($zz['access']) ? $zz['access'] : (isset($zz_conf['access']) ? $zz_conf['access'] : '');

	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if ($zz_error['error']) return zzform_exit($ops); // exits script

	zz_set_encoding($zz_conf['character_set']);

	// include dependent modules
	zz_dependent_modules($zz);

	if ($zz_conf['zzform_calls'] > 1 AND empty($zz_conf['multi'])) { 
		// show a warning only if zzform is not explicitly called via zzform_multi()
		$zz_error[] = array(
			'msg_dev' => 'zzform has been called as a function more than once. '
				.'You might want to check if this is correct.',
			'level' => E_USER_NOTICE
		);
		zz_error();
		$ops['output'] .= zz_error_output();
	}

	list($zz_conf, $zz) = zz_backwards($zz_conf, $zz);
	
	// get hash from $zz and $zz_conf to get a unique identification of
	// the settings, e. g. to save time for zzform_multi() or to get a
	// secret key for some cases
	$zz_conf['int']['hash'] = zz_hash($zz, $zz_conf);

//
//	Database connection, set db_name
//
	$zz['table'] = zz_db_connection($zz['table']);
	if (!$zz_conf['db_name']) return zzform_exit($ops); // exits script
	$zz = zz_sql_prefix($zz);
	zz_sql_prefix($zz_conf, 'zz_conf');
	if ($zz_conf['modules']['debug']) zz_debug('database connection ok');

//
//	Filter, ID, WHERE
//
	// get 'unique_fields', especially 'id' = PRIMARY KEY
	$zz_var = zz_get_unique_fields($zz['fields']);
	// exit if there's something wrong with the table definition
	if (!$zz_var) return zzform_exit($ops);

	// check GET 'filter'
	list($zz['filter'], $zz_var['filters']) = zz_filter_defaults($zz);

	// get and apply where conditions to SQL query and fields
	list ($zz, $zz_var) = zz_where_conditions($zz, $zz_var);

//
//	Check mode, action, access for record;
//	access for list will be checked later
//
	$ops['mode'] = false;		// mode: what form/view is presented to the user

	if ($zz_conf['generate_output']) {
		zz_init_limit($zz);
	}

	// initalize export module
	// might set mode to export
	if (!empty($zz_conf['export'])) list($zz, $ops) = zz_export_init($zz, $ops);

	// set $ops['mode'], $zz_var['action'], ['id']['value'] and $zz_conf for access
	list($zz, $ops, $zz_var) = zz_record_access($zz, $ops, $zz_var);
	$ops['error'] = zz_error_multi($ops['error']);

	// mode won't be changed anymore before record operations
	// action won't be changed before record operations
	// (there it will be changed depending on outcome of db operations)

	// upload values are only needed for record
	if (!$zz_conf['int']['record']) unset($zz_conf['upload']);

//
//	Errors? Initaliziation of output
//
	if ($zz_conf['int']['access'] !== 'export') {
		zz_error();
		$ops['output'] .= zz_error_output(); // initialise zz_error
	}	
	
	if ($zz_conf['generate_output'])
		$ops['heading'] = zz_output_heading($zz['title'], $zz['table']);

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

//
//	Fields, 2nd check after definitions are complete
//
	if ($ops['mode'] !== 'add' AND $zz_var['action'] !== 'insert') {
		$zz_var = zz_write_onces($zz, $zz_var);
	}

	// if no operations with the record are done, remove zz_fields
	if (!$zz_var['action'] AND (!$ops['mode'] OR $ops['mode'] === 'list_only'))
		unset($zz_var['zz_fields']);

	// Module 'conditions': evaluate conditions
	if (!empty($zz_conf['modules']['conditions'])) {
		if ($zz_conf['modules']['debug']) zz_debug('conditions start');
		$zz = zz_conditions_set($zz);
		$zz_conditions = zz_conditions_check($zz, $ops['mode'], $zz_var);
	} else {
		$zz_conditions = array();
	}

	// conditions for list view will be set later
	if ($zz_conf['int']['show_list'])
		$zz['fields_in_list'] = $zz['fields']; 

//
//	Add, Update or Delete
//

	if ($zz_conf['int']['record']) {
		if (!empty($zz_conf['modules']['conditions'])) {
			$zz_conditions = zz_conditions_record_check($zz, $ops['mode'], $zz_var, $zz_conditions);
			$zz = zz_conditions_record($zz, $zz_conditions, $zz_var['id']['value']);
		}
	 	// sets some $zz-definitions for records depending on existing definition for
		// translations, subtabes, uploads, write_once-fields
		list($zz['fields'], $zz_var) = zz_set_fielddefs_for_record($zz['fields'], $zz_var);
		if (empty($zz_var['upload_form'])) unset($zz_conf['upload']); // values are not needed
	}

	// now we have the correct field definitions	
	// set type, title etc. where unset
	$zz['fields'] = zz_fill_out($zz['fields'], $zz_conf['db_name'].'.'.$zz['table'], false, $ops['mode'], $zz_var['action']); 

	zz_trigger_error_too_big();
	zz_error();	// @todo check if this can go into zz_trigger_error_too_big()

	if ($zz_conf['generate_output'] AND ($zz_conf['int']['record'] OR $zz_conf['int']['show_list'])) {
		$ops = zz_output_page($ops, $zz, $zz_var['where_condition']);
	}

	if (isset($_POST['zz_multifunction'])) {
		if (file_exists($file = $zz_conf['hooks_dir'].'/multi.inc.php')) {
			require_once $file;
		}
		$index = key($_POST['zz_multifunction']);
		$function = $zz_conf['multi_function'][$index]['function'];
		return $function($_POST['zz_record_id']);
	}
	if (isset($_POST['zz_merge'])) {
		require_once $zz_conf['dir_inc'].'/merge.inc.php';
		$merge = zz_merge_records($zz);
		if ($merge['msg'] OR $merge['title']) {
			$ops['output'] .= zz_merge_message($merge);
		}
		if ($merge['uncheck']) $zz['list']['dont_check_records'] = true;
		$zz_conf['int']['record'] = false;
	}

	if ($zz_conf['int']['record']) {
		require_once $zz_conf['dir_inc'].'/preparation.inc.php';

		if (in_array('upload', $zz_conf['modules']) && $zz_conf['modules']['upload'])
			zz_upload_check_max_file_size();
		
		$zz_tab = zz_prepare_tables($zz, $zz_var, $ops['mode']);
		if (!$zz_tab) return zzform_exit($ops);
		// @todo keep track of current values for ID separately
		$zz_tab[0][0]['id'] = &$zz_var['id'];

		// set conditions for detail records
		// id is available just now
		if (!empty($zz_conf['modules']['conditions'])) {
			$zz_conditions = zz_conditions_subrecord_check($zz, $zz_tab, $zz_conditions);
			$zz_tab = zz_conditions_subrecord($zz_tab, $zz_conditions);
		}

	//	Start action

		$validation = true;

		if ($zz_var['subtables'] && $zz_var['action'] !== 'delete')
			if (isset($_POST['zz_subtables'])) $validation = false;
		// just handing over form with values
		if (isset($_POST['zz_review'])) $validation = false;

		if (in_array($zz_var['action'], array('insert', 'update', 'delete'))) {
			// check for validity, insert/update/delete record
			require_once $zz_conf['dir_inc'].'/action.inc.php';
			list($ops, $zz_tab, $validation) = zz_action($ops, $zz_tab, $validation, $zz_var);
			// some minor errors?
			zz_error();
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
		} elseif ($zz_var['action'] === 'thumbnails') {
			$ops = zz_upload_thumbnail($ops, $zz_tab, $zz_var);
		}

	//	Query updated, added or editable record
	
		if (!$validation) {
			if ($zz_var['action'] == 'update') $ops['mode'] = 'edit';
			elseif ($zz_var['action'] == 'insert') $ops['mode'] = 'add';
			// this is from zz_access() but since mode has set, has to be
			// checked against again
			if (in_array($ops['mode'], array('edit', 'add')) 
				AND !$zz_conf['show_list_while_edit']) $zz_conf['int']['show_list'] = false;
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

		if (!$zz_conf['generate_output']) {
			$ops['error'] = zz_error_multi($ops['error']);
			zz_error_validation();
		}
	}

	if (!$zz_conf['generate_output']) {
		return zzform_exit($ops);
	}
	
	$zz_var['extraGET'] = zz_extra_get_params($ops['mode'], $zz_conf);

	if ($zz_conf['int']['record']) {
		// there might be now a where value for this record
		if (!empty($zz_var['where'][$zz['table']])) {
			foreach ($zz_var['where'][$zz['table']] as $field_name => $value) {
				if ($value) continue;
				if (empty($zz_tab[0][0]['record'][$field_name])) continue;
				$zz_var['where'][$zz['table']][$field_name] = $zz_tab[0][0]['record'][$field_name];
			}
		}
		// display updated, added or editable Record
		require_once $zz_conf['dir_inc'].'/record.inc.php';
		$ops['output'] .= zz_record($ops, $zz_tab, $zz_var, $zz_conditions);	
	} else {
		// call error function if there's anything
		zz_error();
		$ops['output'] .= zz_error_output();
	}

	if ($zz_conf['int']['show_list']) {
		// shows table with all records (limited by zz_conf['limit'])
		// and add/nav if limit/search buttons
		require_once $zz_conf['dir_inc'].'/list.inc.php';
		list($ops, $zz_var) = zz_list($zz, $ops, $zz_var, $zz_conditions); 
	}
	if ($ops['mode'] !== 'export') {
		$ops['output'] .= zz_output_backlink();
		// if there was no add button in list, add it here
		if (!empty($zz_conf['int']['no_add_button_so_far']) AND !empty($zz_conf['no_add_above'])
			AND $ops['mode'] != 'add') {
			$ops['output'] .= zz_output_add_links($zz_var['extraGET']);
		}
	}
	if ($zz_error['error']) return zzform_exit($ops); // critical error: exit;

	// set title
	if ($ops['heading']) {
		$ops['title'] = zz_nice_title($ops['heading'], $zz['fields'], $zz_var, $ops['mode']);
	}
	if ($ops['mode'] !== 'export') {
		$ops['output'] .= zz_output_wmd_editor();
		$ops['output'] .= zz_output_upndown_editor();
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
	if ($ops['mode'] !== 'export') {
		$ops['output'] .= zz_error_output();
	}
	$ops['critical_error'] = $zz_error['error'] ? true : false;
	$ops['error_mail'] = array();
	if (!empty($zz_conf['int']['error']))
		$ops['error_mail'] = $zz_conf['int']['error'];

	// return to old database
	if (!empty($zz_conf['int']['db_current'])) zz_db_select($zz_conf['int']['db_current']);

	// end debug mode
	if ($zz_conf['modules']['debug']) {
		zz_debug('end');
		// debug time only if there's a result and before leaving the page
		if ($ops['result'] AND !empty($zz_conf['debug_time'])) {
			zz_debug_time($ops['return']);
		}
		if ($zz_conf['debug'] AND $ops['mode'] !== 'export') {
			$ops['output'] .= '<div class="debug">'.zz_debug_htmlout().'</div>'."\n";
		}
		zz_debug_unset();
	}
	// prepare HTML output, not for export
	if ($ops['mode'] !== 'export') {
		if ($zz_conf['footer_text']) $ops['output'] .= $zz_conf['footer_text'];
		$ops['output'] = '<div id="zzform">'."\n".$ops['output'].'</div>'."\n";
	}

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

	// save correct URL
	$ops['url'] = $zz_conf['int']['url']['full'].$zz_conf['int']['url']['qs'];
	if ($zz_conf['int']['url']['qs_zzform']) {
		$ops['url'] .= $zz_conf['int']['url']['?&'].$zz_conf['int']['url']['qs_zzform'];
	}

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
	$action_requests = array('delete', 'insert', 'update', 'noupdate');
	$request_found = false;
	foreach ($action_requests as $request) {
		if (!isset($_GET[$request])) continue;
		$request_found = $request;
		break;
	}
	if (!$request_found) return false;
	if ($action) {
		if (!is_array($action)) $action = array($action);
		if (!in_array($request_found, $action)) return false;
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
 * set default values for $zz
 *
 * @param array $zz
 * @return array
 */
function zz_defaults($zz) {
	if (!isset($zz['title']))
		$zz['title'] = NULL;
	if (!isset($zz['explanation']))
		$zz['explanation'] = '';
	return $zz;
}

/**
 * initalize zzform, sets default configuration variables if not set by user
 * includes modules
 *
 * @param string $mode
 *		default: false; 
 *		'overwrite': overwrites $zz_conf array with $zz_saved
 *		'old_conf': writes $zz_saved['old_conf'] back to $zz_conf
 * @global array $zz_conf
 * @global array $zz_error
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_initialize($mode = false) {
	global $zz_conf;
	global $zz_error;
	static $zz_saved;
	
	if ($mode === 'old_conf') {
		// in case zzform was called from within zzform, get the old conf back
		$zz_conf = $zz_saved['old_conf'];
		return true;
	}

	if (!empty($zz_conf['zzform_init'])) {
		// get clean $zz_conf without changes from different zzform calls or included scripts
		if ($mode === 'overwrite') {
			if (!empty($zz_conf['zzform_calls'])) {
				// zzform was called first (zzform_calls >= 1), zzform_multi() inside
				$zz_saved['old_conf'] = $zz_conf;
			}
			if (!empty($zz_saved)) {
				$calls = $zz_conf['zzform_calls'];
				$zz_conf = $zz_saved['conf'];
				$zz_conf['zzform_calls'] = $calls;
			}
		}
		zz_initialize_int();
		$zz_conf['id'] = mt_rand();
		return true;
	}
	$zz_conf['id'] = mt_rand();

	// Configuration on project level: Core defaults and functions
	$default['character_set']	= 'utf-8';					// character set
	$default['dir_ext']			= $zz_conf['dir'].'/ext';	// directory for extensions
	$default['dir_custom']		= $zz_conf['dir'].'/local';
	$default['dir_inc']			= $zz_conf['dir'].'/inc';
	$default['generate_output']	= true;
	$default['error_mail_level']	= array('error', 'warning', 'notice');
	$default['ext_modules']		= array('markdown', 'textile');
	$default['int_modules'] 	= array('debug', 'compatibility', 'validate');
	zz_write_conf($default);
	
	// modules depending on settings
	if ($zz_conf['generate_output']) $zz_conf['int_modules'][] = 'output';

	// Configuration on project level: shorthand values
	if (!is_array($zz_conf['error_mail_level'])) {
		if ($zz_conf['error_mail_level'] === 'error')
			$zz_conf['error_mail_level'] = array('error');
		elseif ($zz_conf['error_mail_level'] === 'warning')
			$zz_conf['error_mail_level'] = array('error', 'warning');
		elseif ($zz_conf['error_mail_level'] === 'notice')
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
	zz_add_modules($zz_conf['ext_modules'], $zz_conf['dir_ext']);

	// stop if there were errors while adding modules
	if ($zz_error['error']) zz_return(false);

	$default['hooks_dir']		= $zz_conf['dir_custom'];	// directory for included scripts after action has been taken
	$default['lang_dir']		= $zz_conf['dir_custom'];	// directory for additional text

	$default['always_show_empty_detail_record'] = false;
	$default['additional_text']	= false;
	$default['add']				= true;		// add or do not add data.
	$default['cancel_link']		= true;
	$default['check_referential_integrity'] = true;
	$default['copy']			= false;	// show action: copy
	$default['decimal_point']	= '.';
	$default['delete']			= true;	// show action: delete
	$default['details']			= false;	// column details; links to detail records with foreign key
	$default['details_base']	= false;
	$default['details_referer']	= true;		// add referer to details link
	$default['details_url']		= array(); // might be array, therefore no $default
	$default['details_sql']		= array();
	$default['details_target']	= false;	// target-window for details link
	$default['edit']			= true;		// show Action: Edit

	$default['error_handling']		= 'output';
	$default['error_log']['error']	= ini_get('error_log');
	$default['error_log']['notice']	= ini_get('error_log');
	$default['error_log']['warning']	= ini_get('error_log');
	$default['error_mail_from']		= false;
	$default['error_mail_to']		= false;
	$default['log_errors_max_len'] 	= ini_get('log_errors_max_len');
	$default['log_errors'] 			= ini_get('log_errors');
	$default['error_log_post']		= false;

	$default['export']				= false;
	$default['filter_position'] 		= 'top';
	$default['footer_text']			= false;		// text at the end of all
	$default['group_html_table']		= '<strong>%s</strong>';
	$default['hash_cost_log2']		= 8;
	$default['hash_portable']		= false;
	$default['hash_password']		= 'md5';
	$default['heading_prefix']		= false;
	$default['html_autofocus']		= true;
	$default['limit']				= false;	// only n records are shown at once
	$default['limit_show_range'] 	= 800;		// range in which links to records around current selection will be shown
	$default['limit_display']		= 'pages';
	$default['limit_all_max']		= 1500;		// maximum records on one page
	$default['list_display']			= 'table';
	$default['logging'] 				= false;	//	if logging should occur, turned off by default 
	$default['logging_table'] 		= '_logging';	// name of table where INSERT, DELETE and UPDATE actions will be logged
	$default['max_detail_records']	= 20;		// max 20 detail records, might be expanded later on
	$default['max_select_val_len']	= 60;		// maximum length of values in select
	$default['max_select'] 			= 60;		// maximum entries in select/option, if bigger than sub-select
	$default['merge']				= false;
	$default['min_detail_records']	= 0;		// min 0 detail records, might be expanded later on
	$default['multi'] 				= false;		// zzform_multi
	$default['multi_delete']		= false;
	$default['multi_edit']			= false;
	$default['multi_function']		= array();
	$default['multilang_fieldnames'] = false;	// translate fieldnames via zz_text($fieldname)
	$default['prefix'] 				= false;	//	prefix for ALL tables like zz_
	$default['project']				= preg_match('/^[a-zA-Z0-9-\.]+$/', $_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
	$default['redirect']['successful_delete'] = false;	// redirect to diff. page after delete
	$default['redirect']['successful_insert'] = false;	// redirect to diff. page after insert
	$default['redirect']['successful_update'] = false;	// redirect to diff. page after update
	$default['redirect']['no_update'] = false;	// redirect to diff. page after update without changes
	$default['redirect_on_change']	= true;
	$default['relations_table'] 		= '_relations';	//	name of relations table for referential integrity
	$default['search'] 				= true;	// search for records possible or not
	$default['search_form_always']	= false;
	$default['show_list_while_edit'] = true;
	$default['show_output']		= true;		// ECHO output or keep it in $ops['output']
	$default['title_separator']	= ' &#8211; ';
	$default['thousands_separator']	= ' ';
	$default['user']				= isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
	$default['view']				= false;	// 	show Action: View
	$default['translate_log_encodings'] = array(
		'iso-8859-2' => 'iso-8859-1'
	);
	$default['url_self']			= false;
	
	zz_write_conf($default);
	
	zz_initialize_int();

	//	URL parameter
	if (get_magic_quotes_gpc()) {
		// sometimes unwanted standard config
		// @deprecated removed from PHP 5.4.0 on
		if (!empty($_POST)) $_POST = zz_magic_quotes_strip($_POST);
		if (!empty($_GET)) $_GET = zz_magic_quotes_strip($_GET);
		if (!empty($_FILES)) $_FILES = zz_magic_quotes_strip($_FILES);
		// _COOKIE and _REQUEST are not being used
	}

	$zz_conf['zzform_init'] = true;
	$zz_saved['conf'] = $zz_conf;
	zz_return(true);
}

/**
 * initalize internal variables
 * will be created new for each call, are not visible to the outside
 *
 * @global array $zz_conf
 */
function zz_initialize_int() {
	global $zz_conf;

	//	allowed parameters
	// initialize internal variables
	$zz_conf['int'] = array();
	$zz_conf['int']['allowed_params']['mode'] = array(
		'edit', 'delete', 'show', 'add', 'review', 'list_only'
	);
	// action parameters, 'review' is for internal use only
	$zz_conf['int']['allowed_params']['action'] = array(
		'insert', 'delete', 'update', 'multiple', 'thumbnails'
	); 

	$zz_conf['int']['url'] = zz_get_url_self($zz_conf['url_self']);

	if ($zz_conf['generate_output']) {
		// display list of records in database
		$zz_conf['int']['show_list'] = true;
		// don't show list in case 'nolist' parameter is set
		if (isset($_GET['nolist'])) $zz_conf['int']['show_list'] = false;

		zz_init_referer();
	}
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
 *		array	'POST'
 *		array	'GET'
 *		array	'FILES'
 *		string	'action' => 'POST'['zz_action']: insert, delete, update
 *		array	'ids' => List of select-field names that get direct input of an id 
 * @return array $ops
 * @todo do not unset superglobals
 * @todo so far, we have no support for 'values' for subrecords
 * @todo zzform() and zzform_multi() called within an action-script
 * causes not all zz_conf variables to be reset
 */
function zzform_multi($definition_file, $values) {
	// unset all variables that are not needed
	// important because there may be multiple zzform calls
	global $zz_conf;
	
	$old_conf = $zz_conf;
	// debug, note: this will only start from the second time, zzform_multi()
	// has been called! (modules debug is not set beforehands)
	if (!empty($zz_conf['modules']['debug']) AND !empty($zz_conf['id'])) {
		$id = $zz_conf['id'];
		zz_debug('start', __FUNCTION__);
	}

	unset($_GET);
	unset($_POST);
	unset($_FILES);
	$ops = array();
	$ops['result'] = '';
	$ops['id'] = 0;
	// keep internal variables
	$int = !empty($zz_conf['int']) ? $zz_conf['int'] : array();

	zz_initialize('overwrite');
	$zz_conf['generate_output'] = false;
	// do not show output as it will be included after page head
	$zz_conf['show_output'] = false;
	// set 'multi' so we know the operation mode for other scripts
	$zz_conf['multi'] = true;
	if (!empty($values['GET'])) $_GET = $values['GET'];
	if (!empty($values['POST'])) $_POST = $values['POST'];
	// add some shortcuts easier to remember
	if (!empty($values['action'])) {
		$_POST['zz_action'] = $values['action'];
	}
	if (!empty($values['ids'])) {
		foreach ($values['ids'] as $field_name) {
			$_POST['zz_check_select'][$field_name] = true;
		}
	}

	if (!empty($values['FILES'])) $_FILES = $values['FILES'];
	else $_FILES = array();

	if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
		$old_id = $zz_conf['id'];	
		$zz_conf['id'] = $id;
		zz_debug('before including definition file');
	}
	$zz = zzform_include_table($definition_file, $values);
	if (empty($zz_conf['user'])) {
		$zz_conf['user'] = $_SERVER['REQUEST_URI'];
	}
	if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
		zz_debug('definition file included');
		$zz_conf['id'] = $old_id;
	}
	// return on error in form script
	if (!empty($ops['error'])) return $ops;
	$ops = zzform($zz);
	if ($zz_conf['zzform_calls'] > 1) {
		zz_initialize('old_conf');
	} else {
		$zz_conf['generate_output'] = isset($old_conf['generate_output']) ? $old_conf['generate_output'] : true;
		$zz_conf['show_output'] = isset($old_conf['show_output']) ? $old_conf['show_output'] : true;
		$zz_conf['multi'] = false;
		// zzform_multi was called before zzform from some other script
		// this is of no interest to us
		$zz_conf['zzform_calls'] = 0;
	}
	
	// clean up
	unset($_GET);
	unset($_POST);
	unset($_FILES);

	$zz_conf['int'] = $int;
	if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
		$zz_conf['id'] = $id;
		zz_debug('end');
	}
	return $ops;
}

/**
 * get filename of table definition file
 *
 * @param string $definition_file
 * @global array $zz_conf
 * @global array $zz_setting
 * @return array list of scripts
 */
function zzform_file($definition_file) {
	global $zz_conf;
	global $zz_setting;
	
	$scripts = array();

	if (file_exists($zz_conf['form_scripts'].'/'.$definition_file.'.php')) {
		if (file_exists($zz_conf['form_scripts'].'/_common.inc.php')) {
			$scripts['common'] = $zz_conf['form_scripts'].'/_common.inc.php';
		} else {
			$scripts['common'] = false;
		}
		$scripts['tables'] = $zz_conf['form_scripts'].'/'.$definition_file.'.php';
	} else {
		require_once $zz_setting['lib'].'/zzbrick/forms.inc.php';
		$brick['setting'] = &$zz_setting;
		if (empty($brick['setting']['brick_custom_dir']))
			$brick['setting']['brick_custom_dir'] = $zz_setting['custom'].'/zzbrick_';
		if (empty($brick['setting']['brick_module_dir']))
			$brick['setting']['brick_module_dir'] = '/zzbrick_';

		$brick['path'] = $brick['setting']['brick_custom_dir'].'tables';
		$brick['module_path'] = $brick['setting']['brick_module_dir'].'tables';
		$brick['vars'] = array($definition_file);
		$brick = brick_forms_file($brick);
		$scripts['common'] = $brick['common_script_path']; // might be empty
		$scripts['tables'] = $brick['form_script_path']; // might be empty
	}
	return $scripts;
}

/**
 * include $zz- or $zz_sub-table definition and accept changes for $zz_conf
 * all other local variables will be ignored
 *
 * @param string $definition_file filename of table definition
 * @param array $values (optional) values which might be used in table definition
 * @global array $zz_conf
 * @global array $zz_setting
 * @return array $zz
 */
function zzform_include_table($definition_file, $values = array()) {
	global $zz_conf;
	global $zz_setting;
	
	$scripts = zzform_file($definition_file);
	if ($scripts) {
		if ($scripts['common']) require_once $scripts['common'];
		$zz_view = !empty($values['view']) ? $values['view'] : false;		
		require $scripts['tables'];
		if (!empty($zz)) {
			$zz['view'] = $zz_view;
			return $zz;
		}
		if (!empty($zz_sub)) {
			$zz_sub['view'] = $zz_view;
			return $zz_sub;
		}
		$error = 'No table definition in file %s found.';
	} else {
		$error = 'Table definition for %s: file is missing.';
	}

	$error = sprintf($error, $definition_file);
	if (function_exists('wrap_error')) {
		wrap_error($error, E_USER_ERROR);
	} else {
		// @todo throw zzform error
		echo $error;
	}
	exit;
}

/**
 * Create config variables from defaults
 *
 * @param array $variables	default configuration variables
 * @param bool $overwrite	overwrite existing variables? default = no
 * @global $zz_conf
 * @return bool
 */
function zz_write_conf($variables, $overwrite = false) {
	global $zz_conf;
	zz_write_conf_vars($variables, $zz_conf, $overwrite);
	if (!empty($GLOBALS['zz_saved']['conf'])) {
		zz_write_conf_vars($variables, $GLOBALS['zz_saved']['conf'], $overwrite);
	}
	return true;
}

function zz_write_conf_vars($variables, &$conf, $overwrite) {
	if ($overwrite) {
		$conf = zz_array_merge($conf, $variables);
		return true;
	}
	foreach (array_keys($variables) as $key) {
		if (!isset($conf[$key])) {
			// no key set, so write default values in configuration
			$conf[$key] = $variables[$key];
		} elseif (is_array($variables[$key])) {
			// check if it's an array, it might be that some of the subkeys
			// are already set, others not
			foreach (array_keys($variables[$key]) as $subkey) {
				if (is_numeric($subkey)) continue;
				if (!isset($conf[$key][$subkey])) {
					$conf[$key][$subkey] = $variables[$key][$subkey];
				}
			}
		}
	}
	return true;
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
	$querystrings = array(
		'order', 'group', 'mode', 'q'
	);
	foreach ($querystrings as $string) {
		if (empty($_GET[$string])) continue;
		$noindex = true;
		break;
	}
	if ($noindex) {
		$meta[] = array('name' => 'robots', 'content' => 'noindex, follow');
	}
	return $meta;
}

/**
 * checks whether an error occured because too much was POSTed
 * will try to get GET-Variables from HTTP_REFERER
 *
 * @return bool true: error, false: everything ok
 */
function zzform_post_too_big() {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
	if (!empty($_POST)) return false;
	if ($_SERVER['CONTENT_LENGTH'] <= zz_return_bytes(ini_get('post_max_size'))) return false;

	// without sessions, we can't find out where the user has come from
	// just if we have a REFERER
	if (empty($_SERVER['HTTP_REFERER'])) return true;
	$url = parse_url($_SERVER['HTTP_REFERER']);
	if (!empty($url['query'])) {
		parse_str($url['query'], $query);
		$_GET = array_merge($_GET, $query);
	}
	return true;
}
