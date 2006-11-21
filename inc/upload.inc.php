<?php 

/*
	zzform Scripts
	image upload

	(c) Gustaf Mossakowski <gustaf@koenige.org> 2006

*/

/*

* kennung generieren (dateiname ohne endung, exif, was passiert bei mehreren dateien, forcefilename)
  (falls erforderlich, s. path. ist das da erkennbar?)
  kennung kann auch increment sein, ggf. vorhandene dateien checken (bauplan)

allowed output files

todo:
-----
* files that are bigger than in php.ini-var post_max_upload: no 
  error message will be shown, simply nothing happens!
  check if there is a possibility to generate an error message from this.
* check if permissions are correctly set (upload folder, backup folder, ...)
* check if images may be in subtables as well or if this causes problems (sql-query e. g.)

*/

function zz_upload_action(&$zz_tab, $zz_conf) {
	global $zz_error;
	if ($zz_tab[0][0]['action'] != 'delete')
		zz_upload_write($zz_tab, $zz_tab[0][0]['action'], $zz_conf);
	else
		zz_upload_delete($zz_tab, $zz_conf);
}

function zz_get_upload_fields(&$zz_tab) {
	$upload_fields = false;
	foreach (array_keys($zz_tab) as $i) {
		foreach (array_keys($zz_tab[$i]) as $k) {
			if (!is_int($i) OR !is_int($k)) continue;
			foreach ($zz_tab[$i][$k]['fields'] as $f => $field)
				if ($field['type'] == 'upload_image') {
					$my['i'] = $i;
					$my['k'] = $k;
					$my['f'] = $f;
					$upload_fields[] = $my;
				}
		}
	}
	$zz_tab[0]['upload_fields'] = $upload_fields;
}

function zz_upload_delete($zz_tab, $zz_conf) {
	// delete files or move them to backup folder
	// todo: check if enough permissions in filesystem are granted to do so
	global $zz_error;
	global $text;
	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$my_tab = &$zz_tab[$uf['i']][$uf['k']];
		foreach ($my_tab['fields'][$uf['f']]['image'] as $img => $val) {
			$path = false;
			$mode = false;
			foreach ($val['path'] as $path_key => $path_value)
				if ($path_key == 'root') $path = $path_value;
				elseif (substr($path_key, 0, 4) == 'mode') $mode[] = $path_value;
				elseif (substr($path_key, 0, 6) == 'string') $path .= $path_value;
				elseif (substr($path_key, 0, 5) == 'field') {
					$content = (!empty($my_tab['old_record'][$path_value]) 
						? $my_tab['old_record'][$path_value] 
						: ''); // todo: check whether something will not be deleted because of an error.
					if (!empty($mode))
						foreach ($mode as $mymode)
							$content = $mymode($content);
					$path.= $content;
				}
				if (file_exists($path) && is_file($path)) {
					if ($zz_conf['backup'])
						$success = rename($path, zz_upload_path($zz_conf['backup_dir'], 'delete', $path));
					else
						$success = unlink($path);
					if (!$success) $zz_error['msg'].= sprintf($text['Could not delete %s.'], $path);
				} elseif(file_exists($path) && !is_file($path))
					$zz_error['msg'].= '<br>'.'Configuration Error [1]: Filename is invalid.';
				elseif ($path && !isset($val['ignore']))
					$zz_error['msg'].= '<br>'.sprintf($text['Could not delete %s, file did not exist.'], $path);
			}
		}
}

function zz_upload_path($dir, $action, $path) {
	$my_base = $dir.'/'.$action.'/';
	check_dir($my_base);
	$i = 0;
	do  { 
		$my_path = $my_base.time().$i.'.'.basename($path);
		$i++;
	} while (file_exists($my_path));
	return $my_path;
}

