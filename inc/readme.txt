zzform
readme

(c) 2006 Gustaf Mossakowski, gustaf@koenige.org

required: at least PHP 4.1.2 
lower PHP versions have not been tested
- mysql_real_escape_string: above 4.3 (addslashes will be used in PHP versions prior)
- exif_read_info: above 4.2 (no exif data will be read in PHP versions prior)

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
		$zz_conf['debug']			debugging mode, shows several debugging outputs
		$zz_conf['upload_MAX_FILE_SIZE'] in bytes, default is value from php.ini
		
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
					-> value
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
					-> image
				display				column for display only
					-> display_field
				option				column to choose an option, won't be saved to database
					-> type_detail	
					-> options			
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
			$zz['fields'][n]['value']				value for field, cannot be changed, overwrites record
			$zz['fields'][n]['append_next']			false | true; appends next record in form view in the same line
			$zz['fields'][n]['title_append']		title for several records which will be in one line
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

			$zz['fields'][n]['type_detail']			type of field, used for option and predefined to set real type of field but still remain special functionality
			$zz['fields'][n]['format']				function which will be used to format text for display, e. g. markdown | textile
			$zz['fields'][n]['enum']				
			$zz['fields'][n]['enum_title']			optional. in case you don't like your enum values.	
			$zz['fields'][n]['set']
			$zz['fields'][n]['sql']
			$zz['fields'][n]['rows']				number of rows in textarea
			$zz['fields'][n]['sql_without_id']		appends current id_value to sql-query
			$zz['fields'][n]['sql_where']
			$zz['fields'][n]['key_field_name']		field name differs from foreign_key, e. g. mother_org_id instead of org_id
			$zz['fields'][n]['sql_index_only']		only show index field (e. g. for SHOW COLUMNS ... queries)
			$zz['fields'][n]['number_type']			latitude | longitude, for entering geo information
			$zz['fields'][n]['factor']				for doubles etc. factor will be multiplied with value
			$zz['fields'][n]['function']			function which will be called to change input value
			$zz['fields'][n]['fields']				vars which will be passed to function or identifier
			$zz['fields'][n]['auto_value']			increment | ... // 1 will be added and inserted in 'default'
			$zz['fields'][n]['conf_identifier']		array, affects standard values for generating identifier: array('forceFilename' => '-', 'concat' => '.', 'exists' => '.'); - attention: values longer than 1 will be cut off!
			$zz['fields'][n]['path']				array, values: 
				root DOCUMENT_ROOT or path to directory, will be used as a prefix to check whether file_exists or not
				fieldXX will add corresponding field value, 
				stringXX will add string value, (string1 will be weblink to file, parallel to root)
				modeXX: functions that will be applied to all field_values
				e. g. array('field1' => 'fieldname_bla', 'string1' => '/', 'field2' => 'fieldname_blubb') etc.
			$zz['fields'][n]['sql_password_check']	query to check existing password
			$zz['fields'][n]['options']				together with enum-array; values from enum-array will have to be set as pairs
													e. g. enum-value proportional will be an array in options, first is key which will be added to image, second is corresponding value
													['options'] = array('proportional' => array('key' => 'value')); existing keys will be overwritten
			$zz['fields'][n]['image'][n2]			array, what has to be done with uploaded image. keys:
				title
				width
				height
				field_name
				path
				required (true | false)
				source (if no extra upload field shall be shown)
				options (key value, reads options from this field, overwrite existing keys, options will only be read once)
				auto (function to change values of some of these fields before action will take place)
				auto_values (values used by auto-function)
				auto_size_tolerance (default 15, value in px for auto_size function)
				action (what to do with image, functions in image-[library].inc.php)
				ignore (true | false, true = field will be ignored)

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
			$zz['fields'][n]['upload_sql'] = 'SELECT ... WHERE ... = '; // uses upload_value to get value for this field
				
			
		$zz['sql']					SQL Query without ORDER-part
		$zz['sqlorder']				ORDER part of SQL Query
				
		$zz['output']				HTML output												$output
		$zz['action']				what to do (POST): insert | update | delete | review	$action
		$zz['mode']					what to prepare (GET): add | edit | delete | show		$mode
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
		
undocumented features ;-)

	$_GET['search'] gt, lt greater than, lesser than, default is like


