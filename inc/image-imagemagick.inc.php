<?php 

/*
	zzform Scripts
	image manipulation with imageMagick

	(c) Gustaf Mossakowski <gustaf@koenige.org> 2006

*/


function zz_image_gray($source, $destination) {
	$convert = imagick_convert('colorspace gray', $source.' '.$destination);
	if ($convert) return true;
	else return false;
}

function imagick_convert($options, $files, $more_options = false, $more_files = false) {
	$possible_paths = array('/usr/bin/', '/usr/sbin/', '/usr/local/bin', '/usr/phpbin');
	$path_convert = $possible_paths[0];
	$i = 1;
	while (!file_exists($path_convert.'/convert')) {
		$path_convert = $possible_paths[$i];
		$i++;
		if ($i > count($possible_paths)) break;
	}
	$call_convert = $path_convert.'/convert ';
	$call_convert.= '-'.$options.' ';
	$call_convert.= ' "'.str_replace(' ', '" "', $files).'" ';
	$success = exec($call_convert, $return);
	if ($return) {
		echo $call_convert;
		echo '<pre>';
		print_r($return);
		echo '</pre>';
	}
	if ($success) return true;
	else return false;
}


?>