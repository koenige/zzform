<?php 

/**
 * zzform
 * functions that are always available
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
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
 * @return array $ops
 * @todo do not unset superglobals
 * @todo so far, we have no support for 'values' for subrecords
 * @todo zzform() and zzform_multi() called within an action-script
 * causes not all zz_conf variables to be reset
 */
function zzform_multi($definition_file, $values) {
	// unset all variables that are not needed
	// important because there may be multiple zzform calls
	global $zz_conf;
	global $zz_setting;
	require_once $zz_conf['dir'].'/zzform.php';
	
	$old = [
		'conf' => $zz_conf,
		'GET' => !empty($_GET) ? $_GET : [],
		'POST' => !empty($_POST) ? $_POST : [],
		'FILES' => !empty($_FILES) ? $_FILES : []
	];
	unset($_GET);
	unset($_POST);
	unset($_FILES);

	// debug, note: this will only start from the second time, zzform_multi()
	// has been called! (modules debug is not set beforehands)
	if (!empty($zz_conf['modules']['debug']) AND !empty($zz_conf['id'])) {
		$id = $zz_conf['id'];
		zz_debug('start', __FUNCTION__);
	}
	$ops = [];
	$ops['result'] = '';
	$ops['id'] = 0;
	// keep internal variables
	$int = !empty($zz_conf['int']) ? $zz_conf['int'] : [];

	zz_initialize('overwrite');
	unset($zz_conf['if']);
	unset($zz_conf['unless']);
	$zz_conf['generate_output'] = false;
	// do not show output as it will be included after page head
	$zz_conf['show_output'] = false;
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

	if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
		$old['id'] = $zz_conf['id'];	
		$zz_conf['id'] = zz_check_id_value($id);
		zz_debug('find definition file', $definition_file);
	}
	$zz = zzform_include_table($definition_file, $values);
	if (empty($zz_conf['user'])) {
		if (!empty($_SESSION['username'])) $zz_conf['user'] = $_SESSION['username'];
		else $zz_conf['user'] = $zz_setting['request_uri'];
	}
	if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
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
	if (!empty($zz_conf['modules']['debug']) AND !empty($id)) {
		$zz_conf['id'] = zz_check_id_value($id);
		zz_debug('end');
	}
	return $ops;
}
