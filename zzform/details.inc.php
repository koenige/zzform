<?php

/**
 * zzform
 * handling of detail forms
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * List of functions in this file
 *	zzform()				main zzform function
 *		zz_initialize()		sets defaults, imports modules, reads URI
 *	zzform_multi()			multi edit for zzform, e. g. import
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2016 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * check if form was called via 'add_details'
 *
 * @param array $zz
 * @return array $zz
 */
function zz_details($zz) {
	global $zz_conf;
	
	// start script?
	if (!empty($_POST['zz_add_details'])) {
		zz_details_start($zz);
		// on success: redirect
		return $zz;
	}
	if (empty($_SESSION['zzform'])) return $zz;
	if (!array_key_exists($zz_conf['id'], $_SESSION['zzform'])) return $zz;

	// check position
	$script_name = zz_url_basename($_SERVER['REQUEST_URI']);
	$last = NULL;
	$current = NULL;

	foreach ($_SESSION['zzform'][$zz_conf['id']] as $index => $form) {
		if ($form['destination_script'] === $script_name) {
			$last = $index;
		} elseif ($form['source_script'] === $script_name) {
			$current = $index;
		}
	}
	
	// what to do?
	if (!empty($_POST)) {
		// prepare writing which should occur after record was successfully
		// saved
		$zz_conf['int']['details_current'] = $current;
		$zz_conf['int']['details_last'] = $last;
		if (!empty($zz['hooks']['after_insert']) AND !is_array($zz['hooks']['after_insert'])) {
			$zz['hooks']['after_insert'] = array($zz['hooks']['after_insert']);
		}
		$zz['hooks']['after_insert'][] = 'zz_details_return';
	}
	
	return zz_details_show($zz, $current, $last);
}

/**
 * [New …] was clicked
 * save data [n], set source + destination and redirect to another table
 * to add missing detail record
 * 
 * @param array $zz
 * @return bool false if there's an error
 */
function zz_details_start($zz) {
	global $zz_conf;
	if (empty($_SESSION['logged_in'])) return false;

	$add_details = key($_POST['zz_add_details']);
	$add_details = explode('-', $add_details);
	if (count($add_details) === 4) {
		list($id, $field_no) = $add_details;
		$subtable_no = false;
	} else {
		list($id, $subtable_no, $field_no, $tab, $rec) = $add_details;
	}

	foreach ($zz['fields'] as $no => $field) {
		if ($subtable_no AND $no == $subtable_no) {
			foreach ($field['fields'] as $sub_no => $sub_field) {
				if ($sub_no == $field_no) {
					$table_name = isset($field['table_name']) ? $field['table_name'] : $field['table'];
					$posted_value = $_POST[$table_name][$rec][$sub_field['field_name']];
					$source_field_name = $table_name.'['.$rec.']['.$sub_field['field_name'].']';
				}
			}
		} elseif ($no == $field_no) {
			$posted_value = $_POST[$field['field_name']];
			$source_field_name = $field['field_name'];
		}
		if (empty($field['type'])) continue;
		if ($field['type'] !== 'id') continue;
		$id_field_name = $field['field_name'];
	}

	if ($subtable_no) {
		$field = $zz['fields'][$subtable_no]['fields'][$field_no];
	} else {
		$field = $zz['fields'][$field_no];
	}
	if (empty($field['add_details'])) return false;
	
	$redirect_to = $field['add_details'];
	$redirect_to .= strstr($field['add_details'], '?') ? '&' : '?';
	$redirect_to .= sprintf('mode=add&zz=%s', $zz_conf['id']);

	$source = $_SERVER['REQUEST_URI'];
	$source .= strstr($source, '?') ? '&' : '?';
	if (!strstr($source, 'zz=')) {
		$source .= sprintf('zz=%s&', $zz_conf['id']);
	}
	if ($_POST['zz_action'] === 'insert') {
		$source .= 'mode=add';
	} else {
		$source .= sprintf('mode=edit&id=%d', $_POST[$id_field_name]);
	}
	foreach ($zz_conf['int']['internal_post_fields'] as $fname) {
		unset($_POST[$fname]);
	}
	wrap_session_start();
	$_SESSION['zzform'][$id][] = array(
		'post' => $_POST,
		'get' => $_GET,
		'source' => $source,
		'source_script' => zz_url_basename($source),
		'source_field_name' => $source_field_name,
		'destination' => $redirect_to,
		'destination_script' => zz_url_basename($redirect_to),
		'new_value' => $posted_value
	);

	wrap_http_status_header(303);
	header('Location: '.$redirect_to);
	exit;
}

