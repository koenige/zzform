<?php 

/**
 * zzform
 * State management: tokens, definition hashing, and anti-replay protection
 *
 * This module manages the state of form instances through:
 * - State tokens: Random 6-character identifiers for each form interaction
 * - Definition hashing: Structural fingerprints of form definitions
 * - Token-hash pairing: Anti-replay attack protection
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
 * Get, set, or initialize the state token for this zzform instance
 * Returns the 6-character random token that identifies this form interaction
 *
 * Usage:
 * - zz_state_token()           Get current token (auto-initializes if needed)
 * - zz_state_token('abc123')   Set specific token (validates automatically)
 * - zz_state_token('generate') Force generate new random token
 *
 * If token is not yet set, auto-initializes by:
 * - Checking GET parameter 'zz' for existing token
 * - Checking POST parameter 'zz_token' for existing token
 * - Generating a new random token if none provided
 *
 * @param string $token (optional) token to set, or 'generate' for new random token
 * @return string current state token (always 6 characters after first call)
 */
function zz_state_token($token = NULL) {
	static $state_token = '';
	
	// Setter: force generate new random token
	if ($token === 'generate') {
		$state_token = wrap_random_hash(6);
		return $state_token;
	}
	
	// Setter: set specific token (with validation)
	if ($token !== NULL) {
		$state_token = zz_state_token_validate($token);
		return $state_token;
	}
	
	// Getter: return existing token if set
	if ($state_token) return $state_token;
	
	// Auto-initialize if not set
	if (!empty($_GET['zz']) AND strlen($_GET['zz']) === 6) {
		$state_token = zz_state_token_validate($_GET['zz']);
	} elseif (!empty($_POST['zz_token']) AND !is_array($_POST['zz_token']) AND strlen($_POST['zz_token']) === 6) {
		$state_token = zz_state_token_validate($_POST['zz_token']);
	} else {
		$state_token = wrap_random_hash(6);
	}
	
	return $state_token;
}

/**
 * Validate state token format (must be 6 alphanumeric characters)
 * If invalid, generates a new token and logs security error
 *
 * @param string $string token to validate
 * @return string validated token or new token if validation failed
 */
function zz_state_token_validate($string) {
	if (is_array($string))
		return zz_state_token_validate_error();
	for ($i = 0; $i < mb_strlen($string); $i++) {
		$letter = mb_substr($string, $i, 1);
		if (!strstr('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', $letter))
			return zz_state_token_validate_error();
	}
	return $string;
}

/**
 * Handle invalid state token: log security error, clear POST data, generate new token
 * If token was received via POST, this indicates a potential security issue
 *
 * @return string new random token
 */
