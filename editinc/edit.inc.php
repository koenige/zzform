<?php 


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

// if $query has no title get it out of field_name

// Variables

$error = false;

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
	foreach (array_keys($values) as $value) {
		if ($value == $field) $where = true;
	}
	return $where;
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
		} else {
			$new_uri.= $query_key.'='.$queries[$query_key];
		}
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
	foreach (array_keys($get) as $where_key) {
		$extras.= '&where['.$where_key.']='.$get[$where_key];
	}
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
	$img = false;
	if ($record) {
		$img = '<img src="';
		$alt = $text['no_image'];
		foreach (array_keys($path) as $part) {
			if (substr($part,0,5) == 'field') {
				$img.= $record[$path[$part]];
				$alt = 'Image: '.$record[$path[$part]];
			}
			else $img.= $path[$part];
		}
		$img.= '" alt="'.$alt.'" class="thumb">';
	}
	return $img;
}

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

if (!isset($verbindung)) include ($level.'/'.$inc.'/db.inc.php');
if (!function_exists('datum_de')) include ($level.'/'.$inc.'/numbers.inc.php');
if (file_exists($level.'/'.$inc.'/dec2dms.inc.php')) include_once($level.'/'.$inc.'/dec2dms.inc.php');
if (file_exists($level.'/'.$inc.'/coords.inc.php')) include_once($level.'/'.$inc.'/coords.inc.php');
if (file_exists($level.'/'.$inc.'/coords-edit.inc.php')) include_once($level.'/'.$inc.'/coords-edit.inc.php');
/*
if (!function_exists('waehrung')) {
	if (file_exists ($level.'/'.$inc.'/waehrung.inc.php'))
		include ($level.'/'.$inc.'/waehrung.inc.php');
}*/
if (!isset($delete)) $delete = false;
if (!isset($add)) $add = true;			// Add New Record wird angeboten
if (!isset($list)) $list = true;		// nur hinzufügen möglich, nicht bearbeiten, keine Tabelle
if (!isset($tabelle)) $tabelle = true;  // nur bearbeiten möglich, keine Tabelle
if (!isset($tfoot)) $tfoot = false;  // Tabellenfuss
if (isset($_GET['tabelle'])) $tabelle = $_GET['tabelle'];
if (isset($_GET['limit'])) $limit = $_GET['limit'];
if (!isset($limit)) $limit = false;		// nur 20 Datensaetze auf einmal angezeigt
if (!isset($referer)) {
	$referer = false;
	if (isset($_GET['referer'])) $referer = $_GET['referer'];
	if (isset($_POST['referer'])) $referer = $_POST['referer'];
} elseif (isset($_POST['referer'])) {
	$referer = $_POST['referer'];
} else {
	$referer = $_SERVER['HTTP_REFERER'];
}

if (isset($_GET['test'])) {
	foreach (array_keys($_GET['test']) as $test) {
		echo $test.': '.$_GET['test'][$test];
	}
}

// Add with suggested values
$values = false;
if (isset($_GET['value'])) $values = read_fields($_GET['value'], 'replace', $values);
if (isset($_GET['where'])) $values = read_fields($_GET['where'], 'replace', $values);
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

if ($mode == 'add') $submit = 'insert';
elseif ($mode == 'edit') $submit = 'update';
elseif ($mode == 'delete') $submit = 'delete';

?>
<script type="text/javascript">
<!--
	function Highlight() {
		this.style.backgroundColor = '#CCC';
	}
//-->
</script>

<h2><?php echo ueberschrift($maintable);?></h2>
<?php 

// Extra GET Parameter

$extras = false;
$addextras = false;
if (isset($_GET['where'])) $extras .= get_to_array($_GET['where']);
// elseif ($values) $extras .= '&where='.$_GET['values'];
if ($limit) $extras.= '&limit='.$limit;
if ($referer) $extras.= '&referer='.$referer;
if ($extras) $extras = substr($extras, 1, strlen($extras) -1 ); // first ? or & to be added as needed!

if ($mode) echo '<form action="'.$_SERVER['PHP_SELF'].'?'.$extras.'" method="POST">';

