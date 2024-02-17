<?php 

/**
 * zzform
 * functions that are always available
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * call zzform() once or multiple times without user interaction 
 * 
 * If zzform_multi() is called from within zzform() (e. g. via an action before/
 * after-script), not all values in $zz_conf will be reset to default. Therefore,
 * it is important that the script used for zzform_multi() will set all variables
 * as it needs them.
 * A solution might be for you to run zz_initialize() before setting the record
 * specific variables and calling zzform()
 *
 * @param string $definition_file - script filename
 * @param array $values - values sent to script (instead of $_GET, $_POST and $_FILES)
 *		array	'POST'
 *		array	'GET'
 *		array	'FILES'
 *		string	'action' => 'POST'['zz_action']: insert, delete, update
 *		array	'ids' => List of select-field names that get direct input of an id 
 * @param string $type (optional)
 * @return array $ops
 * @todo do not unset superglobals
 * @todo so far, we have no support for 'values' for subrecords
 * @todo zzform() and zzform_multi() called within an action-script
 * causes not all zz_conf variables to be reset
 */
function zzform_multi($definition_file, $values, $type = 'tables') {
	// unset all variables that are not needed
	// important because there may be multiple zzform calls
	global $zz_conf;
	require_once __DIR__.'/zzform.php';
	
	$old = [
		'conf' => $zz_conf,
		'GET' => $_GET ?? [],
		'POST' => $_POST ?? [],
		'FILES' => $_FILES ?? []
	];
	unset($_GET);
	unset($_POST);
	unset($_FILES);

	// debug, note: this will only start from the second time, zzform_multi()
	// has been called! (modules debug is not set beforehands)
	if (wrap_setting('debug') AND function_exists('zz_debug') AND !empty($zz_conf['id'])) {
		$id = $zz_conf['id'];
		zz_debug('start', __FUNCTION__);
	}
	$ops = [];
	$ops['result'] = '';
	$ops['id'] = 0;
	// keep internal variables
	$int = $zz_conf['int'] ?? [];

	zz_initialize('overwrite');
	unset($zz_conf['if']);
	unset($zz_conf['unless']);
	$zz_conf['generate_output'] = false;
	// set 'multi' so we know the operation mode for other scripts
	$zz_conf['multi'] = true;
	if (!empty($values['GET'])) $_GET = $values['GET'];
	if (!empty($values['POST'])) $_POST = $values['POST'];
	// add some shortcuts easier to remember
	if (!empty($values['action'])) {
		$_POST['zz_action'] = $values['action'];
	}
	if (!empty($values['ids'])) {
		foreach ($values['ids'] as $field_name) {
			$_POST['zz_check_select'][] = $field_name;
		}
	}

	if (!empty($values['FILES'])) $_FILES = $values['FILES'];
	else $_FILES = [];

	if (wrap_setting('debug') AND function_exists('zz_debug') AND !empty($id)) {
		$old['id'] = $zz_conf['id'];	
		$zz_conf['id'] = zz_check_id_value($id);
		zz_debug('find definition file', $definition_file);
	}
	$zz = zzform_include($definition_file, $values, $type);
	wrap_setting('log_username_default', wrap_setting('request_uri'));
	if (wrap_setting('debug') AND function_exists('zz_debug') AND !empty($id)) {
		zz_debug('got definition file');
		$zz_conf['id'] = zz_check_id_value($old['id']);
	}
	// return on error in form script
	if (!empty($ops['error'])) return $ops;
	$ops = zzform($zz);

	// clean up
	zz_initialize('old_conf', $old['conf']);
	$_GET = $old['GET'];
	$_POST = $old['POST'];
	$_FILES = $old['FILES'];

	$zz_conf['int'] = $int;
	if (wrap_setting('debug') AND function_exists('zz_debug') AND !empty($id)) {
		$zz_conf['id'] = zz_check_id_value($id);
		zz_debug('end');
	}
	return $ops;
}

/**
 * include $zz- or $zz_sub-table definition and accept changes for $zz_conf
 * all other local variables will be ignored
 * include zzform/forms.inc.php or zzform/tables.inc.php, too
 *
 * @param string $definition_file filename of table definition
 * @param array $values (optional) values which might be used in table definition
 * @param string $type 'table', 'form' or 'integrity-check'
 * @param array $brick (optional, if called from zzbrick/forms)
 * @global array $zz_conf
 * @return array
 */
