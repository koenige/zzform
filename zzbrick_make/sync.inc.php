<?php 

/**
 * zzform
 * sync data
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2025 Gustaf Mossakowski
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
	wrap_include('zzform/helpers');
	if (!isset($_GET['finish'])) {
		$data = zz_sync($config[$params[0]]);
		if (!$data) {
			$page['status'] = 404;
			$page['text'] = '';
			$page['extra']['job'] = 'fail';
			return $page;
		}
	}
	wrap_setting_add('extra_http_headers', 'X-Frame-Options: Deny');
	wrap_setting_add('extra_http_headers', "Content-Security-Policy: frame-ancestors 'self'");

	$page['extra']['job'] = 'sync';
	$url_self = parse_url(wrap_setting('request_uri'), PHP_URL_PATH);
	$url_self = sprintf('%s%s', wrap_setting('host_base'), $url_self);
	if (isset($_GET['deletable'])) {
		$page['data'] = [
			'deleted' => $data['deleted'] ?? 0,
			'next_url' => $url_self.'?finish'
		];
		$page['query_strings'] = ['deletable'];
		$page['title'] = wrap_text('Deletable Records');
		$page['text'] = wrap_template('sync-deletable', $data);
	} elseif (isset($_GET['finish'])) {
		$page['data'] = [
			'finished' => 1,
			'next_url' => ''
		];
		$page['query_strings'] = ['finish'];
		$page['title'] = wrap_text('Sync finished');
		$data['finished'] = true;
		$page['text'] = wrap_template('sync', $data);
	} else {
		$page['data'] = [
			'updated' => $data['updated'],
			'inserted' => $data['inserted'],
			'nothing' => $data['nothing'],
			'next_url' => $url_self.(!empty($data['last']) ? '?deletable' : sprintf('?limit=%d', $data['end']))
		];
		$page['text'] = wrap_template('sync', $data);
	}
	return $page;
}