// Add, Update or Delete

if ($action == 'insert') {
	$sql_edit = 'INSERT INTO '.$maintable;
	$sql_edit .= ' (';
	$i = 0;
	foreach ($query as $field) {
		if ($field['type'] != 'calculated' && $field['type'] != 'image') {
			if ($i) $sql_edit .= ', ';
			$sql_edit .= $field['field_name'];
			$i++;
		}
	}
	$sql_edit .= ') VALUES (';
	$i = 0;
	$error = false;
	foreach ($query as $field) {
		if (isset($field['factor']) && $_POST[$field['field_name']]) $_POST[$field['field_name']] *=2;
		if ($field['type'] == 'number' AND isset($field['number_type']) AND $field['number_type'] == 'latitude' || $field['number_type'] == 'longitude') {
			// geographical coordinates
			if ($_POST[$field['field_name']]['which'] == 'dec') $_POST[$field['field_name']] = $_POST[$field['field_name']]['dec'];
			elseif ($_POST[$field['field_name']]['which'] == 'dms') {
				$degree = dms2db($_POST[$field['field_name']]); 
				$error .= $error_message;
				$_POST[$field['field_name']] = $degree[substr($field['number_type'], 0, 3).'dec'];
			}
			if (strlen($_POST[$field['field_name']]) == 0) $_POST[$field['field_name']] = NULL;
		} 
		if ($field['type'] != 'calculated' && $field['type'] != 'image') {
			if ($i && $field['type']) $sql_edit.= ', ';
			if ($field['type'] == 'id') $sql_edit .= "''";
			else {
				if ($_POST[$field['field_name']]) {
					if ($field['type'] == 'date')
						$sql_edit .= "'".addslashes(datum_int($_POST[$field['field_name']]))."'";
					/*elseif ($field['type'] == 'currency')
						$sql_edit .= "'".addslashes(waehrung_int($_POST[$field['field_name']]))."'";
					*/
					else 
						$sql_edit .= "'".addslashes($_POST[$field['field_name']])."'";
				} else
					if (isset($field['number_type']) AND !is_null($_POST[$field['field_name']]) AND $field['number_type'] == 'latitude' || $field['number_type'] == 'longitude') $sql_edit .= '0';
					else $sql_edit .= 'NULL';
			}
			$i++;
		}
	}
	$sql_edit .= ')';
	$result = mysql_query($sql_edit);
	if ($result) $success = $text['record_was_inserted'];
	else {
		$success = false;
		$my_error = mysql_error();
		$error_sql = $sql_edit;
	}
} elseif ($action == 'update') {
	$sql_edit = 'UPDATE '.$maintable;
	$sql_edit.= ' SET ';
	$i = 0;
	foreach ($query as $field) {
		if (isset($field['factor']) && $_POST[$field['field_name']]) $_POST[$field['field_name']] *=2;
		if ($field['type'] == 'number' AND isset($field['number_type']) AND $field['number_type'] == 'latitude' || $field['number_type'] == 'longitude') {
			// geographical coordinates
			if ($_POST[$field['field_name']]['which'] == 'dec') {
				$_POST[$field['field_name']] = $_POST[$field['field_name']]['dec'];
			}
			elseif ($_POST[$field['field_name']]['which'] == 'dms') {
				$degree = dms2db($_POST[$field['field_name']]); 
				$error .= $error_message;
				$_POST[$field['field_name']] = $degree[substr($field['number_type'], 0, 3).'dec'];
			}
			if (strlen($_POST[$field['field_name']]) == 0) $_POST[$field['field_name']] = NULL;
		} 
		if ($field['type'] != 'id' AND $field['type'] != 'calculated' && $field['type'] != 'image') {
			if ($i) $sql_edit.= ', ';
			if ($_POST[$field['field_name']]) {
				if ($field['type'] == 'date') {
					$field_value = "'".datum_int($_POST[$field['field_name']])."'";
				/*} elseif ($field['type'] == 'currency') {
					$field_value = "'".waehrung_int($_POST[$field['field_name']])."'";*/
				} else 
					$field_value = "'".addslashes($_POST[$field['field_name']])."'";
			} else {
				if (isset($field['number_type']) AND !is_null($_POST[$field['field_name']]) AND $field['number_type'] == 'latitude' || $field['number_type'] == 'longitude') $field_value = '0';
				else $field_value = 'NULL';
			}
			$sql_edit.= $field['field_name'].' = '.$field_value;
			$i++;
		}
	}
	$sql_edit .= ' WHERE ';
	foreach ($query as $field) {
		if ($field['type'] == 'id') $sql_edit.= $field['field_name'].' = '.$_POST[$field['field_name']];
	}
	$result = mysql_query($sql_edit);
	if ($result) $success = $text['record_was_updated'];
	else {
		$success = false;
		$my_error = mysql_error();
		$error_sql = $sql_edit;
	}
} elseif ($action == 'delete') {
	$sql_edit = 'DELETE FROM '.$maintable;
	$sql_edit.= ' WHERE '.$query[1]['field_name']." = '".$_POST[$query[1]['field_name']]."'";
	$result = mysql_query($sql_edit);
	if ($result) {
		$success = $text['record_was_deleted'];
		if (isset($delete_action)) include ($level.'/'.$inc.'/'.$delete_action.'.inc.php'); // if any other action after deletion is required
	} else {
		$success = false;
		$my_error = mysql_error();
		$error_sql = $sql_edit;
	}
}

