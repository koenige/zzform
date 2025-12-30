<?php 

/**
 * zzwrap
 * Language and internationalization functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzwrap
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */



/**
 * Translate values with wrap_translate() from zzwrap module
 *
 * @param array $def ($field or $zz)
 * @param array $values
 * @return array $values (translated)
 */
function zz_translate($def, $values) {
	if (empty($values)) return $values;
	if (empty($def['sql_translate'])) return $values;
	if (!is_array($def['sql_translate'])) {
		$def['sql_translate'] = [$def['sql_translate']];
	}
	foreach ($def['sql_translate'] as $id_field_name => $table) {
		if (is_numeric($id_field_name)) {
			$values = wrap_translate($values, $table);
		} else {
			$values = wrap_translate($values, $table, $id_field_name);
		}
	}
	foreach (array_keys($values) as $index) {
		if (!is_numeric($index)) {
			unset($values['wrap_source_language']);
			unset($values['wrap_source_content']);
			break;
		}
		unset($values[$index]['wrap_source_language']);
		unset($values[$index]['wrap_source_content']);
	}
	return $values;
}

/**
 * check if there are matches for a query in its translated values as well
 *
 * @param array $field
 *		array 'sql_translate' e. g. ['country_id' => 'countries']
 *		string 'key_field_name' OR 'field_name'
 * @param string $sql_fieldname field name to look for
 * @param string $value value to look for
 * @param bool $search_equal search with = or LIKE
 * @return string SQL query part with ID or empty
 */
function zz_translate_search($field, $sql_fieldname, $value, $search_equal) {
	if (!wrap_setting('translate_fields')) return '';

	// set conditions
	$tconditions = [];
	if ($search_equal) {
		$tconditions[] = sprintf('translation = "%s"', wrap_db_escape(trim($value)));
	} else {
		$tconditions[] = sprintf('translation LIKE "%%%s%%"', wrap_db_escape($value));
	}

	// set query
	$sql_translations = wrap_sql_query('default_translations');
	if (!$sql_translations) return '';
	$sql_translations = str_replace('AND field_id IN (%s)', '', $sql_translations);

	// get translations
	$translationfields = zz_translate_fields_query($field['sql_translate']);
	$records = [];
	foreach ($translationfields as $type => $t_fields) {
		foreach ($t_fields as $t_field) {
			if ($t_field['field_name'] !== $sql_fieldname) continue;
			$sql = sprintf($sql_translations,
				$type, implode(',', array_keys($t_fields)), wrap_setting('lang')
			);
			$sql = wrap_edit_sql($sql, 'WHERE', implode(' OR ', $tconditions));
			$new_records = wrap_db_fetch($sql, '_dummy_', 'numeric');
				$records += $new_records;
		}
	}
	if (!$records) return '';

	// return IDs
	$field_ids = [];
	foreach ($records as $record) {
		$field_ids[$record['field_id']] = $record['field_id'];
	}
	return sprintf('%s IN (%s)', $field['key_field_name'], implode(',', $field_ids));
}

/**
 * check if a field can be translated
 *
 * @param array $sql_translate e. g. [['country_id'] => 'countries']
 * @return array definition in varchar, text, which fields of table can be translated
 */
function zz_translate_fields_query($sql_translate) {
	static $tfields = [];
	if (!is_array($sql_translate))
		$sql_translate = [$sql_translate];
	$key = json_encode($sql_translate);
	if (array_key_exists($key, $tfields)) return $tfields[$key];

	$sql_fields = 'SELECT translationfield_id, field_name, field_type
		FROM /*_TABLE default_translationfields _*/
		WHERE db_name = "%s" AND table_name = "%s"';
	$tfields[$key]['varchar'] = [];
	$tfields[$key]['text'] = [];
	foreach ($sql_translate as $id_field_name => $table) {
		$my = zz_db_table($table);
		$sql = sprintf($sql_fields, $my['db_name'], $my['table']);
		$fields = zz_db_fetch($sql, 'translationfield_id');
		if (!$fields) continue;
		foreach ($fields as $tfield) {
			$tfields[$key][$tfield['field_type']][$tfield['translationfield_id']] = $tfield;
		}
	}
	return $tfields[$key];	
}
