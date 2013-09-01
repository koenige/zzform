<?php 

/**
 * zzform
 * File upload
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 *	1. main functions (in order in which they are called)
 *
 *	zz_upload_get()				writes arrays upload_fields, images
 *								i. e. checks which fields offer uploads,
 *								collects and writes information about files
 *		zz_check_def_files()
 *		zz_upload_get_fields()	checks which fields allow upload
 *		zz_upload_check_files()	checks files,  puts information to 'image' array
 *			zz_upload_fileinfo()	read information (filesize, exif etc.)
 *			zz_upload_make_title()	converts filename to title
 *			zz_upload_make_name()	converts filename to better filename
 *			zz_upload_mimecheck()	checks whether supposed mimetype was already checked for
 *			zz_upload_filecheck()	gets filetype from list
 *			...
 *	zz_upload_prepare()			prepares files for upload (resize, rotate etc.)
 *		zz_upload_extension()	gets extension
 *	zz_upload_check()			validates file input (upload errors, requirements)
 *		not directly called but from zz_action() instead:
 *		zz_write_upload_fields()
 *		zz_val_get_from_upload()
 *	zz_upload_action()			writes/deletes files after successful sql insert/update
 *	zz_upload_cleanup()			cleanup after files have been moved or deleted
 *
 *	2. additional functions
 *
 *	zz_upload_path()			creates unique name for file (?)
 *	zz_upload_get_typelist()	reads filetypes from txt-file
 *
 *	3. zz_tab array
 *	
 *	global
 *	$zz_tab[0]['upload_fields'][n]['tab']
 *	$zz_tab[0]['upload_fields'][n]['rec']
 *	$zz_tab[0]['upload_fields'][n]['f'] ...
 *
 *	subtable, currently only 0 0 supported
 *	$zz_tab[0][0]['images']
 *	$zz_tab[0][0]['images'][n]['title']
 *	$zz_tab[0][0]['images'][n]['filename']
 *	values from table definition + option
 *	$zz_tab[0][0]['images'][n][0]['title']
 *	$zz_tab[0][0]['images'][n][0]['field_name']
 *	$zz_tab[0][0]['images'][n][0]['path']
 *	$zz_tab[0][0]['images'][n][0][...]
 *	upload values from PHP form
 *	$zz_tab[0][0]['images'][n][0]['upload']['name']	local filename
 *	$zz_tab[0][0]['images'][n][0]['upload']['type'] mimetype, as browser sends it
 *	$zz_tab[0][0]['images'][n][0]['upload']['tmp_name'] temporary filename on server
 *	$zz_tab[0][0]['images'][n][0]['upload']['error'] errorcode, 0 = no error
 *	own upload values, read from image
 *	$zz_tab[0][0]['images'][n][0]['upload']['size']		filesize
 *	$zz_tab[0][0]['images'][n][0]['upload']['width']	width in px
 *	$zz_tab[0][0]['images'][n][0]['upload']['height']	height in px
 *	$zz_tab[0][0]['images'][n][0]['upload']['exif']		exif data
 *
 *	$zz_tab[0][0]['images'][n][0]['upload']['filetype']	Filetype
 *	$zz_tab[0][0]['images'][n][0]['upload']['ext']		file extension
 *	$zz_tab[0][0]['images'][n][0]['upload']['mime']		MimeType
 *	$zz_tab[0][0]['images'][n][0]['upload']['imagick_format']	ImageMagick_Format
 *	$zz_tab[0][0]['images'][n][0]['upload']['imagick_mode']		ImageMagick_Mode
 *	$zz_tab[0][0]['images'][n][0]['upload']['imagick_desc']		ImageMagick_Description
 *	$zz_tab[0][0]['images'][n][0]['upload']['validated']	validated (yes = tested, no = rely on fileupload i. e. user)
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2006-2013 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/*	----------------------------------------------	*
 *					VARIABLES						*
 *	----------------------------------------------	*/

/**
 * Default settings for upload module
 */
