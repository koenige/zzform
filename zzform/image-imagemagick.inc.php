<?php 

/**
 * zzform
 * Image manipulation with ImageMagick
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2006-2015 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 * @todo
 *	identify -list Format
 *	      XMP*  rw-  Adobe XML metadata
 *
 *	type			Filetype
 *	ext				Extension
 *	imagick_format	ImageMagick_Format
 *	imagick_mode	ImageMagick_Mode
 *	imagick_desc	ImageMagick_Description
 *	mime			MimeType
 *
 *	$bla = array(
 *	1 => array('type' => 'gif', 'ext' => 'gif', 'mime' => 'image/gif', 
 *		'imagick_format' => 'GIF', 'imagick_mode' = 'rw+', 
 *		'imagick_desc' => 'CompuServe graphics interchange format (LZW disabled)')
 *	2 => array('type' => 'gif', 'ext' => 'gif', 'mime' => 'image/gif', 
 *		'imagick_format' => 'GIF', 'imagick_mode' = 'rw+', 
 *		'imagick_desc' => 'CompuServe graphics interchange format (LZW disabled)')
 *	);
 */


/**
 * get information about file via ImageMagick's identify
 *
 * function call:
 *	identify "phoomap bg.psd"
 *	phoomap bg.psd PSD 100x100+0+0 PseudoClass 256c 8-bit 23.9kb 0.000u 0:01
 * result:
 *    [0] => /private/var/tmp/phpLjPhBP.mp4=>/var/tmp/magick-xN8MBYXd.pam[0]
 *    [1] => MP4
 *    [2] => 384x288
 *    [3] => 384x288+0+0
 *    [4] => 8-bit
 *    [5] => TrueColor
 *    [6] => DirectClass
 *    [7] => 165.9MB
 *    [8] => 0.350u
 *    [9] => 0:00.349
 * @param string $filename filename of file which needs to be identified
 * @param array $file
 * @global array $zz_conf
 * @return array $file
 *		string 'filetype', int 'width', int 'height', bool 'validated',
 *		string 'ext', array 'warnings'
 * @todo always fill out $file['ext']
 */
function zz_imagick_identify($filename, $file) {
	global $zz_conf;

	if ($zz_conf['graphics_library'] !== 'imagemagick') return $file;
	if (!$zz_conf['upload_tools']['identify']) return $file;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if (!file_exists($filename)) return zz_return(false);

	$command = zz_imagick_findpath('identify');
	// always check only first page if it's a multipage file (document, movie etc.)
	$command = sprintf('%s -format "%%m %%w %%h %%[colorspace]" "%s[0]"', $command, $filename);
	zz_upload_exec($command, 'ImageMagick identify', $output, $return_var);
	if (!$output) return zz_return($file);
	if ($zz_conf['modules']['debug']) zz_debug('identify output', json_encode($output));
	
	// Error? Then first token ends with colon
	// e. g. Error: identify: mv:
	$tokens = explode(' ', $output[0]);
	if (substr($tokens[0], -1) === ':') return zz_return($file);
	$result = array_pop($output);
	if ($result === 'aborting...') return zz_return($file);
	if (count($output)) {
		// e. g.  '   **** Warning:', 'GPL Ghostscript:'
		$file['warnings']['ImageMagick identify'] = $output;
	}

	$tokens = explode(' ', $result);
	$file['filetype'] = strtolower($tokens[0]);

	if (count($tokens) >= 3) {
		$file['width'] = $tokens[1];
		$file['height'] = $tokens[2];
		if (count($tokens) === 4) {
			$file['colorspace'] = $tokens[3];
		}
	}
	if (empty($file['ext'])) {
		if (isset($file['name'])) {
			$file['ext'] = substr($file['name'], strrpos($file['name'], '.') +1);
		}
	}
	$file['validated'] = true;
	return zz_return($file);
}

/**
 * checks whether filetype has multiple pages
 *
 * @param string $source
 * @param strinf $filetype
 * @param global $zz_conf
 * @param return $source
 */
function zz_imagick_check_multipage($source, $filetype, $image) {
	global $zz_conf;

	if (!in_array($filetype, $zz_conf['upload_multipage_images'])) {
		return $source;
	}
	if (!$filetype) {
		$filetype = substr($source, strrpos($source, '.') +1);
	}
	if (isset($image['source_frame'])) {
		// here we start with page 1 as 1 not as 0, therefore remove 1
		if (!$image['source_frame']) {
			$source_frame = 0;
		} else {
			$source_frame = $image['source_frame'] - 1;
		}
	} elseif (!empty($zz_conf['upload_multipage_which'][$filetype])) {
		$source_frame = $zz_conf['upload_multipage_which'][$filetype];
	} else {
		// convert only first page or top layer
		$source_frame = 0;
	}
	$source .= '['.$source_frame.']';
	return $source;
}

/**
 * Create image in grayscale
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $destination (temporary) name of destination file without extension
 * @param string $dest_extension file extension for destination image
 * @param array $image further information about the image
 * @global array $zz_conf
 * @return bool (false: no image was created; true: image was created)
 */
