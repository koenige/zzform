<?php 


function thumbnail_2 ($image, $thumbw, $ausgabe, $image_path) {

	$convert = imagick_convert('-thumbnail', $thumbw, $image, $image_path);
	if ($convert) return true;
	else return false;
}

function imagick_convert($parameter, $size, $source, $destination) {
	$pathconvert = '/usr/local/bin/';
	$call_convert = $pathconvert.'convert -thumbnail '.$size.'x'.$size." \"$source\" \"$destination\"";
	$success = system($call_convert);
	echo $call_convert.'<br>'; 
	if (file_exists($destination)) return true;
	else return false;
}
	
?>