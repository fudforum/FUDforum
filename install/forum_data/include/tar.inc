<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: tar.inc,v 1.2 2004/11/24 19:53:34 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function _tar_hdr_checksum($hdr)
{
	$unsigned_chksum = 0;
	for($i=0; $i<512; $i++) {
		$unsigned_chksum += ord($hdr[$i]);
	}
	for($i=0; $i<8; $i++) {
		$unsigned_chksum -= ord($hdr[148 + $i]);
	}
	$unsigned_chksum += 32 * 8;

	return $unsigned_chksum;
}

function add_to_tar($v, $di, $is_dir, $s)
{
	$hdr = '';
	$hdr .= str_pad(substr($v, $s), 100, "\0");
	$hdr .= str_pad(decoct($di[2]), 7, '0', STR_PAD_LEFT) . "\0";
	$hdr .= str_pad(decoct($di[4]), 7, '0', STR_PAD_LEFT) . "\0";
	$hdr .= str_pad(decoct($di[5]), 7, '0', STR_PAD_LEFT) . "\0";
	$hdr .= str_pad(decoct(($is_dir ? 0 : $di[7])), 11, '0', STR_PAD_LEFT) . "\0";
	$hdr .= str_pad(decoct($di[9]), 11, '0', STR_PAD_LEFT) . "\0";
	$hdr .= "        " . ($is_dir ? '5' : '0');
	$hdr .= str_repeat("\0", 100);
	$hdr .= "ustar  \0";
	$hdr .= str_repeat("\0", 32 + 32 + 8 + 8 + 155 + 12);

	// generate checksum
	$checksum = str_pad(decoct(_tar_hdr_checksum($hdr)), 6, '0', STR_PAD_LEFT);
	for($i = 0; $i < 6; $i++) {
		$hdr[148 + $i] = $checksum[$i];
	}
	$hdr[154] = "\0";
	$hdr[155] = " ";

	if (!$is_dir) {
		$pad = $di[7] % 512;
		if ($pad) {
			$pad = str_repeat("\0", 512 - $pad);
		} else {
			$pad = '';
		}
		return $hdr . file_get_contents($v) . $pad;
	}
	return $hdr;
}

function make_tar($dir)
{
	$out = '';

	$dir = realpath($dir);
	$l = strlen(dirname($dir)) + 1;

	$dirs = array($dir);
	while (list(,$v) = each($dirs)) {
		if (!($d = opendir($v))) {
			echo "Error: unable to read directory ".$v;
			continue;
		}
		$di = stat($v);
		$out .= add_to_tar($v, $di, 1, $l);

		// Add new tar formatted data to tar file contents
		while (($f = readdir($d))) {
			if ($f == '.' || $f == '..') {
				continue;
			}
			$path = $v . "/" . $f;

			$info = stat($path);
			if ($info[2] & 0100000) {
				$out .= add_to_tar($path, $info, 0, $l);
			}
			if ($info[2] & 0040000) { // directory
				$dirs[] = $path;
			}
		}
		closedir($d);
	}

	return $out;
}
?>