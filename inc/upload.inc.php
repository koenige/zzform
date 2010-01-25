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
		zz_upload_check_files()	checks files,  puts information to 'image' array
			zz_upload_fileinfo()	read information (filesize, exif etc.)
			zz_upload_make_title()	converts filename to title
			zz_upload_make_name()	converts filename to better filename
			zz_upload_mimecheck()	checks whether supposed mimetype was already checked for
			zz_upload_filecheck()	gets filetype from list
	zz_upload_prepare()			prepares files for upload (resize, rotate etc.)
		zz_upload_extension()	gets extension
	zz_upload_check()			validates file input (upload errors, requirements)
	zz_upload_action()			writes/deletes files after successful sql insert/update
		zz_upload_sqlval()	
	zz_upload_cleanup()			cleanup after files have been moved or deleted

	2. additional functions

	zz_upload_path()			creates unique name for file (?)
	zz_upload_reformat_field()	removes "" from validated field, if neccessary	
	zz_upload_checkdir()		creates new directory and upper dirs as well
	zz_upload_get_typelist()	reads filetypes from txt-file

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

global $zz_error;

$zz_default['backup'] 			= false;	//	backup uploaded files?
$zz_default['backup_dir'] 		= $zz_conf_global['dir'].'/backup';	//	directory where backup will be put into
if (ini_get('upload_tmp_dir'))
	$zz_default['tmp_dir']		= ini_get('upload_tmp_dir');
else
	$zz_default['tmp_dir'] 		= false;
$zz_default['graphics_library'] = 'imagemagick';
$zz_default['imagemagick_paths'] = array('/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/phpbin'); 
$zz_default['upload_fileinfo_with_file'] = false;
$zz_default['upload_identify_with_imagemagick'] = true; // might be turned off for performance reasons while handling raw data

$max_filesize = ini_get('upload_max_filesize');
switch (substr($max_filesize, -1)) {
	case 'G': define('ZZ_UPLOAD_INI_MAXFILESIZE', $max_filesize * pow(1024, 3)); break;
	case 'M': define('ZZ_UPLOAD_INI_MAXFILESIZE', $max_filesize * pow(1024, 2)); break;
	case 'K': define('ZZ_UPLOAD_INI_MAXFILESIZE', $max_filesize * pow(1024, 1)); break;
}
$zz_default['upload_MAX_FILE_SIZE']	= ZZ_UPLOAD_INI_MAXFILESIZE;

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

$zz_default['file_types'] = zz_upload_get_typelist($zz_conf_global['dir_inc'].'/filetypes.txt');
if ($zz_error['error']) return false;
$zz_default['upload_iptc_fields'] = zz_upload_get_typelist($zz_conf_global['dir_inc'].'/iptc-iimv4-1.txt', 'IPTC', true);

// unwanted mimetypes and their replacements
$zz_default['mime_types_rewritten'] = array(
	'image/pjpeg' => 'image/jpeg', 	// Internet Explorer knows progressive JPEG instead of JPEG
	'image/x-png' => 'image/png',	// Internet Explorer
	'application/octet_stream' => 'application/octet-stream'
); 

// extensions for images that can be natively displayed in browser
$zz_default['webimages_by_extension'] = array('jpg', 'jpeg', 'gif', 'png');

