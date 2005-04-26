<?php 

if (!isset($verbindung)) include ($level.'/'.$inc.'/db.inc.php');

/*

START

*/


function check_integrity($master_db, $master_table, $master_field, $master_value) {
	// return false - do not delete
	// return true 	- deletion is possible
	$sql = 'SELECT * FROM _relations';
	$result = mysql_query($sql);
	if ($result) {
		if (mysql_num_rows($result)) {
			while ($line = mysql_fetch_array($result)) {
				$relations[$line['master_db']][$line['master_table']][$line['master_field']][] = $line;
			}
		} else {
			$response['text'] = 'No records in relation table';
			return $response;
		}
	} else {
		$response['text'] = 'No relation table';
		return $response;
	}
	//echo 'rel_check';
	//echo '<pre>test '.print_r($relations).' '.$master_db.' '.$master_table.' '.$master_field.' '.$master_value.' test</pre>';
	if (!$check = check_if_detail($relations, $master_db, $master_table, $master_field, $master_value)) 
		return false;
	else {
		$response['text'] = 'Detail records exist in the following tables:';
		$response['fields'] = explode(',', $check);
		return $response;
	}
}

function check_if_detail($relations, $master_db, $master_table, $master_field, $master_value) {
	if (isset($relations[$master_db][$master_table])) {
	//	this table is master in at least one relation
	//	check whether there are detail records at all
		$detail_records = false;
		foreach (array_keys($relations[$master_db][$master_table][$master_field]) as $key) {
			$field = $relations[$master_db][$master_table][$master_field][$key];
			$sql = 'SELECT COUNT('.$field['detail_field'].') AS Rows';
			$sql.= ' FROM '.$field['detail_table'];
			$sql.= ' WHERE '.$field['detail_field'].' = '.$master_value;
			$result = mysql_query($sql);
			if ($result) if (mysql_num_rows($result))
				if(mysql_result($result,0,0) == 0) {
				// there is no detail record
					$detail_records .= '';
				} else {
				// there is a detail record
					if ($detail_records) $detail_records.= ', ';
					$detail_records .= ucfirst($field['detail_table']);
				}
		}
		if (!$detail_records) return false;
		else return $detail_records;
	} else {
	//	no relations which have this table as master
	//	do not care about master_field because it has to be PRIMARY key anyways
	//	so only one field name possible
		return false;
	}
}

?>
