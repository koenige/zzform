<?php 

/**
 * zzform
 * Instance token and definition hash management
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/*
 * --------------------------------------------------------------------
 * Token management
 * --------------------------------------------------------------------
 */

/**
 * create an ID for zzform for internal purposes
 *
 * @param void
 * @global array $zz_conf
 */
function zz_set_id() {
	global $zz_conf;
	if (!empty($zz_conf['id']) AND empty($zz_conf['multi'])) return;
	if (!empty($_GET['zz']) AND strlen($_GET['zz']) === 6) {
		$zz_conf['id'] = zz_check_id_value($_GET['zz']);
	} elseif (!empty($_POST['zz_id']) AND !is_array($_POST['zz_id']) AND strlen($_POST['zz_id']) === 6) {
		$zz_conf['id'] = zz_check_id_value($_POST['zz_id']);
	} else {
		$zz_conf['id'] = wrap_random_hash(6);
	}
	return;
}

/**
 * check if ID is valid (if returned via browser), if invalid: create new ID
 *
 * @param string
 * @return string
 */
function zz_check_id_value($string) {
	if (is_array($string))
		return zz_check_id_value_error();
	for ($i = 0; $i < mb_strlen($string); $i++) {
		$letter = mb_substr($string, $i, 1);
		if (!strstr('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', $letter))
			return zz_check_id_value_error();
	}
	return $string;
}

/**
 * if ID is invalid, create new ID and if it was received via POST, log as error
 *
 * @return string
 */
function zz_check_id_value_error() {
	if (!empty($_POST['zz_id'])) {
		wrap_setting('log_username_suffix', wrap_setting('remote_ip'));
		wrap_error(sprintf('POST data removed because of illegal zz_id value `%s`', json_encode($_POST['zz_id'])), E_USER_NOTICE);
		unset($_POST);
	}
	return wrap_random_hash(6);
}


/*
 * --------------------------------------------------------------------
 * Definition/hash computation
 * --------------------------------------------------------------------
 */

/**
 * get a unique hash for a specific set of table definition ($zz) and
 * configuration ($zz_conf) to be able to save time for zzform_multi() and
 * to get a possible hash for a secret key
 *
 * @param array $zz (optional, for creating a hash)
 * @param array $zz_conf (optional, for creating a hash)
 * @return string $hash
 * @todo check if $_GET['id'], $_GET['where'] and so on need to be included
 */
function zz_hash($zz = [], $zz_conf = []) {
	static $hash = '';
	static $id = '';
	// if zzform ID is known and has changed, re-generate hash
	if ($hash AND empty($zz_conf['id']) OR $zz_conf['id'] === $id) return $hash;

	// get rid of varying and internal settings
	// get rid of configuration settings which are not important for
	// the definition of the database table(s)
	$id = $zz_conf['id'];
	$uninteresting_zz_conf_keys = [
		'int', 'id'
	];
	foreach ($uninteresting_zz_conf_keys as $key) unset($zz_conf[$key]);
	$uninteresting_zz_keys = [
		'title', 'explanation', 'explanation_top', 'subtitle', 'list', 'access',
		'explanation_insert', 'export', 'details', 'footer', 'page', 'setting'
	];
	foreach ($uninteresting_zz_keys as $key) unset($zz[$key]);
	foreach ($zz['fields'] as $no => &$field) {
		// defaults might change, e. g. dates
		zz_hash_remove_defaults($field);
		if (!empty($field['type']) AND in_array($field['type'], ['subtable', 'foreign_table'])) {
			foreach ($field['fields'] as $sub_no => &$sub_field)
				zz_hash_remove_defaults($sub_field);
		}
		// @todo remove if[no][default] too
	}
	$my['zz'] = $zz;
	$my['zz_conf'] = $zz_conf;
	$hash = sha1(serialize($my));
	zz_secret_id('write', $id, $hash);
	return $hash;
}

/**
 * remove default values for hash, might be timestamps etc., to get a definition
 * that does not change
 *
 * @param array $field
 */
function zz_hash_remove_defaults(&$field) {
	if (isset($field['default'])) unset($field['default']);
	$conditions = ['if', 'unless'];
	foreach ($conditions as $condition) {
		if (isset($field[$condition]) AND is_array($field[$condition])) {
			foreach ($field[$condition] as $if_key => $if_settings) {
				if (!is_array($if_settings)) continue;
				if (!array_key_exists('default', $if_settings)) continue;
				unset($field[$condition][$if_key]['default']);
			}
		}
	}
}


/*
 * --------------------------------------------------------------------
 * Hash storage
 * --------------------------------------------------------------------
 */

/**
 * hash a secret key and make it small, store and retrieve it
 *
 * @param int $id (optional) if provided, generate new secret key
 * @param string $action (optional)
 *		'once': generate key without storing (for one-off uses)
 *		'write': write $id as value
 * @return string
 */
function zzform_secret_key($id = NULL, $action = '') {
	static $secret_key = NULL;

	if ($action === 'write')
		return $secret_key = $id;
	
	// Generate new key if ID provided
	if ($id !== NULL) {
		$hash = sha1(zz_hash().$id);
		$hash = wrap_base_convert($hash, 16, 62);
		// return key without storing
		if ($action === 'once') return $hash;
		// store key
		$secret_key = $hash;
	}
	
	return $secret_key;
}


/*
 * --------------------------------------------------------------------
 * Pairing
 * --------------------------------------------------------------------
 */

/**
 * read or write secret_key connected to zzform ID
 *
 * @param string $mode ('read', 'write', 'timecheck')
 * @param string $id
 * @param string $hash
 * @return string
 */
function zz_secret_id($mode, $id = '', $hash = '') {
	global $zz_conf;
	if (!empty($zz_conf['multi'])) return '';
	
	// @deprecated
	if (file_exists(wrap_setting('log_dir').'/zzform-ids.log')) {
		wrap_mkdir(wrap_setting('log_dir').'/zzform');
		rename(wrap_setting('log_dir').'/zzform-ids.log', wrap_setting('log_dir').'/zzform/ids.log');
	}

	if (!$id) $id = $zz_conf['id'];
	$found = '';
	$timestamp = 0;

	wrap_include('file', 'zzwrap');
	$logs = wrap_file_log('zzform/ids');
	foreach ($logs as $index => $line) {
		if ($line['zzform_id'] !== $id) continue;
		$found = $line['zzform_hash'];
		$timestamp = $line['timestamp'];
	}
	if ($mode === 'read') return $found;
	elseif ($mode === 'timecheck') return time() - $timestamp;
	if ($found) return $found;
	if (!empty($_POST)) { // no hash found but POST? resend required, possibly spam
		// but first check if it is because of add_details
		if (empty($_POST['zz_edit_details']) AND empty($_POST['zz_add_details']))
			$zz_conf['int']['resend_form_required'] = true;
	}
	wrap_file_log('zzform/ids', 'write', [time(), $id, $hash]);
}

/**
 * delete secret ID after successful operation
 * to avoid reusing zz_id
 *
 * @return bool
 */
function zz_secret_id_delete() {
	global $zz_conf;
	if (empty($zz_conf['id'])) return false;
	if (!zzform_secret_key()) return false;

	wrap_include('file', 'zzwrap');
	wrap_file_log('zzform/ids', 'delete', [
		'zzform_id' => $zz_conf['id'],
		'zzform_hash' => zzform_secret_key()
	]);
	return true;
}
