<?php

/**
 * zzform
 * formatting functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
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
		} else {
			$attr[] = sprintf('%s="%s"', $attr_name, $attr_value);
		}
	}
	$attr = implode(' ', $attr);
	if ($attr) $attr = ' '.$attr;
	return $attr;
}
