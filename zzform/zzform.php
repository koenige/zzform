<?php 

/**
 * zzform
 * Core script
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * List of functions in this file
 *	zzform()				main zzform function
 *		zz_initialize()		sets defaults, imports modules, reads URI
 *	zzform_multi()			multi edit for zzform, e. g. import
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * zzform generates forms for editing single records, list views with several
 * records and does insert, update and delete database operations
 *
 * @param array $zz
 * @global array $zz_conf	configuration variables
 * @todo think of zzform($zz, $zz_conf) to get rid of global variables
 */
function zzform($zz) {
	global $zz_conf;
	
// diversion?
	if (!empty($_GET['request'])) return zzform_exit(zzform_request());

	if (isset($_POST['zz_multifunction'])) {
		wrap_include_files('zzform/multi', 'custom/active');
		$index = key($_POST['zz_multifunction']);
		$function = $zz['list']['multi_function'][$index]['function'];
		$_POST['zz_record_id'] = $_POST['zz_record_id'] ?? [];
		return $function($_POST['zz_record_id']);
	}

//
//	Initialize variables & modules
//
	$ops = [
		'result' => '',
		'headers' => false,
		'output' => '',
		'error' => [],
		'id' => 0,
		'mode' => false,
		'footer' => $zz['footer'] ?? [],
		'footer_text' => '',
		'html_fragment' => !empty($_POST['zz_html_fragment']) ? true : false,
		'redirect_url' => false,
		'explanation' => '',
		'exit' => false
	];
	if (empty($zz_conf['multi']))
		wrap_static('page', '', $zz['page'] ?? [], 'init');

	zzform_deprecated($ops, $zz);

	// set default configuration variables
	// import modules, set and get URI
	zz_initialize('form');
	zz_error();
	if (!empty($_GET['merge']) AND !empty($zz['list']['merge'])) {
		$ops['output'] .= sprintf('<h2>%s</h2>',
			wrap_text('%d records merged successfully', ['values' => substr($_GET['merge'], strrpos($_GET['merge'], '-') + 1)])
		);
	}
	$ops['output'] .= zz_error_output();
	$zz = zz_defaults($zz);
	
	// make some settings always in sync
	// @todo reduce settings
	if (empty($zz_conf['limit']))
		$zz_conf['limit'] = wrap_setting('zzform_limit');
	else
		wrap_setting('zzform_limit', $zz_conf['limit']);

	if (empty($zz['fields'])) {
		zz_error_log([
			'msg_dev' => 'There is no table definition available (\'fields\'). Please check.',
			'level' => E_USER_NOTICE
		]);
		zz_error();
		return zzform_exit($ops);
	}

	$ops['review_via_login'] = false;
	if (!empty($_SESSION['zzform']['review_via_login'])) {
		require_once __DIR__.'/session.inc.php';
		zz_review_via_login();
		$ops['review_via_login'] = true;
	}
	if (empty($zz_conf['multi'])
		AND ((!empty($_POST['zz_add_details']) OR !empty($_POST['zz_edit_details']))
		OR !empty($_SESSION['zzform'][$zz_conf['id']]))
	) {
		require_once __DIR__.'/details.inc.php';
		$zz = zz_details($zz);
	}

	$zz_conf['int']['access'] = $zz['access'] ?? $zz_conf['access'] ?? '';

	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	if (zz_error_exit()) return zzform_exit($ops); // exits script

	// include dependent modules
	zz_dependent_modules($zz);

	// get hash from $zz and $zz_conf to get a unique identification of
	// the settings, e. g. to save time for zzform_multi() or to get a
	// secret key for some cases
	$zz_conf['int']['hash'] = zz_hash($zz, $zz_conf);

//
//	Database connection, set db_name
//
	$zz['table'] = zz_db_connection($zz['table']);
	if (!wrap_setting('db_name')) return zzform_exit($ops); // exits script
	$zz = zz_sql_prefix($zz);
	if (wrap_setting('debug')) zz_debug('database connection ok');
	if (empty($zz['sqlrecord'])) $zz['sqlrecord'] = $zz['sql'];

//
//	Filter, ID, WHERE
//
	// get 'unique_fields', especially 'id' = PRIMARY KEY
	$success = zz_get_unique_fields($zz['fields']);
	// exit if there's something wrong with the table definition
	if (!$success) return zzform_exit($ops);

	// check GET 'filter'
	zz_filter_defaults($zz);

	// get and apply where conditions to SQL query and fields
	zz_where_conditions($zz);

//
//	Check mode, action, access for record;
//	access for list will be checked later
//
	if ($zz_conf['generate_output']) 
		zz_init_limit($zz);

	// initalize export module
	// might set mode to export
	if (!empty($zz_conf['modules']['export'])) zz_export_init($ops, $zz);

	// set $ops['mode'], $zz['record']['action'], ['id']['value'] and $zz_conf for access
	list($zz, $ops) = zz_record_access($zz, $ops);
	$ops['error'] = zz_error_multi($ops['error']);

	// mode won't be changed anymore before record operations
	// action won't be changed before record operations
	// (there it will be changed depending on outcome of db operations)

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
		if (zz_error_exit()) {
			// if an error occured in zz_translations_check_for, return
			return zzform_exit($ops);
		}
	}