/**
 * operations for detail record navigation after a new detail record was saved
 * successfully
 * save return_id, remove SESSION, remove zz-query string if top level
 *
 * @param array $ops
 * @global array $zz_conf
 * 		int[details_current], int[details_last]
 * @return void
 */
function zz_details_return($ops) {
	global $zz_conf;
	$current = $zz_conf['int']['details_current'];
	$last = $zz_conf['int']['details_last'];
	
	wrap_session_start();

	// save return_id
	if (isset($last)) {
		$_SESSION['zzform'][$zz_conf['id']][$last]['new_id'] = $ops['id'];
	}

	// remove session entries for this record
	if (isset($current)) {
		unset($_SESSION['zzform'][$zz_conf['id']][$current]);
	}
	if (isset($_SESSION['zzform'][$zz_conf['id']]) AND !$_SESSION['zzform'][$zz_conf['id']]) {
		unset($_SESSION['zzform'][$zz_conf['id']]);
		// no more detail forms open, remove zz-ID from URL
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
			$zz_conf['int']['url']['qs_zzform'], array('zz')
		);
	}

	session_write_close();
	return;
}

/**
 * Form is shown where detail records have been or will be added
 * show saved data, add new ID from next record or add new values from last
 * record, don't show list if it's a detail form
 *
 * @param int $current
 * @param int $last
 * @return array $zz
 */
function zz_details_show($zz, $current, $last) {
	global $zz_conf;

	if (!empty($_SESSION['zzform'][$zz_conf['id']][$last])) {
		// is there a form to return to?
		$zz_conf['int']['show_list'] = false;
		$zz_conf['int']['cancel_url'] = $_SESSION['zzform'][$zz_conf['id']][$last]['source'];
		$zz_conf['referer'] = $_SESSION['zzform'][$zz_conf['id']][$last]['source'];
		$zz_conf['referer_text'] = 'Back to last form';
		zz_init_referer();
	}

	if (!empty($_POST)) return $zz;

	if (!empty($_SESSION['zzform'][$zz_conf['id']][$current]['post'])) {
		// read saved POST data top populate form if there is data
		$_POST = $_SESSION['zzform'][$zz_conf['id']][$current]['post'];
		$_GET = array_merge($_GET, $_SESSION['zzform'][$zz_conf['id']][$current]['get']);
		$zz_conf['int']['add_details_return'] = true;

	} elseif (!empty($_SESSION['zzform'][$zz_conf['id']][$last]['new_value'])) {
		// write string from previous form as a default to this form
		$found = false;
		$first = false;
		foreach ($zz['fields'] as $no => $field) {
			if (empty($field['type'])) continue;
			if ($field['type'] === 'id') continue;
			if (!$first AND $field['type'] === 'text') {
				$first = $no;
			}
			if (empty($field['add_details_destination'])) continue;
			$found = $no;
		}
		if (!$found AND $first) {
			$found = $first;
		}
		if ($found) {
			$zz['fields'][$found]['default'] = $_SESSION['zzform'][$zz_conf['id']][$last]['new_value'];
		}
	}

	if (!empty($_SESSION['zzform'][$zz_conf['id']][$current]['new_id'])) {
		// write new ID from next form back to this form
		$_POST = zz_check_values(
			$_POST,
			$_SESSION['zzform'][$zz_conf['id']][$current]['source_field_name'],
			$_SESSION['zzform'][$zz_conf['id']][$current]['new_id']
		);
	} 

	return $zz;
}

/**
 * get basename for script
 *
 * @param string $url
 * @return string
 */
function zz_url_basename($url) {
	$parts = parse_url($url);
	return basename($parts['path']);
}