function zz_upload_write(&$zz_tab, $action, $zz_conf) {
	global $zz_error;
	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$my_tab = &$zz_tab[$uf['i']][$uf['k']];
		$my_tab['POST'][$my_tab['id']['field_name']] = $my_tab['id']['value']; // to catch mysql_insert_id
		foreach ($my_tab['fields'][$uf['f']]['image'] as $img => $val) {
			$image = &$my_tab['images'][$uf['f']][$img]; // reference on image data
			
		//	update
			$mode = false;
			if ($action == 'update') {
				foreach ($val['path'] as $path_key => $path_value) // todo: mode!
					if ($path_key == 'root') {
						$path = $path_value;
						$old_path = $path_value;
					} elseif (substr($path_key, 0, 4) == 'mode')
						$mode[] = $path_value;
					elseif (substr($path_key, 0, 6) == 'string') {
						$path .= $path_value;
						$old_path .= $path_value;
					} elseif (substr($path_key, 0, 5) == 'field') {
						$content = (isset($my_tab['POST'][$path_value])) 
							? zz_reformat_field($my_tab['POST'][$path_value])
							: zz_get_sql_value($path_value, $zz_tab[$uf['i']]['sql'],  $my_tab['id']['value'], $zz_tab[$uf['i']]['table'].'.'.$my_tab['id']['field_name']);
						if (!empty($mode))
							foreach ($mode as $mymode)
								$content = $mymode($content);
						$path .= $content;
						$content = (isset($my_tab['old_record']) 
							? $my_tab['old_record'][$path_value] : '');
						if (!empty($mode))
							foreach ($mode as $mymode)
								$content = $mymode($content);
						$old_path.= $content;
					}
				if ($path != $old_path) {
					$image['files']['update']['path'] = $path; // not necessary maybe, but in case ...
					$image['files']['update']['old_path'] = $old_path; // too
					check_dir(dirname($path));
					if (file_exists($path) && $zz_conf['backup']) // this case should not occur
						rename($path, zz_upload_path($zz_conf['backup_dir'], $action, $path));
					if (file_exists($old_path))
						if ($zz_conf['backup'] && isset($image['files']['tmp_file'])) // new image will be added later on for sure
							rename($old_path, zz_upload_path($zz_conf['backup_dir'], $action, $path));
						else // just path will change
							rename($old_path, $path);
				}
			}

		// insert, update
			if (!empty($image['files']['tmp_file'])) {
				$dest = false;
				$mode = false;
				foreach ($val['path'] as $dest_key => $dest_value) // todo: mode!
					if ($dest_key == 'root') $dest = $dest_value;
					elseif (substr($dest_key, 0, 4) == 'mode') $mode[] = $dest_value;
					elseif (substr($dest_key, 0, 6) == 'string') $dest .= $dest_value;
					elseif (substr($dest_key, 0, 5) == 'field') {
						$content = (!empty($my_tab['POST'][$dest_value])) 
							? zz_reformat_field($my_tab['POST'][$dest_value])
							: zz_get_sql_value($dest_value, $zz_tab[$uf['i']]['sql'], $my_tab['id']['value'], $zz_tab[$uf['i']]['table'].'.'.$my_tab['id']['field_name']);
						if (!empty($mode))
							foreach ($mode as $mymode)
								$content = $mymode($content);
						$dest .= $content;
					}
				$image['files']['destination'] = $dest; // not necessary, just in case ...
				$filename = $image['files']['tmp_file'];
				check_dir(dirname($dest)); // create path if it does not exist
				if (file_exists($dest) && is_file($dest))
					if ($zz_conf['backup'])
						rename($dest, zz_upload_path($zz_conf['backup_dir'], $action, $dest));
					else unlink($dest);
				elseif (file_exists($dest) && !is_file($dest))
					$zz_error['msg'].= '<br>'.'Configuration Error [2]: Filename "'.$dest.'" is invalid.';
				if (!isset($image['source']) && empty($image['action'])) // do this with images which have not been touched
					// todo: error handling!!
					move_uploaded_file($filename, $dest);
				else {
					$success = copy($filename, $dest);
					if (!$success)
						echo 'Copying not successful<br>'.$filename.' '.$dest.'<br>';
				}
			}
		}
	}
}

function zz_upload_cleanup(&$zz_tab) {
	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$my_tab = &$zz_tab[$uf['i']][$uf['k']];
		foreach ($my_tab['fields'][$uf['f']]['image'] as $img => $val) {
			$image = &$my_tab['images'][$uf['f']][$img]; // reference on image data
		// clean up
			if (!empty($image['files']['all_temp']))
				if (count($image['files']['all_temp']))
					foreach ($image['files']['all_temp'] as $file)
						if (file_exists($file) && is_file($file)) unlink($file);
		}
	}
}