$zz_default['exif_supported'] = array('jpeg', 'tiff', 'dng', 'cr2', 'nef');
$zz_default['upload_destination_filetype']['tiff'] = 'png';
$zz_default['upload_destination_filetype']['tif'] = 'png';
$zz_default['upload_destination_filetype']['tga'] = 'png';
$zz_default['upload_destination_filetype']['pdf'] = 'png';
$zz_default['upload_destination_filetype']['cr2'] = 'jpeg';
$zz_default['upload_destination_filetype']['dng'] = 'jpeg';
$zz_default['upload_pdf_density'] = '300x300'; // dpi in which pdf will be rasterized
$zz_default['upload_ghostscript_available'] = false; // whether we can use gs library


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
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	if ($zz_conf['graphics_library'])
		include_once $zz_conf['dir_inc'].'/image-'.$zz_conf['graphics_library'].'.inc.php';
	
	// create array upload_fields in $zz_tab[0] to get easy access to upload fields
	$zz_tab[0]['upload_fields'] = zz_upload_get_fields($zz_tab); // n = (i =>, k =>, f =>)

	//	read information of files, put into 'images'-array
	if ($_FILES && $zz_tab[0][0]['action'] != 'delete')
		zz_upload_check_files($zz_tab);
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
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
				if ($field['type'] == 'upload_image') {
					$key = (!empty($zz_tab[$i]['no']) ? $zz_tab[$i]['no'] : $f);
					$upload_fields[] = array('i' => $i, 'k' => $k, 'f' => $f, 'field_index' => $key);
				}
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
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	global $zz_error;
	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$my_tab = &$zz_tab[$uf['i']][$uf['k']];
		$images = false;
		$key = $uf['f'];
		$field = $my_tab['fields'][$key];
		
		// get unique fieldname for subtables and file uploads as set in editform.inc
		// $i means subtable, since main table has $i = 0
		$field['f_field_name'] = '';
		if ($uf['i']) $field['f_field_name'] = $zz_tab[$uf['i']]['table_name'].'['.$uf['k'].']['.$field['field_name'].']';
		elseif (isset($field['field_name'])) $field['f_field_name'] = $field['field_name'];
		$field['f_field_name'] = make_id_fieldname($field['f_field_name']);

		if (empty($_FILES[$field['f_field_name']])) {
			$my_tab['images'] = $images;
			continue;
		}
		$myfiles = &$_FILES[$field['f_field_name']];
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
			if (!empty($myfiles['do_not_delete'][$image['field_name']]))
				$images[$key][$subkey]['upload']['do_not_delete'] = $myfiles['do_not_delete'][$image['field_name']];
			
			// add extension to temporary filename (important for image manipulations,
			// e. g. imagemagick can only recognize .ico-files if they end in .ico)
			// but only if there is not already a file extension
			$oldfilename = $myfiles['tmp_name'][$image['field_name']];
			$extension = zz_upload_file_extension($images[$key][$subkey]['upload']['name']);
			if ($oldfilename AND strtolower(substr($oldfilename, -(strlen('.'.$extension)))) != strtolower('.'.$extension)) {
				// uploaded file
				$myfilename = $oldfilename.'.'.$extension;
				move_uploaded_file($oldfilename, $myfilename);
			} elseif ($oldfilename) {
				// mass upload
				$myfilename = $oldfilename;
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
				if (file_exists($images[$key][$subkey]['upload']['tmp_name'])) {
					zz_unlink_cleanup($images[$key][$subkey]['upload']['tmp_name']);
				}
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
			$images[$key][$subkey]['upload'] = zz_upload_fileinfo($images[$key][$subkey]['upload'], $myfilename, $extension);
			$myfilename = false;
			$my_tab['file_upload'] = true;
		}
		$my_tab['images'] = $images;
	}
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
}

