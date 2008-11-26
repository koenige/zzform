
/*	----------------------------------------------	*
 *			README									*
 *	----------------------------------------------	*/

zzform readme
(c) 2006-2008 Gustaf Mossakowski, gustaf@koenige.org

required: at least PHP 4.1.2 
lower PHP versions have not been tested
- mysql_real_escape_string: above 4.3 (addslashes will be used in PHP versions prior)
- exif_read_info: above 4.2 (no exif data will be read in PHP versions prior)

Remarks:
- ID field has to be $zz['fields'][1], see edit_functions for reason why.
- when linking tables (subtables, details), special care has to be taken that the key
field names are alike

/*	----------------------------------------------	*
 *			MAIN CONFIGURATION						*
 *	----------------------------------------------	*/

$zz_conf - configuration variables
	$zz_conf['dir']				directory in which zzform resides in
	$zz_conf['tmp_dir']			TemporŠres Verzeichnis fŸr Bilduploads
	$zz_conf['language']		language of zzform
	$zz_conf['search']			search records possible or not 
		(true or bottom: below list, top = above list, both = below and above list)
		\ as first string in search query will be removed (escapes reserved symbols)
		> greater than ... (should be used with scope set)
		< lesser than ... (should be used with scope set)
		[nothing] LIKE %...%
	$zz_conf['delete']			delete records possible or not
	$zz_conf['view']			view records, this will only be enabled if edit records is turned off
	$zz_conf['do_validation']	backwards compatiblity to old edit.inc, to be removed in the future
	$zz_conf['limit']			display only limited amount of records (20)
	$zz_conf['limit_show_range'] = 800;		// range in which links to records around current selection will be shown
	$zz_conf['show_list']		display list of records in database
	$zz_conf['list_display']	form of list display, defaults to table, other possibilities include "ul"
	$zz_conf['show_output']		
	$zz_conf['url_self']		own url or target url									$self
	$zz_conf['details']			column details; links to detail records with foreign key	$details	
	$zz_conf['details_base']	array, corresponding to details, 
		does not make use of details as first part of details_url but this field instead
	$zz_conf['details_url']		what url to follow for detail records					$details_url		
								may be array e. g. array('field1' => 'fieldname_bla', 'string1' => '/', 'field2' => 'fieldname_blubb') etc.
	$zz_conf['details_target']	target window for details link	
	$zz_conf['details_referer']	// add referer to details link
	$zz_conf['referer']			referer which links back to previous page				$referer	
	$zz_conf['dynamic_referer']	referer which links back to previous page, 
		overwrites 'referer' and must be array with field1, string1 etc.
	$zz_conf['add']				do not add data

	$zz_conf['access']			default: all
		add_only: only allow to add record, do not show anything else (add new record-link, list table, ...)
		edit_only: only allow to edit record, do not show anything else (add new record-link, list table, ...)
		show: only view records
		show_edit_add
		show_and_delete
		add_then_edit: only allow to add a new record, then to reedit this record if it already exists (only works with GET['where']...)
		edit_details_only: do not edit main record, allow only detail records to be edited, no deletion and adding possible
		none: only show list, no possibility to do anything with the records
	$zz_conf['heading']			optional: h2-heading to be used for form instead of $zz['table']
	$zz_conf['heading_text']	Textblock after heading
	$zz_conf['footer_text']		Textblock at the end of div id zzform
	$zz_conf['heading_sql']		['heading_sql'][$where_id, without tablename] = zz['fields'][n]['sql'] where n is the index of the field corresponding to the key
	$zz_conf['heading_enum']	['heading_enum'][$where_id, without tablename] = zz['fields'][n]['enum'] where n is the index of the field corresponding to the key
	$zz_conf['heading_var']		['heading_var'][$where_id, without tablename] = array() field from heading_sql-query which shall be used for better display of H2 and TITLE blabla:<br>var1 var2 var3
		-- the corresponding field may be hidden from the form and the list with
		if (isset($_GET['where']['gebaeude.gebaeude_id'])) {
			$zz['fields'][2]['class'] = 'hidden';
			$zz['fields'][2]['hide_in_list'] = true;
		}
	$zz_conf['title']			= heading, but without HTML tags
	$zz_conf['prefix']			table_prefix like zz_ (will be removed in error output)
	$zz_conf['action']			action to be performed after or before insert, update, delete
								array values: before_update, before_insert, before_delete, after_update, after_insert, after_delete
								value: file to be included without .inc.php
								if you do insert/update/delete queries, you might want to add them to the logging table
								with zz_log_sql($sql, $user, $record_id); ($sql being the query, $user the username)
								old: $query_action
	$zz_conf['action_dir']		Directory where included scripts from $zz_conf['action'] reside, default: $zz_conf['dir'].'/local'
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
	$zz_conf['logging_id']		logging of record_id enabled? default: false
	$zz_conf['logging_table']	table where logging will be written into, default: _logging
	$zz_conf['max_select_val_len']	maximum length of values in select, default = 60
	$zz_conf['debug']			debugging mode, shows several debugging outputs
	$zz_conf['debug_allsql']	shows even more sql queries. squeezes all of them out of zzform. (all but some password related queries)
	$zz_conf['max_select']		configures the maximum entries in a select-dialog, if there are more entries, an empty input field will be provided
	$zz_conf['redirect']['successful_update']	redirect to this URL (local, starting with / or full qualified URI) when this event occurs
	$zz_conf['redirect']['successful_insert']	redirect to this URL (local, starting with / or full qualified URI) when this event occurs
	$zz_conf['redirect']['successful_delete']	redirect to this URL (local, starting with / or full qualified URI) when this event occurs
	$zz_conf['folder'][]		array with root, string, field, mode for a folder that must be renamed/deleted after changing the record

	$zz_conf['show_hierarchy'] = true;	display table in a hierarchical view, instead of true, an ID value might be used as well
	$zz_conf['hierarchy']['mother_id_field_name']	field_name: mother ID, to get hierarchical view
	$zz_conf['hierarchy']['display_in'] 			field_name where hierarchy shall be displayed (level0...10)
	$zz_conf['format']['markdown']['link']	Link to markdown help page; similarly use ['format'][$format]['link'] for other formats

