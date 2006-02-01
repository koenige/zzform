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

function zz_upload_action($zz_tab, $zz_conf) {
	global $zz_error;
	if ($zz_tab[0][0]['action'] != 'delete')
		zz_upload_write($zz_tab, $zz_tab[0][0]['action'], $zz_conf);
	else
		zz_upload_delete($zz_tab, $zz_conf);
}

function zz_upload_delete($zz_tab, $zz_conf) {
	// delete files or move them to backup folder
	// todo: check if enough permissions in filesystem are granted to do so
	global $zz_error;
	global $text;
	foreach (array_keys($zz_tab) as $i)
		foreach (array_keys($zz_tab[$i]) as $k) {
			if (!is_int($i) OR !is_int($k)) continue;
			foreach ($zz_tab[$i][$k]['fields'] as $f => $field)
				if ($field['type'] == 'upload_image')
					foreach ($field['image'] as $img => $val) {
						$path = false;
						foreach ($val['path'] as $path_key => $path_value)
							if ($path_key == 'root') $path = $path_value;
							elseif (substr($path_key, 0, 6) == 'string') $path .= $path_value;
							elseif (substr($path_key, 0, 5) == 'field') $path .= $zz_tab[$i][$k]['old_record'][$path_value];
						$path = realpath($path);
						if (file_exists($path)) {
							if ($zz_conf['backup']) {
								check_dir($zz_conf['backup_dir'].'/delete');
								$success = rename($path, $zz_conf['backup_dir'].'/delete/'.time().'.'.basename($path));
							} else
								$success = unlink($path);
							if (!$success) $zz_error['msg'].= sprintf($text['Could not delete %s.'], $path);
						} else $zz_error['msg'].= sprintf($text['Could not delete %s, file did not exist.'], $path);
					}
		}
}

function zz_upload_write($zz_tab, $action, $zz_conf) {
	// check if there is something to write at all!

	// decrease image size if applicable (ImageMagick|GD)
	// perform actions if applicable
	
	// create path
	// check if path exists, if not, create it
	// check if file_exists, if true, move file to backup-directory, if zz_conf says so
	// no changes: move_uploaded_file to destination directory, write new filename to array in case this image will be needed later on 
	// changes: move changed file to dest. directory
	// on error: return error_message - critical error, because record has already been saved!
	foreach (array_keys($zz_tab) as $i)
		foreach (array_keys($zz_tab[$i]) as $k) {
			if (!is_int($i) OR !is_int($k)) continue;
			foreach ($zz_tab[$i][$k]['fields'] as $f => $field)
				if ($field['type'] == 'upload_image')
					foreach ($field['image'] as $img => $val) {
						$image = $zz_tab[$i][$k]['images'][$f][$img];
						if (empty($image['source']))
							$tmp_name = $image['upload']['tmp_name'];
						else {
							$src_image = $zz_tab[$i][$k]['images'][$f][$image['source']];
							$tmp_name = $src_image['upload']['tmp_name'];
						}
						if ($action == 'update') {
							foreach ($val['path'] as $path_key => $path_value)
								if ($path_key == 'root') {
									$path = $path_value;
									$old_path = $path_value;
								} elseif (substr($path_key, 0, 6) == 'string') {
									$path .= $path_value;
									$old_path .= $path_value;
								} elseif (substr($path_key, 0, 5) == 'field') {
									$path .= (isset($zz_tab[$i][$k]['POST'][$path_value])) 
										? zz_reformat_field($zz_tab[$i][$k]['POST'][$path_value])
										: zz_get_sql_value($path_value, $zz_tab[$i]['sql']);
									$old_path .= $zz_tab[$i][$k]['old_record'][$path_value];
								}
							if ($path != $old_path) {
								check_dir(dirname($path));
								if (file_exists($path) && $zz_conf['backup']) { // this case should not occur
									check_dir($zz_conf['backup_dir'].'/'.$action);
									rename($path, $zz_conf['backup_dir'].'/'.$action.'/'.time().'.'.basename($path));
								}
								if ($tmp_name) { // new image will be added later on for sure
									check_dir($zz_conf['backup_dir'].'/'.$action);
									rename($old_path, $zz_conf['backup_dir'].'/'.$action.'/'.time().'.'.basename($old_path));
								} else // just path will change
									rename($old_path, $path);
							}
						}
						if ($tmp_name) { // only if something new was uploaded!
							//echo 'tmp '.$i.'/'.$k.': '.$tmp_name.'<br>';
							if (file_exists($tmp_name)) $filename = $tmp_name;
							else $filename = $src_image['new_filename']; // if name has been changed
							$tmp_filename = false;
							if (!empty($image['action'])) {
								$tmp_filename = tempnam($zz_conf['tmp_dir'], "UPLOAD_");
								include_once $zz_conf['dir'].'/inc/image-'.$zz_conf['graphics_library'].'.inc.php';
								$image['action'] = 'zz_image_'.$image['action'];
								$image['action']($filename, $tmp_filename);
								if (file_exists($tmp_filename))	$filename = $tmp_filename;
								else echo 'Error: File '.$tmp_filename.' does not exist';
							}
							$path = false;
							foreach ($val['path'] as $path_key => $path_value)
								if ($path_key == 'root') $path = $path_value;
								elseif (substr($path_key, 0, 6) == 'string') $path .= $path_value;
								elseif (substr($path_key, 0, 5) == 'field') $path .= 
									(isset($zz_tab[$i][$k]['POST'][$path_value])) 
									? zz_reformat_field($zz_tab[$i][$k]['POST'][$path_value])
									: zz_get_sql_value($path_value, $zz_tab[$i]['sql']);
							check_dir(dirname($path)); // create path if it does not exist
							//$path = realpath($path);
							if (file_exists($path))
								if ($zz_conf['backup']) {
									check_dir($zz_conf['backup_dir'].'/'.$action);
									rename($path, $zz_conf['backup_dir'].'/'.$action.'/'.time().'.'.basename($path));
								} else unlink($path);
							if (empty($image['source'])) { // todo: error handling!!
								move_uploaded_file($filename, $path);
								$zz_tab[$i][$k]['images'][$f][$img]['new_filename'] = $path;
							} else {
								$success = copy($filename, $path);
								if (!$success) {
									echo 'Copying not successful<br>';
									echo $filename.' '.$path.'<br>';
								}
							}
							if ($tmp_filename) unlink($tmp_filename); // clean up
						}
					}
		}
	// return true or false
	// output errors
}

