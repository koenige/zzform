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

	zzform_all				(optional call, if page['head'] and ['foot'] shall
							be incorporated)

		zzform				main zzform function

			zz_initialize		sets defaults, imports modules, reads URI
			microtime_float		microtimer, for debugging

	zzform_multi			multi edit for zzform, e. g. import

*/

//	Required Variables, global so they can be used by the including script after
//	processing as well

global $zz;			// Table description
global $zz_conf;	// Config variables
global $zz_page;	// Page (Layout) variables


/**
 * zzform generates forms for editing single records, list views with several
 * records and does insert, update and delete database operations
 *
 * @global float $zz_timer microtime when zzform was started
 * @global array $zz_debug see zz_debug()
 * @global array $zz
 * @global array $zz_conf
 * @global array $zz_error
 * @global array $text
 */
function zzform() {
	global $zz;			// Table description
	global $zz_conf;	// Config variables
	global $zz_timer;	// debug module
	global $zz_debug;	// debug module

	$zz_timer = microtime_float();	// debug module
	$zz_debug = array();			// debug module

	// This variable signals that zzform is included
	if (empty($zz_conf['zzform_calls'])) $zz_conf['zzform_calls'] = 1;
	else $zz_conf['zzform_calls']++;

	// divert to import if set
	if (!empty($_GET['mode']) AND $_GET['mode'] == 'import' AND !empty($zz_conf['import'])) {
		if (empty($zz_conf['dir_inc'])) 
			$zz_conf['dir_inc'] = $zz_conf['dir'].'/inc';
		require_once $zz_conf['dir_inc'].'/import.inc.php';
		return zzform_exit(zzform_import());
	}

//	Variables which are required by several functions
	global $zz_error;
	$zz_error = array();
	$zz_error['error'] = false;	// if true, exit script immediately
	global $text;
	
//
//	Default Configuration
//

//	initialize variables
	$zz['result'] = false;
	$zz['headers'] = false;
	$zz['output'] = false;
	$zz['error'] = array();
	$zz_tab = array();

	// set default configuration variables
	// import modules
	// set and get URI
	zz_initialize();
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if ($zz_error['error']) {
		return zzform_exit(zz_error()); // exits script
	}


	if ($zz_conf['zzform_calls'] > 1 AND empty($zz_conf['multi'])) { 
		// show a warning only if zzform is not explicitly called via zzform_multi()
		$zz_error[] = array(
			'msg_dev' => 'zzform has been called as a function more than once. You might want to check if this is correct.',
			'level' => E_USER_NOTICE
		);
		$zz['output'] .= zz_error();
	}

//
//	Database connection, set db_name
//

	list($zz_tab[0]['db_name'], $zz['table']) = zz_db_connection($zz['table']);
	if (!$zz_tab[0]['db_name']) return zzform_exit(zz_error()); // exits script

//
//	Filter, WHERE, ID
//

	// check GET 'filter', set $zz_conf['show_hierarchy']
	zz_apply_filter();

	// get 'where_conditions' for SQL query from GET add, filter oder where
	// get 'zz_fields' from GET add
	$zz_var = zz_get_where_conditions();

	// get 'unique_fields', especially 'id' = PRIMARY KEY
	$zz_var = zz_get_unique_fields($zz_var, $zz['fields']);
	// exit if there's something wrong with the table definition
	if (!$zz_var) return zzform_exit(zz_error());

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
	$zz['mode'] = false;		// mode: what form/view is presented to the user
	$zz_var['action'] = false;	// action: what database operations are to be done

	// initalize export module
	// might set mode to export
	if (!empty($zz_conf['export'])) zz_export_init();

	// set $zz['mode'], $zz_var['action'], ['id']['value'] and $zz_conf for access
	list($zz, $zz_var) = zz_record_access($zz, $zz_var);
	if ($zz_conf['multi']) {
		foreach ($zz_error as $index => $error) {
			if (!is_numeric($index)) continue;
			if (empty($error['msg_dev'])) continue;
			$zz['error'][] = $error['msg_dev'];
		}
	}

	// mode won't be changed anymore before record operations

	// action won't be changed before record operations
	// (there it will be changed depending on outcome of db operations)
	// so we can set it to $zz_tab

	if ($zz_conf['show_record'])
		$zz_tab[0][0]['action'] = $zz_var['action'];
	
	// check if we are in export mode
	if ($zz_conf['access'] == 'export') {
		$zz['filetype'] = $zz_conf['export_filetypes'][0]; // default filetype for export
		if (!empty($_GET['export'])) { // get filetype for export
			if (in_array($_GET['export'], $zz_conf['export_filetypes']))
				$zz['filetype'] = $_GET['export'];
		} elseif (!empty($_GET['filetype'])) // get filetype for export
			if (in_array($_GET['filetype'], $zz_conf['export_filetypes']))
				$zz['filetype'] = $_GET['filetype'];
	}

	if (!$zz_conf['show_record']) unset($zz_conf['upload']); // values are not needed

	// Turn off hierarchical sorting when using search
	// TODO: implement hierarchical view even when using search
	if (!empty($_GET['q']) AND $zz_conf['search'] AND $zz_conf['show_hierarchy']) {
		$zz_conf['show_hierarchy'] = false;
	}
	

//	Required files

	if ($zz_conf['show_record']) {
		require_once $zz_conf['dir_inc'].'/record.inc.php';		// Form
	}
	require $zz_conf['dir_inc'].'/text-en.inc.php';					// English text
	if ($zz_conf['additional_text'] AND file_exists($langfile = $zz_conf['lang_dir'].'/text-en.inc.php')) 
		include $langfile; // must not be include_once since $text is cleared beforehands

	if ($zz_conf['modules']['debug']) zz_debug('required files included');
	

//	Optional files

	if (isset($zz_conf['language']) && $zz_conf['language'] != 'en') {	// text in other languages
		$langfile = $zz_conf['dir_inc'].'/text-'.$zz_conf['language'].'.inc.php';
		if (file_exists($langfile)) include $langfile;
		else {
			$zz_error[] = array(
				'msg_dev' => sprintf(zz_text('No language file for "%s" found. Using English instead.'), 
					'<strong>'.$zz_conf['language'].'</strong>'),
				'level' => E_USER_NOTICE
			);
		}
		if ($zz_conf['additional_text'] AND file_exists($langfile = $zz_conf['lang_dir']
			.'/text-'.$zz_conf['language'].'.inc.php')) {
			include $langfile; // must not be include_once since $text is cleared beforehands
		}
	}
	if (!empty($zz_conf['text'][$zz_conf['language']])) {
		$text = array_merge($text, $zz_conf['text'][$zz_conf['language']]);
	}
	// todo: if file exists else lang = en

	if (!function_exists('datum_de')) include_once $zz_conf['dir_inc'].'/numbers.inc.php';
	if (file_exists($zz_conf['dir_inc'].'/forcefilename-'.$zz_conf['character_set'].'.inc.php'))
		include_once $zz_conf['dir_inc'].'/forcefilename-'.$zz_conf['character_set'].'.inc.php';

	if ($zz_conf['modules']['debug']) zz_debug('files and database connection ok');


//	Variables

	if ($zz_conf['access'] != 'export') {
		$zz['output'].= '<div id="zzform">'."\n";
		$zz['output'].= zz_error(); // initialise zz_error
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
			if ($zz_conf['access'] != 'export') $zz['output'].= '</div>';
			return zzform_exit(); // if an error occured in zz_translations_check_for, return false
		}
	}

	foreach ($zz['fields'] as $field) {
		// get write once fields so we can base conditions (scope=values) on them
		if (!empty($field['type']) AND $field['type'] == 'write_once' 
			AND ($zz['mode'] != 'add' AND $zz_var['action'] != 'insert')) { 
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
	if (!$zz_var['action'] AND (!$zz['mode'] OR $zz['mode'] == 'list_only'))
		unset($zz_var['zz_fields']);

	if ($zz_conf['show_record']) {
		// ### variables for main table will be saved in zz_tab[0]
		$zz_tab[0]['table'] = $zz['table'];
		$zz_tab[0]['table_name'] = $zz['table'];
		$zz_tab[0]['sql'] = $zz['sql'].(!empty($zz['sqlorder']) ? ' '.$zz['sqlorder'] : '');
	}
	
	// only if search is allowed and there is something
	// if q modify $zz['sql']: add search query
	if (!empty($_GET['q']) AND $zz_conf['search']) 
		$zz['sql'] = zz_search_sql($zz['fields'], $zz['sql'], $zz['table'], $zz_var['id']['field_name']);	

//	Add, Update or Delete

	// conditions for list view will be set later
	$zz['fields_in_list'] = $zz['fields']; 

	if ($zz_conf['modules']['debug']) zz_debug("start conditions");

	// Module 'conditions': evaluate conditions
	if (!empty($zz_conf['modules']['conditions']) AND !empty($zz['conditions']))
		$zz_conditions = zz_conditions_record_check($zz, $zz_var);
	else
		$zz_conditions = array();

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
	$zz['fields'] = zz_fill_out($zz['fields'], $zz_tab[0]['db_name'].'.'.$zz['table'], false, $zz['mode']); 


//	page output
	if ($zz_conf['access'] != 'export') {
		// make nicer headings
		$zz_conf['heading'] = zz_nice_headings($zz_conf['heading'], $zz['fields'], $zz_var['where_condition']);
		// provisional title, in case errors occur
		$zz_conf['title'] = strip_tags($zz_conf['heading']);
		$zz['output'].= "\n".'<h1>'.$zz_conf['heading'].'</h1>'."\n\n";
		if ($zz_conf['heading_text'] 
			AND (!$zz_conf['heading_text_hidden_while_editing'] OR $zz['mode'] == 'list_only')) 
			$zz['output'] .= $zz_conf['heading_text'];
		$zz['output'].= zz_error();

		$selection = zz_nice_selection($zz['fields']);
		if ($selection)
			$zz['output'].= "\n".'<h2>'.$selection.'</h2>'."\n\n";
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
			$zz_tab[$tab] = zz_get_subrecords($zz, $zz['fields'][$no], $zz_tab[$tab], $zz_tab[0], $zz_var, $tab);
		}
		if ($zz_var['subtables'] && $zz_var['action'] != 'delete')
			if (isset($_POST['zz_subtables'])) $validation = false;

	
	// Upload
		// if there is a directory which has to be renamed, save old name in array
		// do the same if a file might be renamed, deleted ... via upload
		// or if there is a display or write_once field (so that it can be used
		// e. g. for identifiers):
		if ($zz_var['action'] == 'update' OR $zz_var['action'] == 'delete') {
			if (!empty($zz_conf['folder']) OR !empty($zz_var['upload_form']) 
				OR count($zz_var['save_old_record']) OR $zz_conf['get_old_record']) {
				zz_foldercheck_before($zz_tab);
				$zz_conf['get_old_record'] = true;
			}
			if (count($zz_var['save_old_record']) && !empty($zz_tab[0][0]['old_record'])) {
				foreach ($zz_var['save_old_record'] as $no) {
					if (!empty($zz_tab[0][0]['old_record'][$zz['fields'][$no]['field_name']])) {
						$_POST[$zz['fields'][$no]['field_name']] = $zz_tab[0][0]['old_record'][$zz['fields'][$no]['field_name']];
					}
				}
			}
		}
		if (!empty($_POST['zz_action'])) {
			// fields with values must still have values
			// TODO TODO
			$zz_tab[0]['existing'][0] = array();
			if (!empty($_POST[$zz_var['id']['field_name']]) AND $_POST['zz_action'] == 'update') {
				$sql = 'SELECT * 
					FROM `'.$zz_tab[0]['db_name'].'`.`'.$zz_tab[0]['table'].'`
					WHERE '.$zz_var['id']['field_name'].' = '.$_POST[$zz_var['id']['field_name']];
				$zz_tab[0]['existing'][0] = zz_db_fetch($sql);
			}
			$zz_tab[0][0]['POST'] = zz_check_def_vals($_POST, $zz_tab[0][0]['fields'], $zz_tab[0]['existing'][0],
				(!empty($zz_var['where'][$zz_tab[0]['table']]) ? $zz_var['where'][$zz_tab[0]['table']] : ''));
			// get rid of some POST values that are used at another place
			$internal_fields = array('MAX_FILE_SIZE', 'zz_check_select', 'zz_action',
				'zz_records', 'zz_subtables', 'zz_subtable_deleted', 'zz_delete_file',
				'zz_referer');
			foreach ($internal_fields as $key) unset($zz_tab[0][0]['POST'][$key]);
			if (!empty($_FILES)) 
				$_FILES = zz_check_def_files($_FILES);
		}
	
	//	Start action
		$zz_var['formhead'] = false;
		$zz_var['record_action'] = false;
		if ($zz_var['action'] == 'insert' OR $zz_var['action'] == 'update' OR $zz_var['action'] == 'delete') {
			// check for validity, insert/update/delete record
			require_once $zz_conf['dir_inc'].'/action.inc.php';		// update/delete/insert
			require_once $zz_conf['dir_inc'].'/validation.inc.php';	// Basic Validation
			list($zz, $zz_tab, $validation, $zz_var) = zz_action($zz, $zz_tab, $validation, $zz_var); 
			if ($zz_error['error']) {
				$zz['output'].= '</div>';
				return zzform_exit(); // if an error occured in zz_action, return false
			}
		}

	//	Query updated, added or editable record
	
		if (!$validation) {
			if ($zz_var['action'] == 'update') $zz['mode'] = 'edit';
			elseif ($zz_var['action'] == 'insert') $zz['mode'] = 'add';
			// this is from zz_access() but since mode has set, has to be
			// checked against again
			if (in_array($zz['mode'], array('edit', 'add')) 
				AND !$zz_conf['show_list_while_edit']) $zz_conf['show_list'] = false;
	
			foreach ($zz_var['subtables'] as $tab => $no) {
				$zz_tab[$tab] = zz_get_subrecords($zz, $zz['fields'][$no], $zz_tab[$tab], $zz_tab[0], $zz_var, $tab);
			}
		}
	
		if ($zz_conf['modules']['debug']) zz_debug('subtables end');

		// query record
		foreach (array_keys($zz_tab) as $tab) {
			foreach (array_keys($zz_tab[$tab]) as $rec) {
				if (!is_numeric($rec)) continue;
				$zz_tab[$tab] = zz_query_record($zz_tab[$tab], $rec, $validation, $zz['mode']);
			}
		}
		if ($zz_error['error']) {
			if ($zz_conf['access'] != 'export') $zz['output'].= '</div>';
			return zzform_exit(zz_error());
		}

		// there might be now a where value for this record
		if (!empty($zz_var['where'][$zz['table']])) {
			foreach ($zz_var['where'][$zz['table']] as $field_name => $value) {
				if ($value) continue;
				if (empty($zz_tab[0][0]['record'][$field_name])) continue;
				$zz_var['where'][$zz['table']][$field_name] = $zz_tab[0][0]['record'][$field_name];
			}
		}

		$zz_var['extraGET'] = zz_extra_get_params($zz['mode'], $zz_conf);

		if ($zz_conf['generate_output']) {
	//	Display Updated, Added or Editable Record
			$zz['output'] .= zz_record($zz, $zz_tab, $zz_var, $zz_conditions);	
		}
		unset ($zz_var['formhead']); // has served it's duty, good bye.

	} else {
		// call error function if there's anything
		$zz_var['extraGET'] = zz_extra_get_params($zz['mode'], $zz_conf);
		$zz['output'] .= zz_error();
	}

	// filter
	if (!empty($zz_conf['filter']) AND $zz_conf['access'] != 'export'
		AND in_array($zz_conf['filter_position'], array('top', 'both'))
		AND $zz_conf['show_list'])
		$zz['output'] .= zz_filter_selection($zz_conf['filter']);
	
	if ($zz['mode'] != 'add' && $zz_conf['add_link'] && !is_array($zz_conf['add'])
		&& $zz_conf['access'] != 'export') {
		$toolsline = array();
		$toolsline[] = '<a accesskey="n" href="'.$zz_conf['url_self']
			.$zz_conf['url_self_qs_base'].$zz_conf['url_append'].'mode=add'
			.$zz_var['extraGET'].'">'.zz_text('Add new record').'</a>';
		if ($zz_conf['import']) {
			$toolsline[] = '<a href="'
				.$zz_conf['url_self'].$zz_conf['url_self_qs_base']
				.$zz_conf['url_append'].'mode=import'.$zz_var['extraGET'].'">'
				.zz_text('Import data').'</a>';
		}
		if ($toolsline) {
			$zz['output'] .= '<p class="add-new">'.implode(' | ', $toolsline).'</p>'."\n";
		}
	}

	if ($zz_conf['backlink']) {
		if (!empty($zz_conf['dynamic_referer'])) {
			if (empty($zz_tab[0][0]['id'])) $zz_tab[0][0]['id'] = $zz_var['id'];
			$zz['output'].= '<p id="back-overview"><a href="'
				.zz_makepath($zz_conf['dynamic_referer'], $zz_tab, 'new', 'local')
				.'">'.zz_text('back-to-overview').'</a></p>'."\n";
		} elseif ($zz_conf['referer'])
			$zz['output'].= '<p id="back-overview"><a href="'.$zz_conf['referer_esc'].'">'
				.zz_text('back-to-overview').'</a></p>'."\n";
	}
	
	if ($zz_conf['show_list']) {
		// shows table with all records (limited by zz_conf['limit'])
		// and add/nav if limit/search buttons
		require_once $zz_conf['dir_inc'].'/list.inc.php';		// Table output with all records
		list($zz, $zz_var) = zz_list($zz, $zz_var, $zz_conditions); 
	}
	if ($zz_error['error']) {
		if ($zz_conf['access'] != 'export') $zz['output'].= '</div>';
		return zzform_exit(); // critical error: exit;
	}

	if ($zz_conf['modules']['debug']) {
		zz_debug("finished");
		if ($zz_conf['debug'] AND $zz_conf['access'] != 'export') {
			$zz['output'] .= '<div class="debug">'.zz_debug_htmlout($zz_debug).'</div>'."\n";
		}
	}
	if ($zz_conf['show_record'] AND $zz['result']) {
		// debug time only if there's a result and before leaving the page
		if ($zz_conf['modules']['debug'] AND $zz_conf['debug_time']) {
			$zz_error[] = array(
				'msg_dev' => '[DEBUG] time: '.implode(' ', $zz_debug['time']),
				'level' => E_USER_NOTICE
			);
			zz_error();
		}
		// Redirect, if wanted.
		if (!empty($zz_conf['redirect'][$zz['result']])) {
			if (substr($zz_conf['redirect'][$zz['result']], 0, 1) == '/') {
				$scheme = ((isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] == "on") ? 'https' : 'http');
				$zz_conf['redirect'][$zz['result']] = $scheme.'://'.$_SERVER['SERVER_NAME']
					.$zz_conf['redirect'][$zz['result']];
			}
			header('Location: '.$zz_conf['redirect'][$zz['result']]);
			exit;
		}
	}
	if ($zz_conf['access'] != 'export') {
		if ($zz_conf['footer_text']) $zz['output'].= $zz_conf['footer_text'];
		 $zz['output'].= '</div>';
	}
	// set title
	$zz_conf['title'] = zz_nice_title($zz_conf['heading'], $zz['fields'], $zz_var, $zz['mode']);
	// last time check for errors
	$zz['output'] .= zz_error();
	if ($zz_conf['show_output']) echo $zz['output'];