//
//	Fields, 2nd check after definitions are complete
//
	if ($ops['mode'] !== 'add' AND $zz['record']['action'] !== 'insert') {
		zz_write_onces($zz);
	}

	// if no operations with the record are done, remove zz_fields
	if (!$zz['record']['action'] AND (!$ops['mode'] OR $ops['mode'] === 'list_only'))
		unset($zz['record']['zz_fields']);

	// Module 'conditions': evaluate conditions
	if (!empty($zz_conf['modules']['conditions'])) {
		if (wrap_setting('debug')) zz_debug('conditions start');
		$zz = zz_conditions_set($zz);
		$zz_conditions = zz_conditions_check($zz, $ops['mode']);
	} else {
		$zz_conditions = [];
	}

	if ($zz_conf['int']['record']) {
		$ops = zzform_record($zz, $ops, $zz_conditions);
		if ($ops['exit']) return zzform_exit($ops);
	}

	if (!$zz_conf['generate_output'])
		return zzform_exit($ops);

	zz_extra_get_params();

	// in case no record is shown, create page info
	$ops = zz_output_page($ops, $zz);

	if (!$zz_conf['int']['record']) {
		// call error function if there's anything
		zz_error();
		$ops['output'] .= zz_error_output();
	}

	if ($zz_conf['int']['show_list']) {
		// shows table with all records (limited by zz_conf['limit'])
		// and add/nav if limit/search buttons
		require_once __DIR__.'/list.inc.php';
		$ops = zz_list($zz, $ops, $zz_conditions);
		if (empty($ops['mode']) AND wrap_static('page', 'status') AND isset($ops['text'])) {
			// return of a request script (mode alone might be empty for empty exports, too)
			$ops['mode'] = '';
			$ops['output'] = $ops['text'];
			$ops['heading'] = $ops['title'];
			// @todo breadcrumbs, head, etc.
		}
	} elseif (!$zz_conf['int']['record']) {
		// no list, no record? redirect to referer
		if ($referer = wrap_static('page', 'referer')) wrap_redirect($referer);
	}
	if ($ops['mode'] !== 'export') {
		$ops['output'] .= zz_output_backlink();
		// if there was no add button in list, add it here
		$ops['output'] .= zz_output_add_export_links($zz, $ops, 'nolist');
	}
	if (zz_error_exit()) return zzform_exit($ops); // critical error: exit;

	if ($ops['heading'])
		$ops['title'] = zz_nice_title($ops['heading'], $zz['fields'], $ops, $ops['mode']);
	if ($ops['mode'] !== 'export')
		$ops['footer_text'] = $zz['footer_text'] ?? '';
	return zzform_exit($ops);
}

/**
 * record operations
 * add, update or delete
 * show record
 *
 * @param array $zz
 * @param array $ops
 * @param array $zz_conditions
 * @return array
 */
