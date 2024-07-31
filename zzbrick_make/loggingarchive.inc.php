<?php 

/**
 * zzform module
 * archive logging data
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * archive logging data in JSON foles
 *
 * @param array $page
 * @return array $page
 */
function mod_zzform_make_loggingarchive() {
	wrap_include('logging', 'zzform');

	$data = zz_logging_oldest_month();
	if (!$data['oldest_month'])
		$data['data_unavailable'] = true;
	elseif ($data['oldest_month'] >= $data['keep_month'])
		$data['no_archive_data'] = true;
	elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$archived = mod_zzform_make_loggingarchive_go($data['oldest_month']);
		if ($archived) wrap_redirect(sprintf('?archived=%d&month=%d', $archived, $data['oldest_month']));
	}
	if (!empty($_GET['archived']) AND !empty($_GET['month'])) {
		$data['archived'] = intval($_GET['archived']);
		$data['month'] = intval($_GET['month']);
	}

	$page['query_strings'][] = 'archived';
	$page['query_strings'][] = 'month';
	$page['text'] = wrap_template('logging-archive', $data);
	return $page;
}

/**
 * archive logging data of a certain month
 *
 * @param int $month
 * @return int
 */
function mod_zzform_make_loggingarchive_go($month) {
	$log = zz_logging_read($month, 'month');
	
	$success = zz_logging_save($month, $log[0]);
	if (!$success) return 0;
	
	$records = zz_logging_delete_month($month, $log[1]);
	return $records['rows'];
}
