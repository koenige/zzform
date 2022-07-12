<?php 

/**
 * zzform
 * File upload
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 *	1. main functions (in order in which they are called)
 *
 *	zz_upload_get()				writes arrays upload_fields, images
 *								i. e. checks which fields offer uploads,
 *								collects and writes information about files
 *		zz_upload_get_fields()	checks which fields allow upload
 *		zz_upload_check_files()	checks files, puts information to 'image' array
 *			zz_upload_fileinfo()	read information (filesize, exif etc.)
 *			zz_upload_make_title()	converts filename to title
 *			zz_upload_make_name()	converts filename to better filename
 *			zz_upload_mimecheck()	checks whether supposed mimetype was already checked for
 *			zz_upload_filecheck()	gets filetype from list
 *			...
 *		zz_upload_check_recreate()
 *	zz_upload_prepare()			prepares files for upload (resize, rotate etc.)
 *		zz_upload_extension()	gets extension
 *		zz_upload_create_source()
 *	zz_upload_check()			validates file input (upload errors, requirements)
 *		not directly called but from zz_action() instead:
 *		zz_write_upload_fields()
 *		zz_val_get_from_upload()
 *	zz_upload_action()			writes/deletes files after successful sql insert/update
 *	zz_upload_cleanup()			cleanup after files have been moved or deleted
 *
 *	2. additional functions
 *
 *	zz_upload_path()			creates unique name for backup file
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
 *
 *	upload values from PHP form
 *	$zz_tab[0][0]['images'][n][0]['upload']
 *		['name']			local filename
 *		['type']			mimetype, as browser sends it
 *		['tmp_name']		temporary filename on server
 *		['error'] 			errorcode, 0 = no error
 *
 *	own upload values, read from image
 *	$zz_tab[0][0]['images'][n][0]['upload']
 *		['size']			filesize
 *		['width']			width in px
 *		['height']			height in px
 *		['exif']			exif data
 *		['filetype']		Filetype
 *		['ext']				file extension
 *		['mime']			MimeType
 *		['imagick_format']	ImageMagick_Format
 *		['imagick_mode']	ImageMagick_Mode
 *		['imagick_desc']	ImageMagick_Description
 *		['validated']		validated (yes = tested, no = rely on fileupload i. e. user)
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2006-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/*	----------------------------------------------	*
 *					VARIABLES						*
 *	----------------------------------------------	*/

/**
 * Default settings for upload module
 */
function zz_upload_config() {
	global $zz_conf;
	// don't call this twice. use variable because static does not work
	// with zzform_multi() 
	if (!empty($zz_conf['upload_calls'])) return;

	$default['imagemagick_paths'] = [
		'/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/phpbin',
		'/opt/local/bin'
	];
	$default['icc_profiles'] = [];
	$default['upload_tools']['fileinfo'] = false;
	$default['upload_tools']['fileinfo_whereis'] = 'file';
	$default['upload_tools']['exiftool'] = false;
	$default['upload_tools']['exiftool_whereis'] = '/usr/local/bin/exiftool';
	$default['upload_tools']['identify'] = true; // might be turned off for performance reasons while handling raw data
	$default['upload_tools']['ghostscript'] = false; // whether we can use gs library
	$default['upload_tools']['pdfinfo'] = false;
	$default['upload_log'] = false;

	if (!defined('ZZ_UPLOAD_INI_MAXFILESIZE')) {
		$max_filesize = ini_get('upload_max_filesize');
		define('ZZ_UPLOAD_INI_MAXFILESIZE', wrap_return_bytes($max_filesize));
	}
	$default['upload_MAX_FILE_SIZE']	= ZZ_UPLOAD_INI_MAXFILESIZE;

	// mimetypes, hardcoded in php

	$default['image_types'] = [
		1 =>  ['mime' => 'image/gif', 'ext' => 'gif'],				// 1	IMAGETYPE_GIF
		2 =>  ['mime' => 'image/jpeg', 'ext' => 'jpeg'],			// 2	IMAGETYPE_JPEG
		3 =>  ['mime' => 'image/png', 'ext' => 'png'],				// 3	IMAGETYPE_PNG
		4 =>  ['mime' => 'application/x-shockwave-flash', 'ext' => 'swf'],	// 4	IMAGETYPE_SWF
		5 =>  ['mime' => 'image/psd', 'ext' => 'psd'],				// 5	IMAGETYPE_PSD
		6 =>  ['mime' => 'image/bmp', 'ext' => 'bmp'],				// 6	IMAGETYPE_BMP
		7 =>  ['mime' => 'image/tiff', 'ext' => 'tiff'],			// 7	IMAGETYPE_TIFF_II (intel byte order)
		8 =>  ['mime' => 'image/tiff', 'ext' => 'tiff'],			// 8	IMAGETYPE_TIFF_MM (motorola byte order)
		9 =>  ['mime' => 'application/octet-stream', 'ext' => 'jpc'],		// 9	IMAGETYPE_JPC	>= PHP 4.3.2
		10 => ['mime' => 'image/jp2', 'ext' => 'jp2'],				// 10	IMAGETYPE_JP2	>= PHP 4.3.2
		11 => ['mime' => 'application/octet-stream', 'ext' => 'jpf'],		// 11	IMAGETYPE_JPX	>= PHP 4.3.2
		12 => ['mime' => 'application/octet-stream', 'ext' => 'jb2'],		// 12	IMAGETYPE_JB2	>= PHP 4.3.2
		13 => ['mime' => 'application/x-shockwave-flash', 'ext' => 'swc'],	// 13	IMAGETYPE_SWC	>= PHP 4.3.0
		14 => ['mime' => 'image/iff', 'ext' => 'aiff'],			// 14	IMAGETYPE_IFF
		15 => ['mime' => 'image/vnd.wap.wbmp', 'ext' => 'wbmp'],	// 15	IMAGETYPE_WBMP	>= PHP 4.3.2
		16 => ['mime' => 'image/xbm', 'ext' => 'xbm']				// 16	IMAGETYPE_XBM	>= PHP 4.3.2
	];
	foreach (array_keys($default['image_types']) as $key)
		$default['image_types'][$key]['filetype'] = $default['image_types'][$key]['ext'];

	$default['file_types'] = wrap_filetypes();
	if (zz_error_exit()) return false;

	// unwanted mimetypes and their replacements
	$default['mime_types_rewritten'] = [
		'image/pjpeg' => 'image/jpeg', 	// Internet Explorer knows progressive JPEG instead of JPEG
		'image/x-png' => 'image/png',	// Internet Explorer
		'application/octet_stream' => 'application/octet-stream'
	]; 

	// extensions for images that the browser can display natively
	$default['webimages_by_extension'] = ['jpg', 'jpeg', 'gif', 'png'];

	// generate thumbnails in a background process?
	$default['upload_background_thumbnails'] = false;

	$default['upload_no_thumbnails'] = [];
	$default['upload_multipage_images'] = [];
	$default['exif_supported'] = [];
	$default['exiftool_supported'] = [];
	$default['upload_destination_filetype'] = [];
	foreach ($default['file_types'] as $filetype => $def) {
		if (empty($def['thumbnail'])) $default['upload_no_thumbnails'][] = $filetype;
		if (!empty($def['multipage'])) $default['upload_multipage_images'][] = $filetype;
		if (!empty($def['exif_supported'])) $default['exif_supported'][] = $filetype;
		if (!empty($def['exiftool_supported'])) $default['exiftool_supported'][] = $filetype;
		if (!empty($def['destination_filetype'])) {
			foreach ($def['extension'] as $extension) {
				$default['upload_destination_filetype'][$extension] = $def['destination_filetype'];
			}
		}
		if (!empty($def['destination_filetype_transparency'])) {
			foreach ($def['extension'] as $extension) {
				$default['upload_destination_filetype_transparency'][$extension] = $def['destination_filetype_transparency'];
			}
		}
	}
	// don't take first frame from mp4 movie, might be black
	$default['upload_multipage_which']['m4v'] = 5;

	$default['upload_filetype_map']['tif'] = 'tiff';
	$default['upload_filetype_map']['jpe'] = 'jpeg';
	$default['upload_filetype_map']['jpg'] = 'jpeg';

	// XML documents will be recognized as SVG (which is XML, too)
	$default['upload_remap_type_if_extension']['gpx'] = 'svg';
	// AI documents can be real PDF documents
	$default['upload_remap_type_if_extension']['ai'] = 'pdf';
	// EPS documents are PS documents, they are different
	// this is a workaround, 'file' will make a differences
	$default['upload_remap_type_if_extension']['eps'] = 'ps';
	
	zz_write_conf($default);
	$zz_conf['upload_calls'] = 1;
}

/*	----------------------------------------------	*
 *					MAIN FUNCTIONS					*
 *	----------------------------------------------	*/

/**
 * direct creation of a single thumbnail
 *
 * @param array $ops
 * @param array $zz_tab
 * @return array $ops
 */
