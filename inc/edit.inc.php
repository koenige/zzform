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
		$zz_conf['details_url']		what url to follow for detail records					$details_url		
		$zz_conf['referer']			referer which links back to previous page				$referer	
		$zz_conf['add']				do not add data
		$zz_conf['heading']			optional: h2-heading to be used for form instead of $zz['table']
		$zz_conf['heading_text']	Textblock after heading
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
	
	$zz
		$zz['table']				name of main table										$maintable
		$zz['fields']				all fields of a table									$query
			$zz['fields'][n]['title']
			$zz['fields'][n]['title_desc']
			$zz['fields'][n]['field_name']
			$zz['fields'][n]['type']
				id | text | number | url | mail | subtable | foreign_key | memo
			$zz['fields'][n]['hide_in_list']
			$zz['fields'][n]['display_field']
			$zz['fields'][n]['default']
			$zz['fields'][n]['enum']
			$zz['fields'][n]['set']
			$zz['fields'][n]['sql']
			$zz['fields'][n]['rows']
			$zz['fields'][n]['size']
			$zz['fields'][n]['add_details']
			$zz['fields'][n]['link']
			$zz['fields'][n]['link_no_append']		don't append record id to link
			$zz['fields'][n]['sql_without_id']
			$zz['fields'][n]['sql_where']
			$zz['fields'][n]['key_field_name']		field name differs from foreign_key, e. g. mother_org_id instead of org_id
			$zz['fields'][n]['sql_index_only']		only show index field (e. g. for SHOW COLUMNS ... queries)
			$zz['fields'][n]['number_type']			latitude | longitude
			$zz['fields'][n]['factor']				for doubles etc. factor will be multiplied with value
			$zz['fields'][n]['function']			function which will be called to change input value
			$zz['fields'][n]['fields']				vars which will be passed to function
			$zz['fields'][n]['show_id']				?
			$zz['fields'][n]['auto_value']			increment | ...
			$zz['fields'][n]['null']				value might be 0
			$zz['fields'][n]['append_next']			false | true; appends next record in form view
			$zz['fields'][n]['suffix']				adds suffix-string to form view	
			$zz['fields'][n]['suffix_function']		adds suffix-function to form view	
			$zz['fields'][n]['suffix_function_var']	parameters for suffix-function to form view	(array)
			$zz['fields'][n]['prefix']				adds prefix-string to form view	
			$zz['fields'][n]['order']				set order, e. g. for mixed alpha-numerical strings without preceding zeros
			$zz['fields'][n]['show_title']			Show field title in TH
			$zz['fields'][n]['table_name']			Alias if more subtables are included (used for search only, AFAIK)
			$zz['fields'][n]['unique']				if field value is unique, it can be used for where-clauses and will show only one record in display mode, without add new record
			$zz['fields'][n]['explanation']			explanation how to fill in values in this field, will only be shown in edit or insert mode
			$zz['fields'][n]['upload-field'] = 8;
			$zz['fields'][n]['upload-value'] = 'exif[FileName]'; // possible values:
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
				
			
		$zz['sql']
		$zz['sqlorder']
				
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

		$zz_tab[1]['table']
		$zz_tab[1]['no']			= n in $zz['fields'][n]
		$zz_tab[1]['records']		number of subrecords
		$zz_tab[1]['max_records']	max. subrecords
		$zz_tab[1]['min_records']	min. subrecords

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

	foreach (array_keys($zz_default) as $key)
		if (!isset($zz_conf[$key])) $zz_conf[$key] = $zz_default[$key];

	if (!isset($zz_conf['url_self'])) {
		$zz_conf['url_self'] = parse_url($_SERVER['REQUEST_URI']);
		$zz_conf['url_self'] = $zz_conf['url_self']['path'];
	}
	
	/*
		Required files
	*/
	
	require_once($zz_conf['dir'].'/inc/editform.inc.php');			// Form
	require_once($zz_conf['dir'].'/inc/editfunc.inc.php');			// Functions
	require_once($zz_conf['dir'].'/inc/editval.inc.php');			// Basic Validation
	require_once($zz_conf['dir'].'/inc/text-en.inc.php');			// English text
	
	$zz_error['msg'] = '';
	$zz_error['query'] = '';
	$zz_error['level'] = '';
	$zz_error['type'] = '';
	$zz_error['mysql'] = '';
	
	/*
		Optional files
	*/
	
	if (isset($zz_conf['language']) && $zz_conf['language'] != 'en') {	// text in other languages
		$langfile = $zz_conf['dir'].'/inc/text-'.$zz_conf['language'].'.inc.php';
		if (file_exists($langfile)) include_once($langfile);
		else {
			$zz_error['level'] = 'warning';
			$zz_error['msg'] = 'No language file for <strong>'.$zz_conf['language'].'</strong> found. Using English instead.';
			$zz_error['type'] = 'config';
		}
	}
	// todo: if file exists else lang = en
	if (!isset($zz_conf['db_connection'])) include_once ($zz_conf['dir'].'/local/db.inc.php');
	if (!empty($zz_conf['db_name'])) mysql_select_db($zz_conf['db_name']);
	if (!function_exists('datum_de')) include ($zz_conf['dir'].'/inc/numbers.inc.php');
	if (file_exists($zz_conf['dir'].'/inc/geo/dec2dms.inc.php')) 
		include_once($zz_conf['dir'].'/inc/geo/dec2dms.inc.php');
	if (file_exists($zz_conf['dir'].'/inc/geo/coords.inc.php'))
		include_once($zz_conf['dir'].'/inc/geo/coords.inc.php');
	if (file_exists($zz_conf['dir'].'/inc/geo/coords-edit.inc.php'))
		include_once($zz_conf['dir'].'/inc/geo/coords-edit.inc.php');
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
			$sql_ext .= $field." = '".$sql_where[$field]."' ";
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
			elseif (substr($zz['fields'][$i]['type'], 0, 7) == 'upload-') {// at least one upload field adds enctype-field to form
				$upload_form = true;
				include($zz_conf['dir'].'/inc/upload.inc.php');
			}
		}
	
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
	
	if ($subqueries && $zz['action'] != 'delete') {
		$i = 1;
		foreach ($subqueries as $subquery) {
			$zz_tab[$i]['table'] = $zz['fields'][$subquery]['table'];
			$zz_tab[$i]['table_name'] = $zz['fields'][$subquery]['table_name'];
			$zz_tab[$i]['max_records'] = (isset($zz['fields'][$subquery]['max_records'])) ? $zz['fields'][$subquery]['max_records'] : $zz_conf['max_detail_records'];
			$zz_tab[$i]['min_records'] = (isset($zz['fields'][$subquery]['min_records'])) ? $zz['fields'][$subquery]['min_records'] : $zz_conf['min_detail_records'];
			$zz_tab[$i]['no'] = $subquery;
			$zz_tab[$i]['sql'] = $zz['fields'][$subquery]['sql'];
			if ($zz['mode']) {
				if ($zz['mode'] == 'add')
					$zz_tab[$i] = zz_subqueries($i, true, true, false, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // min, details
				elseif ($zz['mode'] == 'edit')
					$zz_tab[$i] = zz_subqueries($i, true, true, true, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // min, details, sql
				elseif ($zz['mode'] == 'delete')
					$zz_tab[$i] = zz_subqueries($i, false, false, true, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // sql
				elseif ($zz['mode'] == 'review')
					$zz_tab[$i] = zz_subqueries($i, false, false, true, $zz['fields'][$zz_tab[$i]['no']], $zz_tab); // sql
			} elseif ($zz['action'] && is_array($_POST[$zz['fields'][$subquery]['table_name']])) {
				foreach (array_keys($_POST[$zz['fields'][$subquery]['table_name']]) as $subkey) {
					$zz_tab[$i][$subkey]['fields'] = $zz['fields'][$zz_tab[$i]['no']]['fields'];
					$zz_tab[$i][$subkey]['validation'] = true;
					$zz_tab[$i][$subkey]['record'] = false;
					$zz_tab[$i][$subkey]['action'] = false;
					foreach ($zz_tab[$i][$subkey]['fields'] as $field)
						if (isset($field['type']) && $field['type'] == 'id') 
							$zz_tab[$i][$subkey]['id']['field_name'] = $field['field_name'];
					$table = $zz['fields'][$subquery]['table_name'];
					$field_name = $zz_tab[$i][$subkey]['id']['field_name'];
					if (isset($_POST[$table][$subkey][$field_name])) 
						$zz_tab[$i][$subkey]['id']['value'] = $_POST[$table][$subkey][$field_name];
					else
						$zz_tab[$i][$subkey]['id']['value'] = '';
				}
			}
			$i++;
		}
		unset($i);	
		if (isset($_POST['subtables'])) $validation = false;
	}
	
	foreach (array_keys($zz_tab) as $i)
		foreach (array_keys($zz_tab[$i]) as $k)
			if (is_numeric($k)) $zz_tab[$i][$k] = fill_out($zz_tab[$i][$k]); // set type, title etc. where unset
	$zz = fill_out($zz); // set type, title etc. where unset
	
	//						echo '<pre>';
	//						print_r($zz_tab);
	//						echo '</pre>';
	
	$zz['sql'] = zz_search_sql($zz['fields'], $zz['sql'], $zz['table']);	// if q modify $zz['sql']: add search query
	$zz['sql'].= ' '.$zz['sqlorder']; 									// must be here because of where-clause
	$zz['formhead'] = false;
	
	if ($zz['action'] == 'insert' OR $zz['action'] == 'update' OR $zz['action'] == 'delete') {
	
	//	### Check for validity, do some operations ###
		foreach (array_keys($zz_tab) as $i)
			foreach (array_keys($zz_tab[$i]) as $k) {
				if (!empty($upload_form) && $zz_tab[0][0]['action'] != 'delete' && is_numeric($k)) // do only for zz_tab 0 0 etc. not zz_tab 0 sql
					$zz_tab[$i][$k]['images'] = zz_get_upload($zz_tab[$i][$k]); // read upload image information, as required
				if ($i && is_numeric($k)) {
				// only if $i and $k != 0, i. e. only for subtables!
					$zz_tab[$i][$k]['POST'] = $_POST[$zz['fields'][$zz_tab[$i]['no']]['table_name']][$k];
					$values = '';
					// check whether there are values in fields
					foreach ($zz_tab[$i][$k]['fields'] as $field) {
						if (isset($zz_tab[$i][$k]['POST'][$field['field_name']]))
							if ($field['type'] == 'number' && isset($field['number_type']) && ($field['number_type'] == 'latitude' OR $field['number_type'] == 'longitude')) {
								// coordinates:
								// rather problematic stuff because this input is divided into several fields
								$t_coord = $zz_tab[$i][$k]['POST'][$field['field_name']];
								if ($t_coord['which'] == 'dms') {
									if (isset($t_coord['lat'])) $t_sub = 'lat';
									else $t_sub = 'lon';
									$values .= $t_coord[$t_sub]['deg'];
									$values .= $t_coord[$t_sub]['min'];
									$values .= $t_coord[$t_sub]['sec'];
								} else 
									$values .= $t_coord['dec'];
							} elseif ($field['type'] != 'timestamp' && $field['type'] != 'id')
								if (!(isset($field['default']) && $field['default'] && $field['default'] == $zz_tab[$i][$k]['POST'][$field['field_name']]) // default values will be ignored
									AND !isset($field['auto_value'])) // auto values will be ignored 
									$values .= $zz_tab[$i][$k]['POST'][$field['field_name']];
						if ($field['type'] == 'id')
							if (!isset($zz_tab[$i][$k]['POST'][$field['field_name']]))
								$zz_tab[$i][$k]['action'] = 'insert';
							else
								$zz_tab[$i][$k]['action'] = 'update';
					}
					// todo: seems to be twice the same operation since $i and $k are !0
					if (!$values)
						if ($zz_tab[$i][$k]['id']['value'])
							$zz_tab[$i][$k]['action'] = 'delete';
						else
							unset($zz_tab[$i][$k]);
					if ($zz_tab[0][0]['action'] == 'delete') 
						if ($zz_tab[$i][$k]['id']['value'])
							$zz_tab[$i][$k]['action'] = 'delete';
						else
							unset($zz_tab[$i][$k]);
				}
				if (!isset($zz_tab[$i]['table_name'])) $zz_tab[$i]['table_name'] = $zz_tab[$i]['table'];
				if (isset($zz_tab[$i][$k]))
					if ($zz_tab[$i][$k]['action'] == 'insert' OR $zz_tab[$i][$k]['action'] == 'update')
					// do something with the POST array before proceeding
						$zz_tab[$i][$k] = zz_validate($zz_tab[$i][$k], $zz_conf, $zz_tab[$i]['table'], $zz_tab[$i]['table_name']); 
					elseif (is_numeric($k))
					//	Check referential integrity
						if (file_exists($zz_conf['dir'].'/inc/integrity.inc.php')) {
							include_once($zz_conf['dir'].'/inc/integrity.inc.php');
					//test
							$record_idfield = $zz_tab[$i][$k]['id']['field_name'];
							$detailrecords = '';
							//echo '<pre>';
							//print_r($zz_tab);
							//echo '</pre>';
							if (!$no_delete_reason = check_integrity($zz_conf['db_name'], $zz_tab[$i]['table'], $record_idfield, $zz_tab[$i][$k]['POST'][$record_idfield], $zz_conf['relations_table'], $detailrecords))
							// todo: remove db_name maybe?
								$zz_tab[$i][$k]['validation'] = true;
							else $zz_tab[$i][$k]['validation'] = false;
						}
			}
	
		if (isset($zz_conf['action']['before_'.$zz['action']]))
			include ($zz_conf['action_dir'].'/'.$zz_conf['action']['before_'.$zz['action']].'.inc.php'); 
				// if any other action before insertion/update/delete is required
		
		foreach ($zz_tab as $subtab) foreach (array_keys($subtab) as $subset)
			if (is_numeric($subset))
				if (!$subtab[$subset]['validation'])
					$validation = false;
		
		if ($validation) {
			// put delete_ids into zz_tab-array
			if (isset($_POST['deleted']))
				foreach (array_keys($_POST['deleted']) as $del_tab) {
					foreach (array_keys($zz_tab) as $i)
						if ($i) if ($zz_tab[$i]['table_name'] == $del_tab) $tabindex = $i;
					foreach ($_POST['deleted'][$del_tab] as $idfield) {
						$my['action'] = 'delete';
						$my['id']['field_name'] = key($idfield);
						$my['POST'][$my['id']['field_name']] = $idfield[$my['id']['field_name']];
						$zz_tab[$tabindex][] = $my;
					}
				}
			$sql_edit = '';
			foreach (array_keys($zz_tab) as $i)
				foreach (array_keys($zz_tab[$i]) as $me) if (is_numeric($me)) {
					//echo 'rec '.$i.' '.$me.'<br>';
			//	### Insert a record ###
			
				if ($zz_tab[$i][$me]['action'] == 'insert') {
					$field_values = '';
					$field_list = '';
					foreach ($zz_tab[$i][$me]['fields'] as $field)
						if ($field['in_sql_query']) {
							if ($field_list) $field_list .= ', ';
							$field_list .= $field['field_name'];
							if ($field_values && $field['type']) $field_values.= ', ';
							//if ($me == 0 OR $field['type'] != 'foreign_key')
								$field_values .= $zz_tab[$i][$me]['POST'][$field['field_name']];
						}
					$me_sql = ' INSERT INTO '.$zz_tab[$i]['table'];
					$me_sql .= ' ('.$field_list.') VALUES ('.$field_values.')';
					
			// ### Update a record ###
		
				} elseif ($zz_tab[$i][$me]['action'] == 'update') {
					$update_values = '';
					foreach ($zz_tab[$i][$me]['fields'] as $field)
						if ($field['type'] != 'subtable' AND $field['type'] != 'id' && $field['in_sql_query']) {
							if ($update_values) $update_values.= ', ';
							$update_values.= $field['field_name'].' = '.$zz_tab[$i][$me]['POST'][$field['field_name']];
						}
					$me_sql = ' UPDATE '.$zz_tab[$i]['table'];
					$me_sql.= ' SET '.$update_values.' WHERE '.$zz_tab[$i][$me]['id']['field_name'].' = "'.$zz_tab[$i][$me]['id']['value'].'"';
				
			// ### Delete a record ###
		
				} elseif ($zz_tab[$i][$me]['action'] == 'delete') {
					$me_sql= ' DELETE FROM '.$zz_tab[$i]['table'];
					$id_field = $zz_tab[$i][$me]['id']['field_name'];
					$me_sql.= ' WHERE '.$id_field." = '".$zz_tab[$i][$me]['POST'][$id_field]."'";
					$me_sql.= ' LIMIT 1';
				}
	
				if (!$sql_edit) $sql_edit = $me_sql;
				else 			$detail_sql_edit[$i][$me] = $me_sql;
			}
			// ### Do mysql-query and additional actions ###
			
			$result = mysql_query($sql_edit);
			if ($result) {
				if ($zz_tab[0][0]['action'] == 'insert') $zz['formhead'] = $text['record_was_inserted'];
				elseif ($zz_tab[0][0]['action'] == 'update') $zz['formhead'] = $text['record_was_updated'];
				elseif ($zz_tab[0][0]['action'] == 'delete') $zz['formhead'] = $text['record_was_deleted'];
				if ($zz_tab[0][0]['action'] == 'insert') $zz_tab[0][0]['id']['value'] = mysql_insert_id(); // for requery
				if ($zz_conf['logging']) zz_log_sql($sql_edit, $zz_conf['user']); // Logs SQL Query, must be after insert_id was checked
				if (isset($detail_sql_edit))
					foreach (array_keys($detail_sql_edit) as $i)
						foreach (array_keys($detail_sql_edit[$i]) as $k) {
							$detail_sql = $detail_sql_edit[$i][$k];
							$detail_sql = str_replace('[FOREIGN_KEY]', '"'.$zz_tab[0][0]['id']['value'].'"', $detail_sql);
							//if ($zz['action'] == 'insert') $detail_sql .= $zz_tab[0][0]['id']['value'].');';
							$detail_result = mysql_query($detail_sql);
							if (!$detail_result) {
								$zz['formhead'] = false;
								$zz_error['msg']	.= 'Detail record could not be handled';
								$zz_error['level']	.= 'crucial';
								$zz_error['type']	.= 'mysql';
								$zz_error['query']	.= $detail_sql;
								$zz_error['mysql']	.= mysql_error();
	
							} elseif ($zz_tab[$i][$k]['action'] == 'insert') 
								$zz_tab[$i][$k]['id']['value'] = mysql_insert_id(); // for requery
							if ($zz_conf['logging']) zz_log_sql($detail_sql, $zz_conf['user']); // Logs SQL Query
						}
				if (isset($zz_conf['action']['after_'.$zz['action']])) 
					include ($zz_conf['action_dir'].'/'.$zz_conf['action']['after_'.$zz['action']].'.inc.php'); 
					// if any other action after insertion/update/delete is required
				if (!empty($upload_form) && $zz_tab[0][0]['action'] != 'delete')
					zz_write_upload($zz); // upload images, as required
				//elseif(!empty($upload_form) && $zz_tab[0][0]['action'] == 'delete') 
					// todo: delete images
			} else {
				// Output Error Message
				$zz['formhead'] = false;
				if ($zz['action'] == 'insert') $zz_tab[0][0]['id']['value'] = false; // for requery
				$zz_error['msg']	.= $zz['action'].' failed';
				$zz_error['level']	.= 'crucial';
				$zz_error['type']	.= 'mysql';
				$zz_error['query']	.= $sql_edit;
				$zz_error['mysql']	.= mysql_error();
			}
		}
	}
	
	/*
		Query Updated, Added or Editable Record
	*/
	
	if (!$validation)
		if ($zz['action'] == 'update') $zz['mode'] = 'edit';
		elseif ($zz['action'] == 'insert') $zz['mode'] = 'add';
	
	$zz['output'].= zz_error($zz_error);
	
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
			if ($extras) $zz['output'].= '?'.$extras;
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
				$zz_error['msg'].= ' '.$text[$no_delete_reason['text']].'</p>'."\n";
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
		$zz['output'].= zz_error($zz_error); // will be done in zz_display_records as well, after output of formhead
	
	if ($zz['mode'] != 'add' && $zz_conf['add']) $zz['output'].= '<p class="add-new"><a accesskey="n" href="'.$zz_conf['url_self'].'?mode=add'.$zz['extraGET'].'">'.$text['add_new_record'].'</a></p>'."\n";
	if ($zz_conf['referer'] && $zz_conf['backlink']) $zz['output'].= '<p id="back-overview"><a href="'.$zz_conf['referer'].'">'.$text['back-to-overview'].'</a></p>'."\n";
	
	
	// Display
	// Elemente der Tabelle herausnehmen, die nicht angezeigt werden sollen
	
	foreach ($zz['fields'] as $field)
		if (!isset($field['hide_in_list'])) $table_query[] = $field;
		else if (!$field['hide_in_list']) $table_query[] = $field;
	
	//
	// Table head
	//
	
	// ORDER BY
	
	$zz['sql'] = zz_sql_order($zz['fields'], $zz['sql']); // Alter SQL query if GET order (AND maybe GET dir) are set

	if ($zz_conf['list'] AND $zz_conf['show_list']) {
		if ($zz_conf['limit']) $zz['sql'].= ' LIMIT '.($zz_conf['limit']-20).', 20';
		$result = mysql_query($zz['sql']);
		if ($result) $count_rows = mysql_num_rows($result);
		else {
			$count_rows = 0;
			$zz['output'].= zz_error($zz_error = array(
				'mysql' => mysql_error(), 
				'query' => $zz['sql'], 
				'msg' => $text['error-sql-incorrect']));
		}
		if ($result && $count_rows > 0) {
			$zz['output'].= '<table class="data">';
			$zz['output'].= '<thead>'."\n";
			$zz['output'].= '<tr>';
			foreach ($table_query as $field) {
				$zz['output'].= '<th';
				$zz['output'].= check_if_class($field, $zz_var['where']);
				$zz['output'].= '>';
				if ($field['type'] != 'calculated' && $field['type'] != 'image' && isset($field['field_name'])) { //  && $field['type'] != 'subtable'
					$zz['output'].= '<a href="';
					if (isset($field['display_field'])) $order_val = $field['display_field'];
					else $order_val = $field['field_name'];
					$uri = addvar($_SERVER['REQUEST_URI'], 'order', $order_val);
					$order_dir = 'asc';
					if (str_replace('&amp;', '&', $uri) == $_SERVER['REQUEST_URI']) {
						$uri.= '&amp;dir=desc';
						$order_dir = 'desc';
					}
					$zz['output'].= $uri;
					$zz['output'].= '" title="'.$text['order by'].' '.strip_tags($field['title']).' ('.$text[$order_dir].')">';
				}
				if (isset($zz_conf['multilang_fieldnames']) && $zz_conf['multilang_fieldnames']) $zz['output'].= $text[$field['title']];
				else $zz['output'].= $field['title'];
				if ($field['type'] != 'calculated')
					$zz['output'].= '</a>';
				$zz['output'].= '</th>';
			}
			if ($zz_conf['edit'])
				$zz['output'].= ' <th class="editbutton">'.$text['action'].'</th>';
			if (isset($zz_conf['details']) && $zz_conf['details']) $zz['output'].= ' <th class="editbutton">'.$text['detail'].'</th>';
			$zz['output'].= '</tr>';
			$zz['output'].= '</thead>'."\n";
			$zz['output'].= '<tbody>'."\n";
		} else {
			$zz_conf['show_list'] = false;
			$zz['output'].= '<p>'.$text['table-empty'].'</p>';
		}
	//
	// Table body
	//	
		if ($result) {
			if (mysql_num_rows($result) > 0) {
				$z = 0;
				while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
					$zz['output'].= '<tr class="';
					$zz['output'].= ($z & 1 ? 'uneven':'even');
					$zz['output'].= '">'; //onclick="Highlight();"
					$id = '';
					foreach ($table_query as $field) {
						$zz['output'].= '<td';
						$zz['output'].= check_if_class($field, $zz_var['where']);
						$zz['output'].= '>';
						if ($field['type'] == 'calculated') {
							if ($field['calculation'] == 'hours') {
								$diff = 0;
								foreach ($field['calculation_fields'] as $calc_field) {
									if (!$diff) $diff = strtotime($line[$calc_field]);
									else $diff -= strtotime($line[$calc_field]);
								}
								$zz['output'].= gmdate('H:i', $diff);
								if (isset($field['sum']) && $field['sum'] == true) {
									if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
									$sum[$field['title']] += $diff;
								}
							} elseif ($field['calculation'] == 'sum') {
								$my_sum = 0;
								foreach ($field['calculation_fields'] as $calc_field) {
									$my_sum += $line[$calc_field];
								}
								$zz['output'].= $my_sum;
								if (isset($field['sum']) && $field['sum'] == true) {
									if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
									$sum[$field['title']] .= $my_sum;
								}
							} elseif ($field['calculation'] == 'sql') {
								$zz['output'].= $line[$field['field_name']];
							}
						} elseif ($field['type'] == 'image' OR $field['type'] == 'upload-image') {
							if (isset($field['path'])) {
								$img = show_image($field['path'], $line);
								if ($img) {
									if (isset($field['link'])) {
										if (is_array($field['link'])) {
											$zz['output'].= '<a href="'.show_link($field['link'], $line);
											if (!isset($field['link_no_append'])) $zz['output'].= $line[$field['field_name']];
											$zz['output'].= '">';
										} else $zz['output'].= '<a href="'.$field['link'].$line[$field['field_name']].'">';
									} 
									$zz['output'].= $img;
									if (isset($field['link'])) $zz['output'] .= '</a>';
								}
							}	
						} elseif ($field['type'] == 'thumbnail' && $line[$field['field_name']]) {
							$zz['output'].= '<img src="'.$zz_conf['dir'].'/'.$line[$field['field_name']].'" alt="'.$line[$field['field_name']].'">';
						} elseif ($field['type'] == 'subtable') {
							// Subtable
							if (isset($field['display_field'])) $zz['output'].= htmlchars($line[$field['display_field']]);
						} else {
							if ($field['type'] == 'url') $zz['output'].= '<a href="'.$line[$field['field_name']].'">';
							if ($field['type'] == 'mail') $zz['output'].= '<a href="mailto:'.$line[$field['field_name']].'">';
							if (isset($field['link'])) {
								if (is_array($field['link'])) {
									$zz['output'].= '<a href="'.show_link($field['link'], $line);
									if (!isset($field['link_no_append'])) $zz['output'].= $line[$field['field_name']];
									$zz['output'].= '">';
								} else $zz['output'].= '<a href="'.$field['link'].$line[$field['field_name']].'">';
							}
							if (isset($field['display_field'])) $zz['output'].= htmlchars($line[$field['display_field']]);
							else {
								if (isset($field['factor']) && $line[$field['field_name']]) $line[$field['field_name']] /=$field['factor'];
								if ($field['type'] == 'date') $zz['output'].= datum_de($line[$field['field_name']]);
								elseif (isset($field['number_type']) && $field['number_type'] == 'currency') $zz['output'].= waehrung($line[$field['field_name']], '');
								elseif (isset($field['number_type']) && $field['number_type'] == 'latitude' && $line[$field['field_name']]) {
									$deg = dec2dms($line[$field['field_name']], '');
									$zz['output'].= $deg['latitude'];
								} elseif (isset($field['number_type']) && $field['number_type'] == 'longitude' &&  $line[$field['field_name']]) {
									$deg = dec2dms('', $line[$field['field_name']]);
									$zz['output'].= $deg['longitude'];
								}
								else $zz['output'].= nl2br(htmlchars($line[$field['field_name']]));
							}
							if ($field['type'] == 'url') $zz['output'].= '</a>';
							if (isset($field['link'])) $zz['output'].= '</a>';
							if (isset($field['sum']) && $field['sum'] == true) {
								if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
								$sum[$field['title']] += $line[$field['field_name']];
							}
						}
						if (isset($field['unit'])) 
							/* && $line[$field['field_name']]) does not work because of calculated fields*/ 
							$zz['output'].= '&nbsp;'.$field['unit'];	
						$zz['output'].= '</td>';
						if ($field['type'] == 'id') $id = $line[$field['field_name']];
					}
					if ($zz_conf['edit']) {
						$zz['output'].= '<td class="editbutton"><a href="'.$zz_conf['url_self'].'?mode=edit&amp;id='.$id.$zz['extraGET'].'">'.$text['edit'].'</a>';
						if ($zz_conf['delete']) $zz['output'].= '&nbsp;| <a href="'.$zz_conf['url_self'].'?mode=delete&amp;id='.$id.$zz['extraGET'].'">'.$text['delete'].'</a>';
						if (isset($zz_conf['details'])) {
							$zz['output'].= '</td><td class="editbutton">';
							$zz['output'].= show_more_actions($zz_conf['details'], $zz_conf['details_url'], $id, $line);
						}
						$zz['output'].= '</td>';
					}
					$zz['output'].= '</tr>'."\n";
					$z++;
				}
			}
		}	
	
	// Table footer
	
		$zz['output'].= '</tbody>'."\n";
		
		if ($zz_conf['tfoot'] && isset($z)) {
			$zz['output'].= '<tfoot>'."\n";
			$zz['output'].= '<tr>';
			foreach ($table_query as $field) {
				if ($field['type'] == 'id') $zz['output'].= '<td class="recordid">'.$z.'</td>';
				elseif (isset($field['sum']) AND $field['sum'] == true) {
					$zz['output'].= '<td>';
					if (isset($field['calculation']) AND $field['calculation'] == 'hours')
						$sum[$field['title']] = hours($sum[$field['title']]);
					$zz['output'].= $sum[$field['title']];
					if (isset($field['unit'])) $zz['output'].= '&nbsp;'.$field['unit'];	
					$zz['output'].= '</td>';
				}
				else $zz['output'].= '<td>&nbsp;</td>';
			}
			$zz['output'].= '<td class="editbutton">&nbsp;</td>';
			$zz['output'].= '</tr>'."\n";
			$zz['output'].= '</tfoot>'."\n";
		}
	
		$zz['output'].= '</table>'."\n";
	
		if ($zz['mode'] != 'add' && $zz_conf['add'] && $zz_conf['show_list'])
			$zz['output'].= '<p class="add-new bottom-add-new"><a accesskey="n" href="'.$zz_conf['url_self'].'?mode=add'.$zz['extraGET'].'">'.$text['add_new_record'].'</a></p>';
		if ($zz_lines == 1) $zz['output'].= '<p class="totalrecords">'.$zz_lines.' '.$text['record total'].'</p>'; 
		elseif ($zz_lines) $zz['output'].= '<p class="totalrecords">'.$zz_lines.' '.$text['records total'].'</p>';
		$zz['output'].= zz_limit($zz_conf['limit'], $count_rows, $zz['sql'], $zz_lines, 'body');	// NEXT, PREV Links at the end of the page
		//$zz_conf['links'] = zz_limit($zz_conf['limit'], $count_rows, $zz['sql'], $zz_lines, 'body');	// NEXT, PREV Links at the end of the page
		if ($zz_conf['search'] == true) 
			if ($zz_lines OR isset($_GET['q'])) // show search form only if there are records as a result of this query; q: show search form if empty search result occured as well
				$zz['output'].= zz_search_form($zz_conf['url_self'], $zz['fields'], $zz['table']);
	}
	
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