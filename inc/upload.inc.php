<?php 


/*
	zzform Scripts
	image upload

	(c) Gustaf Mossakowski <gustaf@koenige.org> 2006-2008
*/

/*	----------------------------------------------	*
 *					DESCRIPTION						*
 *	----------------------------------------------	*/

/*
	1. main functions (in order in which they are called)

	zz_upload_get()				writes arrays upload_fields, images
								i. e. checks which fields offer uploads,
								collects and writes information about files
		zz_upload_get_fields()	checks which fields allow upload
		zz_upload_check_files()	checks files, read information (filesize, exif 
									etc.), puts information to 'image' array
			zz_upload_make_title()	converts filename to title
			zz_upload_make_name()	converts filename to better filename
			zz_upload_mimecheck()	checks whether supposed mimetype was already checked for
			zz_upload_filecheck()	gets filetype from list
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
	zz_upload_getfiletypes()	reads filetypes from txt-file

	3. zz_tab array
	
	global
	$zz_tab[0]['upload_fields'][n]['i']
	$zz_tab[0]['upload_fields'][n]['k']
	$zz_tab[0]['upload_fields'][n]['f'] ...

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
	own upload values, read from image
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

/*	----------------------------------------------	*
 *					VARIABLES						*
 *	----------------------------------------------	*/

global $zz_conf;

$zz_default['backup'] 			= false;	//	backup uploaded files?
$zz_default['backup_dir'] 		= $zz_conf['dir'].'/backup';	//	directory where backup will be put into
if (ini_get('upload_tmp_dir'))
	$zz_default['tmp_dir']		= ini_get('upload_tmp_dir');
else
	$zz_default['tmp_dir'] = false;
