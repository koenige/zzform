<?php

/**
 * default module
 * check relational integrity
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/default
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2010, 2013-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * checks all fields that have an entry in the relations_table if they
 * contain invalid values (e. g. values that do not have a corresponding value
 * in the master table
 *
 * @param array $page
 * @return string text output
 * @todo add translations with wrap_text()
 */
function mod_zzform_integritycheck($params) {
	$sql = 'SELECT * FROM /*_TABLE zzform_relations _*/';
	$relations = wrap_db_fetch($sql, 'rel_id');

	$data = [];
	foreach ($relations as $relation) {
		$sql = 'SELECT DISTINCT detail_table.`%s`
				, detail_table.`%s`
			FROM `%s`.`%s` detail_table
			LEFT JOIN `%s`.`%s` master_table
				ON detail_table.`%s` = master_table.`%s`
			WHERE ISNULL(master_table.`%s`)
			AND NOT ISNULL(detail_table.`%s`)
		';
		$sql = sprintf($sql,
			$relation['detail_id_field'], $relation['detail_field'],
			$relation['detail_db'], $relation['detail_table'],
			$relation['master_db'], $relation['master_table'],
			$relation['detail_field'], $relation['master_field'],
			$relation['master_field'], $relation['detail_field']
		);
		$ids = wrap_db_fetch($sql, '_dummy_', 'key/value', false, E_USER_NOTICE);
		$detail_field = $relation['detail_db'].' . '.$relation['detail_table'].' . '.$relation['detail_field'];
		if ($ids) {
			$error_ids = [];
			foreach ($ids as $id => $foreign_id) {
				$error_ids[] = [
					'id' => $id,
					'foreign_id' => $foreign_id
				];
			}
			$data[] = [
				'status' => 'error',
				'field_name' => $detail_field,
				'detail_id_field' => $relation['detail_id_field'],
				'detail_field' => $relation['detail_field'],
				'ids' => $error_ids
			];
		} else {
			$data[] = [
				'status' => 'ok',
				'field_name' => $detail_field
			];
		}
	}
	if (!$data) $data['nothing_to_check'] = true;
	$page['text'] = wrap_template('integritycheck', $data);
	$page['title'] = wrap_text('Relational Integrity');
	$page['breadcrumbs'][]['title'] = wrap_text('Relational Integrity');
	return $page;
}