function zz_image_gray($source, $destination, $dest_extension, $image) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$filetype = !empty($image['upload']['filetype']) ? $image['upload']['filetype'] : '';
	$source = zz_imagick_check_multipage($source, $filetype, $image);
	$convert = zz_imagick_convert(
		'-colorspace gray '.$image['convert_options'],
		sprintf('"%s" %s:"%s"', $source, $dest_extension, $destination),
		$image['upload']['ext']
	);

	if ($zz_conf['modules']['debug']) zz_debug('end');
	return $convert;
}

/**
 * Create thumbnail image
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $destination (temporary) name of destination file without extension
 * @param string $dest_extension file extension for destination image
 * @param array $image further information about the image
 *		width, height
 * @global array $zz_conf
 * @return bool (false: no image was created; true: image was created)
 */
function zz_image_thumbnail($source, $destination, $dest_extension, $image) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	
	$geometry = isset($image['width']) ? $image['width'] : '';
	$geometry .= isset($image['height']) ? 'x'.$image['height'] : '';
	$filetype = !empty($image['upload']['filetype']) ? $image['upload']['filetype'] : '';
	$source = zz_imagick_check_multipage($source, $filetype, $image);
	$convert = zz_imagick_convert(
		sprintf('-thumbnail %s ', $geometry).$image['convert_options'],
		sprintf('"%s" %s:"%s"', $source, $dest_extension, $destination),
		$image['upload']['ext']
	);

	if ($zz_conf['modules']['debug']) zz_debug('thumbnail creation '
		.($convert === true ? '' : 'un').'successful:<br>'.$destination);
	return zz_return($convert);
}

/**
 * Create 1:1 preview image in a web accessible format
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $destination (temporary) name of destination file without extension
 * @param string $dest_extension file extension for destination image
 * @param array $image further information about the image
 *		source_file (string, field name of source path), source_path (array,
 *		path-array ...), source_path_sql (string, SQL query ...), 
 *		update_from_source_field_name (string), update_from_source_value (string),
 *		field_name (string), path (array), required (boolean), options (array),
 *		options_sql (string), source_field (array), upload (array), type (string)
 *		action (string) name of function, source (int)
 * @global array $zz_conf
 * @return bool (false: no image was created; true: image was created)
 */
function zz_image_webimage($source, $destination, $dest_extension, $image) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$filetype = !empty($image['upload']['filetype']) ? $image['upload']['filetype'] : '';
	$source = zz_imagick_check_multipage($source, $filetype, $image);
	$source_extension = $image['upload']['ext'];
	if (in_array($source_extension, array('pdf', 'eps'))) {
		if (!$zz_conf['upload_tools']['ghostscript']) return zz_return(false);
	}

	if (empty($image['convert_options']) 
		AND (!$source_extension OR !empty($zz_conf['webimages_by_extension'][$source_extension]))
	) {
		// do not create an identical webimage of already existing webimage
		return zz_return(false);
	} elseif (!empty($zz_conf['upload_destination_filetype'][$source_extension])) {
		$dest_extension = $zz_conf['upload_destination_filetype'][$source_extension];
	} elseif (!empty($image['convert_options'])) {
		// keep original image, create a new modified image
		$dest_extension = $source_extension;
	} else {
		return zz_return(false);
	}
	$convert = zz_imagick_convert(
		$image['convert_options'],
		sprintf('"%s" %s:"%s"', $source, $dest_extension, $destination),
		$source_extension
	);
	return zz_return($convert);
}

/**
 * Create cropped image
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $destination (temporary) name of destination file without extension
 * @param string $dest_extension file extension for destination image
 * @param array $image further information about the image
 *		width, height etc.
 * @global array $zz_conf
 * @return bool (false: no image was created; true: image was created)
 */
function zz_image_crop($source, $destination, $dest_extension, $image) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
// example: convert -thumbnail x240 -crop 240x240+140x0 reiff-pic09b.jpg test.jpg
	$dest_ratio = $image['width'] / $image['height'];
	if (!empty($image['upload']['height']) AND !empty($image['upload']['width'])) {
		$source_width = $image['upload']['width'];
		$source_height = $image['upload']['height'];
	} else {
		// @todo this won't work with PDF etc.
		$source_image = getimagesize($source);
		if (empty($source_image[0])) {
			$return = array(
				'error' => true,
				'error_msg' => 'ImageMagick: cropped image was not created.',
				'command' => sprintf('getimagesize(%s)', $source)
			);
			return zz_return($return); // no height means no picture or error
		}
		$source_width = $source_image[0];
		$source_height = $source_image[1];
	}
	$source_ratio = $source_width / $source_height;
	if ($dest_ratio == $source_ratio) {
		$options = '-thumbnail %dx%d';
		$options = sprintf($options, $image['width'], $image['height']);
	} elseif ($dest_ratio < $source_ratio) {
		$new_width = floor($image['height']*$source_ratio);
		$pos_x = floor(($new_width - $image['width']) / 2);
		$pos_y = 0;
		$options = '-thumbnail %dx%d -crop %dx%d+%d+%d';
		$options = sprintf(
			$options, $new_width, $image['height'], $image['width'],
			$image['height'], $pos_x, $pos_y
		);
	} else {
		$new_height = floor($image['width']/$source_ratio);
		$pos_x = 0;
		$pos_y = floor(($new_height - $image['height']) / 2);
		$options = '-thumbnail %dx%d -crop %dx%d+%d+%d';
		$options = sprintf(
			$options, $image['width'], $new_height, $image['width'],
			$image['height'], $pos_x, $pos_y
		);
	}
	$filetype = !empty($image['upload']['filetype']) ? $image['upload']['filetype'] : '';
	$source = zz_imagick_check_multipage($source, $filetype, $image);
	$convert = zz_imagick_convert(
		$options.' '.$image['convert_options'],
		sprintf('"%s" %s:"%s"', $source, $dest_extension, $destination),
		$image['upload']['ext']
	);
	return zz_return($convert);
}

