<?php

/**
 * zzform
 * Import of data
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2012 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Import function
 *
 * @return ...
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo This function is not working yet. Do not use!
 */
function zzform_import($ops) {
	global $zz_conf;
	require_once $zz_conf['dir_inc'].'/functions.inc.php';	// include core functions
	require_once $zz_conf['dir_inc'].'/database.inc.php';	// include db functions

	zz_initialize('overwrite');

	$ops['output'] = '';
	$ops['mode'] = 'import';

	if (!empty($_FILES['zz_import']['name'])) {
		// get file, open it and transform values into array which can directly
		// be saved into database
		// @todo: check files, check errors
		if (substr($_FILES['zz_import']['name'], strrpos($_FILES['zz_import']['name'], '.')) != '.txt') {
			$ops['output'] .= zz_text('Wrong filetype. Only textfiles are allowed');
			return false;
		}
		
//		if (substr($_FILES['zz_import']['name'], strrpos('.', $_FILES['zz_import']['name']
		$file = readfile($_FILES['zz_import']['tmp_name']);
		
		zz_print_r($_FILES['zz_import']);
		// save file somewhere to temporary database or disk
		
		// show records which might be imported
	
	} else {
		$ops['output'].= '<div id="zzform">'."\n"
			.'<h1>'.zz_text('Import').' '.$zz_conf['heading'].'</h1>'."\n\n"
			.'<form action="'.$zz_conf['int']['url']['self'].$zz_conf['int']['url']['qs'];
		$ops['output'] .= $zz_conf['int']['url']['?&'].'mode=import';
		$ops['output'].= '" method="POST" enctype="multipart/form-data"'
			.' accept-charset="'.$zz_conf['character_set'].'">'."\n"
			.'<p><input type="file" name="zz_import"></p>'
			.'<p><input type="submit" value="'.zz_text('Import data').'"></p>'
			.'</form>'."\n\n";
		$ops['output'].= '</div>'."\n";
	}
	return $ops;
}

