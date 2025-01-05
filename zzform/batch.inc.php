<?php 

/**
 * zzform
 * generic batch functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * batch delete several records
 *
 * @param array $zz
 * @param array $post
 * @return string
 */
function zzform_batch_delete($zz, $post) {
	if (empty($zz['list']['batch_delete']))
		wrap_quit(403, wrap_text('Sorry, multiple deletion is not permitted.'));
	if (empty($post['zz_action']) OR $post['zz_action'] !== 'multiple')
		wrap_quit(400);

	if (empty($post['zz_record_id']))
		return '';

	$table = wrap_db_prefix_remove($zz['table']);
	
	$data['deleted_ids'] = zzform_delete($table, $post['zz_record_id']);
	$data['deleted'] = count($data['deleted_ids']);
	$data['not_deleted'] = count($post['zz_record_id']) - $data['deleted'];
	$data['not_deleted_ids'] = array_diff($post['zz_record_id'], $data['deleted_ids']);
	wrap_session_start();
	$_SESSION['zzform']['batch'] = $data;
	wrap_redirect_change();
}

/**
 * show result of multiple deletions
 *
 * @return string
 */
function zzform_batch_delete_result() {
	global $zz_conf;

	wrap_session_start();
	$data = $_SESSION['zzform']['batch'];
	unset($_SESSION['zzform']['batch']);
	if ($data['not_deleted_ids'])
		$zz_conf['int']['id']['values'] = $data['not_deleted_ids'];

	if ($data['not_deleted'])
		return wrap_text('%d records were deleted, %d not', ['values' => [$data['deleted'], $data['not_deleted']]]);
	return wrap_text('%d records were deleted', ['values' => [$data['deleted']]]);
}

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

	$sql = 'SELECT `%s` FROM %s WHERE `%s` = %d';
	$sql = sprintf($sql, $field_name, zz_db_table_backticks($table), $id_field_name, $line[$id_field_name]);
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
