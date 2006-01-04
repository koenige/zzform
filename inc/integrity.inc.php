<?php 

/*

zzform

functions for checking for relational integrity of mysql-databases
uses table _relations, similar to the PHPMyAdmin table of relations
name of table may be set with $zz_conf['relations_table']

(c) Gustaf Mossakowski <gustaf@koenige.org>, 2005-2006

*/

function check_integrity($master_db, $master_table, $master_field, $master_value, $relation_table, $detailrecords = false) {
	// return false - do not delete
	// return true 	- deletion is possible
	$sql = 'SELECT * FROM '.$relation_table;
	$result = mysql_query($sql);
	if ($result) {
		if (mysql_num_rows($result))
			while ($line = mysql_fetch_array($result))
				$relations[$line['master_db']][$line['master_table']][$line['master_field']][] = $line;
		else {
			$response['text'] = 'No records in relation table';
			return $response;
		}
	} else {
		echo mysql_error();
		$response['text'] = 'No relation table';
		return $response;
	}
	//echo 'rel_check<br>';
	//echo 'test MD:'.$master_db.' MT:'.$master_table.' MF:'.$master_field.' MV:'.$master_value.' RT:'.$relation_table.' test<br>';
	//echo '<pre>test '.print_r($relations).' '.$master_db.' '.$master_table.' '.$master_field.' '.$master_value.' test</pre>';
	if (!$check = check_if_detail($relations, $master_db, $master_table, $master_field, $master_value, $detailrecords)) 
		return false;
	else {
		$response['text'] = 'Detail records exist in the following tables:';
		$response['fields'] = explode(',', $check);
		return $response;
	}
}

function check_if_detail($relations, $master_db, $master_table, $master_field, $master_value, $detailrecords) {
	if (isset($relations[$master_db][$master_table])) {
	//	this table is master in at least one relation
	//	check whether there are detail records at all
		$detail_records_in = false;
		foreach (array_keys($relations[$master_db][$master_table][$master_field]) as $key) {
			$field = $relations[$master_db][$master_table][$master_field][$key];
			$sql = 'SELECT COUNT('.$field['detail_field'].') AS Rows';
			$sql.= ' FROM '.$field['detail_table'];
			$sql.= ' WHERE '.$field['detail_field'].' = '.$master_value;
			$result = mysql_query($sql);
			if ($result) if (mysql_num_rows($result))
				if(!mysql_result($result,0,0)) {
				// there is no detail record
					$detail_records_in .= '';
				} else {
					if (!empty($detailrecords[$field['detail_db']][$field['detail_table']])
						&& mysql_num_rows($result) == count($detailrecords[$field['detail_db']][$field['detail_table']]))
					//	detail records match total number of records in table
					//	this should always be the case if $detailrecords... is not emtpy
					//	there may be rare cases when it is not so, therefore we compare the count
						$detail_records_in .= '';
					else {
					//	there is a detail record
						if ($detail_records_in) $detail_records.= ',';
						$detail_records_in .= ucfirst($field['detail_table']);
					}
				}
		}
		if (!$detail_records_in) return false;
		else return $detail_records_in;
	} else {
	//	no relations which have this table as master
	//	do not care about master_field because it has to be PRIMARY key anyways
	//	so only one field name possible
		return false;
	}
}

?>