function zz_upload_prepare(&$zz_tab, $zz_conf) {
	// check if there is something to write at all!

	// decrease image size if applicable (ImageMagick|GD)
	// perform actions if applicable

	// create path
	// check if path exists, if not, create it
	// check if file_exists, if true, move file to backup-directory, if zz_conf says so
	// no changes: move_uploaded_file to destination directory, write new filename to array in case this image will be needed later on 
	// changes: move changed file to dest. directory
	// on error: return error_message - critical error, because record has already been saved!

	$action = $zz_tab[0][0]['action'];
	
	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$my_tab = &$zz_tab[$uf['i']][$uf['k']];
		foreach ($my_tab['fields'][$uf['f']]['image'] as $img => $val) {
			$image = &$my_tab['images'][$uf['f']][$img]; // reference on image data
		//	read user input
			if (!empty($image['options'])) { // read user input
				if (!is_array($image['options'])) // to make it easier, allow input without array construct as well
					$image['options'] = array($image['options']); 
				foreach ($image['options'] as $optionfield) {
					$option_fieldname = $my_tab['fields'][$optionfield]['field_name']; // field_name of field where options reside
					$options = $my_tab['fields'][$optionfield]['options']; // these are the options from the script
					$option_value = $my_tab['POST'][$option_fieldname]; // this ist the selected option
					$image = array_merge($image, $options[$option_value]); // overwrite values in script with selected option
				}
			}
			if (!isset($image['source']))
				$tmp_name = $image['upload']['tmp_name'];
			else {
				$src_image = $my_tab['images'][$uf['f']][$image['source']];
				if (!empty($image['use_modified_source']))
					$tmp_name = (isset($src_image['files'])
						? $src_image['files']['tmp_file'] : false);
				else
					$tmp_name = $src_image['upload']['tmp_name'];
				$image['upload'] = $src_image['upload']; // get some variables from source image as well
			}
			if (!empty($image['auto']) && !empty($image['auto_values'])) // choose values from uploaded image, best fit
				if ($tmp_name) {
					$autofunc = 'zz_image_auto_'.$image['auto'];
					if (function_exists($autofunc)) $autofunc($image);
					else echo '<br>Configuration Error: Function '.$autofunc.' does not exist.';
				}
			if (!empty($image['ignore'])) continue; // ignore image
			if ($tmp_name && $tmp_name != 'none') { // only if something new was uploaded!
				if (file_exists($tmp_name)) {
					$filename = $tmp_name;
					$all_temp_filenames[] = $tmp_name;
				} else $filename = $src_image['files']['tmp_file']; // if name has been changed
				$tmp_filename = false;
				if (!empty($image['action'])) {
					$tmp_filename = tempnam(realpath($zz_conf['tmp_dir']), "UPLOAD_"); // create temporary file, so that original file remains the same for further actions
					$dest_extension = zz_image_extension($image['path'], $my_tab, $zz_tab, $uf);
					include_once $zz_conf['dir'].'/inc/image-'.$zz_conf['graphics_library'].'.inc.php';
					$image['action'] = 'zz_image_'.$image['action'];
					$image['action']($filename, $tmp_filename, $dest_extension, $image);
					if (file_exists($tmp_filename))	{
						$filename = $tmp_filename;
						$all_temp_filenames[] = $tmp_filename;
						$my_img = getimagesize($tmp_filename);
						$image['modified']['width'] = $my_img[0];
						$image['modified']['height'] = $my_img[1];
						// todo: name, type, ...
					} else echo 'Error: File '.$tmp_filename.' does not exist<br>
						Temporary Directory: '.realpath($zz_conf['tmp_dir']).'<br>';
				}
				$image['files']['tmp_file'] = $filename;
				$image['files']['all_temp'] = $all_temp_filenames;
			}
		}
	}
	// return true or false
	// output errors
}

function zz_image_extension($path, &$my_tab, &$zz_tab, &$uf) {
	foreach ($path as $path_key => $path_value) {// todo: implement mode!
		// move to last, can be done better, of course. todo! no time right now.
	}
	if (substr($path_key, 0, 6) == 'string')
		$extension = substr($path_value, strrpos('.', $path_value)+1);
	elseif (substr($path_key, 0, 5) == 'field') {
		$content = (isset($my_tab['POST'][$path_value])) 
			? zz_reformat_field($my_tab['POST'][$path_value]) : '';
		$extension = substr($content, strrpos($content, '.')+1);
		if (!$extension) { // check for sql-query which gives extension. usual way does not work, because at this stage record is not saved yet.
			foreach (array_keys($my_tab['fields']) as $key)
				if(!empty($my_tab['fields'][$key]['display_field']) && $my_tab['fields'][$key]['display_field'] == $path_value) {
					$sql = $my_tab['fields'][$key]['path_sql'];
					$id_value = zz_reformat_field($my_tab['POST'][$my_tab['fields'][$key]['field_name']]);
					if ($id_value) {
						$result = mysql_query($sql);
						if ($result) if (mysql_num_rows($result) == 1)
							$extension = mysql_result($result, 0, 0);
					} else $extension = false; // no extension could be found,
						// probably due to extension from field which has not been filled yet
						// does not matter, that means that filetype for destination
						// file remains the same.
				}
		}
	} else {
		echo 'Error. Could not determine file ending';
	}
	return $extension;
}