function zzform_record($zz, $ops, $zz_conditions) {
	global $zz_conf;

	if (!empty($zz_conf['modules']['conditions'])) {
		$zz_conditions = zz_conditions_record_check($zz, $ops['mode'], $zz_conditions);
		$zz = zz_conditions_record($zz, $zz_conditions);
	}
	// sets some $zz-definitions for records depending on existing definition for
	// translations, subtabes, uploads, write_once-fields
	zz_set_fielddefs_for_record($zz);

	// now we have the correct field definitions	
	// set type, title etc. where unset
	$zz['fields'] = zz_fill_out($zz['fields'], wrap_setting('db_name').'.'.$zz['table'], false, $ops['mode'], $zz['record']['action']); 

	zz_trigger_error_too_big();
	zz_error();	// @todo check if this can go into zz_trigger_error_too_big()

	if ($zz_conf['generate_output'])
		$ops = zz_output_page($ops, $zz);

	if (isset($_POST['zz_merge'])) {
		if (empty($zz['list']['merge']))
			wrap_error(wrap_text('Merging of record is not allowed.'), E_USER_ERROR);

		require_once __DIR__.'/merge.inc.php';
		$merge = zz_merge_records($zz);
		if ($merge['msg'])
			$ops['output'] .= wrap_template('zzform-merge', $merge);
		if ($merge['uncheck']) $ops['list']['dont_check_records'] = true;
		return $ops;
	}

	require_once __DIR__.'/preparation.inc.php';
	$zz_tab = zz_prepare_tables($zz, $ops['mode']);
	if (!$zz_tab) {
		$ops['exit'] = true;
		return $ops;
	}

	// set conditions for detail records
	// id is available just now
	if (!empty($zz_conf['modules']['conditions'])) {
		$zz_conditions = zz_conditions_subrecord_check($zz, $zz_tab, $zz_conditions);
		$zz_tab = zz_conditions_subrecord($zz_tab, $zz_conditions);
	}

	//	Start action

	$validation = true;

	if ($zz['record']['subtables'] && $zz['record']['action'] !== 'delete')
		if (isset($_POST['zz_subtables'])) $validation = false;
	// just handing over form with values
	if (isset($_POST['zz_review'])) $validation = false;
	if ($ops['review_via_login']) $validation = false;
	if ($ops['review_via_login'] OR !empty($_SESSION['zzform']['delete_via_login'])) {
		zz_error_log([
			'msg' => 'You had been logged out automatically. Therefore, your changes were not yet saved. Please submit the form again.',
			'level' => E_USER_NOTICE
		]);
	}
	if (!empty($_SESSION['zzform']['delete_via_login'])) {
		wrap_session_start();
		unset($_SESSION['zzform']['delete_via_login']);
	}

	if (in_array($zz['record']['action'], ['insert', 'update', 'delete'])) {
		// check for validity, insert/update/delete record
		require_once __DIR__.'/action.inc.php';
		list($ops, $zz_tab, $validation) = zz_action($ops, $zz_tab, $validation, $zz['record']);
		// some minor errors?
		zz_error();
		// if an error occured in zz_action, exit
		if (zz_error_exit()) {
			$ops['exit'] = true;
			return $ops;
		}
		// was action successful?
		if ($ops['result'] AND !$zz_conf['generate_output']) {
			// zzform_multi: exit here, rest is for output only
			$ops['exit'] = true;
			return $ops;
		} elseif ($ops['result']) {
			// Redirect, if wanted.
			$ops['redirect_url'] = zz_output_redirect($ops, $zz, $zz_tab);
			if ($ops['redirect_url']) {
				if (empty($ops['html_fragment']))
					wrap_redirect_change($ops['redirect_url']);
				$redirect_url = parse_url($ops['redirect_url']);
				$request_url = parse_url(wrap_setting('request_uri'));
				if ($redirect_url['path'] !== $request_url['path'])
					wrap_redirect_change($ops['redirect_url']);
				$zz['record']['action'] = false;
				$ops['mode'] = 'show';
			}
			// @todo re-evalutate some conditions at this stage
			// since records have changed
			if (!empty($zz_conf['int']['revisions_only'])) {
				$zz_conf['int']['revision_data'] = zz_revisions_tab($zz_tab[0]);
			}
		}
	} elseif ($zz['record']['action'] === 'thumbnails') {
		$ops = zz_upload_thumbnail($ops, $zz_tab);
		$ops['exit'] = true;
		return $ops;
	}

	//	Query updated, added or editable record
	
	if (!$validation) {
		if (!empty($ops['result']) AND str_starts_with($ops['result'], 'partial_')) $ops['mode'] = 'edit';
		elseif ($zz['record']['action'] === 'update') $ops['mode'] = 'edit';
		elseif ($zz['record']['action'] === 'insert') $ops['mode'] = 'add';
		// this is from zz_access() but since mode has set, has to be
		// checked against again
		if (in_array($ops['mode'], ['edit', 'add']) 
			AND !wrap_setting('zzform_show_list_while_edit')) $zz_conf['int']['show_list'] = false;
	}

	if (wrap_setting('debug')) zz_debug('subtables end');

	// query record
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			$zz_tab[$tab] = zz_query_record($zz_tab, $tab, $rec, $validation, $ops['mode']);
		}
	}
	if ($ops['mode'] === 'revise' AND $zz_tab[0][0]['action'] === 'delete') {
		$ops['mode'] = 'delete';
	}
	if (zz_error_exit()) {
		$ops['exit'] = true;
		return $ops;
	}

	if (!$zz_conf['generate_output']) {
		$ops['error'] = zz_error_multi($ops['error']);
		zz_error_validation();
	}
	// save record for footer template
	$ops['record'] = $zz_tab[0][0]['record'];

	if (!$zz_conf['generate_output']) {
		$ops['exit'] = true;
		return $ops;
	}

	// check conditions after action again
	if (!empty($zz_conf['modules']['conditions'])) {
		// update $zz_tab, $zz_conditions
		zz_conditions_before_record($zz, $zz_tab, $zz_conditions, $ops['mode']);
	}

	zz_extra_get_params();

	// display updated, added or editable Record
	require_once __DIR__.'/record.inc.php';
	$ops['output'] .= zz_record($ops, $zz['record'], $zz_tab, $zz_conditions);	
	return $ops;
}

