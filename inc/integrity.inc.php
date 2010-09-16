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
 * gets all entries from the table where the database relations are stored
 *
 * @param string $relation_table	Name of relation table ($zz_conf['relation_table'])
 * @return array $relations
 */
function zz_integrity_relations($relation_table) {
	$sql = 'SELECT * FROM '.$relation_table;
	$relations = zz_db_fetch($sql, array('master_db', 'master_table', 'master_field', 'rel_id'));
	return $relations;
}

/**
 * Checks relational integrity upon a deletion request and says if its ok.
 *
 * @param array $deletable_ids
 * @param array $relations
 * @return mixed bool false: deletion of record possible, integrity will remain
 *		array: 'text' (error message), 'fields' (optional, names of tables
 *		which have a relation to the current record)
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_integrity_check($deletable_ids, $relations) {
	if (!$relations) {
		$response['text'] = zz_text('No records in relation table');
		return $response;
	}

	$response = array();
	$response['fields'] = array();
	foreach ($deletable_ids as $master_db => $tables) {
		foreach ($tables as $master_table => $fields) {
			if (!isset($relations[$master_db][$master_table])) {
			//	no relations which have this table as master
			//	do not care about master_field because it has to be PRIMARY key anyways
			//	so only one field name possible
				continue;
			}
			$master_field = key($fields);
			$ids = array_shift($fields);
			foreach ($relations[$master_db][$master_table][$master_field] as $key => $field) {
				$sql = 'SELECT `'.$field['detail_id_field'].'`
					FROM `'.$field['detail_db'].'`.`'.$field['detail_table'].'`
					WHERE `'.$field['detail_field'].'` IN ('.implode(',', $ids).')';
				$detail_ids = zz_db_fetch($sql, $field['detail_id_field'], 'single value');
				if (!$detail_ids) continue;
				
				if (!empty($deletable_ids[$field['detail_db']][$field['detail_table']][$field['detail_id_field']])) {
					$deletable_detail_ids = $deletable_ids[$field['detail_db']][$field['detail_table']][$field['detail_id_field']];
					$remaining_ids = array_diff($detail_ids, $deletable_detail_ids);
				} else {
					$remaining_ids = $detail_ids;
				}
				if ($remaining_ids) {
					// there are still IDs which cannot be deleted
					// check which record they belong to
					$response['fields'][] = $field['detail_table'];
				}
			}
		}
	}
	if ($response['fields']) {
		// we still have detail records
		$response['text'] = zz_text('Detail records exist in the following tables:');
		return $response;
	} else {
		// everything is okay
		return false;
	}
}

/**
 * checks tables if there are records in other tables which will be deleted
 * with their main records in this table together (relation = delete)
 *
 * @param array $zz_tab (all table definitions and records)
 * @param array $relations		All entries of relations table
 * @return array $details
 *		[db][table][id_field] = array with IDs that can be deleted safely
 * @see zz_get_detail_record_ids()
 */
function zz_integrity_dependent_record_ids($zz_tab, $relations) {
	if (!$relations) return array();

	$details = array();
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if (!$zz_tab[$tab][$rec]['id']['value']) continue;
			if ($zz_tab[$tab][$rec]['action'] != 'delete') continue;
			if (empty($relations[$zz_tab[$tab]['db_name']])) continue;
			if (empty($relations[$zz_tab[$tab]['db_name']][$zz_tab[$tab]['table']])) continue;
			if (empty($relations[$zz_tab[$tab]['db_name']][$zz_tab[$tab]['table']][$zz_tab[$tab][$rec]['id']['field_name']])) continue;

			$my_relations = $relations[$zz_tab[$tab]['db_name']][$zz_tab[$tab]['table']][$zz_tab[$tab][$rec]['id']['field_name']];
			foreach ($my_relations as $rel) {
				// we care just about 'delete'-relations
				if ($rel['delete'] != 'delete') continue;
				$sql = 'SELECT `'.$rel['detail_id_field'].'`
					FROM `'.$rel['detail_db'].'`.`'.$rel['detail_table'].'`
					WHERE `'.$rel['detail_field'].'` = '.$zz_tab[$tab][$rec]['id']['value'];
				$records = zz_db_fetch($sql, $rel['detail_id_field'], 'single value');
				if (!$records) continue;
				// check if detail records have other detail records
				// if no entry in relations table exists, make no changes
				if (empty($details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']]))
					$details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']] = array();
				$details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']] 
					= array_merge($records, $details[$rel['detail_db']][$rel['detail_table']][$rel['detail_id_field']]);
			}
		}
	}
	if (!$details) return array();
	return $details;
}

/**
 * reads all existing ID values from main record and subrecords which
 * are going to be deleted 
 * 
 * (if main record will be deleted, subrecords will all
 * be deleted; it is possible that only some subrecords will be deleted while
 * main record gets updated)
 *
 * @param array $zz_tab (all table definitions and records)
 * @return array $details
 *		[db][table][id_field] = array with IDs that can be deleted safely
 * @see zz_get_depending_records()
 */
function zz_integrity_record_ids($zz_tab) {
	$records = array();
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_numeric($rec)) continue;
			if (!$zz_tab[$tab][$rec]['id']['value']) continue;
			if ($zz_tab[$tab][$rec]['action'] != 'delete') continue;
			$records[$zz_tab[$tab]['db_name']][$zz_tab[$tab]['table']][$zz_tab[$tab][$rec]['id']['field_name']][]
				= $zz_tab[$tab][$rec]['id']['value'];
		}
	}
	return $records;
}

?>