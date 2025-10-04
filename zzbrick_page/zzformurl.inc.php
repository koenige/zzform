<?php 

/**
 * zzform
 * page elements: zzform URL
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/** 
 * zzform URL
 * 
 * @param array $params
 * @param array $page
 * @param array $settings
 * @return string $text
 */
function page_zzformurl(&$params, $page, $settings) {
	$params = [];
	$url = zzform_url($settings['type'] ?? '');
	if (!empty($settings['add'])) {
		$settings['add'] = str_replace('>', '=', $settings['add']);
		parse_str($settings['add'], $settings['add']);
		foreach ($settings['add'] as $key => $value) {
			if ($value !== 'NULL') continue;
			$settings['add'][$key] = NULL;
		}
		$url = zzform_url_add($settings['add'], $url);
	}
	if (!empty($settings['encode']))
		$url = zzform_url_escape($url);
	return $url;
}