$zz_default['graphics_library'] = 'imagemagick';
$zz_default['imagemagick_paths'] = array('/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/phpbin'); 
$zz_conf['upload_ini_max_filesize'] = ini_get('upload_max_filesize');
switch (substr($zz_conf['upload_ini_max_filesize'], -1)) {
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
foreach (array_keys($zz_default['image_types']) as $key)
	$zz_default['image_types'][$key]['filetype'] = $zz_default['image_types'][$key]['ext'];

$zz_default['file_types'] = zz_upload_getfiletypes($zz_conf['dir'].'/inc/filetypes.txt');

// unwanted mimetypes and their replacements

$zz_default['mime_types_rewritten'] = array(
	'image/pjpeg' => 'image/jpeg', 	// Internet Explorer knows progressive JPEG instead of JPEG
	'image/x-png' => 'image/png'	// Internet Explorer
); 

$zz_default['exif_supported'] = array('jpeg', 'tiff');

/*	----------------------------------------------	*
 *					MAIN FUNCTIONS					*
 *	----------------------------------------------	*/

/** writes arrays upload_fields, images
 *
 * i. e. checks which fields offer uploads, 
 * collects and writes information about files
 * 1- get faster access to upload fields
 * 2- get 'images' array with information about each file
 * 
 * @param $zz_tab(array) complete table data
 * @return array $zz_tab[0]['upload_fields']
 * @return array $zz_tab[0][0]['images']
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_get(&$zz_tab) {
	global $zz_conf;
	if ($zz_conf['graphics_library'])
		include_once $zz_conf['dir'].'/inc/image-'.$zz_conf['graphics_library'].'.inc.php';
	
	// create array upload_fields in $zz_tab[0] to get easy access to upload fields
	$zz_tab[0]['upload_fields'] = zz_upload_get_fields($zz_tab); // n = (i =>, k =>, f =>)

	//	read information of files, put into 'images'-array
	if ($_FILES && $zz_tab[0][0]['action'] != 'delete')
		zz_upload_check_files($zz_tab);
}

/** checks which fields allow file upload
 * @param $zz_tab(array) complete table data
 * @return $upload_fields with i, k, and f in $zz_tab[$i][$k]['fields'][$f]
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_get_fields(&$zz_tab) {
	$upload_fields = false;
	foreach (array_keys($zz_tab) as $i)
		foreach (array_keys($zz_tab[$i]) as $k) {
			if (!is_int($i) OR !is_int($k)) continue;
			foreach ($zz_tab[$i][$k]['fields'] as $f => $field)
				if ($field['type'] == 'upload_image')
					$upload_fields[] = array('i' => $i, 'k' => $k, 'f' => $f);
		}
	return $upload_fields;
}

/** checks which files allow file upload
 * @param $my(array) $zz_tab[$i][$k]
 * @return array multidimensional information about images
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_check_files(&$zz_tab) {
	global $zz_conf;
	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$my_tab = &$zz_tab[$uf['i']][$uf['k']];
		$images = false;
		$key = $uf['f'];
		$field = $my_tab['fields'][$key];
		if (empty($_FILES[$field['field_name']])) {
			$my_tab['images'] = $images;
			continue;
		}
		$myfiles = &$_FILES[$field['field_name']];
		foreach ($field['image'] as $subkey => $image) {
			$images[$key][$subkey] = $field['image'][$subkey];
			if (empty($image['field_name'])) continue; // don't do the rest if field_name is not set
			// title, generated from local filename, to be used for 'upload_value'
			$images[$key]['title'] = (!empty($images[$key]['title'])) 
				// this and field_name will be '' if first image is false
				? $images[$key]['title']
				: zz_upload_make_title($myfiles['name'][$image['field_name']]);
			// local filename, extension (up to 4 letters) removed, to be used for 'upload_value'
			$images[$key]['filename'] = (!empty($images[$key]['filename']))
				? $images[$key]['filename']
				: zz_upload_make_name($myfiles['name'][$image['field_name']]); 
			
			$images[$key][$subkey]['upload']['name'] = $myfiles['name'][$image['field_name']];
			$images[$key][$subkey]['upload']['type'] = $myfiles['type'][$image['field_name']];
			
			// add extension to temporary filename (important for image manipulations,
			// e. g. imagemagick can only recognize .ico-files if they end in .ico)
			$oldfilename = $myfiles['tmp_name'][$image['field_name']];
			$extension = strtolower(substr($images[$key][$subkey]['upload']['name'], 
				strrpos($images[$key][$subkey]['upload']['name'], '.')+1));
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
			if ($myfiles['size'][$image['field_name']] < 3) { // file is to small or 0, might occur while incorrect refresh of browser
				$images[$key][$subkey]['upload']['error'] = 4; // no file
				if (file_exists($images[$key][$subkey]['upload']['tmp_name']))
					zz_unlink_cleanup($images[$key][$subkey]['upload']['tmp_name']);
				$images[$key][$subkey]['upload']['tmp_name'] = false;
				$images[$key][$subkey]['upload']['type'] = false;
				$images[$key][$subkey]['upload']['name'] = false;
			} else
				$images[$key][$subkey]['upload']['size'] = $myfiles['size'][$image['field_name']];
			switch ($images[$key][$subkey]['upload']['error']) {
				// constants since PHP 4.3.0!
				case 4: continue 2; // no file (UPLOAD_ERR_NO_FILE)
				case 3: continue 2; // partial upload (UPLOAD_ERR_PARTIAL)
				case 2: continue 2; // file is too big (UPLOAD_ERR_INI_SIZE)
				case 1: continue 2; // file is too big (UPLOAD_ERR_FORM_SIZE)
				case false: break; // everything ok. (UPLOAD_ERR_OK)
			}
/*
	determine filetype and file extension
	1. use reliable functions
	1a. getimagesize
	1b. exif_imagetype (despite being faster than getimagesize, this function comes
		second, because getimagesize also reads width and height)
		
		exif_imagetype()
		When a correct signature is found, the appropriate constant value will be
		returned otherwise the return value is FALSE. The return value is the same
		value that getimagesize() returns in index 2 but exif_imagetype() is much
		faster.

	1c. todo: finfo_file, see: http://www.php.net/manual/en/function.finfo-file.php
			(c. relies on magic.mime file)
	1d. use identify in imagemagick
	2. if this is impossible, check for file extension
*/				

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
				if (!$images[$key][$subkey]['upload']['validated'] && function_exists('exif_imagetype')) {// > 4.3.0
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
					$temp_imagick = zz_imagick_identify($myfilename);
					if ($temp_imagick)
					$images[$key][$subkey]['upload'] = array_merge(
						$images[$key][$subkey]['upload'], $temp_imagick);
				}
				// TODO: allow further file testing here, e. g. for PDF, DXF
				// and others, go for Identifying Characters.
				// maybe use magic_mime_type()
				if (!$images[$key][$subkey]['upload']['validated']) {
					if (zz_upload_mimecheck($images[$key][$subkey]['upload']['type'], $extension)) {
						// Error: this mimetype/extension combination was already checked against
						$images[$key][$subkey]['upload']['ext'] = 'unknown-'.$extension;
						$images[$key][$subkey]['upload']['mime'] = 'unknown';
						$images[$key][$subkey]['upload']['filetype'] = 'unknown';
					} else {
						$filetype = zz_upload_filecheck($images[$key][$subkey]['upload']['type'], $extension);
						if ($filetype) {
							$images[$key][$subkey]['upload']['ext'] = $filetype['ext'];
							$images[$key][$subkey]['upload']['mime'] = $filetype['mime'];
							$images[$key][$subkey]['upload']['filetype'] = $filetype['filetype'];
						} else {
							$images[$key][$subkey]['upload']['ext'] = 'unknown-'.$extension;
							$images[$key][$subkey]['upload']['mime'] = 'unknown: '.$images[$key][$subkey]['upload']['type'];
							$images[$key][$subkey]['upload']['filetype'] = 'unknown';
						}
					}
				}	
				if (function_exists('exif_read_data') 
					AND in_array($images[$key][$subkey]['upload']['filetype'], $zz_conf['exif_supported']))
					$images[$key][$subkey]['upload']['exif'] = exif_read_data($myfilename);
				// TODO: further functions, e. g. zz_pdf_read_data if filetype == pdf ...
				// TODO: or read AutoCAD Version from DXF, DWG, ...
				// TODO: or read IPCT data.
			}

			$myfilename = false;
		}
		$my_tab['images'] = $images;
	}
}