/*	----------------------------------------------	*
 *		MAIN CONFIGURATION (UPLOAD MODULE)			*
 *	----------------------------------------------	*/

	$zz_conf['backup']			do a backup of old files?
	$zz_conf['backup_dir']		directory where old files shall be backed up to, default: zz_conf['dir'].backup
	$zz_conf['graphics_library'] graphics library used for image manipulation (imagemagick is default, others are currently not supported)
	$zz_conf['upload_MAX_FILE_SIZE'] in bytes, default is value from php.ini
	$zz_conf['upload_ini_max_filesize'] = ini_get('upload_max_filesize'); // must not be changed
	$zz_conf['imagemagick_paths'] = array('/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/phpbin'); 
	$zz_conf['image_types']		Image filetypes, supported by PHP, should only be changed if PHP supports more.
	$zz_conf['file_types']		Known filetypes, array with array for each file_type ('filetype', 'ext_old', 'ext', 'mime', 'desc')	
	$zz_conf['mime_types_rewritten']	array('unwanted_mimetype' => 'wanted_mimetype') e. g. for image/pjpeg = image/jpeg
	$zz_conf['exif_supported'] = array('jpeg', 'tiff'); // filetypes that support exif.

/*	----------------------------------------------	*
 *			FIELD DEFINITIONS						*
 *	----------------------------------------------	*/
	