/**
 * do something else via 'request=xy'
 *
 * @param void
 * @return void
 */
function zzform_request() {
	if (empty($_GET['request'])) wrap_quit(404);
	switch ($_GET['request']) {
	case 'captcha':
		if (empty($_GET['zz'])) wrap_quit(404);
		require_once __DIR__.'/captcha.inc.php';
		return zz_captcha_image($_GET['zz']);
		break;
	}
	wrap_quit(404);
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
	
	// last time check for errors
	zz_error();
	$ops['critical_error'] = zz_error_exit();
	$ops['error_mail'] = [];
	if (!empty($zz_conf['int']['error']))
		$ops['error_mail'] = $zz_conf['int']['error'];
	if (!empty($zz_conf['int']['ops_error_msg'])) {
		$ops['error'] = array_merge($ops['error'], $zz_conf['int']['ops_error_msg']);
	}

	// return to old database
	if (!empty($zz_conf['int']['db_current'])) zz_db_select($zz_conf['int']['db_current']);

	// end debug mode
	if (wrap_setting('debug')) {
		zz_debug('end');
		// debug time only if there's a result and before leaving the page
		if ($ops['result'])
			zz_debug('_time', $ops['return']);
		if (wrap_setting('debug') AND $ops['mode'] !== 'export')
			$ops['debug'] = zz_debug('_output');
		zz_debug('_clear');
	}
	// prepare HTML output, not for export
	if ($zz_conf['generate_output'] AND function_exists('zz_output_full')) {
		$ops['output'] = zz_output_full($ops);

		// HTML head
		wrap_static('page', 'head', wrap_template('zzform-head', [], 'ignore positions'), 'append');
		wrap_static('page', 'meta', zz_output_meta_tags(), 'add');

		if (!empty($ops['html_fragment'])) {
			wrap_static('page', 'template', 'empty');
			wrap_static('page', 'url', $ops['redirect_url']);
			wrap_static('page', 'send_as_json', true);
		}
	}

	// HTTP status
	if (!wrap_static('page', 'status'))
		wrap_static('page', 'status', 200);

	// check if request is valid
	$zz_conf['valid_request'] = zz_valid_request();

	// save correct URL
	$ops['url'] = $zz_conf['int']['url']['full'].$zz_conf['int']['url']['qs'];
	if ($zz_conf['int']['url']['qs_zzform']) {
		$ops['url'] .= $zz_conf['int']['url']['?&'].$zz_conf['int']['url']['qs_zzform'];
	}
	if (!$zz_conf['valid_request'] AND !empty($_GET['zzhash'])
		AND (!empty($_GET['insert']) OR !empty($_GET['update']))
	) {
		wrap_static('page', 'redirect', $ops['url']);
		wrap_quit(301, '', wrap_static('page'));
	}

	// get rid of internal variables
	unset($zz_conf['int']);

	$ops['page'] = wrap_static('page');
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
	global $zz_conf;
	static $dont_log_error;

	$action_requests = ['delete', 'insert', 'update', 'noupdate', 'thumbs'];
	$request_found = false;
	foreach ($action_requests as $request) {
		if (!isset($_GET[$request])) continue;
		$request_found = $request;
		break;
	}
	if (!$request_found) return false;
	if ($action) {
		if (!is_array($action)) $action = [$action];
		if (!in_array($request_found, $action)) return false;
	}
	if (!empty($zz_conf['int']['where_with_unique_id'])) return true;
	if (empty($_GET['zzhash'])) return false;
	
	if ($_GET['zzhash'] !== $zz_conf['int']['secret_key']) {
		if (!$dont_log_error) {
			zz_error_log([
				'msg_dev' => 'Hash of script and ID differs from secret key (hash %s, secret %s).',
				'msg_dev_args' => [$_GET['zzhash'], $zz_conf['int']['secret_key']],
				'level' => E_USER_NOTICE
			]);
			$dont_log_error = true;
		}
		// remove invalid parameters from URL (for XHR requests)
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
			$zz_conf['int']['url']['qs_zzform'], ['zzhash', 'insert', 'update']
		);
		$zz_conf['int']['id']['value'] = false;
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
	// set indices
	if (!isset($zz['title']))
		$zz['title'] = NULL;
	if (!isset($zz['explanation']))
		$zz['explanation'] = '';
	if (!isset($zz['conditions']))
		$zz['conditions'] = [];

	// record
	if (!isset($zz['record']['copy']))
		$zz['record']['copy'] = false;	// show action: copy
	if (!isset($zz['record']['add']))
		$zz['record']['add'] = true;	// add or do not add data.
	if (!isset($zz['record']['edit']))
		$zz['record']['edit'] = true;	// show action: edit
	if (!isset($zz['record']['delete']))
		$zz['record']['delete'] = true;	// show action: delete
	if (!isset($zz['record']['view']))
		$zz['record']['view'] = false;	// show action: view
	if (!isset($zz['record']['cancel_link']))
		$zz['record']['cancel_link'] = true;
	if (!isset($zz['record']['no_ok']))
		$zz['record']['no_ok'] = false;
	
	// check hooks if they are arrays
	if (!empty($zz['hooks'])) {
		foreach ($zz['hooks'] as $hook => $actions) {
			if (is_array($actions)) continue;
			$zz['hooks'][$hook] = [$actions];
		}
	}

	return $zz;
}

