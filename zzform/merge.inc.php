<?php 

/**
 * zzform
 * Merge functions
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function zz_merge_records($zz) {
	global $zz_conf;
	
	if (empty($_POST['zz_action'])) return false;
	if ($_POST['zz_action'] !== 'multiple') return false;
	if (empty($_POST['zz_record_id'])) return false;
	if (!is_array($_POST['zz_record_id'])) return false;

	$ids = array();
	foreach ($_POST['zz_record_id'] as $id) {
		$ids[] = intval($id);
	}
	sort($ids);
	$new_id = array_shift($ids);
	$old_ids = $ids;
	
	$field_name = '';
	foreach ($zz['fields'] as $field) {
		if ($field['type'] !== 'id') continue;
		$field_name = $field['field_name'];
		break;
	}

	$sql = 'SELECT rel_id, detail_db, detail_table, detail_field, `delete`, detail_id_field
		FROM _relations
		WHERE master_db = "%s"
		AND master_table = "%s"
		AND master_field = "%s"';
	$sql = sprintf($sql, $zz_conf['db_name'], $zz['table'], $field_name);
	$dependent_records = zz_db_fetch($sql, 'rel_id');
	
	$dependent_sql = 'SELECT %s, %s FROM %s.%s WHERE %s IN (%s)';
	$record_sql = 'UPDATE %s SET %s = %%d WHERE %s = %%d';
	$msg = array();
	foreach ($dependent_records as $record) {
		$sql = sprintf($dependent_sql,
			$record['detail_id_field'], $record['detail_field'],
			$record['detail_db'], $record['detail_table'], $record['detail_field'],
			implode(',', $old_ids)
		);
		$records = zz_db_fetch($sql, $record['detail_id_field']);
		if (!$records) continue;

		$this_record_sql = sprintf($record_sql,
			($record['detail_db'] !== $zz_conf['db_name'] 
				? $record['detail_db'].'.'.$record['detail_table'] 
				: $record['detail_table']),
			$record['detail_field'], $record['detail_id_field']
		);
		foreach ($records as $entry) {
			$record_id = $entry[$record['detail_id_field']];
			$sql = sprintf($this_record_sql, $new_id, $record_id);
			$result = zz_db_change($sql, $record_id);
			if ($result['action']) {
				$msg[] = sprintf(zz_text('Merging entry in table %s merged (ID: %d)'),
					'<code>'.$record['detail_table'].'</code>', $record_id
				);
			} else {
				$msg[] = sprintf(zz_text('Merging entry in table %s failed with an error (ID: %d): %s'),
					'<code>'.$record['detail_table'].'</code>', $record_id, $result['error']['db_msg']
				);
			}
			// @todo catch errors, e. g. if UNIQUE key hinders update
		}
	}	

	// @todo show main records
	// @todo remove main records
	return $msg;
}