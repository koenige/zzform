<?php 


// README

/*

	This script (c) Copyright 2004/2005 Gustaf Mossakowski, gustaf@koenige.org
	No use without permission. All rights reserved.


	$query[4]['sql_where'][1] = array(
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

/* MySQL Datenbankverbindung
	mit den Methoden:
	set_doerror($boolvalue)) -- TRUE / FALSE
	int connect()
	int query($sql)
	echoerror()
	array data()
	echoquery($sql)
*/


$editinc = true;

class mysql_db {
	var $link = false;
	var $resid = false;
    var $host = DB_HOST;
    var $user = DB_USER;
    var $passwd = DB_PWD;
    var $tables = DB_NAME;
   
//	errors will be shown normally
//	doerror might be changed with this function
	var $doerror = true;
	function set_doerror($boolvalue) {
		$this->doerror = $boolvalue;
	}

//	establishes connection to database
	function connect() {
		$temp = @mysql_connect
		($this->host, $this->user, $this->passwd);
		if (!$temp) {
			$this->echoerror();
			return false;
		}
		$this->link = $temp;
		$temp = @mysql_select_db($this->tables, $temp);
		if (!$temp) {
			$this->echoerror();
			return false;
		}
		return $this->link;
	}
	
	function query($sql) {
	// Sendet eine Anfrage an die Datenbank
		if (!$this->link) {
			if ($this->doerror) {
				echo ('<p class="error">Datenbank nicht verbunden</p>');
				return false;
			}
		}
		if ($this->resid) @mysql_free_result($this->resid);
		$result = mysql_query($sql, $this->link);
		if (!$result)  $this->echoerror();
		$this->resid = $result;
		return $result;
	}

//	if doerror = true shows error message
	function echoerror() {
		if (!$this->doerror) return;
		if (!mysql_errno()) return;
		echo ("<font color=\"red\"><b>" . mysql_errno());
		echo (": ". mysql_error() ." </b></font><br>");
	}

	function data() {
	// liefert einen Datensatz
		if (!$this->link) {
			if ($this->doerror)
				echo ("<b>Nicht verbunden!</b><br>");
			return false;
		}
		if (!$this->resid) {
    		if ($this->doerror)
				echo ("<b>Keine Abfrage!</b><br>");
			return false;
		}
		$result = mysql_fetch_array($this->resid, MYSQL_BOTH);
		$this->echoerror();
		return $result;
	}

	function echoquery($sql) {
		//Fragt die Datenbank ab und stellt die Abfrage dar
		$this->query($sql);
		echo("<table border cellpadding=\"3\"><tr>");
		$index = 0;
		echo("<th>record</th>");
		while ($field = mysql_fetch_field($this->resid))
			echo("<th>$field->name</th>");
		echo ("</tr>\n");
		$rec=0;
		while ($row = $this->data()) {
			$rec++;
			echo("<tr><td>$rec</td>");
			for ($i=0; $i<mysql_num_fields($this->resid); $i++)
				echo("<td>".htmlentities($row[$i])."&nbsp;</td>");
			echo("</tr>\n");
		}
		echo ("</table>");
	}

	function mysql_db() {
		$this->connect();
	}
}


$output = '<div id="editinc">';

// en lang variables

$text['add_new_record'] = 'Add New Record';
$text['all_records'] = 'All Records';
$text['prev_20_records'] = 'Previous 20 Records';
$text['next_20_records'] = 'Next 20 Records';
$text['record_was_inserted'] = 'Record was inserted';
$text['warning'] = 'Warning';
$text['action'] = 'Action';
$text['none_selected'] = 'None selected';
$text['edit'] = 'Edit';
$text['delete'] = 'Delete';
$text['back-to-overview'] = 'Back to overview';
$text['no_selection_possible'] = 'No selection possible.';
$text['calculated_field'] = 'calculated field';
$text['will_be_added_automatically'] = 'will be added automatically';
$text['no_selection'] = 'No selection';
$text['record_was_updated'] = 'Record was updated';
$text['record_was_deleted'] = 'Record was deleted';
$text['reason'] = 'Reason';
$text['following_values_checked_again'] = 'The following values have to be checked again';
$text['database'] = 'Database';
$text['no_image'] = 'No Image';
$text['sql-query'] = 'SQL-Query';
$text['database-error'] = 'Database error';
$text['decimal'] = 'decimal';
$text['no_source_defined'] = 'No source defined';
$text['image_not_display'] = 'Image cannot yet be displayed';
$text['update'] = 'Update';
$text['delete_from'] = 'Delete from';
$text['add_to'] = 'Add to';
$text['error-sql-incorrect'] = 'An Error occured. This SQL Statement seems to be incorrect';
$text['add'] = 'Add';
$text['a_record'] = 'a Record';
$text['failed'] = 'failed';
$text['insert'] = 'Insert';
$text['order by'] = 'Order by';
$text['asc'] = 'ascending';
$text['desc'] = 'descending';
$text['new'] = 'New';
$text['no-data-available'] = 'No data available.';
$text['edit-after-save'] = 'No entry possible. First save this record.';
$text['table-empty'] = 'No entries available';
// if $query has no title get it out of field_name
$text['detail'] = 'Details';
$text['Value_incorrect_in_field'] = 'Value missing or incorrect in field';
$text['Following_errors_occured'] = 'The following errors occured';
$text['This record could not be deleted because there are details about this record in other records.'] = 'This record could not be deleted because there are details about this record in other records.';
$text['Detail records exist in the following tables:'] = 'Detail records exist in the following tables:';
$text['No relation table'] = 'No _relation table. Program will not work without it.';
$text['No records in relation table'] = 'No records in relation table. Program will not work without records.';
$text['show_record'] = 'Show record';
$text['Database error. This database has ambiguous values in ID field.'] ='Database error. This table has ambiguous values in ID field.';
$text['search'] = 'search';
$text['in'] = 'in';
$text['all fields'] = 'all fields';

// Variables

$error = false;

$i = 1;
foreach ($query as $line) {
	while (!isset($query[$i])) $i++;
	if (!isset($query[$i]['title'])) {
		$query[$i]['title'] = ucfirst($query[$i]['field_name']);
		$query[$i]['title'] = str_replace('_id', ' ', $query[$i]['title']);
		$query[$i]['title'] = str_replace('_', ' ', $query[$i]['title']);
	}
	$i++;
}

// standard

if (!isset($inc)) $inc = 'inc'; // Standard-Include-Verzeichnis

if (isset($language) && $language != 'en')
	include_once($level.'/'.$inc.'/edit-'.$language.'.inc.php');
if (!isset($show_output)) $show_output = true; // standardmaessig wird output angezeigt

if (!isset($verbindung)) include_once ($level.'/'.$inc.'/db.inc.php');
if (!function_exists('datum_de')) include ($level.'/'.$inc.'/numbers.inc.php');
if (file_exists($level.'/'.$inc.'/dec2dms.inc.php')) include_once($level.'/'.$inc.'/dec2dms.inc.php');
if (file_exists($level.'/'.$inc.'/coords.inc.php')) include_once($level.'/'.$inc.'/coords.inc.php');
if (file_exists($level.'/'.$inc.'/coords-edit.inc.php')) include_once($level.'/'.$inc.'/coords-edit.inc.php');
if (file_exists($level.'/'.$inc.'/validate.inc.php')) include_once($level.'/'.$inc.'/validate.inc.php');
if (file_exists($level.'/'.$inc.'/markdown.php')) include_once($level.'/'.$inc.'/markdown.php');
if (file_exists($level.'/'.$inc.'/func/markdown.php')) include_once($level.'/'.$inc.'/func/markdown.php');
if (file_exists($level.'/'.$inc.'/textile.php')) include_once($level.'/'.$inc.'/textile.php');
if (file_exists($level.'/'.$inc.'/func/textile.php')) include_once($level.'/'.$inc.'/func/textile.php');
/*
if (!function_exists('waehrung')) {
	if (file_exists ($level.'/'.$inc.'/waehrung.inc.php'))
		include ($level.'/'.$inc.'/waehrung.inc.php');
}*/
if (!isset($delete)) $delete = false;
if (!isset($do_validation)) $do_validation = false;
if (!isset($multilang_fieldnames)) $multilang_fieldnames = false;	// translate fieldnames via $text[$fieldname]
if (!isset($backlink)) $backlink = true;	// show back-to-overview link
if (!isset($add)) $add = true;			// Add New Record wird angeboten
if (!isset($list)) $list = true;		// nur hinzufügen möglich, nicht bearbeiten, keine Tabelle
if (!isset($tabelle)) $tabelle = true;  // nur bearbeiten möglich, keine Tabelle
if (!isset($editvar['search'])) $editvar['search'] = false;	// Suchfunktion am Ende des Formulars ja/nein
if (!isset($tfoot)) $tfoot = false;  // Tabellenfuss
if (isset($_GET['tabelle'])) $tabelle = $_GET['tabelle'];
if (isset($_GET['limit'])) $limit = $_GET['limit'];
if (!isset($limit)) $limit = false;		// nur 20 Datensaetze auf einmal angezeigt
if (isset($_GET['file'])) $file = $_GET['file'];
else $file = false;
if (!isset($referer)) {
	$referer = false;
	if (isset($_GET['referer'])) $referer = $_GET['referer'];
	if (isset($_POST['referer'])) $referer = $_POST['referer'];
} elseif (isset($_POST['referer'])) {
	$referer = $_POST['referer'];
} elseif (isset($_SERVER['HTTP_REFERER'])) {
	$referer = $_SERVER['HTTP_REFERER'];
}

