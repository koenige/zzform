<?php 


/*
	zzform Scripts
	image upload

	(c) Gustaf Mossakowski <gustaf@koenige.org> 2006
*/

/*		----------------------------------------------
 *						DESCRIPTION
 *		---------------------------------------------- */

/*
	1. main functions (in order in which they are called)

	zz_upload_get()				writes arrays upload_fields, images, old_record
								i. e. checks which fields offer uploads,
								collects and writes information about files
								reads old record from database before update
		zz_upload_get_fields()	checks which fields allow upload
		zz_upload_check_files()	checks files, read information (filesize, exif 
									etc.), puts information to 'image' array
			zz_upload_make_title()	converts filename to title
			zz_upload_make_name()	converts filename to better filename
	zz_upload_prepare()			prepares files for upload (resize, rotate etc.)
		zz_upload_extension()	gets extension
	zz_upload_check()			validates file input (upload errors, requirements)
	zz_upload_action()			diverts to zz_upload_write or _delete		
		zz_upload_write()		writes files after successful sql insert/update
			zz_upload_sqlval()	
		zz_upload_delete()		deletes files after successful sql delete
	zz_upload_cleanup()			cleanup after files have been moved or deleted

	2. additional functions

	zz_upload_path()			creates unique name for file (?)
	zz_upload_reformat_field()	removes "" from validated field, if neccessary	
	zz_upload_checkdir()		creates new directory and upper dirs as well

	3. zz_tab array
	
	global
	$zz_tab[0]['upload_fields'][0]['i']
	$zz_tab[0]['upload_fields'][0]['k']
	$zz_tab[0]['upload_fields'][0]['f']
	$zz_tab[0]['upload_fields'][1]['i'] ...

	subtable, currently only 0 0 supported
	$zz_tab[0][0]['images']
	$zz_tab[0][0]['images'][n]['title']
	$zz_tab[0][0]['images'][n]['filename']
	values from table definition + option
	$zz_tab[0][0]['images'][n][0]['title']
	$zz_tab[0][0]['images'][n][0]['field_name']
	$zz_tab[0][0]['images'][n][0]['path']
	$zz_tab[0][0]['images'][n][0][...]
	upload values from PHP form
	$zz_tab[0][0]['images'][n][0]['upload']['name']	local filename
	$zz_tab[0][0]['images'][n][0]['upload']['type'] mimetype, as browser sends it
	$zz_tab[0][0]['images'][n][0]['upload']['tmp_name'] temporary filename on server
	$zz_tab[0][0]['images'][n][0]['upload']['error'] errorcode, 0 = no error
	own upload values
	$zz_tab[0][0]['images'][n][0]['upload']['size']		filesize
	$zz_tab[0][0]['images'][n][0]['upload']['width']	width in px
	$zz_tab[0][0]['images'][n][0]['upload']['height']	height in px
	$zz_tab[0][0]['images'][n][0]['upload']['exif']		exif data

	$zz_tab[0][0]['images'][n][0]['upload']['filetype']	Filetype
	$zz_tab[0][0]['images'][n][0]['upload']['ext']		file extension
	$zz_tab[0][0]['images'][n][0]['upload']['mime']		MimeType
	$zz_tab[0][0]['images'][n][0]['upload']['imagick_format']	ImageMagick_Format
	$zz_tab[0][0]['images'][n][0]['upload']['imagick_mode']		ImageMagick_Mode
	$zz_tab[0][0]['images'][n][0]['upload']['imagick_desc']		ImageMagick_Description
	$zz_tab[0][0]['images'][n][0]['upload']['validated']	validated (yes = tested, no = rely on fileupload i. e. user)

	$zz_tab[0][0]['old_record']
	
*/

/*		----------------------------------------------
 *					VARIABLES
 *		---------------------------------------------- */


