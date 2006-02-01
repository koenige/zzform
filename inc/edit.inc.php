<?php 

// README

/*

	This script (c) Copyright 2004/2005 Gustaf Mossakowski, gustaf@koenige.org
	No use without permission

	required: at least PHP 4.3.0 (mysql_real_escape_string might be replaced in editval.inc for lower PHP version)

	Remarks:
	- ID field has to be $zz['fields'][1], see edit_functions for reason why.

	$zz_conf - configuration variables
		$zz_conf['dir']				directory in which zzform resides in
		$zz_conf['language']		language of zzform
		$zz_conf['search']			search records possible or not
		$zz_conf['delete']			delete records possible or not
		$zz_conf['do_validation']	backwards compatiblity to old edit.inc, to be removed in the future
		$zz_conf['limit']			display only limited amount of records (20)
		$zz_conf['show_list']		display list of records in database
		$zz_conf['list']			?
		$zz_conf['show_output']		
		$zz_conf['url_self']		own url or target url									$self
		$zz_conf['details']			column details; links to detail records with foreign key	$details	
		$zz_conf['details_base']	array, corresponding to details, does not make use of details as first part of details_url but this field instead
		$zz_conf['details_url']		what url to follow for detail records					$details_url		
									may be array e. g. array('field1' => 'fieldname_bla', 'string1' => '/', 'field2' => 'fieldname_blubb') etc.
		$zz_conf['referer']			referer which links back to previous page				$referer	
		$zz_conf['add']				do not add data
		$zz_conf['heading']			optional: h2-heading to be used for form instead of $zz['table']
		$zz_conf['heading_text']	Textblock after heading
		$zz_conf['heading_sql']		['heading_sql'][$where_id, without tablename] = zz['fields'][n]['sql'] where n is the index of the field corresponding to the key
		$zz_conf['heading_var']		['heading_var'][$where_id, without tablename] = array() field from heading_sql-query which shall be used for better display of H2 and TITLE blabla:<br>var1 var2 var3
			-- the corresponding field may be hidden from the form and the list with
			if (isset($_GET['where']['gebaeude.gebaeude_id'])) {
				$zz['fields'][2]['class'] = 'hidden';
				$zz['fields'][2]['hide_in_list'] = true;
			}
		$zz_conf['title']			= heading, but without HTML tags
		$zz_conf['prefix']			table_prefix like zz_ (will be removed in error output)
		$zz_conf['action']			action to be performed after or before insert, update, delete
									(file to be included)	$query_action
		$zz_conf['user']			user name, default false
		$zz_conf['error_mail_to']	mailaddress where errors go to
		$zz_conf['error_mail_from']	mailaddress where errrors come from
		$zz_conf['project']			project name, used sparely, default is server name
		$zz_conf['error_handling']	what to do with errors, default is output | mail
		$zz_conf['dir_ext']			path to extensions directory 
		$zz_conf['db_connection']	resource of MySQL connection (open connection will be used, therefore this value can be set to true as well)
		$zz_conf['db_name']			database name
		$zz_conf['export']			if sql result might be exported (link for export will appear at the end of the page)
		$zz_conf['export_filetypes']	possible filetypes for export
		$zz_conf['relations_table']	table for relations for relational integrity
		$zz_conf['additional_text']	additional textfile in directory local (text-en.inc.php where en = zz_conf['language'])? false | true, overwrites standard messages as well!
		$zz_conf['logging']			logging of INSERT UPDATE DELETE enabled? default: false
		$zz_conf['logging_table']	table where logging will be written into, default: _logging
		$zz_conf['backup']			do a backup of old files?
		$zz_conf['backup_dir']		directory where old files shall be backed up to, default: zz_conf['dir'].backup
		$zz_conf['graphics_library'] graphics library used for image manipulation (imagemagick is default, others are currently not supported)
		$zz_conf['max_select_val_len']	maximum length of values in select, default = 60

	$zz
		$zz['table']				name of main table										$maintable
		$zz['fields']				all fields of a table									$query
		
		//--> for all fields

			$zz['fields'][n]['field_name']			field name from database, required if field shall be included

			$zz['fields'][n]['type']				type of field, default if not set is "text"
				possible values:
				
				id				ID of record, must be first field in list zz['fields'][1]
				hidden			hidden field
					-> value		value for hidden field
				identifier		textual identifier for a record, unique, will be put together from 'fields'
					-> fields		if one of the fields-values is field_name of current field, identifier will be written only once and not change thereafter
					-> conf_identifier
				timestamp		timestamp
					-> value		value for timestamp
				unix_timestamp	unix-timestamp, will be converted to readable date and back
				foreign			... (not in use currently)
					-> add_foreign	??
				password		password input, will be md5 encoded
				password_change	change a password (enter old, enter new twice, md5 encoded)
					-> sql_password_check
				text			standard one line text field, maxlength from VARCHAR-value if applicable, size=32
					-> format
					-> size
				url				URL, will be checked for validity (absolute URLs only, http:// as well as beginning with /)
				time			time, will be checked for validity
				mail			email address, will be checked for validity and hyperlinked in list
				datetime		datetime
				number			number
					-> number_type	longitude | latitude
					-> unit			displays a unit behind a number, won't display if value = NULL
					-> factor		factor for avoiding doubles as database fields
					-> auto_value
					-> wrong_fields	(internal value for latitude, longitude)
				date			
				memo			will show textarea
					-> rows
					-> format
				select
					-> enum			array for enum, default value with 'default' should be set as well
					-> set			array for set
					-> sql			SQL-Query for select, first field is key field which will not be displayed but entered into database field
					-> sql_where	adds where to sql-string, rather complicated ... :-)
					-> sql_without_id	where['id']-value will be appended automatically
					-> key_field_name
					-> show_hierarchy	shows hierarchy in selects, value must be set to corresponding SQL field name
					-> add_details
				image
					-> path			syntax = see below
				upload_image
					-> path
				display				column for display only
					-> display_field
				calculated
					-> calculation = hours | sum (only supported modes)
					-> calculation_fields	array of fields which shall be used for calculation
				subtable		subrecord with foreign key which can be edited together with main record
					-> table_name
					-> 
				foreign_key		field is foreign_key (only possible for subtables)

			internal types
				predefined	... (only for internal use)
					-> type_detail

			internal values
				f_field_name
				
			$zz['fields'][n]['title']				title of field, will be shown in form and table [optional, value will be generated from field_name if not set]
			$zz['fields'][n]['title_desc']			description, will always be shown below title in form, values in format will be added automatically (cf. explanation)
			$zz['fields'][n]['hide_in_list']		field will not be shown in table view
			$zz['fields'][n]['display_field']		field (from sql query) which will be shown in table (e. g. as replacement for id values)
			$zz['fields'][n]['default']				default value for field
			$zz['fields'][n]['value']				value for field, cannot be changed
			$zz['fields'][n]['append_next']			false | true; appends next record in form view in the same line
			$zz['fields'][n]['add_details']			add detail records in different table, attention: current input will not be saved.
			$zz['fields'][n]['explanation']			explanation how to fill in values in this field, will only be shown in edit or insert mode
			$zz['fields'][n]['link']				link in list to record
			$zz['fields'][n]['link_no_append']		don't append record id to link
			$zz['fields'][n]['null']				value might be 0 or '', won't be set to NULL
			$zz['fields'][n]['class']				class="" (some classes will be added by zzform, e. g. idrow, ...)
			$zz['fields'][n]['show_title']			display record: show field title in TH (mainly for subtables, for aesthetic reasons)
			$zz['fields'][n]['maxlength']			maxlength, if not set will be taken from database
			$zz['fields'][n]['size']				size of input field, standard for number 16, for time 8 and for all other fields 32 (or maxlength if smaller)
			$zz['fields'][n]['suffix']				adds suffix-string to form view	
			$zz['fields'][n]['suffix_function']		adds suffix-function to form view	
			$zz['fields'][n]['suffix_function_var']	parameters for suffix-function to form view	(array)
			$zz['fields'][n]['prefix']				adds prefix-string to form view	
			$zz['fields'][n]['exclude_from_search']	search will do no operations in this field

		//--> depending on type of field, see -> above

			$zz['fields'][n]['format']				function which will be used to format text for display, e. g. markdown | textile
			$zz['fields'][n]['enum']				
			$zz['fields'][n]['set']
			$zz['fields'][n]['sql']
			$zz['fields'][n]['rows']				number of rows in textarea
			$zz['fields'][n]['sql_without_id']
			$zz['fields'][n]['sql_where']
			$zz['fields'][n]['key_field_name']		field name differs from foreign_key, e. g. mother_org_id instead of org_id
			$zz['fields'][n]['sql_index_only']		only show index field (e. g. for SHOW COLUMNS ... queries)
			$zz['fields'][n]['number_type']			latitude | longitude, for entering geo information
			$zz['fields'][n]['factor']				for doubles etc. factor will be multiplied with value
			$zz['fields'][n]['function']			function which will be called to change input value
			$zz['fields'][n]['fields']				vars which will be passed to function or identifier
			$zz['fields'][n]['auto_value']			increment | ... // 1 will be added and inserted in 'default'
			$zz['fields'][n]['conf_identifier']		array, affects standard values for generating identifier: array('forceFilename' => '-', 'concat' => '.', 'exists' => '.'); - attention: values longer than 1 will be cut off!
			$zz['fields'][n]['path']				array, values: root DOCUMENT_ROOT or path to directory, will be used as a prefix to check whether file_exists or not
													fieldXX will add corresponding field value, stringXX will add string value, modeXX: functions that will be applied to all field_values
													e. g. array('field1' => 'fieldname_bla', 'string1' => '/', 'field2' => 'fieldname_blubb') etc.
			$zz['fields'][n]['sql_password_check']	query to check existing password

		$zz_tab[1]['table']
		$zz_tab[1]['no']			= n in $zz['fields'][n]
		$zz_tab[1]['records']		number of subrecords
		$zz_tab[1]['max_records']	max. subrecords
		$zz_tab[1]['min_records']	min. subrecords


			$zz['fields'][n]['order']				set order, e. g. for mixed alpha-numerical strings without preceding zeros

			$zz['fields'][n]['table_name']			Alias if more subtables are included (used for search only, AFAIK)

			$zz['fields'][n]['unique']				if field value is unique, it can be used for where-clauses and will show only one record in display mode, without add new record
			$zz['fields'][n]['upload_field'] = 8;
			$zz['fields'][n]['upload_value'] = 'exif[FileName]'; // possible values:
				filename = filename without extension and web compatible
				title = title, tried to extract from filename (_ replaced with space, ucfirst, ...)
				name	from upload
				type	from upload
				tmp_name	from upload
				error	from upload
				size	from upload
				width	getimgsize orig. image
				height	getimgsize orig. image
				exif[FileName]
				exif[FileSize] ...
				
			
		$zz['sql']					SQL Query without ORDER-part
		$zz['sqlorder']				ORDER part of SQL Query
				
		$zz['output']				HTML output												$output
		$zz['action']				what to do (POST): insert | update | delete | review	$action
		$zz['mode']					what to prepare (GET): add | edit | delete				$mode
		$zz['POST']					POST values												$myPOST
		$zz['record']				current record with all fields							$record
		$zz['extraGET']				extra GET values										$add_extras

	$zz_tab[n]
		$zz_tab[0]['table']			= $zz['table']
		$zz_tab[0][0]['fields']		= $zz['fields']
		$zz_tab[0][0]['action']
		$zz_tab[0][0]['POST']
		$zz_tab[0][0]['validation']
		$zz_tab[0][0]['record']
		$zz_tab[0][0]['id']			- variables regarding current record_id
			$zz_tab[0][0]['id']['field_name']		field name of current record id
			$zz_tab[0][0]['id']['value']			value of current record id
			$zz_tab[0][0]['id']['where']			value of current record id, taken from URL ?where[]=

	//--> subtables:

		$zz_tab[1][0]['fields']
		$zz_tab[1][0]['POST']
		$zz_tab[1][0]['validation']
		$zz_tab[1][0]['action']
		$zz_tab[1][0]['record']

		$zz_tab[1][1]['fields']
		$zz_tab[1][1]['POST']
		$zz_tab[1][1]['validation']
		$zz_tab[1][1]['action']
		$zz_tab[1][1]['record']
	
	$zz_error
		$zz_error['msg']
		$zz_error['query']
		$zz_error['level']			= crucial | warning
		$zz_error['type']			= mysql | config
		$zz_error['mysql']			mysql error message

	$zz_var['values']				$values
	$zz_var['where']				$where_values
	$zz_var['url_append']			? or &amp;

	$zz['fields'][4]['sql_where'][1] = array(
		'team_id',
		'paarung_id', 
		'SELECT heim_team_id FROM ligen_paarungen WHERE paarung_id = ');
	Target: additional where-clause in sql-clause, e. g.
		WHERE team_id = 1
	How to do it:
		element [0] = field_name in WHERE-clause, e. g. team_id
		element [1] = field_name which has to be queried
		element [2] = SQL-clause where [1] is inserted to get value for [0] as result.

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

/*
	Default Configuration
*/

	$zz_error['msg'] = '';
	$zz_error['query'] = '';
	$zz_error['level'] = '';
	$zz_error['type'] = '';
	$zz_error['mysql'] = '';
	$upload_form = false;

	$zz_default['delete']			= false;	// $delete				show Action: Delete
	$zz_default['edit']				= true;		// 						show Action: Edit
	$zz_default['add']				= true;		// $add					show Add new record
	$zz_default['do_validation']	= true;	// $do_validation		left over from old edit.inc, for backwards compatiblity
	$zz_default['show_list']		= true;		// $tabelle				nur bearbeiten möglich, keine Tabelle
	$zz_default['search'] 			= false;	// $editvar['search']	Suchfunktion am Ende des Formulars ja/nein
	$zz_default['backlink']			= true;		// $backlink			show back-to-overview link
	$zz_default['tfoot']			= false;  	// $tfoot				Tabellenfuss
	$zz_default['show_output']		= true;		// $show_output			standardmaessig wird output angezeigt
	$zz_default['multilang_fieldnames'] = false;	// $multilang_fieldnames translate fieldnames via $text[$fieldname]
	$zz_default['limit']			= false;	// $limit				nur 20 Datensaetze auf einmal angezeigt
	$zz_default['list']				= true;		// $list				nur hinzufügen möglich, nicht bearbeiten, keine Tabelle
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
	$zz_default['graphics_library'] = 'imagemagick';
	$zz_default['max_select_val_len']	= 60;		// maximum length of values in select
	$upload_max_filesize = ini_get('upload_max_filesize');
	switch (substr($upload_max_filesize, strlen($upload_max_filesize)-1)) {
		case 'G': $upload_max_filesize *= pow(1024, 3); break;
		case 'M': $upload_max_filesize *= pow(1024, 2); break;
		case 'K': $upload_max_filesize *= pow(1024, 1); break;
	}
	$zz_default['upload']['MAX_FILE_SIZE']	= $upload_max_filesize;

	foreach (array_keys($zz_default) as $key)
		if (!isset($zz_conf[$key])) $zz_conf[$key] = $zz_default[$key];

	if (!isset($zz_conf['url_self'])) {
		$zz_conf['url_self'] = parse_url($_SERVER['REQUEST_URI']);
		$zz_conf['url_self'] = $zz_conf['url_self']['path'];
	}
	$zz_var['url_append'] ='?';
	$test_url_self = parse_url($zz_conf['url_self']);
	if (!empty($test_url_self['query'])) $zz_var['url_append'] ='&amp;';

	/*
		Required files
	*/
	
	require_once($zz_conf['dir'].'/inc/editform.inc.php');			// Form
	require_once($zz_conf['dir'].'/inc/editfunc.inc.php');			// Functions
	if ($zz_conf['list'] AND $zz_conf['show_list'])
		require_once($zz_conf['dir'].'/inc/edittab.inc.php');		// Table output with all records
	require_once($zz_conf['dir'].'/inc/editrecord.inc.php');		// update/delete/insert
	require_once($zz_conf['dir'].'/inc/editval.inc.php');			// Basic Validation
	require_once($zz_conf['dir'].'/inc/text-en.inc.php');			// English text
	if ($zz_conf['additional_text'] AND file_exists($langfile = $zz_conf['dir'].'/local/text-'.$zz_conf['language'].'.inc.php'))
		include_once $langfile;
	
	if ($zz_conf['upload']['MAX_FILE_SIZE'] > $upload_max_filesize) {
		$zz_error['msg'] = 'Value for upload_max_filesize from php.ini is smaller than value which is set in the script. The value from php.ini will be used. To upload bigger files, please adjust your configuration settings.';
		$zz_conf['upload']['MAX_FILE_SIZE'] = $upload_max_filesize;
	}
	
	/*
		Optional files
	*/
	
	if (isset($zz_conf['language']) && $zz_conf['language'] != 'en') {	// text in other languages
		$langfile = $zz_conf['dir'].'/inc/text-'.$zz_conf['language'].'.inc.php';
		if (file_exists($langfile)) include_once($langfile);
		else {
			$zz_error['level'] = 'warning';
			$zz_error['msg'] .= 'No language file for <strong>'.$zz_conf['language'].'</strong> found. Using English instead.';
			$zz_error['type'] = 'config';
		}
		if ($zz_conf['additional_text'] AND file_exists($langfile = $zz_conf['dir'].'/local/text-'.$zz_conf['language'].'.inc.php'))
			include_once $langfile;
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
	
	/*
		External Add-ons
	*/
	
	if (file_exists($zz_conf['dir_ext'].'/markdown.php'))
		include_once($zz_conf['dir_ext'].'/markdown.php');
	if (file_exists($zz_conf['dir_ext'].'/textile.php'))
		include_once($zz_conf['dir_ext'].'/textile.php');

	/*
		Variables
	*/
	
	$zz['output'] = '<div id="zzform">'."\n";
	$zz['output'].= zz_error($zz_error); // initialise zz_error
	
	/*
		URL parameter
	*/
	
	// not sure if this is useful for anything
	//if (isset($_GET['tabelle']))	$zz_conf['show_list'] = $_GET['tabelle'];
	
	if (isset($_GET['limit']) && is_numeric($_GET['limit']))	
		$zz_conf['limit'] = (int) $_GET['limit'];
	
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
	$zz_var['values'] = false;
	$zz_var['where'] = false;
	$sql_where = false;
	$zz_tab[0][0]['id']['where'] = false;
	$zz_tab[0][0]['id']['value'] = false;
	if (isset($_GET['value'])) $zz_var['values'] = read_fields($_GET['value'], 'replace', $zz_var['values'], $zz['table']);
	if (isset($_GET['where']))  {
		$zz_var['where'] = read_fields($_GET['where'], 'replace', $zz_var['where'], $zz['table']);
		$sql_where = read_fields($_GET['where'], false, false, $zz['table']);
		if (stristr($zz['sql'], ' WHERE ')) $sql_ext = ' ';
		else $sql_ext = false;
		foreach (array_keys($sql_where) as $field) {
			if (strstr($field, '.')) $myfield = substr($field, strrpos($field, '.')+1);
			else $myfield = $field;
			foreach ($zz['fields'] as $thisfield)
				if (isset($thisfield['type']) && $thisfield['type'] == 'id' AND $myfield == $thisfield['field_name']) {
					$zz_tab[0][0]['id']['where'] = $thisfield['field_name'];
					$zz_tab[0][0]['id']['value'] = $sql_where[$field];
					if (!isset($_GET['mode']) && !isset($_POST['action'])) $_GET['mode'] = 'review';
				} elseif (isset($thisfield['type']) && $thisfield['type'] == 'id')
					foreach ($zz['fields'] as $this_field)
						if (isset($this_field['unique']) && $this_field['unique'] == true && $myfield == $this_field['field_name'])
							$zz_tab[0][0]['id']['where'] = $thisfield['field_name'];  // just for UNIQUE, see below
			if (!$sql_ext) $sql_ext = ' WHERE ';
			else $sql_ext .= ' AND ';
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
			$mfield = $field;
			if (!strstr($field, '.')) $mfield = $zz['table'].'.'.$field; // this makes it unneccessary to add table_name to where-clause
			$sql_ext .= $mfield." = '".$sql_where[$field]."' ";
		}
		$zz['sql'].= $sql_ext;
	
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
		
		foreach (array_keys($_GET['where']) as $mywh) {
			$wh = explode('.', $mywh);
			if (!isset($wh[1])) $index = 0; // without .
			else $index = 1;
			if (isset($zz_conf['heading_sql'][$wh[$index]]) && isset($zz_conf['heading_var'][$wh[$index]])) {
			//	create sql query, with $mywh instead of $wh[$index] because first might be ambiguous
				if (strstr($zz_conf['heading_sql'][$wh[$index]], 'WHERE'))
					$wh_sql = str_replace('WHERE', 'WHERE ('.$mywh.' = '.$_GET['where'][$mywh].') AND ', $zz_conf['heading_sql'][$wh[$index]]);
				elseif (strstr($zz_conf['heading_sql'][$wh[$index]], 'ORDER BY'))
					$wh_sql = str_replace('ORDER BY', 'WHERE ('.$mywh.' = '.$_GET['where'][$mywh].') ORDER BY ', $zz_conf['heading_sql'][$wh[$index]]);
				else
					$wh_sql = $zz_conf['heading_sql'][$wh[$index]].' WHERE ('.$mywh.' = '.$_GET['where'][$mywh].') LIMIT 1';
			//	if key_field_name is set
				foreach ($zz['fields'] as $field)
					if (isset($field['field_name']) && $field['field_name'] == $wh[$index])
						if (isset($field['key_field_name']))
							$wh_sql = str_replace($wh[$index], $field['key_field_name'], $wh_sql);
			//	do query
				$result = mysql_query($wh_sql);
				if (!$result) {
					$zz_error['msg'] = 'Error';
					$zz_error['query'] = $wh_sql;
					$zz_error['mysql'] = mysql_error();
				} else {
					$wh_array = mysql_fetch_assoc($result);
					$zz_conf['heading'].= ':<br>';
					foreach ($zz_conf['heading_var'][$wh[$index]] as $myfield)
						$zz_conf['heading'].= ' '.$wh_array[$myfield];
				}
			}
		}
	}
	
	$zz_conf['title'] = strip_tags($zz_conf['heading']);

	$zz['output'].= "\n".'<h2>';
	$zz['output'].= $zz_conf['heading'];
	$zz['output'].= '</h2>'."\n\n";
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
				include($zz_conf['dir'].'/inc/upload.inc.php');
			}
		}
	if (empty($upload_form)) unset($zz_conf['upload']); // values are not needed
	
	$zz['action'] = false;
	if (isset($_GET['mode'])) {
		$zz['mode'] = $_GET['mode'];
		if ($zz['mode'] == 'edit' OR $zz['mode'] == 'delete')
			$zz_tab[0][0]['id']['value'] = $_GET['id'];
	} else {
		$zz['mode'] = false;
		if (isset($_POST['action']))
			$zz['action'] = $_POST['action'];
			if (isset($_POST[$zz_tab[0][0]['id']['field_name']]))
				$zz_tab[0][0]['id']['value'] = $_POST[$zz_tab[0][0]['id']['field_name']];
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
	if ($zz_conf['limit']) 					$extras.= '&amp;limit='.$zz_conf['limit'];
	if ($zz_conf['referer']) 				$extras.= '&amp;referer='.urlencode($zz_conf['referer']);
	if ($extras)
		if (substr($extras, 0, 1) == '&') $extras = substr($extras, 5);
		 else $extras = substr($extras, 1, strlen($extras) -1 ); 
											// first ? or & to be added as needed!
	if ($extras) 							$zz['extraGET'] = '&amp;'.$extras;
	
	$zz['filetype'] = $zz_conf['export_filetypes'][0]; // default filetype for export
	if (!empty($_GET['filetype'])) // get filetype for export
		if (in_array($_GET['filetype'], $zz_conf['export_filetypes']))
			$zz['filetype'] = $_GET['filetype'];
	
	/*
		Add, Update or Delete
	*/
	
	
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

	foreach (array_keys($zz_tab) as $i)
		foreach (array_keys($zz_tab[$i]) as $k)
			if (is_numeric($k)) $zz_tab[$i][$k] = fill_out($zz_tab[$i][$k]); // set type, title etc. where unset
	$zz = fill_out($zz); // set type, title etc. where unset
	
	$zz['sql'] = zz_search_sql($zz['fields'], $zz['sql'], $zz['table']);	// if q modify $zz['sql']: add search query
	$zz['sql'].= ' '.$zz['sqlorder']; 									// must be here because of where-clause
	$zz['formhead'] = false;
	
	$no_delete_reason = false;
	if ($zz['action'] == 'insert' OR $zz['action'] == 'update' OR $zz['action'] == 'delete')
		zz_action($zz_tab, $zz_conf, $zz, $validation, $upload_form, $no_delete_reason, $subqueries); // check for validity, insert/update/delete record

	/*
		Query Updated, Added or Editable Record
	*/
	
	if (!$validation) {
		if ($zz['action'] == 'update') $zz['mode'] = 'edit';
		elseif ($zz['action'] == 'insert') $zz['mode'] = 'add';
		zz_get_subqueries($subqueries, $zz, $zz_tab, $zz_conf);
	}
	//$zz['output'].= zz_error($zz_error);

	/*
		Display Updated, Added or Editable Record
	*/
	
	// Query for table below record and for value = increment
	// moved to end
	
	if ($zz['mode'] && $zz['mode'] != 'review') {
	
	//	mode = add | edit | delete: show form
		if ($zz['mode'] == 'delete') $display = 'review';
		else $display = 'form';
		$zz['output'].= '<form action="'.$zz_conf['url_self'];
		if ($extras) $zz['output'].= $zz_var['url_append'].$extras;
		$zz['output'].= '" method="POST"';
		if (!empty($upload_form)) $zz['output'] .= ' enctype="multipart/form-data"';
		$zz['output'].= '>';
		$zz['formhead'] = $text[$zz['mode']].' '.$text['a_record'];
	} elseif ($zz['action']) {	
	
	//	action = insert update review: show form with new values
		if ($zz['action'] == 'delete') $display = false;
		else $display = 'review';
		if (!$zz['formhead']) {
			$zz['formhead'] = ucfirst($text[$zz['action']].' '.$text['failed']);
			$display = false;
		}
		if (!empty($no_delete_reason)) {
			$zz['formhead'] = $text['warning'].'!';
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
	
	$result = mysql_query($zz['sql']);  // must be behind update, insert etc. or it will return the wrong number
	if ($result) $zz_lines = mysql_num_rows($result);
	else $zz_lines = 0;
	
	if (isset($zz['formhead']))
		$zz['output'] .= zz_display_records($zz, $zz_tab, $zz_conf, $display, $zz_var);
	else
		$zz['output'] .= zz_error($zz_error); // will be done in zz_display_records as well, after output of formhead

	if ($zz['mode'] && $zz['mode'] != 'review') $zz['output'].= "</form>\n";
	if ($zz['mode'] != 'add' && $zz_conf['add']) $zz['output'].= '<p class="add-new"><a accesskey="n" href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=add'.$zz['extraGET'].'">'.$text['add_new_record'].'</a></p>'."\n";
	if ($zz_conf['referer'] && $zz_conf['backlink']) $zz['output'].= '<p id="back-overview"><a href="'.$zz_conf['referer_esc'].'">'.$text['back-to-overview'].'</a></p>'."\n";
	
	$zz['sql'] = zz_sql_order($zz['fields'], $zz['sql']); // Alter SQL query if GET order (AND maybe GET dir) are set
	
	if ($zz_conf['list'] AND $zz_conf['show_list'])
		zz_display_table($zz, $zz_conf, $zz_error, $zz_var, $zz_lines); // shows table with all records (limited by zz_conf['limit'] and add/nav if limit/search buttons)

	$zz['output'].= '</div>';
	if ($zz_conf['show_output']) echo $zz['output'];
}

function zzform_all() {
//	Die folgenden globalen Definitionen der  Variablen sind nur noetig, wenn man wie
//	hier die darauffolgenden vier Zeilen in einer Funktion zusammenfassen will
	global $zz;			// Table description
	global $zz_conf;	// Config variables
	global $zz_tab;		// Table values, generated by zzform()
	global $zz_page;	// Page (Layout) variables
	$zz_conf['show_output'] = false; // do not show output as it will be included after page head

//	Zusammenbasteln der Seite
	zzform();					// Funktion aufrufen
	include($zz_page['head']);	// Seitenkopf ausgeben, teilw. mit Variablen aus Funktion
	echo $zz['output'];			// Output der Funktion ausgeben
	include($zz_page['foot']);	// Seitenfuss ausgeben
}


?>