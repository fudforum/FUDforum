<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	if (isset($usr->data)) {
		$image_text = $usr->data;
	} else {
		exit('Go away!');
	}

	/* Pick a random font from the forum's fonts directory. */
	$fonts = glob('images/fonts/*.ttf');
	$font = $fonts[ array_rand($fonts) ];

	/* Image width and height. */
	$width = isset($_GET['width']) ? (int) $_GET['width'] : 300;
	$height = isset($_GET['height']) ? (int) $_GET['height'] : 75;

	/* Set font size to 66% of image height. */
	$font_size = $height * 0.66;
	$img = imagecreate($width, $height) or die('Cannot initialize GD image');

	/* Allocate colors. */
	$background_color = imagecolorallocate($img, 192, 192, 192);	// gray
	$noise_color = imagecolorallocate($img, mt_rand(128,210), mt_rand(128,210), mt_rand(128,210));

	/* Random dots in background. */
	for ($i=0; $i<($width*$height)/3; $i++) {
		imagefilledellipse($img, mt_rand(0,$width), mt_rand(0,$height), 1, 1, $noise_color);
	}

	/* Random lines in background. */
	for ($i=0; $i<($width*$height)/150; $i++) {
		imageline($img, mt_rand(0,$width), mt_rand(0,$height), mt_rand(0,$width), mt_rand(0,$height), $noise_color);
	}

	/* Create textbox and add text. */
	$ttf_support = 1;
	$textbox = imagettfbbox($font_size, 0, $font, $image_text) or $ttf_support = 0;
	if ($ttf_support) {
		/* Print chars. */
		$x = $font_size;
		$y = ($height - $textbox[5])/2;
		for ($i = 0; $i < strlen($image_text); $i++) {
			$text_color = imagecolorallocate($img, mt_rand(1,127), mt_rand(0,127), mt_rand(0,127));
			imagettftext($img, mt_rand($font_size-5, $font_size+5), mt_rand(-$font_size/3,$font_size/3), mt_rand($x-$font_size/5,$x)+($i*$font_size), mt_rand($y-5, $y+5), $text_color, $font, $image_text[$i]) or die('Error in imagettftext function');
		}
	} else {
		$font = 5;
		$x = imagefontheight($font);
		$y = imagefontwidth($font);
		for ($i = 0; $i < strlen($image_text); $i++) {
			$text_color = imagecolorallocate($img, mt_rand(1,100), mt_rand(0,100), mt_rand(0,100));
			imagestring($img, $font, mt_rand($x-15,$x)+($i*$font_size), mt_rand($y-5,$y+5), $image_text[$i], $text_color);
		}
	}

	/* Fade image. */
	if (function_exists('imagefilter')) {
		imagefilter($img, IMG_FILTER_SMOOTH, 0.1);
	}

	/* Render image. */
	header('Content-type: image/png');
	header('Cache-control: no-cache, no-store');
	header('Content-Disposition:inline; filename=captcha.png');
	imagepng($img);
	imagedestroy($img);
?>
