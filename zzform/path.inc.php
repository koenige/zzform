<?php 

/**
 * zzform
 * creating a path
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * get a URL link from a path definition and a flat record
 *
 * @param array $def
 * @param array $record
 * @return string
 */
function zz_path_link($def, $record) {
	$path = zz_makelink($def, $record);
	if (!$path) return '';
	return $path['web'];
}

/**
 * get a web path from a path definition and a flat record
 *
 * @param array $def
 * @param array $record
 * @return string
 */
function zz_path_link2($def, $record) {
	return zz_makepath($def, $record, 'local');
}

/**
 * get an HTML img element from a path definition and a flat record
 *
 * @param array $def
 * @param array $record
 * @return string
 */
function zz_path_image($def, $record) {
	$path = zz_makelink($def, $record, 'image');
	if (!$path) return '';

	if ($path['root']) {
		// get filetype from extension
		if (strstr($path['file'], '.')) {
			$ext = strtoupper(substr($path['file'], strrpos($path['file'], '.') + 1));
		} else {
			$ext = wrap_text('- unknown -');
		}

		// filesize is 0 = looks like error
		if (!$size = filesize($path['root'].$path['file'])) return '';
		// getimagesize tests whether it's a web image
		$filetype_def = wrap_filetypes(strtolower($ext), 'read-per-extension');
		if (empty($filetype_def['webimage']) AND !getimagesize($path['root'].$path['file'])) {
			// if not, return EXT (4.4 MB)
			return $ext.' ('.wrap_bytes($size).')';
		}
	}
	if (!$path['web']) return '';
	
	$srcset = [];
	foreach ($path['srcset'] as $factor => $path_srcset)
		$srcset[] = $path_srcset.' '.$factor.'x';
	$srcset = $srcset ? sprintf(' srcset="%s 1x, %s"', $path['web'], implode(', ', $srcset)) : '';
	$img = '<img src="'.$path['web'].'"'.$srcset.' alt="'.$path['alt'].'" class="thumb">';
	return $img;
}

/**
 * get an absolute filesystem path from a path definition and a flat record
 *
 * @param array $def
 * @param array $record
 * @return string
 */
function zz_path_file($def, $record) {
	$path = zz_makelink($def, $record);
	if (!$path) return '';
	return $path['root'].$path['file'];
}

/**
 * get an absolute filesystem path from a path definition and a flat record
 *
 * @param array $def
 * @param array $record
 * @return string
 */
function zz_path_file2($def, $record) {
	return zz_makepath($def, $record, 'file');
}


/** 
 * Creates link or HTML img from path
 * 
 * @param array $def
 *		'root', 'webroot', 'field1...fieldn', 'string1...stringn', 'mode1...n',
 *		'extension', 'x_field[]', 'x_webfield[]', 'x_extension[]'
 *		'ignore_record' will cause record to be ignored
 *		'alternate_root' will check for an alternate root
 * @param array $record current record
 * @param string $type (optional) link, path or image, image will be returned in
 *		<img src="" alt="">
 * @return array
 */