function zz_reformat_field($value) {
	if (substr($value, 0, 1) == '"' AND substr($value, strlen($value) -1) == '"')
		$value = substr($value, 1, strlen($value) -2);
	return $value;
}

function check_dir($my_dir) { 
	// checks if directories above current_dir exist and creates them if necessary
	if (substr($my_dir, strlen($my_dir)-1) == '/')	//	removes / from the end
		$my_dir = substr($my_dir, 0, strlen($my_dir)-1);
	if (!file_exists($my_dir)) { //	if dir does not exist, do a recursive check/makedir on parent director[y|ies]
		$upper_dir = substr($my_dir, 0, strrpos($my_dir, '/'));
		$success = check_dir($upper_dir);
		if ($success) {
			mkdir($my_dir);
			return true;
		}
		return false;
	} else return true;
}

function zz_get_sql_value($value, $sql) { // gets a value from sql query. note: all values should be the same! First value is taken
	$result = mysql_query($sql);
	if ($result) if (mysql_num_rows($result))
		$line = mysql_fetch_assoc($result);
	if (!empty($line[$value])) return $line[$value];
	else return false;
}

function zz_get_upload(&$zz_tab, $action, $sql) {
	if ($action == 'delete' OR $action == 'update') {
		if (stristr($sql, ' WHERE ')) $sql.= ' AND ';
		else $sql.= ' WHERE ';
		$sql.= $zz_tab['id']['field_name'].' = '.$zz_tab['id']['value'];
		$result = mysql_query($sql);
		echo mysql_error();
		if ($result) if (mysql_num_rows($result))
			$zz_tab['old_record'] = mysql_fetch_assoc($result);
	}
	if ($_FILES && $action != 'delete')
		$zz_tab['images'] = zz_check_files($zz_tab);
}

function zz_check_files($my) {
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
						$images[$key][$subkey]['upload']['error'] = $myfiles['error'][$image['field_name']];
						$images[$key][$subkey]['upload']['size'] = $myfiles['size'][$image['field_name']];
						switch ($myfiles['error'][$image['field_name']]) {
							// constants since PHP 4.3.0!
							case 4: continue; // no file (UPLOAD_ERR_NO_FILE)
							case 3: continue; // partial upload (UPLOAD_ERR_PARTIAL)
							case 2: continue; // file is too big (UPLOAD_ERR_INI_SIZE)
							case 1: continue; // file is too big (UPLOAD_ERR_FORM_SIZE)
							case false: break; // everything ok. (UPLOAD_ERR_OK)
						}
						$sizes = getimagesize($myfiles['tmp_name'][$image['field_name']]);
						$images[$key][$subkey]['upload']['width'] = $sizes[0];
						$images[$key][$subkey]['upload']['height'] = $sizes[1];
						if (in_array($myfiles['type'][$image['field_name']], $exif_supported))
							$images[$key][$subkey]['upload']['exif'] = exif_read_data($myfiles['tmp_name'][$image['field_name']]);
							
					}
					// here: convert image, write back to array 'convert' in $images
					// size, if applicable convert to grayscale etc.
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
					$images[$key]['error'][] = $text['Error: '].$text['File is too big.'].' '.$text['Maximum allowed filesize is'].' '.floor($zz_conf['upload']['MAX_FILE_SIZE']/1024).'KB'; // Max allowed
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
				.'<br>'.$text['Supported filetypes are:'].' '.implode(',', $input_filetypes);

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