<?php 

/*
	zzform Scripts
	image manipulation with GD

	work in progress, not usable so far

*/

function create_grey($input,$output) {
	$bild = imagecreatefromjpeg($input);
	$x = imagesx($bild);
	$y = imagesy($bild);
	for($i=0; $i<$y; $i++) {
		for($j=0; $j<$x; $j++) {
			$pos = imagecolorat($bild, $j, $i);
			$f = imagecolorsforindex($bild, $pos);
			$gst = $f["red"]*0.15 + $f["green"]*0.5 + $f["blue"]*0.35;
			$col = imagecolorresolve($bild, $gst, $gst, $gst);
			imagesetpixel($bild, $j, $i, $col);
		}
	}
	imagejpeg($bild,$output,90);
}

?>