$zz_default['backup'] 			= false;	//	backup uploaded files?
$zz_default['backup_dir'] 		= $zz_conf['dir'].'/backup';	//	directory where backup will be put into
$zz_default['tmp_dir']			= false;
$zz_default['graphics_library'] = 'imagemagick';
$zz_default['imagemagick_paths'] = array('/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/phpbin'); 
$zz_conf['upload_ini_max_filesize'] = ini_get('upload_max_filesize');
switch (substr($zz_conf['upload_ini_max_filesize'], strlen($zz_conf['upload_ini_max_filesize'])-1)) {
	case 'G': $zz_conf['upload_ini_max_filesize'] *= pow(1024, 3); break;
	case 'M': $zz_conf['upload_ini_max_filesize'] *= pow(1024, 2); break;
	case 'K': $zz_conf['upload_ini_max_filesize'] *= pow(1024, 1); break;
}
$zz_default['upload_MAX_FILE_SIZE']	= $zz_conf['upload_ini_max_filesize'];

// mimetypes, hardcoded in php

$zz_default['image_types'] = array(
	1 =>  array('mime' => 'image/gif', 'ext' => 'gif'),				// 1	IMAGETYPE_GIF
	2 =>  array('mime' => 'image/jpeg', 'ext' => 'jpeg'),			// 2	IMAGETYPE_JPEG
	3 =>  array('mime' => 'image/png', 'ext' => 'png'),				// 3	IMAGETYPE_PNG
	4 =>  array('mime' => 'application/x-shockwave-flash', 'ext' => 'swf'),	// 4	IMAGETYPE_SWF
	5 =>  array('mime' => 'image/psd', 'ext' => 'psd'),				// 5	IMAGETYPE_PSD
	6 =>  array('mime' => 'image/bmp', 'ext' => 'bmp'),				// 6	IMAGETYPE_BMP
	7 =>  array('mime' => 'image/tiff', 'ext' => 'tiff'),			// 7	IMAGETYPE_TIFF_II (intel byte order)
	8 =>  array('mime' => 'image/tiff', 'ext' => 'tiff'),			// 8	IMAGETYPE_TIFF_MM (motorola byte order)
	9 =>  array('mime' => 'application/octet-stream', 'ext' => 'jpc'),		// 9	IMAGETYPE_JPC	>= PHP 4.3.2
	10 => array('mime' => 'image/jp2', 'ext' => 'jp2'),				// 10	IMAGETYPE_JP2	>= PHP 4.3.2
	11 => array('mime' => 'application/octet-stream', 'ext' => 'jpf'),		// 11	IMAGETYPE_JPX	>= PHP 4.3.2
	12 => array('mime' => 'application/octet-stream', 'ext' => 'jb2'),		// 12	IMAGETYPE_JB2	>= PHP 4.3.2
	13 => array('mime' => 'application/x-shockwave-flash', 'ext' => 'swc'),	// 13	IMAGETYPE_SWC	>= PHP 4.3.0
	14 => array('mime' => 'image/iff', 'ext' => 'aiff'),			// 14	IMAGETYPE_IFF
	15 => array('mime' => 'image/vnd.wap.wbmp', 'ext' => 'wbmp'),	// 15	IMAGETYPE_WBMP	>= PHP 4.3.2
	16 => array('mime' => 'image/xbm', 'ext' => 'xbm')				// 16	IMAGETYPE_XBM	>= PHP 4.3.2
);
foreach (array_keys($zz_default['image_types']) as $key) {
	$zz_default['image_types'][$key]['filetype'] = $zz_default['image_types'][$key]['ext'];
}

// unwanted mimetypes and their replacements

$zz_default['mime_types_rewritten'] = array(
	'image/pjpeg' => 'image/jpeg', 	// Internet Explorer knows progressive JPEG instead of JPEG
	'image/x-png' => 'image/png'	// Internet Explorer
); 

/*		----------------------------------------------
 *					MAIN FUNCTIONS
 *		---------------------------------------------- */