function zz_makelink($def, $record, $type = 'link') {
	if (empty($def['ignore_record']) AND !$record) return [];
	if (!$def) return [];
	
	$path = [
		'file' => '',
		'root' => '',
		'root_alt' => '',
		'srcset' => [],
		'web' => ''
	];
	$modes = [];
	$sets = [];
	foreach (array_keys($def) as $part) {
		if (substr($part, 0, 2) !== 'x_') continue;
		$part = explode('[', $part);
		$part = substr($part[1], 0, strpos($part[1], ']'));
		$sets[$part] = $part;
	}
	foreach ($sets as $myset) {
		$path['srcset'][$myset] = '';		// relative path to retina image on website
		$set[$myset] = NULL;			// show 2x image
	}
	
	$check_against_root = false;

	if ($type === 'image') {
		$path['alt'] = wrap_text('No image');
		// lock if there is something definitely called extension
		$alt_locked = false; 
	}
	if (!is_array($def)) $def = ['string' => $def];
	
	// check if extension field is given but has no value
	if (!empty($def['extension_missing']) AND !empty($def['extension'])
		AND empty($record[$def['extension']])) {
		// check if extension_missing[extension] is webimage, otherwise return false
		if ($type === 'image' AND !empty($record[$def['extension_missing']['extension']])) {
			$filetype_def = wrap_filetypes($record[$def['extension_missing']['extension']], 'read-per-extension');
			if (empty($filetype_def['webimage']) AND empty($filetype_def['php'])) return [];
		}
		$def = array_merge($def, $def['extension_missing']);
	}
	
	foreach ($def as $part => $value) {
		if (!$value) continue;
		// remove numbers at the end of the part type
		while (is_numeric(substr($part, -1))) $part = substr($part, 0, -1);
		if (substr($part, -1) === ']') {
			$current_set = substr($part, strpos($part, '[') + 1, -1); 
			$part = substr($part, 0, strpos($part, '['));
		}
		switch ($part) {
		case 'area':
			$path_values = [];
			if (empty($def['fields'])) $def['fields'] = [];
			elseif (!is_array($def['fields'])) $def['fields'] = [$def['fields']];
			foreach ($def['fields'] as $index => $this_field) {
				if (empty($record[$this_field])) break 2;
				if (!empty($def['target'][$index]))
					// placeholder for later use
					$path_values[] = '*'.$record[$this_field].'*';
				else
					$path_values[] = $record[$this_field];
			}
			if (strstr($value, '[%s]') AND !empty($def['area_fields'])) {
				$area_values = [];
				foreach ($def['area_fields'] as $this_field)
					$area_values[] = $record[$this_field];
				$value = vsprintf($value, $area_values);
			}
			$rights = true;
			if (!empty($def['restrict_to']) AND !empty($record[$def['restrict_to']]))
				$rights = sprintf('%s:%d', $def['restrict_to'], $record[$def['restrict_to']]);
			$path['web'] .= wrap_path($value, $path_values, $rights);
			break;

		case 'function':
			if (function_exists($value) AND !empty($def['fields'])) {
				$params = [];
				foreach ($def['fields'] as $function_field) {
					if (!isset($record[$function_field])) continue;
					$params[$function_field] = $record[$function_field];
				}
				$path['web'] .= $value($params);
			}
			break;
		case 'fields':
		case 'restrict_to':
			break;

		case 'root':
			$check_against_root = true;
			// root has to be first element, everything before will be ignored
			$path['root'] = $value;
			if (substr($path['root'], -1) !== '/')
				$path['root'] .= '/';
			break;

		case 'alternate_root':
			$path['root_alt'] = $value;
			if (substr($path['root_alt'], -1) !== '/')
				$path['root_alt'] .= '/';
			break;

		case 'webroot':
			// web might come later, ignore parts before for web and add them
			// to full path
			$path['web'] = $value;
			foreach ($sets as $myset) {
				$path['srcset'][$myset] = $value;
			}
			$path['root'] .= $path['file'];
			$path['root_alt'] .= $path['file'];
			$path['file'] = '';
			break;

		case 'extension':
		case 'field':
		case 'webfield':
			// we don't have that field or it is NULL, so we can't build the
			// path and return with nothing
			// if you need an empty field, use IFNULL(field_name, "")
			if (!isset($record[$value])) return [];
			$content = $record[$value];
			if ($modes) {
				$content = zz_path_mode($modes, $content, E_USER_ERROR);
				if (!$content) return [];
				$modes = [];
			}
			if ($part !== 'webfield') {
				$path['file'] .= $content;
			}
			$path['web'] .= $content;
			if ($type === 'image' AND !$alt_locked) {
				$path['alt'] = wrap_text('File: ').$record[$value];
				if ($part === 'extension') $alt_locked = true;
			}
			break;

		case 'x_extension':
		case 'x_field':
		case 'x_webfield':
			if ($set[$current_set] === false) break;
			if (!isset($record[$value])) { $set[$current_set] = false; break; }
			$set[$current_set] = true;
			$content = $record[$value];
			if ($modes) {
				$content = zz_path_mode($modes, $content, E_USER_ERROR);
				if (!$content) break;
				$modes = [];
			}
			$path['srcset'][$current_set] .= $content;
			break;

		case 'string':
			$path['file'] .= $value;

		case 'webstring':
			$path['web'] .= $value;
			foreach ($sets as $myset) {
				$path['srcset'][$myset] .= $value;
			}
			break;

		case 'mode':
			$modes[] = $value;
			break;
		}
	}

	if ($check_against_root) {
		// check whether file exists
		if (!file_exists($path['root'].$path['file'])) {
			// file does not exist = false
			if (!$path['root_alt']) return [];
			if (!file_exists($path['root_alt'].$path['file'])) return [];
			$path['root'] = $path['root_alt'];
		}
	}

	foreach ($sets as $myset) {
		if (!$set[$myset]) unset($path['srcset'][$myset]);
	}

	return $path;
}

/**
 * apply all modes as a function to content
 *
 * @param array $modes
 * @param string $content
 * @return string
 */
