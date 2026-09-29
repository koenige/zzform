<?php 

/**
 * zzform
 * Runtime field definitions
 *
 * Prepare `$zz['fields']` for list, record, validation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Prepares field definitions: defaults, translations, DB-derived info
 *
 * @param array $fields
 * @param string $db_table [i. e. db_name.table or just table]
 * @param bool $multiple_times marker for conditions
 * @param string $mode (optional, $ops['mode'])
 * @param string $action (optional, $zz['record']['action'])
 * @param int $subtable_no number of subtable in definition
 * @return array $fields
 */
function zz_prepare_fields($fields, $db_table, $multiple_times = false, $mode = false, $action = false, $subtable_no = false) {
	if (wrap_setting('debug')) {
		zz_debug('start', __FUNCTION__.$multiple_times);
	}
	if ($db_table) {
		$table_def = zz_db_table($db_table);
		$db_table = $table_def['db_name'].'.'.$table_def['table'];
	}
	static $defs = [];
	$hash = md5(serialize($fields).$db_table.$multiple_times.$mode.$subtable_no);
	if (!empty($defs[$hash])) return zz_return($defs[$hash]);

	$to_translates = [
		'explanation', 'explanation_top', 'title_append', 'title_tab'
	];

	foreach (array_keys($fields) as $no) {
		if (!empty($fields[$no]['if'])) {
			if ($multiple_times === 1) {
				 // if there are only conditions, go on
				if (count($fields[$no]) === 1) continue;
			}
		}
		if (!$fields[$no]) {
			// allow placeholder for fields to get them into the wanted order
			unset($fields[$no]);
			continue;
		}
		$fields[$no]['field_no'] = $no;
		$fields[$no]['subtable_no'] = $subtable_no;
		if (!isset($fields[$no]['type'])) {
			// default type: text
			$fields[$no]['type'] = 'text';
		}
		$fields[$no]['title'] = zz_field_title($fields[$no]);
		if (empty($fields[$no]['class'])) $fields[$no]['class'] = [];
		elseif (!is_array($fields[$no]['class'])) $fields[$no]['class'] = [$fields[$no]['class']];

		if (empty($fields[$no]['translated'])) {
			// translate fieldnames, if set
			foreach ($to_translates as $to_translate) {
				if (empty($fields[$no][$to_translate])) continue;
				$fields[$no][$to_translate] = wrap_text($fields[$no][$to_translate], ['source' => wrap_static('zzform', 'script_path')]);
			}
			$fields[$no]['translated'] = true;
		}

		if (!isset($fields[$no]['explanation'])) {
			$fields[$no]['explanation'] = '';
		}
		if (!$multiple_times
			AND ($fields[$no]['type'] ?? '') === 'parameter'
			AND empty($fields[$no]['help'])
		) {
			$parameter_help = zz_prepare_fields_parameter_help($db_table);
			if ($parameter_help) $fields[$no]['help'] = $parameter_help;
		}
		if (!$multiple_times AND !empty($fields[$no]['help'])) {
			$help = brick(['helplink', $fields[$no]['help']]);
			if ($help) {
				$help = str_replace(['<p ', '</p>'], ['<span ', '</span>'], trim($help));
				if ($fields[$no]['explanation']) $fields[$no]['explanation'] .= '<br>';
				$fields[$no]['explanation'] .= $help;
			}
			unset($fields[$no]['help']);
		}

		if (!$multiple_times) {
			if (!empty($fields[$no]['sql'])) // replace whitespace with space
				$fields[$no]['sql'] = preg_replace("/\s+/", " ", $fields[$no]['sql']);
		}

		// settings depending on field type
		switch ($fields[$no]['type']) {
		case 'option':
			// do not show option-fields in tab
			$fields[$no]['hide_in_list'] = true;
			// makes no sense to export a form field
			$fields[$no]['export'] = false;
			// format option-fields with CSS
			if (!in_array('option', $fields[$no]['class'])) {
				$fields[$no]['class'][] = 'option';
			}
			break;
		}		
		
		$type = $fields[$no]['type_detail'] ?? $fields[$no]['type'];
		if (!$subtable_no) {
			// these do not exist in main record, replace type
			switch ($type) {
			case 'translation_key':
			case 'foreign_key':
				$fields[$no]['type'] = $fields[$no]['type_detail'] ?? 'number';
				break;
			}
		}

		switch ($type) {
		case 'write_once':
			// would not be here if it had a type_detail set
			$fields[$no]['type_detail'] = 'text';
			break;
		case 'id':
			// set dont_sort as a default for ID columns
			if (!isset($fields[$no]['dont_sort'])) $fields[$no]['dont_sort'] = true;
			// hide empty ID fields on add
			if ($mode === 'add') $fields[$no]['hide_in_form'] = true;
			break;
		case 'memo':
			$fields[$no]['class'][] = 'hyphenate';
			break;
		case 'subtable':
		case 'foreign_table':
			if (empty($fields[$no]['subselect']) AND !isset($fields[$no]['export'])) {
				// subtables have no output by default unless there is a subselect
				// definition; however in rare cases (e. g. with a condition set)
				// you might want to overwrite this manually
				$fields[$no]['export'] = false;
			}
			// for subtables, do this as well; here we still should have a
			// different db_name in 'table' if using multiples dbs so it's no
			// need to prepend the db name of this table
			if (empty($fields[$no]['table_name'])) {
				$fields[$no]['table_name'] = $fields[$no]['table'];
			}
			$fields[$no]['fields'] = zz_prepare_fields(
				$fields[$no]['fields'], $fields[$no]['table'], $multiple_times,
				$mode, $action, !empty($fields[$no]['subtable_no']) ? $fields[$no]['subtable_no'].'-'.$no : $no
			);
			break;
		case 'captcha':
			if (!empty($action) AND $action !== 'insert') {
				unset($fields[$no]);
				continue 2;
			}
			if (!empty($mode) AND $mode !== 'add') {
				unset($fields[$no]);
				continue 2;
			}
			$fields[$no]['hide_in_list'] = true;
			$fields[$no]['export'] = false;
			break;
		case 'password':
		case 'password_change':
			if (!isset($fields[$no]['minlength'])) $fields[$no]['minlength'] = 8;
			if (!isset($fields[$no]['maxlength'])) $fields[$no]['maxlength'] = 60;
			break;
		case 'upload_image':
			wrap_include('upload', 'zzform');
			$fields[$no]['upload_max_filesize'] = zz_upload_max_filesize($fields[$no]['upload_max_filesize'] ?? 0);
			if (!empty($fields[$no]['image'])) {
				foreach ($fields[$no]['image'] as $img => &$image) {
					if (!empty($image['path'])) continue;
					if (!empty($fields[$no]['path']))
						$image['path'] = $fields[$no]['path'];
					if (!empty($fields[$no]['path_web']))
						$image['path_web'] = $fields[$no]['path_web'];
				}
				unset($image);
			}
			break;
		case 'select':
			if (!isset($fields[$no]['max_select']))
				$fields[$no]['max_select'] = wrap_setting('zzform_max_select');
			if (!isset($fields[$no]['max_select_val_len']))
				$fields[$no]['max_select_val_len'] = wrap_setting('zzform_max_select_val_len');
			zz_prepare_fields_enum_set($fields[$no]);
		case 'foreign_key':
			$fields[$no]['key_field_name'] = zz_prepare_fields_key_field_name($fields[$no]);
			// shortcut as key for results
			$fields[$no]['key_field'] = $fields[$no]['key_field_name'];
			if ($pos = strrpos($fields[$no]['key_field'], '.'))
				$fields[$no]['key_field'] = substr($fields[$no]['key_field'], $pos + 1);
			break;
		case 'time':
		case 'datetime':
			if (empty($fields[$no]['time_format']))
				$fields[$no]['time_format'] = 'H:i';
			// no break here
		case 'date':
			if (!empty($fields[$no]['default']) AND $fields[$no]['default'] === 'current_date') {
				$fields[$no]['default'] = date('Y-m-d H:i:s');
			}
			if (!empty($fields[$no]['value']) AND $fields[$no]['value'] === 'current_date') {
				$fields[$no]['value'] = date('Y-m-d H:i:s');
			}
			// same for if/unless (merged later, so replace placeholder here)
			foreach (['if', 'unless'] as $cond_key) {
				if (empty($fields[$no][$cond_key])) continue;
				foreach ($fields[$no][$cond_key] as $condition => $cond_values) {
					if (!is_array($cond_values)) continue;
					foreach (['default', 'value'] as $key) {
						if (!empty($cond_values[$key]) AND $cond_values[$key] === 'current_date') {
							$fields[$no][$cond_key][$condition][$key] = date('Y-m-d H:i:s');
						}
					}
				}
			}
			if (!empty($fields[$no]['default']) AND !empty($fields[$no]['round_date'])) {
				wrap_include('format', 'zzform');
				$fields[$no]['default'] = zzform_round_date($fields[$no]['default']);
			}
			break;
		case 'identifier':
			if (!empty($fields[$no]['conf_identifier'])) {
				$fields[$no]['identifier'] = $fields[$no]['conf_identifier'];
				wrap_error('Use key `identifier` instead of `conf_identifier`', E_USER_DEPRECATED);
			}
			break;
		case 'url':
		case 'url+placeholder':
			if (!isset($fields[$no]['max_select_val_len']))
				$fields[$no]['max_select_val_len'] = wrap_setting('zzform_max_select_val_len');
		}

		if (in_array($mode, ['add', 'edit', 'revise']) OR in_array($action, ['insert', 'update'])) {
			if (empty($fields[$no]['maxlength'])) {
				if (isset($fields[$no]['field_name'])) {
					// no need to check maxlength in list view only
//					if (!in_array($fields[$no]['type'], ['number', 'sequence'], true)) {
						zz_db_field_maxlength($fields[$no], $db_table);
//					}
				} else {
					$fields[$no]['maxlength'] = 32;
				}
			}
			$fields[$no]['required'] = zz_prepare_fields_required($fields[$no], $db_table);
		} else {
			if (!isset($fields[$no]['maxlength'])) $fields[$no]['maxlength'] = 0;
			if (!isset($fields[$no]['required'])) $fields[$no]['required'] = false;
		}
		// save 'required' status for validation of subrecords as well,
		// where required attribute might be set to false
		$fields[$no]['required_in_db'] = $fields[$no]['required'];
	}
	$defs[$hash] = $fields;
	return zz_return($fields);
}

