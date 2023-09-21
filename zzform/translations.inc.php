<?php 

/**
 * zzform
 * Translations
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 *	main functions (in order in which they are called)
 *	zz_translations_init()		checks whether fields should be translated
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2013, 2016-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * initalizes zzform for translation subtables
 *
 * @param string $table current table name to check which fields to translate
 * @param array $fields
 * @return array $fields
 */
function zz_translations_init($table, $fields) {
	if (!wrap_setting('translate_fields')) return $fields;
	if (!wrap_sql_table('default_translationfields')) {
		zz_error_log([
			'msg_dev' => '`default_translationfields__table` must be set.',
			'level' => E_USER_ERROR
		]);
		return zz_error();
	}
	if (!wrap_setting('languages_allowed')) return $fields;
	if (count(wrap_setting('languages_allowed')) === 1) return $fields;

	// Step 1: get fields which might be translated
	$sql = 'SELECT translationfield_id, field_name, field_type
		FROM %s
		WHERE db_name = "%s" AND table_name = "%s"';
	$sql = sprintf($sql, wrap_sql_table('default_translationfields'), wrap_setting('db_name'), $table);
	$translationfields = zz_db_fetch($sql, 'field_name');

	$all_indices = array_keys($fields);
	asort($all_indices);
	$last_index = array_pop($all_indices);
	$index = $last_index + 1; // last index, increment 1
	unset($all_indices);

	$k = 0;
	$j = 1; // how many fields after original position of field to translate

	foreach (array_keys($fields) as $no) {
		if (empty($fields[$no]['field_name'])) {
			$k++;
			continue;
		}
		$field_name = $fields[$no]['field_name'];
		if (empty($translationfields[$field_name])) {
			$k++;
			continue;
		}
		if (!empty($fields[$no]['hide_in_form'])) continue;

		// include new subtable for translations
		$zz = [];	
		$translationsubtable = [];	

		// include and read translation script
		$zz = zzform_include(sprintf('translations-%s', $translationfields[$field_name]['field_type']));
		if (!$zz)
			wrap_error(sprintf('Translations script for `%s` does not exist!', $translationfields[$field_name]['field_type']), E_USER_ERROR);
		$zz = zz_sql_prefix($zz);
		// change title
		$zz['title'] = sprintf('%s (%s)'
			, zz_field_title($fields[$no])
			, wrap_text($zz['title'])
		);
		
		// split fields-array
		// glue together fields-array
		foreach (array_keys($zz['fields']) as $key) {
			if (!empty($zz['fields'][$key]['type'])) {
				if ($zz['fields'][$key]['type'] == 'translation_key') {
					$zz['fields'][$key]['translation_key'] = $translationfields[$field_name]['translationfield_id'];
				} elseif ($zz['fields'][$key]['type'] == 'foreign_key') {
					$zz['foreign_key_field_name'] = $zz['fields'][$key]['field_name'];
				}
			}
			if (!empty($zz['fields'][$key]['inherit_format'])) {
				$inherit_defs = ['type', 'format', 'typo_cleanup', 'rows'];
				foreach ($inherit_defs as $inherit_def) {
					if (!array_key_exists($inherit_def, $fields[$no])) continue;
					$zz['fields'][$key][$inherit_def] = $fields[$no][$inherit_def];
					if ($inherit_def === 'type' AND $fields[$no][$inherit_def] === 'memo'
						AND $translationfields[$field_name]['field_type'] === 'varchar') {
						// varchar form: display below, not inline
						unset($zz['form_display']);
						$zz['fields'][5]['append_next'] = false;
					}
				}
			}
			if (!empty($fields[$no]['rows']))
				$zz['fields'][$key]['rows'] = $fields[$no]['rows'];
		}
		if (!empty($fields[$no]['if']))
			$zz['if'] = $fields[$no]['if'];
		if (!empty($fields[$no]['unless']))
			$zz['unless'] = $fields[$no]['unless'];
		if (!empty($fields[$no]['separator'])) {
			$zz['separator'] = $fields[$no]['separator'];
			unset($fields[$no]['separator']);
		}
		if (!empty($fields[$no]['field_sequence'])) {
			if (strstr($fields[$no]['field_sequence'], '.')) {
				$zz['field_sequence'] = $fields[$no]['field_sequence'].'1';
			} else {
				$zz['field_sequence'] = $fields[$no]['field_sequence'].'.1';
			}
		}
		$translationsubtable[$index+$k] = $zz;
		$translationsubtable[$index+$k]['table_name'] .= '_'.$k;
		$translationsubtable[$index+$k]['translate_field_name'] = $field_name;
		$translationsubtable[$index+$k]['translate_field_index'] = $no;
		if(!empty($fields[$no]['translation'])) {
			$translationsubtable[$index+$k] = array_merge($translationsubtable[$index+$k], $fields[$no]['translation']);
		}
		$zz_fields = array_merge(array_slice($fields, 0, $k+$j), $translationsubtable, array_slice($fields, $k+$j));
	// old PHP 4 support
		$zz_fields_keys = array_merge(array_slice(array_keys($fields), 0, $k+$j), array_keys($translationsubtable), array_slice(array_keys($fields), $k+$j));
		unset($fields);
		foreach($zz_fields_keys as $f_index => $real_index) {
			$fields[$real_index] = $zz_fields[$f_index];
		}
		$j++;
	// old PHP 4 support end, might be replaced by variables in array_slice
		$k++;
	}
	return $fields;
}

/**
 * check if a field can be translated
 *
 * @param array $sql_translate e. g. [['country_id'] => 'countries']
 * @return array definition in varchar, text, which fields of table can be translated
 */
function zz_translations_fields($sql_translate) {
	static $tfields;
	if (empty($tfields)) $tfields = [];
	if (!is_array($sql_translate)) {
		$sql_translate = [$sql_translate];
	}
	$key = json_encode($sql_translate);
	if (array_key_exists($key, $tfields)) return $tfields[$key];

	$sql_fields = 'SELECT translationfield_id, field_name, field_type
		FROM '.wrap_sql_table('default_translationfields').'
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