function zz_upload_thumbnail($ops, $zz_tab) {
	global $zz_conf;

	if (empty($zz_tab[0][0]['existing'])) {
		$ops['error'][] = sprintf('ID %s not found', $zz_conf['int']['id']['value']);
		$zz_conf['int']['http_status'] = 404;
		return $ops;
	}
	$ops['thumb_field'] = explode('-', $_GET['field']);
	if (count($ops['thumb_field']) !== 2) {
		$zz_conf['int']['http_status'] = 404;
		return $ops;
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		$zz_conf['int']['http_status'] = 404;
		return $ops;
	}

	$zz_tab = zz_upload_get($zz_tab);
	$zz_tab = zz_upload_prepare_tn($zz_tab, $ops);
	$zz_tab = zz_upload_action($zz_tab);
	zz_upload_cleanup($zz_tab);

	if (!empty($zz_tab[0][0]['file_upload'])) {
		$ops['id'] = $zz_conf['int']['id']['value'];
		$ops['result'] = 'thumbnail created';
	} elseif (!empty($zz_tab[0][0]['no_file_upload'])) {
		$ops['id'] = $zz_conf['int']['id']['value'];
		$ops['result'] = 'thumbnail not created';
	} else {
		$ops['error'][] = sprintf('Thumbnail information for field %d (No. %d) not found',
			$ops['thumb_field'][0], $ops['thumb_field'][1]
		);
		$zz_conf['int']['http_status'] = 404;
	}
	return $ops;
}

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
 */
function zz_upload_get($zz_tab) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if ($zz_conf['graphics_library'])
		include_once __DIR__.'/image-'.$zz_conf['graphics_library'].'.inc.php';

	// allow shortcuts for file_types
	$zz_conf['file_types'] = wrap_filetypes_normalize($zz_conf['file_types']);
	$zz_conf['int']['upload_cleanup_files'] = [];

	// create array upload_fields in $zz_tab[0] for easy access to upload fields
	$zz_tab[0]['upload_fields'] = zz_upload_get_fields($zz_tab);

	//	read information of files, put into 'images'-array
	if ($zz_tab[0][0]['action'] !== 'delete')
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
 */
function zz_upload_get_fields($zz_tab) {
	$upload_fields = [];
	foreach (array_keys($zz_tab) as $tab) {
		foreach (array_keys($zz_tab[$tab]) as $rec) {
			if (!is_int($tab) OR !is_int($rec)) continue;
			foreach ($zz_tab[$tab][$rec]['fields'] as $f => $field) {
				if ($field['type'] != 'upload_image') continue;
				$key = !empty($zz_tab[$tab]['no']) ? $zz_tab[$tab]['no'] : $f;
				$upload_fields[] = [
					'tab' => $tab, 
					'rec' => $rec,
					'f' => $f,
					'field_index' => $key
				];
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
 * @return array multidimensional information about images
 *		bool $zz_tab[tab][rec]['file_upload']
 *		array $zz_tab[tab][rec]['images']
 */
function zz_upload_check_files($zz_tab) {
	global $zz_conf;
	
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	$session = zz_session_read('files');
	if (!empty($session['upload_cleanup_files'])) {
		$zz_conf['int']['upload_cleanup_files'] = array_merge(
			$zz_conf['int']['upload_cleanup_files'], $session['upload_cleanup_files']
		);
	}
	$zz_tab[0]['file_upload'] = false;

	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$tab = $uf['tab'];
		$rec = $uf['rec'];
		$no = $uf['f'];
		$my_rec = &$zz_tab[$tab][$rec];
		$images = [];
		$field = $my_rec['fields'][$no];

		// get unique fieldname for subtables and file uploads as set in editform.inc
		// $tab means subtable, since main table has $tab = 0
		if ($tab) {
			$field['f_field_name'] = $zz_tab[$tab]['table_name'].'['.$rec.']['.$field['field_name'].']';
		} elseif (isset($field['field_name'])) {
			$field['f_field_name'] = $field['field_name'];
		} else {
			$field['f_field_name'] = '';
		}
		$field['f_field_name'] = zz_make_id_fieldname($field['f_field_name']);

		$myfiles = [];
		if (!empty($_FILES[$field['f_field_name']])) {
			$myfiles = $_FILES[$field['f_field_name']];
		}
		foreach (array_keys($field['image']) as $img) {
			$images[$no][$img] = $field['image'][$img];
			$images[$no][$img]['optional_image'] = isset($field['optional_image'])
				? $field['optional_image'] : false;
			$images[$no][$img]['upload_max_filesize'] = $field['upload_max_filesize'];

			// initialize convert_options
			if (!isset($images[$no][$img]['convert_options'])) {
				$images[$no][$img]['convert_options'] = '';
			}

			// check if thumbnail image might have to be recreated
			if (!empty($images[$no][$img]['recreate_on_change'])) {
				$images[$no][$img]['recreate'] = zz_upload_check_recreate($images[$no][$img], $zz_tab);
			} else {
				$images[$no][$img]['recreate'] = false;
			}

			if (empty($images[$no][$img]['field_name'])) {
				// don't do the rest if field_name is not set
				// just read values from session
				if (!empty($images[$no]['read_from_session'])) {
					$images[$no][$img] = $session[$tab][$rec]['images'][$no][$img];
				}
				continue;
			}
			$field_name = $images[$no][$img]['field_name'];
			
			if (empty($myfiles['name'][$field_name])
				AND !empty($session[$tab][$rec]['images'][$no][$img])) {
				$images[$no]['title'] = $session[$tab][$rec]['images'][$no]['title'];
				$images[$no]['filename'] = $session[$tab][$rec]['images'][$no]['filename'];
				$images[$no][$img] = $session[$tab][$rec]['images'][$no][$img];
				$images[$no]['read_from_session'] = true;
				$my_rec['file_upload'] = $session[$tab][$rec]['file_upload'];
				if ($my_rec['file_upload']) {
					$zz_tab[0]['file_upload'] = true;
				}
				continue;
			}

			// we need at least a tmp_name from somewhere pointing to a file
			// might be '' (file upload with no file)
			if (!isset($myfiles['tmp_name'][$field_name])) {
				$myfiles['tmp_name'][$field_name] = '';
				$myfiles['name'][$field_name] = '';
				$myfiles['type'][$field_name] = '';
				$myfiles['size'][$field_name] = 0;
				$myfiles['error'][$field_name] = UPLOAD_ERR_NO_FILE;
			}

			if (!isset($myfiles['name'][$field_name])) {
				$myfiles['name'][$field_name] = 'unknown';
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
			
			if (!empty($myfiles['error'][$field_name])) {
				$images[$no][$img]['upload']['error'] = $myfiles['error'][$field_name];
			} else {
				// zzform_multi()
				$images[$no][$img]['upload']['error'] = UPLOAD_ERR_OK;
			}
			if (!isset($myfiles['size'][$field_name])) {
				$myfiles['size'][$field_name] = filesize($images[$no][$img]['upload']['tmp_name']);
			}
			if ($myfiles['size'][$field_name] < 3 
				AND empty($images[$no][$img]['upload']['error'])) { 
				// don't overwrite different error messages, filesize = 0 also might be the case
				// if file which was uploaded is too big
				// file is too small or 0, might occur while incorrect refresh of browser
				$images[$no][$img]['upload']['error'] = UPLOAD_ERR_NO_FILE;
				if ($images[$no][$img]['upload']['name'] AND $images[$no][$img]['upload']['type']) {
					$images[$no][$img]['upload']['msg']
						= 'The file %s is empty. If you are uploading from a Mac, please check if the data is not only available in the so-called “resource fork” of the file.';
					$images[$no][$img]['upload']['msg_args']
						= [wrap_html_escape($images[$no][$img]['upload']['name'])];
				}
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
			// file not too big if max smaller than ini_size?
			if ($field['upload_max_filesize'] < $images[$no][$img]['upload']['size']) {
				$images[$no][$img]['upload']['error'] = UPLOAD_ERR_FORM_SIZE;
				if (file_exists($images[$no][$img]['upload']['tmp_name']))
					zz_unlink_cleanup($images[$no][$img]['upload']['tmp_name']);
				$images[$no][$img]['upload']['tmp_name'] = false;
			}
			
			switch ($images[$no][$img]['upload']['error']) {
				case UPLOAD_ERR_NO_FILE:
					continue 2;
				case UPLOAD_ERR_PARTIAL:
				case UPLOAD_ERR_FORM_SIZE:
				case UPLOAD_ERR_INI_SIZE:
					$my_rec['file_upload_error'] = true;
					$zz_tab[0][0]['fields'][$uf['field_index']]['check_validation'] = false;
					continue 2;
				case UPLOAD_ERR_OK:
					break;
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
					$images[$no][$img]['input_filetypes'] = [];
				}
			}
			// input_filetypes must be an array
			if (!is_array($images[$no][$img]['input_filetypes'])) {
				$images[$no][$img]['input_filetypes'] = [$images[$no][$img]['input_filetypes']];
			}

			//	check if filetype is allowed
			if (!in_array($images[$no][$img]['upload']['filetype'], $images[$no][$img]['input_filetypes'])) {
				$filetype = $images[$no][$img]['upload']['filetype'];
				if ($filetype === 'unknown') // give more information
					$filetype = zz_text('unknown').' ('.zz_htmltag_escape($images[$no][$img]['upload']['type']).')';
				$images[$no][$img]['unsupported_filetype'] = zz_text('Error: ')
					.zz_text('Unsupported filetype:').' '
					.strtoupper($filetype)
					.'<br class="nonewline_in_mail">'
					.zz_upload_supported_filetypes($images[$no][$img]['input_filetypes']);
				$my_rec['file_upload'] = false;
				$my_rec['file_upload_error'] = true;
				// mark with class 'error'
				$zz_tab[0][0]['fields'][$uf['field_index']]['check_validation'] = false;
			} else {
				$zz_tab[0]['file_upload'] = $my_rec['file_upload'] = true;
			}
		}
		if (!empty($my_rec['images'])) {
			$my_rec['images'] += $images;
		} else {
			$my_rec['images'] = $images;
		}
	}
	if ($zz_conf['modules']['debug']) zz_debug('end');
	return $zz_tab;
}

/**
 * downloads a file from a remote location as source for file upload
 * only works with zzform_multi(), otherwise filename cannot be set to URL
 *
 * @param string $filename
 * @global array $zz_setting 'tmp_dir'
 * @return string temp filename on local server
 * @todo add further registered streams if necessary
 * @todo preserve timestamp (parse http headers?)
 */
function zz_upload_remote_file($filename) {
	global $zz_setting;
	if (substr($filename, 0, 7) !== 'http://'
		AND substr($filename, 0, 8) !== 'https://'
		AND substr($filename, 0, 6) !== 'ftp://'
	) {
		return $filename;
	}
	$tmp = $zz_setting['tmp_dir'];
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
 *	1c. @todo finfo_file, see: http://www.php.net/manual/en/function.finfo-file.php
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
	$file['upload_ext'] = $extension;

	// check filetype by several means
	if ($zz_conf['modules']['debug']) zz_debug('file', json_encode($file));
	$functions = [
		'upload_getimagesize',		// getimagesize(), PHP
		'upload_exif_imagetype',	// exif_imagetype(), PHP > 4.3.0
		'imagick_identify',			// ImageMagick identify command
		'upload_unix_file'			// Unix file command
	];
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
	// change extension to default extension of filetype
	if (isset($zz_conf['file_types'][$file['filetype']])) {
		$file['ext'] = $zz_conf['file_types'][$file['filetype']]['extension'][0];
	}

	if (!empty($file['warnings'])) {
		foreach ($file['warnings'] as $function => $warnings) {
			zz_error_log([
				'msg_dev' => "%s returns with a warning:\n\n%s",
				'msg_dev_args' => [$function, implode("\n", $warnings)],
				'log_post_data' => false,
				'level' => E_USER_NOTICE
			]);
		}
	}
	// @todo allow further file testing here, e. g. for PDF, DXF
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
				$file['ext'] = $filetype['extension'][0];
				$file['mime'] = $filetype['mime'][0];
				$file['filetype'] = $filetype['filetype'];
			} else {
				$file['ext'] = 'unknown-'.$extension;
				if (isset($file['type']))
					$file['mime'] = 'unknown: '.$file['type']; // show user upload type
				$file['filetype'] = 'unknown';
			}
		}
	}
	// some filetypes are identical to others, so we have to check the extension
	if (array_key_exists($extension, $zz_conf['upload_remap_type_if_extension'])) {
		if (!is_array($zz_conf['upload_remap_type_if_extension'][$extension])) {
			$zz_conf['upload_remap_type_if_extension'][$extension]
				= [$zz_conf['upload_remap_type_if_extension'][$extension]];
		}
		foreach ($zz_conf['upload_remap_type_if_extension'][$extension] as $ftype) {
			if ($file['filetype'] !== $ftype) continue;
			$file['filetype'] = $extension;
			$file['mime'] = $zz_conf['file_types'][$file['filetype']]['mime'][0];
			break;
		}
	}
	if ($zz_conf['modules']['debug']) zz_debug('finish', json_encode($file));

	// save unknown files for debugging
	if ($file['filetype'] === 'unknown') {
		$return = [
			'error' => true,
			'error_msg' => 'There was an attempt to upload the following file but the filetype is unknown.'
		];
		zz_upload_error_with_file($filename, $file, $return);
	}

	// read metadata
	if (in_array($file['filetype'], $zz_conf['exif_supported'])
		OR in_array($file['filetype'], $zz_conf['exiftool_supported'])) {
		// you will need enough memory size to handle this if you are uploading
		// pictures with layers from photoshop, because the original image is
		// saved as metadata. exif_read_data() cannot read only the array keys
		// or you could exclude key ImageSourceData where the original image
		// is kept
		if ($zz_conf['upload_tools']['exiftool']) {
			$file['exiftool'] = zz_upload_exiftool_read($filename);
			$file['exif'] = [];
		} elseif (function_exists('exif_read_data') AND in_array($file['filetype'], $zz_conf['exif_supported'])) {
			$file['exif'] = exif_read_data($filename);
		} else {
			$file['exif'] = [];
		}
	}
	if ($file['filetype'] === 'pdf' AND $zz_conf['upload_tools']['pdfinfo']) {
		$file['pdfinfo'] = zz_upload_pdfinfo($filename);
	}
	
	// @todo further functions, e. g. zz_pdf_read_data if filetype == pdf ...
	// @todo or read AutoCAD Version from DXF, DWG, ...
	// @todo or read IPCT data.

	return zz_return($file);
}

/**
 * read information about pdf with pdfinfo
 *
 * @param string $filename
 * @return array
 */
function zz_upload_pdfinfo($filename) {
	exec(sprintf('pdfinfo "%s"', $filename), $raw);
	$data = [];
	foreach ($raw as $line) {
		$separator = strpos($line, ':');
		$key = substr($line, 0, $separator);
		$value  = trim(substr($line, $separator + 1));
		$data[$key] = $value;
	}
	return $data;
}

/**
 * read EXIF metadata with ExifTool
 * better than exif_read_data() because this function will crash if it cannot
 * read the EXIF data
 *
 * @param string $filename
 * @global array $zz_conf
 * @return array
 */
function zz_upload_exiftool_read($filename) {
	global $zz_conf;
	// @todo use similar mechanism for finding ExifTool path as in imagemagick
	$cmd = $zz_conf['upload_tools']['exiftool_whereis'].' -b -j -struct -c "%%d %%d %%.8f" -l -lang %s -g1 "%s"';
	$cmd = sprintf($cmd, wrap_get_setting('lang'), $filename);
	exec($cmd, $file_meta);
	if (!$file_meta) return [];
	$file_meta = json_decode(implode('', $file_meta), true);
	$file_meta = $file_meta[0];
	return $file_meta;
}

/**
 * write EXIF metadata with ExifTool
 *
 * @param string $filename
 * @global array $my_rec ($zz_tab[$tab][$rec])
 * @return void
 * @todo under development
 */
function zz_upload_exiftool_write($filename, $my_rec) {
	global $zz_conf;
	return;
	$cmd = '%s -key="value" "%s"';
	$cmd = sprintf($cmd, $zz_conf['upload_tools']['exiftool_whereis'], $filename);
	exec($cmd);
}

/**
 * converts filename to human readable string
 * 
 * @param string $filename filename
 * @return string title
 */
function zz_upload_make_title($filename) {
	// remove file extension up to 4 letters
	$filename = preg_replace('/\.[a-zA-Z0-9]*$/', '', $filename);
	$filename = str_replace('_', ' ', $filename); // make output more readable
	$filename = str_replace('.', ' ', $filename); // make output more readable
	$filename = ucfirst($filename);
	$filename = wrap_normalize($filename);
	return $filename;
}

/**
 * converts filename to wanted filename
 * 
 * @param string $filename filename
 * @return string filename
 */
function zz_upload_make_name($filename) {
	// remove file extension up to 4 letters
	$filename = preg_replace('/\.[a-zA-Z0-9]*$/', '', $filename);
	$filename = wrap_filename($filename);
	return $filename;
}

/**
 * checks whether a given combination of mimetype and extension exists
 * 
 * @param string $mimetype mime type
 * @param string $extension file extension
 * @return boolean
 */
function zz_upload_mimecheck($mimetype, $extension) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	foreach ($zz_conf['image_types'] as $imagetype)
		if ($imagetype['mime'] === $mimetype AND $imagetype['ext'] === $extension)
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
	global $zz_conf;
	if ($extension === $type) return true;
	if (empty($zz_conf['upload_filetype_map'][$type])) return false;
	if ($extension === $zz_conf['upload_filetype_map'][$type]) return true;
	return false;
}

