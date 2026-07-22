<?php

/**
 * zzform
 * Export module
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2007-2024, 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * initializes export, sets a few variables
 *
 * @param array $ops
 * @param array $zz
 * @return array $ops
 */
function zz_export_init(&$ops, &$zz) {
	if (empty($_GET['export'])) return;
	if (!$zz['export']) {
		zz_export_remove();
		return;
	}

	// no edit modes allowed
	$unwanted_keys = [
		'mode', 'id', 'source_id', 'show', 'edit', 'add', 'delete', 'insert',
		'update', 'revise'
	];
	foreach ($unwanted_keys as $key) {
		if (!isset($_GET[$key])) continue;
		zzform_url_remove($unwanted_keys);
		zz_error_log([
			'_msg' => 'Please don’t mess with the URL parameters. <code>%s</code> is not allowed here.',
			'_msg_values' => [$key],
			'level' => E_USER_NOTICE,
			'status' => 404
		]);
		zz_export_remove();
		$ops['mode'] = false;
		return;
	}
	// do not export anything if it's a 404 in export mode
	// and e. g. limit is incorrect
	if (wrap_static('zzform_page', 'status') === 404) {
		zz_export_remove();
		$ops['mode'] = false;
		return;
	}

	// check GET parameter for matches
	$export = null;
	foreach ($zz['export'] as $key => $value) {
		$entry = zz_export_entry($key, $value);
		if (!$entry) continue;
		if ($_GET['export'] !== $entry['slug']) continue;
		$export = $entry;
		break;
	}
	if (!$export) {
		zz_error_log([
			'_msg_dev' => 'Export slug not registered: `%s`',
			'_msg_dev_values' => [zz_htmltag_escape($_GET['export'])],
			'level' => E_USER_NOTICE,
			'status' => 404
		]);
		zz_export_remove();
		$ops['mode'] = false;
		return;
	}
	$ops['mode'] = 'export';
	$ops['list']['export_file'] = $export['file'];
	$ops['list']['export_function'] = sprintf('export_%s', str_replace('-', '_', $export['slug']));
	$zz['list']['display'] = $export['filetype'];

	switch ($export['filetype']) {
	case 'kml':
	case 'geojson':
		// always use UTF-8
		wrap_db_charset('utf8');
		// if kml file is called without limit parameter, it does not default
		// to limit=20 but to no limit instead
		if (empty($_GET['limit']))
			wrap_page_limit('remove'); 
		break;
	case 'csv':
	case 'pdf':
	case 'zip':
		// always export all records
		wrap_page_limit('remove');
		break;
	}
}

/**
 * Remove invalid export parameter from request and URLs
 *
 * @return void
 */
function zz_export_remove() {
	unset($_GET['export']);
	zzform_url_remove(['export']);
}

/**
 * HTML output of links for export
 *
 * @param array $export ($zz['export'])
 * @return array $links array of strings with links for export
 */
function zz_export_links($export) {
	$links = [];
	
	// remove some querystrings which have no effect anyways
	$unwanted_querystrings = ['nolist', 'debug', 'referer', 'limit', 'order', 'dir'];
	$qs = zzform_url_remove($unwanted_querystrings, 'extra_get');
	if ($qs) $qs = substr($qs, 1);

	foreach ($export as $key => $value) {
		$entry = zz_export_entry($key, $value);
		if (!$entry) continue;
		$links[] = [
			'slug' => $entry['slug'],
			'qs' => $qs,
			'label' => $entry['label']
		];
	}
	return $links;
}

/**
 * Normalize one $zz['export'] entry
 *
 * Numeric key: slug from label, file under zzform/export-{format}-{script}.
 * String key: slug = key, label = value, file under zzform_export/{slug}.
 *
 * @param int|string $key
 * @param string $label
 * @return array{slug: string, label: string, file: string}
 */
function zz_export_entry($key, $label) {
	$entry = [];

	// get slug
	if (is_numeric($key)) {
		$entry['slug'] = strtolower($label);
		$entry['slug'] = str_replace(' ', '-', $entry['slug']);
	} else {
		$entry['slug'] = $key;
	}

	// get filetype
	// get file
	if ($pos = strpos($entry['slug'], '-')) {
		$entry['filetype'] = substr($entry['slug'], 0, $pos);
	} else {
		$entry['filetype'] = $entry['slug'];
	}
	if (!in_array($entry['filetype'], wrap_setting('zzform_export_formats'))) {
		zz_error_log([
			'_msg_dev' => 'Wrong configuration: The format `%s` of export %s is not in the list of possible formats.',
			'_msg_dev_values' => [$entry['filetype'], $label],
			'level' => E_USER_NOTICE
		]);
		return [];
	}
	if (in_array($entry['filetype'], ['pdf', 'zip'])) {
		if (is_numeric($key))
			$entry['file'] = sprintf('zzform/export-%s', $entry['slug']);
		else
			$entry['file'] = sprintf('zzform_export/%s', $entry['slug']);
	} else {
		$entry['file'] = sprintf('zzform_export/%s', $entry['filetype']);
	}

	// get label
	if (is_numeric($key)) {
		$entry['label'] = $label;
	} else {
		$entry['label'] = sprintf('%s %s', strtoupper($entry['filetype']), $label);
	}

	return $entry;
}

/**
 * export data
 *
 * @param array $ops
 *		$ops['headers'] = HTTP headers which might be used for sending PDF to browser
 *		$ops['output']['head'] = Table definition, each field has an index
 *		$ops['output']['rows'] = Table data, lines 0...n, each line has fields
 *			with numerical index corresponding to 'head', each field is array
 *			made of 'class' (= HTML attribute values) and 'text' (= content)
 * @param array $zz
 * @param array $list
 * @return mixed void (direct output) or array $ops
 */
function zz_export($ops, $zz, $list) {
	$files = wrap_include($list['export_file'], 'custom/active/zzform');
	if (!$files)
		wrap_quit(503, wrap_text(
			'A file is missing for `%s` export. Please create a script at `%s`.',
			['values' => [$list['export_function'], $list['export_file'].'.inc.php']]
		));
	$export_function = null;
	foreach ($files['functions'] as $function) {
		if (empty($function['short'])) continue;
		if ($function['short'] !== $list['export_function']) continue;
		$export_function = $function['function'];
		break;
	}
	if (!$export_function)
		wrap_quit(503, wrap_text(
			'A function is missing for `%s` export in the script at `%s`.',
			['values' => [$list['export_function'], $list['export_file'].'.inc.php']]
		));
	return call_user_func($export_function, $ops);
}

/**
 * return a filename for sending, derived from title
 *
 * @param string $title
 * @return string
 */
function zz_export_filename($title, $extension) {
	$filename = wrap_filename($title, " ", [':' => ' ', '.' => ' ', '–' => '-']);
	if (!empty($_GET['q'])) $filename .= ' '.wrap_filename($_GET['q']);
	return sprintf('%s.%s', $filename, $extension);
}