function zz_upload_config() {
	global $zz_error;
	global $zz_conf;

	$default['backup'] 			= false;	//	backup uploaded files?
	$default['backup_dir'] 		= $zz_conf['dir'].'/backup';	//	directory where backup will be put into
	if (ini_get('upload_tmp_dir'))
		$default['tmp_dir']		= ini_get('upload_tmp_dir');
	else
		$default['tmp_dir'] 		= false;
	$default['graphics_library'] = 'imagemagick';
	$default['imagemagick_paths'] = array('/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/phpbin', '/opt/local/bin'); 
	$default['upload_tools']['fileinfo'] = false;
	$default['upload_tools']['fileinfo_whereis'] = 'file';
	$default['upload_tools']['exiftools'] = false;
	$default['upload_tools']['identify'] = true; // might be turned off for performance reasons while handling raw data
	$default['upload_tools']['ghostscript'] = false; // whether we can use gs library
	$default['upload_log']		= '';

	$max_filesize = ini_get('upload_max_filesize');
	define('ZZ_UPLOAD_INI_MAXFILESIZE', zz_return_bytes($max_filesize));
	$default['upload_MAX_FILE_SIZE']	= ZZ_UPLOAD_INI_MAXFILESIZE;

	// mimetypes, hardcoded in php

	$default['image_types'] = array(
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
	foreach (array_keys($default['image_types']) as $key)
		$default['image_types'][$key]['filetype'] = $default['image_types'][$key]['ext'];

	$default['file_types'] = zz_upload_get_typelist($zz_conf['dir_inc'].'/filetypes.txt');
	if ($zz_error['error']) return false;
	$default['upload_iptc_fields'] = zz_upload_get_typelist($zz_conf['dir_inc'].'/iptc-iimv4-1.txt', 'IPTC', true);

	// unwanted mimetypes and their replacements
	$default['mime_types_rewritten'] = array(
		'image/pjpeg' => 'image/jpeg', 	// Internet Explorer knows progressive JPEG instead of JPEG
		'image/x-png' => 'image/png',	// Internet Explorer
		'application/octet_stream' => 'application/octet-stream'
	); 

	// extensions for images that can be natively displayed in browser
	$default['webimages_by_extension'] = array('jpg', 'jpeg', 'gif', 'png');

	$default['exif_supported'] = array('jpeg', 'tiff', 'dng', 'cr2', 'nef');
	$default['upload_destination_filetype']['tiff'] = 'png';
	$default['upload_destination_filetype']['tif'] = 'png';
	$default['upload_destination_filetype']['tga'] = 'png';
	$default['upload_destination_filetype']['pdf'] = 'png';
	$default['upload_destination_filetype']['ai'] = 'png';
	$default['upload_destination_filetype']['eps'] = 'png';
	$default['upload_destination_filetype']['cr2'] = 'jpeg';
	$default['upload_destination_filetype']['dng'] = 'jpeg';
	$default['upload_destination_filetype']['psd'] = 'jpeg';
	$default['upload_destination_filetype']['mp4'] = 'jpeg';
	$default['upload_destination_filetype']['mov'] = 'jpeg';
	$default['upload_destination_filetype']['mpg'] = 'jpeg';
	$default['upload_destination_filetype']['flv'] = 'jpeg';
	$default['upload_destination_filetype']['avi'] = 'jpeg';

	$default['upload_pdf_density'] = '300x300'; // dpi in which pdf will be rasterized

	$default['upload_multipage_images'] = array('pdf', 'psd', 'mp4', 'mov', 'mpg', 'flv', 'avi');
	$default['upload_multipage_which']['mp4'] = 5; // don't take first frame, might be black

	$default['upload_filetype_map']['tif'] = 'tiff';
	$default['upload_filetype_map']['jpe'] = 'jpeg';
	$default['upload_filetype_map']['jpg'] = 'jpeg';

	$default['upload_no_thumbnails'] = array('doc', 'docx', 'wps', 'rtf', 'xls',
		'dot', 'odt', 'ott', 'ods', 'indd', 'txt', 'csv');
	zz_write_conf($default);
}

/*	----------------------------------------------	*
 *					MAIN FUNCTIONS					*
 *	----------------------------------------------	*/

/**
 * writes arrays upload_fields, images
 *
 * i. e. checks which fields offer uploads, 
 * collects and writes information about files
 * 1- get faster access to upload fields
 * 2- get 'images' array with information about each file
 * 
 * @param array $zz_tab complete table data
 * @return array $zz_tab
 *		$zz_tab[0]['upload_fields']
 * 		$zz_tab[0][0]['images']
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_get($zz_tab) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if ($zz_conf['graphics_library'])
		include_once $zz_conf['dir_inc'].'/image-'.$zz_conf['graphics_library'].'.inc.php';

	// create array upload_fields in $zz_tab[0] for easy access to upload fields
	$zz_tab[0]['upload_fields'] = zz_upload_get_fields($zz_tab);

	//	read information of files, put into 'images'-array
	if ($zz_tab[0][0]['action'] != 'delete')
		$zz_tab = zz_upload_check_files($zz_tab);
	if ($zz_conf['modules']['debug']) zz_debug('end');
	return $zz_tab;
}

/**
 * checks which fields allow file upload
 * 
 * @param array $zz_tab complete table data
 * @return array $upload_fields with tab, rec, and f in 
 *		$zz_tab[$tab][$rec]['fields'][$f]
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_get_fields($zz_tab) {
	$upload_fields = array();
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_int($tab) OR !is_int($rec)) continue;
			foreach ($zz_tab[$tab][$rec]['fields'] as $f => $field) {
				if ($field['type'] != 'upload_image') continue;
				$key = !empty($zz_tab[$tab]['no']) ? $zz_tab[$tab]['no'] : $f;
				$upload_fields[] = array(
					'tab' => $tab, 
					'rec' => $rec,
					'f' => $f,
					'field_index' => $key
				);
			}
		}
	}
	return $upload_fields;
}

/**
 * checks which files allow file upload
 *
 * @param array $my = $zz_tab[$tab][$rec]
 * @global array $zz_conf
 * @global array $zz_error
 * @return array multidimensional information about images
 *		bool $zz_tab[tab][rec]['file_upload']
 *		array $zz_tab[tab][rec]['images']
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_check_files($zz_tab) {
	global $zz_conf;
	global $zz_error;
	
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$id = $zz_conf['int']['secret_key'];
	$session = !empty($_SESSION['zz_files'][$id]) ? $_SESSION['zz_files'][$id] : array();

	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$tab = $uf['tab'];
		$rec = $uf['rec'];
		$no = $uf['f'];
		$my_rec = &$zz_tab[$tab][$rec];
		$images = array();
		$field = $my_rec['fields'][$no];

		// get unique fieldname for subtables and file uploads as set in editform.inc
		// $tab means subtable, since main table has $tab = 0
		$field['f_field_name'] = '';
		if ($tab) {
			$field['f_field_name'] = $zz_tab[$tab]['table_name'].'['.$rec.']['.$field['field_name'].']';
		} elseif (isset($field['field_name'])) {
			$field['f_field_name'] = $field['field_name'];
		}
		$field['f_field_name'] = zz_make_id_fieldname($field['f_field_name']);

		$myfiles = array();
		if (!empty($_FILES[$field['f_field_name']])) {
			$myfiles = $_FILES[$field['f_field_name']];
		}
		foreach (array_keys($field['image']) as $img) {
			$images[$no][$img] = $field['image'][$img];
			if (empty($images[$no][$img]['field_name'])) {
				// don't do the rest if field_name is not set
				continue;
			}
			$field_name = $images[$no][$img]['field_name'];
			
			if (!empty($session[$tab][$rec]['images'][$no][$img]))
				$images[$no]['all_temp'] = $session[$tab][$rec]['images'][$no]['all_temp'];
			
			if (empty($myfiles['name'][$field_name])
				AND !empty($session[$tab][$rec]['images'][$no][$img])) {
				$images[$no]['title'] = $session[$tab][$rec]['images'][$no]['title'];
				$images[$no]['filename'] = $session[$tab][$rec]['images'][$no]['filename'];
				$images[$no][$img] = $session[$tab][$rec]['images'][$no][$img];
				$images[$no]['read_from_session'] = true;
				$my_rec['file_upload'] = $session[$tab][$rec]['file_upload'];
				continue;
			}
			// we need at least a tmp_name from somewhere pointing to a file
			// might be '' (file upload with no file)
			if (!isset($myfiles['tmp_name'][$field_name])) {
				$myfiles['tmp_name'][$field_name] = '';
				$myfiles['name'][$field_name] = '';
				$myfiles['type'][$field_name] = '';
				$myfiles['size'][$field_name] = 0;
				$myfiles['error'][$field_name] = 4; // no file was uploaded
			}

			if (!isset($myfiles['name'][$field_name])) {
				$myfiles['name'][$field_name] = 'unknown';
			}

			// initialize convert_options
			if (!isset($images[$no][$img]['convert_options'])) {
				$images[$no][$img]['convert_options'] = '';
			}

			// title, generated from local filename, to be used for 'upload_value'
			if (empty($images[$no]['title'])) {
				// this and field_name will be '' if first image is false
				$images[$no]['title'] = zz_upload_make_title($myfiles['name'][$field_name]);
			}

			// local filename, extension (up to 4 letters) removed, to be used for 'upload_value'
			if (empty($images[$no]['filename'])) {
				$images[$no]['filename'] = zz_upload_make_name($myfiles['name'][$field_name]); 
			}
			
			$images[$no][$img]['upload']['name'] = $myfiles['name'][$field_name];
			if (!isset($myfiles['type'][$field_name])) {
				$myfiles['type'][$field_name] = 'application/octet-stream';
			}
			$images[$no][$img]['upload']['type'] = $myfiles['type'][$field_name];
			if (!empty($myfiles['do_not_delete'][$field_name]))
				$images[$no][$img]['upload']['do_not_delete'] = $myfiles['do_not_delete'][$field_name];
			
			// add extension to temporary filename (important for image manipulations,
			// e. g. imagemagick can only recognize .ico-files if they end in .ico)
			// but only if there is not already a file extension
			
			$oldfilename = $myfiles['tmp_name'][$field_name];
			$oldfilename = zz_upload_remote_file($oldfilename);
			$extension = zz_upload_file_extension($images[$no][$img]['upload']['name']);
			if ($oldfilename AND strtolower(substr($oldfilename, -(strlen('.'.$extension)))) != strtolower('.'.$extension)) {
				// uploaded file
				$myfilename = $oldfilename.'.'.$extension;
				// no move_uploaded_file here because file might gotten here somehow different
				rename($oldfilename, $myfilename);
			} elseif ($oldfilename) {
				// mass upload
				$myfilename = $oldfilename;
			} else {
				$myfilename = false;
			}
			$images[$no][$img]['upload']['tmp_name'] = $myfilename;
			$myfilename = false;
			
			if (!isset($myfiles['error'][$field_name])) { // PHP 4.1 and prior
				$images[$no][$img]['upload'] = zz_upload_compat_error($images[$no][$img]['upload']);
			} else {
				$images[$no][$img]['upload']['error'] = $myfiles['error'][$field_name];
			}
			if (!isset($myfiles['size'][$field_name])) {
				$myfiles['size'][$field_name] = filesize($images[$no][$img]['upload']['tmp_name']);
			}
			if ($myfiles['size'][$field_name] < 3 
				AND empty($images[$no][$img]['upload']['error'])) { 
				// don't overwrite different error messages, filesize = 0 also might be the case
				// if file which was uploaded is too big
				// file is to small or 0, might occur while incorrect refresh of browser
				$images[$no][$img]['upload']['error'] = UPLOAD_ERR_NO_FILE; // no file
				if (file_exists($images[$no][$img]['upload']['tmp_name'])) {
					// get rid of max 3 byte large file
					zz_unlink_cleanup($images[$no][$img]['upload']['tmp_name']);
				}
				$images[$no][$img]['upload']['tmp_name'] = false;
				$images[$no][$img]['upload']['type'] = false;
				$images[$no][$img]['upload']['name'] = false;
			} else {
				$images[$no][$img]['upload']['size'] = $myfiles['size'][$field_name];
			}
			switch ($images[$no][$img]['upload']['error']) {
				case UPLOAD_ERR_NO_FILE: continue 2; // no file
				case UPLOAD_ERR_PARTIAL: continue 2; // partial upload
				case UPLOAD_ERR_FORM_SIZE: continue 2; // file is too big
				case UPLOAD_ERR_INI_SIZE: continue 2; // file is too big
				case UPLOAD_ERR_OK: break; // everything ok.
			}
			// get upload info
			$images[$no][$img]['upload'] = zz_upload_fileinfo($images[$no][$img]['upload'], $extension);

			// input_filetypes
			if (empty($images[$no][$img]['input_filetypes'])) {
				// if not set, inherit from $zz['fields'][n]['input_filetypes']
				// or initialize it
				if (isset($field['input_filetypes'])) {
					$images[$no][$img]['input_filetypes'] = $field['input_filetypes'];
				} else {
					$images[$no][$img]['input_filetypes'] = array();
				}
			}
			// input_filetypes must be an array
			if (!is_array($images[$no][$img]['input_filetypes'])) {
				$images[$no][$img]['input_filetypes'] = array($images[$no][$img]['input_filetypes']);
			}

			//	check if filetype is allowed
			if (!in_array($images[$no][$img]['upload']['filetype'], $images[$no][$img]['input_filetypes'])) {
				$filetype = $images[$no][$img]['upload']['filetype'];
				if ($filetype == 'unknown') // give more information
					$filetype .= ' ('.htmlspecialchars($images[$no][$img]['upload']['type']).')';
				$images[$no][$img]['unsupported_filetype'] = zz_text('Error: ')
					.zz_text('Unsupported filetype:').' '
					.$filetype
					.'<br class="nonewline_in_mail">'.zz_text('Supported filetypes are:').' '
					.implode(', ', $images[$no][$img]['input_filetypes']);
				$my_rec['file_upload'] = false;
			} else {
				$my_rec['file_upload'] = true;
			}
		}
		$my_rec['images'] = $images;
	}
	if ($zz_conf['modules']['debug']) zz_debug('end');
	return $zz_tab;
}

/**
 * downloads a file from a remote location as source for file upload
 * only works with zzform_multi(), otherwise filename cannot be set to URL
 *
 * @param string $filename
 * @global array $zz_conf 'tmp_dir'
 * @return string temp filename on local server
 * @todo add further registered streams if necessary
 * @todo preserve timestamp (parse http headers?)
 */
function zz_upload_remote_file($filename) {
	if (substr($filename, 0, 7) !== 'http://'
		AND substr($filename, 0, 8) !== 'https://'
		AND substr($filename, 0, 6) !== 'ftp://'
	) {
		return $filename;
	}
	global $zz_conf;
	$tmp = $zz_conf['tmp_dir'];
	if (!is_dir($tmp)) $tmp = sys_get_temp_dir();

	// download file
	$filename_new = tempnam($tmp, 'DL_');
	copy($filename, $filename_new);
	return $filename_new;
}	

/**
 * checks which files allow file upload
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
 *	1c. @todo: finfo_file, see: http://www.php.net/manual/en/function.finfo-file.php
 *			(c. relies on magic.mime file)
 *	1d. use identify in imagemagick
 *	2. if this is impossible, check for file extension
 * @param array $file
 *		(upload:) string 'tmp_name',
 *		(upload, optional:): int 'size', string 'name', string 'type', int 'error',
 *		(optional:) bool 'do_not_delete'
 * @param string $extension (optional)
 * @return array $file, multidimensional information about images
 *		(existing +) bool 'validated', string 'filetype'
 *		(all optional:) string 'filetype_file', string 'ext', string 'mime', 
 *		string 'charset', int 'width', int 'height', string 'bits', 
 *		string 'channels', array 'exif'
 */
function zz_upload_fileinfo($file, $extension = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$file['validated'] = false;
	$file['filetype'] = 'unknown';
	$file['mime'] = !empty($file['type']) ? $file['type'] : '';
	// check if there is a file
	if (empty($file['tmp_name'])) return $file;
	// rewrite some misspelled and misset filetypes
	if (!empty($file['type'])) {
		if (in_array($file['type'], array_keys($zz_conf['mime_types_rewritten'])))
			$file['type'] = $zz_conf['mime_types_rewritten'][$file['type']];
	}
	// check whether filesize is above 2 bytes or it will give a read error
	if (empty($file['size'])) $file['size'] = filesize($file['tmp_name']);
	if ($file['size'] <= 3) return zz_return($file);

	$filename = $file['tmp_name'];
	if (!$extension) $extension = zz_upload_file_extension($filename);

	// check filetype by several means
	if ($zz_conf['modules']['debug']) zz_debug('file', json_encode($file));
	$functions = array(
		'upload_getimagesize',		// getimagesize(), PHP
		'upload_exif_imagetype',	// exif_imagetype(), PHP > 4.3.0
		'imagick_identify',			// ImageMagick identify command
		'upload_unix_file'			// Unix file command
	);
	foreach ($functions as $function) {
		$function_name = 'zz_'.$function;
		if (!function_exists($function_name)) continue;
		$file = $function_name($filename, $file);
		if (!empty($file['recheck'])) {
			foreach ($file['recheck'] as $type) {
				if ($file['filetype'] != $type) continue;
				if (zz_upload_extension_matches_type($extension, $type)) continue;
				// this filetype/extension combination needs to be re-checked
				$file['validated'] = false;
			}
			unset($file['recheck']);
		}
		if ($zz_conf['modules']['debug']) {
			$function_name = substr($function_name, strpos('_', $function_name));
			$type = !empty($file['filetype_file']) ? $file['filetype_file'] : $file['filetype'];
			zz_debug($function_name."()", $type.': '.json_encode($file));
		}
	}
	// @todo: allow further file testing here, e. g. for PDF, DXF
	// and others, go for Identifying Characters.
	// maybe use magic_mime_type()
	if (empty($file['validated'])) {
		if (zz_upload_mimecheck($file['mime'], $extension)) {
			// Error: this mimetype/extension combination was already checked
			$file['ext'] = 'unknown-'.$extension;
			$file['mime'] = 'unknown';
			$file['filetype'] = 'unknown';
		} else {
			$filetype = zz_upload_filecheck($file['mime'], $extension);
			if ($filetype) {
				$file['ext'] = $filetype['ext'];
				$file['mime'] = $filetype['mime'];
				$file['filetype'] = $filetype['filetype'];
			} else {
				$file['ext'] = 'unknown-'.$extension;
				if (isset($file['type']))
					$file['mime'] = 'unknown: '.$file['type']; // show user upload type
				$file['filetype'] = 'unknown';
			}
		}
	}
	// some filetypes are identical to others, so we have to check the
	// extension
	if ($extension == 'ai' AND $file['filetype'] == 'pdf') {
		// it's a valid PDF, so it might be an AI file
		$file['filetype'] = 'ai';
		$file['mime'] = 'application/postscript';
	}
	if ($zz_conf['modules']['debug']) zz_debug('finish', json_encode($file));

	// save unknown files for debugging
	zz_upload_error_with_file($filename, $file);

	// read metadata
	if (function_exists('exif_read_data') 
		AND in_array($file['filetype'], $zz_conf['exif_supported'])) {
		// you will need enough memory size to handle this if you are uploading
		// pictures with layers from photoshop, because the original image is
		// saved as metadata. exif_read_data() cannot read only the array keys
		// or you could exclude key ImageSourceData where the original image
		// is kept
		$file['exif'] = exif_read_data($filename);
	}
	// @todo: further functions, e. g. zz_pdf_read_data if filetype == pdf ...
	// @todo: or read AutoCAD Version from DXF, DWG, ...
	// @todo: or read IPCT data.
	
	return zz_return($file);
}

/**
 * converts filename to human readable string
 * 
 * @param string $filename filename
 * @return string title
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_make_title($filename) {
	// remove file extension up to 4 letters
	$filename = preg_replace('/\.[a-zA-Z0-9]*$/', '', $filename);
	$filename = str_replace('_', ' ', $filename); // make output more readable
	$filename = str_replace('.', ' ', $filename); // make output more readable
	$filename = ucfirst($filename);
	return $filename;
}

/**
 * converts filename to wanted filename
 * 
 * @param string $filename filename
 * @return string filename
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_make_name($filename) {
	// remove file extension up to 4 letters
	$filename = preg_replace('/\.[a-zA-Z0-9]*$/', '', $filename);
	$filename = forceFilename($filename);
	return $filename;
}

/**
 * checks whether a given combination of mimetype and extension exists
 * 
 * @param string $mimetype mime type
 * @param string $extension file extension
 * @return boolean
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_mimecheck($mimetype, $extension) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	foreach ($zz_conf['image_types'] as $imagetype)
		if ($imagetype['mime'] == $mimetype AND $imagetype['ext'] == $extension)
			return zz_return(true);
	if ($zz_conf['modules']['debug']) zz_debug('combination not yet checked');
	return zz_return(false);
}

/**
 * checks if extension matches type
 *
 * @param string $extension
 * @param string $type
 * @global array $zz_conf
 * @return bool
 */
function zz_upload_extension_matches_type($extension, $type) {
	if ($extension == $type) return true;
	if (empty($zz_conf['upload_filetype_map'][$type])) return false;
	if ($extension == $zz_conf['upload_filetype_map'][$type]) return true;
	return false;
}

/**
 * checks from a list of filetypes if mimetype and extension match with
 * a filetype from this list
 * 
 * @param string $mimetype mime type
 * @param string $extension file extension
 * @global array $zz_conf 'file_types'
 * @return string $type or false
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_filecheck($mimetype, $extension) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) {
		zz_debug('start', __FUNCTION__);
		zz_debug('check', $mimetype.' .'.$extension);
	}
	$extension = strtolower($extension);
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
		return zz_return($type1); // 1st priority: mimetype AND extension match
	if ($type2 && $type2unique) 
		return zz_return($type2); // 2nd priority: extension matches AND is unique
	if ($type3 && $type3unique) 
		return zz_return($type3); // 3rd priority: mimetype matches AND is unique
}

/**
 * get information about the filetype from PHPs getimagesize()
 *
 * @param string $filename
 * @param array $file
 * @global array $zz_conf (array 'image_types')
 * @return array $file
 *		(exists): int 'width', int 'height', string 'ext', string 'mime',
 *		string 'filetype', bool 'validated'
 *		(optional): string 'bits', string 'channels',
 *		(internal, optional): array 'recheck'
 */
function zz_upload_getimagesize($filename, $file) {
	global $zz_conf;
	if (!function_exists('getimagesize')) return $file;
	if (!file_exists($filename)) return $file;
	if (is_dir($filename)) return $file;
	$sizes = getimagesize($filename);
	if (!$sizes) return $file;
	if (empty($zz_conf['image_types'][$sizes[2]])) return $file;
	$file['width'] = $sizes[0];
	$file['height'] = $sizes[1];
	$file['ext'] = $zz_conf['image_types'][$sizes[2]]['ext'];
	$file['mime'] = $zz_conf['image_types'][$sizes[2]]['mime'];
	$file['filetype'] = $zz_conf['image_types'][$sizes[2]]['filetype'];
	if (!empty($sizes['bits'])) $file['bits'] = $sizes['bits'];
	if (!empty($sizes['channels'])) $file['channels'] = $sizes['channels'];
	$file['recheck'] = array('tiff'); // RAW images might be recognized as TIFF
	$file['validated'] = true;
	return $file;
}

/**
 * get information about the filetype from PHPs exif_imagetype()
 *
 * @param string $filename
 * @param array $file
 * @return array $file
 *		(exists): string 'ext', string 'mime', string 'filetype', bool 'validated',
 *		(internal, optional): array 'recheck'
 */
function zz_upload_exif_imagetype($filename, $file) {
	global $zz_conf;
	if (!function_exists('exif_imagetype')) return $file; 
	if ($file['validated']) return $file;
	$imagetype = exif_imagetype($filename);
	if (!$imagetype) return $file;
	if (empty($zz_conf['image_types'][$imagetype])) return $file;
	$file['ext'] = $zz_conf['image_types'][$imagetype]['ext'];
	$file['mime'] = $zz_conf['image_types'][$imagetype]['mime'];
	$file['filetype'] = $zz_conf['image_types'][$imagetype]['filetype'];
	$file['recheck'] = array('tiff'); // RAW images might be recognized as TIFF
	$file['validated'] = true;
	return $file;
} 

/**
 * get information about the filetype from unix command 'file'
 *
 * @param string $filename
 * @param array $file
 * @global array $zz_conf
 * @return array $file
 *		(optional): string 'filetype_file',
 *		string 'type', string 'charset', string 'ext', string 'mime',
 *		string 'filetype', bool 'validated'
 */
function zz_upload_unix_file($filename, $file) {
	global $zz_conf;
	if (!$zz_conf['upload_tools']['fileinfo']) return $file;

	$fileinfo = $zz_conf['upload_tools']['fileinfo_whereis'];
	zz_upload_exec($fileinfo.' --brief "'.$filename.'"', 'Fileinfo', $output);
	if (!$output) return $file;
	if ($zz_conf['modules']['debug']) {
		zz_debug('file brief', json_encode($output));
	}
	$file['filetype_file'] = $output[0];
	unset($output);

	// get mime type
	// attention, -I changed to -i in file, don't use shorthand here
	zz_upload_exec($fileinfo.' --mime --brief "'.$filename.'"', 'Fileinfo with MIME', $output);
	if ($zz_conf['modules']['debug']) {
		zz_debug('file mime', json_encode($output));
	}
	if (empty($output[0])) return $file;

	$file['mime'] = $output[0];
	// save charset somewhere else
	// application/pdf; charset=binary or text/plain; charset=utf-8
	if (strstr($file['mime'], ';')) {
		$type = explode(';', $file['mime']);
		$file['mime'] = array_shift($type);
		foreach ($type as $appendix) {
			$appendix = trim($appendix);
			if (strstr($appendix, '=')) {
				$appendix = explode('=', $appendix);
				$file[$appendix[0]] = $appendix[1];
			} else {
				$file[$appendix] = true;
			}
		}
	}
	$imagetype = false;
	// check if it's an autocad document
	if (substr($file['filetype_file'], 0, 20) == 'DWG AutoDesk AutoCAD') {
		$imagetype = 'dwg';
		$file['validated'] = true;
	} elseif ($file['filetype_file'] == 'AutoCad (release 14)') {
		$imagetype = 'dwg';
		$file['validated'] = true;
	}
	// @todo: check this, these are not only DOCs but also MPPs.
//	} elseif ($file['filetype_file'] == 'Microsoft Office Document') {
//		$imagetype = 'doc';
//		$file['validated'] = true;
//	} elseif ($file['filetype_file'] == 'data') {
	// ...
	if (!empty($file['validated']) AND $imagetype) {
		$file['ext'] = $zz_conf['file_types'][$imagetype][0]['ext'];
		$file['mime'] = $zz_conf['file_types'][$imagetype][0]['mime'];
		$file['filetype'] = $zz_conf['file_types'][$imagetype][0]['filetype'];
	}
	return $file;
}

/**
 * saves files with an unknown filetype or with an error while converting
 * in the backup directory for further inspection, sends mail to admin
 *
 * @param string $filename
 * @param array $file
 * @param string $type type of error: 'unknown' = unknown file; 'convert' = 
 *		error while converting a file
 * @global array $zz_conf
 *		'debug_upload', 'backup', 'backup_dir'
 * @global array $zz_error
 * @return bool false: nothing was found, true: unknown file was found
 */
function zz_upload_error_with_file($filename, $file, $type = 'unknown') {
	global $zz_conf;
	global $zz_error;
	if ($type === 'unknown' AND $file['filetype'] != 'unknown') return false;
	if (empty($zz_conf['debug_upload'])) return false;

	$error_filename = false;
	if ($zz_conf['backup']) {
		// don't return here in case of error - 
		// it's not so important to break the whole process
		$my_error = $zz_error['error'];
		$error_filename = zz_upload_path($zz_conf['backup_dir'], 'error', $filename);
		if (!$zz_error['error'])
			copy($filename, $error_filename);
		$zz_error['error'] = $my_error;
	}
	switch ($type) {
	case 'unknown':
		$msg_dev = 'There was an attempt to upload the following file but the filetype is unknown.';
		break;
	case 'convert':
		$msg_dev = 'While converting the following file, an error occured.';
		break;
	}
	$msg_dev .= zz_text($msg_dev)."\n\n".var_export($file, true);
	if ($error_filename)
		$msg_dev .= "\r\n".zz_text('The file was temporarily saved under: ').$error_filename;
	$zz_error[] = array(
		'msg_dev' => $msg_dev,
		'log_post_data' => false,
		'level' => E_USER_NOTICE
	);
	zz_error();
	return true;
}

/**
 * prepares files for upload (resize, rotate etc.)
 * 
 * 1- checks user input via option fields
 * 2- checks which source has to be used (own source, other fields source)
 *    gets further information from source if necessary
 * 3- calls auto functions if set
 * 4- skips rest of procedure on if ignore is set
 * 5- perform actions (e. g. decrease size, rotate etc.) on file if applicable,
 *    modified files will get temporary filename
 *
 * @param array $zz_tab complete table data
 * @global array $zz_conf configuration variables
 * @global array $zz_error
 * @return array $zz_tab changed
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_prepare($zz_tab) {
	// do only something if there are upload_fields
	if (empty($zz_tab[0]['upload_fields'])) return $zz_tab;

	global $zz_conf;
	global $zz_error;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$all_temp_filenames = array();
	
	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$tab = $uf['tab'];
		$rec = $uf['rec'];
		$no = $uf['f'];
		$my_rec = &$zz_tab[$tab][$rec];
		if (!empty($my_rec['images'][$no]['read_from_session'])) continue;

		foreach ($my_rec['fields'][$no]['image'] as $img => $val) {
			if ($zz_conf['modules']['debug']) {
				zz_debug('preparing ['.$tab.']['.$rec.'] - '.$img);
			}
			if (empty($my_rec['images'][$no][$img])) continue;

			// reference on image data
			$image = $my_rec['images'][$no][$img];
			if (!empty($image['unsupported_filetype'])) {
				// get rid of the file and go on
				if (empty($image['upload']['do_not_delete'])) {
					zz_unlink_cleanup($image['upload']['tmp_name']);
				}
				continue;
			}
			$image = zz_upload_merge_options($image, $my_rec, $zz_tab[$tab], $rec);

			if (!empty($image['ignore'])) {
				$my_rec['images'][$no][$img] = $image;
				continue; // ignore image
			}

			$dont_use_upload = false;
			$src_image = false;
			if (!empty($image['source_field'])) {
				$image['source'] = !empty($image['source']) ? $image['source'] : 0;
				$src_image = zz_upload_get_source_field($image, $zz_tab);
				// nothing adequate found, so we can go on with source_file instead!
				if (!$src_image) unset($image['source']); 
			}

			// check which source file shall be used
			if (isset($image['source'])) { // must be isset, because 'source' might be 0
				// it's a thumbnail or some other derivate from the original file
				if ($zz_conf['modules']['debug']) zz_debug('source: '.$image['source']);

				if (!$src_image) // might come from zz_upload_get_source_field()
					$src_image = $my_rec['images'][$no][$image['source']];
				if (!empty($src_image['unsupported_filetype'])) continue;
				if (!empty($image['use_modified_source'])) {
					// get filename from modified source, false if there was an error
					$source_filename = isset($src_image['files']) 
						? $src_image['files']['tmp_files'][$image['source']] : false;
					if (!$source_filename AND $zz_conf['modules']['debug']) 
						zz_debug('use_modified_source: no source filename!');
				} else
					$source_filename = $src_image['upload']['tmp_name'];
				// get some variables from source image as well
				$image['upload'] = $src_image['upload']; 
				// check if it's not a form that allows upload of different filetypes at once
				// cross check input filetypes
				if (!empty($image['input_filetypes']) AND !empty($src_image['upload']['filetype'])) {
					// continue if this file shall not be touched.
					if (!in_array($src_image['upload']['filetype'], $image['input_filetypes'])) continue;
				}
				unset($src_image);
				$dont_use_upload = true;
			} elseif (!empty($image['source_file'])) {
				$source_filename = false;
				// get source file
				// test, whether it is an ID field with ... or not
				unset($field_index);
				// convert string in ID, if it's a checkselect
				foreach ($my_rec['fields'] as $index => $field) {
					if ($field['field_name'] == $image['source_file']
						AND $my_rec['POST'][$image['source_file']]) {
						$field_index = $index;
					} 
				}
				if (isset($field_index)) {
					if ($my_rec['fields'][$field_index]['type'] == 'select') {
						$my_rec = zz_check_select($my_rec, $field_index, $zz_conf['max_select'], 
							$zz_tab[$tab]['table'].'['.$rec.']['.$my_rec['fields'][$field_index]['field_name'].']', 
							$zz_tab[$tab]['db_name'].'.'.$zz_tab[$tab]['table']);
					}
					$sql = sprintf($image['source_path_sql'], $my_rec['POST'][$image['source_file']]);
					if (!empty($image['update_from_source_field_name']) AND !empty($image['update_from_source_value'])) {
						$where = array();
						foreach ($image['update_from_source_field_name'] as $index => $field_name) {
							if (!array_key_exists($image['update_from_source_value'][$index], $zz_tab[$tab]['existing'][$rec])) continue;
							$field_value = $zz_tab[$tab]['existing'][$rec][$image['update_from_source_value'][$index]];
							if ($field_value) {
								$where[] = sprintf('(%s != "%s" OR ISNULL(%s))', $field_name, $field_value, $field_name);
							} else {
								$where[] = sprintf('!ISNULL(%s)', $field_name);
							}
						}
						if ($where) {
							$sql = zz_edit_sql($sql, 'WHERE', implode(' OR ', $where));
						}
					}
					$old_record = zz_db_fetch($sql);
					if ($old_record) {
						$source_tab[$tab]['existing'][$rec] = $old_record;
						$source_filename = zz_makepath($image['source_path'], $source_tab, 'old', 'file', $tab, $rec);
						unset($source_tab);
						if (file_exists($source_filename)) {
							$image['upload']['name'] = basename($source_filename);
							$image['upload']['tmp_name'] = $source_filename; // same because it's no upload
							$image['upload']['error'] = 0;
							$image['upload'] = zz_upload_fileinfo($image['upload']);
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
			if ($source_filename) {
				$image = zz_upload_auto_image($image);
				if (!$image) zz_return($zz_tab);
			}

			if ($zz_conf['modules']['debug']) zz_debug('source_filename: '.$source_filename);
			if ($source_filename && $source_filename != 'none') {
				// only if something new was uploaded!
				$filename = file_exists($source_filename) ? $source_filename : '';
				$image['modified'] = zz_upload_create_thumbnails($filename, $image, $my_rec);
				if ($image['modified'] === -1) {
					$filename = false; // do not upload anything
					// @todo: mark existing image for deletion if there is one!							
					$image['delete_thumbnail'] = true;
					$my_rec['no_file_upload'] = true;
				} elseif ($image['modified']) {
					$filename = $image['modified']['tmp_name'];
					$all_temp_filenames[] = $image['modified']['tmp_name'];
					$my_rec['file_upload'] = true;
				} else {
					unset($image['modified']);
				}
				$image['files']['tmp_files'][$img] = $filename;
				if (!empty($my_rec['images'][$no]['all_temp']))
					$my_rec['images'][$no]['all_temp'] = array_merge($my_rec['images'][$no]['all_temp'], $all_temp_filenames);
				else
					$my_rec['images'][$no]['all_temp'] = $all_temp_filenames; // for later cleanup of leftover tmp files
				$all_temp_filenames = array();
			}
			// write $image back to $zz_tab
			$my_rec['images'][$no][$img] = $image;
		}
	}
	// return true or false
	// output errors
	return zz_return($zz_tab);
}

/**
 * file operations (thumbnails etc.) for images
 *
 * @param string $filename
 * @param array $image
 * @param array $my_rec
 * @global array $zz_conf
 * @global array $zz_error
 * @return mixed $modified (false: does not apply; -1: error; array: success)
 */
function zz_upload_create_thumbnails($filename, $image, $my_rec) {
	global $zz_conf;
	global $zz_error;
	
	if (empty($image['action'])) return false;

	if (!$filename) {
		$zz_error[] = array(
			'msg_dev' => sprintf(zz_text('Error: Source file %s does not exist. '), $filename),
			'log_post_data' => false
		);
		return false;
	}
	
	// create temporary file, so that original file remains the same 
	// for further actions
	$tmp_filename = tempnam(realpath($zz_conf['tmp_dir']), 'UPLOAD_');
	$dest_extension = zz_upload_extension($image['path'], $my_rec);
	if (!$dest_extension) {
		$dest_extension = strtolower($image['upload']['ext']);
		// map files to extensions, e. g. TIFF to PNG
		if (!empty($zz_conf['upload_destination_filetype'][$dest_extension]))
			$dest_extension = $zz_conf['upload_destination_filetype'][$dest_extension];
	}
	if (in_array($dest_extension, $zz_conf['upload_no_thumbnails'])) return false;

	$zz_conf['int']['no_image_action'] = false;
	$action = 'zz_image_'.$image['action'];
	$action($filename, $tmp_filename, $dest_extension, $image);
	if (!file_exists($tmp_filename)) {
		$zz_error[] = array(
			'msg_dev' => sprintf(zz_text('Error: File %s does not exist. Temporary Directory: %s'), 
				$tmp_filename, realpath($zz_conf['tmp_dir'])),
			'log_post_data' => false
		);
		return false;
	}
	if (filesize($tmp_filename) > 3) {
		$modified = array();
		$modified['tmp_name'] = $tmp_filename;
		$modified = zz_upload_fileinfo($modified, $dest_extension);
		// @todo: ['modified']['name'] ?? necessary? so far, it's not.
	}  else {
		// image action did not work out the way it should have.
		$modified = -1;
		if (!$zz_conf['int']['no_image_action']) {
			$zz_error[] = array(
				'msg_dev' => sprintf(zz_text('No real file was returned from function %s'), '<code>'.$action.'()</code>'),
				'log_post_data' => false,
				'level' => E_USER_NOTICE
			);
		}
		zz_upload_error_with_file($tmp_filename, $image, 'convert');
		zz_unlink_cleanup($tmp_filename);
	}
	$zz_conf['int']['no_image_action'] = false;
	zz_error();
	return $modified;
}

/**
 * Merges settings depending on 'options' field to current image settings
 *
 * @param array $image
 * @param array $my_rec ($zz_tab[$tab][$rec])
 * @param array $my_tab ($zz_tab[$tab])
 * @param int $rec
 * @return array $image
 */
function zz_upload_merge_options($image, $my_rec, $my_tab, $rec) {
	global $zz_conf;
	
	if (empty($image['options'])) return $image;
	// to make it easier, allow input without array construct as well
	if (!is_array($image['options'])) 
		$image['options'] = array($image['options']); 
	if (isset($image['options_sql']) AND !is_array($image['options_sql'])) 
		$image['options_sql'] = array($image['options_sql']); 
	// go through all options
	foreach ($image['options'] as $index => $no) {
		// check if we have the corresponding field, if not, simply ignore it!
		if (empty($my_rec['fields'][$no])) continue;
		// field_name of field where options reside
		$field_name = $my_rec['fields'][$no]['field_name']; 
		// this is the selected option
		if ($my_rec['fields'][$no]['type'] === 'select') {
			// @todo do this in action module beforehands
			$my_rec = zz_check_select($my_rec, $no, $zz_conf['max_select'], 
				$my_tab['table'].'['.$rec.']['.$my_rec['fields'][$no]['field_name'].']', 
				$my_tab['db_name'].'.'.$my_tab['table']);
		}
		$option_value = $my_rec['POST'][$field_name];
		if (!empty($image['options_sql'][$index]) AND $option_value) {
			// get options from database
			$sql = sprintf($image['options_sql'][$index], zz_db_escape($option_value));
			$option_record = zz_db_fetch($sql, '', 'single value');
			if ($option_record) {
				parse_str($option_record, $options[$option_value]);
			} else {
				$options[$option_value] = array();
			}
		} else {
			// get options from field
			$options = $my_rec['fields'][$no]['options'];
		}
		// overwrite values in script with selected option
		$image = array_merge($image, $options[$option_value]); 
	}
	return $image;
}

/*
 * get definition for source image from a different field
 *
 * @param array $image ('source', 'source_field')
 * @param array $zz_tab
 * @return array $src_image
 */
function zz_upload_get_source_field($image, $zz_tab) {				
	// values might be numeric (e. g. 14) for main table
	// or array style (14[20]) for subtables
	// source might only be 0, if other values are needed change to 
	// if unset image source then 0
	$source_field = array();
	if (strstr($image['source_field'], '[')) { 
		// it's an array like 44[20] meaning field 44, subtable, there field 20
		preg_match('/(\d+)\[(\d+)\]/', $image['source_field'], $source_field);
		array_shift($source_field); // we don't need the 44[20] result
	} else { // field in main table
		$source_field[0] = $image['source_field'];
	}
	$src_image = false;
	foreach ($zz_tab[0]['upload_fields'] AS $index => $tab) {
		if ($tab['field_index'] != $source_field[0]) continue;
		if (!empty($source_field[1]) AND $tab['f'] != $source_field[1]) continue;

		// if there are several subtables, value for 0 should always be set.
		// then go through other subtables, if there's a better field,
		// re-set $src_image!
		$my_rec = $zz_tab[$tab['tab']][$tab['rec']];
		if (empty($my_rec['images'][$tab['f']][$image['source']])) continue;

		// ok, this is a reason to get it
		$get_image = $my_rec['images'][$tab['f']][$image['source']]; 
		// check if no picture, no required, no id then false
		$id_field_name = $my_rec['id']['field_name'];
		if (!empty($get_image['upload']['error']) AND !isset($my_rec['POST'][$id_field_name])) {
			$get_image = false;
		}
		// if there's something, overwrite $src_image
		if ($get_image) $src_image = $get_image;
	}
	return $src_image;
}

/**
 * calls function that will do some automatic stuff to images
 *
 * @param array $image
 * @return mixed array $image if everything is ok, false if an error occured
 */
function zz_upload_auto_image($image) {
	if (empty($image['auto'])) return $image;
	if (empty($image['auto_values'])) return $image;

	// choose values from uploaded image, best fit
	$autofunc = 'zz_image_auto_'.$image['auto'];
	if (!function_exists($autofunc)) {
		global $zz_error;
		$zz_error[] = array(
			'msg_dev' => sprintf(zz_text('Configuration error: function %s for image upload does not exist.'), '<code>'.$autofunc.'()</code>'),
			'level' => E_USER_ERROR
		);
		zz_error();
		return false;
	}
	$image = $autofunc($image);
	return $image;
}

/**
 * get wanted file extension from database record
 * 
 * @param array $path
 * @param array $my_rec = $zz_tab[$tab][$rec]
 * @return string $extension
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_extension($path, &$my_rec) {
	// @todo: implement mode!
	$path_value = end($path);
	$path_key = key($path);

	if (substr($path_key, 0, 6) == 'string') {
		if (strstr($path_value, '.'))
			return substr($path_value, strrpos($path_value, '.')+1);
		else
			return $path_value;
	} elseif (substr($path_key, 0, 5) == 'field') {
		$content = isset($my_rec['POST'][$path_value]) ? $my_rec['POST'][$path_value] : '';
		if (strstr($content, '.'))
			$extension = substr($content, strrpos($content, '.')+1);
		else
			$extension = $content;
		if ($extension) return $extension;

		// check for sql-query which gives extension. usual way does not work, 
		// because at this stage record is not saved yet.
		foreach (array_keys($my_rec['fields']) as $no) {
			if (empty($my_rec['fields'][$no]['display_field']) 
				OR $my_rec['fields'][$no]['display_field'] != $path_value) continue;
			$sql = $my_rec['fields'][$no]['path_sql'];
			if ($id_value = $my_rec['POST'][$my_rec['fields'][$no]['field_name']]) {
				$extension = zz_db_fetch($sql.$id_value, '', 'single value');
				if (!$extension) $extension = false;
			} else {
				$extension = false; // no extension could be found,
				// probably due to extension from field which has not been filled yet
				// does not matter, that means that filetype for destination
				// file remains the same.
			}
		}
		return $extension;
	}
	$zz_error[] = array(
		'msg_dev' => zz_text('Error. Could not determine file ending'),
		'level' => E_USER_ERROR
	);
	return false;	
}

/**
 * checks uploads for conformity and problems
 * 
 * @param array $images $zz_tab[$tab][$rec]['images']
 * @param string $action sql action (insert|delete|update)
 * @global array $zz_conf configuration variables
 * @return bool true/false
 * @return $images might change as well (?)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_check(&$images, $action, $rec = 0) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if ($zz_conf['modules']['debug']) zz_debug();
	$error = false;
	if (!$images) return zz_return(false);

	foreach (array_keys($images) as $img) {
	//	check if image was uploaded
		if (!is_numeric($img)) continue; //file_name, title
		if (!isset($images[$img]['required'])) $images[$img]['required'] = false;
		if ($rec AND !empty($images[$img]['required_only_first_detail_record']))
			$images[$img]['required'] = false;
		$images[$img]['error'] = array();
		if (empty($images[$img]['field_name'])) continue;
		if (!isset($images[$img]['upload']['error']))
			$images[$img]['upload']['error'] = UPLOAD_ERR_NO_FILE;

		switch ($images[$img]['upload']['error']) {
			case UPLOAD_ERR_NO_FILE: // no file
				if ($images[$img]['required'] && $action == 'insert') // required only for insert
					$images[$img]['error'][] = zz_text('Error: ').zz_text('No file was uploaded.');
				else continue 2;
				break;
			case UPLOAD_ERR_PARTIAL: // partial upload
				$images[$img]['error'][] = zz_text('Error: ').zz_text('File was only partially uploaded.');
				break; 
			case UPLOAD_ERR_FORM_SIZE: // file is too big
			case UPLOAD_ERR_INI_SIZE: // file is too big
				$images[$img]['error'][] = zz_text('Error: ').zz_text('File is too big.').' '
					.zz_text('Maximum allowed filesize is').' '
					.zz_byte_format($zz_conf['upload_MAX_FILE_SIZE']); // Max allowed
				break; 
			case UPLOAD_ERR_OK: // everything ok.
				break; 
		}
		if ($images[$img]['error']) {
			$error = true;
			continue;
		}
		
//	check if filetype is allowed
		if (!empty($images[$img]['unsupported_filetype'])) {
			$images[$img]['error'][] = $images[$img]['unsupported_filetype'];
			$error = true;
			// don't do further checks, because filetype is wrong anyways
			continue; 
		}

//	check if minimal image size is reached
		$width_height = array('width', 'height');
		foreach ($width_height as $which)
			if (!empty($images[$img]['min_'.$which]) 
				&& $images[$img]['min_'.$which] > $images[$img]['upload'][$which])
				$images[$img]['error'][] = zz_text('Error: ')
					.sprintf(zz_text('Minimum '.$which
					.' %s was not reached.'), '('.$images[$img]['min_'.$which].'px)')
					.' ('.$images[$img]['upload'][$which].'px)';

//	check if maximal image size has not been exceeded
		$width_height = array('width', 'height');
		foreach ($width_height as $which)
			if (!empty($images[$img]['max_'.$which])
				&& $images[$img]['max_'.$which] < $images[$img]['upload'][$which])
				$images[$img]['error'][] = zz_text('Error: ')
					.sprintf(zz_text('Maximum '.$which
					.' %s has been exceeded.'), '('.$images[$img]['max_'.$which].'px)')
					.' ('.$images[$img]['upload'][$which].'px)';

		if ($images[$img]['error']) $error = true;
	}
	if ($error) return zz_return(false);
	else return zz_return(true);
}

/**
 * writes values from upload metadata to fields
 *
 * @param array $zz_tab
 * @param int $f
 * @param int $tab (optional)
 * @param int $rec (optional)
 * @global array $zz_error
 * @return string
 */
function zz_write_upload_fields($zz_tab, $f, $tab = 0, $rec = 0) {
	global $zz_error;
	
	$field = $zz_tab[$tab][$rec]['fields'][$f];
	$posted = $zz_tab[$tab][$rec]['POST'][$field['field_name']];
	$images = false;

	if (!strstr($field['upload_field'], '[')) {
		// file from main record
		// check if something was uploaded
		if (empty($zz_tab[$tab][$rec]['file_upload'])) return $posted;
		//	insert data from file upload/convert
		$images = $zz_tab[$tab][$rec]['images'][$field['upload_field']];
	} else {
		// file from detail record
		preg_match('~(\d+)\[(\d+)\]\[(\d+)\]~', $field['upload_field'], $nos);
		// check if definition is correct
		if (count($nos) !== 4) {
			$zz_error[] = array(
				'msg_dev' => 'Error in $zz definition for upload_field: ['.$f.']',
				'level' => E_USER_NOTICE
			);
		} elseif (!empty($zz_tab[$nos[1]][$nos[2]]['images'][$nos[3]])) {
			// check if something was uploaded
			if (empty($zz_tab[$nos[1]][$nos[2]]['file_upload'])) return $posted;
			//	insert data from file upload/convert
			$images = $zz_tab[$nos[1]][$nos[2]]['images'][$nos[3]];
		}
	}
	// if there's no such value, return unchanged
	if (!$images) return $posted;
	
	//	insert data from file upload/convert
	return zz_val_get_from_upload($field, $images, $posted);
}

/**
 * if 'upload_field' is set, gets values for fields from this upload field
 * either plain values, exif values or even values with an SQL query from
 * the database
 *
 * @param array $field
 * @param array $images
 * @param array $post
 * @global array $zz_conf
 * @return array $post
 */
function zz_val_get_from_upload($field, $images, $post) {
	global $zz_conf;

	$possible_upload_fields = array('date', 'time', 'text', 'memo', 'hidden', 
		'number', 'select');
	if (!in_array($field['type'], $possible_upload_fields)) 
		return $post;
	// apart from hidden, set only values if no values have been set so far
	if ($field['type'] !== 'hidden' AND !empty($post))
		return $post;

	$myval = false;
	$v_arr = false;
	$g = $field['upload_field'];
	$possible_values = $field['upload_value'];
	if (!is_array($possible_values)) $possible_values = array($possible_values);
	
	foreach ($possible_values AS $v) {
		switch ($v) {
		case 'increment_on_change':
			// @todo: think about incrementing only if new file is different
			// from existing file; on the other hand, why should you upload the
			// same file twice? and maybe some depending files will change
			if (empty($images[0])) break;
			$increment = false;
			if (!empty($images[0]['modified']['tmp_name'])) {
				$increment = true;
			} elseif (!empty($images[0]['upload']['tmp_name'])) {
				$increment = true;
			}
			if (!$increment) break;
			if (!$post) $myval = 1;
			else $myval = $post + 1;
			break;
		case 'md5':
			if (empty($images[0])) break;
			if (!empty($images[0]['modified']['tmp_name']))
				$myval = md5_file($images[0]['modified']['tmp_name']);
			elseif (!empty($images[0]['upload']['tmp_name']))
				$myval = md5_file($images[0]['upload']['tmp_name']);
			break;
		case 'md5_source_file':
			if (empty($images[0]['upload']['tmp_name'])) break;
			$myval = md5_file($images[0]['upload']['tmp_name']);
			break;
		case 'sha1':
			if (empty($images[0])) break;
			if (!empty($images[0]['modified']['tmp_name']))
				$myval = sha1_file($images[0]['modified']['tmp_name']);
			elseif (!empty($images[0]['upload']['tmp_name']))
				$myval = sha1_file($images[0]['upload']['tmp_name']);
			break;
		case 'sha1_source_file':
			if (empty($images[0]['upload']['tmp_name'])) break;
			$myval = sha1_file($images[0]['upload']['tmp_name']);
			break;
		default:
			if (preg_match('/.+\[.+\]/', $v)) { // construct access to array values
				$myv = explode('[', $v);
				if (!isset($images[0])) break;
				$myval_upload = (isset($images[0]['upload']) ? $images[0]['upload'] : '');
				$myval_altern = $images[0];
				foreach ($myv as $v_var) {
					if (substr($v_var, -1) === ']') $v_var = substr($v_var, 0, -1);
					if (substr($v_var, -1) === '*') {
						$v_var = substr($v_var, 0, -1); // get rid of asterisk
						$arrays = array($myval_upload, $myval_altern);
						$subkeys = array();
						foreach ($arrays as $array) {
							if (!$array) continue;
							foreach ($array as $key => $value) {
								if (substr($key, 0, strlen($v_var)) == $v_var) {
									$subkeys[$key] = $value;
								}
							}
							if ($subkeys) continue; // get from _upload, then from _altern
						}
						$myval_upload = $subkeys;
						$myval_altern = false;
					} else {
						if (isset($myval_upload[$v_var])) {
							$myval_upload = $myval_upload[$v_var];
						} else $myval_upload = false;
						if (isset($myval_altern[$v_var])) {
							$myval_altern = $myval_altern[$v_var];
						} else $myval_altern = false;
					}
				}
				$myval = ($myval_upload ? $myval_upload : $myval_altern);
			} elseif (!empty($images[$v])) {
				// take value from upload-array
				$myval = $images[$v];
			} elseif (!empty($images[0]['upload'][$v])) {
				// or take value from first sub-image
				$myval = $images[0]['upload'][$v];
			} else {
				$mval = '';
			}
			// remove empty values
			if ($myval === '0/0') $myval = false; // e. g. GPS bearing
			// we don't need whitespace (DateTime field may be set to "    ..."
			if (!is_array($myval)) $myval = trim($myval);
			if ($myval === ':  :     :  :') $myval = ''; // empty DateTime
			if (!empty($field['upload_func'])) {
				$myval = $field['upload_func']($myval);
				if (is_array($myval)) $myval = $myval['value'];
			} elseif (is_array($myval)) {
				$myval = false;
			}
			if (!$myval AND !empty($field['upload_default']))
				$myval = $field['upload_default'];
			if ($myval && !empty($field['upload_sql'])) {
				$sql = $field['upload_sql'].'"'.$myval.'"';
				$myval = zz_db_fetch($sql, '', 'single value');
				if (!$myval) {
					$myval = ''; // string, not array
					if (!empty($field['upload_insert'])) {
						// ... script_name, values
					}
				}
			}
		}
		// go through this foreach until you have a value
		if ($myval) break;
	}
	if ($zz_conf['modules']['debug']) zz_debug(
		'uploadfield: '.$field['field_name'].' %'.$post.'%<br>'
		.'val: %'.$myval.'%'
	);

	if ($myval) return $myval;
	else return $post;
}

/** 
 * Deletes files when specifically requested (e. g. in multiple upload forms)
 * 
 * called from within function zz_upload_action
 * @param array $zz_tab complete table data
 * @global array $zz_conf
 *		modules[debug], backup, backup_dir
 * @return array $zz_tab with changed values
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @see zz_upload_action()
 */
function zz_upload_delete_file($zz_tab) {
	if (empty($_POST['zz_delete_file'])) return $zz_tab;

	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$action = $zz_tab[0][0]['action'];

	foreach ($_POST['zz_delete_file'] as $keys => $status) {
		if ($status != 'on') return zz_return($zz_tab); // checkbox checked
		$keys = explode('-', $keys);
		$no = (int) $keys[0];
		$image = (int) $keys[1];
		if (empty($zz_tab[0][0]['images'][$no][$image])) {
			return zz_return($zz_tab); // impossible, might be manipulation or so
		}
		$val = &$zz_tab[0][0]['images'][$no][$image];
		// new path is not interesting, old picture shall be deleted
		$old_path = zz_makepath($val['path'], $zz_tab, 'old', 'file');
		$success = zz_upload_delete($old_path, false, $action);
		if (!$success) return zz_return($zz_tab);
		foreach ($zz_tab[0][0]['images'][$no] as $img => $other_image) {
			if (!is_numeric($img)) continue;
			if (!isset($other_image['source'])) continue;
			if ($other_image['source'] != $image) continue;
			$old_path = zz_makepath($other_image['path'], $zz_tab, 'old', 'file');
			$success = zz_upload_delete($old_path, false, $action);
			if (!$success) return zz_return($zz_tab);
		}
		// remove images which base on this image as well (source = $image)
	}
	return zz_return($zz_tab);
}

/** 
 * Moves or deletes file after successful SQL operations
 * 
 * if backup variable is set to true, script will move old files to backup folder
 * called from within function zz_action
 * @param array $zz_tab complete table data
 * @global array $zz_conf configuration variables
 * @return array $zz_tab
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_action($zz_tab) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	// delete files, if necessary
	$zz_tab = zz_upload_delete_file($zz_tab);

	// create path
	// check if path exists, if not, create it
	// check if file_exists, if true, move file to backup-directory,
	//		if zz_conf says so
	// no changes: move_uploaded_file to destination directory, write new 
	//		filename to array in case this image will be needed later on 
	// changes: move changed file to dest. directory
	// on error: return error_message - critical error, because record has 
	// already been saved!

	foreach ($zz_tab[0]['upload_fields'] as $index => $uf) {
		$tab = $uf['tab'];
		$rec = $uf['rec'];
		$no = $uf['f'];
		if (empty($zz_tab[$tab][$rec])) {
			// no file, might arise if there's an exif_upload without a 
			// resulting file
			unset ($zz_tab[0]['upload_fields'][$index]);
			continue;
		}
		$my_rec = &$zz_tab[$tab][$rec];
		// to catch inserted id:
		$my_rec['POST'][$my_rec['id']['field_name']] = $my_rec['id']['value'];
		foreach ($my_rec['fields'][$no]['image'] as $img => $val) {
			$image = &$my_rec['images'][$no][$img]; // reference on image data
			$mode = false;
			$action = $zz_tab[$tab][$rec]['action'];
			// no thumbnails were made, it's a detail record that 
			// will be ignored
			if ($action === 'ignore') continue;

		// 	delete
			if ($action === 'delete') {
				$filename = zz_makepath($val['path'], $zz_tab, 'old', 'file', $tab, $rec);
				$show_filename = true;
				// optional files: don't show error message!
				if (!$filename) $show_filename = false;
				if (!empty($val['ignore'])) $show_filename = false;
				if (!empty($my_rec['fields'][$no]['optional_image'])) $show_filename = false;
				if (!empty($val['optional_image'])) $show_filename = false;
				if ($show_filename)
					$show_filename = zz_makepath($val['path'], $zz_tab, 'old', 'local', $tab, $rec);
				// delete file
				$success = zz_upload_delete($filename, $show_filename);
				if (!$success) return zz_return($zz_tab);
				continue; // deleted, so don't care about the rest of the code
			}

		//	check if some file was uploaded
			$uploaded_file = !empty($image['files']['tmp_files'][$img])
				? $image['files']['tmp_files'][$img] : '';

		//	update, only if we have an old record (might sometimes not be the case!)
			$old_path = ''; // initialize here, will be used later with delete_thumbnail
			if ($action === 'update' AND !empty($zz_tab[$tab]['existing'][$rec])) {
				$path = zz_makepath($val['path'], $zz_tab, 'new', 'file', $tab, $rec);
				$old_path = zz_makepath($val['path'], $zz_tab, 'old', 'file', $tab, $rec);
				if (!empty($zz_tab[0]['folder']))
					foreach ($zz_tab[0]['folder'] as $folder) {
						// escape foldername, preg_match delimiters will
						// be replaced with \/
						$folder['old_e'] = str_replace('/', '\\/', $folder['old']);
						if (preg_match('/^'.$folder['old_e'].'/', $old_path))
							$old_path = preg_replace('/^('.$folder['old_e'].')/', $folder['new'], $old_path);
					}
				if ($path != $old_path) {
					// save paths: not necessary maybe, but in case ...
					$image['files']['update']['path'] = $path;
					$image['files']['update']['old_path'] = $old_path;
					$success = zz_upload_update($old_path, $path, $uploaded_file);
					if (!$success) return zz_return($zz_tab);
				}
			}

		// insert, update
			if ($uploaded_file) {
				$image['files']['destination'] = zz_makepath($val['path'], $zz_tab, 'new', 'file', $tab, $rec);
				if (!isset($image['source']) && !isset($image['source_file']) && empty($image['action'])) {
					$mode = 'move';
				} else {
					$mode = 'copy';
				}
				$success = zz_upload_insert($uploaded_file, $image['files']['destination'], $action, $mode);
				if (!$success) zz_return($zz_tab);
			} else {
				// ok, no thumbnail image, so in this case delete existing thumbnails
				// if there are any
				if (!empty($image['delete_thumbnail']) AND !empty($old_path)) {
					zz_unlink_cleanup($old_path); // delete old thumbnail
				}
			}

		// @todo: EXIF or ICPT write operations go here!
		}
	}
	if ($zz_conf['modules']['debug']) zz_debug('end');
	return $zz_tab;
}

/**
 * Deletes a file (backups old file if configuration is set accordingly)
 *
 * @param string $filename = name of file to be deleted
 * @param string $show_filename = part of filename to be shown to user
 		if this is not set, no error message will be shown to user (optional file)
 * @param string $action (optional) = record action, for backup only
 * @global array $zz_conf
 * @global array $zz_error
 * @return bool false: major error; true: file does not exist anymore
 */
function zz_upload_delete($filename, $show_filename = false, $action = 'delete') {
	global $zz_conf;
	global $zz_error;

	if (!file_exists($filename)) {
	// just a precaution for e. g. simultaneous access
		if ($show_filename) {
			$zz_error[] = array(
				'msg' => sprintf(zz_text('Could not delete %s, file did not exist.'), $show_filename),
				'level' => E_USER_NOTICE
			);
		}
		return true;
	}
	if (!is_file($filename)) {
		$zz_error[] = array(
			'msg_dev' => sprintf(zz_text('File %s exists, but is not a file.', $filename)),
			'level' => E_USER_ERROR
		);
		zz_error();
		return false;
	}

	if ($zz_conf['backup']) {
		$success = zz_rename($filename, zz_upload_path($zz_conf['backup_dir'], $action, $filename));
		if ($zz_error['error']) return false;
		zz_cleanup_dirs(dirname($filename));
	} else {
		$success = zz_unlink_cleanup($filename);
	}
	if (!$success) {
		$zz_error[] = array(
			'msg' => sprintf(zz_text('Could not delete %s.'), $filename),
			'level' => E_USER_NOTICE
		);
		return true;
	} 
	if ($zz_conf['modules']['debug']) {
		zz_debug('File deleted: %'.$filename.'%');
	}
	return true;
}

/**
 * Renames a file in case of an update
 *
 * @param string $source = old source filename
 * @param string $dest = old destination filename
 * @param string $uploaded_file = new upload file
 * @param string $action (optional) = record action, for backup only
 * @global array $zz_conf
 * @global array $zz_error
 * @return bool true: copy was succesful, false: an error occured
 */
function zz_upload_update($source, $dest, $uploaded_file, $action = 'update') {
	global $zz_conf;
	global $zz_error;

	zz_create_topfolders(dirname($dest));
	if ($zz_error['error']) return false;
	if (file_exists($dest) AND $zz_conf['backup'] AND (strtolower($source) != strtolower($dest))) { 
		// this case should not occur
		// attention: file_exists returns true even if there is a change in case
		zz_rename($dest, zz_upload_path($zz_conf['backup_dir'], $action, $dest));
		if ($zz_error['error']) return false;
	}
	if (!file_exists($source)) return true;
	if ($zz_conf['backup'] AND $uploaded_file) {
		// new image will be added later on for sure
		zz_rename($source, zz_upload_path($zz_conf['backup_dir'], $action, $dest));
		if ($zz_error['error']) return false;
	} else {
		// just filename will change
		zz_rename($source, $dest);
	}
	return true;
}

/**
 * Moves a file from it's temporary place to its destination. If there's
 * already a file, this will be backuped or deleted.
 *
 * @param string $source = source filename
 * @param string $dest = destination filename
 * @param string $action (optional) = record action, for backup only
 * @param string $mode (optional) = copy|move (default: copy)
 * @global array $zz_conf
 * @global array $zz_error
 * @return bool true: copy was succesful, false: an error occured
 */
function zz_upload_insert($source, $dest, $action = '-', $mode = 'copy') {
	global $zz_conf;
	global $zz_error;
	
	// check if destination exists, back it up or delete it
	if (file_exists($dest)) {
		if (!is_file($dest)) {
			$zz_error[] = array(
				'msg_dev' => sprintf(zz_text('Insert: %s exists, but is not a '
					.'file.'), '<code>'.$dest.'</code>'),
				'level' => E_USER_ERROR
			);
			zz_error();
			return false;
		}
		if ($zz_conf['backup']) {
			zz_rename($dest, zz_upload_path($zz_conf['backup_dir'], $action, $dest));
			if ($zz_error['error']) return false;
			zz_cleanup_dirs(dirname($dest));
		} else {
			zz_unlink_cleanup($dest);
		}
	}
	// create path if it does not exist or if cleanup removed it.
	zz_create_topfolders(dirname($dest));
	if ($zz_error['error']) return false;
	// copy file, this also works in older php versions between partitions.
	$success = copy($source, $dest);
	if (!$success) {
		if (!is_writeable(dirname($dest))) {
			$msg_dev = sprintf(zz_text('Insufficient rights. Directory %s is not writable.'), 
				'<code>'.dirname($dest).'</code>');
		} else { 
			$msg_dev = zz_text('Unknown error.').zz_text('Copying not successful.')
				.'<br>'.zz_text('from:').' '.$source
				.'<br>'.zz_text('to:').' '.$dest.'<br>';
		}
		$zz_error[] = array(
			'msg' => zz_text('File could not be saved. There is a problem with '
				.'the user rights. We are working on it.'),
			'msg_dev' => $msg_dev,
			'level' => E_USER_ERROR
		);
		return false;
	}

	if ($mode === 'move') { 
		// do this with images which have not been touched
		zz_unlink_cleanup($source);	
	}
	chmod($dest, 0644);
	if ($zz_conf['modules']['debug']) {
		zz_debug('file copied: %'.$source.'% to: %'.$dest.'%');
	}
	return true;
}

/**
 * Creates unique filename from backup dir, action and file path
 * 
 * called form zz_upload_action
 * @param string $dir backup directory
 * @param string $action sql action
 * @param string $path file path
 * @global array $zz_error
 * @return string unique filename ? path?
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_path($dir, $action, $path) {
	global $zz_error;
	$my_base = $dir.'/'.$action.'/';
	zz_create_topfolders($my_base);
	if ($zz_error['error']) return false;
	$i = 0;
	do  { 
		$my_path = $my_base.time().$i.'.'.basename($path);
		$i++;
	} while (file_exists($my_path));
	return $my_path;
}

/**
 * Remove unused files from upload process
 * 
 * called form zz_action
 * @param array $zz_tab table data
 * @param bool $validated (optional, true: validation was passed, false: not)
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_cleanup($zz_tab, $validated = true) {
	global $zz_conf;
	
	// files-ID = combination of script name and ID
	$id = $zz_conf['int']['secret_key'];
	if (!$validated) {
		// this will only work with SESSIONs
		if (empty($_SESSION)) $validated = false;
	} else {
		if (!empty($_SESSION['zz_files'][$id])) {
			unset($_SESSION['zz_files'][$id]);
		}
	}
	if (!$zz_tab[0]['upload_fields']) return false;
	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$tab = $uf['tab'];
		$rec = $uf['rec'];
		$no = $uf['f'];
		if (empty($zz_tab[$tab][$rec]['images'][$no]['all_temp'])) continue;
		foreach ($zz_tab[$tab][$rec]['images'][$no]['all_temp'] as $file) {
			if (file_exists($file) && is_file($file)) {
				if ($validated) {
				// delete file and empty parent folders
					zz_unlink_cleanup($file);
				} else {
					$_SESSION['zz_files'][$id][$tab][$rec]['file_upload'] = $zz_tab[$tab][$rec]['file_upload'];
					$_SESSION['zz_files'][$id][$tab][$rec]['images'] = $zz_tab[$tab][$rec]['images'];
					$_SESSION['zz_files'][$id][$tab][$rec][$no]['filenames'][] = $file;
				}
			}
		}
	}
	return true;
}


/*	----------------------------------------------	*
 *				IMAGE FUNCTIONS (zz_image...)		*
 *	----------------------------------------------	*/

/**
 * checks automatically which size from a given list suits best for thumbnail
 * generation
 *
 * will be called via 'auto_size'
 * @param array $image
 * @return array $image
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_image_auto_size($image) {
	//	basics
	// tolerance in px
	$tolerance = !empty($image['auto_size_tolerance']) ? $image['auto_size_tolerance'] : 15; 
	$width = $image['upload']['width'];
	$height = $image['upload']['height'];
	if (!$height) return $image;
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
		} else {
			// if == or in a range of $tolerance, ratio_tolerated will be 1
			$pair[0] = $pair[1];
		}
		$my['ratio_tolerated'] = $pair[0]/$pair[1];
		$pairs[$key] = $my;
		if (empty($smallest) OR $smallest['size'] > $my['size']) {
			$smallest['key'] = $key;
			$smallest['size'] = $my['size'];
		}
		$key++;
	}
	unset($pair);
	
	//	check which pairs will be acceptable (at least one dimension bigger than
	// given dimensions plus tolerance)
	foreach ($pairs as $pair)
		if (($pair['height'] - $tolerance) <= $height OR ($pair['width'] - $tolerance) <= $width)
			$acceptable_pairs[] = $pair;
	if (empty($acceptable_pairs)) { // Houston, we've got a problem
		// return field with smallest size
		$image['width'] = $pairs[$smallest['key']]['width'];
		$image['height'] = $pairs[$smallest['key']]['height'];
		return $image;
	}

	// check for best ratio
	foreach ($pairs as $key => $pair) {
		if (empty($best_pair) || zz_image_is_better_ratio($ratio, $best_pair['ratio'], $pair['ratio'])) {
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
	return $image;
}

/**
 * checks whether a ratio is better than the other
 *
 * @param double $ratio			given ratio
 * @param double $old_ratio		old ratio, which will be checked if its better
 * @param double $new_ratio		new ratio to compare with
 * @return bool true if ratio is better, false if ratio is not better
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_image_is_better_ratio($ratio, $old_ratio, $new_ratio) {
	if ($ratio > 1) {
		if ($old_ratio > $ratio AND $new_ratio < $old_ratio) return true; 
		// = ratio too big, small always better
		if ($old_ratio < $ratio AND $new_ratio <= $ratio AND $new_ratio > $old_ratio) return true;
		// = closer to ratio, better
	} elseif ($ratio == 1) {
		$distance_of_old = ($old_ratio >= 1) ? $old_ratio : 1/$old_ratio;
		$distance_of_new = ($new_ratio >= 1) ? $new_ratio : 1/$new_ratio;
		if ($distance_of_old > $distance_of_new) return true; // closer to 1
	} else { // smaller than 1
		if ($old_ratio < $ratio AND $new_ratio > $old_ratio) return true; 
		// = ratio too small, bigger always better
		if ($old_ratio > $ratio AND $new_ratio >= $ratio AND $new_ratio < $old_ratio) return true;
		// = closer to ratio, better
	}
	return false;
}

/**
 * reads default definitions from a textfile and writes them into an array
 *
 * @param string $filename Name of file
 * @param string $type (optional) 'Filetype' or 'IPTC'
 * @param string $optional
 * @global array $zz_error 
 * @return array $defaults
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo $mode = file, sql; read values from database table
 */
function zz_upload_get_typelist($filename, $type = 'Filetype', $optional = false) {
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

/**
 * extracts dirname from filename and checks whether directory is empty
 * removes this directory and upper directories if they are empty
 *
 * @param string $file filename
 * @return bool 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_unlink_cleanup($file) {
	$full_path = realpath($file);
	$dir = dirname($full_path);
	$success = unlink($full_path);
	
	zz_cleanup_dirs($dir);
		
	if ($success) return true;
	return false;
}

/**
 * removes empty directories hierarchically, except for system directories
 *
 * @param string $dir name of directory
 * @global array $zz_conf
 * @return bool true
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_cleanup_dirs($dir) {
	// first check if it's a directory that shall always be there
	global $zz_conf;
	$dir = realpath($dir);
	if (!$dir) return false;
	$indelible = array(realpath($zz_conf['backup_dir']), realpath($zz_conf['tmp_dir']),
		realpath($zz_conf['root']), '/tmp');
	if (in_array($dir, $indelible)) return false;

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

/**
 * Extracts EXIF thumbnail from image
 *
 * This function is independent of the graphics library used, therefore it's
 * directly part of the upload module
 * @param string $source
 * @param string $destination
 * @param string $dest_ext (optional, extension of destination file)
 * @param array $image (optional)
 * @return bool true if image was extracted, false if not
 */
function zz_image_exif_thumbnail($source, $destination, $dest_ext = false, $image = false) {
	global $zz_conf;
	// don't regard it as an error if no Exif thumbnail was found nor created
	$zz_conf['int']['no_image_action'] = true;
	if (!in_array($image['upload']['filetype'], $zz_conf['exif_supported'])) {
		return false;
	}
	$exif_thumb = exif_thumbnail($source);
	if (!$exif_thumb) return false;
	$imagehandle = fopen($destination, 'a');
	fwrite($imagehandle, $exif_thumb);	//write the thumbnail image
	return true;
}

/**
 * returns extension from any given filename
 *
 * @param string $filename
 * @return string $extension (part behind last dot or 'unknown')
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_upload_file_extension($filename) {
	if (strstr($filename, '.'))
		$extension = strtolower(substr($filename, strrpos($filename, '.')+1));
	else
		$extension = 'unknown';
	return $extension;
}

/**
 * renames a file with php rename, only if 'upload_copy_for_rename' is set
 * copies file and then deletes old file (in case there are problems renaming
 * files from one mount to another)
 *
 * @param string $oldname old file name
 * @param string $newname new file name
 * @param ressource $context
 * @global array $zz_conf -> bool 'upload_copy_for_rename'
 * @return bool true if rename was successful, false if not
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_rename($oldname, $newname, $context = false) {
	global $zz_conf;
	global $zz_error;
	if (!$newname) {
		$zz_error[] = array(
			'msg_dev' => zz_text('zz_rename(): No new filename given.'),
			'level' => E_USER_WARNING
		);
		return false;
	}
	if (!file_exists($oldname)) {
		$zz_error[] = array(
			'msg_dev' => sprintf(zz_text('zz_rename(): File %s does not exist.'), $oldname),
			'level' => E_USER_WARNING
		);
		return false;
	}
	if (!empty($zz_conf['upload_copy_for_rename'])) {
		$success = copy($oldname, $newname);
		if ($success) {
			$success = unlink($oldname);
			if ($success) return true;
		}
	} else {
		if ($context) {
			$success = rename($oldname, $newname, $context);
			if ($success) return $success;
		} else {
			$success = rename($oldname, $newname);
			if ($success) return $success;
		}
	}
	$zz_error[] = array(
		'msg_dev' => sprintf(zz_text('Copy/Delete for rename failed. Old filename: %s, new filename: %s'),
			$oldname, $newname),
		'level' => E_USER_NOTICE
	);
	return false;
}

/**
 * set errors for upload, backwards compatiblity for PHP 4.1 and earlier
 * will be used from zzform_multi() as well
 *
 * @param array $upload
 * @global array $zz_conf
 * @return array $upload
 */
function zz_upload_compat_error($upload) {
	global $zz_conf;
	if ($upload['tmp_name'] == 'none') {
		$upload['error'] = UPLOAD_ERR_NO_FILE; // no file
		$upload['type'] = false; // set to application/octet-stream
		$upload['name'] = false;
		$upload['tmp_name'] = false;
	} elseif ($zz_conf['upload_MAX_FILE_SIZE'] AND (isset($upload['size']))
		&& $upload['size'] > $zz_conf['upload_MAX_FILE_SIZE']) {
		$upload['error'] = UPLOAD_ERR_FORM_SIZE; // too big
		$upload['type'] = false; // set to application/octet-stream
		$upload['name'] = false;
		$upload['tmp_name'] = false;
	} else {
		$upload['error'] = UPLOAD_ERR_OK; // everything ok
	}
	return $upload;
}

/**
 * check if upload max file size is not bigger than ini-setting
 *
 * @global array $zz_conf;
 * @global array $zz_error;
 */
function zz_upload_check_max_file_size() {
	global $zz_conf;
	global $zz_error;
	
	if ($zz_conf['upload_MAX_FILE_SIZE'] > ZZ_UPLOAD_INI_MAXFILESIZE) {
		$zz_error[] = array(
			'msg_dev' => 'Value for upload_max_filesize from php.ini is '
				.'smaller than value which is set in the script. The '
				.'value from php.ini will be used. To upload bigger files'
				.', please adjust your configuration settings.',
			'level' => E_USER_NOTICE
		);
		$zz_conf['upload_MAX_FILE_SIZE'] = ZZ_UPLOAD_INI_MAXFILESIZE;
	}
}

/**
 * Adds logging capability to exec()-function call
 *
 * @param string $command
 * @param string $log_description will appear in logfile(s)
 * @param array $output (optional, exec() $output)
 * @param array $return_var (optional, exec() $return_var)
 * @global array $zz_conf
 * @return bool
 */
function zz_upload_exec($command, $log_description, &$output = array(), &$return_var = 0) {
	global $zz_conf;
	
	if ($zz_conf['upload_log']) {
		$time = microtime(true);
	}
	if ($zz_conf['modules']['debug']) {
		zz_debug('identify command', $command);
	}
	exec($command, $output, $return_var);
	if ($zz_conf['upload_log']) {
		$time = microtime(true) - $time;
		$user = $zz_conf['user'] ? $zz_conf['user'] : zz_text('No user');
		if (!$output) $out = '-';
		elseif (is_array($output) AND count($output) === 1) $out = reset($output);
		else $out = '[json] '.json_encode($output);
		$log = "[%s] zzform Upload: %s %s (Output: %s) {%s} [User: %s]\n";
		$log = sprintf($log, date('d-M-Y H:i:s'), $log_description, $command, $out, $time, $user);
		error_log($log, 3, $zz_conf['upload_log']);
	}
	return true;
}

?>