function zz_reformat_field($value) {
	if (substr($value, 0, 1) == '"' AND substr($value, strlen($value) -1) == '"')
		$value = substr($value, 1, strlen($value) -2);
	return $value;
}

function check_dir($my_dir) { 
	// checks if directories above current_dir exist and creates them if necessary
	while (strstr($my_dir, '//'))
		$my_dir = str_replace('//', '/', $my_dir);
	if (substr($my_dir, strlen($my_dir)-1) == '/')	//	removes / from the end
		$my_dir = substr($my_dir, 0, strlen($my_dir)-1);
	if (!file_exists($my_dir)) { //	if dir does not exist, do a recursive check/makedir on parent director[y|ies]
		$upper_dir = substr($my_dir, 0, strrpos($my_dir, '/'));
		$success = check_dir($upper_dir);
		if ($success) {
			$success = mkdir($my_dir, 0777);
			if (!$success) echo 'Creation of '.$my_dir.' failed.<br>';
			//else $success = chown($my_dir, getmyuid());
			//if (!$success) echo 'Change of Ownership of '.$my_dir.' failed.<br>';
			else return true;
		}
		return false;
	} else return true;
}

function zz_get_sql_value($value, $sql, $idvalue = false, $idfield = false) { // gets a value from sql query. 
	if ($idvalue) // if idvalue is not set: note: all values should be the same! First value is taken
		$sql = zz_edit_sql($sql, 'WHERE', $idfield.' = "'.$idvalue.'"');
	$result = mysql_query($sql);
	if ($error = mysql_error())
		echo '<p class="error">Error in zz_get_sql_value: <br>'.$sql.'<br>'.$error.'</p>';
	if ($result) if (mysql_num_rows($result))
		$line = mysql_fetch_assoc($result);
	if (!empty($line[$value])) return $line[$value];
	else return false;
}

///
function zz_upload_get(&$sub_tab, $action, $sql, &$zz_tab, $table) {
	zz_get_upload_fields($zz_tab);
	if ($action == 'delete' OR $action == 'update') {
		$sql = zz_edit_sql($sql, 'WHERE', $table.'.'.$sub_tab['id']['field_name'].' = '.$sub_tab['id']['value']);
		$result = mysql_query($sql);
		if ($result) if (mysql_num_rows($result))
			$sub_tab['old_record'] = mysql_fetch_assoc($result);
		if ($error = mysql_error())
			echo '<p>Error in script: zz_upload_get() <br>'.$sql.'<br>'.$error.'</p>';
	}
	if ($_FILES && $action != 'delete')
		$sub_tab['images'] = zz_check_files($sub_tab);
}