/**
 * convert a file with ImageMagick
 *
 * @param string $options
 * @param string $files
 * @param string $source_extension
 * @global array $zz_conf
 *		string 'upload_imagick_options', bool 'modules'['debug'], bool 'debug',
 *		array 'upload_imagick_options_for'
 * @return bool
 */
function zz_imagick_convert($options, $files, $source_extension) {
	global $zz_conf;

	// avoid errors like
	// libgomp: Thread creation failed: Resource temporarily unavailable
	// some ImageMagick versions have OpenMP support compiled into
	// it looks as it does not work with multiple cores correctly, so disable this.
	putenv("MAGICK_THREAD_LIMIT=1");
	putenv("OMP_NUM_THREADS=1");

	$command = zz_imagick_findpath('convert');

	$ext_options = '';
	if (empty($zz_conf['upload_imagick_options_no_defaults'][$source_extension])) {
		if (!empty($zz_conf['file_types'][$source_extension]['convert'])) {
			$ext_options .= ' '.implode(' ', $zz_conf['file_types'][$source_extension]['convert']);
		}
	}
	if (!empty($zz_conf['upload_imagick_options_for'][$source_extension])) {
		$ext_options .= ' '.$zz_conf['upload_imagick_options_for'][$source_extension];
	} elseif (!empty($zz_conf['upload_imagick_options'])) {
		$ext_options .= ' '.$zz_conf['upload_imagick_options'];
	}
	// first extra options like auto-orient, then other options by script
	if ($ext_options) $command .= $ext_options.' ';
	if ($options) $command .= $options.' ';

	$command .= ' '.$files.' ';
	zz_upload_exec($command, 'ImageMagick convert', $output, $return_var);
	$return = true;
	if ($return_var === -1) {
		// function not found, or [function.exec]: Unable to fork ...
		// try again once, one second later
		sleep(1);
		zz_upload_exec($command, 'ImageMagick convert', $output, $return_var);
	}
	if ($output OR $return_var) {
		$return = array(
			'error' => true,
			'error_msg' => 'ImageMagick: surrogate image was not created.',
			'exit_status' => $return_var,
			'output' => $output,
			'command' => $command
		);
	}
	return $return;
}

/**
 * find ImageMagick path
 *
 * @param string $command name of ImageMagick command
 * @global array $zz_conf
 *		imagemagick_path_unchecked, imagemagick_paths
 * @return string $command correct path and command
 */
function zz_imagick_findpath($command = 'convert') {
	global $zz_conf;

	if (!empty($zz_conf['imagemagick_path_unchecked'])) {
		// don't do checks
		$command = $zz_conf['imagemagick_path_unchecked'].'/'.$command.' ';
		return $command;
	}

	$paths = $zz_conf['imagemagick_paths'];
	if ($last_dir = array_pop($paths) != '/notexistent') {
		$zz_conf['imagemagick_paths'][] = '/notexistent';
	}
	$path = $zz_conf['imagemagick_paths'][0];
	$i = 1;
	while (!file_exists($path.'/'.$command) AND !is_link($path.'/'.$command)) {
		$path = $zz_conf['imagemagick_paths'][$i];
		$i++;
		if ($i > count($zz_conf['imagemagick_paths']) -1) break;
	}
	if ($path == '/notexistent') {
		echo '<p>Configuration error on server: ImageMagick <code>'.$command
			.'</code> could not be found. Paths tried: '
			.implode(', ', $zz_conf['imagemagick_paths']).'</p>';
		exit;
	}
	$command = $path.'/'.$command.' ';
	return $command;
}

/**
 * ImageMagick version
 *
 * @param void
 * @return string
 */
function zz_imagick_version() {
	global $zz_conf;
	$command = zz_imagick_findpath();
	$command .= ' --version';
	exec($command, $output);
	if (!$output) return '';
	return implode("  \n", $output);
}

/**
 * GhostScript version
 *
 * @param void
 * @return string
 */
function zz_ghostscript_version() {
	global $zz_conf;
	$command = zz_imagick_findpath('gs');
	$command .= ' --help';
	exec($command, $output);
	if (!$output) return '';
	return implode("  \n", $output);
}
