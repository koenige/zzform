<?php 

/**
 * zzform module
 * show format, link to help text if exists
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * show format, link to help text if exists
 * 
 * examples: 
 * 		%%% show format_link Name of the help text %%% 
 * @param array $params
 * @return array
 */
function mod_zzform_show_format_link($params, $settings) {
	if (!$params) return [];
	if (!wrap_path('default_help', [], ['testing' => true])) return [];

	$filename = strtolower(implode('-', $params));
	$filename = str_replace('_', '-', $filename);

	wrap_include('request', 'zzbrick');
	$data = brick_request_data('help', [$filename]);
	if (!$data)
		$data = ['title' => ucfirst(implode(' ', $params))];

	$page['text'] = wrap_template('format-link', $data);
	return $page;
}