/** converts filename to human readable string
 * 
 * @param $filename(string) filename
 * @return string title
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_make_title($filename) {
	$filename = preg_replace('/\.[a-zA-Z0-9]*$/', '', $filename);	// remove file extension up to 4 letters
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
	$filename = preg_replace('/\.[a-zA-Z0-9]*$/', '', $filename);	// remove file extension up to 4 letters
	$filename = forceFilename($filename);
	return $filename;
}

/** checks whether a given combination of mimetype and extension exists
 * 
 * @param $mimetype(string) mime type
 * @param $extension(string) file extension
 * @return boolean
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_mimecheck($mimetype, $extension) {
	global $zz_conf;
	if (in_array($mimetype, $zz_conf['mime_types_rewritten']))
		$mimetype = $zz_conf['mime_types_rewritten'][$mimetype];
	foreach ($zz_conf['image_types'] as $imagetype)
		if ($imagetype['mime'] == $mimetype AND $imagetype['ext'] == $extension)
			return true;
	return false;
}

function zz_upload_filecheck($mimetype, $extension) {
	global $zz_conf;
	$type1 = false;
	$type2 = false;
	$type2unique = true;
	$type3 = false;
	$type3unique = true;
	foreach ($zz_conf['file_types'] as $filetype)
		if ($filetype['ext_old'] == $extension AND $filetype['mime'] == $mimetype)
			$type1 = $filetype;
		elseif ($filetype['ext_old'] == $extension) {
			if ($type2) $type2unique = false;
			else $type2 = $filetype;
		} elseif ($filetype['mime'] == $mimetype) {
			if ($type3) $type3unique = false;
			else $type3 = $filetype;
		}
	if ($type1) 
		return $type1;	// first priority: mimetype AND extension match
	if ($type2 && $type2unique) 
		return $type2;	// second priority: extension matches AND is unique
	if ($type3 && $type3unique) 
		return $type3;	// third priority: mimetype matches AND is unique
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
				if (!is_array($image['options'])) 
					// to make it easier, allow input without array construct as well
					$image['options'] = array($image['options']); 
				foreach ($image['options'] as $optionfield) {
					// field_name of field where options reside
					$option_fieldname = $my_tab['fields'][$optionfield]['field_name']; 
					// these are the options from the script
					$options = $my_tab['fields'][$optionfield]['options'];
					// this is the selected option
					$option_value = $my_tab['POST'][$option_fieldname]; 
					// overwrite values in script with selected option
					$image = array_merge($image, $options[$option_value]); 
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
			$is_image = true;
			// only do the following things with images!
			if (!empty($image['upload']['mime'])) { // check whether some file was uploaded
				$is_image = explode('/', $image['upload']['mime']);
				$is_image = ($is_image[0] == 'image' ? true : false);
			}
//			if (!is_array($image['input_filetypes'])) $image['input_filetypes'] = array($image['input_filetypes']);
//			if ((isset($image['source']) AND !$is_image) 
//				OR (!empty($image['input_filetypes']) AND 
//				!in_array($image['upload']['filetype'], $image['input_filetypes']))) {
//				continue; // no thumbnail images from non-image files
//			}
			if (!empty($image['auto']) && !empty($image['auto_values'])) 
				// choose values from uploaded image, best fit
				if ($tmp_name) {
					$autofunc = 'zz_image_auto_'.$image['auto'];
					if (function_exists($autofunc)) $autofunc($image);
					else echo '<br>Configuration Error: Function <code>'.$autofunc.'()</code> does not exist.';
				}
			if (!empty($image['ignore'])) continue; // ignore image
			if ($tmp_name && $tmp_name != 'none') { // only if something new was uploaded!
				if (file_exists($tmp_name)) {
					$filename = $tmp_name;
					$all_temp_filenames[] = $tmp_name;
				} else $filename = $src_image['files']['tmp_files'][$image['source']]; // if name has been changed
				$tmp_filename = false;
				if (!empty($image['action']) AND $is_image) { // image operations only for images
					// create temporary file, so that original file remains the same for further actions
					$tmp_filename = tempnam(realpath($zz_conf['tmp_dir']), "UPLOAD_"); 
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
						}  else {
							// ELSE: if image-action did not work out the way it should have.
							$filename = false; // do not upload anything
							// TODO: mark existing image for deletion if there is one!							
							$image['delete_thumbnail'] = true;
							if ($zz_conf['debug']) 
								echo 'No real file was returned from <code>'.$image['action'].'()</code><br>';
						}
					} else echo 'Error: File '.$tmp_filename.' does not exist<br>
						Temporary Directory: '.realpath($zz_conf['tmp_dir']).'<br>';
				}
				$image['files']['tmp_files'][$img] = $filename;
				if (!empty($my_tab['images'][$uf['f']]['all_temp']))
					$my_tab['images'][$uf['f']]['all_temp'] = array_merge($my_tab['images'][$uf['f']]['all_temp'], $all_temp_filenames);
				else
					$my_tab['images'][$uf['f']]['all_temp'] = $all_temp_filenames; // for later cleanup of leftover tmp files
				$all_temp_filenames = array();
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
	if (substr($path_key, 0, 6) == 'string') {
		if (strstr($path_value, '.'))
			$extension = substr($path_value, strrpos($path_value, '.')+1);
		else
			$extension = $path_value;
	} elseif (substr($path_key, 0, 5) == 'field') {
		$content = (isset($my_tab['POST'][$path_value])) 
			? zz_upload_reformat_field($my_tab['POST'][$path_value]) : '';
		if (strstr($content, '.'))
			$extension = substr($content, strrpos($content, '.')+1);
		else
			$extension = $content;
		if (!$extension) { 
			// check for sql-query which gives extension. usual way does not work, 
			// because at this stage record is not saved yet.
			foreach (array_keys($my_tab['fields']) as $key)
				if(!empty($my_tab['fields'][$key]['display_field']) 
					&& $my_tab['fields'][$key]['display_field'] == $path_value) {
					$sql = $my_tab['fields'][$key]['path_sql'];
					$id_value = zz_upload_reformat_field($my_tab['POST'][$my_tab['fields'][$key]['field_name']]);
					if ($id_value) {
						$result = mysql_query($sql.$id_value);
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
		if (strstr($input_filetypes[0], 'image/') 
			OR strstr($input_filetypes[0], 'application/')) { // deprecated version, please change
			echo '<p class="error">Error: Deprecated use of MIME types in input_filetypes. Please use filetypes instead.</p>';
		}
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
					$images[$key]['error'][] = $text['Error: '].$text['File is too big.'].' '
						.$text['Maximum allowed filesize is'].' '
						.floor($zz_conf['upload_MAX_FILE_SIZE']/1024).'KB'; // Max allowed
					break; 
				case false: // everything ok. (UPLOAD_ERR_OK)
					break; 
			}
			if ($images[$key]['error']) {
				$error = true;
				continue;
			}
			
	//	check if filetype is allowed
			if (empty($images[$key]['input_filetypes']))
				$images[$key]['input_filetypes'] = $input_filetypes;

			if (!is_array($images[$key]['input_filetypes']))
				$images[$key]['input_filetypes'] = array($images[$key]['input_filetypes']);
			if (!in_array($images[$key]['upload']['filetype'], $images[$key]['input_filetypes'])) {
				$images[$key]['error'][] = $text['Error: ']
				.$text['Unsupported filetype:'].' '
				.$images[$key]['upload']['filetype']
				.'<br>'.$text['Supported filetypes are:'].' '
				.implode(', ', $images[$key]['input_filetypes']);
				$error = true;
				continue; // do not go on and do further checks, because filetype is wrong anyways
			}

	//	check if minimal image size is reached
			$width_height = array('width', 'height');
			foreach ($width_height as $which)
				if (!empty($images[$key]['min_'.$which]) 
					&& $images[$key]['min_'.$which] > $images[$key]['upload'][$which])
					$images[$key]['error'][] = $text['Error: ']
						.sprintf($text['Minimum '.$which
						.' %s was not reached.'], '('.$images[$key]['min_'.$which].'px)')
						.' ('.$images[$key]['upload'][$which].'px)';

	//	check if maximal image size has not been exceeded
			$width_height = array('width', 'height');
			foreach ($width_height as $which)
				if (!empty($images[$key]['max_'.$which])
					&& $images[$key]['max_'.$which] < $images[$key]['upload'][$which])
					$images[$key]['error'][] = $text['Error: ']
						.sprintf($text['Maximum '.$which
						.' %s has been exceeded.'], '('.$images[$key]['max_'.$which].'px)')
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
	if (!empty($_POST['zz_delete_file'])) 
		zz_upload_delete_file($zz_tab, $zz_tab[0][0]['action'], $zz_conf);
	if ($zz_tab[0][0]['action'] != 'delete')
		zz_upload_write($zz_tab, $zz_tab[0][0]['action'], $zz_conf);
	else
		zz_upload_delete($zz_tab, $zz_conf);
}

/** Deletes files when specifically requested (e. g. in multiple upload forms)
 * 
 * called from within function zz_upload_action
 * @param $zz_tab(array) complete table data
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_delete_file(&$zz_tab, $action, $zz_conf) {
	foreach ($_POST['zz_delete_file'] as $keys => $status) {
		if ($status != 'on') return false; // checkbox checked
		$keys = explode('-', $keys);
		$field = (int) $keys[0];
		$image = (int) $keys[1];
		if (empty($zz_tab[0][0]['images'][$field][$image])) {
			return false; // impossible, might be manipulation or so
		}
		$val = &$zz_tab[0][0]['images'][$field][$image];
		// new path is not interesting, old picture shall be deleted
		$old_path = zz_makepath($val['path'], $zz_tab, 'old', 'file', 0, 0);
		if (file_exists($old_path)) { // just a precaution for e. g. simultaneous access
			if ($zz_conf['backup'])
				rename($old_path, zz_upload_path($zz_conf['backup_dir'], $action, $old_path));
			else
				unlink($old_path);
		}
		foreach ($zz_tab[0][0]['images'][$field] as $key => $other_image) {
			if (is_numeric($key) && isset($other_image['source']) && $other_image['source'] == $image) {
				$old_path = zz_makepath($other_image['path'], $zz_tab, 'old', 'file', 0, 0);
				if (file_exists($old_path)) { // just a precaution for e. g. simultaneous access
					if ($zz_conf['backup'])
						rename($old_path, zz_upload_path($zz_conf['backup_dir'], $action, $old_path));
					else
						unlink($old_path);
				}
			}
		}
		// remove images which base on this image as well (source = $image)
	}
	return true;
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
	// no changes: move_uploaded_file to destination directory, write new filename to 
	//		array in case this image will be needed later on 
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
				$path = zz_makepath($val['path'], $zz_tab, 'new', 'file', $uf['i'], $uf['k']);
				$old_path = zz_makepath($val['path'], $zz_tab, 'old', 'file', $uf['i'], $uf['k']);
				if (!empty($zz_tab[0]['folder']))
					foreach ($zz_tab[0]['folder'] as $folder) {
						// escape foldername, preg_match delimiters will
						// be replaced with \/
						$folder['old_e'] = str_replace('/', '\\/', $folder['old']);
						if (preg_match('/^'.$folder['old_e'].'/', $old_path))
							$old_path = preg_replace('/^('.$folder['old_e'].')/', $folder['new'], $old_path);
					}
				if ($path != $old_path) {
					$image['files']['update']['path'] = $path; // not necessary maybe, but in case ...
					$image['files']['update']['old_path'] = $old_path; // too
					zz_upload_checkdir(dirname($path));
					if (file_exists($path) && $zz_conf['backup']) // this case should not occur
						rename($path, zz_upload_path($zz_conf['backup_dir'], $action, $path));
					if (file_exists($old_path))
						if ($zz_conf['backup'] && isset($image['files']['tmp_files'][$img])) 
							// new image will be added later on for sure
							rename($old_path, zz_upload_path($zz_conf['backup_dir'], $action, $path));
						else // just path will change
							rename($old_path, $path);
				}
			}

		// insert, update
			if (!empty($image['files']['tmp_files'][$img])) {
				$dest = zz_makepath($val['path'], $zz_tab, 'new', 'file', $uf['i'], $uf['k']);
				$image['files']['destination'] = $dest; // not necessary, just in case ...
				$filename = $image['files']['tmp_files'][$img];
				if (file_exists($dest) && is_file($dest))
					if ($zz_conf['backup']) {
						rename($dest, zz_upload_path($zz_conf['backup_dir'], $action, $dest));
						zz_cleanup_dirs(dirname($dest));
					} else zz_unlink_cleanup($dest);
				elseif (file_exists($dest) && !is_file($dest))
					$zz_error[]['msg'] = '<br>'.'Configuration Error [2]: Filename "'
						.$dest.'" is invalid.';
				zz_upload_checkdir(dirname($dest)); // create path if it does not exist or if cleanup removed it.
				if (!isset($image['source']) && empty($image['action'])) { 
					// do this with images which have not been touched
					// todo: error handling!!
					copy($filename, $dest);		// instead of rename:
					if (!file_exists($dest)) {
						if (!is_writeable(dirname($dest))) 
							echo '<br>Insufficient rights. Directory <code>'.dirname($dest).'</code> is not writeable.';
						else 
							echo '<br>Unknown error. Copying not successful<br>from: '.$filename.'<br>to: '.$dest.'<br>';
					}
					zz_unlink_cleanup($filename);			// this also works in older php versions between partitions.
					chmod($dest, 0644);
				} else {
				//	echo '<br>FILENAME: %'.$filename.'% DEST %'.$dest.'<br>';
					$success = copy($filename, $dest);
					chmod($dest, 0644);
					if (!$success)
						echo '<br>
						Copying not successful<br>from: '.$filename.'<br>to: '.$dest.'<br>';
				}
			} else {
				// ok, no thumbnail image, so in this case delete existing thumbnails
				// if there are any
				if (!empty($image['delete_thumbnail']) AND !empty($old_path)) {
					zz_unlink_cleanup($old_path); // delete old thumbnail
				}
			}

		// TODO: EXIF or ICPT write operations go here!
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
		$my_tab = $zz_tab[$uf['i']][$uf['k']];
		foreach ($my_tab['fields'][$uf['f']]['image'] as $img => $val) {
			$path = zz_makepath($val['path'], $zz_tab, 'old', 'file', $uf['i'], $uf['k']);
			$localpath = zz_makepath($val['path'], $zz_tab, 'old', 'local', $uf['i'], $uf['k']);
			if (file_exists($path) && is_file($path)) {
				if ($zz_conf['backup']) {
					$success = rename($path, zz_upload_path($zz_conf['backup_dir'], 'delete', $path));
					zz_cleanup_dirs(dirname($path));
				} else
					$success = zz_unlink_cleanup($path);
				if (!$success) 
					$zz_error[]['msg'] = sprintf($text['Could not delete %s.'], $path);
			} elseif(file_exists($path) && !is_file($path))
				$zz_error[]['msg'] = '<br>'.'Configuration Error [1]: Filename is invalid.';
			elseif ($path && empty($val['ignore']) 
				&& empty($my_tab['fields'][$uf['f']]['optional_image'])
				&& empty($val['optional_image'])) { // optional images: don't show error message!
				$zz_error[]['msg'] = '<br>'.sprintf($text['Could not delete %s, file did not exist.'], $localpath);
			}
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
		if (!empty($zz_tab[$uf['i']][$uf['k']]['images'][$uf['f']]['all_temp']))
			foreach ($zz_tab[$uf['i']][$uf['k']]['images'][$uf['f']]['all_temp'] as $file)
				if (file_exists($file) && is_file($file)) 
					zz_unlink_cleanup($file);
	}
}

/*	----------------------------------------------	*
 *					FUNCTIONS						*
 *	----------------------------------------------	*/