/**
 * initalize zzform, sets default configuration variables if not set by user
 * includes modules
 *
 * @param string $mode (optional)
 *		default: false; 
 *		'overwrite': overwrites $zz_conf array with $zz_saved
 *		'old_conf': writes $zz_saved['old_conf'] back to $zz_conf
 * @param array $old_conf (optional)
 * @global array $zz_conf
 * @global array $zz_saved (needs global status, access as well from zz_write_conf())
 */
function zz_initialize($mode = false, $old_conf = []) {
	global $zz_conf;
	global $zz_saved;
	static $zzform_calls = 0;
	static $zzform_init = false;

	switch($mode) {
	case 'form':
		$zzform_calls++;
		break;
	case 'old_conf':
		if ($zzform_calls > 1) {
			// in case zzform was called from within zzform, get the old conf back
			$zz_conf = $zz_saved['old_conf'];
			$zzform_calls -= 1;
		}
		if ($zzform_calls > 1) {
			// We're still in multiple calls
			$zz_conf['generate_output'] = false;
			$zz_conf['multi'] = true;
		} else {
			// inside the first call
			$zz_conf['generate_output'] = $old_conf['generate_output'] ?? true;
			$zz_conf['multi'] = false;
		}
		return true;
	}

	if ($zzform_init) {
		zz_error_exit(false);
		zz_error_out(false);
		// get clean $zz_conf without changes from different zzform calls or included scripts
		if ($mode === 'overwrite') {
			if ($zzform_calls === 1) {
				// zzform was called first (zzform_calls >= 1), zzform_multi() inside
				$zz_saved['old_conf'] = $zz_conf;
			}
			if (!empty($zz_saved)) {
				$zz_conf = $zz_saved['conf'];
			}
		}
		zz_initialize_int();
		zz_set_id();
		return true;
	}
	zz_set_id();

	// Configuration on project level: Core defaults and functions
	$default['generate_output']	= true;
	$default['int_modules'] 	= ['debug', 'validate'];
	zz_write_conf($default);
	
	// modules depending on settings
	if ($zz_conf['generate_output']) $zz_conf['int_modules'][] = 'output';

	// include core functions
	require_once __DIR__.'/functions.inc.php';
	require_once __DIR__.'/database.inc.php';
	zz_error_exit(false);
	zz_error_out(false);

	// Modules on project level
	// debug module must come first because of debugging reasons!
	$zz_conf['modules'] = zz_add_modules($zz_conf['int_modules']);
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	// stop if there were errors while adding modules
	if (zz_error_exit()) zz_return(false);

	$default['multi'] 				= false;		// zzform_multi
	$default['url_self']			= false;
	
	zz_write_conf($default);
	
	zz_initialize_int();

	$zzform_init = true;
	$zz_saved['conf'] = $zz_conf;

	if ($mode === 'form' AND $zzform_calls > 1 AND empty($zz_conf['multi'])) { 
		// show a warning only if zzform is not explicitly called via zzform_multi()
		zz_error_log([
			'msg_dev' => 'zzform has been called as a function more than once. '
				.'You might want to check if this is correct.',
			'level' => E_USER_NOTICE
		]);
	}
	zz_return(true);
}

