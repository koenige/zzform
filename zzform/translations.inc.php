<?php 

/**
 * zzform
 * Translations
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 *	main functions (in order in which they are called)
 *	zz_translations_init()		checks whether fields should be translated
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2013, 2016-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * initalizes zzform for translation subtables
 *
 * @param string $table current table name to check which fields to translate
 * @param array $fields
 * @param string $action
 * @return array $fields
 */
function zz_translations_init($table, $fields, $action) {
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
		FROM /*_TABLE default_translationfields _*/
		WHERE db_name = "/*_SETTING db_name _*/" AND table_name = "%s"';
	$sql = sprintf($sql, $table);
	$translationfields = zz_db_fetch($sql, 'field_name');

	$all_indices = array_keys($fields);
	asort($all_indices);
	$last_index = array_pop($all_indices);
	$index = $last_index + 1; // last index, increment 1
	unset($all_indices);

	$k = 0;
	$j = 1; // how many fields after original position of field to translate
	$identifier_fields = [];

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
		$zz = zzform_include(sprintf(
			'translations-%s', $translationfields[$field_name]['field_type']
		));
		if (!$zz)
			wrap_error(wrap_text(
				'Translations script for `%s` does not exist!',
				['values' => [$translationfields[$field_name]['field_type']]]
			), E_USER_ERROR);
		$zz = zz_sql_prefix($zz);
		// change title
		$zz['title'] = sprintf('%s (%s)'
			, zz_field_title($fields[$no])
			, wrap_text($zz['title'], ['source' => wrap_setting('zzform_script_path')])
		);
		$zz['table_name'] .= '_'.$k;
		$new_no = $index + $k;
		
		// split fields-array
		// glue together fields-array
		foreach (array_keys($zz['fields']) as $key) {
			$type = $zz['fields'][$key]['type'] ?? '';
 			switch ($type) {
			case 'id':
				break;

			case 'translation_key':
				$zz['fields'][$key]['translation_key'] = $translationfields[$field_name]['translationfield_id'];
				break;

			case 'foreign_key':
				$zz['foreign_key_field_name'] = $zz['fields'][$key]['field_name'];
				break;
			
			default:
				if (!empty($zz['fields'][$key]['inherit_format'])) {
					$inherit_defs = ['type', 'format', 'typo_cleanup', 'rows'];
					foreach ($inherit_defs as $inherit_def) {
						if (!array_key_exists($inherit_def, $fields[$no])) continue;
						if ($inherit_def === 'type' AND $fields[$no]['type'] === 'write_once') {
							if (!empty($fields[$no]['type_detail']))
								$zz['fields'][$key]['type'] = $fields[$no]['type_detail'];
						} elseif ($inherit_def === 'type' AND $fields[$no]['type'] === 'identifier'
						    AND !empty($fields[$no]['identifier_translate_manually'])) {
						    // … do not set this field to 'identifier'
						} else {
							$zz['fields'][$key][$inherit_def] = $fields[$no][$inherit_def];
						}
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
				if (!empty($zz['fields'][$key]['type']) AND $zz['fields'][$key]['type'] === 'identifier') {
					$zz['fields'][$key]['fields'] = $fields[$no]['fields'] ?? [];
					$zz['fields'][$key]['identifier'] = $fields[$no]['identifier'] ?? [];
					$zz['fields'][$key]['identifier']['replace_fields'] = [
						$fields[$no]['field_name'] => $zz['fields'][$key]['field_name']
					];
					// existing WHERE keys are here not possible
					$zz['fields'][$key]['identifier']['where'] = sprintf(
						'translationfield_id = %d', $translationfields[$field_name]['translationfield_id']
					);
					// mark all values as required, to avoid incomplete identifiers
					// if translation is empty
					$zz['fields'][$key]['identifier']['values_required'] = true;
					// read options?
					$zz['fields'][$key]['read_options'] = $fields[$no]['read_options'] ?? NULL;
					$zz['fields'][$key]['if'] = $fields[$no]['if'] ?? [];
					$zz['fields'][$key]['unless'] = $fields[$no]['unless'] ?? [];
					if ($zz['fields'][$key]['read_options'])
						$zz['fields'][$key]['read_options'] = sprintf('0[%s]', $zz['fields'][$key]['read_options']);
					$conditions = ['if', 'unless'];
					foreach ($conditions as $condition) {
						foreach ($zz['fields'][$key][$condition] as $cond_index => $cond) {
							if (empty($cond['read_options'])) continue;
							$zz['fields'][$key][$condition][$cond_index]['read_options'] = sprintf('0[%s]', $cond['read_options']);
						}
					}
					// identifier(s) are created automatically
					$zz['access'] = 'none';
					$identifier_fields[$new_no] = $key;
				}
				if ($zz['fields'][$key]['field_name'] === 'translation') {
					$fields[$no]['translate_subtable'] = $new_no;
					$fields[$no]['translate_subtable_field'] = $key;
				}
				break;
			}
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
		$zz['translate_field_name'] = $field_name;
		$zz['translate_field_index'] = $no;
		if (!empty($fields[$no]['translation']))
			$zz = array_merge($zz, $fields[$no]['translation']);

		$offset = $k + $j;

		$fields = array_slice($fields, 0, $offset, true)
			+ [$new_no => $zz]
			+ array_slice($fields, $offset, null, true);
		
		$j++;
		$k++;
	}

	// was there an identifier?
	if ($identifier_fields AND $action !== 'delete') {
		require_once __DIR__.'/identifier.inc.php';
		zz_identifier_translation_fields($fields, $identifier_fields);
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