// Query Updated, Added or Editable Record

if ($action == 'update') $record_id = $_POST[$query[1]['field_name']];
elseif ($action == 'insert') $record_id = mysql_insert_id();
$record = '';
if ($action != 'delete') {
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
			// echo 'Error in Database. Possibly the SQL statement is incorrect: '.$sql_edit;
			}
		} else {
			echo '<p>'.$sql_edit.'</p>';
			echo '<p>'.mysql_error().'</p>';
		}
	}
}
if ($sql_where) {
	$sql_ext = false;
	foreach (array_keys($sql_where) as $field) {
		if (!$sql_ext) $sql_ext = ' WHERE ';
		else $sql_ext .= ' AND ';
		$sql_ext .= $field." = '".$sql_where[$field]."' ";
	}
	$sql.= $sql_ext;
}
$sql.= $sqlorder; // must be here because of where-clause

// Display Updated, Added or Editable Record

if ($mode) {
	$display = 'form';
	$h3 = $text[$mode].' '.$text['a_record'];
	if ($mode == 'delete') $display = 'review';
} elseif ($action) {
	if ($action == 'delete') $display = false;
	else $display = 'review';
	$h3 = $success;
	if (!$h3) {
		$h3 = $text[$action].' '.$text['failed'];
		echo '<div id="add">'."\n";
		echo '<h3>'.ucfirst($h3).'</h3>'."\n";
		echo '<p><em>'.$text['reason'].':</em> '.$my_error;
		if ($list) echo '<br><em>'.$text['sql-query'].':</em> '.$error_sql.'</p>';
		echo '</div>'."\n";
		$display = false;
	}
	if ($error) {
		echo '<h3>'.$text['warning'].'!</h3>'."\n";
		echo '<p>'.$text['following_values_checked_again'].':'."\n";
		echo $error.'</p>';
	}
} else {
	$display = false;
}
if ($display) {
	echo '<div id="add">'."\n";
	echo '<h3>'.ucfirst($h3).'</h3>'."\n";
	echo '<table class="record">'; 
	foreach ($query as $field) {
		if (!($field['type'] == 'id' AND !$list)) {
			echo '<tr>';
			echo '<th>';
			echo $field['title'];
			echo '</th> ';
			echo '<td>';
			if ($record && isset($field['factor']) && $record[$field['field_name']]) echo $record[$field['field_name']] /=2;
			if (isset($values[$field['field_name']])) {
				if ($field['type'] == 'select') $field['type_detail'] = 'select';
				else $field['type_detail'] = false;
				$field['type'] = 'predefined';
			}
			if ($field['type'] == 'id') {
				if ($record_id) echo '<input type="hidden" value="'.$record_id.'" name="'.$field['field_name'].'">'.$record_id;
				else echo '('.$text['will_be_added_automatically'].')&nbsp;';
			} elseif ($field['type'] == 'hidden') {
				echo '<input type="hidden" value="';
				if (isset($field['value'])) echo $field['value'];
				elseif ($record) echo $record[$field['field_name']];
				echo '" name="'.$field['field_name'].'">';
				if ($record) {
					if (isset($field['timestamp']) && $field['timestamp'])
						echo timestamp2date($record[$field['field_name']]);
					elseif (isset($field['display_field'])) echo $record[$field['display_field']];
					else echo $record[$field['field_name']];
				} else {
					echo '('.$text['will_be_added_automatically'].')&nbsp;';
				}
			} elseif ($field['type'] == 'predefined') {
				echo '<input type="hidden" name="'.$field['field_name'].'" value="'.$values[$field['field_name']].'">';
				if ($field['type_detail'] == 'select') {
					if (strstr($field['sql'], 'ORDER BY')) {
						$mysql = str_replace('ORDER BY', (' WHERE '.$field['field_name'].' = '.$values[$field['field_name']].' ORDER BY'), $field['sql']);
					} else {
						$mysql = $field['sql'].' WHERE '.$field['field_name'].' = '.$values[$field['field_name']];
					}
					$result = mysql_query($mysql);
					if ($result) {
						if (mysql_num_rows($result) == 1) {
							$myline = mysql_fetch_assoc($result);
							$my_i = 0;
							foreach ($myline as $myfield) {
								if ($my_i) {
									if ($my_i != 1) echo ' | ';
									echo $myfield;
								}
								$my_i++;
							}
							unset ($my_i);
						}
					} else echo $text['database_error'].': '.mysql_error().'<br>'.$mysql;
				} else {
					echo $values[$field['field_name']];
				}
			} elseif ($field['type'] == 'text' OR $field['type'] == 'url'
				OR $field['type'] == 'time'
				OR $field['type'] == 'set' OR $field['type'] == 'mail'
				OR $field['type'] == 'datetime'
			) {
				if ($display == 'form') echo '<input type="text" name="'.$field['field_name'].'" size="32" ';
				if ($display == 'form' && isset($field['required']) && $field['required']) echo ' class="required"';
				if ($record) {
					if ($display == 'form') echo 'value="';
					echo htmlspecialchars($record[$field['field_name']]);
					if ($display == 'form') echo '"';
				} elseif ($mode == 'add' AND $field['type'] == 'datetime') { 
					echo 'value="'.date('Y-m-d H:i:s', time()).'"';
				}
				if ($display == 'form') echo '>';
			} elseif ($field['type'] == 'number') {
				if (isset($field['number_type']) AND $field['number_type'] == 'latitude' || $field['number_type'] == 'longitude') {
					$var = false;
					if ($record) {
						if ($field['number_type'] == 'latitude') $var = dec2dms($record[$field['field_name']], '');
						elseif ($field['number_type'] == 'longitude') $var = dec2dms('', $record[$field['field_name']]);
					}
					if ($display == 'form') {
						echo "&deg; ' ''".': <input type="radio" name="'.$field['field_name'].'[which]" value="dms" checked="checked"> ';
						print_editform($field['field_name'].'['.substr($field['number_type'],0,3), $var);
						echo ' || ';
					} elseif ($var) {
						echo $var[$field['number_type']];
						echo ' || ';
					} else {
						echo 'N/A';
					}
					if ($display == 'form') {
						echo $text['decimal'].': <input type="radio" name="'.$field['field_name'].'[which]" value="dec"> ';
						echo '<input type="text" name="'.$field['field_name'].'[dec]" size="12" ';
						if (isset($field['required']) && $field['required']) echo ' class="required"';
					} 
					if ($record) {
						if ($display == 'form') echo 'value="';
						echo $record[$field['field_name']];
						if ($display == 'form') echo '"';
					}
					if ($display == 'form') echo '>';
					
				} else {
					if ($display == 'form') echo '<input type="text" name="'.$field['field_name'].'" size="16" ';
					if ($display == 'form' && isset($field['required']) && $field['required']) echo ' class="required"';
					if ($record) {
						if ($display == 'form') echo 'value="';
						echo htmlspecialchars($record[$field['field_name']]);
						if ($display == 'form') echo '"';
					}
					if ($display == 'form') echo '>';
				}
				if (isset($field['unit'])) {
					if ($record) { 
						if ($record[$field['field_name']]) // display unit if record not null
							echo ' '.$field['unit']; 
					} else {
						echo ' '.$field['unit']; 
					}
				}
			} elseif ($field['type'] == 'thumbnail') {
				if ($record) {
					echo '<img src="'.$level.'/'.$record[$field['field_name']].'" alt="'.$record[$field['field_name']].'"><br>';
					echo '<input type="hidden" name="'.$field['field_name'].'" size="64" value="'.$record[$field['field_name']].'">';
				}
				else echo '<input type="text" name="'.$field['field_name'].'" size="64">';
			} elseif ($field['type'] == 'date') {
				if ($display == 'form') echo '<input type="text" name="'.$field['field_name'].'" size="12" ';
				if ($display == 'form' && isset($field['required']) && $field['required']) echo ' class="required"';
				if ($record) {
					if ($display == 'form') echo 'value="';
					echo datum_de($record[$field['field_name']]);
					if ($display == 'form') echo '"';
				} 
				if ($display == 'form') echo '>';
/*			} elseif ($field['type'] == 'currency') {
				if ($display == 'form') echo '<input type="text" name="'.$field['field_name'].'" size="32" ';
				if ($display == 'form' && isset($field['required']) && $field['required']) echo ' class="required"';
				if ($record) {
					if ($display == 'form') echo 'value="';
					echo waehrung($record[$field['field_name']]);
					if ($display == 'form') echo '"';
				} 
				if ($display == 'form') echo '>';
				echo ' &euro;';
*/
			} elseif ($field['type'] == 'memo') {
				if ($display == 'form') echo '<textarea rows="8" cols="60" name="'.$field['field_name'];
				if ($display == 'form' && isset($field['required']) && $field['required']) echo ' class="required"';
				if ($display == 'form') echo '">';
				if ($record) echo htmlspecialchars(stripslashes($record[$field['field_name']]));
				if ($display == 'form') echo '</textarea>';
			//} elseif ($field['type'] == 'set') {
			//	echo mysql_field_flags($field['field_name']);
			} elseif ($field['type'] == 'select') {
				//if ($action) echo $record[$field['field_name']];
				//else {
				if (isset($field['sql_without_id'])) $field['sql'] .= $record_id;
				if (isset($field['sql'])) {
					// ggfs. WHERE einfuegen
					if (isset($field['sql_where']) && $values) {
						$my_where = '';
						foreach ($field['sql_where'] as $sql_where) {
							if (!$my_where) $my_where = ' WHERE ';
							else $my_where .= ' AND ';
							if (isset($sql_where[2])) {
								foreach (array_keys($values) as $value_key) {
									if ($value_key == $sql_where[1]) $sql_where[2].= $values[$value_key];
								}
								$result = mysql_query($sql_where[2]);
								if ($result) {
									if (mysql_num_rows($result) == 1)
										$index = mysql_result($result,0,0);
									else echo $sql_where[2];
								} else {
									echo mysql_error();
									echo '<br>';
									echo $sql_where[2];
								}
							}
							$my_where .= $sql_where[0]." = '".$index."'"; 	
						}
						if (strstr($field['sql'], 'ORDER BY'))
							$field['sql'] = str_replace('ORDER BY', ($my_where.' ORDER BY'), $field['sql']);
						else
							$field['sql'] .= ' '.$my_where;
					}
					$result = mysql_query($field['sql']);
					if (!$result) echo '<br>'.$field['sql'].'<br>';
					if (mysql_num_rows($result) > 0) {
						if ($display == 'form') {
							echo '<select name="'.$field['field_name'].'">'."\n";
							echo '<option value=""';
							if ($record) if (!$record[$field['field_name']]) echo ' selected';
							echo '>'.$text['none_selected'].'</option>';
						}
						while ($line = mysql_fetch_array($result)) {
							if ($display == 'form') {
								echo '<option value="'.$line[0].'"';
								if ($record) if ($line[0] == $record[$field['field_name']]) echo ' selected';
								echo '>';
								$i = 1;
								while (isset($line[$i])) {
									if ($i > 1) echo ' | ';
									echo $line[$i];
									$i++;
								}
								echo '</option>';
							} else {
								if ($line[0] == $record[$field['field_name']]) {
								// same as above
									$i = 1;
									while (isset($line[$i])) {
										if ($i > 1) echo ' | ';
										echo $line[$i];
										$i++;
									}
								}
							}
						}
						if ($display == 'form') echo '</select>'."\n";
					} else {
						echo '<input type="hidden" value="" name="'.$field['field_name'].'">';
						echo $text['no_selection_possible'];
					}
				} elseif (isset($field['enum'])) {
					if ($display == 'form') {
						if (count($field['enum']) <= 2) {
							echo '<span class="hidden"><input type="radio" name="'.$field['field_name'].'" value=""';
							if ($record) if (!$record[$field['field_name']]) echo ' checked';
							echo '>'.$text['no_selection'].'</span>';
						} else {
							echo '<select name="'.$field['field_name'].'">'."\n";
							echo '<option value=""';
							if ($record) if (!$record[$field['field_name']]) echo ' selected';
							echo '>'.$text['none_selected'].'</option>';
						} 
					}
					foreach ($field['enum'] as $enum) {
						if ($display == 'form') {
							if (count($field['enum']) <= 2) {
								echo ' <input type="radio" name="'.$field['field_name'].'" value="'.$enum.'"';
								if ($record) {
									if ($enum == $record[$field['field_name']]) echo ' checked';
								} else {
									if (isset($field['enum_default']))
										if ($enum == $field['enum_default']) echo ' checked';
								} 
								echo '> '.$enum;
							} else {
								echo '<option value="'.$enum.'"';
								if ($record) if ($enum == $record[$field['field_name']]) echo ' selected';
								echo '>';
								echo $enum;
								echo '</option>';
							}
						} else {
							if ($enum == $record[$field['field_name']]) echo $enum;
						}
					}
					if ($display == 'form' && count($field['enum']) > 2) echo '</select>'."\n";
				} else {
					echo $text['no_source_defined'].'. '.$text['no_selection_possible'];
				}
			} elseif ($field['type'] == 'image') {
				$img = false;
				if (isset($field['path'])) {
					echo $img = show_image($field['path'], $record);
				}
				if (!$img) echo '('.$text['image_not_display'].')';
			} elseif ($field['type'] == 'calculated') {
				if (!$mode) {
					// identischer Code mit weiter unten, nur statt $line $record!!
					if ($field['calculation'] == 'hours') {
						$diff = 0;
						foreach ($field['calculation_fields'] as $calc_field) {
							if (!$diff) $diff = strtotime($record[$calc_field]);
							else $diff -= strtotime($record[$calc_field]);
						}
						echo gmdate('H:i', $diff);
					} elseif ($field['calculation'] == 'sum') {
						$sum = 0;
						foreach ($field['calculation_fields'] as $calc_field) {
							$sum += $record[$calc_field];
						}
						echo $sum;
						if (isset($field['unit'])) echo ' '.$field['unit'];
					}
				} else echo '('.$text['calculated_field'].')';
			}
			echo '</td></tr>'."\n";
		}
	}
	if ($mode) {
		echo '<tr><th>&nbsp;</th> <td><input type="submit" value="';
		if ($mode == 'edit') echo $text['update'].' ';
		elseif ($mode == 'delete') echo $text['delete_from'].' ';
		else echo $text['add_to'].' ';
		echo $text['database'].'"></td></tr>'."\n";
	} else {
		if ($list) {
			echo '<tr><th>&nbsp;</th> <td class="reedit">';
			echo '<a href="'.$_SERVER['PHP_SELF'].'?mode=edit&amp;id='.$record_id.'&'.$extras.'">'.$text['edit'].'</a>';
			if ($delete) echo ' | <a href="'.$_SERVER['PHP_SELF'].'?mode=delete&amp;id='.$record_id.'&'.$extras.'">'.$text['delete'].'</a>';
			echo '</td></tr>'."\n";
		}
	}
	echo '</table>'."\n";
	if ($mode == 'delete') echo '<input type="hidden" name="'.$query[1]['field_name'].'" value="'.$record_id.'">';
	if ($mode) echo '<input type="hidden" name="action" value="'.$submit.'">';
	if ($mode && $referer) echo '<input type="hidden" value="'.$referer.'" name="referer">';
	if ($mode) echo '</form>';
	echo '</div>'."\n";
}

