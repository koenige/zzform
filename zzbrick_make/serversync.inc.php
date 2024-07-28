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
	wrap_quit(403, wrap_text(
		'This URL is for synchronising a production and a development server only. No direct access is possible.'
	));
}

/**
 * Synchronisation on development server
 *
 * @return array
 */
function mod_zzform_make_serversync_development() {
	wrap_access_quit('default_maintenance');
	wrap_include('logging', 'zzform');
	$page['title'] = wrap_text('Synchronize local and remote server');
	wrap_setting('log_username_default', wrap_setting('sync_user'));
	
	if (!wrap_setting('local_access')) {
		$out['local_only'] = true;
		$page['text'] = wrap_template('sync-server', $out);
		return $page;
	}

	$path = wrap_path('zzform_sync_server');
	$url = sprintf('https://%s%s', substr(wrap_setting('hostname'), 0, -6), $path);
	$data = ['return_last_logging_entry' => 1];
	$headers_to_send[] = 'Accept: application/json';
	list($status, $headers, $content) = wrap_get_protected_url($url, $headers_to_send, 'POST', $data);
	
	if ($status !== 200) {
		$out['status_error'] = $status;
		$content = json_decode($content, true);
		$out['error_explanation'] = $content['error_explanation'] ?? '';
		$page['text'] = wrap_template('sync-server', $out);
		return $page;
	}
	$last_log = json_decode($content, true);
	$last_log_local = zz_logging_last();
	if ($last_log === $last_log_local) {
		$out['identical'] = wrap_number($last_log['log_id']);
	} elseif ($last_log['log_id'] < $last_log_local['log_id']) {
		// push data from local server
		list($log, $limit) = zz_logging_read($last_log['log_id'] + 1);
		$data = [];
		$data['add_log'] = json_encode($log, true);
		list($status, $headers, $content) = wrap_get_protected_url($url, $headers_to_send, 'POST', $data);
		if ($status !== 200) {
			$out['status_error'] = $status;
			$content = json_decode($content, true);
			$out['error_explanation'] = $content['error_explanation'] ?? '';
			$page['text'] = wrap_template('sync-server', $out);
			return $page;
		}
		$out = json_decode($content, true);
		$out['hide_upload_form'] = true;
		$out['remote_changes'] = true;
		$page['text'] = wrap_template('logging-add', $out);
		return $page;
	} elseif ($last_log['log_id'] > $last_log_local['log_id']) {
		// get data from remote server
		$url .= sprintf('?get_log_from_id=%d', $last_log_local['log_id'] + 1);
		list($status, $headers, $content) = wrap_get_protected_url($url, $headers_to_send);
		if ($status !== 200) {
			$out['status_error'] = $status;
			$content = json_decode($content, true);
			$out['error_explanation'] = $content['error_explanation'] ?? '';
			$page['text'] = wrap_template('sync-server', $out);
			return $page;
		}
		$out = zz_logging_add($content);
		$out['hide_upload_form'] = true;
		$out['local_changes'] = true;
		$page['text'] = wrap_template('logging-add', $out);
		return $page;
	} else {
		$out['mismatch'] = $last_log['log_id'];
	}
	$page['text'] = wrap_template('sync-server', $out);
	return $page;
}
