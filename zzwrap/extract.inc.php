<?php 

/**
 * zzform
 * extract functions for zzwrap: scan table/form definitions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Register zzform extract handlers
 *
 * @return array list of handler definitions
 */
function zz_extract_register() {
	return [
		[
			'match' => ['zzbrick_tables/*.php', 'zzbrick_forms/*.php'],
			'scan' => 'zz_extract_table_fields',
		],
	];
}

/**
 * Scan a table/form definition file for translatable $zz values
 *
 * Extracts string literals assigned to keys marked translate = 1 in
 * zz-fields.cfg ($zz['fields'][n][key]) and zz.cfg ($zz[key]).
 * Skips assignments whose value is a function call, variable, or boolean.
 *
 * @param string $content file contents with Unix line endings
 * @param string $relative_path path relative to package folder
 * @param array $entries collected entries (by reference)
 * @return void
 */
function zz_extract_table_fields($content, $relative_path, &$entries) {
	$pot = wrap_extract_translate_pot($content);
	$field_keys = zz_extract_translatable_field_keys();
	$zz_keys = zz_extract_translatable_zz_keys();

	// $zz['fields'][n]['key'] = 'value';
	// $zz['fields'][n]['key'][] = 'value';
	// $zz['fields'][n]['key']['subkey'] = 'value';
	foreach ($field_keys as $key) {
		$pattern = '/\$zz\[\'fields\'\]\[\d+\]\[\''
			. preg_quote($key, '/')
			. '\'\](\[\]|\[\'[^\']*\'\]|\["[^"]*"\])?\s*=\s*/';
		if (!preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) continue;

		foreach ($matches[0] as $index => $match) {
			$value_offset = $match[1] + strlen($match[0]);
			$is_array_push = ($matches[1][$index][0] === '[]');
			zz_extract_assignment_value(
				$content, $value_offset, $relative_path, $pot, $key,
				$is_array_push, $entries
			);
		}
	}

	// $zz['key'] = 'value';
	foreach ($zz_keys as $key) {
		$pattern = '/\$zz\[\'' . preg_quote($key, '/') . '\'\]\s*=\s*/';
		if (!preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) continue;

		foreach ($matches[0] as $match) {
			$value_offset = $match[1] + strlen($match[0]);
			zz_extract_assignment_value(
				$content, $value_offset, $relative_path, $pot, $key,
				false, $entries
			);
		}
	}

	// implicit titles: field_name without explicit title
	zz_extract_implicit_titles($content, $relative_path, $pot, $entries);
}

/**
 * Extract implicit titles derived from field_name when title is not set
 *
 * @param string $content file contents with Unix line endings
 * @param string $relative_path path relative to package folder
 * @param string $pot translate_pot suffix
 * @param array $entries collected entries (by reference)
 * @return void
 */
function zz_extract_implicit_titles($content, $relative_path, $pot, &$entries) {
	// collect field indices that have an explicit title
	$has_title = [];
	if (preg_match_all(
		'/\$zz\[\'fields\'\]\[(\d+)\]\[\'title\'\]/',
		$content, $matches
	)) {
		$has_title = array_flip($matches[1]);
	}

	// collect field_name assignments
	if (!preg_match_all(
		'/\$zz\[\'fields\'\]\[(\d+)\]\[\'field_name\'\]\s*=\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/',
		$content, $matches, PREG_OFFSET_CAPTURE
	)) return;

	foreach ($matches[1] as $index => $field_index_match) {
		$field_index = $field_index_match[0];
		if (isset($has_title[$field_index])) continue;

		$quoted = $matches[2][$index][0];
		$field_name = zz_extract_string_at($content, $matches[2][$index][1]);
		if ($field_name === null OR $field_name === '') continue;

		$title = zz_field_title_extract($field_name);
		if ($title === '') continue;

		$reference = sprintf(
			'%s:%d', $relative_path,
			wrap_extract_line_number($content, $matches[2][$index][1])
		);
		wrap_extract_add($entries, $title, $reference, $pot);
	}
}