/**
 * create an ID for zzform for internal purposes
 *
 * @param void
 * @global array $zz_conf
 */
function zz_set_id() {
	global $zz_conf;
	if (!empty($zz_conf['id']) AND empty($zz_conf['multi'])) return;
	if (!empty($_GET['zz']) AND strlen($_GET['zz']) === 6) {
		$zz_conf['id'] = zz_check_id_value($_GET['zz']);
	} elseif (!empty($_POST['zz_id']) AND strlen($_POST['zz_id']) === 6) {
		$zz_conf['id'] = zz_check_id_value($_POST['zz_id']);
	} else {
		$zz_conf['id'] = wrap_random_hash(6);
	}
	return;
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
	$zz_conf['int'] = [];
	$zz_conf['int']['allowed_params']['mode'] = [
		'edit', 'delete', 'show', 'add', 'review', 'list_only', 'revise'
	];
	if (array_key_exists('export', $zz_conf['modules']))
		$zz_conf['int']['allowed_params']['mode'][] = 'export';
	// action parameters, 'review' is for internal use only
	$zz_conf['int']['allowed_params']['action'] = [
		'insert', 'delete', 'update', 'multiple', 'thumbnails'
	];

	$zz_conf['int']['url'] = zz_get_url_self();

	if ($zz_conf['generate_output']) {
		// display list of records in database
		$zz_conf['int']['show_list'] = true;
		// don't show list in case 'nolist' parameter is set
		if (isset($_GET['nolist'])) $zz_conf['int']['show_list'] = false;

		zz_init_referer();
	}

	$zz_conf['int']['internal_post_fields'] = [
		'MAX_FILE_SIZE', 'zz_check_select', 'zz_action', 'zz_subtables',
		'zz_delete_file', 'zz_referer', 'zz_save_record', 'zz_subtable_ids',
		'zz_id', 'zz_html_fragment'
	];
}