/** checks which files allow file upload
 *
 *	determine filetype and file extension
 *	1. use reliable functions
 *	1a. getimagesize
 *	1b. exif_imagetype (despite being faster than getimagesize, this function comes
 *		second, because getimagesize also reads width and height)
 *		
 *		exif_imagetype()
 *		When a correct signature is found, the appropriate constant value will be
 *		returned otherwise the return value is FALSE. The return value is the same
 *		value that getimagesize() returns in index 2 but exif_imagetype() is much
 *		faster.
 *
 *	1c. todo: finfo_file, see: http://www.php.net/manual/en/function.finfo-file.php
 *			(c. relies on magic.mime file)
 *	1d. use identify in imagemagick
 *	2. if this is impossible, check for file extension
 *
 * @param $my(array) $zz_tab[$i][$k]
 * @return array multidimensional information about images
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_fileinfo($file, $myfilename, $extension) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	global $zz_error;
	$file['validated'] = false;
	// rewrite some misspelled and misset filetypes
	if (!empty($file['type'])) {
		if (in_array($file['type'], array_keys($zz_conf['mime_types_rewritten'])))
			$file['type'] = $zz_conf['mime_types_rewritten'][$file['type']];
	}
	// check whether filesize is above 2 bytes or it will give a read error
	if ($file['size'] >= 3) { 
		$extension = substr($myfilename, strrpos($myfilename, '.') +1);
		// 1a.
		// 1b.
		if (function_exists('getimagesize')) {
			$sizes = getimagesize($myfilename);
			if ($sizes && !empty($zz_conf['image_types'][$sizes[2]])) {
				$file['width'] = $sizes[0];
				$file['height'] = $sizes[1];
				$file['ext'] = $zz_conf['image_types'][$sizes[2]]['ext'];
				$file['mime'] = $zz_conf['image_types'][$sizes[2]]['mime'];
				$file['filetype'] = $zz_conf['image_types'][$sizes[2]]['filetype'];
				if (!empty($sizes['bits'])) $file['bits'] = $sizes['bits'];
				if (!empty($sizes['channels'])) $file['channels'] = $sizes['channels'];
				$file['validated'] = true;
				if ($file['filetype'] == 'tiff' AND $extension != 'tif' AND $extension != 'tiff') {
					// there are problems with RAW images recognized as tiff images
					$file['validated'] = false;
				}
				$tested_filetypes = array();
			}
			if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "getimagesize()");
		} 
		if (!$file['validated'] && function_exists('exif_imagetype')) {// > 4.3.0
			$imagetype = exif_imagetype($myfilename);
			if ($imagetype && !empty($zz_conf['image_types'][$imagetype])) {
				$file['ext'] = $zz_conf['image_types'][$imagetype]['ext'];
				$file['mime'] = $zz_conf['image_types'][$imagetype]['mime'];
				$file['filetype'] = $zz_conf['image_types'][$imagetype]['filetype'];
				$file['validated'] = true;
				if ($file['filetype'] == 'tiff' AND $extension != 'tif' AND $extension != 'tiff') {
					// there are problems with RAW images recognized as tiff images
					$file['validated'] = false;
				}
				$tested_filetypes = array();
			}
			if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "exif_imagetype()");
		} 
		if ($zz_conf['graphics_library'] == 'imagemagick' AND $zz_conf['upload_identify_with_imagemagick']) {
			$temp_imagick = zz_imagick_identify($myfilename);
			if ($temp_imagick) {
				$file = array_merge($file, $temp_imagick);
			}
			if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "identify()");
		}
		if ($zz_conf['upload_fileinfo_with_file']) {
			exec('file "'.$myfilename.'"', $return_var);
			if ($return_var) {
				$imagetype = false;
				$file['filetype_file'] = substr($return_var[0], strpos($return_var[0], ':')+2);
				if ($file['filetype_file'] == 'AutoCad (release 14)') {
					$imagetype = 'dwg';
					$file['validated'] = true;
// TODO: check this, these are not only DOCs but also MPPs.
//				} elseif ($file['filetype_file'] == 'Microsoft Office Document') {
//					$imagetype = 'doc';
//					$file['validated'] = true;
				}
				if ($file['validated'] AND $imagetype) {
					$file['ext'] = $zz_conf['file_types'][$imagetype][0]['ext'];
					$file['mime'] = $zz_conf['file_types'][$imagetype][0]['mime'];
					$file['filetype'] = $zz_conf['file_types'][$imagetype][0]['filetype'];
				}
			}
			if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "file()");
		}
		// TODO: allow further file testing here, e. g. for PDF, DXF
		// and others, go for Identifying Characters.
		// maybe use magic_mime_type()
		if (!$file['validated']) {
			if (zz_upload_mimecheck($file['type'], $extension)) {
				// Error: this mimetype/extension combination was already checked against
				$file['ext'] = 'unknown-'.$extension;
				$file['mime'] = 'unknown';
				$file['filetype'] = 'unknown';
			} else {
				$filetype = zz_upload_filecheck($file['type'], $extension);
				if ($filetype) {
					$file['ext'] = $filetype['ext'];
					$file['mime'] = $filetype['mime'];
					$file['filetype'] = $filetype['filetype'];
				} else {
					$file['ext'] = 'unknown-'.$extension;
					$file['mime'] = 'unknown: '.$file['type'];
					$file['filetype'] = 'unknown';
				}
			}
		}
		if ($file['filetype'] == 'unknown' AND !empty($zz_conf['debug_upload'])) {
			$error_filename = false;
			if ($zz_conf['backup']) {
				// don't return here in case of error - it's not so important to break the whole process
				$my_error = $zz_error['error'];
				$error_filename = zz_upload_path($zz_conf['backup_dir'], 'error', $myfilename);
				if (!$zz_error['error'])
					copy ($myfilename, $error_filename);
				$zz_error['error'] = $my_error;
			}
			$mailtext = zz_text('There was an attempt to upload the following file but it resulted with
an unknown filetype. You might want to check this.

').var_export($file, true);
			if ($error_filename) $mailtext .= "\r\n".'The file was temporarily saved under: '.$error_filename;
			$zz_error[] = array(
				'msg_dev' => $mailtext,
				'level' => E_USER_NOTICE
			);
			zz_error();
		}
		if (function_exists('exif_read_data') 
			AND in_array($file['filetype'], $zz_conf['exif_supported']))
			$file['exif'] = exif_read_data($myfilename);
		// TODO: further functions, e. g. zz_pdf_read_data if filetype == pdf ...
		// TODO: or read AutoCAD Version from DXF, DWG, ...
		// TODO: or read IPCT data.
	}
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
	return $file;
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
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function);
	foreach ($zz_conf['image_types'] as $imagetype)
		if ($imagetype['mime'] == $mimetype AND $imagetype['ext'] == $extension)
			return true;
	return false;
}

function zz_upload_filecheck($mimetype, $extension) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	$extension = strtolower($extension);
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function);
	$type1 = false;
	$type2 = false;
	$type2unique = true;
	$type3 = false;
	$type3unique = true;
	foreach ($zz_conf['file_types'] as $filetypelist) {
		foreach ($filetypelist as $filetype) {
			if ($filetype['ext_old'] == $extension AND $filetype['mime'] == $mimetype) {
				$type1 = $filetype;
			} elseif ($filetype['ext_old'] == $extension) {
				if ($type2) $type2unique = false;
				else $type2 = $filetype;
			} elseif ($filetype['mime'] == $mimetype) {
				if ($type3) $type3unique = false;
				else $type3 = $filetype;
			}
		}
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
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	global $zz_error;
	$all_temp_filenames = array();
	
	if (!empty($zz_tab[0]['upload_fields'])) foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$my_tab = &$zz_tab[$uf['i']][$uf['k']];
		foreach ($my_tab['fields'][$uf['f']]['image'] as $img => $val) {

			// reference on image data
			$image = &$my_tab['images'][$uf['f']][$img]; 

			//	read user input via options
			if (!empty($image['options'])) {
				// to make it easier, allow input without array construct as well
				if (!is_array($image['options'])) 
					$image['options'] = array($image['options']); 
				// go through all options
				foreach ($image['options'] as $optionfield) {
					// field_name of field where options reside
					$option_fieldname = $my_tab['fields'][$optionfield]['field_name']; 
					// this is the selected option
					if ($uf['i']) { // it's a subtable
						/// POST['files'][0], [1]
						$post_record = &$zz_tab[0][0]['POST'][$zz_tab[$uf['i']]['table_name']][$uf['k']];
					} else { // main table
						$post_record = &$my_tab['POST'];  
					}
					$option_value = $post_record[$option_fieldname];
					if (!empty($image['options_sql'])) {
						$sql = $image['options_sql'].' '.mysql_real_escape_string($option_value);
						$result = mysql_query($sql);
						if ($result and mysql_num_rows($result) == 1) {
							parse_str(mysql_result($result, 0, 0), $options[$option_value]);
						} else {
							$zz_error[] = array(
								'msg_dev' => zz_text('database-error'),
								'query' => $sql,
								'mysql' => mysql_error()
							);
							zz_error();
							return false;
						}
					} else {
						// these are the options from the script
						$options = $my_tab['fields'][$optionfield]['options'];
					}
					// overwrite values in script with selected option
					$image = array_merge($image, $options[$option_value]); 
				}
				unset($option_fieldname);
				unset($option);
				unset($option_value);
			}

			if (!empty($image['ignore'])) continue; // ignore image

			$dont_use_upload = false;
			$src_image = false;
			if (!empty($image['source_field'])) {
				// values might be numeric (e. g. 14) for main table
				// or array style (14[20]) for subtables
				// source might only be 0, if other values are needed change to if unset image source then 0
				$image['source'] = (!empty($image['source']) ? $image['source'] : 0);
				$source_field = array();
				if (strstr($image['source_field'], '[')) { // it's an array like 44[20] meaning field 44, subtable, there field 20
					preg_match('/(\d+)\[(\d+)\]/', $image['source_field'], $source_field);
					array_shift($source_field); // we don't need the 44[20] result
				} else { // field in main table
					$source_field[0] = $image['source_field'];
				}
				foreach ($zz_tab[0]['upload_fields'] AS $index => $values) {
					if ($values['field_index'] == $source_field[0]
						AND (empty($source_field[1]) OR $values['f'] == $source_field[1])) {
						// if there are several subtables, value for 0 should always be set.
						// then go through other subtables, if there's a better field,
						// re-set $src_image!
						$get_image = false;
						if (!empty($zz_tab[$values['i']][$values['k']]['images'][$values['f']][$image['source']])) {
							$get_image = $zz_tab[$values['i']][$values['k']]['images'][$values['f']][$image['source']]; // ok, this is a reason to get it
							// check if no picture, no required, no id then false
							if ($get_image['upload']['error'] AND !isset($_POST[$zz_tab[$values['i']]['table_name']][$values['k']][$zz_tab[$values['i']][$values['k']]['id']['field_name']])) {
								$get_image = false;
							}
						}
						if ($get_image) {
							$src_image = $zz_tab[$values['i']][$values['k']]['images'][$values['f']][$image['source']];
						}
					}
				}
				if (!$src_image) unset($image['source']); // nothing adequate found, so we can go on with source_file instead!
			}

			// check which source file shall be used
			if (isset($image['source'])) { // must be isset, because 'source' might be 0
				// it's a thumbnail or some other derivate from the original file
				if (!$src_image) $src_image = $my_tab['images'][$uf['f']][$image['source']];
				if (!empty($image['use_modified_source']))
					// get filename from modified source, false if there was an error
					$source_filename = (isset($src_image['files']) ? $src_image['files']['tmp_files'][$image['source']] : false);
				else
					$source_filename = $src_image['upload']['tmp_name'];
				// get some variables from source image as well
				$image['upload'] = $src_image['upload']; 
				// check if it's not a form that allows upload of different filetypes at once
				// cross check input filetypes
				if (!empty($image['input_filetypes'])) {
					if (!in_array($src_image['upload']['filetype'], $image['input_filetypes'])) continue;
					// continue if this file shall not be touched.
				}
				unset($src_image);
				$dont_use_upload = true;
			} elseif (!empty($image['source_file'])) {
				$source_filename = false;
				// get source file
				// test, whether it is an ID field with ... or not
				unset($field_index);
				// convert string in ID, if it's a checkselect
				foreach ($my_tab['fields'] as $index => $field) {
					if ($field['field_name'] == $image['source_file']) {
						$field_index = $index;
					} 
				}
				if (isset($field_index)) { 
					if (isset($_POST['zz_check_select']) 
						&& $my_tab['fields'][$field_index]['type'] == 'select' 
						&& (in_array($my_tab['fields'][$field_index]['field_name'], $_POST['zz_check_select']) 
							OR (in_array($zz_tab[$uf['i']]['table'].'['.$uf['k'].']['.$my_tab['fields'][$field_index]['field_name'].']', $_POST['zz_check_select']))) // check only for 0, might be problem, but 0 should always be there
						&& $post_record[$my_tab['fields'][$field_index]['field_name']]) { // if null -> accept it
						$my_tab = zz_check_select($my_tab, $field_index, $zz_conf['max_select']);
					}
					$sql = $image['source_path_sql'].$post_record[$image['source_file']];
					if (!empty($image['update_from_source_field_name']) AND !empty($image['update_from_source_value'])
						AND !empty($zz_tab[$uf['i']][$uf['k']]['old_record'][$image['update_from_source_value']])) {
						$sql = zz_edit_sql($sql, 'WHERE', $image['update_from_source_field_name'].' != "'.$zz_tab[$uf['i']][$uf['k']]['old_record'][$image['update_from_source_value']].'"');
					}
					$result = mysql_query($sql);
					if ($result AND mysql_num_rows($result) == 1) {
						$source_tab[$uf['i']][$uf['k']]['old_record'] = mysql_fetch_assoc($result);
						$source_filename = zz_makepath($image['source_path'], $source_tab, 'old', 'file', $uf['i'], $uf['k']);
						unset($source_tab);
						if (file_exists($source_filename)) {
							$extension = zz_upload_file_extension($source_filename);
							$image['upload']['name'] = basename($source_filename);
							$image['upload']['type'] = ''; // TODO ?
							$image['upload']['tmp_name'] = $source_filename; // same because it's no upload
							$image['upload']['error'] = 0;
							$image['upload']['size'] = filesize($source_filename);
							$image['upload'] = zz_upload_fileinfo($image['upload'], $source_filename, $extension);
						}
					}
					$dont_use_upload = true;
				}
			}
			if (!$dont_use_upload) {
				// it's the original file we upload to the server
				$source_filename = $image['upload']['tmp_name'];
				if (file_exists($source_filename) AND empty($image['upload']['do_not_delete']))
					$all_temp_filenames[] = $source_filename;
			}
			if (!empty($image['auto']) && !empty($image['auto_values'])) 
				// choose values from uploaded image, best fit
				if ($source_filename) {
					$autofunc = 'zz_image_auto_'.$image['auto'];
					if (function_exists($autofunc)) $autofunc($image);
					else {
						$zz_error[] = array(
							'msg_dev' => sprintf(zz_text('Configuration error: function %s for image upload does not exist.'), '<code>'.$autofunc.'()</code>'),
							'level' => E_USER_ERROR
						);
						zz_error();
						return false;
					}
				}
			if ($source_filename && $source_filename != 'none') { // only if something new was uploaded!
				$filename = (file_exists($source_filename) ? $source_filename : '');
				$tmp_filename = false;
				if (!empty($image['action'])) { // image operations only for images
					// create temporary file, so that original file remains the same for further actions
					$tmp_filename = tempnam(realpath($zz_conf['tmp_dir']), "UPLOAD_"); 
					$dest_extension = zz_upload_extension($image['path'], $zz_tab, $uf);
					if (!$dest_extension) {
						$dest_extension = $image['upload']['ext'];
						// map files to extensions, e. g. TIFF to PNG
						if (!empty($zz_conf['upload_destination_filetype'][$dest_extension]))
							$dest_extension = $zz_conf['upload_destination_filetype'][$dest_extension];
					}
					$image['action'] = 'zz_image_'.$image['action'];
					$image['action']($filename, $tmp_filename, $dest_extension, $image);
					if (file_exists($tmp_filename))	{
						if (filesize($tmp_filename) > 3) {
							$filename = $tmp_filename;
							$all_temp_filenames[] = $tmp_filename;
							$my_tab['file_upload'] = true;
							$image['modified']['tmp_name'] = $tmp_filename;
							$image['modified']['size'] = filesize($tmp_filename);
							$image['modified'] = zz_upload_fileinfo($image['modified'], $tmp_filename, $dest_extension);
							// todo: ['modified']['name'] ?? neccessary? so far, it's not.
						}  else {
							// ELSE: if image-action did not work out the way it should have.
							$filename = false; // do not upload anything
							// TODO: mark existing image for deletion if there is one!							
							$image['delete_thumbnail'] = true;
							$my_tab['no_file_upload'] = true;
							$zz_error[] = array(
								'msg_dev' => sprintf(zz_text('No real file was returned from function %s'), '<code>'.$image['action'].'()</code>'),
								'level' => E_USER_NOTICE
							);
						}
					} else {
						$zz_error[] = array(
							'msg_dev' => sprintf(zz_text('Error: File %s does not exist. Temporary Directory: %s'), $tmp_filename, realpath($zz_conf['tmp_dir']))
						);
					}
					zz_error();
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
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
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
		$zz_error[] = array(
			'msg_dev' => zz_text('Error. Could not determine file ending'),
			'level' => E_USER_ERROR
		);
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
function zz_upload_check(&$images, $action, $zz_conf, $input_filetypes = array(), $k = 0) {
	global $zz_error;
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function);
	$error = false;
	if ($input_filetypes) {
		if (strstr($input_filetypes[0], 'image/') 
			OR strstr($input_filetypes[0], 'application/')) { // deprecated version, please change
			$zz_error[] = array(
				'msg_dev' => zz_text('Error: Deprecated use of MIME types in input_filetypes. Please use filetypes instead.'),
				'level' => E_USER_NOTICE
			);
		}
	}
	foreach (array_keys($images) as $key) {
	//	check if image was uploaded
		if (!is_numeric($key)) continue; //file_name, title
		$images[$key]['required'] = (!empty($images[$key]['required']) ? $images[$key]['required'] : false);
		if ($k AND !empty($images[$key]['required_only_first_detail_record']))
			$images[$key]['required'] = false;
		$images[$key]['error'] = false;
		if (!empty($images[$key]['field_name'])) {
			switch ($images[$key]['upload']['error']) {
				// constants since PHP 4.3.0!
				case 4: // no file (UPLOAD_ERR_NO_FILE)
					if ($images[$key]['required'] && $action == 'insert') // required only for insert
						$images[$key]['error'][] = zz_text('Error: ').zz_text('No file was uploaded.');
					else continue 2;
					break;
				case 3: // partial upload (UPLOAD_ERR_PARTIAL)
					$images[$key]['error'][] = zz_text('Error: ').zz_text('File was only partially uploaded.');
					break; 
				case 2: // file is too big (UPLOAD_ERR_INI_SIZE)
				case 1: // file is too big (UPLOAD_ERR_FORM_SIZE)
					$images[$key]['error'][] = zz_text('Error: ').zz_text('File is too big.').' '
						.zz_text('Maximum allowed filesize is').' '
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
				$images[$key]['error'][] = zz_text('Error: ')
				.zz_text('Unsupported filetype:').' '
				.$images[$key]['upload']['filetype']
				.'<br class="nonewline_in_mail">'.zz_text('Supported filetypes are:').' '
				.implode(', ', $images[$key]['input_filetypes']);
				$error = true;
				continue; // do not go on and do further checks, because filetype is wrong anyways
			}

	//	check if minimal image size is reached
			$width_height = array('width', 'height');
			foreach ($width_height as $which)
				if (!empty($images[$key]['min_'.$which]) 
					&& $images[$key]['min_'.$which] > $images[$key]['upload'][$which])
					$images[$key]['error'][] = zz_text('Error: ')
						.sprintf(zz_text('Minimum '.$which
						.' %s was not reached.'), '('.$images[$key]['min_'.$which].'px)')
						.' ('.$images[$key]['upload'][$which].'px)';

	//	check if maximal image size has not been exceeded
			$width_height = array('width', 'height');
			foreach ($width_height as $which)
				if (!empty($images[$key]['max_'.$which])
					&& $images[$key]['max_'.$which] < $images[$key]['upload'][$which])
					$images[$key]['error'][] = zz_text('Error: ')
						.sprintf(zz_text('Maximum '.$which
						.' %s has been exceeded.'), '('.$images[$key]['max_'.$which].'px)')
						.' ('.$images[$key]['upload'][$which].'px)';
	
		}
		if ($images[$key]['error']) $error = true;
	}
	if ($error) return false;
	else return true;
}

/** Deletes files when specifically requested (e. g. in multiple upload forms)
 * 
 * called from within function zz_upload_action
 * @param $zz_tab(array) complete table data
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_delete_file(&$zz_tab, $action, $zz_conf) {
	global $zz_error;
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function);
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
		if (file_exists($old_path)) { 
			// just a precaution for e. g. simultaneous access
			if ($zz_conf['backup']) {
				rename($old_path, zz_upload_path($zz_conf['backup_dir'], $action, $old_path));
				if ($zz_error['error']) return false;
			} else {
				unlink($old_path);
			}
		}
		foreach ($zz_tab[0][0]['images'][$field] as $key => $other_image) {
			if (is_numeric($key) && isset($other_image['source']) && $other_image['source'] == $image) {
				$old_path = zz_makepath($other_image['path'], $zz_tab, 'old', 'file', 0, 0);
				if (file_exists($old_path)) { // just a precaution for e. g. simultaneous access
					if ($zz_conf['backup']) {
						rename($old_path, zz_upload_path($zz_conf['backup_dir'], $action, $old_path));
						if ($zz_error['error']) return false;
					} else {
						unlink($old_path);
					}
				}
			}
		}
		// remove images which base on this image as well (source = $image)
	}
	return true;
}

/** Moves or deletes file after successful SQL operations
 * 
 * if backup variable is set to true, script will move old files to backup folder
 * called from within function zz_action
 * @param $zz_tab(array) complete table data
 * @param $zz_conf(array) configuration variables
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_action(&$zz_tab, $zz_conf) {
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();

	// delete files, if neccessary
	if (!empty($_POST['zz_delete_file'])) 
		zz_upload_delete_file($zz_tab, $zz_tab[0][0]['action'], $zz_conf);

	// create path
	// check if path exists, if not, create it
	// check if file_exists, if true, move file to backup-directory, if zz_conf says so
	// no changes: move_uploaded_file to destination directory, write new filename to 
	//		array in case this image will be needed later on 
	// changes: move changed file to dest. directory
	// on error: return error_message - critical error, because record has already been saved!

	global $zz_error;
	foreach ($zz_tab[0]['upload_fields'] as $index => $uf) {
		if (empty($zz_tab[$uf['i']][$uf['k']])) {
			unset ($zz_tab[0]['upload_fields'][$index]);
			continue;  // no file, might arise if there's an exif_upload without a resulting file
		}
		$my_tab = &$zz_tab[$uf['i']][$uf['k']];
		$my_tab['POST'][$my_tab['id']['field_name']] = $my_tab['id']['value']; // to catch mysql_insert_id
		foreach ($my_tab['fields'][$uf['f']]['image'] as $img => $val) {
			$image = &$my_tab['images'][$uf['f']][$img]; // reference on image data
			$mode = false;
			$action = $zz_tab[$uf['i']][$uf['k']]['action'];

		// 	delete
			if ($action == 'delete') {
				$path = zz_makepath($val['path'], $zz_tab, 'old', 'file', $uf['i'], $uf['k']);
				$localpath = zz_makepath($val['path'], $zz_tab, 'old', 'local', $uf['i'], $uf['k']);
				if (file_exists($path) && is_file($path)) {
					if ($zz_conf['backup']) {
						$success = rename($path, zz_upload_path($zz_conf['backup_dir'], 'delete', $path));
						if ($zz_error['error']) return false;
						zz_cleanup_dirs(dirname($path));
					} else {
						$success = zz_unlink_cleanup($path);
					}
					if (!$success) 
						$zz_error[] = array(
							'msg' => sprintf(zz_text('Could not delete %s.'), $path),
							'level' => E_USER_NOTICE
						);
				} elseif(file_exists($path) && !is_file($path)) {
					$zz_error[] = array(
						'msg_dev' => zz_text('Configuration Error [1]: Filename is invalid: ').$path,
						'level' => E_USER_ERROR
					);
					return (zz_error());
				} elseif ($path && empty($val['ignore']) 
					&& empty($my_tab['fields'][$uf['f']]['optional_image'])
					&& empty($val['optional_image'])) { // optional images: don't show error message!
					$zz_error[] = array(
						'msg' => sprintf(zz_text('Could not delete %s, file did not exist.'), $localpath),
						'level' => E_USER_NOTICE
					);
				}
				continue; // deleted, so don't care about the rest of the code
			}

		//	update, only if we have an old record (might sometimes not be the case!)
			if ($action == 'update' AND !empty($my_tab['old_record'])) {
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
					if ($zz_error['error']) return false;
					if (file_exists($path) && $zz_conf['backup'] AND (strtolower($old_path) != strtolower($path))
						) { // this case should not occur
							 // attention: file_exists returns true even if there is a change in case
						rename($path, zz_upload_path($zz_conf['backup_dir'], $action, $path));
						if ($zz_error['error']) return false;
					}
					if (file_exists($old_path)) {
						if ($zz_conf['backup'] && isset($image['files']['tmp_files'][$img])) {
							// new image will be added later on for sure
							rename($old_path, zz_upload_path($zz_conf['backup_dir'], $action, $path));
							if ($zz_error['error']) return false;
						} else { // just path will change
							rename($old_path, $path);
						}
					}
				}
			}

		// insert, update
			if (!empty($image['files']['tmp_files'][$img])) {
				$image['files']['destination'] = zz_makepath($val['path'], $zz_tab, 'new', 'file', $uf['i'], $uf['k']);
				$filename = $image['files']['tmp_files'][$img];
				if (file_exists($image['files']['destination']) && is_file($image['files']['destination'])) {
					if ($zz_conf['backup']) {
						rename($image['files']['destination'], zz_upload_path($zz_conf['backup_dir'], $action, $image['files']['destination']));
						if ($zz_error['error']) return false;
						zz_cleanup_dirs(dirname($image['files']['destination']));
					} else {
						zz_unlink_cleanup($image['files']['destination']);
					}
				} elseif (file_exists($image['files']['destination']) && !is_file($image['files']['destination'])) {
					$zz_error[] = array(
						'msg_dev' => sprintf(zz_text('Configuration Error [2]: Filename "%s" is invalid.'), $image['files']['destination']),
						'level' => E_USER_ERROR
					);
					return zz_error();
				}
				zz_upload_checkdir(dirname($image['files']['destination'])); // create path if it does not exist or if cleanup removed it.
				if ($zz_error['error']) return false;
				if (!isset($image['source']) && !isset($image['source_file']) && empty($image['action'])) { 
					// do this with images which have not been touched
					// todo: error handling!!
					copy($filename, $image['files']['destination']);		// instead of rename:
					if (!file_exists($image['files']['destination'])) {
						if (!is_writeable(dirname($image['files']['destination']))) {
							$zz_error[] = array(
								'msg' => 'File could not be saved. There is a problem with the user rights. We are working on it.',
								'msg_dev' => sprintf(zz_text('Insufficient rights. Directory %s is not writeable.'), 
									'<code>'.dirname($image['files']['destination']).'</code>'),
								'level' => E_USER_ERROR
							);
							return zz_error();
								
						} else { 
							$zz_error[] = array(
								'msg' => 'File could not be saved. There is a problem with the user rights. We are working on it.',
								'msg_dev' => zz_text('Unknown error.').zz_text('Copying not successful.')
									.'<br>'.zz_text('from:').' '.$filename.'<br>'.zz_text('to:').' '
									.$image['files']['destination'].'<br>',
								'level' => E_USER_ERROR
							);
							return zz_error();
						}
					}
					zz_unlink_cleanup($filename);			// this also works in older php versions between partitions.
					chmod($image['files']['destination'], 0644);
				} else {
				//	echo '<br>FILENAME: %'.$filename.'% DEST %'.$image['files']['destination'].'<br>';
					$success = copy($filename, $image['files']['destination']);
					chmod($image['files']['destination'], 0644);
					if (!$success) {
						$zz_error[] = array(
							'msg_dev' => zz_text('Copying not successful.').'<br>'.zz_text('from:').' '.$filename.'<br>'.zz_text('to:').' '.$image['files']['destination'].'<br>',
							'level' => E_USER_ERROR
						);
						return zz_error();
					}
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
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
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
	if ($result) {
		if (mysql_num_rows($result))
			$line = mysql_fetch_assoc($result);
	} else {
		global $zz_error;
		$zz_error[] = array(
			'mysql' => mysql_error(),
			'query' => $sql,
			'msg_dev' => 'zz_upload_sqlval()',
			'level' => E_USER_ERROR
		);
		return zz_error();
	}
	if (!empty($line[$value])) return $line[$value];
	else return false;
}


/** Creates unique filename from backup dir, action and file path
 * 
 * called form zz_upload_action
 * @param $dir(string) backup directory
 * @param $action(string) sql action
 * @param $path(string) file path
 * @return unique filename ? path?
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_path($dir, $action, $path) {
	global $zz_error;
	$my_base = $dir.'/'.$action.'/';
	zz_upload_checkdir($my_base);
	if ($zz_error['error']) return false;
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
	if ($zz_tab[0]['upload_fields']) foreach ($zz_tab[0]['upload_fields'] as $uf) {
		if (!empty($zz_tab[$uf['i']][$uf['k']]['images'][$uf['f']]['all_temp']))
			foreach ($zz_tab[$uf['i']][$uf['k']]['images'][$uf['f']]['all_temp'] as $file)
				if (file_exists($file) && is_file($file)) {
					zz_unlink_cleanup($file);
				}
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
	global $zz_error;
	if (!$my_dir) return false;
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
			if (!$success) {
				$zz_error[] = array(
					'msg_dev' => sprintf(zz_text('Creation of directory %s failed.'), $my_dir),
					'level' => E_USER_ERROR
				);
				zz_error();
				return false;
			
			//else $success = chown($my_dir, getmyuid());
			//if (!$success) echo 'Change of Ownership of '.$my_dir.' failed.<br>';
			} else return true;
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

function zz_upload_get_typelist($filename, $type = 'Filetype', $optional = false) {
	// TODO: $mode = file, sql; read values from database table
	if (!file_exists($filename)) {
		if ($optional) return false;
		global $zz_error;
		$zz_error[] = array(
			'msg_dev' => sprintf(zz_text($type.' definitions in %s are not available!'), '"'.$filename.'"'),
			'level' => E_USER_ERROR
		);
		return zz_error();
	}
	if ($type == 'Filetype') {
		$keys = array('filetype', 'ext_old', 'ext', 'mime', 'desc');
	} elseif ($type == 'IPTC') {
		$keys = array('ipct_id', 'dataset');
	}
	$matrix = file($filename);
	foreach ($matrix as $line) {
		$default = false;
		if (substr($line, 0, 1) == '#') continue;	// Lines with # will be ignored
		elseif (!trim($line)) continue;				// empty lines will be ignored
		$values = explode("\t", trim($line));
		
		$i = 0;
		foreach ($values as $value) {
			if ($value == '### EOF') continue 2;
			if ($value)	{
				if ($type == 'IPTC' AND !$i) {
					$parts = explode(':', $value);
					$value = $parts[0].'#'.sprintf("%03s", $parts[1], 0);
				}
				$default[$keys[$i]] = $value;
				$i++;
			}
		}
		if ($type == 'IPTC') {
			$defaults[$default[$keys[0]]] = $default;
		} else {
			$defaults[$default[$keys[0]]][] = $default;
		}
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

/* Extracts EXIF thumbnail from image
 *
 * This function is independent of the graphics library used, therefore it's
 * directly part of the upload module
*/
function zz_image_exif_thumbnail($source, $destination, $dest_extension = false, $image = false) {
	global $zz_conf;
	if (!in_array($image['upload']['filetype'], $zz_conf['exif_supported'])) return false;
	$exif_thumb = exif_thumbnail($source);
	if ($exif_thumb) {
		$imagehandle = fopen($destination, 'a');
		fwrite($imagehandle, $exif_thumb);	//write the thumbnail image
		return true;
	} else return false;
}

function zz_upload_file_extension($filename) {
	if (strstr($filename, '.'))
		$extension = strtolower(substr($filename, strrpos($filename, '.')+1));
	else
		$extension = 'unknown';
	return $extension;
}

?>