<?php 

/**
 * zzform
 * Session functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * write a session for a part of the program to disk
 * php session is not locked, so race conditions might occur
 *
 * @param string $type name of the part of the program
 * @param array $session data to write
 * @return bool
 */
function zz_session_write($type, $data) {
	switch ($type) {
	case 'files':
		// $data = $zz_tab
		$session = [];
		$session['upload_cleanup_files'] = zz_upload_cleanup_files();
		foreach ($data[0]['upload_fields'] as $uf) {
			$tab = $uf['tab'];
			$rec = $uf['rec'];
			if (empty($data[$tab][$rec]['images'])) continue;
			if (isset($data[$tab][$rec]['file_upload'])) {
				$session[$tab][$rec]['file_upload'] = $data[$tab][$rec]['file_upload'];
			} else {
				$session[$tab][$rec]['file_upload'] = false;
			} 
			$session[$tab][$rec]['images'] = $data[$tab][$rec]['images'];
		}
		break;
	case 'filedata':
		// $data = $_FILES
		require_once __DIR__.'/upload.inc.php';
		$session = $data;
		foreach ($session AS $field_name => $file) {
			if (is_array($file['tmp_name'])) {
				foreach ($file['tmp_name'] as $field_key => $filename) {
					if (!$filename) continue;
					if (!is_uploaded_file($filename)) continue; // might have been already moved
					if ($file['error'][$field_key] !== UPLOAD_ERR_OK) continue;
					$new_filename = wrap_setting('tmp_dir').'/zzform-sessions/'.basename($filename);
					zz_rename($filename, $new_filename);
					$session[$field_name]['tmp_name'][$field_key] = $new_filename;
				}
			} else {
				if (!$file['tmp_name']) continue;
				if (!is_uploaded_file($file['tmp_name'])) continue; // might have been already moved
				if ($file['error'] !== UPLOAD_ERR_OK) continue;
				$new_filename = wrap_setting('tmp_dir').'/zzform-sessions/'.basename($file['tmp_name']);
				zz_rename($filename, $new_filename);
				$session[$field_name]['tmp_name'] = $new_filename;
			}
		}
		break;
	case 'postdata':
		// $data = $_POST
		$session = $data;
		break;
	}
	$fp = fopen(zz_session_filename($type), 'w');
	fwrite($fp, json_encode($session, JSON_PRETTY_PRINT));
	fclose($fp);
	return true;
}

/**
 * read a session for a part of the program from disk
 *
 * @param string $type name of the part of the program
 * @param array $data default empty, if data: check if something else was posted
 * @return array
 */
function zz_session_read($type, $data = []) {
	$filename = zz_session_filename($type);
	if (!file_exists($filename)) return $data;
	$session = file_get_contents($filename);
	$session = json_decode($session, true);
	unlink($filename);
	if (!$session) return $data;
	if (!$data) return $session;

	if ($type !== 'filedata') {
		wrap_error(sprintf('Merging session data is not supported for type `%s`.', $type));
		return $session;
	}
	foreach ($data as $field_name => $files) {
		if (is_array($files['error'])) {
			foreach ($files['error'] as $field_key => $error) {
				// new data has nothing to show: take old data
				if ($error === UPLOAD_ERR_NO_FILE) continue;
				// take new data, remove old saved file
				if (array_key_exists($field_name, $session)) {
					$previous_upload = $session[$field_name]['tmp_name'][$field_key];
					if (file_exists($previous_upload)) unlink($previous_upload);
				}
				foreach ($files as $key => $values) {
					$session[$field_name][$key][$field_key] = $values[$field_key];
				}
			}
		} else {
			// new data has nothing to show: take old data
			if ($files['error'] === UPLOAD_ERR_NO_FILE) continue;
			// take new data, remove old saved file
			$previous_upload = $session[$field_name]['tmp_name'];
			if (file_exists($previous_upload)) unlink($previous_upload);
			$session[$field_name] = $data[$field_name];
		}
	}
	return $session;
}

/**
 * generate a session filename made out of
 * current session ID and script ID
 *
 * @param string $type name of the part of the program
 * @return string
 */
function zz_session_filename($type) {
	global $zz_conf;
	$dir = wrap_setting('tmp_dir').'/zzform-sessions';
	wrap_mkdir($dir);
	$filename = sprintf('%s/%s-%s-%s.txt'
		, $dir
		, (empty(session_id()) OR wrap_setting('zzform_id_from_session')) ? $zz_conf['id'] : session_id()
		, $zz_conf['int']['secret_key']
		, $type
	);
	return $filename;
}

/**
 * save POST and FILES for use after login
 *
 * called via auth functions in zzwrap
 * @param void
 * @return string
 */
function zz_session_via_login() {
	global $zz_conf;
	wrap_package_activate('zzform'); // get _functions

	// this function is called from outside zzform!
	$zz_conf['id'] = zz_check_id_value($_POST['zz_id']);
	$zz_conf['int']['secret_key'] = zz_secret_id('read');

	zz_session_write('postdata', $_POST);
	zz_session_write('filedata', $_FILES); // if files, move files to _tmp dir

	$text = sprintf('<input type="hidden" name="zz_review_via_login" value="%s">'."\n", $zz_conf['id']);
	return $text;
}

/**
 * review a form after being logged out and logged in again
 *
 * return bool
 */
function zz_review_via_login() {
	global $zz_conf;

	$zz_conf['id'] = zz_check_id_value($_SESSION['zzform']['review_via_login']);
	$zz_conf['int']['secret_key'] = zz_secret_id('read');

	wrap_setting('zzform_id_from_session', true);
	$_POST = zz_session_read('postdata');
	$_FILES = zz_session_read('filedata');
	wrap_setting('zzform_id_from_session', false);
	
	wrap_session_start();
	unset($_SESSION['zzform']['review_via_login']);

	if (empty($_POST['zz_action'])) return false;
	if ($_POST['zz_action'] !== 'delete') return true;

	$_SESSION['zzform']['delete_via_login'] = true;
	unset($_POST['zz_id']);
	unset($_POST['zz_action']);
	$_GET['delete'] = array_shift($_POST);
	$uri = wrap_setting('request_uri');
	if (strstr($uri, '?')) $uri .= '&';
	else $uri .= '?';
	$uri .= sprintf('delete=%d', $_GET['delete']);
	return wrap_redirect($uri, 301, false);
}
