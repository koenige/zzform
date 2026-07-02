<?php

/**
 * zzform
 * XHR helper functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * parse and validate a subtable_no from an XHR request
 *
 * supports compound format like "23-5" for nested subtables
 * @param mixed $subtable_no raw value from $_GET['subtable_no']
 * @return array|false array of integer parts on success, false on validation error
 */
function zz_xhr_subtable_parse($subtable_no) {
	if (!$subtable_no) return [];
	$parts = strstr($subtable_no, '-') ? explode('-', $subtable_no) : [$subtable_no];
	foreach ($parts as $part) {
		if (!wrap_is_int($part)) return false;
	}
	return $parts;
}

/**
 * resolve a parsed subtable path to the nested fields array
 *
 * @param array $parts validated parts from zz_xhr_subtable_parse()
 * @param array $fields top-level $zz['fields']
 * @return array|false ['fields' => array, 'definition' => array] on success, false if not found
 */
function zz_xhr_subtable_resolve($parts, $fields) {
	$definition = NULL;
	foreach ($parts as $part) {
		$part = intval($part);
		if (!array_key_exists($part, $fields))
			return false;
		if (!array_key_exists('fields', $fields[$part]))
			return false;
		$definition = $fields[$part];
		$fields = $fields[$part]['fields'];
	}
	return ['fields' => $fields, 'definition' => $definition];
}
