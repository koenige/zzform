<?php 

/*
	zzform Scripts
	image manipulation with imageMagick

	(c) Gustaf Mossakowski <gustaf@koenige.org> 2006

*/

//      GIF*  rw+  
//   GIF87*  rw-  CompuServe graphics interchange format (version 87a)

/*

$bla = array(
	1 => array('type' => 'gif', 'ext' => 'gif', 'mime' => 'image/gif', 
	'imagick_format' => 'GIF', 'imagick_mode' = 'rw+', 
	'imagick_desc' => 'CompuServe graphics interchange format (LZW disabled)')
	2 => array('type' => 'gif', 'ext' => 'gif', 'mime' => 'image/gif', 
	'imagick_format' => 'GIF', 'imagick_mode' = 'rw+', 
	'imagick_desc' => 'CompuServe graphics interchange format (LZW disabled)')
);
*/

function zz_imagick_identify($source) {
/*

	type			Filetype
	ext				Extension
	imagick_format	ImageMagick_Format
	imagick_mode	ImageMagick_Mode
	imagick_desc	ImageMagick_Description
	mime			MimeType

	identify -list Format
	      XMP*  rw-  Adobe XML metadata
	identify "phoomap bg.psd"
	phoomap bg.psd PSD 100x100+0+0 PseudoClass 256c 8-bit 23.9kb 0.000u 0:01

*/

	// 19.09.07 19:45

	global $zz_conf;
	if (!file_exists($source)) return false;
	$paths = $zz_conf['imagemagick_paths'];
	if ($last_dir = array_pop($paths) != '/notexistent') {
		$zz_conf['imagemagick_paths'][] = '/notexistent';
	}
	$path_identify = $zz_conf['imagemagick_paths'][0];
	$i = 1;
	while (!file_exists($path_identify.'/identify') AND !is_link($path_identify.'/identify')) {
		$path_identify = $zz_conf['imagemagick_paths'][$i];
		$i++;
		if ($i > count($zz_conf['imagemagick_paths']) -1) break;
	}
	if ($path_identify == '/notexistent') {
		echo 'Configuration error on server: ImageMagick "identify" could not be found. Paths tried: '.implode(', ', $zz_conf['imagemagick_paths']).'<br>';
		exit;
	}
	$call_identify = $path_identify.'/identify "'.$source.'"';	
	exec($call_identify, $output, $return_var);
	if (!$output) return false;
	$image = false;
	foreach ($output as $line) {
		// just check first line
		if (substr($line, 0, strlen($source)) == $source) {
			preg_match('/^ (\w+) (\d+)x(\d+).*$/', substr($line, strlen($source)), $image);
		}
	}	
	//$myimage = $bla[$image[1]];
	if (!empty($image[2])) $myimage['width'] = $image[2];
	if (!empty($image[3])) $myimage['height'] = $image[3];
	$myimage['validated'] = true;
}


function zz_image_gray($source, $destination, $dest_extension = false, $image = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();

	$convert = zz_imagick_convert('colorspace gray', '"'.$source.'" '.($dest_extension 
		? $dest_extension.':' : '').'"'.$destination.'"');

	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
	if ($convert) return true;
	else return false;
}

