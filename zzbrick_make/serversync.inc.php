<?php

/**
 * zzform module
 * Synchronisation of data from development and production server
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2018, 2020-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Synchronisation of data from development and production server
 *
 * @return array
 */
function mod_zzform_make_serversync() {
	return mod_zzform_make_serversync_production();
}

/**
 * Synchronisation on production server
 *
 * @return array
 */
function mod_zzform_make_serversync_production() {
	wrap_access_quit('default_maintenance');
	wrap_include('logging', 'zzform');
	
	if (!empty($_POST['return_last_logging_entry'])) {
		$data = zz_logging_last();
		$page['text'] = json_encode($data, true);
		$page['content_type'] = 'json';
		$page['headers']['filename'] = 'logging_last.json';
		return $page;
	} elseif (!empty($_POST['add_log'])) {
		$out = zz_logging_add($_POST['add_log']);
		$page['text'] = json_encode($out, true);
		$page['content_type'] = 'json';
		$page['headers']['filename'] = 'logging_add.json';
		return $page;
	} elseif (!empty($_GET['get_log_from_id'])) {
		list($log, $limit) = zz_logging_read($_GET['get_log_from_id']);
		$page['text'] = json_encode($log, true);
		$page['query_strings'] = ['get_log_from_id'];
		$page['content_type'] = 'json';
		if ($limit) {
			$page['headers']['filename'] = sprintf('logging_%d-%d.json', $_GET['get_log_from_id'], $_GET['get_log_from_id'] + $limit - 1);
		} else {
			$page['headers']['filename'] = sprintf('logging_%d.json', $_GET['get_log_from_id']);
		}
		return $page;
	}
	wrap_quit(403, wrap_text('This URL is for synchronising a production and a development server only. No direct access is possible.'));
}
