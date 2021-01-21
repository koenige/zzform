<?php 

/**
 * zzform
 * Image manipulation with GD library
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 *	work in progress, not to be seen as a replacment for ImageMagick
 *	- thumbnail: support for jpeg, gif, png
 *	
 *	Functions:
 *	zz_imagegd()	Main image function for GD library, not to be
 *					called directly, just through:
 *	-	zz_image_thumbnail()
 *	-	zz_image_crop()
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2007-2014, 2017 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * creates an image with the GD graphic library
 * 
 * @param string $source filename of source file
 * @param string $destination filename of destination file
 * @param array $params sizes and coordinates for image manipulation:
 *	dst_x = x-coordinate of destination point. 
 *	dst_y = y-coordinate of destination point. 
 *	src_x = x-coordinate of source point. 
 *	src_y = y-coordinate of source point. 
 *	dst_w = Destination width. 
 *	dst_h = Destination height. 
 *	src_w = Source width. 
 *	src_h = Source height. 
 * @param string $dest_extension extension of destination file
 * @param array $image upload image array
 * @return mixed bool true: image creation was successful, array: error message
 */
function zz_imagegd($source, $destination, $params, $dest_extension, $image) {
	global $zz_conf;
	if ($zz_conf['modules']['debug']) zz_debug('start', __FUNCTION__);

	$possible_filetypes = ['xpm', 'xbm', 'wbmp', 'png', 'jpeg', 'gif'];
	$source_filetype = $image['upload']['filetype'];
	if (!in_array($source_filetype, $possible_filetypes)) {
		$return = [
			'error' => true,
			'error_msg' => sprintf(
				'GD Library: filetype not allowed (%s). Allowed filetypes are: %s',
				$source_filetype, implode(', ', $possible_filetypes))
		];
		return zz_return($return);
	}
	$imagecreatefromfunction = 'ImageCreateFrom'.$source_filetype;
	$destination_image = ImageCreateTrueColor($params['dst_w'], $params['dst_h']);
	// Filetype, either set different by zzform or don't change the filetype
	$filetype = ($dest_extension ? $dest_extension : $image['upload']['filetype']);	
	switch ($filetype) {
	case 'jpg':
	case 'jpeg':
		$jpeg_quality = 75; // set the jpeg quality, around 60 is good enough for web
		// Create Image
		$source_image = $imagecreatefromfunction($source);
		Imagefill($destination_image, 0, 0, imagecolorallocate($destination_image, 255, 255, 255));
		// Resizing the Image
		ImageCopyResampled($destination_image, $source_image, $params['dst_x'],
			$params['dst_y'], $params['src_x'], $params['src_y'], $params['dst_w'], 
			$params['dst_h'], $params['src_w'], $params['src_h']);
		// Outputting the image, save it to $destination
		ImageJPEG($destination_image, $destination, $jpeg_quality);
		// we are finished!
		ImageDestroy($source_image);
		ImageDestroy($destination_image);
		if (file_exists($destination)) return zz_return(true);
		$return = [
			'error' => true,
			'error_msg' => 'GD Library: no JPEG image was created.',
			'command' => sprintf('ImageJPEG(%s, %s, %s)', $destination_image, $destination, $jpeg_quality)
		];
		return zz_return($return);
	case 'gif':
		// Create Image
		$source_image = $imagecreatefromfunction($source);
		// Transparency
		$transparent_index = imagecolortransparent($source_image);
		if ($transparent_index >= 0) { //it is transparent
			$transparent_color = imagecolorsforindex($source_image, $transparent_index);
			$transparent_index = imagecolorallocate($destination_image,
				$transparent_color['red'], $transparent_color['green'], $transparent_color['blue']
			);
			imagefill($destination_image, 0, 0, $transparent_index);
			imagecolortransparent($destination_image, $transparent_index);
		}
		// Resizing the Image
		ImageCopyResampled($destination_image, $source_image, $params['dst_x'],
			$params['dst_y'], $params['src_x'], $params['src_y'], $params['dst_w'], 
			$params['dst_h'], $params['src_w'], $params['src_h']);
		// Outputting the image, save it to $destination
		ImageGIF($destination_image, $destination);
		// we are finished!
		ImageDestroy($source_image);
		ImageDestroy($destination_image);
		if (file_exists($destination)) return zz_return(true);
		$return = [
			'error' => true,
			'error_msg' => 'GD Library: no GIF image was created.',
			'command' => sprintf('ImageGIF(%s, %s)', $destination_image, $destination)
		];
		return zz_return($return);
	case 'png':
		// Create Image
		$source_image = $imagecreatefromfunction($source);
		// Transparency
		imagealphablending($destination_image, false);
		$colorTransparent = imagecolorallocatealpha($destination_image, 0, 0, 0, 127);
		imagefill($destination_image, 0, 0, $colorTransparent);
		imagesavealpha($destination_image, true);
		// Resizing the Image
		ImageCopyResampled($destination_image, $source_image, $params['dst_x'],
			$params['dst_y'], $params['src_x'], $params['src_y'], $params['dst_w'], 
			$params['dst_h'], $params['src_w'], $params['src_h']);
		// Outputting the image, save it to $destination
		ImagePNG($destination_image, $destination);
		// we are finished!
		ImageDestroy($destination_image);
		ImageDestroy($source_image);
		if (file_exists($destination)) return zz_return(true);
		$return = [
			'error' => true,
			'error_msg' => 'GD Library: no PNG image was created.',
			'command' => sprintf('ImagePNG(%s, %s)', $destination_image, $destination)
		];
		return zz_return($return);
	}
	ImageDestroy($destination_image);
	$return = [
		'error' => true,
		'error_msg' => sprintf('GD Library: filetype not yet supported (%s).', $filetype)
	];
	return zz_return($return);
}


