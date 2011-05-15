<?php 

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2004-2010
// Core

/*

zzform

This script (c) Copyright 2004-2010 Gustaf Mossakowski, gustaf@koenige.org
No use without permission. The use of this product is restricted
to what has been agreed on in written or spoken form beforehands. If nothing 
has been explicitly said about the use, these scripts may not be used for a
different database than originally intended.

required: at least PHP 4.1.2 
lower PHP versions have not been tested
- mysql_real_escape_string: above 4.3 (addslashes will be used in PHP versions prior)
- exif_read_info: above 4.2 (no exif data will be read in PHP versions prior)


List of functions in this file

	zzform()				main zzform function
		zz_initialize()		sets defaults, imports modules, reads URI

	zzform_multi()			multi edit for zzform, e. g. import

*/

//	Required Variables, global so they can be used by the including script after
//	processing as well

global $zz_conf;	// Config variables

//	include deprecated page function, it's recommended to use zzbrick instead
if (file_exists($zz_conf['dir'].'/page.inc.php'))
	require_once $zz_conf['dir'].'/page.inc.php';
elseif (file_exists($zz_conf['dir'].'/inc/page.inc.php'))
	require_once $zz_conf['dir'].'/inc/page.inc.php';


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

	// check if POST is too big, then it will be empty
	$post_too_big = zzform_post_too_big();

	// Modules dependent on $zz-table definition
	$modules = array('translations', 'conditions', 'geo', 'export', 'upload');
	foreach ($modules as $index => $module) {
		if (!empty($zz_conf['modules'][$module])) continue; // module already loaded
		$zz_conf['modules'][$module] = false;
		switch ($module) {
		case 'translations':
			if (empty($zz_conf['translations_of_fields'])) unset($modules[$index]);
			break;
		case 'conditions':
			if (empty($zz['conditions'])) unset($modules[$index]);
			break;
		case 'geo':
			if (!zz_module_fieldcheck($zz, 'number_type', 'latitude')
				AND !zz_module_fieldcheck($zz, 'number_type', 'longitude')) unset($modules[$index]);
			break;
		case 'export':
			$export = false;
			if (!empty($zz_conf['export'])) {
				$export = true;
				break;
			}
			if (!empty($zz_conf['conditions'])) {
				foreach ($zz_conf['conditions'] as $condition) {
					if (!empty($condition['export'])) {
						$export = true;
						break;
					}
				}
			}
			if (!$export) unset($modules[$index]);
			break;
		case 'upload':
			if ($post_too_big) break; // there was an upload, we need this module
			if (!empty($_FILES)) break; // there was an upload, we need this module
			if (!zz_module_fieldcheck($zz, 'type', 'upload_image')) unset($modules[$index]);
			break;
		}
	}
	$zz_conf['modules'] = array_merge($zz_conf['modules'], zz_add_modules($modules, $zz_conf['dir_inc']));
	if (!empty($GLOBALS['zz_saved']['conf'])) {
		$GLOBALS['zz_saved']['conf']['modules'] = $zz_conf['modules'];
	}

	if ($zz_conf['zzform_calls'] > 1 AND empty($zz_conf['multi'])) { 
		// show a warning only if zzform is not explicitly called via zzform_multi()
		$zz_error[] = array(
			'msg_dev' => 'zzform has been called as a function more than once. You might want to check if this is correct.',
			'level' => E_USER_NOTICE
		);
		zz_error();
		$ops['output'] .= zz_error_output();
	}
	
	// get hash from $zz and $zz_conf to get a unique identification of
	// the settings, e. g. to save time for zzform_multi() or to get a
	// secret key for some cases
	$zz_conf['int']['hash'] = zz_hash($zz, $zz_conf);

//
//	Database connection, set db_name
//

	list($zz_tab[0]['db_name'], $zz['table']) = zz_db_connection($zz['table']);
	if (!$zz_tab[0]['db_name']) return zzform_exit($ops); // exits script

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
	if ($zz_conf['multi']) {
		foreach ($zz_error as $index => $error) {
			if (!is_numeric($index)) continue;
			if (empty($error['msg_dev'])) continue;
			$ops['error'][] = $error['msg_dev'];
		}
	}

	// mode won't be changed anymore before record operations

	// action won't be changed before record operations
	// (there it will be changed depending on outcome of db operations)
	// so we can set it to $zz_tab

	if ($zz_conf['show_record'])
		$zz_tab[0][0]['action'] = $zz_var['action'];
	
	// check if we are in export mode
	/* under development
	if ($zz_conf['access'] == 'export') {
		$zz['filetype'] = $zz_conf['export_filetypes'][0]; // default filetype for export
		if (!empty($_GET['export'])) { // get filetype for export
			if (in_array($_GET['export'], $zz_conf['export_filetypes']))
				$zz['filetype'] = $_GET['export'];
		} elseif (!empty($_GET['filetype'])) // get filetype for export
			if (in_array($_GET['filetype'], $zz_conf['export_filetypes']))
				$zz['filetype'] = $_GET['filetype'];
	}
	*/

	if (!$zz_conf['show_record']) unset($zz_conf['upload']); // values are not needed

