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
 * @param array $map_other other mappings of IDs (optional)
 * @return array
 */
function zz_copy_records($table, $foreign_id_field_name, $source_id, $destination_id, $transfer_field_name = false, $map_other = []) {
	$def = zz_db_table_structure($table);
	$main_id_field_name = 'main_'.$def['primary_key'];

	// existing records
	$sql = 'SELECT * FROM `%s` WHERE `%s` = %d';
	// does a main id field name exist?
	// move values with main id to the end
	if (!empty($def['foreign_keys']) AND in_array($main_id_field_name, $def['foreign_keys']))
		$sql .= sprintf(' ORDER BY IF(ISNULL(%s), NULL, 1)', $main_id_field_name);
	else
		$main_id_field_name = false;
	$sql = sprintf($sql, $def['table'], $foreign_id_field_name, $source_id);
	$data = wrap_db_fetch($sql, $def['primary_key']);
	if (!$data) return [];
	
	$dont_copy = wrap_setting('zzform_copy_fields_exclude');
	$dont_copy[] = $def['primary_key'];
	$dont_copy[] = $foreign_id_field_name;
	
	$map = [];

	$values = [];
	$values['action'] = 'insert';
	$values['ids'] = $def['foreign_keys'];
	$values['POST'][$foreign_id_field_name] = $destination_id;
	foreach ($data as $line) {
		foreach ($line as $field_name => $value)
			if (!in_array($field_name, $dont_copy)) {
				if (array_key_exists($field_name, $map_other) AND array_key_exists($value, $map_other[$field_name]))
					$value = $map_other[$field_name][$value];
				$values['POST'][$field_name] = $value;
			}
		// main ID field name? map to copied main ID field name
		if ($main_id_field_name AND !empty($values['POST'][$main_id_field_name]))
			if (!isset($map[$values['POST'][$main_id_field_name]])) continue; // do not add this record
			else $values['POST'][$main_id_field_name] = $map[$values['POST'][$main_id_field_name]];
		if ($transfer_field_name)
			$values['POST'][$transfer_field_name] = $line[$def['primary_key']];
		$ops = zzform_multi($def['script_name'], $values);
		if (!$ops['id'])
			wrap_error(sprintf('Could not copy %s %d', $def['primary_key'], $line[$def['primary_key']]));
		// map old fields to new fields for translations
		$map[$line[$def['primary_key']]] = $ops['id'];
	}
	zz_copy_records_translations($def['table'], array_keys($data), $map);
	return [$def['primary_key'] => $map];	
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
	$sql = 'SELECT translationfield_id, field_type
		FROM %s
		WHERE db_name = "%s" AND table_name = "%s"';
	$sql = sprintf($sql
		, wrap_sql_table('default_translationfields')
		, wrap_setting('db_name')
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
