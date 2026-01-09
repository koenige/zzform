<?php 

/**
 * zzform
 * Core script
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * List of functions in this file
 *	zzform()				main zzform function
 *		zz_initialize()		sets defaults, imports modules, reads URI
 *	zzform_multi()			multi edit for zzform, e. g. import
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2026 Gustaf Mossakowski
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

	if (isset($_POST['zz_batch_function'])) {
		wrap_include('zzform/multi', 'custom/active');
		$index = key($_POST['zz_batch_function']);
		$function = $zz['list']['batch_function'][$index]['function'];
		$_POST['zz_record_id'] = $_POST['zz_record_id'] ?? [];
		return $function($_POST['zz_record_id']);
	}

	if (isset($_POST['zz_batch_delete'])) {
		wrap_include('batch', 'zzform');
		zzform_batch_delete($zz, $_POST);
	}
	
//
//	Initialize variables & modules
//

	// include core functions
	zzform_includes();

	// remove and log deprecated variables
	$zz = zz_configuration_deprecated('zz_conf', $zz_conf, $zz);
	$zz = zz_configuration_deprecated('zz', $zz, $zz);
	
	// initialise $zz
	$zz = zz_configuration('zz', $zz);

	$ops = [
		'result' => '',
		'headers' => false,
		'output' => '',
		'error' => [],
		'id' => 0,
		'mode' => false,
		'footer' => $zz['footer'],
		'footer_text' => '',
		'html_fragment' => !empty($_POST['zz_html_fragment']) ? true : false,
		'redirect_url' => false,
		'explanation' => '',
		'exit' => false,
		'old_settings' => zzform_setting($zz['setting']),
		'ignore' => false
	];
	// internal zzform variables
	wrap_static('zzform', '', $zz['vars'], 'init');
	// page variables, general settings
	if (empty($zz_conf['multi']))
		wrap_static('page', '', $zz['page'], 'init');
	zz_error_validation_log('delete');

	// set default configuration variables
	// import modules, set and get URI
	zz_initialize('form');
	zz_error();
	if (!empty($_GET['merge']) AND $zz['list']['merge']) {
		$ops['output'] .= sprintf('<h2>%s</h2>',
			wrap_text('%d records merged successfully', ['values' => substr($_GET['merge'], strrpos($_GET['merge'], '-') + 1)])
		);
	} elseif (!empty($_SESSION['zzform']['batch'])) {
		wrap_include('batch', 'zzform');
		$ops['output'] .= sprintf('<h2>%s</h2>', zzform_batch_delete_result());
	}
	$ops['output'] .= zz_error_output();
	
	if (!$zz['fields']) {
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

	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	if (zz_error_exit()) return zzform_exit($ops); // exits script

	// include dependent modules
	zz_dependent_modules($zz);

	// get hash from $zz and $zz_conf to get a unique identification of
	// the settings, e. g. to save time for zzform_multi() or to get a
	// secret key for some cases
	zz_hash($zz, $zz_conf);

	if (zz_modules('conditions'))
		$zz = zz_conditions_access($zz);
	$zz_conf['int']['access'] = $zz['access'] ?? '';

//
//	Database connection, set db_name
//
	$zz = zz_sql_prefix($zz);
	if (wrap_setting('debug')) zz_debug('database connection ok');
	if (!$zz['sql_record']) $zz['sql_record'] = $zz['sql'];

//
//	Filter, ID, WHERE
//
	// get 'unique_fields', especially 'id' = PRIMARY KEY
	$success = zz_get_unique_fields($zz['fields']);
	// exit if there's something wrong with the table definition
	if (!$success) return zzform_exit($ops);

	// check GET 'filter'
	if (zz_modules('filter'))
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
	if (zz_modules('export')) zz_export_init($ops, $zz);

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
	if (zz_modules('translations')) {
		$zz['fields'] = zz_translations_init($zz['table'], $zz['fields'], $zz['record']['action']);
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
	if (zz_modules('conditions')) {
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

	// in case no record is shown, create page info
	$ops = zz_output_page($ops, $zz);

	if (!$zz_conf['int']['record']) {
		// call error function if there's anything
		zz_error();
		$ops['output'] .= zz_error_output();
	}

	// get $list
	if (!empty($ops['list']['unchanged'])) {
		$zz = wrap_array_merge($zz, $ops['list']['unchanged']);
		unset($ops['list']['unchanged']);
	}
	$list = zz_configuration('zz[list]', $zz['list'], $ops['list'] ?? []);
	// don't show list in case 'nolist' parameter is set
	if (isset($_GET['nolist']) AND (!isset($_GET['delete']) OR $_GET['delete']))
		$list['display'] = false;
	if (in_array($ops['mode'], ['edit', 'delete', 'add', 'revise'])
		AND !wrap_setting('zzform_show_list_while_edit')) $list['display'] = false;

	if ($list['display']) {
		// shows table with all records (limited by setting `zzform_limit`)
		// and add/nav if limit/search buttons
		require_once __DIR__.'/list.inc.php';
		$ops = zz_list($zz, $list, $ops, $zz_conditions);
		if (empty($ops['mode']) AND (wrap_static('page', 'status') OR !empty($ops['status'])) AND isset($ops['text'])) {
			// return of a request script (mode alone might be empty for empty exports, too)
			$ops['mode'] = '';
			$ops['output'] = $ops['text'];
			$ops['heading'] = $ops['title'];
			$ops['explanation'] = '';
			if (!wrap_static('page', 'status'))
				wrap_static('page', 'status', $ops['status']);
			// @todo show error message if status != 200
			// @todo breadcrumbs, head, etc.
		}
	} elseif (!$zz_conf['int']['record']) {
		// no list, no record? redirect to referer
		if ($referer = wrap_static('page', 'referer')) wrap_redirect($referer);
	}
	$zz['fields'] = zz_fill_out($zz['fields'], $zz['table']);
	if ($ops['mode'] !== 'export') {
		$ops['output'] .= zz_output_backlink();
		// if there was no add button in list, add it here
		$ops['output'] .= zz_output_add_export_links($zz, $ops, 'nolist');
	}
	if (zz_error_exit()) return zzform_exit($ops); // critical error: exit;

	if ($ops['heading'])
		$ops['title'] = zz_nice_title($ops['heading'], $zz['fields'], $ops, $ops['mode']);
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

	if (zz_modules('conditions')) {
		$zz_conditions = zz_conditions_record_check($zz, $ops['mode'], $zz_conditions);
		$zz = zz_conditions_record($zz, $zz_conditions);
	}
	// sets some $zz-definitions for records depending on existing definition for
	// translations, subtabes, uploads, write_once-fields
	zz_set_fielddefs_for_record($zz);

	// now we have the correct field definitions	
	// set type, title etc. where unset
	$zz['fields'] = zz_fill_out($zz['fields'], $zz['table'], false, $ops['mode'], $zz['record']['action']); 

	zz_trigger_error_too_big();
	zz_error();	// @todo check if this can go into zz_trigger_error_too_big()

	if ($zz_conf['generate_output'])
		$ops = zz_output_page($ops, $zz);

	if (isset($_POST['zz_merge'])) {
		if (empty($zz['list']['merge']))
			wrap_error(wrap_text('Merging of record is not allowed.'), E_USER_ERROR);

		require_once __DIR__.'/merge.inc.php';
		$merge = zz_merge_records($zz);
		if ($merge) {
			if ($merge['msg'])
				$ops['output'] .= wrap_template('zzform-merge', $merge);
			if ($merge['uncheck']) $ops['list']['dont_check_records'] = true;
		}
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
	if (zz_modules('conditions')) {
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
			// remove secret
			zz_secret_id_delete();
			// Redirect, if wanted.
			$ops['redirect_url'] = zz_output_redirect($ops, $zz, $zz_tab);
			if ($ops['redirect_url']) {
				if (empty($ops['html_fragment']))
					wrap_redirect_change($ops['redirect_url']);
				if (parse_url($ops['redirect_url'], PHP_URL_PATH) !== parse_url(wrap_setting('request_uri'), PHP_URL_PATH))
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
			AND !wrap_setting('zzform_show_list_while_edit')) $ops['list']['display'] = false;
	}

	if (wrap_setting('debug')) zz_debug('subtables end');

	// query record
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($tab)) continue;
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
	if (zz_modules('conditions')) {
		// update $zz_tab, $zz_conditions
		zz_conditions_before_record($zz, $zz_tab, $zz_conditions, $ops['mode']);
	}

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
	if (!empty($zz_conf['int']['ops_error_msg']))
		$ops['error'] = array_merge($ops['error'], $zz_conf['int']['ops_error_msg']);

	// reset changed settings in $zz['setting']
	zzform_setting($ops['old_settings'] ?? []);

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
			wrap_setting('send_as_json', true);
		}
	}

	// HTTP status
	if (!wrap_static('page', 'status'))
		wrap_static('page', 'status', 200);

	if ($zz_conf['generate_output']) {
		// save correct URL
		$ops['url'] = zzform_url();

		// check if request is valid
		if (!zz_valid_request() AND !empty($_GET['zzhash'])
			AND (!empty($_GET['insert']) OR !empty($_GET['update']))
		) {
			wrap_static('page', 'redirect', $ops['url']);
			wrap_quit(301, '', wrap_static('page'));
		}
	}

	// for use in a template, %%% if form insert %%% etc.
	wrap_static('zzform', 'insert', $ops['result'] === 'successful_insert' ? true : zz_valid_request('insert'));
	wrap_static('zzform', 'delete', $ops['result'] === 'successful_delete' ? true : zz_valid_request('delete'));
	wrap_static('zzform', 'update', $ops['result'] === 'successful_update' ? true : zz_valid_request('update'));
	wrap_static('zzform', 'no_update', $ops['result'] === 'no_update' ? true : zz_valid_request('noupdate'));

	// get rid of internal variables
	unset($zz_conf['int']);

	$ops['page'] = wrap_static('page');
	return $ops;
}

/**
 * checks if request is valid while accessing a restricted table
 *
 * @param mixed $action check if this action is matching
 * @global array $zz_conf
 * @return bool 
 *		true: request is valid
 *		false: request is invalid (or no restriction is in place)
 */
