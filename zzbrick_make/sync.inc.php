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

	$data = wrap_cfg_files('sync');
	foreach (array_keys($data) as $identifier)
		$data[$identifier]['identifier'] = $identifier;

	if (empty($params[0]) OR !array_key_exists($params[0], $data)) {
		$data = array_values($data);
		if (!array_key_exists($params[0], $data)) {
			foreach (array_keys($data) as $index)
				$data[$index]['sync_inexistent'] = true;
			$data['sync_inexistent'] = true;
			$data['identifier'] = $params[0];
			$page['status'] = 404;
		}
		$page['text'] = wrap_template('sync-overview', $data);
		return $page;
	}

	wrap_include('sync', 'zzform');
	wrap_include('zzform/definition');
	$page = zz_sync($data[$params[0]]);
	$page['breadcrumbs'][]['title'] = $data[$params[0]]['title'];
	return $page;
}
