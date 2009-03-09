<?php 

/*

zzform

functions for checking for relational integrity of mysql-databases
uses table _relations, similar to the PHPMyAdmin table of relations
name of table may be set with $zz_conf['relations_table']

detail records if shown with current record will be deleted if they don't 
have detail records themselves. In the latter case, the resulting error message
is not 100% correct, but these occasions should be very rare anyways

(c) Gustaf Mossakowski <gustaf@koenige.org>, 2005-2006

*/

function check_integrity($master_db, $master_table, $master_field, $master_value, 
	$relation_table, $detailrecords) {
	global $text;
	// return false - deletion is possible
	// return true 	- do not delete
	if (strstr($master_table, '.')) { // don't know if this is important, but if 
			//someone sets master_table to dbname.tablename, this will work
		$master = explode('.', $master_table);
		$master_db = $master[0];
		$master_table = $master[1];
		unset($master);
	}
	$sql = 'SELECT * FROM '.$relation_table;
	$result = mysql_query($sql);
	if ($result) {
		if (mysql_num_rows($result))
			while ($line = mysql_fetch_array($result))
				$relations[$line['master_db']][$line['master_table']][$line['master_field']][] = $line;
		else {
			$response['text'] = sprintf(zz_text('No records in relation table')
				, $relation_table);
			return $response;
		}
	} else {
		echo mysql_error();
		$response['text'] = sprintf(zz_text('No relation table'), $relation_table);
		return $response;
	}
	if (!$check = check_if_detail($relations, $master_db, $master_table, 
		$master_field, $master_value, $detailrecords)) 
		return false;
	else {
		$response['text'] = zz_text('Detail records exist in the following tables:');
		$response['fields'] = explode(',', $check);
		return $response;
	}
}

function check_if_detail($relations, $master_db, $master_table, $master_field, 
	$master_value, $detailrecords) {
	global $zz_conf;
	global $zz_error;
	if (isset($relations[$master_db][$master_table])) {
	//	this table is master in at least one relation
	//	check whether there are detail records at all
		$detail_records_in = false;
		foreach (array_keys($relations[$master_db][$master_table][$master_field]) as $key) {
			$field = $relations[$master_db][$master_table][$master_field][$key];
			$sql = 'SELECT COUNT('.$field['detail_field'].') AS Rows';
			$sql.= ' FROM '.$field['detail_db'].'.'.$field['detail_table'];
			$sql.= ' WHERE '.$field['detail_field'].' = '.$master_value;
			if ($zz_conf['debug']) echo $sql.'<br>';
			$result = mysql_query($sql);
			if ($result) if (mysql_num_rows($result))
				if ($all_detailrecords = mysql_result($result, 0, 0)) { 
					// there is a detail record
					if ($zz_conf['debug']) echo $all_detailrecords.'<br>';
					$my_detailrecords = 0;
					$detail = $field['detail_db'].'.'.$field['detail_table'];
					if (!empty($detailrecords[$detail])) {
						$my_detailrecords = false;
						foreach ($detailrecords[$detail]['sql'] as $sql) {
							// add master record to selection, neccessary for multiple links on one subtable
							$sql = zz_edit_sql($sql, 'WHERE', $master_db.'.'
								.$master_table.'.'
								.$master_field.' = '.$master_value);
							// add detail record to selection, neccessary for some cases where there are multiple ID fields of same type (parent/children)
							$sql = zz_edit_sql($sql, 'WHERE', $detail.'.'
								.$field['detail_field'].' = '.$master_value);
							$myres = mysql_query($sql);
							if (mysql_error())
								$zz_error[] = array('msg' => 'Error in check_if_detail():<br><br> '.mysql_error(), 'query' => $sql);
							if ($myres)
								$my_detailrecords += mysql_num_rows($myres);
							if ($zz_conf['debug']) echo $sql.'<br>';
						}
						if ($zz_conf['debug']) echo $my_detailrecords.'<br><br>';
					}
					if ($all_detailrecords == $my_detailrecords) {
					//	detail records match total number of records in table
					//	this should always be the case if $detailrecords... is not emtpy
					//	there may be rare cases when it is not so, therefore we compare the count and the sql clause
						while ($line = mysql_fetch_assoc($myres)) { // check if the detail record has a detail record
							$detail_line = check_if_detail($relations, $field['detail_db'], $field['detail_table'], $field['detail_id_field'], $line[$field['detail_id_field']], $detailrecords);
							if ($detail_line) {
								$detail_records_in[] = ucfirst($field['detail_table']); // this is of course not correct. but it is too complicated right now to show the exact relation to this record
								break;
							}
						}
						// if everything is ok, do nothing, so detail_records_in will still be false
					} else { //	there is a detail record
						if ($zz_conf['debug']) echo 'All records '.$all_detailrecords.', in this record:'.$my_detailrecords.'<br><br>';
						$detail_records_in[] = ucfirst($field['detail_table']);
					}
				}
		}
		if (!$detail_records_in) return false;
		else return implode(',', $detail_records_in);
	} else {
	//	no relations which have this table as master
	//	do not care about master_field because it has to be PRIMARY key anyways
	//	so only one field name possible
		return false;
	}
}

?>