/**
 * creates a thumbnail image with the GD graphic library
 * 
 * @param string $source filename of source file
 * @param string $destination filename of destination file
 * @param string $dest_extension extension of destination file
 * @param array $image upload image array
 * @return bool true/false true: image creation was successful, false: unsuccessful
 */
function zz_image_thumbnail($source, $destination, $dest_extension, $image) {
	if (empty($image['upload']['width']) OR empty($image['upload']['height'])) {
		// no image width nor height, not an image
		return false;
	}
	// get new width and height, keep ratio
	$ratio = $image['upload']['width']/$image['upload']['height'];
	// ratio greater than 1: landscape, == 1 square, less than 1: portrait
	if ($ratio > 1) {
		$params['dst_w'] = $image['width'];
		$params['dst_h'] = intval($image['width']/$ratio);
		if ($params['dst_h'] > $image['height']) {
			$params['dst_w'] = $image['height'];
			$params['dst_h'] = intval($image['height']*$ratio);
		}
	} elseif ($ratio == 1) {
		$params['dst_w'] = $image['width'];
		$params['dst_h'] = $image['width'];
		if ($params['dst_h'] > $image['height']) {
			$params['dst_w'] = $image['height'];
			$params['dst_h'] = $image['height'];
		}
	} else {
		$params['dst_w'] = intval($image['height']*$ratio);
		$params['dst_h'] = $image['height'];
		if ($params['dst_w'] > $image['width']) {
			$params['dst_w'] = $image['width'];
			$params['dst_h'] = intval($image['width']/$ratio);
		}
	}
	// full image
	$source_image = getimagesize($source);
	$params['dst_x'] = 0;
	$params['dst_y'] = 0;
	$params['src_x'] = 0;
	$params['src_y'] = 0;
	$params['src_w'] = $source_image[0];
	$params['src_h'] = $source_image[1];
	return zz_imagegd($source, $destination, $params, $dest_extension, $image);
}

/**
 * creates a cropped thumbnail image with the GD graphic library
 * 
 * @param string $source filename of source file
 * @param string $destination filename of destination file
 * @param string $dest_extension extension of destination file
 * @param array $image upload image array
 * @param string $clipping (defaults to center)
 * @return bool true/false true: image creation was successful, false: unsuccessful
 */
 function zz_image_crop($source, $destination, $dest_extension, $image, $clipping = 'center') {
 	// Image will be resized exactly to the size as wanted
	$params['dst_w'] = $image['width'];
	$params['dst_h'] = $image['height'];
	$dest_ratio = $params['dst_w']/$params['dst_h'];

	// full image
	$params['dst_x'] = 0;	
	$params['dst_y'] = 0;

	// define cropping area, basics
	if (empty($image['upload']['width']) OR empty($image['upload']['height'])) {
		$source_image = getimagesize($source);
		if (!$source_image) {
			// no dimensions: no cropping is possible
			return false;
		}
		$params['src_w'] = $source_image[0];
		$params['src_h'] = $source_image[1];
	} else {
		$params['src_w'] = $image['upload']['width'];
		$params['src_h'] = $image['upload']['height'];
	}
	$params['src_x'] = 0;	// full image
	$params['src_y'] = 0;

	// get ratio of source image
	$source_ratio = $params['src_w']/$params['src_h'];
	// offset depending on sizes of source and destination images
	if ($source_ratio > $dest_ratio) { // crop something from left and right side
		$offset = ($params['src_w'] - $params['src_h'] / $params['dst_h'] * $params['dst_w']) / 2;
		if ($offset < 0) $offset = -$offset; // we need a positive offset
		switch ($clipping) {
			case 'left': $params['src_x'] = 0; break;
			case 'right': $params['src_x'] = $offset * 2; break;
			default: $params['src_x'] = floor($offset); break;
		}
		$params['src_w'] = floor($params['src_w'] - 2 * $offset); // not exact, but 1px +/-
	} elseif ($source_ratio < $dest_ratio) { // crop something from top and bottom
		$offset = ($params['src_h']-$params['src_w']/$params['dst_w']*$params['dst_h'])/2;
		if ($offset < 0) $offset = -$offset; // we need a positive offset
		switch ($clipping) {
			case 'top': $params['src_y'] = 0; break;
			case 'bottom': $params['src_y'] = $offset * 2; break;
			default: $params['src_y'] = floor($offset); break;
		}
		$params['src_h'] = floor($params['src_h'] - 2 * $offset); // not exact, but 1px +/-
	} // no changes if source ratio = destination ratio, then no cropping will occur
	
	return zz_imagegd($source, $destination, $params, $dest_extension, $image);
}
