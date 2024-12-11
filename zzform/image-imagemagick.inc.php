<?php 

/**
 * zzform
 * Image manipulation with ImageMagick
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2006-2023 Gustaf Mossakowski
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
 *	$bla = [
 *	1 => ['type' => 'gif', 'ext' => 'gif', 'mime' => 'image/gif', 
 *		'imagick_format' => 'GIF', 'imagick_mode' = 'rw+', 
 *		'imagick_desc' => 'CompuServe graphics interchange format (LZW disabled)']
 *	2 => ['type' => 'gif', 'ext' => 'gif', 'mime' => 'image/gif', 
 *		'imagick_format' => 'GIF', 'imagick_mode' = 'rw+', 
 *		'imagick_desc' => 'CompuServe graphics interchange format (LZW disabled)']
 *	];
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
 * @return array $file
 *		string 'filetype', int 'width', int 'height', bool 'validated',
 *		string 'ext', array 'warnings'
 * @todo always fill out $file['ext']
 */
function zz_imagick_identify($filename, $file) {
	if (wrap_setting('zzform_graphics_library') !== 'imagemagick') return $file;
	if (!wrap_setting('zzform_upload_tools_identify')) return $file;
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	if (!file_exists($filename)) return zz_return(false);

	$command = zz_upload_binary('identify');
	// always check only first page if it's a multipage file (document, movie etc.)
	$time = filemtime($filename);
	$command = sprintf('%s -format "%%m ~ %%w ~ %%h ~ %%[opaque] ~ %%[colorspace] ~ %%[profile:icc] ~ %%z" "%s[0]"', $command, $filename);
	list($output, $return_var) = zz_upload_exec($command, 'ImageMagick identify');
	// identify has a bug at least with NEF images delegated to ufraw
	// where it changes the file modification date and time to the current time
	// note: filemtime() here would return the old time, so it's not possible to check that
	touch($filename, $time);
	if (!$output) return zz_return($file);
	if (wrap_setting('debug')) zz_debug('identify output', json_encode($output));
	
	// Error? Then first token ends with colon
	// e. g. Error: identify: mv:
	$result = array_pop($output);
	if (preg_match('~[a-z]+:~', substr($result, 0, strpos($result, ' ')))) {
		array_push($output, $result);
		$result = false;
	}
	if ($result === 'aborting...') return zz_return($file);
	if (count($output)) {
		// remove some warnings
		$removes = [];
		$removes[] = 'identify: unknown image property "%[profile:icc]"'; // no ICC profile = ok
		foreach ($output as $index => $line) {
		 	foreach ($removes as $remove) {
		 		if (substr($line, 0, strlen($remove)) !== $remove) continue;
		 		unset($output[$index]);
		 	}
		}
	}
	if (count($output) AND zz_upload_show_warning($file, 'identify')) {
		// e. g.  '   **** Warning:', 'GPL Ghostscript:'
		$file['warnings']['ImageMagick identify'] = $output;
	}
	if (!$result) return zz_return($file);

	if (substr($result, -1) !== ' ') $result .= ' '; // for explode
	$tokens = explode(' ~ ', $result);
	
	$ftype = strtolower($tokens[0]);
	if ($filetype_def = wrap_filetypes($ftype)) {
		$file['filetype'] = $filetype_def['filetype'];
		$file['ext'] = reset($filetype_def['extension']);
		$file['mime'] = reset($filetype_def['mime']);
		$file['validated'] = true;
	}

	if (count($tokens) >= 3) {
		// bug: XMP are said to be 1 x 1 px sRGB
		if (!in_array($file['filetype'], ['xmp'])) {
			$file['width'] = $tokens[1];
			$file['height'] = $tokens[2];
			$file['transparency'] = (isset($tokens[3]) AND in_array($tokens[3], ['False', 'false', false])) ? true : false;
			$file['colorspace'] = isset($tokens[4]) ? $tokens[4] : '';
			$file['icc_profile'] = isset($tokens[5]) ? $tokens[5] : '';
			$file['depth_bit'] = isset($tokens[6]) ? $tokens[6] : '';
		}
	}
	if (empty($file['ext'])) {
		if (isset($file['name'])) {
			$file['ext'] = substr($file['name'], strrpos($file['name'], '.') +1);
		}
	}
	return zz_return($file);
}

/**
 * checks whether filetype has multiple pages
 *
 * @param string $source
 * @param string $filetype
 * @param return $source
 */