/**
 * return normalized extension
 *
 * @param string $extension
 * @global array $zz_conf
 * @return string
 */
function zz_upload_extension_normalize($extension) {
	global $zz_conf;
	if (empty($zz_conf['upload_filetype_map'][$extension])) return $extension;
	return $zz_conf['upload_filetype_map'][$extension];
}

/**
 * checks from a list of filetypes if mimetype and extension match with
 * a filetype from this list
 * 
 * @param string $mimetype mime type
 * @param string $extension file extension
 * @global array $zz_conf 'file_types'
 * @return array $type or false
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
	foreach ($zz_conf['file_types'] as $filetype) {
		if (in_array($extension, $filetype['extension'])
			AND in_array($mimetype, $filetype['mime'])) {
			$type1 = $filetype;	
		} elseif (in_array($extension, $filetype['extension'])) {
			if ($type2) $type2unique = false;
			else $type2 = $filetype;
		} elseif (in_array($mimetype, $filetype['mime'])) {
			if ($type3) $type3unique = false;
			else $type3 = $filetype;
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
	$file['recheck'] = ['tiff']; // RAW images might be recognized as TIFF
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
	$file['recheck'] = ['tiff']; // RAW images might be recognized as TIFF
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
	list($output, $return_var) = zz_upload_exec($fileinfo.' --brief "'.$filename.'"', 'Fileinfo');
	if (!$output) return $file;
	if ($zz_conf['modules']['debug']) {
		zz_debug('file brief', json_encode($output));
	}
	// output might contain characters in a different character encoding
	$file['filetype_file'] = wrap_convert_string($output[0]);
	// remove uninteresting key=value pairs
	$file['filetype_file'] = explode(', ', $file['filetype_file']);
	foreach ($file['filetype_file'] as $index => $values) {
		if (preg_match('~^[A-Za-z0-9]+=.*~', $values)) unset($file['filetype_file'][$index]);
	}
	$file['filetype_file'] = implode(', ', $file['filetype_file']);

	// get mime type
	// attention, -I changed to -i in file, don't use shorthand here
	list($output, $return_var) = zz_upload_exec($fileinfo.' --mime --brief "'.$filename.'"', 'Fileinfo with MIME');
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
	$file['filetype_file_full'] = $file['filetype_file'];
	if ($pos = strpos($file['filetype_file'], ': [')) {
		$file['filetype_file'] = substr($file['filetype_file'], 0, $pos);
	}
	if (substr($file['filetype_file'], 0, 20) == 'DWG AutoDesk AutoCAD') {
		$imagetype = 'dwg';
		$file['validated'] = true;
	} elseif ($file['filetype_file'] == 'AutoCad (release 14)') {
		$imagetype = 'dwg';
		$file['validated'] = true;
	}
	// @todo check this, these are not only DOCs but also MPPs.
//	} elseif ($file['filetype_file'] == 'Microsoft Office Document') {
//		$imagetype = 'doc';
//		$file['validated'] = true;
//	} elseif ($file['filetype_file'] == 'data') {
	// ...
	
	// check if mime type from file() matches $file['filetype']
	$possible_filetypes = [];
	foreach ($zz_conf['file_types'] as $filetype => $data) {
		if (empty($data['mime'])) continue;
		if (!in_array($file['mime'], $data['mime'])) continue;
		$possible_filetypes[] = $filetype;
	}
	
	if (!in_array($file['filetype'], $possible_filetypes)) {
		if ($file['filetype'] !== 'unknown') {
			zz_error_log([
				'msg_dev' => 'File type %s does not match MIME type %s as found by file() for %s',
				'msg_dev_args' => [$file['filetype'], $file['mime'], $file['name']],
				'log_post_data' => false
			]);
		}
		foreach ($possible_filetypes as $index => $filetype) {
			if (!in_array($file['upload_ext'], $zz_conf['file_types'][$filetype]['extension']))
				unset($possible_filetypes[$index]);
		}
		if (count($possible_filetypes) === 1) {
			$imagetype = reset($possible_filetypes);
		} else {
			$file['validated'] = false;
		}
	}
	
	if (!empty($file['validated']) AND $imagetype) {
		$file['ext'] = $zz_conf['file_types'][$imagetype]['extension'][0];
		$file['mime'] = $zz_conf['file_types'][$imagetype]['mime'][0];
		$file['filetype'] = $zz_conf['file_types'][$imagetype]['filetype'];
	}
	return $file;
}

/**
 * saves files with an unknown filetype or with an error while converting
 * in the backup directory for further inspection, sends mail to admin
 *
 * @param string $filename
 * @param array $file
 * @param array $return
 *		bool 'error'
 *		string 'error_msg'
 *		mixed 'output'
 * 		string 'command'
 * 		int 'exit_status'
 * @param string $type type of error: 'unknown' = unknown file; 'convert' = 
 *		error while converting a file
 * @global array $zz_conf
 *		'debug_upload', 'backup', 'backup_dir'
 * @return bool false: nothing was found, true: unknown file was found
 */
