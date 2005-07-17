<?php 

function ImageCopyResampleBicubic (&$dst_img, &$src_img, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) {
	$palsize = ImageColorsTotal ($src_img);
 	for ($i = 0; $i < $palsize; $i++) {  // get palette.
  		$colors = ImageColorsForIndex ($src_img, $i);
  		ImageColorAllocate ($dst_img, $colors['red'], $colors['green'],$colors['blue']);
 	}
 
 	$scaleX = ($src_w - 1) / $dst_w;
 	$scaleY = ($src_h - 1) / $dst_h;
 
 	$scaleX2 = (int) ($scaleX / 2);
 	$scaleY2 = (int) ($scaleY / 2);

	for ($j = $src_y; $j < $dst_h; $j++) {
		$sY = (int) ($j * $scaleY);
		$y13 = $sY + $scaleY2;

		for ($i = $src_x; $i < $dst_w; $i++) {
			$sX = (int) ($i * $scaleX);
			$x34 = $sX + $scaleX2;

			$color1 = ImageColorsForIndex ($src_img, ImageColorAt ($src_img, $sX,$y13));
			$color2 = ImageColorsForIndex ($src_img, ImageColorAt ($src_img, $sX,$sY));
			$color3 = ImageColorsForIndex ($src_img, ImageColorAt ($src_img, $x34,$y13));
			$color4 = ImageColorsForIndex ($src_img, ImageColorAt ($src_img, $x34,$sY));

			$red = ($color1['red'] + $color2['red'] + $color3['red'] +

			$color4['red']) / 4;
			$green = ($color1['green'] + $color2['green'] + $color3['green'] +$color4['green']) / 4;
			$blue = ($color1['blue'] + $color2['blue'] + $color3['blue'] +$color4['blue']) / 4;

			ImageSetPixel ($dst_img, $i + $dst_x - $src_x, $j + $dst_y - $src_y,
			ImageColorClosest ($dst_img, $red, $green, $blue));
		}
	}
}


function thumbnail ($image, $new_image, $thumbh, $ausgabe) {

	$size = getimagesize($image);
	$thumbw = (int)($thumbh * $size[0] / $size[1]);

	$src_img = ImageCreateFromJPEG($image);
	$dst_img = ImageCreateTrueColor($thumbw, $thumbh);

	ImageCopyResampled($dst_img, $src_img, 0,0,0,0, $thumbw, $thumbh, $size[0], $size[1]);
	//ImageCopyResampleBicubic($dst_img, $src_img, 0,0,0,0, $thumbw, $thumbh, ImageSX($src_img), ImageSY($src_img));
	
	$thumbfilename = str_replace('.jpg', '.jpg', $new_image); 
	ImageJPEG($dst_img, $thumbfilename);

	//if ($ausgabe)
	//	echo ('<a href="'.$image.'"><IMG SRC="'.$thumbfilename.'" border="0" width="'.$thumbw.'" height="'.$thumbh.'"></a>');

	ImageDestroy($dst_img);

	return $thumbfilename;
}
	
?>