function zz_check_files($my) {
	global $zz_conf;
	$exif_supported = array(
		'image/jpeg',
		'image/pjpeg',
		'image/tiff'
	);
	foreach ($my['fields'] as $key => $field) {
		if (substr($field['type'], 0, 7) == 'upload_') {
			if (!empty($_FILES[$field['field_name']])) {
				$myfiles = $_FILES[$field['field_name']];
				foreach ($field['image'] as $subkey => $image) {
					$images[$key][$subkey] = $image;
					$images[$key]['title'] = (isset($images[$key]['title'])) // this and field_name will be '' if first image is false
						? $images[$key]['title']
						: zz_make_title($myfiles['name'][$image['field_name']]);
					$images[$key]['filename'] = (isset($images[$key]['filename']))
						? $images[$key]['filename']
						: zz_make_name($myfiles['name'][$image['field_name']]); // todo: what if filename is not set? forcefilename filename
					if (!empty($image['field_name'])) {
						$images[$key][$subkey]['upload']['name'] = $myfiles['name'][$image['field_name']];
						$images[$key][$subkey]['upload']['type'] = $myfiles['type'][$image['field_name']];
						$images[$key][$subkey]['upload']['tmp_name'] = $myfiles['tmp_name'][$image['field_name']];
						if (!isset($myfiles['error'][$image['field_name']])) {// PHP 4.1 and prior
							if ($myfiles['tmp_name'][$image['field_name']] == 'none') {
								$images[$key][$subkey]['upload']['error'] = 4; // no file
								$images[$key][$subkey]['upload']['type'] = false; // set to application/octet-stream
								$images[$key][$subkey]['upload']['name'] = false;
								$images[$key][$subkey]['upload']['tmp_name'] = false;
							} elseif ($zz_conf['upload_MAX_FILE_SIZE'] 
								&& $images[$key][$subkey]['upload']['size'] > $zz_conf['upload_MAX_FILE_SIZE']) {
								$images[$key][$subkey]['upload']['error'] = 2; // too big
								$images[$key][$subkey]['upload']['type'] = false; // set to application/octet-stream
								$images[$key][$subkey]['upload']['name'] = false;
								$images[$key][$subkey]['upload']['tmp_name'] = false;
							} else
								$images[$key][$subkey]['upload']['error'] = 0; // everything ok
						} else
							$images[$key][$subkey]['upload']['error'] = $myfiles['error'][$image['field_name']];
						$images[$key][$subkey]['upload']['size'] = $myfiles['size'][$image['field_name']];
						switch ($images[$key][$subkey]['upload']['error']) {
							// constants since PHP 4.3.0!
							case 4: continue 2; // no file (UPLOAD_ERR_NO_FILE)
							case 3: continue 2; // partial upload (UPLOAD_ERR_PARTIAL)
							case 2: continue 2; // file is too big (UPLOAD_ERR_INI_SIZE)
							case 1: continue 2; // file is too big (UPLOAD_ERR_FORM_SIZE)
							case false: break; // everything ok. (UPLOAD_ERR_OK)
						}
						$sizes = getimagesize($myfiles['tmp_name'][$image['field_name']]);
						$images[$key][$subkey]['upload']['width'] = $sizes[0];
						$images[$key][$subkey]['upload']['height'] = $sizes[1];
						if (function_exists('exif_read_data') AND in_array($myfiles['type'][$image['field_name']], $exif_supported))
							$images[$key][$subkey]['upload']['exif'] = exif_read_data($myfiles['tmp_name'][$image['field_name']]);
							
					}
				} 
			}
		}
	}
	return $images;
}

function zz_check_upload(&$images, $action, $zz_conf, $input_filetypes = array()) {
	global $text;
	$error = false;
	if ($input_filetypes)
		if (in_array('image/jpeg', $input_filetypes)) 
			$input_filetypes[] = 'image/pjpeg'; // Internet Explorer renamed this filetype to pjpeg
	foreach (array_keys($images) as $key) {
	//	check if image was uploaded
		if (!is_numeric($key)) continue; //file_name, title
		$images[$key]['error'] = false;
		if (!empty($images[$key]['field_name'])) {
			switch ($images[$key]['upload']['error']) {
				// constants since PHP 4.3.0!
				case 4: // no file (UPLOAD_ERR_NO_FILE)
					if (!empty($images[$key]['required']) && $action == 'insert') // required only for insert
						$images[$key]['error'][] = $text['Error: '].$text['No file was uploaded.'];
					else continue 2;
					break;
				case 3: // partial upload (UPLOAD_ERR_PARTIAL)
					$images[$key]['error'][] = $text['Error: '].$text['File was only partially uploaded.'];
					break; 
				case 2: // file is too big (UPLOAD_ERR_INI_SIZE)
				case 1: // file is too big (UPLOAD_ERR_FORM_SIZE)
					$images[$key]['error'][] = $text['Error: '].$text['File is too big.'].' '.$text['Maximum allowed filesize is'].' '.floor($zz_conf['upload_MAX_FILE_SIZE']/1024).'KB'; // Max allowed
					break; 
				case false: // everything ok. (UPLOAD_ERR_OK)
					break; 
			}
			if ($images[$key]['error']) {
				$error = true;
				continue;
			}
			
	//	check if filetype is allowed
			if ($input_filetypes && !in_array($images[$key]['upload']['type'], $input_filetypes))
				$images[$key]['error'][] = $text['Error: '].$text['Unsupported filetype:'].' '.$images[$key]['upload']['type']
				.'<br>'.$text['Supported filetypes are:'].' '.implode(', ', $input_filetypes);
	
	// 	sometimes MIME types are needed for the database, better change unknown MIME types:
			$unwanted_mime = array('image/pjpeg' => 'image/jpeg'); // Internet Explorer knows progressive JPEG instead of JPEG
			if (in_array($images[$key]['upload']['type'], array_keys($unwanted_mime)))
				$images[$key]['upload']['type'] = $unwanted_mime[$images[$key]['upload']['type']];

	//	check if minimal image size is reached
			$width_height = array('width', 'height');
			foreach ($width_height as $which)
				if (!empty($images[$key]['min_'.$which]) && $images[$key]['min_'.$which] > $images[$key]['upload'][$which])
					$images[$key]['error'][] = $text['Error: '].$text['Minimum '.$which.' was not reached.'].' ('.$images[$key]['min_'.$which].'px)';
	
		}
		if ($images[$key]['error']) $error = true;
	}
	if ($error) return false;
	else return true;
}