/**
 * Extract translatable value(s) from the RHS of an assignment
 *
 * Handles: string literals, array of strings (['a', 'b']).
 * Skips: function calls, variables, booleans, integers.
 * Flags wrap_text() usage for manual review.
 *
 * @param string $content file contents
 * @param int $offset byte offset of the value (after = )
 * @param string $relative_path path relative to package folder
 * @param string $pot translate_pot suffix
 * @param string $key the field key name (for warnings)
 * @param bool $is_array_push true if assignment was ['key'][] =
 * @param array $entries collected entries (by reference)
 * @return void
 */
function zz_extract_assignment_value($content, $offset, $relative_path, $pot, $key, $is_array_push, &$entries) {
	if (preg_match('/\G\s*/', $content, $ws, 0, $offset))
		$offset += strlen($ws[0]);
	if ($offset >= strlen($content)) return;

	$char = $content[$offset];

	// skip variables, booleans, integers, null
	if ($char === '$') return;
	if (preg_match('/\G(true|false|null|TRUE|FALSE|NULL)\b/', $content, $m, 0, $offset)) return;
	if (preg_match('/\G\d/', $content, $m, 0, $offset)) return;

	// skip function calls (wrap_text is already caught by the PHP handler)
	if (preg_match('/\G[a-zA-Z_]\w*\s*\(/', $content, $m, 0, $offset)) {
		if (preg_match('/\Gwrap_text\s*\(/', $content, $m, 0, $offset))
			zz_extract_warn_wrap_text($relative_path, $key, $content, $offset);
		return;
	}

	// wrap_text-style array: ['msgid', ['context' => '...']]
	if ($char === '[') {
		$text_array = wrap_extract_text_array($content, $offset);
		if ($text_array) {
			$reference = sprintf(
				'%s:%d', $relative_path,
				wrap_extract_line_number($content, $text_array['offset'])
			);
			wrap_extract_add($entries, $text_array['msgid'], $reference, $pot, $text_array['context']);
			return;
		}
		// plain array literal: ['value1', 'value2']
		zz_extract_array_values($content, $offset, $relative_path, $pot, $key, $entries);
		return;
	}

	// string literal
	if ($char === '\'' OR $char === '"') {
		$msgid = zz_extract_string_at($content, $offset);
		if ($msgid === null OR $msgid === '') return;
		$reference = sprintf(
			'%s:%d', $relative_path,
			wrap_extract_line_number($content, $offset)
		);
		wrap_extract_add($entries, $msgid, $reference, $pot);
	}
}

/**
 * Extract translatable strings from an array literal assignment
 *
 * @param string $content file contents
 * @param int $offset byte offset of opening [
 * @param string $relative_path path relative to package folder
 * @param string $pot translate_pot suffix
 * @param string $key the field key name
 * @param array $entries collected entries (by reference)
 * @return void
 */
function zz_extract_array_values($content, $offset, $relative_path, $pot, $key, &$entries) {
	$length = strlen($content);
	$pos = $offset + 1;

	while ($pos < $length) {
		if (preg_match('/\G\s*/', $content, $ws, 0, $pos))
			$pos += strlen($ws[0]);
		if ($pos >= $length) break;
		if ($content[$pos] === ']') break;
		if ($content[$pos] === ',') {
			$pos++;
			continue;
		}

		// skip keys in key => value pairs
		if (preg_match('/\G(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|[a-zA-Z_]\w*|\d+)\s*=>/', $content, $m, 0, $pos)) {
			$pos += strlen($m[0]);
			if (preg_match('/\G\s*/', $content, $ws, 0, $pos))
				$pos += strlen($ws[0]);
		}

		if ($pos >= $length) break;
		$char = $content[$pos];

		if ($char === '\'' OR $char === '"') {
			$msgid = zz_extract_string_at($content, $pos);
			if ($msgid !== null AND $msgid !== '') {
				$reference = sprintf(
					'%s:%d', $relative_path,
					wrap_extract_line_number($content, $pos)
				);
				wrap_extract_add($entries, $msgid, $reference, $pot);
			}
			// advance past the string
			if ($char === '\'')
				preg_match('/\G\'(?:[^\'\\\\]|\\\\.)*\'/', $content, $m, 0, $pos);
			else
				preg_match('/\G"(?:[^"\\\\]|\\\\.)*"/', $content, $m, 0, $pos);
			$pos += strlen($m[0] ?? '');
		} else {
			// skip non-string element (variable, function call, etc.)
			$pos = zz_extract_skip_element($content, $pos);
		}
	}
}