function zz_state_token_validate_error() {
	if (!empty($_POST['zz_token'])) {
		wrap_setting('log_username_suffix', wrap_setting('remote_ip'));
		wrap_error(sprintf('POST data removed because of illegal zz_token value `%s`', json_encode($_POST['zz_token'])), E_USER_NOTICE);
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
 * Compute a hash of the form definition (structure only, not content)
 * Creates a SHA1 hash representing the essential structure of the form
 * Excludes cosmetic elements (titles, explanations) and volatile data (defaults, timestamps)
 *
 * This hash is used for:
 * - Form integrity verification
 * - Detecting structural changes
 * - Anti-replay attack protection when paired with token
 *
 * @param array $zz (optional) form definition array
 * @return string SHA1 hash of the form's structural definition
 * @todo check if $_GET['id'], $_GET['where'] and so on need to be included
 */
function zz_state_definition($zz = []) {
	static $hashes = [];
	$token = zz_state_token();
	if (array_key_exists($token, $hashes))
		return $hashes[$token];
	if (!$zz) {
		wrap_error(sprintf('Unable to find hash for token %s', $token), E_USER_WARNING);
		return '';
	}
	
	// get rid of varying and internal settings
	// get rid of configuration settings which are not important for
	// the definition of the database table(s)
	$uninteresting_zz_keys = [
		'title', 'explanation', 'explanation_top', 'subtitle', 'list', 'access',
		'explanation_insert', 'export', 'details', 'footer', 'page', 'setting'
	];
	foreach ($uninteresting_zz_keys as $key) unset($zz[$key]);
	foreach ($zz['fields'] as $no => &$field) {
		// defaults might change, e. g. dates
		zz_state_definition_remove_defaults($field);
		if (!empty($field['type']) AND in_array($field['type'], ['subtable', 'foreign_table'])) {
			foreach ($field['fields'] as $sub_no => &$sub_field)
				zz_state_definition_remove_defaults($sub_field);
		}
		// @todo remove if[no][default] too
	}
	$hashes[$token] = sha1(serialize($zz));
	zz_state_pairing('write', $token, $hashes[$token]);
	return $hashes[$token];
}

/**
 * Remove volatile default values from field definition before hashing
 * Defaults may contain timestamps or dynamic values that change between requests
 * Removing them ensures the definition hash only reflects structural changes
 *
 * @param array &$field field definition array (modified by reference)
 * @return void
 */
function zz_state_definition_remove_defaults(&$field) {
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
 * Generate or retrieve the state hash (definition hash + additional entropy)
 * Creates a compact hash derived from the form definition hash combined with additional data
 * Converted from hex to base62 for shorter representation
 *
 * This hash is used for integrity verification and is paired with the token in logs
 *
 * @param mixed $value (optional) if provided, generate new hash from this value
 * @param string $action (optional)
 *		'once': generate hash without storing (for one-off uses)
 *		'write': directly write $value as the stored hash
 * @return string current state hash or null if not yet generated
 */
function zz_state_hash($value = NULL, $action = '') {
	static $state_hash = NULL;

	if ($action === 'write')
		return $state_hash = $value;
	
	// Generate new hash if value provided
	if ($value !== NULL) {
		$hash = sha1(zz_state_definition().$value);
		$hash = wrap_base_convert($hash, 16, 62);
		// return hash without storing
		if ($action === 'once') return $hash;
		// store hash
		$state_hash = $hash;
	}
	
	return $state_hash;
}


/*
 * --------------------------------------------------------------------
 * Pairing
 * --------------------------------------------------------------------
 */

/**
 * Manage token-hash pairings for anti-replay protection
 * Logs valid combinations of state tokens and their corresponding hashes
 * Prevents form replay attacks by verifying token-hash pairs match logged values
 *
 * @param string $mode operation mode:
 *		'read': retrieve hash for given token
 *		'write': log new token-hash pairing
 *		'timecheck': return seconds since token was logged
 * @param string $token (optional) state token, defaults to current token from zz_state_token()
 * @param string $hash (optional) definition hash to pair with token
 * @return string|int depends on mode: 'read' returns hash, 'timecheck' returns seconds, 'write' returns hash or empty
 */
function zz_state_pairing($mode, $token = '', $hash = '') {
	// no need for pairing in batch mode
	if (wrap_static('zzform_output', 'batch_mode')) return '';

	zz_state_pairing_deprecated();

	if (!$token) $token = zz_state_token();
	$hash_found = '';
	$timestamp = 0;

	wrap_include('file', 'zzwrap');
	$logs = wrap_file_log('zzform/tokens');
	foreach ($logs as $index => $line) {
		if ($line['zzform_token'] !== $token) continue;
		$hash_found = $line['zzform_hash'];
		$timestamp = $line['timestamp'];
	}
	
	if ($mode === 'read') return $hash_found;
	if ($mode === 'timecheck') return time() - $timestamp;
	// now we have $mode = write
	if ($hash_found) return $hash_found;
	if (!empty($_POST)) {
		// no hash found but POST? resend required, possibly spam
		// but first check if it is because of add_details
		if (empty($_POST['zz_edit_details']) AND empty($_POST['zz_add_details']))
			wrap_static('zzform_output', 'resend_form_required', true);
	}
	wrap_file_log('zzform/tokens', 'write', [time(), $token, $hash]);
}

/**
 * Delete token-hash pairing after successful form operation
 * Prevents replay attacks by invalidating the token-hash combination
 * Should be called after INSERT/UPDATE/DELETE operations complete successfully
 *
 * @return bool true if pairing was deleted, false if nothing to delete
 */
function zz_state_pairing_delete() {
	if (!zz_state_token()) return false;
	if (!zz_state_hash()) return false;

	wrap_include('file', 'zzwrap');
	wrap_file_log('zzform/tokens', 'delete', [
		'zzform_token' => zz_state_token(),
		'zzform_hash' => zz_state_hash()
	]);
	return true;
}

/**
 * migrate old log file location
 *
 * @deprecated
 */
function zz_state_pairing_deprecated() {
	if (file_exists(wrap_setting('log_dir').'/zzform-ids.log')) {
		wrap_mkdir(wrap_setting('log_dir').'/zzform');
		rename(wrap_setting('log_dir').'/zzform-ids.log', wrap_setting('log_dir').'/zzform/tokens.log');
	}
	if (file_exists(wrap_setting('log_dir').'/zzform/ids.log')) {
		rename(wrap_setting('log_dir').'/zzform/ids.log', wrap_setting('log_dir').'/zzform/tokens.log');
	}
}
