<?php 

/**
 * zzform
 * copy data
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * get structure of table
 *
 * @param string $table name of table
 * @return array
 */
function zz_db_table_structure($table) {
	$def = [];
	$def['table'] = $table;
	$sql = 'SHOW COLUMNS FROM `%s`';
	$sql = sprintf($sql, $def['table']);
	$structure = wrap_db_fetch($sql, '_dummy_', 'numeric');
	foreach ($structure as $field) {
		if ($field['Key'] === 'PRI')
			$def['primary_key'] = $field['Field'];
		elseif (str_ends_with($field['Field'], '_id'))
			$def['foreign_keys'][] = $field['Field'];
	}
	$def['script_name'] = str_replace('_', '-', $def['table']);
	return $def;
}

/**
 * copy one or several records of a dependent table
 *
 * @param string $table name of table
 * @param string $foreign_id_field_name
 * @param int $source_id
 * @param int $destination_id
 * @param string $transfer_field_name to transfer additional data for POST (optional)
 * @return bool
 */
function zz_copy_records($table, $foreign_id_field_name, $source_id, $destination_id, $transfer_field_name = false) {
	$def = zz_db_table_structure($table);

	// existing records
	$sql = 'SELECT * FROM `%s` WHERE `%s` = %d';
	$sql = sprintf($sql, $def['table'], $foreign_id_field_name, $source_id);
	$data = wrap_db_fetch($sql, $def['primary_key']);
	if (!$data) return false;
	
	$dont_copy = wrap_get_setting('zzform_copy_fields_exclude');
	$dont_copy[] = $def['primary_key'];
	$dont_copy[] = $foreign_id_field_name;
	
	$map = [];

	$values = [];
	$values['action'] = 'insert';
	$values['ids'] = $def['foreign_keys'];
	$values['POST'][$foreign_id_field_name] = $destination_id;
	foreach ($data as $line) {
		foreach ($line as $field_name => $value)
			if (!in_array($field_name, $dont_copy))
				$values['POST'][$field_name] = $value;
		if ($transfer_field_name)
			$values['POST'][$transfer_field_name] = $line[$def['primary_key']];
		$ops = zzform_multi($def['script_name'], $values);
		if (!$ops['id'])
			wrap_error(sprintf('Could not copy %s %d', $def['primary_key'], $line[$def['primary_key']]));
		// map old fields to new fields for translations
		$map[$line[$def['primary_key']]] = $ops['id'];
	}
	zz_copy_records_translations($def['table'], array_keys($data), $map);
	return true;	
}

/**
 * copy translations for a table
 *
 * @param string $table name of table
 * @param array $ids IDs of fields to translate
 * @param array $map mapping of 
 * @return bool
 */
function zz_copy_records_translations($table, $ids, $map) {
	global $zz_conf;

	$sql = 'SELECT translationfield_id, field_type
		FROM %s
		WHERE db_name = "%s" AND table_name = "%s"';
	$sql = sprintf($sql
		, wrap_sql_table('default_translationfields')
		, $zz_conf['db_name']
		, $table
	);
	$translationfields = wrap_db_fetch($sql, 'translationfield_id');
	if (!$translationfields) return false;

	foreach ($translationfields as $field) {
		$sql = 'SELECT translation_id, field_id, translation, language_id
			FROM _translations_%s
			WHERE translationfield_id = %d
			AND field_id IN (%s)';
		$sql = sprintf($sql
			, $field['field_type']
			, $field['translationfield_id']
			, implode(',', $ids)
		);
		$translations = wrap_db_fetch($sql, 'translation_id');
		$values = [];
		$values['action'] = 'insert';
		$values['ids'] = ['translationfield_id', 'language_id'];
		$values['POST']['translationfield_id'] = $field['translationfield_id'];
		foreach ($translations as $translation) {
			$values['POST']['field_id'] = $map[$translation['field_id']];
			$values['POST']['translation'] = $translation['translation'];
			$values['POST']['language_id'] = $translation['language_id'];
			$ops = zzform_multi(sprintf('translations-%s', $field['field_type']), $values);
			if (!$ops['id']) {
				wrap_error(sprintf('Could not copy translation for table %s ID %d', $table, $map[$translation['field_id']]));
			}
		}
	}
	return true;
}
