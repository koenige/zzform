<?php 

/**
 * zzform
 * Translations
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 *	main functions (in order in which they are called)
 *	zz_translations_init()		checks whether fields should be translated
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2013, 2016-2018 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Default settings for translation module
 */
function zz_translations_config() {
	$default['translations_of_fields'] = false;
	$default['translations_table'] = '';
	$default['translations_script'] = [];
	zz_write_conf($default);
}

/**
 * initalizes zzform for translation subtables
 *
 * @param string $table current table name to check which fields to translate
 * @param array $fields
 * @global array $zz_conf
 * @return array $fields
 */
function zz_translations_init($table, $fields) {
	global $zz_conf;
	global $zz_setting;

	if (!$zz_conf['translations_of_fields']) return $fields;
	if (!$zz_conf['translations_table']) {
		zz_error_log([
			'msg_dev' => '$zz_conf[\'translations_table\'] must be set.',
			'level' => E_USER_ERROR
		]);
		return zz_error();
	}
	if (count($zz_setting['languages_allowed']) === 1) return $fields;

	// Step 1: get fields which might be translated
	$sql = 'SELECT translationfield_id, field_name, field_type
		FROM %s
		WHERE db_name = "%s" AND table_name = "%s"';
	$sql = sprintf($sql, $zz_conf['translations_table'], $zz_conf['db_name'], $table);
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

		// include new subtable for translations
		$zz_sub = [];	
		$translationsubtable = false;	

		// include and read translation script
		if (array_key_exists($translationfields[$field_name]['field_type'], $zz_conf['translations_script'])) {
			require $zz_conf['dir_custom'].'/'.$zz_conf['translations_script'][$translationfields[$field_name]['field_type']].'.inc.php';
		} else {
			$file = zzform_file(sprintf('translations-%s', $translationfields[$field_name]['field_type']));
			if (!$file)
				wrap_error(sprintf('Translations script for `%s` does not exist!', $translationfields[$field_name]['field_type']), E_USER_ERROR);
			require $file['tables'];
		}
		$zz_sub = zz_sql_prefix($zz_sub);
		
		// split fields-array
		// glue together fields-array
		foreach (array_keys($zz_sub['fields']) as $key) {
			if (!empty($zz_sub['fields'][$key]['type'])) {
				if ($zz_sub['fields'][$key]['type'] == 'translation_key') {
					$zz_sub['fields'][$key]['translation_key'] = $translationfields[$field_name]['translationfield_id'];
				} elseif ($zz_sub['fields'][$key]['type'] == 'foreign_key') {
					$zz_sub['foreign_key_field_name'] = $zz_sub['fields'][$key]['field_name'];
				}
			}
			if (!empty($zz_sub['fields'][$key]['inherit_format']) AND !empty($fields[$no]['format']))
				$zz_sub['fields'][$key]['format'] = $fields[$no]['format'];
		}
		$translationsubtable[$index+$k] = $zz_sub;
		$translationsubtable[$index+$k]['table_name'] .= '-'.$k;
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
