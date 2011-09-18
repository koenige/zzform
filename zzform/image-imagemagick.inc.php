<?php 

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2006-2010
// image manipulation with imageMagick


/*

// TODO
	identify -list Format
	      XMP*  rw-  Adobe XML metadata

	type			Filetype
	ext				Extension
	imagick_format	ImageMagick_Format
	imagick_mode	ImageMagick_Mode
	imagick_desc	ImageMagick_Description
	mime			MimeType

$bla = array(
	1 => array('type' => 'gif', 'ext' => 'gif', 'mime' => 'image/gif', 
	'imagick_format' => 'GIF', 'imagick_mode' = 'rw+', 
	'imagick_desc' => 'CompuServe graphics interchange format (LZW disabled)')
	2 => array('type' => 'gif', 'ext' => 'gif', 'mime' => 'image/gif', 
	'imagick_format' => 'GIF', 'imagick_mode' = 'rw+', 
	'imagick_desc' => 'CompuServe graphics interchange format (LZW disabled)')
);
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
 *		string 'ext'
 * @todo always fill out $file['ext']
 */
function zz_imagick_identify($filename, $file) {
	global $zz_conf;
	if ($zz_conf['graphics_library'] != 'imagemagick') return $file;
	if (!$zz_conf['upload_tools']['identify']) return $file;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	if (!file_exists($filename)) return zz_return(false);

	$command = zz_imagick_findpath('identify');
	// always check only first page if it's a multipage file (document, movie etc.)
	$command .= ' -format "%m %w %h" "'.$filename.'[0]"';
	exec($command, $output, $return_var);
	if ($zz_conf['modules']['debug']) zz_debug("identify command", $command);
	if (!$output) return zz_return($file);
	if ($zz_conf['modules']['debug']) zz_debug("identify output", json_encode($output));

	$tokens = explode(' ', $output[0]);
	$file['filetype'] = strtolower($tokens[0]);

	if (count($tokens) == 3) {
		$file['width'] = $tokens[1];
		$file['height'] = $tokens[2];
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
 * @param global $zz_conf
 * @param return $source
 */
function zz_imagick_check_multipage($source) {
	global $zz_conf;
	$source_extension = substr($source, strrpos($source, '.') +1);
	if (in_array($source_extension, $zz_conf['upload_multipage_images'])) {
		$source .= '['.(!empty($zz_conf['upload_multipage_which'][$source_extension])
			? $zz_conf['upload_multipage_which'][$source_extension] : '0')
			.']'; // convert only first page or top layer
	}

	return $source;
}

/**
 * Creates image in grayscale
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $destination (temporary) name of destination file without extension
 * @param string $dest_extension file extension for destination image
 * @param array $image further information about the image
 * @global array $zz_conf
 * @return bool (false: no image was created; true: image was created)
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_image_gray($source, $destination, $dest_extension = false, $image = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$source = zz_imagick_check_multipage($source);
	$convert = zz_imagick_convert('colorspace gray', '"'.$source.'" '.($dest_extension 
		? $dest_extension.':' : '').'"'.$destination.'"');

	if ($zz_conf['modules']['debug']) zz_debug("end");
	if ($convert) return true;
	else return false;
}

/**
 * Creates thumbnail image
 *
 * @param string $source (temporary) name of source file with extension
 * @param string $destination (temporary) name of destination file without extension
 * @param string $dest_extension file extension for destination image
 * @param array $image further information about the image
 *		width, height
 * @global array $zz_conf
 * @return bool (false: no image was created; true: image was created)
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_image_thumbnail($source, $destination, $dest_extension = false, $image = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
	
	$geometry = (isset($image['width']) ? $image['width'] : '');
	$geometry.= (isset($image['height']) ? 'x'.$image['height'] : '');
	$source = zz_imagick_check_multipage($source);
	$convert = zz_imagick_convert('thumbnail '.$geometry, '"'.$source.'" '.($dest_extension 
		? $dest_extension.':' : '').'"'.$destination.'"');

	if ($zz_conf['modules']['debug']) zz_debug('thumbnail creation '
		.($convert ? '' : 'un').'successful:<br>'.$destination);
	if ($convert) return zz_return(true);
	else return zz_return(false);
}

/**
 * Creates 1:1 preview image in a web accessible format
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
 * @author Gustaf Mossakowski, <gustaf@koenige.org>
 */
function zz_image_webimage($source, $destination, $dest_extension = false, $image = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$convert = false;
	$source = zz_imagick_check_multipage($source);
	$source_extension = substr($source, strrpos($source, '.') +1);

	if (!$source_extension OR !empty($zz_conf['webimages_by_extension'][$source_extension])) {
		$zz_conf['int']['no_image_action'] = true;
		return zz_return(false); // do not create an identical webimage of already existing webimage
	} elseif ($source_extension == 'pdf' OR $source_extension == 'eps') {
		if ($zz_conf['upload_tools']['ghostscript']) {
			$dest_extension = $zz_conf['upload_destination_filetype'][$source_extension];
			$convert = zz_imagick_convert('density '.$zz_conf['upload_pdf_density'], ' "'.$source.'" '.$dest_extension.':'.'"'.$destination.'"');
		}
	} elseif (!empty($zz_conf['upload_destination_filetype'][$source_extension])) {
		$dest_extension = $zz_conf['upload_destination_filetype'][$source_extension];
		$convert = zz_imagick_convert(false, ' "'.$source.'" '.$dest_extension.':'.'"'.$destination.'"');
	} else {
		$zz_conf['int']['no_image_action'] = true;
		return zz_return(false);
	}
	return zz_return($convert);
}

function zz_image_crop($source, $destination, $dest_extension = false, $image = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);
// example: convert -thumbnail x240 -crop 240x240+140x0 reiff-pic09b.jpg test.jpg
	$dest_ratio = $image['width'] / $image['height'];
	$source_image = getimagesize($source);
	if (empty($source_image[0])) {
		return zz_return(false); // no height means no picture or error
	}
	$source_ratio = $source_image[0] / $source_image[1]; // 0 = width, 1 = height
	if ($dest_ratio == $source_ratio)
		$options = 'thumbnail '.$image['width'].'x'.$image['height'];
	elseif ($dest_ratio < $source_ratio) {
		$new_width = floor($image['height']*$source_ratio);
		$pos_x = floor(($new_width - $image['width'])/2);
		$pos_y = 0;
		$options = 'thumbnail '.$new_width.'x'.$image['height']
			.' -crop '.$image['width'].'x'.$image['height'].'+'.$pos_x.'+'.$pos_y;
	} else {
		$new_height = floor($image['width']/$source_ratio);
		$pos_x = 0;
		$pos_y = floor(($new_height - $image['height'])/2);
		$options = 'thumbnail '.$image['width'].'x'.$new_height
			.' -crop '.$image['width'].'x'.$image['height'].'+'.$pos_x.'+'.$pos_y;
	}
	$source = zz_imagick_check_multipage($source);
	$convert = zz_imagick_convert($options, '"'.$source.'" '.($dest_extension 
		? $dest_extension.':' : '').'"'.$destination.'"');

	if ($convert) return zz_return(true);
	else return zz_return(false);
}

/**
 * converts a file with ImageMagick
 *
 * @param string $options
 * @param string $files
 * @global array $zz_conf
 *		string 'upload_imagick_options', bool 'modules'['debug'], bool 'debug'
 * @global array $zz_error
 * @return bool
 */
function zz_imagick_convert($options, $files) {
	global $zz_conf;
	global $zz_error;

	$command = zz_imagick_findpath('convert');

	if ($options) $options = '-'.$options;
	if (!empty($zz_conf['upload_imagick_options'])) $options .= ' '.$zz_conf['upload_imagick_options'];
	if ($options) $command .= $options.' ';

	$command .= ' '.$files.' ';
	$success = exec($command, $return, $return_var);
	if ($return) {
		$zz_error[] = array('msg_dev' => $command.': '.json_encode($return));
	}
	if ($return_var) {
		$zz_error[] = array('msg_dev' => $command.': '.json_encode($return_var));
	}
	if (!$return_var) return true;
	else return false;
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

?>