/**
 * Imports files
 *
 * will be called from zzform_multi() and will call itself recursively
 * @param string $definition_file $zz-table script which defines db table(s)
 * @param array $values Values for import into database
 * @param array $params
 *		string 'base_dir' (base path to directory where files reside)
 *		string 'source_dir'
 *		string 'destination_dir'
 *		string 'destination_sql'
 *		string 'destination_identifier'
 *		array 'destination_conf_identifier'
 *		int 'parent_destination_folder_id'
 * @return string HTML output of what was imported/failed
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_import_files($definition_file, $values, $params) {
	global $zz_conf;
	global $zz_error;
	global $zz_import_error_msg;

	static $zz_import_i;
	if (!$zz_import_i) $zz_import_i = 1;
	else $zz_import_i++;

	// stop a little ahead max_execution_time (75%)
	if (empty($params['time'])) {
		$params['time']['max_execution_time'] = (.75*ini_get('max_execution_time'));
		$params['time']['start'] = microtime(true);
	}
	if (!empty($zz_conf['modules']['debug'])) zz_debug('start', __FUNCTION__);

	// set parameters if not set to defaults
	if (empty($params['source_dir']))
		$params['source_dir'] = $params['base_dir'];

	// check if directory exists, just a security precaution
	if (!file_exists($params['source_dir']) || !is_dir($params['source_dir'])) {
		$output = '<p>'.sprintf(zz_text('Folder "%s" does not exist.'), $params['source_dir']).'</p>'."\n";
		return zz_return($output);
	}

	$output = '<ul>'."\n".'<li>'.$params['destination_dir'];

	// If there is a database entry for the destination folder
	// get the value from database or create it
	if (!empty($params['destination_sql']))
		$output .= zz_import_create_folder($definition_file, $values, $params);

	// Walk through files and folders in this folder and insert them into database
	$folders = array();
	$files = array();

	// go on with files in folder
	// open folder recursively
	if (!empty($zz_conf['modules']['debug'])) zz_debug('read files start');
	$handle = opendir($params['source_dir']);
	while ($filename = readdir($handle)) {
		if (substr($filename, 0, 1) == '.') continue; // ignore filenames with dots
		$file = array();
		$file['full'] = $params['source_dir'].'/'.$filename;
		$file['short'] = $filename;
		$file['extension'] = '';
		$file['basename'] = $filename;
		if (is_dir($file['full'])) {
			$file['type'] = 'folder';
			$folders[] = $file;		
		} else {
			if ($dot = strrpos($filename, '.')) {
				$file['extension'] = strtolower(substr($filename, $dot +1)); // +1: without dot
				$file['basename'] = substr($filename, 0, $dot);
			}
			$file['type'] = 'file';
			$files[$file['basename']][$file['extension']] = $file;		
		}
	}

	if (!empty($zz_conf['import_file_order_by_extension'])) {
	// use a different order for file import than just the alphabetical order 
	// of the file extension
		foreach (array_keys($files) as $basename) {
			$old_array = $files[$basename];
			unset($files[$basename]);
			$files[$basename] = array(); // might be empty afterwards if no match
			foreach ($zz_conf['import_file_order_by_extension'] AS $ext) {
				// get this into the correct order!
				if (in_array($ext, array_keys($old_array))) {
					$files[$basename][$ext] = $old_array[$ext];
					unset($old_array[$ext]);
				}
			}
			if ($old_array) $files[$basename] = array_merge($files[$basename], $old_array);
			unset($old_array);
		}
	}
	if (!empty($zz_conf['modules']['debug'])) zz_debug("read files end");

	// import
	if (empty($params['destination_identifier']))
		$params['destination_identifier'] = $params['destination_dir'];
	$values['local']['parameters'] = $params['destination_identifier'];

	$output .= zz_import_create_files($definition_file, $values, $params, $files);
	
	foreach ($folders as $folder) {
		if (!empty($zz_conf['modules']['debug'])) zz_debug("folder start");
		// if time's almost up, exit function
		if (microtime(true) > $params['time']['start']+$params['time']['max_execution_time'])
			break;
		$params['source_dir'] = $folder['full'];
		$params['destination_dir'] = $params['destination_identifier'].'/'.$folder['short'];
		$output .= zz_import_files($definition_file, $values, $params);
		if (!empty($zz_conf['modules']['debug'])) zz_debug("folder end");
	}


	$output .= '</ul>'."\n";
	if ($zz_import_i !== 1) {
		$output .= $zz_import_error_msg;
		$zz_import_error_msg = '';
		if (!empty($zz_conf['modules']['debug'])) zz_debug("end MT");
	}
	return $output;

	// $files['/root/incoming/CAAD'][0]['full'] = '/root/incoming/CAAD.jpeg';
	// $files['/root/incoming/CAAD'][0]['short'] = 'CAAD.jpeg';
	// $files['/root/incoming/CAAD'][0]['extension'] = 'jpeg';
	// $files['/root/incoming/CAAD'][0]['basename'] = 'CAAD';
	// $files['/root/incoming/CAAD'][0]['type'] = 'file'; // folder
	
}

/**
 * Imports folder entries into database
 *
 * @param string $definition_file $zz-table script which defines db table(s)
 * @param array $values Values for import into database
 * @param array $params
 *		string 'base_dir' (base path to directory where files reside)
 *		string 'source_dir'
 *		string 'destination_dir'
 *		string 'destination_sql'
 *		string 'destination_identifier'
 *		array 'destination_conf_identifier'
 *		int 'parent_destination_folder_id'
 * @return string HTML output of what was imported/failed
 *		$params['destination_folder_id'] and $params['destination_identifier']
 *		will be changed
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_import_create_folder($definition_file, $values, &$params) {
	global $zz_conf;
	global $zz_error;

	// initalize variables
	$output = '';

	// 1. Check whether destination folder is already in database?
	// @todo: problem that identifier will be different after it was inserted
	// @todo: this gets most of it, but will not check for max_length
	if (!empty($params['destination_conf_identifier'])) {
		if (!empty($params['destination_identifier'])) {
			// existing elements
			$fields[] = $params['destination_identifier'];
			// new element, restricted to max_length; remove leading slash
			$fields[] = substr(str_replace($params['destination_identifier'],
				'', $params['destination_dir']), 1);
		} else {
			$fields[] = $params['destination_dir'];
		}
	 	$params['destination_identifier'] = zz_identifier($fields, $params['destination_conf_identifier']);
	} else {
		$params['destination_identifier'] = $params['destination_dir'];
	}
	$sql = sprintf($params['destination_sql'], zz_db_escape($params['destination_identifier']));
	$parent_destination_folder_id = zz_db_fetch($sql, '', 'single value');
	if ($parent_destination_folder_id) {
		// It's already in the database, get ID and return
		$params['parent_destination_folder_id'] = $parent_destination_folder_id;
		$output = '<strong> &#8211; '.zz_text('Folder OK').'</strong>'."\n";
		return $output;
	}
	
	// check if there is something for the parent folder, if not, return with error
	// @todo: it's right now unclear for me when this happens
	if (empty($params['parent_destination_folder_id'])) {
		$output = '<strong> &#8211; '.zz_text('Error: Invalid or no parent folder set.').'</strong>'."\n";
		return $output;
	}
	
	// 2. If not, create new folder, but this will not be possible for top level folders
	// @todo: set values dependent on $destination_folder_id!!
	$source_dir = str_replace($params['base_dir'], '', $params['source_dir']);
	
	if (!empty($values['folder']['placeholder'])) {
		foreach ($values['folder']['placeholder'] as $index => $vals) {
			if (!empty($vals['source'])) {
				switch ($vals['source']) {
				case 'parent_destination_folder_id':
					$val = $params['parent_destination_folder_id'];
					break;
				case 'destination_dirname':
					$val = substr($params['destination_dir'], strrpos($params['destination_dir'], '/')+1);
					break;
				case 'destination_path':
					$val = substr($params['destination_dir'], 0, strrpos($params['destination_dir'], '/'));
					break;
				default:
					$val = false;
				}
				$values['folder']['placeholder'][$index]['value'] = $val;
			}
			if (!empty($vals['matches'])) {
				$val = zz_import_check_matches($source_dir, $vals['matches']);
				if (is_null($val)) 
					// don't change anything, go on to next value
					continue; 
				elseif ($val)
					// overwrite default value with new value - this will last during this import process!
					$values['folder']['placeholder'][$index]['value'] = $val;
				else {
					// ignore this file/folder in import process
					// exit this function
					$output .= ' &#8211; '.zz_text('Folder will not be imported due to restrictions from your import settings.');
					return $output;
				}
			}
		}
	}
	
	if ($values['folder']['POST']['zz_action'] == 'insert') {
		// here, we need file and local values put together
		$values_zzform = $values['folder'];
		$values_zzform['local'] = $values['local'];
		$ops = zzform_multi($definition_file, $values_zzform, 'record');
		if (empty($ops['return'][0]['table']) OR $ops['return'][0]['table'] != 'objects') {
			$zz_error[] = array(
				'msg' => 'Folder could not be imported.',
				'level' => E_USER_NOTICE
			);
			zz_error();
			$output .= zz_error_output();
		} else {
			$params['parent_destination_folder_id'] = $ops['return'][0]['id_value'];
			$output .= ' &#8211; '.zz_text('Import was successful.');
		}
	} else {
		$output .= ' &#8211; '.zz_text('Would be imported.');
	}
	return $output;
}

/**
 * Checks filenames against wildcards and returns values if match was successful
 * 
 * @param string $filename Filename for import, without DOCUMENT_ROOT
 * @param array $matches Key: wildcards with or without asterisk, Value: value that will be returned if match was successful
 * @return mixed value (string = overwrite old value;  false: ignore file/folder ;NULL (= do not change value)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_import_check_matches($filename, $matches) {
	// initialize variables
	unset($val);
	// ignore case
	$filename = strtolower($filename);
	// go through all matches
	foreach ($matches as $match => $possible_val) {
		$match = strtolower($match);
		if (substr($match, 0, 1) == '*' AND substr($match, -1) == '*') {
			$match = substr($match, 1, -1); // remove asterisk
			if (stristr($filename, $match)) {
				$val = $possible_val;
			}
		} elseif (substr($match, 0, 1) == '*') { 
			$match = substr($match, 1); // remove asterisk
			if (substr($filename, -strlen($match)) == $match) {
				$val = $possible_val;
			}
		} elseif (substr($match, -1) == '*') {
			$match = substr($match, 0, -1); // remove asterisk
			if (substr($filename, 0, strlen($match)) == $match) {
				$val = $possible_val;
			}
		} else {
			// default or equal
			if (!$match OR $filename == $match) {
				$val = $possible_val;
			}
		}
	}
	if (isset($val)) return $val; // use new value
	else return NULL; // use old value
}

/**
 * Imports files into database
 *
 * @param string $definition_file $zz-table script which defines db table(s)
 * @param array $values Values for import into database
 *		array 'folder'
 *		array 'file'
 *		array 'local'
 * @param array $params
 *		string 'base_dir' (base path to directory where files reside)
 *		string 'source_dir'
 *		string 'destination_dir'
 *		string 'destination_sql'
 *		string 'destination_identifier'
 *		array 'destination_conf_identifier'
 *		int 'parent_destination_folder_id'
 * @param array $files
 *		indexed by basename, list of files per basename indexed by extension
 * @return string HTML output of what was imported/failed
 *		$params and $files will be changed
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_import_create_files($definition_file, $values, &$params, &$files) {
	global $zz_conf;
	global $zz_error;
	global $zz_import_error_msg;

	if (!empty($zz_conf['modules']['debug'])) zz_debug('start', __FUNCTION__);
	$output = '<ul>';

	foreach ($files as $basename => $myfiles) {
		if (!empty($zz_conf['modules']['debug'])) zz_debug('file start');
		// if time's almost up, exit function
		if (microtime(true) > ($params['time']['start'] + $params['time']['max_execution_time'])) {
			$zz_import_error_msg .= '<p class="error">'
				.sprintf(zz_text('%s files left for import. Please wait, the script will reload itself.'), count($files)).'</p>'."\n";
			break;
		}
		if (!is_writeable(dirname($params['source_dir']))) {
			$output .= zz_text('Warning! Insufficient access rights. Please make sure that the source directory is writable.')
				.': '.$params['source_dir']."\n"
				.'</li>'."\n";
			break;
		}
		
		$output .= '<li>'.$basename.' ('.strtoupper(implode(', ', array_keys($myfiles))).') ';
		if (!empty($values['file']['placeholder'])) {
			foreach ($values['file']['placeholder'] as $index => $vals) {
				if (!empty($vals['source'])) {
					switch ($vals['source']) {
					case 'parent_destination_folder_id':
						$val = $params['parent_destination_folder_id'];
						break;
					case 'basename':
						$val = $basename;
						break;
					default:
						$val = false;
					}
					$values['file']['placeholder'][$index]['value'] = $val;
				}
				if (!empty($vals['matches'])) {
					foreach ($myfiles as $type => $myfile) {
						$filename = str_replace($params['base_dir'], '', $myfile['full']);
						$val = zz_import_check_matches($filename, $vals['matches']);
						if (is_null($val)) 
							// don't change anything, go on to next value
							continue; 
						elseif ($val)
							// overwrite default value with new value
							// @todo: separate possibilities for each filetype!
							$values['file']['placeholder'][$index]['value'] = $val;
						else {
							// ignore this file/folder in import process
							// exit this function
							$output .= ' &#8211; '.zz_text('File will not be imported due to restrictions from your import settings.');
							continue 3;
						}
					}
				}
			}
		}
		// ok, here we go, import our files
		// read data from files, look for second file with the same name but different ending
		// e. g.: .CR2 / .JPG
//		if (count($myfiles) > 1) {
//			$output .= ' &#8211; '.zz_text('Import of two files at the same time is a to do.');
//			continue; // @todo: later!
//		}
		
		$i = 0;
		foreach ($myfiles as $file) {
			if (!is_readable($file['full'])) {
				$output .= ' &#8211; '.zz_text('Warning! Insufficient access rights for this file.');
				continue 2;
			}
			// @todo: 'short' might be removed, as it may be not neccessary
			$values['file']['FILES'][$values['file']['key'][$i]]['name']['file'] = $file['short'];
			$values['file']['FILES'][$values['file']['key'][$i]]['tmp_name']['file'] = $file['full'];
			$values['file']['FILES'][$values['file']['key'][$i]]['do_not_delete']['file'] = true;
			$i++;					
		}

		if ($values['file']['POST']['zz_action'] == 'insert') {
			// here, we need file and local values put together
			$values_zzform = $values['file'];
			$values_zzform['local'] = $values['local'];
			$ops = zzform_multi($definition_file, $values_zzform, 'record');
			if (empty($ops['return'][0]['table']) OR $ops['return'][0]['table'] != 'objects') {
				$zz_error[] = array(
					'msg' => 'File could not be imported.',
					'level' => E_USER_NOTICE
				);
				zz_error();
				$output .= zz_error_output();
			} else {
//				$params['parent_destination_folder_id'] = $ops['return'][0]['id_value'];
				$output .= ' &#8211; '.zz_text('Import was successful.');
			}
		} else {
			$output .= ' &#8211; '.zz_text('Would be imported.');
			if (!empty($values['file']['GET']['where'])) {
				$output .= zz_import_show_wheres($definition_file, $values);
			}
//			$output .= 'Category_ID '.$_GET['where']['category_id'];
		}
		if (!empty($zz_conf['modules']['debug'])) zz_debug('file end');
		unset($files[$basename]);
	}
	if ($output == '<ul>') $output = false; // nothing was added, so avoid emtpy uls
	else $output .= '</ul>'."\n";
	return zz_return($output);
}

/**
 * HTML output of imported files in test mode (shows what would be imported)
 *
 * @param string $definition_file
 * @param array $values
 * @return string HTML output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_import_show_wheres($definition_file, $values = array()) {
	global $zz_conf;

	$zz_conf['multi'] = true;
	$zz_conf['testimport'] = true;
	if (!empty($values['file']['GET'])) {
		$_GET = $values['file']['GET'];
	} elseif (!isset($_GET)) {
		$_GET = array();
	}

	// get $zz definitions
	global $zz_setting;
	require $zz_conf['form_scripts'].'/'.$definition_file.'.php';

	$output = '';
	$text = array();
	
	foreach ($_GET['where'] AS $fieldname => $value) {
		$zz['fields'] = zz_fill_out($zz['fields'], $zz_conf['db_name'].'.'.$zz['table']);
		foreach ($zz['fields'] AS $field) {
			if (empty($field['field_name'])) continue;
			if ($field['field_name'] != $fieldname) continue;
			if (!empty($field['sql'])) {
				$sql = zz_edit_sql($field['sql'], 'WHERE', $field['field_name'].'="'.$value.'"');
				$line = zz_db_fetch($sql, '', '', __FUNCTION__);
				if ($line) {
					array_shift($line); // first field MUST be ID
					$value = implode(' | ', $line);
				}
			}
			$text[] = $field['title'].': '.$value;
		}
	}
	$output .= ' &#8211; '.implode(' / ', $text);
	return $output;
}

/**
 * Reads all files from a given directory and returns them as an array
 *
 * @param string $source: absolute path to base directory
 * @return array $files Array of files and folders
 *		'count_folders' int folder count; 'count_files' int file count; 
 *		'folders' array {int key => string foldername}; 'files' array {int key
 *		=> array 'filename', 'extension', 'path' (relative to source path)
 *		'folder' (int Key of folder), 'folder_is_writable' (bool)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo: save folders as well, with main_folder_id
 */