/**
 * include $zz- or $zz_sub-table definition and accept changes for $zz_conf
 * all other local variables will be ignored
 *
 * @param string $definition_file filename of table definition
 * @param array $values (optional) values which might be used in table definition
 * @return array
 * @deprecated
 */
function zzform_include_table($definition_file, $values = []) {
	return zzform_include($definition_file, $values, 'tables');
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
		$conf = wrap_array_merge($conf, $variables);
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
 * checks whether an error occured because too much was POSTed
 * will try to get GET-Variables from HTTP_REFERER
 *
 * @return bool true: error, false: everything ok
 */
function zzform_post_too_big() {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
	if (!empty($_POST)) return false;
	if (empty($_SERVER['CONTENT_LENGTH'])) return false;
	if ($_SERVER['CONTENT_LENGTH'] <= wrap_return_bytes(ini_get('post_max_size'))) return false;

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

/**
 * mark some deprecated settings
 *
 * @param array $ops
 * @param array $zz
 */
function zzform_deprecated(&$ops, &$zz) {
	global $zz_conf;

	$to_page = ['dont_show_title_as_breadcrumb', 'referer'];
	foreach ($to_page as $key) {
		if (!isset($zz_conf[$key])) continue;
		wrap_static('page', $key, $zz_conf[$key]);
		unset($zz_conf[$key]);
		wrap_error('Use $zz[\'page\'][\''.$key.'\'] instead of $zz_conf[\''.$key.'\']', E_USER_DEPRECATED);
	}
	if (isset($zz['dynamic_referer'])) {
		wrap_static('page', 'dynamic_referer', $zz['dynamic_referer']);
		unset($zz['dynamic_referer']);
		wrap_error('Use $zz[\'page\'][\'dynamic_referer\'] instead of $zz[\'dynamic_referer\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['footer_text'])) {
		$ops['footer']['text'] = $zz_conf['footer_text'];
		unset($zz_conf['footer_text']);
		wrap_error('Use $zz[\'footer\'][\'text\'] instead of $zz_conf[\'footer_text\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['footer_text_insert'])) {
		$ops['footer']['text_insert'] = $zz_conf['footer_text_insert'];
		unset($zz_conf['footer_text_insert']);
		wrap_error('Use $zz[\'footer\'][\'text_insert\'] instead of $zz_conf[\'footer_text_insert\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['footer_template'])) {
		$ops['footer']['template'] = $zz_conf['footer_template'];
		unset($zz_conf['footer_template']);
		wrap_error('Use $zz[\'footer\'][\'template\'] instead of $zz_conf[\'footer_template\']', E_USER_DEPRECATED);
	}
	$to_list = [
		'no_add_above', 'multi_function', 'multi_edit', 'multi_delete', 'merge'
	];
	foreach ($to_list as $key) {
		if (!isset($zz_conf[$key])) continue;
		$zz['list'][$key] = $zz_conf[$key];
		unset($zz_conf[$key]);
		wrap_error('Use $zz[\'list\'][\''.$key.'\'] instead of $zz_conf[\''.$key.'\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['export'])) {
		$zz['export'] = $zz_conf['export'];
		unset($zz_conf['export']);
		wrap_error('Use $zz[\'export\'] instead of $zz_conf[\'export\']', E_USER_DEPRECATED);
	}
	if (!empty($zz_conf['list_display'])) {
		$zz['list']['display'] = $zz_conf['list_display'];
		unset($zz_conf['list_display']);
		wrap_error('Use $zz[\'list\'][\'display\'] instead of $zz_conf[\'list_display\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['export_csv_no_head'])) {
		wrap_setting('export_csv_heading', !$zz_conf['export_csv_no_head']);
		unset($zz_conf['export_csv_no_head']);
		wrap_error('Use setting `export_csv_heading`, false instead of $zz_conf[\'export_csv_no_head\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['export_csv_replace'])) {
		wrap_setting('export_csv_replace', $zz_conf['export_csv_replace']);
		unset($zz_conf['export_csv_replace']);
		wrap_error('Use setting `export_csv_replace` instead of $zz_conf[\'export_csv_replace\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['export_csv_delimiter'])) {
		wrap_setting('export_csv_delimiter', $zz_conf['export_csv_delimiter']);
		unset($zz_conf['export_csv_delimiter']);
		wrap_error('Use setting `export_csv_delimiter` instead of $zz_conf[\'export_csv_delimiter\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['search'])) {
		wrap_setting('zzform_search', $zz_conf['search']);
		unset($zz_conf['search']);
		wrap_error('Use setting `zzform_search` instead of $zz_conf[\'search\']', E_USER_DEPRECATED);
	}
	if (!empty($zz_conf['search_form_always'])) {
		wrap_setting('zzform_search_form_always', $zz_conf['search_form_always']);
		unset($zz_conf['search_form_always']);
		wrap_error('Use setting `zzform_search_form_always` instead of $zz_conf[\'search_form_always\']', E_USER_DEPRECATED);
	}
	if (!empty($zz_conf['nice_tablename'])) {
		foreach ($zz_conf['nice_tablename'] as $table => $nice)
			wrap_setting('zzform_nice_tablename['.$table.']', $nice);
		unset($zz_conf['nice_tablename']);
		wrap_error('Use setting `zzform_nice_tablename` instead of $zz_conf[\'nice_tablename\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['max_select_val_len'])) {
		wrap_setting('zzform_max_select_val_len', $zz_conf['max_select_val_len']);
		unset($zz_conf['max_select_val_len']);
		wrap_error('Use setting `zzform_max_select_val_len` instead of $zz_conf[\'max_select_val_len\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['max_select'])) {
		wrap_setting('zzform_max_select', $zz_conf['max_select']);
		unset($zz_conf['max_select']);
		wrap_error('Use setting `zzform_max_select` instead of $zz_conf[\'max_select\']', E_USER_DEPRECATED);
	}

	$moved_to_record = [
		'redirect', 'copy', 'add', 'edit', 'delete', 'view', 'cancel_link', 'no_ok'
	];
	foreach ($moved_to_record as $key) {
		if (!isset($zz_conf[$key])) continue;
		$zz['record'][$key] = $zz_conf[$key];
		unset($zz_conf[$key]);
		wrap_error('Use $zz[\'record\'][\''.$key.'\'] instead of $zz_conf[\''.$key.'\']', E_USER_DEPRECATED);
	}
	// @todo $zz_conf['if'][1]['add'] is not re-written, as is $zz_conf['if'][1]['search']
	if (isset($zz_conf['dont_show_total_records'])) {
		wrap_setting('zzform_hide_total_records', $zz_conf['dont_show_total_records']);
		unset($zz_conf['dont_show_total_records']);
		wrap_error('Use setting `zzform_hide_total_records` instead of $zz_conf[\'dont_show_total_records\']', E_USER_DEPRECATED);
	}
	if (isset($zz_conf['html_autofocus']) AND !$zz_conf['html_autofocus']) {
		wrap_setting('zzform_autofocus', []);
		unset($zz_conf['html_autofocus']);
		wrap_error('Use setting `zzform_autofocus` instead of $zz_conf[\'html_autofocus\']', E_USER_DEPRECATED);
	}
}