/** writes arrays upload_fields, images, old_record
 *
 * i. e. checks which fields offer uploads, 
 * collects and writes information about files
 * reads old record from database before update
 * 1- get faster access to upload fields
 * 2- retrieve current record from db 
 *    -- todo: what happens if someone simultaneously accesses this record
 * 3- get 'images' array with information about each file
 * 
 * @param $zz_tab(array) complete table data
 * @return array $zz_tab[0]['upload_fields']
 * @return array $zz_tab[0]['old_record']
 * @return array $zz_tab[0][0]['images']
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */


function zz_upload_get(&$zz_tab, $i, $k) {
	global $zz_conf;
	include_once $zz_conf['dir'].'/inc/image-'.$zz_conf['graphics_library'].'.inc.php';
	// currently, upload fields are only possible for main table
	$action = $zz_tab[0][0]['action'];
	$sql = $zz_tab[0]['sql'];
	$table = $zz_tab[$i]['table'];
	$sub_tab = &$zz_tab[$i][$k];
	
	// create array upload_fields in $zz_tab[0] to get fast access to upload fields
	zz_upload_get_fields($zz_tab);

	// in case of deletion or update, save old record to be able
	// to get old filename before deletion or update
	if ($action == 'delete' OR $action == 'update') {
		$sql = zz_edit_sql($sql, 'WHERE', $table.'.'.$sub_tab['id']['field_name']
			.' = '.$sub_tab['id']['value']);
		$result = mysql_query($sql);
		if ($result) if (mysql_num_rows($result))
			$sub_tab['old_record'] = mysql_fetch_assoc($result);
		if ($error = mysql_error())
			echo '<p>Error in script: zz_upload_get() <br>'.$sql
				.'<br>'.$error.'</p>';
	}
	//	read information of files, put into 'images'-array
	if ($_FILES && $action != 'delete')
		$sub_tab['images'] = zz_upload_check_files($sub_tab);
}