function zzform_include($file, $values = [], $type = 'tables', $brick = []) {
	if ($type === 'integrity-check') {
		$zz_conf = zzform_not_global();
		$type = 'tables';
	} else {
		global $zz_conf;
	}
	if (str_starts_with($file, '../zzbrick_forms/')) {
		$file = substr($file, 17);
		$type = 'forms';
		wrap_error('calling zzform_include() with `../zzbrick_forms/` is deprecated', E_USER_DEPRECATED);
	}

	if (!in_array($type, ['tables', 'forms']))
		wrap_error(sprintf('%s is not a possible type for %s().', ucfirst($type), __FUNCTION__), E_USER_ERROR);
	
	$path = 'zzbrick_%s/%s.php';
	$files = wrap_collect_files(sprintf($path, $type, $file));
	if (!$files and strstr($file, '/')) {
		$parts = explode('/', $file);
		$files = wrap_collect_files(sprintf($path, $type, $parts[1]), $parts[0]);
	}
	if (!$files)
		if ($brick['parameter']) wrap_quit(404); // looking with *
		else wrap_error(sprintf('%s definition for %s: file is missing.', ucfirst($type), $file), E_USER_ERROR);

	// allow to use script without recursion
	$backtrace = debug_backtrace();
	foreach ($backtrace as $step) {
		if (!array_key_exists('file', $step)) continue;
		if (!in_array($step['file'], $files)) continue;
		$key = array_search($step['file'], $files);
		unset($files[$key]);
	}
	if (!$files)
		wrap_error(sprintf('%s definition for %s: file is missing.', ucfirst($type), $file), E_USER_ERROR);

	if (key($files) === 'default' AND count($files) > 1)
		array_shift($files);

	if (key($files) === 'default') {
		if (!$default_tables = wrap_setting('default_tables')) return [];
		if (is_array($default_tables) AND !in_array($file, $default_tables)) return [];
	}
	if ($package = key($files) AND $package !== 'custom')
		wrap_package_activate($package);

	$path = 'zzform/%s.inc.php';
	$common_files = wrap_include_files(sprintf($path, $type, $file));
	$definition = reset($files);
	
	global $zz_conf;
	include $definition;

	$def = $zz ?? $zz_sub ?? [];
	if (!$def)
		wrap_error(sprintf('No %s definition in file %s found.', $type, $file), E_USER_ERROR);
	return $def;
}

/**
 * get read only $zz_conf
 *
 * @return array
 */
function zzform_not_global() {
	global $zz_conf;
	return $zz_conf;
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

/**
 * read or write secret_key connected to zzform ID
 *
 * @param string $mode ('read', 'write', 'timecheck')
 * @param string $id
 * @param string $hash
 */
function zz_secret_id($mode, $id = '', $hash = '') {
	global $zz_conf;
	if (!empty($zz_conf['multi'])) return;

	$logfile = wrap_setting('log_dir').'/zzform-ids.log';
	if (!file_exists($logfile)) touch($logfile);
	$logs = file($logfile);
	if (!$id) $id = $zz_conf['id'];

	$now = time();
	// keep IDs for a maximum of one day
	$keep_max = $now - 60 * 60 * 24;
	$found = '';
	$timestamp = 0;
	$delete_lines = [];
	foreach ($logs as $index => $line) {
		// 0 = timestamp, 1 = zz_id, 2 = secret
		$file = explode(' ', trim($line));
		if ($file[0] < $keep_max) $delete_lines[] = $index;
		if ($file[1] !== $id) continue;
		$found = $file[2];
		$timestamp = $file[0];
		break;
	}
	if ($delete_lines) {
		require_once wrap_setting('core').'/file.inc.php';
		wrap_file_delete_line($logfile, $delete_lines);
	}

	if ($mode === 'read') return $found;
	elseif ($mode === 'timecheck') return $now - $timestamp;
	if ($found) return;
	error_log(sprintf("%s %s %s\n", $now, $id, $hash), 3, $logfile);
}