function zz_make_title($filename) {
	$filename = preg_replace('/\..{1,4}/', '', $filename);	// remove file extension up to 4 letters
	$filename = str_replace('_', ' ', $filename);			// make output more readable
	$filename = str_replace('.', ' ', $filename);			// make output more readable
	$filename = ucfirst($filename);
	return $filename;
}

function zz_make_name($filename) {
	$filename = preg_replace('/\..{1,4}/', '', $filename);	// remove file extension up to 4 letters
	$filename = forceFilename($filename);
	return $filename;
}

function zz_image_auto_size(&$image) {
	//	basics
	$tolerance = (!empty($image['auto_size_tolerance']) ? $image['auto_size_tolerance'] : 15); // tolerance in px
	$width = $image['upload']['width'];
	$height = $image['upload']['height'];
	$ratio = $width/$height;
	$key = 0;
	foreach ($image['auto_values'] as $pair) {
		$my = false;
		$my['width'] = $pair[0];
		$my['height'] = $pair[1];
		$my['size'] = $pair[0]*$pair[1];
		$my['ratio'] = $pair[0]/$pair[1];
		if ($pair[0] > ($pair[1] + $tolerance)) { 
			$pair[0] -= $tolerance;
			$pair[1] += $tolerance;
		} elseif (($pair[0] + $tolerance) < $pair[1]) {
			$pair[0] += $tolerance;
			$pair[1] -= $tolerance;
		} else $pair[0] = $pair[1]; // if == or in a range of $tolerance, ratio_tolerated will be 1
		$my['ratio_tolerated'] = $pair[0]/$pair[1];
		$pairs[$key] = $my;
		if (empty($smallest) OR $smallest['size'] > $my['size']) {
			$smallest['key'] = $key;
			$smallest['size'] = $my['size'];
		}
		$key++;
	}
	unset($pair);
	
	//	check which pairs will be acceptable (at least one dimension bigger than given dimensions plus tolerance)
	foreach ($pairs as $pair)
		if (($pair['height'] - $tolerance) <= $height OR ($pair['width'] - $tolerance) <= $width)
			$acceptable_pairs[] = $pair;
	if (empty($acceptable_pairs)) { // Houston, we've got a problem
		// return field with smallest size
		$image['width'] = $pairs[$smallest['key']]['width'];
		$image['height'] = $pairs[$smallest['key']]['height'];
		return true;
	}

	// check for best ratio
	foreach ($pairs as $key => $pair) {
		if (empty($best_pair) || is_better_ratio($ratio, $best_pair['ratio'], $pair['ratio'])) {
			$best_pair['key'] = $key;
			$best_pair['ratio'] = $pair['ratio'];
			$best_pair['size'] = $pair['size'];
		}
	}
	
	// check whether there's more than one field with the same ratio
	foreach ($pairs as $key => $pair)
		if ($pair['ratio'] == $best_pair['ratio'] && $pair['size'] > $best_pair['size']) {
			$best_pair['key'] = $key;
			$best_pair['size'] = $pair['size'];
		}

	// these values will be returned (&$image)
	$image['width'] = $pairs[$best_pair['key']]['width'];
	$image['height'] = $pairs[$best_pair['key']]['height'];
	return true;
}