function zz_upload_error_with_file($filename, $file, $return = []) {
	global $zz_conf;
	static $copied_files;
	if (empty($zz_conf['debug_upload'])) return false;
	if (empty($copied_files)) $copied_files = [];

	// save file
	// don’t do that when creating thumbnails in background: master file is
	// already saved anyways
	$error_filename = false;
	if ($zz_conf['backup'] AND !in_array($filename, $copied_files)
		AND empty($file['create_in_background'])) {
		// don't return here in case of error - 
		// it's not so important to break the whole process
		$my_error = zz_error_exit();
		$error_filename = zz_upload_path('error', $filename);
		if (!zz_error_exit())
			copy($filename, $error_filename);
		$copied_files[] = $filename; // just copy a file once
		zz_error_exit($my_error);
	}
	
	if (empty($return['msg_dev_args'])) {
		$return['msg_dev_args'] = [];
	}
	if (empty($return['error_msg'])) {
		$return['error_msg'] = 'Action `%s` returned no file.';
		$return['msg_dev_args'][] = $file['action'];
	} elseif (!empty($file['action'])) { // e. g. if filetype unknown
		$return['error_msg'] .= "\r\nAction: %s";
		$return['msg_dev_args'][] = $file['action'];
	}
	$return['error_msg'] .= "\r\n";
	if (!empty($return['command'])) {
		$return['error_msg'] .= "\r\nCommand: %s";
		$return['msg_dev_args'][] = $return['command'];
	}
	if (!empty($return['output'])) {
		$return['error_msg'] .= "\r\nOutput: %s";
		$return['msg_dev_args'][] = json_encode($return['output']);
	}
	if (!empty($return['exit_status'])) {
		$return['error_msg'] .= "\r\nExit status: %s";
		$return['msg_dev_args'][] = $return['exit_status'];
	}
	if (!empty($file['upload'])) { // e. g. if filetype unknown
		$err_upload = $file['upload'];
		unset($err_upload['exif']); // too much information for log
		unset($err_upload['exiftool']); // too much information for log
		$return['error_msg'] .= "\r\n%s";
		$return['msg_dev_args'][] = var_export($err_upload, true);
	}
	if ($error_filename) {
		$return['error_msg'] .= "\r\nThe source file was temporarily saved under: %s";
		$return['msg_dev_args'][] = $error_filename;
	}

	zz_error_log([
		'msg_dev' => $return['error_msg'],
		'msg_dev_args' => $return['msg_dev_args'],
		'log_post_data' => false,
		'level' => E_USER_NOTICE
	]);
	zz_error();
	return true;
}

/**
 * checks if thumbnail files have to be recreated because a field value has
 * changed (e. g. thumb_filetype_id)
 *
 * @param array $image
 * @param array $zz_tab
 * @return bool
 */
function zz_upload_check_recreate($image, $zz_tab) {
	if (empty($zz_tab[0][0]['existing'])) return false;
	if (empty($zz_tab[0][0]['POST'])) return false;

	$fields = [];
	foreach ($image['recreate_on_change'] as $no) {
		if (!isset($zz_tab[0][0]['fields'][$no]['field_name'])) continue;
		$fields[] = $zz_tab[0][0]['fields'][$no]['field_name'];
	}
	if (!$fields) return false;
	$recreate = false;
	foreach ($fields as $field) {
		if (!array_key_exists($field, $zz_tab[0][0]['POST'])) continue;
		if (!array_key_exists($field, $zz_tab[0][0]['existing'])) continue;
		if ($zz_tab[0][0]['POST'][$field] != $zz_tab[0][0]['existing'][$field]) {
			$recreate = true;
		}
	}
	return $recreate;
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
 * @return array $zz_tab changed
 */
function zz_upload_prepare($zz_tab) {
	// do only something if there are upload_fields
	if (empty($zz_tab[0]['upload_fields'])) return $zz_tab;

	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	
	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		$tab = $uf['tab'];
		$rec = $uf['rec'];
		$no = $uf['f'];
		$my_rec = &$zz_tab[$tab][$rec];
		if (!empty($my_rec['images'][$no]['read_from_session'])) {
			// mark if background images should be created
			foreach (array_keys($my_rec['fields'][$no]['image']) as $img) {
				if (empty($my_rec['images'][$no][$img])) continue;
				if ($zz_conf['upload_background_thumbnails'] AND empty($my_rec['images'][$no][$img]['create_in_background'])) {
					zz_upload_background($no.'-'.$img);
				}
			}
			continue;
		}

		foreach (array_keys($my_rec['fields'][$no]['image']) as $img) {
			if (empty($my_rec['images'][$no][$img])) continue;
			$prepared_img = zz_upload_prepare_file($zz_tab, $tab, $rec, $no, $img);
			if ($prepared_img) {
				$my_rec['images'][$no][$img] = $prepared_img;
				if (!empty($prepared_img['no_file_upload'])) {
					$my_rec['no_file_upload'] = true;
				}
				if (!empty($prepared_img['file_upload'])) {
					$my_rec['file_upload'] = true;
				}
			}
		}
	}
	return zz_return($zz_tab);
}

/**
 * prepares single file for thumbnail generation
 *
 * @param array $zz_tab
 * @param array $ops
 *		array 'thumb_field' with number and image
 * @return array $zz_tab
 */
function zz_upload_prepare_tn($zz_tab, $ops) {
	// do only something if there are upload_fields
	if (empty($zz_tab[0]['upload_fields'])) return $zz_tab;

	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$no = $ops['thumb_field'][0];
	$img = $ops['thumb_field'][1];

	foreach ($zz_tab[0]['upload_fields'] as $uf) {
		if ($uf['f'] !== intval($no)) continue;
		$zz_tab[$uf['tab']][$uf['rec']]['images'][$no][$img]['create_in_background'] = true;
		$prepared_img = zz_upload_prepare_file($zz_tab, $uf['tab'], $uf['rec'], $no, $img);
		if ($prepared_img) {
			$zz_tab[$uf['tab']][$uf['rec']]['images'][$no][$img] = $prepared_img;
			if (!empty($prepared_img['no_file_upload'])) {
				$zz_tab[$uf['tab']][$uf['rec']]['no_file_upload'] = true;
			}
			if (!empty($prepared_img['file_upload'])) {
				$zz_tab[$uf['tab']][$uf['rec']]['file_upload'] = true;
			}
		}
	}
	return zz_return($zz_tab);
}

/**
 * prepare files
 *
 * @param array $zz_tab
 * @param int $tab
 * @param int $rec
 * @param int $no
 * @param int $img
 * @return array $image = $zz_tab[tab][rec]['images'][no][img]
 */
function zz_upload_prepare_file($zz_tab, $tab, $rec, $no, $img) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) {
		zz_debug('preparing ['.$tab.']['.$rec.'] - '.$img);
	}

	// reference on image data
	$my_rec = &$zz_tab[$tab][$rec];
	$image = $my_rec['images'][$no][$img];

	$abort = false;
	if (!empty($image['unsupported_filetype'])) $abort = true;