function zz_path_mode($modes, $content, $error = E_USER_WARNING) {
	foreach ($modes as $mode) {
		if (!function_exists($mode)) {
			zz_error_log([
				'msg_dev' => 'Configuration Error: mode with non-existing function `%s`',
				'msg_dev_args' => [$mode],
				'level' => $error
			]);
			return false;
		}
		$content = $mode($content);
	}
	return $content;
}

/** 
 * Construct path from values
 * 
 * @param array $def array with variables which make path
 *		'root' (DOCUMENT_ROOT), 'webroot' (different root for web, all fields
 *		and strings before webroot will be ignored for this), 'mode' (function  
 *		to do something with strings from now on), 'string1...n' (string, number
 *		has no meaning, no sorting will take place, will be shown 1:1),
 *		'field1...n' (field value from record)
 * @param array $record (from $zz_tab or simple line)
 * @param string $type
 * @return string
 */
function zz_makepath($def, $record, $type) {
	// set variables
	$path['file'] = '';
	$modes = false;
	$path['root'] = '';
	$rootp = false;		// path just for root
	$webroot = false;	// web root
	$sql_fields = [];

	// put path together
	foreach ($def as $part => $pvalue) {
		if (!$pvalue) continue;
		while (is_numeric(substr($part, -1))) $part = substr($part, 0, -1);
		switch ($part) {
		case 'root':
			$path['root'] = $pvalue;
			break;

		case 'webroot':
			$webroot = $pvalue;
			$rootp = $path['file'];
			$path['file'] = '';
			break;

		case 'mode':
			$modes[] = $pvalue;
			break;
		
		case 'string':
			$path['file'] .= $pvalue;
			break;

		case 'sql_field':
			$sql_fields[] = $record[$pvalue] ?? '';
			break;

		case 'sql':
			$sql = $pvalue;
			if ($sql_fields) $sql = vsprintf($sql, $sql_fields);
			$result = wrap_db_fetch($sql, '', 'single value');
			if ($result) $path['file'] .= $result;
			$sql_fields = [];
			break;

		case 'extension':
		case 'field':
			$content = $record[$pvalue] ?? '';
			if ($modes) {
				$content = zz_path_mode($modes, $content);
				if (!$content AND $content !== '0') return '';
			}
			$path['file'] .= $content;
			$modes = false;
			break;

		case 'webstring':
		case 'webfield':
		case 'extension_missing':
			break;

		default:
			wrap_error(sprintf('Unknown mode %s in %s', $part, __FUNCTION__), E_USER_NOTICE);
			break;
		}
	}

	switch ($type) {
		case 'file':
			// webroot will be ignored
			return $path['root'].$rootp.$path['file'];
		case 'local':
			return $webroot.$path['file'];
	}
}

/**
 * extract a new record from a zz_tab data structure, pre-fetching
 * missing field values needed by the path definition
 *
 * @param array $def path definition
 * @param array $my_tab $zz_tab[0]
 * @param int $rec (optional)
 * @return array
 */
function zz_path_record($def, $my_tab, $rec = 0) {
	$record = $my_tab[$rec]['POST'] ?? [];
	foreach ($def as $part => $value) {
		if (!$value) continue;
		while (is_numeric(substr($part, -1))) $part = substr($part, 0, -1);
		if ($part !== 'field' AND $part !== 'extension') continue;
		if (isset($record[$value]) AND ($record[$value] OR $record[$value] === '0')) continue;
		$content = zz_path_query(
			$value, $my_tab['sql'], $my_tab[$rec]['id']['value'],
			$my_tab['table'].'.'.$my_tab[$rec]['id']['field_name']
		);
		if ($content !== false) $record[$value] = $content;
	}
	return $record;
}

/** 
 * gets value from a single record
 * 
 * @param string $field_name
 * @param string $sql
 * @param string $idvalue (optional)
 * @param string $idfield (optional)
 * @return string
 */
function zz_path_query($field_name, $sql, $idvalue = false, $idfield = false) { 
	static $queried = [];
	$key = sprintf('%s-%s-%s', $sql, $idvalue, $idfield);
	// if idvalue is not set: note: all values should be the same!
	// First value is taken
	if (!array_key_exists($key, $queried)) {
		if ($idvalue) 
			$sql = wrap_edit_sql($sql, 'WHERE', sprintf('%s = %d', $idfield, $idvalue));
		$queried[$key] = zz_db_fetch($sql, '', '', __FUNCTION__);
	}
	return $queried[$key][$field_name] ?? false;
}
