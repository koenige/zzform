<?php 

/*

zzform

This script (c) Copyright 2004-2006 Gustaf Mossakowski, gustaf@koenige.org
No use without permission. The use of this product is restricted
to what has been agreed on in written or spoken form beforehands. If nothing 
has been explicitly said about the use, these scripts may not be used for a
different database than originally intended.
*/

//	Required Variables, global so they can be used by the including script after
//	processing as well

global $zz;			// Table description
global $zz_conf;	// Config variables
global $zz_tab;		// Table values, generated by zzform()
global $zz_page;	// Page (Layout) variables

function zzform() {
	global $zz;			// Table description
	global $zz_conf;	// Config variables
	global $zz_tab;		// Table values, generated by zzform()
	global $zz_page;	// Page (Layout) variables

	$zzform = true; // This variable signals that zz_form is included
//	Variables which are required by several functions
	global $zz_error;
	global $text;
	global $zzform;

//	Default Configuration

	$zz_error['msg'] = '';
	$zz_error['query'] = '';
	$zz_error['level'] = '';
	$zz_error['type'] = '';
	$zz_error['mysql'] = '';
	$upload_form = false;
	
//	for backwards compatibility, to be removed ASAP
	if (!empty($zz_conf['edit_only'])) $zz_conf['access'] = 'edit_only';
	if (!empty($zz_conf['add_only'])) $zz_conf['access'] = 'add_only';
	if (!empty($zz_conf['show'])) $zz_conf['access'] = 'show';
	
	$zz_default['view']				= false;	// 						show Action: View
	$zz_default['delete']			= false;	// $delete				show Action: Delete
	$zz_default['edit']				= true;		// 						show Action: Edit
	$zz_default['add']				= true;		// $add					show Add new record
	$zz_default['do_validation']	= true;	// $do_validation		left over from old edit.inc, for backwards compatiblity
	$zz_default['show_list']		= true;		// $tabelle				nur bearbeiten m�glich, keine Tabelle
	$zz_default['search'] 			= false;	// $editvar['search']	Suchfunktion am Ende des Formulars ja/nein
	$zz_default['backlink']			= true;		// $backlink			show back-to-overview link
	$zz_default['tfoot']			= false;  	// $tfoot				Tabellenfuss
	$zz_default['show_output']		= true;		// $show_output			standardmaessig wird output angezeigt
	$zz_default['multilang_fieldnames'] = false;	// $multilang_fieldnames translate fieldnames via $text[$fieldname]
	$zz_default['limit']			= false;	// $limit				only n records are shown at once
	$zz_default['this_limit']		= false;	// internal value, current range which records are shown
	$zz_default['limit_show_range'] = 800;		// range in which links to records around current selection will be shown
	$zz_default['list']				= true;		// $list				nur hinzuf�gen m�glich, nicht bearbeiten, keine Tabelle
	$zz_default['rootdir']			= $_SERVER['DOCUMENT_ROOT'];		//Root Directory
	$zz_default['max_detail_records'] = 20;		// max 20 detail records, might be expanded later on
	$zz_default['min_detail_records'] = 0;		// min 0 detail records, might be expanded later on
	$zz_default['relations_table'] 	= '_relations';	//	name of relations table for referential integrity
	$zz_default['logging'] 			= false;	//	if logging should occur, turned off by default 
	$zz_default['logging_table'] 	= '_logging';	//	name of table where INSERT, DELETE and UPDATE actions will be logged
	$zz_default['backup'] 			= false;	//	backup uploaded files?
	$zz_default['backup_dir'] 		= $zz_conf['dir'].'/backup';	//	directory where backup will be put into
	$zz_default['prefix'] 			= false;	//	prefix for ALL tables like zz_
	$zz_default['max_select'] 		= 60;		//	maximum entries in select/option, if bigger than sub-select
	$zz_default['dir_ext']			= $zz_conf['dir'].'/ext';			// directory for extensions
	$zz_default['character_set']	= 'utf-8';	// character set
	$zz_default['user']				= '';	// character set
	$zz_default['project']			= $_SERVER['SERVER_NAME'];
	$zz_default['error_mail_to']	= false;
	$zz_default['error_mail_from']	= false;
	$zz_default['error_handling']	= 'output';
	$zz_default['action_dir']		= $zz_conf['dir'].'/local';			// directory for included scripts after action has been taken
	$zz_default['export']			= false;							// if sql result might be exported (link for export will appear at the end of the page)
	$zz_default['export_filetypes']	= array('csv');						// possible filetypes for export
	$zz_default['details_base']		= false;
	$zz_default['additional_text']	= false;
	$zz_default['lang_dir']			= $zz_conf['dir'].'/local';			// directory for additional text
	$zz_default['debug']			= false;
	$zz_default['tmp_dir']			= false;
	$zz_default['access']			= 'all';			// edit_only, add_only, show
	$zz_default['graphics_library'] = 'imagemagick';
//	$zz_default['tmp_dir']			= 
	$zz_default['max_select_val_len']	= 60;		// maximum length of values in select
	$upload_max_filesize = ini_get('upload_max_filesize');
	switch (substr($upload_max_filesize, strlen($upload_max_filesize)-1)) {
		case 'G': $upload_max_filesize *= pow(1024, 3); break;
		case 'M': $upload_max_filesize *= pow(1024, 2); break;
		case 'K': $upload_max_filesize *= pow(1024, 1); break;
	}
	$zz_default['upload_MAX_FILE_SIZE']	= $upload_max_filesize;

	foreach (array_keys($zz_default) as $key)
		if (!isset($zz_conf[$key])) $zz_conf[$key] = $zz_default[$key];
	
	if (!isset($zz_conf['url_self'])) {
		$zz_conf['url_self'] = parse_url($_SERVER['REQUEST_URI']);
		$zz_conf['url_self'] = $zz_conf['url_self']['path'];
	}
	$zz_var['url_append'] ='?';
	$test_url_self = parse_url($zz_conf['url_self']);
	if (!empty($test_url_self['query'])) $zz_var['url_append'] ='&amp;';
	if (!$zz_conf['this_limit'] && $zz_conf['limit']) 
		$zz_conf['this_limit'] = $zz_conf['limit'];

//	Required files
	
	require_once($zz_conf['dir'].'/inc/editform.inc.php');			// Form
	require_once($zz_conf['dir'].'/inc/editfunc.inc.php');			// Functions
	if ($zz_conf['list'] AND $zz_conf['show_list'])
		require_once($zz_conf['dir'].'/inc/edittab.inc.php');		// Table output with all records
	require_once($zz_conf['dir'].'/inc/editrecord.inc.php');		// update/delete/insert
	require_once($zz_conf['dir'].'/inc/editval.inc.php');			// Basic Validation
	require_once($zz_conf['dir'].'/inc/text-en.inc.php');			// English text
	if ($zz_conf['additional_text'] AND file_exists($langfile = $zz_conf['lang_dir'].'/text-en.inc.php')) 
		include $langfile; // must not be include_once since $text is cleared beforehands

	if ($zz_conf['upload_MAX_FILE_SIZE'] > $upload_max_filesize) {
		$zz_error['msg'] = 'Value for upload_max_filesize from php.ini is smaller than value which is set in the script. The value from php.ini will be used. To upload bigger files, please adjust your configuration settings.';
		$zz_conf['upload_MAX_FILE_SIZE'] = $upload_max_filesize;
	}
	
//	Optional files
	
	if (isset($zz_conf['language']) && $zz_conf['language'] != 'en') {	// text in other languages
		$langfile = $zz_conf['dir'].'/inc/text-'.$zz_conf['language'].'.inc.php';
		if (file_exists($langfile)) include_once($langfile);
		else {
			$zz_error['level'] = 'warning';
			$zz_error['msg'] .= 'No language file for <strong>'.$zz_conf['language'].'</strong> found. Using English instead.';
			$zz_error['type'] = 'config';
		}
		if ($zz_conf['additional_text'] AND file_exists($langfile = $zz_conf['lang_dir'].'/text-'.$zz_conf['language'].'.inc.php'))
			include $langfile; // must not be include_once since $text is cleared beforehands
	}
	// todo: if file exists else lang = en
	if (!isset($zz_conf['db_connection'])) include_once ($zz_conf['dir'].'/local/db.inc.php');
	if (!empty($zz_conf['db_name'])) {
		$dbname = mysql_select_db($zz_conf['db_name']);
		if (!$dbname) $zz_error['msg'] .= mysql_error();
	}
	if (!function_exists('datum_de')) include ($zz_conf['dir'].'/inc/numbers.inc.php');
	if (file_exists($zz_conf['dir'].'/inc/geocoords.inc.php')) 
		include_once($zz_conf['dir'].'/inc/geocoords.inc.php');
	if (file_exists($zz_conf['dir'].'/inc/validate.inc.php'))
		include_once($zz_conf['dir'].'/inc/validate.inc.php');
	if (file_exists($zz_conf['dir'].'/inc/forcefilename-'.$zz_conf['character_set'].'.inc.php'))
		include_once($zz_conf['dir'].'/inc/forcefilename-'.$zz_conf['character_set'].'.inc.php');
	
//	External Add-ons
	
	if (file_exists($zz_conf['dir_ext'].'/markdown.php'))
		include_once($zz_conf['dir_ext'].'/markdown.php');
	if (file_exists($zz_conf['dir_ext'].'/textile.php'))
		include_once($zz_conf['dir_ext'].'/textile.php');

//	Variables
	
	$zz['output'] = '<div id="zzform">'."\n";
	$zz['output'].= zz_error($zz_error); // initialise zz_error
	
//	URL parameter
	
	if (get_magic_quotes_gpc()) // sometimes unwanted standard config
		$_POST = magic_quotes_strip($_POST);
	
	if (isset($_GET['limit']) && is_numeric($_GET['limit']))	
		$zz_conf['this_limit'] = (int) $_GET['limit'];
	
	if (!isset($zz_conf['referer'])) {
		$zz_conf['referer'] = false;
		if (isset($_GET['referer'])) $zz_conf['referer'] = $_GET['referer'];
		if (isset($_POST['referer'])) $zz_conf['referer'] = $_POST['referer'];
	} elseif (isset($_POST['referer']))
		$zz_conf['referer'] = $_POST['referer'];
	elseif (isset($_SERVER['HTTP_REFERER']))
		$zz_conf['referer'] = $_SERVER['HTTP_REFERER'];
	$zz_conf['referer_esc'] = str_replace('&', '&amp;', $zz_conf['referer']);
		
	$zz_conf['heading'] = (!isset($zz_conf['heading'])) ? zz_form_heading($zz['table']) : $zz_conf['heading'];
	
	// Add with suggested values
	$zz_var['where'] = false;
	$zz_tab[0][0]['id']['where'] = false;
	$zz_tab[0][0]['id']['value'] = false;
	if (isset($_GET['where'])) {
		// todo: check fields and values in this array for validity
		$zz_var['where'] = zz_read_fields($_GET['where'], 'replace', $zz['table']);
		foreach (array_keys($_GET['where']) as $field) {
			if (strstr($field, '.')) $myfield = substr($field, strrpos($field, '.')+1);
			else $myfield = $field;
			foreach ($zz['fields'] as $thisfield)
				if (isset($thisfield['type']) && $thisfield['type'] == 'id'
					AND $myfield == $thisfield['field_name']) {
					$zz_tab[0][0]['id']['where'] = $thisfield['field_name'];
					$zz_tab[0][0]['id']['value'] = $_GET['where'][$field];
				} elseif (isset($thisfield['type']) && $thisfield['type'] == 'id')
					foreach ($zz['fields'] as $this_field)
						if (isset($this_field['unique']) && $this_field['unique'] == true && $myfield == $this_field['field_name'])
							$zz_tab[0][0]['id']['where'] = $thisfield['field_name'];  // just for UNIQUE, see below
			$mfield = (!strstr($field, '.') ? $zz['table'].'.' : '').$field; // this makes it unneccessary to add table_name to where-clause
			$zz['sql'] = zz_edit_sql($zz['sql'], 'WHERE', $mfield." = '".$_GET['where'][$field]."'");
		}
	/*
		thought of it, but it would be too complicated (check what type of field it is, ... (add, edit))
			if (substr($field, 0, 1) == '!') {
				$equal = '!=';
				$fieldname = substr($field, 1);
			} else {
				$equal = '=';
				$fieldname = $field;
			}
	*/

		// in case where is not combined with ID field but UNIQUE
		
		if (!($zz_tab[0][0]['id']['value'])) {
			$result = mysql_query($zz['sql']);
			if ($result) if (mysql_num_rows($result) == 1) {
				while ($line = mysql_fetch_array($result))
					if (isset($line[$zz_tab[0][0]['id']['where']]))
						$zz_tab[0][0]['id']['value'] = $line[$zz_tab[0][0]['id']['where']];
			} else
				$zz_tab[0][0]['id']['where'] = false;
		}

		// make nicer headings
		zz_nice_headings($zz['fields'], $zz_conf, $zz_error);
	}
	
	$zz_conf['title'] = strip_tags($zz_conf['heading']);

	$zz['output'].= "\n".'<h2>'.$zz_conf['heading'].'</h2>'."\n\n";
	if (isset($zz_conf['heading_text'])) $zz['output'] .= $zz_conf['heading_text'];
	$zz['output'].= zz_error($zz_error);
	
	// ### check if there are any subtables ###
	
	$subqueries = '';
	$j = 1;
	foreach (array_keys($zz['fields']) as $i)
		if (isset($zz['fields'][$i]['type'])) {
			if ($zz['fields'][$i]['type'] == 'subtable') {
				$subqueries[$j] = $i;
				$zz['fields'][$i]['subtable'] = $j;
				if (!isset($zz['fields'][$i]['table_name']))
					$zz['fields'][$i]['table_name'] = $zz['fields'][$i]['table'];
				$j++;
			} elseif ($zz['fields'][$i]['type'] == 'id')
				$zz_tab[0][0]['id']['field_name'] = $zz['fields'][$i]['field_name'];
			elseif (substr($zz['fields'][$i]['type'], 0, 7) == 'upload_') {// at least one upload field adds enctype-field to form
				$upload_form = true;
				include_once $zz_conf['dir'].'/inc/upload.inc.php';
			}
		}
	if (empty($upload_form)) unset($zz_conf['upload']); // values are not needed

	$zz['action'] = false;
	if (!empty($_GET['mode'])) {
		$zz['mode'] = $_GET['mode'];
		if (($zz['mode'] == 'edit' OR $zz['mode'] == 'delete' OR $zz['mode'] == 'show')
			&& !$zz_tab[0][0]['id']['value'])
			$zz_tab[0][0]['id']['value'] = $_GET['id'];
	} else {
		$zz['mode'] = false;
		if (isset($_POST['action']))
			$zz['action'] = $_POST['action'];
			if (isset($_POST[$zz_tab[0][0]['id']['field_name']]) && !$zz_tab[0][0]['id']['value'])
				$zz_tab[0][0]['id']['value'] = $_POST[$zz_tab[0][0]['id']['field_name']];
		if ($zz_conf['access'] == 'add_only' && empty($_POST)) 
			$zz['mode'] = $_GET['mode'] = 'add';
		elseif ($zz_conf['access'] == 'edit_only' && empty($_POST))
			$zz['mode'] = $_GET['mode'] = 'edit';
		elseif (!isset($_POST['action'])) $zz['mode'] = $_GET['mode'] = 'review';
	}

	if (isset($_POST['subtables']))
		// not submit button but only add or remove form fields for subtable
		if ($zz['action'] == 'insert') {
			$zz['action'] = false;
			$zz['mode'] = 'add';
		} elseif ($zz['action'] == 'update') {
			$zz['action'] = false;
			$zz['mode'] = 'edit';
			$zz_tab[0][0]['id']['value'] = $_POST[$zz_tab[0][0]['id']['field_name']];
		}
	
	$zz_tab[0][0]['action'] = $zz['action'];
	
	// Extra GET Parameter
	
	$extras = false;
	$zz['extraGET'] = false;
	if (!empty($_GET['where'])) 			$extras .= get_to_array($_GET['where']);
	if (!empty($_GET['order'])) 			$extras .= '&amp;order='.$_GET['order'];
	if (!empty($_GET['q'])) 				$extras .= '&amp;q='.urlencode($_GET['q']);
	if (!empty($_GET['scope'])) 			$extras .= '&amp;scope='.$_GET['scope'];
	if (!empty($_GET['dir'])) 				$extras .= '&amp;dir='.$_GET['dir'];
	if (!empty($_GET['var'])) 				$extras .= get_to_array($_GET['var']);
	if ($zz_conf['this_limit']) 			$extras.= '&amp;limit='.$zz_conf['this_limit'];
	if ($zz_conf['referer']) 				$extras.= '&amp;referer='.urlencode($zz_conf['referer']);
	if ($extras)
		if (substr($extras, 0, 1) == '&') $extras = substr($extras, 5);
		 else $extras = substr($extras, 1, strlen($extras) -1 ); 
											// first ? or & to be added as needed!
	if ($extras) 							$zz['extraGET'] = '&amp;'.$extras;

	if ($zz_conf['access'] == 'show') {
		$zz_conf['add'] = false;
		$zz_conf['edit'] = false;
		$zz_conf['delete'] = false;
		if ($_GET['mode'] == 'add' OR $_GET['mode'] == 'edit' OR $_GET['mode'] == 'delete') {
			$zz['mode'] = 'show';
			$_GET['mode'] = 'show';
		}
	} elseif ($zz_conf['access'] == 'add_only') { // show only form, nothing else
		$zz_conf['delete'] = false;
		$zz_conf['search'] = false;
		$zz_conf['show_list'] = false;
		$zz_conf['list'] = false;
		$zz_conf['show_output'] = false;
		$zz_conf['add'] = false;
	} elseif ($zz_conf['access'] == 'edit_only') {
		$zz_conf['add'] = false;
		$zz_conf['delete'] = false;
		$zz_conf['limit'] = false;
		$zz_conf['search'] = false;
		$zz_conf['show_list'] = false;
	} elseif (isset($_GET['mode']) AND ((!$zz_conf['delete'] && $_GET['mode'] == 'delete') // protection from URL manipulation
		OR (!$zz_conf['edit'] && $_GET['mode'] == 'edit')
		OR (!$zz_conf['add'] && $_GET['mode'] == 'add'))) {
		$_GET['mode'] = 'show';
		$zz['mode'] = 'show';
	}
	
	$zz['filetype'] = $zz_conf['export_filetypes'][0]; // default filetype for export
	if (!empty($_GET['filetype'])) // get filetype for export
		if (in_array($_GET['filetype'], $zz_conf['export_filetypes']))
			$zz['filetype'] = $_GET['filetype'];
	
//	Add, Update or Delete
	
	fill_out($zz); // set type, title etc. where unset
	// ### variables for main table will be saved in zz_tab[0]
	
	$zz_tab[0]['table'] = $zz['table'];
	$zz_tab[0]['sql'] = $zz['sql'];
	$zz_tab[0]['sqlorder'] = $zz['sqlorder'];
	
	$zz_tab[0][0]['fields'] = $zz['fields'];
	$zz_tab[0][0]['validation'] = true;
	$zz_tab[0][0]['record'] = false;
	if ($_POST) $zz_tab[0][0]['POST'] = $_POST;
	
	$validation = true;
	
	//	### put each table (if more than one) into one array of its own ###
	zz_get_subqueries($subqueries, $zz, $zz_tab, $zz_conf);
	if ($subqueries && $zz['action'] != 'delete')
		if (isset($_POST['subtables'])) $validation = false;
	
	$zz['sql'] = zz_search_sql($zz['fields'], $zz['sql'], $zz['table']);	// if q modify $zz['sql']: add search query
	$zz['sql'].= ' '.$zz['sqlorder']; 									// must be here because of where-clause
	$zz['formhead'] = false;
	
	if ($zz['action'] == 'insert' OR $zz['action'] == 'update' OR $zz['action'] == 'delete')
		zz_action($zz_tab, $zz_conf, $zz, $validation, $upload_form, $subqueries); // check for validity, insert/update/delete record

//	Query Updated, Added or Editable Record
	
	if (!$validation) {
		if ($zz['action'] == 'update') $zz['mode'] = 'edit';
		elseif ($zz['action'] == 'insert') $zz['mode'] = 'add';
		zz_get_subqueries($subqueries, $zz, $zz_tab, $zz_conf);
	}

//	Display Updated, Added or Editable Record
	
	// Query for table below record and for value = increment
	// moved to end
	
	if ($zz['mode'] && $zz['mode'] != 'review') {
	
	//	mode = add | edit | delete: show form
		if ($zz['mode'] == 'delete' OR $zz['mode'] == 'show') $display = 'review';
		else $display = 'form';
		if ($zz['mode'] != 'show') {
			$zz['output'].= '<form action="'.$zz_conf['url_self'];
			if ($extras) $zz['output'].= $zz_var['url_append'].$extras;
			$zz['output'].= '" method="POST"';
			if (!empty($upload_form)) $zz['output'] .= ' enctype="multipart/form-data"';
			$zz['output'].= '>';
		}
		$zz['formhead'] = $text[$zz['mode']].' '.$text['a_record'];
	} elseif ($zz['action']) {	
	//	action = insert update review: show form with new values
		if ($zz['action'] == 'delete') $display = false;
		else $display = 'review';
		if (!$zz['formhead']) {
			$zz['formhead'] = ucfirst($text[$zz['action']].' '.$text['failed']);
			$display = false;
		}
		if (!empty($zz['no-delete'])) {
			$zz['formhead'] = $text['warning'].'!';
			foreach ($zz['no-delete'] as $tab) {
				$tab = explode(',', $tab);
				$no_delete_reason = $zz_tab[$tab[0]][$tab[1]]['no-delete'];
				$zz_error['msg'].= '<p>'.$text['This record could not be deleted because there are details about this record in other records.'];
				$zz_error['msg'].= ' '.$no_delete_reason['text'].'</p>'."\n";
				if (isset($no_delete_reason['fields'])) {
					$zz_error['msg'].= '<ul>'."\n";
					foreach ($no_delete_reason['fields'] as $del_tab) {
						if ($zz_conf['prefix']) { // makes the response look nicer
							if (strtolower(substr($del_tab, 0, strlen($zz_conf['prefix']))) == strtolower($zz_conf['prefix']))
								$del_tab = substr($del_tab, strlen($zz_conf['prefix']));
							else echo substr($del_tab, 0, strlen($zz_conf['prefix']));
						}
						$del_tab = ucfirst($del_tab);
						$zz_error['msg'].= '<li>'.$del_tab.'</li>'."\n";
					}
					$zz_error['msg'].= '</ul>'."\n";
				} 
			}
		}
	} elseif ($zz['mode'] == 'review' && $zz_tab[0][0]['id']['where']) {
		$display = 'review';
		$zz['formhead'] = $text['show_record'];
	//
		$result = mysql_query($zz['sql']);
		if ($result) 
			if (mysql_num_rows($result) == 1) {
				$zz_tab[0][0]['record'] = mysql_fetch_array($result, MYSQL_ASSOC);
				$zz_tab[0][0]['id']['value'] = $zz_tab[0][0]['record'][$zz_tab[0][0]['id']['where']];
			} else
				$zz_error['msg'].= $text['Database error. This database has ambiguous values in ID field.'];
	//
	} else
		$display = false;

	if ($zz_tab[0][0]['id']['where']) { // ??? in case of where and not unique, ie. only one record in table, don't do this.
		$zz_conf['show_list'] = false;		// don't show table
		$zz_conf['add'] = false;			// don't show add new record
	}
	
	foreach (array_keys($zz_tab) as $i)
		foreach (array_keys($zz_tab[$i]) as $k)
			if (is_numeric($k))
				$zz_tab[$i][$k] = zz_requery_record($zz_tab[$i][$k], $validation, $zz_tab[$i]['sql'], $zz_tab[$i]['table'], $zz['mode']);
			// requery record if successful, 
	if (($zz['mode'] == 'edit' OR $zz['mode'] == 'delete') AND !$zz_tab[0][0]['record']) {
		$zz['formhead'] = '<span class="error">'.$text['There is no record under this ID:'].' '.htmlspecialchars($zz_tab[0][0]['id']['value']).'</span>';	
		$display = false;
	}
	$result = mysql_query($zz['sql']);  // must be behind update, insert etc. or it will return the wrong number
	if ($result) $zz_lines = mysql_num_rows($result);
	else $zz_lines = 0;
	
	$zz['output'] .= zz_display_records($zz, $zz_tab, $zz_conf, $display, $zz_var);

	if ($zz['mode'] && $zz['mode'] != 'review' && $zz['mode'] != 'show') $zz['output'].= "</form>\n";
	if ($zz['mode'] != 'add' && $zz_conf['add']) $zz['output'].= '<p class="add-new"><a accesskey="n" href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=add'.$zz['extraGET'].'">'.$text['add_new_record'].'</a></p>'."\n";
	if ($zz_conf['referer'] && $zz_conf['backlink']) $zz['output'].= '<p id="back-overview"><a href="'.$zz_conf['referer_esc'].'">'.$text['back-to-overview'].'</a></p>'."\n";
	
	$zz['sql'] = zz_sql_order($zz['fields'], $zz['sql']); // Alter SQL query if GET order (AND maybe GET dir) are set
	
	if ($zz_conf['list'] AND $zz_conf['show_list'])
		zz_display_table($zz, $zz_conf, $zz_error, $zz_var, $zz_lines); // shows table with all records (limited by zz_conf['limit'] and add/nav if limit/search buttons)

	$zz['output'].= '</div>';
	if ($zz_conf['show_output']) echo $zz['output'];
}

function zzform_all($glob_vals = false) {
//	Die folgenden globalen Definitionen der Variablen sind nur noetig, wenn man wie
//	hier die darauffolgenden vier Zeilen in einer Funktion zusammenfassen will
	global $zz;			// Table description
	global $zz_conf;	// Config variables
	global $zz_tab;		// Table values, generated by zzform()
	global $zz_page;	// Page (Layout) variables
	if ($glob_vals)		// Further variables, may be set by user
		if (is_array($glob_vals))
			foreach ($glob_vals as $glob_val)
				global $$glob_val;
		else
			global $$glob_vals;
	$zz_conf['show_output'] = false; // do not show output as it will be included after page head
	
//	Zusammenbasteln der Seite
	zzform();					// Funktion aufrufen
	if (empty($zz_page['title'])) $zz_page['title'] = $zz_conf['title'];
	include($zz_page['head']);	// Seitenkopf ausgeben, teilw. mit Variablen aus Funktion
	echo $zz['output'];			// Output der Funktion ausgeben
	include($zz_page['foot']);	// Seitenfuss ausgeben
}


?>