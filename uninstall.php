<?php
exit('<h2>To run the uninstaller, comment out the 2nd line of this script!</h2>');

/***************************************************************************
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
***************************************************************************/

function fud_ini_get($opt)
{
	return (ini_get($opt) == '1' ? 1 : 0);
}

function show_debug_message($msg, $webonly=false)
{
	if (php_sapi_name() == 'cli') {
		if ($webonly) return;
		echo strip_tags($msg) ."\n";
	} else {
		echo $msg .'<br />';
		@ob_flush(); flush();
	}
}

function print_error($msg)
{
	if (php_sapi_name() == 'cli') {
		exit(strip_tags($msg) ."\n");
	} else {
		exit('<br /><div class="alert">'. $msg .'</div></td></tr></table></body></html>');
	}
}

/* Delete files and directories recursively. */
function fud_rmdir($dir)
{
	$dirs = array(realpath($dir));

	while (list(,$v) = each($dirs)) {
		if (!($files = glob($v.'/{.b*,.h*,.p*,.n*,.m*,*}', GLOB_BRACE|GLOB_NOSORT))) {
			continue;
		}
		foreach ($files as $file) {
			if (is_dir($file) && !is_link($file)) {
				$dirs[] = $file;
			} else if (!unlink($file)) {
				show_debug_message('<b>Could not delete file "'. $file .'"');
			}
		}
	}

	$dirs = array_reverse($dirs);

	foreach ($dirs as $dir) {
		if (!rmdir($dir)) {
			show_debug_message('<b>Could not delete directory "'. $dir .'"');
		}
	}
}