if ($extras) $addextras = '&'.$extras;
if ($mode != 'add' && $add) echo '<p><a href="'.$_SERVER['PHP_SELF'].'?mode=add'.$addextras.'">'.$text['add_new_record'].'</a></p>';
if ($referer) echo '<p><a href="'.$referer.'">'.$text['back-to-overview'].'</a></p>';


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
	echo '<table class="data">';
	echo '<thead>'."\n";
	echo '<tr>';
	foreach ($table_query as $field) {
		echo '<th';
		echo check_if_class($field, $values);
		echo '>';
		if ($field['type'] != 'calculated') {
			echo '<a href="';
			if (isset($field['display_field'])) $order_val = $field['display_field'];
			else $order_val = $field['field_name'];
			$uri = addvar($_SERVER['REQUEST_URI'], 'order', $order_val);
			if ($uri == $_SERVER['REQUEST_URI']) $uri.= '&dir=desc';
			echo $uri;
			echo '">';
		}
		echo $field['title'];
		if ($field['type'] != 'calculated')
			echo '</a>';
		echo '</th>';
	}
	echo ' <th>'.$text['action'].'</th>';
	echo '</tr>';
	echo '</thead>'."\n";
	echo '<tbody>'."\n";

//
// Table body
//	
	if ($limit) $sql.= ' LIMIT '.($limit-20).', 20';
	$result = mysql_query($sql);
	if (!$result) {
		echo '<p>'.$text['error-sql-incorrect'].':<br>';
		echo $sql.'</p>';
		echo '<p>'.mysql_error().'</p>';
	} else {
		if (mysql_num_rows($result) > 0) {
			$z = 0;
			while ($line = mysql_fetch_array($result)) {
				echo '<tr class="';
				echo ($z & 1 ? 'uneven':'even');
				echo '">'; //onclick="Highlight();"
				$id = '';
				foreach ($table_query as $field) {
					echo '<td';
					echo check_if_class($field, $values);
					echo '>';
					if ($field['type'] == 'calculated') {
						if ($field['calculation'] == 'hours') {
							$diff = 0;
							foreach ($field['calculation_fields'] as $calc_field) {
								if (!$diff) $diff = strtotime($line[$calc_field]);
								else $diff -= strtotime($line[$calc_field]);
							}
							echo gmdate('H:i', $diff);
							if (isset($field['sum']) && $field['sum'] == true) {
								if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
								$sum[$field['title']] += $diff;
							}
						} elseif ($field['calculation'] == 'sum') {
							$my_sum = 0;
							foreach ($field['calculation_fields'] as $calc_field) {
								$my_sum += $line[$calc_field];
							}
							echo $my_sum;
							if (isset($field['sum']) && $field['sum'] == true) {
								if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
								$sum[$field['title']] .= $my_sum;
							}
						}
					} elseif ($field['type'] == 'image') {
						if (isset($field['path'])) {
							echo $img = show_image($field['path'], $line);
						}
					} elseif ($field['type'] == 'thumbnail' && $line[$field['field_name']]) {
						echo '<img src="'.$level.'/'.$line[$field['field_name']].'" alt="'.$line[$field['field_name']].'">';
					} else {
						if ($field['type'] == 'url') echo '<a href="'.$line[$field['field_name']].'">';
						if (isset($field['link'])) echo '<a href="'.$field['link'].$line[$field['field_name']].'">';
						if (isset($field['display_field'])) echo htmlspecialchars($line[$field['display_field']]);
						else {
							if (isset($field['factor']) && $line[$field['field_name']]) $line[$field['field_name']] /=2;
							if ($field['type'] == 'date') echo datum_de($line[$field['field_name']]);
							elseif (isset($field['number_type']) && $field['number_type'] == 'currency') echo waehrung($line[$field['field_name']], '');
							elseif (isset($field['number_type']) && $field['number_type'] == 'latitude' && $line[$field['field_name']]) {
								$deg = dec2dms($line[$field['field_name']], '');
								echo $deg['latitude'];
							} elseif (isset($field['number_type']) && $field['number_type'] == 'longitude' &&  $line[$field['field_name']]) {
								$deg = dec2dms('', $line[$field['field_name']]);
								echo $deg['longitude'];
							}
							else echo nl2br(htmlspecialchars($line[$field['field_name']]));
						}
						if ($field['type'] == 'url') echo '</a>';
						if (isset($field['link'])) echo '</a>';
						if (isset($field['sum']) && $field['sum'] == true) {
							if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
							$sum[$field['title']] += $line[$field['field_name']];
						}
					}
					if (isset($field['unit'])) 
						/* && $line[$field['field_name']]) does not work because of calculated fields*/ 
						echo '&nbsp;'.$field['unit'];	
					echo '</td>';
					if ($field['type'] == 'id') $id = $line[$field['field_name']];
				}
				echo '<td class="editbutton"><a href="'.$_SERVER['PHP_SELF'].'?mode=edit&amp;id='.$id;
				echo '&'.$extras;
				echo '">'.$text['edit'].'</a>';
				if ($delete) {
					echo ' | <a href="'.$_SERVER['PHP_SELF'].'?mode=delete&amp;id='.$id;
					echo '&'.$extras;
					echo '">'.$text['delete'].'</a>';
				}
				if (isset($more_actions)) {
					foreach ($more_actions as $new_action) {
						echo ' | <a href="'.$new_action;
						if (isset($more_actions_url)) {
							if (is_array($more_actions_url)) {
								foreach (array_keys($more_actions_url) as $part_key) {
									if (substr($part_key, 0, 5) == 'field') {
										echo $line[$more_actions_url[$part_key]];
										//echo $line[$more_actions[$part_key]];
									} else {
										echo $more_actions_url[$part_key];
									}
								}
							} else {
								echo $more_actions_url;
							}
						}	
						else echo '.php?id=';
						if (!isset($more_actions_url) OR !is_array($more_actions_url)) echo $id;
						echo '">'.ucfirst($new_action).'</a>';
					}
				}
				echo '</td>';
				echo '</tr>'."\n";
				$z++;
			}
		}
	}	

