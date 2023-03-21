<?php

/**
 * zzform
 * Captcha functions
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2019, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * log a captcha solution in a logfile or check against given solution
 *
 * format: timestamp zz_id captcha
 * @param int $zz_id
 * @param int $code existing code
 * @return void
 */
function zz_captcha_code($zz_id, $code = false) {
	require_once wrap_setting('core').'/file.inc.php';
	$logfile = wrap_setting('log_dir').'/captcha.log';
	if (!file_exists($logfile)) touch($logfile);

	$lines = file($logfile);
	// check if there is a captcha code already?
	$existing_digit = '';
	$delete_lines = [];
	foreach ($lines as $index => $line) {
		$line = explode(" ", $line);
		if (($line[0] + 86400 * 30) < time()) {
			$delete_lines[] = $index;
		}
		if ($line[1].'' !== $zz_id.'') continue;
		$existing_digit = trim($line[2]);
		if ($code) $delete_lines[] = $index;
		break;
	}
	if ($code) {
		if ($existing_digit.'' !== $code.'') return false;
		// delete correct result
		wrap_file_delete_line($logfile, $delete_lines);
		return true;
	}
	// delete old lines, older than 30 days
	wrap_file_delete_line($logfile, $delete_lines);
	if ($existing_digit) return $existing_digit;
	$digit = '';
	for ($x = 1; $x <= 5; $x++) {
	  $digit .= rand(0, 9);
	}
	error_log(sprintf("%s %s %s\n", time(), $zz_id, $digit), 3, $logfile);
	return $digit;
}

/**
 * show a captcha image
 * adapted from The Art of Web: www.the-art-of-web.com
 *
 * @param void
 * @return void
 */
function zz_captcha_image($zz_id) {
	// initialise image with dimensions of 120 x 30 pixels
	$image = @imagecreatetruecolor(120, 30) or wrap_quit(503, "Cannot Initialize new GD image stream");

	// set background and allocate drawing colours
	$background = imagecolorallocate($image, 0x66, 0x99, 0x66);
	imagefill($image, 0, 0, $background);
	$linecolor = imagecolorallocate($image, 0x99, 0xCC, 0x99);
	$textcolor1 = imagecolorallocate($image, 0x00, 0x00, 0x00);
	$textcolor2 = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);

	// draw random lines on canvas
	for($i=0; $i < 6; $i++) {
		imagesetthickness($image, rand(1,3));
		imageline($image, 0, rand(0,30), 120, rand(0,30), $linecolor);
	}

	// using a mixture of TTF fonts
	$fonts = [];
	if ($captcha_font_dir = wrap_setting('captcha_font_dir')) {
		if (is_dir($captcha_font_dir)) {
			$fonts = scandir($captcha_font_dir);
			foreach ($fonts as $index => $font) {
				if (substr($font, 0, 1) === '.') unset($fonts[$index]);
				elseif (substr($font, -4) !== '.ttf') unset($fonts[$index]);
				else $fonts[$index] = $captcha_font_dir.'/'.$font;
			}
		}
	}

	// add random digits to canvas
	$digit = str_split(zz_captcha_code($zz_id));
	for ($x = 15; $x <= 95; $x += 20) {
		$textcolor = (rand() % 2) ? $textcolor1 : $textcolor2;
		$num = array_shift($digit);
		if ($fonts) {
		    imagettftext($image, 16, rand(-30,30), $x, rand(20, 28), $textcolor, $fonts[array_rand($fonts)], $num);
		} else {
			imagechar($image, rand(3, 5), $x, rand(2, 14), $num, $textcolor);
		}
	}

	// display image and clean up
	header('Content-type: image/png');
	imagepng($image);
	imagedestroy($image);
	exit;
}

function zz_captcha_alt_text($captcha_code) {
	$substraction = rand(1, 10);
	$number = $captcha_code - $substraction;
	$split = str_split($number);
	$alt_code = [];
	foreach ($split as $pos) {
		$alt_code[] = zz_captcha_number_to_string($pos);
	}
	$text = sprintf(wrap_text('Please enter the following code: %s.'), implode(' ', $alt_code));
	$text .= ' '.sprintf(wrap_text('Please add %s to it.'), zz_captcha_number_to_string($substraction));
	return $text;
}

function zz_captcha_number_to_string($cipher) {
	switch ($cipher) {
		case 0: return wrap_text('zero');
		case 1: return wrap_text('one');
		case 2: return wrap_text('two');
		case 3: return wrap_text('three');
		case 4: return wrap_text('four');
		case 5: return wrap_text('five');
		case 6: return wrap_text('six');
		case 7: return wrap_text('seven');
		case 8: return wrap_text('eight');
		case 9: return wrap_text('nine');
		case 10: return wrap_text('ten');
	}
}

