<?php 

/**
 * zzform
 * add logging data
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * export JSON file in _logging
 *
 * @param array $page
 * @return array $page
 */
function mod_zzform_make_loggingadd() {
	$data = [];
	if (empty($_FILES['sqlfile'])) $data['no_file'] = true;
	elseif ($_FILES['sqlfile']['error'] === UPLOAD_ERR_NO_FILE) $data['no_file'] = true;
	elseif ($_FILES['sqlfile']['error'] !== 0) $data['file_error'] = true;
	elseif ($_FILES['sqlfile']['size'] <= 3) $data['file_error'] = true;
	else {
		wrap_include('logging', 'zzform');
		$json = file_get_contents($_FILES['sqlfile']['tmp_name']);
		$data = zz_logging_add($json);
	}
	$page['title'] = wrap_text('Upload SQL log');
	$page['breadcrumbs'][]['title'] = wrap_text('Upload SQL log');
	$page['text'] = wrap_template('logging-add', $data);
	return $page;
}
