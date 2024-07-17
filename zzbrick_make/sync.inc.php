<?php 

/**
 * zzform module
 * sync data
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * sync data
 *
 * @param array $params
 * @return array
 */
function mod_zzform_make_sync($params) {
	wrap_setting('log_username_suffix', 'Sync');

	$config = wrap_cfg_files('sync');
	foreach (array_keys($config) as $identifier)
		$config[$identifier]['identifier'] = $identifier;

	if (empty($params[0]) OR !array_key_exists($params[0], $config)) {
		$config = array_values($config);
		if (count($params) AND !array_key_exists($params[0], $config)) {
			foreach (array_keys($config) as $index)
				$config[$index]['sync_inexistent'] = true;
			$config['sync_inexistent'] = true;
			$config['identifier'] = $params[0];
			$page['status'] = 404;
		}
		$page['text'] = wrap_template('sync-overview', $config);
		return $page;
	}

	$page['query_strings'] = ['limit'];
	$page['title'] = wrap_text('Synchronization: %s', ['values' => [$config[$params[0]]['title']]]);
	$page['breadcrumbs'][]['title'] = $config[$params[0]]['title'];

	wrap_include('sync', 'zzform');
	wrap_include('zzform/definition');
	$data = zz_sync($config[$params[0]]);
	if (!$data) {
		$page['status'] = 404;
		$page['text'] = '';
		return $page;
	}
	wrap_setting_add('extra_http_headers', 'X-Frame-Options: Deny');
	wrap_setting_add('extra_http_headers', "Content-Security-Policy: frame-ancestors 'self'");

	if (isset($_GET['deletable'])) {
		$page['query_strings'] = ['deletable'];
		$page['text'] = wrap_template('sync-deletable', $data);
		$page['title'] = wrap_text('Deletable Records');
		return $page;
	}

	$page['text'] = wrap_template('sync', $data);
	return $page;
}
