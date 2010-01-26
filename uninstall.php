<?php
exit("To run the un-installer, comment out the 2nd line of this script!\n");

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
		exit('<br /><div style="color:red;">'.$msg.'</div></body></html>');
	}
}

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
				show_debug_message('<b>Could not delete file "'.$file.'"');
			}
		}
	}
	
	$dirs = array_reverse($dirs);
	
	foreach ($dirs as $dir) {
		if (!rmdir($dir)) {
			show_debug_message('<b>Could not delete directory "'.$dir.'"');
		}
	}
}

/* main */
	@set_magic_quotes_runtime(0);

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
			header('Location: '.dirname($_SERVER['SCRIPT_NAME']).'/uninstall_safe.php?SERVER_DATA_ROOT='.urlencode($_POST['SERVER_DATA_ROOT']).'&SERVER_ROOT='.urlencode($_POST['SERVER_ROOT']));
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
</head>
<body>
<table style="background: #527bbd; color: white; width: 100%; height: 50px;"><tr>
  <td><img src="images/fudlogo.gif" alt="" style="float:left;" border="0" /></td>
  <td><span style="color: #fff; font-weight: bold; font-size: x-large;">FUDforum Uninstall Wizard</span></td>
  <td> &nbsp; </td>
</tr></table>
<br />
<?php
	}

	if (isset($SERVER_DATA_ROOT)) {
		/* Sanity checks. */
		if (!is_dir($SERVER_DATA_ROOT)) {
			print_error('Forum Data Root directory "'.$SERVER_DATA_ROOT.'" does not exist!');
		}
		if (!empty($SERVER_ROOT) && !is_dir($SERVER_ROOT)) {
			print_error('Server Root directory "'.$SERVER_ROOT.'" does not exist!');
		}
		if (!file_exists($SERVER_DATA_ROOT . '/include/GLOBALS.php')) {
			print_error('Directory "'.$SERVER_DATA_ROOT.'" does not appear to be a Forum Data Root directory!');
		}
		if (!empty($SERVER_ROOT) && !file_exists($SERVER_ROOT . '/adm/admpanel.php')) {
			print_error('Directory "'.$SERVER_ROOT.'" does not appear to be a Server Root directory!');
		}		

		/* Read GLOBALS.php to determine database settings so that databases can be cleaned up. */
		$data = file_get_contents($SERVER_DATA_ROOT . '/include/GLOBALS.php');
		$s = strpos($data, '*/') + 2;
		$data = substr($data, $s, (strpos($data, 'DO NOT EDIT FILE BEYOND THIS POINT UNLESS YOU KNOW WHAT YOU ARE DOING', $s) - $s)) . ' */';
		eval($data);

		/* Drop database tables. */
		$dbinc = $SERVER_DATA_ROOT.'/sql/'.$DBHOST_DBTYPE.'/db.inc';
		if (!file_exists($dbinc)) {
			show_debug_message('DB driver not fount at '.$dbinc);
			show_debug_message('Database tables will be dropped!');
		} else {
			include_once $dbinc;

			foreach(get_fud_table_list() as $tbl) {
				show_debug_message('Dropping table '. $tbl);
				q('DROP TABLE '. $tbl);
			}
		}

		/* Remove symlinks first - unlink doesn't delete broken symlinks. */
		@unlink($SERVER_DATA_ROOT . '/scripts/GLOBALS.php');
		@unlink((empty($SERVER_ROOT) ? $SERVER_DATA_ROOT : $SERVER_DATA_ROOT) . '/GLOBALS.php');
		@unlink((empty($SERVER_ROOT) ? $SERVER_DATA_ROOT : $SERVER_DATA_ROOT) . '/adm/GLOBALS.php');
		
		/* Remove files on disk. */
		show_debug_message('Removing files in directory '.$SERVER_DATA_ROOT);
		fud_rmdir($SERVER_DATA_ROOT);
		if ($SERVER_ROOT != $SERVER_DATA_ROOT && $SERVER_ROOT) {
			show_debug_message('Removing files in directory '.$SERVER_ROOT);
			fud_rmdir($SERVER_ROOT);
		}

		print_error('FUDforum was successfully uninstalled!<br /><br />Sorry to see you go. If there is anything we can do to help, please let us know on the support forum at <a href="http://fudforum.org/">fudforum.org</a>.');
	}

	if (php_sapi_name() == 'cli') {
		show_debug_message('Usage: uninstall.php SERVER_DATA_ROOT SERVER_ROOT');
		print_error('Please run a full backup of your system before continuing!');
	} else {
?>
<div align="center">
<form name="uninstall" action="uninstall.php" method="post">
<table bgcolor="#000" align="center" border="0" cellspacing="0" cellpadding="1">
<tr><td><table bgcolor="#fff" border="0" cellspacing="1" cellpadding="4" align="center">
	<tr><td colspan="2" bgcolor="#e5ffe7"><font color="red"><b>This utility will uninstall FUDforum from the specified directories. Make sure that this is what you want to do, because once it runs there is no going back. We recommend running a full backup of your system before continuing.</b></font></td></tr>
	<tr bgcolor="#bff8ff"><td valign="top"><b>Forum Data Root</b><br /><font size="-1">This is the directory where you've installed the non-browseable forum files</font></td><td><input type="text" name="SERVER_DATA_ROOT" value="" size=40 /></td></tr>
	<tr bgcolor="#bff8ff"><td valign="top"><b>Server Root</b><br /><font size="-1">This is the directory where you've installed the browseable forum files. If it is the same as "Forum Data Root", you can leave this field blank.</font></td><td><input type="text" name="SERVER_ROOT" value="" size="40" /></td></tr>
	<tr><td colspan="2" align="center" bgcolor="white"><input type="submit" name="submit" value="uninstall" style="background:red; color:white; font-size: large;" /></td></tr>
</table></td></tr></table>
</form>
</div>
</body>
</html>
<?php
	}
?>