//	if ($zz_var['record_action']) {
//		$scheme = ((isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] == "on") ? 'https' : 'http');
//		übergabe via SESSION, so dass bestätiger Datensatz angezeigt wird.
//		header('Location: '.$scheme.'://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']); // Parameter, die bestätigten Record anzeigen
//	}
	return zzform_exit(true);
}

/** 
 * exit function for zzform, will always be called to adjust some settings
 *
 * @param mixed $return return parameter
 * @return mixed return parameter
 */
function zzform_exit($return = false) {
	global $zz_conf;

	// return to old database
	if ($zz_conf['db_current']) mysql_select_db($zz_conf['db_current']);
	// end debug mode
	if ($zz_conf['modules']['debug']) zz_debug('end');
	return $return;
}

/**
 * zzform shortcut, includes some page parameters
 *
 * @param array $glob_vals optional variables that must be declared globally
 * @global array $zz
 * @global array $zz_conf
 * @global array $zz_page
 * @global array $zz_setting
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zzform_all($glob_vals = false) {
//	Die folgenden globalen Definitionen der Variablen sind nur noetig, wenn man wie
//	hier die darauffolgenden vier Zeilen in einer Funktion zusammenfassen will
	global $zz;			// Table description
	global $zz_conf;	// Config variables
	global $zz_page;	// Page (Layout) variables
	global $zz_setting;	// Settings
	if ($glob_vals)		// Further variables, may be set by user
		if (is_array($glob_vals))
			foreach ($glob_vals as $glob_val)
				global $$glob_val;
		else
			global $$glob_vals;
	$zz_conf['show_output'] = false; // do not show output as it will be included after page head
	
//	Zusammenbasteln der Seite
	zzform();					// Funktion aufrufen
	if ($zz['mode'] == 'export') {
		foreach ($zz['headers'] as $index) {
			foreach ($index as $bool => $header) {
				header($header, $bool);
			}
		}
		echo $zz['output'];			// Output der Funktion ausgeben
	} else {
		if (empty($zz_page['title'])) $zz_page['title'] = $zz_conf['title'];
		include $zz_page['head'];	// Seitenkopf ausgeben, teilw. mit Variablen aus Funktion
		echo $zz['output'];			// Output der Funktion ausgeben
		include $zz_page['foot'];	// Seitenfuss ausgeben
	}
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
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_initialize($mode = false) {
	global $zz_conf;
	global $zz_error;
	global $zz_saved;

	if (!empty($zz_conf['zzform_init'])) {
		// get clean $zz_conf without changes from different zzform calls or included scripts
		if (!empty($zz_conf['zzform_calls']) AND !empty($zz_saved) AND $mode == 'overwrite') {
			$calls = $zz_conf['zzform_calls'];
			$zz_conf = $zz_saved;
			$zz_conf['zzform_calls'] = $calls;
		}
		return true;
	}

	//	allowed parameters
	$zz_conf['allowed_params']['mode'] = array('edit', 'delete', 'show', 'add', 'review', 'list_only');
	$zz_conf['allowed_params']['action'] = array('insert', 'delete', 'update'); // review is for internal use only
	
	// Configuration on project level: Core defaults and functions
	$zz_default['character_set']	= 'utf-8';					// character set
	$zz_default['dir_ext']			= $zz_conf['dir'].'/ext';	// directory for extensions
	$zz_default['dir_custom']		= $zz_conf['dir'].'/local';
	$zz_default['dir_inc']			= $zz_conf['dir'].'/inc';
	$zz_default['error_mail_level']	= array('error', 'warning', 'notice');
	$zz_default['ext_modules']		= array('markdown', 'textile');
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

	// todo: include modules geo and upload only if corresponding fields are defined, 
	// see $upload_form as a way how to do that.
	// Problem: zzform_multi() might have problems with a solution like that!

	// Modules on project level
	// debug module must come first because of debugging reasons!
	$int_modules = array('debug', 'geo', 'validate', 'export', 'compatibility', 'upload', 
		'conditions', 'translations');
	$int_modules = zz_add_modules($int_modules, $zz_conf['dir_inc'], $zz_conf);
	$zz_conf['modules'] = $int_modules['modules'];
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$ext_modules = zz_add_modules($zz_conf['ext_modules'], $zz_conf['dir_ext'], $zz_conf);
	foreach ($int_modules['vars'] as $index => $var) {			// import variables from internal modules
		if (is_array($var)) {
			$$index = zz_array_merge($$index, $var);
		}
	}
	// stop if there were errors while adding modules
	if ($zz_error['error']) zz_return(false);

	$zz_conf['ext_modules'] = $ext_modules['modules'];

	$zz_default['action_dir']		= $zz_conf['dir_custom'];	// directory for included scripts after action has been taken
	$zz_default['lang_dir']			= $zz_conf['dir_custom'];	// directory for additional text

	$zz_default['additional_text']	= false;
	$zz_default['backlink']			= true;		// show back-to-overview link
	$zz_default['access']			= '';		// nothing, does not need to be set, might be set individually
	$zz_default['add']				= true;		// add or do not add data.
	$zz_default['delete']			= false;	// show Action: Delete
	$zz_default['details']			= false;	// column details; links to detail records with foreign key
	$zz_default['details_base']		= false;
	$zz_default['details_referer']	= true;		// add referer to details link
	$zz_default['details_target']	= false;	// target-window for details link
	$zz_default['edit']				= true;		// show Action: Edit
	$zz_default['group']			= false;
	$zz_conf['group_field_no']		= false;
	$zz_default['import']			= false;	// import files

	$zz_default['error_handling']		= 'output';
	$zz_default['error_log']['error']	= ini_get('error_log');
	$zz_default['error_log']['notice']	= ini_get('error_log');
	$zz_default['error_log']['warning']	= ini_get('error_log');
	$zz_default['error_mail_from']		= false;
	$zz_default['error_mail_to']		= false;
	$zz_default['log_errors_max_len'] 	= ini_get('log_errors_max_len');
	$zz_default['log_errors'] 			= ini_get('log_errors');

	$zz_default['export']				= false;
	$zz_default['filter_position'] 		= 'top';
	$zz_default['filter'] 				= array();
	$zz_default['footer_text']			= false;		// text at the end of all
	$zz_default['generate_output']		= true;
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
	$zz_default['prefix'] 				= false;	//	prefix for ALL tables like zz_
	$zz_default['project']				= $_SERVER['SERVER_NAME'];
	$zz_default['redirect']['successful_delete'] = false;	// redirect to diff. page after delete
	$zz_default['redirect']['successful_insert'] = false;	// redirect to diff. page after insert
	$zz_default['redirect']['successful_update'] = false;	// redirect to diff. page after update
	$zz_default['relations_table'] 		= '_relations';	//	name of relations table for referential integrity
	$zz_default['search'] 				= false;	// search for records possible or not
	$zz_default['select_multiple_records'] = false;
	$zz_default['show_hierarchy']	= false;
	$zz_default['show_list_while_edit'] = true;	
	$zz_default['show_list']		= true;		// display list of records in database				
	$zz_default['show_output']		= true;		// ECHO output or keep it in $zz['output']
	$zz_default['tfoot']			= false;  	// shows table foot, e. g. for sums of individual values
	$zz_default['this_limit']		= false;	//	internal value, current range which records are shown
	$zz_default['title_separator']	= ' &#8211; ';
	$zz_default['user']				= '';		//	username
	$zz_default['view']				= false;	// 	show Action: View
	$zz_default['password_encryption'] = 'md5';
	$zz_default['get_old_record']	= false;
	
	zz_write_defaults($zz_default, $zz_conf);

	// set default limit in case 'show_hierarchy' is used because hierarchies need more memory
	if (!$zz_conf['limit'] AND $zz_conf['show_hierarchy']) $zz_conf['limit'] = 40;

	$zz_conf['url_append'] = '?'; // normal situation: there is no query string in the base url, so add query string starting ?
	$my_uri = parse_url('http://www.example.org'.$_SERVER['REQUEST_URI']);
	$zz_conf['url_self_qs_base'] = ''; // no base query string which belongs url_self
	// get own URI
	if (empty($zz_conf['url_self'])) {
		$zz_conf['url_self'] = $my_uri['path'];
		$zz_conf['url_self_qs_zzform'] = (!empty($my_uri['query']) ? '?'.$my_uri['query'] : ''); // zzform query string
	} else {
		// it's possible to use url_self without http://hostname, so check for that
		$examplebase = (substr($zz_conf['url_self'], 0, 1) == '/' ? 'http://www.example.org' : '');
		$base_uri = parse_url($examplebase.$zz_conf['url_self']);
		if ($examplebase) {
			$zz_conf['url_self'] = $base_uri['path'];
		} else {
			$zz_conf['url_self'] = $base_uri['scheme'].'://'.$base_uri['host'].$base_uri['path'];
		}
		if (!empty($base_uri['query'])) {
			$zz_conf['url_self_qs_base'] = '?'.$base_uri['query']; // no base query string which belongs url_self
			$zz_conf['url_append'] = '&amp;';
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
			$zz_conf['url_self_qs_zzform'] = http_build_query($my_uri_query);
			if ($zz_conf['url_self_qs_zzform']) $zz_conf['url_self_qs_zzform'] = '&'.$zz_conf['url_self_qs_zzform'];
		} elseif (!empty($my_uri['query']))
			$zz_conf['url_self_qs_zzform'] = '&'.$my_uri['query'];
		else
			$zz_conf['url_self_qs_zzform'] = '';
	}
	unset($my_uri);

	// get LIMIT from URI
	if (!$zz_conf['this_limit'] && $zz_conf['limit']) 
		$zz_conf['this_limit'] = $zz_conf['limit'];
	if (isset($_GET['limit']) && is_numeric($_GET['limit']))	
		$zz_conf['this_limit'] = (int) $_GET['limit'];
	
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
	$zz_conf['referer_esc'] = str_replace('&', '&amp;', $zz_conf['referer']);

	//	URL parameter
	if (get_magic_quotes_gpc()) { // sometimes unwanted standard config
		$_POST = magic_quotes_strip($_POST);
		$_GET = magic_quotes_strip($_GET);
		$_FILES = magic_quotes_strip($_FILES);
		// _COOKIE and _REQUEST are not being used
	}

	if ($zz_conf['character_set'] == 'utf-8') {
		mb_internal_encoding("UTF-8");
	}

	$zz_conf['zzform_init'] = true;
	$zz_saved = $zz_conf;
	zz_return(true);
}

/**
 * call zzform() once or multiple times without user interaction 
 * 
 * @param string $definition_file - script filename
 * @param array $values - values sent to script (instead of $_GET, $_POST and $_FILES)
 * @param string $type - what to do
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zzform_multi($definition_file, $values, $type, $params = false) {
	// unset all variables that are not needed
	// important because there may be multiple zzform calls
	global $zz_conf;
	global $zz_setting;
	global $zz_saved;

	// Allowed:
	$allowed_types = array('csv', 'xml', 'files', 'record');
	if (!in_array($type, $allowed_types)) {
		echo 'Illegal type set for function zzform_multi(): '.htmlspecialchars($type);
		return false;
	}
	
	// TODO: $glob_vals
	
	unset($_GET);
	unset($_POST);
	unset($_FILES);

	switch ($type) {
	case 'record':  // one operation only
		global $zz;			// Table description
		$zz = array();
		// TODO: so far, we have no support for 'values' for subrecords
		// TODO: zzform() and zzform_multi() called within an action-script
		// causes not all zz_conf variables to be reset
		zz_initialize('overwrite');
		$zz_conf['show_output'] = false; // do not show output as it will be included after page head
		$zz_conf['show_list'] = false; // no output, so list view is not neccessary
		$zz_conf['multi'] = true;		// so we know the operation mode for other scripts
		if (!empty($values['GET'])) $_GET = $values['GET'];
		if (!empty($values['POST'])) $_POST = $values['POST'];
		if (!empty($values['FILES'])) $_FILES = $values['FILES'];
		else $_FILES = '';
		require $zz_conf['form_scripts'].'/'.$definition_file.'.php';
		// return on error in form script
		if (!empty($zz['error'])) return false;
		zzform();
		break;
	case 'files':
		require_once $zz_conf['dir_inc'].'/functions.inc.php';
		require_once $zz_conf['dir_inc'].'/import.inc.php';
		require_once $zz_conf['dir_inc'].'/forcefilename-'.$zz_conf['character_set'].'.inc.php';
		$zz['output'] = zz_import_files($definition_file, $values, $params);
		return $zz['output'];
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
}

function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
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


?>