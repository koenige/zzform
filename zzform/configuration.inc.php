<?php 

/**
 * zzform
 * read and parse a configuration file
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 *  zz_configuration()
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * initalize a variable from .cfg file
 *
 * @param string $key
 * @param array $ext existing definition by user
 * @param array $int existing internal definition by system (optional)
 * @return array
 */
function zz_configuration($cfg_key, $ext, $int = []) {
	$cfg = zz_configuration_file($cfg_key);
	$settings = [];
	foreach ($cfg as $key => $def) {
		// ignore deprecated keys
		if (!empty($def['deprecated'])) continue;
		// just for completeness?
		if (!empty($def['no_init'])) continue;
		// value?
		if (empty($def['type'])) $def['type'] = 'text';
		$value = zz_configuration_value($key, $def, $ext, $int);
		if (!$value AND !empty($def['no_auto_init'])) continue;
		$new_settings = wrap_setting_key($key, $value);
		$settings = wrap_array_merge($settings, $new_settings, false);
	}
	// variables in $ext left?
	zz_configuration_unused($cfg_key, $ext, $settings);
	return $settings;
}

/**
 * rewrite deprecated variables from .cfg file
 *
 * @param string $key
 * @param array $settings list of variables which might be changed
 * @return array
 */
function zz_configuration_deprecated($cfg_key, $settings) {
	$cfg = zz_configuration_file($cfg_key);
	foreach ($cfg as $key => $def) {
		if (empty($def['deprecated'])) continue;
		if (empty($def['type'])) $def['type'] = 'text';
		$value = zz_configuration_value($key, $def, $settings);
		if ($value === NULL) continue;
		if (!empty($def['moved_to_zz'])) {
			$new_key = $def['moved_to_zz'] === '1' ? $key : $def['moved_to_zz'];
			$settings = wrap_array_merge($settings, wrap_setting_key($new_key, $value));
			// @todo better display of error message for arrays
			wrap_error(wrap_text(
				'Deprecated notation $%s["%s"] found. Please use $zz["%s"] instead.'
				, ['values' => [$cfg_key, $key, $new_key]]
			), E_USER_DEPRECATED);
		} elseif (!empty($def['moved_to_setting'])) {
			$new_key = $def['moved_to_setting'] === '1' ? $key : $def['moved_to_setting'];
			// invert_setting
			if (!empty($def['invert_setting'])) $value = !$value;
			if (empty($def['ignore_if_true']) OR $value !== true) {
				if (is_array(wrap_setting($new_key)) AND $value)
					wrap_setting_add($new_key, $value);
				else
					wrap_setting($new_key, $value);
			}
			wrap_error(wrap_text(
				'Deprecated notation $%s["%s"] found. Please use wrap_setting("%s") instead.'
				, ['values' => [$cfg_key, $key, $new_key]]
			), E_USER_DEPRECATED);
		} elseif (!empty($def['moved_to'])) {
			$new_key = $def['moved_to'];
			$settings = wrap_array_merge($settings, wrap_setting_key($new_key, $value));
			$settings = wrap_setting_key_unset($key, $settings);
			wrap_error(wrap_text(
				'Deprecated notation $%s["%s"] found. Please use $%s["%s"] instead.'
				, ['values' => [$cfg_key, $key, $cfg_key, $new_key]]
			), E_USER_DEPRECATED);
		
		}
		// @todo support keys inside arrays
		unset($settings[$key]);
	}
	return $settings;
}

/**
 * check for unused (mistyped?) keys in definition
 *
 * @param string $cfg_key
 * @param array $values
 * @param array $settings
 * @return void
 */
function zz_configuration_unused($cfg_key, $values, $settings) {
	foreach ($values as $key => $value) {
		if ($key === '*') continue; // might come from brick forms, merging local_settings
		if (is_array($value)) zz_configuration_unused($cfg_key.'["'.$key.'"]', $value, $settings[$key] ?? []);
		if (array_key_exists($key, $settings)) continue;
		if (!empty($values['init_ignore_log']) AND in_array($key, $values['init_ignore_log'])) continue;
		wrap_error(wrap_text('Key $%s["%s"] is set, but not used.', ['values' => [$cfg_key, $key]]), E_USER_NOTICE);
	}
}

/**
 * get configuration file
 *
 * @param string $key
 * @return array
 */
function zz_configuration_file($key) {
	if (strstr($key, '[')) {
		$keys = rtrim($key, ']');
		$keys = explode('[', $keys);
		// own cfg file
		$cfg = wrap_cfg_files(implode('-', $keys), ['package' => 'zzform']);
		if (!$cfg) {
			// subset of main file
			$cfg = [];
			$cfg_complete = wrap_cfg_files($keys[0], ['package' => 'zzform']);
			if (!$cfg_complete) return [];
			foreach ($cfg_complete as $cfg_key => $def) {
				if (!str_starts_with($cfg_key, $keys[1].'[')) continue;
				$cfg_keys = wrap_setting_key_array($cfg_key);
				$cfg_key = '';
				foreach ($cfg_keys as $index => $sub_key)
					if ($index === 0) continue;
					elseif ($index === 1) $cfg_key .= $sub_key;
					else $cfg_key .= sprintf('[%s]', $sub_key);
				$cfg[$cfg_key] = $def;
			}
		}
	} else {
		$cfg = wrap_cfg_files($key, ['package' => 'zzform']);
	}
	return $cfg;
}

/**
 * get configuration value
 *
 * @param string $key
 * @param array $def definition of a single config key
 * @param array $ext
 * @param array $int
 * @return mixed
 */
function zz_configuration_value($key, $def, $ext, $int = []) {
	// get values from array
	$ext = zz_configuration_array_value($key, $ext);
	$int = zz_configuration_array_value($key, $int);
	// get value, in order int, ext, default
	if ($int)
		$value = $int;
	elseif (isset($ext) AND (empty($def['scope']) OR !in_array('internal', $def['scope'])))
		$value = $ext;
	elseif (isset($def['default']))
		$value = wrap_setting_parse($def['default']);
	elseif (!empty($def['list']))
		$value = [];
	else
		switch ($def['type']) {
		case 'list':
			$value = [];
			break;
		case 'int':
		case 'text':
		default:
			$value = NULL; break;
		}
	
	// array?
	if (!empty($def['list']) AND !is_array($value))
		if ($value) $value = [$value];
		else $value = [];

	// check values if they match type
	switch ($def['type']) {
	case 'enum':
		if (!in_array($value, $def['enum'])) $value = $def['default'] ?? '';
		break;
	}

	return $value;
}

/**
 * get value from array, key might be footer[text], look for $values['footer']['text']
 *
 * @param string $key
 * @param array $value
 * @return mixed
 */
function zz_configuration_array_value($key, $values) {
	if (!strstr($key, '[')) return $values[$key] ?? NULL;
	$keys = rtrim($key, ']');
	$keys = explode('[', $keys);
	$value = $values;
	foreach ($keys as $key) {
		$key = rtrim($key, ']');
		$value = $value[$key] ?? NULL;
	}
	return $value;
}