/* main */
	@set_magic_quotes_runtime(0);	// Depricated in PHP 5.3.

	define('SAFE_MODE', fud_ini_get('safe_mode'));

	/* Read command line parameters. */
	if (php_sapi_name() == 'cli' && (!empty($_SERVER['argv'][1]))) {
		$_POST['SERVER_DATA_ROOT'] = $_SERVER['argv'][1];
		if (!empty($_SERVER['argv'][2])) {
			$_POST['SERVER_ROOT'] = $_SERVER['argv'][2];
		}
	}

	if (count($_POST) && $_POST['SERVER_DATA_ROOT']) {
		if (SAFE_MODE && basename(__FILE__) != 'uninstall_safe.php') {
			$c = getcwd();
			copy($c . '/uninstall.php', $c . '/uninstall_safe.php');
			header('Location: '. dirname($_SERVER['SCRIPT_NAME']) .'/uninstall_safe.php?SERVER_DATA_ROOT='. urlencode($_POST['SERVER_DATA_ROOT']) .'&SERVER_ROOT='. urlencode($_POST['SERVER_ROOT']));
			exit;
		}
		$SERVER_DATA_ROOT = rtrim($_POST['SERVER_DATA_ROOT'], '\\/ ');
		$SERVER_ROOT = isset($_POST['SERVER_ROOT']) ? rtrim($_POST['SERVER_ROOT'], '\\/ ') : '';
	} else if (SAFE_MODE && !empty($_GET['SERVER_DATA_ROOT'])) {
		$SERVER_DATA_ROOT = rtrim($_GET['SERVER_DATA_ROOT'], '\\/ ');
		$SERVER_ROOT = isset($_POST['SERVER_ROOT']) ? rtrim($_GET['SERVER_ROOT'], '\\/ ') : '';
	}

	if (php_sapi_name() != 'cli') {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
	<title>FUDforum Uninstaller</title>
	<link rel="styleSheet" href="adm/style/adm.css" type="text/css" />
	<style>html, body { height: 95%; }</style>
</head>
<body>
<table class="headtable"><tr>
  <td><img src="images/fudlogo.gif" alt="" style="float:left;" border="0" /></td>
  <td><span class="linkhead">FUDforum Uninstall Wizard</span></td>
  <td> &nbsp; </td>
</tr></table>
<table class="maintable" style="height:100%;">
<tr>
<td class="maindata">
<?php
	}

	if (isset($SERVER_DATA_ROOT)) {
		/* Sanity checks. */
		if (!is_dir($SERVER_DATA_ROOT)) {
			print_error('Forum Data Root directory "'. $SERVER_DATA_ROOT .'" does not exist!');
		}
		if (!empty($SERVER_ROOT) && !is_dir($SERVER_ROOT)) {
			print_error('Server Root directory "'. $SERVER_ROOT .'" does not exist!');
		}
		if (!file_exists($SERVER_DATA_ROOT .'/include/GLOBALS.php')) {
			print_error('Directory "'. $SERVER_DATA_ROOT .'" does not appear to be a Forum Data Root directory!');
		}
		if (!empty($SERVER_ROOT) && !file_exists($SERVER_ROOT .'/adm/header.php')) {
			print_error('Directory "'. $SERVER_ROOT .'" does not appear to be a Server Root directory!');
		}

		/* Read GLOBALS.php to determine database settings so that databases can be cleaned up. */
		$data = file_get_contents($SERVER_DATA_ROOT .'/include/GLOBALS.php');
		$s = strpos($data, '*/') + 2;
		$data = substr($data, $s, (strpos($data, 'DO NOT EDIT FILE BEYOND THIS POINT UNLESS YOU KNOW WHAT YOU ARE DOING', $s) - $s)) .' */';
		eval($data);

		/* Check if debug mode is enabled. */
		$dryrun = empty($_POST['dryrun']) ? 0 : 1;
		if ($dryrun) {
			show_debug_message('<div class="tutor">Performing a mock uninstall. Don\'t worry, your forum will NOT be uninstalled!</div>');
		} else {
			show_debug_message('<h2>Uninstall actions:</h2>');
		}

		/* Drop database tables. */
		$dbinc = $SERVER_DATA_ROOT .'/sql/'. $DBHOST_DBTYPE .'/db.inc';
		if (!file_exists($dbinc)) {
			show_debug_message('DB driver not fount at '. $dbinc);
			show_debug_message('Database tables will be dropped!');
		} else {
			include_once $dbinc;

			foreach(get_fud_table_list() as $tbl) {
				show_debug_message('Dropping table '. $tbl);
				if (!$dryrun) {
					q('DROP TABLE '. $tbl);
				}
			}
		}

		/* Remove symlinks first - unlink doesn't delete broken symlinks. */
		if (!$dryrun) {
			@unlink($SERVER_DATA_ROOT .'/scripts/GLOBALS.php');
			@unlink((empty($SERVER_ROOT) ? $SERVER_DATA_ROOT : $SERVER_DATA_ROOT) .'/GLOBALS.php');
			@unlink((empty($SERVER_ROOT) ? $SERVER_DATA_ROOT : $SERVER_DATA_ROOT) .'/adm/GLOBALS.php');
		}

		/* Remove files on disk. */
		show_debug_message('Removing files in directory '. $SERVER_DATA_ROOT);
		if (!$dryrun) {
			fud_rmdir($SERVER_DATA_ROOT);
		}
		if ($SERVER_ROOT != $SERVER_DATA_ROOT && $SERVER_ROOT) {
			show_debug_message('Removing files in directory '. $SERVER_ROOT);
			if (!$dryrun) {
				fud_rmdir($SERVER_ROOT);
			}
		}

		show_debug_message('FUDforum was successfully uninstalled!');
		print_error('Sorry to see you go. If there is anything we can do to help, please let us know on the support forum at <a href="http://fudforum.org/">fudforum.org</a>.');
	}

	if (php_sapi_name() == 'cli') {
		show_debug_message('Usage: uninstall.php SERVER_DATA_ROOT SERVER_ROOT');
		print_error('Please run a full backup of your system before continuing!');
	} else {
?>
<br />
<p class="alert">
	This utility will uninstall FUDforum from the specified directories. 
	Make sure that this is what you want to do, because once it runs there is no going back. 
	We recommend running a full backup of your system before continuing.
</p>
<br />
<div align="center">
<form name="uninstall" action="uninstall.php" method="post">
<table cellspacing="1" cellpadding="4">
	<tr class="field"><td><b>Forum Data Root</b><br /><font size="-1">This is the directory where you've installed the non-browseable forum files</font></td><td><input type="text" name="SERVER_DATA_ROOT" value="" size=40 /></td></tr>
	<tr class="field"><td><b>Server Root</b><br /><font size="-1">This is the directory where you've installed the browseable forum files. If it is the same as "Forum Data Root", you can leave this field blank.</font></td><td><input type="text" name="SERVER_ROOT" value="" size="40" /></td></tr>
	<tr class="field"><td><b>Dry Run</b><br /><font size="-1">Do a mock uninstall. Forum will NOT be uninstalled.</font></td><td><input type="checkbox" name="dryrun" value="1" checked="checked" /></td></tr>
	<tr><td colspan="2" align="center"><input type="submit" name="submit" value="uninstall" class="button" style="background:red; color:white; font-size: x-large;" /></td></tr>
</table>
</form>
</div>
</td></tr></table>
</body>
</html>
<?php
	}
?>