function zz_imagick_check_multipage($source, $filetype, $image) {
	if (!$filetype)
		$filetype = substr($source, strrpos($source, '.') +1);
	$filetype_def = wrap_filetypes($filetype);
	if (empty($filetype_def['multipage']))
		return $source;

	if (isset($image['source_frame'])) {
		// here we start with page 1 as 1 not as 0, therefore remove 1
		if (!$image['source_frame']) {
			$source_frame = 0;
		} else {
			$source_frame = $image['source_frame'] - 1;
		}
	} else {
		// setting? if not, convert only first page or top layer
		$source_frame = $filetype['multipage_thumbnail_frame'] ?? 0;
	}
	$source = sprintf('%s[%d]', $source, $source_frame);
	return $source;
}

/**
 * Create image in grayscale
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $dest (temporary) name of destination file without extension
 * @param string $dest_ext file extension for destination image
 * @param array $image further information about the image
 * @return bool (false: no image was created; true: image was created)
 */
function zz_image_gray($source, $dest, $dest_ext, $image) {
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	$filetype = $image['upload']['filetype'] ?? '';
	$source = zz_imagick_check_multipage($source, $filetype, $image);
	$convert = zz_imagick_convert(
		'-colorspace gray '.$image['convert_options'],
		$source, $image['upload']['ext'], $filetype, $dest, $dest_ext, $image
	);

	if (wrap_setting('debug')) zz_debug('end');
	return $convert;
}

/**
 * Create image with custom properties
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $dest (temporary) name of destination file without extension
 * @param string $dest_ext file extension for destination image
 * @param array $image further information about the image
 *		here: 'image' array with key/value pairs for imagemagick options
 * @return bool (false: no image was created; true: image was created)
 */
function zz_image_custom($source, $dest, $dest_ext, $image) {
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	
	$image['convert_options'] = ''; // just custom options
	$image['no_options'] = true; // no standards from filetype
	$keys = ['image' => 'convert_options', 'image2' => 'convert_options_append'];
	foreach ($keys as $key_read => $key_write) {
		if (empty($image[$key_read])) continue;
		if (empty($image[$key_write])) $image[$key_write] = '';
		foreach ($image[$key_read] as $key => $value) {
			$image[$key_write] .= ' -'.$key;
			if ($value)
				$image[$key_write] .= ' '.$value;
		}
	}
	
	$filetype = $image['upload']['filetype'] ?? '';
	$source = zz_imagick_check_multipage($source, $filetype, $image);
	$convert = zz_imagick_convert(
		$image['convert_options'],
		$source, $image['upload']['ext'], $filetype, $dest, $dest_ext, $image
	);

	if (wrap_setting('debug')) zz_debug('end');
	return $convert;
}

/**
 * Create thumbnail image
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $dest (temporary) name of destination file without extension
 * @param string $dest_ext file extension for destination image
 * @param array $image further information about the image
 *		width, height
 * @return bool (false: no image was created; true: image was created)
 */
function zz_image_thumbnail($source, $dest, $dest_ext, $image) {
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
	
	$rotate = false;
	if (strstr($image['convert_options'], '-rotate ')) {
		preg_match_all('~-rotate ([0-9.]+)~', $image['convert_options'], $rotation);
		$rotation = end($rotation); // get only matches
		$rotation = end($rotation); // last occurence counts
		if ($rotation < 0) $rotation = 360 - $rotation;
		if ($rotation > 45 AND $rotation < 135) $rotate = true;
		elseif ($rotation > 225 AND $rotation < 315) $rotate = true;
	}
	if ($rotate) {
		$geometry = isset($image['height']) ? $image['height'] : '';
		$geometry .= isset($image['width']) ? 'x'.$image['width'] : '';
	} else {
		$geometry = isset($image['width']) ? $image['width'] : '';
		$geometry .= isset($image['height']) ? 'x'.$image['height'] : '';
	}
	$filetype = $image['upload']['filetype'] ?? '';
	$source = zz_imagick_check_multipage($source, $filetype, $image);
	$convert = zz_imagick_convert(
		sprintf('-thumbnail %s ', $geometry).$image['convert_options'],
		$source, $image['upload']['ext'], $filetype, $dest, $dest_ext, $image
	);

	if (wrap_setting('debug')) zz_debug('thumbnail creation '
		.($convert === true ? '' : 'un').'successful:<br>'.$dest);
	return zz_return($convert);
}

/**
 * Create 1:1 preview image in a web accessible format
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $dest (temporary) name of destination file without extension
 * @param string $dest_ext file extension for destination image
 * @param array $image further information about the image
 *		source_file (string, field name of source path), source_path (array,
 *		path-array ...), source_path_sql (string, SQL query ...), 
 *		update_from_source_field_name (string), update_from_source_value (string),
 *		field_name (string), path (array), required (boolean), options (array),
 *		options_sql (string), source_field (array), upload (array), type (string)
 *		action (string) name of function, source (int)
 * @return bool (false: no image was created; true: image was created)
 */
