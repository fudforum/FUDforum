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

function pf($msg, $webonly=false)
{
	if (php_sapi_name() == 'cli') {
		if ($webonly) return;
		echo strip_tags($msg) ."\n";
	} else {
		echo $msg .'<br />';
		@ob_flush(); flush();
	}
}

function seterr($msg)
{
	if (php_sapi_name() == 'cli') {
		exit(strip_tags($msg) ."\n");
	} else {
		exit('<br /><div class="alert">'. $msg .'</div></td></tr></table></body></html>');
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
			copy($c .'/uninstall.php', $c .'/uninstall_safe.php');
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
			seterr('The data directory "'. $SERVER_DATA_ROOT .'" does not exist!');
		}
		if (!empty($SERVER_ROOT) && !is_dir($SERVER_ROOT)) {
			seterr('The web directory "'. $SERVER_ROOT .'" does not exist!');
		}
		if (!file_exists($SERVER_DATA_ROOT .'/include/GLOBALS.php')) {
			seterr('Directory "'. $SERVER_DATA_ROOT .'" does not appear to be a Forum Data directory!');
		}
		if (!empty($SERVER_ROOT) && !file_exists($SERVER_ROOT .'/adm/header.php')) {
			seterr('Directory "'. $SERVER_ROOT .'" does not appear to be a Forum Web directory!');
		}

		/* Read GLOBALS.php for database settings so that the db can be cleaned up. */
		$settings = file($SERVER_DATA_ROOT .'/include/GLOBALS.php');
		$settings = preg_grep('/^\s+\$\w+\s+=\s+.+;/', $settings);	// Only variables.
		$settings = implode('', $settings);
		eval($settings);

		/* Check if debug mode is enabled. */
		$dryrun = empty($_POST['dryrun']) ? 0 : 1;
		if ($dryrun) {
			pf('<div class="tutor">Performing a mock uninstall. Don\'t worry, your forum will NOT be uninstalled!</div>');
		} else {
			pf('<h2>Uninstall actions:</h2>');
		}

		/* Drop database tables. */
		$dbinc = $SERVER_DATA_ROOT .'/sql/'. $DBHOST_DBTYPE .'/db.inc';
		if (!file_exists($dbinc)) {
			pf('No DB driver found at '. $dbinc);
			pf('Database tables will not be dropped!');
		} else {
			include_once $dbinc;
			include_once $SERVER_DATA_ROOT .'/include/dbadmin.inc';

			foreach(get_fud_table_list() as $tbl) {
				pf('Dropping table '. $tbl);
				if (!$dryrun) {
					drop_table($tbl);
				}
			}
		}

		if (!file_exists($INCLUDE .'file_adm.inc')) {
			pf('Unable to load file functions.');
			pf('Files and directories will not be deleted!');
		} else {
			include_once $INCLUDE .'file_adm.inc';

			/* Remove symlinks first - unlink doesn't delete broken symlinks. */
			if (!$dryrun) {
				@unlink($SERVER_DATA_ROOT .'/scripts/GLOBALS.php');
				@unlink((empty($SERVER_ROOT) ? $SERVER_DATA_ROOT : $SERVER_DATA_ROOT) .'/GLOBALS.php');
				@unlink((empty($SERVER_ROOT) ? $SERVER_DATA_ROOT : $SERVER_DATA_ROOT) .'/adm/GLOBALS.php');
			}

			/* Remove files on disk. */
			pf('Removing files in directory '. $SERVER_DATA_ROOT);
			if (!$dryrun) {
				fud_rmdir($SERVER_DATA_ROOT, true);
			}
			if ($SERVER_ROOT != $SERVER_DATA_ROOT && $SERVER_ROOT) {
				pf('Removing files in directory '. $SERVER_ROOT);
				if (!$dryrun) {
					fud_rmdir($SERVER_ROOT, true);
				}
			}
		}

		pf('FUDforum was successfully uninstalled!');
		seterr('Sorry to see you go. If there is anything we can do to help, please let us know on the support forum at <a href="http://fudforum.org/">fudforum.org</a>.');
	}

	if (php_sapi_name() == 'cli') {
		pf('Usage: uninstall.php SERVER_DATA_ROOT SERVER_ROOT');
		seterr('Please run a full backup of your system before continuing!');
	} else {
?>
<br />
<p class="alert">
	This utility will uninstall FUDforum from the specified directories.
	Make sure that this is what you want to do, because once it runs, there is no going back.
	We recommend running a full backup of your system before continuing.
</p>
<br />
<div align="center">
<form name="uninstall" action="uninstall.php" method="post">
<table cellspacing="1" cellpadding="4">
	<tr class="field"><td><b>Data Directory</b><br /><font size="-1">This is the directory where you've installed the non-browseable forum files.</font></td><td><input type="text" name="SERVER_DATA_ROOT" value="" size=40 /></td></tr>
	<tr class="field"><td><b>Web Directory</b><br /><font size="-1">This is the directory where you've installed the browseable forum files. If it is the same as the "Data Directory", you can leave this field empty.</font></td><td><input type="text" name="SERVER_ROOT" value="" size="40" /></td></tr>
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
