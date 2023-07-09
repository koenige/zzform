<?php

/**
 * zzform
 * handling of detail forms
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2021, 2023 Gustaf Mossakowski
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
	if (!empty($_POST['zz_add_details']) OR !empty($_POST['zz_edit_details'])) {
		zz_details_start($zz);
		// on success: redirect
		return $zz;
	}
	if (empty($_SESSION['zzform'])) return $zz;
	if (!array_key_exists($zz_conf['id'], $_SESSION['zzform'])) return $zz;

	// check position
	$script_name = zz_url_basename(wrap_setting('request_uri'));
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
		$hooks = ['after_insert', 'after_update'];
		foreach ($hooks as $hook) {
			$zz['hooks'][$hook][] = 'zz_details_return';
		}
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

	$mode = !empty($_POST['zz_add_details']) ? 'add' : 'edit';
	$add_details = key($_POST['zz_'.$mode.'_details']);
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
					$table_name = wrap_db_prefix($table_name);
					$posted_value = $_POST[$table_name][$rec][$sub_field['field_name']];
					$source_field_name = $table_name.'['.$rec.']['.$sub_field['field_name'].']';
				}
			}
		} elseif (!$subtable_no AND $no == $field_no) {
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
	if (is_array($field['add_details'])) {
		$field['add_details'] = zz_details_link($field['add_details'], $_POST);
	}
	if (empty($field['add_details'])) return false;
	
	$redirect_to = $field['add_details'];
	$redirect_to .= strstr($field['add_details'], '?') ? '&' : '?';
	if ($mode === 'add')
		$redirect_to .= sprintf('add&zz=%s', $zz_conf['id']);
	else
		$redirect_to .= sprintf('edit=%d&zz=%s', $posted_value, $zz_conf['id']);

	$source = wrap_setting('request_uri');
	$source .= strstr($source, '?') ? '&' : '?';
	if (!strstr($source, 'zz=')) {
		$source .= sprintf('zz=%s&', $zz_conf['id']);
	}
	if ($_POST['zz_action'] === 'insert') {
		$source .= 'add';
	} else {
		$source .= sprintf('edit=%d', $_POST[$id_field_name]);
	}
	foreach ($zz_conf['int']['internal_post_fields'] as $fname) {
		unset($_POST[$fname]);
	}
	$session = [
		'post' => $_POST,
		'get' => $_GET,
		'source' => $source,
		'source_script' => zz_url_basename($source),
		'source_field_name' => $source_field_name,
		'destination' => $redirect_to,
		'destination_script' => zz_url_basename($redirect_to),
		'new_value' => $posted_value
	];
	// overwrite existing SESSION entries with same source script
	// if more than one detail record is added
	$existing = NULL;
	if (!empty($_SESSION['zzform'][$id])) {
		foreach ($_SESSION['zzform'][$id] as $index => $form) {
			if ($form['source_script'] !== $session['source_script']) continue;
			$existing = $index;
		}
	}
	wrap_session_start();
	if (isset($existing)) {
		$_SESSION['zzform'][$id][$existing] = $session;
	} else {
		$_SESSION['zzform'][$id][] = $session;
	}

	wrap_redirect_change($redirect_to);
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
function zz_details_return($ops, $zz_tab) {
	global $zz_conf;
	$current = $zz_conf['int']['details_current'];
	$last = $zz_conf['int']['details_last'];
	
	wrap_session_start();

	// save return_id
	if (isset($last)) {
		if (!empty($zz_tab[0]['add_details_return_field'])) {
			list($id_table, $id_field) = explode('.', $zz_tab[0]['add_details_return_field']);
			foreach ($ops['return'] as $index => $table) {
				if ($table['table'] !== $id_table) continue;
				$id = $ops['record_new'][$index][$id_field];
			}
		} else {
			$id = $ops['id'];
		}
		$_SESSION['zzform'][$zz_conf['id']][$last]['new_id'] = $id;
	}

	// remove session entries for this record
	if (isset($current)) {
		unset($_SESSION['zzform'][$zz_conf['id']][$current]);
	}
	if (isset($_SESSION['zzform'][$zz_conf['id']]) AND !$_SESSION['zzform'][$zz_conf['id']]) {
		unset($_SESSION['zzform'][$zz_conf['id']]);
		// no more detail forms open, remove zz-ID from URL
		$zz_conf['int']['url']['qs_zzform'] = zz_edit_query_string(
			$zz_conf['int']['url']['qs_zzform'], ['zz'], [], '&'
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
		wrap_static('page', 'referer', $_SESSION['zzform'][$zz_conf['id']][$last]['source']);
		wrap_setting('zzform_referer_text', 'Back to last form');
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
		$subfound = false;
		foreach ($zz['fields'] as $no => $field) {
			if (empty($field)) continue;
			if (empty($field['type'])) $field['type'] = 'text'; // zz_fill_out() starts later
			if ($field['type'] === 'id') continue;
			if (!$first AND $field['type'] === 'text') {
				$first = $no;
			}
			if (empty($field['add_details_destination'])) continue;
			$found = $no;
			if (in_array($field['type'], ['subtable', 'foreign_table'])) {
				foreach ($field['fields'] as $subno => $subfield) {
					if (empty($subfield['add_details_destination'])) continue;
					$subfound = $subno;
				}
			}
		}
		if (!$found AND $first) {
			$found = $first;
		}
		if ($found) {
			if ($subfound) {
				$zz['fields'][$found]['fields'][$subfound]['default'] = $_SESSION['zzform'][$zz_conf['id']][$last]['new_value'];
			} else {
				$zz['fields'][$found]['default'] = $_SESSION['zzform'][$zz_conf['id']][$last]['new_value'];
			}
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

/**
 * create link to details form
 *
 * @param array $details
 * @param array $record main record
 * @return string
 */
function zz_details_link($details, $record) {
	$link = zz_makelink($details, $record);
	if (empty($details['target'])) return $link;

	preg_match('~\*(.+)\*~', $link, $matches);
	if (empty($matches[1])) return '';
	$ids = wrap_id($details['target'][0]['ids'], '', 'list');
	foreach ($ids as $identifier => $id) {
		if ($id !== $matches[1]) continue;
		$sql = 'SELECT %s FROM %s WHERE %s LIKE "%%&target=%s%%"';
		$sql = sprintf($sql
			, $details['target'][0]['identifier_field'] ?? 'identifier'
			, $details['target'][0]['table']
			, $details['target'][0]['parameters_field'] ?? 'parameters'
			, $identifier
		);
		$result = wrap_db_fetch($sql, '', 'single value');
		if (!$result) return '';
		return str_replace($matches[0], $result, $link);
	}
	return '';
}

/**
 * hide add link if details mode
 *
 * @return bool true: show link, false: hide link
 */
function zz_details_add_link() {
	global $zz_conf;
	
	if (empty($_SESSION['zzform'][$zz_conf['id']][0])) return true;
	$session = $_SESSION['zzform'][$zz_conf['id']][0];
	if (!empty($session['new_id'])) return false;
	return true;
}