if (!isset($self)) {
	$self = parse_url($_SERVER['REQUEST_URI']);
	$self = $self['path'];
}
if (isset($_GET['test'])) {
	foreach (array_keys($_GET['test']) as $test) {
		$output.= $test.': '.$_GET['test'][$test];
	}
}

// Add with suggested values
$values = false;
$where_values = false;
if (isset($_GET['value'])) $values = read_fields($_GET['value'], 'replace', $values);
if (isset($_GET['where'])) $where_values = read_fields($_GET['where'], 'replace', $where_values);
$sql_where = false;
if (isset($_GET['where'])) $sql_where = read_fields($_GET['where'], false, false);

$action = false;
$record_id = false;
if (isset($_GET['mode'])) {
	$mode = $_GET['mode'];
	if ($mode == 'edit' OR $mode == 'delete') {
		$record_id = $_GET['id'];
	}
} else {
	$mode = false;
	if (isset($_POST['action']))
		$action = $_POST['action'];
}

// standard

$output.= '<h2>';
$output.= (!isset($heading)) ? ueberschrift($maintable) : $heading;
$output.= '</h2>'."\n";

// Extra GET Parameter

$extras = false;
$add_extras = false;
if (isset($_GET['where'])) $extras .= get_to_array($_GET['where']);
if (isset($_GET['order'])) $extras .= '&order='.$_GET['order'];
if (isset($_GET['q']) && $_GET['q']) $extras .= '&q='.$_GET['q'];
if (isset($_GET['scope']) && $_GET['scope']) $extras .= '&scope='.$_GET['scope'];
if (isset($_GET['dir'])) $extras .= '&dir='.$_GET['dir'];
if (isset($_GET['var'])) $extras .= get_to_array($_GET['var']);
// elseif ($values) $extras .= '&where='.$_GET['values'];
if ($limit) $extras.= '&limit='.$limit;
if ($referer) $extras.= '&referer='.urlencode($referer);
if ($extras) $extras = substr($extras, 1, strlen($extras) -1 ); // first ? or & to be added as needed!
if ($extras) $add_extras = '&'.$extras;

//
// Add, Update or Delete
//

$validation = true;
if ($action == 'insert' OR $action == 'update' OR $action == 'delete') {

//	### Check for validity, do some operations ###
	$myPOST = $_POST;
	if ($action == 'insert' OR $action == 'update') {
		foreach (array_keys($query) as $qf) {
		//	remove entries which are for display only
			if ($query[$qf]['type'] == 'calculated' OR $query[$qf]['type'] == 'image' 
				OR $query[$qf]['type'] == 'foreign')
				$query[$qf]['in_sql_query'] = false;
			elseif ($query[$qf]['type'] == 'id') {
				$query[$qf]['in_sql_query'] = true;
				if ($action == 'update') {
					$id_field = $query[$qf]['field_name'];
					$record_id = $myPOST[$id_field]; // for display of updated record
				} else
					$myPOST[$query[$qf]['field_name']] = "''";
			} else {
				$query[$qf]['in_sql_query'] = true;
			
			//	copy value if field detail_value isset
				if (isset($query[$qf]['detail_value']))
					$myPOST[$query[$qf]['field_name']] = $myPOST[$query[$qf]['detail_value']];

			//	calculation and choosing of right values in case of coordinates
				if ($query[$qf]['type'] == 'number' AND isset($query[$qf]['number_type']) 
					AND $query[$qf]['number_type'] == 'latitude' || $query[$qf]['number_type'] == 'longitude') {
					// geographical coordinates
					if ($myPOST[$query[$qf]['field_name']]['which'] == 'dec') 
						$myPOST[$query[$qf]['field_name']] = $myPOST[$query[$qf]['field_name']]['dec'];
					elseif ($myPOST[$query[$qf]['field_name']]['which'] == 'dms') {
						$degree = dms2db($myPOST[$query[$qf]['field_name']]); 
						$error .= $error_message;
						$myPOST[$query[$qf]['field_name']] = $degree[substr($query[$qf]['number_type'], 0, 3).'dec'];
					}
					if (strlen($myPOST[$query[$qf]['field_name']]) == 0) $myPOST[$query[$qf]['field_name']] = '';
				} 

			//	check if numbers are entered with . 			

			//	factor for avoiding doubles
				if (isset($query[$qf]['factor']) && $myPOST[$query[$qf]['field_name']]) 
					$myPOST[$query[$qf]['field_name']] =str_replace(',', '.', $myPOST[$query[$qf]['field_name']]) * $query[$qf]['factor'];
	
			//	validate time
				if ($query[$qf]['type'] == 'time')
					if ($myPOST[$query[$qf]['field_name']])
						if ($my_time = validate_time($myPOST[$query[$qf]['field_name']]))
							$myPOST[$query[$qf]['field_name']] = $my_time;
						else {
							//echo $myPOST[$query[$qf]['field_name']].'<br>';
							$query[$qf]['check_validation'] = false;
							$validation = false;
						}

			//	internationalize date!
				if ($query[$qf]['type'] == 'date')
					if ($myPOST[$query[$qf]['field_name']])
					// submit to datum_int only if there is a value, else return would be false and validation true!
						if ($my_date = datum_int($myPOST[$query[$qf]['field_name']]))
							$myPOST[$query[$qf]['field_name']] = $my_date;
						else {
							//echo $myPOST[$query[$qf]['field_name']].'<br>';
							$query[$qf]['check_validation'] = false;
							$validation = false;
						}

				if ($query[$qf]['type'] == 'hidden' && isset($query[$qf]['function'])) {
					foreach ($query[$qf]['fields'] as $var)
						$func_vars[$var] = $myPOST[$var];
					$myPOST[$query[$qf]['field_name']] = $query[$qf]['function']($func_vars);
				}
					

			//	validation
			//	first check for backwards compatibilty - old edit.inc does not include validation
				if ($do_validation) {

			//		check whether is false but most not be NULL
					if (!isset($myPOST[$query[$qf]['field_name']])) {
						// no set = must be error
						if (!isset($query[$qf]['set'])) {
							$validation = false;
						}
						elseif (!checkfornull($query[$qf]['field_name'])) {
							$validation = false;
							$query[$qf]['check_validation'] = false;
						}
					} elseif(!$myPOST[$query[$qf]['field_name']] AND !isset($query[$qf]['null']) || !$query[$qf]['null'])
						if (!checkfornull($query[$qf]['field_name'])) {
							$validation = false;
							$query[$qf]['check_validation'] = false;
						}

			//		check for correct enum values
					if (isset($query[$qf]['enum'])) {
						if ($myPOST[$query[$qf]['field_name']]) {
							if (!$tempvar = checkenum($myPOST[$query[$qf]['field_name']], $query[$qf]['field_name'])) {
								$validation = false;
								$query[$qf]['check_validation'] = false;
							} else $myPOST[$query[$qf]['field_name']] = $tempvar;
						}
					}
			//		check for correct url
					if ($query[$qf]['type'] == 'url') {
						if ($myPOST[$query[$qf]['field_name']]) {
							if (!$tempvar = check_url($myPOST[$query[$qf]['field_name']])) {
								$validation = false;
								$query[$qf]['check_validation'] = false;
							} else $myPOST[$query[$qf]['field_name']] = $tempvar;
						}
					}

			//		check for correct mailaddress
					if ($query[$qf]['type'] == 'mail') {
						if ($myPOST[$query[$qf]['field_name']]) {
							if (!$tempvar = checkmail($myPOST[$query[$qf]['field_name']])) {
								$validation = false;
								$query[$qf]['check_validation'] = false;
							} else $myPOST[$query[$qf]['field_name']] = $tempvar;
						}
					}
				}
			}
		// finished
		}
		if ($validation) {
			foreach (array_keys($query) as $qf) {
			//	set
				if ($query[$qf]['type'] == 'select' && isset($query[$qf]['set'])) {
					$value = '';
					if (isset($myPOST[$query[$qf]['field_name']]) && $myPOST[$query[$qf]['field_name']]) {
						foreach ($myPOST[$query[$qf]['field_name']] as $this_value) {
							if ($value) $value .= ',';
							$value .= $this_value;
						}
						$myPOST[$query[$qf]['field_name']] = $value;
					} else {
						$myPOST[$query[$qf]['field_name']] = '';
					}
				}
			//	slashes, 0 and NULL
				if ($query[$qf]['type'] != 'calculated' AND $query[$qf]['type'] != 'image' 
						AND $query[$qf]['type'] != 'id' AND $query[$qf]['type'] != 'foreign') {
					if ($myPOST[$query[$qf]['field_name']])
						if (!get_magic_quotes_gpc()) // sometimes unwanted standard config
							$myPOST[$query[$qf]['field_name']] = '"'.addslashes($myPOST[$query[$qf]['field_name']]).'"';
						else
							$myPOST[$query[$qf]['field_name']] = '"'.$myPOST[$query[$qf]['field_name']].'"';
					else {
						if (isset($query[$qf]['number_type']) AND !is_null($myPOST[$query[$qf]['field_name']]) 
							AND $query[$qf]['number_type'] == 'latitude' || $query[$qf]['number_type'] == 'longitude')
							$myPOST[$query[$qf]['field_name']] = '0';
						elseif (isset($query[$qf]['null']) AND $query[$qf]['null']) 
							$myPOST[$query[$qf]['field_name']] = '0';
						else 
							$myPOST[$query[$qf]['field_name']] = 'NULL';
					}
				}
			}
		}
	} else {
//	Check referential integrity
		if (file_exists($level.'/'.$inc.'/integrity.inc.php')) {
			include_once($level.'/'.$inc.'/integrity.inc.php');
		//test
			foreach (array_keys($query) as $qf)
				if ($query[$qf]['type'] == 'id') {
					$record_idfield = $query[$qf]['field_name'];
				}
			if (!$no_delete_reason = check_integrity(DB_NAME, $maintable, $record_idfield, $_POST[$record_idfield])) $validation = true;
			else $validation = false;
		}
	}
	
//	### Check whether more tables are involved ###

	$queries[0] = $query;
	$tablenames[0] = $maintable;
	if (isset($detail_table)) {
		$i = 1;
		foreach (array_keys($detail_table) as $tablekey) {
			$tablenames[$i] = $detail_table[$tablekey];
			foreach ($detail_fields[$i] as $no) {
				$queries[$i][$no] = $query[$no];
				unset($queries[0][$no]);
			}
			if (isset($detail_key[$i])) {
				$this_key['field_name'] = $detail_key[$i];
				$this_key['in_sql_query'] = true;
				$this_key['type'] = 'number';
				$queries[$i][] = $this_key;
			}
			$i++;
		}
	}
	if (isset($query_action['before_'.$action]))
		include ($level.'/'.$inc.'/'.$query_action['before_'.$action].'.inc.php'); 
			// if any other action before insertion/update/delete is required

	if ($validation) {
		$sql_edit = '';
		foreach (array_keys($queries) as $me) {
	
		//	### Insert a record ###
		
			if ($action == 'insert') {
				$field_values = '';
				$field_list = '';
				foreach ($queries[$me] as $field)
					if ($field['in_sql_query']) {
						if ($field_list) $field_list .= ', ';
						$field_list .= $field['field_name'];
						if ($field_values && $field['type']) $field_values.= ', ';
						if ($me == 0 OR $field['field_name'] != $detail_key[$me])
							$field_values .= $myPOST[$field['field_name']];
					}
				$me_sql = ' INSERT INTO '.$tablenames[$me];
				$me_sql .= ' ('.$field_list.') VALUES ('.$field_values;
				
		// ### Update a record ###
	
			} elseif ($action == 'update') {
				$update_values = '';
				foreach ($queries[$me] as $field)
					if ($field['type'] != 'id' && $field['in_sql_query']) {
						if ($update_values) $update_values.= ', ';
						$update_values.= $field['field_name'].' = '.$myPOST[$field['field_name']];
					}
				$me_sql = ' UPDATE '.$tablenames[$me];
				$me_sql.= ' SET '.$update_values.' WHERE '.$id_field.' = "'.$record_id.'"';
			
		// ### Delete a record ###
	
			} elseif ($action == 'delete') {
				$me_sql= ' DELETE FROM '.$tablenames[$me];
				$me_sql.= ' WHERE '.$queries[0][1]['field_name']." = '".$myPOST[$queries[0][1]['field_name']]."'";
				$me_sql.= ' LIMIT 1';
			}
			if (!$sql_edit) {
				$sql_edit = $me_sql;
				if ($action == 'insert') $sql_edit .=')';
			} else $detail_sql_edit[] = $me_sql;
			
		}
		// ### Do mysql-query and additional actions ###
		
		//echo $sql_edit;
		
		$result = mysql_query($sql_edit);
		if ($result) {
			if ($action == 'insert') $success = $text['record_was_inserted'];
			elseif ($action == 'update') $success = $text['record_was_updated'];
			elseif ($action == 'delete') $success = $text['record_was_deleted'];
			if ($action == 'insert') $record_id = mysql_insert_id(); // for requery
			if (isset($detail_sql_edit))
				foreach ($detail_sql_edit as $detail_sql) {
					if ($action == 'insert') $detail_sql .= $record_id.');';
					$detail_result = mysql_query($detail_sql);
					if (!$detail_result) {
						$success = false;
						$my_error = mysql_error();
						$error_sql = $detail_sql;
					}
				}
			if (isset($query_action['after_'.$action])) 
				include ($level.'/'.$inc.'/'.$query_action['after_'.$action].'.inc.php'); 
				// if any other action after insertion/update/delete is required
		} else {
			// Output Error Message
			$success = false;
			if ($action == 'insert') $record_id = false; // for requery
			$my_error = mysql_error();
			$error_sql = $sql_edit;
		}
	}
}