function zz_image_webimage($source, $dest, $dest_ext, $image) {
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);

	$filetype = $image['upload']['filetype'] ?? '';
	$filetype_def = wrap_filetypes($filetype);	
	$source = zz_imagick_check_multipage($source, $filetype, $image);
	$source_ext = $image['upload']['ext'];
	if (in_array($source_ext, ['pdf', 'eps'])) {
		if (!wrap_setting('zzform_upload_tools_gs')) return zz_return(false);
	}

	if (empty($image['convert_options']) 
		AND (!$source_ext OR in_array($source_ext, wrap_setting('zzform_webimages_by_extension')))
	) {
		// do not create an identical webimage of already existing webimage
		return zz_return(false);
	} elseif (!empty($image['upload']['transparency']) AND !empty($filetype_def['destination_filetype_transparency'])) {
		$dest_ext = $filetype_def['destination_filetype_transparency'];
	} elseif (!empty($filetype_def['destination_filetype'])) {
		$dest_ext = $filetype_def['destination_filetype'];
	} elseif (!empty($image['convert_options'])) {
		// keep original image, create a new modified image
		$dest_ext = $source_ext;
	} else {
		return zz_return(false);
	}
	$convert = zz_imagick_convert(
		$image['convert_options'],
		$source, $source_ext, $filetype, $dest, $dest_ext, $image
	);
	return zz_return($convert);
}

/**
 * Add options for ImageMagick depending on source extension
 *
 * no defaults from filetypes.cfg: wrap_setting('filetypes[pdf][convert]', '');
 * no defaults and own options: wrap_setting('filetypes[pdf][convert]', '-option1 -option2');
 * add own options to defaults: wrap_setting('filetypes[pdf][convert][]', '-option1 -option2');
 *
 * @param string $filetype
 * @param array $image (optional)
 * @return string options
 */
function zz_imagick_add_options($filetype, $image = []) {
	if (!empty($image['no_options'])) return '';

	$ext_options = [];
	$filetype_def = wrap_filetypes($filetype);

	// filetypes.cfg or wrap_setting() has convert?
	$convert_options = $filetype_def['convert'] ?? [];
	// no? then check if there are default options
	if (!$convert_options AND wrap_setting('zzform_upload_imagick_options_default'))
		$convert_options = wrap_setting('zzform_upload_imagick_options_default');

	// look for e. g. -colorspace sRGB and replace it with profiles
	foreach ($convert_options as $index => $option) {
		if (!str_starts_with($option, '-colorspace sRGB')) continue;
		if (empty($image['upload']['colorspace'])) continue;
		if ($image['upload']['colorspace'] === $option[1]) continue;
		if (empty($image['upload']['icc_profile'])) continue;
		if ($image['upload']['icc_profile'] === 'Artifex Software sRGB ICC Profile') continue;
		if (!wrap_setting('zzform_icc_profiles['.$image['upload']['icc_profile'].']')) {
			zz_error_log([
				'msg_dev' => 'No ICC profile found for %s',
				'msg_dev_args' => [$image['upload']['icc_profile']],
				'log_post_data' => false,
				'level' => E_USER_NOTICE
			]);
			continue;
		}
		// use profiles!
		$ext_options[] = sprintf('-profile "%s" -profile "%s"',
			wrap_setting('zzform_icc_profiles['.$image['upload']['icc_profile'].']'),
			wrap_setting('zzform_icc_profiles[sRGB]')
		);
		unset($convert_options[$index]);
	}
	$ext_options = array_merge($ext_options, $convert_options);
	if ($options = wrap_setting('zzform_upload_imagick_options_always'))
		$ext_options[] = $options;
	return implode(' ', $ext_options);
}

/**
 * Create cropped image
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $dest (temporary) name of destination file without extension
 * @param string $dest_ext file extension for destination image
 * @param array $image further information about the image
 *		width, height etc.
 * @param string $clipping defaults to center
 * @return bool (false: no image was created; true: image was created)
 */
