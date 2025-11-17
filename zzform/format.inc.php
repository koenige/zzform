<?php

/**
 * zzform
 * formatting functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * prepare attributes for HTML
 *
 * @param array $fieldattr
 * @return string
 */
function zzform_attributes($fieldattr) {
	$attr = [];
	foreach ($fieldattr as $attr_name => $attr_value) {
		if ($attr_value === false OR is_null($attr_value)) {
			// boolean false
			continue;
		} elseif ($attr_value === true) {
			// boolean true
			$attr[] = $attr_name;
		} elseif (strstr($attr_value, '"')) {
			$attr[] = sprintf("%s='%s'", $attr_name, $attr_value);
		} else {
			$attr[] = sprintf('%s="%s"', $attr_name, $attr_value);
		}
	}
	$attr = implode(' ', $attr);
	if ($attr) $attr = ' '.$attr;
	return $attr;
}

/**
 * concatenate list of classes for output
 *
 * @param array $classes
 * @return string
 */
function zzform_classes($classes) {
	if (!$classes) return '';
	if (!is_array($classes)) return $classes;
	return implode(' ', $classes);
}

/**
 * get a date with a rounded time
 *
 * @param string $date_iso
 * @param int $round_to_min
 * @return string
 */
function zzform_round_date($date_iso = '', $round_to_min = NULL) {
	if (is_null($round_to_min)) $round_to_min = wrap_setting('zzform_date_round_to_min');
	if (!$round_to_min) return $date_iso;
	$date_time = date_create($date_iso);

	// rounding seconds
	if (date_format($date_time, 's') > 30)
		date_modify($date_time, '+1 minute');

	// rounding minutes
	$minute = date_format($date_time, 'i');
	$minute_rounded = round($minute / $round_to_min) * $round_to_min;
	$minute_diff = $minute_rounded - $minute; 
	date_modify($date_time, '+'.$minute_diff.' minute');
	
	return date_format($date_time, 'Y-m-d H:i');
}

/**
 * format a setting
 *
 * @param string $value
 * @param array $cfg
 * @return string
 */
function zz_format_setting($value, $cfg = []) {
	wrap_include('list', 'zzform');

	$value = trim($value);
	if (array_key_exists('private', $cfg) AND ($cfg['private']))
		return sprintf('<abbr title="%s">******</abbr>',
			wrap_text('The value is only visible during editing.')
		);

	if (str_starts_with($value, '[') AND str_ends_with($value, ']')) {
		$value = substr($value, 1, -1);
		$values = explode(',', $value);
		foreach (array_keys($values) as $index) {
			$values[$index] = trim($values[$index]);
			$values[$index] = zz_list_word_split($values[$index]);
		}
		$value = implode('</li><li>', $values);
		return sprintf('<ul class="default-settings"><li>%s</li></ul>', $value);
	}

	return zz_list_word_split($value);
}
