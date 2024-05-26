<?php 

/**
 * zzform
 * generic batch functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * update date if current entry contains incomplete date and new data is better
 *
 * @param array $line
 * @param string $table
 * @param string $id_field_name
 * @param string $field_name
 * @return bool
 */
function zzform_update_date($line, $table, $id_field_name, $field_name) {
	if (empty($line[$field_name])) return false;
	if (!array_key_exists($id_field_name, $line)) return false;
	
	$new = zzform_date_quality($line[$field_name]);
	if (!$new) return false;

	$sql = 'SELECT `%s` FROM `%s` WHERE `%s` = %d';
	$sql = sprintf($sql, $field_name, $table, $id_field_name, $line[$id_field_name]);
	$existing = wrap_db_fetch($sql, '', 'single value');
	$old = $existing ? zzform_date_quality($existing) : 0;
	if ($old >= $new) return false;

	$line = [
		$id_field_name => $line[$id_field_name],
		$field_name => $line[$field_name]
	];
	$success = zzform_update($table, $line);
	if (!$success) return false;
	return true;
}

/**
 * check if a date is incomplete
 * returns 0 if there is no date, 3 if date is complete, 1 and 2 for incompleteness
 *
 * @param string $date
 * @return int
 */
function zzform_date_quality($date) {
	wrap_include('validate', 'zzform');
	$date = zz_check_date($date);
	if (!$date) return 0;
	if (str_ends_with($date, '-00-00')) return 1;
	if (str_ends_with($date, '-00')) return 2;
	return 3;
}