function zz_image_thumbnail($source, $destination, $dest_extension = false, $image = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
	
	$geometry = (isset($image['width']) ? $image['width'] : '');
	$geometry.= (isset($image['height']) ? 'x'.$image['height'] : '');
	$source_extension = substr($source, strrpos($source, '.') +1);
	if (in_array($source_extension, $zz_conf['upload_multipage_images'])) {
		$source .= '[0]'; // convert only first page or top layer
	}
	$convert = zz_imagick_convert('thumbnail '.$geometry, '"'.$source.'" '.($dest_extension 
		? $dest_extension.':' : '').'"'.$destination.'"');

	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
	if ($convert) return true;
	else return false;
}
/*

 * @param $source (string) temporary name of source file with file extension
 * @param $destination (string) temporary name of destination file without file extension
 * @param $dest_extension (string) file extension for destination image
 * @param $image (array) image array
 		source_file (string) field name of source path
 		source_path (array) path-array ...
 		source_path_sql (string) SQL query ...
 		update_from_source_field_name (string)
 		update_from_source_value (string)
 		field_name (string)
 		path (array)
 		required (boolean)
 		options (array)
 		options_sql (string)
 		source_field (array)
 		upload (array)
 		type (string)
 		action (string) name of function
 		source (int)
  * @return boolean (false: no image was created; true: image was created)
*/
function zz_image_webimage($source, $destination, $dest_extension = false, $image = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();

	global $zz_conf;
	$convert = false;
	$source_extension = substr($source, strrpos($source, '.') +1);
	if (in_array($source_extension, $zz_conf['upload_multipage_images'])) {
		$source .= '[0]'; // convert only first page or top layer
	}

	if (!$source_extension OR !empty($zz_conf['webimages_by_extension'][$source_extension])) {
		return false; // do not create an identical webimage of already existing webimage
	} elseif ($source_extension == 'pdf' OR $source_extension == 'eps') {
		if ($zz_conf['upload_tools']['ghostscript']) {
			$dest_extension = $zz_conf['upload_destination_filetype'][$source_extension];
			$convert = zz_imagick_convert('density '.$zz_conf['upload_pdf_density'], ' "'.$source.'" '.$dest_extension.':'.'"'.$destination.'"');
		}
	} elseif (!empty($zz_conf['upload_destination_filetype'][$source_extension])) {
		$dest_extension = $zz_conf['upload_destination_filetype'][$source_extension];
		$convert = zz_imagick_convert(false, ' "'.$source.'" '.$dest_extension.':'.'"'.$destination.'"');
	}
	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
	return $convert;
}

function zz_image_crop($source, $destination, $dest_extension = false, $image = false) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
// example: convert -thumbnail x240 -crop 240x240+140x0 reiff-pic09b.jpg test.jpg
	$dest_ratio = $image['width'] / $image['height'];
	if (empty($image['upload']['height'])) return false; // no height means no picture or error
	$source_ratio = $image['upload']['width'] / $image['upload']['height'];
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
	$convert = zz_imagick_convert($options, '"'.$source.'" '.($dest_extension 
		? $dest_extension.':' : '').'"'.$destination.'"');

	if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function, "end");
	if ($convert) return true;
	else return false;
}

function zz_imagick_convert($options, $files) {
	global $zz_conf;

	if ($options) $options = '-'.$options;
	if (!empty($zz_conf['upload_imagick_options'])) $options .= ' '.$zz_conf['upload_imagick_options'];
	$paths = $zz_conf['imagemagick_paths'];
	if ($last_dir = array_pop($paths) != '/notexistent') {
		$zz_conf['imagemagick_paths'][] = '/notexistent';
	}
	$path_convert = $zz_conf['imagemagick_paths'][0];
	$i = 1;
	while (!file_exists($path_convert.'/convert') AND !is_link($path_convert.'/convert')) {
		$path_convert = $zz_conf['imagemagick_paths'][$i];
		$i++;
		if ($i > count($zz_conf['imagemagick_paths']) -1) break;
	}
	if ($path_convert == '/notexistent') {
		echo 'Configuration error on server: ImageMagick could not be found. Paths tried: '.implode(', ', $zz_conf['imagemagick_paths']).'<br>';
		exit;
	}
	$call_convert = $path_convert.'/convert ';
	if ($options) $call_convert.= $options.' ';
	$call_convert.= ' '.$files.' ';
	$success = exec($call_convert, $return, $return_var);
	if ($return AND $zz_conf['modules']['debug'] AND $zz_conf['debug']) {
		echo $call_convert;
		zz_print_r($return);
	}
	if ($return_var AND $zz_conf['modules']['debug'] AND $zz_conf['debug']) {
		echo $call_convert;
		zz_print_r($return_var);
	}
	if (!$return_var) return true;
	else return false;
}


?>