function zz_import_get_files($source, $files = array()) {
	// this function will be called recursively
	// first call: split $source into array
	if (!is_array($source)) {
		$base = $source;
		unset($source);
		$source['base'] = $base;
		$source['path'] = '';
		$files['count_folders'] = 1;
		$files['folders'][0] = '';
		$files['count_files'] = 0;
	}
	// current folder
	$dir = $source['base'].($source['path'] ? '/'.$source['path'] : '');
	$my_folder = $files['count_folders'] - 1;
	$my_folder_is_writable = is_writable($dir);
	// open dir and check files recursively
	$handle = opendir($dir);
	while ($filename = readdir($handle)) {
		if (substr($filename, 0, 1) == '.') continue; // ignore filenames with dots
		$file = $dir.'/'.$filename;
		if (is_dir($file)) {
			$sub['base'] = $source['base'];
			$sub['path'] = ($source['path'] ? $source['path'].'/' : '').$filename;
			// Folder
			$files['folders'][$files['count_folders']] = $sub['path'];
			$files['count_folders']++;
			$files = array_merge($files, zz_import_get_files($sub, $files));
			$files['count_files'] = !empty($files['files']) ? count($files['files']) : 0;
			$files['count_folders'] = count($files['folders']);
		} else {
			// File
			if ($dot = strrpos($filename, '.')) {
				$extension = substr($filename, $dot + 1); // ignore dot
				$filename = substr($filename, 0, $dot);
			}
			$files['files'][$files['count_files']] = array(
				'filename' => $filename,
				'extension' => $extension,
				'path' => $source['path'],
				'folder' => $my_folder,
				'folder_is_writable' => $my_folder_is_writable
			);
			$files['count_files']++;
		}
	}
	closedir($handle);
	return $files;
}
?>