/** checks which fields allow file upload
 * @param $zz_tab(array) complete table data
 * @return writes array in $zz_tab[0]['upload_fields'] with i, k, and f in $zz_tab[$i][$k]['fields'][$f]
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_get_fields(&$zz_tab) {
	$upload_fields = false;
	foreach (array_keys($zz_tab) as $i) {
		foreach (array_keys($zz_tab[$i]) as $k) {
			if (!is_int($i) OR !is_int($k)) continue;
			foreach ($zz_tab[$i][$k]['fields'] as $f => $field)
				if ($field['type'] == 'upload_image') {
					$my = false;
					$my['i'] = $i;
					$my['k'] = $k;
					$my['f'] = $f;
					$upload_fields[] = $my;
				}
		}
	}
	$zz_tab[0]['upload_fields'] = $upload_fields;
}

/** checks which files allow file upload
 * @param $my(array) $zz_tab[$i][$k]
 * @return array multidimensional information about images
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_check_files($my) {
	global $zz_conf;
	$exif_supported = array(
		'image/jpeg',
		'image/pjpeg',
		'image/tiff'
	);
	foreach ($my['fields'] as $key => $field) {
		if (substr($field['type'], 0, 7) == 'upload_' && !empty($_FILES[$field['field_name']])) {
			$myfiles = $_FILES[$field['field_name']];
			foreach ($field['image'] as $subkey => $image) {
				$images[$key][$subkey] = $field['image'][$subkey];
				if (empty($image['field_name'])) continue; // don't do the rest if field_name is not set
				$images[$key]['title'] = (!empty($images[$key]['title'])) // this and field_name will be '' if first image is false
					? $images[$key]['title']
					: zz_upload_make_title($myfiles['name'][$image['field_name']]);
				$images[$key]['filename'] = (!empty($images[$key]['filename']))
					? $images[$key]['filename']
					: zz_upload_make_name($myfiles['name'][$image['field_name']]); // todo: what if filename is not set? forcefilename filename
				
				$images[$key][$subkey]['upload']['name'] = $myfiles['name'][$image['field_name']];
				$images[$key][$subkey]['upload']['type'] = $myfiles['type'][$image['field_name']];
				
				$oldfilename = $myfiles['tmp_name'][$image['field_name']];
				$extension = strtolower(substr($images[$key][$subkey]['upload']['name'], strrpos($images[$key][$subkey]['upload']['name'], '.')+1));
				if ($oldfilename) {
					$myfilename = $oldfilename.'.'.$extension;
					move_uploaded_file($oldfilename, $myfilename);
				} else
					$myfilename = false;
				$images[$key][$subkey]['upload']['tmp_name'] = $myfilename;
				
				if (!isset($myfiles['error'][$image['field_name']])) {// PHP 4.1 and prior
					if ($myfilename == 'none') {
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
				// determine filetype and file extension
				// 1. use reliable functions
				// 1a. getimagesize
				// 1b. exif_imagetype
				// 1c. todo: finfo_file, see: http://www.php.net/manual/en/function.finfo-file.php
				// 		(c. relies on magic.mime file)
				// 1d. use identify in imagemagick
				// 2. if this is impossible, check for file extension
				
				// check whether filesize is above 2 bytes or it will give a read error
				$images[$key][$subkey]['upload']['validated'] = false;
				if ($images[$key][$subkey]['upload']['size'] >= 3) { 
					// 1a.
					// 1b.
					if (function_exists('getimagesize')) {
						$sizes = getimagesize($myfilename);
						if ($sizes && !empty($zz_conf['image_types'][$sizes[2]])) {
							$images[$key][$subkey]['upload']['width'] = $sizes[0];
							$images[$key][$subkey]['upload']['height'] = $sizes[1];
							$images[$key][$subkey]['upload']['ext'] = $zz_conf['image_types'][$sizes[2]]['ext'];
							$images[$key][$subkey]['upload']['mime'] = $zz_conf['image_types'][$sizes[2]]['mime'];
							$images[$key][$subkey]['upload']['filetype'] = $zz_conf['image_types'][$sizes[2]]['filetype'];
							$images[$key][$subkey]['upload']['validated'] = true;
							$tested_filetypes = array();
						}
					} 
					if (!$images[$key][$subkey]['upload']['validated'] && function_exists('exif_imagetype')) {// < 4.3.0
						$imagetype = exif_imagetype($myfilename);
						if ($imagetype && !empty($zz_conf['image_types'][$imagetype])) {
							$images[$key][$subkey]['upload']['ext'] = $zz_conf['image_types'][$imagetype]['ext'];
							$images[$key][$subkey]['upload']['mime'] = $zz_conf['image_types'][$imagetype]['mime'];
							$images[$key][$subkey]['upload']['filetype'] = $zz_conf['image_types'][$imagetype]['filetype'];
							$images[$key][$subkey]['upload']['validated'] = true;
							$tested_filetypes = array();
						}
					} 
					if ($zz_conf['graphics_library'] == 'imagemagick') {
						$images[$key][$subkey]['upload'] = array_merge(
							$images[$key][$subkey]['upload'], zz_imagick_identify($myfilename));
					}
					if (!$images[$key][$subkey]['upload']['validated']) {
						$images[$key][$subkey]['upload']['ext'] = $extension;
						$images[$key][$subkey]['upload']['mime'] = $images[$key][$subkey]['upload']['type'];
						$images[$key][$subkey]['upload']['filetype'] = $extension; // todo: get from extension and mime!
						// test whether one of the functions above already tested for that mimetype.
					}	
					if (function_exists('exif_read_data') AND in_array($myfiles['type'][$image['field_name']], $exif_supported))
						$images[$key][$subkey]['upload']['exif'] = exif_read_data($myfilename);
				}
				$myfilename = false;
			}
		}
	}
	return $images;
}

/** converts filename to human readable string
 * 
 * @param $filename(string) filename
 * @return string title
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_make_title($filename) {
	$filename = preg_replace('/\..{1,4}/', '', $filename);	// remove file extension up to 4 letters
	$filename = str_replace('_', ' ', $filename);			// make output more readable
	$filename = str_replace('.', ' ', $filename);			// make output more readable
	$filename = ucfirst($filename);
	return $filename;
}

/** converts filename to wanted filename
 * 
 * @param $filename(string) filename
 * @return string filename
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_make_name($filename) {
	$filename = preg_replace('/\..{1,4}/', '', $filename);	// remove file extension up to 4 letters
	$filename = forceFilename($filename);
	return $filename;
}


/** prepares files for upload (resize, rotate etc.)
 * 
 * 1- checks user input via option fields
 * 2- checks which source has to be used (own source, other fields source)
 *    gets further information from source if neccessary
 * 3- calls auto functions if set
 * 4- skips rest of procedure on if ignore is set
 * 5- perform actions (e. g. decrease size, rotate etc.) on file if applicable,
 *    modified files will get temporary filename
 *
 * @param $zz_tab(array) complete table data
 * @param $zz_conf(array) configuration variables
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_prepare(&$zz_tab, $zz_conf) {
	$action = $zz_tab[0][0]['action'];
	
	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$my_tab = &$zz_tab[$uf['i']][$uf['k']];
		foreach ($my_tab['fields'][$uf['f']]['image'] as $img => $val) {
			$image = &$my_tab['images'][$uf['f']][$img]; // reference on image data
			//	read user input via options
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
						? $src_image['files']['tmp_files'][$image['source']] : false);
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
				} else $filename = $src_image['files']['tmp_files'][$image['source']]; // if name has been changed
				$tmp_filename = false;
				if (!empty($image['action'])) {
					$tmp_filename = tempnam(realpath($zz_conf['tmp_dir']), "UPLOAD_"); // create temporary file, so that original file remains the same for further actions
					$dest_extension = zz_upload_extension($image['path'], $zz_tab, $uf);
					$image['action'] = 'zz_image_'.$image['action'];
					$image['action']($filename, $tmp_filename, $dest_extension, $image);
					if (file_exists($tmp_filename))	{
						if (filesize($tmp_filename) > 3) {
							$filename = $tmp_filename;
							$all_temp_filenames[] = $tmp_filename;
							$my_img = getimagesize($tmp_filename);
							$image['modified']['width'] = $my_img[0];
							$image['modified']['height'] = $my_img[1];
							// todo: name, type, ...
						}
					} else echo 'Error: File '.$tmp_filename.' does not exist<br>
						Temporary Directory: '.realpath($zz_conf['tmp_dir']).'<br>';
				}
				$image['files']['tmp_files'][$img] = $filename;
				if (!empty($image['files']['all_temp']))
					$image['files']['all_temp'] = array_merge($image['files']['all_temp'], $all_temp_filenames);
				else
					$image['files']['all_temp'] = $all_temp_filenames; // for later cleanup of leftover tmp files
			}
		}
	}
	// return true or false
	// output errors
}

/** get file extension 
 * 
 * @param $path(array)
 * @param $zz_tab(array)
 * @param $uf(string)
 * @return $extension
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_extension($path, &$zz_tab, &$uf) {
	$my_tab = &$zz_tab[$uf['i']][$uf['k']];
	foreach ($path as $path_key => $path_value) {// todo: implement mode!
		// move to last, can be done better, of course. todo! no time right now.
	}
	if (substr($path_key, 0, 6) == 'string')
		$extension = substr($path_value, strrpos('.', $path_value)+1);
	elseif (substr($path_key, 0, 5) == 'field') {
		$content = (isset($my_tab['POST'][$path_value])) 
			? zz_upload_reformat_field($my_tab['POST'][$path_value]) : '';
		$extension = substr($content, strrpos($content, '.')+1);
		if (!$extension) { // check for sql-query which gives extension. usual way does not work, because at this stage record is not saved yet.
			foreach (array_keys($my_tab['fields']) as $key)
				if(!empty($my_tab['fields'][$key]['display_field']) && $my_tab['fields'][$key]['display_field'] == $path_value) {
					$sql = $my_tab['fields'][$key]['path_sql'];
					$id_value = zz_upload_reformat_field($my_tab['POST'][$my_tab['fields'][$key]['field_name']]);
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

/** checks uploads for conformity and problems
 * 
 * @param $images(array) $zz_tab[$i][$k]['images']
 * @param $action(string) sql action (insert|delete|update)
 * @param $zz_conf(array) configuration variables
 * @param $input_filetypes(array) array with allowed filetypes for input, e. g. 'image/png'
 * @return bool true/false
 * @return $images might change as well (?)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_check(&$images, $action, $zz_conf, $input_filetypes = array()) {
	global $text;
	$error = false;
	if ($input_filetypes) {
		if (in_array('image/jpeg', $input_filetypes)) 
			$input_filetypes[] = 'image/pjpeg'; // Internet Explorer treats progressive jpeg separately
		if (in_array('image/png', $input_filetypes)) 
			$input_filetypes[] = 'image/x-png'; // Internet Explorer
	}
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
			if (!empty($images[$key]['input_filetypes'])) {
				if (!is_array($images[$key]['input_filetypes']))
					$images[$key]['input_filetypes'] = array($images[$key]['input_filetypes']);
				if (!in_array($images[$key]['upload']['filetype'], $images[$key]['input_filetypes'])) {
					$images[$key]['error'][] = $text['Error: '].$text['Unsupported filetype:'].' '.$images[$key]['upload']['filetype']
					.'<br>'.$text['Supported filetypes are:'].' '.implode(', ', $images[$key]['input_filetypes']);
					$error = true;
					continue; // do not go on and do further checks, because filetype is wrong anyways
				}
			} elseif ($input_filetypes && !in_array($images[$key]['upload']['type'], $input_filetypes)) {
				$images[$key]['error'][] = $text['Error: '].$text['Unsupported filetype:'].' '.$images[$key]['upload']['type']
				.'<br>'.$text['Supported filetypes are:'].' '.implode(', ', $input_filetypes);
				$error = true;
				continue; // do not go on and do further checks, because filetype is wrong anyways
			}
			
	
	// 	sometimes MIME types are needed for the database, better change unknown MIME types:
			$unwanted_mime = array(
				'image/pjpeg' => 'image/jpeg', 	// Internet Explorer knows progressive JPEG instead of JPEG
				'image/x-png' => 'image/png'	// Internet Explorer
			); 
			if (in_array($images[$key]['upload']['type'], array_keys($unwanted_mime)))
				$images[$key]['upload']['type'] = $unwanted_mime[$images[$key]['upload']['type']];

	//	check if minimal image size is reached
			$width_height = array('width', 'height');
			foreach ($width_height as $which)
				if (!empty($images[$key]['min_'.$which]) && $images[$key]['min_'.$which] > $images[$key]['upload'][$which])
					$images[$key]['error'][] = $text['Error: ']
						.sprintf($text['Minimum '.$which.' %s was not reached.'], '('.$images[$key]['min_'.$which].'px)')
						.' ('.$images[$key]['upload'][$which].'px)';

	//	check if maximal image size has not been exceeded
			$width_height = array('width', 'height');
			foreach ($width_height as $which)
				if (!empty($images[$key]['max_'.$which]) && $images[$key]['max_'.$which] < $images[$key]['upload'][$which])
					$images[$key]['error'][] = $text['Error: ']
						.sprintf($text['Maximum '.$which.' %s has been exceeded.'], '('.$images[$key]['max_'.$which].'px)')
						.' ('.$images[$key]['upload'][$which].'px)';
	
		}
		if ($images[$key]['error']) $error = true;
	}
	if ($error) return false;
	else return true;
}

/** Moves or deletes file after successful SQL operations
 * 
 * called from within function zz_action
 * @param $zz_tab(array) complete table data
 * @param $zz_conf(array) configuration variables
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_action(&$zz_tab, $zz_conf) {
	global $zz_error;
	if ($zz_tab[0][0]['action'] != 'delete')
		zz_upload_write($zz_tab, $zz_tab[0][0]['action'], $zz_conf);
	else
		zz_upload_delete($zz_tab, $zz_conf);
}

/** Writes files after successful SQL operations
 * 
 * if backup variable is set to true, script will move old files to backup folder
 * called from within function zz_upload_action
 * @param $zz_tab(array) complete table data
 * @param $action(string) action: update or something else
 * @param $zz_conf(array) configuration variables
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_write(&$zz_tab, $action, $zz_conf) {

	// create path
	// check if path exists, if not, create it
	// check if file_exists, if true, move file to backup-directory, if zz_conf says so
	// no changes: move_uploaded_file to destination directory, write new filename to array in case this image will be needed later on 
	// changes: move changed file to dest. directory
	// on error: return error_message - critical error, because record has already been saved!

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
							? zz_upload_reformat_field($my_tab['POST'][$path_value])
							: zz_upload_sqlval($path_value, $zz_tab[$uf['i']]['sql'],  $my_tab['id']['value'], $zz_tab[$uf['i']]['table'].'.'.$my_tab['id']['field_name']);
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
					} // no else, webroot will be ignored
				if ($path != $old_path) {
					$image['files']['update']['path'] = $path; // not necessary maybe, but in case ...
					$image['files']['update']['old_path'] = $old_path; // too
					zz_upload_checkdir(dirname($path));
					if (file_exists($path) && $zz_conf['backup']) // this case should not occur
						rename($path, zz_upload_path($zz_conf['backup_dir'], $action, $path));
					if (file_exists($old_path))
						if ($zz_conf['backup'] && isset($image['files']['tmp_files'][$img])) // new image will be added later on for sure
							rename($old_path, zz_upload_path($zz_conf['backup_dir'], $action, $path));
						else // just path will change
							rename($old_path, $path);
				}
			}

		// insert, update
			if (!empty($image['files']['tmp_files'][$img])) {
				$dest = false;
				$mode = false;
				foreach ($val['path'] as $dest_key => $dest_value) // todo: mode!
					if ($dest_key == 'root') $dest = $dest_value;
					elseif (substr($dest_key, 0, 4) == 'mode') $mode[] = $dest_value;
					elseif (substr($dest_key, 0, 6) == 'string') $dest .= $dest_value;
					elseif (substr($dest_key, 0, 5) == 'field') {
						$content = (!empty($my_tab['POST'][$dest_value])) 
							? zz_upload_reformat_field($my_tab['POST'][$dest_value])
							: zz_upload_sqlval($dest_value, $zz_tab[$uf['i']]['sql'], $my_tab['id']['value'], $zz_tab[$uf['i']]['table'].'.'.$my_tab['id']['field_name']);
						if (!empty($mode))
							foreach ($mode as $mymode)
								$content = $mymode($content);
						$dest .= $content;
					} // no else, webroot will be ignored
				$image['files']['destination'] = $dest; // not necessary, just in case ...
				$filename = $image['files']['tmp_files'][$img];
				zz_upload_checkdir(dirname($dest)); // create path if it does not exist
				if (file_exists($dest) && is_file($dest))
					if ($zz_conf['backup'])
						rename($dest, zz_upload_path($zz_conf['backup_dir'], $action, $dest));
					else unlink($dest);
				elseif (file_exists($dest) && !is_file($dest))
					$zz_error['msg'].= '<br>'.'Configuration Error [2]: Filename "'.$dest.'" is invalid.';
				if (!isset($image['source']) && empty($image['action'])) { // do this with images which have not been touched
					// todo: error handling!!
					rename($filename, $dest);
					chmod($dest, 0644);
				} else {
					$success = copy($filename, $dest);
					chmod($dest, 0644);
					if (!$success)
						echo 'Copying not successful<br>'.$filename.' '.$dest.'<br>';
				}
			}
		}
	}
}

/** get value needed for upload from sql query
 * 
 * @param $value
 * @param $sql
 * @param $idvalue
 * @param $idfield
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_sqlval($value, $sql, $idvalue = false, $idfield = false) { // gets a value from sql query. 
	if ($idvalue) // if idvalue is not set: note: all values should be the same! First value is taken
		$sql = zz_edit_sql($sql, 'WHERE', $idfield.' = "'.$idvalue.'"');
	$result = mysql_query($sql);
	if ($error = mysql_error())
		echo '<p class="error">Error in zz_upload_sqlval: <br>'.$sql.'<br>'.$error.'</p>';
	if ($result) if (mysql_num_rows($result))
		$line = mysql_fetch_assoc($result);
	if (!empty($line[$value])) return $line[$value];
	else return false;
}

/** Deletes files after successful SQL operations
 * 
 * if backup variable is set to true, script will move old files to backup folder
 * called from within function zz_upload_action
 * todo: checki if enough permissions in filesystem are granted to do so
 * @param $zz_tab(array) complete table data
 * @param $zz_conf(array) configuration variables
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_delete($zz_tab, $zz_conf) {
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
				} // no else: webroot will be ignored because it's just for webstuff
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

/** Creates unique filename from backup dir, action and file path
 * 
 * called form zz_upload_write and zz_upload_delete
 * @param $dir(string) backup directory
 * @param $action(string) sql action
 * @param $path(string) file path
 * @return unique filename ? path?
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_path($dir, $action, $path) {
	$my_base = $dir.'/'.$action.'/';
	zz_upload_checkdir($my_base);
	$i = 0;
	do  { 
		$my_path = $my_base.time().$i.'.'.basename($path);
		$i++;
	} while (file_exists($my_path));
	return $my_path;
}

/** Remove unused files from upload process
 * 
 * called form zz_action
 * @param $zz_tab(array) table data
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
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

/*		----------------------------------------------
 *						FUNCTIONS
 *		---------------------------------------------- */