function zz_valid_request($action = false) {
	global $zz_conf;
	static $dont_log_error = false;

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
	
	if ($_GET['zzhash'] !== zzform_secret_key()) {
		if (!$dont_log_error) {
			zz_error_log([
				'msg_dev' => 'Hash of script and ID differs from secret key (hash %s, secret %s).',
				'msg_dev_args' => [$_GET['zzhash'], zzform_secret_key()],
				'level' => E_USER_NOTICE
			]);
			$dont_log_error = true;
		}
		// remove invalid parameters from URL (for XHR requests)
		zzform_url_remove(['zzhash', 'insert', 'update']);
		$zz_conf['int']['id']['value'] = false;
		return false;
	}
	return true;
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

	// include core functions
	zzform_includes();

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
			wrap_setting('access_global', true);
		} else {
			// inside the first call
			$zz_conf['generate_output'] = $old_conf['generate_output'] ?? true;
			$zz_conf['multi'] = false;
			// @todo this disables global access set in other scripts, too
			wrap_setting('access_global', false);
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

	zz_error_exit(false);
	zz_error_out(false);

	// Modules on project level
	// debug module must come first because of debugging reasons!
	zz_modules('', $zz_conf['int_modules']);
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	// stop if there were errors while adding modules
	if (zz_error_exit()) zz_return(false);

	$default['multi'] 				= false;		// zzform_multi
	
	zz_write_conf($default);
	
	zz_initialize_int();
	if ($zz_conf['generate_output'])
		zz_init_referer();

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
 * initalize internal variables
 * will be created new for each call, are not visible to the outside
 *
 * @global array $zz_conf
 */
function zz_initialize_int() {
	global $zz_conf;

	// initialize internal variables
	$zz_conf['int'] = [];
	zzform_secret_key(NULL, 'write');
}

/**
 * include $zz-table definition and accept changes for $zz_conf
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
	if ($query = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY)) {
		parse_str($query, $query);
		$_GET = array_merge($_GET, $query);
	}
	return true;
}

/**
 * includes required files for zzform
 */
function zzform_includes() {
	static $included = NULL;
	if ($included) return;

	wrap_include('zzform/definition'); // also done in zzbrick/form, here for zzform_multi()
	wrap_include('zzform/helpers'); // also done in zzbrick/form, here for zzform_multi()
	wrap_include('configuration', 'zzform');
	wrap_include('errorhandling', 'zzform');
	wrap_include('format', 'zzform');
	wrap_include('functions', 'zzform');
	wrap_include('language', 'zzform');
	wrap_include('url', 'zzform');
	wrap_include('database', 'zzform');
	wrap_include('sql', 'zzform');
	wrap_include('state', 'zzform');
	$included = true;
}

/**
 * write $zz['setting'] to wrap_setting()
 *
 * allow some settings to be applied for batch operations
 * @param array $setting
 * @return array
 */
function zzform_setting($setting) {
	global $zz_conf;
	static $zzform_settings = [];
	if (!$setting) return [];
	if (!empty($zz_conf['multi']) AND !$zzform_settings)
		$zzform_settings = wrap_cfg_files('settings', ['package' => 'zzform']);

	$old_settings = [];
	foreach ($setting as $key => $value) {
		// are there any changes?
		if (wrap_setting($key) === $value) continue;
		// inside batch operations, only allow to change some settings
		if (!empty($zz_conf['multi']) AND empty($zzform_settings[$key]['batch_setting'])) continue;
		// save old setting, write new setting
		$old_settings[$key] = wrap_setting($key);
		wrap_setting($key, $value);
	}
	return $old_settings;
}
