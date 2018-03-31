<?php
/**
* copyright            : (C) 2001-2018 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/

/**
 * Replacement for PHP's readfile() to workaround memory and timeout problems
 * when downloading large files via slow connections.
 */
function fud_readfile($filename)
{
	$chunksize = 10*1024*1024; // How many bytes per chunk?
	
	if (($fh = fopen($filename, 'rb')) === FALSE) {
		return false;
	}

	while (!feof($fh)) {
		echo fread($fh, $chunksize);
		ob_flush();
		flush();
		sleep(0);
	}

	return fclose($fh);
}

/** Calculate space used by a given directory and its subdirectories. */
function fud_dir_space_usage($dir)
{
	$disk_space = 0;
	$dirs = array(realpath($dir));

	foreach ($dirs as &$v) {
		if (!($files = glob($v .'/*', GLOB_NOSORT))) {
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
			$disk_space += @filesize($f);
		}
	}

	return $disk_space;
}

/* Alternative implementation of fud_dir_space_usage(), however, it's too slow???
function fud_dir_space_usage_ALT($dir)
{
	$space = 0;
	$dir   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator($dir) );
	foreach ($dir as $file)
		$space += $file->getSize();
	return $space;
}
*/

/** Recursively create a given directory path.
  * If the directory alredy exists, it will do nothing.
  */
function fud_mkdir($path, $perms=0755)
{
	$u = umask(0);

	$dirs = array();
	while (!is_dir($path)) {
		$dirs[] = $path;
		$path = dirname($path);
		if (!$path || $path == '/') {
			break;
		}
	}
	foreach (array_reverse($dirs) as $dir) {
		if (!mkdir($dir, $perms)) {
			throw new Exception('Unable to create directory "'. $dir .'".');
		}
	}
	
	umask($u);
}

/** Recursively delete a given directory. */
function fud_rmdir($dir, $deleteRootToo=false)
{
	if(!$dh = @opendir($dir)) {
		return;
	}
	while (false !== ($obj = readdir($dh))) {
		if($obj == '.' || $obj == '..') {
			continue;
		}
		$file = $dir .'/'. $obj;
		if (is_dir($file) && !is_link($file)) {
			fud_rmdir($file, true);
		} else if (!unlink($file)) {
			return false;
		}
	}
	closedir($dh);

	if ($deleteRootToo) {
		@rmdir($dir);
	}

	return true;
}

/** Recursively copy a directory or individual files.
  * To copy a diretory:    fud_copy(srcdir, $destdir);
  * To copy a single file: fud_copy(srcdir, $destdir, '{file.ext}');
  */
function fud_copy($src, $dest, $mask='*', $recur=false)
{
	$src  = preg_replace('!/*$!', '', $src);
	$dest = preg_replace('!/*$!', '', $dest);

	$u = umask(0133);	// Limit to rw-r--r--
	$dirs = array($src);
	foreach ($dirs as &$v) {
		if (is_link($v)) {	// Don't copy symlinks.
			continue;
		}

		if (!is_readable($v)) {
			throw new Exception('Cannot open directory "'. $v .'".');
			return;
		}

		if (defined('GLOB_BRACE')) {		
			if (!($files = glob($v .'/'. $mask, GLOB_BRACE|GLOB_NOSORT))) {
				continue;
			}
		} else {
			$files = array();
			foreach (explode(',', trim($mask, '{}')) as $m) {
				$files = array_merge($files, (array)glob($v .'/'. $m, GLOB_NOSORT));
			}
			if (!$files) {
				continue;
			}
		}
		foreach ($files as $file) {
			$d = $dest .'/'. str_replace($src, '', $v) .'/'. basename($file);
		
			if (is_dir($file)) {
				if (!is_dir($d) && !mkdir($d)) {
					throw new Exception('Cannot create directory "'. $d .'".');
					return;
				}
				$dirs[] = $file;
				continue;
			}

			if (!@copy($file, $d) && !@file_exists($d)) {
				throw new Exception('Cannot copy "'. $file .'" to "'. $d .'".');
			}
		}		
	}
	umask($u);
}

/** Write ini settings file (use with the built-in 'parse_ini_file' function). */
function put_ini_file($ini, $file, $has_section = false, $write_to_file = true) {
	$fileContent = '';
	if (!empty($ini)) {
		foreach ($ini as $i=>$v) {
			if ($has_section){
				$fileContent .= "[".$i."]\n" . put_ini_file($v, $file, false, false);
			} else {
    				if (is_array($v)) {
					foreach($v as $t=>$m)
						$fileContent .= $i."[".$t."] = ".(is_numeric($m) ? $m : '"'.$m.'"') . "\n";
				} else {
					$fileContent .= $i . " = " . (is_numeric($v) ? $v : '"'.$v.'"') . "\n";
				}
			}
		}
	}
 
	if ($write_to_file && strlen($fileContent))
		return file_put_contents($file, $fileContent, LOCK_EX);
	else
		return $fileContent;
}

?>
