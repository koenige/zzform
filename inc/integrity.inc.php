<?php 

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2004-2010
// Standard module for integrity

/*

zzform

functions for checking for relational integrity of mysql-databases
uses table _relations, similar to the PHPMyAdmin table of relations
name of table may be set with $zz_conf['relations_table']

detail records if shown with current record will be deleted if they don't 
have detail records themselves. In the latter case, the resulting error message
is not 100% correct, but these occasions should be very rare anyways

*/

/**
 * Checks relational integrity upon a deletion request and says if its ok.
 *
 * @param string $master_db		Name of master database
 * @param string $master_table	Name of master table
 * @param string $master_field	Name of master field
 * @param string $master_value	Value of field
 * @param string $relation_table	Name of relation table ($zz_conf['relation_table'])
 * @param array $detailrecords	...
 * @return mixed bool false: deletion of record possible, integrity will remain
 *		array: 'text' (error message), 'fields' (optional, names of tables
 *		which have a relation to the current record)
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_check_integrity($master_db, $master_table, $master_field, $master_value, 
	$relation_table, $detailrecords) {
	// return false - deletion is possible
	// return true 	- do not delete
	$response = array();
	$sql = 'SELECT * FROM '.$relation_table;
	$relations = zz_db_fetch($sql, array('master_db', 'master_table', 'master_field', 'rel_id'));
	if (!$relations) {
		$response['text'] = sprintf(zz_text('No records in relation table'), $relation_table);
		return $response;
	}
	if (!$check = zz_check_if_detail($relations, $master_db, $master_table, 
		$master_field, $master_value, $detailrecords)) 
		return false;
	else {
		$response['text'] = zz_text('Detail records exist in the following tables:');
		$response['fields'] = explode(',', $check);
		return $response;
	}
}

/**
 * Checks relational integrity upon a deletion request and says if its ok.
 *
 * @param array $relations		All entries of relations table
 * @param string $master_db		Name of master database
 * @param string $master_table	Name of master table
 * @param string $master_field	Name of master field
 * @param string $master_value	Value of field
 * @param array $detailrecords	...
 * @global array $zz_conf
 * @global array $zz_error
 * @return mixed bool false: no detail records; string: Table names of detail records
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_check_if_detail($relations, $master_db, $master_table, $master_field, 
	$master_value, $detailrecords) {
	global $zz_conf;
	global $zz_error;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	if (!isset($relations[$master_db][$master_table])) {
	//	no relations which have this table as master
	//	do not care about master_field because it has to be PRIMARY key anyways
	//	so only one field name possible
		return zz_return(false);
	}

	//	this table is master in at least one relation
	//	check whether there are detail records at all
	$detail_records_in = false;
	foreach ($relations[$master_db][$master_table][$master_field] as $key => $field) {
		$sql = 'SELECT COUNT('.$field['detail_field'].') AS Rows
			FROM '.$field['detail_db'].'.'.$field['detail_table'].'
			WHERE '.$field['detail_field'].' = '.$master_value;
		$all_detailrecords = zz_db_fetch($sql, '', 'single value');
		if (!$all_detailrecords) continue;

		// there is a detail record
		if ($zz_conf['modules']['debug']) zz_debug('There is a detailrecord');
		$my_detailrecords = 0;
		$detail = $field['detail_db'].'.'.$field['detail_table'];
		$all_records = array();
		if (!empty($detailrecords[$detail])) {
			$my_detailrecords = false;
			foreach ($detailrecords[$detail]['sql'] as $sql) {
				// add master record to selection, neccessary for 
				// multiple links on one subtable
				// but do NOT do this if master and detail table 
				// are the same (main_something_id or mother_something_id)
				if ($detail != $master_db.'.'.$master_table) {
					$sql = zz_edit_sql($sql, 'WHERE', $master_db.'.'
						.$master_table.'.'.$master_field.' = '.$master_value);
				}
				// add detail record to selection, neccessary for some 
				// cases where there are multiple ID fields of same type (parent/children)
				$sql = zz_edit_sql($sql, 'WHERE', $detail.'.'
					.$field['detail_field'].' = '.$master_value);
				$records = zz_db_fetch($sql, $field['detail_id_field']);
				if ($zz_error['error']) return zz_return(zz_error());

				if ($records) {
					$my_detailrecords += count($records);
					$all_records += $records;
				}
			}
			if ($zz_conf['modules']['debug']) 
				zz_debug("my_detailrecords: ".$my_detailrecords, $sql);
		}
		if ($all_detailrecords == $my_detailrecords) {
		// detail records match total number of records in table
		// this should always be the case if $detailrecords... is not 
		// emtpy. there may be rare cases when it is not so,  
		// therefore we compare the count and the sql clause
			foreach ($all_records as $line) { 
				// check if the detail record has a detail record
				$detail_line = zz_check_if_detail($relations, $field['detail_db'], $field['detail_table'], 
					$field['detail_id_field'], $line[$field['detail_id_field']], $detailrecords);
				if ($detail_line) {
					$detail_records_in[$field['detail_table']] = $field['detail_table']; 
					// this is of course not correct. but it is too 
					// complicated right now to show the exact 
					// relation to this record
					break;
				} elseif ($zz_error['error']) return zz_return(false);
			}
			// if everything is ok, do nothing, so detail_records_in will still be false
		} else { //	there is a detail record
			if ($zz_conf['modules']['debug']) 
				zz_debug('All records '.$all_detailrecords.', in this record:'
					.$my_detailrecords, $sql);
			$detail_records_in[$field['detail_table']] = $field['detail_table'];
		}
	}
	if (!$detail_records_in) return zz_return(false);
	else return zz_return(implode(',', $detail_records_in));
}


?>