/** Remove "" from validated field input
 * 
 * @param $value(string) value that will be checked and reformatted
 * @return string checked value, reformatted
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_reformat_field($value) {
	if (substr($value, 0, 1) == '"' AND substr($value, -1) == '"')
		$value = substr($value, 1, -1);
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
	while (strpos($my_dir, '//'))
		$my_dir = str_replace('//', '/', $my_dir);
	if (substr($my_dir, -1) == '/')	//	removes / from the end
		$my_dir = substr($my_dir, 0, -1);
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

/*	----------------------------------------------	*
 *				IMAGE FUNCTIONS (zz_image...)		*
 *	----------------------------------------------	*/
/* 	will be called via 'auto_size' */

function zz_image_auto_size(&$image) {
	//	basics
	$tolerance = (!empty($image['auto_size_tolerance']) ? $image['auto_size_tolerance'] : 15); // tolerance in px
	$width = $image['upload']['width'];
	$height = $image['upload']['height'];
	if (!$height) return false;
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

function zz_upload_getfiletypes($filetypes_file) {
	// TODO: $mode = file, sql; read values from database table
	if (!file_exists($filetypes_file)) {
		echo ' Filetype definitions in "'.$filetypes_file.'" are not available!';
		exit;
	}
	$matrix = file($filetypes_file);
	foreach ($matrix as $line) {
		$default = false;
		if (substr($line, 0, 1) == '#') continue;	// Lines with # will be ignored
		elseif (!trim($line)) continue;				// empty lines will be ignored
		$values = explode("\t", trim($line));
		$keys = array('filetype', 'ext_old', 'ext', 'mime', 'desc');
		$i = 0;
		foreach ($values as $value) {
			if ($value == '### EOF') continue 2;
			if ($value)	{
				$default[$keys[$i]] = $value;
				$i++;
			}
		}
		$defaults[] = $default;
	}
	return $defaults;
}

// cleanup after deletion, e. g. remove empty dirs
function zz_unlink_cleanup($file) {
	$full_path = realpath($file);
	$dir = dirname($full_path);
	$success = unlink($full_path);
	if ($dir == '/tmp') return true; // don't delete /tmp-Folder
	
	zz_cleanup_dirs($dir);
		
	if ($success) return true;
	return false;
}

function zz_cleanup_dirs($dir) {
	$success = false;
	if (is_dir($dir)) {
		$dir_handle = opendir($dir);
		$i = 0;
		// check if directory is empty
		while ($filename = readdir($dir_handle)) {
			if ($filename != '.' AND $filename != '..') $i++;
		}
		closedir($dir_handle);
		if ($i == 0) $success = rmdir($dir);
	}
	if ($success) {
		// walk through dirs recursively
		$upper_dir = dirname($dir);
		zz_cleanup_dirs($upper_dir);
	}
	return true;
}

function zz_image_exif_thumbnail($source, $destination, $dest_extension = false, $image = false) {
	$exif_thumb = exif_thumbnail($source);
	if ($exif_thumb) {
		$imagehandle = fopen($destination, 'a');
		fwrite($imagehandle, $exif_thumb);	//write the thumbnail image
		return true;
	} else return false;
}

?>