/**
 * Parse a quoted string literal at a byte offset and return its value
 *
 * @param string $content file contents
 * @param int $offset byte offset of the opening quote
 * @return string|null decoded string value, or null if not parseable
 */
function zz_extract_string_at($content, $offset) {
	$char = $content[$offset];
	if ($char === '\'') {
		if (!preg_match('/\G\'((?:[^\'\\\\]|\\\\.)*)\'/', $content, $m, 0, $offset))
			return null;
		return str_replace(["\\\\", "\\'"], ['\\', "'"], $m[1]);
	}
	if ($char === '"') {
		if (!preg_match('/\G"((?:[^"\\\\]|\\\\.)*)"/', $content, $m, 0, $offset))
			return null;
		return stripcslashes($m[1]);
	}
	return null;
}

/**
 * Skip a non-string array element (advances past commas and brackets)
 *
 * @param string $content file contents
 * @param int $offset current byte position
 * @return int byte offset after the element
 */
function zz_extract_skip_element($content, $offset) {
	$length = strlen($content);
	$pos = $offset;
	$depth = 0;

	while ($pos < $length) {
		$char = $content[$pos];
		if ($char === '\'' OR $char === '"') {
			if ($char === '\'')
				preg_match('/\G\'(?:[^\'\\\\]|\\\\.)*\'/', $content, $m, 0, $pos);
			else
				preg_match('/\G"(?:[^"\\\\]|\\\\.)*"/', $content, $m, 0, $pos);
			$pos += strlen($m[0] ?? 1);
			continue;
		}
		if ($char === '[' OR $char === '(') {
			$depth++;
			$pos++;
			continue;
		}
		if ($char === ']' OR $char === ')') {
			if ($depth > 0) {
				$depth--;
				$pos++;
				continue;
			}
			return $pos;
		}
		if ($char === ',' AND $depth === 0) return $pos;
		$pos++;
	}
	return $pos;
}

/**
 * Log a warning when a translatable key uses wrap_text() in definition
 *
 * @param string $relative_path path relative to package folder
 * @param string $key the field key name
 * @param string $content file contents
 * @param int $offset byte offset of the wrap_text call
 * @return void
 */
function zz_extract_warn_wrap_text($relative_path, $key, $content, $offset) {
	$line = wrap_extract_line_number($content, $offset);
	wrap_extract_warning(
		$relative_path, $line,
		wrap_text('translatable key "%s" uses wrap_text()', ['values' => $key])
	);
}

/**
 * Translatable $zz['fields'][n] keys from zz-fields.cfg
 *
 * @return array list of key names with translate = 1
 */
function zz_extract_translatable_field_keys() {
	static $keys = null;
	if ($keys !== null) return $keys;

	$keys = [];
	$cfg_files = wrap_collect_files('configuration/zz-fields.cfg', 'zzform');
	if (!$cfg_files) return $keys;
	$cfg_file = reset($cfg_files);

	$content = file_get_contents($cfg_file);
	$keys = zz_extract_cfg_translate_keys($content);
	return $keys;
}

/**
 * Translatable $zz top-level keys from zz.cfg
 *
 * @return array list of key names with translate = 1
 */
function zz_extract_translatable_zz_keys() {
	static $keys = null;
	if ($keys !== null) return $keys;

	$keys = [];
	$cfg_files = wrap_collect_files('configuration/zz.cfg', 'zzform');
	if (!$cfg_files) return $keys;
	$cfg_file = reset($cfg_files);
	
	$content = file_get_contents($cfg_file);
	$keys = zz_extract_cfg_translate_keys($content);
	return $keys;
}

/**
 * Parse a .cfg file to find section names with translate = 1
 *
 * @param string $content .cfg file contents
 * @return array list of key names (section headers without brackets/quotes)
 */
function zz_extract_cfg_translate_keys($content) {
	$keys = [];
	$current_section = null;

	foreach (explode("\n", $content) as $line) {
		$line = trim($line);
		if ($line === '' OR $line[0] === ';') continue;

		if (preg_match('/^\["?([^"\]]+)"?\]$/', $line, $match)) {
			$current_section = $match[1];
			continue;
		}
		if ($current_section === null) continue;

		if (preg_match('/^translate\s*=\s*1\s*$/', $line)) {
			$keys[] = $current_section;
			$current_section = null;
		}
	}
	return $keys;
}