function is_better_ratio($ratio, $old_ratio, $new_ratio) { // returns true if new_ratio is better
	if ($ratio > 1) {
		if ($old_ratio > $ratio AND $new_ratio < $old_ratio) return true; // ratio too big, small always better
		if ($old_ratio < $ratio AND $new_ratio <= $ratio AND $new_ratio > $old_ratio) return true; // closer to ratio, better
	} elseif ($ratio == 1) {
		$distance_of_old = ($old_ratio >= 1 ? $old_ratio : 1/$old_ratio);
		$distance_of_new = ($new_ratio >= 1 ? $new_ratio : 1/$new_ratio);
		if ($distance_of_old > $distance_of_new) return true; // closer to 1
	} else { // smaller than 1
		if ($old_ratio < $ratio AND $new_ratio > $old_ratio) return true; // ratio too small, bigger always better
		if ($old_ratio > $ratio AND $new_ratio >= $ratio AND $new_ratio < $old_ratio) return true; // closer to ratio, better
	}
	return false;
}

function zz_get_upload_val(&$sub_tab, $path_value, &$image) {
	foreach ($sub_tab['fields'] as $key => $field)
		if ($field['field_name'] == $path_value)
			if (isset($field['upload_value']))
				$my_value = $field['upload_value'];
	if (empty($my_value)) return false;
	if (isset($image['upload'][$my_value]))
		return $image['upload'][$my_value];
	if (preg_match('/.+\[.+\]/', $my_value)) { // construct access to array values
		$myv = explode('[', $my_value);
		foreach ($myv as $v_var) {
			if (substr($v_var, strlen($v_var) -1) == ']') $v_var = substr($v_var, 0, strlen($v_var) - 1);
			$v_arr[] = $v_var;
		}
		eval('$myval = $my[\'images\'][$g][0][\'upload\'][\''.implode("']['", $v_arr).'\'];');
		if (!$myval) eval('$myval = $my[\'images\'][$g][0][\''.implode("']['", $v_arr).'\'];');
		if ($myval) return $myval;
	}
	return false;
}

/*

exif_imagetype()
When a correct signature is found, the appropriate constant value will be
returned otherwise the return value is FALSE. The return value is the same
value that getimagesize() returns in index 2 but exif_imagetype() is much
faster.

Value	Constant
1	IMAGETYPE_GIF
2	IMAGETYPE_JPEG
3	IMAGETYPE_PNG
4	IMAGETYPE_SWF
5	IMAGETYPE_PSD
6	IMAGETYPE_BMP
7	IMAGETYPE_TIFF_II (intel byte order)
8	IMAGETYPE_TIFF_MM (motorola byte order)
9	IMAGETYPE_JPC
10	IMAGETYPE_JP2
11	IMAGETYPE_JPX
12	IMAGETYPE_JB2
13	IMAGETYPE_SWC
14	IMAGETYPE_IFF
15	IMAGETYPE_WBMP
16	IMAGETYPE_XBM

http://gustaf.local/phpdoc/function.exif-read-data.html
http://gustaf.local/phpdoc/function.exif-thumbnail.html

bei umbenennen und file_exists true:
filetype um zu testen, ob es sich um eine datei oder ein verzeichnis etc. handelt.

is_uploaded_file -- PrŸft, ob die Datei mittels HTTP POST upgeloaded wurde
move_uploaded_file -- ggf. vorher Zieldatei auf Existenz ŸberprŸfen und sichern

tmpfile ( void ) um eine temporŠre Datei anzulegen, ggf. s. tempnam
(neues Bild anlegen)

test: function_exists fuer php-imagick-funktionen, sonst ueber exec

lesen:
http://gustaf.local/phpdoc/ref.image.html

max_size:
ini_get('post_max_size'), wenn das kleiner ist, Warnung ausgeben! 
(oder geht das per ini_set einzustellen?)

mime_content_type -- Detect MIME Content-type for a file

set_time_limit, falls safe_mode off ggf. anwenden

imagick shell:
- escapeshellarg()
- wie programm im hintergrund laufen lassen?

ggf. mehrere Dateien hochladbar, die gleich behandelt werden (Massenupload)?


Kuer:
-------

testen, ob verzeichnis schreibbar fuer eigene gruppe/eigenen user
(777 oder gruppenspezifisch)
filegroup ($filename)
posix_getgrgid($group_id) 
fileowner()
posix_getpwuid() 
fileperms($filename)
get_current_user -- zeigt den aktuellen User an, der PHP nutzt, getmygid Gruppe, getmyuid

disk_free_space($dir) testen, ob's reicht?!

*/

?>