//
// Query Updated, Added or Editable Record
//
$record = '';
if ($action != 'delete') {
	if ($validation) {
		if ($mode == 'edit' OR $mode == 'delete' OR $action) {
			// checks whether there is already a where-clause in the sql clause
			$where = ' WHERE ';
			if (strstr($sql, 'WHERE')) $where = ' AND ';
			$sql_edit = $sql.$where;
			if (isset($query[1]['ambiguous'])) $sql_edit .= $maintable.'.';
			$sql_edit .= $query[1]['field_name']." = '";
			$sql_edit .= $record_id."'";
			$result_edit = mysql_query($sql_edit);
			if ($result_edit) {
				if (mysql_num_rows($result_edit) == 1) {
					while ($line = mysql_fetch_array($result_edit)) {
						$record = $line;
					}
				} else {
				// $output.= 'Error in Database. Possibly the SQL statement is incorrect: '.$sql_edit;
				}
			} else $output.= show_error(mysql_error(), $sql_edit, false);
		}
	} else {
		$record = $myPOST;
		$success = 'Review record';
		if ($action == 'update') $mode = 'edit';
		elseif ($action == 'insert') $mode = 'add';
		$action = 'review';	// display form again
	//	print out all records which were wrong, set class to error
		$validate_errors = false;
		foreach (array_keys($query) as $qf) {
			if (isset($query[$qf]['check_validation'])) {
				if (!$query[$qf]['check_validation']) {
					if (isset($query[$qf]['class'])) $query[$qf]['class'].= ' error';
					else $query[$qf]['class'] = 'error';
					if (!$validate_errors) $validate_errors = '<p>'.$text['Following_errors_occured'].':</p><ul>';
					$validate_errors.= '<li>'.$text['Value_incorrect_in_field'].' <strong>'.$query[$qf]['title'].'</strong></li>';
				} else {
					echo $query[$qf]['check_validation'];
				}
			}
		}
		if ($validate_errors) $output.= $validate_errors.'</ul>';
	}
} else {
	if (!$validation) {
	//	check for referential integrity was not passed
		$success = 'Deletion not possible';
		//$no_delete_reason['text'] .= '<br>Record cannot be deleted, because there are detail records for this record in other tables.';
	}
}
$whereid = false;
if ($sql_where) {
	if (strstr($sql, ' WHERE ')) $sql_ext = ' ';
	else $sql_ext = false;
	foreach (array_keys($sql_where) as $field) {
		if (strstr($field, '.')) $myfield = substr($field, strrpos($field, '.')+1);
		else $myfield = $field;
		foreach ($query as $thisfield)
			if ($thisfield['type'] == 'id' AND $myfield == $thisfield['field_name']) 
				$whereid = $thisfield['field_name'];
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
	$sql.= $sql_ext;
}

// Search with q
if (isset($_GET['q']) && isset($_GET['scope']) && $_GET['scope']) {
	$scope = false;
	foreach ($query as $field) {
	// todo: check whether scope is in_array($searchfields)
		if ($field['type'] != 'image' && $field['type'] != 'calculated') {
			if (!isset($field['sql']) && $_GET['scope'] == $field['field_name'] OR $_GET['scope'] == $maintable.'.'.$field['field_name']) {
				$scope = $_GET['scope'];
				if (isset($field['display_field']) && $_GET['scope'] == $field['display_field']) $scope = $_GET['scope'];
				if (isset($field['search'])) $scope = $field['search'];
			}
		}
	}
	if (strstr($sql, 'WHERE')) $sql.= ' AND';
	else $sql.= ' WHERE';
	if ($scope)
		// search results
		$sql.= ' '.$scope.' LIKE "%'.$_GET['q'].'%"';
	else
		$sql.= ' NULL';
} elseif (isset($_GET['q'])) {
	$q_search = '';
	foreach ($query as $field) {
		if ($field['type'] != 'calculated' && $field['type'] != 'image') {
			if (!$q_search)
				if (strstr($sql, 'WHERE')) $q_search = ' AND (';
				else $q_search = ' WHERE (';
			else $q_search .= ' OR ';
			if (isset($field['search'])) $fieldname = $field['search'];
			elseif (isset($field['display_field'])) $fieldname = $field['display_field'];
			else $fieldname = $maintable.'.'.$field['field_name'];
			$q_search .= $fieldname.' LIKE "%'.$_GET['q'].'%"';
		}
	}
	$q_search.= ')';
	$sql.= $q_search;
}
$sql.= $sqlorder; // must be here because of where-clause

//
// Display Updated, Added or Editable Record
//

// Query for table below record and for value = increment
// moved to end

if ($mode == 'add') $submit = 'insert';
elseif ($mode == 'edit') $submit = 'update';
elseif ($mode == 'delete') $submit = 'delete';


if ($mode) {
	$output.= '<form action="'.$self;
	if ($extras) $output.= '?'.$extras;
	$output.= '" method="POST">';
}

if ($mode) {
	// $mode = add update delete = show form
	$display = 'form';
	$h3 = $text[$mode].' '.$text['a_record'];
	if ($mode == 'delete') $display = 'review';
} elseif ($action) {
	// $action = add update review: show form with new values
	if ($action == 'delete') $display = false;
	else $display = 'review';
	$h3 = $success;
	if (!$h3) {
		$h3 = $text[$action].' '.$text['failed'];
		$output.= '<div id="add">'."\n";
		$output.= '<h3>'.ucfirst($h3).'</h3>'."\n";
		$output.= show_error($my_error, $error_sql, false);
		$output.= '</div>'."\n";
		$display = false;
	}
	if ($error) {
		$output.= '<h3>'.$text['warning'].'!</h3>'."\n";
		$output.= '<p>'.$text['following_values_checked_again'].':'."\n";
		$output.= $error.'</p>';
	}
	if (isset($no_delete_reason) && $no_delete_reason) {
		$output.= '<h3>'.$text['warning'].'!</h3>'."\n";
		$output.= '<p>'.$text['This record could not be deleted because there are details about this record in other records.'];
		$output.= ' '.$text[$no_delete_reason['text']].'</p>'."\n";
		if (isset($no_delete_reason['fields'])) {
			$output.= '<ul>'."\n";
			foreach ($no_delete_reason['fields'] as $del_field) {
				$output.= '<li>'.$del_field.'</li>'."\n";
			}
			$output.= '</ul>'."\n";
		} 
	}
} elseif ($whereid) {
	$display = 'review';
	$h3 = $text['show_record'];
	$tabelle = false;		// don't show table
	$add = false;			// don't show add new record
	$result = mysql_query($sql);
	if ($result) 
		if (mysql_num_rows($result) == 1) {
			$record = mysql_fetch_array($result);
			$record_id = $record[$whereid];
		} else
			echo $text['Database error. This database has ambiguous values in ID field.'];
} else {
	$display = false;
}

if ($display) {	
	$output.= '<div id="add">'."\n";
	$output.= '<h3>'.ucfirst($h3).'</h3>'."\n";
	$output.= '<table class="record">';
	$append_next = false; 
	foreach ($query as $field) {
		if (($multilang_fieldnames)) $field['title'] = $text[$field['title']];
		if (!($field['type'] == 'id' AND !$list)) {
			if (!$append_next) {
				$output.= '<tr';
				if (isset($field['class'])) $output.= ' class="'.$field['class'].'"';
				$output.= '><th>';
				$output.= $field['title'];
				$output.= '</th> <td>';
			}
			if (isset($field['append_next']) && $field['append_next']) $append_next = true;
			else $append_next = false;
			if (!isset($field['maxlength'])) $field['maxlength'] = check_maxlength($field['field_name']);
			if (!isset($field['size']))
				if ($field['type'] == 'number') $field['size'] = 16;
 				else $field['size'] = 32;
			if ($field['type'] == 'time') $field['size'] = 8;
			if ($field['maxlength'] && $field['maxlength'] < $field['size']) $field['size'] = $field['maxlength'];
			if ($record && isset($field['factor']) && $record[$field['field_name']]) $record[$field['field_name']] /=$field['factor'];
			if (isset($field['auto_value'])) {
				if ($field['auto_value'] == 'increment') {
					/* added 2004-12-06
						maybe easier and faster without sql query - instead rely on table query
					*/
					$sql_max = $sql;
					if (strstr($sql_max, 'ORDER BY')) {
						preg_match('/(.*) ORDER BY.*/', $sql_max, $sql_result);
						$sql_max = $sql_result[1];
					}
					$sql_max .= ' ORDER BY '.$field['field_name'].' DESC';
					$myresult = mysql_query($sql_max);
					if ($myresult) {
						if (mysql_num_rows($myresult)) {
							$field['default'] = mysql_result($myresult, 0, $field['field_name']);
							$field['default']++;
						} else $field['default'] = 1;
					}
				}
			}
			if (isset($where_values[$field['field_name']])) {
				if ($field['type'] == 'select') $field['type_detail'] = 'select';
				else $field['type_detail'] = false;
				$field['type'] = 'predefined';
			} elseif (isset($values[$field['field_name']])) {
				$field['default'] = $values[$field['field_name']];
			}
			if (isset($field['default'])) {
				if (!$record) {
					$record[$field['field_name']] = $field['default'];
					$default_value = true; // must be unset later on because of this value
				}
			}
			if ($field['type'] == 'id') {
				if ($record_id) $output.= '<input type="hidden" value="'.$record_id.'" name="'.$field['field_name'].'">'.$record_id;
				else $output.= '('.$text['will_be_added_automatically'].')&nbsp;';
			} elseif ($field['type'] == 'hidden') {
				$output.= '<input type="hidden" value="';
				if (isset($field['value'])) $output.= $field['value'];
				elseif ($record) $output.= $record[$field['field_name']];
				$output.= '" name="'.$field['field_name'].'">';
				if ($record) {
					if (isset($field['timestamp']) && $field['timestamp'])
						$output.= timestamp2date($record[$field['field_name']]);
					elseif (isset($field['display_field'])) $output.= $record[$field['display_field']];
					else $output.= $record[$field['field_name']];
				} else {
					$output.= '('.$text['will_be_added_automatically'].')&nbsp;';
				}
			} elseif ($field['type'] == 'foreign') {
				$foreign_res = mysql_query($field['sql'].$record_id);
				//$output.= $field['sql'].$record_id;
				if ($foreign_res) {
					if (mysql_num_rows($foreign_res) > 0) {
						$my_output = false;
						while ($fline = mysql_fetch_array($foreign_res)) {
							if ($my_output) $output.= ', ';
							$my_output.= $fline[0]; // All Data in one Line! via SQL
						}
						if ($my_output) $output.= $my_output;
						else $output.= $text['no-data-available'];
					} else {
						$output.= $text['no-data-available'];
					}
				} 
				if (isset($field['add_foreign'])) {
					if ($record_id)
						$output.= ' <a href="'.$field['add_foreign'].$record_id.'&amp;referer='.urlencode($_SERVER['REQUEST_URI']).'">['.$text['edit'].' &hellip;]</a>';
					else
						$output.= $text['edit-after-save'];
				}
			} elseif ($field['type'] == 'predefined') {
				$output.= '<input type="hidden" name="'.$field['field_name'].'" value="'.$where_values[$field['field_name']].'">';
				if ($field['type_detail'] == 'select') {
					$my_fieldname = $field['field_name'];
					if (isset($field['key_field_name'])) $my_fieldname = $field['key_field_name'];
					if (isset($field['sql'])) {
						if (strstr($field['sql'], 'ORDER BY'))
							$mysql = str_replace('ORDER BY', (' WHERE '.$my_fieldname.' = '.$where_values[$field['field_name']].' ORDER BY'), $field['sql']);
						else
							$mysql = $field['sql'].' WHERE '.$my_fieldname.' = '.$where_values[$field['field_name']];
						$result_detail = mysql_query($mysql);
						if ($result_detail) {
							if (mysql_num_rows($result_detail) == 1) {
								$myline = mysql_fetch_assoc($result_detail);
								$my_i = 0;
								foreach ($myline as $myfield) {
									if ($my_i) {
										if ($my_i != 1) $output.= ' | ';
										$output.= $myfield;
									}
									$my_i++;
								}
								unset ($my_i);
							}
						} else $output.= show_error(mysql_error(), $mysql, false);
					} elseif (isset($field['enum'])) {
						$output.= $where_values[$field['field_name']];
					}
				} else {
					$output.= $where_values[$field['field_name']];
				}
			} elseif ($field['type'] == 'text' OR $field['type'] == 'url'
				OR $field['type'] == 'time'
				OR $field['type'] == 'enum' OR $field['type'] == 'mail'
				OR $field['type'] == 'datetime') {
				if ($display == 'form') {
					$output.= '<input type="text" name="'.$field['field_name'].'" size="'.$field['size'].'" ';
					if (isset($field['maxlength']) && $field['maxlength']) $output.= ' maxlength="'.$field['maxlength'].'" ';
					if (isset($field['required']) && $field['required']) $output.= ' class="required"';
				}
				if ($record) {
					if ($display == 'form') $output.= 'value="';
					if ($field['type'] == 'url' && $display != 'form') 
						$output.= '<a href="'.$record[$field['field_name']].'">';
					elseif ($field['type'] == 'mail' && $display != 'form') 
						$output.= '<a href="mailto:'.$record[$field['field_name']].'">';
					$output.= htmlchars($record[$field['field_name']]);
					if ($field['type'] == 'url' && $display != 'form') $output.= '</a>';
					if ($field['type'] == 'mail' && $display != 'form') $output.= '</a>';
					if ($display == 'form') $output.= '"';
				} elseif ($mode == 'add' AND $field['type'] == 'datetime') { 
					$output.= 'value="'.date('Y-m-d H:i:s', time()).'"';
				}
				if ($display == 'form') $output.= '>';
			} elseif ($field['type'] == 'number') {
				if (isset($field['number_type']) AND $field['number_type'] == 'latitude' || $field['number_type'] == 'longitude') {
					$var = false;
					if ($record) {
						if ($field['number_type'] == 'latitude') $var = dec2dms($record[$field['field_name']], '');
						elseif ($field['number_type'] == 'longitude') $var = dec2dms('', $record[$field['field_name']]);
					}
					if ($display == 'form') {
						$output.= '<span class="edit-coord-degree">'."&deg; ' ''".': <input type="radio" name="'.$field['field_name'].'[which]" value="dms" checked="checked"> ';
						$output.= print_editform($field['field_name'].'['.substr($field['number_type'],0,3), $var);
						$output.= ' || ';
					} elseif ($var) {
						$output.= $var[$field['number_type']];
						$output.= ' || ';
					} else {
						$output.= 'N/A';
					}
					if ($display == 'form') {
						$output.= $text['decimal'].': <input type="radio" name="'.$field['field_name'].'[which]" value="dec"></span> ';
						$output.= '<input type="text" name="'.$field['field_name'].'[dec]" size="12" ';
						if (isset($field['required']) && $field['required']) $output.= ' class="required"';
					} 
					if ($record) {
						if ($display == 'form') $output.= 'value="';
						$output.= $record[$field['field_name']];
						if ($display == 'form') $output.= '"';
					}
					if ($display == 'form') $output.= '>';
					
				} else {
					if ($display == 'form') {
						$output.= '<input type="text" ';
						if (isset($field['show_id']) && $field['show_id'] == 1) $output.= 'id="'.$field['field_name'].'"';
						$output.=  'name="'.$field['field_name'].'" size="'.$field['size'].'" ';
					}
					if ($display == 'form' && isset($field['required']) && $field['required']) $output.= ' class="required"';
					if ($record) {
						if ($display == 'form') $output.= 'value="';
						$output.= htmlchars($record[$field['field_name']]);
						if ($display == 'form') $output.= '"';
					}
					if ($display == 'form') $output.= '>';
				}
				if (isset($field['unit'])) {
					//if ($record) { 
					//	if ($record[$field['field_name']]) // display unit if record not null
					//		$output.= ' '.$field['unit']; 
					//} else {
						$output.= ' '.$field['unit']; 
					//}
				}
			} elseif ($field['type'] == 'thumbnail') {
				if ($record) {
					$output.= '<img src="'.$level.'/'.$record[$field['field_name']].'" alt="'.$record[$field['field_name']].'"><br>';
					$output.= '<input type="hidden" name="'.$field['field_name'].'" size="64" value="'.$record[$field['field_name']].'">';
				}
				else $output.= '<input type="text" name="'.$field['field_name'].'" size="64">';
			} elseif ($field['type'] == 'date') {
				if ($display == 'form') $output.= '<input type="text" name="'.$field['field_name'].'" size="12" ';
				if ($display == 'form' && isset($field['required']) && $field['required']) $output.= ' class="required"';
				if ($record) {
					if ($display == 'form') $output.= 'value="';
					$output.= datum_de($record[$field['field_name']]);
					if ($display == 'form') $output.= '"';
				} 
				if ($display == 'form') $output.= '>';
			} elseif ($field['type'] == 'memo') {
				if (!isset($field['rows'])) $field['rows'] = 8;
				if ($display == 'form') $output.= '<textarea rows="'.$field['rows'].'" cols="60" name="'.$field['field_name'];
				if ($display == 'form' && isset($field['required']) && $field['required']) $output.= ' class="required"';
				if ($display == 'form') $output.= '">';
				if ($record) {
					$memotext = stripslashes($record[$field['field_name']]);
					$memotext = htmlchars($memotext);
					if ($display != 'form' && isset($field['format'])) $memotext = $field['format']($memotext);
					$output.= $memotext;
				}
				if ($display == 'form') $output.= '</textarea>';
			//} elseif ($field['type'] == 'enum') {
			//	$output.= mysql_field_flags($field['field_name']);
			} elseif ($field['type'] == 'select') {
				//if ($action) $output.= $record[$field['field_name']];
				//else {
				if (isset($field['sql_without_id'])) $field['sql'] .= $record_id;
				if (isset($field['sql'])) {
					// ggfs. WHERE einfuegen
					if (isset($field['sql_where']) && $where_values) {
						$my_where = '';
						$add_details_where = ''; // for add_details
						foreach ($field['sql_where'] as $sql_where) {
							// might be several where-clauses
							if (!$my_where) $my_where = ' WHERE ';
							else $my_where .= ' AND ';
							if (isset($sql_where[2])) {
								foreach (array_keys($where_values) as $value_key)
									if ($value_key == $sql_where[1]) $sql_where[2].= $where_values[$value_key];
								$result_detail = mysql_query($sql_where[2]);
								if ($result_detail) {
									//if (mysql_num_rows($result_detail) == 1)
									// might be that there are more results, so that should not be a problem
										$index = mysql_result($result_detail,0,0);
									//else $output.= $sql_where[2];
								} else $output.= show_error(mysql_error(), $sql_where[2], false);
							}
							$my_where .= $sql_where[0]." = '".$index."'";
							$add_details_where .= '&amp;where['.$sql_where[0].']='.$index;
						}
						if (strstr($field['sql'], 'ORDER BY'))
							$field['sql'] = str_replace('ORDER BY', ($my_where.' ORDER BY'), $field['sql']);
						else
							$field['sql'] .= ' '.$my_where;
					}
					$result_detail = mysql_query($field['sql']);
					if (!$result_detail) $output.= show_error(mysql_error(), $field['sql'], false);
					else {
						/*
						if (mysql_num_rows($result_detail) == 1 && isset($field['select_no_choice']) && $field['select_no_choice']) {
							while ($line = mysql_fetch_array($result_detail)) {
								if ($display == 'form') $output.= '<input type="hidden" name="'.$field['field_name'].'" value="'.$line[0].'">';
								$i = 1;
								while (isset($line[$i])) {
									if ($i > 1) $output.= ' | ';
									$output.= $line[$i];
									$i++;
								}
							}
						} else*/
						if (mysql_num_rows($result_detail) > 0) {
							if ($display == 'form') {
								$output.= '<select name="'.$field['field_name'].'">'."\n";
								$output.= '<option value=""';
								if ($record) if (!$record[$field['field_name']]) $output.= ' selected';
								$output.= '>'.$text['none_selected'].'</option>';
							}
							while ($line = mysql_fetch_array($result_detail))
								if ($display == 'form')
									if (isset($field['show_hierarchy']) && $field['show_hierarchy'])
										if ($line[$field['show_hierarchy']])
											$my_select[$line['mutter_kategorie_id']][] = $line;
										else
											$my_select['NULL'][] = $line;
									else
										$output.= draw_select($line, $record, $field, false, 0, false);
								else
									if ($line[0] == $record[$field['field_name']]) {
									// same as above
										$i = 1;
										while (isset($line[$i])) {
											if ($i > 1) $output.= ' | ';
											$output.= $line[$i];
											$i++;
										}
									}
							if ($display == 'form') {
								if (isset($field['show_hierarchy']) && $field['show_hierarchy'])
									foreach ($my_select['NULL'] AS $my_field)
										$output.= draw_select($my_field, $record, $field, $my_select, 0, $field['show_hierarchy']);
								$output.= '</select>'."\n";
							}
						} else {
							$output.= '<input type="hidden" value="" name="'.$field['field_name'].'">';
							$output.= $text['no_selection_possible'];
						}
					}
				} elseif (isset($field['set'])) {
					$myvalue = '';
					$sets = count($field['set']);
					$myi=0;
					foreach ($field['set'] as $set) {
						$myi++;
						$myid = 'check-'.$field['field_name'].'-'.$myi;
						if ($display == 'form') {
							$output.= ' <label for="'.$myid.'"><input type="checkbox" id="'.$myid.'" name="'.$field['field_name'].'[]" value="'.$set.'"';
							if ($record) {
								if (isset($record[$field['field_name']]))
									if (!is_array($record[$field['field_name']])) 
									//	won't be array normally
										$set_array = explode(',', $record[$field['field_name']]);
									else
									//	just if a field did not pass validation, set fields become arrays
										$set_array = $record[$field['field_name']];
									if (is_array($set_array)) if (in_array($set, $set_array)) $output.= ' checked';
							} 
							$output.= '> '.$set.'</label>';
							if (count($field['set']) >=4) $output.= '<br>';
						} else {
							if (in_array($set, explode(',', $record[$field['field_name']]))) {
								if ($myvalue) $myvalue .= ' | ';
								$myvalue.= $set;
							}
						}
					}
					$output.=$myvalue;
				} elseif (isset($field['enum'])) {
					$myi=0;
					if ($display == 'form') {
						if (count($field['enum']) <= 2) {
							$myid = 'radio-'.$field['field_name'].'-'.$myi;
							$output.= '<label for="'.$myid.'" class="hidden"><input type="radio" id="'.$myid.'" name="'.$field['field_name'].'" value=""';
							if ($record) if (!$record[$field['field_name']]) $output.= ' checked';
							$output.= '>'.$text['no_selection'].'</label>';

						} else {
							$output.= '<select name="'.$field['field_name'].'">'."\n";
							$output.= '<option value=""';
							if ($record) if (!$record[$field['field_name']]) $output.= ' selected';
							$output.= '>'.$text['none_selected'].'</option>';
						} 
					}
					foreach ($field['enum'] as $set) {
						if ($display == 'form') {
							if (count($field['enum']) <= 2) {
								$myi++;
								$myid = 'radio-'.$field['field_name'].'-'.$myi;
								$output.= ' <label for="'.$myid.'"><input type="radio" id="'.$myid.'" name="'.$field['field_name'].'" value="'.$set.'"';
								if ($record) if ($set == $record[$field['field_name']]) $output.= ' checked';
								$output.= '> '.$set.'</label>';

							} else {
								$output.= '<option value="'.$set.'"';
								if ($record) if ($set == $record[$field['field_name']]) $output.= ' selected';
								$output.= '>';
								$output.= $set;
								$output.= '</option>';
							}
						} else {
							if ($set == $record[$field['field_name']]) $output.= $set;
						}
					}
					if ($display == 'form' && count($field['enum']) > 2) $output.= '</select>'."\n";
				} else {
					$output.= $text['no_source_defined'].'. '.$text['no_selection_possible'];
				}
			} elseif ($field['type'] == 'image') {
				$img = false;
				if (isset($field['path'])) {
					$output.= $img = show_image($field['path'], $record);
				}
				if (!$img) $output.= '('.$text['image_not_display'].')';
			} elseif ($field['type'] == 'calculated') {
				if (!$mode) {
					// identischer Code mit weiter unten, nur statt $line $record!!
					if ($field['calculation'] == 'hours') {
						$diff = 0;
						foreach ($field['calculation_fields'] as $calc_field) {
							if (!$diff) $diff = strtotime($record[$calc_field]);
							else $diff -= strtotime($record[$calc_field]);
						}
						$output.= gmdate('H:i', $diff);
					} elseif ($field['calculation'] == 'sum') {
						$sum = 0;
						foreach ($field['calculation_fields'] as $calc_field) {
							$sum += $record[$calc_field];
						}
						$output.= $sum;
						if (isset($field['unit'])) $output.= ' '.$field['unit'];
					}
				} else $output.= '('.$text['calculated_field'].')';
			}
			if (isset($default_value)) {
				if ($default_value)
					// unset $record so following fields are empty
					unset($record[$field['field_name']]); 
			}
			$output.= ' ';
			if (!isset($add_details_where)) $add_details_where = false;
			if ($mode) if (isset($field['add_details'])) $output.= ' <a href="'.$field['add_details'].'?mode=add&amp;referer='.urlencode($_SERVER['REQUEST_URI']).$add_details_where.'">['.$text['new'].' &hellip;]</a>';
			if (!$append_next) $output.= '</td></tr>'."\n";
		}
	}
	if ($mode) {
		$output.= '<tfoot>'."\n";
		$output.= '<tr><th>&nbsp;</th> <td><input type="submit" value="';
		if ($mode == 'edit') $output.= $text['update'].' ';
		elseif ($mode == 'review-edit') $output.= $text['update'].' ';
		elseif ($mode == 'delete') $output.= $text['delete_from'].' ';
		elseif ($mode == 'review-add') $output.= $text['add_to'].' ';
		else $output.= $text['add_to'].' ';
		$output.= $text['database'].'"></td></tr>'."\n";
		$output.= '</tfoot>'."\n";
	} else {
		if ($list) {
			$output.= '<tfoot>'."\n";
			$output.= '<tr><th>&nbsp;</th> <td class="reedit">';
			$output.= '<a href="'.$self.'?mode=edit&amp;id='.$record_id.$add_extras.'">'.$text['edit'].'</a>';
			if ($delete) $output.= ' | <a href="'.$self.'?mode=delete&amp;id='.$record_id.$add_extras.'">'.$text['delete'].'</a>';
			$output.= '</td></tr>'."\n";
			if (isset($more_actions)) {
				$output.= '<tr><th>&nbsp;</th><td class="editbutton">';
				$output.= show_more_actions($more_actions, $more_actions_url, $record_id, $line);
				$output.= '</td></tr>';
			}
			$output.= '</tfoot>'."\n";
		}
	}
	$output.= '</table>'."\n";
	if ($mode == 'delete') $output.= '<input type="hidden" name="'.$query[1]['field_name'].'" value="'.$record_id.'">';
	if ($mode) $output.= '<input type="hidden" name="action" value="'.$submit.'">';
	if ($mode && $referer) $output.= '<input type="hidden" value="'.$referer.'" name="referer">';
	if ($mode && $file) $output.= '<input type="hidden" value="'.$file.'" name="file">';
	if (isset($editvar['variable'])) {
		foreach ($editvar['variable'] as $var) {
			if (isset($record[$var['field_name']])) $output.= '<input type="hidden" value="'.$record[$var['field_name']].'" name="'.$var['field_name'].'">';
		}
	}
	if ($mode OR $action == 'review') $output.= '</form>';
	$output.= '</div>'."\n";
}

if ($mode != 'add' && $add) $output.= '<p class="add-new"><a href="'.$self.'?mode=add'.$add_extras.'">'.$text['add_new_record'].'</a></p>';
if ($referer && $backlink) $output.= '<p id="back-overview"><a href="'.$referer.'">'.$text['back-to-overview'].'</a></p>';


// Display
// Elemente der Tabelle herausnehmen, die nicht angezeigt werden sollen

foreach ($query as $field) {
	if (!isset($field['hide_in_list'])) {
		$table_query[] = $field;
	} else {
		if (!$field['hide_in_list']) $table_query[] = $field;
	} 
}

//
// Table head
//

// ORDER BY

if (isset($_GET['order'])) {
	$my_order = $_GET['order'];
	if (isset($_GET['dir'])) {
		if ($_GET['dir'] == 'asc') $my_order.= ' ASC';
		elseif ($_GET['dir'] == 'desc') $my_order.= ' DESC';
	}
	if (strstr($sql, 'ORDER BY')) {
		$sql = str_replace ('ORDER BY', 'ORDER BY '.$my_order.', ', $sql);
	} else {
		$sql.= ' ORDER BY '.$my_order;
	}
}

if ($list AND $tabelle) {
	if ($limit) $sql.= ' LIMIT '.($limit-20).', 20';
	$result = mysql_query($sql);
	$count_rows = mysql_num_rows($result);
	if ($result && $count_rows > 0) {
		$output.= '<table class="data">';
		$output.= '<thead>'."\n";
		$output.= '<tr>';
		foreach ($table_query as $field) {
			$output.= '<th';
			$output.= check_if_class($field, $where_values);
			$output.= '>';
			if ($field['type'] != 'calculated' && $field['type'] != 'image') {
				$output.= '<a href="';
				if (isset($field['display_field'])) $order_val = $field['display_field'];
				else $order_val = $field['field_name'];
				$uri = addvar($_SERVER['REQUEST_URI'], 'order', $order_val);
				$order_dir = 'asc';
				if ($uri == $_SERVER['REQUEST_URI']) {
					$uri.= '&dir=desc';
					$order_dir = 'desc';
				}
				$output.= $uri;
				$output.= '" title="'.$text['order by'].' '.strip_tags($field['title']).' ('.$text[$order_dir].')">';
			}
			$output.= $field['title'];
			if ($field['type'] != 'calculated')
				$output.= '</a>';
			$output.= '</th>';
		}
		$output.= ' <th class="editbutton">'.$text['action'].'</th>';
		if (isset($more_actions) && $more_actions) $output.= ' <th class="editbutton">'.$text['detail'].'</th>';
		$output.= '</tr>';
		$output.= '</thead>'."\n";
		$output.= '<tbody>'."\n";
	} else {
		$tabelle = false;
		$show_search = true;
		$output.= '<p>'.$text['table-empty'].'</p>';
	}
//
// Table body
//	
	if (!$result) {
		$output.= show_error (mysql_error(), $sql, $text['error-sql-incorrect'], false);
	} else {
		if (mysql_num_rows($result) > 0) {
			$z = 0;
			while ($line = mysql_fetch_array($result)) {
				$output.= '<tr class="';
				$output.= ($z & 1 ? 'uneven':'even');
				$output.= '">'; //onclick="Highlight();"
				$id = '';
				foreach ($table_query as $field) {
					$output.= '<td';
					$output.= check_if_class($field, $where_values);
					$output.= '>';
					if ($field['type'] == 'calculated') {
						if ($field['calculation'] == 'hours') {
							$diff = 0;
							foreach ($field['calculation_fields'] as $calc_field) {
								if (!$diff) $diff = strtotime($line[$calc_field]);
								else $diff -= strtotime($line[$calc_field]);
							}
							$output.= gmdate('H:i', $diff);
							if (isset($field['sum']) && $field['sum'] == true) {
								if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
								$sum[$field['title']] += $diff;
							}
						} elseif ($field['calculation'] == 'sum') {
							$my_sum = 0;
							foreach ($field['calculation_fields'] as $calc_field) {
								$my_sum += $line[$calc_field];
							}
							$output.= $my_sum;
							if (isset($field['sum']) && $field['sum'] == true) {
								if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
								$sum[$field['title']] .= $my_sum;
							}
						} elseif ($field['calculation'] == 'sql') {
							$output.= $line[$field['field_name']];
						}
					} elseif ($field['type'] == 'image') {
						if (isset($field['path'])) {
							$output.= $img = show_image($field['path'], $line);
						}
					} elseif ($field['type'] == 'thumbnail' && $line[$field['field_name']]) {
						$output.= '<img src="'.$level.'/'.$line[$field['field_name']].'" alt="'.$line[$field['field_name']].'">';
					} else {
						if ($field['type'] == 'url') $output.= '<a href="'.$line[$field['field_name']].'">';
						if ($field['type'] == 'mail') $output.= '<a href="mailto:'.$line[$field['field_name']].'">';
						if (isset($field['link'])) {
							if (is_array($field['link'])) {
								$output.= '<a href="'.show_link($field['link'], $line);
								if (!isset($field['link_no_append'])) $output.= $line[$field['field_name']];
								$output.= '">';
							} else $output.= '<a href="'.$field['link'].$line[$field['field_name']].'">';
						}
						if (isset($field['display_field'])) $output.= htmlchars($line[$field['display_field']]);
						else {
							if (isset($field['factor']) && $line[$field['field_name']]) $line[$field['field_name']] /=$field['factor'];
							if ($field['type'] == 'date') $output.= datum_de($line[$field['field_name']]);
							elseif (isset($field['number_type']) && $field['number_type'] == 'currency') $output.= waehrung($line[$field['field_name']], '');
							elseif (isset($field['number_type']) && $field['number_type'] == 'latitude' && $line[$field['field_name']]) {
								$deg = dec2dms($line[$field['field_name']], '');
								$output.= $deg['latitude'];
							} elseif (isset($field['number_type']) && $field['number_type'] == 'longitude' &&  $line[$field['field_name']]) {
								$deg = dec2dms('', $line[$field['field_name']]);
								$output.= $deg['longitude'];
							}
							else $output.= nl2br(htmlchars($line[$field['field_name']]));
						}
						if ($field['type'] == 'url') $output.= '</a>';
						if (isset($field['link'])) $output.= '</a>';
						if (isset($field['sum']) && $field['sum'] == true) {
							if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
							$sum[$field['title']] += $line[$field['field_name']];
						}
					}
					if (isset($field['unit'])) 
						/* && $line[$field['field_name']]) does not work because of calculated fields*/ 
						$output.= '&nbsp;'.$field['unit'];	
					$output.= '</td>';
					if ($field['type'] == 'id') $id = $line[$field['field_name']];
				}
				$output.= '<td class="editbutton"><a href="'.$self.'?mode=edit&amp;id='.$id.$add_extras.'">'.$text['edit'].'</a>';
				if ($delete) $output.= '&nbsp;| <a href="'.$self.'?mode=delete&amp;id='.$id.$add_extras.'">'.$text['delete'].'</a>';
				if (isset($more_actions)) {
					$output.= '</td><td class="editbutton">';
					$output.= show_more_actions($more_actions, $more_actions_url, $id, $line);
				}
				$output.= '</td>';
				$output.= '</tr>'."\n";
				$z++;
			}
		}
	}	

// Table footer

	$output .= '</tbody>'."\n";

	if ($tfoot && isset($z)) {
		$output.= '<tfoot>'."\n";
		$output.= '<tr>';
		foreach ($table_query as $field) {
			if ($field['type'] == 'id') $output.= '<td class="recordid">'.$z.'</td>';
			elseif (isset($field['sum']) AND $field['sum'] == true) {
				$output.= '<td>';
				if (isset($field['calculation']) AND $field['calculation'] == 'hours')
					$sum[$field['title']] = hours($sum[$field['title']]);
				$output.= $sum[$field['title']];
				if (isset($field['unit'])) $output.= '&nbsp;'.$field['unit'];	
				$output.= '</td>';
			}
			else $output.= '<td>&nbsp;</td>';
		}
		$output.= '<td class="editbutton">&nbsp;</td>';
		$output.= '</tr>'."\n";
		$output.= '</tfoot>'."\n";
	}

	$output.= '</table>'."\n";

	if ($mode != 'add' && $add && $tabelle) {
		$output.= '<p class="add-new bottom-add-new"><a href="'.$self.'?mode=add'.$add_extras.'">'.$text['add_new_record'].'</a></p>';
	}
	if ($limit && $count_rows > 19 OR $limit > 20) {
		$next = false;
		$prev = false;
		$result = mysql_query(preg_replace('/LIMIT \d+, \d+/i', '', $sql));
		if ($result) $rows = mysql_num_rows($result);
		if ($rows) {
			$uri = $_SERVER['REQUEST_URI'];
			$all = str_replace('&limit='.$limit, '', $uri);
			$all = str_replace('?limit='.$limit, '', $all);
			if (strstr($all, '?')) $all .= '&limit=0';
			else $all .= '?limit=0';
			if ($limit < $rows) {
				$next = str_replace('&limit='.$limit, '&limit='.($limit+20), $uri);
				$next = str_replace('?limit='.$limit, '?limit='.($limit+20), $next);
				if (!strstr($next, 'limit=')) {
					if (strstr($next, '?')) $next.= '&';
					else $next.= '?';
					$next.= 'limit='.($limit+20);
				}
			} else $next = false;
			if ($limit > 20) {
				$prev = str_replace('&limit='.$limit, '&limit='.($limit-20), $uri);
				$prev = str_replace('?limit='.$limit, '?limit='.($limit-20), $prev);
				if (!strstr($prev, 'limit=')) {
					if (strstr($prev, '?')) $prev.= '&';
					else $prev.= '?';
					$prev.= 'limit='.($limit+20);
				}
			} else $prev = false;
			$output.= '<ul>';
			if ($prev) $output.= '<li><a href="'.$prev.'">'.$text['prev_20_records'].'</a></li>';
			$output.= '<li><a href="'.$all.'">'.$text['all_records'].'</a></li>';
			if ($next) $output.= '<li><a href="'.$next.'">'.$text['next_20_records'].'</a></li>';
			$output.= '</ul>';
		}
	}
}
$output.= '</div>';
if (($list AND $tabelle) OR isset($show_search)) {
	if ($editvar['search'] == true) {
		$output.= "\n";
		$output.= '<form method="GET" action="'.$self;
		$output.= '" class="search">';
		foreach (array_keys($_GET) as $key)
			if (is_array($_GET[$key]))
				foreach(array_keys($_GET[$key]) as $subkey)
					$output.= '<input type="hidden" name="'.$key.'['.$subkey.']" value="'.$_GET[$key][$subkey].'">';
			else 
				if ($key != 'q' && $key != 'scope' && $key != 'limit')
					$output.= '<input type="hidden" name="'.$key.'" value="'.$_GET[$key].'">';
		$output.= '<input type="text" size="30" name="q"';
		if (isset($_GET['q'])) $output.= ' value="'.htmlchars($_GET['q']).'"';
		$output.= '>';
		$output.= ' <input type="submit" value="'.$text['search'].'">';
		$output.= ' '.$text['in'].' ';	
		$output.= '<select name="scope">';
		$output.= '<option value="">'.$text['all fields'].'</option>';
		foreach ($query as $field) {
			$fieldname = (isset($field['display_field']) && $field['display_field']) ? $field['display_field'] : $maintable.'.'.$field['field_name'];
			if ($field['type'] != 'calculated' && $field['type'] != 'image')
			$output.= '<option value="'.$fieldname.'"';
			if (isset($_GET['scope'])) if ($_GET['scope'] == $fieldname) $output.= ' selected';
			$output.= '>'.$field['title'].'</option>';
		}
		$output.= '</select>';
		$output.= '</form>'."\n";
	}
}

if ($show_output) echo $output;



//
//	functions
//

function ueberschrift($string) {
	$string = str_replace('_', ' ', $string);
	$string = ucfirst($string);
	return $string;
}

function unhtmlspecialchars( $string ) {
	$string = str_replace ( '&amp;', '&', $string );
	$string = str_replace ( '&#039;', '\'', $string );
	$string = str_replace ( '&quot;', '\"', $string );
	$string = str_replace ( '&lt;', '<', $string );
	$string = str_replace ( '&gt;', '>', $string );
	return $string;
}
   
function show_error ($error, $sql, $mytext) {
	global $list;
	global $text;
	global $project;
	global $error_mail_to;
	global $error_mail_from;
	$output = '';
	$output.= '<p><em>'.$text['database-error'].'! '.$text['reason'].':</em> '.$error.'</p>';
	if (!isset($error_mail_to)) {
		// Error will be shown
		if ($list) {
			$output.= '<p>';
			$output.= '<em>'.$text['sql-query'].':</em> ';
			$output.= $sql.'</p>';
		}
	} else {
		// Error will be mailed
		mail ($error_mail_to, '['.unhtmlspecialchars($project).']', $error."\n\n".$sql, 'From: '.$error_mail_from);
	}
	return $output;
}

function hours($seconds) {
	$hours = 0;
	$minutes = 0;
	while ($seconds >= 60) {
		$seconds -= 60;
		$minutes++;
	}
	while ($minutes >= 60) {
		$minutes -= 60;
		$hours++;
	}
	if (strlen($minutes) == 1) $minutes = '0'.$minutes;
	$time = $hours.':'.$minutes;
	return $time;
}

function field_in_where($field, $values) {
	$where = false;
	foreach (array_keys($values) as $value)
		if ($value == $field) $where = true;
	return $where;
}

function check_maxlength($field) {
	global $maintable;
	$sql = 'SHOW COLUMNS FROM '.$maintable.' LIKE "'.$field.'"';
	$result = mysql_query($sql);
	if ($result)
		if (mysql_num_rows($result) == 1) {
			$maxlength = mysql_fetch_array($result);
			preg_match('/varchar\((\d+)\)/s', $maxlength['Type'], $my_result);
			if ($my_result) return $my_result[1];
		}
	return false;
}

function check_if_class ($field, $values) {
	$class = false;
	if ($field['type'] == 'id') $class = 'recordid';
	elseif ($field['type'] == 'number' OR $field['type'] == 'calculated') $class ='number';
	if ($values) {
		if (field_in_where($field['field_name'], $values)) 
			if ($class) $class .= ' where';
			else $class = 'where';
	}
	if ($class) return ' class="'.$class.'"';
	else return false;
}

function addvar($uri, $field, $value) {
	$uri_p = parse_url($uri);
	if (isset($uri_p['query'])) {
		parse_str($uri_p['query'], $queries);
		unset($queries['dir']); // ORDER direction will be removed - attention if function will be used for other purposes
	}
	$queries[$field] = $value;
	$new_uri = $uri_p['path'].'?'; 
	// other uri parts are ignored, may be changed if necessary
	// e. g. fragment.
	foreach (array_keys($queries) as $query_key) {
		if ($new_uri != $uri_p['path'].'?') $new_uri.= '&';
		if (is_array($queries[$query_key])) {
			foreach (array_keys($queries[$query_key]) as $qq_key) {
				$new_uri.= $query_key.'['.$qq_key.']='.$queries[$query_key][$qq_key];
			}
		} else
			$new_uri.= $query_key.'='.$queries[$query_key];
	}
	return $new_uri;
}

function timestamp2date($timestamp) {
	if ($timestamp) {
		$date = substr($timestamp,6,2).'.'.substr($timestamp,4,2).'.'.substr($timestamp, 0,4).' ';
		$date.= substr($timestamp,8,2).':'.substr($timestamp,10,2).':'.substr($timestamp,12,2);
		return $date;
	} else return false;
}

function get_to_array($get) {
	$extras = false;
	foreach (array_keys($get) as $where_key)
		$extras.= '&where['.$where_key.']='.$get[$where_key];
	return $extras;
}

function read_fields($array, $mode, $values) {
	global $maintable;
	foreach (array_keys($array) as $val_key) {
		$values[$val_key] = $array[$val_key];
		if ($mode == 'replace') {
			if (substr($val_key, 0, strlen($maintable)) == $maintable) {
				// maintable. aus string entfernen!
				$val_key_new = substr($val_key, strlen($maintable) +1, (strlen($val_key) - strlen($maintable)));
				$values[$val_key_new] = $array[$val_key];
				unset ($values[$val_key]);
			}
			if (strpos($val_key, '.')) {
				// macht obere Funktion eigentl. ueberfluessig, oder koennen Feldnamen Punkte enthalten?
				$val_key_new = strstr($val_key, '.');
				$val_key_new = substr($val_key_new, 1, strlen($val_key_new) -1);
				$values[$val_key_new] = $array[$val_key];
				unset ($values[$val_key]);
			}
		}
	}
	return $values;
}

function show_image($path, $record) {
	global $text;
	$img = false;
	if ($record) {
		$img = '<img src="';
		$alt = $text['no_image'];
		$img_src = '';
		foreach (array_keys($path) as $part) {
			if (substr($part,0,4) == 'root')
				$root = $path[$part];
			elseif (substr($part,0,4) == 'mode') {
				$mode[] = $path[$part];
			} elseif (substr($part,0,5) == 'field') {
				if (!isset($mode))
					$img_src.= $record[$path[$part]];
				else {
					$content = $record[$path[$part]];
					foreach ($mode as $mymode) {
						$content = $mymode($content);
					}
					$img_src.= $content;
				}
				$alt = 'Image: '.$record[$path[$part]];
			}
			else $img_src.= $path[$part];
		}
		if (!isset($root))
			$img.= $img_src;
		else
			// check whether image exists
			if (file_exists($root.$img_src)) $img.= $img_src;
			else return false;
		$img.= '" alt="'.$alt.'" class="thumb">';
	}
	return $img;
}

function show_link($path, $record) {
	global $field;
	$link = false;
	if ($record)
		foreach (array_keys($path) as $part) {
			if (substr($part,0,5) == 'field') $link.= $record[$path[$part]];
			else $link.= $path[$part];
		}
	return $link;
}

function show_more_actions($more_actions, $more_actions_url, $id, $line) {
	$act = 0;
	$output = '';
	foreach ($more_actions as $new_action) {
		if ($act) $output.= '&nbsp;| ';
		$act++;
		$output.= '<a href="'.$new_action;
		if (isset($more_actions_url))
			if (is_array($more_actions_url))
				foreach (array_keys($more_actions_url) as $part_key)
					if (substr($part_key, 0, 5) == 'field')
						$output.= $line[$more_actions_url[$part_key]];
					else
						$output.= $more_actions_url[$part_key];
			else
				$output.= $more_actions_url;
		else $output.= '.php?id=';
		if (!isset($more_actions_url) OR !is_array($more_actions_url)) $output.= $id;
		$output.= '&amp;referer='.urlencode($_SERVER['REQUEST_URI']);
		$output.= '">'.ucfirst($new_action).'</a>';
	}
	return $output;
}

function draw_select($line, $record, $field, $hierarchy, $level, $parent_field_name) {
	//echo $field['field_name'].'tt';
	//echo $parent_field_name.'dd';
	$output = '<option value="'.$line[0].'"';
	if ($record) if ($line[0] == $record[$field['field_name']]) $output.= ' selected';
	if ($hierarchy) $output.= ' class="level'.$level.'"';
	$output.= '>';
	$i = 1;
	$output.= str_repeat('&nbsp;', 6*$level); 
	foreach (array_keys($line) as $key) {
		/*
			$i = 1: field['type'] == 'id'!
		*/
		if ($key != $parent_field_name && !is_numeric($key)) {
			if ($i > 2) $output.= ' | ';
			if ($i > 1)$output.= $line[$key];
			$i++;
		}
	}
	$output.= '</option>';
	$level++;
	if ($hierarchy && isset($hierarchy[$line[0]])) {
		foreach ($hierarchy[$line[0]] as $secondline)
			$output.= draw_select($secondline, $record, $field, $hierarchy, $level, $parent_field_name);
	}
	return $output;
}

function htmlchars($string) {
	$string = str_replace('&amp;', '&', htmlspecialchars($string));
	$string = str_replace('&quot;', '"', $string);
	return $string;
}

?>