function zz_image_crop($source, $dest, $dest_ext, $image, $clipping = 'center') {
	if (wrap_setting('debug')) zz_debug('start', __FUNCTION__);
// example: convert -thumbnail x240 -crop 240x240+140x0 reiff-pic09b.jpg test.jpg
	$dest_ratio = $image['width'] / $image['height'];
	if (!empty($image['upload']['height']) AND !empty($image['upload']['width'])) {
		$source_width = $image['upload']['width'];
		$source_height = $image['upload']['height'];
	} else {
		// @todo this won't work with PDF etc.
		$source_image = getimagesize($source);
		if (empty($source_image[0])) {
			$return = [
				'error' => true,
				'error_msg' => 'ImageMagick: cropped image was not created.',
				'command' => sprintf('getimagesize(%s)', $source)
			];
			return zz_return($return); // no height means no picture or error
		}
		$source_width = $source_image[0];
		$source_height = $source_image[1];
	}
	$source_ratio = $source_width / $source_height;
	if ($dest_ratio == $source_ratio) {
		$options = '-thumbnail %dx%d';
		$options = sprintf($options, $image['width'], $image['height']);
	} else {
		if ($dest_ratio < $source_ratio) {
			$new_width = floor($image['height'] * $source_ratio);
			$pos_y = 0;
			switch ($clipping) {
			case 'left':
				$gravity = 'West';
				$pos_x = 0; break;
			case 'right':
				$gravity = 'East';
				$pos_x = $new_width - $image['width']; break;
			default: // center
				$gravity = 'Center';
				$pos_x = floor(($new_width - $image['width']) / 2);
				break;
			}
		} else {
			$new_height = floor($image['width'] / $source_ratio);
			$pos_x = 0;
			switch ($clipping) {
			case 'top':
				$gravity = 'North';
				$pos_y = 0; break;
			case 'bottom':
				$gravity = 'South';
				$pos_y = $new_height - $image['height']; break;
			default: // center
				$gravity = 'Center';
				$pos_y = floor(($new_height - $image['height']) / 2);
				break;
			}
		}
		$options = '-thumbnail %dx%d^ -gravity %s -extent %dx%d';
		$options = sprintf(
			$options, $image['width'], $image['height'], $gravity,
			$image['width'], $image['height']
		);
	}
	$filetype = $image['upload']['filetype'] ?? '';
	$source = zz_imagick_check_multipage($source, $filetype, $image);
	if (!empty($image['watermark'])) {
		if (!empty($pos_x) OR !empty($pos_y)) {
			$options .= sprintf(' -geometry +%d+%d', $pos_x, $pos_y);
		}
	}
	$convert = zz_imagick_convert(
		$options.' '.$image['convert_options'],
		$source, $image['upload']['ext'], $filetype, $dest, $dest_ext, $image
	);
	return zz_return($convert);
}

/**
 * convert a file with ImageMagick
 *
 * @param string $options
 * @param string $source
 * @param string $source_ext
 * @param string $filetype
 * @param string $dest
 * @param string $dest_ext
 * @param array $image (optional)
 * @return bool
 */
function zz_imagick_convert($options, $source, $source_ext, $filetype, $dest, $dest_ext, $image = []) {
	// avoid errors like
	// libgomp: Thread creation failed: Resource temporarily unavailable
	// some ImageMagick versions have OpenMP support compiled into
	// it looks as it does not work with multiple cores correctly, so disable this.
	putenv("MAGICK_THREAD_LIMIT=1");
	putenv("OMP_NUM_THREADS=1");

	$command = zz_upload_binary('convert');

	$ext_options = zz_imagick_add_options($filetype, $image);
	// first extra options like auto-orient, then other options by script
	if ($ext_options) $command .= $ext_options.' ';
	if ($options) $command .= $options.' ';
	if (!empty($image['watermark'])) {
		if (!file_exists($image['watermark']))
			$image['watermark'] = wrap_setting('custom').'/watermarks/'.$image['watermark'];
		if (empty($image['convert_options_append']))
			$image['convert_options_append'] = '';
		$image['convert_options_append'] .= sprintf(' "%s" -composite', $image['watermark']);
	}

	$options_append = $image['convert_options_append'] ?? '';
	
	$command .= sprintf(' "%s" %s %s:"%s" ', $source, $options_append, $dest_ext, $dest);
	list($output, $return_var) = zz_upload_exec($command, 'ImageMagick convert');
	$return = true;
	if ($return_var === -1) {
		// function not found, or [function.exec]: Unable to fork ...
		// try again once, one second later
		sleep(1);
		list($output, $return_var) = zz_upload_exec($command, 'ImageMagick convert');
	}
	if ($output OR $return_var) {
		$return = [
			'error' => true,
			'error_msg' => 'ImageMagick: surrogate image was not created.',
			'exit_status' => $return_var,
			'output' => $output,
			'command' => $command
		];
	}
	return $return;
}