// Table footer

?>
</tbody>
<?php 
if ($tfoot) {
	echo '<tfoot>'."\n";
	echo '<tr>';
	foreach ($table_query as $field) {
		if ($field['type'] == 'id') echo '<td>'.$z.'</td>';
		elseif (isset($field['sum']) AND $field['sum'] == true) {
			echo '<td>';
			if (isset($field['calculation']) AND $field['calculation'] == 'hours')
				$sum[$field['title']] = hours($sum[$field['title']]);
			echo $sum[$field['title']];
			if (isset($field['unit'])) echo '&nbsp;'.$field['unit'];	
			echo '</td>';
		}
		else echo '<td>&nbsp;</td>';
	}
	echo '<td>&nbsp;</td>';
	echo '</tr>'."\n";
	echo '</tfoot>'."\n";
}

?>
</table>

<?php 
	if ($mode != 'add' & $add) {
		echo '<p><a href="'.$_SERVER['PHP_SELF'].'?mode=add'.$addextras.'">'.$text['add_new_record'].'</a></p>';
	}
	if ($limit) {
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
			echo '<ul>';
			if ($prev) echo '<li><a href="'.$prev.'">'.$text['prev_20_records'].'</li>';
			echo '<li><a href="'.$all.'">'.$text['all_records'].'</li>';
			if ($next) echo '<li><a href="'.$next.'">'.$text['next_20_records'].'</li>';
			echo '</ul>';
		}
	}
}

function ueberschrift($string) {
	$string = str_replace('_', ' ', $string);
	$string = ucfirst($string);
	return $string;
}

?>