<?php 

/*
	zzform Scripts
	image manipulation with imageMagick

	(c) Gustaf Mossakowski <gustaf@koenige.org> 2006

*/


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
	$possible_paths = array('/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/phpbin/im6/',
		'/usr/phpbin', '/notexistent'); // /usr/phpbin/im6/ for provider Artfiles, project qhz...
		// phpbin/im6 must be before phpbin
	$path_convert = $possible_paths[0];
	$i = 1;
	while (!file_exists($path_convert.'/convert')) {
		$path_convert = $possible_paths[$i];
		$i++;
		if ($i > count($possible_paths) -1) break;
	}
	if ($path_convert == '/notexistent') echo 'Configuration error on server: ImageMagick could not be found. Paths tried: '.implode(', ', $possible_paths).'<br>';
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