/**
 * help path for a parameter field: {package}/{table}-parameters
 *
 * @param string $db_table db_name.table
 * @return string|null
 */
function zz_prepare_fields_parameter_help($db_table) {
	if (!wrap_path('default_help', [], ['testing' => 1])) return null;

	$db_table = explode('.', $db_table);
	$table = $db_table[1] ?? $db_table[0];
	$table = wrap_db_prefix_remove($table);
	if (wrap_setting('db_prefix') AND str_starts_with($table, wrap_setting('db_prefix')))
		$table = substr($table, strlen(wrap_setting('db_prefix')));

	$files = wrap_collect_files('zzbrick_tables/index.json');
	$index = [];
	foreach ($files as $package => $file) {
		$data = json_decode(file_get_contents($file), true);
		if (array_key_exists($table, $data))
			return $package.'/'.$table.'-parameters';
	}
	return null;
}

/**
 * sets attribute 'required' to fields which do not have one yet
 * depending on field type, NULL and null_string
 *
 * @param array $field field definition from $zz['fields'][$no]
 * @param string $db_table [i. e. db_name.table]
 * @return bool true: field is required, false: field is optional
 */
function zz_prepare_fields_required($field, $db_table) {
	if (!empty($field['required'])) return true;
	if (isset($field['required'])) return false;
	// might be empty string
	if (!empty($field['null_string'])) return false;
	// no field name = not in database
	if (empty($field['field_name'])) return false;
	// might be NULL
	if (zz_db_field_null($field['field_name'], $db_table)) return false;
	// some field types never can be required
	$never_required = [
		'calculated', 'display', 'option', 'image', 'foreign', 'subtable', 'foreign_table'
	];
	if (in_array($field['type'], $never_required)) return false;

	return true;
}

