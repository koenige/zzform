<?php 


function thumbnail ($image, $thumbw, $ausgabe, $image_path) {

	$convert = imagick_convert('-thumbnail', $thumbw, $image, $image_path);
	if ($convert) return true;
	else return false;
}

//function thumbnail_2 ($image, $new_image, $thumbw, $ausgabe, $type) {
function thumbnail_2 ($image, $thumbw, $ausgabe, $new_image) {
	$size = getimagesize($image);
	// 1 height, 0 width
	if ($size[1] > $size[0]) {
		$thumbh = $thumbw;
		$thumbw = (int)($thumbh * $size[0] / $size[1]);
	} else
		$thumbh = (int)($thumbw * $size[1] / $size[0]);

	$src_img = ImageCreateFromJPEG($image);
	$dst_img = ImageCreateTrueColor($thumbw, $thumbh);
	ImageCopyResampled($dst_img, $src_img, 0,0,0,0, $thumbw, $thumbh, $size[0], $size[1]);
	ImageJPEG($dst_img, $new_image);

//	if ($ausgabe)
//		echo ('<a href="'.$image.'"><IMG SRC="'.$thumbfilename.'" border="0" width="'.$thumbw.'" height="'.$thumbh.'"></a>');

	ImageDestroy($dst_img);
	return $new_image;
}

function imagick_convert($parameter, $size, $source, $destination) {
	$pathconvert = '/usr/local/bin/';
	$call_convert = $pathconvert.'convert -thumbnail '.$size.'x'.$size." \"$source\" \"$destination\"";
	$success = system($call_convert);
	//echo $call_convert.'<br>'; 
	if (file_exists($destination)) return true;
	else return false;
}

function imagick_rotate($file) {
	$pathconvert = '/usr/local/bin/';
	$call_convert = $pathconvert.'convert -rotate 90 "$file" "$file"';
	$success = system($call_convert);
	//convert -rotate 90 395.jpg 395.jpg
}
	
?>