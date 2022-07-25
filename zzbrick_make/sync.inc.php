<?php 

/**
 * zzform module
 * sync data
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * sync data
 *
 * @param array $params
 * @return array
 */
function mod_zzform_make_sync($params) {
	global $zz_setting;
	global $zz_conf;
	$zz_conf['user'] = 'Sync';
	
	$queries = [];
	$files = wrap_collect_files('configuration/sync.sql');
	foreach ($files as $file)
		$queries = array_merge_recursive($queries, wrap_sql_file($file, '_'));
	foreach (array_keys($queries) as $identifier)
		$queries[$identifier] = wrap_system_sql_placeholders($queries[$identifier]);

	$data = wrap_cfg_files('sync');
	foreach (array_keys($data) as $identifier) {
		$data[$identifier] += $queries[$identifier];
		$data[$identifier]['identifier'] = $identifier;
	}

	if (empty($params[0])) {
		$data = array_values($data);
		$page['text'] = wrap_template('sync-overview', $data);
		return $page;
	} 

	require $zz_conf['dir'].'/sync.inc.php';
	$page = zz_sync($data[$params[0]]);
	$page['breadcrumbs'][] = $data[$params[0]]['title'];
	return $page;
}