//	Required files

	if ($zz_conf['show_record']) {
		require_once $zz_conf['dir_inc'].'/record.inc.php';		// Form
	}
	if ($zz_conf['modules']['debug']) zz_debug('required files included');
	

//	Optional files

	if (!function_exists('datum_de')) include_once $zz_conf['dir_inc'].'/numbers.inc.php';
	if (file_exists($zz_conf['dir_inc'].'/forcefilename-'.$zz_conf['character_set'].'.inc.php'))
		include_once $zz_conf['dir_inc'].'/forcefilename-'.$zz_conf['character_set'].'.inc.php';

	if ($zz_conf['modules']['debug']) zz_debug('files and database connection ok');


//	Variables

	if ($zz_conf['access'] != 'export') {
		zz_error();
		$ops['output'] .= zz_error_output(); // initialise zz_error
	}	

	$zz_conf['heading'] = !isset($zz_conf['heading']) ? zz_form_heading($zz['table']) : $zz_conf['heading'];
	if ($zz_conf['multilang_fieldnames'])
		$zz_conf['heading'] = zz_text($zz_conf['heading']);

	//	Translation module
	//	check whether or not to include default translation subtables
	//	this will be done after conditions were checked for so to be able
	//	to not include certain fields and not to get translation fields for these
	if ($zz_conf['modules']['translations']) {
		$zz['fields'] = zz_translations_init($zz['table'], $zz['fields']);
		if ($zz_error['error']) {
			return zzform_exit($ops); // if an error occured in zz_translations_check_for, return
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

	if (!empty($_GET['group'])) {
		foreach ($zz['fields'] as $field)
			if ((isset($field['display_field']) && $field['display_field'] == $_GET['group'])
				OR (isset($field['field_name']) && $field['field_name'] == $_GET['group'])
			) {
				if (isset($field['order'])) $zz_conf['group'] = $field['order'];
				else $zz_conf['group'] = $_GET['group'];
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
	$zz['fields_in_list'] = $zz['fields']; 

	if ($zz_conf['show_record']) {
		if (!empty($zz_conf['modules']['conditions']) AND !empty($zz_conditions['values'])
			AND !empty($zz['conditional_fields']))
			$zz['fields'] = zz_conditions_record_fields($zz['fields'], $zz['conditional_fields'], $zz_conditions['values']);
		
		// check if there are any bool-conditions 
		if (!empty($zz_conf['modules']['conditions']) AND !empty($zz_conditions['bool'])) {
			foreach (array_keys($zz['fields']) as $no) {
				if (!empty($zz['fields'][$no]['conditions'])) {
					$zz['fields'][$no] = zz_conditions_merge($zz['fields'][$no], $zz_conditions['bool'], $zz_var['id']['value']);
				}
				if (!empty($zz['fields'][$no]['not_conditions'])) {
					$zz['fields'][$no] = zz_conditions_merge($zz['fields'][$no], $zz_conditions['bool'], $zz_var['id']['value'], true);
				}
			}
		} elseif (!empty($zz_conf['modules']['conditions']) AND !empty($zz_conditions['values'])
			AND $zz_conf['modules']['debug']) {
			zz_debug('Notice: `values`-condition was set, but there\'s no `conditional_field`! (Waste of ressources)');
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
	if ($zz_conf['access'] != 'export') {
		// make nicer headings
		$zz_conf['heading'] = zz_nice_headings($zz_conf['heading'], $zz['fields'], $zz_var['where_condition']);
		// provisional title, in case errors occur
		$zz_conf['title'] = strip_tags($zz_conf['heading']);
		if (trim($zz_conf['heading']))
			$ops['output'].= "\n".'<h1>'.$zz_conf['heading'].'</h1>'."\n\n";
		if ($zz_conf['heading_text'] 
			AND (!$zz_conf['heading_text_hidden_while_editing'] OR $ops['mode'] == 'list_only')) 
			$ops['output'] .= $zz_conf['heading_text'];
		if ($post_too_big) {
			$zz_error[] = array(
				'msg' => zz_text('Transfer failed. Probably you sent a file that was too large.').'<br>'
					.zz_text('Maximum allowed filesize is').' '
					.zz_format_bytes($zz_conf['upload_MAX_FILE_SIZE']).' &#8211; '
					.sprintf(zz_text('You sent: %s data.'), zz_format_bytes($_SERVER['CONTENT_LENGTH'])),
				'level' => E_USER_WARNING
			);
		}
		zz_error();
		$ops['output'] .= zz_error_output();

		$selection = zz_nice_selection($zz['fields']);
		if ($selection)
			$ops['output'].= "\n".'<h2>'.$selection.'</h2>'."\n\n";
	}

	if ($zz_conf['show_record']) {
	//	Upload

		if (in_array('upload', $zz_conf['modules']) && $zz_conf['modules']['upload'])
			if ($zz_conf['upload_MAX_FILE_SIZE'] > ZZ_UPLOAD_INI_MAXFILESIZE) {
				$zz_error[] = array(
					'msg_dev' => 'Value for upload_max_filesize from php.ini is '
						.'smaller than value which is set in the script. The '
						.'value from php.ini will be used. To upload bigger files'
						.', please adjust your configuration settings.',
					'level' => E_USER_NOTICE
				);
				$zz_conf['upload_MAX_FILE_SIZE'] = ZZ_UPLOAD_INI_MAXFILESIZE;
			}

		$validation = true;

		// ### variables for main table will be saved in zz_tab[0]
		$zz_tab[0][0]['fields'] = $zz['fields'];
		$zz_tab[0][0]['validation'] = true;
		$zz_tab[0][0]['record'] = false;
		$zz_tab[0][0]['access'] = (!empty($zz['access']) ? $zz['access'] : false);

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
			} else
				$zz_tab[0]['existing'][0] = array();

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

			// set defaults and values, clean up POST
			$zz_tab[0][0]['POST'] = zz_check_def_vals($_POST, $zz_tab[0][0]['fields'], $zz_tab[0]['existing'][0],
				(!empty($zz_var['where'][$zz_tab[0]['table']]) ? $zz_var['where'][$zz_tab[0]['table']] : ''));
			//  POST is secured, now get rid of password fields in case of error_log_post
			foreach ($zz['fields'] AS $field) {
				if (empty($field['type'])) continue;
				if ($field['type'] == 'password') unset($_POST[$field['field_name']]);
				if ($field['type'] == 'password_change') unset($_POST[$field['field_name']]);
			}
			// get rid of some POST values that are used at another place
			$internal_fields = array('MAX_FILE_SIZE', 'zz_check_select', 'zz_action',
				'zz_subtables', 'zz_subtable_deleted', 'zz_delete_file',
				'zz_referer', 'zz_save_record');
			foreach ($internal_fields as $key) unset($zz_tab[0][0]['POST'][$key]);
		}
	
	//	Start action
		$zz_var['record_action'] = false;
		if ($zz_var['action'] == 'insert' OR $zz_var['action'] == 'update' OR $zz_var['action'] == 'delete') {
			// check for validity, insert/update/delete record
			require_once $zz_conf['dir_inc'].'/action.inc.php';		// update/delete/insert
			list($ops, $zz_tab, $validation, $zz_var) = zz_action($ops, $zz_tab, $validation, $zz_var); 
			// if an error occured in zz_action, exit
			if ($zz_error['error']) return zzform_exit($ops); 
			// was action successful?
			if ($ops['result'] AND $zz_conf['generate_output']) {
				// Redirect, if wanted.
				if (!empty($zz_conf['redirect'][$ops['result']])) {
					if ($zz_conf['modules']['debug'] AND $zz_conf['debug_time']) {
						zz_debug_time($ops['return']);
					}
					if (is_array($zz_conf['redirect'][$ops['result']])) {
						$zz_conf['redirect'][$ops['result']] = zz_makepath($zz_conf['redirect'][$ops['result']], $zz_tab);
					}
					if (substr($zz_conf['redirect'][$ops['result']], 0, 1) == '/') {
						$zz_conf['redirect'][$ops['result']] = $zz_conf['int']['url']['base']
							.$zz_conf['redirect'][$ops['result']];
					}
					header('Location: '.$zz_conf['redirect'][$ops['result']]);
					exit;
				} elseif (!$zz_conf['debug'] AND $zz_conf['redirect_on_change']) {
				// redirect to same URL, don't do so in case of debugging
				// as to protect against reloading the POST variables
					$self = $zz_conf['int']['url']['full']
						.$zz_conf['int']['url']['qs'].$zz_conf['int']['url']['qs_zzform']
						.($zz_conf['int']['url']['qs_zzform'] ? '&' : $zz_conf['int']['url']['?&'])
						.'zzaction=';
					$secure = false;
					if (!empty($zz_conf['int']['hash_id'])) {
						$secure = '&zzhash='.$zz_conf['int']['secret_key'];
					}
					switch ($ops['result']) {
					case 'successful_delete':
						header('Location: '.$self.'delete');
						exit;
					case 'successful_insert':
						header('Location: '.$self.'insert&id='.$zz_var['id']['value'].$secure);
						exit;
					case 'successful_update':
						header('Location: '.$self.'update&id='.$zz_var['id']['value'].$secure);
						exit;
					case 'no_update':
						header('Location: '.$self.'noupdate&id='.$zz_var['id']['value'].$secure);
						exit;
					default:
						break;
					}
				}
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

		$zz_var['extraGET'] = zz_extra_get_params($ops['mode'], $zz_conf);

		if ($zz_conf['generate_output']) {
			// display updated, added or editable Record
			$ops['output'] .= zz_record($ops, $zz_tab, $zz_var, $zz_conditions);	
		}

	} else {
		// call error function if there's anything
		$zz_var['extraGET'] = zz_extra_get_params($ops['mode'], $zz_conf);
		zz_error();
		$ops['output'] .= zz_error_output();
	}

	if ($zz_conf['show_list']) {
		// set 'selection', $zz_conf['show_hierarchy']
		zz_apply_filter();

		// filter
		if (!empty($zz_conf['filter']) AND $zz_conf['access'] != 'export'
			AND in_array($zz_conf['filter_position'], array('top', 'both')))
			$ops['output'] .= zz_filter_selection($zz_conf['filter']);
	}

	if ($ops['mode'] != 'add' && $zz_conf['add_link'] && !is_array($zz_conf['add'])
		&& $zz_conf['access'] != 'export') {
		$toolsline = array();
		$toolsline[] = '<a accesskey="n" href="'.$zz_conf['int']['url']['self']
			.$zz_conf['int']['url']['qs'].$zz_conf['int']['url']['?&'].'mode=add'
			.$zz_var['extraGET'].'">'.zz_text('Add new record').'</a>';
		if ($zz_conf['import']) {
			$toolsline[] = '<a href="'
				.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs']
				.$zz_conf['int']['url']['?&'].'mode=import'.$zz_var['extraGET'].'">'
				.zz_text('Import data').'</a>';
		}
		if ($toolsline) {
			$ops['output'] .= '<p class="add-new">'.implode(' | ', $toolsline).'</p>'."\n";
		}
	}

	if ($zz_conf['backlink']) {
		if (!empty($zz_conf['dynamic_referer'])) {
			if (empty($zz_tab[0][0]['id'])) $zz_tab[0][0]['id'] = $zz_var['id'];
			$ops['output'].= '<p id="back-overview"><a href="'
				.zz_makepath($zz_conf['dynamic_referer'], $zz_tab, 'new', 'local')
				.'">'.zz_text('back-to-overview').'</a></p>'."\n";
		} elseif ($zz_conf['referer'])
			$ops['output'].= '<p id="back-overview"><a href="'.$zz_conf['int']['referer_esc'].'">'
				.zz_text('back-to-overview').'</a></p>'."\n";
	}
	
	if ($zz_conf['show_list']) {
		// shows table with all records (limited by zz_conf['limit'])
		// and add/nav if limit/search buttons
		require_once $zz_conf['dir_inc'].'/list.inc.php';		// Table output with all records
		list($ops, $zz_var) = zz_list($zz, $ops, $zz_var, $zz_conditions); 
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
	$ops['output'] .= zz_error_output();
	$ops['critical_error'] = $zz_error['error'] ? true : false;
	$ops['error_mail'] = array();
	if (!empty($zz_conf['int']['error']))
		$ops['error_mail'] = $zz_conf['int']['error'];

	// return to old database
	if ($zz_conf['int']['db_current']) mysql_select_db($zz_conf['int']['db_current']);

	// debug time only if there's a result and before leaving the page
	if ($ops['result'] AND $zz_conf['modules']['debug'] AND $zz_conf['debug_time']) {
		zz_debug_time($ops['return']);
	}
	// end debug mode
	if ($zz_conf['modules']['debug']) {
		zz_debug('end');
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

	// get rid of internal variables
	unset($zz_conf['int']);

	return $ops;
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
	$zz_conf['int']['allowed_params']['mode'] = array('edit', 'delete', 'show', 'add', 'review', 'list_only');
	$zz_conf['int']['allowed_params']['action'] = array('insert', 'delete', 'update'); // review is for internal use only
	
	// Configuration on project level: Core defaults and functions
	$zz_default['character_set']	= 'utf-8';					// character set
	$zz_default['dir_ext']			= $zz_conf['dir'].'/ext';	// directory for extensions
	$zz_default['dir_custom']		= $zz_conf['dir'].'/local';
	$zz_default['dir_inc']			= $zz_conf['dir'].'/inc';
	$zz_default['error_mail_level']	= array('error', 'warning', 'notice');
	$zz_default['ext_modules']		= array('markdown', 'textile');
	$zz_default['int_modules'] 		= array('debug', 'compatibility', 'validate');
	zz_write_defaults($zz_default, $zz_conf);

	// Configuration on project level: shorthand values
	if (!is_array($zz_conf['error_mail_level'])) {
		if ($zz_conf['error_mail_level'] == 'error')
			$zz_conf['error_mail_level'] = array('error');
		elseif ($zz_conf['error_mail_level'] == 'warning')
			$zz_conf['error_mail_level'] = array('error', 'warning');
		elseif ($zz_conf['error_mail_level'] == 'notice')
			$zz_conf['error_mail_level'] = array('error', 'warning', 'notice');
	}
	require_once $zz_conf['dir_inc'].'/functions.inc.php';		// include core functions

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
	$zz_default['generate_output']		= true;
	$zz_default['group_html_table']		= '<strong>%s</strong>';
	$zz_default['heading_text'] 		= '';
	$zz_default['heading_text_hidden_while_editing'] 	= false;
	$zz_default['limit']				= false;	// only n records are shown at once
	$zz_default['limit_show_range'] 	= 800;		// range in which links to records around current selection will be shown
	$zz_default['limit_display']		= 'pages';
	$zz_default['list_display']			= 'table';
	$zz_default['logging'] 				= false;	//	if logging should occur, turned off by default 
	$zz_default['logging_table'] 		= '_logging';	// name of table where INSERT, DELETE and UPDATE actions will be logged
	$zz_default['max_detail_records']	= 20;		// max 20 detail records, might be expanded later on
	$zz_default['max_select_val_len']	= 60;		// maximum length of values in select
	$zz_default['max_select'] 			= 60;		// maximum entries in select/option, if bigger than sub-select
	$zz_default['min_detail_records']	= 0;		// min 0 detail records, might be expanded later on
	$zz_default['multi'] 				= false;		// zzform_multi
	$zz_default['multilang_fieldnames'] = false;	// translate fieldnames via zz_text($fieldname)
	$zz_default['password_salt']		= '';
	$zz_default['password_encryption']	= 'md5';
	$zz_default['prefix'] 				= false;	//	prefix for ALL tables like zz_
	$zz_default['project']				= htmlspecialchars($_SERVER['HTTP_HOST']);
	$zz_default['redirect']['successful_delete'] = false;	// redirect to diff. page after delete
	$zz_default['redirect']['successful_insert'] = false;	// redirect to diff. page after insert
	$zz_default['redirect']['successful_update'] = false;	// redirect to diff. page after update
	$zz_default['redirect']['no_update'] = false;	// redirect to diff. page after update without changes
	$zz_default['redirect_on_change']	= true;
	$zz_default['relations_table'] 		= '_relations';	//	name of relations table for referential integrity
	$zz_default['search'] 				= false;	// search for records possible or not
	$zz_default['select_multiple_records'] = false;
	$zz_default['show_hierarchy']	= false;
	$zz_default['show_list_while_edit'] = true;	
	$zz_default['show_list']		= true;		// display list of records in database				
	$zz_default['show_output']		= true;		// ECHO output or keep it in $ops['output']
	$zz_default['tfoot']			= false;  	// shows table foot, e. g. for sums of individual values
	$zz_default['title_separator']	= ' &#8211; ';
	$zz_default['user']				= '';		//	username
	$zz_default['view']				= false;	// 	show Action: View
	$zz_default['password_encryption'] = 'md5';
	$zz_default['translate_log_encodings'] = array(
		'iso-8859-2' => 'iso-8859-1'
	);
	$zz_default['url_self']			= false;
	
	zz_write_defaults($zz_default, $zz_conf);

	// set default limit in case 'show_hierarchy' is used because hierarchies need more memory
	if (!$zz_conf['limit'] AND $zz_conf['show_hierarchy']) $zz_conf['limit'] = 40;

	$zz_conf['int']['url'] = zz_get_url_self($zz_conf['url_self']);

	// get LIMIT from URI
	if (!$zz_conf['int']['this_limit'] && $zz_conf['limit']) 
		$zz_conf['int']['this_limit'] = $zz_conf['limit'];
	if (isset($_GET['limit']) && is_numeric($_GET['limit']))	
		$zz_conf['int']['this_limit'] = (int) $_GET['limit'];
	
	// don't show list in case 'nolist' parameter is set
	if (isset($_GET['nolist'])) $zz_conf['show_list'] = false;

	// get referer // TODO: add support for SESSIONs as well
	if (!isset($zz_conf['referer'])) {
		$zz_conf['referer'] = false;
		if (isset($_GET['referer'])) $zz_conf['referer'] = $_GET['referer'];
		if (isset($_POST['zz_referer'])) $zz_conf['referer'] = $_POST['zz_referer'];
	} elseif (isset($_POST['zz_referer']))
		$zz_conf['referer'] = $_POST['zz_referer'];
	elseif (isset($_SERVER['HTTP_REFERER']))
		$zz_conf['referer'] = $_SERVER['HTTP_REFERER'];
	// remove 'zzaction' from referer if set
	$zz_conf['referer'] = parse_url($zz_conf['referer']);
	if (!empty($zz_conf['referer']['query'])) {
		$zz_conf['referer']['query'] = zz_edit_query_string($zz_conf['referer']['query'], array('zzaction'));
	}
	$zz_conf['referer'] = (
		(!empty($zz_conf['referer']['scheme']) ? $zz_conf['referer']['scheme'].'://'
		.$zz_conf['referer']['host'] : '').$zz_conf['referer']['path']
		.(!empty($zz_conf['referer']['query']) ? $zz_conf['referer']['query'] : ''));
	$zz_conf['int']['referer_esc'] = str_replace('&', '&amp;', $zz_conf['referer']);

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

	switch ($type) {
	case 'form': // hand back form to user, just fill out values
		$ops = array();
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
		$ops = array();
		// TODO: so far, we have no support for 'values' for subrecords
		// TODO: zzform() and zzform_multi() called within an action-script
		// causes not all zz_conf variables to be reset
		zz_initialize('overwrite');
//		if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__)
		$zz_conf['show_output'] = false; // do not show output as it will be included after page head
		$zz_conf['show_list'] = false;	// no output, so list view is not necessary
		$zz_conf['multi'] = true;		// so we know the operation mode for other scripts
		if (!empty($values['GET'])) $_GET = $values['GET'];
		if (!empty($values['POST'])) $_POST = $values['POST'];
		if (!empty($values['FILES'])) $_FILES = $values['FILES'];
		else $_FILES = array();
		$zz_conf['generate_output'] = false; // don't generate output
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
		require_once $zz_conf['dir_inc'].'/functions.inc.php';
		require_once $zz_conf['dir_inc'].'/import.inc.php';
		require_once $zz_conf['dir_inc'].'/forcefilename-'.$zz_conf['character_set'].'.inc.php';
		$ops['output'] = zz_import_files($definition_file, $values, $params);
		return $ops['output'];
	}
	
	// clean up, $zz must remain intact!
	unset($_GET);
	unset($_POST);
	unset($_FILES);

	// create new $_GET, $_POST and $_FILES from $values as needed
//	$actions = array();	// for each call, create one action


//	foreach ($actions as $action) {
//		$zz_conf = $global_zz_conf;	// get clean $zz_conf without changes from different zzform calls or included scripts
//		zzform();					// Funktion aufrufen
		
		// on success: remove entry in csv file
		// on success: delete files, move files ...
		// on failure: output record, output error ?
//	}
	// TODO: export, might go into extra file?

	// what to return:
	// array whith all record_ids that were inserted, sorted by operation (so to include subrecords)

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

?>