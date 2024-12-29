<?php 

/**
 * default module
 * export SQL log as JSON file
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/default
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * export SQL log as JSON file
 *
 * @param array $params
 * @return array
 */
function mod_zzform_loggingread($params) {
	wrap_include('logging', 'zzform');
	list($data, $limit) = zz_logging_read($params[0]);
	if (!$data) {
		$sql = 'SELECT MAX(log_id) FROM /*_TABLE zzform_logging _*/';
		$data['max_logs'] = wrap_db_fetch($sql, '', 'single value');
		$page['title'] = ' '.wrap_text('Download SQL log');
		$page['breadcrumbs'][]['title'] = wrap_text('Download SQL log');
		$page['text'] = wrap_template('logging-read', $data);
		return $page;
	}

	$page['text'] = json_encode($data);
	$page['query_strings'][] = 'loggingread'; // @todo change this, here for call from maintenance script
	$page['content_type'] = 'json';
	if ($limit) {
		$page['headers']['filename'] = sprintf('logging_%d-%d.json', $params[0], $params[0] + $limit - 1);
	} else {
		$page['headers']['filename'] = sprintf('logging_%d.json', $params[0]);
	}
	return $page;
}
