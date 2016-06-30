<?php
exit('<h2>To run the uninstaller, comment out the 2nd line of this script!</h2>');

/***************************************************************************
* copyright            : (C) 2001-2013 Advanced Internet Designs Inc.
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

/** Print message to web browser or command line. */
function pf($msg='', $webonly=false)
{
	if (php_sapi_name() == 'cli') {
		if ($webonly) return;
		echo strip_tags($msg) ."\n";
	} else {
		echo $msg .'<br />';
		@ob_flush(); flush();
	}
}

/** Print error message and exit. */
function seterr($msg)
{
	if (php_sapi_name() == 'cli') {
		exit(strip_tags($msg) ."\n");
	} else {
		exit('<br /><div class="alert">'. $msg .'</div></td></tr></table></body></html>');
	}
}

/* main */
	define('SAFE_MODE', fud_ini_get('safe_mode'));

	/* Read command line parameters. */
	if (php_sapi_name() == 'cli' && (!empty($_SERVER['argv'][1]))) {
		$_POST['DATA_DISK'] = $_SERVER['argv'][1];
		if (!empty($_SERVER['argv'][2])) {
			$_POST['WWW_ROOT_DISK'] = $_SERVER['argv'][2];
		}
	}

	if (count($_POST) && $_POST['DATA_DISK']) {
		$dryrun = isset($_POST['dryrun']);
		if (SAFE_MODE && basename(__FILE__) != 'uninstall_safe.php') {
			$c = getcwd();
			copy($c .'/uninstall.php', $c .'/uninstall_safe.php');
			header('Location: '. dirname($_SERVER['SCRIPT_NAME']) .'/uninstall_safe.php?DATA_DISK='. urlencode($_POST['DATA_DISK']) .'&WWW_ROOT_DISK='. urlencode($_POST['WWW_ROOT_DISK']). '&dryrun='. $dryrun);
			exit;
		}
		$DATA_DISK = rtrim($_POST['DATA_DISK'], '\\/ ');
		$WWW_ROOT_DISK = isset($_POST['WWW_ROOT_DISK']) ? rtrim($_POST['WWW_ROOT_DISK'], '\\/ ') : '';
	} else if (SAFE_MODE && !empty($_GET['DATA_DISK'])) {
		$dryrun = $_GET['dryrun'];
		$DATA_DISK = rtrim($_GET['DATA_DISK'], '\\/ ');
		$WWW_ROOT_DISK = isset($_POST['WWW_ROOT_DISK']) ? rtrim($_GET['WWW_ROOT_DISK'], '\\/ ') : '';
	}

	if (php_sapi_name() != 'cli') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>FUDforum Uninstaller</title>
	<link rel="stylesheet" href="adm/style/adm.css" />
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

	if (isset($DATA_DISK)) {
		/* Sanity checks. */
		if (!is_dir($DATA_DISK)) {
			seterr('The data directory "'. $DATA_DISK .'" does not exist!');
		}
		if (!empty($WWW_ROOT_DISK) && !is_dir($WWW_ROOT_DISK)) {
			seterr('The web directory "'. $WWW_ROOT_DISK .'" does not exist!');
		}
		if (!file_exists($DATA_DISK .'/include/GLOBALS.php')) {
			seterr('Directory "'. $DATA_DISK .'" does not appear to be a Forum Data directory!');
		}
		if (!empty($WWW_ROOT_DISK) && !file_exists($WWW_ROOT_DISK .'/adm/header.php')) {
			seterr('Directory "'. $WWW_ROOT_DISK .'" does not appear to be a Forum Web directory!');
		}

		/* Read GLOBALS.php for database settings so that the db can be cleaned up. */
		$inc = $DATA_DISK .'/include/glob.inc';
		if (!file_exists($inc)) {
			seterr('Missing include file glob.inc at '. $inc);
		} else {
			require_once($DATA_DISK .'/include/glob.inc');
			read_global_settings();
		}

		/* Check if debug mode is enabled. */
		if ($dryrun) {
			pf('<div class="tutor">Performing a mock uninstall. Don\'t worry, your forum will NOT be uninstalled!</div>');
		} else {
			pf('<h2>Uninstall actions:</h2>');
		}

		/* Drop database tables. */
		$inc = $DATA_DISK .'/sql/'. $DBHOST_DBTYPE .'/db.inc';
		if (!file_exists($inc)) {
			pf('No DB driver found at '. $inc);
			pf('Database tables will not be dropped!');
		} else {
			include_once $inc;
			include_once $DATA_DISK .'/include/dbadmin.inc';

			foreach(get_fud_table_list() as $tbl) {
				pf('Dropping table '. $tbl);
				if (!$dryrun) {
					drop_table($tbl);
				}
			}
		}

		if (!file_exists($INCLUDE .'fs.inc')) {
			pf('Unable to load fs.inc (file functions).');
			pf('Files and directories will not be deleted!');
		} else {
			include_once $INCLUDE .'fs.inc';

			/* Remove symlinks first - unlink doesn't delete broken symlinks. */
			if (!$dryrun) {
				@unlink($DATA_DISK .'/scripts/GLOBALS.php');
				@unlink((empty($WWW_ROOT_DISK) ? $DATA_DISK : $DATA_DISK) .'/GLOBALS.php');
				@unlink((empty($WWW_ROOT_DISK) ? $DATA_DISK : $DATA_DISK) .'/adm/GLOBALS.php');
			}

			/* Remove files on disk. */
			pf('Removing files in directory '. $DATA_DISK);
			if (!$dryrun) {
				fud_rmdir($DATA_DISK, true);
			}
			if ($WWW_ROOT_DISK != $DATA_DISK && $WWW_ROOT_DISK) {
				pf('Removing files in directory '. $WWW_ROOT_DISK);
				if (!$dryrun) {
					fud_rmdir($WWW_ROOT_DISK, true);
				}
			}
		}

		pf('FUDforum was successfully uninstalled!');
		seterr('Sorry to see you go. If there is anything we can do to help, please let us know on the support forum at <a href="http://fudforum.org/">fudforum.org</a>.');
	}

	if (php_sapi_name() == 'cli') {
		pf('Usage: uninstall.php DATA_DISK WWW_ROOT_DISK');
		seterr('Please run a full backup of your system before continuing!');
	} else {
	
		/* If available, read GLOBALS.php.  */
		$DATA_DIR = $WWW_ROOT_DISK = '';
		if (file_exists('GLOBALS.php')) {
			@include('GLOBALS.php');
		}
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
	<tr class="field"><td><b>Data Directory</b><br /><font size="-1">This is the directory where you've installed the non-browseable forum files.</font></td><td><input type="text" name="DATA_DISK" value="<?php echo $DATA_DIR; ?>" size="40" /></td></tr>
	<tr class="field"><td><b>Web Directory</b><br /><font size="-1">This is the directory where you've installed the browseable forum files. If it is the same as the "Data Directory", you can leave this field empty.</font></td><td><input type="text" name="WWW_ROOT_DISK" value="<?php echo $WWW_ROOT_DISK; ?>" size="40" /></td></tr>
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
