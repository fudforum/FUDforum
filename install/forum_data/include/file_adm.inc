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

/* Calculate space used by a given directory and it's subdirectories. */
function dir_space_usage($dirp)
{
	$disk_space = 0;
	$dirs = array(realpath($dirp));

	while (list(,$v) = each($dirs)) {
		if (!($files = glob($v.'/*', GLOB_NOSORT))) {
			continue;	
		}
		foreach ($files as $f) {
			if (is_link($f)) {
				continue;
			}
			if (is_dir($f)) {
				$dirs[] = $f;
				continue;
			}
			$disk_space += filesize($f);
		}
	}

	return $disk_space;
}

/* Recursively create a given directory path. */
function fud_mkdir($path)
{
	$dirs = array();
	while (!is_dir($path)) {
		$dirs[] = $path;
		$path = dirname($path);
		if (!$path || $path == '/') {
			break;
		}
	}
	foreach (array_reverse($dirs) as $dir) {
		if (!mkdir($dir, 0755)) {
			fe('Failed to create "'. $dir .'" directory.');	
		}
	}
}

/* Recursively delete a given directory. */
function fud_rmdir($dir, $deleteRootToo=false)
{
	if(!$dh = @opendir($dir)) {
		return;
	}
	while (false !== ($obj = readdir($dh))) {
		if($obj == '.' || $obj == '..') {
			continue;
		}
		if (!@unlink($dir .'/'. $obj)) {
			fud_rmdir($dir .'/'. $obj, true);
		}
	}
	closedir($dh);
	if ($deleteRootToo) {
		@rmdir($dir);
	}
	return;
}

/* Unlock forum files. */
function fud_unlock($dir)
{
	$dirs = array(realpath($dir));

	while (list(,$v) = each($dirs)) {
		if (!($files = glob($v.'/{.b*,.h*,.p*,.n*,.m*,*}', GLOB_BRACE|GLOB_NOSORT))) {
			continue;
		}
		foreach ($files as $file) {
			if (is_dir($file) && !is_link($file)) {
				$perm = 0777;
				$dirs[] = $file;
			} else {
				$perm = 0666;
			}
			if (!chmod($file, $perm)) {
				echo '<b>Could not unlock path "'. $file .'"<br />';
			}
		}
	}
}

?>