/**
 * get key_field_name from first field in SQL query
 *
 * @param array $field
 * @return string
 */
function zz_prepare_fields_key_field_name($field) {
	if (!empty($field['key_field_name']))
		return $field['key_field_name'];
	if (!empty($field['id_field_name'])) {
		wrap_error('Please use `key_field_name` instead of `id_field_name`.', E_USER_DEPRECATED);
		return $field['id_field_name'];
	}

	if (!empty($field['sql'])) {
		if (str_starts_with($field['sql'], 'SHOW DATABASES'))
			return 'Database';
		// categories.category_id or category_id
		$fields = wrap_edit_sql($field['sql'], 'SELECT', '', 'list');
		if (isset($fields[0]['field_name']))
			return $fields[0]['field_name'];
	}
	// just a backup in case, e. g. media_category_id
	if (str_ends_with($field['field_name'], '_id')
		AND substr_count($field['field_name'], '_') > 1) {
		$pos = strrpos(substr($field['field_name'], 0, -3), '_') + 1;
		return substr($field['field_name'], $pos);
	}
	// category_id
	return $field['field_name'];
}

/**
 * build enum/set + enum_abbr/set_abbr from configuration/*.tsv (replaces manual lists)
 * Only for type = select (with enum and/or set).
 *
 * @param array $field field definition (by reference)
 * @return void
 */