//	the following line does not work because if image is missing on update
//	there's no retrying creating the missing thumbnails
//	if (!empty($image['upload']['error']) AND empty($image['source_file'])) $abort = true;
	if ($abort) {
		// get rid of the file and go on
		if (empty($image['upload']['do_not_delete'])) {
			zz_unlink_cleanup($image['upload']['tmp_name']);
		}
		return [];
	}

	$image = zz_upload_merge_options($image, $zz_tab[$tab], $rec);
	if (!empty($image['on_request'])) {
		// on request = do not create file, just update it if it was created on request
		$source_filename = zz_makepath($image['path'], $zz_tab, 'old', 'file', $tab, $rec);
		if (!file_exists($source_filename)) return $image;
	}
	if (!empty($image['ignore'])) return $image;

	$use_uploaded_file = true;
	$src_image = false;
	if (!empty($image['source_field'])) {
		!empty($image['source']) OR $image['source'] = 0;
		$src_image = zz_upload_get_source_field($image, $zz_tab);
		// nothing adequate found, so we can go on with source_file instead!
		if (!$src_image) unset($image['source']); 
	}

	// check which source file shall be used
	if (!empty($image['create_in_background'])) {
		if (!$src_image) // might come from zz_upload_get_source_field()
			$src_image = $my_rec['images'][$no][$image['source']];
		if (!empty($src_image['unsupported_filetype'])) return [];
		list($image, $source_filename) = zz_upload_create_source($image, $src_image['path'], $zz_tab, $tab, $rec);
		$image['upload']['do_not_delete'] = true; // don't delete source!
		$use_uploaded_file = false;

	} elseif (isset($image['source'])) {
		// must be isset, because 'source' might be 0
		// it's a thumbnail or some other derivate from the original file
		if ($zz_conf['modules']['debug']) zz_debug('source: '.$image['source']);

		if (!$src_image) // might come from zz_upload_get_source_field()
			$src_image = $my_rec['images'][$no][$image['source']];
		if (!empty($src_image['unsupported_filetype'])) return [];
//		@todo this also does not work, see above
//		if (!empty($src_image['upload']['error'])) return [];
		if (!empty($image['use_modified_source'])) {
			// get filename from modified source, false if there was an error
			$source_filename = isset($src_image['files']) 
				? $src_image['files']['tmp_file'] : false;
			if (!$source_filename AND $zz_conf['modules']['debug']) 
				zz_debug('use_modified_source: no source filename!');
			// get some variables from source image as well
			$image['upload'] = $src_image['upload']; 
		} else {
			$source_filename = $src_image['upload']['tmp_name'];
			if (!$source_filename AND $image['recreate']) {
				list($image, $source_filename) = zz_upload_create_source($image, $src_image['path'], $zz_tab);
			} elseif (!$source_filename AND $zz_tab[$tab][$rec]['action'] === 'update') {
				// no new file was uploaded, nothing to do
				// but: check if all thumbnails already exist (due to errors or
				// change in thumbnail definition!)
				$thumb_filename = zz_makepath($image['path'], $zz_tab, 'old', 'file', $tab, $rec);
				if (!file_exists($thumb_filename)) {
					list($image, $source_filename) = zz_upload_create_source($image, $src_image['path'], $zz_tab);
				} else {
					// else: exists, everything okay
					$image['upload'] = $src_image['upload']; 
				}
			} else {
				// get some variables from source image as well
				$image['upload'] = $src_image['upload']; 
			}
		}
		// check if it's not a form that allows upload of different filetypes at once
		// cross check input filetypes
		if (!empty($image['input_filetypes']) AND !empty($src_image['upload']['filetype'])) {
			// continue if this file shall not be touched.
			if (!in_array($src_image['upload']['filetype'], $image['input_filetypes'])) return [];
		}
		$use_uploaded_file = false;

	} elseif (!empty($image['source_file'])) {
		// use existing file in database, on error use nothing
		$result = zz_upload_prepare_source_file($image, $my_rec, $zz_tab, $tab, $rec);
		if ($result) list($image, $source_filename) = $result;
		else $source_filename = false;
		$use_uploaded_file = false;
	}

	if ($use_uploaded_file) {
		// it's the original file we upload to the server
		$source_filename = $image['upload']['tmp_name'];
		// for later cleanup of leftover tmp files
		if (empty($image['upload']['do_not_delete']) AND $source_filename) {
			$zz_conf['int']['upload_cleanup_files'][] = $source_filename;
		}
	}

	if ($zz_conf['modules']['debug']) zz_debug('source_filename: '.$source_filename);
	if (!$source_filename) return [];
	if ($source_filename === 'none') return [];

	// get e. g. width and height from a list of widths and heights which fit best
	$image = zz_upload_auto_image($image);
	if (!$image) return [];

	$tn = zz_upload_create_thumbnails($source_filename, $image, $my_rec, $no, $img);
	if ($tn === -2) {
		// there should be an extension, but none was selected,
		// so do not create new and delete existing thumbnails
		$image['delete_thumbnail'] = true;

	} elseif ($tn === -1) {
		// an error occured
		$image['no_file_upload'] = true;
		$image['files']['tmp_file'] = false; // do not upload anything
		// @todo mark existing image for deletion if there is one!							
		$image['delete_thumbnail'] = true;

	} elseif ($tn) {
		// a thumbnail was created
		$image['modified'] = $tn;
		$image['file_upload'] = true;
		$image['files']['tmp_file'] = $image['modified']['tmp_name'];
		$zz_conf['int']['upload_cleanup_files'][] = $image['modified']['tmp_name'];

	} elseif (!isset($image['source'])) {
		// save original file, no thumbnail was created
		$image['files']['tmp_file'] = file_exists($source_filename) ? $source_filename : '';

	} else {
		// thumbnail could have been created, but was not, probably
		// because it is impossible to create one (due to filetype) or there was
		// some error
		$image['files']['tmp_file'] = false;
	}

	return $image;
}

/**
 * get original filename for creating a thumbnail file from an existing
 * file in database
 *
 * @param array $image
 * @param array $path
 * @param array $zz_tab
 * @param int $tab (optional)
 * @param int $rec (optional)
 * @return array
 */
function zz_upload_create_source($image, $path, $zz_tab, $tab = 0, $rec = 0) {
	$source_filename = zz_makepath($path, $zz_tab, 'old', 'file', $tab, $rec);
	if (!file_exists($source_filename)) {
		$image['upload'] = [];
		if (empty($image['optional_image'])) {
			zz_error_log([
				'msg_dev' => 'Error: Source file %s does not exist.',
				'msg_dev_args' => [$source_filename],
				'log_post_data' => false
			]);
		}
	} else {
		$image['upload']['name'] = basename($source_filename);
		$image['upload']['tmp_name'] = $source_filename; // same because it's no upload
		$image['upload']['error'] = 0;
		$image['upload'] = zz_upload_fileinfo($image['upload']);
	}
	return [$image, $source_filename];
}

/**
 * get an existing file from the database as a source for the thumbnail
 *
 * @param array $image
 * @param array $my_rec
 * @param array $zz_tab
 * @param int $tab
 * @param int $rec
 * @return mixed; bool false if nothing was found,
 *		array array $image, string $filename on success
 */
function zz_upload_prepare_source_file($image, $my_rec, $zz_tab, $tab, $rec) {
	global $zz_conf;
	$source_filename = false;

	// check if field is there, convert string in ID, if it's a checkselect
	$found = false;
	foreach ($my_rec['fields'] as $index => $field) {
		if ($field['field_name'] !== $image['source_file']) continue;
		if (!$my_rec['POST'][$image['source_file']]) continue;
		$found = true;
		if ($field['type'] !== 'select') break;
		$my_rec = zz_check_select($my_rec, $index);
	}
	if (!$found) return false;

	$sql = sprintf($image['source_path_sql'], $my_rec['POST'][$image['source_file']]);
	$old_sql = $sql;
	if (!empty($image['update_from_source_field_name']) AND !empty($image['update_from_source_value'])) {
		$where = [];
		foreach ($image['update_from_source_field_name'] as $index => $field_name) {
			if (!array_key_exists($image['update_from_source_value'][$index], $my_rec['existing'])) continue;
			$field_value = $my_rec['existing'][$image['update_from_source_value'][$index]];
			if ($field_value) {
				$where[] = sprintf('(%s != "%s" OR ISNULL(%s))', $field_name, $field_value, $field_name);
			} else {
				$where[] = sprintf('NOT ISNULL(%s)', $field_name);
			}
		}
		if ($where) {
			$sql = wrap_edit_sql($sql, 'WHERE', implode(' OR ', $where));
		}
	}
	$old_record = zz_db_fetch($sql);
	if (!$old_record) {
		// does file exist?
		$thumb_filename = zz_makepath($image['path'], $zz_tab, 'old', 'file', $tab, $rec);
		if (file_exists($thumb_filename)) return false;
		$old_record = zz_db_fetch($old_sql);
	}
	if (!$old_record) return false;

	$source_tab[$tab][$rec]['existing'] = $old_record;
	list($image, $source_filename) = zz_upload_create_source($image, $image['source_path'], $source_tab, $tab, $rec);
	return [$image, $source_filename];
}

/**
 * file operations (thumbnails etc.) for images
 *
 * @param string $filename
 * @param array $image
 * @param array $my_rec
 * @param int $no
 * @param int $img
 * @global array $zz_conf
 * @return mixed $modified (false: does not apply; -1: error; 
 *		-2: there should be an extension but none was selected, array: success)
 */
