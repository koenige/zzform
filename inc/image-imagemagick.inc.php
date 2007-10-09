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
	if ($last_dir = array_pop($zz_conf['imagemagick_paths']) != '/notexistent') {
		$zz_conf['imagemagick_paths'][] = $zz_conf['imagemagick_paths'];
		$zz_conf['imagemagick_paths'][] = '/notexistent';
	}
	$path_identify = $zz_conf['imagemagick_paths'][0];
	$i = 1;
	while (!file_exists($path_identify.'/identify')) {
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
	$myimage['width'] = $image[2];
	$myimage['height'] = $image[3];
	$myimage['validated'] = true;
}


function zz_image_gray($source, $destination, $dest_extension = false, $image = false) {
	$convert = zz_imagick_convert('colorspace gray', '"'.$source.'" '.($dest_extension 
		? $dest_extension.':' : '').'"'.$destination.'"');
	if ($convert) return true;
	else return false;
}

function zz_image_thumbnail($source, $destination, $dest_extension = false, $image = false) {
	$geometry = (isset($image['width']) ? $image['width'] : '');
	$geometry.= (isset($image['height']) ? 'x'.$image['height'] : '');
	$convert = zz_imagick_convert('thumbnail '.$geometry, '"'.$source.'" '.($dest_extension 
		? $dest_extension.':' : '').'"'.$destination.'"');
	if ($convert) return true;
	else return false;
}

function zz_image_crop($source, $destination, $dest_extension = false, $image = false) {
// example: convert -thumbnail x240 -crop 240x240+140x0 reiff-pic09b.jpg test.jpg
	$dest_ratio = $image['width'] / $image['height'];
	if (!$image['upload']['height']) return false; // no height means no picture or error
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
	$convert = zz_imagick_convert($options	, '"'.$source.'" '.($dest_extension 
		? $dest_extension.':' : '').'"'.$destination.'"');
	if ($convert) return true;
	else return false;
}

function zz_imagick_convert($options, $files, $more_options = false, $more_files = false) {
	global $zz_conf;
	if ($last_dir = array_pop($zz_conf['imagemagick_paths']) != '/notexistent') {
		$zz_conf['imagemagick_paths'][] = $zz_conf['imagemagick_paths'];
		$zz_conf['imagemagick_paths'][] = '/notexistent';
	}
	$path_convert = $zz_conf['imagemagick_paths'][0];
	$i = 1;
	while (!file_exists($path_convert.'/convert')) {
		$path_convert = $zz_conf['imagemagick_paths'][$i];
		$i++;
		if ($i > count($zz_conf['imagemagick_paths']) -1) break;
	}
	if ($path_convert == '/notexistent') {
		echo 'Configuration error on server: ImageMagick could not be found. Paths tried: '.implode(', ', $zz_conf['imagemagick_paths']).'<br>';
		exit;
	}
	$call_convert = $path_convert.'/convert ';
	$call_convert.= '-'.$options.' ';
	$call_convert.= ' '.$files.' ';
	$success = exec($call_convert, $return, $return_var);
	if ($return) {
		echo $call_convert;
		echo '<pre>';
		print_r($return);
		echo '</pre>';
	}
	if (!$return_var) return true;
	else return false;
}


?>