$zz
	$zz['table']				name of main table										$maintable
	$zz['fields']				all fields of a table									$query
	
	//--> for all fields, n might get a maximum value of 899 if you use automatic translations

		$zz['fields'][n]['field_name']			field name from database, required if field shall be included

		$zz['fields'][n]['type']				type of field, default if not set is "text"
			possible values:
			
			id				ID of record, must be first field in list zz['fields'][1]
				-> show_id
			hidden			hidden field
				-> value		value for hidden field
				-> function		
			identifier		textual identifier for a record, unique, will be put together from 'fields'
				-> fields		if one of the fields-values is field_name of current field, identifier will be written only once and not change thereafter
				-> conf_identifier
			timestamp		timestamp
				-> value		value for timestamp
			unix_timestamp	unix-timestamp, will be converted to readable date and back
				-> value
			foreign			... (not in use currently)
				-> add_foreign	??
			password		password input, will be md5 encoded
			password_change	change a password (enter old, enter new twice, md5 encoded)
				-> sql_password_check
			text			standard one line text field, maxlength from VARCHAR-value if applicable, size=32
				-> format
				-> list_format
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
				-> cols
				-> rows
				-> format
				-> list_format
			select
				-> enum			array for enum, default value with 'default' should be set as well
					-> 'show_values_as_list'	puts all values in a radio button list, not in select/option-elements
				-> set			array for set
				-> sql			SQL-Query for select, first field is key field which will not be displayed but entered into database field
				-> sql_where	adds where to sql-string, rather complicated ... :-)
				-> sql_without_id	where['id']-value will be appended automatically
				-> sql_ignore	doesn't display fields of sql query in form view. this is useful if you need the fields for an identifier field.
				-> key_field_name	if where is used and where-key-name is different from key name in sql-query
				-> show_hierarchy	shows hierarchy in selects, value must be set to corresponding SQL field name
				-> show_hierarchy_subtree	ID of top hierarchy value, if not set NULL will be used
				-> add_details
				-> path_sql		only if this sql query is needed for constructing the extension of a file path
				-> display_field	field to be displayed instead of ID
				-> hide_novalue = false: as a default, the choice of no value for a radio button set will be hidden. By setting this value to false, it will be shown.
			image
				-> path			syntax = see below
				-> default_image	in case, path leads to no existing image, use this as a default_image (full path only)
			upload_image
				each image that shall be uploaded has to get a distinctive field_name in the 'image'-section!
				-> path
				-> image
				-> max_width
				-> min_width
				-> max_height
				-> min_height
				-> dont_show_image
			display				column for display only
				-> display_field
			option				column to choose an option, won't be saved to database
				-> type_detail	
				-> options			
			write_once
				-> type_detail
			calculated
				-> calculation = hours | sum (only supported modes)
				-> calculation_fields	array of fields which shall be used for calculation
			subtable		subrecord with foreign key which can be edited together with main record
				-> table_name
				-> form_display
			foreign_key		field is foreign_key (only possible for subtables)
			detail_value	copies value from other field

		internal types
			predefined	... (only for internal use)
				-> type_detail

		internal values
			f_field_name
			
		$zz['fields'][n]['title']				title of field, will be shown in form and table [optional, value will be generated from field_name if not set]
		$zz['fields'][n]['title_desc']			description, will always be shown below title in form, values in format will be added automatically (cf. explanation)
		$zz['fields'][n]['title_tab']			title in table display (optional, default = 'title'), e. g. for abbreviations to save place
		$zz['fields'][n]['hide_in_list']		field will not be shown in table view
		$zz['fields'][n]['display_field']		field (from sql query) which will be shown in table (e. g. as replacement for id values)
		$zz['fields'][n]['display_value']		static value which will be shown in field of type display
		$zz['fields'][n]['default']				default value for field
		$zz['fields'][n]['value']				value for field, cannot be changed, overwrites record
		$zz['fields'][n]['append_next']			false | true; appends next record in form view in the same line
		$zz['fields'][n]['list_append_next']	false | true; appends next record in list/tab view in the same line
			- list_prefix
			- list_suffix
		$zz['fields'][n]['title_append']		title for several records which will be in one line
		$zz['fields'][n]['add_details']			add detail records in different table, attention: current input will not be saved. Field gets ID #zz_add_details_x_y_z where x is table no [0...n], y is for subtable no [0 if main table, else 0...n] z is field number in zz-array
		$zz['fields'][n]['add_details_target']	target window for add_details
		$zz['fields'][n]['explanation']			explanation how to fill in values in this field, will only be shown in edit or insert mode
		$zz['fields'][n]['explanation_top']		same as explanation, this will show up above the form element, not below.
		$zz['fields'][n]['link']				link in list to record
												may be array, then it works with field, mode and string (field1, field2, mode1, ...) see also: path
												if root is set, link will be looked for in filesystem. if not existent, returns false (no link)
		$zz['fields'][n]['link_title']			title for link in list to record
												may be array, then it works with field and string (field1, field2, string1, ...) see also: path
		$zz['fields'][n]['link_no_append']		don't append record id to link
		$zz['fields'][n]['link_target']			target="$value" for link
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
		$zz['fields'][n]['separator']			true: will put a separation between fields, to improve form layout.
												column_begin, column, column_end: put form in two columns
		$zz['fields'][n]['show_id']				normally, id fields get class record_id {display: none;}, show_id stops zzform from doing that
		$zz['fields'][n]['assoc_files']			associated files to field, names will be changed as field value changes
		$zz['fields'][n]['write_once']			value might only be written once, then it's a read only value.

	//--> depending on type of field, see -> above

		$zz['fields'][n]['path_sql']			sql-query which will be used if this field's display_value is set as fieldxx in path to get a correct extension for file conversions
		$zz['fields'][n]['def_val_ignore']		this is for subtables only. For 'value', 'auto_value' and 'default'-fields, this setting specifies whether value will be accepted without any other input by the user in this sub-record or not. true: input will be ignored, false (standard): input will not be ignored. important for deciding whether to add or delete a sub-record.
		$zz['fields'][n]['type_detail']			type of field, used for option and predefined to set real type of field but still remain special functionality
		$zz['fields'][n]['format']				function which will be used to format text for display, e. g. markdown | textile, only in form part
		$zz['fields'][n]['list_format']			function which will be used to format text for display, e. g. markdown | textile, in list part
		$zz['fields'][n]['inherit_format']		translation tables: inherit format assignments from field that is to be translated
		$zz['fields'][n]['enum']				
		$zz['fields'][n]['enum_title']			optional. in case you don't like your enum values.	
		$zz['fields'][n]['set']
		$zz['fields'][n]['sql']
		$zz['fields'][n]['rows']				number of rows in textarea
		$zz['fields'][n]['cols']				number of cols in textarea
		$zz['fields'][n]['sql_without_id']		appends current id_value to sql-query
		$zz['fields'][n]['sql_where']
		$zz['fields'][n]['sql_ignore']			array, fields won't display in form view.
		$zz['fields'][n]['key_field_name']		field name differs from foreign_key, e. g. mother_org_id instead of org_id
		$zz['fields'][n]['sql_index_only']		only show index field (e. g. for SHOW COLUMNS ... queries)
		$zz['fields'][n]['number_type']			latitude | longitude, for entering geo information
		$zz['fields'][n]['factor']				for doubles etc. factor will be multiplied with value
		$zz['fields'][n]['function']			function which will be called to change input value
		$zz['fields'][n]['fields']				vars which will be passed to function or identifier, might be in form like "select_id[field_name_from_select]" as well, this refers to a field "select_id" with an associated sql-query and returns the value of the "field_name_from_select" of the query instead. Values from subtables will have a table_name. or table. prefix. first value is chosen if more than one (more than one record not recommended!). {0,4} or {4} ... will call a substr()-function to return just a part of the field value
												if identifier must not be changed after set, include field_name of identifier in list
		$zz['fields'][n]['auto_value']			increment | ... // 1 will be added and inserted in 'default'
		$zz['fields'][n]['conf_identifier']		array, affects standard values for generating identifier: array('forceFilename' => '-', 'concat' => '.' (or array in order of use, if array shorter than fields-array, last value will be repeated), 'exists' => '.', lowercase => true, 'start' => 2, 'start_always' => false); - attention: values longer than 1 will be cut off! (start is start value if record already exists, start_always says it has always to add exists and start)
												additional values: 'prefix' for a prefix;
		$zz['fields'][n]['path']				array, values: 
			root DOCUMENT_ROOT or path to directory, will be used as a prefix to check whether file_exists or not
			webroot alternative for DOCUMENT_ROOT on URL basis of the webserver
			fieldXX will add corresponding field value, 
			stringXX will add string value, (string1 will be weblink to file, parallel to root)
			modeXX: functions that will be applied to all field_values
			last stringXX oder fieldXX: this must consist of the fileextension only (with or without .)
			e. g. array('field1' => 'fieldname_bla', 'string1' => '/', 'field2' => 'fieldname_blubb') etc.
		$zz['fields'][n]['sql_password_check']	query to check existing password
		$zz['fields'][n]['hide_in_form']		hides field in form, but not in list. 

		$zz['fields'][n]['dont_show_image']		doesn't show image in form view (e. g. good for file upload)
		$zz['fields'][n]['dont_show_file_link']	doesn't show link below upload field which otherwise will be shown if a file already exists
		$zz['fields'][n]['options']				together with enum-array; values from enum-array will have to be set as pairs
												e. g. enum-value proportional will be an array in options, first is key which will be added to image, second is corresponding value
												['options'] = array('proportional' => array('key' => 'value')); existing keys will be overwritten
		$zz['fields'][n]['image'][n2]			array, what has to be done with uploaded image. keys:
			title
			width
			height
			field_name
			path (last field/string part must be file extension only! three letters with dot in case of string, field without dot!)
			required (true | false)
			source (if no extra upload field shall be shown)
			options (key value, reads options from this field, overwrite existing keys, options will only be read once)
			auto (function to change values of some of these fields before action will take place)
			auto_values (values used by auto-function)
			auto_size_tolerance (default 15, value in px for auto_size function)
			action (what to do with image, functions in image-[library].inc.php)
			ignore (true | false, true = field will be ignored)
			max_width			max width in px of uploaded image (only applicable for n2-Fields without 'source')
			min_width			min width in px of uploaded image (only applicable for n2-Fields without 'source')
			max_height			max height in px of uploaded image (only applicable for n2-Fields without 'source')
			min_height			min height in px of uploaded image (only applicable for n2-Fields without 'source')
			show_link			shows for files without thumbnails link to file (form: long with filename, list: short with title as linktext)
			input_filetypes		allowed input_filetypes, e. g. pdf, jpeg, doc, ...
			optional_image		image is optional, i. e. error notices when deleting a record and there are no accompanying files won't be shown.
			
		$zz['fields'][n]['subselect']['sql']	For subtables. SQL-Select query, foreign_key must be included, shows detail records in list view as well.
		$zz['fields'][n]['subselect']['prefix'] = '<p>'
		$zz['fields'][n]['subselect']['suffix'] = '</p>'
		$zz['fields'][n]['subselect']['concat_rows'] = "</p>\n<p>"
		$zz['fields'][n]['subselect']['concat_fields'] = ' ' 

	$zz_tab[1]['table']
	$zz_tab[1]['no']			= n in $zz['fields'][n]
	$zz_tab[1]['records']		number of subrecords
	$zz_tab[1]['max_records']	max. subrecords
	$zz_tab[1]['min_records']	min. subrecords
	$zz_tab[1]['dont_delete_records']	no [-] field, one may not delete a subrecord


		$zz['fields'][n]['order']				set order, e. g. for mixed alpha-numerical strings without preceding zeros

		$zz['fields'][n]['table_name']			Alias if more subtables are included (used for search only, AFAIK)
		$zz['fields'][n]['form_display']		default: vertical; horizontal shows detail records in list view
		$zz['fields'][n]['access']				values: show; makes a subrecord not editable, just viewable

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
			type_ext	mimetype + extension, e. g. application/octet-stream/dwg
			exif[FileName]
			exif[FileSize] ...
		$zz['fields'][n]['upload_sql'] = 'SELECT ... WHERE ... = '; // uses upload_value to get value for this field
		
	$zz['sql']					SQL Query without ORDER-part
	$zz['sqlorder']				ORDER part of SQL Query
			
	$zz['output']				HTML output												$output
	$zz['action']				what to do (POST): insert | update | delete | review	$action
	$zz['mode']					what to prepare (GET): add | edit | delete | show		$mode
	$zz['POST']					POST values												$myPOST
	$zz['record']				current record with all fields							$record
	$zz['extraGET']				extra GET values										$add_extras
	$zz['result']				gives result of operation if at all: successful_insert, successful_update, successful_delete

/*	----------------------------------------------	*
 *			INTERNAL zzform VARIABLES				*
 *	----------------------------------------------	*/
can be used inside zzform, e. g. in 'action'-scripts

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

// 	upload, rename folder

	$zz_tab[0]['upload_fields'][0]['i']
	$zz_tab[0][0]['old_record']		update, delete together with file upload or renaming a folder:
									save old record before update or delete in this array
	$zz_tab[0][0]['images']			uploaded documents go here

$zz_error
	$zz_error[]['msg']
	$zz_error[]['query']
	$zz_error[]['level']		= crucial | warning
	$zz_error[]['type']			= mysql | config
	$zz_error[]['mysql']		mysql error message
	$zz_error[]['mysql_errno']

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
	
undocumented features ;-), may change sooner or later

- currently none ...

/*	----------------------------------------------	*
 *			FUNCTIONS (NAMESPACE)					*
 *	----------------------------------------------	*/

zz_* 				- all zzform functions

zz_upload_*			- Upload module
zz_image_*			- Upload module: Image manipulation via function
zz_imagick_*		- Upload module: ImageMagick functions
zz_gd_*				- Upload module: GD functions
