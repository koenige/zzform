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
	foreach (array_keys($data) as $identifier) {
		$data[$identifier] += zz_sync_queries($identifier);
		$data[$identifier]['identifier'] = $identifier;
	}

	if (empty($params[0])) {
		$data = array_values($data);
		$page['text'] = wrap_template('sync-overview', $data);
		return $page;
	}

	wrap_include('sync', 'zzform');
	wrap_include('zzform/definition');
	$page = zz_sync($data[$params[0]]);
	$page['breadcrumbs'][]['title'] = $data[$params[0]]['title'];
	return $page;
}

/**
 * read data from sync.sql
 *
 * @param string $identifier
 * @return array
 */
function zz_sync_queries($identifier) {
	$queries = [];
	$files = wrap_collect_files('configuration/sync.sql');
	foreach ($files as $file)
		$queries = array_merge_recursive($queries, wrap_sql_file($file, '_'));

	if (!array_key_exists($identifier, $queries)) return [];
	$queries = $queries[$identifier];

	// get ids
	$ids = [];
	foreach ($queries as $key => $query) {
		if (!str_starts_with($key, 'static')) continue;
		if (!strstr($query, ' = /*_ID')) continue;
		list($qkey, $qvalue) = explode(' = ', $query);
		$ids[] = $qkey;
	}
	$queries = wrap_sql_placeholders($queries);
	if ($ids)
		$queries['ids'] = $ids;

	return $queries;
}