function zz_prepare_fields_enum_set(&$field) {
	$specs = [
		['set_tsv', 'set_tsv_package', 'set'],
		['enum_tsv', 'enum_tsv_package', 'enum'],
	];
	foreach ($specs as $spec) {
		list($key_files, $key_package, $which) = $spec;
		if (empty($field[$key_files])) continue;
		$files = $field[$key_files];
		if (!is_array($files)) $files = [$files];
		$package = $field[$key_package] ?? '';
		$map = [];
		foreach ($files as $file) {
			$map = array_merge($map, wrap_tsv_parse($file, $package));
		}
		$field[$which] = [];
		$field[$which.'_abbr'] = [];
		foreach ($map as $key => $english) {
			$field[$which][] = $key;
			$field[$which.'_abbr'][] = wrap_text($english);
		}
		unset($field[$key_files], $field[$key_package]);
	}
}

/**
 * sets some $zz-definitions for records depending on existing definition for
 * translations, subtabes, uploads, write_once-fields
 *
 * changes in 'subtables', 'save_old_record', some minor 'fields' 
 * @param array $fields = $zz['fields']
 * @return bool 
 */
function zz_set_fielddefs_for_record(&$zz) {
	$tab = 1;
	foreach (array_keys($zz['fields']) as $no) {
		// translations
		if (!empty($zz['fields'][$no]['translate_field_index'])) {
			$t_index = $zz['fields'][$no]['translate_field_index'];
			if (isset($zz['fields'][$t_index]['translation'])
				AND !$zz['fields'][$t_index]['translation']) {
				unset ($zz['fields'][$no]);
				continue;
			}
		}
		if (!isset($zz['fields'][$no]['type'])) continue;
		switch ($zz['fields'][$no]['type']) {
		case 'subtable':
		case 'foreign_table':
			// save number of subtable, get table_name and check whether sql
			// is unique, look for upload form as well
			$zz['record']['subtables'][$tab] = $no;
			if (!isset($zz['fields'][$no]['table_name']))
				$zz['fields'][$no]['table_name'] = $zz['fields'][$no]['table'];
			$zz['fields'][$no]['subtable'] = $tab;
			$tab++;
			if (!empty($zz['fields'][$no]['sql_not_unique'])) {
				// must not change record where main record is not directly 
				// superior to detail record 
				// - foreign ID would be changed to main record's id
				$zz['fields'][$no]['access'] = 'show';
			}
			foreach ($zz['fields'][$no]['fields'] as $subno => $subfield) {
				if (empty($subfield['type'])) continue;
				switch ($subfield['type']) {
				case 'upload_image':
					$zz['record']['upload_form'] = true;
					break;
				case 'subtable': 
					$zz['record']['subtables'][$tab] = $no.'-'.$subno;
					if (!isset($subfield['table_name']))
						$zz['fields'][$no]['fields'][$subno]['table_name'] = $subfield['table'];
					$zz['fields'][$no]['fields'][$subno]['subtable'] = $tab;
					$tab++;
					break;
				}
			}
			break;
		case 'upload_image':
			$zz['record']['upload_form'] = true;
			break;
		case 'write_once':
		case 'display':
			$zz['record']['save_old_record'][] = $no;
			break;
		}
	}
	return true;
}