function zz_upload_create_thumbnails($filename, $image, $my_rec, $no, $img) {
	global $zz_conf;
	global $zz_setting;
	
	if (empty($image['action'])) return false;

	if (!file_exists($filename)) {
		if (empty($image['optional_image'])) {
			zz_error_log([
				'msg_dev' => 'Error: Source file %s does not exist.',
				'msg_dev_args' => [$filename],
				'log_post_data' => false
			]);
		}
		return false;
	}

	zz_upload_config();
	if (in_array($image['upload']['filetype'], $zz_conf['upload_no_thumbnails'])) {
		return false;
	}

	$dest_extension = zz_upload_extension($image['path'], $my_rec);
	if ($dest_extension === -1) {
		$dest_extension = false;
		if (!empty($image['no_action_unless_thumb_extension'])) return -2;
	}

	if ($zz_conf['upload_background_thumbnails'] AND empty($image['create_in_background'])) {
		zz_upload_background($no.'-'.$img);
		return false;
	}
	
	// set destination filetype
	if (!$dest_extension) {
		$dest_extension = strtolower($image['upload']['ext']);
		// map files to extensions, e. g. TIFF to PNG
		if (!empty($image['upload']['transparency']) AND !empty($zz_conf['upload_destination_filetype_transparency'][$dest_extension]))
			$dest_extension = $zz_conf['upload_destination_filetype_transparency'][$dest_extension];
		elseif (!empty($zz_conf['upload_destination_filetype'][$dest_extension]))
			$dest_extension = $zz_conf['upload_destination_filetype'][$dest_extension];
	}

	if (!empty($zz_conf['file_types'][$image['upload']['filetype']]['exiftool_thumbnail'])
		AND !empty($zz_conf['upload_tools']['exiftool'])) {
		$source_filename = zz_image_exiftool($filename, $image);
		// @todo allow other fields as source as well
		$meta = zz_upload_exiftool_read($source_filename);
		if (!empty($meta['File']['MIMEType']['val'])) {
			foreach ($zz_conf['file_types'] as $type => $values) {
				foreach ($values['mime'] as $mime) {
					if ($mime === $meta['File']['MIMEType']['val'])
						$dest_extension = reset($values['extension']);
				}
			}
		}
	} else {
		$source_filename = $filename;
	}

	// create temporary file, so that original file remains the same 
	// for further actions
	$tmp_filename = tempnam($zz_setting['tmp_dir'], 'UPLOAD_');

	$action = 'zz_image_'.$image['action'];
	$return = $action($source_filename, $tmp_filename, $dest_extension, $image);
	if (!file_exists($tmp_filename)) {
		zz_error_log([
			'msg_dev' => 'Error: File %s does not exist. Temporary Directory: %s',
			'msg_dev_args' => [$tmp_filename, $zz_setting['tmp_dir']],
			'log_post_data' => false
		]);
		return false;
	}
	if (filesize($tmp_filename) > 3) {
		$modified = [];
		$modified['tmp_name'] = $tmp_filename;
		zz_upload_exiftool_write($tmp_filename, $my_rec);
		$modified = zz_upload_fileinfo($modified, $dest_extension);
		// @todo ['modified']['name'] ?? necessary? so far, it's not.
	}  else {
		// image action did not work out the way it should have.
		$modified = -1;
		if (is_array($return) AND !empty($return['error'])) {
			zz_upload_error_with_file($filename, $image, $return);
		}
		zz_unlink_cleanup($tmp_filename);
	}
	zz_error();
	return $modified;
}

/**
 * Merges settings depending on 'options' field to current image settings
 *
 * @param array $image
 * @param array $my_tab ($zz_tab[$tab])
 * @param int $rec
 * @return array $image
 */