/** Remove "" from validated field input
 * 
 * @param $value(string) value that will be checked and reformatted
 * @return string checked value, reformatted
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_reformat_field($value) {
	if (substr($value, 0, 1) == '"' AND substr($value, strlen($value) -1) == '"')
		$value = substr($value, 1, strlen($value) -2);
	return $value;
}

/** Creates new directory (and dirs above, if neccessary)
 * 
 * @param $my_dir(string) directory to be created
 * @return bool true/false = successful/fail
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_checkdir($my_dir) { 
	// checks if directories above current_dir exist and creates them if necessary
	while (strstr($my_dir, '//'))
		$my_dir = str_replace('//', '/', $my_dir);
	if (substr($my_dir, strlen($my_dir)-1) == '/')	//	removes / from the end
		$my_dir = substr($my_dir, 0, strlen($my_dir)-1);
	if (!file_exists($my_dir)) { //	if dir does not exist, do a recursive check/makedir on parent director[y|ies]
		$upper_dir = substr($my_dir, 0, strrpos($my_dir, '/'));
		$success = zz_upload_checkdir($upper_dir);
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

/*		----------------------------------------------
 *				IMAGE FUNCTIONS (zz_image...)
 *		---------------------------------------------- */
/* 	will be called via 'auto_size' */

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

/*		----------------------------------------------
 *						TODO
 *		---------------------------------------------- */


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
/*

* kennung generieren (dateiname ohne endung, exif, was passiert bei mehreren 
	dateien, forcefilename)
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

?>