function zz_upload_merge_options($image, $my_tab, $rec = 0) {
	if (empty($image['options'])) return $image;
	// to make it easier, allow input without array construct as well
	if (!is_array($image['options'])) 
		$image['options'] = [$image['options']]; 
	if (isset($image['options_sql']) AND !is_array($image['options_sql'])) 
		$image['options_sql'] = [$image['options_sql']]; 
	// go through all options
	foreach ($image['options'] as $index => $no) {
		// check if we have the corresponding field, if not, simply ignore it!
		if (empty($my_tab[$rec]['fields'][$no])) continue;
		// field_name of field where options reside
		$field_name = $my_tab[$rec]['fields'][$no]['field_name']; 
		// this is the selected option
		if ($my_tab[$rec]['fields'][$no]['type'] === 'select') {
			// @todo do this in action module beforehands
			$my_tab[$rec] = zz_check_select($my_tab[$rec], $no);
		}
		$option_value = $my_tab[$rec]['POST'][$field_name];
		if (!empty($image['options_sql'][$index]) AND $option_value) {
			// get options from database
			$sql = sprintf($image['options_sql'][$index], wrap_db_escape($option_value));
			$option_record = zz_db_fetch($sql, '', 'single value');
			if ($option_record) {
				parse_str($option_record, $options[$option_value]);
			} else {
				$options[$option_value] = [];
			}
		} elseif (!empty($image['options_key'][$index])) {
			// use value of field as option value
			$options[$option_value] = [
				$image['options_key'][$index] => $option_value
			];
		} elseif (isset($my_tab[$rec]['fields'][$no]['options'])) {
			// get options from field
			$options = $my_tab[$rec]['fields'][$no]['options'];
		} elseif ($my_tab[$rec]['fields'][$no]['type'] === 'parameter') {
			// some parameter field
			parse_str($my_tab[$rec]['POST'][$my_tab[$rec]['fields'][$no]['field_name']], $options[$option_value]);
		} else {
			zz_error_log([
				'msg_dev' => 'No options for field %s were found.',
				'msg_dev_args' => [$my_tab[$rec]['fields'][$no]['field_name']]
			]);
			$options[$option_value] = false;
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
	$source_field = [];
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
		zz_error_log([
			'msg_dev' => 'Configuration error: function <code>%s()</code> for image upload does not exist.',
			'msg_dev_args' => [$autofunc],
			'log_post_data' => false,
			'level' => E_USER_ERROR
		]);
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
 */
function zz_upload_extension($path, &$my_rec) {
	// @todo implement mode!
	$path_value = '';
	foreach ($path as $key => $value) {
		if ($key === 'root') continue;
		if (substr($key, 0, 3) === 'web') continue;
		$path_value = $value;
		$path_key = $key;
		// just use last field, overwrite until last
		// unless it's an extension
		if ($key === 'extension') {
			// definite field
			break;
		}
	}
	if (!$path_value) return false;

	if (substr($path_key, 0, 6) === 'string') {
		if (strstr($path_value, '.'))
			return substr($path_value, strrpos($path_value, '.') + 1);
		else
			return $path_value;
	} elseif ($path_key === 'extension' OR substr($path_key, 0, 5) === 'field') {
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
			}
		}
		if (!empty($extension)) return $extension;
	
		// no extension could be found,
		// probably due to extension from field which has not been filled yet
		// does not matter, that means that filetype for destination
		// file remains the same.
		return -1;		
	}
	zz_error_log([
		'msg_dev' => 'Error. Could not determine file ending',
		'log_post_data' => false,
		'level' => E_USER_ERROR
	]);
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
		$images[$img]['error'] = [];
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
					.sprintf(zz_text('Maximum allowed filesize is %s.'),
						wrap_bytes($images[$img]['upload_max_filesize'])
					); // Max allowed
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

//	check if minimal image size is reached: min_width, min_height
		$width_height = ['width', 'height'];
		foreach ($width_height as $which)
			if (!empty($images[$img]['min_'.$which]) 
				&& $images[$img]['min_'.$which] > $images[$img]['upload'][$which])
				$images[$img]['error'][] = zz_text('Error: ')
					.sprintf(zz_text('Minimum '.$which
					.' %s was not reached.'), '('.$images[$img]['min_'.$which].'px)')
					.' ('.$images[$img]['upload'][$which].'px)';

//	check if maximal image size has not been exceeded: max_width, max_height
		$width_height = ['width', 'height'];
		foreach ($width_height as $which)
			if (!empty($images[$img]['max_'.$which])
				&& $images[$img]['max_'.$which] < $images[$img]['upload'][$which])
				$images[$img]['error'][] = zz_text('Error: ')
					.sprintf(zz_text('Maximum '.$which
					.' %s has been exceeded.'), '('.$images[$img]['max_'.$which].'px)')
					.' ('.$images[$img]['upload'][$which].'px)';

//	check if maximal number of pages is not exceeded
		if (!empty($images[$img]['max_pages']) AND $images[$img]['upload']['filetype'] === 'pdf') {
			if (!empty($images[$img]['upload']['pdfinfo']['Pages'])) {
				if ($images[$img]['upload']['pdfinfo']['Pages'] > $images[$img]['max_pages']) {
					$images[$img]['error'][] = zz_text('Error: ')
						.sprintf(zz_text('The PDF consists of %d pages. Only %d pages are allowed.')
							, $images[$img]['upload']['pdfinfo']['Pages']
							, $images[$img]['max_pages']
					);
				}
			} else {
				zz_error_log([
					'msg_dev' => '`max_pages` can only be used with PDF filetypes and `pdfinfo` as upload tool.'
				]);
			}
		}

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
 * @return string
 */
function zz_write_upload_fields($zz_tab, $f, $tab = 0, $rec = 0) {
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
		preg_match('~(\d+)\[([\d\*]+)\]\[(\d+)\]~', $field['upload_field'], $nos);
		// check if definition is correct
		if (count($nos) !== 4) {
			zz_error_log([
				'msg_dev' => 'Error in $zz definition for upload_field: [%d]',
				'msg_dev_args' => [$f],
				'log_post_data' => false,
				'level' => E_USER_NOTICE
			]);
		} elseif ($nos[2] === '*') {
			$numeric_keys = [];
			foreach (array_keys($zz_tab[$nos[1]]) as $no) {
				if (!is_numeric($no)) continue;
				if (empty($zz_tab[$nos[1]][$no]['file_upload'])) continue;
				$numeric_keys[] = $no;
			}
			if (!$numeric_keys) return $posted;
			$images = [];
			foreach ($numeric_keys as $no) {
				$images = zz_array_merge($images, $zz_tab[$nos[1]][$no]['images'][$nos[3]]);
			}
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

	$possible_upload_fields = [
		'date', 'time', 'text', 'memo', 'hidden', 'number', 'select'
	];
	if (!in_array($field['type'], $possible_upload_fields)) 
		return $post;
	// apart from hidden, set only values if no values have been set so far
	if ($field['type'] !== 'hidden' AND !empty($post))
		return $post;

	$myval = false;
	$possible_values = $field['upload_value'];
	if (!is_array($possible_values)) $possible_values = [$possible_values];
	// empty values, e. g. GPS bearing = 0/0, ExifTool GPSImgDirectionRef = 'Unknown ()'
	// ExifTool undef
	$empty_values = ['0/0', 'Unknown ()', 'undef'];
	
	foreach ($possible_values AS $v) {
		switch ($v) {
		case 'increment_on_change':
			// @todo think about incrementing only if new file is different
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
						$arrays = [$myval_upload, $myval_altern];
						$subkeys = [];
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
			if (in_array($myval, $empty_values)) $myval = false;
			// we don't need whitespace (DateTime field may be set to "    ..."
			if (!is_array($myval)) $myval = trim($myval);
			if ($myval === ':  :     :  :') $myval = ''; // empty DateTime
			if (!empty($field['upload_func'])) {
				$myval = $field['upload_func']($myval);
				if (is_array($myval)) $myval = $myval['value'];
			} elseif (is_array($myval)) {
				$myval = false;
			}
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
			if (substr_count($myval, '/') === 1) {
				// count: dates might also be written 2004/12/31
				$vals = explode('/', $myval);
				if (is_numeric($vals[0]) AND is_numeric($vals[1])) {
					$myval = array_shift($vals);
					foreach ($vals as $val) {
						$myval /= $val;
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
	return $post;
}

/** 
 * Deletes files when specifically requested (e. g. in multiple upload forms)
 * 
 * called from within function zz_upload_action
 * @param array $zz_tab complete table data
 * @global array $zz_conf
 *		modules[debug], backup, backup_dir
 * @return array $zz_tab with changed values
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
				elseif (!empty($val['ignore'])) $show_filename = false;
				elseif (!empty($my_rec['fields'][$no]['optional_image'])) $show_filename = false;
				elseif (!empty($val['optional_image'])) $show_filename = false;
				elseif (!empty($val['on_request'])) $show_filename = false;
				if ($show_filename)
					$show_filename = zz_makepath($val['path'], $zz_tab, 'old', 'local', $tab, $rec);
				// delete file
				if (str_ends_with($filename, '.') AND !empty($val['no_action_unless_thumb_extension'])) {
					continue; // there is no file
				}
				$success = zz_upload_delete($filename, $show_filename);
				if (!$success) return zz_return($zz_tab);
				continue; // deleted, so don't care about the rest of the code
			}

		//	check if some file was uploaded
			$uploaded_file = !empty($image['files']['tmp_file'])
				? $image['files']['tmp_file'] : '';

		//	update, only if we have an old record (might sometimes not be the case!)
			$old_path = ''; // initialize here, will be used later with delete_thumbnail
			if ($action === 'update' AND !empty($my_rec['existing'])) {
				$path = zz_makepath($val['path'], $zz_tab, 'new', 'file', $tab, $rec);
				$old_path = zz_makepath($val['path'], $zz_tab, 'old', 'file', $tab, $rec);
				if ($zz_tab[0]['folder']) {
					foreach ($zz_tab[0]['folder'] as $folder) {
						// escape foldername, preg_match delimiters will
						// be replaced with \/
						$folder['old_e'] = str_replace('/', '\\/', $folder['old']);
						if (preg_match('/^'.$folder['old_e'].'/', $old_path))
							$old_path = preg_replace('/^('.$folder['old_e'].')/', $folder['new'], $old_path);
					}
				}
				if ($path != $old_path AND empty($image['delete_thumbnail'])) {
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

		// @todo EXIF or IPTC write operations go here!
		}
	}

	// background thumbnails will be triggered now
	zz_upload_background($zz_conf['int']['id']['value'], 'create');

	if ($zz_conf['modules']['debug']) zz_debug('end');
	return $zz_tab;
}

/**
 * create thumbnails in background
 *
 * @param string $number
 * @param string $action ('set' or 'create')
 */
function zz_upload_background($number, $action = 'set') {
	global $zz_conf;
	global $zz_setting;
	static $fields;
	if (!is_array($fields)) $fields = [];
	
	if ($action === 'set') {
		if (in_array($number, $fields)) return;
		// no thumbnail for main image @todo check when this is called
		if (substr($number, -2) === '-0') return;
		$fields[] = $number;
		return;
	}
	if (!$fields) return;
	foreach ($fields as $index => $field) {
		$url = sprintf('%s?thumbs=%d&field=%s',
			$zz_conf['int']['url']['full'], $number, $field
		);
		if (in_array($zz_conf['int']['access'], ['add_only', 'edit_only', 'add_then_edit'])
			AND empty($zz_conf['int']['where_with_unique_id'])) {
			$url .= sprintf('&zzhash=%s', zz_secret_key($number));
		}
		$headers[] = 'X-Request-WWW-Authentication: 1';
		$headers[] = 'X-Timeout-Ignore: 1';
		$method = 'POST';
		$data['thumbnails'] = 1;
		$pwd = sprintf('%s:%s', $zz_conf['user'], wrap_password_token($zz_conf['user']));
	
		require_once $zz_setting['core'].'/syndication.inc.php';
		$result = wrap_syndication_retrieve_via_http($url, $headers, $method, $data, $pwd);
		unset($fields[$index]);
	}
	return;
}

/**
 * Deletes a file (backups old file if configuration is set accordingly)
 *
 * @param string $filename = name of file to be deleted
 * @param string $show_filename = part of filename to be shown to user
 		if this is not set, no error message will be shown to user (optional file)
 * @param string $action (optional) = record action, for backup only
 * @global array $zz_conf
 * @return bool false: major error; true: file does not exist anymore
 */
function zz_upload_delete($filename, $show_filename = false, $action = 'delete') {
	global $zz_conf;

	if (!file_exists($filename)) {
	// just a precaution for e. g. simultaneous access
		if ($show_filename) {
			zz_error_log([
				'msg' => 'Could not delete %s, file did not exist.',
				'msg_args' => [$show_filename],
				'log_post_data' => false,
				'level' => E_USER_NOTICE
			]);
		}
		return true;
	}
	if (!is_file($filename)) {
		zz_error_log([
			'msg_dev' => 'File %s exists, but is not a file.',
			'msg_dev_args' => $filename,
			'log_post_data' => false,
			'level' => E_USER_ERROR
		]);
		zz_error();
		return false;
	}

	if ($zz_conf['backup']) {
		$success = zz_rename($filename, zz_upload_path($action, $filename));
		if (zz_error_exit()) return false;
		zz_cleanup_dirs(dirname($filename));
	} else {
		$success = zz_unlink_cleanup($filename);
	}
	if (!$success) {
		zz_error_log([
			'msg' => 'Could not delete %s.',
			'msg_args' => [$filename],
			'log_post_data' => false,
			'level' => E_USER_NOTICE
		]);
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
 * @return bool true: copy was succesful, false: an error occured
 */
function zz_upload_update($source, $dest, $uploaded_file, $action = 'update') {
	global $zz_conf;

	zz_create_topfolders(dirname($dest));
	if (zz_error_exit()) return false;
	if (file_exists($dest) AND $zz_conf['backup'] AND (strtolower($source) != strtolower($dest))) { 
		// this case should not occur
		// attention: file_exists returns true even if there is a change in case
		zz_rename($dest, zz_upload_path($action, $dest));
		if (zz_error_exit()) return false;
	}
	if (!file_exists($source)) return true;
	if ($zz_conf['backup'] AND $uploaded_file) {
		// new image will be added later on for sure
		zz_rename($source, zz_upload_path($action, $dest));
		if (zz_error_exit()) return false;
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
 * @return bool true: copy was succesful, false: an error occured
 */
function zz_upload_insert($source, $dest, $action = '-', $mode = 'copy') {
	global $zz_conf;
	
	// check if destination exists, back it up or delete it
	if (file_exists($dest)) {
		if (!is_file($dest)) {
			zz_error_log([
				'msg_dev' => 'Insert: `%s` exists, but is not a file.',
				'msg_dev_args' => [$dest],
				'level' => E_USER_ERROR
			]);
			zz_error();
			return false;
		}
		if ($zz_conf['backup']) {
			zz_rename($dest, zz_upload_path($action, $dest));
			if (zz_error_exit()) return false;
			zz_cleanup_dirs(dirname($dest));
		} else {
			zz_unlink_cleanup($dest);
		}
	}
	// create path if it does not exist or if cleanup removed it.
	zz_create_topfolders(dirname($dest));
	if (zz_error_exit()) return false;
	$success = zz_rename($source, $dest);
	if (!$success) {
		if (!is_writeable(dirname($dest))) {
			$msg_dev = 'Insufficient rights. Directory `%s` is not writable.';
			$msg_dev_args[] = dirname($dest);
		} else { 
			$msg_dev = 'Unknown error. Copying not successful. <br>From: %s <br>To: %s<br>';
			$msg_dev_args = [$source, $dest];
		}
		zz_error_log([
			'msg' => 'File could not be saved. There is a problem with '
				.'the user rights. We are working on it.',
			'msg_dev' => $msg_dev,
			'msg_dev_args' => $msg_dev_args,
			'log_post_data' => false,
			'level' => E_USER_ERROR
		]);
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
 * @param string $action sql action
 * @param string $path file path
 * @return string unique filename
 */
function zz_upload_path($action, $path) {
	global $zz_conf;

	$my_base = $zz_conf['backup_dir'].'/'.$action.'/';
	zz_create_topfolders($my_base);
	if (zz_error_exit()) return false;
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
 */
function zz_upload_cleanup($zz_tab, $validated = true) {
	global $zz_conf;

	// valid request = destroy session and delete files
	if ($validated) {
		foreach ($zz_conf['int']['upload_cleanup_files'] as $file) {
			zz_unlink_cleanup($file);
		}
		return true;
	}

	if (!$zz_tab[0]['upload_fields']) return false;

	// unfinished request: put files into session, do not delete
	zz_session_write('files', $zz_tab);
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
 *		added: int 'width', int 'height'
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
 * Create cropped image, clipping center
 *
 */
function zz_image_crop_center($source, $dest, $dest_ext, $image) {
	return zz_image_crop($source, $dest, $dest_ext, $image, 'center');
}

/**
 * Create cropped image, clipping from top
 *
 */
function zz_image_crop_top($source, $dest, $dest_ext, $image) {
	return zz_image_crop($source, $dest, $dest_ext, $image, 'top');
}

/**
 * Create cropped image, clipping from right
 *
 */
function zz_image_crop_right($source, $dest, $dest_ext, $image) {
	return zz_image_crop($source, $dest, $dest_ext, $image, 'right');
}

/**
 * Create cropped image, clipping from bottom
 *
 */
function zz_image_crop_bottom($source, $dest, $dest_ext, $image) {
	return zz_image_crop($source, $dest, $dest_ext, $image, 'bottom');
}

/**
 * Create cropped image, clipping from left
 *
 */
function zz_image_crop_left($source, $dest, $dest_ext, $image) {
	return zz_image_crop($source, $dest, $dest_ext, $image, 'left');
}

/**
 * Create cropped image, custom clipping
 *
 */
function zz_image_crop_custom($source, $dest, $dest_ext, $image) {
	return zz_image_crop($source, $dest, $dest_ext, $image, 'custom');
}

/**
 * extracts dirname from filename and checks whether directory is empty
 * removes this directory and upper directories if they are empty
 *
 * @param string $file filename
 * @return bool 
 */
function zz_unlink_cleanup($file) {
	if (!$file) return false;
	$full_path = realpath($file);
	if (!$full_path) return true;
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
 * @param array $indelible additional indelible dirs
 * @global array $zz_conf
 * @return bool
 */
function zz_cleanup_dirs($dir, $indelible = []) {
	// first check if it's a directory that shall always be there
	global $zz_conf;
	global $zz_setting;
	$dir = realpath($dir);
	if (!$dir) return false;
	$indelible[] = realpath($zz_conf['backup_dir']);
	$indelible[] = $zz_setting['tmp_dir'];
	$indelible[] = realpath($zz_conf['root']);
	$indelible[] = '/tmp';
	if (in_array($dir, $indelible)) return false;

	if (!is_dir($dir)) return false;
	$dir_handle = opendir($dir);
	if (!$dir_handle) return false;

	$ignores = ['.', '..'];
	$delete_if_empty = ['.DS_Store', 'Thumbs.db'];
	$to_delete = [];
	$i = 0;
	// check if directory is empty
	while ($filename = readdir($dir_handle)) {
		if (in_array($filename, $ignores)) continue;
		if (in_array($filename, $delete_if_empty)) {
			$to_delete[] = $dir.'/'.$filename;
			continue;
		}
		$i++;
	}
	closedir($dir_handle);
	if ($i !== 0) return false;
	foreach ($to_delete as $delete_filename) {
		unlink($delete_filename);
	}
	$success = rmdir($dir);
	if (!$success) return false;

	// walk through dirs recursively
	$upper_dir = dirname($dir);
	zz_cleanup_dirs($upper_dir);
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
 * @return bool true if image was extracted, array with error message if not
 */
function zz_image_exif_thumbnail($source, $destination, $dest_ext = false, $image = false) {
	global $zz_conf;
	if (!in_array($image['upload']['filetype'], $zz_conf['exif_supported'])) {
		// this filetype does not support EXIF thumbnails
		return false;
	}
	if (!array_key_exists('THUMBNAIL', $image['upload']['exif'])
		AND empty($image['upload']['exiftool']['Composite']['PreviewImage'])
		AND empty($image['upload']['exiftool']['Composite']['JpgFromRaw'])) {
		// don't regard it as an error if no EXIF thumbnail was found
		return false;
	}
	$exif_thumb = exif_thumbnail($source);
	if (!$exif_thumb) return [
		'error' => true,
		'error_msg' => 'EXIF thumbnail was not created.',
		'command' => sprintf('exif_thumbnail(%s)', $source)
	];
	$imagehandle = fopen($destination, 'a');
	fwrite($imagehandle, $exif_thumb);	//write the thumbnail image
	return true;
}

/**
 * create thumbnail out of source file with ExifTool
 *
 * @param string $filename
 * @param array $image
 * @return string
 */
function zz_image_exiftool($filename, $image) {
	global $zz_setting;
	$tmp_filename = tempnam($zz_setting['tmp_dir'], 'UPLOAD_');
	if (!empty($image['upload']['exiftool']['QuickTime']['CoverArt'])) {
		$field = 'CoverArt';
	} elseif (!empty($image['upload']['exiftool']['ID3v2_4']['Picture'])) {
		$field = 'Picture';
	} else {
		return false;
	}

	$cmd = $zz_conf['upload_tools']['exiftool_whereis'].' -b -%s "%s" > "%s"';
	$cmd = sprintf($cmd, $field, $filename, $tmp_filename);
	exec($cmd);
	return $tmp_filename;
}

/**
 * returns extension from any given filename
 *
 * @param string $filename
 * @return string $extension (part behind last dot or 'unknown')
 */
function zz_upload_file_extension($filename) {
	$filename = basename($filename);
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
 */
function zz_rename($oldname, $newname, $context = false) {
	global $zz_conf;
	if (!$newname) {
		zz_error_log([
			'msg_dev' => 'zz_rename(): No new filename given.',
			'level' => E_USER_WARNING
		]);
		return false;
	}
	if (!file_exists($oldname)) {
		zz_error_log([
			'msg_dev' => 'zz_rename(): File %s does not exist.',
			'msg_dev_args' => [$oldname],
			'level' => E_USER_WARNING
		]);
		return false;
	}
	if (!empty($zz_conf['upload_copy_for_rename'])) {
		// copy file, this also works in older php versions between partitions.
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
	zz_error_log([
		'msg_dev' => 'Copy/Delete for rename failed. Old filename: %s, new filename: %s',
		'msg_dev_args' => [$oldname, $newname],
		'level' => E_USER_NOTICE
	]);
	return false;
}

/**
 * check if upload max file size is not bigger than ini-setting
 *
 * @global array $zz_conf;
 */
function zz_upload_check_max_file_size() {
	global $zz_conf;
	
	if ($zz_conf['upload_MAX_FILE_SIZE'] > ZZ_UPLOAD_INI_MAXFILESIZE) {
		zz_error_log([
			'msg_dev' => 'Value for upload_max_filesize from php.ini is '
				.'smaller than value which is set in the script. The '
				.'value from php.ini will be used. To upload bigger files'
				.', please adjust your configuration settings.',
			'log_post_data' => false,
			'level' => E_USER_NOTICE
		]);
		$zz_conf['upload_MAX_FILE_SIZE'] = ZZ_UPLOAD_INI_MAXFILESIZE;
	}
}

/**
 * Adds logging capability to exec()-function call
 *
 * @param string $command
 * @param string $log_description will appear in logfile(s)
 * @return array
 *		array $output (optional, exec() $output)
 * 		array $return_var (optional, exec() $return_var)
 * @global array $zz_conf
 * @return bool
 */
function zz_upload_exec($command, $log_description) {
	global $zz_conf;
	global $zz_setting;
	
	// save stderr output to stdout ($output):
	$command .= ' 2>&1';

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
		error_log($log, 3, $zz_setting['log_dir'].'/upload.log');
	}
	return [$output, $return_var];
}

/**
 * show list of supported filetypes
 *
 * @param array $filetypes
 * @return string
 */
function zz_upload_supported_filetypes($filetypes) {
	$sql = sprintf(wrap_sql('filetypelist'), implode("', '", $filetypes));
	$filetypes = wrap_db_fetch($sql, 'filetype_id', 'numeric');
	$filetypes = wrap_translate($filetypes, 'filetypes', 'filetype_id');
	
	$text = zz_text('Supported filetypes:').' ';
	foreach ($filetypes as $index => $filetype) {
		if ($filetype['filetype_description'])
			$text .= sprintf('<abbr title="%s">%s</abbr>', $filetype['filetype_description'], $filetype['filetype']);
		else
			$text .= $filetype['filetype'];
		if ($index + 1 < count($filetypes)) $text .= ', ';
	}
	return $text;
}
